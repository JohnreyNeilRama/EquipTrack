<?php

namespace App\Services;

use App\Models\FacultyMember;
use App\Models\Student;
use App\Models\UserAccount;
use App\Rules\UniqueAccountEmail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creating Student and Faculty accounts, shared by the Admin Users page and the
 * department Users page so both apply the same rules.
 *
 * The department is not part of these rules: the Admin picks one, while
 * Laboratory Personnel always add users to their own department. Callers pass
 * the department id to create().
 */
class UserAccountCreator
{
    public static function studentRules(): array
    {
        return self::personRules() + [
            'id_number' => ['required', 'string', 'regex:/^\d{8}$/', Rule::unique('student', 'id_number')],
            'year_level' => ['required', Rule::in(UserAccountEditor::YEAR_LEVELS)],
        ];
    }

    public static function facultyRules(): array
    {
        return self::personRules() + [
            'faculty_id_number' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('faculty_member', 'faculty_id_number')],
            'teaching_license_no' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9-]+$/', Rule::unique('faculty_member', 'teaching_license_no')],
            'highest_educational_attainment' => ['nullable', 'string', 'max:100'],
        ];
    }

    public static function messages(): array
    {
        return [
            'id_number.regex' => 'Student ID must be exactly 8 digits.',
            'id_number.unique' => 'Another student already has this student ID.',
            'faculty_id_number.required' => 'Enter the faculty ID number.',
            'faculty_id_number.regex' => 'The faculty ID number may contain only letters, numbers and hyphens.',
            'faculty_id_number.unique' => 'Another faculty member already has this faculty ID number.',
            'teaching_license_no.required' => 'Enter the teaching license number.',
            'teaching_license_no.regex' => 'The teaching license number may contain only letters, numbers and hyphens.',
            'teaching_license_no.unique' => 'Another faculty member already has this teaching license number.',
            'email.unique' => 'An account with email :input already exists.',
        ];
    }

    /**
     * Creates the account and its Student or Faculty profile in one transaction
     * and records it in the audit trail.
     *
     * @param  'Student'|'Faculty'  $role
     */
    public static function create(string $role, array $data, int $departmentId, array $actor, ?Request $request = null): UserAccount
    {
        $account = DB::transaction(function () use ($role, $data, $departmentId) {
            $account = UserAccount::create([
                'role' => $role,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'department_id' => $departmentId,
            ]);

            $profile = [
                'user_id' => $account->user_id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'address' => $data['address'],
            ];

            if ($role === 'Faculty') {
                FacultyMember::create($profile + [
                    'faculty_id_number' => strtoupper(trim($data['faculty_id_number'])),
                    'teaching_license_no' => strtoupper(trim($data['teaching_license_no'])),
                    'highest_educational_attainment' => ($data['highest_educational_attainment'] ?? null) ?: null,
                ]);
            } else {
                Student::create($profile + [
                    'id_number' => $data['id_number'],
                    'year_level' => $data['year_level'],
                ]);
            }

            return $account;
        });

        AuditLogger::log(
            $role === 'Faculty' ? 'Added faculty account' : 'Added student account',
            'user_account:' . $account->user_id,
            $data['first_name'] . ' ' . $data['last_name'] . ' (' . $data['email'] . ')',
            $actor,
            $request,
        );

        return $account;
    }

    private static function personRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:100', Rule::unique('user_account', 'email'), new UniqueAccountEmail()],
            'address' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', Password::defaults()],
        ];
    }
}
