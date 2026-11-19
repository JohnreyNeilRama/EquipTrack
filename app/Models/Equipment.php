<?php

namespace App\Models;

use App\Models\Concerns\BelongsToDepartment;
use App\Services\EquipmentUnits;
use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    use BelongsToDepartment;

    /** Which department owns the record: see BelongsToDepartment. */
    protected static function departmentPath(): string
    {
        return '';
    }

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

    /**
     * A serial number given to the item itself (older clients and tests send
     * one with typed quantities). Serial numbers belong to units, so it is
     * put on the item's first unit when the item is saved.
     */
    private ?string $pendingSerial = null;

    /**
     * Units follow the quantities: a new item gets its units (the first one
     * with a serial number given to the item; units beyond the available
     * quantity Under Maintenance), and quantities typed on the equipment form
     * are matched by adding, removing or setting aside units. When stock
     * moves (release, return, lost), the units move and the counts follow
     * them (App\Services\EquipmentUnits).
     */
    protected static function booted(): void
    {
        static::created(function (Equipment $equipment) {
            $total = max(0, (int) $equipment->total_qty);
            $available = min($total, max(0, (int) $equipment->available_qty));
            $serial = $equipment->pendingSerial;
            $equipment->pendingSerial = null;

            EquipmentUnits::create($equipment, $available, EquipmentUnit::STATUS_AVAILABLE, $serial);
            EquipmentUnits::create($equipment, $total - $available, EquipmentUnit::STATUS_MAINTENANCE, $available === 0 ? $serial : null);
        });

        static::updated(function (Equipment $equipment) {
            if (EquipmentUnits::isSyncing()) {
                return;
            }

            if ($equipment->wasChanged(['total_qty', 'available_qty'])) {
                EquipmentUnits::matchCounts($equipment, (int) $equipment->total_qty, (int) $equipment->available_qty);
            }
        });

        // A serial number given to an existing item goes on its first unit
        // (its own code, sent back unchanged, means "no serial number").
        static::saved(function (Equipment $equipment) {
            if ($equipment->pendingSerial === null) {
                return;
            }
            $serial = $equipment->pendingSerial;
            $equipment->pendingSerial = null;

            $first = $equipment->units()->get()->sortBy(fn ($u) => EquipmentUnit::numberOf($u->unit_code))->first();
            if ($first && strcasecmp($serial, $first->unit_code) !== 0 && $serial !== $first->serial_number) {
                $first->update(['serial_number' => $serial !== '' ? $serial : null]);
            }
        });
    }

    /** Older clients' item serial number: kept for the first unit (see booted()). */
    public function setSerialNumberAttribute($value): void
    {
        $this->pendingSerial = trim((string) $value);
    }

    /**
     * The item's first unit's serial number, else its code: how older pages
     * and messages name the item. Units carry the real serial numbers.
     */
    public function getSerialNumberAttribute(): ?string
    {
        if (!$this->exists) {
            return $this->pendingSerial;
        }

        $units = $this->relationLoaded('units') ? $this->units : $this->units()->get();
        $first = $units->sortBy([
            fn ($a, $b) => (int) in_array($a->status, EquipmentUnit::GONE, true) <=> (int) in_array($b->status, EquipmentUnit::GONE, true),
            fn ($a, $b) => EquipmentUnit::numberOf($a->unit_code) <=> EquipmentUnit::numberOf($b->unit_code),
        ])->first();

        return $first?->label();
    }

    /**
     * The serial numbers (else codes) of the units still in use, e.g.
     * "SN-1, SN-2, EQ-4-003 +2 more". Load the units first for lists.
     */
    public function unitLabels(int $max = 3): string
    {
        $labels = ($this->relationLoaded('units') ? $this->units : $this->units()->get())
            ->reject(fn ($u) => in_array($u->status, EquipmentUnit::GONE, true))
            ->sortBy(fn ($u) => EquipmentUnit::numberOf($u->unit_code))
            ->map(fn ($u) => $u->label())
            ->values();

        return $labels->take($max)->implode(', ') . ($labels->count() > $max ? ' +' . ($labels->count() - $max) . ' more' : '');
    }

    /** Every unit's serial number and code, for searching. */
    public function unitSearchText(): string
    {
        return ($this->relationLoaded('units') ? $this->units : $this->units()->get())
            ->flatMap(fn ($u) => array_filter([$u->serial_number, $u->unit_code]))
            ->implode(' ');
    }

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

    /** The item's physical units, each with its own code, serial number and status. */
    public function units()
    {
        return $this->hasMany(EquipmentUnit::class, 'equipment_id', 'equipment_id');
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
