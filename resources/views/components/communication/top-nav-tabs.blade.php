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
// 2026-07-09: audited every href in this bar against the live route list —
// 5 of 14 were hard 404s/500s. Root cause: the old tabbed Communication
// Manager (Overdue/Today/Long Term/Ongoing/Yesterday/Special Days as
// separate filtered views) was retired 2026-07-06 in favour of PRE's single
// unified "Today's Actions" screen (routes/communication.php's manager.index
// now just redirects to relationship.today) — but this tab array was never
// updated to match, so 5 of its 6 old sub-filter links fell through to the
// /communication/manager/{id} show-route wildcard and 500'd trying to treat
// "today"/"long-term"/etc. as a numeric id. Since there's no longer a
// distinct destination for each filter, collapsing to one "Overdue" tab
// (the pre-existing first entry) instead of shipping 6 tabs that all open
// the identical page. Call Manager / Activity Log / Follow-up Calendar /
// Tasks kept as separate tabs but repointed at their real, currently-working
// routes.
$tabs = [
    ['key' => 'overdue',            'label' => 'Overdue',          'icon' => 'alert-triangle',    'route' => '/communication/manager',              'count_key' => 'overdue',   'badge_class' => 'tb-red'],
    ['key' => 'whatsapp',           'label' => 'WhatsApp',         'icon' => 'brand-whatsapp',     'route' => '/communication/whatsapp',              'count_key' => null,        'badge_class' => ''],
    ['key' => 'reviews',            'label' => 'Reviews',          'icon' => 'star',               'route' => '/communication/reviews',               'count_key' => null,        'badge_class' => ''],
    ['key' => 'call_manager',       'label' => 'Call Manager',     'icon' => 'phone',              'route' => '/communication/manager',               'count_key' => null,        'badge_class' => ''],
    // 'leads'/'pipeline' repointed to PRE — Phase 8 PRM Retirement (Slice 5).
    ['key' => 'leads',              'label' => 'Leads',            'icon' => 'users',              'route' => '/relationship/pipeline',               'count_key' => null,        'badge_class' => ''],
    ['key' => 'pipeline',           'label' => 'Pipeline',         'icon' => 'layout-kanban',      'route' => '/relationship/pipeline',               'count_key' => null,        'badge_class' => ''],
    ['key' => 'activity_log',       'label' => 'Activity Log',     'icon' => 'clipboard-list',     'route' => '/settings/activity-log',               'count_key' => null,        'badge_class' => ''],
    ['key' => 'followup_calendar',  'label' => 'Follow-up Calendar','icon' => 'calendar-event',   'route' => '/communication/followup/calendar',     'count_key' => null,        'badge_class' => ''],
    ['key' => 'tasks',              'label' => 'Tasks',            'icon' => 'checkbox',           'route' => '/tasks',                               'count_key' => null,        'badge_class' => ''],
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
