<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Per-account sign-in lockout. After MAX_FAILURES wrong passwords for the
 * same email or username (from any computer), sign-in for that name is
 * refused for LOCK_MINUTES, even with the right password. This stops slow
 * password guessing spread over many IP addresses, which the per-visitor
 * limit on the login route (6 per minute) can't see.
 *
 * Keyed on what was typed (lowercased), whether or not such an account
 * exists, so the lockout can't be used to find out which emails are
 * registered. A successful sign-in or a password reset clears it.
 */
class LoginLockout
{
    public const MAX_FAILURES = 10;

    public const LOCK_MINUTES = 15;

    public static function key(string $login): string
    {
        return 'login-lockout|' . mb_strtolower(trim($login));
    }

    public static function isLocked(string $login): bool
    {
        return RateLimiter::tooManyAttempts(self::key($login), self::MAX_FAILURES);
    }

    /** Whole minutes until sign-in is allowed again (at least 1). */
    public static function minutesLeft(string $login): int
    {
        return max(1, (int) ceil(RateLimiter::availableIn(self::key($login)) / 60));
    }

    /** Counts a wrong password; true when this failure locks the account. */
    public static function recordFailure(string $login): bool
    {
        return RateLimiter::hit(self::key($login), self::LOCK_MINUTES * 60) === self::MAX_FAILURES;
    }

    /**
     * Clears the lockout for every name the account can sign in with (empty
     * ones, e.g. no username, are skipped).
     */
    public static function clear(?string ...$logins): void
    {
        foreach (array_filter($logins) as $login) {
            RateLimiter::clear(self::key($login));
        }
    }
}
