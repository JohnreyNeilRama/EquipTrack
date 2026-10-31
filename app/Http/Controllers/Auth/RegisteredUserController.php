<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\FacultyMember;
use App\Models\Student;
use App\Models\UserAccount;
use App\Rules\UniqueAccountEmail;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function show(): View
    {
        return view('auth.register', [
            'departments' => Department::orderBy('department_name')->get(['department_id', 'department_name', 'department_code']),
        ]);
    }

    /**
     * Public registration is limited to Student/Faculty.
     * (The legacy form also offered "Admin" — that hole is deliberately not ported.)
     */
    public function store(Request $request): RedirectResponse
    {
        $isStudent = $request->input('role') === 'student';

        // Faculty IDs are stored upper-case, the same as when staff add them,
        // so "t-1001" and "T-1001" count as the same ID.
        if (is_string($request->input('id_number'))) {
            $idNumber = trim($request->input('id_number'));
            $request->merge(['id_number' => $isStudent ? $idNumber : strtoupper($idNumber)]);
        }

        $data = $request->validate([
            'role' => ['required', Rule::in(['student', 'teacher'])],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            // Checked here so a taken ID shows a message instead of failing on
            // the database's unique key.
            'id_number' => $isStudent
                ? ['required', 'string', 'regex:/^\d{8}$/', Rule::unique('student', 'id_number')]
                : [
                    'required', 'string', 'max:20', 'regex:/^[A-Za-z0-9-]+$/',
                    Rule::unique('faculty_member', 'faculty_id_number'),
                    // The teaching license number is derived from the ID and is unique too.
                    function (string $attribute, mixed $value, \Closure $fail) {
                        if (FacultyMember::where('teaching_license_no', 'TL-' . $value)->exists()) {
                            $fail('Another faculty member already has this faculty ID number.');
                        }
                    },
                ],
            'year_level' => ['nullable', 'string'],
            // The department decides which Lab Personnel see and manage this user.
            'department_id' => ['required', 'integer', Rule::exists('department', 'department_id')],
            'email' => ['required', 'email', 'max:100', Rule::unique('user_account', 'email'), new UniqueAccountEmail()],
            'address' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', Password::defaults()],
        ], [
            'id_number.regex' => $isStudent
                ? 'Student ID must be exactly 8 digits.'
                : 'The faculty ID number may contain only letters, numbers and hyphens.',
            'id_number.unique' => $isStudent
                ? 'Another student already has this student ID.'
                : 'Another faculty member already has this faculty ID number.',
            'department_id.required' => 'Please select your department.',
            'department_id.exists' => 'Please select a valid department.',
        ]);

        $yearLevelMap = ['1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'];
        $role = $data['role'] === 'teacher' ? 'Faculty' : 'Student';

        $account = DB::transaction(function () use ($data, $role, $yearLevelMap) {
            $account = UserAccount::create([
                'role' => $role,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'department_id' => (int) $data['department_id'],
            ]);

            if ($role === 'Student') {
                Student::create([
                    'user_id' => $account->user_id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'id_number' => $data['id_number'],
                    'year_level' => $yearLevelMap[$data['year_level'] ?? ''] ?? ($data['year_level'] ?: '1st Year'),
                    'address' => $data['address'],
                ]);
            } else {
                FacultyMember::create([
                    'user_id' => $account->user_id,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'faculty_id_number' => $data['id_number'],
                    'teaching_license_no' => 'TL-' . $data['id_number'],
                    'address' => $data['address'],
                ]);
            }

            return $account;
        });

        AuditLogger::log(
            'Registered account',
            'user_account:' . $account->user_id,
            $role . ' self-registration (' . $account->email . ')',
            ['user_id' => $account->user_id],
            $request,
        );

        return redirect('/login?registered=1');
    }
}
