<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\StaffNotificationController;
use App\Services\ReviewerNotificationService;
use App\Support\DepartmentScope;

/** Lab Personnel (department) notification bell: Reservation Status Alerts, new requests and returns to confirm. */
class DepartmentNotificationController extends StaffNotificationController
{
    protected function viewer(): array
    {
        $dept = auth('dept')->user();

        return [
            ReviewerNotificationService::DEPT,
            (int) $dept->dept_acc_id,
            DepartmentScope::id(),
        ];
    }
}
