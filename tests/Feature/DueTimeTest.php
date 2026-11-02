<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\UserAccount;
use App\Notifications\LoanReminderNotification;
use App\Support\LoanTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Date-only borrowing: borrowers choose Within the Day (returned the same day)
 * or Multiple Days, never a time. Due dates are stored at the return deadline
 * (7:00 PM by default) and a loan is overdue once that deadline has passed.
 * Bookings of the same unit block whole days; back-to-back days don't clash.
 */
class DueTimeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Department $ccs;

    private DepartmentAccount $lab;

    private UserAccount $ana;

    private UserAccount $ben;

    private Equipment $projector;

    protected function setUp(): void
    {
        parent::setUp();

        // 9:00 AM on Nov 2, 2026, Philippine time.
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Asia/Manila'));

        $this->ccs = $this->makeDepartment();
        $this->lab = $this->makeLabPersonnel($this->ccs);
        $this->ana = $this->makeStudent($this->ccs, 'ana@example.com', ['first_name' => 'Ana', 'last_name' => 'Santos']);
        $this->ben = $this->makeStudent($this->ccs, 'ben@example.com', ['first_name' => 'Ben', 'last_name' => 'Reyes']);
        $this->projector = $this->makeEquipment($this->ccs, ['name' => 'Epson Projector', 'available_qty' => 1, 'total_qty' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function submit(UserAccount $user, string $type, string $fromDate, ?string $toDate = null)
    {
        return $this->actingAs($user, 'user')->postJson(route('user.borrow.store'), array_filter([
            'equipment_id' => $this->projector->equipment_id,
            'borrow_type' => $type,
            'borrow_date' => $fromDate,
            'return_date' => $toDate,
            'purpose' => 'Laboratory Activity',
            'instructor_name' => 'Prof. Santos',
        ], fn ($value) => $value !== null));
    }

    private function approve(int $requestId)
    {
        return $this->actingAs($this->lab, 'dept')->postJson(route('department.requests.update'), [
            'request_id' => $requestId,
            'status' => 'Approved',
        ]);
    }

    public function test_a_within_the_day_loan_today_is_due_at_the_return_deadline(): void
    {
        // Any return date sent along is ignored: Within the Day is the borrow date.
        $id = (int) $this->submit($this->ana, 'within_day', '2026-11-02', '2026-11-09')
            ->assertOk()->json('request_id');

        $request = BorrowRequest::find($id);
        $this->assertSame('2026-11-02 00:00:00', $request->borrow_date->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 19:00:00', $request->due_date->format('Y-m-d H:i:s'));

        $this->approve($id)->assertOk();
        $loan = BorrowTransaction::where('request_id', $id)->first();
        $this->assertSame('2026-11-02 00:00:00', $loan->borrow_date->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-02 19:00:00', $loan->due_date->format('Y-m-d H:i:s'));
    }

    public function test_multiple_days_needs_a_later_return_date(): void
    {
        $this->submit($this->ana, 'multiple_days', '2026-11-03')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['return_date' => 'Please choose the return date.']);

        $this->submit($this->ana, 'multiple_days', '2026-11-03', '2026-11-03')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['return_date' => 'For Multiple Days, the return date must be after the borrow date.']);

        $id = (int) $this->submit($this->ana, 'multiple_days', '2026-11-03', '2026-11-05')->assertOk()->json('request_id');
        $this->assertSame('2026-11-05 19:00:00', BorrowRequest::find($id)->due_date->format('Y-m-d H:i:s'));
    }

    public function test_times_sent_with_the_dates_are_ignored(): void
    {
        $id = (int) $this->actingAs($this->ana, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $this->projector->equipment_id,
            'borrow_date' => '2026-11-02',
            'borrow_time' => '10:00',
            'return_date' => '2026-11-02',
            'return_time' => '11:00',
            'purpose' => 'Laboratory Activity',
            'instructor_name' => 'Prof. Santos',
        ])->assertOk()->json('request_id');

        $this->assertSame('2026-11-02 19:00:00', BorrowRequest::find($id)->due_date->format('Y-m-d H:i:s'));
    }

    public function test_today_cannot_be_chosen_after_the_return_deadline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 19:05', 'Asia/Manila'));

        $this->submit($this->ana, 'within_day', '2026-11-02')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['borrow_date' => "Today's return deadline (7:00 PM) has passed. Please choose tomorrow or a later date."]);

        $this->submit($this->ana, 'within_day', '2026-11-01')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['borrow_date' => 'The borrow date cannot be in the past.']);

        $this->submit($this->ana, 'within_day', '2026-11-03')->assertOk();
    }

    public function test_the_return_deadline_comes_from_config(): void
    {
        config(['equiptrack.return_deadline' => '17:00']);

        $id = (int) $this->submit($this->ana, 'within_day', '2026-11-02')->assertOk()->json('request_id');
        $this->assertSame('2026-11-02 17:00:00', BorrowRequest::find($id)->due_date->format('Y-m-d H:i:s'));

        Carbon::setTestNow(Carbon::parse('2026-11-02 17:30', 'Asia/Manila'));
        $this->submit($this->ben, 'within_day', '2026-11-02')->assertStatus(422);
    }

    public function test_bookings_block_whole_days_and_back_to_back_days_do_not_clash(): void
    {
        $first = (int) $this->submit($this->ana, 'multiple_days', '2026-11-03', '2026-11-05')->assertOk()->json('request_id');
        $this->approve($first)->assertOk();

        $this->submit($this->ben, 'within_day', '2026-11-05')
            ->assertStatus(400)
            ->assertJson(['message' => 'Sorry, Epson Projector is fully booked on Nov 3–5. Please choose other dates.']);

        $this->submit($this->ben, 'within_day', '2026-11-02')->assertOk();
        $second = (int) $this->submit($this->ben, 'within_day', '2026-11-06')->assertOk()->json('request_id');
        $this->approve($second)->assertOk();

        $this->assertSame(2, BorrowTransaction::count());

        // Back-to-back bookings read as one fully booked period.
        $html = html_entity_decode($this->actingAs($this->ben, 'user')->get(route('user.equipment'))->getContent());
        $this->assertStringContainsString(json_encode(['Nov 3–6']), $html);
    }

    public function test_a_loan_is_overdue_only_after_the_return_deadline(): void
    {
        Notification::fake();

        $id = (int) $this->submit($this->ana, 'within_day', '2026-11-02')->assertOk()->json('request_id');
        $this->approve($id)->assertOk();
        $this->releaseLoan($id);
        $loan = BorrowTransaction::where('request_id', $id)->first();

        Carbon::setTestNow(Carbon::parse('2026-11-02 18:59', 'Asia/Manila'));
        $this->assertFalse($loan->fresh()->isOverdue());
        $this->assertSame(0, $this->actingAs($this->lab, 'dept')->getJson(route('department.dashboard.data'))->json('stats.overdueItems'));

        Carbon::setTestNow(Carbon::parse('2026-11-02 19:01', 'Asia/Manila'));
        $this->assertTrue($loan->fresh()->isOverdue());
        $dashboard = $this->actingAs($this->lab, 'dept')->getJson(route('department.dashboard.data'))->json();
        $this->assertSame(1, $dashboard['stats']['overdueItems']);
        $this->assertSame('Nov 02, 2026', $dashboard['overdue'][0]['dueDate']);
        $this->assertSame('1 Day', $dashboard['overdue'][0]['daysLateText']);

        // The next morning's reminder run sends the overdue email.
        Carbon::setTestNow(Carbon::parse('2026-11-03 07:00', 'Asia/Manila'));
        Artisan::call('equiptrack:send-loan-reminders');
        Notification::assertSentTo($this->ana, LoanReminderNotification::class);
    }

    public function test_late_days_count_from_the_return_deadline(): void
    {
        $id = (int) $this->submit($this->ana, 'within_day', '2026-11-02')->assertOk()->json('request_id');
        $this->approve($id)->assertOk();
        $this->releaseLoan($id);
        $loan = BorrowTransaction::where('request_id', $id)->first();

        $loan->update(['return_submitted_at' => Carbon::parse('2026-11-02 18:30', 'Asia/Manila')->utc()]);
        $this->assertSame(0, $loan->fresh()->daysLate());

        $loan->update(['return_submitted_at' => Carbon::parse('2026-11-03 09:00', 'Asia/Manila')->utc()]);
        $this->assertSame(1, $loan->fresh()->daysLate());

        // Only the return date known: on the due date is on time, the day after is 1 day late.
        $loan->update(['return_submitted_at' => null, 'return_date' => '2026-11-02']);
        $this->assertSame(0, $loan->fresh()->daysLate());
        $loan->update(['return_date' => '2026-11-03']);
        $this->assertSame(1, $loan->fresh()->daysLate());
    }

    public function test_a_loan_past_its_deadline_cannot_be_released(): void
    {
        $id = (int) $this->submit($this->ana, 'within_day', '2026-11-02')->assertOk()->json('request_id');
        $this->approve($id)->assertOk();
        $loan = BorrowTransaction::where('request_id', $id)->first();

        Carbon::setTestNow(Carbon::parse('2026-11-02 19:30', 'Asia/Manila'));
        $this->actingAs($this->lab, 'dept')->postJson(route('department.releases.release'), [
            'transaction_id' => $loan->transaction_id,
            'id_verified' => '1',
        ])->assertStatus(400)->assertJson(['success' => false]);

        $this->assertSame(BorrowTransaction::STATUS_READY, $loan->fresh()->status);
    }

    public function test_dates_are_shown_without_times_to_borrowers_and_staff(): void
    {
        $id = (int) $this->submit($this->ana, 'multiple_days', '2026-11-03', '2026-11-05')->assertOk()->json('request_id');

        $this->actingAs($this->ana, 'user')->get(route('user.requests'))
            ->assertSee('Nov 03, 2026')
            ->assertSee('Nov 05, 2026');

        $row = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.requests.data'))->json('requests'))->firstWhere('id', $id);
        $this->assertSame('Nov 03, 2026', $row['borrowDate']);
        $this->assertSame('Nov 05, 2026', $row['dueDate']);

        $this->approve($id)->assertOk();
        $release = $this->actingAs($this->lab, 'dept')->getJson(route('department.releases.data'))->json('releases.0');
        $this->assertSame('Nov 03, 2026', $release['pickupDate']);
        $this->assertSame('Nov 05, 2026', $release['dueDate']);

        $this->assertSame('Nov 05, 2026 by 7:00 PM', LoanTime::dueBy(BorrowRequest::find($id)->due_date));
    }

    public function test_the_request_form_has_dates_only(): void
    {
        $html = $this->actingAs($this->ana, 'user')->get(route('user.equipment'))->assertOk()->getContent();

        $this->assertStringContainsString('Within the Day', $html);
        $this->assertStringContainsString('Multiple Days', $html);
        $this->assertStringContainsString('Return by <strong>7:00 PM</strong>', $html);
        $this->assertStringNotContainsString('id="borrowTime"', $html);
        $this->assertStringNotContainsString('id="returnTime"', $html);
        $this->assertStringNotContainsString('data-minutes="60"', $html);
    }

    public function test_the_migration_gives_whole_day_due_dates_the_end_of_the_day(): void
    {
        $request = $this->makePendingRequest($this->ana, $this->projector);
        $loan = BorrowTransaction::create([
            'request_id' => $request->request_id,
            'borrow_date' => '2026-11-05',
            'due_date' => '2026-11-07',
            'status' => BorrowTransaction::STATUS_READY,
        ]);
        // As the old DATE columns read: midnight.
        DB::table('borrow_request')->where('request_id', $request->request_id)->update(['due_date' => '2026-11-04 00:00:00']);
        DB::table('borrow_transaction')->where('transaction_id', $loan->transaction_id)->update(['due_date' => '2026-11-07 00:00:00']);

        // The data step of the migration (the column change already ran).
        $migration = require database_path('migrations/2026_11_03_000001_add_times_to_borrow_dates.php');
        $migration->endWholeDayDueDates();

        $this->assertSame('2026-11-04 23:59:00', (string) DB::table('borrow_request')->where('request_id', $request->request_id)->value('due_date'));
        $this->assertSame('2026-11-07 23:59:00', (string) DB::table('borrow_transaction')->where('transaction_id', $loan->transaction_id)->value('due_date'));
        $this->assertSame('2026-11-05 00:00:00', (string) DB::table('borrow_transaction')->where('transaction_id', $loan->transaction_id)->value('borrow_date'));
        $this->assertSame('Nov 07, 2026', LoanTime::format($loan->fresh()->due_date));
        $this->assertSame('Nov 07, 2026', LoanTime::dueBy($loan->fresh()->due_date));
    }
}
