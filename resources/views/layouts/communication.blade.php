@extends('layouts.app')

@push('styles')
    @vite('resources/css/communication/module.css')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    @stack('communication-styles')
@endpush

@section('content')
    @yield('communication-content')

    {{-- Global PRM modals — available on every communication page --}}
    <x-prm.add-lead-modal />
@endsection

@push('scripts')
    @vite('resources/js/communication/navigation.js')
    @stack('communication-scripts')
@endpush