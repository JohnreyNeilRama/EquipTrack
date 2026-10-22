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
 * Equipment Classification and the approval workflow it selects:
 *
 *  - The admin sets Expensive / Not Expensive when adding or editing equipment.
 *  - Not Expensive: handled by the department alone. The request is hidden
 *    from the admin's lists and the admin cannot approve or reject it.
 *  - Expensive: the request is fully approved, and the loan activated, only
 *    after BOTH the admin and the department approve. Either rejection rejects.
 */
class EquipmentClassificationApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private EquipmentCategory $category;

    private UserAccount $user;

    private Admin $admin;

    private DepartmentAccount $deptAccount;

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
            'full_name' => 'Lab Custodian',
            'email' => 'dept@example.com',
            'password' => Hash::make('password'),
            'role' => 'Department Head',
            'department_id' => $this->department->department_id,
            'status' => 'Active',
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function makeEquipment(?string $classification, int $available = 3, int $total = 3): Equipment
    {
        $attributes = [
            'name' => 'Dell Latitude',
            'brand' => 'Dell',
            'serial_number' => 'EQ-' . uniqid(),
            'image' => '',
            'available_qty' => $available,
            'total_qty' => $total,
            'status' => $available > 0 ? 'Available' : 'Unavailable',
            'category_id' => $this->category->category_id,
            'department_id' => $this->department->department_id,
        ];

        if ($classification !== null) {
            $attributes['classification'] = $classification;
        }

        return Equipment::create($attributes);
    }

    private function equipmentPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Epson Projector',
            'brand' => 'Epson',
            'model' => 'EB-X06',
            'serial_number' => 'SN-' . uniqid(),
            'image' => '/storage/images/projector.jpg',
            'available_qty' => 2,
            'total_qty' => 2,
            'status' => 'Available',
            'classification' => 'Expensive',
            'category_id' => $this->category->category_id,
            'department_id' => $this->department->department_id,
        ], $overrides);
    }

    private function saveAsAdmin(array $payload)
    {
        return $this->actingAs($this->admin, 'admin')->postJson(route('admin.equipment.save'), $payload);
    }

    private function submit(Equipment $equipment): int
    {
        $response = $this->actingAs($this->user, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $equipment->equipment_id,
            'borrow_date' => now()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'purpose' => 'Thesis demo',
            'instructor_name' => 'Prof. Santos',
        ]);

        $response->assertOk()->assertJson(['success' => true]);

        return (int) $response->json('request_id');
    }

    private function adminReview(int $requestId, string $status = 'Approved', ?string $reason = null)
    {
        $payload = ['request_id' => $requestId, 'status' => $status];
        if ($reason !== null) {
            $payload['reject_reason'] = $reason;
        }

        return $this->actingAs($this->admin, 'admin')->postJson(route('admin.requests.update'), $payload);
    }

    private function deptReview(int $requestId, string $status = 'Approved', ?string $reason = null)
    {
        $payload = ['request_id' => $requestId, 'status' => $status];
        if ($reason !== null) {
            $payload['reject_reason'] = $reason;
        }

        return $this->actingAs($this->deptAccount, 'dept')->postJson(route('department.requests.update'), $payload);
    }

    private function request(int $id): BorrowRequest
    {
        return BorrowRequest::findOrFail($id);
    }

    // ------------------------------------------------------------------
    // Classification field (Equipment Management)
    // ------------------------------------------------------------------

    public function test_admin_adds_equipment_with_a_classification(): void
    {
        $this->saveAsAdmin($this->equipmentPayload(['serial_number' => 'SN-EXP-1']))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('equipment', ['serial_number' => 'SN-EXP-1', 'classification' => 'Expensive']);

        $this->saveAsAdmin($this->equipmentPayload(['serial_number' => 'SN-CHEAP-1', 'classification' => 'Not Expensive']))
            ->assertOk();

        $this->assertDatabaseHas('equipment', ['serial_number' => 'SN-CHEAP-1', 'classification' => 'Not Expensive']);
    }

    public function test_classification_is_required_and_must_be_a_valid_option(): void
    {
        $payload = $this->equipmentPayload();
        unset($payload['classification']);

        $this->saveAsAdmin($payload)->assertStatus(422)->assertJsonValidationErrors('classification');
        $this->saveAsAdmin($this->equipmentPayload(['classification' => '']))->assertStatus(422)->assertJsonValidationErrors('classification');
        $this->saveAsAdmin($this->equipmentPayload(['classification' => 'Cheap']))->assertStatus(422)->assertJsonValidationErrors('classification');

        $this->assertSame(0, Equipment::count());
    }

    public function test_editing_changes_the_classification_and_the_feed_returns_it(): void
    {
        $equipment = $this->makeEquipment('Not Expensive');

        $this->saveAsAdmin($this->equipmentPayload([
            'equipment_id' => $equipment->equipment_id,
            'serial_number' => $equipment->serial_number,
            'classification' => 'Expensive',
        ]))->assertOk();

        $this->assertSame('Expensive', $equipment->fresh()->classification);

        $row = collect($this->actingAs($this->admin, 'admin')->getJson(route('admin.equipment.data'))->json('equipment'))
            ->firstWhere('equipment_id', $equipment->equipment_id);
        $this->assertSame('Expensive', $row['classification']);

        // An invalid value is refused on edit too, and nothing changes.
        $this->saveAsAdmin($this->equipmentPayload([
            'equipment_id' => $equipment->equipment_id,
            'serial_number' => $equipment->serial_number,
            'classification' => 'Priceless',
        ]))->assertStatus(422);
        $this->assertSame('Expensive', $equipment->fresh()->classification);
    }

    public function test_an_edit_without_the_field_keeps_the_saved_classification(): void
    {
        $equipment = $this->makeEquipment('Expensive');

        $payload = $this->equipmentPayload([
            'equipment_id' => $equipment->equipment_id,
            'serial_number' => $equipment->serial_number,
            'name' => 'Renamed Laptop',
        ]);
        unset($payload['classification']);

        $this->saveAsAdmin($payload)->assertOk();

        $fresh = $equipment->fresh();
        $this->assertSame('Renamed Laptop', $fresh->name);
        $this->assertSame('Expensive', $fresh->classification);
    }

    public function test_existing_equipment_defaults_to_not_expensive_and_departments_must_classify_new_items(): void
    {
        $legacy = $this->makeEquipment(null);
        $this->assertSame('Not Expensive', $legacy->fresh()->classification);

        $payload = [
            'name' => 'Arduino Kit',
            'brand' => 'Arduino',
            'serial_number' => 'SN-DEPT-1',
            'image' => '/storage/images/kit.jpg',
            'available_qty' => 5,
            'total_qty' => 5,
            'status' => 'Available',
            'category_id' => $this->category->category_id,
        ];

        // Like the admin, a department must choose a classification for new equipment.
        $this->actingAs($this->deptAccount, 'dept')->postJson(route('department.equipment.save'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('classification');

        $this->actingAs($this->deptAccount, 'dept')->postJson(route('department.equipment.save'), $payload + ['classification' => 'Expensive'])
            ->assertOk();

        $this->assertDatabaseHas('equipment', ['serial_number' => 'SN-DEPT-1', 'classification' => 'Expensive']);
    }

    public function test_a_department_edit_never_changes_the_classification(): void
    {
        $equipment = $this->makeEquipment('Expensive');

        $this->actingAs($this->deptAccount, 'dept')->postJson(route('department.equipment.save'), [
            'equipment_id' => $equipment->equipment_id,
            'name' => 'Dell Latitude 5420',
            'brand' => 'Dell',
            'serial_number' => $equipment->serial_number,
            'image' => '',
            'available_qty' => 3,
            'total_qty' => 3,
            'status' => 'Available',
            'category_id' => $this->category->category_id,
        ])->assertOk();

        $this->assertSame('Expensive', $equipment->fresh()->classification);
    }

    // ------------------------------------------------------------------
    // Submission reads the classification from the database
    // ------------------------------------------------------------------

    public function test_submitting_a_request_records_the_equipment_classification(): void
    {
        $expensive = $this->makeEquipment('Expensive');
        $cheap = $this->makeEquipment('Not Expensive');

        $this->assertSame('Expensive', $this->request($this->submit($expensive))->equipment_classification);
        $this->assertSame('Not Expensive', $this->request($this->submit($cheap))->equipment_classification);
    }

    // ------------------------------------------------------------------
    // Not Expensive: department only
    // ------------------------------------------------------------------

    public function test_the_admin_cannot_approve_or_reject_a_not_expensive_request(): void
    {
        $equipment = $this->makeEquipment('Not Expensive');
        $id = $this->submit($equipment);

        $this->adminReview($id)->assertStatus(403)->assertJson(['success' => false]);
        $this->adminReview($id, 'Rejected', 'Not allowed.')->assertStatus(403);

        $request = $this->request($id);
        $this->assertSame('Pending', $request->overall_status);
        $this->assertSame('Pending', $request->admin_status);
        $this->assertNull($request->admin_id);
        $this->assertNull(BorrowTransaction::where('request_id', $id)->first());
        $this->assertSame(3, (int) $equipment->fresh()->available_qty);
    }

    public function test_not_expensive_requests_are_hidden_from_the_admin_and_shown_to_the_department(): void
    {
        $cheapId = $this->submit($this->makeEquipment('Not Expensive'));
        $expensiveId = $this->submit($this->makeEquipment('Expensive'));

        $adminRows = collect($this->actingAs($this->admin, 'admin')->getJson(route('admin.requests.data'))->json('requests'));
        $this->assertNull($adminRows->firstWhere('id', $cheapId));
        $this->assertNotNull($adminRows->firstWhere('id', $expensiveId));
        $this->assertTrue($adminRows->firstWhere('id', $expensiveId)['canReview']);

        $adminRecent = collect($this->actingAs($this->admin, 'admin')->getJson(route('admin.dashboard.data'))->json('recentRequests'));
        $this->assertNull($adminRecent->firstWhere('id', $cheapId));
        $this->assertNotNull($adminRecent->firstWhere('id', $expensiveId));

        $deptRows = collect($this->actingAs($this->deptAccount, 'dept')->get(route('department.requests'))->viewData('dbRequests'));
        $this->assertNotNull($deptRows->firstWhere('id', $cheapId));
        $this->assertNotNull($deptRows->firstWhere('id', $expensiveId));
    }

    public function test_the_department_table_uses_the_same_rows_and_layout_as_the_admin_table(): void
    {
        $id = $this->submit($this->makeEquipment('Expensive'));

        $adminRow = collect($this->actingAs($this->admin, 'admin')->getJson(route('admin.requests.data'))->json('requests'))
            ->firstWhere('id', $id);
        $deptRow = collect($this->actingAs($this->deptAccount, 'dept')->getJson(route('department.requests.data'))->assertOk()->json('requests'))
            ->firstWhere('id', $id);

        $this->assertSame(array_keys($adminRow), array_keys($deptRow));
        $this->assertTrue($deptRow['canReview']);

        // Same compact table on both pages (Request ID column, truncating User
        // column with the role under the name, no separate Role column) and the
        // same modals.
        foreach ([[$this->admin, 'admin', 'admin.requests'], [$this->deptAccount, 'dept', 'department.requests']] as [$account, $guard, $route]) {
            $this->actingAs($account, $guard)->get(route($route))
                ->assertOk()
                ->assertSee('class="table-container card admin-table-card"', false)
                // The Department page adds its own column-width class after it.
                ->assertSee('class="requests-table-compact', false)
                ->assertSeeInOrder(['<th>Request ID</th>', '<th class="col-user">User</th>', '<th>Equipment</th>', '<th>Date</th>', '<th>Status</th>', 'Action</th>'], false)
                ->assertDontSee('<th>Role</th>', false)
                ->assertSee('const COMBINE_USER_ROLE = true', false)
                ->assertSee('id="detailsModal"', false)
                ->assertSee('id="rejectReasonModal"', false);
        }

        // Each reviewer polls its own feed and posts to its own endpoint.
        $this->actingAs($this->deptAccount, 'dept')->get(route('department.requests'))
            ->assertSee('const PARTY = "dept"', false)
            ->assertDontSee(json_encode(route('admin.requests.update')), false);
    }

    public function test_the_department_rejects_a_not_expensive_request(): void
    {
        $equipment = $this->makeEquipment('Not Expensive');
        $id = $this->submit($equipment);

        $this->deptReview($id, 'Rejected', 'Unit under repair.')->assertOk()->assertJson(['overallStatus' => 'Rejected']);

        $request = $this->request($id);
        $this->assertSame('Rejected', $request->overall_status);
        $this->assertSame('Rejected', $request->dept_status);
        $this->assertSame('Pending', $request->admin_status);
        $this->assertSame(3, (int) $equipment->fresh()->available_qty);
    }

    public function test_not_expensive_is_fully_approved_by_the_department_alone(): void
    {
        $equipment = $this->makeEquipment('Not Expensive');
        $id = $this->submit($equipment);

        $this->deptReview($id)->assertOk()->assertJson(['overallStatus' => 'Approved']);

        $this->assertSame('Approved', $this->request($id)->overall_status);
        $this->assertSame(2, (int) $equipment->fresh()->available_qty);
    }

    public function test_requests_from_before_classification_are_handled_by_the_department(): void
    {
        $equipment = $this->makeEquipment('Expensive');
        $id = $this->submit($equipment);

        // Simulate a request submitted before the column existed.
        BorrowRequest::where('request_id', $id)->update(['equipment_classification' => null]);

        $this->adminReview($id)->assertStatus(403);
        $this->deptReview($id)->assertOk()->assertJson(['overallStatus' => 'Approved']);
        $this->assertSame('Approved', $this->request($id)->overall_status);
    }

    public function test_the_admin_keeps_seeing_requests_it_reviewed_before_classification(): void
    {
        $id = $this->submit($this->makeEquipment('Not Expensive'));
        BorrowRequest::where('request_id', $id)->update([
            'equipment_classification' => null,
            'admin_status' => 'Approved',
            'overall_status' => 'Approved',
            'admin_id' => $this->admin->admin_id,
        ]);

        $row = collect($this->actingAs($this->admin, 'admin')->getJson(route('admin.requests.data'))->json('requests'))
            ->firstWhere('id', $id);

        $this->assertNotNull($row);
        $this->assertFalse($row['canReview']);
    }

    // ------------------------------------------------------------------
    // Expensive: both parties must approve
    // ------------------------------------------------------------------

    public function test_expensive_admin_approval_alone_is_not_full_approval(): void
    {
        $equipment = $this->makeEquipment('Expensive');
        $id = $this->submit($equipment);

        $this->adminReview($id)->assertOk()->assertJson([
            'success' => true,
            'overallStatus' => 'Pending',
            'adminStatus' => 'Approved',
        ]);

        $request = $this->request($id);
        $this->assertSame('Approved', $request->admin_status);
        $this->assertSame('Pending', $request->dept_status);
        $this->assertSame('Pending', $request->overall_status);
        $this->assertNull(BorrowTransaction::where('request_id', $id)->first(), 'No loan until both approve.');
        $this->assertSame(3, (int) $equipment->fresh()->available_qty, 'Stock is untouched until both approve.');

        // The admin cannot approve the same request twice.
        $this->adminReview($id)->assertStatus(400);

        // The department's approval completes it.
        $this->deptReview($id)->assertOk()->assertJson([
            'overallStatus' => 'Approved',
            'message' => 'Borrow request approved successfully!',
        ]);

        $request = $this->request($id);
        $this->assertSame('Approved', $request->overall_status);
        $this->assertSame('Approved', $request->admin_status);
        $this->assertSame('Approved', $request->dept_status);
        $this->assertSame(1, BorrowTransaction::where('request_id', $id)->count());
        $this->assertSame(2, (int) $equipment->fresh()->available_qty, 'Stock is deducted exactly once.');
    }

    public function test_expensive_department_first_then_admin(): void
    {
        $equipment = $this->makeEquipment('Expensive');
        $id = $this->submit($equipment);

        $this->deptReview($id)->assertOk()->assertJson(['overallStatus' => 'Pending', 'deptStatus' => 'Approved']);
        $this->assertSame('Pending', $this->request($id)->overall_status);
        $this->assertSame(3, (int) $equipment->fresh()->available_qty);

        $this->adminReview($id)->assertOk()->assertJson(['overallStatus' => 'Approved']);

        $this->assertSame('Approved', $this->request($id)->overall_status);
        $this->assertSame(2, (int) $equipment->fresh()->available_qty);
        $this->assertSame(1, BorrowTransaction::where('request_id', $id)->count());
    }

    public function test_expensive_department_rejection_after_admin_approval_rejects_the_request(): void
    {
        $equipment = $this->makeEquipment('Expensive');
        $id = $this->submit($equipment);

        $this->adminReview($id)->assertOk();
        $this->deptReview($id, 'Rejected', 'Unit reserved for a lab class.')->assertOk()->assertJson(['overallStatus' => 'Rejected']);

        $request = $this->request($id);
        $this->assertSame('Approved', $request->admin_status);
        $this->assertSame('Rejected', $request->dept_status);
        $this->assertSame('Rejected', $request->overall_status);
        $this->assertSame('Unit reserved for a lab class.', $request->reject_reason);
        $this->assertNull(BorrowTransaction::where('request_id', $id)->first());
        $this->assertSame(3, (int) $equipment->fresh()->available_qty);
    }

    public function test_expensive_admin_rejection_after_department_approval_rejects_the_request(): void
    {
        $equipment = $this->makeEquipment('Expensive');
        $id = $this->submit($equipment);

        $this->deptReview($id)->assertOk();
        $this->adminReview($id, 'Rejected', 'Not for off-campus use.')->assertOk()->assertJson(['overallStatus' => 'Rejected']);

        $this->assertSame('Rejected', $this->request($id)->overall_status);
        $this->assertNull(BorrowTransaction::where('request_id', $id)->first());
        $this->assertSame(3, (int) $equipment->fresh()->available_qty);
    }

    public function test_expensive_rejected_request_can_never_become_approved(): void
    {
        $equipment = $this->makeEquipment('Expensive');
        $id = $this->submit($equipment);

        $this->adminReview($id, 'Rejected', 'Incomplete purpose.')->assertOk();

        $this->deptReview($id)->assertStatus(400);

        $this->assertSame('Rejected', $this->request($id)->overall_status);
        $this->assertSame('Pending', $this->request($id)->dept_status);
        $this->assertNull(BorrowTransaction::where('request_id', $id)->first());
    }

    public function test_expensive_final_approval_is_blocked_when_stock_ran_out(): void
    {
        $equipment = $this->makeEquipment('Expensive', 1, 1);
        $first = $this->submit($equipment);
        $second = $this->submit($equipment);

        // Both get the admin's approval; neither has a loan yet.
        $this->adminReview($first)->assertOk();
        $this->adminReview($second)->assertOk();
        $this->assertSame(1, (int) $equipment->fresh()->available_qty);

        $this->deptReview($first)->assertOk()->assertJson(['overallStatus' => 'Approved']);
        $this->assertSame(0, (int) $equipment->fresh()->available_qty);

        // No stock left for the second one: refused, and still not approved.
        $this->deptReview($second)->assertStatus(400)->assertJson(['success' => false]);

        $request = $this->request($second);
        $this->assertSame('Pending', $request->overall_status);
        $this->assertSame('Pending', $request->dept_status);
        $this->assertSame('Approved', $request->admin_status);
        $this->assertSame(0, (int) $equipment->fresh()->available_qty, 'Stock never goes negative.');
    }

    public function test_reclassifying_equipment_does_not_change_requests_already_submitted(): void
    {
        $equipment = $this->makeEquipment('Not Expensive');
        $id = $this->submit($equipment);

        $equipment->update(['classification' => 'Expensive']);

        // Submitted as Not Expensive: still the department's alone.
        $this->adminReview($id)->assertStatus(403);
        $this->deptReview($id)->assertOk()->assertJson(['overallStatus' => 'Approved']);

        // New requests follow the new classification.
        $newId = $this->submit($equipment);
        $this->adminReview($newId)->assertOk()->assertJson(['overallStatus' => 'Pending']);
    }

    // ------------------------------------------------------------------
    // Both parties' statuses are exposed separately
    // ------------------------------------------------------------------

    public function test_request_lists_show_each_partys_status(): void
    {
        $id = $this->submit($this->makeEquipment('Expensive'));
        $this->adminReview($id)->assertOk();

        $row = collect($this->actingAs($this->admin, 'admin')->getJson(route('admin.requests.data'))->json('requests'))
            ->firstWhere('id', $id);

        $this->assertSame('Pending', $row['status']);
        $this->assertSame('Approved', $row['adminStatus']);
        $this->assertSame('Pending', $row['deptStatus']);
        $this->assertSame('Expensive', $row['classification']);
        $this->assertTrue($row['requiresBothApprovals']);

        $this->actingAs($this->deptAccount, 'dept')
            ->get(route('department.requests'))
            ->assertOk()
            ->assertSee('"requiresBothApprovals":true', false)
            ->assertSee('"adminStatus":"Approved"', false);

        $this->actingAs($this->user, 'user')
            ->get(route('user.requests'))
            ->assertOk()
            ->assertSee('data-dual="1"', false)
            ->assertSee('data-admin-status="Approved"', false)
            ->assertSee('data-dept-status="Pending"', false);
    }

    public function test_the_user_is_not_told_approved_until_both_parties_approve(): void
    {
        $id = $this->submit($this->makeEquipment('Expensive'));

        $this->adminReview($id)->assertOk();

        $feed = $this->actingAs($this->user, 'user')->getJson(route('user.notifications'))->json();
        $this->assertSame([], $feed['notifications']);

        $dashboard = $this->actingAs($this->user, 'user')->getJson(route('user.dashboard.data'))->json();
        $this->assertSame(1, $dashboard['stats']['pendingRequests']);
        $this->assertSame(0, $dashboard['stats']['activeBorrows']);

        $this->deptReview($id)->assertOk();

        $feed = $this->actingAs($this->user, 'user')->getJson(route('user.notifications'))->json();
        $this->assertSame('approved', $feed['notifications'][0]['type']);
    }
}
