<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use App\Services\AuditLogger;
use App\Support\LoginLockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Email verification for Students and Faculty who registered themselves.
 *
 * - notice: "Check your email" page. The /user pages send unverified
 *   accounts here (the "verified" middleware on that route group).
 * - verify: the signed link from VerifyEmailNotification. Works whether or
 *   not the person is signed in (they may open it on their phone).
 * - resend: sends a new link (rate-limited on the route).
 *
 * After verifying, self-registered Faculty still wait for their department's
 * approval (EnsureFacultyApproved, Auth\PendingApprovalController).
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        $account = $request->user('user');

        if ($account->hasVerifiedEmail()) {
            return redirect()->route('user.dashboard');
        }

        return view('auth.verify-email', [
            'email' => $account->email,
            'faculty' => $account->isPendingApproval(),
        ]);
    }

    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $account = UserAccount::find($id);

        $valid = $account
            && $request->hasValidSignature()
            && hash_equals(sha1($account->getEmailForVerification()), $hash);

        if (!$valid) {
            $message = 'This verification link is invalid or has expired. Sign in to send a new one.';

            return auth('user')->check()
                ? redirect()->route('verification.notice')->with('error', $message)
                : redirect()->route('login')->withErrors(['email' => $message]);
        }

        if (!$account->hasVerifiedEmail()) {
            $account->markEmailAsVerified();

            AuditLogger::log(
                'Verified email address',
                'user_account:' . $account->user_id,
                $account->email,
                AuditLogger::actorFor('user', $account),
                $request,
            );
        }

        $status = $account->isPendingApproval()
            ? 'Your email is verified. Your department will review your Faculty account; we will email you once it is approved.'
            : 'Your email is verified. You can now use EquipTrack.';

        // Signed in as this account: straight on. Otherwise: log in.
        if ((int) auth('user')->id() === (int) $account->user_id) {
            return redirect()->route('user.dashboard')->with('status', $status);
        }

        LoginLockout::clear($account->email);

        return redirect()->route('login')->with('status', $status . ' Log in below.');
    }

    public function resend(Request $request): RedirectResponse
    {
        $account = $request->user('user');

        if ($account->hasVerifiedEmail()) {
            return redirect()->route('user.dashboard');
        }

        try {
            $account->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', "We couldn't send the email just now. Please try again in a few minutes.");
        }

        return back()->with('status', 'A new verification link was sent to ' . $account->email . '.');
    }
}
