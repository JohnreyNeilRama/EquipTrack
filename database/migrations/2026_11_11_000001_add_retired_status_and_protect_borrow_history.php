<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Borrowing history is never deleted together with an account or an item.
//
// 1. equipment.status gains "Retired": equipment that is permanently out of
//    the inventory keeps its record and history instead of being deleted.
// 2. borrow_request.user_id and borrow_request.equipment_id change from
//    ON DELETE CASCADE to ON DELETE RESTRICT: the database refuses to delete a
//    user account or an equipment item that has borrow requests, so its
//    requests, loans and penalties can't disappear with it. Accounts are
//    deactivated and equipment retired instead (see AdminUsersController and
//    Equipment::deleteBlocker()). ON UPDATE CASCADE is kept.
return new class extends Migration
{
    public function up(): void
    {
        // ->change() works on MySQL and on SQLite (the test database).
        Schema::table('equipment', function (Blueprint $table) {
            $table->enum('status', ['Available', 'Unavailable', 'On Hold', 'Under Maintenance', 'Retired'])->change();
        });

        $this->setDeleteRule('restrict');
    }

    public function down(): void
    {
        // Retired has no place in the old list; such items stay out of use.
        DB::table('equipment')->where('status', 'Retired')->update(['status' => 'Unavailable']);

        Schema::table('equipment', function (Blueprint $table) {
            $table->enum('status', ['Available', 'Unavailable', 'On Hold', 'Under Maintenance'])->change();
        });

        $this->setDeleteRule('cascade');
    }

    /** Re-creates both borrow_request foreign keys with the given ON DELETE rule. */
    private function setDeleteRule(string $rule): void
    {
        Schema::table('borrow_request', function (Blueprint $table) {
            // By column, so the default key names are used on MySQL and SQLite alike.
            $table->dropForeign(['user_id']);
            $table->dropForeign(['equipment_id']);
        });

        Schema::table('borrow_request', function (Blueprint $table) use ($rule) {
            $user = $table->foreign('user_id')->references('user_id')->on('user_account')->cascadeOnUpdate();
            $equipment = $table->foreign('equipment_id')->references('equipment_id')->on('equipment')->cascadeOnUpdate();

            if ($rule === 'restrict') {
                $user->restrictOnDelete();
                $equipment->restrictOnDelete();
            } else {
                $user->cascadeOnDelete();
                $equipment->cascadeOnDelete();
            }
        });
    }
};
