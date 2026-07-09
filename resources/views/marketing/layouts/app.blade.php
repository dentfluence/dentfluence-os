{{--
|==========================================================================
| Dentfluence OS — Marketing Module Layout
| File: resources/views/marketing/layouts/app.blade.php
|
| Architecture:
|   Extends layouts.app (inherits sidebar + topbar + shell).
|   Injects a full-width secondary tab nav into the content slot.
|   Child views extend THIS layout and fill @section('marketing-content').
|
| Usage in child views:
|   @extends('marketing.layouts.app')
|   @section('page-title', 'Marketing — Overview')
|   @section('marketing-content') ... @endsection
|==========================================================================
--}}
@extends('layouts.app')

{{-- ── Let child views override the browser title ── --}}
@section('page-title', 'Marketing — ' . ($marketingPageTitle ?? 'Overview'))

@section('content')

{{-- ══════════════════════════════════════════════════════════════
     MARKETING SUB-NAV
     Extracted to <x-marketing.subnav /> 2026-07-09 —
     see resources/views/components/marketing/subnav.blade.php.
═══════════════════════════════════════════════════════════════ --}}
<x-marketing.subnav />

{{-- ══════════════════════════════════════════════════════════════
     MARKETING CONTENT SLOT
     Child views fill this section.
═══════════════════════════════════════════════════════════════ --}}
@yield('marketing-content')

@endsection
