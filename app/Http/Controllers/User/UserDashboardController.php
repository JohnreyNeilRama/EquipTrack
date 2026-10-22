<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Penalty;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Student / Faculty dashboard.
 *
 * Every Quick Stat, table and reminder is derived from the signed-in user's
 * own rows in borrow_request / borrow_transaction (joined to equipment). The
 * user id always comes from the authenticated session, never from the request,
 * so a user can never see another user's borrowing records. There is no
 * dashboard-only table and no hardcoded sample data: a user without records
 * simply gets zeros and empty lists.
 *
 * "Active loan" uses the same definition as the Return Item page: an approved
 * request that has not been settled with a Returned borrow_transaction (legacy
 * approved rows without a transaction still count as out). The active-loan set
 * is built once and reused by the stats, the table and the reminders, so those
 * figures can never disagree with each other or with the Return Item page.
 */
class UserDashboardController extends Controller
{
    private const RECENT_REQUESTS_LIMIT = 5;

    private const ACTIVE_LOANS_LIMIT = 5;

    private const REMINDERS_LIMIT = 5;

    /** Loans due within this many days (inclusive of today) raise a reminder; shared with the bell. */
    private const DUE_SOON_DAYS = BorrowTransaction::DUE_SOON_DAYS;

    public function index(): View
    {
        return view('user.dashboard', ['dashboard' => $this->buildDashboard()]);
    }

    /** JSON feed so the cards, tables and reminders stay current without a reload. */
    public function data(): JsonResponse
    {
        return response()->json(['success' => true] + $this->buildDashboard());
    }

    private function buildDashboard(): array
    {
        $userId = (int) auth('user')->user()->user_id;
        $today = now()->startOfDay();

        $loans = $this->activeLoans($userId);

        return [
            'stats' => $this->stats($userId, $loans, $today),
            'activeBorrows' => $this->activeBorrowRows($loans, $today),
            'recentRequests' => $this->recentRequests($userId),
            'reminders' => $this->reminders($userId, $loans, $today),
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * The user's approved requests that are still out (not Returned), with the
     * due date resolved the same way the history page does.
     */
    private function activeLoans(int $userId): Collection
    {
        return BorrowRequest::with(['equipment.category', 'transaction'])
            ->where('user_id', $userId)
            ->where('overall_status', 'Approved')
            ->get()
            ->reject(fn (BorrowRequest $request) => $request->transaction && $request->transaction->status === 'Returned')
            ->map(function (BorrowRequest $request) {
                $transaction = $request->transaction;

                return [
                    'request' => $request,
                    'quantity' => max(1, (int) $request->quantity),
                    'borrowDate' => $transaction?->borrow_date ?: $request->borrow_date ?: $request->date_needed,
                    'dueDate' => $transaction?->due_date ?: $request->due_date ?: $request->return_date,
                    'flaggedOverdue' => $transaction?->status === 'Overdue',
                ];
            })
            ->values();
    }

    private function stats(int $userId, Collection $loans, Carbon $today): array
    {
        $activeUnits = 0;
        $overdueUnits = 0;

        foreach ($loans as $loan) {
            $activeUnits += $loan['quantity'];

            if ($this->isOverdue($loan, $today)) {
                $overdueUnits += $loan['quantity'];
            }
        }

        $pending = BorrowRequest::where('user_id', $userId)
            ->where(function ($query) {
                $query->whereNull('overall_status')
                    ->orWhere('overall_status', 'Pending');
            })
            ->count();

        return [
            'activeBorrows' => $activeUnits,
            'pendingRequests' => $pending,
            'overdueItems' => $overdueUnits,
        ];
    }

    /** Items currently with the user, most urgent (earliest due date) first. */
    private function activeBorrowRows(Collection $loans, Carbon $today): array
    {
        return $loans
            ->sortBy(fn (array $loan) => $loan['dueDate']
                ? Carbon::parse($loan['dueDate'])->getTimestamp()
                : PHP_INT_MAX)
            ->take(self::ACTIVE_LOANS_LIMIT)
            ->map(fn (array $loan) => [
                'id' => (int) $loan['request']->request_id,
                'equipment' => $loan['request']->equipment?->name ?? 'Unknown equipment',
                ...$this->equipmentVisual($loan['request']),
                'borrowDate' => $this->fmt($loan['borrowDate']),
                'dueDate' => $this->fmt($loan['dueDate']),
                // Still out until the department confirms the submitted return.
                'status' => $loan['request']->transaction?->isReturnPending()
                    ? 'Pending Confirmation'
                    : ($this->isOverdue($loan, $today) ? 'Overdue' : 'Borrowed'),
            ])
            ->values()
            ->all();
    }

    /**
     * Photo and category shown in the equipment cell, as on the My Requests
     * table (the logo stands in when the equipment has no photo).
     */
    private function equipmentVisual(BorrowRequest $request): array
    {
        return [
            'image' => $request->equipment?->imageUrl() ?? asset('images/EquipTrack_logo.png'),
            'category' => $request->equipment?->category?->category_name ?: 'General',
        ];
    }

    /** The user's latest requests, whatever their status. */
    private function recentRequests(int $userId): array
    {
        return BorrowRequest::with('equipment.category')
            ->where('user_id', $userId)
            ->orderByDesc('request_id')
            ->limit(self::RECENT_REQUESTS_LIMIT)
            ->get()
            ->map(fn (BorrowRequest $request) => [
                'id' => (int) $request->request_id,
                'equipment' => $request->equipment?->name ?? 'Unknown equipment',
                ...$this->equipmentVisual($request),
                'date' => $this->fmt($request->date_requested),
                'status' => $request->overall_status ?: 'Pending',
            ])
            ->all();
    }

    /**
     * Pending penalties and overdue loans (danger) first, then loans due within
     * DUE_SOON_DAYS (warning), earliest first within each group.
     */
    private function reminders(int $userId, Collection $loans, Carbon $today): array
    {
        $rows = [];

        // Pending penalties come first: they block new borrow requests.
        $penalties = Penalty::with('transaction.request.equipment')
            ->where('user_id', $userId)
            ->where('status', Penalty::STATUS_PENDING)
            ->orderBy('recorded_at')
            ->get();

        foreach ($penalties as $penalty) {
            $equipment = $penalty->transaction?->request?->equipment?->name ?? 'Unknown equipment';

            $rows[] = [
                'sort' => [-1, $penalty->recorded_at?->getTimestamp() ?? 0],
                'type' => 'danger',
                'label' => 'Penalty: ' . $penalty->type,
                'equipment' => $equipment,
                'title' => 'Penalty: ' . $equipment,
                'message' => ($penalty->amountLabel() ? $penalty->amountLabel() . '. ' : '')
                    . $penalty->description . ' Settle it with the department or the admin office.',
            ];
        }

        foreach ($loans as $loan) {
            $equipment = $loan['request']->equipment?->name ?? 'Unknown equipment';
            $due = $loan['dueDate'] ? Carbon::parse($loan['dueDate'])->startOfDay() : null;

            if ($this->isOverdue($loan, $today)) {
                $daysLate = $due ? max(0, (int) $due->diffInDays($today)) : 0;

                $rows[] = [
                    'sort' => [0, $due ? $due->getTimestamp() : 0],
                    'type' => 'danger',
                    'label' => 'Overdue',
                    'equipment' => $equipment,
                    'title' => 'Overdue: ' . $equipment,
                    'message' => $due
                        ? 'Was due on ' . $due->format('M d, Y')
                            . ($daysLate > 0 ? ' (' . $daysLate . ' ' . ($daysLate === 1 ? 'day' : 'days') . ' late)' : '')
                            . '. Please return it as soon as possible.'
                        : 'This item is overdue. Please return it as soon as possible.',
                ];

                continue;
            }

            if (!$due) {
                continue;
            }

            $daysLeft = (int) $today->diffInDays($due, false);

            if ($daysLeft < 0 || $daysLeft > self::DUE_SOON_DAYS) {
                continue;
            }

            $when = match ($daysLeft) {
                0 => 'Due today',
                1 => 'Due tomorrow',
                default => "Due in {$daysLeft} days",
            };

            $rows[] = [
                'sort' => [1, $due->getTimestamp()],
                'type' => 'warning',
                'label' => $when,
                'equipment' => $equipment,
                'title' => $when . ': ' . $equipment,
                'message' => 'Return it by ' . $due->format('M d, Y') . '.',
            ];
        }

        usort($rows, fn (array $a, array $b) => $a['sort'] <=> $b['sort']);

        return array_map(
            fn (array $row) => [
                'type' => $row['type'],
                'label' => $row['label'],
                'equipment' => $row['equipment'],
                'title' => $row['title'],
                'message' => $row['message'],
            ],
            array_slice($rows, 0, self::REMINDERS_LIMIT)
        );
    }

    private function isOverdue(array $loan, Carbon $today): bool
    {
        if ($loan['flaggedOverdue']) {
            return true;
        }

        return $loan['dueDate'] && Carbon::parse($loan['dueDate'])->startOfDay()->lt($today);
    }

    private function fmt($value): string
    {
        return $value ? Carbon::parse($value)->format('M d, Y') : 'N/A';
    }
}
