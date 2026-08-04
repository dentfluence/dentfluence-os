@if(session('merged_notice'))
    <div class="mx-6 mt-3 flex items-center gap-2 bg-blue-50 border border-blue-200 text-blue-800 text-sm px-4 py-2 rounded">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 7v8a2 2 0 0 0 2 2h6"/><path d="m16 13 4 4-4 4"/><circle cx="5" cy="5" r="3"/></svg>
        <span>{{ session('merged_notice') }}</span>
    </div>
@endif

{{-- Duplicate Merge — safety-net undo (Final Design §1). Admin-only, and only
     shown while the merge is still within its bounded undo window with zero
     activity recorded against this patient since. Not a general rollback. --}}
@if(auth()->user()?->isAdminRole())
    @php
        $recentMerge = $patient->latestUndoableMerge();
        $undoStatus  = $recentMerge ? app(\App\Services\Patient\PatientMergeService::class)->undoStatus($recentMerge) : null;
    @endphp
    @if($recentMerge && $undoStatus && $undoStatus['allowed'])
        <div class="mx-6 mt-3 flex items-center justify-between gap-3 bg-amber-50 border border-amber-200 text-amber-900 text-sm px-4 py-2 rounded">
            <span>A duplicate record was merged into this patient {{ $recentMerge->created_at->diffForHumans() }}.</span>
            <form method="POST" action="{{ route('patients.merge.undo', [$patient, $recentMerge]) }}"
                  onsubmit="return confirm('Undo this merge? The merged record will be restored as a separate patient.');"
                  class="flex items-center gap-2">
                @csrf
                <input type="password" name="password" required placeholder="Your password"
                       class="text-xs border border-amber-300 rounded px-2 py-1 w-32">
                <button type="submit"
                        class="px-3 py-1 text-xs font-medium text-white bg-amber-600 rounded hover:bg-amber-700 whitespace-nowrap">
                    Undo ({{ $undoStatus['minutes_left'] }} min left)
                </button>
            </form>
        </div>
    @endif
@endif

{{-- ══════════════════════════════════════════════════════════
     HEADER (sticky)
══════════════════════════════════════════════════════════ --}}
<div id="patient-sticky-header" class="bg-white px-6 pt-4 pb-2">

    {{-- Top row: breadcrumb + buttons --}}
    <div class="flex items-center justify-between mb-4">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('patients.index') }}"
               class="flex items-center gap-1 text-gray-500 hover:text-[#6a0f70] transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
                Patients
            </a>
            <span class="text-gray-300">/</span>
            <span class="text-gray-700 font-medium">Patient Profile</span>
        </div>
        <div class="flex gap-2">
            {{-- Relationship Profile (PRE) — Timeline, Journeys, Communication, Tasks --}}
            @if($patient->relationship_id)
            <a href="{{ route('relationship.profile', $patient->relationship_id) }}"
               class="px-4 py-2 text-sm border border-gray-300 text-gray-700 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors bg-white font-medium">
                Relationship Profile
            </a>
            @endif
            {{-- Print patient profile --}}
            <a href="{{ route('patients.print', $patient) }}" target="_blank"
               class="px-4 py-2 text-sm border border-gray-300 text-gray-700 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors bg-white font-medium">
                Print
            </a>
            {{-- ABHA / Health ID --}}
            <a href="{{ route('patients.abha.edit', $patient) }}"
               class="px-4 py-2 text-sm border border-gray-300 text-gray-700 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors bg-white font-medium">
                ABHA
            </a>
            {{-- DPDP Consent --}}
            <a href="{{ route('consent.patient', $patient) }}"
               class="px-4 py-2 text-sm border border-gray-300 text-gray-700 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors bg-white font-medium">
                Consent
            </a>
            {{-- DPDP Data Request --}}
            <a href="{{ route('data-rights.create', ['patient' => $patient->id]) }}"
               class="px-4 py-2 text-sm border border-gray-300 text-gray-700 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors bg-white font-medium">
                Data Request
            </a>
            <button x-on:click="window.dispatchEvent(new CustomEvent('open-edit-patient', { detail: window.__editPatientPrefill }))"
                    class="px-4 py-2 text-sm border border-gray-300 text-gray-700 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors bg-white font-medium">
                Edit Patient
            </button>
            <button @click="openVisitForm()"
                    class="px-4 py-2 text-sm border border-gray-300 text-gray-700 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors bg-white font-medium">
                New Visit
            </button>
            <button onclick="openQuickPayModal()"
                    class="px-4 py-2 text-sm bg-green-600 text-white hover:bg-green-700 transition-colors font-medium">
                Record Payment
            </button>
            {{-- Deactivate / Delete dropdown --}}
            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                <button @click="open = !open"
                        class="px-3 py-2 text-sm border border-gray-200 text-gray-400 hover:border-red-300 hover:text-red-500 transition-colors bg-white font-medium leading-none">
                    ···
                </button>
                <div x-show="open" x-transition
                     class="absolute right-0 top-full mt-1 w-48 bg-white border border-gray-200 rounded shadow-lg z-50 py-1">
                    @if($patient->is_active ?? true)
                    <button onclick="document.getElementById('patient-action-modal').classList.remove('hidden'); document.getElementById('patient-action-mode').value='deactivate'; document.getElementById('patient-action-title').textContent='Deactivate Patient';"
                            class="w-full text-left px-4 py-2 text-sm text-amber-700 hover:bg-amber-50">
                        Deactivate Patient
                    </button>
                    @else
                    <form method="POST" action="{{ route('patients.reactivate', $patient) }}" class="w-full">
                        @csrf
                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50">
                            Reactivate Patient
                        </button>
                    </form>
                    <div class="mx-4 my-1 text-[10px] text-amber-600 bg-amber-50 px-2 py-1 rounded">
                        Deactivated: {{ $patient->deactivation_reason ?? '' }}
                    </div>
                    @endif
                    <div class="border-t border-gray-100 my-1"></div>
                    <button onclick="document.getElementById('patient-action-modal').classList.remove('hidden'); document.getElementById('patient-action-mode').value='delete'; document.getElementById('patient-action-title').textContent='Delete Patient Record';"
                            class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                        Delete Patient Record
                    </button>
                    @if(auth()->user()?->isAdminRole())
                    <div class="border-t border-gray-100 my-1"></div>
                    <a href="{{ route('patients.merge.create', $patient) }}"
                       class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                        Merge Duplicate…
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Patient identity row --}}
    <div class="flex items-start gap-5 pb-5 flex-wrap xl:flex-nowrap">

        {{-- Avatar --}}
        <div class="relative flex-shrink-0">
            @if($patient->photo ?? false)
                <img src="{{ asset('storage/'.$patient->photo) }}"
                     class="w-[72px] h-[72px] rounded-full object-cover ring-2 ring-white shadow-md">
            @else
                <div class="w-[72px] h-[72px] rounded-full bg-gradient-to-br from-[#6a0f70] to-[#380740]
                            flex items-center justify-center text-white text-2xl font-semibold shadow-md"
                     style="font-family:'Cormorant Garamond',serif;">
                    {{ $patient->initials }}
                </div>
            @endif
            <span class="absolute bottom-0.5 right-0.5 w-3.5 h-3.5 rounded-full border-2 border-white
                {{ $patient->recall_status === 'active' ? 'bg-green-400' :
                  ($patient->recall_status === 'overdue' ? 'bg-red-400' : 'bg-amber-400') }}">
            </span>
        </div>

        {{-- Name + meta --}}
        <div class="flex-1 min-w-0 pt-1">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-[22px] font-bold text-gray-900 leading-tight"
                    style="font-family:'Cormorant Garamond',serif;">
                    {{ $patient->name }}
                </h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold
                    {{ $patient->recall_status === 'active' ? 'bg-green-100 text-green-700' :
                      ($patient->recall_status === 'overdue' ? 'bg-red-100 text-red-600' : 'bg-amber-100 text-amber-700') }}">
                    {{ ucfirst($patient->recall_status ?? 'Active') }}
                </span>
            </div>

            <div class="flex items-center gap-3 mt-1 flex-wrap">
                {{-- Patient ID --}}
                <span class="font-mono text-xs bg-[#f5eef9] px-2.5 py-0.5 text-[#6a0f70] font-semibold tracking-wider border border-[#6a0f70]/20">
                    {{ $patient->patient_id ?? 'DF-'.str_pad($patient->id, 5, '0', STR_PAD_LEFT) }}
                </span>

                {{-- AOCP Membership badge --}}
                @if($patient->is_aocp_active)
                    <span class="inline-flex items-center gap-1 text-[10px] font-bold px-2.5 py-0.5 rounded-full bg-[#fdf3ff] text-[#6a0f70] border border-[#d8b4fe]">
                        <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="currentColor"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
                        AOCP Active
                        @if($patient->membership_expires_at)
                            · exp {{ $patient->membership_expires_at->format('d M Y') }}
                        @endif
                    </span>
                    {{-- Family identity badge (only if migration has run and family_name/member_type set) --}}
                    @if(($activeMembership ?? null) && in_array($activeMembership->member_type ?? 'individual', ['head','addon']))
                        @php
                            $famLabel = $activeMembership->member_type === 'head'
                                ? (($activeMembership->family_name ? $activeMembership->family_name . ' ' : '') . '· Head')
                                : (($activeMembership->familyHead->family_name ?? 'Family') . ' · Member');
                        @endphp
                        <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2.5 py-0.5 rounded-full bg-purple-50 text-purple-700 border border-purple-200">
                            <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            {{ $famLabel }}
                        </span>
                    @endif
                @elseif($patient->effective_membership_status === 'expired')
                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold px-2.5 py-0.5 rounded-full bg-red-50 text-red-600 border border-red-200">
                        AOCP Expired
                        @if($patient->membership_expires_at)
                            · {{ $patient->membership_expires_at->format('d M Y') }}
                        @endif
                    </span>
                @endif

                @if($patient->age ?? false)
                <span class="flex items-center gap-1 text-sm text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    </svg>
                    {{ $patient->age }}{{ $patient->gender ? ' / '.ucfirst($patient->gender) : '' }}
                </span>
                @endif
                <span class="flex items-center gap-1 text-sm text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.1 12 19.79 19.79 0 0 1 1.03 3.33 2 2 0 0 1 3 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 21 16.92z"/>
                    </svg>
                    {{ $patient->phone }}
                </span>
                @if($patient->phone)
                <button type="button" onclick="patientWhatsApp({{ $patient->id }}, this)"
                        class="inline-flex items-center gap-1 text-xs font-medium text-green-700 bg-green-50 hover:bg-green-100 border border-green-200 rounded px-2 py-0.5"
                        title="Message this patient on WhatsApp">
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12.04 2c-5.46 0-9.9 4.44-9.9 9.9 0 1.75.46 3.45 1.32 4.95L2 22l5.3-1.39c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.9-4.44 9.9-9.9S17.5 2 12.04 2m0 18.15c-1.48 0-2.93-.4-4.2-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.2 8.2 0 0 1-1.26-4.36c0-4.54 3.7-8.24 8.25-8.24 4.54 0 8.24 3.7 8.24 8.24s-3.7 8.24-8.24 8.24"/>
                    </svg>
                    WhatsApp
                </button>
                @endif
                @if($patient->area || $patient->city)
                <span class="flex items-center gap-1 text-sm text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
                    </svg>
                    {{ collect([$patient->area, $patient->city])->filter()->implode(', ') }}
                </span>
                @endif
            </div>

            <div class="flex items-center gap-4 mt-1 text-xs text-gray-400 flex-wrap">
                @if($patient->source)
                    <span>Source: <span class="text-[#6a0f70] font-medium">{{ $patient->source }}</span></span>
                @endif
                <span>Patient Since: {{ $patient->created_at->format('d M Y') }}</span>
                @if($patient->last_visit_date)
                    <span>Last Visit: {{ \Carbon\Carbon::parse($patient->last_visit_date)->format('d M Y') }}</span>
                @endif
            </div>
        </div>

        {{-- 6 stat cards --}}
        @php
            // Compute from live invoice data (same source as Billing tab)
            $totalBilled   = ($invoices ?? collect())->sum(fn($inv) => (float) $inv->total_amount);
            $totalCollectedHdr = ($invoices ?? collect())->sum(fn($inv) => (float) $inv->paid_amount);
            $totalOutstandingHdr = ($invoices ?? collect())->sum(fn($inv) => (float) $inv->balance_due);
            $collectedPct  = $totalBilled > 0 ? round(($totalCollectedHdr / $totalBilled) * 100, 1) : 0;
            // Advance / credit sitting in the wallet — money paid but not yet applied to an invoice.
            // Promotional credit is a discount liability, not the patient's money, so it is excluded.
            $advanceOnFile = (float) ($wallet->balance_permanent ?? 0);
            $acceptedOpps  = $opportunities->whereIn('status',['accepted','completed'])->count();
            $totalOpps     = $opportunities->count();
            $acceptPct     = $totalOpps > 0 ? round(($acceptedOpps/$totalOpps)*100) : 0;
        @endphp
        <div class="flex gap-2.5 flex-wrap xl:flex-nowrap flex-shrink-0 xl:ml-2">

            {{-- Total Billed --}}
            <div class="stat-card bg-white border border-gray-200 rounded-lg px-4 py-3 min-w-[120px] flex flex-col">
                <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Total Billed</div>
                <div class="text-sm font-bold text-gray-800">Rs.  {{ number_format($totalBilled,0) }}</div>
            </div>

            {{-- Total Collected --}}
            <div class="stat-card bg-white border border-gray-200 rounded-lg px-4 py-3 min-w-[120px] flex flex-col">
                <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Total Collected</div>
                <div class="text-sm font-bold text-gray-800">Rs.  {{ number_format($totalCollectedHdr,0) }}</div>
                @if($totalBilled > 0)
                <div class="text-[10px] text-green-600 font-semibold mt-auto pt-1">{{ $collectedPct }}% collected</div>
                @endif
            </div>

            {{-- Outstanding --}}
            <div class="stat-card bg-white border border-gray-200 rounded-lg px-4 py-3 min-w-[120px] flex flex-col">
                <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Outstanding</div>
                <div class="text-sm font-bold {{ $totalOutstandingHdr > 0 ? 'text-red-600' : 'text-gray-800' }}">
                    Rs.  {{ number_format($totalOutstandingHdr,0) }}
                </div>
            </div>

            {{-- Advance on file (credit wallet only) — shown only when the patient has prepaid --}}
            @if($advanceOnFile > 0)
            <div class="stat-card bg-white border border-purple-200 rounded-lg px-4 py-3 min-w-[120px] flex flex-col">
                <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Advance</div>
                <div class="text-sm font-bold text-[#6a0f70]">Rs.  {{ number_format($advanceOnFile,0) }}</div>
                <div class="text-[10px] text-gray-400 font-medium mt-auto pt-1">On file · unbilled</div>
            </div>
            @endif

            {{-- Recall Status --}}
            <div class="stat-card bg-white border border-gray-200 rounded-lg px-4 py-3 min-w-[120px] flex flex-col">
                <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Recall Status</div>
                <div class="text-sm font-bold
                    {{ $patient->recall_status === 'overdue' ? 'text-red-600' :
                      ($patient->recall_status === 'due' ? 'text-amber-600' : 'text-green-700') }}">
                    @if($patient->recall_status === 'overdue')
                        @if($patient->next_recall_date)
                            Due in {{ now()->diffInDays($patient->next_recall_date) }} days
                        @else Overdue @endif
                    @elseif($patient->recall_status === 'due') Due Soon
                    @else Up to Date @endif
                </div>
                <div class="text-[10px] text-gray-400 mt-auto pt-1">
                    @if($patient->next_recall_date)
                        {{ \Carbon\Carbon::parse($patient->next_recall_date)->format('d M Y') }}
                    @else
                        &nbsp;
                    @endif
                </div>
            </div>

        </div>
    </div>

    {{-- Medical alert banner --}}
    @php
        $clinicalAlerts = [];
        // Conditions (array cast)
        if (!empty($patient->medical_conditions)) {
            foreach ($patient->medical_conditions as $mc) {
                if (trim($mc)) $clinicalAlerts[] = ['text' => trim($mc), 'type' => 'condition'];
            }
        }
        // Allergies (array cast)
        if (!empty($patient->allergies)) {
            foreach ($patient->allergies as $al) {
                if (trim($al)) $clinicalAlerts[] = ['text' => 'Allergy: '.trim($al), 'type' => 'allergy'];
            }
        }
        // Medical alerts — stored as comma-separated string (checkbox flags + custom)
        if (!empty($patient->medical_alert)) {
            foreach (array_map('trim', explode(',', $patient->medical_alert)) as $ma) {
                if ($ma) $clinicalAlerts[] = ['text' => $ma, 'type' => 'alert'];
            }
        }
        // Habits that are clinical concerns
        $clinicalHabits = ['Tobacco (Chewing)','Tobacco (Smoking)','Alcohol','Smoking','Betel Nut','Pan Masala'];
        if (!empty($patient->habits)) {
            foreach ($patient->habits as $h) {
                if (in_array(trim($h), $clinicalHabits)) {
                    $clinicalAlerts[] = ['text' => trim($h), 'type' => 'habit'];
                }
            }
        }
    @endphp
    @if(count($clinicalAlerts))
    <div class="mx-0 mb-3 flex items-center gap-3 px-4 py-2 flex-wrap"
         style="background:#fff5f5; border-left:3px solid #dc2626; border-radius:4px;">
        <div class="flex items-center gap-1.5 text-red-700 font-bold text-[11px] tracking-widest uppercase flex-shrink-0">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/>
                <path d="M12 9v4"/><path d="M12 17h.01"/>
            </svg>
            Clinical Alerts
        </div>
        <div class="w-px h-3.5 bg-red-300 flex-shrink-0"></div>
        <div class="flex items-center gap-1.5 flex-wrap">
            @foreach($clinicalAlerts as $alert)
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 text-[11px] font-semibold rounded-full
                    {{ $alert['type'] === 'allergy' ? 'bg-amber-50 text-amber-700 border border-amber-300' :
                      ($alert['type'] === 'habit'   ? 'bg-orange-50 text-orange-700 border border-orange-300' :
                                                      'bg-red-50 text-red-700 border border-red-300') }}">
                    {{ $alert['text'] }}
                </span>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Deactivated patient banner --}}
    @if(isset($patient->is_active) && !$patient->is_active)
    <div class="mx-0 mb-3 flex items-center gap-3 px-4 py-2 flex-wrap"
         style="background:#fffbeb; border-left:3px solid #d97706; border-radius:4px;">
        <span class="text-amber-700 font-bold text-[11px] tracking-widest uppercase flex-shrink-0">Patient Deactivated</span>
        <div class="w-px h-3.5 bg-amber-300 flex-shrink-0"></div>
        <span class="text-xs text-amber-700">{{ $patient->deactivation_reason }}</span>
    </div>
    @endif

    {{-- Tabs — capsule pill nav. Money tabs (Billing/Wallet) only render for
         roles holding the finance View flag — same rule as the Journey
         Timeline's per-event filter; the fragment endpoint enforces it
         server-side too (Variants release pass 2026-08-03). --}}
    @php
        $canSeeFinanceTabs = auth()->user()?->canAccess('finance', 'view');
        $patientTabs = [
            'profile'           => 'Profile',
            'consultation'      => 'Consultation',
            'treatment-plan'    => 'Treatment Plan',
            'visits'            => 'Treatment Visits',
            'lab'               => 'Lab Cases',
            'prescriptions'     => 'Prescriptions',
            'billing'           => 'Billing',
            'wallet'            => 'Wallet',
            'membership'        => 'Membership',
            'documents'         => 'Documents',
            'notes'             => 'Notes & Logs',
        ];
        if (! $canSeeFinanceTabs) {
            unset($patientTabs['billing'], $patientTabs['wallet']);
        }
    @endphp
    <div class="patient-tab-nav mt-3 mx-1 mb-1">
        @foreach($patientTabs as $tab => $label)
        <button
            x-on:click="activeTab = '{{ $tab }}'"
            dusk="tab-{{ $tab }}"
            :class="activeTab === '{{ $tab }}' ? 'active' : ''"
            class="patient-tab-btn">
            {{ $label }}
        </button>
        @endforeach
    </div>
</div>
{{-- /header --}}

{{-- Tab content scroll area — sits below sticky header --}}
<div style="padding-top:4px;"></div>
