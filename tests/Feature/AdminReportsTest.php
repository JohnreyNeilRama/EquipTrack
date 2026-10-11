<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
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
 * Admin Reports: every figure, chart and row comes from the live database and
 * follows the same loan rules as the Dashboard / Monitoring / History pages.
 */
class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private Admin $admin;

    private UserAccount $user;

    private EquipmentCategory $laptops;

    private EquipmentCategory $cameras;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'department_name' => 'College of Computer Studies',
            'department_code' => 'CCS',
        ]);

        $this->laptops = EquipmentCategory::create(['category_name' => 'Laptop']);
        $this->cameras = EquipmentCategory::create(['category_name' => 'Camera']);

        $this->admin = Admin::create([
            'name' => 'System Admin',
            'username' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $this->user = $this->makeUser('student@example.com', 'Juan', 'Dela Cruz', '2020-0001');
    }

    private function makeUser(string $email, string $first, string $last, string $idNumber): UserAccount
    {
        $user = UserAccount::create([
            'role' => 'Student',
            'email' => $email,
            'password' => Hash::make('password'),
            'department_id' => $this->department->department_id,
            'status' => 'Active',
        ]);

        Student::create([
            'user_id' => $user->user_id,
            'first_name' => $first,
            'last_name' => $last,
            'id_number' => $idNumber,
            'year_level' => '3',
            'address' => 'Cebu City',
        ]);

        return $user;
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
            'category_id' => $this->laptops->category_id,
            'department_id' => $this->department->department_id,
        ], $overrides));
    }

    private function approved(array $overrides = []): array
    {
        return array_merge([
            'overall_status' => 'Approved',
            'admin_status' => 'Approved',
            'dept_status' => 'Approved',
        ], $overrides);
    }

    private function makeRequest(Equipment $equipment, array $overrides = [], ?UserAccount $user = null): BorrowRequest
    {
        $user ??= $this->user;

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
            'admin_status' => 'Pending',
            'dept_status' => 'Pending',
        ], $overrides));

        return $request->refresh();
    }

    /** Creates the borrow_transaction the approval workflow would have created. */
    private function loan(BorrowRequest $request, array $overrides = []): BorrowTransaction
    {
        return BorrowTransaction::create(array_merge([
            'request_id' => $request->request_id,
            'borrow_date' => $request->borrow_date->toDateString(),
            'due_date' => $request->due_date->toDateString(),
            'status' => 'Active',
        ], $overrides));
    }

    private function report(array $query = []): array
    {
        $response = $this->actingAs($this->admin, 'admin')->getJson(route('admin.reports.data', $query));

        $response->assertOk()->assertJson(['success' => true]);

        return $response->json();
    }

    private function statuses(array $payload): array
    {
        return collect($payload['report']['rows'])->pluck('status')->sort()->values()->all();
    }

    public function test_reports_page_renders_for_the_admin(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee('Generate Report Filters')
            ->assertSee('Laptop')
            ->assertSee('Camera');
    }

    public function test_empty_database_gives_zero_figures_and_no_rows(): void
    {
        $payload = $this->report();

        $this->assertSame(0, $payload['report']['total']);
        $this->assertSame([], $payload['report']['rows']);

        foreach (['borrowings', 'returns', 'active', 'overdue', 'requests', 'pending', 'rejected', 'availableUnits', 'totalUnits', 'reportsThisMonth'] as $key) {
            $this->assertSame(0, $payload['summary'][$key], $key);
        }

        $this->assertSame(array_fill(0, 6, 0), $payload['charts']['monthly']['values']);
        $this->assertSame([], $payload['charts']['category']['labels']);
        $this->assertNull($payload['charts']['mostBorrowed']);
        $this->assertSame([], $payload['recent']);
    }

    public function test_borrowing_report_reflects_every_request_status_and_matches_the_summary(): void
    {
        $laptop = $this->makeEquipment(['available_qty' => 3, 'total_qty' => 5]);

        $this->makeRequest($laptop); // pending
        $this->makeRequest($laptop, ['overall_status' => 'Rejected', 'admin_status' => 'Rejected']);

        $borrowed = $this->makeRequest($laptop, $this->approved());
        $this->loan($borrowed);

        $overdue = $this->makeRequest($laptop, $this->approved([
            'borrow_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(4)->toDateString(),
        ]));
        $this->loan($overdue);

        $returned = $this->makeRequest($laptop, $this->approved());
        $this->loan($returned, ['status' => 'Returned', 'return_date' => now()->toDateString(), 'condition_on_return' => 'Good']);

        $payload = $this->report();

        $this->assertSame(['Borrowed', 'Overdue', 'Pending', 'Rejected', 'Returned'], $this->statuses($payload));
        $this->assertSame(5, $payload['report']['total']);

        $summary = $payload['summary'];
        $this->assertSame(5, $summary['requests']);
        $this->assertSame(1, $summary['pending']);
        $this->assertSame(1, $summary['rejected']);
        $this->assertSame(3, $summary['borrowings']);
        $this->assertSame(1, $summary['returns']);
        $this->assertSame(2, $summary['active']);
        $this->assertSame(1, $summary['overdue']);
        // Requests = pending + rejected + approved (borrowings).
        $this->assertSame($summary['requests'], $summary['pending'] + $summary['rejected'] + $summary['borrowings']);
        // Approved loans = still out on loan + returned.
        $this->assertSame($summary['borrowings'], $summary['active'] + $summary['returns']);

        // Status filters.
        $this->assertSame(['Overdue'], $this->statuses($this->report(['status' => 'Overdue'])));
        $this->assertSame(['Borrowed'], $this->statuses($this->report(['status' => 'Borrowed'])));
        $this->assertSame(['Pending'], $this->statuses($this->report(['status' => 'Pending'])));
        $this->assertSame(['Rejected'], $this->statuses($this->report(['status' => 'Rejected'])));
        $this->assertSame(['Returned'], $this->statuses($this->report(['status' => 'Returned'])));
        // "Approved" is every approved request, whatever stage the loan is at.
        $this->assertSame(['Borrowed', 'Overdue', 'Returned'], $this->statuses($this->report(['status' => 'Approved'])));

        $row = collect($payload['report']['rows'])->firstWhere('status', 'Overdue');
        $this->assertSame('Juan Dela Cruz', $row['borrower']);
        $this->assertSame('2020-0001', $row['id_number']);
        $this->assertSame('Dell Latitude', $row['equipment']);
        $this->assertSame('Laptop', $row['category']);
        $this->assertSame(now()->subDays(4)->format('M d, Y'), $row['due_date']);
        $this->assertSame('—', $row['return_date']);
    }

    public function test_quantities_are_reported_in_units_for_active_and_overdue_loans(): void
    {
        $laptop = $this->makeEquipment(['available_qty' => 0, 'total_qty' => 3, 'status' => 'Unavailable']);

        $request = $this->makeRequest($laptop, $this->approved([
            'quantity' => 3,
            'borrow_date' => now()->subDays(8)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
        ]));
        $this->loan($request);

        $summary = $this->report()['summary'];

        // One loan record covering three units (the Dashboard counts the same 3 units).
        $this->assertSame(1, $summary['active']);
        $this->assertSame(3, $summary['activeUnits']);
        $this->assertSame(1, $summary['overdue']);
        $this->assertSame(3, $summary['overdueUnits']);
    }

    public function test_date_range_filters_by_borrow_date_and_rejects_an_invalid_range(): void
    {
        $laptop = $this->makeEquipment();

        $this->makeRequest($laptop); // borrow date: today
        $this->makeRequest($laptop, ['borrow_date' => now()->subDays(40)->toDateString(), 'date_needed' => now()->subDays(40)->toDateString(), 'due_date' => now()->subDays(37)->toDateString()]);

        $this->assertSame(2, $this->report()['report']['total']);

        $recent = $this->report(['from' => now()->subDays(5)->toDateString()]);
        $this->assertSame(1, $recent['report']['total']);
        $this->assertSame(1, $recent['summary']['requests']);

        $old = $this->report(['from' => now()->subDays(60)->toDateString(), 'to' => now()->subDays(30)->toDateString()]);
        $this->assertSame(1, $old['report']['total']);

        $none = $this->report(['from' => now()->addDays(10)->toDateString()]);
        $this->assertSame(0, $none['report']['total']);

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.reports.data', ['from' => '2026-10-10', 'to' => '2026-10-01']))
            ->assertStatus(422);
    }

    public function test_category_filter_limits_rows_and_figures(): void
    {
        $laptop = $this->makeEquipment(['name' => 'Dell Latitude', 'available_qty' => 2, 'total_qty' => 3]);
        $camera = $this->makeEquipment(['name' => 'Canon EOS', 'category_id' => $this->cameras->category_id, 'available_qty' => 1, 'total_qty' => 4]);

        $this->makeRequest($laptop);
        $this->makeRequest($camera);

        $all = $this->report();
        $this->assertSame(2, $all['report']['total']);
        $this->assertSame(3, $all['summary']['availableUnits']);
        $this->assertSame(7, $all['summary']['totalUnits']);

        $cameras = $this->report(['category' => $this->cameras->category_id]);
        $this->assertSame(1, $cameras['report']['total']);
        $this->assertSame('Canon EOS', $cameras['report']['rows'][0]['equipment']);
        $this->assertSame(1, $cameras['summary']['requests']);
        $this->assertSame(1, $cameras['summary']['availableUnits']);
        $this->assertSame(4, $cameras['summary']['totalUnits']);
        $this->assertSame($this->cameras->category_id, $cameras['filters']['category']);
    }

    public function test_return_report_resolves_status_from_the_condition_and_due_date(): void
    {
        $laptop = $this->makeEquipment();

        $onTime = $this->makeRequest($laptop, $this->approved());
        $this->loan($onTime, ['status' => 'Returned', 'return_date' => now()->toDateString(), 'condition_on_return' => 'Good — no issues']);

        $late = $this->makeRequest($laptop, $this->approved([
            'borrow_date' => now()->subDays(8)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
        ]));
        $this->loan($late, ['status' => 'Returned', 'return_date' => now()->toDateString(), 'condition_on_return' => 'Good']);

        $damaged = $this->makeRequest($laptop, $this->approved());
        $this->loan($damaged, ['status' => 'Returned', 'return_date' => now()->toDateString(), 'condition_on_return' => 'Damaged — cracked screen']);

        // A loan that is still out must not appear in the Return Report.
        $this->loan($this->makeRequest($laptop, $this->approved()));

        $payload = $this->report(['type' => 'return']);

        $this->assertSame(['Damaged', 'Returned', 'Returned Late'], $this->statuses($payload));
        $this->assertSame('Return Report', $payload['report']['title']);
        $this->assertSame(['Returned Late'], $this->statuses($this->report(['type' => 'return', 'status' => 'Returned Late'])));
        $this->assertSame(['Damaged'], $this->statuses($this->report(['type' => 'return', 'status' => 'Damaged'])));

        // Date range uses the date the item was actually returned.
        $today = now()->toDateString();
        $this->assertSame(3, $this->report(['type' => 'return', 'from' => $today, 'to' => $today])['report']['total']);
        $this->assertSame(0, $this->report(['type' => 'return', 'from' => now()->addDay()->toDateString()])['report']['total']);

        $damagedRow = collect($payload['report']['rows'])->firstWhere('status', 'Damaged');
        $this->assertSame('Damaged', $damagedRow['condition']);
        $this->assertSame(now()->format('M d, Y'), $damagedRow['return_date']);
    }

    public function test_overdue_report_lists_only_loans_that_are_past_due_and_still_out(): void
    {
        $laptop = $this->makeEquipment(['available_qty' => 0, 'total_qty' => 3]);

        $this->loan($this->makeRequest($laptop, $this->approved())); // due in 3 days

        $overdue = $this->makeRequest($laptop, $this->approved([
            'borrow_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(4)->toDateString(),
        ]));
        $this->loan($overdue);

        $returnedLate = $this->makeRequest($laptop, $this->approved([
            'borrow_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->subDays(4)->toDateString(),
        ]));
        $this->loan($returnedLate, ['status' => 'Returned', 'return_date' => now()->toDateString()]);

        $payload = $this->report(['type' => 'overdue']);

        $this->assertSame(1, $payload['report']['total']);
        $this->assertSame(4, $payload['report']['rows'][0]['days_overdue']);
        $this->assertSame('Juan Dela Cruz', $payload['report']['rows'][0]['borrower']);
        $this->assertSame($payload['summary']['overdue'], $payload['report']['total']);

        // Date range filters by due date.
        $this->assertSame(0, $this->report(['type' => 'overdue', 'from' => now()->subDay()->toDateString()])['report']['total']);
        $this->assertSame(1, $this->report(['type' => 'overdue', 'to' => now()->subDays(3)->toDateString()])['report']['total']);
    }

    public function test_summary_cards_follow_the_report_date_basis_for_return_and_overdue_reports(): void
    {
        $laptop = $this->makeEquipment(['available_qty' => 1, 'total_qty' => 3]);

        // Borrowed 20 days ago, returned today: its return date, not its borrow date, is in range.
        $returned = $this->makeRequest($laptop, $this->approved([
            'borrow_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDays(10)->toDateString(),
        ]));
        $this->loan($returned, ['status' => 'Returned', 'return_date' => now()->toDateString(), 'condition_on_return' => 'Good']);

        // Borrowed 15 days ago, due 5 days ago and still out.
        $overdue = $this->makeRequest($laptop, $this->approved([
            'borrow_date' => now()->subDays(15)->toDateString(),
            'due_date' => now()->subDays(5)->toDateString(),
        ]));
        $this->loan($overdue);

        $returns = $this->report(['type' => 'return', 'from' => now()->subDays(2)->toDateString(), 'to' => now()->toDateString()]);
        $this->assertSame(1, $returns['report']['total']);
        $this->assertSame(1, $returns['summary']['returns']);

        $overdues = $this->report(['type' => 'overdue', 'from' => now()->subDays(6)->toDateString(), 'to' => now()->subDays(4)->toDateString()]);
        $this->assertSame(1, $overdues['report']['total']);
        $this->assertSame(1, $overdues['summary']['overdue']);
    }

    public function test_inventory_report_shows_stock_units_on_loan_and_ignores_dates(): void
    {
        $loaned = $this->makeEquipment(['name' => 'Dell Latitude', 'available_qty' => 3, 'total_qty' => 5]);
        $this->makeEquipment([
            'name' => 'Canon EOS',
            'category_id' => $this->cameras->category_id,
            'available_qty' => 0,
            'total_qty' => 2,
            'status' => 'Unavailable',
        ]);

        $this->loan($this->makeRequest($loaned, $this->approved(['quantity' => 2])));

        $payload = $this->report(['type' => 'inventory', 'from' => '2020-01-01', 'to' => '2020-01-31']);

        $this->assertNull($payload['filters']['from']);
        $this->assertNull($payload['filters']['to']);
        $this->assertSame(2, $payload['report']['total']);

        $row = collect($payload['report']['rows'])->firstWhere('equipment', 'Dell Latitude');
        $this->assertSame(5, $row['total']);
        $this->assertSame(3, $row['available']);
        $this->assertSame(2, $row['on_loan']);
        $this->assertSame('Available', $row['status']);
        $this->assertSame('Laptop', $row['category']);
        $this->assertSame('College of Computer Studies', $row['department']);

        $this->assertSame(['Unavailable'], $this->statuses($this->report(['type' => 'inventory', 'status' => 'Unavailable'])));
        $this->assertSame(1, $this->report(['type' => 'inventory', 'category' => $this->cameras->category_id])['report']['total']);
    }

    public function test_user_activity_report_summarises_each_borrower(): void
    {
        $laptop = $this->makeEquipment(['available_qty' => 5, 'total_qty' => 5]);
        $maria = $this->makeUser('maria@example.com', 'Maria', 'Santos', '2020-0002');

        $this->loan($this->makeRequest($laptop, $this->approved())); // Juan: on loan
        $this->makeRequest($laptop, ['overall_status' => 'Rejected', 'admin_status' => 'Rejected']);
        $this->makeRequest($laptop);
        $this->makeRequest($laptop, [], $maria);

        $payload = $this->report(['type' => 'activity']);
        $rows = $payload['report']['rows'];

        $this->assertCount(2, $rows);

        $this->assertSame('Juan Dela Cruz', $rows[0]['borrower']);
        $this->assertSame(3, $rows[0]['requests']);
        $this->assertSame(1, $rows[0]['approved']);
        $this->assertSame(1, $rows[0]['rejected']);
        $this->assertSame(1, $rows[0]['pending']);
        $this->assertSame(1, $rows[0]['borrowed']);
        $this->assertSame(0, $rows[0]['overdue']);
        $this->assertSame(0, $rows[0]['returned']);

        $this->assertSame('Maria Santos', $rows[1]['borrower']);
        $this->assertSame(1, $rows[1]['requests']);

        // The per-user totals add up to the summary.
        $this->assertSame($payload['summary']['requests'], array_sum(array_column($rows, 'requests')));
    }

    public function test_charts_count_approved_borrowings_by_month_category_and_equipment(): void
    {
        $laptop = $this->makeEquipment(['name' => 'Dell Latitude']);
        $camera = $this->makeEquipment(['name' => 'Canon EOS', 'category_id' => $this->cameras->category_id]);

        $this->loan($this->makeRequest($laptop, $this->approved()));
        $this->loan($this->makeRequest($laptop, $this->approved()));
        $this->loan($this->makeRequest($camera, $this->approved()));
        $this->makeRequest($camera); // pending requests are not borrowings

        $charts = $this->report()['charts'];

        $this->assertCount(6, $charts['monthly']['labels']);
        $this->assertSame(now()->format('M Y'), end($charts['monthly']['labels']));
        $this->assertSame(3, end($charts['monthly']['values']));

        $this->assertSame(['Laptop', 'Camera'], $charts['category']['labels']);
        $this->assertSame([2, 1], $charts['category']['values']);

        $this->assertSame('Dell Latitude', $charts['mostBorrowed']['name']);
        $this->assertSame(2, $charts['mostBorrowed']['count']);
    }

    public function test_generating_a_report_is_logged_and_feeds_the_counter_and_recent_list(): void
    {
        $this->makeRequest($this->makeEquipment());

        $this->assertSame(0, $this->report()['summary']['reportsThisMonth']);

        $response = $this->actingAs($this->admin, 'admin')->postJson(route('admin.reports.generate'), [
            'type' => 'overdue',
            'category' => $this->laptops->category_id,
        ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertSame(1, $response->json('summary.reportsThisMonth'));

        $this->assertDatabaseHas('audit_trail', [
            'admin_id' => $this->admin->admin_id,
            'action' => 'Generated report: Overdue Report',
            'affected_entity' => 'report:overdue',
        ]);

        // Reading the report again (no Generate click) must not count as a new report.
        $payload = $this->report();
        $this->assertSame(1, $payload['summary']['reportsThisMonth']);
        $this->assertCount(1, $payload['recent']);

        $recent = $payload['recent'][0];
        $this->assertSame('Overdue Report', $recent['name']);
        $this->assertSame('System Admin', $recent['by']);
        $this->assertSame('On screen', $recent['format']);
        $this->assertSame('Laptop', $recent['detail']);
        $this->assertSame('overdue', $recent['filters']['type']);
        $this->assertSame($this->laptops->category_id, $recent['filters']['category']);
    }

    public function test_csv_export_matches_the_preview_and_neutralises_spreadsheet_formulas(): void
    {
        $this->makeRequest($this->makeEquipment(['name' => '=SUM(1+1)']));

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.export', ['format' => 'csv', 'type' => 'borrowing']));

        $response->assertOk()->assertDownload();

        $csv = $response->streamedContent();

        $this->assertStringContainsString('Borrowing Report', $csv);
        $this->assertStringContainsString('Student/Faculty ID', $csv);
        $this->assertStringContainsString('Juan Dela Cruz', $csv);
        $this->assertStringContainsString("'=SUM(1+1)", $csv);
        $this->assertStringContainsString('"Total records",1', $csv);

        $this->assertDatabaseHas('audit_trail', [
            'action' => 'Exported report: Borrowing Report (CSV)',
            'affected_entity' => 'report:borrowing',
        ]);
    }

    public function test_print_export_renders_a_printable_page_and_shows_the_empty_state(): void
    {
        $this->makeRequest($this->makeEquipment());

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.export', ['format' => 'print', 'type' => 'borrowing']))
            ->assertOk()
            ->assertSee('Borrowing Report')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('window.print');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.export', ['format' => 'pdf', 'type' => 'overdue']))
            ->assertOk()
            ->assertSee('No records match the selected filters.');

        $this->assertSame(2, AuditTrail::where('affected_entity', 'like', 'report:%')->count());
    }

    public function test_export_rejects_an_unknown_format(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.export', ['format' => 'docx']))
            ->assertNotFound();
    }
}
