<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\UserAccount;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Borrowing history is never deleted together with an account or an item:
 * equipment is retired, users are deactivated, the database refuses the
 * delete (ON DELETE RESTRICT), and a department account can't be deleted
 * while its department has open work. Also covers the backup command.
 */
class BorrowHistoryProtectionTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    private Department $department;

    private DepartmentAccount $lab;

    private Admin $admin;

    private UserAccount $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = $this->makeDepartment();
        $this->lab = $this->makeLabPersonnel($this->department);
        $this->admin = $this->makeAdmin();
        $this->student = $this->makeStudent($this->department);
    }

    // ------------------------------------------------------------------
    // Database rules
    // ------------------------------------------------------------------

    public function test_the_database_refuses_to_delete_a_user_or_item_with_borrow_requests(): void
    {
        $equipment = $this->makeEquipment($this->department);
        $this->makePendingRequest($this->student, $equipment, ['overall_status' => 'Rejected']);

        $this->assertDeleteRefused(fn () => $this->student->delete());
        $this->assertDeleteRefused(fn () => $equipment->delete());

        $this->assertNotNull($this->student->fresh());
        $this->assertNotNull($equipment->fresh());
    }

    public function test_equipment_status_accepts_retired(): void
    {
        $equipment = $this->makeEquipment($this->department, ['status' => Equipment::STATUS_RETIRED]);

        $this->assertTrue($equipment->fresh()->isRetired());
    }

    // ------------------------------------------------------------------
    // Equipment: delete only without history, otherwise retire
    // ------------------------------------------------------------------

    public function test_equipment_with_borrowing_history_is_not_deleted_on_either_page(): void
    {
        $equipment = $this->makeEquipment($this->department, ['name' => 'Old Projector']);
        $this->makeLoan($this->student, $equipment, '2026-10-22', ['status' => BorrowTransaction::STATUS_RETURNED]);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.equipment.delete'), ['equipment_id' => $equipment->equipment_id])
            ->assertStatus(400)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'Retired'));

        $this->actingAs($this->lab, 'dept')
            ->postJson(route('department.equipment.delete'), ['equipment_id' => $equipment->equipment_id])
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->assertNotNull($equipment->fresh());
    }

    public function test_equipment_never_requested_can_still_be_deleted(): void
    {
        $equipment = $this->makeEquipment($this->department);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.equipment.delete'), ['equipment_id' => $equipment->equipment_id])
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNull($equipment->fresh());
    }

    public function test_equipment_is_retired_once_nothing_is_open(): void
    {
        $equipment = $this->makeEquipment($this->department, ['name' => 'Old Projector']);
        $this->makeLoan($this->student, $equipment, '2026-10-22', ['status' => BorrowTransaction::STATUS_RETURNED]);

        $this->saveAsAdmin($equipment, Equipment::STATUS_RETIRED)->assertOk()->assertJson(['success' => true]);
        $this->assertTrue($equipment->fresh()->isRetired());

        // And back into service.
        $this->saveAsAdmin($equipment, 'Available')->assertOk();
        $this->assertFalse($equipment->fresh()->isRetired());
    }

    public function test_equipment_with_a_pending_request_or_open_loan_cannot_be_retired(): void
    {
        $pending = $this->makeEquipment($this->department, ['name' => 'Requested Laptop']);
        $this->makePendingRequest($this->student, $pending);

        $onLoan = $this->makeEquipment($this->department, ['name' => 'Borrowed Laptop']);
        $this->makeLoan($this->student, $onLoan, now()->addDays(2)->toDateString());

        $this->saveAsAdmin($pending, Equipment::STATUS_RETIRED)
            ->assertStatus(400)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'pending borrow requests'));

        $this->saveAsDepartment($onLoan, Equipment::STATUS_RETIRED)
            ->assertStatus(400)
            ->assertJsonPath('message', fn (string $m) => str_contains($m, 'on loan'));

        $this->assertSame('Available', $pending->fresh()->status);
        $this->assertSame('Available', $onLoan->fresh()->status);
    }

    public function test_new_equipment_cannot_start_as_retired(): void
    {
        $this->actingAs($this->admin, 'admin')->postJson(route('admin.equipment.save'), [
            'name' => 'New Camera',
            'brand' => 'Canon',
            'serial_number' => 'CAM-1',
            'available_qty' => 1,
            'total_qty' => 1,
            'status' => Equipment::STATUS_RETIRED,
            'classification' => Equipment::CLASS_NOT_EXPENSIVE,
            'category_id' => $this->makeEquipment($this->department)->category_id,
            'department_id' => $this->department->department_id,
        ])->assertStatus(400);

        $this->assertDatabaseMissing('equipment', ['serial_number' => 'CAM-1']);
    }

    public function test_retired_equipment_is_hidden_from_borrowers_and_stock_counts(): void
    {
        $this->makeEquipment($this->department, ['name' => 'Working Laptop', 'total_qty' => 3, 'available_qty' => 3]);
        $retired = $this->makeEquipment($this->department, [
            'name' => 'Broken Old Laptop', 'total_qty' => 5, 'available_qty' => 0, 'status' => Equipment::STATUS_RETIRED,
        ]);

        $this->actingAs($this->student, 'user')->get(route('user.equipment'))
            ->assertOk()
            ->assertSee('Working Laptop')
            ->assertDontSee('Broken Old Laptop');

        $this->actingAs($this->student, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $retired->equipment_id,
            'borrow_date' => now()->addDay()->toDateString(),
            'return_date' => now()->addDays(2)->toDateString(),
            'purpose' => 'Class',
            'instructor_name' => 'Prof. Santos',
        ])->assertJsonPath('success', false);

        $dashboard = $this->actingAs($this->admin, 'admin')->getJson(route('admin.dashboard.data'))->json();
        $this->assertSame(3, $dashboard['stats']['totalEquipmentUnits']);
        $this->assertNotContains('Broken Old Laptop', array_column($dashboard['lowStock'], 'name'));
    }

    // ------------------------------------------------------------------
    // Accounts
    // ------------------------------------------------------------------

    public function test_users_with_borrowing_history_are_deactivated_not_deleted(): void
    {
        $equipment = $this->makeEquipment($this->department);
        $this->makePendingRequest($this->student, $equipment, ['overall_status' => 'Cancelled']);

        $this->actingAs($this->admin, 'admin')->post(route('admin.users.delete'), [
            'user_id' => $this->student->user_id,
            'user_type' => 'student',
        ])->assertRedirect(route('admin.users'))
            ->assertSessionHas('serverMsgType', 'error')
            ->assertSessionHas('serverMsg', fn (string $m) => str_contains($m, 'Deactivate'));

        $this->assertNotNull($this->student->fresh());
    }

    public function test_users_without_borrowing_history_can_be_deleted(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.users.delete'), [
            'user_id' => $this->student->user_id,
            'user_type' => 'student',
        ])->assertSessionHas('serverMsgType', 'success');

        $this->assertNull($this->student->fresh());
    }

    public function test_a_department_account_with_open_work_cannot_be_deleted(): void
    {
        $equipment = $this->makeEquipment($this->department);
        $request = $this->makePendingRequest($this->student, $equipment);

        $delete = fn () => $this->actingAs($this->admin, 'admin')->post(route('admin.users.delete'), [
            'user_id' => $this->lab->dept_acc_id + 100000,
            'user_type' => 'department',
        ]);

        $delete()->assertSessionHas('serverMsgType', 'error')
            ->assertSessionHas('serverMsg', fn (string $m) => str_contains($m, '1 pending request'));
        $this->assertNotNull($this->lab->fresh());

        // A unit on loan blocks it too.
        $request->update(['overall_status' => 'Rejected']);
        $loan = $this->makeLoan($this->student, $equipment, now()->addDay()->toDateString());

        $delete()->assertSessionHas('serverMsg', fn (string $m) => str_contains($m, 'on loan'));
        $this->assertNotNull($this->lab->fresh());

        // Once returned, the account can go; the department's history stays.
        $loan->update(['status' => BorrowTransaction::STATUS_RETURNED]);

        $delete()->assertSessionHas('serverMsgType', 'success');
        $this->assertNull($this->lab->fresh());
        $this->assertNotNull($loan->fresh());
    }

    // ------------------------------------------------------------------
    // Backups
    // ------------------------------------------------------------------

    public function test_backup_copies_the_database_and_uploads_and_removes_old_backups(): void
    {
        $root = storage_path('framework/testing/backups-' . uniqid());
        $database = storage_path('framework/testing/backup-source-' . uniqid() . '.sqlite');
        File::ensureDirectoryExists(dirname($database));
        File::put($database, 'sqlite contents');

        $old = $root . DIRECTORY_SEPARATOR . 'equiptrack-20200101-000000';
        File::ensureDirectoryExists($old);
        touch($old, now()->subDays(30)->getTimestamp());
        $unrelated = $root . DIRECTORY_SEPARATOR . 'my-notes';
        File::ensureDirectoryExists($unrelated);
        touch($unrelated, now()->subDays(30)->getTimestamp());

        config([
            'equiptrack.backup.path' => $root,
            'equiptrack.backup.keep_days' => 14,
            'database.connections.sqlite.database' => $database,
        ]);

        try {
            $this->artisan('equiptrack:backup')->assertSuccessful();

            $made = collect(File::directories($root))
                ->map(fn ($dir) => basename($dir))
                ->filter(fn ($name) => preg_match('/^equiptrack-\d{8}-\d{6}$/', $name))
                ->values();

            $this->assertCount(1, $made, 'Only the new backup is left; the 30-day-old one is removed.');
            $this->assertSame('sqlite contents', File::get($root . '/' . $made[0] . '/database.sqlite'));
            $this->assertDirectoryExists($root . '/' . $made[0] . '/files/public');
            $this->assertDirectoryExists($unrelated, 'Folders the command did not make are left alone.');
        } finally {
            File::deleteDirectory($root);
            File::delete($database);
        }
    }

    public function test_a_failed_backup_leaves_no_partial_folder(): void
    {
        $root = storage_path('framework/testing/backups-' . uniqid());
        config([
            'equiptrack.backup.path' => $root,
            'database.connections.sqlite.database' => ':memory:',
        ]);

        try {
            $this->artisan('equiptrack:backup')->assertFailed();
            $this->assertSame([], File::isDirectory($root) ? File::directories($root) : []);
        } finally {
            File::deleteDirectory($root);
        }
    }

    // ------------------------------------------------------------------

    private function assertDeleteRefused(callable $delete): void
    {
        try {
            $delete();
            $this->fail('The database allowed the delete.');
        } catch (QueryException $e) {
            $this->assertStringContainsStringIgnoringCase('foreign key', $e->getMessage());
        }
    }

    private function saveAsAdmin(Equipment $equipment, string $status)
    {
        return $this->actingAs($this->admin, 'admin')->postJson(route('admin.equipment.save'), $this->payload($equipment, $status) + [
            'department_id' => $equipment->department_id,
            'classification' => $equipment->classificationLabel(),
        ]);
    }

    private function saveAsDepartment(Equipment $equipment, string $status)
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.equipment.save'), $this->payload($equipment, $status));
    }

    private function payload(Equipment $equipment, string $status): array
    {
        return [
            'equipment_id' => $equipment->equipment_id,
            'name' => $equipment->name,
            'brand' => $equipment->brand,
            'serial_number' => $equipment->serial_number,
            'available_qty' => $equipment->available_qty,
            'total_qty' => $equipment->total_qty,
            'status' => $status,
            'category_id' => $equipment->category_id,
        ];
    }
}
