<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Admin Profile page: the same design and behaviour as the User account's.
 * Personal information is read-only until Edit Profile and saved by AJAX;
 * the password has its own Change Password section with a Save Password button.
 */
class AdminProfileTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeAdmin('admin@example.com', 'System Admin');
    }

    private function changePassword(array $input)
    {
        return $this->actingAs($this->admin, 'admin')->postJson(route('admin.profile.password'), $input);
    }

    public function test_the_page_shows_the_profile_read_only_with_edit_and_password_sections(): void
    {
        $html = $this->actingAs($this->admin, 'admin')->get(route('admin.profile'))->assertOk()->getContent();

        // Its own stylesheet (same design as the User profile's).
        $this->assertStringContainsString('admin/css/adminprofile.css', $html);
        $this->assertStringNotContainsString('user/css/userprofile.css', $html);
        $this->assertStringContainsString('id="btnEditProfile"', $html);
        $this->assertStringContainsString('Save Changes', $html);
        $this->assertStringContainsString('Save Password', $html);
        $this->assertStringContainsString('System Admin', $html);

        foreach (['adminFullName', 'adminEmail', 'adminEmployeeId', 'adminRole'] as $id) {
            $this->assertMatchesRegularExpression('/<input[^>]*id="' . $id . '"[^>]*readonly/', $html, $id . ' should be read-only');
        }

        // No made-up employee ID: an admin without one sees "Not set".
        $this->assertStringNotContainsString('ADM-0001', $html);
        $this->assertStringContainsString('Not set', $html);
    }

    public function test_saving_profile_information_by_ajax_returns_json(): void
    {
        $this->actingAs($this->admin, 'admin')->postJson(route('admin.profile.update'), [
            'admin_full_name' => 'Head Admin',
            'admin_email' => 'head@example.com',
            'admin_employee_id' => 'ADM-0042',
        ])->assertOk()->assertJson(['success' => true]);

        $this->admin->refresh();
        $this->assertSame('Head Admin', $this->admin->name);
        $this->assertSame('head@example.com', $this->admin->email);
        $this->assertSame('head@example.com', $this->admin->username);
        $this->assertSame('ADM-0042', $this->admin->employee_id);
        $this->assertTrue(Hash::check('Admin2026', $this->admin->password));
    }

    public function test_a_taken_email_is_a_json_error_on_the_ajax_save(): void
    {
        $this->makeAdmin('other@example.com', 'Other Admin');

        $this->actingAs($this->admin, 'admin')->postJson(route('admin.profile.update'), [
            'admin_full_name' => 'System Admin',
            'admin_email' => 'other@example.com',
        ])->assertStatus(422)->assertJsonValidationErrors('admin_email');

        $this->assertSame('admin@example.com', $this->admin->fresh()->email);
    }

    public function test_a_plain_form_post_is_still_redirected_back(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.profile.update'), [
            'admin_full_name' => 'Head Admin',
            'admin_email' => 'admin@example.com',
        ])->assertRedirect(route('admin.profile'));

        $this->assertSame('Head Admin', $this->admin->fresh()->name);
    }

    public function test_save_password_changes_the_password_and_is_audited(): void
    {
        $this->changePassword([
            'current_password' => 'Admin2026',
            'new_password' => 'NewAdmin2026',
            'confirm_password' => 'NewAdmin2026',
        ])->assertOk()->assertJson(['success' => true]);

        $this->assertTrue(Hash::check('NewAdmin2026', $this->admin->fresh()->password));
        $this->assertDatabaseHas('audit_trail', ['admin_id' => $this->admin->admin_id, 'action' => 'Changed password']);
    }

    public function test_save_password_checks_the_current_password_and_the_new_one(): void
    {
        $this->changePassword([])->assertStatus(422)
            ->assertJsonValidationErrors(['current_password', 'new_password', 'confirm_password']);

        $this->changePassword([
            'current_password' => 'wrong-password',
            'new_password' => 'NewAdmin2026',
            'confirm_password' => 'NewAdmin2026',
        ])->assertStatus(422)->assertJsonValidationErrors(['current_password' => 'Current password is incorrect.']);

        $this->assertTrue(Hash::check('Admin2026', $this->admin->fresh()->password));

        $this->app['auth']->forgetGuards();
        $this->postJson(route('admin.profile.password'), [])->assertUnauthorized();
    }
}
