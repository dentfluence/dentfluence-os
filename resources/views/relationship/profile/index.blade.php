{{--
|==========================================================================
| Relationship Profile — Phase 3
| Route: GET /relationship/{id}   [relationship.profile]
|
| Tabs:
|   timeline     — Unified chronological log (all sources merged)
|   journeys     — RelationshipJourneys + opportunities
|   communication — Calls, WhatsApp, emails
|   clinical     — Link to patient show page + summary
|   tasks        — Open tasks + quick-add
|
| Variables: see ProfileController@show
|==========================================================================
--}}
@extends('layouts.app')

@section('page-title', $relationship->name . ' — Relationship Profile')

@push('styles')
<style>
    /* ── Profile page: edge-to-edge, no inner padding ── */
    #df-content-inner { padding: 0 !important; max-width: 100% !important; }
    #df-content-area  { background: #f3f4f8 !important; }

    /* ── Sticky profile header ── */
    #rp-sticky-header {
        position: sticky;
        top: 0;
        z-index: 40;
        background: #fff;
        border-bottom: 1px solid #e5e7eb;
        box-shadow: 0 2px 8px rgba(106,15,112,0.06);
    }

    /* ── Capsule tab nav (mirrors patient show) ── */
    .rp-tab-nav {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 5px 6px;
        background: #f0e6f2;
        border-radius: 12px;
        overflow-x: auto;
        scrollbar-width: none;
        flex-wrap: nowrap;
    }
    .rp-tab-nav::-webkit-scrollbar { display: none; }
    .rp-tab-btn {
        flex-shrink: 0;
        padding: 6px 14px;
        border-radius: 8px;
        border: none;
        background: transparent;
        font-size: 13px;
        font-weight: 500;
        color: #6b7280;
        cursor: pointer;
        white-space: nowrap;
        transition: background 0.15s, color 0.15s, box-shadow 0.15s;
    }
    .rp-tab-btn:hover { background: rgba(106,15,112,0.08); color: #6a0f70; }
    .rp-tab-btn.active {
        background: #ffffff;
        color: #6a0f70;
        font-weight: 600;
        box-shadow: 0 1px 4px rgba(106,15,112,0.15), 0 0 0 1px rgba(106,15,112,0.10);
    }

    /* ── Timeline ── */
    .tl-entry {
        display: flex;
        gap: 14px;
        padding: 12px 0;
        border-bottom: 1px solid #f3f4f6;
        position: relative;
    }
    .tl-entry:last-child { border-bottom: none; }
    .tl-icon {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        margin-top: 2px;
    }
    .tl-icon.type-call        { background: #e8f7ef; color: #1a7a45; }
    .tl-icon.type-whatsapp    { background: #e8f7ef; color: #128c3d; }
    .tl-icon.type-appointment { background: #e6f0fb; color: #1a5ea8; }
    .tl-icon.type-payment     { background: #e8f7ef; color: #1a7a45; }
    .tl-icon.type-lead        { background: #f0e6f2; color: #6a0f70; }
    .tl-icon.type-recall      { background: #fff4e0; color: #a05c00; }
    .tl-icon.type-task        { background: #fdeaea; color: #b52020; }
    .tl-icon.type-note        { background: #f5eef9; color: #6a0f70; }
    .tl-icon.type-activity    { background: #f5f5f5; color: #6b7280; }
    .tl-icon.type-communication { background: #e6f0fb; color: #1a5ea8; }

    /* ── Journey cards ── */
    .journey-card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 16px;
        margin-bottom: 12px;
    }
    .journey-state-badge {
        font-size: 11px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 99px;
    }

    /* ── Section title style ── */
    .section-title {
        font-size: 11px;
        font-weight: 700;
        color: #6a0f70;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-bottom: 12px;
    }

    /* ── Task item ── */
    .task-item {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 1px solid #f3f4f6;
    }
    .task-item:last-child { border-bottom: none; }
</style>
@endpush

@section('content')
<div x-data="{ activeTab: 'timeline' }">

{{-- ══════════════════════════════════════════════════════════
     STICKY HEADER
══════════════════════════════════════════════════════════ --}}
<div id="rp-sticky-header" class="bg-white px-6 pt-4 pb-3">

    {{-- Breadcrumb + actions --}}
    <div class="flex items-center justify-between mb-4 flex-wrap gap-2">
        <div class="flex items-center gap-2 text-sm">
            <a href="{{ route('relationship.today') }}"
               class="flex items-center gap-1 text-gray-500 hover:text-[#6a0f70] transition-colors">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="m15 18-6-6 6-6"/>
                </svg>
                Today's Actions
            </a>
            <span class="text-gray-300">/</span>
            <span class="text-gray-700 font-medium">Relationship Profile</span>
        </div>

        <div class="flex gap-2 flex-wrap">
            @if($patient)
                <a href="{{ route('patients.show', $patient) }}"
                   class="px-3 py-1.5 text-xs border border-gray-300 text-gray-600 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors bg-white font-medium">
                    Full Clinical Record →
                </a>
            @endif
            {{-- Phase 8 PRM Retirement (Slice 5) — "PRM Lead View" removed (it only ever
                 redirected back here). Replaced with a working edit action on PRE. --}}
            @if($lead)
                <a href="{{ route('relationship.pipeline.edit-lead', $lead->id) }}"
                   class="px-3 py-1.5 text-xs border border-gray-300 text-gray-600 hover:border-[#6a0f70] hover:text-[#6a0f70] transition-colors bg-white font-medium">
                    Edit Lead
                </a>
            @endif
        </div>
    </div>

    {{-- Summary card --}}
    @include('relationship.profile._summary')

    {{-- Tab nav --}}
    <div class="mt-4">
        <div class="rp-tab-nav">
            <button @click="activeTab='timeline'"
                    :class="activeTab==='timeline' ? 'active' : ''"
                    class="rp-tab-btn">
                Timeline
            </button>
            <button @click="activeTab='journeys'"
                    :class="activeTab==='journeys' ? 'active' : ''"
                    class="rp-tab-btn">
                Journeys
                @if($opportunities->count() > 0)
                    <span style="background:#6a0f70;color:#fff;font-size:10px;padding:1px 6px;border-radius:99px;margin-left:4px;">
                        {{ $opportunities->count() }}
                    </span>
                @endif
            </button>
            <button @click="activeTab='communication'"
                    :class="activeTab==='communication' ? 'active' : ''"
                    class="rp-tab-btn">
                Communication
            </button>
            @if($patient)
            <button @click="activeTab='clinical'"
                    :class="activeTab==='clinical' ? 'active' : ''"
                    class="rp-tab-btn">
                Clinical
            </button>
            @endif
            <button @click="activeTab='tasks'"
                    :class="activeTab==='tasks' ? 'active' : ''"
                    class="rp-tab-btn">
                Tasks
                @if($openTasks->count() > 0)
                    <span style="background:#b52020;color:#fff;font-size:10px;padding:1px 6px;border-radius:99px;margin-left:4px;">
                        {{ $openTasks->count() }}
                    </span>
                @endif
            </button>
        </div>
    </div>

</div>

{{-- ══════════════════════════════════════════════════════════
     TAB CONTENT
══════════════════════════════════════════════════════════ --}}
<div class="px-6 py-5">

{{-- ── TAB: UNIFIED TIMELINE ──────────────────────────────────────────── --}}
<div x-show="activeTab==='timeline'" x-cloak>
    <div style="max-width:860px;">

        @if($timeline->isEmpty())
            <div style="text-align:center;padding:60px 20px;color:#9a7aaa;">
                <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;display:block;opacity:0.4;">
                    <circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>
                </svg>
                <p style="font-size:14px;margin:0;">No timeline entries yet.</p>
                <p style="font-size:12px;margin-top:4px;opacity:0.7;">Activity will appear here as interactions are logged.</p>
            </div>
        @else
            <div class="section-title">{{ $timeline->count() }} events · newest first</div>

            @foreach($timeline as $entry)
            @php
                $iconType = $entry['icon_type'] ?? 'activity';
                $dateObj  = $entry['date'] instanceof \Carbon\Carbon ? $entry['date'] : \Carbon\Carbon::parse($entry['date']);
            @endphp
            <div class="tl-entry">
                {{-- Icon --}}
                <div class="tl-icon type-{{ $iconType }}">
                    @switch($iconType)
                        @case('call')
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.8 3.4 2 2 0 0 1 3.78 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                            @break
                        @case('whatsapp')
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                            @break
                        @case('appointment')
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                            @break
                        @case('payment')
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            @break
                        @case('task')
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                            @break
                        @case('note')
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            @break
                        @case('lead')
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            @break
                        @default
                            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="4"/></svg>
                    @endswitch
                </div>

                {{-- Content --}}
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;">
                        <div>
                            <p style="margin:0 0 2px;font-size:13px;font-weight:500;color:#1a0a24;">
                                {{ $entry['title'] }}
                            </p>
                            @if($entry['description'])
                                <p style="margin:0 0 4px;font-size:12px;color:#7a6884;line-height:1.5;">
                                    {{ Str::limit($entry['description'], 120) }}
                                </p>
                            @endif
                            <div style="display:flex;align-items:center;gap:8px;margin-top:4px;flex-wrap:wrap;">
                                @if($entry['actor'])
                                    <span style="font-size:11px;color:#9a7aaa;">by {{ $entry['actor'] }}</span>
                                @endif
                                @if($entry['meta'])
                                    <span style="font-size:11px;background:#f0e6f2;color:#6a0f70;padding:1px 7px;border-radius:99px;">
                                        {{ $entry['meta'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div style="flex-shrink:0;text-align:right;">
                            <span style="font-size:11px;color:#9a7aaa;white-space:nowrap;">
                                {{ $dateObj->format('d M Y') }}
                            </span>
                            <br>
                            <span style="font-size:10px;color:#b0a4bc;">
                                {{ $dateObj->format('H:i') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            @endforeach

            @if($timeline->count() >= 100)
                <p style="text-align:center;font-size:12px;color:#9a7aaa;padding:16px 0;">
                    Showing 100 most recent events.
                </p>
            @endif
        @endif
    </div>
</div>

{{-- ── TAB: JOURNEYS ──────────────────────────────────────────────────── --}}
<div x-show="activeTab==='journeys'" x-cloak>
    <div style="max-width:860px;">

        {{-- Active Journeys --}}
        @forelse($relationship->journeys->whereNull('closed_at') as $journey)
        <div class="journey-card">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                <span style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#6a0f70;">
                    {{ ucfirst($journey->type) }} Journey
                </span>
                <span class="journey-state-badge" style="
                    background:{{ in_array($journey->state, ['lost','declined','closed']) ? '#fdeaea' : '#e8f7ef' }};
                    color:{{ in_array($journey->state, ['lost','declined','closed']) ? '#b52020' : '#1a7a45' }};">
                    {{ ucfirst(str_replace('_',' ', $journey->state)) }}
                </span>
            </div>
            <div style="font-size:12px;color:#9a7aaa;">
                Started: {{ $journey->started_at ? $journey->started_at->format('d M Y') : '—' }}
            </div>
            @if($journey->metadata)
                <div style="margin-top:8px;font-size:12px;color:#7a6884;">
                    @foreach($journey->metadata as $k => $v)
                        @if(is_scalar($v))
                            <span style="display:inline-block;margin-right:8px;margin-bottom:4px;background:#f5eef9;padding:2px 8px;border-radius:4px;">
                                {{ ucfirst(str_replace('_',' ',$k)) }}: {{ $v }}
                            </span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
        @empty
            <p style="color:#9a7aaa;font-size:13px;">No active journeys.</p>
        @endforelse

        {{-- Open Opportunities --}}
        @if($opportunities->count() > 0)
        <div class="section-title" style="margin-top:24px;">Open Opportunities ({{ $opportunities->count() }})</div>
        @foreach($opportunities as $opp)
        <div class="journey-card">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                <div>
                    <p style="margin:0 0 4px;font-size:14px;font-weight:600;color:#1a0a24;">
                        {{ $opp->treatment_name ?? $opp->title ?? 'Opportunity' }}
                    </p>
                    @if($opp->estimated_value)
                        <p style="margin:0 0 4px;font-size:13px;color:#1a7a45;font-weight:500;">
                            ₹ {{ number_format($opp->estimated_value, 0) }}
                        </p>
                    @endif
                    @if($opp->follow_up_date)
                        <p style="margin:0;font-size:12px;color:{{ \Carbon\Carbon::parse($opp->follow_up_date)->isPast() ? '#b52020' : '#9a7aaa' }};">
                            Follow up: {{ \Carbon\Carbon::parse($opp->follow_up_date)->format('d M Y') }}
                            @if(\Carbon\Carbon::parse($opp->follow_up_date)->isPast())
                                <span style="color:#b52020;font-weight:600;"> · Overdue</span>
                            @endif
                        </p>
                    @endif
                </div>
                <span class="journey-state-badge" style="
                    background:{{ in_array($opp->status ?? '', ['completed','declined']) ? '#fdeaea' : '#fff4e0' }};
                    color:{{ in_array($opp->status ?? '', ['completed','declined']) ? '#b52020' : '#a05c00' }};
                    flex-shrink:0;">
                    {{ ucfirst($opp->status ?? 'Open') }}
                </span>
            </div>
        </div>
        @endforeach
        @endif

        {{-- Closed Journeys --}}
        @if($relationship->journeys->whereNotNull('closed_at')->count() > 0)
        <div class="section-title" style="margin-top:24px;">Closed Journeys</div>
        @foreach($relationship->journeys->whereNotNull('closed_at') as $journey)
        <div class="journey-card" style="opacity:0.65;">
            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:12px;font-weight:600;color:#7a6884;">
                    {{ ucfirst($journey->type) }} Journey
                </span>
                <span class="journey-state-badge" style="background:#f3f4f6;color:#6b7280;">
                    {{ ucfirst(str_replace('_',' ', $journey->state)) }}
                </span>
            </div>
            <div style="font-size:11px;color:#b0a4bc;margin-top:4px;">
                {{ $journey->started_at?->format('d M Y') }} → {{ $journey->closed_at?->format('d M Y') }}
            </div>
        </div>
        @endforeach
        @endif

    </div>
</div>

{{-- ── TAB: COMMUNICATION ──────────────────────────────────────────────── --}}
<div x-show="activeTab==='communication'" x-cloak>
    <div style="max-width:860px;">

        {{-- Quick log call button --}}
        <div style="margin-bottom:20px;display:flex;gap:10px;align-items:center;">
            @if($patient)
                <a href="{{ route('patients.show', $patient) }}#communication"
                   style="padding:8px 16px;font-size:12px;font-weight:500;background:#6a0f70;color:#fff;border-radius:3px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.8 3.4 2 2 0 0 1 3.78 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    Log Call
                </a>
            @endif
        </div>

        {{-- Recent patient_communications --}}
        @if($recentComms->count() > 0)
            <div class="section-title">Recent Communications</div>
            @foreach($recentComms as $comm)
            <div class="tl-entry">
                <div class="tl-icon type-{{ $comm->type ?? 'call' }}" style="width:30px;height:30px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.8 3.4 2 2 0 0 1 3.78 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                </div>
                <div style="flex:1;">
                    <p style="margin:0 0 2px;font-size:13px;font-weight:500;color:#1a0a24;">
                        {{ ucfirst($comm->type ?? 'Communication') }}
                        <span style="font-size:11px;font-weight:400;color:#9a7aaa;">· {{ $comm->direction ?? '' }}</span>
                    </p>
                    @if(!empty($comm->message))
                        <p style="margin:0 0 4px;font-size:12px;color:#7a6884;">{{ Str::limit($comm->message, 100) }}</p>
                    @endif
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        @if(!empty($comm->staff_name))
                            <span style="font-size:11px;color:#9a7aaa;">{{ $comm->staff_name }}</span>
                        @endif
                        <span style="font-size:11px;background:#f0e6f2;color:#6a0f70;padding:1px 6px;border-radius:99px;">
                            {{ ucfirst($comm->status ?? '') }}
                        </span>
                        <span style="font-size:11px;color:#b0a4bc;">
                            {{ \Carbon\Carbon::parse($comm->sent_at ?? $comm->created_at)->format('d M Y H:i') }}
                        </span>
                    </div>
                </div>
            </div>
            @endforeach
        @endif

        {{-- WhatsApp messages --}}
        @if($waMessages->count() > 0)
            <div class="section-title" style="margin-top:24px;">WhatsApp Messages</div>
            @foreach($waMessages as $wa)
            <div class="tl-entry">
                <div class="tl-icon type-whatsapp" style="width:30px;height:30px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                </div>
                <div style="flex:1;">
                    <p style="margin:0 0 2px;font-size:12px;font-weight:500;color:#1a0a24;">
                        {{ Str::limit($wa->body ?? $wa->content ?? '', 100) }}
                    </p>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <span style="font-size:11px;background:#e8f7ef;color:#128c3d;padding:1px 6px;border-radius:99px;">
                            {{ ucfirst($wa->direction ?? '') }}
                        </span>
                        <span style="font-size:11px;color:#b0a4bc;">
                            {{ \Carbon\Carbon::parse($wa->created_at)->format('d M Y H:i') }}
                        </span>
                    </div>
                </div>
            </div>
            @endforeach
        @endif

        @if($recentComms->isEmpty() && $waMessages->isEmpty())
            @if($lead && $lead->activities && $lead->activities->count() > 0)
                <div class="section-title">Lead Activities (pre-conversion)</div>
                @foreach($lead->activities as $la)
                <div class="tl-entry">
                    <div class="tl-icon type-communication" style="width:30px;height:30px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.8 3.4 2 2 0 0 1 3.78 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 8.91a16 16 0 0 0 6 6l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    </div>
                    <div style="flex:1;">
                        <p style="margin:0 0 2px;font-size:13px;font-weight:500;color:#1a0a24;">{{ $la->label ?? ucfirst($la->type) }}</p>
                        @if($la->note)
                            <p style="margin:0 0 4px;font-size:12px;color:#7a6884;">{{ Str::limit($la->note, 100) }}</p>
                        @endif
                        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                            @if($la->by) <span style="font-size:11px;color:#9a7aaa;">{{ $la->by }}</span> @endif
                            @if($la->outcome) <span style="font-size:11px;background:#f0e6f2;color:#6a0f70;padding:1px 6px;border-radius:99px;">{{ $la->outcome }}</span> @endif
                            @if($la->activity_date) <span style="font-size:11px;color:#b0a4bc;">{{ \Carbon\Carbon::parse($la->activity_date)->format('d M Y') }}</span> @endif
                        </div>
                    </div>
                </div>
                @endforeach
            @else
                <div style="text-align:center;padding:60px 20px;color:#9a7aaa;">
                    <p style="font-size:14px;margin:0;">No communication history.</p>
                </div>
            @endif
        @endif

    </div>
</div>

{{-- ── TAB: CLINICAL ───────────────────────────────────────────────────── --}}
@if($patient)
<div x-show="activeTab==='clinical'" x-cloak>
    <div style="max-width:860px;">

        {{-- Household panel (slice 4) — only when several patients share this relationship --}}
        @if ($isHousehold)
            <div style="background:#fff;border:1px solid rgba(185,92,183,0.14);border-radius:4px;padding:20px;margin-bottom:16px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <span style="font-size:11px;font-weight:700;color:#6a0f70;letter-spacing:0.06em;text-transform:uppercase;">Household</span>
                    <span style="background:#f0e6f2;color:#6a0f70;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;">{{ $householdPatients->count() }} patients</span>
                </div>
                <p style="font-size:12.5px;color:#7a6884;margin:0 0 14px;">
                    Several people share this phone, so they're linked to one relationship. All linked patients:
                </p>

                <div style="display:flex;flex-direction:column;gap:8px;">
                    @foreach ($householdPatients as $hp)
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid rgba(185,92,183,0.12);border-radius:4px;padding:10px 14px;">
                            <div style="min-width:0;">
                                <div style="font-size:14px;font-weight:600;color:#1a0a24;">
                                    {{ $hp->name }}
                                    @if ($patient && $hp->id === $patient->id)
                                        <span style="font-size:10.5px;font-weight:600;color:#6a0f70;background:#f5eef9;padding:1px 7px;border-radius:999px;margin-left:6px;">Primary</span>
                                    @endif
                                </div>
                                <div style="font-size:12px;color:#7a6884;">
                                    #{{ $hp->id }}@if ($hp->phone) · {{ $hp->phone }}@endif
                                </div>
                            </div>
                            <a href="{{ route('patients.show', $hp->id) }}"
                               style="flex-shrink:0;display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#f0e6f2;color:#6a0f70;font-size:12.5px;font-weight:600;border-radius:3px;text-decoration:none;">
                                Open record
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Referral panel — who referred this patient + who they've referred --}}
        @if ($referredByPatient || $patient->referrer_name || $referralsMade->isNotEmpty())
            <div style="background:#fff;border:1px solid rgba(185,92,183,0.14);border-radius:4px;padding:20px;margin-bottom:16px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;flex-wrap:wrap;">
                    <span style="font-size:11px;font-weight:700;color:#6a0f70;letter-spacing:0.06em;text-transform:uppercase;">Referral</span>
                    @if ($referralsMade->isNotEmpty())
                        <span style="background:#f0e6f2;color:#6a0f70;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;">
                            {{ $referralsMade->count() }} referred · ₹{{ number_format($referralValue, 0) }} lifetime
                        </span>
                    @endif
                </div>

                @if ($referredByPatient)
                    <p style="font-size:12.5px;color:#7a6884;margin:0 0 14px;">
                        Referred by
                        <a href="{{ route('patients.show', $referredByPatient->id) }}" style="color:#6a0f70;font-weight:600;text-decoration:none;">{{ $referredByPatient->name }}</a>
                        (#{{ $referredByPatient->id }})
                    </p>
                @elseif ($patient->referrer_name)
                    <p style="font-size:12.5px;color:#7a6884;margin:0 0 14px;">
                        Referred by {{ $patient->referrer_name }}@if($patient->referrer_mobile) · {{ $patient->referrer_mobile }}@endif
                        @if($patient->referrer_type)
                            <span style="font-size:11px;background:#f0e6f2;color:#6a0f70;padding:1px 6px;border-radius:99px;margin-left:4px;">{{ ucfirst($patient->referrer_type) }}</span>
                        @endif
                    </p>
                @endif

                @if ($referralsMade->isNotEmpty())
                    <p style="font-size:12.5px;color:#7a6884;margin:0 0 10px;">Patients referred by {{ $patient->name }}:</p>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        @foreach ($referralsMade as $rp)
                            @php $reward = $referralRewards->get($rp->id); @endphp
                            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid rgba(185,92,183,0.12);border-radius:4px;padding:10px 14px;flex-wrap:wrap;">
                                <div style="min-width:0;">
                                    <div style="font-size:14px;font-weight:600;color:#1a0a24;">{{ $rp->name }}</div>
                                    <div style="font-size:12px;color:#7a6884;">
                                        #{{ $rp->id }}@if ($rp->phone) · {{ $rp->phone }}@endif
                                    </div>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
                                    @if ($reward)
                                        <span style="font-size:11.5px;font-weight:600;color:#1a7a45;background:#e8f7ef;padding:4px 10px;border-radius:99px;">
                                            Rewarded ₹{{ number_format($reward->amount, 0) }} · {{ $reward->created_at->format('d M Y') }}
                                        </span>
                                    @elseif ($referralRewardEnabled && $referralPaidPatientIds->contains($rp->id))
                                        <form action="{{ route('relationship.referral-reward.store', $relationship->id) }}" method="POST" style="margin:0;">
                                            @csrf
                                            <input type="hidden" name="referrer_patient_id" value="{{ $patient->id }}">
                                            <input type="hidden" name="referred_patient_id" value="{{ $rp->id }}">
                                            <button type="submit"
                                                    style="border:none;background:#6a0f70;color:#fff;font-size:12px;font-weight:600;padding:7px 12px;border-radius:3px;cursor:pointer;"
                                                    onclick="return confirm('Credit ₹{{ number_format($referralRewardAmount, 0) }} to {{ $patient->name }}\'s wallet for referring {{ $rp->name }}?');">
                                                Reward ₹{{ number_format($referralRewardAmount, 0) }}
                                            </button>
                                        </form>
                                    @elseif ($referralRewardEnabled)
                                        <span style="font-size:11.5px;color:#9a7aaa;">Not yet eligible — no paid invoice</span>
                                    @endif
                                    <a href="{{ route('patients.show', $rp->id) }}"
                                       style="flex-shrink:0;display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#f0e6f2;color:#6a0f70;font-size:12.5px;font-weight:600;border-radius:3px;text-decoration:none;">
                                        Open record
                                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Extended family panel — patient_links, separate from the household (shared-phone) panel above --}}
        @if ($extendedFamily->isNotEmpty())
            <div style="background:#fff;border:1px solid rgba(185,92,183,0.14);border-radius:4px;padding:20px;margin-bottom:16px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <span style="font-size:11px;font-weight:700;color:#6a0f70;letter-spacing:0.06em;text-transform:uppercase;">Family</span>
                    <span style="background:#f0e6f2;color:#6a0f70;font-size:11px;font-weight:700;padding:2px 9px;border-radius:999px;">{{ $extendedFamily->count() }} linked</span>
                </div>
                <p style="font-size:12.5px;color:#7a6884;margin:0 0 14px;">
                    Family members linked to {{ $patient->name }} (separate patient records, own phone):
                </p>

                <div style="display:flex;flex-direction:column;gap:8px;">
                    @foreach ($extendedFamily as $fp)
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;border:1px solid rgba(185,92,183,0.12);border-radius:4px;padding:10px 14px;">
                            <div style="min-width:0;">
                                <div style="font-size:14px;font-weight:600;color:#1a0a24;">
                                    {{ $fp->name }}
                                    @if (!empty($fp->pivot->relationship))
                                        <span style="font-size:10.5px;font-weight:600;color:#6a0f70;background:#f5eef9;padding:1px 7px;border-radius:999px;margin-left:6px;">{{ $fp->pivot->relationship }}</span>
                                    @endif
                                </div>
                                <div style="font-size:12px;color:#7a6884;">
                                    #{{ $fp->id }}@if ($fp->phone) · {{ $fp->phone }}@endif
                                </div>
                            </div>
                            <a href="{{ route('patients.show', $fp->id) }}"
                               style="flex-shrink:0;display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#f0e6f2;color:#6a0f70;font-size:12.5px;font-weight:600;border-radius:3px;text-decoration:none;">
                                Open record
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Summary card —  clinical detail stays in patient show, just a bridge here --}}
        <div style="background:#fff;border:1px solid rgba(185,92,183,0.14);border-radius:4px;padding:28px;text-align:center;">
            <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5" style="margin:0 auto 14px;display:block;opacity:0.6;">
                <rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h6M9 12h6M9 15h4"/>
            </svg>
            <p style="font-size:15px;font-weight:600;color:#1a0a24;margin:0 0 6px;">Full Clinical Record</p>
            <p style="font-size:13px;color:#7a6884;margin:0 0 20px;max-width:400px;margin-left:auto;margin-right:auto;">
                Consultations, prescriptions, treatment plans, lab cases, imaging, vitals, and clinical notes are all in the full Patient Record.
            </p>
            <a href="{{ route('patients.show', $patient) }}"
               style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:#6a0f70;color:#fff;font-size:13px;font-weight:500;border-radius:3px;text-decoration:none;">
                Open Patient Record — {{ $patient->name }}
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
            </a>
        </div>

        {{-- Quick stats recap --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-top:16px;">
            <div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:4px;padding:16px;">
                <div style="font-size:10px;font-weight:700;color:#9a7aaa;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Patient ID</div>
                <div style="font-size:15px;font-weight:600;color:#6a0f70;">#{{ $patient->id }}</div>
            </div>
            <div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:4px;padding:16px;">
                <div style="font-size:10px;font-weight:700;color:#9a7aaa;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Total Visits</div>
                <div style="font-size:15px;font-weight:600;color:#1a0a24;">{{ $totalVisits }}</div>
            </div>
            <div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:4px;padding:16px;">
                <div style="font-size:10px;font-weight:700;color:#9a7aaa;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Lifetime Revenue</div>
                <div style="font-size:15px;font-weight:600;color:#1a7a45;">₹ {{ number_format($lifetimeRevenue, 0) }}</div>
            </div>
            <div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:4px;padding:16px;">
                <div style="font-size:10px;font-weight:700;color:#9a7aaa;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Recall</div>
                <div style="font-size:15px;font-weight:600;color:{{ $recallStatus === 'overdue' ? '#b52020' : '#1a0a24' }};">
                    {{ ucfirst($recallStatus ?? 'None') }}
                </div>
            </div>
            <div style="background:#fff;border:1px solid rgba(185,92,183,0.12);border-radius:4px;padding:16px;">
                <div style="font-size:10px;font-weight:700;color:#9a7aaa;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:6px;">Review</div>
                @if ($latestReview && $latestReview->rating !== null)
                    <div style="font-size:15px;font-weight:600;color:{{ $latestReview->isPositive() ? '#1a7a45' : '#b52020' }};">
                        {{ $latestReview->rating }}★ left
                    </div>
                @elseif ($latestReview)
                    <div style="font-size:15px;font-weight:600;color:#1a0a24;">Requested, no reply</div>
                @else
                    <form action="{{ route('communication.reviews.send') }}" method="POST" style="margin:0;">
                        @csrf
                        <input type="hidden" name="patient_id" value="{{ $patient->id }}">
                        <button type="submit" style="border:none;background:none;padding:0;font-size:13.5px;font-weight:600;color:#6a0f70;cursor:pointer;text-decoration:underline;">
                            Never asked — Send request
                        </button>
                    </form>
                @endif
            </div>
        </div>

    </div>
</div>
@endif

{{-- ── TAB: TASKS ──────────────────────────────────────────────────────── --}}
<div x-show="activeTab==='tasks'" x-cloak>
    <div style="max-width:860px;">

        {{-- Add task link --}}
        @if($patient)
        <div style="margin-bottom:16px;">
            <a href="{{ route('patients.show', $patient) }}#tasks"
               style="padding:8px 14px;font-size:12px;font-weight:500;background:#6a0f70;color:#fff;border-radius:3px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Task
            </a>
        </div>
        @endif

        @if($openTasks->count() > 0)
            <div class="section-title">Open Tasks ({{ $openTasks->count() }})</div>
            @foreach($openTasks as $task)
            @php
                $taskTitle  = $task->title ?? $task->task_title ?? 'Task';
                $taskStatus = $task->status ?? 'pending';
                $taskDue    = $task->due_date ? \Carbon\Carbon::parse($task->due_date) : null;
                $isOverdue  = $taskDue && $taskDue->isPast();
            @endphp
            <div class="task-item">
                {{-- Status dot --}}
                <div style="width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:4px;
                    background:{{ $taskStatus === 'in_progress' ? '#6a0f70' : ($isOverdue ? '#b52020' : '#d4c8dc') }};"></div>

                <div style="flex:1;">
                    <p style="margin:0 0 2px;font-size:13px;font-weight:500;color:#1a0a24;">{{ $taskTitle }}</p>
                    @if(!empty($task->description))
                        <p style="margin:0 0 4px;font-size:12px;color:#7a6884;">{{ Str::limit($task->description, 80) }}</p>
                    @endif
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <span style="font-size:11px;background:#f0e6f2;color:#6a0f70;padding:1px 7px;border-radius:99px;">
                            {{ ucfirst($taskStatus) }}
                        </span>
                        @if($taskDue)
                            <span style="font-size:11px;color:{{ $isOverdue ? '#b52020' : '#9a7aaa' }};font-weight:{{ $isOverdue ? '600' : '400' }};">
                                Due: {{ $taskDue->format('d M Y') }}{{ $isOverdue ? ' · Overdue' : '' }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        @else
            <div style="text-align:center;padding:60px 20px;color:#9a7aaa;">
                <svg xmlns="http://www.w3.org/2000/svg" width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="margin:0 auto 12px;display:block;opacity:0.4;">
                    <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                </svg>
                <p style="font-size:14px;margin:0;">No open tasks.</p>
            </div>
        @endif

    </div>
</div>

</div>{{-- end .px-6 content --}}
</div>{{-- end x-data --}}
@endsection
