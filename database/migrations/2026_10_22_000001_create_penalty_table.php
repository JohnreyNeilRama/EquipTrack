<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Damage & Penalty Recording.
 *
 * penalty
 *   One row per penalty on a returned loan (borrow_transaction). A loan can
 *   carry more than one, e.g. Late Return and Damaged, but only one of each
 *   type. The system only records penalties: payment happens outside the
 *   system, and staff mark the penalty Settled (with an optional receipt /
 *   reference number) or Waived (with a reason).
 *
 *   type         Late Return / Damaged / Incomplete / Lost
 *   amount       optional: empty for non-monetary sanctions, which the
 *                description spells out (replacement, suspension, ...)
 *   status       Pending / Settled / Waived
 *   recorded_by_* / settled_by_*   the admin or department account that acted
 *
 * borrow_transaction.inspected_condition
 *   Gains "Lost", so the department can record what users already report.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('penalty')) {
            Schema::create('penalty', function (Blueprint $table) {
                $table->increments('penalty_id');
                $table->unsignedInteger('transaction_id');
                $table->unsignedInteger('user_id');
                $table->enum('type', ['Late Return', 'Damaged', 'Incomplete', 'Lost']);
                $table->decimal('amount', 10, 2)->nullable();
                $table->text('description');
                $table->enum('status', ['Pending', 'Settled', 'Waived'])->default('Pending');
                $table->unsignedInteger('recorded_by_admin_id')->nullable();
                $table->unsignedInteger('recorded_by_dept_acc_id')->nullable();
                $table->dateTime('recorded_at');
                $table->unsignedInteger('settled_by_admin_id')->nullable();
                $table->unsignedInteger('settled_by_dept_acc_id')->nullable();
                $table->dateTime('settled_at')->nullable();
                $table->string('reference_no', 100)->nullable();
                $table->text('settle_remarks')->nullable();

                $table->unique(['transaction_id', 'type'], 'uq_penalty_transaction_type');
                $table->index(['user_id', 'status'], 'idx_penalty_user_status');

                $table->foreign('transaction_id', 'fk_penalty_transaction')
                    ->references('transaction_id')->on('borrow_transaction')
                    ->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('user_id', 'fk_penalty_user')
                    ->references('user_id')->on('user_account')
                    ->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('recorded_by_admin_id', 'fk_penalty_recorded_admin')
                    ->references('admin_id')->on('admin')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('recorded_by_dept_acc_id', 'fk_penalty_recorded_dept')
                    ->references('dept_acc_id')->on('department_account')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('settled_by_admin_id', 'fk_penalty_settled_admin')
                    ->references('admin_id')->on('admin')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('settled_by_dept_acc_id', 'fk_penalty_settled_dept')
                    ->references('dept_acc_id')->on('department_account')->nullOnDelete()->cascadeOnUpdate();
            });
        }

        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->enum('inspected_condition', ['Good', 'Damaged', 'Incomplete', 'Lost'])->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty');

        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->enum('inspected_condition', ['Good', 'Damaged', 'Incomplete'])->nullable()->change();
        });
    }
};
