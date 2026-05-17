{{--
    partials/diagnosis.blade.php
    Section 9 — Diagnosis
    Alpine state: form.diagnosis.{primary, secondary, risk, notes}
--}}
@php
    $saved = $consultation
        ? (is_array($consultation->diagnosis_data) ? $consultation->diagnosis_data : json_decode($consultation->diagnosis_data ?? '{}', true))
        : [];
@endphp

<div class="c-card" x-data="{open: {{ !empty($saved['primary'] ?? '') ? 'true' : 'false' }}}">
    <div class="c-card-head" @click="open=!open">
        <span class="sec-label"><span class="sec-num">9</span>Diagnosis</span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span class="sec-summary" x-show="!open && form.diagnosis.primary" x-cloak
                  x-text="form.diagnosis.primary.substring(0,50)+(form.diagnosis.primary.length>50?'…':'')"></span>
            <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </div>
    </div>

    <div x-show="open" x-collapse style="padding:18px;display:grid;grid-template-columns:1fr 1fr;gap:14px;">

        <div>
            <label class="df-label">Primary Diagnosis <span class="req">*</span></label>
            <textarea name="primary_diagnosis"
                      x-model="form.diagnosis.primary"
                      class="df-input"
                      rows="3"
                      placeholder="e.g. Early Caries #14, 15, 46 with Dentin Hypersensitivity">{{ $saved['primary'] ?? '' }}</textarea>
        </div>

        <div>
            <label class="df-label">Secondary Diagnosis</label>
            <textarea name="secondary_diagnosis"
                      x-model="form.diagnosis.secondary"
                      class="df-input"
                      rows="3"
                      placeholder="e.g. Mild Chronic Gingivitis">{{ $saved['secondary'] ?? '' }}</textarea>
        </div>

        <div>
            <label class="df-label">Risk Assessment</label>
            <select name="risk_assessment" x-model="form.diagnosis.risk" class="df-input">
                <option value="">Select</option>
                @foreach(['Low Risk','Moderate Risk','High Risk','Very High Risk'] as $r)
                    <option {{ ($saved['risk'] ?? '') === $r ? 'selected' : '' }}>{{ $r }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="df-label">Notes</label>
            <textarea name="diagnosis_notes"
                      x-model="form.diagnosis.notes"
                      class="df-input"
                      rows="3"
                      placeholder="Clinical reasoning, patient concerns…">{{ $saved['notes'] ?? '' }}</textarea>
        </div>
    </div>
</div>
