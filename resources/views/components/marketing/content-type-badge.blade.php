{{--
|==========================================================================
| Component: content-type-badge
| Usage: <x-marketing.content-type-badge type="reel" />
|
| Props:
|   type — 'reel' | 'post' | 'carousel' | 'story' | 'blog' | 'offer'
|==========================================================================
--}}
@props(['type' => 'post'])

@php
$types = [
    'reel'     => ['label' => 'Reel',     'color' => '#e1306c', 'bg' => 'rgba(225,48,108,0.10)'],
    'post'     => ['label' => 'Post',     'color' => '#0284c7', 'bg' => 'rgba(2,132,199,0.10)'],
    'carousel' => ['label' => 'Carousel', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.10)'],
    'story'    => ['label' => 'Story',    'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.10)'],
    'blog'     => ['label' => 'Blog',     'color' => '#6a0f70', 'bg' => 'rgba(106,15,112,0.08)'],
    'offer'    => ['label' => 'Offer',    'color' => '#16a34a', 'bg' => 'rgba(22,163,74,0.10)'],
];

$t = $types[$type] ?? $types['post'];
@endphp

<span style="
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px 2px 6px;
    border-radius: 20px;
    background: {{ $t['bg'] }};
    font-family: 'Inter', sans-serif;
    font-size: 11px;
    font-weight: 600;
    color: {{ $t['color'] }};
    white-space: nowrap;
    letter-spacing: 0.01em;
    text-transform: uppercase;
">

    {{-- Type icon --}}
    @if($type === 'reel')
    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="5 3 19 12 5 21 5 3"/>
    </svg>

    @elseif($type === 'post')
    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="18" height="18" rx="2"/>
        <circle cx="8.5" cy="8.5" r="1.5"/>
        <polyline points="21 15 16 10 5 21"/>
    </svg>

    @elseif($type === 'carousel')
    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <rect x="2" y="6" width="14" height="12" rx="1"/>
        <path d="M22 8v8"/><path d="M18 10v4"/>
    </svg>

    @elseif($type === 'story')
    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <rect x="7" y="2" width="10" height="20" rx="2"/>
    </svg>

    @elseif($type === 'blog')
    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/>
        <polyline points="14 2 14 8 20 8"/>
        <line x1="16" y1="13" x2="8" y2="13"/>
        <line x1="16" y1="17" x2="8" y2="17"/>
    </svg>

    @elseif($type === 'offer')
    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/>
        <line x1="7" y1="7" x2="7.01" y2="7"/>
    </svg>
    @endif

    {{ $t['label'] }}
</span>
