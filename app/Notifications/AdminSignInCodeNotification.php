<?php

namespace App\Notifications;

use App\Models\Admin;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The 6-digit code an Admin enters after their password
 * (App\Support\AdminTwoFactor). Sent right away (not queued), so the admin
 * isn't kept waiting and no queue worker has to be running.
 */
class AdminSignInCodeNotification extends Notification
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
        /** @var Admin $notifiable */
        return (new MailMessage)
            ->subject('Your EquipTrack sign-in code: ' . $this->code)
            ->view(['emails.account-message', 'emails.account-message-text'], [
                'title' => 'Your EquipTrack sign-in code',
                'preview' => 'Enter this code to finish signing in to EquipTrack.',
                'heading' => 'Your sign-in code',
                'name' => trim((string) $notifiable->name) ?: 'Administrator',
                'paragraphs' => [
                    'Someone, hopefully you, entered the correct password for your EquipTrack administrator account. Enter this code to finish signing in:',
                ],
                'code' => $this->code,
                'note' => 'The code works once and expires in ' . $this->minutes . ' minutes. Sign-in attempt from IP address ' . ($this->ipAddress ?: 'unknown') . '.',
                'footnote' => "If this wasn't you, don't share the code with anyone, and change your password right away: someone knows it.",
            ]);
    }
}
