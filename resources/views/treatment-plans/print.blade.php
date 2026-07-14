<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Treatment Plan — {{ $patient->name ?? 'Patient' }}</title>
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
        /* ── Accent defaults (overridden above by the selected scheme) ── */
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

        /* ── Letterhead: patient (left) + doctor (right) ── */
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
        .lh-name   { font-size: 16px; font-weight: 700; color: #111; }
        .lh-line   { font-size: 12px; color: #444; margin-top: 3px; line-height: 1.5; }
        .lh-right  { text-align: right; }
        .lh-doc    { font-size: 15px; font-weight: 700; color: var(--accent-dark); }
        .lh-logo   { max-height: 48px; max-width: 150px; object-fit: contain; margin-bottom: 6px; }

        /* ── Document title ── */
        .doc-title {
            text-align: center;
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--accent-dark);
            margin: 20px 0 6px;
        }
        .doc-date { font-size: 12px; color: #333; margin-bottom: 18px; }
        .doc-date strong { color: #111; }

        /* ── Option heading (only shown when >1 option) ── */
        .opt-head {
            font-size: 12px;
            font-weight: 700;
            color: var(--accent-dark);
            background: var(--accent-light);
            border-left: 3px solid var(--accent);
            padding: 6px 12px;
            margin: 22px 0 8px;
        }
        .opt-meta { font-size: 11px; color: #555; font-weight: 400; }

        /* ── Treatments label ── */
        .tx-label { font-size: 12px; font-weight: 700; color: #111; margin: 4px 0 6px; }

        /* ── Items table ── */
        table.tx-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        table.tx-table th {
            background: var(--accent);
            color: #fff;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .4px;
            padding: 8px 10px;
            text-align: left;
            border: 1px solid var(--accent);
        }
        table.tx-table th.r,
        table.tx-table td.r { text-align: right; }
        table.tx-table th.c,
        table.tx-table td.c { text-align: center; }
        table.tx-table td {
            font-size: 12px;
            padding: 8px 10px;
            border: 1px solid #e5e5e5;
            vertical-align: top;
        }
        table.tx-table tbody tr:nth-child(even) td { background: #fafafa; }
        .tx-name   { font-weight: 600; color: #111; }
        .tx-tooth  { font-size: 11px; color: var(--accent-dark); font-weight: 600; }
        .tx-note   { font-size: 10.5px; color: #666; font-style: italic; margin-top: 2px; line-height: 1.4; }
        .tx-variants { font-size: 9.5px; color: #777; margin-top: 3px; line-height: 1.5; }

        /* ── Notes ── */
        .notes {
            margin-top: 6px; padding: 10px 14px;
            background: var(--accent-light);
            border-radius: 6px; font-size: 11px; color: #444; line-height: 1.6;
        }
        .notes strong { color: var(--accent-dark); }

        /* ── Smart Presentation QR (only when a live link exists) ── */
        .qr-box {
            display: flex; align-items: center; justify-content: flex-end;
            gap: 8px; margin-top: 6px;
        }
        .qr-box img { width: 52px; height: 52px; flex-shrink: 0; }
        .qr-box span { font-size: 9.5px; color: #666; line-height: 1.3; max-width: 220px; text-align: right; }

        /* ── Signature + footer ── */
        .sig { margin-top: 40px; text-align: right; }
        .sig-line { border-top: 1px solid #333; display: inline-block; width: 180px; margin-bottom: 5px; }
        .sig-name { font-size: 12px; font-weight: 700; color: var(--accent-dark); }
        .sig-sub  { font-size: 11px; color: #666; }

        /* ── Terms & validity ── */
        .terms {
            margin-top: 22px;
            border: 1px solid var(--accent-light);
            border-left: 3px solid var(--accent);
            background: var(--accent-light);
            border-radius: 6px;
            padding: 10px 14px;
        }
        .terms-title {
            font-size: 10px; font-weight: 800; letter-spacing: .6px;
            text-transform: uppercase; color: var(--accent-dark); margin-bottom: 6px;
        }
        .terms ul { margin: 0; padding-left: 16px; }
        .terms li { font-size: 10.5px; color: #444; line-height: 1.6; margin-bottom: 3px; }
        .terms strong { color: var(--accent-dark); }

        .doc-footer {
            margin-top: 18px; padding-top: 10px;
            border-top: 1px solid #ddd;
            display: flex; justify-content: space-between;
            font-size: 10px; color: #888;
        }

        /* ── Print media ── */
        @media print {
            .no-print { display: none !important; }
            body { padding: {{ $pm['top'] }} {{ $pm['right'] }} {{ $pm['bottom'] }} {{ $pm['left'] }}; }
            @page { size: A4; margin: 0; }
            .opt-block { page-break-inside: avoid; }
            table.tx-table th,
            .opt-head,
            .notes { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

{{-- ── Screen action bar ── --}}
<div class="no-print">
    <span>Treatment Plan — {{ $patient->name ?? '—' }}</span>
    <div>
        <button class="btn btn-print" onclick="window.print()">Print / Save as PDF</button>
        <a class="btn" href="{{ route('patients.show', $patient->id) }}#treatment-plan">← Back</a>
    </div>
</div>

@php
    use App\Models\AppSetting;

    $clinic   = AppSetting::group('clinic');
    $print    = AppSetting::group('print');
    $headerType = $print['print_header_type'] ?? 'plain';
    // Plain paper = pre-printed stationery already carrying clinic identity,
    // so we don't re-print the clinic name/logo here.
    $showClinic = $headerType !== 'plain';
    $doctor   = $consultation?->doctor;

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

    $planDateRaw = $consultation?->consultation_date
                   ? \Carbon\Carbon::parse($consultation->consultation_date)
                   : now();
    $planDate    = $planDateRaw->format('d M Y');
    $validDays   = max(1, (int) ($print['tp_valid_days'] ?? 15));
    $validUntil  = $planDateRaw->copy()->addDays($validDays)->format('d M Y');

    // Footer text blocks — editable in Settings → Printing. One line = one
    // bullet; a cleared setting hides the block. Placeholders {days},
    // {plan_date}, {valid_until} are substituted per printout.
    $tpTermsDefault = "These treatment charges are valid for {days} days from the date of planning ({plan_date}) — valid until {valid_until}."
        . "\nThis plan is based on the current clinical findings. The treatment, its sequence, or the charges may be revised if additional conditions are detected during the course of treatment."
        . "\nThe clinic reserves the right to modify the treatment plan as clinically required. Charges for any additional procedures will be discussed and agreed before they are carried out.";
    $termsLines = collect(preg_split('/\r\n|\r|\n/', $print['tp_terms'] ?? $tpTermsDefault))
        ->map(fn ($l) => trim(strtr($l, [
            '{days}'        => $validDays,
            '{plan_date}'   => $planDate,
            '{valid_until}' => $validUntil,
        ])))
        ->filter()
        ->values();

    $benefitTitle   = trim($print['tp_benefit_title'] ?? 'AOCP Membership Benefit');
    $benefitDefault = 'AOCP holders get a 10% discount on all the above treatments — except orthodontic treatments, full mouth rehabilitation, and full mouth implant treatments.';
    $benefitLines   = collect(preg_split('/\r\n|\r|\n/', $print['tp_benefit_text'] ?? $benefitDefault))
        ->map(fn ($l) => trim($l))
        ->filter()
        ->values();

    $multiple = count($plans) > 1;
@endphp

{{-- ── Configurable print header (Settings → Print) ── --}}
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

    {{-- Doctor / clinic (right). Clinic name only as a fallback, and never on
         plain paper (the pre-printed sheet already shows clinic identity). --}}
    <div class="lh-right">
        <div class="lh-doc">{{ $doctor?->doctor_name ?? ($showClinic ? ($clinic['clinic_name'] ?? '') : '') }}</div>
        @if($doctor?->designation ?? null)<div class="lh-line">{{ $doctor->designation }}</div>@endif
        @if($doctor?->registration_number ?? null)<div class="lh-line">Registration No.: {{ $doctor->registration_number }}</div>@endif
    </div>
</div>

{{-- ── Title + date ── --}}
<div class="doc-title">Treatment Plan</div>
<div style="text-align:center;border-bottom:1px solid #d8d8d8;margin-bottom:16px;"></div>
<div class="doc-date"><strong>Date:</strong> {{ $planDate }}</div>

{{-- ── One block per option ── --}}
@foreach($plans as $idx => $plan)
<div class="opt-block">

    {{-- Option heading — only when comparing multiple options --}}
    @if($multiple)
    <div class="opt-head">
        {{ $plan->plan_name }}
        <span class="opt-meta">
            @if($plan->estimated_duration) · {{ $plan->estimated_duration }} @endif
            @if($plan->visit_count) · ~{{ $plan->visit_count }} visits @endif
        </span>
    </div>
    @else
    <div class="tx-label">Treatments:</div>
    @endif

    {{-- Items table --}}
    <table class="tx-table">
        <thead>
            <tr>
                <th>Treatment</th>
                <th class="c">Qty.</th>
                <th class="r">Treatment Charges</th>
                <th class="r">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($plan->items as $item)
            @php
                $qty   = max((int) $item->units, 1);
                $gross = (float) $item->unit_price * $qty;
                // Material options — the chosen one already IS the main line,
                // so print only the alternatives the patient could switch to.
                $altVariants = collect(is_array($item->material_variants) ? $item->material_variants : [])
                    ->filter(fn ($v) => filled($v['label'] ?? null) && empty($v['selected']))
                    ->values();
            @endphp
            <tr>
                <td>
                    <span class="tx-name">{{ $item->treatment_name }}</span>@if($item->tooth_number) <span class="tx-tooth">({{ $item->tooth_number }})</span>@endif
                    @if(filled($item->notes))<div class="tx-note">{{ $item->notes }}</div>@endif
                    @if($altVariants->isNotEmpty())
                    <div class="tx-variants">
                        Other options:
                        @foreach($altVariants as $v)<span>{{ $v['label'] }} — Rs. {{ number_format((float) ($v['price'] ?? 0), 0) }}</span>@if(!$loop->last) &middot; @endif
                        @endforeach
                    </div>
                    @endif
                </td>
                <td class="c">{{ $qty }}</td>
                <td class="r">{{ number_format((float) $item->unit_price, 0) }}</td>
                <td class="r">{{ number_format($gross, 0) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Doctor note for this option --}}
    @if($plan->doctor_notes)
    <div class="notes"><strong>Note:</strong> {{ $plan->doctor_notes }}</div>
    @endif

    {{-- Scan to view this option online (only if a Smart Presentation link already exists) --}}
    @if($plan->presentation_qr ?? null)
    <div class="qr-box">
        <img src="{{ $plan->presentation_qr }}" alt="QR code">
        <span>Scan to view this plan online, with cost breakdown and payment options.</span>
    </div>
    @endif

</div>
@endforeach

{{-- ── Signature ── --}}
<div class="sig">
    <div class="sig-line"></div>
    <div class="sig-name">{{ $doctor?->doctor_name ?? '' }}</div>
    @if($doctor?->designation ?? null)<div class="sig-sub">{{ $doctor->designation }}</div>@endif
    @if($doctor?->registration_number ?? null)<div class="sig-sub">Reg. No.: {{ $doctor->registration_number }}</div>@endif
</div>

{{-- ── Terms & validity (editable: Settings → Printing) ── --}}
@if($termsLines->isNotEmpty())
<div class="terms">
    <div class="terms-title">Terms &amp; Validity</div>
    <ul>
        @foreach($termsLines as $line)
        <li>{{ $line }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- ── Benefit note (editable: Settings → Printing) ── --}}
@if($benefitLines->isNotEmpty())
<div class="terms" style="margin-top:10px;">
    @if($benefitTitle !== '')<div class="terms-title">{{ $benefitTitle }}</div>@endif
    <ul>
        @foreach($benefitLines as $line)
        <li>{{ $line }}</li>
        @endforeach
    </ul>
</div>
@endif

{{-- ── Footer (clinic identity hidden on plain / pre-printed stationery) ── --}}
@if($showClinic && (($clinic['clinic_name'] ?? false) || ($clinic['clinic_phone'] ?? false)))
<div class="doc-footer">
    <span>{{ $clinic['clinic_name'] ?? '' }}@if($clinic['clinic_phone'] ?? false) · {{ $clinic['clinic_phone'] }}@endif</span>
</div>
@endif

</body>
</html>
