@extends('layouts.app')
@section('page-title', 'Expense Analytics — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
                &nbsp;/&nbsp;
                <a href="{{ route('finance.analytics.index') }}" class="hover:text-[#6a0f70]">Analytics</a>
                &nbsp;/&nbsp; Expenses
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                Expense Analytics
            </h1>
        </div>
        <div class="flex gap-2">
            @foreach([3=>'3M',6=>'6M',12=>'12M'] as $m => $label)
            <a href="{{ url()->current() }}?months={{ $m }}"
               class="text-xs px-3 py-1 border transition-colors
                   {{ $months == $m ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'border-gray-300 text-gray-600 hover:border-[#6a0f70] hover:text-[#6a0f70]' }}">
                {{ $label }}
            </a>
            @endforeach
            <a href="{{ route('finance.expenses.export', request()->query()) }}"
               class="text-xs px-3 py-1 border border-green-600 text-green-700 hover:bg-green-50 transition-colors">
                ↓ Excel
            </a>
        </div>
    </div>

    {{-- KPI strip --}}
    <div class="grid grid-cols-5 gap-3">
        @foreach([
            ['label'=>'Total Spend',  'val'=>'Rs. '.number_format($summary['total'],0),     'color'=>'text-[#6a0f70]'],
            ['label'=>'GST Paid',     'val'=>'Rs. '.number_format($summary['gst'],0),        'color'=>'text-blue-600'],
            ['label'=>'Unpaid Bills', 'val'=>'Rs. '.number_format($summary['unpaid'],0),     'color'=>'text-red-600'],
            ['label'=>'Recurring',    'val'=>'Rs. '.number_format($summary['recurring'],0),  'color'=>'text-amber-600'],
            ['label'=>'Transactions', 'val'=>$summary['count'],                            'color'=>'text-gray-700'],
        ] as $c)
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">{{ $c['label'] }}</p>
            <p class="text-xl font-bold {{ $c['color'] }} mt-1">{{ $c['val'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-2 gap-5">
        {{-- Monthly trend --}}
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Monthly Expense Trend</p>
            <canvas id="trendChart" height="200"></canvas>
        </div>
        {{-- Category donut --}}
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Category Breakdown</p>
            <canvas id="catChart" height="200"></canvas>
        </div>
    </div>

    {{-- Category table --}}
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">Category-wise Spend</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Count</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">% of Total</th>
                    <th class="px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Share</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($byCategory as $row)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $row['category'] }}</td>
                    <td class="px-4 py-3 text-right text-gray-600">{{ $row['cnt'] }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">Rs. {{ number_format($row['total'], 0) }}</td>
                    <td class="px-4 py-3 text-right text-gray-500">{{ $row['pct'] }}%</td>
                    <td class="px-4 py-3">
                        <div class="w-full bg-gray-100 rounded h-2">
                            <div class="bg-[#6a0f70] h-2 rounded" style="width:{{ $row['pct'] }}%"></div>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Top vendors --}}
    @if($byVendor->isNotEmpty())
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">Top Vendors by Spend</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Vendor</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Transactions</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Total Spend</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($byVendor as $v)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $v->vendor_name }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500">{{ str_replace('_',' ',ucfirst($v->vendor_type)) }}</td>
                    <td class="px-4 py-3 text-right text-gray-600">{{ $v->cnt }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-[#6a0f70]">Rs. {{ number_format($v->total, 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('trendChart'), {
    type: 'line',
    data: {
        labels: {!! $monthlyTrend->map(fn($m) => \Carbon\Carbon::parse($m->month.'-01')->format('M Y'))->toJson() !!},
        datasets: [
            { label: 'Total', data: {!! $monthlyTrend->pluck('total')->toJson() !!}, borderColor:'#6a0f70', backgroundColor:'rgba(106,15,112,0.1)', fill:true, tension:0.3 },
            { label: 'Unpaid', data: {!! $monthlyTrend->pluck('unpaid_total')->toJson() !!}, borderColor:'#ef4444', backgroundColor:'rgba(239,68,68,0.1)', fill:true, tension:0.3 }
        ]
    },
    options: { responsive:true, scales:{ y:{ beginAtZero:true, ticks:{ callback: v=>'Rs. '+v.toLocaleString('en-IN') } } } }
});
new Chart(document.getElementById('catChart'), {
    type: 'doughnut',
    data: {
        labels: {!! collect($byCategory)->pluck('category')->toJson() !!},
        datasets: [{ data: {!! collect($byCategory)->pluck('total')->toJson() !!},
            backgroundColor:['#6a0f70','#9c27b0','#ce93d8','#4a148c','#7b1fa2','#ab47bc','#e1bee7','#d500f9'] }]
    },
    options: { responsive:true, plugins:{ legend:{ position:'right' },
        tooltip:{ callbacks:{ label: ctx=>ctx.label+': Rs. '+Number(ctx.raw).toLocaleString('en-IN') } } } }
});
</script>
@endsection
