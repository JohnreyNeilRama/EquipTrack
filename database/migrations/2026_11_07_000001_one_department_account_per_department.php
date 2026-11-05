<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One Department Account per department, and its role is always Department
 * Head. The unique index enforces the first rule in the database itself.
 * (An account whose department was deleted keeps a NULL department_id;
 * NULLs never clash, so such accounts are still allowed.)
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('department_account')
            ->whereNotNull('department_id')
            ->groupBy('department_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('department_id');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Some departments have more than one Department Account (department_id: '
                . $duplicates->implode(', ')
                . '). Keep one account per department, then run the migration again.'
            );
        }

        DB::table('department_account')
            ->where('role', '!=', 'Department Head')
            ->update(['role' => 'Department Head']);

        Schema::table('department_account', function (Blueprint $table) {
            $table->unique('department_id', 'uq_dept_acc_department');
        });
    }

    public function down(): void
    {
        Schema::table('department_account', function (Blueprint $table) {
            $table->dropUnique('uq_dept_acc_department');
        });
    }
};
