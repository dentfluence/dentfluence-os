@extends('layouts.app')
@section('page-title', 'Procurement Analytics — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
                &nbsp;/&nbsp;
                <a href="{{ route('finance.analytics.index') }}" class="hover:text-[#6a0f70]">Analytics</a>
                &nbsp;/&nbsp; Procurement
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                Procurement Analytics
            </h1>
        </div>
        <div class="flex gap-2">
            @foreach([3=>'3M',6=>'6M',12=>'12M'] as $m => $label)
            <a href="{{ route('finance.analytics.procurement', ['months'=>$m]) }}"
               class="text-xs px-3 py-1 border transition-colors
                   {{ $months == $m ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'border-gray-300 text-gray-600 hover:border-[#6a0f70] hover:text-[#6a0f70]' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-4 gap-3">
        @foreach([
            ['label'=>'Total POs',        'val'=>$kpis['total_pos'],                        'color'=>'text-[#6a0f70]'],
            ['label'=>'PO Value',         'val'=>'Rs. '.number_format($kpis['total_po_value'],0),'color'=>'text-[#6a0f70]'],
            ['label'=>'Pending POs',      'val'=>$kpis['pending_pos'],                      'color'=>'text-amber-600'],
            ['label'=>'Total Invoiced',   'val'=>'Rs. '.number_format($kpis['total_invoiced'],0),'color'=>'text-blue-600'],
            ['label'=>'Unpaid Invoices',  'val'=>'Rs. '.number_format($kpis['unpaid_invoices'],0),'color'=>'text-red-600'],
            ['label'=>'GRNs Received',    'val'=>$kpis['grn_count'],                        'color'=>'text-green-600'],
            ['label'=>'GRN Value',        'val'=>'Rs. '.number_format($kpis['grn_value'],0),   'color'=>'text-green-600'],
        ] as $c)
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">{{ $c['label'] }}</p>
            <p class="text-xl font-bold {{ $c['color'] }} mt-1">{{ $c['val'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-2 gap-5">
        {{-- Monthly PO trend --}}
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Monthly Purchase Order Trend</p>
            <canvas id="poChart" height="200"></canvas>
        </div>
        {{-- PO Status donut --}}
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">PO Status Breakdown</p>
            <canvas id="statusChart" height="200"></canvas>
        </div>
    </div>

    {{-- Top vendors --}}
    @if($topVendors->isNotEmpty())
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">Top Vendors by PO Value</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Vendor</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">POs</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Total Value</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($topVendors as $v)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $v->vendor_name }}</td>
                    <td class="px-4 py-3 text-right text-gray-600">{{ $v->cnt }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-[#6a0f70]">Rs. {{ number_format($v->total, 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Pending POs --}}
    @if($pendingPos->isNotEmpty())
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">Pending / Partial Purchase Orders</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">PO No.</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Vendor</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Order Date</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Value</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($pendingPos as $po)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $po->order_no }}</td>
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $po->financeVendor?->vendor_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $po->order_date?->format('d M Y') }}</td>
                    <td class="px-4 py-3 text-right font-semibold">Rs. {{ number_format($po->total_amount, 0) }}</td>
                    <td class="px-4 py-3 text-right">
                        <span class="inline-block px-2 py-0.5 text-xs rounded-full
                            {{ $po->status === 'partial' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600' }}">
                            {{ ucfirst($po->status) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('inventory.purchase') }}" class="text-xs text-[#6a0f70] hover:underline">View →</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('poChart'), {
    type: 'bar',
    data: {
        labels: {!! $monthlyPo->map(fn($m)=>\Carbon\Carbon::parse($m->month.'-01')->format('M Y'))->toJson() !!},
        datasets: [
            { label:'POs', data:{!! $monthlyPo->pluck('cnt')->toJson() !!}, backgroundColor:'rgba(106,15,112,0.7)', yAxisID:'y' },
            { label:'Value (Rs. )', data:{!! $monthlyPo->pluck('total')->toJson() !!}, type:'line', borderColor:'#2563eb', yAxisID:'y1', tension:0.3, fill:false }
        ]
    },
    options:{ responsive:true, scales:{
        y:{ beginAtZero:true, position:'left' },
        y1:{ beginAtZero:true, position:'right', grid:{drawOnChartArea:false}, ticks:{callback:v=>'Rs. '+v.toLocaleString('en-IN')} }
    }}
});
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: {!! $poSummary->keys()->map(fn($k)=>ucfirst($k))->toJson() !!},
        datasets: [{ data:{!! $poSummary->pluck('cnt')->toJson() !!},
            backgroundColor:['#6a0f70','#f59e0b','#10b981','#ef4444','#3b82f6'] }]
    },
    options:{ responsive:true, plugins:{ legend:{ position:'right' } } }
});
</script>
@endsection
