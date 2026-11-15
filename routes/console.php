<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Return reminder emails (due soon, Within the Day, hours left, overdue),
// checked every 15 minutes so same-day reminders arrive on time. The command
// itself only sends on office days during office hours, and never sends the
// same reminder twice. Needs the scheduler to run every minute: a cron job on
// the server (`php artisan schedule:run`), or `php artisan schedule:work`
// locally.
Schedule::command('equiptrack:send-loan-reminders')
    ->everyFifteenMinutes()
    ->timezone(config('app.display_timezone'))
    ->withoutOverlapping();

// Daily backup of the database and uploaded files (config equiptrack.backup).
// Like the reminders, it needs the scheduler running at that time.
Schedule::command('equiptrack:backup')
    ->dailyAt(config('equiptrack.backup.time'))
    ->timezone(config('app.display_timezone'))
    ->withoutOverlapping();

// Daily cleanup of old audit entries (archived to CSV first) and proof files
// of long-closed penalties (config equiptrack.retention). Runs after the
// backup, so everything it removes is in that night's backup.
Schedule::command('equiptrack:prune-old-records')
    ->dailyAt(config('equiptrack.retention.time'))
    ->timezone(config('app.display_timezone'))
    ->withoutOverlapping();
