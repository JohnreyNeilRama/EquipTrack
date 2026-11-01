<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\UserAccount;
use App\Notifications\LoanReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Release Confirmation: approval holds a unit as Ready for Pickup; the loan
 * only starts when Lab Personnel check the borrower's school ID and release
 * the item, or ends as Not Claimed (unit back in stock) if they never come.
 * Until it is released it cannot be returned, is not overdue and sends no
 * reminders.
 */
class ReleaseEquipmentTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Department $ccs;

    private DepartmentAccount $lab;

    private DepartmentAccount $otherLab;

    private Admin $admin;

    private UserAccount $student;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        // 9:00 AM on Oct 31, 2026, Philippine time.
        Carbon::setTestNow(Carbon::parse('2026-10-31 09:00', 'Asia/Manila'));

        $this->ccs = $this->makeDepartment();
        $this->lab = $this->makeLabPersonnel($this->ccs, 'lab@example.com', 'Ana Custodian');
        $this->otherLab = $this->makeLabPersonnel($this->makeDepartment('College of Criminal Justice', 'CCJ'), 'ccj@example.com');
        $this->admin = $this->makeAdmin();
        $this->student = $this->makeStudent($this->ccs, 'juan@example.com', ['id_number' => '20260001']);
        $this->equipment = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector', 'available_qty' => 2, 'total_qty' => 2]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Submitted by the student and approved by Lab Personnel; returns the request ID. */
    private function approvedRequest(string $pickup = '2026-10-31', string $due = '2026-11-03'): int
    {
        $requestId = (int) $this->actingAs($this->student, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $this->equipment->equipment_id,
            'borrow_date' => $pickup,
            'return_date' => $due,
            'purpose' => 'Thesis defense',
            'instructor_name' => 'Prof. Santos',
        ])->assertOk()->json('request_id');

        $this->actingAs($this->lab, 'dept')->postJson(route('department.requests.update'), [
            'request_id' => $requestId,
            'status' => 'Approved',
        ])->assertOk();

        return $requestId;
    }

    private function loan(int $requestId): BorrowTransaction
    {
        return BorrowTransaction::where('request_id', $requestId)->firstOrFail();
    }

    private function release(int $requestId, ?DepartmentAccount $as = null, array $input = ['id_verified' => '1'])
    {
        return $this->actingAs($as ?? $this->lab, 'dept')->postJson(route('department.releases.release'), [
            'transaction_id' => $this->loan($requestId)->transaction_id,
        ] + $input);
    }

    private function notClaimed(int $requestId, array $input = [])
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.releases.not-claimed'), [
            'transaction_id' => $this->loan($requestId)->transaction_id,
        ] + $input);
    }

    private function returnItem(int $requestId)
    {
        return $this->actingAs($this->student, 'user')->postJson(route('user.returns.store'), [
            'request_id' => $requestId,
            'condition' => 'Good',
        ]);
    }

    public function test_approval_holds_a_unit_as_ready_for_pickup(): void
    {
        $requestId = $this->approvedRequest();

        $loan = $this->loan($requestId);
        $this->assertSame(BorrowTransaction::STATUS_READY, $loan->status);
        $this->assertNull($loan->released_at);
        $this->assertSame(1, (int) $this->equipment->fresh()->available_qty);

        // The borrower is told to pick it up, and cannot return it yet.
        $this->actingAs($this->student, 'user')->get(route('user.returns'))
            ->assertOk()
            ->assertSee('Ready for Pickup')
            ->assertSee('Pick up at the lab')
            ->assertDontSee('onclick="openReturnModal(this)"', false);

        $this->returnItem($requestId)->assertStatus(400)
            ->assertJson(['message' => 'This item has not been released to you yet. Pick it up at the lab first.']);

        $approved = collect($this->actingAs($this->student, 'user')->getJson(route('user.notifications'))->json('notifications'))
            ->firstWhere('key', 'request-' . $requestId . '-approved');
        $this->assertStringContainsString('ready for pickup on Oct 31, 2026', $approved['message']);
    }

    public function test_release_needs_the_id_check_then_starts_the_loan(): void
    {
        $requestId = $this->approvedRequest();

        $this->release($requestId, null, [])->assertStatus(422)->assertJsonValidationErrors('id_verified');
        $this->assertSame(BorrowTransaction::STATUS_READY, $this->loan($requestId)->status);

        $this->release($requestId)->assertOk()->assertJson(['success' => true]);

        $loan = $this->loan($requestId);
        $this->assertSame(BorrowTransaction::STATUS_ACTIVE, $loan->status);
        $this->assertNotNull($loan->released_at);
        $this->assertSame($this->lab->dept_acc_id, (int) $loan->released_by);
        $this->assertSame(1, (int) $this->equipment->fresh()->available_qty);

        $this->assertTrue(AuditTrail::where('dept_acc_id', $this->lab->dept_acc_id)
            ->where('action', 'like', 'Released borrow request #' . $requestId . '%')
            ->where('details', 'like', '%school ID checked%')
            ->exists());

        // Now the borrower has it and can return it.
        $this->returnItem($requestId)->assertOk();
    }

    public function test_a_loan_is_released_or_marked_not_claimed_only_once(): void
    {
        $requestId = $this->approvedRequest();

        $this->release($requestId)->assertOk();
        $this->release($requestId)->assertStatus(400)->assertJson(['message' => 'This equipment has already been released.']);
        $this->notClaimed($requestId)->assertStatus(400);
        $this->assertSame(1, (int) $this->equipment->fresh()->available_qty);
    }

    public function test_only_the_owning_department_can_release(): void
    {
        $requestId = $this->approvedRequest();

        $this->release($requestId, $this->otherLab)->assertNotFound();

        $this->actingAs($this->otherLab, 'dept')->getJson(route('department.releases.data'))
            ->assertOk()->assertJson(['releases' => []]);

        // Signed out: no access at all.
        $this->app['auth']->forgetGuards();
        $this->postJson(route('department.releases.release'), ['transaction_id' => $this->loan($requestId)->transaction_id, 'id_verified' => '1'])
            ->assertUnauthorized();

        $this->assertSame(BorrowTransaction::STATUS_READY, $this->loan($requestId)->status);
    }

    public function test_not_claimed_gives_the_unit_back_and_closes_the_request(): void
    {
        $requestId = $this->approvedRequest();

        $this->notClaimed($requestId, ['note' => 'Did not come on the pickup date.'])->assertOk();

        $loan = $this->loan($requestId);
        $this->assertSame(BorrowTransaction::STATUS_NOT_CLAIMED, $loan->status);
        $this->assertNotNull($loan->unclaimed_at);
        $this->assertNull($loan->released_at);
        $this->assertSame(2, (int) $this->equipment->fresh()->available_qty);
        $this->assertTrue(AuditTrail::where('action', 'Marked borrow request #' . $requestId . ' as Not Claimed')
            ->where('details', 'Note: Did not come on the pickup date.')
            ->exists());

        // Gone from the Return Item page, listed as Not Claimed in history.
        $this->actingAs($this->student, 'user')->get(route('user.returns'))->assertDontSee('Epson Projector');
        $this->actingAs($this->student, 'user')->get(route('user.history'))->assertSee('Not Claimed');
        $this->returnItem($requestId)->assertStatus(400);

        $keys = collect($this->actingAs($this->student, 'user')->getJson(route('user.notifications'))->json('notifications'))->pluck('key');
        $this->assertContains('request-' . $requestId . '-not-claimed', $keys);
    }

    public function test_the_release_page_lists_waiting_pickups_first(): void
    {
        $released = $this->approvedRequest();
        $this->release($released)->assertOk();
        $waiting = $this->approvedRequest('2026-11-02', '2026-11-05');

        $this->actingAs($this->lab, 'dept')->get(route('department.releases'))
            ->assertOk()
            ->assertSee('Release Equipment')
            ->assertSee('Release Equipment', false);

        $rows = $this->actingAs($this->lab, 'dept')->getJson(route('department.releases.data'))->json('releases');

        $this->assertSame([$waiting, $released], array_column($rows, 'requestId'));
        $this->assertSame(['Ready for Pickup', 'Released'], array_column($rows, 'status'));
        $this->assertSame('20260001', $rows[0]['idNumber']);
        $this->assertSame('In 2 days', $rows[0]['pickupNote']);
        $this->assertTrue($rows[0]['canAct']);
        $this->assertFalse($rows[1]['canAct']);
        $this->assertSame('Ana Custodian', $rows[1]['releasedBy']);
    }

    public function test_unreleased_loans_are_not_overdue_and_get_no_reminders(): void
    {
        Notification::fake();

        // Due yesterday, never picked up.
        $requestId = $this->approvedRequest('2026-10-31', '2026-10-31');
        Carbon::setTestNow(Carbon::parse('2026-11-01 09:00', 'Asia/Manila'));

        Artisan::call('equiptrack:send-loan-reminders');
        Notification::assertNothingSent();

        $keys = collect($this->actingAs($this->student, 'user')->getJson(route('user.notifications'))->json('notifications'))->pluck('key');
        $this->assertEmpty($keys->filter(fn ($key) => str_starts_with($key, 'overdue-') || str_starts_with($key, 'due-')));

        $dashboard = $this->actingAs($this->student, 'user')->getJson(route('user.dashboard.data'))->json();
        $this->assertSame(0, $dashboard['stats']['activeBorrows']);
        $this->assertSame(0, $dashboard['stats']['overdueItems']);
        $this->assertSame('Ready for Pickup', $dashboard['activeBorrows'][0]['status']);

        $this->assertSame([], $this->actingAs($this->admin, 'admin')->getJson(route('admin.dashboard.data'))->json('overdue'));

        // Once released, the same loan is overdue and reminded.
        $this->release($requestId)->assertOk();
        Artisan::call('equiptrack:send-loan-reminders');
        Notification::assertSentTo($this->student, LoanReminderNotification::class);
    }

    public function test_lab_personnel_are_alerted_on_the_pickup_day(): void
    {
        $today = $this->approvedRequest('2026-10-31');
        $this->approvedRequest('2026-11-04', '2026-11-06');

        $feed = $this->actingAs($this->lab, 'dept')->getJson(route('department.notifications'))->json('notifications');
        $pickups = collect($feed)->where('type', 'pickup')->values();

        $this->assertCount(1, $pickups);
        $this->assertSame('pickup-' . $this->loan($today)->transaction_id, $pickups[0]['key']);
        $this->assertSame('Pickup Today', $pickups[0]['title']);
        $this->assertSame(route('department.releases'), $pickups[0]['url']);

        // Gone once released; the admin never gets pickup alerts.
        $this->release($today)->assertOk();
        $this->assertEmpty(collect($this->actingAs($this->lab, 'dept')->getJson(route('department.notifications'))->json('notifications'))->where('type', 'pickup'));
        $this->assertEmpty(collect($this->actingAs($this->admin, 'admin')->getJson(route('admin.notifications'))->json('notifications'))->where('type', 'pickup'));
    }

    public function test_monitoring_and_reports_show_held_units_as_reserved_or_ready(): void
    {
        $ready = $this->approvedRequest();
        $unclaimed = $this->approvedRequest();
        $this->notClaimed($unclaimed)->assertOk();

        $rows = $this->actingAs($this->lab, 'dept')->get(route('department.monitoring'))->viewData('dbMonitoring');
        $this->assertSame(['Reserved'], collect($rows)->pluck('status')->unique()->values()->all());

        $report = $this->actingAs($this->admin, 'admin')->getJson(route('admin.reports.data', ['type' => 'borrowing']))->json('report.rows');
        $this->assertEqualsCanonicalizing(['Ready for Pickup', 'Not Claimed'], array_column($report, 'status'));

        $this->release($ready)->assertOk();
        $report = $this->actingAs($this->admin, 'admin')->getJson(route('admin.reports.data', ['type' => 'borrowing']))->json('report.rows');
        $this->assertEqualsCanonicalizing(['Borrowed', 'Not Claimed'], array_column($report, 'status'));
    }

    public function test_users_with_a_unit_held_for_pickup_cannot_be_deleted(): void
    {
        $this->approvedRequest();

        $this->actingAs($this->admin, 'admin')->post(route('admin.users.delete'), [
            'user_id' => $this->student->user_id,
            'user_type' => 'user',
        ])->assertRedirect(route('admin.users'));

        $this->assertNotNull(UserAccount::find($this->student->user_id));
    }
}
