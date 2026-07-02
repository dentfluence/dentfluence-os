{{--
    Component: communication/nav-item
    Usage: @include('components.communication.nav-item', ['module' => $module])

    $module = [
        'key'         => string,
        'label'       => string,
        'route'       => string,
        'description' => string,
        'count'       => int,
        'status'      => 'active'|'coming_soon',
    ]
--}}
@php
    $isActive   = $module['status'] === 'active';
    $routeUrl   = $isActive ? route($module['route']) : '#';
    $countBadge = $module['count'] > 0;
@endphp

<a href="{{ $routeUrl }}"
   class="comm-nav-item {{ !$isActive ? 'comm-nav-item--disabled' : '' }}"
   @if(!$isActive) aria-disabled="true" tabindex="-1" @endif>

    <div class="comm-nav-item__inner">

        {{-- Icon slot --}}
        <div class="comm-nav-item__icon-wrap" aria-hidden="true">
            @include('components.communication.module-badge', ['key' => $module['key']])
        </div>

        {{-- Text --}}
        <div class="comm-nav-item__text">
            <span class="comm-nav-item__label">{{ $module['label'] }}</span>
            <span class="comm-nav-item__desc">{{ $module['description'] }}</span>
        </div>

        {{-- Badge --}}
        <div class="comm-nav-item__badge-wrap">
            @if($countBadge)
                @include('components.communication.status-chip', [
                    'text'  => $module['count'],
                    'color' => 'urgent',
                    'size'  => 'sm',
                ])
            @endif
            @if(!$isActive)
                @include('components.communication.status-chip', [
                    'text'  => 'Soon',
                    'color' => 'muted',
                    'size'  => 'sm',
                ])
            @endif
        </div>

        {{-- Arrow --}}
        @if($isActive)
            <svg class="comm-nav-item__arrow" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"/></svg>
        @endif
    </div>
</a>
