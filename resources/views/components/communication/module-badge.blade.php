{{-- =====================================================================
     Component: communication/module-badge
     Renders the colored icon badge for each submodule.
     Usage: @include('components.communication.module-badge', ['key' => 'manager'])
     ===================================================================== --}}
@php
    $icons = [
        'manager'       => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'prm'           => '<rect x="3" y="3" width="5" height="18"/><rect x="10" y="3" width="5" height="12"/><rect x="17" y="3" width="5" height="8"/>',
        'followup'      => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'opportunities' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>',
        'tasks'         => '<path d="M5 4h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/><polyline points="9 11 12 14 22 4"/>',
        'timeline'      => '<circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="6"/><line x1="12" y1="18" x2="12" y2="22"/>',
        'templates'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/>',
        'huddle'        => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'dashboard'     => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>',
    ];
    $colors = [
        'manager'       => 'teal',
        'prm'           => 'blue',
        'followup'      => 'amber',
        'opportunities' => 'coral',
        'tasks'         => 'purple',
        'timeline'      => 'green',
        'templates'     => 'gray',
        'huddle'        => 'blue',
        'dashboard'     => 'gray',
    ];
    $svgPath  = $icons[$key]  ?? $icons['dashboard'];
    $color    = $colors[$key] ?? 'gray';
@endphp

<div class="comm-module-badge comm-module-badge--{{ $color }}" aria-hidden="true">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        {!! $svgPath !!}
    </svg>
</div>
