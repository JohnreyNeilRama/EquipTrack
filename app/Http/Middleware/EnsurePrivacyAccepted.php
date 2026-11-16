<?php

namespace App\Http\Middleware;

use App\Support\PrivacyConsent;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signed-in pages (Admin, Department, Students & Faculty) open only once the
 * account has agreed to the current Privacy Notice. Accounts created before
 * the notice existed, or that agreed to an older version, are sent once to
 * the "please agree" page, then back to where they were going.
 */
class EnsurePrivacyAccepted
{
    public function handle(Request $request, Closure $next): Response
    {
        $signedIn = PrivacyConsent::signedIn();

        if ($signedIn && !PrivacyConsent::hasAccepted($signedIn[1])) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please read and agree to the Privacy Notice first.',
                    'redirect' => route('privacy.accept'),
                ], 403);
            }

            if ($request->isMethod('GET')) {
                redirect()->setIntendedUrl($request->fullUrl());
            }

            return redirect()->route('privacy.accept');
        }

        return $next($request);
    }
}
