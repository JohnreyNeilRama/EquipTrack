<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use App\Services\AuditCatalog;
use App\Services\UserAccountEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * The Admin's Edit Details form on the Users page, for Student, Faculty and
 * Laboratory Personnel accounts. Lab Personnel rows use page ids offset by
 * 100000. Changes are listed in the message and recorded in the audit trail.
 */
class AdminEditUserTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Admin $admin;

    private Department $ccs;

    private Department $ccj;

    private UserAccount $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeAdmin();
        $this->ccs = $this->makeDepartment();
        $this->ccj = $this->makeDepartment('College of Criminal Justice', 'CCJ');
        $this->student = $this->makeStudent($this->ccs, 'juan@example.com', ['id_number' => '20200001', 'year_level' => '2nd Year']);
    }

    private function edit(UserAccount|DepartmentAccount $account, array $changes, ?object $as = null, string $guard = 'admin')
    {
        $account = $account->fresh();
        $id = $account instanceof DepartmentAccount ? 100000 + $account->dept_acc_id : $account->user_id;
        $type = $account instanceof DepartmentAccount ? 'department' : 'user_account';

        return $this->actingAs($as ?? $this->admin, $guard)->post(route('admin.users.update'), [
            'form_type' => 'edit',
            'user_id' => $id,
            'user_type' => $type,
        ] + array_merge(UserAccountEditor::values($account), $changes));
    }

    public function test_student_details_are_saved_and_the_changes_are_listed_and_audited(): void
    {
        $this->edit($this->student, [
            'year_level' => '3rd Year',
            'email' => 'juan.updated@example.com',
            'department_id' => $this->ccj->department_id,
            'address' => 'Mandaue City',
        ])->assertSessionHas('serverMsg', 'Account details updated. Changed: Year level, Department, Email, Address.');

        $student = $this->student->fresh('student');
        $this->assertSame('3rd Year', $student->student->year_level);
        $this->assertSame('juan.updated@example.com', $student->email);
        $this->assertSame($this->ccj->department_id, (int) $student->department_id);

        $audit = AuditTrail::where('action', 'Updated user account')->where('affected_entity', 'user_account:' . $this->student->user_id)->firstOrFail();
        $this->assertSame($this->admin->admin_id, (int) $audit->admin_id);
        $this->assertStringContainsString('Changed: Year level, Department, Email, Address', $audit->details);
        $this->assertSame('Updated User', AuditCatalog::classify($audit->action, $audit->affected_entity)['label']);
    }

    public function test_saving_without_changes_saves_nothing(): void
    {
        $this->edit($this->student, [])->assertSessionHas('serverMsg', 'No changes to save.');

        $this->assertSame(0, AuditTrail::where('action', 'Updated user account')->count());
    }

    public function test_fields_of_other_account_types_are_ignored(): void
    {
        $this->edit($this->student, ['faculty_id_number' => 'T-1', 'role' => 'Department Head', 'full_name' => 'X'])
            ->assertSessionHas('serverMsg', 'No changes to save.');

        $this->assertSame('Student', $this->student->fresh()->role);
    }

    public function test_invalid_student_details_are_refused(): void
    {
        $this->makeStudent($this->ccs, 'pedro@example.com', ['id_number' => '20209999']);
        $this->makeLabPersonnel($this->ccs, 'lab@example.com');

        $cases = [
            "another student's ID" => [['id_number' => '20209999'], 'Another student already has this student ID.'],
            'an ID that is not 8 digits' => [['id_number' => '12345'], 'Student ID must be exactly 8 digits.'],
            "a Lab Personnel's email" => [['email' => 'lab@example.com'], 'already used by another EquipTrack account'],
            "another student's email" => [['email' => 'pedro@example.com'], 'Another account already uses this email address.'],
            'a year level that does not exist' => [['year_level' => '9th Year'], 'invalid'],
            'a department that does not exist' => [['department_id' => 999], 'Select a valid department.'],
            'an empty first name' => [['first_name' => ''], 'first name'],
        ];

        foreach ($cases as $label => [$change, $expected]) {
            $before = UserAccountEditor::values($this->student->fresh('student'));
            $this->edit($this->student, $change)->assertSessionHasErrors();
            $this->assertEquals($before, UserAccountEditor::values($this->student->fresh('student')), $label);
            $this->assertStringContainsStringIgnoringCase($expected, $this->firstError(), $label);
        }
    }

    public function test_faculty_ids_are_saved_in_capitals_and_education_is_optional(): void
    {
        $faculty = $this->makeFaculty($this->ccs, 'ramon@example.com');

        $this->edit($faculty, ['faculty_id_number' => 't-012412b', 'teaching_license_no' => 'prc-0099', 'highest_educational_attainment' => 'PhD in Education']);
        $profile = $faculty->fresh('facultyMember')->facultyMember;
        $this->assertSame('T-012412B', $profile->faculty_id_number);
        $this->assertSame('PRC-0099', $profile->teaching_license_no);
        $this->assertSame('PhD in Education', $profile->highest_educational_attainment);

        $this->edit($faculty, ['highest_educational_attainment' => '']);
        $this->assertNull($faculty->fresh('facultyMember')->facultyMember->highest_educational_attainment);

        $this->edit($faculty, ['faculty_id_number' => 'T 01#'])->assertSessionHasErrors();
        $this->assertStringContainsString('letters, numbers and hyphens', $this->firstError());
    }

    public function test_lab_personnel_details_are_saved_with_their_own_rules(): void
    {
        $lab = $this->makeLabPersonnel($this->ccs, 'lab@example.com', 'Dr. Christian Ferrer', ['employee_id' => 'EMP-0001']);
        $this->makeLabPersonnel($this->ccj, 'other.lab@example.com', 'Dr. Jemuel Rama', ['employee_id' => 'EMP-0002']);

        $this->edit($lab, ['full_name' => 'Dr. Christian M. Ferrer']);
        $this->assertSame('Dr. Christian M. Ferrer', $lab->fresh()->full_name);
        $this->assertTrue(AuditTrail::where('affected_entity', 'department_account:' . $lab->dept_acc_id)->where('action', 'Updated user account')->exists());

        $this->edit($lab, ['employee_id' => 'EMP-0002'])->assertSessionHasErrors();
        $this->assertStringContainsString('already has this employee ID', $this->firstError());
    }

    public function test_a_department_account_is_always_department_head_and_cannot_move_to_a_department_that_has_one(): void
    {
        $lab = $this->makeLabPersonnel($this->ccs, 'lab@example.com', 'Dr. Christian Ferrer', ['employee_id' => 'EMP-0001']);
        $this->makeLabPersonnel($this->ccj, 'other.lab@example.com', 'Dr. Jemuel Rama', ['employee_id' => 'EMP-0002']);

        // A posted role is ignored: the account stays Department Head.
        $this->edit($lab, ['role' => 'Department Staff', 'full_name' => 'Dr. Christian M. Ferrer']);
        $this->assertSame(DepartmentAccount::ROLE, $lab->fresh()->role);
        $this->assertSame('Dr. Christian M. Ferrer', $lab->fresh()->full_name);

        // CCJ already has its Department Account.
        $this->edit($lab, ['department_id' => $this->ccj->department_id])->assertSessionHasErrors('department_id');
        $this->assertStringContainsString('already has a Department Account', $this->firstError());
        $this->assertSame($this->ccs->department_id, (int) $lab->fresh()->department_id);

        // A department with no account yet is fine.
        $cba = $this->makeDepartment('College of Business Administration', 'CBA');
        $this->edit($lab, ['department_id' => $cba->department_id])->assertSessionHasNoErrors();
        $this->assertSame($cba->department_id, (int) $lab->fresh()->department_id);
    }

    /** The Edit form's Department details box, filled with the department's saved values. */
    private function departmentDetails(Department $department, array $changes = []): array
    {
        $department = $department->fresh();

        return array_merge([
            'department_name' => $department->department_name,
            'department_code' => $department->department_code,
            'department_college' => (string) $department->college,
        ], $changes);
    }

    public function test_admin_can_correct_a_departments_name_code_and_college_without_losing_anything(): void
    {
        $lab = $this->makeLabPersonnel($this->ccs, 'lab@example.com', 'Dr. Christian Ferrer', ['employee_id' => 'EMP-0001']);
        $passwordHash = $lab->fresh()->password;
        $equipment = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector']);
        $loan = $this->makeLoan($this->student, $equipment, '2026-12-01');

        $this->edit($lab, $this->departmentDetails($this->ccs, [
            'department_name' => '  College of Computing Studies ',
            'department_code' => 'ccs-main',
            'department_college' => 'Main Campus',
        ]))->assertSessionHasNoErrors()
            ->assertSessionHas('serverMsg', 'Account details updated. Changed: Department name, Department code, College.');

        // The same department row is corrected (no new department, same id).
        $department = $this->ccs->fresh();
        $this->assertSame('College of Computing Studies', $department->department_name);
        $this->assertSame('CCS-MAIN', $department->department_code);
        $this->assertSame('Main Campus', $department->college);
        $this->assertSame(2, Department::count());

        // The account, its sign-in, role and everything linked to the department are kept.
        $lab = $lab->fresh();
        $this->assertSame($this->ccs->department_id, (int) $lab->department_id);
        $this->assertSame($passwordHash, $lab->password);
        $this->assertSame(DepartmentAccount::ROLE, $lab->role);
        $this->assertSame('lab@example.com', $lab->email);
        $this->assertSame($this->ccs->department_id, (int) $this->student->fresh()->department_id);
        $this->assertSame($this->ccs->department_id, (int) $equipment->fresh()->department_id);
        $this->assertNotNull($loan->fresh());

        // Every page reads the department by id, so the new name shows at once.
        $rows = collect($this->actingAs($this->admin, 'admin')->getJson(route('admin.users.data'))->json('users'));
        $this->assertSame('College of Computing Studies', $rows->firstWhere('email', 'juan@example.com')['department']);
        $this->assertSame('College of Computing Studies', $rows->firstWhere('email', 'lab@example.com')['department']);
        $this->assertSame('College of Computing Studies', $equipment->fresh()->department->department_name);

        // Audited as "Updated Department", with the old and new details.
        $audit = AuditTrail::where('affected_entity', 'department:' . $this->ccs->department_id)->firstOrFail();
        $this->assertSame('Updated department CCS-MAIN', $audit->action);
        $this->assertSame('Updated Department', AuditCatalog::classify($audit->action)['label']);
        $this->assertSame(
            'Changed: Department name, Department code, College · College of Computer Studies (CCS) → College of Computing Studies (CCS-MAIN), Main Campus',
            $audit->details,
        );

        // The optional college can be cleared again; the unchanged name and code are not "taken".
        $this->edit($lab, $this->departmentDetails($this->ccs, ['department_college' => '']))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('serverMsg', 'Account details updated. Changed: College.');
        $this->assertNull($this->ccs->fresh()->college);
        $this->assertSame('College of Computing Studies', $this->ccs->fresh()->department_name);
    }

    public function test_corrected_department_details_must_stay_unique_and_valid(): void
    {
        $lab = $this->makeLabPersonnel($this->ccs, 'lab@example.com', 'Dr. Christian Ferrer', ['employee_id' => 'EMP-0001']);

        $cases = [
            'department_name' => [['department_name' => 'College of Criminal Justice'], 'Another department already has this name.'],
            'department_code' => [['department_code' => 'ccj'], 'Another department already has this code.'],
        ];
        foreach ($cases as $field => [$changes, $message]) {
            $this->edit($lab, $this->departmentDetails($this->ccs, $changes))->assertSessionHasErrors([$field => $message]);
        }

        $this->edit($lab, $this->departmentDetails($this->ccs, ['department_name' => '   ']))->assertSessionHasErrors('department_name');
        $this->edit($lab, $this->departmentDetails($this->ccs, ['department_code' => 'CS 1']))
            ->assertSessionHasErrors(['department_code' => 'The department code may contain only letters, numbers and hyphens.']);

        // Nothing was saved, not even the account's other changes.
        $this->edit($lab, $this->departmentDetails($this->ccs, ['full_name' => 'Dr. C. Ferrer', 'department_code' => 'CCJ']))->assertSessionHasErrors('department_code');
        $this->assertSame('Dr. Christian Ferrer', $lab->fresh()->full_name);
        $this->assertSame('College of Computer Studies', $this->ccs->fresh()->department_name);
        $this->assertSame('CCS', $this->ccs->fresh()->department_code);
        $this->assertFalse(AuditTrail::where('action', 'like', 'Updated department%')->exists());
    }

    public function test_the_edit_form_shows_the_department_details_box(): void
    {
        $this->makeLabPersonnel($this->ccs, 'lab@example.com');

        $this->actingAs($this->admin, 'admin')->get(route('admin.users'))
            ->assertOk()
            ->assertSee('<legend>Department details</legend>', false)
            ->assertSee('name="department_name"', false)
            ->assertSee('name="department_code"', false)
            ->assertSee('name="department_college"', false)
            ->assertSee('const DEPARTMENT_DETAILS', false);
    }

    public function test_an_account_that_no_longer_exists_gets_a_clear_message(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.users.update'), [
            'form_type' => 'edit', 'user_id' => 99999, 'user_type' => 'user_account', 'first_name' => 'X',
        ])->assertSessionHas('serverMsg', 'That account no longer exists.');
    }

    public function test_only_administrators_can_use_the_admin_edit_form(): void
    {
        $lab = $this->makeLabPersonnel($this->ccs);

        $this->edit($this->student, ['first_name' => 'Hacked'], $lab, 'dept')->assertRedirect();
        $this->assertSame('Juan', $this->student->fresh('student')->student->first_name);
    }

    public function test_signed_out_visitors_are_sent_to_the_login_page(): void
    {
        $this->post(route('admin.users.update'), ['user_id' => $this->student->user_id, 'user_type' => 'user_account', 'first_name' => 'Hacked'])
            ->assertRedirect();
        $this->assertSame('Juan', $this->student->fresh('student')->student->first_name);
    }
}
