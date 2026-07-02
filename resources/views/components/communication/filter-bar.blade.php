{{--
  Filter Bar Component
  Usage: <x-communication.filter-bar :filters="$filters" />
--}}
@props(['filters' => []])

@php
$sources = [
    ''          => 'All Sources',
    'call'      => 'Calls',
    'whatsapp'  => 'WhatsApp',
    'instagram' => 'Instagram',
    'google'    => 'Google',
    'walkin'    => 'Walk-in',
];

$statuses = [
    ''           => 'All Status',
    'pending'    => 'Pending',
    'in_progress'=> 'In Progress',
    'completed'  => 'Completed',
];

$priorities = [
    ''       => 'All Priority',
    'high'   => 'High',
    'medium' => 'Medium',
    'low'    => 'Low',
];
@endphp

<div class="cm-filter-row">
    {{-- Search --}}
    <input type="text"
           class="cm-search-input"
           placeholder="Search patient, note, tag…"
           value="{{ request('q') }}"
           oninput="filterQueue(this.value)"
           autocomplete="off">

    {{-- Source filter --}}
    <select class="cm-filter-btn" onchange="applyFilter('source', this.value)"
            style="appearance:none; padding-right:24px; cursor:pointer;">
        @foreach($sources as $val => $label)
            <option value="{{ $val }}" {{ ($filters['source'] ?? '') === $val ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>

    {{-- Status filter --}}
    <select class="cm-filter-btn" onchange="applyFilter('status', this.value)"
            style="appearance:none; padding-right:24px; cursor:pointer;">
        @foreach($statuses as $val => $label)
            <option value="{{ $val }}" {{ ($filters['status'] ?? '') === $val ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>

    {{-- Priority filter --}}
    <select class="cm-filter-btn" onchange="applyFilter('priority', this.value)"
            style="appearance:none; padding-right:24px; cursor:pointer;">
        @foreach($priorities as $val => $label)
            <option value="{{ $val }}" {{ ($filters['priority'] ?? '') === $val ? 'selected' : '' }}>
                {{ $label }}
            </option>
        @endforeach
    </select>

    {{-- Overdue toggle --}}
    <button class="cm-filter-btn {{ request('overdue') ? 'active' : '' }}"
            onclick="toggleFilter('overdue')">
        <span class="dot"></span>
        Overdue Only
    </button>

    {{-- Reset --}}
    @if(array_filter($filters))
    <a href="{{ route('communication.manager.index') }}" class="cm-filter-btn">
        ✕ Clear
    </a>
    @endif
</div>
