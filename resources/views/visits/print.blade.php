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
    @php $pm = \App\Models\AppSetting::printMargins(['top' => '16mm', 'bottom' => '16mm', 'left' => '14mm', 'right' => '14mm']); @endphp

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
            body { padding: {{ $pm['top'] }} {{ $pm['right'] }} {{ $pm['bottom'] }} {{ $pm['left'] }}; max-width: none; margin: 0; }
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

    // ── Parity pass (desktop parity sprint): stage keys → human labels,
    //    same source (allStagesFromDb) the on-screen visit card already
    //    reads via TV_STAGES, so a stage reads the same word-for-word on
    //    screen and on the printout instead of a raw machine key. ──
    $stageLabels    = \App\Models\TreatmentVisit::allStagesFromDb()[$visit->treatment_name] ?? [];
    $currentStageLabel = $stageLabels[$visit->current_stage] ?? $visit->current_stage;

    // ── Status label — Visit Details previously omitted this entirely,
    //    even though it's a core field on every other surface. ──
    $statusLabel = $visit->status ? ucwords(str_replace('_', ' ', $visit->status)) : null;

    // ── Vitals — same fields the on-screen visit card and the Edit form
    //    already capture; the case sheet silently omitted them. Only
    //    shown when at least one was recorded (same "no empty rows" rule
    //    as the rest of this document). ──
    $hasVitals = filled($visit->bp_systolic) || filled($visit->bp_diastolic) || filled($visit->pulse_rate)
        || filled($visit->spo2) || filled($visit->temperature) || filled($visit->blood_sugar)
        || filled($visit->weight) || filled($visit->vitals_notes);

    // ── Billing Summary — mirrors the cost/amount_paid/balance_due the
    //    on-screen visit card already shows; the case sheet previously
    //    showed no financial summary at all. Only shown once something
    //    has actually been billed (cost > 0), same "no empty rows" rule.
    //    Payment status/receipts remain the invoice's job — this is a
    //    one-line summary, not a duplicate of the Billing tab. ──
    $visitCost      = (float) ($visit->cost ?? 0);
    $visitPaid      = (float) ($visit->amount_paid ?? 0);
    $visitBalance   = max(0, $visitCost - $visitPaid);
    $hasBillingInfo = $visitCost > 0;

    // ── Procedure Worksheet details — the clinical specifics captured in
    //    the per-treatment worksheet (RCT / Implant / Filling / Scaling /
    //    Extraction / Crown Prep) previously never reached the case sheet
    //    at all. Rendered only for the treatment actually recorded, and
    //    only the fields that were actually filled in — same pattern as
    //    every other section on this page. ──
    $implantPlacement = $visit->implantPlacement; // lazy-loaded on first access, single-record page — no N+1
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
    @if($statusLabel)
    <div class="cp-row">
        <span class="cp-label">Status</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ $statusLabel }}</span>
    </div>
    @endif
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
        <span class="cp-value">{{ $currentStageLabel }}</span>
    </div>
    @endif
    @if(!empty($visit->completed_stages))
    <div class="cp-row">
        <span class="cp-label">Completed</span><span class="cp-colon">:</span>
        <span class="cp-value"><span class="cp-chips">@foreach((array) $visit->completed_stages as $st)<span class="cp-chip">{{ $stageLabels[$st] ?? $st }}</span>@endforeach</span></span>
    </div>
    @endif
</div>
@endif

{{-- ── Procedure Worksheet — desktop parity sprint: the clinical specifics
     captured in the per-treatment worksheet on Create/Edit (and already
     kept in the treatment_visits row) previously never reached the case
     sheet. Only the block matching this visit's treatment_name renders,
     and only the fields actually filled in — same "no empty rows" rule
     as the rest of this document. --}}
@if($showTreat && $visit->treatment_name === 'RCT' && (filled($visit->rct_num_canals) || filled($visit->rct_file_type) || filled($visit->rct_irrigant) || filled($visit->rct_obturation_method) || !empty($visit->rct_canal_lengths)))
<div class="cp-section">
    <div class="cp-section-title">Procedure Worksheet — RCT</div>
    @if(filled($visit->rct_num_canals))
    <div class="cp-row"><span class="cp-label">Canals</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->rct_num_canals }}</span></div>
    @endif
    @if(!empty($visit->rct_canal_lengths))
    <div class="cp-row"><span class="cp-label">Working Lengths</span><span class="cp-colon">:</span><span class="cp-value">{{ collect($visit->rct_canal_lengths)->filter()->implode(', ') }}</span></div>
    @endif
    @if(filled($visit->rct_file_type))
    <div class="cp-row"><span class="cp-label">File Type</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->rct_file_type }}</span></div>
    @endif
    @if(filled($visit->rct_irrigant))
    <div class="cp-row"><span class="cp-label">Irrigant</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->rct_irrigant }}</span></div>
    @endif
    @if(filled($visit->rct_obturation_method))
    <div class="cp-row"><span class="cp-label">Obturation</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->rct_obturation_method }}</span></div>
    @endif
</div>
@endif

@if($showTreat && $visit->treatment_name === 'Implant' && (filled($visit->impl_brand) || filled($visit->impl_size) || filled($visit->impl_torque) || filled($visit->impl_graft_used) || filled($visit->impl_membrane) || filled($visit->impl_healing_collar) || $implantPlacement))
<div class="cp-section">
    <div class="cp-section-title">Procedure Worksheet — Implant</div>
    @if($implantPlacement?->catalogItem?->getFullName())
    <div class="cp-row"><span class="cp-label">Fixture</span><span class="cp-colon">:</span><span class="cp-value">{{ $implantPlacement->catalogItem->getFullName() }}</span></div>
    @elseif(filled($visit->impl_brand) || filled($visit->impl_size))
    <div class="cp-row"><span class="cp-label">Fixture</span><span class="cp-colon">:</span><span class="cp-value">{{ trim(($visit->impl_brand ?? '').' '.($visit->impl_size ?? '')) }}</span></div>
    @endif
    @if(filled($visit->impl_torque))
    <div class="cp-row"><span class="cp-label">Insertion Torque</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->impl_torque }}</span></div>
    @endif
    @if(filled($visit->implant_lot_number ?? $implantPlacement?->lot_number))
    <div class="cp-row"><span class="cp-label">Lot Number</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->implant_lot_number ?? $implantPlacement?->lot_number }}</span></div>
    @endif
    @if(filled($visit->impl_graft_used))
    <div class="cp-row"><span class="cp-label">Graft</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->impl_graft_used }}{{ $visit->impl_graft_brand ? ' — '.$visit->impl_graft_brand : '' }}</span></div>
    @endif
    @if(filled($visit->impl_membrane))
    <div class="cp-row"><span class="cp-label">Membrane</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->impl_membrane }}</span></div>
    @endif
    @if(filled($visit->impl_healing_collar))
    <div class="cp-row"><span class="cp-label">Healing Collar</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->impl_healing_collar }}</span></div>
    @endif
</div>
@endif

@if($showTreat && $visit->treatment_name === 'Filling' && (filled($visit->fill_material) || filled($visit->fill_shade)))
<div class="cp-section">
    <div class="cp-section-title">Procedure Worksheet — Filling</div>
    @if(filled($visit->fill_material))
    <div class="cp-row"><span class="cp-label">Material</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->fill_material }}</span></div>
    @endif
    @if(filled($visit->fill_shade))
    <div class="cp-row"><span class="cp-label">Shade</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->fill_shade }}</span></div>
    @endif
</div>
@endif

@if($showTreat && $visit->treatment_name === 'Scaling' && (filled($visit->scale_quadrants) || filled($visit->scale_method)))
<div class="cp-section">
    <div class="cp-section-title">Procedure Worksheet — Scaling</div>
    @if(filled($visit->scale_quadrants))
    <div class="cp-row"><span class="cp-label">Quadrants</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->scale_quadrants }}</span></div>
    @endif
    @if(filled($visit->scale_method))
    <div class="cp-row"><span class="cp-label">Method</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->scale_method }}</span></div>
    @endif
</div>
@endif

@if($showTreat && $visit->treatment_name === 'Extraction' && (filled($visit->ext_type) || filled($visit->ext_socket) || $visit->ext_suture))
<div class="cp-section">
    <div class="cp-section-title">Procedure Worksheet — Extraction</div>
    @if(filled($visit->ext_type))
    <div class="cp-row"><span class="cp-label">Type</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->ext_type }}</span></div>
    @endif
    @if(filled($visit->ext_socket))
    <div class="cp-row"><span class="cp-label">Socket Management</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->ext_socket }}</span></div>
    @endif
    @if($visit->ext_suture)
    <div class="cp-row"><span class="cp-label">Suture Placed</span><span class="cp-colon">:</span><span class="cp-value">Yes</span></div>
    @endif
</div>
@endif

@if($showTreat && $visit->treatment_name === 'Crown Prep' && (filled($visit->crown_type) || filled($visit->crown_shade) || $visit->crown_impression || filled($visit->crown_temp_placed)))
<div class="cp-section">
    <div class="cp-section-title">Procedure Worksheet — Crown Prep</div>
    @if(filled($visit->crown_type))
    <div class="cp-row"><span class="cp-label">Type</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->crown_type }}</span></div>
    @endif
    @if(filled($visit->crown_shade))
    <div class="cp-row"><span class="cp-label">Shade</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->crown_shade }}</span></div>
    @endif
    @if($visit->crown_impression)
    <div class="cp-row"><span class="cp-label">Impression Taken</span><span class="cp-colon">:</span><span class="cp-value">Yes</span></div>
    @endif
    @if(filled($visit->crown_temp_placed))
    <div class="cp-row"><span class="cp-label">Temporary Placed</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->crown_temp_placed }}</span></div>
    @endif
</div>
@endif

{{-- ── Vitals — desktop parity sprint: same fields the on-screen visit
     card and Edit form already capture; the case sheet previously
     omitted them entirely. Only shown when at least one was recorded. ── --}}
@if($showNotes && $hasVitals)
<div class="cp-section">
    <div class="cp-section-title">Vitals</div>
    @if(filled($visit->bp_systolic) || filled($visit->bp_diastolic))
    <div class="cp-row"><span class="cp-label">Blood Pressure</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->bp_systolic ?: '—' }}/{{ $visit->bp_diastolic ?: '—' }} mmHg</span></div>
    @endif
    @if(filled($visit->pulse_rate))
    <div class="cp-row"><span class="cp-label">Pulse</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->pulse_rate }} bpm</span></div>
    @endif
    @if(filled($visit->spo2))
    <div class="cp-row"><span class="cp-label">SpO&#8322;</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->spo2 }}%</span></div>
    @endif
    @if(filled($visit->temperature))
    <div class="cp-row"><span class="cp-label">Temperature</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->temperature }}&deg;C</span></div>
    @endif
    @if(filled($visit->blood_sugar))
    <div class="cp-row"><span class="cp-label">Blood Sugar</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->blood_sugar }}{{ $visit->blood_sugar_type ? ' ('.ucfirst($visit->blood_sugar_type).')' : '' }}</span></div>
    @endif
    @if(filled($visit->weight))
    <div class="cp-row"><span class="cp-label">Weight</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->weight }} kg</span></div>
    @endif
    @if(filled($visit->vitals_notes))
    <div class="cp-row"><span class="cp-label">Note</span><span class="cp-colon">:</span><span class="cp-value">{{ $visit->vitals_notes }}</span></div>
    @endif
</div>
@endif

{{-- ── Procedures (billing line items) — closure sprint (08-05): the case
     sheet previously omitted what was actually billed on this visit. ── --}}
@if($showTreat && $visit->visitItems->isNotEmpty())
<div class="cp-section">
    <div class="cp-section-title">Procedures</div>
    <table class="cp-table">
        <thead>
            <tr>
                <th style="width:24px;">#</th>
                <th>Treatment</th>
                <th>Tooth</th>
                <th>Material / Option</th>
            </tr>
        </thead>
        <tbody>
            @foreach($visit->visitItems as $i => $item)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $item->treatment_name ?? '—' }}@if($item->is_repeat) <span class="cp-chip" style="font-size:9px;padding:1px 6px;">Repeat</span>@endif</td>
                <td>{{ $item->tooth_number ?: '—' }}</td>
                <td>{{ $item->material_option ?: '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ── Lab Case — closure sprint (08-05): case sheet previously omitted
     any lab work ordered on this visit. ── --}}
@if($showTreat && $visit->labCases->isNotEmpty())
<div class="cp-section">
    <div class="cp-section-title">Lab Case</div>
    @foreach($visit->labCases as $lab)
    <div class="cp-row">
        <span class="cp-label">{{ $lab->work_category ?? 'Lab Work' }}</span><span class="cp-colon">:</span>
        <span class="cp-value">
            {{ $lab->work_subtype ?: '—' }}
            @if($lab->vendor?->name) — {{ $lab->vendor->name }} @endif
            @if($lab->expected_return_date) (expected {{ \Carbon\Carbon::parse($lab->expected_return_date)->format('d M Y') }}) @endif
        </span>
    </div>
    @endforeach
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

{{-- ── Billing Summary — desktop parity sprint: mirrors the cost/paid/due
     figures the on-screen visit card already shows; the case sheet
     previously carried no financial summary at all. A one-line summary
     only — itemised billing/receipts remain the invoice's job. ── --}}
@if($showTreat && $hasBillingInfo)
<div class="cp-section">
    <div class="cp-section-title">Billing Summary</div>
    <div class="cp-row">
        <span class="cp-label">Visit Cost</span><span class="cp-colon">:</span>
        <span class="cp-value">Rs. {{ number_format($visitCost, 2) }}</span>
    </div>
    <div class="cp-row">
        <span class="cp-label">Amount Paid</span><span class="cp-colon">:</span>
        <span class="cp-value">Rs. {{ number_format($visitPaid, 2) }}</span>
    </div>
    @if($visitBalance > 0)
    <div class="cp-row">
        <span class="cp-label">Balance Due</span><span class="cp-colon">:</span>
        <span class="cp-value" style="font-weight:700;">Rs. {{ number_format($visitBalance, 2) }}</span>
    </div>
    @endif
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
