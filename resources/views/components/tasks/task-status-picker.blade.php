{{-- Task Status Picker Component --}}
{{-- Inline status changer for task cards --}}
<div class="task-status-picker" data-task-id="{{ $taskId ?? '' }}">
    @php
    $statuses = [
        ['value' => 'pending', 'label' => 'Pending', 'color' => '#DD6B20', 'bg' => '#FFFAF0'],
        ['value' => 'in_progress', 'label' => 'In Progress', 'color' => '#5B4FBE', 'bg' => '#EEF0FF'],
        ['value' => 'completed', 'label' => 'Completed', 'color' => '#38A169', 'bg' => '#F0FFF4'],
        ['value' => 'cancelled', 'label' => 'Cancelled', 'color' => '#718096', 'bg' => '#F7FAFC'],
        ['value' => 'escalated', 'label' => 'Escalated', 'color' => '#E53E3E', 'bg' => '#FFF5F5'],
    ];
    $current = $currentStatus ?? 'pending';
    @endphp

    <div class="status-picker-trigger" onclick="toggleStatusPicker(this)">
        @php $s = collect($statuses)->firstWhere('value', $current); @endphp
        <span class="status-dot-sm" style="background: {{ $s['color'] ?? '#718096' }}"></span>
        <span>{{ $s['label'] ?? ucfirst($current) }}</span>
        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
    </div>

    <div class="status-picker-dropdown" style="display:none">
        @foreach($statuses as $status)
        <div class="status-picker-option" onclick="selectStatus('{{ $status['value'] }}', '{{ $status['label'] }}', '{{ $status['color'] }}', this)"
             style="background: {{ $status['bg'] }}; color: {{ $status['color'] }}">
            <span class="status-dot-sm" style="background: {{ $status['color'] }}"></span>
            {{ $status['label'] }}
        </div>
        @endforeach
    </div>
</div>
