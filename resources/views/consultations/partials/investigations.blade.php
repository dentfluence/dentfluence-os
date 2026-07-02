{{--
    partials/investigations.blade.php  —  Section 5 (Simplified)
    Layout: dropdown checklist (left) + notes textarea (right)
    Selected items show as removable pills below.
    Styles live in create.blade.php — do NOT add <style> block here.
--}}
@php
    $savedInv = $consultation
        ? (is_array($consultation->investigations)
            ? $consultation->investigations
            : json_decode($consultation->investigations ?? '[]', true))
        : [];
    $savedDet = $consultation
        ? (is_array($consultation->investigation_details)
            ? $consultation->investigation_details
            : json_decode($consultation->investigation_details ?? '{}', true))
        : [];
    $savedInv = $savedInv ?? [];
    $savedDet = $savedDet ?? [];

    // All investigation options: [key, short label, description]
    $allInv = [
        // — Radiographic —
        ['iopa',        'IOPA',         'Periapical X-ray'],
        ['opg',         'OPG',          'Panoramic X-ray'],
        ['cbct',        'CBCT',         'Cone Beam CT'],
        ['rvg',         'RVG',          'Radiovisiography'],
        ['ceph',        'Lat. Ceph.',   'Cephalogram'],
        // — Blood & Lab —
        ['cbc',         'CBC',          'Complete Blood Count'],
        ['blood_sugar', 'Blood Sugar',  'FBS / RBS / PPBS'],
        ['hba1c',       'HbA1c',        'Glycated Haemoglobin'],
        ['pt_inr',      'PT / INR',     'Bleeding & Clotting'],
        ['hiv',         'HIV',          'HIV I & II'],
        ['hbsag',       'HBsAg',        'Hepatitis B'],
        ['thyroid',     'Thyroid',      'T3 / T4 / TSH'],
        ['lab_custom',  'Other Lab',    'Custom Test'],
        // — Other Imaging —
        ['mri',         'MRI',          'MR Imaging'],
        ['usg',         'USG',          'Ultrasonography'],
        ['ct',          'CT Scan',      'CT Scan'],
        ['biopsy',      'Biopsy',       'Tissue Biopsy / FNAC'],
        ['sialography', 'Sialography',  'Salivary Gland Study'],
    ];

    // Build a JS-friendly key→label map for pill display
    $invLabels = collect($allInv)->mapWithKeys(fn($i) => [$i[0] => $i[1]])->toJson();
@endphp

{{-- x-data merges invLabels map into this card's scope --}}
<div class="c-card" x-data="{ open: true, ddOpen: false, invLabels: {{ $invLabels }} }">

    {{-- ── Card Header ── --}}
    <div class="c-card-head" @click="open = !open" style="cursor:pointer;">
        <span class="sec-label">
            <span class="sec-num">5</span>Investigations
        </span>
        <div style="display:flex;align-items:center;gap:8px;">
            {{-- Summary count when collapsed --}}
            <span class="sec-summary"
                  x-show="!open && form.investigations && form.investigations.length"
                  x-cloak
                  x-text="(form.investigations ? form.investigations.length : 0) + ' advised'"></span>
            {{-- AI assist button --}}
            <button type="button"
                    @click.stop="$dispatch('cip-assist', {section:'investigations',label:'Investigations'})"
                    title="Get section guidance"
                    style="font-size:12px;background:transparent;border:none;cursor:pointer;
                           padding:2px 4px;border-radius:3px;line-height:1;opacity:.55;transition:opacity .15s;"
                    onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='.55'">✨</button>
            {{-- Chevron --}}
            <svg :class="open ? 'sec-chevron open' : 'sec-chevron'"
                 width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round">
                <path d="m6 9 6 6 6-6"/>
            </svg>
        </div>
    </div>

    {{-- ── Body ── --}}
    <div x-show="open" style="padding:14px 16px;">

        {{-- Row: dropdown trigger (left) + notes textarea (right) --}}
        <div class="inv-simple-wrap">

            {{-- LEFT — Dropdown checklist --}}
            <div class="inv-dd-wrap" @click.outside="ddOpen = false">

                {{-- Trigger button --}}
                <button type="button" class="inv-dd-btn" @click.stop="ddOpen = !ddOpen">
                    {{-- stethoscope-ish icon --}}
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M2 12a10 10 0 0 0 10 10 10 10 0 0 0 10-10"/>
                        <path d="M12 22v-4"/>
                        <circle cx="12" cy="12" r="3"/>
                    </svg>
                    Select Investigations
                    <span class="inv-dd-count"
                          x-show="form.investigations && form.investigations.length > 0"
                          x-cloak
                          x-text="'(' + form.investigations.length + ')'"></span>
                    <svg class="inv-dd-chevron" :class="ddOpen ? 'open' : ''"
                         width="12" height="12" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2.5"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </button>

                {{-- Dropdown panel --}}
                <div x-show="ddOpen" class="inv-dd-panel">

                    {{-- Section dividers inside the list --}}
                    <div style="padding:5px 14px 3px;font-size:9px;font-weight:800;
                                text-transform:uppercase;letter-spacing:.09em;color:#b95cb7;
                                font-family:'Inter',sans-serif;">Radiographic</div>

                    @foreach($allInv as $idx => [$key, $short, $desc])
                        {{-- Insert section headings --}}
                        @if($idx === 5)<div class="inv-dd-sep"></div>
                        <div style="padding:5px 14px 3px;font-size:9px;font-weight:800;
                                    text-transform:uppercase;letter-spacing:.09em;color:#b95cb7;
                                    font-family:'Inter',sans-serif;">Blood &amp; Lab</div>
                        @elseif($idx === 13)<div class="inv-dd-sep"></div>
                        <div style="padding:5px 14px 3px;font-size:9px;font-weight:800;
                                    text-transform:uppercase;letter-spacing:.09em;color:#b95cb7;
                                    font-family:'Inter',sans-serif;">Other Imaging</div>
                        @endif

                        <label class="inv-dd-item">
                            <input type="checkbox"
                                   id="inv_{{ $key }}" name="investigations[]"
                                   value="{{ $key }}"
                                   x-model="form.investigations"
                                   class="inv-dd-check"
                                   @if(in_array($key, (array)$savedInv)) checked @endif>
                            <span class="inv-dd-name">{{ $short }}</span>
                            <span class="inv-dd-desc">{{ $desc }}</span>
                        </label>
                    @endforeach

                </div>{{-- /dropdown panel --}}
            </div>{{-- /inv-dd-wrap --}}

            {{-- RIGHT — Single notes textarea --}}
            <textarea name="investigation_details[_notes]"
                      class="inv-notes-area"
                      placeholder="Notes / instructions to patient (e.g. IOPA of 36 before RCT, bring reports on next visit…)"
                      >{{ old('investigation_details._notes', $savedDet['_notes'] ?? '') }}</textarea>

        </div>{{-- /inv-simple-wrap --}}

        {{-- Selected investigation pills (shown below the row) --}}
        <template x-if="form.investigations && form.investigations.length > 0">
            <div class="inv-pills-wrap">
                <template x-for="k in form.investigations" :key="k">
                    <span class="inv-pill">
                        <span x-text="invLabels[k] || k"></span>
                        <button type="button" class="inv-pill-rm"
                                @click="form.investigations = form.investigations.filter(i => i !== k)"
                                title="Remove">×</button>
                    </span>
                </template>
            </div>
        </template>

    </div>{{-- /body --}}

</div>{{-- /c-card investigations --}}
