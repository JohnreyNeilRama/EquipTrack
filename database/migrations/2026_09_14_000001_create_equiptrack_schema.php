<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reproduces the live `equiptrack` schema (dumped 2026-09-14) with three
// deliberate FK fixes, all documented inline below.
// INT keys are used throughout (increments/unsignedInteger) to match the
// original schema exactly — BIGINT ids would make the FKs incompatible.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department', function (Blueprint $table) {
            $table->increments('department_id');
            $table->string('department_name', 255)->unique('uq_department_name');
            $table->string('department_code', 50)->unique('uq_department_code');
            $table->string('college', 255)->nullable();
            $table->string('department_head', 255)->nullable();
            $table->longText('profile_image')->nullable();
            $table->dateTime('created_at')->nullable()->useCurrent();
        });

        Schema::create('admin', function (Blueprint $table) {
            $table->increments('admin_id');
            $table->string('name', 100);
            $table->string('username', 50)->unique('uq_admin_username');
            $table->string('email', 100)->nullable();
            $table->string('employee_id', 50)->nullable();
            $table->longText('profile_image')->nullable();
            $table->string('password', 255);
        });

        Schema::create('department_account', function (Blueprint $table) {
            $table->increments('dept_acc_id');
            $table->string('full_name', 255);
            $table->string('email', 100)->unique('uq_dept_acc_email');
            $table->string('password', 255);
            $table->string('employee_id', 100)->nullable()->unique('uq_dept_acc_employee_id');
            $table->string('role', 50);
            $table->unsignedInteger('department_id')->nullable();
            $table->dateTime('last_online')->nullable();
            $table->longText('profile_image')->nullable();
            $table->string('status', 50)->default('Active');
            $table->timestamp('created_at')->nullable()->useCurrent();
            // FIX: original had no ON DELETE rule; a deleted department no longer blocks on assigned accounts
            $table->foreign('department_id')->references('department_id')->on('department')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('user_account', function (Blueprint $table) {
            $table->increments('user_id');
            $table->enum('role', ['Student', 'Faculty']);
            $table->string('email', 100)->unique('uq_user_email');
            $table->string('password', 255);
            $table->unsignedInteger('department_id')->nullable();
            $table->dateTime('date_created')->useCurrent();
            $table->longText('profile_image')->nullable();
            $table->dateTime('last_online')->nullable();
            $table->string('status', 50)->default('Active');
            // FIX: same rationale as department_account
            $table->foreign('department_id')->references('department_id')->on('department')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('student', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('id_number', 20)->unique('uq_student_id_number');
            $table->string('year_level', 20);
            $table->string('address', 150);
            $table->foreign('user_id')->references('user_id')->on('user_account')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('faculty_member', function (Blueprint $table) {
            $table->unsignedInteger('user_id')->primary();
            $table->string('first_name', 50);
            $table->string('last_name', 50);
            $table->string('faculty_id_number', 20)->unique('uq_faculty_id_number');
            $table->string('teaching_license_no', 30)->unique('uq_teaching_license_no');
            $table->string('highest_educational_attainment', 100)->nullable();
            $table->string('address', 150);
            $table->foreign('user_id')->references('user_id')->on('user_account')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('equipment_category', function (Blueprint $table) {
            $table->increments('category_id');
            $table->string('category_name', 255)->unique('uq_category_name');
            $table->dateTime('created_at')->nullable()->useCurrent();
        });

        Schema::create('equipment', function (Blueprint $table) {
            $table->increments('equipment_id');
            $table->string('name', 100);
            $table->string('brand', 50);
            $table->string('model', 50)->nullable();
            $table->string('serial_number', 50)->unique('uq_equipment_serial_number');
            $table->longText('image');
            $table->integer('available_qty')->default(0);
            $table->integer('total_qty');
            $table->enum('status', ['Available', 'Unavailable', 'On Hold', 'Under Maintenance']);
            $table->string('accessories_included', 150)->nullable();
            $table->unsignedInteger('category_id');
            $table->unsignedInteger('department_id');
            $table->dateTime('date_created')->useCurrent();
            $table->foreign('category_id')->references('category_id')->on('equipment_category')
                ->restrictOnDelete()->cascadeOnUpdate();
            // FIX: original had no ON DELETE rule; deleting a department now removes its equipment instead of failing
            $table->foreign('department_id')->references('department_id')->on('department')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        Schema::create('borrow_request', function (Blueprint $table) {
            $table->increments('request_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('equipment_id');
            $table->integer('quantity')->default(1);
            $table->string('purpose', 255);
            $table->text('notes')->nullable();
            $table->dateTime('date_requested')->useCurrent();
            $table->date('date_needed');
            $table->date('return_date')->nullable();
            $table->date('due_date')->nullable();
            $table->date('borrow_date')->nullable();
            $table->enum('admin_status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->unsignedInteger('admin_id')->nullable();
            $table->dateTime('admin_reviewed_at')->nullable();
            $table->enum('dept_status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->unsignedInteger('dept_acc_id')->nullable();
            $table->dateTime('dept_reviewed_at')->nullable();
            $table->enum('overall_status', ['Pending', 'Approved', 'Rejected'])->default('Pending');
            $table->text('reject_reason')->nullable();
            // FIX: original had no ON DELETE rule, which made user deletion fail
            // (and the old delete flow reported false success)
            $table->foreign('user_id')->references('user_id')->on('user_account')
                ->cascadeOnDelete()->cascadeOnUpdate();
            // FIX: same as above for deleted equipment
            $table->foreign('equipment_id')->references('equipment_id')->on('equipment')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('admin_id')->references('admin_id')->on('admin')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('dept_acc_id')->references('dept_acc_id')->on('department_account')
                ->nullOnDelete()->cascadeOnUpdate();
        });

        Schema::create('borrow_transaction', function (Blueprint $table) {
            $table->increments('transaction_id');
            $table->unsignedInteger('request_id');
            $table->date('borrow_date');
            $table->date('due_date');
            $table->date('return_date')->nullable();
            $table->string('condition_on_return', 150)->nullable();
            $table->enum('status', ['Active', 'Returned', 'Overdue'])->default('Active');
            $table->decimal('penalty_amount', 8, 2)->default(0.00);
            $table->enum('penalty_status', ['None', 'Unpaid', 'Paid'])->default('None');
            $table->unique('request_id', 'uq_borrow_transaction_request');
            $table->foreign('request_id')->references('request_id')->on('borrow_request')
                ->cascadeOnUpdate();
        });

        Schema::create('audit_trail', function (Blueprint $table) {
            $table->increments('audit_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('admin_id')->nullable();
            $table->unsignedInteger('dept_acc_id')->nullable();
            $table->string('action', 100);
            $table->string('ip_address', 45);
            $table->string('affected_entity', 100);
            $table->timestamp('created_at')->useCurrent();
            $table->string('details', 255)->nullable();
            $table->foreign('user_id')->references('user_id')->on('user_account')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('admin_id')->references('admin_id')->on('admin')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('dept_acc_id')->references('dept_acc_id')->on('department_account')
                ->nullOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_trail');
        Schema::dropIfExists('borrow_transaction');
        Schema::dropIfExists('borrow_request');
        Schema::dropIfExists('equipment');
        Schema::dropIfExists('equipment_category');
        Schema::dropIfExists('faculty_member');
        Schema::dropIfExists('student');
        Schema::dropIfExists('user_account');
        Schema::dropIfExists('department_account');
        Schema::dropIfExists('admin');
        Schema::dropIfExists('department');
    }
};
