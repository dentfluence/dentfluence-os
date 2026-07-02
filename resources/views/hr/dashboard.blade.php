@extends('layouts.app')
@section('page-title', 'HR Dashboard')

@section('content')
<div class="p-6 space-y-6">

    @include('hr.partials.subnav', ['active' => 'dashboard'])

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-display font-semibold text-gray-900">HR Dashboard</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ now()->format('l, d M Y') }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('hr.attendance.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
                Attendance
            </a>
            <a href="{{ route('hr.staff.create') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-purple-700 rounded-lg text-sm font-medium text-white hover:bg-purple-800 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add Staff
            </a>
        </div>
    </div>

    {{-- Stat Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">

        <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Staff</p>
            <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalStaff }}</p>
            <a href="{{ route('hr.staff.index') }}" class="text-xs text-purple-600 mt-1 inline-block hover:underline">View all →</a>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Present Today</p>
            <p class="text-3xl font-bold text-green-600 mt-1">{{ $presentCount }}</p>
            <p class="text-xs text-gray-400 mt-1">of {{ $totalStaff }} staff</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Absent</p>
            <p class="text-3xl font-bold text-red-500 mt-1">{{ $absentCount }}</p>
            <p class="text-xs text-gray-400 mt-1">{{ $notMarkedCount }} not marked</p>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 p-5 shadow-sm">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">On Leave</p>
            <p class="text-3xl font-bold text-blue-500 mt-1">{{ $onLeaveCount }}</p>
            <p class="text-xs text-gray-400 mt-1">today</p>
        </div>

    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Today's Attendance Board --}}
        <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between p-5 border-b border-gray-50">
                <h2 class="font-semibold text-gray-800">Today's Attendance</h2>
                <a href="{{ route('hr.attendance.index') }}" class="text-xs text-purple-600 hover:underline">Mark attendance →</a>
            </div>
            <div class="divide-y divide-gray-50">
                @forelse($todayAttendance as $record)
                <div class="flex items-center justify-between px-5 py-3">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700">
                            {{ $record->user->initials ?? '?' }}
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-800">{{ $record->user->name }}</p>
                            <p class="text-xs text-gray-400">{{ $record->user->designation ?? $record->user->role_label }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        @if($record->check_in)
                        <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($record->check_in)->format('h:i A') }}</span>
                        @endif
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $record->status_badge_class }}">
                            {{ $record->status_label }}
                        </span>
                    </div>
                </div>
                @empty
                <div class="px-5 py-8 text-center text-sm text-gray-400">
                    No attendance marked yet today.
                    <a href="{{ route('hr.attendance.index') }}" class="text-purple-600 hover:underline ml-1">Mark now →</a>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Right column --}}
        <div class="space-y-5">

            {{-- License Expiry Alerts --}}
            @if($expiringLicenses->count())
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-5">
                <h3 class="font-semibold text-amber-800 text-sm flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                    </svg>
                    License Expiry Alerts
                </h3>
                <div class="mt-3 space-y-2">
                    @foreach($expiringLicenses as $profile)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-amber-900">{{ $profile->user->name }}</span>
                        <span class="text-xs font-medium text-amber-700">
                            {{ $profile->license_days_remaining }}d left
                        </span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

        </div>
    </div>

</div>
@endsection
