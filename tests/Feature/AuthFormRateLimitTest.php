<?php

namespace Tests\Feature;

use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Every sign-in form limits how often one device can send it, and says so on
 * the form (never the bare "429 Too Many Requests" page):
 * - Login: 6 a minute, Register: 10 a minute (route limits, see bootstrap/app.php);
 * - Forgot Password: 5 a minute, Reset Password: 10 a minute (in their controllers).
 */
class AuthFormRateLimitTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTE_MESSAGE = 'Too many requests. Please wait a minute and try again.';

    public function test_login_over_the_limit_shows_a_message_on_the_form(): void
    {
        foreach (range(1, 6) as $i) {
            $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
                ->assertRedirect('/login');
        }

        $this->from('/login')->post('/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => self::ROUTE_MESSAGE]);

        // The email is kept on the form; the password never is.
        $this->assertSame('nobody@example.com', session()->getOldInput('email'));
        $this->assertNull(session()->getOldInput('password'));
    }

    public function test_register_over_the_limit_shows_a_message_on_the_form(): void
    {
        foreach (range(1, 10) as $i) {
            $this->from('/register')->post('/register', ['role' => 'student'])->assertRedirect('/register');
        }

        $this->from('/register')->post('/register', ['role' => 'student'])
            ->assertRedirect('/register')
            ->assertSessionHasErrors(['email' => self::ROUTE_MESSAGE]);
    }

    public function test_forgot_password_keeps_its_own_limit_of_five_a_minute(): void
    {
        foreach (range(1, 6) as $i) {
            UserAccount::create([
                'role' => 'Student',
                'email' => "student{$i}@example.com",
                'password' => Hash::make('Secret123'),
                'status' => 'Active',
            ]);
        }

        // Different addresses each time: the per-device limit still applies.
        foreach (range(1, 5) as $i) {
            $this->from('/forgot-password')->post('/forgot-password', ['email' => "student{$i}@example.com"])
                ->assertSessionDoesntHaveErrors();
        }

        $this->from('/forgot-password')->post('/forgot-password', ['email' => 'student6@example.com'])
            ->assertRedirect('/forgot-password')
            ->assertSessionHasErrors('email');
        $this->assertStringStartsWith('Too many requests. Please wait', session('errors')->first('email'));
    }

    public function test_reset_password_keeps_its_own_limit_of_ten_a_minute(): void
    {
        $send = fn () => $this->from('/reset-password/bad-token')->post('/reset-password', [
            'token' => 'bad-token',
            'email' => 'nobody@example.com',
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
        ]);

        foreach (range(1, 10) as $i) {
            $send()->assertRedirect('/reset-password/bad-token');
        }

        $send()->assertRedirect('/reset-password/bad-token')->assertSessionHasErrors('password');
        $this->assertStringStartsWith('Too many attempts. Please wait', session('errors')->first('password'));
    }
}
