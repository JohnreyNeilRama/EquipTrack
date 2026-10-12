<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Equipment Classification.
 *
 * equipment.classification
 *   Expensive / Not Expensive, chosen by the admin. Every existing row gets
 *   "Not Expensive", so existing equipment keeps the current approval workflow
 *   until the admin reclassifies it. Department-registered equipment, which has
 *   no classification field, gets the same default.
 *
 * borrow_request.equipment_classification
 *   The equipment's classification copied at the moment the user submits the
 *   request. It decides which approval workflow the request follows, so
 *   reclassifying equipment later never changes the rules for requests that
 *   are already in review. NULL (requests submitted before this migration) is
 *   treated as "Not Expensive", i.e. the existing workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('equipment', 'classification')) {
            Schema::table('equipment', function (Blueprint $table) {
                $table->enum('classification', ['Expensive', 'Not Expensive'])
                    ->default('Not Expensive')
                    ->after('status');
            });
        }

        if (!Schema::hasColumn('borrow_request', 'equipment_classification')) {
            Schema::table('borrow_request', function (Blueprint $table) {
                $table->enum('equipment_classification', ['Expensive', 'Not Expensive'])
                    ->nullable()
                    ->after('quantity');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('borrow_request', 'equipment_classification')) {
            Schema::table('borrow_request', function (Blueprint $table) {
                $table->dropColumn('equipment_classification');
            });
        }

        if (Schema::hasColumn('equipment', 'classification')) {
            Schema::table('equipment', function (Blueprint $table) {
                $table->dropColumn('classification');
            });
        }
    }
};
