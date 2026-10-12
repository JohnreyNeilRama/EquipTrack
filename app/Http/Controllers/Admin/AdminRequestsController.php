<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Services\RequestTableRows;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        // Not Expensive requests are handled by the department alone.
        if (!$borrow->adminCanReview()) {
            return response()->json([
                'success' => false,
                'message' => 'This request is for Not Expensive equipment and is handled by the department.',
            ], 403);
        }

        // FIX vs legacy: rows already reviewed could be re-approved/re-rejected,
        // double-logging audit entries.
        if ($borrow->admin_status !== 'Pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been reviewed.',
            ], 400);
        }

        // The department workflow may have already rejected this request.
        if ($borrow->overall_status === 'Rejected') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been rejected.',
            ], 400);
        }

        $admin = auth('admin')->user();
        $now = now();
        $reason = $data['status'] === 'Rejected' ? trim($data['reject_reason']) : null;

        $result = DB::transaction(function () use ($request, $data, $borrow, $admin, $now, $reason) {
            // Re-fetch under a lock so concurrent approvals cannot double-decrement stock.
            $locked = BorrowRequest::where('request_id', $borrow->request_id)->lockForUpdate()->first();
            if (!$locked || $locked->admin_status !== 'Pending' || $locked->overall_status === 'Rejected') {
                return ['code' => 400, 'message' => 'This request has already been reviewed.'];
            }

            // Expensive: this approval completes the request only if the
            // department already approved.
            $completes = $data['status'] === 'Approved'
                && $locked->approvalCompletes(BorrowRequest::PARTY_ADMIN);

            if ($completes) {
                // Decrements the equipment's available quantity and creates the
                // Active borrow_transaction (idempotent).
                $activation = $locked->activateLoan();
                if (($activation['code'] ?? 200) !== 200) {
                    return $activation;
                }
            } elseif ($data['status'] === 'Rejected' && $locked->transaction()->exists()) {
                // The department may already have activated the loan. Rejecting
                // it now would leave an active transaction with stock deducted.
                return ['code' => 400, 'message' => 'This request is already approved and the equipment is on loan.'];
            }

            $overall = match (true) {
                $data['status'] === 'Rejected' => 'Rejected',
                $completes => 'Approved',
                // Expensive, waiting for the department: not fully approved yet.
                default => $locked->overall_status ?: 'Pending',
            };

            $locked->update([
                'admin_status' => $data['status'],
                'overall_status' => $overall,
                'admin_id' => $admin->admin_id,
                'admin_reviewed_at' => $now,
                'reject_reason' => $reason,
            ]);

            // Legacy audit inserts used a nonexistent `timestamp` column and failed
            // silently; write to the real audit_trail schema.
            $action = $data['status'] === 'Approved'
                ? 'Approved borrow request #' . $locked->request_id . ' for ' . $borrow->equipment->name
                : 'Rejected borrow request #' . $locked->request_id . ' for ' . $borrow->equipment->name;

            $details = $reason !== null
                ? mb_substr('Reason: ' . $reason, 0, 255)
                : ($data['status'] === 'Approved' && !$completes
                    ? 'Expensive equipment: awaiting department approval.'
                    : null);

            AuditTrail::create([
                'admin_id' => $admin->admin_id,
                'action' => mb_substr($action, 0, 100),
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'affected_entity' => 'borrow_request:' . $locked->request_id,
                // The reason is kept whole here; `action` is capped at 100 characters.
                'details' => $details,
            ]);

            return ['code' => 200, 'overall' => $overall];
        });

        if (($result['code'] ?? 200) !== 200) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], $result['code']);
        }

        $message = match (true) {
            $data['status'] === 'Rejected' => 'Borrow request rejected.',
            $result['overall'] === 'Approved' => 'Borrow request approved successfully!',
            default => 'Your approval was recorded. This is Expensive equipment, so the request also needs the department\'s approval.',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'overallStatus' => $result['overall'],
            'adminStatus' => $data['status'],
        ]);
    }

    /** Expensive requests (Not Expensive ones are handled by the department alone). */
    private function fetchAllBorrowRequests(): array
    {
        return RequestTableRows::fetch(
            BorrowRequest::query()->visibleToAdmin(),
            fn (BorrowRequest $row) => $row->adminCanReview(),
        );
    }
}
