{{-- resources/views/visits/print.blade.php --}}
@extends('layouts.print')

@section('title', 'Visit — ' . $visit->patient->name)

@section('content')
@php
    $p       = $print;
    $patient = $visit->patient;
    $doctor  = $visit->doctor;

    $showVitals   = ($p['print_section_vital_signs']    ?? '1') === '1';
    $showComplaints=($p['print_section_complaints']     ?? '1') === '1';
    $showNotes    = ($p['print_section_notes']          ?? '1') === '1';
    $showTreat    = ($p['print_section_treatments']     ?? '1') === '1';
    $showRemarks  = ($p['print_section_remarks']        ?? '1') === '1';
    $showFollowup = ($p['print_section_followup']       ?? '1') === '1';

    $drugs        = $visit->prescription_drugs ?? [];
    $instructions = $visit->prescription_instructions ?? [];
@endphp

{{-- ── Visit Header ── --}}
<div class="print-section">
    <div class="print-section-title">Visit Details</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 24px;">
        <div class="print-row"><span class="print-label">Patient:</span><span class="print-value">{{ $patient->name }}</span></div>
        <div class="print-row"><span class="print-label">Visit Date:</span><span class="print-value">{{ \Carbon\Carbon::parse($visit->visit_date)->format('d M Y') }}</span></div>
        <div class="print-row"><span class="print-label">Age / Gender:</span><span class="print-value">{{ $patient->age ?? '—' }} / {{ ucfirst($patient->gender ?? '—') }}</span></div>
        <div class="print-row"><span class="print-label">Doctor:</span><span class="print-value">Dr. {{ $doctor?->name ?? '—' }}</span></div>
        <div class="print-row"><span class="print-label">Phone:</span><span class="print-value">{{ $patient->phone ?? '—' }}</span></div>
        <div class="print-row"><span class="print-label">Visit Type:</span><span class="print-value">{{ ucfirst($visit->visit_type ?? '—') }}</span></div>
        @if($visit->tooth_number)
        <div class="print-row"><span class="print-label">Tooth No:</span><span class="print-value">{{ $visit->tooth_number }}</span></div>
        @endif
        @if($visit->treatment_name)
        <div class="print-row"><span class="print-label">Treatment:</span><span class="print-value">{{ $visit->treatment_name }}</span></div>
        @endif
    </div>
</div>

{{-- ── Chief Complaint ── --}}
@if($showComplaints && $visit->chief_complaint)
<div class="print-section">
    <div class="print-section-title">Chief Complaint</div>
    <p style="font-size:11pt;line-height:1.6;">{{ $visit->chief_complaint }}</p>
</div>
@endif

{{-- ── Clinical Notes ── --}}
@if($showNotes && $visit->notes)
<div class="print-section">
    <div class="print-section-title">Clinical Notes</div>
    <p style="font-size:11pt;line-height:1.6;white-space:pre-wrap;">{{ $visit->notes }}</p>
</div>
@endif

{{-- ── Treatment Done ── --}}
@if($showTreat && $visit->current_stage)
<div class="print-section">
    <div class="print-section-title">Treatment Done</div>
    <div class="print-row"><span class="print-label">Stage:</span><span class="print-value">{{ $visit->current_stage }}</span></div>
    @if(!empty($visit->completed_stages))
    <div class="print-row"><span class="print-label">Completed:</span><span class="print-value">{{ implode(', ', $visit->completed_stages) }}</span></div>
    @endif
</div>
@endif

{{-- ── Prescription ── --}}
@if($showTreat && count($drugs))
<div class="print-section">
    <div class="print-section-title">Prescription</div>
    <table class="print-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Medicine</th>
                <th>Dose</th>
                <th>Frequency</th>
                <th>Duration</th>
                <th>Instructions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($drugs as $i => $drug)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $drug['name'] ?? '—' }}</td>
                <td>{{ $drug['dose'] ?? '—' }}</td>
                <td>{{ $drug['frequency'] ?? '—' }}</td>
                <td>{{ isset($drug['duration']) ? $drug['duration'].' days' : '—' }}</td>
                <td>{{ $drug['instructions'] ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    @if($visit->prescription_custom_notes)
    <p style="margin-top:8px;font-size:10.5pt;color:#555;">
        <strong>Note:</strong> {{ $visit->prescription_custom_notes }}
    </p>
    @endif
</div>
@endif

{{-- ── Patient Instructions ── --}}
@if($showRemarks && count($instructions))
<div class="print-section">
    <div class="print-section-title">Patient Instructions</div>
    <ul style="margin:0;padding-left:18px;font-size:11pt;line-height:1.8;">
        @foreach($instructions as $instr)
        <li>{{ is_array($instr) ? ($instr['text'] ?? $instr) : $instr }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- ── Follow-up ── --}}
@if($showFollowup && $visit->next_visit_date)
<div class="print-section">
    <div class="print-section-title">Follow-up</div>
    <div class="print-row">
        <span class="print-label">Next Visit:</span>
        <span class="print-value" style="font-weight:700;">{{ \Carbon\Carbon::parse($visit->next_visit_date)->format('d M Y') }}</span>
        @if($visit->next_visit_type)
        &nbsp;—&nbsp;{{ ucfirst($visit->next_visit_type) }}
        @endif
    </div>
</div>
@endif

{{-- ── Signature ── --}}
<div class="print-signature">
    <div class="sig-line"></div>
    <div class="sig-name">Dr. {{ $doctor?->name }}</div>
    <div style="font-size:9pt;color:#888;">{{ $doctor?->designation ?? 'BDS' }}</div>
</div>

{{-- ── Footer ── --}}
<div class="print-footer">
    <span>{{ $clinic['clinic_name'] ?? '' }} &nbsp;|&nbsp; {{ $clinic['clinic_phone'] ?? '' }}</span>
    <span>Printed: {{ now()->format('d M Y, h:i A') }}</span>
</div>

@endsection
