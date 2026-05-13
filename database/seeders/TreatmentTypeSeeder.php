<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TreatmentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Consultation',        'duration' => 15,  'price' => 200,   'color' => '#6a0f70', 'order' => 1],
            ['name' => 'Scaling & Polishing', 'duration' => 45,  'price' => 800,   'color' => '#2563eb', 'order' => 2],
            ['name' => 'Filling',             'duration' => 30,  'price' => 600,   'color' => '#059669', 'order' => 3],
            ['name' => 'Root Canal (RCT)',     'duration' => 60,  'price' => 3500,  'color' => '#dc2626', 'order' => 4],
            ['name' => 'Crown',               'duration' => 60,  'price' => 5000,  'color' => '#d97706', 'order' => 5],
            ['name' => 'Extraction',          'duration' => 30,  'price' => 500,   'color' => '#7c3aed', 'order' => 6],
            ['name' => 'Implant',             'duration' => 90,  'price' => 25000, 'color' => '#0891b2', 'order' => 7],
            ['name' => 'Orthodontic Review',  'duration' => 30,  'price' => 500,   'color' => '#be185d', 'order' => 8],
            ['name' => 'Teeth Whitening',     'duration' => 60,  'price' => 4000,  'color' => '#ca8a04', 'order' => 9],
            ['name' => 'X-Ray',               'duration' => 15,  'price' => 300,   'color' => '#475569', 'order' => 10],
        ];

        foreach ($types as $t) {
            DB::table('treatment_types')->insertOrIgnore([
                'name'                     => $t['name'],
                'slug'                     => Str::slug($t['name']),
                'default_duration_minutes' => $t['duration'],
                'base_price'               => $t['price'],
                'color'                    => $t['color'],
                'is_active'                => true,
                'sort_order'               => $t['order'],
                'created_at'               => now(),
                'updated_at'               => now(),
            ]);
        }
    }
}
