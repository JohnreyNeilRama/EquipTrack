<?php

namespace Tests\Feature;

use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Requests can be submitted and approved any day, but equipment is handed
 * over only on an office day, from the pickup date on (the unit may be
 * reserved for someone else before it), and before the due date's deadline.
 * Not Claimed stays available.
 */
class ReleaseScheduleTest extends TestCase
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

    /** Submitted and approved: Ready for Pickup. */
    private function readyLoan(string $from, string $to): BorrowTransaction
    {
        $id = (int) $this->actingAs($this->ana, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $this->projector->equipment_id,
            'borrow_type' => $from === $to ? 'within_day' : 'multiple_days',
            'borrow_date' => $from,
            'return_date' => $to,
            'purpose' => 'Laboratory Activity',
            'instructor_name' => 'Prof. Santos',
        ])->assertOk()->json('request_id');

        $this->actingAs($this->lab, 'dept')->postJson(route('department.requests.update'), [
            'request_id' => $id,
            'status' => 'Approved',
        ])->assertOk();

        return BorrowTransaction::where('request_id', $id)->firstOrFail();
    }

    private function release(BorrowTransaction $loan)
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.releases.release'), [
            'transaction_id' => $loan->transaction_id,
            'id_verified' => '1',
        ]);
    }

    private function row(BorrowTransaction $loan): array
    {
        return collect($this->actingAs($this->lab, 'dept')->getJson(route('department.releases.data'))->json('releases'))
            ->firstWhere('id', $loan->transaction_id);
    }

    public function test_equipment_cannot_be_released_before_its_pickup_date(): void
    {
        $loan = $this->readyLoan('2026-11-12', '2026-11-13');

        $this->release($loan)
            ->assertStatus(400)
            ->assertJson(['message' => 'This equipment is reserved from Thu, Nov 12, 2026. It can be released from that date.']);
        $this->assertSame(BorrowTransaction::STATUS_READY, $loan->fresh()->status);
        $this->assertSame(1, (int) $this->projector->fresh()->available_qty);

        // The page shows the button disabled with the same reason.
        $this->assertSame('This equipment is reserved from Thu, Nov 12, 2026. It can be released from that date.', $this->row($loan)['releaseBlocked']);

        Carbon::setTestNow(Carbon::parse('2026-11-12 08:00', 'Asia/Manila'));
        $this->assertNull($this->row($loan)['releaseBlocked']);
        $this->release($loan)->assertOk();
        $this->assertSame(BorrowTransaction::STATUS_ACTIVE, $loan->fresh()->status);
    }

    public function test_equipment_cannot_be_released_on_a_closed_day(): void
    {
        // Picked up Saturday to Monday, but nobody released it on Saturday.
        $loan = $this->readyLoan('2026-11-07', '2026-11-09');

        Carbon::setTestNow(Carbon::parse('2026-11-08 10:00', 'Asia/Manila'));
        $this->release($loan)
            ->assertStatus(400)
            ->assertJson(['message' => "The equipment office is closed on Sundays, so equipment can't be released today."]);
        $this->assertSame(BorrowTransaction::STATUS_READY, $loan->fresh()->status);

        Carbon::setTestNow(Carbon::parse('2026-11-09 08:00', 'Asia/Manila'));
        $this->release($loan)->assertOk();
    }

    public function test_release_works_on_the_pickup_day_including_saturday(): void
    {
        $loan = $this->readyLoan('2026-11-07', '2026-11-07');

        $this->assertNull($this->row($loan)['releaseBlocked']);
        $this->release($loan)->assertOk();
    }

    private function notClaimed(BorrowTransaction $loan)
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.releases.not-claimed'), [
            'transaction_id' => $loan->transaction_id,
        ]);
    }

    private function cancel(BorrowTransaction $loan, ?string $reason)
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.releases.cancel'), array_filter([
            'transaction_id' => $loan->transaction_id,
            'reason' => $reason,
        ]));
    }

    public function test_not_claimed_waits_for_the_pickup_date(): void
    {
        $loan = $this->readyLoan('2026-11-12', '2026-11-12');

        $message = 'The pickup date is Thu, Nov 12, 2026, so the borrower has not missed it yet. To free the dates now, cancel the reservation instead.';
        $this->notClaimed($loan)->assertStatus(400)->assertJson(['message' => $message]);
        $this->assertSame($message, $this->row($loan)['notClaimedBlocked']);
        $this->assertSame(BorrowTransaction::STATUS_READY, $loan->fresh()->status);

        // On the pickup day the borrower can miss it.
        Carbon::setTestNow(Carbon::parse('2026-11-12 17:00', 'Asia/Manila'));
        $this->assertNull($this->row($loan)['notClaimedBlocked']);
        $this->notClaimed($loan)->assertOk();
        $this->assertSame(BorrowTransaction::STATUS_NOT_CLAIMED, $loan->fresh()->status);
    }

    public function test_a_reservation_can_be_cancelled_before_pickup_with_a_reason(): void
    {
        $loan = $this->readyLoan('2026-11-12', '2026-11-13');

        $this->cancel($loan, null)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason' => 'Please give the reason for cancelling this reservation. The borrower will see it.']);

        $this->cancel($loan, 'The projector lamp broke. Please request another unit.')
            ->assertOk()
            ->assertJson(['message' => 'Reservation cancelled. The borrower has been notified and the reserved dates are free again.']);

        $loan = $loan->fresh();
        $this->assertSame(BorrowTransaction::STATUS_CANCELLED, $loan->status);
        $this->assertSame('The projector lamp broke. Please request another unit.', $loan->cancel_reason);
        $this->assertSame($this->lab->dept_acc_id, (int) $loan->cancelled_by);
        $this->assertNotNull($loan->cancelled_at);
        $this->assertSame(1, (int) $this->projector->fresh()->available_qty, 'Stock never moved.');

        // It can't be released, cancelled again or marked Not Claimed any more.
        $this->cancel($loan, 'Again')->assertStatus(400)->assertJson(['message' => 'This reservation was already cancelled.']);
        Carbon::setTestNow(Carbon::parse('2026-11-12 09:00', 'Asia/Manila'));
        $this->release($loan)->assertStatus(400);

        // The dates are free for someone else.
        $ben = $this->makeStudent($this->ccs, 'ben@example.com', ['first_name' => 'Ben', 'last_name' => 'Reyes']);
        $this->actingAs($ben, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $this->projector->equipment_id,
            'borrow_type' => 'within_day',
            'borrow_date' => '2026-11-12',
            'purpose' => 'Laboratory Activity',
            'instructor_name' => 'Prof. Santos',
        ])->assertOk();
    }

    public function test_the_borrower_is_told_why_their_reservation_was_cancelled(): void
    {
        $loan = $this->readyLoan('2026-11-12', '2026-11-12');
        $this->cancel($loan, 'Needed for the department exam.')->assertOk();

        $notice = collect($this->actingAs($this->ana, 'user')->getJson(route('user.notifications'))->json('notifications'))
            ->firstWhere('title', 'Reservation Cancelled');
        $this->assertSame('The lab cancelled your reservation for Epson Projector. Reason: Needed for the department exam. You can request it again.', $notice['message']);

        $this->actingAs($this->ana, 'user')->get(route('user.history'))
            ->assertOk()
            ->assertSee('Reservation Cancelled')
            ->assertSee('Cancelled by the lab: Needed for the department exam.');

        // Nothing to return, and it no longer counts as an active borrow.
        $this->actingAs($this->ana, 'user')->get(route('user.returns'))->assertOk()->assertDontSee('data-id="' . $loan->request_id . '"', false);
        $this->assertSame(0, $this->actingAs($this->ana, 'user')->getJson(route('user.dashboard.data'))->json('stats.activeBorrows'));
    }

    public function test_cancellations_are_listed_reported_and_audited(): void
    {
        $loan = $this->readyLoan('2026-11-12', '2026-11-12');
        $this->cancel($loan, 'Equipment under repair.')->assertOk();

        $row = $this->row($loan);
        $this->assertSame('Cancelled', $row['status']);
        $this->assertSame('Equipment under repair.', $row['cancelReason']);
        $this->assertSame('Lab Custodian', $row['cancelledBy']);

        $admin = $this->makeAdmin();
        $report = $this->actingAs($admin, 'admin')->getJson(route('admin.reports.data', [
            'type' => 'borrowing', 'from' => '2026-11-01', 'to' => '2026-11-30', 'status' => 'Reservation Cancelled',
        ]))->assertOk()->json('report.rows');
        $this->assertCount(1, $report);

        $this->assertDatabaseHas('audit_trail', [
            'dept_acc_id' => $this->lab->dept_acc_id,
            'details' => 'Reason: Equipment under repair.',
        ]);
        $action = (string) \App\Models\AuditTrail::where('details', 'Reason: Equipment under repair.')->value('action');
        $this->assertSame('Cancelled Reservation', \App\Services\AuditCatalog::classify($action)['label']);
        $this->assertSame('Releases', \App\Services\AuditCatalog::classify($action)['module']);
    }

    public function test_the_release_page_offers_cancel_before_pickup_and_both_after(): void
    {
        $this->readyLoan('2026-11-12', '2026-11-12');
        $html = $this->actingAs($this->lab, 'dept')->get(route('department.releases'))->assertOk()->getContent();

        $this->assertStringContainsString('id="cancelFields"', $html);
        $this->assertStringContainsString('<option value="Cancelled">Cancelled</option>', $html);
        $this->assertStringContainsString('Not Claimed / Cancelled (last 30 days)', $html);
        $this->assertStringContainsString(json_encode(route('department.releases.cancel')), $html);
    }

    public function test_the_view_window_holds_the_details_and_the_rarer_actions(): void
    {
        $loan = $this->readyLoan('2026-11-12', '2026-11-12');
        $html = $this->actingAs($this->lab, 'dept')->get(route('department.releases'))->assertOk()->getContent();

        // The View window, with Not Claimed and Cancel reservation in its footer.
        $this->assertStringContainsString('id="viewModal"', $html);
        $this->assertStringContainsString('data-view-action="not-claimed"', $html);
        $this->assertStringContainsString('data-view-action="cancel"', $html);
        // The table itself only offers Release and View.
        $this->assertStringContainsString('data-view="${r.id}"', $html);
        $this->assertStringNotContainsString('data-not-claimed="${r.id}"', $html);
        $this->assertStringNotContainsString('data-cancel="${r.id}"', $html);

        $row = $this->row($loan);
        $this->assertSame('Prof. Santos', $row['instructor']);
        $this->assertSame('College of Computer Studies', $row['department']);
        $this->assertStringStartsWith('Lab Custodian · ', $row['deptApproval']);
        $this->assertNull($row['adminApproval'], 'Not Expensive: no admin approval.');
        $this->assertNotSame('—', $row['requestedAt']);
    }

    public function test_the_release_page_greys_out_release_with_the_reason(): void
    {
        $this->readyLoan('2026-11-12', '2026-11-12');

        $html = $this->actingAs($this->lab, 'dept')->get(route('department.releases'))->assertOk()->getContent();

        $this->assertStringContainsString('"releaseBlocked":"This equipment is reserved from Thu, Nov 12, 2026. It can be released from that date."', $html);
        // The muted chip that replaces Release shows the short label.
        $this->assertStringContainsString('"releaseBlockedLabel":"From Nov 12"', $html);
        $this->assertStringContainsString('rel-btn--wait', $html);
    }

    public function test_the_short_release_labels_follow_the_same_rules(): void
    {
        $early = $this->readyLoan('2026-11-12', '2026-11-12');
        $this->assertSame('From Nov 12', $this->row($early)['releaseBlockedLabel']);

        // Sunday: the office is closed.
        Carbon::setTestNow(Carbon::parse('2026-11-08 10:00', 'Asia/Manila'));
        $this->assertSame('Office closed', $this->row($early)['releaseBlockedLabel']);

        // On the pickup day it can be released: no label.
        Carbon::setTestNow(Carbon::parse('2026-11-12 08:00', 'Asia/Manila'));
        $this->assertNull($this->row($early)['releaseBlockedLabel']);

        // After the due date's deadline.
        Carbon::setTestNow(Carbon::parse('2026-11-12 19:30', 'Asia/Manila'));
        $this->assertSame('Past due date', $this->row($early)['releaseBlockedLabel']);
    }
}
