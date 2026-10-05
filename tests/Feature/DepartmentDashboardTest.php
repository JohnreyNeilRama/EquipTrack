<?php

namespace Tests\Feature;

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
 * Department Dashboard tests:
 * Ensures all stats, tables, and live data are strictly scoped to the
 * authenticated department account and update accurately with database changes.
 */
class DepartmentDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Department $deptA;

    private Department $deptB;

    private DepartmentAccount $deptAccountA;

    private DepartmentAccount $deptAccountB;

    private UserAccount $userA;

    private UserAccount $userB;

    private EquipmentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deptA = Department::create([
            'department_name' => 'College of Computer Studies',
            'department_code' => 'CCS',
        ]);

        $this->deptB = Department::create([
            'department_name' => 'College of Criminal Justice',
            'department_code' => 'CCJ',
        ]);

        $this->deptAccountA = DepartmentAccount::create([
            'full_name' => 'CCS Head',
            'email' => 'ccs-head@example.com',
            'password' => Hash::make('password'),
            'role' => 'Department Head',
            'department_id' => $this->deptA->department_id,
            'status' => 'Active',
        ]);

        $this->deptAccountB = DepartmentAccount::create([
            'full_name' => 'CCJ Head',
            'email' => 'ccj-head@example.com',
            'password' => Hash::make('password'),
            'role' => 'Department Head',
            'department_id' => $this->deptB->department_id,
            'status' => 'Active',
        ]);

        $this->category = EquipmentCategory::create(['category_name' => 'Laptops']);

        $this->userA = UserAccount::create([
            'role' => 'Student',
            'email' => 'studentA@example.com',
            'password' => Hash::make('password'),
            'department_id' => $this->deptA->department_id,
            'status' => 'Active',
        ]);

        Student::create([
            'user_id' => $this->userA->user_id,
            'first_name' => 'Alice',
            'last_name' => 'Smith',
            'id_number' => 'CCS-001',
            'year_level' => '3',
            'address' => 'Cebu City',
        ]);

        $this->userB = UserAccount::create([
            'role' => 'Student',
            'email' => 'studentB@example.com',
            'password' => Hash::make('password'),
            'department_id' => $this->deptB->department_id,
            'status' => 'Active',
        ]);

        Student::create([
            'user_id' => $this->userB->user_id,
            'first_name' => 'Bob',
            'last_name' => 'Jones',
            'id_number' => 'CCJ-001',
            'year_level' => '2',
            'address' => 'Mandaue City',
        ]);
    }

    private function makeEquipment(Department $dept, array $overrides = []): Equipment
    {
        return Equipment::create(array_merge([
            'name' => 'Test Equipment',
            'brand' => 'BrandX',
            'serial_number' => 'SN-' . uniqid(),
            'image' => '/storage/images/eq.jpg',
            'available_qty' => 5,
            'total_qty' => 5,
            'status' => 'Available',
            'category_id' => $this->category->category_id,
            'department_id' => $dept->department_id,
        ], $overrides));
    }

    private function makeRequest(Equipment $equipment, UserAccount $user, array $overrides = []): BorrowRequest
    {
        $request = BorrowRequest::create(array_merge([
            'user_id' => $user->user_id,
            'equipment_id' => $equipment->equipment_id,
            'quantity' => 1,
            'purpose' => 'Research',
            'notes' => '',
            'date_needed' => now()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'borrow_date' => now()->toDateString(),
            'overall_status' => 'Pending',
            'admin_status' => 'Approved',
            'dept_status' => 'Pending',
        ], $overrides));

        $request->forceFill(['date_requested' => $overrides['date_requested'] ?? now()])->save();

        return $request->refresh();
    }

    public function test_dashboard_renders_with_zero_state_when_empty(): void
    {
        $response = $this->actingAs($this->deptAccountA, 'dept')
            ->get(route('department.dashboard'));

        $response->assertOk();
        $response->assertSee('Quick Stats');
        $response->assertSee('Recent Borrow Requests');
        $response->assertSee('Overdue Alerts');
        $response->assertSee('Upcoming Returns');
        $response->assertSee('No recent borrow requests found.');
        $response->assertSee('No overdue items.');
        $response->assertSee('No upcoming returns scheduled.');
    }

    public function test_dashboard_data_endpoint_returns_json_structure(): void
    {
        $response = $this->actingAs($this->deptAccountA, 'dept')
            ->getJson(route('department.dashboard.data'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'stats' => [
                'totalEquipment' => 0,
                'totalEquipmentUnits' => 0,
                'availableUnits' => 0,
                'pendingRequests' => 0,
                'borrowedEquipment' => 0,
                'overdueItems' => 0,
                'departmentUsers' => 1, // studentA is the only user in deptA
            ],
            'recentRequests' => [],
            'overdue' => [],
            'upcoming' => [],
        ]);

        $response->assertJsonStructure([
            'success',
            'stats' => [
                'totalEquipment',
                'totalEquipmentUnits',
                'availableUnits',
                'pendingRequests',
                'borrowedEquipment',
                'overdueItems',
                'departmentUsers',
            ],
            'recentRequests',
            'overdue',
            'upcoming',
            'generatedAt',
        ]);
    }

    public function test_dashboard_strictly_scopes_equipment_and_users_to_signed_in_department(): void
    {
        $eqA = $this->makeEquipment($this->deptA, ['total_qty' => 10, 'available_qty' => 8]);
        $eqB = $this->makeEquipment($this->deptB, ['total_qty' => 4, 'available_qty' => 4]);

        // Dept A
        $dataA = $this->actingAs($this->deptAccountA, 'dept')
            ->getJson(route('department.dashboard.data'))
            ->json();

        $this->assertSame(1, $dataA['stats']['totalEquipment']);
        $this->assertSame(10, $dataA['stats']['totalEquipmentUnits']);
        $this->assertSame(8, $dataA['stats']['availableUnits']);
        $this->assertSame(1, $dataA['stats']['departmentUsers']);

        // Dept B
        $dataB = $this->actingAs($this->deptAccountB, 'dept')
            ->getJson(route('department.dashboard.data'))
            ->json();

        $this->assertSame(1, $dataB['stats']['totalEquipment']);
        $this->assertSame(4, $dataB['stats']['totalEquipmentUnits']);
        $this->assertSame(4, $dataB['stats']['availableUnits']);
        $this->assertSame(1, $dataB['stats']['departmentUsers']);
    }

    public function test_pending_requests_are_counted_per_department(): void
    {
        $eqA = $this->makeEquipment($this->deptA);
        $eqB = $this->makeEquipment($this->deptB);

        $this->makeRequest($eqA, $this->userA, ['dept_status' => 'Pending']);
        $this->makeRequest($eqB, $this->userB, ['dept_status' => 'Pending']);

        $dataA = $this->actingAs($this->deptAccountA, 'dept')
            ->getJson(route('department.dashboard.data'))
            ->json();

        $this->assertSame(1, $dataA['stats']['pendingRequests']);
        $this->assertCount(1, $dataA['recentRequests']);
        $this->assertSame('Alice Smith', $dataA['recentRequests'][0]['user']);

        $dataB = $this->actingAs($this->deptAccountB, 'dept')
            ->getJson(route('department.dashboard.data'))
            ->json();

        $this->assertSame(1, $dataB['stats']['pendingRequests']);
        $this->assertCount(1, $dataB['recentRequests']);
        $this->assertSame('Bob Jones', $dataB['recentRequests'][0]['user']);
    }

    public function test_borrowed_equipment_and_upcoming_returns(): void
    {
        $eqA = $this->makeEquipment($this->deptA);

        $req = $this->makeRequest($eqA, $this->userA, [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'dept_status' => 'Approved',
            'quantity' => 2,
        ]);

        $due = now()->addDays(2)->startOfDay();

        BorrowTransaction::create([
            'request_id' => $req->request_id,
            'user_id' => $this->userA->user_id,
            'equipment_id' => $eqA->equipment_id,
            'quantity' => 2,
            'status' => 'Active',
            'borrow_date' => now()->toDateString(),
            'due_date' => $due->toDateTimeString(),
        ]);

        $data = $this->actingAs($this->deptAccountA, 'dept')
            ->getJson(route('department.dashboard.data'))
            ->json();

        $this->assertSame(2, $data['stats']['borrowedEquipment']);
        $this->assertSame(0, $data['stats']['overdueItems']);
        $this->assertCount(0, $data['overdue']);
        $this->assertCount(1, $data['upcoming']);
        $this->assertSame('Due in 2 Days', $data['upcoming'][0]['daysRemainingText']);
        $this->assertSame('Alice Smith', $data['upcoming'][0]['user']);
    }

    public function test_overdue_alerts_for_past_due_loans(): void
    {
        $eqA = $this->makeEquipment($this->deptA);

        $req = $this->makeRequest($eqA, $this->userA, [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'dept_status' => 'Approved',
            'quantity' => 1,
        ]);

        $pastDue = now()->subDays(3)->startOfDay();

        BorrowTransaction::create([
            'request_id' => $req->request_id,
            'user_id' => $this->userA->user_id,
            'equipment_id' => $eqA->equipment_id,
            'quantity' => 1,
            'status' => 'Active',
            'borrow_date' => now()->subDays(6)->toDateString(),
            'due_date' => $pastDue->toDateTimeString(),
        ]);

        $data = $this->actingAs($this->deptAccountA, 'dept')
            ->getJson(route('department.dashboard.data'))
            ->json();

        $this->assertSame(1, $data['stats']['borrowedEquipment']);
        $this->assertSame(1, $data['stats']['overdueItems']);
        $this->assertCount(1, $data['overdue']);
        $this->assertSame('Alice Smith', $data['overdue'][0]['user']);
        $this->assertSame(3, $data['overdue'][0]['daysLate']);
        $this->assertSame('3 Days', $data['overdue'][0]['daysLateText']);
        $this->assertCount(0, $data['upcoming']);
    }

    public function test_returned_transactions_are_not_counted_as_borrowed_or_overdue(): void
    {
        $eqA = $this->makeEquipment($this->deptA);

        $req = $this->makeRequest($eqA, $this->userA, [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'dept_status' => 'Approved',
            'quantity' => 1,
        ]);

        BorrowTransaction::create([
            'request_id' => $req->request_id,
            'user_id' => $this->userA->user_id,
            'equipment_id' => $eqA->equipment_id,
            'quantity' => 1,
            'status' => 'Returned',
            'borrow_date' => now()->subDays(6)->toDateString(),
            'due_date' => now()->subDays(2)->toDateTimeString(),
            'returned_at' => now()->toDateTimeString(),
        ]);

        $data = $this->actingAs($this->deptAccountA, 'dept')
            ->getJson(route('department.dashboard.data'))
            ->json();

        $this->assertSame(0, $data['stats']['borrowedEquipment']);
        $this->assertSame(0, $data['stats']['overdueItems']);
        $this->assertCount(0, $data['overdue']);
        $this->assertCount(0, $data['upcoming']);
    }

    public function test_rendered_dashboard_shows_real_rows_and_hides_other_departments(): void
    {
        $eqA = $this->makeEquipment($this->deptA, [
            'name' => 'CCS Projector',
            'total_qty' => 4,
            'available_qty' => 3,
        ]);

        // A pending request plus one overdue loan and one upcoming return for dept A.
        $this->makeRequest($eqA, $this->userA, [
            'dept_status' => 'Pending',
            'overall_status' => 'Pending',
        ]);

        $overdueReq = $this->makeRequest($eqA, $this->userA, [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'dept_status' => 'Approved',
            'quantity' => 1,
        ]);

        BorrowTransaction::create([
            'request_id' => $overdueReq->request_id,
            'borrow_date' => now()->subDays(6)->toDateString(),
            'due_date' => now()->subDays(2)->toDateTimeString(),
            'status' => 'Active',
        ]);

        // Dept B must never leak into dept A's dashboard.
        $eqB = $this->makeEquipment($this->deptB, ['name' => 'CCJ Camera']);
        $this->makeRequest($eqB, $this->userB, [
            'dept_status' => 'Pending',
            'overall_status' => 'Pending',
        ]);

        $response = $this->actingAs($this->deptAccountA, 'dept')
            ->get(route('department.dashboard'));

        $response->assertOk();

        // Real data reaches the HTML.
        $response->assertSee('CCS Projector');
        $response->assertSee('Alice Smith');
        $response->assertSee('2 Days');  // daysLateText for the 2-day-past-due loan

        // No other department's data leaks in.
        $response->assertDontSee('CCJ Camera');
        $response->assertDontSee('Bob Jones');

        // And the JSON feed agrees with the server-rendered page.
        $data = $this->actingAs($this->deptAccountA, 'dept')
            ->getJson(route('department.dashboard.data'))
            ->json();

        $this->assertSame(1, $data['stats']['totalEquipment']);
        $this->assertSame(4, $data['stats']['totalEquipmentUnits']);
        $this->assertSame(3, $data['stats']['availableUnits']);
        $this->assertSame(1, $data['stats']['pendingRequests']);
        $this->assertSame(1, $data['stats']['overdueItems']);
        // Both dept A requests are listed (pending + approved), dept B's is not.
        $this->assertCount(2, $data['recentRequests']);
        $this->assertCount(1, $data['overdue']);
        $this->assertSame('CCS Projector', $data['recentRequests'][0]['equipment']);
    }

    public function test_stats_update_after_borrow_approval_and_return(): void
    {
        $eqA = $this->makeEquipment($this->deptA, ['total_qty' => 4, 'available_qty' => 4]);

        $before = $this->actingAs($this->deptAccountA, 'dept')
            ->getJson(route('department.dashboard.data'))->json();

        $this->assertSame(4, $before['stats']['availableUnits']);
        $this->assertSame(0, $before['stats']['borrowedEquipment']);

        $req = $this->makeRequest($eqA, $this->userA, [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'dept_status' => 'Approved',
            'quantity' => 2,
        ]);

        // Same code path the admin/department approval workflow uses.
        $req->activateLoan();

        $afterBorrow = $this->actingAs($this->deptAccountA, 'dept')
            ->getJson(route('department.dashboard.data'))->json();

        $this->assertSame(2, $afterBorrow['stats']['availableUnits']);
        $this->assertSame(2, $afterBorrow['stats']['borrowedEquipment']);
        $this->assertCount(1, $afterBorrow['upcoming']);

        // Returning the item restores stock and clears the loan.
        $transaction = $req->transaction()->first();
        $transaction->update([
            'status' => 'Returned',
            'return_date' => now()->toDateString(),
        ]);

        $afterReturn = $this->actingAs($this->deptAccountA, 'dept')
            ->getJson(route('department.dashboard.data'))->json();

        $this->assertSame(0, $afterReturn['stats']['borrowedEquipment']);
        $this->assertSame(0, $afterReturn['stats']['overdueItems']);
        $this->assertCount(0, $afterReturn['upcoming']);
    }
}
