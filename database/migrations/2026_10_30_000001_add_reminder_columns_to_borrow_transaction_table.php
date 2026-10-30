<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Reminder emails for borrowed equipment (equiptrack:send-loan-reminders):
// when the "due soon" email was sent, and when the latest overdue email was
// sent, so a borrower never gets the same reminder twice.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrow_transaction', function (Blueprint $table) {
            if (!Schema::hasColumn('borrow_transaction', 'due_reminder_sent_at')) {
                $table->dateTime('due_reminder_sent_at')->nullable()->after('return_restores_stock');
            }
            if (!Schema::hasColumn('borrow_transaction', 'overdue_reminder_sent_at')) {
                $table->dateTime('overdue_reminder_sent_at')->nullable()->after('due_reminder_sent_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('borrow_transaction', function (Blueprint $table) {
            foreach (['overdue_reminder_sent_at', 'due_reminder_sent_at'] as $column) {
                if (Schema::hasColumn('borrow_transaction', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
