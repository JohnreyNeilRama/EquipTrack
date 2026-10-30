<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Due-soon and overdue reminder emails, every morning (Philippine time).
// Needs the scheduler to run every minute: a cron job on the server
// (`php artisan schedule:run`), or `php artisan schedule:work` locally.
Schedule::command('equiptrack:send-loan-reminders')
    ->dailyAt('07:00')
    ->timezone(config('app.display_timezone'))
    ->withoutOverlapping();
