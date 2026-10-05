<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\UserAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Department dashboard.
 *
 * Every Quick Stat card and every table is derived from the existing tables
 * (equipment, borrow_request, borrow_transaction, user_account), restricted to
 * the equipment owned by the signed-in department account
 * (equipment.department_id). A department therefore never sees another
 * department's inventory, requests, loans or overdue records, and there is no
 * dashboard-only table and no hardcoded sample data.
 *
 * The active-loan set is fetched once and reused by the stats, the overdue
 * alerts and the upcoming returns, so those figures can never disagree.
 */
class DepartmentDashboardController extends Controller
{
    private const RECENT_REQUESTS_LIMIT = 5;

    private const UPCOMING_LIMIT = 5;

    public function index(): View
    {
        return view('department.dashboard', ['dashboard' => $this->buildDashboard()]);
    }

    /** JSON feed so the cards and tables stay current without a reload. */
    public function data(): JsonResponse
    {
        return response()->json(['success' => true] + $this->buildDashboard());
    }

    private function buildDashboard(): array
    {
        $departmentId = $this->departmentId();

        if (!$departmentId) {
            // Account without a department: valid, simply empty.
            return [
                'stats' => [
                    'totalEquipment' => 0,
                    'totalEquipmentUnits' => 0,
                    'availableUnits' => 0,
                    'pendingRequests' => 0,
                    'borrowedEquipment' => 0,
                    'overdueItems' => 0,
                    'departmentUsers' => 0,
                ],
                'recentRequests' => [],
                'overdue' => [],
                'upcoming' => [],
                'generatedAt' => now()->toIso8601String(),
            ];
        }

        $loans = $this->activeLoans($departmentId);

        return [
            'stats' => $this->stats($departmentId, $loans),
            'recentRequests' => $this->recentRequests($departmentId),
            'overdue' => $this->overdueLoans($loans),
            'upcoming' => $this->upcomingReturns($loans),
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /** Department the signed-in department account belongs to. */
    private function departmentId(): ?int
    {
        $departmentId = auth('dept')->user()?->department_id;

        return $departmentId ? (int) $departmentId : null;
    }

    /**
     * Quick Stats cards scoped to this department.
     */
    private function stats(int $departmentId, $loans): array
    {
        $today = now()->startOfDay();
        $borrowedUnits = 0;
        $overdueUnits = 0;

        foreach ($loans as $loan) {
            $quantity = max(1, (int) ($loan->request->quantity ?? 1));
            $borrowedUnits += $quantity;

            if ($this->isOverdue($loan, $today)) {
                $overdueUnits += $quantity;
            }
        }

        $equipmentQuery = Equipment::where('department_id', $departmentId);

        return [
            'totalEquipment' => (clone $equipmentQuery)->count(),
            'totalEquipmentUnits' => (int) (clone $equipmentQuery)->sum('total_qty'),
            'availableUnits' => (int) (clone $equipmentQuery)->sum('available_qty'),
            'pendingRequests' => BorrowRequest::whereHas('equipment', fn ($query) => $query->where('department_id', $departmentId))
                ->where('dept_status', 'Pending')
                ->where(function ($query) {
                    $query->whereNull('overall_status')
                        ->orWhere('overall_status', '!=', 'Rejected');
                })
                ->count(),
            'borrowedEquipment' => $borrowedUnits,
            'overdueItems' => $overdueUnits,
            'departmentUsers' => UserAccount::where('department_id', $departmentId)->count(),
        ];
    }

    /**
     * Recent borrow requests for equipment owned by this department.
     */
    private function recentRequests(int $departmentId): array
    {
        $rows = BorrowRequest::with(['user.student', 'user.facultyMember', 'equipment'])
            ->whereHas('equipment', fn ($query) => $query->where('department_id', $departmentId))
            ->orderByDesc('request_id')
            ->limit(self::RECENT_REQUESTS_LIMIT)
            ->get();

        return $rows->map(function (BorrowRequest $row) {
            $user = $row->user;
            $status = $row->overall_status ?: 'Pending';

            return [
                'id' => (int) $row->request_id,
                'user' => $user?->fullName() ?: ($user?->email ?? 'Unknown User'),
                'equipment' => $row->equipment?->name ?? 'Unknown Equipment',
                'date' => $row->date_requested
                    ? Carbon::parse($row->date_requested)->format('M d, Y')
                    : ($row->created_at ? Carbon::parse($row->created_at)->format('M d, Y') : 'N/A'),
                'status' => ucfirst($status),
            ];
        })->all();
    }

    /**
     * Active loans (not returned, request approved) for equipment owned by this department.
     */
    private function activeLoans(int $departmentId)
    {
        return BorrowTransaction::with(['request.user.student', 'request.user.facultyMember', 'request.equipment'])
            ->where('status', '!=', 'Returned')
            ->whereHas('request', fn ($query) => $query->where('overall_status', 'Approved'))
            ->whereHas('request.equipment', fn ($query) => $query->where('department_id', $departmentId))
            ->orderBy('transaction_id')
            ->get();
    }

    /** Overdue alerts: one row per loan that is past its due date. */
    private function overdueLoans($loans): array
    {
        $today = now()->startOfDay();
        $rows = [];

        foreach ($loans as $loan) {
            if (!$this->isOverdue($loan, $today)) {
                continue;
            }

            $request = $loan->request;
            $user = $request?->user;
            $dueDate = $loan->due_date ? Carbon::parse($loan->due_date)->startOfDay() : null;
            $daysLate = $dueDate ? max(0, (int) $dueDate->diffInDays($today)) : 0;

            $rows[] = [
                'id' => (int) $loan->transaction_id,
                'user' => $user?->fullName() ?: ($user?->email ?? 'Unknown User'),
                'equipment' => $request?->equipment?->name ?? 'Unknown Equipment',
                'dueDate' => $dueDate ? $dueDate->format('M d, Y') : '—',
                'daysLate' => $daysLate,
                'daysLateText' => $daysLate === 1 ? '1 Day' : "{$daysLate} Days",
            ];
        }

        return $rows;
    }

    /** Upcoming returns: non-overdue active loans scheduled to be returned. */
    private function upcomingReturns($loans): array
    {
        $today = now()->startOfDay();
        $rows = [];

        foreach ($loans as $loan) {
            if ($this->isOverdue($loan, $today)) {
                continue;
            }

            $dueDate = $loan->due_date ? Carbon::parse($loan->due_date)->startOfDay() : null;
            if (!$dueDate) {
                continue;
            }

            $request = $loan->request;
            $user = $request?->user;
            $daysRemaining = (int) $today->diffInDays($dueDate, false);

            if ($daysRemaining === 0) {
                $statusType = 'today';
                $daysRemainingText = 'Due Today';
            } elseif ($daysRemaining === 1) {
                $statusType = 'tomorrow';
                $daysRemainingText = 'Due Tomorrow';
            } else {
                $statusType = 'upcoming';
                $daysRemainingText = "Due in {$daysRemaining} Days";
            }

            $rows[] = [
                'id' => (int) $loan->transaction_id,
                'user' => $user?->fullName() ?: ($user?->email ?? 'Unknown User'),
                'equipment' => $request?->equipment?->name ?? 'Unknown Equipment',
                'dueDate' => $dueDate->format('M d, Y'),
                'daysRemaining' => $daysRemaining,
                'daysRemainingText' => $daysRemainingText,
                'statusType' => $statusType,
            ];
        }

        // Sort upcoming returns by earliest due date first
        usort($rows, fn ($a, $b) => $a['daysRemaining'] <=> $b['daysRemaining']);

        return array_slice($rows, 0, self::UPCOMING_LIMIT);
    }

    private function isOverdue(BorrowTransaction $loan, Carbon $today): bool
    {
        if ($loan->status === 'Overdue') {
            return true;
        }

        if ($loan->due_date && Carbon::parse($loan->due_date)->startOfDay()->lt($today)) {
            return true;
        }

        return false;
    }

}

