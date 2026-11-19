<?php

namespace App\Console\Commands;

use App\Models\Equipment;
use App\Models\EquipmentUnit;
use Illuminate\Console\Command;

/**
 * Lists the equipment units that need someone's attention, mainly after the
 * switch to unit tracking. Read-only; it changes nothing.
 *
 *     php artisan equiptrack:check-units
 *
 *  - Units set aside by the conversion (Under Maintenance, "check this unit"):
 *    the old quantities didn't account for them, e.g. a unit returned damaged.
 *    Check each one in the lab, then make it Available or retire it.
 *  - Units of Expensive equipment without a serial number: every Expensive
 *    unit needs its own.
 *  - Items whose available / total quantities don't match their units.
 */
class CheckEquipmentUnits extends Command
{
    protected $signature = 'equiptrack:check-units';

    protected $description = 'List equipment units to check after the switch to unit tracking (read-only)';

    public function handle(): int
    {
        $items = Equipment::with('units')->orderBy('name')->get();
        $this->line('Equipment items: ' . $items->count() . ', units: ' . $items->sum(fn ($e) => $e->units->count()));

        $toCheck = [];
        $noSerial = [];
        $mismatch = [];

        foreach ($items as $item) {
            foreach ($item->units as $unit) {
                if ($unit->status === EquipmentUnit::STATUS_MAINTENANCE && $unit->remarks === EquipmentUnit::CHECK_REMARK) {
                    $toCheck[] = [$unit->unit_code, $item->name, $unit->serial_number ?: '—'];
                }
                if ($item->isExpensive() && !in_array($unit->status, EquipmentUnit::GONE, true) && trim((string) $unit->serial_number) === '') {
                    $noSerial[] = [$unit->unit_code, $item->name, $unit->status];
                }
            }

            $total = $item->units->whereNotIn('status', EquipmentUnit::GONE)->count();
            $available = $item->units->where('status', EquipmentUnit::STATUS_AVAILABLE)->count();
            if ($total !== (int) $item->total_qty || $available !== (int) $item->available_qty) {
                $mismatch[] = [$item->equipment_id, $item->name, $item->available_qty . ' / ' . $item->total_qty, $available . ' / ' . $total];
            }
        }

        $this->newLine();
        $this->info('Units to check in the lab (' . count($toCheck) . ')');
        $toCheck
            ? $this->table(['Unit', 'Equipment', 'Serial No.'], $toCheck)
            : $this->line('  None.');

        $this->newLine();
        $this->info('Expensive units without a serial number (' . count($noSerial) . ')');
        $noSerial
            ? $this->table(['Unit', 'Equipment', 'Status'], $noSerial)
            : $this->line('  None.');

        $this->newLine();
        $this->info('Quantities that differ from the units (' . count($mismatch) . ')');
        $mismatch
            ? $this->table(['ID', 'Equipment', 'Saved (available / total)', 'Units (available / total)'], $mismatch)
            : $this->line('  None.');

        return self::SUCCESS;
    }
}
