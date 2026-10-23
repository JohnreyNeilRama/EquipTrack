<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Penalty;
use App\Models\PenaltySubmission;
use App\Models\PenaltySubmissionFile;
use App\Models\UserAccount;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Proof of penalty settlement: a student / faculty member uploads proof that
 * a Pending penalty was paid, replaced or repaired outside the system, and the
 * equipment's department or the admin approves it (which settles the penalty
 * through PenaltyService, lifting the borrowing block) or rejects it with a
 * reason (the user can then submit again).
 *
 * Files go to the private disk under penalty-proofs/ and are only served
 * through permission-checked routes. Nothing here takes payments.
 *
 * $actor is AuditLogger::actorFor(...): ['admin_id' => ..] or ['dept_acc_id' => ..].
 */
class PenaltySubmissionService
{
    private const DISK = 'local';

    public function __construct(private PenaltyService $penalties)
    {
    }

    /**
     * @param  array{type: string, reference_no?: ?string, amount_paid?: mixed, settled_on: string, notes?: ?string}  $data
     * @param  UploadedFile[]  $files
     */
    public function submit(Penalty $penalty, UserAccount $user, array $data, array $files): PenaltySubmission|string
    {
        if ((int) $penalty->user_id !== (int) $user->user_id) {
            return 'This penalty is not yours.';
        }

        if (!in_array($data['type'], PenaltySubmission::typesFor($penalty->type), true)) {
            return 'That kind of proof does not apply to a ' . $penalty->type . ' penalty.';
        }

        $stored = [];

        try {
            return DB::transaction(function () use ($penalty, $user, $data, $files, &$stored) {
                $locked = Penalty::whereKey($penalty->penalty_id)->lockForUpdate()->first();

                if (!$locked || !$locked->isPending()) {
                    throw new RuntimeException('This penalty is no longer pending.');
                }

                if ($locked->submissions()->where('status', PenaltySubmission::STATUS_REVIEW)->exists()) {
                    throw new RuntimeException('Your proof for this penalty is already under review.');
                }

                $reference = trim((string) ($data['reference_no'] ?? ''));
                $notes = trim((string) ($data['notes'] ?? ''));

                $submission = PenaltySubmission::create([
                    'penalty_id' => $locked->penalty_id,
                    'user_id' => $user->user_id,
                    'type' => $data['type'],
                    'reference_no' => $reference !== '' ? $reference : null,
                    'amount_paid' => ($data['amount_paid'] ?? '') !== '' && $data['amount_paid'] !== null ? round((float) $data['amount_paid'], 2) : null,
                    'settled_on' => $data['settled_on'],
                    'notes' => $notes !== '' ? $notes : null,
                    'status' => PenaltySubmission::STATUS_REVIEW,
                    'submitted_at' => now(),
                ]);

                foreach ($files as $file) {
                    // Random names; the folder is per penalty so the files stay easy to find.
                    $path = $file->storeAs(
                        'penalty-proofs/' . $locked->penalty_id,
                        Str::random(32) . '.' . $file->extension(),
                        self::DISK,
                    );

                    if ($path === false) {
                        throw new RuntimeException('Your file could not be saved. Please try again.');
                    }

                    $stored[] = $path;

                    PenaltySubmissionFile::create([
                        'submission_id' => $submission->submission_id,
                        'path' => $path,
                        'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                        'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                        'size' => (int) $file->getSize(),
                    ]);
                }

                AuditLogger::log(
                    'Submitted proof for penalty #' . $locked->penalty_id . ' (' . $submission->type . ')',
                    'penalty:' . $locked->penalty_id,
                    trim(($reference !== '' ? 'Reference: ' . $reference . '. ' : '') . count($files) . ' file(s).'),
                    AuditLogger::actorFor('user', $user),
                );

                return $submission;
            });
        } catch (RuntimeException $e) {
            // Nothing was recorded, so remove any file that was already written.
            Storage::disk(self::DISK)->delete($stored);

            return $e->getMessage();
        }
    }

    /**
     * Approves proof under review and settles the penalty. Replacement and
     * repair proof needs $itemReceived (staff received and inspected the
     * item); an approved replacement of a Lost item puts the units back into
     * the equipment's stock.
     */
    public function approve(PenaltySubmission $submission, bool $itemReceived, array $actor): ?string
    {
        try {
            DB::transaction(function () use ($submission, $itemReceived, $actor) {
                $locked = PenaltySubmission::with('penalty.transaction.request')
                    ->whereKey($submission->submission_id)
                    ->lockForUpdate()
                    ->first();

                if (!$locked || !$locked->isUnderReview()) {
                    throw new RuntimeException('This proof has already been reviewed.');
                }

                if ($locked->needsItemInspection() && !$itemReceived) {
                    throw new RuntimeException('Confirm that the replacement or repaired item was received and inspected.');
                }

                $penalty = $locked->penalty;

                $locked->update([
                    'status' => PenaltySubmission::STATUS_APPROVED,
                    'reviewed_by_admin_id' => $actor['admin_id'] ?? null,
                    'reviewed_by_dept_acc_id' => $actor['dept_acc_id'] ?? null,
                    'reviewed_at' => now(),
                    'replacement_received' => $locked->needsItemInspection() && $itemReceived,
                ]);

                $error = $this->penalties->settle(
                    $penalty,
                    $locked->reference_no ?: 'Proof #' . $locked->submission_id,
                    'Approved proof #' . $locked->submission_id . ': ' . $locked->typeLabel() . '.',
                    $actor,
                );

                if ($error !== null) {
                    throw new RuntimeException($error);
                }

                if ($locked->type === PenaltySubmission::TYPE_REPLACEMENT && $penalty->type === Penalty::TYPE_LOST) {
                    $this->restockReplacedUnits($penalty);
                }

                AuditLogger::log(
                    'Approved proof for penalty #' . $penalty->penalty_id . ' (' . $locked->type . ')',
                    'penalty:' . $penalty->penalty_id,
                    $locked->needsItemInspection() ? 'Item received and inspected.' : null,
                    $actor,
                );
            });
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /** Rejects proof under review; the user can submit again. */
    public function reject(PenaltySubmission $submission, string $reason, array $actor): ?string
    {
        return DB::transaction(function () use ($submission, $reason, $actor) {
            $locked = PenaltySubmission::whereKey($submission->submission_id)->lockForUpdate()->first();

            if (!$locked || !$locked->isUnderReview()) {
                return 'This proof has already been reviewed.';
            }

            $locked->update([
                'status' => PenaltySubmission::STATUS_REJECTED,
                'reviewed_by_admin_id' => $actor['admin_id'] ?? null,
                'reviewed_by_dept_acc_id' => $actor['dept_acc_id'] ?? null,
                'reviewed_at' => now(),
                'reject_reason' => trim($reason),
            ]);

            AuditLogger::log(
                'Rejected proof for penalty #' . $locked->penalty_id . ' (' . $locked->type . ')',
                'penalty:' . $locked->penalty_id,
                'Reason: ' . trim($reason),
                $actor,
            );

            return null;
        });
    }

    /** A lost unit was replaced: it counts as owned and available again. */
    private function restockReplacedUnits(Penalty $penalty): void
    {
        $borrow = $penalty->transaction?->request;
        $equipment = $borrow
            ? Equipment::where('equipment_id', $borrow->equipment_id)->lockForUpdate()->first()
            : null;

        if (!$equipment) {
            return;
        }

        $units = max(1, (int) $borrow->quantity);
        $equipment->total_qty = (int) $equipment->total_qty + $units;
        $equipment->available_qty = min((int) $equipment->total_qty, (int) $equipment->available_qty + $units);

        if ($equipment->status === 'Unavailable' && $equipment->available_qty > 0) {
            $equipment->status = 'Available';
        }

        $equipment->save();
    }
}
