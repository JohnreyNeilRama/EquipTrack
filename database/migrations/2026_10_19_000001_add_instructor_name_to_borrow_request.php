<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * borrow_request.instructor_name
 *   The instructor the user names on the Request Equipment form. Required for
 *   new requests (validated in UserEquipmentController::store); requests
 *   submitted before this migration keep NULL and show "N/A".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('borrow_request', 'instructor_name')) {
            Schema::table('borrow_request', function (Blueprint $table) {
                $table->string('instructor_name', 150)->nullable()->after('purpose');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('borrow_request', 'instructor_name')) {
            Schema::table('borrow_request', function (Blueprint $table) {
                $table->dropColumn('instructor_name');
            });
        }
    }
};
