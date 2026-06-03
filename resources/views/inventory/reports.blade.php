@extends('layouts.app')
@section('page-title', 'Inventory — Reports')
@section('head-extra')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endsection
@section('content')

<div class="df-page-header">
    <div>
        <div class="df-page-title" style="font-size:22px;">Inventory</div>
        <div class="df-page-subtitle">Reports · Stock valuation, movement history &amp; consumption analysis</div>
    </div>
</div>

@include('inventory.partials.subnav')

@php
use App\Models\Inventory\InventoryItem;
use App\Models\Inventory\StockMovement;
use Illuminate\Support\Facades\DB;

// Stock valuation by category
$categoryValuation = DB::table('inventory_items as i')
    ->join('inventory_stocks as s','i.id','=','s.inventory_item_id')
    ->join('inventory_categories as c','i.category_id','=','c.id')
    ->where('i.is_active',true)
    ->select('c.name','c.color',DB::raw('SUM(s.available_qty * i.average_purchase_price) as value'),DB::raw('COUNT(DISTINCT i.id) as item_count'))
    ->groupBy('c.id','c.name','c.color')
    ->orderByDesc('value')
    ->get();

// Movement summary last 30 days
$movementSummary = DB::table('stock_movements')
    ->where('created_at','>=',now()->subDays(30))
    ->select('movement_type',DB::raw('COUNT(*) as count'),DB::raw('SUM(ABS(total_cost)) as total_cost'))
    ->groupBy('movement_type')
    ->get();

// Top consumed items last 30 days
$topConsumed = DB::table('stock_movements as m')
    ->join('inventory_items as i','m.inventory_item_id','=','i.id')
    ->where('m.movement_type','stock_out')
    ->where('m.created_at','>=',now()->subDays(30))
    ->select('i.product_name',DB::raw('SUM(ABS(m.qty)) as total_qty'),DB::raw('SUM(m.total_cost) as total_cost'),'i.consumption_unit')
    ->groupBy('i.id','i.product_name','i.consumption_unit')
    ->orderByDesc('total_qty')
    ->limit(10)
    ->get();

// Total value
$totalValue = DB::table('inventory_stocks as s')
    ->join('inventory_items as i','s.inventory_item_id','=','i.id')
    ->where('i.is_active',true)
    ->sum(DB::raw('s.available_qty * i.average_purchase_price'));

// Daily movement last 14 days for mini-chart
$dailyMovements = collect();
for($d=13;$d>=0;$d--){
    $date = now()->subDays($d);
    $in  = DB::table('stock_movements')->whereIn('movement_type',['stock_in','opening_stock'])->whereDate('created_at',$date)->sum('total_cost');
    $out = DB::table('stock_movements')->whereIn('movement_type',['stock_out','treatment_usage'])->whereDate('created_at',$date)->count();
    $dailyMovements->push(['label'=>$date->format('d M'),'in'=>round($in,0),'out'=>$out]);
}
@endphp

{{-- Summary KPI strip --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px;">
    @php $stats = [
        ['label'=>'Total Stock Value','value'=>'₹'.number_format($totalValue,0),'color'=>'#6a0f70','bg'=>'#f9f3fa'],
        ['label'=>'Categories','value'=>$categoryValuation->count(),'color'=>'#1a5ea8','bg'=>'#e6f0fb'],
        ['label'=>'Movements (30d)','value'=>$movementSummary->sum('count'),'color'=>'#0e7b89','bg'=>'#e0f7f9'],
        ['label'=>'Top Consumed (30d)','value'=>$topConsumed->count().' items','color'=>'#a05c00','bg'=>'#fff4e0'],
    ]; @endphp
    @foreach($stats as $s)
    <div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:4px;padding:16px;">
        <div style="font-family:'DM Mono',monospace;font-size:22px;font-weight:700;color:{{ $s['color'] }};">{{ $s['value'] }}</div>
        <div style="font-size:12px;color:#7a6884;margin-top:4px;">{{ $s['label'] }}</div>
    </div>
    @endforeach
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

    {{-- Category Valuation --}}
    <div class="df-card">
        <div class="df-card-header"><span style="font-size:12.5px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#4e0a53;">Stock Value by Category</span></div>
        <div class="df-card-body">
            @forelse($categoryValuation as $cv)
            @php $pct = $totalValue > 0 ? round(($cv->value/$totalValue)*100) : 0; @endphp
            <div style="margin-bottom:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                    <span style="font-size:12.5px;color:#2e1040;font-weight:500;">{{ $cv->name }}</span>
                    <span style="font-size:12px;color:#9a85aa;font-family:'DM Mono',monospace;">₹{{ number_format($cv->value,0) }} <span style="font-size:10px;">({{ $pct }}%)</span></span>
                </div>
                <div style="height:5px;background:rgba(185,92,183,0.08);border-radius:3px;overflow:hidden;">
                    <div style="height:100%;width:{{ $pct }}%;background:{{ $cv->color }};border-radius:3px;transition:width 600ms;"></div>
                </div>
            </div>
            @empty
            <div style="text-align:center;color:#9a85aa;padding:24px;font-size:13px;">No stock data yet.</div>
            @endforelse
        </div>
    </div>

    {{-- Movement Summary --}}
    <div class="df-card">
        <div class="df-card-header"><span style="font-size:12.5px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#4e0a53;">Movement Summary (Last 30 Days)</span></div>
        <div class="df-card-body">
            @php $typeColors=['stock_in'=>'#1a7a45','opening_stock'=>'#1a7a45','stock_out'=>'#6a0f70','treatment_usage'=>'#1a5ea8','damaged'=>'#b52020','expired'=>'#a05c00','adjustment'=>'#555','transfer'=>'#0e7b89']; @endphp
            @forelse($movementSummary as $mv)
            <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid rgba(185,92,183,0.06);">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="width:8px;height:8px;border-radius:50%;background:{{ $typeColors[$mv->movement_type] ?? '#555' }};flex-shrink:0;"></span>
                    <span style="font-size:13px;color:#2e1040;font-weight:500;">{{ ucfirst(str_replace('_',' ',$mv->movement_type)) }}</span>
                </div>
                <div style="text-align:right;">
                    <div style="font-family:'DM Mono',monospace;font-size:13px;font-weight:600;color:#1e0a2c;">{{ $mv->count }}×</div>
                    @if($mv->total_cost > 0)<div style="font-size:11px;color:#9a85aa;">₹{{ number_format($mv->total_cost,0) }}</div>@endif
                </div>
            </div>
            @empty
            <div style="text-align:center;color:#9a85aa;padding:24px;font-size:13px;">No movements in last 30 days.</div>
            @endforelse
        </div>
    </div>

</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">

    {{-- Top consumed items --}}
    <div class="df-card">
        <div class="df-card-header"><span style="font-size:12.5px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#4e0a53;">Top Consumed Items (Last 30 Days)</span></div>
        <div style="overflow-x:auto;">
            <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                <thead><tr style="background:#faf5fb;">
                    <th style="padding:8px 18px;text-align:left;font-size:10.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Item</th>
                    <th style="padding:8px 14px;text-align:right;font-size:10.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Qty Used</th>
                    <th style="padding:8px 18px;text-align:right;font-size:10.5px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;color:#9a85aa;">Cost</th>
                </tr></thead>
                <tbody>
                    @forelse($topConsumed as $item)
                    <tr style="border-bottom:1px solid rgba(185,92,183,0.05);">
                        <td style="padding:9px 18px;font-weight:500;color:#1e0a2c;">{{ Str::limit($item->product_name,30) }}</td>
                        <td style="padding:9px 14px;text-align:right;font-family:'DM Mono',monospace;color:#6a0f70;font-weight:600;">{{ number_format($item->total_qty,0) }} <span style="font-size:10px;color:#9a85aa;">{{ $item->consumption_unit }}</span></td>
                        <td style="padding:9px 18px;text-align:right;font-family:'DM Mono',monospace;color:#1e0a2c;">₹{{ number_format($item->total_cost,0) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="padding:24px;text-align:center;color:#9a85aa;">No stock-out data yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Daily activity chart --}}
    <div class="df-card">
        <div class="df-card-header"><span style="font-size:12.5px;font-weight:600;letter-spacing:.04em;text-transform:uppercase;color:#4e0a53;">Daily Activity (Last 14 Days)</span></div>
        <div class="df-card-body" style="padding-top:8px;padding-bottom:8px;">
            <canvas id="dailyChart" height="160"></canvas>
        </div>
    </div>

</div>

@endsection
@push('scripts')
<script>
const daily = @json($dailyMovements);
new Chart(document.getElementById('dailyChart'), {
    type: 'bar',
    data: {
        labels: daily.map(d => d.label),
        datasets: [
            { label: 'Stock In Value (₹)', data: daily.map(d => d.in), backgroundColor: 'rgba(26,122,69,0.7)', borderRadius: 2 },
            { label: 'Dispensed (count)', data: daily.map(d => d.out), backgroundColor: 'rgba(106,15,112,0.55)', borderRadius: 2, yAxisID: 'y1' }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { labels: { font: { size: 11, family: 'DM Sans' }, boxWidth: 10 } } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#9a85aa' }, border: { display: false } },
            y: { grid: { color: 'rgba(185,92,183,0.06)' }, ticks: { font: { size: 10, family: 'DM Mono' }, color: '#9a85aa', callback: v => '₹'+(v>=1000?(v/1000).toFixed(0)+'k':v) }, border: { display: false } },
            y1: { position: 'right', grid: { display: false }, ticks: { font: { size: 10 }, color: '#9a85aa' }, border: { display: false } }
        }
    }
});
</script>
@endpush
