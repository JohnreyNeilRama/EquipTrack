<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
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
 * Equipment quantity sync: approving a borrow request (from either the admin
 * or the department workflow) decreases the equipment's Available quantity,
 * returning it increases it again, Total never changes, and Available stays
 * within [0, total_qty].
 */
class EquipmentQuantitySyncTest extends TestCase
{
    use RefreshDatabase;

    private UserAccount $user;

    private Admin $admin;

    private DepartmentAccount $deptAccount;

    private Department $department;

    private EquipmentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'department_name' => 'College of Computer Studies',
            'department_code' => 'CCS',
        ]);

        $this->category = EquipmentCategory::create(['category_name' => 'Laptop']);

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

        $this->admin = Admin::create([
            'name' => 'System Admin',
            'username' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $this->deptAccount = DepartmentAccount::create([
            'full_name' => 'Dept Head',
            'email' => 'dept@example.com',
            'password' => Hash::make('password'),
            'role' => 'Department Head',
            'department_id' => $this->department->department_id,
            'status' => 'Active',
        ]);
    }

    private function makeEquipment(int $available, int $total): Equipment
    {
        return Equipment::create([
            'name' => 'Dell Latitude',
            'brand' => 'Dell',
            'serial_number' => 'EQ-' . uniqid(),
            'image' => '',
            'available_qty' => $available,
            'total_qty' => $total,
            'status' => $available > 0 ? 'Available' : 'Unavailable',
            'category_id' => $this->category->category_id,
            'department_id' => $this->department->department_id,
        ]);
    }

    private function submitBorrowRequest(Equipment $equipment): int
    {
        $response = $this->actingAs($this->user, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $equipment->equipment_id,
            'borrow_date' => now()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'purpose' => 'Research',
            'notes' => 'Handle with care',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        return (int) $response->json('request_id');
    }

    private function adminApprove(int $requestId, string $status = 'Approved', ?string $reason = null)
    {
        $payload = ['request_id' => $requestId, 'status' => $status];
        if ($reason !== null) {
            $payload['reject_reason'] = $reason;
        }

        return $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.requests.update'), $payload);
    }

    private function deptReview(int $requestId, string $status = 'Approved', ?string $reason = null)
    {
        $payload = ['request_id' => $requestId, 'status' => $status];
        if ($reason !== null) {
            $payload['reject_reason'] = $reason;
        }

        return $this->actingAs($this->deptAccount, 'dept')
            ->postJson(route('department.requests.update'), $payload);
    }

    private function returnItem(int $requestId)
    {
        return $this->actingAs($this->user, 'user')->postJson(route('user.returns.store'), [
            'request_id' => $requestId,
            'condition' => 'Good',
            'remarks' => 'Returned in good shape',
        ]);
    }

    public function test_admin_approval_decreases_available_and_leaves_total_unchanged(): void
    {
        $equipment = $this->makeEquipment(3, 3);
        $requestId = $this->submitBorrowRequest($equipment);

        $this->adminApprove($requestId)->assertOk()->assertJson(['success' => true]);

        $fresh = $equipment->fresh();
        $this->assertSame(2, (int) $fresh->available_qty, 'Available quantity must decrease by the borrowed quantity.');
        $this->assertSame(3, (int) $fresh->total_qty, 'Total quantity must never change.');
    }

    public function test_equipment_management_page_data_reflects_the_new_quantities(): void
    {
        $equipment = $this->makeEquipment(2, 2);
        $requestId = $this->submitBorrowRequest($equipment);
        $this->adminApprove($requestId)->assertOk();

        $response = $this->actingAs($this->admin, 'admin')->getJson(route('admin.equipment.data'));
        $response->assertOk()->assertJson(['success' => true]);

        $row = collect($response->json('equipment'))->firstWhere('equipment_id', $equipment->equipment_id);

        $this->assertNotNull($row);
        $this->assertSame(1, $row['available_qty']);
        $this->assertSame(2, $row['total_qty']);
    }

    public function test_department_approval_decreases_available_quantity(): void
    {
        $equipment = $this->makeEquipment(3, 3);
        $requestId = $this->submitBorrowRequest($equipment);

        $this->deptReview($requestId, 'Approved')->assertOk()->assertJson(['success' => true]);

        $this->assertSame(2, (int) $equipment->fresh()->available_qty);

        $this->assertDatabaseHas('borrow_request', [
            'request_id' => $requestId,
            'dept_status' => 'Approved',
            'overall_status' => 'Approved',
            'dept_acc_id' => $this->deptAccount->dept_acc_id,
        ]);

        $transaction = BorrowTransaction::where('request_id', $requestId)->first();
        $this->assertNotNull($transaction);
        $this->assertSame('Active', $transaction->status);
    }

    public function test_admin_then_department_approval_deducts_stock_only_once(): void
    {
        $equipment = $this->makeEquipment(3, 3);
        $requestId = $this->submitBorrowRequest($equipment);

        $this->adminApprove($requestId)->assertOk();
        $this->deptReview($requestId, 'Approved')->assertOk();

        $this->assertSame(2, (int) $equipment->fresh()->available_qty, 'The second approval must not deduct stock again.');
        $this->assertSame(1, BorrowTransaction::where('request_id', $requestId)->count());
    }

    public function test_department_then_admin_approval_deducts_stock_only_once(): void
    {
        $equipment = $this->makeEquipment(3, 3);
        $requestId = $this->submitBorrowRequest($equipment);

        $this->deptReview($requestId, 'Approved')->assertOk();
        $this->assertSame(2, (int) $equipment->fresh()->available_qty);

        $this->adminApprove($requestId)->assertOk();

        $this->assertSame(2, (int) $equipment->fresh()->available_qty, 'The admin approval must not deduct stock again.');
        $this->assertSame(1, BorrowTransaction::where('request_id', $requestId)->count());
    }

    public function test_approval_is_blocked_when_there_is_no_stock_left(): void
    {
        $equipment = $this->makeEquipment(1, 1);

        $firstRequest = $this->submitBorrowRequest($equipment);
        $secondRequest = $this->submitBorrowRequest($equipment);

        $this->adminApprove($firstRequest)->assertOk();

        $this->assertSame(0, (int) $equipment->fresh()->available_qty);
        $this->assertSame('Unavailable', $equipment->fresh()->status);

        $this->adminApprove($secondRequest)
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        $this->deptReview($secondRequest, 'Approved')
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        $this->assertSame(0, (int) $equipment->fresh()->available_qty, 'Available quantity must never become negative.');
    }

    public function test_available_quantity_returns_to_normal_after_the_item_is_returned(): void
    {
        $equipment = $this->makeEquipment(1, 1);
        $requestId = $this->submitBorrowRequest($equipment);

        $this->adminApprove($requestId)->assertOk();
        $this->assertSame(0, (int) $equipment->fresh()->available_qty);
        $this->assertSame('Unavailable', $equipment->fresh()->status);

        $this->returnItem($requestId)->assertOk()->assertJson(['success' => true]);

        $fresh = $equipment->fresh();
        $this->assertSame(1, (int) $fresh->available_qty);
        $this->assertSame(1, (int) $fresh->total_qty);
        $this->assertSame('Available', $fresh->status);
    }

    public function test_return_never_pushes_available_quantity_above_total(): void
    {
        $equipment = $this->makeEquipment(3, 3);
        $requestId = $this->submitBorrowRequest($equipment);

        $this->adminApprove($requestId)->assertOk();
        $this->assertSame(2, (int) $equipment->fresh()->available_qty);

        // Simulate the admin correcting stock while the item is out on loan.
        $equipment->update(['available_qty' => 3]);

        $this->returnItem($requestId)->assertOk();

        $fresh = $equipment->fresh();
        $this->assertSame(3, (int) $fresh->available_qty, 'Available quantity cannot exceed total quantity.');
        $this->assertSame(3, (int) $fresh->total_qty);
    }

    public function test_admin_cannot_reject_after_department_approval_activates_the_loan(): void
    {
        $equipment = $this->makeEquipment(2, 2);
        $requestId = $this->submitBorrowRequest($equipment);

        $this->deptReview($requestId, 'Approved')->assertOk();
        $this->adminApprove($requestId, 'Rejected', 'Too late')->assertStatus(400);

        $fresh = $equipment->fresh();
        $this->assertSame(1, (int) $fresh->available_qty);
        $this->assertSame(2, (int) $fresh->total_qty);
        $this->assertDatabaseHas('borrow_transaction', [
            'request_id' => $requestId,
            'status' => 'Active',
        ]);
        $this->assertDatabaseHas('borrow_request', [
            'request_id' => $requestId,
            'admin_status' => 'Pending',
            'overall_status' => 'Approved',
        ]);
    }

    public function test_department_rejection_leaves_the_stock_untouched(): void
    {
        $equipment = $this->makeEquipment(2, 2);
        $requestId = $this->submitBorrowRequest($equipment);

        $this->deptReview($requestId, 'Rejected', 'Equipment needed for lab class')->assertOk();

        $this->assertSame(2, (int) $equipment->fresh()->available_qty);
        $this->assertNull(BorrowTransaction::where('request_id', $requestId)->first());
        $this->assertDatabaseHas('borrow_request', [
            'request_id' => $requestId,
            'dept_status' => 'Rejected',
            'overall_status' => 'Rejected',
        ]);
    }

    public function test_department_cannot_review_another_departments_equipment(): void
    {
        $otherDepartment = Department::create([
            'department_name' => 'College of Engineering',
            'department_code' => 'COE',
        ]);

        $equipment = Equipment::create([
            'name' => 'Canon Camera',
            'brand' => 'Canon',
            'serial_number' => 'EQ-COE-1',
            'image' => '',
            'available_qty' => 1,
            'total_qty' => 1,
            'status' => 'Available',
            'category_id' => $this->category->category_id,
            'department_id' => $otherDepartment->department_id,
        ]);

        $requestId = $this->submitBorrowRequest($equipment);

        $this->deptReview($requestId, 'Approved')->assertStatus(404);
        $this->assertSame(1, (int) $equipment->fresh()->available_qty);
    }

    public function test_equipment_management_rejects_invalid_available_quantities(): void
    {
        $equipment = $this->makeEquipment(2, 2);

        $payload = [
            'equipment_id' => $equipment->equipment_id,
            'name' => $equipment->name,
            'brand' => $equipment->brand,
            'serial_number' => $equipment->serial_number,
            'image' => $equipment->image,
            'status' => 'Available',
            'category_id' => $this->category->category_id,
            'department_id' => $this->department->department_id,
        ];

        // Available greater than total is refused.
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.equipment.save'), $payload + ['available_qty' => 5, 'total_qty' => 2])
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        // Negative available is refused by validation.
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.equipment.save'), $payload + ['available_qty' => -1, 'total_qty' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors('available_qty');

        $this->assertSame(2, (int) $equipment->fresh()->available_qty);
        $this->assertSame(2, (int) $equipment->fresh()->total_qty);
    }
}
