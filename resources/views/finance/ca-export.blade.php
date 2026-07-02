@extends('layouts.app')
@section('page-title', 'CA Export — Finance')

@section('content')
<div class="p-6 max-w-2xl space-y-5">

    {{-- ── HEADER ── --}}
    <div>
        <p class="text-xs text-gray-400 uppercase tracking-widest">
            <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a> &nbsp;/&nbsp; CA Export
        </p>
        <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">CA Export</h1>
        <p class="text-sm text-gray-500 mt-1">Download a structured ledger for your CA or accountant — Income, Expenses and Payment Vouchers in separate sections.</p>
    </div>

    {{-- ── FILTER FORM ── --}}
    <form id="ca-form" method="GET" action="{{ route('finance.ca-export') }}" class="bg-white border border-[#e8d5f0] p-6 space-y-5">

        {{-- Preset buttons --}}
        <div>
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider mb-2">Quick Period</p>
            <div class="flex flex-wrap gap-2">
                @foreach(['fy' => 'This FY (Apr–Mar)', 'month' => 'This Month', 'quarter' => 'This Quarter'] as $key => $label)
                    <a href="{{ route('finance.ca-export') }}?preset={{ $key }}"
                       class="text-xs px-3 py-1.5 border transition-colors
                              {{ ($preset ?? 'fy') === $key
                                 ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                 : 'bg-white text-gray-600 border-gray-300 hover:border-[#6a0f70] hover:text-[#6a0f70]' }}">
                        {{ $label }}
                    </a>
                @endforeach
                <span class="text-xs px-3 py-1.5 border border-gray-200 text-gray-400">Custom ↓</span>
            </div>
        </div>

        {{-- Custom date range --}}
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">From</label>
                <input type="date" name="from"
                       value="{{ ($from instanceof \Carbon\Carbon) ? $from->toDateString() : $from }}"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">To</label>
                <input type="date" name="to"
                       value="{{ ($to instanceof \Carbon\Carbon) ? $to->toDateString() : $to }}"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            </div>
        </div>

        {{-- Preview totals --}}
        <div class="grid grid-cols-3 gap-3 py-4 border-t border-b border-gray-100">
            <div class="text-center">
                <p class="text-xs text-gray-400 uppercase tracking-wider">Total Income</p>
                <p class="text-xl font-bold text-green-600 mt-1">&#8377;{{ number_format($incomeTotal, 0) }}</p>
            </div>
            <div class="text-center">
                <p class="text-xs text-gray-400 uppercase tracking-wider">Total Expenses</p>
                <p class="text-xl font-bold text-red-600 mt-1">&#8377;{{ number_format($expenseTotal, 0) }}</p>
            </div>
            <div class="text-center">
                <p class="text-xs text-gray-400 uppercase tracking-wider">GST Collected</p>
                <p class="text-xl font-bold text-amber-600 mt-1">&#8377;{{ number_format($gstCollected, 0) }}</p>
            </div>
        </div>

        {{-- Net profit line --}}
        @php $net = $incomeTotal - $expenseTotal; @endphp
        <div class="flex items-center justify-between text-sm border border-gray-100 bg-gray-50 px-4 py-2">
            <span class="text-gray-500">Net Profit (Income minus Expenses)</span>
            <span class="font-bold {{ $net >= 0 ? 'text-green-600' : 'text-red-600' }}">
                &#8377;{{ number_format($net, 0) }}
            </span>
        </div>

        {{-- Action buttons --}}
        <div class="flex flex-wrap items-center gap-3">
            <button type="submit"
                    class="bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
                Refresh Preview
            </button>

            <button type="button" onclick="caDownload('excel')"
                    class="inline-flex items-center gap-2 bg-emerald-600 text-white text-sm px-4 py-2 hover:bg-emerald-700 transition-colors">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Download Excel (.xlsx)
            </button>

            <button type="button" onclick="caDownload('csv')"
                    class="inline-flex items-center gap-2 bg-blue-600 text-white text-sm px-4 py-2 hover:bg-blue-700 transition-colors">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="7 10 12 15 17 10"/>
                    <line x1="12" y1="15" x2="12" y2="3"/>
                </svg>
                Download CSV
            </button>
        </div>
    </form>

    {{-- ── WHAT IS INCLUDED ── --}}
    <div class="bg-gray-50 border border-gray-200 p-4 text-xs text-gray-500 space-y-1">
        <p class="font-semibold text-gray-600 mb-2">What is included in the export:</p>
        <p>Income: All invoice payments received in the period (date, invoice, patient, treatment, amount, mode)</p>
        <p>Expenses: All recorded expenses (date, title, category, vendor, amount, GST, mode, status)</p>
        <p>Payment Vouchers: All vouchers generated for vendor payments in the period</p>
        <p class="pt-1">Excel download creates 3 separate sheets. CSV creates sections in a single file.</p>
    </div>

</div>

<script>
function caDownload(format) {
    var form = document.getElementById('ca-form');
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
