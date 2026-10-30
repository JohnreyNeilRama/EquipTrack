<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\Penalty;
use App\Services\AuditCatalog;
use App\Services\PasswordResetAccounts;
use App\Services\UserAccountEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Administrators manage other administrator accounts from the Users page:
 * add, edit, activate/deactivate and delete, but never their own account.
 * Admin rows use page ids offset by 200000.
 */
class AdminAccountManagementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private const OFFSET = 200000;

    private Admin $me;

    protected function setUp(): void
    {
        parent::setUp();

        $this->me = $this->makeAdmin('admin@example.com', 'System Admin');
    }

    private function addAdmin(array $input = [])
    {
        return $this->actingAs($this->me, 'admin')->post(route('admin.users.admin'), $input + [
            'form_type' => 'admin',
            'name' => 'Maria Santos',
            'employee_id' => 'ADM-0002',
            'email' => 'maria@example.com',
            'password' => 'Admin2026',
        ]);
    }

    public function test_an_admin_adds_another_administrator(): void
    {
        $this->addAdmin()
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('serverMsgType', 'success');

        $maria = Admin::where('email', 'maria@example.com')->firstOrFail();
        $this->assertSame('Active', $maria->status);
        $this->assertSame('maria@example.com', $maria->username);   // the email is also the username
        $this->assertTrue(Hash::check('Admin2026', $maria->password));

        $audit = AuditTrail::where('affected_entity', 'admin:' . $maria->admin_id)->where('action', 'Added admin account')->firstOrFail();
        $this->assertSame($this->me->admin_id, (int) $audit->admin_id);
        $this->assertSame('Added User', AuditCatalog::classify($audit->action, $audit->affected_entity)['label']);
    }

    public function test_the_new_administrator_can_sign_in_and_last_online_is_recorded(): void
    {
        $this->addAdmin();
        $this->app['auth']->forgetGuards();

        $this->post('/login', ['email' => 'maria@example.com', 'password' => 'Admin2026'])
            ->assertRedirect(route('admin.dashboard'));

        $this->assertNotNull(Admin::where('email', 'maria@example.com')->value('last_online'));
    }

    public function test_invalid_administrator_details_are_refused(): void
    {
        $this->makeStudent($this->makeDepartment(), 'student@example.com');
        $this->makeLabPersonnel(null, 'lab@example.com');
        $this->addAdmin();

        $cases = [
            "a student's email" => [['email' => 'student@example.com'], 'already used by another EquipTrack account'],
            // Same spelling: MySQL/MariaDB also match other capitalisations, the SQLite test database does not.
            "Lab Personnel's email" => [['email' => 'lab@example.com'], 'already used by another EquipTrack account'],
            'an existing administrator email' => [['email' => 'maria@example.com'], 'already exists'],
            'a weak password' => [['email' => 'x@example.com', 'password' => 'short'], 'at least 8 characters'],
            'no name' => [['email' => 'y@example.com', 'name' => ''], "Enter the administrator's full name."],
        ];

        foreach ($cases as $label => [$override, $expected]) {
            $before = Admin::count();
            $this->addAdmin($override)->assertSessionHasErrors();
            $this->assertSame($before, Admin::count(), $label);
            $this->assertStringContainsString($expected, $this->firstError(), $label);
        }
    }

    public function test_an_admin_edits_another_administrator_and_the_username_follows_the_email(): void
    {
        $maria = $this->makeAdmin('maria@example.com', 'Maria Santos');

        $this->actingAs($this->me, 'admin')->post(route('admin.users.update'), [
            'form_type' => 'edit',
            'user_id' => self::OFFSET + $maria->admin_id,
            'user_type' => 'admin',
        ] + array_merge(UserAccountEditor::values($maria), ['name' => 'Maria L. Santos', 'email' => 'maria.santos@example.com']))
            ->assertSessionHas('serverMsg', 'Account details updated. Changed: Full name, Email.');

        $maria->refresh();
        $this->assertSame('Maria L. Santos', $maria->name);
        $this->assertSame('maria.santos@example.com', $maria->email);
        $this->assertSame('maria.santos@example.com', $maria->username);
    }

    public function test_an_admin_cannot_deactivate_or_delete_their_own_account(): void
    {
        $this->actingAs($this->me, 'admin')->post(route('admin.users.toggle'), [
            'target_user_id' => self::OFFSET + $this->me->admin_id,
            'target_user_type' => 'admin',
            'new_status' => 'Deactivated',
        ])->assertSessionHas('serverMsgType', 'error');
        $this->assertSame('Active', $this->me->fresh()->status);

        $this->actingAs($this->me, 'admin')->post(route('admin.users.delete'), [
            'user_id' => self::OFFSET + $this->me->admin_id,
            'user_type' => 'admin',
        ])->assertSessionHas('serverMsgType', 'error');
        $this->assertNotNull($this->me->fresh());
    }

    public function test_a_deactivated_administrator_is_locked_out_until_activated_again(): void
    {
        $maria = $this->makeAdmin('maria@example.com', 'Maria Santos', 'Admin2026');

        $this->actingAs($this->me, 'admin')->post(route('admin.users.toggle'), [
            'target_user_id' => self::OFFSET + $maria->admin_id,
            'target_user_type' => 'admin',
            'new_status' => 'Deactivated',
        ])->assertSessionHas('serverMsgType', 'success');
        $this->assertSame('Deactivated', $maria->fresh()->status);
        $this->assertTrue(AuditTrail::where('affected_entity', 'admin:' . $maria->admin_id)->where('action', 'Deactivated user account')->exists());
        $this->assertTrue(PasswordResetAccounts::isDeactivated($maria->fresh()));

        // Already signed in: signed out on the next page.
        $this->actingAs($maria->fresh(), 'admin')->get(route('admin.dashboard'))->assertRedirect();
        $this->assertFalse($this->app['auth']->guard('admin')->check());

        // Signing in again is refused.
        $this->post('/login', ['email' => 'maria@example.com', 'password' => 'Admin2026'])->assertSessionHasErrors('email');
        $this->assertStringContainsString('deactivated', $this->firstError());

        $this->actingAs($this->me, 'admin')->post(route('admin.users.toggle'), [
            'target_user_id' => self::OFFSET + $maria->admin_id,
            'target_user_type' => 'admin',
            'new_status' => 'Active',
        ]);
        $this->assertSame('Active', $maria->fresh()->status);
    }

    public function test_deleting_an_administrator_keeps_the_records_they_made(): void
    {
        $maria = $this->makeAdmin('maria@example.com', 'Maria Santos');
        $department = $this->makeDepartment();
        $loan = $this->makeLoan($this->makeStudent($department), $this->makeEquipment($department), '2026-10-22', ['status' => 'Returned', 'return_date' => '2026-10-22']);
        $penalty = Penalty::create([
            'transaction_id' => $loan->transaction_id,
            'user_id' => $loan->request->user_id,
            'type' => 'Damaged',
            'amount' => 100,
            'description' => 'Cracked screen',
            'status' => 'Pending',
            'recorded_by_admin_id' => $maria->admin_id,
            'recorded_at' => now(),
        ]);

        $this->actingAs($this->me, 'admin')->post(route('admin.users.delete'), [
            'user_id' => self::OFFSET + $maria->admin_id,
            'user_type' => 'admin',
        ])->assertSessionHas('serverMsg', 'Administrator account deleted successfully.');

        $this->assertNull(Admin::find($maria->admin_id));
        $this->assertNotNull($penalty->fresh());
        $this->assertNull($penalty->fresh()->recorded_by_admin_id);
        $this->assertTrue(AuditTrail::where('affected_entity', 'admin:' . $maria->admin_id)->where('action', 'Deleted admin account')->exists());
    }

    public function test_the_users_page_lists_administrators_and_marks_your_own_account(): void
    {
        $this->makeAdmin('maria@example.com', 'Maria Santos');

        $html = $this->actingAs($this->me, 'admin')->get(route('admin.users'))->assertOk()->getContent();

        $this->assertStringContainsString('id="addAdminBtn"', $html);
        $this->assertStringContainsString('value="administrator"', $html);
        preg_match('/let users = (\[.*?\]);/s', $html, $m);
        $rows = collect(json_decode($m[1] ?? '[]', true));
        $mine = $rows->firstWhere('id', self::OFFSET + $this->me->admin_id);
        $this->assertSame('admin', $mine['db_type']);
        $this->assertTrue($mine['is_self']);
        $this->assertFalse($rows->firstWhere('email', 'maria@example.com')['is_self']);
    }

    public function test_lab_personnel_cannot_add_administrators(): void
    {
        $lab = $this->makeLabPersonnel($this->makeDepartment());

        $this->actingAs($lab, 'dept')->post(route('admin.users.admin'), [
            'name' => 'X', 'email' => 'x@example.com', 'password' => 'Admin2026',
        ])->assertRedirect();

        $this->assertNull(Admin::where('email', 'x@example.com')->first());
    }
}
