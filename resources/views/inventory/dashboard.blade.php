{{--
|==========================================================================
| Inventory Dashboard — Phase 1
| Dentfluence Inventory OS — Clinical Resource Management
|==========================================================================
--}}
@extends('layouts.app')

@section('page-title', 'Inventory')

@section('head-extra')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    /* ── Inventory Module Styles ── */
    .inv-kpi-grid {
        display: grid;
        grid-template-columns: repeat(6, 1fr);
        gap: 12px;
        margin-bottom: 20px;
    }
    @media (max-width: 1200px) { .inv-kpi-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 640px)  { .inv-kpi-grid { grid-template-columns: repeat(2, 1fr); } }

    .inv-kpi-card {
        background: #fff;
        border: 1px solid rgba(185,92,183,0.12);
        border-radius: 4px;
        padding: 16px;
        position: relative;
        overflow: hidden;
        cursor: default;
        transition: box-shadow 150ms, transform 150ms;
    }
    .inv-kpi-card:hover {
        box-shadow: 0 4px 14px rgba(106,15,112,0.08);
        transform: translateY(-1px);
    }
    .inv-kpi-card.alert-card {
        border-left: 3px solid var(--alert-color);
    }

    .inv-qa-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
        margin-bottom: 24px;
    }
    @media (max-width: 1100px) { .inv-qa-grid { grid-template-columns: repeat(4, 1fr); } }
    @media (max-width: 600px)  { .inv-qa-grid { grid-template-columns: repeat(3, 1fr); } }

    .inv-qa-btn {
        background: #fff;
        border: 1px solid rgba(185,92,183,0.12);
        border-radius: 4px;
        padding: 14px 10px;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
        text-decoration: none;
        color: #1e0a2c;
        font-family: 'DM Sans', sans-serif;
        font-size: 12px;
        font-weight: 500;
        text-align: center;
        cursor: pointer;
        transition: background 150ms, box-shadow 150ms, transform 150ms;
        line-height: 1.3;
    }
    .inv-qa-btn:hover {
        background: #f9f3fa;
        box-shadow: 0 2px 10px rgba(106,15,112,0.07);
        transform: translateY(-1px);
        color: #6a0f70;
    }
    .inv-qa-btn svg { flex-shrink: 0; }

    .inv-section-grid-2 {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }
    @media (max-width: 900px) { .inv-section-grid-2 { grid-template-columns: 1fr; } }

    .inv-section-grid-3 {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 16px;
        margin-bottom: 16px;
    }
    @media (max-width: 1100px) { .inv-section-grid-3 { grid-template-columns: 1fr 1fr; } }
    @media (max-width: 700px)  { .inv-section-grid-3 { grid-template-columns: 1fr; } }

    .inv-card {
        background: #fff;
        border: 1px solid rgba(185,92,183,0.10);
        border-radius: 4px;
        overflow: hidden;
    }
    .inv-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 13px 18px;
        border-bottom: 1px solid rgba(185,92,183,0.07);
        background: #faf5fb;
    }
    .inv-card-title {
        font-family: 'DM Sans', sans-serif;
        font-size: 12.5px;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        color: #4e0a53;
    }
    .inv-card-body {
        padding: 18px;
    }

    .inv-status-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 2px 8px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
        white-space: nowrap;
    }

    .inv-movement-feed { display: flex; flex-direction: column; gap: 0; }
    .inv-movement-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 10px 18px;
        border-bottom: 1px solid rgba(185,92,183,0.05);
        transition: background 120ms;
    }
    .inv-movement-item:last-child { border-bottom: none; }
    .inv-movement-item:hover { background: #faf5fb; }

    .inv-footer-strip {
        display: flex;
        gap: 0;
        background: #fff;
        border: 1px solid rgba(185,92,183,0.10);
        border-radius: 4px;
        margin-top: 20px;
        overflow: hidden;
    }
    .inv-footer-stat {
        flex: 1;
        padding: 14px 18px;
        text-align: center;
        border-right: 1px solid rgba(185,92,183,0.08);
    }
    .inv-footer-stat:last-child { border-right: none; }
    @media (max-width: 700px) {
        .inv-footer-strip { flex-wrap: wrap; }
        .inv-footer-stat  { flex: 1 0 33%; border-bottom: 1px solid rgba(185,92,183,0.08); }
    }

    /* Bar chart row */
    .inv-bar-row { margin-bottom: 12px; }
    .inv-bar-track { height: 6px; background: rgba(185,92,183,0.08); border-radius: 3px; overflow: hidden; margin-top: 5px; }
    .inv-bar-fill  { height: 100%; border-radius: 3px; transition: width 600ms cubic-bezier(0.4,0,0.2,1); }

    /* Expiry urgency */
    .urgency-high   { color: #b52020; }
    .urgency-medium { color: #a05c00; }
    .urgency-low    { color: #1a7a45; }
</style>
@endsection

@section('content')

{{-- ── Module Page Header ── --}}
<div class="df-page-header">
    <div>
        <div class="df-page-title" style="font-size:22px;">Inventory</div>
        <div class="df-page-subtitle">Clinical Resource Operating System · {{ now()->format('l, d M Y') }}</div>
    </div>
    <div class="df-page-actions">
        <a href="{{ route('inventory.stock-in') }}"
           style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#6a0f70;color:#fff;border-radius:3px;font-size:13px;font-weight:500;text-decoration:none;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Stock In
        </a>
    </div>
</div>

{{-- ── Horizontal Sub-Nav ── --}}
@include('inventory.partials.subnav')

{{-- ════════════════════════════════════════════════════════════
     1. KPI CARDS
════════════════════════════════════════════════════════════ --}}
<div class="inv-kpi-grid">
    @foreach($kpis as $kpi)
    <div class="inv-kpi-card {{ !empty($kpi['alert']) ? 'alert-card' : '' }}"
         style="--alert-color: {{ $kpi['color'] }};">
        {{-- Icon ── --}}
        <div style="width:36px;height:36px;background:{{ $kpi['bg'] }};border-radius:50%;display:flex;align-items:center;justify-content:center;margin-bottom:12px;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="{{ $kpi['color'] }}" stroke-width="1.75"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="{{ $kpi['icon'] }}"/>
            </svg>
        </div>
        {{-- Value ── --}}
        <div style="font-family:'DM Mono','DM Sans',monospace;font-size:22px;font-weight:600;color:{{ $kpi['color'] }};line-height:1;margin-bottom:4px;">
            {{ $kpi['value'] }}
        </div>
        {{-- Label ── --}}
        <div style="font-size:12px;font-weight:500;color:#1e0a2c;margin-bottom:3px;">{{ $kpi['label'] }}</div>
        {{-- Insight ── --}}
        <div style="font-size:11px;color:#9a85aa;font-weight:400;">{{ $kpi['insight'] }}</div>
        @if(!empty($kpi['alert']))
        <div style="position:absolute;top:10px;right:10px;width:7px;height:7px;background:{{ $kpi['color'] }};border-radius:50%;"></div>
        @endif
    </div>
    @endforeach
</div>

{{-- ════════════════════════════════════════════════════════════
     2. QUICK ACTIONS
════════════════════════════════════════════════════════════ --}}
<div class="inv-qa-grid">
    @php
    $quickActions = [
        ['label' => 'Stock In',         'href' => route('inventory.stock-in'),        'icon' => 'M12 5v14M5 12l7-7 7 7', 'color' => '#1a7a45'],
        ['label' => 'Stock Out',        'href' => route('inventory.stock-out'),       'icon' => 'M12 19V5M5 12l7 7 7-7', 'color' => '#b52020'],
        ['label' => 'Purchase Order',   'href' => route('inventory.purchase'),        'icon' => 'M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 2 2h2a2 2 0 0 0 2-2M9 5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2M12 12h.01', 'color' => '#1a5ea8'],
        ['label' => 'Add Item',         'href' => route('inventory.items'),           'icon' => 'M12 5v14M5 12h14', 'color' => '#6a0f70'],
        ['label' => 'Reusable Assets',  'href' => route('inventory.reusable-assets'), 'icon' => 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z', 'color' => '#0e7b89'],
        ['label' => 'Check Expiry',     'href' => route('inventory.expiry'),          'icon' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01', 'color' => '#a05c00'],
        ['label' => 'Reports',          'href' => '#inv-reports',                     'icon' => 'M18 20V10M12 20V4M6 20v-6', 'color' => '#555'],
    ];
    @endphp

    @foreach($quickActions as $qa)
    <a href="{{ $qa['href'] }}" class="inv-qa-btn">
        <div style="width:38px;height:38px;background:{{ $qa['color'] }}11;border-radius:50%;display:flex;align-items:center;justify-content:center;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"
                 stroke="{{ $qa['color'] }}" stroke-width="1.75"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="{{ $qa['icon'] }}"/>
            </svg>
        </div>
        {{ $qa['label'] }}
    </a>
    @endforeach
</div>

{{-- ════════════════════════════════════════════════════════════
     3. MIDDLE ROW: Stock Status Donut + Category Values + Value Trend
════════════════════════════════════════════════════════════ --}}
<div class="inv-section-grid-3" style="margin-bottom:16px;">

    {{-- ── Stock Status Donut ── --}}
    <div class="inv-card">
        <div class="inv-card-head">
            <span class="inv-card-title">Stock Status</span>
            <span style="font-size:11px;color:#9a85aa;">All locations</span>
        </div>
        <div class="inv-card-body" style="display:flex;align-items:center;gap:20px;">
            <div style="position:relative;width:110px;height:110px;flex-shrink:0;">
                <canvas id="stockDonut" width="110" height="110"></canvas>
                @php $total = array_sum(array_column($stockStatus, 'value')); @endphp
                <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;pointer-events:none;">
                    <div style="font-size:18px;font-weight:700;color:#1e0a2c;font-family:'DM Mono',monospace;">{{ $total }}</div>
                    <div style="font-size:10px;color:#9a85aa;">items</div>
                </div>
            </div>
            <div style="flex:1;">
                @foreach($stockStatus as $s)
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <div style="display:flex;align-items:center;gap:6px;">
                        <div style="width:8px;height:8px;border-radius:50%;background:{{ $s['color'] }};flex-shrink:0;"></div>
                        <span style="font-size:12px;color:#4e2060;">{{ $s['label'] }}</span>
                    </div>
                    <span style="font-size:12px;font-weight:600;color:#1e0a2c;font-family:'DM Mono',monospace;">{{ $s['value'] }}</span>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── Category Values Bars ── --}}
    <div class="inv-card">
        <div class="inv-card-head">
            <span class="inv-card-title">Value by Category</span>
            <span style="font-size:11px;color:#9a85aa;">Top 6</span>
        </div>
        <div class="inv-card-body" style="padding-top:12px;">
            @forelse($categoryValues as $cv)
            <div class="inv-bar-row">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:12px;color:#2e1040;font-weight:500;">{{ $cv['category'] }}</span>
                    <span style="font-size:11px;color:#9a85aa;font-family:'DM Mono',monospace;">{{ $cv['label'] }}</span>
                </div>
                <div class="inv-bar-track">
                    <div class="inv-bar-fill" style="width:{{ $cv['pct'] }}%;background:{{ $cv['color'] }};"></div>
                </div>
            </div>
            @empty
            <div style="text-align:center;padding:24px 0;color:#9a85aa;font-size:12px;">
                No inventory data yet. Add items to see category values.
            </div>
            @endforelse
        </div>
    </div>

    {{-- ── Value Trend Line ── --}}
    <div class="inv-card">
        <div class="inv-card-head">
            <span class="inv-card-title">Purchase Trend</span>
            <span style="font-size:11px;color:#9a85aa;">Last 8 weeks</span>
        </div>
        <div class="inv-card-body" style="padding-top:8px;padding-bottom:8px;">
            <canvas id="valueTrendChart" height="130"></canvas>
        </div>
    </div>

</div>

{{-- ════════════════════════════════════════════════════════════
     4. BOTTOM ROW: Critical Items + Expiring Soon
════════════════════════════════════════════════════════════ --}}
<div class="inv-section-grid-2" style="margin-bottom:16px;">

    {{-- ── Critical Items ── --}}
    <div class="inv-card">
        <div class="inv-card-head">
            <span class="inv-card-title">Critical Items</span>
            <a href="{{ route('inventory.items') }}" style="font-size:11px;color:#6a0f70;text-decoration:none;">View all →</a>
        </div>
        @if($criticalItems->isEmpty())
        <div class="inv-card-body" style="text-align:center;padding:32px;color:#9a85aa;font-size:13px;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="rgba(106,15,112,0.25)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 10px;display:block;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            All items are well-stocked
        </div>
        @else
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="background:#faf5fb;">
                        <th style="padding:8px 18px;text-align:left;font-weight:600;font-size:10.5px;letter-spacing:0.06em;text-transform:uppercase;color:#9a85aa;border-bottom:1px solid rgba(185,92,183,0.07);">Item</th>
                        <th style="padding:8px 14px;text-align:center;font-weight:600;font-size:10.5px;letter-spacing:0.06em;text-transform:uppercase;color:#9a85aa;border-bottom:1px solid rgba(185,92,183,0.07);">In Stock</th>
                        <th style="padding:8px 14px;text-align:center;font-weight:600;font-size:10.5px;letter-spacing:0.06em;text-transform:uppercase;color:#9a85aa;border-bottom:1px solid rgba(185,92,183,0.07);">Min</th>
                        <th style="padding:8px 18px;text-align:left;font-weight:600;font-size:10.5px;letter-spacing:0.06em;text-transform:uppercase;color:#9a85aa;border-bottom:1px solid rgba(185,92,183,0.07);">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($criticalItems as $item)
                    <tr style="border-bottom:1px solid rgba(185,92,183,0.05);transition:background 120ms;" onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background=''">
                        <td style="padding:10px 18px;">
                            <div style="font-weight:500;color:#1e0a2c;">{{ Str::limit($item->product_name, 28) }}</div>
                            @if($item->category)
                            <div style="font-size:11px;color:#9a85aa;margin-top:1px;">{{ $item->category->name }}</div>
                            @endif
                        </td>
                        <td style="padding:10px 14px;text-align:center;font-family:'DM Mono',monospace;font-weight:600;color:{{ $item->total_stock <= 0 ? '#b52020' : '#a05c00' }};">
                            {{ number_format($item->total_stock, 0) }}
                        </td>
                        <td style="padding:10px 14px;text-align:center;font-family:'DM Mono',monospace;color:#9a85aa;">
                            {{ number_format($item->minimum_qty, 0) }}
                        </td>
                        <td style="padding:10px 18px;">
                            @if($item->total_stock <= 0)
                            <span class="inv-status-badge" style="background:#fdeaea;color:#b52020;">
                                <span style="width:5px;height:5px;background:#b52020;border-radius:50%;"></span>Out of Stock
                            </span>
                            @else
                            <span class="inv-status-badge" style="background:#fff4e0;color:#a05c00;">
                                <span style="width:5px;height:5px;background:#a05c00;border-radius:50%;"></span>Low Stock
                            </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

    {{-- ── Expiring Soon ── --}}
    <div class="inv-card">
        <div class="inv-card-head">
            <span class="inv-card-title">Expiring Soon</span>
            <a href="{{ route('inventory.expiry') }}" style="font-size:11px;color:#6a0f70;text-decoration:none;">View all →</a>
        </div>
        @if($expiringSoon->isEmpty())
        <div class="inv-card-body" style="text-align:center;padding:32px;color:#9a85aa;font-size:13px;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="rgba(106,15,112,0.25)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin:0 auto 10px;display:block;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            No items expiring in the next 90 days
        </div>
        @else
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead>
                    <tr style="background:#faf5fb;">
                        <th style="padding:8px 18px;text-align:left;font-weight:600;font-size:10.5px;letter-spacing:0.06em;text-transform:uppercase;color:#9a85aa;border-bottom:1px solid rgba(185,92,183,0.07);">Item</th>
                        <th style="padding:8px 14px;text-align:left;font-weight:600;font-size:10.5px;letter-spacing:0.06em;text-transform:uppercase;color:#9a85aa;border-bottom:1px solid rgba(185,92,183,0.07);">Batch</th>
                        <th style="padding:8px 18px;text-align:left;font-weight:600;font-size:10.5px;letter-spacing:0.06em;text-transform:uppercase;color:#9a85aa;border-bottom:1px solid rgba(185,92,183,0.07);">Expiry</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expiringSoon as $exp)
                    @php
                        $daysLeft = now()->diffInDays($exp->expiry_date, false);
                        $urgencyClass = $daysLeft <= 14 ? 'urgency-high' : ($daysLeft <= 30 ? 'urgency-medium' : 'urgency-low');
                        $urgencyBg    = $daysLeft <= 14 ? '#fdeaea' : ($daysLeft <= 30 ? '#fff4e0' : '#e8f7ef');
                    @endphp
                    <tr style="border-bottom:1px solid rgba(185,92,183,0.05);transition:background 120ms;" onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background=''">
                        <td style="padding:10px 18px;">
                            <div style="font-weight:500;color:#1e0a2c;">{{ $exp->item ? Str::limit($exp->item->product_name, 26) : '—' }}</div>
                            <div style="font-size:11px;color:#9a85aa;">{{ $exp->toLocation?->name ?? 'Main Store' }}</div>
                        </td>
                        <td style="padding:10px 14px;font-family:'DM Mono',monospace;font-size:11.5px;color:#4e2060;">
                            {{ $exp->batch_no ?: '—' }}
                        </td>
                        <td style="padding:10px 18px;">
                            <span class="inv-status-badge {{ $urgencyClass }}" style="background:{{ $urgencyBg }};">
                                {{ $exp->expiry_date ? $exp->expiry_date->format('d M Y') : '—' }}
                                <span style="font-size:10px;opacity:0.75;">({{ $daysLeft }}d)</span>
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
    </div>

</div>

{{-- ════════════════════════════════════════════════════════════
     5. RECENT MOVEMENTS FEED
════════════════════════════════════════════════════════════ --}}
<div class="inv-card" style="margin-bottom:16px;">
    <div class="inv-card-head">
        <span class="inv-card-title">Recent Stock Movements</span>
        <span style="font-size:11px;color:#9a85aa;">Last 12 events</span>
    </div>
    @if($recentMovements->isEmpty())
    <div class="inv-card-body" style="text-align:center;padding:32px;color:#9a85aa;font-size:13px;">
        No stock movements yet. Use Stock In or Stock Out to record your first movement.
    </div>
    @else
    <div class="inv-movement-feed">
        @foreach($recentMovements as $mv)
        @php
            $typeColors = [
                'stock_in'        => ['bg' => '#e8f7ef', 'color' => '#1a7a45', 'icon' => 'M12 5v14M5 12l7-7 7 7'],
                'opening_stock'   => ['bg' => '#e8f7ef', 'color' => '#1a7a45', 'icon' => 'M12 5v14M5 12l7-7 7 7'],
                'stock_out'       => ['bg' => '#f9f3fa', 'color' => '#6a0f70', 'icon' => 'M12 19V5M5 12l7 7 7-7'],
                'treatment_usage' => ['bg' => '#f9f3fa', 'color' => '#6a0f70', 'icon' => 'M12 19V5M5 12l7 7 7-7'],
                'transfer'        => ['bg' => '#e6f0fb', 'color' => '#1a5ea8', 'icon' => 'M7 16V4m0 0L3 8m4-4l4 4M17 8v12m0 0l4-4m-4 4l-4-4'],
                'adjustment'      => ['bg' => '#fff4e0', 'color' => '#a05c00', 'icon' => 'M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z'],
                'expired'         => ['bg' => '#fdeaea', 'color' => '#b52020', 'icon' => 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01'],
                'damaged'         => ['bg' => '#fdeaea', 'color' => '#b52020', 'icon' => 'M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z'],
                'sterilization'   => ['bg' => '#e0f7f9', 'color' => '#0e7b89', 'icon' => 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z'],
                'maintenance'     => ['bg' => '#f9f3fa', 'color' => '#6a0f70', 'icon' => 'M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z'],
            ];
            $tc = $typeColors[$mv->movement_type] ?? ['bg' => '#f5f5f5', 'color' => '#555', 'icon' => 'M12 5v14'];
        @endphp
        <div class="inv-movement-item">
            {{-- Type icon ── --}}
            <div style="width:32px;height:32px;background:{{ $tc['bg'] }};border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:1px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="{{ $tc['color'] }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="{{ $tc['icon'] }}"/>
                </svg>
            </div>
            {{-- Details ── --}}
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <span style="font-size:13px;font-weight:500;color:#1e0a2c;">
                        {{ $mv->item ? Str::limit($mv->item->product_name, 35) : 'Unknown Item' }}
                    </span>
                    <span class="inv-status-badge" style="background:{{ $tc['bg'] }};color:{{ $tc['color'] }};">
                        {{ $mv->getMovementLabel() }}
                    </span>
                </div>
                <div style="font-size:11.5px;color:#9a85aa;margin-top:3px;">
                    @if($mv->movement_type === 'transfer')
                        {{ $mv->fromLocation?->name ?? '—' }} → {{ $mv->toLocation?->name ?? '—' }}
                    @elseif($mv->toLocation)
                        {{ $mv->toLocation->name }}
                    @elseif($mv->fromLocation)
                        {{ $mv->fromLocation->name }}
                    @endif
                    @if($mv->batch_no) · Batch: {{ $mv->batch_no }} @endif
                    @if($mv->createdBy) · {{ $mv->createdBy->name }} @endif
                </div>
            </div>
            {{-- Qty + time ── --}}
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-family:'DM Mono',monospace;font-size:14px;font-weight:600;color:{{ $tc['color'] }};">
                    {{ $mv->qty > 0 ? '+' : '' }}{{ number_format($mv->qty, 1) }}
                </div>
                <div style="font-size:11px;color:#c0b0cc;margin-top:2px;">{{ $mv->created_at->diffForHumans() }}</div>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>

{{-- ════════════════════════════════════════════════════════════
     6. FOOTER OPERATIONAL STATS STRIP
════════════════════════════════════════════════════════════ --}}
<div class="inv-footer-strip">
    @foreach($footerStats as $stat)
    <div class="inv-footer-stat">
        <div style="font-family:'DM Mono',monospace;font-size:18px;font-weight:700;color:#1e0a2c;line-height:1;">{{ $stat['value'] }}</div>
        <div style="font-size:11px;color:#9a85aa;margin-top:4px;font-weight:400;">{{ $stat['label'] }}</div>
    </div>
    @endforeach
</div>

{{-- ════════════════════════════════════════════════════════════
     7. INLINE REPORTS — anchor #inv-reports
     Clicking the "Reports" quick-action or KPI cards scrolls here.
════════════════════════════════════════════════════════════ --}}
<div id="inv-reports" style="margin-top:32px;scroll-margin-top:80px;">

    {{-- Section heading ── --}}
    <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
        <div style="height:1px;flex:1;background:rgba(185,92,183,0.12);"></div>
        <span style="font-family:'DM Sans',sans-serif;font-size:11px;font-weight:600;
                     text-transform:uppercase;letter-spacing:0.08em;color:#9a85aa;">
            Reports
        </span>
        <div style="height:1px;flex:1;background:rgba(185,92,183,0.12);"></div>
    </div>

    @php
        use Illuminate\Support\Facades\DB;

        // Stock valuation by category
        $rptCatVal = DB::table('inventory_items as i')
            ->join('inventory_stocks as s','i.id','=','s.inventory_item_id')
            ->join('inventory_categories as c','i.category_id','=','c.id')
            ->where('i.is_active', true)
            ->select('c.name','c.color',
                DB::raw('SUM(s.available_qty * i.average_purchase_price) as value'),
                DB::raw('COUNT(DISTINCT i.id) as item_count'))
            ->groupBy('c.id','c.name','c.color')
            ->orderByDesc('value')
            ->get();
        $rptTotalVal = $rptCatVal->sum('value');

        // Top 8 consumed items last 30 days
        $rptTopConsumed = DB::table('stock_movements as m')
            ->join('inventory_items as i','m.inventory_item_id','=','i.id')
            ->whereIn('m.movement_type',['stock_out','treatment_usage'])
            ->where('m.created_at','>=',now()->subDays(30))
            ->select('i.product_name',
                DB::raw('SUM(ABS(m.qty)) as total_qty'),
                DB::raw('SUM(ABS(m.total_cost)) as total_cost'),
                'i.consumption_unit')
            ->groupBy('i.id','i.product_name','i.consumption_unit')
            ->orderByDesc('total_qty')
            ->limit(8)
            ->get();

        // Movement summary last 30 days
        $rptMvtSummary = DB::table('stock_movements')
            ->where('created_at','>=',now()->subDays(30))
            ->select('movement_type',
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(ABS(total_cost)) as total_cost'))
            ->groupBy('movement_type')
            ->get()
            ->keyBy('movement_type');

        $mvtIn  = ($rptMvtSummary->get('stock_in') ?? (object)['count'=>0,'total_cost'=>0]);
        $mvtOut = ($rptMvtSummary->get('stock_out') ?? (object)['count'=>0,'total_cost'=>0]);
    @endphp

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

        {{-- ── Report 1: Stock Valuation by Category ── --}}
        <div style="background:#fff;border:1px solid rgba(185,92,183,0.10);border-radius:4px;overflow:hidden;">
            <div style="background:#faf5fb;padding:12px 18px;border-bottom:1px solid rgba(185,92,183,0.08);
                        display:flex;align-items:center;justify-content:space-between;">
                <span style="font-family:'DM Sans',sans-serif;font-size:12.5px;font-weight:600;
                             text-transform:uppercase;letter-spacing:0.04em;color:#4e0a53;">
                    Stock Valuation by Category
                </span>
                <span style="font-family:'DM Mono',monospace;font-size:13px;font-weight:700;color:#6a0f70;">
                    ₹{{ number_format($rptTotalVal, 0) }}
                </span>
            </div>
            <div style="padding:14px 18px;">
                @forelse($rptCatVal as $cv)
                @php $pct = $rptTotalVal > 0 ? round(($cv->value / $rptTotalVal) * 100, 1) : 0; @endphp
                <div style="margin-bottom:11px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <div style="display:flex;align-items:center;gap:7px;">
                            <span style="width:8px;height:8px;border-radius:50%;
                                         background:{{ $cv->color ?: '#9070a0' }};flex-shrink:0;display:inline-block;"></span>
                            <span style="font-family:'DM Sans',sans-serif;font-size:12.5px;color:#2e1040;">{{ $cv->name }}</span>
                            <span style="font-family:'DM Sans',sans-serif;font-size:11px;color:#c0b0cc;">
                                {{ $cv->item_count }} items
                            </span>
                        </div>
                        <span style="font-family:'DM Mono',monospace;font-size:12px;color:#4e0a53;font-weight:600;">
                            ₹{{ number_format($cv->value, 0) }}
                            <span style="font-weight:400;color:#c0b0cc;font-size:11px;"> {{ $pct }}%</span>
                        </span>
                    </div>
                    <div style="height:5px;background:rgba(185,92,183,0.08);border-radius:3px;">
                        <div style="height:100%;width:{{ $pct }}%;background:{{ $cv->color ?: '#9070a0' }};border-radius:3px;"></div>
                    </div>
                </div>
                @empty
                <p style="text-align:center;color:#c0b0cc;font-size:13px;padding:20px 0;">No stock data yet.</p>
                @endforelse
            </div>
        </div>

        {{-- ── Report 2: Top Consumed Items (30 days) ── --}}
        <div style="background:#fff;border:1px solid rgba(185,92,183,0.10);border-radius:4px;overflow:hidden;">
            <div style="background:#faf5fb;padding:12px 18px;border-bottom:1px solid rgba(185,92,183,0.08);
                        display:flex;align-items:center;justify-content:space-between;">
                <span style="font-family:'DM Sans',sans-serif;font-size:12.5px;font-weight:600;
                             text-transform:uppercase;letter-spacing:0.04em;color:#4e0a53;">
                    Top Consumed — Last 30 Days
                </span>
                <span style="font-size:11px;color:#9a85aa;font-family:'DM Sans',sans-serif;">
                    Stock Out + Treatment Usage
                </span>
            </div>
            <div style="padding:0;">
                @forelse($rptTopConsumed as $tc)
                <div style="display:flex;align-items:center;justify-content:space-between;
                            padding:9px 18px;border-bottom:1px solid rgba(185,92,183,0.05);">
                    <div style="flex:1;min-width:0;">
                        <div style="font-family:'DM Sans',sans-serif;font-size:13px;color:#1e0a2c;
                                    white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ $tc->product_name }}
                        </div>
                        @if($tc->total_cost > 0)
                        <div style="font-size:11px;color:#9a85aa;">
                            ₹{{ number_format($tc->total_cost, 0) }} total value used
                        </div>
                        @endif
                    </div>
                    <div style="text-align:right;flex-shrink:0;margin-left:12px;">
                        <span style="font-family:'DM Mono',monospace;font-size:14px;font-weight:700;color:#b52020;">
                            {{ number_format($tc->total_qty, 0) }}
                        </span>
                        <span style="font-size:11px;color:#9a85aa;"> {{ $tc->consumption_unit ?: 'units' }}</span>
                    </div>
                </div>
                @empty
                <p style="text-align:center;color:#c0b0cc;font-size:13px;padding:24px;">
                    No stock-out movements in the last 30 days.
                </p>
                @endforelse
            </div>
        </div>

    </div>

    {{-- ── Report 3: Movement summary strip ── --}}
    <div style="background:#fff;border:1px solid rgba(185,92,183,0.10);border-radius:4px;
                padding:14px 20px;display:flex;gap:32px;flex-wrap:wrap;align-items:center;">
        <span style="font-family:'DM Sans',sans-serif;font-size:12px;font-weight:600;
                     color:#9a85aa;text-transform:uppercase;letter-spacing:0.05em;">Last 30 days</span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="width:8px;height:8px;background:#1a7a45;border-radius:50%;display:inline-block;"></span>
            <span style="font-family:'DM Sans',sans-serif;font-size:13px;color:#2e1040;">
                Stock In: <strong style="font-family:'DM Mono',monospace;">{{ $mvtIn->count }}</strong> entries
                @if($mvtIn->total_cost > 0)
                    · ₹{{ number_format($mvtIn->total_cost, 0) }}
                @endif
            </span>
        </div>
        <div style="display:flex;align-items:center;gap:8px;">
            <span style="width:8px;height:8px;background:#b52020;border-radius:50%;display:inline-block;"></span>
            <span style="font-family:'DM Sans',sans-serif;font-size:13px;color:#2e1040;">
                Stock Out: <strong style="font-family:'DM Mono',monospace;">{{ $mvtOut->count }}</strong> entries
                @if($mvtOut->total_cost > 0)
                    · ₹{{ number_format($mvtOut->total_cost, 0) }}
                @endif
            </span>
        </div>
        <a href="{{ route('inventory.reports') }}"
           style="margin-left:auto;font-family:'DM Sans',sans-serif;font-size:12px;
                  color:#6a0f70;text-decoration:none;font-weight:500;">
            Full Report →
        </a>
    </div>

</div>

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {

    /* ── Stock Status Donut ── */
    const donutCtx = document.getElementById('stockDonut');
    if (donutCtx) {
        const statusData = @json($stockStatus);
        new Chart(donutCtx, {
            type: 'doughnut',
            data: {
                labels: statusData.map(s => s.label),
                datasets: [{
                    data: statusData.map(s => s.value || 0),
                    backgroundColor: statusData.map(s => s.color),
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverBorderWidth: 0,
                }]
            },
            options: {
                responsive: false,
                cutout: '68%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ' + ctx.label + ': ' + ctx.raw + ' items'
                        }
                    }
                },
                animation: { animateRotate: true, duration: 700 },
            }
        });
    }

    /* ── Value Trend Line ── */
    const trendCtx = document.getElementById('valueTrendChart');
    if (trendCtx) {
        const trendData = @json($valueTrend);
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: trendData.map(d => d.label),
                datasets: [{
                    data: trendData.map(d => d.value),
                    borderColor: '#6a0f70',
                    backgroundColor: 'rgba(106,15,112,0.06)',
                    borderWidth: 2,
                    pointRadius: 3,
                    pointBackgroundColor: '#6a0f70',
                    pointHoverRadius: 5,
                    fill: true,
                    tension: 0.4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ' ₹' + ctx.raw.toLocaleString('en-IN')
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: {
                            font: { size: 10, family: 'DM Sans' },
                            color: '#9a85aa',
                        },
                        border: { display: false }
                    },
                    y: {
                        grid: { color: 'rgba(185,92,183,0.06)', drawTicks: false },
                        ticks: {
                            font: { size: 10, family: 'DM Mono' },
                            color: '#9a85aa',
                            callback: v => v === 0 ? '0' : '₹' + (v >= 1000 ? (v/1000).toFixed(0)+'k' : v),
                        },
                        border: { display: false }
                    }
                }
            }
        });
    }

});
</script>
@endpush
