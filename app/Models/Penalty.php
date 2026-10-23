<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A penalty recorded on a returned loan. EquipTrack only records it: payment
 * happens outside the system and staff mark it Settled or Waived.
 */
class Penalty extends Model
{
    public const TYPE_LATE = 'Late Return';

    public const TYPE_DAMAGED = 'Damaged';

    public const TYPE_INCOMPLETE = 'Incomplete';

    public const TYPE_LOST = 'Lost';

    public const TYPES = [self::TYPE_LATE, self::TYPE_DAMAGED, self::TYPE_INCOMPLETE, self::TYPE_LOST];

    public const STATUS_PENDING = 'Pending';

    public const STATUS_SETTLED = 'Settled';

    public const STATUS_WAIVED = 'Waived';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_SETTLED, self::STATUS_WAIVED];

    protected $table = 'penalty';
    protected $primaryKey = 'penalty_id';
    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'user_id',
        'type',
        'amount',
        'description',
        'status',
        'recorded_by_admin_id',
        'recorded_by_dept_acc_id',
        'recorded_at',
        'settled_by_admin_id',
        'settled_by_dept_acc_id',
        'settled_at',
        'reference_no',
        'settle_remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'recorded_at' => 'datetime',
        'settled_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(BorrowTransaction::class, 'transaction_id', 'transaction_id');
    }

    public function user()
    {
        return $this->belongsTo(UserAccount::class, 'user_id', 'user_id');
    }

    public function recordedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'recorded_by_admin_id', 'admin_id');
    }

    public function recordedByDept()
    {
        return $this->belongsTo(DepartmentAccount::class, 'recorded_by_dept_acc_id', 'dept_acc_id');
    }

    public function settledByAdmin()
    {
        return $this->belongsTo(Admin::class, 'settled_by_admin_id', 'admin_id');
    }

    public function settledByDept()
    {
        return $this->belongsTo(DepartmentAccount::class, 'settled_by_dept_acc_id', 'dept_acc_id');
    }

    /** Proof the user submitted, newest first. */
    public function submissions()
    {
        return $this->hasMany(PenaltySubmission::class, 'penalty_id', 'penalty_id')
            ->orderByDesc('submitted_at')
            ->orderByDesc('submission_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /** Penalties on equipment owned by the given department. */
    public function scopeForDepartment($query, int $departmentId)
    {
        return $query->whereHas('transaction.request.equipment', fn ($q) => $q->where('department_id', $departmentId));
    }

    /**
     * The user's oldest Pending penalty when unsettled penalties block new
     * borrow requests (config equiptrack.block_borrowing_with_pending_penalty).
     */
    public static function blockingBorrowFor(int $userId): ?self
    {
        if (!config('equiptrack.block_borrowing_with_pending_penalty', true)) {
            return null;
        }

        return self::with('transaction.request.equipment')
            ->where('user_id', $userId)
            ->where('status', self::STATUS_PENDING)
            ->orderBy('recorded_at')
            ->first();
    }

    /** Why the user cannot borrow, for the equipment page and the request endpoint. */
    public function borrowBlockMessage(): string
    {
        $equipment = $this->transaction?->request?->equipment?->name ?? 'a returned item';

        return 'You have an unsettled ' . $this->type . ' penalty for ' . $equipment
            . '. Settle it, then submit your proof on My Penalties; you can borrow again once it is verified.';
    }

    /** "₱150.00", or null for a non-monetary penalty. */
    public function amountLabel(): ?string
    {
        return $this->amount !== null ? '₱' . number_format((float) $this->amount, 2) : null;
    }
}
