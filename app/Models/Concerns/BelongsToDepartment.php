<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * The one place that says which department a record belongs to, so every
 * page filters "this department's records" the same way:
 *
 * - Equipment and user accounts carry department_id themselves.
 * - Borrow requests, loans, penalties and penalty proofs belong to the
 *   department that owns the equipment (equipment.department_id), not the
 *   borrower's department: a Nursing student borrowing an IT projector is
 *   IT's request.
 *
 * Use Model::forDepartment($id). A missing department (null or 0) matches
 * nothing, so an account without a department never sees everyone's records.
 * For the signed-in Department Account, App\Support\DepartmentScope does this.
 */
trait BelongsToDepartment
{
    /**
     * Relation path to the record holding department_id ('' = this table's
     * own column), e.g. 'equipment' or 'request.equipment'.
     */
    abstract protected static function departmentPath(): string;

    public function scopeForDepartment(Builder $query, ?int $departmentId): Builder
    {
        if (!$departmentId) {
            return $query->whereRaw('1 = 0');
        }

        $path = static::departmentPath();

        return $path === ''
            ? $query->where($query->qualifyColumn('department_id'), $departmentId)
            : $query->whereHas($path, fn ($owner) => $owner->where('department_id', $departmentId));
    }
}
