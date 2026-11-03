<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Requests nobody reviewed before their due date's return deadline (7:00 PM)
 * become Expired: they leave review queues, counts and alerts, can no longer
 * be approved or rejected, and the borrower is told to submit a new request.
 */
class ExpiredRequestTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Department $ccs;

    private DepartmentAccount $lab;

    private Admin $admin;

    private UserAccount $ana;

    private Equipment $projector;

    protected function setUp(): void
    {
        parent::setUp();

        // 9:00 AM on Nov 2, 2026, Philippine time.
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Asia/Manila'));

        $this->ccs = $this->makeDepartment();
        $this->lab = $this->makeLabPersonnel($this->ccs);
        $this->admin = $this->makeAdmin();
        $this->ana = $this->makeStudent($this->ccs, 'ana@example.com', ['first_name' => 'Ana', 'last_name' => 'Santos']);
        $this->projector = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector', 'available_qty' => 1, 'total_qty' => 1]);
    }

    private function submit(string $type, string $from, ?string $to = null, ?Equipment $equipment = null): int
    {
        return (int) $this->actingAs($this->ana, 'user')->postJson(route('user.borrow.store'), array_filter([
            'equipment_id' => ($equipment ?? $this->projector)->equipment_id,
            'borrow_type' => $type,
            'borrow_date' => $from,
            'return_date' => $to,
            'purpose' => 'Laboratory Activity',
            'instructor_name' => 'Prof. Santos',
        ], fn ($value) => $value !== null))->assertOk()->json('request_id');
    }

    private function deptReview(int $id, string $status)
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.requests.update'), array_filter([
            'request_id' => $id,
            'status' => $status,
            'reject_reason' => $status === 'Rejected' ? 'Not available for that class.' : null,
        ]));
    }

    /** Moves the clock and lets the next page load expire stale requests again. */
    private function moveClockTo(string $philippineTime): void
    {
        Carbon::setTestNow(Carbon::parse($philippineTime, 'Asia/Manila'));
        Cache::forget('equiptrack:expire-stale-requests');
    }

    public function test_a_request_is_still_pending_until_its_return_deadline(): void
    {
        $id = $this->submit('within_day', '2026-11-02');

        $this->moveClockTo('2026-11-02 18:59');
        $this->actingAs($this->lab, 'dept')->getJson(route('department.requests.data'))->assertOk();
        $this->assertSame('Pending', BorrowRequest::find($id)->overall_status);

        $this->deptReview($id, 'Approved')->assertOk();
        $this->assertSame(1, BorrowTransaction::where('request_id', $id)->count());
    }

    public function test_an_unreviewed_request_expires_after_its_return_deadline_and_leaves_the_queue(): void
    {
        $id = $this->submit('within_day', '2026-11-02');
        $this->assertSame(1, $this->actingAs($this->lab, 'dept')->getJson(route('department.dashboard.data'))->json('stats.pendingRequests'));

        $this->moveClockTo('2026-11-02 19:01');
        $dashboard = $this->actingAs($this->lab, 'dept')->getJson(route('department.dashboard.data'))->json();

        $this->assertSame(BorrowRequest::STATUS_EXPIRED, BorrowRequest::find($id)->overall_status);
        $this->assertSame(0, $dashboard['stats']['pendingRequests']);

        $titles = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.notifications'))->json('notifications'))->pluck('title');
        $this->assertNotContains('New Reservation Request', $titles);

        $row = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.requests.data'))->json('requests'))->firstWhere('id', $id);
        // Approve / Reject buttons only show for Pending rows.
        $this->assertSame('Expired', $row['status']);
        $this->assertSame('Nov 02, 2026 by 7:00 PM', $row['expiresAt']);
    }

    public function test_an_expired_request_cannot_be_approved_or_rejected_even_before_it_was_marked(): void
    {
        $approve = $this->submit('within_day', '2026-11-02');
        $reject = $this->submit('within_day', '2026-11-03');

        // Days later. The once-a-minute expiry has just run, so the review
        // action itself must notice the dates are over.
        Carbon::setTestNow(Carbon::parse('2026-11-05 10:00', 'Asia/Manila'));
        Cache::put('equiptrack:expire-stale-requests', true, 60);
        $this->assertSame('Pending', BorrowRequest::find($approve)->overall_status);

        $this->deptReview($approve, 'Approved')
            ->assertStatus(400)
            ->assertJson(['message' => 'This request expired: its return date (Nov 02, 2026 by 7:00 PM) passed before it was approved. The borrower can submit a new request.']);
        $this->deptReview($reject, 'Rejected')->assertStatus(400);

        $this->assertSame(0, BorrowTransaction::count());
        $this->assertSame(BorrowRequest::STATUS_EXPIRED, BorrowRequest::find($approve)->overall_status);
        $this->assertSame(BorrowRequest::STATUS_EXPIRED, BorrowRequest::find($reject)->overall_status);
        $this->assertSame('Pending', BorrowRequest::find($approve)->dept_status);
    }

    public function test_the_admin_cannot_approve_an_expired_expensive_request(): void
    {
        $camera = $this->makeEquipment($this->ccs, ['name' => 'Canon Camera', 'classification' => Equipment::CLASS_EXPENSIVE]);
        $id = $this->submit('within_day', '2026-11-02', null, $camera);
        // The department approved in time; the admin did not.
        $this->deptReview($id, 'Approved')->assertOk();
        $this->assertSame('Pending', BorrowRequest::find($id)->overall_status);

        $this->moveClockTo('2026-11-03 08:00');
        $this->actingAs($this->admin, 'admin')->postJson(route('admin.requests.update'), [
            'request_id' => $id,
            'status' => 'Approved',
        ])->assertStatus(400)->assertJsonFragment(['success' => false]);

        $this->assertSame(BorrowRequest::STATUS_EXPIRED, BorrowRequest::find($id)->overall_status);
        $this->assertSame(0, BorrowTransaction::count());
    }

    public function test_a_multiple_days_request_can_still_be_approved_after_its_borrow_date(): void
    {
        $id = $this->submit('multiple_days', '2026-11-02', '2026-11-05');

        $this->moveClockTo('2026-11-03 10:00');
        $this->deptReview($id, 'Approved')->assertOk();

        $this->moveClockTo('2026-11-06 10:00');
        $this->actingAs($this->lab, 'dept')->getJson(route('department.requests.data'))->assertOk();
        // Approved requests never expire (their loan is handled by release / Not Claimed).
        $this->assertSame('Approved', BorrowRequest::find($id)->overall_status);
    }

    public function test_the_borrower_sees_expired_and_is_told_to_request_again(): void
    {
        $id = $this->submit('within_day', '2026-11-02');

        $this->moveClockTo('2026-11-03 08:00');
        $notice = collect($this->actingAs($this->ana, 'user')->getJson(route('user.notifications'))->json('notifications'))
            ->firstWhere('key', 'request-' . $id . '-expired');
        $this->assertSame('Request Expired', $notice['title']);
        $this->assertSame('Your request to borrow Epson Projector expired: it was not reviewed before Nov 02, 2026 by 7:00 PM. You can submit a new request.', $notice['message']);

        $this->actingAs($this->ana, 'user')->get(route('user.requests'))
            ->assertOk()
            ->assertSee('data-status="Expired"', false)
            ->assertSee('Not reviewed before Nov 02, 2026 by 7:00 PM. You can submit a new request.')
            ->assertSee('<option value="Expired">Expired</option>', false);

        $this->actingAs($this->ana, 'user')->get(route('user.history'))->assertOk()->assertSee('Expired');

        // An expired request can't be cancelled, and the dates are free again.
        $this->actingAs($this->ana, 'user')->postJson(route('user.requests.cancel'), ['request_id' => $id])->assertStatus(400);
        $this->submit('within_day', '2026-11-03');
    }

    public function test_reports_list_and_filter_expired_requests(): void
    {
        $id = $this->submit('within_day', '2026-11-02');
        $this->moveClockTo('2026-11-03 08:00');

        $rows = $this->actingAs($this->admin, 'admin')->getJson(route('admin.reports.data', [
            'type' => 'borrowing', 'from' => '2026-11-01', 'to' => '2026-11-30', 'status' => 'Expired',
        ]))->assertOk()->json('report.rows');

        $this->assertCount(1, $rows);
        $this->assertSame(BorrowRequest::STATUS_EXPIRED, BorrowRequest::find($id)->overall_status);
    }

    public function test_staff_are_told_to_mark_an_unclaimed_loan_past_its_return_date_not_claimed(): void
    {
        $id = $this->submit('within_day', '2026-11-02');
        $this->deptReview($id, 'Approved')->assertOk();

        $this->moveClockTo('2026-11-03 08:00');
        $notice = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.notifications'))->json('notifications'))
            ->firstWhere('type', 'pickup');

        $this->assertSame('Pickup Date Passed', $notice['title']);
        $this->assertStringEndsWith('its return date has passed. Mark it Not Claimed.', $notice['message']);
    }
}
