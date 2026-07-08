<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\ApiController;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryVendor;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\ImplantCatalog;
use App\Models\Inventory\ImplantPlacement;
use App\Models\Inventory\ReusableAsset;
use App\Models\Inventory\StockMovement;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * InventoryController (API v1)
 * ----------------------------
 * Thin controller over InventoryService — the same "one brain" the web
 * inventory pages use. Returns the standard {success, message, data} envelope.
 *
 * NOTE: Inventory is clinic-wide (the tables have no branch_id), so these
 * endpoints are not branch-filtered — this matches the web module exactly.
 * Writes are role-gated in routes/api.php via 'api.role:...'.
 *
 *   GET   /inventory/meta                         dropdown data for all forms
 *   GET   /inventory/dashboard                    KPIs/health/lists for mobile dashboard
 *   GET   /inventory/items                        stock-by-location list
 *   GET   /inventory/products                     product master (search/filter)
 *   GET   /inventory/items/{item}                 one item (detail)
 *   PUT   /inventory/items/{item}                 update item core fields
 *   POST  /inventory/items/{item}/adjust          quick +/- stock adjust
 *   POST  /inventory/stock-in                     record a receipt
 *   POST  /inventory/stock-out                    record usage/damage/etc.
 *   GET   /inventory/vendors                      vendor list (+ WhatsApp)
 *   GET   /inventory/purchase-orders              PO list (status filter)
 *   GET   /inventory/purchase-orders/{po}         one PO (for receive screen)
 *   POST  /inventory/purchase-orders              create PO
 *   PATCH /inventory/purchase-orders/{po}/mark-ordered
 *   POST  /inventory/purchase-orders/{po}/receive record GRN
 *   GET   /inventory/purchase-orders/{po}/whatsapp-message
 *   GET   /inventory/implants/catalog             implant catalog list
 *   POST  /inventory/implants/catalog             add catalog component
 *   POST  /inventory/implants/catalog/{catalogItem}  update component
 *   GET   /inventory/implants/placements          placements list
 *   GET   /inventory/implants/form-options        patients/catalog/surgeons
 *   POST  /inventory/implants/placements          record a placement
 *   POST  /inventory/implants/placements/{placement} update a placement
 */
class InventoryController extends ApiController
{
    public function __construct(private InventoryService $inventory) {}

    /* ─────────── META ─────────── */

    public function meta(): JsonResponse
    {
        return $this->success($this->inventory->meta(), 'Inventory form options');
    }

    /* ─────────── ALERTS (mobile) ─────────── */

    /**
     * GET /api/v1/inventory/alerts
     * Returns three alert buckets for the mobile alerts screen:
     *   low_stock   — items at/below minimum_qty (out + critical + low)
     *   expiring    — stock movements with expiry_date in next 60 days
     *   expired     — stock movements with expiry_date already passed
     */
    public function alerts(): JsonResponse
    {
        // Low / critical / out-of-stock items
        $lowStock = \App\Models\Inventory\InventoryItem::query()
            ->with('category')
            ->withSum('stocks as total_qty', 'available_qty')
            ->where('is_active', true)
            ->whereHas('stocks', function ($q) {
                $q->havingRaw('SUM(available_qty) <= inventory_items.minimum_qty')
                  ->where('minimum_qty', '>', 0);
            }, '<=', 0)
            ->orWhere(function ($q) {
                $q->where('is_active', true)
                  ->withSum('stocks as total_qty', 'available_qty')
                  ->havingRaw('total_qty <= minimum_qty AND minimum_qty > 0');
            })
            ->get()
            ->filter(fn ($i) => ($i->total_qty ?? 0) <= ($i->minimum_qty ?? 0) && ($i->minimum_qty ?? 0) > 0)
            ->map(fn ($i) => [
                'id'               => $i->id,
                'product_name'     => $i->product_name,
                'category_name'    => $i->category?->name,
                'consumption_unit' => $i->consumption_unit,
                'total_qty'        => (float) ($i->total_qty ?? 0),
                'minimum_qty'      => (float) $i->minimum_qty,
                'status'           => ($i->total_qty ?? 0) <= 0 ? 'out'
                    : (($i->total_qty ?? 0) <= ($i->minimum_qty / 2) ? 'critical' : 'low'),
            ])
            ->values();

        // Expiring in next 60 days
        $expiring = \App\Models\Inventory\StockMovement::query()
            ->with(['item:id,product_name,consumption_unit', 'toLocation:id,name'])
            ->whereNotNull('expiry_date')
            ->whereIn('movement_type', ['stock_in', 'opening_stock'])
            ->where('qty', '>', 0)
            ->whereBetween('expiry_date', [today(), today()->addDays(60)])
            ->orderBy('expiry_date')
            ->limit(50)
            ->get()
            ->map(fn ($m) => [
                'item_id'       => $m->inventory_item_id,
                'product_name'  => $m->item?->product_name,
                'unit'          => $m->item?->consumption_unit,
                'location'      => $m->toLocation?->name,
                'batch_no'      => $m->batch_no,
                'expiry_date'   => $m->expiry_date?->format('Y-m-d'),
                'qty'           => (float) $m->qty,
                'days_left'     => (int) today()->diffInDays($m->expiry_date, false),
            ]);

        // Already expired (last 30 days to keep list manageable)
        $expired = \App\Models\Inventory\StockMovement::query()
            ->with(['item:id,product_name,consumption_unit', 'toLocation:id,name'])
            ->whereNotNull('expiry_date')
            ->whereIn('movement_type', ['stock_in', 'opening_stock'])
            ->where('qty', '>', 0)
            ->whereBetween('expiry_date', [today()->subDays(30), today()->subDay()])
            ->orderByDesc('expiry_date')
            ->limit(30)
            ->get()
            ->map(fn ($m) => [
                'item_id'      => $m->inventory_item_id,
                'product_name' => $m->item?->product_name,
                'unit'         => $m->item?->consumption_unit,
                'location'     => $m->toLocation?->name,
                'batch_no'     => $m->batch_no,
                'expiry_date'  => $m->expiry_date?->format('Y-m-d'),
                'qty'          => (float) $m->qty,
                'days_ago'     => (int) $m->expiry_date->diffInDays(today(), false),
            ]);

        return $this->success([
            'low_stock' => $lowStock,
            'expiring'  => $expiring,
            'expired'   => $expired,
        ], 'Inventory alerts');
    }

    /**
     * GET /api/v1/inventory/dashboard
     *
     * Mobile-shaped version of the web Inventory Dashboard (see the private
     * buildKpis()/buildStockStatus()/buildHealthScore()/buildExtraKpis()/
     * buildCriticalItems()/buildExpiringSoon()/buildRecentMovements()/
     * buildFooterStats()/buildAssistantActionItems() methods on the web
     * App\Http\Controllers\InventoryController — same thresholds/logic,
     * but flat data (no SVG icon paths / hex colors, no chart series).
     * Mobile picks its own icons/colors and skips value-trend + category
     * charts entirely (V1 mobile is simpler than the web dashboard).
     */
    public function dashboard(Request $request): JsonResponse
    {
        /* ── KPIs ── */
        $totalValue = DB::table('inventory_stocks')
            ->join('inventory_items', 'inventory_stocks.inventory_item_id', '=', 'inventory_items.id')
            ->where('inventory_items.is_active', true)
            ->sum(DB::raw('inventory_stocks.available_qty * inventory_items.average_purchase_price'));

        $totalItems = InventoryItem::where('is_active', true)->count();

        $lowStockCount = DB::table('inventory_items as i')
            ->join('inventory_stocks as s', 'i.id', '=', 's.inventory_item_id')
            ->where('i.is_active', true)
            ->whereRaw('s.available_qty > 0')
            ->whereRaw('s.available_qty <= i.minimum_qty')
            ->distinct('i.id')
            ->count('i.id');

        $outOfStockCount = InventoryItem::where('is_active', true)
            ->whereDoesntHave('stocks', fn ($q) => $q->where('available_qty', '>', 0))
            ->count();

        $expiringSoonCount = StockMovement::whereNotNull('expiry_date')
            ->where('expiry_date', '>=', today())
            ->where('expiry_date', '<=', today()->addDays(90))
            ->distinct('inventory_item_id')
            ->count('inventory_item_id');

        $assetsDue = ReusableAsset::whereNotNull('retirement_threshold')
            ->whereRaw('current_usage_count >= retirement_threshold')
            ->count();

        /* ── Stock status donut buckets ── */
        $items = InventoryItem::where('is_active', true)->with('stocks')->get();
        $healthy = $low = $critical = $out = 0;
        foreach ($items as $item) {
            $qty = $item->stocks->sum('available_qty');
            if ($qty <= 0) {
                $out++;
            } elseif ($item->minimum_qty > 0 && $qty <= $item->minimum_qty) {
                $critical++;
            } elseif ($item->minimum_qty > 0 && $qty <= $item->minimum_qty * 1.5) {
                $low++;
            } else {
                $healthy++;
            }
        }

        /* ── Health score (0-100) ── */
        $totalActive = $totalItems ?: 1;

        $lowForScore = $lowStockCount; // qty>0 but <= minimum_qty (same query as KPI)

        $critExpiry = StockMovement::whereNotNull('expiry_date')
            ->where('expiry_date', '>=', today())
            ->where('expiry_date', '<=', today()->addDays(30))
            ->where('qty', '>', 0)
            ->distinct('inventory_item_id')
            ->count('inventory_item_id');

        $deadCount = DB::table('inventory_items as i')
            ->join('inventory_stocks as s', 'i.id', '=', 's.inventory_item_id')
            ->where('i.is_active', true)
            ->where('s.available_qty', '>', 0)
            ->whereNotExists(function ($q) {
                $q->from('stock_movements')
                    ->whereColumn('stock_movements.inventory_item_id', 'i.id')
                    ->where('stock_movements.created_at', '>=', now()->subDays(90));
            })
            ->distinct('i.id')
            ->count('i.id');

        $score = 100;
        $score -= min(35, (int) round(($outOfStockCount / $totalActive) * 100 * 0.35));
        $score -= min(25, (int) round(($lowForScore / $totalActive) * 100 * 0.25));
        $score -= min(20, (int) round(($critExpiry / $totalActive) * 100 * 0.20));
        $score -= min(10, (int) round(($deadCount / $totalActive) * 100 * 0.10));
        $score  = max(0, $score);

        if ($score >= 90) {
            $grade = 'Excellent';
        } elseif ($score >= 70) {
            $grade = 'Good';
        } elseif ($score >= 50) {
            $grade = 'Needs Attention';
        } else {
            $grade = 'Critical';
        }

        /* ── Critical items (qty <= minimum_qty), lowest stock first, top 8 ── */
        $criticalItems = InventoryItem::where('is_active', true)
            ->with(['stocks', 'category'])
            ->get()
            ->filter(fn ($item) => $item->total_stock <= $item->minimum_qty)
            ->sortBy('total_stock')
            ->take(8)
            ->values()
            ->map(fn ($item) => [
                'item_id'       => $item->id,
                'product_name'  => $item->product_name,
                'available_qty' => (float) $item->total_stock,
                'minimum_qty'   => (float) $item->minimum_qty,
                'category_name' => $item->category?->name,
            ]);

        /* ── Expiring soon (next 90 days), soonest first, top 8 ── */
        $expiringSoon = StockMovement::with('item')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', today())
            ->where('expiry_date', '<=', today()->addDays(90))
            ->whereIn('movement_type', ['stock_in', 'opening_stock'])
            ->orderBy('expiry_date')
            ->take(8)
            ->get()
            ->map(fn ($m) => [
                'item_id'      => $m->inventory_item_id,
                'product_name' => $m->item?->product_name,
                'qty'          => (float) $m->qty,
                'expiry_date'  => $m->expiry_date?->format('Y-m-d'),
                'batch_no'     => $m->batch_no,
            ]);

        /* ── Recent movements feed, latest 12 ── */
        $recentMovements = StockMovement::with(['item', 'createdBy', 'fromLocation', 'toLocation'])
            ->latest()
            ->take(12)
            ->get()
            ->map(fn ($m) => [
                'id'            => $m->id,
                'product_name'  => $m->item?->product_name,
                'movement_type' => $m->movement_type,
                'qty'           => (float) $m->qty,
                'location'      => $m->toLocation?->name ?? $m->fromLocation?->name,
                'created_at'    => $m->created_at?->toIso8601String(),
                'created_by'    => $m->createdBy?->name,
            ]);

        /* ── Footer stats ── */
        $footerStats = [
            'locations'       => InventoryLocation::where('is_active', true)->count(),
            'vendors'         => InventoryVendor::where('is_active', true)->count(),
            'pending_pos'     => PurchaseOrder::whereIn('status', ['draft', 'ordered', 'partially_received'])->count(),
            'grn_pending'     => PurchaseOrder::whereIn('status', ['ordered', 'partially_received'])->count(),
            'stock_out_today' => StockMovement::where('movement_type', 'stock_out')->whereDate('created_at', today())->count(),
            'reusable_assets' => ReusableAsset::count(),
        ];

        /* ── Action items (plain text, no routes/icons) ── */
        $actionItems = [];

        $todayPOs = PurchaseOrder::with('vendor')
            ->whereIn('status', ['ordered', 'partially_received'])
            ->whereDate('expected_date', today())
            ->get();
        foreach ($todayPOs as $po) {
            $actionItems[] = [
                'priority' => 'high',
                'text'     => 'Receive delivery from ' . ($po->vendor?->vendor_name ?? 'vendor'),
                'sub'      => 'Order #' . ($po->order_no ?? $po->id) . ' expected today',
            ];
        }

        $urgentExpiry = StockMovement::with('item')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', today())
            ->where('expiry_date', '<=', today()->addDays(14))
            ->where('qty', '>', 0)
            ->orderBy('expiry_date')
            ->take(3)
            ->get();
        foreach ($urgentExpiry as $exp) {
            $days = today()->diffInDays($exp->expiry_date);
            $actionItems[] = [
                'priority' => 'medium',
                'text'     => 'Use ' . ($exp->item?->product_name ?? 'item') . ' before expiry',
                'sub'      => 'Expires in ' . $days . ' day' . ($days === 1 ? '' : 's'),
            ];
        }

        $oosItems = InventoryItem::where('is_active', true)
            ->whereDoesntHave('stocks', fn ($q) => $q->where('available_qty', '>', 0))
            ->take(3)
            ->get();
        foreach ($oosItems as $item) {
            $actionItems[] = [
                'priority' => 'high',
                'text'     => $item->product_name . ' is out of stock',
                'sub'      => 'Notify manager to reorder',
            ];
        }

        $actionItems = array_slice($actionItems, 0, 5);

        return $this->success([
            'kpis' => [
                'inventory_value' => (float) $totalValue,
                'total_items'     => $totalItems,
                'low_stock'       => $lowStockCount,
                'critical_oos'    => $outOfStockCount,
                'expiring_soon'   => $expiringSoonCount,
                'assets_due'      => $assetsDue,
            ],
            'stock_status' => [
                'healthy'  => $healthy,
                'low'      => $low,
                'critical' => $critical,
                'out'      => $out,
            ],
            'health_score' => [
                'score'     => $score,
                'grade'     => $grade,
                'penalties' => [
                    'oos'    => $outOfStockCount,
                    'low'    => $lowForScore,
                    'expiry' => $critExpiry,
                    'dead'   => $deadCount,
                ],
            ],
            'critical_items'   => $criticalItems,
            'expiring_soon'    => $expiringSoon,
            'recent_movements' => $recentMovements,
            'footer_stats'     => $footerStats,
            'action_items'     => $actionItems,
            'generated_at'     => now()->toIso8601String(),
        ], '');
    }

    /* ─────────── ITEMS / STOCK ─────────── */

    public function items(Request $request): JsonResponse
    {
        $query = $this->inventory->itemsStockQuery($request->only(['category_id', 'location_id', 'search', 'sort', 'dir', 'stock_status']));
        $page  = $this->paginate($query, $request);

        return $this->respond($page, fn ($r) => [
            'item_id'        => (int) $r->item_id,
            'product_name'   => $r->product_name,
            'generic_name'   => $r->generic_name,
            'consumption_unit' => $r->consumption_unit,
            'minimum_qty'    => (float) $r->minimum_qty,
            'reorder_level'  => (float) $r->reorder_level,
            'available_qty'  => (float) ($r->available_qty ?? 0),
            'location_id'    => $r->location_id ? (int) $r->location_id : null,
            'location_name'  => $r->location_name,
            'category_id'    => $r->category_id ? (int) $r->category_id : null,
            'category_name'  => $r->category_name,
            'sub_type_name'  => $r->sub_type_name,
        ]);
    }

    public function products(Request $request): JsonResponse
    {
        $query = $this->inventory->productsQuery(
            $request->only(['search', 'category_id', 'sub_type_id', 'brand', 'location_id', 'stock_level', 'sellable_only'])
        );
        $page = $this->paginate($query, $request);

        return $this->respond($page, fn (InventoryItem $i) => $this->mapProduct($i));
    }

    /**
     * POST /api/v1/inventory/products
     * Create a new product in the inventory product master — full parity
     * with the web Add Product modal (2026-07-07), including the Clinical /
     * Saleable(FMCG) split. `product_kind` = 'saleable' routes to the
     * minimal retail form; anything else (including absent) is Clinical.
     * Mirrors InventoryController::storeItem()/prepareProductData()/
     * prepareSaleableProductData()/attachProductRelations() on web exactly —
     * see that class for the canonical version this follows.
     */
    public function storeProduct(Request $request): JsonResponse
    {
        [$data, $primaryVendorId, $alternateVendorIds] = $request->input('product_kind') === 'saleable'
            ? $this->prepareSaleableProductData($this->validateSaleableProductForm($request))
            : $this->prepareClinicalProductData($request, $this->validateClinicalProductForm($request));

        $data['item_code']              = 'ITEM-' . str_pad(InventoryItem::count() + 1, 4, '0', STR_PAD_LEFT);
        $data['average_purchase_price'] = $data['last_purchase_price'] ?? 0;
        $data['created_by']             = $request->user()->id;

        $item = InventoryItem::create($data);

        if ($primaryVendorId) {
            $item->dealers()->syncWithoutDetaching([$primaryVendorId => ['is_primary' => true]]);
        }
        foreach ($alternateVendorIds as $vendorId) {
            if ($vendorId != $primaryVendorId) {
                $item->dealers()->syncWithoutDetaching([$vendorId => ['is_alternate' => true]]);
            }
        }

        return $this->success($this->mapProduct($item), 'Product created.', 201);
    }

    /** Validation for the Clinical product form — mirrors web's validateProductForm(). */
    private function validateClinicalProductForm(Request $request): array
    {
        return $request->validate([
            'product_name'           => 'required|string|max:255',
            'brand'                  => 'nullable|string|max:255',
            'category_id'            => 'nullable|exists:inventory_categories,id',
            'sub_type_id'            => 'nullable|exists:inventory_sub_types,id',
            'variant_id'             => 'nullable|exists:inventory_variants,id',
            'usage_type'             => 'nullable|in:single_use,multiple_use',
            'max_usage_count'        => 'nullable|integer|min:1',
            'description'            => 'nullable|string|max:2000',
            'packaging_type'         => 'required|string|max:60',
            'qty_in_packaging'       => 'required|numeric|min:0.01',
            'packaging_unit_label'   => 'required|string|max:20',
            'company_name'           => 'nullable|string|max:100',
            'photo'                  => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'last_purchase_price'    => 'nullable|numeric|min:0',
            'mrp'                    => 'nullable|numeric|min:0',
            'minimum_qty'            => 'required|numeric|min:0',
            'reorder_level'          => 'nullable|numeric|min:0',
            'alternative_brands'     => 'nullable|array',
            'alternative_brands.*'   => 'string|max:255',
            'primary_vendor_id'      => 'nullable|exists:inventory_vendors,id',
            'alternate_vendor_ids'   => 'nullable|array',
            'alternate_vendor_ids.*' => 'exists:inventory_vendors,id',
            'treatment_tags'         => 'nullable|array',
            'treatment_tags.*'       => 'string|max:100',
            'product_notes'          => 'nullable|string|max:2000',
            'is_active'              => 'boolean',
        ]);
    }

    /** Validation for the Saleable/FMCG form — mirrors web's validateSaleableProductForm(). */
    private function validateSaleableProductForm(Request $request): array
    {
        return $request->validate([
            'product_name'       => 'required|string|max:255',
            'retail_type'        => 'required|string|max:40',
            'brand'              => 'required|string|max:255',
            'mrp'                => 'required|numeric|min:0',
            'retail_expiry_date' => 'nullable|date',
            'minimum_qty'        => 'required|numeric|min:0',
        ]);
    }

    /**
     * Fold Clinical form data into InventoryItem columns. Returns
     * [$data, $primaryVendorId, $alternateVendorIds] — mirrors web's
     * prepareProductData() (photo upload + packaging -> legacy unit columns).
     */
    private function prepareClinicalProductData(Request $request, array $data): array
    {
        $primaryVendorId    = $data['primary_vendor_id'] ?? null;
        $alternateVendorIds = $data['alternate_vendor_ids'] ?? [];
        unset($data['primary_vendor_id'], $data['alternate_vendor_ids']);

        if ($request->hasFile('photo')) {
            $data['image'] = $request->file('photo')->store('inventory/products', 'public');
        }
        unset($data['photo']);

        $data['usage_type']         = $data['usage_type'] ?? 'multiple_use';
        $data['inventory_behavior'] = $data['usage_type'] === 'single_use' ? 'consumable' : 'reusable';
        $data['purchase_unit']      = $data['packaging_type'];
        $data['consumption_unit']   = $data['packaging_unit_label'];
        $data['pieces_per_unit']    = max(1, (int) round($data['qty_in_packaging']));
        $data['minimum_order_qty']  = 1;
        $data['is_reusable']        = $data['usage_type'] !== 'single_use';
        $data['last_purchase_price'] = $data['last_purchase_price'] ?? 0;
        $data['reorder_level']       = $data['reorder_level'] ?? 0;
        $data['is_active']          = $request->boolean('is_active', true);
        $data['is_sellable']        = false;

        return [$data, $primaryVendorId, $alternateVendorIds];
    }

    /**
     * Fills in every column the Saleable tab doesn't ask about. Mirrors
     * web's prepareSaleableProductData() — no vendor linking from this tab.
     */
    private function prepareSaleableProductData(array $data): array
    {
        $data['is_sellable']         = true;
        $data['is_active']           = true;
        $data['usage_type']          = 'multiple_use';
        $data['inventory_behavior']  = 'reusable';
        $data['is_reusable']         = true;
        $data['packaging_type']      = 'Piece';
        $data['qty_in_packaging']    = 1;
        $data['packaging_unit_label'] = 'units';
        $data['purchase_unit']       = 'Piece';
        $data['consumption_unit']    = 'units';
        $data['pieces_per_unit']     = 1;
        $data['minimum_order_qty']   = 1;
        $data['last_purchase_price'] = 0;
        $data['reorder_level']       = 0;

        return [$data, null, []];
    }

    public function showItem(InventoryItem $item): JsonResponse
    {
        $model = $this->inventory->findItem($item->id);
        if (! $model) {
            return $this->error('Item not found.', [], 404);
        }

        return $this->success($this->mapProduct($model, true), '');
    }

    public function updateItem(Request $request, InventoryItem $item): JsonResponse
    {
        $data = $request->validate([
            'product_name'        => 'required|string|max:255',
            'generic_name'        => 'nullable|string|max:255',
            'brand'               => 'nullable|string|max:255',
            'category_id'         => 'nullable|exists:inventory_categories,id',
            'inventory_behavior'  => 'required|in:consumable,reusable,semi_reusable',
            'purchase_unit'       => 'required|string|max:40',
            'consumption_unit'    => 'required|string|max:40',
            'pieces_per_unit'     => 'required|integer|min:1',
            'minimum_qty'         => 'required|numeric|min:0',
            'minimum_order_qty'   => 'required|numeric|min:1',
            'last_purchase_price' => 'nullable|numeric|min:0',
            'gst_rate'            => 'nullable|numeric|min:0|max:100',
            'has_expiry'          => 'boolean',
            'is_reusable'         => 'boolean',
        ]);

        $this->inventory->updateItem($item, $data);

        return $this->success($this->mapProduct($this->inventory->findItem($item->id), true), 'Item updated.');
    }

    public function adjustStock(Request $request, InventoryItem $item): JsonResponse
    {
        $data = $request->validate([
            'type'        => 'required|in:add,remove',
            'qty'         => 'required|integer|min:1',
            'location_id' => 'required|exists:inventory_locations,id',
            'note'        => 'nullable|string|max:255',
        ]);

        try {
            $this->inventory->adjustStock($item, $data, $this->user($request));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), [], 422);
        }

        return $this->success($this->mapProduct($this->inventory->findItem($item->id), true), 'Stock updated.');
    }

    /* ─────────── STOCK IN / OUT ─────────── */

    public function stockIn(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id'  => 'required|exists:inventory_items,id',
            'to_location_id'     => 'required|exists:inventory_locations,id',
            'qty'                => 'required|numeric|min:0.01',
            'unit_cost'          => 'required|numeric|min:0.01',
            'batch_no'           => 'nullable|string|max:80',
            'expiry_date'        => 'nullable|date|after:today',
            'manufacturing_date' => 'nullable|date',
            'notes'              => 'nullable|string|max:500',
        ]);

        $movement = $this->inventory->createStockIn($data, $this->user($request));

        return $this->success(['movement_id' => $movement->id], 'Stock In recorded.', 201);
    }

    public function stockOut(Request $request): JsonResponse
    {
        $data = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'from_location_id'  => 'required|exists:inventory_locations,id',
            'qty'               => 'required|numeric|min:0.01',
            'movement_type'     => 'required|in:stock_out,treatment_usage,damaged,expired,adjustment',
            'notes'             => 'nullable|string|max:500',
        ]);

        try {
            $movement = $this->inventory->createStockOut($data, $this->user($request));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), ['qty' => [$e->getMessage()]], 422);
        }

        return $this->success(['movement_id' => $movement->id], 'Stock Out recorded.', 201);
    }

    /* ─────────── VENDORS ─────────── */

    public function vendors(Request $request): JsonResponse
    {
        $query = $this->inventory->vendorsQuery();
        if ($search = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => $q
                ->where('vendor_name', 'like', "%{$search}%")
                ->orWhere('contact_person', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }
        $page = $this->paginate($query, $request);

        return $this->respond($page, fn (InventoryVendor $v) => $this->mapVendor($v));
    }

    /* ─────────── PURCHASE ORDERS + GRN ─────────── */

    public function purchaseOrders(Request $request): JsonResponse
    {
        $query = $this->inventory->purchaseOrdersQuery($request->only(['status']));
        $page  = $this->paginate($query, $request);

        return $this->respond($page, fn (PurchaseOrder $po) => $this->mapPoList($po));
    }

    public function showPurchaseOrder(PurchaseOrder $po): JsonResponse
    {
        return $this->success($this->mapPoDetail($this->inventory->findPurchaseOrder($po->id)), '');
    }

    public function storePurchaseOrder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_id'       => 'required|exists:inventory_vendors,id',
            'order_date'      => 'required|date',
            'expected_date'   => 'nullable|date|after_or_equal:order_date',
            'status'          => 'required|in:draft,ordered',
            'notes'           => 'nullable|string|max:1000',
            'items'           => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:inventory_items,id',
            'items.*.qty'     => 'required|integer|min:1',
            'items.*.price'   => 'required|numeric|min:0',
            'items.*.gst'     => 'nullable|numeric|min:0|max:100',
        ]);

        $po = $this->inventory->createPurchaseOrder($data, $this->user($request));

        return $this->success($this->mapPoDetail($po), "Purchase Order {$po->order_no} created.", 201);
    }

    public function markOrdered(Request $request, PurchaseOrder $po): JsonResponse
    {
        try {
            $updated = $this->inventory->markOrdered($po);
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), [], 422);
        }

        return $this->success($this->mapPoList($updated), "PO {$updated->order_no} marked as Ordered.");
    }

    public function receivePurchaseOrder(Request $request, PurchaseOrder $po): JsonResponse
    {
        $data = $request->validate([
            'location_id'       => 'required|exists:inventory_locations,id',
            'received_date'     => 'required|date',
            'vendor_invoice_no' => 'nullable|string|max:80',
            'lines'             => 'required|array|min:1',
            'lines.*.item_id'   => 'required|exists:inventory_items,id',
            'lines.*.qty'       => 'required|integer|min:0',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
            'lines.*.batch_no'  => 'nullable|string|max:80',
            'lines.*.expiry'    => 'nullable|date',
        ]);

        try {
            $grn = $this->inventory->receivePurchaseOrder($po, $data, $this->user($request));
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), ['lines' => [$e->getMessage()]], 422);
        }

        return $this->success([
            'grn_number'   => $grn->grn_number,
            'po_status'    => $po->fresh()->status,
        ], "GRN recorded for PO# {$po->order_no}. Stock updated; pending bill added to Finance.", 201);
    }

    public function purchaseOrderWhatsapp(PurchaseOrder $po): JsonResponse
    {
        return $this->success($this->inventory->purchaseOrderWhatsappMessage($po), '');
    }

    /* ─────────── IMPLANTS ─────────── */

    public function implantCatalog(Request $request): JsonResponse
    {
        $page = $this->paginate($this->inventory->implantCatalogQuery(), $request, 30);

        return $this->respond($page, fn (ImplantCatalog $c) => $this->mapCatalog($c));
    }

    public function storeCatalogItem(Request $request): JsonResponse
    {
        $data = $request->validate([
            'brand'          => 'required|string|max:100',
            'system'         => 'nullable|string|max:100',
            'component_type' => 'required|in:fixture,abutment,healing_abutment,analogue,scan_body,coping,graft,other',
            'product_code'   => 'nullable|string|max:100',
            'description'    => 'nullable|string|max:255',
            'diameter_mm'    => 'nullable|string|max:30',
            'length_mm'      => 'nullable|string|max:30',
            'platform'       => 'nullable|string|max:60',
            'material'       => 'nullable|string|max:80',
            'unit_price'     => 'nullable|numeric|min:0',
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        unset($data['photo']);

        $item = $this->inventory->createCatalogItem($data, $request->file('photo'), $this->user($request));

        return $this->success($this->mapCatalog($item), 'Implant component added to catalog.', 201);
    }

    public function updateCatalogItem(Request $request, ImplantCatalog $catalogItem): JsonResponse
    {
        $data = $request->validate([
            'brand'          => 'required|string|max:100',
            'system'         => 'nullable|string|max:100',
            'component_type' => 'required|in:fixture,abutment,healing_abutment,analogue,scan_body,coping,graft,other',
            'product_code'   => 'nullable|string|max:100',
            'description'    => 'nullable|string|max:255',
            'diameter_mm'    => 'nullable|string|max:30',
            'length_mm'      => 'nullable|string|max:30',
            'platform'       => 'nullable|string|max:60',
            'material'       => 'nullable|string|max:80',
            'unit_price'     => 'nullable|numeric|min:0',
            'is_active'      => 'boolean',
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        unset($data['photo']);

        $item = $this->inventory->updateCatalogItem($catalogItem, $data, $request->file('photo'));

        return $this->success($this->mapCatalog($item), 'Component updated.');
    }

    public function implantPlacements(Request $request): JsonResponse
    {
        $page = $this->paginate($this->inventory->implantPlacementsQuery(), $request, 30);

        return $this->respond($page, fn (ImplantPlacement $p) => $this->mapPlacement($p));
    }

    public function implantFormOptions(): JsonResponse
    {
        $catalog = ImplantCatalog::where('is_active', true)
            ->orderBy('brand')->orderBy('system')
            ->get(['id', 'brand', 'system', 'component_type', 'product_code']);

        $surgeons = User::where('is_active', true)
            ->where(fn ($q) => $q->whereIn('role', User::DOCTOR_ROLES)->orWhere('name', 'like', 'Dr.%'))
            ->orderBy('name')->get(['id', 'name']);

        return $this->success([
            'patients' => $this->inventory->placementPatients(),
            'catalog'  => $catalog->map(fn ($c) => [
                'id'   => $c->id,
                'name' => trim("{$c->brand} {$c->system} {$c->component_type} {$c->product_code}"),
            ])->values(),
            'surgeons' => $surgeons,
            'statuses' => ['placed', 'osseointegrating', 'loaded', 'failed', 'explanted'],
        ], 'Implant placement form options');
    }

    public function storePlacement(Request $request): JsonResponse
    {
        $data = $request->validate([
            'patient_id'             => 'required|exists:patients,id',
            'treatment_visit_id'     => 'nullable|exists:treatment_visits,id',
            'implant_catalog_id'     => 'nullable|exists:implant_catalog,id',
            'surgeon_id'             => 'nullable|exists:users,id',
            'lot_number'             => 'nullable|string|max:100',
            'serial_number'          => 'nullable|string|max:100',
            'tooth_position'         => 'nullable|string|max:30',
            'surgery_date'           => 'required|date',
            'implant_brand_freetext' => 'nullable|string|max:150',
            'implant_code_freetext'  => 'nullable|string|max:150',
            'status'                 => 'required|in:placed,osseointegrating,loaded,failed,explanted',
            'notes'                  => 'nullable|string|max:1000',
            'label_photo'            => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);
        unset($data['label_photo']);

        $placement = $this->inventory->createPlacement($data, $request->file('label_photo'), $this->user($request));

        return $this->success($this->mapPlacement($placement), 'Implant placement recorded.', 201);
    }

    public function updatePlacement(Request $request, ImplantPlacement $placement): JsonResponse
    {
        $data = $request->validate([
            'status'         => 'required|in:placed,osseointegrating,loaded,failed,explanted',
            'lot_number'     => 'nullable|string|max:100',
            'serial_number'  => 'nullable|string|max:100',
            'tooth_position' => 'nullable|string|max:30',
            'surgery_date'   => 'nullable|date',
            'notes'          => 'nullable|string|max:1000',
            'label_photo'    => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);
        unset($data['label_photo']);

        $updated = $this->inventory->updatePlacement($placement, $data, $request->file('label_photo'));

        return $this->success($this->mapPlacement($updated), 'Placement updated.');
    }

    /* ═══════════════════════════════════════════════════════════
       Helpers — pagination + response shaping
    ═══════════════════════════════════════════════════════════ */

    /** Authenticated user (typed). */
    private function user(Request $request): User
    {
        return $request->user();
    }

    /** Paginate any query builder with clamped limit. */
    private function paginate($query, Request $request, int $default = 20): LengthAwarePaginator
    {
        $limit = (int) $request->query('limit', $default);
        $limit = max(1, min($limit, 100));

        return $query->paginate($limit)->appends($request->query());
    }

    /** Map a paginator through a callback and return the standard envelope + meta. */
    private function respond(LengthAwarePaginator $page, callable $map, string $message = ''): JsonResponse
    {
        $items = collect($page->items())->map($map)->values();

        return $this->success($items, $message, 200, [
            'current_page' => $page->currentPage(),
            'per_page'     => $page->perPage(),
            'total'        => $page->total(),
            'last_page'    => $page->lastPage(),
        ]);
    }

    /* ── Model → array mappers ── */

    private function mapProduct(InventoryItem $i, bool $withStocks = false): array
    {
        $out = [
            'id'                  => $i->id,
            'item_code'           => $i->item_code,
            'product_name'        => $i->product_name,
            'generic_name'        => $i->generic_name,
            'brand'               => $i->brand,
            'company_name'        => $i->company_name,
            'category_id'         => $i->category_id,
            'category_name'       => $i->category?->name,
            'sub_type_id'         => $i->sub_type_id,
            'sub_type_name'       => $i->subType?->name,
            'variant_id'          => $i->variant_id,
            'variant_name'        => $i->variant?->name,
            // Clinical form extras (2026-07-07 mobile parity)
            'usage_type'          => $i->usage_type,
            'max_usage_count'     => $i->max_usage_count,
            'description'         => $i->description,
            'alternative_brands'  => $i->alternative_brands ?? [],
            'treatment_tags'      => $i->treatment_tags ?? [],
            'product_notes'       => $i->product_notes,
            // Saleable/FMCG extras
            'retail_type'         => $i->retail_type,
            'retail_expiry_date'  => $i->retail_expiry_date?->format('Y-m-d'),
            'inventory_behavior'  => $i->inventory_behavior,
            'purchase_unit'       => $i->purchase_unit,
            'consumption_unit'    => $i->consumption_unit,
            'pieces_per_unit'     => $i->pieces_per_unit,
            'minimum_qty'         => (float) $i->minimum_qty,
            'minimum_order_qty'   => (float) $i->minimum_order_qty,
            'reorder_level'       => (float) $i->reorder_level,
            'last_purchase_price' => (float) $i->last_purchase_price,
            'mrp'                 => $i->mrp !== null ? (float) $i->mrp : null,
            'gst_rate'            => (float) $i->gst_rate,
            // Retail/FMCG billing flag (2026-07-06 web parity) — items with
            // this true can be added as a sold-product line on an invoice.
            'is_sellable'         => (bool) $i->is_sellable,
            'has_expiry'          => (bool) $i->has_expiry,
            'is_reusable'         => (bool) $i->is_reusable,
            'total_qty'           => (float) ($i->total_qty ?? 0),
            'is_active'           => (bool) $i->is_active,
            'image_url'           => $i->image ? \Illuminate\Support\Facades\Storage::url($i->image) : null,
        ];

        if ($withStocks && $i->relationLoaded('stocks')) {
            $out['stocks'] = $i->stocks->map(fn ($s) => [
                'location_id'   => $s->location_id,
                'location_name' => $s->location?->name,
                'available_qty' => (float) $s->available_qty,
            ])->values();
        }

        return $out;
    }

    private function mapVendor(InventoryVendor $v): array
    {
        return [
            'id'             => $v->id,
            'vendor_name'    => $v->vendor_name,
            'contact_person' => $v->contact_person,
            'phone'          => $v->phone,
            'whatsapp'       => $v->whatsapp,
            'email'          => $v->email,
            'gst_no'         => $v->gst_no,
            'address'        => $v->address,
            'city'           => $v->city,
            'credit_days'    => $v->credit_days,
            'is_active'      => (bool) $v->is_active,
        ];
    }

    private function mapPoList(PurchaseOrder $po): array
    {
        return [
            'id'            => $po->id,
            'order_no'      => $po->order_no,
            'vendor_id'     => $po->vendor_id,
            'vendor_name'   => $po->vendor?->vendor_name,
            'order_date'    => optional($po->order_date)->format('Y-m-d') ?? (string) $po->order_date,
            'expected_date' => $po->expected_date ? (optional($po->expected_date)->format('Y-m-d') ?? (string) $po->expected_date) : null,
            'status'        => $po->status,
            'invoice_status'=> $po->invoice_status,
            'total_amount'  => (float) $po->total_amount,
            'gst_amount'    => (float) $po->gst_amount,
            'item_count'    => $po->items->count(),
        ];
    }

    private function mapPoDetail(PurchaseOrder $po): array
    {
        return array_merge($this->mapPoList($po), [
            'notes' => $po->notes,
            'items' => $po->items->map(fn ($line) => [
                'id'                => $line->id,
                'inventory_item_id' => $line->inventory_item_id,
                'product_name'      => $line->item?->product_name,
                'qty_ordered'       => (float) $line->qty_ordered,
                'qty_received'      => (float) $line->qty_received,
                'unit_price'        => (float) $line->unit_price,
                'gst_rate'          => (float) $line->gst_rate,
                'total_price'       => (float) $line->total_price,
            ])->values(),
        ]);
    }

    private function mapCatalog(ImplantCatalog $c): array
    {
        return [
            'id'              => $c->id,
            'brand'           => $c->brand,
            'system'          => $c->system,
            'component_type'  => $c->component_type,
            'product_code'    => $c->product_code,
            'description'     => $c->description,
            'diameter_mm'     => $c->diameter_mm,
            'length_mm'       => $c->length_mm,
            'platform'        => $c->platform,
            'material'        => $c->material,
            'unit_price'      => $c->unit_price !== null ? (float) $c->unit_price : null,
            'photo_url'       => $c->photo_path ? asset('storage/' . $c->photo_path) : null,
            'is_active'       => (bool) $c->is_active,
            'placements_count'=> $c->placements_count ?? null,
        ];
    }

    private function mapPlacement(ImplantPlacement $p): array
    {
        return [
            'id'                     => $p->id,
            'patient_id'             => $p->patient_id,
            'patient_name'           => $p->patient?->name,
            'implant_catalog_id'     => $p->implant_catalog_id,
            'catalog_name'           => $p->catalogItem
                ? trim("{$p->catalogItem->brand} {$p->catalogItem->system} {$p->catalogItem->component_type}")
                : null,
            'surgeon_id'             => $p->surgeon_id,
            'surgeon_name'           => $p->surgeon?->name,
            'lot_number'             => $p->lot_number,
            'serial_number'          => $p->serial_number,
            'tooth_position'         => $p->tooth_position,
            'surgery_date'           => $p->surgery_date ? (optional($p->surgery_date)->format('Y-m-d') ?? (string) $p->surgery_date) : null,
            'implant_brand_freetext' => $p->implant_brand_freetext,
            'implant_code_freetext'  => $p->implant_code_freetext,
            'status'                 => $p->status,
            'label_photo_url'        => $p->label_photo_path ? asset('storage/' . $p->label_photo_path) : null,
            'notes'                  => $p->notes,
        ];
    }
}
