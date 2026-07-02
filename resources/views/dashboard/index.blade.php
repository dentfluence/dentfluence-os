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

    {{-- ── Two-column: schedule + sidebar ──────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        {{-- Today's Schedule --}}
        <div class="lg:col-span-2 bg-white border border-[#e8d5f0]">
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
        </div>

        {{-- Sidebar: Quick Actions + Go To --}}
        <div class="space-y-4">

            {{-- Quick Actions --}}
            <div class="bg-white border border-[#e8d5f0]">
                <div class="px-5 py-4 border-b border-[#e8d5f0]">
                    <h2 class="text-sm font-semibold uppercase tracking-widest text-[#6a0f70] font-[DM_Sans]">Quick Actions</h2>
                </div>
                <div class="p-4 space-y-2">
                    <button type="button" x-on:click="openAppointmentModal()"
                        class="w-full flex items-center gap-3 px-4 py-3 bg-[#6a0f70] text-white text-sm font-semibold font-[DM_Sans] hover:bg-[#380740] transition">
                        Book Appointment
                    </button>
                    <button type="button" x-on:click="$dispatch('open-add-patient')"
                        class="w-full flex items-center gap-3 px-4 py-3 border border-[#6a0f70] text-[#6a0f70] text-sm font-semibold font-[DM_Sans] hover:bg-[#f5eef9] transition">
                        Add Patient
                    </button>
                    <a href="{{ route('patients.index') }}"
                        class="w-full flex items-center gap-3 px-4 py-3 border border-gray-200 text-gray-600 text-sm font-semibold font-[DM_Sans] hover:bg-gray-50 transition">
                        All Patients
                    </a>
                </div>
            </div>

            {{-- Go To --}}
            <div class="bg-white border border-[#e8d5f0]">
                <div class="px-5 py-4 border-b border-[#e8d5f0]">
                    <h2 class="text-sm font-semibold uppercase tracking-widest text-[#6a0f70] font-[DM_Sans]">Go To</h2>
                </div>
                <div class="divide-y divide-[#f0e4f5]">
                    @php
                    $shortcuts = [
                        ['label' => 'Finance',       'route' => 'finance.dashboard',        'icon' => 'M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z'],
                        ['label' => 'Lab Cases',     'route' => 'lab.index',                'icon' => 'M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.301 24.301 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1 1 .925 2.65-.2 3.5a9 9 0 01-5 1.5 9 9 0 01-5-1.5c-1.125-.85-1.2-2.5-.2-3.5L5 14.5'],
                        ['label' => 'Inventory',     'route' => 'inventory.index',          'icon' => 'M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z'],
                        ['label' => 'Communication', 'route' => 'communication.index',      'icon' => 'M20.25 8.511c.884.284 1.5 1.128 1.5 2.097v4.286c0 1.136-.847 2.1-1.98 2.193-.34.027-.68.052-1.02.072v3.091l-3-3c-1.354 0-2.694-.055-4.02-.163a2.115 2.115 0 01-.825-.242m9.345-8.334a2.126 2.126 0 00-.476-.095 48.64 48.64 0 00-8.048 0c-1.131.094-1.976 1.057-1.976 2.192v4.286c0 .837.46 1.58 1.155 1.951m9.345-8.334V6.637c0-1.621-1.152-3.026-2.76-3.235A48.455 48.455 0 0011.25 3c-2.115 0-4.198.137-6.24.402-1.608.209-2.76 1.614-2.76 3.235v6.226c0 1.621 1.152 3.026 2.76 3.235.577.075 1.157.14 1.74.194V21l4.155-4.155'],
                        ['label' => 'Marketing',     'route' => 'marketing.campaigns.index','icon' => 'M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 110-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 01-1.44-4.282m3.102.069a18.03 18.03 0 01-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 018.835 2.535M10.34 6.66a23.847 23.847 0 008.835-2.535m0 0A23.74 23.74 0 0018.795 3m.38 1.125a23.91 23.91 0 011.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 001.014-5.395m0-3.46c.495.413.811 1.035.811 1.73 0 .695-.316 1.317-.811 1.73m0-3.46a24.347 24.347 0 010 3.46'],
                    ];
                    @endphp
                    @foreach($shortcuts as $sc)
                        @if(Route::has($sc['route']))
                        <a href="{{ route($sc['route']) }}"
                           class="flex items-center gap-3 px-5 py-3 text-sm text-gray-600 font-[DM_Sans] hover:bg-[#faf5fc] hover:text-[#6a0f70] transition group">
                            {{ $sc['label'] }}
                        </a>
                        @endif
                    @endforeach
                </div>
            </div>

        </div>
    </div>{{-- /two-column --}}

    {{-- Modals --}}
    @include('partials.add-patient-modal')

</div>{{-- /x-data --}}
@endsection
