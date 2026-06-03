{{--
    communication/huddle/overdue-summary.blade.php
    Embeddable partial — include inside existing Daily Huddle view.

    Usage:
        @include('communication.huddle.overdue-summary', [
            'overdue' => $commOverdue,
            'counts'  => $commCounts,
        ])

    Required variables:
        $overdue  — array from HuddleController::buildOverdueItems()
        $counts   — array from HuddleController::buildCounts()
--}}

<div class="comm-overdue-summary" id="commOverdueSummary">

    {{-- Section Header --}}
    <div class="comm-overdue-summary__header">
        <div class="comm-overdue-summary__title-wrap">
            <span class="comm-overdue-summary__dot"></span>
            <h3 class="comm-overdue-summary__title">Overdue Follow-ups</h3>
            <span class="comm-overdue-summary__badge">{{ $counts['overdue_callbacks'] }}</span>
        </div>
        <a href="{{ '/communication/prm' }}?filter=overdue"
           class="comm-overdue-summary__view-all">
            View All →
        </a>
    </div>

    {{-- Overdue Cards Row --}}
    <div class="comm-overdue-summary__cards" id="commOverdueCards">
       @forelse($overdue as $item)
            <a href="/communication/prm?lead={{ $item['id'] ?? '' }}" class="comm-overdue-card" style="text-decoration:none; color:inherit; display:block;">
                {{-- Avatar --}}
                <div class="comm-overdue-card__avatar"
                     style="background: {{ $item['color'] }}22; color: {{ $item['color'] }}">
                    {{ $item['initials'] }}
                </div>

                {{-- Info --}}
                <div class="comm-overdue-card__info">
                    <div class="comm-overdue-card__name">{{ $item['name'] }}</div>
                    <div class="comm-overdue-card__meta">
                        @if($item['icon'] === 'whatsapp')
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="#25D366">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                        @else
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#E74C3C" stroke-width="2.5">
                                <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .93h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                            </svg>
                        @endif
                        <span>{{ $item['type'] }}</span>
                    </div>
                    <div class="comm-overdue-card__date">{{ $item['due_date'] }}</div>
                </div>

                {{-- Overdue Badge --}}
                <div class="comm-overdue-card__right">
                    <span class="comm-overdue-card__overdue-tag">
                        Overdue {{ $item['overdue'] }}
                    </span>
                    <button class="comm-overdue-card__call-btn"
                            title="Call {{ $item['name'] }}"
                            onclick="commHuddleCall('{{ $item['phone'] }}', '{{ $item['name'] }}')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81a19.79 19.79 0 01-3.07-8.67A2 2 0 012 .93h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.09 8.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/>
                        </svg>
                    </button>
                </div>
            </div>
        @empty
            <div class="comm-overdue-summary__empty">
                <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#27AE60" stroke-width="1.5">
                    <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                <p>No overdue follow-ups today!</p>
            </div>
        @endforelse
    </div>

    {{-- Escalations Alert (show only if there are escalations) --}}
    @if($counts['escalations'] > 0)
        <div class="comm-overdue-summary__escalation-alert">
            <span class="comm-escalation-alert__icon">🚨</span>
            <span class="comm-escalation-alert__text">
                <strong>{{ $counts['escalations'] }} escalations</strong> require immediate attention
            </span>
            <a href="{{ '/communication/prm' }}?filter=escalated"
               class="comm-escalation-alert__btn">
                Resolve Now
            </a>
        </div>
    @endif

</div>
