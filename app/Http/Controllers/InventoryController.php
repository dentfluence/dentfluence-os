<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\InventoryCategory;
use App\Models\Inventory\InventoryLocation;
use App\Models\Inventory\InventoryStock;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\InventoryVendor;
use App\Models\Inventory\ReusableAsset;
use App\Models\Inventory\ImplantCatalog;
use App\Models\Inventory\ImplantPlacement;
use Carbon\Carbon;

class InventoryController extends Controller
{
    /* ═══════════════════════════════════════════════════════════
       DASHBOARD — Phase 1
       Returns all KPI data, charts, feeds for the dashboard view.
    ═══════════════════════════════════════════════════════════ */

    public function dashboard()
    {
        $kpis            = $this->buildKpis();
        $stockStatus     = $this->buildStockStatus();
        $categoryValues  = $this->buildCategoryValues();
        $valueTrend      = $this->buildValueTrend();
        $criticalItems   = $this->buildCriticalItems();
        $expiringSoon    = $this->buildExpiringSoon();
        $recentMovements = $this->buildRecentMovements();
        $footerStats     = $this->buildFooterStats();

        return view('inventory.dashboard', compact(
            'kpis', 'stockStatus', 'categoryValues', 'valueTrend',
            'criticalItems', 'expiringSoon', 'recentMovements', 'footerStats'
        ));
    }

    /* ─────────────────────────────────────────────────────────
       KPI CARDS
    ───────────────────────────────────────────────────────── */

    private function buildKpis(): array
    {
        $totalValue = DB::table('inventory_stocks')
            ->join('inventory_items', 'inventory_stocks.inventory_item_id', '=', 'inventory_items.id')
            ->where('inventory_items.is_active', true)
            ->sum(DB::raw('inventory_stocks.available_qty * inventory_items.average_purchase_price'));

        $totalItems = InventoryItem::where('is_active', true)->count();

        $lowStock = DB::table('inventory_items as i')
            ->join('inventory_stocks as s', 'i.id', '=', 's.inventory_item_id')
            ->where('i.is_active', true)
            ->whereRaw('s.available_qty > 0')
            ->whereRaw('s.available_qty <= i.minimum_qty')
            ->distinct('i.id')
            ->count('i.id');

        $outOfStock = InventoryItem::where('is_active', true)
            ->whereDoesntHave('stocks', fn($q) => $q->where('available_qty', '>', 0))
            ->count();

        $expiringSoon = StockMovement::whereNotNull('expiry_date')
            ->where('expiry_date', '>=', today())
            ->where('expiry_date', '<=', today()->addDays(90))
            ->distinct('inventory_item_id')
            ->count('inventory_item_id');

        $assetsDue = ReusableAsset::whereNotNull('retirement_threshold')
            ->whereRaw('current_usage_count >= retirement_threshold')
            ->count();

        return [
            [
                'label'   => 'Inventory Value',
                'value'   => '₹' . number_format($totalValue, 0),
                'insight' => 'Total stock at cost',
                'icon'    => 'M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6',
                'color'   => '#6a0f70',
                'bg'      => '#f9f3fa',
            ],
            [
                'label'   => 'Total Items',
                'value'   => number_format($totalItems),
                'insight' => 'Active catalogue items',
                'icon'    => 'M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z',
                'color'   => '#1a5ea8',
                'bg'      => '#e6f0fb',
            ],
            [
                'label'   => 'Low Stock',
                'value'   => $lowStock,
                'insight' => 'Below minimum threshold',
                'icon'    => 'M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01',
                'color'   => '#a05c00',
                'bg'      => '#fff4e0',
                'alert'   => $lowStock > 0,
            ],
            [
                'label'   => 'Critical / OOS',
                'value'   => $outOfStock,
                'insight' => 'Out of stock items',
                'icon'    => 'M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0zM12 9v4M12 17h.01',
                'color'   => '#b52020',
                'bg'      => '#fdeaea',
                'alert'   => $outOfStock > 0,
            ],
            [
                'label'   => 'Expiring Soon',
                'value'   => $expiringSoon,
                'insight' => 'Within 90 days',
                'icon'    => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
                'color'   => '#a05c00',
                'bg'      => '#fff4e0',
                'alert'   => $expiringSoon > 0,
            ],
            [
                'label'   => 'Assets Due',
                'value'   => $assetsDue,
                'insight' => 'Reusable — service needed',
                'icon'    => 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z',
                'color'   => '#6a0f70',
                'bg'      => '#f9f3fa',
                'alert'   => $assetsDue > 0,
            ],
        ];
    }

    /* ─────────────────────────────────────────────────────────
       STOCK STATUS (donut chart data)
    ───────────────────────────────────────────────────────── */

    private function buildStockStatus(): array
    {
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

        return [
            ['label' => 'Healthy',  'value' => $healthy,  'color' => '#1a7a45'],
            ['label' => 'Low',      'value' => $low,      'color' => '#a05c00'],
            ['label' => 'Critical', 'value' => $critical, 'color' => '#d97706'],
            ['label' => 'Out',      'value' => $out,      'color' => '#b52020'],
        ];
    }

    /* ─────────────────────────────────────────────────────────
       CATEGORY VALUES (bar chart data)
    ───────────────────────────────────────────────────────── */

    private function buildCategoryValues(): array
    {
        $data = DB::table('inventory_items as i')
            ->join('inventory_stocks as s', 'i.id', '=', 's.inventory_item_id')
            ->join('inventory_categories as c', 'i.category_id', '=', 'c.id')
            ->where('i.is_active', true)
            ->select(
                'c.name as category',
                'c.color',
                DB::raw('SUM(s.available_qty * i.average_purchase_price) as total_value')
            )
            ->groupBy('c.id', 'c.name', 'c.color')
            ->orderByDesc('total_value')
            ->limit(6)
            ->get();

        $max = $data->max('total_value') ?: 1;

        return $data->map(fn($d) => [
            'category' => $d->category,
            'value'    => $d->total_value,
            'color'    => $d->color,
            'pct'      => round(($d->total_value / $max) * 100),
            'label'    => '₹' . number_format($d->total_value, 0),
        ])->toArray();
    }

    /* ─────────────────────────────────────────────────────────
       VALUE TREND (last 8 weeks of stock_in cost)
    ───────────────────────────────────────────────────────── */

    private function buildValueTrend(): array
    {
        $weeks = [];
        for ($i = 7; $i >= 0; $i--) {
            $start = Carbon::now()->subWeeks($i)->startOfWeek();
            $end   = Carbon::now()->subWeeks($i)->endOfWeek();

            $value = DB::table('stock_movements')
                ->whereIn('movement_type', ['stock_in', 'opening_stock'])
                ->whereBetween('created_at', [$start, $end])
                ->sum('total_cost');

            $weeks[] = [
                'label' => $start->format('M d'),
                'value' => round($value, 0),
            ];
        }
        return $weeks;
    }

    /* ─────────────────────────────────────────────────────────
       CRITICAL ITEMS
    ───────────────────────────────────────────────────────── */

    private function buildCriticalItems(): \Illuminate\Support\Collection
    {
        return InventoryItem::where('is_active', true)
            ->with(['stocks', 'category'])
            ->get()
            ->filter(fn($item) => $item->total_stock <= $item->minimum_qty)
            ->sortBy('total_stock')
            ->take(8)
            ->values();
    }

    /* ─────────────────────────────────────────────────────────
       EXPIRING SOON (next 90 days)
    ───────────────────────────────────────────────────────── */

    private function buildExpiringSoon(): \Illuminate\Support\Collection
    {
        return StockMovement::with(['item', 'toLocation'])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', today())
            ->where('expiry_date', '<=', today()->addDays(90))
            ->whereIn('movement_type', ['stock_in', 'opening_stock'])
            ->orderBy('expiry_date')
            ->take(8)
            ->get();
    }

    /* ─────────────────────────────────────────────────────────
       RECENT MOVEMENTS FEED
    ───────────────────────────────────────────────────────── */

    private function buildRecentMovements(): \Illuminate\Support\Collection
    {
        return StockMovement::with(['item', 'fromLocation', 'toLocation', 'createdBy'])
            ->latest()
            ->take(12)
            ->get();
    }

    /* ─────────────────────────────────────────────────────────
       FOOTER STATS
    ───────────────────────────────────────────────────────── */

    private function buildFooterStats(): array
    {
        return [
            ['label' => 'Locations',       'value' => InventoryLocation::where('is_active', true)->count()],
            ['label' => 'Vendors',         'value' => InventoryVendor::where('is_active', true)->count()],
            ['label' => 'Pending POs',     'value' => PurchaseOrder::whereIn('status', ['draft', 'ordered', 'partially_received'])->count()],
            ['label' => 'GRN Pending',     'value' => PurchaseOrder::whereIn('status', ['ordered', 'partially_received'])->count()],
            ['label' => 'Stock Out Today', 'value' => StockMovement::where('movement_type', 'stock_out')->whereDate('created_at', today())->count()],
            ['label' => 'Reusable Assets', 'value' => ReusableAsset::count()],
        ];
    }

    /* ═══════════════════════════════════════════════════════════
       STUB PAGES — sub-section views (built in later phases)
    ═══════════════════════════════════════════════════════════ */

    public function items()
    {
        // Join items → stocks → locations to get one row per product/location combination.
        // If a product has no stock record yet it still shows via LEFT JOIN with qty 0.
        $stockRows = DB::table('inventory_items as i')
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
                'st.name as sub_type_name',
            ])
            ->orderBy('i.product_name')
            ->orderBy('l.name')
            ->get();

        return view('inventory.items', compact('stockRows'));
    }

    public function purchase(Request $request)
    {
        $query = PurchaseOrder::with(['vendor', 'items.item'])->latest();

        // Status filter from query string
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $orders  = $query->paginate(20)->withQueryString();
        $vendors = InventoryVendor::active()->get();
        // Also pass all items for the Create PO modal line-item selects
        $allItems = InventoryItem::where('is_active', true)
            ->orderBy('product_name')
            ->get(['id','product_name','purchase_unit','consumption_unit','last_purchase_price']);

        return view('inventory.purchase', compact('orders', 'vendors', 'allItems'));
    }

    /* ═══════════════════════════════════════════════════════════
       ITEMS — store + update
    ═══════════════════════════════════════════════════════════ */

    public function storeItem(Request $request)
    {
        $data = $request->validate([
            'product_name'       => 'required|string|max:255',
            'generic_name'       => 'nullable|string|max:255',
            'brand'              => 'nullable|string|max:255',
            'category_id'        => 'nullable|exists:inventory_categories,id',
            'inventory_behavior' => 'required|in:consumable,reusable,semi_reusable',
            'purchase_unit'      => 'required|string|max:40',
            'consumption_unit'   => 'required|string|max:40',
            'pieces_per_unit'    => 'required|integer|min:1',
            'minimum_qty'        => 'required|numeric|min:0',
            'minimum_order_qty'  => 'required|numeric|min:1',
            'last_purchase_price'=> 'nullable|numeric|min:0',
            'gst_rate'           => 'nullable|numeric|min:0|max:100',
            'has_expiry'         => 'boolean',
            'is_reusable'        => 'boolean',
        ]);

        // Auto-generate item code
        $data['item_code'] = 'ITEM-' . str_pad(InventoryItem::count() + 1, 4, '0', STR_PAD_LEFT);
        $data['average_purchase_price'] = $data['last_purchase_price'] ?? 0;
        $data['has_expiry']  = $request->boolean('has_expiry');
        $data['is_reusable'] = $request->boolean('is_reusable');
        $data['created_by']  = auth()->id();

        InventoryItem::create($data);

        return back()->with('success', 'Item "' . $data['product_name'] . '" added to catalogue.');
    }

    public function updateItem(Request $request, InventoryItem $item)
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

        $data['has_expiry']  = $request->boolean('has_expiry');
        $data['is_reusable'] = $request->boolean('is_reusable');

        $item->update($data);

        return back()->with('success', 'Item updated successfully.');
    }

    /* ═══════════════════════════════════════════════════════════
       STOCK IN — view + store
    ═══════════════════════════════════════════════════════════ */

    public function stockIn()
    {
        $items     = InventoryItem::where('is_active', true)->orderBy('product_name')->get();
        $locations = InventoryLocation::active()->get();
        return view('inventory.stock-in', compact('items', 'locations'));
    }

    public function storeStockIn(Request $request)
    {
        $data = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'to_location_id'    => 'required|exists:inventory_locations,id',
            'qty'               => 'required|numeric|min:0.01',
            'unit_cost'         => 'nullable|numeric|min:0',
            'batch_no'          => 'nullable|string|max:80',
            'expiry_date'       => 'nullable|date|after:today',
            'manufacturing_date'=> 'nullable|date',
            'notes'             => 'nullable|string|max:500',
        ]);

        $qty      = (float) $data['qty'];
        $unitCost = (float) ($data['unit_cost'] ?? 0);

        $movement = StockMovement::create([
            'inventory_item_id' => $data['inventory_item_id'],
            'movement_type'     => 'stock_in',
            'qty'               => $qty,
            'to_location_id'    => $data['to_location_id'],
            'unit_cost'         => $unitCost,
            'total_cost'        => round($qty * $unitCost, 2),
            'batch_no'          => $data['batch_no'] ?? null,
            'expiry_date'       => $data['expiry_date'] ?? null,
            'manufacturing_date'=> $data['manufacturing_date'] ?? null,
            'notes'             => $data['notes'] ?? null,
            'created_by'        => auth()->id(),
        ]);

        // Update item's last + average purchase price
        if ($unitCost > 0) {
            $item = InventoryItem::find($data['inventory_item_id']);
            $item->last_purchase_price    = $unitCost;
            $item->average_purchase_price = $unitCost; // simple update; weighted avg in Phase 3
            $item->save();
        }

        $item = InventoryItem::find($data['inventory_item_id']);
        return back()->with('success', 'Stock In recorded: ' . $qty . ' × ' . $item->product_name);
    }

    /* ═══════════════════════════════════════════════════════════
       STOCK OUT — view + store
    ═══════════════════════════════════════════════════════════ */

    public function stockOut()
    {
        $items     = InventoryItem::where('is_active', true)->orderBy('product_name')->get();
        $locations = InventoryLocation::active()->get();
        return view('inventory.stock-out', compact('items', 'locations'));
    }

    public function storeStockOut(Request $request)
    {
        $data = $request->validate([
            'inventory_item_id' => 'required|exists:inventory_items,id',
            'from_location_id'  => 'required|exists:inventory_locations,id',
            'qty'               => 'required|numeric|min:0.01',
            'movement_type'     => 'required|in:stock_out,treatment_usage,damaged,expired,adjustment',
            'notes'             => 'nullable|string|max:500',
        ]);

        $item = InventoryItem::find($data['inventory_item_id']);

        // Check available stock at location
        $stock = \App\Models\Inventory\InventoryStock::where('inventory_item_id', $data['inventory_item_id'])
            ->where('location_id', $data['from_location_id'])
            ->first();

        $available = $stock ? $stock->available_qty : 0;

        if ($data['qty'] > $available) {
            return back()->withErrors(['qty' => 'Insufficient stock. Available: ' . $available . ' ' . $item->consumption_unit])->withInput();
        }

        StockMovement::create([
            'inventory_item_id' => $data['inventory_item_id'],
            'movement_type'     => $data['movement_type'],
            'qty'               => -1 * abs((float) $data['qty']), // negative = leaving system
            'from_location_id'  => $data['from_location_id'],
            'unit_cost'         => $item->average_purchase_price,
            'total_cost'        => round(abs((float) $data['qty']) * $item->average_purchase_price, 2),
            'notes'             => $data['notes'] ?? null,
            'created_by'        => auth()->id(),
        ]);

        return back()->with('success', 'Stock Out recorded: ' . $data['qty'] . ' × ' . $item->product_name);
    }

    /* ═══════════════════════════════════════════════════════════
       VENDORS — view + store
    ═══════════════════════════════════════════════════════════ */

    public function vendors()
    {
        $vendors = InventoryVendor::orderBy('vendor_name')->paginate(20);
        return view('inventory.vendors', compact('vendors'));
    }

    public function storeVendor(Request $request)
    {
        $data = $request->validate([
            'vendor_name'    => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:20',
            'whatsapp'       => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:255',
            'gst_no'         => 'nullable|string|max:20',
            'address'        => 'nullable|string|max:500',
            'city'           => 'nullable|string|max:80',
            'credit_days'    => 'nullable|integer|min:0',
        ]);

        InventoryVendor::create($data);
        return back()->with('success', 'Vendor "' . $data['vendor_name'] . '" added.');
    }

    /* ═══════════════════════════════════════════════════════════
       PURCHASE ORDERS — store
    ═══════════════════════════════════════════════════════════ */

    public function storePurchaseOrder(Request $request)
    {
        $request->validate([
            'vendor_id'        => 'required|exists:inventory_vendors,id',
            'order_date'       => 'required|date',
            'expected_date'    => 'nullable|date|after_or_equal:order_date',
            'status'           => 'required|in:draft,ordered',
            'notes'            => 'nullable|string|max:1000',
            'items'            => 'required|array|min:1',
            'items.*.item_id'  => 'required|exists:inventory_items,id',
            'items.*.qty'      => 'required|numeric|min:0.01',
            'items.*.price'    => 'required|numeric|min:0',
            'items.*.gst'      => 'nullable|numeric|min:0|max:100',
        ]);

        // Calculate totals
        $subtotal  = 0;
        $gstTotal  = 0;
        foreach ($request->items as $line) {
            $lineTotal  = $line['qty'] * $line['price'];
            $lineGst    = $lineTotal * (($line['gst'] ?? 0) / 100);
            $subtotal  += $lineTotal;
            $gstTotal  += $lineGst;
        }

        $po = PurchaseOrder::create([
            'order_no'      => PurchaseOrder::generateOrderNo(),
            'vendor_id'     => $request->vendor_id,
            'order_date'    => $request->order_date,
            'expected_date' => $request->expected_date,
            'status'        => $request->status,
            'total_amount'  => $subtotal + $gstTotal,
            'gst_amount'    => $gstTotal,
            'notes'         => $request->notes,
            'created_by'    => auth()->id(),
        ]);

        foreach ($request->items as $line) {
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

        return redirect()->route('inventory.purchase')
            ->with('success', 'Purchase Order ' . $po->order_no . ' created successfully.');
    }

    /* ═══════════════════════════════════════════════════════════
       OTHER STUBS
    ═══════════════════════════════════════════════════════════ */

    public function reusableAssets()
    {
        $assets = ReusableAsset::with(['item', 'location'])->latest()->paginate(20);
        return view('inventory.reusable-assets', compact('assets'));
    }

    public function expiry(Request $request)
    {
        $filter = $request->get('filter', 'all');
        $query  = StockMovement::with(['item.category', 'toLocation'])
            ->whereNotNull('expiry_date')
            ->whereIn('movement_type', ['stock_in', 'opening_stock']);

        match($filter) {
            'expired' => $query->where('expiry_date', '<', today()),
            '7'       => $query->whereBetween('expiry_date', [today(), today()->addDays(7)]),
            '30'      => $query->whereBetween('expiry_date', [today(), today()->addDays(30)]),
            '90'      => $query->whereBetween('expiry_date', [today(), today()->addDays(90)]),
            default   => $query->where('expiry_date', '>=', today()),
        };

        $movements = $query->orderBy('expiry_date')->paginate(30)->withQueryString();
        return view('inventory.expiry', compact('movements'));
    }

    public function reports()
    {
        return view('inventory.reports');
    }

    /* ═══════════════════════════════════════════════════════════
       SETTINGS (admin-only)
    ═══════════════════════════════════════════════════════════ */

    public function settings()
    {
        // Gate: admin only
        if (auth()->user()?->role !== 'admin') {
            abort(403, 'Access denied.');
        }

        $categories = InventoryCategory::withCount('items')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $locations = InventoryLocation::orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $settings = DB::table('inventory_settings')
            ->orderBy('group')
            ->orderBy('id')
            ->get()
            ->keyBy('key');

        $subTypes = \App\Models\Inventory\InventorySubType::with('category')
            ->orderBy('name')
            ->get();

        return view('inventory.settings', compact('categories', 'locations', 'settings', 'subTypes'));
    }

    public function updateSettings(Request $request)
    {
        if (auth()->user()?->role !== 'admin') {
            abort(403);
        }

        $data = $request->validate([
            'settings'   => 'required|array',
            'settings.*' => 'nullable|string|max:255',
        ]);

        foreach ($data['settings'] as $key => $value) {
            // Checkboxes send nothing when unchecked; treat missing as 0
            DB::table('inventory_settings')
                ->where('key', $key)
                ->update(['value' => $value ?? '0', 'updated_at' => now()]);
        }

        // Handle boolean checkboxes that were NOT submitted (= unchecked)
        $boolKeys = DB::table('inventory_settings')->where('type', 'boolean')->pluck('key');
        foreach ($boolKeys as $boolKey) {
            if (!isset($data['settings'][$boolKey])) {
                DB::table('inventory_settings')
                    ->where('key', $boolKey)
                    ->update(['value' => '0', 'updated_at' => now()]);
            }
        }

        return back()->with('success', 'Settings saved successfully.');
    }

    /* ─────────────────────────────────────────────────────────
       CATEGORIES CRUD
    ───────────────────────────────────────────────────────── */

    public function storeCategory(Request $request)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'color'       => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
        ]);

        $data['slug']       = \Illuminate\Support\Str::slug($data['name']);
        $data['sort_order'] = InventoryCategory::max('sort_order') + 1;
        $data['is_active']  = true;

        InventoryCategory::create($data);
        return back()->with('success', 'Category "' . $data['name'] . '" added.');
    }

    public function updateCategory(Request $request, InventoryCategory $cat)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'color'       => 'nullable|string|max:20',
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        $data['slug']      = \Illuminate\Support\Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');
        $cat->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroyCategory(InventoryCategory $cat)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        if ($cat->items()->count() > 0) {
            return back()->withErrors(['category' => 'Cannot delete — this category has ' . $cat->items()->count() . ' item(s) assigned to it.']);
        }

        $cat->delete();
        return back()->with('success', 'Category deleted.');
    }

    /* ─────────────────────────────────────────────────────────
       LOCATIONS CRUD
    ───────────────────────────────────────────────────────── */

    public function storeLocation(Request $request)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'code'        => 'nullable|string|max:20|unique:inventory_locations,code',
            'type'        => 'required|in:main_store,operatory,sterilization,lab,implant_drawer,storage,other',
            'description' => 'nullable|string|max:255',
        ]);

        $data['sort_order'] = InventoryLocation::max('sort_order') + 1;
        $data['is_active']  = true;

        InventoryLocation::create($data);
        return back()->with('success', 'Location "' . $data['name'] . '" added.');
    }

    public function updateLocation(Request $request, InventoryLocation $loc)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'code'        => 'nullable|string|max:20|unique:inventory_locations,code,' . $loc->id,
            'type'        => 'required|in:main_store,operatory,sterilization,lab,implant_drawer,storage,other',
            'description' => 'nullable|string|max:255',
            'is_active'   => 'boolean',
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $loc->update($data);
        return back()->with('success', 'Location updated.');
    }

    public function destroyLocation(InventoryLocation $loc)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        if ($loc->stocks()->where('available_qty', '>', 0)->count() > 0) {
            return back()->withErrors(['location' => 'Cannot delete — this location has stock assigned to it.']);
        }

        $loc->update(['is_active' => false]);
        return back()->with('success', 'Location deactivated.');
    }

    /* ─────────────────────────────────────────────────────────
       VENDOR UPDATE (edit modal)
    ───────────────────────────────────────────────────────── */

    public function updateVendor(Request $request, InventoryVendor $vendor)
    {
        $data = $request->validate([
            'vendor_name'    => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone'          => 'nullable|string|max:20',
            'whatsapp'       => 'nullable|string|max:20',
            'email'          => 'nullable|email|max:255',
            'gst_no'         => 'nullable|string|max:20',
            'address'        => 'nullable|string|max:500',
            'city'           => 'nullable|string|max:80',
            'credit_days'    => 'nullable|integer|min:0',
        ]);

        $vendor->update($data);
        return back()->with('success', 'Vendor "' . $vendor->vendor_name . '" updated.');
    }

    /* ─────────────────────────────────────────────────────────
       GRN — Receive Against PO
    ───────────────────────────────────────────────────────── */

    public function receivePO(Request $request, PurchaseOrder $po)
    {
        $request->validate([
            'location_id'       => 'required|exists:inventory_locations,id',
            'received_date'     => 'required|date',
            'lines'             => 'required|array|min:1',
            'lines.*.item_id'   => 'required|exists:inventory_items,id',
            'lines.*.qty'       => 'required|numeric|min:0',
            'lines.*.unit_cost' => 'nullable|numeric|min:0',
            'lines.*.batch_no'  => 'nullable|string|max:80',
            'lines.*.expiry'    => 'nullable|date',
        ]);

        $locationId   = $request->location_id;
        $receivedDate = $request->received_date;
        $anyReceived  = false;

        foreach ($request->lines as $idx => $line) {
            $qty = (float) $line['qty'];
            if ($qty <= 0) continue;

            $anyReceived = true;
            $poItem   = $po->items()->where('inventory_item_id', $line['item_id'])->first();
            $unitCost = (float) ($line['unit_cost'] ?? $poItem?->unit_price ?? 0);

            // Record stock_in movement (uses polymorphic reference for PO traceability)
            StockMovement::create([
                'inventory_item_id' => $line['item_id'],
                'movement_type'     => 'stock_in',
                'qty'               => $qty,
                'to_location_id'    => $locationId,
                'unit_cost'         => $unitCost,
                'total_cost'        => $qty * $unitCost,
                'batch_no'          => $line['batch_no'] ?? null,
                'expiry_date'       => $line['expiry'] ?? null,
                'reference_type'    => \App\Models\Inventory\PurchaseOrder::class,
                'reference_id'      => $po->id,
                'notes'             => 'GRN against PO# ' . $po->order_no,
                'created_by'        => auth()->id(),
            ]);

            // Update qty_received on PO line
            if ($poItem) {
                $poItem->increment('qty_received', $qty);
                // Update item last purchase price
                if (!empty($line['unit_cost']) && (float)$line['unit_cost'] > 0) {
                    $invItem = InventoryItem::find($line['item_id']);
                    $invItem->last_purchase_price    = (float)$line['unit_cost'];
                    $invItem->average_purchase_price = (float)$line['unit_cost'];
                    $invItem->save();
                }
            }
        }

        if (!$anyReceived) {
            return back()->withErrors(['lines' => 'Enter at least one quantity greater than 0.']);
        }

        // Recalculate PO status
        $po->refresh();
        $allItems = $po->items;
        $allFullyReceived = $allItems->every(fn($i) => $i->qty_received >= $i->qty_ordered);
        $anyPartial       = $allItems->some(fn($i) => $i->qty_received > 0);

        $po->update([
            'status' => $allFullyReceived ? 'completed' : ($anyPartial ? 'partially_received' : 'ordered'),
        ]);

        return redirect()->route('inventory.purchase')
            ->with('success', 'GRN recorded for PO# ' . $po->order_no . '. Stock updated.');
    }

    /* ─────────────────────────────────────────────────────────
       AJAX — Stock availability check
    ───────────────────────────────────────────────────────── */

    public function stockCheck(Request $request)
    {
        $itemId     = $request->item_id;
        $locationId = $request->location_id;

        if (!$itemId) {
            return response()->json(['available' => 0, 'unit' => '']);
        }

        $query = InventoryStock::where('inventory_item_id', $itemId);
        if ($locationId) {
            $query->where('location_id', $locationId);
        }

        $available = $query->sum('available_qty');
        $item      = InventoryItem::find($itemId);

        return response()->json([
            'available' => (float) $available,
            'unit'      => $item?->consumption_unit ?? '',
            'minimum'   => (float) ($item?->minimum_qty ?? 0),
        ]);
    }

    /* ═══════════════════════════════════════════════════════════
       IMPLANT REGISTRY
    ═══════════════════════════════════════════════════════════ */

    public function implants(Request $request)
    {
        $tab = $request->get('tab', 'catalog');

        $catalog = ImplantCatalog::withCount('placements')
            ->orderBy('brand')
            ->orderBy('system')
            ->orderBy('component_type')
            ->paginate(30)->withQueryString();

        $placements = ImplantPlacement::with(['patient', 'catalogItem', 'surgeon'])
            ->latest('surgery_date')
            ->paginate(30)->withQueryString();

        $brands = ImplantCatalog::distinct('brand')->pluck('brand');
        $types  = ['fixture','abutment','healing_abutment','analogue','scan_body','coping','graft','other'];

        // Load all patients for the placement form dropdown
        $patients = \App\Models\Patient::orderBy('name')
            ->select('id', 'name', 'phone')
            ->get();

        return view('inventory.implants', compact('catalog', 'placements', 'brands', 'types', 'tab', 'patients'));
    }

    public function storeCatalogItem(Request $request)
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

        // Handle photo upload
        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store('implants/catalog', 'public');
            $data['photo_path'] = $path;
        }

        $data['created_by'] = auth()->id();
        unset($data['photo']);

        ImplantCatalog::create($data);
        return back()->with('success', 'Implant component added to catalog.');
    }

    public function updateCatalogItem(Request $request, ImplantCatalog $catalogItem)
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

        if ($request->hasFile('photo')) {
            // Delete old photo
            if ($catalogItem->photo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($catalogItem->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('implants/catalog', 'public');
        }

        $data['is_active'] = $request->boolean('is_active');
        unset($data['photo']);

        $catalogItem->update($data);
        return back()->with('success', 'Component updated.');
    }

    public function storePlacement(Request $request)
    {
        $data = $request->validate([
            'patient_id'              => 'required|exists:patients,id',
            'treatment_visit_id'      => 'nullable|exists:treatment_visits,id',
            'implant_catalog_id'      => 'nullable|exists:implant_catalog,id',
            'surgeon_id'              => 'nullable|exists:users,id',
            'lot_number'              => 'nullable|string|max:100',
            'serial_number'           => 'nullable|string|max:100',
            'tooth_position'          => 'nullable|string|max:30',
            'surgery_date'            => 'required|date',
            'implant_brand_freetext'  => 'nullable|string|max:150',
            'implant_code_freetext'   => 'nullable|string|max:150',
            'status'                  => 'required|in:placed,osseointegrating,loaded,failed,explanted',
            'notes'                   => 'nullable|string|max:1000',
            'label_photo'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        if ($request->hasFile('label_photo')) {
            $data['label_photo_path'] = $request->file('label_photo')
                ->store('implants/labels', 'public');
        }

        $data['created_by'] = auth()->id();
        unset($data['label_photo']);

        ImplantPlacement::create($data);
        return back()->with('success', 'Implant placement recorded successfully.');
    }

    public function updatePlacement(Request $request, ImplantPlacement $placement)
    {
        $data = $request->validate([
            'status'                 => 'required|in:placed,osseointegrating,loaded,failed,explanted',
            'lot_number'             => 'nullable|string|max:100',
            'serial_number'          => 'nullable|string|max:100',
            'tooth_position'         => 'nullable|string|max:30',
            'surgery_date'           => 'nullable|date',
            'notes'                  => 'nullable|string|max:1000',
            'label_photo'            => 'nullable|image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        if ($request->hasFile('label_photo')) {
            if ($placement->label_photo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($placement->label_photo_path);
            }
            $data['label_photo_path'] = $request->file('label_photo')
                ->store('implants/labels', 'public');
        }

        unset($data['label_photo']);
        $placement->update($data);

        return back()->with('success', 'Placement updated.');
    }

    /* ═══════════════════════════════════════════════════════════
       PRODUCT MASTER
    ═══════════════════════════════════════════════════════════ */

    public function products(Request $request)
    {
        $search   = $request->get('q');
        $catId    = $request->get('category_id');

        $query = InventoryItem::with(['category', 'subType', 'dealers'])
            ->withSum('stocks as total_qty', 'available_qty');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                  ->orWhere('generic_name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%");
            });
        }
        if ($catId) {
            $query->where('category_id', $catId);
        }

        $products   = $query->orderBy('product_name')->paginate(25)->withQueryString();
        $categories = \App\Models\Inventory\InventoryCategory::orderBy('name')->get();
        $subTypes   = \App\Models\Inventory\InventorySubType::with('category')->orderBy('name')->get();
        $vendors    = InventoryVendor::orderBy('vendor_name')->get(); // column is vendor_name, not name
        $locations  = InventoryLocation::orderBy('name')->get();

        return view('inventory.products', compact(
            'products', 'categories', 'subTypes', 'vendors', 'locations', 'search', 'catId'
        ));
    }

    public function storeProduct(Request $request)
    {
        $data = $request->validate([
            'product_name'         => 'required|string|max:200',
            'generic_name'         => 'nullable|string|max:200',
            'category_id'          => 'required|exists:inventory_categories,id',
            'sub_type_id'          => 'nullable|exists:inventory_sub_types,id',
            'usage_type'           => 'required|in:single_use,multiple_use',
            'packaging_type'       => 'nullable|string|max:60',
            'qty_in_packaging'     => 'nullable|numeric|min:0',
            'packaging_unit_label' => 'nullable|string|max:20',
            'pack_size_label'      => 'nullable|string|max:80',
            'shelf_life_months'    => 'nullable|integer|min:0',
            'company_name'         => 'nullable|string|max:100',
            'brand'                => 'nullable|string|max:100',
            'preferred_brand'      => 'nullable|string|max:100',
            'last_purchase_price'  => 'nullable|numeric|min:0',
            'mrp'                  => 'nullable|numeric|min:0',
            'last_purchase_date'   => 'nullable|date',
            'minimum_qty'          => 'nullable|numeric|min:0',
            'reorder_level'        => 'nullable|numeric|min:0',
            'treatment_tags'       => 'nullable|array',
            'treatment_tags.*'     => 'string|max:80',
            'product_notes'        => 'nullable|string|max:1000',
            'is_active'            => 'boolean',
            'photo'                => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'alternative_brands'   => 'nullable|array',
            'alternative_brands.*' => 'string|max:100',
            'primary_vendor_id'    => 'nullable|exists:inventory_vendors,id',
            'alternate_vendor_ids' => 'nullable|array',
            'alternate_vendor_ids.*' => 'exists:inventory_vendors,id',
        ]);

        // Photo upload
        if ($request->hasFile('photo')) {
            $data['image'] = $request->file('photo')->store('inventory/products', 'public');
        }
        unset($data['photo']);

        // Auto-generate item code
        $data['item_code'] = 'PRD-' . strtoupper(substr(md5(uniqid()), 0, 6));
        $data['created_by'] = auth()->id();
        $data['is_active']  = $request->boolean('is_active', true);

        // Extract dealer data before create
        $primaryVendorId    = $data['primary_vendor_id'] ?? null;
        $alternateVendorIds = $data['alternate_vendor_ids'] ?? [];
        unset($data['primary_vendor_id'], $data['alternate_vendor_ids']);

        $item = InventoryItem::create($data);

        // Attach dealers
        if ($primaryVendorId) {
            $item->dealers()->attach($primaryVendorId, ['is_primary' => true, 'is_alternate' => false]);
        }
        foreach ($alternateVendorIds as $vid) {
            if ($vid != $primaryVendorId) {
                $item->dealers()->attach($vid, ['is_primary' => false, 'is_alternate' => true]);
            }
        }

        return redirect()->route('inventory.products')->with('success', "Product '{$item->product_name}' added to master list.");
    }

    public function updateProduct(Request $request, InventoryItem $item)
    {
        $data = $request->validate([
            'product_name'         => 'required|string|max:200',
            'generic_name'         => 'nullable|string|max:200',
            'category_id'          => 'required|exists:inventory_categories,id',
            'sub_type_id'          => 'nullable|exists:inventory_sub_types,id',
            'usage_type'           => 'required|in:single_use,multiple_use',
            'packaging_type'       => 'nullable|string|max:60',
            'qty_in_packaging'     => 'nullable|numeric|min:0',
            'packaging_unit_label' => 'nullable|string|max:20',
            'pack_size_label'      => 'nullable|string|max:80',
            'shelf_life_months'    => 'nullable|integer|min:0',
            'company_name'         => 'nullable|string|max:100',
            'brand'                => 'nullable|string|max:100',
            'preferred_brand'      => 'nullable|string|max:100',
            'last_purchase_price'  => 'nullable|numeric|min:0',
            'mrp'                  => 'nullable|numeric|min:0',
            'last_purchase_date'   => 'nullable|date',
            'minimum_qty'          => 'nullable|numeric|min:0',
            'reorder_level'        => 'nullable|numeric|min:0',
            'treatment_tags'       => 'nullable|array',
            'treatment_tags.*'     => 'string|max:80',
            'product_notes'        => 'nullable|string|max:1000',
            'is_active'            => 'boolean',
            'photo'                => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'alternative_brands'   => 'nullable|array',
            'alternative_brands.*' => 'string|max:100',
            'primary_vendor_id'    => 'nullable|exists:inventory_vendors,id',
            'alternate_vendor_ids' => 'nullable|array',
            'alternate_vendor_ids.*' => 'exists:inventory_vendors,id',
        ]);

        if ($request->hasFile('photo')) {
            $data['image'] = $request->file('photo')->store('inventory/products', 'public');
        }
        unset($data['photo']);
        $data['is_active'] = $request->boolean('is_active', true);

        $primaryVendorId    = $data['primary_vendor_id'] ?? null;
        $alternateVendorIds = $data['alternate_vendor_ids'] ?? [];
        unset($data['primary_vendor_id'], $data['alternate_vendor_ids']);

        $item->update($data);

        // Re-sync dealers
        $item->dealers()->detach();
        if ($primaryVendorId) {
            $item->dealers()->attach($primaryVendorId, ['is_primary' => true, 'is_alternate' => false]);
        }
        foreach ($alternateVendorIds as $vid) {
            if ($vid != $primaryVendorId) {
                $item->dealers()->attach($vid, ['is_primary' => false, 'is_alternate' => true]);
            }
        }

        return redirect()->route('inventory.products')->with('success', "Product '{$item->product_name}' updated.");
    }

    public function destroyProduct(InventoryItem $item)
    {
        $item->update(['is_active' => false]);
        return back()->with('success', "Product '{$item->product_name}' deactivated.");
    }

    /* ═══════════════════════════════════════════════════════════
       STOCK VIEW — Quick +/- Adjust
    ═══════════════════════════════════════════════════════════ */

    public function adjustStock(Request $request, InventoryItem $item)
    {
        $data = $request->validate([
            'type'        => 'required|in:add,remove',
            'qty'         => 'required|numeric|min:0.01',
            'location_id' => 'required|exists:inventory_locations,id',
            'note'        => 'nullable|string|max:255',
        ]);

        $qty = (float) $data['qty'];

        // For "remove": check we have enough stock before proceeding
        if ($data['type'] === 'remove') {
            $stock = InventoryStock::where('inventory_item_id', $item->id)
                ->where('location_id', $data['location_id'])
                ->first();

            if (!$stock || $stock->available_qty < $qty) {
                return back()->withErrors(['qty' => 'Cannot remove more than available stock (' . ($stock->available_qty ?? 0) . ').']);
            }
        }

        // Creating a StockMovement automatically updates inventory_stocks
        // via the model's booted() → updateLiveStock() hook.
        // Do NOT manually increment/decrement here — that would double-count.
        StockMovement::create([
            'inventory_item_id' => $item->id,
            'to_location_id'    => $data['type'] === 'add'    ? $data['location_id'] : null,
            'from_location_id'  => $data['type'] === 'remove' ? $data['location_id'] : null,
            'movement_type'     => $data['type'] === 'add' ? 'stock_in' : 'stock_out',
            'qty'               => $qty,
            'notes'             => $data['note'] ?? 'Manual adjustment',
            'reference_type'    => 'manual_adjustment',
            'reference_id'      => null,
            'created_by'        => auth()->id(),
        ]);

        return back()->with('success', 'Stock updated.');
    }

    /* ═══════════════════════════════════════════════════════════
       SUB-TYPES (Settings)
    ═══════════════════════════════════════════════════════════ */

    public function storeSubType(Request $request)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        $data = $request->validate([
            'category_id' => 'required|exists:inventory_categories,id',
            'name'        => 'required|string|max:100',
        ]);

        \App\Models\Inventory\InventorySubType::create($data);
        return back()->with('success', "Sub-type '{$data['name']}' added.");
    }

    public function updateSubType(Request $request, \App\Models\Inventory\InventorySubType $st)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        $data = $request->validate([
            'name'      => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        $st->update($data);

        return back()->with('success', 'Sub-type updated.');
    }

    public function destroySubType(\App\Models\Inventory\InventorySubType $st)
    {
        if (auth()->user()?->role !== 'admin') abort(403);
        $st->delete();
        return back()->with('success', 'Sub-type deleted.');
    }
}
