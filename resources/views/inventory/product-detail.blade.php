{{--
|===========================================================================
| Product Detail — 360 View (Phase 3)
| Stock by location · Expiry batches · Purchase history · Movement timeline
|===========================================================================
--}}
@extends('layouts.app')
@section('title', $item->product_name)

@section('head-extra')
<style>
.pd-grid{display:grid;grid-template-columns:320px 1fr;gap:20px;align-items:start;}
@media(max-width:900px){.pd-grid{grid-template-columns:1fr;}}
.pd-card{background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:10px;padding:20px;margin-bottom:16px;}
.pd-card-title{font-family:'Inter',sans-serif;font-size:11.5px;font-weight:700;letter-spacing:.06em;
               text-transform:uppercase;color:#9a85aa;margin:0 0 14px;}
.pd-stat{display:flex;flex-direction:column;align-items:center;padding:14px 10px;
         background:#faf5fb;border-radius:8px;border:1px solid rgba(185,92,183,0.10);}
.pd-stat-val{font-size:24px;font-weight:700;font-family:'Inter',sans-serif;color:#1e0a2c;line-height:1;}
.pd-stat-lbl{font-size:10.5px;color:#9a85aa;font-family:'Inter',sans-serif;margin-top:4px;text-align:center;}
.pd-tag{display:inline-flex;align-items:center;padding:3px 10px;border-radius:10px;
        font-size:11px;font-family:'Inter',sans-serif;font-weight:500;margin:2px;}
.pd-tbl{width:100%;border-collapse:collapse;font-size:12.5px;font-family:'Inter',sans-serif;}
.pd-tbl th{padding:8px 12px;text-align:left;font-size:10.5px;font-weight:700;letter-spacing:.05em;
           text-transform:uppercase;color:#9a85aa;border-bottom:1px solid rgba(185,92,183,0.10);
           background:#faf5fb;}
.pd-tbl td{padding:9px 12px;border-bottom:1px solid rgba(185,92,183,0.05);color:#1e0a2c;vertical-align:top;}
.pd-tbl tr:last-child td{border-bottom:none;}
.pd-tbl tr:hover td{background:#faf5fb;}
.pd-move-dot{width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0;}
</style>
@endsection

@section('content')
@include('inventory.partials.subnav')

@php
    $userRole = auth()->user()?->role ?? '';
    $isAdmin  = auth()->user()?->isAdmin() ?? false;
    $totalQty = $item->stocks->sum('available_qty');
    $lowStock = $totalQty <= ($item->minimum_qty ?? 0) && $totalQty > 0;
    $outStock = $totalQty <= 0;
    if ($outStock)      { $statusDot='#dc2626'; $statusTxt='Out of Stock'; $statusBg='#fdeaea'; }
    elseif ($lowStock)  { $statusDot='#d97706'; $statusTxt='Low Stock';    $statusBg='#fef3cd'; }
    else                { $statusDot='#1a7a45'; $statusTxt='In Stock';     $statusBg='#e8f7ee'; }
@endphp

{{-- ── Breadcrumb + back ── --}}
<div style="display:flex;align-items:center;gap:8px;margin-bottom:16px;font-family:'Inter',sans-serif;font-size:12.5px;color:#9a85aa;">
    <a href="{{ route('inventory.products') }}" style="color:#6a0f70;text-decoration:none;display:flex;align-items:center;gap:4px;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5"/><path d="m12 5-7 7 7 7"/></svg>
        Inventory
    </a>
    <span>›</span>
    <span style="color:#4e2060;">{{ $item->product_name }}</span>
</div>

{{-- ── Page header ── --}}
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;gap:12px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:14px;">
        {{-- Thumbnail --}}
        <div style="width:60px;height:60px;border-radius:10px;background:#f5f0f8;border:1px solid #ede5f4;
                    display:flex;align-items:center;justify-content:center;font-size:26px;overflow:hidden;flex-shrink:0;">
            @if($item->image)
                <img src="{{ asset('storage/'.$item->image) }}" style="width:100%;height:100%;object-fit:cover;">
            @else
                💊
            @endif
        </div>
        <div>
            <h1 style="font-family:'Cormorant Garamond',serif;font-size:26px;font-weight:600;color:#1a0a1e;margin:0 0 3px;">
                {{ $item->product_name }}
            </h1>
            <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                @if($item->brand)
                <span style="font-size:13px;color:#7a6884;font-family:'Inter',sans-serif;">{{ $item->brand }}</span>
                @endif
                @if($item->item_code)
                <span style="font-size:11px;color:#c0b0d0;font-family:'Inter',sans-serif;background:#faf5fb;
                             border:1px solid #ede5f4;padding:2px 8px;border-radius:4px;">{{ $item->item_code }}</span>
                @endif
                <span style="background:{{ $statusBg }};color:{{ $statusDot }};padding:3px 10px;border-radius:10px;
                             font-size:11.5px;font-weight:600;font-family:'Inter',sans-serif;
                             display:flex;align-items:center;gap:5px;">
                    <span style="width:7px;height:7px;border-radius:50%;background:{{ $statusDot }};display:inline-block;"></span>
                    {{ $statusTxt }}
                </span>
            </div>
            @if($item->category || $item->subType)
            <div style="font-size:12px;color:#9a85aa;font-family:'Inter',sans-serif;margin-top:4px;">
                {{ $item->category?->name }}@if($item->subType && $item->category) › @endif{{ $item->subType?->name }}
                @if($item->variant) · {{ $item->variant->name }}@endif
            </div>
            @endif
        </div>
    </div>
    {{-- Quick actions --}}
    <div style="display:flex;gap:8px;flex-shrink:0;flex-wrap:wrap;">
        <a href="{{ route('inventory.stock-out') }}?item={{ $item->id }}"
           style="padding:8px 16px;border:1.5px solid rgba(185,92,183,0.25);border-radius:6px;
                  font-size:13px;font-family:'Inter',sans-serif;color:#6a0f70;text-decoration:none;
                  background:#fff;font-weight:500;">
            Use Item
        </a>
        <a href="{{ route('inventory.stock-in') }}?item={{ $item->id }}"
           style="padding:8px 16px;background:#6a0f70;border:none;border-radius:6px;
                  font-size:13px;font-family:'Inter',sans-serif;color:#fff;text-decoration:none;
                  font-weight:500;">
            Receive Stock
        </a>
        @if($isAdmin)
        <a href="{{ route('inventory.purchase') }}?item={{ $item->id }}"
           style="padding:8px 16px;border:1.5px solid rgba(26,122,69,0.25);border-radius:6px;
                  font-size:13px;font-family:'Inter',sans-serif;color:#1a7a45;text-decoration:none;
                  background:#f0fdf4;font-weight:500;">
            Create Order
        </a>
        @endif
    </div>
</div>

{{-- ── Main layout: sidebar + content ── --}}
<div class="pd-grid">

    {{-- ════ LEFT SIDEBAR ════ --}}
    <div>

        {{-- Stock summary ── --}}
        <div class="pd-card">
            <div class="pd-card-title">Current Stock</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                <div class="pd-stat">
                    <span class="pd-stat-val" style="color:{{ $statusDot }};">{{ (int)$totalQty }}</span>
                    <span class="pd-stat-lbl">{{ $item->consumption_unit }}<br>Total Stock</span>
                </div>
                <div class="pd-stat">
                    <span class="pd-stat-val">{{ (int)($item->minimum_qty ?? 0) }}</span>
                    <span class="pd-stat-lbl">Minimum<br>Required</span>
                </div>
                @if($avgMonthlyConsumption > 0)
                <div class="pd-stat">
                    <span class="pd-stat-val">{{ $avgMonthlyConsumption }}</span>
                    <span class="pd-stat-lbl">Avg Monthly<br>Usage</span>
                </div>
                <div class="pd-stat">
                    <span class="pd-stat-val" style="color:{{ $daysUntilStockout !== null && $daysUntilStockout < 14 ? '#dc2626' : ($daysUntilStockout !== null && $daysUntilStockout < 30 ? '#d97706' : '#1a7a45') }};">
                        {{ $daysUntilStockout !== null ? $daysUntilStockout : '∞' }}
                    </span>
                    <span class="pd-stat-lbl">Days Until<br>Stock-out</span>
                </div>
                @endif
            </div>

            {{-- By location ── --}}
            @if($item->stocks->count())
            <div style="font-size:11px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;
                        color:#9a85aa;margin-bottom:8px;font-family:'Inter',sans-serif;">By Location</div>
            @foreach($item->stocks->sortByDesc('available_qty') as $stock)
            @php
                $sq = (float)$stock->available_qty;
                $sColor = $sq <= 0 ? '#dc2626' : ($sq <= ($item->minimum_qty/2) ? '#d97706' : '#1a7a45');
            @endphp
            <div style="display:flex;align-items:center;justify-content:space-between;
                        padding:7px 0;border-bottom:1px solid rgba(185,92,183,0.06);">
                <span style="font-size:12.5px;color:#4e2060;font-family:'Inter',sans-serif;">
                    {{ $stock->location?->name ?? 'Default' }}
                </span>
                <span style="font-size:13px;font-weight:600;color:{{ $sColor }};font-family:'Inter',sans-serif;">
                    {{ (int)$sq }} <span style="font-size:10.5px;font-weight:400;color:#9a85aa;">{{ $item->consumption_unit }}</span>
                </span>
            </div>
            @endforeach
            @endif
        </div>

        {{-- Product details ── --}}
        <div class="pd-card">
            <div class="pd-card-title">Product Details</div>
            <div style="display:flex;flex-direction:column;gap:9px;font-family:'Inter',sans-serif;font-size:12.5px;">
                @if($item->generic_name)
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <span style="color:#9a85aa;">Generic Name</span>
                    <span style="color:#1e0a2c;text-align:right;">{{ $item->generic_name }}</span>
                </div>
                @endif
                @if($item->company_name)
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <span style="color:#9a85aa;">Company</span>
                    <span style="color:#1e0a2c;text-align:right;">{{ $item->company_name }}</span>
                </div>
                @endif
                @if($item->retail_type)
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <span style="color:#9a85aa;">Retail Type</span>
                    <span style="color:#1e0a2c;text-align:right;">{{ $item->retail_type }}</span>
                </div>
                @endif
                @if($item->retail_expiry_date)
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <span style="color:#9a85aa;">Expiry Date</span>
                    <span style="color:#1e0a2c;text-align:right;">{{ $item->retail_expiry_date->format('d M Y') }}</span>
                </div>
                @endif
                @if($item->packaging_type)
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <span style="color:#9a85aa;">Packaging</span>
                    <span style="color:#1e0a2c;text-align:right;">{{ $item->packaging_type }}@if($item->qty_in_packaging) · {{ $item->qty_in_packaging }}{{ $item->packaging_unit_label }}@endif</span>
                </div>
                @endif
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <span style="color:#9a85aa;">Purchase Unit</span>
                    <span style="color:#1e0a2c;">{{ $item->purchase_unit ?? '—' }}</span>
                </div>
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <span style="color:#9a85aa;">Usage Unit</span>
                    <span style="color:#1e0a2c;">{{ $item->consumption_unit ?? '—' }}</span>
                </div>
                @if($item->last_purchase_price)
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <span style="color:#9a85aa;">Last Price</span>
                    <span style="color:#1e0a2c;font-weight:600;">₹{{ number_format($item->last_purchase_price, 2) }}</span>
                </div>
                @endif
                @if($item->average_purchase_price)
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <span style="color:#9a85aa;">Avg Price</span>
                    <span style="color:#1e0a2c;">₹{{ number_format($item->average_purchase_price, 2) }}</span>
                </div>
                @endif
                @if($item->cost_per_usage)
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <span style="color:#9a85aa;">Price / Use</span>
                    <span style="color:#1e0a2c;">₹{{ number_format($item->cost_per_usage, 2) }}{{ ($item->usage_type === 'multiple_use' && $item->max_usage_count) ? ' (over '.$item->max_usage_count.' uses)' : '' }}</span>
                </div>
                @endif
                @if($item->gst_rate)
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <span style="color:#9a85aa;">GST</span>
                    <span style="color:#1e0a2c;">{{ $item->gst_rate }}%</span>
                </div>
                @endif
                @if($item->reorder_level)
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <span style="color:#9a85aa;">Reorder Level</span>
                    <span style="color:#1e0a2c;">{{ (int)$item->reorder_level }} {{ $item->consumption_unit }}</span>
                </div>
                @endif
                @if($item->last_purchase_date)
                <div style="display:flex;justify-content:space-between;gap:8px;">
                    <span style="color:#9a85aa;">Last Purchased</span>
                    <span style="color:#1e0a2c;">{{ $item->last_purchase_date->format('d M Y') }}</span>
                </div>
                @endif
            </div>
        </div>

        {{-- Suppliers ── --}}
        @if($item->dealers->count())
        <div class="pd-card">
            <div class="pd-card-title">Suppliers</div>
            @foreach($item->dealers as $dealer)
            <div style="display:flex;align-items:center;justify-content:space-between;
                        padding:7px 0;border-bottom:1px solid rgba(185,92,183,0.06);font-family:'Inter',sans-serif;">
                <span style="font-size:12.5px;color:#1e0a2c;">{{ $dealer->name }}</span>
                @if($dealer->pivot->is_primary)
                <span style="font-size:10px;background:#e8f7ee;color:#1a7a45;padding:2px 7px;border-radius:8px;font-weight:500;">Primary</span>
                @else
                <span style="font-size:10px;background:#f5f0f8;color:#9a85aa;padding:2px 7px;border-radius:8px;">Alternate</span>
                @endif
            </div>
            @endforeach
        </div>
        @endif

        {{-- Treatment tags ── --}}
        @if(!empty($item->treatment_tags))
        <div class="pd-card">
            <div class="pd-card-title">Used In Treatments</div>
            <div style="display:flex;flex-wrap:wrap;gap:5px;">
                @foreach($item->treatment_tags as $tag)
                <span class="pd-tag" style="background:#faf5fb;color:#6a0f70;border:1px solid rgba(185,92,183,0.15);">
                    {{ $tag }}
                </span>
                @endforeach
            </div>
        </div>
        @endif

    </div>{{-- end sidebar --}}

    {{-- ════ RIGHT CONTENT ════ --}}
    <div>

        {{-- Consumption chart ── --}}
        @if($monthlyConsumption->count())
        <div class="pd-card">
            <div class="pd-card-title">Monthly Usage (Last 6 Months)</div>
            <canvas id="consumptionChart" height="80"></canvas>
        </div>
        @endif

        {{-- Expiry batches ── --}}
        @if($expiryBatches->count())
        <div class="pd-card">
            <div class="pd-card-title">Expiry Batches</div>
            <table class="pd-tbl">
                <thead>
                    <tr>
                        <th>Expiry Date</th>
                        <th>Location</th>
                        <th style="text-align:right;">Qty</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($expiryBatches as $batch)
                    @php
                        $expDate = $batch->expiry_date ? \Carbon\Carbon::parse($batch->expiry_date) : null;
                        $daysLeft = $expDate ? now()->diffInDays($expDate, false) : null;
                        if ($daysLeft === null)          { $eBg='#f5f5f5'; $eColor='#888'; $eLbl='No Date'; }
                        elseif ($daysLeft < 0)           { $eBg='#fdeaea'; $eColor='#b52020'; $eLbl='Expired'; }
                        elseif ($daysLeft <= 30)         { $eBg='#fef3cd'; $eColor='#b45309'; $eLbl=$daysLeft.'d left'; }
                        elseif ($daysLeft <= 90)         { $eBg='#fff7e6'; $eColor='#d97706'; $eLbl=$daysLeft.'d left'; }
                        else                              { $eBg='#e8f7ee'; $eColor='#1a7a45'; $eLbl='Good'; }
                    @endphp
                    <tr>
                        <td style="font-weight:500;">{{ $expDate ? $expDate->format('d M Y') : '—' }}</td>
                        <td style="color:#7a6884;">{{ $batch->location_name ?? 'Default' }}</td>
                        <td style="text-align:right;font-weight:600;">{{ (int)$batch->qty }}</td>
                        <td>
                            <span style="background:{{ $eBg }};color:{{ $eColor }};padding:2px 8px;
                                         border-radius:8px;font-size:11px;font-weight:500;">{{ $eLbl }}</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Purchase history ── --}}
        @if($purchaseHistory->count())
        <div class="pd-card">
            <div class="pd-card-title">Purchase History <span style="font-weight:400;color:#c0b0d0;">(last 10 GRNs)</span></div>
            <table class="pd-tbl">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Batch</th>
                        <th>Expiry</th>
                        <th style="text-align:right;">Qty</th>
                        <th style="text-align:right;">Unit Cost</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchaseHistory as $ph)
                    <tr>
                        <td style="white-space:nowrap;">
                            {{ $ph->received_at ? \Carbon\Carbon::parse($ph->received_at)->format('d M Y') : '—' }}
                        </td>
                        <td style="color:#4e2060;">{{ $ph->vendor_name ?? '—' }}</td>
                        <td style="color:#9a85aa;font-size:11.5px;">{{ $ph->batch_no ?? '—' }}</td>
                        <td style="font-size:11.5px;">
                            @if($ph->expiry_date)
                                {{ \Carbon\Carbon::parse($ph->expiry_date)->format('M Y') }}
                            @else —
                            @endif
                        </td>
                        <td style="text-align:right;font-weight:600;">{{ $ph->quantity_received }}</td>
                        <td style="text-align:right;">{{ $ph->unit_cost ? '₹'.number_format($ph->unit_cost,2) : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        {{-- Movement timeline ── --}}
        <div class="pd-card">
            <div class="pd-card-title">Activity Timeline <span style="font-weight:400;color:#c0b0d0;">(last 50 movements)</span></div>
            @if($movements->count())
            <div style="display:flex;flex-direction:column;gap:0;">
                @foreach($movements as $mv)
                @php
                    $mvType = $mv->movement_type;
                    switch($mvType) {
                        case 'stock_in':        $mvDot='#1a7a45'; $mvLabel='Stock In';        $mvIcon='↑'; break;
                        case 'stock_out':       $mvDot='#6a0f70'; $mvLabel='Used';            $mvIcon='↓'; break;
                        case 'treatment_usage': $mvDot='#6a0f70'; $mvLabel='Treatment Usage'; $mvIcon='⚕'; break;
                        case 'adjustment':      $mvDot='#d97706'; $mvLabel='Adjustment';      $mvIcon='≡'; break;
                        case 'expired':         $mvDot='#dc2626'; $mvLabel='Expired';         $mvIcon='⚠'; break;
                        case 'damaged':         $mvDot='#dc2626'; $mvLabel='Damaged';         $mvIcon='✗'; break;
                        case 'transfer':        $mvDot='#1a5ea8'; $mvLabel='Transfer';        $mvIcon='⇄'; break;
                        default:                $mvDot='#9a85aa'; $mvLabel=ucwords(str_replace('_',' ',$mvType)); $mvIcon='•';
                    }
                    $mvQty = (float)($mv->quantity ?? 0);
                    $isIn  = in_array($mvType, ['stock_in']);
                @endphp
                <div style="display:flex;align-items:flex-start;gap:10px;padding:9px 0;
                            border-bottom:1px solid rgba(185,92,183,0.05);font-family:'Inter',sans-serif;">
                    <div style="width:26px;height:26px;border-radius:50%;background:{{ $mvDot }}20;
                                display:flex;align-items:center;justify-content:center;
                                font-size:11px;color:{{ $mvDot }};flex-shrink:0;margin-top:1px;">
                        {{ $mvIcon }}
                    </div>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                            <span style="font-size:12.5px;font-weight:500;color:#1e0a2c;">{{ $mvLabel }}</span>
                            <span style="font-size:13px;font-weight:700;color:{{ $mvDot }};">
                                {{ $isIn ? '+' : '-' }}{{ abs($mvQty) }} {{ $item->consumption_unit }}
                            </span>
                        </div>
                        <div style="font-size:11px;color:#9a85aa;margin-top:2px;display:flex;align-items:center;gap:10px;">
                            <span>{{ $mv->created_at?->format('d M Y, h:i A') ?? '—' }}</span>
                            @if($mv->location?->name)
                            <span>· 📍 {{ $mv->location->name }}</span>
                            @endif
                            @if($mv->notes)
                            <span style="color:#7a6884;" title="{{ $mv->notes }}">· "{{ Str::limit($mv->notes, 40) }}"</span>
                            @endif
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @else
            <div style="text-align:center;padding:32px;color:#9a85aa;font-family:'Inter',sans-serif;font-size:13px;">
                No movement history yet.
            </div>
            @endif
        </div>

        {{-- Notes ── --}}
        @if($item->product_notes)
        <div class="pd-card">
            <div class="pd-card-title">Notes</div>
            <p style="font-family:'Inter',sans-serif;font-size:13px;color:#4e2060;line-height:1.6;margin:0;">
                {{ $item->product_notes }}
            </p>
        </div>
        @endif

    </div>{{-- end right content --}}
</div>{{-- end pd-grid --}}

@endsection

@push('scripts')
@if($monthlyConsumption->count())
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
(function() {
    const labels = @json($monthlyConsumption->pluck('month')->map(fn($m) => \Carbon\Carbon::parse($m.'-01')->format('M Y')));
    const data   = @json($monthlyConsumption->pluck('qty'));
    const ctx = document.getElementById('consumptionChart');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Units Used',
                data: data,
                backgroundColor: 'rgba(106,15,112,0.15)',
                borderColor: '#6a0f70',
                borderWidth: 2,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { font: { size: 11 } }, grid: { color: 'rgba(185,92,183,0.08)' } },
                x: { ticks: { font: { size: 11 } }, grid: { display: false } }
            }
        }
    });
})();
</script>
@endif
@endpush
