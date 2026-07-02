@extends('layouts.app')
@section('page-title', 'Wallet Management')

@section('content')
<div class="max-w-5xl mx-auto px-4 py-6">

    <a href="{{ route('finance.dashboard') }}" class="inline-block text-sm text-gray-500 hover:text-[#6a0f70] mb-4">← Finance</a>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-xl font-bold text-gray-800">Wallet Management</h1>
            <p class="text-sm text-gray-500 mt-0.5">Promotional campaigns · Individual credit balances.</p>
        </div>
        <a href="{{ route('finance.wallet.register') }}"
           class="border border-[#6a0f70] text-[#6a0f70] text-sm px-4 py-2 hover:bg-[#6a0f70] hover:text-white transition-colors font-medium">
            Transaction Register
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-700 text-sm px-4 py-2 mb-4">{{ session('success') }}</div>
    @endif

    {{-- ── Dashboard Cards ──────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Patients w/ Balance</div>
            <div class="text-2xl font-bold text-[#6a0f70]">{{ number_format($patientsWithBalance) }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Total Outstanding</div>
            <div class="text-2xl font-bold text-[#6a0f70]">Rs. {{ number_format($totalOutstanding, 0) }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Credits This Month</div>
            <div class="text-2xl font-bold text-green-600">Rs. {{ number_format($creditsThisMonth, 0) }}</div>
        </div>
        <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
            <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Utilized This Month</div>
            <div class="text-2xl font-bold text-red-500">Rs. {{ number_format($utilizedThisMonth, 0) }}</div>
        </div>
        <div class="bg-[#6a0f70] rounded-lg p-4 text-center text-white">
            <div class="text-xs uppercase tracking-wide mb-1 opacity-80">Active Balance</div>
            <div class="text-2xl font-bold">Rs. {{ number_format($activeBalance, 0) }}</div>
        </div>
    </div>

    {{-- ── Tab nav ──────────────────────────────────────────────────── --}}
    <div class="flex gap-1 mb-5 border-b border-gray-200">
        <button onclick="switchTab('promo')" id="tab-promo"
                class="tab-btn px-5 py-2.5 text-sm font-medium border-b-2 border-[#6a0f70] text-[#6a0f70] -mb-px">
            Promotional Campaigns
        </button>
        <button onclick="switchTab('credit')" id="tab-credit"
                class="tab-btn px-5 py-2.5 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 -mb-px">
            Individual Credit
        </button>
    </div>

    {{-- ══════════════════════════════════════════════
         TAB: Promotional Campaigns
    ══════════════════════════════════════════════ --}}
    <div id="pane-promo">
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm text-gray-500">Bulk promotional money credited to patients matching filter criteria.</p>
            <a href="{{ route('finance.wallet-campaigns.create') }}"
               class="bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors font-medium">
                + New Campaign
            </a>
        </div>

        @if($campaigns->isEmpty())
            <div class="bg-white border border-gray-200 rounded-lg px-6 py-14 text-center">
                <div class="w-12 h-12 rounded-full bg-amber-50 flex items-center justify-center mx-auto mb-3">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                </div>
                <p class="text-gray-500 font-medium">No campaigns yet</p>
                <p class="text-xs text-gray-400 mt-1 mb-4">Create a campaign to bulk-credit promotional money using patient filters.</p>
                <a href="{{ route('finance.wallet-campaigns.create') }}"
                   class="inline-block bg-[#6a0f70] text-white text-sm px-5 py-2 hover:bg-[#380740]">
                    + New Campaign
                </a>
            </div>
        @else
            <div class="bg-white border border-gray-200 overflow-hidden rounded-lg">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Campaign</th>
                            <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Filters</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Amount</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Expiry</th>
                            <th class="text-center px-4 py-3 text-xs font-semibold text-gray-500">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($campaigns as $campaign)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-800">{{ $campaign->name }}</div>
                                    <div class="text-xs text-gray-400 mt-0.5">{{ $campaign->created_at->format('d M Y') }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs text-gray-500 max-w-xs">
                                    {{ $campaign->filterSummary() }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="font-bold text-amber-700">Rs. {{ number_format($campaign->amount, 0) }}</span>
                                </td>
                                <td class="px-4 py-3 text-center text-xs text-gray-600">
                                    {{ $campaign->expiry_date->format('d M Y') }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    @if($campaign->status === 'draft')
                                        <span class="text-xs bg-blue-100 text-blue-700 px-2 py-0.5 rounded-full">Draft</span>
                                    @elseif($campaign->status === 'applied')
                                        <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full">
                                            Applied · {{ number_format($campaign->patients_credited) }} pts
                                        </span>
                                    @else
                                        <span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full">Cancelled</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('finance.wallet-campaigns.show', $campaign) }}"
                                       class="text-xs text-[#6a0f70] hover:underline font-medium">
                                        {{ $campaign->isDraft() ? 'Review →' : 'Details →' }}
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ══════════════════════════════════════════════
         TAB: Individual Credit
    ══════════════════════════════════════════════ --}}
    <div id="pane-credit" class="hidden">
        <div class="flex items-center justify-between mb-4">
            <p class="text-sm text-gray-500">Patients with credit balance (advance payment / credit note).</p>
            <button onclick="openAddCreditModal()"
                    class="bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors font-medium flex items-center gap-1.5">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                </svg>
                Add Credit
            </button>
        </div>

        {{-- Active credit wallets --}}
        <div>
            <h3 class="text-sm font-semibold text-gray-700 mb-3">Patients with Credit Balance</h3>
            @if($creditWallets->isEmpty())
                <div class="bg-white border border-gray-200 rounded-lg px-6 py-8 text-center text-gray-400 text-sm">
                    No patients have a credit balance yet.
                </div>
            @else
                <div class="bg-white border border-gray-200 overflow-hidden rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b border-gray-100">
                            <tr>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500">Patient</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500">Credit Balance</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500">Promo Balance</th>
                                <th class="text-right px-4 py-3 text-xs font-semibold text-gray-500">Total</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($creditWallets as $wallet)
                                @continue(!$wallet->patient)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('patients.show', $wallet->patient) }}"
                                           class="font-medium text-gray-800 hover:text-[#6a0f70]">
                                            {{ $wallet->patient->name }}
                                        </a>
                                        <div class="text-xs text-gray-400">{{ $wallet->patient->phone }}</div>
                                    </td>
                                    <td class="px-4 py-3 text-right text-purple-700 font-medium">
                                        Rs. {{ number_format($wallet->balance_permanent, 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-amber-600 text-xs">
                                        @if($wallet->balance_promotional > 0)
                                            Rs. {{ number_format($wallet->balance_promotional, 0) }}
                                        @else
                                            <span class="text-gray-300">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right font-bold text-[#6a0f70]">
                                        Rs. {{ number_format($wallet->balance_total, 0) }}
                                    </td>
                                    <td class="px-4 py-3 flex items-center gap-3 justify-end">
                                        <a href="{{ route('finance.wallets.show', $wallet->patient) }}"
                                           class="text-xs text-gray-500 hover:underline">Ledger</a>
                                        <a href="{{ route('finance.wallets.credit-form', $wallet->patient) }}"
                                           class="text-xs text-[#6a0f70] hover:underline font-medium">+ Add Credit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">{{ $creditWallets->links() }}</div>
            @endif
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════════
     ADD CREDIT MODAL
══════════════════════════════════════════════════════════════ --}}
<div id="addCreditModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
     onclick="if(event.target===this)closeAddCreditModal()">
    <div class="bg-white w-full max-w-lg mx-4 rounded-lg shadow-2xl flex flex-col max-h-[92vh]">

        {{-- Modal header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-shrink-0">
            <div>
                <h3 class="text-base font-bold text-gray-800" id="acmTitle">Add Wallet Credit</h3>
                <p class="text-xs text-gray-400 mt-0.5" id="acmSubtitle">Search for a patient to credit</p>
            </div>
            <button onclick="closeAddCreditModal()" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>

        <div class="overflow-y-auto flex-1">

            {{-- ── STEP 1: Patient search ──────────────────────────── --}}
            <div id="acmStep1" class="p-5">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Search Patient</label>
                <input type="text" id="acmSearch"
                       placeholder="Name, mobile number, or patient ID..."
                       class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70] mb-2"
                       oninput="acmSearchPatients(this.value)">

                <div id="acmSearchLoading" class="hidden text-center py-4 text-sm text-gray-400">Searching...</div>
                <div id="acmSearchEmpty" class="hidden text-center py-4 text-sm text-gray-400">No patients found.</div>
                <div id="acmSearchResults" class="hidden border border-gray-200 rounded divide-y divide-gray-100 max-h-60 overflow-y-auto">
                    <div id="acmSearchList"></div>
                </div>

                {{-- Recent / existing wallet patients shortcut --}}
                <div class="mt-4">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Or pick from patients with existing balance</p>
                    <div class="space-y-1 max-h-44 overflow-y-auto">
                        @forelse($creditWallets->take(8) as $cw)
                        <button type="button"
                                onclick="acmSelectPatient({{ $cw->patient->id }}, '{{ addslashes($cw->patient->name) }}', '{{ $cw->patient->phone }}')"
                                class="w-full text-left flex items-center justify-between px-3 py-2 rounded hover:bg-purple-50 border border-transparent hover:border-purple-200 transition text-sm">
                            <div>
                                <span class="font-medium text-gray-800">{{ $cw->patient->name }}</span>
                                <span class="text-xs text-gray-400 ml-2">{{ $cw->patient->phone }}</span>
                            </div>
                            <span class="text-xs text-purple-700 font-semibold">Rs. {{ number_format($cw->balance_total, 0) }}</span>
                        </button>
                        @empty
                        <p class="text-xs text-gray-400 py-2">No patients with existing balance.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ── STEP 2: Credit form ──────────────────────────────── --}}
            <div id="acmStep2" class="hidden">
                <div class="px-5 pt-4 pb-2 border-b border-gray-100 flex items-center gap-2">
                    <button onclick="acmBackToSearch()" class="text-gray-400 hover:text-[#6a0f70] transition">
                        <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <div>
                        <span class="text-sm font-semibold text-gray-800" id="acmPatientName"></span>
                        <span class="text-xs text-gray-400 ml-1" id="acmPatientPhone"></span>
                    </div>
                </div>

                <form id="acmForm" method="POST" action="" class="p-5 space-y-5">
                    @csrf

                    {{-- Credit Type --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">Credit Type <span class="text-red-500">*</span></label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="cursor-pointer" for="acm_type_perm">
                                <input type="radio" name="credit_type" value="permanent" id="acm_type_perm" checked class="sr-only peer">
                                <div class="peer-checked:border-[#6a0f70] peer-checked:bg-purple-50 border-2 border-gray-200 rounded-lg p-3 transition-all">
                                    <p class="text-sm font-semibold text-[#6a0f70]">Credit Balance</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Advance / credit note. All treatments. Optional expiry.</p>
                                </div>
                            </label>
                            <label class="cursor-pointer" for="acm_type_promo">
                                <input type="radio" name="credit_type" value="promotional" id="acm_type_promo" class="sr-only peer">
                                <div class="peer-checked:border-amber-500 peer-checked:bg-amber-50 border-2 border-gray-200 rounded-lg p-3 transition-all">
                                    <p class="text-sm font-semibold text-amber-700">Promotional</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Campaign-based. Has expiry. Consumed first at billing.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    {{-- Amount --}}
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-1">Amount (Rs. ) <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" min="1" step="0.01" required
                               placeholder="e.g. 500"
                               class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                    </div>

                    {{-- Permanent: optional expiry --}}
                    <div id="acmPermFields" class="border-l-4 border-purple-300 pl-4 space-y-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="acmPermHasExpiry" name="perm_has_expiry" value="1"
                                   class="w-4 h-4 text-[#6a0f70]"
                                   onchange="document.getElementById('acmPermExpiryRow').classList.toggle('hidden', !this.checked)">
                            <span class="text-sm text-gray-700">Set validity / expiry date</span>
                            <span class="text-xs text-gray-400">(optional)</span>
                        </label>
                        <div id="acmPermExpiryRow" class="hidden">
                            <input type="date" name="perm_expiry_date"
                                   min="{{ now()->addDay()->format('Y-m-d') }}"
                                   class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                        </div>
                    </div>

                    {{-- Promotional fields --}}
                    <div id="acmPromoFields" class="hidden border-l-4 border-amber-300 pl-4 space-y-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Campaign Name <span class="text-gray-400 text-xs">(optional)</span></label>
                            <input type="text" name="campaign_name" placeholder="e.g. Diwali Offer"
                                   class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-amber-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Valid Until <span class="text-red-500">*</span></label>
                            <input type="date" name="expiry_date" id="acmPromoExpiry"
                                   min="{{ now()->addDay()->format('Y-m-d') }}"
                                   class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-amber-500">
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Notes <span class="text-gray-400 text-xs">(optional)</span></label>
                        <input type="text" name="notes" placeholder="e.g. Advance payment received, referral bonus..."
                               class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                    </div>

                    {{-- Submit --}}
                    <div class="flex items-center gap-3 pt-1">
                        <button type="submit"
                                class="bg-[#6a0f70] text-white text-sm px-6 py-2.5 hover:bg-[#380740] transition-colors font-medium">
                            Add Credit
                        </button>
                        <button type="button" onclick="closeAddCreditModal()"
                                class="text-sm text-gray-500 hover:text-gray-700">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// ── Tab switching ───────────────────────────────────────────────────────────
function switchTab(name) {
    ['promo', 'credit'].forEach(t => {
        document.getElementById('pane-' + t).classList.toggle('hidden', t !== name);
        const btn = document.getElementById('tab-' + t);
        btn.classList.toggle('border-[#6a0f70]', t === name);
        btn.classList.toggle('text-[#6a0f70]', t === name);
        btn.classList.toggle('border-transparent', t !== name);
        btn.classList.toggle('text-gray-500', t !== name);
    });
    localStorage.setItem('walletTab', name);
}

// Restore last active tab
const savedTab = localStorage.getItem('walletTab');
if (savedTab) switchTab(savedTab);

// ── Add Credit Modal ────────────────────────────────────────────────────────
function openAddCreditModal() {
    document.getElementById('addCreditModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    // reset to step 1
    acmBackToSearch();
    setTimeout(() => document.getElementById('acmSearch').focus(), 100);
}

function closeAddCreditModal() {
    document.getElementById('addCreditModal').classList.add('hidden');
    document.body.style.overflow = '';
}

function acmBackToSearch() {
    document.getElementById('acmStep1').classList.remove('hidden');
    document.getElementById('acmStep2').classList.add('hidden');
    document.getElementById('acmSearch').value = '';
    document.getElementById('acmSearchResults').classList.add('hidden');
    document.getElementById('acmSearchEmpty').classList.add('hidden');
    document.getElementById('acmTitle').textContent = 'Add Wallet Credit';
    document.getElementById('acmSubtitle').textContent = 'Search for a patient to credit';
}

function acmSelectPatient(id, name, phone) {
    document.getElementById('acmStep1').classList.add('hidden');
    document.getElementById('acmStep2').classList.remove('hidden');
    document.getElementById('acmPatientName').textContent = name;
    document.getElementById('acmPatientPhone').textContent = phone;
    document.getElementById('acmTitle').textContent = 'Add Credit — ' + name;
    document.getElementById('acmSubtitle').textContent = '';
    document.getElementById('acmForm').action = `/finance/wallets/${id}/credit`;
    // reset form fields
    document.querySelector('#acmForm input[name="amount"]').value = '';
    document.querySelector('#acmForm input[name="notes"]').value = '';
    document.getElementById('acm_type_perm').checked = true;
    acmToggleType('permanent');
}

// Credit type toggle inside modal
document.querySelectorAll('[name="credit_type"]').forEach(r => {
    r.addEventListener('change', () => acmToggleType(r.value));
});

function acmToggleType(val) {
    document.getElementById('acmPermFields').classList.toggle('hidden', val !== 'permanent');
    document.getElementById('acmPromoFields').classList.toggle('hidden', val !== 'promotional');
    document.getElementById('acmPromoExpiry').required = (val === 'promotional');
}

// ── Patient search inside modal ─────────────────────────────────────────────
let acmTimeout = null;

function acmSearchPatients(q) {
    clearTimeout(acmTimeout);
    const results  = document.getElementById('acmSearchResults');
    const empty    = document.getElementById('acmSearchEmpty');
    const loading  = document.getElementById('acmSearchLoading');

    if (q.trim().length < 2) {
        results.classList.add('hidden');
        empty.classList.add('hidden');
        return;
    }

    loading.classList.remove('hidden');
    results.classList.add('hidden');
    empty.classList.add('hidden');

    acmTimeout = setTimeout(() => {
        fetch(`{{ route('patients.search') }}?q=${encodeURIComponent(q)}&limit=10`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            loading.classList.add('hidden');
            const list = document.getElementById('acmSearchList');
            list.innerHTML = '';

            if (!data.length) {
                empty.classList.remove('hidden');
                return;
            }

            data.forEach(p => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'w-full text-left flex items-center justify-between px-4 py-3 hover:bg-purple-50 transition-colors';
                btn.innerHTML = `
                    <div>
                        <div class="font-medium text-gray-800 text-sm">${p.name}</div>
                        <div class="text-xs text-gray-400">${p.phone || ''}</div>
                    </div>
                    <span class="text-xs text-[#6a0f70] font-medium">Select →</span>
                `;
                btn.onclick = () => acmSelectPatient(p.id, p.name, p.phone || '');
                list.appendChild(btn);
            });

            results.classList.remove('hidden');
        })
        .catch(() => loading.classList.add('hidden'));
    }, 350);
}
</script>
@endsection
