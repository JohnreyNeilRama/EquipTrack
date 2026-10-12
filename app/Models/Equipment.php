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

    public function category()
    {
        return $this->belongsTo(EquipmentCategory::class, 'category_id', 'category_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function borrowRequests()
    {
        return $this->hasMany(BorrowRequest::class, 'equipment_id', 'equipment_id');
    }
}
