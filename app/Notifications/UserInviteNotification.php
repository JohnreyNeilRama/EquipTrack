<?php

namespace App\Notifications;

use App\Models\UserAccount;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invite for a Student or Faculty account added by staff on a Users page:
 * the link opens Set Your Password (Auth\AccountSetupController). Setting the
 * password also confirms the email address. The token comes from the
 * "user_invites" broker (config/auth.php): stored hashed, valid 7 days, single use.
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
        /** @var UserAccount $notifiable */
        $days = (int) round(config('auth.passwords.user_invites.expire', 10080) / 1440);
        $type = $notifiable->role === 'Faculty' ? 'Faculty' : 'Student';
        $department = $notifiable->department?->department_name;

        return (new MailMessage)
            ->subject('Your EquipTrack account is ready: set your password')
            ->view(['emails.account-message', 'emails.account-message-text'], [
                'title' => 'Set your EquipTrack password',
                'preview' => 'An EquipTrack account was created for you. Choose your password to start.',
                'heading' => 'Welcome to EquipTrack',
                'name' => trim($notifiable->fullName()) ?: 'there',
                'paragraphs' => [
                    $this->invitedBy . ' created an EquipTrack ' . $type . ' account for you'
                        . ($department ? ' in ' . $department : '') . ', with this email address (' . $notifiable->email . ').',
                    'Choose your password to start borrowing equipment. Your email address is confirmed at the same time.',
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
