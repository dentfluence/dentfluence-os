{{-- resources/views/billing/show.blade.php --}}
@extends('layouts.app')

@section('page-title', 'Invoice ' . ($invoice->invoice_number ?? 'Unpaid'))

@php
    // Back-to-patient context: passed as ?from_patient={id} OR derived from invoice's patient
    $fromPatient  = request('from_patient') ? (int) request('from_patient') : ($invoice->patient_id ?? null);
    // Back-to-income context: passed as ?return_to=<url> when opened from Income Ledger
    $returnTo     = request('return_to');
    // Default back is patient billing tab (falls back to Income Ledger only if no patient)
    $backUrl      = $returnTo
        ? $returnTo
        : ($fromPatient
            ? route('patients.show', $fromPatient) . '#billing'
            : route('finance.income'));
    $backLabel    = $returnTo
        ? 'Back to Income Ledger'
        : ($fromPatient ? 'Back to Patient' : 'Back to Income Ledger');

    // Status colours + human labels
    $statusColors = [
        'paid'      => 'bg-green-100 text-green-700',
        'partial'   => 'bg-yellow-100 text-yellow-700',
        'draft'     => 'bg-gray-100 text-gray-600',
        'sent'      => 'bg-blue-100 text-blue-700',
        'cancelled' => 'bg-red-100 text-red-600',
        'refunded'  => 'bg-purple-100 text-purple-700',
    ];
    $statusLabels = [
        'paid'      => 'Paid',
        'partial'   => 'Partially Paid',
        'draft'     => 'Unpaid',
        'sent'      => 'Sent',
        'cancelled' => 'Cancelled',
        'refunded'  => 'Refunded',
    ];
    $cls         = $statusColors[$invoice->status] ?? 'bg-gray-100 text-gray-600';
    $statusLabel = $statusLabels[$invoice->status] ?? ucfirst($invoice->status);
    $canEdit     = ! in_array($invoice->status, ['paid', 'cancelled']);
    $canDelete   = $invoice->status !== 'paid';
@endphp

@section('content')
<div class="p-4 md:p-6 max-w-4xl mx-auto space-y-5">

    {{-- ── Header bar ────────────────────────────────────────────────────── --}}
    <div class="flex items-center justify-between flex-wrap gap-3">
        <div class="flex items-center gap-3">
            {{-- Smart back button — styled to match the consultation workflows --}}
            <a href="{{ $backUrl }}" title="{{ $backLabel }}"
               class="inline-flex items-center px-3.5 py-1.5 text-xs font-semibold text-gray-500 bg-white border border-gray-300 rounded-md no-underline hover:border-[#6a0f70] hover:text-[#6a0f70] transition">← Back</a>
            <div>
                <h2 class="text-xl font-semibold text-gray-800">
                    {{ $invoice->invoice_number ?? '—' }}
                </h2>
                <p class="text-xs text-gray-500">{{ $invoice->invoice_date?->format('d M Y') }}</p>
            </div>
            <span class="px-2.5 py-1 rounded-full text-xs font-semibold {{ $cls }}">
                {{ $statusLabel }}
            </span>
        </div>

        <div class="flex gap-2 flex-wrap">
            {{-- Print --}}
            <a href="{{ route('billing.print', $invoice) }}" target="_blank"
               class="px-4 py-2 bg-gray-100 text-gray-700 border border-gray-200 text-sm rounded-lg hover:bg-gray-200 flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                </svg>
                Print / PDF
            </a>

            @if($invoice->balance_due > 0 && $invoice->status !== 'cancelled')
            {{-- Record Payment --}}
            <button onclick="document.getElementById('paymentModal').classList.remove('hidden')"
                    dusk="pay-open"
                    class="px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700 flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                </svg>
                Record Payment
            </button>
            @endif

            @if($canEdit)
            {{-- Edit — opens auth gate modal --}}
            <button onclick="document.getElementById('editAuthModal').classList.remove('hidden')"
                    class="px-4 py-2 bg-yellow-50 text-yellow-700 border border-yellow-200 text-sm rounded-lg hover:bg-yellow-100">
                Edit Invoice
            </button>

            {{-- Cancel --}}
            <form method="POST" action="{{ route('billing.cancel', $invoice) }}"
                  onsubmit="return confirm('Cancel this invoice? This cannot be undone.')">
                @csrf
                <button type="submit"
                        class="px-4 py-2 bg-orange-50 text-orange-600 border border-orange-200 text-sm rounded-lg hover:bg-orange-100">
                    Cancel
                </button>
            </form>
            @endif

            @if($canDelete)
            {{-- Delete — opens auth modal with reason + password --}}
            <button onclick="document.getElementById('deleteAuthModal').classList.remove('hidden')"
                    class="px-4 py-2 bg-red-50 text-red-600 border border-red-200 text-sm rounded-lg hover:bg-red-100 flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Delete
            </button>
            @endif

            <a href="{{ $backUrl }}"
               class="px-4 py-2 bg-gray-100 text-gray-600 text-sm rounded-lg hover:bg-gray-200">
                {{ $backLabel }}
            </a>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="px-4 py-3 bg-green-50 border border-green-200 text-green-800 rounded-lg text-sm">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
        {{ session('error') }}
    </div>
    @endif
    @if(session('info'))
    <div class="px-4 py-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg text-sm">
        {{ session('info') }}
    </div>
    @endif
    @if($errors->has('password'))
    <div class="px-4 py-3 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm">
        {{ $errors->first('password') }}
    </div>
    @endif

    {{-- ── Body ─────────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">

        {{-- Invoice body --}}
        <div class="md:col-span-2 space-y-5">

            {{-- Patient + invoice meta --}}
            <div class="bg-white border border-gray-200 rounded-xl p-5">
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <p class="text-xs text-gray-400 mb-1">Patient</p>
                        <a href="{{ route('patients.show', $invoice->patient) }}#billing"
                           class="font-semibold text-gray-800 hover:text-[#6a0f70]">
                            {{ $invoice->patient->name }}
                        </a>
                        <p class="text-gray-500">{{ $invoice->patient->phone }}</p>
                        <p class="text-xs text-gray-400 mt-0.5 font-mono">{{ $invoice->patient->patient_id ?? '' }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-gray-400 mb-1">Invoice Date</p>
                        <p class="font-medium text-gray-700">{{ $invoice->invoice_date?->format('d M Y') }}</p>
                        @if($invoice->due_date)
                        <p class="text-xs text-gray-400 mt-1">Due: {{ $invoice->due_date->format('d M Y') }}</p>
                        @endif
                        <p class="text-xs text-gray-400 mt-1 font-mono">{{ $invoice->invoice_number ?? 'Unnumbered' }}</p>
                    </div>
                </div>
                @if($invoice->notes)
                <div class="mt-4 pt-4 border-t border-gray-100 text-sm text-gray-600">
                    <p class="text-xs text-gray-400 mb-1">Notes</p>
                    {{ $invoice->notes }}
                </div>
                @endif
            </div>

            {{-- Line items --}}
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Description</th>
                            <th class="text-center px-3 py-3 text-xs font-semibold text-gray-500">Tooth</th>
                            <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500">Price</th>
                            <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500">Qty</th>
                            <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500">Disc%</th>
                            <th class="text-right px-3 py-3 text-xs font-semibold text-gray-500">GST%</th>
                            <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($invoice->items as $item)
                        <tr>
                            <td class="px-4 py-3 text-gray-800">{{ $item->description }}</td>
                            <td class="px-3 py-3 text-center text-gray-500 text-xs">{{ $item->tooth_number ?: '—' }}</td>
                            <td class="px-3 py-3 text-right text-gray-600">Rs. {{ number_format($item->unit_price, 2) }}</td>
                            <td class="px-3 py-3 text-right text-gray-600">{{ $item->qty }}</td>
                            <td class="px-3 py-3 text-right text-gray-500">{{ $item->disc_pct }}%</td>
                            <td class="px-3 py-3 text-right text-gray-500">{{ $item->gst_pct }}%</td>
                            <td class="px-4 py-3 text-right font-medium text-gray-800">Rs. {{ number_format($item->total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                {{-- Totals footer --}}
                <div class="border-t border-gray-200 px-4 py-4 space-y-1.5 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>Subtotal</span>
                        <span>Rs. {{ number_format($invoice->subtotal, 2) }}</span>
                    </div>
                    @if(($invoice->discount_amount ?? 0) > 0)
                    <div class="flex justify-between text-gray-500">
                        <span>Discount ({{ $invoice->discount_pct }}%)</span>
                        <span>−Rs. {{ number_format($invoice->discount_amount, 2) }}</span>
                    </div>
                    @endif
                    @if(($invoice->membership_discount ?? 0) > 0)
                    <div class="flex justify-between text-purple-700">
                        <span>AOCP Membership Discount</span>
                        <span>−Rs. {{ number_format($invoice->membership_discount, 2) }}</span>
                    </div>
                    @endif
                    @if(($invoice->coupon_discount ?? 0) > 0)
                    <div class="flex justify-between text-blue-700">
                        <span>Coupon Discount</span>
                        <span>−Rs. {{ number_format($invoice->coupon_discount, 2) }}</span>
                    </div>
                    @endif
                    @if(($invoice->manual_discount_amount ?? 0) > 0)
                    <div class="flex justify-between text-rose-700">
                        <span>
                            Manual Discount
                            @if($invoice->manual_discount_type === 'percentage')
                                ({{ rtrim(rtrim(number_format($invoice->manual_discount_value, 2), '0'), '.') }}%)
                            @endif
                        </span>
                        <span>&minus;Rs. {{ number_format($invoice->manual_discount_amount, 2) }}</span>
                    </div>
                    @if($invoice->manual_discount_reason)
                    <div class="text-[11px] text-gray-400 -mt-1">
                        Reason: {{ $invoice->manual_discount_reason }}
                        @if($invoice->manualDiscountApplier) &middot; by {{ $invoice->manualDiscountApplier->name }} @endif
                    </div>
                    @endif
                    @endif
                    @if(($invoice->wallet_applied ?? 0) > 0)
                    <div class="flex justify-between text-[#6a0f70]">
                        <span>Wallet Credit Applied</span>
                        <span>−Rs. {{ number_format($invoice->wallet_applied, 2) }}</span>
                    </div>
                    @endif
                    @if(($invoice->gst_amount ?? 0) > 0)
                    <div class="flex justify-between text-gray-600">
                        <span>Tax (GST)</span>
                        <span>Rs. {{ number_format($invoice->gst_amount, 2) }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between font-bold text-gray-800 text-base border-t border-gray-100 pt-2">
                        <span>Total</span>
                        <span>Rs. {{ number_format($invoice->total_amount, 2) }}</span>
                    </div>
                </div>
            </div>

            {{-- Manual discount actions (permission-gated; unpaid/uncancelled only) --}}
            @if($canEdit)
            <div class="flex items-center gap-3 text-xs px-1">
                <button type="button"
                        onclick="document.getElementById('manualDiscountModal').classList.remove('hidden')"
                        class="text-rose-700 hover:underline font-medium">
                    {{ ($invoice->manual_discount_amount ?? 0) > 0 ? 'Edit manual discount' : '+ Apply manual discount' }}
                </button>
                @if(($invoice->manual_discount_amount ?? 0) > 0)
                <form method="POST" action="{{ route('billing.manualDiscount.remove', $invoice) }}"
                      onsubmit="return confirm('Remove the manual discount from this invoice?')">
                    @csrf
                    <button type="submit" class="text-gray-400 hover:text-red-600">Remove</button>
                </form>
                @endif
            </div>
            @endif

            {{-- Payment history --}}
            @if($invoice->payments->count())
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-700">Payment History</h3>
                    @if($invoice->receipts->count())
                    <span class="text-xs text-gray-400">{{ $invoice->receipts->count() }} receipt(s) generated</span>
                    @endif
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-4 py-2 text-xs text-gray-500 font-medium">Date</th>
                            <th class="text-left px-4 py-2 text-xs text-gray-500 font-medium">Mode</th>
                            <th class="text-left px-4 py-2 text-xs text-gray-500 font-medium">Ref #</th>
                            <th class="text-right px-4 py-2 text-xs text-gray-500 font-medium">Amount</th>
                            <th class="text-right px-4 py-2 text-xs text-gray-500 font-medium">Receipt</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($invoice->payments as $pmt)
                        @php
                            $pmtReceipts = $invoice->receipts->where('invoice_payment_id', $pmt->id);
                            $isProviderEmi = $pmt->emi_type === 'provider';
                            $upfrontRcpt   = $isProviderEmi ? $pmtReceipts->firstWhere('receipt_type', 'patient_upfront') : null;
                            $settlRcpt     = $isProviderEmi ? $pmtReceipts->firstWhere('receipt_type', 'provider_settlement') : null;
                            $stdRcpt       = !$isProviderEmi ? $pmtReceipts->first() : null;
                        @endphp
                        <tr>
                            <td class="px-4 py-2 text-gray-600">
                                <span class="inline-flex items-center gap-1.5">
                                    {{ $pmt->payment_date->format('d M Y') }}
                                    <button type="button"
                                            onclick="openEditPaymentDateModal({{ $pmt->id }}, '{{ $pmt->payment_date->format('Y-m-d') }}')"
                                            title="Edit payment date"
                                            class="text-gray-300 hover:text-[#6a0f70]">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                    </button>
                                </span>
                            </td>
                            <td class="px-4 py-2 text-gray-600 capitalize">
                                {{ $pmt->payment_mode }}
                                @if($isProviderEmi)
                                    <span class="ml-1 text-xs bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded font-semibold">Provider</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-400 text-xs font-mono">{{ $pmt->reference_no ?: '—' }}</td>
                            <td class="px-4 py-2 text-right font-medium text-green-600">Rs. {{ number_format($pmt->amount, 2) }}</td>
                            <td class="px-4 py-2 text-right space-y-1">
                                @if($isProviderEmi)
                                    {{-- Receipt #1: patient upfront --}}
                                    @if($upfrontRcpt)
                                    <div class="flex items-center gap-1 justify-end">
                                        <a href="{{ route('billing.receipt', [$invoice, $upfrontRcpt]) }}" target="_blank"
                                           class="text-xs text-blue-600 hover:underline font-mono">{{ $upfrontRcpt->receipt_number }}</a>
                                        <span class="text-xs text-gray-400">upfront</span>
                                        <button type="button"
                                                onclick="openVoidModal('{{ $upfrontRcpt->receipt_number }}', {{ $upfrontRcpt->id }}, {{ (float) $upfrontRcpt->amount }})"
                                                title="Void this receipt"
                                                class="text-red-400 hover:text-red-600 ml-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                    @elseif($pmt->emi_upfront_amount == 0)
                                    <span class="text-xs text-gray-400">No upfront</span>
                                    @endif

                                    {{-- Receipt #2: provider settlement --}}
                                    @if($settlRcpt)
                                    <div class="flex items-center gap-1 justify-end">
                                        <a href="{{ route('billing.receipt', [$invoice, $settlRcpt]) }}" target="_blank"
                                           class="text-xs text-green-600 hover:underline font-mono">{{ $settlRcpt->receipt_number }}</a>
                                        <span class="text-xs text-gray-400">settlement</span>
                                        <button type="button"
                                                onclick="openVoidModal('{{ $settlRcpt->receipt_number }}', {{ $settlRcpt->id }}, {{ (float) $settlRcpt->amount }})"
                                                title="Void this receipt"
                                                class="text-red-400 hover:text-red-600 ml-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                    @else
                                    <button onclick="openProviderPaidModal({{ $pmt->id }})"
                                            class="text-xs bg-amber-100 text-amber-700 hover:bg-amber-200 px-2 py-0.5 rounded font-medium whitespace-nowrap">
                                        Mark Received
                                    </button>
                                    @endif
                                @else
                                    {{-- Standard single receipt --}}
                                    @if($stdRcpt)
                                    <div class="flex items-center gap-1 justify-end">
                                        <a href="{{ route('billing.receipt', [$invoice, $stdRcpt]) }}" target="_blank"
                                           class="text-xs text-blue-600 hover:underline font-mono">{{ $stdRcpt->receipt_number }}</a>
                                        <button type="button"
                                                onclick="openVoidModal('{{ $stdRcpt->receipt_number }}', {{ $stdRcpt->id }}, {{ (float) $stdRcpt->amount }})"
                                                title="Void this receipt"
                                                class="text-red-400 hover:text-red-600 ml-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                        </button>
                                    </div>
                                    @else
                                    <span class="text-xs text-gray-300">—</span>
                                    @endif
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            {{-- Final Bill --}}
            @if($invoice->finalBill)
            <div class="bg-green-50 border border-green-200 rounded-xl p-4 flex items-center justify-between">
                <div>
                    <p class="text-sm font-semibold text-green-800">Final Bill Generated</p>
                    <p class="text-xs text-green-600 mt-0.5">
                        {{ $invoice->finalBill->bill_number }}
                        &nbsp;·&nbsp; {{ $invoice->finalBill->generated_date->format('d M Y') }}
                    </p>
                </div>
                <a href="{{ route('billing.finalBill', $invoice) }}" target="_blank"
                   class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                    View Final Bill
                </a>
            </div>
            @endif

        </div>

        {{-- Sidebar: balance summary --}}
        <div class="space-y-4">
            <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                <h3 class="text-sm font-semibold text-gray-700">Payment Summary</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between text-gray-600">
                        <span>Invoice Total</span>
                        <span class="font-medium">Rs. {{ number_format($invoice->total_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-gray-600">
                        <span>Paid</span>
                        <span class="text-green-600 font-medium">Rs. {{ number_format($invoice->paid_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between font-bold text-base border-t border-gray-100 pt-2
                                {{ $invoice->balance_due > 0 ? 'text-red-500' : 'text-green-600' }}">
                        <span>Balance Due</span>
                        <span>Rs. {{ number_format($invoice->balance_due, 2) }}</span>
                    </div>
                </div>

                @if($invoice->balance_due > 0 && $invoice->status !== 'cancelled')
                <button onclick="document.getElementById('paymentModal').classList.remove('hidden')"
                        class="w-full mt-2 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                    + Record Payment
                </button>
                @endif
            </div>

            {{-- Back to patient shortcut --}}
            @if($fromPatient)
            <a href="{{ route('patients.show', $fromPatient) }}#billing"
               class="flex items-center gap-2 px-4 py-3 bg-purple-50 border border-purple-100 rounded-xl text-sm text-[#6a0f70] hover:bg-purple-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                </svg>
                Back to {{ $invoice->patient->name }}'s Profile
            </a>
            @endif
        </div>

    </div>
</div>

{{-- ══ MODALS ══════════════════════════════════════════════════════════════════ --}}

{{-- ── 1. Payment Modal ────────────────────────────────────────────────────── --}}
@if($invoice->balance_due > 0 && $invoice->status !== 'cancelled')
<div id="paymentModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white rounded-t-2xl px-6 pt-5 pb-3 border-b border-gray-100 flex items-center justify-between z-10">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Record Payment</h3>
                <p class="text-xs text-gray-500 mt-0.5">Invoice: {{ $invoice->invoice_number }} · Balance: <span class="font-bold text-red-500">Rs. {{ number_format($invoice->balance_due, 2) }}</span></p>
            </div>
            <button onclick="document.getElementById('paymentModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <form method="POST" action="{{ route('billing.payment', $invoice) }}" id="paymentForm" class="px-6 pb-6 pt-4 space-y-4">
            @csrf
            @if($fromPatient)
            <input type="hidden" name="from_patient" value="{{ $fromPatient }}">
            @endif

            {{-- Amount + Date --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Amount (Rs. ) <span class="text-red-500">*</span></label>
                    <input type="number" name="amount" id="pmtAmount" required
                           value="{{ old('amount', $invoice->balance_due) }}"
                           min="0.01" step="0.01"
                           oninput="onAmountChange()"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Payment Date <span class="text-red-500">*</span></label>
                    <input type="date" name="payment_date" id="pmtDate" required
                           value="{{ old('payment_date', now()->format('Y-m-d')) }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>

            {{-- Use Wallet Credit (payment allocation) --}}
            @if(isset($wallet) && $wallet->balance_total > 0)
            <div class="bg-purple-50 border border-purple-100 rounded-lg p-3">
                <div class="flex items-center justify-between mb-1">
                    <label class="text-xs font-semibold text-[#6a0f70]">Use Wallet Credit</label>
                    <span class="text-xs text-gray-500">Available: Rs. {{ number_format($wallet->balance_total, 2) }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <input type="number" name="wallet_used" id="pmtWallet" min="0" step="0.01" value="0"
                           max="{{ min($wallet->balance_total, $invoice->balance_due) }}"
                           data-balance="{{ (float) $invoice->balance_due }}"
                           oninput="onWalletUsedChange()"
                           class="flex-1 border border-purple-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
                    <button type="button" onclick="useAllWallet()"
                            class="px-2 py-2 text-[10px] bg-purple-100 text-[#6a0f70] rounded hover:bg-purple-200 whitespace-nowrap">Use Max</button>
                </div>
                <p class="text-[11px] text-gray-500 mt-1">Wallet lowers the balance; the Amount above is the remaining cash to collect.</p>
            </div>
            @endif

            {{-- Payment Mode --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Payment Mode <span class="text-red-500">*</span></label>
                <select name="payment_mode" id="pmtMode" required onchange="onModeChange()"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="cash"          @selected(old('payment_mode','cash')==='cash')>Cash</option>
                    <option value="upi"           @selected(old('payment_mode')==='upi')>UPI</option>
                    <option value="card"          @selected(old('payment_mode')==='card')>Credit Card</option>
                    <option value="debit_card"    @selected(old('payment_mode')==='debit_card')>Debit Card</option>
                    <option value="netbanking"    @selected(old('payment_mode')==='netbanking')>Net Banking</option>
                    <option value="bank_transfer" @selected(old('payment_mode')==='bank_transfer')>Bank Transfer</option>
                    <option value="cheque"        @selected(old('payment_mode')==='cheque')>Cheque</option>
                    <option value="emi"           @selected(old('payment_mode')==='emi')>EMI</option>
                    <option value="other"         @selected(old('payment_mode')==='other')>Other</option>
                </select>
            </div>

            {{-- ── Clinic Account Received In (Phase 2 — Income Module) ── --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Received In (Clinic Account)
                </label>
                <select name="clinic_account_id"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">— Select Account —</option>
                    @foreach($bankAccounts ?? [] as $acct)
                        <option value="{{ $acct->id }}"
                            @selected(old('clinic_account_id') == $acct->id)>
                            {{ $acct->account_name }}
                            @if($acct->bank_name) — {{ $acct->bank_name }} @endif
                        </option>
                    @endforeach
                </select>
                <p class="text-xs text-gray-400 mt-0.5">For accounting records only — not printed on patient receipt.</p>
            </div>

            {{-- ── UPI / Netbanking / Bank Transfer: reference required ── --}}
            <div id="fieldRefNo" class="hidden">
                <label class="block text-xs font-medium text-gray-600 mb-1">Transaction Reference No. <span class="text-red-500">*</span></label>
                <input type="text" name="reference_no" value="{{ old('reference_no') }}"
                       placeholder="UTR / Transaction ID"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            {{-- ── Credit Card: convenience fee panel ─────────────────── --}}
            <div id="fieldCreditCard" class="hidden space-y-3">
                <div id="ccFeePanel" class="hidden bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-sm">
                    <div class="flex items-center justify-between">
                        <span class="text-amber-800 font-medium">Convenience Fee ({{ rtrim(rtrim(number_format((float) \App\Models\AppSetting::get('cc_convenience_rate', 2.5), 2), '0'), '.') }}%)</span>
                        <span id="ccFeeAmount" class="font-bold text-amber-700">Rs. 0.00</span>
                    </div>
                    <p class="text-xs text-amber-600 mt-1">Applied on credit card payments above Rs. {{ number_format((float) \App\Models\AppSetting::get('cc_convenience_threshold', 10000), 0) }}.</p>
                    <input type="hidden" name="convenience_fee" id="pmtConvFee" value="0">
                </div>
                <div id="ccSplitWarning" class="hidden bg-red-50 border border-red-200 rounded-lg px-4 py-3 text-xs text-red-700">
                    Split transaction detected. The 2.5% fee is calculated on the combined daily total for this patient.
                </div>
                <div id="ccTotalCharged" class="hidden flex justify-between text-sm font-semibold text-gray-700 bg-gray-50 rounded-lg px-4 py-2">
                    <span>Total charged to patient:</span>
                    <span id="ccTotalAmt">Rs. 0.00</span>
                </div>
            </div>

            {{-- ── Cheque fields ──────────────────────────────────────── --}}
            <div id="fieldCheque" class="hidden space-y-3">
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Bank Name <span class="text-red-500">*</span></label>
                        <input type="text" name="bank_name" value="{{ old('bank_name') }}"
                               placeholder="e.g. HDFC Bank"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Cheque No. <span class="text-red-500">*</span></label>
                        <input type="text" name="cheque_no" value="{{ old('cheque_no') }}"
                               placeholder="6-digit cheque number"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Cheque Date <span class="text-red-500">*</span></label>
                    <input type="date" name="cheque_date" value="{{ old('cheque_date') }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg px-4 py-3 text-xs text-yellow-800 space-y-1">
                    <p class="font-semibold">Cheque Policy</p>
                    <p>Receipt will be generated only after cheque realisation. Bounce charges apply on dishonoured cheques. Patient will be informed on realisation/bounce.</p>
                </div>
            </div>

            {{-- ── EMI fields ─────────────────────────────────────────── --}}
            @php
                $activeEmiProviders = \App\Models\EmiProvider::where('is_active', true)
                    ->with(['schemes' => fn($q) => $q->where('is_active', true)])
                    ->orderBy('name')->get();
            @endphp
            <input type="hidden" name="emi_type" id="emiType" value="direct">

            <div id="fieldEmi" class="hidden space-y-3">

                {{-- Sub-mode toggle --}}
                <div class="flex gap-2">
                    <button type="button" id="btnDirectEmi"
                            onclick="switchEmiType('direct')"
                            class="flex-1 py-2 text-xs font-semibold rounded-lg border border-purple-600 bg-purple-600 text-white">
                        Direct EMI<br>
                        <span class="font-normal opacity-80">Clinic collects instalments</span>
                    </button>
                    <button type="button" id="btnProviderEmi"
                            onclick="switchEmiType('provider')"
                            class="flex-1 py-2 text-xs font-semibold rounded-lg border border-purple-200 bg-white text-purple-700 {{ $activeEmiProviders->isEmpty() ? 'opacity-40 cursor-not-allowed' : '' }}"
                            {{ $activeEmiProviders->isEmpty() ? 'disabled title="No EMI providers configured in Settings"' : '' }}>
                        Provider EMI<br>
                        <span class="font-normal opacity-80">Provider pays clinic upfront</span>
                    </button>
                </div>

                {{-- ── Direct EMI fields ──────────────────────── --}}
                <div id="directEmiFields">
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Financer / Bank (optional)</label>
                        <input type="text" name="emi_provider" value="{{ old('emi_provider') }}"
                               placeholder="e.g. HDFC Card EMI, SBI EMI..."
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
                    </div>
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Tenure (months) <span class="text-red-500">*</span></label>
                            <select name="emi_tenure" id="emiTenure" onchange="calcEmi()"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
                                <option value="">Select…</option>
                                @foreach([3,6,9,12,18,24,36,48,60] as $m)
                                <option value="{{ $m }}" @selected(old('emi_tenure')==$m)>{{ $m }} months</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Interest Rate (% p.a.) <span class="text-red-500">*</span></label>
                            <input type="number" name="emi_interest_rate" id="emiRate"
                                   value="{{ old('emi_interest_rate', 0) }}" min="0" max="36" step="0.01"
                                   oninput="calcEmi()"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">First Auto-Debit Date <span class="text-red-500">*</span></label>
                        <input type="date" name="emi_start_date" id="emiStartDate"
                               value="{{ old('emi_start_date') }}" onchange="calcEmi()"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
                    </div>

                    {{-- Direct EMI calculation result --}}
                    <div id="emiCalcResult" class="hidden bg-purple-50 border border-purple-200 rounded-lg px-4 py-3 space-y-2 mt-3">
                        <div class="flex justify-between text-sm font-semibold text-purple-800">
                            <span>Monthly EMI</span>
                            <span id="emiMonthlyAmt">—</span>
                        </div>
                        <div class="flex justify-between text-xs text-purple-600">
                            <span>Total Payable</span>
                            <span id="emiTotalAmt">—</span>
                        </div>
                        <div class="flex justify-between text-xs text-purple-600">
                            <span>Total Interest</span>
                            <span id="emiInterestAmt">—</span>
                        </div>
                    </div>

                    {{-- Direct EMI schedule preview --}}
                    <div id="emiScheduleWrap" class="hidden mt-2">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-gray-600">Instalment Schedule</span>
                            <button type="button" onclick="toggleEmiSchedule()" id="emiToggleBtn"
                                    class="text-xs text-purple-600 hover:underline">Show</button>
                        </div>
                        <div id="emiScheduleTable" class="hidden overflow-x-auto rounded-lg border border-purple-100">
                            <table class="w-full text-xs">
                                <thead class="bg-purple-50">
                                    <tr>
                                        <th class="px-3 py-2 text-left text-purple-700">#</th>
                                        <th class="px-3 py-2 text-left text-purple-700">Due Date</th>
                                        <th class="px-3 py-2 text-right text-purple-700">Principal</th>
                                        <th class="px-3 py-2 text-right text-purple-700">Interest</th>
                                        <th class="px-3 py-2 text-right text-purple-700">EMI</th>
                                    </tr>
                                </thead>
                                <tbody id="emiScheduleBody" class="divide-y divide-purple-50 bg-white"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- ── Provider EMI fields ──────────────────────── --}}
                <div id="providerEmiFields" class="hidden space-y-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">EMI Provider <span class="text-red-500">*</span></label>
                        <select id="providerSelect" onchange="loadProviderSchemes()"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                            <option value="">— Select Provider —</option>
                            @foreach($activeEmiProviders as $ep)
                            <option value="{{ $ep->id }}">{{ $ep->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="schemeSelectWrap" class="hidden">
                        <label class="block text-xs font-medium text-gray-600 mb-1">Scheme <span class="text-red-500">*</span></label>
                        <select id="schemeSelect" name="emi_provider_scheme_id" onchange="applySchemeBreakdown()"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-400">
                            <option value="">— Select Scheme —</option>
                        </select>
                    </div>

                    {{-- Provider EMI breakdown card --}}
                    <div id="providerBreakdownCard" class="hidden bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3 space-y-2 text-sm">
                        <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wide mb-2">Scheme Breakdown</p>

                        <div class="flex justify-between text-indigo-900">
                            <span>Patient Monthly EMI</span>
                            <span id="pbMonthlyEmi" class="font-bold">—</span>
                        </div>
                        <div id="pbUpfrontRow" class="hidden flex justify-between text-amber-700">
                            <span>Upfront payment today (<span id="pbUpfrontCount">0</span> EMI)</span>
                            <span id="pbUpfrontAmt" class="font-semibold">—</span>
                        </div>
                        <div class="border-t border-indigo-200 pt-2 mt-1">
                            <div class="flex justify-between text-gray-500 text-xs">
                                <span>Clinic interest cost</span>
                                <span id="pbClinicInterest">—</span>
                            </div>
                            <div class="flex justify-between text-gray-500 text-xs">
                                <span>GST on interest (18%)</span>
                                <span id="pbGstInterest">—</span>
                            </div>
                            <div class="flex justify-between text-gray-600 text-xs font-medium mt-1">
                                <span>Provider deduction</span>
                                <span id="pbDeduction" class="text-red-500">—</span>
                            </div>
                        </div>
                        <div class="border-t border-indigo-200 pt-2">
                            <div class="flex justify-between text-green-700 font-semibold">
                                <span>Clinic net amount</span>
                                <span id="pbClinicNet">—</span>
                            </div>
                        </div>
                        <div id="pbConvenienceRow" class="hidden border-t border-amber-200 pt-2">
                            <div class="flex justify-between text-amber-700 font-semibold text-sm">
                                <span>Convenience charge (passed to patient)</span>
                                <span id="pbConvenienceAmt">—</span>
                            </div>
                            <div class="flex justify-between text-amber-900 font-bold">
                                <span>Receipt total</span>
                                <span id="pbReceiptTotal">—</span>
                            </div>
                            <input type="hidden" name="convenience_fee" id="providerConvFee" value="0">
                        </div>
                        <input type="hidden" name="emi_upfront_amount" id="providerUpfrontHidden" value="0">
                        <p class="text-xs text-indigo-500 mt-2">
                            Receipt #1 (upfront) is generated now for what the patient pays today. Receipt #2 (settlement) is generated when you click "Mark Provider Payment Received" after the provider remits the clinic net amount.
                        </p>
                    </div>
                </div>

            </div>

            {{-- Notes (always shown) --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                <textarea name="notes" rows="2"
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">{{ old('notes') }}</textarea>
            </div>

            <div class="flex gap-3 pt-1">
                <button type="submit" id="pmtSubmitBtn"
                        class="flex-1 py-2.5 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700">
                    Save Payment
                </button>
                <button type="button"
                        onclick="document.getElementById('paymentModal').classList.add('hidden')"
                        class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// ── Double-submit protection ──────────────────────────────────────────────
// Disable the Save button the moment the form submits so a double-click or a
// slow-network re-click can't record the same payment twice. The server also
// de-dupes, but this stops the duplicate at the source.
document.getElementById('paymentForm')?.addEventListener('submit', function () {
    const btn = document.getElementById('pmtSubmitBtn');
    if (btn) {
        btn.disabled = true;
        btn.classList.add('opacity-60', 'cursor-not-allowed');
        btn.textContent = 'Saving…';
    }
});

// ── Payment modal JS ──────────────────────────────────────────────────────
// Configurable in Settings → Billing → Credit Card Convenience Fee
const CC_LIMIT       = {{ (float) \App\Models\AppSetting::get('cc_convenience_threshold', 10000) }};
const CC_FEE_RATE    = {{ (float) \App\Models\AppSetting::get('cc_convenience_rate', 2.5) / 100 }};
const INVOICE_TOTAL  = {{ (float) $invoice->total_amount }};

const show = id => { const e = document.getElementById(id); if (e) e.classList.remove('hidden'); };
const hide = id => { const e = document.getElementById(id); if (e) e.classList.add('hidden'); };

function onModeChange() {
    const mode = document.getElementById('pmtMode').value;
    hide('fieldRefNo'); hide('fieldCreditCard'); hide('fieldCheque'); hide('fieldEmi');

    if (['upi','netbanking','bank_transfer'].includes(mode)) show('fieldRefNo');
    if (mode === 'card')   { show('fieldCreditCard'); onAmountChange(); }
    if (mode === 'cheque') show('fieldCheque');
    if (mode === 'emi')    show('fieldEmi');
}

// Wallet allocation: reduce the cash Amount by whatever the wallet covers.
function onWalletUsedChange() {
    const wEl = document.getElementById('pmtWallet');
    if (!wEl) return;
    const balance = parseFloat(wEl.dataset.balance) || 0;
    const maxUse  = parseFloat(wEl.max) || 0;
    let used = parseFloat(wEl.value) || 0;
    if (used < 0) used = 0;
    if (used > maxUse) { used = maxUse; wEl.value = used; }
    const amtEl = document.getElementById('pmtAmount');
    if (amtEl) { amtEl.value = Math.max(0, +(balance - used).toFixed(2)); onAmountChange(); }
}
function useAllWallet() {
    const wEl = document.getElementById('pmtWallet');
    if (!wEl) return;
    wEl.value = parseFloat(wEl.max) || 0;
    onWalletUsedChange();
}

function onAmountChange() {
    const mode = document.getElementById('pmtMode').value;
    if (mode !== 'card') return;

    const amount   = parseFloat(document.getElementById('pmtAmount').value) || 0;
    const feePanel = document.getElementById('ccFeePanel');
    const splitWarn= document.getElementById('ccSplitWarning');
    const totalRow = document.getElementById('ccTotalCharged');

    if (amount > CC_LIMIT) {
        const fee     = Math.round(amount * CC_FEE_RATE * 100) / 100;
        document.getElementById('ccFeeAmount').textContent = 'Rs. ' + fee.toFixed(2);
        document.getElementById('pmtConvFee').value = fee;
        document.getElementById('ccTotalAmt').textContent = 'Rs. ' + (amount + fee).toFixed(2);
        feePanel.classList.remove('hidden');
        totalRow.classList.remove('hidden');
        splitWarn.classList.add('hidden');
    } else {
        feePanel.classList.add('hidden');
        totalRow.classList.add('hidden');
        splitWarn.classList.add('hidden');
        document.getElementById('pmtConvFee').value = 0;
    }
}

// ── EMI sub-mode switching ────────────────────────────────────────────────
function switchEmiType(type) {
    document.getElementById('emiType').value = type;

    const btnDirect   = document.getElementById('btnDirectEmi');
    const btnProvider = document.getElementById('btnProviderEmi');
    const activeClass = ['border-purple-600','bg-purple-600','text-white'];
    const inactiveClass = ['border-purple-200','bg-white','text-purple-700'];

    if (type === 'direct') {
        activeClass.forEach(c => btnDirect.classList.add(c));
        inactiveClass.forEach(c => btnDirect.classList.remove(c));
        inactiveClass.forEach(c => btnProvider.classList.add(c));
        activeClass.forEach(c => btnProvider.classList.remove(c));
        show('directEmiFields');
        hide('providerEmiFields');
    } else {
        activeClass.forEach(c => btnProvider.classList.add(c));
        inactiveClass.forEach(c => btnProvider.classList.remove(c));
        inactiveClass.forEach(c => btnDirect.classList.add(c));
        activeClass.forEach(c => btnDirect.classList.remove(c));
        hide('directEmiFields');
        show('providerEmiFields');
    }
}

// ── Provider EMI: load schemes via AJAX ──────────────────────────────────
let _schemeData = [];

function loadProviderSchemes() {
    const providerId = document.getElementById('providerSelect').value;
    hide('schemeSelectWrap');
    hide('providerBreakdownCard');
    _schemeData = [];

    if (!providerId) return;

    const url = '{{ route("settings.emi.schemes.ajax") }}?provider_id=' + providerId
              + '&invoice_total=' + INVOICE_TOTAL;

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(r => r.json())
        .then(data => {
            _schemeData = data;
            const sel = document.getElementById('schemeSelect');
            sel.innerHTML = '<option value="">— Select Scheme —</option>';
            data.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.scheme_name + ' · ' + s.tenure_months + 'M';
                sel.appendChild(opt);
            });
            show('schemeSelectWrap');
        })
        .catch(() => alert('Could not load schemes. Please try again.'));
}

function applySchemeBreakdown() {
    const schemeId = document.getElementById('schemeSelect').value;
    hide('providerBreakdownCard');

    if (!schemeId) return;
    const s = _schemeData.find(x => String(x.id) === String(schemeId));
    if (!s) return;

    const fmt = v => 'Rs. ' + parseFloat(v).toFixed(2);

    document.getElementById('pbMonthlyEmi').textContent  = fmt(s.patient_monthly_emi);
    document.getElementById('pbClinicInterest').textContent = fmt(s.clinic_interest_cost);
    document.getElementById('pbGstInterest').textContent    = fmt(s.gst_on_interest);
    document.getElementById('pbDeduction').textContent      = fmt(s.total_provider_deduction);
    document.getElementById('pbClinicNet').textContent      = fmt(s.clinic_net_amount);

    if (s.upfront_emis > 0) {
        document.getElementById('pbUpfrontCount').textContent = s.upfront_emis;
        document.getElementById('pbUpfrontAmt').textContent   = fmt(s.patient_upfront_amount);
        show('pbUpfrontRow');
    } else {
        hide('pbUpfrontRow');
    }

    if (s.pass_cost_to_patient && s.convenience_charge > 0) {
        document.getElementById('pbConvenienceAmt').textContent = fmt(s.convenience_charge);
        document.getElementById('pbReceiptTotal').textContent   = fmt(s.receipt_total);
        document.getElementById('providerConvFee').value = s.convenience_charge;
        show('pbConvenienceRow');
    } else {
        document.getElementById('providerConvFee').value = 0;
        hide('pbConvenienceRow');
    }

    document.getElementById('providerUpfrontHidden').value = s.patient_upfront_amount || 0;
    show('providerBreakdownCard');
}

// ── Direct EMI calculator ─────────────────────────────────────────────────
function calcEmi() {
    const principal = parseFloat(document.getElementById('pmtAmount').value) || 0;
    const tenure    = parseInt(document.getElementById('emiTenure').value) || 0;
    const annualRate= parseFloat(document.getElementById('emiRate').value) || 0;
    const startDate = document.getElementById('emiStartDate').value;

    if (!principal || !tenure || !startDate) {
        hide('emiCalcResult'); hide('emiScheduleWrap'); return;
    }

    let schedule = [];
    if (annualRate <= 0) {
        const monthly = Math.round((principal / tenure) * 100) / 100;
        let date = new Date(startDate);
        for (let i = 1; i <= tenure; i++) {
            schedule.push({ no:i, date:fmtDate(date), principal:monthly, interest:0, emi:monthly });
            date.setMonth(date.getMonth() + 1);
        }
    } else {
        const r = (annualRate / 100) / 12;
        const factor = Math.pow(1 + r, tenure);
        const emi   = Math.round(principal * r * factor / (factor - 1) * 100) / 100;
        let balance = principal, date = new Date(startDate);
        for (let i = 1; i <= tenure; i++) {
            const interest = Math.round(balance * r * 100) / 100;
            let princ = (i === tenure) ? Math.round(balance*100)/100 : Math.round((emi-interest)*100)/100;
            schedule.push({ no:i, date:fmtDate(date), principal:princ, interest, emi:Math.round((princ+interest)*100)/100 });
            balance -= princ;
            date.setMonth(date.getMonth() + 1);
        }
    }

    const totalPayable  = schedule.reduce((s,r) => s + r.emi, 0);
    const totalInterest = schedule.reduce((s,r) => s + r.interest, 0);

    document.getElementById('emiMonthlyAmt').textContent  = 'Rs. ' + schedule[0].emi.toFixed(2);
    document.getElementById('emiTotalAmt').textContent    = 'Rs. ' + totalPayable.toFixed(2);
    document.getElementById('emiInterestAmt').textContent = 'Rs. ' + totalInterest.toFixed(2);
    show('emiCalcResult');

    const tbody = document.getElementById('emiScheduleBody');
    tbody.innerHTML = schedule.map(r =>
        `<tr><td class="px-3 py-1.5">${r.no}</td><td class="px-3 py-1.5">${r.date}</td>`+
        `<td class="px-3 py-1.5 text-right">Rs. ${r.principal.toFixed(2)}</td>`+
        `<td class="px-3 py-1.5 text-right">Rs. ${r.interest.toFixed(2)}</td>`+
        `<td class="px-3 py-1.5 text-right font-semibold">Rs. ${r.emi.toFixed(2)}</td></tr>`
    ).join('');
    show('emiScheduleWrap');
}

function fmtDate(d) {
    return d.toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });
}

function toggleEmiSchedule() {
    const t = document.getElementById('emiScheduleTable');
    const b = document.getElementById('emiToggleBtn');
    t.classList.toggle('hidden');
    b.textContent = t.classList.contains('hidden') ? 'Show' : 'Hide';
}

// Init
document.addEventListener('DOMContentLoaded', () => {
    onModeChange();
    switchEmiType('direct'); // default
});
</script>
@endif

{{-- ── 2. Edit Auth Modal ──────────────────────────────────────────────────── --}}
@if($canEdit)
<div id="editAuthModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Authorise Edit</h3>
                <p class="text-xs text-gray-500 mt-0.5">Provide a reason and your password to edit this invoice.</p>
            </div>
            <button onclick="document.getElementById('editAuthModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <form method="POST" action="{{ route('billing.editAuth', $invoice) }}" class="space-y-4">
            @csrf
            @if($fromPatient)
            <input type="hidden" name="from_patient" value="{{ $fromPatient }}">
            @endif

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Reason for edit <span class="text-red-500">*</span>
                </label>
                <textarea name="reason" rows="3" required minlength="5"
                          placeholder="e.g. Incorrect treatment description, patient requested change..."
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400">{{ old('reason') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">Minimum 5 characters. This is logged permanently.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Your password <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password" required
                       placeholder="Enter your login password"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-yellow-400">
            </div>

            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="flex-1 py-2.5 bg-yellow-500 text-white font-medium text-sm rounded-lg hover:bg-yellow-600">
                    Proceed to Edit
                </button>
                <button type="button"
                        onclick="document.getElementById('editAuthModal').classList.add('hidden')"
                        class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ── 3. Delete Auth Modal ────────────────────────────────────────────────── --}}
@if($canDelete)
<div id="deleteAuthModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-red-700">Delete Invoice</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    This will permanently remove <strong>{{ $invoice->invoice_number ?? 'this invoice' }}</strong>.
                    A reason and your password are required.
                </p>
            </div>
            <button onclick="document.getElementById('deleteAuthModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-xs text-red-700">
            Deleted invoices are permanently removed. The action is logged with your name and reason.
        </div>

        <form method="POST" action="{{ route('billing.deleteAuth', $invoice) }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Reason for deletion <span class="text-red-500">*</span>
                </label>
                <textarea name="reason" rows="3" required minlength="5"
                          placeholder="e.g. Duplicate invoice, created in error, patient cancelled..."
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">{{ old('reason') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">Minimum 5 characters. Stored permanently in the audit log.</p>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Your password <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password" required
                       placeholder="Enter your login password to confirm"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
            </div>

            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="flex-1 py-2.5 bg-red-600 text-white font-medium text-sm rounded-lg hover:bg-red-700">
                    Confirm Delete
                </button>
                <button type="button"
                        onclick="document.getElementById('deleteAuthModal').classList.add('hidden')"
                        class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
@endif

{{-- ── 4. Mark Provider Payment Received Modal ─────────────────────────────── --}}
{{-- ── Manual Discount Modal (permission-gated, audited) ───────────────────── --}}
@if($canEdit)
<div id="manualDiscountModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-rose-700">Manual Discount</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Applied on subtotal <strong>Rs. {{ number_format($invoice->subtotal, 2) }}</strong>.
                    A reason is required and the action is logged.
                </p>
            </div>
            <button onclick="document.getElementById('manualDiscountModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        @if($errors->hasAny(['manual_discount','manual_discount_type','manual_discount_value','manual_discount_reason']))
        <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-xs text-red-700 space-y-1">
            @foreach($errors->only(['manual_discount','manual_discount_type','manual_discount_value','manual_discount_reason']) as $msg)
                <div>{{ $msg }}</div>
            @endforeach
        </div>
        @endif

        <form method="POST" action="{{ route('billing.manualDiscount.apply', $invoice) }}" class="space-y-4">
            @csrf

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Type</label>
                    <select name="manual_discount_type"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                        <option value="flat" @selected(old('manual_discount_type', $invoice->manual_discount_type) === 'flat')>Flat Amount (Rs.)</option>
                        <option value="percentage" @selected(old('manual_discount_type', $invoice->manual_discount_type) === 'percentage')>Percentage (%)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Value</label>
                    <input type="number" name="manual_discount_value" step="0.01" min="0.01" required
                           value="{{ old('manual_discount_value', $invoice->manual_discount_value > 0 ? $invoice->manual_discount_value : '') }}"
                           placeholder="e.g. 500 or 10"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Reason <span class="text-red-500">*</span>
                </label>
                <textarea name="manual_discount_reason" rows="2" required minlength="3"
                          placeholder="e.g. Loyal patient, staff family, goodwill adjustment..."
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">{{ old('manual_discount_reason', $invoice->manual_discount_reason) }}</textarea>
            </div>

            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="flex-1 py-2.5 bg-rose-600 text-white font-medium text-sm rounded-lg hover:bg-rose-700">
                    Apply Discount
                </button>
                <button type="button"
                        onclick="document.getElementById('manualDiscountModal').classList.add('hidden')"
                        class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
@if($errors->hasAny(['manual_discount','manual_discount_type','manual_discount_value','manual_discount_reason']))
<script>document.addEventListener('DOMContentLoaded',()=>document.getElementById('manualDiscountModal')?.classList.remove('hidden'));</script>
@endif
@endif

@php $hasProviderPending = $invoice->payments->contains(fn($p) => $p->emi_type === 'provider' && !$p->provider_paid_at); @endphp
@if($hasProviderPending)
<div id="providerPaidModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-800">Mark Provider Payment Received</h3>
                <p class="text-xs text-gray-500 mt-0.5">Records that the EMI provider has remitted the clinic net amount. Generates Receipt #2.</p>
            </div>
            <button onclick="document.getElementById('providerPaidModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <form method="POST" id="providerPaidForm" class="space-y-4">
            @csrf
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Date Received <span class="text-red-500">*</span></label>
                    <input type="date" name="provider_paid_date" required
                           value="{{ now()->format('Y-m-d') }}"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Reference No.</label>
                    <input type="text" name="provider_reference" placeholder="NEFT / UTR..."
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>
            <div class="bg-indigo-50 border border-indigo-200 rounded-lg px-4 py-3 text-sm">
                <div class="flex justify-between font-semibold text-indigo-800">
                    <span>Settlement Receipt Amount</span>
                    <span id="ppClinicNet">—</span>
                </div>
                <p class="text-xs text-indigo-500 mt-1">Clinic net = invoice total minus provider deduction.</p>
            </div>
            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="flex-1 py-2.5 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700">
                    Confirm & Generate Receipt
                </button>
                <button type="button"
                        onclick="document.getElementById('providerPaidModal').classList.add('hidden')"
                        class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Payment-to-clinic-net map for the provider paid modal
const _providerPayments = {
    @foreach($invoice->payments->where('emi_type', 'provider') as $pp)
    {{ $pp->id }}: { clinicNet: {{ (float) $pp->clinic_net_amount }}, route: '{{ route('billing.markProviderPaid', [$invoice, $pp]) }}' },
    @endforeach
};

function openProviderPaidModal(paymentId) {
    const data = _providerPayments[paymentId];
    if (!data) return;
    document.getElementById('ppClinicNet').textContent = 'Rs. ' + data.clinicNet.toFixed(2);
    document.getElementById('providerPaidForm').action = data.route;
    document.getElementById('providerPaidModal').classList.remove('hidden');
}

document.getElementById('providerPaidModal')?.addEventListener('click', e => {
    if (e.target === document.getElementById('providerPaidModal')) {
        document.getElementById('providerPaidModal').classList.add('hidden');
    }
});
</script>
@endif

<script>
// Re-open payment modal if there are payment validation errors
@if($errors->hasAny(['amount','payment_mode','payment_date']))
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('paymentModal')?.classList.remove('hidden');
});
@endif
// Re-open edit modal on password error
@if($errors->has('password') && old('_from_edit'))
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('editAuthModal')?.classList.remove('hidden');
});
@endif

// Close modals on backdrop click
['paymentModal','editAuthModal','deleteAuthModal','voidReceiptModal','editPaymentDateModal'].forEach(id => {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', e => { if (e.target === el) el.classList.add('hidden'); });
});
// openProviderPaidModal is defined in the provider modal block above (if it exists)
</script>

{{-- ── 5. Void Receipt Modal ───────────────────────────────────────────────── --}}
<div id="voidReceiptModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-5">

        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-red-700">Void Receipt</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Receipt <strong id="voidReceiptNumber">—</strong>
                    &nbsp;·&nbsp; Rs. <span id="voidReceiptAmount">—</span>
                </p>
            </div>
            <button onclick="document.getElementById('voidReceiptModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3 text-xs text-amber-800 space-y-1">
            <p class="font-semibold">What happens when a receipt is voided</p>
            <p>The receipt and its linked payment entry are reversed. The invoice balance is updated. <strong>Final bills cannot be deleted</strong> — only the receipt record is removed.</p>
        </div>

        <form id="voidReceiptForm" method="POST" action="" class="space-y-4">
            @csrf

            {{-- Reason --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Reason for voiding <span class="text-red-500">*</span>
                </label>
                <textarea name="reason" rows="3" required minlength="5"
                          placeholder="e.g. Duplicate receipt, patient paid via wrong mode, entered in error..."
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">{{ old('reason') }}</textarea>
                <p class="text-xs text-gray-400 mt-1">Minimum 5 characters. Stored permanently in audit log.</p>
            </div>

            {{-- Post-void action --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-2">
                    After voiding, what should happen to the Rs. <span id="voidActionAmt">—</span>?
                </label>
                <div class="space-y-2">
                    <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-gray-400 has-[:checked]:border-gray-500 has-[:checked]:bg-gray-50">
                        <input type="radio" name="action" value="none" class="mt-0.5" checked>
                        <div>
                            <p class="text-sm font-medium text-gray-700">No refund</p>
                            <p class="text-xs text-gray-400">Receipt is voided. Invoice balance reopens. No money movement recorded.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-indigo-400 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                        <input type="radio" name="action" value="wallet_credit" class="mt-0.5">
                        <div>
                            <p class="text-sm font-medium text-indigo-700">Credit to patient wallet</p>
                            <p class="text-xs text-gray-400">Amount is added as permanent wallet balance. Patient can use it on future invoices.</p>
                        </div>
                    </label>
                    <label class="flex items-start gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:border-green-400 has-[:checked]:border-green-500 has-[:checked]:bg-green-50">
                        <input type="radio" name="action" value="refund_note" class="mt-0.5">
                        <div>
                            <p class="text-sm font-medium text-green-700">Physical refund (record only)</p>
                            <p class="text-xs text-gray-400">Records a refund in Finance ledger. Actual cash/transfer is handled offline by staff.</p>
                        </div>
                    </label>
                </div>
            </div>

            {{-- Password --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Your password <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password" required
                       placeholder="Enter your login password to confirm"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
                @error('password')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="flex-1 py-2.5 bg-red-600 text-white font-medium text-sm rounded-lg hover:bg-red-700">
                    Void Receipt
                </button>
                <button type="button"
                        onclick="document.getElementById('voidReceiptModal').classList.add('hidden')"
                        class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Void receipt modal — populated dynamically per receipt row
const _voidRouteBase = '{{ route("billing.receipt.void", [$invoice, "__RECEIPT_ID__"]) }}';

function openVoidModal(receiptNumber, receiptId, amount) {
    document.getElementById('voidReceiptNumber').textContent = receiptNumber;
    document.getElementById('voidReceiptAmount').textContent = amount.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
    document.getElementById('voidActionAmt').textContent     = amount.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
    // Set the form action with the correct receipt ID
    document.getElementById('voidReceiptForm').action = _voidRouteBase.replace('__RECEIPT_ID__', receiptId);
    // Reset form fields
    document.querySelector('#voidReceiptForm textarea[name="reason"]').value = '';
    document.querySelector('#voidReceiptForm input[name="password"]').value  = '';
    document.querySelector('#voidReceiptForm input[value="none"]').checked   = true;
    document.getElementById('voidReceiptModal').classList.remove('hidden');
}

// Edit payment date modal — populated dynamically per payment row
const _editPaymentDateRouteBase = '{{ route("billing.payment.update", [$invoice, "__PAYMENT_ID__"]) }}';

function openEditPaymentDateModal(paymentId, currentDate) {
    document.getElementById('editPaymentDateForm').action = _editPaymentDateRouteBase.replace('__PAYMENT_ID__', paymentId);
    document.querySelector('#editPaymentDateForm input[name="payment_date"]').value = currentDate;
    document.getElementById('editPaymentDateModal').classList.remove('hidden');
}
</script>

{{-- ── Edit Payment Date Modal ─────────────────────────────────────────────── --}}
<div id="editPaymentDateModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-sm p-6 space-y-4">
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-800">Edit Payment Date</h3>
            <button onclick="document.getElementById('editPaymentDateModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <p class="text-xs text-gray-500">Also updates the linked receipt date and finance ledger entry so they stay in sync.</p>
        <form id="editPaymentDateForm" method="POST" action="" class="space-y-4">
            @csrf
            @method('PATCH')
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Payment Date</label>
                <input type="date" name="payment_date" required max="{{ now()->toDateString() }}"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400">
            </div>
            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="flex-1 py-2.5 bg-[#6a0f70] text-white font-medium text-sm rounded-lg hover:bg-[#5a0c60]">
                    Save
                </button>
                <button type="button"
                        onclick="document.getElementById('editPaymentDateModal').classList.add('hidden')"
                        class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

@endsection
                                                                                                                                                                                             