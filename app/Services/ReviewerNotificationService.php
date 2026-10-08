<?php

namespace App\Services;

use App\Models\BorrowRequest;
use App\Models\StaffNotificationRead;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Reservation Status Alerts for the reviewers: Admin and Lab Personnel
 * (department accounts).
 *
 * Notifications are derived live from borrow_request, so they always match the
 * real review decisions and can never be duplicated. Each decision (Approve or
 * Reject) by a reviewer is one notification with a deterministic key:
 *
 *   review-{request_id}-{admin|dept}-{approved|rejected}
 *
 * Scope:
 *  - Admin sees the decisions on every request.
 *  - Lab Personnel sees the decisions on requests for equipment owned by their
 *    own department.
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

        return collect($notifications)
            ->sortByDesc(fn (array $n) => $n['at']->getTimestamp())
            ->take(self::LIMIT)
            ->values()
            ->all();
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
