<?php

namespace App\Support;

use App\Models\Admin;
use App\Models\DepartmentAccount;
use App\Notifications\SignInCodeNotification;
use Illuminate\Http\Request;

/**
 * Two-step sign-in for the staff accounts: Admin (config
 * equiptrack.admin_two_factor) and Lab Personnel / Department accounts
 * (config equiptrack.department_two_factor). After the right password, a
 * 6-digit code is emailed to the account's address and must be entered
 * before the account is signed in. Students and Faculty sign in with their
 * password only.
 *
 * The waiting sign-in lives only in this browser's session, so the code is
 * useless anywhere else. Only a keyed hash of the code is kept, never the
 * code itself. A code works once, for CODE_MINUTES, and MAX_ATTEMPTS wrong
 * tries cancel it (the person starts again with the password, which counts
 * towards App\Support\LoginLockout as usual).
 */
class SignInCode
{
    public const CODE_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /** A new code can be asked for at most this often. */
    public const RESEND_SECONDS = 60;

    private const KEY = 'sign_in_code';

    /** Guards that confirm sign-in with an emailed code, and their setting. */
    private const SETTINGS = [
        'admin' => 'equiptrack.admin_two_factor',
        'dept' => 'equiptrack.department_two_factor',
    ];

    public static function enabledFor(string $guard): bool
    {
        return isset(self::SETTINGS[$guard]) && (bool) config(self::SETTINGS[$guard]);
    }

    /**
     * Starts the code step for an account whose password was right and emails
     * the code. Throws when the email can't be sent (nothing is waiting then).
     */
    public static function start(Request $request, string $guard, Admin|DepartmentAccount $account, bool $remember): void
    {
        $request->session()->put(self::KEY, [
            'guard' => $guard,
            'id' => $account->getKey(),
            'remember' => $remember,
            'attempts' => 0,
        ]);

        try {
            self::sendCode($request, $account);
        } catch (\Throwable $e) {
            self::clear($request);
            throw $e;
        }
    }

    /**
     * The account waiting to enter a code in this browser, if any.
     *
     * @return array{0: string, 1: Admin|DepartmentAccount}|null  guard and account
     */
    public static function pending(Request $request): ?array
    {
        $pending = $request->session()->get(self::KEY);
        if (!$pending) {
            return null;
        }

        $account = match ($pending['guard']) {
            'admin' => Admin::find($pending['id']),
            'dept' => DepartmentAccount::find($pending['id']),
            default => null,
        };

        return $account ? [$pending['guard'], $account] : null;
    }

    public static function remember(Request $request): bool
    {
        return (bool) ($request->session()->get(self::KEY)['remember'] ?? false);
    }

    /**
     * Checks a typed code.
     *
     * @return 'ok'|'wrong'|'expired'|'too_many'|'none'
     */
    public static function check(Request $request, string $code): string
    {
        $pending = $request->session()->get(self::KEY);
        if (!$pending || empty($pending['hash'])) {
            return 'none';
        }

        if (now()->getTimestamp() > $pending['expires_at']) {
            return 'expired';
        }

        $code = preg_replace('/\D/', '', $code);
        if (hash_equals($pending['hash'], self::hash($code, $pending['guard'], $pending['id']))) {
            self::clear($request);

            return 'ok';
        }

        $pending['attempts']++;
        if ($pending['attempts'] >= self::MAX_ATTEMPTS) {
            self::clear($request);

            return 'too_many';
        }

        $request->session()->put(self::KEY, $pending);

        return 'wrong';
    }

    public static function attemptsLeft(Request $request): int
    {
        return max(0, self::MAX_ATTEMPTS - (int) ($request->session()->get(self::KEY)['attempts'] ?? 0));
    }

    /** Seconds until a new code may be sent (0 = now). */
    public static function resendWait(Request $request): int
    {
        $sentAt = (int) ($request->session()->get(self::KEY)['sent_at'] ?? 0);

        return max(0, $sentAt + self::RESEND_SECONDS - now()->getTimestamp());
    }

    /** Emails a new code (the old one stops working). */
    public static function sendCode(Request $request, Admin|DepartmentAccount $account): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $pending = $request->session()->get(self::KEY, []);
        $request->session()->put(self::KEY, array_merge($pending, [
            'hash' => self::hash($code, $pending['guard'], $account->getKey()),
            'expires_at' => now()->addMinutes(self::CODE_MINUTES)->getTimestamp(),
            'sent_at' => now()->getTimestamp(),
            'attempts' => 0,
        ]));

        $account->notify(new SignInCodeNotification($code, self::CODE_MINUTES, (string) $request->ip()));
    }

    public static function clear(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }

    /**
     * Keyed with the app key and tied to the account (and its type), so a
     * stored hash can't be reused for another account or guessed offline.
     */
    private static function hash(string $code, string $guard, int $id): string
    {
        return hash_hmac('sha256', $guard . '|' . $id . '|' . $code, (string) config('app.key'));
    }
}
