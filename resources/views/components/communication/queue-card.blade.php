{{--
  Queue Card Component — Core card UI for each communication item.
  Usage: <x-communication.queue-card :item="$item" />
--}}
@props(['item'])

@php
$classLabels = [
    'new_patient'     => 'New Patient',
    'existing'        => 'Existing',
    'ongoing_case'    => 'Ongoing Case',
    'doctor'          => 'Doctor',
    'vendor'          => 'Vendor',
    'lab'             => 'Lab',
    'spam'            => 'Spam',
    'other_important' => 'Other Important',
    'other'           => 'Other',
];

$cardClass = 'cm-card';
if ($item['is_overdue'] ?? false) $cardClass .= ' overdue';
elseif (($item['status'] ?? '') === 'in_progress') $cardClass .= ' in-progress';
elseif (($item['status'] ?? '') === 'completed') $cardClass .= ' completed';
@endphp

<div class="{{ $cardClass }}" data-id="{{ $item['id'] }}"
     data-source="{{ $item['source'] }}"
     data-status="{{ $item['status'] }}"
     data-priority="{{ $item['priority'] }}">

    {{-- Source icon --}}
    <x-communication.source-icon :source="$item['source']" />

    {{-- Main content --}}
    <div class="cm-card-main">

        {{-- Top row: name + phone + status + overdue --}}
        <div class="cm-card-top">
            <span class="cm-person-name">{{ $item['person_name'] }}</span>
            <span class="cm-phone">{{ $item['phone'] }}</span>
            <x-communication.status-chip :status="$item['status']" />
            @if($item['is_overdue'] ?? false)
                <x-communication.overdue-badge :since="$item['overdue_since']" />
            @endif
            <span class="cm-class">{{ $classLabels[$item['classification']] ?? $item['classification'] }}</span>
        </div>

        {{-- Note --}}
        @if(!empty($item['note']))
        <p class="cm-card-note">{{ $item['note'] }}</p>
        @endif

        {{-- Meta: tags + assignee + timestamp --}}
        <div class="cm-card-meta">
            {{-- Tags --}}
            @foreach($item['tags'] ?? [] as $tag)
                <span class="cm-tag">{{ $tag }}</span>
            @endforeach

            {{-- Assignee --}}
            @if($item['assigned_to'] ?? false)
            <div class="cm-assignee">
                <div class="cm-avatar">{{ $item['assigned_avatar'] }}</div>
                <span>{{ $item['assigned_to'] }}</span>
            </div>
            @else
            <div class="cm-assignee" style="color: var(--cm-red); font-size: 12px; font-weight: 500;">
                ⚠ Unassigned
            </div>
            @endif

            {{-- Timestamp --}}
            <span class="cm-timestamp">
                {{ \Carbon\Carbon::parse($item['created_at'])->format('d M, h:i A') }}
            </span>
        </div>
    </div>

    {{-- Quick actions --}}
    <x-communication.quick-actions :item="$item" />

</div>
