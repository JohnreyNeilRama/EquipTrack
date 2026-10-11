<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// borrow_transaction.request_id had no ON DELETE rule (MySQL default: RESTRICT),
// so deleting a user_account cascaded into borrow_request and then failed on
// the borrow_transaction rows that reference those requests (error 1451).
// Now a deleted request removes its transaction too. The admin delete flow
// separately refuses to delete users with Active/Overdue loans or unpaid
// penalties, so only completed history is ever cascaded away.
return new class extends Migration
{
    // SQLite (used by the test suite) cannot drop a foreign key by name, only by
    // column. MySQL keeps using the named key exactly as before.
    private function dropRequestForeign(Blueprint $table): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $table->dropForeign(['request_id']);

            return;
        }

        $table->dropForeign('borrow_transaction_request_id_foreign');
    }

    public function up(): void
    {
        Schema::table('borrow_transaction', function (Blueprint $table) {
            $this->dropRequestForeign($table);
            $table->foreign('request_id', 'borrow_transaction_request_id_foreign')
                ->references('request_id')->on('borrow_request')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('borrow_transaction', function (Blueprint $table) {
            $this->dropRequestForeign($table);
            $table->foreign('request_id', 'borrow_transaction_request_id_foreign')
                ->references('request_id')->on('borrow_request')
                ->cascadeOnUpdate();
        });
    }
};
