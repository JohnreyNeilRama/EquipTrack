<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Return Confirmation.
 *
 * A user's return no longer settles the loan by itself. It puts the
 * borrow_transaction into return_status "Pending Confirmation"; the loan stays
 * Active / Overdue (still out, stock untouched) until the department that owns
 * the equipment physically checks it and either:
 *
 *  - confirms it: status becomes Returned, the inspected condition and remarks
 *    are recorded, and stock is restored when the unit is in Good condition; or
 *  - rejects it: return_status "Rejected" with a reason; the loan stays open and
 *    the user can submit the return again.
 *
 * Columns
 *   return_status          NULL (no return submitted) / Pending Confirmation / Confirmed / Rejected
 *   return_submitted_at    when the user submitted the return
 *   reported_condition     what the user reported: "Good — remarks" (Good / Damaged / Lost)
 *   inspected_condition    what the department found: Good / Damaged / Incomplete
 *   inspection_remarks     the department's notes on damage, missing parts, etc.
 *   return_reviewed_by     department account that confirmed / rejected it
 *   return_reviewed_at     when it was confirmed / rejected
 *   return_reject_reason   why the return was rejected
 *   return_restores_stock  false for legacy loans approved before stock was
 *                          tracked, whose return must not inflate the stock
 *
 * Transactions that were already Returned are marked Confirmed, so the history
 * stays consistent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('borrow_transaction', 'return_status')) {
            return;
        }

        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->enum('return_status', ['Pending Confirmation', 'Confirmed', 'Rejected'])->nullable()->after('status');
            $table->dateTime('return_submitted_at')->nullable()->after('return_status');
            $table->string('reported_condition', 150)->nullable()->after('return_submitted_at');
            $table->enum('inspected_condition', ['Good', 'Damaged', 'Incomplete'])->nullable()->after('reported_condition');
            $table->text('inspection_remarks')->nullable()->after('inspected_condition');
            $table->unsignedInteger('return_reviewed_by')->nullable()->after('inspection_remarks');
            $table->dateTime('return_reviewed_at')->nullable()->after('return_reviewed_by');
            $table->text('return_reject_reason')->nullable()->after('return_reviewed_at');
            $table->boolean('return_restores_stock')->default(true)->after('return_reject_reason');

            $table->foreign('return_reviewed_by', 'fk_borrow_transaction_return_reviewer')
                ->references('dept_acc_id')->on('department_account')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        DB::table('borrow_transaction')
            ->where('status', 'Returned')
            ->update(['return_status' => 'Confirmed']);
    }

    public function down(): void
    {
        if (!Schema::hasColumn('borrow_transaction', 'return_status')) {
            return;
        }

        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->dropForeign('fk_borrow_transaction_return_reviewer');
            $table->dropColumn([
                'return_status',
                'return_submitted_at',
                'reported_condition',
                'inspected_condition',
                'inspection_remarks',
                'return_reviewed_by',
                'return_reviewed_at',
                'return_reject_reason',
                'return_restores_stock',
            ]);
        });
    }
};
