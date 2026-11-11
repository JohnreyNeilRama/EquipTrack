<?php

namespace App\Notifications;

use App\Models\Admin;
use App\Models\DepartmentAccount;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invite for an account added by someone else: a Student or Faculty account
 * added by staff on a Users page, or a Department (Lab Personnel) or Admin
 * account added by an Admin. The link opens Set Your Password
 * (Auth\AccountSetupController); setting the password also confirms the email
 * address. The token comes from the account type's invite broker
 * (config/auth.php): stored hashed, valid 7 days, single use.
 *
 * Sent right away (not queued), so no queue worker has to be running.
 */
class UserInviteNotification extends Notification
{
    public function __construct(
        #[\SensitiveParameter] public string $token,
        public string $invitedBy,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = (int) round(config('auth.passwords.user_invites.expire', 10080) / 1440);

        // Student / Faculty, Laboratory Personnel (department account) or Administrator.
        [$type, $name, $department, $purpose] = match (true) {
            $notifiable instanceof Admin => ['Administrator', $notifiable->name, null, 'start managing EquipTrack'],
            $notifiable instanceof DepartmentAccount => ['Laboratory Personnel', $notifiable->full_name, $notifiable->department?->department_name, "start managing your department's equipment"],
            default => [$notifiable->role === 'Faculty' ? 'Faculty' : 'Student', $notifiable->fullName(), $notifiable->department?->department_name, 'start borrowing equipment'],
        };

        return (new MailMessage)
            ->subject('Your EquipTrack account is ready: set your password')
            ->view(['emails.account-message', 'emails.account-message-text'], [
                'title' => 'Set your EquipTrack password',
                'preview' => 'An EquipTrack account was created for you. Choose your password to start.',
                'heading' => 'Welcome to EquipTrack',
                'name' => trim((string) $name) ?: 'there',
                'paragraphs' => [
                    $this->invitedBy . ' created an EquipTrack ' . $type . ' account for you'
                        . ($department ? ' in ' . $department : '') . ', with this email address (' . $notifiable->email . ').',
                    'Choose your password to ' . $purpose . '. Your email address is confirmed at the same time.',
                ],
                'buttonLabel' => 'Set My Password',
                'buttonUrl' => self::setupUrl($this->token, $notifiable->email),
                'note' => 'This link expires in ' . $days . ' days and can be used only once. If it expires, ask the laboratory or the Admin to send a new invite.',
                'footnote' => "If you weren't expecting this, you can ignore this email; no one can sign in until a password is chosen.",
            ]);
    }

    public static function setupUrl(string $token, string $email): string
    {
        return route('account.setup', ['token' => $token, 'email' => $email]);
    }
}
