<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read state for an Admin or Lab Personnel (department) notification.
 * account_type is 'admin' or 'dept'. Notifications are derived from
 * borrow_request; this only remembers which ones were read.
 */
class StaffNotificationRead extends Model
{
    protected $table = 'staff_notification_reads';
    public $timestamps = false;

    protected $fillable = [
        'account_type',
        'account_id',
        'notif_key',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
