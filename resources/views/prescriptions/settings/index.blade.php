@extends('layouts.app')
@section('page-title', 'Prescription Settings')

@section('content')
<div class="p-6 max-w-5xl mx-auto">
    <div class="mb-8">
        <h1 class="text-2xl font-display font-semibold text-brand-800">Prescription Settings</h1>
        <p class="text-sm text-gray-500 mt-0.5">Configure drug master, templates, CDSS rules, and defaults</p>
    </div>

    @php
    $sections = [
        [
            'title'   => 'Drug Master',
            'desc'    => 'Complete drug registry with safety profiles, defaults, and clinical flags',
            'icon'    => '',
            'route'   => 'rx.drugs.index',
            'badge'   => null,
        ],
        [
            'title'   => 'Generic Master',
            'desc'    => 'Manage generic drug names and drug classes',
            'icon'    => '',
            'route'   => 'rx.settings.generics',
            'badge'   => null,
        ],
        [
            'title'   => 'Drug Categories',
            'desc'    => 'Analgesic, Antibiotic, Antifungal, Antiseptic, PPI…',
            'icon'    => '',
            'route'   => 'rx.settings.categories',
            'badge'   => null,
        ],
        [
            'title'   => 'Dose Templates',
            'desc'    => 'OD, BD, TDS, SOS — frequency presets with morning/afternoon/night',
            'icon'    => '',
            'route'   => 'rx.settings.dose-templates',
            'badge'   => null,
        ],
        [
            'title'   => 'Duration Templates',
            'desc'    => '3 Days, 5 Days, 1 Week, 1 Month — quick duration options',
            'icon'    => '',
            'route'   => 'rx.settings.duration-templates',
            'badge'   => null,
        ],
        [
            'title'   => 'Food Instructions',
            'desc'    => 'Before Food, After Food, With Food — with multilingual labels',
            'icon'    => '',
            'route'   => 'rx.settings.food-instructions',
            'badge'   => null,
        ],
        [
            'title'   => 'Routes of Administration',
            'desc'    => 'Oral, Topical, Subgingival, Mouthwash, IM, IV…',
            'icon'    => '',
            'route'   => 'rx.settings.routes',
            'badge'   => null,
        ],
        [
            'title'   => 'Warning Rules (CDSS)',
            'desc'    => 'Drug-condition warnings: NSAIDs + gastric ulcer, steroids + diabetes…',
            'icon'    => '',
            'route'   => 'rx.settings.warning-rules',
            'badge'   => 'CDSS',
        ],
        [
            'title'   => 'Prescription Templates',
            'desc'    => 'RCT Pain, Post-Extraction, Pericoronitis, Scaling, Ulcers…',
            'icon'    => '',
            'route'   => 'rx.settings.prescription-templates',
            'badge'   => null,
        ],
    ];
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($sections as $s)
        <a href="{{ route($s['route']) }}"
           class="group bg-white rounded-xl border border-gray-200 p-5 hover:border-brand-300 hover:shadow-md transition flex flex-col gap-2">
            <div class="flex items-start justify-between">
                <span class="text-2xl">{{ $s['icon'] }}</span>
                @if($s['badge'])
                    <span class="px-2 py-0.5 text-[10px] font-bold bg-purple-100 text-purple-700 rounded-full uppercase tracking-wide">{{ $s['badge'] }}</span>
                @endif
            </div>
            <div>
                <p class="font-semibold text-gray-800 group-hover:text-brand-700 text-sm">{{ $s['title'] }}</p>
                <p class="text-xs text-gray-500 mt-0.5 leading-relaxed">{{ $s['desc'] }}</p>
            </div>
        </a>
        @endforeach
    </div>
</div>
@endsection
