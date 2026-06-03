@extends('layouts.app')

@push('styles')
    @vite('resources/css/communication/module.css')
    <link rel="stylesheet" href="{{ asset('css/communication/timeline.css') }}">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
    @stack('communication-styles')
@endpush

@section('content')
    @yield('communication-content')
    <x-prm.add-lead-modal />
@endsection

@push('scripts')
    @vite('resources/js/communication/navigation.js')
    <script src="{{ asset('js/communication/timeline.js') }}"></script>
    @stack('communication-scripts')
@endpush
