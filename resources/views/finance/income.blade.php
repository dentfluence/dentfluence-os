@extends('layouts.app')
@section('page-title', 'Income — Finance')

@php
    // Helper: build a URL that swaps one query param while keeping the rest
    $tabUrl = fn(string $tab) => route('finance.income', array_merge(
        request()->except(['tab','page','inv_page','rcp_page','bill_page']),
        ['tab' => $tab]
    ));
    $filterBase = array_merge(request()->except(['page','inv_page','rcp_page','bill_page']), ['tab' => $activeTab]);
@endphp

@section('content')
<div class="p-6 space-y-5">

    {{-- ── PAGE HEADER ── --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
                &nbsp;/&nbsp; Income
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                Income Ledger
            </h1>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('finance.income.export', array_merge(request()->query(), ['format'=>'excel'])) }}"
               class="inline-flex items-center gap-1.5 border border-green-600 text-green-700 text-xs px-3 py-1.5 hover:bg-green-50 transition-colors">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                Excel
            </a>
            <a href="{{ route('finance.income.export', array_merge(request()->query(), ['format'=>'pdf'])) }}"
               target="_blank"
               class="inline-flex items-center gap-1.5 border border-red-500 text-red-600 text-xs px-3 py-1.5 hover:bg-red-50 transition-colors">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                PDF
            </a>
            <a href="{{ route('billing.create') }}"
               class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Invoice
            </a>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('success'))
    <div class="px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded text-sm">{{ session('success') }}</div>
    @endif

    {{-- ── KPI STRIP ── --}}
    <div class="grid grid-cols-4 gap-3">
        @foreach([
            ['label'=>'Today',       'val'=>$kpis['today'],       'color'=>'text-green-600'],
            ['label'=>'This Week',   'val'=>$kpis['this_week'],   'color'=>'text-blue-600'],
            ['label'=>'This Month',  'val'=>$kpis['this_month'],  'color'=>'text-[#6a0f70]'],
            ['label'=>'Outstanding', 'val'=>$kpis['outstanding'], 'color'=>'text-amber-600'],
        ] as $c)
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">{{ $c['label'] }}</p>
            <p class="text-2xl font-bold {{ $c['color'] }} mt-1">Rs. {{ number_format($c['val'], 0) }}</p>
        </div>
        @endforeach
    </div>

    {{-- ── TAB NAV ── --}}
    <div class="border-b border-[#e8d5f0]">
        <nav class="flex gap-0 -mb-px">
            @foreach([
                ['tab'=>'invoices', 'label'=>'Invoices',  'icon'=>'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                ['tab'=>'receipts', 'label'=>'Receipts',  'icon'=>'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z'],
                ['tab'=>'bills',    'label'=>'Final Bills','icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4'],
                ['tab'=>'trash',    'label'=>'Trash',     'icon'=>'M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16'],
            ] as $t)
            @php $active = $activeTab === $t['tab']; @endphp
            <a href="{{ $tabUrl($t['tab']) }}"
               class="inline-flex items-center gap-1.5 px-5 py-3 text-sm font-medium border-b-2 transition-colors
                   {{ $active
                       ? 'border-[#6a0f70] text-[#6a0f70]'
                       : 'border-transparent text-gray-500 hover:text-[#6a0f70] hover:border-[#c084d4]' }}
                   {{ $t['tab'] === 'trash' ? 'ml-auto' : '' }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $t['icon'] }}"/>
                </svg>
                {{ $t['label'] }}
            </a>
            @endforeach
        </nav>
    </div>

    {{-- ── DATE PRESETS + FILTER BAR (hidden on Trash tab) ── --}}
    @if($activeTab !== 'trash')

    <div class="flex flex-wrap gap-2 items-center">
        <span class="text-xs text-gray-400 uppercase tracking-wider mr-1">Quick:</span>
        @foreach(['today'=>'Today','yesterday'=>'Yesterday','week'=>'This Week','month'=>'This Month','quarter'=>'Quarter','fy'=>'Financial Year'] as $key=>$label)
        <a href="{{ route('finance.income', array_merge(request()->except(['from','to','preset','page']), ['tab'=>$activeTab,'preset'=>$key])) }}"
           class="text-xs px-3 py-1 border transition-colors
               {{ $preset === $key ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'border-gray-300 text-gray-600 hover:border-[#6a0f70] hover:text-[#6a0f70]' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('finance.income') }}" class="bg-white border border-[#e8d5f0] p-4">
        <input type="hidden" name="tab" value="{{ $activeTab }}">
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">From</label>
                <input type="date" name="from" value="{{ $from->toDateString() }}"
                       class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">To</label>
                <input type="date" name="to" value="{{ $to->toDateString() }}"
                       class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            @if($activeTab !== 'bills')
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Status</label>
                <select name="status" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                    <option value="">All</option>
                    <option value="paid"    {{ $statusFilter==='paid'    ? 'selected':'' }}>Paid</option>
                    <option value="partial" {{ $statusFilter==='partial' ? 'selected':'' }}>Partial</option>
                    <option value="unpaid"  {{ $statusFilter==='unpaid'  ? 'selected':'' }}>Unpaid</option>
                </select>
            </div>
            @endif
            @if($activeTab === 'receipts')
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Mode</label>
                <select name="mode" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                    <option value="">All Modes</option>
                    @foreach($modes as $m)
                        <option value="{{ $m }}" {{ $mode===$m ? 'selected':'' }}>{{ ucfirst(str_replace('_',' ',$m)) }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Sort By</label>
                <select name="sort" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                    <option value="newest"       {{ $sortBy==='newest'       ? 'selected':'' }}>Newest First</option>
                    <option value="oldest"       {{ $sortBy==='oldest'       ? 'selected':'' }}>Oldest First</option>
                    <option value="amount_desc"  {{ $sortBy==='amount_desc'  ? 'selected':'' }}>Amount: High → Low</option>
                    <option value="amount_asc"   {{ $sortBy==='amount_asc'   ? 'selected':'' }}>Amount: Low → High</option>
                    <option value="patient_asc"  {{ $sortBy==='patient_asc'  ? 'selected':'' }}>Patient: A → Z</option>
                    <option value="patient_desc" {{ $sortBy==='patient_desc' ? 'selected':'' }}>Patient: Z → A</option>
                </select>
            </div>
            <div class="flex-1 min-w-[180px]">
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Patient name, phone{{ $activeTab==='invoices' ? ', invoice no' : ($activeTab==='bills' ? ', bill no' : '') }}…"
                       class="w-full border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div class="flex gap-2">
                <button type="submit" class="bg-[#6a0f70] text-white text-sm px-4 py-1.5 hover:bg-[#380740] transition-colors">Filter</button>
                <a href="{{ route('finance.income', ['tab'=>$activeTab]) }}" class="text-sm text-gray-500 hover:text-[#6a0f70] py-1.5">Reset</a>
            </div>
        </div>
    </form>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: INVOICES                                                       --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'invoices')
    <div class="bg-white border border-[#e8d5f0]" style="overflow:visible">
        <div class="flex items-center justify-between px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">
                {{ $invoices->total() }} invoice{{ $invoices->total() !== 1 ? 's' : '' }}
                &nbsp;—&nbsp;
                <span class="text-[#6a0f70] font-semibold">Rs. {{ number_format($invoices->getCollection()->sum('total_amount'), 0) }}</span> on this page
            </p>
            <a href="{{ route('finance.ca-export') }}" class="text-xs text-[#6a0f70] hover:underline">CA Export →</a>
        </div>
        @if($invoices->isEmpty())
        <div class="py-12 text-center text-gray-400 text-sm">No invoices found for the selected filters.</div>
        @else
        <table class="w-full text-sm" style="overflow:visible">
            <thead class="bg-[#f9f4fb] border-b border-[#e8d5f0]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Invoice #</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Patient</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Paid</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Balance</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2.5 w-10"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($invoices as $inv)
                @php
                    $stColors = ['paid'=>'bg-green-100 text-green-700','partial'=>'bg-yellow-100 text-yellow-700','draft'=>'bg-gray-100 text-gray-500','cancelled'=>'bg-red-100 text-red-600','refunded'=>'bg-purple-100 text-purple-700'];
                    $stLabels = ['paid'=>'Paid','partial'=>'Partial','draft'=>'Unpaid','cancelled'=>'Cancelled','refunded'=>'Refunded'];
                @endphp
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $inv->invoice_date?->format('d M Y') }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('billing.show', $inv->id) }}?return_to={{ urlencode(request()->fullUrl()) }}"
                           class="text-blue-600 hover:underline font-mono text-xs">{{ $inv->invoice_number }}</a>
                    </td>
                    <td class="px-4 py-3">
                        @if($inv->patient)
                        <a href="{{ route('patients.show', $inv->patient_id) }}" class="text-[#6a0f70] hover:underline font-medium">{{ $inv->patient->name }}</a>
                        <div class="text-xs text-gray-400">{{ $inv->patient->phone }}</div>
                        @else<span class="text-gray-400">—</span>@endif
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">Rs. {{ number_format($inv->total_amount, 0) }}</td>
                    <td class="px-4 py-3 text-right text-green-700">Rs. {{ number_format($inv->paid_amount, 0) }}</td>
                    <td class="px-4 py-3 text-right {{ $inv->balance_due > 0 ? 'text-amber-600 font-medium' : 'text-gray-400' }}">
                        {{ $inv->balance_due > 0 ? 'Rs. '.number_format($inv->balance_due,0) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right">
                        <span class="inline-block px-2 py-0.5 text-xs rounded-full {{ $stColors[$inv->status] ?? 'bg-gray-100 text-gray-500' }}">
                            {{ $stLabels[$inv->status] ?? ucfirst($inv->status) }}
                        </span>
                    </td>
                    {{-- ── 3-dot action menu (Finance module only) ── --}}
                    <td class="px-2 py-3 text-center"
                        x-data="{ open: false }"
                        @click.outside="open = false">
                        <button @click.stop="
                                    open = !open;
                                    if (open) {
                                        var r = $el.getBoundingClientRect();
                                        $refs.menu.style.top   = (r.bottom + 4) + 'px';
                                        $refs.menu.style.right = (window.innerWidth - r.right) + 'px';
                                    }
                                "
                                class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors mx-auto"
                                title="Actions">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                        </button>
                        <div x-ref="menu"
                             x-show="open"
                             style="position:fixed; z-index:9999; min-width:160px; display:none;"
                             class="bg-white border border-gray-200 shadow-lg rounded py-1 text-sm text-left">
                            {{-- View --}}
                            <a href="{{ route('billing.show', $inv->id) }}?return_to={{ urlencode(request()->fullUrl()) }}"
                               class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-[#fdf8ff] hover:text-[#6a0f70]">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                View
                            </a>
                            {{-- Print --}}
                            <a href="{{ route('billing.print', $inv->id) }}" target="_blank"
                               class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-[#fdf8ff] hover:text-[#6a0f70]">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                Print
                            </a>
                            @if(auth()->user()->isAdminRole())
                            {{-- Edit (admin only) --}}
                            <a href="{{ route('billing.edit', $inv->id) }}"
                               class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-[#fdf8ff] hover:text-[#6a0f70]">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                Edit
                            </a>
                            {{-- Divider --}}
                            <div class="border-t border-gray-100 my-1"></div>
                            {{-- Cancel/Delete (admin only) — opens reason+refund modal --}}
                            <button type="button"
                                    @click="open=false; $dispatch('open-cancel-invoice-modal', {
                                        invoiceId: '{{ $inv->id }}',
                                        invoiceNumber: '{{ $inv->invoice_number }}',
                                        status: '{{ $inv->status }}',
                                        paidAmount: '{{ $inv->paid_amount }}'
                                    })"
                                    class="w-full flex items-center gap-2 px-4 py-2 text-red-600 hover:bg-red-50">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                {{ in_array($inv->status, ['paid','partial']) ? 'Cancel Invoice' : 'Delete' }}
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($invoices->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $invoices->links() }}</div>
        @endif
        @endif
    </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: RECEIPTS                                                       --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'receipts')

    {{-- Mode breakdown --}}
    @if($byMode->isNotEmpty())
    <div class="bg-white border border-[#e8d5f0] p-4">
        <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Payment Mode Breakdown — Selected Period</p>
        <div class="flex flex-wrap gap-4">
            @foreach(['cash','card','upi','cheque','netbanking','emi','other'] as $m)
                @if(isset($byMode[$m]))
                <div class="flex items-center gap-2">
                    <span class="inline-block w-3 h-3 rounded-full" style="background:{{ match($m){'cash'=>'#16a34a','upi'=>'#7c3aed','card'=>'#2563eb','cheque'=>'#d97706','netbanking'=>'#0891b2','emi'=>'#db2777',default=>'#6b7280'} }}"></span>
                    <span class="text-sm text-gray-700 capitalize">{{ $m }}</span>
                    <span class="text-sm font-semibold text-gray-900">Rs. {{ number_format($byMode[$m]->total, 0) }}</span>
                    <span class="text-xs text-gray-400">({{ $byMode[$m]->cnt }})</span>
                </div>
                @endif
            @endforeach
        </div>
    </div>
    @endif

    <div class="bg-white border border-[#e8d5f0]" style="overflow:visible">
        <div class="flex items-center justify-between px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">
                {{ $receipts->total() }} receipt{{ $receipts->total() !== 1 ? 's' : '' }}
                &nbsp;—&nbsp;
                <span class="text-[#6a0f70] font-semibold">Rs. {{ number_format($receipts->getCollection()->sum('amount'), 0) }}</span> on this page
            </p>
            <a href="{{ route('finance.ca-export') }}" class="text-xs text-[#6a0f70] hover:underline">CA Export →</a>
        </div>
        @if($receipts->isEmpty())
        <div class="py-12 text-center text-gray-400 text-sm">No receipts found for the selected filters.</div>
        @else
        <table class="w-full text-sm" style="overflow:visible">
            <thead class="bg-[#f9f4fb] border-b border-[#e8d5f0]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Patient</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Invoice</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Mode</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Reference</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2.5 w-10"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($receipts as $payment)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $payment->payment_date?->format('d M Y') }}</td>
                    <td class="px-4 py-3">
                        @if($payment->invoice?->patient)
                            <a href="{{ route('patients.show', $payment->invoice->patient_id) }}" class="text-[#6a0f70] hover:underline font-medium">{{ $payment->invoice->patient->name }}</a>
                            <div class="text-xs text-gray-400">{{ $payment->invoice->patient->phone }}</div>
                        @else<span class="text-gray-400">—</span>@endif
                    </td>
                    <td class="px-4 py-3">
                        @if($payment->invoice)
                            <a href="{{ route('billing.show', $payment->invoice_id) }}?return_to={{ urlencode(request()->fullUrl()) }}"
                               class="text-blue-600 hover:underline font-mono text-xs">{{ $payment->invoice->invoice_number }}</a>
                            <div class="text-xs text-gray-400">Total: Rs. {{ number_format($payment->invoice->total_amount, 0) }}</div>
                        @else<span class="text-gray-400">—</span>@endif
                    </td>
                    <td class="px-4 py-3">
                        @php $modeColors=['cash'=>'green','upi'=>'purple','card'=>'blue','cheque'=>'yellow','netbanking'=>'cyan','emi'=>'pink','other'=>'gray']; $c=$modeColors[$payment->payment_mode]??'gray'; @endphp
                        <span class="inline-block px-2 py-0.5 text-xs font-medium rounded-full bg-{{ $c }}-100 text-{{ $c }}-700 capitalize">{{ str_replace('_',' ',$payment->payment_mode) }}</span>
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-500 font-mono">{{ $payment->reference_no ?? '—' }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">Rs. {{ number_format($payment->amount, 2) }}</td>
                    <td class="px-4 py-3 text-right">
                        @if($payment->invoice)
                        @php $st=$payment->invoice->status; @endphp
                        <span class="inline-block px-2 py-0.5 text-xs rounded-full {{ $st==='paid'?'bg-green-100 text-green-700':($st==='partial'?'bg-yellow-100 text-yellow-700':'bg-gray-100 text-gray-600') }}">{{ ucfirst($st) }}</span>
                        @endif
                    </td>
                    {{-- ── 3-dot action menu (Finance module only) ── --}}
                    <td class="px-2 py-3 text-center"
                        x-data="{ open: false }"
                        @click.outside="open = false">
                        <button @click.stop="
                                    open = !open;
                                    if (open) {
                                        var r = $el.getBoundingClientRect();
                                        $refs.menu.style.top   = (r.bottom + 4) + 'px';
                                        $refs.menu.style.right = (window.innerWidth - r.right) + 'px';
                                    }
                                "
                                class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors mx-auto"
                                title="Actions">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                        </button>
                        <div x-ref="menu"
                             x-show="open"
                             style="position:fixed; z-index:9999; min-width:160px; display:none;"
                             class="bg-white border border-gray-200 shadow-lg rounded py-1 text-sm text-left">
                            {{-- View Invoice --}}
                            @if($payment->invoice)
                            <a href="{{ route('billing.show', $payment->invoice_id) }}?return_to={{ urlencode(request()->fullUrl()) }}"
                               class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-[#fdf8ff] hover:text-[#6a0f70]">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                View Invoice
                            </a>
                            {{-- Print Receipt --}}
                            <a href="{{ route('billing.print', $payment->invoice_id) }}" target="_blank"
                               class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-[#fdf8ff] hover:text-[#6a0f70]">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                Print
                            </a>
                            @if(auth()->user()->isAdminRole())
                            {{-- Divider --}}
                            <div class="border-t border-gray-100 my-1"></div>
                            {{-- Void Receipt (admin only) — opens reason+refund modal --}}
                            <button type="button"
                                    @click="open=false; $dispatch('open-void-receipt-modal', {
                                        invoiceId: '{{ $payment->invoice_id }}',
                                        receiptId: '{{ $payment->receipt_id ?? $payment->id }}',
                                        receiptNumber: '{{ $payment->receipt_number ?? ('RCP-'.$payment->id) }}',
                                        amount: '{{ $payment->amount }}',
                                        mode: '{{ $payment->payment_mode }}'
                                    })"
                                    class="w-full flex items-center gap-2 px-4 py-2 text-red-600 hover:bg-red-50">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                Void Receipt
                            </button>
                            @endif
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($receipts->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $receipts->links() }}</div>
        @endif
        @endif
    </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: FINAL BILLS                                                    --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'bills')
    <div class="bg-white border border-[#e8d5f0]" style="overflow:visible">
        <div class="flex items-center justify-between px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">
                {{ $bills->total() }} final bill{{ $bills->total() !== 1 ? 's' : '' }}
                &nbsp;—&nbsp;
                <span class="text-[#6a0f70] font-semibold">Rs. {{ number_format($bills->getCollection()->sum('total_amount'), 0) }}</span> on this page
            </p>
        </div>
        @if($bills->isEmpty())
        <div class="py-12 text-center text-gray-400 text-sm">No final bills found for the selected filters.</div>
        @else
        <table class="w-full text-sm" style="overflow:visible">
            <thead class="bg-[#f9f4fb] border-b border-[#e8d5f0]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Generated</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Bill #</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Patient</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Invoice</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Subtotal</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Discount</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Paid</th>
                    <th class="px-4 py-2.5 w-10"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($bills as $bill)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ $bill->generated_date?->format('d M Y') }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-blue-700">{{ $bill->bill_number }}</td>
                    <td class="px-4 py-3">
                        @if($bill->patient)
                        <a href="{{ route('patients.show', $bill->patient_id) }}" class="text-[#6a0f70] hover:underline font-medium">{{ $bill->patient->name }}</a>
                        <div class="text-xs text-gray-400">{{ $bill->patient->phone }}</div>
                        @else<span class="text-gray-400">—</span>@endif
                    </td>
                    <td class="px-4 py-3">
                        @if($bill->invoice_id)
                        <a href="{{ route('billing.show', $bill->invoice_id) }}?return_to={{ urlencode(request()->fullUrl()) }}"
                           class="text-blue-600 hover:underline font-mono text-xs">View Invoice →</a>
                        @else<span class="text-gray-400">—</span>@endif
                    </td>
                    <td class="px-4 py-3 text-right text-gray-700">Rs. {{ number_format($bill->subtotal, 0) }}</td>
                    <td class="px-4 py-3 text-right text-red-600">
                        {{ ($bill->discount_amount + $bill->wallet_applied + $bill->coupon_discount) > 0
                            ? '−Rs. '.number_format($bill->discount_amount + $bill->wallet_applied + $bill->coupon_discount, 0)
                            : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">Rs. {{ number_format($bill->total_amount, 0) }}</td>
                    <td class="px-4 py-3 text-right text-green-700 font-medium">Rs. {{ number_format($bill->total_paid, 0) }}</td>
                    {{-- ── 3-dot action menu (Finance module only) ── --}}
                    <td class="px-2 py-3 text-center"
                        x-data="{ open: false }"
                        @click.outside="open = false">
                        <button @click.stop="
                                    open = !open;
                                    if (open) {
                                        var r = $el.getBoundingClientRect();
                                        $refs.menu.style.top   = (r.bottom + 4) + 'px';
                                        $refs.menu.style.right = (window.innerWidth - r.right) + 'px';
                                    }
                                "
                                class="w-7 h-7 flex items-center justify-center rounded hover:bg-gray-100 text-gray-400 hover:text-gray-700 transition-colors mx-auto"
                                title="Actions">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="5" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="12" cy="19" r="2"/></svg>
                        </button>
                        <div x-ref="menu"
                             x-show="open"
                             style="position:fixed; z-index:9999; min-width:160px; display:none;"
                             class="bg-white border border-gray-200 shadow-lg rounded py-1 text-sm text-left">
                            {{-- View Final Bill --}}
                            @if($bill->invoice_id)
                            <a href="{{ route('billing.finalBill', $bill->invoice_id) }}"
                               class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-[#fdf8ff] hover:text-[#6a0f70]">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                View Bill
                            </a>
                            {{-- View Invoice --}}
                            <a href="{{ route('billing.show', $bill->invoice_id) }}?return_to={{ urlencode(request()->fullUrl()) }}"
                               class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-[#fdf8ff] hover:text-[#6a0f70]">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                View Invoice
                            </a>
                            {{-- Print --}}
                            <a href="{{ route('billing.print', $bill->invoice_id) }}" target="_blank"
                               class="flex items-center gap-2 px-4 py-2 text-gray-700 hover:bg-[#fdf8ff] hover:text-[#6a0f70]">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                Print
                            </a>
                            @if(auth()->user()->isAdminRole())
                            {{-- Delete Final Bill (admin only) — opens reason modal --}}
                            <div class="border-t border-gray-100 my-1"></div>
                            <button type="button"
                                    @click="open=false; $dispatch('open-delete-bill-modal', {
                                        billId: '{{ $bill->id }}',
                                        billNumber: '{{ $bill->bill_number }}'
                                    })"
                                    class="w-full flex items-center gap-2 px-4 py-2 text-red-600 hover:bg-red-50">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                Delete Bill
                            </button>
                            @endif
                            @endif
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($bills->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $bills->links() }}</div>
        @endif
        @endif
    </div>
    @endif

    {{-- ════════════════════════════════════════════════════════════════════ --}}
    {{-- TAB: TRASH                                                          --}}
    {{-- ════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'trash')

    {{-- Trash search bar --}}
    <form method="GET" action="{{ route('finance.income') }}" class="bg-white border border-[#e8d5f0] p-4">
        <input type="hidden" name="tab" value="trash">
        <div class="flex gap-3 items-end">
            <div class="flex-1">
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Patient name or phone…"
                       class="w-full border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <button type="submit" class="bg-[#6a0f70] text-white text-sm px-4 py-1.5 hover:bg-[#380740]">Search</button>
            <a href="{{ route('finance.income', ['tab'=>'trash']) }}" class="text-sm text-gray-500 hover:text-[#6a0f70] py-1.5">Reset</a>
        </div>
    </form>

    {{-- Trashed Invoices --}}
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7] flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <p class="text-sm font-semibold text-gray-700">Deleted Invoices ({{ $trashInvoices->total() }})</p>
        </div>
        @if($trashInvoices->isEmpty())
        <div class="px-4 py-6 text-sm text-gray-400">No deleted invoices.</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb] border-b border-[#e8d5f0]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Invoice #</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Patient</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Deleted At</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($trashInvoices as $inv)
                <tr class="hover:bg-red-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $inv->invoice_number }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $inv->patient?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right text-gray-700">Rs. {{ number_format($inv->total_amount, 0) }}</td>
                    <td class="px-4 py-3 text-xs text-gray-400">{{ $inv->deleted_at?->format('d M Y, h:i A') }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('finance.income.trash.invoice.restore', $inv->id) }}"
                              onsubmit="return confirm('Restore this invoice?')">
                            @csrf
                            <button type="submit" class="text-xs text-[#6a0f70] hover:underline font-medium">Restore</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($trashInvoices->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $trashInvoices->links() }}</div>
        @endif
        @endif
    </div>

    {{-- Trashed Receipts --}}
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7] flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <p class="text-sm font-semibold text-gray-700">Voided Receipts ({{ $trashReceipts->total() }})</p>
        </div>
        @if($trashReceipts->isEmpty())
        <div class="px-4 py-6 text-sm text-gray-400">No voided receipts.</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb] border-b border-[#e8d5f0]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Receipt #</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Patient</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Invoice</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Amount</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Voided At</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($trashReceipts as $rcpt)
                <tr class="hover:bg-red-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $rcpt->receipt_number }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $rcpt->invoice?->patient?->name ?? '—' }}</td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $rcpt->invoice?->invoice_number ?? '—' }}</td>
                    <td class="px-4 py-3 text-right text-gray-700">Rs. {{ number_format($rcpt->amount, 0) }}</td>
                    <td class="px-4 py-3 text-xs text-gray-400">{{ $rcpt->deleted_at?->format('d M Y, h:i A') }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('finance.income.trash.receipt.restore', $rcpt->id) }}"
                              onsubmit="return confirm('Restore this receipt? This will re-activate the payment record.')">
                            @csrf
                            <button type="submit" class="text-xs text-[#6a0f70] hover:underline font-medium">Restore</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($trashReceipts->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $trashReceipts->links() }}</div>
        @endif
        @endif
    </div>

    {{-- Trashed Final Bills --}}
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7] flex items-center gap-2">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            <p class="text-sm font-semibold text-gray-700">Deleted Final Bills ({{ $trashBills->total() }})</p>
        </div>
        @if($trashBills->isEmpty())
        <div class="px-4 py-6 text-sm text-gray-400">No deleted final bills.</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb] border-b border-[#e8d5f0]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Bill #</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Patient</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Total</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Deleted At</th>
                    <th class="px-4 py-2.5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($trashBills as $bill)
                <tr class="hover:bg-red-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $bill->bill_number }}</td>
                    <td class="px-4 py-3 text-gray-700">{{ $bill->patient?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-right text-gray-700">Rs. {{ number_format($bill->total_amount, 0) }}</td>
                    <td class="px-4 py-3 text-xs text-gray-400">{{ $bill->deleted_at?->format('d M Y, h:i A') }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('finance.income.trash.bill.restore', $bill->id) }}"
                              onsubmit="return confirm('Restore this final bill?')">
                            @csrf
                            <button type="submit" class="text-xs text-[#6a0f70] hover:underline font-medium">Restore</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($trashBills->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $trashBills->links() }}</div>
        @endif
        @endif
    </div>

    @endif {{-- /trash --}}

</div>

{{-- ═══════════════════════════════════════════════════════════════════════ --}}
{{-- MODALS — Cancel Invoice / Void Receipt / Delete Final Bill             --}}
{{-- All three are Alpine-driven, admin-only, require a typed reason.       --}}
{{-- ═══════════════════════════════════════════════════════════════════════ --}}

{{-- ── 1. CANCEL INVOICE MODAL ─────────────────────────────────────────── --}}
<div x-data="{
        show: false,
        invoiceId: '',
        invoiceNumber: '',
        status: '',
        paidAmount: '0',
        reason: '',
        refundMethod: 'wallet',
        get hasMoney() { return ['paid','partial'].includes(this.status); },
        get cardCharge() { return (parseFloat(this.paidAmount) * 0.025).toFixed(2); },
        get patientReceives() { return (parseFloat(this.paidAmount) - parseFloat(this.cardCharge)).toFixed(2); }
        /* Note: for cancelInvoice the actual charge is computed per-receipt server-side
           because the invoice may have multiple receipts with different payment modes. */
     }"
     @open-cancel-invoice-modal.window="show=true; invoiceId=$event.detail.invoiceId; invoiceNumber=$event.detail.invoiceNumber; status=$event.detail.status; paidAmount=$event.detail.paidAmount; reason=''; refundMethod='wallet';"
     x-show="show"
     x-cloak
     style="display:none"
     class="fixed inset-0 z-[1000] flex items-center justify-center p-4">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/40" @click="show=false"></div>
    {{-- Panel --}}
    <div class="relative bg-white border border-[#e8d5f0] shadow-xl w-full max-w-md p-6 space-y-4" @click.stop>
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800" x-text="hasMoney ? 'Cancel Invoice — ' + invoiceNumber : 'Delete Invoice — ' + invoiceNumber"></h3>
            <button @click="show=false" class="text-gray-400 hover:text-gray-600">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        {{-- Warning --}}
        <div class="bg-red-50 border border-red-200 rounded px-3 py-2 text-xs text-red-700">
            <span x-show="hasMoney">This invoice has collected Rs. <span x-text="parseFloat(paidAmount).toLocaleString('en-IN')"></span>. All linked receipts will be voided and the refund will be processed as selected below.</span>
            <span x-show="!hasMoney">This will permanently cancel the invoice and move it to Trash.</span>
        </div>

        <form :action="hasMoney ? '/billing/' + invoiceId + '/cancel-with-reason' : '/billing/' + invoiceId" method="POST">
            @csrf
            <template x-if="!hasMoney"><input type="hidden" name="_method" value="DELETE"></template>

            {{-- Reason --}}
            <div class="space-y-1 mb-4">
                <label class="text-xs font-medium text-gray-700 uppercase tracking-wider">Reason <span class="text-red-500">*</span></label>
                <textarea name="cancelled_reason" x-model="reason" rows="3" required minlength="5"
                          placeholder="e.g. Duplicate entry, Wrong patient, Treatment not done…"
                          class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
            </div>

            {{-- Refund method — only if money was collected --}}
            <div x-show="hasMoney" class="space-y-2 mb-4">
                <label class="text-xs font-medium text-gray-700 uppercase tracking-wider">Return Money To Patient Via</label>
                <div class="space-y-2">
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="radio" name="cancel_refund_method" value="wallet" x-model="refundMethod" class="mt-0.5">
                        <div>
                            <span class="text-sm font-medium text-gray-700">Wallet Credit</span>
                            <p class="text-xs text-gray-400">Full amount added to patient wallet. Patient uses it on next visit.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="radio" name="cancel_refund_method" value="cash" x-model="refundMethod" class="mt-0.5">
                        <div>
                            <span class="text-sm font-medium text-gray-700">Cash Refund</span>
                            <p class="text-xs text-gray-400">Full amount returned in cash. You handle it manually.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="radio" name="cancel_refund_method" value="bank_transfer" x-model="refundMethod" class="mt-0.5">
                        <div>
                            <span class="text-sm font-medium text-gray-700">Bank Transfer / UPI</span>
                            <p class="text-xs text-gray-400">Returned via bank transfer or UPI. 2.5% clinic charge applies only if original payment was by card / debit card — UPI and other modes have no charge.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="radio" name="cancel_refund_method" value="no_refund" x-model="refundMethod" class="mt-0.5">
                        <div>
                            <span class="text-sm font-medium text-gray-700">No Refund</span>
                            <p class="text-xs text-gray-400">Amount forfeited. Patient receives nothing.</p>
                        </div>
                    </label>
                </div>
                {{-- Bank transfer info note --}}
                <div x-show="refundMethod === 'bank_transfer'" class="bg-amber-50 border border-amber-200 rounded px-3 py-2 text-xs text-amber-800">
                    ℹ️ Exact charge is calculated per receipt. Card / debit card receipts: 2.5% deducted. UPI / cash / cheque receipts: no charge.
                </div>
            </div>

            <div class="flex gap-2 justify-end pt-2">
                <button type="button" @click="show=false"
                        class="text-sm text-gray-500 px-4 py-2 border border-gray-300 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="reason.length < 5"
                        class="text-sm bg-red-600 text-white px-4 py-2 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed">
                    <span x-text="hasMoney ? 'Confirm Cancel' : 'Delete Invoice'"></span>
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── 2. VOID RECEIPT MODAL ────────────────────────────────────────────── --}}
<div x-data="{
        show: false,
        invoiceId: '',
        receiptId: '',
        receiptNumber: '',
        amount: '0',
        mode: '',
        reason: '',
        refundMethod: 'wallet',
        get chargeApplies() { return ['card','debit_card'].includes(this.mode); },
        get cardCharge() { return this.chargeApplies ? (parseFloat(this.amount) * 0.025).toFixed(2) : '0.00'; },
        get patientReceives() { return (parseFloat(this.amount) - parseFloat(this.cardCharge)).toFixed(2); }
     }"
     @open-void-receipt-modal.window="show=true; invoiceId=$event.detail.invoiceId; receiptId=$event.detail.receiptId; receiptNumber=$event.detail.receiptNumber; amount=$event.detail.amount; mode=$event.detail.mode; reason=''; refundMethod='wallet';"
     x-show="show"
     x-cloak
     style="display:none"
     class="fixed inset-0 z-[1000] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="show=false"></div>
    <div class="relative bg-white border border-[#e8d5f0] shadow-xl w-full max-w-md p-6 space-y-4" @click.stop>
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800">Void Receipt — <span x-text="receiptNumber"></span></h3>
            <button @click="show=false" class="text-gray-400 hover:text-gray-600">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="bg-red-50 border border-red-200 rounded px-3 py-2 text-xs text-red-700">
            Voiding reverses Rs. <span x-text="parseFloat(amount).toLocaleString('en-IN')"></span> payment. The invoice balance will reopen. If the invoice had a Final Bill, it will be auto-deleted.
        </div>

        <form :action="'/billing/' + invoiceId + '/receipt/' + receiptId + '/void'" method="POST">
            @csrf

            <div class="space-y-1 mb-4">
                <label class="text-xs font-medium text-gray-700 uppercase tracking-wider">Reason <span class="text-red-500">*</span></label>
                <textarea name="void_reason" x-model="reason" rows="3" required minlength="5"
                          placeholder="e.g. Payment entered twice, Wrong amount, Patient request…"
                          class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
            </div>

            <div class="space-y-2 mb-4">
                <label class="text-xs font-medium text-gray-700 uppercase tracking-wider">Return Rs. <span x-text="parseFloat(amount).toLocaleString('en-IN')"></span> Via</label>
                <div class="space-y-2">
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="radio" name="void_refund_method" value="wallet" x-model="refundMethod" class="mt-0.5">
                        <div>
                            <span class="text-sm font-medium text-gray-700">Wallet Credit</span>
                            <p class="text-xs text-gray-400">Full amount added to patient wallet. No physical refund.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="radio" name="void_refund_method" value="cash" x-model="refundMethod" class="mt-0.5">
                        <div>
                            <span class="text-sm font-medium text-gray-700">Cash Refund</span>
                            <p class="text-xs text-gray-400">Full amount returned in cash.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="radio" name="void_refund_method" value="bank_transfer" x-model="refundMethod" class="mt-0.5">
                        <div>
                            <span class="text-sm font-medium text-gray-700">Bank Transfer / UPI</span>
                            <p class="text-xs text-gray-400">
                                <span x-show="chargeApplies">Original payment was by <span x-text="mode"></span> — 2.5% clinic charge applies.</span>
                                <span x-show="!chargeApplies">No charge — full amount returned via bank transfer / UPI.</span>
                            </p>
                        </div>
                    </label>
                    <label class="flex items-start gap-2 cursor-pointer">
                        <input type="radio" name="void_refund_method" value="no_refund" x-model="refundMethod" class="mt-0.5">
                        <div>
                            <span class="text-sm font-medium text-gray-700">No Refund</span>
                            <p class="text-xs text-gray-400">Amount forfeited. Patient receives nothing.</p>
                        </div>
                    </label>
                </div>
                <div x-show="refundMethod === 'bank_transfer'" class="bg-amber-50 border border-amber-200 rounded px-3 py-2 text-xs text-amber-800">
                    <span x-show="chargeApplies">Clinic deducts: Rs. <span x-text="cardCharge"></span> &nbsp;|&nbsp; Patient receives: Rs. <span x-text="patientReceives"></span></span>
                    <span x-show="!chargeApplies">No deduction — patient receives full Rs. <span x-text="parseFloat(amount).toLocaleString('en-IN')"></span></span>
                </div>
            </div>

            <div class="flex gap-2 justify-end pt-2">
                <button type="button" @click="show=false"
                        class="text-sm text-gray-500 px-4 py-2 border border-gray-300 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="reason.length < 5"
                        class="text-sm bg-red-600 text-white px-4 py-2 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed">
                    Void Receipt
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ── 3. DELETE FINAL BILL MODAL ───────────────────────────────────────── --}}
<div x-data="{
        show: false,
        billId: '',
        billNumber: '',
        reason: ''
     }"
     @open-delete-bill-modal.window="show=true; billId=$event.detail.billId; billNumber=$event.detail.billNumber; reason='';"
     x-show="show"
     x-cloak
     style="display:none"
     class="fixed inset-0 z-[1000] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/40" @click="show=false"></div>
    <div class="relative bg-white border border-[#e8d5f0] shadow-xl w-full max-w-md p-6 space-y-4" @click.stop>
        <div class="flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-800">Delete Final Bill — <span x-text="billNumber"></span></h3>
            <button @click="show=false" class="text-gray-400 hover:text-gray-600">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="bg-blue-50 border border-blue-200 rounded px-3 py-2 text-xs text-blue-700">
            ℹ️ Only the bill document is deleted. The invoice and all payments remain intact. Admin can regenerate the bill after correction.
        </div>

        <form :action="'/billing/final-bill/' + billId" method="POST">
            @csrf
            <input type="hidden" name="_method" value="DELETE">

            <div class="space-y-1 mb-4">
                <label class="text-xs font-medium text-gray-700 uppercase tracking-wider">Reason <span class="text-red-500">*</span></label>
                <textarea name="deleted_reason" x-model="reason" rows="3" required minlength="5"
                          placeholder="e.g. Wrong discount applied, Generated before treatment complete…"
                          class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
            </div>

            <div class="flex gap-2 justify-end pt-2">
                <button type="button" @click="show=false"
                        class="text-sm text-gray-500 px-4 py-2 border border-gray-300 hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="reason.length < 5"
                        class="text-sm bg-red-600 text-white px-4 py-2 hover:bg-red-700 disabled:opacity-40 disabled:cursor-not-allowed">
                    Delete Bill
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
