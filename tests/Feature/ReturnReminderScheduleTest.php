<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\LoanManualReminder;
use App\Models\UserAccount;
use App\Notifications\LoanReminderNotification;
use App\Support\LoanReminders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * The return-reminder schedule in the notification bells (App\Support\LoanReminders)
 * and the "Remind" button on Equipment Monitoring:
 *
 *  - Borrower: Within the Day loans ("return it today", "2 hours left", then overdue).
 *  - Lab Personnel: "due back today", an overdue item from the deadline, flagged
 *    again on the follow-up day; only their own department's equipment.
 *  - Admin: loans still out on the escalation day, and a daily "waiting too
 *    long" summary.
 *  - Remind: bell + email to the borrower, once per loan per day, in the Audit Trail.
 *
 * Calendar: Oct 29, 2026 is a Thursday, Nov 1 a Sunday (closed), Nov 2 a Monday.
 */
class ReturnReminderScheduleTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Department $department;

    private UserAccount $student;

    private Equipment $equipment;

    private DepartmentAccount $lab;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->at('2026-11-02 09:00');

        $this->department = $this->makeDepartment();
        $this->student = $this->makeStudent($this->department, 'juan@example.com');
        $this->equipment = $this->makeEquipment($this->department);
        $this->lab = $this->makeLabPersonnel($this->department);
        $this->admin = $this->makeAdmin();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $philippineTime): void
    {
        Carbon::setTestNow(Carbon::parse($philippineTime, 'Asia/Manila'));
    }

    private function userFeed(): array
    {
        return $this->actingAs($this->student, 'user')->getJson(route('user.notifications'))->assertOk()->json('notifications');
    }

    private function deptFeed(?DepartmentAccount $as = null): array
    {
        return $this->actingAs($as ?? $this->lab, 'dept')->getJson(route('department.notifications'))->assertOk()->json('notifications');
    }

    private function adminFeed(): array
    {
        return $this->actingAs($this->admin, 'admin')->getJson(route('admin.notifications'))->assertOk()->json('notifications');
    }

    /** Only the items about loans (not request decisions etc.). */
    private function keys(array $feed, string $prefix): array
    {
        return array_values(array_filter(array_column($feed, 'key'), fn ($key) => str_starts_with($key, $prefix)));
    }

    // ------------------------------------------------------------- schedule

    public function test_overdue_steps_are_1_3_7_then_weekly(): void
    {
        $steps = array_map([LoanReminders::class, 'overdueStep'], [0, 1, 2, 3, 6, 7, 13, 14, 20, 21]);

        $this->assertSame([0, 1, 1, 3, 3, 7, 7, 14, 14, 21], $steps);
    }

    // ------------------------------------------------------------ borrower

    public function test_within_the_day_loan_in_the_borrowers_bell(): void
    {
        $loan = $this->makeLoan($this->student, $this->equipment, '2026-11-02', ['released_at' => now()], '2026-11-02');

        // Right after release: "Return Today".
        $item = $this->userFeed()[0];
        $this->assertSame('due-' . $loan->transaction_id . '-same-day', $item['key']);
        $this->assertSame('Return Today', $item['title']);
        $this->assertStringContainsString('Return it to the lab today by 7:00 PM', $item['message']);

        // 5:00 PM: "2 Hours Left", a new (unread) item that replaces it.
        $this->at('2026-11-02 17:00');
        $feed = $this->userFeed();
        $this->assertCount(1, $feed);
        $this->assertSame('due-' . $loan->transaction_id . '-final-hours', $feed[0]['key']);
        $this->assertSame('2 Hours Left', $feed[0]['title']);
        $this->assertFalse($feed[0]['read']);

        // 7:01 PM: overdue.
        $this->at('2026-11-02 19:01');
        $feed = $this->userFeed();
        $this->assertSame('overdue-' . $loan->transaction_id . '-1', $feed[0]['key']);
        $this->assertSame('Overdue Equipment', $feed[0]['title']);
    }

    public function test_short_loans_get_no_early_reminder(): void
    {
        // A 2-day loan due Wed: nothing on Mon (2 days left), "Due Tomorrow" on Tue.
        $loan = $this->makeLoan($this->student, $this->equipment, '2026-11-04', ['released_at' => now()], '2026-11-02');

        $this->assertSame([], $this->keys($this->userFeed(), 'due-'));

        $this->at('2026-11-03 08:00');
        $this->assertSame(['due-' . $loan->transaction_id . '-day-before'], $this->keys($this->userFeed(), 'due-'));
    }

    // ------------------------------------------------------- Lab Personnel

    public function test_lab_personnel_see_returns_due_today_then_overdue_then_follow_up(): void
    {
        $loan = $this->makeLoan($this->student, $this->equipment, '2026-11-02');

        // Due today: one summary until the deadline.
        $feed = $this->deptFeed();
        $this->assertSame(['returns-due-2026-11-02'], $this->keys($feed, 'returns-due-'));
        $summary = collect($feed)->firstWhere('key', 'returns-due-2026-11-02');
        $this->assertSame('1 item is due back today by 7:00 PM: Dell Latitude (Juan Dela Cruz).', $summary['message']);
        $this->assertSame([], $this->keys($feed, 'overdue-'));

        // 7:01 PM: not returned by closing.
        $this->at('2026-11-02 19:01');
        $feed = $this->deptFeed();
        $this->assertSame([], $this->keys($feed, 'returns-due-'));
        $item = collect($feed)->firstWhere('key', 'overdue-' . $loan->transaction_id . '-late');
        $this->assertSame('Not Returned by Closing', $item['title']);

        // Next office day: same item (same key), now "Overdue Equipment".
        $this->at('2026-11-03 09:00');
        $item = collect($this->deptFeed())->firstWhere('key', 'overdue-' . $loan->transaction_id . '-late');
        $this->assertSame('Overdue Equipment', $item['title']);

        // 3 office days late (Tue, Wed, Thu): the follow-up item, unread again.
        $this->at('2026-11-05 09:00');
        $feed = $this->deptFeed();
        $this->assertSame(['overdue-' . $loan->transaction_id . '-follow-up'], $this->keys($feed, 'overdue-'));
        $this->assertStringContainsString('record a Late Return penalty', collect($feed)->firstWhere('title', 'Overdue: Follow Up')['message']);

        // The borrower submits the return: the overdue item goes away.
        $loan->update(['return_status' => BorrowTransaction::RETURN_PENDING, 'return_submitted_at' => now()]);
        $this->assertSame([], $this->keys($this->deptFeed(), 'overdue-'));
    }

    public function test_lab_personnel_only_see_their_own_departments_loans(): void
    {
        $this->makeLoan($this->student, $this->equipment, '2026-10-29');
        $other = $this->makeDepartment('College of Nursing', 'CON');
        $otherLab = $this->makeLabPersonnel($other, 'nursing@example.com', 'Nursing Lab');

        $this->assertCount(1, $this->keys($this->deptFeed(), 'overdue-'));
        $this->assertSame([], $this->keys($this->deptFeed($otherLab), 'overdue-'));
    }

    // ---------------------------------------------------------------- Admin

    public function test_admin_hears_about_an_overdue_loan_only_on_the_escalation_day(): void
    {
        // Due Thu Oct 29. Mon Nov 2 is 3 office days late: nothing for the admin yet.
        $loan = $this->makeLoan($this->student, $this->equipment, '2026-10-29');
        $this->assertSame([], $this->keys($this->adminFeed(), 'overdue-'));

        // Fri Nov 6 is 7 office days late (Fri, Sat, Mon–Fri).
        $this->at('2026-11-06 09:00');
        $feed = $this->adminFeed();
        $this->assertSame(['overdue-' . $loan->transaction_id . '-escalated'], $this->keys($feed, 'overdue-'));
        $item = collect($feed)->firstWhere('key', 'overdue-' . $loan->transaction_id . '-escalated');
        $this->assertSame('Overdue 7+ Days', $item['title']);
        $this->assertStringContainsString('Dell Latitude (College of Computer Studies) is 7 days overdue', $item['message']);

        // The borrower sees the final notice at the same point.
        $this->assertStringContainsString('reported to the Admin', $this->userFeed()[0]['message']);
    }

    public function test_admin_gets_one_daily_summary_of_what_has_waited_too_long(): void
    {
        $expensive = $this->makeEquipment($this->department, ['name' => 'MacBook Pro', 'classification' => Equipment::CLASS_EXPENSIVE]);
        $request = $this->makePendingRequest($this->student, $expensive);

        // Requested today: not waiting too long yet.
        $this->assertSame([], $this->keys($this->adminFeed(), 'waiting-'));

        // 3 days later (Thu Nov 5): one summary for today.
        $request->forceFill(['date_requested' => now()])->save();
        $this->at('2026-11-05 09:00');
        $feed = $this->adminFeed();
        $this->assertSame(['waiting-2026-11-05'], $this->keys($feed, 'waiting-'));
        $this->assertStringContainsString('1 Expensive request has waited more than 2 days for your review', collect($feed)->firstWhere('key', 'waiting-2026-11-05')['message']);

        // Sunday: the office is closed, no summary.
        $this->at('2026-11-08 09:00');
        $this->assertSame([], $this->keys($this->adminFeed(), 'waiting-'));
    }

    // ------------------------------------------------------------ Remind

    public function test_admin_remind_sends_a_bell_item_and_an_email_once_a_day(): void
    {
        $loan = $this->makeLoan($this->student, $this->equipment, '2026-10-29');

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.monitoring.remind'), ['transaction_id' => $loan->transaction_id])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Reminder sent to Juan Dela Cruz by email and notification.']);

        // Email.
        Notification::assertSentTo($this->student, LoanReminderNotification::class,
            fn (LoanReminderNotification $n) => $n->type === LoanReminderNotification::MANUAL && $n->sender === 'Admin System Admin');
        $mail = Notification::sent($this->student, LoanReminderNotification::class)->first()->toMail($this->student);
        $this->assertSame('Reminder from the lab: please return Dell Latitude', $mail->subject);
        $this->assertStringContainsString('Admin System Admin sent you this reminder.', $mail->render());

        // Bell.
        $item = collect($this->userFeed())->firstWhere('title', 'Return Reminder');
        $this->assertNotNull($item);
        $this->assertStringContainsString('Admin System Admin reminded you to return Dell Latitude', $item['message']);

        // Audit Trail.
        $audit = AuditTrail::where('action', 'like', 'Sent return reminder%')->first();
        $this->assertNotNull($audit);
        $this->assertSame($this->admin->admin_id, (int) $audit->admin_id);
        $this->assertSame('borrow_request:' . $loan->request_id, $audit->affected_entity);

        // A second reminder the same day (by anyone) is refused.
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.monitoring.remind'), ['transaction_id' => $loan->transaction_id])
            ->assertStatus(429);
        $this->actingAs($this->lab, 'dept')
            ->postJson(route('department.monitoring.remind'), ['transaction_id' => $loan->transaction_id])
            ->assertStatus(429);
        $this->assertSame(1, LoanManualReminder::count());

        // The monitoring page shows the button as spent for today.
        $row = collect($this->actingAs($this->admin, 'admin')->get(route('admin.monitoring'))->viewData('dbRequests'))
            ->firstWhere('transactionId', $loan->transaction_id);
        $this->assertTrue($row['remindedToday']);

        // The next day it can be sent again.
        $this->at('2026-11-03 09:00');
        $this->actingAs($this->lab, 'dept')
            ->postJson(route('department.monitoring.remind'), ['transaction_id' => $loan->transaction_id])
            ->assertOk();
        $this->assertSame(2, LoanManualReminder::count());
    }

    public function test_lab_personnel_can_only_remind_about_their_own_departments_items_that_are_still_out(): void
    {
        $other = $this->makeDepartment('College of Nursing', 'CON');
        $otherLab = $this->makeLabPersonnel($other, 'nursing@example.com', 'Nursing Lab');
        $loan = $this->makeLoan($this->student, $this->equipment, '2026-10-29');

        // Another department's loan: not found.
        $this->actingAs($otherLab, 'dept')
            ->postJson(route('department.monitoring.remind'), ['transaction_id' => $loan->transaction_id])
            ->assertNotFound();

        // Return submitted: nothing to remind.
        $loan->update(['return_status' => BorrowTransaction::RETURN_PENDING, 'return_submitted_at' => now()]);
        $this->actingAs($this->lab, 'dept')
            ->postJson(route('department.monitoring.remind'), ['transaction_id' => $loan->transaction_id])
            ->assertStatus(422);

        Notification::assertNothingSent();
        $this->assertSame(0, LoanManualReminder::count());
    }

    public function test_department_monitoring_rows_carry_the_remind_state(): void
    {
        $loan = $this->makeLoan($this->student, $this->equipment, '2026-10-29');

        $row = collect($this->actingAs($this->lab, 'dept')->get(route('department.monitoring'))->viewData('dbMonitoring'))
            ->firstWhere('transactionId', $loan->transaction_id);

        $this->assertTrue($row['canRemind']);
        $this->assertFalse($row['remindedToday']);
    }
}
