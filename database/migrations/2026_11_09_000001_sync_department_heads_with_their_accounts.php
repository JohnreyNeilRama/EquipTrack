<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data only (no table or column changes): each department's head name is set
 * to the name on its Department Account, the person who actually holds it.
 * Fixes a department still showing a placeholder or a previous head's name;
 * from now on DepartmentAccount keeps the two in sync automatically.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('department_account')
            ->whereNotNull('department_id')
            ->where('full_name', '!=', '')
            ->orderBy('dept_acc_id')
            ->get(['department_id', 'full_name'])
            ->each(fn ($account) => DB::table('department')
                ->where('department_id', $account->department_id)
                ->update(['department_head' => trim($account->full_name)]));
    }

    public function down(): void
    {
        // The previous names were placeholders or out of date; nothing to restore.
    }
};
