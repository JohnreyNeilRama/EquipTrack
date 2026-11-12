<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to an account's OLD email address when its email is changed, with a
 * link to undo the change (see App\Services\AdminEmailChange). Every account
 * type: Administrator, Laboratory Personnel, Student, Faculty.
 *
 * Sent right away (not queued), so no queue worker has to be running.
 */
class AdminEmailChangedNotification extends Notification
{
    public function __construct(
        public string $name,
        public string $oldEmail,
        public string $newEmail,
        public string $changedBy,
        public string $changedAt,
        public string $ipAddress,
        public string $undoUrl,
        public int $days,
        public string $accountType = 'Administrator',
        public string $helpFrom = 'another EquipTrack administrator',
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->accountType === 'Administrator'
                ? 'Your EquipTrack Admin email was changed'
                : 'Your EquipTrack account email was changed')
            ->view(['emails.admin-email-changed', 'emails.admin-email-changed-text'], [
                'name' => $this->name,
                'oldEmail' => $this->oldEmail,
                'newEmail' => $this->newEmail,
                'changedBy' => $this->changedBy,
                'changedAt' => $this->changedAt,
                'ipAddress' => $this->ipAddress,
                'undoUrl' => $this->undoUrl,
                'days' => $this->days,
                'accountType' => $this->accountType,
                'helpFrom' => $this->helpFrom,
            ]);
    }
}
