<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use App\Rules\UniqueAccountEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Editing an existing account's details: Students, Faculty members,
 * Laboratory Personnel (department accounts) and Administrators. Used by the
 * Admin Users page; the department Users page reuses it with its department
 * fixed.
 *
 * Passwords are not edited here (the owner uses Change Password or Forgot
 * Password), and a Student cannot become Faculty or the other way round, since
 * each keeps its own profile table.
 *
 * Every saved change is written to the audit trail as "Updated user account",
 * listing exactly which fields changed.
 */
class UserAccountEditor
{
    public const YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year'];

    public const DEPT_ROLES = ['Department Head', 'Department Staff', 'Department Admin'];

    private const LABELS = [
        'first_name' => 'First name',
        'last_name' => 'Last name',
        'full_name' => 'Full name',
        'name' => 'Full name',
        'id_number' => 'Student ID',
        'year_level' => 'Year level',
        'faculty_id_number' => 'Faculty ID',
        'teaching_license_no' => 'Teaching license no.',
        'highest_educational_attainment' => 'Educational attainment',
        'employee_id' => 'Employee ID',
        'role' => 'Role',
        'department_id' => 'Department',
        'email' => 'Email',
        'address' => 'Address',
    ];

    /** Validation rules for the account's type. */
    public static function rules(UserAccount|DepartmentAccount|Admin $account): array
    {
        if ($account instanceof Admin) {
            return [
                'name' => ['required', 'string', 'max:100'],
                'employee_id' => ['nullable', 'string', 'max:50'],
                // An admin's email is also their username (kept in sync, as on the profile page).
                'email' => ['required', 'email', 'max:100', Rule::unique('admin', 'email')->ignore($account->admin_id, 'admin_id'), Rule::unique('admin', 'username')->ignore($account->admin_id, 'admin_id'), UniqueAccountEmail::except(UniqueAccountEmail::ADMIN, $account->admin_id)],
            ];
        }

        $department = ['required', 'integer', Rule::exists('department', 'department_id')];

        if ($account instanceof DepartmentAccount) {
            return [
                'full_name' => ['required', 'string', 'max:255'],
                'employee_id' => ['required', 'string', 'max:100', Rule::unique('department_account', 'employee_id')->ignore($account->dept_acc_id, 'dept_acc_id')],
                // Keeps an older role value valid if it is not one of today's options.
                'role' => ['required', Rule::in(array_unique([...self::DEPT_ROLES, (string) $account->role]))],
                'department_id' => $department,
                'email' => ['required', 'email', 'max:100', Rule::unique('department_account', 'email')->ignore($account->dept_acc_id, 'dept_acc_id'), UniqueAccountEmail::except(UniqueAccountEmail::DEPT, $account->dept_acc_id)],
            ];
        }

        $common = [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'department_id' => $department,
            'email' => ['required', 'email', 'max:100', Rule::unique('user_account', 'email')->ignore($account->user_id, 'user_id'), UniqueAccountEmail::except(UniqueAccountEmail::USER, $account->user_id)],
            'address' => ['required', 'string', 'max:150'],
        ];

        if ($account->role === 'Faculty') {
            return $common + [
                'faculty_id_number' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('faculty_member', 'faculty_id_number')->ignore($account->user_id, 'user_id')],
                'teaching_license_no' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('faculty_member', 'teaching_license_no')->ignore($account->user_id, 'user_id')],
                'highest_educational_attainment' => ['nullable', 'string', 'max:100'],
            ];
        }

        return $common + [
            'id_number' => ['required', 'string', 'regex:/^\d{8}$/', Rule::unique('student', 'id_number')->ignore($account->user_id, 'user_id')],
            'year_level' => ['required', Rule::in(self::YEAR_LEVELS)],
        ];
    }

    public static function messages(): array
    {
        return [
            'id_number.regex' => 'Student ID must be exactly 8 digits.',
            'id_number.unique' => 'Another student already has this student ID.',
            'faculty_id_number.regex' => 'The faculty ID number may contain only letters, numbers and hyphens.',
            'faculty_id_number.unique' => 'Another faculty member already has this faculty ID number.',
            'teaching_license_no.regex' => 'The teaching license number may contain only letters, numbers and hyphens.',
            'teaching_license_no.unique' => 'Another faculty member already has this teaching license number.',
            'employee_id.unique' => 'Another Laboratory Personnel account already has this employee ID.',
            'email.unique' => 'Another account already uses this email address.',
            'department_id.required' => 'Select a department.',
            'department_id.exists' => 'Select a valid department.',
        ];
    }

    /** The account's current values, as the edit form needs them. */
    public static function values(UserAccount|DepartmentAccount|Admin $account): array
    {
        if ($account instanceof Admin) {
            return [
                'name' => (string) $account->name,
                'employee_id' => (string) $account->employee_id,
                'email' => (string) ($account->email ?: $account->username),
            ];
        }

        if ($account instanceof DepartmentAccount) {
            return [
                'full_name' => (string) $account->full_name,
                'employee_id' => (string) $account->employee_id,
                'role' => (string) $account->role,
                'department_id' => $account->department_id,
                'email' => (string) $account->email,
            ];
        }

        $isFaculty = $account->role === 'Faculty';
        $profile = $isFaculty ? $account->facultyMember : $account->student;
        $values = [
            'first_name' => (string) ($profile?->first_name ?? ''),
            'last_name' => (string) ($profile?->last_name ?? ''),
            'department_id' => $account->department_id,
            'email' => (string) $account->email,
            'address' => (string) ($profile?->address ?? ''),
        ];

        return $values + ($isFaculty
            ? [
                'faculty_id_number' => (string) ($profile?->faculty_id_number ?? ''),
                'teaching_license_no' => (string) ($profile?->teaching_license_no ?? ''),
                'highest_educational_attainment' => (string) ($profile?->highest_educational_attainment ?? ''),
            ]
            : [
                'id_number' => (string) ($profile?->id_number ?? ''),
                'year_level' => (string) ($profile?->year_level ?? ''),
            ]);
    }

    /**
     * Saves validated values and records what changed.
     *
     * @return string[] labels of the fields that changed (empty: nothing to save)
     */
    public static function update(UserAccount|DepartmentAccount|Admin $account, array $data, array $actor, ?Request $request = null): array
    {
        $before = self::values($account);
        $after = array_intersect_key($data, $before) + $before;
        foreach (['faculty_id_number', 'teaching_license_no'] as $field) {
            if (isset($after[$field])) {
                $after[$field] = strtoupper(trim($after[$field]));
            }
        }

        $changed = AuditLogger::changedFields($before, $after, array_intersect_key(self::LABELS, $before));
        if (!$changed) {
            return [];
        }

        DB::transaction(function () use ($account, $after) {
            if ($account instanceof Admin) {
                $account->update([
                    'name' => $after['name'],
                    'employee_id' => ($after['employee_id'] ?? '') ?: null,
                    'email' => $after['email'],
                    'username' => $after['email'],
                ]);

                return;
            }

            if ($account instanceof DepartmentAccount) {
                $account->update([
                    'full_name' => $after['full_name'],
                    'employee_id' => $after['employee_id'],
                    'role' => $after['role'],
                    'department_id' => (int) $after['department_id'],
                    'email' => $after['email'],
                ]);

                return;
            }

            $account->update([
                'email' => $after['email'],
                'department_id' => (int) $after['department_id'],
            ]);

            $profile = [
                'first_name' => $after['first_name'],
                'last_name' => $after['last_name'],
                'address' => $after['address'],
            ];

            if ($account->role === 'Faculty') {
                $account->facultyMember()->updateOrCreate(['user_id' => $account->user_id], $profile + [
                    'faculty_id_number' => $after['faculty_id_number'],
                    'teaching_license_no' => $after['teaching_license_no'],
                    'highest_educational_attainment' => ($after['highest_educational_attainment'] ?? '') ?: null,
                ]);
            } else {
                $account->student()->updateOrCreate(['user_id' => $account->user_id], $profile + [
                    'id_number' => $after['id_number'],
                    'year_level' => $after['year_level'],
                ]);
            }
        });

        [$name, $entity] = match (true) {
            $account instanceof Admin => [$after['name'], 'admin:' . $account->admin_id],
            $account instanceof DepartmentAccount => [$after['full_name'], 'department_account:' . $account->dept_acc_id],
            default => [trim($after['first_name'] . ' ' . $after['last_name']), 'user_account:' . $account->user_id],
        };
        AuditLogger::log(
            'Updated user account',
            $entity,
            $name . ' (' . $after['email'] . ') · Changed: ' . implode(', ', $changed),
            $actor,
            $request,
        );

        return $changed;
    }
}
