<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Before going live (and after changing .env on the server), checks the
 * settings that keep EquipTrack safe online: production mode, debug off,
 * HTTPS, secure session cookies, a real mail server, the database, the
 * storage link and writable folders. Read-only; it changes nothing.
 *
 *     php artisan equiptrack:check-production
 *
 * Exit code 1 when a required setting fails, so it can also run in a deploy
 * script.
 */
class CheckProduction extends Command
{
    protected $signature = 'equiptrack:check-production';

    protected $description = 'Check the live-server settings (production mode, debug off, HTTPS, cookies, mail, database, storage)';

    /** @var array<int, array{0: string, 1: string, 2: string}> */
    private array $rows = [];

    public function handle(): int
    {
        // The raw .env values: with config cached, env() no longer reads .env,
        // and in production the app forces debug off, so config('app.debug')
        // can't show a leftover APP_DEBUG=true.
        $env = $this->readEnvFile();

        $this->check(
            'APP_ENV is production',
            config('app.env') === 'production',
            'APP_ENV=' . config('app.env') . ' (set APP_ENV=production)',
        );

        $debug = strtolower(trim((string) ($env['APP_DEBUG'] ?? 'false'), '"\''));
        $this->check(
            'APP_DEBUG is false',
            in_array($debug, ['false', '0', '', '(false)'], true),
            'APP_DEBUG=' . ($env['APP_DEBUG'] ?? '') . ' in .env (set APP_DEBUG=false)',
        );

        $this->check(
            'APP_KEY is set',
            filled(config('app.key')),
            'run: php artisan key:generate',
        );

        $url = (string) config('app.url');
        $this->check(
            'APP_URL uses https://',
            str_starts_with($url, 'https://'),
            'APP_URL=' . $url . ' (use your https:// address)',
        );

        $this->check(
            'Session cookie is HTTPS-only',
            (bool) config('session.secure'),
            'set SESSION_SECURE_COOKIE=true',
        );

        $this->check(
            'Session data is encrypted',
            (bool) config('session.encrypt'),
            'recommended: SESSION_ENCRYPT=true',
            required: false,
        );

        $mailHost = strtolower((string) config('mail.mailers.smtp.host'));
        $from = strtolower((string) config('mail.from.address'));
        $this->check(
            'Mail uses a real mail server',
            config('mail.default') !== 'log'
                && !in_array($mailHost, ['', '127.0.0.1', 'localhost', 'mailpit'], true)
                && !str_ends_with($from, '.test'),
            'MAIL_HOST=' . ($mailHost ?: '(empty)') . ', MAIL_FROM_ADDRESS=' . ($from ?: '(empty)')
                . ' (reminder and password-reset emails would not arrive)',
        );

        try {
            DB::connection()->getPdo();
            $dbOk = true;
            $dbNote = '';
        } catch (Throwable $e) {
            $dbOk = false;
            $dbNote = 'cannot connect: ' . mb_substr($e->getMessage(), 0, 120);
        }
        $this->check('Database connection works', $dbOk, $dbNote);

        $this->check(
            'Storage link exists (public/storage)',
            file_exists(public_path('storage')),
            'run: php artisan storage:link (equipment photos and avatars need it)',
        );

        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache')] as $label => $path) {
            $this->check(
                "{$label} is writable",
                is_writable($path),
                "make {$label} writable by the web server",
            );
        }

        $this->check(
            'Config is cached',
            $this->laravel->configurationIsCached(),
            'run: php artisan config:cache (after every .env change)',
            required: false,
        );

        $this->table(['Check', 'Result', 'Fix'], $this->rows);

        $this->line('Also make sure the hPanel cron job runs every minute, or reminder emails are not sent:');
        $this->line('    * * * * * cd ' . base_path() . ' && php artisan schedule:run >> /dev/null 2>&1');

        $failed = collect($this->rows)->where(1, 'FAIL')->count();
        if ($failed > 0) {
            $this->error("{$failed} required setting(s) need fixing before going live.");

            return self::FAILURE;
        }

        $this->info('All required production settings are in place.');

        return self::SUCCESS;
    }

    private function check(string $label, bool $ok, string $fix, bool $required = true): void
    {
        $this->rows[] = [$label, $ok ? 'OK' : ($required ? 'FAIL' : 'WARN'), $ok ? '' : $fix];
    }

    /** @return array<string, string|null> */
    private function readEnvFile(): array
    {
        $file = $this->laravel->environmentFilePath();

        return is_file($file) ? Dotenv::parse((string) file_get_contents($file)) : [];
    }
}
