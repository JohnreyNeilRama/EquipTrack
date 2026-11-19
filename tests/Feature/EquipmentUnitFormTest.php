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
 * Unit tracking, phase 2: the Add / Edit Equipment window lists every unit
 * (automatic code, serial number, status). Expensive units need a serial
 * number; Not Expensive ones may have none. Serial numbers are unique across
 * all units. A unit that was ever lent is retired, never removed, and units
 * on loan keep their status. Quantities are counted from the units.
 */
class EquipmentUnitFormTest extends TestCase
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
        $this->student = $this->makeStudent($this->ccs, 'juan@example.com', ['id_number' => '20260001']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** What the window sends: the item's details, its units and the units to remove. */
    private function payload(array $units, array $extra = []): array
    {
        return $extra + [
            'name' => 'Epson Projector',
            'brand' => 'Epson',
            'image' => '/storage/images/projector.jpg',
            'status' => 'Available',
            'classification' => Equipment::CLASS_NOT_EXPENSIVE,
            'category_id' => $this->makeEquipment($this->ccs)->category_id,
            'department_id' => $this->ccs->department_id,
            'units_mode' => '1',
            'units' => $units,
        ];
    }

    private function adminSave(array $payload)
    {
        return $this->actingAs($this->admin, 'admin')->postJson(route('admin.equipment.save'), $payload);
    }

    private function labSave(array $payload)
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.equipment.save'), $payload);
    }

    /** The item's units as the window lists them, to send back with changes. */
    private function unitsOf(Equipment $equipment, array $changes = []): array
    {
        return $equipment->units()->orderBy('unit_id')->get()->map(fn ($u) => array_merge([
            'unit_id' => $u->unit_id,
            'serial_number' => $u->serial_number,
            'status' => $u->status,
        ], $changes[$u->unit_code] ?? []))->all();
    }

    private function released(Equipment $equipment): BorrowTransaction
    {
        $requestId = (int) $this->actingAs($this->student, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $equipment->equipment_id,
            'borrow_date' => '2026-10-31',
            'return_date' => '2026-11-03',
            'purpose' => 'Thesis defense',
            'instructor_name' => 'Prof. Santos',
        ])->assertOk()->json('request_id');
        $this->actingAs($this->lab, 'dept')->postJson(route('department.requests.update'), ['request_id' => $requestId, 'status' => 'Approved'])->assertOk();
        $loan = BorrowTransaction::where('request_id', $requestId)->firstOrFail();
        $this->actingAs($this->lab, 'dept')->postJson(route('department.releases.release'), ['transaction_id' => $loan->transaction_id, 'id_verified' => '1'])->assertOk();

        return $loan->fresh();
    }

    public function test_admin_adds_equipment_with_its_units_and_the_quantities_are_counted(): void
    {
        $this->adminSave($this->payload([
            ['serial_number' => ' SN-EPSON-1 '],
            ['serial_number' => ''],
            ['serial_number' => '', 'status' => 'Under Maintenance'],
        ]))->assertOk()->assertJson(['success' => true]);

        $projector = Equipment::where('name', 'Epson Projector')->firstOrFail();
        $id = $projector->equipment_id;
        $this->assertSame([
            ['unit_code' => "EQ-{$id}-001", 'serial_number' => 'SN-EPSON-1', 'status' => 'Available'],
            ['unit_code' => "EQ-{$id}-002", 'serial_number' => null, 'status' => 'Available'],
            ['unit_code' => "EQ-{$id}-003", 'serial_number' => null, 'status' => 'Under Maintenance'],
        ], $projector->units()->orderBy('unit_id')->get(['unit_code', 'serial_number', 'status'])->toArray());
        $this->assertSame(2, (int) $projector->available_qty);
        $this->assertSame(3, (int) $projector->total_qty);
        // The item's own serial column follows its first unit.
        $this->assertSame('SN-EPSON-1', $projector->serial_number);

        $this->assertSame('Units: 3 added.', AuditTrail::where('affected_entity', 'equipment:' . $id)->value('details'));
    }

    public function test_lab_personnel_add_equipment_whose_first_unit_has_no_serial_number(): void
    {
        $this->labSave($this->payload([['serial_number' => ''], ['serial_number' => '']]))->assertOk();

        $cables = Equipment::where('name', 'Epson Projector')->firstOrFail();
        $this->assertSame($this->ccs->department_id, (int) $cables->department_id);
        $this->assertSame(2, (int) $cables->total_qty);
        $this->assertSame('EQ-' . $cables->equipment_id . '-001', $cables->serial_number);
    }

    public function test_expensive_equipment_needs_a_serial_number_for_every_unit(): void
    {
        $this->adminSave($this->payload(
            [['serial_number' => 'SN-CANON-1'], ['serial_number' => '']],
            ['name' => 'Canon Camera', 'classification' => Equipment::CLASS_EXPENSIVE],
        ))->assertStatus(400)->assertJson(['message' => 'Expensive equipment needs a serial number for every unit. Missing: New unit 2.']);

        $this->assertFalse(Equipment::where('name', 'Canon Camera')->exists());

        $this->adminSave($this->payload(
            [['serial_number' => 'SN-CANON-1'], ['serial_number' => 'SN-CANON-2']],
            ['name' => 'Canon Camera', 'classification' => Equipment::CLASS_EXPENSIVE],
        ))->assertOk();
    }

    public function test_serial_numbers_must_be_unique_and_not_look_like_codes(): void
    {
        $this->makeEquipment($this->ccs, ['name' => 'Dell Latitude', 'serial_number' => 'SN-DELL-1', 'available_qty' => 1, 'total_qty' => 1]);

        $this->adminSave($this->payload([['serial_number' => 'SN-1'], ['serial_number' => 'sn-1']]))
            ->assertStatus(400)->assertJson(['message' => 'Serial number "sn-1" is entered twice (New unit 1 and New unit 2). Each unit needs its own.']);

        $this->adminSave($this->payload([['serial_number' => 'sn-dell-1']]))
            ->assertStatus(400)->assertJsonFragment(['success' => false])
            ->assertJson(fn ($json) => $json->where('message', fn ($m) => str_starts_with($m, 'Serial number "SN-DELL-1" is already used by EQ-'))->etc());

        $this->adminSave($this->payload([['serial_number' => 'EQ-9-001']]))
            ->assertStatus(400)->assertJson(['message' => 'New unit 1: "EQ-9-001" looks like an automatic unit code. Leave the serial number empty if the unit has none.']);

        $this->adminSave($this->payload([]))->assertStatus(400)->assertJson(['message' => 'Add at least one unit.']);
    }

    public function test_editing_units_corrects_serials_sets_statuses_adds_and_removes(): void
    {
        $projector = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector', 'serial_number' => 'SN-TYPO', 'available_qty' => 3, 'total_qty' => 3]);
        $id = $projector->equipment_id;

        $units = $this->unitsOf($projector, [
            "EQ-{$id}-001" => ['serial_number' => 'SN-EPSON-1'],
            "EQ-{$id}-002" => ['status' => 'Under Maintenance'],
        ]);
        $removed = array_pop($units);   // EQ-…-003, never lent
        $units[] = ['serial_number' => 'SN-EPSON-4'];

        $this->adminSave($this->payload($units, [
            'equipment_id' => $id,
            'remove_units' => [$removed['unit_id']],
            'category_id' => $projector->category_id,
        ]))->assertOk();

        $this->assertSame([
            "EQ-{$id}-001" => ['SN-EPSON-1', 'Available'],
            "EQ-{$id}-002" => [null, 'Under Maintenance'],
            "EQ-{$id}-004" => ['SN-EPSON-4', 'Available'],
        ], $projector->units()->orderBy('unit_id')->get()->mapWithKeys(fn ($u) => [$u->unit_code => [$u->serial_number, $u->status]])->all());
        $this->assertSame(2, (int) $projector->fresh()->available_qty);
        $this->assertSame(3, (int) $projector->fresh()->total_qty);
        $this->assertSame('SN-EPSON-1', $projector->fresh()->serial_number);
        $this->assertSame(
            'Units: 1 added, 1 removed, 1 serial number changed, 1 status change.',
            AuditTrail::where('affected_entity', 'equipment:' . $id)->latest('audit_id')->value('details'),
        );
    }

    public function test_a_lent_unit_is_retired_not_removed_and_a_unit_on_loan_keeps_its_status(): void
    {
        $laptops = $this->makeEquipment($this->ccs, ['name' => 'Dell Latitude', 'serial_number' => 'SN-DELL-1', 'available_qty' => 2, 'total_qty' => 2]);
        $id = $laptops->equipment_id;
        $loan = $this->released($laptops);   // EQ-…-001 is now On Loan

        // Removing it is refused (it has history).
        $units = $this->unitsOf($laptops);
        $onLoan = array_shift($units);
        $this->adminSave($this->payload($units, ['equipment_id' => $id, 'remove_units' => [$onLoan['unit_id']], 'category_id' => $laptops->category_id]))
            ->assertStatus(400)->assertJson(['message' => "EQ-{$id}-001 was borrowed before, so it can't be removed (its history must be kept). Set it to Retired instead."]);

        // A status sent for a unit on loan is ignored; its serial number can still be corrected.
        $this->adminSave($this->payload($this->unitsOf($laptops, [
            "EQ-{$id}-001" => ['status' => 'Retired', 'serial_number' => 'SN-DELL-01'],
        ]), ['equipment_id' => $id, 'category_id' => $laptops->category_id]))->assertOk();
        $this->assertSame(['On Loan', 'SN-DELL-01'], [$loan->units()->value('status'), $loan->units()->value('serial_number')]);

        // After the return it can be retired: it leaves the total, its history stays.
        $this->actingAs($this->student, 'user')->postJson(route('user.returns.store'), ['request_id' => $loan->request_id, 'condition' => 'Good'])->assertOk();
        $this->actingAs($this->lab, 'dept')->postJson(route('department.returns.confirm'), ['transaction_id' => $loan->transaction_id, 'condition' => 'Good'])->assertOk();
        $this->adminSave($this->payload($this->unitsOf($laptops, ["EQ-{$id}-001" => ['status' => 'Retired']]), ['equipment_id' => $id, 'category_id' => $laptops->category_id]))->assertOk();

        $this->assertSame('Retired', EquipmentUnit::where('unit_code', "EQ-{$id}-001")->value('status'));
        $this->assertSame(1, (int) $laptops->fresh()->total_qty);
        $this->assertSame(1, $loan->units()->count());
    }

    public function test_switching_to_expensive_needs_every_serial_and_lab_edits_use_the_saved_classification(): void
    {
        $tripods = $this->makeEquipment($this->ccs, ['name' => 'Tripod', 'serial_number' => 'SN-TRIPOD-1', 'available_qty' => 2, 'total_qty' => 2]);
        $id = $tripods->equipment_id;

        $this->adminSave($this->payload($this->unitsOf($tripods), [
            'equipment_id' => $id, 'classification' => Equipment::CLASS_EXPENSIVE, 'category_id' => $tripods->category_id,
        ]))->assertStatus(400)->assertJson(['message' => "Expensive equipment needs a serial number for every unit. Missing: EQ-{$id}-002."]);
        $this->assertSame(Equipment::CLASS_NOT_EXPENSIVE, $tripods->fresh()->classification);

        $this->adminSave($this->payload($this->unitsOf($tripods, ["EQ-{$id}-002" => ['serial_number' => 'SN-TRIPOD-2']]), [
            'equipment_id' => $id, 'classification' => Equipment::CLASS_EXPENSIVE, 'category_id' => $tripods->category_id,
        ]))->assertOk();

        // Lab Personnel can't change the classification, and an Expensive unit can't lose its serial number.
        $this->labSave(array_diff_key($this->payload($this->unitsOf($tripods, ["EQ-{$id}-002" => ['serial_number' => '']]), [
            'equipment_id' => $id, 'category_id' => $tripods->category_id,
        ]), ['classification' => true]))->assertStatus(400)->assertJson(['message' => "Expensive equipment needs a serial number for every unit. Missing: EQ-{$id}-002."]);
    }

    public function test_back_to_available_clears_the_check_remark(): void
    {
        $projector = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector', 'available_qty' => 1, 'total_qty' => 1]);
        $unit = $projector->units()->firstOrFail();
        $unit->update(['status' => 'Under Maintenance', 'remarks' => EquipmentUnit::CHECK_REMARK]);

        $this->adminSave($this->payload($this->unitsOf($projector, [$unit->unit_code => ['status' => 'Available']]), [
            'equipment_id' => $projector->equipment_id, 'category_id' => $projector->category_id,
        ]))->assertOk();

        $this->assertSame(['Available', null], [$unit->fresh()->status, $unit->fresh()->remarks]);
    }

    public function test_another_departments_units_cannot_be_edited(): void
    {
        $other = $this->makeEquipment($this->makeDepartment('College of Criminal Justice', 'CCJ'), ['name' => 'Body Camera', 'available_qty' => 1, 'total_qty' => 1]);

        $this->labSave($this->payload([], ['equipment_id' => $other->equipment_id, 'category_id' => $other->category_id]))->assertNotFound();
        $this->assertSame(1, $other->units()->count());
    }

    public function test_the_window_and_data_feeds_carry_the_units(): void
    {
        $projector = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector', 'serial_number' => 'SN-EPSON-1', 'available_qty' => 2, 'total_qty' => 2]);
        $this->released($projector);

        $row = collect($this->actingAs($this->admin, 'admin')->getJson(route('admin.equipment.data'))->json('equipment'))->firstWhere('equipment_id', $projector->equipment_id);
        $this->assertSame([
            ['unit_code' => 'EQ-' . $projector->equipment_id . '-001', 'serial_number' => 'SN-EPSON-1', 'status' => 'On Loan', 'was_lent' => true],
            ['unit_code' => 'EQ-' . $projector->equipment_id . '-002', 'serial_number' => null, 'status' => 'Available', 'was_lent' => false],
        ], array_map(fn ($u) => array_intersect_key($u, array_flip(['unit_code', 'serial_number', 'status', 'was_lent'])), $row['units']));

        $deptRow = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.equipment.data'))->json('equipment'))->firstWhere('id', $projector->equipment_id);
        $this->assertCount(2, $deptRow['units']);

        foreach ([[$this->admin, 'admin', 'admin.equipment'], [$this->lab, 'dept', 'department.equipment']] as [$who, $guard, $route]) {
            $this->actingAs($who, $guard)->get(route($route))->assertOk()
                ->assertSee('id="eqUnitsList"', false)
                ->assertSee('name="units_mode"', false)
                ->assertDontSee('id="eqFormSerial"', false)
                ->assertDontSee('id="eqFormAvail"', false);
        }
    }
}
