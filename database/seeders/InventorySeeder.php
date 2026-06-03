<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryLocation;

class InventorySeeder extends Seeder
{
    /**
     * Seed dental-specific categories and clinic locations.
     * Run with: php artisan db:seed --class=InventorySeeder
     */
    public function run(): void
    {
        $this->seedLocations();
        $this->seedCategories();
    }

    /* ── LOCATIONS ─────────────────────────────────────────── */

    private function seedLocations(): void
    {
        $locations = [
            ['name' => 'Main Store',      'code' => 'MAIN-STORE', 'type' => 'main_store',     'sort_order' => 1],
            ['name' => 'Operatory 1',     'code' => 'OP-1',       'type' => 'operatory',       'sort_order' => 2],
            ['name' => 'Operatory 2',     'code' => 'OP-2',       'type' => 'operatory',       'sort_order' => 3],
            ['name' => 'Sterilization',   'code' => 'STERIL',     'type' => 'sterilization',   'sort_order' => 4],
            ['name' => 'Implant Drawer',  'code' => 'IMP-DRAW',   'type' => 'implant_drawer',  'sort_order' => 5],
            ['name' => 'Lab',             'code' => 'LAB',        'type' => 'lab',             'sort_order' => 6],
        ];

        foreach ($locations as $loc) {
            InventoryLocation::firstOrCreate(['code' => $loc['code']], $loc);
        }

        $this->command->info('  ✓ Inventory locations seeded (' . count($locations) . ')');
    }

    /* ── CATEGORIES ─────────────────────────────────────────── */

    private function seedCategories(): void
    {
        $topLevel = [
            [
                'name'  => 'Implant Systems',
                'color' => '#6a0f70',
                'icon'  => 'implant',
                'sort_order' => 1,
                'children' => [
                    'Implant Fixtures',
                    'Healing Abutments',
                    'Impression Copings',
                    'Implant Drills',
                    'Bone Grafts',
                    'Membranes',
                ],
            ],
            [
                'name'  => 'Consumables',
                'color' => '#1a5ea8',
                'icon'  => 'consumable',
                'sort_order' => 2,
                'children' => [
                    'Gloves',
                    'Masks & PPE',
                    'Cotton & Gauze',
                    'Suction Tips',
                    'Saliva Ejectors',
                    'Needles & Syringes',
                    'Burs',
                    'Paper Points',
                ],
            ],
            [
                'name'  => 'Restoratives',
                'color' => '#0e7b89',
                'icon'  => 'restorative',
                'sort_order' => 3,
                'children' => [
                    'Composites',
                    'Glass Ionomer',
                    'Bonding Agents',
                    'Cements',
                    'Crowns & Veneers',
                ],
            ],
            [
                'name'  => 'Endodontics',
                'color' => '#7a4a00',
                'icon'  => 'endo',
                'sort_order' => 4,
                'children' => [
                    'Rotary Files',
                    'Hand Files',
                    'Irrigants',
                    'Obturation Materials',
                    'Access Burs',
                ],
            ],
            [
                'name'  => 'Medicines & Drugs',
                'color' => '#b52020',
                'icon'  => 'medicine',
                'sort_order' => 5,
                'children' => [
                    'Local Anaesthetics',
                    'Antibiotics',
                    'Analgesics',
                    'Antiseptics',
                    'Topical Anaesthetics',
                ],
            ],
            [
                'name'  => 'Instruments & Tools',
                'color' => '#555',
                'icon'  => 'instrument',
                'sort_order' => 6,
                'children' => [
                    'Extraction Forceps',
                    'Elevators',
                    'Scalers & Curettes',
                    'Mirrors & Probes',
                    'Surgical Instruments',
                ],
            ],
            [
                'name'  => 'Sterilization',
                'color' => '#0e7b89',
                'icon'  => 'steril',
                'sort_order' => 7,
                'children' => [
                    'Sterilization Pouches',
                    'Autoclave Accessories',
                    'Disinfectants',
                    'Surface Cleaners',
                ],
            ],
            [
                'name'  => 'Office & General',
                'color' => '#4e4e4e',
                'icon'  => 'office',
                'sort_order' => 8,
                'children' => [
                    'Stationery',
                    'Printing Consumables',
                    'Housekeeping',
                    'Patient Amenities',
                ],
            ],
        ];

        foreach ($topLevel as $cat) {
            $children = $cat['children'] ?? [];
            unset($cat['children']);

            $parent = InventoryCategory::firstOrCreate(
                ['slug' => Str::slug($cat['name'])],
                array_merge($cat, ['slug' => Str::slug($cat['name'])])
            );

            foreach ($children as $i => $childName) {
                InventoryCategory::firstOrCreate(
                    ['slug' => Str::slug($childName)],
                    [
                        'name'       => $childName,
                        'slug'       => Str::slug($childName),
                        'parent_id'  => $parent->id,
                        'color'      => $cat['color'],
                        'sort_order' => $i + 1,
                    ]
                );
            }
        }

        $this->command->info('  ✓ Inventory categories seeded (' . count($topLevel) . ' parent + sub-categories)');
    }
}
