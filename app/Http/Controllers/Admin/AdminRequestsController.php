<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminRequestsController extends Controller
{
    public function index(): View
    {
        $dbRequests = $this->fetchAllBorrowRequests();

        return view('admin.requests', compact('dbRequests'));
    }

    /** JSON feed for live polling (legacy requests.php?action=get_requests). */
    public function data(): JsonResponse
    {
        return response()->json(['success' => true, 'requests' => $this->fetchAllBorrowRequests()]);
    }

    /**
     * Approve / Reject. Mirrors the legacy update_status endpoint:
     * status columns + audit trail. On approval the borrow lifecycle is now
     * completed for real: equipment stock is decremented and an Active
     * borrow_transaction is created, which the user Return Item flow depends on.
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:Approved,Rejected'],
            'reject_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['status'] === 'Rejected' && trim($data['reject_reason'] ?? '') === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please state a reason for rejecting the request.',
            ], 400);
        }

        $borrow = BorrowRequest::with('equipment')->find($data['request_id']);
        if (!$borrow || !$borrow->equipment) {
            return response()->json(['success' => false, 'message' => 'Borrow request not found.'], 404);
        }

        // FIX vs legacy: rows already reviewed could be re-approved/re-rejected,
        // double-logging audit entries.
        if ($borrow->admin_status !== 'Pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been reviewed.',
            ], 400);
        }

        $admin = auth('admin')->user();
        $now = now();
        $reason = $data['status'] === 'Rejected' ? trim($data['reject_reason']) : null;

        $result = DB::transaction(function () use ($request, $data, $borrow, $admin, $now, $reason) {
            // Re-fetch under a lock so concurrent approvals cannot double-decrement stock.
            $locked = BorrowRequest::where('request_id', $borrow->request_id)->lockForUpdate()->first();
            if (!$locked || $locked->admin_status !== 'Pending') {
                return ['code' => 400, 'message' => 'This request has already been reviewed.'];
            }

            if ($data['status'] === 'Approved') {
                $equipment = Equipment::where('equipment_id', $locked->equipment_id)
                    ->lockForUpdate()
                    ->first();

                if (!$equipment) {
                    return ['code' => 404, 'message' => 'The equipment for this request was not found.'];
                }

                $qty = max(1, (int) $locked->quantity);
                if ((int) $equipment->available_qty < $qty) {
                    return ['code' => 400, 'message' => 'Cannot approve: this equipment has no available stock left.'];
                }

                $equipment->available_qty = (int) $equipment->available_qty - $qty;
                if ($equipment->available_qty <= 0) {
                    $equipment->available_qty = 0;
                    $equipment->status = 'Unavailable';
                }
                $equipment->save();

                // The borrowing record returned items are settled against.
                BorrowTransaction::create([
                    'request_id' => $locked->request_id,
                    'borrow_date' => $locked->borrow_date ?: $locked->date_needed,
                    'due_date' => $locked->due_date ?: $locked->return_date,
                    'status' => 'Active',
                ]);
            }

            $locked->update([
                'admin_status' => $data['status'],
                'overall_status' => $data['status'],
                'admin_id' => $admin->admin_id,
                'admin_reviewed_at' => $now,
                'reject_reason' => $reason,
            ]);

            // Legacy audit inserts used a nonexistent `timestamp` column and failed
            // silently; write to the real audit_trail schema.
            $action = $data['status'] === 'Approved'
                ? 'Approved borrow request #' . $locked->request_id . ' for ' . $borrow->equipment->name
                : 'Rejected borrow request #' . $locked->request_id . ' for ' . $borrow->equipment->name . '. Reason: ' . $reason;

            AuditTrail::create([
                'admin_id' => $admin->admin_id,
                'action' => mb_substr($action, 0, 100),
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'affected_entity' => 'borrow_request:' . $locked->request_id,
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
            'message' => $data['status'] === 'Approved'
                ? 'Borrow request approved successfully!'
                : 'Borrow request rejected.',
        ]);
    }

    private function fetchAllBorrowRequests(): array
    {
        $requests = [];

        $rows = BorrowRequest::with([
            'user.student',
            'user.facultyMember',
            'equipment.category',
        ])->orderByDesc('request_id')->get();

        foreach ($rows as $row) {
            $u = $row->user;
            $fullName = $u?->fullName() ?: ($u?->email ?? '');
            $bDateRaw = $row->borrow_date ?: $row->date_needed;
            $dDateRaw = $row->due_date ?: $row->return_date;

            $avatar = trim((string) ($u?->profile_image ?? ''));
            if ($avatar === '') {
                $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fullName ?: 'User') . '&background=385585&color=fff';
            }

            $rawImg = trim((string) ($row->equipment?->image ?? ''));
            if ($rawImg === '') {
                $eqImg = asset('images/EquipTrack_logo.png');
            } elseif (preg_match('/^(https?:\/\/|data:|\/storage\/)/i', $rawImg)) {
                $eqImg = $rawImg;
            } else {
                $eqImg = asset(ltrim($rawImg, '/'));
            }

            $fmt = fn ($v) => $v ? Carbon::parse($v)->format('M d, Y') : 'N/A';

            $requests[] = [
                'id' => (int) $row->request_id,
                'user_id' => (int) $row->user_id,
                'user' => $fullName !== '' ? $fullName : 'Unknown User',
                'role' => ucfirst($u?->role ?? 'Student'),
                'email' => $u?->email ?? '',
                'avatar' => $avatar,
                'equipment_id' => (int) $row->equipment_id,
                'equipment' => $row->equipment?->name ?? 'Unknown Equipment',
                'category' => $row->equipment?->category?->category_name ?: 'General',
                'img' => $eqImg,
                'quantity' => (int) ($row->quantity ?? 1),
                'date' => $fmt($row->date_requested),
                'fullDate' => $row->date_requested ? Carbon::parse($row->date_requested)->format('M d, Y h:i A') : 'N/A',
                'borrowDate' => $fmt($bDateRaw),
                'dueDate' => $fmt($dDateRaw),
                'purpose' => $row->purpose ?: 'N/A',
                'notes' => $row->notes ?: 'None',
                'status' => ucfirst($row->overall_status ?? 'Pending'),
                'adminStatus' => ucfirst($row->admin_status ?? 'Pending'),
                'rejectReason' => $row->reject_reason ?? '',
            ];
        }

        return $requests;
    }
}
