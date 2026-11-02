<?php

namespace App\Services;

use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Support\LoanTime;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Day-by-day availability of one equipment item.
 *
 * A unit is reserved from the moment a request is approved: every loan that
 * is Ready for Pickup, Active or Overdue blocks the whole days from its borrow
 * date to its due date (a Within the Day loan blocks that one day). A loan
 * due one day and another starting the next day don't clash. An overdue loan
 * keeps blocking until the unit actually comes back, so also today.
 *
 * Units the lab can lend = units in the lab now (available_qty) + units out
 * on loan that will come back. Damaged units kept out of stock and lost units
 * (removed from the total) are therefore not lendable. Pending requests
 * reserve nothing: the first one approved gets the time.
 */
class EquipmentAvailability
{
    /** How far ahead the "Fully booked" list looks. */
    private const HORIZON_DAYS = 180;

    /**
     * @param  Collection<int, array{request_id:int, from:Carbon, to:Carbon, qty:int, out:bool, transaction:BorrowTransaction}>  $loans
     */
    private function __construct(
        private Equipment $equipment,
        private Collection $loans,
        private Carbon $now,
    ) {
    }

    /** Availability of one item; $exceptRequestId leaves that request's own loan out. */
    public static function for(Equipment $equipment, ?int $exceptRequestId = null): self
    {
        return self::forMany(collect([$equipment]), $exceptRequestId)[$equipment->equipment_id];
    }

    /**
     * Availability of many items with one query (the equipment catalogue).
     *
     * @return array<int, self> keyed by equipment_id
     */
    public static function forMany(Collection $equipment, ?int $exceptRequestId = null): array
    {
        $now = LoanTime::now();
        $ids = $equipment->pluck('equipment_id')->all();

        $loans = BorrowTransaction::with('request.user.student', 'request.user.facultyMember')
            ->whereIn('status', BorrowTransaction::OPEN)
            ->whereHas('request', fn ($query) => $query
                ->whereIn('equipment_id', $ids)
                ->where('overall_status', 'Approved')
                ->when($exceptRequestId, fn ($q) => $q->where('request_id', '!=', $exceptRequestId)))
            ->get()
            ->map(function (BorrowTransaction $transaction) use ($now) {
                $from = ($transaction->borrow_date ?? $now)->copy();
                $to = ($transaction->due_date ?? $from)->copy();

                // Still out past its due date: it blocks until it comes back.
                if ($transaction->isOnLoan() && $to->lt($now)) {
                    $to = $now->copy();
                }

                [$from, $to] = LoanTime::wholeDays($from, $to);

                return [
                    'equipment_id' => (int) $transaction->request->equipment_id,
                    'request_id' => (int) $transaction->request_id,
                    'from' => $from,
                    'to' => $to,
                    'qty' => max(1, (int) $transaction->request->quantity),
                    // Released units were taken out of available_qty; held ones were not.
                    'out' => $transaction->isOnLoan() && $transaction->return_restores_stock,
                    'transaction' => $transaction,
                ];
            })
            ->groupBy('equipment_id');

        $result = [];
        foreach ($equipment as $item) {
            $result[$item->equipment_id] = new self($item, $loans->get($item->equipment_id, collect()), $now);
        }

        return $result;
    }

    /** Units the lab can lend: in the lab now, plus units out that will come back. */
    public function lendableUnits(): int
    {
        return max(0, (int) $this->equipment->available_qty)
            + (int) $this->loans->where('out', true)->sum('qty');
    }

    /**
     * Approved loans that share at least one day with the period. Earliest first.
     *
     * @return Collection<int, array{request_id:int, from:Carbon, to:Carbon, qty:int, out:bool, transaction:BorrowTransaction}>
     */
    public function conflicts(CarbonInterface $from, CarbonInterface $to): Collection
    {
        [$from, $to] = LoanTime::wholeDays($from, $to);

        return $this->loans
            ->filter(fn (array $loan) => $loan['from']->lt($to) && $loan['to']->gt($from))
            ->sortBy(fn (array $loan) => $loan['from']->getTimestamp())
            ->values();
    }

    /** Units free for the whole period (the busiest day decides). */
    public function freeUnits(CarbonInterface $from, CarbonInterface $to): int
    {
        $conflicts = $this->conflicts($from, $to);
        [$from] = LoanTime::wholeDays($from, $to);
        $peak = 0;

        // Each clashing loan blocks [from, to); the busiest day is the first
        // day of the period or the first day of one of those loans.
        $moments = $conflicts->pluck('from')->push($from);
        foreach ($moments as $moment) {
            $booked = $conflicts
                ->filter(fn (array $loan) => $loan['from']->lte($moment) && $loan['to']->gt($moment))
                ->sum('qty');
            $peak = max($peak, (int) $booked);
        }

        return $this->lendableUnits() - $peak;
    }

    /** Whether $quantity units are free for the whole period. */
    public function isFree(CarbonInterface $from, CarbonInterface $to, int $quantity = 1): bool
    {
        return $this->freeUnits($from, $to) >= $quantity;
    }

    /**
     * Dates (from today on) when every lendable unit is booked, merged, for
     * the "Fully booked" line of the request form.
     *
     * @return array<int, array{from:string, to:string, label:string}>
     */
    public function fullyBookedRanges(): array
    {
        $lendable = $this->lendableUnits();
        $limit = $this->now->copy()->addDays(self::HORIZON_DAYS);

        $points = $this->loans
            ->flatMap(fn (array $loan) => [$loan['from'], $loan['to']])
            ->filter(fn (Carbon $point) => $point->lte($limit))
            ->map(fn (Carbon $point) => $point->format('Y-m-d H:i:s'))
            ->unique()
            ->sort()
            ->values();

        $ranges = [];
        $open = null;
        $last = null;

        for ($i = 0; $i < $points->count() - 1; $i++) {
            $start = Carbon::parse($points[$i]);
            $end = Carbon::parse($points[$i + 1]);

            $booked = $this->loans
                ->filter(fn (array $loan) => $loan['from']->lte($start) && $loan['to']->gte($end))
                ->sum('qty');
            $full = $lendable - $booked < 1 && $end->gt($this->now);

            // Listed from the booking's own start, even if that has passed.
            if ($full && $open === null) {
                $open = $start;
            }
            if (!$full && $open !== null) {
                $ranges[] = $this->range($open, $last);
                $open = null;
            }
            $last = $end;
        }

        if ($open !== null) {
            $ranges[] = $this->range($open, $last);
        }

        return $ranges;
    }

    /** Fully booked periods that clash with $from–$to (for the refusal message). */
    public function fullyBookedWithin(CarbonInterface $from, CarbonInterface $to): array
    {
        [$from, $to] = LoanTime::wholeDays($from, $to);

        return array_values(array_filter(
            $this->fullyBookedRanges(),
            fn (array $range) => Carbon::parse($range['from'])->lt($to)
                && Carbon::parse($range['to'])->addDay()->gt($from),
        ));
    }

    /** A period of dates for messages ("Nov 5", "Nov 5–7"). */
    public static function label(CarbonInterface $from, CarbonInterface $to): string
    {
        return LoanTime::range($from, $to);
    }

    /** $to is the start of the day after the last booked day; listed as that last day. */
    private function range(Carbon $from, Carbon $to): array
    {
        $to = $to->copy()->subDay();

        return [
            'from' => $from->format('Y-m-d H:i:s'),
            'to' => $to->format('Y-m-d H:i:s'),
            'label' => self::label($from, $to),
        ];
    }
}
