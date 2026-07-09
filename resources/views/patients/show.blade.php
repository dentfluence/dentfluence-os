@extends('layouts.app')

@push('styles')
<style>
    /* Patient profile — edge-to-edge content, no inner padding */
    #df-content-inner { padding: 0 !important; max-width: 100% !important; }
    #df-content-area  { background: #f3f4f8 !important; }

    /* ── Sticky patient header ── */
    #patient-sticky-header {
        position: sticky;
        top: 0;          /* topbar is a flex sibling, not fixed — content area starts below it */
        z-index: 40;
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        box-shadow: 0 2px 8px rgba(106,15,112,0.06);
    }

    .stat-card { transition: box-shadow 0.15s, transform 0.15s; }
    .stat-card:hover { box-shadow: 0 4px 16px rgba(106,15,112,0.10); transform: translateY(-1px); }
    .opp-dot { width:10px;height:10px;border-radius:50%;border:2px solid #e5e7eb;background:white;flex-shrink:0; }
    .opp-dot.active { border-color:currentColor;background:currentColor; }
    .opp-dot.passed { background:currentColor;border-color:currentColor;opacity:0.4; }
    .opp-line { flex:1;height:2px;background:#e5e7eb; }
    .opp-line.passed { opacity:0.4; }
    .timeline-icon { width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
    .visit-row { transition:background 0.12s; }
    .visit-row:hover { background:#faf5ff; }
    .rapport-item { display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid #f3f4f6; }
    .rapport-item:last-child { border-bottom:none; }
    .tag-pill { display:inline-flex;align-items:center;font-size:12px;padding:4px 12px;border-radius:999px;font-weight:500;border:1.5px solid transparent; }
    .quick-action-btn { display:flex;flex-direction:column;align-items:center;gap:5px;padding:10px 6px;border:1px solid #e5e7eb;background:white;cursor:pointer;transition:all 0.15s;border-radius:4px;text-align:center; }
    .quick-action-btn:hover { border-color:#6a0f70;background:#faf5ff; }
    .quick-action-btn svg { color:#6b7280; }
    .quick-action-btn:hover svg { color:#6a0f70; }
    .quick-action-btn span { font-size:10px;color:#6b7280;line-height:1.3;font-weight:500; }
    .quick-action-btn:hover span { color:#6a0f70; }
    .opp-card { min-width:220px;max-width:220px;border:1px solid #e5e7eb;border-radius:6px;padding:14px;background:white;flex-shrink:0;transition:border-color 0.15s; }
    .opp-card:hover { border-color:#6a0f70; }
    .scroll-area { display:flex;gap:12px;overflow-x:auto;padding-bottom:6px;scrollbar-width:none; }
    .scroll-area::-webkit-scrollbar { display:none; }
    .section-title { font-size:12px;font-weight:700;color:#5b21b6;letter-spacing:0.06em;text-transform:uppercase; }

    /* Consultation screen styles */
    .consult-card { background:white;border:1px solid #e5e7eb;border-radius:8px;padding:20px;transition:all 0.15s; }
    .consult-card:hover { border-color:#6a0f70;box-shadow:0 2px 12px rgba(106,15,112,0.08); }
    .consult-entry { border-left:3px solid #e5e7eb;padding-left:16px;position:relative; }
    .consult-entry::before { content:'';position:absolute;left:-6px;top:6px;width:10px;height:10px;border-radius:50%;background:#6a0f70;border:2px solid white;box-shadow:0 0 0 2px #6a0f70; }
    .consult-section-label { font-size:11px;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.07em;margin-bottom:6px; }

    /* ── Patient profile — capsule tab nav ───────────────────── */
    .patient-tab-nav {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 5px 6px;
        background: #f0e6f2;
        border-radius: 12px;
        overflow-x: auto;
        scrollbar-width: none;
        flex-wrap: nowrap;
    }
    .patient-tab-nav::-webkit-scrollbar { display: none; }
    .patient-tab-btn {
        flex-shrink: 0;
        padding: 6px 14px;
        border-radius: 8px;
        border: none;
        background: transparent;
        font-size: 13px;
        font-weight: 500;
        color: #6b7280;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.15s, color 0.15s, box-shadow 0.15s;
        line-height: 1.4;
    }
    .patient-tab-btn:hover {
        background: rgba(106,15,112,0.08);
        color: #6a0f70;
    }
    .patient-tab-btn.active {
        background: #ffffff;
        color: #6a0f70;
        font-weight: 600;
        box-shadow: 0 1px 4px rgba(106,15,112,0.15), 0 0 0 1px rgba(106,15,112,0.10);
    }
</style>
@endpush

@section('content')
<div x-data="patientProfile()" x-init="init()">

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
            <button x-on:click="editDrawerOpen = true"
                    class="px-4 py-2 text-sm border border-gray-300 text-gray-700 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors bg-white font-medium">
                Edit Patient
            </button>
            <button @click="activeTab='visits'; $nextTick(() => window.dispatchEvent(new CustomEvent('open-visit-form')))"
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
                <div class="text-[10px] text-green-600 font-semibold mt-auto pt-1">{{ $collectedPct }}% collected</div>
            </div>

            {{-- Outstanding --}}
            <div class="stat-card bg-white border border-gray-200 rounded-lg px-4 py-3 min-w-[120px] flex flex-col">
                <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Outstanding</div>
                <div class="text-sm font-bold {{ $totalOutstandingHdr > 0 ? 'text-red-600' : 'text-gray-800' }}">
                    Rs.  {{ number_format($totalOutstandingHdr,0) }}
                </div>
            </div>

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

    {{-- Tabs — capsule pill nav --}}
    <div class="patient-tab-nav mt-3 mx-1 mb-1">
        @foreach([
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
        ] as $tab => $label)
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
                            <button x-on:click="editDrawerOpen = true" class="text-xs text-[#6a0f70] hover:underline font-medium">Edit</button>
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

            {{-- Visit Log / Combined Timeline --}}
            @php
                // Build a unified timeline from consultations + treatment visits
                $tlConsults = ($consultations ?? collect())->map(function($c) use ($patient) {
                    return [
                        'kind'        => 'consultation',
                        'id'          => $c->id,
                        'date'        => $c->consultation_date ?? $c->created_at,
                        'title'       => $c->visit_type ? ucwords(str_replace('_',' ',$c->visit_type)).' Consultation' : 'Consultation',
                        'subtitle'    => $c->chief_complaint,
                        'doctor'      => null,          // consultations don't carry doctor_name here
                        'status'      => $c->status ?? 'completed',
                        'amount'      => null,
                        'edit_url'    => route('patients.consultations.edit', [$patient, $c]),
                        'delete_url'  => route('patients.consultations.destroy', [$patient, $c]),
                        'delete_method' => 'DELETE',
                    ];
                });
                $tlVisits = ($treatmentVisits ?? collect())->map(function($v) use ($patient) {
                    return [
                        'kind'        => 'treatment',
                        'id'          => $v->id,
                        'date'        => $v->visit_date ?? $v->created_at,
                        'title'       => $v->treatment_name ?? ucfirst($v->visit_type ?? 'Treatment').' Visit',
                        'subtitle'    => $v->chief_complaint ?? $v->notes,
                        'tooth'       => $v->tooth_number,
                        'doctor'      => $v->doctor?->name,
                        'status'      => $v->status ?? 'completed',
                        'amount'      => $v->cost > 0 ? $v->cost : null,
                        'edit_url'    => null,          // handled by JS openEditForm inside visits tab
                        'delete_url'  => route('visits.destroy', $v),
                        'delete_method' => 'DELETE',
                    ];
                });
                $combinedTimeline = $tlConsults->concat($tlVisits)
                    ->sortByDesc(fn($e) => \Carbon\Carbon::parse($e['date'])->timestamp)
                    ->values()
                    ->take(8);
                $totalTimelineCount = $tlConsults->count() + $tlVisits->count();
            @endphp
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <span class="section-title">Visit Log / Timeline</span>
                    <div class="flex items-center gap-3">
                        <span class="text-[10px] text-gray-400">Consultations &amp; Visits</span>
                        <button x-on:click="activeTab='visits'" class="text-xs text-[#6a0f70] hover:underline font-medium">View All</button>
                    </div>
                </div>
                @if($combinedTimeline->isEmpty())
                    <div class="py-12 text-center text-sm text-gray-400">No visits or consultations yet.</div>
                @else
                <div class="divide-y divide-gray-100">
                    @foreach($combinedTimeline as $entry)
                    @php
                        $isConsult  = $entry['kind'] === 'consultation';
                        $entryDate  = \Carbon\Carbon::parse($entry['date']);
                        $borderCol  = $isConsult ? '#7c3aed' : '#16a34a';
                        $iconBg     = $isConsult ? 'bg-purple-100' : 'bg-green-100';
                        $iconColor  = $isConsult ? '#7c3aed'       : '#16a34a';
                        $badgeCls   = $isConsult ? 'bg-purple-50 text-purple-700' : 'bg-green-50 text-green-700';
                        $statusCls  = match($entry['status']) {
                            'completed'  => 'bg-green-50 text-green-700',
                            'in_chair'   => 'bg-blue-50 text-blue-600',
                            'draft'      => 'bg-amber-50 text-amber-600',
                            default      => 'bg-gray-100 text-gray-500',
                        };
                    @endphp
                    <div class="visit-row flex group" style="border-left:3px solid {{ $borderCol }}">
                        {{-- Date column --}}
                        <div class="w-14 flex-shrink-0 flex flex-col items-center justify-center py-4 px-2 bg-gray-50/70 border-r border-gray-100">
                            <div class="text-lg font-bold text-gray-800 leading-none">{{ $entryDate->format('d') }}</div>
                            <div class="text-[10px] text-gray-400 uppercase tracking-wide">{{ $entryDate->format('M') }}</div>
                            <div class="text-[10px] text-gray-400">{{ $entryDate->format('Y') }}</div>
                        </div>
                        {{-- Icon --}}
                        <div class="flex-shrink-0 flex items-center px-3">
                            <div class="timeline-icon {{ $iconBg }}" style="color:{{ $iconColor }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    @if($isConsult)
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                    @else
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    @endif
                                </svg>
                            </div>
                        </div>
                        {{-- Content --}}
                        <div class="flex-1 min-w-0 py-3 pr-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <span class="font-semibold text-gray-800 text-sm leading-tight">{{ $entry['title'] }}</span>
                                        @if(!$isConsult && !empty($entry['tooth']))
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-purple-100 text-[#6a0f70]">
                                                Tooth {{ $entry['tooth'] }}
                                            </span>
                                        @endif
                                    </div>
                                    @if($entry['subtitle'])
                                        <div class="text-xs text-gray-500 mt-0.5 truncate">{{ $entry['subtitle'] }}</div>
                                    @endif
                                    @if($entry['doctor'])
                                        <div class="text-xs text-gray-400 mt-0.5">{{ $entry['doctor'] }}</div>
                                    @endif
                                </div>
                                <div class="flex-shrink-0 text-right space-y-1">
                                    <span class="inline-block px-2 py-0.5 text-[10px] rounded-full font-medium {{ $badgeCls }}">
                                        {{ $isConsult ? 'Consultation' : 'Treatment' }}
                                    </span>
                                    @if($entry['amount'])
                                        <div class="text-xs font-bold text-gray-700">Rs.  {{ number_format($entry['amount'],0) }}</div>
                                    @endif
                                    <span class="inline-block px-2 py-0.5 text-[10px] rounded-full font-medium {{ $statusCls }}">
                                        {{ ucfirst(str_replace('_',' ',$entry['status'])) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        {{-- Edit / Delete actions (visible on hover) --}}
                        <div class="flex flex-col items-center justify-center gap-1 px-2 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                            @if($entry['edit_url'])
                            <a href="{{ $entry['edit_url'] }}"
                               class="w-7 h-7 flex items-center justify-center rounded hover:bg-purple-50 text-gray-300 hover:text-[#7c3aed] transition-colors"
                               title="Edit">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </a>
                            @else
                            {{-- Treatment visits: scroll to visits tab + open edit form --}}
                            <button x-on:click="activeTab='visits'"
                                    title="Edit in Visits tab"
                                    class="w-7 h-7 flex items-center justify-center rounded hover:bg-green-50 text-gray-300 hover:text-[#16a34a] transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                            </button>
                            @endif
                            <button
                                x-data
                                x-on:click="
                                    if (!confirm('Delete this {{ $isConsult ? 'consultation' : 'treatment visit' }}?')) return;
                                    fetch('{{ $entry['delete_url'] }}', {
                                        method: 'DELETE',
                                        headers: {
                                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                            'Accept': 'application/json'
                                        }
                                    }).then(r => r.json()).then(d => {
                                        if (d.success) $el.closest('.visit-row').remove();
                                        else alert(d.message || 'Delete failed.');
                                    });
                                "
                                class="w-7 h-7 flex items-center justify-center rounded hover:bg-red-50 text-gray-300 hover:text-red-400 transition-colors"
                                title="Delete">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between">
                    <span class="text-xs text-gray-400">Showing {{ $combinedTimeline->count() }} of {{ $totalTimelineCount }} entries</span>
                    <button x-on:click="activeTab='visits'" class="text-xs text-[#6a0f70] hover:underline font-medium">View Full Timeline →</button>
                </div>
                @endif
            </div>

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
                        ['Add Follow-up',       'js',     "activeTab='visits'; setTimeout(() => window.dispatchEvent(new CustomEvent('open-visit-form')), 50)"],
                        ['Add Treatment Visit', 'js',     "activeTab='visits'; setTimeout(() => window.dispatchEvent(new CustomEvent('open-visit-form')), 50)"],
                        // Documents & Scheduling
                        ['Upload X-ray / Scan', 'tab',    'documents'],
                        ['Treatment Plan',      'tab',    'treatment-plan'],
                        ['Book Appointment',    'alpine', "openAppointmentModal('appointment', null, {{ $patient->id }})"],
                        // Prescriptions & Billing
                        ['Write Prescription',  'url',    route('patients.prescriptions.create', $patient)],
                        ['Billing',             'tab',    'billing'],
                        ['Wallet',              'tab',    'wallet'],
                        ['Membership',          'js',     "activeTab='membership'; document.getElementById('enrollModal').classList.remove('hidden')"],
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
{{-- ══════════════════════════════════════════════════════════
     CONSULTATION TAB
══════════════════════════════════════════════════════════ --}}
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
                    {{-- New Consultation (primary) --}}
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
                            {{-- Edit button (COHA gets its own edit route; others use standard edit) --}}
                            <a href="{{ $isCoha ? route('coha.edit', [$patient, $consult]) : route('patients.consultations.edit', [$patient, $consult]) }}"
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
                        <button x-on:click="editDrawerOpen=true"
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
                        ['Add Follow-up',   "activeTab='visits'; setTimeout(() => window.dispatchEvent(new CustomEvent('open-visit-form')), 50)",
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





@include('patients.partials.treatment-plan-tab')
@include('patients.partials.treatment-visits-tab')

{{-- Lab Cases Tab --}}
@php $labCases = $patient->labCases()->with('doctor')->get(); @endphp
@php $labDoctors = \App\Models\User::orderBy('name')->get(); @endphp
@include('patients.partials.lab-tab', ['cases' => $labCases, 'doctors' => $labDoctors])

{{-- ═══════════════════════════════════════════════════════════
     PRESCRIPTIONS TAB
════════════════════════════════════ --}}
<div x-show="activeTab === 'prescriptions'" style="display:none" class="w-full px-6 py-5">
    <div class="max-w-4xl mx-auto" x-data="{ activeForm: null }">

        {{-- ── Tab header ── --}}
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-gray-800">Prescriptions</h2>
            <button @click="activeForm = (activeForm === 'new' ? null : 'new')"
                    dusk="rx-new-toggle"
                    class="text-sm px-3 py-1.5 bg-red-600 text-white font-semibold rounded-lg hover:bg-red-700 transition"
                    x-text="activeForm === 'new' ? 'Cancel' : '+ New Prescription'">
            </button>
        </div>

        {{-- Medical alert banner --}}
        @if($patient->medical_alert)
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-xs text-amber-800 flex gap-2">
            <span></span>
            <span><strong>Medical Alert:</strong> {{ $patient->medical_alert }}</span>
        </div>
        @endif

        {{-- ── Inline Quick Prescription Form ── --}}
        <div x-show="activeForm === 'new'" x-cloak
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="mb-5">
            @include('prescriptions.partials.quick-form', [
                'patient'      => $patient,
                'prescription' => null,
                'formAction'   => route('patients.prescriptions.store', $patient),
                'formMethod'   => 'POST',
                'cancelUrl'    => null,
            ])
        </div>

        {{-- ── Past Prescriptions List ── --}}
        @if(isset($prescriptions) && $prescriptions->isNotEmpty())
        <div class="space-y-2">
            @foreach($prescriptions as $rx)
            <div class="bg-white border border-gray-100 rounded-xl p-4 flex items-center gap-4 hover:border-red-200 transition">

                {{-- Status dot --}}
                <div class="shrink-0">
                    @if(in_array($rx->status, ['issued','printed','whatsapp_sent','email_sent']))
                        <span class="w-2.5 h-2.5 rounded-full bg-green-500 inline-block"></span>
                    @elseif($rx->status === 'draft')
                        <span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span>
                    @elseif($rx->status === 'cancelled')
                        <span class="w-2.5 h-2.5 rounded-full bg-red-400 inline-block"></span>
                    @elseif($rx->status === 'revised')
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-400 inline-block"></span>
                    @else
                        <span class="w-2.5 h-2.5 rounded-full bg-gray-300 inline-block"></span>
                    @endif
                </div>

                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-mono text-sm font-semibold text-brand-700">{{ $rx->prescription_number }}</span>

                        {{-- Status badge — covers all statuses --}}
                        @php
                            $statusStyle = match($rx->status) {
                                'issued'         => 'bg-green-100 text-green-700',
                                'printed'        => 'bg-teal-100 text-teal-700',
                                'whatsapp_sent'  => 'bg-lime-100 text-lime-700',
                                'email_sent'     => 'bg-sky-100 text-sky-700',
                                'draft'          => 'bg-amber-100 text-amber-700',
                                'revised'        => 'bg-purple-100 text-purple-700',
                                'cancelled'      => 'bg-red-100 text-red-500',
                                default          => 'bg-gray-100 text-gray-400',
                            };
                            $statusLabel = match($rx->status) {
                                'whatsapp_sent'  => 'WhatsApp',
                                'email_sent'     => 'Emailed',
                                default          => ucfirst($rx->status),
                            };
                        @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $statusStyle }}">
                            {{ $statusLabel }}
                        </span>

                        {{-- Source badge --}}
                        @if($rx->source)
                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-600 border border-blue-100">
                            {{ $rx->sourceLabel() }}
                        </span>
                        @endif

                        @if($rx->diagnosis)
                            <span class="text-xs text-gray-400 truncate max-w-xs">{{ $rx->diagnosis }}</span>
                        @endif
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5">
                        {{ $rx->created_at->format('d M Y') }}
                        &nbsp;·&nbsp; {{ $rx->prescribedBy?->doctor_name ?? '—' }}
                        @if($rx->items && $rx->items->count())
                            &nbsp;·&nbsp; {{ $rx->items->count() }} {{ Str::plural('drug', $rx->items->count()) }}
                        @endif
                        @if($rx->print_count)
                            &nbsp;·&nbsp; <span class="text-gray-300">Printed {{ $rx->print_count }}×</span>
                        @endif
                    </p>
                </div>

                {{-- Actions --}}
                <div class="flex items-center gap-1.5 shrink-0">
                    {{-- Print — opens print-optimised page with auto-print --}}
                    <a href="{{ route('patients.prescriptions.print', [$patient, $rx]) }}?auto=1"
                       target="_blank"
                       title="Print prescription"
                       class="text-xs px-2.5 py-1.5 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition flex items-center gap-1">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Print
                    </a>

                    {{-- PDF — opens same print page (user saves as PDF) --}}
                    <a href="{{ route('patients.prescriptions.pdf', [$patient, $rx]) }}"
                       target="_blank"
                       title="Save as PDF"
                       class="text-xs px-2.5 py-1.5 border border-gray-200 rounded-lg text-gray-500 hover:bg-gray-50 hover:text-red-600 transition flex items-center gap-1">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
                        </svg>
                        PDF
                    </a>

                    {{-- View --}}
                    <a href="{{ route('patients.prescriptions.show', [$patient, $rx]) }}"
                       class="text-xs px-2.5 py-1.5 border border-gray-200 rounded-lg text-gray-600 hover:bg-gray-50 transition">
                        View
                    </a>

                    {{-- Edit (always available unless cancelled) — toggles the same inline form used for New Prescription --}}
                    @if(!$rx->isCancelled())
                    <button type="button"
                            @click="activeForm = (activeForm === {{ $rx->id }} ? null : {{ $rx->id }})"
                            class="text-xs px-2.5 py-1.5 bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition"
                            x-text="activeForm === {{ $rx->id }} ? 'Cancel' : 'Edit'">
                    </button>
                    @endif
                </div>
            </div>

            {{-- Inline edit form for this Rx — same component as "+ New Prescription" --}}
            @if(!$rx->isCancelled())
            <div x-show="activeForm === {{ $rx->id }}" x-cloak
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="bg-white border border-red-100 rounded-xl p-4 -mt-1">
                @include('prescriptions.partials.quick-form', [
                    'patient'      => $patient,
                    'prescription' => $rx,
                    'formAction'   => route('patients.prescriptions.update', [$patient, $rx]),
                    'formMethod'   => 'PUT',
                    'cancelUrl'    => null,
                ])
            </div>
            @endif
            @endforeach
        </div>

        @if($prescriptions->count() >= 20)
        <div class="mt-3 text-center">
            <a href="{{ route('patients.prescriptions.index', $patient) }}"
               class="text-xs text-brand-600 hover:underline">View all prescriptions →</a>
        </div>
        @endif

        @else
        <div class="text-center py-12 text-gray-400">
            <p class="text-3xl mb-2"></p>
            <p class="text-sm font-medium">No prescriptions yet</p>
            <button @click="activeForm = 'new'"
                    class="mt-3 inline-block text-sm text-red-600 hover:underline">
                Write the first prescription →
            </button>
        </div>
        @endif

    </div>
</div>{{-- /prescriptions tab --}}

{{-- ═══════════════════════════════════════════════════════════
     BILLING TAB
════════════════════════════��═══════ --}}
<div x-show="activeTab === 'billing'" style="display:none" class="w-full px-6 py-5">
<div>

    @php
        // ── Billing summary from new Invoice model ────────────────────────────
        $totalBilled      = ($invoices ?? collect())->sum(fn($inv) => (float) $inv->total_amount);
        $totalCollected   = ($invoices ?? collect())->sum(fn($inv) => (float) $inv->paid_amount);
        $totalOutstanding = ($invoices ?? collect())->sum(fn($inv) => (float) $inv->balance_due);
        $pendingPrompts   = ($billingPrompts ?? collect())->where('status', 'pending');
        $walletBalance    = (float) ($wallet->balance_total ?? 0);
    @endphp

    <div class="grid grid-cols-1 xl:grid-cols-[1fr_280px] gap-5">

        {{-- ══ MAIN COLUMN ══ --}}
        <div class="space-y-4">

            {{-- Summary cards --}}
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Total Billed</div>
                    <div class="text-xl font-bold text-gray-800">Rs.  {{ number_format($totalBilled, 0) }}</div>
                    <div class="text-[10px] text-gray-400 mt-0.5">{{ ($invoices ?? collect())->count() }} invoice{{ ($invoices ?? collect())->count() !== 1 ? 's' : '' }}</div>
                </div>
                <div class="bg-white border border-gray-200 rounded-lg p-4">
                    <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Collected</div>
                    <div class="text-xl font-bold text-green-700">Rs.  {{ number_format($totalCollected, 0) }}</div>
                    <div class="text-[10px] text-gray-400 mt-0.5">
                        {{ $totalBilled > 0 ? round(($totalCollected / $totalBilled) * 100) : 0 }}% collected
                    </div>
                </div>
                <div class="{{ $totalOutstanding > 0 ? 'cursor-pointer hover:border-red-400 hover:shadow-md' : '' }} bg-white border border-gray-200 rounded-lg p-4 transition"
                     @if($totalOutstanding > 0) onclick="openQuickPayModal()" title="Click to record payment" @endif>
                    <div class="text-xs text-gray-400 uppercase tracking-wider mb-1">Outstanding</div>
                    <div class="text-xl font-bold {{ $totalOutstanding > 0 ? 'text-red-600' : 'text-gray-800' }}">
                        Rs.  {{ number_format($totalOutstanding, 0) }}
                    </div>
                    @if($totalOutstanding > 0)
                        <div class="text-[10px] text-red-400 mt-0.5 flex items-center gap-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            Tap to pay
                        </div>
                    @else
                        <div class="text-[10px] text-green-600 mt-0.5">All clear</div>
                    @endif
                </div>
            </div>

            {{-- ── Billing Prompts ──────────────────────────────────────── --}}
            @if($pendingPrompts->isNotEmpty())
            <div class="bg-amber-50 border border-amber-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3 border-b border-amber-200 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span class="text-xs font-semibold text-amber-800">{{ $pendingPrompts->count() }} Pending Billing Prompt{{ $pendingPrompts->count() > 1 ? 's' : '' }}</span>
                </div>
                <div class="divide-y divide-amber-100">
                    @foreach($pendingPrompts as $prompt)
                    <div class="flex items-center gap-3 px-5 py-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-gray-800">{{ $prompt->description }}</p>
                            <p class="text-[11px] text-gray-500 mt-0.5">{{ $prompt->created_at->format('d M Y, h:i A') }}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            {{-- Opens the editable draft invoice, pre-filled with the visit's treatments --}}
                            <a href="{{ route('billing.createFromPrompt', [$patient, $prompt]) }}"
                               class="inline-flex items-center gap-1 px-3 py-1.5 text-[11px] font-semibold bg-[#6a0f70] text-white rounded hover:bg-[#380740] transition">
                                Build Invoice
                            </a>
                            <form method="POST" action="{{ route('billing.dismissPrompt', $prompt) }}" class="inline">
                                @csrf
                                <button type="submit" onclick="return confirm('Dismiss this billing prompt without invoicing?')"
                                        class="px-3 py-1.5 text-[11px] font-medium border border-amber-300 text-amber-700 rounded hover:bg-amber-100 transition">
                                    Dismiss
                                </button>
                            </form>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            @php
                $allReceipts  = ($invoices ?? collect())->flatMap(fn($i) => $i->receipts ?? collect())->sortByDesc('receipt_date');
                $allFinalBills = ($invoices ?? collect())->filter(fn($i) => $i->finalBill)->map(fn($i) => $i->finalBill)->sortByDesc('generated_date');
            @endphp

            {{-- ── Invoices + Receipts side by side ──────────────────────── --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">

                {{-- Invoices --}}
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden flex flex-col">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <span class="section-title">Invoices</span>
                        <a href="{{ route('billing.create', ['patient_id' => $patient->id]) }}"
                           class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs bg-[#6a0f70] text-white rounded hover:bg-[#380740] transition">
                            <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                            New Invoice
                        </a>
                    </div>
                    @if(($invoices ?? collect())->isEmpty())
                        <div class="py-10 text-center flex-1">
                            <p class="text-sm font-semibold text-gray-500">No invoices yet</p>
                            <p class="text-xs text-gray-400 mt-1">Create one via "New Invoice".</p>
                        </div>
                    @else
                        <div class="divide-y divide-gray-100 flex-1">
                            @foreach($invoices as $inv)
                            @php
                                $badgeCls = match($inv->status) {
                                    'paid'      => 'bg-green-50 text-green-700 border-green-200',
                                    'partial'   => 'bg-amber-50 text-amber-700 border-amber-200',
                                    'cancelled' => 'bg-gray-100 text-gray-500 border-gray-200',
                                    default     => 'bg-blue-50 text-blue-700 border-blue-200',
                                };
                                $badgeLabel = match($inv->status) {
                                    'paid'      => 'Paid',
                                    'partial'   => 'Partial',
                                    'cancelled' => 'Cancelled',
                                    default     => 'Unpaid',
                                };
                                $invCanDelete = $inv->status !== 'paid';
                            @endphp
                            <div class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50/60 transition group">
                                <div class="w-8 h-8 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/>
                                        <line x1="16" y1="8" x2="8" y2="8"/><line x1="16" y1="12" x2="8" y2="12"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-xs font-bold text-gray-800">{{ $inv->invoice_number ?? '—' }}</span>
                                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full border {{ $badgeCls }}">{{ $badgeLabel }}</span>
                                    </div>
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        {{ $inv->invoice_date?->format('d M Y') }}
                                        @if($inv->items->count()) · {{ $inv->items->count() }} item{{ $inv->items->count() > 1 ? 's' : '' }} @endif
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="text-xs font-bold text-gray-800">Rs. {{ number_format($inv->total_amount, 0) }}</div>
                                    @if($inv->balance_due > 0)
                                        <div class="text-[10px] text-red-500">Due Rs. {{ number_format($inv->balance_due, 0) }}</div>
                                    @elseif($inv->paid_amount > 0)
                                        <div class="text-[10px] text-green-600">Paid Rs. {{ number_format($inv->paid_amount, 0) }}</div>
                                    @endif
                                </div>
                                <div class="flex items-center gap-0.5 opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                                    <a href="{{ route('billing.show', $inv) }}"
                                            class="w-7 h-7 flex items-center justify-center rounded hover:bg-blue-50 text-gray-400 hover:text-blue-600" title="View">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <a href="{{ route('billing.print', $inv) }}" target="_blank"
                                       class="w-7 h-7 flex items-center justify-center rounded hover:bg-amber-50 text-gray-400 hover:text-[#b45309]" title="Print">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                    </a>
                                    @if($invCanDelete)
                                    <button onclick="openDeleteModal({{ $inv->id }}, '{{ $inv->invoice_number ?? 'Invoice #'.$inv->id }}')"
                                            class="w-7 h-7 flex items-center justify-center rounded hover:bg-red-50 text-gray-400 hover:text-red-600" title="Delete">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                    </button>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Receipts --}}
                <div class="bg-white border border-gray-200 rounded-lg overflow-hidden flex flex-col">
                    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                        <span class="section-title">Receipts</span>
                        <span class="text-xs text-gray-400">{{ $allReceipts->count() }} receipt{{ $allReceipts->count() !== 1 ? 's' : '' }}</span>
                    </div>
                    @if($allReceipts->isEmpty())
                        <div class="py-10 text-center flex-1">
                            <p class="text-sm font-semibold text-gray-500">No receipts yet</p>
                            <p class="text-xs text-gray-400 mt-1">Generated when a payment is recorded.</p>
                        </div>
                    @else
                        <div class="divide-y divide-gray-100 flex-1">
                            @foreach($allReceipts as $rcpt)
                            <div class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50/60 group">
                                <div class="w-8 h-8 rounded-lg bg-green-50 flex items-center justify-center flex-shrink-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M9 14l2 2 4-4"/><rect x="3" y="5" width="18" height="14" rx="2"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="text-xs font-bold text-gray-800 font-mono">{{ $rcpt->receipt_number }}</div>
                                    <div class="text-[11px] text-gray-400 mt-0.5">
                                        {{ $rcpt->receipt_date?->format('d M Y') }}
                                        · {{ ucfirst(str_replace('_',' ',$rcpt->payment_mode ?? '')) }}
                                        @if($rcpt->reference_no) · {{ $rcpt->reference_no }} @endif
                                    </div>
                                </div>
                                <div class="text-right flex-shrink-0">
                                    <div class="text-xs font-bold text-green-700">Rs. {{ number_format($rcpt->amount, 0) }}</div>
                                    @if($rcpt->invoice)
                                        <div class="text-[10px] text-gray-400 font-mono">{{ $rcpt->invoice->invoice_number }}</div>
                                    @endif
                                </div>
                                <div class="opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                                    @if($rcpt->invoice)
                                    <a href="{{ route('billing.receipt', [$rcpt->invoice, $rcpt]) }}" target="_blank"
                                       class="w-7 h-7 flex items-center justify-center rounded hover:bg-green-50 text-gray-400 hover:text-green-600" title="View Receipt">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>{{-- /invoices+receipts grid --}}

            {{-- ── Final Bills ───────────────────────────────────────────── --}}
            @if($allFinalBills->count())
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <span class="section-title">Final Bills</span>
                    <span class="text-xs text-gray-400">{{ $allFinalBills->count() }} bill{{ $allFinalBills->count() > 1 ? 's' : '' }}</span>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($allFinalBills as $fb)
                    <div class="flex items-center gap-4 px-5 py-3 hover:bg-gray-50/60 group">
                        <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="text-sm font-semibold text-gray-800 font-mono">{{ $fb->bill_number }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $fb->generated_date?->format('d M Y') }}
                                @if($fb->invoice) · Inv {{ $fb->invoice->invoice_number ?? '#'.$fb->invoice_id }} @endif
                            </div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-sm font-bold text-purple-700">Rs.  {{ number_format($fb->total_amount ?? 0, 0) }}</div>
                        </div>
                        <div class="opacity-0 group-hover:opacity-100 transition flex-shrink-0">
                            @if($fb->invoice)
                            <a href="{{ route('billing.finalBill', $fb->invoice) }}" target="_blank"
                               class="w-8 h-8 flex items-center justify-center rounded hover:bg-purple-50 text-gray-400 hover:text-purple-600" title="View Final Bill">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </a>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- ── Patient Ledger ────────────────────────────────────────── --}}
            @php
                // Build ledger entries: invoices (debit) + receipts (credit), sorted by date
                $ledgerEntries = collect();

                foreach (($invoices ?? collect()) as $inv) {
                    $ledgerEntries->push([
                        'date'        => $inv->invoice_date,
                        'sort_key'    => $inv->invoice_date?->format('Y-m-d') . '_A_' . $inv->id,
                        'type'        => 'invoice',
                        'ref'         => $inv->invoice_number ?? 'INV-'.$inv->id,
                        'description' => $inv->items->count() . ' item' . ($inv->items->count() !== 1 ? 's' : ''),
                        'debit'       => (float) $inv->total_amount,
                        'credit'      => 0,
                        'status'      => $inv->status,
                        'inv'         => $inv,
                        'rcpt'        => null,
                    ]);

                    foreach ($inv->receipts ?? [] as $rcpt) {
                        $ledgerEntries->push([
                            'date'        => $rcpt->receipt_date,
                            'sort_key'    => $rcpt->receipt_date?->format('Y-m-d') . '_B_' . $rcpt->id,
                            'type'        => 'receipt',
                            'ref'         => $rcpt->receipt_number,
                            'description' => ucfirst(str_replace('_', ' ', $rcpt->payment_mode ?? '')) . ($rcpt->reference_no ? ' · '.$rcpt->reference_no : ''),
                            'debit'       => 0,
                            'credit'      => (float) $rcpt->amount,
                            'status'      => 'paid',
                            'inv'         => $inv,
                            'rcpt'        => $rcpt,
                        ]);
                    }
                }

                $ledgerEntries = $ledgerEntries->sortBy('sort_key')->values();

                // Compute running balance (debit increases balance owed, credit decreases)
                $runningBalance = 0;
                $ledgerRows = [];
                foreach ($ledgerEntries as $entry) {
                    $runningBalance += $entry['debit'] - $entry['credit'];
                    $entry['balance'] = $runningBalance;
                    $ledgerRows[] = $entry;
                }
            @endphp

            @if(count($ledgerRows))
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>
                        <span class="section-title">Ledger</span>
                    </div>
                    <div class="flex items-center gap-3 text-xs">
                        <span class="flex items-center gap-1 text-gray-400"><span class="w-2 h-2 rounded-full bg-red-300 inline-block"></span>Debit</span>
                        <span class="flex items-center gap-1 text-gray-400"><span class="w-2 h-2 rounded-full bg-green-400 inline-block"></span>Credit</span>
                        <span class="font-semibold {{ $runningBalance > 0 ? 'text-red-600' : 'text-green-700' }}">
                            Balance: Rs. {{ number_format(abs($runningBalance), 0) }} {{ $runningBalance > 0 ? 'Due' : ($runningBalance < 0 ? 'Advance' : 'Clear') }}
                        </span>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-100">
                                <th class="text-left px-4 py-2.5 font-medium text-gray-500 w-24">Date</th>
                                <th class="text-left px-3 py-2.5 font-medium text-gray-500">Type</th>
                                <th class="text-left px-3 py-2.5 font-medium text-gray-500">Ref #</th>
                                <th class="text-left px-3 py-2.5 font-medium text-gray-500 hidden sm:table-cell">Description</th>
                                <th class="text-right px-3 py-2.5 font-medium text-red-400">Debit</th>
                                <th class="text-right px-3 py-2.5 font-medium text-green-600">Credit</th>
                                <th class="text-right px-3 py-2.5 font-medium text-gray-500">Balance</th>
                                <th class="text-center px-3 py-2.5 font-medium text-gray-500">Status</th>
                                <th class="px-3 py-2.5 w-8"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            @foreach($ledgerRows as $row)
                            @php
                                $isInvoice = $row['type'] === 'invoice';
                                $rowStatus = $row['status'];
                                $statusBadge = match($rowStatus) {
                                    'paid'      => ['bg-green-50 text-green-700 border-green-200',  'Paid'],
                                    'partial'   => ['bg-amber-50 text-amber-700 border-amber-200',  'Partial'],
                                    'cancelled' => ['bg-gray-100 text-gray-500 border-gray-200',    'Cancelled'],
                                    default     => ['bg-red-50 text-red-600 border-red-200',         'Unpaid'],
                                };
                            @endphp
                            <tr class="hover:bg-gray-50/60 {{ $isInvoice ? '' : 'bg-green-50/20' }}">
                                <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">
                                    {{ $row['date']?->format('d M Y') ?? '—' }}
                                </td>
                                <td class="px-3 py-2.5">
                                    @if($isInvoice)
                                        <span class="inline-flex items-center gap-1 font-semibold text-amber-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/></svg>
                                            Invoice
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 font-semibold text-green-700">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14l2 2 4-4"/><rect x="3" y="5" width="18" height="14" rx="2"/></svg>
                                            Receipt
                                        </span>
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 font-mono font-semibold text-gray-700">
                                    @if($isInvoice)
                                        <a href="{{ route('billing.show', $row['inv']) }}"
                                                class="hover:text-[#6a0f70] hover:underline">{{ $row['ref'] }}</a>
                                    @else
                                        @if($row['rcpt'] && $row['inv'])
                                            <a href="{{ route('billing.receipt', [$row['inv'], $row['rcpt']]) }}" target="_blank"
                                               class="hover:text-green-700 hover:underline">{{ $row['ref'] }}</a>
                                        @else
                                            {{ $row['ref'] }}
                                        @endif
                                    @endif
                                </td>
                                <td class="px-3 py-2.5 text-gray-400 hidden sm:table-cell max-w-[140px] truncate">
                                    {{ $row['description'] }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-semibold {{ $row['debit'] > 0 ? 'text-red-500' : 'text-gray-200' }}">
                                    {{ $row['debit'] > 0 ? 'Rs. '.number_format($row['debit'], 0) : '—' }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-semibold {{ $row['credit'] > 0 ? 'text-green-600' : 'text-gray-200' }}">
                                    {{ $row['credit'] > 0 ? 'Rs. '.number_format($row['credit'], 0) : '—' }}
                                </td>
                                <td class="px-3 py-2.5 text-right font-bold {{ $row['balance'] > 0 ? 'text-red-600' : ($row['balance'] < 0 ? 'text-blue-600' : 'text-green-600') }}">
                                    Rs. {{ number_format(abs($row['balance']), 0) }}
                                    @if($row['balance'] > 0)<span class="text-[9px] font-normal ml-0.5">DR</span>@elseif($row['balance'] < 0)<span class="text-[9px] font-normal ml-0.5">CR</span>@endif
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full border {{ $statusBadge[0] }}">{{ $statusBadge[1] }}</span>
                                </td>
                                <td class="px-3 py-2.5 text-center">
                                    @if($isInvoice)
                                        <a href="{{ route('billing.print', $row['inv']) }}" target="_blank"
                                           class="text-gray-300 hover:text-[#b45309]" title="Print Invoice">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                                        </a>
                                    @elseif($row['rcpt'] && $row['inv'])
                                        <a href="{{ route('billing.receipt', [$row['inv'], $row['rcpt']]) }}" target="_blank"
                                           class="text-gray-300 hover:text-green-600" title="View Receipt">
                                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                        </a>
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="bg-gray-50 border-t-2 border-gray-200">
                                <td colspan="4" class="px-4 py-2.5 text-xs font-semibold text-gray-600">Totals</td>
                                <td class="px-3 py-2.5 text-right text-xs font-bold text-red-600">
                                    Rs. {{ number_format($ledgerEntries->sum('debit'), 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-right text-xs font-bold text-green-600">
                                    Rs. {{ number_format($ledgerEntries->sum('credit'), 0) }}
                                </td>
                                <td class="px-3 py-2.5 text-right text-xs font-bold {{ $runningBalance > 0 ? 'text-red-600' : 'text-green-600' }}">
                                    Rs. {{ number_format(abs($runningBalance), 0) }}
                                    <span class="font-normal text-[9px]">{{ $runningBalance > 0 ? 'DUE' : ($runningBalance < 0 ? 'ADV' : 'NIL') }}</span>
                                </td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
            @endif

        </div>
        {{-- /main column --}}

        {{-- ══ SIDEBAR ══ --}}
        <div class="space-y-3">

            {{-- Payment Summary --}}
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="section-title mb-3">Summary</div>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-500">Total Invoiced</span>
                        <span class="font-semibold">Rs.  {{ number_format($totalBilled, 0) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-500">Collected</span>
                        <span class="font-semibold text-green-700">Rs.  {{ number_format($totalCollected, 0) }}</span>
                    </div>
                    <div class="border-t pt-2 flex justify-between">
                        <span class="text-gray-500">Outstanding</span>
                        <span class="font-bold {{ $totalOutstanding > 0 ? 'text-red-600' : 'text-gray-800' }}">
                            Rs.  {{ number_format($totalOutstanding, 0) }}
                        </span>
                    </div>
                </div>
                @if($totalBilled > 0)
                @php $collectPct = min(100, round(($totalCollected / $totalBilled) * 100)); @endphp
                <div class="mt-3">
                    <div class="flex justify-between text-[10px] text-gray-400 mb-1">
                        <span>Collection rate</span><span>{{ $collectPct }}%</span>
                    </div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full rounded-full {{ $collectPct === 100 ? 'bg-green-500' : 'bg-[#6a0f70]' }}" style="width: {{ $collectPct }}%"></div>
                    </div>
                </div>
                @endif
            </div>

            {{-- Wallet Balance --}}
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="section-title mb-2">Wallet Balance</div>
                <div class="text-2xl font-bold {{ $walletBalance > 0 ? 'text-[#6a0f70]' : 'text-gray-400' }} mb-1">
                    Rs.  {{ number_format($walletBalance, 0) }}
                </div>
                @if($walletBalance > 0)
                <div class="space-y-1 text-xs text-gray-500">
                    @if(($wallet->balance_promotional ?? 0) > 0)
                        <div class="flex justify-between">
                            <span>Promotional</span>
                            <span class="font-medium text-amber-700">Rs.  {{ number_format($wallet->balance_promotional, 0) }}</span>
                        </div>
                    @endif
                    @if(($wallet->balance_permanent ?? 0) > 0)
                        <div class="flex justify-between">
                            <span>Permanent</span>
                            <span class="font-medium text-green-700">Rs.  {{ number_format($wallet->balance_permanent, 0) }}</span>
                        </div>
                    @endif
                </div>
                @else
                    <p class="text-xs text-gray-400">No credits in wallet.</p>
                @endif
            </div>

            {{-- Membership Status (compact) --}}
            <div class="bg-white border border-gray-200 rounded-lg p-4 cursor-pointer hover:border-purple-300 transition"
                 onclick="activeTab='membership'" x-on:click="activeTab='membership'">
                <div class="section-title mb-2">AOCP Membership</div>
                @if($activeMembership ?? null)
                    <div class="flex items-center gap-2 mb-1">
                        <span class="w-2 h-2 rounded-full flex-shrink-0
                            {{ $activeMembership->days_remaining <= 30 ? 'bg-amber-400' : 'bg-green-500' }}"></span>
                        <span class="text-sm font-bold text-gray-800">{{ $activeMembership->plan->plan_name }}</span>
                    </div>
                    <p class="text-xs text-gray-500 mb-1">
                        Expires {{ $activeMembership->end_date->format('d M Y') }}
                    </p>
                    <p class="text-xs {{ $activeMembership->days_remaining <= 30 ? 'text-amber-600 font-semibold' : 'text-gray-400' }}">
                        {{ $activeMembership->days_remaining }} days left
                    </p>
                @else
                    <p class="text-sm font-semibold text-gray-400">Not enrolled</p>
                    <p class="text-xs text-gray-400 mt-0.5">Tap to enroll →</p>
                @endif
            </div>

            {{-- Invoice Status Breakdown --}}
            @if(($invoices ?? collect())->isNotEmpty())
            @php
                $paidCount      = ($invoices ?? collect())->where('status','paid')->count();
                $partialCount   = ($invoices ?? collect())->where('status','partial')->count();
                $draftCount     = ($invoices ?? collect())->whereIn('status',['draft','pending'])->count();
            @endphp
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="section-title mb-3">Invoice Status</div>
                <div class="space-y-2">
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-green-500"></span><span class="text-gray-600">Paid</span></span>
                        <span class="font-semibold text-green-700">{{ $paidCount }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-amber-400"></span><span class="text-gray-600">Partial</span></span>
                        <span class="font-semibold text-amber-600">{{ $partialCount }}</span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-blue-400"></span><span class="text-gray-600">Draft</span></span>
                        <span class="font-semibold text-blue-600">{{ $draftCount }}</span>
                    </div>
                </div>
            </div>
            @endif

            {{-- Quick actions --}}
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="section-title mb-3">Quick Actions</div>
                <div class="space-y-2">
                    <a href="{{ route('billing.create', ['patient_id' => $patient->id]) }}"
                       class="w-full flex items-center gap-2.5 px-3 py-2.5 text-xs font-medium text-gray-700 border border-gray-200 rounded hover:border-[#6a0f70] hover:text-[#6a0f70] hover:bg-[#faf5ff] transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                        New Invoice
                    </a>
                    <a href="{{ route('billing.index') }}"
                       class="w-full flex items-center gap-2.5 px-3 py-2.5 text-xs font-medium text-gray-700 border border-gray-200 rounded hover:border-gray-400 hover:text-gray-800 transition">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        All Invoices
                    </a>
                </div>
            </div>

        </div>
        {{-- /sidebar --}}

    </div>
</div>{{-- /billing wrapper --}}

{{-- ── Invoice Drawer (slide-over) ────────────────────────────────────────── --}}
<div id="invoiceDrawer"
     class="fixed inset-0 z-50 hidden"
     onclick="if(event.target===this)closeInvoiceDrawer()">
    {{-- Backdrop --}}
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
    {{-- Panel --}}
    <div id="invoiceDrawerPanel"
         class="absolute right-0 top-0 h-full w-full max-w-lg bg-white shadow-2xl flex flex-col
                translate-x-full transition-transform duration-300 ease-out">
        {{-- Drawer header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 flex-shrink-0">
            <div>
                <h3 class="font-semibold text-gray-800" id="drawerInvoiceTitle">Invoice</h3>
                <p class="text-xs text-gray-400 mt-0.5">Patient: {{ $patient->name }}</p>
            </div>
            <button onclick="closeInvoiceDrawer()"
                    class="w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 hover:text-gray-600 text-xl leading-none">
                &times;
            </button>
        </div>
        {{-- Drawer content (populated via fetch) --}}
        <div id="invoiceDrawerContent" class="flex-1 overflow-y-auto px-5 py-4">
            <div id="invoiceDrawerLoader" class="flex items-center justify-center py-16">
                <div class="text-center text-gray-400">
                    <svg class="w-6 h-6 animate-spin mx-auto mb-2" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    <p class="text-sm">Loading invoice…</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function openInvoiceDrawer(invoiceId, invoiceRef) {
    const drawer  = document.getElementById('invoiceDrawer');
    const panel   = document.getElementById('invoiceDrawerPanel');
    const content = document.getElementById('invoiceDrawerContent');
    const loader  = document.getElementById('invoiceDrawerLoader');
    const title   = document.getElementById('drawerInvoiceTitle');

    title.textContent = invoiceRef || 'Invoice';
    content.innerHTML = loader.outerHTML; // reset to spinner
    drawer.classList.remove('hidden');
    requestAnimationFrame(() => panel.classList.remove('translate-x-full'));

    fetch('/billing/' + invoiceId + '/panel', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.text())
    .then(html => { content.innerHTML = html; })
    .catch(() => { content.innerHTML = '<p class="text-red-500 text-sm p-4">Failed to load invoice. Please try again.</p>'; });
}

function closeInvoiceDrawer() {
    const panel = document.getElementById('invoiceDrawerPanel');
    const drawer = document.getElementById('invoiceDrawer');
    panel.classList.add('translate-x-full');
    setTimeout(() => drawer.classList.add('hidden'), 300);
}
</script>

{{-- ── Invoice Delete Auth Modal (shared for all invoice rows) ────────────── --}}
<div id="patientDeleteModal"
     class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm px-4">
    <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-5">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-red-700">Delete Invoice</h3>
                <p class="text-xs text-gray-500 mt-0.5">
                    Deleting <strong id="deleteModalRef"></strong> — provide a reason and your password.
                </p>
            </div>
            <button onclick="document.getElementById('patientDeleteModal').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
        </div>
        <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3 text-xs text-red-700">
            Deleted invoices are permanently removed. The action is logged with your name and reason.
        </div>
        <form id="deleteModalForm" method="POST" action="" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Reason for deletion <span class="text-red-500">*</span>
                </label>
                <textarea name="reason" rows="3" required minlength="5"
                          placeholder="e.g. Duplicate invoice, created in error..."
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400"></textarea>
                <p class="text-xs text-gray-400 mt-1">Stored permanently in the audit log.</p>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">
                    Your password <span class="text-red-500">*</span>
                </label>
                <input type="password" name="password" required
                       placeholder="Enter your login password"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-400">
            </div>
            <div class="flex gap-3 pt-1">
                <button type="submit"
                        class="flex-1 py-2.5 bg-red-600 text-white font-medium text-sm rounded-lg hover:bg-red-700">
                    Confirm Delete
                </button>
                <button type="button"
                        onclick="document.getElementById('patientDeleteModal').classList.add('hidden')"
                        class="flex-1 py-2.5 bg-gray-100 text-gray-700 text-sm rounded-lg hover:bg-gray-200">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>
<script>
function openDeleteModal(invoiceId, invoiceRef) {
    document.getElementById('deleteModalRef').textContent = invoiceRef;
    document.getElementById('deleteModalForm').action = '/billing/' + invoiceId + '/delete-auth';
    document.getElementById('patientDeleteModal').classList.remove('hidden');
}
document.getElementById('patientDeleteModal').addEventListener('click', function(e) {
    if (e.target === this) this.classList.add('hidden');
});
</script>

{{-- Close the billing tab div HERE — before the quick pay modal, so the modal is always in the DOM --}}
</div>{{-- /x-show billing --}}

{{-- ══════════════════════════════════════════════════════════
     WALLET TAB — read-only (no Add Credit / no redirects)
     Credit management lives in Finance → Wallet Management
══════════════════════════════════════════════════════════ --}}
<div x-show="activeTab === 'wallet'" style="display:none" class="w-full px-6 py-5">
@php
    $walletTxns = $wallet ? $wallet->transactions()->with('invoice')->orderByDesc('created_at')->limit(20)->get() : collect();
@endphp
    <div class="max-w-4xl mx-auto">

        {{-- Balance cards --}}
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Promotional</div>
                <div class="text-2xl font-bold text-amber-600">
                    Rs. {{ number_format($wallet->balance_promotional ?? 0, 0) }}
                </div>
                <div class="text-xs text-gray-400 mt-1">Expires first · treatment-restricted</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Credit Balance</div>
                <div class="text-2xl font-bold text-purple-700">
                    Rs. {{ number_format($wallet->balance_permanent ?? 0, 0) }}
                </div>
                <div class="text-xs text-gray-400 mt-1">All treatments · optional expiry</div>
            </div>
            <div class="bg-[#6a0f70] rounded-lg p-4 text-center text-white">
                <div class="text-xs uppercase tracking-wide mb-1 opacity-80">Total Balance</div>
                <div class="text-2xl font-bold">
                    Rs. {{ number_format($wallet->balance_total ?? 0, 0) }}
                </div>
                <div class="text-xs mt-1 opacity-70">Available for invoices</div>
            </div>
        </div>

        {{-- Recent activity (read-only) --}}
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50">
                <h3 class="text-sm font-semibold text-gray-700">Recent Wallet Activity</h3>
            </div>

            @if($walletTxns->isEmpty())
                <div class="px-4 py-10 text-center text-sm text-gray-400">
                    No wallet transactions yet.
                </div>
            @else
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Date</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Type</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Notes</th>
                            <th class="text-left px-4 py-2.5 text-xs font-semibold text-gray-500">Expiry</th>
                            <th class="text-right px-4 py-2.5 text-xs font-semibold text-green-600">Credit</th>
                            <th class="text-right px-4 py-2.5 text-xs font-semibold text-red-500">Debit</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($walletTxns as $tx)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                {{ $tx->created_at->format('d M Y') }}
                            </td>
                            <td class="px-4 py-3">
                                @if($tx->credit_type === 'promotional')
                                    <span class="text-xs px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded">Promotional</span>
                                    @if($tx->campaign_name)
                                        <div class="text-xs text-amber-600 mt-0.5">{{ $tx->campaign_name }}</div>
                                    @endif
                                @elseif($tx->source === 'refund')
                                    <span class="text-xs px-1.5 py-0.5 bg-blue-100 text-blue-700 rounded">Refund</span>
                                @elseif($tx->direction === 'credit')
                                    <span class="text-xs px-1.5 py-0.5 bg-purple-100 text-purple-700 rounded">Credit</span>
                                @else
                                    <span class="text-xs px-1.5 py-0.5 bg-red-50 text-red-600 rounded">Used</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-gray-500">{{ $tx->notes ?: '—' }}</td>
                            <td class="px-4 py-3 text-xs text-gray-500 whitespace-nowrap">
                                @if($tx->expiry_date)
                                    {{ $tx->expiry_date->format('d M Y') }}
                                    @if($tx->expiry_date->isPast())
                                        <span class="text-red-400 block text-[10px]">Expired</span>
                                    @endif
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-xs">
                                @if($tx->direction === 'credit')
                                    <span class="font-semibold text-green-600">+Rs. {{ number_format($tx->amount, 0) }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right text-xs">
                                @if($tx->direction === 'debit')
                                    <span class="font-semibold text-red-500">−Rs. {{ number_format($tx->amount, 0) }}</span>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        <p class="text-xs text-gray-400 mt-3 text-center">
            To add credit or view the full ledger, go to <strong>Finance → Wallet Management</strong>.
        </p>
    </div>
</div>{{-- /x-show wallet --}}

@include('patients.partials.membership-tab', [
    'activeMembership'  => $activeMembership  ?? null,
    'membershipHistory' => $membershipHistory  ?? collect(),
    'benefitLogs'       => $benefitLogs        ?? collect(),
])

{{-- Quick Pay Modal — kept outside all tab x-show divs so it's always accessible from any tab --}}
@php
    $unpaidInvoices      = ($invoices ?? collect())->filter(fn($i) => $i->balance_due > 0 && $i->status !== 'cancelled');
    $activeEmiProvidersQp = $activeEmiProviders ?? collect();
@endphp

<div id="quickPayModal"
     class="hidden fixed inset-0 z-[60] flex items-center justify-center bg-black/50 backdrop-blur-sm"
     onclick="if(event.target===this)closeQuickPayModal()">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 flex flex-col max-h-[90vh]">

        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
            <div>
                <h3 class="text-base font-bold text-gray-800">Record Payment</h3>
                <p class="text-xs text-gray-400 mt-0.5">{{ $patient->name }}</p>
            </div>
            <button onclick="closeQuickPayModal()" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>

        <div class="overflow-y-auto flex-1 p-5 space-y-4">

            @if($unpaidInvoices->isEmpty())
                <div class="text-center pt-4 pb-1 text-gray-500">
                    <svg class="mx-auto mb-2 text-green-400" xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <p class="font-semibold">No outstanding invoices</p>
                    <p class="text-xs text-gray-400 mt-1">All settled — you can still take an advance into the wallet.</p>
                </div>

                {{-- Receive Advance Payment (no invoice needed → wallet credit) --}}
                <form method="POST" action="{{ route('finance.wallets.receive-advance', $patient) }}"
                      class="space-y-3 border-t border-gray-100 pt-4 mt-2">
                    @csrf
                    <input type="hidden" name="from_patient" value="{{ $patient->id }}">
                    <p class="text-xs font-semibold text-[#6a0f70] uppercase tracking-wider">Receive Advance Payment</p>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Amount (Rs.) <span class="text-red-500">*</span></label>
                            <input type="number" name="amount" step="0.01" min="1" required
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Mode</label>
                            <select name="payment_mode" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                                <option value="cash">Cash</option>
                                <option value="upi">UPI</option>
                                <option value="card">Credit Card</option>
                                <option value="debit_card">Debit Card</option>
                                <option value="netbanking">Net Banking</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="cheque">Cheque</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                        <input type="date" name="payment_date" value="{{ now()->toDateString() }}" required
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                        <input type="text" name="notes" placeholder="Optional"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-400">
                    </div>
                    <button type="submit" class="w-full py-2.5 bg-green-600 text-white font-medium text-sm rounded-lg hover:bg-green-700">
                        Add Advance to Wallet
                    </button>
                </form>
            @else

            {{-- Step 1: Pick an invoice --}}
            <div id="qpStep1">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Select Invoice to Pay</p>
                <div class="space-y-2" id="qpInvoiceList">
                    @foreach($unpaidInvoices as $uinv)
                    @php
                        $uBadge = match($uinv->status) {
                            'partial' => 'bg-amber-50 text-amber-700 border-amber-200',
                            default   => 'bg-red-50 text-red-600 border-red-200',
                        };
                        $uLabel = $uinv->status === 'partial' ? 'Partial' : 'Unpaid';
                    @endphp
                    <button type="button"
                            onclick="qpSelectInvoice({{ $uinv->id }}, '{{ $uinv->invoice_number }}', {{ $uinv->balance_due }}, '{{ route('billing.payment', $uinv) }}')"
                            class="w-full text-left flex items-center gap-3 px-4 py-3 border border-gray-200 rounded-xl hover:border-green-400 hover:bg-green-50/30 transition group">
                        <div class="w-9 h-9 rounded-lg bg-amber-50 flex items-center justify-center flex-shrink-0">
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#b45309" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 2v20l2-1 2 1 2-1 2 1 2-1 2 1 2-1 2 1V2l-2 1-2-1-2 1-2-1-2 1-2-1-2 1-2-1Z"/>
                                <line x1="16" y1="8" x2="8" y2="8"/><line x1="16" y1="12" x2="8" y2="12"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-sm text-gray-800 font-mono">{{ $uinv->invoice_number }}</span>
                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full border {{ $uBadge }}">{{ $uLabel }}</span>
                            </div>
                            <div class="text-xs text-gray-400 mt-0.5">{{ $uinv->invoice_date?->format('d M Y') }}</div>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="text-sm font-bold text-red-600">Rs. {{ number_format($uinv->balance_due, 0) }}</div>
                            <div class="text-[10px] text-gray-400">due</div>
                        </div>
                        <svg class="text-gray-300 group-hover:text-green-500 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Step 2: Payment form (hidden until invoice selected) --}}
            <div id="qpStep2" class="hidden">
                <div class="flex items-center gap-2 mb-3">
                    <button onclick="qpBackToList()" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                    </button>
                    <div>
                        <span class="text-xs font-semibold text-gray-700" id="qpSelectedInvNum"></span>
                        <span class="text-xs text-gray-400 ml-1">— Balance: <span class="text-red-500 font-bold" id="qpBalanceLabel"></span></span>
                    </div>
                </div>

                <form method="POST" id="qpPayForm">
                    @csrf
                    <input type="hidden" name="from_patient" value="{{ $patient->id }}">
                    <input type="hidden" name="emi_type" id="qpEmiType" value="direct">

                    {{-- Amount + Date --}}
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Amount (Rs. ) *</label>
                            <input type="number" name="amount" id="qpAmount" required min="0.01" step="0.01"
                                   oninput="qpOnAmountChange()"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Date *</label>
                            <input type="date" name="payment_date" id="qpDate" required
                                   value="{{ now()->format('Y-m-d') }}"
                                   class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                        </div>
                    </div>

                    {{-- Mode --}}
                    <div class="mb-3">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Payment Mode *</label>
                        <div class="grid grid-cols-4 gap-1.5">
                            @foreach(['cash'=>'Cash','upi'=>'UPI','card'=>'Credit Card','cheque'=>'Cheque','netbanking'=>'NetBank','debit_card'=>'Debit Card','bank_transfer'=>'Transfer','emi'=>'EMI'] as $val => $lbl)
                            <label class="flex items-center justify-center text-center px-1 py-2 text-[10px] font-medium border border-gray-200 rounded-lg cursor-pointer hover:border-green-400 hover:bg-green-50 has-[:checked]:border-green-500 has-[:checked]:bg-green-50 has-[:checked]:font-semibold transition">
                                <input type="radio" name="payment_mode" value="{{ $val }}" class="sr-only" onchange="qpOnModeChange()" {{ $val === 'cash' ? 'checked' : '' }}>
                                {{ $lbl }}
                            </label>
                            @endforeach
                        </div>
                        {{-- hidden select for form submission fallback --}}
                        <select name="payment_mode" id="qpModeSelect" class="hidden">
                            @foreach(['cash','upi','card','cheque','netbanking','debit_card','bank_transfer','emi'] as $v)
                            <option value="{{ $v }}">{{ $v }}</option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Reference --}}
                    <div id="qpFieldRef" class="hidden mb-3">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Reference No. *</label>
                        <input type="text" name="reference_no" placeholder="UTR / Transaction ID"
                               class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                    </div>

                    {{-- CC Fee --}}
                    <div id="qpFieldCC" class="hidden mb-3">
                        <div id="qpCcFeePanel" class="hidden bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-xs">
                            <div class="flex justify-between font-semibold text-amber-800">
                                <span>Convenience Fee ({{ rtrim(rtrim(number_format((float) \App\Models\AppSetting::get('cc_convenience_rate', 2.5), 2), '0'), '.') }}%)</span><span id="qpCcFeeAmt">Rs. 0.00</span>
                            </div>
                            <p class="text-amber-600 mt-0.5">On credit-card payments above Rs. {{ number_format((float) \App\Models\AppSetting::get('cc_convenience_threshold', 10000), 0) }}.</p>
                            <input type="hidden" name="convenience_fee" id="qpConvFee" value="0">
                        </div>
                    </div>

                    {{-- Cheque --}}
                    <div id="qpFieldCheque" class="hidden mb-3 space-y-2">
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Bank Name *</label>
                                <input type="text" name="bank_name" placeholder="HDFC Bank"
                                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Cheque No. *</label>
                                <input type="text" name="cheque_no"
                                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Cheque Date *</label>
                            <input type="date" name="cheque_date"
                                   class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-sm">
                        </div>
                    </div>

                    {{-- EMI (same Direct / Provider form as billing pages) --}}
                    @php
                        $qpActiveEmiProviders = \App\Models\EmiProvider::where('is_active', true)
                            ->with(['schemes' => fn($q) => $q->where('is_active', true)])
                            ->orderBy('name')->get();
                    @endphp
                    <div id="qpFieldEmi" class="hidden mb-3 space-y-3">
                        {{-- Sub-type toggle --}}
                        <div class="flex gap-2">
                            <button type="button" id="qpBtnDirect" onclick="qpSwitchEmi('direct')"
                                    class="flex-1 py-2 text-xs font-semibold rounded-lg border border-purple-600 bg-purple-600 text-white">
                                Direct EMI<br>
                                <span class="font-normal opacity-80">Clinic collects instalments</span>
                            </button>
                            <button type="button" id="qpBtnProvider" onclick="qpSwitchEmi('provider')"
                                    class="flex-1 py-2 text-xs font-semibold rounded-lg border border-purple-200 bg-white text-purple-700 {{ $qpActiveEmiProviders->isEmpty() ? 'opacity-40 cursor-not-allowed' : '' }}"
                                    {{ $qpActiveEmiProviders->isEmpty() ? 'disabled title="No EMI providers configured in Settings"' : '' }}>
                                Provider EMI<br>
                                <span class="font-normal opacity-80">Provider pays clinic upfront</span>
                            </button>
                        </div>

                        {{-- Direct EMI fields --}}
                        <div id="qpDirectFields" class="space-y-2">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Financer / Bank (optional)</label>
                                <input type="text" name="emi_provider" placeholder="e.g. HDFC Card EMI, SBI EMI..."
                                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-purple-400">
                            </div>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Tenure *</label>
                                    <select name="emi_tenure" id="qpEmiTenure" onchange="qpCalcEmi()"
                                            class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                                        <option value="">Select…</option>
                                        @foreach([3,6,9,12,18,24,36,48,60] as $m)
                                        <option value="{{ $m }}">{{ $m }} months</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 mb-1">Interest % p.a.</label>
                                    <input type="number" name="emi_interest_rate" id="qpEmiRate"
                                           value="0" min="0" max="36" step="0.01" oninput="qpCalcEmi()"
                                           class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">First Auto-Debit Date *</label>
                                <input type="date" name="emi_start_date" id="qpEmiStart" onchange="qpCalcEmi()"
                                       class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                            </div>
                            <div id="qpEmiResult" class="hidden bg-purple-50 border border-purple-200 rounded-lg px-3 py-2 text-xs">
                                <div class="flex justify-between font-semibold text-purple-800">
                                    <span>Monthly EMI</span><span id="qpEmiMonthly">—</span>
                                </div>
                                <div class="flex justify-between text-purple-600 mt-0.5">
                                    <span>Total Payable</span><span id="qpEmiTotal">—</span>
                                </div>
                            </div>
                        </div>

                        {{-- Provider EMI fields --}}
                        <div id="qpProviderFields" class="hidden space-y-2">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">EMI Provider *</label>
                                <select id="qpProviderSel" onchange="qpLoadSchemes()"
                                        class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                                    <option value="">— Select Provider —</option>
                                    @foreach($qpActiveEmiProviders as $ep)
                                    <option value="{{ $ep->id }}">{{ $ep->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div id="qpSchemeWrap" class="hidden">
                                <label class="block text-xs text-gray-500 mb-1">Scheme *</label>
                                <select name="emi_provider_scheme_id" id="qpSchemeSel" onchange="qpApplyScheme()"
                                        class="w-full border border-gray-200 rounded-lg px-2 py-1.5 text-xs">
                                    <option value="">— Select Scheme —</option>
                                </select>
                            </div>
                            {{-- Provider breakdown card --}}
                            <div id="qpProviderBreakdown" class="hidden bg-indigo-50 border border-indigo-200 rounded-lg px-3 py-2 text-xs space-y-1">
                                <p class="text-xs font-semibold text-indigo-700 uppercase tracking-wide mb-1">Scheme Breakdown</p>
                                <div class="flex justify-between text-indigo-900">
                                    <span>Patient Monthly EMI</span><span id="qpPbMonthly" class="font-bold">—</span>
                                </div>
                                <div id="qpPbUpfrontRow" class="hidden flex justify-between text-amber-700">
                                    <span>Upfront today (<span id="qpPbUpfrontCount">0</span> EMI)</span>
                                    <span id="qpPbUpfront" class="font-semibold">—</span>
                                </div>
                                <div class="border-t border-indigo-200 pt-1 mt-1 space-y-0.5">
                                    <div class="flex justify-between text-gray-500">
                                        <span>Clinic interest cost</span><span id="qpPbClinicInterest">—</span>
                                    </div>
                                    <div class="flex justify-between text-gray-500">
                                        <span>GST on interest (18%)</span><span id="qpPbGstInterest">—</span>
                                    </div>
                                    <div class="flex justify-between text-gray-600 font-medium">
                                        <span>Provider deduction</span><span id="qpPbDeduction" class="text-red-500">—</span>
                                    </div>
                                </div>
                                <div class="border-t border-indigo-200 pt-1">
                                    <div class="flex justify-between text-green-700 font-semibold">
                                        <span>Clinic net amount</span><span id="qpPbNet">—</span>
                                    </div>
                                </div>
                                <div id="qpPbConvRow" class="hidden border-t border-amber-200 pt-1">
                                    <div class="flex justify-between text-amber-700 font-semibold">
                                        <span>Convenience charge (patient pays)</span><span id="qpPbConv">—</span>
                                    </div>
                                    <div class="flex justify-between text-amber-900 font-bold">
                                        <span>Receipt total</span><span id="qpPbReceiptTotal">—</span>
                                    </div>
                                    <input type="hidden" name="convenience_fee" id="qpProvConvFee" value="0" disabled>
                                </div>
                                <input type="hidden" name="emi_upfront_amount" id="qpProvUpfront" value="0">
                                <p class="text-xs text-indigo-500 mt-1">
                                    Receipt #1 (upfront) is generated now for what the patient pays today. Receipt #2 (settlement) is generated when you click "Mark Provider Payment Received".
                                </p>
                            </div>
                        </div>
                    </div>

                    {{-- Notes --}}
                    <div class="mb-4">
                        <label class="block text-xs font-medium text-gray-500 mb-1">Notes</label>
                        <textarea name="notes" rows="2"
                                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500"></textarea>
                    </div>

                    <button type="submit"
                            class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-semibold text-sm rounded-xl transition">
                        Save Payment
                    </button>
                </form>
            </div>

            @endif
        </div>
    </div>
</div>

<script>
(function() {
    // Configurable in Settings → Billing → Credit Card Convenience Fee
    const CC_LIMIT   = {{ (float) \App\Models\AppSetting::get('cc_convenience_threshold', 10000) }};
    const CC_RATE    = {{ (float) \App\Models\AppSetting::get('cc_convenience_rate', 2.5) / 100 }};
    let qpBalance = 0;

    window.openQuickPayModal = function() {
        document.getElementById('quickPayModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        // if only one unpaid invoice, auto-select it
        const btns = document.querySelectorAll('#qpInvoiceList button');
        if (btns.length === 1) btns[0].click();
    };

    window.closeQuickPayModal = function() {
        document.getElementById('quickPayModal').classList.add('hidden');
        document.body.style.overflow = '';
        qpBackToList();
    };

    window.qpSelectInvoice = function(id, num, balance, actionUrl) {
        qpBalance = balance;
        document.getElementById('qpPayForm').action = actionUrl;
        document.getElementById('qpAmount').value = balance;
        document.getElementById('qpSelectedInvNum').textContent = num;
        document.getElementById('qpBalanceLabel').textContent = 'Rs. ' + balance.toLocaleString('en-IN');
        document.getElementById('qpStep1').classList.add('hidden');
        document.getElementById('qpStep2').classList.remove('hidden');
        // sync radio with hidden select
        document.querySelector('input[name="payment_mode"][value="cash"]').checked = true;
        // reset EMI provider state (scheme breakdown depends on the selected invoice)
        const provSel = document.getElementById('qpProviderSel');
        if (provSel) {
            provSel.value = '';
            document.getElementById('qpSchemeSel').innerHTML = '<option value="">— Select Scheme —</option>';
            document.getElementById('qpSchemeWrap').classList.add('hidden');
            document.getElementById('qpProviderBreakdown').classList.add('hidden');
            document.getElementById('qpEmiType').value = 'direct';
        }
        qpOnModeChange();
    };

    window.qpBackToList = function() {
        const s1 = document.getElementById('qpStep1');
        const s2 = document.getElementById('qpStep2');
        if (s1 && s2) { s1.classList.remove('hidden'); s2.classList.add('hidden'); }
    };

    window.qpOnModeChange = function() {
        const checked = document.querySelector('input[name="payment_mode"]:checked');
        const mode = checked ? checked.value : 'cash';
        // sync hidden select
        const sel = document.getElementById('qpModeSelect');
        if (sel) sel.value = mode;
        const ph = id => { const e = document.getElementById(id); if(e) e.classList.add('hidden'); };
        const ps = id => { const e = document.getElementById(id); if(e) e.classList.remove('hidden'); };
        ph('qpFieldRef'); ph('qpFieldCC'); ph('qpFieldCheque'); ph('qpFieldEmi');
        if (['upi','netbanking','bank_transfer'].includes(mode)) ps('qpFieldRef');
        if (mode === 'card')   { ps('qpFieldCC'); qpOnAmountChange(); }
        if (mode === 'cheque') ps('qpFieldCheque');
        if (mode === 'emi')    { ps('qpFieldEmi'); qpSwitchEmi('direct'); }
        else {
            // EMI hidden → make sure provider conv-fee input can't override the CC fee input
            const pcf = document.getElementById('qpProvConvFee');
            if (pcf) { pcf.disabled = true; pcf.value = 0; }
        }
    };

    // ── EMI sub-type toggle (Direct vs Provider) — same as billing pages ──
    window.qpSwitchEmi = function(type) {
        document.getElementById('qpEmiType').value = type;
        const onActive = ['border-purple-600','bg-purple-600','text-white'];
        const onIdle   = ['border-purple-200','bg-white','text-purple-700'];
        const d = document.getElementById('qpBtnDirect');
        const p = document.getElementById('qpBtnProvider');
        const ph = id => { const e=document.getElementById(id); if(e) e.classList.add('hidden'); };
        const ps = id => { const e=document.getElementById(id); if(e) e.classList.remove('hidden'); };
        const pcf = document.getElementById('qpProvConvFee');
        if (type === 'direct') {
            onActive.forEach(c=>d.classList.add(c));   onIdle.forEach(c=>d.classList.remove(c));
            onIdle.forEach(c=>p.classList.add(c));     onActive.forEach(c=>p.classList.remove(c));
            ps('qpDirectFields'); ph('qpProviderFields');
            if (pcf) { pcf.disabled = true; pcf.value = 0; }
        } else {
            onActive.forEach(c=>p.classList.add(c));   onIdle.forEach(c=>p.classList.remove(c));
            onIdle.forEach(c=>d.classList.add(c));     onActive.forEach(c=>d.classList.remove(c));
            ph('qpDirectFields'); ps('qpProviderFields');
            if (pcf) pcf.disabled = false;
        }
    };

    // ── Provider EMI: load schemes via AJAX (same endpoint as billing pages) ──
    let _qpSchemes = [];
    window.qpLoadSchemes = function() {
        const pid = document.getElementById('qpProviderSel').value;
        const ph = id => { const e=document.getElementById(id); if(e) e.classList.add('hidden'); };
        const ps = id => { const e=document.getElementById(id); if(e) e.classList.remove('hidden'); };
        ph('qpSchemeWrap'); ph('qpProviderBreakdown'); _qpSchemes = [];
        if (!pid) return;
        const url = '{{ route("settings.emi.schemes.ajax") }}?provider_id=' + pid + '&invoice_total=' + qpBalance;
        fetch(url, {headers:{'X-Requested-With':'XMLHttpRequest'}})
            .then(r => r.json())
            .then(data => {
                _qpSchemes = data;
                const sel = document.getElementById('qpSchemeSel');
                sel.innerHTML = '<option value="">— Select Scheme —</option>';
                data.forEach(s => {
                    const o = document.createElement('option');
                    o.value = s.id;
                    o.textContent = s.scheme_name + ' · ' + s.tenure_months + 'M';
                    sel.appendChild(o);
                });
                ps('qpSchemeWrap');
            });
    };

    window.qpApplyScheme = function() {
        const sid = document.getElementById('qpSchemeSel').value;
        const ph = id => { const e=document.getElementById(id); if(e) e.classList.add('hidden'); };
        const ps = id => { const e=document.getElementById(id); if(e) e.classList.remove('hidden'); };
        ph('qpProviderBreakdown');
        if (!sid) return;
        const s = _qpSchemes.find(x => String(x.id) === String(sid));
        if (!s) return;
        const fmt = v => 'Rs. ' + parseFloat(v).toFixed(2);
        document.getElementById('qpPbMonthly').textContent = fmt(s.patient_monthly_emi);
        if (s.upfront_emis > 0) {
            document.getElementById('qpPbUpfrontCount').textContent = s.upfront_emis;
            document.getElementById('qpPbUpfront').textContent = fmt(s.patient_upfront_amount);
            ps('qpPbUpfrontRow');
        } else { ph('qpPbUpfrontRow'); }
        document.getElementById('qpPbClinicInterest').textContent = fmt(s.clinic_interest_cost ?? 0);
        document.getElementById('qpPbGstInterest').textContent    = fmt(s.gst_on_interest ?? 0);
        document.getElementById('qpPbDeduction').textContent      = fmt(s.provider_deduction ?? 0);
        document.getElementById('qpPbNet').textContent            = fmt(s.clinic_net_amount);
        if (s.pass_cost_to_patient && s.convenience_charge > 0) {
            document.getElementById('qpPbConv').textContent         = fmt(s.convenience_charge);
            document.getElementById('qpPbReceiptTotal').textContent = fmt((parseFloat(s.patient_upfront_amount)||0) + parseFloat(s.convenience_charge));
            document.getElementById('qpProvConvFee').value          = s.convenience_charge;
            ps('qpPbConvRow');
        } else {
            document.getElementById('qpProvConvFee').value = 0;
            ph('qpPbConvRow');
        }
        document.getElementById('qpProvUpfront').value = s.patient_upfront_amount || 0;
        ps('qpProviderBreakdown');
    };

    window.qpOnAmountChange = function() {
        const checked = document.querySelector('input[name="payment_mode"]:checked');
        if (!checked || checked.value !== 'card') return;
        const amt  = parseFloat(document.getElementById('qpAmount').value) || 0;
        const ph = id => { const e=document.getElementById(id); if(e) e.classList.add('hidden'); };
        const ps = id => { const e=document.getElementById(id); if(e) e.classList.remove('hidden'); };
        if (amt > CC_LIMIT) {
            const fee = Math.round(amt * CC_RATE * 100) / 100;
            document.getElementById('qpCcFeeAmt').textContent = 'Rs. ' + fee.toFixed(2);
            document.getElementById('qpConvFee').value = fee;
            ps('qpCcFeePanel');
        } else {
            ph('qpCcFeePanel');
            document.getElementById('qpConvFee').value = 0;
        }
    };

    window.qpCalcEmi = function() {
        const P = parseFloat(document.getElementById('qpAmount').value) || 0;
        const n = parseInt(document.getElementById('qpEmiTenure').value) || 0;
        const r = parseFloat(document.getElementById('qpEmiRate').value) || 0;
        const s = document.getElementById('qpEmiStart').value;
        const res = document.getElementById('qpEmiResult');
        if (!P || !n || !s) { res.classList.add('hidden'); return; }
        let emi = r <= 0
            ? Math.round(P / n * 100) / 100
            : (() => { const mr=r/100/12; const f=Math.pow(1+mr,n); return Math.round(P*mr*f/(f-1)*100)/100; })();
        document.getElementById('qpEmiMonthly').textContent = 'Rs. ' + emi.toFixed(2);
        document.getElementById('qpEmiTotal').textContent   = 'Rs. ' + (emi * n).toFixed(2);
        res.classList.remove('hidden');
    };

    // Make radio clicks trigger mode change
    document.querySelectorAll('input[name="payment_mode"]').forEach(r => {
        r.addEventListener('change', qpOnModeChange);
    });
})();
</script>

{{-- ════════════════════════════════════
     WALLET TAB
════════════════════════════════════ --}}
<div x-show="activeTab === 'wallet'" style="display:none" class="w-full px-6 py-5">
    <div class="max-w-3xl mx-auto">

        {{-- Balance summary cards --}}
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Promotional</div>
                <div class="text-2xl font-bold text-amber-600">Rs. {{ number_format($wallet->balance_promotional ?? 0, 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">Expires first · treatment-restricted</div>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                <div class="text-xs text-gray-500 uppercase tracking-wide mb-1">Credit Balance</div>
                <div class="text-2xl font-bold text-purple-700">Rs. {{ number_format($wallet->balance_permanent ?? 0, 0) }}</div>
                <div class="text-xs text-gray-400 mt-1">All treatments · optional expiry</div>
            </div>
            <div class="bg-[#6a0f70] rounded-lg p-4 text-center text-white">
                <div class="text-xs uppercase tracking-wide mb-1 opacity-80">Total Balance</div>
                <div class="text-2xl font-bold">Rs. {{ number_format($wallet->balance_total ?? 0, 0) }}</div>
                <div class="text-xs mt-1 opacity-70">Available for invoices</div>
            </div>
        </div>

        {{-- Action buttons --}}
        <div class="flex gap-3 mb-6">
            <a href="{{ route('finance.wallets.credit-form', $patient) }}"
               class="bg-[#6a0f70] text-white text-sm px-4 py-2 rounded hover:bg-[#380740] transition-colors font-medium">
                + Add Credit
            </a>
            <a href="{{ route('finance.wallets.show', $patient) }}"
               class="bg-white border border-gray-200 text-gray-700 text-sm px-4 py-2 rounded hover:bg-gray-50 transition-colors">
                View Full Ledger →
            </a>
        </div>

        {{-- Recent transactions --}}
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">Recent Wallet Activity</h3>
            </div>
            @php
                $recentWalletTx = isset($wallet) ? $wallet->transactions()->with('invoice')->limit(10)->get() : collect();
            @endphp
            @if($recentWalletTx->isEmpty())
                <div class="px-4 py-8 text-center text-gray-400 text-sm">No wallet transactions yet.</div>
            @else
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500">Date</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500">Type</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500">Details</th>
                            <th class="text-left px-4 py-2 text-xs font-medium text-gray-500">Expiry</th>
                            <th class="text-right px-4 py-2 text-xs font-medium text-gray-500">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        @foreach($recentWalletTx as $tx)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2.5 text-gray-500 text-xs">{{ $tx->created_at->format('d M Y') }}</td>
                                <td class="px-4 py-2.5">
                                    @if($tx->credit_type === 'promotional')
                                        <span class="text-xs px-1.5 py-0.5 bg-amber-100 text-amber-700 rounded">Promo</span>
                                        @if($tx->campaign_name)
                                            <div class="text-xs text-amber-600 mt-0.5">{{ $tx->campaign_name }}</div>
                                        @endif
                                    @else
                                        <span class="text-xs px-1.5 py-0.5 bg-purple-100 text-purple-700 rounded">Credit</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-gray-500">
                                    @if($tx->credit_type === 'promotional' && $tx->applicable_treatments !== null)
                                        {{ $tx->applicableTreatmentsLabel() }}
                                    @elseif($tx->notes)
                                        {{ $tx->notes }}
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-xs text-gray-500">
                                    @if($tx->expiry_date)
                                        {{ $tx->expiry_date->format('d M Y') }}
                                        @if($tx->isExpired())<span class="text-red-400 ml-1">Expired</span>@endif
                                    @else
                                        <span class="text-gray-300">No expiry</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <span class="font-semibold text-sm {{ $tx->direction === 'credit' ? 'text-green-600' : 'text-red-500' }}">
                                        {{ $tx->direction === 'credit' ? '+' : '−' }}Rs. {{ number_format($tx->amount, 0) }}
                                    </span>
                                    @if($tx->direction === 'credit' && $tx->credit_type === 'permanent')
                                        <a href="{{ route('finance.wallets.credit-note', [$patient, $tx]) }}"
                                           target="_blank"
                                           class="block text-xs text-[#6a0f70] hover:underline mt-0.5">Credit Note ↗</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @if($recentWalletTx->count() >= 10)
                    <div class="px-4 py-2 border-t border-gray-100 text-center">
                        <a href="{{ route('finance.wallets.show', $patient) }}"
                           class="text-xs text-[#6a0f70] hover:underline">View all transactions →</a>
                    </div>
                @endif
            @endif
        </div>
    </div>
</div>{{-- /wallet tab --}}

{{-- ════════════════════════════════════
     DOCUMENTS TAB
     Phase 1: UI Shell — see patients/partials/documents-tab.blade.php
════════════════════════════════════ --}}
@include('patients.partials.documents-tab')

{{-- OLD STUB REMOVED — replaced by @include above (Phase 1) --}}
{{-- DEAD CODE BELOW: kept only to preserve closing tag balance --}}
{{-- TODO Phase 7: Delete this comment block entirely once wired to real data --}}
<div x-show="false" style="display:none" aria-hidden="true"
     x-data="{ showUploadForm: false, docCategory: 'All', uploading: false, uploadError: '' }">
<div>
    <div class="flex items-center justify-between mb-4">
        <div><h2 class="text-base font-bold text-gray-800">Documents</h2><p class="text-xs text-gray-400 mt-0.5">X-rays, scans, consent forms, prescriptions, invoices</p></div>
        <button @click="showUploadForm = !showUploadForm"
                :class="showUploadForm ? 'bg-[#380740]' : 'bg-[#6a0f70] hover:bg-[#380740]'"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-sm text-white transition rounded-lg">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Upload Document
        </button>
    </div>

    {{-- Upload form --}}
    <div x-show="showUploadForm"
         x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         class="bg-white border border-[#6a0f70]/30 rounded-xl p-5 mb-4 shadow-sm">
        <h3 class="text-sm font-bold text-gray-800 mb-4">Upload Document</h3>
        <form method="POST" action="{{ route('clinical-files.store', $patient) }}" enctype="multipart/form-data"
              @submit.prevent="
                uploading = true; uploadError = '';
                const fd = new FormData($el);
                fetch('{{ route('clinical-files.store', $patient) }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json' },
                    body: fd
                }).then(r => r.json()).then(d => {
                    if (d.success) { showUploadForm = false; window.location.reload(); }
                    else { uploadError = d.message || 'Upload failed.'; }
                }).catch(() => { uploadError = 'Upload failed. Please try again.'; })
                .finally(() => { uploading = false; })
              ">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Category *</label>
                    <select name="category" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                        @foreach(['X-ray','CBCT','IOPA','Photo','Lab Report','Consent Form','Prescription','Invoice','Other'] as $cat)
                        <option>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Title / Label</label>
                    <input type="text" name="title" placeholder="e.g. Pre-treatment OPG" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">File *</label>
                    <input type="file" name="file" required accept=".jpg,.jpeg,.png,.pdf,.dcm,.doc,.docx"
                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70] file:mr-3 file:py-1 file:px-3 file:border-0 file:text-xs file:font-semibold file:bg-[#f5eef9] file:text-[#6a0f70]">
                    <p class="text-[10px] text-gray-400 mt-1">Accepted: JPG, PNG, PDF, DICOM, Word. Max 20MB.</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Notes</label>
                    <textarea name="notes" rows="2" placeholder="Optional notes about this document…" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
                </div>
            </div>
            <div x-show="uploadError" class="mb-3 text-xs text-red-600 bg-red-50 border border-red-100 rounded px-3 py-2" x-text="uploadError"></div>
            <div class="flex gap-2 justify-end">
                <button type="button" @click="showUploadForm=false" class="px-4 py-2 text-xs border border-gray-200 text-gray-500 rounded-lg hover:bg-gray-50">Cancel</button>
                <button type="submit" :disabled="uploading" class="px-5 py-2 text-xs bg-[#6a0f70] text-white hover:bg-[#380740] disabled:opacity-60 rounded-lg font-semibold transition">
                    <span x-text="uploading ? 'Uploading…' : 'Upload'"></span>
                </button>
            </div>
        </form>
    </div>

    <div class="flex gap-2 mb-4 flex-wrap">
        @foreach(['All','X-rays','CBCT','IOPA','Photos','Lab Reports','Consent Forms','Prescriptions','Invoices'] as $cat)
        <button @click="docCategory='{{ $cat }}'"
                :class="docCategory==='{{ $cat }}' ? 'bg-[#f5eef9] border-[#6a0f70] text-[#6a0f70]' : 'bg-white border-gray-200 text-gray-600 hover:border-[#6a0f70] hover:text-[#6a0f70]'"
                class="px-3 py-1.5 text-xs border transition rounded-full">{{ $cat }}</button>
        @endforeach
    </div>

    @php $patientDocs = $patient->documents ?? collect(); @endphp
    @if($patientDocs->isEmpty())
    <div class="bg-white border border-gray-200 rounded-lg py-20 text-center">
        <div class="w-14 h-14 rounded-full bg-blue-50 flex items-center justify-center mx-auto mb-4">
            <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
        </div>
        <p class="text-sm font-semibold text-gray-600 mb-1">No documents uploaded yet</p>
        <p class="text-xs text-gray-400 mb-4">Upload X-rays, scans, consent forms, and other patient documents.</p>
        <button @click="showUploadForm=true" class="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-[#6a0f70] text-white hover:bg-[#380740] rounded-lg transition font-medium">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            Upload First Document
        </button>
    </div>
    @else
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        @foreach($patientDocs as $doc)
        <div class="bg-white border border-gray-200 rounded-xl p-4 hover:border-[#6a0f70]/40 transition group">
            <div class="flex items-start justify-between gap-2">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center flex-shrink-0">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div>
                        <div class="text-sm font-semibold text-gray-800">{{ $doc->title ?? $doc->original_name }}</div>
                        <div class="text-xs text-gray-400">{{ $doc->category }} · {{ $doc->created_at->format('d M Y') }}</div>
                    </div>
                </div>
                <a href="{{ asset('storage/' . $doc->path) }}" target="_blank" class="p-1.5 rounded text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 transition-colors">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                </a>
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>{{-- /x-data documents --}}
</div>{{-- /x-show documents --}}

{{-- ════════════════════════════════════
     NOTES & LOGS TAB
════════════════════════════════════ --}}
<div x-show="activeTab === 'notes'" style="display:none" class="w-full px-6 py-5">
    <div class="grid grid-cols-1 xl:grid-cols-[1fr_320px] gap-5">
            <div class="space-y-3">
                <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-3">
                    <div class="flex gap-2 flex-wrap">
                        @foreach(['internal'=>'Internal Note','staff'=>'Staff Note','call'=>'Call Log','whatsapp'=>'WhatsApp Log'] as $type => $label)
                        <button type="button" x-on:click="noteType='{{ $type }}'"
                            :class="noteType==='{{ $type }}' ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'bg-white text-gray-500 border-gray-200'"
                            class="px-3 py-1 text-xs font-medium border transition-colors">{{ $label }}</button>
                        @endforeach
                    </div>
                    <textarea x-model="newNote" rows="3" placeholder="Add a note, log a call, or record a message…"
                        dusk="note-input"
                        class="w-full text-sm border border-gray-200 px-3 py-2 resize-none focus:outline-none focus:border-[#6a0f70]"></textarea>
                    <div class="flex items-center justify-between">
                        <span x-show="noteSaveError" x-text="noteSaveError" class="text-xs text-red-500"></span>
                        <button type="button" @click="saveNote()" :disabled="noteSaving" dusk="note-save" class="ml-auto px-4 py-2 text-xs bg-[#6a0f70] text-white hover:bg-[#380740] transition font-semibold disabled:opacity-50" x-text="noteSaving ? 'Saving…' : 'Save Note'"></button>
                    </div>
                </div>
                <template x-if="relationshipNotes.length === 0">
                    <div class="bg-white border border-gray-200 rounded-lg py-14 text-center text-gray-400 text-sm">No notes or logs yet.</div>
                </template>
                <template x-for="note in relationshipNotes" :key="'nl-'+note.id">
                    <div class="bg-white border border-gray-200 rounded-lg p-4">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-xs text-gray-400 font-medium">Internal Note</span>
                            <span class="text-[10px] text-gray-300" x-text="note.created_at ? new Date(note.created_at).toLocaleDateString('en-IN',{day:'numeric',month:'short',year:'numeric'}) : ''"></span>
                        </div>
                        <p class="text-sm text-gray-700 mt-2 leading-relaxed" x-text="note.note"></p>
                    </div>
                </template>
            </div>
            <div class="bg-white border border-gray-200 rounded-lg p-4">
                <div class="section-title mb-3">Audit Trail</div>
                <div class="space-y-2 text-xs text-gray-500">
                    <div class="flex items-start gap-2 py-1.5 border-b border-gray-50">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-400 flex-shrink-0 mt-1.5"></span>
                        <div><span class="font-medium text-gray-700">Patient registered</span><span class="block text-gray-400">{{ $patient->created_at->format('d M Y, h:i A') }}</span></div>
                    </div>
                    @if($patient->last_visit_date)
                    <div class="flex items-start gap-2 py-1.5 border-b border-gray-50">
                        <span class="w-1.5 h-1.5 rounded-full bg-blue-400 flex-shrink-0 mt-1.5"></span>
                        <div><span class="font-medium text-gray-700">Last visit</span><span class="block text-gray-400">{{ \Carbon\Carbon::parse($patient->last_visit_date)->format('d M Y') }}</span></div>
                    </div>
                    @endif
                </div>
            </div>
        </div>{{-- /grid notes --}}
    </div>
{{-- /x-show notes --}}
{{-- Edit Patient Drawer — inside x-data patientProfile() scope --}}
@include('patients.partials.edit-patient-drawer')

</div>{{-- /x-data patientProfile --}}


{{-- ══════════════════════════════════════════════════════════
     PATIENT ACTION MODAL (Deactivate / Delete) — with auth
══════════════════════════════════════════════════════════ --}}
<div id="patient-action-modal"
     class="hidden fixed inset-0 z-[999] flex items-center justify-center"
     style="background:rgba(0,0,0,0.45)">
    <div class="bg-white rounded-xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">

        {{-- Header --}}
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 id="patient-action-title" class="text-base font-bold text-gray-800">Patient Action</h3>
            <button onclick="document.getElementById('patient-action-modal').classList.add('hidden')"
                    class="text-gray-300 hover:text-gray-500 text-xl leading-none">&times;</button>
        </div>

        <form id="patient-action-form" method="POST" action="" class="p-6 space-y-4">
            @csrf
            <input type="hidden" name="_method" id="patient-action-method" value="POST">
            <input type="hidden" id="patient-action-mode" value="deactivate">

            {{-- Warning banner --}}
            <div id="patient-action-deactivate-warn"
                 class="p-3 rounded-lg bg-amber-50 border border-amber-200 text-sm text-amber-800">
                <strong>Deactivating</strong> this patient will mark them as inactive. They will not appear in active patient lists, but their records are preserved. You can reactivate them anytime.
            </div>
            <div id="patient-action-delete-warn"
                 class="hidden p-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-800">
                <strong>Deleting</strong> this patient is a soft delete — data is preserved in the database but the patient will be removed from all lists. This action requires your password to confirm.
            </div>

            {{-- Reason --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Reason <span class="text-red-500">*</span>
                </label>
                <textarea name="reason" rows="3" required minlength="5"
                          placeholder="Describe why this action is being taken…"
                          class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
            </div>

            {{-- Password --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Your Password <span class="text-red-500">*</span>
                    <span class="text-xs font-normal text-gray-400 ml-1">to confirm this action</span>
                </label>
                <input type="password" name="password" required autocomplete="current-password"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-[#6a0f70]">
                @error('password')
                <p class="text-xs text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Actions --}}
            <div class="flex gap-3 pt-2">
                <button type="submit"
                        id="patient-action-submit"
                        class="flex-1 py-2 text-sm font-semibold rounded-lg text-white bg-amber-600 hover:bg-amber-700 transition-colors">
                    Deactivate
                </button>
                <button type="button"
                        onclick="document.getElementById('patient-action-modal').classList.add('hidden')"
                        class="flex-1 py-2 text-sm font-medium rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50">
                    Cancel
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    const modal      = document.getElementById('patient-action-modal');
    const form       = document.getElementById('patient-action-form');
    const modeInput  = document.getElementById('patient-action-mode');
    const methodInput = document.getElementById('patient-action-method');
    const submitBtn  = document.getElementById('patient-action-submit');
    const warnDeact  = document.getElementById('patient-action-deactivate-warn');
    const warnDel    = document.getElementById('patient-action-delete-warn');

    const deactivateUrl = "{{ route('patients.deactivate', $patient) }}";
    const deleteUrl     = "{{ route('patients.destroy', $patient) }}";

    // Watch mode changes and update form accordingly
    const observer = new MutationObserver(() => {
        const mode = modeInput.value;
        if (mode === 'deactivate') {
            form.action   = deactivateUrl;
            methodInput.value = 'POST';
            submitBtn.textContent = 'Deactivate Patient';
            submitBtn.className = submitBtn.className.replace(/bg-\S+/, 'bg-amber-600 hover:bg-amber-700');
            warnDeact.classList.remove('hidden');
            warnDel.classList.add('hidden');
        } else {
            form.action   = deleteUrl;
            methodInput.value = 'DELETE';
            submitBtn.textContent = 'Delete Patient';
            submitBtn.className = submitBtn.className.replace(/bg-amber-600 hover:bg-amber-700/, 'bg-red-600 hover:bg-red-700');
            warnDeact.classList.add('hidden');
            warnDel.classList.remove('hidden');
        }
    });
    if (modeInput) observer.observe(modeInput, { attributes: true, childList: true, characterData: true });
})();
</script>

@endsection

@push('scripts')
<script>
function patientProfile() {
    return {
        activeTab: 'profile',
        editDrawerOpen: false,
        editSaving: false,
        editSaveError: '',
        aocpModalOpen: false,
        showNoteForm: false,
        showOppForm: false,
        noteType: 'internal',

        relationshipNotes: @json($relationshipNotes ?? []),
        newNote: '',
        newNoteTags: [],
        noteSaving: false,
        noteSaveError: '',

        opportunities: @json($opportunities ?? []),
        newOpp: { type:'', status:'prospect', priority:'medium', estimated_value:'', follow_up_date:'' },
        oppSaving: false,
        oppEditId: null,
        oppEditData: {},

        oppTypeColors: {
            implant:          { color:'#6a0f70', bg:'#f5f3ff' },
            aligner:          { color:'#2563eb', bg:'#dbeafe' },
            veneers:          { color:'#0891b2', bg:'#e0f2fe' },
            full_mouth_rehab: { color:'#7c3aed', bg:'#ede9fe' },
            whitening:        { color:'#ca8a04', bg:'#fef9c3' },
            crown:            { color:'#b45309', bg:'#fef3c7' },
            bridge:           { color:'#0d9488', bg:'#ccfbf1' },
            rct:              { color:'#dc2626', bg:'#fee2e2' },
            smile_design:     { color:'#db2777', bg:'#fce7f3' },
            gum_treatment:    { color:'#16a34a', bg:'#dcfce7' },
        },

        oppIcons: {
            implant:'', aligner:'', veneers:'✨', full_mouth_rehab:'',
            whitening:'', crown:'', bridge:'', rct:'',
            smile_design:'', gum_treatment:'',
        },

        init() {
            const hash = window.location.hash.replace('#','');
            const validTabs = ['profile','consultation','treatment-plan','visits','lab','prescriptions','billing','wallet','documents','notes'];
            if (validTabs.includes(hash)) {
                this.activeTab = hash;
            }
        },

        oppStageLabel(s) {
            return {prospect:'Identified',discussed:'Discussed',quoted:'Financial Discussion',accepted:'Planned',completed:'Completed'}[s] || s;
        },

        // Save the Edit Patient drawer (form lives in patients/partials/edit-patient-drawer.blade.php).
        // Two fields need a small transform before they match what PatientController@update expects:
        //   - medical_alert_flags[] + medical_alert_custom  -> single comma-separated medical_alert string
        //   - allergies_text (comma separated)               -> allergies[] array
        async submitEditPatient() {
            const form = document.getElementById('editPatientForm');
            if (!form) return;

            this.editSaving = true;
            this.editSaveError = '';

            const fd = new FormData(form);

            const alertFlags  = fd.getAll('medical_alert_flags[]');
            const customAlert = (fd.get('medical_alert_custom') || '').trim();
            const allAlerts   = customAlert ? [...alertFlags, customAlert] : alertFlags;
            fd.delete('medical_alert_flags[]');
            fd.delete('medical_alert_custom');
            fd.set('medical_alert', allAlerts.join(', '));

            const allergiesText = (fd.get('allergies_text') || '').trim();
            fd.delete('allergies_text');
            allergiesText.split(',').map(a => a.trim()).filter(Boolean).forEach(a => fd.append('allergies[]', a));

            try {
                const r = await fetch('{{ route('patients.update', $patient->id) }}', {
                    method: 'POST', // _method=PATCH is spoofed via the @method('PATCH') hidden field already in the form
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                        'Accept': 'application/json',
                    },
                    body: fd,
                });

                if (!r.ok) {
                    const errBody = await r.json().catch(() => ({}));
                    this.editSaveError = errBody.message
                        || (errBody.errors ? Object.values(errBody.errors).flat().join(' ') : null)
                        || `Save failed (${r.status}). Please check the fields and try again.`;
                    console.error('submitEditPatient HTTP error', r.status, errBody);
                    this.editSaving = false;
                    return;
                }

                // Full reload — simplest way to make every tab (header name, billing,
                // referral display, etc.) reflect the updated patient consistently.
                window.location.reload();
            } catch (e) {
                this.editSaveError = 'Network error. Please check your connection.';
                console.error('submitEditPatient error', e);
                this.editSaving = false;
            }
        },

        toggleNoteTag(t) {
            this.newNoteTags.includes(t)
                ? this.newNoteTags = this.newNoteTags.filter(x => x !== t)
                : this.newNoteTags.push(t);
        },

        async saveNote() {
            if (!this.newNote.trim()) return;
            this.noteSaving = true;
            this.noteSaveError = '';
            try {
                const r = await fetch(`/patients/{{ $patient->id }}/relationship-notes`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ note: this.newNote, tags: this.newNoteTags, type: this.noteType }),
                });
                if (!r.ok) {
                    const errBody = await r.text();
                    this.noteSaveError = `Save failed (${r.status}). Please try again.`;
                    console.error('saveNote HTTP error', r.status, errBody);
                    this.noteSaving = false;
                    return;
                }
                const d = await r.json();
                if (d.success && d.note) {
                    const newEntry = {
                        id: d.note.id,
                        note: d.note.note,
                        note_type: d.note.note_type || 'internal',
                        tags: Array.isArray(d.note.tags) ? d.note.tags : [],
                        created_at: d.note.created_at || new Date().toISOString(),
                    };
                    this.relationshipNotes.unshift(newEntry);
                    this.newNote = '';
                    this.newNoteTags = [];
                    this.showNoteForm = false;
                } else {
                    this.noteSaveError = 'Unexpected response from server.';
                }
            } catch(e) {
                this.noteSaveError = 'Network error. Please check your connection.';
                console.error('saveNote error', e);
            }
            this.noteSaving = false;
        },

        async deleteNote(id) {
            if (!confirm('Delete this note?')) return;
            const r = await fetch(`/patients/{{ $patient->id }}/relationship-notes/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
            });
            if ((await r.json()).success) {
                this.relationshipNotes = this.relationshipNotes.filter(n => n.id !== id);
            }
        },

        async saveOpportunity() {
            if (!this.newOpp.type) return;
            const r = await fetch(`/patients/{{ $patient->id }}/opportunities`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(this.newOpp),
            });
            const d = await r.json();
            if (d.success) {
                this.opportunities.unshift(d.opportunity);
                this.newOpp = { type:'', status:'prospect', priority:'medium', estimated_value:'', follow_up_date:'' };
                this.showOppForm = false;
            }
        },

        openOppEdit(opp) {
            this.oppEditId = opp.id;
            this.oppEditData = { ...opp };
        },

        cancelOppEdit() {
            this.oppEditId = null;
            this.oppEditData = {};
        },

        async saveOppEdit() {
            if (!this.oppEditData.type) return;
            this.oppSaving = true;
            try {
                const r = await fetch(`/patients/{{ $patient->id }}/opportunities/${this.oppEditId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ...this.oppEditData, _method: 'PATCH' }),
                });
                const d = await r.json();
                if (d.success) {
                    const idx = this.opportunities.findIndex(o => o.id === this.oppEditId);
                    if (idx !== -1) this.opportunities[idx] = d.opportunity;
                    this.oppEditId = null;
                    this.oppEditData = {};
                }
            } catch(e) {
                console.error(e);
            } finally {
                this.oppSaving = false;
            }
        },

        async deleteOpp(id) {
            if (!confirm('Delete this opportunity?')) return;
            try {
                await fetch(`/patients/{{ $patient->id }}/opportunities/${id}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ _method: 'DELETE' }),
                });
                this.opportunities = this.opportunities.filter(o => o.id !== id);
            } catch(e) {
                console.error(e);
            }
        },
    }
}
</script>
@endpush
                                                                                                                                                                                                                                                                                                                                    