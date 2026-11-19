<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Admin Equipment and Department Equipment share one Add / Edit window
 * (partials/equipment/form-modal) and one script for it and the equipment
 * cards (public/js/equipment-form.js), so a fix reaches both pages.
 */
class SharedEquipmentFormTest extends TestCase
{
    use CreatesEquipTrackRecords;
    use RefreshDatabase;

    private const SHARED_SCRIPT = 'js/equipment-form.js';

    public function test_both_pages_use_the_shared_window_and_script(): void
    {
        $department = $this->makeDepartment();
        $this->makeEquipment($department);   // creates the "Laptop" category

        $admin = $this->actingAs($this->makeAdmin(), 'admin')->get(route('admin.equipment'))->assertOk();
        $lab = $this->actingAs($this->makeLabPersonnel($department), 'dept')->get(route('department.equipment'))->assertOk();

        foreach ([$admin, $lab] as $page) {
            $page->assertSee(self::SHARED_SCRIPT)
                ->assertSee('id="equipmentForm"', false)
                ->assertSee('EquipmentForm.init(', false)
                // Field names both servers' save actions read
                ->assertSee('name="category_id"', false)
                // Serial numbers and quantities come from the Units list (one row per unit)
                ->assertSee('name="units_mode"', false)
                ->assertSee('id="eqUnitsList"', false)
                ->assertSee('name="image"', false)
                // Categories are chosen by id on both pages
                ->assertSee('<option value="1">Laptop</option>', false);
        }

        // Admin picks the department; a Department account never does, and
        // only sees that the classification is locked on edits.
        $admin->assertSee('id="eqFormDepartment"', false)->assertDontSee('id="eqClassLockedHint"', false);
        $lab->assertDontSee('id="eqFormDepartment"', false)->assertSee('id="eqClassLockedHint"', false);
    }

    /** The uploader, saving and card drawing live in one place only. */
    public function test_neither_page_keeps_its_own_copy(): void
    {
        foreach (['admin/equipment.blade.php', 'department/equipment.blade.php'] as $view) {
            $source = File::get(resource_path('views/' . $view));

            foreach (['function showPreview', 'function resetUploader', 'function handleFile', "form.addEventListener('submit'", 'eq-card-actions'] as $copy) {
                $this->assertStringNotContainsString($copy, $source, "$view still has its own copy of: $copy");
            }
        }

        $script = File::get(public_path(self::SHARED_SCRIPT));
        foreach (['function showPreview', 'function handleFile', 'MAX_PHOTO_BYTES', "form.addEventListener('submit'", 'function card('] as $part) {
            $this->assertStringContainsString($part, $script);
        }
    }
}
