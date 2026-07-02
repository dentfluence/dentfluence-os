<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lab Case — {{ $labCase->case_number }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 13px; color: #1e0a2c; background: #fff; padding: 32px; }

        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 24px; padding-bottom: 16px; border-bottom: 2px solid #6a0f70; }
        .clinic-name { font-size: 20px; font-weight: 700; color: #6a0f70; }
        .clinic-sub  { font-size: 12px; color: #9a85aa; margin-top: 2px; }
        .case-no     { font-size: 22px; font-weight: 700; color: #1e0a2c; text-align: right; }
        .case-date   { font-size: 11px; color: #9a85aa; text-align: right; margin-top: 3px; }

        .badge { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .badge-sent        { background: #ede4f3; color: #6a0f70; }
        .badge-in_progress { background: #fff4e0; color: #a05c00; }
        .badge-received    { background: #e8f7ef; color: #1a7a45; }
        .badge-closed      { background: #f0f0f0; color: #555; }

        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px; }
        .grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 20px; }

        .card { border: 1px solid #ede4f3; border-radius: 6px; padding: 14px 16px; }
        .card-title { font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #9a85aa; margin-bottom: 8px; }
        .card-value { font-size: 14px; font-weight: 600; color: #1e0a2c; }
        .card-sub   { font-size: 11px; color: #9a85aa; margin-top: 2px; }

        .section-title { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; color: #6a0f70; margin-bottom: 10px; margin-top: 20px; padding-bottom: 4px; border-bottom: 1px solid #ede4f3; }

        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        th { background: #faf5fb; padding: 7px 12px; text-align: left; font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #9a85aa; }
        td { padding: 9px 12px; border-bottom: 1px solid #f5f0f8; font-size: 12.5px; }

        .notes-box { background: #faf5fb; border-left: 3px solid #c084db; border-radius: 0 4px 4px 0; padding: 10px 14px; font-size: 12.5px; color: #4e2060; line-height: 1.5; }

        .cost-row  { display: flex; justify-content: flex-end; gap: 48px; font-size: 13px; margin-top: 4px; }
        .cost-total { font-weight: 700; font-size: 15px; color: #6a0f70; }

        .signature-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 24px; margin-top: 40px; }
        .sig-box { border-top: 1px solid #ccc; padding-top: 8px; font-size: 11px; color: #9a85aa; text-align: center; }

        .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #ede4f3; font-size: 10.5px; color: #bbb; text-align: center; }

        @media print {
            body { padding: 16px; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

{{-- Print button (hidden on print) --}}
<div class="no-print" style="margin-bottom:20px;text-align:right;">
    <button onclick="window.print()" style="padding:8px 18px;background:#6a0f70;color:#fff;border:none;border-radius:5px;font-size:13px;cursor:pointer;">
        Print / Save PDF
    </button>
    <button onclick="window.close()" style="padding:8px 14px;background:#f5f0f8;color:#6a0f70;border:1px solid #ede4f3;border-radius:5px;font-size:13px;cursor:pointer;margin-left:8px;">
        Close
    </button>
</div>

{{-- Configurable print header (Settings → Print) --}}
@php
    $print      = \App\Models\AppSetting::group('print');
    $clinic     = \App\Models\AppSetting::group('clinic');
    $headerType = $print['print_header_type'] ?? 'plain';
@endphp
@include('partials.print-letterhead')

{{-- Header --}}
<div class="header">
    <div>
        @if($headerType === 'plain')
        <div class="clinic-name">{{ $clinic['clinic_name'] ?? 'Dentfluence Dental Clinic' }}</div>
        @endif
        <div class="clinic-sub">Lab Work Order</div>
    </div>
    <div>
        <div class="case-no">{{ $labCase->case_number }}</div>
        <div class="case-date">Printed {{ now()->format('d M Y, h:i A') }}</div>
        <div style="margin-top:6px;text-align:right;">
            <span class="badge badge-{{ $labCase->status }}">
                {{ ucfirst(str_replace('_',' ', $labCase->status)) }}
            </span>
            @if($labCase->priority !== 'routine')
            <span class="badge" style="background:#fff4e0;color:#a05c00;margin-left:4px;">
                {{ ucfirst($labCase->priority) }}
            </span>
            @endif
        </div>
    </div>
</div>

{{-- Patient + Lab --}}
<div class="grid-2">
    <div class="card">
        <div class="card-title">Patient</div>
        <div class="card-value">{{ $labCase->patient?->name ?? '—' }}</div>
        <div class="card-sub">{{ $labCase->patient?->phone }}</div>
        @if($labCase->doctor)
        <div class="card-sub" style="margin-top:4px;">{{ $labCase->doctor->doctor_name }}</div>
        @endif
    </div>
    <div class="card">
        <div class="card-title">Lab Vendor</div>
        <div class="card-value">{{ $labCase->vendor?->name ?? ($labCase->lab_vendor ?? '—') }}</div>
        <div class="card-sub">{{ $labCase->vendor?->phone ?? $labCase->vendor?->whatsapp_number }}</div>
        @if($labCase->technician_name)
        <div class="card-sub" style="margin-top:4px;">Technician: {{ $labCase->technician_name }}</div>
        @endif
    </div>
</div>

{{-- Work details --}}
<div class="grid-3">
    <div class="card">
        <div class="card-title">Work Type</div>
        <div class="card-value">{{ $labCase->workTypeLabel() }}</div>
    </div>
    <div class="card">
        <div class="card-title">Tooth Number</div>
        <div class="card-value">{{ $labCase->toothSummary() }}</div>
    </div>
    <div class="card">
        <div class="card-title">Shade</div>
        <div class="card-value">{{ $labCase->shade ?? '—' }}</div>
    </div>
</div>

{{-- Dates --}}
<div class="grid-3">
    <div class="card">
        <div class="card-title">Sent Date</div>
        <div class="card-value">{{ $labCase->sent_date?->format('d M Y') ?? '—' }}</div>
    </div>
    <div class="card">
        <div class="card-title">Expected Return</div>
        <div class="card-value" style="{{ $labCase->isOverdue() ? 'color:#b52020' : '' }}">
            {{ $labCase->expected_return_date?->format('d M Y') ?? '—' }}
            @if($labCase->isOverdue()) <span style="font-size:11px;">(overdue)</span> @endif
        </div>
    </div>
    <div class="card">
        <div class="card-title">Received Date</div>
        <div class="card-value">{{ $labCase->received_date?->format('d M Y') ?? '—' }}</div>
    </div>
</div>

{{-- Line items (if any) --}}
@if($labCase->items->count())
<div class="section-title">Work Items</div>
<table>
    <thead>
        <tr>
            <th>Item</th>
            <th>Tooth</th>
            <th>Shade</th>
            <th>Material</th>
            <th style="text-align:right;">Unit Cost</th>
        </tr>
    </thead>
    <tbody>
        @foreach($labCase->items as $item)
        <tr>
            <td>{{ $item->work_type ?? '—' }}</td>
            <td>{{ $item->tooth_number ?? '—' }}</td>
            <td>{{ $item->shade ?? '—' }}</td>
            <td>{{ $item->material ?? '—' }}</td>
            <td style="text-align:right;font-family:monospace;">
                {{ $item->unit_cost ? 'Rs. ' . number_format($item->unit_cost, 0) : '—' }}
            </td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

{{-- Cost summary --}}
@if($labCase->estimated_cost || $labCase->lab_cost)
<div style="margin-top:12px;">
    @if($labCase->estimated_cost)
    <div class="cost-row">
        <span style="color:#9a85aa;">Estimated Cost</span>
        <span>Rs. {{ number_format($labCase->estimated_cost, 0) }}</span>
    </div>
    @endif
    @if($labCase->lab_cost)
    <div class="cost-row" style="margin-top:6px;">
        <span style="color:#9a85aa;">Final Lab Cost</span>
        <span class="cost-total">Rs. {{ number_format($labCase->lab_cost, 0) }}</span>
    </div>
    @endif
</div>
@endif

{{-- Instructions --}}
@if($labCase->instructions)
<div class="section-title">Instructions to Lab</div>
<div class="notes-box">{{ $labCase->instructions }}</div>
@endif

{{-- Signatures --}}
<div class="signature-row">
    <div class="sig-box">Prepared By</div>
    <div class="sig-box">Lab Received</div>
    <div class="sig-box">Doctor Approval</div>
</div>

<div class="footer">
    {{ $labCase->case_number }} · Generated by Dentfluence · {{ now()->format('d M Y') }}
</div>

<script>
    // Auto-print when opened as a popup
    if (window.opener) window.print();
</script>
</body>
</html>
