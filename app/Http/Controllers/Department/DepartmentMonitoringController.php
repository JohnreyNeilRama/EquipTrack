<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\BorrowTransaction;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Services\ManualLoanReminder;
use App\Support\LoanReminders;
use App\Support\LoanTime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Equipment monitoring for the signed-in department.
 *
 * Uses the same row logic as the admin monitoring page
 * (AdminMonitoringController::buildMonitoringRows), scoped to the equipment
 * this department owns — the same scope as the department Requests page:
 *
 * - One row per active loan (Borrowed / Overdue), so every borrower holding a
 *   unit of the department's equipment is listed, whichever department the
 *   borrower belongs to.
 * - Equipment with no active loan keeps a single row with its own status, so
 *   a user return is reflected here as soon as the transaction is Returned.
 */
class DepartmentMonitoringController extends Controller
{
    /** Rows per page. */
    public const PER_PAGE = 25;

    /** Loan rows still out: what the page shows first ("out", the default status filter). */
    private const OUT = ['borrowed', 'overdue', 'reserved'];

    public function index(Request $request): View
    {
        $dbCategories = EquipmentCategory::orderBy('category_name')->pluck('category_name')->all();
        $monitoringPage = $this->monitoringPage($request);
        $dbMonitoring = $monitoringPage['rows'];

        return view('department.monitoring', compact('dbCategories', 'dbMonitoring', 'monitoringPage'));
    }

    /**
     * JSON feed: one page of monitoring rows (query parameters page, search,
     * role, status, category). Status starts on "out": items reserved, on loan
     * or overdue.
     */
    public function data(Request $request): JsonResponse
    {
        return response()->json(['success' => true] + $this->monitoringPage($request));
    }

    /**
     * The rows only ever cover the department's equipment and its open loans
     * (not past ones), so they are built in full, then searched, filtered,
     * counted and cut into pages here.
     *
     * @return array{rows: array, pagination: array{page:int, perPage:int, total:int, lastPage:int}, counts: array<string,int>}
     */
    private function monitoringPage(Request $request): array
    {
        $rows = $this->buildMonitoringRows();
        $status = fn (array $row) => strtolower((string) $row['status']);

        $counts = [
            'total' => count($rows),
            'available' => count(array_filter($rows, fn ($r) => $status($r) === 'available')),
            // Out on loan, including overdue ones
            'borrowed' => count(array_filter($rows, fn ($r) => in_array($status($r), ['borrowed', 'overdue'], true))),
            'reserved' => count(array_filter($rows, fn ($r) => in_array($status($r), ['reserved', 'on hold'], true))),
        ];

        $search = mb_strtolower(trim(mb_substr((string) $request->query('search', ''), 0, 100)));
        $role = mb_strtolower((string) $request->query('role', 'all'));
        $wanted = mb_strtolower((string) $request->query('status', 'out'));
        $category = mb_strtolower(trim((string) $request->query('category', 'all')));

        $matches = array_values(array_filter($rows, function (array $row) use ($status, $search, $role, $wanted, $category) {
            $s = $status($row);
            $haystack = mb_strtolower("{$row['equipment']} {$row['borrower']} {$row['id_number']} {$row['role']} {$row['status']}");

            return ($search === '' || str_contains($haystack, $search))
                && ($role === 'all' || str_contains(mb_strtolower((string) $row['role']), $role))
                && ($category === 'all' || $category === '' || str_contains(mb_strtolower((string) $row['category']), $category))
                && match ($wanted) {
                    'all' => true,
                    'out' => in_array($s, self::OUT, true),
                    'maintenance' => $s === 'under maintenance' || $s === 'maintenance',
                    'reserved' => $s === 'reserved' || $s === 'on hold',
                    default => $s === $wanted,
                };
        }));

        // Overdue loans first (most overdue at the top); the rest keep their order.
        usort($matches, fn (array $a, array $b) => ((int) $b['daysOverdue']) <=> ((int) $a['daysOverdue']));

        $total = count($matches);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) $request->query('page', 1)), $lastPage);

        return [
            'rows' => array_slice($matches, ($page - 1) * self::PER_PAGE, self::PER_PAGE),
            'pagination' => ['page' => $page, 'perPage' => self::PER_PAGE, 'total' => $total, 'lastPage' => $lastPage],
            'counts' => $counts,
        ];
    }

    /**
     * The "Remind" button: sends the borrower a return reminder by hand (bell +
     * email), at most once per loan per day. See App\Services\ManualLoanReminder.
     */
    public function remind(Request $request, ManualLoanReminder $reminders): JsonResponse
    {
        $data = $request->validate(['transaction_id' => ['required', 'integer']]);
        $dept = auth('dept')->user();

        // Only loans of this department's own equipment.
        $loan = BorrowTransaction::whereKey($data['transaction_id'])
            ->whereHas('request.equipment', fn ($e) => $e->where('department_id', $dept->department_id))
            ->first();

        if (!$loan || !$dept->department_id) {
            return response()->json(['success' => false, 'message' => 'Loan not found.'], 404);
        }

        $result = $reminders->send($loan, $dept);

        return response()->json(['success' => $result['ok'], 'message' => $result['message']], $result['status']);
    }

    private function buildMonitoringRows(): array
    {
        $departmentId = auth('dept')->user()->department_id;

        if (!$departmentId) {
            return [];
        }

        $equipmentList = Equipment::with('category')
            ->where('department_id', $departmentId)
            ->inService()
            ->orderBy('equipment_id')
            ->get();

        if ($equipmentList->isEmpty()) {
            return [];
        }

        // Loans still tying up a unit of this department's equipment, keyed by
        // equipment: out on loan (Active / Overdue) or held for pickup (Ready
        // for Pickup, shown as Reserved).
        $activeTransactions = BorrowTransaction::with([
            'request.user.student',
            'request.user.facultyMember',
        ])
            ->whereIn('status', BorrowTransaction::OPEN)
            ->whereHas('request', function ($query) use ($departmentId) {
                $query->where('overall_status', 'Approved')
                    ->whereHas('equipment', fn ($equipment) => $equipment->where('department_id', $departmentId));
            })
            ->orderBy('transaction_id')
            ->get()
            ->groupBy(fn ($transaction) => $transaction->request?->equipment_id);

        $fmt = fn ($value) => LoanTime::format($value);

        // Loans someone already reminded by hand today (the Remind button is spent for today).
        $remindedToday = array_flip(ManualLoanReminder::remindedTodayIds(
            $activeTransactions->flatten()->pluck('transaction_id')->map(fn ($id) => (int) $id)->all()
        ));

        $rows = [];

        foreach ($equipmentList as $equipment) {
            $category = $equipment->category?->category_name ?: 'General';

            // Equipment picture from the equipment record (same resolution as the
            // admin monitoring page); falls back to the EquipTrack logo when empty.
            $rawImg = trim((string) ($equipment->image ?? ''));
            if ($rawImg === '') {
                $img = asset('images/EquipTrack_logo.png');
            } elseif (preg_match('/^(https?:\/\/|data:|\/storage\/)/i', $rawImg)) {
                $img = $rawImg;
            } else {
                $img = asset(ltrim($rawImg, '/'));
            }

            $loanRows = [];

            foreach ($activeTransactions->get($equipment->equipment_id, collect()) as $transaction) {
                $request = $transaction->request;
                $user = $request?->user;

                if (!$request || !$user) {
                    continue;
                }

                $idNumber = $user->role === 'Student'
                    ? ($user->student?->id_number ?? '')
                    : ($user->facultyMember?->faculty_id_number ?? '');

                $reserved = $transaction->isReadyForPickup();
                $isOverdue = !$reserved && (LoanTime::isOverdue($transaction->due_date)
                    || $transaction->status === 'Overdue');

                // Days past the exact due time (overdue, any part of a day
                // counting) or calendar days left until it (shown as "Due
                // today" / "Due tomorrow" in the table). Display only.
                $daysFromDue = $transaction->due_date && !$reserved
                    ? ($isOverdue
                        ? -max(1, LoanTime::daysLate($transaction->due_date))
                        : (int) BorrowTransaction::today()->diffInDays($transaction->due_date->copy()->startOfDay(), false))
                    : null;

                $loanRows[] = [
                    'equipment' => $equipment->name,
                    'category' => $category,
                    'serial' => $equipment->serial_number ?: '',
                    // Units this borrower has (or has reserved) on this loan.
                    'quantity' => max(1, (int) ($request->quantity ?? 1)),
                    'borrower' => $user->fullName() ?: $user->email,
                    'id_number' => $idNumber,
                    'role' => ucfirst($user->role),
                    // Their own profile picture (null: the View window draws initials).
                    'avatar' => $this->profileImageUrl($user->profile_image),
                    'status' => $reserved ? 'Reserved' : ($isOverdue ? 'Overdue' : 'Borrowed'),
                    'borrowDate' => $fmt($transaction->borrow_date),
                    'returnDate' => $fmt($transaction->due_date),
                    'daysOverdue' => $daysFromDue !== null && $daysFromDue < 0 ? -$daysFromDue : 0,
                    'daysUntilDue' => $daysFromDue !== null && $daysFromDue >= 0 ? $daysFromDue : null,
                    'img' => $img,
                    // The Remind button: items out on loan (not reserved, return not submitted).
                    'transactionId' => (int) $transaction->transaction_id,
                    'canRemind' => !$reserved && LoanReminders::isOut($transaction),
                    'remindedToday' => isset($remindedToday[(int) $transaction->transaction_id]),
                ];
            }

            if ($loanRows) {
                array_push($rows, ...$loanRows);

                continue;
            }

            $rows[] = [
                'equipment' => $equipment->name,
                'category' => $category,
                'serial' => $equipment->serial_number ?: '',
                'borrower' => '—',
                'id_number' => '—',
                'role' => '—',
                'avatar' => null,
                'status' => $equipment->status,
                'borrowDate' => '—',
                'returnDate' => '—',
                'daysOverdue' => 0,
                'daysUntilDue' => null,
                'img' => $img,
            ];
        }

        // Page-level row id (several loan rows can belong to the same equipment).
        foreach ($rows as $i => &$row) {
            $row['id'] = 'MON-' . ($i + 1);
        }
        unset($row);

        return $rows;
    }

    /** Stored profile picture path or URL → a URL the page can load (null when there is none). */
    private function profileImageUrl(?string $value): ?string
    {
        $raw = trim((string) $value);

        if ($raw === '') {
            return null;
        }

        return preg_match('/^(https?:\/\/|data:|\/storage\/)/i', $raw) ? $raw : asset(ltrim($raw, '/'));
    }
}
