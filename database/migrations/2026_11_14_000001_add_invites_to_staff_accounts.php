<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Department (Lab Personnel) and Admin accounts added by an Admin get an
// invite email to set their own password, like Students and Faculty added by
// staff (2026_11_12_000001). The Admin no longer chooses or knows it.
//
// - admin / department_account .email_verified_at: when the owner set their
//   password from the invite (which confirms the address). Every existing
//   account is marked confirmed, so nobody is locked out.
// - admin / department_account .invited_at: when the last invite was emailed
//   (NULL for accounts created before invites).
// - admin_invite_tokens / dept_invite_tokens: hashed invite tokens for the
//   "admin_invites" and "department_invites" brokers in config/auth.php.
return new class extends Migration
{
    private array $accountTables = ['admin', 'department_account'];

    private array $tokenTables = ['admin_invite_tokens', 'dept_invite_tokens'];

    public function up(): void
    {
        foreach ($this->accountTables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dateTime('email_verified_at')->nullable();
                $table->dateTime('invited_at')->nullable();
            });

            DB::table($name)->update(['email_verified_at' => now()]);
        }

        foreach ($this->tokenTables as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->string('email', 100)->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tokenTables as $name) {
            Schema::dropIfExists($name);
        }

        foreach ($this->accountTables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['email_verified_at', 'invited_at']);
            });
        }
    }
};
