<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\DepartmentAccount;
use App\Notifications\SignInCodeNotification;
use App\Support\LoginLockout;
use App\Support\SignInCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Two-step sign-in for Admin and Lab Personnel accounts: the right password
 * emails a 6-digit code, and the account is signed in only after entering it.
 * Students and Faculty sign in with their password only.
 */
class SignInCodeTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = $this->makeAdmin('admin@example.com', 'System Admin', 'Admin2026');
    }

    private function password(string $login = 'admin@example.com', string $password = 'Admin2026')
    {
        return $this->post('/login', ['email' => $login, 'password' => $password]);
    }

    private function lastCode(): string
    {
        $code = null;
        Notification::assertSentTo($this->admin, SignInCodeNotification::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });

        return $code;
    }

    private function wrongCode(): string
    {
        return $this->lastCode() === '000000' ? '111111' : '000000';
    }

    public function test_the_right_password_emails_a_code_but_does_not_sign_in(): void
    {
        $this->password()->assertRedirect(route('login.code'));

        $this->assertGuest('admin');
        $this->assertMatchesRegularExpression('/^\d{6}$/', $this->lastCode());
        $this->assertSame(0, AuditTrail::where('action', 'Login')->count());

        // The admin dashboard is still closed.
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));

        // The code page shows where the code went, without the full address.
        $this->get(route('login.code'))->assertOk()
            ->assertSee('ad•••@example.com')
            ->assertDontSee('admin@example.com')
            ->assertSee('autocomplete="one-time-code"', false);
    }

    public function test_the_emailed_code_signs_the_admin_in(): void
    {
        $this->password();
        $this->post(route('login.code.verify'), ['code' => $this->lastCode()])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin, 'admin');
        $this->assertNotNull($this->admin->fresh()->last_online);
        $this->assertDatabaseHas('audit_trail', [
            'admin_id' => $this->admin->admin_id,
            'action' => 'Login',
            'details' => 'Signed in as Administrator with an emailed code',
        ]);

        // Spaces typed in the code are fine; a used code doesn't work again.
        auth('admin')->logout();
        $this->password();
        $code = $this->lastCode();
        $this->post(route('login.code.verify'), ['code' => substr($code, 0, 3) . ' ' . substr($code, 3)])->assertRedirect(route('admin.dashboard'));
        auth('admin')->logout();
        $this->post(route('login.code.verify'), ['code' => $code])->assertRedirect(route('login'));
        $this->assertGuest('admin');
    }

    public function test_a_wrong_code_is_refused_and_recorded(): void
    {
        $this->password();
        $this->from(route('login.code'))->post(route('login.code.verify'), ['code' => $this->wrongCode()])
            ->assertRedirect(route('login.code'))
            ->assertSessionHasErrors(['code' => 'That code is not correct. 4 tries left.']);

        $this->assertGuest('admin');
        $this->assertDatabaseHas('audit_trail', [
            'action' => 'Failed login (wrong sign-in code)',
            'affected_entity' => 'admin:' . $this->admin->admin_id,
            'admin_id' => null,
        ]);

        // The right code still works afterwards.
        $this->post(route('login.code.verify'), ['code' => $this->lastCode()])->assertRedirect(route('admin.dashboard'));
    }

    public function test_five_wrong_codes_cancel_the_code(): void
    {
        $this->password();
        $right = $this->lastCode();

        for ($i = 1; $i < SignInCode::MAX_ATTEMPTS; $i++) {
            $this->post(route('login.code.verify'), ['code' => $this->wrongCode()])->assertSessionHasErrors('code');
        }
        $this->post(route('login.code.verify'), ['code' => $this->wrongCode()])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Too many wrong codes. Sign in again to get a new code.']);

        $this->post(route('login.code.verify'), ['code' => $right])->assertRedirect(route('login'));
        $this->assertGuest('admin');
    }

    public function test_wrong_codes_count_towards_the_account_lockout(): void
    {
        // Spread over time in real life; here the per-visitor limit would stop the test.
        $this->withoutMiddleware(ThrottleRequests::class);

        // Wrong passwords and wrong codes add up to the same lockout.
        for ($i = 0; $i < LoginLockout::MAX_FAILURES - 1; $i++) {
            $this->password(password: 'wrong-password');
        }
        $this->password();
        $right = $this->lastCode();
        $this->post(route('login.code.verify'), ['code' => $this->wrongCode()])
            ->assertRedirect(route('login'));

        $this->assertTrue(LoginLockout::isLocked('admin@example.com'));
        $this->assertDatabaseHas('audit_trail', ['action' => 'Account locked', 'affected_entity' => 'admin:' . $this->admin->admin_id]);

        // Locked: even the right password and code don't get in.
        $this->password()->assertSessionHasErrors('email');
        $this->post(route('login.code.verify'), ['code' => $right])->assertRedirect(route('login'));
        $this->assertGuest('admin');
    }

    public function test_a_code_expires_after_ten_minutes(): void
    {
        $this->password();
        $code = $this->lastCode();

        $this->travel(SignInCode::CODE_MINUTES + 1)->minutes();
        $this->post(route('login.code.verify'), ['code' => $code])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Your sign-in code has expired. Sign in again to get a new one.']);
        $this->assertGuest('admin');
    }

    public function test_a_new_code_can_be_sent_once_a_minute_and_replaces_the_old_one(): void
    {
        $this->password();
        $first = $this->lastCode();

        $this->from(route('login.code'))->post(route('login.code.resend'))->assertSessionHasErrors('code');
        Notification::assertSentToTimes($this->admin, SignInCodeNotification::class, 1);

        $this->travel(SignInCode::RESEND_SECONDS + 1)->seconds();
        $this->from(route('login.code'))->post(route('login.code.resend'))->assertSessionHas('status');
        Notification::assertSentToTimes($this->admin, SignInCodeNotification::class, 2);
        $second = $this->lastCode();

        if ($first !== $second) {
            $this->post(route('login.code.verify'), ['code' => $first])->assertSessionHasErrors('code');
        }
        $this->post(route('login.code.verify'), ['code' => $second])->assertRedirect(route('admin.dashboard'));
    }

    public function test_the_code_only_works_in_the_browser_that_entered_the_password(): void
    {
        $this->password();
        $code = $this->lastCode();

        $this->flushSession();   // another browser
        $this->post(route('login.code.verify'), ['code' => $code])->assertRedirect(route('login'));
        $this->get(route('login.code'))->assertRedirect(route('login'));
        $this->assertGuest('admin');
    }

    public function test_use_a_different_account_drops_the_waiting_sign_in(): void
    {
        $this->password();
        $code = $this->lastCode();

        $this->post(route('login.code.cancel'))->assertRedirect(route('login'));
        $this->post(route('login.code.verify'), ['code' => $code])->assertRedirect(route('login'));
        $this->assertGuest('admin');
    }

    public function test_an_admin_deactivated_while_the_code_was_on_its_way_is_refused(): void
    {
        $this->password();
        $this->admin->update(['status' => 'Deactivated']);

        $this->post(route('login.code.verify'), ['code' => $this->lastCode()])->assertRedirect(route('login'));
        $this->assertGuest('admin');
    }

    public function test_if_the_email_cannot_be_sent_the_admin_is_told_and_not_signed_in(): void
    {
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('Mail server down'));

        $this->password()->assertSessionHasErrors(['email' => "We couldn't email your sign-in code. Please try again in a few minutes."]);
        $this->assertGuest('admin');
        $this->get(route('login.code'))->assertRedirect(route('login'));
    }

    public function test_students_and_faculty_still_sign_in_with_the_password_only(): void
    {
        $department = $this->makeDepartment();
        $this->makeStudent($department, 'student@example.com');
        $this->post('/login', ['email' => 'student@example.com', 'password' => 'Study2026'])->assertRedirect(route('user.dashboard'));
        auth('user')->logout();

        $this->makeFaculty($department, 'faculty@example.com');
        $this->post('/login', ['email' => 'faculty@example.com', 'password' => 'Teach2026'])->assertRedirect(route('user.dashboard'));
        auth('user')->logout();

        Notification::assertNothingSent();
    }

    public function test_a_switched_off_setting_signs_admins_in_with_the_password_only(): void
    {
        config(['equiptrack.admin_two_factor' => false]);
        $this->password()->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin, 'admin');
        Notification::assertNothingSent();
    }

    public function test_lab_personnel_confirm_their_sign_in_with_an_emailed_code(): void
    {
        $lab = $this->makeLabPersonnel($this->makeDepartment(), 'lab@example.com');

        $this->post('/login', ['email' => 'lab@example.com', 'password' => 'Lab2026pass'])->assertRedirect(route('login.code'));
        $this->assertGuest('dept');
        $this->get(route('department.dashboard'))->assertRedirect(route('login'));
        $this->get(route('login.code'))->assertOk()->assertSee('la•••@example.com');

        // A wrong code is refused and counts towards the lockout.
        $code = null;
        Notification::assertSentTo($lab, SignInCodeNotification::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });
        $this->from(route('login.code'))->post(route('login.code.verify'), ['code' => $code === '000000' ? '111111' : '000000'])
            ->assertSessionHasErrors(['code' => 'That code is not correct. 4 tries left.']);
        $this->assertDatabaseHas('audit_trail', [
            'action' => 'Failed login (wrong sign-in code)',
            'affected_entity' => 'department_account:' . $lab->dept_acc_id,
        ]);

        // The right code signs them in.
        $this->post(route('login.code.verify'), ['code' => $code])->assertRedirect(route('department.dashboard'));
        $this->assertAuthenticatedAs($lab, 'dept');
        $this->assertNotNull($lab->fresh()->last_online);
        $this->assertDatabaseHas('audit_trail', [
            'dept_acc_id' => $lab->dept_acc_id,
            'action' => 'Login',
            'details' => 'Signed in as Department account with an emailed code',
        ]);
    }

    public function test_a_lab_personnel_code_is_not_an_admin_code(): void
    {
        // The same person can't turn a Lab Personnel code into an Admin sign-in:
        // the code is tied to the account it was sent to.
        $lab = $this->makeLabPersonnel($this->makeDepartment(), 'lab@example.com');
        $this->post('/login', ['email' => 'lab@example.com', 'password' => 'Lab2026pass']);
        $labCode = null;
        Notification::assertSentTo($lab, SignInCodeNotification::class, function ($n) use (&$labCode) {
            $labCode = $n->code;

            return true;
        });

        $this->post(route('login.code.cancel'));
        $this->password();
        if ($labCode !== $this->lastCode()) {
            $this->post(route('login.code.verify'), ['code' => $labCode])->assertSessionHasErrors('code');
        }
        $this->assertGuest('admin');
        $this->assertGuest('dept');
    }

    public function test_a_lab_personnel_account_deactivated_while_the_code_was_on_its_way_is_refused(): void
    {
        $lab = $this->makeLabPersonnel($this->makeDepartment(), 'lab@example.com');
        $this->post('/login', ['email' => 'lab@example.com', 'password' => 'Lab2026pass']);
        $lab->update(['status' => 'Deactivated']);

        $code = null;
        Notification::assertSentTo($lab, SignInCodeNotification::class, function ($n) use (&$code) {
            $code = $n->code;

            return true;
        });
        $this->post(route('login.code.verify'), ['code' => $code])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'This department account has been deactivated by the Administrator.']);
        $this->assertGuest('dept');
    }

    public function test_the_lab_personnel_setting_can_be_switched_off_on_its_own(): void
    {
        config(['equiptrack.department_two_factor' => false]);
        $lab = $this->makeLabPersonnel($this->makeDepartment(), 'lab@example.com');

        $this->post('/login', ['email' => 'lab@example.com', 'password' => 'Lab2026pass'])->assertRedirect(route('department.dashboard'));
        $this->assertAuthenticatedAs($lab, 'dept');
        Notification::assertNothingSent();

        // Admins still get their code.
        auth('dept')->logout();
        $this->password()->assertRedirect(route('login.code'));
    }

    public function test_the_lab_personnel_email_names_their_account(): void
    {
        $lab = $this->makeLabPersonnel($this->makeDepartment(), 'lab@example.com', 'Maria Lopez');
        $html = (string) (new SignInCodeNotification('482913', 10, '203.0.113.5'))->toMail($lab)->render();

        $this->assertStringContainsString('Hi Maria Lopez', $html);
        $this->assertStringContainsString('your EquipTrack Lab Personnel account', $html);
        $this->assertStringContainsString('482913', $html);
    }

    public function test_the_email_shows_the_code(): void
    {
        $mail = (new SignInCodeNotification('482913', 10, '203.0.113.5'))->toMail($this->admin);
        $html = (string) $mail->render();

        $this->assertSame('Your EquipTrack sign-in code: 482913', $mail->subject);
        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString('expires in 10 minutes', $html);
        $this->assertStringContainsString('203.0.113.5', $html);
    }
}
