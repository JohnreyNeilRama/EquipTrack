<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\LoanManualReminder;
use App\Models\Penalty;
use App\Models\PenaltySubmission;
use App\Models\UserNotificationRead;
use App\Support\LoanReminders;
use App\Support\LoanTime;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Notification bell for Students / Faculty.
 *
 * Notifications are derived live from the signed-in user's own borrowing
 * records, so they always match the real request status and return dates and
 * can never be duplicated: each one has a deterministic key
 * (request-{id}-approved, request-{id}-rejected, due-{transaction_id}-{stage},
 * overdue-{transaction_id}-{step}, reminder-{id},
 * return-{transaction_id}-confirmed, return-{transaction_id}-rejected-{time}).
 * Due-date reminders follow App\Support\LoanReminders.
 * Only the read state is stored (user_notification_reads).
 *
 * Every query is scoped to the authenticated user.
 */
class UserNotificationController extends Controller
{
    /** Approved / rejected notices older than this are no longer listed. */
    private const STATUS_WINDOW_DAYS = 30;

    private const LIMIT = 30;

    /** JSON feed polled by the bell dropdown. */
    public function index(): JsonResponse
    {
        $userId = (int) auth('user')->user()->user_id;

        $notifications = $this->build($userId);
        $readKeys = $this->readKeys($userId)->flip();

        $items = array_map(fn (array $n) => [
            'key' => $n['key'],
            'type' => $n['type'],
            'title' => $n['title'],
            'message' => $n['message'],
            'url' => $n['url'],
            'time' => $n['at']->toIso8601String(),
            'ago' => $n['at']->diffForHumans(),
            'read' => $readKeys->has($n['key']),
        ], $notifications);

        return response()->json([
            'success' => true,
            'notifications' => $items,
            'unreadCount' => count(array_filter($items, fn ($n) => !$n['read'])),
        ]);
    }

    /**
     * Marks notifications read: either the given keys or all of them. Only keys
     * that belong to the signed-in user's own notifications are stored, and the
     * (user_id, notif_key) unique key makes repeats harmless.
     */
    public function markRead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'all' => ['nullable', 'boolean'],
            'keys' => ['nullable', 'array', 'max:100'],
            'keys.*' => ['string', 'max:100'],
        ]);

        $userId = (int) auth('user')->user()->user_id;
        $ownKeys = array_column($this->build($userId), 'key');

        $toMark = !empty($data['all'])
            ? $ownKeys
            : array_values(array_intersect($data['keys'] ?? [], $ownKeys));

        if ($toMark) {
            $now = now();

            try {
                UserNotificationRead::query()->insertOrIgnore(array_map(fn (string $key) => [
                    'user_id' => $userId,
                    'notif_key' => $key,
                    'read_at' => $now,
                ], $toMark));
            } catch (QueryException $e) {
                Log::warning('Could not save notification read state (run "php artisan migrate"): ' . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'message' => 'Read state is not available yet. Please run the database migration.',
                ], 503);
            }
        }

        $readKeys = $this->readKeys($userId)->all();

        return response()->json([
            'success' => true,
            'unreadCount' => count(array_diff($ownKeys, $readKeys)),
        ]);
    }

    /**
     * Keys the user has already read. If the read-state table has not been
     * migrated yet, notifications still load (all unread) instead of the whole
     * feed failing.
     */
    private function readKeys(int $userId): Collection
    {
        try {
            return UserNotificationRead::where('user_id', $userId)->pluck('notif_key');
        } catch (QueryException $e) {
            Log::warning('user_notification_reads is unavailable (run "php artisan migrate"): ' . $e->getMessage());

            return collect();
        }
    }

    /** All current notifications for the user, newest first. */
    private function build(int $userId): array
    {
        return collect($this->statusNotifications($userId))
            ->merge($this->loanReminderNotifications($userId))
            ->merge($this->manualReminderNotifications($userId))
            ->merge($this->returnNotifications($userId))
            ->merge($this->penaltyNotifications($userId))
            ->sortByDesc(fn (array $n) => $n['at']->getTimestamp())
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /** One notice per request that the admin or department approved / rejected, or that expired unreviewed. */
    private function statusNotifications(int $userId): array
    {
        $since = now()->subDays(self::STATUS_WINDOW_DAYS);
        $rows = BorrowRequest::with(['equipment', 'transaction'])
            ->where('user_id', $userId)
            ->whereIn('overall_status', ['Approved', 'Rejected', BorrowRequest::STATUS_EXPIRED])
            ->get();

        $notifications = [];

        foreach ($rows as $row) {
            if ($row->overall_status === BorrowRequest::STATUS_EXPIRED) {
                if ($notice = $this->expiredNotification($row, $since)) {
                    $notifications[] = $notice;
                }

                continue;
            }

            // overall_status follows whichever approver acted last.
            $at = $this->latest($row->admin_reviewed_at, $row->dept_reviewed_at);

            if (!$at || $at->lt($since)) {
                continue;
            }

            $equipment = $row->equipment?->name ?? 'the equipment';
            // "3 units of Arduino Kit" when the request was for several units.
            if ((int) $row->quantity > 1) {
                $equipment = (int) $row->quantity . ' units of ' . $equipment;
            }

            if ($row->overall_status === 'Approved') {
                // Approval holds the unit; the borrower picks it up at the lab.
                $pickup = $row->transaction?->borrow_date;

                $notifications[] = [
                    'key' => 'request-' . $row->request_id . '-approved',
                    'type' => 'approved',
                    'title' => 'Request Approved',
                    'message' => 'Your request to borrow ' . $equipment . ' has been approved. It is ready for pickup'
                        . ($pickup ? ' on ' . $pickup->format('M d, Y') : '') . '. Bring your school ID to the lab.',
                    'url' => route('user.returns'),
                    'at' => $at,
                ];

                // Never picked up: the request was closed and the unit freed.
                $unclaimedAt = $row->transaction?->isNotClaimed() ? $row->transaction->unclaimed_at : null;
                if ($unclaimedAt && $unclaimedAt->gte($since)) {
                    $notifications[] = [
                        'key' => 'request-' . $row->request_id . '-not-claimed',
                        'type' => 'rejected',
                        'title' => 'Not Claimed',
                        'message' => 'You did not pick up ' . $equipment . ', so the lab closed the request. You can request it again.',
                        'url' => route('user.history'),
                        'at' => $unclaimedAt,
                    ];
                }

                // The lab cancelled the reservation before handing it over.
                $cancelledAt = $row->transaction?->isCancelled() ? $row->transaction->cancelled_at : null;
                if ($cancelledAt && $cancelledAt->gte($since)) {
                    $reason = trim((string) $row->transaction->cancel_reason);
                    $notifications[] = [
                        'key' => 'request-' . $row->request_id . '-reservation-cancelled',
                        'type' => 'rejected',
                        'title' => 'Reservation Cancelled',
                        'message' => 'The lab cancelled your reservation for ' . $equipment . '.'
                            . ($reason !== '' ? ' Reason: ' . Str::limit($reason, 120) : '') . ' You can request it again.',
                        'url' => route('user.history'),
                        'at' => $cancelledAt,
                    ];
                }

                continue;
            }

            $reason = trim((string) $row->reject_reason);

            $notifications[] = [
                'key' => 'request-' . $row->request_id . '-rejected',
                'type' => 'rejected',
                'title' => 'Request Rejected',
                'message' => 'Your request to borrow ' . $equipment . ' was rejected.'
                    . ($reason !== '' ? ' Reason: ' . Str::limit($reason, 120) : ''),
                'url' => route('user.requests'),
                'at' => $at,
            ];
        }

        return $notifications;
    }

    /** "Request Expired", dated at the return deadline it missed (Philippine time). */
    private function expiredNotification(BorrowRequest $row, Carbon $since): ?array
    {
        $due = $row->due_date ?? $row->return_date;
        $at = $due ? Carbon::parse($due->format('Y-m-d H:i:s'), config('app.display_timezone')) : null;

        if (!$at || $at->lt($since)) {
            return null;
        }

        return [
            'key' => 'request-' . $row->request_id . '-expired',
            'type' => 'rejected',
            'title' => 'Request Expired',
            'message' => 'Your request to borrow ' . ($row->equipment?->name ?? 'the equipment')
                . ' expired: it was not reviewed before ' . LoanTime::dueBy($due) . '. You can submit a new request.',
            'url' => route('user.requests'),
            'at' => $at,
        ];
    }

    /**
     * Return reminders for every loan still with the user, on the schedule in
     * App\Support\LoanReminders. Before the due date: one item for the
     * current stage (N days before for long loans, the day before, the due
     * date; for Within the Day loans "return it today" and "N hours left").
     * After the deadline: one overdue item showing the days late, which comes
     * back as unread on each overdue step (1, 3, 7 office days late, then
     * weekly), not every day. Loans whose return the department is verifying
     * are skipped.
     */
    private function loanReminderNotifications(int $userId): array
    {
        $loans = BorrowTransaction::with('request.equipment')
            ->onLoan()
            ->whereHas('request', fn ($query) => $query
                ->where('user_id', $userId)
                ->where('overall_status', 'Approved'))
            ->get();

        $notifications = [];

        foreach ($loans as $loan) {
            if (!LoanReminders::isOut($loan)) {
                continue;
            }

            $notice = LoanReminders::daysLate($loan) > 0
                ? $this->overdueNotification($loan)
                : $this->dueNotification($loan);

            if ($notice) {
                $notifications[] = $notice;
            }
        }

        return $notifications;
    }

    /** The current before-due stage of a loan, or null. */
    private function dueNotification(BorrowTransaction $loan): ?array
    {
        $upcoming = LoanReminders::upcoming($loan);

        if (!$upcoming) {
            return null;
        }

        $equipment = $loan->request?->equipment?->name ?? 'The borrowed equipment';
        $by = LoanReminders::time($loan->due_date);
        $days = $upcoming['daysLeft'];
        $hours = LoanReminders::hoursLabel(LoanReminders::sameDayHoursBefore());

        [$title, $message] = match ($upcoming['stage']) {
            LoanReminders::STAGE_SAME_DAY => ['Return Today',
                'You have ' . $equipment . '. Return it to the lab today by ' . $by . '.'],
            LoanReminders::STAGE_FINAL => [ucwords($hours) . ' Left',
                'Return ' . $equipment . ' to the lab by ' . $by . ' today. ' . ucfirst($hours) . ' left.'],
            LoanReminders::STAGE_DUE_TODAY => ['Due Today',
                $equipment . ' is due today by ' . $by . '. Please return it on time.'],
            LoanReminders::STAGE_DAY_BEFORE => ['Due Tomorrow',
                $equipment . ' is due tomorrow (' . LoanTime::dueBy($loan->due_date) . '). Please return it on time.'],
            default => ['Due in ' . $days . ' Days',
                $equipment . ' is due in ' . $days . ' days (' . LoanTime::dueBy($loan->due_date) . '). Please return it on time.'],
        };

        return [
            'key' => 'due-' . $loan->transaction_id . '-' . $upcoming['stage'],
            'type' => 'due',
            'title' => $title,
            'message' => $message,
            'url' => route('user.returns'),
            'at' => $upcoming['at'],
        ];
    }

    /** One overdue item per loan; its key changes (unread again) on each overdue step. */
    private function overdueNotification(BorrowTransaction $loan): array
    {
        $daysLate = LoanReminders::daysLate($loan);
        $step = LoanReminders::overdueStep($daysLate);
        $equipment = $loan->request?->equipment?->name ?? 'The borrowed equipment';
        $escalated = $daysLate >= LoanReminders::adminEscalationDays();

        $message = $equipment . ' was due on ' . LoanTime::format($loan->due_date)
            . ' (' . LoanReminders::daysLabel($daysLate) . ' late). Please return it as soon as possible.'
            . ($escalated ? ' This has been reported to the Admin.' : '');

        return [
            'key' => 'overdue-' . $loan->transaction_id . '-' . $step,
            'type' => 'overdue',
            'title' => $escalated ? 'Overdue: Final Notice' : 'Overdue Equipment',
            'message' => $message,
            'url' => route('user.returns'),
            'at' => LoanReminders::lateSince($loan, $step),
        ];
    }

    /**
     * Reminders Admin or Lab Personnel sent by hand, while the item is still
     * out. If the table has not been migrated yet, the rest of the feed still
     * loads.
     */
    private function manualReminderNotifications(int $userId): array
    {
        $since = now()->subDays(self::STATUS_WINDOW_DAYS);

        try {
            $reminders = LoanManualReminder::with(['transaction.request.equipment', 'admin', 'departmentAccount'])
                ->where('sent_at', '>=', $since)
                ->whereHas('transaction.request', fn ($query) => $query->where('user_id', $userId))
                ->get();
        } catch (QueryException $e) {
            Log::warning('loan_manual_reminders is unavailable (run "php artisan migrate"): ' . $e->getMessage());

            return [];
        }

        return $reminders
            ->filter(fn (LoanManualReminder $reminder) => $reminder->transaction && LoanReminders::isOut($reminder->transaction))
            ->map(function (LoanManualReminder $reminder) {
                $loan = $reminder->transaction;
                $equipment = $loan->request?->equipment?->name ?? 'the equipment';

                return [
                    'key' => 'reminder-' . $reminder->id,
                    'type' => LoanReminders::daysLate($loan) > 0 ? 'overdue' : 'due',
                    'title' => 'Return Reminder',
                    'message' => $reminder->senderLabel() . ' reminded you to return ' . $equipment
                        . ' (due ' . LoanTime::dueBy($loan->due_date) . ').',
                    'url' => route('user.returns'),
                    'at' => Carbon::parse($reminder->sent_at),
                ];
            })
            ->values()
            ->all();
    }

    /** One notice per return the department confirmed or rejected. */
    private function returnNotifications(int $userId): array
    {
        $since = now()->subDays(self::STATUS_WINDOW_DAYS);

        $loans = BorrowTransaction::with('request.equipment')
            ->whereIn('return_status', [BorrowTransaction::RETURN_CONFIRMED, BorrowTransaction::RETURN_REJECTED])
            ->where('return_reviewed_at', '>=', $since)
            ->whereHas('request', fn ($query) => $query->where('user_id', $userId))
            ->get();

        $notifications = [];

        foreach ($loans as $loan) {
            $equipment = $loan->request?->equipment?->name ?? 'the equipment';
            $at = Carbon::parse($loan->return_reviewed_at);

            if ($loan->return_status === BorrowTransaction::RETURN_CONFIRMED) {
                $condition = $loan->inspected_condition ?: 'Good';

                $notifications[] = [
                    'key' => 'return-' . $loan->transaction_id . '-confirmed',
                    'type' => 'approved',
                    'title' => 'Return Confirmed',
                    'message' => 'The department confirmed the return of ' . $equipment
                        . ($condition !== 'Good' ? ' (recorded as ' . $condition . ').' : '.'),
                    'url' => route('user.history'),
                    'at' => $at,
                ];

                continue;
            }

            $reason = trim((string) $loan->return_reject_reason);

            $notifications[] = [
                // A return can be rejected again after a resubmission: one notice per decision.
                'key' => 'return-' . $loan->transaction_id . '-rejected-' . $at->getTimestamp(),
                'type' => 'rejected',
                'title' => 'Return Rejected',
                'message' => 'The department could not confirm the return of ' . $equipment . '.'
                    . ($reason !== '' ? ' Reason: ' . Str::limit($reason, 120) : '')
                    . ' The item is still on loan to you.',
                'url' => route('user.returns'),
                'at' => $at,
            ];
        }

        return $notifications;
    }

    /**
     * One notice when a penalty is recorded (listed for as long as it is
     * Pending), one when it is settled or waived, and one per proof the user
     * submitted: received while staff review it, rejected with the reason
     * (both for STATUS_WINDOW_DAYS once decided). An approved proof settles
     * the penalty, so the settled notice covers it.
     */
    private function penaltyNotifications(int $userId): array
    {
        $since = now()->subDays(self::STATUS_WINDOW_DAYS);

        $penalties = Penalty::with(['transaction.request.equipment', 'submissions'])
            ->where('user_id', $userId)
            ->where(fn ($query) => $query
                ->where('status', Penalty::STATUS_PENDING)
                ->orWhere('settled_at', '>=', $since))
            ->get();

        $notifications = [];

        foreach ($penalties as $penalty) {
            $equipment = $penalty->transaction?->request?->equipment?->name ?? 'the equipment';
            $amount = $penalty->amountLabel();
            $url = route('user.penalties', ['penalty' => $penalty->penalty_id]);

            if ($penalty->isPending() || $penalty->recorded_at?->gte($since)) {
                $notifications[] = [
                    'key' => 'penalty-' . $penalty->penalty_id . '-issued',
                    'type' => 'penalty',
                    'title' => 'Penalty Issued',
                    'message' => 'A ' . $penalty->type . ' penalty' . ($amount ? ' of ' . $amount : '')
                        . ' was recorded for ' . $equipment . ': ' . Str::limit($penalty->description, 120)
                        . ' Settle it, then submit your proof on My Penalties.',
                    'url' => $url,
                    'at' => $penalty->recorded_at ?? now(),
                ];
            }

            if (!$penalty->isPending() && $penalty->settled_at) {
                $waived = $penalty->status === Penalty::STATUS_WAIVED;
                $notifications[] = [
                    'key' => 'penalty-' . $penalty->penalty_id . '-' . strtolower($penalty->status),
                    'type' => 'approved',
                    'title' => $waived ? 'Penalty Waived' : 'Penalty Settled',
                    'message' => 'Your ' . $penalty->type . ' penalty for ' . $equipment
                        . ($waived ? ' was waived.' : ' was marked as settled. You can borrow equipment again.'),
                    'url' => $url,
                    'at' => $penalty->settled_at,
                ];
            }

            foreach ($penalty->submissions as $submission) {
                if ($submission->isUnderReview()) {
                    $notifications[] = [
                        'key' => 'proof-' . $submission->submission_id . '-received',
                        'type' => 'due',
                        'title' => 'Proof Received',
                        'message' => 'Your proof for the ' . $penalty->type . ' penalty on ' . $equipment
                            . ' was received. Staff will verify it soon.',
                        'url' => $url,
                        'at' => $submission->submitted_at ?? now(),
                    ];
                } elseif ($submission->status === PenaltySubmission::STATUS_REJECTED && $submission->reviewed_at?->gte($since)) {
                    $notifications[] = [
                        'key' => 'proof-' . $submission->submission_id . '-rejected',
                        'type' => 'rejected',
                        'title' => 'Proof Rejected',
                        'message' => 'Your proof for ' . $equipment . ' was not accepted. Reason: '
                            . Str::limit((string) $submission->reject_reason, 120) . ' Please submit new proof.',
                        'url' => $url,
                        'at' => $submission->reviewed_at,
                    ];
                }
            }
        }

        return $notifications;
    }

    private function latest($first, $second): ?Carbon
    {
        $first = $first ? Carbon::parse($first) : null;
        $second = $second ? Carbon::parse($second) : null;

        if ($first && $second) {
            return $first->gt($second) ? $first : $second;
        }

        return $first ?: $second;
    }
}
