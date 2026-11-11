<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Student or Faculty account. Implements MustVerifyEmail: the /user pages
 * need a confirmed email (the "verified" middleware); see
 * Auth\EmailVerificationController and Auth\AccountSetupController.
 */
class UserAccount extends Authenticatable implements MustVerifyEmail
{
    use Notifiable;

    public const STATUS_ACTIVE = 'Active';

    public const STATUS_DEACTIVATED = 'Deactivated';

    /** Self-registered Faculty until a Lab Personnel member of their department approves them. */
    public const STATUS_PENDING_APPROVAL = 'Pending Approval';

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
        'email_verified_at',
        'invited_at',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'invited_at' => 'datetime',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * A new email address must be confirmed again, whoever changed it (the
     * owner on Profile, or staff on a Users page): otherwise someone could
     * verify one address and then switch to an address they don't own.
     * A verified account is emailed a new verification link right away; an
     * account still waiting for its invite is sent a new invite by staff
     * (Resend Invite), since its owner has no password yet.
     */
    protected static function booted(): void
    {
        static::updating(function (UserAccount $account) {
            if ($account->isDirty('email')
                && strcasecmp((string) $account->getOriginal('email'), (string) $account->email) !== 0) {
                $account->email_verified_at = null;
            }
        });

        static::updated(function (UserAccount $account) {
            if ($account->wasChanged('email') && !$account->hasVerifiedEmail() && $account->invited_at === null) {
                try {
                    $account->sendEmailVerificationNotification();
                } catch (\Throwable $e) {
                    report($e); // The "check your email" page has a Resend button.
                }
            }
        });
    }

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

    public function isPendingApproval(): bool
    {
        return $this->status === self::STATUS_PENDING_APPROVAL;
    }

    /** Added by staff and emailed an invite that hasn't been used yet. */
    public function hasPendingInvite(): bool
    {
        return $this->invited_at !== null && !$this->hasVerifiedEmail();
    }

    /**
     * What the Users pages show for this account:
     * active | inactive | pending (approval) | invited | unverified.
     */
    public function accountState(): string
    {
        return match (true) {
            strcasecmp((string) $this->status, self::STATUS_DEACTIVATED) === 0 => 'inactive',
            $this->isPendingApproval() => 'pending',
            $this->hasPendingInvite() => 'invited',
            !$this->hasVerifiedEmail() => 'unverified',
            default => 'active',
        };
    }

    /** The label for accountState(). */
    public function accountStateLabel(): string
    {
        return match ($this->accountState()) {
            'inactive' => 'Inactive',
            'pending' => 'Pending Approval',
            'invited' => 'Invite Sent',
            'unverified' => 'Email Not Verified',
            default => 'Active',
        };
    }

    /** EquipTrack's "verify your email" message (self-registration). */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    /** EquipTrack's reset email, sent through this account type's broker ('users'). */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token, 'users'));
    }
}
