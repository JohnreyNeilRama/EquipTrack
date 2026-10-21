<?php

namespace Tests\Feature;

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
 * Student / Faculty dashboard: stats, tables and reminders come from the
 * signed-in user's own borrowing records, follow the borrow lifecycle, show
 * clean empty states for users without records, and never leak another user's
 * data.
 */
class UserDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private EquipmentCategory $category;

    private UserAccount $user;

    private UserAccount $otherUser;

    private UserAccount $emptyUser;

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
        $this->emptyUser = $this->makeStudent('empty@example.com', '2020-0003', 'Pedro');
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

    /** An approved request together with its Active borrow_transaction. */
    private function makeLoan(UserAccount $owner, string $equipmentName, string $dueDate, string $status = 'Active'): BorrowTransaction
    {
        $request = $this->makeRequest($owner, $this->makeEquipment($equipmentName), [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'due_date' => $dueDate,
            'return_date' => $dueDate,
        ]);

        return BorrowTransaction::create([
            'request_id' => $request->request_id,
            'borrow_date' => now()->subDays(10)->toDateString(),
            'due_date' => $dueDate,
            'status' => $status,
        ]);
    }

    private function feed(UserAccount $as): array
    {
        $response = $this->actingAs($as, 'user')->getJson(route('user.dashboard.data'));

        $response->assertOk()->assertJson(['success' => true]);

        return $response->json();
    }

    public function test_guests_cannot_open_the_dashboard_or_its_feed(): void
    {
        $this->get(route('user.dashboard'))->assertRedirect(route('login'));
        $this->getJson(route('user.dashboard.data'))->assertUnauthorized();
    }

    public function test_user_without_records_gets_zeros_and_empty_states(): void
    {
        $feed = $this->feed($this->emptyUser);

        $this->assertSame(
            ['activeBorrows' => 0, 'pendingRequests' => 0, 'overdueItems' => 0],
            $feed['stats']
        );
        $this->assertSame([], $feed['activeBorrows']);
        $this->assertSame([], $feed['recentRequests']);
        $this->assertSame([], $feed['reminders']);

        $this->actingAs($this->emptyUser, 'user')
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('Welcome back, Pedro!')
            ->assertSee('<span>No active borrow items.</span>', false)
            ->assertSee('<span>No recent requests found.</span>', false)
            ->assertSee('No active reminders at this time.');
    }

    public function test_stats_reflect_only_the_users_own_borrowing_records(): void
    {
        // Active, not yet due.
        $this->makeLoan($this->user, 'Dell Latitude', now()->addDays(10)->toDateString());
        // Active and overdue.
        $this->makeLoan($this->user, 'Camera Canon', now()->subDays(2)->toDateString());

        // Returned loans are settled: not active, not overdue.
        $returned = $this->makeLoan($this->user, 'Projector Epson', now()->subDays(5)->toDateString());
        $returned->update(['status' => 'Returned', 'return_date' => now()->toDateString()]);

        // Legacy approved request without a transaction still counts as out
        // (same rule as the Return Item page).
        $this->makeRequest($this->user, $this->makeEquipment('Tripod'), [
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'due_date' => now()->addDays(5)->toDateString(),
        ]);

        // Pending x2 and one rejected request.
        $this->makeRequest($this->user, $this->makeEquipment('Microphone'));
        $this->makeRequest($this->user, $this->makeEquipment('Speaker'));
        $this->makeRequest($this->user, $this->makeEquipment('Router'), [
            'overall_status' => 'Rejected',
            'admin_status' => 'Rejected',
            'reject_reason' => 'Reserved for a class.',
        ]);

        // Another user's records must not change any number.
        $this->makeLoan($this->otherUser, 'Other Laptop', now()->subDays(9)->toDateString());
        $this->makeRequest($this->otherUser, $this->makeEquipment('Other Camera'));
        $this->makeRequest($this->otherUser, $this->makeEquipment('Other Mic'));
        $this->makeRequest($this->otherUser, $this->makeEquipment('Other Speaker'));

        $stats = $this->feed($this->user)['stats'];

        $this->assertSame(3, $stats['activeBorrows']);
        $this->assertSame(2, $stats['pendingRequests']);
        $this->assertSame(1, $stats['overdueItems']);
    }

    public function test_quantity_counts_units_in_the_active_and_overdue_cards(): void
    {
        $loan = $this->makeLoan($this->user, 'Dell Latitude', now()->subDay()->toDateString());
        $loan->request->update(['quantity' => 2]);

        $stats = $this->feed($this->user)['stats'];

        $this->assertSame(2, $stats['activeBorrows']);
        $this->assertSame(2, $stats['overdueItems']);
    }

    public function test_transactions_flagged_overdue_count_as_overdue(): void
    {
        $this->makeLoan($this->user, 'Dell Latitude', now()->addDay()->toDateString(), 'Overdue');

        $feed = $this->feed($this->user);

        $this->assertSame(1, $feed['stats']['overdueItems']);
        $this->assertSame('Overdue', $feed['activeBorrows'][0]['status']);
    }

    public function test_active_borrow_rows_are_ordered_by_due_date_and_flag_overdue(): void
    {
        $this->makeLoan($this->user, 'Later Item', now()->addDays(8)->toDateString());
        $this->makeLoan($this->user, 'Late Item', now()->subDays(3)->toDateString());
        $this->makeLoan($this->user, 'Soon Item', now()->addDays(1)->toDateString());

        $rows = $this->feed($this->user)['activeBorrows'];

        $this->assertSame(['Late Item', 'Soon Item', 'Later Item'], array_column($rows, 'equipment'));
        $this->assertSame(['Overdue', 'Borrowed', 'Borrowed'], array_column($rows, 'status'));
        $this->assertSame(now()->subDays(3)->format('M d, Y'), $rows[0]['dueDate']);
    }

    public function test_recent_requests_are_newest_first_and_limited_to_five(): void
    {
        foreach (range(1, 7) as $n) {
            $this->makeRequest($this->user, $this->makeEquipment('Item ' . $n));
        }

        $rows = $this->feed($this->user)['recentRequests'];

        $this->assertCount(5, $rows);
        $this->assertSame(
            ['Item 7', 'Item 6', 'Item 5', 'Item 4', 'Item 3'],
            array_column($rows, 'equipment')
        );
        $this->assertSame('Pending', $rows[0]['status']);
    }

    public function test_tables_show_at_most_five_rows_with_the_equipment_photo_and_category(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            $this->makeLoan($this->user, 'Laptop ' . $i, now()->addDays($i)->toDateString());
        }

        $feed = $this->feed($this->user);

        $this->assertCount(5, $feed['activeBorrows']);
        $this->assertCount(5, $feed['recentRequests']);
        $this->assertSame(7, $feed['stats']['activeBorrows'], 'The stat still counts every loan.');

        $row = $feed['activeBorrows'][0];
        $this->assertSame('/storage/images/eq.jpg', $row['image']);
        $this->assertSame('Laptop', $row['category']);
        $this->assertSame('/storage/images/eq.jpg', $feed['recentRequests'][0]['image']);

        // Same equipment cell as the My Requests table, and no pagination.
        $this->actingAs($this->user, 'user')->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('class="table-container card requests-table-card"', false)
            ->assertSee('user/css/usertable.css', false)
            ->assertSee('<span class="eq-sub" style="font-size: 12px; color: var(--text-muted);">Laptop</span>', false)
            ->assertSee('<span class="panel-count" id="activeBorrowsCount">5</span>', false)
            ->assertDontSee('pagination', false);
    }

    public function test_reminders_cover_overdue_and_due_soon_loans_only(): void
    {
        $this->makeLoan($this->user, 'Far Away Item', now()->addDays(10)->toDateString());
        $this->makeLoan($this->user, 'Tomorrow Item', now()->addDay()->toDateString());
        $this->makeLoan($this->user, 'Late Item', now()->subDays(4)->toDateString());

        $reminders = $this->feed($this->user)['reminders'];

        $this->assertCount(2, $reminders);

        $this->assertSame('danger', $reminders[0]['type']);
        $this->assertSame('Overdue', $reminders[0]['label']);
        $this->assertSame('Late Item', $reminders[0]['equipment']);
        $this->assertStringContainsString('Late Item', $reminders[0]['title']);
        $this->assertStringContainsString('4 days late', $reminders[0]['message']);

        $this->assertSame('warning', $reminders[1]['type']);
        $this->assertSame('Due tomorrow: Tomorrow Item', $reminders[1]['title']);
    }

    public function test_dashboard_follows_the_borrow_lifecycle(): void
    {
        // 1. Submitted: shows as pending.
        $request = $this->makeRequest($this->user, $this->makeEquipment('Dell Latitude'));

        $feed = $this->feed($this->user);
        $this->assertSame(1, $feed['stats']['pendingRequests']);
        $this->assertSame(0, $feed['stats']['activeBorrows']);
        $this->assertSame('Pending', $feed['recentRequests'][0]['status']);

        // 2. Approved: leaves pending, becomes an active borrow.
        $request->update(['overall_status' => 'Approved', 'admin_status' => 'Approved']);
        $loan = BorrowTransaction::create([
            'request_id' => $request->request_id,
            'borrow_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'status' => 'Active',
        ]);

        $feed = $this->feed($this->user);
        $this->assertSame(0, $feed['stats']['pendingRequests']);
        $this->assertSame(1, $feed['stats']['activeBorrows']);
        $this->assertSame('Dell Latitude', $feed['activeBorrows'][0]['equipment']);
        $this->assertSame('Approved', $feed['recentRequests'][0]['status']);

        // 3. Returned: no longer active, the request stays in the recent list.
        $loan->update(['status' => 'Returned', 'return_date' => now()->toDateString()]);

        $feed = $this->feed($this->user);
        $this->assertSame(0, $feed['stats']['activeBorrows']);
        $this->assertSame([], $feed['activeBorrows']);
        $this->assertSame('Approved', $feed['recentRequests'][0]['status']);
    }

    public function test_rejected_requests_leave_pending_and_show_as_rejected(): void
    {
        $request = $this->makeRequest($this->user, $this->makeEquipment('Dell Latitude'));
        $this->assertSame(1, $this->feed($this->user)['stats']['pendingRequests']);

        $request->update([
            'overall_status' => 'Rejected',
            'admin_status' => 'Rejected',
            'reject_reason' => 'Reserved for a class.',
        ]);

        $feed = $this->feed($this->user);
        $this->assertSame(0, $feed['stats']['pendingRequests']);
        $this->assertSame('Rejected', $feed['recentRequests'][0]['status']);
    }

    public function test_one_user_never_sees_another_users_records(): void
    {
        $this->makeLoan($this->user, 'Secret Laptop', now()->subDays(2)->toDateString());
        $this->makeRequest($this->user, $this->makeEquipment('Secret Camera'));

        $feed = $this->feed($this->otherUser);
        $this->assertSame(0, $feed['stats']['activeBorrows']);
        $this->assertSame(0, $feed['stats']['pendingRequests']);
        $this->assertSame(0, $feed['stats']['overdueItems']);
        $this->assertSame([], $feed['activeBorrows']);
        $this->assertSame([], $feed['recentRequests']);
        $this->assertSame([], $feed['reminders']);

        $this->assertStringNotContainsString('Secret', json_encode($feed));

        $this->actingAs($this->otherUser, 'user')
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertDontSee('Secret Laptop')
            ->assertDontSee('Secret Camera');
    }

    public function test_the_feed_ignores_a_user_id_sent_in_the_request(): void
    {
        $this->makeRequest($this->user, $this->makeEquipment('Secret Camera'));

        $response = $this->actingAs($this->otherUser, 'user')
            ->getJson(route('user.dashboard.data', ['user_id' => $this->user->user_id]));

        $response->assertOk();
        $this->assertSame([], $response->json('recentRequests'));
        $this->assertSame(0, $response->json('stats.pendingRequests'));
    }

    public function test_page_renders_the_users_real_data_and_working_links(): void
    {
        $this->makeLoan($this->user, 'Camera Canon', now()->subDays(2)->toDateString());
        $this->makeRequest($this->user, $this->makeEquipment('Microphone'));

        $this->actingAs($this->user, 'user')
            ->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('Welcome back, Juan!')
            ->assertSee('Camera Canon')
            ->assertSee('Microphone')
            ->assertSee('detail-status-badge status-overdue', false)
            ->assertSee('detail-status-badge status-pending', false)
            ->assertDontSee('<span>No active borrow items.</span>', false)
            ->assertDontSee('<span>No recent requests found.</span>', false)
            ->assertSee('href="' . route('user.returns') . '"', false)
            ->assertSee('href="' . route('user.requests') . '"', false)
            ->assertSee('href="' . route('user.equipment') . '"', false);
    }
}
