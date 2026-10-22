<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Penalty;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Damage & Penalty Recording: the department records a penalty when it
 * confirms a return; Lab Personnel (own department) and the admin (all) settle
 * or waive it. EquipTrack only records penalties; it takes no payments.
 */
class PenaltyRecordingTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private Department $otherDepartment;

    private UserAccount $user;

    private DepartmentAccount $lab;

    private DepartmentAccount $otherLab;

    private Admin $admin;

    private Equipment $equipment;

    private EquipmentCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create(['department_name' => 'College of Computer Studies', 'department_code' => 'CCS']);
        $this->otherDepartment = Department::create(['department_name' => 'College of Education', 'department_code' => 'COE']);
        $this->category = EquipmentCategory::create(['category_name' => 'Laptop']);

        $this->equipment = Equipment::create([
            'name' => 'Dell Latitude',
            'brand' => 'Dell',
            'serial_number' => 'SN-DELL-001',
            'image' => '',
            'available_qty' => 2,
            'total_qty' => 2,
            'status' => 'Available',
            'category_id' => $this->category->category_id,
            'department_id' => $this->department->department_id,
        ]);

        $this->user = UserAccount::create([
            'role' => 'Student',
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
            'department_id' => $this->department->department_id,
            'status' => 'Active',
        ]);
        Student::create([
            'user_id' => $this->user->user_id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'id_number' => '20200001',
            'year_level' => '3rd Year',
            'address' => 'Cebu City',
        ]);

        $this->lab = $this->makeLab($this->department, 'lab@example.com', 'Ana Santos');
        $this->otherLab = $this->makeLab($this->otherDepartment, 'coe-lab@example.com', 'Ben Reyes');

        $this->admin = Admin::create([
            'name' => 'System Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    private function makeLab(Department $department, string $email, string $name): DepartmentAccount
    {
        return DepartmentAccount::create([
            'full_name' => $name,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'Department Head',
            'department_id' => $department->department_id,
            'status' => 'Active',
        ]);
    }

    /** Borrows (department approval) and submits the return; returns the transaction. */
    private function borrowAndReturn(string $reported = 'Good', int $daysLate = 0): BorrowTransaction
    {
        $requestId = (int) $this->actingAs($this->user, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $this->equipment->equipment_id,
            'borrow_date' => now()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'purpose' => 'Thesis demo',
            'instructor_name' => 'Prof. Santos',
        ])->assertOk()->json('request_id');

        $this->actingAs($this->lab, 'dept')->postJson(route('department.requests.update'), [
            'request_id' => $requestId,
            'status' => 'Approved',
        ])->assertOk();

        $transaction = BorrowTransaction::where('request_id', $requestId)->firstOrFail();
        if ($daysLate > 0) {
            $transaction->update(['due_date' => now()->subDays($daysLate)->toDateString()]);
        }

        $this->actingAs($this->user, 'user')->postJson(route('user.returns.store'), [
            'request_id' => $requestId,
            'condition' => $reported,
            'remarks' => 'Returned',
        ])->assertOk();

        return $transaction->fresh();
    }

    private function confirm(BorrowTransaction $transaction, array $payload, ?DepartmentAccount $as = null)
    {
        return $this->actingAs($as ?? $this->lab, 'dept')->postJson(route('department.returns.confirm'), $payload + [
            'transaction_id' => $transaction->transaction_id,
        ]);
    }

    private function recordDamaged(): Penalty
    {
        $transaction = $this->borrowAndReturn('Damaged');

        $this->confirm($transaction, [
            'condition' => 'Damaged',
            'remarks' => 'Cracked screen hinge',
            'penalty_type' => 'Damaged',
            'penalty_amount' => '500',
            'penalty_description' => 'Pay for the hinge repair at the Accounting Office.',
        ])->assertOk();

        return Penalty::firstOrFail();
    }

    // ------------------------------------------------------------------
    // Recording at Return Confirmation
    // ------------------------------------------------------------------

    public function test_confirming_a_return_can_record_a_penalty(): void
    {
        $penalty = $this->recordDamaged();

        $this->assertSame('Damaged', $penalty->type);
        $this->assertSame('500.00', (string) $penalty->amount);
        $this->assertSame(Penalty::STATUS_PENDING, $penalty->status);
        $this->assertSame($this->user->user_id, (int) $penalty->user_id);
        $this->assertSame($this->lab->dept_acc_id, (int) $penalty->recorded_by_dept_acc_id);
        $this->assertSame('Returned', $penalty->transaction->status);

        $this->assertDatabaseHas('audit_trail', [
            'dept_acc_id' => $this->lab->dept_acc_id,
            'affected_entity' => 'penalty:' . $penalty->penalty_id,
        ]);

        // The confirmed return lists its penalty.
        $row = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.returns.data'))->json('returns'))->first();
        $this->assertSame([['type' => 'Damaged', 'amount' => '₱500.00', 'status' => 'Pending']], $row['penalties']);
    }

    public function test_a_penalty_is_optional_and_a_bad_one_undoes_the_whole_confirmation(): void
    {
        $transaction = $this->borrowAndReturn();

        // Missing description: nothing is saved, the return stays pending.
        $this->confirm($transaction, ['condition' => 'Good', 'penalty_type' => 'Late Return'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('penalty_description');
        $this->assertTrue($transaction->fresh()->isReturnPending());

        $this->confirm($transaction, ['condition' => 'Good'])->assertOk();
        $this->assertSame(0, Penalty::count());
        $this->assertSame('Returned', $transaction->fresh()->status);
    }

    public function test_lost_items_leave_the_total_quantity_and_can_carry_a_penalty(): void
    {
        $transaction = $this->borrowAndReturn('Lost');
        $this->assertSame(1, (int) $this->equipment->fresh()->available_qty);

        $this->confirm($transaction, [
            'condition' => 'Lost',
            'remarks' => 'Borrower reported it stolen',
            'penalty_type' => 'Lost',
            'penalty_description' => 'Replace the laptop with the same model.',
        ])->assertOk()->assertJson(['success' => true]);

        $equipment = $this->equipment->fresh();
        $this->assertSame(1, (int) $equipment->total_qty);
        $this->assertSame(1, (int) $equipment->available_qty);
        $this->assertSame('Lost', $transaction->fresh()->inspected_condition);

        // A non-monetary penalty has no amount.
        $this->assertNull(Penalty::firstOrFail()->amount);
    }

    public function test_late_returns_show_days_late_and_the_configured_suggested_fee(): void
    {
        config(['equiptrack.penalty_late_per_day' => 25]);
        $this->borrowAndReturn('Good', 3);

        $row = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.returns.data'))->json('returns'))->first();
        $this->assertSame(3, $row['daysLate']);
        $this->assertEquals(75, $row['suggestedLateFee']);

        config(['equiptrack.penalty_late_per_day' => 0]);
        $row = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.returns.data'))->json('returns'))->first();
        $this->assertNull($row['suggestedLateFee']);
    }

    // ------------------------------------------------------------------
    // Lab Personnel Penalties page
    // ------------------------------------------------------------------

    public function test_lab_personnel_see_and_settle_only_their_departments_penalties(): void
    {
        $penalty = $this->recordDamaged();

        $this->actingAs($this->lab, 'dept')->get(route('department.penalties'))
            ->assertOk()
            ->assertSee('Penalties')
            ->assertSee('PEN-');

        // The record view shows the equipment photo, its category, the units and the borrower's picture.
        $this->equipment->update(['image' => '/storage/images/dell.jpg']);
        $this->user->update(['profile_image' => '/storage/images/user-1-profile.jpg']);
        $row = $this->actingAs($this->lab, 'dept')->getJson(route('department.penalties.data'))->json('penalties.0');
        $this->assertSame('/storage/images/dell.jpg', $row['equipmentImage']);
        $this->assertSame('Laptop', $row['category']);
        $this->assertSame(1, $row['quantity']);
        $this->assertSame('/storage/images/user-1-profile.jpg', $row['avatar']);

        $this->assertSame([], $this->actingAs($this->otherLab, 'dept')->getJson(route('department.penalties.data'))->json('penalties'));
        $this->actingAs($this->otherLab, 'dept')->postJson(route('department.penalties.settle'), ['penalty_id' => $penalty->penalty_id])
            ->assertNotFound();

        $this->actingAs($this->lab, 'dept')->postJson(route('department.penalties.settle'), [
            'penalty_id' => $penalty->penalty_id,
            'reference_no' => 'OR-2026-00123',
            'remarks' => 'Paid at the Accounting Office',
        ])->assertOk()->assertJson(['success' => true]);

        $penalty->refresh();
        $this->assertSame(Penalty::STATUS_SETTLED, $penalty->status);
        $this->assertSame('OR-2026-00123', $penalty->reference_no);
        $this->assertSame($this->lab->dept_acc_id, (int) $penalty->settled_by_dept_acc_id);
        $this->assertNotNull($penalty->settled_at);

        // Closed penalties cannot be settled, waived or edited again.
        $this->actingAs($this->lab, 'dept')->postJson(route('department.penalties.waive'), ['penalty_id' => $penalty->penalty_id, 'reason' => 'x'])
            ->assertStatus(400);
        $this->actingAs($this->lab, 'dept')->postJson(route('department.penalties.update'), ['penalty_id' => $penalty->penalty_id, 'description' => 'x'])
            ->assertStatus(400);
    }

    public function test_waiving_needs_a_reason_and_is_audited(): void
    {
        $penalty = $this->recordDamaged();

        $this->actingAs($this->lab, 'dept')->postJson(route('department.penalties.waive'), ['penalty_id' => $penalty->penalty_id])
            ->assertStatus(422)
            ->assertJsonValidationErrors('reason');

        $this->actingAs($this->lab, 'dept')->postJson(route('department.penalties.waive'), [
            'penalty_id' => $penalty->penalty_id,
            'reason' => 'The damage was there before the loan.',
        ])->assertOk();

        $this->assertSame(Penalty::STATUS_WAIVED, $penalty->fresh()->status);
        $this->assertDatabaseHas('audit_trail', [
            'action' => 'Waived penalty #' . $penalty->penalty_id . ' (Damaged)',
            'details' => 'Reason: The damage was there before the loan.',
        ]);

        $records = collect($this->actingAs($this->admin, 'admin')->getJson(route('admin.audit.data', ['module' => 'Penalties']))->json('records'));
        $this->assertEqualsCanonicalizing(['Recorded Penalty', 'Waived Penalty'], $records->pluck('action')->all());
        $this->assertSame('Penalty #' . $penalty->penalty_id, $records->first()['entity']);
    }

    // ------------------------------------------------------------------
    // Admin Penalties page
    // ------------------------------------------------------------------

    public function test_admin_sees_all_penalties_and_can_add_edit_and_settle(): void
    {
        $transaction = $this->borrowAndReturn('Good', 2);
        $this->confirm($transaction, ['condition' => 'Good'])->assertOk();

        $page = $this->actingAs($this->admin, 'admin')->get(route('admin.penalties'));
        $page->assertOk()->assertSee('Add Penalty');

        $loans = $this->actingAs($this->admin, 'admin')->getJson(route('admin.penalties.data'))->json('returnedLoans');
        $this->assertSame((int) $transaction->transaction_id, $loans[0]['id']);
        $this->assertSame(2, $loans[0]['daysLate']);

        $store = fn (array $payload) => $this->actingAs($this->admin, 'admin')->postJson(route('admin.penalties.store'), $payload + [
            'transaction_id' => $transaction->transaction_id,
            'type' => 'Late Return',
            'description' => 'Returned two days late.',
        ]);

        $store(['amount' => '40'])->assertOk()->assertJson(['success' => true]);
        $store([])->assertStatus(400); // one penalty of each type per loan

        $penalty = Penalty::firstOrFail();
        $this->assertSame($this->admin->admin_id, (int) $penalty->recorded_by_admin_id);

        $this->actingAs($this->admin, 'admin')->postJson(route('admin.penalties.update'), [
            'penalty_id' => $penalty->penalty_id,
            'amount' => '',
            'description' => 'Two days late: one week borrowing suspension.',
        ])->assertOk();
        $this->assertNull($penalty->fresh()->amount);

        $this->actingAs($this->admin, 'admin')->postJson(route('admin.penalties.settle'), ['penalty_id' => $penalty->penalty_id])
            ->assertOk();
        $this->assertSame(Penalty::STATUS_SETTLED, $penalty->fresh()->status);
        $this->assertSame($this->admin->admin_id, (int) $penalty->fresh()->settled_by_admin_id);
    }

    public function test_penalties_can_only_be_added_to_returned_loans(): void
    {
        $requestId = (int) $this->actingAs($this->user, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $this->equipment->equipment_id,
            'borrow_date' => now()->toDateString(),
            'return_date' => now()->addDay()->toDateString(),
            'purpose' => 'Class',
            'instructor_name' => 'Prof. Santos',
        ])->json('request_id');
        $this->actingAs($this->lab, 'dept')->postJson(route('department.requests.update'), ['request_id' => $requestId, 'status' => 'Approved']);
        $active = BorrowTransaction::where('request_id', $requestId)->firstOrFail();

        $this->actingAs($this->admin, 'admin')->postJson(route('admin.penalties.store'), [
            'transaction_id' => $active->transaction_id,
            'type' => 'Damaged',
            'description' => 'x',
        ])->assertStatus(400);

        $this->assertSame(0, Penalty::count());
    }

    // ------------------------------------------------------------------
    // Student / Faculty side
    // ------------------------------------------------------------------

    public function test_a_pending_penalty_notifies_the_user_and_blocks_new_requests_until_settled(): void
    {
        $penalty = $this->recordDamaged();

        $feed = collect($this->actingAs($this->user, 'user')->getJson(route('user.notifications'))->json('notifications'));
        $issued = $feed->firstWhere('key', 'penalty-' . $penalty->penalty_id . '-issued');
        $this->assertSame('penalty', $issued['type']);
        $this->assertStringContainsString('₱500.00', $issued['message']);

        $reminders = $this->actingAs($this->user, 'user')->getJson(route('user.dashboard.data'))->json('reminders');
        $this->assertSame('Penalty: Damaged', $reminders[0]['label']);

        $this->actingAs($this->user, 'user')->get(route('user.equipment'))->assertSee('Borrowing is on hold');

        $request = fn () => $this->actingAs($this->user, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $this->equipment->equipment_id,
            'borrow_date' => now()->toDateString(),
            'return_date' => now()->addDay()->toDateString(),
            'purpose' => 'Class',
            'instructor_name' => 'Prof. Santos',
        ]);

        $request()->assertStatus(403)->assertJson(['success' => false]);

        $this->actingAs($this->lab, 'dept')->postJson(route('department.penalties.settle'), ['penalty_id' => $penalty->penalty_id])->assertOk();

        $request()->assertOk()->assertJson(['success' => true]);
        $this->actingAs($this->user, 'user')->get(route('user.equipment'))->assertDontSee('Borrowing is on hold');

        $keys = collect($this->actingAs($this->user, 'user')->getJson(route('user.notifications'))->json('notifications'))->pluck('key');
        $this->assertContains('penalty-' . $penalty->penalty_id . '-settled', $keys);
    }

    public function test_the_borrowing_block_can_be_turned_off(): void
    {
        $this->recordDamaged();
        config(['equiptrack.block_borrowing_with_pending_penalty' => false]);

        $this->actingAs($this->user, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $this->equipment->equipment_id,
            'borrow_date' => now()->toDateString(),
            'return_date' => now()->addDay()->toDateString(),
            'purpose' => 'Class',
            'instructor_name' => 'Prof. Santos',
        ])->assertOk();
    }

    public function test_history_shows_the_penalty_and_users_with_one_cannot_be_deleted(): void
    {
        $this->recordDamaged();

        $this->actingAs($this->user, 'user')->get(route('user.history'))
            ->assertOk()
            ->assertSee('Damaged ₱500.00 (Pending)');

        $this->actingAs($this->admin, 'admin')->post(route('admin.users.delete'), [
            'user_id' => $this->user->user_id,
            'user_type' => 'user_account',
        ])->assertRedirect();
        $this->assertNotNull($this->user->fresh());
    }

    // ------------------------------------------------------------------
    // Reports
    // ------------------------------------------------------------------

    public function test_penalty_report_lists_recorded_penalties_with_filters(): void
    {
        $penalty = $this->recordDamaged();

        $report = $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.reports.data', ['type' => 'penalties']))
            ->assertOk()
            ->json('report');

        $this->assertSame('Penalty Report', $report['title']);
        $this->assertSame(1, $report['total']);
        $this->assertSame('PEN-' . str_pad((string) $penalty->penalty_id, 4, '0', STR_PAD_LEFT), $report['rows'][0]['penalty_id']);
        $this->assertSame('₱500.00', $report['rows'][0]['amount']);
        $this->assertSame('College of Computer Studies', $report['rows'][0]['department']);

        $settledOnly = $this->actingAs($this->admin, 'admin')
            ->getJson(route('admin.reports.data', ['type' => 'penalties', 'status' => 'Settled']))
            ->json('report');
        $this->assertSame(0, $settledOnly['total']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.reports.export', ['type' => 'penalties', 'format' => 'print']))
            ->assertOk()
            ->assertSee('Penalty Report');
    }
}
