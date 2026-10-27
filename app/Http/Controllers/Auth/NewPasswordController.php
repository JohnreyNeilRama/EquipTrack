<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\PasswordResetAccounts;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * Reset Password: the page the reset email links to (/reset-password/{token}).
 *
 * The token is checked by the account type's password broker: it must match
 * the hash stored for that email, be under 30 minutes old, and is deleted once
 * used, so a link works only once. After a reset the account's "remember me"
 * token is replaced and the auth.session middleware signs out every other
 * device on its next request. The user is not signed in automatically; they
 * log in with the new password.
 */
class NewPasswordController extends Controller
{
    private const MAX_ATTEMPTS_PER_MINUTE = 10;

    private const INVALID_LINK = 'This password reset link is invalid or has expired. Request a new one below.';

    public function show(Request $request, string $token): View
    {
        $email = trim((string) $request->query('email', ''));
        $found = PasswordResetAccounts::find($email);

        // Check the link up front so an expired or used one says so right away.
        $valid = $found
            && !PasswordResetAccounts::isDeactivated($found['account'])
            && Password::broker($found['broker'])->tokenExists($found['account'], $token);

        return view('auth.reset-password', [
            'token' => $token,
            'email' => $email,
            'linkValid' => $valid,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Per-visitor limit, answered on the page itself.
        $limitKey = 'password-reset-submit|' . $request->ip();
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
            'password.required' => 'Enter a new password.',
            'password.min' => 'Your new password must be at least :min characters.',
            'password.letters' => 'Your new password must include at least one letter.',
            'password.numbers' => 'Your new password must include at least one number.',
            'password.confirmed' => 'New password and confirm password do not match.',
        ]);

        $found = PasswordResetAccounts::find($data['email']);
        if (!$found || PasswordResetAccounts::isDeactivated($found['account'])) {
            return $this->invalidLink();
        }
        ['broker' => $broker, 'guard' => $guard, 'account' => $account] = $found;

        // Check the link before looking at the password, so a wrong or expired
        // link never reveals anything about the account.
        if (!Password::broker($broker)->tokenExists($account, $data['token'])) {
            return $this->invalidLink();
        }

        if (Hash::check($data['password'], $account->getAuthPassword())) {
            return back()->withErrors(['password' => 'Your new password must be different from your current password.']);
        }

        $status = Password::broker($broker)->reset(
            [
                'email' => $account->getEmailForPasswordReset(),
                'token' => $data['token'],
                'password' => $data['password'],
                'password_confirmation' => $data['password'],
            ],
            function ($account, string $password) {
                $account->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($account));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->invalidLink();
        }

        AuditLogger::log(
            'Reset password via email link',
            PasswordResetAccounts::entity($guard, $account),
            null,
            AuditLogger::actorFor($guard, $account),
            $request,
        );

        return redirect()->route('login')
            ->with('status', 'Your password has been reset. Log in with your new password.');
    }

    private function invalidLink(): RedirectResponse
    {
        return back()->with('link_invalid', true)->withErrors(['email' => self::INVALID_LINK]);
    }
}
