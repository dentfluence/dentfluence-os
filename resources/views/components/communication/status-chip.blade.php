{{--
  Status Chip Component
  Usage: <x-communication.status-chip :status="$item['status']" />
--}}
@props(['status' => 'pending'])

@php
$labels = [
    'pending'     => 'Pending',
    'in_progress' => 'In Progress',
    'completed'   => 'Completed',
    'cancelled'   => 'Cancelled',
    'escalated'   => 'Escalated',
];
$label = $labels[$status] ?? ucfirst($status);
@endphp

<span class="cm-status {{ $status }}">{{ $label }}</span>
