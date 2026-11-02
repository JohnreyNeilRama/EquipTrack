<?php

namespace App\Models;

use App\Services\EquipmentAvailability;
use App\Support\LoanTime;
use Illuminate\Database\Eloquent\Model;

class BorrowRequest extends Model
{
    public const PARTY_ADMIN = 'admin';

    public const PARTY_DEPT = 'dept';

    /** overall_status of a request its borrower withdrew while it was Pending. */
    public const STATUS_CANCELLED = 'Cancelled';

    protected $table = 'borrow_request';
    protected $primaryKey = 'request_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'equipment_id',
        'quantity',
        'equipment_classification',
        'purpose',
        'instructor_name',
        'notes',
        'date_needed',
        'return_date',
        'due_date',
        'borrow_date',
        'admin_status',
        'admin_id',
        'admin_reviewed_at',
        'dept_status',
        'dept_acc_id',
        'dept_reviewed_at',
        'overall_status',
        'reject_reason',
        'cancelled_at',
    ];

    // Borrow and due dates (Philippine wall-clock time, see App\Support\LoanTime).
    protected $casts = [
        'date_needed' => 'datetime',
        'return_date' => 'datetime',
        'due_date' => 'datetime',
        'borrow_date' => 'datetime',
        'admin_reviewed_at' => 'datetime',
        'dept_reviewed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    /** Due dates are stored at the return deadline of that day. */
    public function setDueDateAttribute($value): void
    {
        $this->attributes['due_date'] = $value === null ? null : $this->fromDateTime(LoanTime::dueValue($value));
    }

    /** The planned return, like the due date: at the return deadline of that day. */
    public function setReturnDateAttribute($value): void
    {
        $this->attributes['return_date'] = $value === null ? null : $this->fromDateTime(LoanTime::dueValue($value));
    }

    public function user()
    {
        return $this->belongsTo(UserAccount::class, 'user_id', 'user_id');
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id', 'equipment_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'admin_id');
    }

    public function departmentAccount()
    {
        return $this->belongsTo(DepartmentAccount::class, 'dept_acc_id', 'dept_acc_id');
    }

    public function transaction()
    {
        return $this->hasOne(BorrowTransaction::class, 'request_id', 'request_id');
    }

    /** Rejected or Cancelled: no one can approve or reject it any more. */
    public function isClosed(): bool
    {
        return in_array($this->overall_status, ['Rejected', self::STATUS_CANCELLED], true);
    }

    /**
     * The borrower can cancel while the request is still Pending and no loan
     * exists yet (an Expensive request approved by only one party still counts).
     */
    public function canBeCancelled(): bool
    {
        return ($this->overall_status ?: 'Pending') === 'Pending' && !$this->transaction()->exists();
    }

    /**
     * Expensive equipment needs both the admin and the department to approve.
     * The classification is the one copied when the request was submitted;
     * requests from before classifications existed (NULL) and Not Expensive
     * requests are handled by the department alone.
     */
    public function requiresDualApproval(): bool
    {
        return $this->equipment_classification === Equipment::CLASS_EXPENSIVE;
    }

    /** Classification this request was submitted under (NULL counts as Not Expensive). */
    public function classificationLabel(): string
    {
        return $this->requiresDualApproval() ? Equipment::CLASS_EXPENSIVE : Equipment::CLASS_NOT_EXPENSIVE;
    }

    /**
     * Whether the admin handles this request. Not Expensive requests (and
     * requests from before classifications existed) belong to the department
     * alone; only Expensive requests go to the admin as well.
     */
    public function adminCanReview(): bool
    {
        return $this->requiresDualApproval();
    }

    /**
     * Requests listed on the admin's side: Expensive requests, plus any request
     * the admin already reviewed before the classification workflow existed,
     * so the admin's own past decisions stay in the history.
     */
    public function scopeVisibleToAdmin($query)
    {
        return $query->where(fn ($q) => $q
            ->where('equipment_classification', Equipment::CLASS_EXPENSIVE)
            ->orWhereIn('admin_status', ['Approved', 'Rejected']));
    }

    /**
     * Whether an approval by $party makes the request fully approved.
     *
     *  - Not Expensive: the department's approval completes it (the admin
     *    cannot review these requests at all).
     *  - Expensive: only when the other party has already approved; otherwise
     *    the approval is recorded and the request stays Pending.
     */
    public function approvalCompletes(string $party): bool
    {
        if (!$this->requiresDualApproval()) {
            return $party === self::PARTY_DEPT;
        }

        $otherStatus = $party === self::PARTY_ADMIN ? $this->dept_status : $this->admin_status;

        return $otherStatus === 'Approved';
    }

    /**
     * Reserves the equipment for an approved request exactly once: the
     * borrow_transaction is created as Ready for Pickup, which blocks the
     * requested dates for everyone else (EquipmentAvailability). Stock is not
     * touched here: the unit leaves the available quantity when Lab Personnel
     * release it at the counter (DepartmentReleasesController).
     *
     * Approval is refused when every lendable unit is already reserved during
     * on one of the requested dates.
     *
     * Both the admin and the department approval workflows call this, so it is
     * idempotent: when a transaction already exists (the other approver already
     * approved), nothing is checked or created again.
     *
     * Call this inside a DB transaction while this request row is locked.
     *
     * @return array{code:int, message?:string}
     */
    public function activateLoan(): array
    {
        if ($this->transaction()->exists()) {
            return ['code' => 200];
        }

        // Locked so two approvals for the same item are checked one at a time.
        $equipment = Equipment::where('equipment_id', $this->equipment_id)
            ->lockForUpdate()
            ->first();

        if (!$equipment) {
            return ['code' => 404, 'message' => 'The equipment for this request was not found.'];
        }

        // Borrow date and due date (at the return deadline).
        $from = ($this->borrow_date ?: $this->date_needed)->copy();
        $to = ($this->due_date ?: $this->return_date ?: $from)->copy();
        $quantity = max(1, (int) $this->quantity);
        $availability = EquipmentAvailability::for($equipment, (int) $this->request_id);

        if (!$availability->isFree($from, $to, $quantity)) {
            return ['code' => 400, 'message' => $this->conflictMessage($equipment, $availability, $from, $to)];
        }

        BorrowTransaction::create([
            'request_id' => $this->request_id,
            'borrow_date' => $from->format('Y-m-d H:i:s'),
            'due_date' => $to->format('Y-m-d H:i:s'),
            'status' => BorrowTransaction::STATUS_READY,
        ]);

        return ['code' => 200];
    }

    /** Why the requested dates can't be approved, naming the clashing reservation when there is one unit. */
    private function conflictMessage(Equipment $equipment, EquipmentAvailability $availability, $from, $to): string
    {
        $lendable = $availability->lendableUnits();

        if ($lendable < 1) {
            return 'Cannot approve: ' . $equipment->name . ' has no units that can be lent right now.';
        }

        $conflicts = $availability->conflicts($from, $to);
        $first = $conflicts->first();
        // A booking's 'to' is the start of the day after its last day.
        $period = $first ? EquipmentAvailability::label($first['from'], $first['to']->copy()->subDay()) : EquipmentAvailability::label($from, $to);

        $subject = $lendable === 1
            ? 'the only unit is already reserved ' . $period . ($first ? ' (request #' . $first['request_id'] . ')' : '')
            : 'all ' . $lendable . ' units of ' . $equipment->name . ' are reserved on some of these dates';

        return 'Cannot approve: ' . $subject . '. Reject this request or ask the borrower for other dates.';
    }
}
