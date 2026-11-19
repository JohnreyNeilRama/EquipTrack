<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\Penalty;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Unit tracking, phase 4: on Return Confirmation, Lab Personnel tick the
 * exact units that came back Damaged, Incomplete or Lost; the others go back
 * to stock as Good. The return note and the penalty name those units. A
 * replaced Lost unit joins the equipment as a new unit with its own serial
 * number (required for Expensive equipment).
 */
class ReturnUnitsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Department $ccs;

    private DepartmentAccount $lab;

    private Admin $admin;

    private UserAccount $student;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-10-31 09:00', 'Asia/Manila'));
        Storage::fake('local');

        $this->ccs = $this->makeDepartment();
        $this->lab = $this->makeLabPersonnel($this->ccs, 'lab@example.com', 'Ana Custodian');
        $this->admin = $this->makeAdmin();
        $this->student = $this->makeStudent($this->ccs, 'juan@example.com', ['id_number' => '20260001']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Requested, approved, released (lowest-numbered units) and handed in by the borrower. */
    private function handedIn(Equipment $equipment, int $quantity, ?array $unitIds = null): BorrowTransaction
    {
        $requestId = (int) $this->actingAs($this->student, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $equipment->equipment_id,
            'quantity' => $quantity,
            'borrow_date' => '2026-10-31',
            'return_date' => '2026-11-03',
            'purpose' => 'Thesis defense',
            'instructor_name' => 'Prof. Santos',
        ])->assertOk()->json('request_id');
        $this->actingAs($this->lab, 'dept')->postJson(route('department.requests.update'), ['request_id' => $requestId, 'status' => 'Approved'])->assertOk();
        if ($equipment->isExpensive()) {
            $this->actingAs($this->admin, 'admin')->postJson(route('admin.requests.update'), ['request_id' => $requestId, 'status' => 'Approved'])->assertOk();
        }
        $loan = BorrowTransaction::where('request_id', $requestId)->firstOrFail();
        $this->actingAs($this->lab, 'dept')->postJson(route('department.releases.release'), array_filter([
            'transaction_id' => $loan->transaction_id,
            'id_verified' => '1',
            'unit_ids' => $unitIds,
        ]))->assertOk();
        $this->actingAs($this->student, 'user')->postJson(route('user.returns.store'), ['request_id' => $requestId, 'condition' => 'Good'])->assertOk();

        return $loan->fresh();
    }

    private function confirm(BorrowTransaction $loan, string $condition, array $units, array $extra = [])
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.returns.confirm'), $extra + [
            'transaction_id' => $loan->transaction_id,
            'condition' => $condition,
            'remarks' => 'Checked on return.',
            'affected_unit_ids' => array_map(fn (EquipmentUnit $u) => $u->unit_id, $units),
        ]);
    }

    private function unit(Equipment $equipment, int $number): EquipmentUnit
    {
        return EquipmentUnit::where('unit_code', EquipmentUnit::codeFor($equipment->equipment_id, $number))->firstOrFail();
    }

    public function test_only_the_ticked_units_are_damaged_and_they_are_named(): void
    {
        $kits = $this->makeEquipment($this->ccs, ['name' => 'Arduino Kit', 'serial_number' => 'SN-KIT-1', 'available_qty' => 3, 'total_qty' => 3]);
        $this->unit($kits, 2)->update(['serial_number' => 'SN-KIT-2']);
        $loan = $this->handedIn($kits, 3);

        // The first unit (not the last one, as a plain count would take) came back damaged.
        $this->confirm($loan, 'Damaged', [$this->unit($kits, 1)], [
            'penalty_type' => Penalty::TYPE_DAMAGED,
            'penalty_description' => 'Pay for the repair at the Accounting Office.',
        ])->assertOk()->assertJson(['message' => 'Return confirmed: 1 unit damaged (Under Maintenance until repaired), 2 units back in stock. A Damaged penalty was recorded.']);

        $this->assertSame(['Under Maintenance', 'Available', 'Available'], [
            $this->unit($kits, 1)->status, $this->unit($kits, 2)->status, $this->unit($kits, 3)->status,
        ]);
        $this->assertSame(
            ['SN-KIT-1' => 'Damaged', 'SN-KIT-2' => 'Good', 'EQ-' . $kits->equipment_id . '-003' => 'Good'],
            $loan->units()->get()->sortBy('unit_code')->mapWithKeys(fn ($u) => [$u->label() => $u->pivot->return_condition])->all(),
        );
        $this->assertSame(2, (int) $kits->fresh()->available_qty);

        $loan = $loan->fresh();
        $this->assertSame(1, (int) $loan->affected_qty);
        $this->assertSame('Damaged — 1 of 3 units: SN-KIT-1. Checked on return.', $loan->condition_on_return);
        $this->assertSame('Pay for the repair at the Accounting Office. (Unit: SN-KIT-1)', Penalty::firstOrFail()->description);
    }

    public function test_ticked_lost_units_leave_the_total(): void
    {
        $kits = $this->makeEquipment($this->ccs, ['name' => 'Arduino Kit', 'available_qty' => 3, 'total_qty' => 3]);
        $loan = $this->handedIn($kits, 3);

        $this->confirm($loan, 'Lost', [$this->unit($kits, 1), $this->unit($kits, 2)])
            ->assertOk()->assertJson(['message' => 'Return confirmed: 2 units lost (removed from the total quantity), 1 unit back in stock.']);

        $this->assertSame(['Lost', 'Lost', 'Available'], [
            $this->unit($kits, 1)->status, $this->unit($kits, 2)->status, $this->unit($kits, 3)->status,
        ]);
        $this->assertSame([1, 1], [(int) $kits->fresh()->available_qty, (int) $kits->fresh()->total_qty]);
    }

    public function test_only_units_handed_over_on_this_loan_can_be_ticked(): void
    {
        $kits = $this->makeEquipment($this->ccs, ['name' => 'Arduino Kit', 'available_qty' => 3, 'total_qty' => 3]);
        $loan = $this->handedIn($kits, 2);   // units 1 and 2

        $this->confirm($loan, 'Damaged', [$this->unit($kits, 3)])
            ->assertStatus(422)->assertJson(['message' => 'A chosen unit was not handed over on this loan. Close the window and try again.']);

        $this->assertTrue($loan->fresh()->isReturnPending());
        $this->assertSame('Available', $this->unit($kits, 3)->status);
        $this->assertSame(['On Loan', 'On Loan'], $loan->units()->pluck('status')->all());
    }

    public function test_the_return_window_lists_the_loans_units(): void
    {
        $kits = $this->makeEquipment($this->ccs, ['name' => 'Arduino Kit', 'serial_number' => 'SN-KIT-1', 'available_qty' => 2, 'total_qty' => 2]);
        $loan = $this->handedIn($kits, 2);

        $row = fn () => collect($this->actingAs($this->lab, 'dept')->getJson(route('department.returns.data'))->json('returns'))->firstWhere('id', $loan->transaction_id);
        $this->assertSame([
            ['id' => $this->unit($kits, 1)->unit_id, 'label' => 'SN-KIT-1', 'code' => 'EQ-' . $kits->equipment_id . '-001', 'out' => true, 'condition' => null],
            ['id' => $this->unit($kits, 2)->unit_id, 'label' => 'EQ-' . $kits->equipment_id . '-002', 'code' => 'EQ-' . $kits->equipment_id . '-002', 'out' => true, 'condition' => null],
        ], $row()['units']);

        $this->confirm($loan, 'Incomplete', [$this->unit($kits, 2)])->assertOk();
        $this->assertSame(['Good', 'Incomplete'], array_column($row()['units'], 'condition'));

        $this->actingAs($this->lab, 'dept')->get(route('department.returns'))->assertOk()
            ->assertSee('id="affectedUnitList"', false);
    }

    public function test_a_replaced_lost_unit_joins_with_its_serial_number(): void
    {
        $camera = $this->makeEquipment($this->ccs, ['name' => 'Canon Camera', 'serial_number' => 'SN-CANON-1', 'classification' => Equipment::CLASS_EXPENSIVE, 'available_qty' => 1, 'total_qty' => 1]);
        $loan = $this->handedIn($camera, 1, [$this->unit($camera, 1)->unit_id]);
        $this->confirm($loan, 'Lost', [$this->unit($camera, 1)], [
            'penalty_type' => Penalty::TYPE_LOST,
            'penalty_description' => 'Replace the camera with the same model.',
        ])->assertOk();
        $penalty = Penalty::firstOrFail();
        $this->assertSame('Replace the camera with the same model. (Unit: SN-CANON-1)', $penalty->description);
        $this->assertSame([0, 0], [(int) $camera->fresh()->available_qty, (int) $camera->fresh()->total_qty]);

        // The borrower brings a replacement.
        $this->actingAs($this->student, 'user')->post(route('user.penalties.submit'), [
            'penalty_id' => $penalty->penalty_id,
            'type' => 'Replacement',
            'settled_on' => now()->toDateString(),
            'files' => [UploadedFile::fake()->image('receipt.jpg', 600, 800)],
        ], ['Accept' => 'application/json'])->assertOk();

        $row = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.penalties.data'))->json('penalties'))->firstWhere('id', $penalty->penalty_id);
        $this->assertSame([1, true], [$row['replacementUnits'], $row['replacementSerialRequired']]);

        $approve = fn (array $serials) => $this->actingAs($this->lab, 'dept')->postJson(route('department.penalties.proof.approve'), [
            'penalty_id' => $penalty->penalty_id,
            'item_received' => '1',
            'replacement_serials' => $serials,
        ]);

        // Expensive: the replacement's serial number is required, and must be new.
        $approve([])->assertStatus(400)->assertJson(['message' => 'Enter the serial number of the replacement unit: Expensive equipment needs one for every unit.']);
        $approve(['sn-canon-1'])->assertStatus(400)->assertJson(['message' => 'Serial number "sn-canon-1" is already used by another unit.']);
        $this->assertSame(Penalty::STATUS_PENDING, $penalty->fresh()->status);

        $approve(['SN-CANON-9'])->assertOk();

        $this->assertSame(Penalty::STATUS_SETTLED, $penalty->fresh()->status);
        $this->assertSame(['Lost', 'Available'], [$this->unit($camera, 1)->status, $this->unit($camera, 2)->status]);
        $this->assertSame('SN-CANON-9', $this->unit($camera, 2)->serial_number);
        $this->assertSame([1, 1], [(int) $camera->fresh()->available_qty, (int) $camera->fresh()->total_qty]);
    }
}
