{{-- Overdue Communications — Visually prominent, action-oriented --}}
@extends('layouts.communication')

@section('title', 'Overdue Communications')

@push('communication-styles')
    <link rel="stylesheet" href="{{ asset('css/communication/manager.css') }}">
@endpush

@section('communication-content')
<div class="comm-manager">

    <div class="cm-page-header">
        <div class="cm-page-header-top">
            <h1 class="cm-page-title">
                🔴 Overdue
                <span>/ Needs Immediate Action</span>
            </h1>
            <div class="cm-header-actions">
                <a href="{{ route('communication.manager.log.form') }}" class="cm-btn cm-btn-primary">
                    + Log Communication
                </a>
            </div>
        </div>
        <div style="display:flex; gap:0; border-bottom: 1px solid var(--cm-border);">
            <a href="{{ route('communication.manager.index') }}" class="cm-tab">All Queue</a>
            <a href="{{ route('communication.manager.queue') }}" class="cm-tab">Execution Queue</a>
            <a href="{{ route('communication.manager.overdue') }}" class="cm-tab active">
                🔴 Overdue
                @if(count($items)) <span class="count">{{ count($items) }}</span> @endif
            </a>
        </div>
    </div>

    <div class="cm-body">

        @if(count($items) > 0)

        {{-- Alert banner --}}
        <div class="cm-overdue-header">
            <div class="icon">⚠️</div>
            <div>
                <h3>{{ count($items) }} communications need immediate attention</h3>
                <p>These have passed their due time. Resolve or escalate now.</p>
            </div>
        </div>

        {{-- Sort by age: longest overdue first --}}
        <div class="cm-section">
            <div class="cm-section-header">
                <div class="cm-section-title">
                    Overdue Items
                    <span class="badge danger">{{ count($items) }}</span>
                </div>
            </div>
            <div class="cm-queue">
                @foreach($items as $item)
                    <x-communication.queue-card :item="$item" />
                @endforeach
            </div>
        </div>

        @else

        <div class="cm-empty">
            <div class="cm-empty-icon">✅</div>
            <h3>No overdue items!</h3>
            <p>Everything is on track. Keep it up.</p>
        </div>

        @endif

    </div>
</div>
@endsection

@push('communication-scripts')
    <script src="{{ asset('js/communication/manager.js') }}"></script>
    <script src="{{ asset('js/communication/queue.js') }}"></script>
@endpush
