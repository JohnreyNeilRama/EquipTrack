<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\UserAccount;
use App\Services\AuditCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Students/Faculty cancel their own request while it is still Pending; staff
 * then see it as Cancelled and can no longer approve or reject it.
 */
class CancelBorrowRequestTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private UserAccount $student;

    private UserAccount $otherStudent;

    private DepartmentAccount $lab;

    private Admin $admin;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $department = $this->makeDepartment();
        $this->student = $this->makeStudent($department, 'juan@example.com');
        $this->otherStudent = $this->makeStudent($department, 'pedro@example.com', ['first_name' => 'Pedro']);
        $this->lab = $this->makeLabPersonnel($department);
        $this->admin = $this->makeAdmin();
        $this->equipment = $this->makeEquipment($department);
    }

    private function cancel(BorrowRequest|int $request, ?UserAccount $as = null)
    {
        $id = $request instanceof BorrowRequest ? $request->request_id : $request;

        return $this->actingAs($as ?? $this->student, 'user')
            ->postJson(route('user.requests.cancel'), ['request_id' => $id]);
    }

    private function review(BorrowRequest $request, string $status, string $as = 'dept')
    {
        $route = $as === 'dept' ? 'department.requests.update' : 'admin.requests.update';
        $account = $as === 'dept' ? $this->lab : $this->admin;

        return $this->actingAs($account, $as)->postJson(route($route), [
            'request_id' => $request->request_id,
            'status' => $status,
            'reject_reason' => $status === 'Rejected' ? 'No units available' : null,
        ]);
    }

    public function test_the_borrower_cancels_a_pending_request(): void
    {
        $request = $this->makePendingRequest($this->student, $this->equipment);

        $this->cancel($request)->assertOk()->assertJson(['success' => true, 'message' => 'Your request has been cancelled.']);

        $request->refresh();
        $this->assertSame(BorrowRequest::STATUS_CANCELLED, $request->overall_status);
        $this->assertNotNull($request->cancelled_at);
        // The approvers never reviewed it.
        $this->assertSame('Pending', $request->admin_status);
        $this->assertSame('Pending', $request->dept_status);
        // Stock is only deducted on approval, so nothing changes.
        $this->assertSame(3, (int) $this->equipment->fresh()->available_qty);
        $this->assertFalse($request->transaction()->exists());

        $audit = AuditTrail::where('affected_entity', 'borrow_request:' . $request->request_id)->firstOrFail();
        $this->assertSame($this->student->user_id, (int) $audit->user_id);
        $this->assertStringStartsWith('Cancelled borrow request #' . $request->request_id, $audit->action);
        $this->assertSame('Cancelled Request', AuditCatalog::classify($audit->action, $audit->affected_entity)['label']);
    }

    public function test_a_request_cannot_be_cancelled_twice(): void
    {
        $request = $this->makePendingRequest($this->student, $this->equipment);
        $this->cancel($request)->assertOk();

        $this->cancel($request)->assertStatus(400)->assertJson(['message' => 'This request is already cancelled.']);
        $this->assertSame(1, AuditTrail::where('affected_entity', 'borrow_request:' . $request->request_id)->count());
    }

    public function test_only_the_owner_can_cancel(): void
    {
        $request = $this->makePendingRequest($this->student, $this->equipment);

        // Another user's request looks like it does not exist.
        $this->cancel($request, $this->otherStudent)->assertNotFound();
        $this->assertSame('Pending', $request->fresh()->overall_status);
    }

    public function test_approved_and_rejected_requests_cannot_be_cancelled(): void
    {
        $approved = $this->makePendingRequest($this->student, $this->equipment);
        $this->review($approved, 'Approved')->assertOk();
        $this->cancel($approved)->assertStatus(400)->assertJson(['message' => 'Only pending requests can be cancelled. This request is already approved.']);
        $this->assertSame('Approved', $approved->fresh()->overall_status);

        $rejected = $this->makePendingRequest($this->student, $this->equipment);
        $this->review($rejected, 'Rejected')->assertOk();
        $this->cancel($rejected)->assertStatus(400)->assertJson(['message' => 'Only pending requests can be cancelled. This request is already rejected.']);
    }

    public function test_staff_cannot_approve_or_reject_a_cancelled_request(): void
    {
        $request = $this->makePendingRequest($this->student, $this->equipment);
        $this->cancel($request)->assertOk();

        $this->review($request, 'Approved')->assertStatus(400)->assertJson(['message' => 'The borrower cancelled this request.']);
        $this->review($request, 'Rejected')->assertStatus(400)->assertJson(['message' => 'The borrower cancelled this request.']);
        $this->assertSame(BorrowRequest::STATUS_CANCELLED, $request->fresh()->overall_status);
        $this->assertSame(3, (int) $this->equipment->fresh()->available_qty);
    }

    public function test_expensive_request_approved_by_one_side_can_still_be_cancelled(): void
    {
        $this->equipment->update(['classification' => Equipment::CLASS_EXPENSIVE]);
        $request = $this->makePendingRequest($this->student, $this->equipment->fresh());

        // The department approves first; it still waits for the Admin.
        $this->review($request, 'Approved')->assertOk();
        $this->assertSame('Pending', $request->fresh()->overall_status);

        $this->cancel($request)->assertOk();

        // The Admin can no longer complete it, so no loan is created.
        $this->review($request, 'Approved', 'admin')->assertStatus(400)->assertJson(['message' => 'The borrower cancelled this request.']);
        $this->assertFalse($request->fresh()->transaction()->exists());
        $this->assertSame(3, (int) $this->equipment->fresh()->available_qty);
    }

    public function test_unknown_or_missing_requests_are_refused(): void
    {
        $this->cancel(999999)->assertNotFound();
        $this->actingAs($this->student, 'user')->postJson(route('user.requests.cancel'), [])->assertUnprocessable();
    }

    public function test_lab_personnel_cannot_use_the_cancel_action(): void
    {
        $request = $this->makePendingRequest($this->student, $this->equipment);

        $this->actingAs($this->lab, 'dept')->postJson(route('user.requests.cancel'), ['request_id' => $request->request_id])->assertUnauthorized();
        $this->assertSame('Pending', $request->fresh()->overall_status);
    }

    public function test_signed_out_visitors_cannot_cancel(): void
    {
        $request = $this->makePendingRequest($this->student, $this->equipment);

        $this->postJson(route('user.requests.cancel'), ['request_id' => $request->request_id])->assertUnauthorized();
        $this->assertSame('Pending', $request->fresh()->overall_status);
    }

    public function test_cancelled_requests_show_as_cancelled_for_the_user_and_staff(): void
    {
        $pending = $this->makePendingRequest($this->student, $this->equipment);
        $cancelled = $this->makePendingRequest($this->student, $this->equipment);
        $this->cancel($cancelled)->assertOk();

        // My Requests: Cancel button for pending requests, Cancelled filter and row.
        $this->actingAs($this->student, 'user')->get(route('user.requests'))
            ->assertOk()
            ->assertSee('id="openCancelConfirmBtn"', false)
            ->assertSee('<option value="Cancelled">Cancelled</option>', false)
            ->assertSee('data-id="' . $cancelled->request_id . '"', false)
            ->assertSee('data-status="Cancelled"', false);

        // Borrowing History lists it as Cancelled.
        $this->actingAs($this->student, 'user')->get(route('user.history'))
            ->assertOk()
            ->assertSee('data-status="Cancelled"', false);

        // Lab Personnel's Requests feed: Cancelled, with when.
        $rows = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.requests.data'))->json('requests'));
        $row = $rows->firstWhere('id', $cancelled->request_id);
        $this->assertSame('Cancelled', $row['status']);
        $this->assertNotSame('', $row['cancelledAt']);
        $this->assertSame('Pending', $rows->firstWhere('id', $pending->request_id)['status']);

        // The department dashboard counts only the request still waiting.
        $this->assertSame(1, $this->actingAs($this->lab, 'dept')->getJson(route('department.dashboard.data'))->json('stats.pendingRequests'));
    }

    public function test_reports_include_cancelled_requests(): void
    {
        $this->makePendingRequest($this->student, $this->equipment, ['date_needed' => now()->toDateString()]);
        $cancelled = $this->makePendingRequest($this->student, $this->equipment, ['date_needed' => now()->toDateString()]);
        $this->cancel($cancelled)->assertOk();

        $range = ['from' => now()->subMonth()->toDateString(), 'to' => now()->addMonth()->toDateString()];

        $borrowing = $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.reports.data', $range + ['type' => 'borrowing', 'status' => 'Cancelled']))
            ->assertOk()->json('report.rows');
        $this->assertCount(1, $borrowing);
        $this->assertSame('Cancelled', $borrowing[0]['status']);

        $activity = $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.reports.data', $range + ['type' => 'activity']))
            ->assertOk()->json('report.rows');
        $row = collect($activity)->firstWhere('borrower', 'Juan Dela Cruz');
        $this->assertSame(2, $row['requests']);
        $this->assertSame(1, $row['cancelled']);
        $this->assertSame(1, $row['pending']);
    }
}
