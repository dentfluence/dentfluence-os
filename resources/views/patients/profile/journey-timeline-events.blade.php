{{-- Journey Timeline — rendered event rows (Phase 4, Slice 2).
     Server-rendered by PatientController@timeline; injected into the
     journey-timeline card. $events = Collection of normalized entries from
     PatientJourneyService (already permission-filtered for the viewer). --}}
@php
    $accent = [
        'slate'  => ['border' => '#94a3b8', 'bg' => 'bg-slate-100',  'text' => '#475569'],
        'blue'   => ['border' => '#3b82f6', 'bg' => 'bg-blue-100',   'text' => '#2563eb'],
        'teal'   => ['border' => '#0d9488', 'bg' => 'bg-teal-100',   'text' => '#0d9488'],
        'violet' => ['border' => '#7c3aed', 'bg' => 'bg-purple-100', 'text' => '#7c3aed'],
        'green'  => ['border' => '#16a34a', 'bg' => 'bg-green-100',  'text' => '#16a34a'],
        'red'    => ['border' => '#dc2626', 'bg' => 'bg-red-100',    'text' => '#dc2626'],
        'amber'  => ['border' => '#d97706', 'bg' => 'bg-amber-100',  'text' => '#d97706'],
        'indigo' => ['border' => '#4f46e5', 'bg' => 'bg-indigo-100', 'text' => '#4f46e5'],
        'cyan'   => ['border' => '#0891b2', 'bg' => 'bg-cyan-100',   'text' => '#0891b2'],
        'orange' => ['border' => '#ea580c', 'bg' => 'bg-orange-100', 'text' => '#ea580c'],
        'yellow' => ['border' => '#ca8a04', 'bg' => 'bg-yellow-100', 'text' => '#ca8a04'],
    ];
@endphp

@forelse($events as $e)
@php
    $a = $accent[$e['color'] ?? 'slate'] ?? $accent['slate'];
    $d = $e['date'];
@endphp
<div class="flex group" style="border-left:3px solid {{ $a['border'] }}">
    {{-- Date column --}}
    <div class="w-14 flex-shrink-0 flex flex-col items-center justify-center py-3 px-2 bg-gray-50/70 border-r border-gray-100">
        <div class="text-base font-bold text-gray-800 leading-none">{{ $d->format('d') }}</div>
        <div class="text-[10px] text-gray-400 uppercase tracking-wide">{{ $d->format('M') }}</div>
        <div class="text-[10px] text-gray-400">{{ $d->format('Y') }}</div>
    </div>
    {{-- Icon dot --}}
    <div class="flex-shrink-0 flex items-center px-3">
        <div class="w-8 h-8 rounded-full flex items-center justify-center {{ $a['bg'] }}" style="color:{{ $a['text'] }}">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                @switch($e['icon_type'] ?? 'activity')
                    @case('patient')      <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/> @break
                    @case('appointment')  <path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/> @break
                    @case('consultation') <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/> @break
                    @case('plan')         <path d="M9 11H3v10h6z"/><path d="M15 3H9v18h6z"/><path d="M21 7h-6v14h6z"/> @break
                    @case('accepted')     <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/> @break
                    @case('rejected')     <circle cx="12" cy="12" r="10"/><path d="m15 9-6 6"/><path d="m9 9 6 6"/> @break
                    @case('deferred')     <circle cx="12" cy="12" r="10"/><line x1="10" x2="10" y1="15" y2="9"/><line x1="14" x2="14" y1="15" y2="9"/> @break
                    @case('treatment')    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/> @break
                    @case('invoice')      <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M16 13H8"/><path d="M16 17H8"/> @break
                    @case('payment')      <line x1="12" x2="12" y1="2" y2="22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/> @break
                    @case('media')        <rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/> @break
                    @case('lab')          <path d="M10 2v7.527a2 2 0 0 1-.211.896L4.72 20.55a1 1 0 0 0 .9 1.45h12.76a1 1 0 0 0 .9-1.45l-5.069-10.127A2 2 0 0 1 14 9.527V2"/><path d="M8.5 2h7"/> @break
                    @case('membership')   <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/> @break
                    @case('review')       <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/> @break
                    @case('consent')      <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/> @break
                    @case('recall')       <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/> @break
                    @case('task')         <path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/> @break
                    @case('note')         <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/> @break
                    @case('whatsapp')     <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8z"/> @break
                    @case('call')         <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.1 12 19.79 19.79 0 0 1 1.03 3.33 2 2 0 0 1 3 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 21 16.92z"/> @break
                    @default              <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                @endswitch
            </svg>
        </div>
    </div>
    {{-- Content --}}
    <div class="flex-1 min-w-0 py-2.5 pr-3">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <div class="font-semibold text-gray-800 text-sm leading-tight">
                    @if(!empty($e['link']))
                        <a href="{{ $e['link'] }}" class="hover:text-[#6a0f70] hover:underline">{{ $e['title'] }}</a>
                    @else
                        {{ $e['title'] }}
                    @endif
                </div>
                @if(!empty($e['description']))
                    <div class="text-xs text-gray-500 mt-0.5 truncate">{{ $e['description'] }}</div>
                @endif
                <div class="text-[11px] text-gray-400 mt-0.5">
                    {{ $d->format('h:i A') }}@if(!empty($e['actor'])) · {{ $e['actor'] }}@endif
                </div>
            </div>
            @if(!empty($e['meta']))
                <span class="flex-shrink-0 inline-block px-2 py-0.5 text-[10px] rounded-full font-medium bg-gray-100 text-gray-500 mt-0.5">
                    {{ \Illuminate\Support\Str::limit($e['meta'], 28) }}
                </span>
            @endif
        </div>
    </div>
</div>
@empty
<div class="py-10 text-center text-sm text-gray-400">No events for this filter yet.</div>
@endforelse
