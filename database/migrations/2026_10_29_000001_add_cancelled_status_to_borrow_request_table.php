<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Borrowers can cancel their own request while it is still Pending: the
// request becomes Cancelled (overall_status) and records when (cancelled_at).
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE borrow_request MODIFY overall_status ENUM('Pending','Approved','Rejected','Cancelled') NOT NULL DEFAULT 'Pending'");

        if (!Schema::hasColumn('borrow_request', 'cancelled_at')) {
            Schema::table('borrow_request', function (Blueprint $table) {
                $table->dateTime('cancelled_at')->nullable()->after('reject_reason');
            });
        }
    }

    public function down(): void
    {
        // Cancelled requests have no place in the old status list; keep them
        // closed as Rejected so they never reappear as Pending.
        DB::table('borrow_request')->where('overall_status', 'Cancelled')->update([
            'overall_status' => 'Rejected',
            'reject_reason' => 'Cancelled by the borrower.',
        ]);

        DB::statement("ALTER TABLE borrow_request MODIFY overall_status ENUM('Pending','Approved','Rejected') NOT NULL DEFAULT 'Pending'");

        if (Schema::hasColumn('borrow_request', 'cancelled_at')) {
            Schema::table('borrow_request', function (Blueprint $table) {
                $table->dropColumn('cancelled_at');
            });
        }
    }
};
