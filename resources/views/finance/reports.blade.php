@extends('layouts.app')
@section('page-title', 'Finance Reports')

@section('content')
<div class="p-6 space-y-5">

    {{-- HEADER --}}
    <div class="flex items-start justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a> &nbsp;/&nbsp; Reports
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">Finance Reports</h1>
        </div>
        <div class="flex gap-2">
            <button onclick="rptDownload('excel')"
                    class="inline-flex items-center gap-1 bg-emerald-600 text-white text-xs px-3 py-2 hover:bg-emerald-700">
                Download Excel
            </button>
            <button onclick="rptDownload('pdf')"
                    class="inline-flex items-center gap-1 bg-gray-600 text-white text-xs px-3 py-2 hover:bg-gray-700">
                Print / PDF
            </button>
        </div>
    </div>

    {{-- FILTER FORM --}}
    <form id="rpt-form" method="GET" action="{{ route('finance.reports') }}" class="bg-white border border-[#e8d5f0] px-5 py-4">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <div class="flex flex-wrap items-end gap-4">
            {{-- Preset buttons --}}
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">Period</p>
                <div class="flex gap-1">
                    @foreach(['fy' => 'FY', 'quarter' => 'Quarter', 'month' => 'Month', 'week' => 'Week'] as $key => $label)
                        <a href="{{ route('finance.reports') }}?tab={{ $tab }}&preset={{ $key }}"
                           class="text-xs px-2 py-1 border transition-colors
                                  {{ $preset === $key
                                     ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                     : 'bg-white text-gray-600 border-gray-300 hover:border-[#6a0f70]' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Custom range --}}
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">From</p>
                <input type="date" name="from"
                       value="{{ ($from instanceof \Carbon\Carbon) ? $from->toDateString() : $from }}"
                       class="border border-gray-300 text-xs px-2 py-1 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wider mb-1">To</p>
                <input type="date" name="to"
                       value="{{ ($to instanceof \Carbon\Carbon) ? $to->toDateString() : $to }}"
                       class="border border-gray-300 text-xs px-2 py-1 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <button type="submit" class="bg-[#6a0f70] text-white text-xs px-4 py-2 hover:bg-[#380740]">
                Apply
            </button>
        </div>
    </form>

    {{-- TAB NAV --}}
    @php
    $tabs = [
        'income'      => 'Income Summary',
        'expense'     => 'Expense Summary',
        'receivables' => 'Receivables',
        'payables'    => 'Payables',
        'membership'  => 'Membership',
        'wallet'      => 'Wallet',
        'coupon'      => 'Coupons',
        'discount'    => 'Discounts',
        'advance'     => 'Advances',
        'liability'   => 'Credit Liability',
        'collection'  => 'Daily Collection',
    ];
    @endphp
    <div class="flex flex-wrap gap-1 border-b border-gray-200">
        @foreach($tabs as $key => $label)
            <a href="{{ route('finance.reports') }}?tab={{ $key }}&preset={{ $preset }}&from={{ ($from instanceof \Carbon\Carbon) ? $from->toDateString() : $from }}&to={{ ($to instanceof \Carbon\Carbon) ? $to->toDateString() : $to }}"
               class="text-sm px-4 py-2 border-b-2 transition-colors
                      {{ $tab === $key
                         ? 'border-[#6a0f70] text-[#6a0f70] font-semibold'
                         : 'border-transparent text-gray-500 hover:text-[#6a0f70]' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- PERIOD LABEL --}}
    <p class="text-xs text-gray-400">
        Period: {{ ($from instanceof \Carbon\Carbon) ? $from->format('d M Y') : $from }}
        &mdash;
        {{ ($to instanceof \Carbon\Carbon) ? $to->format('d M Y') : $to }}
    </p>

    {{-- ── TAB CONTENT ── --}}

    @if($tab === 'income')
    {{-- INCOME SUMMARY --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Collected</p>
            <p class="text-2xl font-bold text-green-600 mt-1">&#8377;{{ number_format($data['total'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Transactions</p>
            <p class="text-2xl font-bold text-gray-700 mt-1">{{ $data['byMonth']->sum('cnt') }}</p>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="bg-white border border-gray-100">
            <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-600 uppercase tracking-wider">Monthly Breakdown</div>
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-xs text-gray-500">
                    <th class="px-4 py-2 text-left">Month</th>
                    <th class="px-4 py-2 text-right">Txns</th>
                    <th class="px-4 py-2 text-right">Amount (&#8377;)</th>
                </tr></thead>
                <tbody>
                @forelse($data['byMonth'] as $m)
                <tr class="border-t border-gray-50 hover:bg-gray-50">
                    <td class="px-4 py-2">{{ $m->month }}</td>
                    <td class="px-4 py-2 text-right">{{ $m->cnt }}</td>
                    <td class="px-4 py-2 text-right font-medium">{{ number_format($m->total, 0) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400 text-xs">No data</td></tr>
                @endforelse
                </tbody>
                <tfoot><tr class="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                    <td class="px-4 py-2">Total</td>
                    <td class="px-4 py-2 text-right">{{ $data['byMonth']->sum('cnt') }}</td>
                    <td class="px-4 py-2 text-right">{{ number_format($data['total'], 0) }}</td>
                </tr></tfoot>
            </table>
        </div>
        <div class="bg-white border border-gray-100">
            <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-600 uppercase tracking-wider">By Payment Mode</div>
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-xs text-gray-500">
                    <th class="px-4 py-2 text-left">Mode</th>
                    <th class="px-4 py-2 text-right">Count</th>
                    <th class="px-4 py-2 text-right">Amount (&#8377;)</th>
                </tr></thead>
                <tbody>
                @forelse($data['byMode'] as $m)
                <tr class="border-t border-gray-50 hover:bg-gray-50">
                    <td class="px-4 py-2 capitalize">{{ $m->payment_mode }}</td>
                    <td class="px-4 py-2 text-right">{{ $m->cnt }}</td>
                    <td class="px-4 py-2 text-right font-medium">{{ number_format($m->total, 0) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400 text-xs">No data</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="bg-white border border-gray-100">
        <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-600 uppercase tracking-wider">Top Patients by Revenue</div>
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs text-gray-500">
                <th class="px-4 py-2 text-left">#</th>
                <th class="px-4 py-2 text-left">Patient</th>
                <th class="px-4 py-2 text-left">Phone</th>
                <th class="px-4 py-2 text-right">Invoices</th>
                <th class="px-4 py-2 text-right">Total Paid (&#8377;)</th>
            </tr></thead>
            <tbody>
            @forelse($data['topPatients'] as $i => $p)
            <tr class="border-t border-gray-50 hover:bg-gray-50">
                <td class="px-4 py-2 text-gray-400">{{ $i + 1 }}</td>
                <td class="px-4 py-2 font-medium">{{ $p->name }}</td>
                <td class="px-4 py-2 text-gray-500">{{ $p->phone }}</td>
                <td class="px-4 py-2 text-right">{{ $p->invoices }}</td>
                <td class="px-4 py-2 text-right font-semibold text-green-700">{{ number_format($p->total_paid, 0) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400 text-xs">No data</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @elseif($tab === 'expense')
    {{-- EXPENSE SUMMARY --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Expenses</p>
            <p class="text-2xl font-bold text-red-600 mt-1">&#8377;{{ number_format($data['total'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Categories</p>
            <p class="text-2xl font-bold text-gray-700 mt-1">{{ $data['byCategory']->count() }}</p>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="bg-white border border-gray-100">
            <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-600 uppercase tracking-wider">By Category</div>
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-xs text-gray-500">
                    <th class="px-4 py-2 text-left">Category</th>
                    <th class="px-4 py-2 text-right">Count</th>
                    <th class="px-4 py-2 text-right">Total (&#8377;)</th>
                </tr></thead>
                <tbody>
                @forelse($data['byCategory'] as $c)
                <tr class="border-t border-gray-50 hover:bg-gray-50">
                    <td class="px-4 py-2">{{ $c->category }}</td>
                    <td class="px-4 py-2 text-right">{{ $c->cnt }}</td>
                    <td class="px-4 py-2 text-right font-medium text-red-600">{{ number_format($c->total, 0) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400 text-xs">No data</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="bg-white border border-gray-100">
            <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-600 uppercase tracking-wider">Top Vendors</div>
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-xs text-gray-500">
                    <th class="px-4 py-2 text-left">Vendor</th>
                    <th class="px-4 py-2 text-right">Bills</th>
                    <th class="px-4 py-2 text-right">Total (&#8377;)</th>
                </tr></thead>
                <tbody>
                @forelse($data['topVendors'] as $v)
                <tr class="border-t border-gray-50 hover:bg-gray-50">
                    <td class="px-4 py-2">{{ $v->vendor_name }}</td>
                    <td class="px-4 py-2 text-right">{{ $v->cnt }}</td>
                    <td class="px-4 py-2 text-right font-medium">{{ number_format($v->total, 0) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400 text-xs">No vendors linked</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @elseif($tab === 'receivables')
    {{-- OUTSTANDING RECEIVABLES --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Outstanding</p>
            <p class="text-2xl font-bold text-orange-600 mt-1">&#8377;{{ number_format($data['total'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Over 30 Days</p>
            <p class="text-2xl font-bold text-red-500 mt-1">&#8377;{{ number_format($data['over30'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Over 90 Days</p>
            <p class="text-2xl font-bold text-red-700 mt-1">&#8377;{{ number_format($data['over90'], 0) }}</p>
        </div>
    </div>
    <div class="bg-white border border-gray-100">
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs text-gray-500">
                <th class="px-4 py-2 text-left">Invoice No</th>
                <th class="px-4 py-2 text-left">Patient</th>
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-4 py-2 text-right">Total (&#8377;)</th>
                <th class="px-4 py-2 text-right">Balance Due (&#8377;)</th>
                <th class="px-4 py-2 text-right">Age</th>
            </tr></thead>
            <tbody>
            @forelse($data['invoices'] as $inv)
            <tr class="border-t border-gray-50 hover:bg-gray-50 {{ $inv->age_days > 90 ? 'bg-red-50' : ($inv->age_days > 30 ? 'bg-amber-50' : '') }}">
                <td class="px-4 py-2 font-mono text-xs">{{ $inv->invoice_number }}</td>
                <td class="px-4 py-2">{{ $inv->patient?->name }}</td>
                <td class="px-4 py-2 text-gray-500">{{ $inv->invoice_date?->format('d-m-Y') }}</td>
                <td class="px-4 py-2 text-right">{{ number_format($inv->total_amount, 0) }}</td>
                <td class="px-4 py-2 text-right font-semibold text-orange-700">{{ number_format($inv->balance_due, 0) }}</td>
                <td class="px-4 py-2 text-right text-xs {{ $inv->age_days > 90 ? 'text-red-600 font-bold' : ($inv->age_days > 30 ? 'text-amber-600' : 'text-gray-500') }}">
                    {{ $inv->age_days }}d
                </td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">No outstanding invoices</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @elseif($tab === 'payables')
    {{-- OUTSTANDING PAYABLES --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Payable</p>
            <p class="text-2xl font-bold text-orange-600 mt-1">&#8377;{{ number_format($data['total'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Overdue</p>
            <p class="text-2xl font-bold text-red-600 mt-1">&#8377;{{ number_format($data['overdue'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Over 30 Days Old</p>
            <p class="text-2xl font-bold text-red-400 mt-1">&#8377;{{ number_format($data['over30'], 0) }}</p>
        </div>
    </div>
    <div class="bg-white border border-gray-100">
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs text-gray-500">
                <th class="px-4 py-2 text-left">Title</th>
                <th class="px-4 py-2 text-left">Vendor</th>
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-4 py-2 text-left">Due</th>
                <th class="px-4 py-2 text-right">Amount (&#8377;)</th>
                <th class="px-4 py-2 text-right">Age</th>
            </tr></thead>
            <tbody>
            @forelse($data['bills'] as $b)
            <tr class="border-t border-gray-50 hover:bg-gray-50 {{ $b->overdue ? 'bg-red-50' : '' }}">
                <td class="px-4 py-2">{{ $b->title }}</td>
                <td class="px-4 py-2 text-gray-500">{{ $b->vendor?->vendor_name ?? '—' }}</td>
                <td class="px-4 py-2 text-gray-500">{{ $b->expense_date?->format('d-m-Y') }}</td>
                <td class="px-4 py-2 text-xs {{ $b->overdue ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                    {{ $b->due_date ? $b->due_date->format('d-m-Y') : '—' }}
                    @if($b->overdue) <span class="ml-1 bg-red-100 text-red-700 px-1 py-0.5 rounded text-xs">Overdue</span>@endif
                </td>
                <td class="px-4 py-2 text-right font-semibold text-red-700">{{ number_format($b->total_amount, 0) }}</td>
                <td class="px-4 py-2 text-right text-xs text-gray-500">{{ $b->age_days }}d</td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400 text-sm">No outstanding payables</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @elseif($tab === 'membership')
    {{-- MEMBERSHIP REVENUE --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Revenue (Period)</p>
            <p class="text-2xl font-bold text-[#6a0f70] mt-1">&#8377;{{ number_format($data['total'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">New Subscriptions</p>
            <p class="text-2xl font-bold text-gray-700 mt-1">{{ $data['subscriptions']->count() }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Active Members</p>
            <p class="text-2xl font-bold text-green-600 mt-1">{{ $data['active'] }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Expired</p>
            <p class="text-2xl font-bold text-gray-400 mt-1">{{ $data['expired'] }}</p>
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="bg-white border border-gray-100">
            <div class="px-4 py-3 border-b text-xs font-semibold text-gray-600 uppercase tracking-wider">By Plan</div>
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-xs text-gray-500">
                    <th class="px-4 py-2 text-left">Plan</th>
                    <th class="px-4 py-2 text-right">Count</th>
                    <th class="px-4 py-2 text-right">Revenue (&#8377;)</th>
                </tr></thead>
                <tbody>
                @forelse($data['byPlan'] as $plan => $stats)
                <tr class="border-t border-gray-50 hover:bg-gray-50">
                    <td class="px-4 py-2">{{ $plan }}</td>
                    <td class="px-4 py-2 text-right">{{ $stats['count'] }}</td>
                    <td class="px-4 py-2 text-right font-medium">{{ number_format($stats['revenue'], 0) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400 text-xs">No data</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="bg-white border border-gray-100">
            <div class="px-4 py-3 border-b text-xs font-semibold text-gray-600 uppercase tracking-wider">Recent Subscriptions</div>
            <table class="w-full text-sm">
                <thead><tr class="bg-gray-50 text-xs text-gray-500">
                    <th class="px-4 py-2 text-left">Date</th>
                    <th class="px-4 py-2 text-left">Patient</th>
                    <th class="px-4 py-2 text-left">Plan</th>
                    <th class="px-4 py-2 text-right">Amount (&#8377;)</th>
                </tr></thead>
                <tbody>
                @forelse($data['subscriptions']->take(10) as $s)
                <tr class="border-t border-gray-50 hover:bg-gray-50">
                    <td class="px-4 py-2 text-xs text-gray-500">{{ $s->created_at?->format('d-m-Y') }}</td>
                    <td class="px-4 py-2">{{ $s->patient?->name }}</td>
                    <td class="px-4 py-2 text-xs text-gray-500">{{ $s->plan?->name }}</td>
                    <td class="px-4 py-2 text-right font-medium">{{ number_format($s->amount_paid, 0) }}</td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400 text-xs">No subscriptions in period</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @elseif($tab === 'wallet')
    {{-- WALLET SUMMARY --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Credits Issued</p>
            <p class="text-2xl font-bold text-green-600 mt-1">&#8377;{{ number_format($data['credits'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Utilized</p>
            <p class="text-2xl font-bold text-red-500 mt-1">&#8377;{{ number_format($data['debits'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Outstanding Balance</p>
            <p class="text-2xl font-bold text-[#6a0f70] mt-1">&#8377;{{ number_format($data['outstanding'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Patients w/ Balance</p>
            <p class="text-2xl font-bold text-gray-700 mt-1">{{ $data['patients'] }}</p>
        </div>
    </div>
    <div class="bg-white border border-gray-100">
        <div class="px-4 py-3 border-b text-xs font-semibold text-gray-600 uppercase tracking-wider">Monthly Wallet Activity</div>
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs text-gray-500">
                <th class="px-4 py-2 text-left">Month</th>
                <th class="px-4 py-2 text-right">Credits (&#8377;)</th>
                <th class="px-4 py-2 text-right">Debits (&#8377;)</th>
            </tr></thead>
            <tbody>
            @php
                $months = $data['monthly']->groupBy('month');
            @endphp
            @forelse($months as $month => $rows)
            <tr class="border-t border-gray-50 hover:bg-gray-50">
                <td class="px-4 py-2">{{ $month }}</td>
                <td class="px-4 py-2 text-right text-green-600">
                    {{ number_format($rows->where('direction', 'credit')->sum('total'), 0) }}
                </td>
                <td class="px-4 py-2 text-right text-red-500">
                    {{ number_format($rows->where('direction', 'debit')->sum('total'), 0) }}
                </td>
            </tr>
            @empty
            <tr><td colspan="3" class="px-4 py-6 text-center text-gray-400 text-xs">No wallet activity</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @elseif($tab === 'coupon')
    {{-- COUPON SUMMARY --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Uses</p>
            <p class="text-2xl font-bold text-[#6a0f70] mt-1">{{ $data['totalUsed'] }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Discount Given</p>
            <p class="text-2xl font-bold text-orange-600 mt-1">&#8377;{{ number_format($data['totalDiscount'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Coupons Used</p>
            <p class="text-2xl font-bold text-gray-700 mt-1">{{ $data['byCoupon']->where('used_count', '>', 0)->count() }}</p>
        </div>
    </div>
    <div class="bg-white border border-gray-100">
        <div class="px-4 py-3 border-b text-xs font-semibold text-gray-600 uppercase tracking-wider">Coupon Performance</div>
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs text-gray-500">
                <th class="px-4 py-2 text-left">Coupon Code</th>
                <th class="px-4 py-2 text-left">Type</th>
                <th class="px-4 py-2 text-right">Uses</th>
                <th class="px-4 py-2 text-right">Total Discount (&#8377;)</th>
            </tr></thead>
            <tbody>
            @forelse($data['byCoupon']->where('used_count', '>', 0) as $c)
            <tr class="border-t border-gray-50 hover:bg-gray-50">
                <td class="px-4 py-2 font-mono text-xs font-bold">{{ $c->code }}</td>
                <td class="px-4 py-2 text-xs text-gray-500 capitalize">{{ $c->discount_type ?? '' }}</td>
                <td class="px-4 py-2 text-right">{{ $c->used_count }}</td>
                <td class="px-4 py-2 text-right font-medium text-orange-700">{{ number_format($c->total_discount ?? 0, 0) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400 text-sm">No coupon usage in this period</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── DISCOUNT REPORT (Coupon vs Manual) ── --}}
    @elseif($tab === 'discount')
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Coupon Discount</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">&#8377;{{ number_format($data['couponTotal'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Manual Discount</p>
            <p class="text-2xl font-bold text-rose-600 mt-1">&#8377;{{ number_format($data['manualTotal'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Combined</p>
            <p class="text-2xl font-bold text-gray-800 mt-1">&#8377;{{ number_format($data['couponTotal'] + $data['manualTotal'], 0) }}</p>
        </div>
    </div>
    <div class="bg-white border border-gray-100">
        <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-600 uppercase tracking-wider">Manual Discounts</div>
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs text-gray-500">
                <th class="px-4 py-2 text-left">Invoice</th><th class="px-4 py-2 text-left">Patient</th>
                <th class="px-4 py-2 text-right">Amount</th><th class="px-4 py-2 text-left">Reason</th>
                <th class="px-4 py-2 text-left">Applied By</th><th class="px-4 py-2 text-left">Date</th>
            </tr></thead>
            <tbody>
            @forelse($data['manualInvoices'] as $inv)
            <tr class="border-t border-gray-50 hover:bg-gray-50">
                <td class="px-4 py-2"><a href="{{ route('billing.show', $inv) }}" class="text-[#6a0f70] hover:underline">{{ $inv->invoice_number }}</a></td>
                <td class="px-4 py-2">{{ $inv->patient?->name }}</td>
                <td class="px-4 py-2 text-right font-medium text-rose-600">&#8377;{{ number_format($inv->manual_discount_amount, 2) }}</td>
                <td class="px-4 py-2 text-gray-500">{{ $inv->manual_discount_reason }}</td>
                <td class="px-4 py-2 text-gray-500">{{ $inv->manualDiscountApplier?->name ?? '—' }}</td>
                <td class="px-4 py-2 text-gray-500">{{ $inv->manual_discount_at?->format('d M Y') }}</td>
            </tr>
            @empty
            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400 text-xs">No manual discounts in this period</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="bg-white border border-gray-100">
        <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-600 uppercase tracking-wider">Coupon Discounts</div>
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs text-gray-500">
                <th class="px-4 py-2 text-left">Coupon</th><th class="px-4 py-2 text-left">Patient</th>
                <th class="px-4 py-2 text-right">Amount</th><th class="px-4 py-2 text-left">Date</th>
            </tr></thead>
            <tbody>
            @forelse($data['couponUsages'] as $u)
            <tr class="border-t border-gray-50 hover:bg-gray-50">
                <td class="px-4 py-2 font-medium">{{ $u->coupon?->code ?? '—' }}</td>
                <td class="px-4 py-2">{{ $u->patient?->name }}</td>
                <td class="px-4 py-2 text-right font-medium text-blue-600">&#8377;{{ number_format($u->discount_amount, 2) }}</td>
                <td class="px-4 py-2 text-gray-500">{{ $u->used_at?->format('d M Y') }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400 text-xs">No coupon discounts in this period</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── ADVANCE PAYMENTS REPORT ── --}}
    @elseif($tab === 'advance')
    <div class="grid grid-cols-2 gap-4">
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Total Advances</p>
            <p class="text-2xl font-bold text-green-600 mt-1">&#8377;{{ number_format($data['total'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Deposits</p>
            <p class="text-2xl font-bold text-gray-700 mt-1">{{ $data['count'] }}</p>
        </div>
    </div>
    <div class="bg-white border border-gray-100">
        <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-600 uppercase tracking-wider">Advance Deposits (into wallet, no invoice)</div>
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs text-gray-500">
                <th class="px-4 py-2 text-left">Date</th><th class="px-4 py-2 text-left">Patient</th>
                <th class="px-4 py-2 text-right">Amount</th><th class="px-4 py-2 text-left">Mode</th><th class="px-4 py-2 text-left">Notes</th>
            </tr></thead>
            <tbody>
            @forelse($data['advances'] as $a)
            <tr class="border-t border-gray-50 hover:bg-gray-50">
                <td class="px-4 py-2 text-gray-500">{{ $a->created_at?->format('d M Y') }}</td>
                <td class="px-4 py-2">{{ $a->patient?->name }}</td>
                <td class="px-4 py-2 text-right font-medium text-green-600">&#8377;{{ number_format($a->amount, 2) }}</td>
                <td class="px-4 py-2 text-gray-500">{{ ucfirst(str_replace('_',' ', $a->payment_mode ?? '—')) }}</td>
                <td class="px-4 py-2 text-gray-500">{{ $a->notes }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400 text-xs">No advance payments in this period</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── CREDIT LIABILITY + OUTSTANDING AFTER WALLET ── --}}
    @elseif($tab === 'liability')
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Credit Liability (Total)</p>
            <p class="text-2xl font-bold text-[#6a0f70] mt-1">&#8377;{{ number_format($data['totalLiability'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Promotional</p>
            <p class="text-2xl font-bold text-amber-600 mt-1">&#8377;{{ number_format($data['promoTotal'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Permanent</p>
            <p class="text-2xl font-bold text-blue-600 mt-1">&#8377;{{ number_format($data['permTotal'], 0) }}</p>
        </div>
    </div>
    <div class="bg-white border border-gray-100">
        <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-600 uppercase tracking-wider">Wallet Balances</div>
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs text-gray-500">
                <th class="px-4 py-2 text-left">Patient</th><th class="px-4 py-2 text-right">Promotional</th>
                <th class="px-4 py-2 text-right">Permanent</th><th class="px-4 py-2 text-right">Total</th>
            </tr></thead>
            <tbody>
            @forelse($data['wallets'] as $w)
            <tr class="border-t border-gray-50 hover:bg-gray-50">
                <td class="px-4 py-2">{{ $w->patient?->name ?? '—' }}</td>
                <td class="px-4 py-2 text-right text-amber-600">&#8377;{{ number_format($w->balance_promotional, 0) }}</td>
                <td class="px-4 py-2 text-right text-blue-600">&#8377;{{ number_format($w->balance_permanent, 0) }}</td>
                <td class="px-4 py-2 text-right font-semibold text-[#6a0f70]">&#8377;{{ number_format($w->balance_total, 0) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400 text-xs">No wallet balances</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="bg-white border border-gray-100">
        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Outstanding After Wallet</span>
            <span class="text-xs text-gray-400">Net &#8377;{{ number_format($data['totalNet'], 0) }} of &#8377;{{ number_format($data['totalOutstanding'], 0) }} outstanding</span>
        </div>
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs text-gray-500">
                <th class="px-4 py-2 text-left">Patient</th><th class="px-4 py-2 text-right">Outstanding</th>
                <th class="px-4 py-2 text-right">Wallet</th><th class="px-4 py-2 text-right">Net Due</th>
            </tr></thead>
            <tbody>
            @forelse($data['outstandingRows'] as $r)
            <tr class="border-t border-gray-50 hover:bg-gray-50">
                <td class="px-4 py-2">{{ $r->name }}</td>
                <td class="px-4 py-2 text-right">&#8377;{{ number_format($r->outstanding, 0) }}</td>
                <td class="px-4 py-2 text-right text-[#6a0f70]">&#8377;{{ number_format($r->wallet, 0) }}</td>
                <td class="px-4 py-2 text-right font-semibold {{ $r->net > 0 ? 'text-red-500' : 'text-green-600' }}">&#8377;{{ number_format($r->net, 0) }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400 text-xs">No outstanding invoices</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── DAILY COLLECTION (split) ── --}}
    @elseif($tab === 'collection')
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Invoice Collections</p>
            <p class="text-2xl font-bold text-green-600 mt-1">&#8377;{{ number_format($data['invoiceTotal'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Advance Collections</p>
            <p class="text-2xl font-bold text-emerald-600 mt-1">&#8377;{{ number_format($data['advanceTotal'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Wallet Utilization</p>
            <p class="text-2xl font-bold text-[#6a0f70] mt-1">&#8377;{{ number_format($data['walletUseTotal'], 0) }}</p>
        </div>
        <div class="bg-white border border-gray-100 p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider">Refunds</p>
            <p class="text-2xl font-bold text-red-500 mt-1">&#8377;{{ number_format($data['refundTotal'], 0) }}</p>
        </div>
    </div>
    <div class="bg-white border border-gray-100">
        <div class="px-4 py-3 border-b border-gray-100 text-xs font-semibold text-gray-600 uppercase tracking-wider">Daily Breakdown</div>
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs text-gray-500">
                <th class="px-4 py-2 text-left">Date</th>
                <th class="px-4 py-2 text-right">Invoice</th><th class="px-4 py-2 text-right">Advance</th>
                <th class="px-4 py-2 text-right">Wallet Used</th><th class="px-4 py-2 text-right">Refunds</th>
            </tr></thead>
            <tbody>
            @forelse($data['daily'] as $d)
            <tr class="border-t border-gray-50 hover:bg-gray-50">
                <td class="px-4 py-2">{{ \Carbon\Carbon::parse($d->date)->format('d M Y') }}</td>
                <td class="px-4 py-2 text-right">&#8377;{{ number_format($d->invoice, 0) }}</td>
                <td class="px-4 py-2 text-right">&#8377;{{ number_format($d->advance, 0) }}</td>
                <td class="px-4 py-2 text-right text-[#6a0f70]">&#8377;{{ number_format($d->wallet_use, 0) }}</td>
                <td class="px-4 py-2 text-right text-red-500">&#8377;{{ number_format($d->refund, 0) }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400 text-sm">No collections in this period</td></tr>
            @endforelse
            </tbody>
            <tfoot><tr class="border-t-2 border-gray-200 bg-gray-50 font-semibold">
                <td class="px-4 py-2">Total</td>
                <td class="px-4 py-2 text-right">&#8377;{{ number_format($data['invoiceTotal'], 0) }}</td>
                <td class="px-4 py-2 text-right">&#8377;{{ number_format($data['advanceTotal'], 0) }}</td>
                <td class="px-4 py-2 text-right text-[#6a0f70]">&#8377;{{ number_format($data['walletUseTotal'], 0) }}</td>
                <td class="px-4 py-2 text-right text-red-500">&#8377;{{ number_format($data['refundTotal'], 0) }}</td>
            </tr></tfoot>
        </table>
    </div>
    @endif

</div>

<script>
function rptDownload(format) {
    var form = document.getElementById('rpt-form');
    form.querySelectorAll('.dl-inject').forEach(function(el) { el.remove(); });

    var d = document.createElement('input');
    d.type = 'hidden'; d.name = 'download'; d.value = '1'; d.className = 'dl-inject';
    form.appendChild(d);

    var f = document.createElement('input');
    f.type = 'hidden'; f.name = 'format'; f.value = format; f.className = 'dl-inject';
    form.appendChild(f);

    form.submit();

    setTimeout(function() {
        form.querySelectorAll('.dl-inject').forEach(function(el) { el.remove(); });
    }, 500);
}
</script>
@endsection
