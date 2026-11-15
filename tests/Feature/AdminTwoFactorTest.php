<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Notifications\AdminSignInCodeNotification;
use App\Support\AdminTwoFactor;
use App\Support\LoginLockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Two-step sign-in for Admin accounts: the right password emails a 6-digit
 * code, and the admin is signed in only after entering it.
 */
class AdminTwoFactorTest extends TestCase
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
        Notification::assertSentTo($this->admin, AdminSignInCodeNotification::class, function ($n) use (&$code) {
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

        for ($i = 1; $i < AdminTwoFactor::MAX_ATTEMPTS; $i++) {
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

        $this->travel(AdminTwoFactor::CODE_MINUTES + 1)->minutes();
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
        Notification::assertSentToTimes($this->admin, AdminSignInCodeNotification::class, 1);

        $this->travel(AdminTwoFactor::RESEND_SECONDS + 1)->seconds();
        $this->from(route('login.code'))->post(route('login.code.resend'))->assertSessionHas('status');
        Notification::assertSentToTimes($this->admin, AdminSignInCodeNotification::class, 2);
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

    public function test_other_accounts_and_a_switched_off_setting_sign_in_with_the_password_only(): void
    {
        $department = $this->makeDepartment();
        $this->makeStudent($department, 'student@example.com');
        $this->post('/login', ['email' => 'student@example.com', 'password' => 'Study2026'])->assertRedirect(route('user.dashboard'));
        auth('user')->logout();

        $this->makeLabPersonnel($department, 'lab@example.com');
        $this->post('/login', ['email' => 'lab@example.com', 'password' => 'Lab2026pass'])->assertRedirect(route('department.dashboard'));
        auth('dept')->logout();

        Notification::assertNothingSent();

        config(['equiptrack.admin_two_factor' => false]);
        $this->password()->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($this->admin, 'admin');
    }

    public function test_the_email_shows_the_code(): void
    {
        $mail = (new AdminSignInCodeNotification('482913', 10, '203.0.113.5'))->toMail($this->admin);
        $html = (string) $mail->render();

        $this->assertSame('Your EquipTrack sign-in code: 482913', $mail->subject);
        $this->assertStringContainsString('482913', $html);
        $this->assertStringContainsString('expires in 10 minutes', $html);
        $this->assertStringContainsString('203.0.113.5', $html);
    }
}
