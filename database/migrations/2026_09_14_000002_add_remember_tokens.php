<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Enables Laravel's "Remember Me" (decorative in the legacy app) for all three account tables.
return new class extends Migration
{
    private array $tables = [
        'user_account' => 'user_id',
        'admin' => 'admin_id',
        'department_account' => 'dept_acc_id',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $pk) {
            Schema::table($table, function (Blueprint $t) {
                $t->rememberToken();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table => $pk) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropColumn('remember_token');
            });
        }
    }
};
