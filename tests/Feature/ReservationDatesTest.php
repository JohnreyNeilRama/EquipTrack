<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Date reservations: an approved request blocks the whole days from its
 * borrow date to its due date for everyone else. Dates that
 * don't clash can be booked even while the unit is out or held; the request
 * form lists the periods when no unit is free; a unit can only be released
 * once it is back in the lab.
 */
class ReservationDatesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Department $ccs;

    private DepartmentAccount $lab;

    private UserAccount $ana;

    private UserAccount $ben;

    private Equipment $projector;

    protected function setUp(): void
    {
        parent::setUp();

        // 9:00 AM on Nov 2, 2026, Philippine time.
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Asia/Manila'));

        $this->ccs = $this->makeDepartment();
        $this->lab = $this->makeLabPersonnel($this->ccs);
        $this->ana = $this->makeStudent($this->ccs, 'ana@example.com', ['first_name' => 'Ana', 'last_name' => 'Santos']);
        $this->ben = $this->makeStudent($this->ccs, 'ben@example.com', ['first_name' => 'Ben', 'last_name' => 'Reyes']);
        $this->projector = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector', 'available_qty' => 1, 'total_qty' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function submit(UserAccount $user, string $from, string $to, ?Equipment $equipment = null)
    {
        return $this->actingAs($user, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => ($equipment ?? $this->projector)->equipment_id,
            'borrow_date' => $from,
            'return_date' => $to,
            'purpose' => 'Laboratory Activity',
            'instructor_name' => 'Prof. Santos',
        ]);
    }

    private function approve(int $requestId)
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.requests.update'), [
            'request_id' => $requestId,
            'status' => 'Approved',
        ]);
    }

    /** Submitted and approved; returns the request ID. */
    private function booked(UserAccount $user, string $from, string $to, ?Equipment $equipment = null): int
    {
        $id = (int) $this->submit($user, $from, $to, $equipment)->assertOk()->json('request_id');
        $this->approve($id)->assertOk()->assertJson(['overallStatus' => 'Approved']);

        return $id;
    }

    public function test_a_later_booking_does_not_block_today(): void
    {
        $this->booked($this->ana, '2026-11-20', '2026-11-22');

        // The projector sits in the lab until Nov 20, so Ben can have it now.
        $this->booked($this->ben, '2026-11-02', '2026-11-04');

        $this->assertSame(2, BorrowTransaction::where('status', BorrowTransaction::STATUS_READY)->count());
        $this->assertSame(1, (int) $this->projector->fresh()->available_qty);
    }

    public function test_whole_day_bookings_on_consecutive_days_are_allowed(): void
    {
        $this->booked($this->ana, '2026-11-05', '2026-11-07');

        $this->booked($this->ben, '2026-11-08', '2026-11-10');
        $this->assertSame(2, BorrowTransaction::count());
    }

    public function test_a_whole_day_booking_blocks_its_last_day(): void
    {
        $this->booked($this->ana, '2026-11-05', '2026-11-07');

        // Ana has it all day on Nov 7, so Ben can't start on it.
        $this->submit($this->ben, '2026-11-07', '2026-11-09')
            ->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Sorry, Epson Projector is fully booked on Nov 5–7. Please choose other dates.',
            ]);

        $this->assertSame(1, BorrowRequest::count());
    }

    public function test_overlapping_requests_are_refused_on_submit_and_on_approval(): void
    {
        // Both submitted while the dates were still free...
        $first = (int) $this->submit($this->ana, '2026-11-05', '2026-11-07')->assertOk()->json('request_id');
        $second = (int) $this->submit($this->ben, '2026-11-06', '2026-11-08')->assertOk()->json('request_id');

        // ...but only the first can be approved.
        $this->approve($first)->assertOk();
        $this->approve($second)->assertStatus(400)->assertJson([
            'success' => false,
            'message' => 'Cannot approve: the only unit is already reserved Nov 5–7 (request #' . $first . '). Reject this request or ask the borrower for other dates.',
        ]);

        $this->assertSame('Pending', BorrowRequest::find($second)->overall_status);
        $this->assertSame(1, BorrowTransaction::count());
    }

    public function test_every_unit_can_be_booked_for_the_same_dates(): void
    {
        $laptops = $this->makeEquipment($this->ccs, ['name' => 'Dell Latitude', 'available_qty' => 2, 'total_qty' => 2]);
        $carlo = $this->makeStudent($this->ccs, 'carlo@example.com');

        $this->booked($this->ana, '2026-11-05', '2026-11-07', $laptops);
        $this->booked($this->ben, '2026-11-05', '2026-11-07', $laptops);

        $this->submit($carlo, '2026-11-06', '2026-11-06', $laptops)
            ->assertStatus(400)
            ->assertJsonFragment(['message' => 'Sorry, Dell Latitude is fully booked on Nov 5–7. Please choose other dates.']);
    }

    public function test_not_claimed_frees_the_dates_for_someone_else(): void
    {
        $first = $this->booked($this->ana, '2026-11-05', '2026-11-07');
        $this->submit($this->ben, '2026-11-05', '2026-11-07')->assertStatus(400);

        $this->actingAs($this->lab, 'dept')->postJson(route('department.releases.not-claimed'), [
            'transaction_id' => BorrowTransaction::where('request_id', $first)->value('transaction_id'),
        ])->assertOk();

        $this->booked($this->ben, '2026-11-05', '2026-11-07');
        $this->assertSame(1, (int) $this->projector->fresh()->available_qty, 'Stock never moved: the unit was never released.');
    }

    public function test_an_overdue_loan_blocks_until_it_comes_back_and_release_waits_for_it(): void
    {
        $first = $this->booked($this->ana, '2026-11-02', '2026-11-04');
        $this->releaseLoan($first);
        $second = $this->booked($this->ben, '2026-11-05', '2026-11-06');

        // Nov 5: Ana is a day late, so the projector isn't in the lab yet.
        Carbon::setTestNow(Carbon::parse('2026-11-05 09:00', 'Asia/Manila'));

        $this->actingAs($this->lab, 'dept')->postJson(route('department.releases.release'), [
            'transaction_id' => BorrowTransaction::where('request_id', $second)->value('transaction_id'),
            'id_verified' => '1',
        ])->assertStatus(400)->assertJson([
            'message' => 'Cannot release yet: the unit is still out with Ana Santos (request #' . $first . ', due Nov 4, overdue).',
        ]);

        $this->assertSame(BorrowTransaction::STATUS_READY, BorrowTransaction::where('request_id', $second)->value('status'));
        $this->assertSame(0, (int) $this->projector->fresh()->available_qty);
    }

    public function test_the_request_form_lists_the_fully_booked_dates(): void
    {
        $this->booked($this->ana, '2026-11-05', '2026-11-07');
        $this->booked($this->ben, '2026-11-20', '2026-11-22');
        $free = $this->makeEquipment($this->ccs, ['name' => 'Canon Camera']);

        $html = $this->actingAs($this->ana, 'user')->get(route('user.equipment'))->assertOk()->getContent();

        preg_match('/data-id="' . $this->projector->equipment_id . '"[^>]*data-fully-booked="([^"]*)"/', $html, $projector);
        preg_match('/data-id="' . $free->equipment_id . '"[^>]*data-fully-booked="([^"]*)"/', $html, $camera);

        $this->assertSame(['Nov 5–7', 'Nov 20–22'], json_decode(html_entity_decode($projector[1]), true));
        $this->assertSame([], json_decode(html_entity_decode($camera[1]), true));
        $this->assertStringContainsString('id="bookedDates"', $html);
        $this->assertStringContainsString('In the lab now:', $html);
    }

    public function test_items_out_on_loan_can_still_be_requested_but_not_ones_the_admin_made_unavailable(): void
    {
        $first = $this->booked($this->ana, '2026-11-02', '2026-11-04');
        $this->releaseLoan($first);

        $html = $this->actingAs($this->ben, 'user')->get(route('user.equipment'))->getContent();
        $this->assertMatchesRegularExpression('/data-id="' . $this->projector->equipment_id . '"[^>]*>\s*Request Item/', $html);

        $this->booked($this->ben, '2026-11-05', '2026-11-06');

        $this->projector->update(['status' => 'Under Maintenance']);
        $this->submit($this->ben, '2026-11-10', '2026-11-11')->assertStatus(400)->assertJson([
            'message' => 'Sorry, Epson Projector is currently under maintenance and cannot be requested.',
        ]);
    }

    public function test_the_data_fix_returns_held_units_and_clears_stock_driven_unavailability(): void
    {
        // As the old rule left things: approval took the unit out of stock
        // and switched the item to Unavailable.
        $request = $this->makePendingRequest($this->ana, $this->projector, [
            'dept_status' => 'Approved',
            'overall_status' => 'Approved',
        ]);
        BorrowTransaction::create([
            'request_id' => $request->request_id,
            'borrow_date' => '2026-11-05',
            'due_date' => '2026-11-07',
            'status' => BorrowTransaction::STATUS_READY,
        ]);
        $this->projector->update(['available_qty' => 0, 'status' => 'Unavailable']);
        $maintenance = $this->makeEquipment($this->ccs, ['name' => 'Old Scanner', 'available_qty' => 2, 'total_qty' => 2, 'status' => 'Under Maintenance']);

        $migration = require database_path('migrations/2026_11_02_000001_move_stock_hold_from_approval_to_release.php');
        $migration->up();

        $this->assertSame(1, (int) $this->projector->fresh()->available_qty);
        $this->assertSame('Available', $this->projector->fresh()->status);
        $this->assertSame('Under Maintenance', $maintenance->fresh()->status, "The admin's own statuses are kept.");
        $this->assertSame(2, (int) DB::table('equipment')->where('equipment_id', $maintenance->equipment_id)->value('available_qty'));
    }
}
