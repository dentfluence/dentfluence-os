{{-- resources/views/communication/timeline/index.blade.php --}}
@extends('layouts.communication')

@section('title', 'Communication Timeline')

@section('communication-content')
@php
    $searchQuery = $searchQuery ?? '';
    $patients    = $patients ?? [];
@endphp
{{-- PREVIEW NOTICE — TimelineController still returns sample data (see its
     getDummy* methods). Remove this banner once wired to live records. 2026-06-26 --}}
<div style="margin:10px 20px 0 28px;padding:10px 14px;border:1px solid var(--comm-warning);background:var(--comm-warning-bg);color:var(--comm-warning-text);border-radius:8px;font-size:13px;font-family:var(--comm-font);">
    <strong>Preview:</strong> This timeline is showing sample data and is not yet connected to live patient/lead records.
</div>
<div style="padding:10px 20px 10px 28px;border-bottom:1px solid rgba(0,0,0,0.06);background:#fff;position:relative;z-index:10;">
    <a href="/communication" style="font-size:12px;color:#5A5A56;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Communication
    </a>
<div class="timeline-index-page">

    {{-- Page Header --}}
    <div class="page-header">
        <div class="page-header-left">
            <div class="page-icon">
                <svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h1 class="page-title">Communication Timeline</h1>
                <p class="page-subtitle">Select a patient or lead to view their full communication history</p>
            </div>
        </div>
    </div>

    {{-- Search Bar --}}
    <div class="timeline-search-wrap">
        <form method="GET" action="{{ route('communication.timeline.index') }}" id="searchForm">
            <div class="search-input-wrap">
                <svg class="search-icon" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
                <input
                    type="text"
                    name="q"
                    id="patientSearch"
                    class="search-input"
                    placeholder="Search by name or phone number..."
                    value="{{ $searchQuery }}"
                    autocomplete="off"
                />
                @if($searchQuery)
                    <a href="{{ route('communication.timeline.index') }}" class="search-clear">
                        <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- Stats Row --}}
    <div class="timeline-stats">
        <div class="tl-stat">
            <span class="tl-stat-num">{{ count($patients) }}</span>
            <span class="tl-stat-label">{{ $searchQuery ? 'Results' : 'Total People' }}</span>
        </div>
        <div class="tl-stat">
            <span class="tl-stat-num tl-stat-lead">{{ count(array_filter($patients, fn($p) => $p['type'] === 'lead')) }}</span>
            <span class="tl-stat-label">Leads</span>
        </div>
        <div class="tl-stat">
            <span class="tl-stat-num tl-stat-patient">{{ count(array_filter($patients, fn($p) => $p['type'] === 'patient')) }}</span>
            <span class="tl-stat-label">Patients</span>
        </div>
    </div>

    {{-- Patient / Lead List --}}
    @if(count($patients) > 0)
        <div class="person-list">
            @foreach($patients as $person)
                <a href="{{ route('communication.timeline.show', $person['id']) }}" class="person-card">
                    <div class="person-avatar {{ $person['type'] === 'patient' ? 'avatar-patient' : 'avatar-lead' }}">
                        {{ $person['avatar'] }}
                    </div>
                    <div class="person-info">
                        <div class="person-name-row">
                            <span class="person-name">{{ $person['name'] }}</span>
                            <span class="person-type-badge {{ $person['type'] === 'patient' ? 'badge-patient' : 'badge-lead' }}">
                                {{ $person['type'] === 'patient' ? 'Patient' : 'Lead' }}
                            </span>
                        </div>
                        <div class="person-phone">
                            <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 8V5z"/>
                            </svg>
                            {{ $person['phone'] }}
                        </div>
                        <div class="person-meta">
                            <span class="meta-tag meta-treatment">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                                {{ $person['treatment'] }}
                            </span>
                            <span class="meta-tag meta-assigned">
                                <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                {{ $person['assigned_to'] }}
                            </span>
                        </div>
                    </div>
                    <div class="person-right">
                        <span class="person-status-chip status-{{ Str::slug($person['status']) }}">
                            {{ $person['status'] }}
                        </span>
                        <span class="person-last-activity">{{ $person['last_activity'] }}</span>
                        <svg class="person-arrow" width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>
                </a>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <div class="empty-icon">
                <svg width="48" height="48" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <h3>No results found</h3>
            <p>No patients or leads match "{{ $searchQuery }}"</p>
            <a href="{{ route('communication.timeline.index') }}" class="btn-secondary">Clear Search</a>
        </div>
    @endif

</div>
@endsection