<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * On the live server (APP_ENV=production), a visit over plain http:// is sent
 * to the same address on https:// (301, permanent). Passwords, session
 * cookies and downloaded files then always travel encrypted, and browsers
 * don't block PDF/CSV downloads as insecure.
 *
 * Local development (APP_ENV=local) is unchanged. Behind Cloudflare or
 * another HTTPS proxy, set TRUSTED_PROXIES so Laravel can tell the visitor
 * used https (config/trustedproxy.php).
 */
class RedirectToHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && !$request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
