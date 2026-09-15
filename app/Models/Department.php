<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $table = 'department';
    protected $primaryKey = 'department_id';
    public $timestamps = false;

    protected $fillable = [
        'department_name',
        'department_code',
        'college',
        'department_head',
        'profile_image',
    ];

    public function accounts()
    {
        return $this->hasMany(DepartmentAccount::class, 'department_id', 'department_id');
    }

    public function equipment()
    {
        return $this->hasMany(Equipment::class, 'department_id', 'department_id');
    }
}
