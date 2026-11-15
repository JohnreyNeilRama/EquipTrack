<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\PenaltyPageController;
use App\Support\DepartmentScope;
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
        return DepartmentScope::penalties();
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
        return DepartmentScope::loans();
    }
}
