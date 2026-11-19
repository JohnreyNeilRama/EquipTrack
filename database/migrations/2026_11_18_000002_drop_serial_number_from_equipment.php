<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Serial numbers now belong to units (equipment_unit.serial_number, given
// to the first unit by 2026_11_18_000001), so the item's own single serial
// number column goes. Undoing it puts back each item's first unit's serial
// number, else its code.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->dropUnique('uq_equipment_serial_number');
        });

        Schema::table('equipment', function (Blueprint $table) {
            $table->dropColumn('serial_number');
        });
    }

    public function down(): void
    {
        Schema::table('equipment', function (Blueprint $table) {
            $table->string('serial_number', 50)->nullable()->after('model');
        });

        foreach (DB::table('equipment')->pluck('equipment_id') as $id) {
            $unit = DB::table('equipment_unit')->where('equipment_id', $id)->orderBy('unit_id')->first();
            DB::table('equipment')->where('equipment_id', $id)->update([
                'serial_number' => $unit ? ($unit->serial_number ?: $unit->unit_code) : 'EQ-' . $id,
            ]);
        }

        Schema::table('equipment', function (Blueprint $table) {
            $table->unique('serial_number', 'uq_equipment_serial_number');
        });
    }
};
