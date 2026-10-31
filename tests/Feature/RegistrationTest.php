<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\FacultyMember;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Self-registration for Students and Faculty. A student or faculty ID that is
 * already taken is turned away with a message instead of a server error.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Department $ccs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ccs = $this->makeDepartment();
    }

    private function register(array $input)
    {
        return $this->from('/register')->post('/register', $input + [
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'year_level' => '1',
            'department_id' => $this->ccs->department_id,
            'email' => 'ana@example.com',
            'address' => 'Cebu City',
            'password' => 'Study2026',
        ]);
    }

    public function test_a_student_registers(): void
    {
        $this->register(['role' => 'student', 'id_number' => '20260001'])->assertRedirect('/login?registered=1');

        $account = UserAccount::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame('Student', $account->role);
        $this->assertDatabaseHas('student', ['user_id' => $account->user_id, 'id_number' => '20260001']);
    }

    public function test_a_taken_student_id_is_rejected_with_a_message(): void
    {
        $this->makeStudent($this->ccs, 'first@example.com', ['id_number' => '20260001']);

        $this->register(['role' => 'student', 'id_number' => '20260001'])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['id_number' => 'Another student already has this student ID.']);

        $this->assertDatabaseMissing('user_account', ['email' => 'ana@example.com']);
        $this->assertSame(1, Student::count());
    }

    public function test_a_faculty_member_registers_with_an_upper_case_id(): void
    {
        $this->register(['role' => 'teacher', 'id_number' => ' t-1001 '])->assertRedirect('/login?registered=1');

        $account = UserAccount::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame('Faculty', $account->role);
        $this->assertDatabaseHas('faculty_member', [
            'user_id' => $account->user_id,
            'faculty_id_number' => 'T-1001',
            'teaching_license_no' => 'TL-T-1001',
        ]);
    }

    public function test_a_taken_faculty_id_is_rejected_whatever_its_case(): void
    {
        $this->makeFaculty($this->ccs, 'first@example.com', ['faculty_id_number' => 'T-1001']);

        $this->register(['role' => 'teacher', 'id_number' => 't-1001'])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['id_number' => 'Another faculty member already has this faculty ID number.']);

        $this->assertDatabaseMissing('user_account', ['email' => 'ana@example.com']);
        $this->assertSame(1, FacultyMember::count());
    }

    public function test_a_faculty_id_whose_license_number_is_taken_is_rejected(): void
    {
        $this->makeFaculty($this->ccs, 'first@example.com', ['faculty_id_number' => 'OLD-1', 'teaching_license_no' => 'TL-T-2002']);

        $this->register(['role' => 'teacher', 'id_number' => 'T-2002'])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['id_number' => 'Another faculty member already has this faculty ID number.']);

        $this->assertSame(1, FacultyMember::count());
    }

    public function test_a_faculty_id_with_invalid_characters_is_rejected(): void
    {
        $this->register(['role' => 'teacher', 'id_number' => 'T 1001!'])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['id_number' => 'The faculty ID number may contain only letters, numbers and hyphens.']);

        $this->assertSame(0, FacultyMember::count());
    }
}
