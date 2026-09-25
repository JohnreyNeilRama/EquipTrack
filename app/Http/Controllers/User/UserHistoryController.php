<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\BorrowRequest;
use Illuminate\Support\Carbon;
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

        $historyItems = BorrowRequest::with(['equipment.category', 'transaction', 'admin'])
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
            'handled_by' => $request->admin?->name ?: '—',
            'timestamp' => $borrowDateRaw
                ? Carbon::parse($borrowDateRaw)->format('Y-m-d')
                : ($request->date_requested ? Carbon::parse($request->date_requested)->format('Y-m-d') : ''),
        ];
    }

    /**
     * Returned / Late Return for settled transactions, Borrowed / Overdue for
     * items still out, and Pending / Rejected for requests not yet loaned.
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

        if ($isOut) {
            $isOverdue = $dueDateRaw
                && Carbon::parse($dueDateRaw)->startOfDay()->lt(now()->startOfDay());

            return $isOverdue ? 'Overdue' : 'Borrowed';
        }

        return $request->overall_status === 'Rejected' ? 'Rejected' : 'Pending';
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

    private function statusClass(string $status): string
    {
        return match ($status) {
            'Returned' => 'status-returned',
            'Late Return', 'Overdue' => 'status-late',
            'Rejected' => 'status-rejected',
            'Pending' => 'status-pending',
            default => 'status-borrowed',
        };
    }

    private function badgeClass(string $status): string
    {
        return match ($status) {
            'Returned' => 'approved',
            'Borrowed' => 'borrowed',
            'Pending' => 'pending',
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
