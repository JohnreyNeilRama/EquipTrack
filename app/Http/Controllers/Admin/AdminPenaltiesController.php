<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\PenaltyPageController;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\Penalty;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;

/**
 * Admin Penalties page: every department's penalties and returned loans.
 */
class AdminPenaltiesController extends PenaltyPageController
{
    protected function scope(): Builder
    {
        return Penalty::query();
    }

    protected function actor(): array
    {
        return AuditLogger::actorFor('admin', auth('admin')->user());
    }

    protected function view(): string
    {
        return 'admin.penalties';
    }

    protected function proofFileRoute(): string
    {
        return 'admin.penalties.proof';
    }

    /** Every department's returned loans. */
    protected function loanScope(): Builder
    {
        return BorrowTransaction::query();
    }

    protected function pageData(): array
    {
        return parent::pageData() + [
            'departments' => Department::orderBy('department_name')->pluck('department_name')->all(),
        ];
    }
}
