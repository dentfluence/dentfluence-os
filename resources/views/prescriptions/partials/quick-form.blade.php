{{--
    resources/views/prescriptions/partials/quick-form.blade.php

    Shared prescription create/edit form. One implementation, three call sites:
      - "+ New Prescription" toggle in the patient's Prescriptions tab
      - each row's inline "Edit" toggle in that same tab
      - the standalone page (prescriptions/form.blade.php) for entry points
        outside the tab ("Write Prescription" quick action, "from Treatment Visit")

    Expected variables:
      $patient      Patient
      $prescription Prescription|null   — null/new for create, existing for edit
      $formAction   string              — route() URL to submit to
      $formMethod   'POST'|'PUT'
      $cancelUrl    string|null         — if given, Cancel navigates there (standalone
                                           page); if null, Cancel just closes the inline
                                           toggle via the enclosing Alpine `activeForm`.
--}}
@php
    $rx = $prescription;

    $panelValue = $rx?->exists
        ? $rx->items->map(function ($item) {
            // Liquids carry their millilitre amount through to the panel;
            // solids collapse to a boolean so the checkbox re-highlights.
            $liquid = in_array(strtolower((string) $item->dosage_form), ['syrup', 'suspension', 'drops'], true);
            return [
                'drug'      => trim($item->drug_name . ($item->strength ? ' ' . $item->strength : '')),
                'drug_id'   => $item->drug_id,
                'form_type' => strtolower($item->dosage_form ?: 'tablet'),
                'food'      => $item->food_advice ?? '',
                'sos'       => (bool) $item->is_sos,
                'morn'      => $liquid ? (float) $item->morning   : ((float) $item->morning   > 0),
                'noon'      => $liquid ? (float) $item->afternoon : ((float) $item->afternoon > 0),
                'night'     => $liquid ? (float) $item->night     : ((float) $item->night     > 0),
                'duration'  => (string) $item->duration,
                'unit'      => $item->duration_unit ?? 'days',
            ];
        })->values()->toArray()
        : [];

    // General instructions were saved as free text (chips joined with "; " + a note,
    // separated by a newline) — split back out so the chips can re-highlight.
    $savedInstr = $rx->general_instructions ?? '';
    $instrLines = explode("\n", $savedInstr, 2);
    $chipsPart  = $instrLines[0] ?? '';
    $notePart   = $instrLines[1] ?? (str_contains($chipsPart, ';') ? '' : $chipsPart);
@endphp

<form method="POST" action="{{ $formAction }}">
    @csrf
    @if(($formMethod ?? 'POST') === 'PUT') @method('PUT') @endif

    <input type="hidden" name="visit_id" value="{{ $rx->visit_id ?? '' }}">
    <input type="hidden" name="consultation_id" value="{{ $rx->consultation_id ?? '' }}">

    {{-- Quick context: complaint + diagnosis + follow-up --}}
    <div class="grid grid-cols-2 gap-3 mb-3">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Chief Complaint <span class="text-gray-400 font-normal">(optional)</span></label>
            <input type="text" name="chief_complaint"
                   value="{{ old('chief_complaint', $rx->chief_complaint ?? '') }}"
                   placeholder="e.g. Post-extraction pain"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-red-300 bg-white">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Diagnosis <span class="text-gray-400 font-normal">(optional)</span></label>
            <input type="text" name="diagnosis"
                   value="{{ old('diagnosis', $rx->diagnosis ?? '') }}"
                   placeholder="e.g. Acute pulpitis"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-red-300 bg-white">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Weight <span class="text-gray-400 font-normal">(optional)</span></label>
            <div class="flex items-center gap-2">
                <input type="text" name="weight" maxlength="20"
                       value="{{ old('weight', $rx->weight ?? '') }}"
                       placeholder="e.g. 15"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-red-300 bg-white">
                <span class="text-xs text-gray-400 shrink-0">kg</span>
            </div>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Follow-up <span class="text-gray-400 font-normal">(optional)</span></label>
            <div class="flex items-center gap-2">
                <input type="date" name="follow_up_date"
                       value="{{ old('follow_up_date', $rx->follow_up_date ?? '') }}"
                       class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-red-300 bg-white">
                <span class="text-xs text-gray-400 shrink-0">or after</span>
                <input type="number" name="follow_up_after_days" min="1" max="365"
                       value="{{ old('follow_up_after_days', $rx->follow_up_after_days ?? '') }}"
                       placeholder="days"
                       class="w-20 border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-red-300 bg-white">
            </div>
            <p class="text-xs text-gray-400 mt-1">Note only — no appointment created</p>
        </div>
    </div>

    {{-- Universal prescription panel --}}
    <x-prescription-panel
        prefix="prescriptions_data"
        note-field="prescription_notes"
        instruct-field="instructions_data"
        :value="$panelValue"
        :note-value="$rx?->exists ? $notePart : ''"
        :instruct-value="$rx?->exists ? array_filter(explode('; ', $chipsPart)) : []"
        :collapsible="false"
        :start-open="true"
    />

    {{-- Optional pediatric syrup dose helper (advisory mg/kg → ml calculator) --}}
    @include('prescriptions.partials.pedo-dose-helper')

    {{-- Save button --}}
    <div class="mt-3 flex justify-end gap-2">
        @if($cancelUrl ?? null)
            <a href="{{ $cancelUrl }}"
               class="text-sm px-4 py-2 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition">
                Cancel
            </a>
        @else
            <button type="button" @click="activeForm = null"
                    class="text-sm px-4 py-2 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition">
                Cancel
            </button>
        @endif
        <button type="submit"
                dusk="rx-save"
                class="text-sm px-5 py-2 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition">
            {{ $rx?->exists ? 'Save Changes' : 'Save Prescription' }}
        </button>
    </div>
</form>
