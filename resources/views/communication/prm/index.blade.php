@extends('layouts.communication')

@section('title', 'Pipeline Board')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/communication/prm.css') }}">
@endpush

@section('content')
<div class="prm-page">

    {{-- ── Page Header ──────────────────────────────────────────────── --}}
    <div class="prm-page__header">
        <div class="prm-page__header-left">
            <h1 class="prm-page__title">
                Pipeline Board
                <i class="ti ti-info-circle prm-page__title-icon" title="Visualize and manage leads across all stages"></i>
            </h1>
            <p class="prm-page__subtitle">Visualize and manage leads across all stages</p>
        </div>
        <div class="prm-page__header-actions">
            <div class="prm-view-toggle">
                <a href="{{ route('communication.prm.index') }}" class="prm-view-toggle__btn prm-view-toggle__btn--active">
                    <i class="ti ti-layout-grid"></i> Board View
                </a>
                <a href="{{ route('communication.prm.index') }}?view=list" class="prm-view-toggle__btn">
                    <i class="ti ti-list"></i> List View
                </a>
            </div>
            <button class="prm-btn prm-btn--outline" id="filtersBtn">
                <i class="ti ti-filter"></i> Filters
            </button>
            <div class="prm-btn-group">
                <button class="prm-btn prm-btn--primary" id="addLeadBtn"
                    data-action="open-add-lead">
                    <i class="ti ti-plus"></i> Add Lead
                </button>
                <button class="prm-btn prm-btn--primary prm-btn--icon-only" id="addLeadDropdown">
                    <i class="ti ti-chevron-down"></i>
                </button>
            </div>
        </div>
    </div>

    {{-- ── Summary Stats ─────────────────────────────────────────────── --}}
    <div class="prm-stats">
        @php
            $statCards = [
                ['icon' => 'ti-users',    'label' => 'Total Leads', 'value' => $stats['total'],      'trend' => '+12% vs last month', 'up' => true,  'bg' => '#EEEDFE', 'color' => '#534AB7'],
                ['icon' => 'ti-user-check','label' => 'Converted',  'value' => $stats['converted'],  'trend' => '+18% vs last month', 'up' => true,  'bg' => '#E1F5EE', 'color' => '#0F6E56'],
                ['icon' => 'ti-trending-up','label'=> 'In Pipeline', 'value' => $stats['in_pipeline'],'trend' => '+8% vs last month',  'up' => true,  'bg' => '#E6F1FB', 'color' => '#185FA5'],
                ['icon' => 'ti-user-x',   'label' => 'Lost',        'value' => $stats['lost'],       'trend' => '-5% vs last month',  'up' => false, 'bg' => '#FAECE7', 'color' => '#993C1D'],
            ];
        @endphp

        @foreach($statCards as $card)
        <div class="prm-stat-card">
            <div class="prm-stat-card__header">
                <div class="prm-stat-card__icon" style="background:{{ $card['bg'] }};color:{{ $card['color'] }}">
                    <i class="ti {{ $card['icon'] }}"></i>
                </div>
                <span class="prm-stat-card__label">{{ $card['label'] }}</span>
            </div>
            <div class="prm-stat-card__value">{{ $card['value'] }}</div>
            <div class="prm-stat-card__trend {{ $card['up'] ? 'prm-stat-card__trend--up' : 'prm-stat-card__trend--down' }}">
                <i class="ti {{ $card['up'] ? 'ti-trending-up' : 'ti-trending-down' }}"></i>
                {{ $card['trend'] }}
            </div>
        </div>
        @endforeach
    </div>

    {{-- ── Pipeline Board ────────────────────────────────────────────── --}}
    @include('communication.prm.board', ['stages' => $stages, 'leads' => $leads])

    {{-- ── Pipeline Summary ─────────────────────────────────────────── --}}
    <div class="prm-summary">
        <h3 class="prm-summary__title">Pipeline Summary</h3>
        <div class="prm-summary__body">
            <canvas id="pipelineDonut" width="100" height="100" class="prm-summary__donut"></canvas>
            <div class="prm-summary__legend">
                @php $total = array_sum(array_map('count', $leads)); @endphp
                @foreach($stages as $stage)
                    @php
                        $count = count($leads[$stage['id']] ?? []);
                        $pct   = $total > 0 ? round($count / $total * 100, 1) : 0;
                    @endphp
                    <div class="prm-summary__legend-item">
                        <span class="prm-summary__legend-dot" style="background:{{ $stage['color'] }}"></span>
                        <span class="prm-summary__legend-label">{{ $stage['label'] }}</span>
                        <span class="prm-summary__legend-count">{{ $count }} ({{ $pct }}%)</span>
                    </div>
                @endforeach
                <div class="prm-summary__legend-item prm-summary__legend-item--total">
                    <span class="prm-summary__legend-label">Total</span>
                    <span class="prm-summary__legend-count">{{ $total }} (100%)</span>
                </div>
            </div>
            <div class="prm-summary__hints">
                <p><i class="ti ti-drag-drop"></i> Drag &amp; Drop to move leads between stages</p>
                <p><i class="ti ti-info-circle"></i> Click on a lead card to view details and take action</p>
            </div>
        </div>
    </div>

</div>

{{-- ── Modals (rendered but hidden) ────────────────────────────────── --}}
@include('components.prm.lead-drawer')

@endsection

@push('scripts')
    <script>
        window.PRM_CONFIG = {
            moveStageUrl:    '{{ route("communication.prm.leads.move-stage", ["id" => "__ID__"]) }}',
            changeStatusUrl: '{{ route("communication.prm.leads.change-status", ["id" => "__ID__"]) }}',
            convertUrl:      '{{ route("communication.prm.leads.convert", ["id" => "__ID__"]) }}',
            leadDetailUrl:   '{{ route("communication.prm.lead-detail", ["id" => "__ID__"]) }}',
            csrfToken:       '{{ csrf_token() }}',
            stages: @json($stages),
        };
    </script>
    <script src="{{ asset('js/communication/prm-board.js') }}"></script>
    <script src="{{ asset('js/communication/lead-drawer.js') }}"></script>
@endpush
