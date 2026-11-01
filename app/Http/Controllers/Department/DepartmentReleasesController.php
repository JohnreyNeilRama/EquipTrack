<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Release Confirmation: Lab Personnel hand approved equipment to the borrower.
 *
 * Approval holds a unit and creates the loan as Ready for Pickup. When the
 * borrower comes to the lab, Lab Personnel check their school ID and Release
 * the item: the loan becomes Active (released_at / released_by), and only then
 * can it be returned, become overdue or get reminders. A borrower who never
 * comes is marked Not Claimed: the held unit goes back to stock, no penalty.
 *
 * Every action is limited to equipment owned by the signed-in department.
 */
class DepartmentReleasesController extends Controller
{
    /** Released / Not Claimed loans stay listed this long, below the ones to release. */
    private const HISTORY_DAYS = 30;

    public function index(): View
    {
        return view('department.releases', ['releases' => $this->fetchReleases()]);
    }

    /** JSON feed for live polling. */
    public function data(): JsonResponse
    {
        return response()->json(['success' => true, 'releases' => $this->fetchReleases()]);
    }

    /** Hands the item to the borrower after their school ID was checked. */
    public function release(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'integer', 'min:1'],
            'id_verified' => ['accepted'],
        ], [
            'id_verified.accepted' => "Check the borrower's school ID before releasing the equipment.",
        ]);

        return $this->act($request, (int) $data['transaction_id'], function (BorrowTransaction $transaction, BorrowRequest $borrow, $dept) {
            $transaction->update([
                'status' => BorrowTransaction::STATUS_ACTIVE,
                'released_at' => now(),
                'released_by' => $dept->dept_acc_id,
            ]);

            return [
                'action' => 'Released borrow request #' . $borrow->request_id . ' for ' . ($borrow->equipment?->name ?? 'equipment'),
                'details' => 'Handed to ' . ($borrow->user?->fullName() ?: ($borrow->user?->email ?? 'the borrower')) . ' (school ID checked).',
                'message' => 'Equipment released. The loan is now active until ' . ($transaction->due_date?->format('M d, Y') ?? 'its due date') . '.',
            ];
        });
    }

    /** The borrower never came: the held unit goes back to stock. */
    public function notClaimed(Request $request): JsonResponse
    {
        $data = $request->validate([
            'transaction_id' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $note = trim($data['note'] ?? '');

        return $this->act($request, (int) $data['transaction_id'], function (BorrowTransaction $transaction, BorrowRequest $borrow) use ($note) {
            $transaction->update([
                'status' => BorrowTransaction::STATUS_NOT_CLAIMED,
                'unclaimed_at' => now(),
            ]);

            // The unit was deducted on approval; it is free again.
            if ($transaction->return_restores_stock) {
                $transaction->restoreStock($borrow);
            }

            return [
                'action' => 'Marked borrow request #' . $borrow->request_id . ' as Not Claimed',
                'details' => $note !== '' ? 'Note: ' . $note : null,
                'message' => 'Marked as Not Claimed. The unit is back in stock.',
            ];
        });
    }

    /**
     * Shared checks for release / not claimed: the loan must belong to this
     * department's equipment and still be Ready for Pickup. The row is locked
     * so two staff members cannot act on the same loan twice.
     */
    private function act(Request $request, int $transactionId, callable $apply): JsonResponse
    {
        $dept = auth('dept')->user();

        $result = DB::transaction(function () use ($request, $transactionId, $apply, $dept) {
            $transaction = BorrowTransaction::where('transaction_id', $transactionId)
                ->whereHas('request.equipment', fn ($query) => $query->where('department_id', $dept->department_id))
                ->lockForUpdate()
                ->first();

            if (!$dept->department_id || !$transaction) {
                return ['code' => 404, 'message' => 'Loan not found for your department.'];
            }

            if (!$transaction->isReadyForPickup()) {
                return ['code' => 400, 'message' => $transaction->isNotClaimed()
                    ? 'This loan was already marked as Not Claimed.'
                    : 'This equipment has already been released.'];
            }

            $borrow = BorrowRequest::with(['equipment', 'user.student', 'user.facultyMember'])->find($transaction->request_id);
            $outcome = $apply($transaction, $borrow, $dept);

            AuditTrail::create([
                'dept_acc_id' => $dept->dept_acc_id,
                'action' => mb_substr($outcome['action'], 0, 100),
                'ip_address' => $request->ip() ?? '127.0.0.1',
                'affected_entity' => 'borrow_request:' . $borrow->request_id,
                'details' => $outcome['details'] !== null ? mb_substr($outcome['details'], 0, 255) : null,
            ]);

            return ['code' => 200, 'message' => $outcome['message']];
        });

        if ($result['code'] !== 200) {
            return response()->json(['success' => false, 'message' => $result['message']], $result['code']);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'releases' => $this->fetchReleases(),
        ]);
    }

    /**
     * This department's loans waiting for pickup (earliest pickup date first),
     * then the ones released or marked Not Claimed in the last HISTORY_DAYS.
     */
    private function fetchReleases(): array
    {
        $departmentId = auth('dept')->user()->department_id;

        if (!$departmentId) {
            return [];
        }

        $since = now()->subDays(self::HISTORY_DAYS);
        $today = BorrowTransaction::today();

        $transactions = BorrowTransaction::with([
            'request.user.student',
            'request.user.facultyMember',
            'request.equipment.category',
            'releaser',
        ])
            ->whereHas('request', fn ($query) => $query
                ->where('overall_status', 'Approved')
                ->whereHas('equipment', fn ($equipment) => $equipment->where('department_id', $departmentId)))
            ->where(fn ($query) => $query
                ->where('status', BorrowTransaction::STATUS_READY)
                ->orWhere(fn ($q) => $q->whereNotNull('released_by')->where('released_at', '>=', $since))
                ->orWhere(fn ($q) => $q->where('status', BorrowTransaction::STATUS_NOT_CLAIMED)->where('unclaimed_at', '>=', $since)))
            ->get()
            ->sortBy([
                fn ($a, $b) => (int) !$a->isReadyForPickup() <=> (int) !$b->isReadyForPickup(),
                // Waiting: earliest pickup first. Done: most recent first.
                fn ($a, $b) => $a->isReadyForPickup()
                    ? ($a->borrow_date?->getTimestamp() ?? 0) <=> ($b->borrow_date?->getTimestamp() ?? 0)
                    : ($this->doneAt($b)?->getTimestamp() ?? 0) <=> ($this->doneAt($a)?->getTimestamp() ?? 0),
                fn ($a, $b) => $a->transaction_id <=> $b->transaction_id,
            ]);

        $fmtDate = fn ($v) => $v ? Carbon::parse($v)->format('M d, Y') : '—';
        $fmtDateTime = fn ($v) => $v ? Carbon::parse($v)->timezone(config('app.display_timezone'))->format('M d, Y h:i A') : '—';

        $rows = [];

        foreach ($transactions as $transaction) {
            $borrow = $transaction->request;
            $user = $borrow?->user;
            $equipment = $borrow?->equipment;

            if (!$borrow || !$equipment) {
                continue;
            }

            $ready = $transaction->isReadyForPickup();
            $status = match (true) {
                $ready => BorrowTransaction::STATUS_READY,
                $transaction->isNotClaimed() => BorrowTransaction::STATUS_NOT_CLAIMED,
                default => 'Released',
            };

            $idNumber = $user?->role === 'Student'
                ? ($user->student?->id_number ?? '')
                : ($user?->facultyMember?->faculty_id_number ?? '');

            // Days from today to the pickup date (negative: the date has passed).
            $daysToPickup = $transaction->borrow_date
                ? (int) $today->diffInDays($transaction->borrow_date->copy()->startOfDay(), false)
                : null;

            $rows[] = [
                'id' => (int) $transaction->transaction_id,
                'requestId' => (int) $borrow->request_id,
                'user' => ($user?->fullName() ?: $user?->email) ?: 'Unknown User',
                'role' => ucfirst($user?->role ?? 'Student'),
                'idNumber' => $idNumber !== '' ? $idNumber : '—',
                'equipment' => $equipment->name,
                'category' => $equipment->category?->category_name ?: 'General',
                'serial' => $equipment->serial_number ?: ('EQ-' . $equipment->equipment_id),
                'quantity' => max(1, (int) ($borrow->quantity ?? 1)),
                'requiresBothApprovals' => $borrow->requiresDualApproval(),
                'purpose' => $borrow->purpose ?: '—',
                'pickupDate' => $fmtDate($transaction->borrow_date),
                'dueDate' => $fmtDate($transaction->due_date),
                'pickupNote' => $ready && $daysToPickup !== null ? match (true) {
                    $daysToPickup < 0 => 'Pickup date passed',
                    $daysToPickup === 0 => 'Pickup today',
                    $daysToPickup === 1 => 'Pickup tomorrow',
                    default => 'In ' . $daysToPickup . ' days',
                } : '',
                'pickupPassed' => $ready && $daysToPickup !== null && $daysToPickup < 0,
                'status' => $status,
                'releasedAt' => $fmtDateTime($transaction->released_at),
                'releasedBy' => $transaction->releaser?->full_name ?: '—',
                'unclaimedAt' => $fmtDateTime($transaction->unclaimed_at),
                'canAct' => $ready,
            ];
        }

        return $rows;
    }

    /** When a finished row was handled, for sorting. */
    private function doneAt(BorrowTransaction $transaction): ?Carbon
    {
        return $transaction->isNotClaimed() ? $transaction->unclaimed_at : $transaction->released_at;
    }
}
