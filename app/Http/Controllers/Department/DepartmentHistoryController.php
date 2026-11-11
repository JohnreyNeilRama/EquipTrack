<?php

namespace App\Http\Controllers\Department;

use App\Http\Controllers\Controller;
use App\Models\BorrowTransaction;
use App\Models\EquipmentCategory;
use App\Support\LoanTime;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Borrowing history for the signed-in department.
 *
 * A history record is a borrow_transaction that has been settled (status
 * Returned) for equipment this department owns — the same ownership scope the
 * department Requests and Monitoring pages use, so a unit borrowed by a
 * student or faculty member of any department shows up here once returned.
 *
 * Returns are recorded by UserReturnsController::store(), which sets the
 * transaction's status / return_date / condition_on_return; reading the same
 * rows here keeps this page in sync with the borrow and return workflow.
 *
 * The page shows PER_PAGE records at a time. Search, role and category are
 * filtered in the database. The return status (Returned, Returned Late,
 * Damaged, Incomplete, Lost) depends on office days (LoanTime), so it is
 * worked out in PHP from a few light columns of every record, which also
 * gives the summary cards; full details are loaded only for the page shown.
 */
class DepartmentHistoryController extends Controller
{
    public const PER_PAGE = 25;

    private const STATUS_FILTERS = ['all', 'returned', 'returned late', 'damaged', 'incomplete', 'lost'];

    public function index(Request $request): View
    {
        $page = $this->historyPage($request);

        return view('department.history', [
            'dbCategories' => EquipmentCategory::orderBy('category_name')->pluck('category_name')->all(),
            'dbHistory' => $page['history'],
            'historyPage' => $page,
        ]);
    }

    /**
     * JSON feed polled by the history page so new returns appear without a
     * reload: one page (query parameters page, search, role, status, category).
     */
    public function data(Request $request): JsonResponse
    {
        return response()->json(['success' => true] + $this->historyPage($request));
    }

    /**
     * @return array{history: array, pagination: array{page:int, perPage:int, total:int, lastPage:int}, counts: array<string,int>}
     */
    private function historyPage(Request $request): array
    {
        $departmentId = auth('dept')->user()->department_id;
        $empty = [
            'history' => [],
            'pagination' => ['page' => 1, 'perPage' => self::PER_PAGE, 'total' => 0, 'lastPage' => 1],
            'counts' => ['total' => 0, 'returned' => 0, 'late' => 0, 'damaged' => 0],
        ];

        if (!$departmentId) {
            return $empty;
        }

        $scope = BorrowTransaction::query()
            ->where('status', 'Returned')
            ->whereHas('request.equipment', fn ($query) => $query->where('department_id', $departmentId));

        // Status of every record, from the few columns it depends on.
        $statuses = (clone $scope)
            ->get(['transaction_id', 'due_date', 'return_date', 'return_submitted_at', 'condition_on_return'])
            ->mapWithKeys(fn (BorrowTransaction $t) => [
                $t->transaction_id => $this->resolveStatus($t, $this->conditionParts($t)[0]),
            ]);

        $counts = [
            'total' => $statuses->count(),
            'returned' => $statuses->filter(fn ($s) => $s === 'Returned')->count(),
            'late' => $statuses->filter(fn ($s) => $s === 'Returned Late')->count(),
            'damaged' => $statuses->filter(fn ($s) => $s === 'Damaged')->count(),
        ];

        // Search, role and category in the database; then the status.
        $filtered = (clone $scope);
        $this->applyFilters($filtered, $request);

        $status = strtolower((string) $request->query('status', 'all'));
        $status = in_array($status, self::STATUS_FILTERS, true) ? $status : 'all';

        $ids = $filtered
            ->orderByDesc('return_date')
            ->orderByDesc('transaction_id')
            ->pluck('transaction_id')
            ->filter(fn ($id) => $status === 'all' || strtolower((string) $statuses->get($id)) === $status)
            ->values();

        $total = $ids->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) $request->query('page', 1)), $lastPage);
        $pageIds = $ids->forPage($page, self::PER_PAGE)->values();

        // Full details for the page shown only, in list order.
        $transactions = BorrowTransaction::with([
            'request.user.student',
            'request.user.facultyMember',
            'request.equipment.category',
        ])
            ->whereIn('transaction_id', $pageIds)
            ->get()
            ->sortBy(fn (BorrowTransaction $t) => $pageIds->search($t->transaction_id));

        $rows = [];
        foreach ($transactions as $transaction) {
            if ($row = $this->buildRow($transaction)) {
                $rows[] = $row;
            }
        }

        return [
            'history' => $rows,
            'pagination' => ['page' => $page, 'perPage' => self::PER_PAGE, 'total' => $total, 'lastPage' => $lastPage],
            'counts' => $counts,
        ];
    }

    /**
     * Role (student / faculty), category (by name) and search: every word must
     * match the transaction ID ("TXN-0012" or "12"), the borrower's name,
     * email or ID number, or the equipment's name.
     */
    private function applyFilters(Builder $query, Request $request): void
    {
        $role = strtolower((string) $request->query('role', 'all'));
        if (in_array($role, ['student', 'faculty'], true)) {
            $query->whereHas('request.user', fn ($q) => $q->where('role', ucfirst($role)));
        }

        $category = trim((string) $request->query('category', 'all'));
        if ($category !== '' && strtolower($category) !== 'all') {
            $query->whereHas('request.equipment.category', fn ($q) => $q->whereRaw('LOWER(category_name) = ?', [mb_strtolower($category)]));
        }

        $search = trim(mb_substr((string) $request->query('search', ''), 0, 100));
        foreach ($search === '' ? [] : preg_split('/\s+/', $search) as $word) {
            $number = preg_match('/^(?:txn-?)?0*(\d+)$/i', $word, $m) ? (int) $m[1] : null;
            $word = str_replace(['%', '_'], '', $word);
            if ($word === '') {
                continue;
            }
            $like = '%' . $word . '%';

            $query->where(function ($q) use ($like, $number) {
                $q->whereHas('request.equipment', fn ($e) => $e->where('name', 'like', $like))
                    ->orWhereHas('request.user', fn ($u) => $u->where('email', 'like', $like)
                        ->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('id_number', 'like', $like))
                        ->orWhereHas('facultyMember', fn ($f) => $f->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like)
                            ->orWhere('faculty_id_number', 'like', $like)));

                if ($number !== null) {
                    $q->orWhere('transaction_id', $number);
                }
            });
        }
    }

    private function buildRow(BorrowTransaction $transaction): ?array
    {
        $request = $transaction->request;
        $user = $request?->user;
        $equipment = $request?->equipment;

        if (!$request || !$user || !$equipment) {
            return null;
        }

        $isStudent = $user->role === 'Student';
        [$conditionType, $remarks] = $this->conditionParts($transaction);
        $fmt = fn ($value) => LoanTime::format($value);

        return [
            'txnid' => 'TXN-' . str_pad((string) $transaction->transaction_id, 4, '0', STR_PAD_LEFT),
            'idnumber' => ($isStudent
                ? $user->student?->id_number
                : $user->facultyMember?->faculty_id_number) ?: '—',
            'name' => $user->fullName() ?: $user->email,
            'userType' => $isStudent ? 'Student' : 'Faculty',
            'attainment' => ($isStudent
                ? $user->student?->year_level
                : $user->facultyMember?->highest_educational_attainment) ?: '—',
            'equipment' => $equipment->name,
            'category' => $equipment->category?->category_name ?: 'General',
            'borrowDate' => $fmt($transaction->borrow_date),
            'returnDate' => $fmt($transaction->return_date),
            'status' => $this->resolveStatus($transaction, $conditionType),
            'remarks' => $remarks !== '' ? $remarks : '—',
        ];
    }

    /** "Good — remarks" / "Damaged — remarks" / "Lost — remarks" → [type, remarks]. */
    private function conditionParts(BorrowTransaction $transaction): array
    {
        $parts = explode('—', trim((string) $transaction->condition_on_return), 2);

        return [trim($parts[0]), isset($parts[1]) ? trim($parts[1]) : ''];
    }

    /**
     * Damaged / Lost come from the condition reported on return; otherwise a
     * return after the due date is "Returned Late".
     */
    private function resolveStatus(BorrowTransaction $transaction, string $conditionType): string
    {
        if (strcasecmp($conditionType, 'Damaged') === 0) {
            return 'Damaged';
        }

        // Recorded by the department's return inspection.
        if (strcasecmp($conditionType, 'Incomplete') === 0) {
            return 'Incomplete';
        }

        if (strcasecmp($conditionType, 'Lost') === 0) {
            return 'Lost';
        }

        // Handed in after the exact due date and time.
        if ($transaction->daysLate() > 0) {
            return 'Returned Late';
        }

        return 'Returned';
    }
}
