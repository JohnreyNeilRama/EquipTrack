<?php

namespace Tests\Feature;

use App\Notifications\LoanReminderNotification;
use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * The scheduled tasks (routes/console.php) run inside the scheduler's own PHP
 * process. On Hostinger PHP may not start other programs (proc_open is off),
 * and Schedule::command() needs that: there every reminder, backup and
 * cleanup failed before it began.
 */
class ScheduledTasksTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    private function event(string $name): CallbackEvent
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === $name);
        $this->assertInstanceOf(CallbackEvent::class, $event, "$name is not scheduled to run inside PHP");

        return $event;
    }

    public function test_no_task_needs_to_start_another_program(): void
    {
        $events = app(Schedule::class)->events();

        $this->assertCount(3, $events);
        foreach ($events as $event) {
            $this->assertInstanceOf(CallbackEvent::class, $event, ($event->command ?? $event->description) . ' would need proc_open');
        }
    }

    public function test_each_task_keeps_its_time_in_philippine_time(): void
    {
        $this->assertSame('*/15 * * * *', $this->event('equiptrack:send-loan-reminders')->expression);
        $this->assertSame('30 19 * * *', $this->event('equiptrack:backup')->expression);
        $this->assertSame('0 20 * * *', $this->event('equiptrack:prune-old-records')->expression);

        foreach (['equiptrack:send-loan-reminders', 'equiptrack:backup', 'equiptrack:prune-old-records'] as $name) {
            $this->assertSame('Asia/Manila', $this->event($name)->timezone);
            $this->assertTrue($this->event($name)->withoutOverlapping, "$name may run twice at once");
        }
    }

    public function test_the_scheduler_really_sends_the_reminders(): void
    {
        Notification::fake();
        // Saturday Oct 31, 2026, 9:15 AM: an office day, on a 15-minute mark.
        Carbon::setTestNow(Carbon::parse('2026-10-31 09:15', 'Asia/Manila'));

        $department = $this->makeDepartment();
        $student = $this->makeStudent($department, 'juan@example.com');
        $this->makeLoan($student, $this->makeEquipment($department), '2026-11-01');

        Artisan::call('schedule:run');

        Notification::assertSentTo($student, LoanReminderNotification::class);
    }

    public function test_a_task_that_fails_is_reported(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('equiptrack:backup failed');

        // No SQLite file to copy in the test database: the backup fails.
        config(['database.connections.sqlite.database' => storage_path('framework/testing/no-such-database.sqlite')]);
        config(['equiptrack.backup.path' => storage_path('framework/testing/backups-' . uniqid())]);

        // The scheduler runs it like this, and reports what it throws to the error log.
        $this->event('equiptrack:backup')->run(app());
    }

    public function test_the_production_check_flags_tasks_that_would_need_another_program(): void
    {
        $this->artisan('equiptrack:check-production')
            ->expectsOutputToContain('Scheduled tasks can run on this server')
            ->doesntExpectOutputToContain('so these would fail');
    }
}
