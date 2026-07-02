@extends('layouts.app')
@section('page-title', 'Staff Directory')

@section('content')
<div class="p-6 space-y-5">

    @php $view = request('view', 'doctors'); @endphp
    @include('hr.partials.subnav', ['active' => $view === 'doctors' ? 'doctors' : 'staff'])

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-display font-semibold text-gray-900">
                {{ $view === 'doctors' ? 'Doctors' : 'Staff' }}
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">
                @if($view === 'doctors')
                    {{ $doctors->count() }} doctor{{ $doctors->count() !== 1 ? 's' : '' }}
                @else
                    {{ $staff->count() }} staff member{{ $staff->count() !== 1 ? 's' : '' }}
                @endif
            </p>
        </div>
        <a href="{{ route('hr.staff.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-purple-700 rounded-lg text-sm font-medium text-white hover:bg-purple-800 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add Member
        </a>
    </div>

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3">
        <input type="hidden" name="view" value="{{ $view }}">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Search name, email, code…"
               class="border border-gray-200 rounded-lg px-3 py-2 text-sm w-56 focus:outline-none focus:ring-2 focus:ring-purple-300">

        <select name="department_id" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
            <option value="">All Departments</option>
            @foreach($departments as $dept)
            <option value="{{ $dept->id }}" @selected(request('department_id') == $dept->id)>{{ $dept->name }}</option>
            @endforeach
        </select>

        @if($view === 'staff')
        <select name="role" class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
            <option value="">All Roles</option>
            <option value="assistant"  @selected(request('role') == 'assistant')>Assistant</option>
            <option value="front_desk" @selected(request('role') == 'front_desk')>Front Desk</option>
            <option value="accounts"   @selected(request('role') == 'accounts')>Accounts</option>
            <option value="admin"      @selected(request('role') == 'admin')>Admin</option>
        </select>
        @endif

        <button type="submit" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-700 transition">Filter</button>
        @if(request()->hasAny(['search','department_id','role']))
        <a href="{{ route('hr.staff.index') }}?view={{ $view }}" class="px-4 py-2 border border-gray-200 text-sm rounded-lg text-gray-600 hover:bg-gray-50 transition">Clear</a>
        @endif
    </form>

    @php
    // Helper: render a staff row
    function staffRow($member) { return $member; }
    @endphp

    {{-- ══════════════════ DOCTORS SECTION ══════════════════ --}}
    @if($view === 'doctors' && $doctors->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">

        {{-- Section header --}}
        <div style="display:flex;align-items:center;gap:8px;padding:10px 20px;background:#f2fbf5;border-bottom:1px solid #d4edda;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="2.2" stroke-linecap="round">
                <path d="M12 22C12 22 5 17 5 11C5 7 7.5 4 12 4C16.5 4 19 7 19 11C19 17 12 22 12 22Z"/>
                <line x1="12" y1="9" x2="12" y2="13"/><line x1="10" y1="11" x2="14" y2="11"/>
            </svg>
            <span style="font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#1a7a45;">Doctors</span>
            <span style="font-size:11px;color:#6aaa88;margin-left:2px;">· {{ $doctors->count() }}</span>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-3 font-medium text-gray-500">Name</th>
                    <th class="text-left px-5 py-3 font-medium text-gray-500 hidden md:table-cell">Designation</th>
                    <th class="text-left px-5 py-3 font-medium text-gray-500 hidden md:table-cell">Department</th>
                    <th class="text-left px-5 py-3 font-medium text-gray-500 hidden lg:table-cell">Joined</th>
                    <th class="text-left px-5 py-3 font-medium text-gray-500">License</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($doctors as $member)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
                                 style="background:#d4edda;color:#1a7a45;">
                                {{ $member->initials }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">{{ $member->name }}</p>
                                <p class="text-xs text-gray-400">{{ $member->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-gray-600 hidden md:table-cell">
                        {{ $member->designation ?? $member->hrProfile?->specialization ?? '—' }}
                    </td>
                    <td class="px-5 py-3 text-gray-500 hidden md:table-cell">
                        {{ $member->hrProfile?->department?->name ?? '—' }}
                    </td>
                    <td class="px-5 py-3 text-gray-500 hidden lg:table-cell">
                        {{ $member->hrProfile?->joining_date?->format('d M Y') ?? '—' }}
                    </td>
                    <td class="px-5 py-3">
                        @php $licStatus = $member->hrProfile?->license_status ?? 'none'; @endphp
                        @if($licStatus === 'expired')
                            <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700 font-medium">Expired</span>
                        @elseif($licStatus === 'expiring_soon')
                            <span class="text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-medium">{{ $member->hrProfile->license_days_remaining }}d left</span>
                        @elseif($licStatus === 'ok')
                            <span class="text-xs text-green-600">✓ Valid</span>
                        @else
                            <span class="text-xs text-gray-300">—</span>
                        @endif
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('hr.staff.show', $member) }}"
                           class="text-xs text-purple-600 hover:underline font-medium">View</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- ══════════════════ STAFF SECTION ══════════════════ --}}
    @if($view === 'staff' && $staff->isNotEmpty())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">

        {{-- Section header --}}
        <div style="display:flex;align-items:center;gap:8px;padding:10px 20px;background:#f0f6ff;border-bottom:1px solid #c9ddf5;">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#1a5ea8" stroke-width="2.2" stroke-linecap="round">
                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <span style="font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#1a5ea8;">Staff</span>
            <span style="font-size:11px;color:#6a8ec8;margin-left:2px;">· {{ $staff->count() }}</span>
        </div>

        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-3 font-medium text-gray-500">Name</th>
                    <th class="text-left px-5 py-3 font-medium text-gray-500 hidden md:table-cell">Department</th>
                    <th class="text-left px-5 py-3 font-medium text-gray-500 hidden md:table-cell">Role</th>
                    <th class="text-left px-5 py-3 font-medium text-gray-500 hidden lg:table-cell">Shift</th>
                    <th class="text-left px-5 py-3 font-medium text-gray-500 hidden lg:table-cell">Joined</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($staff as $member)
                <tr class="hover:bg-gray-50 transition">
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0"
                                 style="background:#e8f0fc;color:#1a5ea8;">
                                {{ $member->initials }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">{{ $member->name }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ $member->hrProfile?->employee_code ?? $member->designation ?? $member->email }}
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-gray-500 hidden md:table-cell">
                        {{ $member->hrProfile?->department?->name ?? '—' }}
                    </td>
                    <td class="px-5 py-3 hidden md:table-cell">
                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-700 font-medium">
                            {{ $member->role_label }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-gray-500 hidden lg:table-cell">
                        {{ $member->currentShift?->shift?->name ?? '—' }}
                    </td>
                    <td class="px-5 py-3 text-gray-500 hidden lg:table-cell">
                        {{ $member->hrProfile?->joining_date?->format('d M Y') ?? '—' }}
                    </td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('hr.staff.show', $member) }}"
                           class="text-xs text-purple-600 hover:underline font-medium">View</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Empty state --}}
    @if(($view === 'doctors' && $doctors->isEmpty()) || ($view === 'staff' && $staff->isEmpty()))
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-10 text-center text-sm text-gray-400">
        No team members found.
        <a href="{{ route('hr.staff.create') }}" class="text-purple-600 hover:underline ml-1">Add first member →</a>
    </div>
    @endif

    {{-- Pagination (filter mode) --}}
    @if($staffPaginated && $staffPaginated->hasPages())
    <div class="px-1 py-2">{{ $staffPaginated->links() }}</div>
    @endif

</div>
@endsection
