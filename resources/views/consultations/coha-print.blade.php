{{-- resources/views/consultations/coha-print.blade.php --}}
{{-- Patient-facing COHA branded report. Not a treatment estimate. --}}
@extends('layouts.print')

@section('title', 'Oral Health Assessment — ' . $patient->name)

@section('content')
@php
    $c    = $clinic ?? [];
    $r    = $cohaReport;
    $doc  = $consultation->doctor;

    // Risk label helpers
    $riskLabel = fn($v) => match($v ?? '') {
        'low'    => ['Low Risk',    '#15803d', '#dcfce7', '#16a34a'],
        'medium' => ['Medium Risk', '#92400e', '#fef9c3', '#ca8a04'],
        'high'   => ['High Risk',   '#991b1b', '#fee2e2', '#dc2626'],
        default  => ['—',           '#6b7280', '#f9fafb', '#d1d5db'],
    };

    // Tooth status display labels
    $toothLabels = [
        'sound'       => 'Sound',
        'caries'      => 'Caries',
        'fracture'    => 'Fracture',
        'root_stump'  => 'Root Stump',
        'crown'       => 'Crown',
        'bridge'      => 'Bridge',
        'implant'     => 'Implant',
        'denture'     => 'Denture',
        'wear'        => 'Wear',
        'restoration' => 'Restoration',
        'missing'     => 'Missing',
    ];

    // Soft tissue field labels
    $softLabels = [
        'lips'            => 'Lips',
        'buccal_mucosa'   => 'Buccal Mucosa',
        'tongue'          => 'Tongue',
        'floor_of_mouth'  => 'Floor of Mouth',
        'hard_palate'     => 'Hard Palate',
        'soft_palate'     => 'Soft Palate',
        'oropharynx'      => 'Oropharynx',
        'salivary_glands' => 'Salivary Glands',
        'oral_cancer_screening' => 'Oral Cancer Screening',
    ];

    // Treatment awareness items
    $awarenessItems = [
        'cleaning'    => ['Cleaning & Scaling',   'Regular professional cleaning removes tartar and protects your gums.'],
        'fillings'    => ['Fillings',             'Treating cavities now prevents them from becoming larger, more painful problems.'],
        'rct'         => ['Root Canal Treatment', 'This treatment saves a badly infected tooth so you do not need an extraction.'],
        'crowns'      => ['Crowns',               'A crown protects and strengthens a weakened or broken tooth.'],
        'extractions' => ['Extractions',          'Removing teeth that are beyond saving — discussed when all other options are exhausted.'],
        'implants'    => ['Implants',             'Dental implants permanently replace missing teeth and look and feel natural.'],
        'ortho'       => ['Orthodontics',         'Straightening your teeth improves your bite, hygiene, and smile.'],
        'perio'       => ['Gum Treatment',        'Treating gum disease is essential — gum disease is the leading cause of adult tooth loss.'],
        'whitening'   => ['Teeth Whitening',      'Professional whitening safely brightens your smile by several shades.'],
        'veneers'     => ['Veneers / Bonding',    'Thin shells or composite resin improve the shape, colour, and appearance of teeth.'],
        'fluoride'    => ['Fluoride Treatment',   'Fluoride strengthens tooth enamel and reduces your risk of cavities.'],
    ];

    // FDI quadrants
    $quads = [
        'Upper Right' => [18,17,16,15,14,13,12,11],
        'Upper Left'  => [21,22,23,24,25,26,27,28],
        'Lower Left'  => [31,32,33,34,35,36,37,38],
        'Lower Right' => [48,47,46,45,44,43,42,41],
    ];

    // Helper: format a snake_case key into readable text
    $fmt = fn($v) => ucwords(str_replace(['_','-'], ' ', $v ?? ''));
@endphp

<style>
    body  { font-family:'Inter', sans-serif; font-size:11px; color:#1f2937; margin:0; }
    /* ── Page header ── */
    .rpt-header {
        display:flex; align-items:center; justify-content:space-between;
        padding:0 0 14px; border-bottom:2px solid #0e7490; margin-bottom:16px;
    }
    .rpt-clinic-name {
        font-family:'Cormorant Garamond',Georgia,serif; font-size:20px;
        font-weight:700; color:#0e7490; line-height:1.1;
    }
    .rpt-clinic-meta { font-size:10px; color:#6b7280; line-height:1.6; }
    .rpt-doc-block { text-align:right; }
    .rpt-doc-name { font-size:12px; font-weight:700; color:#1f2937; }
    .rpt-doc-meta { font-size:10px; color:#6b7280; }

    /* ── Report title band ── */
    .rpt-title-band {
        background:linear-gradient(135deg,#0e7490,#0c6282);
        color:#fff; border-radius:6px;
        padding:14px 18px; margin-bottom:16px;
        display:flex; justify-content:space-between; align-items:center;
    }
    .rpt-title-main { font-family:'Cormorant Garamond',Georgia,serif; font-size:17px; font-weight:700; }
    .rpt-title-sub  { font-size:10px; opacity:.85; margin-top:2px; }
    .rpt-patient-block { text-align:right; }
    .rpt-patient-name { font-size:13px; font-weight:700; }
    .rpt-patient-meta { font-size:10px; opacity:.85; line-height:1.6; }

    /* ── Sections ── */
    .rpt-section { margin-bottom:14px; page-break-inside:avoid; }
    .rpt-section-title {
        font-size:10px; font-weight:800; letter-spacing:.8px; text-transform:uppercase;
        color:#0e7490; border-bottom:1px solid #a5f3fc; padding-bottom:4px; margin-bottom:8px;
    }
    .rpt-grid { display:grid; grid-template-columns:1fr 1fr; gap:4px 20px; }
    .rpt-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:4px 16px; }
    .rpt-row { display:flex; gap:6px; align-items:baseline; }
    .rpt-label { font-size:10px; color:#6b7280; white-space:nowrap; min-width:110px; }
    .rpt-value { font-size:11px; font-weight:600; color:#1f2937; }

    /* ── Risk pills ── */
    .risk-grid { display:flex; flex-direction:column; gap:6px; }
    .risk-row  { display:flex; align-items:center; gap:10px; }
    .risk-name { font-size:10px; color:#374151; flex:1; }
    .risk-pill {
        padding:2px 12px; border-radius:20px; font-size:10px; font-weight:700;
        border:1px solid; white-space:nowrap;
    }

    /* ── Tooth chart ── */
    .tooth-chart-table { width:100%; border-collapse:collapse; font-size:9px; }
    .tooth-chart-table th { background:#f0f9ff; color:#0e7490; font-weight:700; text-align:center; padding:3px 2px; border:1px solid #e5e7eb; }
    .tooth-chart-table td { text-align:center; padding:3px 2px; border:1px solid #e5e7eb; }
    .tooth-no { font-size:8px; color:#6b7280; display:block; }
    .tooth-status { font-size:9px; font-weight:600; }
    .tooth-sound     { color:#15803d; }
    .tooth-caries    { color:#dc2626; }
    .tooth-missing   { color:#9ca3af; font-style:italic; }
    .tooth-crown     { color:#7c3aed; }
    .tooth-other     { color:#0e7490; }

    /* ── Awareness items ── */
    .aware-list { display:flex; flex-direction:column; gap:6px; }
    .aware-item { padding:6px 10px; border-left:3px solid #0e7490; background:#f0f9ff; border-radius:0 4px 4px 0; }
    .aware-item-name { font-size:10px; font-weight:700; color:#0e7490; margin-bottom:2px; }
    .aware-item-desc { font-size:10px; color:#374151; line-height:1.5; }

    /* ── Monitoring ── */
    .monitor-tags { display:flex; flex-wrap:wrap; gap:5px; }
    .monitor-pill {
        background:#ecfeff; border:1px solid #a5f3fc; border-radius:20px;
        padding:2px 10px; font-size:10px; font-weight:700; color:#0e7490;
    }

    /* ── Footer ── */
    .rpt-footer {
        margin-top:20px; padding-top:10px; border-top:1px solid #e5e7eb;
        display:flex; justify-content:space-between; align-items:flex-end;
    }
    .rpt-disclaimer { font-size:9px; color:#9ca3af; max-width:420px; line-height:1.5; }
    .rpt-signature { text-align:center; font-size:10px; }
    .rpt-sig-line { border-top:1px solid #374151; width:140px; margin:0 auto 4px; }
    .rpt-sig-name { font-weight:700; }
    .rpt-sig-title { color:#6b7280; }

    @media print { .no-print { display:none !important; } }
</style>

{{-- ── Print button (no-print) ── --}}
<div class="no-print" style="background:#f0f9ff;border-bottom:1px solid #a5f3fc;padding:10px 20px;display:flex;justify-content:space-between;align-items:center;">
    <div style="font-size:12px;font-weight:600;color:#0e7490;font-family:'Inter',sans-serif;">
        COHA Patient Report — {{ $patient->name }}
    </div>
    <div style="display:flex;gap:8px;">
        <a href="{{ route('coha.edit', [$patient, $consultation]) }}"
           style="padding:6px 14px;font-size:11px;font-weight:600;font-family:'Inter',sans-serif;border:1px solid #a5f3fc;background:#fff;color:#0e7490;border-radius:5px;text-decoration:none;">
            ← Edit Assessment
        </a>
        <button onclick="window.print()"
                style="padding:6px 16px;font-size:11px;font-weight:700;font-family:'Inter',sans-serif;background:#0e7490;color:#fff;border:none;border-radius:5px;cursor:pointer;">
            Print / Save PDF
        </button>
        <a href="{{ route('patients.show', $patient) }}#consultation"
           style="padding:6px 14px;font-size:11px;font-weight:600;font-family:'Inter',sans-serif;border:1px solid #d1d5db;background:#fff;color:#6b7280;border-radius:5px;text-decoration:none;">
            Back to Patient
        </a>
    </div>
</div>

{{-- ── Report Header ── --}}
<div class="rpt-header">
    <div>
        <div class="rpt-clinic-name">{{ $c['clinic_name'] ?? config('app.name') }}</div>
        <div class="rpt-clinic-meta">
            {{ $c['clinic_address'] ?? '' }}<br>
            {{ $c['clinic_phone'] ?? '' }}{{ ($c['clinic_email'] ?? '') ? ' · '.$c['clinic_email'] : '' }}
        </div>
    </div>
    <div class="rpt-doc-block">
        <div class="rpt-doc-name">{{ $doc?->doctor_name ?? auth()->user()->doctor_name }}</div>
        <div class="rpt-doc-meta">
            Assessment Date: {{ $r->report_date?->format('d M Y') ?? $consultation->consultation_date->format('d M Y') }}<br>
            Report No: COHA-{{ str_pad($r->id, 5, '0', STR_PAD_LEFT) }}
        </div>
    </div>
</div>

{{-- ── Title Band ── --}}
<div class="rpt-title-band">
    <div>
        <div class="rpt-title-main">Comprehensive Oral Health Assessment</div>
        <div class="rpt-title-sub">Patient Awareness Report — Not a treatment estimate</div>
    </div>
    <div class="rpt-patient-block">
        <div class="rpt-patient-name">{{ $patient->name }}</div>
        <div class="rpt-patient-meta">
            {{ $patient->age ? 'Age: '.$patient->age.' · ' : '' }}{{ ucfirst($patient->gender ?? '') }}<br>
            {{ $patient->phone ?? '' }}
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════
     SECTION 1 — EXTRAORAL EXAMINATION
════════════════════════════════════════════════════════ --}}
@if($r->extraoral)
<div class="rpt-section">
    <div class="rpt-section-title">1. Extraoral Examination</div>
    <div class="rpt-grid">
        @foreach([
            'tmj'             => 'TMJ',
            'muscles'         => 'Muscles of Mastication',
            'lymph_nodes'     => 'Lymph Nodes',
            'facial_symmetry' => 'Facial Symmetry',
            'facial_profile'  => 'Facial Profile',
            'lips_rest'       => 'Lips at Rest',
        ] as $key => $label)
        @if(!empty($r->extraoral[$key]))
        <div class="rpt-row">
            <span class="rpt-label">{{ $label }}</span>
            <span class="rpt-value">{{ $fmt($r->extraoral[$key]) }}</span>
        </div>
        @endif
        @endforeach
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════
     SECTION 2 — SOFT TISSUE EXAMINATION
════════════════════════════════════════════════════════ --}}
@if($r->soft_tissue)
<div class="rpt-section">
    <div class="rpt-section-title">2. Soft Tissue Examination</div>
    <div class="rpt-grid">
        @foreach($softLabels as $key => $label)
        @if(!empty($r->soft_tissue[$key]))
        <div class="rpt-row">
            <span class="rpt-label">{{ $label }}</span>
            <span class="rpt-value">{{ $fmt($r->soft_tissue[$key]) }}</span>
        </div>
        @endif
        @endforeach
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════
     SECTION 3 — TOOTH ASSESSMENT CHART
════════════════════════════════════════════════════════ --}}
@if($r->tooth_assessment)
@php
    $toothData = $r->tooth_assessment;
    $hasFindings = collect($toothData)->filter(fn($v) => $v && $v !== '')->isNotEmpty();
@endphp
@if($hasFindings)
<div class="rpt-section">
    <div class="rpt-section-title">3. Tooth Assessment</div>
    @foreach($quads as $quadName => $teeth)
    <div style="margin-bottom:8px;">
        <div style="font-size:9px;font-weight:700;color:#6b7280;margin-bottom:3px;text-transform:uppercase;letter-spacing:.5px;">
            {{ $quadName }}
        </div>
        <table class="tooth-chart-table">
            <thead>
                <tr>
                    @foreach($teeth as $t)
                    <th>{{ $t }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                <tr>
                    @foreach($teeth as $t)
                    @php
                        $status = $toothData[$t] ?? '';
                        $statusClass = match($status) {
                            'sound'      => 'tooth-sound',
                            'caries'     => 'tooth-caries',
                            'missing'    => 'tooth-missing',
                            'crown','bridge','implant','denture' => 'tooth-crown',
                            default      => $status ? 'tooth-other' : '',
                        };
                    @endphp
                    <td>
                        <span class="tooth-status {{ $statusClass }}">
                            {{ $toothLabels[$status] ?? ($status ? ucfirst($status) : '—') }}
                        </span>
                    </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>
    @endforeach
</div>
@endif
@endif

{{-- ════════════════════════════════════════════════════════
     SECTIONS 4 & 5 — ORTHODONTIC + PERIODONTAL FINDINGS
════════════════════════════════════════════════════════ --}}
<div class="rpt-grid" style="gap:12px;margin-bottom:14px;">
    {{-- Ortho --}}
    @if($r->ortho_findings && collect($r->ortho_findings)->filter()->isNotEmpty())
    <div class="rpt-section" style="margin-bottom:0;">
        <div class="rpt-section-title">4. Orthodontic Findings</div>
        @foreach([
            'crowding'      => 'Crowding',
            'spacing'       => 'Spacing',
            'overjet'       => 'Overjet',
            'overbite'      => 'Overbite',
            'midline'       => 'Midline',
            'molar_relation'=> 'Molar Relation',
            'crossbite'     => 'Crossbite',
            'skeletal'      => 'Skeletal Pattern',
        ] as $key => $label)
        @if(!empty($r->ortho_findings[$key]))
        <div class="rpt-row">
            <span class="rpt-label">{{ $label }}</span>
            <span class="rpt-value">{{ $fmt($r->ortho_findings[$key]) }}</span>
        </div>
        @endif
        @endforeach
    </div>
    @endif

    {{-- Perio --}}
    @if($r->perio_findings && collect($r->perio_findings)->filter()->isNotEmpty())
    <div class="rpt-section" style="margin-bottom:0;">
        <div class="rpt-section-title">5. Periodontal Findings</div>
        @foreach([
            'bop'               => 'Bleeding on Probing',
            'pocket_depth'      => 'Probing Depth',
            'recession'         => 'Recession',
            'mobility'          => 'Tooth Mobility',
            'furcation'         => 'Furcation',
            'calculus'          => 'Calculus',
            'plaque_control'    => 'Plaque Control',
            'gingival_condition'=> 'Gingival Condition',
        ] as $key => $label)
        @if(!empty($r->perio_findings[$key]))
        <div class="rpt-row">
            <span class="rpt-label">{{ $label }}</span>
            <span class="rpt-value">{{ $fmt($r->perio_findings[$key]) }}</span>
        </div>
        @endif
        @endforeach
    </div>
    @endif
</div>

{{-- ════════════════════════════════════════════════════════
     SECTION 6 — ESTHETIC FINDINGS
════════════════════════════════════════════════════════ --}}
@if($r->esthetic_findings && collect($r->esthetic_findings)->filter()->isNotEmpty())
<div class="rpt-section">
    <div class="rpt-section-title">6. Esthetic Findings</div>
    <div class="rpt-grid">
        @foreach([
            'shade'           => 'Tooth Shade',
            'discolouration'  => 'Discolouration',
            'smile_line'      => 'Smile Line',
            'tooth_proportion'=> 'Tooth Proportions',
            'wear'            => 'Wear / Attrition',
            'buccal_corridor' => 'Buccal Corridor',
        ] as $key => $label)
        @if(!empty($r->esthetic_findings[$key]))
        <div class="rpt-row">
            <span class="rpt-label">{{ $label }}</span>
            <span class="rpt-value">{{ $fmt($r->esthetic_findings[$key]) }}</span>
        </div>
        @endif
        @endforeach
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════
     SECTION 7 — RISK ASSESSMENT
════════════════════════════════════════════════════════ --}}
@if($r->risk_assessment && collect($r->risk_assessment)->filter()->isNotEmpty())
<div class="rpt-section">
    <div class="rpt-section-title">7. Risk Assessment</div>
    <div class="risk-grid">
        @foreach([
            'caries'      => 'Caries Risk',
            'perio'       => 'Periodontal Risk',
            'bruxism'     => 'Bruxism / Wear Risk',
            'oral_cancer' => 'Oral Cancer Risk',
        ] as $key => $label)
        @if(!empty($r->risk_assessment[$key]))
        @php [$rText,$rColor,$rBg,$rBorder] = $riskLabel($r->risk_assessment[$key]); @endphp
        <div class="risk-row">
            <span class="risk-name">{{ $label }}</span>
            <span class="risk-pill" style="color:{{ $rColor }};background:{{ $rBg }};border-color:{{ $rBorder }};">
                {{ $rText }}
            </span>
        </div>
        @endif
        @endforeach
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════
     SECTION 8 — MONITORING TEETH
════════════════════════════════════════════════════════ --}}
@if(!empty($r->monitoring_teeth) && count($r->monitoring_teeth) > 0)
<div class="rpt-section">
    <div class="rpt-section-title">8. Teeth to Monitor at Your Next Visit</div>
    <div style="font-size:10px;color:#374151;margin-bottom:6px;">
        The following teeth require attention. We will re-examine them at your next appointment:
    </div>
    <div class="monitor-tags">
        @foreach($r->monitoring_teeth as $tooth)
        <span class="monitor-pill">Tooth {{ $tooth }}</span>
        @endforeach
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════
     SECTION 9 — TREATMENT AWARENESS
════════════════════════════════════════════════════════ --}}
@php
    $activeAwareness = collect($awarenessItems)->filter(fn($v,$k) => !empty($r->treatment_awareness[$k]));
@endphp
@if($activeAwareness->isNotEmpty())
<div class="rpt-section">
    <div class="rpt-section-title">9. What Your Doctor Recommends You Know About</div>
    <div style="font-size:10px;color:#374151;margin-bottom:8px;font-style:italic;">
        This is an awareness guide to help you understand recommended dental care — it is not a treatment estimate or prescription.
    </div>
    <div class="aware-list">
        @foreach($activeAwareness as $key => [$name, $desc])
        <div class="aware-item">
            <div class="aware-item-name">{{ $name }}</div>
            <div class="aware-item-desc">{{ $desc }}</div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ── Doctor's Notes ── --}}
@if($r->doctor_notes)
<div class="rpt-section">
    <div class="rpt-section-title">Doctor's Notes</div>
    <div style="font-size:11px;color:#1f2937;line-height:1.7;white-space:pre-wrap;">{{ $r->doctor_notes }}</div>
</div>
@endif

{{-- ── Overall Risk Summary bar ── --}}
@php
    $overall       = $r->overallRisk();
    $teethCount    = $r->teethNeedingAttention();
    [$ovText,$ovColor,$ovBg,$ovBorder] = $riskLabel($overall);
@endphp
<div style="background:{{ $ovBg }};border:1px solid {{ $ovBorder }};border-radius:6px;padding:10px 14px;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center;">
    <div>
        <div style="font-size:10px;font-weight:800;color:{{ $ovColor }};letter-spacing:.6px;text-transform:uppercase;">
            Overall Oral Health Risk: {{ $ovText }}
        </div>
        @if($teethCount > 0)
        <div style="font-size:10px;color:#374151;margin-top:2px;">
            {{ $teethCount }} {{ Str::plural('tooth', $teethCount) }} noted for attention.
        </div>
        @endif
    </div>
    <div style="font-size:9px;color:{{ $ovColor }};font-weight:600;">
        Next visit recommended in
        {{ $overall === 'high' ? '1–2 months' : ($overall === 'medium' ? '3–4 months' : '6 months') }}
    </div>
</div>

{{-- ── Footer ── --}}
<div class="rpt-footer">
    <div class="rpt-disclaimer">
        This Comprehensive Oral Health Assessment is a patient awareness document prepared by your dental team.
        It is not a treatment plan or invoice. Please discuss treatment options and costs separately with your doctor.
        Information is accurate as of the assessment date shown above.
    </div>
    <div class="rpt-signature">
        <div class="rpt-sig-line"></div>
        <div class="rpt-sig-name">{{ $doc?->doctor_name ?? auth()->user()->doctor_name }}</div>
        <div class="rpt-sig-title">{{ $c['clinic_name'] ?? config('app.name') }}</div>
    </div>
</div>

@endsection
