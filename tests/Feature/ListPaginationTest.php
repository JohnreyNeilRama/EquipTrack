<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\UserAccount;
use App\Services\RequestTableRows;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Phase 3: the Requests pages (Admin and Department) and the Department
 * History page load 25 rows at a time; search and filters run on the server,
 * so they cover every record. Requests start on "Active" (finished ones hidden).
 */
class ListPaginationTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    private Department $ccs;

    private DepartmentAccount $lab;

    private Admin $admin;

    private UserAccount $student;

    private UserAccount $faculty;

    private Equipment $laptop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ccs = $this->makeDepartment();
        $this->lab = $this->makeLabPersonnel($this->ccs);
        $this->admin = $this->makeAdmin();
        $this->student = $this->makeStudent($this->ccs, 'juan@example.com', ['first_name' => 'Juan', 'last_name' => 'Dela Cruz']);
        $this->faculty = $this->makeFaculty($this->ccs, 'ramon@example.com', ['first_name' => 'Ramon', 'last_name' => 'Reyes']);
        $this->laptop = $this->makeEquipment($this->ccs, ['name' => 'Dell Latitude', 'total_qty' => 50, 'available_qty' => 50]);
    }

    private function deptPage(array $query = [])
    {
        return $this->actingAs($this->lab, 'dept')->getJson(route('department.requests.data', ['paged' => 1] + $query))->assertOk();
    }

    // ------------------------------------------------------------------
    // Requests
    // ------------------------------------------------------------------

    public function test_requests_come_25_to_a_page_newest_first(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->makePendingRequest($this->student, $this->laptop);
        }

        $first = $this->deptPage();
        $this->assertCount(25, $first->json('requests'));
        $this->assertSame(['page' => 1, 'perPage' => 25, 'total' => 30, 'lastPage' => 2], $first->json('pagination'));
        $this->assertSame(30, $first->json('counts.Pending'));

        $ids = array_column($first->json('requests'), 'id');
        $this->assertSame($ids, collect($ids)->sortDesc()->values()->all(), 'Newest first.');

        $second = $this->deptPage(['page' => 2]);
        $this->assertCount(5, $second->json('requests'));
        $this->assertEmpty(array_intersect($ids, array_column($second->json('requests'), 'id')));

        // A page past the end shows the last page.
        $this->assertSame(2, $this->deptPage(['page' => 99])->json('pagination.page'));
    }

    public function test_finished_requests_are_hidden_until_all_status_is_chosen(): void
    {
        $pending = $this->makePendingRequest($this->student, $this->laptop);
        $rejected = $this->makePendingRequest($this->student, $this->laptop, ['overall_status' => 'Rejected', 'dept_status' => 'Rejected']);
        $expired = $this->makePendingRequest($this->student, $this->laptop, ['overall_status' => 'Expired']);
        $onLoan = $this->makeLoan($this->student, $this->laptop, now()->addDays(2)->toDateString())->request_id;
        $returned = $this->makeLoan($this->student, $this->laptop, '2026-10-22', ['status' => BorrowTransaction::STATUS_RETURNED])->request_id;

        $active = array_column($this->deptPage()->json('requests'), 'id');
        $this->assertEqualsCanonicalizing([$pending->request_id, $onLoan], $active);

        $all = array_column($this->deptPage(['status' => 'all'])->json('requests'), 'id');
        $this->assertEqualsCanonicalizing([$pending->request_id, $rejected->request_id, $expired->request_id, $onLoan, $returned], $all);

        $this->assertSame([$rejected->request_id], array_column($this->deptPage(['status' => 'rejected'])->json('requests'), 'id'));

        // Summary counts cover every request, whatever the filter.
        $counts = $this->deptPage(['status' => 'rejected'])->json('counts');
        $this->assertSame(['Pending' => 1, 'Approved' => 2, 'Rejected' => 1, 'Cancelled' => 0, 'Expired' => 1], $counts);
    }

    public function test_search_and_role_cover_every_page(): void
    {
        // The oldest request: on page 2 without a search.
        $projector = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector']);
        $oldest = $this->makePendingRequest($this->faculty, $projector)->request_id;
        for ($i = 0; $i < 26; $i++) {
            $this->makePendingRequest($this->student, $this->laptop);
        }
        $this->assertNotContains($oldest, array_column($this->deptPage()->json('requests'), 'id'));

        $this->assertSame([$oldest], array_column($this->deptPage(['search' => 'epson'])->json('requests'), 'id'));
        $this->assertSame([$oldest], array_column($this->deptPage(['search' => 'Ramon Reyes'])->json('requests'), 'id'));
        $this->assertSame([$oldest], array_column($this->deptPage(['search' => 'reyes epson'])->json('requests'), 'id'));
        $this->assertContains($oldest, array_column($this->deptPage(['search' => '#' . $oldest])->json('requests'), 'id'));
        $this->assertSame([$oldest], array_column($this->deptPage(['role' => 'faculty'])->json('requests'), 'id'));
        $this->assertSame(26, $this->deptPage(['role' => 'student'])->json('pagination.total'));
        $this->assertSame(0, $this->deptPage(['search' => 'nothing-like-this'])->json('pagination.total'));
    }

    public function test_the_admin_requests_page_is_paged_the_same_way(): void
    {
        $expensive = $this->makeEquipment($this->ccs, ['name' => 'Canon Camera', 'classification' => Equipment::CLASS_EXPENSIVE]);
        for ($i = 0; $i < 27; $i++) {
            $this->makePendingRequest($this->student, $expensive);
        }
        // Not Expensive: the department's alone, never on the admin's list.
        $this->makePendingRequest($this->student, $this->laptop);

        $response = $this->actingAs($this->admin, 'admin')->getJson(route('admin.requests.data', ['paged' => 1]))->assertOk();
        $this->assertCount(25, $response->json('requests'));
        $this->assertSame(27, $response->json('pagination.total'));

        $this->get(route('admin.requests'))->assertOk()->assertViewHas('requestPage', fn ($p) => $p['pagination']['total'] === 27 && count($p['requests']) === 25);
    }

    public function test_the_dashboard_feed_can_ask_for_the_newest_few_only(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->makePendingRequest($this->student, $this->laptop);
        }

        $rows = $this->actingAs($this->lab, 'dept')->getJson(route('department.requests.data', ['limit' => 5]))->json('requests');
        $this->assertCount(5, $rows);

        // Without parameters the feed still lists everything (as before).
        $this->assertCount(8, $this->getJson(route('department.requests.data'))->json('requests'));
    }

    public function test_the_page_size_is_25(): void
    {
        $this->assertSame(25, RequestTableRows::PER_PAGE);
    }

    // ------------------------------------------------------------------
    // Users (Department and Admin)
    // ------------------------------------------------------------------

    public function test_department_users_come_25_to_a_page_and_hide_deactivated_accounts(): void
    {
        for ($i = 1; $i <= 27; $i++) {
            $this->makeStudent($this->ccs, "s{$i}@example.com", ['first_name' => 'Student' . $i, 'id_number' => sprintf('2026%04d', $i)]);
        }
        $gone = $this->makeStudent($this->ccs, 'gone@example.com', ['first_name' => 'Gone', 'status' => 'Deactivated']);

        $feed = fn (array $q = []) => $this->actingAs($this->lab, 'dept')->getJson(route('department.users.data', $q))->assertOk();

        // 27 students + the setUp student and faculty member in use.
        $first = $feed();
        $this->assertCount(25, $first->json('users'));
        $this->assertSame(29, $first->json('pagination.total'));
        $this->assertNotContains($gone->user_id, array_column($first->json('users'), 'id'));
        $this->assertSame(['total' => 30, 'students' => 29, 'faculty' => 1, 'active' => 29], $first->json('counts'));

        $this->assertCount(4, $feed(['page' => 2])->json('users'));
        $this->assertSame(30, $feed(['status' => 'all'])->json('pagination.total'));
        $this->assertSame([$gone->user_id], array_column($feed(['status' => 'inactive'])->json('users'), 'id'));
        $this->assertSame([$this->faculty->user_id], array_column($feed(['role' => 'faculty'])->json('users'), 'id'));
        $this->assertSame([$this->student->user_id], array_column($feed(['search' => 'dela juan'])->json('users'), 'id'));
    }

    public function test_admin_users_mix_every_account_type_sorted_by_name_25_to_a_page(): void
    {
        for ($i = 1; $i <= 26; $i++) {
            $this->makeStudent($this->ccs, "s{$i}@example.com", ['first_name' => 'Zed' . $i, 'id_number' => sprintf('2026%04d', $i)]);
        }
        $this->makeStudent($this->ccs, 'gone@example.com', ['first_name' => 'Aaron', 'status' => 'Deactivated']);

        $feed = fn (array $q = []) => $this->actingAs($this->admin, 'admin')->getJson(route('admin.users.data', $q))->assertOk();

        // 26 + Juan + Ramon (students/faculty), 1 Lab Personnel, 1 Admin in use.
        $first = $feed();
        $this->assertCount(25, $first->json('users'));
        $this->assertSame(30, $first->json('pagination.total'));
        $this->assertSame(['total' => 31, 'students' => 28, 'faculty' => 1, 'departments' => 1, 'active' => 30, 'deactivated' => 1], $first->json('counts'));

        // Alphabetical across account types; the deactivated "Aaron" is hidden.
        $names = array_map(fn ($u) => trim($u['first_name'] . ' ' . $u['last_name']), $first->json('users'));
        $this->assertSame($names, collect($names)->sortBy(fn ($n) => mb_strtolower($n), SORT_STRING)->values()->all());
        $this->assertNotContains('Aaron Dela Cruz', $names);

        $this->assertCount(5, $feed(['page' => 2])->json('users'));
        $this->assertSame('Aaron', $feed(['status' => 'inactive'])->json('users.0.first_name'));
        $this->assertSame(['department'], array_column($feed(['role' => 'department'])->json('users'), 'db_type'));
        $this->assertSame(['admin'], array_column($feed(['role' => 'administrator'])->json('users'), 'db_type'));
        $this->assertSame(['ramon@example.com'], array_column($feed(['search' => 'ramon@'])->json('users'), 'email'));

        // The page itself starts on the first page of accounts in use.
        $this->get(route('admin.users'))->assertOk()
            ->assertViewHas('usersPage', fn ($p) => $p['pagination']['total'] === 30 && count($p['users']) === 25);
    }

    // ------------------------------------------------------------------
    // Monitoring and Equipment
    // ------------------------------------------------------------------

    public function test_department_monitoring_opens_on_items_currently_out_25_to_a_page(): void
    {
        // 27 items nobody has, plus one on loan and one overdue.
        for ($i = 1; $i <= 27; $i++) {
            $this->makeEquipment($this->ccs, ['name' => "Spare Item {$i}"]);
        }
        $onLoan = $this->makeLoan($this->student, $this->laptop, now()->addDays(3)->toDateString());
        $projector = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector']);
        $overdue = $this->makeLoan($this->student, $projector, now()->subDays(3)->toDateString());

        $feed = fn (array $q = []) => $this->actingAs($this->lab, 'dept')->getJson(route('department.monitoring.data', $q))->assertOk();

        // Default: only the two loans, the overdue one first.
        $out = $feed();
        $this->assertSame(['Overdue', 'Borrowed'], array_column($out->json('rows'), 'status'));
        $this->assertSame(2, $out->json('pagination.total'));
        $this->assertSame(['total' => 29, 'available' => 27, 'borrowed' => 2, 'reserved' => 0], $out->json('counts'));

        // All Statuses: everything, 25 to a page.
        $all = $feed(['status' => 'all']);
        $this->assertCount(25, $all->json('rows'));
        $this->assertSame(29, $all->json('pagination.total'));
        $this->assertCount(4, $feed(['status' => 'all', 'page' => 2])->json('rows'));

        $this->assertSame(['Epson Projector'], array_column($feed(['search' => 'epson'])->json('rows'), 'equipment'));
        $this->assertSame(27, $feed(['status' => 'available'])->json('pagination.total'));

        // The page itself starts the same way.
        $this->get(route('department.monitoring'))->assertOk()
            ->assertSee('Currently Out')
            ->assertViewHas('monitoringPage', fn ($p) => $p['pagination']['total'] === 2);
    }

    public function test_admin_monitoring_and_both_equipment_pages_hide_finished_items_by_default(): void
    {
        $this->actingAs($this->admin, 'admin')->get(route('admin.monitoring'))->assertOk()
            ->assertSee('<option value="out" selected>Currently Out</option>', false);

        $this->get(route('admin.equipment'))->assertOk()
            ->assertSee('<option value="inservice" selected>In Service</option>', false);

        $this->actingAs($this->lab, 'dept')->get(route('department.equipment'))->assertOk()
            ->assertSee('<option value="inservice" selected>In Service</option>', false);
    }

    // ------------------------------------------------------------------
    // Department History
    // ------------------------------------------------------------------

    private function historyPage(array $query = [])
    {
        return $this->actingAs($this->lab, 'dept')->getJson(route('department.history.data', $query))->assertOk();
    }

    public function test_history_comes_25_to_a_page_with_counts_for_every_record(): void
    {
        for ($i = 0; $i < 27; $i++) {
            $this->makeLoan($this->student, $this->laptop, '2026-10-22', [
                'status' => BorrowTransaction::STATUS_RETURNED,
                'return_date' => '2026-10-21',
                'condition_on_return' => $i < 2 ? 'Damaged — cracked screen' : 'Good',
            ]);
        }
        // Still out: not history.
        $this->makeLoan($this->student, $this->laptop, now()->addDays(2)->toDateString());

        $first = $this->historyPage();
        $this->assertCount(25, $first->json('history'));
        $this->assertSame(27, $first->json('pagination.total'));
        $this->assertSame(['total' => 27, 'returned' => 25, 'late' => 0, 'damaged' => 2], $first->json('counts'));

        $this->assertCount(2, $this->historyPage(['page' => 2])->json('history'));

        // Status filter covers every record, not only the page shown.
        $damaged = $this->historyPage(['status' => 'damaged']);
        $this->assertSame(2, $damaged->json('pagination.total'));
        $this->assertSame(['Damaged', 'Damaged'], array_column($damaged->json('history'), 'status'));
    }

    public function test_history_search_role_and_category_filters(): void
    {
        $projector = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector']);
        $studentLoan = $this->makeLoan($this->student, $this->laptop, '2026-10-22', ['status' => BorrowTransaction::STATUS_RETURNED, 'return_date' => '2026-10-21']);
        $facultyLoan = $this->makeLoan($this->faculty, $projector, '2026-10-22', ['status' => BorrowTransaction::STATUS_RETURNED, 'return_date' => '2026-10-21']);

        $txn = fn ($t) => 'TXN-' . str_pad((string) $t->transaction_id, 4, '0', STR_PAD_LEFT);

        $this->assertSame([$txn($facultyLoan)], array_column($this->historyPage(['search' => 'epson'])->json('history'), 'txnid'));
        $this->assertSame([$txn($studentLoan)], array_column($this->historyPage(['search' => 'juan dela'])->json('history'), 'txnid'));
        $this->assertSame([$txn($studentLoan)], array_column($this->historyPage(['search' => $txn($studentLoan)])->json('history'), 'txnid'));
        $this->assertSame([$txn($facultyLoan)], array_column($this->historyPage(['role' => 'faculty'])->json('history'), 'txnid'));
        $this->assertSame(2, $this->historyPage(['category' => 'laptop'])->json('pagination.total'));
        $this->assertSame(0, $this->historyPage(['category' => 'cameras'])->json('pagination.total'));
    }
}
