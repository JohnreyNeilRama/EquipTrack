<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class UserAccount extends Authenticatable
{
    use Notifiable;

    protected $table = 'user_account';
    protected $primaryKey = 'user_id';
    public $timestamps = false;

    protected $fillable = [
        'role',
        'email',
        'password',
        'department_id',
        'profile_image',
        'last_online',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function student()
    {
        return $this->hasOne(Student::class, 'user_id', 'user_id');
    }

    public function facultyMember()
    {
        return $this->hasOne(FacultyMember::class, 'user_id', 'user_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function fullName(): string
    {
        if ($this->role === 'Student') {
            return trim(($this->student?->first_name ?? '') . ' ' . ($this->student?->last_name ?? ''));
        }

        return trim(($this->facultyMember?->first_name ?? '') . ' ' . ($this->facultyMember?->last_name ?? ''));
    }

    public function initials(): string
    {
        $parts = explode(' ', trim($this->fullName()));
        $initials = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $initials .= strtoupper($part[0]);
            }
        }

        return substr($initials, 0, 2) ?: 'US';
    }
}
