<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BorrowRequest;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Department Equipment page: it lists the shared `equipment` records whose
 * department_id matches the signed-in department account, so admin
 * assignments, reassignments, edits and borrow/return quantity updates are all
 * reflected without a separate department inventory.
 */
class DepartmentEquipmentTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private Department $otherDepartment;

    private DepartmentAccount $deptAccount;

    private DepartmentAccount $otherDeptAccount;

    private Admin $admin;

    private UserAccount $user;

    private EquipmentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'department_name' => 'IT Department',
            'department_code' => 'IT',
        ]);

        $this->otherDepartment = Department::create([
            'department_name' => 'Education Department',
            'department_code' => 'EDU',
        ]);

        $this->deptAccount = $this->makeDeptAccount($this->department, 'it-dept@example.com');
        $this->otherDeptAccount = $this->makeDeptAccount($this->otherDepartment, 'edu-dept@example.com');

        $this->category = EquipmentCategory::create(['category_name' => 'Laptop']);

        $this->admin = Admin::create([
            'name' => 'System Admin',
            'username' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $this->user = UserAccount::create([
            'role' => 'Student',
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
            'department_id' => $this->department->department_id,
            'status' => 'Active',
        ]);

        Student::create([
            'user_id' => $this->user->user_id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'id_number' => '2020-0001',
            'year_level' => '3',
            'address' => 'Cebu City',
        ]);
    }

    private function makeDeptAccount(Department $department, string $email): DepartmentAccount
    {
        return DepartmentAccount::create([
            'full_name' => $department->department_name . ' Head',
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'Department Head',
            'department_id' => $department->department_id,
            'status' => 'Active',
        ]);
    }

    private function makeEquipment(Department $department, array $overrides = []): Equipment
    {
        return Equipment::create(array_merge([
            'name' => 'Dell Latitude',
            'brand' => 'Dell',
            'model' => '5420',
            'serial_number' => 'EQ-' . uniqid(),
            'image' => '/storage/images/equipment-1.jpg',
            'available_qty' => 3,
            'total_qty' => 3,
            'status' => 'Available',
            'accessories_included' => null,
            'category_id' => $this->category->category_id,
            'department_id' => $department->department_id,
        ], $overrides));
    }

    /** Equipment list served to the given department account. */
    private function deptEquipment(DepartmentAccount $account): array
    {
        $response = $this->actingAs($account, 'dept')->getJson(route('department.equipment.data'));
        $response->assertOk()->assertJson(['success' => true]);

        return $response->json('equipment');
    }

    private function adminSave(array $payload)
    {
        return $this->actingAs($this->admin, 'admin')->postJson(route('admin.equipment.save'), $payload);
    }

    public function test_department_equipment_page_renders_for_the_signed_in_department(): void
    {
        $response = $this->actingAs($this->deptAccount, 'dept')->get(route('department.equipment'));

        $response->assertOk();
        $response->assertSee('Department Equipment');
        $response->assertSee('IT Department');
    }

    public function test_data_lists_only_the_signed_in_departments_equipment(): void
    {
        $mine = $this->makeEquipment($this->department, ['name' => 'IT Laptop']);
        $theirs = $this->makeEquipment($this->otherDepartment, ['name' => 'EDU Projector']);

        $names = collect($this->deptEquipment($this->deptAccount))->pluck('name')->all();

        $this->assertContains('IT Laptop', $names);
        $this->assertNotContains('EDU Projector', $names);
        $this->assertSame([(int) $mine->equipment_id], collect($this->deptEquipment($this->deptAccount))->pluck('id')->all());
        $this->assertNotContains((int) $theirs->equipment_id, collect($this->deptEquipment($this->deptAccount))->pluck('id')->all());
    }

    public function test_each_department_only_sees_its_own_assigned_equipment(): void
    {
        $this->makeEquipment($this->department, ['name' => 'IT Switch']);
        $this->makeEquipment($this->otherDepartment, ['name' => 'EDU Camera']);

        $itNames = collect($this->deptEquipment($this->deptAccount))->pluck('name')->all();
        $eduNames = collect($this->deptEquipment($this->otherDeptAccount))->pluck('name')->all();

        $this->assertSame(['IT Switch'], $itNames);
        $this->assertSame(['EDU Camera'], $eduNames);
    }

    public function test_admin_reassignment_moves_equipment_to_the_new_department_list(): void
    {
        $equipment = $this->makeEquipment($this->department, ['name' => 'Shared Tablet']);

        $this->assertContains((int) $equipment->equipment_id, collect($this->deptEquipment($this->deptAccount))->pluck('id')->all());

        // Admin moves the equipment to the Education department.
        $this->adminSave([
            'equipment_id' => $equipment->equipment_id,
            'name' => 'Shared Tablet',
            'brand' => 'Samsung',
            'serial_number' => $equipment->serial_number,
            'image' => $equipment->image,
            'available_qty' => 3,
            'total_qty' => 3,
            'status' => 'Available',
            'category_id' => $this->category->category_id,
            'department_id' => $this->otherDepartment->department_id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertNotContains((int) $equipment->equipment_id, collect($this->deptEquipment($this->deptAccount))->pluck('id')->all());
        $this->assertContains((int) $equipment->equipment_id, collect($this->deptEquipment($this->otherDeptAccount))->pluck('id')->all());
    }

    public function test_admin_edits_are_reflected_in_the_department_page(): void
    {
        $equipment = $this->makeEquipment($this->department);

        $this->adminSave([
            'equipment_id' => $equipment->equipment_id,
            'name' => 'Dell Latitude 5430',
            'brand' => 'Dell',
            'serial_number' => $equipment->serial_number,
            'image' => '/storage/images/equipment-updated.jpg',
            'available_qty' => 2,
            'total_qty' => 5,
            'status' => 'On Hold',
            'category_id' => $this->category->category_id,
            'department_id' => $this->department->department_id,
        ])->assertOk();

        $row = collect($this->deptEquipment($this->deptAccount))->firstWhere('id', $equipment->equipment_id);

        $this->assertSame('Dell Latitude 5430', $row['name']);
        $this->assertSame('Laptop', $row['category']);
        $this->assertSame(2, $row['available']);
        $this->assertSame(5, $row['total']);
        $this->assertSame('On Hold', $row['status']);
        $this->assertSame('/storage/images/equipment-updated.jpg', $row['imgUrl']);
    }

    public function test_admin_added_equipment_appears_for_the_assigned_department_only(): void
    {
        $this->adminSave([
            'name' => 'Network Switch',
            'brand' => 'Cisco',
            'serial_number' => 'SW-0001',
            'image' => '/storage/images/switch.jpg',
            'available_qty' => 1,
            'total_qty' => 1,
            'status' => 'Available',
            'category_id' => $this->category->category_id,
            'department_id' => $this->department->department_id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertSame(['Network Switch'], collect($this->deptEquipment($this->deptAccount))->pluck('name')->all());
        $this->assertSame([], $this->deptEquipment($this->otherDeptAccount));
    }

    public function test_department_save_registers_equipment_in_its_own_department(): void
    {
        // A department_id in the payload must be ignored: the item stays in the
        // signed-in department.
        $this->actingAs($this->deptAccount, 'dept')->postJson(route('department.equipment.save'), [
            'name' => 'Dept Registered Laptop',
            'brand' => 'Lenovo',
            'serial_number' => 'DEPT-0001',
            'image' => '/storage/images/dept.jpg',
            'available_qty' => 2,
            'total_qty' => 2,
            'status' => 'Available',
            'category_id' => $this->category->category_id,
            'department_id' => $this->otherDepartment->department_id,
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('equipment', [
            'name' => 'Dept Registered Laptop',
            'serial_number' => 'DEPT-0001',
            'department_id' => $this->department->department_id,
        ]);

        $this->assertSame(['Dept Registered Laptop'], collect($this->deptEquipment($this->deptAccount))->pluck('name')->all());
        $this->assertSame([], $this->deptEquipment($this->otherDeptAccount));
    }

    public function test_department_edits_update_the_same_record_the_admin_sees(): void
    {
        $equipment = $this->makeEquipment($this->department, ['name' => 'Old Name']);

        $this->actingAs($this->deptAccount, 'dept')->postJson(route('department.equipment.save'), [
            'equipment_id' => $equipment->equipment_id,
            'name' => 'New Name',
            'brand' => 'Dell',
            'serial_number' => $equipment->serial_number,
            'image' => $equipment->image,
            'available_qty' => 1,
            'total_qty' => 4,
            'status' => 'Available',
            'category_id' => $this->category->category_id,
        ])->assertOk()->assertJson(['success' => true]);

        $fresh = $equipment->fresh();
        $this->assertSame('New Name', $fresh->name);
        $this->assertSame(1, (int) $fresh->available_qty);
        $this->assertSame(4, (int) $fresh->total_qty);
        // The record did not move departments.
        $this->assertSame($this->department->department_id, (int) $fresh->department_id);

        $adminRow = collect(
            $this->actingAs($this->admin, 'admin')->getJson(route('admin.equipment.data'))->json('equipment')
        )->firstWhere('equipment_id', $equipment->equipment_id);

        $this->assertSame('New Name', $adminRow['name']);
        $this->assertSame(1, $adminRow['available_qty']);
        $this->assertSame(4, $adminRow['total_qty']);
    }

    public function test_department_cannot_edit_another_departments_equipment(): void
    {
        $theirs = $this->makeEquipment($this->otherDepartment, ['name' => 'EDU Only']);

        $this->actingAs($this->deptAccount, 'dept')->postJson(route('department.equipment.save'), [
            'equipment_id' => $theirs->equipment_id,
            'name' => 'Hijacked',
            'brand' => 'X',
            'serial_number' => $theirs->serial_number,
            'image' => $theirs->image,
            'available_qty' => 0,
            'total_qty' => 0,
            'status' => 'Unavailable',
            'category_id' => $this->category->category_id,
        ])->assertStatus(404)->assertJson(['success' => false]);

        $this->assertSame('EDU Only', $theirs->fresh()->name);
        $this->assertSame($this->otherDepartment->department_id, (int) $theirs->fresh()->department_id);
    }

    public function test_department_save_validates_quantities_and_duplicate_serials(): void
    {
        $existing = $this->makeEquipment($this->department, ['serial_number' => 'SN-TAKEN']);

        $base = [
            'name' => 'Validated Laptop',
            'brand' => 'Dell',
            'serial_number' => 'SN-NEW',
            'image' => '/storage/images/x.jpg',
            'status' => 'Available',
            'category_id' => $this->category->category_id,
        ];

        // Available greater than total is refused.
        $this->actingAs($this->deptAccount, 'dept')
            ->postJson(route('department.equipment.save'), $base + ['available_qty' => 5, 'total_qty' => 2])
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        // Negative available is refused by validation.
        $this->actingAs($this->deptAccount, 'dept')
            ->postJson(route('department.equipment.save'), $base + ['available_qty' => -1, 'total_qty' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('available_qty');

        // Serial numbers stay unique across departments.
        $this->actingAs($this->deptAccount, 'dept')
            ->postJson(route('department.equipment.save'), array_merge($base, [
                'serial_number' => $existing->serial_number,
                'available_qty' => 1,
                'total_qty' => 2,
            ]))
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_borrow_and_return_quantity_changes_are_visible_in_the_department_page(): void
    {
        $equipment = $this->makeEquipment($this->department, ['available_qty' => 1, 'total_qty' => 1]);

        $request = $this->actingAs($this->user, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $equipment->equipment_id,
            'borrow_date' => now()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'purpose' => 'Research',
            'notes' => '',
        ])->assertOk();

        $requestId = (int) $request->json('request_id');

        // The department approves, which deducts the available quantity.
        $this->actingAs($this->deptAccount, 'dept')->postJson(route('department.requests.update'), [
            'request_id' => $requestId,
            'status' => 'Approved',
        ])->assertOk();

        $row = collect($this->deptEquipment($this->deptAccount))->firstWhere('id', $equipment->equipment_id);
        $this->assertSame(0, $row['available']);
        $this->assertSame(1, $row['total']);
        $this->assertSame('Unavailable', $row['status']);

        // Returning the item restores it, leaving Total untouched.
        $this->actingAs($this->user, 'user')->postJson(route('user.returns.store'), [
            'request_id' => $requestId,
            'condition' => 'Good',
            'remarks' => '',
        ])->assertOk();

        $row = collect($this->deptEquipment($this->deptAccount))->firstWhere('id', $equipment->equipment_id);
        $this->assertSame(1, $row['available']);
        $this->assertSame('Available', $row['status']);
        $this->assertSame(1, (int) $equipment->fresh()->total_qty);
    }
}

