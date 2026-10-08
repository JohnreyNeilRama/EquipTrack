<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Read state for Admin / Lab Personnel (department) notifications. The
// notifications themselves are derived from borrow_request, so only what each
// reviewer has read is stored: one row per (account type, account, key).
// account_type is 'admin' or 'dept' because the two account tables have
// independent ids, so there is no single foreign key.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_notification_reads', function (Blueprint $table) {
            $table->increments('id');
            $table->string('account_type', 10);
            $table->unsignedInteger('account_id');
            $table->string('notif_key', 100);
            $table->dateTime('read_at')->useCurrent();
            $table->unique(['account_type', 'account_id', 'notif_key'], 'uq_staff_notification_read');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_notification_reads');
    }
};
