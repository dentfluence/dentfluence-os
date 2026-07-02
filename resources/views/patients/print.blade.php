{{-- resources/views/patients/print.blade.php --}}
@extends('layouts.print')

@section('title', 'Patient Profile — ' . $patient->name)

@section('content')

{{-- ── Patient Details ── --}}
<div class="print-section">
    <div class="print-section-title">Patient Profile</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 24px;">
        <div class="print-row"><span class="print-label">Full Name:</span><span class="print-value">{{ $patient->name }}</span></div>
        <div class="print-row"><span class="print-label">Patient ID:</span><span class="print-value">#{{ str_pad($patient->id, 5, '0', STR_PAD_LEFT) }}</span></div>
        <div class="print-row"><span class="print-label">Date of Birth:</span><span class="print-value">{{ $patient->dob ? \Carbon\Carbon::parse($patient->dob)->format('d M Y') : '—' }}</span></div>
        <div class="print-row"><span class="print-label">Age:</span><span class="print-value">{{ $patient->age ?? '—' }}</span></div>
        <div class="print-row"><span class="print-label">Gender:</span><span class="print-value">{{ ucfirst($patient->gender ?? '—') }}</span></div>
        <div class="print-row"><span class="print-label">Blood Group:</span><span class="print-value">{{ $patient->blood_group ?? '—' }}</span></div>
        <div class="print-row"><span class="print-label">Phone:</span><span class="print-value">{{ $patient->phone ?? '—' }}</span></div>
        <div class="print-row"><span class="print-label">Email:</span><span class="print-value">{{ $patient->email ?? '—' }}</span></div>
        <div class="print-row" style="grid-column:span 2;"><span class="print-label">Address:</span><span class="print-value">{{ $patient->address ?? '—' }}</span></div>
    </div>
</div>

{{-- ── Medical History ── --}}
@if($patient->medical_history || $patient->allergies || $patient->current_medications)
<div class="print-section">
    <div class="print-section-title">Medical History</div>
    @if($patient->medical_history)
    <div class="print-row"><span class="print-label">History:</span><span class="print-value">{{ $patient->medical_history }}</span></div>
    @endif
    @if($patient->allergies)
    <div class="print-row"><span class="print-label">Allergies:</span><span class="print-value" style="color:#c0392b;font-weight:600;">{{ $patient->allergies }}</span></div>
    @endif
    @if($patient->current_medications)
    <div class="print-row"><span class="print-label">Medications:</span><span class="print-value">{{ $patient->current_medications }}</span></div>
    @endif
</div>
@endif

{{-- ── Recent Consultations ── --}}
@if($patient->consultations?->count())
<div class="print-section">
    <div class="print-section-title">Recent Consultations (Last 10)</div>
    <table class="print-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Doctor</th>
                <th>Type</th>
                <th>Chief Complaint</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @foreach($patient->consultations as $c)
            <tr>
                <td>{{ $c->created_at->format('d M Y') }}</td>
                <td>{{ $c->doctor?->doctor_name ?? '—' }}</td>
                <td>{{ ucfirst($c->visit_type ?? '—') }}</td>
                <td>{{ \Illuminate\Support\Str::limit($c->chief_complaint ?? '—', 60) }}</td>
                <td>{{ ucfirst($c->status ?? '—') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ── Footer ── --}}
<div class="print-footer">
    <span>{{ $clinic['clinic_name'] ?? '' }}</span>
    <span>Printed: {{ now()->format('d M Y, h:i A') }}</span>
</div>

@endsection
