<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use App\Services\AuditLogger;
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
 * (/account/setup/{token}?email=...), for Student and Faculty accounts added by
 * staff. The token is checked by the "user_invites" broker (hashed, 7 days,
 * single use). Choosing the password also confirms the email address, since
 * only the inbox owner has the link. The person then logs in.
 */
class AccountSetupController extends Controller
{
    private const MAX_ATTEMPTS_PER_MINUTE = 10;

    private const INVALID_LINK = 'This invite link is invalid, was already used or has expired. Ask the laboratory or the Admin to send a new one.';

    public function show(Request $request, string $token): View
    {
        $email = trim((string) $request->query('email', ''));
        $account = $this->invited($email);

        return view('auth.account-setup', [
            'token' => $token,
            'email' => $email,
            'name' => $account?->fullName() ?: null,
            'linkValid' => $account && Password::broker('user_invites')->tokenExists($account, $token),
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

        $account = $this->invited($data['email']);
        $broker = Password::broker('user_invites');

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
            function (UserAccount $account, string $password) {
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

        LoginLockout::clear($account->email);

        AuditLogger::log(
            'Set password from invite',
            'user_account:' . $account->user_id,
            'Email address confirmed: ' . $account->email,
            AuditLogger::actorFor('user', $account),
            $request,
        );

        return redirect()->route('login')
            ->with('status', 'Your password is set and your email is confirmed. Log in with your new password.');
    }

    /** The invited account for this email: not deactivated and not set up yet. */
    private function invited(string $email): ?UserAccount
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        $account = UserAccount::where('email', $email)->first();

        return $account
            && !$account->hasVerifiedEmail()
            && strcasecmp((string) $account->status, UserAccount::STATUS_DEACTIVATED) !== 0
            ? $account
            : null;
    }
}
