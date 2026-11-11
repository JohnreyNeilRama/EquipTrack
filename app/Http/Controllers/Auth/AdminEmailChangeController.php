<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AdminEmailChange;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The page the "This wasn't me: undo this change" link opens (see
 * App\Services\AdminEmailChange). Opening the link only shows what will
 * happen; the change is undone when the owner clicks the button, so email
 * scanners that open links automatically cannot trigger it.
 */
class AdminEmailChangeController extends Controller
{
    public function show(Request $request): View
    {
        return $this->page($request);
    }

    public function undo(Request $request): View
    {
        $change = $this->change($request);

        if (!$change || $change['stale']) {
            return $this->page($request);
        }

        $error = AdminEmailChange::undo($change, $request);

        return view('auth.email-change-undo', [
            'state' => $error ? 'failed' : 'done',
            'message' => $error,
            'oldEmail' => $change['old'],
        ]);
    }

    private function page(Request $request): View
    {
        $change = $this->change($request);

        return view('auth.email-change-undo', [
            'state' => !$change ? 'invalid' : ($change['stale'] ? 'stale' : 'confirm'),
            'message' => null,
            'oldEmail' => $change['old'] ?? null,
            'newEmail' => $change ? AdminEmailChange::mask($change['new']) : null,
            'action' => $request->fullUrl(),
        ]);
    }

    /** The change the link is about, or null when it is broken or expired. */
    private function change(Request $request): ?array
    {
        if (!$request->hasValidSignature()) {
            return null;
        }

        return AdminEmailChange::read($request->query('t'));
    }
}
