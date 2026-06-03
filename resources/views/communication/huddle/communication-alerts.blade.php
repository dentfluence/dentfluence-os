{{--
    communication/huddle/communication-alerts.blade.php
    Embeddable partial — include inside existing Daily Huddle view.

    Usage:
        @include('communication.huddle.communication-alerts', ['alerts' => $commAlerts])

    Required variables:
        $alerts  — array from HuddleController::buildAlerts()
        $counts  — array from HuddleController::buildCounts()
--}}

<div class="comm-huddle-alerts" id="commHuddleAlerts">

    <div class="comm-huddle-alerts__header">
        <div class="comm-huddle-alerts__title-wrap">
            <span class="comm-huddle-alerts__icon">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/>
                    <path d="M13.73 21a2 2 0 0 1-3.46 0"/>
                </svg>
            </span>
            <h3 class="comm-huddle-alerts__title">Communication Alerts</h3>
        </div>
<a href="/communication/prm" class="comm-huddle-alerts__view-all">            View All →
        </a>
    </div>

    {{-- Alert Cards --}}
    <div class="comm-huddle-alerts__grid">
        @foreach($alerts as $alert)
            <div class="comm-alert-card {{ isset($alert['urgent']) && $alert['urgent'] ? 'comm-alert-card--urgent' : '' }}"
                 data-type="{{ $alert['type'] }}">

                <div class="comm-alert-card__top">
                    <span class="comm-alert-card__emoji">{{ $alert['icon'] }}</span>
                    <span class="comm-alert-card__count"
                          style="color: {{ $alert['color'] }}">{{ $alert['count'] }}</span>
                </div>

                <div class="comm-alert-card__title">{{ $alert['title'] }}</div>

                @if(!empty($alert['names']))
                    <div class="comm-alert-card__names">
                        {{ implode(', ', array_slice($alert['names'], 0, 2)) }}
                        @if(count($alert['names']) > 2)
                            <span class="comm-alert-card__more">+{{ count($alert['names']) - 2 }} more</span>
                        @endif
                    </div>
                @endif

                <button class="comm-alert-card__action"
        style="--alert-color: {{ $alert['color'] }}; color: {{ $alert['color'] }}; border: 1.5px solid {{ $alert['color'] }};"
        onclick="{{ $alert['type'] === 'overdue' ? 'document.getElementById(\'commOverdueSummary\').scrollIntoView({behavior:\'smooth\'})' : 'commHuddleAlertAction(\'' . $alert['type'] . '\')' }}">
    {{ $alert['action'] }}
</button>
            </div>
        @endforeach
    </div>

    {{-- Quick Summary Bar --}}
    <div class="comm-huddle-alerts__summary-bar">
        <div class="comm-summary-stat">
            <span class="comm-summary-stat__num comm-summary-stat__num--red">{{ $counts['overdue_callbacks'] }}</span>
            <span class="comm-summary-stat__label">Overdue</span>
        </div>
        <div class="comm-summary-divider"></div>
        <div class="comm-summary-stat">
            <span class="comm-summary-stat__num comm-summary-stat__num--blue">{{ $counts['pending_today'] }}</span>
            <span class="comm-summary-stat__label">Due Today</span>
        </div>
        <div class="comm-summary-divider"></div>
        <div class="comm-summary-stat">
            <span class="comm-summary-stat__num comm-summary-stat__num--purple">{{ $counts['ongoing_treatments'] }}</span>
            <span class="comm-summary-stat__label">Ongoing</span>
        </div>
        <div class="comm-summary-divider"></div>
        <div class="comm-summary-stat">
            <span class="comm-summary-stat__num comm-summary-stat__num--green">{{ $counts['long_term_followups'] }}</span>
            <span class="comm-summary-stat__label">Long Term</span>
        </div>
        <div class="comm-summary-divider"></div>
        <div class="comm-summary-stat">
            <span class="comm-summary-stat__num comm-summary-stat__num--orange">{{ $counts['pending_estimates'] }}</span>
            <span class="comm-summary-stat__label">Estimates</span>
        </div>
    </div>

</div>
