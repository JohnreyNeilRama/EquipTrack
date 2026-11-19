<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One physical unit of an equipment item (e.g. one of five projectors).
 *
 * Every unit has an automatic code (EQ-{equipment_id}-{number}, e.g.
 * EQ-12-003) and, when it has one, its own serial number. Expensive units
 * need a serial number; Not Expensive units may have none.
 *
 * The equipment's available_qty and total_qty are counted from its units
 * (App\Services\EquipmentUnits::syncCounts): total = units that still exist
 * (not Lost or Retired), available = units in the lab and ready to lend.
 */
class EquipmentUnit extends Model
{
    /** In the lab and ready to lend. */
    public const STATUS_AVAILABLE = 'Available';

    /** Released to a borrower (see borrow_transaction_unit). */
    public const STATUS_ON_LOAN = 'On Loan';

    /** Kept out of stock: returned damaged or incomplete, or set aside to be checked. */
    public const STATUS_MAINTENANCE = 'Under Maintenance';

    /** Lost by a borrower: no longer counted. */
    public const STATUS_LOST = 'Lost';

    /** Permanently out of use, kept with its history. */
    public const STATUS_RETIRED = 'Retired';

    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_ON_LOAN,
        self::STATUS_MAINTENANCE,
        self::STATUS_LOST,
        self::STATUS_RETIRED,
    ];

    /** Units that no longer count towards the equipment's total quantity. */
    public const GONE = [self::STATUS_LOST, self::STATUS_RETIRED];

    /** Remark on units that the conversion to units could not account for. */
    public const CHECK_REMARK = 'Check this unit (set when units were added to EquipTrack)';

    protected $table = 'equipment_unit';
    protected $primaryKey = 'unit_id';
    public $timestamps = false;

    protected $fillable = [
        'equipment_id',
        'unit_code',
        'serial_number',
        'status',
        'remarks',
        'date_added',
    ];

    protected $casts = [
        'date_added' => 'datetime',
    ];

    /** The automatic code of unit number $number of an equipment item, e.g. EQ-12-003. */
    public static function codeFor(int $equipmentId, int $number): string
    {
        return 'EQ-' . $equipmentId . '-' . str_pad((string) $number, 3, '0', STR_PAD_LEFT);
    }

    /** The unit's number within its equipment item (3 for EQ-12-003). */
    public static function numberOf(string $code): int
    {
        return (int) substr($code, strrpos($code, '-') + 1);
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id', 'equipment_id');
    }

    /** Loans this unit was handed over on. */
    public function transactions()
    {
        return $this->belongsToMany(BorrowTransaction::class, 'borrow_transaction_unit', 'unit_id', 'transaction_id')
            ->withPivot('return_condition');
    }

    /**
     * An item's units as the Add / Edit Equipment window needs them (code
     * order). Load them with withExists('transactions as was_lent').
     */
    public static function forForm($units): array
    {
        return collect($units)
            ->sortBy(fn (self $u) => self::numberOf($u->unit_code))
            ->map(fn (self $u) => [
                'unit_id' => (int) $u->unit_id,
                'unit_code' => $u->unit_code,
                'serial_number' => $u->serial_number,
                'status' => $u->status,
                'remarks' => $u->remarks,
                'was_lent' => (bool) $u->was_lent,
            ])
            ->values()
            ->all();
    }

    /** The serial number, or the automatic code when the unit has none. */
    public function label(): string
    {
        return $this->serial_number ?: $this->unit_code;
    }
}
