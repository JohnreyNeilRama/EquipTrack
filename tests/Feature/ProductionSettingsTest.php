<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Live-server safety: HTTPS redirect, https:// links, debug forced off,
 * secure session cookie by default, and the equiptrack:check-production
 * command. Local development is unaffected.
 */
class ProductionSettingsTest extends TestCase
{
    private function asProduction(): void
    {
        $this->app['env'] = 'production';
    }

    public function test_plain_http_visits_are_redirected_to_https_in_production(): void
    {
        $this->asProduction();

        $this->get('http://equiptrack.example/login?registered=1')
            ->assertStatus(301)
            ->assertRedirect('https://equiptrack.example/login?registered=1');
    }

    public function test_https_visits_are_served_normally_in_production(): void
    {
        $this->asProduction();

        $this->get('https://equiptrack.example/login')->assertOk();
    }

    public function test_local_development_is_not_redirected(): void
    {
        $this->app['env'] = 'local';

        $this->get('http://equiptrack.test/login')->assertOk();
    }

    public function test_production_forces_https_links_and_turns_debug_off(): void
    {
        $this->asProduction();
        config(['app.debug' => true, 'app.url' => 'http://equiptrack.example']);
        URL::useOrigin('http://equiptrack.example');

        (new AppServiceProvider($this->app))->boot();

        $this->assertFalse(config('app.debug'));
        $this->assertStringStartsWith('https://', route('login'));
        $this->assertStringStartsWith('https://', url('/admin/reports/export'));
    }

    public function test_session_cookie_is_https_only_by_default_in_production(): void
    {
        $this->assertTrue($this->sessionSecureFor(['APP_ENV' => 'production']));
        $this->assertFalse($this->sessionSecureFor(['APP_ENV' => 'production', 'SESSION_SECURE_COOKIE' => 'false']));
        $this->assertFalse($this->sessionSecureFor(['APP_ENV' => 'local']));
    }

    public function test_check_command_reports_local_settings_as_not_ready(): void
    {
        config(['app.env' => 'local', 'app.url' => 'http://equiptrack.test', 'session.secure' => false]);

        $this->artisan('equiptrack:check-production')
            ->expectsOutputToContain('APP_ENV=local (set APP_ENV=production)')
            ->expectsOutputToContain('need fixing before going live')
            ->assertExitCode(1);
    }

    public function test_check_command_passes_with_production_settings(): void
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'equiptrack-env-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . DIRECTORY_SEPARATOR . '.env', "APP_ENV=production\nAPP_DEBUG=false\n");
        $this->app->useEnvironmentPath($dir);

        config([
            'app.env' => 'production',
            'app.url' => 'https://equiptrack.example',
            'session.secure' => true,
            'session.encrypt' => true,
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.hostinger.com',
            'mail.from.address' => 'no-reply@equiptrack.example',
        ]);

        try {
            $this->artisan('equiptrack:check-production')
                ->expectsOutputToContain('All required production settings are in place.')
                ->assertExitCode(0);
        } finally {
            @unlink($dir . DIRECTORY_SEPARATOR . '.env');
            @rmdir($dir);
        }
    }

    /** config/session.php 'secure' as it would load with these .env values. */
    private function sessionSecureFor(array $vars): bool
    {
        $keys = ['APP_ENV', 'SESSION_SECURE_COOKIE'];
        $saved = [];
        foreach ($keys as $key) {
            $saved[$key] = [$_ENV[$key] ?? null, $_SERVER[$key] ?? null];
            unset($_ENV[$key], $_SERVER[$key]);
            if (array_key_exists($key, $vars)) {
                $_ENV[$key] = $_SERVER[$key] = $vars[$key];
            }
        }

        try {
            return (bool) (require config_path('session.php'))['secure'];
        } finally {
            foreach ($saved as $key => [$env, $server]) {
                unset($_ENV[$key], $_SERVER[$key]);
                if ($env !== null) {
                    $_ENV[$key] = $env;
                }
                if ($server !== null) {
                    $_SERVER[$key] = $server;
                }
            }
        }
    }
}
