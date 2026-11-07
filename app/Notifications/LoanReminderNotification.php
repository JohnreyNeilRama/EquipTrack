<?php

namespace App\Notifications;

use App\Models\BorrowTransaction;
use App\Support\LoanReminders;
use App\Support\LoanTime;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Reminder email to a Student/Faculty borrower about equipment they still
 * have, on the schedule in App\Support\LoanReminders:
 *
 *  - DUE_SOON  the day before the due date (or on it)
 *  - SAME_DAY  a Within the Day loan, when it is released: "return it today"
 *  - FINAL     a Within the Day loan, a few hours before the deadline
 *  - OVERDUE   on each overdue step (1, 3, 7 office days late, then weekly)
 *  - MANUAL    sent by hand by Admin or Lab Personnel (the "Remind" button)
 *
 * Sent by the equiptrack:send-loan-reminders command (DUE_SOON … OVERDUE) or
 * App\Services\ManualLoanReminder (MANUAL). Sent right away (not queued), so
 * no queue worker has to be running.
 */
class LoanReminderNotification extends Notification
{
    public const DUE_SOON = 'due';

    public const SAME_DAY = 'same-day';

    public const FINAL = 'final';

    public const OVERDUE = 'overdue';

    public const MANUAL = 'manual';

    /**
     * @param  string  $type  one of the constants above
     * @param  Carbon  $today  today's date in Philippine time
     * @param  int  $step  the overdue step being reminded (OVERDUE)
     * @param  string|null  $sender  who sent it (MANUAL), e.g. "Admin Maria Santos"
     */
    public function __construct(
        public BorrowTransaction $loan,
        public string $type,
        public Carbon $today,
        public int $step = 0,
        public ?string $sender = null,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $equipment = $this->loan->request?->equipment?->name ?? 'borrowed equipment';
        $dueDay = $this->loan->due_date->copy()->startOfDay();
        $daysLate = LoanTime::isOverdue($this->loan->due_date) || $this->type === self::OVERDUE
            ? max(1, LoanTime::daysLate($this->loan->due_date))
            : 0;
        $overdue = $daysLate > 0 && in_array($this->type, [self::OVERDUE, self::MANUAL], true);
        $daysLeft = (int) $this->today->diffInDays($dueDay, false);
        $by = LoanReminders::time($this->loan->due_date);
        $hours = LoanReminders::hoursLabel(LoanReminders::sameDayHoursBefore());
        $escalated = $this->type === self::OVERDUE && $daysLate >= LoanReminders::adminEscalationDays();

        $when = match (true) {
            $overdue => LoanReminders::daysLabel($daysLate) . ' overdue',
            $this->type === self::FINAL => 'due in ' . $hours,
            $daysLeft <= 0 => 'due today',
            $daysLeft === 1 => 'due tomorrow',
            default => 'due in ' . $daysLeft . ' days',
        };

        $subject = match (true) {
            $this->type === self::MANUAL => "Reminder from the lab: please return {$equipment}",
            $escalated => "Final notice: please return {$equipment}",
            $overdue => "Overdue: please return {$equipment}",
            $this->type === self::SAME_DAY => "Return {$equipment} today by {$by}",
            $this->type === self::FINAL => ucfirst($hours) . " left: return {$equipment} by {$by}",
            default => "Reminder: {$equipment} is {$when}",
        };

        $heading = match (true) {
            $escalated => 'Final notice: your borrowed equipment is overdue',
            $overdue => 'Your borrowed equipment is overdue',
            $this->type === self::SAME_DAY => 'Please return your equipment today',
            $this->type === self::FINAL => 'Only ' . $hours . ' left to return your equipment',
            default => 'Your borrowed equipment is due soon',
        };

        $extra = match (true) {
            $this->type === self::MANUAL => ($this->sender ?: 'The laboratory') . ' sent you this reminder.',
            $escalated => 'This has been reported to the Admin.',
            default => null,
        };

        return (new MailMessage)
            ->subject($subject)
            ->view(['emails.loan-reminder', 'emails.loan-reminder-text'], [
                'overdue' => $overdue,
                'heading' => $heading,
                'extra' => $extra,
                'name' => trim((string) $notifiable->fullName()) ?: 'there',
                'equipment' => $equipment,
                'when' => $when,
                'borrowDate' => LoanTime::format($this->loan->borrow_date, 'N/A'),
                // Reminders before the deadline say by what time; overdue ones only the date.
                'dueDate' => $overdue ? LoanTime::format($this->loan->due_date) : LoanTime::dueBy($this->loan->due_date),
                'daysLate' => $daysLate,
                'returnsUrl' => route('user.returns'),
            ]);
    }
}
