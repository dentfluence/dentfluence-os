<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $prescription->prescription_number }} — {{ $patient->name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            font-size: 12px;
            color: #1a1a1a;
            background: #fff;
            padding: 24px 32px;
            max-width: 720px;
            margin: 0 auto;
        }

        /* ── Screen-only toolbar ── */
        .screen-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 20px;
            padding: 10px 14px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .screen-toolbar p { font-size: 11px; color: #64748b; }
        .btn-print {
            padding: 7px 18px;
            background: #6a0f70;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-back {
            padding: 7px 14px;
            background: #fff;
            color: #475569;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 12px;
            text-decoration: none;
        }

        /* ── Clinic header ── */
        .clinic-header {
            text-align: center;
            border-bottom: 2px solid #6a0f70;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .clinic-header h1 { font-size: 18px; font-weight: 700; color: #6a0f70; }
        .clinic-header p  { font-size: 10px; color: #64748b; margin-top: 2px; }

        /* ── Rx header ── */
        .rx-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px dashed #cbd5e1;
        }
        .rx-header .rx-number { font-size: 14px; font-weight: 700; font-family: monospace; color: #6a0f70; }
        .rx-header .rx-meta   { font-size: 10px; color: #64748b; line-height: 1.6; text-align: right; }

        /* ── Patient info box ── */
        .patient-box {
            background: #faf7fc;
            border: 1px solid #e8dff0;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 14px;
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
        }
        .patient-box .field { }
        .patient-box .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; }
        .patient-box .value { font-size: 12px; font-weight: 600; color: #0f172a; }

        /* ── Clinical context ── */
        .clinical-context {
            display: flex;
            gap: 16px;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }
        .clinical-context .item { flex: 1; min-width: 180px; }
        .clinical-context .label { font-size: 9px; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; margin-bottom: 2px; }
        .clinical-context .value { font-size: 11px; color: #1e293b; }

        /* ── Rx symbol ── */
        .rx-symbol {
            font-size: 28px;
            font-weight: 700;
            color: #6a0f70;
            font-style: italic;
            margin-bottom: 8px;
            font-family: serif;
        }

        /* ── Drug table ── */
        table.drugs {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
        }
        table.drugs th {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #3a0050;
            padding: 5px 8px;
            text-align: left;
            border-bottom: 1px solid #e8dff0;
            background: #f3eef7;
        }
        table.drugs td {
            padding: 7px 8px;
            font-size: 11px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        table.drugs tr:last-child td { border-bottom: none; }
        table.drugs .drug-name  { font-weight: 600; color: #0f172a; }
        table.drugs .drug-sub   { font-size: 9px; color: #64748b; }
        .sos-badge {
            display: inline-block;
            font-size: 8px;
            padding: 1px 5px;
            background: #fee2e2;
            color: #b91c1c;
            border-radius: 4px;
            font-weight: 700;
            margin-left: 4px;
        }

        /* ── Notes & instructions ── */
        .notes-section {
            margin-top: 12px;
            padding: 8px 12px;
            background: #fffbeb;
            border-left: 3px solid #f59e0b;
            border-radius: 0 4px 4px 0;
            font-size: 11px;
            color: #78350f;
        }
        .notes-section .label { font-size: 9px; font-weight: 700; text-transform: uppercase; margin-bottom: 3px; color: #92400e; }

        /* ── Footer ── */
        .rx-footer {
            margin-top: 24px;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }
        .rx-footer .follow-up { font-size: 10px; color: #475569; }
        .signature-area { text-align: center; }
        .signature-area .line { width: 140px; border-bottom: 1px solid #94a3b8; margin-bottom: 4px; }
        .signature-area .sig-label { font-size: 9px; color: #64748b; }

        /* ── Print media ── */
        @media print {
            .screen-toolbar { display: none !important; }
            body { padding: 10mm 12mm; }
            @page { margin: 0; }
        }
    </style>
</head>
<body>

    {{-- Screen-only toolbar --}}
    <div class="screen-toolbar">
        <div>
            <p>To save as PDF: click Print → change Destination to <strong>Save as PDF</strong></p>
        </div>
        <div style="display:flex;gap:8px;align-items:center;">
            <a class="btn-back" href="{{ route('patients.prescriptions.show', [$patient, $prescription]) }}">← Back</a>
            <button class="btn-print" onclick="window.print()">Print / Save PDF</button>
        </div>
    </div>

    {{-- Configurable print header (Settings → Print) --}}
    @php
        $print      = \App\Models\AppSetting::group('print');
        $clinic     = \App\Models\AppSetting::group('clinic');
        $headerType = $print['print_header_type'] ?? 'plain';
    @endphp
    @include('partials.print-letterhead')

    {{-- NOTE: On 'plain' stationery we intentionally print NO clinic identity
         here — the pre-printed letterhead already carries the clinic logo,
         name and address. For 'logo'/'letterhead' modes the banner above
         (print-letterhead) already renders the clinic identity. --}}

    {{-- Rx number + meta --}}
    <div class="rx-header">
        <div>
            <div class="rx-number">{{ $prescription->prescription_number }}</div>
            <div style="font-size:10px;color:#64748b;margin-top:2px;">
                Source: {{ $prescription->sourceLabel() }}
                @if($prescription->printed_at)
                    &nbsp;·&nbsp; Printed: {{ $prescription->printed_at->format('d M Y') }}
                @endif
            </div>
        </div>
        <div class="rx-meta">
            Date: {{ $prescription->created_at->format('d M Y') }}<br>
            {{ $prescription->prescribedBy?->doctor_name ?? '—' }}<br>
            @if($prescription->prescribedBy?->registration_number)Reg. No.: {{ $prescription->prescribedBy->registration_number }}<br>@endif
            @php
                $statusLabels = [
                    'draft'          => 'Draft',
                    'issued'         => 'Issued',
                    'printed'        => 'Printed',
                    'whatsapp_sent'  => 'WhatsApp Sent',
                    'email_sent'     => 'Email Sent',
                    'revised'        => 'Revised',
                    'cancelled'      => 'Cancelled',
                ];
            @endphp
            Status: {{ $statusLabels[$prescription->status] ?? ucfirst($prescription->status) }}
        </div>
    </div>

    {{-- Patient info --}}
    <div class="patient-box">
        <div class="field">
            <div class="label">Patient</div>
            <div class="value">{{ $patient->name }}</div>
        </div>
        @if($patient->age || $patient->dob)
        <div class="field">
            <div class="label">Age</div>
            <div class="value">{{ $patient->age ?? (\Carbon\Carbon::parse($patient->dob)->age . ' yrs') }}</div>
        </div>
        @endif
        @if($patient->gender)
        <div class="field">
            <div class="label">Gender</div>
            <div class="value">{{ ucfirst($patient->gender) }}</div>
        </div>
        @endif
        @if($patient->phone)
        <div class="field">
            <div class="label">Phone</div>
            <div class="value">{{ $patient->phone }}</div>
        </div>
        @endif
        @if($patient->medical_alert)
        <div class="field" style="color:#b91c1c;">
            <div class="label" style="color:#b91c1c;">Medical Alert</div>
            <div class="value" style="font-size:11px;">{{ $patient->medical_alert }}</div>
        </div>
        @endif
    </div>

    {{-- Clinical context --}}
    @if($prescription->chief_complaint || $prescription->diagnosis)
    <div class="clinical-context">
        @if($prescription->chief_complaint)
        <div class="item">
            <div class="label">Chief Complaint</div>
            <div class="value">{{ $prescription->chief_complaint }}</div>
        </div>
        @endif
        @if($prescription->diagnosis)
        <div class="item">
            <div class="label">Diagnosis</div>
            <div class="value">{{ $prescription->diagnosis }}</div>
        </div>
        @endif
    </div>
    @endif

    {{-- Rx symbol --}}
    <div class="rx-symbol">℞</div>

    {{-- Drug table --}}
    @if($prescription->items && $prescription->items->count())
    {{-- Drug table — columns match app-wide prescription panel: Drug | SOS | Morn | Noon | Night | Duration | Total --}}
    <table class="drugs">
        <thead>
            <tr>
                <th style="width:22px;">#</th>
                <th>Drug / Strength</th>
                <th style="text-align:center;width:36px;">SOS</th>
                <th style="text-align:center;width:44px;">Morn</th>
                <th style="text-align:center;width:44px;">Noon</th>
                <th style="text-align:center;width:44px;">Night</th>
                <th style="width:72px;">Duration</th>
                <th style="text-align:center;width:44px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($prescription->items as $i => $item)
            <tr>
                <td style="color:#94a3b8;">{{ $i + 1 }}.</td>
                <td>
                    <span class="drug-name">
                        {{ $item->drug_name ?: ($item->drug?->brand_name ?? '—') }}
                    </span>
                    @if($item->generic_name)
                        <div class="drug-sub">({{ $item->generic_name }})</div>
                    @endif
                    @if($item->strength)
                        <div class="drug-sub">{{ $item->strength }}{{ $item->dosage_form ? ' · '.$item->dosage_form : '' }}</div>
                    @endif
                    @if($item->food_advice || $item->instructions)
                        <div class="drug-sub" style="margin-top:2px;">
                            @if($item->food_advice) {{ $item->food_advice }} @endif
                            @if($item->instructions) · {{ $item->instructions }} @endif
                        </div>
                    @endif
                </td>
                {{-- SOS --}}
                <td style="text-align:center;">
                    @if($item->is_sos)<span class="sos-badge">SOS</span>@else —@endif
                </td>
                {{-- Morn / Noon / Night — use correct DB column names --}}
                <td style="text-align:center;">{{ $item->morning ?: '—' }}</td>
                <td style="text-align:center;">{{ $item->afternoon ?: '—' }}</td>
                <td style="text-align:center;">{{ $item->night ?: '—' }}</td>
                <td>{{ $item->duration ? $item->duration . ' ' . ($item->duration_unit ?? 'days') : '—' }}</td>
                <td style="text-align:center;font-weight:600;">{{ $item->quantity ?: '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
        <p style="font-size:11px;color:#94a3b8;margin-bottom:14px;font-style:italic;">No drug items recorded.</p>
    @endif

    {{-- Notes --}}
    @if($prescription->general_instructions)
    <div class="notes-section">
        <div class="label">General Instructions</div>
        {{ $prescription->general_instructions }}
    </div>
    @endif

    {{-- Footer --}}
    <div class="rx-footer">
        <div class="follow-up">
            @if($prescription->follow_up_date)
                Follow-up: <strong>{{ \Carbon\Carbon::parse($prescription->follow_up_date)->format('d M Y') }}</strong>
            @else
                Follow-up as advised
            @endif
            @if($prescription->print_count && $prescription->print_count > 1)
                <br><span style="font-size:9px;color:#94a3b8;">Print #{{ $prescription->print_count }}</span>
            @endif
        </div>
        <div class="signature-area">
            <div class="line"></div>
            <div class="sig-label">{{ $prescription->prescribedBy?->doctor_name ?? '—' }}<br>@if($prescription->prescribedBy?->registration_number)Reg. No.: {{ $prescription->prescribedBy->registration_number }}<br>@endif Signature &amp; Stamp</div>
        </div>
    </div>

    <script>
        // Auto-print when opened via the Print button (query param triggers it)
        if (new URLSearchParams(window.location.search).get('auto') === '1') {
            window.addEventListener('load', () => setTimeout(() => window.print(), 300));
        }
    </script>
</body>
</html>
