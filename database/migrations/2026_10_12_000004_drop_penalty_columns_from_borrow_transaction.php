<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the unused penalty tracking from borrow_transaction:
 * penalty_amount and penalty_status. Every other column and all existing rows
 * are kept. down() restores both columns with their original types and
 * defaults (the previous values themselves are not recoverable).
 */
return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter(
            ['penalty_amount', 'penalty_status'],
            fn (string $column) => Schema::hasColumn('borrow_transaction', $column)
        ));

        if ($columns) {
            Schema::table('borrow_transaction', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }

    public function down(): void
    {
        Schema::table('borrow_transaction', function (Blueprint $table) {
            if (!Schema::hasColumn('borrow_transaction', 'penalty_amount')) {
                $table->decimal('penalty_amount', 8, 2)->default(0.00);
            }

            if (!Schema::hasColumn('borrow_transaction', 'penalty_status')) {
                $table->enum('penalty_status', ['None', 'Unpaid', 'Paid'])->default('None');
            }
        });
    }
};
