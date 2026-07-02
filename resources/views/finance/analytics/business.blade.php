@extends('layouts.app')
@section('page-title', 'Business Intelligence — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
                &nbsp;/&nbsp;
                <a href="{{ route('finance.analytics.index') }}" class="hover:text-[#6a0f70]">Analytics</a>
                &nbsp;/&nbsp; Business Intelligence
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                Business Intelligence
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
        </div>
    </div>

    {{-- KPIs --}}
    <div class="grid grid-cols-4 gap-3">
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Total Revenue</p>
            <p class="text-2xl font-bold text-green-600 mt-1">Rs. {{ number_format($kpis['total_revenue'],0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Total Expenses</p>
            <p class="text-2xl font-bold text-red-600 mt-1">Rs. {{ number_format($kpis['total_expense'],0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Net Profit</p>
            <p class="text-2xl font-bold {{ $kpis['total_profit'] >= 0 ? 'text-green-700' : 'text-red-700' }} mt-1">
                Rs. {{ number_format($kpis['total_profit'],0) }}
            </p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Profit Margin</p>
            <p class="text-2xl font-bold {{ $kpis['profit_margin'] >= 0 ? 'text-green-700' : 'text-red-700' }} mt-1">
                {{ $kpis['profit_margin'] }}%
            </p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Avg Monthly Revenue</p>
            <p class="text-xl font-bold text-[#6a0f70] mt-1">Rs. {{ number_format($kpis['avg_monthly_rev'],0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Avg Monthly Expense</p>
            <p class="text-xl font-bold text-gray-700 mt-1">Rs. {{ number_format($kpis['avg_monthly_exp'],0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Best Month Revenue</p>
            <p class="text-xl font-bold text-blue-600 mt-1">Rs. {{ number_format($kpis['best_month_rev'],0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Patient Outstanding</p>
            <p class="text-xl font-bold text-amber-600 mt-1">Rs. {{ number_format($kpis['patient_outstanding'],0) }}</p>
        </div>
    </div>

    {{-- Revenue vs Expense vs Profit chart --}}
    <div class="bg-white border border-[#e8d5f0] p-4">
        <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Monthly Profitability</p>
        <canvas id="profitChart" height="120"></canvas>
    </div>

    <div class="grid grid-cols-2 gap-5">
        {{-- Revenue by mode --}}
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Revenue by Payment Mode</p>
            <canvas id="modeChart" height="200"></canvas>
        </div>
        {{-- Expense by category --}}
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Expense by Category</p>
            <canvas id="expCatChart" height="200"></canvas>
        </div>
    </div>

    {{-- Profitability table --}}
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">Monthly P&L Summary</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Month</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Revenue</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Expenses</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Net Profit</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Margin</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($profitability as $row)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 font-medium text-gray-700">{{ $row['label'] }}</td>
                    <td class="px-4 py-3 text-right text-green-600 font-semibold">Rs. {{ number_format($row['revenue'],0) }}</td>
                    <td class="px-4 py-3 text-right text-red-600">Rs. {{ number_format($row['expense'],0) }}</td>
                    <td class="px-4 py-3 text-right font-bold {{ $row['profit'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        Rs. {{ number_format($row['profit'],0) }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="text-xs font-medium {{ $row['margin'] >= 20 ? 'text-green-600' : ($row['margin'] >= 0 ? 'text-amber-600' : 'text-red-600') }}">
                            {{ $row['margin'] }}%
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
new Chart(document.getElementById('profitChart'), {
    type: 'bar',
    data: {
        labels: {!! collect($profitability)->pluck('label')->toJson() !!},
        datasets: [
            { label:'Revenue', data:{!! collect($profitability)->pluck('revenue')->toJson() !!}, backgroundColor:'rgba(22,163,74,0.6)' },
            { label:'Expense', data:{!! collect($profitability)->pluck('expense')->toJson() !!}, backgroundColor:'rgba(220,38,38,0.6)' },
            { label:'Profit',  data:{!! collect($profitability)->pluck('profit')->toJson() !!},  type:'line', borderColor:'#6a0f70', borderWidth:2, fill:false, tension:0.3 }
        ]
    },
    options:{ responsive:true, scales:{ y:{ ticks:{ callback:v=>'Rs. '+v.toLocaleString('en-IN') } } } }
});
new Chart(document.getElementById('modeChart'), {
    type: 'doughnut',
    data: {
        labels: {!! $revenueByMode->pluck('payment_mode')->map(fn($m)=>ucfirst($m))->toJson() !!},
        datasets: [{ data:{!! $revenueByMode->pluck('total')->toJson() !!},
            backgroundColor:['#16a34a','#7c3aed','#2563eb','#d97706','#0891b2','#db2777','#6b7280'] }]
    },
    options:{ responsive:true, plugins:{ legend:{ position:'right' },
        tooltip:{ callbacks:{ label:ctx=>ctx.label+': Rs. '+Number(ctx.raw).toLocaleString('en-IN') } } } }
});
new Chart(document.getElementById('expCatChart'), {
    type: 'doughnut',
    data: {
        labels: {!! $expenseByCategory->pluck('category')->toJson() !!},
        datasets: [{ data:{!! $expenseByCategory->pluck('total')->toJson() !!},
            backgroundColor:['#6a0f70','#9c27b0','#ce93d8','#4a148c','#7b1fa2','#ab47bc','#e1bee7','#d500f9'] }]
    },
    options:{ responsive:true, plugins:{ legend:{ position:'right' },
        tooltip:{ callbacks:{ label:ctx=>ctx.label+': Rs. '+Number(ctx.raw).toLocaleString('en-IN') } } } }
});
</script>
@endsection
