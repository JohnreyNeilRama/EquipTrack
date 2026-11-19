<?php

namespace App\Support;

use App\Models\EquipmentUnit;
use Illuminate\Support\Facades\DB;

/**
 * Gives existing equipment its units, once, when unit tracking is added
 * (migration 2026_11_18_000001). Works on the tables directly, so it does not
 * depend on how the models behave later.
 *
 * For an item with total quantity N it creates N units, EQ-{id}-001 to
 * EQ-{id}-00N. Unit 1 keeps the item's serial number; the others have only
 * their code (Expensive items show "units need serial numbers" until they are
 * filled in). Then:
 *
 *  1. Units released and not yet returned are On Loan, linked to their loans
 *     (only loans that took units out of stock: return_restores_stock = 1).
 *  2. As many as the item's available_qty are Available.
 *  3. Any left over (e.g. units returned damaged and kept out of stock) are
 *     Under Maintenance with a "check this unit" remark, so nothing is lent
 *     by mistake.
 *
 * The item's available_qty and total_qty are not changed.
 */
class EquipmentUnitConversion
{
    /** Returns how many units were created and how many were set aside to be checked. */
    public static function run(): array
    {
        $created = 0;
        $toCheck = 0;
        $now = now();

        $equipmentIds = DB::table('equipment')->orderBy('equipment_id')->pluck('equipment_id');

        foreach ($equipmentIds as $equipmentId) {
            // Only items without units (so running it twice changes nothing).
            if (DB::table('equipment_unit')->where('equipment_id', $equipmentId)->exists()) {
                continue;
            }

            $item = DB::table('equipment')->where('equipment_id', $equipmentId)->first();
            $total = max(0, (int) $item->total_qty);
            if ($total === 0) {
                continue;
            }
            $available = max(0, (int) $item->available_qty);

            $loans = DB::table('borrow_transaction as t')
                ->join('borrow_request as r', 'r.request_id', '=', 't.request_id')
                ->where('r.equipment_id', $equipmentId)
                ->whereIn('t.status', ['Active', 'Overdue'])
                ->where('t.return_restores_stock', 1)
                ->orderBy('t.transaction_id')
                ->get(['t.transaction_id', 'r.quantity']);

            $serial = trim((string) ($item->serial_number ?? ''));
            $number = 0;
            $newUnit = function (string $status, ?string $remarks = null) use (&$number, $equipmentId, $serial, $now): int {
                $number++;

                return (int) DB::table('equipment_unit')->insertGetId([
                    'equipment_id' => $equipmentId,
                    'unit_code' => EquipmentUnit::codeFor($equipmentId, $number),
                    'serial_number' => $number === 1 && $serial !== '' ? $serial : null,
                    'status' => $status,
                    'remarks' => $remarks,
                    'date_added' => $now,
                ], 'unit_id');
            };

            foreach ($loans as $loan) {
                for ($i = 0; $i < max(1, (int) $loan->quantity) && $number < $total; $i++) {
                    DB::table('borrow_transaction_unit')->insert([
                        'transaction_id' => $loan->transaction_id,
                        'unit_id' => $newUnit(EquipmentUnit::STATUS_ON_LOAN),
                    ]);
                }
            }

            while ($number < $total && $available > 0) {
                $newUnit(EquipmentUnit::STATUS_AVAILABLE);
                $available--;
            }

            while ($number < $total) {
                $newUnit(EquipmentUnit::STATUS_MAINTENANCE, EquipmentUnit::CHECK_REMARK);
                $toCheck++;
            }

            $created += $number;
        }

        return ['created' => $created, 'to_check' => $toCheck];
    }
}
