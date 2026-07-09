@extends('layouts.app')
@section('page-title', 'Vouchers — Finance')

@section('content')
<div class="p-6 space-y-5">

    {{-- PAGE HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
                &nbsp;/&nbsp; Vouchers
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">
                Payment Vouchers
            </h1>
        </div>
        {{-- Export button --}}
        <a href="{{ route('finance.vouchers.export', request()->only(['from','to','vendor_id','payment_mode'])) }}"
           class="inline-flex items-center gap-2 border border-[#6a0f70] text-[#6a0f70] text-sm px-4 py-2 hover:bg-[#6a0f70] hover:text-white transition-colors">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
            </svg>
            Export Excel
        </a>
    </div>

    @session('success')
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-3">{{ $value }}</div>
    @endsession

    {{-- SUMMARY STRIP --}}
    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Vouchers Issued</p>
            <p class="text-2xl font-bold text-gray-700 mt-1">{{ $summary->cnt ?? 0 }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Total Paid Out</p>
            <p class="text-2xl font-bold text-[#6a0f70] mt-1">Rs. {{ number_format($summary->total ?? 0, 0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Period</p>
            <p class="text-sm font-medium text-gray-600 mt-1">
                {{ \Carbon\Carbon::parse($from)->format('d M Y') }} — {{ \Carbon\Carbon::parse($to)->format('d M Y') }}
            </p>
        </div>
    </div>

    {{-- FILTER BAR --}}
    <form method="GET" action="{{ route('finance.vouchers.index') }}" class="bg-white border border-[#e8d5f0] p-4">
        <div class="flex flex-wrap gap-3 items-end">
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">From</label>
                <input type="date" name="from" value="{{ $from }}"
                       class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">To</label>
                <input type="date" name="to" value="{{ $to }}"
                       class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Vendor</label>
                <select name="vendor_id" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                    <option value="">All Vendors</option>
                    @foreach($vendors as $v)
                        <option value="{{ $v->id }}" @selected($vendorId == $v->id)>{{ $v->vendor_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Mode</label>
                <select name="payment_mode" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                    <option value="">All Modes</option>
                    @foreach($modes as $m)
                        <option value="{{ $m }}" @selected($mode === $m)>{{ ucfirst(str_replace('_',' ',$m)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Voucher no / vendor / ref…"
                       class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70] w-52">
            </div>
            <label class="flex items-center gap-2 text-xs text-gray-500 pb-1.5">
                <input type="checkbox" name="show_voided" value="1" {{ $showVoided ? 'checked' : '' }}
                       onchange="this.form.submit()">
                Show voided
            </label>
            <button type="submit"
                    class="bg-[#6a0f70] text-white text-sm px-4 py-1.5 hover:bg-[#380740] transition-colors">
                Filter
            </button>
            <a href="{{ route('finance.vouchers.index') }}" class="text-sm text-gray-500 hover:text-[#6a0f70] py-1.5">Reset</a>
        </div>
    </form>

    {{-- TABLE --}}
    <div class="bg-white border border-[#e8d5f0] overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-[#f9f0fb] text-[#6a0f70] text-xs uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left">Voucher No</th>
                    <th class="px-4 py-3 text-left">Date</th>
                    <th class="px-4 py-3 text-left">Vendor</th>
                    <th class="px-4 py-3 text-left">Purpose</th>
                    <th class="px-4 py-3 text-right">Amount</th>
                    <th class="px-4 py-3 text-left">Mode</th>
                    <th class="px-4 py-3 text-left">Reference</th>
                    <th class="px-4 py-3 text-left">Created By</th>
                    <th class="px-4 py-3 text-center">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($vouchers as $v)
                <tr class="hover:bg-[#fdf8ff] transition-colors {{ $v->isVoided() ? 'opacity-50' : '' }}">
                    <td class="px-4 py-3">
                        <a href="{{ route('finance.vouchers.show', $v) }}"
                           class="font-mono text-[#6a0f70] hover:underline font-medium {{ $v->isVoided() ? 'line-through' : '' }}">
                            {{ $v->voucher_number }}
                        </a>
                        @if($v->isVoided())
                        <span class="ml-1 text-xs px-1.5 py-0.5 bg-red-50 text-red-600 border border-red-200">VOIDED</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $v->voucher_date->format('d M Y') }}</td>
                    <td class="px-4 py-3 text-gray-700">
                        {{ $v->vendor_name ?? ($v->vendor?->vendor_name ?? '—') }}
                    </td>
                    <td class="px-4 py-3 text-gray-600 max-w-xs truncate">{{ $v->purpose ?? '—' }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-800">
                        Rs. {{ number_format($v->amount, 0) }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="bg-gray-100 text-gray-600 text-xs px-2 py-0.5">
                            {{ ucfirst(str_replace('_', ' ', $v->payment_mode ?? '')) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $v->reference ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $v->createdBy?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('finance.vouchers.show', $v) }}"
                               class="text-[#6a0f70] hover:underline text-xs">View</a>
                            <a href="{{ route('finance.vouchers.print', $v) }}"
                               target="_blank"
                               class="text-gray-500 hover:text-[#6a0f70] text-xs">Print</a>
                            @if(!$v->isVoided() && auth()->user()?->isAdmin())
                            <form method="POST" action="{{ route('finance.vouchers.destroy', $v) }}"
                                  onsubmit="return promptVoidReason(this)">
                                @csrf @method('DELETE')
                                <input type="hidden" name="void_reason">
                                <button type="submit" class="text-red-500 hover:underline text-xs">Void</button>
                            </form>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="9" class="px-4 py-12 text-center text-gray-400">
                        No vouchers found for this period.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- PAGINATION --}}
    <div>{{ $vouchers->withQueryString()->links() }}</div>

</div>

<script>
function promptVoidReason(form) {
    const reason = prompt('Why is this voucher being voided? (required — this stays on the record)');
    if (!reason || !reason.trim()) return false;
    form.querySelector('input[name="void_reason"]').value = reason.trim();
    return true;
}
</script>
@endsection
