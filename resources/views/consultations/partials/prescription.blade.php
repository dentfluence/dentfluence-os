{{--
    partials/prescription.blade.php
    Section 7 — Prescription (embedded, 2026-07-31 CEO directive)

    Reuses the exact same <x-prescription-panel> component, drug typeahead,
    and PrescriptionQuickSaveService the standalone Prescriptions module uses
    — this is a second entry point into the same engine, not a rewrite. See
    ConsultationController::store()/update() for the save side.

    Expected variables (already in scope from consultations/create.blade.php):
      $patient             Patient
      $consultation         Consultation|null — null for a brand-new consultation
      $linkedPrescription   Prescription|null  — the Rx already tied to this
                             consultation, if editing one that has an Rx
--}}
@php
    $rx = $linkedPrescription ?? null;

    $panelValue = $rx?->exists
        ? $rx->items->map(function ($item) {
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

    // general_instructions was saved as chips joined with "; " + a note on a
    // second line — split back out so the chips can re-highlight on edit.
    $savedInstr = $rx?->general_instructions ?? '';
    $instrLines = explode("\n", $savedInstr, 2);
    $chipsPart  = $instrLines[0] ?? '';
    $notePart   = $instrLines[1] ?? (str_contains($chipsPart, ';') ? '' : $chipsPart);
@endphp

{{-- 2026-07-31 UX Experiment #2 (Split Layout): gcol-r/o2 place this in the
     right column, visual position 2 — no change to the panel itself. --}}
<div class="gcol-r o2" x-show="form.type && form.type !== 'coha' && form.type !== 'same_issue'" x-cloak>
<div class="c-card">

    <div class="c-card-head">
        <span class="c-head-label">Prescription</span>
        <span style="font-size:10px;color:#9ca3af;font-family:'Inter',sans-serif;font-style:italic;">
            Optional — same Rx pad as the Prescriptions tab
        </span>
    </div>

    <div class="c-body">
        <x-prescription-panel
            prefix="prescriptions_data"
            note-field="rx_general_instructions"
            instruct-field="instructions_data"
            :value="$panelValue"
            :note-value="$rx?->exists ? $notePart : ''"
            :instruct-value="$rx?->exists ? array_filter(explode('; ', $chipsPart)) : []"
            :collapsible="false"
            :start-open="true"
        />

        @if($rx?->exists)
        <p style="margin-top:10px;font-size:10px;color:#9ca3af;font-family:'Inter',sans-serif;">
            Editing {{ $rx->prescription_number }} — saving this consultation updates it in place.
            Print, PDF, and WhatsApp are on the patient's
            <a href="{{ route('patients.show', $patient) }}#prescriptions" style="color:#6a0f70;">Prescriptions tab</a>.
        </p>
        @endif
    </div>

</div>
</div>{{-- /prescription section --}}
