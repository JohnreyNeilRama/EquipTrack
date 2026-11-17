<?php

namespace App\Notifications;

use App\Models\Admin;
use App\Models\DepartmentAccount;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The 6-digit code an Admin or Lab Personnel account enters after its
 * password (App\Support\SignInCode). Sent right away (not queued), so nobody
 * is kept waiting and no queue worker has to be running.
 */
class SignInCodeNotification extends Notification
{
    public function __construct(
        public readonly string $code,
        public readonly int $minutes,
        public readonly string $ipAddress,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        /** @var Admin|DepartmentAccount $notifiable */
        [$name, $accountType] = $notifiable instanceof Admin
            ? [trim((string) $notifiable->name) ?: 'Administrator', 'administrator account']
            : [trim((string) $notifiable->full_name) ?: 'Lab Personnel', 'Lab Personnel account'];

        return (new MailMessage)
            ->subject('Your EquipTrack sign-in code: ' . $this->code)
            ->view(['emails.account-message', 'emails.account-message-text'], [
                'title' => 'Your EquipTrack sign-in code',
                'preview' => 'Enter this code to finish signing in to EquipTrack.',
                'heading' => 'Your sign-in code',
                'name' => $name,
                'paragraphs' => [
                    'Someone, hopefully you, entered the correct password for your EquipTrack ' . $accountType . '. Enter this code to finish signing in:',
                ],
                'code' => $this->code,
                'note' => 'The code works once and expires in ' . $this->minutes . ' minutes. Sign-in attempt from IP address ' . ($this->ipAddress ?: 'unknown') . '.',
                'footnote' => "If this wasn't you, don't share the code with anyone, and change your password right away: someone knows it.",
            ]);
    }
}
