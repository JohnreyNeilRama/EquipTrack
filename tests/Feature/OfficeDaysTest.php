<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\UserAccount;
use App\Notifications\LoanReminderNotification;
use App\Support\LoanTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * The equipment office is open Monday to Saturday (config equiptrack.office_days).
 * Requests can be submitted any day, even on Sunday, but borrow and return
 * dates must be office days within the booking window; late days and
 * reminder emails skip the days the office is closed.
 */
class OfficeDaysTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Department $ccs;

    private DepartmentAccount $lab;

    private UserAccount $ana;

    private Equipment $projector;

    protected function setUp(): void
    {
        parent::setUp();

        // Saturday, Nov 7, 2026, 10:00 AM Philippine time.
        Carbon::setTestNow(Carbon::parse('2026-11-07 10:00', 'Asia/Manila'));

        $this->ccs = $this->makeDepartment();
        $this->lab = $this->makeLabPersonnel($this->ccs);
        $this->ana = $this->makeStudent($this->ccs, 'ana@example.com', ['first_name' => 'Ana', 'last_name' => 'Santos']);
        $this->projector = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector', 'available_qty' => 1, 'total_qty' => 1]);
    }

    private function submit(string $type, string $from, ?string $to = null)
    {
        return $this->actingAs($this->ana, 'user')->postJson(route('user.borrow.store'), array_filter([
            'equipment_id' => $this->projector->equipment_id,
            'borrow_type' => $type,
            'borrow_date' => $from,
            'return_date' => $to,
            'purpose' => 'Laboratory Activity',
            'instructor_name' => 'Prof. Santos',
        ], fn ($value) => $value !== null));
    }

    public function test_the_office_is_open_monday_to_saturday_by_default(): void
    {
        $this->assertSame([1, 2, 3, 4, 5, 6], LoanTime::officeDays());
        $this->assertSame('Monday–Saturday', LoanTime::officeDaysLabel());
        $this->assertTrue(LoanTime::isOfficeDay('2026-11-07'));
        $this->assertFalse(LoanTime::isOfficeDay('2026-11-08'));
    }

    public function test_saturday_is_a_normal_office_day(): void
    {
        $this->submit('within_day', '2026-11-07')->assertOk();
        $this->assertSame('2026-11-07 19:00:00', BorrowRequest::first()->due_date->format('Y-m-d H:i:s'));
    }

    public function test_sunday_cannot_be_the_borrow_or_return_date(): void
    {
        $this->submit('within_day', '2026-11-08')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['borrow_date' => 'The equipment office is closed on Sundays. Please choose a borrow date from Monday–Saturday.']);

        $this->submit('multiple_days', '2026-11-07', '2026-11-08')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['return_date' => 'The equipment office is closed on Sundays. Please choose a return date from Monday–Saturday.']);

        $this->assertSame(0, BorrowRequest::count());
    }

    public function test_a_loan_may_run_over_sunday(): void
    {
        $this->submit('multiple_days', '2026-11-07', '2026-11-09')->assertOk();
        $this->assertSame('2026-11-09 19:00:00', BorrowRequest::first()->due_date->format('Y-m-d H:i:s'));
    }

    public function test_requests_submitted_on_sunday_wait_for_monday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-08 15:00', 'Asia/Manila'));

        // Today (Sunday) is closed; Monday onwards is fine.
        $this->submit('within_day', '2026-11-08')->assertStatus(422);
        $id = (int) $this->submit('within_day', '2026-11-09')->assertOk()->json('request_id');
        $this->assertSame('2026-11-09', LoanTime::earliestBorrowDate()->toDateString());

        // Lab Personnel approve it on Monday morning.
        Carbon::setTestNow(Carbon::parse('2026-11-09 08:00', 'Asia/Manila'));
        $this->actingAs($this->lab, 'dept')->postJson(route('department.requests.update'), [
            'request_id' => $id,
            'status' => 'Approved',
        ])->assertOk();
        $this->assertSame(1, BorrowTransaction::count());
    }

    public function test_after_saturdays_deadline_the_next_borrow_date_is_monday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-07 19:30', 'Asia/Manila'));

        $this->assertSame('2026-11-09', LoanTime::earliestBorrowDate()->toDateString());
        $this->submit('within_day', '2026-11-07')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['borrow_date' => "Today's return deadline (7:00 PM) has passed. Please choose Nov 09, 2026 or a later date."]);
    }

    public function test_requests_are_limited_to_the_booking_window(): void
    {
        // 30 days from Saturday, Nov 7 is Monday, Dec 7.
        $this->submit('within_day', '2026-12-07')->assertOk();

        $this->submit('within_day', '2026-12-08')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['borrow_date' => 'You can request up to 30 days ahead (until Dec 07, 2026).']);

        config(['equiptrack.max_advance_days' => 60]);
        $this->submit('within_day', '2026-12-08')->assertOk();
    }

    public function test_office_days_and_the_window_come_from_config(): void
    {
        config(['equiptrack.office_days' => 'Mon,Tue,Wed,Thu,Fri']);

        $this->assertSame('Monday–Friday', LoanTime::officeDaysLabel());
        $this->submit('within_day', '2026-11-07')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['borrow_date' => 'The equipment office is closed on Saturdays. Please choose a borrow date from Monday–Friday.']);
    }

    public function test_late_days_skip_sundays(): void
    {
        // Due Saturday 7:00 PM, back Monday morning: 1 office day late, not 2.
        $this->assertSame(1, LoanTime::daysLate(Carbon::parse('2026-11-07 19:00'), Carbon::parse('2026-11-09 09:00')));
        // Due Friday, back Monday: Saturday and Monday count.
        $this->assertSame(2, LoanTime::daysLate(Carbon::parse('2026-11-06 19:00'), Carbon::parse('2026-11-09 09:00')));
        // Late the same evening is still 1 day.
        $this->assertSame(1, LoanTime::daysLate(Carbon::parse('2026-11-07 19:00'), Carbon::parse('2026-11-07 20:00')));
        $this->assertSame(0, LoanTime::daysLate(Carbon::parse('2026-11-07 19:00'), Carbon::parse('2026-11-07 18:00')));
    }

    public function test_reminder_emails_are_not_sent_on_sunday(): void
    {
        Notification::fake();
        $loan = $this->makeLoan($this->ana, $this->projector, '2026-11-09');

        Carbon::setTestNow(Carbon::parse('2026-11-08 07:00', 'Asia/Manila'));
        Artisan::call('equiptrack:send-loan-reminders');
        Notification::assertNothingSent();
        $this->assertNull($loan->fresh()->due_reminder_sent_at);

        // Monday morning: due today.
        Carbon::setTestNow(Carbon::parse('2026-11-09 07:00', 'Asia/Manila'));
        Artisan::call('equiptrack:send-loan-reminders');
        Notification::assertSentTo($this->ana, LoanReminderNotification::class);
    }

    public function test_the_request_form_shows_the_office_days(): void
    {
        $html = $this->actingAs($this->ana, 'user')->get(route('user.equipment'))->assertOk()->getContent();

        $this->assertStringContainsString('Office days: Monday–Saturday.', $html);
        $this->assertStringContainsString('const OFFICE_DAYS = [1,2,3,4,5,6];', $html);
        $this->assertStringContainsString('const MAX_ADVANCE_DAYS = 30;', $html);
    }
}
