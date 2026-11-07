<?php

namespace App\Console\Commands;

use App\Models\BorrowTransaction;
use App\Notifications\LoanReminderNotification;
use App\Support\LoanReminders;
use App\Support\LoanTime;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Emails Student/Faculty borrowers about equipment they still have, on the
 * schedule in App\Support\LoanReminders (config equiptrack.reminders):
 *
 *  - Due soon: once, the day before the due date, or on the due date when the
 *    borrower only got it the day before (or the day before was closed).
 *  - Within the Day loans: "return it today" when released, then "N hours
 *    left" before the deadline (unless released less than a few hours before).
 *  - Overdue: on each overdue step (1, 3 and 7 office days late, then
 *    weekly) until the item is returned. Day 1's email goes out the next
 *    office morning, not at night.
 *
 * Emails only go out on office days, during office hours. Loans whose return
 * the department is still confirming are skipped, as are deactivated
 * accounts. Each reminder is recorded on the loan (due_reminder_sent_at,
 * release_reminder_sent_at, final_reminder_sent_at, overdue_reminder_step),
 * so running the command again sends nothing new.
 *
 * Scheduled every 15 minutes in routes/console.php.
 */
class SendLoanReminders extends Command
{
    protected $signature = 'equiptrack:send-loan-reminders {--dry-run : List the reminders that would be sent without sending them}';

    protected $description = 'Email borrowers whose equipment is due soon, due today or overdue';

    public function handle(): int
    {
        $now = LoanTime::now();
        $today = BorrowTransaction::today();

        // The office is closed (e.g. Sunday): nothing can be returned today,
        // so reminders wait for the next office day.
        if (!LoanTime::isOfficeDay($today)) {
            $this->info('The equipment office is closed on ' . LoanTime::dayName($today) . 's; no reminders sent.');

            return self::SUCCESS;
        }

        if (!LoanReminders::isEmailWindow($now)) {
            $this->info('Outside office hours (' . LoanTime::clock(LoanTime::opens()) . '–' . LoanTime::clock(LoanTime::closes())
                . '); no reminders sent.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $sent = ['due' => 0, 'same-day' => 0, 'overdue' => 0];
        $failed = 0;

        $loans = BorrowTransaction::with('request.equipment', 'request.user')
            // Only loans the borrower actually has: never one still waiting for pickup.
            ->onLoan()
            ->whereNotNull('due_date')
            ->whereHas('request', fn ($query) => $query->where('overall_status', 'Approved'))
            ->get();

        foreach ($loans as $loan) {
            $user = $loan->request?->user;

            if (!LoanReminders::isOut($loan) || !$user || !filled($user->email)
                || strcasecmp((string) ($user->status ?: 'Active'), 'Active') !== 0) {
                continue;
            }

            $plan = $this->reminderDue($loan, $now);

            if ($plan === null) {
                continue;
            }

            [$type, $step] = $plan;
            $group = match ($type) {
                LoanReminderNotification::OVERDUE => 'overdue',
                LoanReminderNotification::DUE_SOON => 'due',
                default => 'same-day',
            };

            $line = sprintf('%s reminder: loan #%d (%s) to %s, due %s',
                $this->label($type, $step),
                $loan->transaction_id,
                $loan->request?->equipment?->name ?? 'equipment',
                $user->email,
                LoanTime::dueBy($loan->due_date));

            if ($dryRun) {
                $this->line('[dry run] ' . $line);
                $sent[$group]++;

                continue;
            }

            try {
                $user->notify(new LoanReminderNotification($loan, $type, $today, $step));
            } catch (Throwable $e) {
                // Not recorded as sent, so the next run tries again.
                $failed++;
                Log::warning('Loan reminder email failed', ['transaction_id' => $loan->transaction_id, 'error' => $e->getMessage()]);
                $this->warn('Failed: ' . $line . ' (' . $e->getMessage() . ')');

                continue;
            }

            $this->record($loan, $type, $step, $now);
            $sent[$group]++;
            $this->line('Sent ' . $line);
        }

        $this->info(sprintf('%s%d due-soon, %d same-day and %d overdue reminder(s)%s.',
            $dryRun ? 'Would send ' : 'Sent ',
            $sent['due'],
            $sent['same-day'],
            $sent['overdue'],
            $failed ? ", {$failed} failed" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Which email, if any, this loan needs now.
     *
     * @return array{0: string, 1: int}|null  [type, overdue step]
     */
    private function reminderDue(BorrowTransaction $loan, Carbon $now): ?array
    {
        $daysLate = LoanReminders::daysLate($loan, $now);

        if ($daysLate > 0) {
            $step = LoanReminders::overdueStep($daysLate);

            return $step > (int) $loan->overdue_reminder_step ? [LoanReminderNotification::OVERDUE, $step] : null;
        }

        $upcoming = LoanReminders::upcoming($loan, $now);

        if ($upcoming === null) {
            return null;
        }

        if (in_array($upcoming['stage'], [LoanReminders::STAGE_SAME_DAY, LoanReminders::STAGE_FINAL], true)) {
            if ($loan->release_reminder_sent_at === null) {
                return [LoanReminderNotification::SAME_DAY, 0];
            }

            return $upcoming['stage'] === LoanReminders::STAGE_FINAL && $loan->final_reminder_sent_at === null
                ? [LoanReminderNotification::FINAL, 0]
                : null;
        }

        if (in_array($upcoming['stage'], [LoanReminders::STAGE_DAY_BEFORE, LoanReminders::STAGE_DUE_TODAY], true)
            && $loan->due_reminder_sent_at === null) {
            return [LoanReminderNotification::DUE_SOON, 0];
        }

        return null;
    }

    /** Remember what was sent, so it is never sent twice. */
    private function record(BorrowTransaction $loan, string $type, int $step, Carbon $now): void
    {
        $at = now();

        $changes = match ($type) {
            LoanReminderNotification::OVERDUE => ['overdue_reminder_step' => $step, 'overdue_reminder_sent_at' => $at],
            LoanReminderNotification::FINAL => ['final_reminder_sent_at' => $at],
            LoanReminderNotification::SAME_DAY => ['release_reminder_sent_at' => $at, 'due_reminder_sent_at' => $at]
                // Released (or first seen) when the "hours left" time has already come: this email covers both.
                + ($now->gte(LoanReminders::finalReminderAt($loan)) ? ['final_reminder_sent_at' => $at] : []),
            default => ['due_reminder_sent_at' => $at],
        };

        $loan->forceFill($changes)->save();
    }

    private function label(string $type, int $step): string
    {
        return match ($type) {
            LoanReminderNotification::OVERDUE => 'Overdue (day ' . $step . ')',
            LoanReminderNotification::SAME_DAY => 'Return today',
            LoanReminderNotification::FINAL => 'Hours left',
            default => 'Due soon',
        };
    }
}
