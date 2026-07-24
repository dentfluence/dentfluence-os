{{-- Quick Pay Modal — kept outside all tab x-show divs so it's always accessible from any tab --}}
@php
    $unpaidInvoices      = ($invoices ?? collect())->filter(fn($i) => $i->balance_due > 0 && $i->status !== 'cancelled');
    $activeEmiProvidersQp = $activeEmiProviders ?? collect();
@endphp

<div id="quickPayModal"
     class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm"
     onclick="if(event.target===this)closeQuickPayModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[90vh]">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-base font-bold text-gray-800">Record Payment</h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ $patient->name }}</p>
            </div>
            <button onclick="closeQuickPayModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <div class="overflow-y-auto flex-1 p-5 space-y-4">

            @if($unpaidInvoices->isEmpty())
                <div class="text-center pt-4 pb-1 text-gray-500">
                    <svg class="mx-auto mb-2 text-green-400" xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <p class="font-semibold">No outstanding invoices</p>
                    <p class="text-xs text-gray-400 mt-1">All settled — you can still take an advance into the wallet.</p>
                </div>

                {{-- Receive Advance Payment (no invoice needed → wallet credit) --}}
                <form method="POST" action="{{ route('finance.wallets.receive-advance', $patient) }}"
                      class="space-y-3 border-t border-gray-100 pt-4 mt-2">
                    @csrf
                    <input type="hidden" name="from_patient" value="{{ $patient->id }}">
                    <p class="text-xs font-semibold text-[#6a0f70] uppercase tracking-wider">Receive Advance Payment</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Amount (Rs.) <span class="text-red-500">*</span></label>
                            <input type="number" name="amount" step="0.01" min="1" required
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Mode</label>
                            <select name="payment_mode" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="netbanking">Net Banking</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                        <input type="date" name="payment_date" value="{{ now()->toDateString() }}" required
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                        <input type="text" name="notes" placeholder="Optional"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700">
                        Add Advance to Wallet
                    </button>
                </form>
            @else

            {{-- Step 1: Pick an invoice --}}
            <div id="qpStep1">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Select Invoice to Pay</p>
                <div class="space-y-2" id="qpInvoiceList">
                    @foreach($unpaidInvoices as $uinv)
                    @php
                        $uBadge = match($uinv->status) {
                            'partial' => 'bg-amber-50 text-amber-700 border-amber-200',
                            default   => 'bg-red-50 text-red-600 border-red-200',
                        };
                        $uLabel = $uinv->status === 'partial' ? 'Partial' : 'Unpaid';
                    @endphp
                    <button type="button"
                            onclick="qpSelectInvoice({{ $uinv->id }}, '{{ $uinv->invoice_number }}', {{ $uinv->balance_due }}, '{{ route('billing.payment', $uinv) }}')"
                            class="w-full text-left flex items-center gap-3 px-4 py-3 border border-gray-200 rounded-xl hover:border-green-400 hover:bg-green-50/30 transition group">
                        <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/>
                                <line x1="16" y1="8" x2="8" y2="8"/><line x1="16" y1="12" x2="8" y2="12"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-gray-800 font-mono">{{ $uinv->invoice_number }}</span>
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full border {{ $uBadge }}">{{ $uLabel }}</span>
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $uinv->invoice_date?->format('d M Y') }}</div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-sm font-bold text-red-600">Rs. {{ number_format($uinv->balance_due, 0) }}</div>
                            <div class="text-[10px] text-gray-400">due</div>
                        </div>
                        <svg class="text-gray-300 group-hover:text-green-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Step 2: Payment form (hidden until invoice selected) --}}
            <div id="qpStep2" class="hidden">
                <div class="flex items-center gap-2 mb-3">
                    <button onclick="qpBackToList()" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                    <div>
                        <span class="text-xs font-semibold text-gray-700" id="qpSelectedInvNum"></span>
                        <span class="text-xs text-gray-400 ml-1">— Balance: <span class="text-red-500 font-bold" id="qpBalanceLabel"></span></span>
                    </div>
                </div>

                <form method="POST" id="qpPayForm">
                    @csrf
                    <input type="hidden" name="from_patient" value="{{ $patient->id }}">
                    <input type="hidden" name="emi_type" id="qpEmiType" value="direct">

                    {{-- Amount + Date --}}
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Amount (Rs. ) *</label>
                            <input type="number" name="amount" id="qpAmount" required min="0.01" step="0.01"
                                   oninput="qpOnAmountChange()"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Date *</label>
                            <input type="date" name="payment_date" id="qpDate" required
                                   value="{{ now()->format('Y-m-d') }}"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                    </div>

                    {{-- Mode --}}
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Payment Mode *</label>
                        <div class="grid grid-cols-4 gap-1.5">
                            @foreach(['cash'=>'Cash','upi'=>'UPI','card'=>'Credit Card','cheque'=>'Cheque','netbanking'=>'NetBank','debit_card'=>'Debit Card','bank_transfer'=>'Transfer','emi'=>'EMI'] as $val => $lbl)
                            <label class="flex items-center justify-center text-center px-1 py-2 text-[10px] font-medium border border-gray-200 rounded-lg cursor-pointer hover:border-green-400 hover:bg-green-50 has-[:checked]:border-green-500 has-[:checked]:bg-green-50 has-[:checked]:font-semibold transition">
                                <input type="radio" name="payment_mode" value="{{ $val }}" class="sr-only" onchange="qpOnModeChange()" {{ $val === 'cash' ? 'checked' : '' }}>
                                {{ $lbl }}
                            </label>
                            @endforeach
                        </div>
                        {{-- hidden select for form submission fallback --}}
                        <select name="payment_mode" id="qpModeSelect" class="hidden">
                            @foreach(['cash','upi','card','cheque','netbanking','debit_card','bank_transfer','emi'] as $v)
                            <option value="{{ $v }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Reference --}}
                    <div id="qpFieldRef" class="hidden mb-3">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Reference No. *</label>
                        <input type="text" name="reference_no" placeholder="UTR / Transaction ID"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>

                    {{-- CC Fee --}}
                    <div id="qpFieldCC" class="hidden mb-3">
                        <div id="qpCcFeePanel" class="hidden bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs">
                            <div class="flex justify-between font-semibold text-amber-800">
                                <span>Convenience Fee ({{ rtrim(rtrim(number_format((float) \App\Models\AppSetting::get('cc_convenience_rate', 2.5), 2), '0'), '.') }}%)</span><span id="qpCcFeeAmt">Rs. 0.00</span>
                            </div>
                            <p class="text-amber-600 mt-0.5">On credit-card payments above Rs. {{ number_format((float) \App\Models\AppSetting::get('cc_convenience_threshold', 10000), 0) }}.</p>
                            <input type="hidden" name="convenience_fee" id="qpConvFee" value="0">
                        </div>
                    </div>

                    {{-- Cheque --}}
                    <div id="qpFieldCheque" class="hidden mb-3 space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Bank Name *</label>
                                <input type="text" name="bank_name" placeholder="HDFC Bank"
                                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Cheque No. *</label>
                                <input type="text" name="cheque_no"
                                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Cheque Date *</label>
                            <input type="date" name="cheque_date"
                                   class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm">
                        </div>
                    </div>

                    {{-- EMI (same Direct / Provider form as billing pages) --}}
                    @php
                        $qpActiveEmiProviders = \App\Models\EmiProvider::where('is_active', true)
                            ->with(['schemes' => fn($q) => $q->where('is_active', true)])
                            ->orderBy('name')->get();
                    @endphp
                    <div id="qpFieldEmi" class="hidden mb-3 space-y-3">
                        {{-- Sub-type toggle --}}
                        <div class="flex gap-2">
                            <button type="button" id="qpBtnDirect" onclick="qpSwitchEmi('direct')"
                                    class="flex-1 py-2 text-xs font-semibold rounded-lg border border-purple-600 bg-purple-600 text-white">
                                Direct EMI<br>
                                <span class="font-normal opacity-80">Clinic collects instalments</span>
                            </button>
                            <button type="button" id="qpBtnProvider" onclick="qpSwitchEmi('provider')"
                                    class="flex-1 py-2 text-xs font-semibold rounded-lg border border-purple-200 bg-white text-purple-700 {{ $qpActiveEmiProviders->isEmpty() ? 'opacity-40 cursor-not-allowed' : '' }}"
                                    {{ $qpActiveEmiProviders->isEmpty() ? 'disabled title="No EMI providers configured in Settings"' : '' }}>
                                Provider EMI<br>
                                <span class="font-normal opacity-80">Provider pays clinic upfront</span>
                            </button>
                        </div>

                        {{-- Direct EMI fields --}}
                        <div id="qpDirectFields" class="space-y-2">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Financer / Bank (optional)</label>
                                <input type="text" name="emi_provider" placeholder="e.g. HDFC Card EMI, SBI EMI..."
                                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-purple-400">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Tenure *</label>
                                    <select name="emi_tenure" id="qpEmiTenure" onchange="qpCalcEmi()"
                                            class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                                        <option value="">Select…</option>
                                        @foreach([3,6,9,12,18,24,36,48,60] as $m)
                                        <option value="{{ $m }}">{{ $m }} months</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Interest % p.a.</label>
                                    <input type="number" name="emi_interest_rate" id="qpEmiRate"
                                           value="0" min="0" max="36" step="0.01" oninput="qpCalcEmi()"
                                           class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">First Auto-Debit Date *</label>
                                <input type="date" name="emi_start_date" id="qpEmiStart" onchange="qpCalcEmi()"
                                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                            </div>
                            <div id="qpEmiResult" class="hidden bg-purple-50 border border-purple-200 rounded-lg px-3 py-2 text-xs">
                                <div class="flex justify-between font-semibold text-purple-800">
                                    <span>Monthly EMI</span><span id="qpEmiMonthly">—</span>
                                </div>
                                <div class="flex justify-between text-purple-600 mt-0.5">
                                    <span>Total Payable</span><span id="qpEmiTotal">—</span>
                                </div>
                            </div>
                        </div>

                        {{-- Provider EMI fields --}}
                        <div id="qpProviderFields" class="hidden space-y-2">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">EMI Provider *</label>
                                <select id="qpProviderSel" onchange="qpLoadSchemes()"
                                        class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                                    <option value="">— Select Provider —</option>
                                    @foreach($qpActiveEmiProviders as $ep)
                                    <option value="{{ $ep->id }}">{{ $ep->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div id="qpSchemeWrap" class="hidden">
                                <label class="block text-xs text-gray-500 mb-1">Scheme *</label>
                                <select name="emi_provider_scheme_id" id="qpSchemeSel" onchange="qpApplyScheme()"
                                        class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                                    <option value="">— Select Scheme —</option>
                                </select>
                            </div>
                            {{-- Provider breakdown card --}}
                            <div id="qpProviderBreakdown" class="hidden bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-2 text-xs space-y-1">
                                <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wide mb-1">Scheme Breakdown</p>
                                <div class="flex justify-between text-indigo-900">
                                    <span>Patient Monthly EMI</span><span id="qpPbMonthly" class="font-bold">—</span>
                                </div>
                                <div id="qpPbUpfrontRow" class="hidden flex justify-between text-amber-700">
                                    <span>Upfront today (<span id="qpPbUpfrontCount">0</span> EMI)</span>
                                    <span id="qpPbUpfront" class="font-semibold">—</span>
                                </div>
                                <div class="border-t border-indigo-200 pt-1 mt-1 space-y-0.5">
                                    <div class="flex justify-between text-gray-500">
                                        <span>Clinic interest cost</span><span id="qpPbClinicInterest">—</span>
                                    </div>
                                    <div class="flex justify-between text-gray-500">
                                        <span>GST on interest (18%)</span><span id="qpPbGstInterest">—</span>
                                    </div>
                                    <div class="flex justify-between text-gray-600 font-medium">
                                        <span>Provider deduction</span><span id="qpPbDeduction" class="text-red-500">—</span>
                                    </div>
                                </div>
                                <div class="border-t border-indigo-200 pt-1">
                                    <div class="flex justify-between text-green-700 font-semibold">
                                        <span>Clinic net amount</span><span id="qpPbNet">—</span>
                                    </div>
                                </div>
                                <div id="qpPbConvRow" class="hidden border-t border-amber-200 pt-1">
                                    <div class="flex justify-between text-amber-700 font-semibold">
                                        <span>Convenience charge (patient pays)</span><span id="qpPbConv">—</span>
                                    </div>
                                    <div class="flex justify-between text-amber-900 font-bold">
                                        <span>Receipt total</span><span id="qpPbReceiptTotal">—</span>
                                    </div>
                                    <input type="hidden" name="convenience_fee" id="qpProvConvFee" value="0" disabled>
                                </div>
                                <input type="hidden" name="emi_upfront_amount" id="qpProvUpfront" value="0">
                                <p class="text-xs text-indigo-500 mt-1">
                                    Receipt #1 (upfront) is generated now for what the patient pays today. Receipt #2 (settlement) is generated when you click "Mark Provider Payment Received".
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Notes</label>
                        <textarea name="notes" rows="2"
                                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"></textarea>
                    </div>

                    <button type="submit"
                            class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-semibold text-sm rounded-xl transition">
                        Save Payment
                    </button>
                </form>
            </div>

            @endif
        </div>
    </div>
</div>

<script>
(function() {
    // Configurable in Settings → Billing → Credit Card Convenience Fee
    const CC_LIMIT   = {{ (float) \App\Models\AppSetting::get('cc_convenience_threshold', 10000) }};
    const CC_RATE    = {{ (float) \App\Models\AppSetting::get('cc_convenience_rate', 2.5) / 100 }};
    let qpBalance = 0;

    window.openQuickPayModal = function() {
        document.getElementById('quickPayModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        // if only one unpaid invoice, auto-select it
        const btns = document.querySelectorAll('#qpInvoiceList button');
        if (btns.length === 1) btns[0].click();
    };

    window.closeQuickPayModal = function() {
        document.getElementById('quickPayModal').classList.add('hidden');
        document.body.style.overflow = '';
        qpBackToList();
    };

    window.qpSelectInvoice = function(id, num, balance, actionUrl) {
        qpBalance = balance;
        document.getElementById('qpPayForm').action = actionUrl;
        document.getElementById('qpAmount').value = balance;
        document.getElementById('qpSelectedInvNum').textContent = num;
        document.getElementById('qpBalanceLabel').textContent = 'Rs. ' + balance.toLocaleString('en-IN');
        document.getElementById('qpStep1').classList.add('hidden');
        document.getElementById('qpStep2').classList.remove('hidden');
        // sync radio with hidden select
        document.querySelector('input[name="payment_mode"][value="cash"]').checked = true;
        // reset EMI provider state (scheme breakdown depends on the selected invoice)
        const provSel = document.getElementById('qpProviderSel');
        if (provSel) {
            provSel.value = '';
            document.getElementById('qpSchemeSel').innerHTML = '<option value="">— Select Scheme —</option>';
            document.getElementById('qpSchemeWrap').classList.add('hidden');
            document.getElementById('qpProviderBreakdown').classList.add('hidden');
            document.getElementById('qpEmiType').value = 'direct';
        }
        qpOnModeChange();
    };

    window.qpBackToList = function() {
        const s1 = document.getElementById('qpStep1');
        const s2 = document.getElementById('qpStep2');
        if (s1 && s2) { s1.classList.remove('hidden'); s2.classList.add('hidden'); }
    };

    window.qpOnModeChange = function() {
        const checked = document.querySelector('input[name="payment_mode"]:checked');
        const mode = checked ? checked.value : 'cash';
        // sync hidden select
        const sel = document.getElementById('qpModeSelect');
        if (sel) sel.value = mode;
        const ph = id => { const e = document.getElementById(id); if(e) e.classList.add('hidden'); };
        const ps = id => { const e = document.getElementById(id); if(e) e.classList.remove('hidden'); };
        ph('qpFieldRef'); ph('qpFieldCC'); ph('qpFieldCheque'); ph('qpFieldEmi');
        if (['upi','netbanking','bank_transfer'].includes(mode)) ps('qpFieldRef');
        if (mode === 'card')   { ps('qpFieldCC'); qpOnAmountChange(); }
        if (mode === 'cheque') ps('qpFieldCheque');
        if (mode === 'emi')    { ps('qpFieldEmi'); qpSwitchEmi('direct'); }
        else {
            // EMI hidden → make sure provider conv-fee input can't override the CC fee input
            const pcf = document.getElementById('qpProvConvFee');
            if (pcf) { pcf.disabled = true; pcf.value = 0; }
        }
    };

    // ── EMI sub-type toggle (Direct vs Provider) — same as billing pages ──
    window.qpSwitchEmi = function(type) {
        document.getElementById('qpEmiType').value = type;
        const onActive = ['border-purple-600','bg-purple-600','text-white'];
        const onIdle   = ['border-purple-200','bg-white','text-purple-700'];
        const d = document.getElementById('qpBtnDirect');
        const p = document.getElementById('qpBtnProvider');
        const ph = id => { const e=document.getElementById(id); if(e) e.classList.add('hidden'); };
        const ps = id => { const e=document.getElementById(id); if(e) e.classList.remove('hidden'); };
        const pcf = document.getElementById('qpProvConvFee');
        if (type === 'direct') {
            onActive.forEach(c=>d.classList.add(c));   onIdle.forEach(c=>d.classList.remove(c));
            onIdle.forEach(c=>p.classList.add(c));     onActive.forEach(c=>p.classList.remove(c));
            ps('qpDirectFields'); ph('qpProviderFields');
            if (pcf) { pcf.disabled = true; pcf.value = 0; }
        } else {
            onActive.forEach(c=>p.classList.add(c));   onIdle.forEach(c=>p.classList.remove(c));
            onIdle.forEach(c=>d.classList.add(c));     onActive.forEach(c=>d.classList.remove(c));
            ph('qpDirectFields'); ps('qpProviderFields');
            if (pcf) pcf.disabled = false;
        }
    };

    // ── Provider EMI: load schemes via AJAX (same endpoint as billing pages) ──
    let _qpSchemes = [];
    window.qpLoadSchemes = function() {
        const pid = document.getElementById('qpProviderSel').value;
        const ph = id => { const e=document.getElementById(id); if(e) e.classList.add('hidden'); };
        const ps = id => { const e=document.getElementById(id); if(e) e.classList.remove('hidden'); };
        ph('qpSchemeWrap'); ph('qpProviderBreakdown'); _qpSchemes = [];
        if (!pid) return;
        const url = '{{ route("settings.emi.schemes.ajax") }}?provider_id=' + pid + '&invoice_total=' + qpBalance;
        fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r => r.json())
            .then(data => {
                _qpSchemes = data;
                const sel = document.getElementById('qpSchemeSel');
                sel.innerHTML = '<option value="">— Select Scheme —</option>';
                data.forEach(s => {
                    const o = document.createElement('option');
                    o.value = s.id;
                    o.textContent = s.scheme_name + ' · ' + s.tenure_months + 'M';
                    sel.appendChild(o);
                });
                ps('qpSchemeWrap');
            });
    };

    window.qpApplyScheme = function() {
        const sid = document.getElementById('qpSchemeSel').value;
        const ph = id => { const e=document.getElementById(id); if(e) e.classList.add('hidden'); };
        const ps = id => { const e=document.getElementById(id); if(e) e.classList.remove('hidden'); };
        ph('qpProviderBreakdown');
        if (!sid) return;
        const s = _qpSchemes.find(x => String(x.id) === String(sid));
        if (!s) return;
        const fmt = v => 'Rs. ' + parseFloat(v).toFixed(2);
        document.getElementById('qpPbMonthly').textContent = fmt(s.patient_monthly_emi);
        if (s.upfront_emis > 0) {
            document.getElementById('qpPbUpfrontCount').textContent = s.upfront_emis;
            document.getElementById('qpPbUpfront').textContent = fmt(s.patient_upfront_amount);
            ps('qpPbUpfrontRow');
        } else { ph('qpPbUpfrontRow'); }
        document.getElementById('qpPbClinicInterest').textContent = fmt(s.clinic_interest_cost ?? 0);
        document.getElementById('qpPbGstInterest').textContent    = fmt(s.gst_on_interest ?? 0);
        document.getElementById('qpPbDeduction').textContent      = fmt(s.provider_deduction ?? 0);
        document.getElementById('qpPbNet').textContent            = fmt(s.clinic_net_amount);
        if (s.pass_cost_to_patient && s.convenience_charge > 0) {
            document.getElementById('qpPbConv').textContent         = fmt(s.convenience_charge);
            document.getElementById('qpPbReceiptTotal').textContent = fmt((parseFloat(s.patient_upfront_amount)||0) + parseFloat(s.convenience_charge));
            document.getElementById('qpProvConvFee').value          = s.convenience_charge;
            ps('qpPbConvRow');
        } else {
            document.getElementById('qpProvConvFee').value = 0;
            ph('qpPbConvRow');
        }
        document.getElementById('qpProvUpfront').value = s.patient_upfront_amount || 0;
        ps('qpProviderBreakdown');
    };

    window.qpOnAmountChange = function() {
        const checked = document.querySelector('input[name="payment_mode"]:checked');
        if (!checked || checked.value !== 'card') return;
        const amt  = parseFloat(document.getElementById('qpAmount').value) || 0;
        const ph = id => { const e=document.getElementById(id); if(e) e.classList.add('hidden'); };
        const ps = id => { const e=document.getElementById(id); if(e) e.classList.remove('hidden'); };
        if (amt > CC_LIMIT) {
            const fee = Math.round(amt * CC_RATE * 100) / 100;
            document.getElementById('qpCcFeeAmt').textContent = 'Rs. ' + fee.toFixed(2);
            document.getElementById('qpConvFee').value = fee;
            ps('qpCcFeePanel');
        } else {
            ph('qpCcFeePanel');
            document.getElementById('qpConvFee').value = 0;
        }
    };

    window.qpCalcEmi = function() {
        const P = parseFloat(document.getElementById('qpAmount').value) || 0;
        const n = parseInt(document.getElementById('qpEmiTenure').value) || 0;
        const r = parseFloat(document.getElementById('qpEmiRate').value) || 0;
        const s = document.getElementById('qpEmiStart').value;
        const res = document.getElementById('qpEmiResult');
        if (!P || !n || !s) { res.classList.add('hidden'); return; }
        let emi = r <= 0
            ? Math.round(P / n * 100) / 100
            : (() => { const mr=r/100/12; const f=Math.pow(1+mr,n); return Math.round(P*mr*f/(f-1)*100)/100; })();
        document.getElementById('qpEmiMonthly').textContent = 'Rs. ' + emi.toFixed(2);
        document.getElementById('qpEmiTotal').textContent   = 'Rs. ' + (emi * n).toFixed(2);
        res.classList.remove('hidden');
    };

    // Make radio clicks trigger mode change
    document.querySelectorAll('input[name="payment_mode"]').forEach(r => {
        r.addEventListener('change', qpOnModeChange);
    });
})();
</script>
