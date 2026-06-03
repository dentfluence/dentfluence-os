{{-- resources/views/consultations/print.blade.php --}}
@extends('layouts.print')

@section('title', 'Consultation — ' . $consultation->patient->name)

@section('content')
@php
    $p       = $print;
    $patient = $consultation->patient;
    $doctor  = $consultation->doctor;

    // Section visibility
    $showVitals    = ($p['print_section_vital_signs']    ?? '1') === '1';
    $showComplaints= ($p['print_section_complaints']     ?? '1') === '1';
    $showNotes     = ($p['print_section_notes']          ?? '1') === '1';
    $showInvest    = ($p['print_section_investigations'] ?? '1') === '1';
    $showDiag      = ($p['print_section_diagnosis']      ?? '1') === '1';
    $showTreat     = ($p['print_section_treatments']     ?? '1') === '1';
    $showRemarks   = ($p['print_section_remarks']        ?? '1') === '1';
    $showFollowup  = ($p['print_section_followup']       ?? '1') === '1';
@endphp

{{-- ── Patient + Visit Info ── --}}
<div class="print-section">
    <div class="print-section-title">Patient Information</div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px 24px;">
        <div class="print-row"><span class="print-label">Patient:</span><span class="print-value">{{ $patient->name }}</span></div>
        <div class="print-row"><span class="print-label">Date:</span><span class="print-value">{{ $consultation->created_at->format('d M Y') }}</span></div>
        <div class="print-row"><span class="print-label">Age / Gender:</span><span class="print-value">{{ $patient->age ?? '—' }} / {{ ucfirst($patient->gender ?? '—') }}</span></div>
        <div class="print-row"><span class="print-label">Doctor:</span><span class="print-value">Dr. {{ $doctor?->name ?? '—' }}</span></div>
        <div class="print-row"><span class="print-label">Phone:</span><span class="print-value">{{ $patient->phone ?? '—' }}</span></div>
        <div class="print-row"><span class="print-label">Visit Type:</span><span class="print-value">{{ ucfirst($consultation->visit_type ?? '—') }}</span></div>
    </div>
</div>

{{-- ── Vital Signs ── --}}
@if($showVitals && ($consultation->bp || $consultation->pulse || $consultation->temp || $consultation->weight))
<div class="print-section">
    <div class="print-section-title">Vital Signs</div>
    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:8px;">
        @if($consultation->bp)
        <div style="text-align:center;padding:8px;border:1px solid #ede4f3;border-radius:6px;">
            <div style="font-size:9pt;color:#888;">BP</div>
            <div style="font-weight:700;">{{ $consultation->bp }}</div>
        </div>
        @endif
        @if($consultation->pulse)
        <div style="text-align:center;padding:8px;border:1px solid #ede4f3;border-radius:6px;">
            <div style="font-size:9pt;color:#888;">Pulse</div>
            <div style="font-weight:700;">{{ $consultation->pulse }} bpm</div>
        </div>
        @endif
        @if($consultation->temp)
        <div style="text-align:center;padding:8px;border:1px solid #ede4f3;border-radius:6px;">
            <div style="font-size:9pt;color:#888;">Temp</div>
            <div style="font-weight:700;">{{ $consultation->temp }}°F</div>
        </div>
        @endif
        @if($consultation->weight)
        <div style="text-align:center;padding:8px;border:1px solid #ede4f3;border-radius:6px;">
            <div style="font-size:9pt;color:#888;">Weight</div>
            <div style="font-weight:700;">{{ $consultation->weight }} kg</div>
        </div>
        @endif
    </div>
</div>
@endif

{{-- ── Chief Complaints ── --}}
@if($showComplaints && $consultation->chief_complaint)
<div class="print-section">
    <div class="print-section-title">Chief Complaint</div>
    <p style="font-size:11pt;line-height:1.6;">{{ $consultation->chief_complaint }}</p>
</div>
@endif

{{-- ── Notes ── --}}
@if($showNotes && $consultation->notes)
<div class="print-section">
    <div class="print-section-title">Clinical Notes</div>
    <p style="font-size:11pt;line-height:1.6;white-space:pre-wrap;">{{ $consultation->notes }}</p>
</div>
@endif

{{-- ── Investigations ── --}}
@if($showInvest && $consultation->investigations_advised)
<div class="print-section">
    <div class="print-section-title">Investigations Advised</div>
    <p style="font-size:11pt;line-height:1.6;">{{ $consultation->investigations_advised }}</p>
</div>
@endif

{{-- ── Diagnosis ── --}}
@if($showDiag && ($consultation->diagnoses?->count() || $consultation->diagnosis_notes))
<div class="print-section">
    <div class="print-section-title">Diagnosis</div>
    @if($consultation->diagnoses?->count())
    <ul style="margin:0;padding-left:18px;font-size:11pt;line-height:1.8;">
        @foreach($consultation->diagnoses as $d)
        <li>{{ $d->name }}</li>
        @endforeach
    </ul>
    @endif
    @if($consultation->diagnosis_notes)
    <p style="font-size:11pt;margin-top:6px;color:#333;">{{ $consultation->diagnosis_notes }}</p>
    @endif
</div>
@endif

{{-- ── Treatment Plans ── --}}
@if($showTreat && $consultation->treatmentPlans?->count())
<div class="print-section">
    <div class="print-section-title">Treatment Plan</div>
    @foreach($consultation->treatmentPlans as $plan)
    @if($plan->items?->count())
    <table class="print-table" style="margin-bottom:10px;">
        <thead>
            <tr>
                <th>#</th>
                <th>Treatment</th>
                <th>Tooth</th>
                <th>Stage</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plan->items as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->treatment_name ?? $item->name }}</td>
                <td>{{ $item->tooth_number ?? '—' }}</td>
                <td>{{ ucfirst($item->status ?? '—') }}</td>
                <td>{{ $item->amount ? '₹'.number_format($item->amount,0) : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
    @endforeach
</div>
@endif

{{-- ── Remarks / Advice ── --}}
@if($showRemarks && $consultation->remarks)
<div class="print-section">
    <div class="print-section-title">Remarks / Advice</div>
    <p style="font-size:11pt;line-height:1.6;white-space:pre-wrap;">{{ $consultation->remarks }}</p>
</div>
@endif

{{-- ── Follow-up ── --}}
@if($showFollowup && $consultation->followup_date)
<div class="print-section">
    <div class="print-section-title">Follow-up</div>
    <div class="print-row">
        <span class="print-label">Next Visit:</span>
        <span class="print-value" style="font-weight:700;">{{ \Carbon\Carbon::parse($consultation->followup_date)->format('d M Y') }}</span>
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
