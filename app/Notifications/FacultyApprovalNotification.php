<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The department's decision on a self-registered Faculty account: approved
 * (they can borrow now) or declined with a reason (the account is removed, so
 * the email can register again).
 *
 * Sent right away (not queued), so no queue worker has to be running.
 */
class FacultyApprovalNotification extends Notification
{
    public function __construct(
        public bool $approved,
        public string $name,
        public string $department,
        public ?string $reason = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim($this->name) ?: 'there';

        if ($this->approved) {
            return (new MailMessage)
                ->subject('Your EquipTrack Faculty account is approved')
                ->view(['emails.account-message', 'emails.account-message-text'], [
                    'title' => 'Your EquipTrack Faculty account is approved',
                    'preview' => 'You can now sign in and borrow equipment.',
                    'heading' => 'Your account is approved',
                    'name' => $name,
                    'paragraphs' => [
                        $this->department . ' approved your EquipTrack Faculty account. You can now sign in and request equipment.',
                    ],
                    'buttonLabel' => 'Sign In',
                    'buttonUrl' => route('login'),
                ]);
        }

        return (new MailMessage)
            ->subject('Your EquipTrack Faculty registration was declined')
            ->view(['emails.account-message', 'emails.account-message-text'], [
                'title' => 'Your EquipTrack Faculty registration was declined',
                'preview' => 'Your department could not approve your Faculty account.',
                'heading' => 'Registration declined',
                'name' => $name,
                'paragraphs' => [
                    $this->department . ' could not approve your EquipTrack Faculty account, so it has been removed.',
                ],
                'note' => 'Reason: ' . ($this->reason ?: 'No reason given.'),
                'footnote' => 'If you think this is a mistake, contact your department. You can register again with the correct details.',
            ]);
    }
}
