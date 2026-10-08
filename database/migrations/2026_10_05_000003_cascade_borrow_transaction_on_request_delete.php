<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// borrow_transaction.request_id had no ON DELETE rule (MySQL default: RESTRICT),
// so deleting a user_account cascaded into borrow_request and then failed on
// the borrow_transaction rows that reference those requests (error 1451).
// Now a deleted request removes its transaction too. The admin delete flow
// separately refuses to delete users with Active/Overdue loans or unpaid
// penalties, so only completed history is ever cascaded away.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->dropForeign('borrow_transaction_request_id_foreign');
            $table->foreign('request_id', 'borrow_transaction_request_id_foreign')
                ->references('request_id')->on('borrow_request')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('borrow_transaction', function (Blueprint $table) {
            $table->dropForeign('borrow_transaction_request_id_foreign');
            $table->foreign('request_id', 'borrow_transaction_request_id_foreign')
                ->references('request_id')->on('borrow_request')
                ->cascadeOnUpdate();
        });
    }
};
