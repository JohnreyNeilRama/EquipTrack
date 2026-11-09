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

    /**
     * The department's head is the person who holds its Department Account:
     * when the account is created, renamed (e.g. a new head takes over) or
     * moved to another department, that department's head name follows, so
     * the Monitoring cards always show the current head.
     */
    protected static function booted(): void
    {
        static::created(fn (DepartmentAccount $account) => $account->syncDepartmentHead());

        static::updated(function (DepartmentAccount $account) {
            if ($account->wasChanged(['full_name', 'department_id'])) {
                $account->syncDepartmentHead();
            }
        });
    }

    private function syncDepartmentHead(): void
    {
        $name = trim((string) $this->full_name);

        if ($this->department_id && $name !== '') {
            Department::whereKey($this->department_id)->update(['department_head' => $name]);
        }
    }

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
