@php
// Stages loaded from Treatment module (defined per-treatment in Treatments → Stages tab)
$treatmentStages = \App\Models\TreatmentVisit::allStagesFromDb();

// Safe fallback so the closure below never throws "Undefined variable $prescriptions"
$_rxCollection = $prescriptions ?? collect();

$visitsJson = $patient->treatmentVisits->map(function($v) use ($_rxCollection) {
    return [
        'id'               => $v->id,
        'appointment_id'   => $v->appointment_id,
        'visit_date'       => $v->visit_date?->format('Y-m-d'),
        'visit_type'       => $v->visit_type,
        'status'           => $v->status,
        'doctor_id'        => (string)($v->doctor_id ?? ''),
        'doctor_name'      => $v->doctor?->name,
        'treatment_name'   => $v->treatment_name,
        'current_stage'    => $v->current_stage,
        'completed_stages' => $v->completed_stages ?? [],
        'tooth_number'     => $v->tooth_number,
        'notes'            => $v->notes,
        'chief_complaint'  => $v->chief_complaint,
        'cost'             => (float)$v->cost,
        'amount_paid'      => (float)$v->amount_paid,
        'balance_due'      => max(0,(float)$v->cost-(float)$v->amount_paid),
        'payment_mode'     => $v->payment_mode,
        'payment_reference'=> $v->payment_reference,
        'next_visit_date'  => $v->next_visit_date?->format('Y-m-d'),
        'next_visit_type'  => $v->next_visit_type,
        'rct_num_canals'        => $v->rct_num_canals,
        'rct_canal_lengths'     => $v->rct_canal_lengths ?? [],
        'rct_file_type'         => $v->rct_file_type,
        'rct_irrigant'          => $v->rct_irrigant,
        'rct_obturation_method' => $v->rct_obturation_method,
        'impl_brand'            => $v->impl_brand,
        'impl_size'             => $v->impl_size,
        'impl_torque'           => $v->impl_torque,
        'impl_graft_used'       => $v->impl_graft_used,
        'impl_graft_brand'      => $v->impl_graft_brand,
        'impl_membrane'         => $v->impl_membrane,
        'impl_healing_collar'   => $v->impl_healing_collar,
        'fill_material'         => $v->fill_material,
        'fill_shade'            => $v->fill_shade,
        'scale_quadrants'       => $v->scale_quadrants,
        'scale_method'          => $v->scale_method,
        'ext_type'              => $v->ext_type,
        'ext_socket'            => $v->ext_socket,
        'ext_suture'            => (bool)$v->ext_suture,
        'crown_type'            => $v->crown_type,
        'crown_shade'           => $v->crown_shade,
        'crown_impression'      => (bool)$v->crown_impression,
        'crown_temp_placed'     => $v->crown_temp_placed,
        'treatment_plan_id'         => $v->treatment_plan_id,
        'prescription_drugs'        => $v->prescription_drugs ?? [],
        'prescription_instructions' => $v->prescription_instructions ?? [],
        'prescription_custom_notes' => $v->prescription_custom_notes,
        // Vitals
        'bp_systolic'      => $v->bp_systolic,
        'bp_diastolic'     => $v->bp_diastolic,
        'pulse_rate'       => $v->pulse_rate,
        'spo2'             => $v->spo2,
        'temperature'      => $v->temperature,
        'blood_sugar'      => $v->blood_sugar,
        'blood_sugar_type' => $v->blood_sugar_type,
        'weight'           => $v->weight,
        'vitals_notes'     => $v->vitals_notes,
        // F2: visit items for billing
        'visit_items' => $v->visitItems->map(fn($i) => [
            'id'                     => $i->id,
            'treatment_plan_item_id' => $i->treatment_plan_item_id,
            'treatment_name'         => $i->treatment_name,
            'material_option'        => $i->material_option,
            'tooth_number'           => $i->tooth_number,
            'suggested_price'        => (float)$i->suggested_price,
            'billing_status'         => $i->billing_status,
            'notes'                  => $i->notes,
            'is_repeat'              => (bool)$i->is_repeat,
            'repeat_reason'          => $i->repeat_reason,
            'repeat_of_visit_item_id'=> $i->repeat_of_visit_item_id,
        ])->values()->all(),
        // Linked formal prescription (new Prescription module)
        'linked_rx' => (function() use ($v, $_rxCollection) {
            $rx = $_rxCollection->where('visit_id', $v->id)->whereNotIn('status', ['cancelled'])->first();
            if (!$rx) return null;
            return ['number' => $rx->prescription_number, 'id' => $rx->id,
                    'drugs'  => $rx->items->count(), 'status' => $rx->status];
        })(),
        '_isNew' => false,
    ];
});

$stagesJson       = $treatmentStages;

// All active treatments — select lab columns only after migration has run
$_labColsExist  = \Illuminate\Support\Facades\Schema::hasColumn('treatments', 'needs_lab');
$_selectCols    = $_labColsExist ? ['name','needs_lab','lab_work_category'] : ['name'];
$_allTreatments = \App\Models\Treatment::where('is_active', true)
                      ->orderBy('sort_order')->orderBy('name')
                      ->get($_selectCols);
$treatmentsList   = $_allTreatments->pluck('name')->all();

// Map of treatment name → lab info (empty until migration runs)
$labTreatmentsMap = $_labColsExist
    ? $_allTreatments->where('needs_lab', true)
          ->keyBy('name')
          ->map(fn($t) => ['work_category' => $t->lab_work_category ?? ''])
          ->toArray()
    : [];

$doctorsList        = $doctors ?? collect();
$labVendorsList     = \App\Models\LabVendor::where('is_active', true)->orderBy('name')->get(['id','name']);

// F2: pass plans as JSON so visit form can load plan items via AJAX
// Only ACCEPTED plans (patient said yes -> accepted_at is set) should surface
// in the visit form. Pending/un-accepted options stay hidden here.
$treatmentPlansJson = ($patient->treatmentPlans ?? collect())
    ->filter(fn($p) => !is_null($p->accepted_at))
    ->map(fn($p) => [
        'id'        => $p->id,
        'plan_name' => $p->plan_name,
        'status'    => $p->status,
    ])->values();

// Appointments: upcoming + last 30 days, for linking to a visit
$appointmentsJson = ($patient->appointments ?? collect())
    ->filter(fn($a) => $a->appointment_date >= now()->subDays(30))
    ->sortByDesc('appointment_date')
    ->values()
    ->map(fn($a) => [
        'id'          => $a->id,
        'date'        => $a->appointment_date->format('Y-m-d'),
        'label'       => $a->appointment_date->format('d M Y') .
                         ($a->appointment_time ? ' · ' . \Carbon\Carbon::parse($a->appointment_time)->format('h:i A') : '') .
                         ($a->treatmentCategory ? ' · ' . $a->treatmentCategory->name : ''),
        'doctor_id'   => (string)($a->doctor_id ?? ''),
        'status'      => $a->status,
    ]);
@endphp

<style>
    .tv-rx-input { border:1px solid #e5e7eb;border-radius:5px;padding:5px 6px;font-size:12px;color:#374151;background:white;outline:none;transition:border-color .15s;width:100%;min-width:0; }
    .tv-rx-input:focus { border-color:#6a0f70; }
    .tv-rx-pill { display:inline-flex;align-items:center;gap:3px;padding:3px 8px;border-radius:99px;font-size:11px;font-weight:600;border:1.5px solid #e5e7eb;background:white;cursor:pointer;color:#6b7280;transition:all .12s;white-space:nowrap; }
    .tv-rx-pill:hover { border-color:#6a0f70;color:#6a0f70; }
    .tv-rx-pill.on { background:#6a0f70;border-color:#380740;color:white; }
    .tv-stage-btn { padding:4px 10px;font-size:11px;font-weight:600;border:1.5px solid #e5e7eb;border-radius:99px;cursor:pointer;transition:all .15s;background:white;color:#6b7280; }
    .tv-stage-btn:hover { border-color:#6a0f70;color:#6a0f70; }
    .tv-stage-btn.done { background:#16a34a;border-color:#15803d;color:white; }
    .tv-stage-btn.current { background:#6a0f70;border-color:#380740;color:white; }
    .tv-section-legend { font-size:10px;font-weight:700;color:#6a0f70;text-transform:uppercase;letter-spacing:.07em;margin-bottom:10px;display:flex;align-items:center;gap:6px; }
    .tv-section-legend::after { content:'';flex:1;height:1px;background:#f3f4f6; }
</style>

<div
    x-show="activeTab === 'visits'"
    style="display:none"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-data="treatmentVisits()"
    @open-visit-form.window="openAddForm()"
    class="w-full px-6 py-6"
>

    {{-- Summary bar --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
        @php
            $tvAll          = $patient->treatmentVisits;
            $tvCompleted    = $tvAll->where('status','completed')->count();
            $tvOngoing      = $tvAll->whereIn('status',['scheduled','in_chair'])->count();
            // F2: count visits with pending billing items (not yet invoiced)
            $tvBillingItems = $tvAll->sum(fn($v) => $v->visitItems->where('billing_status','pending')->count());
            $tvBilledItems  = $tvAll->sum(fn($v) => $v->visitItems->where('billing_status','!=','pending')->count());
        @endphp
        @foreach([
            ['Total Visits',     $tvAll->count(),     'bg-[#6a0f70]', 'text-white',     null],
            ['Completed',        $tvCompleted,         'bg-green-600', 'text-white',     null],
            ['In Progress',      $tvOngoing,           'bg-blue-600',  'text-white',     null],
            ['Items Pending Billing', $tvBillingItems, 'bg-white',     $tvBillingItems > 0 ? 'text-amber-600 font-bold' : 'text-gray-400', 'border border-gray-200'],
            ['Items Billed',     $tvBilledItems,       'bg-white',     'text-green-700', 'border border-gray-200'],
        ] as [$label, $val, $bg, $col, $extra])
        <div class="rounded-lg px-4 py-3 {{ $bg }} {{ $extra }}">
            <div class="text-[10px] uppercase tracking-wide opacity-70 mb-0.5 {{ $bg === 'bg-white' ? 'text-gray-400' : 'text-white' }}">{{ $label }}</div>
            <div class="text-lg font-bold {{ $col }}">{{ $val }}</div>
        </div>
        @endforeach
    </div>

    {{-- Phase 5, Slice 3 — Workflow Engine read-only preview.
         Sourced from the Slice 2 shadow WorkflowInstance(s) for this patient's
         treatment plan(s). Purely informational: gated behind the
         `workflow.engine` flag (off by default), renders nothing if the flag
         is off or no shadow instance exists yet, and never affects the
         Add/Edit Visit form below — current_stage stays exactly as
         doctors already use it. --}}
    @php
        $workflowPanels = [];
        if (\App\Support\Features\Feature::enabled('workflow.engine')) {
            $wfPlanIds = $patient->treatmentVisits->pluck('treatment_plan_id')->filter()->unique();
            if ($wfPlanIds->isNotEmpty()) {
                $wfEngine = app(\App\Services\Workflow\WorkflowEngine::class);
                $wfInstances = \App\Models\WorkflowInstance::whereIn('subject_id', $wfPlanIds)
                    ->where('subject_type', \App\Models\TreatmentPlan::class)
                    ->with('template')
                    ->get();
                foreach ($wfInstances as $wfi) {
                    $workflowPanels[] = array_merge($wfEngine->status($wfi), [
                        'template_name' => $wfi->template->name,
                    ]);
                }
            }
        }
    @endphp

    @if(count($workflowPanels) > 0)
    <div class="mb-6 space-y-2">
        @foreach($workflowPanels as $wf)
        <div class="rounded-lg border border-purple-100 bg-purple-50/60 px-4 py-3 flex items-center justify-between gap-4">
            <div>
                <div class="text-[10px] uppercase tracking-wide text-purple-500 font-semibold mb-0.5">
                    Workflow (preview) — {{ $wf['template_name'] }}
                </div>
                <div class="text-sm font-semibold text-gray-800">
                    Stage {{ $wf['position'] }} of {{ $wf['total_steps'] }}: {{ $wf['current_step_label'] }}
                    @if($wf['status'] === 'completed')
                        <span class="ml-1 text-green-600 font-normal">— complete</span>
                    @endif
                </div>
                @if($wf['next_step_label'])
                    <div class="text-xs text-gray-500 mt-0.5">
                        Next: {{ $wf['next_step_label'] }}
                        @if($wf['next_eligible_at'])
                            — {{ $wf['next_due'] ? 'due now' : 'not due until ' . $wf['next_eligible_at']->format('d M') }}
                        @endif
                    </div>
                @endif
            </div>
            <div class="text-[10px] text-gray-400 italic text-right max-w-[160px]">
                Read-only preview, based on the stages logged below. Doesn't change how you log visits.
            </div>
        </div>
        @endforeach
    </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-base font-semibold text-gray-900">Treatment Visit Log</h3>
            <p class="text-xs text-gray-400 mt-0.5">Full clinical record of all visits for this patient</p>
        </div>
        <button @click="openAddForm()"
                x-show="!formOpen || editingVisit"
                dusk="visit-add"
                class="inline-flex items-center gap-2 bg-[#6a0f70] hover:bg-[#570c5d] text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add Visit
        </button>
    </div>

    {{-- Filter + Search --}}
    <div class="flex flex-wrap items-center gap-2 mb-4">
        <template x-for="f in filters" :key="f.key">
            <button @click="activeFilter = f.key"
                    :class="activeFilter === f.key ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'bg-white text-gray-500 border-gray-200 hover:border-[#6a0f70] hover:text-[#6a0f70]'"
                    class="px-3 py-1.5 text-xs font-medium rounded-full border transition-colors"
                    x-text="f.label"></button>
        </template>
        <div class="ml-auto">
            <input x-model="search" type="text" placeholder="Search treatment, tooth, notes…"
                   class="text-xs border border-gray-200 rounded-lg px-3 py-1.5 w-52 focus:outline-none focus:border-[#6a0f70]">
        </div>
    </div>

    {{-- Visit cards --}}
    <div class="space-y-3">

        <template x-if="filteredVisits.length === 0">
            <div class="py-16 text-center text-gray-400 bg-white border border-gray-200 rounded-xl">
                <div class="w-14 h-14 rounded-full bg-green-50 flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none"
                         stroke="#16a34a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-600 mb-1"
                   x-text="search || activeFilter !== 'all' ? 'No visits match this filter' : 'No visits recorded yet'"></p>
                <p class="text-xs text-gray-400">
                    <span x-show="!search && activeFilter === 'all'">Click "Add Visit" to record the first visit.</span>
                    <span x-show="search || activeFilter !== 'all'">Try clearing the filter or search.</span>
                </p>
            </div>
        </template>

        <template x-for="visit in filteredVisits" :key="visit.id">
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden hover:border-gray-300 transition-colors"
                 :class="visit._isNew ? 'ring-2 ring-[#6a0f70] ring-offset-1' : ''">
                <div class="flex items-start gap-4 p-4">

                    {{-- Date --}}
                    <div class="flex-shrink-0 w-14 text-center">
                        <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400" x-text="fmtMonth(visit.visit_date)"></div>
                        <div class="text-2xl font-black text-gray-800 leading-none" x-text="fmtDay(visit.visit_date)"></div>
                        <div class="text-[10px] text-gray-400" x-text="fmtYear(visit.visit_date)"></div>
                    </div>

                    <div class="w-px self-stretch bg-gray-100 flex-shrink-0"></div>

                    {{-- Main --}}
                    <div class="flex-1 min-w-0">

                        {{-- Badges row --}}
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide"
                                  :class="typeBadge(visit.visit_type)" x-text="visit.visit_type"></span>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium"
                                  :class="statusBadge(visit.status)" x-text="visit.status.replace('_',' ')"></span>
                            {{-- F2: show "Billing pending" if visit has items awaiting invoice --}}
                            <template x-if="visit.visit_items && visit.visit_items.some(i => i.billing_status === 'pending')">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-100">Billing pending</span>
                            </template>
                            <template x-if="visit.visit_items && visit.visit_items.length > 0 && visit.visit_items.every(i => i.billing_status !== 'pending')">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-50 text-green-700">Billed</span>
                            </template>
                        </div>

                        {{-- Treatment + tooth + doctor --}}
                        <div class="flex flex-wrap items-baseline gap-x-3 gap-y-0.5 mb-1">
                            <span class="text-sm font-bold text-gray-900" x-text="visit.treatment_name || visit.procedure || '—'"></span>
                            <span x-show="visit.tooth_number" class="text-xs text-gray-500">
                                Tooth <span class="font-semibold text-[#6a0f70]" x-text="visit.tooth_number"></span>
                            </span>
                            <span x-show="visit.doctor_name" class="text-xs text-gray-400 flex items-center gap-1">
                                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                Dr. <span x-text="visit.doctor_name"></span>
                            </span>
                            {{-- Repeat-work badge --}}
                            <span x-show="visit.visit_items && visit.visit_items.some(i => i.is_repeat)"
                                  class="text-[10px] font-semibold text-amber-700 bg-amber-100 border border-amber-200 px-2 py-0.5 rounded-full"
                                  :title="(visit.visit_items.find(i => i.is_repeat) || {}).repeat_reason || 'Repeat work'">
                                ↻ Repeat
                            </span>
                        </div>

                        {{-- Stage progress --}}
                        <div x-show="visit.treatment_name && stages[visit.treatment_name]" class="flex flex-wrap gap-1 mb-1.5">
                            <template x-for="(label, key) in (stages[visit.treatment_name] || {})" :key="key">
                                <span class="px-2 py-0.5 text-[10px] rounded-full border font-medium"
                                      :class="visit.completed_stages && visit.completed_stages.includes(key)
                                              ? 'bg-green-100 text-green-700 border-green-200'
                                              : (visit.current_stage === key
                                                  ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                                  : 'bg-gray-50 text-gray-400 border-gray-100')"
                                      x-text="label"></span>
                            </template>
                        </div>

                        <p x-show="visit.notes" class="text-xs text-gray-500 mt-0.5 line-clamp-1 italic" x-text="visit.notes"></p>

                        {{-- Vitals summary (only if any recorded) --}}
                        <div x-show="visit.bp_systolic||visit.bp_diastolic||visit.pulse_rate||visit.spo2||visit.temperature||visit.blood_sugar||visit.weight"
                             class="flex flex-wrap gap-1.5 mt-1.5">
                            <span x-show="visit.bp_systolic||visit.bp_diastolic" class="text-[10px] text-gray-600 bg-gray-50 border border-gray-100 rounded px-1.5 py-0.5">
                                BP <span class="font-semibold" x-text="(visit.bp_systolic||'–')+'/'+(visit.bp_diastolic||'–')"></span>
                            </span>
                            <span x-show="visit.pulse_rate" class="text-[10px] text-gray-600 bg-gray-50 border border-gray-100 rounded px-1.5 py-0.5">
                                Pulse <span class="font-semibold" x-text="visit.pulse_rate"></span>
                            </span>
                            <span x-show="visit.spo2" class="text-[10px] text-gray-600 bg-gray-50 border border-gray-100 rounded px-1.5 py-0.5">
                                SpO₂ <span class="font-semibold" x-text="visit.spo2 + '%'"></span>
                            </span>
                            <span x-show="visit.temperature" class="text-[10px] text-gray-600 bg-gray-50 border border-gray-100 rounded px-1.5 py-0.5">
                                Temp <span class="font-semibold" x-text="visit.temperature + '°C'"></span>
                            </span>
                            <span x-show="visit.blood_sugar" class="text-[10px] text-gray-600 bg-gray-50 border border-gray-100 rounded px-1.5 py-0.5">
                                Sugar <span class="font-semibold" x-text="visit.blood_sugar"></span><span class="uppercase" x-text="visit.blood_sugar_type ? ' '+visit.blood_sugar_type : ''"></span>
                            </span>
                            <span x-show="visit.weight" class="text-[10px] text-gray-600 bg-gray-50 border border-gray-100 rounded px-1.5 py-0.5">
                                Wt <span class="font-semibold" x-text="visit.weight + ' kg'"></span>
                            </span>
                        </div>

                        {{-- Prescription summary --}}
                        <div x-show="visit.prescription_drugs && visit.prescription_drugs.length > 0"
                             class="mt-1 inline-flex items-center gap-1 text-[10px] text-red-600 bg-red-50 px-2 py-0.5 rounded-full">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 2-5 5"/><path d="m2 19 5-5"/><rect x="5" y="2" width="5" height="20" rx="1" transform="rotate(-45 5 2)"/></svg>
                            Rx: <span x-text="visit.prescription_drugs.length + ' drug(s)'"></span>
                        </div>

                        {{-- Next visit --}}
                        <div x-show="visit.next_visit_date"
                             class="mt-1 inline-flex items-center gap-1 text-[10px] text-orange-600 bg-orange-50 px-2 py-0.5 rounded-full ml-1">
                            <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            Next: <span x-text="fmtFull(visit.next_visit_date)"></span>
                        </div>

                        {{-- Linked formal prescription --}}
                        <template x-if="visit.linked_rx">
                            <div class="mt-1 inline-flex items-center gap-1 text-[10px] text-green-700 bg-green-50 px-2 py-0.5 rounded-full ml-1 font-semibold">
                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 2-5 5"/><path d="m2 19 5-5"/><rect x="5" y="2" width="5" height="20" rx="1" transform="rotate(-45 5 2)"/></svg>
                                <span x-text="visit.linked_rx.number"></span>
                                <span class="font-normal text-green-600" x-text="'· ' + visit.linked_rx.drugs + ' drug(s)'"></span>
                            </div>
                        </template>

                        {{-- Cost / financials (shown when cost > 0) --}}
                        <template x-if="visit.cost > 0">
                            <div class="mt-1.5 flex items-center gap-2 text-[10px]">
                                <span class="font-semibold text-gray-700">
                                    Rs. <span x-text="visit.cost.toLocaleString('en-IN')"></span>
                                </span>
                                <template x-if="visit.amount_paid > 0">
                                    <span class="text-green-600">Paid Rs. <span x-text="visit.amount_paid.toLocaleString('en-IN')"></span></span>
                                </template>
                                <template x-if="visit.balance_due > 0">
                                    <span class="text-red-500 font-semibold">Due Rs. <span x-text="visit.balance_due.toLocaleString('en-IN')"></span></span>
                                </template>
                            </div>
                        </template>
                    </div>

                    {{-- Right: billing items badge + actions --}}
                    <div class="flex-shrink-0 text-right space-y-1 ml-2">
                        {{-- F2: show billing items count if present --}}
                        <template x-if="visit.visit_items && visit.visit_items.length > 0">
                            <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 border border-amber-100 text-amber-700">
                                <svg width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                                <span x-text="visit.visit_items.length + ' item' + (visit.visit_items.length > 1 ? 's' : '')"></span>
                            </div>
                        </template>
                        <div class="flex items-center gap-1 justify-end mt-2">
                            {{-- Write Prescription for this visit --}}
                            <a :href="`{{ route('patients.prescriptions.create', $patient) }}?visit_id=${visit.id}`"
                               class="p-1.5 rounded text-gray-400 hover:text-green-600 hover:bg-green-50 transition-colors"
                               title="Write Prescription for this visit">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                    <line x1="16" y1="13" x2="8" y2="13"/>
                                    <line x1="16" y1="17" x2="8" y2="17"/>
                                    <polyline points="10 9 9 9 8 9"/>
                                </svg>
                            </a>
                            {{-- Print visit --}}
                            <a :href="`/visits/${visit.id}/print`" target="_blank"
                               class="p-1.5 rounded text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 transition-colors"
                               title="Print this visit">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="6 9 6 2 18 2 18 9"/>
                                    <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                                    <rect x="6" y="14" width="12" height="8"/>
                                </svg>
                            </a>
                            <button @click="openEditForm(visit)"
                                    class="p-1.5 rounded text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 transition-colors">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            <button @click="deleteVisit(visit)"
                                    class="p-1.5 rounded text-gray-300 hover:text-red-400 hover:bg-red-50 transition-colors">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                    <path d="M10 11v6"/><path d="M14 11v6"/>
                                    <path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                </div>
            </div>
        </template>

    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         ADD / EDIT — CENTERED MODAL
    ══════════════════════════════════════════════════════════════════ --}}

    {{-- Backdrop --}}
    <div x-show="formOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="closeForm()"
         class="fixed inset-0 bg-black/30 z-[140]"
         style="display:none"></div>

    {{-- Modal (centered) --}}
    <div id="tv-inline-form" x-show="formOpen"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95vw] max-w-5xl h-[90vh] bg-white shadow-2xl z-[150] flex flex-col rounded-2xl overflow-hidden"
         style="display:none">

        {{-- Header --}}
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div>
                <h2 class="text-base font-bold text-gray-900" x-text="editingVisit ? 'Edit Visit' : 'New Visit'"></h2>
                <p class="text-xs text-gray-400 mt-0.5">{{ $patient->name }}</p>
            </div>
            <button @click="closeForm()" class="p-2 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-200 transition-colors">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto overflow-x-hidden px-6 py-5">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-6">

                {{-- Error --}}
                <div x-show="errorMsg" class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span x-text="errorMsg"></span>
                </div>

                {{-- Repeat-work warning: same treatment + tooth already done before --}}
                <div x-show="repeatWarnings.length > 0" x-cloak class="lg:col-span-2 px-4 py-3 bg-amber-50 border border-amber-300 rounded-lg">
                    <div class="flex items-start gap-2">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="mt-0.5 flex-shrink-0"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-amber-800">This may be repeat work</p>
                            <ul class="mt-1 space-y-0.5">
                                <template x-for="w in repeatWarnings" :key="w.treatment_name + '|' + w.tooth">
                                    <li class="text-xs text-amber-700">
                                        <span class="font-semibold" x-text="w.treatment_name"></span>
                                        on <span class="font-semibold" x-text="'Tooth ' + w.tooth"></span>
                                        was already done on <span class="font-semibold" x-text="_fmtDate(w.date)"></span>.
                                    </li>
                                </template>
                            </ul>
                            <label class="block text-xs font-semibold text-amber-800 mt-2 mb-1">
                                Reason for repeat work <span class="text-red-500">*</span>
                            </label>
                            <textarea x-model="repeatReason" rows="2"
                                      placeholder="e.g. Filling dislodged / recurrent decay / patient discomfort"
                                      class="w-full border border-amber-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-amber-400 bg-white"></textarea>
                            <p class="text-[11px] text-amber-600 mt-1">Required — this visit is tracked as repeat work for reporting.</p>
                        </div>
                    </div>
                </div>

                {{-- ── SECTION 1: Visit Header ── --}}
                <div>
                    <div class="tv-section-legend">Visit Details</div>
                    <div class="grid grid-cols-2 gap-3">

                        {{-- LAYER 0: Link Appointment --}}
                        <div class="col-span-2" x-show="TV_APPOINTMENTS.length > 0">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">
                                Link Appointment
                                <span class="font-normal text-gray-400 ml-1">— optional</span>
                            </label>
                            <select x-model="form.appointment_id" @change="onAppointmentChange()"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70] bg-white">
                                <option value="">— No appointment linked —</option>
                                <template x-for="appt in TV_APPOINTMENTS" :key="appt.id">
                                    <option :value="appt.id"
                                            :class="appt.status === 'cancelled' ? 'text-gray-400' : ''"
                                            x-text="appt.label + (appt.status !== 'scheduled' ? ' [' + appt.status + ']' : '')"></option>
                                </template>
                            </select>
                        </div>

                        {{-- LAYER 1: Treatment Plan --}}
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Treatment Plan</label>
                            <select x-model="form.treatment_plan_id" @change="onPlanChange()"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">— Select Treatment Plan —</option>
                                <template x-for="plan in treatmentPlans" :key="plan.id">
                                    <option :value="plan.id"
                                            x-text="plan.plan_name + ' (' + (plan.status ? plan.status.charAt(0).toUpperCase() + plan.status.slice(1) : '') + ')'"></option>
                                </template>
                            </select>
                        </div>

                        {{-- LAYER 2: Treatment from Plan --}}
                        <div class="col-span-2" x-show="form.treatment_plan_id">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">
                                What was done today?
                                <span class="font-normal text-gray-400 ml-1">— select from plan</span>
                            </label>
                            <template x-if="planItemsLoading">
                                <p class="text-xs text-gray-400 py-2 italic">Loading plan items…</p>
                            </template>
                            <template x-if="!planItemsLoading && planItems.length === 0">
                                <p class="text-xs text-gray-400 py-2 italic">No items found in this plan.</p>
                            </template>
                            <template x-if="!planItemsLoading && planItems.length > 0">
                                <div>
                                    <div class="flex flex-wrap gap-2 mb-2">
                                        <template x-for="pi in planItems" :key="pi.id">
                                            <button type="button"
                                                    @click="selectPlanTreatment(pi)"
                                                    :class="form.plan_item_id == pi.id
                                                        ? 'bg-[#6a0f70] border-[#380740] text-white shadow-sm'
                                                        : 'bg-white border-gray-200 text-gray-700 hover:border-[#6a0f70] hover:bg-purple-50'"
                                                    class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium transition-all">
                                                <span x-text="pi.treatment_name"></span>
                                                <span x-show="pi.tooth_number" class="opacity-60" x-text="'· T' + pi.tooth_number"></span>
                                                <svg x-show="form.plan_item_id == pi.id" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                            </button>
                                        </template>
                                    </div>
                                    {{-- Fallback: treatment not in plan --}}
                                    <button type="button"
                                            @click="selectPlanTreatment(null)"
                                            :class="form.plan_item_id === '__other__'
                                                ? 'bg-gray-100 border-gray-400 text-gray-700'
                                                : 'bg-white border-dashed border-gray-300 text-gray-400 hover:border-gray-400 hover:text-gray-600'"
                                            class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-medium transition-all">
                                        Other / Not in plan
                                    </button>
                                </div>
                            </template>
                            {{-- Typeahead when "Other" picked --}}
                            <div x-show="form.plan_item_id === '__other__'" class="mt-2">
                                <div class="relative" @click.outside="txSuggestOpen = false">
                                    <div class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2 focus-within:border-[#6a0f70] bg-white transition-colors">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                        <input type="text"
                                               x-model="txSearch"
                                               @input="filterTx()"
                                               @focus="filterTx(); txSuggestOpen = true"
                                               @keydown.escape="txSuggestOpen = false"
                                               @keydown.enter.prevent="txSuggestions.length && selectTx(txSuggestions[0])"
                                               placeholder="Search treatment…"
                                               class="flex-1 text-sm outline-none bg-transparent font-medium"
                                               autocomplete="off">
                                        <button x-show="txSearch" type="button" @click="clearTx()"
                                                class="text-gray-300 hover:text-gray-500 text-lg leading-none flex-shrink-0">&times;</button>
                                    </div>
                                    <div x-show="txSuggestOpen && txSuggestions.length"
                                         class="absolute left-0 top-full mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg z-30 max-h-48 overflow-y-auto">
                                        <template x-for="sug in txSuggestions" :key="sug">
                                            <button type="button" @mousedown.prevent="selectTx(sug)"
                                                    class="w-full text-left px-3 py-2 text-sm hover:bg-purple-50 hover:text-[#6a0f70] transition-colors"
                                                    x-text="sug"></button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- No-plan fallback: typeahead treatment search --}}
                        <div class="col-span-2" x-show="!form.treatment_plan_id">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Treatment</label>
                            <div class="relative" @click.outside="txSuggestOpen = false">
                                <div class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-2 focus-within:border-[#6a0f70] bg-white transition-colors">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="flex-shrink-0"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                                    <input type="text"
                                           x-model="txSearch"
                                           @input="filterTx()"
                                           @focus="filterTx(); txSuggestOpen = true"
                                           @keydown.escape="txSuggestOpen = false"
                                           @keydown.enter.prevent="txSuggestions.length && selectTx(txSuggestions[0])"
                                           placeholder="Search treatment…"
                                           class="flex-1 text-sm outline-none bg-transparent font-medium"
                                           autocomplete="off">
                                    <button x-show="txSearch" type="button" @click="clearTx()"
                                            class="text-gray-300 hover:text-gray-500 text-lg leading-none flex-shrink-0">&times;</button>
                                </div>
                                <div x-show="txSuggestOpen && txSuggestions.length"
                                     class="absolute left-0 top-full mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg z-30 max-h-48 overflow-y-auto">
                                    <template x-for="sug in txSuggestions" :key="sug">
                                        <button type="button" @mousedown.prevent="selectTx(sug)"
                                                class="w-full text-left px-3 py-2 text-sm hover:bg-purple-50 hover:text-[#6a0f70] transition-colors"
                                                x-text="sug"></button>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- LAYER 3: Add-on treatments --}}
                        <div class="col-span-2">
                            <div class="flex items-center justify-between mb-1.5">
                                <label class="text-xs font-semibold text-gray-600">Add-on Treatments</label>
                                <button type="button" @click="addCustomItem()"
                                        class="flex items-center gap-1 text-xs font-semibold text-[#6a0f70] hover:underline">
                                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                    Add treatment
                                </button>
                            </div>
                            <template x-if="addonItems.length === 0">
                                <p class="text-[11px] text-gray-400 italic">No add-ons. Click "Add treatment" to include extra treatments for today.</p>
                            </template>
                            <div class="space-y-1.5">
                                <template x-for="(item, idx) in addonItems" :key="idx">
                                    <div class="flex items-center gap-2 bg-purple-50 border border-purple-100 rounded-lg px-3 py-2">
                                        <input type="text" x-model="item.treatment_name" @input.debounce.400ms="_checkRepeatWork()"
                                               placeholder="Treatment name"
                                               class="flex-1 text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-[#6a0f70] bg-white">
                                        <input type="text" x-model="item.tooth_number" @input.debounce.400ms="_checkRepeatWork()"
                                               placeholder="Tooth"
                                               class="w-16 text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-[#6a0f70] bg-white">
                                        <button type="button" @click="addonItems.splice(idx,1); _checkRepeatWork()"
                                                class="p-1 text-red-300 hover:text-red-500 hover:bg-red-50 rounded transition-colors">
                                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Tooth Chart --}}
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Tooth No.</label>
                            <div class="relative">
                                <button type="button" @click="toothChartOpen = !toothChartOpen"
                                        class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 text-left focus:outline-none focus:border-[#6a0f70] flex items-center justify-between"
                                        :class="form.tooth_number ? 'text-gray-800 font-semibold' : 'text-gray-400'">
                                    <span x-text="form.tooth_number || 'Select tooth(s)…'"></span>
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                </button>

                                {{-- Tooth chart dropdown --}}
                                <div x-show="toothChartOpen"
                                     @click.outside="toothChartOpen = false"
                                     x-transition:enter="transition ease-out duration-100"
                                     x-transition:enter-start="opacity-0 scale-95"
                                     x-transition:enter-end="opacity-100 scale-100"
                                     class="absolute z-50 top-full left-0 mt-1 bg-white border border-gray-200 rounded-xl shadow-lg p-3"
                                     style="min-width:300px">

                                    <div class="text-[10px] text-gray-400 mb-2 flex items-center justify-between">
                                        <span>Click to select. Click again to deselect. Multiple allowed.</span>
                                        <button type="button" @click="selectedTeeth=[]; form.tooth_number=''" class="text-red-400 hover:text-red-600 font-semibold">Clear</button>
                                    </div>

                                    {{-- Upper jaw --}}
                                    <div class="text-[9px] text-gray-400 text-center mb-1 uppercase tracking-wide">Upper (Maxilla)</div>
                                    <div class="flex justify-center gap-0.5 mb-1">
                                        @foreach([18,17,16,15,14,13,12,11,21,22,23,24,25,26,27,28] as $t)
                                        <button type="button"
                                                @click="toggleTooth({{ $t }})"
                                                :class="selectedTeeth.includes({{ $t }}) ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#6a0f70] hover:text-[#6a0f70]'"
                                                class="w-8 h-8 text-[10px] font-bold border rounded flex items-center justify-center transition-all">
                                            {{ $t }}
                                        </button>
                                        @endforeach
                                    </div>

                                    {{-- Divider --}}
                                    <div class="border-t border-dashed border-gray-200 my-1.5"></div>

                                    {{-- Lower jaw --}}
                                    <div class="flex justify-center gap-0.5 mb-1">
                                        @foreach([48,47,46,45,44,43,42,41,31,32,33,34,35,36,37,38] as $t)
                                        <button type="button"
                                                @click="toggleTooth({{ $t }})"
                                                :class="selectedTeeth.includes({{ $t }}) ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#6a0f70] hover:text-[#6a0f70]'"
                                                class="w-8 h-8 text-[10px] font-bold border rounded flex items-center justify-center transition-all">
                                            {{ $t }}
                                        </button>
                                        @endforeach
                                    </div>
                                    <div class="text-[9px] text-gray-400 text-center mt-1 uppercase tracking-wide">Lower (Mandible)</div>

                                    <button type="button" @click="toothChartOpen = false"
                                            class="mt-2 w-full text-xs font-semibold text-white bg-[#6a0f70] hover:bg-[#570c5d] rounded-lg py-1.5 transition-colors">
                                        Done
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Doctor (auto from logged-in user) --}}
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Doctor</label>
                            <div class="w-full text-sm border border-gray-100 bg-gray-50 rounded-lg px-3 py-2 text-gray-700 font-medium">
                                {{ auth()->user()->name }}
                            </div>
                        </div>

                        {{-- Date --}}
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Visit Date</label>
                            <input type="date" x-model="form.visit_date"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>

                        {{-- Visit Type --}}
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Visit Type</label>
                            <select x-model="form.visit_type"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="treatment">Treatment</option>
                                <option value="followup">Follow-up</option>
                                <option value="emergency">Emergency</option>
                                <option value="recall">Recall</option>
                            </select>
                        </div>

                    </div>

                    {{-- Mark treatment as complete --}}
                    <div x-show="form.treatment_plan_id" class="mt-3">
                        <label class="flex items-center gap-2 cursor-pointer select-none group">
                            <input type="checkbox" x-model="form.mark_treatment_complete"
                                   class="w-4 h-4 rounded border-gray-300 text-[#6a0f70] focus:ring-[#6a0f70] cursor-pointer">
                            <span class="text-sm font-medium text-gray-700 group-hover:text-[#6a0f70] transition-colors">
                                Mark this treatment as <strong>complete</strong>
                            </span>
                        </label>
                        <p x-show="form.mark_treatment_complete"
                           class="text-xs text-amber-600 mt-1 ml-6">
                            The linked treatment plan will be marked as completed.
                        </p>
                    </div>
                </div>

                {{-- ── SECTION 2: Treatment Status + Stages ── --}}
                <div x-show="form.treatment_name">
                    <div class="tv-section-legend">Treatment Progress</div>

                    {{-- Stage pills --}}
                    <div x-show="currentStages && Object.keys(currentStages).length > 0">
                        <p class="text-xs text-gray-400 mb-2">Mark stages completed for this visit:</p>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="(label, key) in currentStages" :key="key">
                                <button type="button"
                                        @click="toggleStage(key)"
                                        :class="form.completed_stages.includes(key) ? 'tv-stage-btn done' : (form.current_stage === key ? 'tv-stage-btn current' : 'tv-stage-btn')"
                                        x-text="label"></button>
                            </template>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-2">
                            <span class="inline-block w-2 h-2 rounded-full bg-green-500 mr-1"></span>Green = done &nbsp;
                            <span class="inline-block w-2 h-2 rounded-full bg-[#6a0f70] mr-1"></span>Purple = current visit
                        </p>
                    </div>
                </div>

                {{-- ── LAB CASE PROMPT ── --}}
                {{-- Shows when the selected treatment is flagged as needs_lab --}}
                <div x-show="labNeeded" x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 -translate-y-1"
                     x-transition:enter-end="opacity-100 translate-y-0"
                     class="border-2 border-amber-300 bg-amber-50 rounded-xl overflow-hidden">

                    {{-- Header bar --}}
                    <div class="flex items-center justify-between px-4 py-3 bg-amber-100 border-b border-amber-200">
                        <div class="flex items-center gap-2">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#92400e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2V9M9 21H5a2 2 0 0 1-2-2V9m0 0h18"/></svg>
                            <span class="text-sm font-bold text-amber-900">Lab Case Required</span>
                            <span class="text-xs font-medium text-amber-700 bg-amber-200 px-2 py-0.5 rounded-full" x-text="form.treatment_name"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-amber-700">Create lab case now?</span>
                            <button type="button" @click="labCase.enabled = !labCase.enabled"
                                    :class="labCase.enabled ? 'bg-amber-600 border-amber-700' : 'bg-white border-amber-300'"
                                    class="relative inline-flex h-5 w-9 items-center rounded-full border-2 transition-colors flex-shrink-0">
                                <span :class="labCase.enabled ? 'translate-x-4 bg-white' : 'translate-x-0.5 bg-amber-300'"
                                      class="inline-block h-3.5 w-3.5 transform rounded-full transition-transform"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Lab case form (collapsible) --}}
                    <div x-show="labCase.enabled" x-collapse class="p-4 space-y-3">
                        <div class="grid grid-cols-2 gap-3">

                            {{-- Lab Vendor --}}
                            <div class="col-span-2">
                                <label class="text-xs font-semibold text-amber-800 block mb-1">Lab / Vendor</label>
                                <select x-model="labCase.lab_vendor_id"
                                        class="w-full text-sm border border-amber-200 bg-white rounded-lg px-3 py-2 focus:outline-none focus:border-amber-500">
                                    <option value="">— Select lab —</option>
                                    @foreach($labVendorsList as $v)
                                    <option value="{{ $v->id }}">{{ $v->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Work Category --}}
                            <div>
                                <label class="text-xs font-semibold text-amber-800 block mb-1">Work Category</label>
                                <select x-model="labCase.work_category" @change="labCase.work_subtype = ''"
                                        class="w-full text-sm border border-amber-200 bg-white rounded-lg px-3 py-2 focus:outline-none focus:border-amber-500">
                                    <option value="">— Select category —</option>
                                    @foreach(\App\Models\LabCase::WORK_CATEGORIES as $cat => $subtypes)
                                    <option value="{{ $cat }}">{{ $cat }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Work Subtype (dynamic based on category) --}}
                            <div>
                                <label class="text-xs font-semibold text-amber-800 block mb-1">Subtype / Material</label>
                                <select x-model="labCase.work_subtype"
                                        class="w-full text-sm border border-amber-200 bg-white rounded-lg px-3 py-2 focus:outline-none focus:border-amber-500">
                                    <option value="">— Select subtype —</option>
                                    <template x-for="st in labSubtypes" :key="st">
                                        <option :value="st" x-text="st"></option>
                                    </template>
                                </select>
                            </div>

                            {{-- Priority --}}
                            <div>
                                <label class="text-xs font-semibold text-amber-800 block mb-1">Priority</label>
                                <div class="flex gap-2">
                                    @foreach(['routine' => 'Routine', 'urgent' => 'Urgent', 'express' => 'Express'] as $pKey => $pLabel)
                                    <button type="button" @click="labCase.priority = '{{ $pKey }}'"
                                            :class="labCase.priority === '{{ $pKey }}'
                                                ? '{{ $pKey === 'routine' ? 'bg-gray-200 border-gray-400 text-gray-800' : ($pKey === 'urgent' ? 'bg-amber-400 border-amber-600 text-white' : 'bg-red-500 border-red-700 text-white') }}'
                                                : 'bg-white border-gray-200 text-gray-500 hover:border-gray-400'"
                                            class="flex-1 text-xs font-semibold py-1.5 rounded-lg border transition-all">
                                        {{ $pLabel }}
                                    </button>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Expected Return Date --}}
                            <div>
                                <label class="text-xs font-semibold text-amber-800 block mb-1">Expected Return</label>
                                <input type="date" x-model="labCase.expected_return_date"
                                       class="w-full text-sm border border-amber-200 bg-white rounded-lg px-3 py-2 focus:outline-none focus:border-amber-500">
                            </div>

                            {{-- Instructions --}}
                            <div class="col-span-2">
                                <label class="text-xs font-semibold text-amber-800 block mb-1">Lab Instructions</label>
                                <textarea x-model="labCase.instructions" rows="2"
                                          placeholder="Shade, special instructions, try-in date…"
                                          class="w-full text-sm border border-amber-200 bg-white rounded-lg px-3 py-2 focus:outline-none focus:border-amber-500 resize-none"></textarea>
                            </div>

                        </div>
                        <p class="text-[11px] text-amber-700">
                            A <strong>Draft</strong> lab case will be created when you save this visit. You can send it to the lab from the Lab module.
                        </p>
                    </div>
                </div>

                {{-- ── SECTION 3: Smart Clinical Fields ── --}}

                {{-- RCT --}}
                <div x-show="form.treatment_name === 'RCT'">
                    <div class="tv-section-legend">RCT Details</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">No. of Canals</label>
                            <select x-model="form.rct_num_canals" @change="syncCanalRows()"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>1</option><option>2</option><option>3</option><option>4</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">File Type</label>
                            <select x-model="form.rct_file_type"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>K-file (Manual)</option>
                                <option>Rotary NiTi</option>
                                <option>Reciprocating</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Irrigant</label>
                            <select x-model="form.rct_irrigant"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>NaOCl 2.5%</option><option>NaOCl 5.25%</option>
                                <option>EDTA</option><option>CHX 2%</option><option>Saline</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Obturation Method</label>
                            <select x-model="form.rct_obturation_method"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>Cold Lateral Condensation</option>
                                <option>Warm Vertical Compaction</option>
                                <option>Single Cone</option>
                            </select>
                        </div>
                    </div>
                    {{-- Canal lengths --}}
                    <div x-show="form.rct_canal_lengths && form.rct_canal_lengths.length > 0" class="mt-3">
                        <label class="text-xs font-semibold text-gray-600 block mb-2">Canal Lengths</label>
                        <div class="grid grid-cols-2 gap-2">
                            <template x-for="(canal, idx) in form.rct_canal_lengths" :key="idx">
                                <div class="flex items-center gap-2 border border-gray-200 rounded-lg px-3 py-1.5">
                                    <select x-model="canal.name" class="text-xs border-0 outline-none bg-transparent font-semibold text-gray-600 w-16">
                                        <option>MB</option><option>DB</option><option>P</option>
                                        <option>MB2</option><option>ML</option><option>DL</option>
                                        <option>C1</option><option>C2</option>
                                    </select>
                                    <input type="text" x-model="canal.length" placeholder="e.g. 21mm"
                                           class="text-xs border-0 outline-none flex-1 text-gray-700">
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Implant --}}
                <div x-show="form.treatment_name === 'Implant'">
                    <div class="tv-section-legend">Implant Details</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Implant Brand</label>
                            <select x-model="form.impl_brand"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>Straumann</option><option>Nobel Biocare</option><option>Osstem</option>
                                <option>MIS</option><option>Neodent</option><option>Adin</option>
                                <option>Alpha Bio</option><option>BioHorizons</option><option>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Size (Dia × Length)</label>
                            <input type="text" x-model="form.impl_size" placeholder="e.g. 4.0 × 10mm"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Insertion Torque</label>
                            <input type="text" x-model="form.impl_torque" placeholder="e.g. 35 Ncm"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Healing Collar</label>
                            <input type="text" x-model="form.impl_healing_collar" placeholder="Size / type"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Graft Used</label>
                            <select x-model="form.impl_graft_used"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">None</option>
                                <option>Autograft</option><option>Allograft</option>
                                <option>Xenograft</option><option>Alloplast</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Graft Brand</label>
                            <input type="text" x-model="form.impl_graft_brand" placeholder="e.g. Bio-Oss"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Membrane</label>
                            <input type="text" x-model="form.impl_membrane" placeholder="e.g. Bio-Gide, Collagen membrane"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                    </div>
                </div>

                {{-- Filling --}}
                <div x-show="form.treatment_name === 'Filling'">
                    <div class="tv-section-legend">Filling Details</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Material</label>
                            <select x-model="form.fill_material"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>Composite</option><option>GIC</option>
                                <option>RMGIC</option><option>Amalgam</option>
                                <option>Compomer</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Shade</label>
                            <input type="text" x-model="form.fill_shade" placeholder="e.g. A2, B1"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                    </div>
                </div>

                {{-- Scaling --}}
                <div x-show="form.treatment_name === 'Scaling'">
                    <div class="tv-section-legend">Scaling Details</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Quadrants</label>
                            <div class="flex flex-wrap gap-1.5 mt-1">
                                @foreach(['UR','UL','LR','LL','Full Mouth'] as $q)
                                <button type="button"
                                        @click="toggleQuadrant('{{ $q }}')"
                                        :class="quadrantSelected('{{ $q }}') ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'bg-white text-gray-500 border-gray-200'"
                                        class="px-2.5 py-1 text-xs border rounded-full font-medium transition-colors">{{ $q }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Method</label>
                            <select x-model="form.scale_method"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>Ultrasonic</option><option>Hand Instruments</option><option>Both</option>
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Extraction --}}
                <div x-show="form.treatment_name === 'Extraction'">
                    <div class="tv-section-legend">Extraction Details</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Type</label>
                            <select x-model="form.ext_type"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>Simple</option><option>Surgical</option><option>Impacted</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Socket Condition</label>
                            <select x-model="form.ext_socket"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>Intact</option><option>Bone graft placed</option>
                                <option>Membrane placed</option><option>Irrigated</option>
                            </select>
                        </div>
                        <div class="flex items-center gap-2 col-span-2">
                            <input type="checkbox" x-model="form.ext_suture" id="ext_suture_cb" class="rounded">
                            <label for="ext_suture_cb" class="text-xs font-semibold text-gray-600">Suture Placed</label>
                        </div>
                    </div>
                </div>

                {{-- Crown Prep --}}
                <div x-show="form.treatment_name === 'Crown Prep'">
                    <div class="tv-section-legend">Crown Details</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Crown Type</label>
                            <select x-model="form.crown_type"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">—</option>
                                <option>PFM</option><option>Zirconia</option><option>Full Metal</option>
                                <option>Emax</option><option>PFZ</option><option>PEEK</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Shade</label>
                            <input type="text" x-model="form.crown_shade" placeholder="e.g. A2, B1"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Temporary Crown</label>
                            <input type="text" x-model="form.crown_temp_placed" placeholder="Material / brand"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div class="flex items-center gap-2 mt-4">
                            <input type="checkbox" x-model="form.crown_impression" id="crown_imp_cb" class="rounded">
                            <label for="crown_imp_cb" class="text-xs font-semibold text-gray-600">Impression Taken</label>
                        </div>
                    </div>
                </div>

                {{-- ── SECTION 4: Notes ── --}}
                <div>
                    <div class="tv-section-legend">Clinical Notes</div>
                    <textarea x-model="form.notes" rows="3"
                              dusk="visit-notes"
                              placeholder="Document what was done today, observations, patient response…"
                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
                </div>

                {{-- ── SECTION 4b: Vitals (optional, collapsible) ── --}}
                <div>
                    {{-- Header toggle --}}
                    <button type="button" @click="vitalsOpen = !vitalsOpen"
                            class="w-full flex items-center gap-2 text-left group">
                        <span class="text-[10px] font-bold uppercase tracking-[.07em] text-[#6a0f70] flex items-center gap-2">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                            Vitals
                            <span class="text-gray-400 font-normal normal-case">— optional</span>
                        </span>
                        {{-- "recorded" pill when any vital is filled --}}
                        <span x-show="form.bp_systolic||form.bp_diastolic||form.pulse_rate||form.spo2||form.temperature||form.blood_sugar||form.weight||form.vitals_notes"
                              class="text-[9px] font-semibold text-green-700 bg-green-50 border border-green-100 rounded-full px-2 py-0.5">recorded</span>
                        <span class="flex-1 h-px bg-gray-100"></span>
                        <svg :class="vitalsOpen ? 'rotate-180' : ''" class="transition-transform text-gray-400" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                    </button>

                    {{-- Fields --}}
                    <div x-show="vitalsOpen" x-collapse class="mt-3 grid grid-cols-2 gap-3">
                        {{-- Blood Pressure --}}
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Blood Pressure <span class="text-gray-400 font-normal">(mmHg)</span></label>
                            <div class="flex items-center gap-1">
                                <input type="number" x-model="form.bp_systolic" min="40" max="300" placeholder="Sys"
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <span class="text-gray-400 font-semibold">/</span>
                                <input type="number" x-model="form.bp_diastolic" min="20" max="200" placeholder="Dia"
                                       class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                            </div>
                        </div>
                        {{-- Pulse --}}
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Pulse <span class="text-gray-400 font-normal">(bpm)</span></label>
                            <input type="number" x-model="form.pulse_rate" min="20" max="250" placeholder="e.g. 72"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        {{-- SpO2 --}}
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Oxygen — SpO₂ <span class="text-gray-400 font-normal">(%)</span></label>
                            <input type="number" x-model="form.spo2" min="50" max="100" placeholder="e.g. 98"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        {{-- Temperature --}}
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Temperature <span class="text-gray-400 font-normal">(°C)</span></label>
                            <input type="number" step="0.1" x-model="form.temperature" min="30" max="45" placeholder="e.g. 36.8"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        {{-- Blood Sugar --}}
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Blood Sugar <span class="text-gray-400 font-normal">(mg/dL)</span></label>
                            <input type="number" x-model="form.blood_sugar" min="20" max="800" placeholder="e.g. 110"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        {{-- Blood Sugar type --}}
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Sugar Reading Type</label>
                            <select x-model="form.blood_sugar_type"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">— Select —</option>
                                <option value="random">Random (RBS)</option>
                                <option value="fasting">Fasting (FBS)</option>
                                <option value="pp">Post-Prandial (PP)</option>
                            </select>
                        </div>
                        {{-- Weight --}}
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Weight <span class="text-gray-400 font-normal">(kg)</span></label>
                            <input type="number" step="0.1" x-model="form.weight" min="1" max="400" placeholder="e.g. 65"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        {{-- Vitals notes --}}
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Vitals Note <span class="text-gray-400 font-normal">(optional)</span></label>
                            <input type="text" x-model="form.vitals_notes" maxlength="255" placeholder="e.g. BP high — advised to consult physician before extraction"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                    </div>
                </div>


                {{-- ── SECTION 5: Procedures for Billing (F2) ── --}}
                <div>
                    <div class="tv-section-legend">Procedures for Billing</div>
                    <p class="text-xs text-gray-400 mb-3">Procedures selected above (from plan + add-ons) appear here. Front desk builds the invoice from these.</p>

                    {{-- Selected / custom items editor --}}
                    <div class="space-y-2 mb-3">
                        <template x-for="(item, idx) in visitItems" :key="idx">
                            <div class="bg-purple-50 border border-purple-100 rounded-lg p-3">
                                <div class="flex items-start gap-2">
                                    <div class="flex-1 grid grid-cols-2 gap-2">
                                        <div>
                                            <label class="text-[10px] font-semibold text-gray-500 block mb-1">Treatment *</label>
                                            <input type="text" x-model="item.treatment_name" @input.debounce.400ms="_checkRepeatWork()"
                                                   class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-[#6a0f70] bg-white"
                                                   placeholder="Treatment name">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-semibold text-gray-500 block mb-1">Material / Option</label>
                                            <input type="text" x-model="item.material_option"
                                                   class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-[#6a0f70] bg-white"
                                                   placeholder="e.g. Ceramic, Zirconia">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-semibold text-gray-500 block mb-1">Tooth #</label>
                                            <input type="text" x-model="item.tooth_number" @input.debounce.400ms="_checkRepeatWork()"
                                                   class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-[#6a0f70] bg-white"
                                                   placeholder="e.g. 26">
                                        </div>
                                        <div>
                                            <label class="text-[10px] font-semibold text-gray-500 block mb-1">Suggested Price (Rs. )</label>
                                            <input type="number" x-model="item.suggested_price" min="0"
                                                   class="w-full text-xs border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:border-[#6a0f70] bg-white"
                                                   placeholder="0">
                                        </div>
                                    </div>
                                    <button type="button" @click="visitItems.splice(idx, 1)"
                                            class="mt-5 flex-shrink-0 p-1 text-red-300 hover:text-red-500 hover:bg-red-50 rounded transition-colors">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>

                    <button type="button" @click="addBillingItem()"
                            class="w-full text-xs font-semibold text-[#6a0f70] border border-dashed border-[#c4a0c7] rounded-lg px-4 py-2.5 hover:bg-purple-50 hover:border-[#6a0f70] transition-colors">
                        + Add custom billing line
                    </button>

                    {{-- Estimated total + billing prompt note --}}
                    <template x-if="visitItems.length > 0">
                        <div class="mt-3 px-3 py-2.5 bg-amber-50 border border-amber-100 rounded-lg flex items-center justify-between">
                            <div>
                                <p class="text-xs font-semibold text-amber-800">Est. Total: Rs.  <span x-text="fmt(visitItemsTotal)"></span></p>
                                <p class="text-[10px] text-amber-600 mt-0.5">A billing prompt will be sent to front desk when you save.</p>
                            </div>
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
                        </div>
                    </template>
                </div>

                {{-- ── SECTION 7: Next Visit ── --}}
                <div>
                    <div class="tv-section-legend">Next Visit</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Next Visit Date</label>
                            <input type="date" x-model="form.next_visit_date"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Next Visit For</label>
                            <select x-model="form.next_visit_type"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">— Select —</option>
                                <option>Continue Treatment</option><option>Review</option>
                                <option>RCT Next Step</option><option>Crown Try-in</option>
                                <option>Crown Cementation</option><option>Implant Review</option>
                                <option>Suture Removal</option><option>Recall</option><option>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

        </div>{{-- end grid --}}
        </div>{{-- end body --}}

        {{-- Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-gray-50">
            <button @click="closeForm()" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 font-medium">
                Cancel
            </button>
            <button @click="saveVisit()" :disabled="saving" dusk="visit-save"
                    class="inline-flex items-center gap-2 bg-[#6a0f70] hover:bg-[#570c5d] disabled:opacity-60 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors">
                <svg x-show="saving" class="animate-spin" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/>
                    <line x1="4.93" y1="4.93" x2="7.76" y2="7.76"/><line x1="16.24" y1="16.24" x2="19.07" y2="19.07"/>
                    <line x1="2" y1="12" x2="6" y2="12"/><line x1="18" y1="12" x2="22" y2="12"/>
                    <line x1="4.93" y1="19.07" x2="7.76" y2="16.24"/><line x1="16.24" y1="7.76" x2="19.07" y2="4.93"/>
                </svg>
                <span x-text="saving ? 'Saving…' : (editingVisit ? 'Update Visit' : 'Save Visit')"></span>
            </button>
        </div>

    </div>

</div>{{-- /x-show visits --}}

@push('scripts')
<script>
const TV_STAGES        = {{ Js::from($stagesJson) }};
const TV_LAB_TREATMENTS = {{ Js::from($labTreatmentsMap) }};
const TV_LAB_CATEGORIES = {{ Js::from(\App\Models\LabCase::WORK_CATEGORIES) }};
const TV_APPOINTMENTS  = {{ Js::from($appointmentsJson) }};
const TV_TREATMENTS    = {{ Js::from($treatmentsList) }};

function treatmentVisits() {
    return {
        visits: {{ Js::from($visitsJson) }},
        stages: TV_STAGES,

        activeFilter: 'all',
        search: '',
        filters: [
            { key: 'all',       label: 'All' },
            { key: 'treatment', label: 'Treatment' },
            { key: 'followup',  label: 'Follow-up' },
            { key: 'emergency', label: 'Emergency' },
            { key: 'scheduled', label: 'Scheduled' },
            { key: 'in_chair',  label: 'In Chair' },
            { key: 'completed', label: 'Completed' },
            { key: 'due',       label: 'Billing Pending' },
        ],

        formOpen: false,
        saving: false,
        errorMsg: '',
        editingVisit: null,
        // Treatment typeahead
        txSearch: '',
        txSuggestions: [],
        txSuggestOpen: false,
        // Tooth chart state
        toothChartOpen: false,
        selectedTeeth: [],
        // Vitals collapsible (collapsed by default — optional section)
        vitalsOpen: false,
        // Treatment plans for the dropdown — seeded from server, refreshed on form open
        // so a plan created/accepted in the Plan tab shows up WITHOUT a page reload.
        treatmentPlans: @json($treatmentPlansJson),
        // Plan items + visit items state
        planItems: [],
        planItemsLoading: false,
        visitItems: [],   // billing line items (from plan + add-ons + custom)
        addonItems: [],   // layer-3 add-on procedures (lightweight, merged into visitItems on save)
        form: {},

        // Repeat-work detection
        repeatWarnings: [],   // [{treatment_name, tooth, date, originalItemId}]
        repeatReason: '',     // staff-entered reason (required when repeats exist)

        // Lab case state — populated from the lab prompt section
        labCase: {
            enabled:              false,
            lab_vendor_id:        '',
            work_category:        '',
            work_subtype:         '',
            priority:             'routine',
            expected_return_date: '',
            instructions:         '',
        },

        init() { this.form = this._blank(); },

        get filteredVisits() {
            let out = this.visits;
            if (['scheduled','in_chair','completed','cancelled','no_show'].includes(this.activeFilter)) out = out.filter(v => v.status === this.activeFilter);
            else if (this.activeFilter === 'due')        out = out.filter(v => v.visit_items && v.visit_items.some(i => i.billing_status === 'pending'));
            else if (this.activeFilter !== 'all')        out = out.filter(v => v.visit_type === this.activeFilter);

            if (this.search.trim()) {
                const q = this.search.toLowerCase();
                out = out.filter(v =>
                    (v.treatment_name||'').toLowerCase().includes(q) ||
                    (v.tooth_number||'').toLowerCase().includes(q) ||
                    (v.notes||'').toLowerCase().includes(q) ||
                    (v.doctor_name||'').toLowerCase().includes(q)
                );
            }
            // Always show newest visits first
            return [...out].sort((a, b) => new Date(b.visit_date || 0) - new Date(a.visit_date || 0));
        },

        get currentStages() {
            return this.stages[this.form.treatment_name] || {};
        },

        // F2: total of suggested prices on selected visit items
        get visitItemsTotal() {
            return this.visitItems.reduce((s, i) => s + (parseFloat(i.suggested_price)||0), 0);
        },

        // True when the selected treatment needs lab work
        get labNeeded() {
            return !!(this.form.treatment_name && TV_LAB_TREATMENTS[this.form.treatment_name]);
        },

        // Work subtypes for the selected lab category
        get labSubtypes() {
            return TV_LAB_CATEGORIES[this.labCase.work_category] || [];
        },

        _blank() {
            return {
                appointment_id: '',
                visit_date: new Date().toISOString().slice(0,10),
                visit_type: 'treatment',
                status: 'scheduled',
                doctor_id: '{{ auth()->id() }}',
                treatment_plan_id: '',
                plan_item_id: '',        // selected plan item (layer 2)
                treatment_name: '',
                current_stage: '',
                completed_stages: [],
                tooth_number: '',
                notes: '',
                chief_complaint: '',
                next_visit_date: '', next_visit_type: '',
                mark_treatment_complete: false,
                rct_num_canals: '', rct_canal_lengths: [], rct_file_type: '', rct_irrigant: '', rct_obturation_method: '',
                impl_brand: '', impl_size: '', impl_torque: '', impl_graft_used: '', impl_graft_brand: '', impl_membrane: '', impl_healing_collar: '',
                fill_material: '', fill_shade: '',
                scale_quadrants: '', scale_method: '',
                ext_type: '', ext_socket: '', ext_suture: false,
                crown_type: '', crown_shade: '', crown_impression: false, crown_temp_placed: '',
                prescription_drugs: [], prescription_instructions: [], prescription_custom_notes: '',
                // Vitals (optional)
                bp_systolic: '', bp_diastolic: '', pulse_rate: '', spo2: '', temperature: '',
                blood_sugar: '', blood_sugar_type: '', weight: '', vitals_notes: '',
            };
        },

        // When an appointment is selected, auto-fill visit_date and doctor
        onAppointmentChange() {
            if (!this.form.appointment_id) return;
            const appt = TV_APPOINTMENTS.find(a => String(a.id) === String(this.form.appointment_id));
            if (!appt) return;
            if (appt.date)      this.form.visit_date = appt.date;
            if (appt.doctor_id) this.form.doctor_id  = appt.doctor_id;
        },

        onTreatmentChange() {
            this.form.completed_stages = [];
            this.form.current_stage = '';
            if (this.form.treatment_name === 'RCT') this.form.rct_canal_lengths = [];
            this._carryForwardStages();
            // Auto-configure lab case prompt when treatment needs lab
            this._syncLabCase();
            this._checkRepeatWork();
        },

        // ── Treatment typeahead helpers ──
        filterTx() {
            const q = this.txSearch.toLowerCase().trim();
            this.txSuggestions = q
                ? TV_TREATMENTS.filter(t => t.toLowerCase().includes(q)).slice(0, 12)
                : TV_TREATMENTS.slice(0, 12);
            this.txSuggestOpen = this.txSuggestions.length > 0;
        },
        selectTx(name) {
            this.form.treatment_name = name;
            this.txSearch            = name;
            this.txSuggestOpen       = false;
            this.onTreatmentChange();
        },
        clearTx() {
            this.form.treatment_name = '';
            this.txSearch            = '';
            this.txSuggestions       = [];
            this.txSuggestOpen       = false;
            this.onTreatmentChange();
        },

        _syncLabCase() {
            const info = TV_LAB_TREATMENTS[this.form.treatment_name];
            if (info) {
                // Pre-fill work category from treatment's default; keep enabled state user chose
                if (info.work_category && !this.labCase.work_category) {
                    this.labCase.work_category = info.work_category;
                }
                // Auto-enable the toggle when treatment changes to a lab-needing one
                this.labCase.enabled = true;
            } else {
                // Reset if treatment changed to one without lab
                this.labCase.enabled        = false;
                this.labCase.work_category  = '';
                this.labCase.work_subtype   = '';
            }
        },

        // When treatment+tooth combo matches an existing in-progress visit,
        // pre-load that visit's completed stages so the doctor continues from where they left off.
        _carryForwardStages() {
            if (!this.form.treatment_name || !this.form.tooth_number) return;
            if (this.editingVisit) return; // editing an existing visit — don't override
            const match = this.visits.find(v =>
                v.treatment_name === this.form.treatment_name &&
                v.tooth_number   === this.form.tooth_number   &&
                v.status         !== 'completed'              &&
                (!this.editingVisit || v.id !== this.editingVisit.id)
            );
            if (match && match.completed_stages && match.completed_stages.length > 0) {
                this.form.completed_stages = [...match.completed_stages];
                this.form.current_stage    = match.current_stage || '';
                // Show a subtle hint (won't block saving)
                this.errorMsg = '↩ Stages carried forward from last visit on Tooth ' + this.form.tooth_number + '. Mark new stages done.';
                setTimeout(() => { if (this.errorMsg.startsWith('↩')) this.errorMsg = ''; }, 4000);
            }
        },

        // Tooth chart helpers
        toggleTooth(n) {
            const idx = this.selectedTeeth.indexOf(n);
            if (idx >= 0) this.selectedTeeth.splice(idx, 1);
            else this.selectedTeeth.push(n);
            this.form.tooth_number = this.selectedTeeth.slice().sort((a,b)=>a-b).join(', ');
            this._carryForwardStages();
            this._checkRepeatWork();
        },

        // ── Repeat-work detection ────────────────────────────────────────────
        // Split a "45, 46" style string into a clean array of tooth tokens.
        _teethSet(str) {
            if (!str) return [];
            return String(str).split(/[,\s]+/).map(s => s.trim()).filter(Boolean);
        },

        // Look for any PAST visit where the same treatment was done on the same
        // tooth for this patient. Runs entirely on already-loaded history, so it
        // is instant. Populates this.repeatWarnings.
        _checkRepeatWork() {
            this.repeatWarnings = [];
            if (this.editingVisit) return; // editing an existing visit — don't warn

            // What is being recorded right now (treatment + effective tooth)
            const current = [];
            const push = (name, tooth) => { if (name) current.push({ name: String(name).trim(), tooth }); };
            this.visitItems.forEach(i => push(i.treatment_name, i.tooth_number || this.form.tooth_number));
            this.addonItems.forEach(a => push(a.treatment_name, a.tooth_number || this.form.tooth_number));
            if (this.form.treatment_name) push(this.form.treatment_name, this.form.tooth_number);

            const found = {}; // key "name|tooth" -> warning (most recent date wins)

            current.forEach(cur => {
                const teeth = this._teethSet(cur.tooth);
                if (teeth.length === 0) return;
                const curName = cur.name.toLowerCase();

                this.visits.forEach(v => {
                    if (this.editingVisit && v.id === this.editingVisit.id) return;

                    // Past records to compare against: each visit item, plus the
                    // visit-level treatment (older visits may have no line items).
                    const past = [];
                    (v.visit_items || []).forEach(pi => past.push({ name: pi.treatment_name, tooth: pi.tooth_number, id: pi.id }));
                    if (v.treatment_name) past.push({ name: v.treatment_name, tooth: v.tooth_number, id: null });

                    past.forEach(p => {
                        if (!p.name || String(p.name).toLowerCase() !== curName) return;
                        const overlap = this._teethSet(p.tooth).filter(t => teeth.includes(t));
                        overlap.forEach(t => {
                            const key = curName + '|' + t;
                            const existing = found[key];
                            if (!existing || (v.visit_date && v.visit_date > existing.date)) {
                                found[key] = {
                                    treatment_name: cur.name,
                                    tooth: t,
                                    date: v.visit_date || '',
                                    originalItemId: p.id,
                                };
                            }
                        });
                    });
                });
            });

            this.repeatWarnings = Object.values(found);
            // Clear a stale reason if there are no longer any repeats
            if (this.repeatWarnings.length === 0) this.repeatReason = '';
        },

        // Pretty date for the warning banner (YYYY-MM-DD -> DD Mon YYYY)
        _fmtDate(d) {
            if (!d) return 'a previous visit';
            const dt = new Date(d + 'T00:00:00');
            if (isNaN(dt)) return d;
            return dt.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        },

        _syncTeethFromString() {
            // Parse comma/space separated tooth numbers into selectedTeeth array
            if (!this.form.tooth_number) { this.selectedTeeth = []; return; }
            this.selectedTeeth = this.form.tooth_number.split(/[,\s]+/)
                .map(s => parseInt(s.trim()))
                .filter(n => !isNaN(n));
        },

        toggleStage(key) {
            const idx = this.form.completed_stages.indexOf(key);
            if (idx >= 0) {
                this.form.completed_stages.splice(idx, 1);
                this.form.current_stage = key;
            } else {
                this.form.completed_stages.push(key);
                this.form.current_stage = key;
                const stageKeys = Object.keys(this.currentStages);
                const lastDone = stageKeys.filter(k => this.form.completed_stages.includes(k)).pop();
                const nextIdx  = stageKeys.indexOf(lastDone) + 1;
                if (nextIdx < stageKeys.length) this.form.current_stage = stageKeys[nextIdx];
            }
        },

        syncCanalRows() {
            const n = parseInt(this.form.rct_num_canals) || 0;
            const names = ['MB','DB','P','MB2','DL'];
            this.form.rct_canal_lengths = Array.from({length: n}, (_, i) => ({
                name: this.form.rct_canal_lengths[i]?.name || names[i] || 'C'+(i+1),
                length: this.form.rct_canal_lengths[i]?.length || '',
            }));
        },

        toggleQuadrant(q) {
            const parts = this.form.scale_quadrants ? this.form.scale_quadrants.split(',').map(s=>s.trim()).filter(Boolean) : [];
            if (q === 'Full Mouth') { this.form.scale_quadrants = parts.includes('Full Mouth') ? '' : 'Full Mouth'; return; }
            const idx = parts.indexOf(q);
            idx >= 0 ? parts.splice(idx,1) : parts.push(q);
            this.form.scale_quadrants = parts.join(',');
        },

        quadrantSelected(q) {
            if (!this.form.scale_quadrants) return false;
            return this.form.scale_quadrants.split(',').map(s=>s.trim()).includes(q);
        },

        // ── Rx helpers ──
        rxOpen: false,
        rxAddDrug() {
            this.form.prescription_drugs.push({name:'',sos:false,morning:'',noon:'',night:'',duration:'',dur_unit:'days',total_qty:'',food:'',language:'English',instruction:''});
        },
        rxCalcTotal(i) {
            const d = this.form.prescription_drugs[i];
            const perDay = (parseFloat(d.morning)||0)+(parseFloat(d.noon)||0)+(parseFloat(d.night)||0);
            const dur = parseFloat(d.duration)||0;
            const mult = d.dur_unit==='weeks'?7:1;
            d.total_qty = perDay>0&&dur>0 ? Math.ceil(perDay*dur*mult) : '';
        },
        rxToggleInstr(instr) {
            const idx = this.form.prescription_instructions.indexOf(instr);
            idx>=0 ? this.form.prescription_instructions.splice(idx,1) : this.form.prescription_instructions.push(instr);
        },

        // ── F2: Visit items + plan item helpers ──────────────────────────────

        onPlanChange() {
            // Reset layer-2 selection and plan items; reload
            this.form.plan_item_id = '';
            this.form.treatment_name = '';
            this.form.completed_stages = [];
            this.form.current_stage = '';
            this.visitItems = [];
            this.planItems  = [];
            if (this.form.treatment_plan_id) this.loadPlanItems();
        },

        // Layer-2: select a plan item as the primary treatment for this visit.
        // Passing null means "Other / Not in plan" — free-text mode.
        selectPlanTreatment(pi) {
            if (!pi) {
                this.form.plan_item_id   = '__other__';
                this.form.treatment_name = '';
                // Remove any previously auto-added plan item from visitItems
                this.visitItems = this.visitItems.filter(i => i._fromPlanLayer2 !== true);
                this.onTreatmentChange();
                return;
            }
            // Toggle: clicking the already-selected item deselects it
            if (this.form.plan_item_id == pi.id) {
                this.form.plan_item_id   = '';
                this.form.treatment_name = '';
                this.visitItems = this.visitItems.filter(i => i._fromPlanLayer2 !== true);
                this.onTreatmentChange();
                return;
            }
            this.form.plan_item_id   = pi.id;
            this.form.treatment_name = pi.treatment_name;
            // Remove previous layer-2 billing item, add new one
            this.visitItems = this.visitItems.filter(i => i._fromPlanLayer2 !== true);
            this.visitItems.unshift({
                _fromPlanLayer2:        true,
                treatment_plan_item_id: pi.id,
                treatment_name:         pi.treatment_name,
                material_option:        '',
                tooth_number:           pi.tooth_number || '',
                suggested_price:        pi.unit_price   || '',
                notes:                  '',
            });
            this.onTreatmentChange();
        },

        _loadPlanItemsOLD() { /* replaced below */ },

        isPlanItemSelected(planItemId) {
            return this.visitItems.some(i => i.treatment_plan_item_id == planItemId);
        },

        togglePlanItem(pi) {
            const idx = this.visitItems.findIndex(i => i.treatment_plan_item_id == pi.id);
            if (idx >= 0) {
                this.visitItems.splice(idx, 1);
            } else {
                this.visitItems.push({
                    treatment_plan_item_id: pi.id,
                    treatment_name:  pi.treatment_name,
                    material_option: '',
                    tooth_number:    pi.tooth_number || '',
                    suggested_price: pi.unit_price   || '',
                    notes:           '',
                });
            }
            this._checkRepeatWork();
        },

        // Layer-3: add an add-on procedure row
        addCustomItem() {
            this.addonItems.push({
                treatment_name: '',
                tooth_number:   '',
            });
        },

        // Billing section: add a blank custom billing line
        addBillingItem() {
            this.visitItems.push({
                treatment_plan_item_id: null,
                treatment_name:  '',
                material_option: '',
                tooth_number:    '',
                suggested_price: '',
                notes:           '',
            });
        },

        openAddForm() {
            this.editingVisit = null;
            this.form = this._blank();
            this.visitItems    = [];
            this.addonItems    = [];
            this.planItems     = [];
            this.selectedTeeth = [];
            this.toothChartOpen = false;
            this.errorMsg = '';
            this.repeatWarnings = []; this.repeatReason = '';
            this.txSearch = ''; this.txSuggestions = []; this.txSuggestOpen = false;
            this.labCase = { enabled: false, lab_vendor_id: '', work_category: '', work_subtype: '', priority: 'routine', expected_return_date: '', instructions: '' };
            this.loadTreatmentPlans();   // refresh plan list so new/accepted plans show without reload
            this.formOpen = true;
        },

        openEditForm(visit) {
            this.editingVisit = visit;
            this.form = {
                appointment_id:    visit.appointment_id || '',
                visit_date:        visit.visit_date || '',
                visit_type:        visit.visit_type || 'treatment',
                status:            visit.status || 'scheduled',
                doctor_id:         visit.doctor_id || '',
                treatment_plan_id: visit.treatment_plan_id || '',
                plan_item_id:      '',  // restored below after planItems load
                treatment_name:    visit.treatment_name || '',
                current_stage:     visit.current_stage || '',
                completed_stages:  [...(visit.completed_stages||[])],
                tooth_number:      visit.tooth_number || '',
                notes:             visit.notes || '',
                chief_complaint:   visit.chief_complaint || '',
                next_visit_date:   visit.next_visit_date || '',
                next_visit_type:   visit.next_visit_type || '',
                rct_num_canals:        visit.rct_num_canals || '',
                rct_canal_lengths:     [...(visit.rct_canal_lengths||[])],
                rct_file_type:         visit.rct_file_type || '',
                rct_irrigant:          visit.rct_irrigant || '',
                rct_obturation_method: visit.rct_obturation_method || '',
                impl_brand:            visit.impl_brand || '',
                impl_size:             visit.impl_size || '',
                impl_torque:           visit.impl_torque || '',
                impl_graft_used:       visit.impl_graft_used || '',
                impl_graft_brand:      visit.impl_graft_brand || '',
                impl_membrane:         visit.impl_membrane || '',
                impl_healing_collar:   visit.impl_healing_collar || '',
                fill_material:         visit.fill_material || '',
                fill_shade:            visit.fill_shade || '',
                scale_quadrants:       visit.scale_quadrants || '',
                scale_method:          visit.scale_method || '',
                ext_type:              visit.ext_type || '',
                ext_socket:            visit.ext_socket || '',
                ext_suture:            visit.ext_suture || false,
                crown_type:            visit.crown_type || '',
                crown_shade:           visit.crown_shade || '',
                crown_impression:      visit.crown_impression || false,
                crown_temp_placed:     visit.crown_temp_placed || '',
                prescription_drugs:        [...(visit.prescription_drugs||[])],
                prescription_instructions: [...(visit.prescription_instructions||[])],
                prescription_custom_notes: visit.prescription_custom_notes || '',
                // Vitals
                bp_systolic:       visit.bp_systolic ?? '',
                bp_diastolic:      visit.bp_diastolic ?? '',
                pulse_rate:        visit.pulse_rate ?? '',
                spo2:              visit.spo2 ?? '',
                temperature:       visit.temperature ?? '',
                blood_sugar:       visit.blood_sugar ?? '',
                blood_sugar_type:  visit.blood_sugar_type || '',
                weight:            visit.weight ?? '',
                vitals_notes:      visit.vitals_notes || '',
            };
            // Sync typeahead with existing treatment name
            this.txSearch = visit.treatment_name || '';
            this.txSuggestions = []; this.txSuggestOpen = false;
            // populate visit items, addon items, plan items, and tooth chart state
            this.visitItems = (visit.visit_items || []).map(i => ({...i}));
            this.addonItems = [];
            this.planItems  = [];
            if (this.form.treatment_plan_id) this.loadPlanItems();
            this._syncTeethFromString();
            this.toothChartOpen = false;
            this.errorMsg = '';
            // Reset lab case (no editing of existing lab cases from here — use Lab module)
            this.labCase = { enabled: false, lab_vendor_id: '', work_category: '', work_subtype: '', priority: 'routine', expected_return_date: '', instructions: '' };
            this.loadTreatmentPlans();   // refresh plan list so new/accepted plans show without reload
            this.formOpen = true;
        },

        closeForm() {
            this.formOpen = false;
            this.editingVisit = null;
            this.errorMsg = '';
            this.form = this._blank();
            this.visitItems = [];
            this.addonItems = [];
            this.planItems  = [];
            this.selectedTeeth = [];
            this.repeatWarnings = []; this.repeatReason = '';
            this.txSearch = ''; this.txSuggestions = []; this.txSuggestOpen = false;
        },

        async saveVisit() {
            // Repeat work flagged but no reason given — block and prompt for it.
            this._checkRepeatWork();
            if (this.repeatWarnings.length > 0 && !this.repeatReason.trim()) {
                this.errorMsg = 'This looks like repeat work. Please add a reason before saving.';
                return;
            }

            this.saving = true;
            this.errorMsg = '';
                    try {
                const patientId = {{ $patient->id }};
                const isEdit    = !!this.editingVisit;
                const url       = isEdit
                    ? `{{ url('/visits') }}/${this.editingVisit.id}`
                    : `{{ url('/patients') }}/${patientId}/visits`;
                const method = isEdit ? 'PUT' : 'POST';

                // Helper: does this (treatment, tooth) match a detected repeat?
                // When editing an existing visit we skip detection, so preserve
                // whatever repeat flags the item already had.
                const reason = this.repeatReason.trim();
                const tagRepeat = (item, name, tooth) => {
                    if (this.editingVisit) {
                        return {
                            is_repeat:               !!item.is_repeat,
                            repeat_reason:           item.repeat_reason ?? null,
                            repeat_of_visit_item_id: item.repeat_of_visit_item_id ?? null,
                        };
                    }
                    const teeth = this._teethSet(tooth || this.form.tooth_number);
                    const w = this.repeatWarnings.find(rw =>
                        rw.treatment_name.toLowerCase() === String(name || '').toLowerCase() &&
                        teeth.includes(rw.tooth)
                    );
                    return w
                        ? { is_repeat: true, repeat_reason: reason, repeat_of_visit_item_id: w.originalItemId }
                        : { is_repeat: false, repeat_reason: null, repeat_of_visit_item_id: null };
                };

                const allVisitItems = [
                    ...this.visitItems.map(i => ({ ...i, ...tagRepeat(i, i.treatment_name, i.tooth_number) })),
                    ...this.addonItems.filter(a => a.treatment_name).map(a => ({
                        treatment_plan_item_id: null,
                        treatment_name:         a.treatment_name,
                        material_option:        '',
                        tooth_number:           a.tooth_number || this.form.tooth_number || '',
                        suggested_price:        '',
                        notes:                  '',
                        ...tagRepeat(a, a.treatment_name, a.tooth_number),
                    })),
                ];

                const payload = {
                    ...this.form,
                    tooth_number: this.form.tooth_number,
                    visit_items:  allVisitItems,
                    lab_case:     this.labCase.enabled ? this.labCase : null,
                };

                const resp = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type':     'application/json',
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify(payload),
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) throw new Error(data.message || 'Save failed.');

                if (isEdit) {
                    const idx = this.visits.findIndex(v => v.id === this.editingVisit.id);
                    if (idx >= 0) this.visits.splice(idx, 1, data.visit);
                } else {
                    this.visits.unshift(data.visit);
                }
                this.closeForm();
            } catch (e) {
                this.errorMsg = e.message;
            } finally {
                this.saving = false;
            }
        },

        async deleteVisit(id) {
            if (!confirm('Delete this visit?')) return;
            try {
                const resp = await fetch(`{{ url('/visits') }}/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Accept':           'application/json',
                        'X-CSRF-TOKEN':     document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) throw new Error(data.message || 'Delete failed.');
                this.visits = this.visits.filter(v => v.id !== id);
            } catch (e) {
                alert(e.message);
            }
        },

        // Re-fetch this patient's treatment plans (accepted only — same rule the
        // server uses to seed the dropdown). Keeps the dropdown fresh so a plan
        // accepted in the Plan tab appears here without reloading the page.
        loadTreatmentPlans() {
            fetch(`{{ url('/patients/'.$patient->id.'/treatment-plans') }}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
            .then(r => r.json())
            .then(d => {
                const plans = (d.plans || []).filter(p => p.is_accepted);
                this.treatmentPlans = plans.map(p => ({
                    id: p.id, plan_name: p.plan_name, status: p.status,
                }));
            })
            .catch(() => { /* keep the server-seeded list on failure */ });
        },

        loadPlanItems() {
            if (!this.form.treatment_plan_id) { this.planItems = []; return; }
            this.planItemsLoading = true;
            fetch(`{{ url('/treatment-plans') }}/${this.form.treatment_plan_id}/items`, {
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            })
            .then(r => r.json())
            .then(d => { this.planItems = d.items || []; })
            .catch(() => { this.planItems = []; })
            .finally(() => { this.planItemsLoading = false; });
        },

        fmt(n)    { return Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
        fmtInt(n) { return Number(n).toLocaleString('en-IN', { maximumFractionDigits: 0 }); },
    };
}
</script>
@endpush
