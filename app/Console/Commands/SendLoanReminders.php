<?php

namespace App\Console\Commands;

use App\Models\BorrowTransaction;
use App\Notifications\LoanReminderNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Emails Student/Faculty borrowers about equipment they still have:
 *
 *  - Due soon: once, when the item is due tomorrow or today.
 *  - Overdue: the day after the due date, then again every
 *    OVERDUE_REPEAT_DAYS days until the item is returned.
 *
 * Loans whose return the department is still confirming are skipped, as are
 * deactivated accounts. Dates are Philippine dates. Each reminder is recorded
 * on the loan (due_reminder_sent_at / overdue_reminder_sent_at), so running
 * the command again the same day sends nothing new.
 *
 * Scheduled daily in routes/console.php.
 */
class SendLoanReminders extends Command
{
    protected $signature = 'equiptrack:send-loan-reminders {--dry-run : List the reminders that would be sent without sending them}';

    protected $description = 'Email borrowers whose equipment is due soon or overdue';

    /** Days between repeat overdue emails for the same loan. */
    public const OVERDUE_REPEAT_DAYS = 3;

    public function handle(): int
    {
        $today = BorrowTransaction::today();
        $dryRun = (bool) $this->option('dry-run');
        $sent = ['due' => 0, 'overdue' => 0];
        $failed = 0;

        $loans = BorrowTransaction::with('request.equipment', 'request.user')
            ->where('status', '!=', 'Returned')
            ->whereNotNull('due_date')
            ->whereHas('request', fn ($query) => $query->where('overall_status', 'Approved'))
            ->get();

        foreach ($loans as $loan) {
            $type = $this->reminderDue($loan, $today);
            $user = $loan->request?->user;

            if ($type === null || !$user || !filled($user->email) || strcasecmp((string) ($user->status ?: 'Active'), 'Active') !== 0) {
                continue;
            }

            $line = sprintf('%s reminder: loan #%d (%s) to %s, due %s',
                $type === LoanReminderNotification::OVERDUE ? 'Overdue' : 'Due soon',
                $loan->transaction_id,
                $loan->request?->equipment?->name ?? 'equipment',
                $user->email,
                $loan->due_date->format('M d, Y'));

            if ($dryRun) {
                $this->line('[dry run] ' . $line);
                $sent[$type]++;
                continue;
            }

            try {
                $user->notify(new LoanReminderNotification($loan, $type, $today));
            } catch (Throwable $e) {
                // Not recorded as sent, so tomorrow's run tries again.
                $failed++;
                Log::warning('Loan reminder email failed', ['transaction_id' => $loan->transaction_id, 'error' => $e->getMessage()]);
                $this->warn('Failed: ' . $line . ' (' . $e->getMessage() . ')');
                continue;
            }

            $loan->forceFill([
                $type === LoanReminderNotification::OVERDUE ? 'overdue_reminder_sent_at' : 'due_reminder_sent_at' => now(),
            ])->save();

            $sent[$type]++;
            $this->line('Sent ' . $line);
        }

        $this->info(sprintf('%s%d due-soon and %d overdue reminder(s)%s.',
            $dryRun ? 'Would send ' : 'Sent ',
            $sent['due'],
            $sent['overdue'],
            $failed ? ", {$failed} failed" : ''));

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /** Which reminder, if any, this loan needs today. */
    private function reminderDue(BorrowTransaction $loan, Carbon $today): ?string
    {
        // Handed back; the department is confirming it.
        if ($loan->isReturnPending()) {
            return null;
        }

        $due = $loan->due_date->copy()->startOfDay();

        if ($due->lt($today)) {
            $last = $loan->overdue_reminder_sent_at?->copy()->timezone(config('app.display_timezone'))->startOfDay();

            return $last === null || $last->diffInDays($today) >= self::OVERDUE_REPEAT_DAYS
                ? LoanReminderNotification::OVERDUE
                : null;
        }

        // Due tomorrow or today, and not reminded yet.
        $daysLeft = (int) $today->diffInDays($due);

        return $daysLeft <= 1 && $loan->due_reminder_sent_at === null
            ? LoanReminderNotification::DUE_SOON
            : null;
    }
}
