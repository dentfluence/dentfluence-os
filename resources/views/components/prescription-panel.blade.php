{{--
    Universal Prescription Panel Component
    ───────────────────────────────────────
    Usage anywhere in the app:

        <x-prescription-panel />

        <x-prescription-panel
            prefix="prescriptions_data"
            note-field="prescription_notes"
            instruct-field="instructions_data"
            :value="$consultation->prescriptions ?? []"
            :note-value="$consultation->prescription_notes ?? ''"
            :instruct-value="$consultation->instructions ?? []"
        />

    Submission: emits JSON strings decoded by controller into prescriptions / instructions columns.
--}}

@props([
    'prefix'        => 'prescriptions',
    'noteField'     => 'prescription_notes',
    'instructField' => 'instructions',
    'value'         => [],
    'noteValue'     => '',
    'instructValue' => [],
    'collapsible'   => true,
    'startOpen'     => true,
])

@php
    // Normalise from old format [{drug,dose,frequency,duration}]
    // or new format [{drug,drug_id,sos,morn,noon,night,duration,unit}]
    $normalised = collect((array) $value)->map(function ($row) {
        if (!is_array($row)) return null;
        $formType = $row['form_type'] ?? 'tablet';
        // Liquids (syrup/suspension/drops) are dosed in ml — keep the number.
        // Everything else is a yes/no dose — keep the boolean.
        $liquid = in_array($formType, ['syrup', 'suspension', 'drops'], true);
        $dose = fn ($v) => $liquid ? (float) ($v ?? 0) : (bool) ($v ?? false);
        return [
            'drug'      => $row['drug']      ?? '',
            'drug_id'   => $row['drug_id']   ?? null,
            'form_type' => $formType,
            'food'      => $row['food']      ?? '',
            'sos'       => (bool) ($row['sos']   ?? false),
            'morn'      => $dose($row['morn']  ?? null),
            'noon'      => $dose($row['noon']  ?? null),
            'night'     => $dose($row['night'] ?? null),
            'duration'  => (string) ($row['duration'] ?? ''),
            'unit'      => $row['unit'] ?? 'days',
        ];
    })->filter()->values()->toArray();

    $drugsJson   = json_encode($normalised);
    $instrJson   = json_encode(array_values((array) $instructValue));
    $noteVal     = old($noteField, $noteValue);
@endphp

{{-- Scoped styles for this component --}}
<style>
.rx-panel { background:#fff; border:1px solid #fecaca; border-radius:8px; overflow:hidden; font-family:'Inter',sans-serif; }
.rx-panel-head { padding:11px 16px; background:#fff5f5; border-bottom:1px solid #fecaca; display:flex; align-items:center; justify-content:space-between; cursor:pointer; user-select:none; }
.rx-panel-head-label { display:flex; align-items:center; gap:8px; }
.rx-panel-head-title { font-size:11px; font-weight:700; color:#dc2626; letter-spacing:.05em; text-transform:uppercase; }
.rx-panel-head-count { font-size:10px; color:#9ca3af; font-weight:500; }
.rx-panel-body { padding:14px 16px; display:flex; flex-direction:column; gap:14px; }

/* Drug table */
.rx-table-header, .rx-table-row {
    display:grid;
    grid-template-columns: 1fr 46px 46px 46px 46px 80px 72px 46px 24px;
    gap:5px;
    align-items:center;
}
.rx-table-header { padding-bottom:6px; border-bottom:1px solid #f3f4f6; }
.rx-col-head { font-size:9px; font-weight:700; color:#9ca3af; text-transform:uppercase; letter-spacing:.06em; text-align:center; }
.rx-col-head:first-child { text-align:left; }
.rx-table-row { padding:4px 0; border-bottom:1px dashed #fce7e7; }
.rx-input {
    width:100%; border:1px solid #e5e7eb; border-radius:4px;
    padding:5px 7px; font-size:12px; font-family:'Inter',sans-serif;
    color:#111827; background:#fff; outline:none;
    transition:border-color .15s;
}
.rx-input:focus { border-color:#fca5a5; }
.rx-input-center { text-align:center; }
.rx-checkbox-wrap { display:flex; justify-content:center; align-items:center; }
.rx-total { text-align:center; font-size:12px; font-weight:700; color:#374151; }
.rx-remove-btn {
    display:flex; align-items:center; justify-content:center;
    background:none; border:none; cursor:pointer;
    color:#fca5a5; font-size:15px; line-height:1; padding:0;
    transition:color .12s;
}
.rx-remove-btn:hover { color:#dc2626; }
.rx-add-btn {
    margin-top:6px; width:100%;
    border:1.5px dashed #fca5a5; border-radius:6px;
    padding:8px; background:#fff; color:#dc2626;
    font-size:12px; font-weight:600; font-family:'Inter',sans-serif;
    cursor:pointer; display:flex; align-items:center; justify-content:center; gap:5px;
    transition:background .12s;
}
.rx-add-btn:hover { background:#fff5f5; }

/* Instruction chips */
.rx-instr-section-title { font-size:10px; font-weight:700; color:#6b7280; text-transform:uppercase; letter-spacing:.05em; margin-bottom:7px; }
.rx-instr-chips { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:10px; }
.rx-chip {
    padding:4px 11px; border-radius:99px;
    font-size:11px; font-weight:500; font-family:'Inter',sans-serif;
    border:1.5px solid #e5e7eb; background:#fff; color:#6b7280;
    cursor:pointer; transition:all .12s; line-height:1.5;
}
.rx-chip:hover { border-color:#fca5a5; color:#dc2626; background:#fff5f5; }
.rx-chip.rx-chip-on { background:#fef2f2; border-color:#fca5a5; color:#dc2626; font-weight:600; }

/* Notes textarea */
.rx-notes {
    width:100%; border:1px solid #e5e7eb; border-radius:5px;
    padding:8px 10px; font-size:12px; font-family:'Inter',sans-serif;
    color:#374151; outline:none; resize:vertical; min-height:56px;
    transition:border-color .15s;
}
.rx-notes:focus { border-color:#fca5a5; }
</style>

<div class="rx-panel"
    x-data="{
        open: {{ $startOpen ? 'true' : 'false' }},
        drugs: {{ $drugsJson }},
        selectedInstr: {{ $instrJson }},

        addDrug() {
            this.drugs.push({ drug:'', drug_id:null, form_type:'tablet', food:'', sos:false, morn:false, noon:false, night:false, duration:'', unit:'days' });
        },

        // Single-unit forms don't multiply by dose × duration
        isSingleUnit(row) {
            return ['gel','cream','mouthwash','rinse','brush','toothpaste','tube','lotion','ointment','spray'].includes(row.form_type);
        },
        // Liquids are dosed in millilitres — the time-of-day cells take ml, not a tick.
        isLiquid(row) {
            return ['syrup','suspension','drops'].includes(row.form_type);
        },
        // Keep dose values the right type when the form changes: booleans for
        // solids, numbers for liquids. Prevents a checkbox 'true' leaking into
        // an ml field (and vice-versa).
        onFormTypeChange(row) {
            const liquid = this.isLiquid(row);
            ['morn','noon','night'].forEach(k => {
                if (liquid) {
                    if (typeof row[k] === 'boolean') row[k] = '';
                } else {
                    if (typeof row[k] !== 'boolean') row[k] = (parseFloat(row[k]) || 0) > 0;
                }
            });
        },
        removeDrug(idx) { this.drugs.splice(idx, 1); },
        calcTotal(row) {
            if (this.isSingleUnit(row)) return 1;
            const duration = parseInt(row.duration) || 0;
            if (this.isLiquid(row)) {
                const mlPerDay = (parseFloat(row.morn) || 0) + (parseFloat(row.noon) || 0) + (parseFloat(row.night) || 0);
                return Math.round(mlPerDay * duration * 10) / 10;   // total ml to dispense
            }
            const doses = [row.sos, row.morn, row.noon, row.night].filter(Boolean).length;
            return doses * duration;
        },
        // Total column label — appends the ml suffix for liquids.
        totalLabel(row) {
            const t = this.calcTotal(row);
            if (t <= 0) return '';
            return this.isLiquid(row) ? (t + ' ml') : t;
        },
        toggleInstr(txt) {
            const i = this.selectedInstr.indexOf(txt);
            i === -1 ? this.selectedInstr.push(txt) : this.selectedInstr.splice(i, 1);
        },
        drugsJson() { return JSON.stringify(this.drugs); },
        instrJson() { return JSON.stringify(this.selectedInstr); },
    }">

    {{-- Header --}}
    <div class="rx-panel-head"
         @if($collapsible) @click="open=!open" @endif>
        <div class="rx-panel-head-label">
            {{-- Scissors icon --}}
            <svg width="14" height="14" fill="none" stroke="#dc2626" stroke-width="2"
                 stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/>
                <line x1="20" y1="4" x2="8.12" y2="15.88"/>
                <line x1="14.47" y1="14.48" x2="20" y2="20"/>
                <line x1="8.12" y1="8.12" x2="12" y2="12"/>
            </svg>
            <span class="rx-panel-head-title">Prescription</span>
            <span class="rx-panel-head-count"
                  x-show="drugs.length > 0" x-cloak
                  x-text="drugs.length + (drugs.length === 1 ? ' drug' : ' drugs')"></span>
        </div>
        @if($collapsible)
        <svg :class="open ? 'rx-chevron-up' : 'rx-chevron-down'"
             style="transition:transform .2s;flex-shrink:0;"
             :style="open ? 'transform:rotate(0deg)' : 'transform:rotate(180deg)'"
             width="14" height="14" fill="none" stroke="#9ca3af" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
            <path d="m18 15-6-6-6 6"/>
        </svg>
        @endif
    </div>

    {{-- Body --}}
    <div class="rx-panel-body" x-show="open" x-cloak>

        {{-- Drug table --}}
        <div>
            {{-- Column headers --}}
            <div class="rx-table-header">
                <div class="rx-col-head" style="text-align:left;">Drug / Strength</div>
                <div class="rx-col-head">SOS</div>
                <div class="rx-col-head">Morn</div>
                <div class="rx-col-head">Noon</div>
                <div class="rx-col-head">Night</div>
                <div class="rx-col-head">Duration</div>
                <div class="rx-col-head">Unit</div>
                <div class="rx-col-head">Total</div>
                <div class="rx-col-head"></div>
            </div>

            {{-- Drug rows --}}
            <template x-for="(row, idx) in drugs" :key="idx">
                <div class="rx-table-row">

                    {{-- Drug typeahead — fetches from drug master --}}
                    <div x-data="{
                            q: row.drug,
                            results: [],
                            showDrop: false,
                            loading: false,
                            _timer: null,
                            onInput(val) {
                                row.drug = val;
                                row.drug_id = null;
                                clearTimeout(this._timer);
                                if (val.length < 2) { this.results = []; this.showDrop = false; return; }
                                this.loading = true;
                                this._timer = setTimeout(async () => {
                                    try {
                                        const res = await fetch('/api/rx/drugs/search?q=' + encodeURIComponent(val));
                                        const data = await res.json();
                                        this.results = Array.isArray(data) ? data : (data.drugs ?? []);
                                        this.showDrop = true;
                                    } catch(e) {}
                                    this.loading = false;
                                }, 220);
                            },
                            pick(drug) {
                                const label = drug.brand_name + (drug.strength ? ' ' + drug.strength : '');
                                row.drug    = label;
                                row.drug_id = drug.id;
                                if (drug.default_duration) {
                                    row.duration = String(drug.default_duration);
                                    row.unit     = drug.default_duration_unit || 'days';
                                }
                                // Prefill the drug's default food advice (editable)
                                if (drug.food_advice) row.food = drug.food_advice;
                                // Auto-detect form type from dosage_form string
                                if (drug.dosage_form) {
                                    const f = drug.dosage_form.toLowerCase();
                                    if (f.includes('tablet') || f.includes('tab')) row.form_type = 'tablet';
                                    else if (f.includes('capsule') || f.includes('cap')) row.form_type = 'capsule';
                                    else if (f.includes('syrup')) row.form_type = 'syrup';
                                    else if (f.includes('suspension')) row.form_type = 'suspension';
                                    else if (f.includes('drop')) row.form_type = 'drops';
                                    else if (f.includes('mouthwash') || f.includes('rinse')) row.form_type = 'mouthwash';
                                    else if (f.includes('gel')) row.form_type = 'gel';
                                    else if (f.includes('cream') || f.includes('ointment')) row.form_type = 'cream';
                                    else if (f.includes('toothpaste')) row.form_type = 'toothpaste';
                                    else if (f.includes('spray')) row.form_type = 'spray';
                                    else if (f.includes('injection') || f.includes('inj')) row.form_type = 'injection';
                                }
                                // Keep dose cells the right type for the detected form
                                // (ml numbers for liquids, booleans for solids).
                                const liquid = ['syrup','suspension','drops'].includes(row.form_type);
                                ['morn','noon','night'].forEach(k => {
                                    if (liquid) { if (typeof row[k] === 'boolean') row[k] = ''; }
                                    else { if (typeof row[k] !== 'boolean') row[k] = (parseFloat(row[k]) || 0) > 0; }
                                });
                                this.q       = label;
                                this.results = [];
                                this.showDrop = false;
                            },
                        }"
                        @click.outside="showDrop = false"
                        style="position:relative;">

                        {{-- Form type select (first — user picks type, then searches) --}}
                        <select x-model="row.form_type" @change="onFormTypeChange(row)"
                                style="margin-bottom:4px;width:100%;font-size:11px;border:1px solid #e5e7eb;border-radius:4px;padding:3px 6px;color:#374151;background:#fff;outline:none;">
                            <optgroup label="Solid">
                                <option value="tablet">Tablet</option>
                                <option value="capsule">Capsule</option>
                                <option value="lozenge">Lozenge</option>
                            </optgroup>
                            <optgroup label="Liquid">
                                <option value="syrup">Syrup</option>
                                <option value="suspension">Suspension</option>
                                <option value="drops">Drops</option>
                                <option value="mouthwash">Mouthwash / Rinse</option>
                            </optgroup>
                            <optgroup label="Topical (1 unit)">
                                <option value="gel">Gel</option>
                                <option value="cream">Cream / Ointment</option>
                                <option value="toothpaste">Toothpaste</option>
                                <option value="brush">Brush / Applicator</option>
                                <option value="spray">Spray</option>
                            </optgroup>
                            <optgroup label="Injectable / Other">
                                <option value="injection">Injection</option>
                                <option value="other">Other</option>
                            </optgroup>
                        </select>

                        {{-- Search input (after form type is chosen) --}}
                        <div style="position:relative;">
                            <input type="text" class="rx-input"
                                   :value="q"
                                   @input="onInput($event.target.value); q = $event.target.value"
                                   @focus="if(results.length) showDrop = true"
                                   @keydown.escape="showDrop = false"
                                   :placeholder="'Search ' + row.form_type + '…'"
                                   autocomplete="off">
                            {{-- Loading indicator --}}
                            <span x-show="loading" x-cloak
                                  style="position:absolute;right:8px;top:50%;transform:translateY(-50%);font-size:10px;color:#9ca3af;">
                                ···
                            </span>
                        </div>

                        {{-- Food advice (before/after food …) — prefilled from drug default, editable --}}
                        <select x-model="row.food"
                                style="margin-top:4px;width:100%;font-size:11px;border:1px solid #e5e7eb;border-radius:4px;padding:3px 6px;color:#374151;background:#fff;outline:none;">
                            <option value="">Food advice…</option>
                            <option value="After Food">After Food</option>
                            <option value="Before Food">Before Food</option>
                            <option value="With Food">With Food</option>
                            <option value="Empty Stomach">Empty Stomach</option>
                            <option value="At Bedtime">At Bedtime</option>
                            <option value="Any Time">Any Time</option>
                        </select>

                        {{-- Dropdown — positioned under the search input --}}
                        <div x-show="showDrop" x-cloak
                             style="position:absolute;z-index:9999;top:100%;left:0;right:0;
                                    background:#fff;border:1px solid #fecaca;border-radius:6px;
                                    box-shadow:0 6px 16px rgba(0,0,0,.1);max-height:220px;overflow-y:auto;margin-top:2px;">

                            <template x-for="drug in results" :key="drug.id">
                                <div @click="pick(drug)"
                                     style="padding:7px 10px;cursor:pointer;border-bottom:1px solid #fef2f2;font-size:12px;"
                                     @mouseover="$el.style.background='#fff5f5'"
                                     @mouseout="$el.style.background=''">
                                    <span x-text="drug.brand_name + (drug.strength ? ' ' + drug.strength : '')"
                                          style="font-weight:600;color:#111827;"></span>
                                    <template x-if="drug.generic_name">
                                        <span x-text="' (' + drug.generic_name + ')'"
                                              style="color:#9ca3af;font-size:11px;"></span>
                                    </template>
                                    <template x-if="drug.dosage_form">
                                        <span x-text="' · ' + drug.dosage_form"
                                              style="color:#9ca3af;font-size:10px;"></span>
                                    </template>
                                </div>
                            </template>

                            {{-- No results --}}
                            <template x-if="results.length === 0 && !loading">
                                <div style="padding:8px 10px;font-size:11px;color:#9ca3af;font-style:italic;">
                                    No drugs found
                                </div>
                            </template>

                            {{-- Add to master --}}
                            <a href="{{ route('rx.drugs.create') }}" target="_blank"
                               style="display:flex;align-items:center;gap:5px;padding:8px 10px;
                                      font-size:11px;font-weight:600;color:#dc2626;text-decoration:none;
                                      border-top:1px solid #fecaca;background:#fff5f5;"
                               @mouseover="$el.style.background='#fef2f2'"
                               @mouseout="$el.style.background='#fff5f5'">
                                <svg width="11" height="11" fill="none" stroke="currentColor"
                                     stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                     viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
                                Add New Drug to Master
                            </a>
                        </div>
                    </div>{{-- /typeahead --}}

                    <div class="rx-checkbox-wrap">
                        <input type="checkbox" x-model="row.sos"
                               style="width:15px;height:15px;accent-color:#dc2626;cursor:pointer;">
                    </div>
                    {{-- Morn / Noon / Night: ml number input for liquids, checkbox otherwise --}}
                    <div class="rx-checkbox-wrap">
                        <template x-if="!isLiquid(row)">
                            <input type="checkbox" x-model="row.morn"
                                   style="width:15px;height:15px;accent-color:#dc2626;cursor:pointer;">
                        </template>
                        <template x-if="isLiquid(row)">
                            <input type="number" min="0" step="0.5" x-model.number="row.morn"
                                   placeholder="ml" title="ml per dose"
                                   class="rx-input rx-input-center" style="padding:4px 2px;font-size:11px;">
                        </template>
                    </div>
                    <div class="rx-checkbox-wrap">
                        <template x-if="!isLiquid(row)">
                            <input type="checkbox" x-model="row.noon"
                                   style="width:15px;height:15px;accent-color:#dc2626;cursor:pointer;">
                        </template>
                        <template x-if="isLiquid(row)">
                            <input type="number" min="0" step="0.5" x-model.number="row.noon"
                                   placeholder="ml" title="ml per dose"
                                   class="rx-input rx-input-center" style="padding:4px 2px;font-size:11px;">
                        </template>
                    </div>
                    <div class="rx-checkbox-wrap">
                        <template x-if="!isLiquid(row)">
                            <input type="checkbox" x-model="row.night"
                                   style="width:15px;height:15px;accent-color:#dc2626;cursor:pointer;">
                        </template>
                        <template x-if="isLiquid(row)">
                            <input type="number" min="0" step="0.5" x-model.number="row.night"
                                   placeholder="ml" title="ml per dose"
                                   class="rx-input rx-input-center" style="padding:4px 2px;font-size:11px;">
                        </template>
                    </div>

                    <input type="number" class="rx-input rx-input-center"
                           x-model="row.duration"
                           min="1" max="365" placeholder="7">

                    <select class="rx-input" x-model="row.unit">
                        <option value="days">Days</option>
                        <option value="weeks">Weeks</option>
                        <option value="months">Months</option>
                    </select>

                    <div class="rx-total">
                        {{-- Liquids: no meaningful "total" — the per-dose ml says it all --}}
                        <span x-show="!isLiquid(row) && calcTotal(row) > 0" x-text="totalLabel(row)"></span>
                        <span x-show="isLiquid(row) || calcTotal(row) <= 0" style="color:#d1d5db;">—</span>
                    </div>

                    <button type="button" class="rx-remove-btn" @click="removeDrug(idx)">✕</button>
                </div>
            </template>

            {{-- Add Drug --}}
            <button type="button" class="rx-add-btn" @click="addDrug()">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5"
                     stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                    <path d="M12 5v14M5 12h14"/>
                </svg>
                Add Drug
            </button>
        </div>

        {{-- Patient Instructions --}}
        <div>
            <div class="rx-instr-section-title">Patient Instructions</div>
            <div class="rx-instr-chips">
                @foreach([
                    'Avoid hard/crunchy food for 24 hrs',
                    'Do not rinse vigorously',
                    'Keep the area clean',
                    'Use warm saline rinse',
                    'Apply ice pack for swelling',
                    'Avoid alcohol & smoking',
                    'Complete the full course of antibiotics',
                    'Return if bleeding does not stop',
                    'Avoid brushing near the area',
                    'Take medications as prescribed',
                ] as $instr)
                <button type="button"
                        @click="toggleInstr('{{ $instr }}')"
                        :class="selectedInstr.includes('{{ $instr }}') ? 'rx-chip rx-chip-on' : 'rx-chip'">
                    {{ $instr }}
                </button>
                @endforeach
            </div>

            <textarea class="rx-notes"
                      name="{{ $noteField }}"
                      placeholder="Additional instructions… e.g. Return if pain persists after 3 days."
                      >{{ $noteVal }}</textarea>
        </div>

        {{-- Hidden JSON outputs --}}
        <input type="hidden" name="{{ $prefix }}"        :value="drugsJson()">
        <input type="hidden" name="{{ $instructField }}"  :value="instrJson()">

    </div>
</div>
