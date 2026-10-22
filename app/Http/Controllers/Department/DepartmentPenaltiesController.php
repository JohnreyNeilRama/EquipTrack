<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\PenaltyPageController;
use App\Models\Penalty;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lab Personnel Penalties page: penalties on equipment owned by the signed-in
 * department. They are recorded on the Return Confirmation page; here they are
 * edited while Pending, and settled or waived.
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
}
