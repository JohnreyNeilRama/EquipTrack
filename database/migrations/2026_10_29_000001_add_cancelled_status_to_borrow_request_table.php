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
        // ->change() works on MySQL and on SQLite (the test database).
        Schema::table('borrow_request', function (Blueprint $table) {
            $table->enum('overall_status', ['Pending', 'Approved', 'Rejected', 'Cancelled'])->default('Pending')->change();
        });

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

        Schema::table('borrow_request', function (Blueprint $table) {
            $table->enum('overall_status', ['Pending', 'Approved', 'Rejected'])->default('Pending')->change();
        });

        if (Schema::hasColumn('borrow_request', 'cancelled_at')) {
            Schema::table('borrow_request', function (Blueprint $table) {
                $table->dropColumn('cancelled_at');
            });
        }
    }
};
