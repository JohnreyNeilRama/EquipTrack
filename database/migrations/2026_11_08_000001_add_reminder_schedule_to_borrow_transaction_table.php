<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Reminder schedule for borrowed equipment (App\Support\LoanReminders):
//
//  - borrow_transaction.release_reminder_sent_at: the "return it today" email
//    of a Within the Day loan, sent when it is released.
//  - borrow_transaction.final_reminder_sent_at: the "2 hours left" email of a
//    Within the Day loan.
//  - borrow_transaction.overdue_reminder_step: the latest overdue step that was
//    emailed (1, 3, 7, then weekly), so each step is emailed once.
//  - loan_manual_reminders: every reminder Admin or Lab Personnel sent by hand
//    (the "Remind" button), so it can be limited to once a day per loan and
//    shown in the borrower's bell.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrow_transaction', function (Blueprint $table) {
            if (!Schema::hasColumn('borrow_transaction', 'release_reminder_sent_at')) {
                $table->dateTime('release_reminder_sent_at')->nullable()->after('overdue_reminder_sent_at');
            }
            if (!Schema::hasColumn('borrow_transaction', 'final_reminder_sent_at')) {
                $table->dateTime('final_reminder_sent_at')->nullable()->after('release_reminder_sent_at');
            }
            if (!Schema::hasColumn('borrow_transaction', 'overdue_reminder_step')) {
                $table->unsignedSmallInteger('overdue_reminder_step')->nullable()->after('final_reminder_sent_at');
            }
        });

        // Loans that already got an overdue email under the old schedule count
        // as reminded for the first step; the later steps still follow.
        DB::table('borrow_transaction')
            ->whereNotNull('overdue_reminder_sent_at')
            ->whereNull('overdue_reminder_step')
            ->update(['overdue_reminder_step' => 1]);

        if (!Schema::hasTable('loan_manual_reminders')) {
            Schema::create('loan_manual_reminders', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('transaction_id');
                // 'admin' or 'dept': the two account tables have independent ids.
                $table->string('sent_by_type', 10);
                $table->unsignedInteger('sent_by_id');
                $table->dateTime('sent_at');
                $table->index(['transaction_id', 'sent_at'], 'idx_manual_reminder_loan');
                $table->foreign('transaction_id')->references('transaction_id')->on('borrow_transaction')
                    ->cascadeOnDelete()->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_manual_reminders');

        Schema::table('borrow_transaction', function (Blueprint $table) {
            foreach (['overdue_reminder_step', 'final_reminder_sent_at', 'release_reminder_sent_at'] as $column) {
                if (Schema::hasColumn('borrow_transaction', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
