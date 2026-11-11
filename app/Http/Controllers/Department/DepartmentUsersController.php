<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\UserAccount;
use App\Notifications\FacultyApprovalNotification;
use App\Services\AuditLogger;
use App\Services\UserAccountCreator;
use App\Services\UserAccountEditor;
use App\Services\UserInvites;
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

        // A self-registered Faculty account is reviewed as submitted: its details
        // can be edited only after it is approved. (A declined registration is
        // removed, so it can't be edited either.)
        if ($account->isPendingApproval()) {
            return response()->json([
                'success' => false,
                'message' => 'This Faculty account is waiting for approval, so its details can\'t be edited yet. Approve it first, or decline it.',
            ], 403);
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

        // A Faculty account waiting for approval is approved or declined, not activated.
        if ($account->isPendingApproval()) {
            return response()->json([
                'success' => false,
                'message' => 'This Faculty account is waiting for approval. Use Approve or Decline instead.',
            ], 400);
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

    /**
     * Approves a self-registered Faculty member of this department: they can
     * borrow from now on (once their email is verified) and get an email.
     */
    public function approve(Request $request): JsonResponse
    {
        $account = $this->pendingFaculty($request);
        if ($account instanceof JsonResponse) {
            return $account;
        }

        $account->update(['status' => UserAccount::STATUS_ACTIVE]);
        $name = $account->fullName() ?: $account->email;

        AuditLogger::log(
            'Approved faculty account',
            'user_account:' . $account->user_id,
            $name . ' (' . $account->email . ')',
            AuditLogger::actorFor('dept', auth('dept')->user()),
            $request,
        );

        $emailed = $this->notifyDecision($account, new FacultyApprovalNotification(true, $name, $this->departmentName()));

        return response()->json([
            'success' => true,
            'message' => $name . "'s Faculty account is approved." . ($emailed ? ' They were emailed.' : ' (The email to tell them could not be sent.)'),
            'users' => $this->rows(),
        ]);
    }

    /**
     * Declines a self-registered Faculty member of this department with a
     * reason: they are emailed, and the account is removed (it never borrowed
     * anything), so the address can register again with the right details.
     */
    public function decline(Request $request): JsonResponse
    {
        $request->validate(
            ['reason' => ['required', 'string', 'max:500']],
            ['reason.required' => 'Give a reason; it is included in the email to the applicant.'],
        );

        $account = $this->pendingFaculty($request);
        if ($account instanceof JsonResponse) {
            return $account;
        }

        $name = $account->fullName() ?: $account->email;
        $reason = trim((string) $request->input('reason'));

        // Emailed before the account is removed (the notification needs its address).
        $emailed = $this->notifyDecision($account, new FacultyApprovalNotification(false, $name, $this->departmentName(), $reason));

        AuditLogger::log(
            'Declined faculty account',
            'user_account:' . $account->user_id,
            $name . ' (' . $account->email . '). Reason: ' . $reason,
            AuditLogger::actorFor('dept', auth('dept')->user()),
            $request,
        );

        $account->delete();

        return response()->json([
            'success' => true,
            'message' => $name . "'s registration was declined and removed." . ($emailed ? ' They were emailed the reason.' : ' (The email to tell them could not be sent.)'),
            'users' => $this->rows(),
        ]);
    }

    /** Emails a new "set your password" invite to an account added by staff. */
    public function resendInvite(Request $request): JsonResponse
    {
        $request->validate(['user_id' => ['required', 'integer', 'min:1']]);

        $departmentId = $this->departmentId();
        if (!$departmentId) {
            return $this->noDepartment();
        }

        $account = $this->scopedAccount((int) $request->input('user_id'), $departmentId);
        if (!$account) {
            return $this->notInDepartment();
        }

        // Self-registered accounts get the verification email instead.
        if (!$account->hasVerifiedEmail() && !$account->invited_at) {
            return $this->resendVerification($account);
        }

        if ($error = UserInvites::send($account, auth('dept')->user(), $request)) {
            return response()->json(['success' => false, 'message' => $error], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'A new invite was sent to ' . $account->email . '.',
            'users' => $this->rows(),
        ]);
    }

    private function resendVerification(UserAccount $account): JsonResponse
    {
        try {
            $account->sendEmailVerificationNotification();
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['success' => false, 'message' => 'The verification email could not be sent. Check the mail settings and try again.'], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'A new verification link was sent to ' . $account->email . '.',
            'users' => $this->rows(),
        ]);
    }

    /** The Faculty account of this department waiting for approval, or the error response. */
    private function pendingFaculty(Request $request): UserAccount|JsonResponse
    {
        $request->validate(['user_id' => ['required', 'integer', 'min:1']]);

        $departmentId = $this->departmentId();
        if (!$departmentId) {
            return $this->noDepartment();
        }

        $account = $this->scopedAccount((int) $request->input('user_id'), $departmentId);
        if (!$account) {
            return $this->notInDepartment();
        }

        if (!$account->isPendingApproval()) {
            return response()->json(['success' => false, 'message' => 'This account is not waiting for approval.', 'users' => $this->rows()], 400);
        }

        return $account;
    }

    private function notifyDecision(UserAccount $account, FacultyApprovalNotification $notification): bool
    {
        try {
            $account->notify($notification);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function departmentName(): string
    {
        return auth('dept')->user()->department?->department_name ?: 'Your department';
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

        $account = UserAccountCreator::create($role, $data, $departmentId, AuditLogger::actorFor('dept', auth('dept')->user()), $request);
        $inviteError = UserInvites::send($account, auth('dept')->user(), $request);

        return response()->json([
            'success' => true,
            'message' => $inviteError
                ? "{$role} account for '{$data['first_name']} {$data['last_name']}' was created. " . $inviteError
                : "{$role} account for '{$data['first_name']} {$data['last_name']}' created. An invite to set their password was emailed to {$account->email}.",
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
                    // Active | Inactive | Pending Approval | Invite Sent | Email Not Verified
                    'status' => $account->accountStateLabel(),
                    'state' => $account->accountState(),
                    'emailVerified' => $account->hasVerifiedEmail(),
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
