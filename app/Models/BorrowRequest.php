<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BorrowRequest extends Model
{
    public const PARTY_ADMIN = 'admin';

    public const PARTY_DEPT = 'dept';

    protected $table = 'borrow_request';
    protected $primaryKey = 'request_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'equipment_id',
        'quantity',
        'equipment_classification',
        'purpose',
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
    ];

    protected $casts = [
        'date_needed' => 'date',
        'return_date' => 'date',
        'due_date' => 'date',
        'borrow_date' => 'date',
        'admin_reviewed_at' => 'datetime',
        'dept_reviewed_at' => 'datetime',
    ];

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
     * Activates the loan for an approved request exactly once: the equipment's
     * available quantity is decreased by the requested quantity (never below
     * zero), the equipment is flagged Unavailable when its stock runs out, and
     * the Active borrow_transaction is created.
     *
     * Both the admin and the department approval workflows call this, so it is
     * idempotent: when a transaction already exists (the other approver already
     * approved), the stock is left untouched and no second transaction is made.
     * Total quantity is never modified — it is the number of units owned.
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

        $equipment = Equipment::where('equipment_id', $this->equipment_id)
            ->lockForUpdate()
            ->first();

        if (!$equipment) {
            return ['code' => 404, 'message' => 'The equipment for this request was not found.'];
        }

        $quantity = max(1, (int) $this->quantity);
        $available = (int) $equipment->available_qty;

        if ($available < $quantity) {
            return ['code' => 400, 'message' => 'Cannot approve: this equipment has no available stock left.'];
        }

        // Available quantity stays within [0, total_qty].
        $equipment->available_qty = max(0, $available - $quantity);

        if ((int) $equipment->available_qty === 0) {
            $equipment->status = 'Unavailable';
        }

        $equipment->save();

        BorrowTransaction::create([
            'request_id' => $this->request_id,
            'borrow_date' => $this->borrow_date ?: $this->date_needed,
            'due_date' => $this->due_date ?: $this->return_date,
            'status' => 'Active',
        ]);

        return ['code' => 200];
    }
}
