<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * The Lab Personnel login of a department. Each department has exactly one
 * Department Account (unique department_id), and its role is always
 * Department Head: it manages the department's equipment, users, requests,
 * releases, returns and penalties.
 */
class DepartmentAccount extends Authenticatable
{
    use Notifiable;

    /** The one role a Department Account has. */
    public const ROLE = 'Department Head';

    protected $table = 'department_account';
    protected $primaryKey = 'dept_acc_id';
    public $timestamps = false;

    protected $fillable = [
        'full_name',
        'email',
        'password',
        'employee_id',
        'role',
        'department_id',
        'profile_image',
        'last_online',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function initials(): string
    {
        $parts = explode(' ', trim($this->full_name));
        $initials = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $initials .= strtoupper($part[0]);
            }
        }

        return substr($initials, 0, 2) ?: 'DP';
    }

    /** EquipTrack's reset email, sent through this account type's broker ('departments'). */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token, 'departments'));
    }
}
