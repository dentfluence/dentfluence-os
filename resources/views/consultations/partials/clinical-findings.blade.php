{{--
    partials/clinical-findings.blade.php
    Section 6 — Clinical Findings
    Alpine state: form.clinical.{field} object
--}}
@php
$clinFields = [
    'soft_tissue'         => ['Soft Tissue', ['Normal','Mild Gingival Inflammation','Moderate Gingival Inflammation','Severe Gingival Inflammation','Ulceration','Swelling','Stomatitis','Other']],
    'caries'              => ['Caries', ['None','Caries #14,15,46','Multiple Caries','Deep Caries (Pulp)','Secondary Caries','Caries All Quads','Other']],
    'periodontal'         => ['Periodontal', ['Normal','Mild Gingivitis','Moderate Gingivitis','Severe Gingivitis','Mild Periodontitis','Moderate Periodontitis','Severe Periodontitis']],
    'bleeding_on_probing' => ['Bleeding on Probing', ['Absent','Present','Generalised']],
    'plaque_index'        => ['Plaque Index', ['Good','Fair','Moderate','Poor']],
    'occlusion'           => ['Occlusion', ['Class I','Class II Div 1','Class II Div 2','Class III','Edge to Edge','Cross Bite']],
    'tmj'                 => ['TMJ', ['Normal','No Clicking / No Pain','Clicking','Pain on Opening','Restricted Opening','TMD']],
    'existing_condition'  => ['Existing Condition', ['None','Composite Filling','Amalgam Filling','PFM Crown','Zirconia Crown','Bridge (PFM)','Bridge (Zirconia)','Implant','Implant + Crown','RCT Done','Stainless Steel Crown','Denture','Orthodontic Brackets','Other']],
    'oral_hygiene'        => ['Oral Hygiene', ['Excellent','Good','Fair','Poor']],
];
$saved = ($consultation ?? null)
    ? (is_array($consultation->clinical_data) ? $consultation->clinical_data : json_decode($consultation->clinical_data ?? '{}', true))
    : [];
@endphp

<div class="c-card" x-data="{open: {{ !empty(array_filter($saved ?? [])) ? 'true' : 'false' }}}">
    <div class="c-card-head" @click="open=!open">
        <span class="sec-label"><span class="sec-num">6</span>Clinical Findings</span>
        <div style="display:flex;align-items:center;gap:8px;">
            <button type="button"
                    @click.stop="document.getElementById('tooth-chart-modal').style.display='flex'"
                    style="font-size:10px;color:#6a0f70;border:1px solid rgba(106,15,112,.25);padding:3px 9px;border-radius:3px;background:white;cursor:pointer;display:flex;align-items:center;gap:4px;">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
                    <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
                </svg>
                Chart Teeth
            </button>
            
            {{-- ✨ CIP ghost button --}}
            <button type="button"
                    @click.stop="$dispatch('cip-assist', {section:'clinical-findings',label:'Clinical Findings'})"
                    title="Get section guidance"
                    style="font-size:12px;background:transparent;border:none;cursor:pointer;padding:2px 4px;border-radius:3px;line-height:1;opacity:.55;transition:opacity .15s;"
                    onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.55'">✨</button>
            <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </div>
    </div>

    <div x-show="open" x-collapse style="padding:14px 18px;">

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            @foreach($clinFields as $fld => [$lbl, $opts])
            <div>
                <label class="df-label" style="margin-bottom:2px;">{{ $lbl }}</label>
                <select name="clinical_{{ $fld }}"
                        x-model="form.clinical.{{ $fld }}"
                        class="df-input"
                        style="padding:6px 8px;font-size:13px;"
                        @change="if($event.target.value==='__add__'){var v=prompt('Enter custom {{ $lbl }}:');if(v&&v.trim()){form.clinical.{{ $fld }}=v.trim();}else{form.clinical.{{ $fld }}='';}}">
                    <option value="">Select</option>
                    @foreach($opts as $o)
                        <option {{ ($saved[$fld] ?? '') === $o ? 'selected' : '' }}>{{ $o }}</option>
                    @endforeach
                    <option value="__add__">+ Add custom</option>
                </select>
            </div>
            @endforeach
        </div>

        <div style="margin-top:12px;">
            <label class="df-label">Additional Notes</label>
            <textarea name="clinical_notes"
                      x-model="form.clinical.notes"
                      class="df-input"
                      rows="2"
                      placeholder="e.g. Generalised sensitivity, tartar deposits in lower anteriors.">{{ $saved['notes'] ?? '' }}</textarea>
        </div>

        {{-- Tooth chart summary (populated by archChartClick JS) --}}
        <div id="clinical-chart-summary-wrap" style="margin-top:12px;display:none;">
            <div style="padding:8px 12px;background:#faf5fb;border:1px solid rgba(106,15,112,.15);border-radius:6px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                    <span style="font-size:10px;font-weight:700;color:#6a0f70;text-transform:uppercase;letter-spacing:.05em;">Tooth Charting</span>
                    <button type="button"
                            onclick="document.getElementById('tooth-chart-modal').style.display='flex'"
                            style="font-size:10px;color:#6a0f70;background:none;border:none;cursor:pointer;font-weight:600;">Edit Chart →</button>
                </div>
                <div id="clinical-chart-summary" style="font-size:11px;color:#374151;line-height:1.8;"></div>
            </div>
        </div>
    </div>
</div>
