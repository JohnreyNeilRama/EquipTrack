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
 * Admin dashboard: every Quick Stat, table and summary figure is served from
 * the live database (no hardcoded or sample data), so requests, approvals,
 * borrows, returns and new records are reflected immediately.
 */
class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private Admin $admin;

    private UserAccount $user;

    private EquipmentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'department_name' => 'College of Computer Studies',
            'department_code' => 'CCS',
        ]);

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

    /** Dashboard payload served to the admin (the source of every figure). */
    private function payload(): array
    {
        $response = $this->actingAs($this->admin, 'admin')->getJson(route('admin.dashboard.data'));

        $response->assertOk()->assertJson(['success' => true]);

        return $response->json();
    }

    private function makeEquipment(array $overrides = []): Equipment
    {
        return Equipment::create(array_merge([
            'name' => 'Dell Latitude',
            'brand' => 'Dell',
            'serial_number' => 'EQ-' . uniqid(),
            'image' => '/storage/images/eq.jpg',
            'available_qty' => 3,
            'total_qty' => 3,
            'status' => 'Available',
            'category_id' => $this->category->category_id,
            'department_id' => $this->department->department_id,
        ], $overrides));
    }

    private function makeRequest(Equipment $equipment, array $overrides = []): BorrowRequest
    {
        $request = BorrowRequest::create(array_merge([
            'user_id' => $this->user->user_id,
            'equipment_id' => $equipment->equipment_id,
            'quantity' => 1,
            'purpose' => 'Research',
            'notes' => '',
            'date_needed' => now()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'borrow_date' => now()->toDateString(),
            'overall_status' => 'Pending',
            'admin_status' => 'Pending',
            'dept_status' => 'Pending',
        ], $overrides));

        // date_requested is set by the database default, not mass assignable.
        $request->forceFill(['date_requested' => $overrides['date_requested'] ?? now()])->save();

        return $request->refresh();
    }

    private function submitBorrowRequest(Equipment $equipment): int
    {
        $response = $this->actingAs($this->user, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $equipment->equipment_id,
            'borrow_date' => now()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'purpose' => 'Research',
            'notes' => '',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        return (int) $response->json('request_id');
    }

    public function test_dashboard_page_renders_with_live_stats(): void
    {
        $this->makeEquipment(['available_qty' => 3, 'total_qty' => 3]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Quick Stats');
        $response->assertSee('statTotalEq');
        $response->assertSee('lowStockCard');
    }

    public function test_dashboard_reports_zeros_and_empty_tables_when_there_are_no_records(): void
    {
        // setUp creates the single student account; everything else is empty.
        $payload = $this->payload();

        $this->assertSame(1, $payload['stats']['totalUsers']);
        $this->assertSame(0, $payload['stats']['totalEquipment']);
        $this->assertSame(0, $payload['stats']['pendingRequests']);
        $this->assertSame(0, $payload['stats']['borrowedEquipment']);
        $this->assertSame(0, $payload['stats']['overdueItems']);
        $this->assertSame([], $payload['recentRequests']);
        $this->assertSame([], $payload['overdue']);
        $this->assertSame([], $payload['lowStock']);
        $this->assertSame(0, $payload['summary']['total']);
    }

    public function test_new_borrow_request_increases_the_pending_count(): void
    {
        $equipment = $this->makeEquipment();

        $this->assertSame(0, $this->payload()['stats']['pendingRequests']);

        $this->submitBorrowRequest($equipment);

        $payload = $this->payload();
        $this->assertSame(1, $payload['stats']['pendingRequests']);
        $this->assertSame(0, $payload['stats']['borrowedEquipment']);
    }

    public function test_approval_moves_a_request_from_pending_to_borrowed(): void
    {
        $equipment = $this->makeEquipment(['available_qty' => 2, 'total_qty' => 2]);
        $requestId = $this->submitBorrowRequest($equipment);

        $this->actingAs($this->admin, 'admin')->postJson(route('admin.requests.update'), [
            'request_id' => $requestId,
            'status' => 'Approved',
        ])->assertOk()->assertJson(['success' => true]);

        $payload = $this->payload();

        $this->assertSame(0, $payload['stats']['pendingRequests']);
        $this->assertSame(1, $payload['stats']['borrowedEquipment']);
        $this->assertSame(0, $payload['stats']['overdueItems']);
        $this->assertSame(1, $payload['stats']['totalAvailableUnits']);
        $this->assertSame(2, $payload['stats']['totalEquipmentUnits']);
    }

    public function test_return_clears_the_borrowed_count_again(): void
    {
        $equipment = $this->makeEquipment(['available_qty' => 1, 'total_qty' => 1]);
        $requestId = $this->submitBorrowRequest($equipment);

        $this->actingAs($this->admin, 'admin')->postJson(route('admin.requests.update'), [
            'request_id' => $requestId,
            'status' => 'Approved',
        ])->assertOk();

        $this->assertSame(1, $this->payload()['stats']['borrowedEquipment']);

        $this->actingAs($this->user, 'user')->postJson(route('user.returns.store'), [
            'request_id' => $requestId,
            'condition' => 'Good',
            'remarks' => '',
        ])->assertOk();

        $payload = $this->payload();

        $this->assertSame(0, $payload['stats']['borrowedEquipment']);
        $this->assertSame(1, $payload['stats']['totalAvailableUnits']);
    }


    public function test_overdue_loan_appears_in_the_overdue_alerts_table(): void
    {
        $equipment = $this->makeEquipment(['available_qty' => 0, 'total_qty' => 2, 'status' => 'Unavailable']);

        $request = $this->makeRequest($equipment, [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'dept_status' => 'Approved',
            'quantity' => 2,
        ]);

        BorrowTransaction::create([
            'request_id' => $request->request_id,
            'borrow_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(4)->toDateString(),
            'status' => 'Active',
        ]);

        $payload = $this->payload();

        $this->assertSame(2, $payload['stats']['borrowedEquipment']);
        $this->assertSame(2, $payload['stats']['overdueItems']);
        $this->assertCount(1, $payload['overdue']);
        $this->assertSame('Juan Dela Cruz', $payload['overdue'][0]['user']);
        $this->assertSame($equipment->name, $payload['overdue'][0]['equipment']);
        $this->assertSame(4, $payload['overdue'][0]['daysLate']);
    }

    public function test_loans_within_their_due_date_are_not_listed_as_overdue(): void
    {
        $equipment = $this->makeEquipment(['available_qty' => 1, 'total_qty' => 2]);

        $request = $this->makeRequest($equipment, [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
        ]);

        BorrowTransaction::create([
            'request_id' => $request->request_id,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'status' => 'Active',
        ]);

        $payload = $this->payload();

        $this->assertSame(1, $payload['stats']['borrowedEquipment']);
        $this->assertSame(0, $payload['stats']['overdueItems']);
        $this->assertSame([], $payload['overdue']);
    }

    public function test_low_stock_lists_out_of_stock_and_almost_empty_equipment(): void
    {
        $this->makeEquipment(['name' => 'Projector Epson', 'available_qty' => 0, 'total_qty' => 2, 'status' => 'Unavailable']);
        $this->makeEquipment(['name' => 'Camera Canon', 'available_qty' => 1, 'total_qty' => 4]);
        $this->makeEquipment(['name' => 'Laptop Dell', 'available_qty' => 5, 'total_qty' => 5]);

        $lowStock = collect($this->payload()['lowStock']);

        $this->assertSame(['Projector Epson', 'Camera Canon'], $lowStock->pluck('name')->all());

        $outOfStock = $lowStock->firstWhere('name', 'Projector Epson');
        $this->assertTrue($outOfStock['critical']);
        $this->assertSame('Out of Stock', $outOfStock['label']);
        $this->assertSame(0, $outOfStock['available']);
        $this->assertSame(2, $outOfStock['total']);

        $almostEmpty = $lowStock->firstWhere('name', 'Camera Canon');
        $this->assertFalse($almostEmpty['critical']);
        $this->assertSame('Low Stock', $almostEmpty['label']);
    }

    public function test_system_summary_counts_this_months_requests_by_status(): void
    {
        $equipment = $this->makeEquipment();

        $this->makeRequest($equipment, ['overall_status' => 'Approved', 'admin_status' => 'Approved']);
        $this->makeRequest($equipment, ['overall_status' => 'Rejected', 'admin_status' => 'Rejected']);
        $this->makeRequest($equipment);
        // Outside the current month, so it must not be counted.
        $this->makeRequest($equipment, ['date_requested' => now()->subMonths(2)->startOfMonth()]);

        $summary = $this->payload()['summary'];

        $this->assertSame(1, $summary['approved']);
        $this->assertSame(1, $summary['rejected']);
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(3, $summary['total']);
        $this->assertSame(now()->format('F Y'), $summary['month']);
    }

    public function test_recent_requests_show_real_status_and_only_pending_can_be_acted_on(): void
    {
        $equipment = $this->makeEquipment();

        $pending = $this->makeRequest($equipment);
        $approved = $this->makeRequest($equipment, ['overall_status' => 'Approved', 'admin_status' => 'Approved']);

        $rows = collect($this->payload()['recentRequests']);

        $pendingRow = $rows->firstWhere('id', $pending->request_id);
        $this->assertSame('Pending', $pendingRow['status']);
        $this->assertTrue($pendingRow['canAct']);
        $this->assertSame('Juan Dela Cruz', $pendingRow['user']);
        $this->assertSame($equipment->name, $pendingRow['equipment']);

        $approvedRow = $rows->firstWhere('id', $approved->request_id);
        $this->assertSame('Approved', $approvedRow['status']);
        $this->assertFalse($approvedRow['canAct']);
    }

    public function test_user_and_equipment_counts_follow_new_records(): void
    {
        $this->makeEquipment();
        $this->makeEquipment(['serial_number' => 'EQ-SECOND']);

        DepartmentAccount::create([
            'full_name' => 'Dept Head',
            'email' => 'dept@example.com',
            'password' => Hash::make('password'),
            'role' => 'Department Head',
            'department_id' => $this->department->department_id,
            'status' => 'Active',
        ]);

        $stats = $this->payload()['stats'];

        // 1 student account + 1 department account
        $this->assertSame(2, $stats['totalUsers']);
        $this->assertSame(2, $stats['totalEquipment']);
        $this->assertSame(6, $stats['totalEquipmentUnits']);
    }

}
