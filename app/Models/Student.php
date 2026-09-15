<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    protected $table = 'student';
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'id_number',
        'year_level',
        'address',
    ];

    public function account()
    {
        return $this->belongsTo(UserAccount::class, 'user_id', 'user_id');
    }
}
