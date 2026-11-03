<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Lab Personnel can cancel a reservation (a Ready for Pickup loan) with a
// reason, e.g. the borrower no longer needs it or the equipment broke. It is
// kept apart from Not Claimed, which now means the borrower really didn't
// come on or after the pickup date: status Cancelled, with when
// (cancelled_at), who (cancelled_by) and why (cancel_reason).
return new class extends Migration
{
    public function up(): void
    {
        // ->change() works on MySQL and on SQLite (the test database).
        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->enum('status', ['Ready for Pickup', 'Active', 'Overdue', 'Returned', 'Not Claimed', 'Cancelled'])
                ->default('Ready for Pickup')
                ->change();
        });

        Schema::table('borrow_transaction', function (Blueprint $table) {
            if (!Schema::hasColumn('borrow_transaction', 'cancelled_at')) {
                $table->dateTime('cancelled_at')->nullable()->after('unclaimed_at');
            }
            if (!Schema::hasColumn('borrow_transaction', 'cancelled_by')) {
                $table->unsignedInteger('cancelled_by')->nullable()->after('cancelled_at');
                $table->foreign('cancelled_by', 'fk_borrow_transaction_cancelled_by')
                    ->references('dept_acc_id')->on('department_account')
                    ->nullOnDelete()->cascadeOnUpdate();
            }
            if (!Schema::hasColumn('borrow_transaction', 'cancel_reason')) {
                $table->string('cancel_reason', 255)->nullable()->after('cancelled_by');
            }
        });
    }

    public function down(): void
    {
        // The old status list has no place for Cancelled: close them as Not Claimed.
        DB::table('borrow_transaction')->where('status', 'Cancelled')->update([
            'status' => 'Not Claimed',
            'unclaimed_at' => DB::raw('cancelled_at'),
        ]);

        Schema::table('borrow_transaction', function (Blueprint $table) {
            if (Schema::hasColumn('borrow_transaction', 'cancelled_by')) {
                $table->dropForeign('fk_borrow_transaction_cancelled_by');
            }
        });

        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->dropColumn(array_values(array_filter(
                ['cancelled_at', 'cancelled_by', 'cancel_reason'],
                fn ($column) => Schema::hasColumn('borrow_transaction', $column),
            )));
        });

        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->enum('status', ['Ready for Pickup', 'Active', 'Overdue', 'Returned', 'Not Claimed'])
                ->default('Ready for Pickup')
                ->change();
        });
    }
};
