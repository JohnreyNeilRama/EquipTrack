<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Standard browser protections on every response (pages, JSON, downloads,
 * proof files):
 *
 * - X-Frame-Options / CSP frame-ancestors: other websites can't show
 *   EquipTrack inside a frame to trick people into clicking (clickjacking).
 * - X-Content-Type-Options: files are only treated as the type the server
 *   says, so an uploaded file can't be run as a script or page.
 * - Referrer-Policy: other sites only see "equiptrack" as the referrer, not
 *   full addresses with IDs and filters.
 * - Permissions-Policy: camera, microphone, location and payment features
 *   are switched off (EquipTrack uses none of them).
 * - Content-Security-Policy (limited): forms only submit to EquipTrack, no
 *   <base> tag or plugin tricks. Scripts and styles are not restricted,
 *   because the pages use inline scripts and fonts from cdnjs/Google.
 * - Strict-Transport-Security (live server over HTTPS only): browsers
 *   remember to use HTTPS for a year.
 *
 * A header a response already sets (e.g. nosniff on proof files) is kept.
 */
class SecurityHeaders
{
    private const HEADERS = [
        'X-Frame-Options' => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        'Content-Security-Policy' => "frame-ancestors 'self'; base-uri 'self'; form-action 'self'; object-src 'none'",
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        foreach (self::HEADERS as $name => $value) {
            if (!$response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        if (app()->isProduction() && $request->secure() && !$response->headers->has('Strict-Transport-Security')) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
