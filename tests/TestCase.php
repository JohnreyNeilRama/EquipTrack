<?php

namespace Tests;

use App\Models\BorrowTransaction;
use Illuminate\Contracts\Auth\Authenticatable as UserContract;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

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

        return parent::actingAs($user, $guard);
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
