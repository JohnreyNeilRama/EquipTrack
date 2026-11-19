<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Unit tracking, phase 3: at release, Lab Personnel hand over exact units.
 * Expensive equipment must name the unit (its serial number is checked on
 * the unit); other equipment may choose, or the lowest-numbered units in the
 * lab are taken. Only units of this item that are in the lab can be chosen.
 */
class ReleaseUnitChoiceTest extends TestCase
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

        $this->ccs = $this->makeDepartment();
        $this->lab = $this->makeLabPersonnel($this->ccs, 'lab@example.com', 'Ana Custodian');
        $this->admin = $this->makeAdmin();
        $this->student = $this->makeStudent($this->ccs, 'juan@example.com', ['id_number' => '20260001', 'first_name' => 'Juan', 'last_name' => 'Cruz']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Requested and fully approved: Ready for Pickup. */
    private function readyLoan(Equipment $equipment, int $quantity = 1): BorrowTransaction
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

        return BorrowTransaction::where('request_id', $requestId)->firstOrFail();
    }

    private function release(BorrowTransaction $loan, ?array $unitIds = null)
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.releases.release'), array_filter([
            'transaction_id' => $loan->transaction_id,
            'id_verified' => '1',
            'unit_ids' => $unitIds,
        ]));
    }

    private function unit(Equipment $equipment, int $number): EquipmentUnit
    {
        return EquipmentUnit::where('unit_code', EquipmentUnit::codeFor($equipment->equipment_id, $number))->firstOrFail();
    }

    private function row(BorrowTransaction $loan): array
    {
        return collect($this->actingAs($this->lab, 'dept')->getJson(route('department.releases.data'))->json('releases'))
            ->firstWhere('id', $loan->transaction_id);
    }

    public function test_expensive_equipment_is_released_by_naming_the_exact_unit(): void
    {
        $camera = $this->makeEquipment($this->ccs, ['name' => 'Canon Camera', 'serial_number' => 'SN-CANON-1', 'classification' => Equipment::CLASS_EXPENSIVE, 'available_qty' => 2, 'total_qty' => 2]);
        $this->unit($camera, 2)->update(['serial_number' => 'SN-CANON-2']);
        $loan = $this->readyLoan($camera);

        $this->release($loan)->assertStatus(400)
            ->assertJson(['message' => 'Choose the unit you are handing over: check the serial number on the unit itself.']);
        $this->assertSame(BorrowTransaction::STATUS_READY, $loan->fresh()->status);

        // Unit 2 is the one handed over, not the lowest-numbered one.
        $this->release($loan, [$this->unit($camera, 2)->unit_id])->assertOk();

        $this->assertSame(['SN-CANON-2'], $loan->units()->pluck('serial_number')->all());
        $this->assertSame('On Loan', $this->unit($camera, 2)->status);
        $this->assertSame('Available', $this->unit($camera, 1)->status);
        $this->assertSame(1, (int) $camera->fresh()->available_qty);
        $this->assertStringEndsWith('(school ID checked). Units: SN-CANON-2.', AuditTrail::where('action', 'like', 'Released borrow request%')->value('details'));
    }

    public function test_other_equipment_takes_the_chosen_units_or_the_lowest_numbered_ones(): void
    {
        $cables = $this->makeEquipment($this->ccs, ['name' => 'HDMI Cable', 'available_qty' => 4, 'total_qty' => 4]);
        $id = $cables->equipment_id;

        $chosen = $this->readyLoan($cables, 2);
        $this->release($chosen, [$this->unit($cables, 3)->unit_id, $this->unit($cables, 4)->unit_id])->assertOk();
        $this->assertSame(["EQ-{$id}-003", "EQ-{$id}-004"], $chosen->units()->orderBy('unit_code')->pluck('unit_code')->all());

        $automatic = $this->readyLoan($cables, 1);
        $this->release($automatic)->assertOk();
        $this->assertSame(["EQ-{$id}-001"], $automatic->units()->pluck('unit_code')->all());
        $this->assertSame(1, (int) $cables->fresh()->available_qty);
    }

    public function test_only_this_items_units_in_the_lab_and_the_right_number_can_be_chosen(): void
    {
        $cables = $this->makeEquipment($this->ccs, ['name' => 'HDMI Cable', 'available_qty' => 3, 'total_qty' => 3]);
        $other = $this->makeEquipment($this->ccs, ['name' => 'VGA Cable', 'available_qty' => 1, 'total_qty' => 1]);
        $this->unit($cables, 3)->update(['status' => 'Under Maintenance']);
        \App\Services\EquipmentUnits::syncCounts($cables);
        $loan = $this->readyLoan($cables, 2);

        $this->release($loan, [$this->unit($cables, 1)->unit_id])->assertStatus(400)
            ->assertJson(['message' => 'Choose exactly 2 units to hand over.']);
        $this->release($loan, [$this->unit($cables, 1)->unit_id, $this->unit($cables, 1)->unit_id])->assertStatus(400)
            ->assertJson(['message' => 'Choose exactly 2 units to hand over.']);
        $this->release($loan, [$this->unit($cables, 1)->unit_id, $this->unit($other, 1)->unit_id])->assertStatus(400)
            ->assertJson(['message' => "A chosen unit is not one of this equipment's units. Close the window and try again."]);
        $this->release($loan, [$this->unit($cables, 1)->unit_id, $this->unit($cables, 3)->unit_id])->assertStatus(400)
            ->assertJson(['message' => 'EQ-' . $cables->equipment_id . "-003 is Under Maintenance, so it can't be handed over. Choose another unit."]);

        // Nothing moved.
        $this->assertSame(BorrowTransaction::STATUS_READY, $loan->fresh()->status);
        $this->assertSame(0, $loan->units()->count());
        $this->assertSame(2, (int) $cables->fresh()->available_qty);

        $this->release($loan, [$this->unit($cables, 1)->unit_id, $this->unit($cables, 2)->unit_id])->assertOk();
    }

    public function test_the_release_window_lists_the_units_in_the_lab_and_view_shows_those_handed_over(): void
    {
        $camera = $this->makeEquipment($this->ccs, ['name' => 'Canon Camera', 'serial_number' => 'SN-CANON-1', 'classification' => Equipment::CLASS_EXPENSIVE, 'available_qty' => 2, 'total_qty' => 3]);
        $this->unit($camera, 2)->update(['serial_number' => 'SN-CANON-2']);
        $loan = $this->readyLoan($camera);

        $row = $this->row($loan);
        $this->assertTrue($row['expensive']);
        $this->assertSame([
            ['id' => $this->unit($camera, 1)->unit_id, 'code' => 'EQ-' . $camera->equipment_id . '-001', 'serial' => 'SN-CANON-1'],
            ['id' => $this->unit($camera, 2)->unit_id, 'code' => 'EQ-' . $camera->equipment_id . '-002', 'serial' => 'SN-CANON-2'],
        ], $row['unitChoices']);   // unit 3 is Under Maintenance, so it is not offered

        $this->release($loan, [$this->unit($camera, 1)->unit_id])->assertOk();
        $row = $this->row($loan);
        $this->assertSame([], $row['unitChoices']);
        $this->assertSame(['SN-CANON-1'], $row['units']);

        $this->actingAs($this->lab, 'dept')->get(route('department.releases'))->assertOk()
            ->assertSee('id="relUnitsList"', false)
            ->assertSee("formData.append('unit_ids[]', id)", false);
    }
}
