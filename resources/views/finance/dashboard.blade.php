@extends('layouts.app')
@section('page-title', 'Accounts & Finance')

@section('head-extra')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<style>
    /* ── KPI Card ── */
    .df-kpi-card {
        background: #ffffff;
        border: 1px solid rgba(185,92,183,0.15);
        border-radius: 4px;
        padding: 18px 20px;
    }

    /* ── Icon circle (top of primary KPI cards) ── */
    .df-icon-circle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        margin-bottom: 12px;
        flex-shrink: 0;
    }

    /* ── KPI value (large number) ── */
    .df-kpi-value {
        font-family: 'DM Sans', system-ui, sans-serif;
        font-size: 22px;
        font-weight: 700;
        line-height: 1.2;
        letter-spacing: -0.01em;
    }

    /* ── KPI label (metric name) ── */
    .df-kpi-label {
        font-size: 11.5px;
        font-weight: 500;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-top: 4px;
    }

    /* ── KPI insight (small sub-text) ── */
    .df-kpi-insight {
        font-size: 11px;
        color: #9ca3af;
        margin-top: 6px;
    }
</style>
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
            ['label'=>'Income',     'route'=>'finance.income'],
            ['label'=>'Expenses',   'route'=>'finance.expenses'],
            ['label'=>'Vendors',    'route'=>'finance.vendors'],
            ['label'=>'Membership', 'route'=>'finance.membership.index'],
            ['label'=>'Wallets',    'route'=>'finance.wallet.index'],
            ['label'=>'Coupons',    'route'=>'finance.coupons.index'],
            ['label'=>'CA Export',  'route'=>'finance.ca-export'],
        ] as $m)
        <a href="{{ route($m['route']) }}"
           class="text-sm border border-[#e8d5f0] text-gray-600 px-4 py-1.5 hover:border-[#6a0f70] hover:text-[#6a0f70] hover:bg-[#faf5fc] transition-colors">
            {{ $m['label'] }}
        </a>
        @endforeach
    </div>

    {{-- ── DATE FILTER ── --}}
    <div class="flex flex-wrap gap-2 items-center">
        <span class="text-xs text-gray-400 uppercase tracking-wider mr-1">Period:</span>
        @foreach(['today'=>'Today','yesterday'=>'Yesterday','week'=>'This Week','month'=>'This Month','quarter'=>'Quarter','fy'=>'Financial Year'] as $key=>$label)
        <a href="{{ route('finance.dashboard', ['preset'=>$key]) }}"
           class="text-xs px-3 py-1 border transition-colors
               {{ $dateFilter['preset'] === $key ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'border-gray-300 text-gray-600 hover:border-[#6a0f70] hover:text-[#6a0f70]' }}">
            {{ $label }}
        </a>
        @endforeach
        <form method="GET" action="{{ route('finance.dashboard') }}" class="flex gap-2 items-center ml-2">
            <input type="date" name="from" value="{{ $dateFilter['from']->toDateString() }}"
                   class="border border-gray-300 text-xs px-2 py-1 focus:outline-none focus:border-[#6a0f70]">
            <span class="text-xs text-gray-400">to</span>
            <input type="date" name="to" value="{{ $dateFilter['to']->toDateString() }}"
                   class="border border-gray-300 text-xs px-2 py-1 focus:outline-none focus:border-[#6a0f70]">
            <button type="submit"
                    class="text-xs px-3 py-1 border transition-colors
                        {{ $dateFilter['preset'] === 'custom' ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'border-gray-300 text-gray-600 hover:border-[#6a0f70] hover:text-[#6a0f70]' }}">
                Custom
            </button>
        </form>
    </div>

    {{-- ── ROW 1: PRIMARY KPI CARDS (respect the date filter above) ── --}}
    @php
    $finPrimaryKpis = [
        [
            'label'   => 'Collection',
            'value'   => 'Rs. ' . number_format($kpis['period_collection']),
            'insight' => $kpis['show_revenue_target']
                            ? 'Target Rs. ' . number_format($kpis['revenue_target']) . ' · ' . $kpis['revenue_target_pct'] . '%'
                            : $dateFilter['from']->format('d M') . ' – ' . $dateFilter['to']->format('d M'),
            'color'   => '#1a7a45',
            'bg'      => 'rgba(26,122,69,0.08)',
            'icon'    => 'M22 7 13.5 15.5 8.5 10.5 2 17M16 7h6v6',
        ],
        [
            'label'   => 'Expenses',
            'value'   => 'Rs. ' . number_format($kpis['period_expense']),
            'insight' => $kpis['period_collection'] > 0
                            ? round(($kpis['period_expense'] / $kpis['period_collection']) * 100) . '% of collection'
                            : 'No collection this period',
            'color'   => '#b52020',
            'bg'      => 'rgba(181,32,32,0.08)',
            'icon'    => 'M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6',
        ],
        [
            'label'   => 'Net Profit',
            'value'   => 'Rs. ' . number_format($kpis['period_profit']),
            'insight' => $kpis['period_profit_pct'] . '% margin' . (
                            $kpis['period_profit_pct'] >= 40 ? ' · Excellent' :
                            ($kpis['period_profit_pct'] >= 25 ? ' · Good' :
                            ($kpis['period_profit_pct'] >= 10 ? ' · Fair' :
                            ($kpis['period_profit_pct'] >= 0  ? ' · Tight' : ' · Loss')))
                         ),
            'color'   => '#6a0f70',
            'bg'      => 'rgba(106,15,112,0.08)',
            'icon'    => 'M12 22s-7-5-7-11c0-4 2.5-7 7-7s7 3 7 7c0 6-7 11-7 11z',
        ],
        [
            'label'   => 'Outstanding',
            'value'   => 'Rs. ' . number_format($kpis['outstanding_amount']),
            'insight' => 'As of today · ' . $kpis['outstanding_count'] . ' invoice' . ($kpis['outstanding_count'] == 1 ? '' : 's'),
            'color'   => '#a05c00',
            'bg'      => 'rgba(160,92,0,0.08)',
            'icon'    => 'M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10zM12 8v4M12 16h.01',
        ],
    ];
    @endphp
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;">
        @foreach($finPrimaryKpis as $kpi)
        <div class="df-kpi-card">
            <div class="df-kpi-value" style="color:{{ $kpi['color'] }};">{{ $kpi['value'] }}</div>
            <div class="df-kpi-label">{{ $kpi['label'] }}</div>
            <div class="df-kpi-insight">{{ $kpi['insight'] }}</div>
        </div>
        @endforeach
    </div>

    {{-- ── ROW 2: SECONDARY KPI STRIP ── --}}
    <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:10px;">
        @foreach([
            ['label'=>"Today's Collection",   'val'=>'today_collection',     'color'=>'#6a0f70'],
            ['label'=>'Cash In Hand',         'val'=>'cash_in_hand',         'color'=>'#1a7a45'],
            ['label'=>'Bank Balance',         'val'=>'bank_balance',         'color'=>'#1a5ea8'],
            ['label'=>'Avg Daily Collection', 'val'=>'avg_daily_collection', 'color'=>'#6a0f70'],
            ['label'=>'Projected Period Total','val'=>'projected_total',     'color'=>'#a05c00'],
        ] as $card)
        <div class="df-kpi-card">
            <div class="df-kpi-insight" style="text-transform:uppercase;letter-spacing:0.07em;margin-bottom:8px;">{{ $card['label'] }}</div>
            <div class="df-kpi-value" style="font-size:18px;color:{{ $card['color'] }};">Rs. {{ number_format($kpis[$card['val']]) }}</div>
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
            <div style="position:relative; height:220px;">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>

        {{-- Collection by Mode --}}
        <div class="bg-white border border-[#e8d5f0] p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70] mb-4">Collection by Mode</p>
            @if(count($collectionByMode))
            <div class="flex gap-4 items-center">
                <canvas id="collectionPie" width="130" height="130" style="flex-shrink:0;"></canvas>
                <div class="flex-1 space-y-3">
                    @foreach($collectionByMode as $m)
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <div class="w-2 h-2 rounded-full" style="background:{{ $m['color'] }};"></div>
                            <span class="text-xs text-gray-500">{{ $m['mode'] }}</span>
                        </div>
                        <div class="text-right">
                            <div class="text-xs font-semibold text-gray-700">{{ $m['pct'] }}%</div>
                            <div class="text-xs text-gray-400">Rs. {{ number_format($m['amt']) }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @else
            <p class="text-xs text-gray-400 py-8 text-center">No collections in this period.</p>
            @endif
        </div>

        {{-- Top Expenses --}}
        <div class="bg-white border border-[#e8d5f0] p-5">
            <p class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70] mb-4">Top Expenses</p>
            <div class="space-y-4">
                @foreach($topExpenses as $exp)
                <div>
                    <div class="flex justify-between mb-1">
                        <span class="text-xs text-gray-600">{{ $exp['category'] }}</span>
                        <span class="text-xs font-semibold text-gray-700">Rs. {{ number_format($exp['amount']) }}</span>
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
                            {{ $txn['type']==='income' ? '+' : '−' }}Rs. {{ number_format($txn['amount']) }}
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
        $annualRevenue     = $monthlyRevenue * 12;   // rough projection, based on the current calendar month
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
            <span class="text-xs bg-amber-50 border border-amber-200 text-amber-700 px-3 py-1">Indicative Only</span>
        </div>
        <div class="grid grid-cols-5 divide-x divide-[#e8d5f0]">
            @foreach([
                ['label'=>'Projected Annual Revenue',  'val'=>'Rs. '.number_format($annualRevenue),      'sub'=>'Based on this month × 12',         'hex'=>'#380740'],
                ['label'=>'Taxable Income (50% Rule)', 'val'=>'Rs. '.number_format($taxableIncome44ADA), 'sub'=>'Sec 44ADA presumptive basis',       'hex'=>'#6a0f70'],
                ['label'=>'Estimated Tax Slab',        'val'=>$slabLabel,                              'sub'=>'Highest applicable slab',          'hex'=>'#a05c00'],
                ['label'=>'Est. Annual Tax Liability', 'val'=>'Rs. '.number_format($tax),                'sub'=>'New regime, no deductions applied', 'hex'=>'#b52020'],
                ['label'=>'Advance Tax (per quarter)', 'val'=>'Rs. '.number_format($advanceTax),         'sub'=>'Due: Jun · Sep · Dec · Mar',        'hex'=>'#1a5ea8'],
            ] as $t)
            <div class="p-5">
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-2">{{ $t['label'] }}</p>
                <div class="df-kpi-value" style="font-size:20px;color:{{ $t['hex'] }};">{{ $t['val'] }}</div>
                <p class="text-xs text-gray-400 mt-1">{{ $t['sub'] }}</p>
            </div>
            @endforeach
        </div>
        <div class="px-5 py-3 bg-amber-50 border-t border-amber-100 flex justify-between items-center">
            <p class="text-xs text-amber-700">
                <strong>Advance Tax Dates:</strong> 15% by Jun 15 &nbsp;·&nbsp; 45% by Sep 15 &nbsp;·&nbsp; 75% by Dec 15 &nbsp;·&nbsp; 100% by Mar 15
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
                ['label'=>'Income',     'route'=>'finance.income'],
                ['label'=>'Expenses',   'route'=>'finance.expenses'],
                ['label'=>'Vendors',    'route'=>'finance.vendors'],
                ['label'=>'Membership', 'route'=>'finance.membership.index'],
                ['label'=>'Wallets',    'route'=>'finance.wallet.index'],
                ['label'=>'Coupons',    'route'=>'finance.coupons.index'],
                ['label'=>'CA Export',  'route'=>'finance.ca-export'],
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
        labels: {!! json_encode($trendLabels) !!},
        datasets: [
            {
                label: 'Revenue',
                data: {!! json_encode($trendRevenue) !!},
                backgroundColor: 'rgba(106,15,112,0.70)',
                borderRadius: 2,
            },
            {
                label: 'Expenses',
                data: {!! json_encode($trendExpense) !!},
                backgroundColor: 'rgba(181,32,32,0.55)',
                borderRadius: 2,
            },
        ],
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'bottom', labels: { font: { size: 10 }, boxWidth: 10 } } },
        scales: {
            x: { grid: { display: false }, ticks: { font: { size: 10 } } },
            y: {
                grid: { color: 'rgba(185,92,183,0.08)' },
                ticks: { font: { size: 10 }, callback: v => 'Rs. ' + (v >= 100000 ? (v/100000).toFixed(1)+'L' : v) },
            },
        },
    },
});

// Collection by Mode — doughnut
@if(count($collectionByMode))
const cCtx = document.getElementById('collectionPie').getContext('2d');
new Chart(cCtx, {
    type: 'doughnut',
    data: {
        labels: {!! json_encode(array_column($collectionByMode, 'mode')) !!},
        datasets: [{
            data: {!! json_encode(array_column($collectionByMode, 'pct')) !!},
            backgroundColor: {!! json_encode(array_column($collectionByMode, 'color')) !!},
            borderWidth: 0,
            hoverOffset: 4,
        }],
    },
    options: {
        responsive: false,
        cutout: '68%',
        plugins: { legend: { display: false } },
    },
});
@endif
</script>

@endsection