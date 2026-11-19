<?php

namespace App\Services;

use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moves an equipment item's units between Available, On Loan, Under
 * Maintenance, Lost and Retired, and keeps the item's available_qty and
 * total_qty counted from them:
 *
 *   total_qty     = units that still exist (not Lost or Retired)
 *   available_qty = units that are Available (in the lab, ready to lend)
 *
 * Callers lock the equipment row (lockForUpdate) inside a DB transaction
 * before moving units, as the stock methods on BorrowTransaction do.
 *
 * Loans made before unit tracking, and test records made without a release,
 * have no units linked to them; their units are then taken from the ones not
 * linked to another open loan, so the counts move exactly as they used to.
 */
class EquipmentUnits
{
    /** Set while the counts are written from the units, so the model does not re-match them. */
    private static bool $syncing = false;

    public static function isSyncing(): bool
    {
        return self::$syncing;
    }

    /**
     * Adds $count units to the item, numbered after its highest code (codes
     * are never reused). The first one gets $firstSerial when given.
     *
     * @return Collection<int, EquipmentUnit>
     */
    public static function create(Equipment $equipment, int $count, string $status = EquipmentUnit::STATUS_AVAILABLE, ?string $firstSerial = null): Collection
    {
        $created = collect();
        if ($count < 1) {
            return $created;
        }

        $number = (int) EquipmentUnit::where('equipment_id', $equipment->equipment_id)
            ->pluck('unit_code')
            ->map(fn ($code) => EquipmentUnit::numberOf($code))
            ->max();
        $firstSerial = $firstSerial !== null && trim($firstSerial) !== '' ? trim($firstSerial) : null;

        for ($i = 0; $i < $count; $i++) {
            $number++;
            $created->push(EquipmentUnit::create([
                'equipment_id' => $equipment->equipment_id,
                'unit_code' => EquipmentUnit::codeFor((int) $equipment->equipment_id, $number),
                'serial_number' => $i === 0 ? $firstSerial : null,
                'status' => $status,
                'date_added' => now(),
            ]));
        }

        return $created;
    }

    /** Adds one unit (next code) with its own serial number and status. */
    public static function createOne(Equipment $equipment, ?string $serial, string $status = EquipmentUnit::STATUS_AVAILABLE): EquipmentUnit
    {
        return self::create($equipment, 1, $status, $serial)->first();
    }

    /** Writes available_qty and total_qty from the item's units. */
    public static function syncCounts(Equipment $equipment): void
    {
        $counts = EquipmentUnit::where('equipment_id', $equipment->equipment_id)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $total = (int) $counts->except(EquipmentUnit::GONE)->sum();
        $available = (int) ($counts[EquipmentUnit::STATUS_AVAILABLE] ?? 0);

        self::$syncing = true;
        try {
            $equipment->forceFill(['total_qty' => $total, 'available_qty' => $available])->saveQuietly();
        } finally {
            self::$syncing = false;
        }
    }

    /**
     * Matches the units to quantities typed on the equipment form: adds or
     * removes units for the total, then makes as many Available as typed.
     * Units out on loan are never touched; a removed unit that was ever lent
     * is retired (its history stays), one that never was is deleted. When the
     * typed numbers can't be met (e.g. a total below the units on loan), the
     * counts end up as close as the units allow.
     */
    public static function matchCounts(Equipment $equipment, int $total, int $available): void
    {
        $total = max(0, $total);
        $active = self::units($equipment)->whereNotIn('status', EquipmentUnit::GONE)->get();

        if ($total > $active->count()) {
            self::create($equipment, $total - $active->count());
        } elseif ($total < $active->count()) {
            $removable = $active
                ->whereIn('status', [EquipmentUnit::STATUS_AVAILABLE, EquipmentUnit::STATUS_MAINTENANCE])
                ->sortBy([
                    fn ($a, $b) => self::removeOrder($a) <=> self::removeOrder($b),
                    fn ($a, $b) => EquipmentUnit::numberOf($b->unit_code) <=> EquipmentUnit::numberOf($a->unit_code),
                ])
                ->take($active->count() - $total);

            foreach ($removable as $unit) {
                if ($unit->transactions()->exists()) {
                    $unit->update(['status' => EquipmentUnit::STATUS_RETIRED]);
                } else {
                    $unit->delete();
                }
            }
        }

        // Available among the units in the lab (not on loan).
        $inLab = self::units($equipment)
            ->whereIn('status', [EquipmentUnit::STATUS_AVAILABLE, EquipmentUnit::STATUS_MAINTENANCE])
            ->get();
        $wanted = min(max(0, $available), $inLab->count());
        $current = $inLab->where('status', EquipmentUnit::STATUS_AVAILABLE);

        if ($current->count() > $wanted) {
            $current->sortByDesc(fn ($u) => EquipmentUnit::numberOf($u->unit_code))
                ->take($current->count() - $wanted)
                ->each(fn ($u) => $u->update(['status' => EquipmentUnit::STATUS_MAINTENANCE]));
        } elseif ($current->count() < $wanted) {
            $inLab->where('status', EquipmentUnit::STATUS_MAINTENANCE)
                ->sortBy(fn ($u) => EquipmentUnit::numberOf($u->unit_code))
                ->take($wanted - $current->count())
                ->each(fn ($u) => $u->update(['status' => EquipmentUnit::STATUS_AVAILABLE]));
        }

        self::syncCounts($equipment);
    }

    /**
     * Release: hands $quantity Available units to the loan: the ones Lab
     * Personnel chose ($unitIds), else the lowest codes first. Returns false,
     * changing nothing, when they are not all Available in the lab.
     */
    public static function takeOut(BorrowTransaction $transaction, Equipment $equipment, int $quantity, ?array $unitIds = null): bool
    {
        $quantity = max(1, $quantity);
        $units = self::units($equipment)
            ->where('status', EquipmentUnit::STATUS_AVAILABLE)
            ->when($unitIds !== null, fn ($q) => $q->whereIn('unit_id', $unitIds))
            ->get()
            ->sortBy(fn ($u) => EquipmentUnit::numberOf($u->unit_code))
            ->take($quantity);

        if ($units->count() < $quantity || ($unitIds !== null && count(array_unique($unitIds)) !== $quantity)) {
            return false;
        }

        foreach ($units as $unit) {
            $unit->update(['status' => EquipmentUnit::STATUS_ON_LOAN]);
        }
        $transaction->units()->syncWithoutDetaching($units->pluck('unit_id')->all());

        self::syncCounts($equipment);

        return true;
    }

    /**
     * Return: $count of the loan's units came back Good and are Available
     * again: never the ones in $keepOut (the units found Damaged, Incomplete
     * or Lost, which are settled after this).
     */
    public static function putBack(BorrowTransaction $transaction, Equipment $equipment, int $count, array $keepOut = []): void
    {
        $units = self::stillOut($transaction, $equipment, $count, [EquipmentUnit::STATUS_ON_LOAN, EquipmentUnit::STATUS_MAINTENANCE], $keepOut);
        self::settle($transaction, $units, EquipmentUnit::STATUS_AVAILABLE, 'Good');
        self::syncCounts($equipment);
    }

    /**
     * Return: the loan's units still out came back Damaged or Incomplete; they
     * stay out of stock, Under Maintenance. (Loans without linked units keep
     * the old behaviour: nothing changes.)
     */
    public static function holdForRepair(BorrowTransaction $transaction, Equipment $equipment, string $condition): void
    {
        $units = self::linkedOnLoan($transaction);
        self::settle($transaction, $units, EquipmentUnit::STATUS_MAINTENANCE, $condition);
        self::syncCounts($equipment);
    }

    /** Return: $count of the loan's units were lost and no longer count. */
    public static function markLost(BorrowTransaction $transaction, Equipment $equipment, int $count): void
    {
        $units = self::stillOut($transaction, $equipment, $count, [
            EquipmentUnit::STATUS_ON_LOAN,
            EquipmentUnit::STATUS_MAINTENANCE,
            EquipmentUnit::STATUS_AVAILABLE,
        ]);
        self::settle($transaction, $units, EquipmentUnit::STATUS_LOST, 'Lost');
        self::syncCounts($equipment);
    }

    /**
     * A lost unit was replaced: new Available units (new codes) join the
     * item, with the given serial numbers in order (the rest have none).
     */
    public static function addReplacements(Equipment $equipment, int $count, array $serials = []): void
    {
        for ($i = 0; $i < $count; $i++) {
            self::createOne($equipment, $serials[$i] ?? null);
        }
        self::syncCounts($equipment);
    }

    private static function units(Equipment $equipment)
    {
        return EquipmentUnit::where('equipment_id', $equipment->equipment_id)->lockForUpdate();
    }

    /** The loan's own units still On Loan. */
    private static function linkedOnLoan(BorrowTransaction $transaction): Collection
    {
        return $transaction->units()
            ->where('status', EquipmentUnit::STATUS_ON_LOAN)
            ->lockForUpdate()
            ->get()
            ->sortBy(fn ($u) => EquipmentUnit::numberOf($u->unit_code))
            ->values();
    }

    /**
     * Up to $count units of this loan that are still out: its own On Loan
     * units first; for a loan without (enough) linked units, units with the
     * given statuses that no other open loan holds, newest first.
     */
    private static function stillOut(BorrowTransaction $transaction, Equipment $equipment, int $count, array $fallbackStatuses, array $keepOut = []): Collection
    {
        $count = max(0, $count);
        $units = self::linkedOnLoan($transaction)
            ->reject(fn ($u) => in_array((int) $u->unit_id, array_map('intval', $keepOut), true))
            ->take($count);

        if ($units->count() < $count && !$transaction->units()->exists()) {
            $heldByOpenLoans = DB::table('borrow_transaction_unit as l')
                ->join('borrow_transaction as t', 't.transaction_id', '=', 'l.transaction_id')
                ->whereIn('t.status', BorrowTransaction::ON_LOAN)
                ->pluck('l.unit_id');

            $spare = self::units($equipment)
                ->whereIn('status', $fallbackStatuses)
                ->whereNotIn('unit_id', $heldByOpenLoans)
                ->get()
                ->sortBy([
                    fn ($a, $b) => array_search($a->status, $fallbackStatuses, true) <=> array_search($b->status, $fallbackStatuses, true),
                    fn ($a, $b) => EquipmentUnit::numberOf($b->unit_code) <=> EquipmentUnit::numberOf($a->unit_code),
                ])
                ->take($count - $units->count());

            $units = $units->concat($spare);
        }

        return $units;
    }

    /** Gives the units their new status and records how each came back on the loan. */
    private static function settle(BorrowTransaction $transaction, Collection $units, string $status, string $condition): void
    {
        foreach ($units as $unit) {
            $unit->update(['status' => $status]);
            $transaction->units()->syncWithoutDetaching([$unit->unit_id => ['return_condition' => $condition]]);
        }
    }

    /**
     * Units to remove first when the typed total goes down: units set aside
     * by the conversion (the unaccounted ones), then Available, then other
     * Under Maintenance units.
     */
    private static function removeOrder(EquipmentUnit $unit): int
    {
        return match (true) {
            $unit->status === EquipmentUnit::STATUS_MAINTENANCE && $unit->remarks === EquipmentUnit::CHECK_REMARK => 0,
            $unit->status === EquipmentUnit::STATUS_AVAILABLE => 1,
            default => 2,
        };
    }
}
