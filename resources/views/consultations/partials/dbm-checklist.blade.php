{{--
    partials/dbm-checklist.blade.php
    Section 8 — DBM 35-Point Checklist
    Uses vanilla JS (dbmSet / dbmYN / dbmUpdateScore) defined in create.blade.php.
    Alpine state: x-data="{open:false}" (standalone — no parent model bindings needed here).
--}}
<div class="c-card" x-data="{open: {{ $consultation?->dbm_checklist ? 'true' : 'false' }}}">
    <div class="c-card-head" @click="open=!open">
        <span class="sec-label"><span class="sec-num">8</span>DBM 35-Point Checklist</span>
        <div style="display:flex;align-items:center;gap:10px;">

            {{-- Progress bar --}}
            <div style="display:flex;align-items:center;gap:6px;">
                <div style="width:80px;height:3px;background:#f3f4f6;border-radius:99px;overflow:hidden;">
                    <div id="dbm-prog-fill" style="height:100%;width:0%;background:#6a0f70;border-radius:99px;transition:width .3s;"></div>
                </div>
                <span id="dbm-score-badge" style="font-size:11px;font-weight:700;color:#6a0f70;">0/33</span>
            </div>

            {{-- Shade + Whitening (inline, stopPropagation so card doesn't toggle) --}}
            <div style="display:flex;align-items:center;gap:6px;padding:4px 10px;background:#f9fafb;border-radius:5px;" @click.stop>
                <span style="font-size:10px;color:#9ca3af;font-weight:600;">Shade</span>
                <input type="text" name="dbm_tooth_shade"
                       value="{{ old('dbm_tooth_shade', $consultation?->dbm_tooth_shade) }}"
                       placeholder="A2"
                       style="width:40px;border:1px solid #e5e7eb;border-radius:4px;padding:2px 5px;font-size:11px;outline:none;">
                <span style="font-size:10px;color:#9ca3af;font-weight:600;margin-left:4px;">Whitening</span>
                <button type="button" onclick="event.stopPropagation();dbmYN(this,'whitening','Y')"
                        style="padding:1px 7px;border:1.5px solid #e5e7eb;border-radius:3px;font-size:10px;font-weight:700;cursor:pointer;background:white;">Y</button>
                <button type="button" onclick="event.stopPropagation();dbmYN(this,'whitening','N')"
                        style="padding:1px 7px;border:1.5px solid #e5e7eb;border-radius:3px;font-size:10px;font-weight:700;cursor:pointer;background:white;">N</button>
            </div>

            
            {{-- ✨ CIP ghost button --}}
            <button type="button"
                    @click.stop="$dispatch('cip-assist', {section:'dbm-checklist',label:'DBM Checklist'})"
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
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr 1fr;gap:16px;">

            {{-- Col 1: Hard / Soft Tissue --}}
            <div>
                <div class="dbm-sec-head">Hard / Soft Tissue</div>
                <div style="display:grid;grid-template-columns:1fr 38px 38px;font-size:9px;color:#9ca3af;font-weight:600;text-align:center;padding:2px 4px;margin-bottom:2px;">
                    <span></span><span>Healthy</span><span>Observe</span>
                </div>
                @foreach([[0,'Jaw joints'],[1,'Glands'],[2,'Muscles'],[3,'Cheek tissue'],[4,'Tongue'],[5,'Floor of mouth'],[6,'Roof of mouth'],[7,'Oral cancer'],[8,'Lip muscle']] as [$i, $l])
                <div class="dbm-row" style="grid-template-columns:1fr 30px 30px;" id="dbmr-{{ $i }}">
                    <span>{{ $l }}</span>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'healthy',this)"></div></div>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'observe',this)"></div></div>
                </div>
                @endforeach
            </div>

            {{-- Col 2: Gum Assessment --}}
            <div>
                <div class="dbm-sec-head">Gum Assessment</div>
                <div class="dbm-hdr"><span></span><span>Y</span><span>N</span><span>N/A</span></div>
                @foreach([[9,'Gum score done'],[10,'Gum recession present'],[11,'Hygiene referral discussed']] as [$i, $l])
                <div class="dbm-row" id="dbmr-{{ $i }}">
                    <span>{{ $l }}</span>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'yes',this)"></div></div>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'no',this)"></div></div>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'na',this)"></div></div>
                </div>
                @endforeach
            </div>

            {{-- Col 3: X-Rays & Photos --}}
            <div>
                <div class="dbm-sec-head">X-Rays &amp; Photos</div>
                <div class="dbm-hdr"><span></span><span>Y</span><span>N</span><span>N/A</span></div>
                @foreach([[12,'Routine X-rays'],[13,'Special X-rays'],[14,'Full mouth scan'],[15,'Photos taken']] as [$i, $l])
                <div class="dbm-row" id="dbmr-{{ $i }}">
                    <span>{{ $l }}</span>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'yes',this)"></div></div>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'no',this)"></div></div>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'na',this)"></div></div>
                </div>
                @endforeach
            </div>

            {{-- Col 4: Digital Base Records --}}
            <div>
                <div class="dbm-sec-head">Digital Base Records</div>
                <div class="dbm-hdr"><span></span><span>P</span><span>A</span><span>N/A</span></div>
                @foreach([[16,'Missing teeth'],[17,'Silver fillings'],[18,'White fillings'],[19,'Crowns / Bridge'],[20,'Veneers'],[21,'Implants'],[22,'Crowding'],[23,'Chipped teeth'],[24,'Dentures'],[25,'Decayed teeth']] as [$i, $l])
                <div class="dbm-row" id="dbmr-{{ $i }}">
                    <span>{{ $l }}</span>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'present',this)"></div></div>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'absent',this)"></div></div>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'na',this)"></div></div>
                </div>
                @endforeach
                <div style="display:flex;align-items:center;justify-content:space-between;padding:3px 4px;margin-top:2px;background:#f9fafb;border-radius:4px;font-size:10px;">
                    <span style="color:#374151;">Tooth monitored</span>
                    <div style="display:flex;gap:3px;">
                        <button type="button" onclick="dbmYN(this,'tooth_monitored','Y')"
                                style="padding:1px 6px;border:1.5px solid #e5e7eb;border-radius:3px;font-size:10px;font-weight:700;cursor:pointer;background:white;">Y</button>
                        <button type="button" onclick="dbmYN(this,'tooth_monitored','N')"
                                style="padding:1px 6px;border:1.5px solid #e5e7eb;border-radius:3px;font-size:10px;font-weight:700;cursor:pointer;background:white;">N</button>
                    </div>
                </div>
            </div>

            {{-- Col 5: Treatment Required --}}
            <div>
                <div class="dbm-sec-head">Treatment Required</div>
                <div class="dbm-hdr"><span></span><span>Y</span><span>N</span><span>N/A</span></div>
                @foreach([[26,'Fillings'],[27,'Crowns / Bridges'],[28,'Root Canal'],[29,'Extractions'],[30,'Replacement'],[31,'Cosmetic'],[32,'Braces']] as [$i, $l])
                <div class="dbm-row" id="dbmr-{{ $i }}">
                    <span>{{ $l }}</span>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'yes',this)"></div></div>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'no',this)"></div></div>
                    <div class="dbm-dot-wrap"><div class="dbm-dot" onclick="dbmSet({{ $i }},'na',this)"></div></div>
                </div>
                @endforeach
            </div>

        </div>

        {{-- Hidden field to persist dbm_checklist JSON on submit --}}
        <input type="hidden" name="dbm_checklist" id="dbm-checklist-input" value="{{ old('dbm_checklist', $consultation?->dbm_checklist) }}">
        <input type="hidden" name="dbm_tooth_monitored" id="dbm-tooth-monitored-input">
    </div>
</div>
