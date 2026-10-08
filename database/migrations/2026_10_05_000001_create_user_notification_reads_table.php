<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// User notifications are derived from borrow_request / borrow_transaction, so
// only the read state needs storing: one row per (user, notification key).
// The unique key guarantees a notification can be marked read only once.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_notification_reads', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('notif_key', 100);
            $table->dateTime('read_at')->useCurrent();
            $table->unique(['user_id', 'notif_key'], 'uq_user_notification_read');
            // Matches user_account.user_id (INT); read marks go with the account.
            $table->foreign('user_id')->references('user_id')->on('user_account')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_reads');
    }
};
