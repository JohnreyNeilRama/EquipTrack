<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Admin account management: admins can be deactivated like the other account
// types (status), and the Users page shows when they were added and last
// signed in, like it does for everyone else. Existing admins become Active.
return new class extends Migration
{
    public function up(): void
    {
        $addCreatedAt = !Schema::hasColumn('admin', 'created_at');

        Schema::table('admin', function (Blueprint $table) use ($addCreatedAt) {
            if (!Schema::hasColumn('admin', 'status')) {
                $table->string('status', 50)->default('Active')->after('password');
            }
            if (!Schema::hasColumn('admin', 'last_online')) {
                $table->dateTime('last_online')->nullable()->after('status');
            }
            if ($addCreatedAt) {
                $table->timestamp('created_at')->nullable()->useCurrent()->after('last_online');
            }
        });

        // Admins that already existed have no known creation date; leave it
        // empty rather than stamping them with the date of this migration.
        if ($addCreatedAt) {
            DB::table('admin')->update(['created_at' => null]);
        }
    }

    public function down(): void
    {
        Schema::table('admin', function (Blueprint $table) {
            foreach (['created_at', 'last_online', 'status'] as $column) {
                if (Schema::hasColumn('admin', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
