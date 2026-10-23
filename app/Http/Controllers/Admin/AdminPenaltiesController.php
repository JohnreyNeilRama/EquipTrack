<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\PenaltyPageController;
use App\Models\BorrowTransaction;
use App\Models\Department;
use App\Models\Penalty;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin Penalties page: every department's penalties. Besides editing,
 * settling and waiving, the admin can add a penalty to any returned loan
 * (one the department did not record at Return Confirmation).
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

    protected function pageData(): array
    {
        return [
            'returnedLoans' => $this->penalties->returnedLoans(BorrowTransaction::query()),
            'departments' => Department::orderBy('department_name')->pluck('department_name')->all(),
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'integer', 'min:1'],
            'type' => ['required', Rule::in(Penalty::TYPES)],
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $transaction = BorrowTransaction::with('request')->find($data['transaction_id']);

        if (!$transaction || !$transaction->request) {
            return response()->json(['success' => false, 'message' => 'Returned loan not found.'], 404);
        }

        $result = $this->penalties->record($transaction, $data, $this->actor());

        if (is_string($result)) {
            return response()->json(['success' => false, 'message' => $result], 400);
        }

        return $this->feed('Penalty recorded.');
    }
}
