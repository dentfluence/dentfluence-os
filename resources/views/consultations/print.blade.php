<!DOCTYPE html>
{{--
    Patient take-home "Case Paper".
    Self-contained print page (no app shell).
    Shows ONLY fields that are filled — no empty "—" rows.
    Accent colours follow the personalisation scheme (localStorage df_prefs),
    matching the treatment-plan print.
--}}
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Case Paper — {{ $consultation->patient->name ?? 'Patient' }}</title>
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
        .lh-right { text-align: right; }
        .lh-doc   { font-size: 15px; font-weight: 700; color: var(--accent-dark); }
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

        /* ── Chips (medical history / teeth) ── */
        .cp-chips { display: flex; flex-wrap: wrap; gap: 6px; }
        .cp-chip {
            font-size: 11px; font-weight: 600; padding: 2px 10px;
            border-radius: 12px; border: 1px solid var(--accent-light);
            background: var(--accent-light); color: var(--accent-dark);
        }
        .cp-chip.alert { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }
        .cp-pill {
            display: inline-block; padding: 2px 12px; border-radius: 20px;
            font-size: 11px; font-weight: 700;
        }

        /* ── Embedded prescription (Rx) table ── */
        table.rx-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.rx-table th {
            font-size: 9px; text-transform: uppercase; letter-spacing: .4px;
            color: var(--accent-dark); background: var(--accent-light);
            padding: 5px 8px; text-align: left;
            border-bottom: 1px solid var(--accent-light);
        }
        table.rx-table td {
            font-size: 11.5px; padding: 6px 8px;
            border-bottom: 1px solid #f1f5f9; vertical-align: top;
        }
        table.rx-table tr:last-child td { border-bottom: none; }
        table.rx-table .rx-drug { font-weight: 600; color: #111; }
        table.rx-table .rx-sub  { font-size: 9.5px; color: #666; margin-top: 1px; }

        /* ── Smart Presentation QR (only when a live link exists) ── */
        .qr-box {
            display: flex; align-items: center; gap: 8px; margin-top: 6px;
        }
        .qr-box img { width: 48px; height: 48px; flex-shrink: 0; }
        .qr-box span { font-size: 9.5px; color: #666; line-height: 1.3; }

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
            .cp-chip, .cp-pill, .cp-section-title, table.rx-table th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

@php
    $patient = $consultation->patient;
    $doctor  = $consultation->doctor;

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

    $caseDate = $consultation->consultation_date
                ? \Carbon\Carbon::parse($consultation->consultation_date)->format('d M Y')
                : ($consultation->created_at?->format('d M Y') ?? now()->format('d M Y'));

    // ── Helper: normalise an array of strings / {name:...} into a clean list ──
    $listify = function ($val) {
        return collect((array) $val)
            ->map(fn($v) => is_array($v) ? ($v['name'] ?? reset($v)) : $v)
            ->filter(fn($v) => filled($v))
            ->values();
    };

    $allergies = $listify($patient->allergies ?? []);
    $medConds  = $listify($patient->medical_conditions ?? []);
    $medAlert  = $patient->medical_alert ?? null;
    $medsText  = $patient->current_medications ?? null;
    $hasMedHistory = $medAlert || $allergies->count() || $medConds->count() || filled($medsText);

    // ── Chief complaint sub-details that are actually filled ──
    $complaintDetails = collect([
        'Duration' => $consultation->complaint_duration,
        'Severity' => $consultation->severity,
        'Tooth Area' => $consultation->tooth_area,
        'Location'   => $consultation->location,
    ])->filter(fn($v) => filled($v));

    // ── Diagnosis bits that are filled ──
    // Risk is captured elsewhere in the app (patient risk assessment) but isn't
    // part of the take-home Case Paper — dropped from $hasDiagnosis too so an
    // empty section doesn't render if risk was the only thing set.
    $hasDiagnosis = filled($consultation->primary_diagnosis)
                 || filled($consultation->secondary_diagnosis)
                 || filled($consultation->provisional_diagnosis)
                 || filled($consultation->diagnosis_notes);

    // ── Investigations advised (compact) ──
    $invLabels = [
        'iopa'=>'IOPA (Dental X-Ray)', 'opg'=>'OPG', 'cbct'=>'CBCT', 'rvg'=>'RVG',
        'ceph'=>'Lateral Cephalogram', 'cbc'=>'CBC', 'blood_sugar'=>'Blood Sugar',
        'hba1c'=>'HbA1c', 'pt_inr'=>'PT / INR', 'hiv'=>'HIV', 'hbsag'=>'HBsAg',
        'thyroid'=>'Thyroid Profile', 'mri'=>'MRI', 'usg'=>'USG', 'ct'=>'CT Scan',
        'biopsy'=>'Biopsy', 'photographs'=>'Photographs', 'intraoral'=>'Intraoral Scan',
    ];
    $investigations = array_values(array_filter((array) ($consultation->investigations ?? [])));
    $invDetails     = (array) ($consultation->investigation_details ?? []);

    // ── Treatment to be done ──
    $txText = $consultation->treatment_plan_note ?: $consultation->treatment_done;
    $adviceText = $consultation->advice;
    $linkedPlans = $consultation->treatmentPlans ?? collect();
    $hasTreatment = filled($txText) || filled($adviceText) || $linkedPlans->isNotEmpty();
@endphp

{{-- ── Screen action bar ── --}}
<div class="no-print">
    <span>Case Paper — {{ $patient->name ?? '—' }}</span>
    <div>
        <button class="btn btn-print" onclick="window.print()">Print / Save as PDF</button>
        <a class="btn" href="{{ route('patients.show', $patient->id) }}#consultation">← Back</a>
    </div>
</div>

{{-- ── Configurable print header (Settings → Print) ── --}}
@php
    $print      = \App\Models\AppSetting::group('print');
    $clinic     = $clinic ?? \App\Models\AppSetting::group('clinic');
    $headerType = $print['print_header_type'] ?? 'plain';
    // Plain paper = pre-printed stationery that already carries the clinic
    // logo/name/address — so we must NOT print clinic identity again here.
    $showClinic = $headerType !== 'plain';
@endphp
@include('partials.print-letterhead')

{{-- ── Letterhead ── --}}
<div class="lh-top-rule"></div>
<div class="lh">
    {{-- Patient (left) --}}
    <div>
        <div class="lh-name">{{ $patient->name ?? '—' }} ({{ $patientCode }})</div>
        @if($genderAge)<div class="lh-line">{{ $genderAge }}</div>@endif
        @if($addressLine)<div class="lh-line">{{ $addressLine }}</div>@endif
    </div>

    {{-- Doctor / clinic (right). On plain paper the clinic identity is already
         on the pre-printed sheet, so we only fall back to clinic name otherwise. --}}
    <div class="lh-right">
        <div class="lh-doc">{{ $doctor?->doctor_name ?? ($showClinic ? ($clinic['clinic_name'] ?? '') : '') }}</div>
        @if($doctor?->designation ?? null)<div class="lh-line">{{ $doctor->designation }}</div>@endif
        @if($doctor?->registration_number ?? null)<div class="lh-line">Registration No.: {{ $doctor->registration_number }}</div>@endif
    </div>
</div>

{{-- ── Title + date ── --}}
<div class="doc-title">Case Paper</div>
<div class="doc-rule"></div>
<div class="doc-date"><strong>Date:</strong> {{ $caseDate }}</div>

{{-- ── Medical History ── --}}
@if($hasMedHistory)
<div class="cp-section">
    <div class="cp-section-title">Medical History</div>
    @if($medAlert)
    <div class="cp-row">
        <span class="cp-label">Alert</span><span class="cp-colon">:</span>
        <span class="cp-value"><span class="cp-pill" style="background:#fee2e2;color:#991b1b;">{{ $medAlert }}</span></span>
    </div>
    @endif
    @if($allergies->count())
    <div class="cp-row">
        <span class="cp-label">Allergies</span><span class="cp-colon">:</span>
        <span class="cp-value"><span class="cp-chips">@foreach($allergies as $a)<span class="cp-chip alert">{{ $a }}</span>@endforeach</span></span>
    </div>
    @endif
    @if($medConds->count())
    <div class="cp-row">
        <span class="cp-label">Conditions</span><span class="cp-colon">:</span>
        <span class="cp-value"><span class="cp-chips">@foreach($medConds as $c)<span class="cp-chip">{{ $c }}</span>@endforeach</span></span>
    </div>
    @endif
    @if(filled($medsText))
    <div class="cp-row">
        <span class="cp-label">Medications</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ $medsText }}</span>
    </div>
    @endif
</div>
@endif

{{-- ── Chief Complaint ── --}}
@if(filled($consultation->chief_complaint))
<div class="cp-section">
    <div class="cp-section-title">Chief Complaint</div>
    <div class="cp-row">
        <span class="cp-label">Complaint</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ $consultation->chief_complaint }}</span>
    </div>
    @foreach($complaintDetails as $label => $value)
    <div class="cp-row">
        <span class="cp-label">{{ $label }}</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ $value }}</span>
    </div>
    @endforeach
    @if(filled($consultation->complaint_notes))
    <div class="cp-row">
        <span class="cp-label">Notes</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ $consultation->complaint_notes }}</span>
    </div>
    @endif
</div>
@endif

{{-- ── History of Present Illness ── --}}
@if(filled($consultation->hopi_final))
<div class="cp-section">
    <div class="cp-section-title">History of Present Illness</div>
    <div class="cp-value" style="font-size:12.5px;">{{ $consultation->hopi_final }}</div>
</div>
@endif

{{-- ── Findings Summary (if recorded) ── --}}
@if(filled($consultation->findings_summary_final))
<div class="cp-section">
    <div class="cp-section-title">Examination Findings</div>
    <div class="cp-value" style="font-size:12.5px;">{{ $consultation->findings_summary_final }}</div>
</div>
@endif

{{-- ── Provisional Diagnosis (the only diagnosis type actually used) ── --}}
@if($hasDiagnosis)
<div class="cp-section">
    <div class="cp-section-title">Provisional Diagnosis</div>
    @if(filled($consultation->primary_diagnosis))
    <div class="cp-row">
        <span class="cp-label">Primary</span><span class="cp-colon">:</span>
        <span class="cp-value" style="font-weight:600;">{{ $consultation->primary_diagnosis }}</span>
    </div>
    @endif
    @if(filled($consultation->provisional_diagnosis))
    <div class="cp-value" style="font-size:12.5px;">{{ $consultation->provisional_diagnosis }}</div>
    @endif
    @if(filled($consultation->secondary_diagnosis))
    <div class="cp-row">
        <span class="cp-label">Secondary</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ $consultation->secondary_diagnosis }}</span>
    </div>
    @endif
    @if(filled($consultation->diagnosis_notes))
    <div class="cp-row">
        <span class="cp-label">Notes</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ $consultation->diagnosis_notes }}</span>
    </div>
    @endif
</div>
@endif

{{-- ── Investigations Advised ── --}}
@if(count($investigations))
<div class="cp-section">
    <div class="cp-section-title">Investigations Advised</div>
    @foreach($investigations as $key)
    @php
        $label  = $invLabels[$key] ?? ucwords(str_replace('_', ' ', $key));
        $detail = $invDetails[$key] ?? null;
    @endphp
    <div class="cp-value" style="font-size:12.5px;">{{ $label }}@if($detail && $detail !== '✓') — {{ $detail }}@endif</div>
    @endforeach
</div>
@endif

{{-- ── Treatment To Be Done ── --}}
@if($hasTreatment)
<div class="cp-section">
    <div class="cp-section-title">Treatment Advised</div>
    @if(filled($txText))
    <div class="cp-value" style="font-size:12.5px;margin-bottom:6px;">{{ $txText }}</div>
    @endif
    @if(!filled($txText) && $linkedPlans->isNotEmpty())
        @foreach($linkedPlans as $plan)
        <div class="cp-row">
            <span class="cp-label">{{ $plan->plan_name }}</span><span class="cp-colon">:</span>
            <span class="cp-value">{{ $plan->items->map(fn($i) => trim(($i->tooth_number ? $i->tooth_number.' ' : '').$i->treatment_name))->implode(', ') }}</span>
        </div>
        @endforeach
    @endif
    @if(filled($adviceText))
    <div class="cp-row">
        <span class="cp-label">Advice</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ $adviceText }}</span>
    </div>
    @endif
    @foreach($linkedPlans as $plan)
        @if($plan->presentation_qr ?? null)
        <div class="qr-box">
            <img src="{{ $plan->presentation_qr }}" alt="QR code">
            <span>Scan to view {{ $plan->plan_name }} online, with cost breakdown and payment options.</span>
        </div>
        @endif
    @endforeach
</div>
@endif

{{-- ── Prescription (embedded — saves a separate Rx sheet when one exists) ── --}}
@if(isset($prescription) && $prescription && $prescription->items && $prescription->items->count())
<div class="cp-section">
    <div class="cp-section-title">Prescription &nbsp;<span style="font-weight:600;color:#777;">{{ $prescription->prescription_number }}</span></div>
    <table class="rx-table">
        <thead>
            <tr>
                <th style="width:18px;">#</th>
                <th>Drug / Strength</th>
                <th style="text-align:center;width:32px;">SOS</th>
                <th style="text-align:center;width:38px;">Morn</th>
                <th style="text-align:center;width:38px;">Noon</th>
                <th style="text-align:center;width:38px;">Night</th>
                <th style="width:62px;">Duration</th>
            </tr>
        </thead>
        <tbody>
            @foreach($prescription->items as $i => $item)
            <tr>
                <td style="color:#94a3b8;">{{ $i + 1 }}.</td>
                <td>
                    <span class="rx-drug">{{ $item->drug_name ?: ($item->drug?->brand_name ?? '—') }}</span>
                    @if($item->strength)<div class="rx-sub">{{ $item->strength }}{{ $item->dosage_form ? ' · '.$item->dosage_form : '' }}</div>@endif
                    @if($item->food_advice || $item->instructions)<div class="rx-sub">@if($item->food_advice){{ $item->food_advice }}@endif @if($item->instructions)· {{ $item->instructions }}@endif</div>@endif
                </td>
                <td style="text-align:center;">{{ $item->is_sos ? 'SOS' : '—' }}</td>
                <td style="text-align:center;">{{ $item->morning ?: '—' }}</td>
                <td style="text-align:center;">{{ $item->afternoon ?: '—' }}</td>
                <td style="text-align:center;">{{ $item->night ?: '—' }}</td>
                <td>{{ $item->duration ? $item->duration . ' ' . ($item->duration_unit ?? 'days') : '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @if($prescription->general_instructions)
    <div class="cp-row" style="margin-top:7px;">
        <span class="cp-label">Instructions</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ $prescription->general_instructions }}</span>
    </div>
    @endif
</div>
@endif

{{-- ── Next Follow-up Visit ── --}}
@php
    $followUpDate  = $consultation->follow_up_date ?: $consultation->next_visit_date;
    $nextVisitType = $consultation->next_visit_type;
    $followUpNote  = $consultation->follow_up_note;
    $hasFollowUp   = $followUpDate || filled($nextVisitType) || filled($followUpNote);
@endphp
@if($hasFollowUp)
<div class="cp-section">
    <div class="cp-section-title">Next Follow-up Visit</div>
    @if($followUpDate)
    <div class="cp-row">
        <span class="cp-label">Next Visit Date</span><span class="cp-colon">:</span>
        <span class="cp-value" style="font-weight:600;">{{ \Carbon\Carbon::parse($followUpDate)->format('d M Y') }}</span>
    </div>
    @endif
    @if(filled($nextVisitType))
    <div class="cp-row">
        <span class="cp-label">Visit For</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ ucwords(str_replace('_', ' ', $nextVisitType)) }}</span>
    </div>
    @endif
    @if(filled($followUpNote))
    <div class="cp-row">
        <span class="cp-label">Note</span><span class="cp-colon">:</span>
        <span class="cp-value">{{ $followUpNote }}</span>
    </div>
    @endif
</div>
@endif

{{-- ── Signature ── --}}
<div class="sig">
    @if($showClinic && ($clinic['clinic_name'] ?? false))<div class="sig-for">For {{ $clinic['clinic_name'] }}</div>@endif
    <div class="sig-line"></div>
    <div class="sig-name">{{ $doctor?->doctor_name ?? '' }}</div>
    @if($doctor?->designation ?? null)<div class="sig-sub">{{ $doctor->designation }}</div>@endif
    @if($doctor?->registration_number ?? null)<div class="sig-sub">Reg. No.: {{ $doctor->registration_number }}</div>@endif
</div>

{{-- ── Footer (clinic identity only — hidden entirely on plain / pre-printed
     stationery, and dropped altogether if there's no clinic name to show) ── --}}
@if($showClinic && ($clinic['clinic_name'] ?? false))
<div class="doc-footer">
    <span>{{ $clinic['clinic_name'] }}@if($clinic['clinic_phone'] ?? false) · {{ $clinic['clinic_phone'] }}@endif</span>
</div>
@endif

</body>
</html>
