<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

// One-time data migration from the legacy vanilla-PHP `equiptrack` database
// into the new `equiptrack_laravel` database. Converts base64 data-URI images
// into real files on the public disk and reports any non-bcrypt passwords.
class MigrateLegacyData extends Command
{
    protected $signature = 'equiptrack:migrate-legacy-data';

    protected $description = 'Copy data from the legacy equiptrack DB into the Laravel app, converting base64 images to storage files';

    // Tables in FK-safe copy order: [table, primary key, image columns]
    private array $tables = [
        ['department', 'department_id', ['profile_image']],
        ['admin', 'admin_id', ['profile_image']],
        ['department_account', 'dept_acc_id', ['profile_image']],
        ['user_account', 'user_id', ['profile_image']],
        ['student', 'user_id', []],
        ['faculty_member', 'user_id', []],
        ['equipment_category', 'category_id', []],
        ['equipment', 'equipment_id', ['image']],
        ['borrow_request', 'request_id', []],
        ['borrow_transaction', 'transaction_id', []],
        ['audit_trail', 'audit_id', []],
    ];

    public function handle(): int
    {
        // Register the legacy connection at runtime — it only exists for this job.
        config(['database.connections.legacy' => [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'equiptrack',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
        ]]);

        $legacy = DB::connection('legacy');

        if (!$legacy->getPdo()) {
            $this->error('Cannot connect to legacy database "equiptrack".');
            return self::FAILURE;
        }

        $this->info('Reading legacy database: equiptrack');

        // Wipe target tables (children first) so the command is idempotent.
        Schema::disableForeignKeyConstraints();
        foreach (array_reverse($this->tables) as [$table, , ]) {
            DB::table($table)->truncate();
        }
        Schema::enableForeignKeyConstraints();
        $this->warn('Truncated target tables.');

        $imageDir = 'images';
        if (!Storage::disk('public')->exists($imageDir)) {
            Storage::disk('public')->makeDirectory($imageDir);
        }

        $nonBcrypt = [];

        foreach ($this->tables as [$table, $pk, $imageCols]) {
            $rows = $legacy->table($table)->get();
            $count = 0;

            foreach ($rows as $row) {
                $row = (array) $row;

                // Password integrity report (we never rewrite hashes — they are portable).
                if (isset($row['password']) && !str_starts_with((string) $row['password'], '$2y$')) {
                    $nonBcrypt[] = $table . '#' . ($row[$pk] ?? '?');
                }

                // Base64 data URIs → files on the public disk.
                foreach ($imageCols as $col) {
                    if (!empty($row[$col]) && preg_match('/^data:image\/([a-z+]+);base64,(.+)$/is', $row[$col], $m)) {
                        $ext = match (strtolower($m[1])) {
                            'jpeg', 'jpg' => 'jpg',
                            'png' => 'png',
                            'webp' => 'webp',
                            'gif' => 'gif',
                            default => 'img',
                        };
                        $binary = base64_decode($m[2], true);
                        if ($binary === false) {
                            $this->line("  <fg=red>Un-decodable image in {$table}#{$row[$pk]}.{$col} — left as-is</>");
                            continue;
                        }
                        $filename = "{$imageDir}/{$table}-{$row[$pk]}-{$col}." . $ext;
                        Storage::disk('public')->put($filename, $binary);
                        $row[$col] = '/storage/' . $filename;
                    }
                }

                DB::table($table)->insert($row);
                $count++;
            }

            // Fix the auto-increment counter so new rows never collide with migrated IDs.
            // Syntax differs per driver: MySQL uses ALTER TABLE ... AUTO_INCREMENT,
            // Postgres uses its serial sequence via setval().
            $max = (int) DB::table($table)->max($pk);
            if ($max > 0) {
                $driver = DB::connection()->getDriverName();
                if ($driver === 'pgsql') {
                    DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', '{$pk}'), ?)", [$max]);
                } elseif ($driver === 'mysql' || $driver === 'mariadb') {
                    DB::statement("ALTER TABLE {$table} AUTO_INCREMENT = " . ($max + 1));
                }
                // sqlite: no separate sequence to reset, nothing to do.
            }

            $this->line("<fg=green>Copied</> {$table} ({$count} rows)");
        }

        if ($nonBcrypt !== []) {
            $this->warn('These accounts do NOT have bcrypt passwords and cannot log in until reset:');
            foreach ($nonBcrypt as $who) {
                $this->line('  - ' . $who);
            }
        } else {
            $this->info('All existing passwords are bcrypt hashes — portable to Laravel as-is.');
        }

        $this->info('Legacy data migration complete.');
        return self::SUCCESS;
    }
}
