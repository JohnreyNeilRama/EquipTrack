<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Models\EquipmentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $dbCategories = EquipmentCategory::orderBy('category_name')->pluck('category_name')->all();

        return view('department.requests', [
            'dbCategories' => $dbCategories,
            'dbRequests' => $this->fetchDepartmentRequests(),
        ]);
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
            if (!$locked || $locked->dept_status !== 'Pending' || $locked->overall_status === 'Rejected') {
                return ['code' => 400, 'message' => 'This request has already been reviewed.'];
            }

            if ($data['status'] === 'Approved') {
                // Deducts the available quantity and creates the Active
                // transaction; a no-op when the admin approved first.
                $activation = $locked->activateLoan();
                if (($activation['code'] ?? 200) !== 200) {
                    return $activation;
                }
            } elseif ($locked->transaction()->exists()) {
                // The loan is already active, so it can no longer be rejected.
                return ['code' => 400, 'message' => 'This request is already approved and the equipment is on loan.'];
            }

            $locked->update([
                'dept_status' => $data['status'],
                'dept_acc_id' => $dept->dept_acc_id,
                'dept_reviewed_at' => $now,
                'overall_status' => $data['status'],
                'reject_reason' => $reason,
            ]);

            $action = $data['status'] === 'Approved'
                ? 'Department approved borrow request #' . $locked->request_id . ' for ' . $borrow->equipment->name
                : 'Department rejected borrow request #' . $locked->request_id . ' for ' . $borrow->equipment->name . '. Reason: ' . $reason;

            AuditTrail::create([
                'dept_acc_id' => $dept->dept_acc_id,
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

    /**
     * Borrow requests for equipment owned by the signed-in department, in the
     * shape the requests table renders.
     */
    private function fetchDepartmentRequests(): array
    {
        $departmentId = auth('dept')->user()->department_id;

        if (!$departmentId) {
            return [];
        }

        $rows = BorrowRequest::with([
            'user.student',
            'user.facultyMember',
            'equipment.category',
        ])
            ->whereHas('equipment', fn ($query) => $query->where('department_id', $departmentId))
            ->orderByDesc('request_id')
            ->get();

        $requests = [];

        foreach ($rows as $row) {
            $user = $row->user;
            $fullName = $user?->fullName() ?: ($user?->email ?? '');

            $requests[] = [
                'id' => (int) $row->request_id,
                'user' => $fullName !== '' ? $fullName : 'Unknown User',
                'role' => ucfirst($user?->role ?? 'Student'),
                'equipment' => $row->equipment?->name ?? 'Unknown Equipment',
                'category' => $row->equipment?->category?->category_name ?: 'General',
                'date' => $row->date_requested ? Carbon::parse($row->date_requested)->format('M d, Y') : 'N/A',
                'status' => ucfirst($row->overall_status ?: 'Pending'),
            ];
        }

        return $requests;
    }

}
