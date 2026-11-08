<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AuditTrail;
use App\Models\Department;
use App\Models\Equipment;
use App\Models\EquipmentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesEquipTrackRecords;
use Tests\TestCase;

/**
 * Manage Categories on the Admin Equipment Management page: add, rename and
 * delete equipment categories. A category that equipment uses is never
 * deleted (the Admin is told why), so no equipment loses its category.
 */
class AdminCategoryManagementTest extends TestCase
{
    use RefreshDatabase;
    use CreatesEquipTrackRecords;

    private Admin $admin;

    private Department $department;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeAdmin();
        $this->department = $this->makeDepartment();
    }

    private function postAs(string $route, array $data)
    {
        return $this->actingAs($this->admin, 'admin')->postJson(route($route), $data);
    }

    public function test_the_page_lists_categories_with_how_much_equipment_uses_them(): void
    {
        $this->makeEquipment($this->department); // creates "Laptop" with one item
        EquipmentCategory::create(['category_name' => 'Projectors']);

        $list = collect($this->actingAs($this->admin, 'admin')->get(route('admin.equipment'))->assertOk()
            ->assertSee('Manage Categories')
            ->viewData('categoryList'))->keyBy('category_name');

        $this->assertSame(1, $list['Laptop']['equipment_count']);
        $this->assertSame(0, $list['Projectors']['equipment_count']);
    }

    public function test_an_unused_category_is_deleted_and_logged(): void
    {
        $category = EquipmentCategory::create(['category_name' => 'Projectors']);

        $this->postAs('admin.equipment.category.delete', ['category_id' => $category->category_id])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Category "Projectors" deleted.', 'categories' => []]);

        $this->assertDatabaseMissing('equipment_category', ['category_id' => $category->category_id]);
        $this->assertTrue(AuditTrail::where('action', 'Deleted category "Projectors"')->exists());
    }

    public function test_a_category_that_equipment_uses_is_not_deleted_and_the_admin_is_told_why(): void
    {
        $equipment = $this->makeEquipment($this->department);
        $this->makeEquipment($this->department, ['name' => 'HP ProBook']);
        $category = $equipment->category;

        $response = $this->postAs('admin.equipment.category.delete', ['category_id' => $category->category_id])
            ->assertStatus(409)
            ->assertJson(['success' => false]);

        $this->assertSame(
            '"Laptop" can\'t be deleted because 2 equipment items use it. Move those items to another category first (edit the equipment), then delete the category.',
            $response->json('message'),
        );
        // Nothing was removed: the category and its equipment are intact.
        $this->assertDatabaseHas('equipment_category', ['category_id' => $category->category_id]);
        $this->assertSame(2, Equipment::where('category_id', $category->category_id)->count());
        $this->assertSame(2, $response->json('categories.0.equipment_count'));
    }

    public function test_after_moving_its_equipment_the_category_can_be_deleted(): void
    {
        $equipment = $this->makeEquipment($this->department);
        $old = $equipment->category;
        $new = EquipmentCategory::create(['category_name' => 'Laptops']);

        $this->postAs('admin.equipment.category.delete', ['category_id' => $old->category_id])->assertStatus(409);

        $equipment->update(['category_id' => $new->category_id]);

        $this->postAs('admin.equipment.category.delete', ['category_id' => $old->category_id])->assertOk();
        $this->assertDatabaseMissing('equipment_category', ['category_id' => $old->category_id]);
        $this->assertSame($new->category_id, $equipment->fresh()->category_id);
    }

    public function test_deleting_a_category_that_no_longer_exists_says_so(): void
    {
        $this->postAs('admin.equipment.category.delete', ['category_id' => 999])
            ->assertNotFound()
            ->assertJson(['success' => false, 'message' => 'This category was already deleted.']);
    }

    public function test_a_category_is_renamed_and_its_equipment_keeps_it(): void
    {
        $equipment = $this->makeEquipment($this->department);
        $category = $equipment->category;

        $this->postAs('admin.equipment.category.update', ['category_id' => $category->category_id, 'category_name' => '  Laptops  '])
            ->assertOk()
            ->assertJson(['success' => true, 'message' => 'Category renamed to "Laptops".']);

        $this->assertSame('Laptops', $category->fresh()->category_name);
        $this->assertSame($category->category_id, $equipment->fresh()->category_id);
        $this->assertTrue(AuditTrail::where('action', 'Renamed category "Laptop" to "Laptops"')->exists());
    }

    public function test_names_stay_unique_when_adding_or_renaming(): void
    {
        EquipmentCategory::create(['category_name' => 'Projectors']);
        $other = EquipmentCategory::create(['category_name' => 'Cameras']);

        $this->postAs('admin.equipment.category', ['category_name' => 'projectors'])
            ->assertStatus(400)
            ->assertJson(['message' => 'A category with this name already exists.']);

        $this->postAs('admin.equipment.category.update', ['category_id' => $other->category_id, 'category_name' => 'PROJECTORS'])
            ->assertStatus(400)
            ->assertJson(['message' => 'A category with this name already exists.']);

        // Changing only the letter case of its own name is fine.
        $this->postAs('admin.equipment.category.update', ['category_id' => $other->category_id, 'category_name' => 'cameras'])
            ->assertOk();
    }

    public function test_adding_returns_the_updated_list(): void
    {
        $this->postAs('admin.equipment.category', ['category_name' => 'Projectors'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'category' => ['category_name' => 'Projectors'],
                'categories' => [['category_name' => 'Projectors', 'equipment_count' => 0]],
            ]);
    }

    public function test_only_admins_can_manage_categories(): void
    {
        $category = EquipmentCategory::create(['category_name' => 'Projectors']);

        $this->postJson(route('admin.equipment.category.delete'), ['category_id' => $category->category_id])
            ->assertUnauthorized();

        $this->assertDatabaseHas('equipment_category', ['category_id' => $category->category_id]);
    }
}
