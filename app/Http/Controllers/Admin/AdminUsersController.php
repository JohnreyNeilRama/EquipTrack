<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Penalty;
use App\Models\UserAccount;
use App\Rules\UniqueAccountEmail;
use App\Services\AuditLogger;
use App\Services\UserAccountCreator;
use App\Services\UserAccountEditor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class AdminUsersController extends Controller
{
    /** Admin rows carry this offset on the page, like department rows carry 100000. */
    private const ADMIN_ID_OFFSET = 200000;

    public function index(): View
    {
        $dbUsers = $this->fetchDbUsers();
        $departmentsList = Department::orderBy('department_name')->get();

        return view('admin.users', compact('dbUsers', 'departmentsList'));
    }

    public function addStudent(Request $request): RedirectResponse
    {
        return $this->addUserAccount($request, 'Student');
    }

    /**
     * Creates a Faculty account (user_account + faculty_member), the Admin's
     * counterpart to self-registration as a teacher.
     */
    public function addFaculty(Request $request): RedirectResponse
    {
        return $this->addUserAccount($request, 'Faculty');
    }

    /**
     * Add Student / Add Faculty. The rules and saving are shared with the
     * department Users page (UserAccountCreator); the Admin also picks the
     * department (by name, as the forms send it).
     */
    private function addUserAccount(Request $request, string $role): RedirectResponse
    {
        $rules = ($role === 'Faculty' ? UserAccountCreator::facultyRules() : UserAccountCreator::studentRules())
            + ['department' => ['required', 'string', Rule::exists('department', 'department_name')]];

        $data = $request->validate($rules, UserAccountCreator::messages() + [
            'department.required' => 'Select a department.',
            'department.exists' => 'Select a valid department.',
        ]);

        $deptId = (int) Department::where('department_name', $data['department'])->value('department_id');

        UserAccountCreator::create($role, $data, $deptId, AuditLogger::actorFor('admin', auth('admin')->user()), $request);

        return redirect()
            ->route('admin.users')
            ->with('serverMsg', "{$role} account for '{$data['first_name']} {$data['last_name']}' created successfully!")
            ->with('serverMsgType', 'success');
    }

    /**
     * Edit User: saves changes to a Student, Faculty, Laboratory Personnel or
     * Administrator account. The rules and saving are shared (UserAccountEditor).
     */
    public function updateUser(Request $request): RedirectResponse
    {
        $target = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'user_type' => ['required', 'in:user_account,department,admin'],
        ]);

        // Department rows carry a 100000 offset on the page, admin rows 200000 (see fetchDbUsers).
        $account = match ($target['user_type']) {
            'department' => DepartmentAccount::find($target['user_id'] - 100000),
            'admin' => Admin::find($target['user_id'] - self::ADMIN_ID_OFFSET),
            default => UserAccount::with(['student', 'facultyMember'])->find($target['user_id']),
        };

        if (!$account) {
            return redirect()->route('admin.users')
                ->with('serverMsg', 'That account no longer exists.')
                ->with('serverMsgType', 'error');
        }

        $data = $request->validate(UserAccountEditor::rules($account), UserAccountEditor::messages());

        $changed = UserAccountEditor::update($account, $data, AuditLogger::actorFor('admin', auth('admin')->user()), $request);

        return redirect()->route('admin.users')
            ->with('serverMsg', $changed
                ? 'Account details updated. Changed: ' . implode(', ', $changed) . '.'
                : 'No changes to save.')
            ->with('serverMsgType', 'success');
    }

    /**
     * Add Admin. The email is also the username (as on the admin Profile page);
     * the new admin can change the password from their Profile.
     */
    public function addAdmin(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'employee_id' => ['nullable', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:100', Rule::unique('admin', 'email'), Rule::unique('admin', 'username'), new UniqueAccountEmail()],
            'password' => ['required', 'string', Password::defaults()],
        ], [
            'name.required' => "Enter the administrator's full name.",
            'email.unique' => 'An account with email :input already exists.',
        ]);

        $admin = Admin::create([
            'name' => $data['name'],
            'username' => $data['email'],
            'email' => $data['email'],
            'employee_id' => ($data['employee_id'] ?? '') ?: null,
            'password' => Hash::make($data['password']),
            'status' => 'Active',
        ]);

        AuditLogger::log(
            'Added admin account',
            'admin:' . $admin->admin_id,
            $data['name'] . ' (' . $data['email'] . ')',
            AuditLogger::actorFor('admin', auth('admin')->user()),
            $request,
        );

        return redirect()
            ->route('admin.users')
            ->with('serverMsg', "Administrator account for '{$data['name']}' created successfully!")
            ->with('serverMsgType', 'success');
    }

    /**
     * Safeguards for deactivating or deleting an administrator: never your own
     * account, and never the last active one, so EquipTrack always keeps at
     * least one administrator who can sign in. Returns a reason, or null.
     */
    private function adminSafeguard(Admin $target, string $action): ?string
    {
        if ((int) $target->admin_id === (int) auth('admin')->id()) {
            return "You can't {$action} your own account. Ask another administrator to do it.";
        }

        $otherActiveAdmins = Admin::where('admin_id', '!=', $target->admin_id)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'Deactivated'))
            ->count();

        if (strtolower((string) $target->status) !== 'deactivated' && $otherActiveAdmins === 0) {
            return "This is the last active administrator account, so it can't be {$action}d. Add or activate another administrator first.";
        }

        return null;
    }

    public function addDepartment(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'employee_id' => ['required', 'string', 'max:100', Rule::unique('department_account', 'employee_id')],
            'email' => ['required', 'email', 'max:100', Rule::unique('department_account', 'email'), new UniqueAccountEmail()],
            'role' => ['required', Rule::in(['Department Head', 'Department Staff', 'Department Admin'])],
            'department_id' => ['required', 'integer', Rule::exists('department', 'department_id')],
            'password' => ['required', 'string', Password::defaults()],
        ], [
            'employee_id.unique' => 'A department account with this email or employee ID already exists.',
            'email.unique' => 'A department account with this email or employee ID already exists.',
        ]);

        $department = Department::find($data['department_id']);

        $account = DepartmentAccount::create([
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

        AuditLogger::log(
            'Added department account',
            'department_account:' . $account->dept_acc_id,
            $data['full_name'] . ' (' . $data['email'] . ')',
            null,
            $request,
        );

        return redirect()
            ->route('admin.users')
            ->with('serverMsg', "Department account for '{$data['full_name']}' created successfully!")
            ->with('serverMsgType', 'success');
    }

    public function toggleStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target_user_id' => ['required', 'integer', 'min:1'],
            'target_user_type' => ['required', 'in:user_account,department,admin'],
            'new_status' => ['required', 'in:Active,Deactivated'],
        ]);

        // FIX vs legacy: the button only mutated the client-side array and never
        // POSTed, so deactivation was never persisted.
        if ($data['target_user_type'] === 'admin') {
            $account = Admin::find($data['target_user_id'] - self::ADMIN_ID_OFFSET);
            if (!$account) {
                return redirect()->route('admin.users')->with('serverMsg', 'That account no longer exists.')->with('serverMsgType', 'error');
            }
            if ($data['new_status'] === 'Deactivated' && ($reason = $this->adminSafeguard($account, 'deactivate'))) {
                return redirect()->route('admin.users')->with('serverMsg', $reason)->with('serverMsgType', 'error');
            }
            $entity = 'admin:' . $account->admin_id;
            $name = $account->name ?: $account->email;
            $previous = $account->status;
            $account->update(['status' => $data['new_status']]);
            $account->status = $previous; // for the "real change" check below
        } elseif ($data['target_user_type'] === 'department') {
            $account = DepartmentAccount::find($data['target_user_id'] - 100000);
            $entity = $account ? 'department_account:' . $account->dept_acc_id : '';
            $name = $account ? ($account->full_name ?: $account->email) : '';
            DepartmentAccount::where('dept_acc_id', $data['target_user_id'] - 100000)
                ->update(['status' => $data['new_status']]);
        } else {
            $account = UserAccount::find($data['target_user_id']);
            $entity = $account ? 'user_account:' . $account->user_id : '';
            $name = $account ? ($account->fullName() ?: $account->email) : '';
            UserAccount::where('user_id', $data['target_user_id'])
                ->update(['status' => $data['new_status']]);
        }

        // Only a real change is an event; re-submitting the same status is not logged.
        if ($account && strtolower((string) $account->status) !== strtolower($data['new_status'])) {
            AuditLogger::log(
                $data['new_status'] === 'Active' ? 'Activated user account' : 'Deactivated user account',
                $entity,
                $name . ' (' . $account->email . ')',
                null,
                $request,
            );
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

        if ($data['user_type'] === 'admin') {
            $account = Admin::findOrFail($data['user_id'] - self::ADMIN_ID_OFFSET);
            if ($reason = $this->adminSafeguard($account, 'delete')) {
                return redirect()->route('admin.users')->with('serverMsg', $reason)->with('serverMsgType', 'error');
            }
            // Records this admin reviewed or recorded keep their history
            // (those columns are set to empty, not deleted).
            $logEntity = 'admin:' . $account->admin_id;
            $logAction = 'Deleted admin account';
            $logDetails = ($account->name ?: 'Administrator') . ' (' . $account->email . ')';
            $account->delete();
            $msg = 'Administrator account deleted successfully.';
        } elseif ($data['user_type'] === 'department') {
            // Strip the 100000 display offset before touching the database.
            $account = DepartmentAccount::findOrFail($data['user_id'] - 100000);
            $logEntity = 'department_account:' . $account->dept_acc_id;
            $logAction = 'Deleted department account';
            $logDetails = ($account->full_name ?: 'Department account') . ' (' . $account->email . ')';
            $account->delete();
            $msg = 'Department account deleted successfully.';
        } else {
            $account = UserAccount::findOrFail($data['user_id']);

            // Refuse to delete users who still hold equipment; deleting them
            // would erase the only record of an open loan.
            $hasOpenLoans = BorrowTransaction::whereHas('request', fn ($q) => $q->where('user_id', $account->user_id))
                ->whereIn('status', ['Active', 'Overdue'])
                ->exists();

            if ($hasOpenLoans) {
                return redirect()
                    ->route('admin.users')
                    ->with('serverMsg', 'This user cannot be deleted because they have active or overdue borrowed equipment. Resolve those first, or deactivate the account instead.')
                    ->with('serverMsgType', 'error');
            }

            // Deleting would erase the only record of an unsettled penalty.
            if (Penalty::where('user_id', $account->user_id)->where('status', Penalty::STATUS_PENDING)->exists()) {
                return redirect()
                    ->route('admin.users')
                    ->with('serverMsg', 'This user cannot be deleted because they have an unsettled penalty. Settle or waive it first, or deactivate the account instead.')
                    ->with('serverMsgType', 'error');
            }

            // FKs cascade (student/faculty_member/borrow_request/borrow_transaction),
            // so one authoritative delete removes the account and its completed
            // history, and fails loudly instead of reporting false success.
            $logEntity = 'user_account:' . $account->user_id;
            $logAction = 'Deleted user account';
            $logDetails = ($account->fullName() ?: 'User') . ' (' . $account->email . ')';
            $account->delete();
            $msg = 'User account deleted successfully.';
        }

        AuditLogger::log($logAction, $logEntity, $logDetails, null, $request);

        return redirect()->route('admin.users')->with('serverMsg', $msg)->with('serverMsgType', 'success');
    }

    public function updateProfileImage(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'target_user_id' => ['required', 'integer', 'min:1'],
            'target_user_type' => ['required', 'in:user_account,department,admin'],
            'admin_profile_img_file' => [
                'required', 'file', 'max:5120', 'mimes:jpeg,png,webp,gif',
            ],
        ]);

        // FIX vs legacy: base64-in-LONGTEXT storage + ALTER TABLE per upload.
        $file = $data['admin_profile_img_file'];
        $filename = 'images/' . $data['target_user_type'] . '-' . $data['target_user_id'] . '-profile.' . $file->extension();
        Storage::disk('public')->put($filename, file_get_contents($file->getRealPath()));
        $path = '/storage/' . $filename;

        $targetExists = match ($data['target_user_type']) {
            'department' => DepartmentAccount::whereKey($data['target_user_id'] - 100000)->exists(),
            'admin' => Admin::whereKey($data['target_user_id'] - self::ADMIN_ID_OFFSET)->exists(),
            default => UserAccount::whereKey($data['target_user_id'])->exists(),
        };

        if ($data['target_user_type'] === 'admin') {
            Admin::whereKey($data['target_user_id'] - self::ADMIN_ID_OFFSET)->update(['profile_image' => $path]);
        } elseif ($data['target_user_type'] === 'department') {
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

        if ($targetExists) {
            AuditLogger::log(
                'Updated user profile picture',
                match ($data['target_user_type']) {
                    'department' => 'department_account:' . ($data['target_user_id'] - 100000),
                    'admin' => 'admin:' . ($data['target_user_id'] - self::ADMIN_ID_OFFSET),
                    default => 'user_account:' . $data['target_user_id'],
                },
                null,
                null,
                $request,
            );
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
                // last_online is saved by the app in UTC; date_created comes from the database
                // clock, which already runs on Philippine time, so only this one is converted.
                'last_online' => $row->last_online ? Carbon::parse($row->last_online)->setTimezone(config('app.display_timezone'))->format('M j, Y, g:i A') : 'Offline / Never',
                'profile_image' => $row->profile_image ?: null,
                // Real current values for the Edit form (the fields above may hold display defaults).
                'edit' => UserAccountEditor::values($row),
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
                // last_online is saved by the app in UTC; date_created comes from the database
                // clock, which already runs on Philippine time, so only this one is converted.
                'last_online' => $row->last_online ? Carbon::parse($row->last_online)->setTimezone(config('app.display_timezone'))->format('M j, Y, g:i A') : 'Offline / Never',
                'profile_image' => $shared ?: null,
                'edit' => UserAccountEditor::values($row),
            ];
        }

        // 3. Administrators
        $currentAdminId = (int) auth('admin')->id();
        foreach (Admin::orderBy('admin_id')->get() as $row) {
            $email = $row->email ?: $row->username;
            $dbUsers[] = [
                'id' => self::ADMIN_ID_OFFSET + (int) $row->admin_id,
                'db_type' => 'admin',
                'first_name' => $row->name ?: 'Administrator',
                'last_name' => '',
                'id_number' => $row->employee_id ?: ('ADM-' . sprintf('%04d', $row->admin_id)),
                'role' => 'administrator',
                'year_level' => 'N/A',
                'email' => (string) $email,
                'address' => 'N/A',
                'department' => 'All Departments',
                'status' => $row->status ?: 'Active',
                'username' => (string) $row->username,
                'is_self' => (int) $row->admin_id === $currentAdminId,
                // Added before this column existed: unknown.
                'created_at' => $row->created_at ? Carbon::parse($row->created_at)->format('M j, Y, g:i A') : 'Database Record',
                // Saved by the app in UTC; created_at comes from the database clock (Philippine time).
                'last_online' => $row->last_online ? Carbon::parse($row->last_online)->setTimezone(config('app.display_timezone'))->format('M j, Y, g:i A') : 'Offline / Never',
                'profile_image' => $row->profile_image ?: null,
                'edit' => UserAccountEditor::values($row),
            ];
        }

        return $dbUsers;
    }
}
