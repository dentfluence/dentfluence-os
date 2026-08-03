{{--
    partials/follow-up.blade.php
    Section 9 — Follow-up (Visit redesign, 2026-07-31)

    Closes the workflow gap identified in the 2026-07-31 audit: the target
    Consultation workflow (Chief Complaint → Clinical Examination →
    Investigations → Findings → Diagnosis → Prescription → Follow-up → Save)
    had no Follow-up step even though follow_up_date/follow_up_note already
    exist as columns on `consultations` (used elsewhere, e.g. Brain fields) —
    this just surfaces them here. No migration — reuses existing columns.

    Expected variables (already in scope from consultations/create.blade.php):
      $consultation   Consultation|null
--}}
{{-- 2026-07-31 UX Experiment #2 (Split Layout): gcol-r/o4 place this in the
     right column, visual position 4 — no change to the section itself. --}}
<div class="gcol-r o4" x-show="form.type && form.type !== 'coha' && form.type !== 'same_issue'" x-cloak>
<div class="c-card">

    <div class="c-card-head">
        <span class="c-head-label">Follow-up</span>
        <span style="font-size:10px;color:#9ca3af;font-family:'Inter',sans-serif;font-style:italic;">
            Optional — note only, no appointment is created
        </span>
    </div>

    <div class="c-body">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
            <div>
                <label class="df-label">Follow-up Date</label>
                <input type="date" name="follow_up_date" class="df-input"
                       value="{{ old('follow_up_date', $consultation?->follow_up_date?->format('Y-m-d')) }}">
            </div>
            <div>
                <label class="df-label">Follow-up Note</label>
                <input type="text" name="follow_up_note" class="df-input"
                       value="{{ old('follow_up_note', $consultation?->follow_up_note) }}"
                       placeholder="e.g. Review healing, start Phase 2 of treatment plan">
            </div>
        </div>
    </div>

</div>
</div>{{-- /follow-up section --}}
