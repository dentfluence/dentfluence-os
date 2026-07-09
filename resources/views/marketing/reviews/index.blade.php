@extends('marketing.layouts.app')

@section('page-title', 'Marketing — Reviews')

{{-- Native Marketing Reviews & Reputation page — reuses the shared content
     partial from Communication (same data/actions, no duplicated logic).
     See App\Http\Controllers\Marketing\ReviewsController. --}}
@section('marketing-content')
@include('communication.reviews._content')
@endsection
