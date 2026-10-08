<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\StaffNotificationController;
use App\Services\ReviewerNotificationService;

/** Lab Personnel (department) notification bell: Reservation Status Alerts. */
class DepartmentNotificationController extends StaffNotificationController
{
    protected function viewer(): array
    {
        $dept = auth('dept')->user();

        return [
            ReviewerNotificationService::DEPT,
            (int) $dept->dept_acc_id,
            $dept->department_id ? (int) $dept->department_id : null,
        ];
    }
}
