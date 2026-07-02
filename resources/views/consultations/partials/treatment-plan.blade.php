{{--
    partials/treatment-plan.blade.php
    Section 11A + 11B — Treatment Plans (Best & Acceptable)
    Alpine state: tpBest[], tpAcceptable[], tpBestTotal, tpAcceptableTotal,
                  allTxSelected[], form.aocp_*
    Events dispatched: add-tp-row, remove-tp-row, recalc-tp, open-tp-picker
--}}
@php
    $aocpPlans = ['Silver — Rs. 3,999/yr', 'Gold — Rs. 5,999/yr', 'Platinum — Rs. 8,999/yr'];
@endphp

{{-- ── 11A: Best Options ── --}}
<div class="c-card" x-data="{open: false}">
    <div class="c-card-head">
        <span class="sec-label" @click="open=!open" style="flex:1;cursor:pointer;display:flex;align-items:center;gap:6px;">
            <span class="sec-num" style="font-size:8px;width:22px;">11A</span>Treatment Plan — Best Options
        </span>
        <div style="display:flex;align-items:center;gap:8px;">
            <button type="button" @click.stop="$dispatch('add-tp-row', {type:'best'})"
                    style="font-size:11px;color:#6a0f70;border:1px solid rgba(106,15,112,.25);padding:3px 10px;border-radius:3px;background:white;cursor:pointer;white-space:nowrap;">
                + Add Row
            </button>
            
            {{-- ✨ CIP ghost button --}}
            <button type="button"
                    @click.stop="$dispatch('cip-assist', {section:'treatment-plan',label:'Treatment Plan'})"
                    title="Get section guidance"
                    style="font-size:12px;background:transparent;border:none;cursor:pointer;padding:2px 4px;border-radius:3px;line-height:1;opacity:.55;transition:opacity .15s;"
                    onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.55'">✨</button>
            <svg class="sec-chevron" :class="open?'open':''" width="14" height="14" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 style="cursor:pointer;" @click="open=!open">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </div>
    </div>

    <div x-show="open" x-collapse>
        @include('consultations.partials._tp-table', ['planType' => 'best', 'ref' => 'tpBest', 'totalRef' => 'tpBestTotal'])

        {{-- AOCP --}}
        <div style="padding:10px 16px;border-top:1px solid #f3f4f6;">
            <div style="font-size:10px;font-weight:700;color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">AOCP (if applicable)</div>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                <input type="checkbox" id="aocp_best" x-model="form.aocp_best" style="width:13px;height:13px;accent-color:#6a0f70;">
                <label for="aocp_best" style="font-size:12px;color:#374151;cursor:pointer;">Include AOCP</label>
            </div>
            <div x-show="form.aocp_best" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;max-width:400px;">
                @foreach($aocpPlans as $plan)
                <div class="aocp-card" :class="form.aocp_best_plan==='{{ $plan }}' ? 'active' : ''"
                     @click="form.aocp_best_plan='{{ $plan }}'">
                    <div style="font-size:11px;font-weight:700;color:#374151;">{{ Str::before($plan, ' —') }}</div>
                    <div style="font-size:10px;color:#9ca3af;">{{ Str::after($plan, '— ') }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

{{-- ── 11B: Acceptable Options ── --}}
<div class="c-card" x-data="{open: false}">
    <div class="c-card-head">
        <span class="sec-label" @click="open=!open" style="flex:1;cursor:pointer;display:flex;align-items:center;gap:6px;">
            <span class="sec-num" style="font-size:8px;width:22px;">11B</span>Treatment Plan — Acceptable Options
        </span>
        <div style="display:flex;align-items:center;gap:8px;">
            <button type="button" @click.stop="$dispatch('add-tp-row', {type:'acceptable'})"
                    style="font-size:11px;color:#6a0f70;border:1px solid rgba(106,15,112,.25);padding:3px 10px;border-radius:3px;background:white;cursor:pointer;white-space:nowrap;">
                + Add Row
            </button>
            
            {{-- ✨ CIP ghost button --}}
            <button type="button"
                    @click.stop="$dispatch('cip-assist', {section:'treatment-plan',label:'Treatment Plan'})"
                    title="Get section guidance"
                    style="font-size:12px;background:transparent;border:none;cursor:pointer;padding:2px 4px;border-radius:3px;line-height:1;opacity:.55;transition:opacity .15s;"
                    onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.55'">✨</button>
            <svg class="sec-chevron" :class="open?'open':''" width="14" height="14" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                 style="cursor:pointer;" @click="open=!open">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </div>
    </div>

    <div x-show="open" x-collapse>
        @include('consultations.partials._tp-table', ['planType' => 'acceptable', 'ref' => 'tpAcceptable', 'totalRef' => 'tpAcceptableTotal'])

        {{-- AOCP --}}
        <div style="padding:10px 16px;border-top:1px solid #f3f4f6;">
            <div style="font-size:10px;font-weight:700;color:#6a0f70;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;">AOCP (if applicable)</div>
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:8px;">
                <input type="checkbox" id="aocp_acceptable" x-model="form.aocp_acceptable" style="width:13px;height:13px;accent-color:#6a0f70;">
                <label for="aocp_acceptable" style="font-size:12px;color:#374151;cursor:pointer;">Include AOCP</label>
            </div>
            <div x-show="form.aocp_acceptable" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;max-width:400px;">
                @foreach($aocpPlans as $plan)
                <div class="aocp-card" :class="form.aocp_acceptable_plan==='{{ $plan }}' ? 'active' : ''"
                     @click="form.aocp_acceptable_plan='{{ $plan }}'">
                    <div style="font-size:11px;font-weight:700;color:#374151;">{{ Str::before($plan, ' —') }}</div>
                    <div style="font-size:10px;color:#9ca3af;">{{ Str::after($plan, '— ') }}</div>
                </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
