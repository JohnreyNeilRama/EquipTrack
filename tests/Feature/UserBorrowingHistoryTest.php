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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Covers the user Borrowing History page: it must reflect the real borrow
 * request / transaction records and update itself as the user borrows and
 * returns equipment.
 */
class UserBorrowingHistoryTest extends TestCase
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

    private function submitBorrowRequest(string $purpose = 'Research', ?string $notes = 'Handle with care'): int
    {
        $response = $this->actingAs($this->user, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $this->equipment->equipment_id,
            'borrow_date' => now()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'purpose' => $purpose,
            'notes' => $notes,
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        return (int) $response->json('request_id');
    }

    private function approve(int $requestId): void
    {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.requests.update'), ['request_id' => $requestId, 'status' => 'Approved'])
            ->assertOk();
    }

    private function reject(int $requestId, string $reason = 'Not needed for this term'): void
    {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.requests.update'), [
                'request_id' => $requestId,
                'status' => 'Rejected',
                'reject_reason' => $reason,
            ])
            ->assertOk();
    }

    private function historyRows(): Collection
    {
        return collect(
            $this->actingAs($this->user, 'user')->get(route('user.history'))->viewData('historyItems')
        );
    }

    private function historyRow(string $equipment = 'Dell Latitude'): array
    {
        $row = $this->historyRows()->firstWhere('equipment', $equipment);
        $this->assertNotNull($row, "No history row found for {$equipment}.");

        return $row;
    }

    public function test_history_page_is_empty_for_a_user_without_transactions(): void
    {
        $this->actingAs($this->user, 'user')
            ->get(route('user.history'))
            ->assertOk()
            ->assertSee('No transaction history')
            ->assertSee('id="historyEmptyState" class="empty-state-container" style="display: flex;"', false)
            ->assertDontSee('class="history-row"', false);

        $this->assertCount(0, $this->historyRows());
    }

    public function test_pending_request_is_listed_as_pending_without_a_return_date(): void
    {
        $this->submitBorrowRequest();

        $row = $this->historyRow();

        $this->assertSame('Pending', $row['status']);
        $this->assertSame('—', $row['return_date']);
        $this->assertSame('status-pending', $row['status_class']);
        $this->assertSame('pending', $row['badge_class']);
        $this->assertSame('Handle with care', $row['remarks']);
        $this->assertSame('—', $row['condition']);
    }

    public function test_approved_request_is_listed_as_borrowed_with_the_borrow_date(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);

        $row = $this->historyRow();

        $this->assertSame('Borrowed', $row['status']);
        $this->assertSame('status-borrowed', $row['status_class']);
        $this->assertSame('borrowed', $row['badge_class']);
        $this->assertSame(now()->toDateString(), Carbon::parse($row['borrow_date'])->toDateString());
        $this->assertSame('—', $row['return_date']);
        $this->assertSame('System Admin', $row['handled_by']);
    }

    public function test_history_updates_automatically_after_the_item_is_returned(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);

        $this->assertSame('Borrowed', $this->historyRow()['status']);

        $this->actingAs($this->user, 'user')
            ->postJson(route('user.returns.store'), [
                'request_id' => $requestId,
                'condition' => 'Good',
                'remarks' => 'No visible damage',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);

        $row = $this->historyRow();

        $this->assertSame('Returned', $row['status']);
        $this->assertSame('status-returned', $row['status_class']);
        $this->assertSame('approved', $row['badge_class']);
        $this->assertSame(now()->toDateString(), Carbon::parse($row['return_date'])->toDateString());
        $this->assertSame('Good — No visible damage', $row['condition']);
        $this->assertSame('No visible damage', $row['remarks']);
    }

    public function test_late_return_is_flagged_and_uses_the_red_status_class(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);

        BorrowTransaction::where('request_id', $requestId)->update([
            'borrow_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(4)->toDateString(),
        ]);

        $this->actingAs($this->user, 'user')
            ->postJson(route('user.returns.store'), [
                'request_id' => $requestId,
                'condition' => 'Damaged',
                'remarks' => 'Screen flickers',
            ])
            ->assertOk();

        $row = $this->historyRow();

        $this->assertSame('Late Return', $row['status']);
        $this->assertSame('status-late', $row['status_class']);
        $this->assertSame('rejected', $row['badge_class']);
        $this->assertSame('Damaged — Screen flickers', $row['condition']);
        $this->assertSame('Screen flickers', $row['remarks']);
    }

    public function test_overdue_item_still_out_is_flagged_as_overdue(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);

        BorrowTransaction::where('request_id', $requestId)->update([
            'borrow_date' => now()->subDays(9)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
        ]);

        $row = $this->historyRow();

        $this->assertSame('Overdue', $row['status']);
        $this->assertSame('status-late', $row['status_class']);
        $this->assertSame('rejected', $row['badge_class']);
        $this->assertSame('—', $row['return_date']);
    }

    public function test_rejected_request_is_listed_with_its_reason(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->reject($requestId, 'Equipment reserved for lab use');

        $row = $this->historyRow();

        $this->assertSame('Rejected', $row['status']);
        $this->assertSame('status-rejected', $row['status_class']);
        $this->assertSame('rejected', $row['badge_class']);
        $this->assertSame('Equipment reserved for lab use', $row['remarks']);
        $this->assertSame('System Admin', $row['handled_by']);
    }

    public function test_history_page_renders_rows_with_status_and_modal_data(): void
    {
        $requestId = $this->submitBorrowRequest();
        $this->approve($requestId);

        $this->actingAs($this->user, 'user')
            ->get(route('user.history'))
            ->assertOk()
            ->assertSee('Dell Latitude')
            ->assertSee('data-status="Borrowed"', false)
            ->assertSee('status-text status-borrowed', false)
            ->assertSee('data-badge="borrowed"', false)
            ->assertSee('data-handled-by="System Admin"', false)
            ->assertSee('data-timestamp=', false)
            ->assertSee('id="historyEmptyState" class="empty-state-container" style="display: none;"', false);
    }

    public function test_history_only_contains_the_signed_in_users_transactions(): void
    {
        $this->submitBorrowRequest();

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

        $otherEquipment = Equipment::create([
            'name' => 'Canon Camera',
            'brand' => 'Canon',
            'serial_number' => 'EQ-0002',
            'image' => '',
            'available_qty' => 1,
            'total_qty' => 1,
            'status' => 'Available',
            'category_id' => $this->equipment->category_id,
            'department_id' => $this->equipment->department_id,
        ]);

        BorrowRequest::create([
            'user_id' => $otherUser->user_id,
            'equipment_id' => $otherEquipment->equipment_id,
            'quantity' => 1,
            'purpose' => 'Someone else request',
            'date_needed' => now()->toDateString(),
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'admin_status' => 'Approved',
            'dept_status' => 'Approved',
            'overall_status' => 'Approved',
        ]);

        $this->assertSame(['Dell Latitude'], $this->historyRows()->pluck('equipment')->all());
    }

}
