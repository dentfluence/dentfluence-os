<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $config['title'] }} — {{ $treatment->name }}</title>
    @php $pm = \App\Models\AppSetting::printMargins(['top' => '24px', 'bottom' => '24px', 'left' => '24px', 'right' => '24px']); @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            'Inter', sans-serif;
            color: #1a1a1a;
            background: #fff;
            padding: 40px;
            max-width: 720px;
            margin: 0 auto;
        }
        .clinic-header {
            border-bottom: 2px solid #380740;
            padding-bottom: 16px;
            margin-bottom: 24px;
        }
        .clinic-name {
            font-size: 22px;
            font-weight: bold;
            color: #380740;
            letter-spacing: 0.5px;
        }
        .doc-title {
            font-size: 18px;
            color: #380740;
            margin-top: 4px;
        }
        .treatment-badge {
            display: inline-block;
            background: #f3e8f9;
            color: #6a0f70;
            padding: 4px 12px;
            font-size: 12px;
            'Inter', sans-serif;
            margin-top: 8px;
            border: 1px solid #e8d5f0;
        }
        .section-title {
            font-size: 14px;
            font-weight: bold;
            color: #380740;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 28px 0 12px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e8d5f0;
            'Inter', sans-serif;
        }
        .content-box {
            background: #faf5ff;
            border: 1px solid #e8d5f0;
            padding: 20px;
            font-size: 14px;
            line-height: 1.8;
            white-space: pre-line;
            'Inter', sans-serif;
        }
        .no-content {
            color: #9ca3af;
            font-style: italic;
            'Inter', sans-serif;
            font-size: 13px;
            padding: 16px;
            border: 1px dashed #e8d5f0;
        }
        .pdf-notice {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 12px 16px;
            font-size: 13px;
            'Inter', sans-serif;
            color: #1e40af;
        }
        .footer {
            margin-top: 40px;
            padding-top: 16px;
            border-top: 1px solid #e8d5f0;
            font-size: 11px;
            color: #9ca3af;
            'Inter', sans-serif;
            display: flex;
            justify-content: space-between;
        }
        .patient-fill {
            margin-top: 32px;
            padding: 16px;
            border: 1px solid #e8d5f0;
        }
        .patient-fill-row {
            display: flex;
            gap: 32px;
            margin-bottom: 16px;
        }
        .patient-fill-field {
            flex: 1;
            'Inter', sans-serif;
            font-size: 13px;
            color: #374151;
        }
        .patient-fill-line {
            border-bottom: 1px solid #374151;
            height: 24px;
            margin-top: 4px;
        }
        .no-print-bar {
            background: #f3e8f9;
            border-bottom: 1px solid #e8d5f0;
            padding: 12px 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            'Inter', sans-serif;
            font-size: 13px;
            color: #380740;
            margin: -40px -40px 40px -40px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 13px;
            'Inter', sans-serif;
            cursor: pointer;
            border: none;
            text-decoration: none;
        }
        .btn-primary { background: #6a0f70; color: #fff; }
        .btn-outline { background: #fff; color: #6a0f70; border: 1px solid #6a0f70; }
        .btn-green   { background: #16a34a; color: #fff; }
        .btn-blue    { background: #2563eb; color: #fff; }
        .btn-bar { display: flex; gap: 8px; align-items: center; }
        @media print {
            .no-print-bar { display: none !important; }
            body { padding: {{ $pm['top'] }} {{ $pm['right'] }} {{ $pm['bottom'] }} {{ $pm['left'] }}; }
            @page { size: A4; margin: 0; }
        }
    </style>
</head>
<body>

{{-- ── Action bar (hidden on print) ── --}}
<div class="no-print-bar">
    <div>
        <strong>{{ $config['title'] }}</strong> — {{ $treatment->name }}
    </div>
    <div class="btn-bar">
        <button class="btn btn-primary" onclick="window.print()">
            Print
        </button>
        @if($treatment->activeSop?->{$config['field']})
        {{-- WhatsApp share of text --}}
        @php
            $shareText = urlencode(
                "*{$config['title']}*\n*Treatment: {$treatment->name}*\n\n" .
                ($textContent ?? '')
            );
        @endphp
        <a class="btn btn-green"
           href="https://wa.me/?text={{ $shareText }}"
           target="_blank">
            Share via WhatsApp
        </a>
        @endif
        <a class="btn btn-outline" href="{{ url()->previous() }}">← Back</a>
    </div>
</div>

{{-- ── Clinic header ── --}}
<div class="clinic-header">
    <div class="clinic-name">{{ config('app.clinic_name', 'Dentfluence Dental Clinic') }}</div>
    <div class="doc-title">{{ $config['title'] }}</div>
    <span class="treatment-badge">{{ $treatment->name }}</span>
</div>

{{-- ── If an uploaded PDF exists for this type ── --}}
@if($pdfMedia && $pdfMedia->url)
<div class="pdf-notice">
    A PDF version of this document is available:
    <a href="{{ $pdfMedia->url }}" target="_blank" style="color:#1e40af;font-weight:bold;">
        {{ $pdfMedia->label }}
    </a>
    — open and print/share directly from there for best formatting.
</div>
@endif

{{-- ── Text content ── --}}
<div class="section-title">{{ $config['title'] }}</div>

@if($textContent)
<div class="content-box">{{ $textContent }}</div>
@else
<div class="no-content">
    No {{ strtolower($config['title']) }} have been added yet for this treatment.
    @if($pdfMedia)
        Please refer to the uploaded PDF above.
    @endif
</div>
@endif

{{-- ── Patient acknowledgement section (for consent) ── --}}
@if($type === 'consent')
<div class="patient-fill">
    <div class="section-title" style="margin-top:0">Patient Acknowledgement</div>
    <div class="patient-fill-row">
        <div class="patient-fill-field">
            Patient Name
            <div class="patient-fill-line"></div>
        </div>
        <div class="patient-fill-field">
            Date
            <div class="patient-fill-line"></div>
        </div>
    </div>
    <div class="patient-fill-row">
        <div class="patient-fill-field">
            Patient Signature
            <div class="patient-fill-line" style="height:48px"></div>
        </div>
        <div class="patient-fill-field">
            Doctor / Witness
            <div class="patient-fill-line" style="height:48px"></div>
        </div>
    </div>
</div>
@endif

{{-- ── Footer ── --}}
<div class="footer">
    <span>{{ $treatment->name }} — {{ $config['title'] }}</span>
    <span>Printed: {{ now()->format('d M Y') }}</span>
</div>

</body>
</html>
