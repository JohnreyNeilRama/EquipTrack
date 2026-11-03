<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Loans can cover several units. When a return is confirmed as Damaged,
// Incomplete or Lost, affected_qty records how many of the units are in that
// condition; the rest came back Good and go back to stock. NULL (Good returns,
// and older records) means every unit of the loan had the inspected condition.
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('borrow_transaction', 'affected_qty')) {
            Schema::table('borrow_transaction', function (Blueprint $table) {
                $table->unsignedInteger('affected_qty')->nullable()->after('inspected_condition');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('borrow_transaction', 'affected_qty')) {
            Schema::table('borrow_transaction', function (Blueprint $table) {
                $table->dropColumn('affected_qty');
            });
        }
    }
};
