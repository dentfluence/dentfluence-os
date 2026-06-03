@extends('layouts.app')
@section('page-title', 'CA Export — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a> &nbsp;/&nbsp; CA Export
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">CA Portal & Export</h1>
            <p class="text-sm text-gray-400 mt-0.5">One-click financial reports for your Chartered Accountant</p>
        </div>
    </div>

    {{-- CA Contact --}}
    <div class="bg-white border border-[#e8d5f0] p-5 flex justify-between items-center">
        <div class="flex items-center gap-4">
            <div class="w-10 h-10 rounded-full bg-[#f3e8f4] flex items-center justify-center text-lg font-bold text-[#6a0f70]">R</div>
            <div>
                <p class="font-semibold text-gray-800">CA Ramesh Joshi</p>
                <p class="text-xs text-gray-400">ramesh@joshica.com &nbsp;·&nbsp; +91 98001 34567</p>
            </div>
        </div>
        <div class="flex gap-2">
            <button class="text-sm border border-[#6a0f70] text-[#6a0f70] px-4 py-2 hover:bg-[#6a0f70] hover:text-white transition-colors">📧 Email CA</button>
            <button class="text-sm border border-green-600 text-green-700 px-4 py-2 hover:bg-green-600 hover:text-white transition-colors">💬 WhatsApp CA</button>
        </div>
    </div>

    {{-- Period Selector --}}
    <div class="bg-white border border-[#e8d5f0] p-5">
        <p class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70] mb-3">Select Export Period</p>
        <div class="flex gap-2 flex-wrap items-center">
            @foreach(['This Month','Last Month','This Quarter','Last Quarter','This FY','Custom Range'] as $p)
            <button class="text-xs px-4 py-2 border transition-colors {{ $p==='This Month' ? 'border-[#6a0f70] bg-[#6a0f70] text-white' : 'border-[#e8d5f0] text-gray-600 hover:border-[#6a0f70] hover:text-[#6a0f70]' }}">
                {{ $p }}
            </button>
            @endforeach
            <input type="date" class="text-sm border border-gray-300 px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            <span class="text-gray-400 text-sm">to</span>
            <input type="date" class="text-sm border border-gray-300 px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
        </div>
    </div>

    {{-- Report Cards --}}
    <div class="grid grid-cols-3 gap-3">
        @foreach([
            ['title'=>'Profit & Loss',      'desc'=>'Revenue, expenses, net profit summary',  'icon'=>'📊'],
            ['title'=>'Income Report',       'desc'=>'All patient payments by category/mode',  'icon'=>'💚'],
            ['title'=>'Expense Report',      'desc'=>'Category-wise expense breakdown',         'icon'=>'📋'],
            ['title'=>'Vendor Ledger',       'desc'=>'Purchase & payment history per vendor',   'icon'=>'🏢'],
            ['title'=>'Purchase Register',   'desc'=>'All inventory purchases with GST',        'icon'=>'📦'],
            ['title'=>'Cash Book',           'desc'=>'Daily cash opening/closing register',     'icon'=>'💵'],
            ['title'=>'Bank Book',           'desc'=>'All bank account transactions',           'icon'=>'🏦'],
            ['title'=>'Outstanding Report',  'desc'=>'Pending patient payments & dues',         'icon'=>'⏳'],
            ['title'=>'Payroll Register',    'desc'=>'Staff salary disbursement register',      'icon'=>'👥'],
            ['title'=>'Balance Sheet Data',  'desc'=>'Assets, liabilities, equity summary',     'icon'=>'⚖️'],
            ['title'=>'GST Summary',         'desc'=>'Input/output GST compilation',            'icon'=>'🧾'],
            ['title'=>'Tally Export',        'desc'=>'Tally-compatible XML/CSV format',         'icon'=>'📤'],
        ] as $report)
        <div class="bg-white border border-[#e8d5f0] p-4 flex justify-between items-center hover:border-[#6a0f70] transition-colors">
            <div class="flex items-center gap-3">
                <span class="text-xl">{{ $report['icon'] }}</span>
                <div>
                    <p class="text-sm font-medium text-gray-800">{{ $report['title'] }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">{{ $report['desc'] }}</p>
                </div>
            </div>
            <div class="flex gap-1.5 flex-shrink-0 ml-3">
                <button class="text-xs border border-[#e8d5f0] text-gray-500 px-2.5 py-1 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">PDF</button>
                <button class="text-xs border border-[#e8d5f0] text-gray-500 px-2.5 py-1 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">XLS</button>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Send All --}}
    <div class="bg-white border border-[#6a0f70] p-6 text-center">
        <p class="font-semibold text-[#380740] mb-1" style="font-family:'Cormorant Garamond',serif; font-size:18px;">Send Complete Package to CA</p>
        <p class="text-sm text-gray-400 mb-5">All 12 reports bundled in one email + WhatsApp message</p>
        <button class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-6 py-3 hover:bg-[#380740] transition-colors font-medium">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
            Send All Reports to CA
        </button>
    </div>

</div>
@endsection
