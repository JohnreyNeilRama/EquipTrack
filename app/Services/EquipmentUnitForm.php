<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\EquipmentUnit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The Units section of the Add / Edit Equipment window (Admin and Lab
 * Personnel). The window sends every unit of the item:
 *
 *   units[i][unit_id]        existing unit (empty for a new one)
 *   units[i][serial_number]  its serial number (may be empty)
 *   units[i][status]         Available, Under Maintenance or Retired
 *   remove_units[]           new-looking mistakes: units never lent, to delete
 *
 * Rules:
 *  - Expensive equipment: every unit in use (not Lost or Retired) needs its
 *    own serial number. Not Expensive: serial numbers are optional; a unit
 *    without one is known by its automatic code (EQ-12-003).
 *  - A serial number is unique across all units of all equipment (any case)
 *    and can't look like an automatic code.
 *  - Units On Loan or Lost keep their status (release and return change it);
 *    their serial number can still be corrected.
 *  - A unit can be removed only if it was never lent; otherwise it is retired,
 *    so its borrowing history stays.
 *
 * The item's quantities are then counted from its units.
 */
class EquipmentUnitForm
{
    /** Statuses the window may set; On Loan and Lost only change through loans. */
    public const EDITABLE = [EquipmentUnit::STATUS_AVAILABLE, EquipmentUnit::STATUS_MAINTENANCE, EquipmentUnit::STATUS_RETIRED];

    /** Most units one save may hold. */
    public const MAX_UNITS = 500;

    /** Validation rules for the units fields (added to each controller's own rules). */
    public static function rules(): array
    {
        return [
            'units' => ['array', 'max:' . self::MAX_UNITS],
            'units.*' => ['array'],
            'units.*.unit_id' => ['nullable', 'integer', 'min:1'],
            'units.*.serial_number' => ['nullable', 'string', 'max:50'],
            'units.*.status' => ['nullable', 'string', 'in:' . implode(',', self::EDITABLE)],
            'remove_units' => ['array', 'max:' . self::MAX_UNITS],
            'remove_units.*' => ['integer', 'min:1'],
        ];
    }

    public static function messages(): array
    {
        return [
            'units.max' => 'One equipment item can hold at most ' . self::MAX_UNITS . ' units per save.',
            'units.*.serial_number.max' => 'A serial number can be at most 50 characters.',
            'units.*.status.in' => 'A unit\'s status must be Available, Under Maintenance or Retired.',
        ];
    }

    /**
     * Checks the submitted units against the rules. Returns the plan to save,
     * or a message saying what to fix.
     *
     * @return array{rows: array<int, array{unit: ?EquipmentUnit, serial: ?string, status: string}>, remove: Collection}|string
     */
    public static function check(array $data, ?Equipment $equipment, string $classification): array|string
    {
        $existing = $equipment
            ? $equipment->units()->withExists('transactions as was_lent')->get()->keyBy('unit_id')
            : collect();

        $rows = [];
        $seenIds = [];
        $seenSerials = [];
        foreach (array_values($data['units'] ?? []) as $i => $row) {
            $id = isset($row['unit_id']) && $row['unit_id'] !== '' ? (int) $row['unit_id'] : null;
            $unit = $id !== null ? $existing->get($id) : null;
            if ($id !== null && (!$unit || isset($seenIds[$id]))) {
                return 'A unit in the list no longer belongs to this equipment. Close the window and open it again.';
            }
            $seenIds[$id ?? 'new-' . $i] = true;
            $name = $unit ? $unit->unit_code : 'New unit ' . (count(array_filter($rows, fn ($r) => !$r['unit'])) + 1);

            $serial = trim((string) ($row['serial_number'] ?? ''));
            $serial = $serial !== '' ? $serial : null;
            if ($serial !== null) {
                // (A serial number the unit already had, e.g. from before unit tracking, is kept as it is.)
                if (preg_match('/^EQ-\d+-\d+$/i', $serial) && $serial !== $unit?->serial_number) {
                    return $name . ': "' . $serial . '" looks like an automatic unit code. Leave the serial number empty if the unit has none.';
                }
                $key = mb_strtolower($serial);
                if (isset($seenSerials[$key])) {
                    return 'Serial number "' . $serial . '" is entered twice (' . $seenSerials[$key] . ' and ' . $name . '). Each unit needs its own.';
                }
                $seenSerials[$key] = $name;
            }

            // On Loan and Lost units keep their status; new units start Available
            // (or Under Maintenance) and are never added as Retired.
            $status = (string) ($row['status'] ?? '') ?: EquipmentUnit::STATUS_AVAILABLE;
            if ($unit && !in_array($unit->status, self::EDITABLE, true)) {
                $status = $unit->status;
            } elseif (!$unit && $status === EquipmentUnit::STATUS_RETIRED) {
                return $name . ': a new unit can\'t be added as Retired.';
            }

            $rows[] = ['unit' => $unit, 'name' => $name, 'serial' => $serial, 'status' => $status];
        }

        $remove = collect();
        foreach (array_unique(array_map('intval', $data['remove_units'] ?? [])) as $id) {
            $unit = $existing->get($id);
            if (!$unit) {
                return 'A unit to remove no longer belongs to this equipment. Close the window and open it again.';
            }
            if ($unit->was_lent || $unit->status === EquipmentUnit::STATUS_ON_LOAN) {
                return $unit->unit_code . ' was borrowed before, so it can\'t be removed (its history must be kept). Set it to Retired instead.';
            }
            if (isset($seenIds[$id])) {
                return $unit->unit_code . ' is both kept and removed. Close the window and open it again.';
            }
            $remove->push($unit);
        }

        // Every existing unit must be either kept (listed) or removed.
        $missing = $existing->keys()->diff(array_keys($seenIds))->diff($remove->pluck('unit_id'));
        if ($missing->isNotEmpty()) {
            return 'The list of units is out of date. Close the window and open it again.';
        }

        if (!$equipment && count($rows) === 0) {
            return 'Add at least one unit.';
        }

        if ($classification === Equipment::CLASS_EXPENSIVE) {
            $noSerial = array_values(array_map(fn ($r) => $r['name'], array_filter(
                $rows,
                fn ($r) => $r['serial'] === null && !in_array($r['status'], EquipmentUnit::GONE, true),
            )));
            if ($noSerial) {
                return 'Expensive equipment needs a serial number for every unit. Missing: ' . implode(', ', array_slice($noSerial, 0, 10))
                    . (count($noSerial) > 10 ? ' and ' . (count($noSerial) - 10) . ' more' : '') . '.';
            }
        }

        // Serial numbers already used by another unit (of any equipment).
        if ($seenSerials) {
            $keptIds = array_filter(array_map(fn ($r) => $r['unit']?->unit_id, $rows));
            $taken = EquipmentUnit::with('equipment:equipment_id,name')
                ->whereNotNull('serial_number')
                ->whereIn(DB::raw('LOWER(serial_number)'), array_keys($seenSerials))
                ->when($keptIds, fn ($q) => $q->whereNotIn('unit_id', $keptIds))
                ->whereNotIn('unit_id', $remove->pluck('unit_id')->all() ?: [0])
                ->first();
            if ($taken) {
                return 'Serial number "' . $taken->serial_number . '" is already used by ' . $taken->unit_code
                    . ($taken->equipment ? ' (' . $taken->equipment->name . ')' : '') . '.';
            }
        }

        return ['rows' => $rows, 'remove' => $remove];
    }

    /**
     * Saves a checked plan: removes, updates and adds units, then counts the
     * item's quantities. Call inside a DB transaction. Returns a short summary
     * of what changed, for the audit trail (null when nothing did).
     */
    public static function apply(Equipment $equipment, array $plan): ?string
    {
        Equipment::whereKey($equipment->equipment_id)->lockForUpdate()->first();

        $added = 0;
        $serials = 0;
        $statuses = 0;

        // Removed units free their serial numbers now, but are deleted only
        // after the new units are numbered, so a new unit never takes the
        // code of one removed in the same save.
        foreach ($plan['remove'] as $unit) {
            if ($unit->serial_number !== null) {
                $unit->update(['serial_number' => null]);
            }
        }

        // Existing units first, so a serial number moved from one unit to
        // another is freed before it is taken again.
        foreach ($plan['rows'] as $row) {
            if (!$row['unit']) {
                continue;
            }
            $unit = $row['unit'];
            $changes = [];
            if ($unit->serial_number !== $row['serial']) {
                $changes['serial_number'] = null;
                $serials++;
            }
            if ($unit->status !== $row['status']) {
                $changes['status'] = $row['status'];
                // A unit back in use no longer needs its old note (e.g. "check this unit").
                if ($row['status'] === EquipmentUnit::STATUS_AVAILABLE) {
                    $changes['remarks'] = null;
                }
                $statuses++;
            }
            if ($changes) {
                $unit->update($changes);
            }
        }
        foreach ($plan['rows'] as $row) {
            if ($row['unit'] && $row['unit']->serial_number !== $row['serial']) {
                $row['unit']->update(['serial_number' => $row['serial']]);
            }
        }

        foreach ($plan['rows'] as $row) {
            if (!$row['unit']) {
                EquipmentUnits::createOne($equipment, $row['serial'], $row['status']);
                $added++;
            }
        }

        foreach ($plan['remove'] as $unit) {
            $unit->delete();
        }

        EquipmentUnits::syncCounts($equipment);

        $summary = array_filter([
            $added ? $added . ' added' : null,
            $plan['remove']->count() ? $plan['remove']->count() . ' removed' : null,
            $serials ? $serials . ' serial ' . ($serials === 1 ? 'number' : 'numbers') . ' changed' : null,
            $statuses ? $statuses . ' status ' . ($statuses === 1 ? 'change' : 'changes') : null,
        ]);

        return $summary ? 'Units: ' . implode(', ', $summary) . '.' : null;
    }

    /** Whether another unit (not of $exceptEquipmentId) already uses this serial number. */
    public static function serialTaken(string $serial, ?int $exceptEquipmentId = null): bool
    {
        return EquipmentUnit::whereRaw('LOWER(serial_number) = ?', [mb_strtolower(trim($serial))])
            ->when($exceptEquipmentId, fn ($q) => $q->where('equipment_id', '!=', $exceptEquipmentId))
            ->exists();
    }
}
