<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\DepartmentAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Lab Personnel Profile page: the same design and behaviour as the User
 * account's. Personal information is read-only until Edit Profile; the
 * password has its own Change Password section with a Save Password button.
 */
class DepartmentProfileTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private DepartmentAccount $lab;

    protected function setUp(): void
    {
        parent::setUp();

        $this->lab = $this->makeLabPersonnel($this->makeDepartment(), 'lab@example.com', 'Ana Custodian', ['employee_id' => 'EMP-001']);
    }

    private function changePassword(array $input)
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.profile.password'), $input);
    }

    public function test_the_page_shows_the_profile_read_only_with_edit_and_password_sections(): void
    {
        $html = $this->actingAs($this->lab, 'dept')->get(route('department.profile'))->assertOk()->getContent();

        // Its own stylesheet (same design as the User profile's).
        $this->assertStringContainsString('departments/css/profile.css', $html);
        $this->assertStringNotContainsString('user/css/userprofile.css', $html);
        $this->assertStringContainsString('id="btnEditProfile"', $html);
        $this->assertStringContainsString('Save Changes', $html);
        $this->assertStringContainsString('Save Password', $html);
        $this->assertStringContainsString('Ana Custodian', $html);
        $this->assertStringContainsString('College of Computer Studies', $html);
        $this->assertStringContainsString('EMP-001', $html);

        // Every Personal Information field starts read-only.
        foreach (['fullName', 'email', 'employeeId', 'role', 'department'] as $id) {
            $this->assertMatchesRegularExpression('/<input[^>]*id="' . $id . '"[^>]*readonly/', $html, $id . ' should be read-only');
        }
    }

    public function test_saving_profile_information_no_longer_needs_the_password_fields(): void
    {
        $this->actingAs($this->lab, 'dept')->postJson(route('department.profile.update'), [
            'full_name' => 'Ana Santos',
            'email' => 'ana@example.com',
            'employee_id' => 'EMP-002',
        ])->assertOk()->assertJson(['success' => true]);

        $this->lab->refresh();
        $this->assertSame('Ana Santos', $this->lab->full_name);
        $this->assertSame('ana@example.com', $this->lab->email);
        $this->assertSame('EMP-002', $this->lab->employee_id);
        $this->assertTrue(Hash::check('Lab2026pass', $this->lab->password));
    }

    public function test_save_password_changes_the_password_and_is_audited(): void
    {
        $this->changePassword([
            'current_password' => 'Lab2026pass',
            'new_password' => 'NewLab2026',
            'confirm_password' => 'NewLab2026',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertTrue(Hash::check('NewLab2026', $this->lab->fresh()->password));
        $this->assertDatabaseHas('audit_trail', ['dept_acc_id' => $this->lab->dept_acc_id, 'action' => 'Changed password']);
    }

    public function test_save_password_checks_the_current_password_and_the_new_one(): void
    {
        $this->changePassword([])->assertStatus(422)
            ->assertJsonValidationErrors(['current_password', 'new_password', 'confirm_password']);

        $this->changePassword([
            'current_password' => 'wrong-password',
            'new_password' => 'NewLab2026',
            'confirm_password' => 'NewLab2026',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password' => 'Current password is incorrect.']);

        $this->changePassword([
            'current_password' => 'Lab2026pass',
            'new_password' => 'short',
            'confirm_password' => 'different',
        ])->assertStatus(422)->assertJsonValidationErrors(['new_password', 'confirm_password']);

        $this->assertTrue(Hash::check('Lab2026pass', $this->lab->fresh()->password));
    }

    public function test_only_signed_in_lab_personnel_can_change_their_password(): void
    {
        $this->postJson(route('department.profile.password'), [
            'current_password' => 'Lab2026pass',
            'new_password' => 'NewLab2026',
            'confirm_password' => 'NewLab2026',
        ])->assertUnauthorized();

        $student = $this->makeStudent(Department::first());
        $this->actingAs($student, 'user')->postJson(route('department.profile.password'), [])->assertUnauthorized();
    }
}
