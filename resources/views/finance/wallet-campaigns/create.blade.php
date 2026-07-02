@extends('layouts.app')
@section('page-title', 'New Promotional Campaign')

@section('content')
<div class="max-w-6xl mx-auto px-4 py-6">

    <div class="mb-5">
        <a href="{{ route('finance.wallet-campaigns.index') }}" class="text-sm text-gray-500 hover:text-[#6a0f70]">← Campaigns</a>
        <h1 class="text-xl font-bold text-gray-800 mt-1">New Promotional Campaign</h1>
        <p class="text-sm text-gray-500">Set filters → preview matching patients → save as draft → apply.</p>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 mb-4">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('finance.wallet-campaigns.store') }}" id="campaignForm">
    @csrf

    <div class="grid grid-cols-3 gap-5">

        {{-- ══ LEFT: Campaign Settings ══ --}}
        <div class="col-span-2 space-y-5">

            {{-- Campaign identity --}}
            <div class="bg-white border border-gray-200 rounded-lg p-5 space-y-4">
                <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Campaign Details</h2>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Campaign Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                           placeholder="e.g. Diwali 2025 Offer, New Patient Welcome Bonus"
                           class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description (optional)</label>
                    <textarea name="description" rows="2"
                              placeholder="Internal note about this campaign..."
                              class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">{{ old('description') }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Amount per Patient (Rs. ) <span class="text-red-500">*</span></label>
                        <input type="number" name="amount" value="{{ old('amount') }}" min="1" step="0.01" required
                               placeholder="e.g. 500"
                               class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Valid Until <span class="text-red-500">*</span></label>
                        <input type="date" name="expiry_date" value="{{ old('expiry_date') }}" required
                               min="{{ now()->addDay()->format('Y-m-d') }}"
                               class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                    </div>
                </div>

                {{-- Treatment restriction --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Treatment Restriction</label>
                    <div class="flex gap-5 mb-2">
                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                            <input type="radio" name="treatment_scope" value="all"
                                   {{ old('treatment_scope', 'all') === 'all' ? 'checked' : '' }}
                                   class="w-4 h-4 text-amber-600">
                            <span>All treatments</span>
                        </label>
                        <label class="flex items-center gap-2 cursor-pointer text-sm">
                            <input type="radio" name="treatment_scope" value="specific"
                                   {{ old('treatment_scope') === 'specific' ? 'checked' : '' }}
                                   class="w-4 h-4 text-amber-600">
                            <span>Specific treatments only</span>
                        </label>
                    </div>
                    <div id="treatmentPicker" class="{{ old('treatment_scope') === 'specific' ? '' : 'hidden' }} border border-gray-200 rounded max-h-40 overflow-y-auto p-2 bg-gray-50 space-y-0.5">
                        @foreach($treatments as $t)
                            <label class="flex items-center gap-2 px-2 py-1.5 hover:bg-white rounded cursor-pointer text-sm">
                                <input type="checkbox" name="applicable_treatments[]" value="{{ $t->id }}"
                                       {{ in_array($t->id, old('applicable_treatments', [])) ? 'checked' : '' }}
                                       class="w-4 h-4 text-amber-600 rounded">
                                <span class="text-gray-700">{{ $t->name }}</span>
                            </label>
                        @endforeach
                    </div>
                    <p id="treatmentRestrictionNote" class="{{ old('treatment_scope') === 'specific' ? '' : 'hidden' }} text-xs text-red-500 mt-1">
                        Promo will be blocked at billing if invoice doesn't include these treatments.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Internal Notes (optional)</label>
                    <input type="text" name="notes" value="{{ old('notes') }}"
                           placeholder="e.g. Approved by Dr. Sumit for festive season"
                           class="w-full border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                </div>
            </div>

            {{-- ── Patient Filters ── --}}
            <div class="bg-white border border-gray-200 rounded-lg p-5 space-y-5">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Patient Filters</h2>
                        {{-- Green badge shown when no filters are active --}}
                        <span id="allPatientsBadge"
                              class="text-xs bg-green-100 text-green-700 border border-green-200 px-2.5 py-0.5 rounded-full font-medium">
                            All Patients
                        </span>
                    </div>
                    <button type="button" id="clearFiltersBtn"
                            onclick="clearAllFilters()"
                            class="hidden text-xs text-gray-400 hover:text-red-500 transition-colors underline underline-offset-2">
                        Clear All Filters
                    </button>
                </div>

                {{-- Gender --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Gender</label>
                    <div class="flex flex-wrap gap-3">
                        @foreach(['male' => 'Male', 'female' => 'Female', 'other' => 'Other'] as $val => $label)
                            <label class="flex items-center gap-2 cursor-pointer text-sm filter-trigger">
                                <input type="checkbox" name="filter_gender[]" value="{{ $val }}"
                                       {{ in_array($val, old('filter_gender', [])) ? 'checked' : '' }}
                                       class="w-4 h-4 text-[#6a0f70] rounded">
                                <span class="text-gray-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Area --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Area / Locality</label>
                    @if($areas->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach($areas as $area)
                                <label class="flex items-center gap-1.5 cursor-pointer text-sm filter-trigger">
                                    <input type="checkbox" name="filter_area[]" value="{{ $area }}"
                                           {{ in_array($area, old('filter_area', [])) ? 'checked' : '' }}
                                           class="w-4 h-4 text-[#6a0f70] rounded">
                                    <span class="text-gray-700">{{ $area }}</span>
                                </label>
                            @endforeach
                        </div>
                    @else
                        <p class="text-xs text-gray-400">No area data in patient records yet.</p>
                    @endif
                </div>

                {{-- Age Range --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Age Group</label>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">From</span>
                            <input type="number" name="filter_age_min" value="{{ old('filter_age_min') }}"
                                   min="0" max="150" placeholder="e.g. 18"
                                   class="w-20 border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:border-[#6a0f70] filter-trigger-input">
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-sm text-gray-500">to</span>
                            <input type="number" name="filter_age_max" value="{{ old('filter_age_max') }}"
                                   min="0" max="150" placeholder="e.g. 60"
                                   class="w-20 border border-gray-300 px-2 py-1.5 text-sm focus:outline-none focus:border-[#6a0f70] filter-trigger-input">
                            <span class="text-sm text-gray-400">years</span>
                        </div>
                    </div>
                </div>

                {{-- Membership --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Membership Status</label>
                    <div class="flex flex-wrap gap-3">
                        @foreach(['active' => 'Active Member', 'not_enrolled' => 'Not Enrolled', 'expired' => 'Expired'] as $val => $label)
                            <label class="flex items-center gap-2 cursor-pointer text-sm filter-trigger">
                                <input type="checkbox" name="filter_membership[]" value="{{ $val }}"
                                       {{ in_array($val, old('filter_membership', [])) ? 'checked' : '' }}
                                       class="w-4 h-4 text-[#6a0f70] rounded">
                                <span class="text-gray-700">{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>

                {{-- Tags --}}
                @if($tags->isNotEmpty())
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Patient Tags</label>
                    <p class="text-xs text-gray-400 mb-2">Patient must have ALL selected tags.</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach($tags as $tag)
                            <label class="flex items-center gap-1.5 cursor-pointer filter-trigger">
                                <input type="checkbox" name="filter_tag_ids[]" value="{{ $tag->id }}"
                                       {{ in_array($tag->id, old('filter_tag_ids', [])) ? 'checked' : '' }}
                                       class="w-4 h-4 text-[#6a0f70] rounded">
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                      style="background: {{ $tag->color ?? '#f3f4f6' }}20; color: {{ $tag->color ?? '#374151' }}; border: 1px solid {{ $tag->color ?? '#e5e7eb' }}">
                                    {{ $tag->name }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Source --}}
                @if($sources->isNotEmpty())
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Patient Source</label>
                    <div class="flex flex-wrap gap-3">
                        @foreach($sources as $source)
                            <label class="flex items-center gap-1.5 cursor-pointer text-sm filter-trigger">
                                <input type="checkbox" name="filter_source[]" value="{{ $source }}"
                                       {{ in_array($source, old('filter_source', [])) ? 'checked' : '' }}
                                       class="w-4 h-4 text-[#6a0f70] rounded">
                                <span class="text-gray-700 capitalize">{{ str_replace('_', ' ', $source) }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                @endif

            </div>

        </div>

        {{-- ══ RIGHT: Live Preview Panel (sticky, self-contained) ══ --}}
        <div>
            {{-- Single sticky card: preview + save always visible --}}
            <div class="bg-white border border-gray-200 rounded-lg sticky top-4 flex flex-col"
                 style="max-height: calc(100vh - 2rem);">

                {{-- Card header --}}
                <div class="px-4 pt-4 pb-3 border-b border-gray-100 flex-shrink-0">
                    <h3 class="text-sm font-bold text-gray-700">Live Preview</h3>
                </div>

                {{-- Scrollable preview body --}}
                <div class="flex-1 overflow-y-auto p-4 space-y-3 min-h-0">

                    <div id="previewLoading" class="hidden text-xs text-gray-400 text-center py-4">
                        <svg class="animate-spin w-5 h-5 mx-auto mb-1 text-[#6a0f70]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        Calculating...
                    </div>

                    <div id="previewResult">
                        <div class="text-center py-3">
                            <div class="text-3xl font-bold text-[#6a0f70]" id="previewCount">—</div>
                            <div class="text-xs text-gray-500 mt-1">patients match your filters</div>
                        </div>

                        <div id="previewTotal" class="hidden bg-amber-50 border border-amber-100 rounded px-3 py-2 text-center">
                            <div class="text-xs text-amber-700">Total Credits to Issue</div>
                            <div class="text-lg font-bold text-amber-700" id="previewTotalAmt">—</div>
                        </div>

                        <div id="previewList" class="space-y-1 mt-2"></div>

                        <p id="previewMore" class="hidden text-xs text-gray-400 text-center mt-2">Showing first 10 matches</p>
                    </div>

                    <button type="button" id="previewBtn"
                            onclick="triggerPreview()"
                            class="w-full bg-gray-100 text-gray-700 text-sm py-2 rounded hover:bg-gray-200 transition-colors font-medium">
                        Preview Patients
                    </button>

                    {{-- Warning note (shown after first preview) --}}
                    <div id="summaryCard" class="hidden bg-amber-50 border border-amber-200 rounded p-3 text-xs text-amber-800">
                        <p class="font-semibold mb-1">Before applying:</p>
                        <ul class="space-y-1 list-disc list-inside text-amber-700">
                            <li>Saved as <strong>Draft</strong> first — review before applying.</li>
                            <li>Once applied, credits are issued immediately.</li>
                            <li>Cannot be reversed in bulk.</li>
                        </ul>
                    </div>

                </div>

                {{-- Pinned footer: save button always visible --}}
                <div class="px-4 py-3 border-t border-gray-100 flex-shrink-0 space-y-2">
                    <button type="submit"
                            class="w-full bg-[#6a0f70] text-white text-sm py-2.5 hover:bg-[#380740] transition-colors font-medium rounded">
                        Save as Draft →
                    </button>
                    <a href="{{ route('finance.wallet-campaigns.index') }}"
                       class="block text-center text-sm text-gray-500 hover:text-gray-700">Cancel</a>
                </div>

            </div>
        </div>
    </div>

    </form>
</div>

<script>
// ── Treatment picker toggle ─────────────────────────────────────────────────
document.querySelectorAll('[name="treatment_scope"]').forEach(r => {
    r.addEventListener('change', () => {
        const isSpecific = r.value === 'specific' && r.checked;
        document.getElementById('treatmentPicker').classList.toggle('hidden', !isSpecific);
        document.getElementById('treatmentRestrictionNote').classList.toggle('hidden', !isSpecific);
    });
});

// ── Live preview ────────────────────────────────────────────────────────────
let previewTimeout = null;

function getFilters() {
    const fd = new FormData(document.getElementById('campaignForm'));
    const params = new URLSearchParams();
    for (const [k, v] of fd.entries()) {
        if (k.startsWith('filter_') || k === 'amount') {
            params.append(k, v);
        }
    }
    return params;
}

function triggerPreview() {
    const loading = document.getElementById('previewLoading');
    const result  = document.getElementById('previewResult');
    loading.classList.remove('hidden');
    result.classList.add('opacity-50');

    const params = getFilters();

    fetch('{{ route('finance.wallet-campaigns.preview') }}?' + params.toString(), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('previewCount').textContent = data.count.toLocaleString();

        // Total amount calculation
        const amount = parseFloat(document.querySelector('[name="amount"]')?.value) || 0;
        if (amount > 0 && data.count > 0) {
            document.getElementById('previewTotal').classList.remove('hidden');
            document.getElementById('previewTotalAmt').textContent =
                'Rs. ' + (amount * data.count).toLocaleString('en-IN', {minimumFractionDigits: 0});
        } else {
            document.getElementById('previewTotal').classList.add('hidden');
        }

        // Patient preview list
        const list = document.getElementById('previewList');
        list.innerHTML = '';
        data.preview.forEach(p => {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between text-xs py-1 border-b border-gray-50 last:border-0';
            div.innerHTML = `<span class="font-medium text-gray-700">${p.name}</span>
                             <span class="text-gray-400">${p.phone || ''}</span>`;
            list.appendChild(div);
        });

        document.getElementById('previewMore').classList.toggle('hidden', data.count <= 10);
        document.getElementById('summaryCard').classList.remove('hidden');
        loading.classList.add('hidden');
        result.classList.remove('opacity-50');
    })
    .catch(() => {
        loading.classList.add('hidden');
        result.classList.remove('opacity-50');
    });
}

// ── "All Patients" badge + Clear Filters ────────────────────────────────────
function hasActiveFilters() {
    const anyChecked = [...document.querySelectorAll('.filter-trigger input[type="checkbox"]')]
                       .some(el => el.checked);
    const ageMin = document.querySelector('[name="filter_age_min"]')?.value?.trim();
    const ageMax = document.querySelector('[name="filter_age_max"]')?.value?.trim();
    return anyChecked || ageMin || ageMax;
}

function updateFilterBadge() {
    const active = hasActiveFilters();
    document.getElementById('allPatientsBadge').classList.toggle('hidden', active);
    document.getElementById('clearFiltersBtn').classList.toggle('hidden', !active);
}

function clearAllFilters() {
    // Uncheck all filter checkboxes
    document.querySelectorAll('.filter-trigger input[type="checkbox"]').forEach(el => {
        el.checked = false;
    });
    // Clear age inputs
    const ageMin = document.querySelector('[name="filter_age_min"]');
    const ageMax = document.querySelector('[name="filter_age_max"]');
    if (ageMin) ageMin.value = '';
    if (ageMax) ageMax.value = '';
    // Update badge and re-preview
    updateFilterBadge();
    clearTimeout(previewTimeout);
    previewTimeout = setTimeout(triggerPreview, 300);
}

// Auto-trigger preview when filters change (debounced)
document.querySelectorAll('.filter-trigger input').forEach(el => {
    el.addEventListener('change', () => {
        updateFilterBadge();
        clearTimeout(previewTimeout);
        previewTimeout = setTimeout(triggerPreview, 600);
    });
});

document.querySelectorAll('.filter-trigger-input').forEach(el => {
    el.addEventListener('input', () => {
        updateFilterBadge();
        clearTimeout(previewTimeout);
        previewTimeout = setTimeout(triggerPreview, 800);
    });
});

// Trigger on amount change (to update total display)
document.querySelector('[name="amount"]')?.addEventListener('input', () => {
    clearTimeout(previewTimeout);
    previewTimeout = setTimeout(triggerPreview, 600);
});

// Initial state on load
updateFilterBadge();
triggerPreview();
</script>
@endsection
