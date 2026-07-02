@extends('layouts.app')

@push('styles')
    @vite('resources/css/communication/module.css')
    <link rel="stylesheet" href="{{ asset('css/communication/timeline.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    @stack('communication-styles')
@endpush

@section('content')
    {{-- ── Back to PRM Dashboard (hidden on the dashboard itself) ── --}}
    @unless(request()->routeIs('communication.index'))
    <div style="padding:10px 24px 0; display:flex; align-items:center;">
        <a href="{{ route('communication.index') }}"
           style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:500;color:var(--comm-brand);text-decoration:none;padding:5px 12px;border:1px solid var(--comm-brand-border);border-radius:6px;background:var(--comm-brand-bg);transition:all .15s;"
           onmouseover="this.style.background='#6a0f70';this.style.color='#fff';"
           onmouseout="this.style.background='#fdf4ff';this.style.color='#6a0f70';">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            PRM Dashboard
        </a>
    </div>
    @endunless
    @yield('communication-content')
    <x-prm.add-lead-modal />
@endsection

@push('scripts')
    @vite('resources/js/communication/navigation.js')
    <script src="{{ asset('js/communication/timeline.js') }}"></script>
    @stack('communication-scripts')
@endpush
