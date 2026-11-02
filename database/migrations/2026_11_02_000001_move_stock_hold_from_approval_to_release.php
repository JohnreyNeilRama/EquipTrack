<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Date-based reservations (data only, no schema change). Approval now reserves
// the requested dates instead of taking a unit out of stock; the unit leaves
// stock when Lab Personnel release it. The equipment status is left to the
// admin instead of switching to Unavailable whenever stock runs out.
return new class extends Migration
{
    public function up(): void
    {
        // 1. Items the system marked Unavailable only because their last unit
        //    went out: dates decide availability now, so they are Available.
        //    (An admin who wants an item unavailable sets it again.)
        DB::table('equipment')
            ->where('status', 'Unavailable')
            ->where('available_qty', 0)
            ->update(['status' => 'Available']);

        // 2. Loans still waiting for pickup had a unit taken out of stock at
        //    approval under the old rule; give it back (never above the total).
        foreach ($this->heldUnits() as $equipmentId => $units) {
            DB::table('equipment')
                ->where('equipment_id', $equipmentId)
                // CASE instead of LEAST(): works on MySQL and SQLite (tests).
                ->update(['available_qty' => DB::raw('CASE WHEN available_qty + ' . (int) $units . ' > total_qty THEN total_qty ELSE available_qty + ' . (int) $units . ' END')]);
        }
    }

    public function down(): void
    {
        // Take the held units out of stock again (the status change in step 1
        // can't be told apart from an admin's choice, so it is kept).
        foreach ($this->heldUnits() as $equipmentId => $units) {
            DB::table('equipment')
                ->where('equipment_id', $equipmentId)
                ->update(['available_qty' => DB::raw('CASE WHEN available_qty > ' . (int) $units . ' THEN available_qty - ' . (int) $units . ' ELSE 0 END')]);
        }
    }

    /** Units of each item held by loans that are Ready for Pickup. */
    private function heldUnits(): array
    {
        return DB::table('borrow_transaction')
            ->join('borrow_request', 'borrow_request.request_id', '=', 'borrow_transaction.request_id')
            ->where('borrow_transaction.status', 'Ready for Pickup')
            ->where('borrow_transaction.return_restores_stock', 1)
            ->groupBy('borrow_request.equipment_id')
            ->selectRaw('borrow_request.equipment_id, SUM(CASE WHEN borrow_request.quantity > 0 THEN borrow_request.quantity ELSE 1 END) AS units')
            ->pluck('units', 'equipment_id')
            ->all();
    }
};
