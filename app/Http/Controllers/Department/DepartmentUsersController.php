<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use App\Services\AuditLogger;
use App\Services\UserAccountCreator;
use App\Services\UserAccountEditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Department "Users" page: the students and faculty members registered under
 * the signed-in department. Everything comes from the database and is scoped to
 * the logged-in department account's department_id, so other departments'
 * users are never sent to the browser.
 */
class DepartmentUsersController extends Controller
{
    public function index(): View
    {
        return view('department.users', [
            'dbUsers' => $this->rows(),
            'departmentName' => auth('dept')->user()->department?->department_name ?? 'Your department',
        ]);
    }

    /** JSON feed the page polls so the table stays current. */
    public function data(): JsonResponse
    {
        return response()->json(['success' => true, 'users' => $this->rows()]);
    }

    /*
     * Managing the department's Students and Faculty. Laboratory Personnel can
     * add, edit and activate/deactivate accounts in their own department only:
     * new accounts always go to that department, the department of an existing
     * account cannot be changed here, and accounts of other departments are
     * refused. Deleting accounts stays with the Admin.
     */

    public function addStudent(Request $request): JsonResponse
    {
        return $this->addUserAccount($request, 'Student');
    }

    public function addFaculty(Request $request): JsonResponse
    {
        return $this->addUserAccount($request, 'Faculty');
    }

    public function update(Request $request): JsonResponse
    {
        $departmentId = $this->departmentId();
        if (!$departmentId) {
            return $this->noDepartment();
        }

        $account = $this->scopedAccount((int) $request->input('user_id'), $departmentId);
        if (!$account) {
            return $this->notInDepartment();
        }

        // The department stays the Lab Personnel's own (no moving users elsewhere).
        $request->merge(['department_id' => $departmentId]);
        $data = $request->validate(UserAccountEditor::rules($account), UserAccountEditor::messages());

        $changed = UserAccountEditor::update($account, $data, AuditLogger::actorFor('dept', auth('dept')->user()), $request);

        return response()->json([
            'success' => true,
            'message' => $changed ? 'Account details updated. Changed: ' . implode(', ', $changed) . '.' : 'No changes to save.',
            'users' => $this->rows(),
        ]);
    }

    public function setStatus(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:Active,Deactivated'],
        ]);

        $departmentId = $this->departmentId();
        if (!$departmentId) {
            return $this->noDepartment();
        }

        $account = $this->scopedAccount((int) $data['user_id'], $departmentId);
        if (!$account) {
            return $this->notInDepartment();
        }

        // Only a real change is an event; repeating the same status is not logged.
        if (strcasecmp((string) ($account->status ?: 'Active'), $data['status']) !== 0) {
            $account->update(['status' => $data['status']]);

            AuditLogger::log(
                $data['status'] === 'Active' ? 'Activated user account' : 'Deactivated user account',
                'user_account:' . $account->user_id,
                ($account->fullName() ?: $account->email) . ' (' . $account->email . ')',
                AuditLogger::actorFor('dept', auth('dept')->user()),
                $request,
            );
        }

        return response()->json([
            'success' => true,
            'message' => $data['status'] === 'Active' ? 'Account activated.' : 'Account deactivated. The user can no longer log in.',
            'users' => $this->rows(),
        ]);
    }

    private function addUserAccount(Request $request, string $role): JsonResponse
    {
        $departmentId = $this->departmentId();
        if (!$departmentId) {
            return $this->noDepartment();
        }

        $data = $request->validate(
            $role === 'Faculty' ? UserAccountCreator::facultyRules() : UserAccountCreator::studentRules(),
            UserAccountCreator::messages(),
        );

        UserAccountCreator::create($role, $data, $departmentId, AuditLogger::actorFor('dept', auth('dept')->user()), $request);

        return response()->json([
            'success' => true,
            'message' => "{$role} account for '{$data['first_name']} {$data['last_name']}' created successfully!",
            'users' => $this->rows(),
        ]);
    }

    private function departmentId(): ?int
    {
        return auth('dept')->user()->department_id ?: null;
    }

    /** A Student or Faculty account of this department, or null. */
    private function scopedAccount(int $userId, int $departmentId): ?UserAccount
    {
        return UserAccount::with(['student', 'facultyMember'])
            ->where('user_id', $userId)
            ->where('department_id', $departmentId)
            ->whereIn('role', ['Student', 'Faculty'])
            ->first();
    }

    private function noDepartment(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Your account is not assigned to a department.'], 403);
    }

    private function notInDepartment(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'That account is not in your department.'], 404);
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(): array
    {
        $departmentId = auth('dept')->user()->department_id;

        // An account that isn't assigned to a department has no users in scope.
        if (!$departmentId) {
            return [];
        }

        return UserAccount::with(['student', 'facultyMember', 'department'])
            ->where('department_id', $departmentId)
            ->orderByDesc('user_id')
            ->get()
            ->map(function (UserAccount $account) {
                $isStudent = strtolower((string) $account->role) === 'student';
                $profile = $isStudent ? $account->student : $account->facultyMember;

                $fullName = $account->fullName();
                if ($fullName === '') {
                    $fullName = ucfirst(explode('@', (string) $account->email)[0]);
                }

                $idNumber = $isStudent ? ($profile?->id_number ?? '') : ($profile?->faculty_id_number ?? '');
                if ($idNumber === '') {
                    $idNumber = 'USR-' . sprintf('%04d', $account->user_id);
                }

                $attainment = $isStudent
                    ? ($profile?->year_level ?? '')
                    : ($profile?->highest_educational_attainment ?? '');

                return [
                    'id' => (int) $account->user_id,
                    'fullName' => $fullName,
                    'idNumber' => $idNumber,
                    'userType' => $isStudent ? 'Student' : 'Faculty Member',
                    'attainment' => trim((string) $attainment) !== '' ? $attainment : 'N/A',
                    // Anything other than Active (e.g. Deactivated) counts as Inactive.
                    'status' => strcasecmp((string) ($account->status ?: 'Active'), 'Active') === 0 ? 'Active' : 'Inactive',
                    'dateRegistered' => $account->date_created
                        ? Carbon::parse($account->date_created)->format('M d, Y')
                        : '—',
                    // Extra fields for the View Details modal (same data the admin modal shows).
                    'email' => (string) $account->email,
                    'address' => trim((string) ($profile?->address ?? '')) !== '' ? $profile->address : 'N/A',
                    'department' => $account->department?->department_name ?: 'N/A',
                    'attainmentLabel' => $isStudent ? 'Year Level' : 'Highest Attainment',
                    'createdAt' => $account->date_created
                        ? Carbon::parse($account->date_created)->format('M j, Y, g:i A')
                        : 'Database Record',
                    // Saved by the app in UTC (date_created above already comes from the
                    // database clock in Philippine time), so only this one is converted.
                    'lastOnline' => $account->last_online
                        ? Carbon::parse($account->last_online)->setTimezone(config('app.display_timezone'))->format('M j, Y, g:i A')
                        : 'Offline / Never',
                    'profileImage' => $account->profile_image ?: null,
                    // For Edit Details and Activate/Deactivate.
                    'role' => $isStudent ? 'Student' : 'Faculty',
                    'edit' => UserAccountEditor::values($account),
                ];
            })
            ->all();
    }
}
