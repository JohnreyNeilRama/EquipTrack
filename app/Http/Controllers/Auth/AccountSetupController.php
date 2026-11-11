<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use App\Services\AuditLogger;
use App\Services\PasswordResetAccounts;
use App\Services\UserInvites;
use App\Support\LoginLockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Set Your Password: the page the invite email links to
 * (/account/setup/{token}?email=...), for accounts added by someone else:
 * Students and Faculty added by staff, and Department (Lab Personnel) and
 * Admin accounts added by an Admin. The token is checked by the account
 * type's invite broker (hashed, 7 days, single use). Choosing the password
 * also confirms the email address, since only the inbox owner has the link.
 * The person then logs in.
 */
class AccountSetupController extends Controller
{
    private const MAX_ATTEMPTS_PER_MINUTE = 10;

    private const INVALID_LINK = 'This invite link is invalid, was already used or has expired. Ask the laboratory or the Admin to send a new one.';

    public function show(Request $request, string $token): View
    {
        $email = trim((string) $request->query('email', ''));
        $found = $this->invited($email);
        $account = $found['account'] ?? null;

        return view('auth.account-setup', [
            'token' => $token,
            'email' => $email,
            'name' => $account ? (self::displayName($account) ?: null) : null,
            'linkValid' => $account && Password::broker(UserInvites::broker($account))->tokenExists($account, $token),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $limitKey = 'account-setup|' . $request->ip();
        if (RateLimiter::tooManyAttempts($limitKey, self::MAX_ATTEMPTS_PER_MINUTE)) {
            return back()->withErrors([
                'password' => 'Too many attempts. Please wait ' . RateLimiter::availableIn($limitKey) . ' seconds and try again.',
            ]);
        }
        RateLimiter::hit($limitKey, 60);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', PasswordRule::defaults(), 'confirmed'],
        ], [
            'password.required' => 'Choose a password.',
            'password.min' => 'Your password must be at least :min characters.',
            'password.letters' => 'Your password must include at least one letter.',
            'password.numbers' => 'Your password must include at least one number.',
            'password.confirmed' => 'Password and confirm password do not match.',
        ]);

        $found = $this->invited($data['email']);
        $account = $found['account'] ?? null;
        $broker = $account ? Password::broker(UserInvites::broker($account)) : null;

        if (!$account || !$broker->tokenExists($account, $data['token'])) {
            return back()->with('link_invalid', true)->withErrors(['email' => self::INVALID_LINK]);
        }

        $status = $broker->reset(
            [
                'email' => $account->email,
                'token' => $data['token'],
                'password' => $data['password'],
                'password_confirmation' => $data['password'],
            ],
            function ($account, string $password) {
                $account->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                    'email_verified_at' => now(),
                ])->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->with('link_invalid', true)->withErrors(['email' => self::INVALID_LINK]);
        }

        // Admins can also sign in with their username.
        LoginLockout::clear($account->email, $account instanceof Admin ? $account->username : null);

        AuditLogger::log(
            'Set password from invite',
            PasswordResetAccounts::entity($found['guard'], $account),
            'Email address confirmed: ' . $account->email,
            AuditLogger::actorFor($found['guard'], $account),
            $request,
        );

        return redirect()->route('login')
            ->with('status', 'Your password is set and your email is confirmed. Log in with your new password.');
    }

    /**
     * The invited account for this email (any account type): not deactivated
     * and not set up yet.
     *
     * @return array{account: UserAccount|DepartmentAccount|Admin, guard: string}|null
     */
    private function invited(string $email): ?array
    {
        $found = PasswordResetAccounts::find($email);
        if (!$found) {
            return null;
        }

        $account = $found['account'];

        return !UserInvites::isSetUp($account) && !PasswordResetAccounts::isDeactivated($account)
            ? ['account' => $account, 'guard' => $found['guard']]
            : null;
    }

    private static function displayName(UserAccount|DepartmentAccount|Admin $account): string
    {
        return trim((string) match (true) {
            $account instanceof Admin => $account->name,
            $account instanceof DepartmentAccount => $account->full_name,
            default => $account->fullName(),
        });
    }
}
