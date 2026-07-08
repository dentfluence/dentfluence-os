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
use App\Models\Finance\FinanceExpense;
use App\Models\Finance\FinanceExpenseCategory;
use App\Models\AppSetting;
use App\Models\Procurement\GoodsReceiptNote;
use Illuminate\Support\Str;
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

        // Phase 2 additions
        $healthScore     = $this->buildHealthScore();
        $extraKpis       = $this->buildExtraKpis();
        $actionItems     = $this->buildAssistantActionItems();

        return view('inventory.dashboard', compact(
            'kpis', 'stockStatus', 'categoryValues', 'valueTrend',
            'criticalItems', 'expiringSoon', 'recentMovements', 'footerStats',
            'healthScore', 'extraKpis', 'actionItems'
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

    /* ─────────────────────────────────────────────────────────
       HEALTH SCORE (Phase 2)
       Composite 0–100 score from availability, low stock,
       critical expiry, and dead stock penalties.
    ───────────────────────────────────────────────────────── */

    private function buildHealthScore(): array
    {
        $totalActive = InventoryItem::where('is_active', true)->count() ?: 1;

        // Out-of-stock count
        $oos = InventoryItem::where('is_active', true)
            ->whereDoesntHave('stocks', fn($q) => $q->where('available_qty', '>', 0))
            ->count();

        // Low-stock count (qty > 0 but <= minimum_qty)
        $low = DB::table('inventory_items as i')
            ->join('inventory_stocks as s', 'i.id', '=', 's.inventory_item_id')
            ->where('i.is_active', true)
            ->whereRaw('s.available_qty > 0')
            ->whereRaw('s.available_qty <= i.minimum_qty')
            ->distinct('i.id')
            ->count('i.id');

        // Critically expiring (<=30 days) distinct items
        $critExpiry = StockMovement::whereNotNull('expiry_date')
            ->where('expiry_date', '>=', today())
            ->where('expiry_date', '<=', today()->addDays(30))
            ->where('qty', '>', 0)
            ->distinct('inventory_item_id')
            ->count('inventory_item_id');

        // Dead stock count (no movement in 90 days, qty > 0)
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

        // Calculate penalties (max possible = 100)
        $score = 100;
        $score -= min(35, (int) round(($oos / $totalActive) * 100 * 0.35));   // OOS up to -35
        $score -= min(25, (int) round(($low / $totalActive) * 100 * 0.25));   // Low up to -25
        $score -= min(20, (int) round(($critExpiry / $totalActive) * 100 * 0.20)); // Expiry up to -20
        $score -= min(10, (int) round(($deadCount / $totalActive) * 100 * 0.10)); // Dead up to -10
        $score  = max(0, $score);

        // Grade
        if ($score >= 90) {
            $grade = 'Excellent'; $color = '#15803d'; $bg = '#f0fdf4'; $border = '#86efac';
        } elseif ($score >= 70) {
            $grade = 'Good';      $color = '#2563eb'; $bg = '#eff6ff'; $border = '#93c5fd';
        } elseif ($score >= 50) {
            $grade = 'Needs Attention'; $color = '#d97706'; $bg = '#fffbeb'; $border = '#fcd34d';
        } else {
            $grade = 'Critical'; $color = '#dc2626'; $bg = '#fef2f2'; $border = '#fca5a5';
        }

        return [
            'score'        => $score,
            'grade'        => $grade,
            'color'        => $color,
            'bg'           => $bg,
            'border'       => $border,
            'penalties'    => [
                'oos'     => $oos,
                'low'     => $low,
                'expiry'  => $critExpiry,
                'dead'    => $deadCount,
            ],
            'total_active' => $totalActive,
        ];
    }

    /* ─────────────────────────────────────────────────────────
       EXTRA KPI CARDS (Phase 2)
       Days Until Stockout · Dead Stock Value · Monthly Wastage ·
       Today's Deliveries · Implant Stock Health
    ───────────────────────────────────────────────────────── */

    private function buildExtraKpis(): array
    {
        // Days Until Stockout — item running out soonest (with consumption history)
        $soonestDays = null;
        $soonestItem = null;

        $stockedItems = DB::table('inventory_items as i')
            ->join('inventory_stocks as s', 'i.id', '=', 's.inventory_item_id')
            ->where('i.is_active', true)
            ->where('s.available_qty', '>', 0)
            ->select('i.id', 'i.product_name', DB::raw('SUM(s.available_qty) as qty'))
            ->groupBy('i.id', 'i.product_name')
            ->get();

        foreach ($stockedItems as $item) {
            $consumed30 = DB::table('stock_movements')
                ->where('inventory_item_id', $item->id)
                ->whereIn('movement_type', ['stock_out', 'treatment_usage'])
                ->where('created_at', '>=', now()->subDays(30))
                ->sum(DB::raw('ABS(qty)'));

            if ($consumed30 <= 0) continue; // skip items with no recent consumption

            $dailyRate = $consumed30 / 30;
            $days = (int) floor($item->qty / $dailyRate);

            if ($soonestDays === null || $days < $soonestDays) {
                $soonestDays = $days;
                $soonestItem = $item->product_name;
            }
        }

        // Dead stock value
        $deadValue = DB::table('inventory_items as i')
            ->join('inventory_stocks as s', 'i.id', '=', 's.inventory_item_id')
            ->where('i.is_active', true)
            ->where('s.available_qty', '>', 0)
            ->whereNotExists(function ($q) {
                $q->from('stock_movements')
                    ->whereColumn('stock_movements.inventory_item_id', 'i.id')
                    ->where('stock_movements.created_at', '>=', now()->subDays(90));
            })
            ->sum(DB::raw('s.available_qty * i.average_purchase_price'));

        // Monthly wastage (expired + damaged this calendar month)
        $wastage = DB::table('stock_movements')
            ->whereIn('movement_type', ['expired', 'damaged'])
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum(DB::raw('ABS(total_cost)'));

        // Today's deliveries (POs expected today)
        $todayDeliveries = PurchaseOrder::whereIn('status', ['ordered', 'partially_received'])
            ->whereDate('expected_date', today())
            ->count();

        // Implant stock health (catalog size — placements tracked separately)
        $implantLow   = 0; // ImplantCatalog has no per-item stock columns; use placements view for detail
        $implantTotal = \App\Models\Inventory\ImplantCatalog::where('is_active', true)->count();

        return [
            [
                'label'   => 'Days Until Stock-out',
                'value'   => $soonestDays !== null ? $soonestDays . 'd' : '—',
                'insight' => $soonestItem ? Str::limit($soonestItem, 22) : 'No consumption data',
                'icon'    => 'M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
                'color'   => $soonestDays !== null && $soonestDays <= 7 ? '#dc2626' : ($soonestDays <= 14 ? '#d97706' : '#2563eb'),
                'bg'      => $soonestDays !== null && $soonestDays <= 7 ? '#fef2f2' : ($soonestDays <= 14 ? '#fffbeb' : '#eff6ff'),
                'alert'   => $soonestDays !== null && $soonestDays <= 7,
            ],
            [
                'label'   => 'Dead Stock Value',
                'value'   => $deadValue > 0 ? '₹' . number_format($deadValue, 0) : '₹0',
                'insight' => 'No movement in 90+ days',
                'icon'    => 'M23 6L13.5 15.5 8.5 10.5 1 18M17 6h6v6',
                'color'   => $deadValue > 0 ? '#6b7280' : '#15803d',
                'bg'      => $deadValue > 0 ? '#f3f4f6' : '#f0fdf4',
                'alert'   => false,
            ],
            [
                'label'   => 'Monthly Wastage',
                'value'   => $wastage > 0 ? '₹' . number_format($wastage, 0) : '₹0',
                'insight' => 'Expired + damaged this month',
                'icon'    => 'M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16',
                'color'   => $wastage > 0 ? '#b52020' : '#15803d',
                'bg'      => $wastage > 0 ? '#fef2f2' : '#f0fdf4',
                'alert'   => $wastage > 0,
            ],
            [
                'label'   => "Today's Deliveries",
                'value'   => $todayDeliveries,
                'insight' => 'Orders expected today',
                'icon'    => 'M5 8h14M5 8a2 2 0 1 0-4 0v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8m-14 0V6a2 2 0 1 1 4 0v2',
                'color'   => $todayDeliveries > 0 ? '#2563eb' : '#6b7280',
                'bg'      => $todayDeliveries > 0 ? '#eff6ff' : '#f9fafb',
                'alert'   => false,
            ],
            [
                'label'   => 'Implant Stock Health',
                'value'   => $implantTotal > 0 ? ($implantTotal - $implantLow) . '/' . $implantTotal : '—',
                'insight' => $implantLow > 0 ? $implantLow . ' running low' : 'All implants stocked',
                'icon'    => 'M12 2a5 5 0 0 1 5 5c0 5-5 11-5 11S7 12 7 7a5 5 0 0 1 5-5z',
                'color'   => $implantLow > 0 ? '#d97706' : '#15803d',
                'bg'      => $implantLow > 0 ? '#fffbeb' : '#f0fdf4',
                'alert'   => $implantLow > 0,
            ],
        ];
    }

    /* ─────────────────────────────────────────────────────────
       ASSISTANT ACTION ITEMS (Phase 2)
       Simple "what to do today" list for assistants/front desk
    ───────────────────────────────────────────────────────── */

    private function buildAssistantActionItems(): array
    {
        $actions = [];

        // Items to receive today
        $todayPOs = PurchaseOrder::with('vendor')
            ->whereIn('status', ['ordered', 'partially_received'])
            ->whereDate('expected_date', today())
            ->get();

        foreach ($todayPOs as $po) {
            $actions[] = [
                'priority' => 'high',
                'icon'     => 'M5 8h14M5 8a2 2 0 1 0-4 0v10a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8',
                'text'     => 'Receive delivery from ' . ($po->vendor?->vendor_name ?? 'vendor'),
                'sub'      => 'Order #' . ($po->order_no ?? $po->id) . ' expected today',
                'href'     => route('inventory.purchase'),
                'action'   => 'Receive',
            ];
        }

        // Items expiring in ≤14 days — use first
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
            $actions[] = [
                'priority' => 'medium',
                'icon'     => 'M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z',
                'text'     => 'Use ' . ($exp->item?->product_name ?? 'item') . ' before expiry',
                'sub'      => 'Expires in ' . $days . ' day' . ($days === 1 ? '' : 's'),
                'href'     => route('inventory.stock-out'),
                'action'   => 'Use Item',
            ];
        }

        // Critical OOS items
        $oosItems = InventoryItem::where('is_active', true)
            ->whereDoesntHave('stocks', fn($q) => $q->where('available_qty', '>', 0))
            ->take(3)
            ->get();

        foreach ($oosItems as $item) {
            $actions[] = [
                'priority' => 'high',
                'icon'     => 'M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z',
                'text'     => $item->product_name . ' is out of stock',
                'sub'      => 'Notify manager to reorder',
                'href'     => route('inventory.alerts'),
                'action'   => 'View Alert',
            ];
        }

        return $actions;
    }

    /* ═══════════════════════════════════════════════════════════
       PRODUCT MASTER — card-based inventory catalogue
    ═══════════════════════════════════════════════════════════ */

    public function products(Request $request)
    {
        $search      = trim($request->get('q', ''));
        $catId       = $request->get('category_id');
        $subTypeId   = $request->get('sub_type_id');
        $brandFilter = $request->get('brand');
        $locationId  = $request->get('location_id');
        $stockLevel  = $request->get('stock_level');
        $perPage     = (int) $request->get('per_page', 25);
        $perPage     = in_array($perPage, [25, 50, 100]) ? $perPage : 25;

        // ── Main query ──
        $query = InventoryItem::with(['stocks.location', 'category', 'subType', 'variant'])
            ->withSum('stocks', 'available_qty')
            ->where('is_active', true);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('product_name', 'like', "%{$search}%")
                  ->orWhere('generic_name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%")
                  ->orWhere('item_code', 'like', "%{$search}%");
            });
        }
        if ($catId)       $query->where('category_id', $catId);
        if ($subTypeId)   $query->where('sub_type_id', $subTypeId);
        if ($brandFilter) $query->where('brand', $brandFilter);
        if ($locationId) {
            $query->whereHas('stocks', fn($q) => $q->where('location_id', $locationId)->where('available_qty', '>', 0));
        }
        if ($stockLevel === 'out') {
            $query->where(function ($q) {
                $q->doesntHave('stocks')
                  ->orWhereHas('stocks', fn($sq) => $sq->havingRaw('SUM(available_qty) <= 0'));
            });
        } elseif ($stockLevel === 'critical') {
            $query->whereHas('stocks', fn($q) => $q->havingRaw('SUM(available_qty) <= 0'));
        } elseif ($stockLevel === 'low') {
            $query->whereHas('stocks', fn($q) => $q->havingRaw('SUM(available_qty) > 0'))
                  ->whereRaw('(SELECT SUM(available_qty) FROM inventory_stocks WHERE inventory_item_id = inventory_items.id) <= inventory_items.minimum_qty');
        } elseif ($stockLevel === 'healthy') {
            $query->whereRaw('(SELECT SUM(available_qty) FROM inventory_stocks WHERE inventory_item_id = inventory_items.id) > inventory_items.minimum_qty * 2');
        }

        $products = $query->orderBy('product_name')->paginate($perPage)->withQueryString();

        // Alias stocks_sum_available_qty → total_qty so the existing table view continues working
        $products->getCollection()->transform(function ($item) {
            $item->total_qty = $item->stocks_sum_available_qty ?? 0;
            return $item;
        });

        // ── Filter sidebar data ──
        $categories = InventoryCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $subTypes   = \App\Models\Inventory\InventorySubType::orderBy('name')->get(['id', 'name', 'category_id']);
        $variants   = \App\Models\Inventory\InventoryVariant::orderBy('name')->get(['id', 'name', 'sub_type_id']);
        $brands     = InventoryItem::where('is_active', true)->whereNotNull('brand')
                        ->where('brand', '!=', '')->distinct()->orderBy('brand')->pluck('brand');
        $locations  = InventoryLocation::active()->orderBy('name')->get(['id', 'name']);
        $vendors    = InventoryVendor::active()->get(['id', 'vendor_name']);

        return view('inventory.products', compact(
            'products', 'search', 'catId', 'subTypeId', 'brandFilter',
            'locationId', 'stockLevel', 'perPage',
            'categories', 'subTypes', 'variants', 'brands', 'locations', 'vendors'
        ));
    }

    public function showProduct(InventoryItem $item)
    {
        // Full 360 view: stock by location, expiry batches, movement history, purchase history
        $item->load(['stocks.location', 'category', 'subType', 'variant', 'dealers']);

        // Stock movements (latest 50)
        $movements = StockMovement::where('inventory_item_id', $item->id)
            ->with(['fromLocation', 'toLocation'])
            ->latest()
            ->take(50)
            ->get();

        // Monthly consumption (last 6 months) for trend chart
        $monthlyConsumption = DB::table('stock_movements')
            ->select(DB::raw("DATE_FORMAT(created_at,'%Y-%m') as month"), DB::raw('SUM(ABS(qty)) as qty'))
            ->where('inventory_item_id', $item->id)
            ->whereIn('movement_type', ['stock_out', 'treatment_usage'])
            ->where('created_at', '>=', now()->subMonths(6)->startOfMonth())
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Avg monthly consumption (last 90 days)
        $consumed90 = StockMovement::where('inventory_item_id', $item->id)
            ->whereIn('movement_type', ['stock_out', 'treatment_usage'])
            ->where('created_at', '>=', now()->subDays(90))
            ->sum(DB::raw('ABS(qty)'));
        $avgMonthlyConsumption = round($consumed90 / 3, 1);

        // Days until stockout
        $totalStock = $item->stocks->sum('available_qty');
        $daysUntilStockout = $avgMonthlyConsumption > 0
            ? (int) round(($totalStock / $avgMonthlyConsumption) * 30)
            : null;

        // Expiry batches (stock_in movements with expiry dates, grouped by date+location)
        $expiryBatches = DB::table('stock_movements as sm')
            ->leftJoin('inventory_locations as l', 'l.id', '=', 'sm.to_location_id')
            ->select('sm.expiry_date', 'sm.to_location_id', 'l.name as location_name',
                     DB::raw('SUM(ABS(sm.qty)) as qty'))
            ->where('sm.inventory_item_id', $item->id)
            ->where('sm.movement_type', 'stock_in')
            ->whereNotNull('sm.expiry_date')
            ->groupBy('sm.expiry_date', 'sm.to_location_id', 'l.name')
            ->orderBy('sm.expiry_date')
            ->get();

        // Purchase history (last 10 GRN lines for this item)
        $purchaseHistory = DB::table('grn_items as gi')
            ->join('goods_receipt_notes as grn', 'grn.id', '=', 'gi.grn_id')
            ->leftJoin('inventory_vendors as v', 'v.id', '=', 'grn.vendor_id')
            ->select(
                'grn.received_date', 'v.vendor_name',
                'gi.qty_received', 'gi.unit_price', 'gi.batch_no', 'gi.expiry_date'
            )
            ->where('gi.inventory_item_id', $item->id)
            ->orderByDesc('grn.received_date')
            ->limit(10)
            ->get();

        return view('inventory.product-detail', compact(
            'item', 'movements', 'monthlyConsumption',
            'avgMonthlyConsumption', 'daysUntilStockout',
            'expiryBatches', 'purchaseHistory', 'totalStock'
        ));
    }

    public function storeProduct(Request $request)
    {
        // Delegates to existing storeItem logic (same validation + creation)
        return $this->storeItem($request);
    }

    public function updateProduct(Request $request, InventoryItem $item)
    {
        // Delegates to existing updateItem logic
        return $this->updateItem($request, $item);
    }

    public function destroyProduct(InventoryItem $item)
    {
        // Soft-disable: mark inactive rather than hard-delete to preserve movement history
        $name = $item->product_name;
        $item->update(['is_active' => false]);
        return back()->with('success', '"' . $name . '" removed from catalogue.');
    }

    /* ═══════════════════════════════════════════════════════════
       STUB PAGES — sub-section views (built in later phases)
    ═══════════════════════════════════════════════════════════ */

    public function items(Request $request)
    {
        // ── Filters & sort from query string ──
        $categoryId = $request->get('category_id');
        $locationId = $request->get('location_id');
        $sort       = $request->get('sort', 'product_name');   // product_name | location_name | available_qty
        $dir        = $request->get('dir', 'asc') === 'desc' ? 'desc' : 'asc';

        // Only allow safe sort columns
        $allowedSorts = ['product_name', 'location_name', 'available_qty'];
        if (!in_array($sort, $allowedSorts)) {
            $sort = 'product_name';
        }

        // Join items → stocks → locations to get one row per product/location combination.
        // If a product has no stock record yet it still shows via LEFT JOIN with qty 0.
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

        // Apply filters
        if ($categoryId) {
            $query->where('i.category_id', $categoryId);
        }
        if ($locationId) {
            $query->where('s.location_id', $locationId);
        }

        // Apply sort — secondary always product_name for stability
        if ($sort === 'location_name') {
            $query->orderBy('l.name', $dir)->orderBy('i.product_name', 'asc');
        } elseif ($sort === 'available_qty') {
            $query->orderBy('s.available_qty', $dir)->orderBy('i.product_name', 'asc');
        } else {
            $query->orderBy('i.product_name', $dir)->orderBy('l.name', 'asc');
        }

        $stockRows  = $query->get();
        $categories = InventoryCategory::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $locations  = InventoryLocation::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return view('inventory.items', compact('stockRows', 'categories', 'locations', 'sort', 'dir', 'categoryId', 'locationId'));
    }

    public function purchase(Request $request)
    {
        $query = PurchaseOrder::with(['vendor', 'items.item'])->latest();

        // Status filter from query string
        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $orders  = $query->paginate(20)->withQueryString();
        $vendors = InventoryVendor::active()->get(['id','vendor_name','contact_person','phone','whatsapp','email']);

        // All items for the "show all" override toggle in Create PO modal
        // Eager-load stocks so total_stock attribute works without N+1 queries
        $allItems = InventoryItem::where('is_active', true)
            ->with('stocks')
            ->orderBy('product_name')
            ->get(['id','product_name','purchase_unit','consumption_unit','last_purchase_price','minimum_qty','reorder_level']);

        // Low/critical items: at or below reorder_level (or minimum_qty as fallback)
        // Only include items that actually have a threshold set (> 0)
        $lowStockItemIds = $allItems->filter(function ($item) {
            $threshold = ($item->reorder_level > 0) ? $item->reorder_level : $item->minimum_qty;
            if ($threshold <= 0) return false; // no minimum configured — exclude
            return $item->total_stock <= $threshold;
        })->pluck('id')->all();

        $isAdmin           = auth()->user()?->isAdmin();
        $grnWindowHours    = (int) AppSetting::get('grn_correction_window_hours', 0);

        return view('inventory.purchase', compact('orders', 'vendors', 'allItems', 'lowStockItemIds', 'isAdmin', 'grnWindowHours'));
    }

    /* ═══════════════════════════════════════════════════════════
       ITEMS — store + update
    ═══════════════════════════════════════════════════════════ */

    /**
     * Shared validation for the Add/Edit Product modal (products.blade.php).
     * The modal collects packaging-centric fields (packaging_type,
     * qty_in_packaging, packaging_unit_label, usage_type, ...) rather than
     * the legacy purchase_unit/consumption_unit/inventory_behavior/
     * pieces_per_unit/minimum_order_qty columns. Those legacy columns are
     * still read by Purchase, Stock-In, Reports and Alerts, so they're kept
     * and derived from the newer fields below instead of being requested
     * twice from the user.
     */
    private function validateProductForm(Request $request): array
    {
        return $request->validate([
            'product_name'           => 'required|string|max:255',
            'generic_name'           => 'nullable|string|max:255',
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
            'gst_rate'               => 'nullable|numeric|min:0|max:100',
            'primary_location_id'    => 'nullable|exists:inventory_locations,id',
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

    /**
     * Fold packaging fields into the legacy unit/behavior columns, pull out
     * the fields that don't map 1:1 to an inventory_items column, and handle
     * the photo upload. Returns [$data, $primaryLocationId, $primaryVendorId,
     * $alternateVendorIds].
     */
    private function prepareProductData(Request $request, array $data): array
    {
        $primaryLocationId  = $data['primary_location_id'] ?? null;
        $primaryVendorId    = $data['primary_vendor_id'] ?? null;
        $alternateVendorIds = $data['alternate_vendor_ids'] ?? [];
        unset($data['primary_location_id'], $data['primary_vendor_id'], $data['alternate_vendor_ids']);

        if ($request->hasFile('photo')) {
            $data['image'] = $request->file('photo')->store('inventory/products', 'public');
        }
        unset($data['photo']);

        $data['usage_type']         = $data['usage_type'] ?? 'multiple_use';
        $data['inventory_behavior'] = $data['usage_type'] === 'single_use' ? 'consumable' : 'reusable';
        $data['purchase_unit']      = $data['packaging_type'];
        $data['consumption_unit']   = $data['packaging_unit_label'];
        $data['pieces_per_unit']    = max(1, (int) round($data['qty_in_packaging']));
        $data['minimum_order_qty']  = 1; // not collected by this form yet — matches mobile API default
        $data['is_reusable']        = $data['usage_type'] !== 'single_use';
        // These columns are NOT NULL with a DB default of 0, but their form
        // fields are optional — an empty field submits as null, which the DB
        // rejects outright (same class of bug as cost_per_usage below).
        // Default each to 0 rather than crash the save.
        $data['last_purchase_price'] = $data['last_purchase_price'] ?? 0;
        $data['gst_rate']            = $data['gst_rate'] ?? 0;
        $data['reorder_level']       = $data['reorder_level'] ?? 0;
        // has_expiry is no longer editable from this general product-master
        // form (shelf_life_months field removed 2026-07-06 — expiry is a
        // batch/lot-level concept captured at GRN/stock-in, not a master
        // attribute). Leave any existing has_expiry value on the record
        // untouched rather than forcing it here.
        // Checkbox defaults checked in the DOM for both Add and Edit, so an
        // unchecked box always reflects a deliberate user action (deactivate).
        $data['is_active']          = $request->boolean('is_active');
        // Clinical products never appear on the billing product picker — that's
        // what the Saleable/FMCG tab is for. The checkbox that used to let a
        // clinical item opt into billing was removed once that tab shipped.
        $data['is_sellable']        = false;

        return [$data, $primaryLocationId, $primaryVendorId, $alternateVendorIds];
    }

    /** Create the initial stock row and vendor links a product was submitted with. */
    private function attachProductRelations(InventoryItem $item, ?int $primaryLocationId, ?int $primaryVendorId, array $alternateVendorIds): void
    {
        if ($primaryLocationId) {
            InventoryStock::firstOrCreate(
                ['inventory_item_id' => $item->id, 'location_id' => $primaryLocationId],
                ['available_qty' => 0, 'reserved_qty' => 0]
            );
        }

        // Full replace, not additive: the product form always submits the
        // complete supplier selection, so a dropped vendor must actually be
        // detached here — otherwise stale/duplicate tags pile up on every edit.
        $dealerSync = [];
        if ($primaryVendorId) {
            $dealerSync[$primaryVendorId] = ['is_primary' => true, 'is_alternate' => false];
        }
        foreach ($alternateVendorIds as $vendorId) {
            if ($vendorId != $primaryVendorId) {
                $dealerSync[$vendorId] = ['is_primary' => false, 'is_alternate' => true];
            }
        }
        $item->dealers()->sync($dealerSync);
    }

    /**
     * Cost per individual use — cost per piece (avg price ÷ qty in packaging)
     * divided by how many times a multi-use piece gets reused before
     * replacement. This is the number that actually feeds treatment costing;
     * "₹800 a box" doesn't tell you if a procedure is profitable, "₹2 a use" does.
     */
    private function calculateCostPerUsage(array $data): float
    {
        // Column is NOT NULL (default 0), so uncomputable cases resolve to
        // 0 rather than null — the blade view already treats 0 as "hide this row".
        $qty = (float) ($data['qty_in_packaging'] ?? 0);
        if ($qty <= 0 || empty($data['average_purchase_price'])) {
            return 0.0;
        }
        $costPerPiece = $data['average_purchase_price'] / $qty;
        $uses = ($data['usage_type'] === 'multiple_use' && !empty($data['max_usage_count']))
            ? (int) $data['max_usage_count']
            : 1;

        return round($costPerPiece / max(1, $uses), 4);
    }

    public function storeItem(Request $request)
    {
        [$data, $primaryLocationId, $primaryVendorId, $alternateVendorIds] = $request->input('product_kind') === 'saleable'
            ? $this->prepareSaleableProductData($request, $this->validateSaleableProductForm($request))
            : $this->prepareProductData($request, $this->validateProductForm($request));

        $data['item_code']              = 'ITEM-' . str_pad(InventoryItem::count() + 1, 4, '0', STR_PAD_LEFT);
        $data['average_purchase_price'] = $data['last_purchase_price'] ?? 0;
        $data['cost_per_usage']         = $this->calculateCostPerUsage($data);
        $data['created_by']             = auth()->id();

        $item = InventoryItem::create($data);
        $this->attachProductRelations($item, $primaryLocationId, $primaryVendorId, $alternateVendorIds);

        return back()->with('success', 'Item "' . $data['product_name'] . '" added to catalogue.');
    }

    public function updateItem(Request $request, InventoryItem $item)
    {
        [$data, $primaryLocationId, $primaryVendorId, $alternateVendorIds] = $request->input('product_kind') === 'saleable'
            ? $this->prepareSaleableProductData($request, $this->validateSaleableProductForm($request))
            : $this->prepareProductData($request, $this->validateProductForm($request));

        $data['average_purchase_price'] = $data['last_purchase_price'] ?? $item->average_purchase_price;
        $data['cost_per_usage']         = $this->calculateCostPerUsage($data);

        $item->update($data);
        $this->attachProductRelations($item, $primaryLocationId, $primaryVendorId, $alternateVendorIds);

        return back()->with('success', 'Item updated successfully.');
    }

    /**
     * Validation for the Saleable / FMCG tab — deliberately minimal per
     * Sumit's spec: Type, Brand Name, MRP, Expiry, Min Qty only. No
     * category/packaging/cost fields are collected here at all.
     */
    private function validateSaleableProductForm(Request $request): array
    {
        return $request->validate([
            'product_name'       => 'required|string|max:255',
            'retail_type'        => 'required|string|max:40',
            'brand'              => 'required|string|max:255',
            'mrp'                => 'required|numeric|min:0',
            'retail_expiry_date' => 'nullable|date',
            'minimum_qty'        => 'required|numeric|min:0',
            'is_active'          => 'boolean',
        ]);
    }

    /**
     * Fills in every column the Saleable tab doesn't ask about, so it can
     * share the same InventoryItem table/create-flow as clinical products.
     * Returns the same [$data, $primaryLocationId, $primaryVendorId,
     * $alternateVendorIds] shape as prepareProductData() — no location/vendor
     * linking happens from this tab, hence the trailing nulls/[] below.
     */
    private function prepareSaleableProductData(Request $request, array $data): array
    {
        // Retail products are always sold to patients, always active, and
        // don't use the clinical Category/Sub Type/Variant taxonomy at all.
        $data['is_sellable']         = true;
        $data['is_active']           = true;
        $data['category_id']         = null;
        $data['sub_type_id']         = null;
        $data['variant_id']          = null;

        // Single piece, single use per sale — a tube of toothpaste isn't
        // "packaged" in the clinical sense, so these are silently defaulted
        // rather than shown as fields (per Sumit's decision to hide them).
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

        // Same NOT-NULL-with-DB-default-0 columns flagged in prepareProductData()
        // — this tab never collects Purchase Price/GST/Reorder Level, so they
        // must be defaulted here too or the insert crashes.
        $data['last_purchase_price'] = 0;
        $data['gst_rate']            = 0;
        $data['reorder_level']       = 0;

        return [$data, null, null, []];
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
            'unit_cost'         => 'required|numeric|min:0.01',
            'batch_no'          => 'nullable|string|max:80',
            'expiry_date'       => 'nullable|date|after:today',
            'manufacturing_date'=> 'nullable|date',
            'notes'             => 'nullable|string|max:500',
        ]);

        $qty      = (float) $data['qty'];
        $unitCost = (float) $data['unit_cost'];

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

        $vendor = InventoryVendor::create($data);

        // Phase 1: auto-sync to Finance so the vendor appears in Finance > Vendors
        $vendor->syncToFinance();

        return back()->with('success', 'Vendor "' . $data['vendor_name'] . '" added and synced to Finance.');
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
            'items.*.qty'      => 'required|integer|min:1',
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

        // Phase 1: resolve Finance vendor ID from inventory vendor's sync link
        $invVendor       = InventoryVendor::find($request->vendor_id);
        $financeVendorId = $invVendor?->finance_vendor_id
            ?? $invVendor?->syncToFinance()?->id;

        $po = PurchaseOrder::create([
            'order_no'          => PurchaseOrder::generateOrderNo(),
            'vendor_id'         => $request->vendor_id,
            'finance_vendor_id' => $financeVendorId,
            'order_date'        => $request->order_date,
            'expected_date'     => $request->expected_date,
            'status'            => $request->status,
            'total_amount'      => $subtotal + $gstTotal,
            'gst_amount'        => $gstTotal,
            'notes'             => $request->notes,
            'created_by'        => auth()->id(),
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

        // ── Auto-create vendor communication tasks (only when PO is "ordered") ──
        if ($request->status === 'ordered') {
            $vendorName = $invVendor?->vendor_name ?? 'Vendor';
            $vendorNote = 'PO# ' . $po->order_no . ' — ' . $vendorName;

            // Task 1: Confirm PO with vendor on the order date itself
            \App\Models\Task::create([
                'title'       => 'Confirm PO with ' . $vendorName,
                'description' => 'Call or WhatsApp ' . $vendorName . ' to confirm receipt of purchase order. ' . $vendorNote . '.',
                'assigned_to' => auth()->id(),
                'created_by'  => auth()->id(),
                'branch_id'   => auth()->user()->branch_id ?? null,
                'due_date'    => $request->order_date,
                'priority'    => 'medium',
                'category'    => 'call',
                'status'      => 'pending',
                'po_id'       => $po->id,
                'vendor_note' => $vendorNote,
            ]);

            // Task 2: Delivery status follow-up — 1 day before expected date (if set)
            if ($request->expected_date) {
                $followUpDate = \Carbon\Carbon::parse($request->expected_date)->subDay();
                // Only create if follow-up date is in the future
                if ($followUpDate->isFuture() || $followUpDate->isToday()) {
                    \App\Models\Task::create([
                        'title'       => 'Delivery follow-up: ' . $vendorName,
                        'description' => 'Call ' . $vendorName . ' to check delivery status. Expected date is ' . \Carbon\Carbon::parse($request->expected_date)->format('d M Y') . '. ' . $vendorNote . '.',
                        'assigned_to' => auth()->id(),
                        'created_by'  => auth()->id(),
                        'branch_id'   => auth()->user()->branch_id ?? null,
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

        return redirect()->route('inventory.purchase')
            ->with('success', 'Purchase Order ' . $po->order_no . ' created successfully.');
    }

    /* ═══════════════════════════════════════════════════════════
       OTHER STUBS
    ═══════════════════════════════════════════════════════════ */

    public function reusableAssets(Request $request)
    {
        $search   = $request->get('search');
        $status   = $request->get('status');
        $locId    = $request->get('location_id');

        $query = ReusableAsset::with(['item.category', 'location'])->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'like', "%$search%")
                  ->orWhere('serial_number', 'like', "%$search%")
                  ->orWhereHas('item', fn($qi) => $qi->where('product_name', 'like', "%$search%"));
            });
        }
        if ($status)  $query->where('status', $status);
        if ($locId)   $query->where('location_id', $locId);

        $assets    = $query->paginate(25)->withQueryString();
        $locations = InventoryLocation::orderBy('name')->get(['id', 'name']);
        $items     = InventoryItem::orderBy('product_name')->get(['id', 'product_name']);

        // Status summary counts
        $statusCounts = ReusableAsset::selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        return view('inventory.reusable-assets', compact('assets', 'locations', 'items', 'search', 'status', 'locId', 'statusCounts'));
    }

    public function storeAsset(Request $request)
    {
        $data = $request->validate([
            'inventory_item_id'    => 'required|exists:inventory_items,id',
            'asset_code'           => 'required|string|max:50|unique:reusable_assets,asset_code',
            'serial_number'        => 'nullable|string|max:80',
            'tracking_type'        => 'required|in:usage_based,sterilization_based,time_based',
            'max_usage_count'      => 'nullable|integer|min:1',
            'retirement_threshold' => 'nullable|integer|min:1',
            'sterilization_required' => 'boolean',
            'maintenance_interval' => 'nullable|integer|min:1',
            'status'               => 'required|in:available,in_use,sterilization_pending,under_maintenance,retired',
            'purchase_date'        => 'nullable|date',
            'location_id'          => 'nullable|exists:inventory_locations,id',
            'notes'                => 'nullable|string',
        ]);

        $data['sterilization_required'] = $request->boolean('sterilization_required');
        ReusableAsset::create($data);

        return redirect()->route('inventory.reusable-assets')->with('success', 'Asset added successfully.');
    }

    public function updateAsset(Request $request, ReusableAsset $asset)
    {
        $data = $request->validate([
            'asset_code'           => 'required|string|max:50|unique:reusable_assets,asset_code,' . $asset->id,
            'serial_number'        => 'nullable|string|max:80',
            'tracking_type'        => 'required|in:usage_based,sterilization_based,time_based',
            'max_usage_count'      => 'nullable|integer|min:1',
            'retirement_threshold' => 'nullable|integer|min:1',
            'sterilization_required' => 'boolean',
            'maintenance_interval' => 'nullable|integer|min:1',
            'status'               => 'required|in:available,in_use,sterilization_pending,under_maintenance,retired',
            'purchase_date'        => 'nullable|date',
            'location_id'          => 'nullable|exists:inventory_locations,id',
            'notes'                => 'nullable|string',
        ]);

        $data['sterilization_required'] = $request->boolean('sterilization_required');
        $asset->update($data);

        return redirect()->route('inventory.reusable-assets')->with('success', 'Asset updated.');
    }

    public function updateAssetStatus(Request $request, ReusableAsset $asset)
    {
        $request->validate(['action' => 'required|in:sterilized,maintained,retire,mark_available,mark_in_use']);

        match ($request->action) {
            'sterilized'     => $asset->update([
                'status'             => 'available',
                'last_sterilized_at' => now(),
                'sterilization_count'=> $asset->sterilization_count + 1,
            ]),
            'maintained'     => $asset->update([
                'status'             => 'available',
                'last_maintained_at' => now(),
                'next_maintenance_due'=> $asset->maintenance_interval
                    ? now()->addDays($asset->maintenance_interval) : null,
            ]),
            'retire'         => $asset->update(['status' => 'retired']),
            'mark_available' => $asset->update(['status' => 'available']),
            'mark_in_use'    => $asset->update([
                'status'              => 'in_use',
                'current_usage_count' => $asset->current_usage_count + 1,
            ]),
        };

        return redirect()->route('inventory.reusable-assets')->with('success', 'Asset status updated.');
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

        $variants = \App\Models\Inventory\InventoryVariant::with('subType.category')
            ->orderBy('name')
            ->get();

        return view('inventory.settings', compact('categories', 'locations', 'settings', 'subTypes', 'variants'));
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
       SUB-TYPES CRUD
    ───────────────────────────────────────────────────────── */

    public function storeSubType(Request $request)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        $data = $request->validate([
            'category_id' => 'required|exists:inventory_categories,id',
            'name'        => 'required|string|max:100',
        ]);
        $data['is_active'] = true;

        \App\Models\Inventory\InventorySubType::create($data);
        return back()->with('success', 'Sub-type "' . $data['name'] . '" added.');
    }

    public function updateSubType(Request $request, \App\Models\Inventory\InventorySubType $st)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        $data = $request->validate([
            'category_id' => 'required|exists:inventory_categories,id',
            'name'        => 'required|string|max:100',
            'is_active'   => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $st->update($data);

        return back()->with('success', 'Sub-type updated.');
    }

    public function destroySubType(\App\Models\Inventory\InventorySubType $st)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        if ($st->items()->count() > 0) {
            return back()->withErrors(['sub_type' => 'Cannot delete — ' . $st->items()->count() . ' product(s) use this sub-type.']);
        }

        $st->delete(); // cascades to inventory_variants
        return back()->with('success', 'Sub-type deleted.');
    }

    /* ─────────────────────────────────────────────────────────
       VARIANTS CRUD  (3rd tier: Category → Sub-type → Variant)
    ───────────────────────────────────────────────────────── */

    /**
     * AJAX — return active variants for a given sub-type.
     * Called by the product form when sub_type changes.
     * GET /inventory/ajax/variants?sub_type_id=X
     */
    public function ajaxVariants(Request $request)
    {
        $subTypeId = $request->input('sub_type_id');

        if (!$subTypeId) {
            return response()->json([]);
        }

        $variants = \App\Models\Inventory\InventoryVariant::where('sub_type_id', $subTypeId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($variants);
    }

    /**
     * AJAX — create a variant inline from the product form.
     * POST /inventory/ajax/variants  (JSON request/response)
     */
    public function ajaxStoreVariant(Request $request)
    {
        $data = $request->validate([
            'sub_type_id' => 'required|exists:inventory_sub_types,id',
            'name'        => 'required|string|max:100',
        ]);
        $data['is_active'] = true;

        // Prevent duplicate names for the same sub-type
        $exists = \App\Models\Inventory\InventoryVariant::where('sub_type_id', $data['sub_type_id'])
            ->whereRaw('LOWER(name) = ?', [strtolower($data['name'])])
            ->first();

        if ($exists) {
            return response()->json(['error' => '"' . $data['name'] . '" already exists for this sub-type.'], 422);
        }

        $variant = \App\Models\Inventory\InventoryVariant::create($data);

        return response()->json([
            'id'          => $variant->id,
            'name'        => $variant->name,
            'sub_type_id' => $variant->sub_type_id,
        ]);
    }

    public function storeVariant(Request $request)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        $data = $request->validate([
            'sub_type_id' => 'required|exists:inventory_sub_types,id',
            'name'        => 'required|string|max:100',
        ]);
        $data['is_active'] = true;

        \App\Models\Inventory\InventoryVariant::create($data);
        return back()->with('success', 'Variant "' . $data['name'] . '" added.');
    }

    public function updateVariant(Request $request, \App\Models\Inventory\InventoryVariant $variant)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        $data = $request->validate([
            'sub_type_id' => 'required|exists:inventory_sub_types,id',
            'name'        => 'required|string|max:100',
            'is_active'   => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $variant->update($data);

        return back()->with('success', 'Variant updated.');
    }

    public function destroyVariant(\App\Models\Inventory\InventoryVariant $variant)
    {
        if (auth()->user()?->role !== 'admin') abort(403);

        if ($variant->items()->count() > 0) {
            return back()->withErrors(['variant' => 'Cannot delete — ' . $variant->items()->count() . ' product(s) use this variant.']);
        }

        $variant->delete();
        return back()->with('success', 'Variant deleted.');
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

        // `code` is NOT NULL + unique at the DB level, but the form marks it "optional" —
        // auto-generate one from the name so a blank field never hits a NULL insert.
        if (empty($data['code'])) {
            $data['code'] = $this->generateUniqueLocationCode($data['name']);
        }

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

        if (empty($data['code'])) {
            $data['code'] = $this->generateUniqueLocationCode($data['name'], $loc->id);
        }

        $data['is_active'] = $request->boolean('is_active');
        $loc->update($data);
        return back()->with('success', 'Location updated.');
    }

    /**
     * Build a short, unique location code from a name (e.g. "Main Store" -> "MAIN-STORE").
     * Falls back to a numeric suffix if the base slug is already taken.
     */
    private function generateUniqueLocationCode(string $name, ?int $ignoreId = null): string
    {
        $base = strtoupper(Str::slug($name, '-'));
        $base = substr($base, 0, 16) ?: 'LOC';

        $code = $base;
        $suffix = 1;

        while (
            InventoryLocation::where('code', $code)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $suffix++;
            $code = substr($base, 0, 20 - strlen("-{$suffix}")) . "-{$suffix}";
        }

        return $code;
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

        // Phase 1: keep Finance mirror in sync
        $vendor->refresh()->syncToFinance();

        return back()->with('success', 'Vendor "' . $vendor->vendor_name . '" updated.');
    }

    /* ─────────────────────────────────────────────────────────
       GRN — Receive Against PO
    ───────────────────────────────────────────────────────── */

    /**
     * Mark a Draft PO as Ordered (sent to vendor).
     * Called via PATCH /inventory/purchase/{po}/mark-ordered
     */
    public function markOrdered(PurchaseOrder $po)
    {
        if ($po->status !== 'draft') {
            return back()->with('error', 'Only draft orders can be marked as ordered.');
        }

        $po->update(['status' => 'ordered']);

        return back()->with('success', "PO {$po->order_no} marked as Ordered.");
    }

    /* ─────────────────────────────────────────────────────────
       PO EDIT (header fields only — vendor, dates, notes)
    ───────────────────────────────────────────────────────── */

    public function updatePO(Request $request, PurchaseOrder $po)
    {
        // Cancelled POs cannot be edited
        if ($po->status === 'cancelled') {
            return back()->withErrors(['po' => 'Cancelled POs cannot be edited.']);
        }

        $data = $request->validate([
            'vendor_id'     => 'required|exists:inventory_vendors,id',
            'order_date'    => 'required|date',
            'expected_date' => 'nullable|date|after_or_equal:order_date',
            'notes'         => 'nullable|string|max:1000',
        ]);

        $po->update($data);

        return back()->with('success', "PO {$po->order_no} updated.");
    }

    /* ─────────────────────────────────────────────────────────
       PO DELETE
       - Draft:   any user can delete (with reason)
       - Ordered: admin/owner only (hard delete, with reason)
       - Received/Completed/Cancelled: blocked
    ───────────────────────────────────────────────────────── */

    public function destroyPO(Request $request, PurchaseOrder $po)
    {
        $request->validate([
            'delete_reason' => 'required|string|min:5|max:500',
        ]);

        $user = auth()->user();

        if (in_array($po->status, ['partially_received', 'completed'])) {
            return back()->withErrors(['po' => 'PO ' . $po->order_no . ' cannot be deleted — goods have already been received against it.']);
        }

        if ($po->status === 'ordered') {
            if (!$user->isAdmin()) {
                return back()->withErrors(['po' => 'Only an admin/owner can delete an ordered PO. Please contact your administrator.']);
            }
        }

        // Log reason to notes before deleting (so it's traceable if needed)
        \Illuminate\Support\Facades\Log::info("PO {$po->order_no} deleted by user #{$user->id} ({$user->name}). Reason: {$request->delete_reason}");

        $po->items()->delete();
        $po->delete();

        $label = $po->status === 'ordered' ? 'Ordered PO' : 'Draft PO';
        return back()->with('success', "{$label} {$po->order_no} deleted.");
    }

    /* ─────────────────────────────────────────────────────────
       GRN REVERSAL — undo the most recent GRN for this PO
       Only allowed within the admin-configured correction window.
    ───────────────────────────────────────────────────────── */

    public function reverseLastGrn(PurchaseOrder $po)
    {
        // Check correction window is enabled
        $windowHours = (int) AppSetting::get('grn_correction_window_hours', 0);
        if ($windowHours === 0) {
            return back()->withErrors(['grn' => 'GRN corrections are disabled. Enable them in Settings → Inventory.']);
        }

        // Get the most recent GRN for this PO
        $grn = GoodsReceiptNote::where('purchase_order_id', $po->id)
            ->latest()
            ->first();

        if (!$grn) {
            return back()->withErrors(['grn' => 'No receipt found for this PO.']);
        }

        // Check if within the correction window
        $cutoff = now()->subHours($windowHours);
        if ($grn->created_at->lt($cutoff)) {
            return back()->withErrors(['grn' => 'Correction window has expired. GRN ' . $grn->grn_number . ' was recorded more than ' . $windowHours . ' hour(s) ago.']);
        }

        \DB::transaction(function () use ($grn, $po) {
            // 1. Reverse each stock movement created by this GRN
            foreach ($grn->items as $grnItem) {
                // Decrement qty_received on the PO line
                $poItem = $po->items()
                    ->where('inventory_item_id', $grnItem->inventory_item_id)
                    ->first();
                if ($poItem) {
                    $newQty = max(0, $poItem->qty_received - $grnItem->qty_received);
                    $poItem->update(['qty_received' => $newQty]);
                }

                // Delete the linked stock movement
                if ($grnItem->stock_movement_id) {
                    StockMovement::where('id', $grnItem->stock_movement_id)->delete();
                }
            }

            // 2. Void the Finance expense linked to this GRN
            FinanceExpense::where('grn_number', $grn->grn_number)->update([
                'payment_status' => 'void',
                'notes'          => 'Voided — GRN ' . $grn->grn_number . ' reversed on ' . now()->format('d M Y H:i') . '.',
            ]);

            // 3. Delete the GRN (items cascade via FK or manual)
            $grn->items()->delete();
            $grn->delete();

            // 4. Recalculate PO status
            $po->refresh();
            $allItems = $po->items;
            $allFullyReceived = $allItems->every(fn($i) => $i->qty_received >= $i->qty_ordered);
            $anyPartial       = $allItems->some(fn($i)  => $i->qty_received > 0);

            $po->update([
                'status' => $allFullyReceived ? 'completed'
                          : ($anyPartial ? 'partially_received' : 'ordered'),
            ]);
        });

        return back()->with('success', 'GRN reversed. Stock and Finance expense have been corrected for PO ' . $po->order_no . '.');
    }

    public function receivePO(Request $request, PurchaseOrder $po)
    {
        $request->validate([
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

        $locationId   = $request->location_id;
        $receivedDate = $request->received_date;
        $anyReceived  = false;

        // Phase 1: create a GRN header record
        $grn = \App\Models\Procurement\GoodsReceiptNote::create([
            'grn_number'        => \App\Models\Procurement\GoodsReceiptNote::generateGrnNumber(),
            'purchase_order_id' => $po->id,
            'vendor_id'         => $po->vendor_id,
            'received_date'     => $receivedDate,
            'location_id'       => $locationId,
            'status'            => 'confirmed',
            'created_by'        => auth()->id(),
        ]);

        foreach ($request->lines as $idx => $line) {
            $qty = (float) $line['qty'];
            if ($qty <= 0) continue;

            $anyReceived = true;
            $poItem   = $po->items()->where('inventory_item_id', $line['item_id'])->first();
            $unitCost = (float) ($line['unit_cost'] ?? $poItem?->unit_price ?? 0);

            // Record stock_in movement (uses polymorphic reference for PO traceability)
            $movement = StockMovement::create([
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
                'notes'             => 'GRN# ' . $grn->grn_number . ' — PO# ' . $po->order_no,
                'created_by'        => auth()->id(),
            ]);

            // Phase 1: create GRN item record with link back to StockMovement
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
            // Clean up the empty GRN header we created
            $grn->delete();
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

        // ── Auto-create an unpaid vendor invoice in Finance for THIS GRN ──
        // Each GRN = one vendor invoice (supports partial deliveries with multiple invoices per PO)

        // Reload GRN items from DB — relationship cache is empty right after bulk insert
        $grn->load('items');

        // Calculate the value of items received in THIS GRN
        $grnSubtotal = 0;
        $grnGst      = 0;
        foreach ($grn->items as $grnItem) {
            $poItem      = $po->items()->where('inventory_item_id', $grnItem->inventory_item_id)->first();
            $gstRate     = $poItem?->gst_rate ?? 0;
            $lineAmt     = $grnItem->total_price; // qty * unit_price already stored
            $lineGst     = $lineAmt * ($gstRate / 100);
            $grnSubtotal += $lineAmt;
            $grnGst      += $lineGst;
        }
        $grnTotal = $grnSubtotal + $grnGst;

        if ($grnTotal > 0) {
            $suppliesCategory = FinanceExpenseCategory::where('name', 'like', '%Suppl%')
                ->orWhere('name', 'like', '%Dental%')
                ->orWhere('name', 'like', '%Inventory%')
                ->first();

            $financeVendorId = $po->finance_vendor_id
                ?? $po->resolveFinanceVendor()?->id;

            $vendorInvoiceNo = $request->vendor_invoice_no;
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
                'source_type'       => GoodsReceiptNote::class,
                'source_id'         => $grn->id,
                'created_by'        => auth()->id(),
            ]);
        }

        return redirect()->route('inventory.purchase')
            ->with('success', 'GRN recorded for PO# ' . $po->order_no . '. Stock updated. Pending bill added to Finance.');
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
       ALERTS HUB — smart purchasing intelligence
    ═══════════════════════════════════════════════════════════ */

    public function alerts(Request $request)
    {
        $today = now()->toDateString();

        // Critical stock: at or below half of minimum_qty (or zero)
        $criticalStock = InventoryItem::with(['category', 'stocks.location'])
            ->withSum('stocks as total_qty', 'available_qty')
            ->where('is_active', true)
            ->whereNotNull('minimum_qty')
            ->where('minimum_qty', '>', 0)
            ->havingRaw('total_qty <= (minimum_qty / 2)')
            ->orderBy('product_name')
            ->get();

        // Low stock: above half but at or below minimum_qty
        $lowStock = InventoryItem::with(['category', 'stocks.location'])
            ->withSum('stocks as total_qty', 'available_qty')
            ->where('is_active', true)
            ->whereNotNull('minimum_qty')
            ->where('minimum_qty', '>', 0)
            ->havingRaw('total_qty > (minimum_qty / 2) AND total_qty <= minimum_qty')
            ->orderBy('product_name')
            ->get();

        // Expiring within 90 days — based on stock_in movements with expiry dates
        $expiringSoon = StockMovement::with(['item.category', 'toLocation'])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>', $today)
            ->where('expiry_date', '<=', now()->addDays(90)->toDateString())
            ->where('qty', '>', 0)
            ->whereIn('movement_type', ['stock_in', 'opening_stock'])
            ->orderBy('expiry_date')
            ->get();

        // Already expired movements that still had qty > 0 when received
        $expiredItems = StockMovement::with(['item.category', 'toLocation'])
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<=', $today)
            ->where('qty', '>', 0)
            ->whereIn('movement_type', ['stock_in', 'opening_stock'])
            ->orderBy('expiry_date')
            ->get();

        // Dead stock: items with stock but no movement in 90+ days
        $deadStock = InventoryItem::with(['category'])
            ->withSum('stocks as total_qty', 'available_qty')
            ->where('is_active', true)
            ->havingRaw('total_qty > 0')
            ->whereDoesntHave('movements', function ($q) {
                $q->where('created_at', '>=', now()->subDays(90));
            })
            ->orderBy('product_name')
            ->get();

        // Pending deliveries: POs not yet fully received
        $pendingDeliveries = \App\Models\Inventory\PurchaseOrder::with(['vendor', 'items.inventoryItem'])
            ->whereIn('status', ['ordered', 'partially_received'])
            ->orderBy('expected_date')
            ->get();

        $summary = [
            'critical' => $criticalStock->count(),
            'low'      => $lowStock->count(),
            'expiring' => $expiringSoon->count(),
            'expired'  => $expiredItems->count(),
            'dead'     => $deadStock->count(),
            'pending'  => $pendingDeliveries->count(),
        ];

        return view('inventory.alerts', compact(
            'criticalStock', 'lowStock', 'expiringSoon', 'expiredItems',
            'deadStock', 'pendingDeliveries', 'summary'
        ));
    }

    /* ═══════════════════════════════════════════════════════════
       IMPLANT REGISTRY
    ═══════════════════════════════════════════════════════════ */

    public function implants(Request $request)
    {
        $tab = $request->get('tab', 'catalog');

        $catalog = ImplantCatalog::withCount('placements')
            ->with(['inventoryItem.stocks', 'inventoryItem.usageModeChangedBy'])
            ->orderBy('brand')
            ->orderBy('system')
            ->orderBy('component_type')
            ->paginate(30)->withQueryString();

        $placements = ImplantPlacement::with(['patient', 'catalogItem', 'surgeon'])
            ->latest('surgery_date')
            ->paginate(30)->withQueryString();

        $brands = ImplantCatalog::distinct('brand')->pluck('brand');
        $types  = ['fixture','abutment','healing_abutment','analogue','scan_body','coping','cover_screw','graft','other'];

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
            'component_type' => 'required|in:fixture,abutment,healing_abutment,analogue,scan_body,coping,cover_screw,graft,other',
            // Fixtures are the implanted device — product_code is what a recall or
            // warranty claim traces back to (alongside lot_number on the placement),
            // so it's required for that type only. Other components rarely have a
            // real manufacturer code, so it stays optional for them.
            'product_code'   => ['required_if:component_type,fixture', 'nullable', 'string', 'max:100'],
            'description'    => 'nullable|string|max:255',
            'diameter_mm'    => 'nullable|string|max:30',
            'length_mm'      => 'nullable|string|max:30',
            'platform'       => 'nullable|string|max:60',
            'material'       => 'nullable|string|max:80',
            'unit_price'     => 'nullable|numeric|min:0',
            'is_reusable'    => 'boolean',
            'minimum_qty'    => 'nullable|numeric|min:0',
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $request->file('photo')->store('implants/catalog', 'public');
        }

        $isReusable = $request->boolean('is_reusable'); // default false = manufacturer single-use IFU
        $minimumQty = (float) ($data['minimum_qty'] ?? 0);
        unset($data['photo'], $data['is_reusable'], $data['minimum_qty']);
        $data['created_by'] = auth()->id();

        DB::transaction(function () use ($data, $isReusable, $minimumQty) {
            $catalogItem = ImplantCatalog::create($data);

            // Pair with a real stock-tracked inventory item. This is what makes
            // quantity for healing abutments / cover screws / copings / scan bodies
            // real for the first time, instead of the catalog being reference-only.
            $inventoryItem = InventoryItem::create([
                'item_code'               => 'IMPL-' . str_pad(InventoryItem::where('item_code', 'like', 'IMPL-%')->count() + 1, 4, '0', STR_PAD_LEFT),
                'product_name'            => $catalogItem->getFullName(),
                'brand'                   => $catalogItem->brand,
                'inventory_behavior'      => $isReusable ? 'reusable' : 'consumable',
                'usage_type'              => $isReusable ? 'multiple_use' : 'single_use',
                'is_reusable'             => $isReusable,
                'sterilization_required'  => $isReusable,
                'purchase_unit'           => 'piece',
                'consumption_unit'        => 'piece',
                'pieces_per_unit'         => 1,
                'last_purchase_price'     => $catalogItem->unit_price ?? 0,
                'average_purchase_price'  => $catalogItem->unit_price ?? 0,
                'minimum_qty'             => $minimumQty,
                'minimum_order_qty'       => 1,
                'is_active'               => true,
                'created_by'              => auth()->id(),
            ]);

            $catalogItem->update(['inventory_item_id' => $inventoryItem->id]);
        });

        return back()->with('success', 'Implant component added to catalog with stock tracking.');
    }

    public function updateCatalogItem(Request $request, ImplantCatalog $catalogItem)
    {
        $data = $request->validate([
            'brand'          => 'required|string|max:100',
            'system'         => 'nullable|string|max:100',
            'component_type' => 'required|in:fixture,abutment,healing_abutment,analogue,scan_body,coping,cover_screw,graft,other',
            'product_code'   => ['required_if:component_type,fixture', 'nullable', 'string', 'max:100'],
            'description'    => 'nullable|string|max:255',
            'diameter_mm'    => 'nullable|string|max:30',
            'length_mm'      => 'nullable|string|max:30',
            'platform'       => 'nullable|string|max:60',
            'material'       => 'nullable|string|max:80',
            'unit_price'     => 'nullable|numeric|min:0',
            'is_active'      => 'boolean',
            'is_reusable'    => 'boolean',
            'minimum_qty'    => 'nullable|numeric|min:0',
            'photo'          => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            if ($catalogItem->photo_path) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($catalogItem->photo_path);
            }
            $data['photo_path'] = $request->file('photo')->store('implants/catalog', 'public');
        }

        $data['is_active'] = $request->boolean('is_active');
        $isReusable = $request->boolean('is_reusable');
        $minimumQty = (float) ($data['minimum_qty'] ?? 0);
        unset($data['photo'], $data['is_reusable'], $data['minimum_qty']);

        DB::transaction(function () use ($catalogItem, $data, $isReusable, $minimumQty) {
            $catalogItem->update($data);
            $catalogItem->refresh();

            $inventoryItem = $catalogItem->inventoryItem;

            if (!$inventoryItem) {
                // Legacy catalog row created before stock-linking existed — backfill now.
                $inventoryItem = InventoryItem::create([
                    'item_code'               => 'IMPL-' . str_pad(InventoryItem::where('item_code', 'like', 'IMPL-%')->count() + 1, 4, '0', STR_PAD_LEFT),
                    'product_name'            => $catalogItem->getFullName(),
                    'brand'                   => $catalogItem->brand,
                    'inventory_behavior'      => $isReusable ? 'reusable' : 'consumable',
                    'usage_type'              => $isReusable ? 'multiple_use' : 'single_use',
                    'is_reusable'             => $isReusable,
                    'sterilization_required'  => $isReusable,
                    'purchase_unit'           => 'piece',
                    'consumption_unit'        => 'piece',
                    'pieces_per_unit'         => 1,
                    'last_purchase_price'     => $catalogItem->unit_price ?? 0,
                    'average_purchase_price'  => $catalogItem->unit_price ?? 0,
                    'minimum_qty'             => $minimumQty,
                    'minimum_order_qty'       => 1,
                    'is_active'               => true,
                    'created_by'              => auth()->id(),
                ]);
                $catalogItem->update(['inventory_item_id' => $inventoryItem->id]);
                return;
            }

            // Only stamp the audit trail when the reusable/single-use call actually changes —
            // this is what makes the override traceable (who + when) without a separate flag.
            $usageModeChanged = (bool) $inventoryItem->is_reusable !== $isReusable;

            $inventoryItem->product_name = $catalogItem->getFullName();
            $inventoryItem->brand        = $catalogItem->brand;
            $inventoryItem->minimum_qty  = $minimumQty;
            if (!empty($data['unit_price'])) {
                $inventoryItem->last_purchase_price    = $data['unit_price'];
                $inventoryItem->average_purchase_price = $data['unit_price'];
            }

            if ($usageModeChanged) {
                $inventoryItem->is_reusable            = $isReusable;
                $inventoryItem->inventory_behavior     = $isReusable ? 'reusable' : 'consumable';
                $inventoryItem->usage_type             = $isReusable ? 'multiple_use' : 'single_use';
                $inventoryItem->sterilization_required = $isReusable;
                $inventoryItem->usage_mode_changed_by   = auth()->id();
                $inventoryItem->usage_mode_changed_at   = now();
            }

            $inventoryItem->save();
        });

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
       STOCK VIEW — Quick +/- Adjust
    ═══════════════════════════════════════════════════════════ */

    public function adjustStock(Request $request, InventoryItem $item)
    {
        $data = $request->validate([
            'type'        => 'required|in:add,remove',
            'qty'         => 'required|integer|min:1',
            'location_id' => 'required|exists:inventory_locations,id',
            'note'        => 'nullable|string|max:255',
            'unit_price'  => 'nullable|numeric|min:0',
        ]);

        $qty = (float) $data['qty'];

        if ($data['type'] === 'remove') {
            $stock = InventoryStock::where('inventory_item_id', $item->id)
                ->where('location_id', $data['location_id'])
                ->first();

            if (!$stock || $stock->available_qty < $qty) {
                return back()->withErrors(['qty' => 'Cannot remove more than available stock (' . ($stock->available_qty ?? 0) . ').']);
            }
        }

        // Price only ever moves with stock coming IN. If a fresh unit price was
        // given, it becomes the item's new purchase price (same simple-overwrite
        // convention already used by GRN receiving — see updateCatalogItem()).
        // Leaving it blank keeps the last known price untouched, so a routine
        // "add stock" for an item whose price hasn't changed stays one field.
        $unitCost = (float) $item->average_purchase_price;
        if ($data['type'] === 'add' && !empty($data['unit_price'])) {
            $unitCost = (float) $data['unit_price'];
            $item->last_purchase_price    = $unitCost;
            $item->average_purchase_price = $unitCost;
            $item->save();
        }

        // StockMovement::booted() → updateLiveStock() handles inventory_stocks update automatically.
        StockMovement::create([
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
            'created_by'        => auth()->id(),
        ]);

        return back()->with('success', 'Stock updated.');
    }

    /**
     * Recent stock movement log for one product — powers the "Stock History"
     * panel from the products table's ⋯ menu. Sumit flagged that there was no
     * log view at all, which is what made "fix a mistake" impossible to reason
     * about; this is that log, plus which entries can still be reversed.
     */
    public function stockHistory(InventoryItem $item)
    {
        $movements = StockMovement::where('inventory_item_id', $item->id)
            ->with(['toLocation:id,name', 'fromLocation:id,name', 'createdBy:id,name', 'reversedBy:id,name'])
            ->latest()
            ->take(25)
            ->get()
            ->map(fn (StockMovement $m) => [
                'id'          => $m->id,
                'date'        => $m->created_at->format('d M Y, h:i A'),
                'type'        => $m->getMovementLabel(),
                'color'       => $m->getMovementColor(),
                'is_add'      => $m->movement_type === 'stock_in',
                'qty'         => (float) $m->qty,
                'unit_cost'   => (float) $m->unit_cost,
                'total_cost'  => (float) $m->total_cost,
                'location'    => $m->toLocation?->name ?? $m->fromLocation?->name ?? '—',
                'note'        => $m->notes,
                'created_by'  => $m->createdBy?->name ?? '—',
                'is_reversal' => (bool) $m->reversal_of_id,
                'reversed'    => (bool) $m->reversed_at,
                'reversed_meta' => $m->reversed_at
                    ? ($m->reversed_at->format('d M Y, h:i A') . ' by ' . ($m->reversedBy?->name ?? '—'))
                    : null,
                'can_reverse' => $m->isReversible() && (auth()->user()?->isAdminRole() ?? false),
            ]);

        return response()->json(['movements' => $movements]);
    }

    /**
     * Reverse a manual quick-adjustment. This never edits or deletes the
     * original row — it creates a compensating movement (same pattern as
     * reverseLastGrn()) and stamps the original as reversed so it can't be
     * reversed twice. Admin-only, same gate as deleting a product.
     */
    public function reverseAdjustment(StockMovement $movement)
    {
        if (!$movement->isReversible()) {
            return back()->withErrors(['reverse' => 'This entry can no longer be reversed.']);
        }

        $isAdd = $movement->movement_type === 'stock_in';

        // Reversing an "add" removes stock again — make sure it's still there
        // (it may have since been consumed, transferred, or sold).
        if ($isAdd) {
            $stock = InventoryStock::where('inventory_item_id', $movement->inventory_item_id)
                ->where('location_id', $movement->to_location_id)
                ->first();

            if (!$stock || $stock->available_qty < $movement->qty) {
                return back()->withErrors(['reverse' => 'Cannot reverse — only ' . ($stock->available_qty ?? 0) . ' left at that location (some of this stock has already moved).']);
            }
        }

        DB::transaction(function () use ($movement, $isAdd) {
            StockMovement::create([
                'inventory_item_id' => $movement->inventory_item_id,
                'to_location_id'    => $isAdd ? null : $movement->from_location_id,
                'from_location_id'  => $isAdd ? $movement->to_location_id : null,
                'movement_type'     => $isAdd ? 'stock_out' : 'stock_in',
                'qty'               => $movement->qty,
                'unit_cost'         => $movement->unit_cost,
                'total_cost'        => $movement->total_cost,
                'reference_type'    => 'manual_adjustment_reversal',
                'reference_id'      => $movement->id,
                'reversal_of_id'    => $movement->id,
                'notes'             => 'Reversal of #' . $movement->id . ($movement->notes ? ' — ' . $movement->notes : ''),
                'created_by'        => auth()->id(),
            ]);

            $movement->update([
                'reversed_at' => now(),
                'reversed_by' => auth()->id(),
            ]);
        });

        return back()->with('success', 'Adjustment reversed.');
    }

}