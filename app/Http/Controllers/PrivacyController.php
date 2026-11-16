<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\DepartmentAccount;
use App\Support\PrivacyConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The Privacy Notice (Data Privacy Act of 2012): the public page, and the
 * one-time "please agree" page for signed-in accounts that haven't agreed to
 * the current version (App\Http\Middleware\EnsurePrivacyAccepted).
 */
class PrivacyController extends Controller
{
    public function notice(): View
    {
        return view('privacy');
    }

    public function acceptForm(): View|RedirectResponse
    {
        $signedIn = PrivacyConsent::signedIn();
        if (!$signedIn) {
            return redirect()->route('login');
        }

        [$guard, $account] = $signedIn;
        if (PrivacyConsent::hasAccepted($account)) {
            return redirect()->intended($this->home($guard));
        }

        return view('auth.privacy-accept', [
            'name' => $this->firstName($account),
            'isUpdate' => $account->privacy_version !== null,
        ]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $signedIn = PrivacyConsent::signedIn();
        if (!$signedIn) {
            return redirect()->route('login');
        }

        $request->validate(['agree' => ['accepted']], [
            'agree.accepted' => 'Please tick the box to agree to the Privacy Notice, or sign out.',
        ]);

        [$guard, $account] = $signedIn;
        if (!PrivacyConsent::hasAccepted($account)) {
            PrivacyConsent::record($guard, $account, 'at sign-in', $request);
        }

        return redirect()->intended($this->home($guard));
    }

    private function home(string $guard): string
    {
        return match ($guard) {
            'admin' => route('admin.dashboard'),
            'dept' => route('department.dashboard'),
            default => route('user.dashboard'),
        };
    }

    private function firstName($account): string
    {
        $name = match (true) {
            $account instanceof Admin => (string) $account->name,
            $account instanceof DepartmentAccount => (string) $account->full_name,
            default => (string) $account->fullName(),
        };

        return trim(explode(' ', trim($name))[0] ?? '') ?: 'there';
    }
}
