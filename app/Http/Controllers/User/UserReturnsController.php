<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserReturnsController extends Controller
{
    /**
     * Lists the signed-in user's currently borrowed equipment: approved
     * borrow requests that have not been settled with a Returned
     * borrow_transaction yet.
     */
    public function index(): View
    {
        $userId = auth('user')->user()->user_id;

        $returnItems = BorrowRequest::with(['equipment.category', 'transaction'])
            ->where('user_id', $userId)
            ->where('overall_status', 'Approved')
            ->orderByDesc('request_id')
            ->get()
            ->reject(fn ($request) => $request->transaction && $request->transaction->status === 'Returned')
            ->values();

        return view('user.returns', compact('returnItems'));
    }

    /**
     * Processes a return submitted from the Return Item page. Marks the
     * borrowing record (borrow_transaction) as Returned with the reported
     * condition, restores the equipment stock/status, and writes an audit
     * trail entry — all inside one transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'integer', 'min:1'],
            'condition' => ['required', 'in:Good,Damaged,Lost'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $userId = auth('user')->user()->user_id;

        $result = DB::transaction(function () use ($request, $data, $userId) {
            $borrow = BorrowRequest::with(['equipment', 'transaction'])
                ->where('request_id', $data['request_id'])
                ->lockForUpdate()
                ->first();

            if (!$borrow) {
                return ['code' => 404, 'message' => 'Borrow record not found.'];
            }

            if ((int) $borrow->user_id !== (int) $userId) {
                return ['code' => 403, 'message' => 'You are not allowed to return this item.'];
            }

            if ($borrow->overall_status !== 'Approved') {
                return ['code' => 400, 'message' => 'Only approved borrows can be returned.'];
            }

            $transaction = $borrow->transaction;
            if ($transaction && $transaction->status === 'Returned') {
                return ['code' => 400, 'message' => 'This item has already been returned.'];
            }

            $today = now()->toDateString();
            $remarks = trim($data['remarks'] ?? '');
            $conditionText = mb_substr(
                $data['condition'] . ($remarks !== '' ? ' — ' . $remarks : ''),
                0,
                150
            );

            // A pre-existing transaction was created when the request was
            // approved, i.e. stock was decremented at that point and must be
            // restored now. Legacy approved rows have no transaction and never
            // touched stock, so their return must not inflate the counts.
            $restoreStock = $transaction !== null;

            if ($transaction) {
                $transaction->update([
                    'return_date' => $today,
                    'condition_on_return' => $conditionText,
                    'status' => 'Returned',
                ]);
            } else {
                BorrowTransaction::create([
                    'request_id' => $borrow->request_id,
                    'borrow_date' => $borrow->borrow_date ?: $borrow->date_needed,
                    'due_date' => $borrow->due_date ?: $borrow->return_date,
                    'return_date' => $today,
                    'condition_on_return' => $conditionText,
                    'status' => 'Returned',
                ]);
            }

            if ($restoreStock) {
                $equipment = Equipment::where('equipment_id', $borrow->equipment_id)
                    ->lockForUpdate()
                    ->first();

                if ($equipment) {
                    $equipment->available_qty = min(
                        (int) $equipment->total_qty,
                        (int) $equipment->available_qty + max(1, (int) $borrow->quantity)
                    );

                    // Only revive stock-driven unavailability; never override
                    // admin-set states like On Hold / Under Maintenance.
                    if ($equipment->status === 'Unavailable' && $equipment->available_qty > 0) {
                        $equipment->status = 'Available';
                    }

                    $equipment->save();
                }
            }

            AuditTrail::create([
                'user_id' => $userId,
                'action' => mb_substr(
                    'Returned borrow request #' . $borrow->request_id . ' for '
                        . ($borrow->equipment?->name ?? 'equipment') . ' (' . $data['condition'] . ' condition)',
                    0,
                    100
                ),
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'affected_entity' => 'borrow_request:' . $borrow->request_id,
                'details' => $remarks !== '' ? mb_substr($remarks, 0, 255) : null,
            ]);

            return ['code' => 200];
        });

        if (($result['code'] ?? 200) !== 200) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Equipment returned successfully!',
        ]);
    }
}
