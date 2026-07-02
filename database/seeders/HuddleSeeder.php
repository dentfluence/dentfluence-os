<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class HuddleSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🦷 Running Tulip Dental — Huddle Module Seeders');
        $this->command->info('─────────────────────────────────────────────');

        $this->call([
            HuddleTestUsersSeeder::class,
            HuddleSettingsSeeder::class,
        ]);

        $this->command->info('─────────────────────────────────────────────');
        $this->command->info('✅ Huddle seeding complete.');
        $this->command->info('');
    }
}
