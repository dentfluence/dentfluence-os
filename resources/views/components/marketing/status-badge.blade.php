{{--
|==========================================================================
| Component: status-badge
| Usage: <x-marketing.status-badge status="running" />
|
| Props:
|   status — 'running' | 'scheduled' | 'draft' | 'published'
|           | 'failed'  | 'pending'   | 'paused' | 'completed'
|==========================================================================
--}}
@props(['status' => 'draft'])

@php
$statuses = [
    'running'   => ['label' => 'Running',   'color' => '#0284c7', 'bg' => 'rgba(2,132,199,0.10)',   'dot' => '#0284c7', 'pulse' => true],
    'scheduled' => ['label' => 'Scheduled', 'color' => '#7c3aed', 'bg' => 'rgba(124,58,237,0.10)',  'dot' => '#7c3aed', 'pulse' => false],
    'draft'     => ['label' => 'Draft',     'color' => '#64748b', 'bg' => 'rgba(100,116,139,0.10)', 'dot' => '#94a3b8', 'pulse' => false],
    'published' => ['label' => 'Published', 'color' => '#16a34a', 'bg' => 'rgba(22,163,74,0.10)',   'dot' => '#16a34a', 'pulse' => false],
    'failed'    => ['label' => 'Failed',    'color' => '#dc2626', 'bg' => 'rgba(220,38,38,0.10)',   'dot' => '#dc2626', 'pulse' => false],
    'pending'   => ['label' => 'Pending',   'color' => '#d97706', 'bg' => 'rgba(217,119,6,0.10)',   'dot' => '#d97706', 'pulse' => false],
    'paused'    => ['label' => 'Paused',    'color' => '#9b6aad', 'bg' => 'rgba(155,106,173,0.12)', 'dot' => '#b95cb7', 'pulse' => false],
    'completed' => ['label' => 'Completed', 'color' => '#16a34a', 'bg' => 'rgba(22,163,74,0.10)',   'dot' => '#16a34a', 'pulse' => false],
];

$s = $statuses[$status] ?? $statuses['draft'];
@endphp

<span style="
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 2px 9px 2px 7px;
    border-radius: 20px;
    background: {{ $s['bg'] }};
    font-family: 'Inter', sans-serif;
    font-size: 11.5px;
    font-weight: 600;
    color: {{ $s['color'] }};
    white-space: nowrap;
    letter-spacing: 0.01em;
">
    {{-- Status dot (pulsing for 'running') --}}
    @if($s['pulse'])
    <span style="
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: {{ $s['dot'] }};
        display: inline-block;
        flex-shrink: 0;
        animation: mkt-pulse 1.5s ease-in-out infinite;
    "></span>
    <style>
        @keyframes mkt-pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: 0.5; transform: scale(0.7); }
        }
    </style>
    @else
    <span style="
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: {{ $s['dot'] }};
        display: inline-block;
        flex-shrink: 0;
    "></span>
    @endif

    {{ $s['label'] }}
</span>
