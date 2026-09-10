{{-- Bootstrap extracted (08-05) to patients/partials/_treatment-visit-bootstrap.php
     so the dedicated Treatment Visit page can share it verbatim. Native PHP
     `include` (not Blade @include) — see that file's header comment for why:
     Blade @include() renders an isolated View and its variables don't leak
     back to this scope, which the later @includes on this page depend on. --}}
@php
    include resource_path('views/patients/partials/_treatment-visit-bootstrap.php');
@endphp

{{-- .tv-stage-btn / .tv-section-legend now live in treatment-visit-form-fields.blade.php
     (08-05 polish sprint) — that's the one file both this tab fragment AND the
     dedicated visits.create/visits.edit page include, so the styling loads on
     both instead of being missing from the dedicated page. Do not redefine here. --}}

<div
    x-show="activeTab === 'visits'"
    style="display:none"
    x-transition:enter="transition ease-out duration-150"
    x-transition:enter-start="opacity-0 translate-y-1"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-data="treatmentVisits()"
    class="w-full px-6 py-6"
>

    {{-- UX-01 (Freeze Spec, 2026-08-05): the per-patient KPI summary bar was
         removed — dashboard aggregates don't belong on a data-entry tab.
         Clinic-wide visit analytics move to a reporting surface in V2. --}}

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
        <a href="{{ route('visits.create', $patient) }}"
           dusk="visit-add"
           class="inline-flex items-center gap-2 bg-[#6a0f70] hover:bg-[#570c5d] text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors shadow-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add Visit
        </a>
    </div>

    {{-- Search (UX-01: filter chips removed — search carries the load) --}}
    <div class="flex items-center mb-4">
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
                   x-text="search ? 'No visits match this search' : 'No visits recorded yet'"></p>
                <p class="text-xs text-gray-400">
                    <span x-show="!search">Click "Add Visit" to record the first visit.</span>
                    <span x-show="search">Try clearing the search.</span>
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

                        {{-- Chief Complaint — desktop parity sprint: captured on
                             every Create/Edit visit but was never shown on this
                             card at all, unlike Notes right below it. Same
                             line-clamp treatment as Notes, just labeled since
                             "complaint" and "notes" read differently unlabeled. --}}
                        <p x-show="visit.chief_complaint" class="text-xs text-gray-500 mt-0.5 line-clamp-1"><span class="font-semibold text-gray-600">Complaint:</span> <span x-text="visit.chief_complaint"></span></p>
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

                        {{-- N-2: doctor → front desk handover --}}
                        <div x-show="visit.handover_summary"
                             class="mt-1 inline-flex items-center gap-1 text-[10px] text-purple-700 bg-purple-50 px-2 py-0.5 rounded-full ml-1">
                            Desk: <span x-text="visit.handover_summary"></span>
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
                            <a :href="`/visits/${visit.id}/edit`"
                               class="p-1.5 rounded text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 transition-colors">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </a>
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
         ADD / EDIT — moved (08-05, presentation-only) to a dedicated page:
         GET /patients/{patient}/visits/create and /visits/{visit}/edit
         (TreatmentVisitController::create/edit, patients/treatment-visit-form.blade.php).
         "+ Add Visit" and row "Edit" below now link there instead of opening
         an inline modal. Same store()/update() endpoints, same payload, same
         validation, same treatmentVisits() Alpine factory (still shared via
         the include below — this fragment keeps it for the timeline's own
         `visits`/`search` state; the form-only state/methods it also carries
         are simply unused here now).
    ══════════════════════════════════════════════════════════════════ --}}

</div>{{-- /x-show visits --}}

@include('patients.partials.treatment-visit-form-script')
