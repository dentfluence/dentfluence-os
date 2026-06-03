@extends('layouts.app')
@section('page-title', 'Accounts & Finance')

@section('head-extra')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endsection

@section('content')
<div class="p-6 space-y-6">

    {{-- ── PAGE HEADER ── --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-[#6a0f70]" style="font-family:'Cormorant Garamond',serif;">
                Accounts & Finance
            </h1>
            <p class="text-xs text-gray-400 uppercase tracking-widest mt-0.5">
                {{ now()->format('l, d F Y') }} &nbsp;·&nbsp; Financial Health Overview
            </p>
        </div>
        <div class="flex gap-2 items-center">
            <select class="text-sm border border-gray-300 bg-white text-gray-600 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                <option>This Month</option>
                <option>Last Month</option>
                <option>This Quarter</option>
                <option>This Year</option>
            </select>
            <a href="{{ route('finance.expenses') }}"
               class="inline-flex items-center gap-2 border border-[#6a0f70] text-[#6a0f70] text-sm px-4 py-2 hover:bg-[#6a0f70] hover:text-white transition-colors">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Expense
            </a>
            <a href="{{ route('finance.ca-export') }}"
               class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Send to CA
            </a>
        </div>
    </div>

    {{-- ── MODULE NAV ── --}}
    <div class="flex gap-2 flex-wrap">
        @foreach([
            ['label'=>'Income',    'route'=>'finance.income'],
            ['label'=>'Expenses',  'route'=>'finance.expenses'],
            ['label'=>'Vendors',   'route'=>'finance.vendors'],
            ['label'=>'Payroll',   'route'=>'finance.payroll'],
            ['label'=>'Cashbook',  'route'=>'finance.cashbook'],
            ['label'=>'Banking',   'route'=>'finance.banking'],
            ['label'=>'CA Export', 'route'=>'finance.ca-export'],
            ['label'=>'GST',       'route'=>'finance.gst'],
        ] as $m)
        <a href="{{ route($m['route']) }}"
           class="text-sm border border-[#e8d5f0] text-gray-600 px-4 py-1.5 hover:border-[#6a0f70] hover:text-[#6a0f70] hover:bg-[#faf5fc] transition-colors">
            {{ $m['label'] }}
        </a>
        @endforeach
    </div>

    {{-- ── ROW 1: PRIMARY KPI CARDS ── --}}
    <div class="grid grid-cols-4 gap-4">

        <div class="bg-white border border-[#e8d5f0] p-5">
            <div class="flex items-start justify-between mb-3">
                <p class="text-xs text-gray-400 uppercase tracking-widest">Today's Collection</p>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg>
            </div>
            <p class="text-4xl font-semibold text-[#380740]" style="font-family:'Cormorant Garamond',serif;">₹{{ number_format($kpis['today_collection']) }}</p>
            <p class="text-xs text-green-600 mt-1">↑ +12% vs yesterday</p>
        </div>

        <div class="bg-white border border-[#e8d5f0] p-5">
            <div class="flex items-start justify-between mb-3">
                <p class="text-xs text-gray-400 uppercase tracking-widest">Monthly Revenue</p>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.5"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
            </div>
            <p class="text-4xl font-semibold text-[#380740]" style="font-family:'Cormorant Garamond',serif;">₹{{ number_format($kpis['monthly_revenue']) }}</p>
            <p class="text-xs text-gray-400 mt-1">Target ₹5,00,000 &nbsp;·&nbsp; <span class="text-green-600">82%</span></p>
        </div>

        <div class="bg-white border border-[#e8d5f0] p-5">
            <div class="flex items-start justify-between mb-3">
                <p class="text-xs text-gray-400 uppercase tracking-widest">Net Profit</p>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5"><path d="M12 22C12 22 5 17 5 11C5 7 7.5 4 12 4C16.5 4 19 7 19 11C19 17 12 22 12 22Z"/></svg>
            </div>
            <p class="text-4xl font-semibold text-[#380740]" style="font-family:'Cormorant Garamond',serif;">₹{{ number_format($kpis['monthly_profit']) }}</p>
            <p class="text-xs text-[#6a0f70] mt-1">{{ $kpis['profit_percentage'] }}% margin &nbsp;·&nbsp; Excellent</p>
        </div>

        <div class="bg-white border border-[#e8d5f0] p-5">
            <div class="flex items-start justify-between mb-3">
                <p class="text-xs text-gray-400 uppercase tracking-widest">Outstanding</p>
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <p class="text-4xl font-semibold text-[#380740]" style="font-family:'Cormorant Garamond',serif;">₹{{ number_format($kpis['outstanding_amount']) }}</p>
            <p class="text-xs text-amber-600 mt-1">Pending: ₹{{ number_format($kpis['pending_payments']) }} &nbsp;·&nbsp; 14 patients</p>
        </div>
    </div>

    {{-- ── ROW 2: SECONDARY KPI STRIP ── --}}
    <div class="grid grid-cols-5 gap-3">
        @foreach([
            ['label'=>'Cash In Hand',        'val'=>'cash_in_hand',         'color'=>'text-green-600'],
            ['label'=>'Bank Balance',         'val'=>'bank_balance',         'color'=>'text-blue-600'],
            ['label'=>'Today Expenses',       'val'=>'today_expenses',       'color'=>'text-red-500'],
            ['label'=>'Avg Daily Collection', 'val'=>'avg_daily_collection', 'color'=>'text-[#6a0f70]'],
            ['label'=>'Projected Month End',  'val'=>'projected_month_end',  'color'=>'text-amber-600'],
        ] as $card)
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest mb-2">{{ $card['label'] }}</p>
            <p class="text-xl font-semibold {{ $card['color'] }}">₹{{ number_format($kpis[$card['val']]) }}</p>
        </div>
        @endforeach
    </div>

    {{-- ── ROW 3: CHARTS ── --}}
    <div class="grid grid-cols-3 gap-4">

        {{-- Revenue Chart --}}
        <div class="col-span-1 bg-white border border-[#e8d5f0] p-5">
            <div class="flex justify-between items-center mb-4">
                <p class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70]">Revenue vs Expenses</p>
                <span class="text-xs text-gray-400">Last 6 months</span>
            </div>
            <canvas id="revenueChart" height="180"></canvas>
        </div>

        {{-- Collection by Mode --}}
        <div class="bg-white border border-[#e8d5f0] p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70] mb-4">Collection by Mode</p>
            <div class="flex gap-4 items-center">
                <canvas id="collectionPie" width="130" height="130" style="flex-shrink:0;"></canvas>
                <div class="flex-1 space-y-3">
                    @foreach([
                        ['mode'=>'UPI',           'pct'=>42,'color'=>'bg-purple-400','amt'=>'₹1,73,040'],
                        ['mode'=>'Cash',          'pct'=>28,'color'=>'bg-green-400', 'amt'=>'₹1,15,360'],
                        ['mode'=>'Card',          'pct'=>18,'color'=>'bg-blue-400',  'amt'=>'₹74,160'],
                        ['mode'=>'Bank Transfer', 'pct'=>12,'color'=>'bg-amber-400', 'amt'=>'₹49,440'],
                    ] as $m)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full {{ $m['color'] }}"></div>
                            <span class="text-xs text-gray-500">{{ $m['mode'] }}</span>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-semibold text-gray-700">{{ $m['pct'] }}%</div>
                            <div class="text-xs text-gray-400">{{ $m['amt'] }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Top Expenses --}}
        <div class="bg-white border border-[#e8d5f0] p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70] mb-4">Top Expenses</p>
            <div class="space-y-4">
                @foreach($topExpenses as $exp)
                <div>
                    <div class="flex justify-between mb-1">
                        <span class="text-xs text-gray-600">{{ $exp['category'] }}</span>
                        <span class="text-xs font-semibold text-gray-700">₹{{ number_format($exp['amount']) }}</span>
                    </div>
                    <div class="h-1.5 bg-[#f3e8f4] rounded-full">
                        <div class="h-1.5 bg-[#6a0f70] rounded-full" style="width:{{ $exp['percent'] }}%"></div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── ROW 4: TRANSACTIONS + QUICK ACTIONS ── --}}
    <div class="grid gap-4" style="grid-template-columns:1fr 260px;">

        {{-- Recent Transactions --}}
        <div class="bg-white border border-[#e8d5f0]">
            <div class="flex justify-between items-center px-5 py-4 border-b border-[#e8d5f0]">
                <h2 class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70]">Recent Transactions</h2>
                <a href="{{ route('finance.income') }}" class="text-xs text-[#6a0f70] hover:underline">View All →</a>
            </div>
            <table class="w-full">
                <thead>
                    <tr class="border-b border-[#e8d5f0] bg-[#faf5fc]">
                        <th class="text-left px-5 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Name / Ref</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Category</th>
                        <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Mode</th>
                        <th class="text-right px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Amount</th>
                        <th class="text-right px-5 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">Time</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($recentTransactions as $txn)
                    <tr class="border-b border-[#e8d5f0] hover:bg-[#faf5fc] transition-colors">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full flex items-center justify-center {{ $txn['type']==='income' ? 'bg-green-100' : 'bg-red-100' }}">
                                    @if($txn['type']==='income')
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                                    @else
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                                    @endif
                                </div>
                                <span class="text-sm text-gray-700">{{ $txn['patient'] }}</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs text-gray-500">{{ $txn['category'] }}</td>
                        <td class="px-4 py-3">
                            <span class="text-xs bg-[#f3e8f4] text-[#6a0f70] px-2 py-0.5 rounded">{{ $txn['mode'] }}</span>
                        </td>
                        <td class="px-4 py-3 text-right text-sm font-semibold {{ $txn['type']==='income' ? 'text-green-600' : 'text-red-500' }}">
                            {{ $txn['type']==='income' ? '+' : '−' }}₹{{ number_format($txn['amount']) }}
                        </td>
                        <td class="px-5 py-3 text-right text-xs text-gray-400">{{ $txn['date'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Quick Actions --}}
        <div class="bg-white border border-[#e8d5f0] p-5">
            <h2 class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70] mb-4">Quick Actions</h2>
            <div class="space-y-2">
                @foreach([
                    ['label'=>'Add Expense',    'href'=>'finance.expenses',  'color'=>'text-red-500'],
                    ['label'=>'Record Payment', 'href'=>'finance.income',    'color'=>'text-green-600'],
                    ['label'=>'Vendor Payment', 'href'=>'finance.vendors',   'color'=>'text-blue-600'],
                    ['label'=>'Salary Entry',   'href'=>'finance.payroll',   'color'=>'text-[#6a0f70]'],
                    ['label'=>'Cash Entry',     'href'=>'finance.cashbook',  'color'=>'text-amber-600'],
                    ['label'=>'Export to CA',   'href'=>'finance.ca-export', 'color'=>'text-green-700'],
                ] as $action)
                <a href="{{ route($action['href']) }}"
                   class="flex items-center justify-between p-3 border border-[#e8d5f0] hover:border-[#6a0f70] hover:bg-[#faf5fc] transition-colors group">
                    <span class="text-sm text-gray-600 group-hover:text-[#380740]">{{ $action['label'] }}</span>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>
                </a>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── TAX ESTIMATOR ── --}}
    @php
        $annualRevenue     = $kpis['monthly_revenue'] * 12;   // rough projection
        $taxableIncome44ADA = $annualRevenue * 0.50;           // Section 44ADA: 50% presumptive
        // New Tax Regime slabs FY 2025-26
        $tax = 0;
        $slabs = [
            [300000, 700000,  0.05],
            [700000, 1000000, 0.10],
            [1000000,1200000, 0.15],
            [1200000,1500000, 0.20],
            [1500000, PHP_INT_MAX, 0.30],
        ];
        foreach ($slabs as [$from, $to, $rate]) {
            if ($taxableIncome44ADA > $from) {
                $tax += (min($taxableIncome44ADA, $to) - $from) * $rate;
            }
        }
        $tax       = max(0, $tax);
        $taxMonthly = round($tax / 12);
        $advanceTax = round($tax / 4);
        // Slab label
        $slabLabel = $taxableIncome44ADA <= 300000 ? 'Nil' :
                    ($taxableIncome44ADA <= 700000 ? '5%' :
                    ($taxableIncome44ADA <= 1000000 ? '10%' :
                    ($taxableIncome44ADA <= 1200000 ? '15%' :
                    ($taxableIncome44ADA <= 1500000 ? '20%' : '30%'))));
    @endphp

    <div class="bg-white border border-[#e8d5f0]">
        <div class="flex justify-between items-center px-5 py-4 border-b border-[#e8d5f0]">
            <div>
                <h2 class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70]">Income Tax Estimator</h2>
                <p class="text-xs text-gray-400 mt-0.5">Based on Section 44ADA (Presumptive) · New Tax Regime FY 2025-26 · <span class="text-amber-600">Estimate only — confirm with your CA</span></p>
            </div>
            <span class="text-xs bg-amber-50 border border-amber-200 text-amber-700 px-3 py-1">⚠ Indicative Only</span>
        </div>
        <div class="grid grid-cols-5 divide-x divide-[#e8d5f0]">
            @foreach([
                ['label'=>'Projected Annual Revenue',  'val'=>'₹'.number_format($annualRevenue),      'sub'=>'Based on this month × 12',         'color'=>'text-[#380740]'],
                ['label'=>'Taxable Income (50% Rule)', 'val'=>'₹'.number_format($taxableIncome44ADA), 'sub'=>'Sec 44ADA presumptive basis',       'color'=>'text-[#6a0f70]'],
                ['label'=>'Estimated Tax Slab',        'val'=>$slabLabel,                              'sub'=>'Highest applicable slab',          'color'=>'text-amber-600'],
                ['label'=>'Est. Annual Tax Liability', 'val'=>'₹'.number_format($tax),                'sub'=>'New regime, no deductions applied', 'color'=>'text-red-500'],
                ['label'=>'Advance Tax (per quarter)', 'val'=>'₹'.number_format($advanceTax),         'sub'=>'Due: Jun · Sep · Dec · Mar',        'color'=>'text-blue-600'],
            ] as $t)
            <div class="p-5">
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-2">{{ $t['label'] }}</p>
                <p class="text-2xl font-semibold {{ $t['color'] }}" style="font-family:'Cormorant Garamond',serif;">{{ $t['val'] }}</p>
                <p class="text-xs text-gray-400 mt-1">{{ $t['sub'] }}</p>
            </div>
            @endforeach
        </div>
        <div class="px-5 py-3 bg-amber-50 border-t border-amber-100 flex justify-between items-center">
            <p class="text-xs text-amber-700">
                💡 <strong>Advance Tax Dates:</strong> 15% by Jun 15 &nbsp;·&nbsp; 45% by Sep 15 &nbsp;·&nbsp; 75% by Dec 15 &nbsp;·&nbsp; 100% by Mar 15
                &nbsp;&nbsp;|&nbsp;&nbsp; This is a rough estimate. Actual tax depends on deductions, regime choice & CA advice.
            </p>
            <a href="{{ route('finance.ca-export') }}" class="text-xs bg-[#6a0f70] text-white px-4 py-1.5 hover:bg-[#380740] transition-colors flex-shrink-0 ml-4">Discuss with CA →</a>
        </div>
    </div>

    {{-- ── FINANCE MODULE TABS ── --}}
    <div class="bg-white border border-[#e8d5f0] p-5">
        <p class="text-xs text-gray-400 uppercase tracking-widest mb-3">Finance Modules</p>
        <div class="flex gap-2 flex-wrap">
            @foreach([
                ['label'=>'Income',    'route'=>'finance.income'],
                ['label'=>'Expenses',  'route'=>'finance.expenses'],
                ['label'=>'Vendors',   'route'=>'finance.vendors'],
                ['label'=>'Payroll',   'route'=>'finance.payroll'],
                ['label'=>'Cashbook',  'route'=>'finance.cashbook'],
                ['label'=>'Banking',   'route'=>'finance.banking'],
                ['label'=>'CA Export', 'route'=>'finance.ca-export'],
                ['label'=>'GST',       'route'=>'finance.gst'],
            ] as $tab)
            <a href="{{ route($tab['route']) }}"
               class="text-xs border border-[#e8d5f0] text-gray-600 px-4 py-2 hover:border-[#6a0f70] hover:text-[#6a0f70] hover:bg-[#faf5fc] transition-colors">
                {{ $tab['label'] }}
            </a>
            @endforeach
        </div>
    </div>

</div>

<script>
const rCtx = document.getElementById('revenueChart').getContext('2d');
new Chart(rCtx, {
    type: 'bar',
    data: {
        labels: ['Dec','Jan','Feb','Mar','Apr','May'],
        datasets: [
            { label:'Revenue', data:[340000,380000,295000,420000,395000,412000], backgroundColor:'rgba(106,15,112,0.55)', borderColor:'rgba(106,15,112,0.80)', borderWidth:1, borderRadius:2 },
            { label:'Expenses', data:[82000,91000,78000,105000,88000,98000], backgroundColor:'rgba(239,68,68,0.25)', borderColor:'rgba(239,68,68,0.50)', borderWidth:1, borderRadius:2 }
        ]
    },
    options: {
        responsive:true, maintainAspectRatio:true,
        plugins: { legend: { labels: { color:'#6b7280', font:{ family:'DM Sans', size:11 } } } },
        scales: {
            x: { ticks:{ color:'#9ca3af', font:{size:11} }, grid:{ color:'#f3e8f0' } },
            y: { ticks:{ color:'#9ca3af', font:{size:11}, callback: v=>'₹'+(v/1000)+'K' }, grid:{ color:'#f3e8f0' } }
        }
    }
});
const pCtx = document.getElementById('collectionPie').getContext('2d');
new Chart(pCtx, {
    type:'doughnut',
    data: {
        labels:['UPI','Cash','Card','Bank'],
        datasets:[{ data:[42,28,18,12], backgroundColor:['#a78bfa','#4ade80','#60a5fa','#fbbf24'], borderColor:'#ffffff', borderWidth:2 }]
    },
    options: { cutout:'68%', plugins:{ legend:{ display:false } } }
});
</script>
@endsection
