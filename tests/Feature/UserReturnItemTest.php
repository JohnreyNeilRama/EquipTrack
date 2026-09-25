<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * End-to-end coverage for the user Return Item flow:
 * borrow request -> admin approval (stock -1, transaction created) ->
 * user return (transaction Returned, stock restored, audit written) ->
 * reflected on the returns page and on admin monitoring.
 */
class UserReturnItemTest extends TestCase
{
    use RefreshDatabase;

    private UserAccount $user;

    private Admin $admin;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $department = Department::create([
            'department_name' => 'College of Computer Studies',
            'department_code' => 'CCS',
        ]);

        $category = EquipmentCategory::create(['category_name' => 'Laptop']);

        $this->equipment = Equipment::create([
            'name' => 'Dell Latitude',
            'brand' => 'Dell',
            'serial_number' => 'EQ-0001',
            'image' => '',
            'available_qty' => 2,
            'total_qty' => 2,
            'status' => 'Available',
            'category_id' => $category->category_id,
            'department_id' => $department->department_id,
        ]);

        $this->user = UserAccount::create([
            'role' => 'Student',
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
            'department_id' => $department->department_id,
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
    }

    private function submitBorrowRequest(): int
    {
        $response = $this->actingAs($this->user, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $this->equipment->equipment_id,
            'borrow_date' => now()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'purpose' => 'Research',
            'notes' => 'Handle with care',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        return (int) $response->json('request_id');
    }

    private function approve(int $requestId): void
    {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.requests.update'), [
                'request_id' => $requestId,
                'status' => 'Approved',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    private function returnItem(int $requestId, string $condition = 'Good', string $remarks = 'No visible damage')
    {
        return $this->actingAs($this->user, 'user')->postJson(route('user.returns.store'), [
            'request_id' => $requestId,
            'condition' => $condition,
            'remarks' => $remarks,
        ]);
    }

    public function test_borrow_request_starts_as_pending(): void
    {
        $requestId = $this->submitBorrowRequest();

        $this->assertDatabaseHas('borrow_request', [
            'request_id' => $requestId,
            'user_id' => $this->user->user_id,
            'overall_status' => 'Pending',
        ]);
    }

    public function test_approval_decrements_stock_and_creates_active_transaction(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);

        $this->assertSame(1, (int) $this->equipment->fresh()->available_qty);

        $transaction = BorrowTransaction::where('request_id', $requestId)->first();
        $this->assertNotNull($transaction);
        $this->assertSame('Active', $transaction->status);
    }

    public function test_returns_page_lists_active_borrows_as_borrowed(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);

        $this->actingAs($this->user, 'user')
            ->get(route('user.returns'))
            ->assertOk()
            ->assertSee('Dell Latitude')
            ->assertSee('data-status="Borrowed"', false);
    }

    public function test_returns_page_marks_past_due_items_as_overdue(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);

        BorrowTransaction::where('request_id', $requestId)->update([
            'borrow_date' => now()->subDays(5)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAs($this->user, 'user')
            ->get(route('user.returns'))
            ->assertOk()
            ->assertSee('data-status="Overdue"', false);
    }

    public function test_user_can_return_item_and_stock_is_restored(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);

        $this->returnItem($requestId)
            ->assertOk()
            ->assertJson(['success' => true]);

        $transaction = BorrowTransaction::where('request_id', $requestId)->first();
        $this->assertSame('Returned', $transaction->status);
        $this->assertSame(now()->toDateString(), $transaction->return_date->toDateString());
        $this->assertStringContainsString('Good', (string) $transaction->condition_on_return);
        $this->assertStringContainsString('No visible damage', (string) $transaction->condition_on_return);

        $equipment = $this->equipment->fresh();
        $this->assertSame(2, (int) $equipment->available_qty);
        $this->assertSame('Available', $equipment->status);

        $this->assertDatabaseHas('audit_trail', [
            'user_id' => $this->user->user_id,
            'affected_entity' => 'borrow_request:' . $requestId,
        ]);
    }

    public function test_returned_item_is_removed_from_returns_page(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);
        $this->returnItem($requestId)->assertOk();

        $this->actingAs($this->user, 'user')
            ->get(route('user.returns'))
            ->assertOk()
            ->assertDontSee('Dell Latitude');
    }

    public function test_item_cannot_be_returned_twice(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);

        $this->returnItem($requestId)->assertOk();

        $this->returnItem($requestId)
            ->assertStatus(400)
            ->assertJson(['success' => false]);

        // Stock must not be inflated by the rejected duplicate return.
        $this->assertSame(2, (int) $this->equipment->fresh()->available_qty);
    }

    public function test_return_requires_a_valid_condition(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);

        $this->returnItem($requestId, 'Broken')
            ->assertStatus(422)
            ->assertJsonValidationErrors('condition');
    }

    public function test_user_cannot_return_another_users_item(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);

        $otherUser = UserAccount::create([
            'role' => 'Student',
            'email' => 'other@example.com',
            'password' => Hash::make('password'),
            'department_id' => $this->user->department_id,
            'status' => 'Active',
        ]);

        Student::create([
            'user_id' => $otherUser->user_id,
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'id_number' => '2020-0002',
            'year_level' => '4',
            'address' => 'Cebu City',
        ]);

        $this->actingAs($otherUser, 'user')
            ->postJson(route('user.returns.store'), [
                'request_id' => $requestId,
                'condition' => 'Good',
            ])
            ->assertStatus(403)
            ->assertJson(['success' => false]);

        $this->assertSame('Active', BorrowTransaction::where('request_id', $requestId)->first()->status);
    }

    public function test_pending_request_cannot_be_returned(): void
    {
        $requestId = $this->submitBorrowRequest();

        $this->returnItem($requestId)
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_legacy_approved_request_return_does_not_inflate_stock(): void
    {
        // Approved before stock tracking existed: no transaction, stock untouched.
        $borrow = BorrowRequest::create([
            'user_id' => $this->user->user_id,
            'equipment_id' => $this->equipment->equipment_id,
            'quantity' => 1,
            'purpose' => 'Legacy request',
            'date_needed' => now()->toDateString(),
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'admin_status' => 'Approved',
            'dept_status' => 'Approved',
            'overall_status' => 'Approved',
        ]);

        $this->returnItem($borrow->request_id)->assertOk();

        $this->assertSame(
            'Returned',
            BorrowTransaction::where('request_id', $borrow->request_id)->first()->status
        );
        $this->assertSame(2, (int) $this->equipment->fresh()->available_qty);
    }

    public function test_admin_monitoring_reflects_borrowed_and_returned_equipment(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.monitoring'));
        $response->assertOk();

        $borrowed = collect($response->viewData('dbRequests'))->firstWhere('equipment', 'Dell Latitude');
        $this->assertNotNull($borrowed);
        $this->assertSame('Borrowed', $borrowed['status']);
        $this->assertSame('Juan Dela Cruz', $borrowed['user']);
        $this->assertSame('2020-0001', $borrowed['id_number']);

        $this->returnItem($requestId)->assertOk();

        $afterReturn = collect(
            $this->actingAs($this->admin, 'admin')
                ->get(route('admin.monitoring'))
                ->viewData('dbRequests')
        )->firstWhere('equipment', 'Dell Latitude');

        $this->assertSame('Available', $afterReturn['status']);
        $this->assertSame('—', $afterReturn['user']);
    }

}
