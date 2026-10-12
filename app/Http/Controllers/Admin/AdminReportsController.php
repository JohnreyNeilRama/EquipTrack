<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Models\BorrowRequest;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin Reports.
 *
 * Every figure, chart and table row is derived from the existing tables
 * (borrow_request, borrow_transaction, equipment, equipment_category,
 * user_account / student / faculty_member) - nothing is hardcoded.
 *
 * Status rules deliberately mirror the rest of the system so the numbers agree
 * with the Dashboard, Monitoring and Department History pages:
 *  - a loan is an Approved request that has a borrow_transaction;
 *  - a loan is Returned when its transaction is Returned, otherwise it is still
 *    out on loan (Borrowed) - and Overdue once its due date has passed or the
 *    transaction itself is flagged Overdue (same rule as AdminDashboardController);
 *  - returns are Damaged / Lost from the condition recorded on return, or
 *    Returned Late when returned after the due date (same rule as
 *    DepartmentHistoryController).
 *
 * Generating and exporting a report is written to audit_trail (entity
 * "report:<type>") which feeds "Reports Generated" and "Recent Generated Reports".
 */
class AdminReportsController extends Controller
{
    /** Report types shown in the Report Type dropdown. `basis` is the date the range filters on. */
    private const TYPES = [
        'borrowing' => ['label' => 'Borrowing Report', 'basis' => 'Borrow date'],
        'return' => ['label' => 'Return Report', 'basis' => 'Return date'],
        'inventory' => ['label' => 'Equipment Inventory Report', 'basis' => null],
        'overdue' => ['label' => 'Overdue Report', 'basis' => 'Due date'],
        'activity' => ['label' => 'User Activity Report', 'basis' => 'Borrow date'],
    ];

    /** Status filter values available for each report type (empty = no status filter). */
    private const STATUSES = [
        'borrowing' => ['Pending', 'Approved', 'Rejected', 'Borrowed', 'Overdue', 'Returned'],
        'return' => ['Returned', 'Returned Late', 'Damaged', 'Incomplete', 'Lost'],
        'inventory' => ['Available', 'Unavailable', 'On Hold', 'Under Maintenance'],
        'overdue' => [],
        'activity' => [],
    ];

    private const FORMATS = ['csv' => 'CSV', 'pdf' => 'PDF', 'print' => 'Print'];

    private const RECENT_LIMIT = 25;

    public function index(): View
    {
        return view('admin.reports', [
            'dbCategories' => EquipmentCategory::orderBy('category_name')->get(['category_id', 'category_name']),
            'reportTypes' => collect(self::TYPES)->map(fn ($type) => $type['label'])->all(),
            'reportBasis' => collect(self::TYPES)->map(fn ($type) => $type['basis'])->all(),
            'reportStatuses' => self::STATUSES,
            'report' => $this->buildPayload($this->normalizeFilters([])),
        ]);
    }

    /** JSON feed: recomputes everything for the given filters (does not log). */
    public function data(Request $request): JsonResponse
    {
        $filters = $this->normalizeFilters($this->validated($request));

        return response()->json(['success' => true] + $this->buildPayload($filters));
    }

    /** Generate Report button: same payload as data(), and the run is logged. */
    public function generate(Request $request): JsonResponse
    {
        $filters = $this->normalizeFilters($this->validated($request));

        $this->logActivity($request, $filters, 'Generated');

        return response()->json(['success' => true] + $this->buildPayload($filters));
    }

    /**
     * CSV download, or a print-optimised page for PDF / Print (the browser's
     * print dialog can save it as a PDF). Uses the same rows as the preview.
     */
    public function export(Request $request): StreamedResponse|View
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        abort_unless(isset(self::FORMATS[$format]), 404);

        $filters = $this->normalizeFilters($this->validated($request));
        $payload = $this->buildPayload($filters);
        $report = $payload['report'];
        $admin = auth('admin')->user();

        $this->logActivity($request, $filters, 'Exported', self::FORMATS[$format]);

        $filtersText = $this->describeFilters($filters);

        if ($format !== 'csv') {
            return view('admin.reports-print', [
                'report' => $report,
                'filtersText' => $filtersText,
                'generatedAt' => $payload['generatedAt'],
                'generatedBy' => $admin->name ?? 'Administrator',
                'format' => $format,
            ]);
        }

        $filename = 'equiptrack-' . $filters['type'] . '-report-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($report, $payload, $filtersText, $admin) {
            $out = fopen('php://output', 'w');
            $put = fn (array $fields) => fputcsv($out, $fields, ',', '"', '');

            // UTF-8 BOM so Excel reads accented characters correctly.
            fwrite($out, "\xEF\xBB\xBF");

            $put(['EquipTrack - ' . $report['title']]);
            $put(['Generated by', $admin->name ?? 'Administrator']);
            $put(['Generated on', $payload['generatedAt']]);
            $put(['Filters', $filtersText]);
            $put([]);
            $put(array_map(fn ($column) => $column['label'], $report['columns']));

            foreach ($report['rows'] as $row) {
                $put(array_map(fn ($column) => $this->csvSafe($row[$column['key']] ?? ''), $report['columns']));
            }

            $put([]);
            $put(['Total records', $report['total']]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ------------------------------------------------------------------
    // Filters
    // ------------------------------------------------------------------

    private function validated(Request $request): array
    {
        return $request->validate([
            'type' => ['nullable', 'in:' . implode(',', array_keys(self::TYPES))],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'status' => ['nullable', 'string', 'max:30'],
            'category' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    /** Fills defaults and drops values that do not apply to the chosen report type. */
    private function normalizeFilters(array $input): array
    {
        $type = $input['type'] ?? 'borrowing';
        if (!isset(self::TYPES[$type])) {
            $type = 'borrowing';
        }

        $status = trim((string) ($input['status'] ?? ''));
        if (!in_array($status, self::STATUSES[$type], true)) {
            $status = '';
        }

        $categoryId = (int) ($input['category'] ?? 0);
        $category = $categoryId > 0 ? EquipmentCategory::find($categoryId) : null;

        $from = $this->parseDate($input['from'] ?? null);
        $to = $this->parseDate($input['to'] ?? null);

        // The inventory report is a snapshot of current stock; a date range does not apply.
        if (self::TYPES[$type]['basis'] === null) {
            $from = null;
            $to = null;
        }

        return [
            'type' => $type,
            'from' => $from ? $from->toDateString() : null,
            'to' => $to ? $to->toDateString() : null,
            'status' => $status,
            'category' => $category ? (int) $category->category_id : null,
            'category_name' => $category ? $category->category_name : null,
        ];
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function describeFilters(array $filters): string
    {
        $parts = [];

        if ($filters['from'] || $filters['to']) {
            $from = $filters['from'] ? Carbon::parse($filters['from'])->format('M d, Y') : 'earliest';
            $to = $filters['to'] ? Carbon::parse($filters['to'])->format('M d, Y') : 'latest';
            $parts[] = (self::TYPES[$filters['type']]['basis'] ?? 'Date') . ': ' . $from . ' to ' . $to;
        }

        if ($filters['status'] !== '') {
            $parts[] = 'Status: ' . $filters['status'];
        }

        if ($filters['category_name']) {
            $parts[] = 'Category: ' . $filters['category_name'];
        }

        return $parts ? implode(' | ', $parts) : 'None (all records)';
    }

    // ------------------------------------------------------------------
    // Payload
    // ------------------------------------------------------------------

    private function buildPayload(array $filters): array
    {
        $today = now()->startOfDay();
        $all = $this->loadRecords($today);

        return [
            'filters' => $filters,
            'report' => $this->buildReport($filters, $all),
            'summary' => $this->summary($filters, $all),
            'charts' => $this->charts($filters, $all, $today),
            'recent' => $this->recentActivity(),
            'generatedAt' => now()->format('M d, Y h:i A'),
        ];
    }

    /** One normalised record per borrow request, with its loan state resolved. */
    private function loadRecords(CarbonInterface $today): Collection
    {
        return BorrowRequest::with([
            'user.student',
            'user.facultyMember',
            'equipment.category',
            'transaction',
        ])
            ->orderBy('request_id')
            ->get()
            ->map(fn (BorrowRequest $request) => $this->describe($request, $today))
            ->values();
    }

    private function describe(BorrowRequest $request, CarbonInterface $today): array
    {
        $user = $request->user;
        $equipment = $request->equipment;
        $transaction = $request->transaction;

        $overall = $request->overall_status ?: 'Pending';

        // A loan only exists for an approved request that has a transaction
        // (the same population the Dashboard and Monitoring pages use).
        $isLoan = $overall === 'Approved' && $transaction !== null;
        $returned = $isLoan && $transaction->status === 'Returned';
        $active = $isLoan && !$returned;

        $borrow = $transaction?->borrow_date ?? $request->borrow_date ?? $request->date_needed;
        $due = $transaction?->due_date ?? $request->due_date ?? $request->return_date;
        $returnedOn = $returned ? $transaction->return_date : null;

        $overdue = $active && (
            ($due && $due->copy()->startOfDay()->lt($today))
            || $transaction->status === 'Overdue'
        );

        if ($overall === 'Rejected') {
            $lifecycle = 'Rejected';
        } elseif ($overall !== 'Approved') {
            $lifecycle = 'Pending';
        } elseif ($returned) {
            $lifecycle = 'Returned';
        } elseif ($overdue) {
            $lifecycle = 'Overdue';
        } elseif ($active) {
            $lifecycle = 'Borrowed';
        } else {
            // Approved before loans were tracked: no transaction exists.
            $lifecycle = 'Approved';
        }

        // "Good — remarks" / "Damaged — remarks" / "Lost — remarks"
        $conditionType = '';
        if ($returned) {
            $parts = explode('—', trim((string) $transaction->condition_on_return), 2);
            $conditionType = trim($parts[0]);
        }

        $isStudent = $user && $user->role === 'Student';
        $idNumber = $user
            ? ($isStudent ? $user->student?->id_number : $user->facultyMember?->faculty_id_number)
            : null;

        return [
            'request_id' => (int) $request->request_id,
            'user_id' => (int) $request->user_id,
            'user' => ($user ? $user->fullName() : '') ?: ($user ? $user->email : 'Unknown User'),
            'id_number' => $idNumber ?: '—',
            'role' => $user ? $user->role : '—',
            'equipment_id' => (int) $request->equipment_id,
            'equipment' => $equipment ? $equipment->name : 'Unknown Equipment',
            'category_id' => $equipment && $equipment->category_id ? (int) $equipment->category_id : null,
            'category' => ($equipment && $equipment->category ? $equipment->category->category_name : null) ?: 'General',
            'qty' => max(1, (int) ($request->quantity ?? 1)),
            'overall' => $overall,
            'lifecycle' => $lifecycle,
            'loan' => $returned ? 'Returned' : ($active ? 'Active' : null),
            'overdue' => $overdue,
            'borrow' => $borrow,
            'due' => $due,
            'returned_on' => $returnedOn,
            'days_overdue' => $overdue && $due
                ? max(0, (int) $due->copy()->startOfDay()->diffInDays($today))
                : 0,
            'condition' => $conditionType,
            'return_status' => $returned ? $this->returnStatus($transaction, $conditionType) : null,
        ];
    }

    /** Damaged / Incomplete / Lost from the recorded condition; otherwise Returned Late when past due. */
    private function returnStatus($transaction, string $conditionType): string
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

        if ($transaction->return_date && $transaction->due_date
            && $transaction->return_date->toDateString() > $transaction->due_date->toDateString()) {
            return 'Returned Late';
        }

        return 'Returned';
    }

    // ------------------------------------------------------------------
    // Scope helpers
    // ------------------------------------------------------------------

    private function inRange(?CarbonInterface $date, ?string $from, ?string $to): bool
    {
        if ($from === null && $to === null) {
            return true;
        }

        if ($date === null) {
            return false;
        }

        $day = $date->toDateString();

        if ($from !== null && $day < $from) {
            return false;
        }

        return $to === null || $day <= $to;
    }

    private function categoryMatches(array $row, array $filters): bool
    {
        return $filters['category'] === null || $row['category_id'] === $filters['category'];
    }

    /** Requests whose borrow date falls in the range and that match the category. */
    private function scope(Collection $all, array $filters): Collection
    {
        return $all->filter(
            fn (array $row) => $this->categoryMatches($row, $filters)
                && $this->inRange($row['borrow'], $filters['from'], $filters['to'])
        )->values();
    }

    private function fmt(?CarbonInterface $date): string
    {
        return $date ? $date->format('M d, Y') : '—';
    }

    // ------------------------------------------------------------------
    // Summary cards & charts
    // ------------------------------------------------------------------

    private function summary(array $filters, Collection $all): array
    {
        $scope = $this->scope($all, $filters);
        $approved = $scope->where('overall', 'Approved');
        $active = $approved->where('loan', 'Active');

        // On the Return / Overdue reports the date range filters by return / due
        // date, so the matching card uses that same basis and equals the table.
        $returned = $filters['type'] === 'return'
            ? $all->filter(fn (array $row) => $row['loan'] === 'Returned'
                && $this->categoryMatches($row, $filters)
                && $this->inRange($row['returned_on'], $filters['from'], $filters['to']))
            : $approved->where('loan', 'Returned');

        $overdue = $filters['type'] === 'overdue'
            ? $all->filter(fn (array $row) => $row['overdue']
                && $this->categoryMatches($row, $filters)
                && $this->inRange($row['due'], $filters['from'], $filters['to']))
            : $active->where('overdue', true);

        $equipment = fn () => Equipment::query()
            ->when($filters['category'], fn ($query, $categoryId) => $query->where('category_id', $categoryId));

        return [
            'borrowings' => $approved->count(),
            'returns' => $returned->count(),
            'active' => $active->count(),
            'activeUnits' => (int) $active->sum('qty'),
            'overdue' => $overdue->count(),
            'overdueUnits' => (int) $overdue->sum('qty'),
            'requests' => $scope->count(),
            'pending' => $scope->where('overall', 'Pending')->count(),
            'rejected' => $scope->where('overall', 'Rejected')->count(),
            'availableUnits' => (int) $equipment()->sum('available_qty'),
            'totalUnits' => (int) $equipment()->sum('total_qty'),
            'reportsThisMonth' => $this->reportsThisMonth(),
        ];
    }

    private function charts(array $filters, Collection $all, CarbonInterface $today): array
    {
        $borrowings = $this->scope($all, $filters)->where('overall', 'Approved');

        // Monthly borrowings (approved requests by borrow month).
        [$start, $end] = $this->chartWindow($filters, $today);

        $keys = [];
        $labels = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $keys[] = $cursor->format('Y-m');
            $labels[] = $cursor->format('M Y');
            $cursor = $cursor->copy()->addMonth();
        }

        $monthly = array_fill_keys($keys, 0);
        foreach ($borrowings as $row) {
            $key = $row['borrow'] ? $row['borrow']->format('Y-m') : null;
            if ($key !== null && isset($monthly[$key])) {
                $monthly[$key]++;
            }
        }

        // Borrowings by equipment category.
        $byCategory = $borrowings->groupBy('category')->map(fn (Collection $group) => $group->count())->all();
        uksort($byCategory, fn ($a, $b) => ($byCategory[$b] <=> $byCategory[$a]) ?: strcmp((string) $a, (string) $b));

        // Most borrowed equipment.
        $top = $borrowings->groupBy('equipment_id')
            ->map(fn (Collection $group) => [
                'name' => $group->first()['equipment'],
                'count' => $group->count(),
                'units' => (int) $group->sum('qty'),
            ])
            ->sort(fn (array $a, array $b) => [$b['count'], $b['units'], $a['name']] <=> [$a['count'], $a['units'], $b['name']])
            ->first();

        return [
            'monthly' => [
                'labels' => $labels,
                'values' => array_values($monthly),
                'range' => $labels ? $labels[0] . ($labels[0] !== end($labels) ? ' – ' . end($labels) : '') : '',
            ],
            'category' => [
                'labels' => array_map('strval', array_keys($byCategory)),
                'values' => array_values($byCategory),
            ],
            'mostBorrowed' => $top,
        ];
    }

    /** Months plotted on the monthly chart: the filter range (max 12 months) or the last 6 months. */
    private function chartWindow(array $filters, CarbonInterface $today): array
    {
        $end = $filters['to']
            ? Carbon::parse($filters['to'])->startOfMonth()
            : $today->copy()->startOfMonth();

        $start = $filters['from']
            ? Carbon::parse($filters['from'])->startOfMonth()
            : $end->copy()->subMonths(5);

        if ($start->gt($end)) {
            $end = $start->copy();
        }

        if ($start->diffInMonths($end) > 11) {
            $start = $end->copy()->subMonths(11);
        }

        return [$start, $end];
    }

    // ------------------------------------------------------------------
    // Reports
    // ------------------------------------------------------------------

    private function col(string $key, string $label, string $kind = 'text'): array
    {
        return ['key' => $key, 'label' => $label, 'kind' => $kind];
    }

    private function finish(string $type, array $columns, Collection $rows): array
    {
        return [
            'type' => $type,
            'title' => self::TYPES[$type]['label'],
            'basis' => self::TYPES[$type]['basis'],
            'columns' => $columns,
            'rows' => $rows->values()->all(),
            'total' => $rows->count(),
        ];
    }

    private function buildReport(array $filters, Collection $all): array
    {
        return match ($filters['type']) {
            'return' => $this->returnReport($filters, $all),
            'inventory' => $this->inventoryReport($filters, $all),
            'overdue' => $this->overdueReport($filters, $all),
            'activity' => $this->activityReport($filters, $all),
            default => $this->borrowingReport($filters, $all),
        };
    }

    /** Every borrow request (pending, approved, rejected) with where it currently stands. */
    private function borrowingReport(array $filters, Collection $all): array
    {
        $rows = $this->scope($all, $filters)
            ->filter(fn (array $row) => match ($filters['status']) {
                '' => true,
                'Pending', 'Approved', 'Rejected' => $row['overall'] === $filters['status'],
                default => $row['lifecycle'] === $filters['status'],
            })
            ->sort(fn (array $a, array $b) => [$this->stamp($b['borrow']), $b['request_id']] <=> [$this->stamp($a['borrow']), $a['request_id']])
            ->map(fn (array $row) => [
                'borrower' => $row['user'],
                'id_number' => $row['id_number'],
                'equipment' => $row['equipment'],
                'category' => $row['category'],
                'qty' => $row['qty'],
                'borrow_date' => $this->fmt($row['borrow']),
                'due_date' => $this->fmt($row['due']),
                'return_date' => $this->fmt($row['returned_on']),
                'status' => $row['lifecycle'],
            ]);

        return $this->finish('borrowing', [
            $this->col('borrower', 'Borrower'),
            $this->col('id_number', 'Student/Faculty ID'),
            $this->col('equipment', 'Equipment'),
            $this->col('category', 'Category'),
            $this->col('qty', 'Qty', 'num'),
            $this->col('borrow_date', 'Borrow Date'),
            $this->col('due_date', 'Due Date'),
            $this->col('return_date', 'Return Date'),
            $this->col('status', 'Status', 'status'),
        ], $rows);
    }

    /** Settled loans, filtered by the date they were actually returned. */
    private function returnReport(array $filters, Collection $all): array
    {
        $rows = $all
            ->filter(fn (array $row) => $row['loan'] === 'Returned'
                && $this->categoryMatches($row, $filters)
                && $this->inRange($row['returned_on'], $filters['from'], $filters['to'])
                && ($filters['status'] === '' || $row['return_status'] === $filters['status']))
            ->sort(fn (array $a, array $b) => [$this->stamp($b['returned_on']), $b['request_id']] <=> [$this->stamp($a['returned_on']), $a['request_id']])
            ->map(fn (array $row) => [
                'borrower' => $row['user'],
                'id_number' => $row['id_number'],
                'equipment' => $row['equipment'],
                'category' => $row['category'],
                'qty' => $row['qty'],
                'borrow_date' => $this->fmt($row['borrow']),
                'due_date' => $this->fmt($row['due']),
                'return_date' => $this->fmt($row['returned_on']),
                'condition' => $row['condition'] !== '' ? $row['condition'] : '—',
                'status' => $row['return_status'],
            ]);

        return $this->finish('return', [
            $this->col('borrower', 'Borrower'),
            $this->col('id_number', 'Student/Faculty ID'),
            $this->col('equipment', 'Equipment'),
            $this->col('category', 'Category'),
            $this->col('qty', 'Qty', 'num'),
            $this->col('borrow_date', 'Borrow Date'),
            $this->col('due_date', 'Due Date'),
            $this->col('return_date', 'Return Date'),
            $this->col('condition', 'Condition'),
            $this->col('status', 'Status', 'status'),
        ], $rows);
    }

    /** Loans still out and past their due date, filtered by due date. */
    private function overdueReport(array $filters, Collection $all): array
    {
        $rows = $all
            ->filter(fn (array $row) => $row['overdue']
                && $this->categoryMatches($row, $filters)
                && $this->inRange($row['due'], $filters['from'], $filters['to']))
            ->sort(fn (array $a, array $b) => [$b['days_overdue'], $a['request_id']] <=> [$a['days_overdue'], $b['request_id']])
            ->map(fn (array $row) => [
                'borrower' => $row['user'],
                'id_number' => $row['id_number'],
                'equipment' => $row['equipment'],
                'category' => $row['category'],
                'qty' => $row['qty'],
                'borrow_date' => $this->fmt($row['borrow']),
                'due_date' => $this->fmt($row['due']),
                'days_overdue' => $row['days_overdue'],
            ]);

        return $this->finish('overdue', [
            $this->col('borrower', 'Borrower'),
            $this->col('id_number', 'Student/Faculty ID'),
            $this->col('equipment', 'Equipment'),
            $this->col('category', 'Category'),
            $this->col('qty', 'Qty', 'num'),
            $this->col('borrow_date', 'Borrow Date'),
            $this->col('due_date', 'Due Date'),
            $this->col('days_overdue', 'Days Overdue', 'num'),
        ], $rows);
    }

    /** Current stock per equipment item, with the units that are out on loan. */
    private function inventoryReport(array $filters, Collection $all): array
    {
        $onLoan = $all->where('loan', 'Active')
            ->groupBy('equipment_id')
            ->map(fn (Collection $group) => (int) $group->sum('qty'));

        $rows = Equipment::with(['category', 'department'])
            ->when($filters['category'], fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('name')
            ->orderBy('equipment_id')
            ->get()
            ->map(fn (Equipment $item) => [
                'equipment' => $item->name,
                'serial' => $item->serial_number,
                'category' => $item->category ? $item->category->category_name : 'General',
                'department' => $item->department ? $item->department->department_name : '—',
                'total' => (int) $item->total_qty,
                'available' => (int) $item->available_qty,
                'on_loan' => (int) ($onLoan[$item->equipment_id] ?? 0),
                'status' => $item->status,
            ]);

        return $this->finish('inventory', [
            $this->col('equipment', 'Equipment'),
            $this->col('serial', 'Serial No.'),
            $this->col('category', 'Category'),
            $this->col('department', 'Department'),
            $this->col('total', 'Total Units', 'num'),
            $this->col('available', 'Available', 'num'),
            $this->col('on_loan', 'On Loan', 'num'),
            $this->col('status', 'Status', 'status'),
        ], $rows);
    }

    /** One row per borrower summarising their requests and loans. */
    private function activityReport(array $filters, Collection $all): array
    {
        $rows = $this->scope($all, $filters)
            ->groupBy('user_id')
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'borrower' => $first['user'],
                    'id_number' => $first['id_number'],
                    'role' => $first['role'],
                    'requests' => $group->count(),
                    'approved' => $group->where('overall', 'Approved')->count(),
                    'rejected' => $group->where('overall', 'Rejected')->count(),
                    'pending' => $group->where('overall', 'Pending')->count(),
                    'returned' => $group->where('loan', 'Returned')->count(),
                    'borrowed' => $group->where('loan', 'Active')->count(),
                    'overdue' => $group->where('overdue', true)->count(),
                ];
            })
            ->sort(fn (array $a, array $b) => [$b['requests'], $a['borrower']] <=> [$a['requests'], $b['borrower']]);

        return $this->finish('activity', [
            $this->col('borrower', 'Borrower'),
            $this->col('id_number', 'Student/Faculty ID'),
            $this->col('role', 'Role'),
            $this->col('requests', 'Requests', 'num'),
            $this->col('approved', 'Approved', 'num'),
            $this->col('rejected', 'Rejected', 'num'),
            $this->col('pending', 'Pending', 'num'),
            $this->col('returned', 'Returned', 'num'),
            $this->col('borrowed', 'Currently Borrowed', 'num'),
            $this->col('overdue', 'Overdue', 'num'),
        ], $rows);
    }

    private function stamp(?CarbonInterface $date): int
    {
        return $date ? $date->getTimestamp() : 0;
    }

    // ------------------------------------------------------------------
    // Generated-report log (audit_trail)
    // ------------------------------------------------------------------

    /**
     * Records that a report was generated or exported. The filters are stored
     * compactly in `details` so "Recent Generated Reports" can re-run them.
     */
    private function logActivity(Request $request, array $filters, string $verb, ?string $format = null): void
    {
        $meta = array_filter([
            't' => $filters['type'],
            'f' => $filters['from'],
            'to' => $filters['to'],
            's' => $filters['status'],
            'c' => $filters['category'],
            'x' => $format,
        ], fn ($value) => $value !== null && $value !== '');

        $action = $verb . ' report: ' . self::TYPES[$filters['type']]['label'] . ($format ? ' (' . $format . ')' : '');

        $log = new AuditTrail([
            'admin_id' => auth('admin')->user()->admin_id,
            'action' => mb_substr($action, 0, 100),
            'ip_address' => $request->ip() ?? '127.0.0.1',
            'affected_entity' => 'report:' . $filters['type'],
            'details' => json_encode($meta),
        ]);
        // Stamped from the app clock so it matches how the page reads it back.
        $log->created_at = now();
        $log->save();
    }

    private function reportsThisMonth(): int
    {
        return AuditTrail::where('affected_entity', 'like', 'report:%')
            ->where('action', 'like', 'Generated report:%')
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();
    }

    private function recentActivity(): array
    {
        $categories = EquipmentCategory::pluck('category_name', 'category_id');

        return AuditTrail::with('admin')
            ->where('affected_entity', 'like', 'report:%')
            ->orderByDesc('audit_id')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(function (AuditTrail $log) use ($categories) {
                $meta = json_decode((string) $log->details, true);
                $type = is_array($meta) ? ($meta['t'] ?? '') : '';

                if (!isset(self::TYPES[$type])) {
                    return null;
                }

                $categoryId = isset($meta['c']) ? (int) $meta['c'] : null;
                $categoryName = $categoryId ? ($categories[$categoryId] ?? null) : null;

                $detail = [];
                if (!empty($meta['f']) || !empty($meta['to'])) {
                    $from = !empty($meta['f']) ? Carbon::parse($meta['f'])->format('M d, Y') : 'earliest';
                    $to = !empty($meta['to']) ? Carbon::parse($meta['to'])->format('M d, Y') : 'latest';
                    $detail[] = $from . ' – ' . $to;
                }
                if (!empty($meta['s'])) {
                    $detail[] = $meta['s'];
                }
                if ($categoryName) {
                    $detail[] = $categoryName;
                }

                return [
                    'name' => self::TYPES[$type]['label'],
                    'detail' => $detail ? implode(' · ', $detail) : 'All records',
                    'by' => $log->admin ? $log->admin->name : 'Administrator',
                    'date' => $log->created_at ? $log->created_at->format('M d, Y h:i A') : '—',
                    'format' => $meta['x'] ?? 'On screen',
                    'filters' => [
                        'type' => $type,
                        'from' => $meta['f'] ?? '',
                        'to' => $meta['to'] ?? '',
                        'status' => $meta['s'] ?? '',
                        // A deleted category falls back to "All Categories" when re-run.
                        'category' => $categoryName ? $categoryId : '',
                    ],
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /** Stops spreadsheet apps from executing cell text that starts with a formula character. */
    private function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)
            ? "'" . $value
            : $value;
    }
}
