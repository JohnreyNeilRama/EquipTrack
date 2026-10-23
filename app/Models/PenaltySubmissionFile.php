<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One uploaded receipt / photo of a penalty submission. Stored on the private
 * disk; served only through permission-checked routes.
 */
class PenaltySubmissionFile extends Model
{
    protected $table = 'penalty_submission_file';
    protected $primaryKey = 'file_id';
    public $timestamps = false;

    protected $fillable = [
        'submission_id',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    public function submission()
    {
        return $this->belongsTo(PenaltySubmission::class, 'submission_id', 'submission_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
