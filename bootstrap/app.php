<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Env;

// Read .env into each request's own memory instead of PHP's process-wide
// putenv() store. On a multi-threaded Windows Apache (Laragon), one request
// finishing can clear those shared values while another request is still
// running, which randomly surfaced as "No application encryption key has been
// specified" (logged as env "production") on hard refreshes and AJAX polling.
Env::disablePutenv();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active' => \App\Http\Middleware\EnsureAccountActive::class,
            'expire.requests' => \App\Http\Middleware\ExpireStaleRequests::class,
            'approved' => \App\Http\Middleware\EnsureFacultyApproved::class,
        ]);
        // Unauthenticated hits on protected routes go to our login page.
        $middleware->redirectGuestsTo(fn () => route('login'));
        // Live server only: plain http:// visits go to https:// (see the class).
        // Appended, so it runs after TrustProxies has read the proxy headers.
        $middleware->append(\App\Http\Middleware\RedirectToHttps::class);
        // Browser protections on every response (clickjacking, file types,
        // referrer, permissions, HTTPS memory). See the class.
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Login and Register sent too often from one device (route throttle):
        // back to the form with a message, instead of the bare "429 Too Many
        // Requests" page. (Forgot / Reset Password have their own limit with
        // a message on the page, in their controllers.)
        $exceptions->render(function (\Illuminate\Http\Exceptions\ThrottleRequestsException $e, Request $request) {
            if ($request->expectsJson() || !$request->isMethod('post')
                || !$request->is('login', 'register')) {
                return null;
            }

            $seconds = max(1, (int) ($e->getHeaders()['Retry-After'] ?? 60));
            $wait = $seconds >= 60 ? 'a minute' : $seconds . ' seconds';

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors(['email' => "Too many requests. Please wait {$wait} and try again."]);
        });
    })->create();
