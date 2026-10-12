<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class UserReturnsController extends Controller
{
    /**
     * Lists the signed-in user's currently borrowed equipment: approved
     * borrow requests that have not been settled with a Returned
     * borrow_transaction yet. Returns awaiting the department's confirmation
     * (or rejected by it) stay listed with their return status.
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
     * Submits a return from the Return Item page. The return is recorded as
     * Pending Confirmation with the condition the user reported; the loan
     * stays Active (still out, stock untouched) until the department that owns
     * the equipment physically verifies it on its Return Confirmation page.
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

            $transaction = $borrow->transaction
                ? BorrowTransaction::where('transaction_id', $borrow->transaction->transaction_id)->lockForUpdate()->first()
                : null;

            if ($transaction && $transaction->status === 'Returned') {
                return ['code' => 400, 'message' => 'This item has already been returned.'];
            }

            if ($transaction && $transaction->isReturnPending()) {
                return ['code' => 400, 'message' => 'This return is already awaiting the department\'s confirmation.'];
            }

            $remarks = trim($data['remarks'] ?? '');
            $reported = mb_substr(
                $data['condition'] . ($remarks !== '' ? ' — ' . $remarks : ''),
                0,
                150
            );

            $pending = [
                'return_status' => BorrowTransaction::RETURN_PENDING,
                'return_submitted_at' => now(),
                'reported_condition' => $reported,
                // A new submission starts a new review (a rejected one is kept in the audit trail).
                'inspected_condition' => null,
                'inspection_remarks' => null,
                'return_reviewed_by' => null,
                'return_reviewed_at' => null,
                'return_reject_reason' => null,
            ];

            if ($transaction) {
                $transaction->update($pending);
            } else {
                // Approved before loans were tracked: stock was never deducted,
                // so confirming this return must not add stock back.
                BorrowTransaction::create($pending + [
                    'request_id' => $borrow->request_id,
                    'borrow_date' => $borrow->borrow_date ?: $borrow->date_needed,
                    'due_date' => $borrow->due_date ?: $borrow->return_date,
                    'status' => 'Active',
                    'return_restores_stock' => false,
                ]);
            }

            AuditTrail::create([
                'user_id' => $userId,
                'action' => mb_substr(
                    'Submitted return for borrow request #' . $borrow->request_id . ' for '
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
            'message' => 'Your return has been submitted. It is pending confirmation until the department checks the equipment.',
            'returnStatus' => BorrowTransaction::RETURN_PENDING,
        ]);
    }
}
