<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes borrow_transaction.return_photo. The Return Confirmation form no
 * longer accepts a photo (reviewers compare against equipment.image instead),
 * so nothing writes this column. Databases that never had it are left as is.
 * down() restores the column empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('borrow_transaction', 'return_photo')) {
            Schema::table('borrow_transaction', function (Blueprint $table) {
                $table->dropColumn('return_photo');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('borrow_transaction', 'return_photo')) {
            Schema::table('borrow_transaction', function (Blueprint $table) {
                $table->string('return_photo', 255)->nullable()->after('inspection_remarks');
            });
        }
    }
};
