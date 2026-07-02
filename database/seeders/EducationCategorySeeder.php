<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EducationCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = config('cms.default_education_categories', []);

        foreach ($categories as $cat) {
            DB::table('education_categories')->updateOrInsert(
                ['slug' => $cat['slug']],
                array_merge($cat, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Education categories seeded.');
    }
}
