<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Blocks accounts whose status column is 'Deactivated' — the legacy
// auth_check.php files enforced this at every protected page load.
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['user', 'dept'] as $guard) {
            $account = auth($guard)->user();
            if ($account && strtolower((string) $account->status) === 'deactivated') {
                auth($guard)->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect('/login?error=deactivated');
            }
        }

        return $next($request);
    }
}
