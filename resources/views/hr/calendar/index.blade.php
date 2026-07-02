@extends('layouts.app')
@section('page-title', 'Staff Calendar')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
@endpush

@section('content')
<div class="p-6 space-y-6">

    @include('hr.partials.subnav', ['active' => 'calendar'])

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-display font-semibold text-gray-900">Staff Calendar</h1>
            <p class="text-sm text-gray-500 mt-0.5">Training sessions, leave, and attendance at a glance</p>
        </div>
        <select id="staffFilter"
            class="border border-gray-200 rounded-lg text-sm px-3 py-2 focus:ring-2 focus:ring-purple-300 outline-none">
            <option value="">All Staff</option>
            @foreach($staff as $s)
            <option value="{{ $s->id }}">{{ $s->name }}</option>
            @endforeach
        </select>
    </div>

    {{-- Legend --}}
    <div class="flex gap-4 text-xs text-gray-500">
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-purple-600 inline-block"></span> Training (Scheduled)</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-green-600 inline-block"></span> Training (Completed)</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-blue-500 inline-block"></span> Leave</span>
        <span class="flex items-center gap-1.5"><span class="w-3 h-3 rounded-full bg-red-500 inline-block"></span> Absent</span>
    </div>

    {{-- Calendar --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div id="calendar"></div>
    </div>

</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const calendarEl  = document.getElementById('calendar');
    const staffFilter = document.getElementById('staffFilter');

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,listWeek',
        },
        height: 'auto',
        eventSources: [{
            url: '{{ route('hr.calendar.events') }}',
            extraParams: function () {
                return { staff_id: staffFilter.value };
            },
        }],
        eventClick: function (info) {
            if (info.event.url) {
                info.jsEvent.preventDefault();
                window.location.href = info.event.url;
            }
        },
        eventDidMount: function (info) {
            const props = info.event.extendedProps;
            if (props.venue) info.el.title = '' + props.venue;
        },
    });

    calendar.render();

    staffFilter.addEventListener('change', function () {
        calendar.refetchEvents();
    });
});
</script>
@endpush
