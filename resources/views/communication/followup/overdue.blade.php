{{-- resources/views/communication/followup/overdue.blade.php --}}

@extends('layouts.communication')
@section('title', 'Overdue Follow-ups')

@section('content')
<div class="fu-page">

    <div class="fu-topbar">
        <div class="fu-topbar-left">
            <a href="{{ route('communication.followup.index') }}" class="fu-back-btn">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <div>
                <h1 class="fu-page-title" style="color: #EF4444">Overdue Follow-ups</h1>
                <p class="fu-page-sub">{{ count($overdue) }} items need immediate attention</p>
            </div>
        </div>
        <div class="fu-topbar-right">
            <button class="fu-btn-secondary" onclick="openFilterModal()">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                Filters
            </button>
        </div>
    </div>

    <div class="fu-overdue-full-list">
        @foreach($overdue as $item)
        <div class="fu-overdue-full-card">
            <div class="fu-overdue-full-avatar" style="background:#EF444418; color:#EF4444">
                {{ $item['avatar'] }}
            </div>
            <div class="fu-overdue-full-info">
                <span class="fu-overdue-full-name">{{ $item['name'] }}</span>
                <span class="fu-overdue-full-phone">{{ $item['phone'] }}</span>
                <span class="fu-overdue-full-date">{{ $item['date'] }}</span>
                <span class="fu-overdue-full-days">Overdue by {{ $item['overdue_by'] }}</span>
            </div>
            <div class="fu-overdue-full-actions">
                <button class="fu-btn-primary fu-btn-sm" onclick="openCompleteModal({{ $item['id'] }})">Call Now</button>
                <button class="fu-btn-secondary fu-btn-sm" onclick="openRescheduleModal({{ $item['id'] }})">Reschedule</button>
            </div>
        </div>
        @endforeach
    </div>

</div>

@include('components.followup.complete-followup-modal')
@include('components.followup.reschedule-modal')
@include('components.followup.filter-sort-modal')

@endsection

@push('scripts')
<script src="{{ asset('resources/js/communication/followup-modals.js') }}"></script>
@endpush
