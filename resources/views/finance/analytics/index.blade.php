@extends('layouts.app')
@section('page-title', 'Analytics — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div>
        <p class="text-xs text-gray-400 uppercase tracking-widest">
            <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
            &nbsp;/&nbsp; Analytics
        </p>
        <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
            Analytics & Reporting
        </h1>
    </div>

    <div class="grid grid-cols-3 gap-4">
        @foreach([
            ['route'=>'analytics.vendor',       'title'=>'Vendor Analytics',       'desc'=>'Outstanding balances, monthly purchases, due payments by vendor',     'icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0'],
            ['route'=>'analytics.expense',      'title'=>'Expense Analytics',      'desc'=>'Category-wise, vendor-wise spend and monthly expense trends',          'icon'=>'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z'],
            ['route'=>'analytics.lab',          'title'=>'Lab Analytics',          'desc'=>'Cases, billing status, outstanding lab spend, cost per case, TAT',     'icon'=>'M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z'],
            ['route'=>'analytics.procurement',  'title'=>'Procurement Analytics', 'desc'=>'PO trends, GRN tracking, vendor invoice status, pending orders',       'icon'=>'M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4'],
            ['route'=>'analytics.cashflow',     'title'=>'Cash Flow',              'desc'=>'Monthly in/out, net cash position, payment forecasts and overdue bills','icon'=>'M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z'],
            ['route'=>'analytics.outstanding',  'title'=>'Outstanding Dashboard',  'desc'=>'All outstanding — patient, vendor, procurement, lab liabilities',       'icon'=>'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z'],
            ['route'=>'analytics.business',     'title'=>'Business Intelligence',  'desc'=>'Monthly P&L, profit margin, revenue vs expense trends',                 'icon'=>'M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z'],
            ['route'=>'analytics.audit',        'title'=>'Audit & History',        'desc'=>'Complete audit trail — payments, vouchers, expenses, lab, procurement', 'icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
        ] as $card)
        <a href="{{ route($card['route']) }}"
           class="bg-white border border-[#e8d5f0] p-5 hover:border-[#6a0f70] hover:shadow-sm transition-all group block">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-[#f9f4fb] group-hover:bg-[#ede0f4] rounded transition-colors flex-shrink-0">
                    <svg class="w-5 h-5 text-[#6a0f70]" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $card['icon'] }}"/>
                    </svg>
                </div>
                <div>
                    <h3 class="font-semibold text-gray-800 group-hover:text-[#6a0f70] transition-colors text-sm">{{ $card['title'] }}</h3>
                    <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ $card['desc'] }}</p>
                </div>
            </div>
        </a>
        @endforeach
    </div>

</div>
@endsection
