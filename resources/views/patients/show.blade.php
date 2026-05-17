@extends('layouts.app')

@push('styles')
<style>
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
</style>
@endpush

@section('content')
<div x-data="patientProfile()" x-init="init()" class="bg-[#f3f4f8] min-h-screen">

{{-- ══════════════════════════════════════════════════════════
     HEADER
══════════════════════════════════════════════════════════ --}}
<div class="bg-white border-b border-gray-200 px-6 pt-4 pb-0">

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
            <button x-on:click="editDrawerOpen = true"
                    class="px-4 py-2 text-sm border border-gray-300 text-gray-700 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors bg-white font-medium">
                Edit Patient
            </button>
            <a href="#"
               class="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-[#6a0f70] text-white hover:bg-[#380740] transition-colors font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14"/><path d="M12 5v14"/>
                </svg>
                New Visit
            </a>
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

            <div class="flex items-center gap-4 mt-1 flex-wrap">
                <span class="font-mono text-xs bg-gray-100 px-2 py-0.5 rounded text-gray-500 tracking-wider">
                    PNT-{{ str_pad($patient->id, 7, '0', STR_PAD_LEFT) }}
                </span>
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
                @if($patient->city)
                <span class="flex items-center gap-1 text-sm text-gray-500">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>
                    </svg>
                    {{ collect([$patient->city,$patient->state])->filter()->implode(', ') }}
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
            $totalBilled   = $patient->lifetime_value + $patient->outstanding_balance;
            $collectedPct  = $totalBilled > 0 ? round(($patient->lifetime_value / $totalBilled) * 100, 1) : 0;
            $acceptedOpps  = $opportunities->whereIn('status',['accepted','completed'])->count();
            $totalOpps     = $opportunities->count();
            $acceptPct     = $totalOpps > 0 ? round(($acceptedOpps/$totalOpps)*100) : 0;
        @endphp
        <div class="flex gap-2.5 flex-wrap xl:flex-nowrap flex-shrink-0 xl:ml-2">

            {{-- Total Billed --}}
            <div class="stat-card bg-white border border-gray-200 rounded-lg px-4 py-3 min-w-[120px]">
                <div class="w-7 h-7 rounded-full bg-purple-50 flex items-center justify-center mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                </div>
                <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Total Billed</div>
                <div class="text-sm font-bold text-gray-800">₹ {{ number_format($totalBilled,0) }}</div>
                <a href="#" class="text-[10px] text-[#6a0f70] hover:underline mt-0.5 block">View Details</a>
            </div>

            {{-- Total Collected --}}
            <div class="stat-card bg-white border border-gray-200 rounded-lg px-4 py-3 min-w-[120px]">
                <div class="w-7 h-7 rounded-full bg-blue-50 flex items-center justify-center mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/></svg>
                </div>
                <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Total Collected</div>
                <div class="text-sm font-bold text-gray-800">₹ {{ number_format($patient->lifetime_value,0) }}</div>
                <div class="text-[10px] text-green-600 font-semibold mt-0.5">{{ $collectedPct }}%</div>
            </div>

            {{-- Outstanding --}}
            <div class="stat-card bg-white border border-gray-200 rounded-lg px-4 py-3 min-w-[120px]">
                <div class="w-7 h-7 rounded-full bg-orange-50 flex items-center justify-center mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#ea580c" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                </div>
                <div class="text-[10px] text-gray-400 uppercase tracking-wide mb-0.5">Outstanding</div>
                <div class="text-sm font-bold {{ $patient->outstanding_balance > 0 ? 'text-red-600' : 'text-gray-800' }}">
                    ₹ {{ number_format($patient->outstanding_balance,0) }}
                </div>
                <a href="#" class="text-[10px] text-[#6a0f70] hover:underline mt-0.5 block">View Details</a>
            </div>

            {{-- Recall Status --}}
            <div class="stat-card bg-white border border-gray-200 rounded-lg px-4 py-3 min-w-[120px]">
                <div class="w-7 h-7 rounded-full bg-red-50 flex items-center justify-center mb-2">
                    <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                </div>
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
                @if($patient->next_recall_date)
                    <div class="text-[10px] text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($patient->next_recall_date)->format('d M Y') }}</div>
                @endif
            </div>

        </div>
    </div>

    {{-- Medical alert banner --}}
    @if($patient->medical_alert)
    <div class="mx-0 mb-3 flex items-center gap-2 px-4 py-2.5 bg-red-50 border border-red-200 text-red-700 text-sm rounded">
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
        <strong>Medical Alert:</strong>&nbsp;{{ $patient->medical_alert }}
    </div>
    @endif

    {{-- Tabs --}}
    <div class="flex gap-0 -mb-px border-b border-gray-200 mt-1">
        @foreach([
            'profile'       => 'Profile',
            'consultation'  => 'Consultation',
            'followup'      => 'Follow-up',
            'visits'        => 'Treatment Visits',
        ] as $tab => $label)
        <button
            x-on:click="activeTab = '{{ $tab }}'"
            :class="activeTab === '{{ $tab }}'
                ? 'border-b-2 border-[#6a0f70] text-[#6a0f70] font-semibold'
                : 'border-b-2 border-transparent text-gray-500 hover:text-gray-700'"
            class="px-6 py-3 text-sm transition-colors whitespace-nowrap">
            {{ $label }}
        </button>
        @endforeach
    </div>
</div>
{{-- /header --}}


{{-- ══════════════════════════════════════════════════════════
     PROFILE TAB  (was "Consultation" tab — patient details)
══════════════════════════════════════════════════════════ --}}
{{-- ══════════════════════════════════════════════════════════
     PROFILE TAB — 50/50 layout: Patient Details left | Visit Log right
══════════════════════════════════════════════════════════ --}}
<div x-show="activeTab === 'profile'" class="max-w-[1440px] mx-auto px-6 py-5">
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
                                    'Date of Birth'   => $patient->date_of_birth ? \Carbon\Carbon::parse($patient->date_of_birth)->format('d M Y') : null,
                                    'Occupation'      => $patient->occupation,
                                    'Address'         => collect([$patient->address,$patient->city,$patient->state])->filter()->implode(', ') ?: null,
                                    'Medical Alerts'  => $patient->medical_alert ?: 'No Known Allergies',
                                    'Habits'          => $patient->habits ? (is_array($patient->habits) ? implode(', ', $patient->habits) : $patient->habits) : null,
                                    'Allergies'       => $patient->allergies ? (is_array($patient->allergies) ? implode(', ', $patient->allergies) : $patient->allergies) : null,
                                    'Chief Complaint' => $patient->chief_complaint,
                                    'Referred By'     => $patient->referred_by,
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
                            <div class="flex gap-2">
                                <button x-on:click="saveNote()" class="px-3 py-1.5 text-xs bg-[#380740] text-white hover:bg-[#6a0f70] rounded">Save</button>
                                <button x-on:click="showNoteForm=false;newNote='';newNoteTags=[]" class="px-3 py-1.5 text-xs border border-gray-200 text-gray-500 rounded">Cancel</button>
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
                <div class="px-5 py-4 border-t border-gray-100 bg-gray-50/60">
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
                            <span class="tag-pill" style="background:#fef3c7;color:#92400e;border-color:#fcd34d;">⭐ High Value</span>
                        @endif
                        @if($patient->referred_by || $patient->source === 'Referral')
                            <span class="tag-pill" style="background:#ede9fe;color:#5b21b6;border-color:#c4b5fd;">👥 Referral Patient</span>
                        @endif
                        <template x-for="note in relationshipNotes" :key="'tn-'+note.id">
                            <template x-for="tag in (note.tags||[])" :key="tag">
                                <span class="tag-pill" style="background:#f0fdf4;color:#166534;border-color:#bbf7d0;"
                                      x-text="tag.replace(/\b\w/g,c=>c.toUpperCase())"></span>
                            </template>
                        </template>
                        <template x-if="opportunities.length === 0 && relationshipNotes.length === 0">
                            <span class="text-xs text-gray-400 py-1 italic">No tags yet.</span>
                        </template>
                    </div>
                </div>
            </div>

            {{-- Treatment Opportunities --}}
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <span class="section-title">Treatment Opportunities / Potential Tags</span>
                    <button x-on:click="showOppForm = !showOppForm"
                            class="text-xs text-[#6a0f70] border border-[#6a0f70]/30 px-3 py-1.5 hover:bg-[#f5eef9] transition-colors font-medium">
                        + Add Opportunity
                    </button>
                </div>

                {{-- Add form --}}
                <div x-show="showOppForm" x-collapse class="border-b border-gray-100 bg-gray-50">
                    <div class="px-5 py-4 grid grid-cols-2 md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Treatment Type</label>
                            <select x-model="newOpp.type" class="w-full text-sm border border-gray-200 px-3 py-2 bg-white rounded focus:outline-none focus:border-[#6a0f70]">
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
                            <label class="block text-xs text-gray-500 mb-1">Est. Value (₹)</label>
                            <input type="number" x-model="newOpp.estimated_value" placeholder="0"
                                   class="w-full text-sm border border-gray-200 px-3 py-2 rounded focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Next Follow-up</label>
                            <input type="date" x-model="newOpp.follow_up_date"
                                   class="w-full text-sm border border-gray-200 px-3 py-2 rounded focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div class="flex items-end gap-2">
                            <button x-on:click="saveOpportunity()" class="flex-1 py-2 text-xs bg-[#380740] text-white hover:bg-[#6a0f70] rounded">Save</button>
                            <button x-on:click="showOppForm=false" class="flex-1 py-2 text-xs border border-gray-200 text-gray-500 rounded hover:bg-gray-50">Cancel</button>
                        </div>
                    </div>
                </div>

                {{-- Opportunity rows --}}
                <div class="p-4 space-y-2">
                    <template x-if="opportunities.length === 0">
                        <p class="text-sm text-gray-400 text-center py-6">No treatment opportunities tracked yet.</p>
                    </template>
                    <template x-for="(opp, idx) in opportunities" :key="opp.id">
                        <div class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg hover:border-[#6a0f70]/40 hover:bg-[#faf5ff] transition-all group cursor-pointer">
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
                                        <span> · Next: <span x-text="formatDate(opp.follow_up_date)"></span></span>
                                    </template>
                                </div>
                            </div>
                            <template x-if="opp.estimated_value">
                                <div class="text-sm font-bold text-gray-700 flex-shrink-0"
                                     x-text="'₹ '+Number(opp.estimated_value).toLocaleString('en-IN')"></div>
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

            {{-- Visit Log --}}
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <span class="section-title">Visit Log / Timeline</span>
                    <button x-on:click="activeTab='visits'" class="text-xs text-[#6a0f70] hover:underline font-medium">View All Visits</button>
                </div>
                @if($recentVisits->isEmpty())
                    <div class="py-12 text-center text-sm text-gray-400">No visits yet.</div>
                @else
                <div class="divide-y divide-gray-100">
                    @foreach($recentVisits->take(6) as $visit)
                    @php
                        $vt = $visit->type ?? 'treatment';
                        $configs = [
                            'consultation' => ['border'=>'#7c3aed','bg'=>'bg-purple-100','color'=>'#7c3aed','typebadge'=>'bg-purple-50 text-purple-700'],
                            'emergency'    => ['border'=>'#dc2626','bg'=>'bg-red-100',   'color'=>'#dc2626','typebadge'=>'bg-red-50 text-red-600'],
                            'followup'     => ['border'=>'#ea580c','bg'=>'bg-orange-100','color'=>'#ea580c','typebadge'=>'bg-orange-50 text-orange-600'],
                            'treatment'    => ['border'=>'#16a34a','bg'=>'bg-green-100', 'color'=>'#16a34a','typebadge'=>'bg-green-50 text-green-700'],
                        ];
                        $vc = $configs[$vt] ?? $configs['treatment'];
                    @endphp
                    <div class="visit-row flex cursor-pointer group" style="border-left:3px solid {{ $vc['border'] }}">
                        <div class="w-14 flex-shrink-0 flex flex-col items-center justify-center py-4 px-2 bg-gray-50/70 border-r border-gray-100">
                            <div class="text-lg font-bold text-gray-800 leading-none">{{ $visit->appointment_date?->format('d') ?? '—' }}</div>
                            <div class="text-[10px] text-gray-400 uppercase tracking-wide">{{ $visit->appointment_date?->format('M') ?? '' }}</div>
                            <div class="text-[10px] text-gray-400">{{ $visit->appointment_date?->format('Y') ?? '' }}</div>
                        </div>
                        <div class="flex-shrink-0 flex items-center px-3">
                            <div class="timeline-icon {{ $vc['bg'] }}" style="color:{{ $vc['color'] }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    @if($vt==='emergency') <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>
                                    @elseif($vt==='consultation') <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                                    @elseif($vt==='followup') <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/>
                                    @else <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                    @endif
                                </svg>
                            </div>
                        </div>
                        <div class="flex-1 min-w-0 py-3 pr-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-800 text-sm leading-tight truncate">
                                        {{ $visit->treatment?->name ?? ucfirst($vt).' Visit' }}
                                    </div>
                                    @if($visit->chief_complaint ?? false)
                                        <div class="text-xs text-gray-500 mt-0.5 truncate">{{ $visit->chief_complaint }}</div>
                                    @endif
                                    @if($visit->doctor?->name ?? false)
                                        <div class="text-xs text-gray-400 mt-1">{{ $visit->doctor->name }}</div>
                                    @endif
                                </div>
                                <div class="flex-shrink-0 text-right space-y-1">
                                    <span class="inline-block px-2 py-0.5 text-[10px] rounded-full font-medium {{ $vc['typebadge'] }}">{{ ucfirst($vt) }}</span>
                                    @if($visit->amount ?? false)
                                        <div class="text-sm font-bold text-gray-800">₹ {{ number_format($visit->amount,0) }}</div>
                                    @endif
                                    <span class="inline-block px-2 py-0.5 text-[10px] rounded-full font-medium
                                        {{ $visit->status==='completed' ? 'bg-green-50 text-green-700' : ($visit->status==='in_chair' ? 'bg-blue-50 text-blue-600' : 'bg-gray-100 text-gray-500') }}">
                                        {{ ucfirst(str_replace('_',' ',$visit->status ?? 'Scheduled')) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between">
                    <span class="text-xs text-gray-400">Showing {{ min($recentVisits->count(),6) }} of {{ $recentVisits->count() }} visits</span>
                    <button x-on:click="activeTab='visits'" class="text-xs text-[#6a0f70] hover:underline font-medium">View Full Timeline</button>
                </div>
                @endif
            </div>

            {{-- Quick Actions --}}
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <div class="px-5 py-3.5 border-b border-gray-100">
                    <span class="section-title">Quick Actions</span>
                </div>
                <div class="p-3 space-y-2">
                    <div class="grid grid-cols-3 gap-2">
                        @foreach([
                            ['Add Consultation',    '#7c3aed','#f5f3ff','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>'],
                            ['Add Follow-up',       '#ea580c','#fff7ed','<rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/>'],
                            ['Add Treatment Visit', '#16a34a','#f0fdf4','<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>'],
                        ] as [$label,$color,$bg,$path])
                        <button class="flex flex-col items-center justify-center gap-2 py-4 px-2 border border-gray-200 rounded-lg transition-all"
                                onmouseover="this.style.borderColor='{{ $color }}';this.style.background='{{ $bg }}'"
                                onmouseout="this.style.borderColor='#e5e7eb';this.style.background='white'">
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center" style="background:{{ $bg }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                     fill="none" stroke="{{ $color }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $path !!}</svg>
                            </div>
                            <span class="text-[11px] font-medium text-gray-600 text-center leading-tight">{{ $label }}</span>
                        </button>
                        @endforeach
                    </div>
                    @php
                        $sec = [
                            ['Upload X-ray / Scan',   '#2563eb','#eff6ff','<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>'],
                            ['Create Treatment Plan', '#4f46e5','#eef2ff','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
                            ['Send Recall / Message', '#16a34a','#f0fdf4','<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.1 12 19.79 19.79 0 0 1 1.03 3.33 2 2 0 0 1 3 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 21 16.92z"/>'],
                            ['Add Task',              '#0d9488','#f0fdfa','<path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z"/><path d="m9 12 2 2 4-4"/>'],
                            ['Print Consultation',    '#475569','#f8fafc','<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>'],
                            ['Print Treatment Plan',  '#475569','#f8fafc','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
                        ];
                    @endphp
                    @foreach(array_chunk($sec,3) as $row)
                    <div class="grid grid-cols-3 gap-2">
                        @foreach($row as [$label,$color,$bg,$path])
                        <button class="flex flex-col items-center justify-center gap-1.5 py-3 px-2 border border-gray-200 rounded-lg transition-all"
                                onmouseover="this.style.borderColor='{{ $color }}';this.style.background='{{ $bg }}'"
                                onmouseout="this.style.borderColor='#e5e7eb';this.style.background='white'">
                            <div class="w-7 h-7 rounded-md flex items-center justify-center" style="background:{{ $bg }}">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                                     fill="none" stroke="{{ $color }}" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">{!! $path !!}</svg>
                            </div>
                            <span class="text-[10px] font-medium text-gray-500 text-center leading-tight">{{ $label }}</span>
                        </button>
                        @endforeach
                    </div>
                    @endforeach
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
<div x-show="activeTab === 'consultation'" class="max-w-[1440px] mx-auto px-6 py-5">
    <div class="grid grid-cols-1 xl:grid-cols-[1fr_360px] gap-5">

        {{-- LEFT: consultation list / new form --}}
        <div class="space-y-4">

            {{-- Header row --}}
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-gray-800">Consultations</h2>
                    <p class="text-xs text-gray-400 mt-0.5">All consultation records for {{ $patient->name }}</p>
                </div>
                <a href="{{ route('patients.consultations.create', $patient) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-[#6a0f70] text-white hover:bg-[#380740] transition-colors font-medium rounded-sm">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M5 12h14"/><path d="M12 5v14"/>
                    </svg>
                    New Consultation
                </a>
            </div>

            {{-- Past consultations list --}}
            @php
                $consultations = $recentVisits->where('type', 'consultation');
            @endphp

            @if($consultations->isEmpty())
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
                <a href="{{ route('patients.consultations.create', $patient) }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-[#6a0f70] text-white hover:bg-[#380740] rounded transition-colors font-medium">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
                    Add First Consultation
                </a>
            </div>
            @else
            <div class="space-y-3">
                @foreach($consultations as $consult)
                <div class="bg-white border border-gray-200 rounded-lg p-5 hover:border-[#6a0f70]/30 transition-colors cursor-pointer">
                    <div class="flex items-start justify-between mb-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0">
                                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                </svg>
                            </div>
                            <div>
                                <div class="font-semibold text-gray-800 text-sm">
                                    Consultation Visit
                                </div>
                                <div class="text-xs text-gray-400">
                                    {{ $consult->appointment_date?->format('d M Y') ?? '—' }}
                                    @if($consult->doctor?->name)
                                        &middot; {{ $consult->doctor->name }}
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($consult->amount ?? false)
                                <span class="text-sm font-bold text-gray-700">₹ {{ number_format($consult->amount,0) }}</span>
                            @endif
                            <span class="px-2 py-0.5 text-[10px] rounded-full font-medium
                                {{ $consult->status === 'completed' ? 'bg-green-50 text-green-700' : 'bg-purple-50 text-purple-700' }}">
                                {{ ucfirst($consult->status ?? 'Scheduled') }}
                            </span>
                        </div>
                    </div>
                    @if($consult->chief_complaint ?? false)
                    <div class="consult-entry mb-2">
                        <div class="consult-section-label">Chief Complaint</div>
                        <p class="text-sm text-gray-700">{{ $consult->chief_complaint }}</p>
                    </div>
                    @endif
                    @if($consult->diagnosis ?? false)
                    <div class="consult-entry">
                        <div class="consult-section-label">Diagnosis</div>
                        <p class="text-sm text-gray-700">{{ $consult->diagnosis }}</p>
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
                                <span class="text-base" x-text="oppIcons[opp.type] || '🦷'"></span>
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
                    @foreach([
                        ['Add Follow-up','<rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/>'],
                        ['Treatment Plan','<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>'],
                        ['Print','<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect width="12" height="8" x="6" y="14"/>'],
                        ['Upload Scan','<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>'],
                    ] as [$lbl, $path])
                    <button class="quick-action-btn">
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


{{-- ══════════════════════════════════════════════════════════
     FOLLOW-UP TAB
══════════════════════════════════════════════════════════ --}}
<div x-show="activeTab === 'followup'" class="max-w-[1440px] mx-auto px-6 py-16 text-center text-gray-400">
    <div class="w-14 h-14 rounded-full bg-orange-50 flex items-center justify-center mx-auto mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none"
             stroke="#ea580c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <rect width="18" height="18" x="3" y="4" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/>
            <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            <path d="m9 16 2 2 4-4"/>
        </svg>
    </div>
    <p class="text-sm font-semibold text-gray-600 mb-1">Follow-up module</p>
    <p class="text-xs text-gray-400">Coming soon — building next.</p>
</div>


{{-- ══════════════════════════════════════════════════════════
     TREATMENT VISITS TAB
══════════════════════════════════════════════════════════ --}}
<div x-show="activeTab === 'visits'" class="max-w-[1440px] mx-auto px-6 py-16 text-center text-gray-400">
    <div class="w-14 h-14 rounded-full bg-green-50 flex items-center justify-center mx-auto mb-4">
        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" viewBox="0 0 24 24" fill="none"
             stroke="#16a34a" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
    </div>
    <p class="text-sm font-semibold text-gray-600 mb-1">Treatment Visits module</p>
    <p class="text-xs text-gray-400">Coming soon — building next.</p>
</div>


{{-- Edit drawer --}}
@include('patients.partials.edit-patient-drawer')

</div>

@push('scripts')
<script>
function patientProfile() {
    return {
        activeTab: 'profile',
        editDrawerOpen: false,
        showNoteForm: false,
        showOppForm: false,
        showConsultForm: false,

        relationshipNotes: @json($relationshipNotes),
        newNote: '',
        newNoteTags: [],

        opportunities: @json($opportunities),
        newOpp: { type:'', status:'prospect', priority:'medium', estimated_value:'', follow_up_date:'' },

   
       oppTypeColors: {
    implant:         { color:'#6a0f70', bg:'#f5f3ff' },
    aligner:         { color:'#2563eb', bg:'#dbeafe' },
    veneers:         { color:'#0891b2', bg:'#e0f2fe' },
    full_mouth_rehab:{ color:'#7c3aed', bg:'#ede9fe' },
    whitening:       { color:'#ca8a04', bg:'#fef9c3' },
    crown:           { color:'#b45309', bg:'#fef3c7' },
    bridge:          { color:'#0d9488', bg:'#ccfbf1' },
    rct:             { color:'#dc2626', bg:'#fee2e2' },
    smile_design:    { color:'#db2777', bg:'#fce7f3' },
    gum_treatment:   { color:'#16a34a', bg:'#dcfce7' },
},

        init() {},

        oppStageLabel(s) {
            return {prospect:'Identified',discussed:'Discussed',quoted:'Financial Discussion',accepted:'Planned',completed:'Completed'}[s] || s;
        },
        toggleNoteTag(t) {
            this.newNoteTags.includes(t)
                ? this.newNoteTags = this.newNoteTags.filter(x=>x!==t)
                : this.newNoteTags.push(t);
        },
        async saveNote() {
            if (!this.newNote.trim()) return;
            const r = await fetch(`/patients/{{ $patient->id }}/relationship-notes`, {
                method:'POST',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},
                body:JSON.stringify({note:this.newNote,tags:this.newNoteTags}),
            });
            const d = await r.json();
            if (d.success) { this.relationshipNotes.unshift(d.note); this.newNote=''; this.newNoteTags=[]; this.showNoteForm=false; }
        },
        async deleteNote(id) {
            if (!confirm('Delete?')) return;
            const r = await fetch(`/patients/{{ $patient->id }}/relationship-notes/${id}`,{
                method:'DELETE',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},
            });
            if ((await r.json()).success) this.relationshipNotes = this.relationshipNotes.filter(n=>n.id!==id);
        },
        async saveOpportunity() {
            if (!this.newOpp.type) return;
            const r = await fetch(`/patients/{{ $patient->id }}/opportunities`,{
                method:'POST',
                headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},
                body:JSON.stringify(this.newOpp),
            });
            const d = await r.json();
            if (d.success) { this.opportunities.unshift(d.opportunity); this.newOpp={type:'',status:'prospect',priority:'medium',estimated_value:'',follow_up_date:''}; this.showOppForm=false; }
        },
        async deleteOpportunity(id) {
            if (!confirm('Remove?')) return;
            const r = await fetch(`/patients/{{ $patient->id }}/opportunities/${id}`,{
                method:'DELETE',headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},
            });
            if ((await r.json()).success) this.opportunities = this.opportunities.filter(o=>o.id!==id);
        },
        async submitEditPatient() {
            const form = document.getElementById('editPatientForm');
            const fd = new FormData(form);
            const at = fd.get('allergies_text')||''; fd.delete('allergies_text');
            at.split(',').map(s=>s.trim()).filter(Boolean).forEach(a=>fd.append('allergies[]',a));
            try {
                const r = await fetch(`/patients/{{ $patient->id }}`,{
                    method:'POST',
                    headers:{'X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},
                    body:fd,
                });
                const d = await r.json();
                if (d.success) { this.editDrawerOpen=false; window.location.reload(); }
            } catch(e) { console.error(e); }
        },
        formatDate(s) {
            if (!s) return '';
            return new Date(s).toLocaleDateString('en-IN',{day:'numeric',month:'short',year:'numeric'});
        },
    }
}
</script>
@endpush
@endsection