{{-- resources/views/communication/followup/partials/calendar-week.blade.php --}}

@php
$days = [
    ['date' => '2025-05-18', 'label' => 'Sun', 'day' => '18 May'],
    ['date' => '2025-05-19', 'label' => 'Mon', 'day' => '19 May', 'today' => true],
    ['date' => '2025-05-20', 'label' => 'Tue', 'day' => '20 May'],
    ['date' => '2025-05-21', 'label' => 'Wed', 'day' => '21 May'],
    ['date' => '2025-05-22', 'label' => 'Thu', 'day' => '22 May'],
    ['date' => '2025-05-23', 'label' => 'Fri', 'day' => '23 May'],
    ['date' => '2025-05-24', 'label' => 'Sat', 'day' => '24 May'],
];

$timeSlots = [
    '9:00 AM', '10:00 AM', '11:00 AM', '12:00 PM',
    '1:00 PM', '2:00 PM', '3:00 PM', '4:00 PM',
    '5:00 PM', '6:00 PM', '7:00 PM',
];
@endphp

<div class="fu-week-grid">

    {{-- Header row --}}
    <div class="fu-week-header">
        <div class="fu-week-gutter"></div>
        @foreach($days as $day)
        <div class="fu-week-day-header {{ !empty($day['today']) ? 'fu-today-header' : '' }}">
            <span class="fu-dow">{{ $day['label'] }}</span>
            <span class="fu-dom {{ !empty($day['today']) ? 'fu-dom-today' : '' }}">
                {{ explode(' ', $day['day'])[0] }}
                @if(!empty($day['today']))
                <span class="fu-today-dot"></span>
                @endif
            </span>
        </div>
        @endforeach
    </div>

    {{-- Time grid --}}
    <div class="fu-week-body" id="weekBody">
        @foreach($timeSlots as $slot)
        <div class="fu-time-row">
            <div class="fu-time-label">{{ $slot }}</div>
            @foreach($days as $day)
            <div class="fu-time-cell" data-date="{{ $day['date'] }}" data-time="{{ $slot }}">
                @if(isset($events[$day['date']]))
                    @foreach($events[$day['date']] as $event)
                        @if($event['time'] === $slot)
                        <div class="fu-event fu-event-{{ $event['type'] }}"
                             data-id="{{ $event['id'] }}"
                             onclick="openEventActions({{ $event['id'] }}, '{{ $event['name'] }}', '{{ $event['channel'] }}')"
                             style="border-left: 3px solid {{ $event['color'] }}; background: {{ $event['color'] }}15">
                            <span class="fu-event-name">{{ $event['name'] }}</span>
                            <span class="fu-event-time">{{ $event['time'] }}</span>
                            <span class="fu-event-channel">
                                @if($event['channel'] === 'call') 📞
                                @elseif($event['channel'] === 'whatsapp') 💬
                                @else 🏥
                                @endif
                                {{ ucfirst($event['channel']) }}
                            </span>
                        </div>
                        @endif
                    @endforeach
                @endif
            </div>
            @endforeach
        </div>
        @endforeach
    </div>

</div>
