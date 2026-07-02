{{-- Execution Queue — Prioritized actionable items --}}
@extends('layouts.communication')

@section('title', 'Execution Queue')

@push('communication-styles')
    <link rel="stylesheet" href="{{ asset('css/communication/manager.css') }}">
@endpush

@section('communication-content')
<div class="comm-manager">

    <div class="cm-page-header">
        <div class="cm-page-header-top">
            <h1 class="cm-page-title">
                Execution Queue
                <span>/ Today's Actions</span>
            </h1>
            <div class="cm-header-actions">
                <a href="{{ route('communication.manager.log.form') }}" class="cm-btn cm-btn-primary">
                    + Log Communication
                </a>
            </div>
        </div>

        {{-- Tabs --}}
        <div style="display:flex; gap:0; border-bottom: 1px solid var(--cm-border);">
            <a href="{{ route('communication.manager.index') }}" class="cm-tab">All Queue</a>
            <a href="{{ route('communication.manager.queue') }}" class="cm-tab active">
                Execution Queue
            </a>
            <a href="{{ route('communication.manager.overdue') }}" class="cm-tab">Overdue</a>
        </div>
    </div>

    <div class="cm-body">

        <x-communication.filter-bar :filters="$filters" />

        @if(count($items) > 0)

            {{-- Sort by priority: high → medium → low --}}
            @php
            $high   = collect($items)->where('priority', 'high')->values();
            $medium = collect($items)->where('priority', 'medium')->values();
            $low    = collect($items)->where('priority', 'low')->values();
            @endphp

            @if($high->count())
            <div class="cm-section">
                <div class="cm-section-header">
                    <div class="cm-section-title">
                        High Priority
                        <span class="badge danger">{{ $high->count() }}</span>
                    </div>
                </div>
                <div class="cm-queue">
                    @foreach($high as $item)
                        <x-communication.queue-card :item="$item->toArray()" />
                    @endforeach
                </div>
            </div>
            @endif

            @if($medium->count())
            <div class="cm-section">
                <div class="cm-section-header">
                    <div class="cm-section-title">
                        Medium Priority
                        <span class="badge normal">{{ $medium->count() }}</span>
                    </div>
                </div>
                <div class="cm-queue">
                    @foreach($medium as $item)
                        <x-communication.queue-card :item="$item->toArray()" />
                    @endforeach
                </div>
            </div>
            @endif

            @if($low->count())
            <div class="cm-section">
                <div class="cm-section-header">
                    <div class="cm-section-title">
                        Low Priority
                        <span class="badge normal">{{ $low->count() }}</span>
                    </div>
                </div>
                <div class="cm-queue">
                    @foreach($low as $item)
                        <x-communication.queue-card :item="$item->toArray()" />
                    @endforeach
                </div>
            </div>
            @endif

        @else
            <div class="cm-empty">
                <h3>Queue is clear</h3>
                <p>Nothing pending. All actions are up to date.</p>
            </div>
        @endif

    </div>
</div>
@endsection

@push('communication-scripts')
    <script src="{{ asset('js/communication/manager.js') }}"></script>
    <script src="{{ asset('js/communication/queue.js') }}"></script>
@endpush
