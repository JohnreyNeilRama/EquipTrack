<?php

namespace Tests\Feature;

use App\Support\Avatar;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Initials pictures (for accounts and departments without an uploaded
 * picture) are drawn by EquipTrack at /avatar, so no name is sent to an
 * outside website and they work offline.
 */
class AvatarTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    public function test_initials_match_the_previous_pictures(): void
    {
        $this->assertSame('JR', Avatar::initials('Johnrey Neil Rama'));
        $this->assertSame('DD', Avatar::initials('Dr. Jane Doe'));
        $this->assertSame('DD', Avatar::initials('Dennis Durano'));
        $this->assertSame('AD', Avatar::initials('Admin'));
        $this->assertSame('ÑG', Avatar::initials('ñino gómez'));
        $this->assertSame('?', Avatar::initials('   '));
    }

    public function test_the_picture_is_an_svg_with_the_initials_and_colours(): void
    {
        $response = $this->get('/avatar?name=Johnrey+Rama&background=385585&color=fff&size=300&bold=true');

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=utf-8');
        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=604800', $response->headers->get('Cache-Control'));
        $svg = $response->getContent();
        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" width="300" height="300"', $svg);
        $this->assertStringContainsString('fill="#385585"', $svg);
        $this->assertStringContainsString('fill="#fff"', $svg);
        $this->assertStringContainsString('font-weight="700"', $svg);
        $this->assertStringContainsString('>JR</text>', $svg);
    }

    public function test_bad_input_is_made_safe(): void
    {
        // Script in the name is shown as text; odd colours and sizes fall back.
        $svg = $this->get('/avatar?' . http_build_query([
            'name' => '<script>alert(1)</script> "x"',
            'background' => 'red;"><script>',
            'color' => 'javascript:x',
            'size' => 99999,
        ]))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script', $svg);
        $this->assertStringContainsString('fill="#385585"', $svg);
        $this->assertStringContainsString('fill="#ffffff"', $svg);
        $this->assertStringContainsString('width="512"', $svg);

        // No name at all still draws a picture.
        $this->get('/avatar')->assertOk()->assertSee('?</text>', false);
    }

    public function test_pages_no_longer_link_to_the_outside_service(): void
    {
        $department = $this->makeDepartment();
        $student = $this->makeStudent($department);
        $admin = $this->makeAdmin();

        $this->actingAs($student, 'user')->get(route('user.profile'))->assertOk()
            ->assertDontSee('ui-avatars.com', false)
            ->assertSee(url('/avatar') . '?name=', false);

        $this->actingAs($admin, 'admin')->get(route('admin.profile'))->assertOk()
            ->assertDontSee('ui-avatars.com', false)
            ->assertSee(url('/avatar') . '?name=', false);

        $this->actingAs($admin, 'admin')->get(route('admin.users'))->assertOk()->assertDontSee('ui-avatars.com', false);
        $this->actingAs($admin, 'admin')->get(route('admin.monitoring'))->assertOk()->assertDontSee('ui-avatars.com', false);
        $this->actingAs($admin, 'admin')->get(route('admin.requests'))->assertOk()->assertDontSee('ui-avatars.com', false);
    }

    public function test_request_rows_use_the_local_picture_when_there_is_no_upload(): void
    {
        $this->assertStringStartsWith(url('/avatar') . '?name=Juan+Dela+Cruz', Avatar::url('Juan Dela Cruz'));
    }
}
