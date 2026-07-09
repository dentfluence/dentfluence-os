@extends('layouts.app')
@section('page-title', 'Voucher ' . $voucher->voucher_number)

@section('content')
<div class="p-6 space-y-5 max-w-2xl mx-auto">

    {{-- BREADCRUMB --}}
    <p class="text-xs text-gray-400 uppercase tracking-widest">
        <a href="{{ route('finance.dashboard') }}" class="hover:text-[#6a0f70]">Finance</a>
        &nbsp;/&nbsp;
        <a href="{{ route('finance.expenses', ['tab' => 'vouchers']) }}" class="hover:text-[#6a0f70]">Voucher Register</a>
        &nbsp;/&nbsp; {{ $voucher->voucher_number }}
    </p>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2">{{ session('success') }}</div>
    @endif

    {{-- VOIDED BANNER --}}
    @if($voucher->isVoided())
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
        <p class="font-semibold">This voucher has been voided.</p>
        <p class="mt-0.5">{{ $voucher->void_reason }}</p>
        <p class="text-xs text-red-500 mt-1">
            Voided by {{ $voucher->voidedBy?->name ?? '—' }} on {{ $voucher->voided_at?->format('d M Y, h:i A') }}
        </p>
    </div>
    @endif

    {{-- VOUCHER CARD --}}
    <div class="bg-white border border-[#e8d5f0] shadow-sm {{ $voucher->isVoided() ? 'opacity-60' : '' }}">

        {{-- Header band --}}
        <div class="bg-[#6a0f70] text-white px-6 py-4 flex items-center justify-between">
            <div>
                <p class="text-xs opacity-70 uppercase tracking-widest">Payment Voucher</p>
                <p class="text-xl font-bold font-mono mt-0.5">{{ $voucher->voucher_number }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs opacity-70">Date</p>
                <p class="text-sm font-medium mt-0.5">{{ $voucher->voucher_date->format('d M Y') }}</p>
            </div>
        </div>

        {{-- Amount band --}}
        <div class="px-6 py-5 border-b border-[#e8d5f0] bg-[#fdf8ff]">
            <p class="text-xs text-gray-400 uppercase tracking-widest">Amount Paid</p>
            <p class="text-3xl font-bold text-[#6a0f70] mt-1">Rs. {{ number_format($voucher->amount, 2) }}</p>
        </div>

        {{-- Details grid (2-column) --}}
        <div class="px-6 py-5 grid grid-cols-2 gap-x-8 gap-y-4 text-sm">

            {{-- Vendor --}}
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-0.5">Vendor / Payee</p>
                <p class="font-medium text-gray-800">
                    {{ $voucher->vendor_name ?? ($voucher->vendor?->vendor_name ?? '—') }}
                </p>
                @if($voucher->vendor?->vendor_type)
                    <p class="text-xs text-gray-500">{{ $voucher->vendor->vendor_type }}</p>
                @endif
            </div>

            {{-- Payment Mode --}}
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-0.5">Payment Mode</p>
                <p class="font-medium text-gray-800">{{ $voucher->getPaymentModeLabel() }}</p>
            </div>

            {{-- Clinic Account --}}
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-0.5">Clinic Account Used</p>
                <p class="font-medium text-gray-800">{{ $voucher->clinic_account_name ?? '—' }}</p>
            </div>

            {{-- UTR / Cheque --}}
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-0.5">
                    {{ $voucher->payment_mode === 'cheque' ? 'Cheque Number' : 'UTR / Transaction Ref' }}
                </p>
                <p class="font-medium text-gray-800 font-mono">
                    @if($voucher->payment_mode === 'cheque')
                        {{ $voucher->cheque_number ?? $voucher->reference ?? '—' }}
                    @else
                        {{ $voucher->reference ?? '—' }}
                    @endif
                </p>
            </div>

            {{-- Purpose --}}
            <div class="col-span-2">
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-0.5">Purpose / Bill Title</p>
                <p class="font-medium text-gray-800">{{ $voucher->purpose ?? '—' }}</p>
            </div>

            {{-- Created By --}}
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-0.5">Created By</p>
                <p class="font-medium text-gray-800">{{ $voucher->createdBy?->name ?? '—' }}</p>
                <p class="text-xs text-gray-500">{{ $voucher->created_at->format('d M Y, h:i A') }}</p>
            </div>

            {{-- Approved By --}}
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-0.5">Approved By</p>
                <p class="font-medium text-gray-800">{{ $voucher->approvedBy?->name ?? '—' }}</p>
                @if($voucher->approved_at)
                    <p class="text-xs text-gray-500">{{ $voucher->approved_at->format('d M Y') }}</p>
                @endif
            </div>

            {{-- Notes --}}
            @if($voucher->notes)
            <div class="col-span-2">
                <p class="text-xs text-gray-400 uppercase tracking-widest mb-0.5">Notes / Remarks</p>
                <p class="text-gray-700">{{ $voucher->notes }}</p>
            </div>
            @endif

        </div>

        {{-- Linked Expense / Bill --}}
        @if($voucher->expense)
        <div class="px-6 py-4 border-t border-[#e8d5f0] bg-gray-50">
            <p class="text-xs text-gray-400 uppercase tracking-widest mb-2">Linked Bill / Expense</p>
            <div class="flex items-center justify-between flex-wrap gap-2">
                <div>
                    <p class="text-sm font-medium text-gray-800">{{ $voucher->expense->title }}</p>
                    <p class="text-xs text-gray-500">
                        {{ $voucher->expense->category?->name ?? 'Uncategorised' }}
                        &bull; {{ $voucher->expense->expense_date->format('d M Y') }}
                        @if($voucher->expense->source_type)
                            &bull; <span class="text-blue-500">{{ class_basename($voucher->expense->source_type) }}</span>
                        @endif
                    </p>
                </div>
                <a href="{{ route('finance.expenses.edit', $voucher->expense) }}"
                   class="text-xs text-[#6a0f70] hover:underline">View Expense →</a>
            </div>
        </div>
        @endif

        {{-- Footer actions --}}
        <div class="px-6 py-4 border-t border-[#e8d5f0] flex flex-wrap gap-3">
            <a href="{{ route('finance.vouchers.print', $voucher) }}" target="_blank"
               class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <polyline points="6 9 6 2 18 2 18 9"/>
                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Print Voucher
            </a>
            <a href="{{ route('finance.vouchers.print', $voucher) }}?pdf=1" target="_blank"
               class="inline-flex items-center gap-2 border border-red-400 text-red-600 text-sm px-4 py-2 hover:bg-red-50 transition-colors">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                    <polyline points="14 2 14 8 20 8"/>
                </svg>
                Download PDF
            </a>
            <a href="{{ route('finance.expenses', ['tab' => 'vouchers']) }}"
               class="inline-flex items-center gap-2 border border-gray-300 text-gray-600 text-sm px-4 py-2 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">
                ← Voucher Register
            </a>
            @if(!$voucher->isVoided() && auth()->user()?->isAdmin())
            <form method="POST" action="{{ route('finance.vouchers.destroy', $voucher) }}"
                  onsubmit="return promptVoidReason(this)" class="ml-auto">
                @csrf @method('DELETE')
                <input type="hidden" name="void_reason">
                <button type="submit"
                        class="inline-flex items-center gap-2 border border-red-300 text-red-600 text-sm px-4 py-2 hover:bg-red-50 transition-colors">
                    Void Voucher
                </button>
            </form>
            @endif
        </div>
    </div>

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
