<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Requests nobody reviewed before their due date's return deadline can no
// longer be used: they become Expired (overall_status) instead of staying
// Pending forever. The borrower can submit a new request.
return new class extends Migration
{
    public function up(): void
    {
        // ->change() works on MySQL and on SQLite (the test database).
        Schema::table('borrow_request', function (Blueprint $table) {
            $table->enum('overall_status', ['Pending', 'Approved', 'Rejected', 'Cancelled', 'Expired'])->default('Pending')->change();
        });
    }

    public function down(): void
    {
        // Expired requests have no place in the old status list; keep them
        // closed as Rejected so they never reappear as Pending.
        DB::table('borrow_request')->where('overall_status', 'Expired')->update([
            'overall_status' => 'Rejected',
            'reject_reason' => 'Expired: not reviewed before its return date.',
        ]);

        Schema::table('borrow_request', function (Blueprint $table) {
            $table->enum('overall_status', ['Pending', 'Approved', 'Rejected', 'Cancelled'])->default('Pending')->change();
        });
    }
};
