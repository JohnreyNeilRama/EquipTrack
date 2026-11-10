<?php

namespace App\Models;

use App\Support\LoanTime;
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

    /** Lab Personnel cancelled the reservation before it was handed over (cancel_reason says why). */
    public const STATUS_CANCELLED = 'Cancelled';

    /** Closed without ever reaching the borrower: nothing to return, the dates are free. */
    public const NEVER_RELEASED = [self::STATUS_NOT_CLAIMED, self::STATUS_CANCELLED];

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
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
        'return_status',
        'return_submitted_at',
        'reported_condition',
        'inspected_condition',
        'affected_qty',
        'inspection_remarks',
        'return_reviewed_by',
        'return_reviewed_at',
        'return_reject_reason',
        'return_restores_stock',
        'due_reminder_sent_at',
        'overdue_reminder_sent_at',
        'release_reminder_sent_at',
        'final_reminder_sent_at',
        'overdue_reminder_step',
    ];

    // Borrow and due dates (Philippine wall-clock time, see App\Support\LoanTime);
    // return_date is the day the unit came back.
    protected $casts = [
        'borrow_date' => 'datetime',
        'due_date' => 'datetime',
        'return_date' => 'date',
        'released_at' => 'datetime',
        'unclaimed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'return_submitted_at' => 'datetime',
        'return_reviewed_at' => 'datetime',
        'return_restores_stock' => 'boolean',
        'due_reminder_sent_at' => 'datetime',
        'overdue_reminder_sent_at' => 'datetime',
        'release_reminder_sent_at' => 'datetime',
        'final_reminder_sent_at' => 'datetime',
        'overdue_reminder_step' => 'integer',
    ];

    public function request()
    {
        return $this->belongsTo(BorrowRequest::class, 'request_id', 'request_id');
    }

    /** Due dates are stored at the return deadline of that day. */
    public function setDueDateAttribute($value): void
    {
        $this->attributes['due_date'] = $value === null ? null : $this->fromDateTime(LoanTime::dueValue($value));
    }

    /** Whether the due date's return deadline has passed (still out, not returned). */
    public function isOverdue(): bool
    {
        return $this->isOnLoan() && LoanTime::isOverdue($this->due_date);
    }

    /**
     * The borrower's oldest overdue loan, which blocks new borrow requests
     * until it is returned (config equiptrack.block_borrowing_with_overdue_item),
     * or null. A loan whose return the borrower already submitted (waiting
     * for the department's confirmation) no longer blocks; a rejected
     * return does, since the item is still out.
     */
    public static function blockingBorrowFor(int $userId): ?self
    {
        if (!config('equiptrack.block_borrowing_with_overdue_item', true)) {
            return null;
        }

        $now = LoanTime::now()->format('Y-m-d H:i:s');

        return self::with('request.equipment')
            ->onLoan()
            ->whereHas('request', fn ($q) => $q->where('user_id', $userId))
            ->where(fn ($q) => $q->where('due_date', '<', $now)->orWhere('status', self::STATUS_OVERDUE))
            ->where(fn ($q) => $q->whereNull('return_status')->orWhere('return_status', '!=', self::RETURN_PENDING))
            ->orderBy('due_date')
            ->first();
    }

    /** Why new requests are blocked, for the Available Equipment page and the request form. */
    public function borrowBlockMessage(): string
    {
        $equipment = $this->request?->equipment?->name ?? 'a borrowed item';

        return 'You have overdue equipment: ' . $equipment . ' was due ' . LoanTime::dueBy($this->due_date)
            . '. Return it first; you can borrow again once it is back.';
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

    /** Lab Personnel account that cancelled the reservation. */
    public function canceller()
    {
        return $this->belongsTo(DepartmentAccount::class, 'cancelled_by', 'dept_acc_id');
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

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Units in the inspected condition when the return was Damaged, Incomplete
     * or Lost: affected_qty, or every unit of the loan for older records.
     * 0 for Good returns.
     */
    public function affectedUnits(): int
    {
        if (!$this->inspected_condition || $this->inspected_condition === 'Good') {
            return 0;
        }

        $quantity = max(1, (int) ($this->request?->quantity ?? 1));

        return $this->affected_qty !== null ? min($quantity, max(1, (int) $this->affected_qty)) : $quantity;
    }

    /** Not Claimed or Cancelled: closed before it was handed over. */
    public function wasNeverReleased(): bool
    {
        return in_array($this->status, self::NEVER_RELEASED, true);
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
     * Days the item came back after its due date's return deadline, any part
     * of a day counting as a day: counted to the moment the user handed it in,
     * else the return date (a return on the due date is on time), else now.
     */
    public function daysLate(): int
    {
        $end = match (true) {
            $this->return_submitted_at !== null => LoanTime::fromInstant($this->return_submitted_at),
            $this->return_date !== null => LoanTime::dueOn($this->return_date->toDateString()),
            default => LoanTime::now(),
        };

        return LoanTime::daysLate($this->due_date, $end);
    }

    /**
     * Takes lost units out of the equipment's total quantity: they no longer
     * exist. Their stock was never restored, so the available quantity is only
     * kept within the new total. Call this inside a DB transaction.
     */
    public function removeLostUnits(BorrowRequest $borrow, ?int $units = null): void
    {
        $equipment = Equipment::where('equipment_id', $borrow->equipment_id)
            ->lockForUpdate()
            ->first();

        if (!$equipment) {
            return;
        }

        $equipment->total_qty = max(0, (int) $equipment->total_qty - ($units ?? max(1, (int) $borrow->quantity)));
        $equipment->available_qty = min((int) $equipment->available_qty, (int) $equipment->total_qty);

        // The status is the admin's to set; running out of units no longer
        // changes it (dates decide whether the item can be borrowed).
        $equipment->save();
    }

    /**
     * Takes the released units out of the equipment's available stock: they
     * are now with the borrower. Returns false (nothing changed) when not
     * enough units are in the lab, e.g. the previous borrower hasn't returned
     * it yet. Call this inside a DB transaction.
     */
    public function takeStock(BorrowRequest $borrow): bool
    {
        $equipment = Equipment::where('equipment_id', $borrow->equipment_id)
            ->lockForUpdate()
            ->first();

        $units = max(1, (int) $borrow->quantity);

        if (!$equipment || (int) $equipment->available_qty < $units) {
            return false;
        }

        $equipment->available_qty = (int) $equipment->available_qty - $units;
        $equipment->save();

        return true;
    }

    /**
     * Puts the borrowed units back into the equipment's available stock (never
     * above the total quantity): all of them, or $units when only some came
     * back Good. The status is left as the admin set it. Only the
     * department's return confirmation calls this.
     *
     * Call this inside a DB transaction.
     */
    public function restoreStock(BorrowRequest $borrow, ?int $units = null): void
    {
        $equipment = Equipment::where('equipment_id', $borrow->equipment_id)
            ->lockForUpdate()
            ->first();

        if (!$equipment) {
            return;
        }

        $equipment->available_qty = min(
            (int) $equipment->total_qty,
            (int) $equipment->available_qty + ($units ?? max(1, (int) $borrow->quantity))
        );

        $equipment->save();
    }
}
