<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
 * The database is saved with mysqldump where PHP may start other programs,
 * and by PHP itself where it may not (shared hosting such as Hostinger turns
 * proc_open off); config equiptrack.backup.method = "php" forces the latter.
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
            'mysql', 'mariadb' => self::canRunPrograms() && config('equiptrack.backup.method') !== 'php'
                ? $this->dumpMysql($db, $target)
                : $this->dumpMysqlWithPhp($connection, $target),
            'sqlite' => $this->copySqlite($db, $target),
            default => throw new \RuntimeException('Backups support MySQL, MariaDB and SQLite databases only.'),
        };
    }

    /**
     * Whether PHP may start another program (mysqldump). Shared hosting such
     * as Hostinger switches proc_open off; the backup then writes the dump
     * with PHP itself (dumpMysqlWithPhp).
     */
    public static function canRunPrograms(): bool
    {
        $disabled = array_map('trim', explode(',', strtolower((string) ini_get('disable_functions'))));

        return function_exists('proc_open') && !in_array('proc_open', $disabled, true);
    }

    /**
     * The same kind of file mysqldump writes, made by PHP over the normal
     * database connection: for every table its CREATE TABLE statement and
     * its rows as INSERT statements (200 rows each), with foreign key checks
     * off while restoring. Rows are read one at a time, so large tables don't
     * fill the memory. Restore it like any .sql file (phpMyAdmin Import, or
     * `mysql equiptrack_laravel < database.sql`).
     */
    private function dumpMysqlWithPhp(string $connection, string $target): void
    {
        $db = DB::connection($connection);
        $pdo = $db->getPdo();
        $out = fopen($target, 'wb');

        if ($out === false) {
            throw new \RuntimeException('Could not create ' . $target . '.');
        }

        try {
            $write = function (string $text) use ($out) {
                if (fwrite($out, $text) === false) {
                    throw new \RuntimeException('Could not write the database backup (is the disk full?).');
                }
            };

            $write("-- EquipTrack database backup (made by PHP)\n"
                . '-- Database: ' . $db->getDatabaseName() . "\n"
                . '-- Created: ' . now(config('app.display_timezone'))->format('Y-m-d H:i:s') . " (Philippine time)\n\n"
                . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET UNIQUE_CHECKS = 0;\n"
                . "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n");

            $tables = array_map(
                fn ($row) => array_values((array) $row)[0],
                $db->select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"),
            );

            foreach ($tables as $table) {
                $quoted = '`' . str_replace('`', '``', $table) . '`';
                $create = array_values((array) $db->selectOne('SHOW CREATE TABLE ' . $quoted))[1];

                $write("-- Table {$table}\nDROP TABLE IF EXISTS {$quoted};\n{$create};\n\n");

                $columns = null;
                $batch = [];
                $flush = function () use (&$batch, &$columns, $quoted, $write) {
                    if ($batch) {
                        $write("INSERT INTO {$quoted} ({$columns}) VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                };

                foreach ($db->table($table)->cursor() as $row) {
                    $row = (array) $row;
                    $columns ??= implode(', ', array_map(fn ($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row)));
                    $batch[] = '(' . implode(', ', array_map(
                        fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value),
                        $row,
                    )) . ')';

                    if (count($batch) >= 200) {
                        $flush();
                    }
                }
                $flush();
                $write("\n");
            }

            $write("SET FOREIGN_KEY_CHECKS = 1;\nSET UNIQUE_CHECKS = 1;\n");
        } finally {
            fclose($out);
        }

        if (filesize($target) === 0) {
            throw new \RuntimeException('The database backup came out empty.');
        }
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
