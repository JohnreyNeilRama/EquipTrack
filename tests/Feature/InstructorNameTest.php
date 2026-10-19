<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BorrowRequest;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\FacultyMember;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Instructor Name on the Request Equipment form: required, saved with the
 * borrow request, and shown in the user's and the reviewers' request details.
 */
class InstructorNameTest extends TestCase
{
    use RefreshDatabase;

    private UserAccount $user;

    private Admin $admin;

    private DepartmentAccount $deptAccount;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $department = Department::create([
            'department_name' => 'College of Computer Studies',
            'department_code' => 'CCS',
        ]);

        $category = EquipmentCategory::create(['category_name' => 'Laptop']);

        $this->equipment = Equipment::create([
            'name' => 'Dell Latitude',
            'brand' => 'Dell',
            'serial_number' => 'EQ-0001',
            'image' => '',
            'available_qty' => 2,
            'total_qty' => 2,
            'status' => 'Available',
            'category_id' => $category->category_id,
            'department_id' => $department->department_id,
        ]);

        $this->user = UserAccount::create([
            'role' => 'Student',
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
            'department_id' => $department->department_id,
            'status' => 'Active',
        ]);

        Student::create([
            'user_id' => $this->user->user_id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'id_number' => '2020-0001',
            'year_level' => '3',
            'address' => 'Cebu City',
        ]);

        $this->admin = Admin::create([
            'name' => 'System Admin',
            'username' => 'admin',
            'password' => Hash::make('password'),
        ]);

        $this->deptAccount = DepartmentAccount::create([
            'full_name' => 'Lab Custodian',
            'email' => 'dept@example.com',
            'password' => Hash::make('password'),
            'role' => 'Department Head',
            'department_id' => $department->department_id,
            'status' => 'Active',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'equipment_id' => $this->equipment->equipment_id,
            'borrow_date' => now()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'purpose' => 'Laboratory Activity',
            'instructor_name' => '  Prof. Maria Santos  ',
            'notes' => 'Handle with care',
        ], $overrides);
    }

    public function test_the_request_form_has_the_field_between_purpose_and_notes(): void
    {
        $this->actingAs($this->user, 'user')->get(route('user.equipment'))
            ->assertOk()
            ->assertSeeInOrder(['id="borrowPurpose"', 'id="borrowInstructor"', 'id="borrowNotes"'], false)
            ->assertSee("formData.append('instructor_name', instructor)", false);
    }

    public function test_the_instructor_name_is_saved_with_the_request(): void
    {
        $response = $this->actingAs($this->user, 'user')
            ->postJson(route('user.borrow.store'), $this->payload());

        $response->assertOk()->assertJson(['success' => true]);

        $request = BorrowRequest::findOrFail($response->json('request_id'));
        $this->assertSame('Prof. Maria Santos', $request->instructor_name);
    }

    public function test_the_instructor_name_is_required(): void
    {
        foreach ([[], ['instructor_name' => ''], ['instructor_name' => '   ']] as $overrides) {
            $payload = $this->payload($overrides);
            if ($overrides === []) {
                unset($payload['instructor_name']);
            }

            $this->actingAs($this->user, 'user')
                ->postJson(route('user.borrow.store'), $payload)
                ->assertStatus(422)
                ->assertJsonValidationErrors(['instructor_name' => "Please enter your instructor's name."]);
        }

        $this->actingAs($this->user, 'user')
            ->postJson(route('user.borrow.store'), $this->payload(['instructor_name' => str_repeat('a', 151)]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('instructor_name');

        $this->assertSame(0, BorrowRequest::count());
    }

    public function test_the_user_and_the_reviewers_can_see_the_instructor_name(): void
    {
        $id = (int) $this->actingAs($this->user, 'user')
            ->postJson(route('user.borrow.store'), $this->payload())
            ->json('request_id');

        $this->actingAs($this->user, 'user')->get(route('user.requests'))
            ->assertOk()
            ->assertSee('data-instructor="Prof. Maria Santos"', false)
            ->assertSee('id="detailInstructor"', false);

        $deptRow = collect($this->actingAs($this->deptAccount, 'dept')
            ->getJson(route('department.requests.data'))->assertOk()->json('requests'))
            ->firstWhere('id', $id);
        $this->assertSame('Prof. Maria Santos', $deptRow['instructor']);

        $this->actingAs($this->deptAccount, 'dept')->get(route('department.requests'))
            ->assertOk()
            ->assertSeeInOrder(['id="modalPurpose"', 'id="modalInstructor"', 'id="modalNotes"'], false);
    }

    private function facultyUser(): UserAccount
    {
        $faculty = UserAccount::create([
            'role' => 'Faculty',
            'email' => 'faculty@example.com',
            'password' => Hash::make('password'),
            'department_id' => $this->user->department_id,
            'status' => 'Active',
        ]);

        FacultyMember::create([
            'user_id' => $faculty->user_id,
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'faculty_id_number' => 'F-0001',
            'teaching_license_no' => 'LIC-0001',
            'address' => 'Cebu City',
        ]);

        return $faculty;
    }

    public function test_faculty_members_do_not_see_the_field(): void
    {
        $this->actingAs($this->facultyUser(), 'user')->get(route('user.equipment'))
            ->assertOk()
            ->assertSeeInOrder(['id="borrowPurpose"', 'id="borrowNotes"'], false)
            ->assertDontSee('id="borrowInstructor"', false)
            ->assertDontSee('Instructor Name:', false);

        // Students still get it
        $this->actingAs($this->user, 'user')->get(route('user.equipment'))
            ->assertOk()
            ->assertSee('id="borrowInstructor"', false);
    }

    public function test_faculty_members_can_request_without_an_instructor_name(): void
    {
        $faculty = $this->facultyUser();

        $payload = $this->payload();
        unset($payload['instructor_name']);

        $response = $this->actingAs($faculty, 'user')->postJson(route('user.borrow.store'), $payload);
        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNull(BorrowRequest::findOrFail($response->json('request_id'))->instructor_name);

        // Anything sent anyway is not saved for faculty members
        $response = $this->actingAs($faculty, 'user')
            ->postJson(route('user.borrow.store'), $this->payload(['instructor_name' => 'Someone Else']));
        $response->assertOk();
        $this->assertNull(BorrowRequest::findOrFail($response->json('request_id'))->instructor_name);
    }

    public function test_requests_from_before_the_field_show_n_a(): void
    {
        $id = (int) $this->actingAs($this->user, 'user')
            ->postJson(route('user.borrow.store'), $this->payload())
            ->json('request_id');
        BorrowRequest::whereKey($id)->update(['instructor_name' => null]);

        $this->actingAs($this->user, 'user')->get(route('user.requests'))
            ->assertOk()
            ->assertSee('data-instructor="N/A"', false);

        $deptRow = collect($this->actingAs($this->deptAccount, 'dept')
            ->getJson(route('department.requests.data'))->json('requests'))
            ->firstWhere('id', $id);
        $this->assertSame('N/A', $deptRow['instructor']);
    }
}
