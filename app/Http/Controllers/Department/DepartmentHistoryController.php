<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\BorrowTransaction;
use App\Models\EquipmentCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Borrowing history for the signed-in department.
 *
 * A history record is a borrow_transaction that has been settled (status
 * Returned) for equipment this department owns — the same ownership scope the
 * department Requests and Monitoring pages use, so a unit borrowed by a
 * student or faculty member of any department shows up here once returned.
 *
 * Returns are recorded by UserReturnsController::store(), which sets the
 * transaction's status / return_date / condition_on_return; reading the same
 * rows here keeps this page in sync with the borrow and return workflow.
 */
class DepartmentHistoryController extends Controller
{
    public function index(): View
    {
        return view('department.history', [
            'dbCategories' => EquipmentCategory::orderBy('category_name')->pluck('category_name')->all(),
            'dbHistory' => $this->buildHistoryRows(),
        ]);
    }

    /**
     * JSON feed polled by the history page so new returns appear without a reload.
     */
    public function data(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'history' => $this->buildHistoryRows(),
        ]);
    }

    private function buildHistoryRows(): array
    {
        $departmentId = auth('dept')->user()->department_id;

        if (!$departmentId) {
            return [];
        }

        $fmt = fn ($value) => $value ? Carbon::parse($value)->format('M d, Y') : '—';

        $transactions = BorrowTransaction::with([
            'request.user.student',
            'request.user.facultyMember',
            'request.equipment.category',
        ])
            ->where('status', 'Returned')
            ->whereHas('request.equipment', fn ($query) => $query->where('department_id', $departmentId))
            ->orderByDesc('return_date')
            ->orderByDesc('transaction_id')
            ->get();

        $rows = [];

        foreach ($transactions as $transaction) {
            $request = $transaction->request;
            $user = $request?->user;
            $equipment = $request?->equipment;

            if (!$request || !$user || !$equipment) {
                continue;
            }

            $isStudent = $user->role === 'Student';

            // "Good — remarks" / "Damaged — remarks" / "Lost — remarks"
            $condition = trim((string) $transaction->condition_on_return);
            $parts = explode('—', $condition, 2);
            $conditionType = trim($parts[0]);
            $remarks = isset($parts[1]) ? trim($parts[1]) : '';

            $rows[] = [
                'txnid' => 'TXN-' . str_pad((string) $transaction->transaction_id, 4, '0', STR_PAD_LEFT),
                'idnumber' => ($isStudent
                    ? $user->student?->id_number
                    : $user->facultyMember?->faculty_id_number) ?: '—',
                'name' => $user->fullName() ?: $user->email,
                'userType' => $isStudent ? 'Student' : 'Faculty',
                'attainment' => ($isStudent
                    ? $user->student?->year_level
                    : $user->facultyMember?->highest_educational_attainment) ?: '—',
                'equipment' => $equipment->name,
                'category' => $equipment->category?->category_name ?: 'General',
                'borrowDate' => $fmt($transaction->borrow_date),
                'returnDate' => $fmt($transaction->return_date),
                'status' => $this->resolveStatus($transaction, $conditionType),
                'remarks' => $remarks !== '' ? $remarks : '—',
            ];
        }

        return $rows;
    }

    /**
     * Damaged / Lost come from the condition reported on return; otherwise a
     * return after the due date is "Returned Late".
     */
    private function resolveStatus(BorrowTransaction $transaction, string $conditionType): string
    {
        if (strcasecmp($conditionType, 'Damaged') === 0) {
            return 'Damaged';
        }

        if (strcasecmp($conditionType, 'Lost') === 0) {
            return 'Lost';
        }

        if ($transaction->return_date && $transaction->due_date
            && $transaction->return_date->toDateString() > $transaction->due_date->toDateString()) {
            return 'Returned Late';
        }

        return 'Returned';
    }
}
