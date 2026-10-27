<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\PasswordResetAccounts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Timebox;
use Illuminate\View\View;

/**
 * Forgot Password: emails a reset link for any account type.
 *
 * The account is found by PasswordResetAccounts (same order as the login
 * form). The link is sent by that account type's broker (config/auth.php),
 * which also enforces the 60-second wait between emails.
 *
 * Every request gets the same message and takes at least the same time,
 * whether or not an account exists, so the form cannot be used to find out
 * which emails are registered. Deactivated accounts get no email.
 */
class PasswordResetLinkController extends Controller
{
    /** Minimum time per request, so "no account" and "email sent" look alike. */
    private const MIN_RESPONSE_MICROSECONDS = 1_000_000;

    private const MAX_REQUESTS_PER_MINUTE = 5;

    public function show(): View|RedirectResponse
    {
        foreach (['admin' => 'admin.dashboard', 'dept' => 'department.dashboard', 'user' => 'user.dashboard'] as $guard => $route) {
            if (auth($guard)->check()) {
                return redirect()->route($route);
            }
        }

        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:100'],
        ], [
            'email.required' => 'Enter the email address for your account.',
            'email.email' => 'Enter a valid email address.',
        ]);

        // Per-visitor limit, answered on the page itself (not a bare 429 error).
        // Each account also has a 60-second wait between emails (config/auth.php).
        $limitKey = 'password-reset-request|' . $request->ip();
        if (RateLimiter::tooManyAttempts($limitKey, self::MAX_REQUESTS_PER_MINUTE)) {
            return back()->withInput()->withErrors([
                'email' => 'Too many requests. Please wait ' . RateLimiter::availableIn($limitKey) . ' seconds and try again.',
            ]);
        }
        RateLimiter::hit($limitKey, 60);

        $email = trim($data['email']);

        (new Timebox)->call(function () use ($email, $request) {
            $found = PasswordResetAccounts::find($email);

            if (!$found || PasswordResetAccounts::isDeactivated($found['account'])) {
                return;
            }
            ['broker' => $broker, 'guard' => $guard, 'account' => $account] = $found;

            try {
                $status = Password::broker($broker)->sendResetLink(['email' => $account->getEmailForPasswordReset()]);
            } catch (\Throwable $e) {
                // The mail server is down or misconfigured. Log it for the
                // developers; the visitor still gets the same message.
                report($e);

                return;
            }

            if ($status === Password::RESET_LINK_SENT) {
                AuditLogger::log(
                    'Requested password reset',
                    PasswordResetAccounts::entity($guard, $account),
                    'Reset link emailed to ' . $account->getEmailForPasswordReset(),
                    AuditLogger::actorFor($guard, $account),
                    $request,
                );
            }
        }, self::MIN_RESPONSE_MICROSECONDS);

        return redirect()->route('password.request')
            ->with('status', "If an EquipTrack account uses that email, we've sent it a password reset link. Check your inbox and spam folder. The link expires in 30 minutes.");
    }
}
