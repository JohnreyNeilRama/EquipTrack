<?php

namespace App\Services;

use App\Models\BorrowRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Rows for the shared Requests table (resources/views/partials/requests),
 * used by both the Admin Requests page and the Department Borrow Requests
 * page so the two tables show exactly the same information.
 */
class RequestTableRows
{
    /**
     * @param  callable(BorrowRequest): bool  $canReview  whether the viewer's role may approve/reject this request
     */
    public static function fetch(Builder $query, callable $canReview): array
    {
        $rows = $query->with([
            'user.student',
            'user.facultyMember',
            'equipment.category',
        ])->orderByDesc('request_id')->get();

        return $rows->map(fn (BorrowRequest $row) => self::row($row, $canReview($row)))->all();
    }

    private static function row(BorrowRequest $row, bool $canReview): array
    {
        $u = $row->user;
        $fullName = $u?->fullName() ?: ($u?->email ?? '');
        $bDateRaw = $row->borrow_date ?: $row->date_needed;
        $dDateRaw = $row->due_date ?: $row->return_date;

        $avatar = trim((string) ($u?->profile_image ?? ''));
        if ($avatar === '') {
            $avatar = 'https://ui-avatars.com/api/?name=' . urlencode($fullName ?: 'User') . '&background=385585&color=fff';
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
            'borrowDate' => $fmt($bDateRaw),
            'dueDate' => $fmt($dDateRaw),
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
        ];
    }
}
