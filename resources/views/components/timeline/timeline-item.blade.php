{{-- resources/views/components/timeline/timeline-item.blade.php --}}
<div class="timeline-item tl-{{ $event['type'] }} tl-{{ $event['subtype'] ?? '' }}" data-type="{{ $event['type'] }}">

    {{-- Connector line --}}
    <div class="tl-connector">
        <div class="tl-dot tl-dot-{{ $event['color'] }}">
            @include('components.timeline.event-icon', ['type' => $event['type'], 'subtype' => $event['subtype'] ?? ''])
        </div>
        <div class="tl-line"></div>
    </div>

    {{-- Content --}}
    <div class="tl-content">
        <div class="tl-header">
            <div class="tl-title-row">
                <span class="tl-title">{{ $event['title'] }}</span>
                @if($event['outcome'])
                    <span class="tl-outcome-chip outcome-{{ Str::slug($event['outcome']) }}">
                        {{ $event['outcome'] }}
                    </span>
                @endif
            </div>
            <div class="tl-meta">
                <span class="tl-date">{{ $event['date'] }}</span>
                <span class="tl-sep">·</span>
                <span class="tl-time">{{ $event['time'] }}</span>
                @if($event['duration'])
                    <span class="tl-sep">·</span>
                    <span class="tl-duration">{{ $event['duration'] }}</span>
                @endif
                <span class="tl-sep">·</span>
                <span class="tl-actor">by {{ $event['actor'] }}</span>
            </div>
        </div>
        <p class="tl-description">{{ $event['description'] }}</p>

        {{-- Type-specific actions --}}
        <div class="tl-actions">
            @if($event['type'] === 'call')
                <button class="tl-action-btn" onclick="openLogCallModal()">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.948V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 8V5z"/>
                    </svg>
                    Log Another Call
                </button>
            @endif
            @if($event['type'] === 'followup')
                <button class="tl-action-btn" onclick="openScheduleFollowupModal()">
                    <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    Reschedule
                </button>
            @endif
            <button class="tl-action-btn" onclick="openAddNoteModal()">
                <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Add Note
            </button>
        </div>
    </div>

</div>
