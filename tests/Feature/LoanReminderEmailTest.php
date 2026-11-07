<?php

namespace Tests\Feature;

use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\UserAccount;
use App\Notifications\LoanReminderNotification;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * equiptrack:send-loan-reminders, on the schedule in App\Support\LoanReminders:
 * one "due soon" email the day before (or on) the due date; Within the Day
 * loans get "return it today" when released and "2 hours left" before the
 * deadline; overdue emails on 1, 3 and 7 office days late, then weekly.
 * Emails only go out on office days during office hours (7:00 AM–7:00 PM),
 * and never twice. Dates are Philippine dates. Mail is faked; nothing is sent.
 *
 * Calendar used here: Oct 29, 2026 is a Thursday, Oct 31 a Saturday, Nov 1 a
 * Sunday (office closed), Nov 2 a Monday.
 */
class LoanReminderEmailTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private UserAccount $student;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->at('2026-10-31 09:00');

        $department = $this->makeDepartment();
        $this->student = $this->makeStudent($department, 'juan@example.com');
        $this->equipment = $this->makeEquipment($department);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Set the clock to a Philippine date and time. */
    private function at(string $philippineTime): void
    {
        Carbon::setTestNow(Carbon::parse($philippineTime, 'Asia/Manila'));
    }

    private function runReminders(array $options = []): string
    {
        Artisan::call('equiptrack:send-loan-reminders', $options);

        return Artisan::output();
    }

    private function sentTypes(?UserAccount $to = null): array
    {
        return Notification::sent($to ?? $this->student, LoanReminderNotification::class)
            ->map(fn (LoanReminderNotification $n) => $n->type . ($n->step ? ':' . $n->step : ''))
            ->values()
            ->all();
    }

    private function lastMail(?UserAccount $to = null)
    {
        $to ??= $this->student;

        return Notification::sent($to, LoanReminderNotification::class)->last()->toMail($to);
    }

    /** A Within the Day loan, released at the current time. */
    private function makeSameDayLoan(string $date): BorrowTransaction
    {
        return $this->makeLoan($this->student, $this->equipment, $date, ['released_at' => now()], $date);
    }

    public function test_items_due_tomorrow_or_today_get_one_due_soon_email(): void
    {
        $tomorrow = $this->makeLoan($this->student, $this->equipment, '2026-11-01');
        $other = $this->makeStudent($this->equipment->department, 'pedro@example.com', ['first_name' => 'Pedro']);
        $today = $this->makeLoan($other, $this->equipment, '2026-10-31');

        $this->assertStringContainsString('Sent 2 due-soon, 0 same-day and 0 overdue', $this->runReminders());

        $this->assertSame([LoanReminderNotification::DUE_SOON], $this->sentTypes());
        $this->assertSame([LoanReminderNotification::DUE_SOON], $this->sentTypes($other));
        $this->assertNotNull($tomorrow->fresh()->due_reminder_sent_at);
        $this->assertNotNull($today->fresh()->due_reminder_sent_at);

        // Running again (every 15 minutes) sends nothing new.
        $this->at('2026-10-31 09:15');
        $this->assertStringContainsString('Sent 0 due-soon, 0 same-day and 0 overdue', $this->runReminders());
        $this->assertCount(1, $this->sentTypes());
    }

    public function test_items_due_later_get_no_email_yet(): void
    {
        // Due in 3 days: only a bell reminder (long loans), no email.
        $this->makeLoan($this->student, $this->equipment, '2026-11-03');

        $this->runReminders();

        Notification::assertNothingSent();
    }

    public function test_overdue_emails_follow_the_steps_until_the_item_is_returned(): void
    {
        // Due Thu Oct 29 by 7:00 PM. Sat Oct 31 is 2 office days late: step 1.
        $loan = $this->makeLoan($this->student, $this->equipment, '2026-10-29');

        $this->runReminders();
        $this->assertSame(['overdue:1'], $this->sentTypes());
        $this->assertSame(1, $loan->fresh()->overdue_reminder_step);

        // Same step later that day: nothing new.
        $this->at('2026-10-31 15:00');
        $this->runReminders();
        $this->assertCount(1, $this->sentTypes());

        // Mon Nov 2: 3 office days late (Sunday doesn't count): step 3.
        $this->at('2026-11-02 08:00');
        $this->runReminders();
        $this->assertSame(['overdue:1', 'overdue:3'], $this->sentTypes());

        // Tue Nov 3: 4 days late, still step 3.
        $this->at('2026-11-03 08:00');
        $this->runReminders();
        $this->assertCount(2, $this->sentTypes());

        // Sat Nov 7: 8 days late, step 7: the final notice.
        $this->at('2026-11-07 08:00');
        $this->runReminders();
        $this->assertSame(['overdue:1', 'overdue:3', 'overdue:7'], $this->sentTypes());
        $mail = $this->lastMail();
        $this->assertSame('Final notice: please return Dell Latitude', $mail->subject);
        $this->assertStringContainsString('This has been reported to the Admin.', $mail->render());

        // Fri Nov 13: 13 days late, no new step yet; Sat Nov 14: 14 days, the weekly repeat.
        $this->at('2026-11-13 08:00');
        $this->runReminders();
        $this->assertCount(3, $this->sentTypes());
        $this->at('2026-11-14 08:00');
        $this->runReminders();
        $this->assertSame(['overdue:1', 'overdue:3', 'overdue:7', 'overdue:14'], $this->sentTypes());

        // Returned: no more reminders.
        $loan->update(['status' => 'Returned', 'return_date' => '2026-11-14']);
        $this->at('2026-11-30 08:00');
        $this->runReminders();
        $this->assertCount(4, $this->sentTypes());
    }

    public function test_the_first_overdue_email_waits_for_the_next_office_morning(): void
    {
        // Due Sat Oct 31 by 7:00 PM and not returned.
        $this->makeLoan($this->student, $this->equipment, '2026-10-31');
        $this->runReminders(); // 9:00 AM: the "due today" email
        $this->assertSame([LoanReminderNotification::DUE_SOON], $this->sentTypes());

        // 7:30 PM: overdue, but the office is closed for the night.
        $this->at('2026-10-31 19:30');
        $this->assertStringContainsString('Outside office hours', $this->runReminders());

        // Sunday: the office is closed all day.
        $this->at('2026-11-01 09:00');
        $this->assertStringContainsString('closed on Sundays', $this->runReminders());
        $this->assertCount(1, $this->sentTypes());

        // Monday 7:00 AM: the first overdue email.
        $this->at('2026-11-02 07:00');
        $this->runReminders();
        $this->assertSame([LoanReminderNotification::DUE_SOON, 'overdue:1'], $this->sentTypes());
        $this->assertStringContainsString('1 day overdue', $this->lastMail()->render());
    }

    public function test_within_the_day_loans_get_return_today_then_hours_left_then_overdue(): void
    {
        // Released Mon Nov 2 at 9:00 AM, due back the same day by 7:00 PM.
        $this->at('2026-11-02 09:00');
        $loan = $this->makeSameDayLoan('2026-11-02');

        $this->at('2026-11-02 09:15');
        $this->assertStringContainsString('Sent 0 due-soon, 1 same-day and 0 overdue', $this->runReminders());
        $this->assertSame([LoanReminderNotification::SAME_DAY], $this->sentTypes());
        $this->assertSame('Return Dell Latitude today by 7:00 PM', $this->lastMail()->subject);

        // Later in the morning: nothing new.
        $this->at('2026-11-02 11:00');
        $this->runReminders();
        $this->assertCount(1, $this->sentTypes());

        // 5:00 PM, two hours before the deadline.
        $this->at('2026-11-02 17:00');
        $this->runReminders();
        $this->assertSame([LoanReminderNotification::SAME_DAY, LoanReminderNotification::FINAL], $this->sentTypes());
        $this->assertSame('2 hours left: return Dell Latitude by 7:00 PM', $this->lastMail()->subject);
        $this->assertNotNull($loan->fresh()->final_reminder_sent_at);

        $this->at('2026-11-02 17:45');
        $this->runReminders();
        $this->assertCount(2, $this->sentTypes());

        // Not returned: no email at night, the overdue email next morning.
        $this->at('2026-11-02 19:30');
        $this->runReminders();
        $this->at('2026-11-03 07:00');
        $this->runReminders();
        $this->assertSame([LoanReminderNotification::SAME_DAY, LoanReminderNotification::FINAL, 'overdue:1'], $this->sentTypes());
    }

    public function test_within_the_day_loans_released_late_skip_the_hours_left_email(): void
    {
        // Released at 4:30 PM: less than 3 hours before the deadline.
        $this->at('2026-11-02 16:30');
        $this->makeSameDayLoan('2026-11-02');

        $this->at('2026-11-02 16:45');
        $this->runReminders();
        $this->at('2026-11-02 17:15');
        $this->runReminders();

        $this->assertSame([LoanReminderNotification::SAME_DAY], $this->sentTypes());
    }

    public function test_no_email_when_the_return_is_being_confirmed_or_the_account_is_deactivated(): void
    {
        $this->makeLoan($this->student, $this->equipment, '2026-10-29', [
            'return_status' => BorrowTransaction::RETURN_PENDING,
            'return_submitted_at' => now(),
        ]);
        $inactive = $this->makeStudent($this->equipment->department, 'inactive@example.com', ['status' => 'Deactivated']);
        $this->makeLoan($inactive, $this->equipment, '2026-10-29');

        $this->runReminders();

        Notification::assertNothingSent();
    }

    public function test_dry_run_lists_reminders_without_sending_or_recording_them(): void
    {
        $loan = $this->makeLoan($this->student, $this->equipment, '2026-10-29');

        $output = $this->runReminders(['--dry-run' => true]);

        $this->assertStringContainsString('Would send 0 due-soon, 0 same-day and 1 overdue', $output);
        $this->assertStringContainsString('juan@example.com', $output);
        Notification::assertNothingSent();
        $this->assertNull($loan->fresh()->overdue_reminder_sent_at);
        $this->assertNull($loan->fresh()->overdue_reminder_step);
    }

    public function test_emails_wait_for_office_hours_on_the_philippine_date(): void
    {
        // 2:00 AM on Oct 31 in the Philippines is still Oct 30 on the UTC server clock,
        // and before office hours: nothing yet.
        $this->at('2026-10-31 02:00');
        $this->assertSame('2026-10-30', now()->toDateString());

        $this->makeLoan($this->student, $this->equipment, '2026-10-30');
        $this->assertStringContainsString('Outside office hours', $this->runReminders());
        Notification::assertNothingSent();

        // 7:00 AM: one office day late.
        $this->at('2026-10-31 07:00');
        $this->runReminders();
        $this->assertSame(['overdue:1'], $this->sentTypes());
        $this->assertStringContainsString('1 day overdue', $this->lastMail()->render());
    }

    public function test_the_email_names_the_item_the_dates_and_links_to_return_item(): void
    {
        $this->makeLoan($this->student, $this->equipment, '2026-10-29', [], '2026-10-24');
        $this->runReminders();

        $mail = $this->lastMail();
        $this->assertSame('Overdue: please return Dell Latitude', $mail->subject);

        $html = $mail->render();
        $this->assertStringContainsString('Hi Juan Dela Cruz', $html);
        $this->assertStringContainsString('2 days overdue', $html);
        $this->assertStringContainsString('Oct 29, 2026', $html);
        $this->assertStringContainsString('Oct 24, 2026', $html);
        $this->assertStringContainsString(route('user.returns'), $html);
    }

    public function test_the_due_soon_email_wording(): void
    {
        $this->makeLoan($this->student, $this->equipment, '2026-11-01');
        $this->runReminders();

        $mail = $this->lastMail();
        $this->assertSame('Reminder: Dell Latitude is due tomorrow', $mail->subject);
        $this->assertStringContainsString('due tomorrow', $mail->render());
    }

    public function test_reminders_are_checked_every_15_minutes_in_philippine_time(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'equiptrack:send-loan-reminders'));

        $this->assertNotNull($event);
        $this->assertSame('*/15 * * * *', $event->expression);
        $this->assertSame('Asia/Manila', $event->timezone);
    }
}
