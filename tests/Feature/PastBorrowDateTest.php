<?php

namespace Tests\Feature;

use App\Models\Equipment;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * The borrow form refuses past dates on the server, using the Philippine date
 * (the server clock is UTC, still on the previous day until 8:00 AM).
 */
class PastBorrowDateTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private UserAccount $student;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $department = $this->makeDepartment();
        $this->student = $this->makeStudent($department);
        $this->equipment = $this->makeEquipment($department, ['available_qty' => 5, 'total_qty' => 5]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function borrow(string $borrowDate, string $returnDate)
    {
        return $this->actingAs($this->student, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $this->equipment->equipment_id,
            'borrow_date' => $borrowDate,
            'return_date' => $returnDate,
            'purpose' => 'Laboratory activity',
            'instructor_name' => 'Prof. Santos',
        ]);
    }

    public function test_past_dates_are_refused_and_today_or_later_is_accepted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 14:00', 'Asia/Manila'));

        $this->borrow('2026-10-27', '2026-10-29')->assertUnprocessable()
            ->assertJsonValidationErrors(['borrow_date' => 'The borrow date cannot be in the past.']);
        $this->borrow('2026-10-18', '2026-10-18')->assertUnprocessable();
        $this->borrow('2026-10-28', '2026-10-30')->assertOk();
        $this->borrow('2026-11-02', '2026-11-04')->assertOk();
    }

    public function test_the_return_date_cannot_be_before_the_borrow_date(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-28 14:00', 'Asia/Manila'));

        $this->borrow('2026-10-30', '2026-10-29')->assertUnprocessable()
            ->assertJsonValidationErrors(['return_date' => 'Return date cannot be earlier than borrow date.']);
    }

    public function test_just_after_midnight_the_philippine_date_counts(): void
    {
        // 6:00 AM on Oct 28 in the Philippines is still Oct 27 on the UTC server clock.
        Carbon::setTestNow(Carbon::parse('2026-10-28 06:00', 'Asia/Manila'));
        $this->assertSame('2026-10-27', now()->toDateString());

        $this->borrow('2026-10-27', '2026-10-29')->assertUnprocessable();
        $this->borrow('2026-10-28', '2026-10-30')->assertOk();
    }
}
