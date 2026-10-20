<?php

namespace Tests\Feature;

use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Department Equipment Monitoring: overdue loans are listed as Overdue with a
 * View action only. There is no "send overdue notice" button: it only showed
 * a "sent" message without sending anything, and borrowers already get an
 * automatic overdue notification in their own bell.
 */
class DepartmentMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_overdue_loans_are_listed_without_a_fake_notice_button(): void
    {
        $department = Department::create([
            'department_name' => 'College of Computer Studies',
            'department_code' => 'CCS',
        ]);

        $category = EquipmentCategory::create(['category_name' => 'Laptop']);

        $equipment = Equipment::create([
            'name' => 'Dell Latitude',
            'brand' => 'Dell',
            'serial_number' => 'EQ-0001',
            'image' => '',
            'available_qty' => 1,
            'total_qty' => 2,
            'status' => 'Available',
            'category_id' => $category->category_id,
            'department_id' => $department->department_id,
        ]);

        $user = UserAccount::create([
            'role' => 'Student',
            'email' => 'student@example.com',
            'password' => Hash::make('password'),
            'department_id' => $department->department_id,
            'status' => 'Active',
        ]);

        Student::create([
            'user_id' => $user->user_id,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'id_number' => '2020-0001',
            'year_level' => '3',
            'address' => 'Cebu City',
        ]);

        $request = BorrowRequest::create([
            'user_id' => $user->user_id,
            'equipment_id' => $equipment->equipment_id,
            'quantity' => 1,
            'purpose' => 'Research',
            'date_needed' => now()->subDays(5)->toDateString(),
            'borrow_date' => now()->subDays(5)->toDateString(),
            'return_date' => now()->subDays(2)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'dept_status' => 'Approved',
            'overall_status' => 'Approved',
        ]);

        BorrowTransaction::create([
            'request_id' => $request->request_id,
            'borrow_date' => now()->subDays(5)->toDateString(),
            'due_date' => now()->subDays(2)->toDateString(),
            'status' => 'Active',
        ]);

        $deptAccount = DepartmentAccount::create([
            'full_name' => 'Lab Custodian',
            'email' => 'dept@example.com',
            'password' => Hash::make('password'),
            'role' => 'Department Head',
            'department_id' => $department->department_id,
            'status' => 'Active',
        ]);

        $response = $this->actingAs($deptAccount, 'dept')->get(route('department.monitoring'));

        $response->assertOk()
            ->assertViewHas('dbMonitoring', fn (array $rows) => collect($rows)
                ->contains(fn ($row) => $row['status'] === 'Overdue'
                    && $row['equipment'] === 'Dell Latitude'
                    && $row['serial'] === 'EQ-0001'
                    && $row['daysOverdue'] === 2
                    && $row['daysUntilDue'] === null
                    && array_key_exists('avatar', $row)))
            // View Details: borrower, equipment and loan sections; the borrower
            // picture is drawn locally, never fetched from an outside service.
            ->assertSeeInOrder(['id="viewBorrowerHeading"', 'id="viewEquipmentHeading"', 'id="viewLoanHeading"'], false)
            ->assertDontSee('ui-avatars.com', false)
            ->assertSeeInOrder(['>Equipment</th>', '>User</th>', '>Borrowed</th>', '>Due</th>', '>Status</th>', '>Action</th>'], false)
            ->assertDontSee('>Role</th>', false)
            ->assertSee('btn-action-view', false)
            ->assertDontSee('sendOverdueNotice', false)
            ->assertDontSee('btn-action-alert', false)
            ->assertDontSee('Overdue return notification sent', false);
    }
}
