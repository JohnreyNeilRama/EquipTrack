<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\PenaltyPageController;
use App\Models\BorrowTransaction;
use App\Models\Penalty;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lab Personnel Penalties page: penalties on equipment owned by the signed-in
 * department. Most are recorded at Return Confirmation; Add Penalty here covers
 * damage found after a return was confirmed. Pending penalties are edited,
 * settled or waived here, and the borrower's proof is reviewed.
 */
class DepartmentPenaltiesController extends PenaltyPageController
{
    protected function scope(): Builder
    {
        $departmentId = (int) auth('dept')->user()->department_id;

        // An account without a department has nothing in scope.
        return $departmentId
            ? Penalty::query()->forDepartment($departmentId)
            : Penalty::query()->whereRaw('1 = 0');
    }

    protected function actor(): array
    {
        return AuditLogger::actorFor('dept', auth('dept')->user());
    }

    protected function view(): string
    {
        return 'department.penalties';
    }

    protected function proofFileRoute(): string
    {
        return 'department.penalties.proof';
    }

    /** Loans of equipment owned by this department; none without a department. */
    protected function loanScope(): Builder
    {
        $departmentId = (int) auth('dept')->user()->department_id;

        return $departmentId
            ? BorrowTransaction::query()->whereHas('request.equipment', fn ($q) => $q->where('department_id', $departmentId))
            : BorrowTransaction::query()->whereRaw('1 = 0');
    }
}
