@php
    $treatmentsList  = $treatments ?? collect();   // Treatment model collection
    $consultationsList = $consultations ?? collect(); // for linking plans to consultations
$plansJson = ($patient->treatmentPlans ?? collect())->map(function($p) {
        return [
            'id'               => $p->id,
            'plan_name'        => $p->plan_name,
            'plan_type'        => $p->plan_type,
            'status'           => $p->status,
            'overall_disc_pct' => (float)$p->overall_disc_pct,
            'total'            => (float)$p->total,
            'consultation_id'  => $p->consultation_id,
            'created_by_name'  => $p->creator?->name,
            'created_at'       => $p->created_at?->format('d M Y'),
            'items'            => $p->items->map(fn($i) => [
                'id'             => $i->id,
                'tooth_number'   => $i->tooth_number,
                'treatment_name' => $i->treatment_name,
                'unit_price'     => (float)$i->unit_price,
                'units'          => (int)$i->units,
                'disc_pct'       => (float)$i->disc_pct,
                'disc_amount'    => (float)$i->disc_amount,
                'net_amount'     => (float)$i->net_amount,
                'gst_pct'        => (float)$i->gst_pct,
                'gst_amount'     => (float)$i->gst_amount,
                'total'          => (float)$i->total,
                'aocp_applied'   => (bool)$i->aocp_applied,
                'option_rank'    => $i->option_rank,
                'status'         => $i->status,
                'notes'          => $i->notes,
            ])->values()->all(),
        ];
    });

    $treatmentsJson = $treatmentsList->map(fn($t) => [
        'id'    => $t->id,
        'name'  => $t->name,
'price' => (float)($t->default_price ?? 0),    ]);
@endphp

<style>
    .tp-legend { font-size:10px;font-weight:700;color:#6a0f70;text-transform:uppercase;letter-spacing:.07em;margin-bottom:10px;display:flex;align-items:center;gap:6px; }
    .tp-legend::after { content:'';flex:1;height:1px;background:#f3f4f6; }
    .tp-input { border:1px solid #e5e7eb;border-radius:6px;padding:6px 10px;font-size:13px;color:#374151;background:white;outline:none;transition:border-color .15s;width:100%; }
    .tp-input:focus { border-color:#6a0f70; }
    .tp-rank-best { background:#dcfce7;color:#166534;border-color:#86efac; }
    .tp-rank-acceptable { background:#fef9c3;color:#854d0e;border-color:#fde047; }
    .tp-rank-alternative { background:#f3f4f6;color:#4b5563;border-color:#d1d5db; }
    .tp-status-pending { background:#f3f4f6;color:#6b7280; }
    .tp-status-ongoing { background:#dbeafe;color:#1d4ed8; }
    .tp-status-completed { background:#dcfce7;color:#166534; }
    .tp-status-cancelled { background:#fee2e2;color:#dc2626; }
</style>
<div x-show="activeTab === 'treatment-plan'" style="display:none">
<div
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-data="treatmentPlan()"
    x-init="init()"
    class="w-full px-6 py-6"
>
>
>

    {{-- ── Page Header ── --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h3 class="text-base font-bold text-gray-900">Treatment Plans</h3>
            <p class="text-xs text-gray-400 mt-0.5">AI-assisted planning · Billing managed by front desk</p>
        </div>
        <div class="flex items-center gap-2">
            <button @click="openAiPanel()"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm border border-[#6a0f70] text-[#6a0f70] hover:bg-[#f5eef9] font-medium rounded-lg transition-colors">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10"/><path d="M12 8v4l3 3"/><circle cx="19" cy="5" r="3"/></svg>
                AI Suggest
            </button>
            <button @click="openNewPlanForm()"
                    class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-[#6a0f70] text-white hover:bg-[#380740] font-medium rounded-lg transition-colors shadow-sm">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Plan
            </button>
        </div>
    </div>

    {{-- ── AI Suggestion Panel ── --}}
    <div x-show="aiPanelOpen" x-collapse
         class="mb-6 bg-gradient-to-br from-purple-50 to-indigo-50 border border-purple-200 rounded-xl overflow-hidden">
        <div class="px-6 py-4 border-b border-purple-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full bg-[#6a0f70] flex items-center justify-center">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a10 10 0 1 0 10 10"/><path d="M12 8v4l3 3"/></svg>
                </div>
                <span class="text-sm font-bold text-[#6a0f70]">AI Treatment Suggestion</span>
                <span class="text-xs text-purple-400">Based on consultation findings</span>
            </div>
            <button @click="aiPanelOpen = false" class="text-gray-400 hover:text-gray-600">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="px-6 py-5">
            {{-- Show what was fetched from consultation --}}
            <div x-show="aiForm.chief_complaint || aiForm.diagnosis"
                 class="mb-4 p-3 bg-white border border-purple-100 rounded-lg">
                <div class="text-xs font-semibold text-[#6a0f70] mb-2">Fetched from latest consultation:</div>
                <div class="grid grid-cols-2 gap-3">
                    <div x-show="aiForm.chief_complaint">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Chief Complaint</span>
                        <p class="text-xs text-gray-700 mt-0.5" x-text="aiForm.chief_complaint"></p>
                    </div>
                    <div x-show="aiForm.diagnosis">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Diagnosis</span>
                        <p class="text-xs text-gray-700 mt-0.5" x-text="aiForm.diagnosis"></p>
                    </div>
                    <div x-show="aiForm.radiographic_notes">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Radiographic</span>
                        <p class="text-xs text-gray-700 mt-0.5" x-text="aiForm.radiographic_notes"></p>
                    </div>
                    <div x-show="aiForm.examination_notes">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wide">Examination</span>
                        <p class="text-xs text-gray-700 mt-0.5" x-text="aiForm.examination_notes"></p>
                    </div>
                </div>
            </div>
            <div x-show="!aiForm.chief_complaint && !aiForm.diagnosis"
                 class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-700">
                No consultation found for this patient. Please add a consultation first.
            </div>
            </div>
            <div x-show="aiError" class="mb-3 px-4 py-2.5 bg-red-50 border border-red-200 rounded-lg text-sm text-red-600" x-text="aiError"></div>
            <div class="flex items-center gap-3">
                <button @click="runAiSuggest()"
                        :disabled="aiLoading"
                        class="inline-flex items-center gap-2 px-5 py-2.5 bg-[#6a0f70] text-white text-sm font-semibold rounded-lg hover:bg-[#380740] disabled:opacity-60 transition-colors">
                    <svg x-show="aiLoading" class="animate-spin" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/></svg>
                    <span x-text="aiLoading ? 'Analysing…' : 'Regenerate Plan'"></span>
                </button>
                <span class="text-xs text-gray-400">Results will appear below. You can edit before saving.</span>
            </div>

            {{-- AI Results --}}
            <div x-show="aiResult" x-collapse class="mt-5">
                <div class="mb-3 p-3 bg-white border border-purple-200 rounded-lg">
                    <div class="text-xs font-semibold text-[#6a0f70] mb-1">Diagnosis Summary</div>
                    <p class="text-sm text-gray-700" x-text="aiResult?.diagnosis_summary"></p>
                </div>

                <template x-for="(group, gi) in aiResult?.plan_groups" :key="gi">
                    <div class="mb-4 bg-white border border-gray-200 rounded-lg overflow-hidden">
                        <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                            <div>
                                <span class="text-xs font-bold text-gray-700" x-text="group.problem"></span>
                                <span x-show="group.tooth_number" class="ml-2 text-xs text-[#6a0f70] font-semibold"
                                      x-text="'Tooth ' + group.tooth_number"></span>
                            </div>
                        </div>
                        <div class="divide-y divide-gray-50">
                            <template x-for="(opt, oi) in group.options" :key="oi">
                                <div class="px-4 py-3 flex items-center gap-4">
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border capitalize"
                                          :class="'tp-rank-' + opt.option_rank"
                                          x-text="opt.option_rank"></span>
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-semibold text-gray-800" x-text="opt.treatment_name"></div>
                                        <div class="text-xs text-gray-400" x-text="opt.brief_reason"></div>
                                    </div>
                                    <div class="text-sm font-bold text-gray-700">
                                        ₹ <span x-text="fmt(opt.unit_price || 0)"></span>
                                    </div>
                                    <button @click="addAiOptionToPlan(group, opt)"
                                            class="px-3 py-1.5 text-xs bg-[#6a0f70] text-white rounded-lg hover:bg-[#380740] font-semibold transition-colors">
                                        Add to Plan
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>

                <button @click="addAllBestToPlan()"
                        class="mt-2 px-4 py-2 text-sm border border-[#6a0f70] text-[#6a0f70] rounded-lg hover:bg-[#f5eef9] font-semibold transition-colors">
                    Add All "Best" Options to New Plan
                </button>
            </div>
        </div>
    </div>

    {{-- ── Plan Form (Add / Edit) ── --}}
    <div x-show="planFormOpen" x-collapse
         class="mb-6 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">

        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div>
                <h2 class="text-sm font-bold text-gray-900" x-text="editingPlan ? 'Edit Treatment Plan' : 'New Treatment Plan'"></h2>
                <p class="text-xs text-gray-400 mt-0.5">{{ $patient->name }}</p>
            </div>
            <button @click="closePlanForm()" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-200 transition-colors">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="px-6 py-5">

            {{-- Plan header fields --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div>
                    <label class="text-xs font-semibold text-gray-600 block mb-1">Plan Name</label>
                    <input type="text" x-model="planForm.plan_name" placeholder="e.g. Treatment Plan A"
                           class="tp-input">
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 block mb-1">Type</label>
                    <select x-model="planForm.plan_type" class="tp-input">
                        <option value="best">Best</option>
                        <option value="acceptable">Acceptable</option>
                    </select>
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 block mb-1">Overall Disc %</label>
                    <input type="number" x-model="planForm.overall_disc_pct" min="0" max="100" step="0.5"
                           placeholder="0" @input="applyOverallDiscount()" class="tp-input">
                </div>
                <div>
                    <label class="text-xs font-semibold text-gray-600 block mb-1">Status</label>
                    <select x-model="planForm.status" class="tp-input">
                        <option value="pending">Pending</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>

            {{-- Items table --}}
            <div class="tp-legend">Treatment Items</div>

            {{-- Table header --}}
            <div class="hidden lg:grid gap-2 mb-2 text-[10px] font-bold text-gray-400 uppercase tracking-wide px-1"
                 style="grid-template-columns:70px 1fr 90px 60px 60px 80px 80px 80px 90px 28px;">
                <span>Tooth</span>
                <span>Treatment</span>
                <span>Price (₹)</span>
                <span class="text-center">Units</span>
                <span class="text-center">Disc%</span>
                <span class="text-right">Net Amt</span>
                <span class="text-center">GST%</span>
                <span class="text-right">Total</span>
                <span>Rank</span>
                <span></span>
            </div>

            <div class="space-y-2 mb-3">
                <template x-for="(item, idx) in planForm.items" :key="idx">
                    <div class="border border-gray-200 rounded-lg p-3 hover:border-[#6a0f70]/30 transition-colors">
                        <div class="grid gap-2 items-center"
                             style="grid-template-columns:70px 1fr 90px 60px 60px 80px 80px 80px 90px 28px;">

                            {{-- Tooth --}}
                            <div class="relative">
                                <input type="text" x-model="item.tooth_number"
                                       @click="openToothChart(idx)"
                                       placeholder="#" readonly
                                       class="tp-input text-center cursor-pointer text-[#6a0f70] font-bold">
                            </div>

                            {{-- Treatment --}}
                            <div>
                                <select x-model="item.treatment_name" @change="onTreatmentSelect(idx)"
                                        class="tp-input">
                                    <option value="">— Select Treatment —</option>
                                    @foreach($treatmentsList as $t)
                                    <option value="{{ $t->name }}" data-price="{{ $t->default_price ?? 0 }}">{{ $t->name }} (₹{{ number_format($t->default_price ?? 0, 0) }})</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Price --}}
                            <input type="number" x-model="item.unit_price" min="0" step="1"
                                   @input="calcRow(idx)" class="tp-input text-right">

                            {{-- Units --}}
                            <input type="number" x-model="item.units" min="1"
                                   @input="calcRow(idx)" class="tp-input text-center">

                            {{-- Disc % --}}
                            <input type="number" x-model="item.disc_pct" min="0" max="100" step="0.5"
                                   @input="calcRow(idx)" class="tp-input text-center">

                            {{-- Net --}}
                            <div class="text-right text-sm font-semibold text-gray-700 px-1"
                                 x-text="'₹ ' + fmt(item.net_amount)"></div>

                            {{-- GST % --}}
                            <input type="number" x-model="item.gst_pct" min="0" max="28" step="0.5"
                                   @input="calcRow(idx)" class="tp-input text-center">

                            {{-- Total --}}
                            <div class="text-right text-sm font-bold text-gray-800 px-1"
                                 x-text="'₹ ' + fmt(item.total)"></div>

                            {{-- Rank --}}
                            <select x-model="item.option_rank" class="tp-input text-xs">
                                <option value="best">Best</option>
                                <option value="acceptable">Acceptable</option>
                                <option value="alternative">Alternative</option>
                            </select>

                            {{-- Delete --}}
                            <button type="button" @click="removeItem(idx)"
                                    class="w-7 h-7 flex items-center justify-center text-gray-300 hover:text-red-400 hover:bg-red-50 rounded transition-colors">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                            </button>
                        </div>

                        {{-- Notes row --}}
                        <div class="mt-2 pl-[70px]">
                            <input type="text" x-model="item.notes" placeholder="Item notes (optional)…"
                                   class="tp-input text-xs text-gray-500">
                        </div>
                    </div>
                </template>
            </div>

            {{-- Add row button --}}
            <button type="button" @click="addItem()"
                    class="w-full flex items-center justify-center gap-2 py-2.5 border border-dashed border-gray-300 rounded-lg text-xs font-semibold text-gray-500 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors mb-5">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Treatment Row
            </button>

            {{-- Totals row --}}
            <div class="flex items-center justify-end gap-8 px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg">
                <div class="text-xs text-gray-500">
                    Gross: <span class="font-bold text-gray-700 ml-1">₹ <span x-text="fmt(grandGross)"></span></span>
                </div>
                <div class="text-xs text-gray-500">
                    Disc: <span class="font-bold text-red-500 ml-1">₹ <span x-text="fmt(grandDisc)"></span></span>
                </div>
                <div class="text-xs text-gray-500">
                    GST: <span class="font-bold text-gray-700 ml-1">₹ <span x-text="fmt(grandGst)"></span></span>
                </div>
                <div class="text-sm font-bold text-[#6a0f70]">
                    Total: ₹ <span x-text="fmt(grandTotal)"></span>
                </div>
            </div>
        </div>

        {{-- Form Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-gray-50">
            <div x-show="planFormError" class="text-sm text-red-600" x-text="planFormError"></div>
            <div class="flex items-center gap-3 ml-auto">
                <button @click="closePlanForm()" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 font-medium">Cancel</button>
                <button @click="savePlan()" :disabled="planSaving"
                        class="inline-flex items-center gap-2 px-5 py-2 bg-[#6a0f70] text-white text-sm font-semibold rounded-lg hover:bg-[#380740] disabled:opacity-60 transition-colors">
                    <svg x-show="planSaving" class="animate-spin" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/></svg>
                    <span x-text="planSaving ? 'Saving…' : (editingPlan ? 'Update Plan' : 'Save Plan')"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Tooth Chart Modal ── --}}
    <template x-if="toothChartOpen">
<div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
     @click.self="toothChartOpen = false">
             <div class="bg-white rounded-xl shadow-2xl p-6 w-auto">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-gray-900">Select Tooth (FDI)</h3>
                <button @click="toothChartOpen = false" class="text-gray-400 hover:text-gray-600">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            @php
                $toothRows = [
                    ['label'=>'UR', 'teeth'=>[18,17,16,15,14,13,12,11], 'side'=>'right'],
                    ['label'=>'UL', 'teeth'=>[21,22,23,24,25,26,27,28], 'side'=>'left'],
                    ['label'=>'LR', 'teeth'=>[48,47,46,45,44,43,42,41], 'side'=>'right'],
                    ['label'=>'LL', 'teeth'=>[31,32,33,34,35,36,37,38], 'side'=>'left'],
                ];
                $primaryRows = [
                    ['label'=>'UR', 'teeth'=>[55,54,53,52,51]],
                    ['label'=>'UL', 'teeth'=>[61,62,63,64,65]],
                    ['label'=>'LR', 'teeth'=>[85,84,83,82,81]],
                    ['label'=>'LL', 'teeth'=>[71,72,73,74,75]],
                ];
            @endphp
            {{-- Permanent teeth --}}
            <div class="mb-3">
                <div class="text-[10px] text-gray-400 text-center mb-2 font-semibold">PERMANENT TEETH</div>
                <div class="grid grid-cols-2 gap-1 mb-1">
                    <div class="flex items-center gap-1">
                        <span class="text-[10px] font-bold text-gray-400 w-6">UR</span>
                        @foreach([18,17,16,15,14,13,12,11] as $t)
                        <button @click="selectTooth('{{ $t }}')"
                                :class="currentToothVal == '{{ $t }}' ? 'bg-[#6a0f70] text-white' : 'bg-gray-100 hover:bg-purple-100 text-gray-700'"
                                class="w-8 h-8 text-xs font-bold rounded transition-colors">{{ $t }}</button>
                        @endforeach
                        <span class="text-[10px] font-bold text-gray-400 w-6 text-right">UL</span>
                    </div>
                    <div class="flex items-center gap-1">
                        @foreach([21,22,23,24,25,26,27,28] as $t)
                        <button @click="selectTooth('{{ $t }}')"
                                :class="currentToothVal == '{{ $t }}' ? 'bg-[#6a0f70] text-white' : 'bg-gray-100 hover:bg-purple-100 text-gray-700'"
                                class="w-8 h-8 text-xs font-bold rounded transition-colors">{{ $t }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-1">
                    <div class="flex items-center gap-1">
                        <span class="text-[10px] font-bold text-gray-400 w-6">LR</span>
                        @foreach([48,47,46,45,44,43,42,41] as $t)
                        <button @click="selectTooth('{{ $t }}')"
                                :class="currentToothVal == '{{ $t }}' ? 'bg-[#6a0f70] text-white' : 'bg-gray-100 hover:bg-purple-100 text-gray-700'"
                                class="w-8 h-8 text-xs font-bold rounded transition-colors">{{ $t }}</button>
                        @endforeach
                        <span class="text-[10px] font-bold text-gray-400 w-6 text-right">LL</span>
                    </div>
                    <div class="flex items-center gap-1">
                        @foreach([31,32,33,34,35,36,37,38] as $t)
                        <button @click="selectTooth('{{ $t }}')"
                                :class="currentToothVal == '{{ $t }}' ? 'bg-[#6a0f70] text-white' : 'bg-gray-100 hover:bg-purple-100 text-gray-700'"
                                class="w-8 h-8 text-xs font-bold rounded transition-colors">{{ $t }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
            {{-- Primary teeth --}}
            <div class="mb-3">
                <div class="text-[10px] text-gray-400 text-center mb-2 font-semibold">PRIMARY TEETH</div>
                <div class="grid grid-cols-2 gap-1 mb-1">
                    <div class="flex items-center gap-1 justify-end">
                        @foreach([55,54,53,52,51] as $t)
                        <button @click="selectTooth('{{ $t }}')"
                                :class="currentToothVal == '{{ $t }}' ? 'bg-[#6a0f70] text-white' : 'bg-amber-50 hover:bg-amber-100 text-amber-700'"
                                class="w-8 h-8 text-xs font-bold rounded transition-colors">{{ $t }}</button>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-1">
                        @foreach([61,62,63,64,65] as $t)
                        <button @click="selectTooth('{{ $t }}')"
                                :class="currentToothVal == '{{ $t }}' ? 'bg-[#6a0f70] text-white' : 'bg-amber-50 hover:bg-amber-100 text-amber-700'"
                                class="w-8 h-8 text-xs font-bold rounded transition-colors">{{ $t }}</button>
                        @endforeach
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-1">
                    <div class="flex items-center gap-1 justify-end">
                        @foreach([85,84,83,82,81] as $t)
                        <button @click="selectTooth('{{ $t }}')"
                                :class="currentToothVal == '{{ $t }}' ? 'bg-[#6a0f70] text-white' : 'bg-amber-50 hover:bg-amber-100 text-amber-700'"
                                class="w-8 h-8 text-xs font-bold rounded transition-colors">{{ $t }}</button>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-1">
                        @foreach([71,72,73,74,75] as $t)
                        <button @click="selectTooth('{{ $t }}')"
                                :class="currentToothVal == '{{ $t }}' ? 'bg-[#6a0f70] text-white' : 'bg-amber-50 hover:bg-amber-100 text-amber-700'"
                                class="w-8 h-8 text-xs font-bold rounded transition-colors">{{ $t }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="flex gap-2 mt-3">
                <button @click="selectTooth('Full Mouth')"
                        :class="currentToothVal === 'Full Mouth' ? 'bg-[#6a0f70] text-white' : 'border-gray-200 text-gray-600'"
                        class="flex-1 py-2 text-xs font-bold border rounded-lg transition-colors">Full Mouth</button>
                <button @click="clearTooth()"
                        class="px-4 py-2 text-xs font-bold border border-gray-200 text-gray-500 rounded-lg hover:bg-gray-50 transition-colors">Clear</button>
           </div>
</template>

    {{-- ── Plans List ── --}}
    <div x-show="plans.length === 0 && !planFormOpen" class="py-16 text-center bg-white border border-gray-200 rounded-xl">
        <div class="w-14 h-14 rounded-full bg-purple-50 flex items-center justify-center mx-auto mb-4">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        </div>
        <p class="text-sm font-semibold text-gray-700 mb-1">No treatment plans yet</p>
        <p class="text-xs text-gray-400 mb-4">Use AI Suggest or create a plan manually.</p>
        <button @click="openNewPlanForm()"
                class="inline-flex items-center gap-2 px-4 py-2 text-sm bg-[#6a0f70] text-white rounded-lg hover:bg-[#380740] font-medium">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            Create First Plan
        </button>
    </div>

    <div class="space-y-4">
        <template x-for="plan in plans" :key="plan.id">
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden hover:border-[#6a0f70]/30 transition-colors">

                {{-- Plan header --}}
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-lg bg-purple-50 flex items-center justify-center">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div>
                            <div class="font-bold text-gray-900 text-sm" x-text="plan.plan_name"></div>
                            <div class="text-xs text-gray-400 mt-0.5">
                                <span x-text="plan.created_at"></span>
                                <span x-show="plan.created_by_name"> · Dr. <span x-text="plan.created_by_name"></span></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="px-2.5 py-0.5 text-[10px] font-bold rounded-full border capitalize"
                              :class="'tp-rank-' + plan.plan_type"
                              x-text="plan.plan_type"></span>
                        <span class="px-2.5 py-0.5 text-[10px] font-semibold rounded-full capitalize"
                              :class="'tp-status-' + plan.status"
                              x-text="plan.status"></span>
                        <div class="text-sm font-bold text-gray-800">₹ <span x-text="fmt(plan.total)"></span></div>
                        <div class="flex items-center gap-1">
                            <button @click="openEditPlanForm(plan)"
                                    class="p-1.5 rounded text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 transition-colors">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            <button @click="deletePlan(plan.id)"
                                    class="p-1.5 rounded text-gray-300 hover:text-red-400 hover:bg-red-50 transition-colors">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Items table --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-[10px] font-bold text-gray-400 uppercase tracking-wide bg-gray-50/70">
                                <th class="px-4 py-2 text-left w-16">Tooth</th>
                                <th class="px-4 py-2 text-left">Treatment</th>
                                <th class="px-4 py-2 text-right">Price</th>
                                <th class="px-4 py-2 text-center">Units</th>
                                <th class="px-4 py-2 text-center">Disc%</th>
                                <th class="px-4 py-2 text-right">Net Amt</th>
                                <th class="px-4 py-2 text-center">GST%</th>
                                <th class="px-4 py-2 text-right">Total</th>
                                <th class="px-4 py-2 text-center">Rank</th>
                                <th class="px-4 py-2 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <template x-for="item in plan.items" :key="item.id">
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="px-4 py-2.5">
                                        <span x-show="item.tooth_number"
                                              class="inline-flex items-center gap-1 text-xs font-bold text-[#6a0f70]">
                                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2c-3 0-6 2-6 6 0 5 3 8 3 12h6c0-4 3-7 3-12 0-4-3-6-6-6z"/></svg>
                                            <span x-text="item.tooth_number"></span>
                                        </span>
                                        <span x-show="!item.tooth_number" class="text-gray-300">—</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <div class="font-medium text-gray-800" x-text="item.treatment_name"></div>
                                        <div x-show="item.notes" class="text-xs text-gray-400" x-text="item.notes"></div>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-600" x-text="'₹ ' + fmt(item.unit_price)"></td>
                                    <td class="px-4 py-2.5 text-center text-gray-600" x-text="item.units"></td>
                                    <td class="px-4 py-2.5 text-center text-gray-600" x-text="item.disc_pct + '%'"></td>
                                    <td class="px-4 py-2.5 text-right text-gray-700 font-medium" x-text="'₹ ' + fmt(item.net_amount)"></td>
                                    <td class="px-4 py-2.5 text-center text-gray-500" x-text="item.gst_pct + '%'"></td>
                                    <td class="px-4 py-2.5 text-right font-bold text-gray-800" x-text="'₹ ' + fmt(item.total)"></td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full border capitalize"
                                              :class="'tp-rank-' + item.option_rank"
                                              x-text="item.option_rank"></span>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full capitalize"
                                              :class="'tp-status-' + item.status"
                                              x-text="item.status"></span>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 border-t border-gray-200">
                                <td colspan="7" class="px-4 py-2.5 text-right text-xs font-bold text-gray-500">Plan Total</td>
                                <td class="px-4 py-2.5 text-right text-sm font-black text-[#6a0f70]"
                                    x-text="'₹ ' + fmt(plan.total)"></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                {{-- Plan footer --}}
                <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between bg-gray-50/50">
                    <div x-show="plan.overall_disc_pct > 0" class="text-xs text-gray-400">
                        Overall discount: <span class="font-semibold text-red-500" x-text="plan.overall_disc_pct + '%'"></span>
                    </div>
                    <div class="ml-auto">
                        <button @click="activeTab = 'visits'"
                                class="text-xs text-[#6a0f70] hover:underline font-semibold">
                            View Treatment Visits →
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

</div>

@push('scripts')
<script>
const TP_TREATMENTS = @json($treatmentsJson);

function treatmentPlan() {
    return {
        plans: @json($plansJson),

        // ── AI ──
        aiPanelOpen: false,
        aiLoading: false,
        aiError: '',
        aiResult: null,
        aiForm: { chief_complaint:'', examination_notes:'', radiographic_notes:'', diagnosis:'' },

        // ── Plan form ──
        planFormOpen: false,
        planSaving: false,
        planFormError: '',
        editingPlan: null,
        planForm: {},

        // ── Tooth chart ──
        toothChartOpen: false,
        toothChartItemIdx: null,
        currentToothVal: '',

        init() {
    this.planForm = this._blankPlan();
    this.prefillAiFromConsultation();
},

        // ─── Computed totals ──────────────────────────────────────────────────
        get grandGross() {
            return this.planForm.items.reduce((s, i) => s + ((parseFloat(i.unit_price)||0) * (parseInt(i.units)||1)), 0);
        },
        get grandDisc() {
            return this.planForm.items.reduce((s, i) => s + (parseFloat(i.disc_amount)||0), 0);
        },
        get grandGst() {
            return this.planForm.items.reduce((s, i) => s + (parseFloat(i.gst_amount)||0), 0);
        },
        get grandTotal() {
            return this.planForm.items.reduce((s, i) => s + (parseFloat(i.total)||0), 0);
        },

        // ─── Blank objects ────────────────────────────────────────────────────
        _blankPlan() {
            const alpha = String.fromCharCode(65 + this.plans.length);
            return {
                plan_name: 'Treatment Plan ' + alpha,
                plan_type: 'best',
                status: 'pending',
                overall_disc_pct: 0,
                items: [],
            };
        },
        _blankItem() {
            return {
                tooth_number: '', treatment_name: '',
                unit_price: 0, units: 1, disc_pct: 0,
                disc_amount: 0, net_amount: 0,
                gst_pct: 0, gst_amount: 0, total: 0,
                option_rank: 'best', status: 'pending', notes: '',
            };
        },

        // ─── Row calculations ─────────────────────────────────────────────────
        calcRow(idx) {
            const i    = this.planForm.items[idx];
            const gross = (parseFloat(i.unit_price)||0) * (parseInt(i.units)||1);
            const disc  = gross * ((parseFloat(i.disc_pct)||0) / 100);
            const net   = gross - disc;
            const gst   = net * ((parseFloat(i.gst_pct)||0) / 100);
            i.disc_amount = Math.round(disc * 100) / 100;
            i.net_amount  = Math.round(net  * 100) / 100;
            i.gst_amount  = Math.round(gst  * 100) / 100;
            i.total       = Math.round((net + gst) * 100) / 100;
        },

        onTreatmentSelect(idx) {
            const item = this.planForm.items[idx];
            const found = TP_TREATMENTS.find(t => t.name === item.treatment_name);
            if (found) {
                item.unit_price = found.price;
                this.calcRow(idx);
            }
        },

        applyOverallDiscount() {
            const pct = parseFloat(this.planForm.overall_disc_pct) || 0;
            this.planForm.items.forEach((item, idx) => {
                item.disc_pct = pct;
                this.calcRow(idx);
            });
        },

        addItem() { this.planForm.items.push(this._blankItem()); },
        removeItem(idx) { this.planForm.items.splice(idx, 1); },

        // ─── Tooth chart ──────────────────────────────────────────────────────
        openToothChart(idx) {
            this.toothChartItemIdx = idx;
            this.currentToothVal = this.planForm.items[idx]?.tooth_number || '';
            this.toothChartOpen = true;
        },
        selectTooth(t) {
            if (this.toothChartItemIdx !== null) {
                this.planForm.items[this.toothChartItemIdx].tooth_number = t;
                this.currentToothVal = t;
            }
            this.toothChartOpen = false;
        },
        clearTooth() {
            if (this.toothChartItemIdx !== null) {
                this.planForm.items[this.toothChartItemIdx].tooth_number = '';
            }
            this.currentToothVal = '';
        },

        // ─── Plan form open/close ─────────────────────────────────────────────
        openNewPlanForm() {
            this.editingPlan = null;
            this.planForm = this._blankPlan();
            this.planFormError = '';
            this.planFormOpen = true;
            this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
        },
        openEditPlanForm(plan) {
            this.editingPlan = plan;
            this.planForm = {
                plan_name: plan.plan_name,
                plan_type: plan.plan_type,
                status: plan.status,
                overall_disc_pct: plan.overall_disc_pct,
                items: plan.items.map(i => ({ ...i })),
            };
            this.planFormError = '';
            this.planFormOpen = true;
        },
        closePlanForm() {
            this.planFormOpen = false;
            this.editingPlan = null;
        },

        // ─── Save plan ────────────────────────────────────────────────────────
        async savePlan() {
            if (!this.planForm.items.length) {
                this.planFormError = 'Add at least one treatment row.';
                return;
            }
            this.planSaving = true;
            this.planFormError = '';
            try {
                const isEdit = !!this.editingPlan;
                const url    = isEdit
                    ? `/treatment-plans/${this.editingPlan.id}`
                    : `/patients/{{ $patient->id }}/treatment-plans`;
                const resp = await fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.planForm),
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) throw new Error(data.message || 'Save failed.');
                if (isEdit) {
                    const idx = this.plans.findIndex(p => p.id === this.editingPlan.id);
                    if (idx > -1) this.plans[idx] = data.plan;
                } else {
                    this.plans.unshift(data.plan);
                }
                this.closePlanForm();
            } catch(e) {
                this.planFormError = e.message;
            } finally {
                this.planSaving = false;
            }
        },

        async deletePlan(id) {
            if (!confirm('Delete this treatment plan? This cannot be undone.')) return;
            try {
                const resp = await fetch(`/treatment-plans/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) throw new Error(data.message || 'Delete failed.');
                this.plans = this.plans.filter(p => p.id !== id);
            } catch(e) { alert(e.message); }
        },

        // ─── AI ───────────────────────────────────────────────────────────────
        openAiPanel() {
    this.prefillAiFromConsultation();
    this.aiPanelOpen = true;
    this.$nextTick(() => {
        if (this.aiForm.chief_complaint || this.aiForm.diagnosis) {
            this.runAiSuggest();
        }
    });
},

prefillAiFromConsultation() {
    @if($consultations->isNotEmpty())
    @php $latest = $consultations->first(); @endphp
    this.aiForm.chief_complaint    = @json($latest->chief_complaint ?? '');
    this.aiForm.examination_notes  = @json(($latest->clinical_data['notes'] ?? $latest->clinical_data['oral_hygiene'] ?? '') ?: '');
    this.aiForm.radiographic_notes = @json(($latest->radio_data['notes'] ?? '') ?: '');
    this.aiForm.diagnosis          = @json(trim(($latest->primary_diagnosis ?? '') . ' ' . ($latest->secondary_diagnosis ?? '')) ?: '');
    @endif
},

        async runAiSuggest() {
            this.aiLoading = true;
            this.aiError   = '';
            this.aiResult  = null;
            try {
                const resp = await fetch(`/patients/{{ $patient->id }}/treatment-plans/ai-suggest`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.aiForm),
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) throw new Error(data.message || 'AI failed.');
                this.aiResult = data.suggestion;
            } catch(e) {
                this.aiError = e.message;
            } finally {
                this.aiLoading = false;
            }
        },

        addAiOptionToPlan(group, opt) {
            if (!this.planFormOpen) {
                this.editingPlan = null;
                this.planForm = this._blankPlan();
                this.planFormOpen = true;
            }
            const item = this._blankItem();
            item.tooth_number   = group.tooth_number || '';
            item.treatment_name = opt.treatment_name;
            item.unit_price     = opt.unit_price || 0;
            item.option_rank    = opt.option_rank || 'best';
            item.units = 1;
            this.planForm.items.push(item);
            this.calcRow(this.planForm.items.length - 1);
        },

        addAllBestToPlan() {
            if (!this.aiResult) return;
            this.editingPlan = null;
            this.planForm = this._blankPlan();
            this.planFormOpen = true;
            this.aiResult.plan_groups.forEach(group => {
                const best = group.options.find(o => o.option_rank === 'best');
                if (best) this.addAiOptionToPlan(group, best);
            });
        },

        fmt(n) { return Number(n || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
    };
}
</script>
@endpush
