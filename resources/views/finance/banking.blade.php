@extends('layouts.app')
@section('page-title', 'Banking — Settings')

@section('content')
<div class="p-6 space-y-5" x-data="{ showAdd: false, editing: null }">

    {{-- ── HEADER ── --}}
    <div class="flex items-center justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest">
                <a href="{{ route('settings.index') }}" class="hover:text-[#6a0f70]">Settings</a> &nbsp;/&nbsp; Banking
            </p>
            <h1 class="text-2xl font-semibold text-[#6a0f70] mt-0.5" style="font-family:'Cormorant Garamond',serif;">Bank Accounts</h1>
            <p class="text-xs text-gray-400 mt-1">Clinic bank accounts used for expense payments and reconciliation.</p>
        </div>
        <button @click="showAdd = !showAdd" type="button"
                class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Add Account
        </button>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">{{ $errors->first() }}</div>
    @endif

    {{-- ── ADD ACCOUNT FORM ── --}}
    <div x-show="showAdd" x-cloak class="bg-white border border-[#e8d5f0] p-5">
        <h3 class="text-sm font-semibold text-[#6a0f70] uppercase tracking-widest mb-4">New Bank Account</h3>
        <form method="POST" action="{{ route('finance.banking.store') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Account Name *</label>
                <input type="text" name="account_name" required
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]"
                       placeholder="e.g. Clinic Current Account">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Bank Name *</label>
                <input type="text" name="bank_name" required
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]"
                       placeholder="e.g. HDFC Bank">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Account Type *</label>
                <select name="account_type" required class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    <option value="current">Current</option>
                    <option value="savings">Savings</option>
                    <option value="od">Overdraft (OD)</option>
                    <option value="cc">Cash Credit (CC)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Account Number *</label>
                <input type="text" name="account_number" required
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">IFSC Code</label>
                <input type="text" name="ifsc_code" class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Branch</label>
                <input type="text" name="branch" class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">UPI ID</label>
                <input type="text" name="upi_id" class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]" placeholder="clinic@upi">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Opening Balance (Rs.)</label>
                <input type="number" name="opening_balance" step="0.01" min="0"
                       class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]" placeholder="0">
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm text-gray-600">
                    <input type="checkbox" name="is_primary" value="1">
                    Set as primary account
                </label>
            </div>
            <div class="md:col-span-3">
                <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Notes</label>
                <textarea name="notes" rows="2" class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]" style="resize:vertical;"></textarea>
            </div>
            <div class="md:col-span-3 flex gap-2">
                <button type="submit" class="bg-[#6a0f70] text-white text-sm px-5 py-2 hover:bg-[#380740] transition-colors">Save Account</button>
                <button type="button" @click="showAdd = false" class="text-sm border border-gray-300 text-gray-600 px-4 py-2 hover:border-[#6a0f70]">Cancel</button>
            </div>
        </form>
    </div>

    {{-- ── ACCOUNTS ── --}}
    @if($accounts->isEmpty())
    <div class="bg-white border border-[#e8d5f0] py-12 text-center">
        <p class="text-gray-400 text-sm">No bank accounts configured yet.</p>
        <p class="text-xs text-gray-300 mt-1">Click "Add Account" above to add your first clinic bank account.</p>
    </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($accounts as $acc)
        <div class="bg-white border border-[#e8d5f0] p-5 space-y-2 {{ !$acc->is_active ? 'opacity-50' : '' }}">
            <div class="flex items-center justify-between">
                <p class="font-semibold text-gray-800">{{ $acc->account_name }}</p>
                <div class="flex items-center gap-2">
                    @if($acc->is_primary)
                    <span class="text-xs px-2 py-0.5 bg-[#f0e4f7] text-[#6a0f70] rounded-full">Primary</span>
                    @endif
                    @if(!$acc->is_active)
                    <span class="text-xs px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full">Inactive</span>
                    @endif
                </div>
            </div>
            <p class="text-sm text-gray-500">{{ $acc->bank_name }} · {{ ucfirst($acc->account_type) }}</p>
            <p class="text-xs font-mono text-gray-400">{{ $acc->account_number }}</p>
            @if($acc->ifsc_code)
            <p class="text-xs text-gray-400">IFSC: {{ $acc->ifsc_code }}</p>
            @endif
            <div class="pt-2 border-t border-gray-100">
                <p class="text-xs text-gray-400 uppercase tracking-wider">Current Balance</p>
                <p class="text-xl font-bold text-[#6a0f70]">Rs. {{ number_format($acc->current_balance, 0) }}</p>
            </div>
            @if($acc->upi_id)
            <p class="text-xs text-gray-400">UPI: {{ $acc->upi_id }}</p>
            @endif

            <div class="pt-2 border-t border-gray-100 flex gap-2">
                <button @click="editing = editing === {{ $acc->id }} ? null : {{ $acc->id }}" type="button"
                        class="text-xs text-[#6a0f70] hover:underline">Edit</button>
                <form method="POST" action="{{ route('finance.banking.toggle', $acc) }}" onsubmit="return confirm('{{ $acc->is_active ? 'Deactivate' : 'Reactivate' }} this account?')">
                    @csrf
                    <button type="submit" class="text-xs text-gray-500 hover:underline">
                        {{ $acc->is_active ? 'Deactivate' : 'Reactivate' }}
                    </button>
                </form>
            </div>

            {{-- Inline edit form --}}
            <div x-show="editing === {{ $acc->id }}" x-cloak class="pt-3 border-t border-gray-100">
                <form method="POST" action="{{ route('finance.banking.update', $acc) }}" class="grid grid-cols-2 gap-3">
                    @csrf @method('PUT')
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Account Name *</label>
                        <input type="text" name="account_name" value="{{ $acc->account_name }}" required
                               class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Bank Name *</label>
                        <input type="text" name="bank_name" value="{{ $acc->bank_name }}" required
                               class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Account Type *</label>
                        <select name="account_type" required class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                            @foreach(['current'=>'Current','savings'=>'Savings','od'=>'Overdraft (OD)','cc'=>'Cash Credit (CC)'] as $val=>$label)
                            <option value="{{ $val }}" {{ $acc->account_type === $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Account Number *</label>
                        <input type="text" name="account_number" value="{{ $acc->account_number }}" required
                               class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">IFSC Code</label>
                        <input type="text" name="ifsc_code" value="{{ $acc->ifsc_code }}"
                               class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Branch</label>
                        <input type="text" name="branch" value="{{ $acc->branch }}"
                               class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">UPI ID</label>
                        <input type="text" name="upi_id" value="{{ $acc->upi_id }}"
                               class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div class="flex items-end">
                        <label class="flex items-center gap-2 text-sm text-gray-600">
                            <input type="checkbox" name="is_primary" value="1" {{ $acc->is_primary ? 'checked' : '' }}>
                            Set as primary account
                        </label>
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-gray-600 uppercase tracking-wider mb-1">Notes</label>
                        <textarea name="notes" rows="2" class="w-full border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#6a0f70]" style="resize:vertical;">{{ $acc->notes }}</textarea>
                    </div>
                    <div class="col-span-2 flex gap-2">
                        <button type="submit" class="bg-[#6a0f70] text-white text-sm px-5 py-2 hover:bg-[#380740] transition-colors">Update Account</button>
                        <button type="button" @click="editing = null" class="text-sm border border-gray-300 text-gray-600 px-4 py-2 hover:border-[#6a0f70]">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    <p class="text-xs text-gray-400">
        Bank account balances are manually maintained. Automatic reconciliation from UPI/bank feeds is a future enhancement.
    </p>
</div>
@endsection
