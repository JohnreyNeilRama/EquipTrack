<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A user's proof that a penalty was settled outside the system (paid,
 * replaced or repaired). Staff approve it, which settles the penalty, or
 * reject it with a reason.
 */
class PenaltySubmission extends Model
{
    public const TYPE_PAYMENT = 'Payment';

    public const TYPE_REPLACEMENT = 'Replacement';

    public const TYPE_REPAIR = 'Repair';

    public const STATUS_REVIEW = 'Under Review';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_REJECTED = 'Rejected';

    /** Accepted proof files. */
    public const MAX_FILES = 3;

    public const MAX_FILE_KB = 5120;

    public const FILE_MIMES = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];

    protected $table = 'penalty_submission';
    protected $primaryKey = 'submission_id';
    public $timestamps = false;

    protected $fillable = [
        'penalty_id',
        'user_id',
        'type',
        'reference_no',
        'amount_paid',
        'settled_on',
        'notes',
        'status',
        'submitted_at',
        'reviewed_by_admin_id',
        'reviewed_by_dept_acc_id',
        'reviewed_at',
        'reject_reason',
        'replacement_received',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:2',
        'settled_on' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'replacement_received' => 'boolean',
    ];

    /**
     * How a penalty of the given type can be settled: paying always works;
     * replacing needs a damaged, incomplete or lost item, repairing a damaged
     * or incomplete one.
     */
    public static function typesFor(string $penaltyType): array
    {
        return match ($penaltyType) {
            Penalty::TYPE_LOST => [self::TYPE_PAYMENT, self::TYPE_REPLACEMENT],
            Penalty::TYPE_DAMAGED, Penalty::TYPE_INCOMPLETE => [self::TYPE_PAYMENT, self::TYPE_REPLACEMENT, self::TYPE_REPAIR],
            default => [self::TYPE_PAYMENT],
        };
    }

    public function penalty()
    {
        return $this->belongsTo(Penalty::class, 'penalty_id', 'penalty_id');
    }

    public function user()
    {
        return $this->belongsTo(UserAccount::class, 'user_id', 'user_id');
    }

    public function files()
    {
        return $this->hasMany(PenaltySubmissionFile::class, 'submission_id', 'submission_id');
    }

    public function reviewedByAdmin()
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id', 'admin_id');
    }

    public function reviewedByDept()
    {
        return $this->belongsTo(DepartmentAccount::class, 'reviewed_by_dept_acc_id', 'dept_acc_id');
    }

    public function isUnderReview(): bool
    {
        return $this->status === self::STATUS_REVIEW;
    }

    /** Replacement and repair need the item itself checked by staff. */
    public function needsItemInspection(): bool
    {
        return in_array($this->type, [self::TYPE_REPLACEMENT, self::TYPE_REPAIR], true);
    }

    /**
     * This submission as the pages show it. $fileRoute is the route that
     * serves a file to the viewer (it checks their permission).
     */
    public function toProofArray(string $fileRoute): array
    {
        $reviewer = $this->reviewedByAdmin
            ? ($this->reviewedByAdmin->name ?: 'Administrator') . ' (Admin)'
            : ($this->reviewedByDept ? ($this->reviewedByDept->full_name ?: $this->reviewedByDept->email) . ' (Lab Personnel)' : '—');

        // Stored in UTC; shown in Philippine time.
        $tz = config('app.display_timezone');

        return [
            'id' => (int) $this->submission_id,
            'type' => $this->type,
            'typeLabel' => $this->typeLabel(),
            'referenceNo' => $this->reference_no ?? '',
            'amountPaid' => $this->amount_paid !== null ? '₱' . number_format((float) $this->amount_paid, 2) : '',
            'settledOn' => $this->settled_on?->format('M d, Y') ?? '—',
            'notes' => $this->notes ?? '',
            'status' => $this->status,
            'submittedAt' => $this->submitted_at?->copy()->setTimezone($tz)->format('M d, Y h:i A') ?? '—',
            'reviewedBy' => $reviewer,
            'reviewedAt' => $this->reviewed_at?->copy()->setTimezone($tz)->format('M d, Y h:i A') ?? '—',
            'rejectReason' => $this->reject_reason ?? '',
            'needsInspection' => $this->needsItemInspection(),
            'files' => $this->files->map(fn (PenaltySubmissionFile $file) => [
                'url' => route($fileRoute, $file->file_id),
                'name' => $file->original_name,
                'isImage' => $file->isImage(),
                'size' => $file->size >= 1048576
                    ? number_format($file->size / 1048576, 1) . ' MB'
                    : max(1, (int) round($file->size / 1024)) . ' KB',
            ])->values()->all(),
        ];
    }

    /** "Paid", "Replaced the item", "Repaired the item". */
    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_REPLACEMENT => 'Replaced the item',
            self::TYPE_REPAIR => 'Repaired the item',
            default => 'Paid the penalty',
        };
    }
}
