<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan db:seed` sets up a new installation: the first Admin account
     * (with a generated password shown once) and the starting records. It only
     * adds what is missing, so running it again never changes existing data.
     */
    public function run(): void
    {
        $this->call(EquipTrackSeeder::class);
    }
}
