{{--
|==========================================================================
| Dentfluence OS — Sidebar Nav Item Sub-Component
| File: resources/views/components/sidebar-item.blade.php
|
| Variables:
|   $href        string   — route URL
|   $label       string   — visible label text
|   $icon        string   — SVG path(s) for the Lucide icon (inner content only)
|   $badgeCount  int|null — optional badge number (0 = hidden)
|   $badgeType   string   — 'brand' | 'danger' | 'warning'  (default: 'brand')
|
| Usage:
|   @include('components.sidebar-item', [
|       'href'       => route('appointments.index'),
|       'label'      => 'Appointments',
|       'icon'       => '<rect .../><line .../>',
|       'badgeCount' => 3,
|       'badgeType'  => 'danger',
|   ])
|==========================================================================
--}}

@php
    $badgeType  = $badgeType  ?? 'brand';
    $badgeCount = $badgeCount ?? 0;
    $disabled   = $disabled   ?? false;
    $isActive   = !$disabled
               && (request()->is(ltrim(parse_url($href, PHP_URL_PATH), '/') . '*')
               || request()->is(ltrim(parse_url($href, PHP_URL_PATH), '/')));
@endphp

@if($disabled)
{{-- Locked nav item — visible but not clickable --}}
<a
    href="#"
    class="df-nav-item df-nav-locked"
    data-tooltip="{{ $label }}"
    data-locked-label="{{ $label }}"
    aria-disabled="true"
    title="{{ $label }} — access restricted"
    onclick="dfNavAccessDenied(event, '{{ $label }}')"
    style="opacity:0.38; cursor:not-allowed; pointer-events:auto;"
>
    <span class="df-nav-icon" aria-hidden="true">
        <svg
            width="16" height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.65"
            stroke-linecap="round"
            stroke-linejoin="round"
        >{!! $icon !!}</svg>
    </span>

    <span class="df-nav-label" style="flex:1; overflow:hidden; text-overflow:ellipsis; transition:opacity 200ms,width 200ms;">
        {{ $label }}
    </span>

    {{-- Lock icon instead of badge --}}
    <span class="df-nav-icon df-nav-label" aria-hidden="true" style="opacity:0.6; margin-left:auto;">
        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
        </svg>
    </span>
</a>
@else
<a
    href="{{ $href }}"
    class="df-nav-item {{ $isActive ? 'df-nav-active' : '' }}"
    data-nav-href="{{ parse_url($href, PHP_URL_PATH) }}"
    data-tooltip="{{ $label }}"
    aria-current="{{ $isActive ? 'page' : 'false' }}"
    title="{{ $label }}"
>
    {{-- Icon --}}
    <span class="df-nav-icon" aria-hidden="true">
        <svg
            width="16" height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.65"
            stroke-linecap="round"
            stroke-linejoin="round"
        >{!! $icon !!}</svg>
    </span>

    {{-- Label --}}
    <span class="df-nav-label" style="flex:1; overflow:hidden; text-overflow:ellipsis; transition:opacity 200ms,width 200ms;">
        {{ $label }}
    </span>

    {{-- Badge (only when count > 0) --}}
    @if ($badgeCount > 0)
        <span
            class="df-nav-badge badge-{{ $badgeType }} df-nav-badge-text"
            aria-label="{{ $badgeCount }} pending"
        >
            {{ $badgeCount > 99 ? '99+' : $badgeCount }}
        </span>
    @endif
</a>
@endif
