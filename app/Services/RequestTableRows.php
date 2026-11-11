<?php

namespace App\Services;

use App\Models\BorrowRequest;
use App\Models\BorrowTransaction;
use App\Support\LoanTime;
use App\Support\Avatar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Rows for the shared Requests table (resources/views/partials/requests),
 * used by both the Admin Requests page and the Department Borrow Requests
 * page so the two tables show exactly the same information.
 */
class RequestTableRows
{
    /** Rows per page on the Requests pages. */
    public const PER_PAGE = 25;

    /**
     * Status filter values. "active" (the default) hides finished requests:
     * rejected, cancelled and expired ones, and approved ones whose loan is
     * over (returned, not claimed or reservation cancelled).
     */
    public const STATUS_FILTERS = ['active', 'all', 'pending', 'approved', 'rejected', 'cancelled', 'expired'];

    /**
     * Every row of $query, newest first (or only the newest $limit rows).
     *
     * @param  callable(BorrowRequest): bool  $canReview  whether the viewer's role may approve/reject this request
     */
    public static function fetch(Builder $query, callable $canReview, ?int $limit = null): array
    {
        $rows = $query->with(self::RELATIONS)
            ->orderByDesc('request_id')
            ->when($limit, fn ($q, $limit) => $q->limit($limit))
            ->get();

        return $rows->map(fn (BorrowRequest $row) => self::row($row, $canReview($row)))->all();
    }

    /**
     * One page of the Requests table, searched and filtered in the database
     * (query parameters: page, search, status, role), plus the request counts
     * per status for the whole of $scope (the summary cards).
     *
     * @param  callable(BorrowRequest): bool  $canReview
     * @return array{requests: array, pagination: array{page:int, perPage:int, total:int, lastPage:int}, counts: array<string,int>, filters: array{status:string, role:string, search:string}}
     */
    public static function page(Builder $scope, callable $canReview, Request $request): array
    {
        $status = strtolower((string) $request->query('status', 'active'));
        $status = in_array($status, self::STATUS_FILTERS, true) ? $status : 'active';
        $role = strtolower((string) $request->query('role', 'all'));
        $role = in_array($role, ['all', 'student', 'faculty'], true) ? $role : 'all';
        $search = trim(mb_substr((string) $request->query('search', ''), 0, 100));

        $query = (clone $scope);
        self::applyStatus($query, $status);
        if ($role !== 'all') {
            $query->whereHas('user', fn ($q) => $q->where('role', ucfirst($role)));
        }
        if ($search !== '') {
            self::applySearch($query, $search);
        }

        $total = (clone $query)->count();
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = min(max(1, (int) $request->query('page', 1)), $lastPage);

        $rows = $query->with(self::RELATIONS)
            ->orderByDesc('request_id')
            ->forPage($page, self::PER_PAGE)
            ->get();

        return [
            'requests' => $rows->map(fn (BorrowRequest $row) => self::row($row, $canReview($row)))->all(),
            'pagination' => ['page' => $page, 'perPage' => self::PER_PAGE, 'total' => $total, 'lastPage' => $lastPage],
            'counts' => self::counts(clone $scope),
            'filters' => ['status' => $status, 'role' => $role, 'search' => $search],
        ];
    }

    private const RELATIONS = ['user.student', 'user.facultyMember', 'equipment.category'];

    /** Requests per overall status in the whole scope (NULL counts as Pending). */
    private static function counts(Builder $scope): array
    {
        $counts = ['Pending' => 0, 'Approved' => 0, 'Rejected' => 0, 'Cancelled' => 0, 'Expired' => 0];

        $scope->toBase()
            ->reorder()
            ->selectRaw("COALESCE(overall_status, 'Pending') as status_name, COUNT(*) as total")
            ->groupBy('status_name')
            ->get()
            ->each(function ($row) use (&$counts) {
                $counts[ucfirst((string) $row->status_name)] = (int) $row->total;
            });

        return $counts;
    }

    private static function applyStatus(Builder $query, string $status): void
    {
        $pending = fn ($q) => $q->whereNull('overall_status')->orWhere('overall_status', 'Pending');

        match ($status) {
            'all' => null,
            'pending' => $query->where($pending),
            'active' => $query->where(fn ($q) => $q
                ->where($pending)
                ->orWhere(fn ($approved) => $approved
                    ->where('overall_status', 'Approved')
                    ->where(fn ($loan) => $loan
                        ->doesntHave('transaction')
                        ->orWhereHas('transaction', fn ($t) => $t->whereIn('status', BorrowTransaction::OPEN))))),
            default => $query->where('overall_status', ucfirst($status)),
        };
    }

    /**
     * Every word must match the borrower's name or email, the equipment's
     * name, or the request number (e.g. "juan projector", "#12").
     */
    private static function applySearch(Builder $query, string $search): void
    {
        foreach (preg_split('/\s+/', $search) as $word) {
            $word = str_replace(['%', '_'], '', ltrim($word, '#'));
            if ($word === '') {
                continue;
            }
            $like = '%' . $word . '%';

            $query->where(function ($q) use ($word, $like) {
                $q->whereHas('equipment', fn ($e) => $e->where('name', 'like', $like))
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $like)
                        ->orWhereHas('student', fn ($s) => $s->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like))
                        ->orWhereHas('facultyMember', fn ($f) => $f->where('first_name', 'like', $like)->orWhere('last_name', 'like', $like)));

                if (ctype_digit($word)) {
                    $q->orWhere('request_id', (int) $word);
                }
            });
        }
    }

    private static function row(BorrowRequest $row, bool $canReview): array
    {
        $u = $row->user;
        $fullName = $u?->fullName() ?: ($u?->email ?? '');
        $bDateRaw = $row->borrow_date ?: $row->date_needed;
        $dDateRaw = $row->due_date ?: $row->return_date;

        $avatar = trim((string) ($u?->profile_image ?? ''));
        if ($avatar === '') {
            $avatar = Avatar::url($fullName ?: 'User');
        }

        $eqImg = $row->equipment?->imageUrl() ?? asset('images/EquipTrack_logo.png');

        $fmt = fn ($v) => $v ? Carbon::parse($v)->format('M d, Y') : 'N/A';

        return [
            'id' => (int) $row->request_id,
            'user_id' => (int) $row->user_id,
            'user' => $fullName !== '' ? $fullName : 'Unknown User',
            'role' => ucfirst($u?->role ?? 'Student'),
            'email' => $u?->email ?? '',
            'avatar' => $avatar,
            'equipment_id' => (int) $row->equipment_id,
            'equipment' => $row->equipment?->name ?? 'Unknown Equipment',
            'category' => $row->equipment?->category?->category_name ?: 'General',
            'img' => $eqImg,
            'quantity' => (int) ($row->quantity ?? 1),
            'date' => $fmt($row->date_requested),
            'fullDate' => $row->date_requested ? Carbon::parse($row->date_requested)->format('M d, Y h:i A') : 'N/A',
            'borrowDate' => LoanTime::format($bDateRaw, 'N/A'),
            'dueDate' => LoanTime::format($dDateRaw, 'N/A'),
            'purpose' => $row->purpose ?: 'N/A',
            'instructor' => $row->instructor_name ?: 'N/A',
            'notes' => $row->notes ?: 'None',
            'status' => ucfirst($row->overall_status ?: 'Pending'),
            'adminStatus' => ucfirst($row->admin_status ?: 'Pending'),
            'deptStatus' => ucfirst($row->dept_status ?: 'Pending'),
            'classification' => $row->classificationLabel(),
            'requiresBothApprovals' => $row->requiresDualApproval(),
            'canReview' => $canReview,
            'rejectReason' => $row->reject_reason ?? '',
            // Its return deadline ("Nov 05, 2026 by 7:00 PM"): Pending requests expire after it.
            'expiresAt' => LoanTime::dueBy($dDateRaw, 'N/A'),
            'cancelledAt' => $row->cancelled_at
                ? $row->cancelled_at->copy()->timezone(config('app.display_timezone'))->format('M d, Y h:i A')
                : '',
        ];
    }
}
