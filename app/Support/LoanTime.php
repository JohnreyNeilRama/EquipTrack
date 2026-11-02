<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Borrow and due dates of loans.
 *
 * Borrowers choose dates only. A loan runs for whole days: it can be picked
 * up any time during office hours on the borrow date, and must be back by the
 * return deadline (config equiptrack.return_deadline, e.g. 7:00 PM) on the due
 * date. "Within the Day" loans are borrowed and due on the same date.
 *
 * Dates are stored as Philippine wall-clock times: borrow dates at 00:00, due
 * dates at the return deadline. A loan is overdue once that deadline has
 * passed. Older records due at 23:59 keep their end-of-day due time.
 */
class LoanTime
{
    /** The current Philippine date and time, read the way loan times are stored. */
    public static function now(): Carbon
    {
        return Carbon::parse(Carbon::now(config('app.display_timezone'))->format('Y-m-d H:i:s'));
    }

    /** A real moment (e.g. released_at, saved in UTC) as Philippine wall-clock time. */
    public static function fromInstant(CarbonInterface $moment): Carbon
    {
        return Carbon::parse($moment->copy()->timezone(config('app.display_timezone'))->format('Y-m-d H:i:s'));
    }

    /**
     * A due date as stored: the date at the return deadline. Dates come from
     * the date-only form ("2026-11-05"); any time given with them is replaced,
     * except the 23:59 of older end-of-day records, which is kept.
     */
    public static function dueValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        $date = $value instanceof CarbonInterface ? Carbon::parse($value->format('Y-m-d H:i:s')) : Carbon::parse($value);

        if ($date->format('H:i') === '23:59') {
            return $date;
        }

        [$h, $m] = array_map('intval', explode(':', self::deadline()));

        return $date->setTime($h, $m);
    }

    /** The due moment for a date: that date at the return deadline. */
    public static function dueOn(string $ymd): Carbon
    {
        return self::dueValue($ymd);
    }

    /**
     * The whole days a booking uses, from the start of its first day to the
     * start of the day after its last (so loans on back-to-back days don't clash).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function wholeDays(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = Carbon::parse($from->format('Y-m-d'));
        $end = Carbon::parse($to->format('Y-m-d'))->addDay();

        return [$start, $end->lte($start) ? $start->copy()->addDay() : $end];
    }

    /** Borrowed and due on the same date. */
    public static function isWithinTheDay(?CarbonInterface $from, ?CarbonInterface $due): bool
    {
        return $from !== null && $due !== null && $from->isSameDay($due);
    }

    /** Whether today's return deadline has passed (no more Within the Day loans today). */
    public static function deadlinePassedToday(): bool
    {
        return self::dueOn(self::now()->toDateString())->lte(self::now());
    }

    public static function isOverdue(?CarbonInterface $due, ?CarbonInterface $now = null): bool
    {
        return $due !== null && $due->lt($now ?? self::now());
    }

    /**
     * Days late, counted in dates: 0 when on time, 1 once the due date's
     * deadline has passed (even later that same day), then the number of
     * dates after the due date.
     */
    public static function daysLate(?CarbonInterface $due, ?CarbonInterface $end = null): int
    {
        $end ??= self::now();

        if ($due === null || !$due->lt($end)) {
            return 0;
        }

        $days = (int) Carbon::parse($due->format('Y-m-d'))->diffInDays(Carbon::parse($end->format('Y-m-d')));

        return max(1, $days);
    }

    /** "Nov 05, 2026" (dates only: borrowers choose no times). */
    public static function format(mixed $value, string $empty = '—'): string
    {
        if ($value === null || $value === '') {
            return $empty;
        }

        $date = $value instanceof CarbonInterface ? $value : Carbon::parse($value);

        return $date->format('M d, Y');
    }

    /** "Nov 05, 2026 by 7:00 PM" for messages (older end-of-day records: the date alone). */
    public static function dueBy(?CarbonInterface $due, string $empty = '—'): string
    {
        if ($due === null) {
            return $empty;
        }

        return self::format($due) . ($due->format('H:i') === '23:59' ? '' : ' by ' . $due->format('g:i A'));
    }

    /** "Nov 5" (the year when it isn't this year). */
    public static function short(CarbonInterface $value): string
    {
        $year = $value->year !== self::now()->year ? ', ' . $value->year : '';

        return $value->format('M j') . $year;
    }

    /** A period of dates: "Nov 5", "Nov 5–7", "Nov 28 – Dec 2". */
    public static function range(CarbonInterface $from, CarbonInterface $to): string
    {
        $year = fn (CarbonInterface $d) => $d->year !== self::now()->year ? ', ' . $d->year : '';

        if ($from->isSameDay($to)) {
            return $from->format('M j') . $year($from);
        }

        return $from->isSameMonth($to)
            ? $from->format('M j') . '–' . $to->format('j') . $year($to)
            : $from->format('M j') . $year($from) . ' – ' . $to->format('M j') . $year($to);
    }

    public static function opens(): string
    {
        return (string) config('equiptrack.lab_opens', '07:00');
    }

    public static function closes(): string
    {
        return (string) config('equiptrack.lab_closes', '19:00');
    }

    /** The time on the due date by which equipment must be returned (HH:MM). */
    public static function deadline(): string
    {
        return (string) config('equiptrack.return_deadline', self::closes());
    }

    /** "7:00 AM" / "7:00 PM" for messages. */
    public static function clock(string $hhmm): string
    {
        return Carbon::createFromFormat('H:i', $hhmm)->format('g:i A');
    }
}
