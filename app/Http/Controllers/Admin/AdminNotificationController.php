<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\StaffNotificationController;
use App\Services\ReviewerNotificationService;

/** Admin notification bell: Reservation Status Alerts (approve / reject) and new Expensive requests to review. */
class AdminNotificationController extends StaffNotificationController
{
    protected function viewer(): array
    {
        return [ReviewerNotificationService::ADMIN, (int) auth('admin')->user()->admin_id, null];
    }
}
