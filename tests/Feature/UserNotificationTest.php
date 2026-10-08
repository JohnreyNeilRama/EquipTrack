<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Student;
use App\Models\UserAccount;
use App\Models\UserNotificationRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * User notification bell: request status (approved / rejected) and overdue
 * reminders are derived from the user's own borrowing records, are never
 * duplicated, and are never visible to other users.
 */
class UserNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private EquipmentCategory $category;

    private UserAccount $user;

    private UserAccount $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'department_name' => 'College of Computer Studies',
            'department_code' => 'CCS',
        ]);

        $this->category = EquipmentCategory::create(['category_name' => 'Laptop']);

        $this->user = $this->makeStudent('student@example.com', '2020-0001', 'Juan');
        $this->otherUser = $this->makeStudent('other@example.com', '2020-0002', 'Maria');
    }

    private function makeStudent(string $email, string $idNumber, string $firstName): UserAccount
    {
        $account = UserAccount::create([
            'role' => 'Student',
            'email' => $email,
            'password' => Hash::make('password'),
            'department_id' => $this->department->department_id,
            'status' => 'Active',
        ]);

        Student::create([
            'user_id' => $account->user_id,
            'first_name' => $firstName,
            'last_name' => 'Dela Cruz',
            'id_number' => $idNumber,
            'year_level' => '3',
            'address' => 'Cebu City',
        ]);

        return $account;
    }

    private function makeEquipment(string $name = 'Dell Latitude'): Equipment
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
            'department_id' => $this->department->department_id,
        ]);
    }

    private function makeRequest(UserAccount $owner, Equipment $equipment, array $overrides = []): BorrowRequest
    {
        return BorrowRequest::create(array_merge([
            'user_id' => $owner->user_id,
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

    private function makeLoan(BorrowRequest $request, string $dueDate, string $status = 'Active'): BorrowTransaction
    {
        return BorrowTransaction::create([
            'request_id' => $request->request_id,
            'borrow_date' => now()->subDays(10)->toDateString(),
            'due_date' => $dueDate,
            'status' => $status,
        ]);
    }

    private function feed(UserAccount $as): array
    {
        $response = $this->actingAs($as, 'user')->getJson(route('user.notifications'));

        $response->assertOk()->assertJson(['success' => true]);

        return $response->json();
    }

    public function test_guests_cannot_read_notifications(): void
    {
        $this->getJson(route('user.notifications'))->assertUnauthorized();
    }

    public function test_pending_requests_do_not_notify(): void
    {
        $this->makeRequest($this->user, $this->makeEquipment());

        $feed = $this->feed($this->user);

        $this->assertSame([], $feed['notifications']);
        $this->assertSame(0, $feed['unreadCount']);
    }

    public function test_approved_and_rejected_requests_notify_the_owner(): void
    {
        $laptop = $this->makeEquipment('Dell Latitude');
        $camera = $this->makeEquipment('Camera Canon');

        $approved = $this->makeRequest($this->user, $laptop, [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'admin_reviewed_at' => now()->subHour(),
        ]);
        $rejected = $this->makeRequest($this->user, $camera, [
            'overall_status' => 'Rejected',
            'admin_status' => 'Rejected',
            'admin_reviewed_at' => now()->subMinutes(5),
            'reject_reason' => 'Equipment is reserved for a class.',
        ]);

        $feed = $this->feed($this->user);
        $byKey = collect($feed['notifications'])->keyBy('key');

        $this->assertCount(2, $feed['notifications']);
        $this->assertSame(2, $feed['unreadCount']);

        $approvedItem = $byKey->get('request-' . $approved->request_id . '-approved');
        $this->assertSame('approved', $approvedItem['type']);
        $this->assertStringContainsString('Dell Latitude', $approvedItem['message']);

        $rejectedItem = $byKey->get('request-' . $rejected->request_id . '-rejected');
        $this->assertSame('rejected', $rejectedItem['type']);
        $this->assertStringContainsString('Camera Canon', $rejectedItem['message']);
        $this->assertStringContainsString('Equipment is reserved for a class.', $rejectedItem['message']);

        // Newest first.
        $this->assertSame($rejectedItem['key'], $feed['notifications'][0]['key']);
    }

    public function test_department_review_time_is_used_when_it_acted_last(): void
    {
        $request = $this->makeRequest($this->user, $this->makeEquipment(), [
            'overall_status' => 'Approved',
            'dept_status' => 'Approved',
            'dept_reviewed_at' => now()->subMinutes(10),
        ]);

        $feed = $this->feed($this->user);

        $this->assertSame('request-' . $request->request_id . '-approved', $feed['notifications'][0]['key']);
    }

    public function test_old_status_notices_are_not_listed(): void
    {
        $this->makeRequest($this->user, $this->makeEquipment(), [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'admin_reviewed_at' => now()->subDays(45),
        ]);

        $this->assertSame([], $this->feed($this->user)['notifications']);
    }

    public function test_notifications_are_private_to_each_user(): void
    {
        $this->makeRequest($this->user, $this->makeEquipment(), [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'admin_reviewed_at' => now()->subHour(),
        ]);

        $this->assertCount(1, $this->feed($this->user)['notifications']);

        $other = $this->feed($this->otherUser);
        $this->assertSame([], $other['notifications']);
        $this->assertSame(0, $other['unreadCount']);
    }

    public function test_overdue_loan_notifies_until_it_is_returned(): void
    {
        $equipment = $this->makeEquipment('Projector Epson');
        $request = $this->makeRequest($this->user, $equipment, [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            // Approved long ago, so only the overdue notice remains.
            'admin_reviewed_at' => now()->subDays(40),
        ]);
        $loan = $this->makeLoan($request, now()->subDays(4)->toDateString());

        $feed = $this->feed($this->user);

        $this->assertCount(1, $feed['notifications']);
        $item = $feed['notifications'][0];
        $this->assertSame('overdue-' . $loan->transaction_id, $item['key']);
        $this->assertSame('overdue', $item['type']);
        $this->assertStringContainsString('Projector Epson', $item['message']);
        $this->assertStringContainsString('4 days late', $item['message']);

        // The other user never sees it.
        $this->assertSame([], $this->feed($this->otherUser)['notifications']);

        // Returning the item clears the reminder.
        $loan->update(['status' => 'Returned', 'return_date' => now()->toDateString()]);

        $this->assertSame([], $this->feed($this->user)['notifications']);
    }

    public function test_loans_within_their_due_date_do_not_notify_as_overdue(): void
    {
        $request = $this->makeRequest($this->user, $this->makeEquipment(), [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'admin_reviewed_at' => now()->subDays(40),
        ]);
        $this->makeLoan($request, now()->addDays(2)->toDateString());

        $this->assertSame([], $this->feed($this->user)['notifications']);
    }

    public function test_transactions_flagged_overdue_notify_even_without_a_past_due_date(): void
    {
        $request = $this->makeRequest($this->user, $this->makeEquipment(), [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'admin_reviewed_at' => now()->subDays(40),
        ]);
        $this->makeLoan($request, now()->addDay()->toDateString(), 'Overdue');

        $feed = $this->feed($this->user);

        $this->assertCount(1, $feed['notifications']);
        $this->assertSame('overdue', $feed['notifications'][0]['type']);
    }

    public function test_marking_read_clears_unread_and_never_duplicates(): void
    {
        $request = $this->makeRequest($this->user, $this->makeEquipment(), [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'admin_reviewed_at' => now()->subHour(),
        ]);
        $key = 'request-' . $request->request_id . '-approved';

        // Polling repeatedly never produces more notifications.
        $this->assertCount(1, $this->feed($this->user)['notifications']);
        $this->assertCount(1, $this->feed($this->user)['notifications']);

        $this->actingAs($this->user, 'user')
            ->postJson(route('user.notifications.read'), ['keys' => [$key]])
            ->assertOk()
            ->assertJson(['success' => true, 'unreadCount' => 0]);

        // Marking it again is harmless: still a single stored read mark.
        $this->actingAs($this->user, 'user')
            ->postJson(route('user.notifications.read'), ['keys' => [$key]])
            ->assertOk();

        $this->assertSame(1, UserNotificationRead::where('user_id', $this->user->user_id)->count());

        $feed = $this->feed($this->user);
        $this->assertCount(1, $feed['notifications']);
        $this->assertTrue($feed['notifications'][0]['read']);
        $this->assertSame(0, $feed['unreadCount']);
    }

    public function test_mark_all_read_marks_every_notification(): void
    {
        $this->makeRequest($this->user, $this->makeEquipment('Dell Latitude'), [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'admin_reviewed_at' => now()->subHour(),
        ]);
        $this->makeRequest($this->user, $this->makeEquipment('Camera Canon'), [
            'overall_status' => 'Rejected',
            'admin_status' => 'Rejected',
            'admin_reviewed_at' => now()->subMinutes(30),
            'reject_reason' => 'Not available.',
        ]);

        $this->assertSame(2, $this->feed($this->user)['unreadCount']);

        $this->actingAs($this->user, 'user')
            ->postJson(route('user.notifications.read'), ['all' => true])
            ->assertOk()
            ->assertJson(['unreadCount' => 0]);

        $this->assertSame(0, $this->feed($this->user)['unreadCount']);
    }

    public function test_a_user_cannot_mark_another_users_notification_read(): void
    {
        $request = $this->makeRequest($this->user, $this->makeEquipment(), [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'admin_reviewed_at' => now()->subHour(),
        ]);
        $key = 'request-' . $request->request_id . '-approved';

        $this->actingAs($this->otherUser, 'user')
            ->postJson(route('user.notifications.read'), ['keys' => [$key]])
            ->assertOk();

        $this->assertSame(0, UserNotificationRead::count());
        $this->assertSame(1, $this->feed($this->user)['unreadCount']);
    }

    public function test_dashboard_page_renders_the_notification_bell(): void
    {
        $response = $this->actingAs($this->user, 'user')->get(route('user.dashboard'));

        $response->assertOk();
        $response->assertSee('notifWrapper', false);
        $response->assertSee('notifBadge', false);
    }
}
