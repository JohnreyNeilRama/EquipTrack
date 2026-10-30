<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use App\Services\AuditCatalog;
use App\Services\UserAccountEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Laboratory Personnel add, edit and activate/deactivate Students and Faculty,
 * always in their own department and never anyone else's.
 */
class DepartmentUserManagementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Department $ccs;

    private Department $ccj;

    private DepartmentAccount $ccsLab;

    private DepartmentAccount $ccjLab;

    private array $newStudent = [
        'first_name' => 'Ana', 'last_name' => 'Santos', 'id_number' => '20260001', 'year_level' => '1st Year',
        'email' => 'ana@example.com', 'address' => 'Cebu City', 'password' => 'Study2026',
    ];

    private array $newFaculty = [
        'first_name' => 'Ramon', 'last_name' => 'Reyes', 'faculty_id_number' => 't-20260', 'teaching_license_no' => '1234500',
        'highest_educational_attainment' => 'MIT', 'email' => 'ramon@example.com', 'address' => 'Cebu City', 'password' => 'Teach2026',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ccs = $this->makeDepartment();
        $this->ccj = $this->makeDepartment('College of Criminal Justice', 'CCJ');
        $this->ccsLab = $this->makeLabPersonnel($this->ccs, 'ccs.lab@example.com');
        $this->ccjLab = $this->makeLabPersonnel($this->ccj, 'ccj.lab@example.com', 'CCJ Custodian');
    }

    private function labPost(string $route, array $input, ?DepartmentAccount $as = null)
    {
        return $this->actingAs($as ?? $this->ccsLab, 'dept')->postJson(route($route), $input);
    }

    public function test_lab_personnel_add_a_student_to_their_own_department(): void
    {
        $response = $this->labPost('department.users.student', $this->newStudent + ['department_id' => $this->ccj->department_id])->assertOk();

        $ana = UserAccount::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame('Student', $ana->role);
        // Their own department, even though the request asked for another one.
        $this->assertSame($this->ccs->department_id, (int) $ana->department_id);
        $this->assertTrue(collect($response->json('users'))->contains('email', 'ana@example.com'));

        $audit = AuditTrail::where('affected_entity', 'user_account:' . $ana->user_id)->where('action', 'Added student account')->firstOrFail();
        $this->assertSame($this->ccsLab->dept_acc_id, (int) $audit->dept_acc_id);
        $this->assertSame('Added User', AuditCatalog::classify($audit->action, $audit->affected_entity)['label']);

        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => 'ana@example.com', 'password' => 'Study2026'])->assertRedirect(route('user.dashboard'));
    }

    public function test_lab_personnel_add_a_faculty_member_with_ids_in_capitals(): void
    {
        $this->labPost('department.users.faculty', $this->newFaculty)->assertOk();

        $ramon = UserAccount::with('facultyMember')->where('email', 'ramon@example.com')->firstOrFail();
        $this->assertSame('Faculty', $ramon->role);
        $this->assertSame($this->ccs->department_id, (int) $ramon->department_id);
        $this->assertSame('T-20260', $ramon->facultyMember->faculty_id_number);
    }

    public function test_invalid_new_accounts_are_refused(): void
    {
        $this->makeStudent($this->ccs, 'pedro@example.com', ['id_number' => '20209999']);
        $this->makeFaculty($this->ccs, 'faculty@example.com', ['teaching_license_no' => 'TL-T-012412']);

        $cases = [
            "another student's ID" => ['department.users.student', ['id_number' => '20209999', 'email' => 'x1@example.com'], 'Another student already has this student ID.'],
            "Lab Personnel's email" => ['department.users.student', ['id_number' => '20260002', 'email' => 'ccj.lab@example.com'], 'already used by another EquipTrack account'],
            'a weak password' => ['department.users.student', ['id_number' => '20260003', 'email' => 'x2@example.com', 'password' => 'short'], 'at least 8 characters'],
            'a year level that is not offered' => ['department.users.student', ['id_number' => '20260004', 'email' => 'x3@example.com', 'year_level' => '9th Year'], 'invalid'],
            // Same capitals: MySQL/MariaDB also match other capitalisations, the SQLite test database does not.
            "another faculty member's license" => ['department.users.faculty', ['faculty_id_number' => 'T-20261', 'teaching_license_no' => 'TL-T-012412', 'email' => 'x4@example.com'], 'already has this teaching license number'],
        ];

        foreach ($cases as $label => [$route, $change, $expected]) {
            $base = $route === 'department.users.student' ? $this->newStudent : $this->newFaculty;
            $before = UserAccount::count();
            $response = $this->labPost($route, array_merge($base, $change))->assertUnprocessable();
            $this->assertSame($before, UserAccount::count(), $label);
            $this->assertStringContainsStringIgnoringCase($expected, collect($response->json('errors'))->flatten()->first(), $label);
        }
    }

    public function test_editing_saves_changes_but_never_moves_the_user_to_another_department(): void
    {
        $juan = $this->makeStudent($this->ccs, 'juan@example.com', ['year_level' => '2nd Year']);

        $this->labPost('department.users.update', ['user_id' => $juan->user_id] + array_merge(
            UserAccountEditor::values($juan->fresh('student')),
            ['year_level' => '3rd Year', 'address' => 'Lapu-Lapu City', 'department_id' => $this->ccj->department_id],
        ))->assertOk()->assertJson(['message' => 'Account details updated. Changed: Year level, Address.']);

        $juan = $juan->fresh('student');
        $this->assertSame('3rd Year', $juan->student->year_level);
        $this->assertSame('Lapu-Lapu City', $juan->student->address);
        $this->assertSame($this->ccs->department_id, (int) $juan->department_id);
        $this->assertSame($this->ccsLab->dept_acc_id, (int) AuditTrail::where('action', 'Updated user account')->value('dept_acc_id'));
    }

    public function test_another_departments_users_are_off_limits(): void
    {
        $carlo = $this->makeStudent($this->ccj, 'carlo@example.com', ['first_name' => 'Carlo']);

        $this->labPost('department.users.update', ['user_id' => $carlo->user_id] + array_merge(UserAccountEditor::values($carlo->fresh('student')), ['first_name' => 'Hacked']))
            ->assertNotFound();
        $this->labPost('department.users.status', ['user_id' => $carlo->user_id, 'status' => 'Deactivated'])->assertNotFound();
        $this->assertSame('Carlo', $carlo->fresh('student')->student->first_name);
        $this->assertSame('Active', $carlo->fresh()->status);

        // Their own Lab Personnel can.
        $this->labPost('department.users.update', ['user_id' => $carlo->user_id] + array_merge(UserAccountEditor::values($carlo->fresh('student')), ['first_name' => 'Carlos']), $this->ccjLab)
            ->assertOk();
        $this->assertSame('Carlos', $carlo->fresh('student')->student->first_name);
    }

    public function test_deactivating_locks_the_user_out_and_is_audited_once(): void
    {
        $ana = $this->makeStudent($this->ccs, 'ana@example.com', [], 'Study2026');

        $response = $this->labPost('department.users.status', ['user_id' => $ana->user_id, 'status' => 'Deactivated'])->assertOk();
        $this->assertSame('Deactivated', $ana->fresh()->status);
        $this->assertSame('Inactive', collect($response->json('users'))->firstWhere('email', 'ana@example.com')['status']);

        // The same status again adds no second audit entry.
        $this->labPost('department.users.status', ['user_id' => $ana->user_id, 'status' => 'Deactivated'])->assertOk();
        $this->assertSame(1, AuditTrail::where('affected_entity', 'user_account:' . $ana->user_id)->where('action', 'Deactivated user account')->count());

        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => 'ana@example.com', 'password' => 'Study2026'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('deactivated', $this->firstError());

        $this->labPost('department.users.status', ['user_id' => $ana->user_id, 'status' => 'Active'])->assertOk();
        $this->assertSame('Active', $ana->fresh()->status);
    }

    public function test_an_account_without_a_department_cannot_add_users(): void
    {
        $noDepartment = $this->makeLabPersonnel(null, 'nodept@example.com');

        $this->labPost('department.users.student', $this->newStudent, $noDepartment)
            ->assertForbidden()
            ->assertJson(['message' => 'Your account is not assigned to a department.']);
    }

    public function test_students_and_signed_out_visitors_cannot_manage_users(): void
    {
        $juan = $this->makeStudent($this->ccs, 'juan@example.com');
        $pedro = $this->makeStudent($this->ccs, 'pedro@example.com');

        $this->actingAs($pedro, 'user')->postJson(route('department.users.status'), ['user_id' => $juan->user_id, 'status' => 'Deactivated'])
            ->assertUnauthorized();
        $this->assertSame('Active', $juan->fresh()->status);
    }

    public function test_signed_out_visitors_cannot_manage_users(): void
    {
        $juan = $this->makeStudent($this->ccs, 'juan@example.com');

        $this->postJson(route('department.users.status'), ['user_id' => $juan->user_id, 'status' => 'Deactivated'])->assertUnauthorized();
        $this->assertSame('Active', $juan->fresh()->status);
    }

    public function test_lab_personnel_have_no_delete_action(): void
    {
        $this->assertFalse(collect(Route::getRoutes())->contains(
            fn ($route) => str_starts_with($route->uri(), 'department/users') && str_contains($route->uri(), 'delete')
        ));
    }
}
