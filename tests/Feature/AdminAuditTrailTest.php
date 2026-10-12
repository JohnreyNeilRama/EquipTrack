<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin Audit Trail: activities are written to audit_trail when they happen
 * and the page reads those same rows back (nothing is hardcoded or invented).
 */
class AdminAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = Admin::create([
            'name' => 'System Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeUser(string $email = 'juan@example.com', string $first = 'Juan', string $last = 'Dela Cruz'): UserAccount
    {
        $user = UserAccount::create([
            'role' => 'Student',
            'email' => $email,
            'password' => Hash::make('password'),
            'status' => 'Active',
        ]);

        Student::create([
            'user_id' => $user->user_id,
            'first_name' => $first,
            'last_name' => $last,
            'id_number' => '2020' . random_int(1000, 9999),
            'year_level' => '3rd Year',
            'address' => 'Cebu City',
        ]);

        return $user;
    }

    private function feed(array $query = []): array
    {
        $response = $this->actingAs($this->admin, 'admin')->getJson(route('admin.audit.data', $query));
        $response->assertOk()->assertJson(['success' => true]);

        return $response->json();
    }

    /** Inserts a raw audit row with a chosen time (fixture for list/filter tests). */
    private function insertRow(array $overrides = []): void
    {
        DB::table('audit_trail')->insert(array_merge([
            'admin_id' => $this->admin->admin_id,
            'action' => 'Login',
            'ip_address' => '127.0.0.1',
            'affected_entity' => 'auth:admin',
            'created_at' => now()->toDateTimeString(),
        ], $overrides));
    }

    // ------------------------------------------------------------------
    // Page & empty state
    // ------------------------------------------------------------------

    public function test_page_renders_and_an_empty_audit_trail_reports_no_records(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.audit'))
            ->assertOk()
            ->assertSee('Audit Trail')
            ->assertSee('No audit records found.');

        $payload = $this->feed();

        $this->assertSame(0, $payload['total']);
        $this->assertSame([], $payload['records']);
        $this->assertFalse($payload['hasAny']);
        $this->assertSame(['today' => 0, 'week' => 0, 'month' => 0, 'activeToday' => 0], $payload['stats']);
    }

    public function test_only_administrators_can_open_the_audit_trail(): void
    {
        $this->get(route('admin.audit'))->assertRedirect(route('login'));
        $this->get(route('admin.audit.data'))->assertRedirect(route('login'));

        $student = $this->makeUser();
        $this->actingAs($student, 'user')->get(route('admin.audit'))->assertRedirect(route('login'));
        $this->actingAs($student, 'user')->get(route('admin.audit.export'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------
    // Recording activity
    // ------------------------------------------------------------------

    public function test_admin_login_is_recorded_once_and_shown_on_the_page(): void
    {
        $this->post('/login', ['email' => 'admin', 'password' => 'password'])->assertRedirect();

        $this->assertDatabaseHas('audit_trail', [
            'admin_id' => $this->admin->admin_id,
            'action' => 'Login',
            'affected_entity' => 'auth:admin',
        ]);
        $this->assertSame(1, AuditTrail::where('action', 'Login')->count());

        $login = collect($this->feed()['records'])->firstWhere('action', 'Login');
        $this->assertSame('System Admin', $login['user']);
        $this->assertSame('Administrator', $login['role']);
        $this->assertSame('Authentication', $login['module']);
        $this->assertSame('Administrator account', $login['entity']);
        $this->assertSame('Signed in as Administrator', $login['note']);
        $this->assertSame('127.0.0.1', $login['ip']);
        $this->assertNotSame('—', $login['date']);
    }

    public function test_logout_is_recorded_once_for_the_signed_in_account(): void
    {
        $this->actingAs($this->admin, 'admin')->post('/logout')->assertRedirect('/login');

        $this->assertSame(1, AuditTrail::where('action', 'Logout')->count());
        $this->assertDatabaseHas('audit_trail', [
            'admin_id' => $this->admin->admin_id,
            'action' => 'Logout',
            'affected_entity' => 'auth:admin',
        ]);
    }

    public function test_logging_out_without_being_signed_in_is_not_recorded(): void
    {
        $this->post('/logout')->assertRedirect('/login');

        $this->assertSame(0, AuditTrail::count());
    }

    public function test_a_failed_login_is_not_recorded(): void
    {
        $this->post('/login', ['email' => 'admin', 'password' => 'wrong-password']);

        $this->assertSame(0, AuditTrail::count());
    }

    public function test_student_login_is_recorded_against_that_student(): void
    {
        $student = $this->makeUser();

        $this->post('/login', ['email' => 'juan@example.com', 'password' => 'password'])->assertRedirect();

        $this->assertDatabaseHas('audit_trail', [
            'user_id' => $student->user_id,
            'action' => 'Login',
            'affected_entity' => 'auth:user',
        ]);

        $row = $this->feed()['records'][0];
        $this->assertSame('Juan Dela Cruz', $row['user']);
        $this->assertSame('Student', $row['role']);
    }

    public function test_public_registration_is_recorded(): void
    {
        $this->post('/register', [
            'role' => 'student',
            'first_name' => 'Ana',
            'last_name' => 'Reyes',
            'id_number' => '20210001',
            'year_level' => '2',
            'email' => 'ana@example.com',
            'address' => 'Cebu City',
            'password' => 'secret12',
        ])->assertRedirect('/login?registered=1');

        $account = UserAccount::where('email', 'ana@example.com')->firstOrFail();

        $this->assertDatabaseHas('audit_trail', [
            'user_id' => $account->user_id,
            'action' => 'Registered account',
            'affected_entity' => 'user_account:' . $account->user_id,
        ]);
        $this->assertSame(1, AuditTrail::where('action', 'Registered account')->count());

        $row = collect($this->feed()['records'])->firstWhere('action', 'Registered Account');
        $this->assertSame('Ana Reyes', $row['user']);
        $this->assertSame('Users', $row['module']);
    }

    public function test_user_management_changes_are_recorded_without_duplicates(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.users.student'), [
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'id_number' => '20220002',
            'year_level' => '1st Year',
            'email' => 'maria@example.com',
            'address' => 'Mandaue City',
            'password' => 'secret12',
        ])->assertRedirect(route('admin.users'));

        $account = UserAccount::where('email', 'maria@example.com')->firstOrFail();

        $this->assertDatabaseHas('audit_trail', [
            'admin_id' => $this->admin->admin_id,
            'action' => 'Added student account',
            'affected_entity' => 'user_account:' . $account->user_id,
        ]);

        // Deactivating twice is one real change, so one log row.
        foreach (range(1, 2) as $ignored) {
            $this->actingAs($this->admin, 'admin')->post(route('admin.users.toggle'), [
                'target_user_id' => $account->user_id,
                'target_user_type' => 'user_account',
                'new_status' => 'Deactivated',
            ]);
        }
        $this->assertSame(1, AuditTrail::where('action', 'Deactivated user account')->count());

        $this->actingAs($this->admin, 'admin')->post(route('admin.users.toggle'), [
            'target_user_id' => $account->user_id,
            'target_user_type' => 'user_account',
            'new_status' => 'Active',
        ]);
        $this->assertSame(1, AuditTrail::where('action', 'Activated user account')->count());

        $this->actingAs($this->admin, 'admin')->post(route('admin.users.delete'), [
            'user_id' => $account->user_id,
            'user_type' => 'user_account',
        ])->assertRedirect(route('admin.users'));

        $this->assertDatabaseMissing('user_account', ['user_id' => $account->user_id]);

        $deleted = AuditTrail::where('action', 'Deleted user account')->firstOrFail();
        $this->assertSame('user_account:' . $account->user_id, $deleted->affected_entity);
        $this->assertStringContainsString('maria@example.com', (string) $deleted->details);

        $actions = collect($this->feed()['records'])->pluck('action');
        $this->assertContains('Added User', $actions->all());
        $this->assertContains('Deactivated User', $actions->all());
        $this->assertContains('Activated User', $actions->all());
        $this->assertContains('Deleted User', $actions->all());
    }

    public function test_adding_an_equipment_category_is_recorded(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->postJson(route('admin.equipment.category'), ['category_name' => 'Projectors'])
            ->assertOk();

        $category = EquipmentCategory::where('category_name', 'Projectors')->firstOrFail();

        $this->assertDatabaseHas('audit_trail', [
            'admin_id' => $this->admin->admin_id,
            'action' => 'Added category "Projectors"',
            'affected_entity' => 'category:' . $category->category_id,
        ]);
    }

    public function test_a_rejection_keeps_its_full_reason_in_details_not_a_cut_off_action(): void
    {
        $department = Department::create(['department_name' => 'College of Computer Studies', 'department_code' => 'CCS']);
        $category = EquipmentCategory::create(['category_name' => 'Laptop']);
        $equipment = Equipment::create([
            'name' => 'Dell Latitude',
            'brand' => 'Dell',
            'serial_number' => 'EQ-1',
            'image' => '/storage/images/eq.jpg',
            'available_qty' => 2,
            'total_qty' => 2,
            'status' => 'Available',
            'category_id' => $category->category_id,
            'department_id' => $department->department_id,
        ]);
        $student = $this->makeUser();

        $request = BorrowRequest::create([
            'user_id' => $student->user_id,
            'equipment_id' => $equipment->equipment_id,
            'quantity' => 1,
            // Only Expensive requests are reviewed by the admin.
            'equipment_classification' => 'Expensive',
            'purpose' => 'Research',
            'date_needed' => now()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'due_date' => now()->addDays(3)->toDateString(),
            'borrow_date' => now()->toDateString(),
        ]);

        $reason = str_repeat('The requested unit is reserved for a faculty demonstration. ', 3);
        $reason = trim($reason);

        $this->actingAs($this->admin, 'admin')->postJson(route('admin.requests.update'), [
            'request_id' => $request->request_id,
            'status' => 'Rejected',
            'reject_reason' => $reason,
        ])->assertOk();

        $log = AuditTrail::where('action', 'like', 'Rejected borrow request%')->firstOrFail();
        $this->assertSame('Rejected borrow request #' . $request->request_id . ' for Dell Latitude', $log->action);
        $this->assertSame(mb_substr('Reason: ' . $reason, 0, 255), $log->details);

        // Reviewing it again must not log a second time.
        $this->actingAs($this->admin, 'admin')->postJson(route('admin.requests.update'), [
            'request_id' => $request->request_id,
            'status' => 'Rejected',
            'reject_reason' => $reason,
        ])->assertStatus(400);
        $this->assertSame(1, AuditTrail::where('action', 'like', 'Rejected borrow request%')->count());

        $row = collect($this->feed()['records'])->firstWhere('action', 'Rejected Request');
        $this->assertSame('Requests', $row['module']);
        $this->assertSame('Request #' . $request->request_id, $row['entity']);
        $this->assertSame('System Admin', $row['user']);
        $this->assertStringStartsWith('Reason: ', $row['note']);
    }

    // ------------------------------------------------------------------
    // Reading it back
    // ------------------------------------------------------------------

    public function test_rows_show_the_responsible_account_or_a_deleted_account_placeholder(): void
    {
        $student = $this->makeUser();

        $this->insertRow(['admin_id' => null, 'user_id' => $student->user_id, 'action' => 'Submitted borrow request #4 for Dell Latitude', 'affected_entity' => 'borrow_request:4']);
        $this->insertRow(['admin_id' => null, 'action' => 'Login']);
        $this->insertRow(['action' => 'Generated report: Overdue Report', 'affected_entity' => 'report:overdue', 'details' => '{"t":"overdue"}']);

        $rows = collect($this->feed()['records']);

        $submitted = $rows->firstWhere('action', 'Submitted Request');
        $this->assertSame('Juan Dela Cruz', $submitted['user']);
        $this->assertSame('Student', $submitted['role']);
        $this->assertSame('Borrow request #4 for Dell Latitude', $submitted['description']);

        $this->assertSame('Deleted account', $rows->firstWhere('action', 'Login')['user']);

        // The JSON kept for "Recent Generated Reports" is never shown as a note.
        $report = $rows->firstWhere('action', 'Generated Report');
        $this->assertSame('', $report['note']);
        $this->assertSame('Report: Overdue Report', $report['description']);
    }

    public function test_search_filters_sorting_and_paging_run_on_the_stored_records(): void
    {
        foreach (range(1, 11) as $i) {
            $this->insertRow(['created_at' => now()->subSeconds($i)->toDateTimeString()]);
        }
        $this->insertRow([
            'action' => 'Deleted equipment #5',
            'affected_entity' => 'equipment:5',
            'created_at' => now()->subDays(40)->toDateTimeString(),
        ]);

        // Paging: 12 rows, 10 per page, newest first.
        $first = $this->feed();
        $this->assertSame(12, $first['total']);
        $this->assertSame(2, $first['lastPage']);
        $this->assertCount(10, $first['records']);
        $this->assertSame(1, $first['from']);
        $this->assertSame(10, $first['to']);

        $second = $this->feed(['page' => 2]);
        $this->assertCount(2, $second['records']);
        $this->assertSame(11, $second['from']);
        $this->assertSame(12, $second['to']);
        $this->assertSame('Deleted Equipment', $second['records'][1]['action']);

        // A page past the end falls back to the last page.
        $this->assertSame(2, $this->feed(['page' => 9])['page']);

        // Sorting.
        $oldestFirst = $this->feed(['dir' => 'asc']);
        $this->assertSame('Deleted Equipment', $oldestFirst['records'][0]['action']);

        // Action and module filters.
        $this->assertSame(1, $this->feed(['action' => 'Deleted Equipment'])['total']);
        $this->assertSame(11, $this->feed(['action' => 'Login'])['total']);
        $this->assertSame(11, $this->feed(['module' => 'Authentication'])['total']);
        $this->assertSame(1, $this->feed(['module' => 'Equipment Management'])['total']);

        // Search: action text, the person, the affected record, and module names.
        $this->assertSame(1, $this->feed(['q' => 'deleted'])['total']);
        $this->assertSame(12, $this->feed(['q' => 'System Admin'])['total']);
        $this->assertSame(1, $this->feed(['q' => 'equipment:5'])['total']);
        $this->assertSame(11, $this->feed(['q' => 'authentication'])['total']);
        $this->assertSame(0, $this->feed(['q' => 'no-such-activity'])['total']);

        // "No matches" is different from "no records at all".
        $none = $this->feed(['q' => 'no-such-activity']);
        $this->assertTrue($none['hasAny']);

        // Wildcard characters are searched literally.
        $this->assertSame(0, $this->feed(['q' => '%'])['total']);

        // Date ranges and stat cards.
        $this->assertSame(11, $this->feed(['range' => 'today'])['total']);
        $custom = $this->feed(['range' => 'custom', 'from' => now()->subDays(41)->toDateString(), 'to' => now()->subDays(39)->toDateString()]);
        $this->assertSame(1, $custom['total']);

        $this->assertSame(11, $first['stats']['today']);
        $this->assertSame(11, $first['stats']['week']);
        $this->assertSame(11, $first['stats']['month']);
        $this->assertSame(1, $first['stats']['activeToday']);

        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.audit.data', ['range' => 'custom', 'from' => '2026-10-10', 'to' => '2026-10-01']))
            ->assertStatus(422);
        $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.audit.data', ['action' => 'Made Up Action']))
            ->assertStatus(422);
    }

    public function test_export_downloads_the_filtered_rows_and_records_the_export_once(): void
    {
        $this->insertRow(['action' => 'Deleted equipment #5', 'affected_entity' => 'equipment:5']);
        $this->insertRow();

        $response = $this->actingAs($this->admin, 'admin')
            ->get(route('admin.audit.export', ['action' => 'Deleted Equipment']));

        $response->assertOk()->assertDownload();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Deleted Equipment', $csv);
        $this->assertStringContainsString('System Admin', $csv);
        $this->assertStringNotContainsString('Authentication', $csv);
        // The export is logged after reading, so it does not list itself.
        $this->assertStringNotContainsString('Exported Audit Log', $csv);

        $this->assertSame(1, AuditTrail::where('action', 'Exported audit log (CSV)')->count());
        $this->assertDatabaseHas('audit_trail', [
            'admin_id' => $this->admin->admin_id,
            'action' => 'Exported audit log (CSV)',
            'affected_entity' => 'audit',
        ]);

        $logged = collect($this->feed()['records'])->firstWhere('action', 'Exported Audit Log');
        $this->assertSame('Audit Trail', $logged['module']);
    }
}
