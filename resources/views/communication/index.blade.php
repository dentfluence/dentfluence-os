{{--
    Communication OS — Module Home / Dashboard
    Dentfluence · Tulip Dental · Session 1
--}}
@extends('layouts.communication')

@section('communication-content')
<div class="comm-home">

    {{-- ── Page Header ────────────────────────────────────────────── --}}
    <div class="comm-home__header">
        <div class="comm-home__header-text">
            <h1 class="comm-home__title">Communication OS</h1>
            <p class="comm-home__subtitle">Operational command center for patient relationships at Tulip Dental</p>
        </div>
        <div class="comm-home__header-meta">
            <span class="comm-home__date">{{ now()->format('D, d M Y') }}</span>
        </div>
    </div>

    {{-- ── Core Principle Banner ──────────────────────────────────── --}}
    <div class="comm-home__principle">
        <div class="comm-home__principle-icon" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <p><strong>No communication leakage.</strong> Always know the next required action.</p>
    </div>

    {{-- ── Module Grid ─────────────────────────────────────────────── --}}
    <div class="comm-home__grid">
        @foreach($modules as $module)
            @include('components.communication.nav-item', ['module' => $module])
        @endforeach
    </div>

</div>
@endsection
