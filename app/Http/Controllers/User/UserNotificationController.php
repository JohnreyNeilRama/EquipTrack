<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Penalty;
use App\Models\UserNotificationRead;
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
 * (request-{id}-approved, request-{id}-rejected, due-{transaction_id}-{day},
 * overdue-{transaction_id}-{day},
 * return-{transaction_id}-confirmed, return-{transaction_id}-rejected-{time}).
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
            ->merge($this->overdueNotifications($userId))
            ->merge($this->returnNotifications($userId))
            ->merge($this->penaltyNotifications($userId))
            ->sortByDesc(fn (array $n) => $n['at']->getTimestamp())
            ->take(self::LIMIT)
            ->values()
            ->all();
    }

    /** One notice per request that the admin or department approved / rejected. */
    private function statusNotifications(int $userId): array
    {
        $since = now()->subDays(self::STATUS_WINDOW_DAYS);
        $rows = BorrowRequest::with('equipment')
            ->where('user_id', $userId)
            ->whereIn('overall_status', ['Approved', 'Rejected'])
            ->get();

        $notifications = [];

        foreach ($rows as $row) {
            // overall_status follows whichever approver acted last.
            $at = $this->latest($row->admin_reviewed_at, $row->dept_reviewed_at);

            if (!$at || $at->lt($since)) {
                continue;
            }

            $equipment = $row->equipment?->name ?? 'the equipment';

            if ($row->overall_status === 'Approved') {
                $notifications[] = [
                    'key' => 'request-' . $row->request_id . '-approved',
                    'type' => 'approved',
                    'title' => 'Request Approved',
                    'message' => 'Your request to borrow ' . $equipment . ' has been approved.',
                    'url' => route('user.returns'),
                    'at' => $at,
                ];

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

    /**
     * Overdue Reminder for every loan still with the user: a "due soon" notice
     * from DUE_SOON_DAYS before the due date, then an overdue notice once the
     * due date has passed. Reminders repeat: the key carries the day, so each
     * day brings one new unread notice per loan until the item is returned.
     * Loans whose return the department is verifying are skipped.
     */
    private function overdueNotifications(int $userId): array
    {
        $today = now()->startOfDay();

        $loans = BorrowTransaction::with('request.equipment')
            ->where('status', '!=', 'Returned')
            ->whereHas('request', fn ($query) => $query
                ->where('user_id', $userId)
                ->where('overall_status', 'Approved'))
            ->get();

        $notifications = [];

        foreach ($loans as $loan) {
            // The user already handed it back; the department is verifying it.
            if ($loan->isReturnPending()) {
                continue;
            }

            $due = $loan->due_date ? Carbon::parse($loan->due_date)->startOfDay() : null;
            $pastDue = $due && $due->lt($today);

            if (!$pastDue && $loan->status !== 'Overdue') {
                if ($notice = $this->dueSoonNotification($loan, $due, $today)) {
                    $notifications[] = $notice;
                }

                continue;
            }

            $equipment = $loan->request?->equipment?->name ?? 'The borrowed equipment';
            $daysLate = $due ? (int) abs($due->diffInDays($today)) : 0;

            $message = $due
                ? $equipment . ' was due on ' . $due->format('M d, Y')
                    . ($daysLate > 0 ? ' (' . $daysLate . ' ' . Str::plural('day', $daysLate) . ' late)' : '')
                    . '. Please return it as soon as possible.'
                : $equipment . ' is overdue. Please return it as soon as possible.';

            $notifications[] = [
                'key' => 'overdue-' . $loan->transaction_id . '-' . $today->toDateString(),
                'type' => 'overdue',
                'title' => 'Overdue Equipment',
                'message' => $message,
                'url' => route('user.returns'),
                // Today's reminder.
                'at' => $today->copy(),
            ];
        }

        return $notifications;
    }

    /** "Due today / tomorrow / in N days" while the due date is DUE_SOON_DAYS away or less. */
    private function dueSoonNotification(BorrowTransaction $loan, ?Carbon $due, Carbon $today): ?array
    {
        if (!$due) {
            return null;
        }

        $daysLeft = (int) $today->diffInDays($due, false);

        if ($daysLeft < 0 || $daysLeft > BorrowTransaction::DUE_SOON_DAYS) {
            return null;
        }

        $when = match ($daysLeft) {
            0 => 'today',
            1 => 'tomorrow',
            default => 'in ' . $daysLeft . ' days',
        };

        $equipment = $loan->request?->equipment?->name ?? 'The borrowed equipment';

        return [
            'key' => 'due-' . $loan->transaction_id . '-' . $today->toDateString(),
            'type' => 'due',
            'title' => 'Due ' . match ($daysLeft) {
                0 => 'Today',
                1 => 'Tomorrow',
                default => 'in ' . $daysLeft . ' Days',
            },
            'message' => $equipment . ' is due ' . $when . ' (' . $due->format('M d, Y') . '). Please return it on time.',
            'url' => route('user.returns'),
            'at' => $today->copy(),
        ];
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
     * Pending) and one when it is settled or waived (for STATUS_WINDOW_DAYS).
     */
    private function penaltyNotifications(int $userId): array
    {
        $since = now()->subDays(self::STATUS_WINDOW_DAYS);

        $penalties = Penalty::with('transaction.request.equipment')
            ->where('user_id', $userId)
            ->where(fn ($query) => $query
                ->where('status', Penalty::STATUS_PENDING)
                ->orWhere('settled_at', '>=', $since))
            ->get();

        $notifications = [];

        foreach ($penalties as $penalty) {
            $equipment = $penalty->transaction?->request?->equipment?->name ?? 'the equipment';
            $amount = $penalty->amountLabel();

            if ($penalty->isPending() || $penalty->recorded_at?->gte($since)) {
                $notifications[] = [
                    'key' => 'penalty-' . $penalty->penalty_id . '-issued',
                    'type' => 'penalty',
                    'title' => 'Penalty Issued',
                    'message' => 'A ' . $penalty->type . ' penalty' . ($amount ? ' of ' . $amount : '')
                        . ' was recorded for ' . $equipment . ': ' . Str::limit($penalty->description, 120)
                        . ' Settle it with the department or the admin office.',
                    'url' => route('user.history'),
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
                        . ($waived ? ' was waived.' : ' was marked as settled.'),
                    'url' => route('user.history'),
                    'at' => $penalty->settled_at,
                ];
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
