<?php

namespace App\Http\Middleware;

use App\Models\UserAccount;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Self-registered Faculty can sign in, but can't use the /user pages until a
 * Lab Personnel member of their department approves the account
 * (DepartmentUsersController::approve). Until then they see the "waiting for
 * approval" page. Runs after "verified", so the email is confirmed first.
 */
class EnsureFacultyApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $account = auth('user')->user();

        if ($account instanceof UserAccount && $account->isPendingApproval()) {
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => 'Your Faculty account is waiting for your department\'s approval.'], 403)
                : redirect()->route('account.pending');
        }

        return $next($request);
    }
}
