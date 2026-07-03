@extends('layouts.app')

@section('content')
<div
    x-data="{
        showAddAppointment: false,
        showAddPatient: false
    }"
    x-on:open-add-appointment.window="openAppointmentModal()"
    x-on:open-add-patient.window="showAddPatient = true"
    class="p-6 space-y-5"
>

    {{-- ── Greeting ────────────────────────────────────────────── --}}
    <div class="flex items-end justify-between">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest font-[DM_Sans]">
                {{ now()->format('l, d F Y') }}
            </p>
            <h1 class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">
                Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }},
                {{ auth()->user()->name }}
            </h1>
        </div>
        @if(Route::has('huddle.index'))
        <a href="{{ route('huddle.index') }}"
           class="flex items-center gap-2 px-4 py-2 border border-[#6a0f70] text-[#6a0f70] text-xs font-semibold uppercase tracking-widest font-[DM_Sans] hover:bg-[#f5eef9] transition">
            Daily Huddle
        </a>
        @endif
    </div>

    {{-- ── Alert Strip ─────────────────────────────────────────── --}}
    @if(count($alerts) > 0)
    <div class="space-y-2">
        @foreach($alerts as $alert)
        <a href="{{ $alert['link'] }}"
           class="flex items-center gap-3 px-4 py-3 border text-sm font-[DM_Sans] transition
               {{ $alert['type'] === 'warning'
                   ? 'bg-amber-50 border-amber-200 text-amber-800 hover:bg-amber-100'
                   : 'bg-blue-50 border-blue-200 text-blue-800 hover:bg-blue-100' }}">
            <span>{{ $alert['message'] }}</span>
        </a>
        @endforeach
    </div>
    @endif

    {{-- ── 4 KPI Cards ─────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

        {{-- Today's Appointments --}}
        <a href="{{ route('appointments.index') }}?date={{ today()->toDateString() }}"
           class="bg-white border border-[#e8d5f0] p-5 hover:border-[#6a0f70] transition group">
            <p class="text-4xl font-semibold text-[#380740] font-[Cormorant_Garamond]">{{ $stats['today_total'] }}</p>
            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1 font-[DM_Sans]">Today's Appointments</p>
            <div class="flex gap-3 mt-3 text-xs font-[DM_Sans]">
                <span class="text-yellow-600">{{ $stats['today_checkin'] }} waiting</span>
                <span class="text-orange-600">{{ $stats['today_in_chair'] }} in chair</span>
                <span class="text-green-600">{{ $stats['today_done'] }} done</span>
            </div>
        </a>

        {{-- Collected Today --}}
        <a href="{{ route('finance.income') }}?date={{ today()->toDateString() }}"
           class="bg-white border border-[#e8d5f0] p-5 hover:border-[#6a0f70] transition group">
            <p class="text-4xl font-semibold text-[#380740] font-[Cormorant_Garamond]">Rs. {{ number_format($todayRevenue, 0) }}</p>
            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1 font-[DM_Sans]">Collected Today</p>
            <div class="mt-3 text-xs text-gray-400 font-[DM_Sans]">payments received</div>
        </a>

        {{-- Outstanding --}}
        <a href="{{ route('finance.income') }}?status=unpaid"
           class="bg-white border border-[#e8d5f0] p-5 hover:border-[#6a0f70] transition group {{ $outstandingBalance > 0 ? 'border-l-4 border-l-amber-400' : '' }}">
            <p class="text-4xl font-semibold text-[#380740] font-[Cormorant_Garamond]">Rs. {{ number_format($outstandingBalance, 0) }}</p>
            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1 font-[DM_Sans]">Outstanding</p>
            <div class="mt-3 text-xs font-[DM_Sans] {{ $outstandingCount > 0 ? 'text-amber-600' : 'text-gray-400' }}">
                {{ $outstandingCount }} unpaid invoice{{ $outstandingCount !== 1 ? 's' : '' }}
            </div>
        </a>

        {{-- Pending Lab Cases --}}
        <a href="{{ route('lab.index') }}"
           class="bg-white border border-[#e8d5f0] p-5 hover:border-[#6a0f70] transition group {{ $overdueLabCount > 0 ? 'border-l-4 border-l-red-400' : '' }}">
            <p class="text-4xl font-semibold text-[#380740] font-[Cormorant_Garamond]">{{ $pendingLabCount }}</p>
            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1 font-[DM_Sans]">Lab Cases Pending</p>
            <div class="mt-3 text-xs font-[DM_Sans] {{ $overdueLabCount > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                @if($overdueLabCount > 0) {{ $overdueLabCount }} overdue @else all on track @endif
            </div>
        </a>

    </div>

    {{-- ── Today's Schedule ─────────────────────────────────────── --}}
    {{-- Quick Actions / Go To panels removed — they only duplicated the
         sidebar's own nav links (Patients, Finance, Lab, Inventory, etc.)
         and the "+" button already in the top bar. --}}
    <div class="bg-white border border-[#e8d5f0]">
        <div class="flex items-center justify-between px-5 py-4 border-b border-[#e8d5f0]">
            <h2 class="text-sm font-semibold uppercase tracking-widest text-[#6a0f70] font-[DM_Sans]">Today's Schedule</h2>
            <a href="{{ route('appointments.index') }}" class="text-xs text-[#6a0f70] hover:underline font-[DM_Sans]">Full Calendar →</a>
        </div>

        @if($todayAppointments->isEmpty())
        <div class="px-5 py-10 text-center">
            <p class="text-sm text-gray-400 font-[DM_Sans]">No appointments scheduled for today.</p>
            <button type="button" x-on:click="openAppointmentModal()"
                class="inline-block mt-3 px-4 py-2 bg-[#6a0f70] text-white text-xs font-semibold uppercase tracking-widest font-[DM_Sans] hover:bg-[#380740] transition">
                Book Appointment
            </button>
        </div>
        @else
        <div class="divide-y divide-[#f0e4f5] max-h-[480px] overflow-y-auto">
            @foreach($todayAppointments as $appt)
            <a href="{{ $appt->patient ? route('patients.show', $appt->patient) : route('appointments.show', $appt) }}"
               class="flex items-center gap-4 px-5 py-3 hover:bg-[#faf5fc] transition group">
                <div class="w-16 shrink-0 text-right">
                    <p class="text-sm font-semibold text-[#380740] font-[DM_Sans]">{{ \Carbon\Carbon::parse($appt->appointment_time)->format('h:i') }}</p>
                    <p class="text-xs text-gray-400 font-[DM_Sans]">{{ \Carbon\Carbon::parse($appt->appointment_time)->format('A') }}</p>
                </div>
                <div class="w-2 h-2 rounded-full shrink-0
                    {{ match($appt->status) {
                        'done'      => 'bg-green-400',
                        'in_chair'  => 'bg-orange-400',
                        'checkin'   => 'bg-yellow-400',
                        'checkout'  => 'bg-teal-400',
                        'cancelled' => 'bg-red-400',
                        'no_show'   => 'bg-gray-300',
                        default     => 'bg-blue-400',
                    } }}"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-[#380740] font-[DM_Sans] truncate">{{ $appt->patient?->name ?? 'Unknown Patient' }}</p>
                    <p class="text-xs text-gray-400 font-[DM_Sans] truncate">
                        @if($appt->treatment){{ $appt->treatment->name }}@endif
                        @if($appt->operatory)<span class="text-purple-600 font-medium">· {{ $appt->operatory->name }}</span>@endif
                    </p>
                </div>
                <span class="shrink-0 px-2 py-0.5 text-xs font-semibold uppercase tracking-wider font-[DM_Sans]
                    {{ match($appt->status) {
                        'scheduled' => 'bg-blue-50 text-blue-600',
                        'checkin'   => 'bg-yellow-50 text-yellow-700',
                        'in_chair'  => 'bg-orange-50 text-orange-700',
                        'checkout'  => 'bg-teal-50 text-teal-700',
                        'done'      => 'bg-green-50 text-green-700',
                        'cancelled' => 'bg-red-50 text-red-600',
                        'no_show'   => 'bg-gray-50 text-gray-500',
                        default     => 'bg-gray-50 text-gray-500',
                    } }}">{{ str_replace('_', ' ', $appt->status) }}</span>
            </a>
            @endforeach
        </div>
        @endif
    </div>{{-- /Today's Schedule --}}

    {{-- Modals --}}
    @include('partials.add-patient-modal')

</div>{{-- /x-data --}}
@endsection
