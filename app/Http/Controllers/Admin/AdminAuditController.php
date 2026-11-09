<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditTrail;
use App\Services\AuditCatalog;
use App\Services\AuditLogger;
use App\Support\ReportPdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin Audit Trail.
 *
 * Every row shown comes from the `audit_trail` table; nothing is generated or
 * hardcoded. Search, filters, sorting and paging run in SQL, and the Action /
 * Module filters are built from AuditCatalog so they always match what the
 * system actually records.
 *
 * "Today / This Week / This Month" are measured against the database clock,
 * the same clock that stamps `created_at`, so they agree with the stored times.
 */
class AdminAuditController extends Controller
{
    private const PER_PAGE = 10;

    private const EXPORT_LIMIT = 5000;

    /**
     * Export PDF is drawn on the server, which takes about 20 seconds for
     * 1,000 rows, so the PDF holds the first 1,000 (in the table's sort order).
     * CSV and Print keep 5,000.
     */
    private const PDF_LIMIT = 1000;

    private const RANGES = ['all', 'today', 'week', 'month', 'custom'];

    private const FORMATS = ['csv' => 'CSV', 'pdf' => 'PDF', 'print' => 'Print'];

    public function index(): View
    {
        return view('admin.audit', [
            'actions' => AuditCatalog::actions(),
            'modules' => AuditCatalog::modules(),
            'stats' => $this->stats(),
        ]);
    }

    /** JSON feed for the table: one filtered, sorted page plus the stat cards. */
    public function data(Request $request): JsonResponse
    {
        $filters = $this->filters($request);
        $query = $this->query($filters);

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) ($filters['page'] ?? 1)), $lastPage);

        $records = $query->forPage($page, self::PER_PAGE)->get()
            ->map(fn (AuditTrail $log) => $this->present($log))
            ->values();

        return response()->json([
            'success' => true,
            'records' => $records,
            'total' => $total,
            'page' => $page,
            'lastPage' => $lastPage,
            'from' => $total === 0 ? 0 : (($page - 1) * self::PER_PAGE) + 1,
            'to' => $total === 0 ? 0 : min($page * self::PER_PAGE, $total),
            'hasAny' => AuditTrail::query()->exists(),
            'stats' => $this->stats(),
        ]);
    }

    /**
     * Everything the current filters match (not just the visible page), as a
     * CSV download, a PDF file download (Export PDF), or the print-ready
     * Audit Trail Report opened in its own tab (Print Report).
     */
    public function export(Request $request): StreamedResponse|Response|View
    {
        $format = strtolower((string) $request->query('format', 'csv'));
        abort_unless(isset(self::FORMATS[$format]), 404);

        $filters = $this->filters($request);
        $admin = auth('admin')->user();
        // Read before the export is logged, so the report shows the moment it was made.
        $generatedAt = $this->dbNow();

        $limit = $format === 'pdf' ? self::PDF_LIMIT : self::EXPORT_LIMIT;
        $rows = $this->query($filters)->limit($limit)->get()
            ->map(fn (AuditTrail $log) => $this->present($log));

        // Logged after the rows are read, so the export does not list itself.
        AuditLogger::log(
            'Exported audit log (' . self::FORMATS[$format] . ')',
            'audit',
            $rows->count() . ' record(s) exported. Filters: ' . $this->describeFilters($filters),
            ['admin_id' => $admin->admin_id],
            $request,
        );

        if ($format === 'pdf') {
            return ReportPdf::download(
                $this->printableReport($rows, $limit),
                $this->describeFilters($filters, true),
                $generatedAt->format('M d, Y h:i A'),
                $admin->name ?: 'Administrator',
                'equiptrack-audit-trail-' . now()->format('Y-m-d') . '.pdf',
            );
        }

        if ($format === 'print') {
            return view('admin.reports-print', [
                'report' => $this->printableReport($rows, $limit),
                'filtersText' => $this->describeFilters($filters, true),
                'generatedAt' => $generatedAt->format('M d, Y h:i A'),
                'generatedBy' => $admin->name ?: 'Administrator',
            ]);
        }

        $filename = 'equiptrack-audit-trail-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            $put = fn (array $fields) => fputcsv($out, $fields, ',', '"', '');

            // UTF-8 BOM so Excel reads accented characters correctly.
            fwrite($out, "\xEF\xBB\xBF");

            $put(['Date & Time', 'User', 'Role', 'Module', 'Action', 'Affected Record', 'Description', 'Note', 'IP Address']);

            foreach ($rows as $row) {
                $put(array_map([$this, 'csvSafe'], [
                    $row['date'],
                    $row['user'],
                    $row['role'],
                    $row['module'],
                    $row['action'],
                    $row['entity'],
                    $row['description'],
                    $row['note'],
                    $row['ip'],
                ]));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * The exported rows in the shape the shared print template reads. Action
     * is the template's status column, so the report opens with a count per
     * action.
     */
    private function printableReport($rows, int $limit): array
    {
        return [
            'title' => 'Audit Trail Report',
            'columns' => [
                ['key' => 'date', 'label' => 'Date & Time', 'kind' => 'text', 'pdfWidth' => 14],
                ['key' => 'user', 'label' => 'User', 'kind' => 'text', 'pdfWidth' => 13],
                ['key' => 'role', 'label' => 'Role', 'kind' => 'text', 'pdfWidth' => 9],
                ['key' => 'module', 'label' => 'Module', 'kind' => 'text', 'pdfWidth' => 9],
                ['key' => 'action', 'label' => 'Action', 'kind' => 'status', 'pdfWidth' => 11],
                ['key' => 'entity', 'label' => 'Affected Record', 'kind' => 'text', 'pdfWidth' => 10],
                ['key' => 'description', 'label' => 'Description', 'kind' => 'text', 'pdfWidth' => 14],
                ['key' => 'note', 'label' => 'Note', 'kind' => 'text', 'pdfWidth' => 13],
                ['key' => 'ip', 'label' => 'IP Address', 'kind' => 'text', 'pdfWidth' => 7],
            ],
            'rows' => $rows->all(),
            'total' => $rows->count(),
            'note' => $rows->count() >= $limit
                ? 'Only the first ' . number_format($limit) . ' matching records are included. Narrow the filters'
                    . ($limit < self::EXPORT_LIMIT ? ', or use Export CSV (up to ' . number_format(self::EXPORT_LIMIT) . ' records),' : '')
                    . ' to see the rest.'
                : null,
        ];
    }

    // ------------------------------------------------------------------
    // Filters & query
    // ------------------------------------------------------------------

    private function filters(Request $request): array
    {
        return $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', Rule::in(AuditCatalog::actions())],
            'module' => ['nullable', Rule::in(AuditCatalog::modules())],
            'range' => ['nullable', Rule::in(self::RANGES)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            'sort' => ['nullable', Rule::in(['date', 'action'])],
            'dir' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
    }

    private function query(array $filters): Builder
    {
        $query = AuditTrail::query()->with([
            'admin',
            'departmentAccount',
            'user.student',
            'user.facultyMember',
        ]);

        if (!empty($filters['action'])) {
            $this->wherePrefixes($query, AuditCatalog::prefixesForAction($filters['action']));
        }

        if (!empty($filters['module'])) {
            $this->wherePrefixes($query, AuditCatalog::prefixesForModule($filters['module']));
        }

        [$start, $end] = $this->window($filters);
        if ($start) {
            $query->where('created_at', '>=', $start->toDateTimeString());
        }
        if ($end) {
            $query->where('created_at', '<=', $end->toDateTimeString());
        }

        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $this->search($query, $term);
        }

        $dir = ($filters['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        if (($filters['sort'] ?? 'date') === 'action') {
            $query->orderBy('action', $dir)->orderByDesc('audit_id');
        } else {
            $query->orderBy('created_at', $dir)->orderBy('audit_id', $dir);
        }

        return $query;
    }

    /** Rows whose action text starts with any of the given catalog prefixes. */
    private function wherePrefixes(Builder $query, array $prefixes): void
    {
        if ($prefixes === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function ($group) use ($prefixes) {
            foreach ($prefixes as $prefix) {
                $group->orWhere('action', 'like', $prefix . '%');
            }
        });
    }

    /** Matches the action text, notes, affected record, the person, and the action / module names. */
    private function search(Builder $query, string $term): void
    {
        $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';

        // Action and module labels are not stored; match them through their prefixes.
        $prefixes = [];
        foreach (AuditCatalog::actions() as $label) {
            if (mb_stripos($label, $term) !== false) {
                array_push($prefixes, ...AuditCatalog::prefixesForAction($label));
            }
        }
        foreach (AuditCatalog::modules() as $module) {
            if (mb_stripos($module, $term) !== false) {
                array_push($prefixes, ...AuditCatalog::prefixesForModule($module));
            }
        }
        $prefixes = array_values(array_unique($prefixes));

        $query->where(function ($group) use ($like, $prefixes) {
            $group->whereRaw("action like ? escape '!'", [$like])
                ->orWhereRaw("affected_entity like ? escape '!'", [$like])
                // Report rows keep machine-readable JSON in `details`; do not search it.
                ->orWhere(function ($notes) use ($like) {
                    $notes->where('affected_entity', 'not like', 'report:%')
                        ->whereRaw("details like ? escape '!'", [$like]);
                })
                ->orWhereHas('admin', fn ($q) => $this->matchColumns($q, ['name', 'username', 'email'], $like))
                ->orWhereHas('departmentAccount', fn ($q) => $this->matchColumns($q, ['full_name', 'email'], $like))
                ->orWhereHas('user', fn ($q) => $this->matchColumns($q, ['email'], $like))
                ->orWhereHas('user.student', fn ($q) => $this->matchColumns($q, ['first_name', 'last_name'], $like))
                ->orWhereHas('user.facultyMember', fn ($q) => $this->matchColumns($q, ['first_name', 'last_name'], $like));

            foreach ($prefixes as $prefix) {
                $group->orWhere('action', 'like', $prefix . '%');
            }
        });
    }

    private function matchColumns($query, array $columns, string $like): void
    {
        $query->where(function ($group) use ($columns, $like) {
            foreach ($columns as $column) {
                $group->orWhereRaw("{$column} like ? escape '!'", [$like]);
            }
        });
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} start and end of the chosen date range */
    private function window(array $filters): array
    {
        $now = $this->dbNow();

        return match ($filters['range'] ?? 'all') {
            'today' => [$now->copy()->startOfDay(), null],
            'week' => [$now->copy()->startOfWeek(), null],
            'month' => [$now->copy()->startOfMonth(), null],
            'custom' => [
                !empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : null,
                !empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : null,
            ],
            default => [null, null],
        };
    }

    /** The applied filters in words: short for the audit log, readable for the printed report. */
    private function describeFilters(array $filters, bool $forReport = false): string
    {
        $parts = [];

        if (!empty($filters['q'])) {
            $parts[] = ($forReport ? 'Search: ' : 'search ') . '"' . $filters['q'] . '"';
        }
        if (!empty($filters['action'])) {
            $parts[] = ($forReport ? 'Action: ' : 'action ') . $filters['action'];
        }
        if (!empty($filters['module'])) {
            $parts[] = ($forReport ? 'Module: ' : 'module ') . $filters['module'];
        }

        $range = $filters['range'] ?? 'all';
        if ($range === 'custom') {
            $from = $filters['from'] ?? null;
            $to = $filters['to'] ?? null;
            $parts[] = $forReport
                ? 'Dates: ' . ($from ? Carbon::parse($from)->format('M d, Y') : 'Earliest') . ' to ' . ($to ? Carbon::parse($to)->format('M d, Y') : 'Latest')
                : 'dates ' . ($from ?? 'earliest') . ' to ' . ($to ?? 'latest');
        } elseif ($range !== 'all') {
            $parts[] = $forReport
                ? 'Dates: ' . ['today' => 'Today', 'week' => 'This Week', 'month' => 'This Month'][$range]
                : 'range ' . $range;
        }

        if (!$parts) {
            return $forReport ? 'None (all records)' : 'none';
        }

        return implode($forReport ? ' · ' : ', ', $parts);
    }

    // ------------------------------------------------------------------
    // Rows & stats
    // ------------------------------------------------------------------

    private function present(AuditTrail $log): array
    {
        $class = AuditCatalog::classify((string) $log->action);
        [$user, $role] = $this->actor($log);

        // Report rows keep machine-readable JSON in `details`; it is not for display.
        $isReport = str_starts_with((string) $log->affected_entity, 'report:');

        return [
            'id' => (int) $log->audit_id,
            'date' => $log->created_at ? $log->created_at->format('M d, Y • h:i A') : '—',
            'user' => $user,
            'role' => $role,
            'module' => $class['module'],
            'action' => $class['label'],
            'entity' => AuditCatalog::entityLabel((string) $log->affected_entity),
            'description' => AuditCatalog::description($class['noun'], $class['rest']),
            'note' => $isReport ? '' : (string) $log->details,
            'ip' => (string) $log->ip_address,
        ];
    }

    /** @return array{0: string, 1: string} name and role of whoever performed the action */
    private function actor(AuditTrail $log): array
    {
        if ($log->admin) {
            return [$log->admin->name ?: $log->admin->username, 'Administrator'];
        }

        if ($log->departmentAccount) {
            return [$log->departmentAccount->full_name ?: $log->departmentAccount->email, 'Department'];
        }

        if ($log->user) {
            return [$log->user->fullName() ?: $log->user->email, $log->user->role ?: 'User'];
        }

        // A sign-in that didn't succeed: nobody was signed in.
        if (in_array(AuditCatalog::classify((string) $log->action)['label'], AuditCatalog::SIGN_IN_PROBLEMS, true)) {
            return ['Not signed in', 'Visitor'];
        }

        // Every actor column is empty: the account was deleted after acting.
        return ['Deleted account', '—'];
    }

    private function stats(): array
    {
        $now = $this->dbNow();
        $dayStart = $now->copy()->startOfDay()->toDateTimeString();

        $since = fn (Carbon $from) => AuditTrail::where('created_at', '>=', $from->toDateTimeString())->count();

        $activeToday = AuditTrail::where('created_at', '>=', $dayStart)
            ->select('admin_id', 'dept_acc_id', 'user_id')
            ->distinct()
            ->get()
            ->filter(fn ($row) => $row->admin_id || $row->dept_acc_id || $row->user_id)
            ->count();

        return [
            'today' => $since($now->copy()->startOfDay()),
            'week' => $since($now->copy()->startOfWeek()),
            'month' => $since($now->copy()->startOfMonth()),
            'activeToday' => $activeToday,
        ];
    }

    /** "Now" on the database clock, which is what stamps audit_trail.created_at. */
    private function dbNow(): Carbon
    {
        try {
            $sql = DB::connection()->getDriverName() === 'sqlite'
                ? "select datetime('now') as n"
                : 'select now() as n';

            return Carbon::parse(DB::selectOne($sql)->n);
        } catch (\Throwable) {
            return now();
        }
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
