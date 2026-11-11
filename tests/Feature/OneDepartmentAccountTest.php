<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Department;
use App\Models\DepartmentAccount;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Each department has exactly one Department Account, and its role is always
 * Department Head. The Admin's Add form refuses a second account for a
 * department, and the database enforces it too.
 */
class OneDepartmentAccountTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Admin $admin;

    private Department $ccs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeAdmin();
        $this->ccs = $this->makeDepartment();
    }

    private function addDepartmentAccount(array $input = [])
    {
        return $this->actingAs($this->admin, 'admin')->post(route('admin.users.department'), $input + [
            'full_name' => 'Dr. Christian Ferrer',
            'employee_id' => 'EMP-2026-001',
            'email' => 'ferrer@example.com',
            'department_id' => $this->ccs->department_id,
            'password' => 'Lab2026pass',
        ]);
    }

    public function test_the_admin_adds_a_department_account_as_department_head(): void
    {
        // A posted role is ignored: every Department Account is Department Head.
        $this->addDepartmentAccount(['role' => 'Department Staff'])->assertSessionHasNoErrors();

        $account = DepartmentAccount::where('email', 'ferrer@example.com')->firstOrFail();
        $this->assertSame(DepartmentAccount::ROLE, $account->role);
        $this->assertSame('Department Head', $account->role);
        $this->assertSame($this->ccs->department_id, (int) $account->department_id);
    }

    public function test_a_second_account_for_the_same_department_is_refused(): void
    {
        $this->addDepartmentAccount()->assertSessionHasNoErrors();

        $this->addDepartmentAccount([
            'full_name' => 'Second Person',
            'employee_id' => 'EMP-2026-002',
            'email' => 'second@example.com',
        ])->assertSessionHasErrors('department_id');

        $this->assertStringContainsString('already has a Department Account', $this->firstError());
        $this->assertSame(1, DepartmentAccount::where('department_id', $this->ccs->department_id)->count());
    }

    public function test_another_department_can_still_get_its_own_account(): void
    {
        $this->addDepartmentAccount()->assertSessionHasNoErrors();
        $ccj = $this->makeDepartment('College of Criminal Justice', 'CCJ');

        $this->addDepartmentAccount([
            'full_name' => 'Dr. Jemuel Rama',
            'employee_id' => 'EMP-2026-002',
            'email' => 'rama@example.com',
            'department_id' => $ccj->department_id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, DepartmentAccount::where('department_id', $ccj->department_id)->count());
    }

    public function test_the_database_refuses_a_second_account_for_a_department(): void
    {
        $this->makeLabPersonnel($this->ccs, 'one@example.com');

        $this->expectException(QueryException::class);
        $this->makeLabPersonnel($this->ccs, 'two@example.com', 'Second Person', ['employee_id' => 'EMP-X']);
    }

    public function test_the_admin_creates_a_new_department_together_with_its_account(): void
    {
        $this->addDepartmentAccount([
            'full_name' => 'Engr. Maria Santos',
            'employee_id' => 'EMP-2026-010',
            'email' => 'santos@example.com',
            'department_id' => 'new',
            'new_department_name' => '  College of Engineering ',
            'new_department_code' => 'coe',
            'new_department_college' => 'College of Engineering',
        ])->assertSessionHasNoErrors()
            ->assertSessionHas('serverMsg', "Department 'College of Engineering' and its Department Account for 'Engr. Maria Santos' were created. An invite to set their password was emailed to santos@example.com.");

        $department = Department::where('department_code', 'COE')->firstOrFail();
        $this->assertSame('College of Engineering', $department->department_name);
        $this->assertSame('College of Engineering', $department->college);
        $this->assertSame('Engr. Maria Santos', $department->department_head);

        $account = DepartmentAccount::where('email', 'santos@example.com')->firstOrFail();
        $this->assertSame($department->department_id, (int) $account->department_id);
        $this->assertSame(DepartmentAccount::ROLE, $account->role);

        $this->assertTrue(\App\Models\AuditTrail::where('affected_entity', 'department:' . $department->department_id)->where('action', 'Created department COE')->exists());
        $this->assertSame('Added Department', \App\Services\AuditCatalog::classify('Created department COE')['label']);
        $this->assertSame('Added User', \App\Services\AuditCatalog::classify('Added department account')['label']);
    }

    public function test_a_new_department_needs_a_unique_name_and_code_and_nothing_is_saved_on_error(): void
    {
        $new = [
            'full_name' => 'Engr. Maria Santos',
            'employee_id' => 'EMP-2026-010',
            'email' => 'santos@example.com',
            'department_id' => 'new',
            'new_department_name' => 'College of Engineering',
            'new_department_code' => 'COE',
        ];

        // Name and code are required.
        $this->addDepartmentAccount(['new_department_name' => '', 'new_department_code' => ''] + $new)
            ->assertSessionHasErrors(['new_department_name', 'new_department_code']);

        // The code takes letters, numbers and hyphens only.
        $this->addDepartmentAccount(['new_department_code' => 'C O E!'] + $new)->assertSessionHasErrors('new_department_code');

        // An existing department's name or code can't be reused (CCS is taken, in any case).
        $this->addDepartmentAccount(['new_department_name' => 'College of Computer Studies'] + $new)->assertSessionHasErrors('new_department_name');
        $this->addDepartmentAccount(['new_department_code' => 'ccs'] + $new)->assertSessionHasErrors('new_department_code');

        // A bad account field saves neither the department nor the account.
        $this->addDepartmentAccount(['email' => 'not-an-email'] + $new)->assertSessionHasErrors('email');

        $this->assertSame(1, Department::count());
        $this->assertSame(0, DepartmentAccount::count());
    }

    public function test_the_add_form_offers_a_new_department(): void
    {
        $this->actingAs($this->admin, 'admin')->get(route('admin.users'))->assertOk()
            ->assertSee('<option value="new" >+ Add a new department</option>', false)
            ->assertSee('id="newDepartmentFields"', false);
    }

    public function test_the_add_form_marks_departments_that_already_have_an_account(): void
    {
        $this->makeLabPersonnel($this->ccs, 'one@example.com');
        $ccj = $this->makeDepartment('College of Criminal Justice', 'CCJ');

        $page = $this->actingAs($this->admin, 'admin')->get(route('admin.users'))->assertOk();

        $page->assertSee('<option value="' . $this->ccs->department_id . '" disabled', false);
        $page->assertSee('College of Computer Studies (CCS) — already has an account', false);
        $page->assertDontSee('College of Criminal Justice (CCJ) — already has an account', false);
        $page->assertSee('value="Department Head" readonly', false);
    }
}
