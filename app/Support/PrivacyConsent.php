<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use App\Services\AuditLogger;
use App\Services\PasswordResetAccounts;
use Illuminate\Http\Request;

/**
 * Agreement to the Privacy Notice (/privacy), as the Data Privacy Act of
 * 2012 (RA 10173) expects: every account agrees once, and again whenever the
 * notice's version (config equiptrack.privacy.version) goes up.
 *
 * Asked on Register and Set Your Password (invites), and at the next sign-in
 * for accounts that haven't agreed to the current version
 * (App\Http\Middleware\EnsurePrivacyAccepted). Each agreement is saved on the
 * account (when, which version) and written to the Audit Trail.
 */
class PrivacyConsent
{
    public static function version(): int
    {
        return (int) config('equiptrack.privacy.version', 1);
    }

    public static function hasAccepted(UserAccount|DepartmentAccount|Admin $account): bool
    {
        return (int) $account->privacy_version >= self::version();
    }

    /** Saves the agreement to the current version. $how: where it happened, for the Audit Trail. */
    public static function record(string $guard, UserAccount|DepartmentAccount|Admin $account, string $how, ?Request $request = null): void
    {
        $account->forceFill([
            'privacy_accepted_at' => now(),
            'privacy_version' => self::version(),
        ])->save();

        AuditLogger::log(
            'Accepted privacy notice',
            PasswordResetAccounts::entity($guard, $account),
            'Version ' . self::version() . ', ' . $how,
            AuditLogger::actorFor($guard, $account),
            $request,
        );
    }

    /**
     * The signed-in account in this browser (one identity per session).
     *
     * @return array{0: string, 1: UserAccount|DepartmentAccount|Admin}|null  guard and account
     */
    public static function signedIn(): ?array
    {
        foreach (['admin', 'dept', 'user'] as $guard) {
            if ($account = auth($guard)->user()) {
                return [$guard, $account];
            }
        }

        return null;
    }
}
