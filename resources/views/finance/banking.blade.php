@extends('layouts.app')
@section('page-title', 'Banking — Finance')

@section('content')
<div class="p-6 space-y-5">

    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a> &nbsp;/&nbsp; Banking
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">Banking Module</h1>
        </div>
        <button class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
            + Add Bank Account
        </button>
    </div>

    <div class="grid grid-cols-3 gap-4">
        @foreach([
            ['name'=>'HDFC Current Account','bank'=>'HDFC Bank',  'acc'=>'****4521','type'=>'Current','balance'=>285000,'primary'=>true],
            ['name'=>'SBI Savings Account', 'bank'=>'State Bank', 'acc'=>'****8832','type'=>'Savings','balance'=>48500, 'primary'=>false],
            ['name'=>'ICICI OD Account',    'bank'=>'ICICI Bank', 'acc'=>'****1190','type'=>'OD',     'balance'=>-25000,'primary'=>false],
        ] as $acc)
        <div class="bg-white border {{ $acc['primary'] ? 'border-[#6a0f70]' : 'border-[#e8d5f0]' }} p-5 relative">
            @if($acc['primary'])
            <span class="absolute top-3 right-3 text-xs bg-[#f3e8f4] text-[#6a0f70] px-2 py-0.5">Primary</span>
            @endif
            <p class="text-xs text-gray-400 uppercase tracking-widest mb-1">{{ $acc['bank'] }}</p>
            <p class="font-semibold text-gray-800">{{ $acc['name'] }}</p>
            <p class="text-xs text-gray-400 mb-4">{{ $acc['acc'] }} &nbsp;·&nbsp; {{ $acc['type'] }}</p>
            <p class="text-3xl font-semibold {{ $acc['balance']>=0 ? 'text-[#380740]' : 'text-red-500' }}" style="font-family:'Cormorant Garamond',serif;">
                {{ $acc['balance']>=0 ? '₹'.number_format($acc['balance']) : '−₹'.number_format(abs($acc['balance'])) }}
            </p>
            <div class="flex gap-2 mt-4">
                <button class="flex-1 text-xs border border-[#e8d5f0] text-gray-500 py-1.5 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">Statement</button>
                <button class="flex-1 text-xs border border-[#e8d5f0] text-gray-500 py-1.5 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">Reconcile</button>
            </div>
        </div>
        @endforeach
    </div>

    <div class="bg-white border border-[#e8d5f0]">
        <div class="flex justify-between items-center px-5 py-4 border-b border-[#e8d5f0]">
            <h2 class="text-xs font-semibold uppercase tracking-widest text-[#6a0f70]">Recent Transactions — HDFC Current</h2>
            <select class="text-sm border border-gray-300 bg-white text-gray-600 px-3 py-1.5 focus:outline-none focus:border-[#6a0f70]">
                <option>HDFC Current Account</option>
                <option>SBI Savings Account</option>
            </select>
        </div>
        <table class="w-full">
            <thead>
                <tr class="border-b border-[#e8d5f0] bg-[#faf5fc]">
                    @foreach(['Date','Description','Type','Reference','Debit','Credit','Balance','Reconciled'] as $h)
                    <th class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-widest text-gray-400">{{ $h }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach([
                    ['date'=>'29 May','desc'=>'UPI Credit — Patient Priya',     'type'=>'UPI', 'ref'=>'UPI24052901','debit'=>0,    'credit'=>45000,'balance'=>285000,'rec'=>true],
                    ['date'=>'29 May','desc'=>'NEFT — Lab Payment City Dental',  'type'=>'NEFT','ref'=>'NEFT240529', 'debit'=>8500, 'credit'=>0,    'balance'=>240000,'rec'=>true],
                    ['date'=>'28 May','desc'=>'UPI Credit — Patient Suresh',     'type'=>'UPI', 'ref'=>'UPI24052803','debit'=>0,    'credit'=>18000,'balance'=>248500,'rec'=>true],
                    ['date'=>'28 May','desc'=>'Staff Salary — Dr. Ananya',       'type'=>'NEFT','ref'=>'NEFT240528A','debit'=>65000,'credit'=>0,    'balance'=>230500,'rec'=>true],
                    ['date'=>'27 May','desc'=>'Osstem Implants Payment',         'type'=>'RTGS','ref'=>'RTGS240527', 'debit'=>75000,'credit'=>0,    'balance'=>295500,'rec'=>false],
                ] as $row)
                <tr class="border-b border-[#e8d5f0] hover:bg-[#faf5fc] transition-colors">
                    <td class="px-4 py-3 text-xs text-gray-400">{{ $row['date'] }}</td>
                    <td class="px-4 py-3 text-sm text-gray-700">{{ $row['desc'] }}</td>
                    <td class="px-4 py-3"><span class="text-xs bg-[#f3e8f4] text-[#6a0f70] px-2 py-0.5">{{ $row['type'] }}</span></td>
                    <td class="px-4 py-3 text-xs text-gray-400 font-mono">{{ $row['ref'] }}</td>
                    <td class="px-4 py-3 text-sm {{ $row['debit']>0 ? 'text-red-500 font-medium' : 'text-gray-300' }}">
                        {{ $row['debit']>0 ? '−₹'.number_format($row['debit']) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-sm {{ $row['credit']>0 ? 'text-green-600 font-medium' : 'text-gray-300' }}">
                        {{ $row['credit']>0 ? '+₹'.number_format($row['credit']) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold text-gray-700">₹{{ number_format($row['balance']) }}</td>
                    <td class="px-4 py-3 text-xs {{ $row['rec'] ? 'text-green-600' : 'text-amber-600' }}">
                        {{ $row['rec'] ? '✓ Reconciled' : '⏳ Pending' }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
