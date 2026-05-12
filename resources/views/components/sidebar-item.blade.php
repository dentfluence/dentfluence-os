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
    $isActive   = request()->is(ltrim(parse_url($href, PHP_URL_PATH), '/') . '*')
               || request()->is(ltrim(parse_url($href, PHP_URL_PATH), '/'));
@endphp

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
