{{--
    Component: prm.lead-card
    Props:
      $lead  — array with keys: id, name, phone, stage, urgency, interest, source, assigned, follow|consulted|planOn|convertedOn
      $stage — array with keys: id, label, color, bg
--}}
@php
    $urgencyColors = ['high' => '#E24B4A', 'medium' => '#EF9F27', 'low' => '#1D9E75'];
    $urgencyColor  = $urgencyColors[$lead['urgency'] ?? 'medium'];
    $initials      = collect(explode(' ', $lead['name']))->map(fn($w) => strtoupper($w[0]))->take(2)->join('');
@endphp

<div class="prm-lead-card"
     draggable="true"
     data-lead-id="{{ $lead['id'] }}"
     data-stage="{{ $lead['stage'] }}"
     ondragstart="PrmBoard.onDragStart(event, {{ $lead['id'] }}, '{{ $lead['stage'] }}')"
     data-action="open-lead-detail">

    {{-- Top row: name + actions --}}
    <div class="prm-lead-card__top">
        <div class="prm-lead-card__name-block">
            <div class="prm-lead-card__name">{{ $lead['name'] }}</div>
            <div class="prm-lead-card__phone">{{ $lead['phone'] }}</div>
        </div>
        <div class="prm-lead-card__actions">
            <a href="tel:{{ preg_replace('/\s+/', '', $lead['phone']) }}"
               class="prm-lead-card__call-btn"
               onclick="event.stopPropagation()"
               title="Call {{ $lead['name'] }}">
                <i class="ti ti-phone"></i>
            </a>
            <button class="prm-lead-card__more-btn"
                    onclick="event.stopPropagation(); PrmBoard.openCardMenu(this, {{ $lead['id'] }})"
                    title="More options">
                <i class="ti ti-dots-vertical"></i>
            </button>
        </div>
    </div>

    {{-- Stage badge --}}
    <div class="prm-lead-card__badge-row">
        <x-prm.stage-badge :stage="$lead['stage']" />
        @if(($lead['urgency'] ?? '') === 'high')
        <span class="prm-lead-card__urgency" style="color:{{ $urgencyColor }}">
            <i class="ti ti-flame"></i>
        </span>
        @endif
    </div>

    {{-- Date row --}}
    <div class="prm-lead-card__date-row">
        @if(!empty($lead['follow']))
            <i class="ti ti-clock"></i>
            Follow-up: {{ $lead['follow'] }}
        @elseif(!empty($lead['consulted']))
            <i class="ti ti-calendar"></i>
            Consulted on: {{ $lead['consulted'] }}
        @elseif(!empty($lead['planOn']))
            <i class="ti ti-calendar"></i>
            Plan on: {{ $lead['planOn'] }}
        @elseif(!empty($lead['convertedOn']))
            <i class="ti ti-check" style="color:#3B6D11"></i>
            {{ $lead['convertedOn'] }}
        @endif
    </div>

</div>
