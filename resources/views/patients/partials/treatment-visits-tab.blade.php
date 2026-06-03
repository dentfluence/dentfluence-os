@php
$treatmentStages = \App\Models\TreatmentVisit::$treatmentStages;

$visitsJson = $patient->treatmentVisits->map(function($v) {
    return [
        'id'               => $v->id,
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
        'prescription_drugs'        => $v->prescription_drugs ?? [],
        'prescription_instructions' => $v->prescription_instructions ?? [],
        'prescription_custom_notes' => $v->prescription_custom_notes,
        '_isNew' => false,
    ];
});

$stagesJson = $treatmentStages;
$doctorsList = $doctors ?? collect();
@endphp

<style>
    .tv-rx-input { border:1px solid #e5e7eb;border-radius:5px;padding:5px 8px;font-size:12px;color:#374151;background:white;outline:none;transition:border-color .15s;width:100%; }
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
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        @php
            $tvAll       = $patient->treatmentVisits;
            $tvCompleted = $tvAll->where('status','completed')->count();
            $tvOngoing   = $tvAll->whereIn('status',['scheduled','in_chair'])->count();
            $tvCost      = $tvAll->sum('cost');
            $tvPaid      = $tvAll->sum('amount_paid');
            $tvBalance   = $tvCost - $tvPaid;
        @endphp
        @foreach([
            ['Total Visits',  $tvAll->count(),                    'bg-[#6a0f70]', 'text-white',     null],
            ['Completed',     $tvCompleted,                       'bg-green-600', 'text-white',     null],
            ['In Progress',   $tvOngoing,                         'bg-blue-600',  'text-white',     null],
            ['Total Billed',  '₹ '.number_format($tvCost, 0),    'bg-white',     'text-gray-800',  'border border-gray-200'],
            ['Amount Paid',   '₹ '.number_format($tvPaid, 0),    'bg-white',     'text-green-700', 'border border-gray-200'],
            ['Balance Due',   '₹ '.number_format($tvBalance, 0), 'bg-white',     $tvBalance > 0 ? 'text-red-600' : 'text-gray-400', 'border border-gray-200'],
        ] as [$label, $val, $bg, $col, $extra])
        <div class="rounded-lg px-4 py-3 {{ $bg }} {{ $extra }}">
            <div class="text-[10px] uppercase tracking-wide opacity-70 mb-0.5 {{ $bg === 'bg-white' ? 'text-gray-400' : 'text-white' }}">{{ $label }}</div>
            <div class="text-lg font-bold {{ $col }}">{{ $val }}</div>
        </div>
        @endforeach
    </div>

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div>
            <h3 class="text-base font-semibold text-gray-900">Treatment Visit Log</h3>
            <p class="text-xs text-gray-400 mt-0.5">Full clinical record of all visits for this patient</p>
        </div>
        <button @click="openAddForm()"
                x-show="!formOpen || editingVisit"
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
                            <span x-show="visit.balance_due > 0"
                                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-red-50 text-red-600 border border-red-100">
                                ₹ <span x-text="fmt(visit.balance_due)"></span> due
                            </span>
                            <span x-show="visit.balance_due <= 0 && visit.status === 'completed'"
                                  class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-green-50 text-green-700">Paid</span>
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
                    </div>

                    {{-- Right: cost + actions --}}
                    <div class="flex-shrink-0 text-right space-y-1 ml-2">
                        <div x-show="visit.cost > 0" class="text-sm font-bold text-gray-800">₹ <span x-text="fmt(visit.cost)"></span></div>
                        <div x-show="visit.amount_paid > 0" class="text-xs text-green-600">Paid ₹ <span x-text="fmt(visit.amount_paid)"></span></div>
                        <div x-show="visit.payment_mode && visit.payment_mode !== 'pending'"
                             class="text-[10px] text-gray-400 uppercase" x-text="visit.payment_mode"></div>
                        <div class="flex items-center gap-1 justify-end mt-2">
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
         ADD / EDIT INLINE FORM
    ══════════════════════════════════════════════════════════════════ --}}
    <div id="tv-inline-form" x-show="formOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-2"
         x-transition:enter-end="opacity-100 translate-y-0"
         class="mt-6 bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">

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
        <div class="px-6 py-5">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-8 gap-y-6">

                {{-- Error --}}
                <div x-show="errorMsg" class="flex items-center gap-2 px-4 py-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span x-text="errorMsg"></span>
                </div>

                {{-- ── SECTION 1: Visit Header ── --}}
                <div>
                    <div class="tv-section-legend">Visit Details</div>
                    <div class="grid grid-cols-2 gap-3">

                        {{-- Treatment --}}
                        <div class="col-span-2">
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Treatment</label>
                            <select x-model="form.treatment_name" @change="onTreatmentChange()"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70] font-medium">
                                <option value="">— Select Treatment —</option>
                                <option>RCT</option>
                                <option>Filling</option>
                                <option>Implant</option>
                                <option>Scaling</option>
                                <option>Extraction</option>
                                <option>Crown Prep</option>
                                <option>Crown Cementation</option>
                                <option>Denture</option>
                                <option>Orthodontic Review</option>
                                <option>Consultation</option>
                                <option>Other</option>
                            </select>
                        </div>

                        {{-- Tooth --}}
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Tooth No.</label>
                            <input type="text" x-model="form.tooth_number" placeholder="e.g. 26, 16-17"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>

                        {{-- Doctor --}}
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Doctor</label>
                            <select x-model="form.doctor_id"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="">— Select —</option>
                                @foreach($doctorsList as $doc)
                                <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                                @endforeach
                            </select>
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
                </div>

                {{-- ── SECTION 2: Treatment Status + Stages ── --}}
                <div x-show="form.treatment_name">
                    <div class="tv-section-legend">Treatment Progress</div>

                    {{-- Status --}}
                    <div class="flex gap-2 mb-3">
                        @foreach(['scheduled' => ['Scheduled','#2563eb','#dbeafe'], 'in_chair' => ['In Chair','#ea580c','#fff7ed'], 'completed' => ['Completed','#16a34a','#dcfce7']] as $val => [$lbl, $color, $bg])
                        <button type="button"
                                @click="form.status = '{{ $val }}'"
                                :class="form.status === '{{ $val }}' ? 'border-[{{ $color }}] text-[{{ $color }}] bg-[{{ $bg }}] font-bold' : 'border-gray-200 text-gray-500'"
                                class="flex-1 py-2 text-xs border rounded-lg transition-colors">
                            {{ $lbl }}
                        </button>
                        @endforeach
                    </div>

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
                              placeholder="Document what was done today, observations, patient response…"
                              class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
                </div>

                {{-- ── SECTION 5: Payment ── --}}
                <div>
                    <div class="tv-section-legend">Payment</div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Cost (₹)</label>
                            <input type="number" x-model="form.cost" min="0" step="1" placeholder="0"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Amount Paid (₹)</label>
                            <input type="number" x-model="form.amount_paid" min="0" step="1" placeholder="0"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Payment Mode</label>
                            <select x-model="form.payment_mode"
                                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                                <option value="pending">Pending</option>
                                <option value="cash">Cash</option><option value="upi">UPI</option>
                                <option value="card">Card</option><option value="bank_transfer">Bank Transfer</option>
                                <option value="insurance">Insurance</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold text-gray-600 block mb-1">Ref / Receipt</label>
                            <input type="text" x-model="form.payment_reference" placeholder="Optional"
                                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between px-4 py-2.5 rounded-lg"
                         :class="balanceDue > 0 ? 'bg-red-50 border border-red-100' : 'bg-green-50 border border-green-100'">
                        <span class="text-xs font-semibold" :class="balanceDue > 0 ? 'text-red-600' : 'text-green-700'">Balance Due</span>
                        <span class="text-sm font-bold" :class="balanceDue > 0 ? 'text-red-600' : 'text-green-700'">
                            ₹ <span x-text="fmt(balanceDue)"></span>
                        </span>
                    </div>
                </div>

                {{-- ── SECTION 6: Prescription (collapsible) ── --}}
                <div>
                    <button type="button" @click="rxOpen = !rxOpen"
                            class="w-full flex items-center justify-between px-4 py-3 bg-red-50 border border-red-100 rounded-lg text-sm font-semibold text-red-700 hover:bg-red-100 transition-colors">
                        <div class="flex items-center gap-2">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 2-5 5"/><path d="m2 19 5-5"/><rect x="5" y="2" width="5" height="20" rx="1" transform="rotate(-45 5 2)"/></svg>
                            Prescription
                            <span x-show="form.prescription_drugs.length > 0"
                                  class="bg-red-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                                  x-text="form.prescription_drugs.length + ' drug(s)'"></span>
                        </div>
                        <svg :class="rxOpen ? 'rotate-180' : ''" class="transition-transform" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                    </button>

                    <div x-show="rxOpen" x-collapse class="border border-t-0 border-gray-200 rounded-b-lg">
                        <div class="p-4 space-y-4">

                            {{-- Drug table --}}
                            <div>
                                <div class="grid gap-1 mb-2 text-[10px] font-bold text-red-600 uppercase tracking-wide"
                                     style="grid-template-columns:180px 36px 64px 64px 64px 80px 90px 60px 28px;">
                                    <span>Drug</span><span class="text-center">SOS</span>
                                    <span class="text-center">Morn</span><span class="text-center">Noon</span><span class="text-center">Night</span>
                                    <span>Duration</span><span>Unit</span><span class="text-center">Total</span><span></span>
                                </div>
                                <template x-for="(drug, i) in form.prescription_drugs" :key="i">
                                    <div class="mb-3">
                                        <div class="grid gap-1 items-center mb-1"
                                             style="grid-template-columns:180px 36px 64px 64px 64px 80px 90px 60px 28px;">
                                            <select x-model="drug.name" class="tv-rx-input"
                                                    @change="if($event.target.value==='__custom__'){var v=prompt('Drug name:');drug.name=v&&v.trim()?v.trim():''}">
                                                <option value="">Select Drug</option>
                                                @foreach(['Amoxicillin 500mg','Metronidazole 400mg','Ibuprofen 400mg','Ibuprofen 600mg','Paracetamol 500mg','Diclofenac 50mg','Clindamycin 300mg','Chlorhexidine Mouthwash','Tetracycline 250mg','Ciprofloxacin 500mg','Doxycycline 100mg','Cephalexin 500mg','Naproxen 250mg','Pantoprazole 40mg','Multivitamin'] as $drug)
                                                <option>{{ $drug }}</option>
                                                @endforeach
                                                <option value="__custom__">+ Custom…</option>
                                            </select>
                                            <div class="flex flex-col items-center gap-0.5 cursor-pointer" @click="drug.sos=!drug.sos">
                                                <div class="w-4 h-4 rounded-full border-2 flex items-center justify-center transition-all"
                                                     :style="drug.sos?'border-color:#6a0f70;background:#6a0f70':'border-color:#d1d5db'">
                                                    <div x-show="drug.sos" class="w-1.5 h-1.5 rounded-full bg-white"></div>
                                                </div>
                                                <span class="text-[8px] font-bold text-gray-400">SOS</span>
                                            </div>
                                            <input type="number" x-model="drug.morning" class="tv-rx-input" placeholder="0" min="0" step="0.5" style="text-align:center;" @input="rxCalcTotal(i)">
                                            <input type="number" x-model="drug.noon" class="tv-rx-input" placeholder="0" min="0" step="0.5" style="text-align:center;" @input="rxCalcTotal(i)">
                                            <input type="number" x-model="drug.night" class="tv-rx-input" placeholder="0" min="0" step="0.5" style="text-align:center;" @input="rxCalcTotal(i)">
                                            <input type="number" x-model="drug.duration" class="tv-rx-input" placeholder="Days" min="1" @input="rxCalcTotal(i)">
                                            <select x-model="drug.dur_unit" class="tv-rx-input" @change="rxCalcTotal(i)">
                                                <option value="days">Days</option>
                                                <option value="weeks">Weeks</option>
                                            </select>
                                            <input type="number" x-model="drug.total_qty" class="tv-rx-input" placeholder="Qty" style="text-align:center;">
                                            <button type="button" @click="form.prescription_drugs.splice(i,1)"
                                                    class="w-7 h-7 border border-red-200 rounded bg-white text-red-400 flex items-center justify-center hover:bg-red-50">
                                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
                                            </button>
                                        </div>
                                        <div class="grid gap-1" style="grid-template-columns:140px 100px 1fr;">
                                            <select x-model="drug.food" class="tv-rx-input">
                                                <option value="">Food timing</option>
                                                <option>Before Food</option><option>After Food</option>
                                                <option>With Food</option><option>Empty Stomach</option><option>Bedtime</option>
                                            </select>
                                            <select x-model="drug.language" class="tv-rx-input">
                                                <option>English</option><option>Hindi</option><option>Marathi</option>
                                            </select>
                                            <input type="text" x-model="drug.instruction" class="tv-rx-input" placeholder="Instruction / notes">
                                        </div>
                                    </div>
                                </template>
                                <button type="button" @click="rxAddDrug()"
                                        class="flex items-center gap-1.5 px-3 py-2 border border-dashed border-gray-300 rounded-lg text-xs font-semibold text-gray-500 hover:border-[#6a0f70] hover:text-[#6a0f70] w-full transition-colors mt-1">
                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                    Add Drug
                                </button>
                            </div>

                            {{-- Instructions --}}
                            <div>
                                <p class="text-xs font-semibold text-gray-600 mb-2">Patient Instructions</p>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach(['Avoid hard/crunchy food for 24 hrs','Do not rinse vigorously','Keep the area clean','Use warm saline rinse','Apply ice pack for swelling','Avoid alcohol & smoking','Complete the full course of antibiotics','Return if bleeding does not stop'] as $instr)
                                    <button type="button" @click="rxToggleInstr('{{ $instr }}')"
                                            :class="form.prescription_instructions.includes('{{ $instr }}') ? 'tv-rx-pill on' : 'tv-rx-pill'">
                                        <svg x-show="form.prescription_instructions.includes('{{ $instr }}')" width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                                        {{ $instr }}
                                    </button>
                                    @endforeach
                                </div>
                                <textarea x-model="form.prescription_custom_notes" rows="2" placeholder="Additional instructions…"
                                          class="w-full text-xs border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70] mt-2 resize-none"></textarea>
                            </div>

                        </div>
                    </div>
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
            </div>

        </div>{{-- end grid --}}
        </div>{{-- end body --}}

        {{-- Footer --}}
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-200 bg-gray-50">
            <button @click="closeForm()" class="px-4 py-2 text-sm text-gray-500 hover:text-gray-700 font-medium">
                Cancel
            </button>
            <button @click="saveVisit()" :disabled="saving"
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

</div>

@push('scripts')
<script>
const TV_STAGES = {{ Js::from($stagesJson) }};

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
            { key: 'due',       label: 'Balance Due' },
        ],

        formOpen: false,
        saving: false,
        errorMsg: '',
        editingVisit: null,
        rxOpen: false,
        form: {},

        init() { this.form = this._blank(); },

        get filteredVisits() {
            let out = this.visits;
            if (['scheduled','in_chair','completed','cancelled','no_show'].includes(this.activeFilter)) out = out.filter(v => v.status === this.activeFilter);
            else if (this.activeFilter === 'due')        out = out.filter(v => v.balance_due > 0);
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
            return out;
        },

        get currentStages() {
            return this.stages[this.form.treatment_name] || {};
        },

        get balanceDue() {
            return Math.max(0, (parseFloat(this.form.cost)||0) - (parseFloat(this.form.amount_paid)||0));
        },

        _blank() {
            return {
                visit_date: new Date().toISOString().slice(0,10),
                visit_type: 'treatment',
                status: 'scheduled',
                doctor_id: '',
                treatment_name: '',
                current_stage: '',
                completed_stages: [],
                tooth_number: '',
                notes: '',
                chief_complaint: '',
                cost: '', amount_paid: '',
                payment_mode: 'cash', payment_reference: '',
                next_visit_date: '', next_visit_type: '',
                rct_num_canals: '', rct_canal_lengths: [], rct_file_type: '', rct_irrigant: '', rct_obturation_method: '',
                impl_brand: '', impl_size: '', impl_torque: '', impl_graft_used: '', impl_graft_brand: '', impl_membrane: '', impl_healing_collar: '',
                fill_material: '', fill_shade: '',
                scale_quadrants: '', scale_method: '',
                ext_type: '', ext_socket: '', ext_suture: false,
                crown_type: '', crown_shade: '', crown_impression: false, crown_temp_placed: '',
                prescription_drugs: [], prescription_instructions: [], prescription_custom_notes: '',
            };
        },

        onTreatmentChange() {
            this.form.completed_stages = [];
            this.form.current_stage = '';
            if (this.form.treatment_name === 'RCT') this.form.rct_canal_lengths = [];
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

        openAddForm() {
            this.editingVisit = null;
            this.form = this._blank();
            this.errorMsg = '';
            this.rxOpen = false;
            this.formOpen = true;
            this.$nextTick(() => {
                document.getElementById('tv-inline-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        },

        openEditForm(visit) {
            this.editingVisit = visit;
            this.form = {
                visit_date:       visit.visit_date || '',
                visit_type:       visit.visit_type || 'treatment',
                status:           visit.status || 'scheduled',
                doctor_id:        visit.doctor_id || '',
                treatment_name:   visit.treatment_name || '',
                current_stage:    visit.current_stage || '',
                completed_stages: [...(visit.completed_stages||[])],
                tooth_number:     visit.tooth_number || '',
                notes:            visit.notes || '',
                chief_complaint:  visit.chief_complaint || '',
                cost:             visit.cost || '',
                amount_paid:      visit.amount_paid || '',
                payment_mode:     visit.payment_mode || 'pending',
                payment_reference:visit.payment_reference || '',
                next_visit_date:  visit.next_visit_date || '',
                next_visit_type:  visit.next_visit_type || '',
                rct_num_canals:       visit.rct_num_canals || '',
                rct_canal_lengths:    [...(visit.rct_canal_lengths||[])],
                rct_file_type:        visit.rct_file_type || '',
                rct_irrigant:         visit.rct_irrigant || '',
                rct_obturation_method:visit.rct_obturation_method || '',
                impl_brand:           visit.impl_brand || '',
                impl_size:            visit.impl_size || '',
                impl_torque:          visit.impl_torque || '',
                impl_graft_used:      visit.impl_graft_used || '',
                impl_graft_brand:     visit.impl_graft_brand || '',
                impl_membrane:        visit.impl_membrane || '',
                impl_healing_collar:  visit.impl_healing_collar || '',
                fill_material:        visit.fill_material || '',
                fill_shade:           visit.fill_shade || '',
                scale_quadrants:      visit.scale_quadrants || '',
                scale_method:         visit.scale_method || '',
                ext_type:             visit.ext_type || '',
                ext_socket:           visit.ext_socket || '',
                ext_suture:           visit.ext_suture || false,
                crown_type:           visit.crown_type || '',
                crown_shade:          visit.crown_shade || '',
                crown_impression:     visit.crown_impression || false,
                crown_temp_placed:    visit.crown_temp_placed || '',
                prescription_drugs:        [...(visit.prescription_drugs||[])],
                prescription_instructions: [...(visit.prescription_instructions||[])],
                prescription_custom_notes: visit.prescription_custom_notes || '',
            };
            this.errorMsg = '';
            this.rxOpen = (this.form.prescription_drugs.length > 0);
            this.formOpen = true;
            this.$nextTick(() => {
                document.getElementById('tv-inline-form')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        },

        closeForm() {
            this.formOpen = false;
            this.editingVisit = null;
            this.errorMsg = '';
        },

        async saveVisit() {
            if (!this.form.visit_date) { this.errorMsg = 'Visit date is required.'; return; }
            this.saving = true; this.errorMsg = '';
            try {
                const isEdit = !!this.editingVisit;
                const url    = isEdit ? `/visits/${this.editingVisit.id}` : `/patients/{{ $patient->id }}/visits`;
                const resp   = await fetch(url, {
                    method: isEdit ? 'PUT' : 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) throw new Error(data.message || 'Save failed.');

                if (isEdit) {
                    const idx = this.visits.findIndex(v => v.id === this.editingVisit.id);
                    if (idx > -1) this.visits[idx] = { ...data.visit, _isNew: false };
                } else {
                    const nv = { ...data.visit, _isNew: true };
                    this.visits.unshift(nv);
                    setTimeout(() => { const v = this.visits.find(x=>x.id===nv.id); if(v) v._isNew=false; }, 2000);
                }
                this.closeForm();
            } catch(e) {
                this.errorMsg = e.message || 'An error occurred.';
            } finally {
                this.saving = false;
            }
        },

        async deleteVisit(visit) {
            if (!confirm('Delete visit on ' + this.fmtFull(visit.visit_date) + '? This cannot be undone.')) return;
            try {
                const resp = await fetch(`/visits/${visit.id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                });
                const data = await resp.json();
                if (!resp.ok || !data.success) throw new Error(data.message || 'Delete failed.');
                this.visits = this.visits.filter(v => v.id !== visit.id);
            } catch(e) { alert(e.message); }
        },

        fmt(n)      { return Number(n||0).toLocaleString('en-IN'); },
        fmtDay(d)   { return d ? new Date(d+'T00:00:00').getDate() : ''; },
        fmtMonth(d) { return d ? new Date(d+'T00:00:00').toLocaleString('en-IN',{month:'short'}).toUpperCase() : ''; },
  
@endpush
