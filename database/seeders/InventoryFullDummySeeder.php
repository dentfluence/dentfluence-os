<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventorySubType;
use App\Models\Inventory\InventoryVendor;
use App\Models\Inventory\InventoryItem;

/**
 * InventoryFullDummySeeder
 * ─────────────────────────────────────────────────────────────────────────────
 * Adds realistic dummy data for:
 *  1. Inventory Vendors  (Promise Dental, Aatmaja, Dentalkart, Medikabazar…)
 *  2. Lab Vendors        (Katara Dental, DG Digital Lab…)
 *  3. Inventory Items:
 *       – Disposable Materials (Consumables)
 *       – Endodontic Files     (Hand Files + Rotary Files under Endodontics)
 *       – Composites           (Composites)
 *       – Implants + Components (Implant Systems)
 *
 * Requires: InventoryMasterDataSeeder to have been run (categories must exist).
 * Run: php artisan db:seed --class=InventoryFullDummySeeder
 * ─────────────────────────────────────────────────────────────────────────────
 */
class InventoryFullDummySeeder extends Seeder
{
    public function run(): void
    {
        $this->seedInventoryVendors();
        $this->seedLabVendors();
        $this->seedDisposables();
        $this->seedEndoFiles();
        $this->seedComposites();
        $this->seedImplants();

        $this->command->info('');
        $this->command->info('  ✓ InventoryFullDummySeeder complete!');
        $this->command->info('    Open: http://dentfluence.test/inventory');
    }

    /* ══════════════════════════════════════════════════════════
       1. INVENTORY VENDORS
    ══════════════════════════════════════════════════════════ */

    private function seedInventoryVendors(): void
    {
        $vendors = [
            [
                'vendor_name'    => 'Promise Dental Systems',
                'contact_person' => 'Rakesh Malhotra',
                'phone'          => '+91 98100 55432',
                'whatsapp'       => '+91 98100 55432',
                'email'          => 'rakesh@promisedental.com',
                'gst_no'         => '07AABCP1234K1ZX',
                'address'        => '42, Industrial Area, Phase II',
                'city'           => 'New Delhi',
                'state'          => 'Delhi',
                'pincode'        => '110020',
                'credit_days'    => 30,
                'notes'          => 'Primary supplier — implants and surgical kits',
                'is_active'      => true,
            ],
            [
                'vendor_name'    => 'Aatmaja Dental Pvt Ltd',
                'contact_person' => 'Sneha Joshi',
                'phone'          => '+91 96870 23411',
                'whatsapp'       => '+91 96870 23411',
                'email'          => 'sneha@aatmajadental.com',
                'gst_no'         => '27AACCA5671M1ZP',
                'address'        => 'Shop 4, Dental Arcade, Bandra West',
                'city'           => 'Mumbai',
                'state'          => 'Maharashtra',
                'pincode'        => '400050',
                'credit_days'    => 21,
                'notes'          => 'Local Mumbai distributor — good for urgent orders',
                'is_active'      => true,
            ],
            [
                'vendor_name'    => 'Dentalkart',
                'contact_person' => 'Support Team',
                'phone'          => '+91 1800 123 4567',
                'whatsapp'       => '+91 99990 00111',
                'email'          => 'orders@dentalkart.com',
                'gst_no'         => '07AAAKD9912R1ZQ',
                'address'        => 'B-12, Sector 63',
                'city'           => 'Noida',
                'state'          => 'Uttar Pradesh',
                'pincode'        => '201309',
                'credit_days'    => 0,
                'notes'          => 'Online marketplace — order via website. Prepaid only.',
                'is_active'      => true,
            ],
            [
                'vendor_name'    => 'Medikabazar',
                'contact_person' => 'Account Manager',
                'phone'          => '+91 80 4600 3900',
                'whatsapp'       => '+91 90041 11222',
                'email'          => 'dental@medikabazar.com',
                'gst_no'         => '29AABEM4567T1ZR',
                'address'        => '15, HSR Layout, Sector 3',
                'city'           => 'Bengaluru',
                'state'          => 'Karnataka',
                'pincode'        => '560102',
                'credit_days'    => 15,
                'notes'          => 'Good for disposables and consumables in bulk. Fast delivery.',
                'is_active'      => true,
            ],
            [
                'vendor_name'    => 'Orsing Implant India',
                'contact_person' => 'Dr. Vikas Sharma',
                'phone'          => '+91 93100 77654',
                'whatsapp'       => '+91 93100 77654',
                'email'          => 'vikas@orsingimplant.in',
                'gst_no'         => '06AABCO7821S1ZT',
                'address'        => 'Plot 19, Faridabad Industrial Belt',
                'city'           => 'Faridabad',
                'state'          => 'Haryana',
                'pincode'        => '121001',
                'credit_days'    => 45,
                'notes'          => 'Implant fixtures, cover screws, healing abutments',
                'is_active'      => true,
            ],
            [
                'vendor_name'    => 'Dent World Distributors',
                'contact_person' => 'Manish Gupta',
                'phone'          => '+91 79 2654 3210',
                'whatsapp'       => '+91 95125 44321',
                'email'          => 'manish@dentworlddist.com',
                'gst_no'         => '24AABMD2211P1ZU',
                'address'        => '9, Navrangpura Commercial Complex',
                'city'           => 'Ahmedabad',
                'state'          => 'Gujarat',
                'pincode'        => '380009',
                'credit_days'    => 30,
                'notes'          => 'Endodontic files, rotary systems, rubber dam accessories',
                'is_active'      => true,
            ],
        ];

        $count = 0;
        foreach ($vendors as $v) {
            InventoryVendor::firstOrCreate(['vendor_name' => $v['vendor_name']], $v);
            $count++;
        }

        $this->command->info("  ✓ Inventory vendors seeded ({$count})");
    }

    /* ══════════════════════════════════════════════════════════
       2. LAB VENDORS
    ══════════════════════════════════════════════════════════ */

    private function seedLabVendors(): void
    {
        $labs = [
            [
                'name'                   => 'Katara Dental Lab',
                'contact_person'         => 'Mr. Suresh Katara',
                'phone'                  => '+91 98231 54678',
                'whatsapp_number'        => '+91 98231 54678',
                'email'                  => 'katara.dental@gmail.com',
                'address'                => '3rd Floor, Dental Complex, Camp Road, Pune',
                'specialties'            => json_encode(['Crown & Bridge', 'Dentures', 'Metal Ceramic', 'Zirconia']),
                'default_turnaround_days'=> 5,
                'payment_terms'          => 'monthly_account',
                'is_active'              => true,
                'notes'                  => 'Trusted lab since 2018. Good quality zirconia and PFM crowns.',
                'branch_id'              => 1,
            ],
            [
                'name'                   => 'DG Digital Lab',
                'contact_person'         => 'Deepak Gajwani',
                'phone'                  => '+91 90111 23456',
                'whatsapp_number'        => '+91 90111 23456',
                'email'                  => 'dgdigitallab@yahoo.com',
                'address'                => 'A-14, MIDC, Andheri East, Mumbai',
                'specialties'            => json_encode(['Digital Workflow', 'Milled Zirconia', 'E.max Crowns', 'Implant Prosthetics', 'Aligners']),
                'default_turnaround_days'=> 4,
                'payment_terms'          => 'per_case',
                'is_active'              => true,
                'notes'                  => 'All-digital lab. Excellent for CAD/CAM prosthetics and implant abutments.',
                'branch_id'              => 1,
            ],
            [
                'name'                   => 'Pearl Dental Works',
                'contact_person'         => 'Nilesh Thakkar',
                'phone'                  => '+91 93222 78901',
                'whatsapp_number'        => '+91 93222 78901',
                'email'                  => 'nilesh@pearldentalworks.com',
                'address'                => '12, Shivaji Nagar, Nashik',
                'specialties'            => json_encode(['Dentures', 'Flexible Dentures', 'Chrome Cobalt Frameworks', 'Removable Partial Dentures']),
                'default_turnaround_days'=> 7,
                'payment_terms'          => 'per_case',
                'is_active'              => true,
                'notes'                  => 'Best for removable prosthodontics. BPS Dentures available.',
                'branch_id'              => 1,
            ],
            [
                'name'                   => 'Smile Craft Orthodontic Lab',
                'contact_person'         => 'Dr. Hemant Bane',
                'phone'                  => '+91 88880 12345',
                'whatsapp_number'        => '+91 88880 12345',
                'email'                  => 'info@smilecraftortho.com',
                'address'                => '7, Agarkar Nagar, FC Road, Pune',
                'specialties'            => json_encode(['Orthodontic Appliances', 'Retainers', 'Study Models', 'Surgical Guides']),
                'default_turnaround_days'=> 6,
                'payment_terms'          => 'monthly_account',
                'is_active'              => true,
                'notes'                  => 'Orthodontic lab. Also makes surgical guides for implants.',
                'branch_id'              => 1,
            ],
            [
                'name'                   => 'Rajasthan Ceramic Works',
                'contact_person'         => 'Mohan Lal Sharma',
                'phone'                  => '+91 94141 33567',
                'whatsapp_number'        => '+91 94141 33567',
                'email'                  => 'rcworks.jaipur@gmail.com',
                'address'                => '56, MI Road, Near Gaurav Tower, Jaipur',
                'specialties'            => json_encode(['Metal Ceramic Crowns', 'Full Cast Crowns', 'Cast Partials']),
                'default_turnaround_days'=> 8,
                'payment_terms'          => 'per_case',
                'is_active'              => false,
                'notes'                  => 'Backup lab. Currently inactive — quality under review.',
                'branch_id'              => 1,
            ],
        ];

        $count = 0;
        foreach ($labs as $lab) {
            $exists = DB::table('lab_vendors')->where('name', $lab['name'])->exists();
            if (! $exists) {
                DB::table('lab_vendors')->insert(array_merge($lab, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
                $count++;
            }
        }

        $this->command->info("  ✓ Lab vendors seeded ({$count} new)");
    }

    /* ══════════════════════════════════════════════════════════
       HELPER — resolve category ID by slug
    ══════════════════════════════════════════════════════════ */

    private function cat(string $slug): ?int
    {
        return InventoryCategory::where('slug', $slug)->value('id');
    }

    private function subType(int $categoryId, string $name): ?int
    {
        return InventorySubType::where('category_id', $categoryId)
            ->where('name', $name)
            ->value('id');
    }

    private function makeItem(array $data): void
    {
        // Generate unique item code
        $prefix = $data['_code_prefix'] ?? 'INV';
        unset($data['_code_prefix']);

        $lastCode = InventoryItem::where('item_code', 'like', $prefix . '-%')
            ->orderByDesc('item_code')
            ->value('item_code');

        if ($lastCode) {
            $num = (int) substr($lastCode, strlen($prefix) + 1);
            $num++;
        } else {
            $num = 1;
        }

        $data['item_code'] = $prefix . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
        $data['is_active'] = true;

        InventoryItem::firstOrCreate(
            ['product_name' => $data['product_name']],
            $data
        );
    }

    /* ══════════════════════════════════════════════════════════
       3. DISPOSABLE MATERIALS (Consumables category)
    ══════════════════════════════════════════════════════════ */

    private function seedDisposables(): void
    {
        $catId = $this->cat('consumables');

        if (! $catId) {
            $this->command->warn('  ✗ "Consumables" category not found. Run InventoryMasterDataSeeder first.');
            return;
        }

        $items = [
            [
                'product_name'           => 'Saliva Ejectors (White) — Pack of 100',
                'generic_name'           => 'Saliva Ejector',
                'brand'                  => 'Indosurgicals',
                'company_name'           => 'Indosurgicals Pvt Ltd',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Saliva Ejectors'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Pack',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 100,
                'packaging_type'         => 'Pack',
                'qty_in_packaging'       => 100,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '100 pieces/pack',
                'last_purchase_price'    => 85.00,
                'average_purchase_price' => 82.00,
                'mrp'                    => 110.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['All Procedures']),
                '_code_prefix'           => 'DISP',
            ],
            [
                'product_name'           => 'HVE Suction Tips (High Volume Evacuator)',
                'generic_name'           => 'HVE Tip',
                'brand'                  => 'Medicom',
                'company_name'           => 'Medicom India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Suction Tips'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Bag',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 100,
                'packaging_type'         => 'Bag',
                'qty_in_packaging'       => 100,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '100 pieces/bag',
                'last_purchase_price'    => 320.00,
                'average_purchase_price' => 310.00,
                'mrp'                    => 400.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 3,
                'reorder_level'          => 5,
                'minimum_order_qty'      => 6,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['All Procedures']),
                '_code_prefix'           => 'DISP',
            ],
            [
                'product_name'           => 'Microbrush Applicators (Regular) — 400pcs',
                'generic_name'           => 'Microbrush',
                'brand'                  => 'Microbrush International',
                'company_name'           => 'Microbrush International',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Microbrushes'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Box',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 400,
                'packaging_type'         => 'Box',
                'qty_in_packaging'       => 400,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '400 pcs/box',
                'last_purchase_price'    => 420.00,
                'average_purchase_price' => 400.00,
                'mrp'                    => 550.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 3,
                'reorder_level'          => 5,
                'minimum_order_qty'      => 5,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['Composite Bonding', 'Sealants', 'Cements']),
                '_code_prefix'           => 'DISP',
            ],
            [
                'product_name'           => 'Cotton Pellets — Jar of 2000',
                'generic_name'           => 'Cotton Pellet',
                'brand'                  => 'Dentsply',
                'company_name'           => 'Dentsply Sirona India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Cotton Pellets'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Jar',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 2000,
                'packaging_type'         => 'Jar',
                'qty_in_packaging'       => 2000,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '2000 pieces/jar',
                'last_purchase_price'    => 180.00,
                'average_purchase_price' => 175.00,
                'mrp'                    => 220.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 3,
                'reorder_level'          => 5,
                'minimum_order_qty'      => 6,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['RCT', 'Restorations', 'Cavity Prep']),
                '_code_prefix'           => 'DISP',
            ],
            [
                'product_name'           => 'Gauze Swabs 2x2 inch — Non-Sterile 100pcs',
                'generic_name'           => 'Gauze Swab',
                'brand'                  => 'Romsons',
                'company_name'           => 'Romsons Group of Industries',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Gauze'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Pack',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 100,
                'packaging_type'         => 'Pack',
                'qty_in_packaging'       => 100,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '100 pcs/pack',
                'last_purchase_price'    => 65.00,
                'average_purchase_price' => 62.00,
                'mrp'                    => 85.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 10,
                'reorder_level'          => 15,
                'minimum_order_qty'      => 20,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['Surgical Extraction', 'All Procedures']),
                '_code_prefix'           => 'DISP',
            ],
            [
                'product_name'           => 'Dental Bibs (Neck Napkins) — Box of 500',
                'generic_name'           => 'Patient Bib',
                'brand'                  => 'Medicom',
                'company_name'           => 'Medicom India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Cotton Rolls'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Box',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 500,
                'packaging_type'         => 'Box',
                'qty_in_packaging'       => 500,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '500 pcs/box',
                'last_purchase_price'    => 480.00,
                'average_purchase_price' => 470.00,
                'mrp'                    => 600.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['All Procedures']),
                '_code_prefix'           => 'DISP',
            ],
            [
                'product_name'           => 'Disposable Air/Water Syringe Tips — 250pcs',
                'generic_name'           => '3-in-1 Syringe Tip',
                'brand'                  => 'Indosurgicals',
                'company_name'           => 'Indosurgicals Pvt Ltd',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Saliva Ejectors'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Pack',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 250,
                'packaging_type'         => 'Pack',
                'qty_in_packaging'       => 250,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '250 pcs/pack',
                'last_purchase_price'    => 210.00,
                'average_purchase_price' => 205.00,
                'mrp'                    => 275.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['All Procedures']),
                '_code_prefix'           => 'DISP',
            ],
        ];

        $count = 0;
        foreach ($items as $item) {
            $this->makeItem($item);
            $count++;
        }

        $this->command->info("  ✓ Disposable Materials seeded ({$count} items)");
    }

    /* ══════════════════════════════════════════════════════════
       4. ENDODONTIC FILES
    ══════════════════════════════════════════════════════════ */

    private function seedEndoFiles(): void
    {
        $catId = $this->cat('endodontics');

        if (! $catId) {
            $this->command->warn('  ✗ "Endodontics" category not found. Run InventoryMasterDataSeeder first.');
            return;
        }

        $items = [
            /* ── Hand Files ── */
            [
                'product_name'           => 'K-Files Stainless Steel #15-40 (21mm) — Box of 6',
                'generic_name'           => 'K-File',
                'brand'                  => 'Mani',
                'company_name'           => 'Mani Inc. (Japan)',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Hand Files'),
                'inventory_behavior'     => 'semi_reusable',
                'usage_type'             => 'multiple_use',
                'purchase_unit'          => 'Box',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 6,
                'packaging_type'         => 'Box',
                'qty_in_packaging'       => 6,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '6 files/box',
                'last_purchase_price'    => 420.00,
                'average_purchase_price' => 410.00,
                'mrp'                    => 550.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => false,
                'is_reusable'            => true,
                'tracking_type'          => 'usage_based',
                'max_usage_count'        => 3,
                'sterilization_required' => true,
                'treatment_tags'         => json_encode(['Root Canal Treatment']),
                '_code_prefix'           => 'ENDO',
            ],
            [
                'product_name'           => 'H-Files (Hedstrom) Stainless Steel #20-60 (25mm)',
                'generic_name'           => 'Hedstrom File',
                'brand'                  => 'Mani',
                'company_name'           => 'Mani Inc. (Japan)',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Hand Files'),
                'inventory_behavior'     => 'semi_reusable',
                'usage_type'             => 'multiple_use',
                'purchase_unit'          => 'Box',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 6,
                'packaging_type'         => 'Box',
                'qty_in_packaging'       => 6,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '6 files/box',
                'last_purchase_price'    => 440.00,
                'average_purchase_price' => 430.00,
                'mrp'                    => 580.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 4,
                'reorder_level'          => 6,
                'minimum_order_qty'      => 8,
                'has_expiry'             => false,
                'is_reusable'            => true,
                'tracking_type'          => 'usage_based',
                'max_usage_count'        => 3,
                'sterilization_required' => true,
                'treatment_tags'         => json_encode(['Root Canal Treatment']),
                '_code_prefix'           => 'ENDO',
            ],
            [
                'product_name'           => 'C-Pilot Files #8 & #10 (21mm) — Stainless Steel',
                'generic_name'           => 'C-Pilot File / Pathfinder File',
                'brand'                  => 'Dentsply Maillefer',
                'company_name'           => 'Dentsply Sirona India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Hand Files'),
                'inventory_behavior'     => 'semi_reusable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Box',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 6,
                'packaging_type'         => 'Box',
                'qty_in_packaging'       => 6,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '6 files/box',
                'last_purchase_price'    => 580.00,
                'average_purchase_price' => 560.00,
                'mrp'                    => 750.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 4,
                'reorder_level'          => 6,
                'minimum_order_qty'      => 8,
                'has_expiry'             => false,
                'is_reusable'            => true,
                'tracking_type'          => 'usage_based',
                'max_usage_count'        => 2,
                'sterilization_required' => true,
                'treatment_tags'         => json_encode(['Root Canal Treatment', 'Canal Glide Path']),
                '_code_prefix'           => 'ENDO',
            ],
            /* ── Rotary Files ── */
            [
                'product_name'           => 'ProTaper NEXT X1–X5 Rotary Files (25mm)',
                'generic_name'           => 'NiTi Rotary File — Progressive Taper',
                'brand'                  => 'Dentsply Maillefer',
                'company_name'           => 'Dentsply Sirona India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Rotary Files'),
                'inventory_behavior'     => 'semi_reusable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Pack',
                'consumption_unit'       => 'Set',
                'pieces_per_unit'        => 5,
                'packaging_type'         => 'Pack',
                'qty_in_packaging'       => 5,
                'packaging_unit_label'   => 'sets',
                'pack_size_label'        => '5 sets/pack',
                'last_purchase_price'    => 3200.00,
                'average_purchase_price' => 3100.00,
                'mrp'                    => 4200.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 3,
                'reorder_level'          => 5,
                'minimum_order_qty'      => 6,
                'has_expiry'             => false,
                'is_reusable'            => true,
                'tracking_type'          => 'usage_based',
                'max_usage_count'        => 4,
                'sterilization_required' => true,
                'treatment_tags'         => json_encode(['Root Canal Treatment', 'Rotary RCT']),
                '_code_prefix'           => 'ENDO',
            ],
            [
                'product_name'           => 'WaveOne Gold Primary 25/.07 Reciprocating File',
                'generic_name'           => 'Reciprocating NiTi File',
                'brand'                  => 'Dentsply Maillefer',
                'company_name'           => 'Dentsply Sirona India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Rotary Files'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Pack',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 3,
                'packaging_type'         => 'Pack',
                'qty_in_packaging'       => 3,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '3 files/pack',
                'last_purchase_price'    => 2400.00,
                'average_purchase_price' => 2350.00,
                'mrp'                    => 3200.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 3,
                'reorder_level'          => 5,
                'minimum_order_qty'      => 6,
                'has_expiry'             => false,
                'sterilization_required' => true,
                'treatment_tags'         => json_encode(['Root Canal Treatment', 'Single-File System']),
                '_code_prefix'           => 'ENDO',
            ],
            [
                'product_name'           => 'Hyflex CM NiTi Rotary Files 25/.04 (25mm) — 6pcs',
                'generic_name'           => 'CM-Wire NiTi Rotary File',
                'brand'                  => 'Coltene',
                'company_name'           => 'Coltene India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Rotary Files'),
                'inventory_behavior'     => 'semi_reusable',
                'usage_type'             => 'multiple_use',
                'purchase_unit'          => 'Pack',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 6,
                'packaging_type'         => 'Pack',
                'qty_in_packaging'       => 6,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '6 files/pack',
                'last_purchase_price'    => 1800.00,
                'average_purchase_price' => 1750.00,
                'mrp'                    => 2400.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 4,
                'reorder_level'          => 6,
                'minimum_order_qty'      => 8,
                'has_expiry'             => false,
                'is_reusable'            => true,
                'tracking_type'          => 'usage_based',
                'max_usage_count'        => 5,
                'sterilization_required' => true,
                'treatment_tags'         => json_encode(['Root Canal Treatment']),
                '_code_prefix'           => 'ENDO',
            ],
            [
                'product_name'           => 'Endo Ruler / Stop Organisers (Rubber Endo Stops)',
                'generic_name'           => 'Rubber Endo Stop',
                'brand'                  => 'Mani',
                'company_name'           => 'Mani Inc. (Japan)',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Endo Accessories'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Bag',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 100,
                'packaging_type'         => 'Bag',
                'qty_in_packaging'       => 100,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '100 pcs/bag',
                'last_purchase_price'    => 120.00,
                'average_purchase_price' => 115.00,
                'mrp'                    => 160.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['Root Canal Treatment']),
                '_code_prefix'           => 'ENDO',
            ],
            [
                'product_name'           => 'Gutta Percha Points #25 (Standardized) — 120pcs',
                'generic_name'           => 'Gutta Percha Point',
                'brand'                  => 'Dentsply Maillefer',
                'company_name'           => 'Dentsply Sirona India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Gutta Percha'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Box',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 120,
                'packaging_type'         => 'Box',
                'qty_in_packaging'       => 120,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '120 points/box',
                'last_purchase_price'    => 380.00,
                'average_purchase_price' => 370.00,
                'mrp'                    => 500.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 4,
                'reorder_level'          => 6,
                'minimum_order_qty'      => 8,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['Root Canal Obturation']),
                '_code_prefix'           => 'ENDO',
            ],
            [
                'product_name'           => 'Paper Points #20–40 Absorbent (200pcs)',
                'generic_name'           => 'Absorbent Paper Point',
                'brand'                  => 'Roeko',
                'company_name'           => 'Coltene India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Paper Points'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Box',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 200,
                'packaging_type'         => 'Box',
                'qty_in_packaging'       => 200,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => '200 points/box',
                'last_purchase_price'    => 280.00,
                'average_purchase_price' => 270.00,
                'mrp'                    => 370.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['Root Canal Treatment', 'Canal Drying']),
                '_code_prefix'           => 'ENDO',
            ],
            [
                'product_name'           => 'AH Plus Epoxy Resin Root Canal Sealer (4g)',
                'generic_name'           => 'Epoxy Resin Sealer',
                'brand'                  => 'Dentsply Maillefer',
                'company_name'           => 'Dentsply Sirona India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Sealers'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'multiple_use',
                'purchase_unit'          => 'Kit',
                'consumption_unit'       => 'Kit',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Kit',
                'qty_in_packaging'       => 4,
                'packaging_unit_label'   => 'g',
                'pack_size_label'        => '4g Kit',
                'last_purchase_price'    => 1800.00,
                'average_purchase_price' => 1750.00,
                'mrp'                    => 2400.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 2,
                'reorder_level'          => 3,
                'minimum_order_qty'      => 4,
                'has_expiry'             => true,
                'shelf_life_months'      => 24,
                'expiry_alert_days'      => 90,
                'treatment_tags'         => json_encode(['Root Canal Obturation']),
                '_code_prefix'           => 'ENDO',
            ],
        ];

        $count = 0;
        foreach ($items as $item) {
            $this->makeItem($item);
            $count++;
        }

        $this->command->info("  ✓ Endodontic Files seeded ({$count} items)");
    }

    /* ══════════════════════════════════════════════════════════
       5. COMPOSITES
    ══════════════════════════════════════════════════════════ */

    private function seedComposites(): void
    {
        $catId = $this->cat('composites');

        if (! $catId) {
            $this->command->warn('  ✗ "Composites" category not found. Run InventoryMasterDataSeeder first.');
            return;
        }

        $items = [
            [
                'product_name'           => 'Tetric N-Ceram Bulk Fill A2 (3.5g Syringe)',
                'generic_name'           => 'Bulk Fill Nanohybrid Composite',
                'brand'                  => 'Tetric N-Ceram',
                'company_name'           => 'Ivoclar Vivadent India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Bulk Fill Composite'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'multiple_use',
                'purchase_unit'          => 'Syringe',
                'consumption_unit'       => 'Syringe',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Syringe',
                'qty_in_packaging'       => 3.5,
                'packaging_unit_label'   => 'g',
                'pack_size_label'        => '3.5g Syringe',
                'last_purchase_price'    => 2100.00,
                'average_purchase_price' => 2050.00,
                'mrp'                    => 2800.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 3,
                'reorder_level'          => 5,
                'minimum_order_qty'      => 6,
                'has_expiry'             => true,
                'shelf_life_months'      => 30,
                'expiry_alert_days'      => 90,
                'alternative_brands'     => json_encode(['Filtek Bulk Fill (3M)', 'SDR Plus (Dentsply)', 'X-tra fil (VOCO)']),
                'treatment_tags'         => json_encode(['Posterior Composite', 'Class I', 'Class II']),
                '_code_prefix'           => 'COMP',
            ],
            [
                'product_name'           => 'Filtek Z350 XT Universal Composite A2 (4g)',
                'generic_name'           => 'Universal Nanofilled Composite Resin',
                'brand'                  => 'Filtek Z350 XT',
                'company_name'           => '3M India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Universal Composite'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'multiple_use',
                'purchase_unit'          => 'Syringe',
                'consumption_unit'       => 'Syringe',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Syringe',
                'qty_in_packaging'       => 4,
                'packaging_unit_label'   => 'g',
                'pack_size_label'        => '4g Syringe',
                'last_purchase_price'    => 2400.00,
                'average_purchase_price' => 2350.00,
                'mrp'                    => 3200.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 3,
                'reorder_level'          => 5,
                'minimum_order_qty'      => 6,
                'has_expiry'             => true,
                'shelf_life_months'      => 36,
                'expiry_alert_days'      => 90,
                'alternative_brands'     => json_encode(['Tetric N-Ceram (Ivoclar)', 'Herculite Ultra (Kerr)']),
                'treatment_tags'         => json_encode(['Anterior Composite', 'Posterior Composite', 'Direct Veneers']),
                '_code_prefix'           => 'COMP',
            ],
            [
                'product_name'           => 'Filtek Z350 XT Universal Composite A3 (4g)',
                'generic_name'           => 'Universal Nanofilled Composite Resin',
                'brand'                  => 'Filtek Z350 XT',
                'company_name'           => '3M India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Universal Composite'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'multiple_use',
                'purchase_unit'          => 'Syringe',
                'consumption_unit'       => 'Syringe',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Syringe',
                'qty_in_packaging'       => 4,
                'packaging_unit_label'   => 'g',
                'pack_size_label'        => '4g Syringe',
                'last_purchase_price'    => 2400.00,
                'average_purchase_price' => 2350.00,
                'mrp'                    => 3200.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 3,
                'reorder_level'          => 5,
                'minimum_order_qty'      => 6,
                'has_expiry'             => true,
                'shelf_life_months'      => 36,
                'expiry_alert_days'      => 90,
                'treatment_tags'         => json_encode(['Posterior Composite', 'Class I', 'Class II']),
                '_code_prefix'           => 'COMP',
            ],
            [
                'product_name'           => 'Estelite Sigma Quick A2 Composite (3.8g)',
                'generic_name'           => 'Spherical Filler Composite Resin',
                'brand'                  => 'Estelite Sigma Quick',
                'company_name'           => 'Tokuyama Dental India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Universal Composite'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'multiple_use',
                'purchase_unit'          => 'Syringe',
                'consumption_unit'       => 'Syringe',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Syringe',
                'qty_in_packaging'       => 3.8,
                'packaging_unit_label'   => 'g',
                'pack_size_label'        => '3.8g Syringe',
                'last_purchase_price'    => 2200.00,
                'average_purchase_price' => 2150.00,
                'mrp'                    => 3000.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 2,
                'reorder_level'          => 4,
                'minimum_order_qty'      => 5,
                'has_expiry'             => true,
                'shelf_life_months'      => 24,
                'expiry_alert_days'      => 90,
                'treatment_tags'         => json_encode(['Anterior Composite', 'Direct Veneers']),
                '_code_prefix'           => 'COMP',
            ],
            [
                'product_name'           => 'Filtek Supreme Ultra Flowable A2 (2g Syringe)',
                'generic_name'           => 'Flowable Nanofilled Composite',
                'brand'                  => 'Filtek Supreme Ultra',
                'company_name'           => '3M India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Flowable Composite'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'multiple_use',
                'purchase_unit'          => 'Syringe',
                'consumption_unit'       => 'Syringe',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Syringe',
                'qty_in_packaging'       => 2,
                'packaging_unit_label'   => 'g',
                'pack_size_label'        => '2g Syringe',
                'last_purchase_price'    => 1600.00,
                'average_purchase_price' => 1550.00,
                'mrp'                    => 2100.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 3,
                'reorder_level'          => 5,
                'minimum_order_qty'      => 6,
                'has_expiry'             => true,
                'shelf_life_months'      => 30,
                'expiry_alert_days'      => 90,
                'alternative_brands'     => json_encode(['Tetric EvoFlow (Ivoclar)', 'SDR Flow (Dentsply)']),
                'treatment_tags'         => json_encode(['Class I Lining', 'Pit and Fissure Sealant', 'Minimal Cavity']),
                '_code_prefix'           => 'COMP',
            ],
            [
                'product_name'           => 'Herculite Ultra Packable Composite A3.5 (5g)',
                'generic_name'           => 'Packable Hybrid Composite',
                'brand'                  => 'Herculite Ultra',
                'company_name'           => 'Kerr Dental India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Packable Composite'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'multiple_use',
                'purchase_unit'          => 'Syringe',
                'consumption_unit'       => 'Syringe',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Syringe',
                'qty_in_packaging'       => 5,
                'packaging_unit_label'   => 'g',
                'pack_size_label'        => '5g Syringe',
                'last_purchase_price'    => 2600.00,
                'average_purchase_price' => 2550.00,
                'mrp'                    => 3500.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 2,
                'reorder_level'          => 4,
                'minimum_order_qty'      => 5,
                'has_expiry'             => true,
                'shelf_life_months'      => 30,
                'expiry_alert_days'      => 90,
                'treatment_tags'         => json_encode(['Posterior Composite', 'Class II MOD']),
                '_code_prefix'           => 'COMP',
            ],
        ];

        $count = 0;
        foreach ($items as $item) {
            $this->makeItem($item);
            $count++;
        }

        $this->command->info("  ✓ Composites seeded ({$count} items)");
    }

    /* ══════════════════════════════════════════════════════════
       6. IMPLANTS + COMPONENTS
    ══════════════════════════════════════════════════════════ */

    private function seedImplants(): void
    {
        $catId = $this->cat('implant-systems');

        if (! $catId) {
            $this->command->warn('  ✗ "Implant Systems" category not found. Run InventoryMasterDataSeeder first.');
            return;
        }

        $items = [
            /* ── Implant Fixtures ── */
            [
                'product_name'           => 'Straumann BL 4.1×10mm SLActive Implant Fixture',
                'generic_name'           => 'Bone Level Tapered Internal Hex Implant',
                'brand'                  => 'Straumann BLT',
                'company_name'           => 'Straumann India Pvt Ltd',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Implant Fixtures'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Piece',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Vial',
                'qty_in_packaging'       => 1,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => 'Single sterile vial',
                'last_purchase_price'    => 22000.00,
                'average_purchase_price' => 21500.00,
                'mrp'                    => 32000.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 3,
                'reorder_level'          => 5,
                'minimum_order_qty'      => 5,
                'has_expiry'             => true,
                'shelf_life_months'      => 60,
                'expiry_alert_days'      => 180,
                'treatment_tags'         => json_encode(['Dental Implant', 'Implant Placement']),
                '_code_prefix'           => 'IMP',
            ],
            [
                'product_name'           => 'Osstem TS III SA 4.0×10mm Implant Fixture',
                'generic_name'           => 'Tapered Slotted Internal Hex Implant',
                'brand'                  => 'Osstem TS III',
                'company_name'           => 'Osstem Implant India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Implant Fixtures'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Piece',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Vial',
                'qty_in_packaging'       => 1,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => 'Single sterile vial',
                'last_purchase_price'    => 14500.00,
                'average_purchase_price' => 14200.00,
                'mrp'                    => 22000.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => true,
                'shelf_life_months'      => 60,
                'expiry_alert_days'      => 180,
                'alternative_brands'     => json_encode(['Straumann BL (Straumann)', 'Nobel Active (Nobel Biocare)']),
                'treatment_tags'         => json_encode(['Dental Implant', 'Implant Placement']),
                '_code_prefix'           => 'IMP',
            ],
            [
                'product_name'           => 'Osstem TS III SA 4.0×12mm Implant Fixture',
                'generic_name'           => 'Tapered Slotted Internal Hex Implant — Long',
                'brand'                  => 'Osstem TS III',
                'company_name'           => 'Osstem Implant India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Implant Fixtures'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Piece',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Vial',
                'qty_in_packaging'       => 1,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => 'Single sterile vial',
                'last_purchase_price'    => 14500.00,
                'average_purchase_price' => 14200.00,
                'mrp'                    => 22000.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 3,
                'reorder_level'          => 5,
                'minimum_order_qty'      => 5,
                'has_expiry'             => true,
                'shelf_life_months'      => 60,
                'expiry_alert_days'      => 180,
                'treatment_tags'         => json_encode(['Dental Implant', 'Implant Placement']),
                '_code_prefix'           => 'IMP',
            ],
            [
                'product_name'           => 'Orsing 3.75×10mm Internal Hex Implant — Standard',
                'generic_name'           => 'Cylindrical Internal Hex Implant',
                'brand'                  => 'Orsing',
                'company_name'           => 'Orsing Implant India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Implant Fixtures'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Piece',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Vial',
                'qty_in_packaging'       => 1,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => 'Single sterile vial',
                'last_purchase_price'    => 8500.00,
                'average_purchase_price' => 8200.00,
                'mrp'                    => 14000.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => true,
                'shelf_life_months'      => 60,
                'expiry_alert_days'      => 180,
                'treatment_tags'         => json_encode(['Dental Implant', 'Budget Implant Protocol']),
                '_code_prefix'           => 'IMP',
            ],
            /* ── Healing Abutments ── */
            [
                'product_name'           => 'Straumann Healing Abutment 4.5×5mm (Bone Level)',
                'generic_name'           => 'Healing Abutment / Healing Cap',
                'brand'                  => 'Straumann',
                'company_name'           => 'Straumann India Pvt Ltd',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Healing Abutments'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Piece',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Blister',
                'qty_in_packaging'       => 1,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => 'Single unit',
                'last_purchase_price'    => 2800.00,
                'average_purchase_price' => 2750.00,
                'mrp'                    => 4200.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['Implant Stage 2', 'Gingival Contouring']),
                '_code_prefix'           => 'IMP',
            ],
            [
                'product_name'           => 'Osstem Healing Abutment 4.5×5mm (TS System)',
                'generic_name'           => 'Healing Abutment',
                'brand'                  => 'Osstem',
                'company_name'           => 'Osstem Implant India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Healing Abutments'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Piece',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Blister',
                'qty_in_packaging'       => 1,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => 'Single unit',
                'last_purchase_price'    => 1400.00,
                'average_purchase_price' => 1350.00,
                'mrp'                    => 2200.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['Implant Stage 2']),
                '_code_prefix'           => 'IMP',
            ],
            /* ── Cover Screws ── */
            [
                'product_name'           => 'Straumann Cover Screw (Bone Level) — RC 4.1',
                'generic_name'           => 'Cover Screw / Closure Screw',
                'brand'                  => 'Straumann',
                'company_name'           => 'Straumann India Pvt Ltd',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Cover Screws'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Piece',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Blister',
                'qty_in_packaging'       => 1,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => 'Single unit',
                'last_purchase_price'    => 1200.00,
                'average_purchase_price' => 1150.00,
                'mrp'                    => 1800.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['Implant Placement', 'Submerged Protocol']),
                '_code_prefix'           => 'IMP',
            ],
            [
                'product_name'           => 'Osstem Cover Screw — TS System 4.0',
                'generic_name'           => 'Cover Screw',
                'brand'                  => 'Osstem',
                'company_name'           => 'Osstem Implant India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Cover Screws'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Piece',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Blister',
                'qty_in_packaging'       => 1,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => 'Single unit',
                'last_purchase_price'    => 600.00,
                'average_purchase_price' => 580.00,
                'mrp'                    => 900.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['Implant Placement', 'Submerged Protocol']),
                '_code_prefix'           => 'IMP',
            ],
            /* ── Implant Analogues ── */
            [
                'product_name'           => 'Straumann BL RC 4.1 Implant Analogue (Lab)',
                'generic_name'           => 'Implant Replica / Lab Analogue',
                'brand'                  => 'Straumann',
                'company_name'           => 'Straumann India Pvt Ltd',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Implant Analogues'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Piece',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Blister',
                'qty_in_packaging'       => 1,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => 'Single unit',
                'last_purchase_price'    => 1500.00,
                'average_purchase_price' => 1450.00,
                'mrp'                    => 2200.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['Implant Prosthetics', 'Lab Work']),
                '_code_prefix'           => 'IMP',
            ],
            [
                'product_name'           => 'Osstem TS Implant Analogue 4.0 (Lab)',
                'generic_name'           => 'Implant Replica / Lab Analogue',
                'brand'                  => 'Osstem',
                'company_name'           => 'Osstem Implant India',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Implant Analogues'),
                'inventory_behavior'     => 'consumable',
                'usage_type'             => 'single_use',
                'purchase_unit'          => 'Piece',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Blister',
                'qty_in_packaging'       => 1,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => 'Single unit',
                'last_purchase_price'    => 700.00,
                'average_purchase_price' => 680.00,
                'mrp'                    => 1100.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 5,
                'reorder_level'          => 8,
                'minimum_order_qty'      => 10,
                'has_expiry'             => false,
                'treatment_tags'         => json_encode(['Implant Prosthetics', 'Lab Work']),
                '_code_prefix'           => 'IMP',
            ],
            /* ── Scan Bodies ── */
            [
                'product_name'           => 'Straumann BL RC 4.1 Scan Body (Digital Workflow)',
                'generic_name'           => 'Scan Body / Scanbody',
                'brand'                  => 'Straumann',
                'company_name'           => 'Straumann India Pvt Ltd',
                'category_id'            => $catId,
                'sub_type_id'            => $this->subType($catId, 'Scan Bodies'),
                'inventory_behavior'     => 'reusable',
                'usage_type'             => 'multiple_use',
                'purchase_unit'          => 'Piece',
                'consumption_unit'       => 'Piece',
                'pieces_per_unit'        => 1,
                'packaging_type'         => 'Blister',
                'qty_in_packaging'       => 1,
                'packaging_unit_label'   => 'pcs',
                'pack_size_label'        => 'Single unit',
                'last_purchase_price'    => 4500.00,
                'average_purchase_price' => 4400.00,
                'mrp'                    => 7000.00,
                'gst_rate'               => 12,
                'minimum_qty'            => 2,
                'reorder_level'          => 3,
                'minimum_order_qty'      => 4,
                'has_expiry'             => false,
                'is_reusable'            => true,
                'tracking_type'          => 'usage_based',
                'max_usage_count'        => 20,
                'sterilization_required' => true,
                'treatment_tags'         => json_encode(['Digital Implant Impressions', 'CAD/CAM Crown']),
                '_code_prefix'           => 'IMP',
            ],
        ];

        $count = 0;
        foreach ($items as $item) {
            $this->makeItem($item);
            $count++;
        }

        $this->command->info("  ✓ Implants & Components seeded ({$count} items)");
    }
}
