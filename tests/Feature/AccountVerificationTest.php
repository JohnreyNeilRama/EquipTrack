<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use App\Notifications\FacultyApprovalNotification;
use App\Notifications\UserInviteNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Phase 2: Students and Faculty prove they own their email address.
 *
 * - Self-registration: a verification link; /user pages wait until it is opened.
 * - Self-registered Faculty also wait for their own department's approval
 *   (Lab Personnel only; the Admin can't approve or activate them).
 * - Accounts added by staff: an invite to set their own password, which also
 *   confirms the email.
 */
class AccountVerificationTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    private Department $ccs;

    private Department $ccj;

    private DepartmentAccount $ccsLab;

    private DepartmentAccount $ccjLab;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->ccs = $this->makeDepartment();
        $this->ccj = $this->makeDepartment('College of Criminal Justice', 'CCJ');
        $this->ccsLab = $this->makeLabPersonnel($this->ccs, 'ccs.lab@example.com');
        $this->ccjLab = $this->makeLabPersonnel($this->ccj, 'ccj.lab@example.com', 'CCJ Custodian');
        $this->admin = $this->makeAdmin();
    }

    // ------------------------------------------------------------------
    // Self-registration + email verification
    // ------------------------------------------------------------------

    public function test_a_registered_student_must_verify_their_email_first(): void
    {
        $this->register('student', '20260001')->assertRedirect(route('verification.notice'));

        $ana = UserAccount::where('email', 'ana@example.com')->firstOrFail();
        $this->assertFalse($ana->hasVerifiedEmail());
        $this->assertSame('Active', $ana->status);
        $this->assertAuthenticatedAs($ana, 'user');
        Notification::assertSentTo($ana, VerifyEmailNotification::class);

        // Every /user page waits for the email.
        $this->get(route('user.dashboard'))->assertRedirect(route('verification.notice'));
        $this->get(route('verification.notice'))->assertOk()->assertSee('Check your email')->assertSee('ana@example.com');

        // Opening the link confirms it.
        $this->get(VerifyEmailNotification::verificationUrl($ana))->assertRedirect(route('user.dashboard'));
        $this->assertTrue($ana->fresh()->hasVerifiedEmail());
        $this->freshRequestUser();
        $this->get(route('user.dashboard'))->assertOk();
        $this->assertDatabaseHas('audit_trail', ['action' => 'Verified email address', 'affected_entity' => 'user_account:' . $ana->user_id]);
    }

    public function test_the_verification_link_works_when_signed_out_and_refuses_tampering(): void
    {
        $this->register('student', '20260001');
        $ana = UserAccount::where('email', 'ana@example.com')->firstOrFail();
        $this->post(route('logout'));

        // A link for another address (wrong hash) or without a valid signature does nothing.
        $tampered = str_replace(sha1('ana@example.com'), sha1('someone@example.com'), VerifyEmailNotification::verificationUrl($ana));
        $this->get($tampered)->assertRedirect(route('login'))->assertSessionHasErrors('email');
        $this->get(route('verification.verify', ['id' => $ana->user_id, 'hash' => sha1('ana@example.com')]))->assertRedirect(route('login'));
        $this->assertFalse($ana->fresh()->hasVerifiedEmail());

        $this->get(VerifyEmailNotification::verificationUrl($ana))->assertRedirect(route('login'))->assertSessionHas('status');
        $this->assertTrue($ana->fresh()->hasVerifiedEmail());
    }

    public function test_the_verification_email_can_be_resent(): void
    {
        $this->register('student', '20260001');
        $ana = UserAccount::where('email', 'ana@example.com')->firstOrFail();

        $this->from(route('verification.notice'))->post(route('verification.send'))->assertRedirect(route('verification.notice'))->assertSessionHas('status');
        Notification::assertSentToTimes($ana, VerifyEmailNotification::class, 2);
    }

    public function test_changing_the_email_needs_a_new_verification(): void
    {
        $juan = $this->makeStudent($this->ccs, 'juan@example.com');

        $juan->update(['email' => 'juan.new@example.com']);

        $this->assertFalse($juan->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($juan, VerifyEmailNotification::class);
        $this->actingAs($juan->fresh(), 'user')->get(route('user.dashboard'))->assertRedirect(route('verification.notice'));

        // The same address in other capitals is not a change.
        $maria = $this->makeStudent($this->ccs, 'maria@example.com');
        $maria->update(['email' => 'Maria@example.com']);
        $this->assertTrue($maria->fresh()->hasVerifiedEmail());
    }

    public function test_a_password_reset_link_also_confirms_the_email(): void
    {
        $this->register('student', '20260001');
        $ana = UserAccount::where('email', 'ana@example.com')->firstOrFail();
        $this->post(route('logout'));

        $token = Password::broker('users')->createToken($ana);
        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'ana@example.com',
            'password' => 'NewPass2026',
            'password_confirmation' => 'NewPass2026',
        ])->assertRedirect(route('login'));

        $this->assertTrue($ana->fresh()->hasVerifiedEmail());
    }

    // ------------------------------------------------------------------
    // Faculty approval (department only)
    // ------------------------------------------------------------------

    public function test_a_registered_teacher_waits_for_their_departments_approval(): void
    {
        $this->register('teacher', 'T-1001')->assertRedirect(route('verification.notice'));
        $ramon = UserAccount::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame(UserAccount::STATUS_PENDING_APPROVAL, $ramon->status);

        $this->get(VerifyEmailNotification::verificationUrl($ramon));
        $this->freshRequestUser();
        $this->get(route('user.dashboard'))->assertRedirect(route('account.pending'));
        $this->get(route('account.pending'))->assertOk()->assertSee('Waiting for approval');
        $this->postJson(route('user.borrow.store'), [])->assertForbidden();

        // Lab Personnel of the department see it in their bell and on the Users page.
        $bell = $this->actingAs($this->ccsLab, 'dept')->getJson(route('department.notifications'))->json('notifications');
        $this->assertContains('faculty-' . $ramon->user_id . '-pending', array_column($bell, 'key'));
        $row = collect($this->getJson(route('department.users.data'))->json('users'))->firstWhere('id', $ramon->user_id);
        $this->assertSame('Pending Approval', $row['status']);

        // Another department can't approve; their own can.
        $this->actingAs($this->ccjLab, 'dept')->postJson(route('department.users.approve'), ['user_id' => $ramon->user_id])->assertNotFound();
        $this->actingAs($this->ccsLab, 'dept')->postJson(route('department.users.approve'), ['user_id' => $ramon->user_id])->assertOk();

        $this->assertSame('Active', $ramon->fresh()->status);
        Notification::assertSentTo($ramon, FacultyApprovalNotification::class, fn ($n) => $n->approved);
        $this->assertDatabaseHas('audit_trail', ['action' => 'Approved faculty account', 'dept_acc_id' => $this->ccsLab->dept_acc_id]);

        $this->actingAs($ramon->fresh(), 'user')->get(route('user.dashboard'))->assertOk();
    }

    public function test_the_admin_cannot_activate_a_teacher_waiting_for_approval(): void
    {
        $ramon = $this->makeFaculty($this->ccs, 'ramon@example.com');
        $ramon->update(['status' => UserAccount::STATUS_PENDING_APPROVAL]);

        $this->actingAs($this->admin, 'admin')->post(route('admin.users.toggle'), [
            'target_user_id' => $ramon->user_id,
            'target_user_type' => 'user_account',
            'new_status' => 'Active',
        ])->assertSessionHas('serverMsgType', 'error');

        // Nor can Lab Personnel skip the approval with Activate.
        $this->actingAs($this->ccsLab, 'dept')->postJson(route('department.users.status'), ['user_id' => $ramon->user_id, 'status' => 'Active'])->assertStatus(400);

        $this->assertSame(UserAccount::STATUS_PENDING_APPROVAL, $ramon->fresh()->status);
    }

    public function test_a_teachers_details_can_be_edited_only_after_approval(): void
    {
        $ramon = $this->makeFaculty($this->ccs, 'ramon@example.com', ['first_name' => 'Ramon']);
        $ramon->update(['status' => UserAccount::STATUS_PENDING_APPROVAL]);

        $edit = fn () => $this->actingAs($this->ccsLab, 'dept')->postJson(route('department.users.update'), [
            'user_id' => $ramon->user_id,
            'first_name' => 'Changed',
            'last_name' => 'Reyes',
            'faculty_id_number' => 'T-' . $ramon->user_id,
            'teaching_license_no' => 'PRC-' . $ramon->user_id,
            'email' => 'ramon@example.com',
            'address' => 'Cebu City',
        ]);

        // Pending: refused, nothing saved.
        $edit()->assertForbidden()->assertJsonPath('success', false);
        $this->assertSame('Ramon', $ramon->fresh('facultyMember')->facultyMember->first_name);

        // Approved: editing works.
        $this->postJson(route('department.users.approve'), ['user_id' => $ramon->user_id])->assertOk();
        $edit()->assertOk();
        $this->assertSame('Changed', $ramon->fresh('facultyMember')->facultyMember->first_name);
    }

    public function test_the_admin_cannot_edit_a_teacher_until_the_department_approves(): void
    {
        $ramon = $this->makeFaculty($this->ccs, 'ramon@example.com', ['first_name' => 'Ramon']);
        $ramon->update(['status' => UserAccount::STATUS_PENDING_APPROVAL]);

        $edit = fn () => $this->actingAs($this->admin, 'admin')->post(route('admin.users.update'), [
            'user_id' => $ramon->user_id,
            'user_type' => 'user_account',
            'first_name' => 'Changed',
            'last_name' => 'Reyes',
            'faculty_id_number' => 'T-' . $ramon->user_id,
            'teaching_license_no' => 'PRC-' . $ramon->user_id,
            'email' => 'ramon@example.com',
            'address' => 'Cebu City',
            'department_id' => $this->ccs->department_id,
        ]);

        // Pending: refused, nothing saved.
        $edit()->assertRedirect(route('admin.users'))->assertSessionHas('serverMsgType', 'error');
        $this->assertSame('Ramon', $ramon->fresh('facultyMember')->facultyMember->first_name);

        // Approved by the department: the Admin can edit.
        $this->actingAs($this->ccsLab, 'dept')->postJson(route('department.users.approve'), ['user_id' => $ramon->user_id])->assertOk();
        $edit()->assertSessionHas('serverMsgType', 'success');
        $this->assertSame('Changed', $ramon->fresh('facultyMember')->facultyMember->first_name);
    }

    public function test_the_admin_reviews_teachers_only_when_their_department_has_no_active_account(): void
    {
        $ramon = $this->makeFaculty($this->ccs, 'ramon@example.com');
        $ramon->update(['status' => UserAccount::STATUS_PENDING_APPROVAL]);
        $asAdmin = fn (string $route, array $data = []) => $this->actingAs($this->admin, 'admin')
            ->post(route($route), ['user_id' => $ramon->user_id] + $data);

        // The department can review: the Admin can't, and isn't offered it.
        $asAdmin('admin.users.approve-faculty')->assertSessionHas('serverMsg', fn ($m) => str_contains($m, 'Lab Personnel'));
        $this->assertSame(UserAccount::STATUS_PENDING_APPROVAL, $ramon->fresh()->status);
        $row = collect($this->getJson(route('admin.users.data', ['status' => 'pending']))->json('users'))->firstWhere('id', $ramon->user_id);
        $this->assertFalse($row['admin_can_review']);

        // The department has no active account (here: deactivated earlier).
        $this->ccsLab->update(['status' => 'Deactivated']);
        $row = collect($this->getJson(route('admin.users.data', ['status' => 'pending']))->json('users'))->firstWhere('id', $ramon->user_id);
        $this->assertTrue($row['admin_can_review']);

        $asAdmin('admin.users.approve-faculty')->assertSessionHas('serverMsgType', 'success');
        $this->assertSame('Active', $ramon->fresh()->status);
        Notification::assertSentTo($ramon, FacultyApprovalNotification::class, fn ($n) => $n->approved);
        $this->assertDatabaseHas('audit_trail', ['action' => 'Approved faculty account', 'admin_id' => $this->admin->admin_id]);
    }

    public function test_the_admin_backup_decline_needs_a_reason_and_emails_it(): void
    {
        $this->ccsLab->update(['status' => 'Deactivated']);
        $ramon = $this->makeFaculty($this->ccs, 'ramon@example.com');
        $ramon->update(['status' => UserAccount::STATUS_PENDING_APPROVAL]);

        $this->actingAs($this->admin, 'admin')->post(route('admin.users.decline-faculty'), ['user_id' => $ramon->user_id])
            ->assertSessionHasErrors('reason');
        $this->assertNotNull($ramon->fresh());

        $this->post(route('admin.users.decline-faculty'), ['user_id' => $ramon->user_id, 'reason' => 'Not on the faculty list.'])
            ->assertSessionHas('serverMsgType', 'success');
        $this->assertNull($ramon->fresh());
        Notification::assertSentTo($ramon, FacultyApprovalNotification::class, fn ($n) => !$n->approved && $n->reason === 'Not on the faculty list.');
    }

    public function test_pending_teachers_cannot_be_deleted_or_stranded(): void
    {
        $ramon = $this->makeFaculty($this->ccs, 'ramon@example.com');
        $ramon->update(['status' => UserAccount::STATUS_PENDING_APPROVAL]);

        // No silent decline: deleting a pending registration is refused.
        $this->actingAs($this->admin, 'admin')->post(route('admin.users.delete'), ['user_id' => $ramon->user_id, 'user_type' => 'faculty'])
            ->assertSessionHas('serverMsg', fn ($m) => str_contains($m, 'Decline'));
        $this->assertNotNull($ramon->fresh());

        // Its department's only account can't be deactivated while he waits.
        $this->post(route('admin.users.toggle'), [
            'target_user_id' => $this->ccsLab->dept_acc_id + 100000,
            'target_user_type' => 'department',
            'new_status' => 'Deactivated',
        ])->assertSessionHas('serverMsg', fn ($m) => str_contains($m, '1 Faculty registration is waiting'));
        $this->assertSame('Active', $this->ccsLab->fresh()->status);

        // Another department's account still can be.
        $this->post(route('admin.users.toggle'), [
            'target_user_id' => $this->ccjLab->dept_acc_id + 100000,
            'target_user_type' => 'department',
            'new_status' => 'Deactivated',
        ])->assertSessionHas('serverMsgType', 'success');
        $this->assertSame('Deactivated', $this->ccjLab->fresh()->status);
    }

    public function test_a_declined_registration_cannot_be_edited(): void
    {
        $ramon = $this->makeFaculty($this->ccs, 'ramon@example.com');
        $ramon->update(['status' => UserAccount::STATUS_PENDING_APPROVAL]);

        $this->actingAs($this->ccsLab, 'dept')->postJson(route('department.users.decline'), ['user_id' => $ramon->user_id, 'reason' => 'Not faculty.'])->assertOk();

        $this->postJson(route('department.users.update'), ['user_id' => $ramon->user_id, 'first_name' => 'Changed'])->assertNotFound();
    }

    public function test_declining_needs_a_reason_and_removes_the_registration(): void
    {
        $ramon = $this->makeFaculty($this->ccs, 'ramon@example.com');
        $ramon->update(['status' => UserAccount::STATUS_PENDING_APPROVAL]);

        $this->actingAs($this->ccsLab, 'dept')->postJson(route('department.users.decline'), ['user_id' => $ramon->user_id])->assertUnprocessable();
        $this->assertNotNull($ramon->fresh());

        $this->postJson(route('department.users.decline'), ['user_id' => $ramon->user_id, 'reason' => 'Not faculty of CCS.'])->assertOk();

        $this->assertNull($ramon->fresh());
        Notification::assertSentTo($ramon, FacultyApprovalNotification::class, fn ($n) => !$n->approved && $n->reason === 'Not faculty of CCS.');
        $this->assertDatabaseHas('audit_trail', ['action' => 'Declined faculty account']);
    }

    public function test_a_department_account_with_teachers_waiting_cannot_be_deleted(): void
    {
        $ramon = $this->makeFaculty($this->ccs, 'ramon@example.com');
        $ramon->update(['status' => UserAccount::STATUS_PENDING_APPROVAL]);

        $this->actingAs($this->admin, 'admin')->post(route('admin.users.delete'), [
            'user_id' => $this->ccsLab->dept_acc_id + 100000,
            'user_type' => 'department',
        ])->assertSessionHas('serverMsg', fn (string $m) => str_contains($m, 'waiting for approval'));

        $this->assertNotNull($this->ccsLab->fresh());
    }

    // ------------------------------------------------------------------
    // Accounts added by staff: invite to set a password
    // ------------------------------------------------------------------

    public function test_a_student_added_by_staff_sets_their_own_password_from_the_invite(): void
    {
        $this->actingAs($this->ccsLab, 'dept')->postJson(route('department.users.student'), [
            'first_name' => 'Ana', 'last_name' => 'Santos', 'id_number' => '20260001', 'year_level' => '1st Year',
            'email' => 'ana@example.com', 'address' => 'Cebu City',
        ])->assertOk()->assertJsonPath('message', fn (string $m) => str_contains($m, 'invite'));

        $ana = UserAccount::where('email', 'ana@example.com')->firstOrFail();
        $this->assertSame('invited', $ana->accountState());

        $token = null;
        Notification::assertSentTo($ana, UserInviteNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        // Nobody can sign in before a password is chosen.
        $this->app['auth']->forgetGuards();
        $this->get(route('account.setup', ['token' => $token, 'email' => 'ana@example.com']))->assertOk()->assertSee('Set Password');

        $this->post(route('account.setup.store'), [
            'token' => $token, 'email' => 'ana@example.com', 'password' => 'Study2026', 'password_confirmation' => 'Study2026',
        ])->assertRedirect(route('login'));

        $ana = $ana->fresh();
        $this->assertTrue($ana->hasVerifiedEmail());
        $this->assertSame('active', $ana->accountState());
        $this->assertDatabaseHas('audit_trail', ['action' => 'Set password from invite']);

        $this->post('/login', ['email' => 'ana@example.com', 'password' => 'Study2026'])->assertRedirect(route('user.dashboard'));

        // The link works only once.
        $this->post(route('logout'));
        $this->get(route('account.setup', ['token' => $token, 'email' => 'ana@example.com']))->assertSee('Link expired');
    }

    public function test_staff_can_resend_an_invite_after_fixing_the_email(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.users.student'), [
            'first_name' => 'Ana', 'last_name' => 'Santos', 'id_number' => '20260001', 'year_level' => '1st Year',
            'email' => 'ana@exmple.com', 'address' => 'Cebu City', 'department' => $this->ccs->department_name,
        ])->assertSessionHas('serverMsgType', 'success');

        $ana = UserAccount::where('email', 'ana@exmple.com')->firstOrFail();

        // Typo fixed: no verification email (no password yet); staff resend the invite.
        $ana->update(['email' => 'ana@example.com']);
        Notification::assertNotSentTo($ana, VerifyEmailNotification::class);

        $this->post(route('admin.users.invite'), ['user_id' => $ana->user_id])->assertSessionHas('serverMsgType', 'success');
        Notification::assertSentToTimes($ana, UserInviteNotification::class, 2);

        // Again right away: refused with a message (one invite a minute per address).
        $this->post(route('admin.users.invite'), ['user_id' => $ana->user_id])->assertSessionHas('serverMsgType', 'error');

        $this->travel(2)->minutes();
        $this->post(route('admin.users.invite'), ['user_id' => $ana->user_id])->assertSessionHas('serverMsgType', 'success');
        Notification::assertSentToTimes($ana, UserInviteNotification::class, 3);
    }

    public function test_an_expired_invite_link_is_refused(): void
    {
        $this->actingAs($this->ccsLab, 'dept')->postJson(route('department.users.student'), [
            'first_name' => 'Ana', 'last_name' => 'Santos', 'id_number' => '20260001', 'year_level' => '1st Year',
            'email' => 'ana@example.com', 'address' => 'Cebu City',
        ])->assertOk();

        $ana = UserAccount::where('email', 'ana@example.com')->firstOrFail();
        $token = null;
        Notification::assertSentTo($ana, UserInviteNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        $this->travel(8)->days();
        $this->app['auth']->forgetGuards();

        $this->post(route('account.setup.store'), [
            'token' => $token, 'email' => 'ana@example.com', 'password' => 'Study2026', 'password_confirmation' => 'Study2026',
        ])->assertSessionHas('link_invalid');

        $this->assertFalse($ana->fresh()->hasVerifiedEmail());
    }

    // ------------------------------------------------------------------

    /**
     * Within one test the signed-in account stays cached in memory between
     * requests; a real browser request loads it fresh from the database.
     */
    private function freshRequestUser(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function register(string $role, string $id)
    {
        return $this->post('/register', [
            'role' => $role,
            'id_number' => $id,
            'first_name' => 'Ana',
            'last_name' => 'Santos',
            'year_level' => '1',
            'department_id' => $this->ccs->department_id,
            'email' => 'ana@example.com',
            'address' => 'Cebu City',
            'password' => 'Study2026',
        ] + ($role === 'teacher' ? ['teaching_license_no' => 'PRC-' . $id] : []));
    }
}
