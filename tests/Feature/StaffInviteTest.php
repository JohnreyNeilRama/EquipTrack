<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use App\Notifications\AdminEmailChangedNotification;
use App\Notifications\UserInviteNotification;
use App\Services\FacultyApproval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * #10: Department (Lab Personnel) and Admin accounts added by an Admin get an
 * invite email to set their own password, like Students and Faculty added by
 * staff. The Admin never chooses or knows it.
 */
class StaffInviteTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    private Admin $admin;

    private Department $ccs;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->admin = $this->makeAdmin();
        $this->ccs = $this->makeDepartment();
    }

    private function addLab(array $input = [])
    {
        return $this->actingAs($this->admin, 'admin')->post(route('admin.users.department'), $input + [
            'full_name' => 'Dr. Christian Ferrer',
            'employee_id' => 'EMP-2026-001',
            'email' => 'ferrer@example.com',
            'department_id' => $this->ccs->department_id,
        ]);
    }

    private function inviteToken(object $account): string
    {
        $token = null;
        Notification::assertSentTo($account, UserInviteNotification::class, function ($n) use (&$token) {
            $token = $n->token;

            return true;
        });

        return $token;
    }

    public function test_a_new_lab_personnel_account_sets_its_own_password_from_the_invite(): void
    {
        $this->addLab()->assertSessionHas('serverMsg', fn ($m) => str_contains($m, 'invite to set their password was emailed to ferrer@example.com'));

        $lab = DepartmentAccount::where('email', 'ferrer@example.com')->firstOrFail();
        $this->assertTrue($lab->hasPendingInvite());

        // Shown as Invite Sent on the Users page.
        $row = collect($this->getJson(route('admin.users.data', ['role' => 'department']))->json('users'))->firstWhere('email', 'ferrer@example.com');
        $this->assertSame('Invite Sent', $row['status']);
        $this->assertSame('invited', $row['state']);

        // Until then the department can't review its teachers itself.
        $this->assertFalse(FacultyApproval::departmentHasActiveAccount($this->ccs->department_id));

        $token = $this->inviteToken($lab);
        $this->app['auth']->forgetGuards();
        $this->get(route('account.setup', ['token' => $token, 'email' => 'ferrer@example.com']))->assertOk()->assertSee('Dr. Christian Ferrer');
        $this->post(route('account.setup.store'), [
            'token' => $token, 'email' => 'ferrer@example.com', 'password' => 'Lab2026pass', 'password_confirmation' => 'Lab2026pass',
        ])->assertRedirect(route('login'));

        $this->assertTrue($lab->fresh()->isSetUp());
        $this->assertTrue(FacultyApproval::departmentHasActiveAccount($this->ccs->department_id));
        $this->assertDatabaseHas('audit_trail', ['action' => 'Set password from invite', 'affected_entity' => 'department_account:' . $lab->dept_acc_id]);

        $this->post('/login', ['email' => 'ferrer@example.com', 'password' => 'Lab2026pass'])->assertRedirect(route('department.dashboard'));
    }

    public function test_the_admin_can_resend_staff_invites_but_not_to_accounts_already_set_up(): void
    {
        $this->addLab();
        $lab = DepartmentAccount::where('email', 'ferrer@example.com')->firstOrFail();

        $resend = fn (int $id, string $type) => $this->actingAs($this->admin, 'admin')
            ->post(route('admin.users.invite'), ['user_id' => $id, 'user_type' => $type]);

        $this->travel(2)->minutes();
        $resend($lab->dept_acc_id + 100000, 'department')->assertSessionHas('serverMsgType', 'success');
        Notification::assertSentToTimes($lab, UserInviteNotification::class, 2);

        // An existing, set-up account gets no invite.
        $other = $this->makeLabPersonnel($this->makeDepartment('College of Nursing', 'CON'), 'nurse.lab@example.com');
        $resend($other->dept_acc_id + 100000, 'department')->assertSessionHas('serverMsg', fn ($m) => str_contains($m, 'already set up'));
    }

    public function test_a_new_admin_can_sign_in_only_after_setting_a_password_from_the_invite(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.users.admin'), [
            'name' => 'Maria Santos', 'email' => 'maria@example.com',
        ])->assertSessionHas('serverMsg', fn ($m) => str_contains($m, 'invite to set their password was emailed to maria@example.com'));

        $maria = Admin::where('email', 'maria@example.com')->firstOrFail();
        $this->assertTrue($maria->hasPendingInvite());
        $row = collect($this->getJson(route('admin.users.data', ['role' => 'administrator']))->json('users'))->firstWhere('email', 'maria@example.com');
        $this->assertSame('Invite Sent', $row['status']);

        $token = $this->inviteToken($maria);
        $this->app['auth']->forgetGuards();

        // No password was ever given out, so nothing works before the invite.
        $this->post('/login', ['email' => 'maria@example.com', 'password' => 'Admin2026'])->assertSessionHasErrors('email');

        $this->post(route('account.setup.store'), [
            'token' => $token, 'email' => 'maria@example.com', 'password' => 'Admin2026', 'password_confirmation' => 'Admin2026',
        ])->assertRedirect(route('login'));
        $this->assertTrue($maria->fresh()->isSetUp());

        // She can also sign in with her username (the same as her email).
        $this->post('/login', ['email' => 'maria@example.com', 'password' => 'Admin2026'])->assertRedirect(route('admin.dashboard'));
    }

    public function test_fixing_a_pending_admins_mistyped_email_sends_no_takeover_alert(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.users.admin'), [
            'name' => 'Maria Santos', 'email' => 'maria@exmple.com',
        ]);
        $maria = Admin::where('email', 'maria@exmple.com')->firstOrFail();

        $this->post(route('admin.users.update'), [
            'user_id' => $maria->admin_id + 200000,
            'user_type' => 'admin',
            'name' => 'Maria Santos',
            'email' => 'maria@example.com',
            'current_password' => 'Admin2026',
        ])->assertSessionHas('serverMsgType', 'success');

        $this->assertSame('maria@example.com', $maria->fresh()->email);
        Notification::assertSentOnDemandTimes(AdminEmailChangedNotification::class, 0);
    }

    public function test_a_new_lab_personnel_member_takes_over_the_department_account(): void
    {
        $old = $this->makeLabPersonnel($this->ccs, 'old.lab@example.com', 'Old Custodian');
        $edit = fn (array $extra) => $this->actingAs($this->admin, 'admin')->post(route('admin.users.update'), [
            'user_id' => $old->dept_acc_id + 100000,
            'user_type' => 'department',
            'full_name' => 'New Custodian',
            'employee_id' => 'EMP-NEW',
            'department_id' => $this->ccs->department_id,
            'email' => 'new.lab@example.com',
        ] + $extra);

        // Only correcting details: the password keeps working, no invite.
        $edit([])->assertSessionHas('serverMsgType', 'success');
        $this->assertTrue($old->fresh()->isSetUp());
        Notification::assertNothingSent();

        // A new person: the old password stops working and the new email gets the link.
        $edit(['new_holder' => '1'])
            ->assertSessionHas('serverMsg', fn ($m) => str_contains($m, 'previous password no longer works') && str_contains($m, 'new.lab@example.com'));

        $lab = $old->fresh();
        $this->assertTrue($lab->hasPendingInvite());
        $this->app['auth']->forgetGuards();
        $this->post('/login', ['email' => 'new.lab@example.com', 'password' => 'Lab2026pass'])->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_trail', ['action' => 'Handed over department account', 'affected_entity' => 'department_account:' . $lab->dept_acc_id]);

        $token = $this->inviteToken($lab);
        $this->post(route('account.setup.store'), [
            'token' => $token, 'email' => 'new.lab@example.com', 'password' => 'NewLab2026', 'password_confirmation' => 'NewLab2026',
        ])->assertRedirect(route('login'));
        $this->post('/login', ['email' => 'new.lab@example.com', 'password' => 'NewLab2026'])->assertRedirect(route('department.dashboard'));
    }

    public function test_a_deactivated_department_account_must_be_activated_before_a_handover(): void
    {
        $old = $this->makeLabPersonnel($this->ccs, 'old.lab@example.com', 'Old Custodian', ['status' => 'Deactivated']);

        $this->actingAs($this->admin, 'admin')->post(route('admin.users.update'), [
            'user_id' => $old->dept_acc_id + 100000,
            'user_type' => 'department',
            'full_name' => 'New Custodian',
            'employee_id' => 'EMP-NEW',
            'department_id' => $this->ccs->department_id,
            'email' => 'new.lab@example.com',
            'new_holder' => '1',
        ])->assertSessionHas('serverMsg', fn ($m) => str_contains($m, 'Activate this department account first'));

        $this->assertSame('old.lab@example.com', $old->fresh()->email);
        Notification::assertNothingSent();
    }

    public function test_existing_staff_accounts_are_already_set_up(): void
    {
        $this->assertTrue($this->admin->isSetUp());
        $this->assertTrue($this->makeLabPersonnel($this->ccs)->isSetUp());
        $this->assertSame(0, UserAccount::count());
    }
}
