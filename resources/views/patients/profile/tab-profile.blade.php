{{-- ══════════════════════════════════════════════════════════
     PROFILE TAB  (was "Consultation" tab — patient details)
══════════════════════════════════════════════════════════ --}}
{{-- ══════════════════════════════════════════════════════════
     PROFILE TAB — 50/50 layout: Patient Details left | Visit Log right
══════════════════════════════════════════════════════════ --}}
<div x-show="activeTab === 'profile'" class="w-full px-6 py-5">
    <div class="grid grid-cols-1 xl:grid-cols-2 gap-5 items-start">

        {{-- ══ LEFT COLUMN ══ --}}
        <div class="space-y-4">

            {{-- Family & Contacts (Phase 3, Slice 3) --}}
            @include('patients.partials.family-contacts')

            {{-- Patient Details & Rapport --}}
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <span class="section-title">Patient Details & Rapport</span>
                </div>
                <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100">

                    {{-- Personal Details --}}
                    <div class="p-5">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-gray-500">Personal Details</span>
                            <button x-on:click="window.dispatchEvent(new CustomEvent('open-edit-patient', { detail: window.__editPatientPrefill }))" class="text-xs text-[#6a0f70] hover:underline font-medium">Edit</button>
                        </div>
                        <div class="space-y-2.5">
                            @php
                                $rows = [
                                    'Date of Birth'     => $patient->dob_unknown
                                        ? ($patient->age_years ? $patient->age_years.' yrs (approx)' : null)
                                        : ($patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->format('d M Y') : null),
                                    'Occupation'        => $patient->occupation,
                                    'Area'              => $patient->area,
                                    'Address'           => collect([$patient->address,$patient->city,$patient->state])->filter()->implode(', ') ?: null,
                                    'Alt. Phone'        => $patient->alternate_phone,
                                    'Medical Conditions'=> $patient->medical_conditions ? implode(', ', $patient->medical_conditions) : null,
                                    'Medications'       => $patient->current_medications,
                                    'Dental Conditions' => $patient->dental_conditions ? implode(', ', $patient->dental_conditions) : null,
                                    'Medical Alerts'    => $patient->medical_alert ?: 'No Known Allergies',
                                    'Habits'            => $patient->habits ? (is_array($patient->habits) ? implode(', ', (array)$patient->habits) : $patient->habits) : null,
                                    'Chief Complaint'   => $patient->chief_complaint,
                                    'Source'            => $patient->source ? $patient->source.($patient->source_referral_name ? ' — '.$patient->source_referral_name : ($patient->source_campaign ? ' — '.$patient->source_campaign : '')) : null,
                                    'Referred By'       => (function() use ($patient) {
                                        if ($patient->referral_type === 'existing_patient' && $patient->referredPatient) {
                                            $rp = $patient->referredPatient;
                                            return 'Patient: '.$rp->name.' ('.$rp->patient_id.')';
                                        }
                                        if ($patient->referral_type === 'other' && $patient->referrer_name) {
                                            $parts = [$patient->referrer_name];
                                            if ($patient->referrer_type)   $parts[] = $patient->referrer_type;
                                            if ($patient->referrer_mobile) $parts[] = $patient->referrer_mobile;
                                            return implode(' · ', $parts);
                                        }
                                        return $patient->referred_by ?: null;
                                    })(),
                                ];
                            @endphp
                            @foreach($rows as $lbl => $val)
                            @if($val)
                            <div class="flex gap-3">
                                <span class="text-xs text-gray-400 w-28 flex-shrink-0 pt-0.5">{{ $lbl }}</span>
                                <span class="text-sm leading-snug
                                    {{ $lbl === 'Medical Alerts' && $patient->medical_alert ? 'text-amber-600 font-medium' : '' }}
                                    {{ $lbl === 'Medical Alerts' && !$patient->medical_alert ? 'text-green-600 font-medium' : '' }}
                                    {{ !in_array($lbl,['Medical Alerts']) ? 'text-gray-700' : '' }}">
                                    {{ $val }}
                                </span>
                            </div>
                            @endif
                            @endforeach
                        </div>
                    </div>

                    {{-- Rapport Notes --}}
                    <div class="p-5">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold text-gray-500">Rapport Building Points</span>
                            <button x-on:click="showNoteForm = !showNoteForm"
                                    class="text-xs text-[#6a0f70] hover:underline font-medium flex items-center gap-1">
                                <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                                Add
                            </button>
                        </div>
                        <div x-show="showNoteForm" x-collapse class="mb-3">
                            <textarea x-model="newNote" rows="2" placeholder="e.g. Fearful of injections…"
                                class="w-full text-sm border border-gray-200 px-3 py-2 resize-none rounded focus:outline-none focus:border-[#6a0f70] mb-2"></textarea>
                            <div class="flex flex-wrap gap-1.5 mb-2">
                                @foreach(['nervous','price-sensitive','vip','morning only','evening pref','needs explanation','family patient','referred by'] as $t)
                                <button type="button" x-on:click="toggleNoteTag('{{ $t }}')"
                                    :class="newNoteTags.includes('{{ $t }}') ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'text-gray-400 border-gray-200 hover:border-[#6a0f70]'"
                                    class="px-2 py-0.5 text-xs border rounded-full transition-colors">{{ $t }}</button>
                                @endforeach
                            </div>
                            <div class="flex gap-2 items-center">
                                <button x-on:click="saveNote()" :disabled="noteSaving" class="px-3 py-1.5 text-xs bg-[#380740] text-white hover:bg-[#6a0f70] rounded disabled:opacity-50" x-text="noteSaving ? 'Saving…' : 'Save'"></button>
                                <button x-on:click="showNoteForm=false;newNote='';newNoteTags=[];noteSaveError=''" class="px-3 py-1.5 text-xs border border-gray-200 text-gray-500 rounded">Cancel</button>
                                <span x-show="noteSaveError" x-text="noteSaveError" class="text-xs text-red-500"></span>
                            </div>
                        </div>
                        <template x-if="relationshipNotes.length === 0 && !showNoteForm">
                            <p class="text-sm text-gray-400 py-2">No rapport notes yet.</p>
                        </template>
                        <template x-for="(note, i) in relationshipNotes" :key="note.id">
                            <div class="rapport-item group">
                                <span class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                </span>
                                <span class="text-sm text-gray-700 flex-1 leading-relaxed" x-text="note.note"></span>
                                <button x-on:click="deleteNote(note.id)"
                                        class="opacity-0 group-hover:opacity-100 ml-1 text-gray-300 hover:text-red-400 flex-shrink-0 transition-opacity">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                </button>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Patient Tags --}}
                <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/60">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#5b21b6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/><path d="M7 7h.01"/></svg>
                            <span class="section-title">Patient Tags</span>
                        </div>
                        <button x-on:click="showOppForm = true"
                                class="text-xs text-[#6a0f70] border border-[#6a0f70]/30 px-2.5 py-1 hover:bg-[#f5eef9] transition-colors font-medium rounded-sm flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                            Add Tag
                        </button>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <template x-for="(opp, i) in opportunities" :key="'tag-'+opp.id">
                            <span class="text-xs px-3 py-1 rounded-full font-semibold border flex-shrink-0"
                            :style="'background:'+(oppTypeColors[opp.type]?.bg||'#f3f4f6')+';color:'+(oppTypeColors[opp.type]?.color||'#6b7280')+';border-color:'+(oppTypeColors[opp.type]?.color||'#6b7280')+'40'"
                             x-text="(opp.label || opp.type.replace(/_/g,' ')).replace(/\b\w/g,c=>c.toUpperCase())+' Prospect'">
                             </span>
                        </template>
                        @if($patient->lifetime_value > 100000)
                            <span class="tag-pill" style="background:#fef3c7;color:#92400e;border-color:#fcd34d;">High Value</span>
                        @endif
                        @if($patient->referred_by || $patient->referral_type || $patient->source === 'Referral')
                            <span class="tag-pill" style="background:#ede9fe;color:#5b21b6;border-color:#c4b5fd;">Referral Patient</span>
                        @endif
                        <template x-for="note in relationshipNotes" :key="'tn-'+note.id">
                            <template x-for="tag in (note.tags||[])" :key="tag">
                                <span class="tag-pill" style="background:#f0fdf4;color:#166534;border-color:#bbf7d0;"
                                      x-text="tag.replace(/\b\w/g,c=>c.toUpperCase())"></span>
                            </template>
                        </template>
                        <template x-if="opportunities.length === 0 && relationshipNotes.length === 0">
                            <span class="text-xs text-gray-400 italic">No tags yet.</span>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Treatment Opportunities --}}
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <span class="section-title">Treatment Opportunities / Potential Tags</span>
                    <button x-on:click="showOppForm = !showOppForm"
                            dusk="opp-add"
                            class="text-xs text-[#6a0f70] border border-[#6a0f70]/30 px-3 py-1.5 hover:bg-[#f5eef9] transition-colors font-medium">
                        + Add Opportunity
                    </button>
                </div>

                {{-- Add form --}}
                <div x-show="showOppForm" x-collapse class="border-b border-gray-100 bg-gray-50">
                    <div class="px-5 py-4 grid grid-cols-2 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Treatment Type</label>
                            <select x-model="newOpp.type" dusk="opp-type" class="w-full text-sm border border-gray-200 px-3 py-2 bg-white rounded focus:outline-none focus:border-[#6a0f70]">
                                <option value="">Select…</option>
                                @foreach(['implant','aligner','veneers','full_mouth_rehab','whitening','crown','bridge','rct','smile_design','gum_treatment'] as $t)
                                    <option value="{{ $t }}">{{ ucwords(str_replace('_',' ',$t)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Stage</label>
                            <select x-model="newOpp.status" class="w-full text-sm border border-gray-200 px-3 py-2 bg-white rounded focus:outline-none focus:border-[#6a0f70]">
                                <option value="prospect">Identified</option>
                                <option value="discussed">Discussed</option>
                                <option value="quoted">Financial Discussion</option>
                                <option value="accepted">Planned</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Priority</label>
                            <select x-model="newOpp.priority" class="w-full text-sm border border-gray-200 px-3 py-2 bg-white rounded focus:outline-none focus:border-[#6a0f70]">
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Est. Value (Rs. )</label>
                            <input type="number" x-model="newOpp.estimated_value" placeholder="0"
                                   class="w-full text-sm border border-gray-200 px-3 py-2 rounded focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Next Follow-up</label>
                            <input type="date" x-model="newOpp.follow_up_date"
                                   class="w-full text-sm border border-gray-200 px-3 py-2 rounded focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div class="flex items-end gap-2">
                            <button x-on:click="saveOpportunity()" dusk="opp-save"
                                    :disabled="oppSaving"
                                    :class="oppSaving ? 'opacity-60 cursor-not-allowed' : 'hover:bg-[#6a0f70]'"
                                    class="flex-1 py-2 text-xs bg-[#380740] text-white rounded"
                                    x-text="oppSaving ? 'Saving…' : 'Save'"></button>
                            <button x-on:click="showOppForm=false; newOpp={type:'',status:'prospect',priority:'medium',estimated_value:'',follow_up_date:''}"
                                    :disabled="oppSaving"
                                    class="flex-1 py-2 text-xs border border-gray-200 text-gray-500 rounded hover:bg-gray-50">Cancel</button>
                        </div>
                    </div>
                </div>

                {{-- Opportunity rows --}}
                <div class="px-4 pt-3 pb-2 space-y-2">
                    <template x-if="opportunities.length === 0">
                        <p class="text-sm text-gray-400 text-center py-3">No treatment opportunities tracked yet.</p>
                    </template>
                    <template x-for="(opp, idx) in opportunities" :key="opp.id">
                        <div class="border border-gray-200 rounded-lg transition-all group"
                             :class="oppEditId === opp.id ? 'border-[#6a0f70]/40 bg-[#faf5ff]' : 'hover:border-[#6a0f70]/40 hover:bg-[#faf5ff]'">

                            {{-- Display row --}}
                            <div x-show="oppEditId !== opp.id"
                                 class="flex items-center gap-3 p-3 cursor-pointer"
                                 x-on:click="openOppEdit(opp)">
                                <div class="w-10 h-10 rounded-lg flex items-center justify-center flex-shrink-0"
                                     :style="'background:'+(oppTypeColors[opp.type]?.bg||'#f3f4f6')">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none"
                                         :stroke="oppTypeColors[opp.type]?.color||'#6b7280'"
                                         stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="font-semibold text-sm text-gray-800 capitalize leading-tight"
                                         x-text="(opp.label||opp.type||'').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())"></div>
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        Stage: <span class="text-gray-600" x-text="oppStageLabel(opp.status)"></span>
                                        <template x-if="opp.follow_up_date">
                                            <span> · Next: <span x-text="opp.follow_up_date"></span></span>
                                        </template>
                                    </div>
                                </div>
                                <template x-if="opp.estimated_value">
                                    <div class="text-sm font-bold text-gray-700 flex-shrink-0"
                                         x-text="'Rs.  '+Number(opp.estimated_value).toLocaleString('en-IN')"></div>
                                </template>
                                <span class="text-xs px-3 py-1 rounded-full font-semibold flex-shrink-0 border"
                                      :class="{
                                        'bg-red-50 text-red-500 border-red-200':      opp.priority==='high',
                                        'bg-amber-50 text-amber-500 border-amber-200': opp.priority==='medium',
                                        'bg-gray-50 text-gray-400 border-gray-200':    opp.priority==='low',
                                      }"
                                      x-text="opp.priority.charAt(0).toUpperCase()+opp.priority.slice(1)"></span>
                                <button x-on:click.stop="deleteOpportunity(opp.id)"
                                        class="opacity-0 group-hover:opacity-100 text-gray-300 hover:text-red-400 transition-opacity flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
                                </button>
                            </div>

                            {{-- Inline edit form — shown only for the active opp, no auto-save on field change --}}
                            <div x-show="oppEditId === opp.id" class="p-3 space-y-2">
                                <div class="text-xs font-semibold text-[#6a0f70] mb-1 capitalize"
                                     x-text="(opp.label||opp.type||'').replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())"></div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div>
                                        <label class="block text-[10px] text-gray-400 mb-0.5">Stage</label>
                                        <select x-model="oppEditData.status"
                                                class="w-full text-xs border border-gray-200 px-2 py-1.5 bg-white rounded focus:outline-none focus:border-[#6a0f70]">
                                            <option value="prospect">Identified</option>
                                            <option value="discussed">Discussed</option>
                                            <option value="quoted">Financial Discussion</option>
                                            <option value="accepted">Planned</option>
                                            <option value="completed">Completed</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-gray-400 mb-0.5">Priority</label>
                                        <select x-model="oppEditData.priority"
                                                class="w-full text-xs border border-gray-200 px-2 py-1.5 bg-white rounded focus:outline-none focus:border-[#6a0f70]">
                                            <option value="high">High</option>
                                            <option value="medium">Medium</option>
                                            <option value="low">Low</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-gray-400 mb-0.5">Est. Value (Rs. )</label>
                                        <input type="number" x-model="oppEditData.estimated_value" placeholder="0"
                                               class="w-full text-xs border border-gray-200 px-2 py-1.5 rounded focus:outline-none focus:border-[#6a0f70]">
                                    </div>
                                    <div>
                                        <label class="block text-[10px] text-gray-400 mb-0.5">Follow-up Date</label>
                                        <input type="date" x-model="oppEditData.follow_up_date"
                                               class="w-full text-xs border border-gray-200 px-2 py-1.5 rounded focus:outline-none focus:border-[#6a0f70]">
                                    </div>
                                </div>
                                <div class="flex gap-2 pt-1">
                                    <button x-on:click.stop="saveOppEdit(opp)"
                                            :disabled="oppSaving"
                                            :class="oppSaving ? 'opacity-60 cursor-not-allowed' : 'hover:bg-[#6a0f70]'"
                                            class="px-3 py-1 text-xs bg-[#380740] text-white rounded"
                                            x-text="oppSaving ? 'Saving…' : 'Save'"></button>
                                    <button x-on:click.stop="cancelOppEdit()"
                                            :disabled="oppSaving"
                                            class="px-3 py-1 text-xs border border-gray-200 text-gray-500 rounded hover:bg-gray-50">Cancel</button>
                                </div>
                            </div>

                        </div>
                    </template>
                </div>

                {{-- Stage legend --}}
                <div class="px-4 pb-4 flex flex-wrap gap-x-4 gap-y-1">
                    @foreach(['Identified'=>'#94a3b8','Discussed'=>'#3b82f6','Financial Discussion'=>'#f59e0b','Planned'=>'#8b5cf6','Started'=>'#10b981','Completed'=>'#059669'] as $lbl => $col)
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full" style="background:{{ $col }}"></span>
                        <span class="text-[10px] text-gray-400">{{ $lbl }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>
        {{-- /left column --}}


        {{-- ══ RIGHT COLUMN ══ --}}
        <div class="space-y-4">

            {{-- Journey Timeline (Phase 4, Slice 2) — unified event feed via
                 PatientJourneyService; replaces the old two-source Visit Log. --}}
            @include('patients.profile.journey-timeline')

            {{-- Quick Actions --}}
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <span class="section-title">Quick Actions</span>
                </div>
                <div class="p-3 space-y-1.5">
                    @php
                    // Quick actions — text-only links, no icons, only working actions
                    $quickActions = [
                        // Clinical
                        ['Add Consultation',    'url',    route('patients.consultations.create', $patient)],
                        ['Add Follow-up',       'js',     "openVisitForm()"],
                        ['Add Treatment Visit', 'js',     "openVisitForm()"],
                        // Documents & Scheduling
                        ['Upload X-ray / Scan', 'tab',    'documents'],
                        ['Treatment Plan',      'tab',    'treatment-plan'],
                        ['Book Appointment',    'alpine', "openAppointmentModal('appointment', null, {{ $patient->id }})"],
                        // Prescriptions & Billing
                        ['Write Prescription',  'url',    route('patients.prescriptions.create', $patient)],
                        ['Billing',             'tab',    'billing'],
                        ['Wallet',              'tab',    'wallet'],
                        ['Membership',          'js',     "openMembershipEnroll()"],
                    ];
                    @endphp

                    @foreach($quickActions as [$label, $actionType, $actionValue])
                    @php
                        $clickHandler = match($actionType) {
                            'tab'    => "activeTab='{$actionValue}'",
                            'url'    => "window.location.href='{$actionValue}'",
                            'alpine' => $actionValue,
                            'js'     => $actionValue,
                            default  => '',
                        };
                    @endphp
                    <button
                        class="w-full text-left px-3 py-2 text-sm text-gray-700 border border-gray-200 rounded hover:border-[#6a0f70] hover:text-[#6a0f70] hover:bg-[#faf5ff] transition-colors font-medium"
                        x-on:click="{{ $clickHandler }}">
                        {{ $label }}
                    </button>
                    @endforeach

                    {{-- Send review request — real form POST (not an Alpine click
                         handler, unlike the actions above) so it reuses the same
                         Communication\ReviewController@send + global flash-banner
                         system the Marketing/Communication Reviews pages use.
                         back() in that controller returns here via the referer. --}}
                    <form method="POST" action="{{ route('communication.reviews.send') }}">
                        @csrf
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        <button type="submit"
                            class="w-full text-left px-3 py-2 text-sm text-gray-700 border border-gray-200 rounded hover:border-[#6a0f70] hover:text-[#6a0f70] hover:bg-[#faf5ff] transition-colors font-medium">
                            Send review request
                        </button>
                    </form>
                </div>
            </div>

        </div>
        {{-- /right column --}}

    </div>
</div>
{{-- /profile tab --}}
