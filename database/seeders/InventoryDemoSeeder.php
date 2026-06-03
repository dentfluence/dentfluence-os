<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryVendor;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\PurchaseOrderItem;
use Carbon\Carbon;

/**
 * InventoryDemoSeeder
 * -------------------
 * Seeds realistic dental clinic inventory data for UI testing.
 * Requires InventorySeeder to have been run first (locations + categories must exist).
 *
 * Run:  php artisan db:seed --class=InventoryDemoSeeder
 * Reset: php artisan migrate:fresh --seed   (runs DatabaseSeeder)
 */
class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        // Make sure base seeder has run
        $this->call(InventorySeeder::class);

        // Also run UserSeeder if no users exist (purchase orders need created_by)
        if (DB::table('users')->count() === 0) {
            $this->call(\Database\Seeders\UserSeeder::class);
        }

        $this->seedVendors();
        $this->seedItems();
        $this->seedStockMovements();
        $this->seedPurchaseOrders();

        $this->command->info('');
        $this->command->info('  ✓ InventoryDemoSeeder complete — all pages now have data.');
        $this->command->info('    Open: http://dentfluence.test/inventory');
    }

    /* ══════════════════════════════════════════════════════════
       VENDORS
    ══════════════════════════════════════════════════════════ */

    private function seedVendors(): void
    {
        $vendors = [
            [
                'vendor_name'    => 'Dentsply Sirona India',
                'contact_person' => 'Rajesh Kumar',
                'phone'          => '+91 98200 11234',
                'whatsapp'       => '+91 98200 11234',
                'email'          => 'rajesh@dentsply.in',
                'gst_no'         => '27AABCD1234R1ZX',
                'city'           => 'Mumbai',
                'state'          => 'Maharashtra',
                'credit_days'    => 30,
                'is_active'      => true,
            ],
            [
                'vendor_name'    => 'GC India Dental Products',
                'contact_person' => 'Priya Sharma',
                'phone'          => '+91 80 4567 8901',
                'whatsapp'       => '+91 97410 55678',
                'email'          => 'priya.sharma@gc-dental.in',
                'gst_no'         => '29AABGE4567S1ZP',
                'city'           => 'Bengaluru',
                'state'          => 'Karnataka',
                'credit_days'    => 21,
                'is_active'      => true,
            ],
            [
                'vendor_name'    => 'Nobel Biocare India',
                'contact_person' => 'Amit Verma',
                'phone'          => '+91 22 6789 0123',
                'whatsapp'       => '+91 99876 54321',
                'email'          => 'amit.verma@nobelbiocare.com',
                'gst_no'         => '27AACNB7890K1ZQ',
                'city'           => 'Mumbai',
                'state'          => 'Maharashtra',
                'credit_days'    => 45,
                'is_active'      => true,
            ],
            [
                'vendor_name'    => 'Septodont India Pvt Ltd',
                'contact_person' => 'Kavitha Nair',
                'phone'          => '+91 44 2345 6789',
                'whatsapp'       => '+91 95550 33221',
                'email'          => 'kavitha@septodont.in',
                'gst_no'         => '33AAACS5678M1ZR',
                'city'           => 'Chennai',
                'state'          => 'Tamil Nadu',
                'credit_days'    => 30,
                'is_active'      => true,
            ],
            [
                'vendor_name'    => 'Maarc Dental Products',
                'contact_person' => 'Suresh Patel',
                'phone'          => '+91 79 3456 7890',
                'whatsapp'       => '+91 94260 77889',
                'email'          => 'suresh@maarcdentalproducts.com',
                'gst_no'         => '24AABCM9012P1ZT',
                'city'           => 'Ahmedabad',
                'state'          => 'Gujarat',
                'credit_days'    => 15,
                'is_active'      => true,
            ],
        ];

        foreach ($vendors as $v) {
            InventoryVendor::firstOrCreate(['vendor_name' => $v['vendor_name']], $v);
        }
        $this->command->info('  ✓ Vendors seeded (5)');
    }

    /* ══════════════════════════════════════════════════════════
       INVENTORY ITEMS
    ══════════════════════════════════════════════════════════ */

    private function seedItems(): void
    {
        // Resolve category IDs
        $cat = fn(string $slug) => InventoryCategory::where('slug', $slug)->value('id');

        $items = [
            /* ── CONSUMABLES ── */
            [
                'product_name'          => 'Nitrile Examination Gloves (M)',
                'generic_name'          => 'Nitrile Gloves',
                'category_id'           => $cat('gloves'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Box',
                'consumption_unit'      => 'Pair',
                'pieces_per_unit'       => 100,
                'minimum_qty'           => 5,
                'minimum_order_qty'     => 10,
                'last_purchase_price'   => 320.00,
                'average_purchase_price'=> 310.00,
                'mrp'                   => null,
                'has_expiry'            => false,
            ],
            [
                'product_name'          => '3-Ply Surgical Face Masks',
                'generic_name'          => 'Surgical Mask',
                'category_id'           => $cat('masks-ppe'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Box',
                'consumption_unit'      => 'Piece',
                'pieces_per_unit'       => 50,
                'minimum_qty'           => 4,
                'minimum_order_qty'     => 8,
                'last_purchase_price'   => 180.00,
                'average_purchase_price'=> 175.00,
                'mrp'                   => null,
                'has_expiry'            => false,
            ],
            [
                'product_name'          => 'Cotton Rolls (Non-Sterile)',
                'generic_name'          => 'Cotton Roll',
                'category_id'           => $cat('cotton-gauze'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Packet',
                'consumption_unit'      => 'Piece',
                'pieces_per_unit'       => 200,
                'minimum_qty'           => 3,
                'minimum_order_qty'     => 6,
                'last_purchase_price'   => 95.00,
                'average_purchase_price'=> 90.00,
                'mrp'                   => null,
                'has_expiry'            => false,
            ],

            /* ── RESTORATIVES ── */
            [
                'product_name'          => 'Tetric N-Ceram Composite A2',
                'generic_name'          => 'Nanohybrid Composite Resin',
                'category_id'           => $cat('composites'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Syringe',
                'consumption_unit'      => 'ml',
                'pieces_per_unit'       => 1,
                'minimum_qty'           => 2,
                'minimum_order_qty'     => 5,
                'last_purchase_price'   => 1850.00,
                'average_purchase_price'=> 1820.00,
                'mrp'                   => 2200.00,
                'has_expiry'            => true,
            ],
            [
                'product_name'          => 'GC Fuji IX GP Extra GIC',
                'generic_name'          => 'Glass Ionomer Cement',
                'category_id'           => $cat('glass-ionomer'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Kit',
                'consumption_unit'      => 'Kit',
                'pieces_per_unit'       => 1,
                'minimum_qty'           => 2,
                'minimum_order_qty'     => 4,
                'last_purchase_price'   => 2400.00,
                'average_purchase_price'=> 2350.00,
                'mrp'                   => 2800.00,
                'has_expiry'            => true,
            ],
            [
                'product_name'          => 'Dentsply Prime & Bond NT',
                'generic_name'          => 'Bonding Agent',
                'category_id'           => $cat('bonding-agents'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Bottle',
                'consumption_unit'      => 'ml',
                'pieces_per_unit'       => 1,
                'minimum_qty'           => 2,
                'minimum_order_qty'     => 4,
                'last_purchase_price'   => 1200.00,
                'average_purchase_price'=> 1150.00,
                'mrp'                   => 1500.00,
                'has_expiry'            => true,
            ],

            /* ── ENDODONTICS ── */
            [
                'product_name'          => 'ProTaper Gold Rotary Files (F1-F3)',
                'generic_name'          => 'NiTi Rotary File Set',
                'category_id'           => $cat('rotary-files'),
                'inventory_behavior'    => 'semi_reusable',
                'purchase_unit'         => 'Pack',
                'consumption_unit'      => 'Set',
                'pieces_per_unit'       => 6,
                'minimum_qty'           => 3,
                'minimum_order_qty'     => 6,
                'last_purchase_price'   => 2800.00,
                'average_purchase_price'=> 2750.00,
                'mrp'                   => 3500.00,
                'has_expiry'            => false,
                'max_usage_count'       => 3,
            ],
            [
                'product_name'          => 'Sodium Hypochlorite 3% (NaOCl)',
                'generic_name'          => 'Root Canal Irrigant',
                'category_id'           => $cat('irrigants'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Bottle',
                'consumption_unit'      => 'ml',
                'pieces_per_unit'       => 500,
                'minimum_qty'           => 5,
                'minimum_order_qty'     => 10,
                'last_purchase_price'   => 180.00,
                'average_purchase_price'=> 170.00,
                'mrp'                   => null,
                'has_expiry'            => true,
            ],

            /* ── MEDICINES ── */
            [
                'product_name'          => 'Lignospan Standard 2% Lignocaine',
                'generic_name'          => 'Lignocaine HCl 2% + Adrenaline 1:80000',
                'category_id'           => $cat('local-anaesthetics'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Box',
                'consumption_unit'      => 'Cartridge',
                'pieces_per_unit'       => 50,
                'minimum_qty'           => 2,
                'minimum_order_qty'     => 4,
                'last_purchase_price'   => 1400.00,
                'average_purchase_price'=> 1380.00,
                'mrp'                   => null,
                'has_expiry'            => true,
            ],
            [
                'product_name'          => 'Amoxicillin 500mg Capsules',
                'generic_name'          => 'Amoxicillin',
                'category_id'           => $cat('antibiotics'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Strip',
                'consumption_unit'      => 'Tablet',
                'pieces_per_unit'       => 10,
                'minimum_qty'           => 10,
                'minimum_order_qty'     => 20,
                'last_purchase_price'   => 28.50,
                'average_purchase_price'=> 27.00,
                'mrp'                   => 35.00,
                'has_expiry'            => true,
            ],
            [
                'product_name'          => 'Ibuprofen 400mg Tablets',
                'generic_name'          => 'Ibuprofen',
                'category_id'           => $cat('analgesics'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Strip',
                'consumption_unit'      => 'Tablet',
                'pieces_per_unit'       => 10,
                'minimum_qty'           => 10,
                'minimum_order_qty'     => 20,
                'last_purchase_price'   => 18.00,
                'average_purchase_price'=> 17.50,
                'mrp'                   => 25.00,
                'has_expiry'            => true,
            ],

            /* ── IMPLANTS ── */
            [
                'product_name'          => 'Nobel Active 4.3 × 10mm Implant',
                'generic_name'          => 'Tapered Internal Hex Implant',
                'category_id'           => $cat('implant-fixtures'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Piece',
                'consumption_unit'      => 'Piece',
                'pieces_per_unit'       => 1,
                'minimum_qty'           => 3,
                'minimum_order_qty'     => 5,
                'last_purchase_price'   => 18500.00,
                'average_purchase_price'=> 18200.00,
                'mrp'                   => 25000.00,
                'has_expiry'            => false,
            ],
            [
                'product_name'          => 'Bio-Oss Bovine Bone Graft 0.5g',
                'generic_name'          => 'Xenograft Bone Substitute',
                'category_id'           => $cat('bone-grafts'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Vial',
                'consumption_unit'      => 'Vial',
                'pieces_per_unit'       => 1,
                'minimum_qty'           => 2,
                'minimum_order_qty'     => 4,
                'last_purchase_price'   => 4800.00,
                'average_purchase_price'=> 4750.00,
                'mrp'                   => 7000.00,
                'has_expiry'            => true,
            ],

            /* ── STERILISATION ── */
            [
                'product_name'          => 'Self-Sealing Sterilization Pouches 90×230mm',
                'generic_name'          => 'Autoclave Pouch',
                'category_id'           => $cat('sterilization-pouches'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Box',
                'consumption_unit'      => 'Piece',
                'pieces_per_unit'       => 200,
                'minimum_qty'           => 2,
                'minimum_order_qty'     => 4,
                'last_purchase_price'   => 650.00,
                'average_purchase_price'=> 640.00,
                'mrp'                   => null,
                'has_expiry'            => false,
            ],
            [
                'product_name'          => 'Cidex OPA Solution (High-Level Disinfectant)',
                'generic_name'          => 'Ortho-phthalaldehyde 0.55%',
                'category_id'           => $cat('disinfectants'),
                'inventory_behavior'    => 'consumable',
                'purchase_unit'         => 'Bottle',
                'consumption_unit'      => 'ml',
                'pieces_per_unit'       => 1000,
                'minimum_qty'           => 2,
                'minimum_order_qty'     => 4,
                'last_purchase_price'   => 2200.00,
                'average_purchase_price'=> 2150.00,
                'mrp'                   => null,
                'has_expiry'            => true,
            ],
        ];

        $count = 0;
        foreach ($items as $i => $item) {
            $itemCode = 'ITEM-' . str_pad($i + 1, 4, '0', STR_PAD_LEFT);
            InventoryItem::firstOrCreate(
                ['product_name' => $item['product_name']],
                array_merge($item, [
                    'item_code'   => $itemCode,
                    'is_active'   => true,
                ])
            );
            $count++;
        }

        $this->command->info("  ✓ Inventory items seeded ({$count})");
    }

    /* ══════════════════════════════════════════════════════════
       STOCK MOVEMENTS  (triggers observer → updates inventory_stocks)
    ══════════════════════════════════════════════════════════ */

    private function seedStockMovements(): void
    {
        $mainStore = InventoryLocation::where('code', 'MAIN-STORE')->first();
        $op1       = InventoryLocation::where('code', 'OP-1')->first();

        if (! $mainStore) {
            $this->command->warn('  ✗ Main Store location missing — skipping stock movements.');
            return;
        }

        $items = InventoryItem::all()->keyBy('product_name');

        // Helper: only create movement if item exists
        $move = function (
            string $itemName,
            string $type,
            float  $qty,
            ?int   $toLocId,
            ?int   $fromLocId,
            float  $unitCost,
            array  $extra = []
        ) use ($items) {
            $item = $items->get($itemName);
            if (! $item) return;

            // Avoid duplicate opening stock
            if ($type === 'opening_stock') {
                $exists = StockMovement::where('inventory_item_id', $item->id)
                    ->where('movement_type', 'opening_stock')
                    ->exists();
                if ($exists) return;
            }

            StockMovement::create(array_merge([
                'inventory_item_id' => $item->id,
                'movement_type'     => $type,
                'qty'               => $qty,
                'unit_cost'         => $unitCost,
                'total_cost'        => abs($qty) * $unitCost,
                'to_location_id'    => $toLocId,
                'from_location_id'  => $fromLocId,
                'notes'             => 'Demo seeder',
            ], $extra));
        };

        // ── Opening Stock ──────────────────────────────────────
        $move('Nitrile Examination Gloves (M)',        'opening_stock', 25, $mainStore->id, null, 310.00);
        $move('3-Ply Surgical Face Masks',             'opening_stock', 12, $mainStore->id, null, 175.00);
        $move('Cotton Rolls (Non-Sterile)',             'opening_stock', 18, $mainStore->id, null,  90.00);
        $move('Tetric N-Ceram Composite A2',           'opening_stock',  8, $mainStore->id, null, 1820.00, [
            'batch_no'    => 'TET-2024-0812',
            'expiry_date' => now()->addMonths(14)->toDateString(),
        ]);
        $move('GC Fuji IX GP Extra GIC',               'opening_stock',  6, $mainStore->id, null, 2350.00, [
            'batch_no'    => 'GC-0991',
            'expiry_date' => now()->addMonths(8)->toDateString(),   // moderate urgency
        ]);
        $move('Dentsply Prime & Bond NT',              'opening_stock',  4, $mainStore->id, null, 1150.00, [
            'batch_no'    => 'PB-2312',
            'expiry_date' => now()->addMonths(18)->toDateString(),
        ]);
        $move('ProTaper Gold Rotary Files (F1-F3)',     'opening_stock', 10, $mainStore->id, null, 2750.00);
        $move('Sodium Hypochlorite 3% (NaOCl)',        'opening_stock', 20, $mainStore->id, null,  170.00, [
            'batch_no'    => 'NAOCL-0524',
            'expiry_date' => now()->addDays(18)->toDateString(),    // amber — within 30 days
        ]);
        $move('Lignospan Standard 2% Lignocaine',      'opening_stock',  8, $mainStore->id, null, 1380.00, [
            'batch_no'    => 'LIG-2023-99',
            'expiry_date' => now()->addDays(5)->toDateString(),     // RED — expires in 5 days!
        ]);
        $move('Amoxicillin 500mg Capsules',             'opening_stock',  6, $mainStore->id, null,  27.00, [
            'batch_no'    => 'AMOX-445',
            'expiry_date' => now()->addMonths(6)->toDateString(),
        ]);
        $move('Ibuprofen 400mg Tablets',               'opening_stock', 15, $mainStore->id, null,  17.50, [
            'batch_no'    => 'IBU-888',
            'expiry_date' => now()->subDays(10)->toDateString(),    // Already EXPIRED
        ]);
        $move('Nobel Active 4.3 × 10mm Implant',       'opening_stock',  4, $mainStore->id, null, 18200.00);
        $move('Bio-Oss Bovine Bone Graft 0.5g',        'opening_stock',  3, $mainStore->id, null,  4750.00, [
            'batch_no'    => 'BIOSS-0623',
            'expiry_date' => now()->addYears(2)->toDateString(),
        ]);
        $move('Self-Sealing Sterilization Pouches 90×230mm', 'opening_stock', 8, $mainStore->id, null, 640.00);
        $move('Cidex OPA Solution (High-Level Disinfectant)', 'opening_stock', 5, $mainStore->id, null, 2150.00, [
            'batch_no'    => 'CIDEX-0924',
            'expiry_date' => now()->addMonths(4)->toDateString(),
        ]);

        // ── Recent Stock-In (simulating purchases received last 30 days) ──
        $move('Nitrile Examination Gloves (M)',  'stock_in', 10, $mainStore->id, null, 320.00, [
            'batch_no'    => 'GLV-2601',
            'notes'       => 'PO-2026-0001 received',
            'created_at'  => now()->subDays(12),
            'updated_at'  => now()->subDays(12),
        ]);
        $move('Tetric N-Ceram Composite A2', 'stock_in', 4, $mainStore->id, null, 1850.00, [
            'batch_no'    => 'TET-2026-0301',
            'expiry_date' => now()->addMonths(24)->toDateString(),
            'notes'       => 'Dentsply order received',
            'created_at'  => now()->subDays(8),
            'updated_at'  => now()->subDays(8),
        ]);

        // ── Dispensed (stock_out) last 30 days ────────────────
        // These are negative qty values
        $dispensed = [
            ['Nitrile Examination Gloves (M)',      -8,  310.00, 'Treatment — Morning batch'],
            ['3-Ply Surgical Face Masks',           -6,  175.00, 'Daily PPE'],
            ['Cotton Rolls (Non-Sterile)',           -5,   90.00, 'OP-1 stock replenishment'],
            ['Tetric N-Ceram Composite A2',         -3, 1820.00, 'Composite restorations'],
            ['ProTaper Gold Rotary Files (F1-F3)',  -4, 2750.00, 'RCT procedures'],
            ['Sodium Hypochlorite 3% (NaOCl)',      -6,  170.00, 'Root canal irrigation'],
            ['Lignospan Standard 2% Lignocaine',    -3, 1380.00, 'Extractions — Week 21'],
            ['Amoxicillin 500mg Capsules',          -2,   27.00, 'Post-op prescription'],
            ['Self-Sealing Sterilization Pouches 90×230mm', -3, 640.00, 'Daily sterilization run'],
        ];

        foreach ($dispensed as [$itemName, $qty, $cost, $notes]) {
            $item = $items->get($itemName);
            if (! $item) continue;

            // Avoid duplicates
            $existsOut = StockMovement::where('inventory_item_id', $item->id)
                ->where('movement_type', 'stock_out')
                ->where('notes', $notes)
                ->exists();
            if ($existsOut) continue;

            StockMovement::create([
                'inventory_item_id' => $item->id,
                'movement_type'     => 'stock_out',
                'qty'               => $qty,
                'unit_cost'         => $cost,
                'total_cost'        => abs($qty) * $cost,
                'from_location_id'  => $mainStore->id,
                'notes'             => $notes,
                'created_at'        => now()->subDays(rand(1, 28)),
                'updated_at'        => now()->subDays(rand(1, 3)),
            ]);
        }

        // ── Treatment Usage (for Reports "top consumed") ──────
        $usages = [
            ['Tetric N-Ceram Composite A2',       -2, 1820.00, 'Patient #1042 — composite filling'],
            ['GC Fuji IX GP Extra GIC',           -1, 2350.00, 'Patient #1033 — ART filling'],
            ['Dentsply Prime & Bond NT',          -1, 1150.00, 'Bonding for veneer — Patient #1055'],
            ['Lignospan Standard 2% Lignocaine',  -5, 1380.00, 'Anaesthesia — surgical day'],
            ['ProTaper Gold Rotary Files (F1-F3)',-2, 2750.00, 'RCT #1041 #1039'],
            ['Nobel Active 4.3 × 10mm Implant',  -1, 18200.00,'Implant placed — Patient #998'],
        ];

        foreach ($usages as [$itemName, $qty, $cost, $notes]) {
            $item = $items->get($itemName);
            if (! $item) continue;

            $existsUsage = StockMovement::where('inventory_item_id', $item->id)
                ->where('movement_type', 'treatment_usage')
                ->where('notes', $notes)
                ->exists();
            if ($existsUsage) continue;

            StockMovement::create([
                'inventory_item_id' => $item->id,
                'movement_type'     => 'treatment_usage',
                'qty'               => $qty,
                'unit_cost'         => $cost,
                'total_cost'        => abs($qty) * $cost,
                'from_location_id'  => $mainStore->id,
                'notes'             => $notes,
                'created_at'        => now()->subDays(rand(1, 20)),
                'updated_at'        => now()->subDays(rand(1, 5)),
            ]);
        }

        $this->command->info('  ✓ Stock movements seeded (opening stock + recent in/out/usage)');
    }

    /* ══════════════════════════════════════════════════════════
       PURCHASE ORDERS
    ══════════════════════════════════════════════════════════ */

    private function seedPurchaseOrders(): void
    {
        // Skip if already seeded
        if (PurchaseOrder::count() > 0) {
            $this->command->info('  ✓ Purchase orders already exist — skipping.');
            return;
        }

        $vendors  = InventoryVendor::all()->keyBy('vendor_name');
        $items    = InventoryItem::all()->keyBy('product_name');
        $firstUser = DB::table('users')->value('id'); // null-safe — FK is nullable

        // ── PO 1: Completed order (received 3 weeks ago) ──────
        $po1 = PurchaseOrder::create([
            'order_no'      => 'PO-2026-0001',
            'vendor_id'     => $vendors->get('Dentsply Sirona India')?->id,
            'order_date'    => now()->subDays(28),
            'expected_date' => now()->subDays(21),
            'status'        => 'completed',
            'total_amount'  => 0, // recalculated below
            'gst_amount'    => 0,
            'notes'         => 'Monthly consumables order — April 2026',
            'created_by'    => $firstUser,
        ]);

        $po1Lines = [
            ['Nitrile Examination Gloves (M)',    10, 310.00, 18],
            ['3-Ply Surgical Face Masks',         10, 175.00, 18],
            ['Dentsply Prime & Bond NT',           4, 1150.00, 12],
            ['Cotton Rolls (Non-Sterile)',         10,  90.00, 18],
        ];
        $this->attachLines($po1, $po1Lines, $items);

        // ── PO 2: Ordered (awaiting delivery) ────────────────
        $po2 = PurchaseOrder::create([
            'order_no'      => 'PO-2026-0002',
            'vendor_id'     => $vendors->get('Nobel Biocare India')?->id,
            'order_date'    => now()->subDays(5),
            'expected_date' => now()->addDays(7),
            'status'        => 'ordered',
            'total_amount'  => 0,
            'gst_amount'    => 0,
            'notes'         => 'Implant system restock',
            'created_by'    => $firstUser,
        ]);

        $po2Lines = [
            ['Nobel Active 4.3 × 10mm Implant',  5, 18200.00, 12],
            ['Bio-Oss Bovine Bone Graft 0.5g',   3,  4750.00, 12],
        ];
        $this->attachLines($po2, $po2Lines, $items);

        // ── PO 3: Partially received ──────────────────────────
        $po3 = PurchaseOrder::create([
            'order_no'      => 'PO-2026-0003',
            'vendor_id'     => $vendors->get('Septodont India Pvt Ltd')?->id,
            'order_date'    => now()->subDays(10),
            'expected_date' => now()->subDays(3),
            'status'        => 'partially_received',
            'total_amount'  => 0,
            'gst_amount'    => 0,
            'notes'         => 'Anaesthetics & medicines — urgent',
            'created_by'    => $firstUser,
        ]);

        $po3Lines = [
            ['Lignospan Standard 2% Lignocaine', 4, 1380.00, 12],
            ['Amoxicillin 500mg Capsules',       20,   27.00, 12],
            ['Ibuprofen 400mg Tablets',          20,   17.50, 12],
        ];
        $this->attachLines($po3, $po3Lines, $items, partialReceive: true);

        // ── PO 4: Draft order (not yet sent) ─────────────────
        $po4 = PurchaseOrder::create([
            'order_no'      => 'PO-2026-0004',
            'vendor_id'     => $vendors->get('GC India Dental Products')?->id,
            'order_date'    => now(),
            'expected_date' => now()->addDays(14),
            'status'        => 'draft',
            'total_amount'  => 0,
            'gst_amount'    => 0,
            'notes'         => 'Draft — pending approval from Dr. Patel',
            'created_by'    => $firstUser,
        ]);

        $po4Lines = [
            ['GC Fuji IX GP Extra GIC', 6, 2350.00, 12],
            ['Tetric N-Ceram Composite A2', 5, 1820.00, 12],
        ];
        $this->attachLines($po4, $po4Lines, $items);

        $this->command->info('  ✓ Purchase orders seeded (4 orders with line items)');
    }

    /**
     * Attach line items to a PO and update the PO totals.
     * $lines = [ [itemName, qty, price, gstRate], ... ]
     */
    private function attachLines(
        PurchaseOrder $po,
        array $lines,
        $items,
        bool $partialReceive = false
    ): void {
        $subtotal = 0;
        $gstAmt   = 0;

        foreach ($lines as [$itemName, $qty, $price, $gst]) {
            $item = $items->get($itemName);
            if (! $item) continue;

            $lineSubtotal = $qty * $price;
            $lineGst      = $lineSubtotal * ($gst / 100);
            $subtotal    += $lineSubtotal;
            $gstAmt      += $lineGst;

            PurchaseOrderItem::create([
                'purchase_order_id'  => $po->id,
                'inventory_item_id'  => $item->id,
                'qty_ordered'        => $qty,
                'qty_received'       => $partialReceive ? floor($qty * 0.5) : ($po->status === 'completed' ? $qty : 0),
                'unit_price'         => $price,
                'gst_rate'           => $gst,
                'total_price'        => ($lineSubtotal + $lineGst),
            ]);
        }

        $po->update([
            'total_amount' => $subtotal + $gstAmt,
            'gst_amount'   => $gstAmt,
        ]);
    }
}
