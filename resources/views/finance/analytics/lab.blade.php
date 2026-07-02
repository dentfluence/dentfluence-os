@extends('layouts.app')
@section('page-title', 'Lab Analytics — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
                &nbsp;/&nbsp;
                <a href="{{ route('finance.analytics.index') }}" class="hover:text-[#6a0f70]">Analytics</a>
                &nbsp;/&nbsp; Lab
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                Lab Analytics
            </h1>
        </div>
        <div class="flex gap-2">
            @foreach([3=>'3M',6=>'6M',12=>'12M'] as $m => $label)
            <a href="{{ route('finance.analytics.lab', ['months'=>$m]) }}"
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
            ['label'=>'Total Cases',     'val'=>$kpis['total_cases'],                            'color'=>'text-[#6a0f70]'],
            ['label'=>'Open Cases',      'val'=>$kpis['open_cases'],                             'color'=>'text-blue-600'],
            ['label'=>'Overdue Cases',   'val'=>$kpis['overdue_cases'],                          'color'=>'text-red-600'],
            ['label'=>'Total Spend',     'val'=>'Rs. '.number_format($kpis['total_spend'],0),        'color'=>'text-[#6a0f70]'],
            ['label'=>'Outstanding',     'val'=>'Rs. '.number_format($kpis['outstanding'],0),        'color'=>'text-amber-600'],
            ['label'=>'Avg Cost/Case',   'val'=>'Rs. '.number_format($kpis['avg_cost'],0),           'color'=>'text-gray-700'],
            ['label'=>'Avg Turnaround',  'val'=>$kpis['avg_tat'].' days',                        'color'=>'text-gray-700'],
            ['label'=>'Closed Cases',    'val'=>$kpis['closed_cases'],                           'color'=>'text-green-600'],
        ] as $c)
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">{{ $c['label'] }}</p>
            <p class="text-xl font-bold {{ $c['color'] }} mt-1">{{ $c['val'] }}</p>
        </div>
        @endforeach
    </div>

    <div class="grid grid-cols-2 gap-5">
        {{-- Monthly case volume --}}
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Monthly Case Volume & Spend</p>
            <canvas id="caseChart" height="200"></canvas>
        </div>
        {{-- Billing status --}}
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Billing Status</p>
            <canvas id="billingChart" height="200"></canvas>
        </div>
    </div>

    {{-- Cases by vendor --}}
    @if($casesByVendor->isNotEmpty())
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">Cases by Lab Vendor</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Lab Vendor</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Cases</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Total Charge</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Avg per Case</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($casesByVendor as $v)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $v->vendor }}</td>
                    <td class="px-4 py-3 text-right text-gray-600">{{ $v->cnt }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-[#6a0f70]">Rs. {{ number_format($v->total_charge, 0) }}</td>
                    <td class="px-4 py-3 text-right text-gray-500">Rs. {{ number_format($v->avg_charge, 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Cost by work category --}}
    @if($costByCategory->isNotEmpty())
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">Cost by Work Category</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Work Category</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Cases</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Total Spend</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Avg Cost/Case</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($costByCategory as $cat)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $cat->work_category }}</td>
                    <td class="px-4 py-3 text-right text-gray-600">{{ $cat->cnt }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">Rs. {{ number_format($cat->total, 0) }}</td>
                    <td class="px-4 py-3 text-right text-[#6a0f70]">Rs. {{ number_format($cat->avg_cost, 0) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Outstanding reconciliations --}}
    @if($outstandingBills->isNotEmpty())
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">Outstanding Lab Bills (Approved, Awaiting Payment)</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Reference</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Lab</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Period</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Our Total</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Agreed</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($outstandingBills as $rec)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ $rec->reconciliation_ref }}</td>
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $rec->labVendor?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ \Carbon\Carbon::create($rec->billing_year, $rec->billing_month)->format('M Y') }}</td>
                    <td class="px-4 py-3 text-right text-gray-700">Rs. {{ number_format($rec->our_total, 0) }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-red-600">Rs. {{ number_format($rec->agreed_amount, 0) }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('lab.reconciliation.show', $rec->id) }}"
                           class="text-xs text-[#6a0f70] hover:underline">View →</a>
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
new Chart(document.getElementById('caseChart'), {
    type: 'bar',
    data: {
        labels: {!! $monthlyCases->map(fn($m)=>\Carbon\Carbon::parse($m->month.'-01')->format('M Y'))->toJson() !!},
        datasets: [
            { label:'Cases', data:{!! $monthlyCases->pluck('cnt')->toJson() !!}, backgroundColor:'rgba(106,15,112,0.7)', yAxisID:'y' },
            { label:'Spend (Rs. )', data:{!! $monthlyCases->pluck('total_charge')->toJson() !!}, type:'line', borderColor:'#ef4444', yAxisID:'y1', tension:0.3, fill:false }
        ]
    },
    options: { responsive:true, scales:{
        y:{ beginAtZero:true, position:'left', title:{display:true,text:'Cases'} },
        y1:{ beginAtZero:true, position:'right', grid:{drawOnChartArea:false}, ticks:{callback:v=>'Rs. '+v.toLocaleString('en-IN')} }
    }}
});
new Chart(document.getElementById('billingChart'), {
    type: 'doughnut',
    data: {
        labels: {!! collect($billingStatus->keys())->map(fn($k)=>ucfirst(str_replace('_',' ',$k)))->toJson() !!},
        datasets: [{ data:{!! collect($billingStatus->values())->pluck('cnt')->toJson() !!},
            backgroundColor:['#6a0f70','#f59e0b','#10b981','#ef4444','#6b7280'] }]
    },
    options: { responsive:true, plugins:{ legend:{ position:'right' } } }
});
</script>
@endsection
