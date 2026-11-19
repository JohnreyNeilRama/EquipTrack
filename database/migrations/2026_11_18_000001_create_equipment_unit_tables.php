<?php

use App\Support\EquipmentUnitConversion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Unit tracking: every physical unit of an equipment item gets its own row,
// with an automatic code (EQ-12-003), its own serial number when it has one
// (required for Expensive equipment) and its own status. A loan records which
// units were handed over and in what condition each came back.
//
// Existing equipment is converted once (App\Support\EquipmentUnitConversion).
// equipment.available_qty / total_qty stay, counted from the units from now on.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipment_unit', function (Blueprint $table) {
            $table->increments('unit_id');
            $table->unsignedInteger('equipment_id');
            $table->string('unit_code', 20)->unique('uq_equipment_unit_code');
            $table->string('serial_number', 50)->nullable()->unique('uq_equipment_unit_serial');
            $table->enum('status', ['Available', 'On Loan', 'Under Maintenance', 'Lost', 'Retired'])->default('Available');
            $table->string('remarks', 255)->nullable();
            $table->dateTime('date_added')->useCurrent();

            $table->index(['equipment_id', 'status'], 'idx_equipment_unit_status');
            // Units go with their equipment item, which can only be deleted
            // while it has no borrowing history.
            $table->foreign('equipment_id', 'fk_equipment_unit_equipment')
                ->references('equipment_id')->on('equipment')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('borrow_transaction_unit', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('transaction_id');
            $table->unsignedInteger('unit_id');
            $table->enum('return_condition', ['Good', 'Damaged', 'Incomplete', 'Lost'])->nullable();

            $table->unique(['transaction_id', 'unit_id'], 'uq_borrow_transaction_unit');
            $table->index('unit_id', 'idx_borrow_transaction_unit_unit');
            $table->foreign('transaction_id', 'fk_borrow_transaction_unit_transaction')
                ->references('transaction_id')->on('borrow_transaction')
                ->cascadeOnDelete()->cascadeOnUpdate();
            // A unit that was ever lent keeps its history: it is retired, not deleted.
            $table->foreign('unit_id', 'fk_borrow_transaction_unit_unit')
                ->references('unit_id')->on('equipment_unit')
                ->restrictOnDelete()->cascadeOnUpdate();
        });

        EquipmentUnitConversion::run();
    }

    public function down(): void
    {
        Schema::dropIfExists('borrow_transaction_unit');
        Schema::dropIfExists('equipment_unit');
    }
};
