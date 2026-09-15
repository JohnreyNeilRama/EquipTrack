<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EquipmentCategory extends Model
{
    protected $table = 'equipment_category';
    protected $primaryKey = 'category_id';
    public $timestamps = false;

    protected $fillable = ['category_name'];

    public function equipment()
    {
        return $this->hasMany(Equipment::class, 'category_id', 'category_id');
    }
}
