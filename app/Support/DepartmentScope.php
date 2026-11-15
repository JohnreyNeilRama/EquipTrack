<?php

namespace App\Support;

use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\Penalty;
use App\Models\PenaltySubmission;
use App\Models\UserAccount;
use Illuminate\Database\Eloquent\Builder;

/**
 * What the signed-in Department Account (Lab Personnel) may see: only its
 * own department's records. Department pages start every query here instead
 * of filtering by hand, so a new page can't forget the rule.
 *
 *     DepartmentScope::requests()->where('dept_status', 'Pending')->count();
 *     DepartmentScope::loans()->whereKey($id)->first();   // null if another department's
 *
 * Which department a record belongs to is defined once, in
 * App\Models\Concerns\BelongsToDepartment. An account without a department
 * gets empty results.
 */
final class DepartmentScope
{
    /** The signed-in Department Account's department, or null. */
    public static function id(): ?int
    {
        $departmentId = auth('dept')->user()?->department_id;

        return $departmentId ? (int) $departmentId : null;
    }

    public static function equipment(): Builder
    {
        return Equipment::query()->forDepartment(self::id());
    }

    public static function requests(): Builder
    {
        return BorrowRequest::query()->forDepartment(self::id());
    }

    public static function loans(): Builder
    {
        return BorrowTransaction::query()->forDepartment(self::id());
    }

    public static function penalties(): Builder
    {
        return Penalty::query()->forDepartment(self::id());
    }

    public static function proofs(): Builder
    {
        return PenaltySubmission::query()->forDepartment(self::id());
    }

    /** Students, Faculty and Personnel of the department. */
    public static function users(): Builder
    {
        return UserAccount::query()->forDepartment(self::id());
    }
}
