<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\View\View;

class UserHistoryController extends Controller
{
    /**
     * Borrowing history for the signed-in user: every borrow request together
     * with its borrowing transaction (if one exists yet), so both current and
     * completed transactions are listed and stay in sync with the borrow /
     * return records.
     */
    public function index(): View
    {
        $userId = auth('user')->user()->user_id;

        $historyItems = BorrowRequest::with(['equipment.category', 'transaction.penalties', 'admin', 'departmentAccount'])
            ->where('user_id', $userId)
            ->orderByDesc('request_id')
            ->get()
            ->map(fn (BorrowRequest $request) => $this->toHistoryRow($request))
            ->values();

        return view('user.history', compact('historyItems'));
    }

    /**
     * Flattens a borrow request + its transaction into the row shape the
     * history table and details modal expect.
     */
    private function toHistoryRow(BorrowRequest $request): array
    {
        $equipment = $request->equipment;
        $transaction = $request->transaction;

        $fmt = fn ($value) => $value ? Carbon::parse($value)->format('M d, Y') : 'N/A';

        $borrowDateRaw = $transaction?->borrow_date ?: $request->borrow_date ?: $request->date_needed;
        $dueDateRaw = $transaction?->due_date ?: $request->due_date ?: $request->return_date;
        $returnDateRaw = $transaction?->status === 'Returned' ? $transaction->return_date : null;

        $status = $this->resolveStatus($request, $dueDateRaw, $returnDateRaw);

        $condition = trim((string) ($transaction?->condition_on_return ?? ''));
        $remarks = $this->resolveRemarks($request, $condition);

        return [
            'equipment' => $equipment?->name ?? 'Unknown equipment',
            'category' => $equipment?->category?->category_name ?: 'General',
            'img' => $this->resolveImage($equipment?->image),
            'borrow_date' => $fmt($borrowDateRaw),
            'return_date' => $returnDateRaw ? $fmt($returnDateRaw) : '—',
            'status' => $status,
            'status_class' => $this->statusClass($status),
            'badge_class' => $this->badgeClass($status),
            'condition' => $condition !== '' ? $condition : '—',
            'remarks' => $remarks,
            // Not Expensive requests are reviewed by the department alone.
            'handled_by' => $request->admin?->name ?: ($request->departmentAccount?->full_name ?: '—'),
            // "Damaged ₱500.00 (Pending); Late Return no amount (Settled)"
            'penalties' => $transaction && $transaction->penalties->isNotEmpty()
                ? $transaction->penalties
                    ->map(fn ($p) => $p->type . ' ' . ($p->amountLabel() ?? 'no amount') . ' (' . $p->status . ')')
                    ->implode('; ')
                : 'None',
            'timestamp' => $borrowDateRaw
                ? Carbon::parse($borrowDateRaw)->format('Y-m-d')
                : ($request->date_requested ? Carbon::parse($request->date_requested)->format('Y-m-d') : ''),
        ];
    }

    /**
     * Returned / Late Return for settled transactions, Pending Confirmation for
     * returns the department has not verified yet, Borrowed / Overdue for items
     * still out, and Pending / Rejected / Cancelled for requests not yet loaned.
     */
    private function resolveStatus(BorrowRequest $request, $dueDateRaw, $returnDateRaw): string
    {
        if ($returnDateRaw) {
            $wasLate = $dueDateRaw
                && Carbon::parse($returnDateRaw)->startOfDay()->gt(Carbon::parse($dueDateRaw)->startOfDay());

            return $wasLate ? 'Late Return' : 'Returned';
        }

        $isOut = ($request->transaction && $request->transaction->status !== 'Returned')
            || $request->overall_status === 'Approved';

        // Returned by the user, waiting for the department to verify it.
        if ($isOut && $request->transaction?->isReturnPending()) {
            return 'Pending Confirmation';
        }

        if ($isOut) {
            $isOverdue = $dueDateRaw
                && Carbon::parse($dueDateRaw)->startOfDay()->lt(BorrowTransaction::today());

            return $isOverdue ? 'Overdue' : 'Borrowed';
        }

        return match ($request->overall_status) {
            'Rejected' => 'Rejected',
            BorrowRequest::STATUS_CANCELLED => 'Cancelled',
            default => 'Pending',
        };
    }

    /**
     * Returned items store "Condition — remarks" on the transaction; other
     * rows fall back to the rejection reason or the borrower's notes.
     */
    private function resolveRemarks(BorrowRequest $request, string $condition): string
    {
        $remarks = '';

        if ($condition !== '' && str_contains($condition, '—')) {
            $remarks = trim(explode('—', $condition, 2)[1]);
        } elseif ($request->overall_status === 'Rejected' && !empty($request->reject_reason)) {
            $remarks = trim((string) $request->reject_reason);
        } else {
            $remarks = trim((string) ($request->notes ?? ''));
        }

        return $remarks !== '' ? $remarks : '—';
    }

    /** Table badge: "Late Return" -> status-late-return (styled in user/css/usertable.css). */
    private function statusClass(string $status): string
    {
        return 'status-' . Str::slug($status);
    }

    private function badgeClass(string $status): string
    {
        return match ($status) {
            'Returned' => 'approved',
            'Borrowed' => 'borrowed',
            'Pending', 'Pending Confirmation' => 'pending',
            'Cancelled' => 'cancelled',
            default => 'rejected',
        };
    }

    private function resolveImage(?string $raw): string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return asset('images/EquipTrack_logo.png');
        }

        if (preg_match('/^(https?:\/\/|data:|\/storage\/)/i', $raw)) {
            return $raw;
        }

        return asset(ltrim($raw, '/'));
    }
}
