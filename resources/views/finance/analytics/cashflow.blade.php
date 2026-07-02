@extends('layouts.app')
@section('page-title', 'Cash Flow — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
                &nbsp;/&nbsp;
                <a href="{{ route('finance.analytics.index') }}" class="hover:text-[#6a0f70]">Analytics</a>
                &nbsp;/&nbsp; Cash Flow
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                Cash Flow Dashboard
            </h1>
        </div>
        <div class="flex gap-2">
            @foreach([3=>'3M',6=>'6M',12=>'12M'] as $m => $label)
            <a href="{{ route('finance.analytics.cashflow', ['months'=>$m]) }}"
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
            <p class="text-xs text-gray-400 uppercase tracking-widest">Total In</p>
            <p class="text-2xl font-bold text-green-600 mt-1">Rs. {{ number_format($kpis['total_in'],0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Total Out</p>
            <p class="text-2xl font-bold text-red-600 mt-1">Rs. {{ number_format($kpis['total_out'],0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Net Cash Flow</p>
            <p class="text-2xl font-bold {{ $kpis['net_cashflow'] >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">
                Rs. {{ number_format($kpis['net_cashflow'],0) }}
            </p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Forecast Due (90d)</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">Rs. {{ number_format($kpis['forecast_due'],0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">This Month In</p>
            <p class="text-xl font-bold text-green-600 mt-1">Rs. {{ number_format($kpis['this_month_in'],0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">This Month Out</p>
            <p class="text-xl font-bold text-red-600 mt-1">Rs. {{ number_format($kpis['this_month_out'],0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">This Month Net</p>
            <p class="text-xl font-bold {{ $kpis['this_month_net'] >= 0 ? 'text-green-600' : 'text-red-600' }} mt-1">
                Rs. {{ number_format($kpis['this_month_net'],0) }}
            </p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Overdue Bills</p>
            <p class="text-xl font-bold text-red-600 mt-1">Rs. {{ number_format($kpis['overdue_amount'],0) }}</p>
        </div>
    </div>

    {{-- Cash flow chart --}}
    <div class="bg-white border border-[#e8d5f0] p-4">
        <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Monthly Cash Flow — In vs Out vs Net</p>
        <canvas id="cfChart" height="120"></canvas>
    </div>

    {{-- Monthly breakdown table --}}
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">Monthly Summary</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Month</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Cash In</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Cash Out</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Net</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($allMonths as $row)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 font-medium text-gray-700">{{ \Carbon\Carbon::parse($row['month'].'-01')->format('M Y') }}</td>
                    <td class="px-4 py-3 text-right text-green-600 font-semibold">Rs. {{ number_format($row['in'],0) }}</td>
                    <td class="px-4 py-3 text-right text-red-600 font-semibold">Rs. {{ number_format($row['out'],0) }}</td>
                    <td class="px-4 py-3 text-right font-bold {{ $row['net'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        Rs. {{ number_format($row['net'],0) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Forecast --}}
    @if($forecast->isNotEmpty())
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">Upcoming Due Payments (Next 90 Days)</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Expense</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Vendor</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Due Date</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($forecast as $exp)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 text-gray-800">{{ $exp->title }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $exp->vendor?->vendor_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $exp->category?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right font-semibold">Rs. {{ number_format($exp->total_amount,0) }}</td>
                    <td class="px-4 py-3 text-right text-xs text-amber-700 font-medium">{{ $exp->due_date?->format('d M Y') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Overdue --}}
    @if($overdue->isNotEmpty())
    <div class="bg-white border border-red-200 overflow-hidden">
        <div class="px-4 py-3 border-b border-red-100 bg-red-50">
            <p class="text-sm font-medium text-red-700">Overdue Bills ({{ $overdue->count() }})</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-red-50">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-red-500 uppercase tracking-wider">Expense</th>
                    <th class="text-left px-4 py-2.5 text-xs text-red-500 uppercase tracking-wider">Vendor</th>
                    <th class="text-right px-4 py-2.5 text-xs text-red-500 uppercase tracking-wider">Amount</th>
                    <th class="text-right px-4 py-2.5 text-xs text-red-500 uppercase tracking-wider">Was Due</th>
                    <th class="text-right px-4 py-2.5 text-xs text-red-500 uppercase tracking-wider">Days Overdue</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-red-50">
                @foreach($overdue as $exp)
                <tr class="hover:bg-red-50">
                    <td class="px-4 py-3 text-gray-800">{{ $exp->title }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $exp->vendor?->vendor_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-red-700">Rs. {{ number_format($exp->total_amount,0) }}</td>
                    <td class="px-4 py-3 text-right text-xs text-red-600">{{ $exp->due_date?->format('d M Y') }}</td>
                    <td class="px-4 py-3 text-right font-bold text-red-700">{{ $exp->due_date?->diffInDays(today()) }}d</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
const months = {!! collect($allMonths)->pluck('label')->toJson() !!};
const cashIn  = {!! collect($allMonths)->pluck('in')->toJson() !!};
const cashOut = {!! collect($allMonths)->pluck('out')->toJson() !!};
const netFlow = {!! collect($allMonths)->pluck('net')->toJson() !!};

new Chart(document.getElementById('cfChart'), {
    type: 'bar',
    data: {
        labels: months,
        datasets: [
            { label:'Cash In',  data:cashIn,  backgroundColor:'rgba(22,163,74,0.7)',  yAxisID:'y' },
            { label:'Cash Out', data:cashOut, backgroundColor:'rgba(220,38,38,0.7)',  yAxisID:'y' },
            { label:'Net',      data:netFlow, type:'line', borderColor:'#6a0f70', yAxisID:'y', tension:0.3, fill:false, borderWidth:2 }
        ]
    },
    options:{ responsive:true, scales:{ y:{ beginAtZero:false, ticks:{ callback:v=>'Rs. '+v.toLocaleString('en-IN') } } } }
});
</script>
@endsection
