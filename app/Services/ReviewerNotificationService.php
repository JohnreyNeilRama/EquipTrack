<?php

namespace App\Services;

use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\PenaltySubmission;
use App\Models\StaffNotificationRead;
use App\Support\LoanReminders;
use App\Support\LoanTime;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reservation Status Alerts for the reviewers: Admin and Lab Personnel
 * (department accounts).
 *
 * Notifications are derived live from borrow_request, borrow_transaction and
 * penalty_submission, so they always match the real records and can never be
 * duplicated. Each one has a deterministic key:
 *
 *   review-{request_id}-{admin|dept}-{approved|rejected}  a reviewer's decision
 *   request-{request_id}-new                              a request waiting for this reviewer
 *   pickup-{transaction_id}                               an item due for pickup today or earlier (Lab Personnel)
 *   return-{transaction_id}-submitted-{time}              a return waiting for confirmation (Lab Personnel)
 *   proof-{submission_id}-submitted                       a penalty proof waiting for verification
 *   returns-due-{date}                                    loans due back today (Lab Personnel)
 *   overdue-{transaction_id}-{late|follow-up}             an overdue loan (Lab Personnel)
 *   overdue-{transaction_id}-escalated                    a loan overdue past the escalation day (Admin)
 *   waiting-{date}                                        requests / proofs waiting too long (Admin)
 *
 * Scope:
 *  - Admin sees the decisions on every request, and the new Expensive requests
 *    waiting for the admin's review.
 *  - Lab Personnel sees the decisions on, the new requests for, the pickups
 *    and the returns of equipment owned by their own department, the loans
 *    due back today and every overdue loan.
 *  - Overdue timing follows App\Support\LoanReminders: Lab Personnel from
 *    the deadline (flagged again on the follow-up day), the admin only once
 *    a loan is still out on the escalation day.
 *
 * Only the read state is stored (staff_notification_reads).
 */
class ReviewerNotificationService
{
    public const ADMIN = 'admin';

    public const DEPT = 'dept';

    /** Decisions older than this are no longer listed. */
    private const WINDOW_DAYS = 30;

    private const LIMIT = 30;

    /**
     * @return array{notifications: array<int, array<string, mixed>>, unreadCount: int}
     */
    public function feed(string $type, int $accountId, ?int $departmentId): array
    {
        $readKeys = $this->readKeys($type, $accountId)->flip();

        $items = array_map(fn (array $n) => [
            'key' => $n['key'],
            'type' => $n['type'],
            'title' => $n['title'],
            'message' => $n['message'],
            'url' => $n['url'],
            'time' => $n['at']->toIso8601String(),
            'ago' => $n['at']->diffForHumans(),
            'read' => $readKeys->has($n['key']),
        ], $this->build($type, $accountId, $departmentId));

        return [
            'notifications' => $items,
            'unreadCount' => count(array_filter($items, fn (array $n) => !$n['read'])),
        ];
    }

    /**
     * Marks the given notifications (or all of them) read. Only keys that belong
     * to this account's own feed are stored, and the unique key makes repeats
     * harmless.
     *
     * @return array{saved: bool, unreadCount: int}
     */
    public function markRead(string $type, int $accountId, ?int $departmentId, array $keys, bool $all): array
    {
        $ownKeys = array_column($this->build($type, $accountId, $departmentId), 'key');
        $toMark = $all ? $ownKeys : array_values(array_intersect($keys, $ownKeys));

        if ($toMark) {
            $now = now();

            try {
                StaffNotificationRead::query()->insertOrIgnore(array_map(fn (string $key) => [
                    'account_type' => $type,
                    'account_id' => $accountId,
                    'notif_key' => $key,
                    'read_at' => $now,
                ], $toMark));
            } catch (QueryException $e) {
                Log::warning('Could not save staff notification read state (run "php artisan migrate"): ' . $e->getMessage());

                return ['saved' => false, 'unreadCount' => 0];
            }
        }

        $read = $this->readKeys($type, $accountId)->all();

        return ['saved' => true, 'unreadCount' => count(array_diff($ownKeys, $read))];
    }

    /** All current notifications for the account, newest first. */
    private function build(string $type, int $accountId, ?int $departmentId): array
    {
        $since = now()->subDays(self::WINDOW_DAYS);

        $query = BorrowRequest::with(['equipment', 'user.student', 'user.facultyMember', 'admin', 'departmentAccount'])
            ->where(function ($q) use ($since) {
                $q->where(fn ($w) => $w->whereIn('admin_status', ['Approved', 'Rejected'])
                        ->where('admin_reviewed_at', '>=', $since))
                    ->orWhere(fn ($w) => $w->whereIn('dept_status', ['Approved', 'Rejected'])
                        ->where('dept_reviewed_at', '>=', $since));
            });

        if ($type === self::DEPT) {
            // A department account without a department has nothing in scope.
            if (!$departmentId) {
                return [];
            }

            $query->whereHas('equipment', fn ($e) => $e->where('department_id', $departmentId));
        }

        $notifications = [];

        foreach ($query->get() as $row) {
            foreach ([self::ADMIN, self::DEPT] as $reviewer) {
                $status = $reviewer === self::ADMIN ? $row->admin_status : $row->dept_status;
                $at = $reviewer === self::ADMIN ? $row->admin_reviewed_at : $row->dept_reviewed_at;

                if (!in_array($status, ['Approved', 'Rejected'], true) || !$at || $at->lt($since)) {
                    continue;
                }

                $approved = $status === 'Approved';
                $actor = $this->actorLabel($reviewer, $row, $type, $accountId);
                $borrower = $row->user?->fullName() ?: ($row->user?->email ?: 'a user');
                $equipment = $row->equipment?->name ?? 'the equipment';
                $reason = trim((string) $row->reject_reason);

                $notifications[] = [
                    'key' => 'review-' . $row->request_id . '-' . $reviewer . '-' . ($approved ? 'approved' : 'rejected'),
                    'type' => $approved ? 'approved' : 'rejected',
                    'title' => $approved ? 'Reservation Approved' : 'Reservation Rejected',
                    'message' => $actor . ($approved ? ' approved ' : ' rejected ') . $borrower . "'s request for " . $equipment . '.'
                        . (!$approved && $reason !== '' ? ' Reason: ' . Str::limit($reason, 120) : ''),
                    'url' => $type === self::ADMIN ? route('admin.requests') : route('department.requests'),
                    'at' => $at,
                ];
            }
        }

        array_push($notifications, ...$this->newRequestNotifications($type, $departmentId));

        if ($type === self::DEPT) {
            array_push($notifications, ...$this->pickupNotifications($departmentId));
            array_push($notifications, ...$this->pendingReturnNotifications($departmentId));
            array_push($notifications, ...$this->dueTodayNotifications($departmentId));
            array_push($notifications, ...$this->departmentOverdueNotifications($departmentId));
        } else {
            array_push($notifications, ...$this->escalationNotifications());
            array_push($notifications, ...$this->waitingTooLongNotifications());
        }

        array_push($notifications, ...$this->proofNotifications($type, $departmentId));

        return collect($notifications)
            ->sortByDesc(fn (array $n) => $n['at']->getTimestamp())
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /**
     * New reservation requests waiting for this reviewer: one notice per request
     * while it is Pending on the viewer's side. The admin reviews Expensive
     * requests only; Lab Personnel review every request for their own
     * department's equipment. Once reviewed, rejected or cancelled the notice
     * is gone (the decision notice takes over).
     */
    private function newRequestNotifications(string $type, ?int $departmentId): array
    {
        $query = BorrowRequest::with(['equipment', 'user.student', 'user.facultyMember'])
            ->where('overall_status', 'Pending');

        if ($type === self::ADMIN) {
            $query->where('admin_status', 'Pending')
                ->where('equipment_classification', Equipment::CLASS_EXPENSIVE);
        } else {
            $query->where('dept_status', 'Pending')
                ->whereHas('equipment', fn ($e) => $e->where('department_id', $departmentId));
        }

        return $query->get()->map(function (BorrowRequest $row) use ($type) {
            $borrower = $row->user?->fullName() ?: ($row->user?->email ?: 'A user');
            $from = $row->borrow_date ?? $row->date_needed;
            $until = $row->due_date ?? $row->return_date;
            $dates = $from ? ' for ' . LoanTime::range($from, $until ?? $from)
                . (LoanTime::isWithinTheDay($from, $until) ? ' (Within the Day)' : '') : '';
            // Expensive equipment needs both approvals; tell each side who else reviews it.
            $also = $row->requiresDualApproval()
                ? ($type === self::ADMIN ? ' It also needs Lab Personnel approval.' : ' It also needs the admin\'s approval.')
                : '';

            return [
                'key' => 'request-' . $row->request_id . '-new',
                'type' => 'request',
                'title' => 'New Reservation Request',
                'message' => $borrower . ' requested ' . ($row->equipment?->name ?? 'equipment') . $dates
                    . '. Please review it.' . $also,
                'url' => $type === self::ADMIN ? route('admin.requests') : route('department.requests'),
                'at' => $row->date_requested ? Carbon::parse($row->date_requested) : now(),
            ];
        })->all();
    }

    /**
     * Approved equipment the borrower should pick up today, or whose pickup
     * date has passed without a release: one notice per loan while it is Ready
     * for Pickup, so Lab Personnel release it or mark it Not Claimed. Only Lab
     * Personnel release equipment, so the admin gets none.
     */
    private function pickupNotifications(?int $departmentId): array
    {
        $today = BorrowTransaction::today();
        $tz = config('app.display_timezone');

        return BorrowTransaction::with(['request.equipment', 'request.user.student', 'request.user.facultyMember'])
            ->where('status', BorrowTransaction::STATUS_READY)
            ->whereDate('borrow_date', '<=', $today->toDateString())
            ->whereHas('request.equipment', fn ($e) => $e->where('department_id', $departmentId))
            ->get()
            ->map(function (BorrowTransaction $transaction) use ($today, $tz) {
                $user = $transaction->request?->user;
                $borrower = $user?->fullName() ?: ($user?->email ?: 'A user');
                $equipment = $transaction->request?->equipment?->name ?? 'equipment';
                $passed = $transaction->borrow_date->lt($today);
                // Past the due date's return deadline it can no longer be released.
                $expired = LoanTime::isOverdue($transaction->due_date);

                return [
                    'key' => 'pickup-' . $transaction->transaction_id,
                    'type' => 'pickup',
                    'title' => $passed || $expired ? 'Pickup Date Passed' : 'Pickup Today',
                    'message' => match (true) {
                        $expired => $borrower . ' never picked up ' . $equipment . ' and its return date has passed. Mark it Not Claimed.',
                        $passed => $borrower . ' has not picked up ' . $equipment . ' (pickup date ' . $transaction->borrow_date->format('M d') . '). Release it or mark it Not Claimed.',
                        default => $borrower . ' is picking up ' . $equipment . ' today. Check their school ID, then release it.',
                    },
                    'url' => route('department.releases'),
                    // Arrives at the start of the pickup day (Philippine time).
                    'at' => Carbon::parse($transaction->borrow_date->toDateString(), $tz),
                ];
            })->all();
    }

    /**
     * Returns waiting for Lab Personnel to inspect and confirm: one notice per
     * submission while it is Pending Confirmation. The key carries the
     * submission time, so a return handed in again after a rejection is a new
     * unread notice. Only Lab Personnel confirm returns, so the admin gets none.
     */
    private function pendingReturnNotifications(?int $departmentId): array
    {
        return BorrowTransaction::with(['request.equipment', 'request.user.student', 'request.user.facultyMember'])
            ->where('return_status', BorrowTransaction::RETURN_PENDING)
            ->whereHas('request.equipment', fn ($e) => $e->where('department_id', $departmentId))
            ->get()
            ->map(function (BorrowTransaction $transaction) {
                $user = $transaction->request?->user;
                $borrower = $user?->fullName() ?: ($user?->email ?: 'A user');
                $submittedAt = $transaction->return_submitted_at ?? now();

                return [
                    'key' => 'return-' . $transaction->transaction_id . '-submitted-' . $submittedAt->getTimestamp(),
                    'type' => 'return',
                    'title' => 'Return Awaiting Confirmation',
                    'message' => $borrower . ' returned ' . ($transaction->request?->equipment?->name ?? 'equipment')
                        . '. Please inspect it and confirm the return.',
                    'url' => route('department.returns'),
                    'at' => $submittedAt,
                ];
            })->all();
    }

    /**
     * Proof of penalty settlement waiting for verification: one notice per
     * submission while it is Under Review. The admin sees every department's;
     * Lab Personnel only their own equipment's.
     */
    private function proofNotifications(string $type, ?int $departmentId): array
    {
        $query = PenaltySubmission::with(['user.student', 'user.facultyMember', 'penalty.transaction.request.equipment'])
            ->where('status', PenaltySubmission::STATUS_REVIEW);

        if ($type === self::DEPT) {
            $query->whereHas('penalty.transaction.request.equipment', fn ($e) => $e->where('department_id', $departmentId));
        }

        return $query->get()->map(function (PenaltySubmission $submission) use ($type) {
            $penalty = $submission->penalty;
            $borrower = $submission->user?->fullName() ?: ($submission->user?->email ?: 'A user');
            $penaltyId = 'PEN-' . str_pad((string) $submission->penalty_id, 4, '0', STR_PAD_LEFT);

            return [
                'key' => 'proof-' . $submission->submission_id . '-submitted',
                'type' => 'penalty',
                'title' => 'New Proof Submitted',
                'message' => $borrower . ' submitted proof for ' . $penaltyId . ' ('
                    . ($penalty?->transaction?->request?->equipment?->name ?? 'equipment') . '): '
                    . strtolower($submission->typeLabel()) . '. Please verify it.',
                'url' => $type === self::ADMIN ? route('admin.penalties') : route('department.penalties'),
                'at' => $submission->submitted_at ?? now(),
            ];
        })->all();
    }

    /**
     * Loans out on loan (released, return not submitted), with borrower and
     * equipment, optionally only for one department's equipment.
     *
     * @return Collection<int, BorrowTransaction>
     */
    private function loansOut(?int $departmentId = null): Collection
    {
        $query = BorrowTransaction::with([
            'request.equipment.department',
            'request.user.student',
            'request.user.facultyMember',
        ])
            ->onLoan()
            ->whereHas('request', fn ($q) => $q->where('overall_status', 'Approved'));

        if ($departmentId !== null) {
            $query->whereHas('request.equipment', fn ($e) => $e->where('department_id', $departmentId));
        }

        return $query->get()->filter(fn (BorrowTransaction $loan) => LoanReminders::isOut($loan))->values();
    }

    /**
     * One summary each due date while the deadline hasn't passed: "N items are
     * due back today", with the borrowers and items, so Lab Personnel know what
     * to expect at the counter. Gone once the deadline passes (the overdue
     * items take over for anything still out).
     */
    private function dueTodayNotifications(?int $departmentId): array
    {
        $now = LoanTime::now();
        $today = $now->toDateString();

        $due = $this->loansOut($departmentId)
            ->filter(fn (BorrowTransaction $loan) => $loan->due_date->toDateString() === $today
                && !LoanTime::isOverdue($loan->due_date, $now))
            ->sortBy(fn (BorrowTransaction $loan) => $loan->due_date->getTimestamp())
            ->values();

        if ($due->isEmpty()) {
            return [];
        }

        $names = $due->take(3)->map(fn (BorrowTransaction $loan) => ($loan->request?->equipment?->name ?? 'Equipment')
            . ' (' . $this->borrowerName($loan) . ')')->implode(', ');
        $more = $due->count() > 3 ? ' and ' . ($due->count() - 3) . ' more' : '';
        $count = $due->count();
        $by = LoanReminders::time($due->first()->due_date);

        return [[
            'key' => 'returns-due-' . $today,
            'type' => 'return',
            'title' => 'Due Back Today',
            'message' => $count . ' ' . ($count === 1 ? 'item is' : 'items are') . ' due back today by ' . $by . ': ' . $names . $more . '.',
            'url' => route('department.monitoring'),
            'at' => LoanReminders::instant(Carbon::parse($today)),
        ]];
    }

    /**
     * One item per overdue loan of the department's equipment, from the moment
     * the deadline passes until the item is returned. On the follow-up day it
     * comes back as unread, asking Lab Personnel to follow up or record a
     * penalty.
     */
    private function departmentOverdueNotifications(?int $departmentId): array
    {
        $followUp = LoanReminders::departmentFollowUpDays();
        $today = LoanTime::now()->toDateString();

        return $this->loansOut($departmentId)
            ->map(function (BorrowTransaction $loan) use ($followUp, $today) {
                $daysLate = LoanReminders::daysLate($loan);

                if ($daysLate <= 0) {
                    return null;
                }

                $equipment = $loan->request?->equipment?->name ?? 'equipment';
                $borrower = $this->borrowerName($loan);
                $late = LoanReminders::daysLabel($daysLate) . ' late';

                if ($daysLate >= $followUp) {
                    return [
                        'key' => 'overdue-' . $loan->transaction_id . '-follow-up',
                        'type' => 'overdue',
                        'title' => 'Overdue: Follow Up',
                        'message' => $borrower . ' still has ' . $equipment . ' (due ' . LoanTime::dueBy($loan->due_date) . ', '
                            . $late . '). Follow up with them, or record a Late Return penalty.',
                        'url' => route('department.monitoring'),
                        'at' => LoanReminders::lateSince($loan, $followUp),
                    ];
                }

                $sameDay = $loan->due_date->toDateString() === $today;

                return [
                    'key' => 'overdue-' . $loan->transaction_id . '-late',
                    'type' => 'overdue',
                    'title' => $sameDay ? 'Not Returned by Closing' : 'Overdue Equipment',
                    'message' => $borrower . ' has not returned ' . $equipment . ' (due ' . LoanTime::dueBy($loan->due_date) . ')'
                        . ($sameDay ? '.' : ', ' . $late . '.'),
                    'url' => route('department.monitoring'),
                    'at' => LoanReminders::lateSince($loan, 1),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Admin: loans still out on the escalation day (7 office days late by
     * default), one item each, so the admin sees only what Lab Personnel have
     * not resolved.
     */
    private function escalationNotifications(): array
    {
        $escalation = LoanReminders::adminEscalationDays();

        return $this->loansOut()
            ->map(function (BorrowTransaction $loan) use ($escalation) {
                $daysLate = LoanReminders::daysLate($loan);

                if ($daysLate < $escalation) {
                    return null;
                }

                $equipment = $loan->request?->equipment?->name ?? 'Equipment';
                $department = $loan->request?->equipment?->department?->department_name;

                return [
                    'key' => 'overdue-' . $loan->transaction_id . '-escalated',
                    'type' => 'overdue',
                    'title' => 'Overdue ' . $escalation . '+ Days',
                    'message' => $equipment . ($department ? ' (' . $department . ')' : '') . ' is '
                        . LoanReminders::daysLabel($daysLate) . ' overdue: ' . $this->borrowerName($loan)
                        . ' has not returned it (due ' . LoanTime::format($loan->due_date) . ').',
                    'url' => route('admin.monitoring'),
                    'at' => LoanReminders::lateSince($loan, $escalation),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Admin: one summary per office day, only when something has waited too
     * long: Expensive requests still waiting for the admin's review, and
     * penalty proofs still waiting for verification.
     */
    private function waitingTooLongNotifications(): array
    {
        $now = LoanTime::now();

        if (!LoanTime::isOfficeDay($now)) {
            return [];
        }

        $requestDays = LoanReminders::staleRequestDays();
        $proofDays = LoanReminders::staleProofDays();

        $requests = BorrowRequest::query()
            ->where('overall_status', 'Pending')
            ->where('admin_status', 'Pending')
            ->where('equipment_classification', Equipment::CLASS_EXPENSIVE)
            ->where('date_requested', '<=', now()->subDays($requestDays))
            ->count();

        $proofs = PenaltySubmission::query()
            ->where('status', PenaltySubmission::STATUS_REVIEW)
            ->where('submitted_at', '<=', now()->subDays($proofDays))
            ->count();

        if ($requests === 0 && $proofs === 0) {
            return [];
        }

        $parts = [];
        if ($requests > 0) {
            $parts[] = $requests . ' Expensive ' . ($requests === 1 ? 'request has' : 'requests have')
                . ' waited more than ' . LoanReminders::daysLabel($requestDays) . ' for your review';
        }
        if ($proofs > 0) {
            $parts[] = $proofs . ' penalty ' . ($proofs === 1 ? 'proof has' : 'proofs have')
                . ' waited more than ' . LoanReminders::daysLabel($proofDays) . ' for verification';
        }

        $today = $now->toDateString();

        return [[
            'key' => 'waiting-' . $today,
            'type' => 'request',
            'title' => 'Waiting Too Long',
            'message' => implode('; ', $parts) . '.',
            'url' => $requests > 0 ? route('admin.requests') : route('admin.penalties'),
            'at' => LoanReminders::instant(Carbon::parse($today)),
        ]];
    }

    private function borrowerName(BorrowTransaction $loan): string
    {
        $user = $loan->request?->user;

        return $user?->fullName() ?: ($user?->email ?: 'A borrower');
    }

    /** Who made the decision, from the viewer's point of view. */
    private function actorLabel(string $reviewer, BorrowRequest $row, string $viewerType, int $viewerId): string
    {
        if ($reviewer === self::ADMIN) {
            if ($viewerType === self::ADMIN && (int) $row->admin_id === $viewerId) {
                return 'You';
            }

            $name = trim((string) $row->admin?->name);

            return $name !== '' ? 'Admin ' . $name : 'The admin';
        }

        if ($viewerType === self::DEPT && (int) $row->dept_acc_id === $viewerId) {
            return 'You';
        }

        $name = trim((string) $row->departmentAccount?->full_name);

        return $name !== '' ? $name . ' (Lab Personnel)' : 'Lab Personnel';
    }

    /**
     * Keys this account has already read. If the read-state table has not been
     * migrated yet, notifications still load (all unread) instead of failing.
     */
    private function readKeys(string $type, int $accountId): Collection
    {
        try {
            return StaffNotificationRead::where('account_type', $type)
                ->where('account_id', $accountId)
                ->pluck('notif_key');
        } catch (QueryException $e) {
            Log::warning('staff_notification_reads is unavailable (run "php artisan migrate"): ' . $e->getMessage());

            return collect();
        }
    }
}
