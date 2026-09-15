<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Student;
use App\Models\UserAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminUsersController extends Controller
{
    public function index(): View
    {
        $dbUsers = $this->fetchDbUsers();
        $departmentsList = Department::orderBy('department_name')->get();

        return view('admin.users', compact('dbUsers', 'departmentsList'));
    }

    public function addStudent(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:50'],
            'last_name' => ['required', 'string', 'max:50'],
            'id_number' => ['required', 'string', 'regex:/^\d{8}$/'],
            'year_level' => ['required', 'string', 'max:20'],
            'department' => ['nullable', 'string'],
            'email' => ['required', 'email', 'max:100', Rule::unique('user_account', 'email')],
            'address' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:6'],
        ], [
            'id_number.regex' => 'Student ID must be exactly 8 digits.',
            'email.unique' => 'An account with email :input already exists.',
        ]);

        $deptName = trim($data['department'] ?? '');
        $dept = $deptName !== ''
            ? Department::where('department_name', $deptName)->first()
            : null;
        $deptId = $dept?->department_id ?? Department::orderBy('department_id')->value('department_id');

        // FIX vs legacy: the student-child insert was never checked, so a failure
        // left an account without a profile row while still reporting success.
        DB::transaction(function () use ($data, $deptId) {
            $account = UserAccount::create([
                'role' => 'Student',
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'department_id' => $deptId,
            ]);

            Student::create([
                'user_id' => $account->user_id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'id_number' => $data['id_number'],
                'year_level' => $data['year_level'],
                'address' => $data['address'],
            ]);
        });

        return redirect()
            ->route('admin.users')
            ->with('serverMsg', "Student account for '{$data['first_name']} {$data['last_name']}' created successfully!")
            ->with('serverMsgType', 'success');
    }

    public function addDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'employee_id' => ['required', 'string', 'max:100', Rule::unique('department_account', 'employee_id')],
            'email' => ['required', 'email', 'max:100', Rule::unique('department_account', 'email')],
            'role' => ['required', Rule::in(['Department Head', 'Department Staff', 'Department Admin'])],
            'department_id' => ['required', 'integer', Rule::exists('department', 'department_id')],
            'password' => ['required', 'string', 'min:6'],
        ], [
            'employee_id.unique' => 'A department account with this email or employee ID already exists.',
            'email.unique' => 'A department account with this email or employee ID already exists.',
        ]);

        $department = Department::find($data['department_id']);

        DepartmentAccount::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'],
            'employee_id' => $data['employee_id'],
            'role' => $data['role'],
            'department_id' => $department->department_id,
            'password' => Hash::make($data['password']),
            // Legacy copied the department's existing image to the new account
            'profile_image' => $department->profile_image
                ?? DepartmentAccount::where('department_id', $department->department_id)
                    ->whereNotNull('profile_image')->where('profile_image', '!=', '')
                    ->orderBy('dept_acc_id')->value('profile_image'),
        ]);

        return redirect()
            ->route('admin.users')
            ->with('serverMsg', "Department account for '{$data['full_name']}' created successfully!")
            ->with('serverMsgType', 'success');
    }

    public function toggleStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target_user_id' => ['required', 'integer', 'min:1'],
            'target_user_type' => ['required', 'in:user_account,department'],
            'new_status' => ['required', 'in:Active,Deactivated'],
        ]);

        // FIX vs legacy: the button only mutated the client-side array and never
        // POSTed, so deactivation was never persisted.
        if ($data['target_user_type'] === 'department') {
            DepartmentAccount::where('dept_acc_id', $data['target_user_id'] - 100000)
                ->update(['status' => $data['new_status']]);
        } else {
            UserAccount::where('user_id', $data['target_user_id'])
                ->update(['status' => $data['new_status']]);
        }

        return redirect()
            ->route('admin.users')
            ->with('serverMsg', "User account status updated to '{$data['new_status']}' successfully!")
            ->with('serverMsgType', 'success');
    }

    public function delete(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'user_type' => ['required', 'string'],
        ]);

        if ($data['user_type'] === 'department') {
            // Strip the 100000 display offset before touching the database.
            DepartmentAccount::findOrFail($data['user_id'] - 100000)->delete();
            $msg = 'Department account deleted successfully.';
        } else {
            // FIX vs legacy: the FKs now cascade (student/faculty_member/
            // borrow_request), so one authoritative delete is enough — and it
            // fails loudly instead of reporting false success.
            UserAccount::findOrFail($data['user_id'])->delete();
            $msg = 'User account deleted successfully.';
        }

        return redirect()->route('admin.users')->with('serverMsg', $msg)->with('serverMsgType', 'success');
    }

    public function updateProfileImage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target_user_id' => ['required', 'integer', 'min:1'],
            'target_user_type' => ['required', 'in:user_account,department'],
            'admin_profile_img_file' => [
                'required', 'file', 'max:5120', 'mimes:jpeg,png,webp,gif',
            ],
        ]);

        // FIX vs legacy: base64-in-LONGTEXT storage + ALTER TABLE per upload.
        $file = $data['admin_profile_img_file'];
        $filename = 'images/' . $data['target_user_type'] . '-' . $data['target_user_id'] . '-profile.' . $file->extension();
        Storage::disk('public')->put($filename, file_get_contents($file->getRealPath()));
        $path = '/storage/' . $filename;

        if ($data['target_user_type'] === 'department') {
            $account = DepartmentAccount::find($data['target_user_id'] - 100000);
            $account?->update(['profile_image' => $path]);
            if ($account?->department_id) {
                // Legacy synced the department banner + all department accounts.
                Department::where('department_id', $account->department_id)->update(['profile_image' => $path]);
                DepartmentAccount::where('department_id', $account->department_id)->update(['profile_image' => $path]);
            }
        } else {
            UserAccount::where('user_id', $data['target_user_id'])->update(['profile_image' => $path]);
        }

        return redirect()
            ->route('admin.users')
            ->with('serverMsg', 'Profile picture updated and saved to database successfully!')
            ->with('serverMsgType', 'success');
    }

    private function fetchDbUsers(): array
    {
        $dbUsers = [];

        // 1. Students & Faculty
        $rows = UserAccount::with(['student', 'facultyMember', 'department'])
            ->orderByDesc('user_id')->get();

        foreach ($rows as $row) {
            $isStudent = strtolower($row->role) === 'student';
            $profile = $isStudent ? $row->student : $row->facultyMember;

            $firstName = $profile?->first_name ?? '';
            $lastName = $profile?->last_name ?? '';
            $idNumber = $isStudent ? ($profile?->id_number ?? '') : ($profile?->faculty_id_number ?? '');
            $address = $profile?->address ?? '';
            $yearLevel = $isStudent ? ($profile?->year_level ?? 'N/A') : 'N/A';
            $departmentName = $row->department?->department_name ?: 'College of Computer Studies';

            if ($firstName === '' && $lastName === '') {
                $firstName = ucfirst(explode('@', $row->email)[0]);
            }
            if ($idNumber === '') {
                $idNumber = 'USR-' . sprintf('%04d', $row->user_id);
            }

            $dbUsers[] = [
                'id' => (int) $row->user_id,
                'db_type' => 'user_account',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'id_number' => $idNumber,
                'role' => strtolower($row->role) === 'faculty' ? 'teacher' : strtolower($row->role),
                'year_level' => $yearLevel,
                'email' => $row->email,
                'address' => $address,
                'department' => $departmentName,
                'status' => $row->status ?: 'Active',
                'username' => explode('@', $row->email)[0],
                'created_at' => $row->date_created ? Carbon::parse($row->date_created)->format('M j, Y, g:i A') : 'Database Record',
                'last_online' => $row->last_online ? Carbon::parse($row->last_online)->format('M j, Y, g:i A') : 'Offline / Never',
                'profile_image' => $row->profile_image ?: null,
            ];
        }

        // 2. Department accounts
        $deptRows = DepartmentAccount::with('department')
            ->orderByDesc('dept_acc_id')->get();

        foreach ($deptRows as $row) {
            $fullName = $row->full_name ?: 'Department Account';
            $empId = $row->employee_id ?: ('EMP-' . $row->dept_acc_id);
            $email = $row->email ?: 'dept@equiptrack.edu';
            $deptName = $row->department?->department_name ?: 'Department Office';
            $roleName = $row->role ?: 'Department Head';

            // Legacy COALESCE: department banner image, else own, else first
            // account in the department that has one.
            $shared = $row->department?->profile_image
                ?: ($row->profile_image ?: null)
                ?: DepartmentAccount::where('department_id', $row->department_id)
                    ->whereNotNull('profile_image')->where('profile_image', '!=', '')
                    ->orderBy('dept_acc_id')->value('profile_image');

            $dbUsers[] = [
                // Legacy client-side disambiguation: department rows carry a
                // 100000 offset so user ids and dept_acc_ids never collide in JS.
                'id' => 100000 + (int) $row->dept_acc_id,
                'db_type' => 'department',
                'first_name' => $fullName,
                'last_name' => '',
                'id_number' => $empId,
                'role' => strtolower($roleName),
                'year_level' => 'N/A',
                'email' => $email,
                'address' => $deptName,
                'department' => $deptName,
                'status' => $row->status ?: 'Active',
                'username' => $email !== '' ? explode('@', $email)[0] : 'dept',
                'employee_id' => $empId,
                'department_id' => $row->department_id,
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('M j, Y, g:i A') : 'Database Record',
                'last_online' => $row->last_online ? Carbon::parse($row->last_online)->format('M j, Y, g:i A') : 'Offline / Never',
                'profile_image' => $shared ?: null,
            ];
        }

        return $dbUsers;
    }
}
