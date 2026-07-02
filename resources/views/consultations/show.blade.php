{{-- resources/views/consultations/show.blade.php --}}
{{-- Layout: Sticky Header | Left Nav | Main Content | Collapsed CIP Panel --}}
@extends('layouts.app')

@section('title', 'Consultation — ' . $consultation->patient->name)

@section('content')
<div class="min-h-screen bg-gray-50" x-data="consultationShow()" @cip-assist.window="cipAssist($event.detail)">

    {{-- Success / info flash ──────────────────────────────────────────────── --}}
    @if(session('success'))
    <div x-data="{ show: true }" x-show="show" x-transition:leave="transition ease-in duration-300" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="bg-green-50 border-b border-green-200 px-4 py-2.5 flex items-center justify-between gap-4 text-sm text-green-800">
        <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-green-600 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            {{ session('success') }} — Continue to <strong>Tx Plan</strong> or <strong>Rx</strong> using the buttons above.
        </div>
        <button @click="show = false" class="text-green-500 hover:text-green-700 shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- STICKY HEADER                                                          --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <header class="bg-white border-b border-gray-200 sticky top-0 z-30 shadow-sm">
        <div class="max-w-screen-2xl mx-auto px-4 py-3 flex items-center justify-between gap-4">

            {{-- Left: back + title --}}
            <div class="flex items-center gap-3 min-w-0">
                <a href="{{ route('patients.show', $consultation->patient_id) }}"
                   class="shrink-0 text-gray-400 hover:text-gray-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                </a>
                <div class="min-w-0">
                    <h1 class="text-base font-semibold text-gray-800 truncate">
                        {{ $consultation->patient->name }}
                    </h1>
                    <p class="text-xs text-gray-400 truncate">
                        {{ \Carbon\Carbon::parse($consultation->consultation_date)->format('D, d M Y') }}
                        &middot; {{ $consultation->doctor->doctor_name }}
                        @if($consultation->visit_type)
                            &middot; <span class="capitalize">{{ $consultation->visit_type }}</span>
                        @endif
                    </p>
                </div>
            </div>

            {{-- Right: action buttons + CIP toggle --}}
            <div class="flex items-center gap-2 shrink-0 flex-wrap justify-end">

                @if($consultation->consultation_type === 'coha')
                    <a href="{{ route('coha.edit', [$consultation->patient_id, $consultation]) }}"
                       class="inline-flex items-center gap-1.5 bg-cyan-700 hover:bg-cyan-800 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit COHA
                    </a>
                    <a href="{{ route('coha.report', [$consultation->patient_id, $consultation]) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 text-cyan-700 border border-cyan-300 hover:border-cyan-500 hover:text-cyan-900 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors bg-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 4 0M9 5a2 2 0 0 1 4 0"/>
                        </svg>
                        View Report
                    </a>
                @else
                    <a href="{{ route('consultations.edit', $consultation) }}"
                       class="inline-flex items-center gap-1.5 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </a>
                    <a href="{{ route('consultations.print', $consultation) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 text-gray-600 border border-gray-300 hover:border-gray-400 hover:text-gray-900 text-sm font-medium px-3 py-1.5 rounded-lg transition-colors bg-white">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <polyline points="6 9 6 2 18 2 18 9"/>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                            <rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Print
                    </a>
                    <a href="{{ route('treatment-plans.from-consultation', [$consultation->patient_id, $consultation]) }}"
                       class="inline-flex items-center gap-1.5 bg-violet-600 hover:bg-violet-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                        </svg>
                        Tx Plan
                    </a>
                    {{-- Rx → opens the patient's Prescriptions tab (not the new-prescription form) --}}
                    <a href="{{ route('patients.show', $consultation->patient_id) }}#prescriptions"
                       onclick="sessionStorage.setItem('patientActiveTab','prescriptions')"
                       class="inline-flex items-center gap-1.5 bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/>
                        </svg>
                        Rx
                    </a>
                @endif

                {{-- CIP Toggle Button --}}
                <button @click="cipOpen = !cipOpen"
                        :class="cipOpen
                            ? 'bg-violet-100 text-violet-700 border-violet-300 hover:bg-violet-200'
                            : 'bg-white text-gray-600 border-gray-300 hover:border-gray-400 hover:text-gray-900'"
                        class="inline-flex items-center gap-1.5 border text-sm font-medium px-3 py-1.5 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                    </svg>
                    <span x-text="cipOpen ? 'Hide Intel' : 'Clinical Intel'"></span>
                </button>

            </div>
        </div>
    </header>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- BODY: Left Nav | Main | CIP Panel                                      --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="max-w-screen-2xl mx-auto px-4 py-6 flex gap-5 items-start">

        {{-- ── LEFT NAV ──────────────────────────────────────────────────── --}}
        <aside class="hidden lg:block w-48 shrink-0">
            <nav class="sticky top-20 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest px-4 pt-4 pb-2">
                    Sections
                </p>
                @php
                $sections = [
                    'sec-header'      => 'Overview',
                    'sec-complaint'   => 'Chief Complaint',
                    'sec-specialty'   => 'Specialty Findings',
                    'sec-hopi'        => 'HOPI',
                    'sec-visit-update'=> 'Visit Update',
                    'sec-toothchart'  => 'Tooth Chart',
                    'sec-invest'      => 'Investigations',
                    'sec-findings'    => 'Findings Summary',
                    'sec-diagnosis'   => 'Diagnosis',
                    'sec-rendered'    => 'Treatment Rendered',
                ];
                // Conditional sections only appear in the nav when they actually render.
                $navHide = [];
                if (!($consultation->specialty_findings ?? false) && !$consultation->specialtyModules()->exists()) $navHide[] = 'sec-specialty';
                if (!$consultation->hopi_final) $navHide[] = 'sec-hopi';
                if (!$consultation->update_notes && !$consultation->additional_findings && $consultation->related_to_clinic_treatment === null) $navHide[] = 'sec-visit-update';
                if (!$consultation->findings_summary_final) $navHide[] = 'sec-findings';
                if (!$consultation->procedure_performed && !$consultation->emergency_treatment_rendered && !$consultation->advice) $navHide[] = 'sec-rendered';
                @endphp
                <ul class="pb-3">
                    @foreach($sections as $id => $label)
                    @continue(in_array($id, $navHide))
                    <li>
                        <a href="#{{ $id }}"
                           class="block text-sm text-gray-600 hover:text-indigo-600 hover:bg-indigo-50 px-4 py-1.5 transition-colors">
                            {{ $label }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </nav>
        </aside>

        {{-- ── MAIN CONTENT ──────────────────────────────────────────────── --}}
        <main class="flex-1 min-w-0 space-y-4">

            {{-- 1. Overview Card ──────────────────────────────────────────── --}}
            <section id="sec-header" class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900">{{ $consultation->patient->name }}</h2>
                        <p class="text-gray-500 text-sm mt-0.5">
                            {{ $consultation->doctor->doctor_name }}
                            &middot; Branch #{{ $consultation->branch_id }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        @if($consultation->visit_type)
                        @php
                        $vtColors = [
                            'emergency' => 'bg-red-100 text-red-700',
                            'routine'   => 'bg-blue-100 text-blue-700',
                            'followup'  => 'bg-purple-100 text-purple-700',
                        ];
                        @endphp
                        <span class="text-xs font-semibold px-3 py-1 rounded-full {{ $vtColors[$consultation->visit_type] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ ucfirst($consultation->visit_type) }}
                        </span>
                        @endif
                        <span class="text-xs font-semibold px-3 py-1 rounded-full
                            {{ $consultation->status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                            {{ ucfirst($consultation->status) }}
                        </span>
                    </div>
                </div>
                <div class="mt-3 text-sm text-gray-500 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    {{ \Carbon\Carbon::parse($consultation->consultation_date)->format('D, d M Y') }}
                </div>
            </section>

            {{-- 2. Chief Complaint ────────────────────────────────────────── --}}
            <section id="sec-complaint" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Chief Complaint</h3>
                </div>
                <dl class="px-5 py-4 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    @foreach([
                        'Complaint'  => $consultation->chief_complaint,
                        'Duration'   => $consultation->complaint_duration,
                        'Severity'   => $consultation->severity,
                        'Tooth Area' => $consultation->tooth_area,
                        'Location'   => $consultation->location,
                        'Notes'      => $consultation->complaint_notes,
                    ] as $label => $value)
                    <div>
                        <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ $label }}</dt>
                        <dd class="mt-0.5 text-gray-800">{{ $value ?: '—' }}</dd>
                    </div>
                    @endforeach
                </dl>
            </section>

            {{-- 2b. Specialty Findings ─────────────────────────────────────── --}}
            {{-- Mirrors the create form's Specialty Modules zone (Ortho / Perio / Endo / Smile / Prostho). --}}
            @php
                // Label maps copied 1:1 from create.blade.php so the view never drifts.
                $specTagLabels = [
                    'orthodontics'   => 'Orthodontic Findings',
                    'periodontics'   => 'Periodontic Findings',
                    'endodontics'    => 'Endodontic Findings',
                    'smile_design'   => 'Smile Design / Cosmetic Findings',
                    'prosthodontics' => 'Prosthodontic Findings',
                ];
                $specFieldLabels = [
                    // Ortho
                    'ortho_crowding'=>'Crowding','ortho_spacing'=>'Spacing','ortho_overjet'=>'Overjet','ortho_overbite'=>'Overbite',
                    'ortho_midline'=>'Midline','ortho_skeletal'=>'Skeletal pattern','ortho_molar'=>'Molar relation','ortho_profile'=>'Profile','ortho_symmetry'=>'Facial symmetry',
                    // Perio
                    'perio_bop'=>'BOP','perio_pocket'=>'Pocket depth','perio_recess'=>'Recession','perio_mobility'=>'Mobility',
                    'perio_furc'=>'Furcation','perio_calc'=>'Calculus','perio_plaque'=>'Plaque score','perio_hygiene'=>'Oral hygiene',
                    // Endo
                    'endo_pain'=>'Pain type','endo_thermal'=>'Thermal test','endo_percuss'=>'Percussion','endo_palp'=>'Palpation',
                    'endo_swell'=>'Swelling','endo_sinus'=>'Sinus tract','endo_pulp'=>'Pulp status','endo_mob'=>'Mobility',
                    // Smile design
                    'sd_shade'=>'Shade','sd_smile'=>'Smile line','sd_buccal'=>'Buccal corridor','sd_ging'=>'Gingival display',
                    'sd_props'=>'Tooth proportions','sd_midline'=>'Midline','sd_disco'=>'Discoloration',
                    // Prostho
                    'pros_miss'=>'Missing teeth','pros_exist'=>'Existing prosthesis','pros_bone'=>'Bone support',
                    'pros_ridge'=>'Ridge condition','pros_occl'=>'Occlusal support','pros_tmj'=>'TMJ status',
                ];
                // Prefer the saved specialty_findings JSON column; fall back to the relation rows.
                $specFindings = $consultation->specialty_findings ?? [];
                if (empty($specFindings) && $consultation->relationLoaded('specialtyModules')) {
                    foreach ($consultation->specialtyModules as $m) {
                        if ($m->rejected_at) continue;
                        $specFindings[$m->specialty_tag] = $m->findings ?? [];
                    }
                }
                $acceptedSpecs = $consultation->accepted_specialties ?? array_keys($specFindings);
                $hasSpecialty  = collect($specFindings)->filter(fn($f) => !empty($f))->isNotEmpty();
            @endphp
            @if($hasSpecialty)
            <section id="sec-specialty" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Specialty Findings</h3>
                </div>
                <div class="px-5 py-4 space-y-5 text-sm">
                    @foreach($specFindings as $tag => $fields)
                        @php $fields = array_filter((array) $fields, fn($v) => $v !== null && $v !== ''); @endphp
                        @if(count($fields))
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide mb-2" style="color:#6a0f70;">
                                {{ $specTagLabels[$tag] ?? ucfirst(str_replace('_',' ', $tag)) }}
                            </p>
                            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-8 gap-y-2">
                                @foreach($fields as $k => $v)
                                <div>
                                    <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ $specFieldLabels[$k] ?? ucfirst(str_replace('_',' ', $k)) }}</dt>
                                    <dd class="mt-0.5 text-gray-800">{{ $v }}</dd>
                                </div>
                                @endforeach
                            </dl>
                        </div>
                        @endif
                    @endforeach
                </div>
            </section>
            @endif

            {{-- 2c. History of Present Illness (HOPI) ──────────────────────── --}}
            @if($consultation->hopi_final)
            <section id="sec-hopi" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">History of Present Illness (HOPI)</h3>
                </div>
                <div class="px-5 py-4 text-sm">
                    <p class="text-gray-800 whitespace-pre-wrap leading-relaxed">{{ $consultation->hopi_final }}</p>
                </div>
            </section>
            @endif

            {{-- 2d. Visit Update (Same-Issue / Minor-Visit context fields) ──── --}}
            @php
                $relFlag = $consultation->related_to_clinic_treatment;
                $hasVisitUpdate = $consultation->update_notes || $consultation->additional_findings || $relFlag !== null;
            @endphp
            @if($hasVisitUpdate)
            <section id="sec-visit-update" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Visit Update</h3>
                </div>
                <div class="px-5 py-4 text-sm space-y-3">
                    @if($relFlag !== null)
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Related to Clinic Treatment</p>
                        <dd class="mt-0.5 text-gray-800">{{ $relFlag ? 'Yes — follow-up of clinic treatment' : 'No — unrelated' }}</dd>
                    </div>
                    @endif
                    @if($consultation->update_notes)
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Patient Update</p>
                        <p class="mt-0.5 text-gray-800 whitespace-pre-wrap leading-relaxed">{{ $consultation->update_notes }}</p>
                    </div>
                    @endif
                    @if($consultation->additional_findings)
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Additional Findings Today</p>
                        <p class="mt-0.5 text-gray-800 whitespace-pre-wrap leading-relaxed">{{ $consultation->additional_findings }}</p>
                    </div>
                    @endif
                </div>
            </section>
            @endif

            {{-- 5. Tooth Chart ────────────────────────────────────────────── --}}
            <section id="sec-toothchart" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Tooth Chart</h3>
                </div>
                <div class="px-5 py-4 text-sm">
                    @php $chartData = $consultation->chart_data ?? []; @endphp
                    @if(count($chartData))
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($chartData as $tooth)
                        <span class="bg-indigo-100 text-indigo-700 text-xs font-semibold px-2 py-0.5 rounded cursor-pointer hover:bg-indigo-200 transition-colors"
                              @click="cipToothTimeline('{{ $tooth }}')" title="View tooth timeline">{{ $tooth }}</span>
                        @endforeach
                    </div>
                    @else
                    <div class="w-full h-16 rounded-lg border-2 border-dashed border-gray-200 flex items-center justify-center text-gray-300 text-sm">
                        No teeth selected
                    </div>
                    @endif
                </div>
            </section>

            {{-- 6. Investigations ─────────────────────────────────────────── --}}
            <section id="sec-invest" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Investigations Advised</h3>
                </div>
                <div class="px-5 py-4 text-sm">
                    @php
                    $investigations = $consultation->investigations ?? [];
                    $invDet = $consultation->investigation_details ?? [];
                    // Label map for display
                    $invLabels = [
                        'iopa'       => ['IOPA', 'Intraoral Periapical'],
                        'opg'        => ['OPG', 'Orthopantomogram'],
                        'cbct'       => ['CBCT', 'Cone Beam CT'],
                        'rvg'        => ['RVG', 'Radiovisiography'],
                        'ceph'       => ['Lat. Ceph.', 'Lateral Cephalogram'],
                        'cbc'        => ['CBC', 'Complete Blood Count'],
                        'blood_sugar'=> ['Blood Sugar', 'FBS / RBS / PPBS'],
                        'hba1c'      => ['HbA1c', 'Glycated Haemoglobin'],
                        'pt_inr'     => ['PT / INR', 'Bleeding & Clotting Profile'],
                        'hiv'        => ['HIV', 'HIV I & II'],
                        'hbsag'      => ['HBsAg', 'Hepatitis B Surface Antigen'],
                        'thyroid'    => ['Thyroid', 'T3 / T4 / TSH Profile'],
                        'lab_custom' => ['Other Lab', 'Custom Laboratory Test'],
                        'mri'        => ['MRI', 'Magnetic Resonance Imaging'],
                        'usg'        => ['USG', 'Ultrasonography'],
                        'ct'         => ['CT Scan', 'Computed Tomography'],
                        'biopsy'     => ['Biopsy', 'Tissue Biopsy / FNAC'],
                        'sialography'=> ['Sialography', 'Salivary Gland Study'],
                        // Legacy keys
                        'photographs'=> ['Photographs', ''],
                        'intraoral'  => ['Intraoral Scan', ''],
                        'blood_tests'=> ['Blood Tests', ''],
                        'mri_usg'    => ['MRI / USG', ''],
                        'other'      => ['Other', ''],
                    ];
                    $invGroups = [
                        'Radiographic' => ['iopa','opg','cbct','rvg','ceph'],
                        'Blood / Lab'  => ['cbc','blood_sugar','hba1c','pt_inr','hiv','hbsag','thyroid','lab_custom'],
                        'Other Imaging'=> ['mri','usg','ct','biopsy','sialography'],
                    ];
                    $clinicalNotes = $invDet['_notes'] ?? null;
                    @endphp

                    @if(count($investigations))
                    <div class="space-y-4">
                        @foreach($invGroups as $groupName => $keys)
                            @php $groupItems = array_filter($investigations, fn($k) => in_array($k, $keys)); @endphp
                            @if(count($groupItems))
                            <div>
                                <p class="text-xs font-bold text-purple-700 uppercase tracking-widest mb-2">{{ $groupName }}</p>
                                <ul class="space-y-1.5">
                                    @foreach($groupItems as $item)
                                    @php
                                        $lbl   = $invLabels[$item] ?? [ucfirst(str_replace('_',' ',$item)), ''];
                                        $detail = $invDet[$item] ?? ($invDet[array_search($item, $investigations)] ?? null);
                                    @endphp
                                    <li class="flex items-start gap-2">
                                        <span class="mt-0.5 w-4 h-4 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center text-xs font-bold shrink-0">✓</span>
                                        <div>
                                            <span class="font-semibold text-gray-800">{{ $lbl[0] }}</span>
                                            @if($lbl[1])
                                            <span class="text-gray-400 text-xs ml-1">— {{ $lbl[1] }}</span>
                                            @endif
                                            @if($detail && $detail !== '✓')
                                            <p class="text-gray-600 text-xs mt-0.5">{{ $detail }}</p>
                                            @endif
                                        </div>
                                    </li>
                                    @endforeach
                                </ul>
                            </div>
                            @endif
                        @endforeach

                        {{-- Any keys not in the known groups --}}
                        @php
                            $allGrouped = array_merge(...array_values($invGroups));
                            $misc = array_filter($investigations, fn($k) => !in_array($k, $allGrouped));
                        @endphp
                        @if(count($misc))
                        <div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-2">Other</p>
                            <ul class="space-y-1.5">
                                @foreach($misc as $item)
                                <li class="flex items-start gap-2">
                                    <span class="mt-0.5 w-4 h-4 rounded-full bg-gray-100 text-gray-600 flex items-center justify-center text-xs font-bold shrink-0">✓</span>
                                    <span class="font-medium text-gray-800">{{ ucfirst(str_replace('_',' ',$item)) }}</span>
                                </li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        {{-- Clinical notes --}}
                        @if($clinicalNotes)
                        <div class="pt-3 border-t border-dashed border-gray-200">
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Clinical Instructions</p>
                            <p class="text-gray-700 leading-relaxed">{{ $clinicalNotes }}</p>
                        </div>
                        @endif
                    </div>
                    @else
                    <p class="text-gray-400 italic">None recorded.</p>
                    @endif
                </div>
            </section>

            {{-- 7. Findings Summary ───────────────────────────────────────── --}}
            @if($consultation->findings_summary_final)
            <section id="sec-findings" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Findings Summary</h3>
                </div>
                <div class="px-5 py-4 text-sm">
                    <p class="text-gray-800 whitespace-pre-wrap leading-relaxed">{{ $consultation->findings_summary_final }}</p>
                </div>
            </section>
            @endif

            {{-- 10. Diagnosis ──────────────────────────────────────────────── --}}
            <section id="sec-diagnosis" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Diagnosis</h3>
                </div>
                <div class="px-5 py-4 space-y-3 text-sm">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Primary Diagnosis</p>
                            <p class="mt-0.5 text-gray-800 font-medium">{{ $consultation->primary_diagnosis ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Secondary Diagnosis</p>
                            <p class="mt-0.5 text-gray-800">{{ $consultation->secondary_diagnosis ?: '—' }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Risk Assessment</p>
                        @php
                        // Create form saves `diagnosis_risk` ("Low Risk" … "Very High Risk");
                        // older records may use `risk_assessment`. Show whichever is present.
                        $riskVal = $consultation->diagnosis_risk ?: $consultation->risk_assessment;
                        $riskLc  = strtolower((string) $riskVal);
                        $riskColor = str_contains($riskLc, 'very high') || str_contains($riskLc, 'high')
                                        ? 'bg-red-100 text-red-700'
                                   : (str_contains($riskLc, 'moderate') || str_contains($riskLc, 'medium')
                                        ? 'bg-yellow-100 text-yellow-700'
                                   : (str_contains($riskLc, 'low')
                                        ? 'bg-green-100 text-green-700'
                                        : 'bg-gray-100 text-gray-600'));
                        @endphp
                        @if($riskVal)
                        <span class="ml-2 text-xs font-semibold px-2 py-0.5 rounded-full {{ $riskColor }}">
                            {{ ucwords($riskVal) }}
                        </span>
                        @else
                        <span class="ml-2 text-gray-400">—</span>
                        @endif
                    </div>
                    @if($consultation->diagnosis_notes)
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Notes</p>
                        <p class="mt-0.5 text-gray-700">{{ $consultation->diagnosis_notes }}</p>
                    </div>
                    @endif
                </div>
            </section>

            {{-- 10b. Treatment Rendered & Advice (Emergency / Minor-Visit) ─── --}}
            @php $hasRendered = $consultation->procedure_performed || $consultation->emergency_treatment_rendered || $consultation->advice; @endphp
            @if($hasRendered)
            <section id="sec-rendered" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-5 py-3 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Treatment Rendered &amp; Advice</h3>
                </div>
                <div class="px-5 py-4 text-sm space-y-3">
                    @if($consultation->emergency_treatment_rendered)
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Emergency Treatment Given</p>
                        <p class="mt-0.5 text-gray-800 whitespace-pre-wrap leading-relaxed">{{ $consultation->emergency_treatment_rendered }}</p>
                    </div>
                    @endif
                    @if($consultation->procedure_performed)
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Procedure Performed</p>
                        <p class="mt-0.5 text-gray-800 whitespace-pre-wrap leading-relaxed">{{ $consultation->procedure_performed }}</p>
                    </div>
                    @endif
                    @if($consultation->advice)
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Advice Given</p>
                        <p class="mt-0.5 text-gray-800 whitespace-pre-wrap leading-relaxed">{{ $consultation->advice }}</p>
                    </div>
                    @endif
                </div>
            </section>
            @endif

            {{-- Bottom Action Bar --}}
            <div class="flex items-center justify-between pt-2 pb-8">
                <a href="{{ route('patients.show', $consultation->patient_id) }}"
                   class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Back to Patient
                </a>
                @if($consultation->consultation_type !== 'coha')
                <a href="{{ route('consultations.edit', $consultation) }}"
                   class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Edit Consultation
                </a>
                @endif
            </div>

        </main>

        {{-- CLINICAL INTELLIGENCE PANEL --}}
        <aside x-show="cipOpen"
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="opacity-0 translate-x-4"
               x-transition:enter-end="opacity-100 translate-x-0"
               x-transition:leave="transition ease-in duration-150"
               x-transition:leave-start="opacity-100 translate-x-0"
               x-transition:leave-end="opacity-0 translate-x-4"
               class="hidden lg:block w-72 shrink-0">

            <div class="sticky top-20 space-y-3 max-h-[calc(100vh-6rem)] overflow-y-auto pb-6 pr-1">

                <div class="bg-gradient-to-r from-violet-600 to-indigo-600 rounded-xl px-4 py-3 text-white">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/>
                            </svg>
                            <h2 class="text-sm font-semibold">Clinical Intelligence</h2>
                        </div>
                        <button @click="cipOpen = false" class="text-violet-200 hover:text-white transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-xs text-violet-200 mt-0.5">Rule-based patient context</p>
                </div>

                {{-- ── AI ASSIST RESULT CARD (shown when a ghost button is clicked) ── --}}
                <div x-show="cipMode !== 'default'"
                     x-transition:enter="transition ease-out duration-200"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     class="bg-white rounded-xl border border-violet-200 shadow-sm overflow-hidden">

                    {{-- Card header --}}
                    <div class="flex items-center justify-between px-4 py-3 bg-violet-50 border-b border-violet-100">
                        <span class="text-sm font-semibold text-violet-800 flex items-center gap-1.5">
                            <span>✨</span>
                            <span x-text="cipMode === 'tooth' ? 'Tooth ' + cipToothNum + ' Timeline' : (cipActiveLabel || 'Section Guidance')"></span>
                        </span>
                        <button @click="cipReset()" class="text-violet-400 hover:text-violet-700 transition-colors text-xs">✕ clear</button>
                    </div>

                    {{-- Loading state --}}
                    <div x-show="cipLoading" class="px-4 py-5 flex items-center gap-2 text-violet-500 text-xs">
                        <svg class="w-4 h-4 animate-spin shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        Loading guidance…
                    </div>

                    {{-- Error state --}}
                    <div x-show="cipError && !cipLoading" class="px-4 py-3 text-xs text-red-600 italic" x-text="cipError"></div>

                    {{-- Section guidance result --}}
                    <div x-show="cipMode === 'section' && cipSection && !cipLoading" class="px-4 pb-4 pt-2 space-y-3">

                        {{-- Tips --}}
                        <template x-if="cipSection && cipSection.tips && cipSection.tips.length">
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Tips</p>
                                <ul class="space-y-1.5">
                                    <template x-for="tip in cipSection.tips" :key="tip">
                                        <li class="flex items-start gap-1.5 text-xs text-gray-700">
                                            <span class="text-violet-400 mt-0.5 shrink-0">•</span>
                                            <span x-text="tip"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>

                        {{-- Checklist --}}
                        <template x-if="cipSection && cipSection.checklist && cipSection.checklist.length">
                            <div>
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">Checklist</p>
                                <ul class="space-y-1" x-data="cipGhostChecklist()">
                                    <template x-for="item in cipSection.checklist" :key="item">
                                        <li class="flex items-center gap-1.5 text-xs cursor-pointer group" @click="toggle(item)">
                                            <span :class="done[item] ? 'bg-violet-500 border-violet-500' : 'border-gray-300 group-hover:border-violet-400'"
                                                  class="w-3.5 h-3.5 rounded border-2 flex items-center justify-center shrink-0 transition-colors">
                                                <svg x-show="done[item]" class="w-2 h-2 text-white" fill="currentColor" viewBox="0 0 12 12">
                                                    <path d="M10 3L5 8.5 2 5.5 1 6.5 5 10.5 11 4z"/>
                                                </svg>
                                            </span>
                                            <span :class="done[item] ? 'line-through text-gray-400' : 'text-gray-700'" x-text="item" class="transition-colors"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>

                        {{-- Red flags --}}
                        <template x-if="cipSection && cipSection.red_flags && cipSection.red_flags.length">
                            <div>
                                <p class="text-xs font-bold text-red-400 uppercase tracking-wide mb-1.5">Red Flags</p>
                                <ul class="space-y-1.5">
                                    <template x-for="flag in cipSection.red_flags" :key="flag">
                                        <li class="flex items-start gap-1.5 text-xs text-red-700 bg-red-50 rounded px-2 py-1">
                                            <span class="shrink-0 mt-0.5">!</span>
                                            <span x-text="flag"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>
                        </template>
                    </div>

                    {{-- Tooth timeline result --}}
                    <div x-show="cipMode === 'tooth' && cipTooth && !cipLoading" class="px-4 pb-4 pt-2">
                        <template x-if="cipTooth && cipTooth.timeline && cipTooth.timeline.length === 0">
                            <p class="text-xs text-gray-400 italic">No previous treatment on tooth <span x-text="cipToothNum"></span>.</p>
                        </template>
                        <template x-if="cipTooth && cipTooth.timeline && cipTooth.timeline.length > 0">
                            <div class="space-y-2">
                                <template x-for="entry in cipTooth.timeline" :key="entry.consultation_id">
                                    <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                                        <div class="flex items-center justify-between gap-1 mb-0.5">
                                            <span class="text-xs font-semibold text-amber-800" x-text="entry.date"></span>
                                            <span class="text-xs px-1.5 py-0.5 rounded bg-amber-100 text-amber-700 capitalize shrink-0" x-text="entry.visit_type"></span>
                                        </div>
                                        <p class="text-xs text-gray-700" x-text="entry.diagnosis"></p>
                                        <p class="text-xs text-gray-500 mt-0.5" x-show="entry.treatment !== '—'" x-text="'Tx: ' + entry.treatment"></p>
                                        <p class="text-xs text-gray-400" x-text="'Dr. ' + entry.doctor"></p>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- 1. Patient Snapshot --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-indigo-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            Patient Snapshot
                        </span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="px-4 pb-4 border-t border-gray-100">
                        <dl class="mt-3 space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-xs text-gray-400 uppercase tracking-wide">Age</dt><dd class="text-gray-800 font-medium">{{ $consultation->patient->age ?? '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-xs text-gray-400 uppercase tracking-wide">Gender</dt><dd class="text-gray-800 font-medium">{{ ucfirst($consultation->patient->gender ?? '—') }}</dd></div>
                            <div class="flex justify-between"><dt class="text-xs text-gray-400 uppercase tracking-wide">Blood Group</dt><dd class="text-gray-800 font-medium">{{ $consultation->patient->blood_group ?? '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-xs text-gray-400 uppercase tracking-wide">Patient ID</dt><dd class="text-gray-800 font-medium">{{ $consultation->patient->patient_id ?? $consultation->patient_id }}</dd></div>
                            @if($consultation->patient->mobile)
                            <div class="flex justify-between"><dt class="text-xs text-gray-400 uppercase tracking-wide">Mobile</dt><dd class="text-gray-800 font-medium">{{ $consultation->patient->mobile }}</dd></div>
                            @endif
                        </dl>
                    </div>
                </div>

                {{-- 2. Medical Alerts --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-red-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            Medical Alerts
                        </span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="px-4 pb-4 border-t border-gray-100">
                        @php $medAlert = $consultation->patient->medical_alert ?? null; $medConds = $consultation->patient->medical_conditions ?? []; $allergies = $consultation->patient->allergies ?? []; @endphp
                        <div class="mt-3 space-y-2">
                            @if($medAlert)<div class="bg-red-50 border border-red-200 rounded-lg px-3 py-2"><p class="text-xs font-semibold text-red-600 uppercase tracking-wide mb-1">Alert</p><p class="text-red-800 text-xs">{{ $medAlert }}</p></div>@endif
                            @if(count($allergies))<div class="bg-orange-50 border border-orange-200 rounded-lg px-3 py-2"><p class="text-xs font-semibold text-orange-600 uppercase tracking-wide mb-1">Allergies</p><div class="flex flex-wrap gap-1">@foreach($allergies as $a)<span class="bg-orange-100 text-orange-800 text-xs px-2 py-0.5 rounded-full">{{ $a }}</span>@endforeach</div></div>@endif
                            @if(count($medConds))<div><p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-1">Conditions</p><div class="flex flex-wrap gap-1">@foreach($medConds as $c)<span class="bg-gray-100 text-gray-700 text-xs px-2 py-0.5 rounded-full">{{ $c }}</span>@endforeach</div></div>@endif
                            @if(!$medAlert && !count($allergies) && !count($medConds))<p class="text-gray-400 italic text-xs">No alerts on record.</p>@endif
                        </div>
                    </div>
                </div>

                {{-- 3. Previous Consultations --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            Previous Consultations
                        </span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="px-4 pb-4 border-t border-gray-100">
                        <div class="mt-3 space-y-2">
                            @forelse($prevConsultations as $prev)
                            <a href="{{ route('consultations.show', $prev) }}" class="block bg-gray-50 hover:bg-indigo-50 border border-gray-200 hover:border-indigo-200 rounded-lg px-3 py-2 transition-colors group">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0"><p class="text-xs font-semibold text-gray-700 group-hover:text-indigo-700 truncate">{{ $prev->primary_diagnosis ?: ($prev->chief_complaint ?: 'Visit') }}</p><p class="text-xs text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($prev->consultation_date)->format('d M Y') }} &middot; {{ $prev->doctor->doctor_name ?? '—' }}</p></div>
                                    <span class="shrink-0 text-xs px-1.5 py-0.5 rounded {{ $prev->status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">{{ ucfirst($prev->status) }}</span>
                                </div>
                            </a>
                            @empty
                            <p class="text-gray-400 italic text-xs">No previous consultations.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- 4. Previous Prescriptions --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Previous Prescriptions
                        </span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="px-4 pb-4 border-t border-gray-100">
                        <div class="mt-3 space-y-2">
                            @forelse($prevPrescriptions as $rx)
                            <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                                <div class="flex items-center justify-between gap-2 mb-1"><p class="text-xs font-semibold text-gray-700">{{ $rx->prescription_number ?? 'Rx #'.$rx->id }}</p><p class="text-xs text-gray-400">{{ $rx->created_at->format('d M Y') }}</p></div>
                                @if($rx->items && $rx->items->count())
                                <ul class="space-y-0.5">
                                    @foreach($rx->items->take(3) as $item)
                                    <li class="text-xs text-gray-600 flex items-center gap-1">
                                        <span class="text-green-400">•</span>
                                        {{ $item->drug_name ?? $item->drug?->name ?? 'Item' }}
                                        @if($item->dose ?? null)
                                        <span class="text-gray-400">{{ $item->dose }}</span>
                                        @endif
                                    </li>
                                    @endforeach
                                    @if($rx->items->count() > 3)
                                    <li class="text-xs text-gray-400 italic">+{{ $rx->items->count() - 3 }} more</li>
                                    @endif
                                </ul>
                                @else
                                <p class="text-xs text-gray-400 italic">No items.</p>
                                @endif
                            </div>
                            @empty
                            <p class="text-gray-400 italic text-xs">No previous prescriptions.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- 5. Prev Treatment on Tooth --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 3H5a2 2 0 00-2 2v4m6-6h10a2 2 0 012 2v4M9 3v18m0 0h10a2 2 0 002-2V9M9 21H5a2 2 0 01-2-2V9m0 0h18"/></svg>
                            Prev. Treatment on Tooth
                        </span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="px-4 pb-4 border-t border-gray-100">
                        @php $currentTeeth = array_merge($consultation->tx_teeth ?? [], $consultation->chart_data ?? []); $relatedConsults = $prevConsultations->filter(function($c) use ($currentTeeth) { $pt = array_merge($c->tx_teeth ?? [], $c->chart_data ?? []); return count($currentTeeth) > 0 && count(array_intersect($currentTeeth, $pt)) > 0; }); @endphp
                        <div class="mt-3">
                            @if(count($currentTeeth))
                            <p class="text-xs text-gray-400 mb-2">Teeth: <span class="font-medium text-gray-600">{{ implode(', ', $currentTeeth) }}</span></p>
                            @if($relatedConsults->count())
                            <div class="space-y-2">@foreach($relatedConsults as $rc)<div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"><p class="text-xs font-semibold text-amber-800">{{ \Carbon\Carbon::parse($rc->consultation_date)->format('d M Y') }}</p><p class="text-xs text-gray-600 mt-0.5">{{ $rc->primary_diagnosis ?: ($rc->chief_complaint ?: 'Treatment on file') }}</p></div>@endforeach</div>
                            @else
                            <p class="text-xs text-gray-400 italic">No previous treatment on these teeth.</p>
                            @endif
                            @else
                            <p class="text-xs text-gray-400 italic">No teeth selected in this consultation.</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- 6. Previous Photos --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-pink-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Previous Photos
                        </span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="px-4 pb-4 border-t border-gray-100">
                        @php $allPrevPhotos = $prevConsultations->flatMap(fn($c) => collect($c->photographs ?? [])->map(fn($p) => ['url' => $p]))->take(6); @endphp
                        <div class="mt-3">
                            @if($allPrevPhotos->count())
                            <div class="grid grid-cols-3 gap-1.5">@foreach($allPrevPhotos as $photo)<a href="{{ $photo['url'] }}" target="_blank"><div class="aspect-square rounded overflow-hidden bg-gray-100 border border-gray-200"><img src="{{ $photo['url'] }}" alt="Photo" class="w-full h-full object-cover hover:opacity-75 transition-opacity" loading="lazy"></div></a>@endforeach</div>
                            <p class="text-xs text-gray-400 mt-2">From previous consultations</p>
                            @else
                            <p class="text-xs text-gray-400 italic">No previous photos on file.</p>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- 7. Previous RVGs / Scans --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-teal-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            Previous RVGs / Scans
                        </span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="px-4 pb-4 border-t border-gray-100">
                        @php $prevScansData = $prevConsultations->filter(fn($c) => count($c->scan_files ?? []) > 0); @endphp
                        <div class="mt-3 space-y-2">
                            @forelse($prevScansData as $sc)
                            <div class="bg-teal-50 border border-teal-200 rounded-lg px-3 py-2"><p class="text-xs font-semibold text-teal-800">{{ \Carbon\Carbon::parse($sc->consultation_date)->format('d M Y') }}</p><ul class="mt-1 space-y-0.5">@foreach($sc->scan_files ?? [] as $file)<li><a href="{{ $file }}" target="_blank" class="text-xs text-teal-700 hover:underline truncate block">{{ basename($file) }}</a></li>@endforeach</ul></div>
                            @empty
                            <p class="text-xs text-gray-400 italic">No previous scans on file.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- 8. Pending Treatment Plan --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                            Pending Treatment Plan
                            @if($pendingTreatmentPlans->count())<span class="bg-purple-100 text-purple-700 text-xs font-bold px-1.5 py-0.5 rounded-full">{{ $pendingTreatmentPlans->count() }}</span>@endif
                        </span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="px-4 pb-4 border-t border-gray-100">
                        <div class="mt-3 space-y-2">
                            @forelse($pendingTreatmentPlans as $plan)
                            <div class="bg-purple-50 border border-purple-200 rounded-lg px-3 py-2">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold text-purple-800 truncate">{{ $plan->name ?? 'Plan #'.$plan->id }}</p>
                                    <span class="text-xs bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded capitalize shrink-0">{{ $plan->status }}</span>
                                </div>
                                @if($plan->total_amount ?? null)
                                <p class="text-xs text-gray-600 mt-1">Total: Rs. {{ number_format($plan->total_amount, 2) }}</p>
                                @endif
                                @if($plan->created_at)
                                <p class="text-xs text-gray-400 mt-0.5">Created {{ $plan->created_at->format('d M Y') }}</p>
                                @endif
                            </div>
                            @empty
                            <p class="text-xs text-gray-400 italic">No pending treatment plans.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- 9. Recall Status --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-cyan-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            Recall Status
                        </span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="px-4 pb-4 border-t border-gray-100">
                        @php $recallStatus = $consultation->patient->recall_status ?? null; $nextRecall = $consultation->patient->next_recall_date ?? null; $badgeColor = $consultation->patient->recall_badge_color ?? 'gray'; @endphp
                        <div class="mt-3 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-400 uppercase tracking-wide">Status</span>
                                @if($recallStatus)
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full {{ $badgeColor === 'green' ? 'bg-green-100 text-green-700' : ($badgeColor === 'red' ? 'bg-red-100 text-red-700' : ($badgeColor === 'yellow' ? 'bg-yellow-100 text-yellow-700' : 'bg-gray-100 text-gray-600')) }}">{{ ucfirst(str_replace('_', ' ', $recallStatus)) }}</span>
                                @else
                                <span class="text-xs text-gray-400">—</span>
                                @endif
                            </div>
                            @if($nextRecall)<div class="flex items-center justify-between"><span class="text-xs text-gray-400 uppercase tracking-wide">Next Recall</span><span class="text-xs font-medium text-gray-800">{{ \Carbon\Carbon::parse($nextRecall)->format('d M Y') }}</span></div>@endif
                        </div>
                    </div>
                </div>

                {{-- 10. Quick Actions --}}
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ open: false }">
                    <button @click="open = !open" class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors">
                        <span class="text-sm font-semibold text-gray-800 flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                            Quick Actions
                        </span>
                        <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </button>
                    <div x-show="open" x-collapse class="px-4 pb-4 border-t border-gray-100">
                        <div class="mt-3 space-y-1">
                            <a href="{{ route('patients.show', $consultation->patient_id) }}" class="flex items-center gap-2 text-sm text-gray-700 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg px-2 py-1.5 transition-colors"><svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>View Patient Profile</a>
                                           <a href="{{ route('consultations.print', $consultation) }}" target="_blank" class="flex items-center gap-2 text-sm text-gray-700 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg px-2 py-1.5 transition-colors"><svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>Print Consultation</a>
                            <a href="{{ route('patients.prescriptions.create', $consultation->patient_id) }}?consultation_id={{ $consultation->id }}" class="flex items-center gap-2 text-sm text-gray-700 hover:text-green-700 hover:bg-green-50 rounded-lg px-2 py-1.5 transition-colors"><svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/></svg>New Prescription</a>
                            <a href="{{ route('consultations.edit', $consultation) }}" class="flex items-center gap-2 text-sm text-gray-700 hover:text-indigo-700 hover:bg-indigo-50 rounded-lg px-2 py-1.5 transition-colors"><svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>Edit Consultation</a>
                        </div>
                    </div>
                </div>
                {{-- /Quick Actions --}}

            </div>
            {{-- /sticky inner --}}
        </aside>
        {{-- /CIP aside --}}

    </div>
    {{-- /3-column flex wrapper --}}

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- DYNAMIC CLINICAL SUMMARY                                               --}}
    {{-- Full-width panel below the 3-col layout. Aggregates this consultation  --}}
    {{-- into a scannable medico-legal snapshot for quick doctor review.        --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div class="max-w-screen-2xl mx-auto px-4 pb-8">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden" id="clinical-summary">

            {{-- Header bar --}}
            <div class="bg-gradient-to-r from-slate-700 to-slate-800 px-6 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 bg-white/15 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-white font-semibold text-sm tracking-wide">Dynamic Clinical Summary</h2>
                        <p class="text-slate-300 text-xs mt-0.5">Auto-generated from this consultation record &middot; For medico-legal reference only</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold px-2.5 py-1 rounded-full
                        {{ $consultation->status === 'completed' ? 'bg-emerald-400/20 text-emerald-200 border border-emerald-400/30' : 'bg-amber-400/20 text-amber-200 border border-amber-400/30' }}">
                        {{ ucfirst($consultation->status) }}
                    </span>
                    <a href="{{ route('consultations.print', $consultation) }}" target="_blank"
                       class="inline-flex items-center gap-1.5 bg-white/10 hover:bg-white/20 text-white text-xs font-medium px-3 py-1.5 rounded-lg transition-colors border border-white/20">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <polyline points="6 9 6 2 18 2 18 9"/>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                            <rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Print Record
                    </a>
                </div>
            </div>

            {{-- Body: 3 columns --}}
            @php
                $hasVitals = $consultation->bp || $consultation->pulse || $consultation->temp || $consultation->weight || $consultation->spo2;
                $hasDiagnosis = $consultation->primary_diagnosis || $consultation->provisional_diagnosis || $consultation->diagnosis_notes;
                $hasFindings  = $consultation->chief_complaint || $consultation->notes;
                $hasTreatment = $consultation->treatmentPlans?->count() > 0;
                $hasFollowup  = $consultation->followup_date;
                $hasInvest    = $consultation->investigations_advised;
                $hasRemarks   = $consultation->remarks;

                $medConds  = $consultation->patient->medical_conditions ?? [];
                $allergies = $consultation->patient->allergies ?? [];
                $medAlert  = $consultation->patient->medical_alert ?? null;

                $riskFlags = [];
                if ($medAlert)           $riskFlags[] = ['type' => 'critical', 'label' => 'Medical Alert', 'value' => $medAlert];
                if (count($allergies))   $riskFlags[] = ['type' => 'warning',  'label' => 'Allergies',     'value' => implode(', ', $allergies)];
                if (count($medConds))    $riskFlags[] = ['type' => 'info',     'label' => 'Conditions',    'value' => implode(', ', $medConds)];

                $daysSinceVisit = \Carbon\Carbon::parse($consultation->consultation_date)->diffInDays(now());
            @endphp

            <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-gray-100">

                {{-- COL 1: Clinical Picture --}}
                <div class="p-5 space-y-4">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Clinical Picture</p>

                    {{-- Visit meta --}}
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-400">Date</span>
                            <span class="font-medium text-gray-800">{{ \Carbon\Carbon::parse($consultation->consultation_date)->format('d M Y') }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-400">Visit Type</span>
                            <span class="font-medium text-gray-800 capitalize">{{ $consultation->visit_type ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-400">Attending</span>
                            <span class="font-medium text-gray-800">{{ $consultation->doctor->doctor_name ?? '—' }}</span>
                        </div>
                    </div>

                    {{-- Chief complaint --}}
                    @if($consultation->chief_complaint)
                    <div>
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">Chief Complaint</p>
                        <p class="text-xs text-gray-700 leading-relaxed">{{ $consultation->chief_complaint }}</p>
                    </div>
                    @endif

                    {{-- Diagnosis --}}
                    @if($hasDiagnosis)
                    <div>
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">Diagnosis</p>
                        @if($consultation->primary_diagnosis)
                            <p class="text-xs font-semibold text-gray-800">{{ $consultation->primary_diagnosis }}</p>
                        @endif
                        @if($consultation->secondary_diagnosis)
                            <p class="text-xs text-gray-600 mt-0.5">{{ $consultation->secondary_diagnosis }}</p>
                        @endif
                        @if($consultation->diagnosis_notes)
                            <p class="text-xs text-gray-500 mt-1 italic">{{ Str::limit($consultation->diagnosis_notes, 120) }}</p>
                        @endif
                    </div>
                    @endif

                    {{-- Vitals strip --}}
                    @if($hasVitals)
                    <div>
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Vitals</p>
                        <div class="flex flex-wrap gap-2">
                            @if($consultation->bp)
                            <div class="bg-blue-50 border border-blue-100 rounded-lg px-2 py-1 text-center">
                                <p class="text-[9px] text-blue-500 font-semibold uppercase">BP</p>
                                <p class="text-xs font-bold text-blue-800">{{ $consultation->bp }}</p>
                            </div>
                            @endif
                            @if($consultation->pulse)
                            <div class="bg-rose-50 border border-rose-100 rounded-lg px-2 py-1 text-center">
                                <p class="text-[9px] text-rose-500 font-semibold uppercase">Pulse</p>
                                <p class="text-xs font-bold text-rose-800">{{ $consultation->pulse }}</p>
                            </div>
                            @endif
                            @if($consultation->temp)
                            <div class="bg-amber-50 border border-amber-100 rounded-lg px-2 py-1 text-center">
                                <p class="text-[9px] text-amber-600 font-semibold uppercase">Temp</p>
                                <p class="text-xs font-bold text-amber-800">{{ $consultation->temp }}°</p>
                            </div>
                            @endif
                            @if($consultation->weight)
                            <div class="bg-green-50 border border-green-100 rounded-lg px-2 py-1 text-center">
                                <p class="text-[9px] text-green-600 font-semibold uppercase">Wt</p>
                                <p class="text-xs font-bold text-green-800">{{ $consultation->weight }}kg</p>
                            </div>
                            @endif
                            @if($consultation->spo2)
                            <div class="bg-purple-50 border border-purple-100 rounded-lg px-2 py-1 text-center">
                                <p class="text-[9px] text-purple-600 font-semibold uppercase">SpO₂</p>
                                <p class="text-xs font-bold text-purple-800">{{ $consultation->spo2 }}%</p>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>

                {{-- COL 2: Risk + Actions --}}
                <div class="p-5 space-y-4">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Risk Profile &amp; Actions</p>

                    {{-- Risk flags --}}
                    @if(count($riskFlags))
                    <div class="space-y-2">
                        @foreach($riskFlags as $flag)
                        @php
                            $colorClass = match($flag['type']) {
                                'critical' => 'bg-red-50 border-red-200 text-red-800',
                                'warning'  => 'bg-orange-50 border-orange-200 text-orange-800',
                                default    => 'bg-blue-50 border-blue-200 text-blue-800',
                            };
                            $dotClass = match($flag['type']) {
                                'critical' => 'bg-red-500',
                                'warning'  => 'bg-orange-500',
                                default    => 'bg-blue-400',
                            };
                        @endphp
                        <div class="rounded-lg border px-3 py-2 {{ $colorClass }}">
                            <div class="flex items-center gap-1.5 mb-0.5">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $dotClass }}"></span>
                                <span class="text-[10px] font-bold uppercase tracking-wide opacity-70">{{ $flag['label'] }}</span>
                            </div>
                            <p class="text-xs leading-snug">{{ $flag['value'] }}</p>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg px-3 py-2">
                        <div class="flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <span class="text-xs text-emerald-700 font-medium">No medical risk flags on record</span>
                        </div>
                    </div>
                    @endif

                    {{-- Treatment plan summary --}}
                    @if($hasTreatment)
                    <div>
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Treatment Planned</p>
                        <div class="space-y-1.5">
                            @foreach($consultation->treatmentPlans->take(3) as $plan)
                            @if($plan->items?->count())
                                @foreach($plan->items->take(4) as $item)
                                <div class="flex items-start gap-1.5 text-xs text-gray-700">
                                    <span class="text-indigo-400 shrink-0 mt-0.5">▸</span>
                                    <span>{{ $item->treatment_name ?? $item->name ?? '—' }}
                                        @if($item->tooth_number ?? null)
                                            <span class="text-gray-400">· Tooth {{ $item->tooth_number }}</span>
                                        @endif
                                    </span>
                                </div>
                                @endforeach
                            @endif
                            @endforeach
                        </div>
                    </div>
                    @endif

                    {{-- Investigations --}}
                    @if($hasInvest)
                    <div>
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">Investigations Advised</p>
                        <p class="text-xs text-gray-700 leading-relaxed">{{ $consultation->investigations_advised }}</p>
                    </div>
                    @endif

                    {{-- Remarks --}}
                    @if($hasRemarks)
                    <div>
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">Remarks / Advice</p>
                        <p class="text-xs text-gray-700 leading-relaxed">{{ Str::limit($consultation->remarks, 160) }}</p>
                    </div>
                    @endif
                </div>

                {{-- COL 3: Status + Medico-Legal Footer --}}
                <div class="p-5 space-y-4">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest">Record Status</p>

                    {{-- Follow-up --}}
                    <div class="bg-indigo-50 border border-indigo-100 rounded-xl px-4 py-3">
                        <p class="text-[10px] font-semibold text-indigo-500 uppercase tracking-wide mb-1">Follow-up</p>
                        @if($hasFollowup)
                            @php $followupDate = \Carbon\Carbon::parse($consultation->followup_date); $isPast = $followupDate->isPast(); @endphp
                            <p class="text-sm font-bold {{ $isPast ? 'text-red-700' : 'text-indigo-800' }}">
                                {{ $followupDate->format('d M Y') }}
                            </p>
                            <p class="text-[10px] text-indigo-400 mt-0.5">
                                {{ $isPast ? 'Overdue by '.$followupDate->diffForHumans() : 'In '.$followupDate->diffForHumans() }}
                            </p>
                        @else
                            <p class="text-xs text-indigo-400 italic">Not scheduled</p>
                        @endif
                    </div>

                    {{-- Clinical notes excerpt --}}
                    @if($consultation->notes)
                    <div>
                        <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">Clinical Notes</p>
                        <p class="text-xs text-gray-600 leading-relaxed italic">{{ Str::limit($consultation->notes, 200) }}</p>
                    </div>
                    @endif

                    {{-- Record age --}}
                    <div class="flex items-center justify-between text-xs border-t border-gray-100 pt-3">
                        <span class="text-gray-400">Record age</span>
                        <span class="font-medium text-gray-600">
                            {{ $daysSinceVisit === 0 ? 'Today' : $daysSinceVisit . ' day' . ($daysSinceVisit > 1 ? 's' : '') . ' ago' }}
                        </span>
                    </div>

                    {{-- Medico-legal signature block --}}
                    <div class="border border-dashed border-gray-300 rounded-xl px-4 py-3 bg-gray-50/50 space-y-2">
                        <p class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Verified Record</p>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">Doctor</span>
                                <span class="font-semibold text-gray-800">{{ $consultation->doctor->doctor_name ?? '—' }}</span>
                            </div>
                            @if($consultation->doctor?->designation ?? null)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">Qualification</span>
                                <span class="font-medium text-gray-700">{{ $consultation->doctor->designation }}</span>
                            </div>
                            @endif
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">Entry</span>
                                <span class="font-medium text-gray-700">{{ $consultation->created_at->format('d M Y, h:i A') }}</span>
                            </div>
                            @if($consultation->updated_at != $consultation->created_at)
                            <div class="flex items-center justify-between">
                                <span class="text-gray-400">Last edited</span>
                                <span class="font-medium text-gray-700">{{ $consultation->updated_at->format('d M Y') }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

            </div>
            {{-- /grid --}}

        </div>
        {{-- /clinical-summary card --}}
    </div>
    {{-- /summary wrapper --}}

</div>
{{-- /min-h-screen --}}

@endsection

@push('styles')
<style>
    /* ── Consultation show: scroll behaviour ─────────────────────────── */
    html { scroll-behavior: smooth; }

    /* ── Section anchor offset (sticky header = ~60px) ──────────────── */
    [id^="sec-"], #clinical-summary {
        scroll-margin-top: 72px;
    }

    /* ── Active left-nav link ────────────────────────────────────────── */
    .consultation-nav-link.active {
        color: #4f46e5;
        background-color: #eef2ff;
        font-weight: 600;
    }

    /* ── Clinical Summary section fade-in ────────────────────────────── */
    #clinical-summary {
        animation: summaryFadeIn .35s ease both;
    }
    @keyframes summaryFadeIn {
        from { opacity: 0; transform: translateY(12px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ── Print: hide everything except clinical content ─────────────── */
    @media print {
        header, aside, footer,
        .no-print,
        [x-data*="cipOpen"],
        #sec-header .flex.flex-wrap.items-start.justify-between > div:last-child {
            display: none !important;
        }
        main { width: 100% !important; }
    }

    /* ── CIP aside: thin scrollbar ───────────────────────────────────── */
    .sticky.top-20.space-y-3::-webkit-scrollbar      { width: 4px; }
    .sticky.top-20.space-y-3::-webkit-scrollbar-track{ background: transparent; }
    .sticky.top-20.space-y-3::-webkit-scrollbar-thumb{ background: #c4b5fd; border-radius: 2px; }

    /* ── Card section hover highlight ────────────────────────────────── */
    section[id] {
        transition: box-shadow .15s ease;
    }
    section[id]:target {
        box-shadow: 0 0 0 2px #6366f1, 0 4px 16px 0 rgba(99,102,241,.12);
    }
</style>
@endpush

@push('scripts')
<script>
function consultationShow() {
    return {
        // ── CIP Panel state ────────────────────────────────────────────
        cipOpen:       false,
        cipMode:       'default',   // 'default' | 'section' | 'tooth'
        cipSection:    null,        // { tips:[], checklist:[], red_flags:[] }
        cipTooth:      null,        // { timeline:[] }
        cipToothNum:   '',
            cipActiveLabel:'',
        cipLoading:    false,
        cipError:      '',

        cipGhostChecklist() {
            return {
                checked: {},
                toggle(item) { this.checked[item] = !this.checked[item]; }
            };
        },

        cipAssist(detail) {
            if (!detail) return;
            const { type, section, label, tooth } = detail;
            this.cipOpen  = true;
            this.cipError = '';
            if (type === 'tooth') {
                this.cipToothTimeline(tooth);
                return;
            }
            this.cipMode        = 'section';
            this.cipActiveLabel = label || section || '';
            this.cipLoading     = true;
            this.cipSection     = null;
            fetch('/consult-assist/section-guidance', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                body: JSON.stringify({ section, complaint: '{{ addslashes($consultation->chief_complaint ?? '') }}', diagnosis: '{{ addslashes($consultation->primary_diagnosis ?? '') }}' })
            })
            .then(r => r.json())
            .then(data => { this.cipSection = data; this.cipLoading = false; })
            .catch(() => { this.cipError = 'Could not load guidance.'; this.cipLoading = false; });
        },

        cipToothTimeline(tooth) {
            this.cipMode       = 'tooth';
            this.cipToothNum   = tooth;
            this.cipActiveLabel= 'Tooth ' + tooth;
            this.cipLoading    = true;
            this.cipTooth      = null;
            fetch('/consult-assist/tooth-timeline', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '' },
                body: JSON.stringify({ patient_id: {{ $consultation->patient_id }}, tooth })
            })
            .then(r => r.json())
            .then(data => { this.cipTooth = data; this.cipLoading = false; })
            .catch(() => { this.cipError = 'Could not load tooth timeline.'; this.cipLoading = false; });
        },

        cipReset() {
            this.cipMode    = 'default';
            this.cipSection = null;
            this.cipTooth   = null;
            this.cipError   = '';
        }
    };
}
</script>
@endpush
