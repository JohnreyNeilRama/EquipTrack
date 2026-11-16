<?php

namespace Tests;

use App\Models\Admin;
use App\Models\BorrowTransaction;
use App\Notifications\AdminSignInCodeNotification;
use App\Support\PrivacyConsent;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    /**
     * Tests run at a fixed moment: Tuesday, Nov 3, 2026, 10:00 AM Philippine
     * time, an office day before the 7:00 PM return deadline. Results then
     * don't depend on the real date (late days skip Sundays, the booking
     * window moves) or on running on a day the office is closed. Tests that
     * need another moment set their own.
     */
    public const TEST_NOW = '2026-11-03 10:00';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse(self::TEST_NOW, config('app.display_timezone')));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Each actingAs() is a fresh sign-in, like logging out and in again. The
     * auth.session middleware remembers the signed-in account's password hash
     * in the session; without clearing it, switching to another account in
     * the same test would be treated as a changed password and signed out (401).
     */
    public function actingAs(UserContract $user, $guard = null)
    {
        $guard ??= config('auth.defaults.guard');

        if ($this->app->bound('session')) {
            $this->app['session']->forget('password_hash_' . $guard);
        }

        // Signed-in pages need agreement to the Privacy Notice (privacy
        // middleware). Test accounts are treated as having agreed, like any
        // real account after the one-time question; tests about that
        // question turn this off with $autoAcceptPrivacy = false.
        if ($this->autoAcceptPrivacy
            && ($user instanceof UserAccount || $user instanceof Admin || $user instanceof DepartmentAccount)
            && (int) $user->privacy_version < PrivacyConsent::version()) {
            $user->forceFill(['privacy_accepted_at' => now(), 'privacy_version' => PrivacyConsent::version()])->saveQuietly();
        }

        return parent::actingAs($user, $guard);
    }

    /** See actingAs(): treat signed-in test accounts as having agreed to the Privacy Notice. */
    protected bool $autoAcceptPrivacy = true;

    /**
     * Second step of an Admin sign-in: enters the code from the latest
     * sign-in email (App\Support\AdminTwoFactor). Notifications must be faked
     * before the password step.
     */
    protected function enterAdminSignInCode(Admin $admin): TestResponse
    {
        $code = null;
        Notification::assertSentTo($admin, AdminSignInCodeNotification::class, function (AdminSignInCodeNotification $n) use (&$code) {
            $code = $n->code;   // the last one sent wins

            return true;
        });

        return $this->post(route('login.code.verify'), ['code' => $code]);
    }

    /**
     * Hands an approved request's equipment to the borrower, as Lab Personnel
     * do on the Release Equipment page: the unit leaves the available stock and
     * the Ready for Pickup loan becomes Active. Tests about borrowing and
     * returning start from a released loan.
     */
    protected function releaseLoan(int $requestId): void
    {
        $transaction = BorrowTransaction::with('request')
            ->where('request_id', $requestId)
            ->where('status', BorrowTransaction::STATUS_READY)
            ->first();

        if (!$transaction) {
            return;
        }

        $this->assertTrue($transaction->takeStock($transaction->request), 'No unit in the lab to release.');
        $transaction->update(['status' => BorrowTransaction::STATUS_ACTIVE, 'released_at' => now()]);
    }
}
