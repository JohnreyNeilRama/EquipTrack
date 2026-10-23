<?php

namespace App\Services;

use App\Models\BorrowTransaction;
use App\Models\Penalty;
use App\Models\PenaltySubmission;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Damage & Penalty Recording, shared by the department (Lab Personnel) and
 * admin pages. Callers decide the scope (which penalties / loans the account
 * may touch); this class records, edits, settles and waives penalties, writes
 * the audit trail, and shapes the rows the pages show.
 *
 * Nothing here takes payments: Settled only records that the penalty was paid
 * (or otherwise fulfilled) outside the system.
 *
 * $actor is AuditLogger::actorFor(...): ['admin_id' => ..] or ['dept_acc_id' => ..].
 */
class PenaltyService
{
    /** Suggested Late Return amount for a loan (days late × the configured rate), or null. */
    public static function suggestedLateFee(BorrowTransaction $transaction): ?float
    {
        $rate = (float) config('equiptrack.penalty_late_per_day', 0);
        $days = $transaction->daysLate();

        return $rate > 0 && $days > 0 ? round($rate * $days, 2) : null;
    }

    /** The penalty type that matches an inspected condition, if any. */
    public static function typeForCondition(?string $condition): ?string
    {
        return in_array($condition, [Penalty::TYPE_DAMAGED, Penalty::TYPE_INCOMPLETE, Penalty::TYPE_LOST], true)
            ? $condition
            : null;
    }

    /**
     * Records a Pending penalty on a returned loan. Returns an error message
     * instead when the loan is not returned yet or already has a penalty of
     * that type.
     *
     * @param  array{type: string, amount?: float|string|null, description: string}  $data
     */
    public function record(BorrowTransaction $transaction, array $data, array $actor): Penalty|string
    {
        if ($transaction->status !== 'Returned') {
            return 'Penalties can only be recorded on returned equipment.';
        }

        if ($transaction->penalties()->where('type', $data['type'])->exists()) {
            return 'This return already has a ' . $data['type'] . ' penalty.';
        }

        $borrow = $transaction->request;

        $penalty = Penalty::create([
            'transaction_id' => $transaction->transaction_id,
            'user_id' => $borrow->user_id,
            'type' => $data['type'],
            'amount' => $this->amount($data['amount'] ?? null),
            'description' => trim($data['description']),
            'status' => Penalty::STATUS_PENDING,
            'recorded_by_admin_id' => $actor['admin_id'] ?? null,
            'recorded_by_dept_acc_id' => $actor['dept_acc_id'] ?? null,
            'recorded_at' => now(),
        ]);

        AuditLogger::log(
            'Recorded penalty #' . $penalty->penalty_id . ' (' . $penalty->type . ') for borrow request #' . $borrow->request_id,
            'penalty:' . $penalty->penalty_id,
            $this->summary($penalty),
            $actor,
        );

        return $penalty;
    }

    /** Changes the amount / description of a Pending penalty. */
    public function update(Penalty $penalty, array $data, array $actor): ?string
    {
        return DB::transaction(function () use ($penalty, $data, $actor) {
            $locked = Penalty::whereKey($penalty->penalty_id)->lockForUpdate()->first();

            if (!$locked || !$locked->isPending()) {
                return 'Only pending penalties can be edited.';
            }

            $locked->update([
                'amount' => $this->amount($data['amount'] ?? null),
                'description' => trim($data['description']),
            ]);

            AuditLogger::log('Updated penalty #' . $locked->penalty_id . ' (' . $locked->type . ')', 'penalty:' . $locked->penalty_id, $this->summary($locked), $actor);

            return null;
        });
    }

    /** Marks a Pending penalty Settled (paid or fulfilled outside the system). */
    public function settle(Penalty $penalty, ?string $referenceNo, ?string $remarks, array $actor): ?string
    {
        return $this->close($penalty, Penalty::STATUS_SETTLED, $referenceNo, $remarks, $actor);
    }

    /** Marks a Pending penalty Waived; the reason is required by the caller. */
    public function waive(Penalty $penalty, string $reason, array $actor): ?string
    {
        return $this->close($penalty, Penalty::STATUS_WAIVED, null, $reason, $actor);
    }

    private function close(Penalty $penalty, string $status, ?string $referenceNo, ?string $remarks, array $actor): ?string
    {
        return DB::transaction(function () use ($penalty, $status, $referenceNo, $remarks, $actor) {
            $locked = Penalty::whereKey($penalty->penalty_id)->lockForUpdate()->first();

            if (!$locked || !$locked->isPending()) {
                return 'This penalty has already been settled or waived.';
            }

            $referenceNo = trim((string) $referenceNo);
            $remarks = trim((string) $remarks);

            $locked->update([
                'status' => $status,
                'settled_by_admin_id' => $actor['admin_id'] ?? null,
                'settled_by_dept_acc_id' => $actor['dept_acc_id'] ?? null,
                'settled_at' => now(),
                'reference_no' => $referenceNo !== '' ? $referenceNo : null,
                'settle_remarks' => $remarks !== '' ? $remarks : null,
            ]);

            $verb = $status === Penalty::STATUS_SETTLED ? 'Settled' : 'Waived';
            $details = $status === Penalty::STATUS_SETTLED
                ? trim(($referenceNo !== '' ? 'Reference: ' . $referenceNo . '. ' : '') . $remarks)
                : 'Reason: ' . $remarks;

            AuditLogger::log($verb . ' penalty #' . $locked->penalty_id . ' (' . $locked->type . ')', 'penalty:' . $locked->penalty_id, $details, $actor);

            return null;
        });
    }

    /**
     * Rows for the Penalties pages (and the user's My Penalties page), newest
     * first. $proofFileRoute is the route that serves proof files to the
     * viewer; it is needed to list the submitted proof.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rows(Builder $query, ?string $proofFileRoute = null): array
    {
        $fmt = fn ($value) => $value ? Carbon::parse($value)->format('M d, Y') : '—';
        // Stored in UTC; shown in Philippine time.
        $tz = config('app.display_timezone');
        $fmtTime = fn ($value) => $value ? Carbon::parse($value)->setTimezone($tz)->format('M d, Y h:i A') : '—';

        return $query->with([
            'transaction.request.equipment.department',
            'transaction.request.equipment.category',
            'user.student',
            'user.facultyMember',
            'recordedByAdmin',
            'recordedByDept',
            'settledByAdmin',
            'settledByDept',
            'submissions.files',
            'submissions.reviewedByAdmin',
            'submissions.reviewedByDept',
        ])
            ->orderByRaw("case when status = 'Pending' then 0 else 1 end")
            ->orderByDesc('recorded_at')
            ->orderByDesc('penalty_id')
            ->get()
            ->map(function (Penalty $penalty) use ($fmt, $fmtTime, $tz, $proofFileRoute) {
                $transaction = $penalty->transaction;
                $latest = $penalty->submissions->first();
                $borrow = $transaction?->request;
                $equipment = $borrow?->equipment;
                $user = $penalty->user;

                return [
                    'id' => (int) $penalty->penalty_id,
                    'penaltyId' => 'PEN-' . str_pad((string) $penalty->penalty_id, 4, '0', STR_PAD_LEFT),
                    'requestId' => (int) ($borrow?->request_id ?? 0),
                    'user' => $user ? ($user->fullName() ?: $user->email) : 'Deleted account',
                    'role' => $user?->role ?: '—',
                    'email' => $user?->email ?? '',
                    'equipment' => $equipment?->name ?? 'Deleted equipment',
                    'serial' => $equipment?->serial_number ?: '—',
                    // Shown on the department's record view (photo, category, units, borrower picture).
                    'equipmentImage' => $equipment?->imageUrl(),
                    'category' => $equipment?->category?->category_name ?: 'General',
                    'quantity' => max(1, (int) ($borrow?->quantity ?? 1)),
                    'avatar' => $this->imageUrl($user?->profile_image),
                    'department' => $equipment?->department?->department_name ?: '—',
                    'type' => $penalty->type,
                    'amount' => $penalty->amount !== null ? (float) $penalty->amount : null,
                    'amountLabel' => $penalty->amountLabel() ?? 'No amount',
                    'description' => $penalty->description,
                    'status' => $penalty->status,
                    'dueDate' => $fmt($transaction?->due_date),
                    'returnDate' => $fmt($transaction?->return_date),
                    'daysLate' => $transaction ? $transaction->daysLate() : 0,
                    'condition' => $transaction?->inspected_condition ?: '—',
                    'recordedBy' => $this->actorName($penalty->recordedByAdmin, $penalty->recordedByDept),
                    'recordedAt' => $fmtTime($penalty->recorded_at),
                    'recordedDate' => $penalty->recorded_at?->copy()->setTimezone($tz)->toDateString(),
                    'settledBy' => $penalty->isPending() ? '—' : $this->actorName($penalty->settledByAdmin, $penalty->settledByDept),
                    'settledAt' => $fmtTime($penalty->settled_at),
                    'referenceNo' => $penalty->reference_no ?? '',
                    'settleRemarks' => $penalty->settle_remarks ?? '',
                    'canEdit' => $penalty->isPending(),
                    // Proof of settlement submitted by the user (newest first).
                    'proofState' => $this->proofState($penalty, $latest),
                    'awaitingReview' => (bool) $latest?->isUnderReview(),
                    'proofTypes' => PenaltySubmission::typesFor($penalty->type),
                    'proof' => $latest && $proofFileRoute ? $latest->toProofArray($proofFileRoute) : null,
                    'proofHistory' => $proofFileRoute
                        ? $penalty->submissions->slice(1)->map(fn (PenaltySubmission $s) => $s->toProofArray($proofFileRoute))->values()->all()
                        : [],
                ];
            })
            ->all();
    }

    /**
     * Returned loans a penalty can still be added to (admin "Add Penalty"):
     * the latest returns first, with the types they do not have yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function returnedLoans(Builder $query, int $limit = 200): array
    {
        return $query->with(['request.equipment', 'request.user.student', 'request.user.facultyMember', 'penalties'])
            ->where('status', 'Returned')
            ->orderByDesc('return_date')
            ->orderByDesc('transaction_id')
            ->limit($limit)
            ->get()
            ->map(function (BorrowTransaction $transaction) {
                $borrow = $transaction->request;
                $taken = $transaction->penalties->pluck('type')->all();
                $available = array_values(array_diff(Penalty::TYPES, $taken));

                if (!$borrow || $available === []) {
                    return null;
                }

                $user = $borrow->user;

                return [
                    'id' => (int) $transaction->transaction_id,
                    'label' => 'Request #' . $borrow->request_id . ' · ' . ($borrow->equipment?->name ?? 'Equipment')
                        . ' · ' . ($user ? ($user->fullName() ?: $user->email) : 'Deleted account')
                        . ' · returned ' . ($transaction->return_date?->format('M d, Y') ?? '—'),
                    'types' => $available,
                    'daysLate' => $transaction->daysLate(),
                    'suggestedLateFee' => self::suggestedLateFee($transaction),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * none / review / rejected while the penalty is Pending (from the latest
     * proof), then settled / waived.
     */
    private function proofState(Penalty $penalty, ?PenaltySubmission $latest): string
    {
        if (!$penalty->isPending()) {
            return strtolower($penalty->status);
        }

        return match ($latest?->status) {
            PenaltySubmission::STATUS_REVIEW => 'review',
            PenaltySubmission::STATUS_REJECTED => 'rejected',
            default => 'none',
        };
    }

    private function amount($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }

    /**
     * A stored picture as a URL the browser can load: full URLs, data URIs and
     * "/storage/..." paths as they are, older relative paths through asset()
     * (the same rule as Equipment::imageUrl()); null when there is none.
     */
    private function imageUrl(?string $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        return preg_match('/^(https?:\/\/|data:|\/storage\/)/i', $raw) ? $raw : asset(ltrim($raw, '/'));
    }

    private function summary(Penalty $penalty): string
    {
        return ($penalty->amountLabel() ?? 'No amount') . '. ' . $penalty->description;
    }

    private function actorName($admin, $dept): string
    {
        if ($admin) {
            return ($admin->name ?: 'Administrator') . ' (Admin)';
        }

        if ($dept) {
            return ($dept->full_name ?: $dept->email) . ' (Lab Personnel)';
        }

        return '—';
    }
}
