{{--
    communication/huddle/widgets.blade.php
    Standalone fallback page at /communication/huddle
    The real integration is via the @include partials embedded in the Daily Huddle view.
--}}
@extends('layouts.app')

@section('title', 'Communication Huddle')

@push('styles')
    @vite(['resources/css/communication/huddle.css'])
@endpush

@section('content')
<div class="comm-huddle-page">
    <div class="comm-huddle-page__header">
        <h1 class="comm-huddle-page__title">Communication Huddle</h1>
        <p class="comm-huddle-page__sub">Today's communication priorities and alerts</p>
    </div>

    {{-- Embed both partials on the standalone page --}}
    @include('communication.huddle.communication-alerts', [
        'alerts' => $alerts,
        'counts' => $counts,
    ])

    <div style="margin-top: 24px;">
        @include('communication.huddle.overdue-summary', [
            'overdue' => $overdue,
            'counts'  => $counts,
        ])
    </div>
</div>
@endsection

@push('scripts')
    @vite(['resources/js/communication/huddle.js'])
@endpush
