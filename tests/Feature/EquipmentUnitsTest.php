<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\EquipmentUnit;
use App\Models\UserAccount;
use App\Services\EquipmentUnits;
use App\Support\EquipmentUnitConversion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Unit tracking, phase 1: every physical unit of an equipment item has its
 * own row (automatic code EQ-{id}-{number}, its serial number when it has
 * one, its own status). Release, return, damage and loss move the exact
 * units, and the item's quantities are counted from them, so the pages
 * behave exactly as before.
 */
class EquipmentUnitsTest extends TestCase
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

        // 9:00 AM on Oct 31, 2026, Philippine time (an office day).
        Carbon::setTestNow(Carbon::parse('2026-10-31 09:00', 'Asia/Manila'));

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

    /** Requested by the student, approved, released; returns the loan. */
    private function releasedLoan(Equipment $equipment, int $quantity = 1): BorrowTransaction
    {
        $requestId = (int) $this->actingAs($this->student, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $equipment->equipment_id,
            'quantity' => $quantity,
            'borrow_date' => '2026-10-31',
            'return_date' => '2026-11-03',
            'purpose' => 'Thesis defense',
            'instructor_name' => 'Prof. Santos',
        ])->assertOk()->json('request_id');

        $this->actingAs($this->lab, 'dept')->postJson(route('department.requests.update'), [
            'request_id' => $requestId,
            'status' => 'Approved',
        ])->assertOk();

        $loan = BorrowTransaction::where('request_id', $requestId)->firstOrFail();
        $this->actingAs($this->lab, 'dept')->postJson(route('department.releases.release'), [
            'transaction_id' => $loan->transaction_id,
            'id_verified' => '1',
        ])->assertOk();

        return $loan->fresh();
    }

    /** The borrower hands it in and Lab Personnel confirm it in $condition. */
    private function returnLoan(BorrowTransaction $loan, string $condition = 'Good', ?int $affected = null)
    {
        $this->actingAs($this->student, 'user')->postJson(route('user.returns.store'), [
            'request_id' => $loan->request_id,
            'condition' => 'Good',
        ])->assertOk();

        return $this->actingAs($this->lab, 'dept')->postJson(route('department.returns.confirm'), array_filter([
            'transaction_id' => $loan->transaction_id,
            'condition' => $condition,
            'affected_units' => $affected,
            'remarks' => $condition === 'Good' ? null : 'Checked on return.',
        ]));
    }

    private function statuses(Equipment $equipment): array
    {
        return $equipment->units()->orderBy('unit_id')->pluck('status', 'unit_code')->all();
    }

    private function assertCounts(Equipment $equipment, int $available, int $total): void
    {
        $equipment = $equipment->fresh();
        $this->assertSame($available, (int) $equipment->available_qty, 'available_qty');
        $this->assertSame($total, (int) $equipment->total_qty, 'total_qty');
    }

    public function test_new_equipment_gets_its_units_with_automatic_codes(): void
    {
        $projector = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector', 'serial_number' => 'SN-EPSON-1', 'available_qty' => 2, 'total_qty' => 3]);
        $id = $projector->equipment_id;

        $this->assertSame([
            "EQ-{$id}-001" => 'Available',
            "EQ-{$id}-002" => 'Available',
            "EQ-{$id}-003" => 'Under Maintenance',
        ], $this->statuses($projector));
        // The item's serial number is on its first unit; the others have only their code.
        $this->assertSame(['SN-EPSON-1', null, null], $projector->units()->orderBy('unit_id')->pluck('serial_number')->all());
        $this->assertCounts($projector, 2, 3);
        $this->assertSame('SN-EPSON-1', $projector->units()->first()->label());
        $this->assertSame("EQ-{$id}-002", $projector->units()->orderBy('unit_id')->skip(1)->first()->label());
    }

    public function test_release_and_a_good_return_move_the_exact_units(): void
    {
        $cables = $this->makeEquipment($this->ccs, ['name' => 'HDMI Cable', 'available_qty' => 3, 'total_qty' => 3]);
        $id = $cables->equipment_id;

        $loan = $this->releasedLoan($cables, 2);

        // The lowest-numbered Available units were handed over.
        $this->assertSame(["EQ-{$id}-001", "EQ-{$id}-002"], $loan->units()->orderBy('unit_code')->pluck('unit_code')->all());
        $this->assertSame([
            "EQ-{$id}-001" => 'On Loan',
            "EQ-{$id}-002" => 'On Loan',
            "EQ-{$id}-003" => 'Available',
        ], $this->statuses($cables));
        $this->assertCounts($cables, 1, 3);

        $this->returnLoan($loan)->assertOk();

        $this->assertSame(array_fill_keys(["EQ-{$id}-001", "EQ-{$id}-002", "EQ-{$id}-003"], 'Available'), $this->statuses($cables));
        $this->assertSame(['Good', 'Good'], $loan->units()->get()->pluck('pivot.return_condition')->all());
        $this->assertCounts($cables, 3, 3);
    }

    public function test_a_damaged_unit_stays_out_of_stock_and_is_named(): void
    {
        $cables = $this->makeEquipment($this->ccs, ['name' => 'HDMI Cable', 'available_qty' => 3, 'total_qty' => 3]);
        $id = $cables->equipment_id;

        $loan = $this->releasedLoan($cables, 3);
        $this->returnLoan($loan, 'Damaged', 1)->assertOk();

        // Two came back Good; one is Under Maintenance, recorded as Damaged on the loan.
        $this->assertSame([
            "EQ-{$id}-001" => 'Available',
            "EQ-{$id}-002" => 'Available',
            "EQ-{$id}-003" => 'Under Maintenance',
        ], $this->statuses($cables));
        $this->assertSame(
            ["EQ-{$id}-001" => 'Good', "EQ-{$id}-002" => 'Good', "EQ-{$id}-003" => 'Damaged'],
            $loan->units()->orderBy('unit_code')->get()->mapWithKeys(fn ($u) => [$u->unit_code => $u->pivot->return_condition])->all(),
        );
        // The same quantities as before unit tracking: 2 available of 3.
        $this->assertCounts($cables, 2, 3);
    }

    public function test_a_lost_unit_leaves_the_total_and_its_replacement_gets_a_new_code(): void
    {
        $cables = $this->makeEquipment($this->ccs, ['name' => 'HDMI Cable', 'available_qty' => 2, 'total_qty' => 2]);
        $id = $cables->equipment_id;

        $loan = $this->releasedLoan($cables, 2);
        $this->returnLoan($loan, 'Lost', 1)->assertOk();

        $this->assertSame(["EQ-{$id}-001" => 'Available', "EQ-{$id}-002" => 'Lost'], $this->statuses($cables));
        $this->assertCounts($cables, 1, 1);

        // A replacement joins as a new unit; the lost one stays Lost (codes are never reused).
        DB::transaction(fn () => EquipmentUnits::addReplacements($cables->fresh(), 1));
        $this->assertSame(["EQ-{$id}-001" => 'Available', "EQ-{$id}-002" => 'Lost', "EQ-{$id}-003" => 'Available'], $this->statuses($cables));
        $this->assertCounts($cables, 2, 2);
    }

    public function test_quantities_typed_on_the_equipment_form_are_matched_without_touching_loans(): void
    {
        $laptops = $this->makeEquipment($this->ccs, ['name' => 'Dell Latitude', 'serial_number' => 'SN-DELL-1', 'available_qty' => 3, 'total_qty' => 3]);
        $id = $laptops->equipment_id;
        $loan = $this->releasedLoan($laptops, 1);
        $this->assertCounts($laptops, 2, 3);

        $save = fn (int $available, int $total) => $this->actingAs($this->admin, 'admin')->postJson(route('admin.equipment.save'), [
            'equipment_id' => $id,
            'name' => 'Dell Latitude',
            'brand' => 'Dell',
            'serial_number' => 'SN-DELL-1',
            'available_qty' => $available,
            'total_qty' => $total,
            'status' => 'Available',
            'classification' => 'Not Expensive',
            'category_id' => $laptops->category_id,
            'department_id' => $this->ccs->department_id,
        ])->assertOk();

        // More units: new codes are added.
        $save(4, 5);
        $this->assertCounts($laptops, 4, 5);
        $this->assertSame(5, $laptops->units()->count());
        $this->assertSame("EQ-{$id}-005", $laptops->units()->orderByDesc('unit_id')->value('unit_code'));

        // Fewer units: spare ones go, the unit on loan is never touched.
        $save(1, 2);
        $this->assertCounts($laptops, 1, 2);
        $this->assertSame(['On Loan'], $loan->units()->pluck('status')->all());
        $this->assertSame("EQ-{$id}-001", $loan->units()->value('unit_code'));

        // It can still come back.
        $this->returnLoan($loan)->assertOk();
        $this->assertCounts($laptops, 2, 2);
    }

    public function test_a_unit_that_was_lent_is_retired_not_deleted_when_the_total_goes_down(): void
    {
        $laptops = $this->makeEquipment($this->ccs, ['name' => 'Dell Latitude', 'available_qty' => 1, 'total_qty' => 1]);
        $this->returnLoan($this->releasedLoan($laptops, 1))->assertOk();

        $laptops->fresh()->update(['available_qty' => 0, 'total_qty' => 0]);

        $unit = $laptops->units()->firstOrFail();
        $this->assertSame('Retired', $unit->status);
        $this->assertSame(1, $unit->transactions()->count(), 'Its borrowing history is kept.');
        $this->assertCounts($laptops, 0, 0);
    }

    public function test_existing_equipment_is_converted_into_units(): void
    {
        $projector = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector', 'serial_number' => 'SN-EPSON-1', 'available_qty' => 4, 'total_qty' => 4]);
        $loan = $this->releasedLoan($projector, 1);
        $id = $projector->equipment_id;

        // As the database was before unit tracking: no units, 1 out on loan,
        // 1 available, and 2 the old quantities don't account for (e.g. returned damaged).
        DB::table('borrow_transaction_unit')->delete();
        DB::table('equipment_unit')->delete();
        DB::table('equipment')->where('equipment_id', $id)->update(['available_qty' => 1, 'total_qty' => 4]);

        $this->assertSame(['created' => 4, 'to_check' => 2], EquipmentUnitConversion::run());
        // (The migration also copied the item's old serial number to unit 1;
        // that column no longer exists once every migration has run.)
        $projector->units()->where('unit_code', "EQ-{$id}-001")->update(['serial_number' => 'SN-EPSON-1']);

        $this->assertSame([
            "EQ-{$id}-001" => 'On Loan',
            "EQ-{$id}-002" => 'Available',
            "EQ-{$id}-003" => 'Under Maintenance',
            "EQ-{$id}-004" => 'Under Maintenance',
        ], $this->statuses($projector));
        $this->assertSame('SN-EPSON-1', $projector->units()->where('unit_code', "EQ-{$id}-001")->value('serial_number'));
        $this->assertSame(["EQ-{$id}-001"], $loan->units()->pluck('unit_code')->all());
        $this->assertSame(2, $projector->units()->where('remarks', EquipmentUnit::CHECK_REMARK)->count());
        // The saved quantities are not changed by the conversion.
        $this->assertCounts($projector, 1, 4);

        // Running it again changes nothing.
        $this->assertSame(['created' => 0, 'to_check' => 0], EquipmentUnitConversion::run());
        $this->assertSame(4, $projector->units()->count());

        // The converted loan returns normally.
        $this->returnLoan($loan)->assertOk();
        $this->assertCounts($projector, 2, 4);
    }

    public function test_the_check_command_lists_units_that_need_attention(): void
    {
        $camera = $this->makeEquipment($this->ccs, [
            'name' => 'Canon Camera',
            'serial_number' => 'SN-CANON-1',
            'classification' => Equipment::CLASS_EXPENSIVE,
            'available_qty' => 1,
            'total_qty' => 2,
        ]);
        $id = $camera->equipment_id;
        DB::table('equipment_unit')->delete();
        EquipmentUnitConversion::run();
        $camera->units()->where('unit_code', "EQ-{$id}-001")->update(['serial_number' => 'SN-CANON-1']);

        $this->artisan('equiptrack:check-units')
            ->expectsOutputToContain('Units to check in the lab (1)')
            ->expectsOutputToContain("EQ-{$id}-002")
            ->expectsOutputToContain('Expensive units without a serial number (1)')
            ->expectsOutputToContain('Quantities that differ from the units (0)')
            ->assertSuccessful();
    }

    public function test_deleting_equipment_without_history_removes_its_units(): void
    {
        $cables = $this->makeEquipment($this->ccs, ['name' => 'HDMI Cable', 'available_qty' => 2, 'total_qty' => 2]);

        $this->actingAs($this->admin, 'admin')->postJson(route('admin.equipment.delete'), ['equipment_id' => $cables->equipment_id])->assertOk();

        $this->assertSame(0, EquipmentUnit::where('equipment_id', $cables->equipment_id)->count());
    }
}
