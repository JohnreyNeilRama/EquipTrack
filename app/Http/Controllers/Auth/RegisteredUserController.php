<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\FacultyMember;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function show(): View
    {
        return view('auth.register');
    }

    /**
     * Public registration is limited to Student/Faculty.
     * (The legacy form also offered "Admin" — that hole is deliberately not ported.)
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['student', 'teacher'])],
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'id_number' => [
                'required', 'string', 'max:20',
                $request->input('role') === 'student' ? 'regex:/^\d{8}$/' : 'nullable',
            ],
            'year_level' => ['nullable', 'string'],
            'email' => ['required', 'email', 'max:100', Rule::unique('user_account', 'email')],
            'address' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:6'],
        ], [
            'id_number.regex' => 'Student ID must be exactly 8 digits.',
        ]);

        $yearLevelMap = ['1' => '1st Year', '2' => '2nd Year', '3' => '3rd Year', '4' => '4th Year'];
        $role = $data['role'] === 'teacher' ? 'Faculty' : 'Student';

        $department = Department::orderBy('department_id')->first();

        DB::transaction(function () use ($data, $role, $yearLevelMap, $department) {
            $account = UserAccount::create([
                'role' => $role,
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'department_id' => $department?->department_id,
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
        });

        return redirect('/login?registered=1');
    }
}
