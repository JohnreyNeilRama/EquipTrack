<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BorrowTransaction extends Model
{
    /** The user submitted the return; the department has not verified it yet. */
    public const RETURN_PENDING = 'Pending Confirmation';

    /** The department verified the physical return and inspected the equipment. */
    public const RETURN_CONFIRMED = 'Confirmed';

    /** The department found the return was not made / the information was wrong. */
    public const RETURN_REJECTED = 'Rejected';

    /** Conditions the department can record when inspecting a returned unit. */
    public const INSPECTED_CONDITIONS = ['Good', 'Damaged', 'Incomplete'];

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
        'return_status',
        'return_submitted_at',
        'reported_condition',
        'inspected_condition',
        'inspection_remarks',
        'return_photo',
        'return_reviewed_by',
        'return_reviewed_at',
        'return_reject_reason',
        'return_restores_stock',
    ];

    protected $casts = [
        'borrow_date' => 'date',
        'due_date' => 'date',
        'return_date' => 'date',
        'return_submitted_at' => 'datetime',
        'return_reviewed_at' => 'datetime',
        'return_restores_stock' => 'boolean',
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

    public function isReturnPending(): bool
    {
        return $this->return_status === self::RETURN_PENDING;
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
