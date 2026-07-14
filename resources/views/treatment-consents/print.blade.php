<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consent Form — {{ $patient->name ?? 'Patient' }}</title>
    @php $pm = \App\Models\AppSetting::printMargins(['top' => '14mm', 'bottom' => '14mm', 'left' => '14mm', 'right' => '14mm']); @endphp

    {{-- ── Apply personalisation colour scheme (same source as the app: localStorage df_prefs) ── --}}
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
        :root {
            --accent:       #6a0f70;
            --accent-dark:  #3a0050;
            --accent-light: #f7eef8;
        }

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

        .lh-top-rule { border-top: 1px solid #d8d8d8; margin-bottom: 14px; }
        .lh {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 24px;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--accent);
            margin-bottom: 4px;
        }
        .lh-name   { font-size: 16px; font-weight: 700; color: #111; }
        .lh-line   { font-size: 12px; color: #444; margin-top: 3px; line-height: 1.5; }
        .lh-right  { text-align: right; }
        .lh-doc    { font-size: 15px; font-weight: 700; color: var(--accent-dark); }

        .doc-title {
            text-align: center;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--accent-dark);
            margin: 20px 0 20px;
        }

        .section {
            border: 1px solid #e5e5e5;
            border-radius: 6px;
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .section-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--accent-light);
            border-bottom: 1px solid #e5e5e5;
            padding: 8px 14px;
        }
        .section-name { font-size: 12.5px; font-weight: 700; color: var(--accent-dark); }
        .tooth-badges { display: flex; gap: 4px; }
        .tooth-badge {
            font-size: 10.5px; font-weight: 700; color: var(--accent-dark);
            background: #fff; border: 1px solid var(--accent);
            border-radius: 4px; padding: 1px 6px;
        }
        .section-body { padding: 12px 14px; font-size: 11.5px; color: #333; line-height: 1.7; white-space: pre-line; }
        .section-missing { font-size: 11px; color: #999; font-style: italic; }

        .ack {
            margin-top: 22px;
            border: 1px solid var(--accent-light);
            border-left: 3px solid var(--accent);
            background: var(--accent-light);
            border-radius: 6px;
            padding: 12px 14px;
            font-size: 11px;
            color: #333;
            line-height: 1.7;
        }
        .ack strong { color: var(--accent-dark); }

        .sig-row { display: flex; justify-content: space-between; margin-top: 48px; gap: 24px; }
        .sig-block { flex: 1; text-align: center; }
        .sig-line { border-top: 1px solid #333; margin-bottom: 6px; }
        .sig-label { font-size: 11px; color: #666; }

        .doc-footer {
            margin-top: 24px; padding-top: 10px;
            border-top: 1px solid #ddd;
            display: flex; justify-content: space-between;
            font-size: 10px; color: #888;
        }

        @media print {
            .no-print { display: none !important; }
            body { padding: {{ $pm['top'] }} {{ $pm['right'] }} {{ $pm['bottom'] }} {{ $pm['left'] }}; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <span>Consent Form — {{ $patient->name ?? '—' }}</span>
    <div>
        <button class="btn btn-print" onclick="window.print()">Print / Save as PDF</button>
        <a class="btn" href="{{ route('patients.show', $patient->id) }}#treatment-plan">← Back</a>
    </div>
</div>

@php
    use App\Models\AppSetting;

    $clinic     = AppSetting::group('clinic');
    $print      = AppSetting::group('print');
    $headerType = $print['print_header_type'] ?? 'plain';
    $showClinic = $headerType !== 'plain';
    // Plan's own treating doctor wins; fall back to the consultation's doctor.
    $doctor     = $plan?->doctor ?? $consultation?->doctor;

    $patientCode = $patient?->patient_id
                   ?? ('P-' . str_pad($patient?->id ?? 0, 5, '0', STR_PAD_LEFT));

    $genderAge = trim(implode(' / ', array_filter([
        ucfirst($patient->gender ?? ''),
        $patient->age ?? null,
    ])), ' /');
@endphp

@include('partials.print-letterhead')

<div class="lh-top-rule"></div>
<div class="lh">
    <div>
        <div class="lh-name">{{ $patient->name ?? '—' }} ({{ $patientCode }})</div>
        @if($genderAge)<div class="lh-line">{{ $genderAge }}</div>@endif
        @if($patient->phone ?? null)<div class="lh-line">{{ $patient->phone }}</div>@endif
        {{-- Blank fill-in, same convention as the signature lines below — not
             auto-filled from the DPDP consent record, since whoever actually
             brings the minor in to sign may not match whatever's on file. --}}
        @if($patient->isMinor())
        <div class="lh-line" style="margin-top:4px;">Guardian Name: <span style="display:inline-block; width:170px; border-bottom:1px solid #999;">&nbsp;</span></div>
        @endif
    </div>
    <div class="lh-right">
        <div class="lh-doc">{{ $doctor?->doctor_name ?? ($showClinic ? ($clinic['clinic_name'] ?? '') : '') }}</div>
        @if($doctor?->registration_number ?? null)<div class="lh-line">Registration No.: {{ $doctor->registration_number }}</div>@endif
    </div>
</div>

<div class="doc-title">Treatment Consent Form</div>

@forelse($sections as $section)
    <div class="section">
        <div class="section-head">
            <span class="section-name">{{ $section['treatment_name'] }}</span>
            @if(!empty($section['tooth_numbers']))
                <div class="tooth-badges">
                    @foreach($section['tooth_numbers'] as $tooth)
                        <span class="tooth-badge">{{ $tooth }}</span>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="section-body">
            @if($section['has_consent_text'])
                {{ $section['consent_text'] }}
            @else
                <span class="section-missing">No consent information has been added for this treatment yet. Please add it under Treatment Library → Consent Information.</span>
            @endif
        </div>
    </div>
@empty
    <div class="section-missing">No procedure on this plan is currently flagged as requiring consent.</div>
@endforelse

<div class="ack">
    <strong>Acknowledgement:</strong> I confirm that the above procedure(s), their risks, benefits, and
    alternatives have been explained to me in a language I understand, that all my questions have been
    answered, and that I voluntarily consent to proceed with the treatment(s) described above.
</div>

<div class="sig-row">
    <div class="sig-block">
        <div class="sig-line"></div>
        <div class="sig-label">Patient / Guardian Signature &amp; Date</div>
    </div>
    <div class="sig-block">
        <div class="sig-line"></div>
        <div class="sig-label">Doctor Signature &amp; Date</div>
    </div>
</div>

<div class="doc-footer">
    <span>Generated {{ $generatedAt->format('d M Y, h:i A') }}</span>
    <span>@if($showClinic){{ $clinic['clinic_name'] ?? '' }}@if($clinic['clinic_phone'] ?? false) · {{ $clinic['clinic_phone'] }}@endif @endif</span>
</div>

</body>
</html>
