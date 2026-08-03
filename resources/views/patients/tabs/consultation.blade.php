<div x-show="activeTab === 'consultation'" style="display:none" class="w-full px-6 py-5">
    <div class="grid grid-cols-1 xl:grid-cols-[1fr_360px] gap-5">

        {{-- LEFT: consultation list / new form --}}
        <div class="space-y-4">

            {{-- Header row --}}
            <div class="flex items-center justify-between flex-wrap gap-3">
                <div>
                    <h2 class="text-base font-bold text-gray-800">Consultations</h2>
                    <p class="text-xs text-gray-400 mt-0.5">All consultation records for {{ $patient->name }}</p>
                </div>
                {{-- ── Consultation type selector ── --}}
                <div class="flex items-center gap-2 flex-wrap">
                    {{-- New Visit (2026-07-31 Visit redesign) — Visit Type picker per directive:
                         Consultation / Minor Visit / COHA. Purely additive: the existing
                         New Consultation/Same Issue/Minor Visit/Emergency/COHA links below
                         are untouched, still work exactly as before. This is a second,
                         optional entry point that groups the 3 target visit types. --}}
                    <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                        <button type="button" @click="open = !open"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-gray-800 text-white hover:bg-gray-900 transition-colors font-semibold rounded">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                            + New Visit
                            <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" :style="open ? 'transform:rotate(180deg)' : ''" style="transition:transform .15s;"><path d="m6 9 6 6 6-6"/></svg>
                        </button>
                        <div x-show="open" x-cloak x-transition
                             class="absolute z-20 mt-1 w-56 bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
                            <a href="{{ route('patients.consultations.create', $patient) }}?type=new"
                               class="block px-3 py-2.5 text-xs font-semibold text-gray-700 hover:bg-purple-50 hover:text-[#6a0f70] border-b border-gray-100">
                                Consultation
                                <div class="text-[10px] text-gray-400 font-normal mt-0.5">Chief complaint → exam → diagnosis → prescription</div>
                            </a>
                            <a href="{{ route('patients.consultations.minor-visit.create', $patient) }}"
                               class="block px-3 py-2.5 text-xs font-semibold text-gray-700 hover:bg-cyan-50 hover:text-cyan-800 border-b border-gray-100">
                                Minor Visit
                                <div class="text-[10px] text-gray-400 font-normal mt-0.5">Dressing, suture removal, adjustment, review</div>
                            </a>
                            <a href="{{ route('coha.create', $patient) }}"
                               class="block px-3 py-2.5 text-xs font-semibold text-gray-700 hover:bg-cyan-50 hover:text-cyan-800">
                                Complete Oral Health Assessment
                                <div class="text-[10px] text-gray-400 font-normal mt-0.5">Full assessment across all systems</div>
                            </a>
                        </div>
                    </div>
                    {{-- New Consultation (existing, unchanged) --}}
                    <a href="{{ route('patients.consultations.create', $patient) }}?type=new"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-[#6a0f70] text-white hover:bg-[#380740] transition-colors font-semibold rounded">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        New Consultation
                    </a>
                    {{-- Same Issue --}}
                    <a href="{{ route('patients.consultations.same-issue.create', $patient) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-amber-50 text-amber-800 border border-amber-300 hover:bg-amber-100 transition-colors font-semibold rounded">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,12 2,6"/></svg>
                        Same Issue
                    </a>
                    {{-- Minor Visit --}}
                    <a href="{{ route('patients.consultations.minor-visit.create', $patient) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-cyan-50 text-cyan-800 border border-cyan-300 hover:bg-cyan-100 transition-colors font-semibold rounded">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                        Minor Visit
                    </a>
                    {{-- Emergency --}}
                    <a href="{{ route('patients.consultations.emergency.create', $patient) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-red-50 text-red-700 border border-red-300 hover:bg-red-100 transition-colors font-semibold rounded">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Emergency
                    </a>
                    {{-- COHA --}}
                    <a href="{{ route('coha.create', $patient) }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs bg-cyan-700 text-white hover:bg-cyan-800 transition-colors font-semibold rounded">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        COHA
                    </a>
                </div>
            </div>

            {{-- Past consultations list --}}
           @php
    $consultationRecords = $consultations ?? collect();
@endphp

            @if($consultationRecords->isEmpty())
            <div class="bg-white border border-gray-200 rounded-lg py-16 text-center">
                <div class="w-14 h-14 rounded-full bg-purple-50 flex items-center justify-center mx-auto mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none"
                         stroke="#7c3aed" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                </div>
                <p class="text-sm font-semibold text-gray-700 mb-1">No consultations yet</p>
                <p class="text-xs text-gray-400 mb-4">Start the patient's clinical record by adding the first consultation.</p>
                <a href="{{ route('patients.consultations.create', $patient) }}?type=new"
                   class="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-[#6a0f70] text-white hover:bg-[#380740] rounded transition-colors font-medium">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    Add First Consultation
                </a>
            </div>
            @else
            <div class="space-y-3">
                @foreach($consultationRecords as $consult)
                @php
                    $cType = $consult->consultation_type ?? 'new';
                    $isCoha = $cType === 'coha';
                    $isEmergency  = $cType === 'emergency';
                    $isMinorVisit = $cType === 'minor_visit';
                    $isSameIssue  = $cType === 'same_issue';
                    // Avatar and accent colour per type
                    $avatarBg  = $isCoha ? 'bg-cyan-100'   : ($isEmergency ? 'bg-red-100'    : ($isMinorVisit ? 'bg-teal-100' : ($isSameIssue ? 'bg-amber-100' : 'bg-purple-100')));
                    $iconColor = $isCoha ? '#0e7490'        : ($isEmergency ? '#b91c1c'       : ($isMinorVisit ? '#0e7490'    : ($isSameIssue ? '#92400e'     : '#7c3aed')));
                    $typeLabel = $consult->typeLabel();
                    $badgeClass= $isCoha      ? 'bg-cyan-100 text-cyan-700'
                               : ($isEmergency   ? 'bg-red-100 text-red-700'
                               : ($isMinorVisit  ? 'bg-teal-100 text-teal-700'
                               : ($isSameIssue   ? 'bg-amber-100 text-amber-700'
                               : 'bg-purple-100 text-purple-700')));
                @endphp
                <div class="bg-white border border-gray-200 rounded-lg p-5 hover:border-[#6a0f70]/30 transition-colors">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full {{ $avatarBg }} flex items-center justify-center flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="{{ $iconColor }}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                </svg>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <div class="font-semibold text-gray-800 text-sm">{{ $typeLabel }}</div>
                                    <span class="text-[9px] font-bold px-1.5 py-0.5 rounded {{ $badgeClass }} uppercase tracking-wide">
                                        {{ strtoupper(str_replace(['_', ' '], [' ', ' '], $cType)) }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-400">
                                    {{ $consult->consultation_date?->format('d M Y') ?? $consult->created_at->format('d M Y') }}
                                    @if($consult->doctor?->name)
                                        &middot; {{ $consult->doctor->doctor_name }}
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-0.5 text-[10px] rounded-full font-medium
                                {{ $consult->status === 'completed' ? 'bg-green-50 text-green-700' : 'bg-purple-50 text-purple-700' }}">
                                {{ ucfirst($consult->status ?? 'Draft') }}
                            </span>
                            {{-- View button --}}
                            <a href="{{ route('consultations.show', $consult) }}"
                               title="View full record"
                               class="p-1.5 rounded text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 transition-colors">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            {{-- Print button --}}
                            <a href="{{ $isCoha ? route('coha.report', [$patient, $consult]) : route('consultations.print', $consult) }}"
                               target="_blank"
                               title="Print record"
                               class="p-1.5 rounded text-gray-400 hover:text-emerald-600 hover:bg-emerald-50 transition-colors">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                            </a>
                            {{-- Edit button (COHA and Minor Visit get their own edit routes;
                                 Slice 1 fix 2026-08-01: Minor Visit used to fall through to
                                 the generic edit form here too — same production blocker as
                                 the show-page Edit buttons. Others use standard edit. --}}
                            <a href="{{ $isCoha ? route('coha.edit', [$patient, $consult])
                                        : ($consult->consultation_type === 'minor_visit' ? route('patients.consultations.minor-visit.edit', [$patient, $consult]) : route('patients.consultations.edit', [$patient, $consult])) }}"
                               title="Edit"
                               class="p-1.5 rounded text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 transition-colors">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            {{-- Delete button --}}
                            <form method="POST" action="{{ route('patients.consultations.destroy', [$patient, $consult]) }}"
                                  onsubmit="return confirm('Delete this consultation?')" style="display:inline;">
                                @csrf @method('DELETE')
                                <button type="submit" title="Delete" class="p-1.5 rounded text-gray-300 hover:text-red-400 hover:bg-red-50 transition-colors">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    @if($consult->chief_complaint)
                    <div class="consult-entry mb-2">
                        <div class="consult-section-label">Chief Complaint</div>
                        <p class="text-sm text-gray-700">{{ $consult->chief_complaint }}</p>
                    </div>
                    @endif
                    @if($consult->primary_diagnosis)
                    <div class="consult-entry">
                        <div class="consult-section-label">Diagnosis</div>
                        <p class="text-sm text-gray-700">{{ $consult->primary_diagnosis }}</p>
                    </div>
                    @endif
                    @php
                        $linkedRx = isset($prescriptions)
                            ? $prescriptions->where('consultation_id', $consult->id)->first()
                            : null;
                    @endphp
                    @if($linkedRx)
                    <div class="consult-entry mt-2 pt-2 border-t border-gray-50">
                        <div class="consult-section-label">Linked Prescription</div>
                        <div class="flex items-center gap-2 mt-0.5">
                            <a href="{{ route('patients.prescriptions.show', [$patient, $linkedRx]) }}"
                               class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-700 hover:text-green-900 hover:underline">
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m19 2-5 5"/><path d="m2 19 5-5"/><rect x="5" y="2" width="5" height="20" rx="1" transform="rotate(-45 5 2)"/></svg>
                                {{ $linkedRx->prescription_number }}
                            </a>
                            <span class="text-[10px] text-gray-400">·</span>
                            <span class="text-[10px] text-gray-500">{{ $linkedRx->items->count() }} drug(s)</span>
                            <span class="px-1.5 py-0.5 text-[9px] font-semibold rounded uppercase tracking-wide
                                {{ $linkedRx->status === 'issued' || $linkedRx->status === 'printed' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                                {{ $linkedRx->status }}
                            </span>
                        </div>
                    </div>
                    @endif
                </div>
                @endforeach
            </div>
            @endif

        </div>
        {{-- /left --}}

        {{-- RIGHT: summary sidebar --}}
        <div class="space-y-4">

            {{-- ── Recall & Follow-up card ── --}}
            @php
                // Last visit: merge consultations + treatment visits, pick the most recent
                $lastConsultDate  = ($consultations ?? collect())->max(fn($c) => $c->consultation_date ?? $c->created_at);
                $lastVisitDate    = ($treatmentVisits ?? collect())->max(fn($v) => $v->visit_date ?? $v->created_at);
                $lastActivityDate = collect(array_filter([$lastConsultDate, $lastVisitDate]))->max();

                $daysSince = $lastActivityDate ? (int) \Carbon\Carbon::parse($lastActivityDate)->diffInDays(now()) : null;

                // Recall status: green < 5 months, amber 5-6 months, red > 6 months
                if ($daysSince === null) {
                    $recallColor = 'gray';
                    $recallLabel = 'No visits yet';
                    $recallBadge = 'bg-gray-100 text-gray-500';
                } elseif ($daysSince <= 150) {
                    $recallColor = 'green';
                    $recallLabel = 'Active';
                    $recallBadge = 'bg-green-100 text-green-700';
                } elseif ($daysSince <= 180) {
                    $recallColor = 'amber';
                    $recallLabel = 'Due Soon';
                    $recallBadge = 'bg-amber-100 text-amber-700';
                } else {
                    $recallColor = 'red';
                    $recallLabel = 'Overdue';
                    $recallBadge = 'bg-red-100 text-red-600';
                }
            @endphp
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <span class="section-title">Recall &amp; Follow-up</span>
                    @if($daysSince !== null)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $recallBadge }}">{{ $recallLabel }}</span>
                    @endif
                </div>
                <div class="p-4 space-y-3">

                    {{-- Last visit row --}}
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Last Visit</span>
                        <span class="text-xs font-semibold text-gray-800">
                            @if($lastActivityDate)
                                {{ \Carbon\Carbon::parse($lastActivityDate)->format('d M Y') }}
                                <span class="font-normal text-gray-400">({{ $daysSince }}d ago)</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </span>
                    </div>

                    {{-- Recall due date row --}}
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-gray-500">Recall Due</span>
                        <span class="text-xs font-semibold {{ $recallColor === 'red' ? 'text-red-600' : ($recallColor === 'amber' ? 'text-amber-600' : 'text-gray-800') }}">
                            @if($lastActivityDate)
                                {{ \Carbon\Carbon::parse($lastActivityDate)->addMonths(6)->format('d M Y') }}
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </span>
                    </div>

                    {{-- Recall task status --}}
                    <div class="pt-2 border-t border-gray-100">
                        @if(isset($recallTask) && $recallTask)
                            <div class="flex items-start gap-2">
                                <div class="w-5 h-5 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0 mt-0.5">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-semibold text-gray-700 leading-snug">Recall task scheduled</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">
                                        Due {{ $recallTask->due_date?->format('d M Y') ?? '—' }}
                                        @if($recallTask->assignedTo)· {{ $recallTask->assignedTo->name }}@endif
                                    </p>
                                </div>
                            </div>
                        @else
                            <p class="text-[10px] text-gray-400 text-center leading-relaxed">
                                Recall task auto-generates when<br>treatment is marked complete.
                            </p>
                        @endif
                    </div>

                </div>
            </div>
            {{-- /Recall & Follow-up card --}}

            {{-- Patient snapshot --}}
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <span class="section-title">Patient Snapshot</span>
                </div>
                <div class="p-4 space-y-3">
                    @php
                        $snap = [
                            'Chief Complaint' => $patient->chief_complaint,
                            'Medical Alerts'  => $patient->medical_alert ?: 'No Known Allergies',
                            'Allergies'       => $patient->allergies ? (is_array($patient->allergies) ? implode(', ', $patient->allergies) : $patient->allergies) : null,
                            'Habits'          => $patient->habits ? (is_array($patient->habits) ? implode(', ', $patient->habits) : $patient->habits) : null,
                        ];
                    @endphp
                    @foreach($snap as $lbl => $val)
                    @if($val)
                    <div>
                        <div class="consult-section-label">{{ $lbl }}</div>
                        <p class="text-sm {{ $lbl === 'Medical Alerts' && $patient->medical_alert ? 'text-amber-600 font-medium' : ($lbl === 'Medical Alerts' ? 'text-green-600' : 'text-gray-700') }}">
                            {{ $val }}
                        </p>
                    </div>
                    @endif
                    @endforeach
                    <div class="pt-1 border-t border-gray-100">
                        <button x-on:click="window.dispatchEvent(new CustomEvent('open-edit-patient', { detail: window.__editPatientPrefill }))"
                                class="text-xs text-[#6a0f70] hover:underline font-medium">
                            Edit Patient Details →
                        </button>
                    </div>
                </div>
            </div>

            {{-- Treatment opportunities summary --}}
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <span class="section-title">Active Opportunities</span>
                    <button x-on:click="activeTab='profile'"
                            class="text-xs text-[#6a0f70] hover:underline font-medium">View All</button>
                </div>
                <div class="p-4">
                    <template x-if="opportunities.length === 0">
                        <p class="text-xs text-gray-400 py-2 text-center">No opportunities tracked yet.</p>
                    </template>
                    <template x-for="(opp, i) in opportunities.slice(0,4)" :key="'cs-opp-'+opp.id">
                        <div class="flex items-center justify-between py-2 border-b border-gray-50 last:border-0">
                            <div class="flex items-center gap-2">
                                <span class="text-base" x-text="oppIcons[opp.type] || ''"></span>
                                <span class="text-sm text-gray-700 capitalize"
                                      x-text="(opp.type||'').replace(/_/g,' ')"></span>
                            </div>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                  :class="{
                                    'bg-red-100 text-red-600':opp.priority==='high',
                                    'bg-amber-100 text-amber-700':opp.priority==='medium',
                                    'bg-gray-100 text-gray-500':opp.priority==='low',
                                  }"
                                  x-text="opp.priority.charAt(0).toUpperCase()+opp.priority.slice(1)"></span>
                        </div>
                    </template>
                </div>
            </div>

            {{-- Quick actions --}}
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <span class="section-title">Quick Actions</span>
                </div>
                <div class="p-3 grid grid-cols-2 gap-2">
                    @php
                    $consultTabActions = [
                        ['Add Follow-up',   "openVisitForm()",
                         '<rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/>'],
                        ['Treatment Plan',  "activeTab='treatment-plan'",
                         '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
                        ['Print Profile',   "window.open('" . route('patients.print', $patient) . "','_blank')",
                         '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>'],
                        ['Upload Scan',     "activeTab='documents'",
                         '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>'],
                    ];
                    @endphp
                    @foreach($consultTabActions as [$lbl, $handler, $path])
                    <button class="quick-action-btn" x-on:click="{{ $handler }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            {!! $path !!}
                        </svg>
                        <span>{{ $lbl }}</span>
                    </button>
                    @endforeach
                </div>
            </div>

        </div>
        {{-- /right --}}

    </div>
</div>
{{-- /consultation tab --}}





