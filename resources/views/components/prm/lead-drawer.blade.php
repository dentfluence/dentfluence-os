{{--
    Lead Drawer — slides in from the right when a lead card is clicked.
    Variables: $lead (array), $stage (array)
--}}
<div class="lead-drawer" id="leadDrawer" data-lead-id="{{ $lead['id'] ?? '' }}">

    {{-- Header --}}
    <div class="lead-drawer__header">
        <div class="lead-drawer__avatar">
            {{ strtoupper(substr($lead['name'] ?? 'U', 0, 1)) }}{{ strtoupper(substr(strstr($lead['name'] ?? 'U ', ' '), 1, 1)) }}
        </div>
        <div class="lead-drawer__header-info">
            <div class="lead-drawer__name">{{ $lead['name'] ?? '—' }}</div>
            <div class="lead-drawer__phone">
                <i class="ti ti-phone"></i> {{ $lead['phone'] ?? '—' }}
            </div>
            <span class="lead-drawer__stage-badge"
                  style="background:{{ $stage['bg'] ?? '#eef2ff' }};color:{{ $stage['color'] ?? '#6366f1' }}">
                {{ $stage['label'] ?? 'Unknown' }}
            </span>
        </div>
        <button class="lead-drawer__close" onclick="PrmBoard.closeDrawer()">
            <i class="ti ti-x"></i>
        </button>
    </div>

    {{-- Quick Actions --}}
    <div class="lead-drawer__actions">
        <a href="tel:{{ $lead['phone'] ?? '' }}" class="lead-drawer__action lead-drawer__action--call">
            <i class="ti ti-phone"></i> Call
        </a>
        <a href="https://wa.me/91{{ preg_replace('/\D/', '', $lead['phone'] ?? '') }}"
           target="_blank"
           class="lead-drawer__action lead-drawer__action--whatsapp">
            <i class="ti ti-brand-whatsapp"></i> WhatsApp
        </a>
        <button class="lead-drawer__action lead-drawer__action--reschedule"
                onclick="PrmBoard.openReschedule('{{ $lead['id'] ?? '' }}')">
            <i class="ti ti-calendar"></i> Reschedule
        </button>
        <button class="lead-drawer__action lead-drawer__action--more"
                onclick="PrmBoard.openMoreActions('{{ $lead['id'] ?? '' }}')">
            <i class="ti ti-dots"></i> More
        </button>
    </div>

    {{-- Key Info Strip --}}
    <div class="lead-drawer__info-strip">
        <div class="lead-drawer__info-item">
            <span class="lead-drawer__info-label">Treatment Interest</span>
            <span class="lead-drawer__info-value">{{ $lead['treatment'] ?? '—' }}</span>
        </div>
        <div class="lead-drawer__info-item">
            <span class="lead-drawer__info-label">Source</span>
            <span class="lead-drawer__info-value">{{ $lead['source'] ?? '—' }}</span>
        </div>
        <div class="lead-drawer__info-item">
            <span class="lead-drawer__info-label">Assigned To</span>
            <span class="lead-drawer__info-value">{{ $lead['assigned_to'] ?? '—' }}</span>
        </div>
        <div class="lead-drawer__info-item">
            <span class="lead-drawer__info-label">Lead Created</span>
            <span class="lead-drawer__info-value">{{ $lead['created_at'] ?? '—' }}</span>
        </div>
    </div>

    {{-- Next Follow-up --}}
    <div class="lead-drawer__followup">
        <div class="lead-drawer__followup-icon">
            <i class="ti ti-calendar-event"></i>
        </div>
        <div class="lead-drawer__followup-info">
            <div class="lead-drawer__followup-label">Next Follow-up</div>
            <div class="lead-drawer__followup-date">
                {{ $lead['followup_date'] ?? 'Not scheduled' }}
                @if(!empty($lead['followup_overdue']))
                    <span class="lead-drawer__overdue-tag">Overdue</span>
                @endif
            </div>
        </div>
        <button class="lead-drawer__followup-reschedule"
                onclick="PrmBoard.openReschedule('{{ $lead['id'] ?? '' }}')">
            <i class="ti ti-calendar"></i> Reschedule
        </button>
    </div>

    {{-- Last Interaction --}}
    @if(!empty($lead['last_note']))
    <div class="lead-drawer__last-interaction">
        <div class="lead-drawer__section-title">Last Interaction</div>
        <div class="lead-drawer__last-note">{{ $lead['last_note'] }}</div>
        <div class="lead-drawer__last-meta">
            <i class="ti ti-calendar"></i> {{ $lead['last_note_date'] ?? '' }}
            &nbsp;·&nbsp;
            <i class="ti ti-clock"></i> {{ $lead['last_note_time'] ?? '' }}
            &nbsp;·&nbsp; by {{ $lead['last_note_by'] ?? '' }}
        </div>
    </div>
    @endif

    {{-- Activity Tabs --}}
    <div class="lead-drawer__tabs">
        <button class="lead-drawer__tab active" data-tab="activity">Activity & Notes</button>
        <button class="lead-drawer__tab" data-tab="info">Patient Info</button>
        <button class="lead-drawer__tab" data-tab="tasks">Tasks (0)</button>
    </div>

    {{-- Activity Feed --}}
    <div class="lead-drawer__tab-content" id="drawerTab-activity">
        @forelse($activities ?? [] as $activity)
        <div class="lead-drawer__activity-item">
            <div class="lead-drawer__activity-icon"
                 style="background:{{ $activity['icon_bg'] ?? '#f3f4f6' }};color:{{ $activity['icon_color'] ?? '#374151' }}">
                <i class="ti ti-{{ $activity['icon'] ?? 'info-circle' }}"></i>
            </div>
            <div class="lead-drawer__activity-body">
                <div class="lead-drawer__activity-action">
                    {{ $activity['action'] ?? '' }}
                    @if(!empty($activity['badge']))
                        <span class="lead-drawer__activity-badge"
                              style="background:{{ $activity['badge_bg'] ?? '#e5e7eb' }};color:{{ $activity['badge_color'] ?? '#374151' }}">
                            {{ $activity['badge'] }}
                        </span>
                    @endif
                </div>
                <div class="lead-drawer__activity-desc">{{ $activity['desc'] ?? '' }}</div>
                <div class="lead-drawer__activity-meta">
                    {{ $activity['date'] ?? '' }} · by {{ $activity['by'] ?? '' }}
                </div>
            </div>
        </div>
        @empty
        <div class="lead-drawer__empty">No activity yet.</div>
        @endforelse

        <button class="lead-drawer__view-all-link">View All Activities ↓</button>
    </div>

    {{-- Bottom Actions --}}
    <div class="lead-drawer__footer">
        <button class="lead-drawer__footer-btn lead-drawer__footer-btn--note"
                onclick="PrmBoard.openAddNote('{{ $lead['id'] ?? '' }}')">
            <i class="ti ti-plus"></i> Add Note
        </button>
        <button class="lead-drawer__footer-btn lead-drawer__footer-btn--unreachable"
                onclick="PrmBoard.markUnreachable('{{ $lead['id'] ?? '' }}')">
            <i class="ti ti-x"></i> Not Reachable
        </button>
        <button class="lead-drawer__footer-btn lead-drawer__footer-btn--reschedule"
                onclick="PrmBoard.openReschedule('{{ $lead['id'] ?? '' }}')">
            <i class="ti ti-calendar"></i> Reschedule
        </button>
        <button class="lead-drawer__footer-btn lead-drawer__footer-btn--done"
                onclick="PrmBoard.markDone('{{ $lead['id'] ?? '' }}')">
            <i class="ti ti-check"></i> Mark as Done
        </button>
    </div>

    {{-- Convert to Patient --}}
    <div class="lead-drawer__convert">
        <button class="lead-drawer__convert-btn"
                onclick="PrmBoard.openConvertToPatient('{{ $lead['id'] ?? '' }}')">
            <i class="ti ti-user-plus"></i> Convert to Patient
        </button>
    </div>

</div>