<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BorrowRequest extends Model
{
    protected $table = 'borrow_request';
    protected $primaryKey = 'request_id';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'equipment_id',
        'quantity',
        'purpose',
        'notes',
        'date_needed',
        'return_date',
        'due_date',
        'borrow_date',
        'admin_status',
        'admin_id',
        'admin_reviewed_at',
        'dept_status',
        'dept_acc_id',
        'dept_reviewed_at',
        'overall_status',
        'reject_reason',
    ];

    protected $casts = [
        'date_needed' => 'date',
        'return_date' => 'date',
        'due_date' => 'date',
        'borrow_date' => 'date',
        'admin_reviewed_at' => 'datetime',
        'dept_reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(UserAccount::class, 'user_id', 'user_id');
    }

    public function equipment()
    {
        return $this->belongsTo(Equipment::class, 'equipment_id', 'equipment_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'admin_id', 'admin_id');
    }

    public function departmentAccount()
    {
        return $this->belongsTo(DepartmentAccount::class, 'dept_acc_id', 'dept_acc_id');
    }

    public function transaction()
    {
        return $this->hasOne(BorrowTransaction::class, 'request_id', 'request_id');
    }
}
