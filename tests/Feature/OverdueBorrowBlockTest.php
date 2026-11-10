<?php

namespace Tests\Feature;

use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\Penalty;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * A student or faculty member who still has an overdue item cannot submit new
 * borrow requests until it is returned. The Available Equipment page says why
 * and links to Return Item.
 */
class OverdueBorrowBlockTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    private Department $department;

    private UserAccount $student;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        // Wednesday, 10:00 AM in the Philippines (an office day, before the deadline).
        Carbon::setTestNow(Carbon::parse('2026-11-11 10:00', 'Asia/Manila'));

        $this->department = $this->makeDepartment();
        $this->student = $this->makeStudent($this->department);
        $this->equipment = $this->makeEquipment($this->department, ['name' => 'Epson Projector']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function requestEquipment(UserAccount $user)
    {
        return $this->actingAs($user, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $this->equipment->equipment_id,
            'borrow_date' => '2026-11-12',
            'return_date' => '2026-11-13',
            'purpose' => 'Class presentation',
            'instructor_name' => 'Prof. Santos',
        ]);
    }

    /** A released loan that was due Monday, Nov 9 at 7:00 PM. */
    private function overdueLoan(array $loan = []): BorrowTransaction
    {
        $laptop = $this->makeEquipment($this->department, ['name' => 'Dell Latitude']);

        return $this->makeLoan($this->student, $laptop, '2026-11-09', $loan, '2026-11-09');
    }

    public function test_an_overdue_item_blocks_new_requests_and_the_page_explains_why(): void
    {
        $this->overdueLoan();

        $this->actingAs($this->student, 'user')->get(route('user.equipment'))
            ->assertOk()
            ->assertSee('Borrowing is on hold')
            ->assertSee('You have overdue equipment: Dell Latitude was due Nov 09, 2026 by 7:00 PM.', false)
            ->assertSee('Return Item')
            ->assertSee(route('user.returns'), false);

        $this->requestEquipment($this->student)
            ->assertStatus(403)
            ->assertJson(['success' => false])
            ->assertJsonPath('message', 'You have overdue equipment: Dell Latitude was due Nov 09, 2026 by 7:00 PM. Return it first; you can borrow again once it is back.');

        $this->assertDatabaseMissing('borrow_request', ['equipment_id' => $this->equipment->equipment_id]);
    }

    public function test_a_loan_flagged_overdue_also_blocks(): void
    {
        $this->overdueLoan(['status' => BorrowTransaction::STATUS_OVERDUE]);

        $this->requestEquipment($this->student)->assertStatus(403);
    }

    public function test_submitting_the_return_lifts_the_block(): void
    {
        $loan = $this->overdueLoan();

        $loan->update([
            'return_status' => BorrowTransaction::RETURN_PENDING,
            'return_submitted_at' => now(),
            'reported_condition' => 'Good',
        ]);

        $this->actingAs($this->student, 'user')->get(route('user.equipment'))->assertDontSee('Borrowing is on hold');
        $this->requestEquipment($this->student)->assertOk()->assertJson(['success' => true]);
    }

    public function test_a_rejected_return_keeps_blocking(): void
    {
        $this->overdueLoan([
            'return_status' => BorrowTransaction::RETURN_REJECTED,
            'return_reject_reason' => 'The item was not handed over.',
        ]);

        $this->requestEquipment($this->student)->assertStatus(403);
    }

    public function test_a_returned_item_does_not_block(): void
    {
        $this->overdueLoan([
            'status' => BorrowTransaction::STATUS_RETURNED,
            'return_status' => BorrowTransaction::RETURN_CONFIRMED,
            'return_date' => '2026-11-10',
        ]);

        $this->requestEquipment($this->student)->assertOk();
    }

    public function test_an_item_not_due_yet_does_not_block(): void
    {
        $laptop = $this->makeEquipment($this->department, ['name' => 'Dell Latitude']);
        $this->makeLoan($this->student, $laptop, '2026-11-11', [], '2026-11-11');

        $this->requestEquipment($this->student)->assertOk();
    }

    public function test_another_borrowers_overdue_item_does_not_block(): void
    {
        $this->overdueLoan();
        $faculty = $this->makeFaculty($this->department);

        $this->requestEquipment($faculty)->assertOk();
    }

    public function test_the_overdue_block_comes_before_the_penalty_block(): void
    {
        $loan = $this->overdueLoan();
        $returned = $this->makeLoan($this->student, $this->equipment, '2026-11-05', [
            'status' => BorrowTransaction::STATUS_RETURNED,
            'return_status' => BorrowTransaction::RETURN_CONFIRMED,
            'return_date' => '2026-11-06',
        ], '2026-11-05');
        Penalty::create([
            'transaction_id' => $returned->transaction_id,
            'user_id' => $this->student->user_id,
            'type' => 'Late Return',
            'description' => 'Returned one day late.',
            'status' => 'Pending',
            'recorded_at' => now(),
        ]);

        $this->actingAs($this->student, 'user')->get(route('user.equipment'))
            ->assertSee('You have overdue equipment', false)
            ->assertSee('Return Item');

        $loan->update(['return_status' => BorrowTransaction::RETURN_PENDING]);

        $this->actingAs($this->student, 'user')->get(route('user.equipment'))
            ->assertSee('unsettled Late Return penalty', false)
            ->assertSee('Submit Proof');
    }

    public function test_the_overdue_block_can_be_turned_off(): void
    {
        $this->overdueLoan();
        config(['equiptrack.block_borrowing_with_overdue_item' => false]);

        $this->requestEquipment($this->student)->assertOk();
    }
}
