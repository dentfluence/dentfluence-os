@extends('layouts.communication')

{{-- Phase B 2.4 — Reviews / reputation dashboard.
     Content extracted to _content.blade.php 2026-07-09 so the identical
     board can also render natively inside Marketing at /marketing/reviews
     (see resources/views/marketing/reviews/index.blade.php) instead of
     Marketing linking out to this URL. --}}

@section('communication-content')
<x-communication.top-nav-tabs active="reviews" />
@include('communication.reviews._content')
@endsection
