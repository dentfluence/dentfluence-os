<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            CmsEduSeeder::class,
            InventoryDemoSeeder::class, // Dental inventory demo data
            MasterDemoSeeder::class,    // 10 patients + appointments + consultations + visits + plans + finance
        ]);
    }
}