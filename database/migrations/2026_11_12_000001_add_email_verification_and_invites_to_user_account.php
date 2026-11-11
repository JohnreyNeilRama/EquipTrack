<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Students and Faculty prove they own their email address.
//
// - user_account.email_verified_at: when the owner confirmed the address,
//   either by the verification link (self-registration) or by setting their
//   password from an invite (accounts added by staff). NULL = not confirmed.
//   Every existing account is marked confirmed, so nobody is locked out.
// - user_account.invited_at: when staff last emailed an invite to set the
//   password (NULL for self-registered accounts).
// - user_account.status gains the value "Pending Approval" (self-registered
//   Faculty until their department approves them); the column is plain text,
//   so only the meaning changes.
// - user_invite_tokens: hashed invite tokens, like password_reset_tokens, for
//   the "user_invites" broker in config/auth.php (valid for 7 days).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_account', function (Blueprint $table) {
            $table->dateTime('email_verified_at')->nullable()->after('email');
            $table->dateTime('invited_at')->nullable()->after('email_verified_at');
        });

        DB::table('user_account')->update(['email_verified_at' => now()]);

        Schema::create('user_invite_tokens', function (Blueprint $table) {
            $table->string('email', 100)->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_invite_tokens');

        // Waiting Faculty can't stay in a state the old code doesn't know.
        DB::table('user_account')->where('status', 'Pending Approval')->update(['status' => 'Deactivated']);

        Schema::table('user_account', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'invited_at']);
        });
    }
};
