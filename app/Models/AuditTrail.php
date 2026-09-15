<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTrail extends Model
{
    protected $table = 'audit_trail';
    protected $primaryKey = 'audit_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'admin_id',
        'dept_acc_id',
        'action',
        'ip_address',
        'affected_entity',
        'details',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(UserAccount::class, 'user_id', 'user_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'admin_id');
    }

    public function departmentAccount()
    {
        return $this->belongsTo(DepartmentAccount::class, 'dept_acc_id', 'dept_acc_id');
    }
}
