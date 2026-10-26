<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * An email may belong to only one EquipTrack account across all three account
 * tables (user_account, department_account, admin). Login and password resets
 * look an email up in those tables in turn, so a shared email could sign in to,
 * or reset, the wrong account.
 *
 * Admins also log in by username, so an admin's username counts as taken too.
 * Pass the account being edited to ignore its own address:
 * UniqueAccountEmail::except('user', $account->user_id).
 */
class UniqueAccountEmail implements ValidationRule
{
    public const USER = 'user';

    public const DEPT = 'dept';

    public const ADMIN = 'admin';

    public function __construct(
        private ?string $ignoreType = null,
        private ?int $ignoreId = null,
    ) {
    }

    public static function except(string $type, int $id): self
    {
        return new self($type, $id);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = trim((string) $value);
        if ($email === '') {
            return;
        }

        // The tables use a case-insensitive collation, so = matches any casing.
        $taken = DB::table('user_account')
            ->where('email', $email)
            ->when($this->ignoreType === self::USER, fn ($q) => $q->where('user_id', '!=', $this->ignoreId))
            ->exists()
            || DB::table('department_account')
                ->where('email', $email)
                ->when($this->ignoreType === self::DEPT, fn ($q) => $q->where('dept_acc_id', '!=', $this->ignoreId))
                ->exists()
            || DB::table('admin')
                ->where(fn ($q) => $q->where('email', $email)->orWhere('username', $email))
                ->when($this->ignoreType === self::ADMIN, fn ($q) => $q->where('admin_id', '!=', $this->ignoreId))
                ->exists();

        if ($taken) {
            $fail('This email address is already used by another EquipTrack account.');
        }
    }
}
