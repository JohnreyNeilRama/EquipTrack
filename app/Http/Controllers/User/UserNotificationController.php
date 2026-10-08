<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
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
 * (request-{id}-approved, request-{id}-rejected, overdue-{transaction_id}).
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

    /** One notice per loan that is past its due date and not yet returned. */
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
            $due = $loan->due_date ? Carbon::parse($loan->due_date)->startOfDay() : null;
            $pastDue = $due && $due->lt($today);

            if (!$pastDue && $loan->status !== 'Overdue') {
                continue;
            }

            $equipment = $loan->request?->equipment?->name ?? 'The borrowed equipment';
            $daysLate = $due ? (int) abs($due->diffInDays($today)) : 0;

            $message = $due
                ? $equipment . ' was due on ' . $due->format('M d, Y')
                    . ($daysLate > 0 ? ' (' . $daysLate . ' ' . Str::plural('day', $daysLate) . ' late)' : '')
                    . '. Please return it as soon as possible.'
                : $equipment . ' is overdue. Please return it as soon as possible.';

            // Overdue since the day after the due date, never in the future.
            $at = $due ? $due->copy()->addDay() : now();
            if ($at->gt(now())) {
                $at = now();
            }

            $notifications[] = [
                'key' => 'overdue-' . $loan->transaction_id,
                'type' => 'overdue',
                'title' => 'Overdue Equipment',
                'message' => $message,
                'url' => route('user.returns'),
                'at' => $at,
            ];
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
