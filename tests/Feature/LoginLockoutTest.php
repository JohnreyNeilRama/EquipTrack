<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\UserAccount;
use App\Support\LoginLockout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Per-account sign-in lockout: 10 wrong passwords for one email/username
 * lock it for 15 minutes (any computer); a successful sign-in or a password
 * reset clears it. The per-visitor route limit is switched off here so the
 * per-account rule is tested on its own.
 */
class LoginLockoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    private function student(string $email = 'student@example.com'): UserAccount
    {
        return UserAccount::create([
            'role' => 'Student',
            'email' => $email,
            'password' => Hash::make('Secret123'),
            'status' => 'Active',
        ]);
    }

    private function attempt(string $login, string $password)
    {
        return $this->from('/login')->post('/login', ['email' => $login, 'password' => $password]);
    }

    private function failTimes(string $login, int $times): void
    {
        for ($i = 0; $i < $times; $i++) {
            $this->attempt($login, 'wrong-password')->assertSessionHasErrors(['email' => 'Invalid email/username or password.']);
        }
    }

    public function test_ten_wrong_passwords_lock_the_account_even_for_the_right_password(): void
    {
        $this->student();
        $this->failTimes('student@example.com', LoginLockout::MAX_FAILURES);

        $response = $this->attempt('student@example.com', 'Secret123');

        $response->assertRedirect('/login')->assertSessionHasErrors([
            'email' => 'Too many failed sign-in attempts for this account. Try again in 15 minutes, or reset your password with "Forgot password?".',
        ]);
        $this->assertGuest('user');
    }

    public function test_fewer_failures_still_allow_sign_in_and_success_resets_the_count(): void
    {
        $this->student();
        $this->failTimes('student@example.com', LoginLockout::MAX_FAILURES - 1);

        $this->attempt('student@example.com', 'Secret123')->assertRedirect(route('user.dashboard'));
        $this->assertAuthenticated('user');

        auth('user')->logout();
        $this->failTimes('student@example.com', LoginLockout::MAX_FAILURES - 1);
        $this->attempt('student@example.com', 'Secret123')->assertRedirect(route('user.dashboard'));
    }

    public function test_the_lock_ignores_letter_case_and_ends_after_fifteen_minutes(): void
    {
        $this->student();
        $this->failTimes('Student@Example.com', LoginLockout::MAX_FAILURES);

        $this->attempt('student@example.com', 'Secret123')->assertSessionHasErrors('email');
        $this->assertGuest('user');

        $this->travel(LoginLockout::LOCK_MINUTES)->minutes();
        $this->travel(5)->seconds();

        $this->attempt('student@example.com', 'Secret123')->assertRedirect(route('user.dashboard'));
    }

    public function test_unknown_emails_lock_the_same_way_so_accounts_cannot_be_discovered(): void
    {
        $this->failTimes('nobody@example.com', LoginLockout::MAX_FAILURES);

        $this->attempt('nobody@example.com', 'anything')->assertSessionHasErrors([
            'email' => 'Too many failed sign-in attempts for this account. Try again in 15 minutes, or reset your password with "Forgot password?".',
        ]);
    }

    public function test_one_locked_account_does_not_affect_others(): void
    {
        $this->student();
        $this->student('other@example.com');
        $this->failTimes('student@example.com', LoginLockout::MAX_FAILURES);

        $this->attempt('other@example.com', 'Secret123')->assertRedirect(route('user.dashboard'));
    }

    public function test_a_password_reset_unlocks_the_account(): void
    {
        $student = $this->student();
        $this->failTimes('student@example.com', LoginLockout::MAX_FAILURES);

        $token = Password::broker('users')->createToken($student);
        $this->post(route('password.store'), [
            'token' => $token,
            'email' => 'student@example.com',
            'password' => 'NewSecret456',
            'password_confirmation' => 'NewSecret456',
        ])->assertRedirect(route('login'));

        $this->attempt('student@example.com', 'NewSecret456')->assertRedirect(route('user.dashboard'));
    }

    public function test_admin_sign_in_clears_both_username_and_email_counts(): void
    {
        Admin::create([
            'name' => 'System Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('Secret123'),
        ]);
        $this->failTimes('admin', LoginLockout::MAX_FAILURES - 1);
        $this->failTimes('admin@example.com', LoginLockout::MAX_FAILURES - 1);

        $this->attempt('admin@example.com', 'Secret123')->assertRedirect(route('admin.dashboard'));
        auth('admin')->logout();

        // Both counters started again: 9 more failures on the username still don't lock it.
        $this->failTimes('admin', LoginLockout::MAX_FAILURES - 1);
        $this->attempt('admin', 'Secret123')->assertRedirect(route('admin.dashboard'));
    }

    public function test_a_deactivated_account_with_the_right_password_is_not_counted_as_a_failure(): void
    {
        $this->student()->update(['status' => 'Deactivated']);

        for ($i = 0; $i < LoginLockout::MAX_FAILURES + 2; $i++) {
            $this->attempt('student@example.com', 'Secret123')
                ->assertSessionHasErrors(['email' => 'Your account has been deactivated by the Administrator.']);
        }

        $this->assertFalse(LoginLockout::isLocked('student@example.com'));
    }
}
