<?php

namespace App\Notifications;

use App\Models\Admin;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The EquipTrack password reset email, for every account type.
 *
 * Sent by the password broker of the account's type (config/auth.php:
 * users, admins, departments), which also decides how long the link lasts.
 * The token in the link is single-use; only its hash is stored.
 *
 * Sent right away (not queued), so no queue worker has to be running.
 */
class ResetPasswordNotification extends Notification
{
    public function __construct(
        public string $token,
        public string $broker,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $email = $notifiable->getEmailForPasswordReset();
        $minutes = (int) config("auth.passwords.{$this->broker}.expire", 30);

        return (new MailMessage)
            ->subject('Reset your EquipTrack password')
            ->view(['emails.password-reset', 'emails.password-reset-text'], [
                'name' => $this->displayName($notifiable),
                'accountType' => $this->accountType($notifiable),
                'email' => $email,
                'resetUrl' => $this->resetUrl($email),
                'minutes' => $minutes,
                'expiresAt' => now(config('app.display_timezone'))->addMinutes($minutes)->format('M d, Y h:i A'),
            ]);
    }

    /** The Reset Password page: /reset-password/{token}?email=… */
    public function resetUrl(string $email): string
    {
        return route('password.reset', ['token' => $this->token, 'email' => $email]);
    }

    private function displayName(object $account): string
    {
        $name = match (true) {
            $account instanceof UserAccount => $account->fullName(),
            $account instanceof Admin => $account->name,
            $account instanceof DepartmentAccount => $account->full_name,
            default => '',
        };

        return trim((string) $name) ?: 'there';
    }

    private function accountType(object $account): string
    {
        return match (true) {
            $account instanceof UserAccount => $account->role === 'Faculty' ? 'Faculty' : 'Student',
            $account instanceof Admin => 'Administrator',
            $account instanceof DepartmentAccount => 'Laboratory Personnel',
            default => 'EquipTrack',
        };
    }
}
