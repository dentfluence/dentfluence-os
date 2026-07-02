@extends('layouts.app')
@section('page-title', 'Vendor Analytics — Finance')

@section('content')
<div class="p-6 space-y-5">

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
                &nbsp;/&nbsp;
                <a href="{{ route('finance.analytics.index') }}" class="hover:text-[#6a0f70]">Analytics</a>
                &nbsp;/&nbsp; Vendors
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                Vendor Analytics
            </h1>
        </div>
        <div class="flex gap-2">
            @foreach([3=>'3M',6=>'6M',12=>'12M'] as $m => $label)
            <a href="{{ route('analytics.vendor', ['months'=>$m]) }}"
               class="text-xs px-3 py-1 border transition-colors
                   {{ $months == $m ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'border-gray-300 text-gray-600 hover:border-[#6a0f70] hover:text-[#6a0f70]' }}">
                {{ $label }}
            </a>
            @endforeach
        </div>
    </div>

    {{-- KPI row --}}
    <div class="grid grid-cols-4 gap-3">
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Outstanding Vendors</p>
            <p class="text-2xl font-bold text-red-600 mt-1">{{ $outstanding->count() }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Total Outstanding</p>
            <p class="text-2xl font-bold text-red-600 mt-1">Rs. {{ number_format($outstanding->sum('outstanding_amount'), 0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Overdue Invoices</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">{{ $overdueInvoices->count() }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Period Purchases</p>
            <p class="text-2xl font-bold text-[#6a0f70] mt-1">Rs. {{ number_format($monthlyTotal->sum('total'), 0) }}</p>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-5">
        {{-- Monthly purchases chart --}}
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Monthly Purchases</p>
            <canvas id="monthlyChart" height="200"></canvas>
        </div>
        {{-- Spend by vendor type --}}
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Spend by Vendor Type</p>
            <canvas id="typeChart" height="200"></canvas>
        </div>
    </div>

    {{-- Outstanding vendors --}}
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">Vendors with Outstanding Balance</p>
        </div>
        @if($outstanding->isEmpty())
            <div class="py-8 text-center text-gray-400 text-sm">No outstanding balances</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Vendor</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Type</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Total Purchases</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Outstanding</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Credit Days</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($outstanding as $v)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $v->vendor_name }}</td>
                    <td class="px-4 py-3 text-xs text-gray-500 capitalize">{{ str_replace('_',' ',$v->vendor_type) }}</td>
                    <td class="px-4 py-3 text-right text-gray-700">Rs. {{ number_format($v->total_purchases, 0) }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-red-600">Rs. {{ number_format($v->outstanding_amount, 0) }}</td>
                    <td class="px-4 py-3 text-right text-gray-500">{{ $v->credit_days ?? '—' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- Due payments --}}
    @if($duePayments->isNotEmpty())
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">Pending Due Payments</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Expense</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Vendor</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Category</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Due Date</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($duePayments as $exp)
                @php $isOverdue = $exp->due_date && $exp->due_date->isPast(); @endphp
                <tr class="{{ $isOverdue ? 'bg-red-50' : 'hover:bg-[#fdf8ff]' }}">
                    <td class="px-4 py-3">
                        <a href="{{ route('finance.expenses') }}" class="text-[#6a0f70] hover:underline">{{ $exp->title }}</a>
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $exp->vendor?->vendor_name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $exp->category?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right font-semibold">Rs. {{ number_format($exp->total_amount, 0) }}</td>
                    <td class="px-4 py-3 text-right text-xs {{ $isOverdue ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                        {{ $exp->due_date?->format('d M Y') }}
                        @if($isOverdue) <span class="ml-1 text-red-500">(overdue)</span>@endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="inline-block px-2 py-0.5 text-xs rounded-full bg-yellow-100 text-yellow-700">Unpaid</span>
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
// Monthly purchases chart
new Chart(document.getElementById('monthlyChart'), {
    type: 'bar',
    data: {
        labels: {!! $monthlyTotal->pluck('month')->map(fn($m) => \Carbon\Carbon::parse($m.'-01')->format('M Y'))->toJson() !!},
        datasets: [{
            label: 'Purchases (Rs. )',
            data: {!! $monthlyTotal->pluck('total')->toJson() !!},
            backgroundColor: 'rgba(106,15,112,0.7)',
            borderColor: '#6a0f70',
            borderWidth: 1,
        }]
    },
    options: { responsive: true, plugins: { legend: { display: false } },
               scales: { y: { beginAtZero: true, ticks: { callback: v => 'Rs. '+v.toLocaleString('en-IN') } } } }
});
// Type donut chart
new Chart(document.getElementById('typeChart'), {
    type: 'doughnut',
    data: {
        labels: {!! $byType->pluck('vendor_type')->map(fn($t) => str_replace('_',' ',ucfirst($t)))->toJson() !!},
        datasets: [{
            data: {!! $byType->pluck('total')->toJson() !!},
            backgroundColor: ['#6a0f70','#9c27b0','#ce93d8','#e1bee7','#4a148c','#7b1fa2','#ab47bc','#d500f9'],
        }]
    },
    options: { responsive: true,
               plugins: { legend: { position: 'right' },
                          tooltip: { callbacks: { label: ctx => ctx.label+': Rs. '+Number(ctx.raw).toLocaleString('en-IN') } } } }
});
</script>
@endsection
