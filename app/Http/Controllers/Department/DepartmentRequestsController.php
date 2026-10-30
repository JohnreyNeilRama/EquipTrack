<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Services\RequestTableRows;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Department review of borrow requests for the equipment the department owns.
 * Approval uses the same loan activation as the admin workflow
 * (BorrowRequest::activateLoan), so the equipment's Available quantity is
 * deducted exactly once even when both approvers approve the same request.
 */
class DepartmentRequestsController extends Controller
{
    public function index(): View
    {
        return view('department.requests', [
            'dbRequests' => $this->fetchDepartmentRequests(),
        ]);
    }

    /** JSON feed for live polling, same as the admin Requests page. */
    public function data(): JsonResponse
    {
        return response()->json(['success' => true, 'requests' => $this->fetchDepartmentRequests()]);
    }

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

        $dept = auth('dept')->user();

        $borrow = BorrowRequest::with(['equipment'])
            ->where('request_id', $data['request_id'])
            ->whereHas('equipment', fn ($query) => $query->where('department_id', $dept->department_id))
            ->first();

        if (!$borrow || !$borrow->equipment) {
            return response()->json([
                'success' => false,
                'message' => 'Borrow request not found for your department.',
            ], 404);
        }

        if ($borrow->dept_status !== 'Pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been reviewed.',
            ], 400);
        }

        if ($borrow->overall_status === BorrowRequest::STATUS_CANCELLED) {
            return response()->json([
                'success' => false,
                'message' => 'The borrower cancelled this request.',
            ], 400);
        }

        if ($borrow->overall_status === 'Rejected') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been rejected.',
            ], 400);
        }

        $now = now();
        $reason = $data['status'] === 'Rejected' ? trim($data['reject_reason']) : null;

        $result = DB::transaction(function () use ($request, $data, $borrow, $dept, $now, $reason) {
            $locked = BorrowRequest::where('request_id', $borrow->request_id)->lockForUpdate()->first();
            if ($locked?->overall_status === BorrowRequest::STATUS_CANCELLED) {
                return ['code' => 400, 'message' => 'The borrower cancelled this request.'];
            }
            if (!$locked || $locked->dept_status !== 'Pending' || $locked->overall_status === 'Rejected') {
                return ['code' => 400, 'message' => 'This request has already been reviewed.'];
            }

            // Not Expensive: the department's approval completes the request.
            // Expensive: only if the admin already approved.
            $completes = $data['status'] === 'Approved'
                && $locked->approvalCompletes(BorrowRequest::PARTY_DEPT);

            if ($completes) {
                // Deducts the available quantity and creates the Active
                // transaction; a no-op when the admin approved first.
                $activation = $locked->activateLoan();
                if (($activation['code'] ?? 200) !== 200) {
                    return $activation;
                }
            } elseif ($data['status'] === 'Rejected' && $locked->transaction()->exists()) {
                // The loan is already active, so it can no longer be rejected.
                return ['code' => 400, 'message' => 'This request is already approved and the equipment is on loan.'];
            }

            $overall = match (true) {
                $data['status'] === 'Rejected' => 'Rejected',
                $completes => 'Approved',
                // Expensive, waiting for the admin: not fully approved yet.
                default => $locked->overall_status ?: 'Pending',
            };

            $locked->update([
                'dept_status' => $data['status'],
                'dept_acc_id' => $dept->dept_acc_id,
                'dept_reviewed_at' => $now,
                'overall_status' => $overall,
                'reject_reason' => $reason,
            ]);

            $action = $data['status'] === 'Approved'
                ? 'Department approved borrow request #' . $locked->request_id . ' for ' . $borrow->equipment->name
                : 'Department rejected borrow request #' . $locked->request_id . ' for ' . $borrow->equipment->name;

            $details = $reason !== null
                ? mb_substr('Reason: ' . $reason, 0, 255)
                : ($data['status'] === 'Approved' && !$completes
                    ? 'Expensive equipment: awaiting admin approval.'
                    : null);

            AuditTrail::create([
                'dept_acc_id' => $dept->dept_acc_id,
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
            default => 'Your approval was recorded. This is Expensive equipment, so the request also needs the admin\'s approval.',
        };

        return response()->json([
            'success' => true,
            'message' => $message,
            'overallStatus' => $result['overall'],
            'deptStatus' => $data['status'],
        ]);
    }

    /**
     * Borrow requests for equipment owned by the signed-in department, in the
     * shape the shared Requests table renders. The department reviews every
     * request in its scope: Not Expensive ones alone, Expensive ones together
     * with the admin.
     */
    private function fetchDepartmentRequests(): array
    {
        $departmentId = auth('dept')->user()->department_id;

        if (!$departmentId) {
            return [];
        }

        return RequestTableRows::fetch(
            BorrowRequest::query()->whereHas('equipment', fn ($query) => $query->where('department_id', $departmentId)),
            fn () => true,
        );
    }
}
