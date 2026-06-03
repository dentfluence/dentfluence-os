@extends('layouts.app')
@section('page-title', 'Cashbook — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div>
        <p class="text-xs text-gray-400 uppercase tracking-widest">
            <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a> &nbsp;/&nbsp; Cashbook
        </p>
        <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">Daily Cashbook</h1>
    </div>

    <div class="grid grid-cols-4 gap-3">
        @foreach([
            ['label'=>'Opening Balance','val'=>'₹38,200','color'=>'text-blue-600'],
            ['label'=>'Cash In',        'val'=>'₹12,500','color'=>'text-green-600'],
            ['label'=>'Cash Out',       'val'=>'₹8,700', 'color'=>'text-red-500'],
            ['label'=>'Closing Balance','val'=>'₹42,000','color'=>'text-[#380740]'],
        ] as $c)
        <div class="bg-white border border-[#e8d5f0] p-5">
            <p class="text-xs text-gray-400 uppercase tracking-widest mb-2">{{ $c['label'] }}</p>
            <p class="text-3xl font-semibold {{ $c['color'] }}" style="font-family:'Cormorant Garamond',serif;">{{ $c['val'] }}</p>
        </div>
        @endforeach
    </div>

    {{-- Reconciliation --}}
    <div class="bg-white border border-[#e8d5f0] p-6">
        <h2 class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70] mb-5">End of Day Reconciliation — 29 May 2026</h2>
        <div class="grid grid-cols-3 gap-6 items-end">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-2">System Closing Balance</p>
                <p class="text-3xl font-semibold text-[#380740]" style="font-family:'Cormorant Garamond',serif;">₹42,000</p>
            </div>
            <div>
                <label class="block text-xs text-gray-400 uppercase tracking-widest mb-2">Physical Count (₹)</label>
                <input type="number" value="42000" class="w-full border border-gray-300 text-lg font-semibold text-gray-800 px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-2">Difference</p>
                <p class="text-2xl font-semibold text-green-600">₹0 &nbsp;✓ Matched</p>
                <button class="mt-3 text-sm bg-[#6a0f70] text-white px-5 py-2 hover:bg-[#380740] transition-colors">Mark Reconciled</button>
            </div>
        </div>
    </div>

    {{-- History --}}
    <div class="bg-white border border-[#e8d5f0]">
        <div class="px-5 py-4 border-b border-[#e8d5f0]">
            <h2 class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70]">Cashbook History</h2>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#e8d5f0] bg-[#faf5fc]">
                    @foreach(['Date','Opening','Cash In','Cash Out','Closing','Physical','Difference','Status'] as $h)
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach([
                    ['date'=>'29 May 2026','open'=>38200,'in'=>12500,'out'=>8700, 'close'=>42000,'physical'=>42000,'diff'=>0,   'status'=>'reconciled'],
                    ['date'=>'28 May 2026','open'=>34000,'in'=>18000,'out'=>13800,'close'=>38200,'physical'=>38200,'diff'=>0,   'status'=>'reconciled'],
                    ['date'=>'27 May 2026','open'=>28500,'in'=>14000,'out'=>8500, 'close'=>34000,'physical'=>33850,'diff'=>-150,'status'=>'mismatch'],
                    ['date'=>'26 May 2026','open'=>22000,'in'=>16500,'out'=>10000,'close'=>28500,'physical'=>28500,'diff'=>0,   'status'=>'reconciled'],
                    ['date'=>'25 May 2026','open'=>18000,'in'=>12000,'out'=>8000, 'close'=>22000,'physical'=>22000,'diff'=>0,   'status'=>'reconciled'],
                ] as $row)
                <tr class="border-b border-[#e8d5f0] hover:bg-[#faf5fc] transition-colors">
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['date'] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-500">₹{{ number_format($row['open']) }}</td>
                    <td class="px-4 py-3 text-sm text-green-600">+₹{{ number_format($row['in']) }}</td>
                    <td class="px-4 py-3 text-sm text-red-500">−₹{{ number_format($row['out']) }}</td>
                    <td class="px-4 py-3 text-sm font-semibold text-[#380740]">₹{{ number_format($row['close']) }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">₹{{ number_format($row['physical']) }}</td>
                    <td class="px-4 py-3 text-sm font-medium {{ $row['diff']==0 ? 'text-green-600' : 'text-red-500' }}">
                        {{ $row['diff']==0 ? '₹0' : '₹'.number_format($row['diff']) }}
                    </td>
                    <td class="px-4 py-3">
                        <span class="text-xs px-2 py-0.5 {{ $row['status']==='reconciled' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-600 border border-red-200' }}">
                            {{ ucfirst($row['status']) }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
