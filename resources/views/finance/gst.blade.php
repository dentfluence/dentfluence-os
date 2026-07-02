@extends('layouts.app')
@section('page-title', 'GST — Finance')

@section('content')
<div class="p-6 space-y-5">

    {{-- ── HEADER ── --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a> &nbsp;/&nbsp; GST
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">GST Summary</h1>
        </div>
        <a href="{{ route('finance.ca-export') }}" class="text-sm text-[#6a0f70] hover:underline">CA Export →</a>
    </div>

    {{-- ── DATE FILTER ── --}}
    <form method="GET" action="{{ route('finance.gst') }}" class="bg-white border border-[#e8d5f0] p-4">
        <div class="flex gap-3 items-end">
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">From</label>
                <input type="date" name="from" value="{{ $from }}" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="text-xs text-gray-500 uppercase tracking-wider block mb-1">To</label>
                <input type="date" name="to" value="{{ $to }}" class="border border-gray-300 text-sm px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <button type="submit" class="bg-[#6a0f70] text-white text-sm px-4 py-1.5 hover:bg-[#380740] transition-colors">Apply</button>
        </div>
    </form>

    {{-- ── BY RATE BREAKDOWN ── --}}
    @if($byRate->isNotEmpty())
    <div class="grid grid-cols-{{ min($byRate->count() + 1, 4) }} gap-3">
        @foreach($byRate as $row)
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">GST @ {{ $row->gst_pct }}%</p>
            <p class="text-xl font-bold text-[#6a0f70] mt-1">Rs. {{ number_format($row->total_gst, 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $row->cnt }} items · Taxable: Rs. {{ number_format($row->taxable_value, 0) }}</p>
        </div>
        @endforeach
        <div class="bg-[#f9f4fb] border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Total GST</p>
            <p class="text-xl font-bold text-gray-800 mt-1">Rs. {{ number_format($totalGst, 2) }}</p>
            <p class="text-xs text-gray-400 mt-1">CGST + SGST applicable</p>
        </div>
    </div>
    @else
    <div class="bg-white border border-[#e8d5f0] py-8 text-center text-gray-400 text-sm">
        No GST-bearing invoice items found for the selected period.
    </div>
    @endif

    {{-- ── ITEMS TABLE ── --}}
    @if($gstItems->isNotEmpty())
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        <div class="px-4 py-3 border-b border-[#f0e4f7]">
            <p class="text-sm font-medium text-gray-700">GST Line Items</p>
        </div>
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb] border-b border-[#e8d5f0]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Invoice</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Patient</th>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Description</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Taxable</th>
                    <th class="text-center px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Rate</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">GST</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($gstItems as $item)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3">
                        <a href="{{ route('billing.show', $item->invoice_id) }}"
                           class="text-xs font-mono text-blue-600 hover:underline">
                            {{ $item->invoice?->invoice_number }}
                        </a>
                        <div class="text-xs text-gray-400">{{ $item->invoice?->invoice_date?->format('d M Y') }}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-700">{{ $item->invoice?->patient?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $item->description }}</td>
                    <td class="px-4 py-3 text-right text-gray-700">Rs. {{ number_format($item->net_amount - $item->gst_amount, 2) }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-xs px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full">{{ $item->gst_pct }}%</span>
                    </td>
                    <td class="px-4 py-3 text-right text-amber-700 font-medium">Rs. {{ number_format($item->gst_amount, 2) }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900">Rs. {{ number_format($item->net_amount, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @if($gstItems->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">{{ $gstItems->links() }}</div>
        @endif
    </div>
    @endif

</div>
@endsection
