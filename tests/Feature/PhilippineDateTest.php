<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BorrowTransaction;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * The server clock runs on UTC, eight hours behind the Philippines. Every
 * due/overdue check must use the Philippine date, so between midnight and
 * 8:00 AM a loan due yesterday is already overdue, not "due today".
 */
class PhilippineDateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Admin $admin;

    private DepartmentAccount $lab;

    private UserAccount $student;

    private BorrowTransaction $loan;

    protected function setUp(): void
    {
        parent::setUp();

        $department = $this->makeDepartment();
        $this->admin = $this->makeAdmin();
        $this->lab = $this->makeLabPersonnel($department);
        $this->student = $this->makeStudent($department);
        // Expensive, so the loan also shows on the Admin's side.
        $equipment = $this->makeEquipment($department, ['classification' => Equipment::CLASS_EXPENSIVE]);
        $this->loan = $this->makeLoan($this->student, $equipment, '2026-10-30', [], '2026-10-27');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** 2:00 AM on Oct 31 in the Philippines, still Oct 30 on the UTC server clock. */
    private function justAfterMidnight(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-31 02:00', 'Asia/Manila'));
        $this->assertSame('2026-10-30', now()->toDateString());
    }

    /** 11:30 PM on Oct 30 in the Philippines: the due day itself. */
    private function lateOnTheDueDate(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-30 23:30', 'Asia/Manila'));
    }

    private function overdueReportRows(): array
    {
        return $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.reports.data', ['type' => 'overdue', 'from' => '2026-10-01', 'to' => '2026-11-30']))
            ->json('report.rows');
    }

    public function test_dashboards_treat_the_loan_as_overdue_right_after_midnight(): void
    {
        $this->justAfterMidnight();

        $admin = collect($this->actingAs($this->admin, 'admin')->getJson(route('admin.dashboard.data'))->json('overdue'));
        $this->assertSame(1, $admin->firstWhere('id', $this->loan->transaction_id)['daysLate']);

        $dept = $this->actingAs($this->lab, 'dept')->getJson(route('department.dashboard.data'));
        $this->assertSame(1, collect($dept->json('overdue'))->firstWhere('id', $this->loan->transaction_id)['daysLate']);
        $this->assertSame(1, $dept->json('stats.overdueItems'));

        $this->assertSame(1, $this->actingAs($this->student, 'user')->getJson(route('user.dashboard.data'))->json('stats.overdueItems'));
    }

    public function test_dashboards_still_say_due_today_late_on_the_due_date(): void
    {
        $this->lateOnTheDueDate();

        $this->assertEmpty($this->actingAs($this->admin, 'admin')->getJson(route('admin.dashboard.data'))->json('overdue'));
        $dept = $this->actingAs($this->lab, 'dept')->getJson(route('department.dashboard.data'));
        $this->assertSame('Due Today', collect($dept->json('upcoming'))->firstWhere('id', $this->loan->transaction_id)['daysRemainingText']);
        $this->assertSame(0, $this->actingAs($this->student, 'user')->getJson(route('user.dashboard.data'))->json('stats.overdueItems'));
    }

    public function test_monitoring_history_and_reports_follow_the_philippine_date(): void
    {
        $this->justAfterMidnight();
        $this->actingAs($this->admin, 'admin')->get(route('admin.monitoring'))->assertOk()->assertSee('"status":"Overdue"', false);
        $this->actingAs($this->lab, 'dept')->get(route('department.monitoring'))->assertOk()->assertSee('"status":"Overdue"', false);
        $this->actingAs($this->student, 'user')->get(route('user.history'))->assertOk()->assertSee('data-status="Overdue"', false);
        $this->assertCount(1, $this->overdueReportRows());

        $this->lateOnTheDueDate();
        $this->actingAs($this->admin, 'admin')->get(route('admin.monitoring'))->assertOk()->assertDontSee('"status":"Overdue"', false);
        $this->actingAs($this->student, 'user')->get(route('user.history'))->assertOk()->assertSee('data-status="Borrowed"', false);
        $this->assertCount(0, $this->overdueReportRows());
    }

    public function test_the_notification_bell_follows_the_philippine_date(): void
    {
        $this->justAfterMidnight();
        $notice = collect($this->actingAs($this->student, 'user')->getJson(route('user.notifications'))->json('notifications'))
            ->firstWhere('type', 'overdue');
        $this->assertSame('Overdue Equipment', $notice['title']);
        $this->assertStringContainsString('(1 day late)', $notice['message']);
        $this->assertStringEndsWith('-2026-10-31', $notice['key']);
        // Shown as having arrived at midnight, never "from now".
        $this->assertStringNotContainsString('from now', $notice['ago']);

        $this->lateOnTheDueDate();
        $notice = collect($this->actingAs($this->student, 'user')->getJson(route('user.notifications'))->json('notifications'))
            ->firstWhere('type', 'due');
        $this->assertSame('Due Today', $notice['title']);
    }
}
