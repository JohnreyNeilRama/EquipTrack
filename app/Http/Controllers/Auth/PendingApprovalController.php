<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Waiting for approval" page for self-registered Faculty whose email is
 * verified but whose department hasn't approved the account yet
 * (see EnsureFacultyApproved).
 */
class PendingApprovalController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        $account = $request->user('user');

        if (!$account->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (!$account->isPendingApproval()) {
            return redirect()->route('user.dashboard');
        }

        return view('auth.pending-approval', [
            'email' => $account->email,
            'department' => $account->department?->department_name ?? 'your department',
        ]);
    }
}
