<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * borrow_transaction.return_photo
 *   Photo of the returned equipment taken by the department during the Return
 *   Confirmation inspection. Stored on the public disk like the other uploaded
 *   images ("/storage/images/returns/..."); NULL when no photo was attached.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('borrow_transaction', 'return_photo')) {
            Schema::table('borrow_transaction', function (Blueprint $table) {
                $table->string('return_photo', 255)->nullable()->after('inspection_remarks');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('borrow_transaction', 'return_photo')) {
            Schema::table('borrow_transaction', function (Blueprint $table) {
                $table->dropColumn('return_photo');
            });
        }
    }
};
