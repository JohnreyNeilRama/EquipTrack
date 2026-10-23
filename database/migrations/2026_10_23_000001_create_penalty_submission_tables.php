<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Proof of penalty settlement.
 *
 * penalty_submission
 *   A student / faculty member's proof that a Pending penalty was settled
 *   outside the system: paid, the item replaced, or the item repaired. Staff
 *   (the equipment's department or the admin) approve it, which settles the
 *   penalty, or reject it with a reason, after which the user can submit
 *   again. Every attempt is kept.
 *
 *   type            Payment / Replacement / Repair
 *   status          Under Review / Approved / Rejected
 *   replacement_received   staff confirmed the replacement / repaired item was
 *                          received and inspected (Replacement and Repair only)
 *
 * penalty_submission_file
 *   The uploaded receipts / photos (1-3 per submission), stored on the private
 *   disk and served only through permission-checked routes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('penalty_submission')) {
            Schema::create('penalty_submission', function (Blueprint $table) {
                $table->increments('submission_id');
                $table->unsignedInteger('penalty_id');
                $table->unsignedInteger('user_id');
                $table->enum('type', ['Payment', 'Replacement', 'Repair']);
                $table->string('reference_no', 100)->nullable();
                $table->decimal('amount_paid', 10, 2)->nullable();
                $table->date('settled_on');
                $table->text('notes')->nullable();
                $table->enum('status', ['Under Review', 'Approved', 'Rejected'])->default('Under Review');
                $table->dateTime('submitted_at');
                $table->unsignedInteger('reviewed_by_admin_id')->nullable();
                $table->unsignedInteger('reviewed_by_dept_acc_id')->nullable();
                $table->dateTime('reviewed_at')->nullable();
                $table->text('reject_reason')->nullable();
                $table->boolean('replacement_received')->default(false);

                $table->index(['penalty_id', 'status'], 'idx_penalty_submission_penalty_status');

                $table->foreign('penalty_id', 'fk_penalty_submission_penalty')
                    ->references('penalty_id')->on('penalty')
                    ->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('user_id', 'fk_penalty_submission_user')
                    ->references('user_id')->on('user_account')
                    ->cascadeOnDelete()->cascadeOnUpdate();
                $table->foreign('reviewed_by_admin_id', 'fk_penalty_submission_admin')
                    ->references('admin_id')->on('admin')->nullOnDelete()->cascadeOnUpdate();
                $table->foreign('reviewed_by_dept_acc_id', 'fk_penalty_submission_dept')
                    ->references('dept_acc_id')->on('department_account')->nullOnDelete()->cascadeOnUpdate();
            });
        }

        if (!Schema::hasTable('penalty_submission_file')) {
            Schema::create('penalty_submission_file', function (Blueprint $table) {
                $table->increments('file_id');
                $table->unsignedInteger('submission_id');
                $table->string('path', 255);
                $table->string('original_name', 255);
                $table->string('mime_type', 100);
                $table->unsignedInteger('size');

                $table->foreign('submission_id', 'fk_penalty_submission_file_submission')
                    ->references('submission_id')->on('penalty_submission')
                    ->cascadeOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('penalty_submission_file');
        Schema::dropIfExists('penalty_submission');
    }
};
