{{-- =====================================================================
     Component: communication/empty-state
     Usage: @include('components.communication.empty-state', [
         'title'      => 'No follow-ups pending',
         'message'    => 'All caught up. Check back after new appointments.',
         'actionUrl'  => route('communication.manager.log.form'),   // optional
         'actionText' => 'Log Communication',                        // optional
         'icon'       => 'check',  // check|clock|inbox|search (default: inbox)
     ])
     ===================================================================== --}}
@php
    $iconKey = $icon ?? 'inbox';
    $svgIcons = [
        'check'  => '<polyline points="20 6 9 17 4 12"/>',
        'clock'  => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'inbox'  => '<polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11L2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
    ];
    $svgPath = $svgIcons[$iconKey] ?? $svgIcons['inbox'];
@endphp

<div class="comm-empty-state" role="status">
    <div class="comm-empty-state__icon" aria-hidden="true">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            {!! $svgPath !!}
        </svg>
    </div>
    <h3 class="comm-empty-state__title">{{ $title ?? 'Nothing here yet' }}</h3>
    @if(isset($message))
        <p class="comm-empty-state__message">{{ $message }}</p>
    @endif
    @if(isset($actionUrl) && isset($actionText))
        <a href="{{ $actionUrl }}" class="comm-empty-state__action">
            {{ $actionText }}
        </a>
    @endif
</div>
