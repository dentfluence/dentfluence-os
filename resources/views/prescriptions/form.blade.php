@extends('layouts.app')
@section('page-title', $prescription->exists ? 'Edit Prescription' : 'New Prescription')

@section('content')
<div
    class="p-4 md:p-6 max-w-6xl mx-auto"
    x-data="rxForm({
        patientId:   {{ $patient->id }},
        checkUrl:    '{{ route('api.rx.check-alerts') }}',
        searchUrl:   '{{ route('api.rx.drugs.search') }}',
        csrfToken:   '{{ csrf_token() }}',
        existing:    @json($prescription->exists ? $prescription->items->toArray() : []),
    })"
    x-init="init()"
>

    {{-- ── Header ── --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-2xl font-display font-semibold text-brand-800">
                {{ $prescription->exists ? 'Edit Prescription' : 'New Prescription' }}
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Patient: <span class="font-medium text-gray-700">{{ $patient->name }}</span>
                @if($prescription->exists)
                    &nbsp;·&nbsp; <span class="font-mono text-xs text-brand-600">{{ $prescription->prescription_number }}</span>
                @endif
            </p>
        </div>
        <a href="{{ route('patients.show', $patient) }}#prescriptions"
           onclick="sessionStorage.setItem('patientActiveTab','prescriptions')"
           class="text-sm text-gray-500 hover:text-brand-700 flex items-center gap-1">
            ← Back to Patient
        </a>
    </div>

    {{-- ── Validation errors ── --}}
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form
        method="POST"
        action="{{ $prescription->exists
            ? route('patients.prescriptions.update', [$patient, $prescription])
            : route('patients.prescriptions.store', $patient) }}"
        @submit.prevent="submitForm($event)"
        id="rx-form"
    >
        @csrf
        @if($prescription->exists) @method('PUT') @endif

        {{-- Hidden: finalize flag (set by finalize button) --}}
        <input type="hidden" name="finalize" x-bind:value="finalizeOnSave ? '1' : '0'">

        {{-- Hidden: CDSS overrides JSON --}}
        <input type="hidden" name="overrides" x-bind:value="JSON.stringify(acknowledgedOverrides)">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

            {{-- ════════════════════════════════════════════
                 LEFT COLUMN  (2/3)  Drug rows + clinical
            ════════════════════════════════════════════ --}}
            <div class="lg:col-span-2 space-y-5">

                {{-- ── Clinical Context ── --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b border-gray-100">Clinical Context</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Chief Complaint</label>
                            <input type="text" name="chief_complaint"
                                   value="{{ old('chief_complaint', $prescription->chief_complaint) }}"
                                   placeholder="Toothache, post-extraction…"
                                   class="df-input">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Diagnosis</label>
                            <input type="text" name="diagnosis"
                                   value="{{ old('diagnosis', $prescription->diagnosis) }}"
                                   placeholder="Acute pulpitis, dry socket…"
                                   class="df-input">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Follow-up Date</label>
                            <input type="date" name="follow_up_date"
                                   value="{{ old('follow_up_date', $prescription->follow_up_date) }}"
                                   class="df-input">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Language</label>
                            <select name="language" class="df-input">
                                <option value="en" @selected(old('language', $prescription->language ?? 'en') === 'en')>English</option>
                                <option value="mr" @selected(old('language', $prescription->language) === 'mr')>Marathi</option>
                                <option value="hi" @selected(old('language', $prescription->language) === 'hi')>Hindi</option>
                            </select>
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">General Instructions</label>
                            <textarea name="general_instructions" rows="2"
                                      x-ref="instrField"
                                      placeholder="Take medications after food. Complete the full antibiotic course… (or use the quick chips below)"
                                      class="df-input resize-none">{{ old('general_instructions', $prescription->general_instructions) }}</textarea>
                        </div>
                    </div>
                </div>

                {{-- ── Drug Rows ── --}}
                <div class="bg-white rounded-xl border border-red-200 shadow-sm overflow-hidden">
                    {{-- Panel-style header matching the visit prescription panel --}}
                    <div class="flex items-center justify-between px-5 py-3 bg-red-50 border-b border-red-200">
                        <div class="flex items-center gap-2">
                            <svg width="14" height="14" fill="none" stroke="#dc2626" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                <circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/>
                                <line x1="20" y1="4" x2="8.12" y2="15.88"/>
                                <line x1="14.47" y1="14.48" x2="20" y2="20"/>
                                <line x1="8.12" y1="8.12" x2="12" y2="12"/>
                            </svg>
                            <span class="text-xs font-bold text-red-700 uppercase tracking-wide">Prescription</span>
                            <span class="text-xs text-gray-400 font-medium"
                                  x-show="rows.length > 0"
                                  x-text="rows.length + (rows.length === 1 ? ' drug' : ' drugs')"></span>
                        </div>
                        <button type="button" @click="addRow()"
                                class="text-xs px-3 py-1.5 bg-red-600 text-white rounded-lg hover:bg-red-700 transition flex items-center gap-1">
                            + Add Drug
                        </button>
                    </div>

                    <div class="p-5">
                    {{-- Column headers (desktop) — matches visit panel: Drug | SOS | Morn | Noon | Night | Duration | Total --}}
                    <div class="hidden md:grid grid-cols-[2fr_auto_1fr_1fr_1fr_1.5fr_1fr_auto] gap-2 text-xs font-bold text-gray-400 uppercase tracking-wide mb-2 px-1">
                        <span>Drug</span>
                        <span class="text-center w-10">SOS</span>
                        <span class="text-center">Morn</span>
                        <span class="text-center">Noon</span>
                        <span class="text-center">Night</span>
                        <span>Duration</span>
                        <span class="text-center">Total</span>
                        <span></span>
                    </div>

                    {{-- Drug rows --}}
                    <div class="space-y-3" id="rx-rows">
                        <template x-for="(row, idx) in rows" :key="row._id">
                            <div class="border border-gray-100 rounded-lg p-3 bg-gray-50/50 relative"
                                 :class="{ 'border-red-300 bg-red-50/30': rowHasAlert(row) }">

                                {{-- Row top: Drug search + food advice --}}
                                <div class="grid md:grid-cols-[2fr_1fr] gap-2 mb-2">

                                    {{-- Form Type select — user picks first, then searches --}}
                                    <select x-model="row.dosage_form"
                                            @change="applyFormType(row)"
                                            class="df-input text-xs text-gray-600">
                                        <optgroup label="Solid">
                                            <option value="Tablet">Tablet</option>
                                            <option value="Capsule">Capsule</option>
                                            <option value="Lozenge">Lozenge</option>
                                        </optgroup>
                                        <optgroup label="Liquid">
                                            <option value="Syrup">Syrup</option>
                                            <option value="Suspension">Suspension</option>
                                            <option value="Drops">Drops</option>
                                            <option value="Mouthwash">Mouthwash / Rinse</option>
                                        </optgroup>
                                        <optgroup label="Topical (1 unit)">
                                            <option value="Gel">Gel</option>
                                            <option value="Cream">Cream / Ointment</option>
                                            <option value="Toothpaste">Toothpaste</option>
                                            <option value="Brush">Brush / Applicator</option>
                                            <option value="Spray">Spray</option>
                                        </optgroup>
                                        <optgroup label="Injectable / Other">
                                            <option value="Injection">Injection</option>
                                            <option value="Other">Other</option>
                                        </optgroup>
                                    </select>

                                    {{-- Drug typeahead (after form type is chosen) --}}
                                    <div class="relative" x-data="{ open: false }">
                                        <input
                                            type="text"
                                            x-model="row.drug_name"
                                            @input.debounce.300ms="searchDrug(row, $event.target.value)"
                                            @focus="if(row.drug_name.length > 1) row._suggestions = row._cachedSuggestions || []"
                                            @blur.prevent="setTimeout(() => { row._suggestions = [] }, 250)"
                                            :placeholder="'Search ' + (row.dosage_form || 'drug') + '…'"
                                            class="df-input pr-8 font-medium"
                                            autocomplete="off"
                                        >
                                        {{-- Loading spinner --}}
                                        <span x-show="row._searching" class="absolute right-2 top-2.5">
                                            <svg class="animate-spin h-4 w-4 text-brand-400" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                            </svg>
                                        </span>
                                        {{-- Dropdown suggestions --}}
                                        <div x-show="row._suggestions && row._suggestions.length > 0"
                                             class="absolute z-50 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-64 overflow-y-auto">
                                            <template x-for="drug in row._suggestions" :key="drug.id">
                                                <button type="button"
                                                        @click="selectDrug(row, drug)"
                                                        class="w-full text-left px-3 py-2.5 hover:bg-brand-50 border-b border-gray-50 last:border-0">
                                                    <div class="font-medium text-sm text-gray-800" x-text="drug.brand_name"></div>
                                                    <div class="text-xs text-gray-400 flex gap-2 mt-0.5">
                                                        <span x-text="drug.generic_name"></span>
                                                        <span x-show="drug.strength" x-text="'· ' + drug.strength"></span>
                                                        <span x-show="drug.dosage_form" x-text="'· ' + drug.dosage_form"></span>
                                                    </div>
                                                </button>
                                            </template>
                                            {{-- Add new drug link --}}
                                            <a href="{{ route('rx.drugs.create') }}" target="_blank"
                                               class="flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-red-600 bg-red-50 hover:bg-red-100 border-t border-red-100 transition">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5"
                                                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                                                    <path d="M12 5v14M5 12h14"/>
                                                </svg>
                                                Add New Drug to Master
                                            </a>
                                        </div>
                                    </div>
                                </div>

                                {{-- Dose row: SOS | Morn | Noon | Night | Duration | Total — matches panel column order --}}
                                <div class="grid grid-cols-[auto_1fr_1fr_1fr_1.8fr_1fr_auto] gap-2 items-center">

                                    {{-- SOS toggle (first, like panel) --}}
                                    <div class="flex flex-col items-center gap-0.5 w-10">
                                        <label class="text-xs text-gray-400 md:hidden">SOS</label>
                                        <button type="button"
                                                @click="row.is_sos = !row.is_sos"
                                                :class="row.is_sos ? 'bg-amber-400 text-white' : 'bg-gray-200 text-gray-500'"
                                                class="w-9 h-7 rounded text-xs font-bold transition-colors">
                                            <span x-text="row.is_sos ? '✓' : 'SOS'"></span>
                                        </button>
                                    </div>

                                    {{-- Morning --}}
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-0.5 md:hidden">Morn</label>
                                        <input type="number" x-model="row.morning" min="0" step="0.25"
                                               @change="recalcQty(row); debouncedCdss()"
                                               placeholder="0" class="df-input text-center text-sm">
                                    </div>

                                    {{-- Afternoon (labelled Noon to match panel) --}}
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-0.5 md:hidden">Noon</label>
                                        <input type="number" x-model="row.afternoon" min="0" step="0.25"
                                               @change="recalcQty(row); debouncedCdss()"
                                               placeholder="0" class="df-input text-center text-sm">
                                    </div>

                                    {{-- Night --}}
                                    <div>
                                        <label class="block text-xs text-gray-400 mb-0.5 md:hidden">Night</label>
                                        <input type="number" x-model="row.night" min="0" step="0.25"
                                               @change="recalcQty(row); debouncedCdss()"
                                               placeholder="0" class="df-input text-center text-sm">
                                    </div>

                                    {{-- Duration: number + unit --}}
                                    <div class="flex gap-1">
                                        <input type="number" x-model="row.duration" min="1"
                                               @change="recalcQty(row); debouncedCdss()"
                                               placeholder="5" class="df-input w-16 text-sm text-center">
                                        <select x-model="row.duration_unit"
                                                @change="recalcQty(row); debouncedCdss()"
                                                class="df-input flex-1 text-sm">
                                            <option value="days">days</option>
                                            <option value="weeks">wks</option>
                                            <option value="months">mo</option>
                                        </select>
                                    </div>

                                    {{-- Total / Qty (auto / pack / manual) --}}
                                    <div class="flex flex-col items-stretch gap-0.5">
                                        <div class="flex items-center gap-1">
                                            <input type="number" x-model="row.quantity" min="0"
                                                   @focus="if(row.dispensing_type !== 'pack' && row.dispensing_type !== 'manual') row.quantity_manual = true"
                                                   :class="qtyStyle(row)"
                                                   class="df-input w-full text-sm text-center"
                                                   :title="row.dispensing_type === 'manual' ? 'Enter quantity manually' : (row.dispensing_type === 'pack' ? 'Pack quantity (default 1)' : 'Auto-calculated. Click to override.')">
                                            <button type="button" x-show="showQtyReset(row)"
                                                    @click="resetQty(row)"
                                                    title="Reset to default"
                                                    class="text-gray-400 hover:text-red-500 text-lg leading-none shrink-0">↺</button>
                                        </div>
                                        {{-- Unit label below qty --}}
                                        <span class="text-center text-xs text-gray-400 leading-none"
                                              x-text="qtyLabel(row)"></span>
                                    </div>

                                    {{-- Delete row --}}
                                    <button type="button" @click="removeRow(idx)"
                                            class="text-gray-300 hover:text-red-500 transition p-1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>

                                {{-- Safety indicators + duplicate molecule warning --}}
                                <div class="flex flex-wrap items-center gap-1.5 mt-1.5"
                                     x-show="row.pregnancy_category || row.is_controlled || rowIsDuplicate(row)">

                                    {{-- Pregnancy category badge --}}
                                    <span x-show="row.pregnancy_category"
                                          :class="pregnancyCategoryClass(row.pregnancy_category)"
                                          :title="'Pregnancy category ' + row.pregnancy_category">
                                        Preg <span x-text="row.pregnancy_category"></span>
                                    </span>

                                    {{-- Controlled drug badge --}}
                                    <span x-show="row.is_controlled"
                                          class="border border-orange-300 bg-orange-50 text-orange-700 text-xs font-semibold px-1.5 py-0.5 rounded"
                                          title="Controlled substance — handle with care">
                                        Controlled
                                    </span>

                                    {{-- Duplicate molecule warning --}}
                                    <span x-show="rowIsDuplicate(row)"
                                          class="border border-amber-400 bg-amber-50 text-amber-800 text-xs font-semibold px-1.5 py-0.5 rounded"
                                          title="Same molecule group already in this prescription">
                                        Duplicate molecule
                                    </span>
                                </div>

                                {{-- Instructions (collapsible) --}}
                                <div class="mt-2">
                                    <textarea x-model="row.instructions"
                                              rows="1"
                                              placeholder="Special instructions for this drug…"
                                              class="df-input text-xs text-gray-600 resize-none w-full"></textarea>
                                </div>

                                {{-- Hidden inputs submitted with form --}}
                                <input type="hidden" :name="`items[${idx}][drug_id]`"       :value="row.drug_id">
                                <input type="hidden" :name="`items[${idx}][drug_name]`"     :value="row.drug_name">
                                <input type="hidden" :name="`items[${idx}][generic_name]`"  :value="row.generic_name">
                                <input type="hidden" :name="`items[${idx}][strength]`"      :value="row.strength">
                                <input type="hidden" :name="`items[${idx}][dosage_form]`"   :value="row.dosage_form">
                                <input type="hidden" :name="`items[${idx}][route]`"         :value="row.route">
                                <input type="hidden" :name="`items[${idx}][morning]`"       :value="row.morning">
                                <input type="hidden" :name="`items[${idx}][afternoon]`"     :value="row.afternoon">
                                <input type="hidden" :name="`items[${idx}][night]`"         :value="row.night">
                                <input type="hidden" :name="`items[${idx}][is_sos]`"        :value="row.is_sos ? '1' : '0'">
                                <input type="hidden" :name="`items[${idx}][duration]`"      :value="row.duration">
                                <input type="hidden" :name="`items[${idx}][duration_unit]`" :value="row.duration_unit">
                                <input type="hidden" :name="`items[${idx}][quantity]`"      :value="row.quantity">
                                <input type="hidden" :name="`items[${idx}][quantity_manual]`" :value="row.quantity_manual ? '1' : '0'">
                                <input type="hidden" :name="`items[${idx}][food_advice]`"   :value="row.food_advice">
                                <input type="hidden" :name="`items[${idx}][instructions]`"  :value="row.instructions">
                                <input type="hidden" :name="`items[${idx}][sort_order]`"    :value="idx">
                                <input type="hidden" :name="`items[${idx}][duplicate_molecule_group]`" :value="row.duplicate_molecule_group">
                                <input type="hidden" :name="`items[${idx}][antibiotic_class]`"      :value="row.antibiotic_class">
                                <input type="hidden" :name="`items[${idx}][max_daily_dose]`"        :value="row.max_daily_dose">
                                <input type="hidden" :name="`items[${idx}][dispensing_type]`"       :value="row.dispensing_type">
                                <input type="hidden" :name="`items[${idx}][pregnancy_category]`"    :value="row.pregnancy_category">
                                <input type="hidden" :name="`items[${idx}][is_controlled]`"         :value="row.is_controlled ? '1' : '0'">

                            </div>
                        </template>

                        {{-- Empty state --}}
                        <div x-show="rows.length === 0"
                             class="text-center py-10 text-gray-400 text-sm border-2 border-dashed border-red-100 rounded-lg">
                            No medications added yet.
                            <button type="button" @click="addRow()" class="text-red-600 hover:underline ml-1">Add first drug →</button>
                        </div>
                    </div>

                    {{-- Patient Instruction Chips — same as visit panel --}}
                    <div class="mt-5 pt-4 border-t border-red-100">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-3">Patient Instructions</p>
                        <div class="flex flex-wrap gap-2 mb-3" x-data="{ selected: {{ json_encode(old('_instruction_chips', [])) }} }">
                            @foreach([
                                'Avoid hard/crunchy food for 24 hrs',
                                'Do not rinse vigorously',
                                'Keep the area clean',
                                'Use warm saline rinse',
                                'Apply ice pack for swelling',
                                'Avoid alcohol & smoking',
                                'Complete the full course of antibiotics',
                                'Return if bleeding does not stop',
                                'Avoid brushing near the area',
                                'Take medications as prescribed',
                            ] as $chip)
                            <button type="button"
                                    @click="
                                        const i = selected.indexOf('{{ $chip }}');
                                        i === -1 ? selected.push('{{ $chip }}') : selected.splice(i, 1);
                                        $refs.instrField.value = selected.join('; ');
                                    "
                                    :class="selected.includes('{{ $chip }}')
                                        ? 'bg-red-50 border-red-400 text-red-700 font-semibold'
                                        : 'bg-white border-gray-200 text-gray-500 hover:border-red-300 hover:text-red-600'"
                                    class="px-3 py-1.5 rounded-full text-xs border transition">
                                {{ $chip }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    </div>{{-- /p-5 --}}
                </div>{{-- /prescription card --}}

            </div>{{-- /left column --}}

            {{-- ════════════════════════════════════════════
                 RIGHT COLUMN  (1/3)  CDSS + summary
            ════════════════════════════════════════════ --}}
            <div class="space-y-4">

                {{-- ── Patient Allergy Banner ── --}}
                @if($patient->medical_alert)
                <div class="bg-red-50 border border-red-200 rounded-xl p-4 text-sm">
                    <div class="font-semibold text-red-700 mb-1 flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                        Medical Alert
                    </div>
                    <p class="text-red-600 text-xs leading-relaxed">{{ $patient->medical_alert }}</p>
                </div>
                @endif

                {{-- ── CDSS Alerts Panel ── --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-700">CDSS Alerts</h3>
                        <span x-show="cdssLoading" class="text-xs text-gray-400 flex items-center gap-1">
                            <svg class="animate-spin h-3 w-3" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                            </svg>
                            Checking…
                        </span>
                        <span x-show="!cdssLoading && alerts.length === 0"
                              class="text-xs text-green-600 font-medium">✓ No alerts</span>
                        <span x-show="!cdssLoading && alerts.length > 0"
                              class="text-xs font-semibold"
                              :class="hasBlockingAlerts ? 'text-red-600' : 'text-amber-600'"
                              x-text="alerts.length + ' alert' + (alerts.length > 1 ? 's' : '')"></span>
                    </div>

                    {{-- Alert list --}}
                    <div class="space-y-2">
                        <template x-for="(alert, ai) in alerts" :key="ai">
                            <div class="rounded-lg p-3 text-xs"
                                 :class="{
                                     'bg-red-50 border border-red-200':    alert.severity === 'critical' || alert.severity === 'major',
                                     'bg-amber-50 border border-amber-200': alert.severity === 'moderate',
                                     'bg-blue-50 border border-blue-100':  alert.severity === 'info' || alert.severity === 'minor',
                                 }">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <span class="font-semibold uppercase tracking-wide text-xs"
                                              :class="{
                                                  'text-red-700':    alert.severity === 'critical' || alert.severity === 'major',
                                                  'text-amber-700':  alert.severity === 'moderate',
                                                  'text-blue-700':   alert.severity === 'info' || alert.severity === 'minor',
                                              }"
                                              x-text="alert.type + ' · ' + alert.severity"></span>
                                        <p class="mt-0.5 leading-relaxed" x-text="alert.message"></p>
                                    </div>
                                    {{-- Acknowledge button --}}
                                    <button type="button"
                                            x-show="!isAcknowledged(alert)"
                                            @click="acknowledge(alert)"
                                            class="shrink-0 text-xs px-2 py-1 rounded bg-white border border-current opacity-70 hover:opacity-100 transition whitespace-nowrap"
                                            :class="{
                                                'text-red-600 border-red-300':    alert.severity === 'critical' || alert.severity === 'major',
                                                'text-amber-600 border-amber-300': alert.severity === 'moderate',
                                                'text-blue-600 border-blue-300':  alert.severity === 'info',
                                            }">
                                        Override
                                    </button>
                                    <span x-show="isAcknowledged(alert)"
                                          class="shrink-0 text-xs px-2 py-1 rounded bg-green-100 text-green-700 whitespace-nowrap">
                                        ✓ Ack'd
                                    </span>
                                </div>
                            </div>
                        </template>

                        <p x-show="alerts.length === 0 && !cdssLoading"
                           class="text-xs text-gray-400 text-center py-4">
                            Alerts will appear here as you add drugs.
                        </p>
                    </div>

                    {{-- Blocking alert notice --}}
                    <div x-show="hasBlockingAlerts && !allBlockingAcknowledged"
                         class="mt-3 p-2 bg-red-100 rounded-lg text-xs text-red-700 font-medium">
                        You must acknowledge all critical alerts before finalizing.
                    </div>
                </div>

                {{-- ── Rx Summary ── --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-3">Summary</h3>
                    <dl class="space-y-1.5 text-xs">
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Drugs</dt>
                            <dd class="font-medium" x-text="rows.length"></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Antibiotics</dt>
                            <dd class="font-medium" x-text="rows.filter(r => r.antibiotic_class).length"></dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500">Total items</dt>
                            <dd class="font-medium" x-text="rows.reduce((s, r) => s + (parseInt(r.quantity) || 0), 0)"></dd>
                        </div>
                    </dl>
                </div>

                {{-- ── Action Buttons ── --}}
                <div class="space-y-2">
                    {{-- Save Draft --}}
                    <button type="button"
                            @click="finalizeOnSave = false; $el.closest('form') || document.getElementById('rx-form').submit()"
                            @click.prevent="finalizeOnSave = false; submitForm()"
                            class="w-full py-2.5 px-4 rounded-xl border border-gray-300 text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                        Save as Draft
                    </button>

                    {{-- Finalize --}}
                    <button type="button"
                            @click.prevent="finalizeOnSave = true; submitForm()"
                            :disabled="hasBlockingAlerts && !allBlockingAcknowledged"
                            :class="(hasBlockingAlerts && !allBlockingAcknowledged)
                                ? 'opacity-50 cursor-not-allowed bg-brand-300'
                                : 'bg-brand-600 hover:bg-brand-700'"
                            class="w-full py-2.5 px-4 rounded-xl text-sm font-semibold text-white transition">
                        Finalize Prescription
                    </button>

                    <p x-show="hasBlockingAlerts && !allBlockingAcknowledged"
                       class="text-xs text-center text-red-500">
                        Acknowledge all critical alerts to finalize.
                    </p>
                </div>

            </div>{{-- /right column --}}

        </div>{{-- /grid --}}

        {{-- ── Override Acknowledgement Modal ── --}}
        <div x-show="overrideModal.open"
             x-transition
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
             @keydown.escape.window="overrideModal.open = false">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-6" @click.stop>
                <h3 class="text-base font-semibold text-gray-800 mb-1">Override CDSS Alert</h3>
                <p class="text-sm text-gray-500 mb-4" x-text="overrideModal.alert?.message"></p>

                <label class="block text-xs font-medium text-gray-600 mb-1.5">Clinical reason for override <span class="text-red-500">*</span></label>
                <textarea x-model="overrideModal.reason" rows="3"
                          placeholder="e.g. Patient has taken this before without issues. Benefit outweighs risk…"
                          class="df-input resize-none w-full mb-4"></textarea>

                <div class="flex gap-3">
                    <button type="button" @click="overrideModal.open = false"
                            class="flex-1 py-2 border border-gray-200 rounded-xl text-sm text-gray-600 hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="button" @click="confirmOverride()"
                            :disabled="!overrideModal.reason.trim()"
                            class="flex-1 py-2 bg-red-600 text-white rounded-xl text-sm font-medium hover:bg-red-700 transition disabled:opacity-50">
                        Confirm Override
                    </button>
                </div>
            </div>
        </div>

    </form>
</div>

{{-- ── Tailwind utility classes shared in this form ── --}}
<style>
    .df-input {
        @apply w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400 bg-white;
    }
</style>
@endsection

@section('head-extra')
<script>
/**
 * rxForm — Alpine.js component for the prescription write pad.
 *
 * State:
 *   rows[]               — drug items on the prescription
 *   alerts[]             — CDSS alerts from the server
 *   acknowledgedOverrides[] — user-confirmed override objects
 *   finalizeOnSave       — boolean flag for the submit button
 *   overrideModal        — state for the acknowledge dialog
 *   cdssLoading          — spinner flag
 */
function rxForm({ patientId, checkUrl, searchUrl, csrfToken, existing }) {

    let _cdssTimer  = null;
    let _rowCounter = 0;

    function blankRow() {
        return {
            _id:                    ++_rowCounter,
            drug_id:                null,
            drug_name:              '',
            generic_name:           '',
            strength:               '',
            dosage_form:            '',
            route:                  '',
            morning:                0,
            afternoon:              0,
            night:                  0,
            is_sos:                 false,
            duration:               5,
            duration_unit:          'days',
            quantity:               0,
            quantity_manual:        false,
            food_advice:            '',
            instructions:           '',
            duplicate_molecule_group: null,
            antibiotic_class:       null,
            max_daily_dose:         null,
            // Dispensing
            dispensing_type:        'unit',
            unit_label:             'tab',
            // Safety
            pregnancy_category:     null,
            is_controlled:          false,
            // UI-only
            _suggestions:           [],
            _cachedSuggestions:     [],
            _searching:             false,
        };
    }

    /**
     * Auto-calculate dispensed quantity based on dispensing type.
     * Returns null for pack/manual types → recalcQty will leave quantity unchanged.
     *   unit   (Tablet/Capsule/Drops) → freq × days
     *   volume (Syrup/Suspension)     → freq × days  (same; unit is ml)
     *   pack   (Gel/Mouthwash/Tube)   → null — defaults to 1 on drug select, user-editable
     *   manual (Injection/LA)         → null — user must enter; never auto-set
     */
    function calcQty(row) {
        const type = row.dispensing_type || 'unit';
        if (type === 'pack' || type === 'manual') return null;

        const daily    = (parseFloat(row.morning) || 0)
                       + (parseFloat(row.afternoon) || 0)
                       + (parseFloat(row.night) || 0);
        let   duration = parseInt(row.duration) || 0;
        if (row.duration_unit === 'weeks')  duration *= 7;
        if (row.duration_unit === 'months') duration *= 30;
        return Math.ceil(daily * duration) || 0;
    }

    return {
        rows:                [],
        alerts:              [],
        acknowledgedOverrides: [],
        finalizeOnSave:      false,
        cdssLoading:         false,
        overrideModal:       { open: false, alert: null, reason: '' },

        // ── Computed ──────────────────────────────────────────────────────────

        get hasBlockingAlerts() {
            return this.alerts.some(a => a.blockable && !this.isAcknowledged(a));
        },
        get allBlockingAcknowledged() {
            return this.alerts
                .filter(a => a.blockable)
                .every(a => this.isAcknowledged(a));
        },

        // ── Lifecycle ─────────────────────────────────────────────────────────

        init() {
            // Load existing items (edit mode)
            if (existing && existing.length > 0) {
                this.rows = existing.map(item => ({
                    ...blankRow(),
                    drug_id:                item.drug_id,
                    drug_name:              item.drug_name,
                    generic_name:           item.generic_name || '',
                    strength:               item.strength || '',
                    dosage_form:            item.dosage_form || '',
                    route:                  item.route || '',
                    morning:                item.morning || 0,
                    afternoon:              item.afternoon || 0,
                    night:                  item.night || 0,
                    is_sos:                 !!item.is_sos,
                    duration:               item.duration || 5,
                    duration_unit:          item.duration_unit || 'days',
                    quantity:               item.quantity || 0,
                    quantity_manual:        !!item.quantity_manual,
                    food_advice:            item.food_advice || '',
                    instructions:           item.instructions || '',
                    // Dispensing (snapshotted on save)
                    dispensing_type:        item.dispensing_type || 'unit',
                    unit_label:             item.unit_label || 'tab',
                    // Safety (not stored on item — re-populated on next drug select)
                    pregnancy_category:     item.pregnancy_category || null,
                    is_controlled:          !!item.is_controlled,
                }));
                this.$nextTick(() => this.runCdss());
            }
        },

        // ── Row management ────────────────────────────────────────────────────

        addRow() {
            this.rows.push(blankRow());
        },

        removeRow(idx) {
            this.rows.splice(idx, 1);
            this.debouncedCdss();
        },

        // ── Drug search ───────────────────────────────────────────────────────

        async searchDrug(row, term) {
            if (term.length < 2) { row._suggestions = []; return; }
            row._searching = true;
            try {
                const res  = await fetch(`${searchUrl}?q=${encodeURIComponent(term)}`, {
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });
                const data = await res.json();
                row._suggestions        = data;
                row._cachedSuggestions  = data;
            } catch (e) {
                console.error('Drug search error', e);
            } finally {
                row._searching = false;
            }
        },

        selectDrug(row, drug) {
            row.drug_id                   = drug.id;
            row.drug_name                 = drug.brand_name;
            row.generic_name              = drug.generic_name || '';
            row.strength                  = drug.strength || '';
            row.dosage_form               = drug.dosage_form || '';
            row.route                     = drug.route || '';
            row.food_advice               = drug.food_advice || '';
            row.instructions              = drug.default_instructions || '';
            row.duplicate_molecule_group  = drug.duplicate_molecule_group || null;
            row.antibiotic_class          = drug.antibiotic_class || null;
            row.max_daily_dose            = drug.max_daily_dose || null;
            // Dispensing
            row.dispensing_type           = drug.dispensing_type || 'unit';
            row.unit_label                = drug.unit_label || 'tab';
            // Safety
            row.pregnancy_category        = drug.pregnancy_category || null;
            row.is_controlled             = !!drug.is_controlled;

            // Prefill defaults
            if (drug.default_duration) {
                row.duration      = drug.default_duration;
                row.duration_unit = drug.default_duration_unit || 'days';
            }

            // Parse default_dose e.g. "1-0-1" → morning=1, afternoon=0, night=1
            if (drug.default_dose) {
                const parts = String(drug.default_dose).split('-');
                if (parts.length === 3) {
                    row.morning   = parseFloat(parts[0]) || 0;
                    row.afternoon = parseFloat(parts[1]) || 0;
                    row.night     = parseFloat(parts[2]) || 0;
                }
            }

            // Set initial quantity based on dispensing type
            row.quantity_manual = false;
            if (row.dispensing_type === 'pack') {
                row.quantity = 1;                // default 1 tube / 1 bottle
            } else if (row.dispensing_type === 'manual') {
                row.quantity        = 0;         // dentist must enter
                row.quantity_manual = true;      // force amber "enter manually" style
            } else {
                this.recalcQty(row);             // unit / volume: auto-calc
            }

            row._suggestions = [];
            this.debouncedCdss();
        },

        // ── Form type → dispensing_type mapper ───────────────────────────────
        // Called when user manually changes the Form Type select.
        // Keeps dispensing_type in sync so calcQty works correctly.

        applyFormType(row) {
            const f = (row.dosage_form || '').toLowerCase();
            const packForms = ['gel','cream','ointment','toothpaste','brush','applicator','mouthwash','rinse','spray'];
            const manualForms = ['injection'];
            const volumeForms = ['syrup','suspension','drops'];

            if (packForms.some(p => f.includes(p))) {
                row.dispensing_type  = 'pack';
                row.unit_label       = f.includes('mouthwash') || f.includes('rinse') ? 'bottle' : 'tube';
                row.quantity         = 1;
                row.quantity_manual  = false;
            } else if (manualForms.some(p => f.includes(p))) {
                row.dispensing_type  = 'manual';
                row.unit_label       = 'vial';
                row.quantity_manual  = true;
            } else if (volumeForms.some(p => f.includes(p))) {
                row.dispensing_type  = 'volume';
                row.unit_label       = 'ml';
                this.recalcQty(row);
            } else {
                row.dispensing_type  = 'unit';
                row.unit_label       = f.includes('capsule') ? 'cap' : 'tab';
                this.recalcQty(row);
            }
        },

        // ── Quantity calculator ───────────────────────────────────────────────

        recalcQty(row) {
            if (row.quantity_manual) return;
            const q = calcQty(row);
            if (q !== null) row.quantity = q;
        },

        // ── CDSS ─────────────────────────────────────────────────────────────

        debouncedCdss() {
            clearTimeout(_cdssTimer);
            _cdssTimer = setTimeout(() => this.runCdss(), 600);
        },

        async runCdss() {
            const items = this.rows
                .filter(r => r.drug_id || r.drug_name)
                .map(r => ({
                    drug_id:          r.drug_id,
                    drug_name:        r.drug_name,
                    morning:          r.morning,
                    afternoon:        r.afternoon,
                    night:            r.night,
                    duration:         r.duration,
                    duration_unit:    r.duration_unit,
                    duplicate_molecule_group: r.duplicate_molecule_group,
                    antibiotic_class: r.antibiotic_class,
                    max_daily_dose:   r.max_daily_dose,
                }));

            if (items.length === 0) { this.alerts = []; return; }

            this.cdssLoading = true;
            try {
                const res  = await fetch(checkUrl, {
                    method:  'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept':       'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({ patient_id: patientId, items }),
                });
                const data = await res.json();
                this.alerts = data.alerts || [];

                // Remove stale acknowledgements for alerts that no longer exist
                const newCodes = this.alerts.map(a => a.code).filter(Boolean);
                this.acknowledgedOverrides = this.acknowledgedOverrides.filter(
                    o => newCodes.includes(o.alert_code)
                );
            } catch (e) {
                console.error('CDSS check error', e);
            } finally {
                this.cdssLoading = false;
            }
        },

        rowHasAlert(row) {
            return this.alerts.some(a => a.drug_id && a.drug_id === row.drug_id);
        },

        // ── Override acknowledgement ──────────────────────────────────────────

        isAcknowledged(alert) {
            return this.acknowledgedOverrides.some(o => o.alert_code === alert.code);
        },

        acknowledge(alert) {
            this.overrideModal = { open: true, alert, reason: '' };
        },

        confirmOverride() {
            const alert = this.overrideModal.alert;
            if (!alert || !this.overrideModal.reason.trim()) return;

            this.acknowledgedOverrides.push({
                drug_id:         alert.drug_id,
                alert_type:      alert.type,
                alert_code:      alert.code,
                alert_message:   alert.message,
                override_reason: this.overrideModal.reason.trim(),
            });

            this.overrideModal = { open: false, alert: null, reason: '' };
        },

        // ── Repeat detection ─────────────────────────────────────────────────

        /**
         * True if another row shares the same duplicate_molecule_group.
         * Triggers the "Duplicate molecule" warning pill on the row.
         */
        rowIsDuplicate(row) {
            if (!row.duplicate_molecule_group) return false;
            return this.rows.some(r =>
                r._id !== row._id &&
                r.duplicate_molecule_group === row.duplicate_molecule_group
            );
        },

        // ── Safety indicator helpers ──────────────────────────────────────────

        /**
         * Tailwind badge classes for pregnancy category A–X.
         * A/B = safe (green), C = caution (amber), D/X = avoid (red).
         */
        pregnancyCategoryClass(cat) {
            const map = {
                A: 'bg-green-100 text-green-700 border-green-200',
                B: 'bg-green-100 text-green-700 border-green-200',
                C: 'bg-amber-100 text-amber-700 border-amber-200',
                D: 'bg-red-100 text-red-700 border-red-200',
                X: 'bg-red-200 text-red-800 border-red-300',
                N: 'bg-gray-100 text-gray-600 border-gray-200',
            };
            return (map[cat] || 'bg-gray-100 text-gray-600 border-gray-200') + ' border text-xs font-semibold px-1.5 py-0.5 rounded';
        },

        /**
         * Border/background classes for the quantity input.
         *   pack   → blue-tinted  (fixed default)
         *   manual → always amber (must enter)
         *   unit/volume auto      → green (calculated)
         *   unit/volume override  → amber (manually overridden)
         */
        qtyStyle(row) {
            if (row.dispensing_type === 'pack')   return 'bg-blue-50 border-blue-300';
            if (row.dispensing_type === 'manual') return 'bg-amber-50 border-amber-400';
            return row.quantity_manual ? 'bg-amber-50 border-amber-300' : 'bg-green-50 border-green-200';
        },

        /** Short unit label shown below the qty input (tab, ml, pack, …). */
        qtyLabel(row) {
            if (row.dispensing_type === 'pack')   return row.unit_label || 'pack';
            if (row.dispensing_type === 'volume') return 'ml';
            if (row.dispensing_type === 'manual') return row.unit_label || '—';
            return row.unit_label || 'tab';
        },

        /** Show the ↺ reset button for unit/volume overrides; pack resets to 1. */
        showQtyReset(row) {
            if (row.dispensing_type === 'pack')   return row.quantity !== 1;
            if (row.dispensing_type === 'manual') return false;
            return row.quantity_manual;
        },

        /** Reset qty back to its type-default (auto-calc for unit; 1 for pack). */
        resetQty(row) {
            row.quantity_manual = false;
            if (row.dispensing_type === 'pack') {
                row.quantity = 1;
            } else {
                this.recalcQty(row);
            }
        },

        // ── Form submit ───────────────────────────────────────────────────────

        submitForm() {
            if (this.hasBlockingAlerts && !this.allBlockingAcknowledged) {
                alert('Please acknowledge all critical CDSS alerts before proceeding.');
                return;
            }
            document.getElementById('rx-form').submit();
        },
    };
}
</script>
@endsection
