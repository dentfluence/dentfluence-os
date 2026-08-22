{{-- Quick Pay Modal — kept outside all tab x-show divs so it's always accessible from any tab --}}
@php
    $unpaidInvoices      = ($invoices ?? collect())->filter(fn($i) => $i->balance_due > 0 && $i->status !== 'cancelled');
    $activeEmiProvidersQp = $activeEmiProviders ?? collect();
@endphp

<div id="quickPayModal"
     {{-- z must clear the topbar (120) and mobile drawer (130) from layouts/app.blade.php;
          at z-[60] the topbar painted OVER this modal and sliced its header. --}}
     class="hidden fixed inset-0 z-[140] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4"
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
                                @foreach (\App\Enums\PaymentMode::options(['wallet', 'emi']) as $pm)
                                    <option value="{{ $pm['value'] }}">{{ $pm['label'] }}</option>
                                @endforeach
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

            {{-- ── Pay total outstanding (auto-allocated, oldest first) ────────
                 One tender, allocated across every open invoice by the server.
                 Staff does not pick an invoice. Surplus becomes Patient Credit.
                 Simple tenders only — EMI and card-convenience-fee payments must
                 still go through a single invoice below. --}}
            <div id="qpAutoPayWrap">
            <div id="qpAutoPay" class="border border-green-200 bg-green-50/40 rounded-xl p-4">
                <div class="flex items-baseline justify-between mb-3">
                    <p class="text-xs font-semibold text-green-800 uppercase tracking-wider">Pay Total Outstanding</p>
                    <span class="text-[10px] text-green-700">auto-allocated, oldest invoice first</span>
                </div>

                <div class="flex items-baseline justify-between mb-3 px-1">
                    <span class="text-xs text-gray-500">Total Outstanding</span>
                    <span class="text-lg font-bold text-red-600">
                        Rs. {{ number_format($unpaidInvoices->sum(fn($i) => (float) $i->balance_due), 2) }}
                    </span>
                </div>

                <form method="POST" action="{{ route('billing.patientPayment', $patient) }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Payment Amount</label>
                            <input type="number" name="amount" step="0.01" min="0.01" required
                                   value="{{ number_format($unpaidInvoices->sum(fn($i) => (float) $i->balance_due), 2, '.', '') }}"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Payment Mode</label>
                            <select name="payment_mode" id="fifoMode" required onchange="fifoOnModeChange()"
                                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                                {{-- A3 — driven by the allocator's own ALLOWED_MODES so this
                                     picker can never offer a mode FIFO will reject. Narrower
                                     than the canonical set by design. --}}
                                @foreach (\App\Services\Billing\PatientPaymentAllocationService::ALLOWED_MODES as $pmv)
                                    <option value="{{ $pmv }}">{{ \App\Enums\PaymentMode::labelFor($pmv) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                            <input type="date" name="payment_date" value="{{ now()->toDateString() }}" required
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Reference No.</label>
                            <input type="text" name="reference_no" placeholder="Optional"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                        </div>
                    </div>
                    {{-- Cheque details. The allocator already persists bank_name /
                         cheque_no / cheque_date / cheque_status and recordPatientPayment
                         already validates them — this form simply never rendered them. --}}
                    <div id="fifoCheque" class="hidden grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Bank Name</label>
                            <input type="text" name="bank_name" placeholder="HDFC Bank" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Cheque No.</label>
                            <input type="text" name="cheque_no" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Cheque Date</label>
                            <input type="date" name="cheque_date" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                        </div>
                    </div>

                    {{-- A3.x — Credit Card and EMI are not offered here at all. Say why,
                         permanently, so staff are not left hunting for a missing option.
                         The list itself comes from ALLOWED_MODES, so it cannot drift. --}}
                    <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-[11px] text-amber-800">
                        Credit Card payments are available when paying a single invoice.
                        For multiple outstanding invoices, please pay invoices individually.
                    </div>

                    <p class="text-[11px] text-gray-500 leading-relaxed">
                        Pays the oldest outstanding invoice first. Anything left over after every
                        invoice is settled is held as Patient Credit — it is not counted as income.
                    </p>
                    <button type="submit" class="w-full py-2.5 bg-green-600 text-white font-semibold text-sm rounded-lg hover:bg-green-700">
                        Receive Payment &amp; Allocate
                    </button>
                </form>
            </div>

            <div class="flex items-center gap-3 py-1">
                <div class="flex-1 h-px bg-gray-200"></div>
                <span class="text-[10px] uppercase tracking-wider text-gray-400">or pay one invoice</span>
                <div class="flex-1 h-px bg-gray-200"></div>
            </div>
            </div>

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

                {{-- Same per-invoice Record Payment form as the invoice screen. The action
                     is set by qpSelectInvoice() when a specific invoice is chosen. --}}
                @include('billing.partials.record-payment-form', [
                    'invoice'       => $unpaidInvoices->first(),
                    'idPrefix'      => 'qp',
                    'fnPrefix'      => 'qp',
                    'formId'        => 'qpPayForm',
                    'action'        => '',
                    'fromPatient'   => $patient->id,
                    // this page defines the collection under its own name
                    'activeEmiProviders' => $activeEmiProvidersQp,
                    'submitLabel'   => 'Save Payment',
                    'cancelOnclick' => 'qpBackToList()',
                ])
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
        // Single-invoice mode owns the modal: hide the auto-allocate block so the
        // two payment forms are never on screen at the same time.
        const apw = document.getElementById('qpAutoPayWrap');
        if (apw) apw.classList.add('hidden');
        // Reset the mode picker for the newly chosen invoice. This used to poke a
        // radio input; the shared partial renders a <select>, and the old line
        // threw on null the moment an invoice was selected.
        const modeSel = document.getElementById('qpMode');
        if (modeSel) modeSel.value = 'cash';
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
        const apw = document.getElementById('qpAutoPayWrap');
        if (apw) apw.classList.remove('hidden');
    };

    window.qpOnModeChange = function() {
        // The mode control is the shared partial's <select>, not the old radio grid.
        const sel  = document.getElementById('qpMode');
        const mode = sel ? sel.value : 'cash';
        const ph = id => { const e = document.getElementById(id); if(e) e.classList.add('hidden'); };
        const ps = id => { const e = document.getElementById(id); if(e) e.classList.remove('hidden'); };
        ph('qpFieldRef'); ph('qpFieldCC'); ph('qpFieldCheque'); ph('qpFieldEmi');
        if (['upi','bank_transfer'].includes(mode)) ps('qpFieldRef');
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
        const sel = document.getElementById('qpMode');
        if (!sel || sel.value !== 'card') return;
        const amt = parseFloat(document.getElementById('qpAmount').value) || 0;
        const ph = id => { const e=document.getElementById(id); if(e) e.classList.add('hidden'); };
        const ps = id => { const e=document.getElementById(id); if(e) e.classList.remove('hidden'); };
        if (amt > CC_LIMIT) {
            const fee = Math.round(amt * CC_RATE * 100) / 100;
            document.getElementById('qpCcFeeAmt').textContent = 'Rs. ' + fee.toFixed(2);
            document.getElementById('qpConvFee').value = fee;
            ps('qpCcFeePanel');
            document.getElementById('qpCcTotalAmt').textContent = 'Rs. ' + (amt + fee).toFixed(2);
            ps('qpCcTotal');
        } else {
            ph('qpCcFeePanel');
            ph('qpCcTotal');
            document.getElementById('qpConvFee').value = 0;
        }
    };

    window.qpCalcEmi = function() {
        const P = parseFloat(document.getElementById('qpAmount').value) || 0;
        const n = parseInt(document.getElementById('qpEmiTenure').value) || 0;
        const r = parseFloat(document.getElementById('qpEmiRate').value) || 0;
        const s = document.getElementById('qpEmiStart').value;
        const res  = document.getElementById('qpEmiResult');
        const wrap = document.getElementById('qpEmiScheduleWrap');
        if (!P || !n || !s) { res.classList.add('hidden'); if (wrap) wrap.classList.add('hidden'); return; }

        const mr  = r > 0 ? r / 100 / 12 : 0;
        let   emi;
        if (mr <= 0) {
            emi = Math.round(P / n * 100) / 100;
        } else {
            const f = Math.pow(1 + mr, n);
            emi = Math.round(P * mr * f / (f - 1) * 100) / 100;
        }
        const totalPayable  = emi * n;
        const totalInterest = totalPayable - P;

        document.getElementById('qpEmiMonthly').textContent = 'Rs. ' + emi.toFixed(2);
        document.getElementById('qpEmiTotal').textContent   = 'Rs. ' + totalPayable.toFixed(2);
        // Interest and the schedule below were never populated here. The shared
        // partial renders both rows (the invoice screen has always had them), so
        // without this they would sit permanently on "—".
        const int = document.getElementById('qpEmiInterest');
        if (int) int.textContent = 'Rs. ' + totalInterest.toFixed(2);
        res.classList.remove('hidden');

        const tbody = document.getElementById('qpEmiScheduleBody');
        if (!tbody || !wrap) return;
        tbody.innerHTML = '';
        let balance = P;
        const startDate = new Date(s);
        for (let i = 1; i <= n; i++) {
            const dueDate = new Date(startDate);
            dueDate.setMonth(dueDate.getMonth() + (i - 1));
            const interestPart  = Math.round(balance * mr * 100) / 100;
            const principalPart = Math.round((emi - interestPart) * 100) / 100;
            balance = Math.round((balance - principalPart) * 100) / 100;
            const tr = document.createElement('tr');
            tr.innerHTML = `<td class="px-2 py-1 text-gray-500">${i}</td>
                <td class="px-2 py-1 text-gray-700">${dueDate.toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'})}</td>
                <td class="px-2 py-1 text-right text-gray-700">Rs. ${principalPart.toFixed(2)}</td>
                <td class="px-2 py-1 text-right text-gray-500">Rs. ${interestPart.toFixed(2)}</td>
                <td class="px-2 py-1 text-right font-medium text-purple-700">Rs. ${emi.toFixed(2)}</td>`;
            tbody.appendChild(tr);
        }
        wrap.classList.remove('hidden');
    };

    // The partial's schedule toggle calls this. It did not exist before the
    // shared form was introduced, so the button would have thrown on click.
    window.qpToggleEmiSchedule = function() {
        const t = document.getElementById('qpEmiScheduleTable');
        const b = document.getElementById('qpEmiToggleBtn');
        if (!t || !b) return;
        t.classList.toggle('hidden');
        b.textContent = t.classList.contains('hidden') ? 'Show' : 'Hide';
    };

    // ── FIFO "Pay Total Outstanding" block ──────────────────────────────────
    // Its own small handler: this form is a different operation from the
    // per-invoice one (one tender across many invoices), so it deliberately has
    // no EMI and no convenience fee — see PatientPaymentAllocationService.
    window.fifoOnModeChange = function() {
        const sel  = document.getElementById('fifoMode');
        if (!sel) return;
        const mode = sel.value;
        const tog  = (id, on) => {
            const e = document.getElementById(id);
            if (e) e.classList.toggle('hidden', !on);
        };
        tog('fifoCheque', mode === 'cheque');
    };
    fifoOnModeChange();

    // The shared partial's <select id="qpMode"> carries onchange="qpOnModeChange()"
    // inline, so no extra wiring is needed here.
})();
</script>
