@extends('layouts.app')

@section('content')
<div
    x-data="{
        showAddAppointment: false,
        showAddPatient: false
    }"
    x-on:open-add-appointment.window="showAddAppointment = true"
    x-on:open-add-patient.window="showAddPatient = true"
    class="p-6 space-y-6"
>

    {{-- Greeting --}}
    <div>
        <p class="text-xs text-gray-400 uppercase tracking-widest font-[DM_Sans]">
            {{ now()->format('l, d F Y') }}
        </p>
        <h1 class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">
            Good {{ now()->hour < 12 ? 'Morning' : (now()->hour < 17 ? 'Afternoon' : 'Evening') }},
            {{ auth()->user()->name }}
        </h1>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

        {{-- Today's Appointments --}}
        <a href="{{ route('appointments.index') }}?date={{ today()->toDateString() }}"
           class="bg-white border border-[#e8d5f0] p-5 hover:border-[#6a0f70] transition group">
            <div class="flex items-start justify-between mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#6a0f70]" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                </svg>
            </div>
            <p class="text-4xl font-semibold text-[#380740] font-[Cormorant_Garamond]">
                {{ $stats['today_total'] }}
            </p>
            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1 font-[DM_Sans]">Today's Appointments</p>
        </a>

        {{-- In Waiting --}}
        <div class="bg-white border border-[#e8d5f0] p-5">
            <div class="flex items-start justify-between mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-yellow-500" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25V9m-3 0h12.75M6.75 9v10.5A2.25 2.25 0 009 21.75h6a2.25 2.25 0 002.25-2.25V9"/>
                </svg>
            </div>
            <p class="text-4xl font-semibold text-[#380740] font-[Cormorant_Garamond]">
                {{ $stats['today_checkin'] }}
            </p>
            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1 font-[DM_Sans]">In Waiting</p>
        </div>

        {{-- In Chair --}}
        <div class="bg-white border border-[#e8d5f0] p-5">
            <div class="flex items-start justify-between mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-orange-500" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M15.182 15.182a4.5 4.5 0 01-6.364 0M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="text-4xl font-semibold text-[#380740] font-[Cormorant_Garamond]">
                {{ $stats['today_in_chair'] }}
            </p>
            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1 font-[DM_Sans]">In Chair</p>
        </div>

        {{-- Completed --}}
        <div class="bg-white border border-[#e8d5f0] p-5">
            <div class="flex items-start justify-between mb-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-green-500" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <p class="text-4xl font-semibold text-[#380740] font-[Cormorant_Garamond]">
                {{ $stats['today_done'] }}
            </p>
            <p class="text-xs text-gray-500 uppercase tracking-widest mt-1 font-[DM_Sans]">Completed</p>
        </div>

    </div>

    {{-- Today's Schedule --}}
    <div class="bg-white border border-[#e8d5f0]">
        <div class="flex items-center justify-between px-5 py-4 border-b border-[#e8d5f0]">
            <h2 class="text-sm font-semibold uppercase tracking-widest text-[#6a0f70] font-[DM_Sans]">
                Today's Schedule
            </h2>
            <a href="{{ route('appointments.index') }}"
               class="text-xs text-[#6a0f70] hover:underline font-[DM_Sans]">
                Full Calendar →
            </a>
        </div>

        @if($todayAppointments->isEmpty())
        <div class="px-5 py-10 text-center">
            <p class="text-sm text-gray-400 font-[DM_Sans]">No appointments scheduled for today.</p>
            <button
                type="button"
                x-on:click="$dispatch('open-add-appointment')"
                class="inline-block mt-3 px-4 py-2 bg-[#6a0f70] text-white text-xs font-semibold uppercase tracking-widest font-[DM_Sans] hover:bg-[#380740] transition">
                Book Appointment
            </button>
        </div>
        @else
        <div class="divide-y divide-[#f0e4f5]">
            @foreach($todayAppointments as $appt)
            <a href="{{ $appt->patient ? route('patients.show', $appt->patient) : route('appointments.show', $appt) }}"
               class="flex items-center gap-4 px-5 py-3 hover:bg-[#faf5fc] transition group">

                {{-- Time --}}
                <div class="w-16 shrink-0 text-right">
                    <p class="text-sm font-semibold text-[#380740] font-[DM_Sans]">
                        {{ \Carbon\Carbon::parse($appt->appointment_time)->format('h:i') }}
                    </p>
                    <p class="text-xs text-gray-400 font-[DM_Sans]">
                        {{ \Carbon\Carbon::parse($appt->appointment_time)->format('A') }}
                    </p>
                </div>

                {{-- Status dot --}}
                <div class="w-2 h-2 rounded-full shrink-0
                    {{ match($appt->status) {
                        'done'      => 'bg-green-400',
                        'in_chair'  => 'bg-orange-400',
                        'checkin'   => 'bg-yellow-400',
                        'checkout'  => 'bg-teal-400',
                        'cancelled' => 'bg-red-400',
                        'no_show'   => 'bg-gray-300',
                        default     => 'bg-blue-400',
                    } }}">
                </div>

                {{-- Patient name + treatment --}}
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-[#380740] font-[DM_Sans] truncate">
                        {{ $appt->patient?->name ?? 'Unknown Patient' }}
                    </p>
                    @if($appt->treatment)
                    <p class="text-xs text-gray-400 font-[DM_Sans] truncate">
                        {{ $appt->treatment->name }}
                    </p>
                    @endif
                </div>

                {{-- Status badge --}}
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
                    } }}">
                    {{ str_replace('_', ' ', $appt->status) }}
                </span>

                <svg xmlns="http://www.w3.org/2000/svg"
                     class="w-4 h-4 text-gray-300 group-hover:text-[#6a0f70] transition shrink-0"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Quick Actions --}}
    <div class="flex gap-3 flex-wrap">

        {{-- New Appointment → opens modal --}}
        <button
            type="button"
            x-on:click="$dispatch('open-add-appointment')"
            class="flex items-center gap-2 px-4 py-2 bg-[#6a0f70] text-white text-xs font-semibold uppercase tracking-widest font-[DM_Sans] hover:bg-[#380740] transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            New Appointment
        </button>

        {{-- New Patient → opens modal --}}
        <button
            type="button"
            x-on:click="$dispatch('open-add-patient')"
            class="flex items-center gap-2 px-4 py-2 border border-[#6a0f70] text-[#6a0f70] text-xs font-semibold uppercase tracking-widest font-[DM_Sans] hover:bg-[#f5eef9] transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.766z"/>
            </svg>
            New Patient
        </button>

        {{-- All Patients → link is fine --}}
        <a href="{{ route('patients.index') }}"
           class="flex items-center gap-2 px-4 py-2 border border-gray-300 text-gray-600 text-xs font-semibold uppercase tracking-widest font-[DM_Sans] hover:bg-gray-50 transition">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
            </svg>
            All Patients
        </a>

    </div>

    {{-- Modals --}}
    @include('partials.add-appointment-modal')
    @include('partials.add-patient-modal')

</div>{{-- /x-data wrapper --}}
@endsection