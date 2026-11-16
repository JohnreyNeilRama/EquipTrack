<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Data Privacy Act (RA 10173): every account agrees to the Privacy Notice
// (/privacy) when it is created (Register, or Set Your Password from an
// invite), and the agreement is kept as proof.
//
// - privacy_accepted_at: when the account agreed
// - privacy_version: which version of the notice it agreed to
//   (config equiptrack.privacy.version)
//
// Accounts created before this are left empty, so they are asked once at
// their next sign-in (App\Http\Middleware\EnsurePrivacyAccepted).
return new class extends Migration
{
    private array $tables = ['user_account', 'admin', 'department_account'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dateTime('privacy_accepted_at')->nullable();
                $table->unsignedSmallInteger('privacy_version')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn(['privacy_accepted_at', 'privacy_version']);
            });
        }
    }
};
