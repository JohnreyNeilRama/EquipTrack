<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Token tables for the three password reset brokers in config/auth.php
// (users, admins, departments). Laravel stores only a hash of each token.
return new class extends Migration
{
    private array $tables = [
        'password_reset_tokens',        // Students & Faculty (user_account)
        'admin_password_reset_tokens',  // admin
        'dept_password_reset_tokens',   // department_account
    ];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (Schema::hasTable($name)) {
                continue;
            }

            Schema::create($name, function (Blueprint $table) {
                $table->string('email', 100)->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::dropIfExists($name);
        }
    }
};
