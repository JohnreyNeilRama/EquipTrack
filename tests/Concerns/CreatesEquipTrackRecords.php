<?php

namespace Tests\Concerns;

use App\Models\Admin;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Models\FacultyMember;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Support\Facades\Hash;

/**
 * Sample departments, accounts, equipment and loans for feature tests, so
 * each test only spells out what it is about.
 */
trait CreatesEquipTrackRecords
{
    protected function makeDepartment(string $name = 'College of Computer Studies', string $code = 'CCS'): Department
    {
        return Department::create(['department_name' => $name, 'department_code' => $code]);
    }

    protected function makeStudent(Department $department, string $email = 'student@example.com', array $profile = [], string $password = 'Study2026'): UserAccount
    {
        $account = UserAccount::create([
            'role' => 'Student',
            'email' => $email,
            'password' => Hash::make($password),
            'department_id' => $department->department_id,
            'status' => $profile['status'] ?? 'Active',
        ]);

        Student::create([
            'user_id' => $account->user_id,
            'first_name' => $profile['first_name'] ?? 'Juan',
            'last_name' => $profile['last_name'] ?? 'Dela Cruz',
            'id_number' => $profile['id_number'] ?? '2020' . str_pad((string) $account->user_id, 4, '0', STR_PAD_LEFT),
            'year_level' => $profile['year_level'] ?? '2nd Year',
            'address' => 'Cebu City',
        ]);

        return $account;
    }

    protected function makeFaculty(Department $department, string $email = 'faculty@example.com', array $profile = []): UserAccount
    {
        $account = UserAccount::create([
            'role' => 'Faculty',
            'email' => $email,
            'password' => Hash::make('Teach2026'),
            'department_id' => $department->department_id,
            'status' => 'Active',
        ]);

        FacultyMember::create([
            'user_id' => $account->user_id,
            'first_name' => $profile['first_name'] ?? 'Ramon',
            'last_name' => $profile['last_name'] ?? 'Reyes',
            'faculty_id_number' => $profile['faculty_id_number'] ?? 'T-' . $account->user_id,
            'teaching_license_no' => $profile['teaching_license_no'] ?? 'PRC-' . $account->user_id,
            'highest_educational_attainment' => $profile['highest_educational_attainment'] ?? 'MIT',
            'address' => 'Cebu City',
        ]);

        return $account;
    }

    protected function makeLabPersonnel(?Department $department, string $email = 'lab@example.com', string $name = 'Lab Custodian', array $extra = []): DepartmentAccount
    {
        return DepartmentAccount::create($extra + [
            'full_name' => $name,
            'email' => $email,
            'password' => Hash::make('Lab2026pass'),
            'role' => 'Department Head',
            'department_id' => $department?->department_id,
            'status' => 'Active',
        ]);
    }

    protected function makeAdmin(string $email = 'admin@example.com', string $name = 'System Admin', string $password = 'Admin2026'): Admin
    {
        return Admin::create([
            'name' => $name,
            'username' => $email,
            'email' => $email,
            'password' => Hash::make($password),
            'status' => 'Active',
        ]);
    }

    protected function makeEquipment(Department $department, array $extra = []): Equipment
    {
        $category = EquipmentCategory::firstOrCreate(['category_name' => 'Laptop']);

        return Equipment::create($extra + [
            'name' => 'Dell Latitude',
            'brand' => 'Dell',
            'serial_number' => 'EQ-' . uniqid(),
            'image' => '',
            'available_qty' => 3,
            'total_qty' => 3,
            'status' => 'Available',
            'classification' => Equipment::CLASS_NOT_EXPENSIVE,
            'category_id' => $category->category_id,
            'department_id' => $department->department_id,
        ]);
    }

    /** First validation message of the last form post (the session keeps the error bag). */
    protected function firstError(): string
    {
        $errors = session('errors');

        return is_object($errors)
            ? (string) $errors->first()
            : (string) collect($errors['default']['messages'] ?? [])->flatten()->first();
    }

    /** A borrow request that nobody has reviewed yet. */
    protected function makePendingRequest(UserAccount $user, Equipment $equipment, array $extra = []): BorrowRequest
    {
        return BorrowRequest::create($extra + [
            'user_id' => $user->user_id,
            'equipment_id' => $equipment->equipment_id,
            'quantity' => 1,
            'equipment_classification' => $equipment->classification,
            'purpose' => 'Laboratory activity',
            'date_needed' => now()->addDays(2)->toDateString(),
            'return_date' => now()->addDays(4)->toDateString(),
            'admin_status' => 'Pending',
            'dept_status' => 'Pending',
            'overall_status' => 'Pending',
        ]);
    }

    /** An approved request with its loan (still out unless $loan says otherwise). */
    protected function makeLoan(UserAccount $user, Equipment $equipment, string $dueDate, array $loan = [], string $borrowDate = '2026-10-20'): BorrowTransaction
    {
        $request = $this->makePendingRequest($user, $equipment, [
            'date_needed' => $borrowDate,
            'return_date' => $dueDate,
            'dept_status' => 'Approved',
            'admin_status' => $equipment->classification === Equipment::CLASS_EXPENSIVE ? 'Approved' : 'Pending',
            'overall_status' => 'Approved',
        ]);

        return BorrowTransaction::create($loan + [
            'request_id' => $request->request_id,
            'borrow_date' => $borrowDate,
            'due_date' => $dueDate,
            'status' => 'Active',
        ]);
    }
}
