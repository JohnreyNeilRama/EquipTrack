<?php

namespace App\Console\Commands;

use App\Models\AuditTrail;
use App\Models\Penalty;
use App\Models\PenaltySubmissionFile;
use App\Services\AuditCatalog;
use App\Services\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Keeps the database and storage from growing forever (config
 * equiptrack.retention):
 *
 * - Audit trail entries older than audit_keep_days are written to a CSV file
 *   in archive_path (audit-trail-YYYYMMDD-HHMMSS.csv), and only removed from
 *   the database once that file is complete.
 * - Penalty proof files (photos, receipts) are deleted proof_keep_days after
 *   their penalty was Settled or Waived. The proof record itself stays.
 *
 *     php artisan equiptrack:prune-old-records [--dry-run]
 *
 * Runs daily from routes/console.php, after the backup. Leaves one audit
 * entry ("Cleared old records") saying what it removed.
 */
class PruneOldRecords extends Command
{
    protected $signature = 'equiptrack:prune-old-records {--dry-run : Only count what would be removed}';

    protected $description = 'Archive and remove old audit entries and delete proof files of long-closed penalties';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        try {
            [$auditCount, $archive] = $this->pruneAuditTrail($dryRun);
            $proofCount = $this->pruneProofFiles($dryRun);
        } catch (\Throwable $e) {
            Log::error('EquipTrack record cleanup failed: ' . $e->getMessage());
            $this->error('Cleanup failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($dryRun) {
            $this->info("Would archive and remove {$auditCount} audit " . str('entry')->plural($auditCount)
                . " and delete {$proofCount} proof " . str('file')->plural($proofCount) . '.');

            return self::SUCCESS;
        }

        if ($auditCount > 0 || $proofCount > 0) {
            AuditLogger::log(
                'Cleared old records',
                'audit',
                $auditCount . ' audit ' . str('entry')->plural($auditCount)
                    . ($archive ? ' (archived to ' . basename($archive) . ')' : '')
                    . ', ' . $proofCount . ' proof ' . str('file')->plural($proofCount),
                [],
            );
        }

        $this->info("Archived and removed {$auditCount} audit " . str('entry')->plural($auditCount)
            . ($archive ? ' (' . $archive . ')' : '') . '.');
        $this->info("Deleted {$proofCount} proof " . str('file')->plural($proofCount) . '.');

        return self::SUCCESS;
    }

    /** @return array{0: int, 1: string|null} entries removed, archive file */
    private function pruneAuditTrail(bool $dryRun): array
    {
        $days = (int) config('equiptrack.retention.audit_keep_days');
        if ($days <= 0) {
            return [0, null];
        }

        $old = AuditTrail::query()->where('created_at', '<', now()->subDays($days));
        $count = (clone $old)->count();

        if ($dryRun || $count === 0) {
            return [$count, null];
        }

        $folder = rtrim((string) config('equiptrack.retention.archive_path'), '\\/');
        File::ensureDirectoryExists($folder);
        $file = $folder . DIRECTORY_SEPARATOR . 'audit-trail-'
            . Carbon::now(config('app.display_timezone'))->format('Ymd-His') . '.csv';

        $lastId = $this->writeArchive($file, clone $old);

        // Only what is safely in the file; anything newer stays.
        $removed = (clone $old)->where('audit_id', '<=', $lastId)->delete();

        return [$removed, $file];
    }

    /** Writes the rows to $file; returns the highest audit_id written. */
    private function writeArchive(string $file, $query): int
    {
        $out = fopen($file, 'wb');
        if ($out === false) {
            throw new \RuntimeException('Could not create ' . $file . '.');
        }

        $lastId = 0;

        try {
            // Byte order mark so Excel reads names with ñ and accents correctly.
            fwrite($out, "\xEF\xBB\xBF");
            $this->csvLine($out, ['Audit ID', 'Date and time', 'Done by', 'Role', 'Action', 'Record', 'Details', 'IP address']);

            $query->with(['admin', 'departmentAccount', 'user'])
                ->chunkById(500, function ($rows) use ($out, &$lastId) {
                    foreach ($rows as $log) {
                        [$name, $role] = $this->actor($log);
                        $this->csvLine($out, [
                            $log->audit_id,
                            $log->created_at?->format('Y-m-d H:i:s'),
                            $name,
                            $role,
                            $log->action,
                            $log->affected_entity,
                            $log->details,
                            $log->ip_address,
                        ]);
                        $lastId = max($lastId, (int) $log->audit_id);
                    }
                }, 'audit_id');
        } finally {
            fclose($out);
        }

        return $lastId;
    }

    private function csvLine($out, array $fields): void
    {
        // A leading = + - @ would make Excel run the cell as a formula.
        $fields = array_map(function ($value) {
            $value = (string) $value;

            return $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true) ? "'" . $value : $value;
        }, $fields);

        if (fputcsv($out, $fields, ',', '"', '') === false) {
            throw new \RuntimeException('Could not write the audit archive (is the disk full?).');
        }
    }

    /** @return array{0: string, 1: string} */
    private function actor(AuditTrail $log): array
    {
        return match (true) {
            (bool) $log->admin => [$log->admin->name ?: $log->admin->username, 'Administrator'],
            (bool) $log->departmentAccount => [$log->departmentAccount->full_name ?: $log->departmentAccount->email, 'Department'],
            (bool) $log->user => [$log->user->fullName() ?: $log->user->email, $log->user->role ?: 'User'],
            in_array(AuditCatalog::classify((string) $log->action)['label'], AuditCatalog::SIGN_IN_PROBLEMS, true) => ['Not signed in', 'Visitor'],
            in_array(AuditCatalog::classify((string) $log->action)['label'], AuditCatalog::SYSTEM_ACTIONS, true) => ['EquipTrack', 'System'],
            default => ['Deleted account', ''],
        };
    }

    /** Deletes proof files of penalties closed long ago; returns how many. */
    private function pruneProofFiles(bool $dryRun): int
    {
        $days = (int) config('equiptrack.retention.proof_keep_days');
        if ($days <= 0) {
            return 0;
        }

        $old = PenaltySubmissionFile::query()->whereHas('submission.penalty', fn ($q) => $q
            ->whereIn('status', [Penalty::STATUS_SETTLED, Penalty::STATUS_WAIVED])
            ->where('settled_at', '<', now()->subDays($days)));

        if ($dryRun) {
            return $old->count();
        }

        $disk = Storage::disk('local');
        $removed = 0;

        $old->chunkById(200, function ($files) use ($disk, &$removed) {
            foreach ($files as $file) {
                // Keep the row while the file is still there, so it can be retried.
                if ($disk->exists($file->path) && !$disk->delete($file->path)) {
                    Log::warning('Could not delete penalty proof file ' . $file->path);

                    continue;
                }

                $file->delete();
                $removed++;
            }
        }, 'file_id');

        return $removed;
    }
}
