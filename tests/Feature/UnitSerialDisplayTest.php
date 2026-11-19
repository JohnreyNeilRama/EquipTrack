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
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Unit tracking, phase 5: serial numbers live only on units. Pages that list
 * a loan show the units handed over on it; equipment cards, Monitoring and
 * the Inventory report show an item's units; searches find any unit's serial
 * number or code. An item's old single serial number column is gone (older
 * clients that still send one put it on the item's first unit).
 */
class UnitSerialDisplayTest extends TestCase
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

    private function readyLoan(Equipment $equipment, int $quantity): BorrowTransaction
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

        return BorrowTransaction::where('request_id', $requestId)->firstOrFail();
    }

    private function release(BorrowTransaction $loan): BorrowTransaction
    {
        $this->actingAs($this->lab, 'dept')->postJson(route('department.releases.release'), ['transaction_id' => $loan->transaction_id, 'id_verified' => '1'])->assertOk();

        return $loan->fresh();
    }

    /** Arduino kits: SN-KIT-1, SN-KIT-2 and a third without a serial number. */
    private function kits(): Equipment
    {
        $kits = $this->makeEquipment($this->ccs, ['name' => 'Arduino Kit', 'serial_number' => 'SN-KIT-1', 'available_qty' => 3, 'total_qty' => 3]);
        $kits->units()->where('unit_code', 'EQ-' . $kits->equipment_id . '-002')->update(['serial_number' => 'SN-KIT-2']);

        return $kits;
    }

    public function test_the_item_has_no_serial_column_and_older_clients_serials_go_on_its_first_unit(): void
    {
        $this->assertFalse(Schema::hasColumn('equipment', 'serial_number'));

        $kits = $this->makeEquipment($this->ccs, ['name' => 'Arduino Kit', 'serial_number' => 'SN-OLD', 'available_qty' => 2, 'total_qty' => 2]);
        $first = $kits->units()->orderBy('unit_id')->first();
        $this->assertSame('SN-OLD', $first->serial_number);
        $this->assertSame('SN-OLD', $kits->fresh()->serial_number);

        // An older client corrects it…
        $kits->fresh()->update(['serial_number' => 'SN-NEW']);
        $this->assertSame('SN-NEW', $first->fresh()->serial_number);

        // …and sending back a unit's own code changes nothing.
        $first->update(['serial_number' => null]);
        $kits->fresh()->update(['serial_number' => $first->unit_code]);
        $this->assertNull($first->fresh()->serial_number);
        $this->assertSame($first->unit_code, $kits->fresh()->serial_number);
    }

    public function test_equipment_cards_show_the_units_and_search_finds_any_serial_or_code(): void
    {
        $kits = $this->kits();
        $code3 = 'EQ-' . $kits->equipment_id . '-003';

        foreach ([
            [$this->admin, 'admin', 'admin.equipment.data', 'equipment_id'],
            [$this->lab, 'dept', 'department.equipment.data', 'id'],
        ] as [$who, $guard, $route, $key]) {
            $row = collect($this->actingAs($who, $guard)->getJson(route($route))->json('equipment'))->firstWhere($key, $kits->equipment_id);
            $this->assertSame("SN-KIT-1, SN-KIT-2, {$code3}", $row['unit_labels']);
            foreach (['SN-KIT-1', 'SN-KIT-2', 'EQ-' . $kits->equipment_id . '-001', $code3] as $text) {
                $this->assertStringContainsString($text, $row['unit_search']);
            }
        }

        // Long lists end in "+N more"; retired units are left out.
        $kits->units()->where('unit_code', $code3)->update(['status' => EquipmentUnit::STATUS_RETIRED]);
        $this->assertSame('SN-KIT-1 +1 more', $kits->fresh()->unitLabels(1));
    }

    public function test_loan_pages_show_the_units_handed_over(): void
    {
        $kits = $this->kits();
        $loan = $this->readyLoan($kits, 2);

        $releaseRow = fn () => collect($this->actingAs($this->lab, 'dept')->getJson(route('department.releases.data'))->json('releases'))->firstWhere('id', $loan->transaction_id);
        $this->assertSame('Unit chosen at release', $releaseRow()['serial']);

        $loan = $this->release($loan);
        $this->assertSame('SN-KIT-1, SN-KIT-2', $releaseRow()['serial']);

        // Monitoring: the borrower's units, and the item's units when nothing is out.
        $idle = $this->makeEquipment($this->ccs, ['name' => 'Tripod', 'serial_number' => 'SN-TRIPOD-1', 'available_qty' => 1, 'total_qty' => 1]);
        $rows = collect($this->actingAs($this->lab, 'dept')->get(route('department.monitoring', ['status' => 'all']))->viewData('dbMonitoring'));
        $this->assertSame('SN-KIT-1, SN-KIT-2', $rows->firstWhere('equipment', 'Arduino Kit')['serial']);
        $this->assertSame('SN-TRIPOD-1', $rows->firstWhere('equipment', $idle->name)['serial']);
        // Its search finds a unit's serial number.
        $found = collect($this->actingAs($this->lab, 'dept')->get(route('department.monitoring', ['status' => 'all', 'search' => 'sn-kit-2']))->viewData('dbMonitoring'));
        $this->assertSame(['Arduino Kit'], $found->pluck('equipment')->all());

        // Return Confirmation and the penalty name the units too.
        $this->actingAs($this->student, 'user')->postJson(route('user.returns.store'), ['request_id' => $loan->request_id, 'condition' => 'Good'])->assertOk();
        $returnRow = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.returns.data'))->json('returns'))->firstWhere('id', $loan->transaction_id);
        $this->assertSame('SN-KIT-1, SN-KIT-2', $returnRow['equipmentId']);

        $this->actingAs($this->lab, 'dept')->postJson(route('department.returns.confirm'), [
            'transaction_id' => $loan->transaction_id,
            'condition' => 'Damaged',
            'remarks' => 'Cracked board',
            'affected_unit_ids' => [$kits->units()->where('serial_number', 'SN-KIT-2')->value('unit_id')],
            'penalty_type' => Penalty::TYPE_DAMAGED,
            'penalty_description' => 'Pay for the repair.',
        ])->assertOk();

        $penaltyRow = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.penalties.data'))->json('penalties'))->first();
        $this->assertSame('SN-KIT-2', $penaltyRow['serial']);
    }

    public function test_the_inventory_report_lists_each_items_units(): void
    {
        $kits = $this->kits();
        $kits->units()->where('unit_code', 'EQ-' . $kits->equipment_id . '-003')->update(['status' => EquipmentUnit::STATUS_MAINTENANCE]);

        $payload = $this->actingAs($this->admin, 'admin')->getJson(route('admin.reports.data', ['type' => 'inventory']))->assertOk()->json();
        $row = collect($payload['report']['rows'])->firstWhere('equipment', 'Arduino Kit');

        $this->assertSame('SN-KIT-1, SN-KIT-2, EQ-' . $kits->equipment_id . '-003', $row['serial']);
        $this->assertSame(1, $row['maintenance']);
        $this->assertContains('Units (Serial No.)', array_column($payload['report']['columns'], 'label'));
        $this->assertContains('Under Maintenance', array_column($payload['report']['columns'], 'label'));
    }
}
