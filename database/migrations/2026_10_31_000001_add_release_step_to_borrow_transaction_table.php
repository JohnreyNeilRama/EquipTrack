<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Release step: an approved loan waits as Ready for Pickup (the unit is held)
// until Lab Personnel hand the item over (released_at / released_by) and it
// becomes Active, or mark it Not Claimed (unclaimed_at) when the borrower never
// came, which gives the unit back.
return new class extends Migration
{
    public function up(): void
    {
        // ->change() works on MySQL and on SQLite (the test database).
        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->enum('status', ['Ready for Pickup', 'Active', 'Overdue', 'Returned', 'Not Claimed'])
                ->default('Ready for Pickup')
                ->change();
        });

        Schema::table('borrow_transaction', function (Blueprint $table) {
            if (!Schema::hasColumn('borrow_transaction', 'released_at')) {
                $table->dateTime('released_at')->nullable()->after('status');
            }
            if (!Schema::hasColumn('borrow_transaction', 'released_by')) {
                $table->unsignedInteger('released_by')->nullable()->after('released_at');
                $table->foreign('released_by', 'fk_borrow_transaction_released_by')
                    ->references('dept_acc_id')->on('department_account')
                    ->nullOnDelete()->cascadeOnUpdate();
            }
            if (!Schema::hasColumn('borrow_transaction', 'unclaimed_at')) {
                $table->dateTime('unclaimed_at')->nullable()->after('released_by');
            }
        });

        // Loans that already existed were handed over before this step existed:
        // treat their borrow date as the release date (who released them is unknown).
        DB::table('borrow_transaction')
            ->whereIn('status', ['Active', 'Overdue', 'Returned'])
            ->whereNull('released_at')
            ->update(['released_at' => DB::raw('borrow_date')]);
    }

    public function down(): void
    {
        // The old status list has no place for these: a held unit goes back to
        // being a normal loan, an unclaimed one is closed as Returned.
        DB::table('borrow_transaction')->where('status', 'Ready for Pickup')->update(['status' => 'Active']);
        DB::table('borrow_transaction')->where('status', 'Not Claimed')->update(['status' => 'Returned']);

        Schema::table('borrow_transaction', function (Blueprint $table) {
            if (Schema::hasColumn('borrow_transaction', 'released_by')) {
                $table->dropForeign('fk_borrow_transaction_released_by');
            }
        });

        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['released_at', 'released_by', 'unclaimed_at'],
                fn ($column) => Schema::hasColumn('borrow_transaction', $column),
            )));
        });

        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->enum('status', ['Active', 'Returned', 'Overdue'])->default('Active')->change();
        });
    }
};
