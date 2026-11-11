<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\UserAccount;
use App\Support\LoginLockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Sign-ins that don't succeed are recorded in the Audit Trail: wrong
 * password, unknown account (masked), the lock after 10 failures, and a
 * deactivated account. Nobody is recorded as the actor, so these never count
 * as an account's activity.
 */
class FailedLoginAuditTest extends TestCase
{
    use RefreshDatabase;

    private UserAccount $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);

        $this->student = UserAccount::create([
            'email_verified_at' => now(),
            'role' => 'Student',
            'email' => 'student@example.com',
            'password' => Hash::make('Secret123'),
            'status' => 'Active',
        ]);
    }

    private function attempt(string $login, string $password): void
    {
        $this->from('/login')->post('/login', ['email' => $login, 'password' => $password]);
    }

    private function signInProblems()
    {
        return AuditTrail::query()
            ->where(fn ($q) => $q->where('action', 'like', 'Failed login%')
                ->orWhere('action', 'like', 'Account locked%')
                ->orWhere('action', 'like', 'Blocked login%'))
            ->orderBy('audit_id')
            ->get();
    }

    public function test_a_wrong_password_is_recorded_against_the_targeted_account_without_an_actor(): void
    {
        $this->attempt('student@example.com', 'wrong-password');

        $row = $this->signInProblems()->sole();
        $this->assertSame('Failed login (wrong password)', $row->action);
        $this->assertSame('user_account:' . $this->student->user_id, $row->affected_entity);
        $this->assertSame('Wrong password for student@example.com', $row->details);
        $this->assertNull($row->user_id);
        $this->assertNull($row->admin_id);
        $this->assertNull($row->dept_acc_id);
        $this->assertNotEmpty($row->ip_address);
    }

    public function test_an_unknown_name_is_recorded_masked(): void
    {
        $this->attempt('jdelacruz@gmail.com', 'whatever');
        $this->attempt('MyPassword123', 'whatever');

        $rows = $this->signInProblems();
        $this->assertSame(['Failed login (unknown account)', 'Failed login (unknown account)'], $rows->pluck('action')->all());
        $this->assertSame(['auth:unknown', 'auth:unknown'], $rows->pluck('affected_entity')->all());
        $this->assertSame('No account matches jd***@gmail.com', $rows[0]->details);
        $this->assertSame('No account matches My***', $rows[1]->details);
        $this->assertFalse(AuditTrail::where('details', 'like', '%MyPassword123%')->exists());
    }

    public function test_the_tenth_failure_records_the_lock_once_and_attempts_while_locked_are_not_logged(): void
    {
        for ($i = 0; $i < LoginLockout::MAX_FAILURES; $i++) {
            $this->attempt('student@example.com', 'wrong-password');
        }
        $this->attempt('student@example.com', 'Secret123');
        $this->attempt('student@example.com', 'wrong-again');

        $rows = $this->signInProblems();
        $this->assertSame(LoginLockout::MAX_FAILURES, $rows->where('action', 'Failed login (wrong password)')->count());
        $locked = $rows->where('action', 'Account locked')->sole();
        $this->assertSame('Sign-in for student@example.com locked for 15 minutes after 10 failed attempts', $locked->details);
        $this->assertCount(LoginLockout::MAX_FAILURES + 1, $rows);
    }

    public function test_a_deactivated_account_with_the_right_password_is_recorded_as_blocked(): void
    {
        $this->student->update(['status' => 'Deactivated']);

        $this->attempt('student@example.com', 'Secret123');

        $row = $this->signInProblems()->sole();
        $this->assertSame('Blocked login (deactivated account)', $row->action);
        $this->assertSame('Correct password for student@example.com, but the account is deactivated', $row->details);
    }

    public function test_a_successful_sign_in_records_no_problem(): void
    {
        $this->attempt('student@example.com', 'Secret123');

        $this->assertCount(0, $this->signInProblems());
        $this->assertTrue(AuditTrail::where('action', 'Login')->where('user_id', $this->student->user_id)->exists());
    }

    public function test_the_audit_trail_shows_them_as_visitor_attempts_that_are_not_account_activity(): void
    {
        $admin = Admin::create([
            'name' => 'System Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
        $this->attempt('student@example.com', 'wrong-password');
        $this->attempt('nobody@example.com', 'wrong-password');

        $feed = $this->actingAs($admin, 'admin')->getJson(route('admin.audit.data', ['module' => 'Authentication']))->assertOk()->json();
        $records = collect($feed['records']);

        $wrong = $records->firstWhere('note', 'Wrong password for student@example.com');
        $this->assertSame('Failed Login', $wrong['action']);
        $this->assertSame('Authentication', $wrong['module']);
        $this->assertSame('Not signed in', $wrong['user']);
        $this->assertSame('Visitor', $wrong['role']);
        $this->assertSame('User account #' . $this->student->user_id, $wrong['entity']);
        $this->assertSame('Failed sign-in (wrong password)', $wrong['description']);

        $unknown = $records->firstWhere('note', 'No account matches no***@example.com');
        $this->assertSame('Unknown account', $unknown['entity']);

        // Nobody signed in, so nobody counts as active today.
        $this->assertSame(0, $feed['stats']['activeToday']);
        $this->assertContains('Failed Login', \App\Services\AuditCatalog::actions());
    }
}
