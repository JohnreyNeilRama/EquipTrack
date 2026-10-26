<?php

namespace App\Providers;

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
        // One password policy for every account type (Student/Faculty, Admin,
        // Laboratory Personnel). Used wherever a password is set or changed via
        // Password::defaults(); existing passwords keep working.
        Password::defaults(fn () => Password::min(8)->letters()->numbers());
    }
}
