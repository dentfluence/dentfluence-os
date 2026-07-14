<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\InventoryVendor;
use App\Models\Inventory\InventorySubType;
use App\Models\Inventory\InventoryVariant;
use App\Models\Inventory\ImplantCatalog;
use App\Models\Inventory\ImplantPlacement;
use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceExpenseCategory;
use App\Models\Procurement\GoodsReceiptNote;
use App\Models\AppSetting;
use App\Models\Task;
use App\Models\Patient;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * InventoryService — the "one brain" for the Inventory module.
 * -----------------------------------------------------------------
 * This service holds the Core-6 inventory logic (items/stock, stock-in,
 * stock-out, purchase orders + GRN, vendors, implants) so the web pages
 * and the /api/v1 mobile endpoints behave identically.
 *
 * IMPORTANT — branch scope:
 *   The inventory tables (items, stocks, vendors, purchase_orders,
 *   stock_movements, implant_*) do NOT have a branch_id column. The web
 *   module treats inventory as clinic-wide, so this service deliberately
 *   does NOT filter by branch_id — that keeps exact parity with the web.
 *   (Tasks created for vendor follow-up DO carry the user's branch_id,
 *   mirroring the web controller.)
 *
 * Methods that build lists return un-executed query builders so the
 * controller decides how to paginate. Write methods return the saved
 * model. Domain failures (e.g. insufficient stock) throw a
 * \RuntimeException whose message is shown to the user.
 */
class InventoryService
{
    /* ═══════════════════════════════════════════════════════════
       META — dropdown / form-option data for every inventory screen
    ═══════════════════════════════════════════════════════════ */

    public function meta(): array
    {
        $categories = InventoryCategory::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $locations = InventoryLocation::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);

        $vendors = InventoryVendor::active()
            ->orderBy('vendor_name')
            ->get(['id', 'vendor_name', 'contact_person', 'phone', 'whatsapp', 'email']);

        // Sub-types/variants (2026-07-07 mobile parity) — full lists like the
        // web Add Product modal loads, so mobile can do the same client-side
        // category -> sub-type -> variant filtering instead of a round-trip
        // per keystroke. Both tables are small (dozens of rows, not thousands).
        $subTypes = InventorySubType::orderBy('name')->get(['id', 'name', 'category_id']);
        $variants = InventoryVariant::orderBy('name')->get(['id', 'name', 'sub_type_id']);

        return [
            'categories' => $categories,
            'locations'  => $locations,
            'vendors'    => $vendors,
            'sub_types'  => $subTypes,
            'variants'   => $variants,
            // Static enums used by the mobile forms (kept in sync with web validation rules)
            'stock_out_movement_types' => [
                'stock_out', 'treatment_usage', 'damaged', 'expired', 'adjustment',
            ],
            'inventory_behaviors' => ['consumable', 'reusable', 'semi_reusable'],
            'implant_component_types' => [
                'fixture', 'abutment', 'healing_abutment', 'analogue',
                'scan_body', 'coping', 'graft', 'other',
            ],
            'implant_placement_statuses' => [
                'placed', 'osseointegrating', 'loaded', 'failed', 'explanted',
            ],
            'grn_correction_window_hours' => (int) AppSetting::get('grn_correction_window_hours', 0),
        ];
    }

    /* ═══════════════════════════════════════════════════════════
       ITEMS / STOCK — list (by location), product master, detail,
       update, quick adjust
    ═══════════════════════════════════════════════════════════ */

    /**
     * Stock-by-location list (mirrors web InventoryController@items).
     * One row per product/location; products with no stock still appear (qty 0).
     * Returns a DB query builder so the controller can paginate.
     */
    public function itemsStockQuery(array $filters)
    {
        $sort = $filters['sort'] ?? 'product_name';
        $dir  = (($filters['dir'] ?? 'asc') === 'desc') ? 'desc' : 'asc';
        if (! in_array($sort, ['product_name', 'location_name', 'available_qty'], true)) {
            $sort = 'product_name';
        }

        $query = DB::table('inventory_items as i')
            ->leftJoin('inventory_stocks as s', 's.inventory_item_id', '=', 'i.id')
            ->leftJoin('inventory_locations as l', 'l.id', '=', 's.location_id')
            ->leftJoin('inventory_categories as c', 'c.id', '=', 'i.category_id')
            ->leftJoin('inventory_sub_types as st', 'st.id', '=', 'i.sub_type_id')
            ->where('i.is_active', true)
            ->select([
                'i.id as item_id',
                'i.product_name',
                'i.generic_name',
                'i.consumption_unit',
                'i.minimum_qty',
                'i.reorder_level',
                's.id as stock_id',
                's.available_qty',
                's.location_id',
                'l.name as location_name',
                'c.name as category_name',
                'c.id as category_id',
                'st.name as sub_type_name',
            ]);

        if (! empty($filters['category_id'])) {
            $query->where('i.category_id', $filters['category_id']);
        }
        if (! empty($filters['location_id'])) {
            $query->where('s.location_id', $filters['location_id']);
        }
        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('i.product_name', 'like', "%{$term}%")
                  ->orWhere('i.generic_name', 'like', "%{$term}%");
            });
        }

        // Stock-status filter — same buckets as the mobile StockRow.stockStatus
        // getter and the web dashboard thresholds: out (<=0), critical
        // (<= minimum_qty/2), low (<= minimum_qty), healthy (everything else).
        // available_qty comes from a LEFT JOIN so a missing stock row reads as
        // NULL, which we treat as 0 via COALESCE. Accepts a comma-separated
        // list (e.g. "low,critical") so the mobile "needs attention" default
        // view can combine buckets in one request.
        if (! empty($filters['stock_status'])) {
            $qtyExpr  = 'COALESCE(s.available_qty, 0)';
            $statuses = array_filter(array_map('trim', explode(',', $filters['stock_status'])));

            $query->where(function ($outer) use ($statuses, $qtyExpr) {
                foreach ($statuses as $status) {
                    $outer->orWhere(function ($q) use ($status, $qtyExpr) {
                        match ($status) {
                            'out'      => $q->whereRaw("{$qtyExpr} <= 0"),
                            'critical' => $q->whereRaw("{$qtyExpr} > 0")
                                             ->where('i.minimum_qty', '>', 0)
                                             ->whereRaw("{$qtyExpr} <= i.minimum_qty / 2"),
                            'low'      => $q->whereRaw("{$qtyExpr} > 0")
                                             ->where('i.minimum_qty', '>', 0)
                                             ->whereRaw("{$qtyExpr} > i.minimum_qty / 2")
                                             ->whereRaw("{$qtyExpr} <= i.minimum_qty"),
                            'healthy'  => $q->whereRaw("{$qtyExpr} > 0")
                                             ->where(function ($hq) use ($qtyExpr) {
                                                 $hq->whereRaw("{$qtyExpr} > i.minimum_qty")
                                                    ->orWhere('i.minimum_qty', '<=', 0);
                                             }),
                            default    => $q->whereRaw('1 = 0'), // unknown bucket matches nothing
                        };
                    });
                }
            });
        }

        if ($sort === 'location_name') {
            $query->orderBy('l.name', $dir)->orderBy('i.product_name', 'asc');
        } elseif ($sort === 'available_qty') {
            $query->orderBy('s.available_qty', $dir)->orderBy('i.product_name', 'asc');
        } else {
            $query->orderBy('i.product_name', $dir)->orderBy('l.name', 'asc');
        }

        return $query;
    }

    /**
     * Product master list (mirrors web InventoryController@products).
     * Eloquent query with stock totals + filters. Returns the builder.
     */
    public function productsQuery(array $filters)
    {
        $query = InventoryItem::with(['category', 'subType', 'variant', 'stocks.location'])
            ->withSum('stocks as total_qty', 'available_qty')
            ->where('is_active', true);

        if (! empty($filters['search'])) {
            $term = $filters['search'];
            $query->where(function ($q) use ($term) {
                $q->where('product_name', 'like', "%{$term}%")
                  ->orWhere('generic_name', 'like', "%{$term}%")
                  ->orWhere('brand', 'like', "%{$term}%")
                  ->orWhere('company_name', 'like', "%{$term}%")
                  ->orWhere('item_code', 'like', "%{$term}%");
            });
        }
        if (! empty($filters['category_id'])) $query->where('category_id', $filters['category_id']);
        if (! empty($filters['sub_type_id'])) $query->where('sub_type_id', $filters['sub_type_id']);
        if (! empty($filters['brand']))       $query->where('brand', $filters['brand']);
        // Retail/FMCG product picker (mobile billing, 2026-07-06 web parity) —
        // only items marked sellable via the Inventory > Saleable/FMCG tab.
        if (! empty($filters['sellable_only'])) $query->where('is_sellable', true);
        if (! empty($filters['location_id'])) {
            $query->whereHas('stocks', fn ($q) => $q->where('location_id', $filters['location_id']));
        }

        $level = $filters['stock_level'] ?? null;
        if ($level) {
            $query->when($level === 'out',      fn ($q) => $q->having('total_qty', '<=', 0))
                  ->when($level === 'critical', fn ($q) => $q->havingRaw('total_qty > 0 AND total_qty <= (minimum_qty / 2)'))
                  ->when($level === 'low',      fn ($q) => $q->havingRaw('total_qty > (minimum_qty / 2) AND total_qty <= minimum_qty'))
                  ->when($level === 'healthy',  fn ($q) => $q->havingRaw('total_qty > minimum_qty OR minimum_qty IS NULL OR minimum_qty = 0'));
        }

        return $query->orderBy('product_name');
    }

    /** Single item with stocks + category for the detail screen. */
    public function findItem($id): ?InventoryItem
    {
        return InventoryItem::with(['category', 'subType', 'variant', 'stocks.location'])
            ->withSum('stocks as total_qty', 'available_qty')
            ->find($id);
    }

    /** Update an item's core fields (mirrors web InventoryController@updateItem). */
    public function updateItem(InventoryItem $item, array $data): InventoryItem
    {
        $item->update([
            'product_name'        => $data['product_name'],
            'generic_name'        => $data['generic_name'] ?? null,
            'brand'               => $data['brand'] ?? null,
            'category_id'         => $data['category_id'] ?? null,
            'inventory_behavior'  => $data['inventory_behavior'],
            'purchase_unit'       => $data['purchase_unit'],
            'consumption_unit'    => $data['consumption_unit'],
            'pieces_per_unit'     => $data['pieces_per_unit'],
            'minimum_qty'         => $data['minimum_qty'],
            'minimum_order_qty'   => $data['minimum_order_qty'],
            'last_purchase_price' => $data['last_purchase_price'] ?? $item->last_purchase_price,
            'gst_rate'            => $data['gst_rate'] ?? $item->gst_rate,
            'has_expiry'          => (bool) ($data['has_expiry'] ?? false),
            'is_reusable'         => (bool) ($data['is_reusable'] ?? false),
        ]);

        return $item->fresh();
    }

    /**
     * Cost-per-usage for an item — the field that drives reusable /
     * use-capped costing. Single implementation (extracted 2026-07-14 from
     * web InventoryController::calculateCostPerUsage; the API create path
     * previously never set it, so mobile-created products costed at 0).
     *
     * Column is NOT NULL (default 0), so uncomputable cases resolve to 0 —
     * the views already treat 0 as "hide this row".
     */
    public static function costPerUsage(array $data): float
    {
        $qty = (float) ($data['qty_in_packaging'] ?? 0);
        if ($qty <= 0 || empty($data['average_purchase_price'])) {
            return 0.0;
        }
        $costPerPiece = $data['average_purchase_price'] / $qty;
        $uses = (($data['usage_type'] ?? null) === 'multiple_use' && ! empty($data['max_usage_count']))
            ? (int) $data['max_usage_count']
            : 1;

        return round($costPerPiece / max(1, $uses), 4);
    }

    /**
     * Quick +/- stock adjust — THE single implementation (web controller now
     * delegates here too, 2026-07-14). Relies on StockMovement's booted()
     * hook to update inventory_stocks.
     *
     * Price only ever moves with stock coming IN: a fresh unit_price on an
     * "add" becomes the item's new purchase price (same simple-overwrite
     * convention GRN receiving uses); blank keeps the last known price. This
     * path previously dropped unit_price entirely on the API, so mobile
     * stock-adds never updated item cost and logged zero-cost movements.
     */
    public function adjustStock(InventoryItem $item, array $data, User $user): StockMovement
    {
        $qty = (float) $data['qty'];

        if ($data['type'] === 'remove') {
            $stock = InventoryStock::where('inventory_item_id', $item->id)
                ->where('location_id', $data['location_id'])
                ->first();
            if (! $stock || $stock->available_qty < $qty) {
                throw new \RuntimeException(
                    'Cannot remove more than available stock (' . ($stock->available_qty ?? 0) . ').'
                );
            }
        }

        $unitCost = (float) $item->average_purchase_price;
        if ($data['type'] === 'add' && ! empty($data['unit_price'])) {
            $unitCost = (float) $data['unit_price'];
            $item->last_purchase_price    = $unitCost;
            $item->average_purchase_price = $unitCost;
            $item->save();
        }

        return StockMovement::create([
            'inventory_item_id' => $item->id,
            'to_location_id'    => $data['type'] === 'add'    ? $data['location_id'] : null,
            'from_location_id'  => $data['type'] === 'remove' ? $data['location_id'] : null,
            'movement_type'     => $data['type'] === 'add' ? 'stock_in' : 'stock_out',
            'qty'               => $qty,
            'unit_cost'         => $unitCost,
            'total_cost'        => round($qty * $unitCost, 2),
            'notes'             => $data['note'] ?? 'Manual adjustment',
            'reference_type'    => 'manual_adjustment',
            'reference_id'      => null,
            'created_by'        => $user->id,
        ]);
    }

    /* ═══════════════════════════════════════════════════════════
       STOCK IN / STOCK OUT
    ═══════════════════════════════════════════════════════════ */

    /** Record a stock receipt (mirrors web InventoryController@storeStockIn). */
    public function createStockIn(array $data, User $user): StockMovement
    {
        $qty      = (float) $data['qty'];
        $unitCost = (float) ($data['unit_cost'] ?? 0);

        $movement = StockMovement::create([
            'inventory_item_id'  => $data['inventory_item_id'],
            'movement_type'      => 'stock_in',
            'qty'                => $qty,
            'to_location_id'     => $data['to_location_id'],
            'unit_cost'          => $unitCost,
            'total_cost'         => round($qty * $unitCost, 2),
            'batch_no'           => $data['batch_no'] ?? null,
            'expiry_date'        => $data['expiry_date'] ?? null,
            'manufacturing_date' => $data['manufacturing_date'] ?? null,
            'notes'              => $data['notes'] ?? null,
            'created_by'         => $user->id,
        ]);

        // Update item's last + average purchase price (simple update; weighted avg later)
        if ($unitCost > 0) {
            $item = InventoryItem::find($data['inventory_item_id']);
            if ($item) {
                $item->last_purchase_price    = $unitCost;
                $item->average_purchase_price = $unitCost;
                $item->save();
            }
        }

        return $movement;
    }

    /** Record a stock-out (mirrors web InventoryController@storeStockOut). */
    public function createStockOut(array $data, User $user): StockMovement
    {
        $item = InventoryItem::find($data['inventory_item_id']);

        $stock = InventoryStock::where('inventory_item_id', $data['inventory_item_id'])
            ->where('location_id', $data['from_location_id'])
            ->first();
        $available = $stock ? $stock->available_qty : 0;

        if ((float) $data['qty'] > $available) {
            throw new \RuntimeException(
                'Insufficient stock. Available: ' . $available . ' ' . ($item?->consumption_unit ?? '')
            );
        }

        return StockMovement::create([
            'inventory_item_id' => $data['inventory_item_id'],
            'movement_type'     => $data['movement_type'],
            'qty'               => -1 * abs((float) $data['qty']), // negative = leaving system
            'from_location_id'  => $data['from_location_id'],
            'unit_cost'         => $item?->average_purchase_price ?? 0,
            'total_cost'        => round(abs((float) $data['qty']) * ($item?->average_purchase_price ?? 0), 2),
            'notes'             => $data['notes'] ?? null,
            'created_by'        => $user->id,
        ]);
    }

    /* ═══════════════════════════════════════════════════════════
       VENDORS
    ═══════════════════════════════════════════════════════════ */

    /** Vendor list query (mirrors web InventoryController@vendors). */
    public function vendorsQuery()
    {
        return InventoryVendor::orderBy('vendor_name');
    }

    /* ═══════════════════════════════════════════════════════════
       PURCHASE ORDERS + GRN
    ═══════════════════════════════════════════════════════════ */

    /** PO list query with status filter (mirrors web InventoryController@purchase). */
    public function purchaseOrdersQuery(array $filters)
    {
        $query = PurchaseOrder::with(['vendor', 'items.item'])->latest();

        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /** One PO with everything needed for the detail / receive screen. */
    public function findPurchaseOrder($id): ?PurchaseOrder
    {
        return PurchaseOrder::with(['vendor', 'items.item', 'grns'])->find($id);
    }

    /**
     * Create a purchase order (mirrors web InventoryController@storePurchaseOrder),
     * including line totals, Finance vendor resolution, and the vendor
     * follow-up Tasks when the PO is placed as "ordered".
     */
    public function createPurchaseOrder(array $data, User $user): PurchaseOrder
    {
        $subtotal = 0;
        $gstTotal = 0;
        foreach ($data['items'] as $line) {
            $lineTotal = $line['qty'] * $line['price'];
            $subtotal += $lineTotal;
            $gstTotal += $lineTotal * (($line['gst'] ?? 0) / 100);
        }

        $invVendor       = InventoryVendor::find($data['vendor_id']);
        $financeVendorId = $invVendor?->finance_vendor_id
            ?? $invVendor?->syncToFinance()?->id;

        $po = PurchaseOrder::create([
            'order_no'          => PurchaseOrder::generateOrderNo(),
            'vendor_id'         => $data['vendor_id'],
            'finance_vendor_id' => $financeVendorId,
            'order_date'        => $data['order_date'],
            'expected_date'     => $data['expected_date'] ?? null,
            'status'            => $data['status'],
            'total_amount'      => $subtotal + $gstTotal,
            'gst_amount'        => $gstTotal,
            'notes'             => $data['notes'] ?? null,
            'created_by'        => $user->id,
        ]);

        foreach ($data['items'] as $line) {
            $lineTotal = $line['qty'] * $line['price'];
            $po->items()->create([
                'inventory_item_id' => $line['item_id'],
                'qty_ordered'       => $line['qty'],
                'qty_received'      => 0,
                'unit_price'        => $line['price'],
                'gst_rate'          => $line['gst'] ?? 0,
                'total_price'       => $lineTotal * (1 + (($line['gst'] ?? 0) / 100)),
            ]);
        }

        // Vendor communication tasks — only when the PO is actually "ordered".
        if ($data['status'] === 'ordered') {
            $vendorName = $invVendor?->vendor_name ?? 'Vendor';
            $vendorNote = 'PO# ' . $po->order_no . ' — ' . $vendorName;

            Task::create([
                'title'       => 'Confirm PO with ' . $vendorName,
                'description' => 'Call or WhatsApp ' . $vendorName . ' to confirm receipt of purchase order. ' . $vendorNote . '.',
                'assigned_to' => $user->id,
                'created_by'  => $user->id,
                'branch_id'   => $user->branch_id ?? null,
                'due_date'    => $data['order_date'],
                'priority'    => 'medium',
                'category'    => 'call',
                'status'      => 'pending',
                'po_id'       => $po->id,
                'vendor_note' => $vendorNote,
            ]);

            if (! empty($data['expected_date'])) {
                $followUpDate = Carbon::parse($data['expected_date'])->subDay();
                if ($followUpDate->isFuture() || $followUpDate->isToday()) {
                    Task::create([
                        'title'       => 'Delivery follow-up: ' . $vendorName,
                        'description' => 'Call ' . $vendorName . ' to check delivery status. Expected date is '
                                         . Carbon::parse($data['expected_date'])->format('d M Y') . '. ' . $vendorNote . '.',
                        'assigned_to' => $user->id,
                        'created_by'  => $user->id,
                        'branch_id'   => $user->branch_id ?? null,
                        'due_date'    => $followUpDate->toDateString(),
                        'priority'    => 'medium',
                        'category'    => 'call',
                        'status'      => 'pending',
                        'po_id'       => $po->id,
                        'vendor_note' => $vendorNote,
                    ]);
                }
            }
        }

        return $po->load(['vendor', 'items.item']);
    }

    /** Mark a draft PO as ordered (mirrors web InventoryController@markOrdered). */
    public function markOrdered(PurchaseOrder $po): PurchaseOrder
    {
        if ($po->status !== 'draft') {
            throw new \RuntimeException('Only draft orders can be marked as ordered.');
        }

        $po->update(['status' => 'ordered']);

        return $po->fresh(['vendor', 'items.item']);
    }

    /**
     * Receive goods against a PO (mirrors web InventoryController@receivePO).
     * Creates a GRN, stock_in movements, recalculates PO status, and posts an
     * unpaid vendor bill to Finance. Wrapped in a DB transaction for safety.
     */
    public function receivePurchaseOrder(PurchaseOrder $po, array $data, User $user): GoodsReceiptNote
    {
        return DB::transaction(function () use ($po, $data, $user) {
            $locationId   = $data['location_id'];
            $receivedDate = $data['received_date'];
            $anyReceived  = false;

            $grn = GoodsReceiptNote::create([
                'grn_number'        => GoodsReceiptNote::generateGrnNumber(),
                'purchase_order_id' => $po->id,
                'vendor_id'         => $po->vendor_id,
                'received_date'     => $receivedDate,
                'location_id'       => $locationId,
                'status'            => 'confirmed',
                'created_by'        => $user->id,
            ]);

            foreach ($data['lines'] as $line) {
                $qty = (float) $line['qty'];
                if ($qty <= 0) continue;

                $anyReceived = true;
                $poItem   = $po->items()->where('inventory_item_id', $line['item_id'])->first();
                $unitCost = (float) ($line['unit_cost'] ?? $poItem?->unit_price ?? 0);

                $movement = StockMovement::create([
                    'inventory_item_id' => $line['item_id'],
                    'movement_type'     => 'stock_in',
                    'qty'               => $qty,
                    'to_location_id'    => $locationId,
                    'unit_cost'         => $unitCost,
                    'total_cost'        => $qty * $unitCost,
                    'batch_no'          => $line['batch_no'] ?? null,
                    'expiry_date'       => $line['expiry'] ?? null,
                    'reference_type'    => PurchaseOrder::class,
                    'reference_id'      => $po->id,
                    'notes'             => 'GRN# ' . $grn->grn_number . ' — PO# ' . $po->order_no,
                    'created_by'        => $user->id,
                ]);

                $grn->items()->create([
                    'purchase_order_item_id' => $poItem?->id,
                    'inventory_item_id'      => $line['item_id'],
                    'qty_received'           => $qty,
                    'unit_price'             => $unitCost,
                    'total_price'            => $qty * $unitCost,
                    'batch_no'               => $line['batch_no'] ?? null,
                    'expiry_date'            => $line['expiry'] ?? null,
                    'stock_movement_id'      => $movement->id,
                ]);

                if ($poItem) {
                    $poItem->increment('qty_received', $qty);
                    if (! empty($line['unit_cost']) && (float) $line['unit_cost'] > 0) {
                        $invItem = InventoryItem::find($line['item_id']);
                        if ($invItem) {
                            $invItem->last_purchase_price    = (float) $line['unit_cost'];
                            $invItem->average_purchase_price = (float) $line['unit_cost'];
                            $invItem->save();
                        }
                    }
                }
            }

            if (! $anyReceived) {
                $grn->delete();
                throw new \RuntimeException('Enter at least one quantity greater than 0.');
            }

            // Recalculate PO status
            $po->refresh();
            $allItems         = $po->items;
            $allFullyReceived = $allItems->every(fn ($i) => $i->qty_received >= $i->qty_ordered);
            $anyPartial       = $allItems->some(fn ($i) => $i->qty_received > 0);
            $po->update([
                'status' => $allFullyReceived ? 'completed' : ($anyPartial ? 'partially_received' : 'ordered'),
            ]);

            // Auto-create an unpaid vendor bill in Finance for THIS GRN
            $grn->load('items');
            $grnSubtotal = 0;
            $grnGst      = 0;
            foreach ($grn->items as $grnItem) {
                $poItem  = $po->items()->where('inventory_item_id', $grnItem->inventory_item_id)->first();
                $gstRate = $poItem?->gst_rate ?? 0;
                $lineAmt = $grnItem->total_price;
                $grnSubtotal += $lineAmt;
                $grnGst      += $lineAmt * ($gstRate / 100);
            }
            $grnTotal = $grnSubtotal + $grnGst;

            if ($grnTotal > 0) {
                $suppliesCategory = FinanceExpenseCategory::where('name', 'like', '%Suppl%')
                    ->orWhere('name', 'like', '%Dental%')
                    ->orWhere('name', 'like', '%Inventory%')
                    ->first();

                $financeVendorId = $po->finance_vendor_id ?? $po->resolveFinanceVendor()?->id;
                $vendorInvoiceNo = $data['vendor_invoice_no'] ?? null;
                $invoiceLabel    = $vendorInvoiceNo ? ' [Inv# ' . $vendorInvoiceNo . ']' : '';

                FinanceExpense::create([
                    'title'             => 'PO# ' . $po->order_no . ' — ' . ($po->vendor?->vendor_name ?? 'Vendor') . $invoiceLabel,
                    'description'       => 'GRN# ' . $grn->grn_number . ' — items received against PO ' . $po->order_no
                                         . ($vendorInvoiceNo ? '. Vendor Invoice: ' . $vendorInvoiceNo : '') . '.',
                    'expense_date'      => $receivedDate,
                    'amount'            => $grnSubtotal,
                    'gst_applicable'    => $grnGst > 0,
                    'gst_amount'        => $grnGst,
                    'total_amount'      => $grnTotal,
                    'category_id'       => $suppliesCategory?->id,
                    'vendor_id'         => $financeVendorId,
                    'payment_status'    => 'unpaid',
                    'status'            => 'approved',
                    // Canonical form — same as web receivePO (GRN class + GRN id).
                    // Was 'PurchaseOrder'/$po->id, which made the vendor-invoice
                    // double-AP guard fragile across channels (2026-07-14).
                    // VendorInvoiceService::findExistingGrnExpense still matches
                    // historical rows written in the old form.
                    'source_type'       => GoodsReceiptNote::class,
                    'source_id'         => $grn->id,
                    'vendor_invoice_no' => $vendorInvoiceNo,
                    'grn_number'        => $grn->grn_number,
                    'created_by'        => $user->id,
                ]);
            }

            // Auto-cancel the pending pre-delivery follow-up task, if any.
            $pendingFollowUp = Task::where('po_id', $po->id)
                ->where('status', 'pending')
                ->where('title', 'like', 'Delivery follow-up:%')
                ->first();
            if ($pendingFollowUp) {
                $pendingFollowUp->update([
                    'status'      => 'done',
                    'done_at'     => now(),
                    'description' => $pendingFollowUp->description
                        . "\n\n[Auto-cancelled] Material received via GRN# "
                        . $grn->grn_number . ' on ' . $receivedDate . ' — before expected date.',
                ]);
            }

            return $grn->fresh(['items']);
        });
    }

    /**
     * Build a ready-to-send WhatsApp message + target number for a PO, so the
     * mobile "Send PO via WhatsApp" button can open wa.me directly.
     * Returns null number if the vendor has no WhatsApp/phone on file.
     */
    public function purchaseOrderWhatsappMessage(PurchaseOrder $po): array
    {
        $po->loadMissing(['vendor', 'items.item']);
        $vendor = $po->vendor;

        $lines = [];
        $lines[] = 'Hello ' . ($vendor?->contact_person ?: $vendor?->vendor_name ?: 'team') . ',';
        $lines[] = '';
        $lines[] = 'Please find our Purchase Order ' . $po->order_no . ':';
        foreach ($po->items as $i) {
            $lines[] = '• ' . ($i->item?->product_name ?? 'Item') . ' × ' . rtrim(rtrim((string) $i->qty_ordered, '0'), '.');
        }
        $lines[] = '';
        $lines[] = 'Order date: ' . Carbon::parse($po->order_date)->format('d M Y');
        if ($po->expected_date) {
            $lines[] = 'Expected delivery: ' . Carbon::parse($po->expected_date)->format('d M Y');
        }
        $lines[] = 'Total (incl. GST): ' . number_format((float) $po->total_amount, 2);
        $lines[] = '';
        $lines[] = 'Kindly confirm. Thank you.';

        $raw    = preg_replace('/\D+/', '', (string) ($vendor?->whatsapp ?: $vendor?->phone ?: ''));
        // Default to India country code when a bare 10-digit number is stored (matches web wa.me/91 usage).
        $number = $raw !== '' && strlen($raw) <= 10 ? '91' . $raw : $raw;

        return [
            'whatsapp_number' => $number ?: null,
            'message'         => implode("\n", $lines),
        ];
    }

    /* ═══════════════════════════════════════════════════════════
       IMPLANTS — catalog + placements
    ═══════════════════════════════════════════════════════════ */

    /** Implant catalog list query (mirrors web InventoryController@implants). */
    public function implantCatalogQuery()
    {
        return ImplantCatalog::withCount('placements')
            ->orderBy('brand')
            ->orderBy('system')
            ->orderBy('component_type');
    }

    /** Implant placements list query. */
    public function implantPlacementsQuery()
    {
        return ImplantPlacement::with(['patient', 'catalogItem', 'surgeon'])
            ->latest('surgery_date');
    }

    /** Patients for the placement form dropdown. */
    public function placementPatients()
    {
        return Patient::orderBy('name')->get(['id', 'name', 'phone']);
    }

    /** Add an implant catalog component (mirrors web @storeCatalogItem). */
    public function createCatalogItem(array $data, ?UploadedFile $photo, User $user): ImplantCatalog
    {
        if ($photo) {
            $data['photo_path'] = $photo->store('implants/catalog', 'public');
        }
        $data['created_by'] = $user->id;

        return ImplantCatalog::create($data);
    }

    /** Update an implant catalog component (mirrors web @updateCatalogItem). */
    public function updateCatalogItem(ImplantCatalog $item, array $data, ?UploadedFile $photo): ImplantCatalog
    {
        if ($photo) {
            if ($item->photo_path) {
                Storage::disk('public')->delete($item->photo_path);
            }
            $data['photo_path'] = $photo->store('implants/catalog', 'public');
        }
        $data['is_active'] = (bool) ($data['is_active'] ?? $item->is_active);

        $item->update($data);

        return $item->fresh();
    }

    /** Record an implant placement (mirrors web @storePlacement). */
    public function createPlacement(array $data, ?UploadedFile $labelPhoto, User $user): ImplantPlacement
    {
        if ($labelPhoto) {
            $data['label_photo_path'] = $labelPhoto->store('implants/labels', 'public');
        }
        $data['created_by'] = $user->id;

        return ImplantPlacement::create($data)->load(['patient', 'catalogItem', 'surgeon']);
    }

    /** Update an implant placement (mirrors web @updatePlacement). */
    public function updatePlacement(ImplantPlacement $placement, array $data, ?UploadedFile $labelPhoto): ImplantPlacement
    {
        if ($labelPhoto) {
            if ($placement->label_photo_path) {
                Storage::disk('public')->delete($placement->label_photo_path);
            }
            $data['label_photo_path'] = $labelPhoto->store('implants/labels', 'public');
        }

        $placement->update($data);

        return $placement->fresh(['patient', 'catalogItem', 'surgeon']);
    }
}
