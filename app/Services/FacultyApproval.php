<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use App\Notifications\FacultyApprovalNotification;
use Illuminate\Http\Request;

/**
 * Approving or declining a self-registered Faculty account (status Pending
 * Approval). Normally the Lab Personnel of the teacher's department decide
 * (DepartmentUsersController). The Admin decides only as a backup, when that
 * department has no active Department Account, so a teacher is never stuck
 * with nobody able to review them (AdminUsersController).
 *
 * Approving activates the account; declining emails the reason and removes
 * the account (it never borrowed anything), so the address can register again.
 * Both are recorded in the audit trail.
 */
class FacultyApproval
{
    /**
     * Whether the department has a Department Account that can sign in: not
     * deactivated, and not still waiting for its invite (no password yet).
     */
    public static function departmentHasActiveAccount(?int $departmentId): bool
    {
        if (!$departmentId) {
            return false;
        }

        return self::canSignIn(DepartmentAccount::where('department_id', $departmentId))->exists();
    }

    /**
     * Departments (ids) that can review their own teachers.
     *
     * @return array<int, true>
     */
    public static function departmentsWithActiveAccount(): array
    {
        return self::canSignIn(DepartmentAccount::whereNotNull('department_id'))
            ->pluck('department_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    private static function canSignIn($query)
    {
        return $query
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'Deactivated'))
            ->where(fn ($q) => $q->whereNull('invited_at')->orWhereNotNull('email_verified_at'));
    }

    /** Activates the account and emails the teacher; returns the message for staff. */
    public static function approve(UserAccount $account, Admin|DepartmentAccount $by, ?Request $request = null): string
    {
        $account->update(['status' => UserAccount::STATUS_ACTIVE]);
        $name = $account->fullName() ?: $account->email;

        AuditLogger::log(
            'Approved faculty account',
            'user_account:' . $account->user_id,
            $name . ' (' . $account->email . ')' . self::byAdminNote($by),
            self::actor($by),
            $request,
        );

        $emailed = self::notify($account, new FacultyApprovalNotification(true, $name, self::departmentName($account)));

        return $name . "'s Faculty account is approved." . ($emailed ? ' They were emailed.' : ' (The email to tell them could not be sent.)');
    }

    /** Emails the teacher the reason, then removes the account; returns the message for staff. */
    public static function decline(UserAccount $account, string $reason, Admin|DepartmentAccount $by, ?Request $request = null): string
    {
        $name = $account->fullName() ?: $account->email;
        $reason = trim($reason);

        // Emailed before the account is removed (the notification needs its address).
        $emailed = self::notify($account, new FacultyApprovalNotification(false, $name, self::departmentName($account), $reason));

        AuditLogger::log(
            'Declined faculty account',
            'user_account:' . $account->user_id,
            $name . ' (' . $account->email . '). Reason: ' . $reason . self::byAdminNote($by),
            self::actor($by),
            $request,
        );

        $account->delete();

        return $name . "'s registration was declined and removed." . ($emailed ? ' They were emailed the reason.' : ' (The email to tell them could not be sent.)');
    }

    private static function departmentName(UserAccount $account): string
    {
        return $account->department?->department_name ?: 'Your department';
    }

    private static function actor(Admin|DepartmentAccount $by): array
    {
        return AuditLogger::actorFor($by instanceof Admin ? 'admin' : 'dept', $by);
    }

    private static function byAdminNote(Admin|DepartmentAccount $by): string
    {
        return $by instanceof Admin ? ' · by the Admin (the department has no active account)' : '';
    }

    private static function notify(UserAccount $account, FacultyApprovalNotification $notification): bool
    {
        try {
            $account->notify($notification);

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
