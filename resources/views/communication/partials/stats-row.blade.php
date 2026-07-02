{{--
    Stats Row — persistent across all Communication OS tabs
    Data passed from CommunicationController or ManagerController
--}}

<div class="os-stats">
    <div class="os-stat">
        <div class="stat-val amber">{{ $stats['pending'] ?? 6 }}</div>
        <div class="stat-lbl">Pending</div>
    </div>
    <div class="os-stat">
        <div class="stat-val red">{{ $stats['overdue'] ?? 3 }}</div>
        <div class="stat-lbl">Overdue</div>
    </div>
    <div class="os-stat">
        <div class="stat-val blue">{{ $stats['callbacks_today'] ?? 2 }}</div>
        <div class="stat-lbl">Callbacks Today</div>
    </div>
    <div class="os-stat">
        <div class="stat-val teal">{{ $stats['completed_today'] ?? 1 }}</div>
        <div class="stat-lbl">Completed Today</div>
    </div>
</div>
