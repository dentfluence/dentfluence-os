{{-- resources/views/consultations/show.blade.php --}}
@extends('layouts.app')

@section('title', 'Consultation — ' . $consultation->patient->name)

@section('content')
<div class="min-h-screen bg-gray-50">

    {{-- ── Top Bar ──────────────────────────────────────────────────────────── --}}
    <div class="bg-white border-b border-gray-200 sticky top-0 z-20 shadow-sm">
        <div class="max-w-screen-xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <a href="{{ route('patients.show', $consultation->patient_id) }}"
                   class="text-gray-400 hover:text-gray-700 transition-colors">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2"
                         viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round"
                         d="M15 19l-7-7 7-7"/></svg>
                </a>
                <h1 class="text-lg font-semibold text-gray-800">Consultation Record</h1>
            </div>
            <a href="{{ route('consultations.edit', $consultation) }}"
               class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                     viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round"
                     d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </a>
        </div>
    </div>

    <div class="max-w-screen-xl mx-auto px-4 py-6 flex gap-6">

        {{-- ── Sidebar Jump-links ───────────────────────────────────────────── --}}
        <aside class="hidden lg:block w-52 shrink-0">
            <nav class="sticky top-20 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest px-4 pt-4 pb-2">
                    Sections
                </p>
                @php
                $sections = [
                    'header'          => 'Overview',
                    'complaint'       => 'Chief Complaint',
                    'photographs'     => 'Photographs',
                    'scans'           => 'Scans',
                    'investigations'  => 'Investigations',
                    'clinical'        => 'Clinical Findings',
                    'radio'           => 'Radiographic',
                    'dbm'             => 'DBM Checklist',
                    'rxinstr'         => 'Rx & Instructions',
                    'diagnosis'       => 'Diagnosis',
                    'txoptions'       => 'Treatment Options',
                    'txplans'         => 'Treatment Plans',
                    'finishing'       => 'Finishing',
                ];
                @endphp
                <ul class="pb-3">
                    @foreach($sections as $id => $label)
                    <li>
                        <a href="#{{ $id }}"
                           class="block text-sm text-gray-600 hover:text-indigo-600 hover:bg-indigo-50 px-4 py-2 transition-colors">
                            {{ $label }}
                        </a>
                    </li>
                    @endforeach
                </ul>
            </nav>
        </aside>

        {{-- ── Main Content ─────────────────────────────────────────────────── --}}
        <main class="flex-1 min-w-0 space-y-5">

            {{-- 1. Header / Overview Card --}}
            <section id="header" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-900">
                            {{ $consultation->patient->name }}
                        </h2>
                        <p class="text-gray-500 text-sm mt-0.5">
                            Dr. {{ $consultation->doctor->name }}
                            &middot; Branch #{{ $consultation->branch_id }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 flex-wrap">
                        {{-- Visit type badge --}}
                        @if($consultation->visit_type)
                        @php
                        $vtColors = [
                            'emergency' => 'bg-red-100 text-red-700',
                            'routine'   => 'bg-blue-100 text-blue-700',
                            'followup'  => 'bg-purple-100 text-purple-700',
                        ];
                        @endphp
                        <span class="inline-block text-xs font-semibold px-3 py-1 rounded-full {{ $vtColors[$consultation->visit_type] ?? 'bg-gray-100 text-gray-600' }}">
                            {{ ucfirst($consultation->visit_type) }}
                        </span>
                        @endif

                        {{-- Status badge --}}
                        <span class="inline-block text-xs font-semibold px-3 py-1 rounded-full
                            {{ $consultation->status === 'completed' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
                            {{ ucfirst($consultation->status) }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 text-sm text-gray-600 flex items-center gap-2">
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" stroke-width="2"
                         viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round"
                         d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    {{ \Carbon\Carbon::parse($consultation->consultation_date)->format('D, d M Y') }}
                </div>
            </section>

            {{-- 2. Chief Complaint --}}
            <section id="complaint" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Chief Complaint</h3>
                </div>
                <dl class="px-6 py-4 grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3 text-sm">
                    @foreach([
                        'Complaint'   => $consultation->chief_complaint,
                        'Duration'    => $consultation->complaint_duration,
                        'Severity'    => $consultation->severity,
                        'Tooth Area'  => $consultation->tooth_area,
                        'Location'    => $consultation->location,
                        'Notes'       => $consultation->complaint_notes,
                    ] as $label => $value)
                    <div>
                        <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ $label }}</dt>
                        <dd class="mt-0.5 text-gray-800">{{ $value ?: '—' }}</dd>
                    </div>
                    @endforeach
                </dl>
            </section>

            {{-- 3. Photographs --}}
            <section id="photographs" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Photographs</h3>
                </div>
                <div class="px-6 py-4">
                    @php $photos = $consultation->photographs ?? []; @endphp
                    @if(count($photos))
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-3">
                        @foreach($photos as $photo)
                        <div class="aspect-square rounded-lg overflow-hidden bg-gray-100 border border-gray-200">
                            <img src="{{ $photo }}" alt="Photograph"
                                 class="w-full h-full object-cover" loading="lazy">
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-sm text-gray-400 italic">None recorded.</p>
                    @endif
                </div>
            </section>

            {{-- 4. Scans --}}
            <section id="scans" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Scans</h3>
                </div>
                <div class="px-6 py-4 text-sm space-y-3">
                    <div>
                        <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Scan Date</span>
                        <p class="mt-0.5 text-gray-800">
                            {{ $consultation->scan_date
                                ? \Carbon\Carbon::parse($consultation->scan_date)->format('d M Y')
                                : '—' }}
                        </p>
                    </div>
                    @php $scanFiles = $consultation->scan_files ?? []; @endphp
                    <div>
                        <span class="text-xs font-medium text-gray-400 uppercase tracking-wide">Files</span>
                        @if(count($scanFiles))
                        <ul class="mt-1 space-y-1">
                            @foreach($scanFiles as $file)
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor"
                                     stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                <a href="{{ $file }}" target="_blank"
                                   class="text-indigo-600 hover:underline truncate">
                                    {{ basename($file) }}
                                </a>
                            </li>
                            @endforeach
                        </ul>
                        @else
                        <p class="mt-0.5 text-gray-400 italic">None uploaded.</p>
                        @endif
                    </div>
                </div>
            </section>

            {{-- 5. Investigations --}}
            <section id="investigations" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Investigations</h3>
                </div>
                <div class="px-6 py-4 text-sm">
                    @php
                    $investigations = $consultation->investigations ?? [];
                    $investigationDetails = $consultation->investigation_details ?? [];
                    @endphp
                    @if(count($investigations))
                    <ul class="space-y-2">
                        @foreach($investigations as $i => $item)
                        <li class="flex items-start gap-2">
                            <span class="mt-0.5 w-4 h-4 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center text-xs font-bold shrink-0">✓</span>
                            <div>
                                <span class="font-medium text-gray-800">{{ $item }}</span>
                                @if(isset($investigationDetails[$i]))
                                <p class="text-gray-500 text-xs mt-0.5">{{ $investigationDetails[$i] }}</p>
                                @endif
                            </div>
                        </li>
                        @endforeach
                    </ul>
                    @else
                    <p class="text-gray-400 italic">None recorded.</p>
                    @endif
                </div>
            </section>

            {{-- 6. Clinical Findings --}}
            <section id="clinical" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Clinical Findings</h3>
                </div>
                <div class="px-6 py-4 text-sm space-y-4">
                    @php $clinicalData = $consultation->clinical_data ?? []; @endphp
                    @if(count($clinicalData))
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-sm border-collapse">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="px-3 py-2 font-medium text-gray-500 border border-gray-200 w-1/3">Finding</th>
                                    <th class="px-3 py-2 font-medium text-gray-500 border border-gray-200">Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($clinicalData as $key => $value)
                                <tr class="even:bg-gray-50">
                                    <td class="px-3 py-2 border border-gray-200 font-medium text-gray-700">{{ $key }}</td>
                                    <td class="px-3 py-2 border border-gray-200 text-gray-600">
                                        {{ is_array($value) ? implode(', ', $value) : $value }}
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-gray-400 italic">No clinical findings recorded.</p>
                    @endif

                    {{-- Tooth chart placeholder --}}
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Tooth Chart</p>
                        @php $chartData = $consultation->chart_data ?? []; @endphp
                        @if(count($chartData))
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($chartData as $tooth)
                            <span class="inline-block bg-indigo-100 text-indigo-700 text-xs font-semibold px-2 py-0.5 rounded">
                                {{ $tooth }}
                            </span>
                            @endforeach
                        </div>
                        @else
                        <div class="w-full h-20 rounded-lg border-2 border-dashed border-gray-200 flex items-center justify-center text-gray-300 text-sm">
                            Tooth chart — no teeth selected
                        </div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- 7. Radiographic Findings --}}
            <section id="radio" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Radiographic Findings</h3>
                </div>
                <div class="px-6 py-4 text-sm">
                    @php $radioData = $consultation->radio_data ?? []; @endphp
                    @if(count($radioData))
                    <div class="flex flex-wrap gap-2">
                        @foreach($radioData as $item)
                        <span class="inline-flex items-center gap-1.5 bg-gray-100 text-gray-700 text-xs font-medium px-3 py-1 rounded-full">
                            <span class="w-2 h-2 rounded-full bg-indigo-500 inline-block"></span>
                            {{ is_array($item) ? ($item['label'] ?? json_encode($item)) : $item }}
                        </span>
                        @endforeach
                    </div>
                    @else
                    <p class="text-gray-400 italic">No radiographic findings recorded.</p>
                    @endif
                </div>
            </section>

            {{-- 8. DBM Checklist --}}
            <section id="dbm" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">DBM Checklist</h3>
                    @if($consultation->dbm_score !== null)
                    @php
                    $score = $consultation->dbm_score;
                    $scoreColor = $score >= 70 ? 'bg-green-100 text-green-700'
                                : ($score >= 40 ? 'bg-yellow-100 text-yellow-700'
                                               : 'bg-red-100 text-red-700');
                    @endphp
                    <span class="text-sm font-bold px-3 py-1 rounded-full {{ $scoreColor }}">
                        Score: {{ $score }}
                    </span>
                    @endif
                </div>
                <div class="px-6 py-4 text-sm space-y-4">
                    @php $checklist = $consultation->dbm_checklist ?? []; @endphp
                    @if(count($checklist))
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        @foreach($checklist as $item => $checked)
                        <div class="flex items-center gap-2">
                            <span class="{{ $checked ? 'text-green-500' : 'text-gray-300' }}">
                                {{ $checked ? '✓' : '✗' }}
                            </span>
                            <span class="{{ $checked ? 'text-gray-800' : 'text-gray-400' }}">{{ $item }}</span>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <p class="text-gray-400 italic">No checklist items recorded.</p>
                    @endif

                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-gray-100">
                        @foreach([
                            'Tooth Shade'     => $consultation->dbm_tooth_shade,
                            'Whitening'       => $consultation->dbm_whitening,
                            'Tooth Monitored' => $consultation->dbm_tooth_monitored,
                        ] as $label => $val)
                        <div>
                            <dt class="text-xs font-medium text-gray-400 uppercase tracking-wide">{{ $label }}</dt>
                            <dd class="mt-0.5 text-gray-800">{{ $val ?: '—' }}</dd>
                        </div>
                        @endforeach
                    </dl>
                </div>
            </section>

            {{-- 9. Prescriptions & Instructions --}}
            <section id="rxinstr" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Prescriptions &amp; Instructions</h3>
                </div>
                <div class="px-6 py-4 grid grid-cols-1 sm:grid-cols-2 gap-6 text-sm">
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Prescriptions</p>
                        @php $rxList = $consultation->prescriptions ?? []; @endphp
                        @if(count($rxList))
                        <ul class="space-y-1">
                            @foreach($rxList as $rx)
                            <li class="flex items-start gap-2">
                                <span class="text-indigo-400 mt-0.5">•</span>
                                <span class="text-gray-700">{{ is_array($rx) ? ($rx['name'] ?? json_encode($rx)) : $rx }}</span>
                            </li>
                            @endforeach
                        </ul>
                        @else
                        <p class="text-gray-400 italic">None.</p>
                        @endif
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Instructions</p>
                        @php $instrList = $consultation->instructions ?? []; @endphp
                        @if(count($instrList))
                        <ul class="space-y-1">
                            @foreach($instrList as $instr)
                            <li class="flex items-start gap-2">
                                <span class="text-teal-400 mt-0.5">•</span>
                                <span class="text-gray-700">{{ is_array($instr) ? ($instr['text'] ?? json_encode($instr)) : $instr }}</span>
                            </li>
                            @endforeach
                        </ul>
                        @else
                        <p class="text-gray-400 italic">None.</p>
                        @endif
                    </div>
                </div>
            </section>

            {{-- 10. Diagnosis --}}
            <section id="diagnosis" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Diagnosis</h3>
                </div>
                <div class="px-6 py-4 space-y-3 text-sm">
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
                        @if($consultation->risk_assessment)
                        @php
                        $riskColor = match(strtolower($consultation->risk_assessment)) {
                            'high'   => 'bg-red-100 text-red-700',
                            'medium' => 'bg-yellow-100 text-yellow-700',
                            'low'    => 'bg-green-100 text-green-700',
                            default  => 'bg-gray-100 text-gray-600',
                        };
                        @endphp
                        <span class="ml-2 text-xs font-semibold px-2 py-0.5 rounded-full {{ $riskColor }}">
                            {{ ucfirst($consultation->risk_assessment) }}
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

            {{-- 11. Treatment Options --}}
            <section id="txoptions" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Treatment Options</h3>
                </div>
                <div class="px-6 py-4">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 text-sm">
                        @php
                        $txGroups = [
                            ['label' => 'Emergency',     'color' => 'red',    'items' => $consultation->tx_emergency ?? []],
                            ['label' => 'Protective',    'color' => 'yellow', 'items' => $consultation->tx_protective ?? []],
                            ['label' => 'Transformative','color' => 'green',  'items' => $consultation->tx_transformative ?? []],
                        ];
                        @endphp
                        @foreach($txGroups as $group)
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-{{ $group['color'] }}-600 mb-2">
                                {{ $group['label'] }}
                            </p>
                            @if(count($group['items']))
                            <ul class="space-y-1">
                                @foreach($group['items'] as $item)
                                <li class="flex items-start gap-1.5">
                                    <span class="text-{{ $group['color'] }}-400 mt-0.5">•</span>
                                    <span class="text-gray-700">{{ is_array($item) ? ($item['name'] ?? json_encode($item)) : $item }}</span>
                                </li>
                                @endforeach
                            </ul>
                            @else
                            <p class="text-gray-400 italic">None.</p>
                            @endif
                        </div>
                        @endforeach
                    </div>

                    @php $txTeeth = $consultation->tx_teeth ?? []; @endphp
                    @if(count($txTeeth))
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Teeth Involved</p>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($txTeeth as $tooth)
                            <span class="bg-gray-100 text-gray-700 text-xs font-semibold px-2 py-0.5 rounded">{{ $tooth }}</span>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </section>

            {{-- 12. Treatment Plans --}}
            <section id="txplans" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Treatment Plans</h3>
                </div>
                <div class="px-6 py-4 space-y-6 text-sm">

                    {{-- Best Plan --}}
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <p class="font-medium text-gray-700">Best Plan</p>
                            @if($consultation->aocp_best)
                            <span class="text-xs bg-indigo-100 text-indigo-700 font-semibold px-2 py-0.5 rounded-full">AOCP</span>
                            @endif
                        </div>
                        @php $bestPlan = $consultation->treatment_plan_best ?? []; @endphp
                        @if(count($bestPlan))
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead>
                                    <tr class="bg-indigo-50">
                                        <th class="px-3 py-2 border border-indigo-100 text-indigo-700 font-medium">#</th>
                                        <th class="px-3 py-2 border border-indigo-100 text-indigo-700 font-medium">Item</th>
                                        <th class="px-3 py-2 border border-indigo-100 text-indigo-700 font-medium text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($bestPlan as $i => $row)
                                    <tr class="even:bg-gray-50">
                                        <td class="px-3 py-2 border border-gray-200 text-gray-500">{{ $i + 1 }}</td>
                                        <td class="px-3 py-2 border border-gray-200 text-gray-800">
                                            {{ is_array($row) ? ($row['name'] ?? json_encode($row)) : $row }}
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 text-gray-800 text-right">
                                            {{ is_array($row) && isset($row['amount']) ? number_format($row['amount'], 2) : '—' }}
                                        </td>
                                    </tr>
                                    @endforeach
                                    <tr class="bg-indigo-50 font-semibold">
                                        <td class="px-3 py-2 border border-indigo-100" colspan="2">Total</td>
                                        <td class="px-3 py-2 border border-indigo-100 text-right">
                                            {{ number_format($consultation->treatment_plan_best_total ?? 0, 2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        @if($consultation->aocp_best_plan)
                        <p class="mt-2 text-xs text-gray-500">AOCP Note: {{ $consultation->aocp_best_plan }}</p>
                        @endif
                        @else
                        <p class="text-gray-400 italic">No best plan recorded.</p>
                        @endif
                    </div>

                    {{-- Acceptable Plan --}}
                    <div>
                        <div class="flex items-center gap-3 mb-2">
                            <p class="font-medium text-gray-700">Acceptable Plan</p>
                            @if($consultation->aocp_acceptable)
                            <span class="text-xs bg-teal-100 text-teal-700 font-semibold px-2 py-0.5 rounded-full">AOCP</span>
                            @endif
                        </div>
                        @php $accPlan = $consultation->treatment_plan_acceptable ?? []; @endphp
                        @if(count($accPlan))
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse text-sm">
                                <thead>
                                    <tr class="bg-teal-50">
                                        <th class="px-3 py-2 border border-teal-100 text-teal-700 font-medium">#</th>
                                        <th class="px-3 py-2 border border-teal-100 text-teal-700 font-medium">Item</th>
                                        <th class="px-3 py-2 border border-teal-100 text-teal-700 font-medium text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($accPlan as $i => $row)
                                    <tr class="even:bg-gray-50">
                                        <td class="px-3 py-2 border border-gray-200 text-gray-500">{{ $i + 1 }}</td>
                                        <td class="px-3 py-2 border border-gray-200 text-gray-800">
                                            {{ is_array($row) ? ($row['name'] ?? json_encode($row)) : $row }}
                                        </td>
                                        <td class="px-3 py-2 border border-gray-200 text-gray-800 text-right">
                                            {{ is_array($row) && isset($row['amount']) ? number_format($row['amount'], 2) : '—' }}
                                        </td>
                                    </tr>
                                    @endforeach
                                    <tr class="bg-teal-50 font-semibold">
                                        <td class="px-3 py-2 border border-teal-100" colspan="2">Total</td>
                                        <td class="px-3 py-2 border border-teal-100 text-right">
                                            {{ number_format($consultation->treatment_plan_acc_total ?? 0, 2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        @if($consultation->aocp_acceptable_plan)
                        <p class="mt-2 text-xs text-gray-500">AOCP Note: {{ $consultation->aocp_acceptable_plan }}</p>
                        @endif
                        @else
                        <p class="text-gray-400 italic">No acceptable plan recorded.</p>
                        @endif
                    </div>
                </div>
            </section>

            {{-- 13. Finishing --}}
            <section id="finishing" class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h3 class="font-semibold text-gray-800">Finishing</h3>
                </div>
                <div class="px-6 py-4 space-y-4 text-sm">
                    @if($consultation->finishing_notes)
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Notes</p>
                        <p class="mt-0.5 text-gray-800">{{ $consultation->finishing_notes }}</p>
                    </div>
                    @endif

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Next Visit Type</p>
                            <p class="mt-0.5 text-gray-800">{{ $consultation->next_visit_type ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Next Visit Date</p>
                            <p class="mt-0.5 text-gray-800">
                                {{ $consultation->next_visit_date
                                    ? \Carbon\Carbon::parse($consultation->next_visit_date)->format('d M Y')
                                    : '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Recall Interval</p>
                            <p class="mt-0.5 text-gray-800">
                                {{ $consultation->recall_interval ?: '—' }}
                                @if($consultation->recall_custom)
                                <span class="text-gray-500">({{ $consultation->recall_custom }})</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Responsible</p>
                            <p class="mt-0.5 text-gray-800">
                                {{ optional($consultation->responsible)->name ?? '—' }}
                            </p>
                        </div>
                    </div>

                    {{-- Attachments --}}
                    @php $attachments = $consultation->attachments ?? []; @endphp
                    @if(count($attachments))
                    <div>
                        <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2">Attachments</p>
                        <ul class="space-y-1">
                            @foreach($attachments as $att)
                            <li class="flex items-center gap-2">
                                <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" stroke="currentColor"
                                     stroke-width="2" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                                </svg>
                                <a href="{{ is_array($att) ? ($att['url'] ?? '#') : $att }}"
                                   target="_blank"
                                   class="text-indigo-600 hover:underline truncate text-sm">
                                    {{ is_array($att) ? ($att['name'] ?? basename($att['url'] ?? '')) : basename($att) }}
                                </a>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                </div>
            </section>

            {{-- Bottom Action Bar --}}
            <div class="flex items-center justify-between pt-2 pb-8">
                <a href="{{ route('patients.show', $consultation->patient_id) }}"
                   class="inline-flex items-center gap-2 text-sm text-gray-600 hover:text-gray-900 border border-gray-300 px-4 py-2 rounded-lg hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                         viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round"
                         d="M15 19l-7-7 7-7"/></svg>
                    Back to Patient
                </a>
                <a href="{{ route('consultations.edit', $consultation) }}"
                   class="inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2"
                         viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round"
                         d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Edit Consultation
                </a>
            </div>

        </main>
    </div>
</div>
@endsection
