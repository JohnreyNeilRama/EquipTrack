<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\Department;
use App\Services\AuditCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Admin Users page → Departments: a department (e.g. a duplicate added by
 * mistake) can be deleted only while nothing belongs to it, so no account,
 * user, equipment or borrowing history is ever lost or left without a
 * department.
 */
class AdminDeleteDepartmentTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Admin $admin;

    private Department $ccs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeAdmin();
        $this->ccs = $this->makeDepartment();
    }

    private function deleteDepartment(Department $department, ?object $as = null, string $guard = 'admin')
    {
        return $this->actingAs($as ?? $this->admin, $guard)->post(route('admin.users.department.delete'), [
            'department_id' => $department->department_id,
        ]);
    }

    public function test_an_unused_duplicate_department_can_be_deleted_and_is_audited(): void
    {
        $duplicate = Department::create(['department_name' => 'Computer Studies', 'department_code' => 'CS', 'college' => 'Main Campus']);

        $this->deleteDepartment($duplicate)
            ->assertRedirect(route('admin.users'))
            ->assertSessionHas('serverMsg', "Department 'Computer Studies' was deleted.")
            ->assertSessionHas('serverMsgType', 'success');

        $this->assertNull(Department::find($duplicate->department_id));
        $this->assertNotNull($this->ccs->fresh());

        $audit = AuditTrail::where('affected_entity', 'department:' . $duplicate->department_id)->firstOrFail();
        $this->assertSame('Deleted department CS', $audit->action);
        $this->assertSame('Computer Studies (CS), Main Campus', $audit->details);
        $this->assertSame('Deleted Department', AuditCatalog::classify($audit->action)['label']);
        // A deleted Department Account keeps its own label.
        $this->assertSame('Deleted User', AuditCatalog::classify('Deleted department account')['label']);
    }

    public function test_a_department_with_anything_linked_to_it_is_kept(): void
    {
        $cases = [
            'account' => fn (Department $d) => $this->makeLabPersonnel($d, 'lab' . $d->department_id . '@example.com', 'Lab', ['employee_id' => 'EMP-' . $d->department_id]),
            'user' => fn (Department $d) => $this->makeStudent($d, 'student' . $d->department_id . '@example.com', ['id_number' => sprintf('2026%04d', $d->department_id)]),
            'equipment' => fn (Department $d) => $this->makeEquipment($d),
        ];
        $expected = [
            'account' => 'its Department Account',
            'user' => '1 student or faculty account',
            'equipment' => '1 equipment item',
        ];

        foreach ($cases as $kind => $link) {
            $department = Department::create(['department_name' => 'Dept ' . $kind, 'department_code' => strtoupper($kind)]);
            $link($department);

            $this->deleteDepartment($department)
                ->assertSessionHas('serverMsgType', 'error')
                ->assertSessionHas('serverMsg', "'Dept {$kind}' can't be deleted because it still has {$expected[$kind]}. Move or remove them first, or correct the department's details instead.");
            $this->assertNotNull($department->fresh(), $kind);
        }

        $this->assertFalse(AuditTrail::where('action', 'like', 'Deleted department %')->exists());
    }

    public function test_a_department_with_borrowing_history_is_kept(): void
    {
        $student = $this->makeStudent($this->ccs, 'juan@example.com');
        $equipment = $this->makeEquipment($this->ccs);
        $loan = $this->makeLoan($student, $equipment, '2026-12-01');

        $this->deleteDepartment($this->ccs)->assertSessionHas('serverMsgType', 'error');

        $this->assertNotNull($this->ccs->fresh());
        $this->assertNotNull($equipment->fresh());
        $this->assertNotNull($loan->fresh());
        $this->assertSame($this->ccs->department_id, (int) $student->fresh()->department_id);
    }

    public function test_a_missing_department_gets_a_clear_message(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.users.department.delete'), ['department_id' => 99999])
            ->assertSessionHas('serverMsg', 'That department no longer exists.');
    }

    public function test_only_administrators_can_delete_departments(): void
    {
        $duplicate = Department::create(['department_name' => 'Computer Studies', 'department_code' => 'CS']);
        $lab = $this->makeLabPersonnel($this->ccs);

        $this->deleteDepartment($duplicate, $lab, 'dept')->assertRedirect();
        $this->app['auth']->forgetGuards();
        $this->post(route('admin.users.department.delete'), ['department_id' => $duplicate->department_id])->assertRedirect();

        $this->assertNotNull($duplicate->fresh());
    }

    public function test_the_departments_window_lists_usage_and_only_enables_delete_when_unused(): void
    {
        $this->makeLabPersonnel($this->ccs);
        Department::create(['department_name' => 'Computer Studies', 'department_code' => 'CS']);

        $html = $this->actingAs($this->admin, 'admin')->get(route('admin.users'))->assertOk()->getContent();

        $this->assertStringContainsString('id="manageDepartmentsBtn"', $html);
        $this->assertStringContainsString('id="departmentsListModal"', $html);
        $this->assertStringContainsString('Has Department Account', $html);
        $this->assertStringContainsString('Not used', $html);
        $this->assertMatchesRegularExpression('/data-name="College of Computer Studies \(CCS\)"\s+disabled/', $html);
        $this->assertDoesNotMatchRegularExpression('/data-name="Computer Studies \(CS\)"\s+disabled/', $html);
        $this->assertStringContainsString(json_encode(route('admin.users.department.delete')), $html);
    }
}
