<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\Penalty;
use App\Models\PenaltySubmission;
use App\Models\PenaltySubmissionFile;
use App\Models\UserAccount;
use App\Support\DepartmentScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * The central rule "Lab Personnel only see and change their own department's
 * records" (App\Models\Concerns\BelongsToDepartment, App\Support\DepartmentScope).
 *
 * The page sweeps go through every Department route, so a page added later
 * is checked automatically.
 */
class DepartmentAccessRulesTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    private Department $it;
    private Department $nursing;
    private DepartmentAccount $itLab;
    private DepartmentAccount $nursingLab;
    private Equipment $equipment;
    private UserAccount $student;
    private BorrowRequest $pendingRequest;
    private BorrowTransaction $readyLoan;
    private BorrowTransaction $returnedLoan;
    private Penalty $penalty;
    private PenaltySubmission $proof;
    private PenaltySubmissionFile $proofFile;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->it = $this->makeDepartment('College of Information Technology', 'CIT');
        $this->nursing = $this->makeDepartment('College of Nursing', 'CON');
        $this->itLab = $this->makeLabPersonnel($this->it, 'it-lab@example.com', 'IT Custodian');
        $this->nursingLab = $this->makeLabPersonnel($this->nursing, 'nursing-lab@example.com', 'Nursing Custodian');

        // Everything below belongs to IT (the equipment's department).
        $this->equipment = $this->makeEquipment($this->it, ['name' => 'Zephyr Projector']);
        $this->student = $this->makeStudent($this->it, 'quillon@example.com', ['first_name' => 'Quillon', 'last_name' => 'Marsh']);

        $this->pendingRequest = $this->makePendingRequest($this->student, $this->equipment);
        $this->readyLoan = $this->makeLoan($this->student, $this->equipment, '2026-12-01', ['status' => BorrowTransaction::STATUS_READY]);
        $this->returnedLoan = $this->makeLoan($this->student, $this->equipment, '2026-12-01', [
            'return_status' => BorrowTransaction::RETURN_PENDING,
            'return_submitted_at' => now(),
        ]);

        $this->penalty = Penalty::create([
            'transaction_id' => $this->returnedLoan->transaction_id,
            'user_id' => $this->student->user_id,
            'type' => Penalty::TYPE_DAMAGED,
            'amount' => 500,
            'description' => 'Cracked lens',
            'status' => Penalty::STATUS_PENDING,
            'recorded_by_dept_acc_id' => $this->itLab->dept_acc_id,
            'recorded_at' => now(),
        ]);
        $this->proof = PenaltySubmission::create([
            'penalty_id' => $this->penalty->penalty_id,
            'user_id' => $this->student->user_id,
            'type' => PenaltySubmission::TYPE_PAYMENT,
            'reference_no' => 'OR-1',
            'amount_paid' => 500,
            'settled_on' => now()->toDateString(),
            'status' => PenaltySubmission::STATUS_REVIEW,
            'submitted_at' => now(),
        ]);
        Storage::disk('local')->put('penalty-proofs/receipt.jpg', 'receipt');
        $this->proofFile = PenaltySubmissionFile::create([
            'submission_id' => $this->proof->submission_id,
            'path' => 'penalty-proofs/receipt.jpg',
            'original_name' => 'receipt.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 7,
        ]);
    }

    public function test_each_kind_of_record_belongs_to_the_department_that_owns_the_equipment(): void
    {
        // A Nursing student borrowing IT's equipment is still IT's request.
        $nursingStudent = $this->makeStudent($this->nursing, 'nurse@example.com');
        $request = $this->makePendingRequest($nursingStudent, $this->equipment);

        $this->actingAs($this->itLab, 'dept');
        $this->assertSame($this->it->department_id, DepartmentScope::id());
        $this->assertTrue(DepartmentScope::requests()->whereKey($request->request_id)->exists());
        $this->assertTrue(DepartmentScope::equipment()->whereKey($this->equipment->equipment_id)->exists());
        $this->assertTrue(DepartmentScope::loans()->whereKey($this->readyLoan->transaction_id)->exists());
        $this->assertTrue(DepartmentScope::penalties()->whereKey($this->penalty->penalty_id)->exists());
        $this->assertTrue(DepartmentScope::proofs()->whereKey($this->proof->submission_id)->exists());
        $this->assertTrue(DepartmentScope::users()->whereKey($this->student->user_id)->exists());
        $this->assertFalse(DepartmentScope::users()->whereKey($nursingStudent->user_id)->exists());

        $this->actingAs($this->nursingLab, 'dept');
        $this->assertSame(0, DepartmentScope::requests()->count());
        $this->assertSame(0, DepartmentScope::equipment()->count());
        $this->assertSame(0, DepartmentScope::loans()->count());
        $this->assertSame(0, DepartmentScope::penalties()->count());
        $this->assertSame(0, DepartmentScope::proofs()->count());
        $this->assertSame([$nursingStudent->user_id], DepartmentScope::users()->pluck('user_id')->all());
    }

    public function test_an_account_without_a_department_sees_nothing(): void
    {
        $this->actingAs($this->makeLabPersonnel(null, 'floating@example.com'), 'dept');

        $this->assertNull(DepartmentScope::id());
        foreach (['equipment', 'requests', 'loans', 'penalties', 'proofs', 'users'] as $scope) {
            $this->assertSame(0, DepartmentScope::$scope()->count(), $scope);
        }
        $this->assertSame(0, Penalty::forDepartment(0)->count());
    }

    /** Every Department page and data feed, signed in as another department. */
    public function test_no_department_page_shows_another_departments_records(): void
    {
        // Sanity: IT's own pages do show them, so the sweep below can't pass by accident.
        $this->actingAs($this->itLab, 'dept');
        $this->getJson(route('department.equipment.data'))->assertOk()->assertSee('Zephyr Projector');
        $this->getJson(route('department.requests.data'))->assertOk()->assertSee('Quillon');
        $this->getJson(route('department.users.data'))->assertOk()->assertSee('Quillon');
        $this->getJson(route('department.penalties.data'))->assertOk()->assertSee('Cracked lens');

        $this->actingAs($this->nursingLab, 'dept');
        $checked = 0;

        foreach ($this->departmentRoutes('GET') as $route) {
            $params = $this->routeParameters($route);
            $response = $this->get(route($route->getName(), $params), ['Accept' => 'application/json, text/html']);

            if (str_contains($route->uri(), '{')) {
                // Someone else's file by its id.
                $this->assertContains($response->status(), [403, 404], $route->getName());
            } else {
                $response->assertOk();
                $body = $response->getContent();
                $this->assertStringNotContainsString('Zephyr Projector', $body, $route->getName());
                $this->assertStringNotContainsString('Quillon', $body, $route->getName());
                $this->assertStringNotContainsString('Cracked lens', $body, $route->getName());
            }
            $checked++;
        }

        $this->assertGreaterThan(15, $checked);
    }

    /** Every Department action, aimed at IT's records by a Nursing account. */
    public function test_no_department_action_changes_another_departments_records(): void
    {
        $before = $this->itSnapshot();
        $this->actingAs($this->nursingLab, 'dept');

        // Actions on the signed-in account itself, not on other records.
        $own = ['department.profile.update', 'department.profile.avatar', 'department.profile.password', 'department.notifications.read', 'department.users.student', 'department.users.faculty', 'department.penalties.store'];

        foreach ($this->departmentRoutes('POST') as $route) {
            $name = $route->getName();
            // `status` means a different thing on these two actions than on user accounts.
            $status = ['department.equipment.save' => 'Available', 'department.requests.update' => 'Approved'][$name] ?? null;
            $response = $this->postJson(route($name), ($status ? ['status' => $status] : []) + $this->probe());

            if (!in_array($name, $own, true)) {
                $this->assertContains($response->status(), [403, 404], $name . ' answered ' . $response->status() . ': ' . $response->getContent());
            }
        }

        // Recording a penalty on IT's loan is refused too.
        $this->postJson(route('department.penalties.store'), $this->probe())->assertNotFound();

        $this->assertSame($before, $this->itSnapshot());
    }

    /**
     * Guards the code itself: department ownership is only worked out in the
     * models, and department pages read their department from DepartmentScope.
     */
    public function test_department_filters_are_not_written_by_hand(): void
    {
        $byHand = '/whereHas\(\s*\'[a-z.]*equipment\'[^;]*department_id/s';

        foreach (File::allFiles(app_path()) as $file) {
            if (str_starts_with($file->getRelativePathname(), 'Models')) {
                continue;
            }

            $this->assertDoesNotMatchRegularExpression($byHand, $file->getContents(),
                $file->getRelativePathname() . ' filters by equipment department by hand; use Model::forDepartment() or DepartmentScope.');
        }

        foreach (File::allFiles(app_path('Http/Controllers/Department')) as $file) {
            $this->assertDoesNotMatchRegularExpression("/auth\('dept'\)->user\(\)\??->department_id/", $file->getContents(),
                $file->getFilename() . ' reads the department directly; use DepartmentScope::id().');
        }
    }

    /** @return array<int, \Illuminate\Routing\Route> */
    private function departmentRoutes(string $method): array
    {
        return array_values(array_filter(
            Route::getRoutes()->getRoutes(),
            fn ($route) => str_starts_with((string) $route->getName(), 'department.') && in_array($method, $route->methods(), true),
        ));
    }

    private function routeParameters($route): array
    {
        return collect($route->parameterNames())
            ->mapWithKeys(fn ($name) => [$name => $name === 'file' ? $this->proofFile->file_id : 1])
            ->all();
    }

    /** One request body that passes validation on every action, pointing at IT's records. */
    private function probe(): array
    {
        return [
            'request_id' => $this->pendingRequest->request_id,
            'transaction_id' => $this->returnedLoan->transaction_id,
            'equipment_id' => $this->equipment->equipment_id,
            'user_id' => $this->student->user_id,
            'penalty_id' => $this->penalty->penalty_id,
            'submission_id' => $this->proof->submission_id,
            'status' => 'Active',
            'reason' => 'Probe',
            'reject_reason' => 'Probe',
            'note' => 'Probe',
            'condition' => 'Good',
            'remarks' => 'Probe',
            'type' => Penalty::TYPE_LOST,
            'amount' => 100,
            'description' => 'Probe',
            'reference_no' => 'Probe',
            'item_received' => true,
            'id_verified' => true,
            'name' => 'Probe',
            'brand' => 'Probe',
            'serial_number' => 'PROBE-1',
            'available_qty' => 1,
            'total_qty' => 1,
            'category_id' => $this->equipment->category_id,
        ];
    }

    /** IT's records as stored, to prove nothing changed. */
    private function itSnapshot(): array
    {
        return [
            'equipment' => Equipment::whereKey($this->equipment->equipment_id)->first()->getAttributes(),
            'user' => UserAccount::whereKey($this->student->user_id)->first()->getAttributes(),
            'requests' => BorrowRequest::orderBy('request_id')->get()->map->getAttributes()->all(),
            'loans' => BorrowTransaction::orderBy('transaction_id')->get()->map->getAttributes()->all(),
            'penalties' => Penalty::orderBy('penalty_id')->get()->map->getAttributes()->all(),
            'proofs' => PenaltySubmission::orderBy('submission_id')->get()->map->getAttributes()->all(),
            'files' => PenaltySubmissionFile::count(),
        ];
    }
}
