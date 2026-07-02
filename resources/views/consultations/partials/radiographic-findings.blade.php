{{--
    partials/radiographic-findings.blade.php
    Section 7 — Radiographic Findings
    Alpine state: form.radio.{type, findings}
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
        
            {{-- ✨ CIP ghost button --}}
            <button type="button"
                    @click.stop="$dispatch('cip-assist', {section:'radiographic-findings',label:'Radiographic Findings'})"
                    title="Get section guidance"
                    style="font-size:12px;background:transparent;border:none;cursor:pointer;padding:2px 4px;border-radius:3px;line-height:1;opacity:.55;transition:opacity .15s;"
                    onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.55'">✨</button>
            <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </div>

    <div x-show="open" x-collapse style="padding:14px 18px;display:flex;flex-direction:column;gap:12px;">

        {{-- Radiograph Type --}}
        <div>
            <label class="df-label">Type of Radiograph</label>
            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:4px;">
                @foreach(['OPG','IOPA','CBCT','RVG','Lateral Ceph','None'] as $rtype)
                <button type="button"
                        @click="form.radio.type='{{ $rtype }}'"
                        :class="form.radio.type==='{{ $rtype }}'
                            ? 'border-[#6a0f70] bg-[#f5eef9] text-[#6a0f70] font-bold'
                            : 'border-gray-200 text-gray-500'"
                        class="px-3 py-1.5 text-xs border rounded-full transition-colors cursor-pointer"
                        style="font-size:12px;font-weight:600;padding:5px 14px;border-radius:99px;border:1.5px solid #e5e7eb;background:white;cursor:pointer;transition:all .15s;"
                        :style="form.radio.type==='{{ $rtype }}'
                            ? 'border-color:#6a0f70;background:#f5eef9;color:#6a0f70;'
                            : 'border-color:#e5e7eb;background:white;color:#6b7280;'">
                    {{ $rtype }}
                </button>
                @endforeach
            </div>
            <input type="hidden" name="radio_type" x-model="form.radio.type">
        </div>

        {{-- Findings --}}
        <div>
            <label class="df-label">Findings</label>
            <textarea name="radio_findings"
                      x-model="form.radio.findings"
                      class="df-input"
                      rows="4"
                      style="resize:vertical;"
                      placeholder="e.g. Periapical infection present on 36, cavity reaching pulp, bone loss on 46…">{{ $saved['findings'] ?? ($saved['iopa'] ?? $saved['opg'] ?? '') }}</textarea>
        </div>

    </div>
</div>