<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\FacultyMember;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Department "Users" page: lists the students and faculty members registered
 * under the signed-in department, straight from the database.
 */
class DepartmentUsersTest extends TestCase
{
    use RefreshDatabase;

    private Department $ccs;

    private Department $cba;

    private DepartmentAccount $ccsLab;

    private DepartmentAccount $cbaLab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ccs = Department::create(['department_name' => 'College of Computer Studies', 'department_code' => 'CCS']);
        $this->cba = Department::create(['department_name' => 'College of Business', 'department_code' => 'CBA']);

        $this->ccsLab = $this->makeDeptAccount('ccs.lab@example.com', 'Ana Santos', $this->ccs);
        $this->cbaLab = $this->makeDeptAccount('cba.lab@example.com', 'Ben Reyes', $this->cba);
    }

    private function makeDeptAccount(string $email, string $name, ?Department $department): DepartmentAccount
    {
        return DepartmentAccount::create([
            'full_name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'Lab Personnel',
            'department_id' => $department?->department_id,
            'status' => 'Active',
        ]);
    }

    private function makeStudent(Department $department, string $email, string $first, string $idNumber, string $yearLevel = '3rd Year', string $status = 'Active'): UserAccount
    {
        $account = UserAccount::create([
            'role' => 'Student',
            'email' => $email,
            'password' => Hash::make('password'),
            'department_id' => $department->department_id,
            'status' => $status,
        ]);

        Student::create([
            'user_id' => $account->user_id,
            'first_name' => $first,
            'last_name' => 'Dela Cruz',
            'id_number' => $idNumber,
            'year_level' => $yearLevel,
            'address' => 'Cebu City',
        ]);

        return $account;
    }

    private function makeFaculty(Department $department, string $email, string $first, string $idNumber, ?string $attainment = 'Master of Science'): UserAccount
    {
        $account = UserAccount::create([
            'role' => 'Faculty',
            'email' => $email,
            'password' => Hash::make('password'),
            'department_id' => $department->department_id,
            'status' => 'Active',
        ]);

        FacultyMember::create([
            'user_id' => $account->user_id,
            'first_name' => $first,
            'last_name' => 'Garcia',
            'faculty_id_number' => $idNumber,
            'teaching_license_no' => 'LIC-' . $idNumber,
            'highest_educational_attainment' => $attainment,
            'address' => 'Cebu City',
        ]);

        return $account;
    }

    private function feed(DepartmentAccount $as): array
    {
        $response = $this->actingAs($as, 'dept')->getJson(route('department.users.data'));
        $response->assertOk()->assertJson(['success' => true]);

        return $response->json('users');
    }

    public function test_guests_cannot_read_department_users(): void
    {
        $this->getJson(route('department.users.data'))->assertUnauthorized();
    }

    public function test_only_users_of_the_logged_in_department_are_listed(): void
    {
        $this->makeStudent($this->ccs, 'juan@example.com', 'Juan', '20200001');
        $this->makeFaculty($this->ccs, 'prof@example.com', 'Rosa', 'F-1001');
        $this->makeStudent($this->cba, 'maria@example.com', 'Maria', '20200002');

        $ccsUsers = $this->feed($this->ccsLab);
        $this->assertCount(2, $ccsUsers);
        $this->assertEqualsCanonicalizing(
            ['Juan Dela Cruz', 'Rosa Garcia'],
            array_column($ccsUsers, 'fullName')
        );

        $cbaUsers = $this->feed($this->cbaLab);
        $this->assertCount(1, $cbaUsers);
        $this->assertSame('Maria Dela Cruz', $cbaUsers[0]['fullName']);
    }

    public function test_student_and_faculty_fields_come_from_their_own_records(): void
    {
        $this->makeStudent($this->ccs, 'juan@example.com', 'Juan', '20200001', '3rd Year');
        $this->makeFaculty($this->ccs, 'prof@example.com', 'Rosa', 'F-1001', 'Master of Science');

        $byName = collect($this->feed($this->ccsLab))->keyBy('fullName');

        $student = $byName['Juan Dela Cruz'];
        $this->assertSame('Student', $student['userType']);
        $this->assertSame('20200001', $student['idNumber']);
        $this->assertSame('3rd Year', $student['attainment']);
        $this->assertSame('Active', $student['status']);
        $this->assertNotSame('—', $student['dateRegistered']);

        $faculty = $byName['Rosa Garcia'];
        $this->assertSame('Faculty Member', $faculty['userType']);
        $this->assertSame('F-1001', $faculty['idNumber']);
        $this->assertSame('Master of Science', $faculty['attainment']);
    }

    public function test_missing_attainment_shows_na(): void
    {
        $this->makeFaculty($this->ccs, 'prof@example.com', 'Rosa', 'F-1001', null);

        $this->assertSame('N/A', $this->feed($this->ccsLab)[0]['attainment']);
    }

    public function test_deactivated_accounts_are_shown_as_inactive(): void
    {
        $this->makeStudent($this->ccs, 'juan@example.com', 'Juan', '20200001', '3rd Year', 'Active');
        $this->makeStudent($this->ccs, 'pedro@example.com', 'Pedro', '20200003', '2nd Year', 'Deactivated');

        $byName = collect($this->feed($this->ccsLab))->keyBy('fullName');

        $this->assertSame('Active', $byName['Juan Dela Cruz']['status']);
        $this->assertSame('Inactive', $byName['Pedro Dela Cruz']['status']);
    }

    public function test_a_department_account_without_a_department_sees_no_users(): void
    {
        $this->makeStudent($this->ccs, 'juan@example.com', 'Juan', '20200001');
        $unassigned = $this->makeDeptAccount('nodept@example.com', 'No Dept', null);

        $this->assertSame([], $this->feed($unassigned));
    }

    public function test_new_users_show_up_in_the_feed(): void
    {
        $this->assertSame([], $this->feed($this->ccsLab));

        $this->makeStudent($this->ccs, 'juan@example.com', 'Juan', '20200001');

        $this->assertCount(1, $this->feed($this->ccsLab));
    }

    public function test_page_renders_only_its_own_departments_users(): void
    {
        $this->makeStudent($this->ccs, 'juan@example.com', 'Juan', '20200001');
        $this->makeStudent($this->cba, 'maria@example.com', 'Maria', '20200002');

        $response = $this->actingAs($this->ccsLab, 'dept')->get(route('department.users'));

        $response->assertOk();
        $response->assertSee('Juan Dela Cruz');
        $response->assertDontSee('Maria Dela Cruz');
    }

    public function test_feed_includes_the_details_shown_in_the_view_details_modal(): void
    {
        $this->makeStudent($this->ccs, 'juan@example.com', 'Juan', '20200001', '3rd Year');
        $this->makeFaculty($this->ccs, 'prof@example.com', 'Rosa', 'F-1001', 'Master of Science');
        $this->makeStudent($this->cba, 'maria@example.com', 'Maria', '20200002');

        $users = collect($this->feed($this->ccsLab));
        $this->assertCount(2, $users);

        $student = $users->firstWhere('fullName', 'Juan Dela Cruz');
        $this->assertSame('juan@example.com', $student['email']);
        $this->assertSame('Cebu City', $student['address']);
        $this->assertSame('College of Computer Studies', $student['department']);
        $this->assertSame('Year Level', $student['attainmentLabel']);
        $this->assertSame('Offline / Never', $student['lastOnline']);
        $this->assertNotSame('Database Record', $student['createdAt']);
        $this->assertNull($student['profileImage']);

        $faculty = $users->firstWhere('fullName', 'Rosa Garcia');
        $this->assertSame('prof@example.com', $faculty['email']);
        $this->assertSame('Highest Attainment', $faculty['attainmentLabel']);

        // Another department's user never reaches this department's feed.
        $this->assertNotContains('maria@example.com', $users->pluck('email')->all());
    }

    public function test_view_details_modal_uses_the_admin_layout_without_admin_only_actions(): void
    {
        $response = $this->actingAs($this->ccsLab, 'dept')->get(route('department.users'));

        $response->assertOk();
        $response->assertSee('id="viewUserModal"', false);
        $response->assertSee('modal-requester-profile', false);
        $response->assertSee('id="modalLastOnline"', false);
        $response->assertSee('id="modalCloseDetailsBtn"', false);
        // Deactivate and photo upload are admin-only and not offered here.
        $response->assertDontSee('modalToggleStatusBtn', false);
        $response->assertDontSee('adminUploadPicBtn', false);
    }
}
