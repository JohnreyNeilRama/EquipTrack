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
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Borrowers choose how many units to borrow. Expensive equipment (its
 * classification, set by the admin) is lent one unit per request; other
 * equipment up to the units the lab can lend. The server checks it on
 * submit, approval and release, and a return of several units records how
 * many were Damaged / Incomplete / Lost so stock stays accurate.
 */
class RequestQuantityTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Department $ccs;

    private DepartmentAccount $lab;

    private UserAccount $ana;

    private UserAccount $ben;

    private Equipment $kits;

    protected function setUp(): void
    {
        parent::setUp();

        // Monday, Nov 2, 2026, 9:00 AM Philippine time.
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Asia/Manila'));

        $this->ccs = $this->makeDepartment();
        $this->lab = $this->makeLabPersonnel($this->ccs);
        $this->ana = $this->makeStudent($this->ccs, 'ana@example.com', ['first_name' => 'Ana', 'last_name' => 'Santos']);
        $this->ben = $this->makeStudent($this->ccs, 'ben@example.com', ['first_name' => 'Ben', 'last_name' => 'Reyes']);
        $this->kits = $this->makeEquipment($this->ccs, ['name' => 'Arduino Kit', 'available_qty' => 5, 'total_qty' => 5]);
    }

    private function submit(UserAccount $user, ?int $quantity, ?Equipment $equipment = null, string $from = '2026-11-05', string $to = '2026-11-05')
    {
        return $this->actingAs($user, 'user')->postJson(route('user.borrow.store'), array_filter([
            'equipment_id' => ($equipment ?? $this->kits)->equipment_id,
            'borrow_type' => $from === $to ? 'within_day' : 'multiple_days',
            'borrow_date' => $from,
            'return_date' => $to,
            'quantity' => $quantity,
            'purpose' => 'Laboratory Activity',
            'instructor_name' => 'Prof. Santos',
        ], fn ($value) => $value !== null));
    }

    private function approve(int $requestId)
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.requests.update'), [
            'request_id' => $requestId,
            'status' => 'Approved',
        ]);
    }

    private function release(int $requestId)
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.releases.release'), [
            'transaction_id' => BorrowTransaction::where('request_id', $requestId)->value('transaction_id'),
            'id_verified' => '1',
        ]);
    }

    /** Approved, released on the pickup day and handed back by the borrower: waiting for confirmation. */
    private function returnedLoan(int $quantity): int
    {
        $id = (int) $this->submit($this->ana, $quantity)->assertOk()->json('request_id');
        $this->approve($id)->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-11-05 08:00', 'Asia/Manila'));
        $this->release($id)->assertOk();
        $this->actingAs($this->ana, 'user')->postJson(route('user.returns.store'), [
            'request_id' => $id,
            'condition' => 'Good',
            'remarks' => '',
        ])->assertOk();

        return $id;
    }

    private function confirm(int $requestId, string $condition, ?int $affected = null, string $remarks = 'Checked')
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.returns.confirm'), array_filter([
            'transaction_id' => BorrowTransaction::where('request_id', $requestId)->value('transaction_id'),
            'condition' => $condition,
            'remarks' => $remarks,
            'affected_units' => $affected,
        ], fn ($value) => $value !== null));
    }

    public function test_several_units_of_regular_equipment_can_be_requested(): void
    {
        $id = (int) $this->submit($this->ana, 3)->assertOk()->json('request_id');
        $this->assertSame(3, (int) BorrowRequest::find($id)->quantity);

        // Without a quantity the request is for 1 unit, as before.
        $other = (int) $this->submit($this->ben, null)->assertOk()->json('request_id');
        $this->assertSame(1, (int) BorrowRequest::find($other)->quantity);
    }

    public function test_expensive_equipment_is_one_unit_per_request_whatever_the_form_sends(): void
    {
        $camera = $this->makeEquipment($this->ccs, ['name' => 'Canon Camera', 'available_qty' => 4, 'total_qty' => 4, 'classification' => Equipment::CLASS_EXPENSIVE]);

        $this->submit($this->ana, 2, $camera)
            ->assertStatus(422)
            ->assertJson(['message' => 'Canon Camera is high-value equipment: you can borrow 1 unit per request.']);
        $this->assertSame(0, BorrowRequest::count());

        $this->submit($this->ana, 1, $camera)->assertOk();
    }

    public function test_requests_cannot_exceed_the_units_the_lab_can_lend(): void
    {
        $this->submit($this->ana, 6)
            ->assertStatus(422)
            ->assertJson(['message' => 'You can request up to 5 units of Arduino Kit at a time.']);

        $this->submit($this->ana, 0)->assertStatus(422)->assertJsonValidationErrors(['quantity' => 'Please request at least 1 unit.']);

        // The optional cap per request.
        config(['equiptrack.max_units_per_request' => 2]);
        $this->submit($this->ana, 3)
            ->assertStatus(422)
            ->assertJson(['message' => 'You can request up to 2 units of Arduino Kit at a time.']);
        $this->submit($this->ana, 2)->assertOk();
    }

    public function test_only_the_units_still_free_on_those_dates_can_be_requested(): void
    {
        $first = (int) $this->submit($this->ana, 4)->assertOk()->json('request_id');
        $this->approve($first)->assertOk();

        $this->submit($this->ben, 2)
            ->assertStatus(400)
            ->assertJson(['message' => 'Only 1 of 5 units of Arduino Kit are free on Nov 5. Request 1 or fewer, or choose other dates.']);

        $this->submit($this->ben, 1)->assertOk();
        // Other dates are free.
        $this->submit($this->ben, 5, null, '2026-11-06', '2026-11-06')->assertOk();
    }

    public function test_approval_rechecks_the_units_so_dates_are_never_overbooked(): void
    {
        // Both submitted while 3 units were free for each...
        $first = (int) $this->submit($this->ana, 3)->assertOk()->json('request_id');
        $second = (int) $this->submit($this->ben, 3)->assertOk()->json('request_id');

        // ...but only the first can be approved: 2 units are left.
        $this->approve($first)->assertOk();
        $this->approve($second)
            ->assertStatus(400)
            ->assertJson(['message' => 'Cannot approve: this request asks for 3 units of Arduino Kit, but only 2 of 5 are free on these dates. Reject it so the borrower can request fewer units or other dates.']);

        $this->assertSame('Pending', BorrowRequest::find($second)->overall_status);
        $this->assertSame(5, (int) $this->kits->fresh()->available_qty, 'Approval reserves dates; stock moves at release.');
    }

    public function test_approval_refuses_several_units_of_expensive_equipment(): void
    {
        $camera = $this->makeEquipment($this->ccs, ['name' => 'Canon Camera', 'available_qty' => 4, 'total_qty' => 4, 'classification' => Equipment::CLASS_EXPENSIVE]);
        // Written straight to the database, past the form's checks.
        $request = $this->makePendingRequest($this->ana, $camera, [
            'quantity' => 2,
            'date_needed' => '2026-11-05',
            'borrow_date' => '2026-11-05',
            'return_date' => '2026-11-05',
            'due_date' => '2026-11-05',
            'admin_status' => 'Approved',
        ]);

        $this->approve($request->request_id)
            ->assertStatus(400)
            ->assertJson(['message' => 'Cannot approve: Canon Camera is high-value equipment, lent 1 unit per request, and this request asks for 2. Reject it so the borrower can request 1 unit.']);
        $this->assertSame(0, BorrowTransaction::count());
    }

    public function test_release_takes_every_unit_and_waits_until_all_are_in_the_lab(): void
    {
        $id = (int) $this->submit($this->ana, 3)->assertOk()->json('request_id');
        $this->approve($id)->assertOk();

        // Two units are away (e.g. still out with someone else).
        $this->kits->update(['available_qty' => 2]);
        Carbon::setTestNow(Carbon::parse('2026-11-05 08:00', 'Asia/Manila'));
        $this->release($id)
            ->assertStatus(400)
            ->assertJson(['message' => 'Cannot release yet: this loan is for 3 units of Arduino Kit, but only 2 are in the lab right now. All units are released together.']);

        $this->kits->update(['available_qty' => 5]);
        $this->release($id)->assertOk();
        $this->assertSame(2, (int) $this->kits->fresh()->available_qty);
    }

    public function test_a_good_return_puts_every_unit_back(): void
    {
        $id = $this->returnedLoan(3);
        $this->assertSame(2, (int) $this->kits->fresh()->available_qty);

        $this->confirm($id, 'Good', null, '')->assertOk();
        $this->assertSame(5, (int) $this->kits->fresh()->available_qty);
        $this->assertNull(BorrowTransaction::where('request_id', $id)->value('affected_qty'));
    }

    public function test_only_the_damaged_units_stay_out_of_stock(): void
    {
        $id = $this->returnedLoan(3);

        $this->confirm($id, 'Damaged', 1, 'Cracked board')
            ->assertOk()
            ->assertJson(['message' => 'Return confirmed: 1 unit damaged (kept out of the available stock until the admin updates it), 2 units back in stock.']);

        $equipment = $this->kits->fresh();
        $this->assertSame(4, (int) $equipment->available_qty, '2 good units came back; 1 damaged stays out.');
        $this->assertSame(5, (int) $equipment->total_qty);

        $loan = BorrowTransaction::where('request_id', $id)->first();
        $this->assertSame(1, (int) $loan->affected_qty);
        $this->assertSame(1, $loan->affectedUnits());
        $this->assertSame('Damaged — 1 of 3 units. Cracked board', $loan->condition_on_return);
    }

    public function test_only_the_lost_units_leave_the_total(): void
    {
        $id = $this->returnedLoan(3);

        $this->confirm($id, 'Lost', 1, 'One kit missing')
            ->assertOk()
            ->assertJson(['message' => 'Return confirmed: 1 unit lost (removed from the total quantity), 2 units back in stock.']);

        $equipment = $this->kits->fresh();
        $this->assertSame(4, (int) $equipment->total_qty);
        $this->assertSame(4, (int) $equipment->available_qty);
    }

    public function test_units_affected_defaults_to_all_and_cannot_exceed_the_loan(): void
    {
        $id = $this->returnedLoan(3);

        $this->confirm($id, 'Damaged', 4)
            ->assertStatus(422)
            ->assertJson(['message' => 'This loan has 3 units, so at most 3 can be damaged.']);
        $this->assertTrue(BorrowTransaction::where('request_id', $id)->first()->isReturnPending(), 'Nothing changed.');

        // Not sent: every unit is in that condition.
        $this->confirm($id, 'Damaged', null)->assertOk();
        $this->assertSame(2, (int) $this->kits->fresh()->available_qty, 'All 3 damaged units stay out.');
        $this->assertSame(3, (int) BorrowTransaction::where('request_id', $id)->value('affected_qty'));
    }

    public function test_quantities_are_shown_to_every_role(): void
    {
        $camera = $this->makeEquipment($this->ccs, ['name' => 'Canon Camera', 'classification' => Equipment::CLASS_EXPENSIVE]);
        $form = $this->actingAs($this->ana, 'user')->get(route('user.equipment'))->assertOk()->getContent();
        $this->assertStringContainsString('id="borrowQuantity"', $form);
        $this->assertMatchesRegularExpression('/data-id="' . $this->kits->equipment_id . '"[^>]*data-max-units="5"[^>]*data-expensive="0"/', $form);
        $this->assertMatchesRegularExpression('/data-id="' . $camera->equipment_id . '"[^>]*data-max-units="1"[^>]*data-expensive="1"/', $form);

        $id = (int) $this->submit($this->ana, 3)->assertOk()->json('request_id');

        // Borrower: My Requests.
        $this->actingAs($this->ana, 'user')->get(route('user.requests'))->assertSee('Laptop · 3 units');

        // Lab Personnel review the request with its quantity.
        $row = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.requests.data'))->json('requests'))->firstWhere('id', $id);
        $this->assertSame(3, $row['quantity']);

        $this->approve($id)->assertOk();
        $notice = collect($this->actingAs($this->ana, 'user')->getJson(route('user.notifications'))->json('notifications'))
            ->firstWhere('title', 'Request Approved');
        $this->assertStringStartsWith('Your request to borrow 3 units of Arduino Kit has been approved.', $notice['message']);

        // Monitoring (Admin and Department) and Borrowing History.
        $admin = $this->makeAdmin();
        $this->actingAs($admin, 'admin')->get(route('admin.monitoring'))->assertSee('"quantity":3', false);
        $this->actingAs($this->lab, 'dept')->get(route('department.monitoring'))->assertSee('"quantity":3', false);
        $this->actingAs($this->ana, 'user')->get(route('user.history'))->assertSee('Laptop · 3 units');
        $this->actingAs($this->ana, 'user')->get(route('user.returns'))->assertSee('Laptop · 3 units');

        // Reports count units.
        $report = $this->actingAs($admin, 'admin')->getJson(route('admin.reports.data', [
            'type' => 'borrowing', 'from' => '2026-11-01', 'to' => '2026-11-30',
        ]))->json('report.rows');
        $this->assertSame(3, collect($report)->first()['qty']);
    }
}
