{{-- resources/views/billing/form.blade.php --}}
@extends('layouts.app')
@section('page-title', $invoice ? 'Edit Invoice' : 'New Invoice')

@section('content')
@php
    $memInfo   = $membershipInfo ?? null;
    $memActive = $memInfo && $memInfo['active'];
@endphp
<div class="p-4 md:p-6 max-w-5xl mx-auto space-y-6">

    {{-- Header --}}
    <div class="flex items-center gap-3">
        {{-- Back → billing tab of the patient profile (matches consultation back buttons) --}}
        <a href="{{ $selectedPatient ? route('patients.show', $selectedPatient).'#billing' : route('billing.index') }}"
           class="inline-flex items-center px-3.5 py-1.5 text-xs font-semibold text-gray-500 bg-white border border-gray-300 rounded-md no-underline hover:border-[#6a0f70] hover:text-[#6a0f70] transition">← Back</a>
        <h2 class="text-xl font-semibold text-gray-800">
            {{ $invoice ? 'Edit Invoice ' . $invoice->invoice_number : 'New Invoice' }}
            @if($selectedPatient)
                <span class="text-base font-normal text-gray-400 ml-2">— {{ $selectedPatient->name }}</span>
            @endif
        </h2>
    </div>

    {{-- Billing prompt context banner --}}
    @isset($prompt)
    <div class="flex items-start gap-3 px-4 py-3 bg-amber-50 border border-amber-200 rounded-lg text-sm">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 flex-shrink-0"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <div>
            <span class="font-semibold text-amber-800">Billing Prompt:</span>
            <span class="text-amber-700 ml-1">{{ $prompt->description }}</span>
        </div>
    </div>
    @endisset

    @if($errors->any())
    <div class="px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 space-y-1">
        @foreach($errors->all() as $e)<p>• {{ $e }}</p>@endforeach
    </div>
    @endif

    <form method="POST"
          action="{{ $invoice ? route('billing.update', $invoice) : route('billing.store') }}"
          id="invoiceForm">
        @csrf
        @if($invoice) @method('PUT') @endif
        {{-- Hidden fields the controller expects --}}
        <input type="hidden" name="discount_pct" value="0">
        <input type="hidden" name="due_date" value="">
        {{-- Resolve this prompt when the invoice is saved (must live INSIDE the form to submit) --}}
        @isset($prompt)
        <input type="hidden" name="prompt_ids[]" value="{{ $prompt->id }}">
        @endisset

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            {{-- ── Left column ──────────────────────────────────────────── --}}
            <div class="md:col-span-2 space-y-5">

                {{-- Patient & Date --}}
                <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                    <h3 class="text-sm font-semibold text-gray-700">Patient & Date</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Patient <span class="text-red-500">*</span></label>
                            <select name="patient_id" required
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <option value="">— Select patient —</option>
                                @foreach($patients as $p)
                                    <option value="{{ $p->id }}"
                                        @selected(old('patient_id', $invoice?->patient_id ?? $selectedPatient?->id) == $p->id)>
                                        {{ $p->name }} @if($p->phone)({{ $p->phone }})@endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Invoice Date <span class="text-red-500">*</span></label>
                            <input type="date" name="invoice_date" required
                                   value="{{ old('invoice_date', $invoice?->invoice_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Notes</label>
                        <textarea name="notes" rows="2"
                                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  >{{ old('notes', $invoice?->notes) }}</textarea>
                    </div>
                </div>

                {{-- Line Items --}}
                <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-gray-700">Line Items</h3>
                        <button type="button" onclick="addRow()" dusk="bill-add-item"
                                class="text-sm text-blue-600 hover:text-blue-700 font-medium">+ Add Item</button>
                    </div>

                    {{-- Pre-load visit items --}}
                    @if(isset($preloadedItems) && $preloadedItems->isNotEmpty())
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-xs font-semibold text-blue-700 mb-2">Pre-filled from the visit — review prices below, then Save. (Click a chip to re-add if you removed it.)</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach($preloadedItems as $vi)
                            <button type="button"
                                    onclick="addRow('{{ addslashes($vi->label()) }}', {{ $vi->suggested_price ?? 0 }}, '{{ $vi->tooth_number ?? '' }}', {{ $vi->id }})"
                                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-white border border-blue-200 text-blue-800 rounded-full hover:bg-blue-100 transition">
                                + {{ $vi->label() }}@if($vi->suggested_price > 0) — Rs. {{ number_format($vi->suggested_price, 0) }}@endif
                            </button>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Quick-add --}}
                    <div class="flex gap-2">
                        <select id="treatmentPicker"
                                class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Quick-add treatment —</option>
                            @foreach($treatments as $t)
                                <option value="{{ $t->id }}" data-name="{{ $t->name }}" data-price="{{ $t->default_price }}"
                                        data-gst="{{ $t->gst_pct ?? 0 }}" data-basis="{{ $t->unit_basis ?? 'per_tooth' }}">
                                    {{ $t->name }} — Rs. {{ number_format($t->default_price, 0) }}
                                </option>
                            @endforeach
                        </select>
                        <button type="button" onclick="addFromTreatment()"
                                class="px-3 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">Add</button>
                    </div>

                    {{-- Quick-add: retail product (toothpaste, brushes, OTC medicines — auto-deducts stock) --}}
                    @if(isset($sellableProducts) && $sellableProducts->isNotEmpty())
                    <div class="flex gap-2">
                        <select id="productPicker"
                                class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">— Quick-add product —</option>
                            @foreach($sellableProducts as $p)
                                @php
                                    $stock = $p->stocks_sum_available_qty ?? 0;
                                @endphp
                                <option value="{{ $p->id }}" data-name="{{ $p->product_name }}" data-price="{{ $p->mrp ?? 0 }}"
                                        data-gst="{{ $p->gst_rate ?? 0 }}" data-stock="{{ $stock }}">
                                    {{ $p->product_name }} — Rs. {{ number_format($p->mrp ?? 0, 0) }}{{ $stock <= 0 ? ' (Out of stock)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <button type="button" onclick="addFromProduct()"
                                class="px-3 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">Add</button>
                    </div>
                    @endif

                    {{-- Item rows --}}
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm" id="itemsTable">
                            <thead>
                                <tr class="border-b border-gray-100">
                                    <th class="text-left py-2 text-xs text-gray-500 font-medium">Description</th>
                                    <th class="text-left py-2 text-xs text-gray-500 font-medium w-20">Tooth</th>
                                    <th class="text-right py-2 text-xs text-gray-500 font-medium w-24">Price</th>
                                    <th class="text-right py-2 text-xs text-gray-500 font-medium w-16">Qty</th>
                                    <th class="text-right py-2 text-xs text-gray-500 font-medium w-16">GST%</th>
                                    <th class="text-right py-2 text-xs text-gray-500 font-medium w-24">Total</th>
                                    <th class="w-6"></th>
                                </tr>
                            </thead>
                            <tbody id="itemRows">
                                @php $existingItems = old('items', $invoice ? $invoice->items->toArray() : []) @endphp
                                @foreach($existingItems as $idx => $item)
                                <tr class="item-row border-b border-gray-50" data-idx="{{ $idx }}" data-basis="per_tooth">
                                    <td class="py-2 pr-2">
                                        <input type="text" name="items[{{ $idx }}][description]"
                                               value="{{ $item['description'] }}" required
                                               class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                                               oninput="recalcTotals()">
                                        <input type="hidden" name="items[{{ $idx }}][treatment_id]" value="{{ $item['treatment_id'] ?? '' }}">
                                        <input type="hidden" name="items[{{ $idx }}][inventory_item_id]" value="{{ $item['inventory_item_id'] ?? '' }}">
                                    </td>
                                    <td class="py-2 pr-2">
                                        {{-- Click to open the FDI tooth-chart modal (single / multiple select) --}}
                                        <input type="text" name="items[{{ $idx }}][tooth_number]"
                                               value="{{ $item['tooth_number'] ?? '' }}" placeholder="Select" readonly
                                               onclick="openToothModal(this)"
                                               class="tooth-field w-full border border-gray-200 rounded px-2 py-1.5 text-sm cursor-pointer bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" name="items[{{ $idx }}][unit_price]"
                                               value="{{ $item['unit_price'] }}" min="0" step="0.01" required
                                               class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-blue-500 row-price"
                                               oninput="recalcRow(this)">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" name="items[{{ $idx }}][qty]"
                                               value="{{ $item['qty'] ?? 1 }}" min="1" required
                                               class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-blue-500 row-qty"
                                               oninput="recalcRow(this)">
                                    </td>
                                    <td class="py-2 pr-2">
                                        <input type="number" name="items[{{ $idx }}][gst_pct]"
                                               value="{{ $item['gst_pct'] ?? 0 }}" min="0" max="100" step="0.01"
                                               class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-blue-500 row-gst"
                                               oninput="recalcRow(this)">
                                    </td>
                                    {{-- disc_pct hidden, always 0 --}}
                                    <input type="hidden" name="items[{{ $idx }}][disc_pct]" value="0">
                                    <td class="py-2 pr-2 text-right font-medium row-total text-gray-700">
                                        Rs. {{ number_format(($item['unit_price'] ?? 0) * ($item['qty'] ?? 1), 2) }}
                                    </td>
                                    <td class="py-2 text-center">
                                        <button type="button" onclick="removeRow(this)" class="text-gray-300 hover:text-red-500">×</button>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @if(count($existingItems) === 0)
                    <p class="text-xs text-gray-400 text-center py-4" id="emptyMsg">
                        No items yet — use quick-add or "+ Add Item" above.
                    </p>
                    @endif
                </div>

            </div>

            {{-- ── Right column ─────────────────────────────────────────── --}}
            <div class="space-y-4">

                {{-- AOCP Membership --}}
                <div class="bg-white border {{ $memActive ? 'border-purple-200' : 'border-gray-200' }} rounded-xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <h3 class="text-xs font-semibold {{ $memActive ? 'text-purple-700' : 'text-gray-400' }} uppercase tracking-wide">
                            AOCP Membership
                        </h3>
                        @if($memActive)
                            <span class="text-[10px] font-semibold bg-purple-100 text-purple-700 px-2 py-0.5 rounded-full">
                                Active · {{ $memInfo['days_remaining'] }}d left
                            </span>
                        @endif
                    </div>
                    <input type="hidden" name="membership_discount" id="membershipDiscount" value="0">
                    <input type="hidden" name="membership_id" value="{{ $memActive ? $memInfo['membership_id'] : '' }}">
                    @if($memActive)
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-purple-600">{{ $memInfo['plan_name'] }}</p>
                            <p class="text-sm font-bold text-purple-700" id="membershipDiscAmt">−Rs. 0.00</p>
                        </div>
                        <p class="text-[11px] text-purple-400" id="membershipSummaryEl">Auto-calculated as you add items</p>
                    @else
                        <p class="text-[11px] text-gray-400">No active membership for this patient.</p>
                    @endif
                </div>

                {{-- Coupon Code --}}
                <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-2">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Additional Discount</h3>
                    <p class="text-[11px] text-gray-400">Staff coupon code only</p>
                    <div class="flex gap-2">
                        <input type="text" id="couponInput" placeholder="Enter code"
                               class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm uppercase focus:outline-none focus:ring-2 focus:ring-blue-400"
                               oninput="this.value=this.value.toUpperCase()">
                        <button type="button" onclick="validateCoupon()"
                                class="px-3 py-2 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Apply</button>
                    </div>
                    <div id="couponMsg" class="text-xs hidden"></div>
                    <input type="hidden" name="coupon_code" id="couponCode" value="{{ old('coupon_code') }}">
                    <input type="hidden" name="coupon_discount" id="couponDiscountInput" value="{{ old('coupon_discount', 0) }}">
                </div>

                {{-- Manual Discount (doctor/manager, permission-gated on save) --}}
                <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-2">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Manual Discount</h3>
                    <p class="text-[11px] text-gray-400">Doctor / manager discount — reason required</p>
                    <div class="flex gap-2">
                        <select name="manual_discount_type" id="manualDiscType" onchange="recalcTotals()"
                                class="w-20 border border-gray-200 rounded-lg px-2 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                            <option value="flat" @selected(old('manual_discount_type', $invoice?->manual_discount_type ?? 'flat') === 'flat')>Rs.</option>
                            <option value="percentage" @selected(old('manual_discount_type', $invoice?->manual_discount_type) === 'percentage')>%</option>
                        </select>
                        <input type="number" name="manual_discount_value" id="manualDiscValue" min="0" step="0.01"
                               value="{{ old('manual_discount_value', ($invoice && $invoice->manual_discount_amount > 0) ? $invoice->manual_discount_value : '') }}"
                               placeholder="0" oninput="recalcTotals()"
                               class="flex-1 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                    </div>
                    <input type="text" name="manual_discount_reason" id="manualDiscReason"
                           value="{{ old('manual_discount_reason', $invoice?->manual_discount_reason) }}"
                           placeholder="Reason (e.g. goodwill, staff family)"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-rose-300">
                    @error('manual_discount_value')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                    @error('manual_discount_reason')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                    @error('manual_discount')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                {{-- Wallet Credit --}}
                @if(isset($wallet) && $wallet->hasBalance())
                <div class="bg-white border border-gray-200 rounded-xl p-4 space-y-2">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Wallet Credit</h3>

                    {{-- Balance breakdown --}}
                    <div class="space-y-1 text-xs">
                        @if($wallet->balance_promotional > 0)
                        <div class="flex justify-between items-center">
                            <span class="text-amber-700 font-medium">Promotional</span>
                            <span class="font-semibold text-amber-700">Rs. {{ number_format($wallet->balance_promotional, 0) }}</span>
                        </div>
                        <p class="text-gray-400 text-[11px] leading-snug pb-1">
                            Promotional money may be restricted to specific treatments.
                            System auto-skips restricted promos — only eligible promo balance will be applied.
                        </p>
                        @endif
                        @if($wallet->balance_permanent > 0)
                        <div class="flex justify-between items-center">
                            <span class="text-purple-700 font-medium">Credit Balance</span>
                            <span class="font-semibold text-purple-700">Rs. {{ number_format($wallet->balance_permanent, 0) }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between items-center border-t border-gray-100 pt-1 mt-1">
                            <span class="text-gray-600 font-semibold">Total Available</span>
                            <span class="font-bold text-[#6a0f70]">Rs. {{ number_format($wallet->balance_total, 0) }}</span>
                        </div>
                    </div>

                    {{-- Amount input --}}
                    <div class="flex items-center gap-2 pt-1">
                        <input type="number" name="wallet_applied" id="walletApplied"
                               value="{{ old('wallet_applied', $invoice?->wallet_applied ?? 0) }}"
                               min="0" max="{{ $wallet->balance_total }}" step="0.01" placeholder="0.00"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-400"
                               oninput="recalcTotals()">
                        <button type="button"
                                onclick="document.getElementById('walletApplied').value={{ $wallet->balance_total }}; recalcTotals()"
                                class="px-2 py-2 text-[10px] bg-gray-100 text-gray-600 rounded hover:bg-gray-200 whitespace-nowrap">
                            Use All
                        </button>
                    </div>

                    {{-- Hidden: treatment IDs collected from selected line items (JS-populated) --}}
                    <div id="walletTreatmentIds"></div>
                </div>
                @else
                    <input type="hidden" name="wallet_applied" value="0">
                @endif

                {{-- Totals --}}
                <div class="bg-white border border-gray-200 rounded-xl p-5 space-y-3 sticky top-20">
                    <h3 class="text-sm font-semibold text-gray-700">Total</h3>
                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between text-gray-600">
                            <span>Subtotal</span>
                            <span id="sumSubtotal">Rs. 0.00</span>
                        </div>
                        <div id="rowAOCP" class="flex justify-between text-purple-700 hidden">
                            <span>AOCP Discount</span>
                            <span id="sumAOCP">−Rs. 0.00</span>
                        </div>
                        <div id="rowCoupon" class="flex justify-between text-blue-700 hidden">
                            <span>Additional Discount</span>
                            <span id="sumCoupon">−Rs. 0.00</span>
                        </div>
                        <div id="rowManual" class="flex justify-between text-rose-700 hidden">
                            <span>Manual Discount</span>
                            <span id="sumManual">&minus;Rs. 0.00</span>
                        </div>
                        <div id="rowWallet" class="flex justify-between text-[#6a0f70] hidden">
                            <span>Wallet</span>
                            <span id="sumWallet">−Rs. 0.00</span>
                        </div>
                        <div id="rowGst" class="flex justify-between text-gray-600 hidden">
                            <span>Tax (GST)</span>
                            <span id="sumGst">Rs. 0.00</span>
                        </div>
                        <div class="flex justify-between font-bold text-gray-800 border-t border-gray-100 pt-2 text-base">
                            <span>Total</span>
                            <span id="sumTotal">Rs. 0.00</span>
                        </div>
                    </div>

                    <button type="submit" dusk="bill-save"
                            class="w-full mt-4 py-2.5 bg-blue-600 text-white font-medium text-sm rounded-lg hover:bg-blue-700 transition">
                        {{ $invoice ? 'Update Invoice' : 'Save Invoice' }}
                    </button>
                    <a href="{{ $selectedPatient ? route('patients.show', $selectedPatient).'#billing' : route('billing.index') }}"
                       class="block text-center text-sm text-gray-500 hover:text-gray-700 mt-2">Cancel</a>
                </div>

            </div>
        </div>
    </form>
</div>

{{-- ─────────────────────────────────────────────────────────────────────────
     TOOTH-CHART MODAL — shared FDI picker (single / multiple tooth selection)
     Opens from any line-item "Tooth" field; writes back comma-separated numbers.
     Uses the personalisation colour (--df-color-primary) for selected teeth.
───────────────────────────────────────────────────────────────────────────── --}}
<style>
    .tooth-modal-overlay {
        position: fixed; inset: 0; z-index: 140;
        background: rgba(20, 8, 30, 0.45);
        display: none; align-items: center; justify-content: center;
        padding: 16px;
    }
    .tooth-modal-overlay.open { display: flex; }
    .tooth-modal {
        background: #fff; border-radius: 16px; width: 100%; max-width: 560px;
        box-shadow: 0 20px 50px rgba(0,0,0,0.25); overflow: hidden;
    }
    .tooth-modal-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 16px 20px; border-bottom: 1px solid #eef0f3;
    }
    .tooth-modal-body { padding: 18px 20px; }
    .tooth-arch-label {
        text-align: center; font-size: 9px; font-weight: 700; color: #9ca3af;
        text-transform: uppercase; letter-spacing: .08em; margin: 4px 0;
    }
    .tooth-row { display: flex; justify-content: center; flex-wrap: nowrap; gap: 3px; }
    .tm-midline { width: 2px; background: #e5e7eb; margin: 0 5px; align-self: stretch; }
    .tm-tooth {
        min-width: 30px; height: 36px; padding: 0 2px;
        border: 1.5px solid #e0d4ea; border-radius: 7px; background: #fff;
        font-size: 12px; font-weight: 600; color: #4b5563; cursor: pointer;
        transition: all 120ms;
    }
    .tm-tooth:hover { border-color: var(--df-color-primary, #6a0f70); color: var(--df-color-primary, #6a0f70); }
    .tm-tooth.sel {
        background: var(--df-color-primary, #6a0f70);
        border-color: var(--df-color-primary, #6a0f70);
        color: #fff; font-weight: 700;
    }
    /* Adult/child (mixed dentition) per-position toggle */
    .tm-tooth-slot { display: flex; flex-direction: column; align-items: center; gap: 2px; }
    .tm-dchip {
        width: 22px; height: 14px; border: 1px solid #e0d4ea; border-radius: 4px;
        font-size: 8px; font-weight: 800; line-height: 1; color: #9ca3af;
        background: #fff; cursor: pointer; padding: 0;
    }
    .tm-dchip:hover { border-color: var(--df-color-primary, #6a0f70); color: var(--df-color-primary, #6a0f70); }
    .tm-dchip.is-child { background: #fce7f3; border-color: #db2777; color: #db2777; }
    .tooth-modal-foot {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 20px; border-top: 1px solid #eef0f3; background: #faf9fc;
    }
    .tm-btn {
        padding: 8px 18px; border-radius: 8px; font-size: 13px; font-weight: 600;
        cursor: pointer; border: none;
    }
    .tm-btn-apply { background: var(--df-color-primary, #6a0f70); color: #fff; }
    .tm-btn-cancel { background: #f1f0f4; color: #555; }
    .tm-btn-clear { background: none; color: #dc2626; font-size: 12px; }
</style>

<div class="tooth-modal-overlay" id="toothModal" onclick="if(event.target===this)closeToothModal()">
    <div class="tooth-modal">
        <div class="tooth-modal-head">
            <div>
                <p class="text-sm font-semibold text-gray-800">Select Tooth / Teeth</p>
                <p class="text-xs text-gray-400">FDI notation · tap to select one or more · small chip toggles primary (child) tooth</p>
            </div>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full"
                  style="background:var(--df-color-light,#f9f3fa);color:var(--df-color-primary,#6a0f70);"
                  id="toothCount">0 selected</span>
        </div>
        <div class="tooth-modal-body">
            <div class="tooth-arch-label">Upper</div>
            <div class="tooth-row" id="archUpper"></div>
            <div class="tooth-arch-label" style="color:#d1d5db;letter-spacing:.18em;margin:8px 0;">— MIDLINE —</div>
            <div class="tooth-row" id="archLower"></div>
            <div class="tooth-arch-label">Lower</div>
        </div>
        <div class="tooth-modal-foot">
            <button type="button" class="tm-btn tm-btn-clear" onclick="clearToothSelection()">Clear all</button>
            <div style="display:flex;gap:8px;">
                <button type="button" class="tm-btn tm-btn-cancel" onclick="closeToothModal()">Cancel</button>
                <button type="button" class="tm-btn tm-btn-apply" onclick="applyToothSelection()">Apply</button>
            </div>
        </div>
    </div>
</div>

<script>
// rowIdx/visitItemIds must be initialized before ANYTHING else in this script
// runs — addRow() (called from inline onclick="" chip buttons further down
// the page, e.g. the "Pre-filled from the visit" re-add chips) closes over
// these. They used to be declared much further down (right before addRow's
// own definition), which left a window where any earlier failure in this
// script — even one recovered from — could leave them stuck in the temporal
// dead zone, so every click on those chips threw "Cannot access 'rowIdx'
// before initialization" and silently did nothing (2026-07-13 bug report:
// clicking a re-add chip never added the line item).
let rowIdx = {{ count($existingItems ?? []) }};
const visitItemIds = new Set();

// ── Tooth-chart modal ───────────────────────────────────────────────────────
// FDI_UPPER/FDI_LOWER are always PERMANENT position codes — that's what
// drives layout order. Mixed dentition (toothDentitionMode) lets any of the
// 20 anterior/premolar slots display & select its primary (child) tooth
// instead; molars (16-18 etc.) have no primary predecessor so never toggle —
// see window.DentalNotation (partials.dental-notation, loaded globally).
const FDI_UPPER = [18,17,16,15,14,13,12,11, null, 21,22,23,24,25,26,27,28];
const FDI_LOWER = [48,47,46,45,44,43,42,41, null, 31,32,33,34,35,36,37,38];
let toothTargetInput = null;          // the line-item input we write back to
let toothSelected = new Set();        // currently selected tooth codes (as strings)
let toothDentitionMode = {};          // { permanentPos: 'primary' } — absent/'permanent' = adult tooth

function activeCode(slot) {
    const pos = Number(slot.dataset.pos);
    return window.DentalNotation.displayCode(pos, toothDentitionMode[pos] || 'permanent');
}

function refreshSlot(slot) {
    const btn = slot.querySelector('.tm-tooth');
    const code = activeCode(slot);
    btn.textContent = code;
    btn.dataset.tooth = code;
    btn.classList.toggle('sel', toothSelected.has(String(code)));

    const chip = slot.querySelector('.tm-dchip');
    if (chip) {
        const isChild = toothDentitionMode[Number(slot.dataset.pos)] === 'primary';
        chip.textContent = isChild ? 'P' : 'A';
        chip.classList.toggle('is-child', isChild);
        chip.title = isChild ? 'Primary tooth — click for permanent' : 'Permanent tooth — click for primary (child)';
    }
}

function toggleDentitionAt(slot) {
    const pos = Number(slot.dataset.pos);
    const oldCode = activeCode(slot);
    const wasChild = toothDentitionMode[pos] === 'primary';
    toothDentitionMode[pos] = wasChild ? 'permanent' : 'primary';
    const newCode = activeCode(slot);
    // Carry the selection over to the newly-shown code so toggling never
    // silently drops what was picked.
    if (toothSelected.has(String(oldCode))) {
        toothSelected.delete(String(oldCode));
        toothSelected.add(String(newCode));
    }
    refreshSlot(slot);
    updateToothCount();
}

// Build the two arches once on load
function buildToothArches() {
    const render = (containerId, list) => {
        const el = document.getElementById(containerId);
        el.innerHTML = '';
        list.forEach(n => {
            if (n === null) {
                const mid = document.createElement('div');
                mid.className = 'tm-midline';
                el.appendChild(mid);
                return;
            }
            const slot = document.createElement('div');
            slot.className = 'tm-tooth-slot';
            slot.dataset.pos = n;

            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'tm-tooth';
            b.onclick = () => toggleTooth(String(activeCode(slot)), b);
            slot.appendChild(b);

            if (window.DentalNotation.hasPrimary(n)) {
                const chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'tm-dchip';
                chip.onclick = (e) => { e.stopPropagation(); toggleDentitionAt(slot); };
                slot.appendChild(chip);
            }

            refreshSlot(slot);
            el.appendChild(slot);
        });
    };
    render('archUpper', FDI_UPPER);
    render('archLower', FDI_LOWER);
}

function openToothModal(input) {
    toothTargetInput = input;
    // Parse existing value (e.g. "36, 37") into the selection set
    toothSelected = new Set(
        (input.value || '').split(',').map(s => s.trim()).filter(Boolean)
    );
    // Rebuild dentition mode from the existing value so any previously-picked
    // primary (child) teeth display correctly when re-opening the picker.
    toothDentitionMode = {};
    toothSelected.forEach(code => {
        const num = Number(code);
        if (window.DentalNotation.isPrimary(num)) {
            toothDentitionMode[window.DentalNotation.D2P[num]] = 'primary';
        }
    });
    document.querySelectorAll('#toothModal .tm-tooth-slot').forEach(refreshSlot);
    updateToothCount();
    document.getElementById('toothModal').classList.add('open');
}

function toggleTooth(n, btn) {
    if (toothSelected.has(n)) { toothSelected.delete(n); btn.classList.remove('sel'); }
    else                      { toothSelected.add(n);    btn.classList.add('sel'); }
    updateToothCount();
}

function updateToothCount() {
    document.getElementById('toothCount').textContent = toothSelected.size + ' selected';
}

function clearToothSelection() {
    toothSelected.clear();
    document.querySelectorAll('#toothModal .tm-tooth').forEach(b => b.classList.remove('sel'));
    updateToothCount();
}

function applyToothSelection() {
    if (toothTargetInput) {
        // Sort numerically for a tidy "12, 36, 47" display
        const sorted = Array.from(toothSelected).map(Number).sort((a,b)=>a-b);
        toothTargetInput.value = sorted.join(', ');
        // Auto-set quantity from the number of teeth (per-tooth treatments only)
        autoQtyFromTeeth(toothTargetInput.closest('tr'));
    }
    closeToothModal();
}

function closeToothModal() {
    document.getElementById('toothModal').classList.remove('open');
    toothTargetInput = null;
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && document.getElementById('toothModal').classList.contains('open')) closeToothModal();
});
// Deferred to DOMContentLoaded (2026-07-13 fix) — buildToothArches() calls
// window.DentalNotation.hasPrimary(), and partials.dental-notation (which
// defines window.DentalNotation) is @included near the bottom of the shared
// layout, AFTER this page's own script runs. Calling it synchronously here
// threw "DentalNotation is undefined" on real page loads and silently
// aborted the REST of this script — every let/const declared afterward
// (memBenefits, then whatever came next) was left permanently uninitialized,
// and neither the invoiceForm submit listener nor the auto-add-from-visit
// DOMContentLoaded block below ever got registered. That's the real cause
// behind: totals/AOCP discount never updating, and line items pre-filled
// from a visit not appearing. Deferring here sidesteps the load-order race
// entirely — by DOMContentLoaded every other script has already run.
document.addEventListener('DOMContentLoaded', buildToothArches);

// Membership benefit config from server — used for client-side recalc
const memBenefits = @json($memActive ? ($memInfo['benefit_config'] ?? null) : null);

// Build free-item trigger strings from config
function getFreeTriggers() {
    if (!memBenefits) return [];
    const t = [];
    if (memBenefits.free_consultation) t.push('consultation');
    if (memBenefits.free_xray)         { t.push('x-ray'); t.push('xray'); }
    if (memBenefits.free_scaling)      t.push('scaling');
    (memBenefits.free_treatments || []).forEach(ft => t.push(ft.toLowerCase().trim()));
    return t;
}

function calcMembershipDiscount() {
    if (!memBenefits) return 0;
    const triggers = getFreeTriggers();
    let discount = 0;
    let eligibleSubtotal = 0; // treatment/procedure rows only — FMCG excluded below
    document.querySelectorAll('.item-row').forEach(row => {
        // FMCG/retail product rows (carry an inventory_item_id) never receive AOCP
        // membership benefits — any discount on those rows is manual, entered
        // directly on the invoice row. Skip them entirely here.
        const invItemId = row.querySelector('[name*="[inventory_item_id]"]')?.value || '';
        if (invItemId) return;
        const name  = (row.querySelector('[name*="[description]"]')?.value || '').toLowerCase();
        const price = parseFloat(row.querySelector('.row-price')?.value) || 0;
        const qty   = parseInt(row.querySelector('.row-qty')?.value) || 1;
        const net   = price * qty;
        eligibleSubtotal += net;
        if (triggers.some(t => name.includes(t))) {
            discount += net;
        }
    });
    const pct = parseFloat(memBenefits.discount_percent || 0);
    if (pct > 0) {
        discount += Math.round(Math.max(0, eligibleSubtotal - discount) * (pct / 100) * 100) / 100;
    }
    return Math.round(discount * 100) / 100;
}

function addRow(desc = '', price = 0, tooth = '', visitItemId = null, treatmentId = null, gst = 0, basis = 'per_tooth', inventoryItemId = null) {
    document.getElementById('emptyMsg')?.remove();
    const idx = rowIdx++;
    const tbody = document.getElementById('itemRows');
    const tr = document.createElement('tr');
    tr.className = 'item-row border-b border-gray-50';
    tr.dataset.idx = idx;
    tr.dataset.basis = basis || 'per_tooth';   // drives auto-quantity from teeth
    if (visitItemId) { visitItemIds.add(visitItemId); tr.dataset.visitItemId = visitItemId; }
    if (treatmentId) { walletTreatmentIds.add(treatmentId); tr.dataset.treatmentId = treatmentId; syncWalletTreatmentIds(); }
    tr.innerHTML = `
        <td class="py-2 pr-2">
            <input type="text" name="items[${idx}][description]" value="${desc}" required
                   class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-blue-500"
                   oninput="recalcTotals()">
            <input type="hidden" name="items[${idx}][treatment_id]" value="${treatmentId || ''}">
            <input type="hidden" name="items[${idx}][inventory_item_id]" value="${inventoryItemId || ''}">
        </td>
        <td class="py-2 pr-2">
            <input type="text" name="items[${idx}][tooth_number]" value="${tooth}" placeholder="Select" readonly
                   onclick="openToothModal(this)"
                   class="tooth-field w-full border border-gray-200 rounded px-2 py-1.5 text-sm cursor-pointer bg-white focus:outline-none focus:ring-1 focus:ring-blue-500">
        </td>
        <td class="py-2 pr-2">
            <input type="number" name="items[${idx}][unit_price]" value="${price}" min="0" step="0.01" required
                   class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-blue-500 row-price"
                   oninput="recalcRow(this)">
        </td>
        <td class="py-2 pr-2">
            <input type="number" name="items[${idx}][qty]" value="1" min="1" required
                   class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-blue-500 row-qty"
                   oninput="recalcRow(this)">
        </td>
        <td class="py-2 pr-2">
            <input type="number" name="items[${idx}][gst_pct]" value="${gst || 0}" min="0" max="100" step="0.01"
                   class="w-full border border-gray-200 rounded px-2 py-1.5 text-sm text-right focus:outline-none focus:ring-1 focus:ring-blue-500 row-gst"
                   oninput="recalcRow(this)">
        </td>
        <input type="hidden" name="items[${idx}][disc_pct]" value="0">
        <td class="py-2 pr-2 text-right font-medium row-total text-gray-700">Rs. 0.00</td>
        <td class="py-2 text-center">
            <button type="button" onclick="removeRow(this)" class="text-gray-300 hover:text-red-500">×</button>
        </td>`;
    tbody.appendChild(tr);
    recalcRow(tr.querySelector('.row-price'));
}

function addFromTreatment() {
    const sel = document.getElementById('treatmentPicker');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    // Pull full detail from the Treatment master: price, GST and unit basis.
    addRow(opt.dataset.name, opt.dataset.price, '', null, parseInt(opt.value),
           parseFloat(opt.dataset.gst) || 0, opt.dataset.basis || 'per_tooth');
    sel.selectedIndex = 0;
}

// Retail product line (toothpaste, brushes, OTC medicines) — no tooth number,
// no treatment_id, but carries inventory_item_id so the sale auto-deducts stock.
function addFromProduct() {
    const sel = document.getElementById('productPicker');
    const opt = sel.options[sel.selectedIndex];
    if (!opt.value) return;
    if ((parseFloat(opt.dataset.stock) || 0) <= 0) {
        alert(opt.dataset.name + ' is out of stock and can\'t be added to this invoice.');
        sel.selectedIndex = 0;
        return;
    }
    addRow(opt.dataset.name, opt.dataset.price, '', null, null,
           parseFloat(opt.dataset.gst) || 0, 'per_visit', parseInt(opt.value));
    sel.selectedIndex = 0;
}

// Auto-quantity: set a row's qty from the number of selected teeth, unless the
// treatment is billed whole-mouth / per-visit (then qty stays as-is / 1).
function autoQtyFromTeeth(row) {
    if (!row) return;
    const basis = row.dataset.basis || 'per_tooth';
    const toothVal = row.querySelector('.tooth-field')?.value || '';
    const teeth = toothVal.split(',').map(s => s.trim()).filter(Boolean);
    const qtyEl = row.querySelector('.row-qty');
    if (!qtyEl) return;
    if (basis === 'per_tooth' && teeth.length > 0) {
        qtyEl.value = teeth.length;
        recalcRow(qtyEl);
    }
}

function removeRow(btn) {
    const tr = btn.closest('tr');
    if (tr.dataset.visitItemId) visitItemIds.delete(parseInt(tr.dataset.visitItemId));
    if (tr.dataset.treatmentId) walletTreatmentIds.delete(parseInt(tr.dataset.treatmentId));
    tr.remove();
    syncWalletTreatmentIds();
    recalcTotals();
}

// Track treatment IDs from selected treatments (for promo restriction enforcement)
const walletTreatmentIds = new Set();

function syncWalletTreatmentIds() {
    const container = document.getElementById('walletTreatmentIds');
    if (!container) return;
    container.innerHTML = '';
    walletTreatmentIds.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = 'wallet_treatment_ids[]';
        inp.value = id;
        container.appendChild(inp);
    });
}

function recalcRow(input) {
    const row   = input.closest('tr');
    const price = parseFloat(row.querySelector('.row-price').value) || 0;
    const qty   = parseInt(row.querySelector('.row-qty').value) || 1;
    const gst   = parseFloat(row.querySelector('.row-gst').value) || 0;
    const net   = price * qty;
    const total = net + net * (gst / 100);
    row.querySelector('.row-total').textContent = 'Rs. ' + total.toFixed(2);
    recalcTotals();
}

function recalcTotals() {
    let subtotal = 0, totalGst = 0;
    document.querySelectorAll('.item-row').forEach(row => {
        const price = parseFloat(row.querySelector('.row-price')?.value) || 0;
        const qty   = parseInt(row.querySelector('.row-qty')?.value) || 1;
        const gst   = parseFloat(row.querySelector('.row-gst')?.value) || 0;
        const net   = price * qty;
        subtotal  += net;
        totalGst  += net * (gst / 100);
    });

    // Auto-calculate membership discount
    const memDisc   = calcMembershipDiscount();
    const couponDisc = parseFloat(document.getElementById('couponDiscountInput')?.value) || 0;
    const wallet     = parseFloat(document.getElementById('walletApplied')?.value) || 0;

    // Manual discount (flat Rs. or % of subtotal); reason becomes required when > 0
    const mdType = document.getElementById('manualDiscType')?.value || 'flat';
    const mdVal  = parseFloat(document.getElementById('manualDiscValue')?.value) || 0;
    let manualDisc = 0;
    if (mdVal > 0) {
        manualDisc = mdType === 'percentage' ? subtotal * mdVal / 100 : Math.min(mdVal, subtotal);
    }
    const mdReasonEl = document.getElementById('manualDiscReason');
    if (mdReasonEl) mdReasonEl.required = mdVal > 0;

    const total = Math.max(0, subtotal + totalGst - memDisc - couponDisc - wallet - manualDisc);

    // Update hidden field
    document.getElementById('membershipDiscount').value = memDisc;

    // Update display
    document.getElementById('sumSubtotal').textContent = 'Rs. ' + subtotal.toFixed(2);
    document.getElementById('sumTotal').textContent    = 'Rs. ' + total.toFixed(2);

    // Membership panel display
    const discAmtEl = document.getElementById('membershipDiscAmt');
    if (discAmtEl) discAmtEl.textContent = '−Rs. ' + memDisc.toFixed(2);

    // Show/hide rows
    const rowAOCP = document.getElementById('rowAOCP');
    if (rowAOCP) { rowAOCP.classList.toggle('hidden', memDisc <= 0); document.getElementById('sumAOCP').textContent = '−Rs. ' + memDisc.toFixed(2); }

    const rowCoupon = document.getElementById('rowCoupon');
    if (rowCoupon) { rowCoupon.classList.toggle('hidden', couponDisc <= 0); document.getElementById('sumCoupon').textContent = '−Rs. ' + couponDisc.toFixed(2); }

    const rowManual = document.getElementById('rowManual');
    if (rowManual) { rowManual.classList.toggle('hidden', manualDisc <= 0); document.getElementById('sumManual').textContent = '−Rs. ' + manualDisc.toFixed(2); }

    const rowWallet = document.getElementById('rowWallet');
    if (rowWallet) { rowWallet.classList.toggle('hidden', wallet <= 0); document.getElementById('sumWallet').textContent = '−Rs. ' + wallet.toFixed(2); }

    const rowGst = document.getElementById('rowGst');
    if (rowGst) { rowGst.classList.toggle('hidden', totalGst <= 0); document.getElementById('sumGst').textContent = 'Rs. ' + totalGst.toFixed(2); }
}

function validateCoupon() {
    const code      = document.getElementById('couponInput').value.trim();
    const msgEl     = document.getElementById('couponMsg');
    const patientId = document.querySelector('[name="patient_id"]')?.value || '';
    const subtotal  = parseFloat(document.getElementById('sumSubtotal').textContent.replace('Rs. ','')) || 0;
    if (!code) { showCouponMsg('Enter a coupon code first.', 'error'); return; }
    showCouponMsg('Checking…', 'neutral');
    fetch('{{ route('billing.validateCoupon') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
        body: JSON.stringify({ code, subtotal, patient_id: patientId }),
    })
    .then(r => r.json())
    .then(data => {
        if (data.valid) {
            document.getElementById('couponCode').value          = code;
            document.getElementById('couponDiscountInput').value = data.discount_amount;
            showCouponMsg('✓ ' + data.label + ' applied (−Rs. ' + parseFloat(data.discount_amount).toFixed(2) + ')', 'success');
        } else {
            document.getElementById('couponCode').value          = '';
            document.getElementById('couponDiscountInput').value = 0;
            showCouponMsg(data.error || 'Invalid coupon.', 'error');
        }
        recalcTotals();
    })
    .catch(() => showCouponMsg('Could not validate. Try again.', 'error'));
}

function showCouponMsg(msg, type) {
    const el = document.getElementById('couponMsg');
    el.textContent = msg;
    el.className = 'text-xs mt-1 ' + (type === 'success' ? 'text-green-600' : type === 'error' ? 'text-red-500' : 'text-gray-500');
    el.classList.remove('hidden');
}

document.getElementById('invoiceForm').addEventListener('submit', function() {
    visitItemIds.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'visit_item_ids[]'; inp.value = id;
        this.appendChild(inp);
    });
});

document.addEventListener('DOMContentLoaded', () => {
@if(isset($preloadedItems) && $preloadedItems->isNotEmpty() && !isset($invoice))
    // Auto-add the visit's treatments as editable rows so the draft opens ready to review.
    @foreach($preloadedItems as $vi)
    addRow(@json($vi->label()), {{ (float) ($vi->suggested_price ?? 0) }}, @json((string) ($vi->tooth_number ?? '')), {{ $vi->id }});
    @endforeach
@endif
    document.querySelectorAll('.item-row').forEach(row => {
        const p = row.querySelector('.row-price');
        if (p) recalcRow(p);
    });
    recalcTotals();
});
</script>
@endsection
