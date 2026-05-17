{{--
    partials/visit-type.blade.php
    Section 2 — Visit Type
    Requires: $patient (for medical_alert banner), $consultation nullable
--}}
<div class="c-card" x-data="{open: {{ $consultation?->visit_type ? 'true' : 'false' }}}">
    <div class="c-card-head" @click="open=!open">
        <span class="sec-label"><span class="sec-num">2</span>Visit Type</span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span class="sec-summary" x-show="!open && form.visit_type" x-cloak
                  x-text="form.visit_type ? form.visit_type.charAt(0).toUpperCase()+form.visit_type.slice(1) : ''"></span>
            <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </div>
    </div>

    <div x-show="open" x-collapse style="padding:18px;">

        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;max-width:560px;">
            @foreach([
                ['emergency', '#dc2626', '#fef2f2', 'Pain / Swelling / Trauma',        'M21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3ZM12 9v4M12 17h.01'],
                ['routine',   '#6a0f70', '#f5f3ff', 'Full Mouth Evaluation',            'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
                ['followup',  '#2563eb', '#eff6ff', 'Review / Re-evaluation',           'M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2zM9 22V12h6v10'],
            ] as [$vt, $col, $bg, $sub, $path])
            <div class="vt-card"
                 :class="form.visit_type==='{{ $vt }}' ? 'sel-{{ $vt }}' : ''"
                 @click="form.visit_type='{{ $vt }}'">
                <div class="vt-icon" :style="form.visit_type==='{{ $vt }}' ? 'background:{{ $bg }}' : 'background:#f3f4f6'">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none"
                         :stroke="form.visit_type==='{{ $vt }}' ? '{{ $col }}' : '#9ca3af'"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="{{ $path }}"/>
                    </svg>
                </div>
                <div style="font-size:11px;font-weight:700;"
                     :style="form.visit_type==='{{ $vt }}' ? 'color:{{ $col }}' : 'color:#6b7280'">
                    {{ ucfirst($vt === 'routine' ? 'Routine / Comprehensive' : $vt) }}
                </div>
                <div style="font-size:10px;color:#9ca3af;margin-top:2px;">{{ $sub }}</div>
                <div class="vt-check">
                    <svg x-show="form.visit_type==='{{ $vt }}'" width="9" height="9" viewBox="0 0 24 24"
                         fill="none" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
            </div>
            @endforeach
        </div>

        <input type="hidden" name="visit_type" x-model="form.visit_type">

        @if($patient->medical_alert)
        <div style="margin-top:12px;display:flex;align-items:flex-start;gap:6px;padding:8px 12px;background:#fef2f2;border:1px solid #fecaca;border-radius:5px;font-size:11px;color:#dc2626;">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                <path d="M12 9v4"/><path d="M12 17h.01"/>
            </svg>
            <span><strong>Alert:</strong> {{ $patient->medical_alert }}</span>
        </div>
        @endif
    </div>
</div>
