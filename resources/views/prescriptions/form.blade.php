{{--
    resources/views/prescriptions/form.blade.php

    Standalone create/edit page — used only for entry points outside the
    patient's Prescriptions tab (the "Write Prescription" quick action, and
    "write from this Treatment Visit"). Shares the exact same quick-form
    partial as the inline tab form, so both places look and behave
    identically. Prefilled from $prescription when editing.

    A prescription is live the moment it's saved — there is no draft/finalize
    step, and it can always be edited again afterwards.
--}}
@extends('layouts.app')
@section('page-title', $prescription->exists ? 'Edit Prescription' : 'New Prescription')

@section('content')
<div class="p-4 md:p-6 max-w-3xl mx-auto">

    {{-- ── Header ── --}}
    <div class="flex items-center justify-between mb-5">
        <div>
            <h1 class="text-2xl font-display font-semibold text-brand-800">
                {{ $prescription->exists ? 'Edit Prescription' : 'New Prescription' }}
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">
                Patient: <span class="font-medium text-gray-700">{{ $patient->name }}</span>
                @if($prescription->exists)
                    &nbsp;·&nbsp; <span class="font-mono text-xs text-brand-600">{{ $prescription->prescription_number }}</span>
                @endif
            </p>
        </div>
        <a href="{{ route('patients.show', $patient) }}#prescriptions"
           onclick="sessionStorage.setItem('patientActiveTab','prescriptions')"
           class="text-sm text-gray-500 hover:text-brand-700 flex items-center gap-1">
            ← Back to Patient
        </a>
    </div>

    {{-- ── Validation errors ── --}}
    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        @include('prescriptions.partials.quick-form', [
            'patient'      => $patient,
            'prescription' => $prescription,
            'formAction'   => $prescription->exists
                ? route('patients.prescriptions.update', [$patient, $prescription])
                : route('patients.prescriptions.store', $patient),
            'formMethod'   => $prescription->exists ? 'PUT' : 'POST',
            'cancelUrl'    => route('patients.show', $patient) . '#prescriptions',
        ])
    </div>
</div>
@endsection
