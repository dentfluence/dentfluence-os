@extends('layouts.app')
@section('title', ($lead['name'] ?? 'Lead') . ' — Lead Details')

@section('content')

<div class="prm-topbar">
    <div class="prm-brand">
        <div class="prm-logo"><i class="ti ti-tooth" aria-hidden="true"></i></div>
        <div>
            <div class="prm-brand-name">PRM</div>
            <div class="prm-brand-sub">Patient Relationship Manager</div>
        </div>
    </div>
    <x-communication.top-nav-tabs :counts="$navCounts" active="pipeline" />
    <div class="prm-topbar-right">
        <div class="prm-search">
            <i class="ti ti-search" aria-hidden="true"></i>
            <input type="text" placeholder="Search leads by name or phone">
        </div>
        <div class="prm-notif"><i class="ti ti-bell" aria-hidden="true"></i><span class="notif-badge">0</span></div>
        <div class="prm-user">
            <div class="prm-avatar">N</div>
            <div><div class="prm-user-name">Dr. Neha</div><div class="prm-user-role">Front Desk</div></div>
            <i class="ti ti-chevron-down" aria-hidden="true"></i>
        </div>
    </div>
</div>

<div class="ld-page">

    {{-- Back + title --}}
    <div class="ld-page-header">
        <a href="{{ route('prm.index') }}" class="back-btn">
            <i class="ti ti-arrow-left" aria-hidden="true"></i> Pipeline Board
        </a>
        <div class="ld-page-actions">
            <a href="/communication/prm/lead/{{ $lead['id'] }}/edit" class="btn-outline-sm">
                <i class="ti ti-edit" aria-hidden="true"></i> Edit Lead
            </a>
            <button class="btn-outline-sm btn-danger-outline" onclick="confirmDeleteLead({{ $lead['id'] }})">
                <i class="ti ti-trash" aria-hidden="true"></i> Delete
            </button>
        </div>
    </div>

    <div class="ld-layout">

        {{-- ── LEFT COLUMN ──────────────────────────────────── --}}
        <div class="ld-left">

            {{-- Lead hero card --}}
            <div class="ld-hero-card">
                <div class="ld-hero-top">
                    <div class="ld-hero-avatar">
                        {{ strtoupper(substr($lead['name'], 0, 1)) }}{{ strtoupper(substr(explode(' ', $lead['name'])[1] ?? '', 0, 1)) }}
                    </div>
                    <div class="ld-hero-info">
                        <div class="ld-hero-name">{{ $lead['name'] }}</div>
                        <x-prm.stage-badge :stage="$lead['stage']" />
                        <div class="ld-hero-phone">
                            <i class="ti ti-phone" aria-hidden="true"></i> {{ $lead['phone'] }}
                        </div>
                        @if(!empty($lead['alt_phone']))
                            <div class="ld-hero-phone">
                                <i class="ti ti-phone" aria-hidden="true"></i> {{ $lead['alt_phone'] }}
                            </div>
                        @endif
                    </div>
                    <div class="ld-hero-meta">
                        <div class="ld-meta-row"><span>Status</span> <x-prm.stage-badge :stage="$lead['stage']" /></div>
                        <div class="ld-meta-row"><span>Pipeline Stage</span> <strong>2 / 6</strong></div>
                        <div class="ld-meta-row"><span>Lead ID</span> <strong>LD-{{ str_pad($lead['id'], 6, '0', STR_PAD_LEFT) }}</strong></div>
                    </div>
                </div>
                <div class="ld-hero-actions">
                    <a href="tel:{{ preg_replace('/\s+/', '', $lead['phone']) }}" class="ld-action-btn btn-call">
                        <i class="ti ti-phone" aria-hidden="true"></i> Call
                        <i class="ti ti-chevron-down" aria-hidden="true"></i>
                    </a>
                    <a href="https://wa.me/91{{ preg_replace('/\s+/', '', $lead['phone']) }}" target="_blank" class="ld-action-btn btn-wa">
                        <i class="ti ti-brand-whatsapp" aria-hidden="true"></i> WhatsApp
                    </a>
                </div>
            </div>

            {{-- Info strip --}}
            <div class="ld-info-strip">
                <div class="ld-info-item">
                    <i class="ti ti-dental" aria-hidden="true"></i>
                    <div class="info-label">Treatment Interest</div>
                    <div class="info-val">{{ $lead['treatment'] }}</div>
                </div>
                <div class="ld-info-item">
                    <i class="ti ti-phone" aria-hidden="true"></i>
                    <div class="info-label">Source</div>
                    <div class="info-val">{{ $lead['source'] }}</div>
                </div>
                <div class="ld-info-item">
                    <i class="ti ti-user" aria-hidden="true"></i>
                    <div class="info-label">Assigned To</div>
                    <div class="info-val">{{ $lead['assigned_to'] }}</div>
                </div>
                <div class="ld-info-item">
                    <i class="ti ti-calendar" aria-hidden="true"></i>
                    <div class="info-label">Lead Created</div>
                    <div class="info-val">{{ \Carbon\Carbon::parse($lead['created_at'])->format('d M Y, h:i A') }}</div>
                </div>
            </div>

            {{-- Next follow-up --}}
            @if(!empty($lead['followup_date']))
            <div class="ld-followup-card {{ $lead['is_overdue'] ? 'fu-overdue' : '' }}">
                <div class="fu-icon"><i class="ti ti-calendar-event" aria-hidden="true"></i></div>
                <div class="fu-info">
                    <div class="fu-label">Next Follow-up</div>
                    <div class="fu-date">
                        {{ \Carbon\Carbon::parse($lead['followup_date'])->format('d M Y (D)') }}
                        @if($lead['is_overdue'])
                            <span class="fu-due-tag">
                                <i class="ti ti-alert-triangle" aria-hidden="true"></i> Overdue {{ $lead['overdue_days'] }}d
                            </span>
                        @elseif(\Carbon\Carbon::parse($lead['followup_date'])->isToday())
                            <span class="fu-due-tag fu-today">
                                <i class="ti ti-bell" aria-hidden="true"></i> Due Today
                            </span>
                        @endif
                    </div>
                    <div class="fu-time">{{ $lead['followup_time'] }}</div>
                </div>
                <button class="btn-outline-sm" onclick="openReschedule({{ $lead['id'] }})">
                    <i class="ti ti-calendar-plus" aria-hidden="true"></i> Reschedule
                </button>
            </div>
            @endif

            {{-- Last interaction --}}
            @if(!empty($lead['activity']))
            @php $last = $lead['activity'][0]; @endphp
            <div class="ld-last-interaction">
                <div class="li-head">
                    <span><i class="ti ti-message" aria-hidden="true"></i> Last Interaction</span>
                    <div class="li-meta">
                        <i class="ti ti-calendar" aria-hidden="true"></i> {{ \Carbon\Carbon::parse($last['date'])->format('d M Y') }}
                        &nbsp; <i class="ti ti-clock" aria-hidden="true"></i> {{ $last['time'] }}
                        &nbsp; by {{ $last['by'] }}
                    </div>
                </div>
                <p class="li-note">{{ $last['note'] }}</p>
            </div>
            @endif

            {{-- Tabs: Activity / Patient Info / Documents / Tasks --}}
            <div class="ld-tabs">
                <button class="ld-tab active" onclick="switchLdTab('activity', this)">
                    <i class="ti ti-clipboard-list" aria-hidden="true"></i> Activity & Notes
                </button>
                <button class="ld-tab" onclick="switchLdTab('patient', this)">
                    <i class="ti ti-user" aria-hidden="true"></i> Patient Info
                </button>
                <button class="ld-tab" onclick="switchLdTab('documents', this)">
                    <i class="ti ti-file" aria-hidden="true"></i> Documents (0)
                </button>
                <button class="ld-tab" onclick="switchLdTab('tasks', this)">
                    <i class="ti ti-checkbox" aria-hidden="true"></i> Tasks (0)
                </button>
            </div>

            {{-- Activity tab --}}
            <div id="tab-activity" class="ld-tab-panel">
                <div class="activity-timeline">
                    @forelse($lead['activity'] as $act)
                    @php
                    $iconMap = ['call'=>'phone','followup'=>'calendar-event','whatsapp'=>'brand-whatsapp','note'=>'notes','appointment'=>'calendar'];
                    $colorMap = ['call'=>'act-call','followup'=>'act-fu','whatsapp'=>'act-wa','note'=>'act-note','appointment'=>'act-appt'];
                    @endphp
                    <div class="act-item">
                        <div class="act-icon-wrap {{ $colorMap[$act['type']] ?? 'act-note' }}">
                            <i class="ti ti-{{ $iconMap[$act['type']] ?? 'notes' }}" aria-hidden="true"></i>
                        </div>
                        <div class="act-body">
                            <div class="act-label">
                                {{ $act['label'] }}
                                @if(!empty($act['outcome']))
                                    <span class="act-outcome">{{ $act['outcome'] }}</span>
                                @endif
                            </div>
                            <div class="act-note">{{ $act['note'] }}</div>
                        </div>
                        <div class="act-meta">
                            {{ \Carbon\Carbon::parse($act['date'])->format('d M Y') }}, {{ $act['time'] }}<br>
                            by {{ $act['by'] }}
                        </div>
                    </div>
                    @empty
                    <div class="act-empty">No activity recorded yet.</div>
                    @endforelse
                </div>
                @if(count($lead['activity']) > 3)
                <button class="view-all-act">
                    View All Activities <i class="ti ti-chevron-down" aria-hidden="true"></i>
                </button>
                @endif
            </div>

            {{-- Patient info tab --}}
            <div id="tab-patient" class="ld-tab-panel" style="display:none">
                <div class="info-grid">
                    <div class="info-kv"><span>Email</span><strong>{{ $lead['email'] ?: '—' }}</strong></div>
                    <div class="info-kv"><span>Date of Birth</span><strong>{{ $lead['dob'] ? \Carbon\Carbon::parse($lead['dob'])->format('d M Y') : '—' }}</strong></div>
                    <div class="info-kv"><span>Gender</span><strong>{{ $lead['gender'] ?: '—' }}</strong></div>
                    <div class="info-kv"><span>Occupation</span><strong>{{ $lead['occupation'] ?: '—' }}</strong></div>
                    <div class="info-kv"><span>Location</span><strong>{{ $lead['location'] ?: '—' }}</strong></div>
                    <div class="info-kv"><span>Language</span><strong>{{ $lead['language'] ?: '—' }}</strong></div>
                    <div class="info-kv"><span>Preferred Contact</span><strong>{{ ucfirst($lead['preferred_contact']) }}</strong></div>
                    <div class="info-kv"><span>Referred By</span><strong>{{ $lead['referred_by'] ?: '—' }}</strong></div>
                </div>
                @if(!empty($lead['notes']))
                <div class="info-notes-block">
                    <div class="info-notes-label">Notes</div>
                    <p>{{ $lead['notes'] }}</p>
                </div>
                @endif
                @if(!empty($lead['tags']))
                <div class="info-tags-block">
                    @foreach($lead['tags'] as $tag)
                        <span class="lead-tag">{{ $tag }}</span>
                    @endforeach
                </div>
                @endif
            </div>

            <div id="tab-documents" class="ld-tab-panel" style="display:none">
                <div class="act-empty">No documents uploaded yet.</div>
            </div>
            <div id="tab-tasks" class="ld-tab-panel" style="display:none">
                <div class="act-empty">No tasks assigned yet.</div>
            </div>

        </div>

        {{-- ── RIGHT COLUMN ─────────────────────────────────── --}}
        <div class="ld-right">
            <x-prm.stage-selector :current="$lead['stage']" :leadId="$lead['id']" />

            <div class="ld-quick-actions">
                <div class="qa-title">Quick Actions</div>
                <button class="qa-btn" onclick="openAddNote({{ $lead['id'] }})">
                    <i class="ti ti-notes" aria-hidden="true"></i> Add Note
                </button>
                <button class="qa-btn qa-btn-danger" onclick="markNotReachable({{ $lead['id'] }})">
                    <i class="ti ti-x" aria-hidden="true"></i> Not Reachable
                </button>
                <button class="qa-btn qa-btn-amber" onclick="openReschedule({{ $lead['id'] }})">
                    <i class="ti ti-calendar-plus" aria-hidden="true"></i> Reschedule
                </button>
                <button class="qa-btn qa-btn-success" onclick="markAsDone({{ $lead['id'] }})">
                    <i class="ti ti-check" aria-hidden="true"></i> Mark as Done
                </button>
            </div>

            <div class="ld-convert-btn">
                <button class="btn-convert-patient" onclick="openConvertToPatient({{ $lead['id'] }})">
                    <i class="ti ti-user-plus" aria-hidden="true"></i> Convert to Patient
                </button>
            </div>
        </div>

    </div>
</div>

@endsection

@push('scripts')
<script src="{{ asset('js/communication/lead-drawer.js') }}"></script>
@endpush
