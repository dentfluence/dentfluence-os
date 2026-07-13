@php
    $consultationsList = $consultations ?? collect();

    // Build plans JSON — clinical fields only, no billing fields in this view
    $plansJson = ($patient->treatmentPlans ?? collect())
        ->sortBy('display_order')
        ->map(fn($p) => [
            'id'                 => $p->id,
            'plan_name'          => $p->plan_name,
            'display_order'      => (int)$p->display_order,
            'status'             => $p->status,
            'is_accepted'        => !is_null($p->accepted_at),
            'accepted_at'        => $p->accepted_at?->format('d M Y'),
            'total'              => (float)$p->total,
            'consultation_id'    => $p->consultation_id,
            'estimated_duration' => $p->estimated_duration,
            'visit_count'        => $p->visit_count ? (int)$p->visit_count : null,
            'doctor_notes'       => $p->doctor_notes,
            'created_by_name'    => $p->creator?->name,
            'created_at'         => $p->created_at?->format('d M Y'),
            'items'              => $p->items->map(fn($i) => [
                'id'                => $i->id,
                'treatment_id'      => $i->treatment_id,
                'tooth_number'      => $i->tooth_number,
                'units'             => (int)($i->units ?? 1),
                'treatment_name'    => $i->treatment_name,
                'unit_price'        => (float)$i->unit_price,
                'total'             => (float)$i->total,
                'notes'             => $i->notes,
                'sort_order'        => (int)$i->sort_order,
                'consent_required'  => (bool)$i->consent_required,
            ])->values()->all(),
        ])->values();

    // Treatments for autocomplete + the tooth-chart treatment picker (grouped by category there)
    $treatmentsJson = ($treatments ?? collect())->map(fn($t) => [
        'id'                => $t->id,
        'name'              => $t->name,
        'price'             => (float)($t->default_price ?? 0),
        'consent_required'  => (bool)($t->consent_required ?? false),
        'category'          => $t->category?->name ?? 'Other',
        'categoryColor'     => $t->category?->color ?: '#6a0f70',
    ]);

    // Latest known condition per tooth, merged chronologically across all of this
    // patient's consultations (a later consultation's reading for a tooth wins).
    // Feeds the faded "as examined" hint in the Chart-by-Tooth treatment picker
    // below, so treatment gets planned against what was actually found.
    $latestToothConditions = [];
    foreach ($consultationsList->sortBy('consultation_date') as $c) {
        $cd = is_array($c->chart_data) ? $c->chart_data : [];
        foreach ($cd as $entry) {
            if (is_array($entry) && !empty($entry['tooth']) && !empty($entry['condition'])) {
                $latestToothConditions[(int)$entry['tooth']] = [
                    'condition' => $entry['condition'],
                    'custom'    => $entry['custom'] ?? null,
                    'surfaces'  => $entry['surfaces'] ?? [],
                ];
            }
        }
    }

    // Group consultations for the consultation selector
    $consultationsJson = $consultationsList->map(fn($c) => [
        'id'              => $c->id,
        'label'           => ($c->consultation_date ? \Carbon\Carbon::parse($c->consultation_date)->format('d M Y') : 'Undated')
                           . ($c->chief_complaint ? ' — ' . \Str::limit($c->chief_complaint, 40) : ''),
        'chief_complaint' => $c->chief_complaint ?? '',
        'diagnosis'       => trim(($c->primary_diagnosis ?? '') . ' ' . ($c->secondary_diagnosis ?? '')),
    ]);
@endphp

<style>
    /* ── Treatment Plan Tab Styles ─────────────────────────────────────────── */
    .tp-input {
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 7px 10px;
        font-size: 13px;
        color: #374151;
        background: white;
        outline: none;
        width: 100%;
        transition: border-color .15s;
    }
    .tp-input:focus { border-color: #6a0f70; box-shadow: 0 0 0 2px rgba(106,15,112,.08); }
    .tp-input:disabled { background: #f9fafb; color: #9ca3af; }

    .tp-btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 6px 12px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 6px;
        cursor: pointer;
        border: none;
        transition: all .15s;
        text-decoration: none;
        white-space: nowrap;
    }
    .tp-btn-primary  { background: #6a0f70; color: #fff; }
    .tp-btn-primary:hover { background: #380740; }
    .tp-btn-outline  { background: #fff; color: #6a0f70; border: 1px solid #6a0f70; }
    .tp-btn-outline:hover { background: #f5eef9; }
    .tp-btn-ghost    { background: transparent; color: #6b7280; border: 1px solid #e5e7eb; }
    .tp-btn-ghost:hover { background: #f9fafb; color: #374151; }
    .tp-btn-green    { background: #16a34a; color: #fff; }
    .tp-btn-green:hover { background: #15803d; }
    .tp-btn-danger   { background: transparent; color: #dc2626; border: 1px solid #fecaca; }
    .tp-btn-danger:hover { background: #fef2f2; }
    .tp-btn-revert   { background: transparent; color: #c2410c; border: 1px solid #fed7aa; }
    .tp-btn-revert:hover { background: #fff7ed; }
    .tp-btn:disabled { opacity: .5; cursor: not-allowed; }

    .tp-badge-accepted {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px; border-radius: 999px;
        font-size: 10px; font-weight: 700;
        background: #dcfce7; color: #166534; border: 1px solid #86efac;
    }
    .tp-badge-pending {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 3px 10px; border-radius: 999px;
        font-size: 10px; font-weight: 700;
        background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb;
    }

    /* consultation group header */
    .tp-consult-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        padding: 12px 0 10px;
        border-bottom: 2px solid #f3f4f6;
        margin-bottom: 14px;
    }
    .tp-consult-date { font-size: 11px; font-weight: 700; color: #6a0f70; text-transform: uppercase; letter-spacing: .05em; }
    .tp-consult-complaint { font-size: 13px; font-weight: 600; color: #111827; margin-top: 2px; }
    .tp-consult-diagnosis { font-size: 12px; color: #6b7280; margin-top: 1px; }

    /* option card */
    .tp-option-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        overflow: hidden;
        transition: border-color .15s;
    }
    .tp-option-card:hover { border-color: #c4b5d4; }
    .tp-option-card.is-accepted { border-color: #86efac; background: #f0fdf4; }

    .tp-card-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 14px 16px 12px;
        border-bottom: 1px solid #f3f4f6;
    }
    .tp-option-number { font-size: 10px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .06em; }
    .tp-option-name   { font-size: 15px; font-weight: 700; color: #111827; margin-top: 2px; }

    /* collapse / expand chevron */
    .tp-collapse-toggle { color: #9ca3af; display: flex; align-items: center; transition: transform .2s ease; }
    .tp-collapse-toggle.is-collapsed { transform: rotate(-90deg); }
    .tp-card-head:hover .tp-collapse-toggle { color: #6a0f70; }

    .tp-card-body {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 0;
    }
    .tp-stat {
        padding: 12px 16px;
        border-right: 1px solid #f3f4f6;
    }
    .tp-stat:last-child { border-right: none; }
    .tp-stat-label { font-size: 10px; color: #9ca3af; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; }
    .tp-stat-value { font-size: 18px; font-weight: 800; color: #111827; margin-top: 2px; }
    .tp-stat-sub   { font-size: 11px; color: #6b7280; margin-top: 1px; }
    .tp-stat-value.is-investment { color: #6a0f70; }

    .tp-card-procedures {
        padding: 10px 16px 12px;
        border-top: 1px solid #f3f4f6;
    }
    .tp-proc-label { font-size: 10px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
    .tp-proc-item  { font-size: 12px; color: #374151; padding: 2px 0; display: flex; align-items: center; gap: 6px; }
    .tp-proc-dot   { width: 4px; height: 4px; border-radius: 50%; background: #6a0f70; flex-shrink: 0; }

    .tp-card-footer {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        padding: 10px 16px;
        border-top: 1px solid #f3f4f6;
        background: #fafafa;
    }

    /* form drawer */
    .tp-form-panel {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        overflow: hidden;
        margin-bottom: 20px;
    }
    .tp-form-head {
        display: flex; align-items: center; justify-content: space-between;
        padding: 14px 20px;
        background: #faf5ff;
        border-bottom: 1px solid #e5e7eb;
    }
    .tp-form-body { padding: 20px; }
    .tp-form-foot {
        display: flex; align-items: center; justify-content: flex-end; gap: 10px;
        padding: 12px 20px;
        border-top: 1px solid #f3f4f6;
        background: #fafafa;
    }
    .tp-label { display: block; font-size: 11px; font-weight: 700; color: #374151; margin-bottom: 4px; text-transform: uppercase; letter-spacing: .04em; }
    .tp-field-group { margin-bottom: 14px; }

    /* procedure rows in form */
    .tp-proc-row {
        display: grid;
        grid-template-columns: 82px 1fr 48px 100px 26px 26px 26px;
        gap: 7px;
        align-items: center;
        padding: 8px 10px;
        border: 1px solid #f3f4f6;
        border-radius: 6px 6px 0 0;
        margin-bottom: 0;
        background: #fafafa;
    }
    /* Tooth-picker CSS (.tp-tooth-*) now lives in partials.tooth-chart-assets,
       included once below — shared with the Lab Case tooth chart. */
    /* qty field */
    .tp-qty-input { text-align: center; }
    .tp-proc-row:hover { border-color: #e8d5f0; background: #fdf8ff; }
    .tp-proc-wrap { margin-bottom: 8px; }

    /* variant rows */
    .tp-variants-wrap {
        border: 1px solid #f3e8ff; border-top: none; border-radius: 0 0 6px 6px;
        background: #fdf8ff; padding: 8px 10px; margin-bottom: 8px;
    }
    /* per-treatment note input */
    .tp-notes-wrap {
        border: 1px solid #f3e8ff; border-top: none; border-radius: 0 0 6px 6px;
        background: #fbfaff; padding: 7px 10px; margin-bottom: 8px;
    }
    .tp-variant-row {
        display: grid; grid-template-columns: 1fr 100px 28px 20px;
        gap: 6px; align-items: center; margin-bottom: 5px;
    }
    .tp-variant-add {
        font-size: 11px; color: #6a0f70; background: none;
        border: 1px dashed #c4b5d4; border-radius: 4px;
        padding: 3px 10px; cursor: pointer; font-family: 'Inter', sans-serif;
    }
    .tp-variant-add:hover { background: #f3e8ff; }

    /* Chart-by-Tooth picker (Slice 4/5) — odontogram styling matches the
       consultation tooth chart 1:1 (resources/views/consultations/create.blade.php)
       so the two feel like the same feature, not two different UIs. */
    .tooth-btn {
        width: 30px; height: 30px; border: 1.5px solid #e5e7eb; border-radius: 4px;
        font-size: 10px; font-weight: 600; color: #6b7280;
        background: #fff; cursor: pointer; transition: all .12s;
        font-family: 'Inter', sans-serif;
        display: inline-flex; align-items: center; justify-content: center;
        padding: 0;
    }
    .tooth-btn:hover { border-color: #b95cb7; color: #6a0f70; background: #faf5fb; }
    .tooth-btn.active { box-shadow: 0 0 0 2px rgba(106,15,112,.45); border-color: #6a0f70; }
    .tooth-row { display: flex; justify-content: center; gap: 3px; }
    .tooth-midline { width: 1px; background: #e5e7eb; margin: 0 6px; flex-shrink: 0; }
    .tooth-slot { display: flex; flex-direction: column; align-items: center; gap: 2px; }
    .tooth-dentition-toggle {
        width: 18px; height: 12px; border: 1px solid #e5e7eb; border-radius: 3px;
        font-size: 7px; font-weight: 800; line-height: 1; color: #b0b0b8;
        background: #fafafa; cursor: pointer; font-family: 'Inter', sans-serif;
        padding: 0; display: inline-flex; align-items: center; justify-content: center;
        letter-spacing: .02em;
    }
    .tooth-dentition-toggle:hover { border-color: #b95cb7; color: #6a0f70; }
    .tooth-dentition-toggle.is-child { background: #fce7f3; border-color: #db2777; color: #db2777; }

    .tx-region-chip {
        padding: 6px 14px; border: 1.5px solid #e5e7eb; border-radius: 999px;
        font-size: 10.5px; font-weight: 700; color: #6b7280; background: #fff;
        cursor: pointer; font-family: 'Inter', sans-serif; transition: all .12s;
    }
    .tx-region-chip:hover { border-color: #b95cb7; color: #6a0f70; background: #faf5fb; }
    .tx-region-chip.active { box-shadow: 0 0 0 2px rgba(106,15,112,.45); border-color: #6a0f70; }

    /* Chart-by-Tooth treatment list — plain list, not a button grid */
    .tx-list-row {
        display: flex; align-items: center; gap: 10px; width: 100%;
        padding: 8px 4px; border: none; border-bottom: 1px solid #f3f4f6;
        background: transparent; cursor: pointer; text-align: left;
        font-family: 'Inter', sans-serif; transition: background .1s;
    }
    .tx-list-row:last-child { border-bottom: none; }
    .tx-list-row:hover { background: #faf5fb; }
    .tx-list-row.selected { background: #f5eef9; }
    .tx-list-check {
        width: 16px; height: 16px; border-radius: 50%; border: 1.5px solid #d1d5db;
        flex-shrink: 0; display: flex; align-items: center; justify-content: center;
        transition: all .1s; background: #fff;
    }
    .tx-list-row.selected .tx-list-check { background: #6a0f70; border-color: #6a0f70; }
    .tx-list-name { flex: 1; font-size: 12.5px; font-weight: 600; color: #374151; }
    .tx-list-row.selected .tx-list-name { color: #6a0f70; }
    .tx-list-price { font-size: 11px; font-weight: 600; color: #9ca3af; flex-shrink: 0; }

    /* Category dropdown header (accordion) */
    .tx-cat-head {
        display: flex; align-items: center; justify-content: space-between; width: 100%;
        background: none; border: none; cursor: pointer; padding: 6px 2px;
        font-family: 'Inter', sans-serif;
    }
    .tx-cat-head:hover .tx-cat-name { color: #6a0f70; }
    .tx-cat-name { font-size: 10.5px; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .06em; transition: color .1s; }
    .tx-cat-count {
        font-size: 10px; font-weight: 700; color: #6a0f70; background: #f5eef9;
        border-radius: 999px; padding: 1px 7px; margin-right: 6px;
    }
    .tx-chevron { transition: transform .15s; flex-shrink: 0; }
    .tx-chevron.open { transform: rotate(180deg); }
</style>

@include('partials.tooth-chart-assets')

<div
    x-show="activeTab === 'treatment-plan'"
    style="display:none"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-data="treatmentPlanTab()"
    x-init="init()"
    class="w-full px-6 py-6"
>

    {{-- ── Page Header ── --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-base font-bold text-gray-900">Treatment Plans</h3>
            <p class="text-xs text-gray-400 mt-0.5">Clinical options · Billing managed separately</p>
        </div>
        <div class="flex items-center gap-2">
            {{-- Pick which treatment plans to print --}}
            <button type="button" x-show="plans.length"
                    @click="openPrintPicker()"
                    class="tp-btn tp-btn-outline">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print Plans
            </button>
            <button @click="openNewForm()"
                    dusk="tp-open-form"
                    class="tp-btn tp-btn-primary">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Treatment Option
            </button>
        </div>
    </div>

    {{-- ── Print Picker Modal ── --}}
    <div x-show="printPickerOpen" x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center"
         style="background:rgba(20,5,25,.45);"
         @click.self="printPickerOpen=false"
         @keydown.escape.window="printPickerOpen=false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden" @click.stop>

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                <div>
                    <div class="text-sm font-bold text-gray-900">Print Treatment Plans</div>
                    <div class="text-xs text-gray-400 mt-0.5">
                        <span x-text="printSelected.length"></span> of <span x-text="plans.length"></span> selected
                    </div>
                </div>
                <button @click="printPickerOpen=false" class="text-gray-400 hover:text-gray-600">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            {{-- Select all / clear --}}
            <div class="flex items-center gap-4 px-5 py-2 border-b border-gray-50 bg-gray-50">
                <button @click="selectAllPrint()" class="text-xs font-semibold text-[#6a0f70] hover:underline">Select all</button>
                <button @click="clearPrint()" class="text-xs font-semibold text-gray-400 hover:text-gray-600 hover:underline">Clear</button>
            </div>

            {{-- Plan list (grouped by consultation) --}}
            <div class="max-h-80 overflow-y-auto px-2 py-2">
                <template x-for="group in groupedPlans" :key="group.consultationId ?? 'none'">
                    <div class="mb-1">
                        <div class="px-3 pt-2 pb-1 text-[10px] font-bold uppercase tracking-wide text-gray-400"
                             x-text="group.consultationDate ? ('Consultation · ' + group.consultationDate) : 'No linked consultation'"></div>
                        <template x-for="plan in group.plans" :key="plan.id">
                            <label class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-purple-50 cursor-pointer">
                                <input type="checkbox" :value="plan.id"
                                       :checked="printSelected.includes(plan.id)"
                                       @change="togglePrintSelect(plan.id)"
                                       style="accent-color:#6a0f70;width:15px;height:15px;flex-shrink:0;">
                                <div class="flex-1 min-w-0">
                                    <div class="text-sm font-medium text-gray-800 truncate" x-text="plan.plan_name"></div>
                                    <div class="text-xs text-gray-400">
                                        Option <span x-text="plan.display_order"></span> · <span x-text="plan.items.length"></span> treatment(s)
                                        <span x-show="plan.is_accepted" class="text-green-600 font-semibold"> · Accepted</span>
                                    </div>
                                </div>
                                <div class="text-sm font-bold text-[#6a0f70] whitespace-nowrap">Rs. <span x-text="fmtInt(plan.total)"></span></div>
                            </label>
                        </template>
                    </div>
                </template>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-100 bg-gray-50">
                <button @click="printPickerOpen=false" class="tp-btn tp-btn-ghost">Cancel</button>
                <button @click="printSelectedPlans()" :disabled="!printSelected.length" class="tp-btn tp-btn-primary">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Print Selected
                </button>
            </div>
        </div>
    </div>

    {{-- ── Revert Acceptance Modal (reason required, logged) ── --}}
    <div x-show="revertOpen" x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center"
         style="background:rgba(20,5,25,.45);"
         @click.self="closeRevert()"
         @keydown.escape.window="closeRevert()">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden" @click.stop>

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                <div>
                    <div class="text-sm font-bold text-gray-900">Revert Acceptance</div>
                    <div class="text-xs text-gray-400 mt-0.5" x-text="revertPlanName"></div>
                </div>
                <button @click="closeRevert()" class="text-gray-400 hover:text-gray-600">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-5 py-4">
                <div class="flex gap-2 p-3 mb-3 rounded-lg bg-orange-50 border border-orange-100 text-xs text-orange-800">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;margin-top:1px;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    <span>This marks the plan as <strong>Pending</strong> again. The reason below is saved to the activity log.</span>
                </div>
                <label class="tp-label">Reason for reverting <span class="text-red-400">*</span></label>
                <textarea x-model="revertReason" rows="3"
                          placeholder="e.g. Patient changed their mind, chose a different option…"
                          class="tp-input resize-none"></textarea>
                <div x-show="revertError" class="text-xs text-red-600 mt-2" x-text="revertError"></div>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-100 bg-gray-50">
                <button @click="closeRevert()" class="tp-btn tp-btn-ghost">Cancel</button>
                <button @click="confirmRevert()" :disabled="reverting" class="tp-btn tp-btn-revert" style="border-width:1px;">
                    <svg x-show="reverting" class="animate-spin" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/></svg>
                    <span x-text="reverting ? 'Reverting…' : 'Confirm Revert'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Consent Form Picker Modal (2026-07-13) ──
         Lets staff choose exactly which treatment/tooth rows on this plan to
         generate a consent document for. Rows come pre-checked from each
         item's consent_required flag (Treatment Library default or the
         per-item override in Edit), but can be freely adjusted per generation
         — this is the primary entry point now, not the Edit-mode toggle. ── --}}
    <div x-show="consentPickerOpen" x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center"
         style="background:rgba(20,5,25,.45);"
         @click.self="consentPickerOpen=false"
         @keydown.escape.window="consentPickerOpen=false">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden" @click.stop>

            {{-- Header --}}
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                <div>
                    <div class="text-sm font-bold text-gray-900">Generate Consent Form</div>
                    <div class="text-xs text-gray-400 mt-0.5">
                        <span x-text="consentRows.filter(r => r.checked).length"></span> of <span x-text="consentRows.length"></span> selected
                    </div>
                </div>
                <button @click="consentPickerOpen=false" class="text-gray-400 hover:text-gray-600">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            {{-- Select all / clear --}}
            <div class="flex items-center gap-4 px-5 py-2 border-b border-gray-50 bg-gray-50">
                <button @click="selectAllConsent()" class="text-xs font-semibold text-[#6a0f70] hover:underline">Select all</button>
                <button @click="clearConsent()" class="text-xs font-semibold text-gray-400 hover:text-gray-600 hover:underline">Clear</button>
            </div>

            {{-- Row list --}}
            <div class="max-h-80 overflow-y-auto px-2 py-2">
                <template x-if="!consentRows.length">
                    <div class="px-3 py-6 text-xs text-gray-400 text-center">No treatments on this plan yet.</div>
                </template>
                <template x-for="row in consentRows" :key="row.key">
                    <label class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-purple-50 cursor-pointer">
                        <input type="checkbox" :checked="row.checked"
                               @change="toggleConsentRow(row.key)"
                               style="accent-color:#6a0f70;width:15px;height:15px;flex-shrink:0;">
                        <div class="flex-1 min-w-0 text-sm font-medium text-gray-800 truncate" x-text="row.label"></div>
                    </label>
                </template>
            </div>

            {{-- Footer --}}
            <div class="flex items-center justify-end gap-2 px-5 py-3 border-t border-gray-100 bg-gray-50">
                <button @click="consentPickerOpen=false" class="tp-btn tp-btn-ghost">Cancel</button>
                <button @click="generateConsentForm()" :disabled="!consentRows.filter(r => r.checked).length" class="tp-btn tp-btn-primary">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                    Generate
                </button>
            </div>
        </div>
    </div>

    {{-- ── New / Edit Form ── --}}
    <div x-show="formOpen" x-collapse class="tp-form-panel">

        <div class="tp-form-head">
            <div>
                <div class="text-sm font-bold text-[#6a0f70]" x-text="editingId ? 'Edit Treatment Option' : 'New Treatment Option'"></div>
                <div class="text-xs text-gray-400 mt-0.5">{{ $patient->name }}</div>
            </div>
            <button @click="closeForm()" class="text-gray-400 hover:text-gray-600">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="tp-form-body">

            {{-- Row 1: Option name + Consultation --}}
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="tp-field-group">
                    <label class="tp-label">Option Name <span class="text-red-400">*</span></label>
                    <input type="text" x-model="form.plan_name" placeholder="e.g. Dental Implant, Fixed Bridge…"
                           dusk="tp-plan-name"
                           class="tp-input">
                </div>
                <div class="tp-field-group">
                    <label class="tp-label">Link to Consultation</label>
                    <select x-model="form.consultation_id" class="tp-input">
                        <option value="">— No consultation —</option>
                        @foreach($consultationsList as $c)
                        <option value="{{ $c->id }}">
                            {{ $c->consultation_date ? \Carbon\Carbon::parse($c->consultation_date)->format('d M Y') : 'Undated' }}
                            {{ $c->chief_complaint ? '— ' . \Str::limit($c->chief_complaint, 35) : '' }}
                        </option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Treatments ── --}}
            <div class="mb-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="tp-label mb-0">Treatments</label>
                    <div style="display:flex;align-items:center;gap:10px;">
                        <button type="button" @click="openChart()"
                                style="font-size:11px;font-weight:600;color:#6a0f70;background:#fdf4ff;
                                       border:1px solid rgba(106,15,112,.2);border-radius:6px;padding:4px 10px;
                                       cursor:pointer;display:flex;align-items:center;gap:5px;">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                            Chart by Tooth
                        </button>
                        <span class="text-xs text-gray-400" x-text="form.items.length + ' treatment(s)'"></span>
                    </div>
                </div>

                {{-- Column headers --}}
                <div x-show="form.items.length" class="tp-proc-row" style="background:transparent;border:none;padding-bottom:2px;">
                    <div style="font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;">Tooth</div>
                    <div style="font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;">Treatment</div>
                    <div style="font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;text-align:center;">Qty</div>
                    <div style="font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.05em;text-align:right;">Price / Unit</div>
                    <div></div>
                    <div></div>
                    <div></div>
                </div>

                <template x-for="(item, idx) in form.items" :key="idx">
                    <div class="tp-proc-wrap">
                        {{-- Main treatment row --}}
                        <div class="tp-proc-row" :class="(item.showVariants || item.showNotes) ? '' : 'rounded-b-md'">

                            {{-- Tooth picker (shared partial — also used by the Lab Case tooth chart) --}}
                            @include('partials.tooth-chart', ['target' => 'item', 'pickerId' => 'idx'])

                            {{-- Treatment name (autocomplete) --}}
                            <div class="relative">
                                <input type="text"
                                       x-model="item.treatment_name"
                                       dusk="tp-tx-name"
                                       @input="filterTx(idx)"
                                       @focus="activeSuggest = idx"
                                       @blur="hideSuggestDelayed()"
                                       placeholder="Treatment name…"
                                       class="tp-input text-sm"
                                       autocomplete="off">
                                <div x-show="activeSuggest === idx && suggestions[idx]?.length"
                                     class="absolute left-0 top-full mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg z-20 max-h-40 overflow-y-auto">
                                    <template x-for="sug in suggestions[idx]" :key="sug.name">
                                        <button type="button"
                                                @mousedown.prevent="selectSuggest(idx, sug)"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-purple-50 hover:text-[#6a0f70]">
                                            <span x-text="sug.name"></span>
                                            <span class="text-xs text-gray-400 ml-2">Rs. <span x-text="fmt(sug.price)"></span></span>
                                        </button>
                                    </template>
                                </div>
                            </div>

                            {{-- Quantity (auto-set to number of selected teeth; editable) --}}
                            <div class="relative">
                                <input type="number" x-model.number="item.units" min="1" step="1"
                                       dusk="tp-tx-qty"
                                       title="Quantity (number of teeth / units)"
                                       class="tp-input tp-qty-input"
                                       placeholder="1">
                            </div>

                            {{-- Fee — price per unit (disabled when variants drive price) --}}
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm font-semibold pointer-events-none">Rs.</span>
                                <input type="number" x-model="item.unit_price" min="0" step="1"
                                       dusk="tp-tx-price"
                                       :disabled="item.variants && item.variants.length > 0"
                                       class="tp-input text-right pl-8"
                                       placeholder="0">
                            </div>

                            {{-- Notes toggle --}}
                            <button type="button"
                                    @click="item.showNotes = !item.showNotes"
                                    :title="item.showNotes ? 'Hide note' : 'Add note'"
                                    class="w-7 h-7 flex items-center justify-center rounded transition-colors"
                                    :class="(item.showNotes || (item.notes && item.notes.length)) ? 'text-[#6a0f70] bg-purple-50' : 'text-gray-300 hover:text-[#6a0f70] hover:bg-purple-50'">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            </button>

                            {{-- Options toggle --}}
                            <button type="button"
                                    @click="item.showVariants = !item.showVariants; if(!item.variants) item.variants=[];"
                                    :title="item.showVariants ? 'Hide options' : 'Add material options'"
                                    class="w-7 h-7 flex items-center justify-center rounded transition-colors"
                                    :class="item.showVariants ? 'text-[#6a0f70] bg-purple-50' : 'text-gray-300 hover:text-[#6a0f70] hover:bg-purple-50'">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>
                            </button>

                            {{-- Consent required toggle — Phase 2 refinement.
                                 Defaults from the treatment's own "Consent form required"
                                 rule when picked from the autocomplete; togglable per item
                                 so staff can opt a specific case in/out. --}}
                            <button type="button"
                                    @click="item.consent_required = !item.consent_required"
                                    :title="item.consent_required ? 'Consent form required for this treatment/tooth' : 'No consent form needed — click to require one'"
                                    class="w-7 h-7 flex items-center justify-center rounded transition-colors"
                                    :class="item.consent_required ? 'text-[#6a0f70] bg-purple-50' : 'text-gray-300 hover:text-[#6a0f70] hover:bg-purple-50'">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                            </button>

                            {{-- Remove --}}
                            <button type="button" @click="removeItem(idx)"
                                    class="w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-400 hover:bg-red-50 rounded transition-colors">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>

                        {{-- Notes input (per treatment) --}}
                        <div x-show="item.showNotes" class="tp-notes-wrap" x-cloak>
                            <input type="text" x-model="item.notes"
                                   placeholder="Note for this treatment (e.g. after RCT, sitting 2)…"
                                   class="tp-input text-sm" style="font-size:12px;">
                        </div>

                        {{-- Variants panel --}}
                        <div x-show="item.showVariants" class="tp-variants-wrap" x-cloak>
                            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6a0f70;margin-bottom:6px;">
                                Material Options <span style="font-weight:400;color:#9ca3af;text-transform:none;letter-spacing:0;">(patient picks one)</span>
                            </div>
                            <template x-if="item.variants && item.variants.length > 0">
                                <div>
                                    <template x-for="(v, vi) in item.variants" :key="vi">
                                        <div class="tp-variant-row">
                                            <input type="text" x-model="v.label" placeholder="e.g. PFM Crown, Zirconia Crown…"
                                                   class="tp-input text-sm" style="font-size:12px;">
                                            <div class="relative">
                                                <span class="absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs font-semibold pointer-events-none">Rs.</span>
                                                <input type="number" x-model="v.price" min="0" step="1"
                                                       @input="if(v.selected) item.unit_price = parseFloat(v.price)||0"
                                                       class="tp-input text-right text-sm" style="padding-left:28px;font-size:12px;"
                                                       placeholder="0">
                                            </div>
                                            <label style="display:flex;align-items:center;gap:4px;font-size:11px;color:#374151;cursor:pointer;white-space:nowrap;">
                                                <input type="radio" :name="'vr_'+idx"
                                                       :checked="v.selected"
                                                       @change="item.variants.forEach((x,xi)=>x.selected=xi===vi); item.unit_price=parseFloat(v.price)||0;"
                                                       style="accent-color:#6a0f70;">
                                                Default
                                            </label>
                                            <button type="button" @click="item.variants.splice(vi,1); if(item.variants.length===0){item.unit_price=0;}"
                                                    style="background:none;border:none;color:#d1d5db;cursor:pointer;font-size:14px;line-height:1;" title="Remove">&#xD7;</button>
                                        </div>
                                    </template>
                                </div>
                            </template>
                            <template x-if="!item.variants || item.variants.length === 0">
                                <p style="font-size:11px;color:#9ca3af;margin-bottom:6px;">No options yet. Click below to add.</p>
                            </template>
                            <button type="button" class="tp-variant-add"
                                    @click="if(!item.variants) item.variants=[]; item.variants.push({label:'',price:0,selected:item.variants.length===0})">
                                + Add Option
                            </button>
                        </div>
                    </div>
                </template>

                <button type="button" @click="addItem()"
                        dusk="tp-add-treatment"
                        class="w-full flex items-center justify-center gap-2 py-2.5 border border-dashed border-gray-300 rounded-lg text-xs font-semibold text-gray-500 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors mt-1">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Treatment
                </button>
            </div>

            {{-- ── Chart-by-Tooth treatment picker (Slice 4/5) ──
                 Click a tooth on the odontogram, see what was found on
                 examination (faded, read-only — pulled from the patient's
                 consultation chart_data), then multi-select treatments for
                 that tooth. Each selected treatment becomes its own line
                 item, so a tooth can carry a sequence (RCT → Post & Core →
                 Crown) without re-typing the tooth number three times. --}}
            <template x-if="chartOpen">
                <div @click.self="closeChart()"
                     style="position:fixed;inset:0;z-index:300;display:flex;align-items:center;justify-content:center;
                            background:rgba(15,5,20,.45);backdrop-filter:blur(3px);">
                    <div @click.stop
                         style="background:#fff;border-radius:14px;width:680px;max-width:94vw;max-height:88vh;
                                overflow:hidden;display:flex;flex-direction:column;
                                box-shadow:0 24px 64px rgba(0,0,0,.22),0 0 0 1px rgba(106,15,112,.08);">

                        {{-- Header --}}
                        <div style="padding:14px 18px;border-bottom:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                            <div>
                                <div style="font-size:13px;font-weight:700;color:#111827;font-family:'Inter',sans-serif;">Chart Treatment by Tooth</div>
                                <div style="font-size:10.5px;color:#9ca3af;font-family:'Inter',sans-serif;margin-top:1px;">Click a tooth, then pick one or more treatments for it</div>
                            </div>
                            <button type="button" @click="closeChart()"
                                    style="width:26px;height:26px;border-radius:50%;border:1px solid #e5e7eb;background:#f9fafb;
                                           color:#9ca3af;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;">×</button>
                        </div>

                        <div style="flex:1;overflow-y:auto;padding:18px 20px;">

                            {{-- Odontogram — full width, centered, identical markup/classes to the consultation tooth chart --}}
                            <div style="max-width:540px;margin:0 auto;">
                                <div style="text-align:center;font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin-bottom:5px;font-family:'Inter',sans-serif;">Upper</div>
                                <div class="tooth-row">
                                    <template x-for="pos in [18,17,16,15,14,13,12,11]" :key="'u'+pos">
                                        <div class="tooth-slot">
                                            <button type="button" @click="chartClickTooth(codeAt(pos))" class="tooth-btn"
                                                    :class="chartTooth === codeAt(pos) ? 'active' : ''"
                                                    :style="chartToothStyle(codeAt(pos))"
                                                    x-text="codeAt(pos)"></button>
                                            <button type="button" x-show="canToggleDentition(pos)" x-cloak
                                                    @click.stop="toggleDentitionMode(pos)" class="tooth-dentition-toggle"
                                                    :class="isChildPos(pos) ? 'is-child' : ''"
                                                    x-text="isChildPos(pos) ? 'P' : 'A'"></button>
                                        </div>
                                    </template>
                                    <div class="tooth-midline"></div>
                                    <template x-for="pos in [21,22,23,24,25,26,27,28]" :key="'u'+pos">
                                        <div class="tooth-slot">
                                            <button type="button" @click="chartClickTooth(codeAt(pos))" class="tooth-btn"
                                                    :class="chartTooth === codeAt(pos) ? 'active' : ''"
                                                    :style="chartToothStyle(codeAt(pos))"
                                                    x-text="codeAt(pos)"></button>
                                            <button type="button" x-show="canToggleDentition(pos)" x-cloak
                                                    @click.stop="toggleDentitionMode(pos)" class="tooth-dentition-toggle"
                                                    :class="isChildPos(pos) ? 'is-child' : ''"
                                                    x-text="isChildPos(pos) ? 'P' : 'A'"></button>
                                        </div>
                                    </template>
                                </div>
                                <div style="text-align:center;font-size:8px;color:#d1d5db;font-family:'Inter',sans-serif;margin:5px 0;letter-spacing:.18em;">— MIDLINE —</div>
                                <div class="tooth-row">
                                    <template x-for="pos in [48,47,46,45,44,43,42,41]" :key="'l'+pos">
                                        <div class="tooth-slot">
                                            <button type="button" @click="chartClickTooth(codeAt(pos))" class="tooth-btn"
                                                    :class="chartTooth === codeAt(pos) ? 'active' : ''"
                                                    :style="chartToothStyle(codeAt(pos))"
                                                    x-text="codeAt(pos)"></button>
                                            <button type="button" x-show="canToggleDentition(pos)" x-cloak
                                                    @click.stop="toggleDentitionMode(pos)" class="tooth-dentition-toggle"
                                                    :class="isChildPos(pos) ? 'is-child' : ''"
                                                    x-text="isChildPos(pos) ? 'P' : 'A'"></button>
                                        </div>
                                    </template>
                                    <div class="tooth-midline"></div>
                                    <template x-for="pos in [31,32,33,34,35,36,37,38]" :key="'l'+pos">
                                        <div class="tooth-slot">
                                            <button type="button" @click="chartClickTooth(codeAt(pos))" class="tooth-btn"
                                                    :class="chartTooth === codeAt(pos) ? 'active' : ''"
                                                    :style="chartToothStyle(codeAt(pos))"
                                                    x-text="codeAt(pos)"></button>
                                            <button type="button" x-show="canToggleDentition(pos)" x-cloak
                                                    @click.stop="toggleDentitionMode(pos)" class="tooth-dentition-toggle"
                                                    :class="isChildPos(pos) ? 'is-child' : ''"
                                                    x-text="isChildPos(pos) ? 'P' : 'A'"></button>
                                        </div>
                                    </template>
                                </div>
                                <div style="text-align:center;font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin-top:5px;font-family:'Inter',sans-serif;">Lower</div>

                                {{-- Region targets — for treatments that apply to more than one
                                     tooth at once (full-mouth extraction, full-arch denture, etc.) --}}
                                <div style="margin-top:16px;text-align:center;">
                                    <div style="font-size:9px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:.07em;margin-bottom:6px;font-family:'Inter',sans-serif;">Or a Region</div>
                                    <div style="display:flex;justify-content:center;flex-wrap:wrap;gap:6px;">
                                        <template x-for="r in REGIONS" :key="r">
                                            <button type="button" @click="chartClickTooth(r)" class="tx-region-chip"
                                                    :class="chartTooth === r ? 'active' : ''"
                                                    :style="chartToothStyle(r)"
                                                    x-text="r"></button>
                                        </template>
                                    </div>
                                </div>

                                <div style="margin-top:16px;display:flex;align-items:center;justify-content:center;gap:16px;font-size:10px;color:#9ca3af;font-family:'Inter',sans-serif;">
                                    <span style="display:flex;align-items:center;gap:5px;">
                                        <span style="width:8px;height:8px;border-radius:2px;background:#6a0f70;display:inline-block;flex-shrink:0;"></span>
                                        Treatment planned
                                    </span>
                                    <span style="display:flex;align-items:center;gap:5px;">
                                        <span style="width:8px;height:8px;border-radius:2px;border:1.5px solid #9ca3af;background:#9ca3af1f;display:inline-block;flex-shrink:0;"></span>
                                        Condition on file
                                    </span>
                                </div>
                            </div>

                            {{-- Treatment list — below the chart, not beside it --}}
                            <div style="max-width:540px;margin:22px auto 0;padding-top:18px;border-top:1px solid #f3f4f6;">
                                <template x-if="!chartTooth">
                                    <div style="min-height:100px;display:flex;align-items:center;justify-content:center;
                                                color:#d1d5db;font-size:12px;font-family:'Inter',sans-serif;text-align:center;">
                                        Select a tooth above to plan treatment
                                    </div>
                                </template>

                                <template x-if="chartTooth">
                                    <div>
                                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                                            <span style="font-size:11px;font-weight:500;color:#9ca3af;text-transform:uppercase;letter-spacing:.04em;font-family:'Inter',sans-serif;"
                                                  x-text="REGIONS.includes(chartTooth) ? 'Region' : 'Tooth'"></span>
                                            <span x-text="chartTooth" style="font-size:13px;font-weight:700;color:#6a0f70;font-family:'Inter',sans-serif;"></span>
                                        </div>

                                        {{-- Faded condition-as-examined hint — pulled from the consultation
                                             tooth chart so treatment is planned against what was found. --}}
                                        <template x-if="chartCondition()">
                                            <div style="display:flex;align-items:center;gap:6px;margin-bottom:12px;padding:6px 10px;
                                                        border-radius:6px;background:#fafafa;border:1px dashed #e5e7eb;opacity:.72;">
                                                <span :style="{ background: chartCondition().color, width: '7px', height: '7px', borderRadius: '50%', flexShrink: '0' }"></span>
                                                <span style="font-size:11px;color:#6b7280;font-family:'Inter',sans-serif;">
                                                    As examined: <strong x-text="chartCondition().label"></strong><span x-show="chartCondition().surfaces.length" x-text="' (' + chartCondition().surfaces.join(',') + ')'"></span>
                                                </span>
                                            </div>
                                        </template>

                                        {{-- Treatment picker — a search box that opens a dropdown panel,
                                             not a permanently-visible list. Stays open while picking
                                             multiple treatments; closes on an outside click. --}}
                                        <div class="relative" @click.outside="chartListOpen = false">
                                            <input type="text" x-model="chartSearch" placeholder="Search treatments…"
                                                   @focus="chartListOpen = true" @click="chartListOpen = true"
                                                   class="tp-input" style="font-size:12.5px;" autocomplete="off">

                                            <div x-show="chartListOpen" x-cloak
                                                 style="position:absolute;left:0;right:0;top:calc(100% + 4px);z-index:20;
                                                        background:#fff;border:1px solid #e5e7eb;border-radius:8px;
                                                        box-shadow:0 12px 28px rgba(0,0,0,.1);max-height:280px;overflow-y:auto;">
                                                <template x-for="grp in chartFilteredGroups" :key="grp.name">
                                                    <div style="border-bottom:1px solid #f3f4f6;">
                                                        <button type="button" class="tx-cat-head" style="padding:6px 10px;" @click="chartToggleCategory(grp.name)">
                                                            <span class="tx-cat-name" x-text="grp.name"></span>
                                                            <span style="display:flex;align-items:center;">
                                                                <span class="tx-cat-count" x-show="chartCategorySelectedCount(grp.name) > 0" x-text="chartCategorySelectedCount(grp.name)"></span>
                                                                <svg class="tx-chevron" :class="chartCategoryOpen(grp.name) ? 'open' : ''"
                                                                     width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round">
                                                                    <path d="m6 9 6 6 6-6"/>
                                                                </svg>
                                                            </span>
                                                        </button>
                                                        <div x-show="chartCategoryOpen(grp.name)" x-collapse style="padding:0 6px 6px;">
                                                            <template x-for="tx in grp.items" :key="tx.id">
                                                                <button type="button" @click="chartToggleTx(tx.id)"
                                                                        class="tx-list-row"
                                                                        :class="chartSelectedIds.includes(tx.id) ? 'selected' : ''">
                                                                    <span class="tx-list-check">
                                                                        <svg x-show="chartSelectedIds.includes(tx.id)" width="10" height="10" viewBox="0 0 24 24" fill="none"
                                                                             stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round">
                                                                            <polyline points="20 6 9 17 4 12"/>
                                                                        </svg>
                                                                    </span>
                                                                    <span class="tx-list-name" x-text="tx.name"></span>
                                                                    <span class="tx-list-price" x-text="'Rs. ' + fmtInt(tx.price)"></span>
                                                                </button>
                                                            </template>
                                                        </div>
                                                    </div>
                                                </template>
                                                <template x-if="chartFilteredGroups.length === 0">
                                                    <div style="font-size:11.5px;color:#d1d5db;font-family:'Inter',sans-serif;padding:10px;">No treatments match "<span x-text="chartSearch"></span>".</div>
                                                </template>
                                            </div>
                                        </div>

                                        {{-- Selected treatments for this tooth — shown as a plain list below
                                             the search box, so the picker doesn't have to stay open to review them. --}}
                                        <template x-if="chartSelectedIds.length > 0">
                                            <div style="margin-top:12px;display:flex;flex-direction:column;gap:4px;">
                                                <template x-for="id in chartSelectedIds" :key="id">
                                                    <div class="tx-list-row selected" style="cursor:default;">
                                                        <span class="tx-list-check" style="background:#6a0f70;border-color:#6a0f70;">
                                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                                        </span>
                                                        <span class="tx-list-name" x-text="TP_TREATMENTS.find(t => t.id === id)?.name || ''"></span>
                                                        <span class="tx-list-price" x-text="'Rs. ' + fmtInt(TP_TREATMENTS.find(t => t.id === id)?.price || 0)"></span>
                                                        <button type="button" @click="chartToggleTx(id)" title="Remove"
                                                                style="background:none;border:none;color:#c4b5d4;cursor:pointer;font-size:14px;line-height:1;padding:0 2px;">&#xD7;</button>
                                                    </div>
                                                </template>
                                            </div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Footer --}}
                        <div style="padding:12px 18px;border-top:1px solid #f3f4f6;display:flex;align-items:center;justify-content:space-between;">
                            <span style="font-size:11px;color:#9ca3af;font-family:'Inter',sans-serif;"
                                  x-text="chartTooth ? (chartSelectedIds.length + ' treatment(s) selected for tooth ' + chartTooth) : ''"></span>
                            <div style="display:flex;gap:8px;">
                                <button type="button" @click="closeChart()" class="tp-btn tp-btn-ghost">Close</button>
                                <button type="button" @click="applyChartTooth()" :disabled="!chartTooth" class="tp-btn tp-btn-primary">Apply &amp; Chart Next Tooth</button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            {{-- Estimated total (read-only) --}}
            <div class="flex items-center justify-between px-4 py-3 rounded-lg bg-purple-50 border border-purple-100">
                <span class="text-xs font-bold text-[#6a0f70] uppercase tracking-wide">Estimated Investment</span>
                <span class="text-lg font-black text-[#6a0f70]">Rs.  <span x-text="fmtInt(grandTotal)"></span></span>
            </div>

            {{-- Doctor notes --}}
            <div class="tp-field-group mt-4">
                <label class="tp-label">Doctor Notes (optional)</label>
                <textarea x-model="form.doctor_notes" rows="2"
                          placeholder="Optional recommendation for this option…"
                          class="tp-input resize-none"></textarea>
            </div>

        </div>

        <div class="tp-form-foot">
            <div x-show="formError" class="text-sm text-red-600 mr-auto" x-text="formError"></div>
            <button @click="closeForm()" class="tp-btn tp-btn-ghost">Cancel</button>
            <button @click="saveForm()" :disabled="saving" dusk="tp-save" class="tp-btn tp-btn-primary">
                <svg x-show="saving" class="animate-spin" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/></svg>
                <span x-text="saving ? 'Saving…' : (editingId ? 'Update Option' : 'Save Option')"></span>
            </button>
        </div>
    </div>

    {{-- ── Empty state ── --}}
    <div x-show="plans.length === 0 && !formOpen"
         class="py-16 text-center bg-white border border-gray-200 rounded-xl">
        <div class="w-14 h-14 rounded-full bg-purple-50 flex items-center justify-center mx-auto mb-4">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </div>
        <p class="text-sm font-semibold text-gray-700 mb-1">No treatment options yet</p>
        <p class="text-xs text-gray-400 mb-4">Create one or more options for the patient to choose from.</p>
        <button @click="openNewForm()" class="tp-btn tp-btn-primary">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Create First Option
        </button>
    </div>

    {{-- ── Plans — grouped by consultation ── --}}
    <template x-for="group in groupedPlans" :key="group.consultationId">
        <div class="mb-8">

            {{-- Consultation header --}}
            <div class="tp-consult-header">
                <div>
                    <div class="tp-consult-date">
                        <template x-if="group.consultationDate">
                            Consultation · <span x-text="group.consultationDate"></span>
                        </template>
                        <template x-if="!group.consultationDate">
                            <span>No Linked Consultation</span>
                        </template>
                    </div>
                    <div x-show="group.complaint" class="tp-consult-complaint" x-text="group.complaint"></div>
                    <div x-show="group.diagnosis" class="tp-consult-diagnosis">Diagnosis: <span x-text="group.diagnosis"></span></div>
                </div>
                {{-- Print all options for this consultation --}}
                <a :href="printUrl(group.plans)"
                   target="_blank"
                   class="tp-btn tp-btn-ghost text-xs">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                    Print All Options
                </a>
            </div>

            {{-- Option cards --}}
            <div class="space-y-3">
                <template x-for="plan in group.plans" :key="plan.id">
                    <div class="tp-option-card" :class="{ 'is-accepted': plan.is_accepted }">

                        {{-- Card header (click to collapse / expand) --}}
                        <div class="tp-card-head" style="cursor:pointer;"
                             @click="toggleCollapse(plan.id)"
                             :title="isCollapsed(plan.id) ? 'Expand' : 'Collapse'">
                            <div>
                                <div class="tp-option-number">
                                    Treatment Option <span x-text="plan.display_order"></span>
                                </div>
                                <div class="tp-option-name" x-text="plan.plan_name"></div>
                            </div>
                            <div class="flex items-center gap-2">
                                <span x-show="plan.is_accepted" class="tp-badge-accepted">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                    Accepted <span x-show="plan.accepted_at" x-text="plan.accepted_at ? '· ' + plan.accepted_at : ''"></span>
                                </span>
                                <span x-show="!plan.is_accepted" class="tp-badge-pending">Pending</span>
                                {{-- Collapse / expand chevron --}}
                                <span class="tp-collapse-toggle" :class="{ 'is-collapsed': isCollapsed(plan.id) }">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                                </span>
                            </div>
                        </div>

                        {{-- Collapsible body (stats, treatments, notes, actions) --}}
                        <div x-show="!isCollapsed(plan.id)" x-collapse>

                        {{-- Stats row --}}
                        <div class="tp-card-body">
                            <div class="tp-stat">
                                <div class="tp-stat-label">Estimated Investment</div>
                                <div class="tp-stat-value is-investment">Rs. <span x-text="fmtInt(plan.total)"></span></div>
                            </div>
                            <div class="tp-stat">
                                <div class="tp-stat-label">Treatments</div>
                                <div class="tp-stat-value" x-text="plan.items.length"></div>
                                <div class="tp-stat-sub" x-text="plan.items.length === 1 ? '1 treatment' : plan.items.length + ' treatments'"></div>
                            </div>
                        </div>

                        {{-- Treatment list --}}
                        <div class="tp-card-procedures">
                            <div class="tp-proc-label">Treatment Summary</div>
                            <template x-for="item in plan.items" :key="item.id">
                                <div class="tp-proc-item">
                                    <span class="tp-proc-dot"></span>
                                    <template x-if="item.tooth_number">
                                        <span class="tp-tooth-badge" x-text="item.tooth_number"></span>
                                    </template>
                                    <span x-text="item.treatment_name"></span>
                                    <template x-if="item.units > 1"><span class="text-xs text-gray-400">&times; <span x-text="item.units"></span></span></template>
                                    <span class="ml-auto text-xs text-gray-400 font-medium">Rs. <span x-text="fmtInt(item.total)"></span></span>
                                </div>
                            </template>
                        </div>

                        {{-- Doctor notes --}}
                        <div x-show="plan.doctor_notes"
                             class="px-4 py-2.5 text-xs text-gray-500 italic border-t border-gray-100"
                             x-text="'Note: ' + plan.doctor_notes"></div>

                        {{-- Action footer --}}
                        <div class="tp-card-footer">
                            {{-- Mark as Accepted (only if not already accepted) --}}
                            <button x-show="!plan.is_accepted"
                                    @click="acceptPlan(plan)"
                                    :disabled="accepting === plan.id"
                                    class="tp-btn tp-btn-green">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                <span x-text="accepting === plan.id ? 'Saving…' : 'Mark as Accepted'"></span>
                            </button>

                            {{-- Revert acceptance (only if already accepted) — reason logged --}}
                            <button x-show="plan.is_accepted"
                                    @click="openRevert(plan)"
                                    class="tp-btn tp-btn-revert">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
                                Revert Acceptance
                            </button>

                            {{-- Print this option --}}
                            <a :href="printUrl([plan])" target="_blank" class="tp-btn tp-btn-ghost">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                Print
                            </a>

                            {{-- Generate Consent Form — Phase 2, docs/gap-analysis-treatment-planning-knowledge-bank.md.
                                 Always visible (2026-07-13 picker refinement): opens a picker
                                 to choose which treatment/tooth rows to generate for, rather
                                 than only working when items were pre-flagged in Edit. The
                                 consent_required flag still drives which rows come pre-checked. --}}
                            <button type="button" @click="openConsentPicker(plan)" class="tp-btn tp-btn-ghost">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                                Consent Form
                            </button>

                            {{-- Create Smart Presentation — new, independent module (2026-07-09).
                                 This is the ONLY touch point added to this file: a single link
                                 into Smart Treatment Presentation, which imports this plan
                                 read-only. See docs/plan-smart-treatment-presentation.md. --}}
                            <a :href="'{{ url('presentations/create-from-plan') }}/' + plan.id" class="tp-btn tp-btn-outline">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>
                                Create Smart Presentation
                            </a>

                            {{-- Bill from Plan (partial multi-tooth) — accepted plans only --}}
                            <a x-show="plan.is_accepted"
                               :href="'{{ url('billing/from-plan') }}/' + plan.id"
                               class="tp-btn tp-btn-green">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                Bill from Plan
                            </a>

                            {{-- Edit --}}
                            <button @click="openEditForm(plan)" class="tp-btn tp-btn-outline">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                Edit
                            </button>

                            {{-- Delete --}}
                            <button @click="deletePlan(plan.id)"
                                    class="tp-btn tp-btn-danger">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                                Delete
                            </button>
                        </div>

                        </div>{{-- /collapsible body --}}
                    </div>
                </template>

                {{-- Add another option to this consultation --}}
                <button @click="openNewForm(group.consultationId)"
                        class="w-full flex items-center justify-center gap-2 py-2.5 border border-dashed border-gray-200 rounded-lg text-xs font-semibold text-gray-400 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors">
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add Another Option for This Consultation
                </button>
            </div>
        </div>
    </template>

</div>

@push('scripts')
<script>
const TP_TREATMENTS = @json($treatmentsJson);
const TP_CONSULTATIONS = @json($consultationsJson);

function treatmentPlanTab() {
    return {
        // Tooth-picker state + helpers (activeToothPicker, dentitionMode,
        // codeAt, isToothSelected, toggleTooth, clearTeeth, syncTeeth, ...) —
        // shared with the Lab Case tooth chart. See tooth-chart-assets.blade.php.
        ...toothChartMixin(),

        plans: @json($plansJson),

        // ── Form state ──────────────────────────────────────────────────────
        formOpen:  false,
        saving:    false,
        formError: '',
        editingId: null,
        form: {},

        // ── Autocomplete ────────────────────────────────────────────────────
        activeSuggest:    null,
        suggestions:      [],

        // ── Accept state ────────────────────────────────────────────────────
        accepting: null,

        // ── Collapse / expand state (keyed by plan id) ──────────────────────
        collapsed: {},
        isCollapsed(id)   { return !!this.collapsed[id]; },
        toggleCollapse(id) { this.collapsed[id] = !this.collapsed[id]; },

        // ── Revert acceptance state ─────────────────────────────────────────
        revertOpen:     false,
        revertPlanId:   null,
        revertPlanName: '',
        revertReason:   '',
        revertError:    '',
        reverting:      false,

        // ── Print picker ────────────────────────────────────────────────────
        printPickerOpen: false,
        printSelected:   [],

        // ── Consent form picker (2026-07-13) ────────────────────────────────
        // Explodes each plan item into one row per tooth (mirrors
        // ConsentDocumentService::buildSections on the backend) so staff pick
        // exactly which treatment/tooth combos to generate a consent doc for.
        consentPickerOpen: false,
        consentPlanId:     null,
        consentRows:       [],

        openConsentPicker(plan) {
            this.consentPlanId = plan.id;
            this.consentRows = [];
            plan.items.forEach(item => {
                const teeth = String(item.tooth_number || '')
                    .split(',').map(t => t.trim()).filter(Boolean);
                if (!teeth.length) {
                    this.consentRows.push({
                        key:     item.id + '|',
                        label:   item.treatment_name,
                        checked: !!item.consent_required,
                    });
                    return;
                }
                teeth.forEach(tooth => {
                    this.consentRows.push({
                        key:     item.id + '|' + tooth,
                        label:   item.treatment_name + ' — Tooth ' + tooth,
                        checked: !!item.consent_required,
                    });
                });
            });
            this.consentPickerOpen = true;
        },
        toggleConsentRow(key) {
            const row = this.consentRows.find(r => r.key === key);
            if (row) row.checked = !row.checked;
        },
        selectAllConsent() { this.consentRows.forEach(r => r.checked = true); },
        clearConsent()     { this.consentRows.forEach(r => r.checked = false); },
        generateConsentForm() {
            const chosen = this.consentRows.filter(r => r.checked).map(r => r.key);
            if (!chosen.length) return;
            const params = chosen.map(k => 'sel[]=' + encodeURIComponent(k)).join('&');
            window.open('{{ url('treatment-plans') }}/' + this.consentPlanId + '/consent?' + params, '_blank');
            this.consentPickerOpen = false;
        },

        // ── Chart-by-Tooth treatment picker (Slice 4/5) ─────────────────────
        // Static condition palette — mirrors the consultation tooth chart
        // (resources/views/consultations/create.blade.php) so a condition
        // read on examination shows in the same colour here. Kept as a small
        // duplicated lookup table rather than a shared JS file — it's static
        // reference data, not logic, so the duplication risk is low.
        CHART_CONDITIONS: [
            { key: 'crown',     label: 'Crown / Bridge',      color: '#d97706' },
            { key: 'composite', label: 'Filling (Composite)', color: '#2563eb' },
            { key: 'amalgam',   label: 'Silver Filling',      color: '#475569' },
            { key: 'veneer',    label: 'Veneer',              color: '#7c3aed' },
            { key: 'rct',       label: 'RCT + Crown',         color: '#ea580c' },
            { key: 'rct_only',  label: 'RCT',                 color: '#db2777' },
            { key: 'implant',   label: 'Implant + Crown',     color: '#0891b2' },
            { key: 'mobile',    label: 'Mobile Tooth',        color: '#ca8a04' },
            { key: 'missing',   label: 'Missing',             color: '#dc2626' },
            { key: 'cavity',    label: 'Cavity',              color: '#991b1b' },
            { key: 'other',     label: 'Other',               color: '#6b7280' },
        ],
        TOOTH_CONDITIONS: @json($latestToothConditions ?? []), // { toothNumber: {condition, custom, surfaces} } — as last examined

        // Region targets for treatments that apply to more than one tooth at
        // once (full-mouth extraction, full-arch denture, etc.) — same
        // vocabulary as the plain tooth-picker used elsewhere in this file
        // (partials/tooth-chart.blade.php), so tooth_number values stay
        // consistent across both entry points.
        REGIONS: ['Full Arch', 'UL', 'UR', 'LL', 'LR'],

        chartOpen:              false,
        chartTooth:             null,
        chartSelectedIds:       [],
        chartSearch:            '',
        chartListOpen:          false, // treatment search dropdown — closed until the search box is used
        chartExpandedCategories: [], // category names currently expanded within the dropdown

        openChart() {
            this.chartOpen               = true;
            this.chartTooth              = null;
            this.chartSelectedIds        = [];
            this.chartSearch             = '';
            this.chartListOpen           = false;
            this.chartExpandedCategories = [];
        },
        closeChart() {
            this.chartOpen        = false;
            this.chartTooth       = null;
            this.chartSelectedIds = [];
            this.chartListOpen    = false;
        },
        chartClickTooth(code) {
            this.chartTooth    = code;
            this.chartSearch   = '';
            this.chartListOpen = false;
            // Pre-select whatever's already been charted for this tooth in the
            // current form, so reopening it doesn't lose or duplicate work.
            const toothStr = String(code);
            this.chartSelectedIds = this.form.items
                .filter(i => i.tooth_number === toothStr && i.treatment_id)
                .map(i => i.treatment_id);
            // Auto-expand any category that already has a selection, so the
            // dentist isn't hunting through collapsed categories for it.
            this.chartExpandedCategories = this.groupedTreatments
                .filter(g => g.items.some(t => this.chartSelectedIds.includes(t.id)))
                .map(g => g.name);
        },
        chartToggleCategory(name) {
            const i = this.chartExpandedCategories.indexOf(name);
            if (i > -1) this.chartExpandedCategories.splice(i, 1);
            else this.chartExpandedCategories.push(name);
        },
        chartCategoryOpen(name) {
            // A search in progress always shows matches, regardless of collapse state.
            return !!this.chartSearch || this.chartExpandedCategories.includes(name);
        },
        chartCategorySelectedCount(name) {
            const grp = this.groupedTreatments.find(g => g.name === name);
            return grp ? grp.items.filter(t => this.chartSelectedIds.includes(t.id)).length : 0;
        },
        chartToggleTx(id) {
            const i = this.chartSelectedIds.indexOf(id);
            if (i > -1) this.chartSelectedIds.splice(i, 1);
            else this.chartSelectedIds.push(id);
        },
        chartCategoryColorFor(treatmentId) {
            return TP_TREATMENTS.find(t => t.id === treatmentId)?.categoryColor || '#6a0f70';
        },
        // Odontogram fill for a tooth/region button — two independent signals:
        //   - solid fill (category colour) = a treatment is already planned here
        //   - light outline (condition colour) = what was found on examination,
        //     pulled from TOOTH_CONDITIONS, shown even before anything is planned
        // Planned takes visual priority since it's the more actionable state.
        chartToothStyle(code) {
            const s = String(code);
            const items = this.form.items.filter(i => i.tooth_number === s && i.treatment_id);
            if (items.length) {
                const color = this.chartCategoryColorFor(items[0].treatment_id);
                return { background: color, borderColor: color, color: '#fff', fontWeight: '700' };
            }
            const cond = this.TOOTH_CONDITIONS[code];
            if (cond && cond.condition) {
                const meta = this.CHART_CONDITIONS.find(x => x.key === cond.condition);
                const color = meta?.color || '#9ca3af';
                return { borderColor: color, color: color, background: color + '14', fontWeight: '700' };
            }
            return {};
        },
        // What was found on examination for this tooth — shown faded/read-only
        // above the treatment list. Pulled from TOOTH_CONDITIONS (built server-
        // side from the patient's consultation chart_data), not editable here.
        chartCondition() {
            const c = this.TOOTH_CONDITIONS[this.chartTooth];
            if (!c || !c.condition) return null;
            const meta = this.CHART_CONDITIONS.find(x => x.key === c.condition);
            return {
                label:    c.condition === 'other' ? (c.custom || 'Other') : (meta?.label || c.condition),
                color:    meta?.color || '#9ca3af',
                surfaces: c.surfaces || [],
            };
        },
        // Treatments grouped by category for the picker — reuses TP_TREATMENTS
        // (already loaded for the autocomplete) rather than a second dataset.
        get groupedTreatments() {
            const map = new Map();
            TP_TREATMENTS.forEach(t => {
                const key = t.category || 'Other';
                if (!map.has(key)) map.set(key, []);
                map.get(key).push(t);
            });
            return Array.from(map.entries()).map(([name, items]) => ({ name, items }));
        },
        get chartFilteredGroups() {
            const q = (this.chartSearch || '').toLowerCase().trim();
            return this.groupedTreatments
                .map(g => ({ name: g.name, items: q ? g.items.filter(t => t.name.toLowerCase().includes(q)) : g.items }))
                .filter(g => g.items.length > 0);
        },
        // Reconciles form.items for the active tooth against chartSelectedIds:
        // drops any tooth+treatment combo that got deselected, adds any new
        // one. Matching is by (tooth_number, treatment_id), so this never
        // creates a duplicate of a treatment added manually for the same tooth.
        applyChartTooth() {
            if (!this.chartTooth) return;
            const toothStr = String(this.chartTooth);

            this.form.items = this.form.items.filter(i =>
                !(i.tooth_number === toothStr && i.treatment_id && !this.chartSelectedIds.includes(i.treatment_id))
            );

            this.chartSelectedIds.forEach(id => {
                const already = this.form.items.some(i => i.tooth_number === toothStr && i.treatment_id === id);
                if (already) return;
                const tx = TP_TREATMENTS.find(t => t.id === id);
                if (!tx) return;
                this.form.items.push({
                    treatment_id:     tx.id,
                    tooth_number:     toothStr,
                    teeth:            [toothStr],
                    units:            1,
                    treatment_name:   tx.name,
                    unit_price:       tx.price,
                    notes:            '',
                    variants:         [],
                    showVariants:     false,
                    showNotes:        false,
                    consent_required: !!tx.consent_required,
                });
            });

            // Back to the tooth grid so the dentist can chart the next tooth
            // without re-opening the modal — mirrors "click tooth to mark".
            this.chartTooth       = null;
            this.chartSelectedIds = [];
            this.chartSearch      = '';
        },

        // ── Init ────────────────────────────────────────────────────────────
        init() {
            this.form = this._blankForm();

            // P2C10c handoff: auto-open if redirected from consultation
            @if(session('from_consultation_id'))
            this.openNewForm({{ session('from_consultation_id') }});
            @endif
        },

        // ── Grouped plans (by consultation) ─────────────────────────────────
        get groupedPlans() {
            const map = new Map();

            this.plans.forEach(p => {
                const key = p.consultation_id ?? 'none';
                if (!map.has(key)) {
                    const consult = TP_CONSULTATIONS.find(c => c.id === p.consultation_id);
                    map.set(key, {
                        consultationId:   p.consultation_id,
                        consultationDate: consult ? consult.label.split(' — ')[0] : null,
                        complaint:        consult?.chief_complaint || '',
                        diagnosis:        consult?.diagnosis || '',
                        plans:            [],
                    });
                }
                map.get(key).plans.push(p);
            });

            return Array.from(map.values());
        },

        // ── Computed total for the form ──────────────────────────────────────
        get grandTotal() {
            return this.form.items?.reduce((s, i) => {
                // If item has variants, use the selected variant price; else unit_price
                const selectedV = i.variants?.find(v => v.selected);
                const price = selectedV ? (parseFloat(selectedV.price) || 0) : (parseFloat(i.unit_price) || 0);
                const qty = parseInt(i.units) || 1;   // multiply by quantity (teeth count)
                return s + price * qty;
            }, 0) ?? 0;
        },

        // ── Print URL ─────────────────────────────────────────────────────────
        printUrl(plans) {
            const ids = plans.map(p => p.id);
            return '{{ url('/treatment-plans/print') }}?' + ids.map(id => 'ids[]=' + id).join('&');
        },

        // ── Print picker ──────────────────────────────────────────────────────
        openPrintPicker() {
            this.printSelected = this.plans.map(p => p.id);   // default: all selected
            this.printPickerOpen = true;
        },
        togglePrintSelect(id) {
            const i = this.printSelected.indexOf(id);
            if (i > -1) this.printSelected.splice(i, 1);
            else this.printSelected.push(id);
        },
        selectAllPrint() { this.printSelected = this.plans.map(p => p.id); },
        clearPrint()     { this.printSelected = []; },
        printSelectedPlans() {
            if (!this.printSelected.length) return;
            const chosen = this.plans.filter(p => this.printSelected.includes(p.id));
            window.open(this.printUrl(chosen), '_blank');
            this.printPickerOpen = false;
        },

        // ── Blank objects ─────────────────────────────────────────────────────
        _blankForm(consultationId = null) {
            const optNum = consultationId
                ? (this.plans.filter(p => p.consultation_id === consultationId).length + 1)
                : (this.plans.length + 1);
            return {
                plan_name:          'Treatment Option ' + optNum,
                consultation_id:    consultationId ?? '',
                estimated_duration: '',
                visit_count:        '',
                doctor_notes:       '',
                items:              [],
            };
        },
        _blankItem() {
            return { treatment_id: null, tooth_number: '', teeth: [], units: 1, treatment_name: '', unit_price: 0, notes: '', variants: [], showVariants: false, showNotes: false, consent_required: false };
        },

        // Tooth multi-select helpers (isToothSelected, toggleTooth, clearTeeth,
        // syncTeeth) now come from toothChartMixin() spread in above.

        // ── Form open / close ─────────────────────────────────────────────────
        openNewForm(consultationId = null) {
            this.editingId  = null;
            this.form       = this._blankForm(consultationId);
            this.suggestions = [];
            this.formError  = '';
            this.formOpen   = true;
            this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        },
        openEditForm(plan) {
            try {
                const items = Array.isArray(plan.items) ? plan.items : [];
                this.editingId = plan.id;
                this.form = {
                    plan_name:          plan.plan_name ?? '',
                    consultation_id:    plan.consultation_id ?? '',
                    estimated_duration: plan.estimated_duration ?? '',
                    visit_count:        plan.visit_count ?? '',
                    doctor_notes:       plan.doctor_notes ?? '',
                    items:              items.map(i => ({
                        id:               i.id,
                        treatment_id:     i.treatment_id ?? null,
                        tooth_number:     i.tooth_number ?? '',
                        teeth:            (i.tooth_number ? String(i.tooth_number).split(',').map(s => s.trim()).filter(Boolean) : []),
                        units:            i.units ?? 1,
                        treatment_name:   i.treatment_name ?? '',
                        unit_price:       i.unit_price ?? 0,
                        notes:            i.notes ?? '',
                        variants:         Array.isArray(i.variants) ? i.variants : [],
                        showVariants:     !!(i.variants && i.variants.length > 0),
                        showNotes:        !!(i.notes && i.notes.length > 0),
                        consent_required: !!i.consent_required,
                    })),
                };
                this.suggestions = [];
                this.formError   = '';
                this.formOpen    = true;
                this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
            } catch (e) {
                console.error('openEditForm failed:', e);
                alert('Could not open the edit form: ' + e.message);
            }
        },
        closeForm() {
            this.formOpen  = false;
            this.editingId = null;
        },

        // ── Items ─────────────────────────────────────────────────────────────
        addItem() {
            this.form.items.push(this._blankItem());
        },
        removeItem(idx) {
            this.form.items.splice(idx, 1);
            this.suggestions.splice(idx, 1);
        },
        // ── Autocomplete ──────────────────────────────────────────────────────
        filterTx(idx) {
            const q = (this.form.items[idx]?.treatment_name || '').toLowerCase().trim();
            if (!q || q.length < 2) {
                this.suggestions[idx] = [];
                return;
            }
            this.suggestions[idx] = TP_TREATMENTS
                .filter(t => t.name.toLowerCase().includes(q))
                .slice(0, 8);
        },
        selectSuggest(idx, sug) {
            this.form.items[idx].treatment_id      = sug.id;
            this.form.items[idx].treatment_name    = sug.name;
            this.form.items[idx].unit_price        = sug.price;
            // Default from the treatment's own "Consent form required" rule
            // (Treatment Library → Rules) — staff can still toggle it off/on
            // for this specific plan item below.
            this.form.items[idx].consent_required  = !!sug.consent_required;
            this.suggestions[idx] = [];
            this.activeSuggest    = null;
        },
        hideSuggestDelayed() {
            setTimeout(() => { this.activeSuggest = null; }, 200);
        },

        // ── Save ──────────────────────────────────────────────────────────────
        async saveForm() {
            if (!this.form.plan_name?.trim()) {
                this.formError = 'Option name is required.';
                return;
            }
            if (!this.form.items.length) {
                this.formError = 'Add at least one treatment.';
                return;
            }
            this.saving    = true;
            this.formError = '';

            const isEdit = !!this.editingId;
            const url    = isEdit
                ? `{{ url('/treatment-plans') }}/${this.editingId}`
                : `{{ url('/patients/' . $patient->id . '/treatment-plans') }}`;

            try {
                const resp = await fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        plan_name:          this.form.plan_name,
                        consultation_id:    this.form.consultation_id || null,
                        estimated_duration: this.form.estimated_duration || null,
                        visit_count:        this.form.visit_count || null,
                        doctor_notes:       this.form.doctor_notes || null,
                        items:              this.form.items,
                    }),
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) throw new Error(data.message || 'Save failed.');

                if (isEdit) {
                    const idx = this.plans.findIndex(p => p.id === this.editingId);
                    if (idx > -1) this.plans[idx] = data.plan;
                } else {
                    this.plans.push(data.plan);
                }
                this.closeForm();
            } catch (e) {
                this.formError = e.message;
            } finally {
                this.saving = false;
            }
        },


        // ── Accept Plan ───────────────────────────────────────────────────────
        async acceptPlan(plan) {
            if (this.accepting === plan.id) return;
            this.accepting = plan.id;
            try {
                const resp = await fetch(`{{ url('/treatment-plans') }}/${plan.id}/accept`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) throw new Error(data.message || 'Accept failed.');
                // Update plan in list
                const idx = this.plans.findIndex(p => p.id === plan.id);
                if (idx > -1) this.plans[idx] = data.plan;
            } catch (e) {
                alert(e.message);
            } finally {
                this.accepting = null;
            }
        },

        // ── Revert acceptance ───────────────────────────────────────────────────
        openRevert(plan) {
            this.revertPlanId   = plan.id;
            this.revertPlanName = plan.plan_name;
            this.revertReason   = '';
            this.revertError    = '';
            this.revertOpen     = true;
        },
        closeRevert() {
            this.revertOpen   = false;
            this.revertPlanId = null;
            this.reverting    = false;
        },
        async confirmRevert() {
            if (!this.revertReason.trim()) {
                this.revertError = 'Please enter a reason.';
                return;
            }
            this.reverting   = true;
            this.revertError = '';
            try {
                const resp = await fetch(`{{ url('/treatment-plans') }}/${this.revertPlanId}/revert`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ reason: this.revertReason.trim() }),
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) throw new Error(data.message || 'Revert failed.');
                // Update plan in list
                const idx = this.plans.findIndex(p => p.id === this.revertPlanId);
                if (idx > -1) this.plans[idx] = data.plan;
                this.closeRevert();
            } catch (e) {
                this.revertError = e.message;
            } finally {
                this.reverting = false;
            }
        },

        // ── Delete Plan ───────────────────────────────────────────────────────
        async deletePlan(id) {
            if (!confirm('Delete this treatment option? This cannot be undone.')) return;
            try {
                const resp = await fetch(`{{ url('/treatment-plans') }}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) throw new Error(data.message || 'Delete failed.');
                this.plans = this.plans.filter(p => p.id !== id);
            } catch (e) {
                alert(e.message);
            }
        },

        fmt(n)    { return Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        fmtInt(n) { return Number(n).toLocaleString('en-IN', { maximumFractionDigits: 0 }); },
    };
}
</script>
@endpush
