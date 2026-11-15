<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| The scheduler needs a cron job running `php artisan schedule:run` every
| minute on the server (`php artisan schedule:work` locally).
|
| Each task runs inside the scheduler's own PHP process (Artisan::call), not
| as a separate program: shared hosting such as Hostinger switches off PHP's
| proc_open, which Schedule::command() needs, so there every task failed
| before it began. A task that fails is still written to the error log.
*/
$task = fn (string $command) => Schedule::call(function () use ($command) {
    $exitCode = Artisan::call($command);

    if ($exitCode !== 0) {
        throw new RuntimeException($command . ' failed: ' . trim(Artisan::output()));
    }
})->name($command);

// Return reminder emails (due soon, Within the Day, hours left, overdue),
// checked every 15 minutes so same-day reminders arrive on time. The command
// itself only sends on office days during office hours, and never sends the
// same reminder twice.
$task('equiptrack:send-loan-reminders')
    ->everyFifteenMinutes()
    ->timezone(config('app.display_timezone'))
    ->withoutOverlapping();

// Daily backup of the database and uploaded files (config equiptrack.backup).
$task('equiptrack:backup')
    ->dailyAt(config('equiptrack.backup.time'))
    ->timezone(config('app.display_timezone'))
    ->withoutOverlapping();

// Daily cleanup of old audit entries (archived to CSV first) and proof files
// of long-closed penalties (config equiptrack.retention). Runs after the
// backup, so everything it removes is in that night's backup.
$task('equiptrack:prune-old-records')
    ->dailyAt(config('equiptrack.retention.time'))
    ->timezone(config('app.display_timezone'))
    ->withoutOverlapping();
