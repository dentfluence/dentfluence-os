@extends('layouts.app')
@section('page-title', 'Cashbook — Finance')

@section('content')
<div class="p-6 space-y-5">

    {{-- ── HEADER ── --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a> &nbsp;/&nbsp; Cashbook
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">Cash Book</h1>
        </div>
    </div>

    {{-- ── DATE FILTER ── --}}
    <form method="GET" action="{{ route('finance.cashbook') }}" class="bg-white border border-[#e8d5f0] p-4">
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

    {{-- ── TOTALS ── --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Cash In</p>
            <p class="text-2xl font-bold text-green-600 mt-1">Rs. {{ number_format($totals['in'], 0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Cash Out</p>
            <p class="text-2xl font-bold text-red-600 mt-1">Rs. {{ number_format($totals['out'], 0) }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Net</p>
            <p class="text-2xl font-bold {{ $totals['net'] >= 0 ? 'text-[#6a0f70]' : 'text-red-600' }} mt-1">
                Rs. {{ number_format(abs($totals['net']), 0) }}
                <span class="text-sm font-normal">{{ $totals['net'] >= 0 ? '↑' : '↓' }}</span>
            </p>
        </div>
    </div>

    {{-- ── DAILY TABLE ── --}}
    <div class="bg-white border border-[#e8d5f0] overflow-hidden">
        @if($rows->isEmpty())
        <div class="py-12 text-center text-gray-400 text-sm">No cash transactions in selected period.</div>
        @else
        <table class="w-full text-sm">
            <thead class="bg-[#f9f4fb] border-b border-[#e8d5f0]">
                <tr>
                    <th class="text-left px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Date</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Cash In (Rs. )</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Cash Out (Rs. )</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Net (Rs. )</th>
                    <th class="text-right px-4 py-2.5 text-xs text-gray-500 uppercase tracking-wider">Running Balance</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @foreach($rows->filter(fn($r) => $r['cash_in'] > 0 || $r['cash_out'] > 0) as $row)
                <tr class="hover:bg-[#fdf8ff]">
                    <td class="px-4 py-3 text-gray-600">{{ \Carbon\Carbon::parse($row['date'])->format('D, d M Y') }}</td>
                    <td class="px-4 py-3 text-right text-green-600 font-medium">
                        {{ $row['cash_in'] > 0 ? 'Rs. ' . number_format($row['cash_in'], 2) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right text-red-600 font-medium">
                        {{ $row['cash_out'] > 0 ? 'Rs. ' . number_format($row['cash_out'], 2) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right {{ $row['net'] >= 0 ? 'text-green-700' : 'text-red-700' }} font-medium">
                        {{ $row['net'] >= 0 ? '+' : '' }}Rs. {{ number_format($row['net'], 2) }}
                    </td>
                    <td class="px-4 py-3 text-right text-gray-800 font-semibold">
                        Rs. {{ number_format($row['balance'], 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

</div>
@endsection
