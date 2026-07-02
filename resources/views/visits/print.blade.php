<!DOCTYPE html>
{{--
    Visit "Case Sheet" — patient take-home record of a single visit.
    Self-contained print page (no app shell), styled to MATCH the
    consultation print view (consultations/print.blade.php).
    Shows ONLY fields that are filled — no empty rows.
    Section visibility still honours Settings → Print toggles.
--}}
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visit — {{ $visit->patient->name ?? 'Patient' }}</title>

    {{-- ── Apply personalisation colour scheme (same source as the app) ── --}}
    <script>
    (function () {
        try {
            var schemes = {
                'default': { primary:'#6a0f70', dark:'#3a0050', light:'#f7eef8' },
                'blue':    { primary:'#1558b0', dark:'#0d3d80', light:'#eef4fc' },
                'teal':    { primary:'#0d7a6a', dark:'#095a4e', light:'#eafaf7' },
                'green':   { primary:'#1a7a45', dark:'#0f5030', light:'#edfaf2' },
                'rose':    { primary:'#b52058', dark:'#821040', light:'#fdeef4' },
            };
            var prefs = JSON.parse(localStorage.getItem('df_prefs') || '{}');
            var s = schemes[prefs.color] || schemes['default'];
            var root = document.documentElement.style;
            root.setProperty('--accent',       s.primary);
            root.setProperty('--accent-dark',  s.dark);
            root.setProperty('--accent-light', s.light);
        } catch (e) { /* keep CSS defaults */ }
    })();
    </script>

    <style>
        :root { --accent:#6a0f70; --accent-dark:#3a0050; --accent-light:#f7eef8; }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            color: #1a1a1a;
            background: #fff;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }

        /* ── Screen-only action bar ── */
        .no-print {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 10px 16px;
            margin: -40px -40px 28px -40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 12px;
        }
        .btn {
            display: inline-block;
            padding: 6px 16px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--accent);
            background: #fff;
            text-decoration: none;
            color: var(--accent);
            border-radius: 6px;
            margin-left: 6px;
        }
        .btn-print { background: var(--accent); color: #fff; }

        /* ── Letterhead ── */
        .lh {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--accent);
            margin-bottom: 4px;
        }
        .lh-top-rule { border-top: 1px solid #d8d8d8; margin-bottom: 14px; }
        .lh-name  { font-size: 16px; font-weight: 700; color: #111; }
        .lh-line  { font-size: 12px; color: #444; margin-top: 3px; line-height: 1.5; }
        .lh-right { text-align: right; flex-shrink: 0; }
        .lh-doc   { font-size: 15px; font-weight: 700; color: var(--accent-dark); white-space: nowrap; }
        .lh-logo  { max-height: 48px; max-width: 150px; object-fit: contain; margin-bottom: 6px; }

        /* ── Title ── */
        .doc-title {
            text-align: center;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--accent-dark);
            margin: 20px 0 6px;
        }
        .doc-rule { border-bottom: 1px solid #d8d8d8; margin-bottom: 16px; }
        .doc-date { font-size: 12px; color: #333; margin-bottom: 18px; }
        .doc-date strong { color: #111; }

        /* ── Section ── */
        .cp-section { margin-bottom: 16px; page-break-inside: avoid; }
        .cp-section-title {
            font-size: 10px; font-weight: 800; letter-spacing: 1px;
            text-transform: uppercase; color: var(--accent-dark);
            border-bottom: 1px solid var(--accent-light);
            padding-bottom: 4px; margin-bottom: 8px;
        }

        /* ── Label : Value rows ── */
        .cp-row {
            display: grid;
            grid-template-columns: 160px 10px 1fr;
            gap: 4px 6px;
            margin-bottom: 7px;
            font-size: 12.5px;
            align-items: baseline;
        }
        .cp-label { font-weight: 700; color: var(--accent-dark); }
        .cp-colon { color: #555; }
        .cp-value { color: #1a1a1a; line-height: 1.6; white-space: pre-wrap; }

        /* ── Chips / pills ── */
        .cp-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .cp-chip {
            font-size: 11px; font-weight: 600; padding: 2px 10px;
            border-radius: 12px; border: 1px solid var(--accent-light);
            background: var(--accent-light); color: var(--accent-dark);
        }
        .cp-pill {
            display: inline-block; padding: 2px 12px; border-radius: 20px;
            font-size: 11px; font-weight: 700;
        }

        /* ── Prescription table ── */
        .cp-table { width: 100%; border-collapse: collapse; font-size: 11.5px; }
        .cp-table th {
            text-align: left; font-weight: 700; color: var(--accent-dark);
            background: var(--accent-light);
            padding: 6px 8px; border: 1px solid #e6dbe9;
        }
        .cp-table td { padding: 6px 8px; border: 1px solid #eee; vertical-align: top; }
        .cp-list { margin: 0; padding-left: 18px; font-size: 12.5px; line-height: 1.7; }

        /* ── Signature + footer ── */
        .sig { margin-top: 44px; text-align: right; }
        .sig-for { font-size: 11px; color: #555; margin-bottom: 34px; }
        .sig-line { border-top: 1px solid #333; display: inline-block; width: 190px; margin-bottom: 5px; }
        .sig-name { font-size: 12px; font-weight: 700; color: var(--accent-dark); }
        .sig-sub  { font-size: 11px; color: #666; }

        .doc-footer {
            margin-top: 26px; padding-top: 10px;
            border-top: 1px solid #ddd;
            display: flex; justify-content: space-between;
            font-size: 10px; color: #888;
        }

        @media print {
            .no-print { display: none !important; }
            /* Margin is baked into the body padding (not @page) so the browser's
               "Margins" dropdown — even "None" — can't strip the top/bottom space. */
            @page { size: A4; margin: 0; }
            body { padding: 16mm 14mm; max-width: none; margin: 0; }
            .cp-section { page-break-inside: avoid; }
            .cp-chip, .cp-pill, .cp-section-title, .cp-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

@php
    $patient = $visit->patient;
    $doctor  = $visit->doctor;

    $p = $print ?? \App\Models\AppSetting::group('print');

    // ── Section visibility (Settings → Print) ──
    $showComplaints = ($p['print_section_complaints'] ?? '1') === '1';
    $showNotes      = ($p['print_section_notes']      ?? '1') === '1';
    $showTreat      = ($p['print_section_treatments'] ?? '1') === '1';
    $showRemarks    = ($p['print_section_remarks']    ?? '1') === '1';
    $showFollowup   = ($p['print_section_followup']   ?? '1') === '1';

    $patientCode = $patient?->patient_id
                   ?? ('P-' . str_pad($patient?->id ?? 0, 5, '0', STR_PAD_LEFT));

    $genderAge = trim(implode(' / ', array_filter([
        ucfirst($patient->gender ?? ''),
        $patient->age ?? null,
    ])), ' /');

    $addressLine = trim(implode(', ', array_filter([
        $patient->address ?? null,
        $patient->area ?? null,
        $patient->city ?? null,
    ])), ', ');

    $visitDate = $visit->visit_date
        ? \Carbon\Carbon::parse($visit->visit_date)->format('d M Y')
        : ($visit->created_at?->format('d M Y') ?? now()->format('d M Y'));

    $drugs        = $visit->prescription_drugs ?? [];
    $instructions = $visit->prescription_instructions ?? [];

    // ── Does the "Work Done" block have anything to show? ──
    $hasWorkDone = filled($visit->current_stage) || !empty($visit->completed_stages);
@endphp

{{-- ── Screen action bar ── --}}
<div class="no-print">
    <span>Visit — {{ $patient->name ?? '—' }}</span>
    <div>
        <button class="btn btn-print" onclick="window.print()">Print / Save as PDF</button>
        <a class="btn" href="{{ route('patients.show', $patient->id) }}#treatment">← Back</a>
    </div>
</div>

{{-- ── Configurable print header (Settings → Print) ── --}}
@php
    $clinic     = $clinic ?? \App\Models\AppSetting::group('clinic');
    $headerType = $p['print_header_type'] ?? 'plain';
@endphp
@include('partials.print-letterhead')

{{-- ── Letterhead ── --}}
<div class="lh-top-rule"></div>
<div class="lh">
    {{-- Patient (left) --}}
    <div>
        <div class="lh-name">{{ $patient->name ?? '—' }} ({{ $patientCode }})</div>
        @if($genderAge)<div class="lh-line">{{ $genderAge }}</div>@endif
        @if($patient->phone ?? null)<div class="lh-line">{{ $patient->phone }}</div>@endif
        @if($addressLine)<div class="lh-line">{{ $addressLine }}</div>@endif
    </div>

    {{-- Doctor / clinic (right) --}}
    <div class="lh-right">
        <div class="lh-doc">{{ $doctor?->doctor_name ?? ($clinic['clinic_name'] ?? '—') }}</div>
        @if($doctor?->designation ?? null)<div class="lh-line">{{ $doctor->designation }}</div>@endif
        @if($doctor?->registration_number ?? null)<div class="lh-line">Registration No.: {{ $doctor->registration_number }}</div>@endif
    </div>
</div>

{{-- ── Title + date ── --}}
<div class="doc-title">Visit Record</div>
<div class="doc-rule"></div>
<div class="doc-date"><strong>Date:</strong> {{ $visitDate }}</div>

{{-- ── Visit Details ── --}}
<div class="cp-section">
    <div class="cp-section-title">Visit Details</div>
    <div class="cp-row">
        <span class="cp-label">Visit Type</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ ucfirst($visit->visit_type ?? '—') }}</span>
    </div>
    @if($visit->tooth_number)
    <div class="cp-row">
        <span class="cp-label">Tooth No.</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ $visit->tooth_number }}</span>
    </div>
    @endif
    @if($visit->treatment_name)
    <div class="cp-row">
        <span class="cp-label">Treatment</span><span class="cp-colon">:</span>
        <span class="cp-value" style="font-weight:600;">{{ $visit->treatment_name }}</span>
    </div>
    @endif
</div>

{{-- ── Chief Complaint ── --}}
@if($showComplaints && filled($visit->chief_complaint))
<div class="cp-section">
    <div class="cp-section-title">Chief Complaint</div>
    <div class="cp-value" style="font-size:12.5px;">{{ $visit->chief_complaint }}</div>
</div>
@endif

{{-- ── Work Done ── --}}
@if($showTreat && $hasWorkDone)
<div class="cp-section">
    <div class="cp-section-title">Work Done</div>
    @if(filled($visit->current_stage))
    <div class="cp-row">
        <span class="cp-label">Stage</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ $visit->current_stage }}</span>
    </div>
    @endif
    @if(!empty($visit->completed_stages))
    <div class="cp-row">
        <span class="cp-label">Completed</span><span class="cp-colon">:</span>
        <span class="cp-value"><span class="cp-chips">@foreach((array) $visit->completed_stages as $st)<span class="cp-chip">{{ $st }}</span>@endforeach</span></span>
    </div>
    @endif
</div>
@endif

{{-- ── Clinical Notes ── --}}
@if($showNotes && filled($visit->notes))
<div class="cp-section">
    <div class="cp-section-title">Clinical Notes</div>
    <div class="cp-value" style="font-size:12.5px;">{{ $visit->notes }}</div>
</div>
@endif

{{-- ── Prescription ── --}}
@if($showTreat && count($drugs))
<div class="cp-section">
    <div class="cp-section-title">Prescription</div>
    <table class="cp-table">
        <thead>
            <tr>
                <th style="width:24px;">#</th>
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
    @if(filled($visit->prescription_custom_notes))
    <div class="cp-row" style="margin-top:8px;">
        <span class="cp-label">Note</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ $visit->prescription_custom_notes }}</span>
    </div>
    @endif
</div>
@endif

{{-- ── Patient Instructions ── --}}
@if($showRemarks && count($instructions))
<div class="cp-section">
    <div class="cp-section-title">Patient Instructions</div>
    <ul class="cp-list">
        @foreach($instructions as $instr)
        <li>{{ is_array($instr) ? ($instr['text'] ?? reset($instr)) : $instr }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- ── Next Visit ── --}}
@if($showFollowup && $visit->next_visit_date)
<div class="cp-section">
    <div class="cp-section-title">Next Visit</div>
    <div class="cp-row">
        <span class="cp-label">Next Visit</span><span class="cp-colon">:</span>
        <span class="cp-value" style="font-weight:700;">{{ \Carbon\Carbon::parse($visit->next_visit_date)->format('d M Y') }}@if($visit->next_visit_type) — {{ ucfirst($visit->next_visit_type) }}@endif</span>
    </div>
</div>
@endif

{{-- ── Signature ── --}}
<div class="sig">
    @if($clinic['clinic_name'] ?? false)<div class="sig-for">For {{ $clinic['clinic_name'] }}</div>@endif
    <div class="sig-line"></div>
    <div class="sig-name">{{ $doctor?->doctor_name ?? '' }}</div>
    @if($doctor?->designation ?? null)<div class="sig-sub">{{ $doctor->designation }}</div>@endif
    @if($doctor?->registration_number ?? null)<div class="sig-sub">Reg. No.: {{ $doctor->registration_number }}</div>@endif
</div>

{{-- ── Footer ── --}}
<div class="doc-footer">
    <span>{{ $clinic['clinic_name'] ?? '' }}@if($clinic['clinic_phone'] ?? false) · {{ $clinic['clinic_phone'] }}@endif</span>
    <span style="white-space:nowrap;margin-left:16px;">Printed: {{ now()->format('d M Y, h:i A') }}</span>
</div>

</body>
</html>
