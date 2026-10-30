<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\BorrowTransaction;
use App\Models\DepartmentAccount;
use App\Models\Penalty;
use App\Models\UserAccount;
use App\Services\AuditCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Laboratory Personnel record a penalty from their Penalties page after a
 * return was confirmed (e.g. damage found later), for loans of their own
 * department's equipment only. The Admin can record on any loan.
 */
class DepartmentPenaltyRecordingTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private DepartmentAccount $ccsLab;

    private DepartmentAccount $ccjLab;

    private Admin $admin;

    private UserAccount $student;

    private BorrowTransaction $ccsLoan;

    private BorrowTransaction $ccjLoan;

    protected function setUp(): void
    {
        parent::setUp();

        $ccs = $this->makeDepartment();
        $ccj = $this->makeDepartment('College of Criminal Justice', 'CCJ');
        $this->ccsLab = $this->makeLabPersonnel($ccs, 'ccs.lab@example.com');
        $this->ccjLab = $this->makeLabPersonnel($ccj, 'ccj.lab@example.com', 'CCJ Custodian');
        $this->admin = $this->makeAdmin();
        $this->student = $this->makeStudent($ccs);

        $returned = ['status' => 'Returned', 'return_date' => '2026-10-22', 'return_status' => BorrowTransaction::RETURN_CONFIRMED];
        $this->ccsLoan = $this->makeLoan($this->student, $this->makeEquipment($ccs), '2026-10-22', $returned);
        $this->ccjLoan = $this->makeLoan($this->student, $this->makeEquipment($ccj, ['name' => 'CCJ Camera']), '2026-10-22', $returned);
    }

    private function record(BorrowTransaction $loan, string $type, object $as, string $guard = 'dept')
    {
        $route = $guard === 'admin' ? 'admin.penalties.store' : 'department.penalties.store';

        return $this->actingAs($as, $guard)->postJson(route($route), [
            'transaction_id' => $loan->transaction_id,
            'type' => $type,
            'amount' => '250',
            'description' => 'Charger port damaged, found during the next check.',
        ]);
    }

    public function test_the_penalties_page_offers_their_own_returned_loans(): void
    {
        $this->actingAs($this->ccsLab, 'dept')->get(route('department.penalties'))
            ->assertOk()
            ->assertSee('id="addPenaltyBtn"', false);

        $loans = collect($this->actingAs($this->ccsLab, 'dept')->getJson(route('department.penalties.data'))->json('returnedLoans'));
        $this->assertTrue($loans->contains('id', $this->ccsLoan->transaction_id));
        $this->assertFalse($loans->contains('id', $this->ccjLoan->transaction_id));
        $this->assertContains('Damaged', $loans->firstWhere('id', $this->ccsLoan->transaction_id)['types']);
    }

    public function test_lab_personnel_record_a_penalty_on_their_returned_loan(): void
    {
        $response = $this->record($this->ccsLoan, 'Damaged', $this->ccsLab)
            ->assertOk()
            ->assertJson(['message' => 'Penalty recorded.']);

        $penalty = Penalty::where('transaction_id', $this->ccsLoan->transaction_id)->firstOrFail();
        $this->assertSame('Pending', $penalty->status);
        $this->assertSame($this->ccsLab->dept_acc_id, (int) $penalty->recorded_by_dept_acc_id);
        $this->assertNull($penalty->recorded_by_admin_id);
        $this->assertSame($this->student->user_id, (int) $penalty->user_id);
        $this->assertTrue(collect($response->json('penalties'))->contains('id', $penalty->penalty_id));

        $audit = AuditTrail::where('affected_entity', 'penalty:' . $penalty->penalty_id)->firstOrFail();
        $this->assertSame($this->ccsLab->dept_acc_id, (int) $audit->dept_acc_id);
        $this->assertSame('Recorded Penalty', AuditCatalog::classify($audit->action, $audit->affected_entity)['label']);

        // The same type again on the same loan is refused.
        $this->record($this->ccsLoan, 'Damaged', $this->ccsLab)->assertStatus(400);
        $this->assertSame(1, Penalty::where('transaction_id', $this->ccsLoan->transaction_id)->count());
    }

    public function test_loans_still_out_and_other_departments_loans_are_refused(): void
    {
        $active = $this->makeLoan($this->student, $this->ccsLoan->request->equipment, '2026-11-05');
        $this->record($active, 'Damaged', $this->ccsLab)->assertStatus(400);

        $this->record($this->ccjLoan, 'Damaged', $this->ccsLab)->assertNotFound()->assertJson(['message' => 'Returned loan not found.']);
        $this->assertSame(0, Penalty::count());

        // The CCJ Lab Personnel can record on their own loan.
        $this->record($this->ccjLoan, 'Damaged', $this->ccjLab)->assertOk();
    }

    public function test_invalid_penalties_are_refused(): void
    {
        $this->actingAs($this->ccsLab, 'dept')->postJson(route('department.penalties.store'), [
            'transaction_id' => $this->ccsLoan->transaction_id, 'type' => 'Lost', 'description' => '',
        ])->assertUnprocessable();

        $this->actingAs($this->ccsLab, 'dept')->postJson(route('department.penalties.store'), [
            'transaction_id' => $this->ccsLoan->transaction_id, 'type' => 'Stolen', 'description' => 'x',
        ])->assertUnprocessable();
    }

    public function test_the_admin_can_still_record_on_any_loan(): void
    {
        $loans = collect($this->actingAs($this->admin, 'admin')->getJson(route('admin.penalties.data'))->json('returnedLoans'));
        $this->assertTrue($loans->contains('id', $this->ccsLoan->transaction_id));
        $this->assertTrue($loans->contains('id', $this->ccjLoan->transaction_id));

        $this->record($this->ccjLoan, 'Lost', $this->admin, 'admin')->assertOk();
        $this->assertNotNull(Penalty::where('transaction_id', $this->ccjLoan->transaction_id)->value('recorded_by_admin_id'));
    }

    public function test_an_account_without_a_department_cannot_record(): void
    {
        $noDepartment = $this->makeLabPersonnel(null, 'nodept@example.com');

        $this->record($this->ccsLoan, 'Late Return', $noDepartment)->assertNotFound();
        $this->assertSame(0, Penalty::count());
    }

    public function test_students_cannot_record_penalties(): void
    {
        $this->record($this->ccsLoan, 'Late Return', $this->student, 'user')->assertUnauthorized();
        $this->assertSame(0, Penalty::count());
    }
}
