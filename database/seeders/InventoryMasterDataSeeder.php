<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventorySubType;

/**
 * InventoryMasterDataSeeder
 *
 * Clears all existing inventory_sub_types and inventory_categories,
 * then seeds the canonical 32-category master list with their sub-types.
 *
 * Run with: php artisan db:seed --class=InventoryMasterDataSeeder
 *
 * NOTE: inventory_items.category_id and sub_type_id are nullOnDelete,
 *       so existing items are preserved (just unlinked).
 */
class InventoryMasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // ── 1. Clear existing data (sub-types first, then categories) ─────────
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('inventory_sub_types')->truncate();
        DB::table('inventory_categories')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $this->command->info('  ✓ Cleared existing inventory categories and sub-types');

        // ── 2. Master data ────────────────────────────────────────────────────
        // Format: 'Category Name' => ['SubType1', 'SubType2', ...]
        // Ordered alphabetically as specified.
        $masterData = [
            'Bone Grafts & Membranes' => [
                'Bone Grafts',
                'Collagen Membranes',
                'PRF Accessories',
            ],
            'Bonding Agents' => [
                'Etchants',
                'Primer',
                'Self-Etch Bond',
                'Universal Bond',
            ],
            'CAD/CAM & Digital' => [
                '3D Printing Resin',
                'Milling Blocks',
                'Scan Spray',
                'Scanner Tips',
            ],
            'Cements' => [
                'Permanent Cement',
                'Resin Cement',
                'Temporary Cement',
                'Zinc Oxide Cement',
            ],
            'Composites' => [
                'Bulk Fill Composite',
                'Flowable Composite',
                'Packable Composite',
                'Universal Composite',
            ],
            'Consumables' => [
                'Cotton Pellets',
                'Cotton Rolls',
                'Gauze',
                'Microbrushes',
                'Saliva Ejectors',
                'Suction Tips',
            ],
            'Diagnostic Instruments' => [
                'Cotton Tweezers',
                'Explorers',
                'Mouth Mirrors',
                'Periodontal Probes',
            ],
            'Disinfectants' => [
                'Floor Cleaners',
                'Hand Sanitizers',
                'Instrument Disinfectants',
                'Surface Disinfectants',
            ],
            'Endodontics' => [
                'Endo Accessories',
                'Gutta Percha',
                'Hand Files',
                'Irrigants',
                'Paper Points',
                'Rotary Files',
                'Sealers',
            ],
            'Equipment Accessories' => [
                'Air-Water Syringe Tips',
                'Curing Light Tips',
                'RVG Sleeves',
                'Sensor Covers',
            ],
            'Glass Ionomers' => [
                'Liner/Base GIC',
                'Luting GIC',
                'Resin Modified GIC',
                'Restorative GIC',
            ],
            'Housekeeping' => [
                'Cleaning Cloths',
                'Garbage Bags',
                'Mop Refills',
                'Tissue Rolls',
            ],
            'Implant Surgery' => [
                'Bone Expanders',
                'Implant Drills',
                'Surgical Kit Components',
                'Torque Wrenches',
            ],
            'Implant Systems' => [
                'Cover Screws',
                'Healing Abutments',
                'Implant Analogues',
                'Implant Fixtures',
                'Scan Bodies',
            ],
            'Impression Materials' => [
                'Alginate',
                'Bite Registration',
                'Heavy Body',
                'Light Body',
                'Putty',
            ],
            'Lab Materials' => [
                'Acrylic Liquid',
                'Acrylic Powder',
                'Dental Stone',
                'Plaster',
                'Wax',
            ],
            'Lab Outsourcing' => [
                'Aligners',
                'Crowns',
                'Dentures',
                'Surgical Guides',
            ],
            'Local Anesthetics' => [
                'Articaine',
                'Bupivacaine',
                'Lignocaine',
                'Topical Gel',
                'Topical Spray',
            ],
            'Lubricants & Maintenance' => [
                'Handpiece Oil',
                'Lubricant Spray',
                'Maintenance Kits',
                'O-Rings',
            ],
            'Medicines & Drugs' => [
                'Analgesics',
                'Anti-inflammatory',
                'Antibiotics',
                'Antiseptic',
                'Emergency Drugs',
                'Mouthwash',
            ],
            'Needles & Syringes' => [
                'Dental Needles',
                'Disposable Syringes',
                'Insulin Syringes',
                'Irrigation Syringes',
            ],
            'Office Supplies' => [
                'Batteries',
                'Files',
                'Pens',
                'Stapler',
            ],
            'Orthodontics' => [
                'Arch Wires',
                'Brackets',
                'Buccal Tubes',
                'Elastics',
                'Ligatures',
            ],
            'Others' => [
                'Miscellaneous',
            ],
            'Patient Amenities' => [
                'Dental Bibs',
                'Disposable Cups',
                'Toothbrushes',
                'Welcome Kits',
            ],
            'PPE' => [
                'Caps',
                'Face Shields',
                'Gloves',
                'Gowns',
                'Masks',
            ],
            'Prosthodontics' => [
                'Impression Copings',
                'Implant Analogues',
                'Prosthetic Accessories',
                'Temporary Crowns',
            ],
            'Restorative Materials' => [
                'Amalgam',
                'Composite',
                'Glass Ionomer',
                'Temporary Filling Material',
            ],
            'Rotary Instruments' => [
                'Carbide Burs',
                'Diamond Burs',
                'Finishing Burs',
                'Polishing Burs',
            ],
            'Stationery' => [
                'Consent Forms',
                'Labels',
                'Prescription Pads',
                'Printer Paper',
            ],
            'Sterilization' => [
                'Biological Indicators',
                'Indicator Strips',
                'Indicator Tape',
                'Sterilization Pouches',
            ],
            'Surgical Instruments' => [
                'Bone Instruments',
                'Elevators',
                'Extraction Forceps',
                'Periosteal Elevators',
                'Surgical Scissors',
            ],
        ];

        // ── 3. Insert categories then sub-types ───────────────────────────────
        $catCount    = 0;
        $subCount    = 0;
        $sortOrder   = 1;

        foreach ($masterData as $categoryName => $subTypes) {
            $category = InventoryCategory::create([
                'name'       => $categoryName,
                'slug'       => Str::slug($categoryName),
                'is_active'  => true,
                'sort_order' => $sortOrder++,
            ]);
            $catCount++;

            foreach ($subTypes as $subName) {
                InventorySubType::create([
                    'category_id' => $category->id,
                    'name'        => $subName,
                    'is_active'   => true,
                ]);
                $subCount++;
            }
        }

        $this->command->info("  ✓ Seeded {$catCount} categories and {$subCount} sub-types");
    }
}
