{{-- resources/views/billing/_invoice_panel.blade.php
     Self-contained partial — no @extends. Loaded via fetch() into the patient profile drawer.
--}}
@php
    $statusColors = [
        'paid'      => 'bg-green-100 text-green-700',
        'partial'   => 'bg-yellow-100 text-yellow-700',
        'draft'     => 'bg-gray-100 text-gray-600',
        'sent'      => 'bg-blue-100 text-blue-700',
        'cancelled' => 'bg-red-100 text-red-600',
    ];
    $statusLabels = ['draft' => 'Unpaid', 'paid' => 'Paid', 'partial' => 'Partially Paid',
                     'sent' => 'Sent', 'cancelled' => 'Cancelled'];
    $cls         = $statusColors[$invoice->status] ?? 'bg-gray-100 text-gray-600';
    $statusLabel = $statusLabels[$invoice->status] ?? ucfirst($invoice->status);
    $canPay      = $invoice->balance_due > 0 && $invoice->status !== 'cancelled';
    $fromPatient = $invoice->patient_id;
@endphp

{{-- ── Invoice header ────────────────────────────────────────────── --}}
<div class="flex items-start justify-between mb-4">
    <div>
        <div class="flex items-center gap-2 mb-1">
            <span class="font-bold text-gray-800 text-base font-mono">{{ $invoice->invoice_number ?? '—' }}</span>
            <span class="px-2 py-0.5 rounded-full text-xs font-semibold {{ $cls }}">{{ $statusLabel }}</span>
        </div>
        <p class="text-xs text-gray-400">{{ $invoice->invoice_date?->format('d M Y') }}</p>
    </div>
    <div class="text-right">
        <p class="text-xs text-gray-400">Patient</p>
        <p class="font-semibold text-sm text-gray-800">{{ $invoice->patient->name }}</p>
        <p class="text-xs text-gray-500">{{ $invoice->patient->phone }}</p>
    </div>
</div>

{{-- ── Line items ─────────────────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-100 overflow-hidden mb-4">
    <table class="w-full text-xs">
        <thead class="bg-gray-50">
            <tr>
                <th class="text-left px-3 py-2 text-gray-500 font-medium">Item</th>
                <th class="text-center px-2 py-2 text-gray-500 font-medium">Qty</th>
                <th class="text-right px-3 py-2 text-gray-500 font-medium">Total</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-50 bg-white">
            @foreach($invoice->items as $item)
            <tr>
                <td class="px-3 py-2 text-gray-700">
                    {{ $item->description }}
                    @if($item->tooth_number)
                        <span class="text-gray-400 ml-1">({{ $item->tooth_number }})</span>
                    @endif
                </td>
                <td class="px-2 py-2 text-center text-gray-500">{{ $item->qty }}</td>
                <td class="px-3 py-2 text-right font-medium text-gray-800">Rs. {{ number_format($item->total, 0) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{-- Totals --}}
    <div class="border-t border-gray-100 px-3 py-2 space-y-1 bg-gray-50/50 text-xs">
        <div class="flex justify-between text-gray-500">
            <span>Subtotal</span><span>Rs. {{ number_format($invoice->subtotal, 2) }}</span>
        </div>
        @if(($invoice->discount_amount ?? 0) > 0)
        <div class="flex justify-between text-gray-400">
            <span>Discount ({{ $invoice->discount_pct }}%)</span>
            <span>−Rs. {{ number_format($invoice->discount_amount, 2) }}</span>
        </div>
        @endif
        @if(($invoice->membership_discount ?? 0) > 0)
        <div class="flex justify-between text-purple-600">
            <span>AOCP Membership</span><span>−Rs. {{ number_format($invoice->membership_discount, 2) }}</span>
        </div>
        @endif
        @if(($invoice->coupon_discount ?? 0) > 0)
        <div class="flex justify-between text-blue-600">
            <span>Coupon</span><span>−Rs. {{ number_format($invoice->coupon_discount, 2) }}</span>
        </div>
        @endif
        @if(($invoice->wallet_applied ?? 0) > 0)
        <div class="flex justify-between text-[#6a0f70]">
            <span>Wallet Credit</span><span>−Rs. {{ number_format($invoice->wallet_applied, 2) }}</span>
        </div>
        @endif
        <div class="flex justify-between font-bold text-gray-800 border-t border-gray-200 pt-1 text-sm">
            <span>Total</span><span>Rs. {{ number_format($invoice->total_amount, 2) }}</span>
        </div>
    </div>
</div>

{{-- ── Payment summary bar ─────────────────────────────────────────── --}}
<div class="grid grid-cols-3 gap-2 mb-4 text-center">
    <div class="bg-gray-50 rounded-lg py-2 px-1">
        <p class="text-xs text-gray-400">Invoice</p>
        <p class="text-sm font-bold text-gray-700">Rs. {{ number_format($invoice->total_amount, 0) }}</p>
    </div>
    <div class="bg-green-50 rounded-lg py-2 px-1">
        <p class="text-xs text-green-500">Paid</p>
        <p class="text-sm font-bold text-green-700">Rs. {{ number_format($invoice->paid_amount, 0) }}</p>
    </div>
    <div class="bg-{{ $invoice->balance_due > 0 ? 'red' : 'green' }}-50 rounded-lg py-2 px-1">
        <p class="text-xs text-{{ $invoice->balance_due > 0 ? 'red' : 'green' }}-400">Balance</p>
        <p class="text-sm font-bold text-{{ $invoice->balance_due > 0 ? 'red' : 'green' }}-700">Rs. {{ number_format($invoice->balance_due, 0) }}</p>
    </div>
</div>

{{-- ── Payment history ─────────────────────────────────────────────── --}}
@if($invoice->payments->count())
<div class="rounded-xl border border-gray-100 overflow-hidden mb-4">
    <div class="px-3 py-2 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
        <span class="text-xs font-semibold text-gray-600">Payment History</span>
        <span class="text-xs text-gray-400">{{ $invoice->payments->count() }} payment(s)</span>
    </div>
    @foreach($invoice->payments as $pmt)
    @php
        $pmtRcpts    = $invoice->receipts->where('invoice_payment_id', $pmt->id);
        $isProvEmi   = $pmt->emi_type === 'provider';
        $upRcpt      = $isProvEmi ? $pmtRcpts->firstWhere('receipt_type', 'patient_upfront') : null;
        $stlRcpt     = $isProvEmi ? $pmtRcpts->firstWhere('receipt_type', 'provider_settlement') : null;
        $stdRcpt     = !$isProvEmi ? $pmtRcpts->first() : null;
    @endphp
    <div class="px-3 py-2 border-b border-gray-50 last:border-0 text-xs">
        <div class="flex items-center">
            <div class="flex-1">
                <span class="font-medium text-gray-700">{{ $pmt->payment_date->format('d M Y') }}</span>
                <a href="{{ route('billing.show', $invoice) }}?from_patient={{ $fromPatient }}#payments" target="_blank"
                   title="Edit payment date on the full invoice page"
                   class="text-gray-300 hover:text-[#6a0f70] inline-block align-middle ml-0.5">
                    <svg class="w-3 h-3 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </a>
                <span class="text-gray-400 ml-2 capitalize">{{ $pmt->payment_mode }}</span>
                @if($isProvEmi)
                    <span class="ml-1 bg-indigo-100 text-indigo-700 px-1 py-0.5 rounded text-xs font-semibold">Provider</span>
                @endif
                @if($pmt->reference_no)
                    <span class="text-gray-400 ml-1 font-mono">· {{ $pmt->reference_no }}</span>
                @endif
            </div>
            <span class="font-bold text-green-600 mr-3">Rs. {{ number_format($pmt->amount, 0) }}</span>
            @if($isProvEmi)
                @if($upRcpt)
                <a href="{{ route('billing.receipt', [$invoice, $upRcpt]) }}" target="_blank"
                   class="text-blue-500 hover:underline font-mono">{{ $upRcpt->receipt_number }}</a>
                @endif
            @elseif($stdRcpt)
                <a href="{{ route('billing.receipt', [$invoice, $stdRcpt]) }}" target="_blank"
                   class="text-blue-500 hover:underline font-mono">{{ $stdRcpt->receipt_number }}</a>
            @endif
        </div>

        {{-- Provider EMI: settlement status --}}
        @if($isProvEmi)
        <div class="mt-1.5 ml-0">
            @if($stlRcpt)
            <div class="flex items-center gap-1.5">
                <span class="text-green-600 font-semibold">✓ Provider paid</span>
                <a href="{{ route('billing.receipt', [$invoice, $stlRcpt]) }}" target="_blank"
                   class="text-green-500 hover:underline font-mono">{{ $stlRcpt->receipt_number }}</a>
                <span class="text-gray-400">(Rs. {{ number_format($pmt->clinic_net_amount, 0) }})</span>
            </div>
            @else
            <div class="flex items-center gap-2">
                <span class="text-amber-600">Awaiting provider payment (Rs. {{ number_format($pmt->clinic_net_amount, 0) }})</span>
                <button onclick="document.getElementById('panelProvPaidForm_{{ $pmt->id }}').classList.toggle('hidden')"
                        class="text-xs bg-amber-100 text-amber-700 hover:bg-amber-200 px-2 py-0.5 rounded font-medium">
                    Mark Received
                </button>
            </div>
            <div id="panelProvPaidForm_{{ $pmt->id }}" class="hidden mt-2">
                <form method="POST" action="{{ route('billing.markProviderPaid', [$invoice, $pmt]) }}" class="flex gap-2 flex-wrap">
                    @csrf
                    <input type="date" name="provider_paid_date" required
                           value="{{ now()->format('Y-m-d') }}"
                           class="border border-gray-200 rounded px-2 py-1 text-xs">
                    <input type="text" name="provider_reference" placeholder="Ref #"
                           class="border border-gray-200 rounded px-2 py-1 text-xs w-28">
                    <button type="submit"
                            class="bg-green-600 text-white text-xs px-3 py-1 rounded hover:bg-green-700">
                        Confirm
                    </button>
                </form>
            </div>
            @endif
        </div>
        @endif
    </div>
    @endforeach
</div>
@endif

{{-- ── Final Bill link ─────────────────────────────────────────────── --}}
@if($invoice->finalBill)
<div class="flex items-center justify-between bg-green-50 border border-green-200 rounded-xl px-4 py-2.5 mb-4 text-sm">
    <div>
        <span class="font-semibold text-green-800">Final Bill</span>
        <span class="text-green-600 ml-2 font-mono text-xs">{{ $invoice->finalBill->bill_number }}</span>
    </div>
    <a href="{{ route('billing.finalBill', $invoice) }}" target="_blank"
       class="text-green-700 text-xs font-semibold hover:underline">View ↗</a>
</div>
@endif

{{-- ── Record Payment section ─────────────────────────────────────── --}}
@if($canPay)
<div id="panelPaySection">
    <button onclick="document.getElementById('panelPayForm').classList.toggle('hidden');this.classList.toggle('hidden')"
            class="w-full py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold text-sm rounded-xl mb-4">
        + Record Payment
    </button>
    <div id="panelPayForm" class="hidden border border-gray-200 rounded-xl p-4 space-y-3 mb-4">
        <p class="text-xs font-semibold text-gray-600 mb-2">Record Payment — Balance: <span class="text-red-500 font-bold">Rs. {{ number_format($invoice->balance_due, 2) }}</span></p>

        {{-- A3 follow-up stage 1: the per-invoice payment form now lives in ONE
             partial. Ids and handler names are unchanged (prefix 'p'), so the
             panel's existing JavaScript binds to exactly the same elements. --}}
        @include('billing.partials.record-payment-form', [
            'invoice'     => $invoice,
            'idPrefix'    => 'p',
            'fnPrefix'    => 'p',
            'formId'      => 'panelPaymentForm',
            'action'      => route('billing.payment', $invoice),
            'fromPatient' => $fromPatient,
        ])
    </div>
</div>
@endif

{{-- ── Quick links ─────────────────────────────────────────────────── --}}
<div class="flex gap-2 mt-2">
    <a href="{{ route('billing.show', $invoice) }}?from_patient={{ $fromPatient }}" target="_blank"
       class="flex-1 text-center py-2 text-xs font-medium text-gray-600 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100">
        Open Full Page ↗
    </a>
    <a href="{{ route('billing.print', $invoice) }}" target="_blank"
       class="flex-1 text-center py-2 text-xs font-medium text-gray-600 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100">
        Print / PDF
    </a>
</div>

{{-- ── Panel JS ─────────────────────────────────────────────────────── --}}
<script>
(function() {
// Configurable in Settings → Billing → Credit Card Convenience Fee
const CC_LIMIT    = {{ (float) \App\Models\AppSetting::get('cc_convenience_threshold', 10000) }};
const CC_RATE     = {{ (float) \App\Models\AppSetting::get('cc_convenience_rate', 2.5) / 100 }};
const PANEL_TOTAL = {{ (float) $invoice->total_amount }};

const ps = id => { const e = document.getElementById(id); if(e) e.classList.remove('hidden'); };
const ph = id => { const e = document.getElementById(id); if(e) e.classList.add('hidden'); };

function pOnModeChange() {
    const mode = document.getElementById('pMode').value;
    ph('pFieldRef'); ph('pFieldCC'); ph('pFieldCheque'); ph('pFieldEmi');
    if (['upi','bank_transfer'].includes(mode)) ps('pFieldRef');
    if (mode === 'card')   { ps('pFieldCC'); pOnAmountChange(); }
    if (mode === 'cheque') ps('pFieldCheque');
    if (mode === 'emi')    ps('pFieldEmi');
}

function pOnAmountChange() {
    if (document.getElementById('pMode').value !== 'card') return;
    const amt  = parseFloat(document.getElementById('pAmount').value) || 0;
    if (amt > CC_LIMIT) {
        const fee = Math.round(amt * CC_RATE * 100) / 100;
        document.getElementById('pCcFeeAmt').textContent = 'Rs. ' + fee.toFixed(2);
        document.getElementById('pConvFee').value = fee;
        ps('pCcFeePanel');
        // What the patient actually pays — amount + fee. billing/show has always
        // shown this line; the panel did not, so the same card payment looked
        // different depending on which screen you opened it from.
        document.getElementById('pCcTotalAmt').textContent = 'Rs. ' + (amt + fee).toFixed(2);
        ps('pCcTotal');
    } else {
        ph('pCcFeePanel');
        ph('pCcTotal');
        document.getElementById('pConvFee').value = 0;
    }
}

function pSwitchEmi(type) {
    document.getElementById('pEmiType').value = type;
    const onActive = ['border-purple-600','bg-purple-600','text-white'];
    const onIdle   = ['border-purple-200','bg-white','text-purple-700'];
    const d = document.getElementById('pBtnDirect');
    const p = document.getElementById('pBtnProvider');
    if (type === 'direct') {
        onActive.forEach(c=>d.classList.add(c));   onIdle.forEach(c=>d.classList.remove(c));
        onIdle.forEach(c=>p.classList.add(c));     onActive.forEach(c=>p.classList.remove(c));
        ps('pDirectFields'); ph('pProviderFields');
    } else {
        onActive.forEach(c=>p.classList.add(c));   onIdle.forEach(c=>p.classList.remove(c));
        onIdle.forEach(c=>d.classList.add(c));     onActive.forEach(c=>d.classList.remove(c));
        ph('pDirectFields'); ps('pProviderFields');
    }
}

function pCalcEmi() {
    const P = parseFloat(document.getElementById('pAmount').value) || 0;
    const n = parseInt(document.getElementById('pEmiTenure').value) || 0;
    const r = parseFloat(document.getElementById('pEmiRate').value) || 0;
    const s = document.getElementById('pEmiStart').value;
    if (!P || !n || !s) { ph('pEmiResult'); ph('pEmiScheduleWrap'); return; }
    const mr = r > 0 ? r / 100 / 12 : 0;
    let emi;
    if (mr <= 0) {
        emi = Math.round(P / n * 100) / 100;
    } else {
        const f = Math.pow(1 + mr, n);
        emi = Math.round(P * mr * f / (f - 1) * 100) / 100;
    }
    const totalPayable = emi * n;
    const totalInterest = totalPayable - P;
    document.getElementById('pEmiMonthly').textContent  = 'Rs. ' + emi.toFixed(2);
    document.getElementById('pEmiTotal').textContent    = 'Rs. ' + totalPayable.toFixed(2);
    document.getElementById('pEmiInterest').textContent = 'Rs. ' + totalInterest.toFixed(2);
    ps('pEmiResult');
    // Build schedule
    const tbody = document.getElementById('pEmiScheduleBody');
    tbody.innerHTML = '';
    let balance = P;
    const startDate = new Date(s);
    for (let i = 1; i <= n; i++) {
        const dueDate = new Date(startDate);
        dueDate.setMonth(dueDate.getMonth() + (i - 1));
        const interestPart = Math.round(balance * mr * 100) / 100;
        const principalPart = Math.round((emi - interestPart) * 100) / 100;
        balance = Math.round((balance - principalPart) * 100) / 100;
        const tr = document.createElement('tr');
        tr.innerHTML = `<td class="px-2 py-1 text-gray-500">${i}</td>
            <td class="px-2 py-1 text-gray-700">${dueDate.toLocaleDateString('en-IN',{day:'2-digit',month:'short',year:'numeric'})}</td>
            <td class="px-2 py-1 text-right text-gray-700">Rs. ${principalPart.toFixed(2)}</td>
            <td class="px-2 py-1 text-right text-gray-500">Rs. ${interestPart.toFixed(2)}</td>
            <td class="px-2 py-1 text-right font-medium text-purple-700">Rs. ${emi.toFixed(2)}</td>`;
        tbody.appendChild(tr);
    }
    ps('pEmiScheduleWrap');
}

function pToggleEmiSchedule() {
    const t = document.getElementById('pEmiScheduleTable');
    const b = document.getElementById('pEmiToggleBtn');
    t.classList.toggle('hidden');
    b.textContent = t.classList.contains('hidden') ? 'Show' : 'Hide';
}

let _panelSchemes = [];
function pLoadSchemes() {
    const pid = document.getElementById('pProviderSel').value;
    ph('pSchemeWrap'); ph('pProviderBreakdown'); _panelSchemes = [];
    if (!pid) return;
    const url = '{{ route("settings.emi.schemes.ajax") }}?provider_id=' + pid + '&invoice_total=' + PANEL_TOTAL;
    fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(r => r.json())
        .then(data => {
            _panelSchemes = data;
            const sel = document.getElementById('pSchemeSel');
            sel.innerHTML = '<option value="">— Select —</option>';
            data.forEach(s => {
                const o = document.createElement('option');
                o.value = s.id;
                o.textContent = s.scheme_name + ' · ' + s.tenure_months + 'M';
                sel.appendChild(o);
            });
            ps('pSchemeWrap');
        });
}

function pApplyScheme() {
    const sid = document.getElementById('pSchemeSel').value;
    ph('pProviderBreakdown');
    if (!sid) return;
    const s = _panelSchemes.find(x => String(x.id) === String(sid));
    if (!s) return;
    const fmt = v => 'Rs. ' + parseFloat(v).toFixed(2);
    // Monthly EMI + upfront
    document.getElementById('pPbMonthly').textContent = fmt(s.patient_monthly_emi);
    if (s.upfront_emis > 0) {
        document.getElementById('pPbUpfrontCount').textContent = s.upfront_emis;
        document.getElementById('pPbUpfront').textContent = fmt(s.patient_upfront_amount);
        ps('pPbUpfrontRow');
    } else { ph('pPbUpfrontRow'); }
    // Detailed cost breakdown
    document.getElementById('pPbClinicInterest').textContent = fmt(s.clinic_interest_cost ?? 0);
    document.getElementById('pPbGstInterest').textContent    = fmt(s.gst_on_interest ?? 0);
    document.getElementById('pPbDeduction').textContent      = fmt(s.provider_deduction ?? 0);
    document.getElementById('pPbNet').textContent            = fmt(s.clinic_net_amount);
    // Convenience charge
    if (s.pass_cost_to_patient && s.convenience_charge > 0) {
        document.getElementById('pPbConv').textContent        = fmt(s.convenience_charge);
        document.getElementById('pPbReceiptTotal').textContent = fmt((parseFloat(s.patient_upfront_amount)||0) + parseFloat(s.convenience_charge));
        document.getElementById('pProvConvFee').value         = s.convenience_charge;
        ps('pPbConvRow');
    } else {
        document.getElementById('pProvConvFee').value = 0;
        ph('pPbConvRow');
    }
    document.getElementById('pProvUpfront').value = s.patient_upfront_amount || 0;
    ps('pProviderBreakdown');
}

// expose to global scope (called by inline onchange/oninput)
window.pOnModeChange       = pOnModeChange;
window.pOnAmountChange     = pOnAmountChange;
window.pSwitchEmi          = pSwitchEmi;
window.pCalcEmi            = pCalcEmi;
window.pToggleEmiSchedule  = pToggleEmiSchedule;
window.pLoadSchemes        = pLoadSchemes;
window.pApplyScheme        = pApplyScheme;

// init
pOnMod