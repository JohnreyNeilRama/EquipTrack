<?php

namespace Tests\Feature;

use App\Models\AuditTrail;
use App\Models\UserAccount;
use App\Notifications\UserInviteNotification;
use App\Support\PrivacyConsent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Data Privacy Act of 2012 (RA 10173): the Privacy Notice, agreement when an
 * account is created, and a one-time question for accounts that haven't
 * agreed to the current version.
 */
class PrivacyNoticeTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    /** These tests are about the question itself: accounts start without agreeing. */
    protected bool $autoAcceptPrivacy = false;

    private function registration(int $departmentId, array $overrides = []): array
    {
        return $overrides + [
            'role' => 'student',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'id_number' => '20261234',
            'year_level' => '2',
            'department_id' => $departmentId,
            'email' => 'juan@example.com',
            'address' => 'Cebu City',
            'password' => 'Study2026',
            'privacy' => '1',
        ];
    }

    public function test_the_notice_is_public_and_matches_what_equiptrack_does(): void
    {
        $this->get(route('privacy'))->assertOk()
            ->assertSee('Privacy Notice')
            ->assertSee('Republic Act No. 10173')
            ->assertSee('University of Cebu, Main Campus')
            ->assertSee('student ID number')
            ->assertSee('teaching license number')
            ->assertSee('IP address')
            // Keeping periods come from the real settings
            ->assertSee('deleted 1 year after the penalty is settled or waived')
            ->assertSee('kept in EquipTrack for 2 years')
            ->assertSee('kept for 14 days')
            ->assertSee('National Privacy Commission')
            ->assertSee('through your department\'s laboratory office', false)
            ->assertSee('Version 1');

        config(['equiptrack.privacy.contact_email' => 'dpo@school.edu.ph', 'equiptrack.privacy.contact_name' => 'Ms. Reyes, Data Protection Officer']);
        $this->get(route('privacy'))->assertSee('mailto:dpo@school.edu.ph', false)->assertSee('Ms. Reyes, Data Protection Officer');
    }

    public function test_registering_needs_the_agreement_and_records_it(): void
    {
        $departmentId = $this->makeDepartment()->department_id;

        $this->from('/register')->post('/register', $this->registration($departmentId, ['privacy' => null]))
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['privacy' => 'Please agree to the Privacy Notice to create your account.']);
        $this->assertSame(0, UserAccount::count());

        $this->post('/register', $this->registration($departmentId))
            ->assertRedirect(route('verification.notice'));

        $account = UserAccount::where('email', 'juan@example.com')->firstOrFail();
        $this->assertSame(1, (int) $account->privacy_version);
        $this->assertNotNull($account->privacy_accepted_at);
        $this->assertDatabaseHas('audit_trail', [
            'user_id' => $account->user_id,
            'action' => 'Accepted privacy notice',
            'details' => 'Version 1, when registering',
        ]);
    }

    public function test_the_register_and_invite_pages_show_the_checkbox_and_link(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('name="privacy"', false)
            ->assertSee(route('privacy'), false);
        $this->get('/login')->assertOk()->assertSee(route('privacy'), false);
        $this->get('/')->assertOk()->assertSee(route('privacy'), false);
    }

    public function test_an_invited_account_agrees_when_setting_its_password(): void
    {
        Notification::fake();
        $department = $this->makeDepartment();
        $admin = $this->makeAdmin();
        $admin->forceFill(['privacy_version' => 1, 'privacy_accepted_at' => now()])->save();

        $this->actingAs($admin, 'admin')->post(route('admin.users.department'), [
            'full_name' => 'Lab Custodian', 'email' => 'lab@example.com', 'employee_id' => 'EMP-1', 'department_id' => $department->department_id,
        ]);
        $lab = \App\Models\DepartmentAccount::where('email', 'lab@example.com')->firstOrFail();
        $token = null;
        Notification::assertSentTo($lab, UserInviteNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });
        auth('admin')->logout();

        $form = ['token' => $token, 'email' => 'lab@example.com', 'password' => 'Lab2026pass', 'password_confirmation' => 'Lab2026pass'];
        $this->post(route('account.setup.store'), $form)
            ->assertSessionHasErrors(['privacy' => 'Please agree to the Privacy Notice to set up your account.']);
        $this->assertNull($lab->fresh()->privacy_accepted_at);

        $this->post(route('account.setup.store'), $form + ['privacy' => '1'])->assertRedirect(route('login'));
        $this->assertSame(1, (int) $lab->fresh()->privacy_version);
        $this->assertDatabaseHas('audit_trail', [
            'dept_acc_id' => $lab->dept_acc_id,
            'action' => 'Accepted privacy notice',
            'details' => 'Version 1, when setting up the account from an invite',
        ]);
    }

    public function test_an_existing_account_is_asked_once_then_continues_where_it_was_going(): void
    {
        $department = $this->makeDepartment();
        $student = $this->makeStudent($department, 'old@example.com');

        // Every signed-in page and data feed waits for the agreement.
        $this->actingAs($student, 'user')->get(route('user.history'))->assertRedirect(route('privacy.accept'));
        $this->getJson(route('user.dashboard.data'))->assertForbidden()
            ->assertJson(['success' => false, 'redirect' => route('privacy.accept')]);

        $this->get(route('privacy.accept'))->assertOk()
            ->assertSee('Please review our Privacy Notice')
            ->assertSee('Hi Juan')
            ->assertSee('I Agree and Continue')
            ->assertSee('Not now, sign me out');

        // Not ticked: asked again, nothing saved.
        $this->from(route('privacy.accept'))->post(route('privacy.accept.store'))
            ->assertRedirect(route('privacy.accept'))
            ->assertSessionHasErrors('agree');
        $this->assertNull($student->fresh()->privacy_accepted_at);

        // Agreed: back to the page they wanted, recorded once.
        $this->post(route('privacy.accept.store'), ['agree' => '1'])->assertRedirect(route('user.history'));
        $this->assertSame(1, (int) $student->fresh()->privacy_version);
        $this->assertSame(1, AuditTrail::where('action', 'Accepted privacy notice')->count());
        $this->assertDatabaseHas('audit_trail', ['user_id' => $student->user_id, 'details' => 'Version 1, at sign-in']);

        $this->get(route('user.history'))->assertOk();
        $this->get(route('privacy.accept'))->assertRedirect(route('user.dashboard'));
    }

    public function test_staff_are_asked_too(): void
    {
        $department = $this->makeDepartment();

        $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin.dashboard'))->assertRedirect(route('privacy.accept'));
        $this->post(route('privacy.accept.store'), ['agree' => '1'])->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk();
        auth('admin')->logout();

        $this->actingAs($this->makeLabPersonnel($department), 'dept')->get(route('department.equipment'))->assertRedirect(route('privacy.accept'));
        $this->post(route('privacy.accept.store'), ['agree' => '1'])->assertRedirect(route('department.equipment'));
    }

    public function test_a_new_version_of_the_notice_is_asked_again(): void
    {
        $student = $this->makeStudent($this->makeDepartment());
        $student->forceFill(['privacy_version' => 1, 'privacy_accepted_at' => now()])->save();

        $this->actingAs($student, 'user')->get(route('user.dashboard'))->assertOk();

        config(['equiptrack.privacy.version' => 2]);
        $this->get(route('user.dashboard'))->assertRedirect(route('privacy.accept'));
        $this->get(route('privacy.accept'))->assertSee('Our Privacy Notice has changed');
        $this->post(route('privacy.accept.store'), ['agree' => '1']);
        $this->assertSame(2, (int) $student->fresh()->privacy_version);
    }

    public function test_the_agreement_page_needs_someone_signed_in(): void
    {
        $this->get(route('privacy.accept'))->assertRedirect(route('login'));
        $this->post(route('privacy.accept.store'), ['agree' => '1'])->assertRedirect(route('login'));
    }

    public function test_profile_pages_link_the_notice_and_show_when_the_account_agreed(): void
    {
        $student = $this->makeStudent($this->makeDepartment());
        PrivacyConsent::record('user', $student, 'test');

        $this->actingAs($student, 'user')->get(route('user.profile'))->assertOk()
            ->assertSee(route('privacy'), false)
            ->assertSee('You agreed on Nov 3, 2026 (version 1)');
    }

    public function test_the_audit_trail_shows_the_agreement(): void
    {
        $student = $this->makeStudent($this->makeDepartment());
        PrivacyConsent::record('user', $student, 'at sign-in');

        $admin = $this->makeAdmin();
        $admin->forceFill(['privacy_version' => 1, 'privacy_accepted_at' => now()])->save();
        $row = collect($this->actingAs($admin, 'admin')->getJson(route('admin.audit.data'))->assertOk()->json('records'))
            ->firstWhere('action', 'Accepted Privacy Notice');

        $this->assertNotNull($row);
        $this->assertSame('Authentication', $row['module']);
        $this->assertSame('Version 1, at sign-in', $row['note']);
    }
}
