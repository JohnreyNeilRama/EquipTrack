<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;

/**
 * Finds which account a password reset is for, by email, across the three
 * account types, in the same order as the login form (Student/Faculty, then
 * Admin, then Laboratory Personnel). UniqueAccountEmail keeps an address on
 * only one account.
 *
 * Returns the account with its password broker (config/auth.php) and guard.
 */
class PasswordResetAccounts
{
    /** @return array{broker: string, guard: string, account: UserAccount|Admin|DepartmentAccount}|null */
    public static function find(string $email): ?array
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }

        if ($account = UserAccount::where('email', $email)->first()) {
            return ['broker' => 'users', 'guard' => 'user', 'account' => $account];
        }
        if ($account = Admin::where('email', $email)->first()) {
            return ['broker' => 'admins', 'guard' => 'admin', 'account' => $account];
        }
        if ($account = DepartmentAccount::where('email', $email)->first()) {
            return ['broker' => 'departments', 'guard' => 'dept', 'account' => $account];
        }

        return null;
    }

    /** Admins have no status column; Student/Faculty and Lab Personnel can be deactivated. */
    public static function isDeactivated(object $account): bool
    {
        return strtolower((string) ($account->status ?? '')) === 'deactivated';
    }

    /** The audit trail's affected_entity for an account. */
    public static function entity(string $guard, object $account): string
    {
        return match ($guard) {
            'admin' => 'admin:' . $account->admin_id,
            'dept' => 'department_account:' . $account->dept_acc_id,
            default => 'user_account:' . $account->user_id,
        };
    }
}
