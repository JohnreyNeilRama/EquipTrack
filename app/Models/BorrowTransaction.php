<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class BorrowTransaction extends Model
{
    /** Approved; the unit is held for the borrower but not handed over yet. */
    public const STATUS_READY = 'Ready for Pickup';

    /** Released to the borrower (Lab Personnel handed it over). */
    public const STATUS_ACTIVE = 'Active';

    public const STATUS_OVERDUE = 'Overdue';

    public const STATUS_RETURNED = 'Returned';

    /** The borrower never picked it up; the held unit went back to stock. */
    public const STATUS_NOT_CLAIMED = 'Not Claimed';

    /** Statuses of a loan the borrower actually has (released, not returned yet). */
    public const ON_LOAN = [self::STATUS_ACTIVE, self::STATUS_OVERDUE];

    /** Statuses that still tie up a unit: held for pickup or out on loan. */
    public const OPEN = [self::STATUS_READY, self::STATUS_ACTIVE, self::STATUS_OVERDUE];

    /** The user submitted the return; the department has not verified it yet. */
    public const RETURN_PENDING = 'Pending Confirmation';

    /** The department verified the physical return and inspected the equipment. */
    public const RETURN_CONFIRMED = 'Confirmed';

    /** The department found the return was not made / the information was wrong. */
    public const RETURN_REJECTED = 'Rejected';

    /**
     * A loan is "due soon" from this many days before its due date until the
     * due date itself (dashboard reminders and the notification bell).
     */
    public const DUE_SOON_DAYS = 3;

    /** Conditions the department can record when inspecting a returned unit. */
    public const INSPECTED_CONDITIONS = ['Good', 'Damaged', 'Incomplete', 'Lost'];

    /**
     * Today's date in the Philippines, for due/overdue checks. The app clock
     * is UTC, where the date only changes at 8:00 AM Philippine time, but due
     * dates are Philippine dates. Returned at midnight in the app timezone,
     * like the date columns (due_date, borrow_date, …) are read, so the two
     * compare and count days directly.
     */
    public static function today(): Carbon
    {
        return Carbon::parse(Carbon::now(config('app.display_timezone'))->toDateString());
    }

    protected $table = 'borrow_transaction';
    protected $primaryKey = 'transaction_id';
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'borrow_date',
        'due_date',
        'return_date',
        'condition_on_return',
        'status',
        'released_at',
        'released_by',
        'unclaimed_at',
        'return_status',
        'return_submitted_at',
        'reported_condition',
        'inspected_condition',
        'inspection_remarks',
        'return_reviewed_by',
        'return_reviewed_at',
        'return_reject_reason',
        'return_restores_stock',
        'due_reminder_sent_at',
        'overdue_reminder_sent_at',
    ];

    protected $casts = [
        'borrow_date' => 'date',
        'due_date' => 'date',
        'return_date' => 'date',
        'released_at' => 'datetime',
        'unclaimed_at' => 'datetime',
        'return_submitted_at' => 'datetime',
        'return_reviewed_at' => 'datetime',
        'return_restores_stock' => 'boolean',
        'due_reminder_sent_at' => 'datetime',
        'overdue_reminder_sent_at' => 'datetime',
    ];

    public function request()
    {
        return $this->belongsTo(BorrowRequest::class, 'request_id', 'request_id');
    }

    /** Department account that confirmed or rejected the return. */
    public function returnReviewer()
    {
        return $this->belongsTo(DepartmentAccount::class, 'return_reviewed_by', 'dept_acc_id');
    }

    /** Department account (Lab Personnel) that handed the item to the borrower. */
    public function releaser()
    {
        return $this->belongsTo(DepartmentAccount::class, 'released_by', 'dept_acc_id');
    }

    /** Loans the borrower actually has: released and not returned yet. */
    public function scopeOnLoan($query)
    {
        return $query->whereIn('status', self::ON_LOAN);
    }

    public function isReadyForPickup(): bool
    {
        return $this->status === self::STATUS_READY;
    }

    public function isNotClaimed(): bool
    {
        return $this->status === self::STATUS_NOT_CLAIMED;
    }

    /** Released to the borrower and not returned yet. */
    public function isOnLoan(): bool
    {
        return in_array($this->status, self::ON_LOAN, true);
    }

    public function isReturnPending(): bool
    {
        return $this->return_status === self::RETURN_PENDING;
    }

    public function penalties()
    {
        return $this->hasMany(Penalty::class, 'transaction_id', 'transaction_id');
    }

    /**
     * Whole days the item came back after its due date: counted to the return
     * date, else the day the user submitted the return, else today (still out).
     */
    public function daysLate(): int
    {
        if (!$this->due_date) {
            return 0;
        }

        $end = $this->return_date ?? $this->return_submitted_at ?? now();

        return max(0, (int) $this->due_date->copy()->startOfDay()->diffInDays($end->copy()->startOfDay(), false));
    }

    /**
     * Takes lost units out of the equipment's total quantity: they no longer
     * exist. Their stock was never restored, so the available quantity is only
     * kept within the new total. Call this inside a DB transaction.
     */
    public function removeLostUnits(BorrowRequest $borrow): void
    {
        $equipment = Equipment::where('equipment_id', $borrow->equipment_id)
            ->lockForUpdate()
            ->first();

        if (!$equipment) {
            return;
        }

        $equipment->total_qty = max(0, (int) $equipment->total_qty - max(1, (int) $borrow->quantity));
        $equipment->available_qty = min((int) $equipment->available_qty, (int) $equipment->total_qty);

        if ((int) $equipment->available_qty === 0 && $equipment->status === 'Available') {
            $equipment->status = 'Unavailable';
        }

        $equipment->save();
    }

    /**
     * Puts the borrowed units back into the equipment's available stock (never
     * above the total quantity) and revives stock-driven unavailability. Only
     * the department's return confirmation calls this.
     *
     * Call this inside a DB transaction.
     */
    public function restoreStock(BorrowRequest $borrow): void
    {
        $equipment = Equipment::where('equipment_id', $borrow->equipment_id)
            ->lockForUpdate()
            ->first();

        if (!$equipment) {
            return;
        }

        $equipment->available_qty = min(
            (int) $equipment->total_qty,
            (int) $equipment->available_qty + max(1, (int) $borrow->quantity)
        );

        // Only revive stock-driven unavailability; never override admin-set
        // states like On Hold / Under Maintenance.
        if ($equipment->status === 'Unavailable' && $equipment->available_qty > 0) {
            $equipment->status = 'Available';
        }

        $equipment->save();
    }
}
