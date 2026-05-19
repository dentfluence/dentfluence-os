{{-- Communication Manager — Main Index Screen --}}
@extends('layouts.communication')

@section('title', 'Communication Manager')

@push('communication-styles')
    <link rel="stylesheet" href="{{ asset('css/communication/manager.css') }}">
@endpush

@section('communication-content')
<div class="comm-manager">

    {{-- Page Header --}}
    <div class="cm-page-header">
        <div class="cm-page-header-top">
            <h1 class="cm-page-title">
                Communication Manager
                <span>/ Tulip Dental</span>
            </h1>
            <div class="cm-header-actions">
                <a href="{{ route('communication.manager.log.form') }}" class="cm-btn cm-btn-primary">
                    + Log Communication
                </a>
                <button class="cm-btn cm-btn-secondary" onclick="refreshQueue()">
                    ↻ Refresh
                </button>
            </div>
        </div>

        {{-- Stats bar --}}
        <div class="cm-stats-bar">
            <div class="cm-stat">
                <div class="cm-stat-value warning">{{ $stats['total_pending'] }}</div>
                <div class="cm-stat-label">Pending</div>
            </div>
            <div class="cm-stat">
                <div class="cm-stat-value danger">{{ $stats['overdue'] }}</div>
                <div class="cm-stat-label">Overdue</div>
            </div>
            <div class="cm-stat">
                <div class="cm-stat-value">{{ $stats['callbacks_today'] }}</div>
                <div class="cm-stat-label">Callbacks Today</div>
            </div>
            <div class="cm-stat">
                <div class="cm-stat-value success">{{ $stats['completed_today'] }}</div>
                <div class="cm-stat-label">Completed Today</div>
            </div>
        </div>
    </div>

    {{-- Body --}}
    <div class="cm-body">

        {{-- Tabs --}}
        <div class="cm-tabs">
            <a href="{{ route('communication.manager.index') }}"
               class="cm-tab active">
                All Queue
                <span class="count">{{ count($queue) }}</span>
            </a>
            <a href="{{ route('communication.manager.queue') }}"
               class="cm-tab">
                Execution Queue
                <span class="count">{{ $stats['total_pending'] }}</span>
            </a>
            <a href="{{ route('communication.manager.overdue') }}"
               class="cm-tab">
                🔴 Overdue
                <span class="count">{{ $stats['overdue'] }}</span>
            </a>
        </div>

        {{-- Filter bar --}}
        <x-communication.filter-bar :filters="$filters" />

        {{-- Overdue section --}}
        @php $overdueItems = collect($queue)->where('is_overdue', true)->values(); @endphp
        @if($overdueItems->count() > 0)
        <div class="cm-section" id="overdue-section">
            <div class="cm-section-header">
                <div class="cm-section-title">
                    🔴 Needs Immediate Attention
                    <span class="badge danger">{{ $overdueItems->count() }} overdue</span>
                </div>
                <a href="{{ route('communication.manager.overdue') }}" class="cm-section-link">
                    View all overdue →
                </a>
            </div>
            <div class="cm-queue" id="overdue-queue">
                @foreach($overdueItems as $item)
                    <x-communication.queue-card :item="$item" />
                @endforeach
            </div>
        </div>
        @endif

        {{-- Pending section --}}
        @php $pendingItems = collect($queue)->where('is_overdue', false)->where('status', 'pending')->values(); @endphp
        @if($pendingItems->count() > 0)
        <div class="cm-section">
            <div class="cm-section-header">
                <div class="cm-section-title">
                    Pending Actions
                    <span class="badge normal">{{ $pendingItems->count() }}</span>
                </div>
            </div>
            <div class="cm-queue" id="pending-queue">
                @foreach($pendingItems as $item)
                    <x-com<x-communication.queue-card :item="$item" />munication.queue-card :item="$item->toArray()" />
                @endforeach
            </div>
        </div>
        @endif

        {{-- In Progress section --}}
        @php $inProgressItems = collect($queue)->where('status', 'in_progress')->values(); @endphp
        @if($inProgressItems->count() > 0)
        <div class="cm-section">
            <div class="cm-section-header">
                <div class="cm-section-title">
                    In Progress
                    <span class="badge normal">{{ $inProgressItems->count() }}</span>
                </div>
            </div>
            <div class="cm-queue">
                @foreach($inProgressItems as $item)
                    <x-communication.queue-card :item="$item" />
                @endforeach
            </div>
        </div>
        @endif

        {{-- Completed today --}}
        @php $completedItems = collect($queue)->where('status', 'completed')->values(); @endphp
        @if($completedItems->count() > 0)
        <div class="cm-section">
            <div class="cm-section-header">
                <div class="cm-section-title">
                    Completed Today
                    <span class="badge normal">{{ $completedItems->count() }}</span>
                </div>
            </div>
            <div class="cm-queue">
                @foreach($completedItems as $item)
                    <x-communication.queue-card :item="$item" />
                @endforeach
            </div>
        </div>
        @endif

        {{-- Empty state --}}
        @if(count($queue) === 0)
        <div class="cm-empty">
            <div class="cm-empty-icon">✅</div>
            <h3>Queue is clear!</h3>
            <p>All communications are handled. Great work.</p>
        </div>
        @endif

    </div>
</div>
@endsection

@push('communication-scripts')
    <script src="{{ asset('js/communication/manager.js') }}"></script>
    <script src="{{ asset('js/communication/queue.js') }}"></script>
@endpush
