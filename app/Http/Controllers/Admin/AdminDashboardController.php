<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\UserAccount;
use App\Services\RequestTableRows;
use App\Support\LoanTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Admin dashboard.
 *
 * Every number and table on the page is derived from the existing tables
 * (equipment, borrow_request, borrow_transaction, user_account,
 * department_account) - there is no dashboard-only table and nothing is
 * hardcoded. Submitting a request, approving/rejecting it, borrowing,
 * returning, or registering users/equipment is therefore reflected by this
 * controller as soon as the page refreshes its data.
 */
class AdminDashboardController extends Controller
{
    /** Equipment with this many units (or fewer) left counts as low stock. */
    private const LOW_STOCK_THRESHOLD = 1;

    private const RECENT_REQUESTS_LIMIT = 5;

    private const LOW_STOCK_LIMIT = 5;

    public function index(): View
    {
        return view('admin.dashboard', [
            'dashboard' => $this->buildDashboard(),
            'dbRequests' => $this->reviewableRequests(),
            'recentRequestsLimit' => self::RECENT_REQUESTS_LIMIT,
        ]);
    }

    /**
     * The requests the admin handles, in the shape the shared Requests table
     * renders (same source as the Admin Requests page), so the Recent Requests
     * table can show, approve and reject them the same way.
     */
    private function reviewableRequests(): array
    {
        return RequestTableRows::fetch(
            BorrowRequest::query()->visibleToAdmin(),
            fn (BorrowRequest $row) => $row->adminCanReview(),
        );
    }

    /** JSON feed used to keep the dashboard current without a manual reload. */
    public function data(): JsonResponse
    {
        return response()->json(['success' => true] + $this->buildDashboard());
    }

    private function buildDashboard(): array
    {
        // Loans still out on loan; reused by the stats and the overdue table so
        // both always agree.
        $loans = $this->activeLoans();

        return [
            'stats' => $this->stats($loans),
            'recentRequests' => $this->recentRequests(),
            'overdue' => $this->overdueLoans($loans),
            'lowStock' => $this->lowStock(),
            'summary' => $this->systemSummary(),
            'generatedAt' => now()->toIso8601String(),
        ];
    }

    /**
     * Quick Stats cards. Borrowed/overdue are counted in units (request
     * quantity) so the figures match the stock shown on the Equipment pages.
     */
    private function stats($loans): array
    {
        $today = BorrowTransaction::today();
        $borrowedUnits = 0;
        $overdueUnits = 0;

        foreach ($loans as $loan) {
            $quantity = max(1, (int) ($loan->request->quantity ?? 1));
            $borrowedUnits += $quantity;

            if ($this->isOverdue($loan, $today)) {
                $overdueUnits += $quantity;
            }
        }

        return [
            // Student/faculty accounts plus department accounts, matching the
            // rows listed on the User Management page.
            'totalUsers' => UserAccount::count() + DepartmentAccount::count(),
            // Retired equipment is out of the inventory and not counted.
            'totalEquipment' => Equipment::inService()->count(),
            'totalEquipmentUnits' => (int) Equipment::inService()->sum('total_qty'),
            'totalAvailableUnits' => (int) Equipment::inService()->sum('available_qty'),
            'pendingRequests' => BorrowRequest::where('overall_status', 'Pending')->count(),
            'borrowedEquipment' => $borrowedUnits,
            'overdueItems' => $overdueUnits,
        ];
    }

    /** Latest borrow requests with the real status and whether they can still be acted on. */
    private function recentRequests(): array
    {
        // Not Expensive requests are handled by the department alone.
        $rows = BorrowRequest::with(['user', 'equipment'])
            ->visibleToAdmin()
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
                    : 'N/A',
                'status' => ucfirst($status),
                'canAct' => $status === 'Pending'
                    && ($row->admin_status ?: 'Pending') === 'Pending'
                    && $row->adminCanReview(),
            ];
        })->all();
    }

    /**
     * Approved requests whose equipment is with the borrower: released and
     * not returned yet (Active / Overdue). Ready for Pickup is not on loan.
     */
    private function activeLoans()
    {
        return BorrowTransaction::with(['request.user', 'request.equipment'])
            ->onLoan()
            ->whereHas('request', fn ($query) => $query->where('overall_status', 'Approved'))
            ->orderBy('transaction_id')
            ->get();
    }
    /** Overdue alerts: one row per loan that is past its due date. */
    private function overdueLoans($loans): array
    {
        $today = BorrowTransaction::today();
        $rows = [];

        foreach ($loans as $loan) {
            if (!$this->isOverdue($loan, $today)) {
                continue;
            }

            $request = $loan->request;
            $user = $request?->user;

            $rows[] = [
                'id' => (int) $loan->transaction_id,
                'user' => $user?->fullName() ?: ($user?->email ?? 'Unknown User'),
                'equipment' => $request?->equipment?->name ?? 'Unknown Equipment',
                'dueDate' => LoanTime::format($loan->due_date),
                'daysLate' => LoanTime::daysLate($loan->due_date),
            ];
        }

        return $rows;
    }


    /** Equipment that is out of stock or almost out. */
    private function lowStock(): array
    {
        return Equipment::with('category')
            ->inService()
            ->where('available_qty', '<=', self::LOW_STOCK_THRESHOLD)
            ->orderBy('available_qty')
            ->orderBy('name')
            ->limit(self::LOW_STOCK_LIMIT)
            ->get()
            ->map(function (Equipment $equipment) {
                $available = (int) $equipment->available_qty;

                return [
                    'id' => (int) $equipment->equipment_id,
                    'name' => $equipment->name,
                    'category' => $equipment->category?->category_name ?: 'General',
                    'available' => $available,
                    'total' => (int) $equipment->total_qty,
                    'critical' => $available === 0,
                    'label' => $available === 0 ? 'Out of Stock' : 'Low Stock',
                ];
            })
            ->all();
    }

    /** System summary: all borrowing requests (all time) grouped by outcome. */
    private function systemSummary(): array
    {
        $counts = BorrowRequest::query()
            ->selectRaw('overall_status, COUNT(*) as total')
            ->groupBy('overall_status')
            ->pluck('total', 'overall_status');

        $approved = (int) ($counts['Approved'] ?? 0);
        $rejected = (int) ($counts['Rejected'] ?? 0);
        $pending = (int) ($counts['Pending'] ?? 0);

        return [
            'scope' => 'All time',
            'approved' => $approved,
            'rejected' => $rejected,
            'pending' => $pending,
            'total' => $approved + $rejected + $pending,
        ];
    }

    private function isOverdue(BorrowTransaction $loan, Carbon $today): bool
    {
        // Overdue once its exact due date and time have passed.
        if (LoanTime::isOverdue($loan->due_date)) {
            return true;
        }

        return $loan->status === 'Overdue';
    }

}
