{{--
    Lead detail drawer/modal.
    Can be rendered:
      - standalone via AJAX (route communication.prm.lead-detail)
      - or included in index.blade.php for initial-load dummy state
    Variables: $lead (array), $activities (array)
--}}

@php
    $initials = collect(explode(' ', $lead['name']))->map(fn($w) => strtoupper($w[0]))->take(2)->join('');
    $urgencyColors = ['high' => '#E24B4A', 'medium' => '#EF9F27', 'low' => '#1D9E75'];
    $urgencyColor  = $urgencyColors[$lead['urgency'] ?? 'medium'];
    $activityIcons = [
        'phone'        => ['icon' => 'ti-phone',      'bg' => '#E1F5EE', 'color' => '#0F6E56'],
        'calendar'     => ['icon' => 'ti-calendar',   'bg' => '#FAEEDA', 'color' => '#854F0B'],
        'whatsapp'     => ['icon' => 'ti-brand-whatsapp', 'bg' => '#E1F5EE','color' => '#0F6E56'],
        'phone-missed' => ['icon' => 'ti-phone-off',  'bg' => '#F1EFE8', 'color' => '#5F5E5A'],
    ];
@endphp

<div class="prm-drawer" id="leadDrawer" data-lead-id="{{ $lead['id'] }}">

    {{-- ── Drawer Header (dark) ─────────────────────────────────────── --}}
    <div class="prm-drawer__topbar">
        <button class="prm-drawer__back" id="drawerClose">
            <i class="ti ti-arrow-left"></i>
        </button>
        <span class="prm-drawer__topbar-title">Lead Details</span>
        <div class="prm-drawer__topbar-actions">
            <a href="{{ route('communication.prm.add-lead') }}?edit={{ $lead['id'] }}" class="prm-drawer__topbar-btn">
                <i class="ti ti-edit"></i>
            </a>
            <button class="prm-drawer__topbar-btn">
                <i class="ti ti-dots-vertical"></i>
            </button>
        </div>
    </div>

    <div class="prm-drawer__body">

        {{-- ── Identity Card ────────────────────────────────────────── --}}
        <div class="prm-drawer__card">
            <div class="prm-drawer__identity">
                <div class="comm-avatar comm-avatar--lg" style="background:#EEEDFE;color:#534AB7">
                    {{ $initials }}
                </div>
                <div class="prm-drawer__identity-info">
                    <div class="prm-drawer__name-row">
                        <span class="prm-drawer__name">{{ $lead['name'] }}</span>
                        <x-prm.stage-badge :stage="$lead['stage']" />
                    </div>
                    <div class="prm-drawer__contact-row">
                        <i class="ti ti-phone"></i> {{ $lead['phone'] }}
                    </div>
                    <div class="prm-drawer__contact-row">
                        <i class="ti ti-brand-whatsapp" style="color:#25D366"></i> {{ $lead['phone'] }}
                    </div>
                </div>
                <div class="prm-drawer__pipeline-meta">
                    <div class="prm-drawer__meta-item">
                        <span class="prm-drawer__meta-label">Status</span>
                        <span class="prm-badge" style="background:#FAEEDA;color:#854F0B">Appointment</span>
                    </div>
                    <div class="prm-drawer__meta-item">
                        <span class="prm-drawer__meta-label">Pipeline Stage</span>
                        <span class="prm-drawer__meta-value">2 / 6</span>
                    </div>
                    <div class="prm-drawer__meta-item">
                        <span class="prm-drawer__meta-label">Lead ID</span>
                        <span class="prm-drawer__meta-value">LD-000{{ str_pad($lead['id'], 3, '0', STR_PAD_LEFT) }}</span>
                    </div>
                </div>
            </div>
            <div class="prm-drawer__cta-row">
                <button class="prm-drawer__cta-btn prm-drawer__cta-btn--call">
                    <i class="ti ti-phone"></i> Call <i class="ti ti-chevron-down"></i>
                </button>
                <a href="https://wa.me/91{{ preg_replace('/\s+/', '', $lead['phone']) }}" target="_blank"
                   class="prm-drawer__cta-btn prm-drawer__cta-btn--wa">
                    <i class="ti ti-brand-whatsapp"></i> WhatsApp
                </a>
            </div>
        </div>

        {{-- ── Quick Info ───────────────────────────────────────────── --}}
        <div class="prm-drawer__card prm-drawer__info-grid">
            @foreach([
                ['icon'=>'ti-tooth',    'label'=>'Treatment Interest', 'value'=> $lead['interest'] ?? 'Dental Implant'],
                ['icon'=>'ti-phone',    'label'=>'Source',             'value'=> $lead['source']   ?? 'Call'],
                ['icon'=>'ti-user',     'label'=>'Assigned To',        'value'=> $lead['assigned'] ?? 'Neha (Front Desk)'],
                ['icon'=>'ti-calendar', 'label'=>'Lead Created',       'value'=> '10 May 2025, 10:30 AM'],
            ] as $info)
            <div class="prm-drawer__info-item">
                <div class="prm-drawer__info-icon" style="background:#EEEDFE;color:#534AB7">
                    <i class="ti {{ $info['icon'] }}"></i>
                </div>
                <div>
                    <div class="prm-drawer__info-label">{{ $info['label'] }}</div>
                    <div class="prm-drawer__info-value">{{ $info['value'] }}</div>
                </div>
            </div>
            @endforeach
        </div>

        {{-- ── Next Follow-up ───────────────────────────────────────── --}}
        <div class="prm-drawer__card prm-drawer__followup-row">
            <div class="prm-drawer__followup-left">
                <div class="prm-drawer__info-icon" style="background:#EEEDFE;color:#534AB7">
                    <i class="ti ti-calendar-event"></i>
                </div>
                <div>
                    <div class="prm-drawer__info-label">Next Follow-up</div>
                    <div class="prm-drawer__followup-date">12 May 2025 (Mon)</div>
                    <div class="prm-drawer__followup-time">11:00 AM</div>
                </div>
            </div>
            <div class="prm-drawer__followup-right">
                <span class="prm-badge prm-badge--danger-soft">
                    <i class="ti ti-bell"></i> Due Today
                </span>
                <button class="prm-btn prm-btn--outline prm-btn--sm" data-action="reschedule" data-lead="{{ $lead['id'] }}">
                    <i class="ti ti-calendar"></i> Reschedule
                </button>
            </div>
        </div>

        {{-- ── Last Interaction ─────────────────────────────────────── --}}
        <div class="prm-drawer__card">
            <div class="prm-drawer__section-title">
                <i class="ti ti-message-2" style="color:#534AB7"></i> Last Interaction
            </div>
            <p class="prm-drawer__last-interaction-text">
                Spoke to patient. Interested in implant. Will get back.
            </p>
            <div class="prm-drawer__last-interaction-meta">
                <span><i class="ti ti-calendar"></i> 10 May 2025</span>
                <span><i class="ti ti-clock"></i> 10:30 AM</span>
                <span>by Neha</span>
            </div>
        </div>

        {{-- ── Tabs ─────────────────────────────────────────────────── --}}
        <div class="prm-drawer__tabs">
            @foreach(['Activity & Notes', 'Patient Info', 'Documents (0)', 'Tasks (0)'] as $tab)
            <button class="prm-drawer__tab {{ $loop->first ? 'prm-drawer__tab--active' : '' }}"
                data-tab="{{ Str::slug($tab) }}">
                {{ $tab }}
            </button>
            @endforeach
        </div>

        {{-- ── Activity Tab Content ─────────────────────────────────── --}}
        <div class="prm-drawer__tab-content" id="tab-activity-notes">
            <div class="prm-activity-list">
                @foreach($activities as $i => $act)
                @php $icons = $activityIcons[$act['icon']] ?? ['icon'=>'ti-circle','bg'=>'#F1EFE8','color'=>'#888']; @endphp
                <div class="prm-activity-item">
                    <div class="prm-activity-item__left">
                        <div class="prm-activity-item__icon"
                             style="background:{{ $icons['bg'] }};color:{{ $icons['color'] }}">
                            <i class="ti {{ $icons['icon'] }}"></i>
                        </div>
                        @if(!$loop->last)
                        <div class="prm-activity-item__line"></div>
                        @endif
                    </div>
                    <div class="prm-activity-item__body">
                        <div class="prm-activity-item__title-row">
                            <span class="prm-activity-item__title">{{ $act['action'] }}</span>
                            @if(!empty($act['badge']))
                            <span class="prm-badge"
                                  style="background:{{ $act['badge_bg'] }};color:{{ $act['badge_color'] }}">
                                {{ $act['badge'] }}
                            </span>
                            @endif
                        </div>
                        <p class="prm-activity-item__desc">{{ $act['desc'] }}</p>
                        <div class="prm-activity-item__meta">
                            {{ $act['date'] }} · by {{ $act['by'] }}
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <button class="prm-drawer__view-all" data-action="view-all-activities" data-lead="{{ $lead['id'] }}">
                View All Activities <i class="ti ti-chevron-down"></i>
            </button>
        </div>

    </div>

    {{-- ── Sticky Footer Actions ────────────────────────────────────── --}}
    <div class="prm-drawer__footer">
        <div class="prm-drawer__footer-actions">
            <button class="prm-drawer__footer-btn prm-drawer__footer-btn--purple"
                data-action="add-note" data-lead="{{ $lead['id'] }}">
                <i class="ti ti-notes"></i> Add Note
            </button>
            <button class="prm-drawer__footer-btn prm-drawer__footer-btn--red"
                data-action="not-reachable" data-lead="{{ $lead['id'] }}">
                <i class="ti ti-x"></i> Not Reachable
            </button>
            <button class="prm-drawer__footer-btn prm-drawer__footer-btn--amber"
                data-action="reschedule" data-lead="{{ $lead['id'] }}">
                <i class="ti ti-calendar"></i> Reschedule
            </button>
            <button class="prm-drawer__footer-btn prm-drawer__footer-btn--green"
                data-action="mark-done" data-lead="{{ $lead['id'] }}">
                <i class="ti ti-check"></i> Mark as Done
            </button>
        </div>
        <button class="prm-drawer__convert-btn" data-action="convert-to-patient" data-lead="{{ $lead['id'] }}">
            <i class="ti ti-user-check"></i> Convert to Patient
        </button>
    </div>

</div>
