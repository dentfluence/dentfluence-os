@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/communication/module.css') }}">
    @stack('communication-styles')
@endpush

@section('content')
    @yield('communication-content')
@endsection

@push('scripts')
    <script src="{{ asset('js/communication/navigation.js') }}" defer></script>
    @stack('communication-scripts')
@endpush