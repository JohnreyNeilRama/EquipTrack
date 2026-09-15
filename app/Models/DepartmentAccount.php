<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class DepartmentAccount extends Authenticatable
{
    use Notifiable;

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
}
