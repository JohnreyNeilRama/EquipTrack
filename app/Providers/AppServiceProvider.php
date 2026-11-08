<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Live server (APP_ENV=production):
        // - Links, form actions and downloads always use https://, even if
        //   APP_URL was left on http:// (a download link on http:// from an
        //   https:// page is blocked by the browser as insecure).
        // - Debug mode stays off even if APP_DEBUG=true was left in .env: debug
        //   error pages show code, settings and passwords to any visitor.
        //   The mistake is logged so it can be fixed in .env.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');

            if (config('app.debug')) {
                config(['app.debug' => false]);
                Log::warning('APP_DEBUG=true was ignored because APP_ENV=production. Set APP_DEBUG=false in .env.');
            }
        }

        // One password policy for every account type (Student/Faculty, Admin,
        // Laboratory Personnel). Used wherever a password is set or changed via
        // Password::defaults(); existing passwords keep working.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        // Named rate limits keyed by account type + id. A plain throttle:5,1 keys
        // by id alone, and user #1, admin #1 and department account #1 would
        // share one counter.
        RateLimiter::for('change-password', fn (Request $request) => Limit::perMinute(5)->by(self::accountKey($request)));
        RateLimiter::for('profile-update', fn (Request $request) => Limit::perMinute(10)->by(self::accountKey($request)));
    }

    /** The signed-in account (guard set by the auth middleware), or the IP for guests. */
    private static function accountKey(Request $request): string
    {
        $account = $request->user();

        return $account
            ? Auth::getDefaultDriver() . '|' . $account->getAuthIdentifier()
            : 'ip|' . $request->ip();
    }
}
