@extends('layouts.communication')

@section('content')

<div class="prm-comm">

    {{-- Page heading --}}
    <div class="prm-comm__heading">
        <div>
            <h1 class="prm-comm__title">PRM – Communication List</h1>
            <p class="prm-comm__subtitle">View Today</p>
        </div>
    </div>

    {{-- Tabs + actions row --}}
    <div class="prm-comm__tabs-row">
        <div class="prm-comm__tabs">
            <a href="{{ route('communication.manager.index') }}"
               class="prm-comm__tab {{ request()->routeIs('communication.manager.index') ? 'is-active' : '' }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Today
                <span class="prm-comm__tab-count prm-comm__tab-count--amber">{{ $stats['callbacks_today'] ?? 34 }}</span>
            </a>
            <a href="{{ route('communication.manager.overdue') }}"
               class="prm-comm__tab {{ request()->routeIs('communication.manager.overdue') ? 'is-active' : '' }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                Overdue
                <span class="prm-comm__tab-count prm-comm__tab-count--red">{{ $stats['overdue'] ?? 18 }}</span>
            </a>
            <a href="{{ route('communication.manager.queue') }}"
               class="prm-comm__tab {{ request()->routeIs('communication.manager.queue') ? 'is-active' : '' }}">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Long Term (6M+)
                <span class="prm-comm__tab-count prm-comm__tab-count--amber">23</span>
            </a>
            <a href="#" class="prm-comm__tab">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Ongoing Treatment
                <span class="prm-comm__tab-count prm-comm__tab-count--purple">{{ $stats['total_pending'] ?? 16 }}</span>
            </a>
            <a href="#" class="prm-comm__tab">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Yesterday
                <span class="prm-comm__tab-count prm-comm__tab-count--amber">12</span>
            </a>
            <a href="#" class="prm-comm__tab">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
                Special Days
                <span class="prm-comm__tab-count prm-comm__tab-count--red">7</span>
            </a>
        </div>
        <div class="prm-comm__tab-actions">
            <button class="prm-comm__filter-btn" onclick="toggleFilters()">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Filters
            </button>
            <a href="{{ route('communication.manager.log.form') }}" class="prm-comm__add-btn">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Lead
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>
            </a>
        </div>
    </div>

    {{-- Summary bar --}}
    <div class="prm-comm__summary">
        <div class="prm-comm__summary-left">
            <div class="prm-comm__summary-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
                <div class="prm-comm__summary-title">Today</div>
                <div class="prm-comm__summary-sub">{{ $stats['callbacks_today'] ?? 34 }} Communications</div>
            </div>
        </div>
        <div class="prm-comm__summary-divider"></div>
        <div class="prm-comm__summary-stat">
            <div class="prm-comm__stat-icon prm-comm__stat-icon--call">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.36 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6.29 6.29l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
            </div>
            <div>
                <div class="prm-comm__stat-label">Call</div>
                <div class="prm-comm__stat-val">14</div>
            </div>
        </div>
        <div class="prm-comm__summary-divider"></div>
        <div class="prm-comm__summary-stat">
            <div class="prm-comm__stat-icon prm-comm__stat-icon--wa">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            </div>
            <div>
                <div class="prm-comm__stat-label">WhatsApp</div>
                <div class="prm-comm__stat-val">20</div>
            </div>
        </div>
        <div class="prm-comm__summary-divider"></div>
        <div class="prm-comm__summary-stat">
            <div class="prm-comm__stat-icon prm-comm__stat-icon--img">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </div>
            <div>
                <div class="prm-comm__stat-label">Image / Brochure</div>
                <div class="prm-comm__stat-val">6</div>
            </div>
        </div>
    </div>

    {{-- Table controls --}}
    <div class="prm-comm__controls">
        <div class="prm-comm__sort">
            Sort by:
            <select class="prm-comm__select" onchange="sortQueue(this.value)">
                <option value="priority">Priority</option>
                <option value="time">Follow-up Time</option>
                <option value="name">Name</option>
                <option value="channel">Channel</option>
            </select>
        </div>
        <div class="prm-comm__controls-spacer"></div>
        <div class="prm-comm__group">
            Group by:
            <select class="prm-comm__select" onchange="groupQueue(this.value)">
                <option value="none">None</option>
                <option value="source">Source</option>
                <option value="staff">Staff</option>
                <option value="priority">Priority</option>
            </select>
        </div>
        <div class="prm-comm__view-toggle">
            <button class="prm-comm__view-btn is-active" aria-label="List view" title="List view">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
            </button>
            <button class="prm-comm__view-btn" aria-label="Grid view" title="Grid view">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            </button>
        </div>
    </div>

    {{-- Data table --}}
    <div class="prm-comm__table-wrap">
        <table class="prm-comm__table" id="comm-table">
            <thead>
                <tr>
                    <th style="width:220px">Lead / Contact</th>
                    <th style="width:150px">Purpose</th>
                    <th style="width:120px">Channel</th>
                    <th style="width:110px">Follow-up Time</th>
                    <th style="width:80px">Priority</th>
                    <th>Last Note / Status</th>
                    <th style="width:48px">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($queue as $item)
                @php
                    $nameParts = explode(' ', trim($item['name'] ?? 'Unknown'));
                    $initials  = strtoupper(substr($nameParts[0], 0, 1));
                    $initials .= isset($nameParts[1]) ? strtoupper(substr($nameParts[1], 0, 1)) : '';
                @endphp
                <tr class="prm-comm__row" data-id="{{ $item['id'] ?? '' }}" data-priority="{{ $item['priority'] ?? 'medium' }}" data-phone="{{ $item['phone'] ?? '' }}">

                    {{-- Lead / Contact --}}
                    <td>
                        <div class="prm-comm__lead-cell">
                            <div class="prm-comm__avatar prm-comm__avatar--{{ $item['avatar_color'] ?? 'purple' }}">
                                {{ $initials }}
                            </div>
                            <div>
                                <div class="prm-comm__lead-top">
                                    <span class="prm-comm__lead-name">{{ $item['name'] ?? '—' }}</span>
                                    @if(($item['classification'] ?? '') === 'new_patient' || ($item['type'] ?? '') === 'new_lead')
                                        <span class="prm-comm__badge prm-comm__badge--new-lead">New Lead</span>
                                    @elseif(($item['classification'] ?? '') === 'existing_patient' || ($item['type'] ?? '') === 'existing')
                                        <span class="prm-comm__badge prm-comm__badge--existing">Existing Patient</span>
                                    @endif
                                </div>
                                <div class="prm-comm__lead-treatment">{{ $item['context'] ?? $item['note'] ?? '—' }}</div>
                                <div class="prm-comm__lead-phone">
                                    @if(($item['source'] ?? '') === 'whatsapp')
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                    @else
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.36 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6.29 6.29l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                    @endif
                                    {{ $item['phone'] ?? '—' }}
                                </div>
                            </div>
                        </div>
                    </td>

                    {{-- Purpose --}}
                    <td>
                        <span class="prm-comm__purpose">{{ $item['purpose'] ?? '—' }}</span>
                    </td>

                    {{-- Channel --}}
                    <td>
                        <div class="prm-comm__channel">
                            @php $src = $item['source'] ?? 'call'; @endphp
                            @if($src === 'whatsapp')
                                <div class="prm-comm__ch-icon prm-comm__ch-icon--wa">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                                </div>
                                WhatsApp
                            @elseif($src === 'instagram')
                                <div class="prm-comm__ch-icon prm-comm__ch-icon--ig">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="5"/><circle cx="17.5" cy="6.5" r="1.5"/></svg>
                                </div>
                                Instagram
                            @elseif($src === 'image')
                                <div class="prm-comm__ch-icon prm-comm__ch-icon--img">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                                </div>
                                Image
                            @else
                                <div class="prm-comm__ch-icon prm-comm__ch-icon--call">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.36 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6.29 6.29l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                                </div>
                                Call
                            @endif
                        </div>
                    </td>

                    {{-- Follow-up Time --}}
                    <td>
                        <span class="prm-comm__time {{ ($item['is_overdue'] ?? false) ? 'prm-comm__time--overdue' : '' }}">
                            {{ isset($item['due_at']) ? \Carbon\Carbon::parse($item['due_at'])->format('g:i A') : '—' }}
                        </span>
                    </td>

                    {{-- Priority --}}
                    <td>
                        @php $prio = $item['priority'] ?? 'medium'; @endphp
                        <span class="prm-comm__priority prm-comm__priority--{{ $prio }}">
                            {{ ucfirst($prio) }}
                        </span>
                    </td>

                    {{-- Last Note / Status --}}
                    <td>
                        @if(!empty($item['last_note']))
                        <div class="prm-comm__note-main">{{ Str::limit($item['last_note'], 60) }}</div>
                        @endif
                        @if(!empty($item['last_note_at']) || !empty($item['assigned_to_name']))
                        <div class="prm-comm__note-meta">
                            @if(!empty($item['last_note_at']))
                                <span class="prm-comm__note-time">· {{ $item['last_note_at'] }}</span>
                            @endif
                            @if(!empty($item['assigned_to_name']))
                                by {{ $item['assigned_to_name'] }}
                            @endif
                        </div>
                        @endif
                    </td>

                    {{-- Actions --}}
                    <td>
                        <div class="prm-comm__actions-cell">
                            <button class="prm-comm__action-dot"
                                    aria-label="Actions for {{ $item['name'] ?? 'record' }}"
                                    onclick="openActionMenu(event, '{{ $item['id'] ?? '' }}')">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="prm-comm__empty">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            <p>Queue is clear. All communications are handled.</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Table footer: count + pagination --}}
    <div class="prm-comm__footer">
        <span class="prm-comm__showing">
            Showing 1 to {{ min(count($queue), $perPage ?? 15) }} of {{ $total ?? count($queue) }}
        </span>
        <div class="prm-comm__pagination">
            @if(($currentPage ?? 1) > 1)
            <a href="{{ request()->fullUrlWithQuery(['page' => ($currentPage ?? 1) - 1]) }}" class="prm-comm__page-btn" aria-label="Previous page">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            @else
            <button class="prm-comm__page-btn" disabled aria-label="Previous page">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            </button>
            @endif

            @for($p = 1; $p <= min($lastPage ?? 1, 6); $p++)
                @if($p === ($currentPage ?? 1))
                    <span class="prm-comm__page-btn prm-comm__page-btn--active" aria-current="page">{{ $p }}</span>
                @else
                    <a href="{{ request()->fullUrlWithQuery(['page' => $p]) }}" class="prm-comm__page-btn">{{ $p }}</a>
                @endif
            @endfor

            @if(($lastPage ?? 1) > 6)
                <span class="prm-comm__page-ellipsis">…</span>
                <a href="{{ request()->fullUrlWithQuery(['page' => $lastPage]) }}" class="prm-comm__page-btn">{{ $lastPage }}</a>
            @endif

            @if(($currentPage ?? 1) < ($lastPage ?? 1))
            <a href="{{ request()->fullUrlWithQuery(['page' => ($currentPage ?? 1) + 1]) }}" class="prm-comm__page-btn" aria-label="Next page">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
            @else
            <button class="prm-comm__page-btn" disabled aria-label="Next page">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
            </button>
            @endif
        </div>
    </div>

</div>

{{-- Action dropdown menu --}}
<div class="prm-comm__action-menu" id="action-menu" style="display:none" role="menu">
    <button class="prm-comm__action-menu-item" onclick="doAction('call')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12 19.79 19.79 0 0 1 1.61 3.36 2 2 0 0 1 3.6 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6.29 6.29l.91-.91a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        Call
    </button>
    <button class="prm-comm__action-menu-item" onclick="doAction('whatsapp')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Open WhatsApp
    </button>
    <button class="prm-comm__action-menu-item" onclick="doAction('note')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Add Note
    </button>
    <button class="prm-comm__action-menu-item" onclick="doAction('followup')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
        Schedule Follow-up
    </button>
    <button class="prm-comm__action-menu-item" onclick="doAction('assign')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Assign Staff
    </button>
    <button class="prm-comm__action-menu-item" onclick="doAction('pipeline')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Move Pipeline
    </button>
    <div class="prm-comm__action-menu-divider"></div>
    <button class="prm-comm__action-menu-item prm-comm__action-menu-item--danger" onclick="doAction('escalate')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Escalate
    </button>
    <button class="prm-comm__action-menu-item prm-comm__action-menu-item--success" onclick="doAction('complete')">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
        Mark Complete
    </button>
</div>

@endsection

@push('scripts')
    <script src="{{ asset('js/communication/manager.js') }}"></script>
@endpush