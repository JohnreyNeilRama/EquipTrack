<?php

namespace App\Notifications;

use App\Models\BorrowTransaction;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Reminder email to a Student/Faculty borrower about equipment they still
 * have: "due soon" before the due date, "overdue" after it. Sent by the
 * equiptrack:send-loan-reminders command (scheduled daily).
 *
 * Sent right away (not queued), so no queue worker has to be running.
 */
class LoanReminderNotification extends Notification
{
    public const DUE_SOON = 'due';

    public const OVERDUE = 'overdue';

    /**
     * @param  string  $type  self::DUE_SOON or self::OVERDUE
     * @param  Carbon  $today  today's date in Philippine time
     */
    public function __construct(
        public BorrowTransaction $loan,
        public string $type,
        public Carbon $today,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $equipment = $this->loan->request?->equipment?->name ?? 'borrowed equipment';
        $due = $this->loan->due_date->copy()->startOfDay();
        $overdue = $this->type === self::OVERDUE;
        $daysLate = $overdue ? (int) $due->diffInDays($this->today) : 0;
        $daysLeft = $overdue ? 0 : (int) $this->today->diffInDays($due, false);

        $when = $overdue
            ? $daysLate . ' ' . Str::plural('day', $daysLate) . ' overdue'
            : ($daysLeft <= 0 ? 'due today' : 'due tomorrow');

        return (new MailMessage)
            ->subject($overdue
                ? "Overdue: please return {$equipment}"
                : "Reminder: {$equipment} is {$when}")
            ->view(['emails.loan-reminder', 'emails.loan-reminder-text'], [
                'overdue' => $overdue,
                'name' => trim((string) $notifiable->fullName()) ?: 'there',
                'equipment' => $equipment,
                'when' => $when,
                'borrowDate' => $this->loan->borrow_date?->format('M d, Y') ?? 'N/A',
                'dueDate' => $due->format('M d, Y'),
                'daysLate' => $daysLate,
                'returnsUrl' => route('user.returns'),
            ]);
    }
}
