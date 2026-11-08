<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Browser protection headers on every response: pages, JSON, file
 * downloads. Strict-Transport-Security only on the live server over HTTPS.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private function assertProtected(TestResponse $response): void
    {
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        // Scripts and styles are deliberately not restricted (inline scripts, CDN fonts).
        $this->assertStringNotContainsString('script-src', $csp);
    }

    private function admin(): Admin
    {
        return Admin::create([
            'name' => 'System Admin',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
        ]);
    }

    public function test_public_pages_send_the_protection_headers(): void
    {
        $this->assertProtected($this->get('/login')->assertOk());
        $this->assertProtected($this->get('/')->assertOk());
    }

    public function test_signed_in_pages_json_and_downloads_send_them_too(): void
    {
        $admin = $this->admin();

        $this->assertProtected($this->actingAs($admin, 'admin')->get(route('admin.dashboard'))->assertOk());
        $this->assertProtected($this->actingAs($admin, 'admin')->getJson(route('admin.audit.data'))->assertOk());
        $this->assertProtected($this->actingAs($admin, 'admin')->get(route('admin.audit.export', ['format' => 'csv']))->assertOk());
        $this->assertProtected($this->actingAs($admin, 'admin')->get(route('admin.audit.export', ['format' => 'pdf']))->assertOk());
    }

    public function test_redirects_send_them(): void
    {
        $this->assertProtected($this->get(route('admin.dashboard'))->assertRedirect(route('login')));
    }

    public function test_https_memory_header_only_on_the_live_server_over_https(): void
    {
        $this->get('/login')->assertHeaderMissing('Strict-Transport-Security');

        $this->app['env'] = 'production';
        $this->get('https://equiptrack.example/login')
            ->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
