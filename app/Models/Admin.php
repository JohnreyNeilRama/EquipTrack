<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    protected $table = 'admin';
    protected $primaryKey = 'admin_id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'username',
        'email',
        'employee_id',
        'profile_image',
        'password',
        'status',
        'last_online',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function initials(): string
    {
        $parts = explode(' ', trim($this->name));
        $initials = '';
        foreach ($parts as $part) {
            if ($part !== '') {
                $initials .= strtoupper($part[0]);
            }
        }

        return substr($initials, 0, 2) ?: 'AD';
    }

    /** EquipTrack's reset email, sent through this account type's broker ('admins'). */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token, 'admins'));
    }
}
