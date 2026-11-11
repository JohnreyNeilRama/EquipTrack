<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    /** Requests need both the admin's and the department's approval. */
    public const CLASS_EXPENSIVE = 'Expensive';

    /** Requests are handled and approved by the department alone. */
    public const CLASS_NOT_EXPENSIVE = 'Not Expensive';

    public const CLASSIFICATIONS = [self::CLASS_EXPENSIVE, self::CLASS_NOT_EXPENSIVE];

    public const STATUS_AVAILABLE = 'Available';

    /**
     * Permanently out of the inventory: hidden from borrowers, left out of
     * stock counts and alerts, but kept with its borrowing history. Equipment
     * that was ever requested is retired instead of deleted.
     */
    public const STATUS_RETIRED = 'Retired';

    /** Every equipment.status value, in the order the forms list them. */
    public const STATUSES = [self::STATUS_AVAILABLE, 'Unavailable', 'On Hold', 'Under Maintenance', self::STATUS_RETIRED];

    protected $table = 'equipment';
    protected $primaryKey = 'equipment_id';
    public $timestamps = false;

    protected $fillable = [
        'name',
        'brand',
        'model',
        'serial_number',
        'image',
        'available_qty',
        'total_qty',
        'status',
        'classification',
        'accessories_included',
        'category_id',
        'department_id',
    ];

    /**
     * Public URL of the photo the admin uploaded in Equipment Management, or
     * null when the record has none. Uploaded files are stored as
     * "/storage/images/..."; full URLs and data URIs are used as they are.
     */
    public function imageUrl(): ?string
    {
        $raw = trim((string) $this->image);

        if ($raw === '') {
            return null;
        }

        return preg_match('/^(https?:\/\/|data:|\/storage\/)/i', $raw) ? $raw : asset(ltrim($raw, '/'));
    }

    /** Classification of this record; rows without one count as Not Expensive. */
    public function classificationLabel(): string
    {
        return in_array($this->classification, self::CLASSIFICATIONS, true)
            ? $this->classification
            : self::CLASS_NOT_EXPENSIVE;
    }

    /** High-value equipment: lent one unit per request (config: the Expensive classification). */
    public function isExpensive(): bool
    {
        return $this->classificationLabel() === self::CLASS_EXPENSIVE;
    }

    /**
     * Most units one request may ask for: 1 for Expensive equipment, otherwise
     * the units the lab can lend, within config equiptrack.max_units_per_request
     * (0 = no limit beyond stock).
     */
    public function maxUnitsPerRequest(int $lendable): int
    {
        if ($lendable < 1) {
            return 0;
        }
        if ($this->isExpensive()) {
            return 1;
        }

        $cap = (int) config('equiptrack.max_units_per_request', 0);

        return $cap > 0 ? min($lendable, $cap) : $lendable;
    }

    public function isRetired(): bool
    {
        return $this->status === self::STATUS_RETIRED;
    }

    /** Equipment still in the inventory (everything except Retired). */
    public function scopeInService($query)
    {
        return $query->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', self::STATUS_RETIRED));
    }

    /**
     * Whether any borrow request was ever made for this item. Such equipment
     * cannot be deleted (the database refuses too: ON DELETE RESTRICT), so its
     * history stays; it is retired instead.
     */
    public function hasBorrowHistory(): bool
    {
        return BorrowRequest::where('equipment_id', $this->equipment_id)->exists();
    }

    /** Why this item can't be deleted right now, or null when it can. */
    public function deleteBlocker(): ?string
    {
        if ($this->hasBorrowHistory()) {
            return $this->name . ' has borrowing history, so it can\'t be deleted. '
                . 'Set its status to Retired instead: it is hidden from borrowers and its history is kept.';
        }

        return null;
    }

    /**
     * Why this item can't be retired right now, or null when it can: a request
     * still waiting for review, or a unit reserved, on loan or not yet confirmed
     * as returned.
     */
    public function retireBlocker(): ?string
    {
        $pending = BorrowRequest::where('equipment_id', $this->equipment_id)
            ->where('overall_status', 'Pending')
            ->exists();

        if ($pending) {
            return $this->name . ' can\'t be retired while it has pending borrow requests. Approve or reject them first.';
        }

        $open = BorrowTransaction::whereIn('status', BorrowTransaction::OPEN)
            ->whereHas('request', fn ($q) => $q->where('equipment_id', $this->equipment_id))
            ->exists();

        if ($open) {
            return $this->name . ' can\'t be retired while a unit is reserved for pickup, on loan or overdue. '
                . 'Wait until it is returned (or the reservation is cancelled) first.';
        }

        return null;
    }

    public function category()
    {
        return $this->belongsTo(EquipmentCategory::class, 'category_id', 'category_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }
}
