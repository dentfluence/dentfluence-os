@extends('layouts.app')
@section('page-title', 'Prescriptions — ' . $patient->name)

@section('content')
<div class="p-4 md:p-6 max-w-5xl mx-auto">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-5 gap-3 flex-wrap">
        <div>
            <div class="flex items-center gap-2 mb-0.5">
                <a href="{{ route('patients.show', $patient) }}"
                   class="text-xs text-gray-400 hover:text-brand-700 transition">← Back to Patient</a>
            </div>
            <h1 class="text-2xl font-display font-semibold text-brand-800">Prescriptions</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $patient->name }} · {{ $patient->patient_id }}</p>
        </div>
        <a href="{{ route('patients.prescriptions.create', $patient) }}"
           class="px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-xl hover:bg-brand-700 transition">
            + New Prescription
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">
            {{ session('success') }}
        </div>
    @endif

    {{-- Patient allergy banner --}}
    @if($patient->medical_alert)
        <div class="mb-4 p-3 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800 flex gap-2">
            <span></span>
            <span><strong>Medical Alert:</strong> {{ $patient->medical_alert }}</span>
        </div>
    @endif

    {{-- Prescription list --}}
    @if($prescriptions->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <p class="text-4xl mb-3"></p>
            <p class="font-medium">No prescriptions yet</p>
            <a href="{{ route('patients.prescriptions.create', $patient) }}"
               class="mt-4 inline-block text-sm text-brand-600 hover:underline">
                Write the first prescription →
            </a>
        </div>
    @else
        <div class="space-y-3">
            @foreach($prescriptions as $rx)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex items-start gap-4">

                    {{-- Status dot --}}
                    <div class="mt-1 shrink-0">
                        @if($rx->status === 'finalized')
                            <span class="w-2.5 h-2.5 rounded-full bg-green-500 inline-block"></span>
                        @elseif($rx->status === 'draft')
                            <span class="w-2.5 h-2.5 rounded-full bg-amber-400 inline-block"></span>
                        @else
                            <span class="w-2.5 h-2.5 rounded-full bg-gray-300 inline-block"></span>
                        @endif
                    </div>

                    {{-- Info --}}
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="font-mono text-sm font-semibold text-brand-700">{{ $rx->prescription_number }}</span>
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium
                                @if($rx->status === 'finalized') bg-green-100 text-green-700
                                @elseif($rx->status === 'draft') bg-amber-100 text-amber-700
                                @else bg-gray-100 text-gray-500 @endif">
                                {{ ucfirst($rx->status) }}
                            </span>
                            @if($rx->diagnosis)
                                <span class="text-xs text-gray-500 truncate max-w-xs">{{ $rx->diagnosis }}</span>
                            @endif
                        </div>
                        <div class="text-xs text-gray-400 mt-1 flex gap-3 flex-wrap">
                            <span>{{ $rx->created_at->format('d M Y') }}</span>
                            <span>By: {{ $rx->prescribedBy?->name ?? '—' }}</span>
                            @if($rx->print_count > 0)
                                <span>Printed {{ $rx->print_count }}×</span>
                            @endif
                        </div>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="{{ route('patients.prescriptions.show', [$patient, $rx]) }}"
                           class="text-xs px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition">
                            View
                        </a>
                        @if($rx->status !== 'cancelled')
                            <a href="{{ route('patients.prescriptions.edit', [$patient, $rx]) }}"
                               class="text-xs px-3 py-1.5 rounded-lg bg-brand-600 text-white hover:bg-brand-700 transition">
                                Edit
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">{{ $prescriptions->links() }}</div>
    @endif
</div>
@endsection
