<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
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
        'accessories_included',
        'category_id',
        'department_id',
    ];

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
