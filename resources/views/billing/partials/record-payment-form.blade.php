{{--
    SHARED RECORD-PAYMENT FORM — stage 1 of the A3 follow-up extraction.

    The per-invoice "Record Payment" form existed in THREE near-identical copies
    (billing/show.blade.php, billing/_invoice_panel.blade.php and
    patients/profile/quick-pay-modal.blade.php) with three different element-id
    prefixes and ~760 lines of duplicated JavaScript between them. This partial
    is the single source of that markup.

    IDs and JS function names stay PREFIX-DRIVEN on purpose: each caller keeps
    the exact ids and handler names its existing script already binds to, so
    this extraction moves markup ONLY and touches no JavaScript. Rendering it
    with the panel's arguments reproduces the previous markup byte for byte.

    Parameters
      $invoice              (required) the invoice being paid
      $idPrefix             element-id prefix, e.g. 'p'   -> id="pAmount"
      $fnPrefix             JS handler prefix, e.g. 'p'   -> pOnModeChange()
                            (separate from $idPrefix: show.blade.php uses
                            pmt* ids with unprefixed handlers)
      $formId               the <form> id
      $action               POST target; defaults to billing.payment
      $fromPatient          nullable patient id carried through the redirect
      $labelClass / $inputClass   caller styling

    EMI is rendered here because this form is always INVOICE-SCOPED. It must not
    be reused for patient-level FIFO: an instalment schedule needs one invoice
    to attach to. FIFO keeps its own form and its own narrower mode list.

    Payment modes come from App\Enums\PaymentMode (A3). 'wallet' is excluded —
    wallet tender is written by WalletService, never chosen on a form.
--}}
@php
    $idPrefix    = $idPrefix    ?? 'pmt';
    $fnPrefix    = $fnPrefix    ?? $idPrefix;
    $formId      = $formId      ?? $idPrefix . 'PaymentForm';
    $action      = $action      ?? route('billing.payment', $invoice);
    $fromPatient = $fromPatient ?? null;
    $labelClass  = $labelClass  ?? 'block text-xs font-medium text-gray-500 mb-1';
    $inputClass  = $inputClass  ?? 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500';
    $submitLabel = $submitLabel ?? 'Save Payment';
    // Caller decides how Cancel dismisses it — the panel collapses an inline
    // block, a modal closes itself. No default that silently no-ops.
    $cancelOnclick = $cancelOnclick ?? "document.getElementById('panelPayForm').classList.add('hidden');document.querySelector('#panelPaySection button').classList.remove('hidden')";
@endphp
        <form method="POST" action="{{ $action }}" id="{{ $formId }}">
            @csrf
            <input type="hidden" name="from_patient" value="{{ $fromPatient }}">
            <input type="hidden" name="emi_type" id="{{ $idPrefix }}EmiType" value="direct">

            {{-- Amount + Date --}}
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="{{ $labelClass }}">Amount (Rs. ) *</label>
                    <input type="number" name="amount" id="{{ $idPrefix }}Amount" required
                           value="{{ $invoice->balance_due }}" min="0.01" step="0.01"
                           oninput="{{ $fnPrefix }}OnAmountChange()"
                           class="{{ $inputClass }}">
                </div>
                <div>
                    <label class="{{ $labelClass }}">Date *</label>
                    <input type="date" name="payment_date" id="{{ $idPrefix }}Date" required
                           value="{{ now()->format('Y-m-d') }}"
                           class="{{ $inputClass }}">
                </div>
            </div>

            {{-- Mode --}}
            <div>
                <label class="{{ $labelClass }}">Mode *</label>
                <select name="payment_mode" id="{{ $idPrefix }}Mode" required onchange="{{ $fnPrefix }}OnModeChange()"
                        class="{{ $inputClass }}">
                    @foreach (\App\Enums\PaymentMode::options(['wallet']) as $pm)
                        <option value="{{ $pm['value'] }}">{{ $pm['label'] }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Reference (UPI / bank) --}}
            <div id="{{ $idPrefix }}FieldRef" class="hidden">
                <label class="{{ $labelClass }}">Transaction Reference No. *</label>
                <input type="text" name="reference_no" placeholder="UTR / Transaction ID"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
            </div>

            {{-- Credit card fee --}}
            <div id="{{ $idPrefix }}FieldCC" class="hidden space-y-2">
                <div id="{{ $idPrefix }}CcFeePanel" class="hidden bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs">
                    <div class="flex justify-between font-semibold text-amber-800">
                        <span>Convenience Fee ({{ rtrim(rtrim(number_format((float) \App\Models\AppSetting::get('cc_convenience_rate', 2.5), 2), '0'), '.') }}%)</span>
                        <span id="{{ $idPrefix }}CcFeeAmt">Rs. 0.00</span>
                    </div>
                    <p class="text-amber-600 mt-0.5">Applied on credit card payments above Rs. {{ number_format((float) \App\Models\AppSetting::get('cc_convenience_threshold', 10000), 0) }}.</p>
                    <input type="hidden" name="convenience_fee" id="{{ $idPrefix }}ConvFee" value="0">
                </div>
                {{-- What the patient actually pays. billing/show.blade.php has always
                     shown this; the panel and the quick-pay modal did not, which is why
                     the same card payment looked different depending on the screen. --}}
                <div id="{{ $idPrefix }}CcTotal" class="hidden flex justify-between text-sm font-semibold text-gray-700 bg-gray-50 rounded-lg px-3 py-2">
                    <span>Total charged to patient:</span>
                    <span id="{{ $idPrefix }}CcTotalAmt">Rs. 0.00</span>
                </div>
                <div id="{{ $idPrefix }}CcSplitWarn" class="hidden bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xs text-red-700">
                    Split transaction detected. The 2.5% fee is calculated on the combined daily total for this patient.
                </div>
            </div>

            {{-- Cheque fields --}}
            <div id="{{ $idPrefix }}FieldCheque" class="hidden space-y-2">
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="{{ $labelClass }}">Bank Name *</label>
                        <input type="text" name="bank_name" placeholder="HDFC Bank"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="{{ $labelClass }}">Cheque No. *</label>
                        <input type="text" name="cheque_no"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="{{ $labelClass }}">Cheque Date *</label>
                    <input type="date" name="cheque_date"
                           class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm">
                </div>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2 text-xs text-yellow-800">
                    <p class="font-semibold">Cheque Policy</p>
                    <p class="mt-0.5">Receipt generated only after realisation. Bounce charges apply on dishonoured cheques.</p>
                </div>
            </div>

            {{-- EMI section --}}
            <div id="{{ $idPrefix }}FieldEmi" class="hidden space-y-3">
                {{-- Sub-type toggle --}}
                <div class="flex gap-2">
                    <button type="button" id="{{ $idPrefix }}BtnDirect" onclick="{{ $fnPrefix }}SwitchEmi('direct')"
                            class="flex-1 py-2 text-xs font-semibold rounded-lg border border-purple-600 bg-purple-600 text-white">
                        Direct EMI<br>
                        <span class="font-normal opacity-80">Clinic collects instalments</span>
                    </button>
                    <button type="button" id="{{ $idPrefix }}BtnProvider" onclick="{{ $fnPrefix }}SwitchEmi('provider')"
                            class="flex-1 py-2 text-xs font-semibold rounded-lg border border-purple-200 bg-white text-purple-700 {{ $activeEmiProviders->isEmpty() ? 'opacity-40 cursor-not-allowed' : '' }}"
                            {{ $activeEmiProviders->isEmpty() ? 'disabled title="No EMI providers configured in Settings"' : '' }}>
                        Provider EMI<br>
                        <span class="font-normal opacity-80">Provider pays clinic upfront</span>
                    </button>
                </div>

                {{-- Direct EMI fields --}}
                <div id="{{ $idPrefix }}DirectFields" class="space-y-2">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Financer / Bank (optional)</label>
                        <input type="text" name="emi_provider" placeholder="e.g. HDFC Card EMI, SBI EMI..."
                               class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-purple-400">
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Tenure (months) *</label>
                            <select name="emi_tenure" id="{{ $idPrefix }}EmiTenure" onchange="{{ $fnPrefix }}CalcEmi()"
                                    class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                                <option value="">Select…</option>
                                @foreach([3,6,9,12,18,24,36,48,60] as $m)
                                <option value="{{ $m }}">{{ $m }} months</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Interest % p.a. *</label>
                            <input type="number" name="emi_interest_rate" id="{{ $idPrefix }}EmiRate"
                                   value="0" min="0" max="36" step="0.01" oninput="{{ $fnPrefix }}CalcEmi()"
                                   class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">First Auto-Debit Date *</label>
                        <input type="date" name="emi_start_date" id="{{ $idPrefix }}EmiStart" onchange="{{ $fnPrefix }}CalcEmi()"
                               class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                    </div>
                    {{-- Calc result --}}
                    <div id="{{ $idPrefix }}EmiResult" class="hidden bg-purple-50 border border-purple-200 rounded-lg px-3 py-2 text-xs space-y-1">
                        <div class="flex justify-between font-semibold text-purple-800">
                            <span>Monthly EMI</span><span id="{{ $idPrefix }}EmiMonthly">—</span>
                        </div>
                        <div class="flex justify-between text-purple-600">
                            <span>Total Payable</span><span id="{{ $idPrefix }}EmiTotal">—</span>
                        </div>
                        <div class="flex justify-between text-purple-600">
                            <span>Total Interest</span><span id="{{ $idPrefix }}EmiInterest">—</span>
                        </div>
                    </div>
                    {{-- Schedule preview --}}
                    <div id="{{ $idPrefix }}EmiScheduleWrap" class="hidden">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-medium text-gray-600">Instalment Schedule</span>
                            <button type="button" onclick="{{ $fnPrefix }}ToggleEmiSchedule()" id="{{ $idPrefix }}EmiToggleBtn"
                                    class="text-xs text-purple-600 hover:underline">Show</button>
                        </div>
                        <div id="{{ $idPrefix }}EmiScheduleTable" class="hidden overflow-x-auto rounded-lg border border-purple-100">
                            <table class="w-full text-xs">
                                <thead class="bg-purple-50">
                                    <tr>
                                        <th class="px-2 py-1.5 text-left text-purple-700">#</th>
                                        <th class="px-2 py-1.5 text-left text-purple-700">Due Date</th>
                                        <th class="px-2 py-1.5 text-right text-purple-700">Principal</th>
                                        <th class="px-2 py-1.5 text-right text-purple-700">Interest</th>
                                        <th class="px-2 py-1.5 text-right text-purple-700">EMI</th>
                                    </tr>
                                </thead>
                                <tbody id="{{ $idPrefix }}EmiScheduleBody" class="divide-y divide-purple-50 bg-white"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {{-- Provider EMI fields --}}
                <div id="{{ $idPrefix }}ProviderFields" class="hidden space-y-2">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">EMI Provider *</label>
                        <select id="{{ $idPrefix }}ProviderSel" onchange="{{ $fnPrefix }}LoadSchemes()"
                                class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                            <option value="">— Select Provider —</option>
                            @foreach($activeEmiProviders as $ep)
                            <option value="{{ $ep->id }}">{{ $ep->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="{{ $idPrefix }}SchemeWrap" class="hidden">
                        <label class="block text-xs text-gray-500 mb-1">Scheme *</label>
                        <select name="emi_provider_scheme_id" id="{{ $idPrefix }}SchemeSel" onchange="{{ $fnPrefix }}ApplyScheme()"
                                class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                            <option value="">— Select Scheme —</option>
                        </select>
                    </div>
                    {{-- Provider breakdown card --}}
                    <div id="{{ $idPrefix }}ProviderBreakdown" class="hidden bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-2 text-xs space-y-1">
                        <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wide mb-1">Scheme Breakdown</p>
                        <div class="flex justify-between text-indigo-900">
                            <span>Patient Monthly EMI</span><span id="{{ $idPrefix }}PbMonthly" class="font-bold">—</span>
                        </div>
                        <div id="{{ $idPrefix }}PbUpfrontRow" class="hidden flex justify-between text-amber-700">
                            <span>Upfront today (<span id="{{ $idPrefix }}PbUpfrontCount">0</span> EMI)</span>
                            <span id="{{ $idPrefix }}PbUpfront" class="font-semibold">—</span>
                        </div>
                        <div class="border-t border-indigo-200 pt-1 mt-1 space-y-0.5">
                            <div class="flex justify-between text-gray-500">
                                <span>Clinic interest cost</span><span id="{{ $idPrefix }}PbClinicInterest">—</span>
                            </div>
                            <div class="flex justify-between text-gray-500">
                                <span>GST on interest (18%)</span><span id="{{ $idPrefix }}PbGstInterest">—</span>
                            </div>
                            <div class="flex justify-between text-gray-600 font-medium">
                                <span>Provider deduction</span><span id="{{ $idPrefix }}PbDeduction" class="text-red-500">—</span>
                            </div>
                        </div>
                        <div class="border-t border-indigo-200 pt-1">
                            <div class="flex justify-between text-green-700 font-semibold">
                                <span>Clinic net amount</span><span id="{{ $idPrefix }}PbNet">—</span>
                            </div>
                        </div>
                        <div id="{{ $idPrefix }}PbConvRow" class="hidden border-t border-amber-200 pt-1">
                            <div class="flex justify-between text-amber-700 font-semibold">
                                <span>Convenience charge (patient pays)</span><span id="{{ $idPrefix }}PbConv">—</span>
                            </div>
                            <div class="flex justify-between text-amber-900 font-bold">
                                <span>Receipt total</span><span id="{{ $idPrefix }}PbReceiptTotal">—</span>
                            </div>
                            <input type="hidden" name="convenience_fee" id="{{ $idPrefix }}ProvConvFee" value="0">
                        </div>
                        <input type="hidden" name="emi_upfront_amount" id="{{ $idPrefix }}ProvUpfront" value="0">
                        <p class="text-xs text-indigo-500 mt-1">
                            Receipt #1 (upfront) is generated now for what the patient pays today. Receipt #2 (settlement) is generated when you click "Mark Provider Payment Received".
                        </p>
                    </div>
                </div>
            </div>

            {{-- Notes --}}
            <div>
                <label class="{{ $labelClass }}">Notes</label>
                <textarea name="notes" rows="2"
                          class="{{ $inputClass }}"></textarea>
            </div>

            <div class="flex gap-2 pt-1">
                <button type="submit"
                        class="flex-1 py-2 bg-green-600 hover:bg-green-700 text-white font-semibold text-sm rounded-lg">
                    {{ $submitLabel }}
                </button>
                <button type="button"
                        onclick="{!! $cancelOnclick !!}"
                        class="flex-1 py-2 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">
                    Cancel
                </button>
            </div>
        </form>
