<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A return reminder that Admin or Lab Personnel sent by hand (the "Remind"
 * button on Equipment Monitoring). sent_by_type is 'admin' or 'dept'.
 * At most one per loan per day (App\Services\ManualLoanReminder).
 */
class LoanManualReminder extends Model
{
    protected $table = 'loan_manual_reminders';
    public $timestamps = false;

    protected $fillable = [
        'transaction_id',
        'sent_by_type',
        'sent_by_id',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function transaction()
    {
        return $this->belongsTo(BorrowTransaction::class, 'transaction_id', 'transaction_id');
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class, 'sent_by_id', 'admin_id');
    }

    public function departmentAccount()
    {
        return $this->belongsTo(DepartmentAccount::class, 'sent_by_id', 'dept_acc_id');
    }

    /** "Admin Maria Santos" / "Lab Custodian (Lab Personnel)" for messages. */
    public function senderLabel(): string
    {
        if ($this->sent_by_type === 'admin') {
            $name = trim((string) $this->admin?->name);

            return $name !== '' ? 'Admin ' . $name : 'The admin';
        }

        $name = trim((string) $this->departmentAccount?->full_name);

        return $name !== '' ? $name . ' (Lab Personnel)' : 'Lab Personnel';
    }
}
