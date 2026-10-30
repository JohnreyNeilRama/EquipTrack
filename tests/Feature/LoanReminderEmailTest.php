<?php

namespace Tests\Feature;

use App\Console\Commands\SendLoanReminders;
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
 * equiptrack:send-loan-reminders: one "due soon" email the day before (or on)
 * the due date, then an overdue email repeated every few days until the item
 * is returned. Dates are Philippine dates. Mail is faked; nothing is sent.
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
        // 9:00 AM on Oct 31, 2026, Philippine time.
        Carbon::setTestNow(Carbon::parse('2026-10-31 09:00', 'Asia/Manila'));

        $department = $this->makeDepartment();
        $this->student = $this->makeStudent($department, 'juan@example.com');
        $this->equipment = $this->makeEquipment($department);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function runReminders(array $options = []): string
    {
        Artisan::call('equiptrack:send-loan-reminders', $options);

        return Artisan::output();
    }

    private function assertReminder(BorrowTransaction $loan, string $type, int $times = 1, ?UserAccount $to = null): void
    {
        Notification::assertSentToTimes($to ?? $this->student, LoanReminderNotification::class, $times);
        Notification::assertSentTo($to ?? $this->student, LoanReminderNotification::class,
            fn (LoanReminderNotification $n) => $n->loan->transaction_id === $loan->transaction_id && $n->type === $type);
    }

    public function test_items_due_tomorrow_or_today_get_one_due_soon_email(): void
    {
        $tomorrow = $this->makeLoan($this->student, $this->equipment, '2026-11-01');
        $other = $this->makeStudent($this->equipment->department, 'pedro@example.com', ['first_name' => 'Pedro']);
        $today = $this->makeLoan($other, $this->equipment, '2026-10-31');

        $this->assertStringContainsString('Sent 2 due-soon and 0 overdue', $this->runReminders());

        $this->assertReminder($tomorrow, LoanReminderNotification::DUE_SOON);
        $this->assertReminder($today, LoanReminderNotification::DUE_SOON, 1, $other);
        $this->assertNotNull($tomorrow->fresh()->due_reminder_sent_at);

        // Running again the same day sends nothing new.
        $this->assertStringContainsString('Sent 0 due-soon and 0 overdue', $this->runReminders());
        Notification::assertSentToTimes($this->student, LoanReminderNotification::class, 1);
    }

    public function test_items_due_later_get_no_email_yet(): void
    {
        $this->makeLoan($this->student, $this->equipment, '2026-11-03');

        $this->runReminders();

        Notification::assertNothingSent();
    }

    public function test_overdue_items_are_reminded_again_every_few_days_until_returned(): void
    {
        $loan = $this->makeLoan($this->student, $this->equipment, '2026-10-29');

        $this->runReminders();
        $this->assertReminder($loan, LoanReminderNotification::OVERDUE);

        // Two days later: too soon for another reminder.
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Asia/Manila'));
        $this->runReminders();
        Notification::assertSentToTimes($this->student, LoanReminderNotification::class, 1);

        // After the repeat interval: reminded again.
        Carbon::setTestNow(Carbon::parse('2026-10-31 09:00', 'Asia/Manila')->addDays(SendLoanReminders::OVERDUE_REPEAT_DAYS));
        $this->runReminders();
        Notification::assertSentToTimes($this->student, LoanReminderNotification::class, 2);

        // Returned: no more reminders.
        $loan->update(['status' => 'Returned', 'return_date' => '2026-11-03']);
        Carbon::setTestNow(Carbon::parse('2026-11-10 09:00', 'Asia/Manila'));
        $this->runReminders();
        Notification::assertSentToTimes($this->student, LoanReminderNotification::class, 2);
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

        $this->assertStringContainsString('Would send 0 due-soon and 1 overdue', $output);
        $this->assertStringContainsString('juan@example.com', $output);
        Notification::assertNothingSent();
        $this->assertNull($loan->fresh()->overdue_reminder_sent_at);
    }

    public function test_overdue_counts_from_the_philippine_date_after_midnight(): void
    {
        // 2:00 AM on Oct 31 in the Philippines is still Oct 30 on the UTC server clock.
        Carbon::setTestNow(Carbon::parse('2026-10-31 02:00', 'Asia/Manila'));
        $this->assertSame('2026-10-30', now()->toDateString());

        $loan = $this->makeLoan($this->student, $this->equipment, '2026-10-30');
        $this->runReminders();

        $this->assertReminder($loan, LoanReminderNotification::OVERDUE);
        $html = $this->sentMail()->render();
        $this->assertStringContainsString('1 day overdue', $html);
    }

    public function test_the_email_names_the_item_the_dates_and_links_to_return_item(): void
    {
        $this->makeLoan($this->student, $this->equipment, '2026-10-29', [], '2026-10-24');
        $this->runReminders();

        $mail = $this->sentMail();
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

        $mail = $this->sentMail();
        $this->assertSame('Reminder: Dell Latitude is due tomorrow', $mail->subject);
        $this->assertStringContainsString('due tomorrow', $mail->render());
    }

    public function test_reminders_are_scheduled_daily_at_7am_philippine_time(): void
    {
        $event = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->command, 'equiptrack:send-loan-reminders'));

        $this->assertNotNull($event);
        $this->assertSame('0 7 * * *', $event->expression);
        $this->assertSame('Asia/Manila', $event->timezone);
    }

    /** The mail message of the (only) reminder sent to the student. */
    private function sentMail()
    {
        $sent = Notification::sent($this->student, LoanReminderNotification::class);
        $this->assertCount(1, $sent);

        return $sent->first()->toMail($this->student);
    }
}
