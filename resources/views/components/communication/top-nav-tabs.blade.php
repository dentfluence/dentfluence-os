{{--
    Component: top-nav-tabs
    Usage: <x-communication.top-nav-tabs :counts="$navCounts" active="pipeline" />
    Active options: overdue | today | long_term | ongoing | yesterday | special | call_manager | leads | pipeline | activity_log | followup_calendar | tasks
--}}

@props([
    'counts' => [],
    'active' => 'pipeline',
])

@php
$tabs = [
    ['key' => 'overdue',            'label' => 'Overdue',          'icon' => 'alert-triangle',    'route' => '/communication/manager',              'count_key' => 'overdue',   'badge_class' => 'tb-red'],
    ['key' => 'today',              'label' => 'Today',            'icon' => 'calendar',           'route' => '/communication/manager/today',         'count_key' => 'today',     'badge_class' => 'tb-blue'],
    ['key' => 'long_term',          'label' => 'Long Term (6M+)',  'icon' => 'calendar-stats',     'route' => '/communication/manager/long-term',     'count_key' => 'long_term', 'badge_class' => 'tb-green'],
    ['key' => 'ongoing',            'label' => 'Ongoing Treatment','icon' => 'heart',              'route' => '/communication/manager/ongoing',       'count_key' => 'ongoing',   'badge_class' => 'tb-blue'],
    ['key' => 'yesterday',          'label' => 'Yesterday',        'icon' => 'clock',              'route' => '/communication/manager/yesterday',     'count_key' => 'yesterday', 'badge_class' => 'tb-gray'],
    ['key' => 'special',            'label' => 'Special Days',     'icon' => 'gift',               'route' => '/communication/manager/special-days',  'count_key' => 'special',   'badge_class' => 'tb-amber'],
    ['key' => 'whatsapp',           'label' => 'WhatsApp',         'icon' => 'brand-whatsapp',     'route' => '/communication/whatsapp',              'count_key' => null,        'badge_class' => ''],
    ['key' => 'reviews',            'label' => 'Reviews',          'icon' => 'star',               'route' => '/communication/reviews',               'count_key' => null,        'badge_class' => ''],
    ['key' => 'call_manager',       'label' => 'Call Manager',     'icon' => 'phone',              'route' => '/communication/call-manager',          'count_key' => null,        'badge_class' => ''],
    // 'leads'/'pipeline' repointed to PRE — Phase 8 PRM Retirement (Slice 5).
    ['key' => 'leads',              'label' => 'Leads',            'icon' => 'users',              'route' => '/relationship/pipeline',               'count_key' => null,        'badge_class' => ''],
    ['key' => 'pipeline',           'label' => 'Pipeline',         'icon' => 'layout-kanban',      'route' => '/relationship/pipeline',               'count_key' => null,        'badge_class' => ''],
    ['key' => 'activity_log',       'label' => 'Activity Log',     'icon' => 'clipboard-list',     'route' => '/communication/activity-log',          'count_key' => null,        'badge_class' => ''],
    ['key' => 'followup_calendar',  'label' => 'Follow-up Calendar','icon' => 'calendar-event',   'route' => '/communication/followup-calendar',     'count_key' => null,        'badge_class' => ''],
    ['key' => 'tasks',              'label' => 'Tasks',            'icon' => 'checkbox',           'route' => '/communication/tasks',                 'count_key' => null,        'badge_class' => ''],
];
@endphp

<div class="prm-top-nav">
    @foreach($tabs as $tab)
        @php
            $isActive = $active === $tab['key'];
            $count    = $tab['count_key'] ? ($counts[$tab['count_key']] ?? null) : null;
        @endphp
        <a href="{{ $tab['route'] }}"
           class="prm-nav-tab {{ $isActive ? 'active' : '' }}"
           title="{{ $tab['label'] }}">
            <i class="ti ti-{{ $tab['icon'] }}" aria-hidden="true"></i>
            <span class="tab-label">{{ $tab['label'] }}</span>
            @if($count)
                <span class="tab-badge {{ $tab['badge_class'] }}">{{ $count }}</span>
            @endif
        </a>
    @endforeach
</div>
