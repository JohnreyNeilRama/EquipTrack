<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Read state for a user's notification. Notifications themselves are derived
 * from the borrowing records; this only remembers which ones were read.
 */
class UserNotificationRead extends Model
{
    protected $table = 'user_notification_reads';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'notif_key',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
    ];
}
