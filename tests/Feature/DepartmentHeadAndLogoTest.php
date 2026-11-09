<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Department;
use App\Models\DepartmentAccount;
use App\Models\UserAccount;
use App\Services\UserAccountEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * A department's card (Admin Equipment Monitoring) always shows its current
 * head and logo:
 * - the head's name follows the name on the department's account (new head
 *   takes over, name corrected, account created);
 * - every picture upload gets a new file name, so the new picture shows right
 *   away, and the old file is deleted once nothing uses it.
 */
class DepartmentHeadAndLogoTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Department $ccs;

    private DepartmentAccount $lab;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->ccs = $this->makeDepartment();
        $this->ccs->update(['department_head' => 'Dr. Department Head']);
        $this->lab = $this->makeLabPersonnel($this->ccs, 'ccs.lab@example.com', 'Dr. Christian Ferrer');
        $this->admin = $this->makeAdmin();
    }

    private function png(string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->image($name, 40, 40);
    }

    private function storedFile(?string $path): string
    {
        return substr((string) $path, strlen('/storage/'));
    }

    // ------------------------------------------------------------------
    // Department head name
    // ------------------------------------------------------------------

    public function test_the_head_name_follows_the_account_holder(): void
    {
        // Creating the account set the head (replacing the placeholder).
        $this->assertSame('Dr. Christian Ferrer', $this->ccs->fresh()->department_head);

        // A new head takes over: the Admin edits the account in Users.
        $this->actingAs($this->admin, 'admin')->post(route('admin.users.update'), [
            'form_type' => 'edit',
            'user_id' => $this->lab->dept_acc_id + 100000,
            'user_type' => 'department',
        ] + array_merge(UserAccountEditor::values($this->lab), [
            'full_name' => 'Dr. Maria Santos',
            'email' => 'maria.santos@example.com',
            'employee_id' => 'EMP-2026-777',
        ]))->assertRedirect();

        $this->assertSame('Dr. Maria Santos', $this->ccs->fresh()->department_head);

        // The head corrects their own name on their Profile.
        $this->actingAs($this->lab->fresh(), 'dept')->post(route('department.profile.update'), [
            'full_name' => 'Dr. Maria L. Santos',
            'email' => 'maria.santos@example.com',
            'employee_id' => 'EMP-2026-777',
        ]);

        $this->assertSame('Dr. Maria L. Santos', $this->ccs->fresh()->department_head);
    }

    public function test_other_account_changes_leave_the_head_name_alone(): void
    {
        $this->ccs->update(['department_head' => 'Kept As Is']);

        $this->lab->update(['last_online' => now(), 'email' => 'new.email@example.com']);

        $this->assertSame('Kept As Is', $this->ccs->fresh()->department_head);
    }

    public function test_other_departments_are_not_touched(): void
    {
        $ccj = $this->makeDepartment('College of Criminal Justice', 'CCJ');
        $ccj->update(['department_head' => 'Dr. Jemuel Rama']);

        $this->lab->update(['full_name' => 'Dr. Someone Else']);

        $this->assertSame('Dr. Jemuel Rama', $ccj->fresh()->department_head);
    }

    // ------------------------------------------------------------------
    // Logos and profile pictures
    // ------------------------------------------------------------------

    public function test_a_new_department_logo_gets_a_new_file_and_the_old_one_is_deleted(): void
    {
        $first = $this->actingAs($this->lab, 'dept')
            ->post(route('department.profile.avatar'), ['avatar_file' => $this->png()])
            ->assertOk()->json('profile_image');

        // Account and department share the logo.
        $this->assertSame($first, $this->lab->fresh()->profile_image);
        $this->assertSame($first, $this->ccs->fresh()->profile_image);
        Storage::disk('public')->assertExists($this->storedFile($first));

        $this->travel(1)->seconds();
        $second = $this->actingAs($this->lab->fresh(), 'dept')
            ->post(route('department.profile.avatar'), ['avatar_file' => $this->png('new.jpg')])
            ->assertOk()->json('profile_image');

        $this->assertNotSame($first, $second);
        $this->assertSame($second, $this->ccs->fresh()->profile_image);
        Storage::disk('public')->assertExists($this->storedFile($second));
        Storage::disk('public')->assertMissing($this->storedFile($first));
    }

    public function test_the_admin_changing_a_department_logo_replaces_the_heads_upload(): void
    {
        $byHead = $this->actingAs($this->lab, 'dept')
            ->post(route('department.profile.avatar'), ['avatar_file' => $this->png()])
            ->json('profile_image');

        $this->actingAs($this->admin, 'admin')->post(route('admin.users.image'), [
            'target_user_id' => $this->lab->dept_acc_id + 100000,
            'target_user_type' => 'department',
            'admin_profile_img_file' => $this->png('by-admin.png'),
        ])->assertRedirect();

        $byAdmin = $this->ccs->fresh()->profile_image;
        $this->assertNotSame($byHead, $byAdmin);
        $this->assertSame($byAdmin, $this->lab->fresh()->profile_image);
        Storage::disk('public')->assertExists($this->storedFile($byAdmin));
        Storage::disk('public')->assertMissing($this->storedFile($byHead));
    }

    public function test_student_and_admin_pictures_get_new_files_too(): void
    {
        $student = $this->makeStudent($this->ccs);
        $first = $this->actingAs($student, 'user')
            ->post(route('user.profile.avatar'), ['profile_image_file' => $this->png()])
            ->assertOk()->json('image_url');
        $this->travel(1)->seconds();
        $second = $this->actingAs($student->fresh(), 'user')
            ->post(route('user.profile.avatar'), ['profile_image_file' => $this->png()])
            ->json('image_url');

        $this->assertNotSame($first, $second);
        $this->assertSame($second, $student->fresh()->profile_image);
        Storage::disk('public')->assertMissing($this->storedFile($first));

        $adminFirst = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.profile.avatar'), ['admin_avatar_file' => $this->png()])
            ->json('image_url');
        $adminSecond = $this->actingAs($this->admin->fresh(), 'admin')
            ->post(route('admin.profile.avatar'), ['admin_avatar_file' => $this->png()])
            ->json('image_url');

        $this->assertNotSame($adminFirst, $adminSecond);
        Storage::disk('public')->assertMissing($this->storedFile($adminFirst));
    }

    public function test_a_file_still_used_elsewhere_and_files_outside_uploads_are_kept(): void
    {
        // Another record still points to the old file: it stays.
        Storage::disk('public')->put('images/shared.png', 'x');
        $this->lab->update(['profile_image' => '/storage/images/shared.png']);
        $this->ccs->update(['profile_image' => '/storage/images/shared.png']);
        UserAccount::query()->update(['profile_image' => '/storage/images/shared.png']);
        $this->makeStudent($this->ccs)->update(['profile_image' => '/storage/images/shared.png']);

        $this->actingAs($this->lab->fresh(), 'dept')
            ->post(route('department.profile.avatar'), ['avatar_file' => $this->png()])
            ->assertOk();

        Storage::disk('public')->assertExists('images/shared.png');

        // A built-in image (public/images) is never deleted.
        $this->lab->update(['profile_image' => 'images/logo_only.png']);
        $this->actingAs($this->lab->fresh(), 'dept')
            ->post(route('department.profile.avatar'), ['avatar_file' => $this->png()])
            ->assertOk();
        $this->assertFileExists(public_path('images/logo_only.png'));
    }
}
