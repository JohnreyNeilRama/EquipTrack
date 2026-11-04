<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Settings that keep EquipTrack working the same on a hosting server as on
 * Laragon: MySQL dates in Philippine time on UTC servers, and HTTPS / real IP
 * addresses behind a proxy such as Cloudflare only when one is configured.
 */
class DeploymentSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // What Laravel concludes about the request after its proxy middleware.
        Route::get('/_deployment-check', fn (Request $request) => [
            'secure' => $request->isSecure(),
            'ip' => $request->ip(),
        ]);
    }

    private function viaProxy()
    {
        return $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-For' => '203.0.113.5'])
            ->getJson('/_deployment-check');
    }

    public function test_mysql_and_mariadb_connections_use_philippine_time(): void
    {
        $this->assertSame('+08:00', config('database.connections.mysql.timezone'));
        $this->assertSame('+08:00', config('database.connections.mariadb.timezone'));
    }

    public function test_proxy_headers_are_ignored_unless_a_proxy_is_configured(): void
    {
        config(['trustedproxy.proxies' => null]);

        // Anyone could send these headers; without a proxy they mean nothing.
        $this->viaProxy()->assertOk()->assertJson(['secure' => false, 'ip' => '10.0.0.1']);
    }

    // One request per test: the test client reuses the previous request's
    // https:// address, which would hide what the next request alone shows.

    public function test_behind_a_configured_proxy_https_and_the_visitor_ip_are_recognised(): void
    {
        config(['trustedproxy.proxies' => '*']);

        $this->viaProxy()->assertOk()->assertJson(['secure' => true, 'ip' => '203.0.113.5']);
    }

    public function test_a_listed_proxy_is_trusted(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.1']);

        $this->viaProxy()->assertOk()->assertJson(['secure' => true, 'ip' => '203.0.113.5']);
    }

    public function test_a_proxy_not_on_the_list_is_not_trusted(): void
    {
        config(['trustedproxy.proxies' => '10.9.9.9']);

        $this->viaProxy()->assertOk()->assertJson(['secure' => false, 'ip' => '10.0.0.1']);
    }
}
