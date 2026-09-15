<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BorrowTransaction extends Model
{
    protected $table = 'borrow_transaction';
    protected $primaryKey = 'transaction_id';
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'borrow_date',
        'due_date',
        'return_date',
        'condition_on_return',
        'status',
        'penalty_amount',
        'penalty_status',
    ];

    protected $casts = [
        'borrow_date' => 'date',
        'due_date' => 'date',
        'return_date' => 'date',
        'penalty_amount' => 'decimal:2',
    ];

    public function request()
    {
        return $this->belongsTo(BorrowRequest::class, 'request_id', 'request_id');
    }
}
