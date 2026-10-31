<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\StaffNotificationRead;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Notification module for the reviewers: Admin and Lab Personnel (department)
 * get Reservation Status Alerts when a request is approved or rejected, when a
 * new request waits for their review, and (Lab Personnel) when a return waits
 * for confirmation. The alerts are derived from the records, never duplicated,
 * and a department only sees its own equipment's.
 */
class ReviewerNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Department $ccs;

    private Department $cba;

    private EquipmentCategory $category;

    private Admin $admin;

    private DepartmentAccount $ccsLab;

    private DepartmentAccount $cbaLab;

    private UserAccount $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ccs = Department::create(['department_name' => 'College of Computer Studies', 'department_code' => 'CCS']);
        $this->cba = Department::create(['department_name' => 'College of Business', 'department_code' => 'CBA']);
        $this->category = EquipmentCategory::create(['category_name' => 'Laptop']);

        $this->admin = Admin::create([
            'name' => 'System Admin',
            'username' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $this->ccsLab = $this->makeDeptAccount('ccs.lab@example.com', 'Ana Santos', $this->ccs);
        $this->cbaLab = $this->makeDeptAccount('cba.lab@example.com', 'Ben Reyes', $this->cba);

        $this->student = UserAccount::create([
            'role' => 'Student',
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
            'department_id' => $this->ccs->department_id,
            'status' => 'Active',
        ]);

        Student::create([
            'user_id' => $this->student->user_id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'id_number' => '2020-0001',
            'year_level' => '3',
            'address' => 'Cebu City',
        ]);
    }

    private function makeDeptAccount(string $email, string $name, Department $department): DepartmentAccount
    {
        return DepartmentAccount::create([
            'full_name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'Lab Personnel',
            'department_id' => $department->department_id,
            'status' => 'Active',
        ]);
    }

    private function makeEquipment(Department $department, string $name = 'Dell Latitude'): Equipment
    {
        return Equipment::create([
            'name' => $name,
            'brand' => 'Dell',
            'serial_number' => 'EQ-' . uniqid(),
            'image' => '/storage/images/eq.jpg',
            'available_qty' => 3,
            'total_qty' => 3,
            'status' => 'Available',
            'category_id' => $this->category->category_id,
            'department_id' => $department->department_id,
        ]);
    }

    private function makeRequest(Equipment $equipment, array $overrides = []): BorrowRequest
    {
        return BorrowRequest::create(array_merge([
            'user_id' => $this->student->user_id,
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
    }

    private function adminFeed(): array
    {
        $response = $this->actingAs($this->admin, 'admin')->getJson(route('admin.notifications'));
        $response->assertOk()->assertJson(['success' => true]);

        return $response->json();
    }

    private function deptFeed(DepartmentAccount $as): array
    {
        $response = $this->actingAs($as, 'dept')->getJson(route('department.notifications'));
        $response->assertOk()->assertJson(['success' => true]);

        return $response->json();
    }

    public function test_guests_cannot_read_reviewer_notifications(): void
    {
        $this->getJson(route('admin.notifications'))->assertUnauthorized();
        $this->getJson(route('department.notifications'))->assertUnauthorized();
    }

    public function test_a_new_not_expensive_request_alerts_only_the_owning_department(): void
    {
        $request = $this->makeRequest($this->makeEquipment($this->ccs, 'Dell Latitude'), [
            'equipment_classification' => Equipment::CLASS_NOT_EXPENSIVE,
        ]);

        $feed = $this->deptFeed($this->ccsLab);
        $this->assertCount(1, $feed['notifications']);
        $this->assertSame('request-' . $request->request_id . '-new', $feed['notifications'][0]['key']);
        $this->assertSame('request', $feed['notifications'][0]['type']);
        $this->assertSame('New Reservation Request', $feed['notifications'][0]['title']);
        $this->assertStringContainsString('Juan Dela Cruz requested Dell Latitude', $feed['notifications'][0]['message']);
        $this->assertStringNotContainsString('admin', $feed['notifications'][0]['message']);
        $this->assertSame(route('department.requests'), $feed['notifications'][0]['url']);
        $this->assertSame(1, $feed['unreadCount']);

        // The admin does not review Not Expensive requests; other departments never see it.
        $this->assertSame([], $this->adminFeed()['notifications']);
        $this->assertSame([], $this->deptFeed($this->cbaLab)['notifications']);
    }

    public function test_a_new_expensive_request_alerts_the_admin_and_the_department_until_each_reviews_it(): void
    {
        $request = $this->makeRequest($this->makeEquipment($this->ccs), [
            'equipment_classification' => Equipment::CLASS_EXPENSIVE,
        ]);
        $key = 'request-' . $request->request_id . '-new';

        $adminFeed = $this->adminFeed();
        $this->assertSame([$key], array_column($adminFeed['notifications'], 'key'));
        $this->assertStringContainsString('also needs Lab Personnel approval', $adminFeed['notifications'][0]['message']);
        $this->assertSame(route('admin.requests'), $adminFeed['notifications'][0]['url']);

        $deptFeed = $this->deptFeed($this->ccsLab);
        $this->assertSame([$key], array_column($deptFeed['notifications'], 'key'));
        $this->assertStringContainsString('also needs the admin\'s approval', $deptFeed['notifications'][0]['message']);

        // The department approves: its own "new request" notice is replaced by the
        // decision, while the admin still has the request to review.
        $request->update([
            'dept_status' => 'Approved',
            'dept_acc_id' => $this->ccsLab->dept_acc_id,
            'dept_reviewed_at' => now(),
        ]);

        $deptKeys = array_column($this->deptFeed($this->ccsLab)['notifications'], 'key');
        $this->assertNotContains($key, $deptKeys);
        $this->assertContains('review-' . $request->request_id . '-dept-approved', $deptKeys);

        $adminKeys = array_column($this->adminFeed()['notifications'], 'key');
        $this->assertContains($key, $adminKeys);
        $this->assertContains('review-' . $request->request_id . '-dept-approved', $adminKeys);
    }

    public function test_rejected_or_cancelled_requests_no_longer_ask_for_review(): void
    {
        $equipment = $this->makeEquipment($this->ccs);
        $this->makeRequest($equipment, [
            'equipment_classification' => Equipment::CLASS_EXPENSIVE,
            'overall_status' => BorrowRequest::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
        $rejected = $this->makeRequest($equipment, [
            'equipment_classification' => Equipment::CLASS_EXPENSIVE,
            'overall_status' => 'Rejected',
            'dept_status' => 'Rejected',
            'dept_acc_id' => $this->ccsLab->dept_acc_id,
            'dept_reviewed_at' => now(),
        ]);

        // Only the department's rejection is listed; nobody is asked to review either request.
        $this->assertSame(['review-' . $rejected->request_id . '-dept-rejected'], array_column($this->adminFeed()['notifications'], 'key'));
        $this->assertSame(['review-' . $rejected->request_id . '-dept-rejected'], array_column($this->deptFeed($this->ccsLab)['notifications'], 'key'));
    }

    public function test_a_submitted_return_alerts_the_owning_department_until_it_is_confirmed(): void
    {
        $request = $this->makeRequest($this->makeEquipment($this->ccs, 'Dell Latitude'), [
            'overall_status' => 'Approved',
            'dept_status' => 'Approved',
            'dept_acc_id' => $this->ccsLab->dept_acc_id,
            // Reviewed long ago, so only the return is listed.
            'dept_reviewed_at' => now()->subDays(40),
        ]);
        $submittedAt = now()->subMinutes(10)->startOfSecond();
        $transaction = BorrowTransaction::create([
            'request_id' => $request->request_id,
            'borrow_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->toDateString(),
            'status' => 'Active',
            'return_status' => BorrowTransaction::RETURN_PENDING,
            'return_submitted_at' => $submittedAt,
        ]);
        $key = 'return-' . $transaction->transaction_id . '-submitted-' . $submittedAt->getTimestamp();

        $feed = $this->deptFeed($this->ccsLab);
        $this->assertSame([$key], array_column($feed['notifications'], 'key'));
        $this->assertSame('return', $feed['notifications'][0]['type']);
        $this->assertSame('Return Awaiting Confirmation', $feed['notifications'][0]['title']);
        $this->assertStringContainsString('Juan Dela Cruz returned Dell Latitude', $feed['notifications'][0]['message']);
        $this->assertSame(route('department.returns'), $feed['notifications'][0]['url']);

        // Lab Personnel confirm returns, so neither the admin nor another department is alerted.
        $this->assertSame([], $this->adminFeed()['notifications']);
        $this->assertSame([], $this->deptFeed($this->cbaLab)['notifications']);

        // Rejected, then handed in again: a new unread notice.
        $this->actingAs($this->ccsLab, 'dept')
            ->postJson(route('department.notifications.read'), ['keys' => [$key]])
            ->assertOk();
        $resubmittedAt = now()->startOfSecond();
        $transaction->update(['return_submitted_at' => $resubmittedAt]);

        $feed = $this->deptFeed($this->ccsLab);
        $this->assertSame(['return-' . $transaction->transaction_id . '-submitted-' . $resubmittedAt->getTimestamp()], array_column($feed['notifications'], 'key'));
        $this->assertSame(1, $feed['unreadCount']);

        $transaction->update(['status' => 'Returned', 'return_status' => BorrowTransaction::RETURN_CONFIRMED]);
        $this->assertSame([], $this->deptFeed($this->ccsLab)['notifications']);
    }

    public function test_admin_rejection_alerts_admin_and_the_owning_department(): void
    {
        $request = $this->makeRequest($this->makeEquipment($this->ccs, 'Dell Latitude'), [
            'overall_status' => 'Rejected',
            'admin_status' => 'Rejected',
            'admin_id' => $this->admin->admin_id,
            'admin_reviewed_at' => now()->subMinutes(5),
            'reject_reason' => 'Reserved for a class.',
        ]);
        $key = 'review-' . $request->request_id . '-admin-rejected';

        $adminFeed = $this->adminFeed();
        $this->assertCount(1, $adminFeed['notifications']);
        $this->assertSame($key, $adminFeed['notifications'][0]['key']);
        $this->assertSame('rejected', $adminFeed['notifications'][0]['type']);
        $this->assertStringContainsString('You rejected Juan', $adminFeed['notifications'][0]['message']);
        $this->assertStringContainsString('Dell Latitude', $adminFeed['notifications'][0]['message']);
        $this->assertStringContainsString('Reserved for a class.', $adminFeed['notifications'][0]['message']);
        $this->assertSame(1, $adminFeed['unreadCount']);

        $deptFeed = $this->deptFeed($this->ccsLab);
        $this->assertCount(1, $deptFeed['notifications']);
        $this->assertSame($key, $deptFeed['notifications'][0]['key']);
        $this->assertStringContainsString('Admin System Admin rejected', $deptFeed['notifications'][0]['message']);
    }

    public function test_department_approval_alerts_admin_and_the_department(): void
    {
        $request = $this->makeRequest($this->makeEquipment($this->ccs), [
            'overall_status' => 'Approved',
            'dept_status' => 'Approved',
            'dept_acc_id' => $this->ccsLab->dept_acc_id,
            'dept_reviewed_at' => now()->subMinutes(5),
        ]);
        $key = 'review-' . $request->request_id . '-dept-approved';

        $adminFeed = $this->adminFeed();
        $this->assertCount(1, $adminFeed['notifications']);
        $this->assertSame($key, $adminFeed['notifications'][0]['key']);
        $this->assertSame('approved', $adminFeed['notifications'][0]['type']);
        $this->assertStringContainsString('Ana Santos (Lab Personnel) approved', $adminFeed['notifications'][0]['message']);

        $deptFeed = $this->deptFeed($this->ccsLab);
        $this->assertCount(1, $deptFeed['notifications']);
        $this->assertStringContainsString('You approved', $deptFeed['notifications'][0]['message']);
    }

    public function test_each_review_decision_is_its_own_notification(): void
    {
        $this->makeRequest($this->makeEquipment($this->ccs), [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'admin_id' => $this->admin->admin_id,
            'admin_reviewed_at' => now()->subMinutes(20),
            'dept_status' => 'Approved',
            'dept_acc_id' => $this->ccsLab->dept_acc_id,
            'dept_reviewed_at' => now()->subMinutes(10),
        ]);

        $feed = $this->adminFeed();

        $this->assertCount(2, $feed['notifications']);
        // Newest first: the department decision came later.
        $this->assertStringContainsString('-dept-approved', $feed['notifications'][0]['key']);
        $this->assertStringContainsString('-admin-approved', $feed['notifications'][1]['key']);
    }

    public function test_a_department_only_sees_its_own_equipment_requests(): void
    {
        $this->makeRequest($this->makeEquipment($this->ccs, 'CCS Laptop'), [
            'overall_status' => 'Rejected',
            'admin_status' => 'Rejected',
            'admin_id' => $this->admin->admin_id,
            'admin_reviewed_at' => now()->subMinutes(5),
        ]);

        $this->assertCount(1, $this->deptFeed($this->ccsLab)['notifications']);

        $other = $this->deptFeed($this->cbaLab);
        $this->assertSame([], $other['notifications']);
        $this->assertSame(0, $other['unreadCount']);

        // The admin sees every department.
        $this->assertCount(1, $this->adminFeed()['notifications']);
    }

    public function test_old_decisions_are_not_listed(): void
    {
        $this->makeRequest($this->makeEquipment($this->ccs), [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'admin_id' => $this->admin->admin_id,
            'admin_reviewed_at' => now()->subDays(45),
        ]);

        $this->assertSame([], $this->adminFeed()['notifications']);
    }

    public function test_marking_read_never_duplicates_and_is_per_account(): void
    {
        $request = $this->makeRequest($this->makeEquipment($this->ccs), [
            'overall_status' => 'Rejected',
            'admin_status' => 'Rejected',
            'admin_id' => $this->admin->admin_id,
            'admin_reviewed_at' => now()->subMinutes(5),
        ]);
        $key = 'review-' . $request->request_id . '-admin-rejected';

        // Polling repeatedly never produces more notifications.
        $this->assertCount(1, $this->adminFeed()['notifications']);
        $this->assertCount(1, $this->adminFeed()['notifications']);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.notifications.read'), ['keys' => [$key]])
            ->assertOk()
            ->assertJson(['success' => true, 'unreadCount' => 0]);

        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.notifications.read'), ['keys' => [$key]])
            ->assertOk();

        $this->assertSame(1, StaffNotificationRead::where('account_type', 'admin')->count());

        $adminFeed = $this->adminFeed();
        $this->assertTrue($adminFeed['notifications'][0]['read']);
        $this->assertSame(0, $adminFeed['unreadCount']);

        // The admin's read mark does not affect the department's bell.
        $this->assertSame(1, $this->deptFeed($this->ccsLab)['unreadCount']);
    }

    public function test_mark_all_read_and_cross_department_keys_are_ignored(): void
    {
        $request = $this->makeRequest($this->makeEquipment($this->ccs), [
            'overall_status' => 'Rejected',
            'admin_status' => 'Rejected',
            'admin_id' => $this->admin->admin_id,
            'admin_reviewed_at' => now()->subMinutes(5),
        ]);
        $key = 'review-' . $request->request_id . '-admin-rejected';

        // A different department cannot mark a request it cannot see.
        $this->actingAs($this->cbaLab, 'dept')
            ->postJson(route('department.notifications.read'), ['keys' => [$key]])
            ->assertOk();
        $this->assertSame(0, StaffNotificationRead::count());

        $this->actingAs($this->ccsLab, 'dept')
            ->postJson(route('department.notifications.read'), ['all' => true])
            ->assertOk()
            ->assertJson(['unreadCount' => 0]);

        $this->assertSame(0, $this->deptFeed($this->ccsLab)['unreadCount']);
    }

    public function test_layouts_render_the_notification_bell(): void
    {
        $this->actingAs($this->admin, 'admin')->get(route('admin.dashboard'))
            ->assertOk()->assertSee('notifWrapper', false);

        $this->actingAs($this->ccsLab, 'dept')->get(route('department.dashboard'))
            ->assertOk()->assertSee('notifWrapper', false);
    }
}
