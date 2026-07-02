@extends('layouts.app')
@section('page-title', 'Add Wallet Credit — ' . $patient->name)

@section('content')
<div class="max-w-2xl mx-auto px-4 py-6">

    <div class="mb-6">
        <a href="{{ route('finance.wallets.show', $patient) }}" class="text-sm text-gray-500 hover:text-[#6a0f70]">← Wallet Ledger</a>
        <h1 class="text-xl font-bold text-gray-800 mt-1">Add Wallet Credit</h1>
        <p class="text-sm text-gray-500 mt-0.5">
            {{ $patient->name }} · Current balance:
            <strong class="text-[#6a0f70]">Rs. {{ number_format($wallet->balance_total, 0) }}</strong>
        </p>
    </div>

    <form method="POST" action="{{ route('finance.wallets.credit', $patient) }}"
          class="bg-white border border-gray-200 p-6 space-y-6" id="walletForm">
        @csrf

        @if($errors->any())
            <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- ── Credit Type ─────────────────────────────────────────────── --}}
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-3">Credit Type <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 gap-3">

                {{-- Promotional --}}
                <label class="cursor-pointer" for="type_promo">
                    <input type="radio" name="credit_type" value="promotional" id="type_promo"
                           {{ old('credit_type') === 'promotional' ? 'checked' : '' }}
                           class="sr-only peer">
                    <div class="peer-checked:border-amber-500 peer-checked:bg-amber-50 border-2 border-gray-200 rounded-lg p-4 transition-all">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-amber-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-amber-700">Promotional Money</p>
                                <p class="text-xs text-gray-500 mt-0.5">Campaign-based. Has expiry. Can be restricted to specific treatments. Consumed first at billing.</p>
                            </div>
                        </div>
                    </div>
                </label>

                {{-- Credit Balance / Permanent --}}
                <label class="cursor-pointer" for="type_perm">
                    <input type="radio" name="credit_type" value="permanent" id="type_perm"
                           {{ old('credit_type', 'permanent') === 'permanent' ? 'checked' : '' }}
                           class="sr-only peer">
                    <div class="peer-checked:border-[#6a0f70] peer-checked:bg-purple-50 border-2 border-gray-200 rounded-lg p-4 transition-all">
                        <div class="flex items-start gap-3">
                            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-[#6a0f70]">Credit Balance</p>
                                <p class="text-xs text-gray-500 mt-0.5">Advance payment / credit note. Valid for all treatments. Optional validity. Printable credit note.</p>
                            </div>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        {{-- ── Amount ──────────────────────────────────────────────────── --}}
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-1">Amount (Rs. ) <span class="text-red-500">*</span></label>
            <input type="number" name="amount" min="1" step="0.01"
                   value="{{ old('amount') }}"
                   placeholder="e.g. 500"
                   class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]"
                   required>
        </div>

        {{-- ── PROMOTIONAL FIELDS ───────────────────────────────────────── --}}
        <div id="promo_fields" class="{{ old('credit_type') === 'promotional' ? '' : 'hidden' }} space-y-5 border-l-4 border-amber-300 pl-4">

            <p class="text-xs font-semibold text-amber-700 uppercase tracking-wide">Promotional Settings</p>

            {{-- Campaign Name --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Campaign Name
                    <span class="text-gray-400 text-xs font-normal ml-1">(e.g. "Diwali Offer", "New Patient Bonus")</span>
                </label>
                <input type="text" name="campaign_name"
                       value="{{ old('campaign_name') }}"
                       placeholder="Campaign label (optional but recommended)"
                       class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-amber-500">
            </div>

            {{-- Expiry Date --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Valid Until <span class="text-red-500">*</span>
                    <span class="text-gray-400 text-xs font-normal ml-1">(promotional credits must have an expiry)</span>
                </label>
                <input type="date" name="expiry_date" id="promo_expiry"
                       value="{{ old('expiry_date') }}"
                       min="{{ now()->addDay()->format('Y-m-d') }}"
                       class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-amber-500">
            </div>

            {{-- Treatment Scope --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Applicable Treatments</label>
                <div class="flex gap-6 mb-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="treatment_scope" value="all" id="scope_all"
                               {{ old('treatment_scope', 'all') === 'all' ? 'checked' : '' }}
                               class="w-4 h-4 text-amber-600">
                        <span class="text-sm text-gray-700">All treatments</span>
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="radio" name="treatment_scope" value="specific" id="scope_specific"
                               {{ old('treatment_scope') === 'specific' ? 'checked' : '' }}
                               class="w-4 h-4 text-amber-600">
                        <span class="text-sm text-gray-700">Specific treatments only</span>
                    </label>
                </div>

                <div id="treatment_picker" class="{{ old('treatment_scope') === 'specific' ? '' : 'hidden' }}">
                    <p class="text-xs text-gray-500 mb-2">Select which treatments this promo can be applied to:</p>
                    <div class="border border-gray-200 rounded max-h-48 overflow-y-auto p-2 space-y-0.5 bg-gray-50">
                        @forelse($treatments as $treatment)
                            <label class="flex items-center gap-2 px-2 py-1.5 rounded hover:bg-white cursor-pointer text-sm">
                                <input type="checkbox"
                                       name="applicable_treatments[]"
                                       value="{{ $treatment->id }}"
                                       {{ in_array($treatment->id, old('applicable_treatments', [])) ? 'checked' : '' }}
                                       class="w-4 h-4 text-amber-600 rounded">
                                <span class="text-gray-700">{{ $treatment->name }}</span>
                            </label>
                        @empty
                            <p class="text-xs text-gray-400 px-2 py-2">No active treatments found.</p>
                        @endforelse
                    </div>
                    <p class="text-xs text-red-500 mt-1.5">
                        If restricted, this promo will be <strong>blocked</strong> at billing for any other treatment.
                    </p>
                </div>
            </div>
        </div>

        {{-- ── CREDIT / PERMANENT FIELDS ────────────────────────────────── --}}
        <div id="perm_fields" class="{{ old('credit_type', 'permanent') === 'permanent' ? '' : 'hidden' }} space-y-4 border-l-4 border-purple-300 pl-4">

            <p class="text-xs font-semibold text-purple-700 uppercase tracking-wide">Credit Balance Settings</p>

            <div>
                <label class="flex items-center gap-2 cursor-pointer mb-3">
                    <input type="checkbox" id="perm_has_expiry" name="perm_has_expiry" value="1"
                           {{ old('perm_has_expiry') ? 'checked' : '' }}
                           class="w-4 h-4 text-[#6a0f70]">
                    <span class="text-sm font-medium text-gray-700">Set validity / expiry date</span>
                    <span class="text-xs text-gray-400">(leave unchecked = no expiry)</span>
                </label>

                <div id="perm_expiry_row" class="{{ old('perm_has_expiry') ? '' : 'hidden' }}">
                    <input type="date" name="perm_expiry_date" id="perm_expiry"
                           value="{{ old('perm_expiry_date') }}"
                           min="{{ now()->addDay()->format('Y-m-d') }}"
                           class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                    <p class="text-xs text-gray-400 mt-1">Credit balance will not be usable after this date.</p>
                </div>
            </div>

            <div class="bg-purple-50 border border-purple-100 rounded px-3 py-2 text-xs text-purple-700">
                A <strong>printable credit note</strong> will be available after saving from the wallet ledger.
            </div>
        </div>

        {{-- ── Notes ─────────────────────────────────────────────────────── --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Notes (optional)</label>
            <input type="text" name="notes"
                   value="{{ old('notes') }}"
                   placeholder="e.g. Advance payment received, referral bonus..."
                   class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
        </div>

        {{-- ── Submit ─────────────────────────────────────────────────────── --}}
        <div class="flex items-center gap-3 pt-2">
            <button type="submit"
                    class="bg-[#6a0f70] text-white text-sm px-6 py-2.5 hover:bg-[#380740] transition-colors font-medium">
                Add Credit
            </button>
            <a href="{{ route('finance.wallets.show', $patient) }}"
               class="text-sm text-gray-500 hover:text-gray-700">Cancel</a>
        </div>
    </form>

</div>

<script>
// ── Credit type toggle ──────────────────────────────────────────────────────
const typeRadios  = document.querySelectorAll('[name="credit_type"]');
const promoFields = document.getElementById('promo_fields');
const permFields  = document.getElementById('perm_fields');
const promoExpiry = document.getElementById('promo_expiry');

function applyTypeToggle(val) {
    promoFields.classList.toggle('hidden', val !== 'promotional');
    permFields.classList.toggle('hidden',  val !== 'permanent');
    promoExpiry.required = (val === 'promotional');
}

typeRadios.forEach(r => r.addEventListener('change', () => applyTypeToggle(r.value)));
const checkedType = document.querySelector('[name="credit_type"]:checked');
if (checkedType) applyTypeToggle(checkedType.value);

// ── Treatment scope toggle ──────────────────────────────────────────────────
const scopeRadios     = document.querySelectorAll('[name="treatment_scope"]');
const treatmentPicker = document.getElementById('treatment_picker');

scopeRadios.forEach(r => r.addEventListener('change', () => {
    treatmentPicker.classList.toggle('hidden', r.value !== 'specific');
}));

// ── Permanent expiry toggle ─────────────────────────────────────────────────
const permHasExpiry = document.getElementById('perm_has_expiry');
const permExpiryRow = document.getElementById('perm_expiry_row');
const permExpiry    = document.getElementById('perm_expiry');

permHasExpiry.addEventListener('change', () => {
    permExpiryRow.classList.toggle('hidden', !permHasExpiry.checked);
    permExpiry.required = permHasExpiry.checked;
});

if (permHasExpiry.checked) {
    permExpiryRow.classList.remove('hidden');
    permExpiry.required = true;
}
</script>
@endsection
