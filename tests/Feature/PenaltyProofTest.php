<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Penalty;
use App\Models\PenaltySubmission;
use App\Models\PenaltySubmissionFile;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Proof of penalty settlement: the borrower uploads proof that a Pending
 * penalty was paid, replaced or repaired; the equipment's department or the
 * admin approves it (settling the penalty) or rejects it with a reason.
 */
class PenaltyProofTest extends TestCase
{
    use RefreshDatabase;

    private Department $department;

    private Department $otherDepartment;

    private UserAccount $user;

    private UserAccount $otherUser;

    private DepartmentAccount $lab;

    private DepartmentAccount $otherLab;

    private Admin $admin;

    private Equipment $equipment;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->department = Department::create(['department_name' => 'College of Computer Studies', 'department_code' => 'CCS']);
        $this->otherDepartment = Department::create(['department_name' => 'College of Education', 'department_code' => 'COE']);
        $category = EquipmentCategory::create(['category_name' => 'Laptop']);

        $this->equipment = Equipment::create([
            'name' => 'Dell Latitude',
            'brand' => 'Dell',
            'serial_number' => 'SN-DELL-001',
            'image' => '',
            'available_qty' => 1,
            'total_qty' => 1,
            'status' => 'Available',
            'category_id' => $category->category_id,
            'department_id' => $this->department->department_id,
        ]);

        $this->user = $this->makeStudent('student@example.com', '20200001', 'Juan');
        $this->otherUser = $this->makeStudent('other@example.com', '20200002', 'Maria');
        $this->lab = $this->makeLab($this->department, 'lab@example.com');
        $this->otherLab = $this->makeLab($this->otherDepartment, 'coe@example.com');
        $this->admin = Admin::create(['name' => 'System Admin', 'username' => 'admin', 'email' => 'admin@example.com', 'password' => Hash::make('password')]);
    }

    private function makeStudent(string $email, string $id, string $first): UserAccount
    {
        $user = UserAccount::create([
            'role' => 'Student',
            'email' => $email,
            'password' => Hash::make('password'),
            'department_id' => $this->department->department_id,
            'status' => 'Active',
        ]);
        Student::create(['user_id' => $user->user_id, 'first_name' => $first, 'last_name' => 'Dela Cruz', 'id_number' => $id, 'year_level' => '3rd Year', 'address' => 'Cebu City']);

        return $user;
    }

    private function makeLab(Department $department, string $email): DepartmentAccount
    {
        return DepartmentAccount::create([
            'full_name' => 'Lab ' . $department->department_code,
            'email' => $email,
            'password' => Hash::make('password'),
            'role' => 'Department Head',
            'department_id' => $department->department_id,
            'status' => 'Active',
        ]);
    }

    /** A returned loan of the user's with a Pending penalty. */
    private function penalty(string $type = 'Damaged', ?float $amount = 500, ?UserAccount $owner = null): Penalty
    {
        $owner ??= $this->user;

        $request = BorrowRequest::create([
            'user_id' => $owner->user_id,
            'equipment_id' => $this->equipment->equipment_id,
            'quantity' => 1,
            'purpose' => 'Class',
            'date_needed' => now()->subDays(5)->toDateString(),
            'return_date' => now()->subDays(2)->toDateString(),
            'overall_status' => 'Approved',
            'admin_status' => 'Pending',
            'dept_status' => 'Approved',
        ]);

        $transaction = BorrowTransaction::create([
            'request_id' => $request->request_id,
            'borrow_date' => now()->subDays(5)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'return_date' => now()->subDay()->toDateString(),
            'status' => 'Returned',
            'return_status' => BorrowTransaction::RETURN_CONFIRMED,
            'inspected_condition' => $type === 'Late Return' ? 'Good' : $type,
        ]);

        return Penalty::create([
            'transaction_id' => $transaction->transaction_id,
            'user_id' => $owner->user_id,
            'type' => $type,
            'amount' => $amount,
            'description' => 'Pay for the repair at the Accounting Office.',
            'status' => Penalty::STATUS_PENDING,
            'recorded_by_dept_acc_id' => $this->lab->dept_acc_id,
            'recorded_at' => now()->subDay(),
        ]);
    }

    private function submit(Penalty $penalty, array $overrides = [], ?UserAccount $as = null)
    {
        return $this->actingAs($as ?? $this->user, 'user')->post(route('user.penalties.submit'), array_merge([
            'penalty_id' => $penalty->penalty_id,
            'type' => 'Payment',
            'reference_no' => 'OR-2026-00123',
            'amount_paid' => '500',
            'settled_on' => now()->toDateString(),
            'notes' => 'Paid at the Accounting Office.',
            'files' => [UploadedFile::fake()->image('receipt.jpg', 600, 800)],
        ], $overrides), ['Accept' => 'application/json']);
    }

    private function userRow(Penalty $penalty): array
    {
        return collect($this->actingAs($this->user, 'user')->getJson(route('user.penalties.data'))->json('penalties'))
            ->firstWhere('id', $penalty->penalty_id);
    }

    public function test_my_penalties_page_lists_only_the_users_own_penalties(): void
    {
        $mine = $this->penalty();
        $this->penalty('Lost', null, $this->otherUser);

        $this->actingAs($this->user, 'user')->get(route('user.penalties'))
            ->assertOk()
            ->assertSee('My Penalties')
            ->assertSee('Submit Proof');

        $rows = $this->actingAs($this->user, 'user')->getJson(route('user.penalties.data'))->json('penalties');
        $this->assertSame([$mine->penalty_id], array_column($rows, 'id'));
        $this->assertSame('none', $rows[0]['proofState']);
        $this->assertSame(['Payment', 'Replacement', 'Repair'], $rows[0]['proofTypes']);
    }

    public function test_submissions_are_validated(): void
    {
        $penalty = $this->penalty();

        $this->submit($penalty, ['files' => []])->assertStatus(422)->assertJsonValidationErrors('files');
        $this->submit($penalty, ['reference_no' => ''])->assertStatus(422)->assertJsonValidationErrors('reference_no');
        $this->submit($penalty, ['settled_on' => now()->addDay()->toDateString()])->assertStatus(422)->assertJsonValidationErrors('settled_on');
        $this->submit($penalty, ['files' => [UploadedFile::fake()->create('notes.txt', 10, 'text/plain')]])
            ->assertStatus(422)->assertJsonValidationErrors('files.0');
        $this->submit($penalty, ['files' => [UploadedFile::fake()->image('big.jpg')->size(6000)]])
            ->assertStatus(422)->assertJsonValidationErrors('files.0');
        $this->submit($penalty, ['files' => array_map(fn ($i) => UploadedFile::fake()->image("r$i.jpg"), range(1, 4))])
            ->assertStatus(422)->assertJsonValidationErrors('files');

        // A replacement does not need a receipt number; repair is not possible for a late return.
        $this->submit($penalty, ['type' => 'Replacement', 'reference_no' => ''])->assertOk();
        $late = $this->penalty('Late Return', 50);
        $this->submit($late, ['type' => 'Repair'])->assertStatus(400);

        // Nobody can submit proof for someone else's penalty.
        $theirs = $this->penalty('Damaged', 100, $this->otherUser);
        $this->submit($theirs)->assertNotFound();
    }

    public function test_a_submission_is_stored_privately_and_goes_under_review(): void
    {
        $penalty = $this->penalty();

        $this->submit($penalty, [
            'files' => [UploadedFile::fake()->image('receipt.jpg'), UploadedFile::fake()->create('receipt.pdf', 200, 'application/pdf')],
        ])->assertOk()->assertJson(['success' => true]);

        $submission = PenaltySubmission::with('files')->firstOrFail();
        $this->assertSame(PenaltySubmission::STATUS_REVIEW, $submission->status);
        $this->assertSame('OR-2026-00123', $submission->reference_no);
        $this->assertCount(2, $submission->files);
        foreach ($submission->files as $file) {
            Storage::disk('local')->assertExists($file->path);
            $this->assertStringStartsWith('penalty-proofs/' . $penalty->penalty_id . '/', $file->path);
            $this->assertStringNotContainsString('receipt', $file->path);
        }

        $row = $this->userRow($penalty);
        $this->assertSame('review', $row['proofState']);
        $this->assertCount(2, $row['proof']['files']);

        // Only one submission at a time; the borrowing hold stays until it is approved.
        $this->submit($penalty)->assertStatus(400);
        $this->assertNotNull(Penalty::blockingBorrowFor($this->user->user_id));

        $this->assertDatabaseHas('audit_trail', ['user_id' => $this->user->user_id, 'action' => 'Submitted proof for penalty #' . $penalty->penalty_id . ' (Payment)']);
        $userKeys = collect($this->actingAs($this->user, 'user')->getJson(route('user.notifications'))->json('notifications'))->pluck('key');
        $this->assertContains('proof-' . $submission->submission_id . '-received', $userKeys);
        $labKeys = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.notifications'))->json('notifications'))->pluck('key');
        $this->assertContains('proof-' . $submission->submission_id . '-submitted', $labKeys);
        $otherLabKeys = collect($this->actingAs($this->otherLab, 'dept')->getJson(route('department.notifications'))->json('notifications'))->pluck('key');
        $this->assertNotContains('proof-' . $submission->submission_id . '-submitted', $otherLabKeys);
    }

    public function test_the_submitted_state_survives_a_page_refresh_and_staff_settlement_shows_no_proof(): void
    {
        $submitted = $this->penalty();
        $settledByStaff = $this->penalty('Incomplete', 150);
        $this->submit($submitted)->assertOk();

        // A fresh page load (refresh) carries the submitted state the tracker reads.
        $page = $this->actingAs($this->user, 'user')->get(route('user.penalties'))->assertOk();
        $rows = collect($page->viewData('dbPenalties'))->keyBy('id');
        $this->assertSame('review', $rows[$submitted->penalty_id]['proofState']);
        $this->assertSame(PenaltySubmission::STATUS_REVIEW, $rows[$submitted->penalty_id]['proof']['status']);

        // Settled at the office without any upload: no proof, so no "Proof submitted" step.
        $this->actingAs($this->lab, 'dept')->postJson(route('department.penalties.settle'), ['penalty_id' => $settledByStaff->penalty_id])->assertOk();
        $row = $this->userRow($settledByStaff);
        $this->assertSame('settled', $row['proofState']);
        $this->assertNull($row['proof']);
    }

    public function test_proof_files_are_only_served_to_the_owner_and_staff_in_scope(): void
    {
        $penalty = $this->penalty();
        $this->submit($penalty)->assertOk();
        $file = PenaltySubmissionFile::firstOrFail();

        $this->actingAs($this->user, 'user')->get(route('user.penalties.file', $file->file_id))->assertOk();
        $this->actingAs($this->otherUser, 'user')->get(route('user.penalties.file', $file->file_id))->assertNotFound();
        $this->actingAs($this->lab, 'dept')->get(route('department.penalties.proof', $file->file_id))->assertOk();
        $this->actingAs($this->otherLab, 'dept')->get(route('department.penalties.proof', $file->file_id))->assertNotFound();
        $this->actingAs($this->admin, 'admin')->get(route('admin.penalties.proof', $file->file_id))->assertOk();

        // Not in the public folder.
        $this->assertFileDoesNotExist(public_path('storage/' . $file->path));
    }

    public function test_staff_see_the_proof_and_rejecting_asks_the_user_to_resubmit(): void
    {
        $penalty = $this->penalty();
        $this->submit($penalty)->assertOk();

        $row = collect($this->actingAs($this->lab, 'dept')->getJson(route('department.penalties.data'))->json('penalties'))->first();
        $this->assertTrue($row['awaitingReview']);
        $this->assertSame('OR-2026-00123', $row['proof']['referenceNo']);
        $this->assertStringContainsString('/department/penalties/proof/file/', $row['proof']['files'][0]['url']);

        $this->actingAs($this->lab, 'dept')->postJson(route('department.penalties.proof.reject'), ['penalty_id' => $penalty->penalty_id])
            ->assertStatus(422)->assertJsonValidationErrors('reason');
        $this->actingAs($this->otherLab, 'dept')->postJson(route('department.penalties.proof.reject'), ['penalty_id' => $penalty->penalty_id, 'reason' => 'x'])
            ->assertNotFound();

        $this->actingAs($this->lab, 'dept')->postJson(route('department.penalties.proof.reject'), [
            'penalty_id' => $penalty->penalty_id,
            'reason' => 'The receipt is unreadable.',
        ])->assertOk();

        $submission = PenaltySubmission::firstOrFail();
        $this->assertSame(PenaltySubmission::STATUS_REJECTED, $submission->status);
        $this->assertSame(Penalty::STATUS_PENDING, $penalty->fresh()->status);
        $this->assertSame('rejected', $this->userRow($penalty)['proofState']);

        $userKeys = collect($this->actingAs($this->user, 'user')->getJson(route('user.notifications'))->json('notifications'))->pluck('key');
        $this->assertContains('proof-' . $submission->submission_id . '-rejected', $userKeys);

        // Resubmitting starts a new review and keeps the rejected attempt as history.
        $this->submit($penalty, ['reference_no' => 'OR-2026-00124'])->assertOk();
        $row = $this->userRow($penalty);
        $this->assertSame('review', $row['proofState']);
        $this->assertCount(1, $row['proofHistory']);
        $this->assertSame('The receipt is unreadable.', $row['proofHistory'][0]['rejectReason']);
    }

    public function test_approving_a_payment_settles_the_penalty_and_lifts_the_borrowing_hold(): void
    {
        $penalty = $this->penalty();
        $this->submit($penalty)->assertOk();

        $this->actingAs($this->admin, 'admin')->postJson(route('admin.penalties.proof.approve'), ['penalty_id' => $penalty->penalty_id])
            ->assertOk()->assertJson(['success' => true]);

        $penalty->refresh();
        $this->assertSame(Penalty::STATUS_SETTLED, $penalty->status);
        $this->assertSame('OR-2026-00123', $penalty->reference_no);
        $this->assertSame($this->admin->admin_id, (int) $penalty->settled_by_admin_id);
        $this->assertSame(PenaltySubmission::STATUS_APPROVED, PenaltySubmission::firstOrFail()->status);
        $this->assertNull(Penalty::blockingBorrowFor($this->user->user_id));
        $this->assertSame('settled', $this->userRow($penalty)['proofState']);

        // Already reviewed.
        $this->actingAs($this->admin, 'admin')->postJson(route('admin.penalties.proof.approve'), ['penalty_id' => $penalty->penalty_id])
            ->assertStatus(400);

        $actions = collect($this->actingAs($this->admin, 'admin')->getJson(route('admin.audit.data', ['module' => 'Penalties']))->json('records'))->pluck('action');
        $this->assertContains('Submitted Proof', $actions);
        $this->assertContains('Approved Proof', $actions);
        $this->assertContains('Settled Penalty', $actions);
    }

    public function test_a_lost_item_replacement_needs_inspection_and_restocks_the_unit(): void
    {
        $this->equipment->update(['total_qty' => 0, 'available_qty' => 0, 'status' => 'Unavailable']);
        $penalty = $this->penalty('Lost', null);
        $this->submit($penalty, ['type' => 'Replacement', 'reference_no' => ''])->assertOk();

        $this->actingAs($this->lab, 'dept')->postJson(route('department.penalties.proof.approve'), ['penalty_id' => $penalty->penalty_id])
            ->assertStatus(400);
        $this->assertSame(Penalty::STATUS_PENDING, $penalty->fresh()->status);

        $this->actingAs($this->lab, 'dept')->postJson(route('department.penalties.proof.approve'), [
            'penalty_id' => $penalty->penalty_id,
            'item_received' => '1',
        ])->assertOk();

        $this->assertSame(Penalty::STATUS_SETTLED, $penalty->fresh()->status);
        $this->assertTrue(PenaltySubmission::firstOrFail()->replacement_received);
        $equipment = $this->equipment->fresh();
        $this->assertSame(1, (int) $equipment->total_qty);
        $this->assertSame(1, (int) $equipment->available_qty);
        $this->assertSame('Available', $equipment->status);
    }
}
