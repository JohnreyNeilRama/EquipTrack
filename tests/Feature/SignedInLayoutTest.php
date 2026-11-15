<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Pieces every signed-in layout shares.
 */
class SignedInLayoutTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    /**
     * The notification bell knows where the login page is, so a page whose
     * session ended (signed out elsewhere, "Undo email change", deactivated)
     * goes there instead of failing quietly every 30 seconds.
     */
    public function test_every_bell_knows_the_login_page(): void
    {
        $department = $this->makeDepartment();
        $loginUrl = 'data-login-url="' . route('login') . '"';

        $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin.dashboard'))->assertOk()->assertSee($loginUrl, false);
        $this->actingAs($this->makeLabPersonnel($department), 'dept')->get(route('department.dashboard'))->assertOk()->assertSee($loginUrl, false);
        $this->actingAs($this->makeStudent($department), 'user')->get(route('user.dashboard'))->assertOk()->assertSee($loginUrl, false);

        $script = File::get(public_path('js/user-notifications.js'));
        $this->assertStringContainsString('res.status === 401', $script);
        $this->assertStringContainsString('clearInterval(timer)', $script);
    }

    /**
     * Pictures come from EquipTrack itself: a stock photo from another site
     * was shown for items without a photo, and the Department "Add" window
     * saved it as the new item's photo.
     */
    public function test_no_page_uses_a_stock_photo_from_another_site(): void
    {
        $files = array_merge(File::allFiles(resource_path('views')), File::files(public_path('js')));

        foreach ($files as $file) {
            $this->assertStringNotContainsString('unsplash.com', $file->getContents(), $file->getRelativePathname());
        }
    }
}
