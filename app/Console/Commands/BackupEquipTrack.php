<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

/**
 * Backs up EquipTrack: the whole database and every uploaded file (profile
 * pictures, department logos, equipment photos, penalty proofs).
 *
 *     php artisan equiptrack:backup
 *
 * Writes a folder equiptrack-YYYYMMDD-HHMMSS under config equiptrack.backup.path
 * with database.sql and files/public + files/private (copies of
 * storage/app/public and storage/app/private), then deletes backups older than
 * equiptrack.backup.keep_days. Runs daily from routes/console.php.
 *
 * Restoring: import database.sql into an empty database (phpMyAdmin, HeidiSQL
 * or `mysql equiptrack_laravel < database.sql`) and copy files/public and
 * files/private back into storage/app.
 *
 * Exit code 1 when the backup failed; the half-written folder is removed.
 */
class BackupEquipTrack extends Command
{
    protected $signature = 'equiptrack:backup';

    protected $description = 'Back up the database and uploaded files';

    public function handle(): int
    {
        $root = rtrim((string) config('equiptrack.backup.path'), '\\/');
        $name = 'equiptrack-' . Carbon::now(config('app.display_timezone'))->format('Ymd-His');
        $folder = $root . DIRECTORY_SEPARATOR . $name;

        try {
            File::ensureDirectoryExists($folder);

            $this->dumpDatabase($folder . DIRECTORY_SEPARATOR . 'database.sql');
            $this->copyUploads($folder . DIRECTORY_SEPARATOR . 'files');
        } catch (\Throwable $e) {
            File::deleteDirectory($folder);
            Log::error('EquipTrack backup failed: ' . $e->getMessage());
            $this->error('Backup failed: ' . $e->getMessage());

            return self::FAILURE;
        }

        $removed = $this->pruneOldBackups($root, $name);

        $this->info('Backup saved to ' . $folder);
        if ($removed > 0) {
            $this->line('Removed ' . $removed . ' old ' . ($removed === 1 ? 'backup' : 'backups') . '.');
        }

        return self::SUCCESS;
    }

    private function dumpDatabase(string $target): void
    {
        $connection = config('database.default');
        $db = config("database.connections.{$connection}");

        match ($db['driver'] ?? null) {
            'mysql', 'mariadb' => $this->dumpMysql($db, $target),
            'sqlite' => $this->copySqlite($db, $target),
            default => throw new \RuntimeException('Backups support MySQL, MariaDB and SQLite databases only.'),
        };
    }

    private function dumpMysql(array $db, string $target): void
    {
        $command = [
            (string) config('equiptrack.backup.mysqldump'),
            '--host=' . ($db['host'] ?? '127.0.0.1'),
            '--port=' . ($db['port'] ?? 3306),
            '--user=' . ($db['username'] ?? 'root'),
            '--single-transaction',
            '--routines',
            '--triggers',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
            '--result-file=' . $target,
            (string) $db['database'],
        ];

        // The password goes in the environment, not on the command line, so it
        // never shows in the list of running programs.
        $result = Process::env(['MYSQL_PWD' => (string) ($db['password'] ?? '')])
            ->timeout(600)
            ->run($command);

        if (!$result->successful()) {
            $output = trim($result->errorOutput() ?: $result->output());

            throw new \RuntimeException(
                'mysqldump did not finish' . ($output !== '' ? ': ' . $output : '.')
                . ' If mysqldump was not found, set BACKUP_MYSQLDUMP in .env to its full path.'
            );
        }

        if (!is_file($target) || filesize($target) === 0) {
            throw new \RuntimeException('mysqldump wrote an empty file.');
        }
    }

    private function copySqlite(array $db, string $target): void
    {
        $source = (string) ($db['database'] ?? '');

        if ($source === '' || $source === ':memory:' || !is_file($source)) {
            throw new \RuntimeException('The SQLite database file was not found.');
        }

        // Saved as a database file next to where database.sql would be.
        File::copy($source, preg_replace('/\.sql$/', '.sqlite', $target));
    }

    private function copyUploads(string $target): void
    {
        foreach (['public', 'private'] as $disk) {
            $source = storage_path('app/' . $disk);

            if (is_dir($source) && !File::copyDirectory($source, $target . DIRECTORY_SEPARATOR . $disk)) {
                throw new \RuntimeException('Could not copy storage/app/' . $disk . '.');
            }
        }
    }

    /** Deletes backup folders older than keep_days; returns how many. */
    private function pruneOldBackups(string $root, string $keep): int
    {
        $days = (int) config('equiptrack.backup.keep_days');
        if ($days <= 0) {
            return 0;
        }

        $cutoff = now()->subDays($days)->getTimestamp();
        $removed = 0;

        foreach (File::directories($root) as $dir) {
            $base = basename($dir);

            // Only folders this command made, never the one just written.
            if ($base === $keep || !preg_match('/^equiptrack-\d{8}-\d{6}$/', $base)) {
                continue;
            }

            if (filemtime($dir) < $cutoff && File::deleteDirectory($dir)) {
                $removed++;
            }
        }

        return $removed;
    }
}
