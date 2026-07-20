{{--
|==========================================================================
| Inventory OS — Alerts Hub
| Phase 1: Functional stub. Full redesign in Phase 4.
| Shows: Critical Stock · Low Stock · Expiring · Expired · Dead Stock · Pending Deliveries
|==========================================================================
--}}
@extends('layouts.app')

@section('page-title', 'Inventory Alerts')

@section('head-extra')
<style>
    .alerts-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
        gap: 12px;
        margin-bottom: 16px;
    }

    .alert-kpi {
        background: #fff;
        border: 1px solid #ede4f0;
        border-radius: 8px;
        padding: 14px 16px;
        text-align: center;
    }
    .alert-kpi .kpi-num {
        font-size: 28px;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 4px;
    }
    .alert-kpi .kpi-label {
        font-size: 11.5px;
        color: #7a6884;
        font-weight: 500;
    }
    .kpi-red   { color: #dc2626; }
    .kpi-amber { color: #d97706; }
    .kpi-blue  { color: #2563eb; }
    .kpi-grey  { color: #6b7280; }

    .alert-section {
        background: #fff;
        border: 1px solid #ede4f0;
        border-radius: 8px;
        margin-bottom: 20px;
        overflow: hidden;
    }
    .alert-section-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 18px;
        border-bottom: 1px solid #f0e8f4;
        background: #faf5ff;
    }
    .alert-section-header h3 {
        font-size: 13px;
        font-weight: 600;
        color: #3a2a4a;
        margin: 0;
    }
    .badge-count {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 22px;
        height: 22px;
        padding: 0 6px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
    }
    .badge-red   { background: #fee2e2; color: #b91c1c; }
    .badge-amber { background: #fef3c7; color: #92400e; }
    .badge-blue  { background: #dbeafe; color: #1d4ed8; }
    .badge-grey  { background: #f3f4f6; color: #374151; }
    .badge-ok    { background: #dcfce7; color: #15803d; }

    .alert-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .alert-table th {
        background: #f9f5fb;
        color: #6a0f70;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 8px 14px;
        text-align: left;
        border-bottom: 1px solid #ede4f0;
    }
    .alert-table td {
        padding: 10px 14px;
        border-bottom: 1px solid #f5f0f8;
        color: #3a2a4a;
        vertical-align: middle;
    }
    .alert-table tr:last-child td { border-bottom: none; }
    .alert-table tr:hover td { background: #faf5ff; }

    .status-dot {
        display: inline-block;
        width: 8px; height: 8px;
        border-radius: 50%;
        margin-right: 6px;
    }
    .dot-red   { background: #ef4444; }
    .dot-amber { background: #f59e0b; }
    .dot-green { background: #22c55e; }

    .empty-state {
        padding: 28px;
        text-align: center;
        color: #9a85aa;
        font-size: 13px;
    }
    .empty-state svg { margin-bottom: 8px; opacity: 0.4; }

    .action-btn {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11.5px;
        font-weight: 500;
        text-decoration: none;
        border: 1px solid transparent;
        cursor: pointer;
        transition: background 100ms;
    }
    .btn-ghost {
        background: #f3e8ff;
        color: #6a0f70;
        border-color: #e0d0ea;
    }
    .btn-ghost:hover { background: #e9d5f5; color: #4e0a53; }

    .expiry-tag {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 600;
    }
    .expiry-expired { background: #fee2e2; color: #b91c1c; }
    .expiry-critical{ background: #fef3c7; color: #92400e; }
    .expiry-warning { background: #fff7ed; color: #c2410c; }
    .expiry-ok      { background: #f0fdf4; color: #15803d; }
</style>
@endsection

@section('content')

<div class="df-page-header">
    <div>
        <div class="df-page-title" style="font-size:22px;">Inventory</div>
        <div class="df-page-subtitle">Alerts · Items that need your attention today</div>
    </div>
    <div class="df-page-actions">
    <a href="{{ route('inventory.index') }}" class="action-btn btn-ghost" style="font-size:12px;">
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
        </svg>
        Dashboard
    </a>
    </div>
</div>

@include('inventory.partials.subnav')

{{-- ── Summary KPI Bar ── --}}
<div class="alerts-summary-grid">
    <div class="alert-kpi">
        <div class="kpi-num kpi-red">{{ $summary['out'] }}</div>
        <div class="kpi-label">Out of Stock</div>
    </div>
    <div class="alert-kpi">
        <div class="kpi-num" style="color:#d97706;">{{ $summary['critical'] }}</div>
        <div class="kpi-label">Critical Stock</div>
    </div>
    <div class="alert-kpi">
        <div class="kpi-num kpi-amber">{{ $summary['low'] }}</div>
        <div class="kpi-label">Low Stock</div>
    </div>
    <div class="alert-kpi">
        <div class="kpi-num kpi-red">{{ $summary['expired'] }}</div>
        <div class="kpi-label">Expired Items</div>
    </div>
    <div class="alert-kpi">
        <div class="kpi-num kpi-amber">{{ $summary['expiring'] }}</div>
        <div class="kpi-label">Expiring Soon</div>
    </div>
    <div class="alert-kpi">
        <div class="kpi-num kpi-grey">{{ $summary['dead'] }}</div>
        <div class="kpi-label">Dead Stock</div>
    </div>
    <div class="alert-kpi">
        <div class="kpi-num kpi-blue">{{ $summary['pending'] }}</div>
        <div class="kpi-label">Pending Deliveries</div>
    </div>
</div>

{{-- ── Minimum Stock Not Set — informational nudge ── --}}
@if(($summary['min_not_set'] ?? 0) > 0)
<div style="display:flex;align-items:center;gap:10px;background:#f3f0f6;border:1px solid #e2dae9;border-radius:8px;padding:12px 16px;margin-bottom:24px;font-size:13px;color:#5a4a6a;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7a6884" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
    </svg>
    <span><strong>{{ $summary['min_not_set'] }}</strong> item{{ $summary['min_not_set'] === 1 ? '' : 's' }} don't have a Minimum Stock level configured. Set one to enable stock alerts for {{ $summary['min_not_set'] === 1 ? 'it' : 'them' }}.</span>
    <a href="{{ route('inventory.products') }}" class="action-btn btn-ghost" style="margin-left:auto;flex-shrink:0;">Review Items</a>
</div>
@endif

{{-- ═══════ SECTION 1: OUT OF STOCK ═══════ --}}
<div class="alert-section">
    <div class="alert-section-header">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
        </svg>
        <h3>Out of Stock</h3>
        <span class="badge-count badge-red">{{ $summary['out'] }}</span>
        <span style="font-size:11.5px;color:#9a85aa;margin-left:auto;">Immediate action required</span>
    </div>
    @if($outOfStock->isEmpty())
        <div class="empty-state">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div>Nothing is out of stock — great!</div>
        </div>
    @else
        <table class="alert-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>On Hand</th>
                    <th>Minimum Stock</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($outOfStock as $item)
                <tr>
                    <td>
                        <span class="status-dot dot-red"></span>
                        <strong>{{ $item->product_name }}</strong>
                    </td>
                    <td style="color:#b91c1c;font-weight:600;">0 <span style="color:#9a85aa;font-size:11px;font-weight:400;">{{ $item->consumption_unit }}</span></td>
                    <td style="color:#7a6884;">{{ rtrim(rtrim(number_format($item->minimum_qty, 2), '0'), '.') }}</td>
                    <td>
                        <a href="{{ route('inventory.purchase') }}" class="action-btn btn-ghost">
                            Order Now
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ═══════ SECTION 1b: CRITICAL STOCK ═══════ --}}
<div class="alert-section">
    <div class="alert-section-header">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <h3>Critical Stock</h3>
        <span class="badge-count badge-amber">{{ $summary['critical'] }}</span>
        <span style="font-size:11.5px;color:#9a85aa;margin-left:auto;">Very low — reorder today</span>
    </div>
    @if($criticalStock->isEmpty())
        <div class="empty-state">
            <div>Nothing critically low</div>
        </div>
    @else
        <table class="alert-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>On Hand</th>
                    <th>Minimum Stock</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($criticalStock as $item)
                <tr>
                    <td><span class="status-dot dot-amber"></span><strong>{{ $item->product_name }}</strong></td>
                    <td><strong style="color:#d97706;">{{ rtrim(rtrim(number_format($item->on_hand, 2), '0'), '.') }}</strong> <span style="color:#9a85aa;font-size:11px;">{{ $item->consumption_unit }}</span></td>
                    <td style="color:#7a6884;">{{ rtrim(rtrim(number_format($item->minimum_qty, 2), '0'), '.') }}</td>
                    <td><a href="{{ route('inventory.purchase') }}" class="action-btn btn-ghost">Order Now</a></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ═══════ SECTION 2: LOW STOCK ═══════ --}}
<div class="alert-section">
    <div class="alert-section-header">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
            <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <h3>Low Stock</h3>
        <span class="badge-count badge-amber">{{ $summary['low'] }}</span>
        <span style="font-size:11.5px;color:#9a85aa;margin-left:auto;">Below minimum level</span>
    </div>
    @if($lowStock->isEmpty())
        <div class="empty-state">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div>All items are above minimum levels</div>
        </div>
    @else
        <table class="alert-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>On Hand</th>
                    <th>Minimum Stock</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($lowStock as $item)
                <tr>
                    <td>
                        <span class="status-dot dot-amber"></span>
                        {{ $item->product_name }}
                    </td>
                    <td>
                        <strong style="color:#d97706;">{{ rtrim(rtrim(number_format($item->on_hand, 2), '0'), '.') }}</strong>
                        <span style="color:#9a85aa;font-size:11px;"> {{ $item->consumption_unit }}</span>
                    </td>
                    <td style="color:#7a6884;">{{ rtrim(rtrim(number_format($item->minimum_qty, 2), '0'), '.') }}</td>
                    <td>
                        <a href="{{ route('inventory.purchase') }}" class="action-btn btn-ghost">
                            Order
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ═══════ SECTION 3: EXPIRED ═══════ --}}
@if($expiredItems->isNotEmpty())
<div class="alert-section">
    <div class="alert-section-header">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
        </svg>
        <h3>Expired Items</h3>
        <span class="badge-count badge-red">{{ $summary['expired'] }}</span>
        <span style="font-size:11.5px;color:#dc2626;font-weight:600;margin-left:auto;">Remove from stock immediately</span>
    </div>
    <table class="alert-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Batch</th>
                <th>Expired On</th>
                <th>Qty Remaining</th>
                <th>Location</th>
            </tr>
        </thead>
        <tbody>
            @foreach($expiredItems as $movement)
            <tr>
                <td><strong>{{ $movement->item?->product_name ?? '—' }}</strong></td>
                <td style="color:#7a6884;font-size:12px;">{{ $movement->batch_no ?? '—' }}</td>
                <td>
                    <span class="expiry-tag expiry-expired">
                        {{ \Carbon\Carbon::parse($movement->expiry_date)->format('d M Y') }}
                    </span>
                </td>
                <td><strong style="color:#dc2626;">{{ $movement->qty }} {{ $movement->item?->consumption_unit }}</strong></td>
                <td style="color:#7a6884;font-size:12px;">{{ $movement->toLocation?->name ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ═══════ SECTION 4: EXPIRING SOON ═══════ --}}
<div class="alert-section">
    <div class="alert-section-header">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
        <h3>Expiring Soon</h3>
        <span class="badge-count badge-amber">{{ $summary['expiring'] }}</span>
        <span style="font-size:11.5px;color:#9a85aa;margin-left:auto;">Within 90 days — use these first</span>
    </div>
    @if($expiringSoon->isEmpty())
        <div class="empty-state">
            <div>No items expiring in the next 90 days</div>
        </div>
    @else
        <table class="alert-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Batch</th>
                    <th>Expiry Date</th>
                    <th>Days Left</th>
                    <th>Qty</th>
                </tr>
            </thead>
            <tbody>
                @foreach($expiringSoon as $movement)
                    @php
                        $daysLeft = today()->diffInDays(\Carbon\Carbon::parse($movement->expiry_date), false);
                        $tagClass = $daysLeft <= 30 ? 'expiry-critical' : ($daysLeft <= 60 ? 'expiry-warning' : 'expiry-ok');
                    @endphp
                    <tr>
                        <td>{{ $movement->item?->product_name ?? '—' }}</td>
                        <td style="color:#7a6884;font-size:12px;">{{ $movement->batch_no ?? '—' }}</td>
                        <td>
                            <span class="expiry-tag {{ $tagClass }}">
                                {{ \Carbon\Carbon::parse($movement->expiry_date)->format('d M Y') }}
                            </span>
                        </td>
                        <td style="font-weight:600;color:{{ $daysLeft <= 30 ? '#b91c1c' : ($daysLeft <= 60 ? '#92400e' : '#15803d') }}">
                            {{ $daysLeft }} days
                        </td>
                        <td>{{ $movement->qty }} <span style="color:#9a85aa;font-size:11px;">{{ $movement->item?->consumption_unit }}</span></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ═══════ SECTION 5: PENDING DELIVERIES ═══════ --}}
<div class="alert-section">
    <div class="alert-section-header">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="3" width="15" height="13"/><polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
        </svg>
        <h3>Pending Deliveries</h3>
        <span class="badge-count badge-blue">{{ $summary['pending'] }}</span>
        <span style="font-size:11.5px;color:#9a85aa;margin-left:auto;">Orders waiting to arrive</span>
    </div>
    @if($pendingDeliveries->isEmpty())
        <div class="empty-state">
            <div>No pending deliveries</div>
        </div>
    @else
        <table class="alert-table">
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Vendor</th>
                    <th>Status</th>
                    <th>Expected Delivery</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @foreach($pendingDeliveries as $po)
                @php
                    $isDelayed = $po->expected_delivery_date && \Carbon\Carbon::parse($po->expected_delivery_date)->isPast();
                    $statusLabel = $po->status === 'partial' ? 'Partial' : 'Ordered';
                    $statusColor = $po->status === 'partial' ? '#92400e' : '#1d4ed8';
                    $statusBg    = $po->status === 'partial' ? '#fef3c7' : '#dbeafe';
                @endphp
                <tr>
                    <td>
                        <strong style="color:#6a0f70;">{{ $po->po_number ?? 'PO-' . str_pad($po->id, 4, '0', STR_PAD_LEFT) }}</strong>
                    </td>
                    <td>{{ $po->vendor?->name ?? '—' }}</td>
                    <td>
                        <span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;background:{{ $statusBg }};color:{{ $statusColor }};">
                            {{ $statusLabel }}
                        </span>
                        @if($isDelayed)
                            <span style="display:inline-block;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;background:#fee2e2;color:#b91c1c;margin-left:4px;">
                                Delayed
                            </span>
                        @endif
                    </td>
                    <td style="color:{{ $isDelayed ? '#dc2626' : '#3a2a4a' }};font-weight:{{ $isDelayed ? '600' : '400' }};">
                        {{ $po->expected_delivery_date ? \Carbon\Carbon::parse($po->expected_delivery_date)->format('d M Y') : '—' }}
                    </td>
                    <td>
                        <a href="{{ route('inventory.purchase') }}" class="action-btn btn-ghost">
                            View Order
                        </a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>

{{-- ═══════ SECTION 6: DEAD STOCK ═══════ --}}
@if($deadStock->isNotEmpty())
<div class="alert-section">
    <div class="alert-section-header">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6b7280" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>
        </svg>
        <h3>Dead Stock</h3>
        <span class="badge-count badge-grey">{{ $summary['dead'] }}</span>
        <span style="font-size:11.5px;color:#9a85aa;margin-left:auto;">No movement in 90+ days</span>
    </div>
    <table class="alert-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Qty in Stock</th>
                <th>Unit</th>
                <th>Value Blocked</th>
            </tr>
        </thead>
        <tbody>
            @foreach($deadStock as $item)
            <tr>
                <td>{{ $item->product_name }}</td>
                <td style="color:#6b7280;">{{ $item->total_qty }}</td>
                <td style="color:#9a85aa;font-size:12px;">{{ $item->consumption_unit ?? '—' }}</td>
                <td style="font-weight:600;color:#374151;">
                    @php $dv = ($item->total_qty ?? 0) * ($item->average_purchase_price ?? 0); @endphp
                    {{ $dv > 0 ? '₹' . number_format($dv, 0) : '—' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ═══════ Phase 4 coming soon note ═══════ --}}
<div style="background:#f9f5ff;border:1px dashed #c9a8d4;border-radius:8px;padding:14px 18px;font-size:12.5px;color:#6a4080;display:flex;align-items:center;gap:10px;">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;opacity:0.6;">
        <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
    </svg>
    <span><strong>Coming in Phase 4:</strong> Smart purchasing suggestions, one-click order creation, intelligent notification tiers, and asset service alerts will be added to this page.</span>
</div>

@endsection
