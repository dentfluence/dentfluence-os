<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketingModuleSeeder extends Seeder
{
    /**
     * Register the Marketing module in the modules table.
     * Matches schema: name, slug, icon, section, sort_order, timestamps.
     * Safe to run multiple times (uses updateOrInsert on slug).
     */
    public function run(): void
    {
        DB::table('modules')->updateOrInsert(
            ['slug' => 'marketing'],
            [
                'name'       => 'Marketing Hub',
                'icon'       => null,
                'section'    => 'operations',
                'sort_order' => 50,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $this->command->info('Marketing module row inserted/updated (slug=marketing).');
    }
}
