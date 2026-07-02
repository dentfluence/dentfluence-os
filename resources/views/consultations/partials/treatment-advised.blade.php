{{--
    partials/treatment-advised.blade.php
    Section 10 — Treatment Advised
    Alpine state: txSelected.{emergency,protective,transformative}[], txTeeth.{}
    Actions: addTx(), removeTx(), openTxTeethPicker()
--}}
@php
    $emergencyTx     = ['Pain Relief / Stabilization','RCT (if needed)','Extraction (if needed)','Temporary Filling','I&D Abscess','Pulpotomy','Splinting'];
    $protectiveTx    = ['Scaling & Polishing','Fluoride Therapy','Restorations (Fillings)','RCT','Crown / Onlay','Gum Treatment','Composite Filling','GIC Filling'];
    $transformativeTx= ['Veneers','Crowns / Bridges','Implants','Aligners','Smile Design','Full Mouth Rehab','Teeth Whitening','Gum Contouring'];
@endphp

<div class="c-card" x-data="{open: false}">
    <div class="c-card-head" @click="open=!open">
        <span class="sec-label">
            <span class="sec-num">10</span>Treatment Advised
            <span style="font-size:9px;color:#9ca3af;font-weight:400;text-transform:none;letter-spacing:0;">Select → appears in treatment plan</span>
        </span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span class="sec-summary" x-show="!open && allTxSelected.length" x-cloak
                  x-text="allTxSelected.length + ' treatment(s)'"></span>
            
            {{-- ✨ CIP ghost button --}}
            <button type="button"
                    @click.stop="$dispatch('cip-assist', {section:'treatment-advised',label:'Treatment Advised'})"
                    title="Get section guidance"
                    style="font-size:12px;background:transparent;border:none;cursor:pointer;padding:2px 4px;border-radius:3px;line-height:1;opacity:.55;transition:opacity .15s;"
                    onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.55'">✨</button>
            <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </div>
    </div>

    <div x-show="open" x-collapse style="padding:18px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;">

        {{-- ── Emergency ── --}}
        @include('consultations.partials._tx-column', [
            'col'   => 'emergency',
            'label' => 'Emergency',
            'color' => '#dc2626',
            'borderColor' => '#fecaca',
            'options' => $emergencyTx,
        ])

        {{-- ── Protective ── --}}
        @include('consultations.partials._tx-column', [
            'col'   => 'protective',
            'label' => 'Protective',
            'color' => '#2563eb',
            'borderColor' => '#bfdbfe',
            'options' => $protectiveTx,
        ])

        {{-- ── Transformative ── --}}
        @include('consultations.partials._tx-column', [
            'col'   => 'transformative',
            'label' => 'Transformative',
            'color' => '#6a0f70',
            'borderColor' => '#e9d5ff',
            'options' => $transformativeTx,
        ])

    </div>
</div>
