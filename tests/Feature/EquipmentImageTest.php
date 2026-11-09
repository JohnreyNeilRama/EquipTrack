<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use App\Support\EquipmentImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Equipment photos (Admin and Department forms): uploads must really be JPG,
 * PNG, WEBP or GIF images of 5MB or less; image links must be http(s) web
 * addresses; the current photo and "no photo" still work.
 */
class EquipmentImageTest extends TestCase
{
    use RefreshDatabase;

    /** A real 1x1 PNG and GIF. */
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private const GIF = 'R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function refused(?string $image): string
    {
        try {
            EquipmentImage::normalize($image, 7);
        } catch (ValidationException $e) {
            return $e->errors()['image'][0];
        }

        $this->fail('The image was accepted.');
    }

    public function test_a_real_image_is_saved_with_the_extension_of_what_it_really_is(): void
    {
        $path = EquipmentImage::normalize('data:image/png;base64,' . self::PNG, 7);
        $this->assertSame('/storage/images/equipment-7.png', $path);
        Storage::disk('public')->assertExists('images/equipment-7.png');

        // A GIF labelled as JPEG is saved as what it really is.
        $this->assertSame('/storage/images/equipment-8.gif', EquipmentImage::normalize('data:image/jpeg;base64,' . self::GIF, 8));
    }

    public function test_files_that_are_not_real_images_are_refused_whatever_they_claim_to_be(): void
    {
        $html = base64_encode('<html><script>alert(1)</script></html>');
        $svg = base64_encode('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>');
        $php = base64_encode('<?php system($_GET["c"]); ?>');

        $this->assertSame('The photo must be a JPG, PNG, WEBP or GIF image.', $this->refused('data:image/png;base64,' . $html));
        $this->assertSame('The photo must be a JPG, PNG, WEBP or GIF image.', $this->refused('data:image/svg+xml;base64,' . $svg));
        $this->assertSame('The photo must be a JPG, PNG, WEBP or GIF image.', $this->refused('data:image/jpeg;base64,' . $php));
        $this->assertSame('The photo could not be read. Choose a JPG, PNG, WEBP or GIF image.', $this->refused('data:image/png;base64,***not base64***'));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_photos_over_5mb_are_refused(): void
    {
        $big = base64_decode(self::PNG) . str_repeat("\0", EquipmentImage::MAX_BYTES);

        $this->assertSame('The photo must be 5MB or smaller.', $this->refused('data:image/png;base64,' . base64_encode($big)));
    }

    public function test_image_links_must_be_web_addresses(): void
    {
        $this->assertSame('https://example.com/photos/laptop.jpg', EquipmentImage::normalize('https://example.com/photos/laptop.jpg', 7));
        $this->assertSame('http://example.com/laptop.png', EquipmentImage::normalize(' http://example.com/laptop.png ', 7));

        $message = 'The image link must be a web address starting with https:// (or http://).';
        foreach ([
            'javascript:alert(1)',
            'JaVaScRiPt:alert(1)',
            'ftp://example.com/a.jpg',
            '//example.com/a.jpg',
            'file:///C:/Windows/win.ini',
            'just some text',
            'https://exa mple.com/a.jpg',
            '"><img src=x onerror=alert(1)>',
            '/storage/../../.env',
        ] as $bad) {
            $this->assertSame($message, $this->refused($bad), $bad);
        }
    }

    public function test_the_current_photo_and_no_photo_still_work(): void
    {
        $this->assertSame('/storage/images/equipment-2-image.jpg', EquipmentImage::normalize('/storage/images/equipment-2-image.jpg', 2));
        $this->assertSame('', EquipmentImage::normalize('', 2));
        $this->assertSame('', EquipmentImage::normalize(null, 2));
    }

    public function test_admin_and_department_forms_show_the_reason_and_save_nothing(): void
    {
        $department = Department::create(['department_name' => 'IT Department', 'department_code' => 'IT']);
        $category = EquipmentCategory::create(['category_name' => 'Laptop']);
        $admin = Admin::create(['name' => 'System Admin', 'username' => 'admin', 'password' => Hash::make('password')]);
        $dept = DepartmentAccount::create([
            'full_name' => 'Lab Head',
            'role' => 'Department Head',
            'email' => 'it@example.com',
            'password' => Hash::make('password'),
            'department_id' => $department->department_id,
            'status' => 'Active',
        ]);
        $payload = [
            'name' => 'Laptop',
            'brand' => 'Dell',
            'serial_number' => 'SN-1',
            'classification' => 'Not Expensive',
            'available_qty' => 1,
            'total_qty' => 1,
            'status' => 'Available',
            'category_id' => $category->category_id,
            'department_id' => $department->department_id,
            'image' => 'data:image/png;base64,' . base64_encode('<html>not an image</html>'),
        ];

        $this->actingAs($admin, 'admin')->postJson(route('admin.equipment.save'), $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'The photo must be a JPG, PNG, WEBP or GIF image.');

        $this->actingAs($dept, 'dept')->postJson(route('department.equipment.save'), ['image' => 'javascript:alert(1)'] + $payload)
            ->assertStatus(422)
            ->assertJsonPath('message', 'The image link must be a web address starting with https:// (or http://).');

        $this->assertSame(0, Equipment::count());

        // A real photo still saves normally.
        $this->actingAs($dept, 'dept')->postJson(route('department.equipment.save'), ['image' => 'data:image/png;base64,' . self::PNG] + $payload)
            ->assertOk()->assertJson(['success' => true]);
        $this->assertStringEndsWith('.png', Equipment::sole()->image);
    }
}
