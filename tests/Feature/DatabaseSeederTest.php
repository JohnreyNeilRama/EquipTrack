<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Department;
use App\Models\EquipmentCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * `php artisan db:seed` on a new installation creates the first Admin account
 * and the starting categories, and running it again changes nothing. No
 * department is seeded: the Admin adds real departments, each with its
 * Department Account, in the app.
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_db_seed_sets_up_a_new_installation(): void
    {
        $this->artisan('db:seed')->assertSuccessful();

        $this->assertSame(1, Admin::count());
        $this->assertSame('ucedu@gmail.com', Admin::sole()->email);
        $this->assertSame(0, Department::count());
        $this->assertSame(2, EquipmentCategory::count());
    }

    public function test_running_it_again_keeps_existing_data(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $password = Admin::sole()->password;

        $this->artisan('db:seed')->assertSuccessful();

        $this->assertSame(1, Admin::count());
        $this->assertSame($password, Admin::sole()->password);
        $this->assertSame(0, Department::count());
        $this->assertSame(2, EquipmentCategory::count());
    }
}
