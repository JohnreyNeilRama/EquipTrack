<?php

namespace App\Support;

use App\Models\BorrowTransaction;
use Illuminate\Support\Carbon;

/**
 * The return-reminder schedule (config equiptrack.reminders), in one place so
 * the borrower's bell, the Lab Personnel and Admin bells and the reminder
 * emails always agree.
 *
 * Borrower, before the due date (one stage at a time):
 *   early       N days before (early_reminder_days), loans of long_loan_days+ only
 *   day-before  the day before the due date
 *   due-today   the due date
 *   same-day    a Within the Day loan, from its release: "return it today"
 *   final-hours a Within the Day loan, same_day_hours_before the deadline
 *               (skipped when it was released less than same_day_min_lead_hours
 *               before the deadline)
 *
 * After the deadline: overdue steps on 1, 3 and 7 office days late
 * (overdue_steps), then every overdue_repeat_days days.
 *
 * Times are compared as Philippine wall-clock times, the way loan dates are
 * stored (see LoanTime). instant() turns one into a real moment for display.
 */
class LoanReminders
{
    public const STAGE_EARLY = 'early';

    public const STAGE_DAY_BEFORE = 'day-before';

    public const STAGE_DUE_TODAY = 'due-today';

    public const STAGE_SAME_DAY = 'same-day';

    public const STAGE_FINAL = 'final-hours';

    // ---------------------------------------------------------------- settings

    public static function earlyDays(): int
    {
        return max(0, (int) self::setting('early_reminder_days', 3));
    }

    public static function longLoanDays(): int
    {
        return max(0, (int) self::setting('long_loan_days', 5));
    }

    public static function sameDayHoursBefore(): int
    {
        return max(0, (int) self::setting('same_day_hours_before', 2));
    }

    public static function sameDayMinLeadHours(): int
    {
        return max(0, (int) self::setting('same_day_min_lead_hours', 3));
    }

    /** @return array<int, int> ascending, e.g. [1, 3, 7] */
    public static function overdueSteps(): array
    {
        $steps = collect((array) self::setting('overdue_steps', [1, 3, 7]))
            ->map(fn ($step) => (int) $step)
            ->filter(fn (int $step) => $step > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $steps ?: [1];
    }

    public static function overdueRepeatDays(): int
    {
        return max(1, (int) self::setting('overdue_repeat_days', 7));
    }

    public static function departmentFollowUpDays(): int
    {
        return max(1, (int) self::setting('department_follow_up_days', 3));
    }

    public static function adminEscalationDays(): int
    {
        return max(1, (int) self::setting('admin_escalation_days', 7));
    }

    public static function staleRequestDays(): int
    {
        return max(1, (int) self::setting('admin_stale_request_days', 2));
    }

    public static function staleProofDays(): int
    {
        return max(1, (int) self::setting('admin_stale_proof_days', 3));
    }

    // ------------------------------------------------------------------ loans

    /** Released, not returned, and the return not submitted yet: reminders apply. */
    public static function isOut(BorrowTransaction $loan): bool
    {
        return $loan->isOnLoan() && !$loan->isReturnPending() && $loan->due_date !== null;
    }

    /** Borrowed and due on the same date. */
    public static function isWithinTheDay(BorrowTransaction $loan): bool
    {
        return LoanTime::isWithinTheDay($loan->borrow_date, $loan->due_date);
    }

    /** When the borrower got it (wall-clock): the release time, else opening time on the borrow date. */
    public static function releasedAt(BorrowTransaction $loan): Carbon
    {
        if ($loan->released_at) {
            return LoanTime::fromInstant($loan->released_at);
        }

        return self::atOpening($loan->borrow_date ?? LoanTime::now());
    }

    /** Calendar days from the borrow date to the due date (0 for Within the Day). */
    public static function loanDays(BorrowTransaction $loan): int
    {
        if (!$loan->borrow_date || !$loan->due_date) {
            return 0;
        }

        return max(0, (int) self::dateOf($loan->borrow_date)->diffInDays(self::dateOf($loan->due_date), false));
    }

    /** The "N hours left" moment of a Within the Day loan (wall-clock). */
    public static function finalReminderAt(BorrowTransaction $loan): Carbon
    {
        return $loan->due_date->copy()->subHours(self::sameDayHoursBefore());
    }

    /** A Within the Day loan released early enough to get the "N hours left" reminder. */
    public static function getsFinalReminder(BorrowTransaction $loan): bool
    {
        return self::isWithinTheDay($loan)
            && self::sameDayHoursBefore() > 0
            && self::releasedAt($loan)->lte($loan->due_date->copy()->subHours(self::sameDayMinLeadHours()));
    }

    /**
     * The borrower's current before-due stage, or null (not out, overdue, or
     * not due soon yet).
     *
     * @return array{stage: string, at: Carbon, daysLeft: int}|null  at is a real moment (see instant())
     */
    public static function upcoming(BorrowTransaction $loan, ?Carbon $now = null): ?array
    {
        $now ??= LoanTime::now();

        if (!self::isOut($loan) || $loan->status === BorrowTransaction::STATUS_OVERDUE || LoanTime::isOverdue($loan->due_date, $now)) {
            return null;
        }

        $today = self::dateOf($now);
        $dueDay = self::dateOf($loan->due_date);
        $daysLeft = (int) $today->diffInDays($dueDay, false);
        $released = self::releasedAt($loan);
        // A stage "arrives" at the start of its day, or when the item was released if that was later.
        $from = fn (Carbon $day) => self::instant($released->gt($day) ? $released : $day);

        if ($daysLeft === 0 && self::isWithinTheDay($loan)) {
            if (self::getsFinalReminder($loan) && $now->gte(self::finalReminderAt($loan))) {
                return ['stage' => self::STAGE_FINAL, 'at' => self::instant(self::finalReminderAt($loan)), 'daysLeft' => 0];
            }

            return ['stage' => self::STAGE_SAME_DAY, 'at' => $from($today), 'daysLeft' => 0];
        }

        if ($daysLeft === 0) {
            return ['stage' => self::STAGE_DUE_TODAY, 'at' => $from($today), 'daysLeft' => 0];
        }

        if ($daysLeft === 1) {
            return ['stage' => self::STAGE_DAY_BEFORE, 'at' => $from($today), 'daysLeft' => 1];
        }

        $early = self::earlyDays();
        if ($early >= 2 && $daysLeft <= $early && self::loanDays($loan) >= self::longLoanDays()) {
            return ['stage' => self::STAGE_EARLY, 'at' => $from($dueDay->copy()->subDays($early)), 'daysLeft' => $daysLeft];
        }

        return null;
    }

    /**
     * Office days late: 0 while not overdue, 1 from the moment the deadline
     * passes (see LoanTime::daysLate). A loan already flagged Overdue counts
     * as at least 1.
     */
    public static function daysLate(BorrowTransaction $loan, ?Carbon $now = null): int
    {
        if (!self::isOut($loan)) {
            return 0;
        }

        $days = LoanTime::daysLate($loan->due_date, $now);

        return $days === 0 && $loan->status === BorrowTransaction::STATUS_OVERDUE ? 1 : $days;
    }

    /** The overdue step reached after $daysLate office days late: 0, then 1, 3, 7, 14, 21, … */
    public static function overdueStep(int $daysLate): int
    {
        if ($daysLate <= 0) {
            return 0;
        }

        $steps = self::overdueSteps();
        $last = end($steps);

        if ($daysLate >= $last) {
            return $last + intdiv($daysLate - $last, self::overdueRepeatDays()) * self::overdueRepeatDays();
        }

        $reached = 0;
        foreach ($steps as $step) {
            if ($daysLate >= $step) {
                $reached = $step;
            }
        }

        return $reached;
    }

    /**
     * When a loan reached $days office days late (a real moment): the deadline
     * itself for day 1, else opening time on that office day.
     */
    public static function lateSince(BorrowTransaction $loan, int $days): Carbon
    {
        if ($days <= 1) {
            $due = $loan->due_date;

            return self::instant($due->lt(LoanTime::now()) ? $due : LoanTime::now());
        }

        $day = self::dateOf($loan->due_date);
        for ($counted = 0; $counted < $days;) {
            $day->addDay();
            if (LoanTime::isOfficeDay($day)) {
                $counted++;
            }
        }

        return self::instant(self::atOpening($day));
    }

    // ------------------------------------------------------------------ times

    /** Office day and office hours (opening time until closing time): when emails may go out. */
    public static function isEmailWindow(?Carbon $now = null): bool
    {
        $now ??= LoanTime::now();

        if (!LoanTime::isOfficeDay($now)) {
            return false;
        }

        $time = $now->format('H:i');

        return $time >= LoanTime::opens() && $time < LoanTime::closes();
    }

    /** A Philippine wall-clock time as a real moment, for "x hours ago" and ISO times. */
    public static function instant(Carbon $wallClock): Carbon
    {
        return Carbon::parse($wallClock->format('Y-m-d H:i:s'), config('app.display_timezone'));
    }

    /** "7:00 PM" of a due moment. */
    public static function time(Carbon $due): string
    {
        return $due->format('g:i A');
    }

    /** "2 hours" / "1 hour". */
    public static function hoursLabel(int $hours): string
    {
        return $hours . ' ' . ($hours === 1 ? 'hour' : 'hours');
    }

    /** "1 day" / "3 days". */
    public static function daysLabel(int $days): string
    {
        return $days . ' ' . ($days === 1 ? 'day' : 'days');
    }

    private static function dateOf(Carbon $value): Carbon
    {
        return Carbon::parse($value->format('Y-m-d'));
    }

    private static function atOpening(Carbon $date): Carbon
    {
        [$h, $m] = array_map('intval', explode(':', LoanTime::opens()));

        return self::dateOf($date)->setTime($h, $m);
    }

    private static function setting(string $key, mixed $default): mixed
    {
        return config('equiptrack.reminders.' . $key, $default);
    }
}
