@extends('layouts.app')
@section('page-title', 'Attendance — ' . $date->format('d M Y'))

@section('content')
<div class="p-6 space-y-5" x-data="attendanceBoard()">

    @include('hr.partials.subnav', ['active' => 'attendance'])

    {{-- Header --}}
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-display font-semibold text-gray-900">Attendance</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $date->format('l, d M Y') }}</p>
        </div>
        <div class="flex flex-wrap gap-2 items-center">

            {{-- Date picker --}}
            <form method="GET" class="flex gap-2">
                @if(request('department_id'))
                <input type="hidden" name="department_id" value="{{ request('department_id') }}">
                @endif
                <input type="date" name="date" value="{{ $date->format('Y-m-d') }}"
                       onchange="this.form.submit()"
                       class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
            </form>

            {{-- Department filter --}}
            <form method="GET" class="flex gap-2">
                <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                <select name="department_id" onchange="this.form.submit()"
                        class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-purple-300">
                    <option value="">All Departments</option>
                    @foreach($departments as $dept)
                    <option value="{{ $dept->id }}" @selected(request('department_id') == $dept->id)>{{ $dept->name }}</option>
                    @endforeach
                </select>
            </form>

            {{-- Mark all present --}}
            <button @click="markAllPresent()"
                    class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                ✓ Mark All Present
            </button>

        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    {{-- Summary Row --}}
    <div class="grid grid-cols-4 gap-3">
        <div class="bg-green-50 border border-green-100 rounded-xl p-4 text-center">
            <p class="text-2xl font-bold text-green-600">{{ $presentCount }}</p>
            <p class="text-xs text-green-700 mt-0.5">Present</p>
        </div>
        <div class="bg-red-50 border border-red-100 rounded-xl p-4 text-center">
            <p class="text-2xl font-bold text-red-500">{{ $absentCount }}</p>
            <p class="text-xs text-red-700 mt-0.5">Absent</p>
        </div>
        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 text-center">
            <p class="text-2xl font-bold text-blue-500">{{ $onLeaveCount }}</p>
            <p class="text-xs text-blue-700 mt-0.5">On Leave</p>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-xl p-4 text-center">
            <p class="text-2xl font-bold text-gray-400">{{ $notMarkedCount }}</p>
            <p class="text-xs text-gray-500 mt-0.5">Not Marked</p>
        </div>
    </div>

    {{-- Attendance Table --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 bg-gray-50">
                    <th class="text-left px-5 py-3 font-medium text-gray-500 w-8">
                        <input type="checkbox" id="selectAll" @change="toggleAll($event)"
                               class="rounded border-gray-300 text-purple-600">
                    </th>
                    <th class="text-left px-5 py-3 font-medium text-gray-500">Staff</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500 hidden md:table-cell">Shift</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500">Status</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500 hidden lg:table-cell">Check In</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500 hidden lg:table-cell">Check Out</th>
                    <th class="text-left px-4 py-3 font-medium text-gray-500 hidden lg:table-cell">Method</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($staffWithAttendance as $member)
                @php $rec = $member->todayAttendance; @endphp
                <tr class="hover:bg-gray-50 transition" x-data="{ open: false }">

                    {{-- Checkbox --}}
                    <td class="px-5 py-3">
                        <input type="checkbox" value="{{ $member->id }}"
                               x-model="selected"
                               class="rounded border-gray-300 text-purple-600">
                    </td>

                    {{-- Staff info --}}
                    <td class="px-5 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-purple-700 bg-purple-100 flex-shrink-0"
                                 style="{{ $member->color ? 'background:' . $member->color . '22;color:' . $member->color : '' }}">
                                {{ $member->initials }}
                            </div>
                            <div>
                                <p class="font-medium text-gray-900">{{ $member->name }}</p>
                                <p class="text-xs text-gray-400">{{ $member->designation ?? $member->role_label }}</p>
                            </div>
                        </div>
                    </td>

                    {{-- Shift --}}
                    <td class="px-4 py-3 text-gray-500 hidden md:table-cell text-xs">
                        @if($member->currentShift?->shift)
                            {{ $member->currentShift->shift->name }}
                            <span class="text-gray-300">({{ $member->currentShift->shift->timing }})</span>
                        @else
                            —
                        @endif
                    </td>

                    {{-- Status badge --}}
                    <td class="px-4 py-3">
                        @if($rec)
                            <span class="text-xs px-2 py-1 rounded-full font-medium {{ $rec->status_badge_class }}">
                                {{ $rec->status_label }}
                            </span>
                        @else
                            <span class="text-xs px-2 py-1 rounded-full font-medium bg-gray-100 text-gray-400">
                                Not marked
                            </span>
                        @endif
                    </td>

                    {{-- Check in --}}
                    <td class="px-4 py-3 text-gray-600 hidden lg:table-cell">
                        @if($rec?->check_in)
                            {{ \Carbon\Carbon::parse($rec->check_in)->format('h:i A') }}
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>

                    {{-- Check out --}}
                    <td class="px-4 py-3 text-gray-600 hidden lg:table-cell">
                        @if($rec?->check_out)
                            {{ \Carbon\Carbon::parse($rec->check_out)->format('h:i A') }}
                            @if($rec->hours_worked)
                            <span class="text-xs text-gray-400 ml-1">({{ $rec->hours_worked }})</span>
                            @endif
                        @else
                            <span class="text-gray-300">—</span>
                        @endif
                    </td>

                    {{-- Method --}}
                    <td class="px-4 py-3 hidden lg:table-cell">
                        @if($rec?->check_in_method === 'qr')
                            <span class="text-xs text-purple-500">QR</span>
                        @elseif($rec?->check_in_method === 'manual')
                            <span class="text-xs text-gray-400">Manual</span>
                        @endif
                    </td>

                    {{-- Quick actions --}}
                    <td class="px-4 py-3">
                        <div class="flex items-center gap-1 justify-end">

                            {{-- Quick present --}}
                            @unless($rec && in_array($rec->status, ['present','late']))
                            <form method="POST" action="{{ route('hr.attendance.mark') }}">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $member->id }}">
                                <input type="hidden" name="date"    value="{{ $date->format('Y-m-d') }}">
                                <input type="hidden" name="status"  value="present">
                                <input type="hidden" name="check_in" value="{{ now()->format('H:i') }}">
                                <button type="submit"
                                        title="Mark Present"
                                        class="w-7 h-7 rounded-full bg-green-100 text-green-700 hover:bg-green-200 transition flex items-center justify-center text-xs font-bold">
                                    P
                                </button>
                            </form>
                            @endunless

                            {{-- Quick absent --}}
                            @unless($rec && $rec->status === 'absent')
                            <form method="POST" action="{{ route('hr.attendance.mark') }}">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $member->id }}">
                                <input type="hidden" name="date"    value="{{ $date->format('Y-m-d') }}">
                                <input type="hidden" name="status"  value="absent">
                                <button type="submit"
                                        title="Mark Absent"
                                        class="w-7 h-7 rounded-full bg-red-100 text-red-600 hover:bg-red-200 transition flex items-center justify-center text-xs font-bold">
                                    A
                                </button>
                            </form>
                            @endunless

                            {{-- Expand for full edit --}}
                            <button @click="open = !open"
                                    title="More options"
                                    class="w-7 h-7 rounded-full bg-gray-100 text-gray-500 hover:bg-gray-200 transition flex items-center justify-center text-xs">
                                ···
                            </button>
                        </div>

                        {{-- Expanded edit form --}}
                        <div x-show="open" x-collapse class="mt-3 pt-3 border-t border-gray-100">
                            <form method="POST" action="{{ route('hr.attendance.mark') }}" class="space-y-2">
                                @csrf
                                <input type="hidden" name="user_id" value="{{ $member->id }}">
                                <input type="hidden" name="date"    value="{{ $date->format('Y-m-d') }}">

                                <div class="flex flex-wrap gap-2">
                                    <select name="status"
                                            class="border border-gray-200 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-purple-300">
                                        @foreach(['present'=>'Present','absent'=>'Absent','late'=>'Late','half_day'=>'Half Day','on_leave'=>'On Leave','holiday'=>'Holiday'] as $v => $l)
                                        <option value="{{ $v }}" @selected($rec?->status === $v)>{{ $l }}</option>
                                        @endforeach
                                    </select>

                                    <input type="time" name="check_in"
                                           value="{{ $rec?->check_in ? \Carbon\Carbon::parse($rec->check_in)->format('H:i') : '' }}"
                                           placeholder="Check in"
                                           class="border border-gray-200 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-purple-300">

                                    <input type="time" name="check_out"
                                           value="{{ $rec?->check_out ? \Carbon\Carbon::parse($rec->check_out)->format('H:i') : '' }}"
                                           placeholder="Check out"
                                           class="border border-gray-200 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-purple-300">

                                    <input type="text" name="notes"
                                           value="{{ $rec?->notes }}"
                                           placeholder="Note (optional)"
                                           class="border border-gray-200 rounded px-2 py-1 text-xs focus:outline-none focus:ring-1 focus:ring-purple-300 w-32">

                                    <button type="submit"
                                            class="px-3 py-1 bg-purple-700 text-white text-xs rounded hover:bg-purple-800 transition">
                                        Save
                                    </button>
                                </div>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="8" class="px-5 py-10 text-center text-sm text-gray-400">
                        No staff with HR profiles found.
                        <a href="{{ route('hr.staff.create') }}" class="text-purple-600 hover:underline ml-1">Add staff →</a>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Bulk action bar (shows when checkboxes selected) --}}
    <div x-show="selected.length > 0"
         x-transition
         class="fixed bottom-6 left-1/2 -translate-x-1/2 bg-gray-900 text-white rounded-2xl shadow-2xl px-6 py-3 flex items-center gap-4 z-50">
        <span class="text-sm font-medium" x-text="selected.length + ' selected'"></span>

        <form method="POST" action="{{ route('hr.attendance.mark-bulk') }}" @submit="appendSelected($event)">
            @csrf
            <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
            <div id="bulk-user-inputs"></div>
            <div class="flex gap-2">
                <button type="submit" name="status" value="present"
                        class="px-3 py-1.5 bg-green-500 text-white text-xs rounded-lg hover:bg-green-600 transition font-medium">
                    Mark Present
                </button>
                <button type="submit" name="status" value="absent"
                        class="px-3 py-1.5 bg-red-500 text-white text-xs rounded-lg hover:bg-red-600 transition font-medium">
                    Mark Absent
                </button>
                <button type="submit" name="status" value="holiday"
                        class="px-3 py-1.5 bg-gray-500 text-white text-xs rounded-lg hover:bg-gray-600 transition font-medium">
                    Holiday
                </button>
            </div>
        </form>

        <button @click="selected = []" class="text-gray-400 hover:text-white text-xs">✕ Clear</button>
    </div>

</div>

<script>
function attendanceBoard() {
    return {
        selected: [],

        toggleAll(e) {
            const checkboxes = document.querySelectorAll('input[type=checkbox][value]');
            this.selected = e.target.checked
                ? Array.from(checkboxes).map(c => c.value)
                : [];
        },

        appendSelected(e) {
            const form = e.target;
            const container = form.querySelector('#bulk-user-inputs');
            container.innerHTML = '';
            this.selected.forEach(id => {
                const input = document.createElement('input');
                input.type  = 'hidden';
                input.name  = 'user_ids[]';
                input.value = id;
                container.appendChild(input);
            });
        },

        markAllPresent() {
            if (!confirm('Mark ALL staff as Present for today?')) return;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('hr.attendance.mark-bulk') }}';
            form.innerHTML = `
                <input type="hidden" name="_token" value="{{ csrf_token() }}">
                <input type="hidden" name="date" value="{{ $date->format('Y-m-d') }}">
                <input type="hidden" name="status" value="present">
                @foreach($staffWithAttendance as $member)
                <input type="hidden" name="user_ids[]" value="{{ $member->id }}">
                @endforeach
            `;
            document.body.appendChild(form);
            form.submit();
        },
    }
}
</script>
@endsection
