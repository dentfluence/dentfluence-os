{{--
    partials/radiographic-findings.blade.php
    Section 7 — Radiographic Findings
    Alpine state: form.radio.{opg, iopa, cbct, notes}
--}}
@php
    $saved = $consultation
        ? (is_array($consultation->radio_data) ? $consultation->radio_data : json_decode($consultation->radio_data ?? '{}', true))
        : [];
    $hasContent = !empty(array_filter($saved ?? []));
@endphp

<div class="c-card" x-data="{open: {{ $hasContent ? 'true' : 'false' }}}">
    <div class="c-card-head" @click="open=!open">
        <span class="sec-label"><span class="sec-num">7</span>Radiographic Findings</span>
        <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </div>

    <div x-show="open" x-collapse style="padding:14px 18px;display:grid;grid-template-columns:1fr 1fr;gap:12px;">

        @foreach(['opg' => 'OPG Findings', 'iopa' => 'IOPA Findings', 'cbct' => 'CBCT Findings', 'notes' => 'Interpretation'] as $k => $lbl)
        <div>
            <label class="df-label" style="margin-bottom:2px;">{{ $lbl }}</label>
            <textarea name="radio_{{ $k }}"
                      x-model="form.radio.{{ $k }}"
                      class="df-input"
                      rows="3"
                      style="resize:vertical;"
                      placeholder="{{ $k === 'cbct' ? 'N/A' : 'Enter findings…' }}">{{ $saved[$k] ?? '' }}</textarea>
        </div>
        @endforeach

    </div>
</div>
