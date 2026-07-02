{{--
    HR Module Sub-Navigation
    Usage: @include('hr.partials.subnav', ['active' => 'dashboard'])
    Active options: dashboard | doctors | staff | attendance | training | calendar | memos | roles
--}}
@php
    $active = $active ?? 'dashboard';
    $tabs = [
        ['key' => 'dashboard',  'label' => 'Overview',            'href' => route('hr.dashboard'),                                   'icon' => '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>'],
        ['key' => 'doctors',    'label' => 'Doctors',             'href' => route('hr.staff.index') . '?view=doctors',               'icon' => '<path d="M12 22C12 22 5 17 5 11C5 7 7.5 4 12 4C16.5 4 19 7 19 11C19 17 12 22 12 22Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="10" y1="11" x2="14" y2="11"/>'],
        ['key' => 'staff',      'label' => 'Staff',               'href' => route('hr.staff.index') . '?view=staff',                 'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>'],
        ['key' => 'attendance', 'label' => 'Attendance',          'href' => route('hr.attendance.index'),                            'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
        ['key' => 'training',   'label' => 'Training',            'href' => route('hr.training.index'),                              'icon' => '<path d="M12 14l9-5-9-5-9 5 9 5z"/><path d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>'],
        ['key' => 'calendar',   'label' => 'Calendar',            'href' => route('hr.calendar.index'),                              'icon' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><circle cx="8" cy="15" r="1" fill="currentColor"/><circle cx="12" cy="15" r="1" fill="currentColor"/>'],
        ['key' => 'memos',      'label' => 'Memos',               'href' => route('hr.memos.index'),                                 'icon' => '<path d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>'],
        ['key' => 'roles',      'label' => 'Roles & Permissions', 'href' => route('hr.roles.index'),                                 'icon' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>'],
    ];
@endphp

<div style="display:flex; align-items:center; gap:0; border-bottom:2px solid #ede4f3; margin-bottom:28px; overflow-x:auto;">
    @foreach($tabs as $tab)
    @php $isActive = $active === $tab['key']; @endphp
    <a href="{{ $tab['href'] }}"
       style="display:inline-flex; align-items:center; gap:7px; padding:10px 18px; font-size:13px; font-weight:{{ $isActive ? '600' : '500' }}; color:{{ $isActive ? '#6a0f70' : '#7a6080' }}; border-bottom:2px solid {{ $isActive ? '#6a0f70' : 'transparent' }}; margin-bottom:-2px; white-space:nowrap; text-decoration:none; transition:color .15s;">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            {!! $tab['icon'] !!}
        </svg>
        {{ $tab['label'] }}
    </a>
    @endforeach
</div>
