@extends('layouts.app')
@section('page-title', 'Banking — Settings')

@section('content')
<div class="p-6 space-y-5">

    {{-- ── HEADER ── --}}
    <div>
        <p class="text-xs text-gray-400 uppercase tracking-widest">
            <a href="{{ route('settings.index') }}" class="hover:text-[#6a0f70]">Settings</a> &nbsp;/&nbsp; Banking
        </p>
        <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">Bank Accounts</h1>
        <p class="text-xs text-gray-400 mt-1">Clinic bank accounts used for expense payments and reconciliation.</p>
    </div>

    {{-- ── ACCOUNTS ── --}}
    @if($accounts->isEmpty())
    <div class="bg-white border border-[#e8d5f0] py-12 text-center">
        <p class="text-gray-400 text-sm">No bank accounts configured.</p>
        <p class="text-xs text-gray-300 mt-1">Run the bank account seeder or add accounts via Settings.</p>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($accounts as $acc)
        <div class="bg-white border border-[#e8d5f0] p-5 space-y-2">
            <div class="flex items-center justify-between">
                <p class="font-semibold text-gray-800">{{ $acc->account_name }}</p>
                @if($acc->is_primary)
                <span class="text-xs px-2 py-0.5 bg-[#f0e4f7] text-[#6a0f70] rounded-full">Primary</span>
                @endif
            </div>
            <p class="text-sm text-gray-500">{{ $acc->bank_name }} · {{ $acc->account_type }}</p>
            <p class="text-xs font-mono text-gray-400">{{ $acc->account_number }}</p>
            @if($acc->ifsc_code)
            <p class="text-xs text-gray-400">IFSC: {{ $acc->ifsc_code }}</p>
            @endif
            <div class="pt-2 border-t border-gray-100">
                <p class="text-xs text-gray-400 uppercase tracking-wider">Current Balance</p>
                <p class="text-xl font-bold text-[#6a0f70]">Rs. {{ number_format($acc->current_balance, 0) }}</p>
            </div>
            @if($acc->upi_id)
            <p class="text-xs text-gray-400">UPI: {{ $acc->upi_id }}</p>
            @endif
        </div>
        @endforeach
    </div>
    @endif

    <p class="text-xs text-gray-400">
        Bank account balances are manually maintained. Automatic reconciliation from UPI/bank feeds is a future enhancement.
    </p>
</div>
@endsection
