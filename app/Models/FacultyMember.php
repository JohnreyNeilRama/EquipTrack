<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacultyMember extends Model
{
    protected $table = 'faculty_member';
    protected $primaryKey = 'user_id';
    public $incrementing = false;
    protected $keyType = 'int';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'faculty_id_number',
        'teaching_license_no',
        'highest_educational_attainment',
        'address',
    ];

    public function account()
    {
        return $this->belongsTo(UserAccount::class, 'user_id', 'user_id');
    }
}
