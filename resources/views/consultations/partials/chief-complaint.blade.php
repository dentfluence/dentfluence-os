{{--
    partials/chief-complaint.blade.php
    Section 1 — Chief Complaint
    Parent scope: consultationForm() Alpine component
    $consultation nullable (null = create mode)
--}}
<div class="c-card" x-data="{open: {{ $consultation?->chief_complaint ? 'true' : 'false' }}}">
    <div class="c-card-head" @click="open=!open">
        <span class="sec-label">
            <span class="sec-num">1</span>Chief Complaint
        </span>
        <div style="display:flex;align-items:center;gap:8px;">
            <span class="sec-summary" x-show="!open && form.chief_complaint" x-cloak
                  x-text="form.chief_complaint.substring(0,50)+(form.chief_complaint.length>50?'…':'')"></span>
            
            {{-- ✨ CIP ghost button --}}
            <button type="button"
                    @click.stop="$dispatch('cip-assist', {section:'chief-complaint',label:'Chief Complaint'})"
                    title="Get section guidance"
                    style="font-size:12px;background:transparent;border:none;cursor:pointer;padding:2px 4px;border-radius:3px;line-height:1;opacity:.55;transition:opacity .15s;"
                    onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.55'">✨</button>
            <svg class="sec-chevron" :class="open?'open':''" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </div>
    </div>

    <div x-show="open" x-collapse style="padding:18px;display:flex;flex-direction:column;gap:14px;">

        {{-- Chief Complaint textarea --}}
        <div>
            <label class="df-label">Chief Complaint <span class="req">*</span></label>
            <textarea name="chief_complaint"
                      x-model="form.chief_complaint"
                      class="df-input"
                      rows="2"
                      placeholder="e.g. Sensitivity in upper and lower right back teeth since 3 days."></textarea>
        </div>

        {{-- Duration · Severity · Location · Tooth/Area --}}
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <div>
                <label class="df-label">Duration</label>
                <select name="complaint_duration" x-model="form.complaint_duration" class="df-input">
                    <option value="">Select</option>
                    @foreach(['1 Day','2 Days','3 Days','1 Week','2 Weeks','1 Month','3 Months','6 Months','1 Year','Chronic'] as $d)
                        <option {{ old('complaint_duration', $consultation?->complaint_duration) === $d ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="df-label">Severity</label>
                <div style="display:flex;gap:6px;margin-top:4px;">
                    @foreach(['Mild','Moderate','Severe'] as $sev)
                        <button type="button"
                                @click="form.severity='{{ $sev }}'"
                                :class="form.severity==='{{ $sev }}' ? 'sev-{{ strtolower($sev) }}' : ''"
                                class="sev-btn"
                                style="flex:1;">{{ $sev }}</button>
                    @endforeach
                </div>
            </div>

            

            <div>
                <label class="df-label">Tooth / Area</label>
                <div style="display:flex;gap:4px;">
                    <input type="text"
                           name="tooth_area"
                           x-model="form.tooth_area"
                           class="df-input"
                           placeholder="#14, 15, 46"
                           style="flex:1;"
                           readonly
                           @click="$dispatch('open-tooth-picker')">
                    <button type="button"
                            @click="$dispatch('open-tooth-picker')"
                            style="border:1px solid #e5e7eb;border-radius:5px;padding:0 10px;background:white;cursor:pointer;color:#6a0f70;font-size:11px;font-weight:600;white-space:nowrap;transition:all .15s;"
                            onmouseover="this.style.background='#faf5fb'" onmouseout="this.style.background='white'">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7" rx="1"/>
                            <rect x="14" y="3" width="7" height="7" rx="1"/>
                            <rect x="3" y="14" width="7" height="7" rx="1"/>
                            <rect x="14" y="14" width="7" height="7" rx="1"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        {{-- Notes --}}
        <div>
            <label class="df-label">Notes (optional)</label>
            <textarea name="complaint_notes"
                      x-model="form.complaint_notes"
                      class="df-input"
                      rows="2"
                      placeholder="Additional context about the complaint…"></textarea>
        </div>
    </div>
</div>
