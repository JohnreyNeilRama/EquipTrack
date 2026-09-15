<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\Department;
use App\Models\EquipmentCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EquipTrackSeeder extends Seeder
{
    public function run(): void
    {
        // Seed only what is missing — never clobber migrated data.
        $ccs = Department::firstOrCreate(
            ['department_code' => 'CCS'],
            [
                'department_name' => 'College of Computer Studies',
                'college' => 'College of Computer Studies',
                'department_head' => 'Dr. Department Head',
            ],
        );

        foreach (['Projectors', 'Laptops'] as $name) {
            EquipmentCategory::firstOrCreate(['category_name' => $name]);
        }

        if (!Admin::exists()) {
            $password = Str::password(16);
            Admin::create([
                'name' => 'System Admin',
                'username' => 'ucedu@gmail.com',
                'email' => 'ucedu@gmail.com',
                'employee_id' => 'ADM-0001',
                'password' => Hash::make($password),
            ]);
            $this->command?->info('Seeded admin account ucedu@gmail.com with generated password: ' . $password);
            $this->command?->warn('Store it now — it is shown only once.');
        }
    }
}
