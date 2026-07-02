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
                'id'             => $i->id,
                'tooth_number'   => $i->tooth_number,
                'units'          => (int)($i->units ?? 1),
                'treatment_name' => $i->treatment_name,
                'unit_price'     => (float)$i->unit_price,
                'total'          => (float)$i->total,
                'notes'          => $i->notes,
                'sort_order'     => (int)$i->sort_order,
            ])->values()->all(),
        ])->values();

    // Treatments for autocomplete
    $treatmentsJson = ($treatments ?? collect())->map(fn($t) => [
        'id'    => $t->id,
        'name'  => $t->name,
        'price' => (float)($t->default_price ?? 0),
    ]);

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
    .tp-tooth-btn span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    /* qty field */
    .tp-qty-input { text-align: center; }
    /* tooth popup footer */
    .tp-tooth-foot { display: flex; align-items: center; justify-content: space-between; margin-top: 8px; padding-top: 6px; border-top: 1px solid #f3e8ff; }
    .tp-tooth-count { font-size: 11px; color: #6a0f70; font-weight: 700; }
    .tp-tooth-foot button { font-size: 11px; font-weight: 700; font-family: 'Inter', sans-serif; border-radius: 5px; padding: 4px 12px; cursor: pointer; border: none; }
    .tp-tooth-done { background: #6a0f70; color: #fff; }
    .tp-tooth-clear { background: #fff; color: #6b7280; border: 1px solid #e5e7eb !important; }
    .tp-proc-row:hover { border-color: #e8d5f0; background: #fdf8ff; }
    .tp-proc-wrap { margin-bottom: 8px; }
    .tp-tooth-badge { display: inline-block; font-size: 10px; font-weight: 700; color: #6a0f70; background: #f3e8ff; border-radius: 4px; padding: 1px 5px; margin-right: 4px; }

    /* tooth picker button */
    .tp-tooth-btn {
        width: 100%; height: 34px; border: 1px solid #e5e7eb; border-radius: 6px;
        background: #fff; cursor: pointer; font-size: 11px; font-weight: 700;
        color: #6a0f70; font-family: 'Inter', sans-serif;
        display: flex; align-items: center; justify-content: center; gap: 4px;
        transition: border-color .15s, background .15s;
    }
    .tp-tooth-btn:hover { border-color: #6a0f70; background: #fdf4ff; }

    /* tooth chart popup */
    .tp-tooth-popup {
        position: absolute; top: calc(100% + 4px); left: 0; z-index: 9999;
        background: #fff; border: 1.5px solid #e9d5ff; border-radius: 10px;
        box-shadow: 0 8px 24px rgba(106,15,112,.13);
        padding: 10px 12px; width: 340px;
    }
    .tp-tooth-grid {
        display: grid; grid-template-columns: repeat(16, 1fr); gap: 2px; margin-bottom: 4px;
    }
    .tp-tooth-grid-lower { margin-bottom: 0; margin-top: 4px; }
    .tp-tooth-cell {
        font-size: 9px; font-weight: 700; font-family: 'Inter', sans-serif;
        border: 1px solid #e5e7eb; border-radius: 3px; padding: 3px 0;
        text-align: center; cursor: pointer; color: #374151; background: #fff;
        transition: background .1s, color .1s;
    }
    .tp-tooth-cell:hover { background: #f3e8ff; color: #6a0f70; border-color: #6a0f70; }
    .tp-tooth-cell.selected { background: #6a0f70; color: #fff; border-color: #6a0f70; }
    .tp-tooth-midline { grid-column: span 16; height: 1px; background: #e9d5ff; margin: 2px 0; }
    .tp-tooth-labels { display: flex; justify-content: space-between; font-size: 8px; color: #9ca3af; margin-bottom: 3px; font-family: 'Inter', sans-serif; }

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
</style>

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
                    <span class="text-xs text-gray-400" x-text="form.items.length + ' treatment(s)'"></span>
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

                            {{-- Tooth picker --}}
                            <div class="relative" @click.outside="if(activeToothPicker===idx) activeToothPicker=null">
                                <button type="button" class="tp-tooth-btn"
                                        @click.stop="activeToothPicker = (activeToothPicker===idx ? null : idx)">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><path d="M8 12h8M12 8v8"/></svg>
                                    <span x-text="item.tooth_number || 'Tooth'"></span>
                                </button>
                                {{-- Tooth chart popup (multi-select) --}}
                                <div x-show="activeToothPicker === idx" class="tp-tooth-popup" x-cloak>
                                    <div style="font-size:10px;color:#9ca3af;margin-bottom:5px;">Tap teeth to select one or more — quantity updates automatically.</div>
                                    <div class="tp-tooth-labels"><span>Upper Right &#x2192;</span><span>&#x2190; Upper Left</span></div>
                                    <div class="tp-tooth-grid">
                                        <template x-for="t in [18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28]" :key="'u'+t">
                                            <div class="tp-tooth-cell"
                                                 :class="isToothSelected(item, t) ? 'selected' : ''"
                                                 @click="toggleTooth(item, t)"
                                                 x-text="t"></div>
                                        </template>
                                        <div class="tp-tooth-midline"></div>
                                        <template x-for="t in [48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38]" :key="'l'+t">
                                            <div class="tp-tooth-cell"
                                                 :class="isToothSelected(item, t) ? 'selected' : ''"
                                                 @click="toggleTooth(item, t)"
                                                 x-text="t"></div>
                                        </template>
                                    </div>
                                    <div class="tp-tooth-labels" style="margin-top:3px;"><span>Lower Right &#x2192;</span><span>&#x2190; Lower Left</span></div>
                                    <div style="display:flex;gap:6px;margin-top:6px;flex-wrap:wrap;">
                                        <template x-for="lbl in ['UL','UR','LL','LR','Full Arch','Multiple']" :key="lbl">
                                            <div class="tp-tooth-cell" style="padding:3px 6px;font-size:9px;"
                                                 :class="isToothSelected(item, lbl) ? 'selected' : ''"
                                                 @click="toggleTooth(item, lbl)"
                                                 x-text="lbl"></div>
                                        </template>
                                    </div>
                                    {{-- Footer: count + actions --}}
                                    <div class="tp-tooth-foot">
                                        <span class="tp-tooth-count" x-text="(item.teeth?.length || 0) + ' selected'"></span>
                                        <div style="display:flex;gap:6px;">
                                            <button type="button" class="tp-tooth-clear" @click="clearTeeth(item)">Clear</button>
                                            <button type="button" class="tp-tooth-done" @click="activeToothPicker=null">Done</button>
                                        </div>
                                    </div>
                                </div>
                            </div>

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

        // ── Tooth picker ────────────────────────────────────────────────────
        activeToothPicker: null,

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
            return { tooth_number: '', teeth: [], units: 1, treatment_name: '', unit_price: 0, notes: '', variants: [], showVariants: false, showNotes: false };
        },

        // ── Tooth multi-select helpers ───────────────────────────────────────
        isToothSelected(item, t) {
            return Array.isArray(item.teeth) && item.teeth.includes(String(t));
        },
        toggleTooth(item, t) {
            t = String(t);
            if (!Array.isArray(item.teeth)) item.teeth = [];
            const i = item.teeth.indexOf(t);
            if (i > -1) item.teeth.splice(i, 1);
            else item.teeth.push(t);
            this.syncTeeth(item);
        },
        clearTeeth(item) {
            item.teeth = [];
            this.syncTeeth(item);
        },
        // Keep tooth_number string + qty (units) in sync with the selected teeth.
        // Numeric teeth are sorted; region labels (UL, Full Arch…) kept as-is.
        syncTeeth(item) {
            const nums = item.teeth.filter(t => /^\d+$/.test(t)).sort((a, b) => parseInt(a) - parseInt(b));
            const labels = item.teeth.filter(t => !/^\d+$/.test(t));
            item.teeth = [...nums, ...labels];
            item.tooth_number = item.teeth.join(', ');
            // Auto quantity = number of teeth selected (min 1 so a no-tooth treatment still counts as 1)
            item.units = Math.max(item.teeth.length, 1);
        },

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
                        id:             i.id,
                        tooth_number:   i.tooth_number ?? '',
                        teeth:          (i.tooth_number ? String(i.tooth_number).split(',').map(s => s.trim()).filter(Boolean) : []),
                        units:          i.units ?? 1,
                        treatment_name: i.treatment_name ?? '',
                        unit_price:     i.unit_price ?? 0,
                        notes:          i.notes ?? '',
                        variants:       Array.isArray(i.variants) ? i.variants : [],
                        showVariants:   !!(i.variants && i.variants.length > 0),
                        showNotes:      !!(i.notes && i.notes.length > 0),
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
            this.form.items[idx].treatment_name = sug.name;
            this.form.items[idx].unit_price     = sug.price;
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
