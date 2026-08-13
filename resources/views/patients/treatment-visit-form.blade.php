{{-- Dedicated Treatment Visit form page (08-05, presentation-only refactor).
     Replaces the modal that used to live inline in the Treatment Visits tab.
     Same store()/update() endpoints, same validation, same payload, same
     treatmentVisits() Alpine factory (shared via include, unmodified except
     one additive guarded redirect on save — see treatment-visit-form-script).
     Reached from patients/partials/treatment-visits-tab.blade.php's
     "+ Add Visit" / row "Edit" links, and from the UX-05 consultation-save
     deep-link handoff (patients/show.blade.php). Returns to
     patients.show#visits on Cancel and after a successful Save, using the
     same hash-navigation infrastructure Consultation's own page already
     uses (consultations/create.blade.php:471). --}}
@extends('layouts.app')

@section('page-title', isset($visit) ? 'Edit Visit' : 'New Visit')

@section('content')
{{-- Native PHP include, not Blade @include — see
     patients/partials/_treatment-visit-bootstrap.php header comment. --}}
@php
    include resource_path('views/patients/partials/_treatment-visit-bootstrap.php');
@endphp

<div class="max-w-[1440px] mx-auto px-6 pt-4 pb-2 border-b border-gray-100">
    <a href="{{ route('patients.show', $patient) }}#visits"
       class="text-xs font-semibold text-[#6a0f70] hover:underline">← Back to Treatment Visits</a>
    <div class="flex items-baseline gap-2 mt-1">
        <h1 class="text-lg font-bold text-gray-900">{{ isset($visit) ? 'Edit Visit' : 'New Visit' }}</h1>
        <span class="text-gray-300">·</span>
        <p class="text-sm text-gray-500">{{ $patient->name }}</p>
    </div>
</div>

<div x-data="treatmentVisits()"
     x-init="
        window.TV_AFTER_SAVE_REDIRECT = @js(route('patients.show', $patient) . '#visits');
        @if(isset($visit))
        openEditForm(visits.find(v => v.id === {{ (int) $visit->id }}));
        @else
        openAddForm({ appointment_id: {{ Js::from($prefillAppointmentId ?? '') }}, treatment_plan_id: {{ Js::from($prefillPlanId ?? '') }} });
        @endif
     ">
    @include('patients.partials.treatment-visit-form-fields')
</div>

{{-- BUG FIX (08-05): this include was missing entirely. treatmentVisits()
     (the whole Alpine factory + TV_STAGES/TV_LAB_TREATMENTS/etc. constants)
     is defined ONLY in this partial, behind @push('scripts'). Without it,
     x-data="treatmentVisits()" above throws "treatmentVisits is not defined"
     on this route — the page rendered (HTML/CSS is server-side, unaffected)
     but had zero reactivity, since Alpine never had a component to init.
     The tab fragment (treatment-visits-tab.blade.php) already includes this
     — this page needs its own copy since it's reached by direct navigation,
     not injected into a page that already loaded it. --}}
@include('patients.partials.treatment-visit-form-script')
@endsection
