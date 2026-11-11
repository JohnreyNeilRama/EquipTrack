<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Password confirmation for sensitive changes to Administrator accounts on
 * the Users page: changing an Admin's email, activating or deactivating an
 * Admin, and deleting an Admin. The signed-in Admin (the one making the
 * change) types their own password, so someone using a session that was left
 * signed in cannot make these changes.
 *
 * The password is sent as current_password, which Laravel never keeps in the
 * old-input session data. Wrong passwords are limited per account.
 */
class AdminReauth
{
    public const MAX_ATTEMPTS_PER_MINUTE = 5;

    /** Why the change is refused, or null when the password is correct. */
    public static function failure(Request $request): ?string
    {
        $admin = auth('admin')->user();
        $key = 'admin-reauth|' . $admin->getAuthIdentifier();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS_PER_MINUTE)) {
            return 'Too many wrong passwords. Please wait ' . RateLimiter::availableIn($key) . ' seconds and try again.';
        }

        $password = (string) $request->input('current_password', '');

        if ($password === '') {
            return 'Enter your password to confirm this change.';
        }

        if (!Hash::check($password, $admin->getAuthPassword())) {
            RateLimiter::hit($key, 60);

            return 'Your password is incorrect, so nothing was changed.';
        }

        RateLimiter::clear($key);

        return null;
    }
}
