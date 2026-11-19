<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Support\DepartmentScope;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\EquipmentUnit;
use App\Models\Penalty;
use App\Services\AuditLogger;
use App\Services\PenaltyService;
use App\Support\LoanTime;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Return Confirmation: the department that owns the equipment verifies that a
 * returned unit was physically handed back, inspects its condition, and
 * confirms or rejects the return.
 *
 *  - Confirm: the loan is settled (transaction Returned, return date = the day
 *    the user submitted the return), the inspected condition and remarks are
 *    recorded, and Good units go back into available stock. For a loan of
 *    several units, Lab Personnel say how many are Damaged / Incomplete / Lost
 *    (affected_qty); the rest count as Good. Damaged / Incomplete units stay
 *    out of the available stock until the admin updates it in Equipment
 *    Management. Lost units also leave the total quantity. A penalty (Damage & Penalty
 *    Recording) can be recorded in the same step; see PenaltyService.
 *  - Reject: the loan stays open (Active / Overdue) and the user can submit the
 *    return again.
 *
 * Every query is limited to equipment owned by the signed-in department.
 */
class DepartmentReturnsController extends Controller
{
    public function index(): View
    {
        return view('department.returns', ['dbReturns' => $this->fetchReturns()]);
    }

    /** JSON feed for live polling. */
    public function data(): JsonResponse
    {
        return response()->json(['success' => true, 'returns' => $this->fetchReturns()]);
    }

    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'integer', 'min:1'],
            'condition' => ['required', Rule::in(BorrowTransaction::INSPECTED_CONDITIONS)],
            'remarks' => ['nullable', 'string', 'max:1000'],
            // Damaged / Incomplete / Lost: which of the loan's units (the window
            // names them), or only how many (older clients; default: all).
            'affected_unit_ids' => ['nullable', 'array', 'max:999'],
            'affected_unit_ids.*' => ['integer', 'min:1'],
            'affected_units' => ['nullable', 'integer', 'min:1', 'max:999'],
            // Optional penalty recorded together with the return.
            'penalty_type' => ['nullable', Rule::in(Penalty::TYPES)],
            'penalty_amount' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'penalty_description' => ['nullable', 'required_with:penalty_type', 'string', 'max:1000'],
        ], [
            'penalty_description.required_with' => 'Please describe the penalty.',
        ]);

        $remarks = trim($data['remarks'] ?? '');

        // Damage, missing parts or a loss must be described before the return is finalized.
        if ($data['condition'] !== 'Good' && $remarks === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please describe the damage, the missing parts or the loss in the remarks.',
            ], 422);
        }

        return $this->review($request, (int) $data['transaction_id'], function (BorrowTransaction $transaction, BorrowRequest $borrow, $dept) use ($data, $remarks) {
            $quantity = max(1, (int) $borrow->quantity);
            $good = $data['condition'] === 'Good';
            $affected = $good ? 0 : (int) ($data['affected_units'] ?? $quantity);

            // The exact units found Damaged / Incomplete / Lost: the loan's units still out.
            $chosen = [];
            if (!$good && !empty($data['affected_unit_ids'])) {
                $chosen = array_values(array_unique(array_map('intval', $data['affected_unit_ids'])));
                $out = $transaction->units()->where('status', EquipmentUnit::STATUS_ON_LOAN)->pluck('equipment_unit.unit_id')->map(fn ($id) => (int) $id)->all();
                if (array_diff($chosen, $out)) {
                    return ['error' => 'A chosen unit was not handed over on this loan. Close the window and try again.', 'code' => 422];
                }
                $affected = count($chosen);
            }

            if (!$good && $affected > $quantity) {
                return ['error' => 'This loan has ' . $quantity . ' ' . ($quantity === 1 ? 'unit' : 'units')
                    . ', so at most ' . $quantity . ' can be ' . strtolower($data['condition']) . '.', 'code' => 422];
            }

            // Good units go back to stock (never the chosen ones). Legacy loans
            // never deducted stock, so they never add it back.
            $goodUnits = $quantity - $affected;
            if ($goodUnits > 0 && $transaction->return_restores_stock) {
                $transaction->restoreStock($borrow, $goodUnits, $chosen);
            }

            // Damaged or incomplete units stay out of stock until they are repaired.
            if (in_array($data['condition'], ['Damaged', 'Incomplete'], true)) {
                $transaction->holdDamagedUnits($borrow, $data['condition']);
            }

            // A lost unit no longer exists, so it leaves the total quantity too.
            if ($data['condition'] === 'Lost') {
                $transaction->removeLostUnits($borrow, $affected);
            }

            // Which units they were (serial number, else code), when the loan recorded its units.
            $affectedLabels = $good ? '' : $transaction->units()
                ->wherePivot('return_condition', $data['condition'])
                ->get()
                ->sortBy(fn ($u) => EquipmentUnit::numberOf($u->unit_code))
                ->map(fn ($u) => $u->label())
                ->implode(', ');

            // Several units: say which (a single unit is already named on the loan).
            $unitsNote = !$good && $quantity > 1
                ? $affected . ' of ' . $quantity . ' units' . ($affectedLabels !== '' ? ': ' . $affectedLabels : '')
                : '';
            $remarksText = trim($unitsNote . ($unitsNote !== '' && $remarks !== '' ? '. ' : '') . $remarks);

            $transaction->update([
                'status' => 'Returned',
                // The unit came back when the user handed it in, not when it was inspected.
                // Its Philippine date (the clock runs in UTC).
                'return_date' => LoanTime::fromInstant($transaction->return_submitted_at ?? now())->toDateString(),
                // Same "Condition — remarks" format the history and reports read.
                'condition_on_return' => mb_substr($data['condition'] . ($remarksText !== '' ? ' — ' . $remarksText : ''), 0, 150),
                'inspected_condition' => $data['condition'],
                'affected_qty' => $good ? null : $affected,
                'inspection_remarks' => $remarks !== '' ? $remarks : null,
                'return_status' => BorrowTransaction::RETURN_CONFIRMED,
                'return_reviewed_by' => $dept->dept_acc_id,
                'return_reviewed_at' => now(),
                'return_reject_reason' => null,
            ]);

            $penalty = null;
            if (!empty($data['penalty_type'])) {
                // The borrower sees which unit the penalty is for.
                $penaltyUnits = $data['penalty_type'] === Penalty::TYPE_LATE || $affectedLabels === ''
                    ? ''
                    : ' (' . (str_contains($affectedLabels, ',') ? 'Units' : 'Unit') . ': ' . $affectedLabels . ')';
                $penalty = app(PenaltyService::class)->record($transaction, [
                    'type' => $data['penalty_type'],
                    'amount' => $data['penalty_amount'] ?? null,
                    'description' => rtrim($data['penalty_description']) . $penaltyUnits,
                ], AuditLogger::actorFor('dept', $dept));

                // Undo the whole confirmation rather than confirm without the penalty.
                if (is_string($penalty)) {
                    throw ValidationException::withMessages(['penalty_type' => $penalty]);
                }
            }

            $units = fn (int $n) => $n . ' ' . ($n === 1 ? 'unit' : 'units');
            $message = match (true) {
                $good => 'Return confirmed. The equipment is back in stock.',
                $quantity === 1 && $data['condition'] === 'Lost' => 'Return confirmed as Lost. The unit was removed from the equipment\'s total quantity.',
                $quantity === 1 => 'Return confirmed as ' . $data['condition'] . '. The unit is Under Maintenance (out of stock) until it is repaired.',
                $data['condition'] === 'Lost' => 'Return confirmed: ' . $units($affected) . ' lost (removed from the total quantity)'
                    . ($goodUnits > 0 ? ', ' . $units($goodUnits) . ' back in stock.' : '.'),
                default => 'Return confirmed: ' . $units($affected) . ' ' . strtolower($data['condition'])
                    . ' (Under Maintenance until repaired)'
                    . ($goodUnits > 0 ? ', ' . $units($goodUnits) . ' back in stock.' : '.'),
            };

            return [
                'action' => 'Department confirmed return of borrow request #' . $borrow->request_id
                    . ' for ' . ($borrow->equipment?->name ?? 'equipment') . ' (' . $data['condition'] . ')',
                'details' => $remarksText !== '' ? 'Remarks: ' . $remarksText : null,
                'message' => $message . ($penalty ? ' A ' . $penalty->type . ' penalty was recorded.' : ''),
            ];
        });
    }

    public function reject(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $reason = trim($data['reason'] ?? '');
        if ($reason === '') {
            return response()->json([
                'success' => false,
                'message' => 'Please state why the return is being rejected.',
            ], 422);
        }

        return $this->review($request, (int) $data['transaction_id'], function (BorrowTransaction $transaction, BorrowRequest $borrow, $dept) use ($reason) {
            // The loan itself (status Active / Overdue, stock) is left untouched.
            $transaction->update([
                'return_status' => BorrowTransaction::RETURN_REJECTED,
                'return_reviewed_by' => $dept->dept_acc_id,
                'return_reviewed_at' => now(),
                'return_reject_reason' => $reason,
            ]);

            return [
                'action' => 'Department rejected return of borrow request #' . $borrow->request_id
                    . ' for ' . ($borrow->equipment?->name ?? 'equipment'),
                'details' => 'Reason: ' . $reason,
                'message' => 'Return rejected. The item stays on loan to the user.',
            ];
        });
    }

    /**
     * Shared checks for confirm / reject: the transaction must belong to this
     * department's equipment and still be Pending Confirmation. The row is
     * locked so two reviewers cannot settle the same return twice.
     */
    private function review(Request $request, int $transactionId, callable $apply): JsonResponse
    {
        $dept = auth('dept')->user();
        $result = $this->runReview($request, $transactionId, $apply, $dept);

        if ($result['code'] !== 200) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['code']);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'returns' => $this->fetchReturns(),
        ]);
    }

    private function runReview(Request $request, int $transactionId, callable $apply, $dept): array
    {
        return DB::transaction(function () use ($request, $transactionId, $apply, $dept) {
            $transaction = DepartmentScope::loans()->whereKey($transactionId)
                ->lockForUpdate()
                ->first();

            if (!$transaction) {
                return ['code' => 404, 'message' => 'Return not found for your department.'];
            }

            if (!$transaction->isReturnPending()) {
                return ['code' => 400, 'message' => 'This return has already been reviewed.'];
            }

            $borrow = BorrowRequest::with('equipment')->find($transaction->request_id);
            $outcome = $apply($transaction, $borrow, $dept);

            // Refused before anything changed (e.g. more units affected than the loan has).
            if (isset($outcome['error'])) {
                return ['code' => $outcome['code'] ?? 400, 'message' => $outcome['error']];
            }

            AuditLogger::log($outcome['action'], 'borrow_request:' . $borrow->request_id, $outcome['details'], AuditLogger::actorFor('dept', $dept), $request);

            return ['code' => 200, 'message' => $outcome['message']];
        });
    }

    /** Every submitted return for this department's equipment, pending ones first. */
    private function fetchReturns(): array
    {
        $departmentId = DepartmentScope::id();

        if (!$departmentId) {
            return [];
        }

        $transactions = BorrowTransaction::with([
            'request.user.student',
            'request.user.facultyMember',
            'request.user.department',
            'request.equipment.category',
            'returnReviewer',
            'penalties',
            'units',
        ])
            ->whereNotNull('return_status')
            ->forDepartment($departmentId)
            ->get()
            ->sortBy([
                fn ($a, $b) => (int) !$a->isReturnPending() <=> (int) !$b->isReturnPending(),
                fn ($a, $b) => ($b->return_submitted_at?->getTimestamp() ?? 0) <=> ($a->return_submitted_at?->getTimestamp() ?? 0),
                fn ($a, $b) => $b->transaction_id <=> $a->transaction_id,
            ]);

        $fmtDate = fn ($v) => $v ? Carbon::parse($v)->format('M d, Y') : 'N/A';
        $fmtDateTime = fn ($v) => $v ? Carbon::parse($v)->format('M d, Y h:i A') : 'N/A';

        $rows = [];

        foreach ($transactions as $transaction) {
            $borrow = $transaction->request;
            $user = $borrow?->user;
            $equipment = $borrow?->equipment;

            if (!$borrow || !$equipment) {
                continue;
            }

            $fullName = $user?->fullName() ?: ($user?->email ?? '');
            [$reportedCondition, $reportedRemarks] = $this->splitCondition($transaction->reported_condition);
            $pending = $transaction->isReturnPending();

            $rows[] = [
                'id' => (int) $transaction->transaction_id,
                'returnId' => 'RET-' . str_pad((string) $transaction->transaction_id, 4, '0', STR_PAD_LEFT),
                'requestId' => (int) $borrow->request_id,
                'user' => $fullName !== '' ? $fullName : 'Unknown User',
                'role' => ucfirst($user?->role ?? 'Student'),
                'email' => $user?->email ?? '',
                // The borrower's own profile picture (null: the form shows an initials avatar).
                'avatar' => $this->profileImageUrl($user?->profile_image),
                'department' => $user?->department?->department_name ?: '—',
                'equipment' => $equipment->name,
                'category' => $equipment->category?->category_name ?: 'General',
                // The units on this loan (serial numbers, else codes).
                'equipmentId' => $transaction->unitLabels() ?: '—',
                // The photo the admin uploaded when adding the equipment (null: none).
                'equipmentImage' => $equipment->imageUrl(),
                'quantity' => max(1, (int) ($borrow->quantity ?? 1)),
                // Shows the "Expensive" tag in the table (display only).
                'requiresBothApprovals' => $borrow->requiresDualApproval(),
                'borrowDate' => LoanTime::format($transaction->borrow_date, 'N/A'),
                'dueDate' => LoanTime::format($transaction->due_date, 'N/A'),
                'submitted' => $fmtDate($transaction->return_submitted_at),
                'submittedFull' => $fmtDateTime($transaction->return_submitted_at),
                'status' => $transaction->return_status,
                'reportedCondition' => $reportedCondition,
                'reportedRemarks' => $reportedRemarks,
                // Before review the table shows what the user reported; afterwards what the department found.
                'condition' => $pending || $transaction->return_status === BorrowTransaction::RETURN_REJECTED
                    ? $reportedCondition
                    : ($transaction->inspected_condition ?: $this->splitCondition($transaction->condition_on_return)[0]),
                'conditionSource' => $pending || $transaction->return_status === BorrowTransaction::RETURN_REJECTED ? 'reported' : 'inspected',
                'inspectionRemarks' => $transaction->inspection_remarks ?? '',
                // Units in the inspected condition (0 for Good); the rest came back Good.
                'affectedUnits' => $transaction->affectedUnits(),
                // The units handed over on this loan (serial number, else code), with
                // how each came back once confirmed; the window ticks the affected ones.
                'units' => $transaction->units
                    ->sortBy(fn ($u) => EquipmentUnit::numberOf($u->unit_code))
                    ->map(fn ($u) => [
                        'id' => (int) $u->unit_id,
                        'label' => $u->label(),
                        'code' => $u->unit_code,
                        'out' => $u->status === EquipmentUnit::STATUS_ON_LOAN,
                        'condition' => $u->pivot->return_condition,
                    ])->values()->all(),
                'rejectReason' => $transaction->return_reject_reason ?? '',
                'reviewedBy' => $transaction->returnReviewer?->full_name ?: ($transaction->return_reviewed_by ? 'Department staff' : '—'),
                'reviewedAt' => $transaction->return_reviewed_at ? $fmtDateTime($transaction->return_reviewed_at) : '—',
                'canReview' => $pending,
                // Penalty step of the confirm form.
                'daysLate' => $transaction->daysLate(),
                'suggestedLateFee' => PenaltyService::suggestedLateFee($transaction),
                'penalties' => $transaction->penalties->map(fn (Penalty $penalty) => [
                    'type' => $penalty->type,
                    'amount' => $penalty->amountLabel() ?? 'No amount',
                    'status' => $penalty->status,
                ])->values()->all(),
            ];
        }

        return $rows;
    }

    /**
     * A stored profile picture as a URL the browser can load: full URLs, data
     * URIs and "/storage/..." paths as they are, older relative paths through
     * asset() (the same rule as Equipment::imageUrl()); null when there is none.
     */
    private function profileImageUrl(?string $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        return preg_match('/^(https?:\/\/|data:|\/storage\/)/i', $raw) ? $raw : asset(ltrim($raw, '/'));
    }

    /** "Condition — remarks" → [condition, remarks]. */
    private function splitCondition(?string $value): array
    {
        $parts = explode('—', trim((string) $value), 2);

        return [trim($parts[0]) !== '' ? trim($parts[0]) : '—', isset($parts[1]) ? trim($parts[1]) : ''];
    }
}
