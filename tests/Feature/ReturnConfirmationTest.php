<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Return Confirmation: a user's return stays Pending Confirmation (loan still
 * out, stock untouched) until the department that owns the equipment verifies
 * the physical return, inspects the condition, and confirms or rejects it.
 */
class ReturnConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private UserAccount $user;

    private DepartmentAccount $deptAccount;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->department = Department::create([
            'department_name' => 'College of Computer Studies',
            'department_code' => 'CCS',
        ]);

        $category = EquipmentCategory::create(['category_name' => 'Laptop']);

        $this->equipment = Equipment::create([
            'name' => 'Dell Latitude',
            'brand' => 'Dell',
            'serial_number' => 'SN-DELL-001',
            'image' => '',
            'available_qty' => 2,
            'total_qty' => 2,
            'status' => 'Available',
            'category_id' => $category->category_id,
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
            'id_number' => '2020-0001',
            'year_level' => '3',
            'address' => 'Cebu City',
        ]);

        $this->deptAccount = DepartmentAccount::create([
            'full_name' => 'Lab Custodian',
            'email' => 'dept@example.com',
            'password' => Hash::make('password'),
            'role' => 'Department Head',
            'department_id' => $this->department->department_id,
            'status' => 'Active',
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** Borrows the equipment (department approval) and returns the request id. */
    private function borrow(): int
    {
        $requestId = (int) $this->actingAs($this->user, 'user')->postJson(route('user.borrow.store'), [
            'equipment_id' => $this->equipment->equipment_id,
            'borrow_date' => now()->toDateString(),
            'return_date' => now()->addDays(3)->toDateString(),
            'purpose' => 'Thesis demo',
        ])->assertOk()->json('request_id');

        $this->actingAs($this->deptAccount, 'dept')->postJson(route('department.requests.update'), [
            'request_id' => $requestId,
            'status' => 'Approved',
        ])->assertOk();

        return $requestId;
    }

    private function submitReturn(int $requestId, string $condition = 'Good', string $remarks = 'Works fine')
    {
        return $this->actingAs($this->user, 'user')->postJson(route('user.returns.store'), [
            'request_id' => $requestId,
            'condition' => $condition,
            'remarks' => $remarks,
        ]);
    }

    private function transaction(int $requestId): BorrowTransaction
    {
        return BorrowTransaction::where('request_id', $requestId)->firstOrFail();
    }

    private function confirm(int $requestId, string $condition = 'Good', ?string $remarks = null, ?DepartmentAccount $as = null)
    {
        return $this->actingAs($as ?? $this->deptAccount, 'dept')->postJson(route('department.returns.confirm'), [
            'transaction_id' => $this->transaction($requestId)->transaction_id,
            'condition' => $condition,
            'remarks' => $remarks,
        ]);
    }

    private function reject(int $requestId, ?string $reason = 'The laptop has not been handed over yet.', ?DepartmentAccount $as = null)
    {
        return $this->actingAs($as ?? $this->deptAccount, 'dept')->postJson(route('department.returns.reject'), [
            'transaction_id' => $this->transaction($requestId)->transaction_id,
            'reason' => $reason,
        ]);
    }

    private function deptRows(): \Illuminate\Support\Collection
    {
        return collect($this->actingAs($this->deptAccount, 'dept')->getJson(route('department.returns.data'))->assertOk()->json('returns'));
    }

    // ------------------------------------------------------------------
    // Sidebar and page
    // ------------------------------------------------------------------

    public function test_sidebar_lists_return_confirmation_between_borrow_requests_and_department_users(): void
    {
        $this->actingAs($this->deptAccount, 'dept')
            ->get(route('department.returns'))
            ->assertOk()
            ->assertSeeInOrder(['Borrow Requests', 'Return Confirmation', 'Department Users'])
            ->assertSee('href="' . route('department.returns') . '" class="nav-item active"', false);
    }

    // ------------------------------------------------------------------
    // Submitting a return
    // ------------------------------------------------------------------

    public function test_a_submitted_return_is_pending_confirmation_and_the_loan_stays_out(): void
    {
        $id = $this->borrow();
        $this->assertSame(1, (int) $this->equipment->fresh()->available_qty);

        $this->submitReturn($id, 'Good', 'Works fine')
            ->assertOk()
            ->assertJson(['success' => true, 'returnStatus' => 'Pending Confirmation']);

        $transaction = $this->transaction($id);
        $this->assertSame('Active', $transaction->status, 'Not marked as returned by the user alone.');
        $this->assertSame('Pending Confirmation', $transaction->return_status);
        $this->assertSame('Good — Works fine', $transaction->reported_condition);
        $this->assertNull($transaction->return_date);
        $this->assertSame(1, (int) $this->equipment->fresh()->available_qty, 'Stock is untouched until confirmation.');

        // Shown on the department's Return Confirmation page with what the table needs.
        $row = $this->deptRows()->firstWhere('requestId', $id);
        $this->assertSame('Pending Confirmation', $row['status']);
        $this->assertSame('Juan Dela Cruz', $row['user']);
        $this->assertSame('College of Computer Studies', $row['department']);
        $this->assertSame('Dell Latitude', $row['equipment']);
        $this->assertSame('SN-DELL-001', $row['equipmentId']);
        $this->assertSame(1, $row['quantity']);
        $this->assertSame(now()->format('M d, Y'), $row['submitted']);
        $this->assertSame('Good', $row['condition']);
        $this->assertSame('reported', $row['conditionSource']);
        $this->assertTrue($row['canReview']);
        $this->assertSame('RET-' . str_pad((string) $transaction->transaction_id, 4, '0', STR_PAD_LEFT), $row['returnId']);
    }

    // ------------------------------------------------------------------
    // Confirming
    // ------------------------------------------------------------------

    public function test_confirming_in_good_condition_completes_the_return_and_restores_stock(): void
    {
        $id = $this->borrow();
        $this->submitReturn($id);

        $this->confirm($id, 'Good', 'All accessories present.')->assertOk()->assertJson(['success' => true]);

        $transaction = $this->transaction($id);
        $this->assertSame('Returned', $transaction->status);
        $this->assertSame('Confirmed', $transaction->return_status);
        $this->assertSame('Good', $transaction->inspected_condition);
        $this->assertSame('All accessories present.', $transaction->inspection_remarks);
        $this->assertSame('Good — All accessories present.', $transaction->condition_on_return);
        $this->assertSame($this->deptAccount->dept_acc_id, (int) $transaction->return_reviewed_by);
        $this->assertNotNull($transaction->return_reviewed_at);
        $this->assertSame(now()->toDateString(), $transaction->return_date->toDateString());
        $this->assertSame(2, (int) $this->equipment->fresh()->available_qty);

        $this->assertDatabaseHas('audit_trail', [
            'dept_acc_id' => $this->deptAccount->dept_acc_id,
            'affected_entity' => 'borrow_request:' . $id,
        ]);
        $this->assertStringStartsWith(
            'Department confirmed return of borrow request #' . $id,
            AuditTrail::where('dept_acc_id', $this->deptAccount->dept_acc_id)->latest('audit_id')->value('action')
        );

        $row = $this->deptRows()->firstWhere('requestId', $id);
        $this->assertSame('Confirmed', $row['status']);
        $this->assertSame('inspected', $row['conditionSource']);
        $this->assertSame('Lab Custodian', $row['reviewedBy']);
        $this->assertFalse($row['canReview']);
    }

    public function test_the_return_date_is_the_day_the_user_handed_it_in(): void
    {
        $id = $this->borrow();
        $this->submitReturn($id);
        $this->transaction($id)->update(['return_submitted_at' => now()->subDays(2)]);

        $this->confirm($id)->assertOk();

        $this->assertSame(now()->subDays(2)->toDateString(), $this->transaction($id)->return_date->toDateString());
    }

    public function test_damaged_or_incomplete_returns_need_remarks_and_stay_out_of_available_stock(): void
    {
        $id = $this->borrow();
        $this->submitReturn($id, 'Good', 'Fine');

        $this->confirm($id, 'Damaged', '')->assertStatus(422);
        $this->assertSame('Pending Confirmation', $this->transaction($id)->return_status);

        $this->confirm($id, 'Damaged', 'Cracked hinge.')->assertOk();

        $transaction = $this->transaction($id);
        $this->assertSame('Returned', $transaction->status);
        $this->assertSame('Damaged', $transaction->inspected_condition);
        $this->assertSame('Damaged — Cracked hinge.', $transaction->condition_on_return);
        $this->assertSame(1, (int) $this->equipment->fresh()->available_qty, 'A damaged unit is not lent out again automatically.');

        // Incomplete works the same way.
        $second = $this->borrow();
        $this->submitReturn($second);
        $this->confirm($second, 'Incomplete', 'Charger missing.')->assertOk();
        $this->assertSame('Incomplete', $this->transaction($second)->inspected_condition);
        $this->assertSame(0, (int) $this->equipment->fresh()->available_qty);

        // The history pages show the inspected condition.
        $history = collect($this->actingAs($this->deptAccount, 'dept')->getJson(route('department.history.data'))->json('history'));
        $this->assertEqualsCanonicalizing(['Damaged', 'Incomplete'], $history->pluck('status')->all());
    }

    // ------------------------------------------------------------------
    // Equipment photos
    // ------------------------------------------------------------------

    public function test_the_review_form_has_no_photo_upload_and_ignores_one_sent_anyway(): void
    {
        Storage::fake('public');

        $this->actingAs($this->deptAccount, 'dept')
            ->get(route('department.returns'))
            ->assertOk()
            ->assertDontSee('id="photoFileInput"', false)
            ->assertDontSee('Equipment Photo <span class="optional">', false);

        $id = $this->borrow();
        $this->submitReturn($id);

        $this->actingAs($this->deptAccount, 'dept')->post(route('department.returns.confirm'), [
            'transaction_id' => $this->transaction($id)->transaction_id,
            'condition' => 'Good',
            'photo' => UploadedFile::fake()->image('laptop.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertFalse(Schema::hasColumn('borrow_transaction', 'return_photo'));
        $this->assertArrayNotHasKey('photo', $this->deptRows()->firstWhere('requestId', $id));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_each_return_shows_the_original_photo_of_its_own_equipment(): void
    {
        $this->equipment->update(['image' => '/storage/images/dell-latitude.jpg']);
        $withPhoto = $this->borrow();
        $this->submitReturn($withPhoto);

        // A second item from the same department, added without a photo.
        $this->equipment = Equipment::create([
            'name' => 'Epson Projector',
            'brand' => 'Epson',
            'serial_number' => 'SN-EPSON-001',
            'image' => '',
            'available_qty' => 1,
            'total_qty' => 1,
            'status' => 'Available',
            'category_id' => $this->equipment->category_id,
            'department_id' => $this->department->department_id,
        ]);
        $withoutPhoto = $this->borrow();
        $this->submitReturn($withoutPhoto);

        $rows = $this->deptRows();
        $this->assertSame('/storage/images/dell-latitude.jpg', $rows->firstWhere('requestId', $withPhoto)['equipmentImage']);
        $this->assertNull($rows->firstWhere('requestId', $withoutPhoto)['equipmentImage'], 'The form shows its placeholder.');

        // Images saved as a relative path resolve the same way as on the other pages.
        $this->assertSame(asset('images/legacy.png'), (new Equipment(['image' => 'images/legacy.png']))->imageUrl());
        $this->assertSame('https://cdn.example.com/eq.jpg', (new Equipment(['image' => 'https://cdn.example.com/eq.jpg']))->imageUrl());

        $this->actingAs($this->deptAccount, 'dept')->get(route('department.returns'))
            ->assertSee('id="reviewEquipmentImg"', false)
            ->assertSee('No equipment photo available');
    }

    public function test_only_listed_conditions_are_accepted(): void
    {
        $id = $this->borrow();
        $this->submitReturn($id);

        $this->confirm($id, 'Lost')->assertStatus(422)->assertJsonValidationErrors('condition');
        $this->confirm($id, 'Perfect')->assertStatus(422);
        $this->assertSame('Pending Confirmation', $this->transaction($id)->return_status);
    }

    // ------------------------------------------------------------------
    // Rejecting
    // ------------------------------------------------------------------

    public function test_rejecting_keeps_the_loan_open_and_the_user_can_return_again(): void
    {
        $id = $this->borrow();
        $this->submitReturn($id);

        $this->reject($id, '')->assertStatus(422);

        $this->reject($id)->assertOk()->assertJson(['success' => true]);

        $transaction = $this->transaction($id);
        $this->assertSame('Active', $transaction->status);
        $this->assertSame('Rejected', $transaction->return_status);
        $this->assertSame('The laptop has not been handed over yet.', $transaction->return_reject_reason);
        $this->assertSame($this->deptAccount->dept_acc_id, (int) $transaction->return_reviewed_by);
        $this->assertNull($transaction->return_date);
        $this->assertSame(1, (int) $this->equipment->fresh()->available_qty);

        // The user sees the rejection and can submit the return again.
        $this->actingAs($this->user, 'user')->get(route('user.returns'))
            ->assertOk()
            ->assertSee('Return rejected: The laptop has not been handed over yet.')
            ->assertSee('onclick="openReturnModal(this)"', false);

        $notice = collect($this->actingAs($this->user, 'user')->getJson(route('user.notifications'))->json('notifications'))
            ->firstWhere('title', 'Return Rejected');
        $this->assertNotNull($notice);

        $this->submitReturn($id)->assertOk();
        $this->assertSame('Pending Confirmation', $this->transaction($id)->return_status);
        $this->assertNull($this->transaction($id)->return_reject_reason);

        $this->confirm($id)->assertOk();
        $this->assertSame('Returned', $this->transaction($id)->status);
        $this->assertSame(2, (int) $this->equipment->fresh()->available_qty);
    }

    public function test_a_return_can_only_be_reviewed_once(): void
    {
        $id = $this->borrow();
        $this->submitReturn($id);
        $this->confirm($id)->assertOk();

        $this->confirm($id)->assertStatus(400);
        $this->reject($id)->assertStatus(400);

        $this->assertSame('Returned', $this->transaction($id)->status);
        $this->assertSame(2, (int) $this->equipment->fresh()->available_qty, 'Stock is restored exactly once.');
    }

    public function test_a_loan_with_no_submitted_return_cannot_be_confirmed(): void
    {
        $id = $this->borrow();

        $this->confirm($id)->assertStatus(400);
        $this->assertSame('Active', $this->transaction($id)->status);
        $this->assertNull($this->deptRows()->firstWhere('requestId', $id));
    }

    // ------------------------------------------------------------------
    // Permissions
    // ------------------------------------------------------------------

    public function test_another_department_cannot_see_or_review_the_return(): void
    {
        $id = $this->borrow();
        $this->submitReturn($id);

        $otherDepartment = Department::create(['department_name' => 'College of Engineering', 'department_code' => 'COE']);
        $outsider = DepartmentAccount::create([
            'full_name' => 'Other Custodian',
            'email' => 'other-dept@example.com',
            'password' => Hash::make('password'),
            'role' => 'Department Head',
            'department_id' => $otherDepartment->department_id,
            'status' => 'Active',
        ]);

        $this->assertSame([], $this->actingAs($outsider, 'dept')->getJson(route('department.returns.data'))->json('returns'));
        $this->confirm($id, 'Good', null, $outsider)->assertStatus(404);
        $this->reject($id, 'Not here', $outsider)->assertStatus(404);

        $this->assertSame('Pending Confirmation', $this->transaction($id)->return_status);
    }

    public function test_users_and_admins_cannot_confirm_returns(): void
    {
        $id = $this->borrow();
        $this->submitReturn($id);
        $transactionId = $this->transaction($id)->transaction_id;

        $admin = Admin::create(['name' => 'System Admin', 'username' => 'admin', 'password' => Hash::make('password')]);

        foreach ([[$this->user, 'user'], [$admin, 'admin']] as [$account, $guard]) {
            // Drop the department login the setup used, so only $guard is signed in.
            $this->app['auth']->forgetGuards();

            $response = $this->actingAs($account, $guard)->postJson(route('department.returns.confirm'), [
                'transaction_id' => $transactionId,
                'condition' => 'Good',
            ]);
            $this->assertFalse($response->isSuccessful());
        }

        $this->assertSame('Pending Confirmation', $this->transaction($id)->return_status);
        $this->assertSame(1, (int) $this->equipment->fresh()->available_qty);
    }

    // ------------------------------------------------------------------
    // Consistency on the user's side
    // ------------------------------------------------------------------

    public function test_the_user_side_shows_pending_confirmation_until_the_department_confirms(): void
    {
        $id = $this->borrow();
        $this->transaction($id)->update(['due_date' => now()->subDays(2)->toDateString()]);

        // Overdue reminder before the return is submitted...
        $titles = fn () => collect($this->actingAs($this->user, 'user')->getJson(route('user.notifications'))->json('notifications'))->pluck('title');
        $this->assertContains('Overdue Equipment', $titles());

        $this->submitReturn($id);

        // ...but not while the department is verifying the return.
        $this->assertNotContains('Overdue Equipment', $titles());

        $dashboard = $this->actingAs($this->user, 'user')->getJson(route('user.dashboard.data'))->json();
        $this->assertSame('Pending Confirmation', $dashboard['activeBorrows'][0]['status']);

        // The dashboard page: its own badge style, overdue alert and summary line.
        $this->actingAs($this->user, 'user')->get(route('user.dashboard'))
            ->assertOk()
            ->assertSee('class="detail-status-badge status-pending-confirmation"', false)
            ->assertSee('class="stat-card card is-alert" data-tone="danger" id="statOverdueCard"', false)
            ->assertSee('1 item is past the due date. Please return it as soon as possible.')
            ->assertSee('<span class="panel-count" id="activeBorrowsCount">1</span>', false);

        $history = collect($this->actingAs($this->user, 'user')->get(route('user.history'))->viewData('historyItems'));
        $this->assertSame('Pending Confirmation', $history->first()['status']);

        $this->confirm($id)->assertOk();

        $this->assertContains('Return Confirmed', $titles());
        // Handed back after the due date.
        $history = collect($this->actingAs($this->user, 'user')->get(route('user.history'))->viewData('historyItems'));
        $this->assertSame('Late Return', $history->first()['status']);
    }
}
