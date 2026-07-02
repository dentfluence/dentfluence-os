@extends('layouts.app')

@section('page-title', isset($consultation) ? 'Edit COHA Report' : 'New COHA Assessment')

@section('head-extra')
<style>
    #df-topbar        { display:none !important; }
    #df-content-inner { padding:0 !important; max-width:100% !important; }
    #df-content-area  { background:#f0f9ff !important; }
    * { box-sizing:border-box; }
    [x-cloak] { display:none !important; }

    /* ── Topbar ── */
    #ctopbar {
        position:sticky; top:0; z-index:100; background:#fff;
        border-bottom:1px solid #e5e7eb;
        height:52px; display:flex; align-items:center;
        justify-content:space-between; padding:0 24px;
    }
    .ctb-left  { display:flex; align-items:center; gap:12px; }
    /* Shared "← Back" button — identical across all consultation workflows */
    .btn-outline {
        padding:6px 14px; font-size:12px; font-weight:600; font-family:'Inter',sans-serif;
        border:1px solid #d1d5db; background:#fff; color:#6b7280;
        border-radius:5px; cursor:pointer; text-decoration:none; transition:all .15s;
    }
    .btn-outline:hover { border-color:#6a0f70; color:#6a0f70; }
    .ctb-title { font-size:13px; font-weight:600; color:#374151; font-family:'Inter',sans-serif; }
    .ctb-sub   { font-size:11px; color:#9ca3af; font-family:'Inter',sans-serif; }
    .ctb-right { display:flex; align-items:center; gap:8px; }
    .btn-save {
        padding:6px 20px; font-size:12px; font-weight:700; font-family:'Inter',sans-serif;
        background:#0e7490; color:#fff; border:none;
        border-radius:5px; cursor:pointer; transition:background .15s;
    }
    .btn-save:hover { background:#0c6282; }
    .btn-cancel {
        padding:6px 16px; font-size:12px; font-weight:600; font-family:'Inter',sans-serif;
        border:1px solid #d1d5db; background:#fff; color:#6b7280;
        border-radius:5px; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center;
    }
    .btn-cancel:hover { border-color:#6b7280; color:#374151; }

    /* ── COHA badge ── */
    .coha-badge {
        display:inline-flex; align-items:center; gap:5px;
        background:#ecfeff; border:1px solid #a5f3fc; border-radius:20px;
        padding:3px 10px; font-size:11px; font-weight:700;
        color:#0e7490; font-family:'Inter',sans-serif; letter-spacing:.4px;
    }

    /* ── Layout ── */
    .coha-wrap { max-width:960px; margin:0 auto; padding:24px 24px 60px; }

    .coha-patient-bar {
        background:#fff; border:1px solid #e5e7eb; border-radius:8px;
        padding:14px 20px; display:flex; align-items:center; gap:16px;
        margin-bottom:20px; font-family:'Inter',sans-serif;
    }
    .coha-patient-name { font-size:14px; font-weight:700; color:#111827; }
    .coha-patient-meta { font-size:12px; color:#6b7280; }

    /* ── Section card ── */
    .coha-card {
        background:#fff; border:1px solid #e5e7eb; border-radius:8px;
        margin-bottom:14px; overflow:hidden;
    }
    .coha-card-head {
        display:flex; align-items:center; gap:10px;
        padding:12px 16px; background:#f8fafc; border-bottom:1px solid #e5e7eb;
        cursor:pointer; user-select:none;
    }
    .coha-num {
        display:inline-flex; align-items:center; justify-content:center;
        width:22px; height:22px; border-radius:50%;
        background:#0e7490; color:#fff; font-size:11px; font-weight:700;
        font-family:'Inter',sans-serif; flex-shrink:0;
    }
    .coha-head-label {
        font-size:12px; font-weight:700; color:#0e7490; letter-spacing:.5px;
        text-transform:uppercase; font-family:'Inter',sans-serif;
        flex:1;
    }
    .coha-chevron {
        transition:transform .2s; color:#9ca3af; flex-shrink:0;
    }
    .coha-body { padding:16px; display:flex; flex-direction:column; gap:12px; }

    /* ── Field grid ── */
    .coha-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .coha-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; }
    .coha-grid-4 { display:grid; grid-template-columns:1fr 1fr 1fr 1fr; gap:8px; }
    @media(max-width:640px) {
        .coha-grid, .coha-grid-3, .coha-grid-4 { grid-template-columns:1fr; }
    }

    .df-label {
        display:block; font-size:11px; font-weight:600; color:#374151;
        margin-bottom:4px; font-family:'Inter',sans-serif;
    }
    .df-select, .df-input {
        width:100%; padding:7px 10px; font-size:12px;
        border:1px solid #d1d5db; border-radius:5px; color:#374151;
        font-family:'Inter',sans-serif; background:#fff;
        transition:border-color .15s;
    }
    .df-select:focus, .df-input:focus {
        outline:none; border-color:#0e7490;
        box-shadow:0 0 0 2px rgba(14,116,144,.1);
    }

    /* ── Risk pills ── */
    .risk-row { display:flex; align-items:center; gap:10px; }
    .risk-label { font-size:12px; color:#374151; font-family:'Inter',sans-serif; flex:1; }
    .risk-options { display:flex; gap:6px; }
    .risk-btn {
        padding:4px 12px; font-size:11px; font-weight:600;
        border:1px solid #d1d5db; border-radius:20px; cursor:pointer;
        background:#fff; font-family:'Inter',sans-serif;
        transition:all .15s;
    }
    .risk-btn[data-level="low"].active   { background:#dcfce7; border-color:#16a34a; color:#15803d; }
    .risk-btn[data-level="medium"].active{ background:#fef9c3; border-color:#ca8a04; color:#92400e; }
    .risk-btn[data-level="high"].active  { background:#fee2e2; border-color:#dc2626; color:#991b1b; }
    .risk-btn:not(.active):hover         { border-color:#9ca3af; }

    /* ── Tooth grid ── */
    .tooth-grid {
        display:grid; grid-template-columns:repeat(8,1fr); gap:4px;
    }
    .tooth-grid-row { display:contents; }
    .tooth-cell {
        display:flex; flex-direction:column; align-items:center; gap:2px;
    }
    .tooth-num {
        font-size:9px; font-weight:700; color:#6b7280; font-family:'Inter',sans-serif;
    }
    .tooth-select {
        width:100%; font-size:9px; padding:2px 2px;
        border:1px solid #d1d5db; border-radius:3px; color:#374151;
        background:#fff; font-family:'Inter',sans-serif;
        appearance:none; text-align:center; cursor:pointer;
    }
    .tooth-select:focus { outline:none; border-color:#0e7490; }
    .tooth-select.has-finding { background:#fff7ed; border-color:#f97316; }

    .tooth-quadrant-label {
        font-size:10px; font-weight:700; color:#0e7490; font-family:'Inter',sans-serif;
        padding:4px 0 2px; text-align:center;
    }
    .tooth-divider { border:none; border-top:1px dashed #e5e7eb; margin:6px 0; }

    /* ── Awareness toggles ── */
    .aware-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:8px; }
    .aware-item {
        display:flex; align-items:center; gap:8px; padding:8px 12px;
        border:1px solid #e5e7eb; border-radius:6px; cursor:pointer;
        font-family:'Inter',sans-serif; font-size:12px; color:#374151;
        transition:all .15s; user-select:none;
    }
    .aware-item.active {
        background:#ecfeff; border-color:#0e7490; color:#0e7490; font-weight:600;
    }
    .aware-item:hover:not(.active) { border-color:#9ca3af; }
    .aware-checkbox { width:14px; height:14px; accent-color:#0e7490; cursor:pointer; }

    /* ── Monitoring teeth tag input ── */
    .monitor-tags { display:flex; flex-wrap:wrap; gap:6px; margin-top:6px; }
    .monitor-tag {
        display:inline-flex; align-items:center; gap:4px;
        background:#ecfeff; border:1px solid #a5f3fc; border-radius:20px;
        padding:3px 10px 3px 10px; font-size:11px; font-weight:600; color:#0e7490;
        font-family:'Inter',sans-serif;
    }
    .monitor-tag button {
        background:none; border:none; cursor:pointer; color:#0e7490;
        padding:0; font-size:12px; line-height:1; display:flex; align-items:center;
    }
    .monitor-tag button:hover { color:#991b1b; }

    /* ── Header fields ── */
    .coha-header-grid {
        display:grid; grid-template-columns:1fr 1fr 1fr; gap:12px;
        background:#fff; border:1px solid #e5e7eb; border-radius:8px;
        padding:16px; margin-bottom:14px;
    }
    @media(max-width:640px) { .coha-header-grid { grid-template-columns:1fr; } }
</style>
@endsection

@section('content')
<div x-data="cohaApp()" x-init="init()">

{{-- ── Topbar ── --}}
<div id="ctopbar">
    <div class="ctb-left">
        <a href="{{ route('patients.show', $patient) }}#consultation" class="btn-outline">← Back</a>
        <div>
            <div class="ctb-title">COHA — Comprehensive Oral Health Assessment</div>
            <div class="ctb-sub">{{ $patient->name }}</div>
        </div>
        <span class="coha-badge">
            <svg width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2M9 5a2 2 0 0 0 4 0M9 5a2 2 0 0 1 4 0"/></svg>
            Patient Awareness Report
        </span>
    </div>
    <div class="ctb-right">
        <a href="{{ route('patients.show', $patient) }}#consultation" class="btn-cancel">Cancel</a>
        <button type="button" class="btn-save" @click="submitForm()">
            Save &amp; View Report →
        </button>
    </div>
</div>

{{-- ── Success flash ── --}}
@if(session('success'))
<div style="background:#d1fae5;border-bottom:1px solid #6ee7b7;padding:10px 24px;font-size:12px;color:#065f46;font-family:'Inter',sans-serif;">
    {{ session('success') }}
</div>
@endif

{{-- ── Form ── --}}
<form id="coha-form"
      action="{{ isset($consultation) ? route('coha.update', [$patient, $consultation]) : route('coha.store', $patient) }}"
      method="POST">
    @csrf
    @if(isset($consultation)) @method('PUT') @endif

    <div class="coha-wrap">

        {{-- ── Assessment header ── --}}
        <div class="coha-header-grid">
            <div>
                <label class="df-label">Doctor</label>
                <select name="doctor_id" class="df-select">
                    @foreach($doctors as $doc)
                    <option value="{{ $doc->id }}" {{ (isset($consultation) ? $consultation->doctor_id : auth()->id()) == $doc->id ? 'selected' : '' }}>
                        {{ $doc->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="df-label">Assessment Date</label>
                <input type="date" name="consultation_date" class="df-input"
                       value="{{ isset($consultation) ? $consultation->consultation_date->format('Y-m-d') : now()->format('Y-m-d') }}">
            </div>
            <div>
                <label class="df-label">Patient</label>
                <input type="text" class="df-input" value="{{ $patient->name }}" disabled
                       style="background:#f9fafb;color:#6b7280;">
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 1 — EXTRAORAL EXAMINATION
        ════════════════════════════════════════════════════════════ --}}
        <div class="coha-card">
            <div class="coha-card-head" @click="toggleSection('extraoral')">
                <span class="coha-num">1</span>
                <span class="coha-head-label">Extraoral Examination</span>
                <svg class="coha-chevron" :style="sections.extraoral ? 'transform:rotate(180deg)' : ''"
                     width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
            <div class="coha-body" x-show="sections.extraoral" x-cloak>
                <div class="coha-grid">
                    <div>
                        <label class="df-label">TMJ (Temporomandibular Joint)</label>
                        <select name="extraoral[tmj]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['normal'=>'Normal','click'=>'Click','crepitus'=>'Crepitus','deviation'=>'Deviation on Opening','tenderness'=>'Tenderness'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->extraoral['tmj'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Muscles of Mastication</label>
                        <select name="extraoral[muscles]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['normal'=>'Normal','tender_masseter'=>'Tender — Masseter','tender_temporal'=>'Tender — Temporalis','hypertrophy'=>'Hypertrophy','spasm'=>'Spasm'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->extraoral['muscles'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Lymph Nodes</label>
                        <select name="extraoral[lymph_nodes]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['not_palpable'=>'Not Palpable','palpable_soft'=>'Palpable — Soft / Mobile','palpable_firm'=>'Palpable — Firm','enlarged'=>'Enlarged'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->extraoral['lymph_nodes'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Facial Symmetry</label>
                        <select name="extraoral[facial_symmetry]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['symmetric'=>'Symmetric','mild_asymmetry'=>'Mild Asymmetry','marked_asymmetry'=>'Marked Asymmetry'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->extraoral['facial_symmetry'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Facial Profile</label>
                        <select name="extraoral[facial_profile]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['straight'=>'Straight','convex'=>'Convex','concave'=>'Concave'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->extraoral['facial_profile'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Lips at Rest</label>
                        <select name="extraoral[lips_rest]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['competent'=>'Competent','incompetent'=>'Incompetent','everted'=>'Everted'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->extraoral['lips_rest'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 2 — SOFT TISSUE EXAMINATION
        ════════════════════════════════════════════════════════════ --}}
        <div class="coha-card">
            <div class="coha-card-head" @click="toggleSection('soft_tissue')">
                <span class="coha-num">2</span>
                <span class="coha-head-label">Soft Tissue Examination</span>
                <svg class="coha-chevron" :style="sections.soft_tissue ? 'transform:rotate(180deg)' : ''"
                     width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
            <div class="coha-body" x-show="sections.soft_tissue" x-cloak>
                @php
                $softFields = [
                    'lips'            => 'Lips',
                    'buccal_mucosa'   => 'Buccal Mucosa',
                    'tongue'          => 'Tongue',
                    'floor_of_mouth'  => 'Floor of Mouth',
                    'hard_palate'     => 'Hard Palate',
                    'soft_palate'     => 'Soft Palate',
                    'oropharynx'      => 'Oropharynx',
                    'salivary_glands' => 'Salivary Glands',
                ];
                $softOpts = ['normal'=>'Normal','erythema'=>'Erythema','ulceration'=>'Ulceration',
                             'white_patch'=>'White Patch','swelling'=>'Swelling','pigmentation'=>'Pigmentation'];
                @endphp
                <div class="coha-grid">
                    @foreach($softFields as $field => $label)
                    <div>
                        <label class="df-label">{{ $label }}</label>
                        <select name="soft_tissue[{{ $field }}]" class="df-select">
                            <option value="">— select —</option>
                            @foreach($softOpts as $v => $l)
                            <option value="{{ $v }}" {{ ($cohaReport->soft_tissue[$field] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endforeach
                </div>
                <div>
                    <label class="df-label">Oral Cancer Screening</label>
                    <select name="soft_tissue[oral_cancer_screening]" class="df-select">
                        <option value="">— select —</option>
                        @foreach(['negative'=>'Negative — No suspicious findings','leukoplakia'=>'Leukoplakia (white patch)','erythroplakia'=>'Erythroplakia (red patch)','mixed'=>'Mixed lesion','suspicious'=>'Suspicious — Refer for biopsy'] as $v=>$l)
                        <option value="{{ $v }}" {{ ($cohaReport->soft_tissue['oral_cancer_screening'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 3 — TOOTH ASSESSMENT (FDI Chart)
        ════════════════════════════════════════════════════════════ --}}
        <div class="coha-card">
            <div class="coha-card-head" @click="toggleSection('tooth_assessment')">
                <span class="coha-num">3</span>
                <span class="coha-head-label">Tooth Assessment</span>
                <svg class="coha-chevron" :style="sections.tooth_assessment ? 'transform:rotate(180deg)' : ''"
                     width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
            <div class="coha-body" x-show="sections.tooth_assessment" x-cloak>
                <div style="font-size:11px;color:#6b7280;font-family:'Inter',sans-serif;margin-bottom:4px;">
                    Mark the status of each tooth. Leave blank for teeth not examined.
                </div>
                @php
                $toothStatus = [''=>'—','sound'=>'Sound','caries'=>'Caries','fracture'=>'Fracture',
                    'root_stump'=>'Root Stump','crown'=>'Crown','bridge'=>'Bridge',
                    'implant'=>'Implant','denture'=>'Denture','wear'=>'Wear','restoration'=>'Restoration',
                    'missing'=>'Missing'];
                $upperRight = [18,17,16,15,14,13,12,11];
                $upperLeft  = [21,22,23,24,25,26,27,28];
                $lowerRight = [48,47,46,45,44,43,42,41];
                $lowerLeft  = [31,32,33,34,35,36,37,38];
                @endphp

                {{-- Upper arch --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <div>
                        <div class="tooth-quadrant-label">Upper Right (1)</div>
                        <div class="tooth-grid">
                            @foreach($upperRight as $t)
                            <div class="tooth-cell">
                                <span class="tooth-num">{{ $t }}</span>
                                <select name="tooth_assessment[{{ $t }}]" class="tooth-select"
                                        @change="toothChanged($event)">
                                    @foreach($toothStatus as $v=>$l)
                                    <option value="{{ $v }}" {{ ($cohaReport->tooth_assessment[$t] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <div class="tooth-quadrant-label">Upper Left (2)</div>
                        <div class="tooth-grid">
                            @foreach($upperLeft as $t)
                            <div class="tooth-cell">
                                <span class="tooth-num">{{ $t }}</span>
                                <select name="tooth_assessment[{{ $t }}]" class="tooth-select"
                                        @change="toothChanged($event)">
                                    @foreach($toothStatus as $v=>$l)
                                    <option value="{{ $v }}" {{ ($cohaReport->tooth_assessment[$t] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <hr class="tooth-divider">

                {{-- Lower arch --}}
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                    <div>
                        <div class="tooth-quadrant-label">Lower Right (4)</div>
                        <div class="tooth-grid">
                            @foreach($lowerRight as $t)
                            <div class="tooth-cell">
                                <span class="tooth-num">{{ $t }}</span>
                                <select name="tooth_assessment[{{ $t }}]" class="tooth-select"
                                        @change="toothChanged($event)">
                                    @foreach($toothStatus as $v=>$l)
                                    <option value="{{ $v }}" {{ ($cohaReport->tooth_assessment[$t] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <div class="tooth-quadrant-label">Lower Left (3)</div>
                        <div class="tooth-grid">
                            @foreach($lowerLeft as $t)
                            <div class="tooth-cell">
                                <span class="tooth-num">{{ $t }}</span>
                                <select name="tooth_assessment[{{ $t }}]" class="tooth-select"
                                        @change="toothChanged($event)">
                                    @foreach($toothStatus as $v=>$l)
                                    <option value="{{ $v }}" {{ ($cohaReport->tooth_assessment[$t] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 4 — ORTHODONTIC FINDINGS
        ════════════════════════════════════════════════════════════ --}}
        <div class="coha-card">
            <div class="coha-card-head" @click="toggleSection('ortho')">
                <span class="coha-num">4</span>
                <span class="coha-head-label">Orthodontic Findings</span>
                <svg class="coha-chevron" :style="sections.ortho ? 'transform:rotate(180deg)' : ''"
                     width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
            <div class="coha-body" x-show="sections.ortho" x-cloak>
                <div class="coha-grid">
                    <div>
                        <label class="df-label">Crowding</label>
                        <select name="ortho_findings[crowding]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['none'=>'None','mild'=>'Mild','moderate'=>'Moderate','severe'=>'Severe'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->ortho_findings['crowding'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Spacing</label>
                        <select name="ortho_findings[spacing]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['none'=>'None','mild'=>'Mild','moderate'=>'Moderate','severe'=>'Severe','diastema'=>'Diastema'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->ortho_findings['spacing'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Overjet</label>
                        <select name="ortho_findings[overjet]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['normal'=>'Normal (2–4 mm)','increased'=>'Increased (>4 mm)','reduced'=>'Reduced','negative'=>'Negative (Reverse)'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->ortho_findings['overjet'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Overbite</label>
                        <select name="ortho_findings[overbite]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['normal'=>'Normal','deep'=>'Deep Bite','reduced'=>'Reduced','open'=>'Open Bite'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->ortho_findings['overbite'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Midline</label>
                        <select name="ortho_findings[midline]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['coincident'=>'Coincident','shifted_upper'=>'Shifted — Upper','shifted_lower'=>'Shifted — Lower','both_shifted'=>'Both Shifted'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->ortho_findings['midline'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Molar Relation</label>
                        <select name="ortho_findings[molar_relation]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['class1'=>'Class I','class2_div1'=>'Class II Div 1','class2_div2'=>'Class II Div 2','class3'=>'Class III'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->ortho_findings['molar_relation'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Crossbite</label>
                        <select name="ortho_findings[crossbite]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['absent'=>'Absent','anterior'=>'Anterior','posterior_right'=>'Posterior — Right','posterior_left'=>'Posterior — Left','bilateral'=>'Bilateral'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->ortho_findings['crossbite'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Skeletal Pattern</label>
                        <select name="ortho_findings[skeletal]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['class1'=>'Class I','class2'=>'Class II','class3'=>'Class III'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->ortho_findings['skeletal'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 5 — PERIODONTAL FINDINGS
        ════════════════════════════════════════════════════════════ --}}
        <div class="coha-card">
            <div class="coha-card-head" @click="toggleSection('perio')">
                <span class="coha-num">5</span>
                <span class="coha-head-label">Periodontal Findings</span>
                <svg class="coha-chevron" :style="sections.perio ? 'transform:rotate(180deg)' : ''"
                     width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
            <div class="coha-body" x-show="sections.perio" x-cloak>
                <div class="coha-grid">
                    <div>
                        <label class="df-label">Bleeding on Probing (BOP)</label>
                        <select name="perio_findings[bop]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['absent'=>'Absent','localized'=>'Localized (<30%)','generalized'=>'Generalized (≥30%)'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->perio_findings['bop'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Probing Depth</label>
                        <select name="perio_findings[pocket_depth]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['wnl'=>'WNL (≤3 mm)','early'=>'4–5 mm (Early)','moderate'=>'5–6 mm (Moderate)','severe'=>'≥7 mm (Severe)'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->perio_findings['pocket_depth'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Recession</label>
                        <select name="perio_findings[recession]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['none'=>'None','localized'=>'Localized','generalized'=>'Generalized'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->perio_findings['recession'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Tooth Mobility</label>
                        <select name="perio_findings[mobility]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['none'=>'None','grade1'=>'Grade I (< 1mm)','grade2'=>'Grade II (1–2 mm)','grade3'=>'Grade III (>2 mm / vertical)'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->perio_findings['mobility'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Furcation Involvement</label>
                        <select name="perio_findings[furcation]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['none'=>'None','class1'=>'Class I','class2'=>'Class II','class3'=>'Class III'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->perio_findings['furcation'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Calculus</label>
                        <select name="perio_findings[calculus]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['none'=>'None','mild'=>'Mild','moderate'=>'Moderate','heavy'=>'Heavy'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->perio_findings['calculus'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Plaque Control</label>
                        <select name="perio_findings[plaque_control]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['good'=>'Good','fair'=>'Fair','poor'=>'Poor'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->perio_findings['plaque_control'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Gingival Condition</label>
                        <select name="perio_findings[gingival_condition]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['healthy'=>'Healthy','inflamed'=>'Inflamed','hyperplastic'=>'Hyperplastic','fibrotic'=>'Fibrotic'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->perio_findings['gingival_condition'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 6 — ESTHETIC FINDINGS
        ════════════════════════════════════════════════════════════ --}}
        <div class="coha-card">
            <div class="coha-card-head" @click="toggleSection('esthetic')">
                <span class="coha-num">6</span>
                <span class="coha-head-label">Esthetic Findings</span>
                <svg class="coha-chevron" :style="sections.esthetic ? 'transform:rotate(180deg)' : ''"
                     width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
            <div class="coha-body" x-show="sections.esthetic" x-cloak>
                <div class="coha-grid">
                    <div>
                        <label class="df-label">Tooth Shade</label>
                        <select name="esthetic_findings[shade]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['a1'=>'A1','a2'=>'A2','a3'=>'A3','a3_5'=>'A3.5','a4'=>'A4','b1'=>'B1','b2'=>'B2','c1'=>'C1','c2'=>'C2','d2'=>'D2','stained'=>'Stained'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->esthetic_findings['shade'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Discolouration</label>
                        <select name="esthetic_findings[discolouration]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['none'=>'None','extrinsic'=>'Extrinsic','intrinsic'=>'Intrinsic','fluorosis'=>'Fluorosis','tetracycline'=>'Tetracycline Staining'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->esthetic_findings['discolouration'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Smile Line</label>
                        <select name="esthetic_findings[smile_line]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['low'=>'Low','average'=>'Average','high'=>'High','gummy'=>'Gummy (>3 mm)'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->esthetic_findings['smile_line'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Tooth Proportions</label>
                        <select name="esthetic_findings[tooth_proportion]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['ideal'=>'Ideal','short'=>'Short','long'=>'Long','wide'=>'Wide'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->esthetic_findings['tooth_proportion'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Wear / Attrition</label>
                        <select name="esthetic_findings[wear]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['none'=>'None','mild'=>'Mild','moderate'=>'Moderate','severe'=>'Severe (Bruxism)'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->esthetic_findings['wear'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="df-label">Buccal Corridor</label>
                        <select name="esthetic_findings[buccal_corridor]" class="df-select">
                            <option value="">— select —</option>
                            @foreach(['narrow'=>'Narrow','average'=>'Average','wide'=>'Wide'] as $v=>$l)
                            <option value="{{ $v }}" {{ ($cohaReport->esthetic_findings['buccal_corridor'] ?? '') === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 7 — RISK ASSESSMENT
        ════════════════════════════════════════════════════════════ --}}
        <div class="coha-card">
            <div class="coha-card-head" @click="toggleSection('risk')">
                <span class="coha-num">7</span>
                <span class="coha-head-label">Risk Assessment</span>
                <svg class="coha-chevron" :style="sections.risk ? 'transform:rotate(180deg)' : ''"
                     width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
            <div class="coha-body" x-show="sections.risk" x-cloak>
                @php
                $risks = [
                    'caries'      => 'Caries Risk',
                    'perio'       => 'Periodontal Risk',
                    'bruxism'     => 'Bruxism / Wear Risk',
                    'oral_cancer' => 'Oral Cancer Risk',
                ];
                @endphp
                <div style="display:flex;flex-direction:column;gap:10px;">
                    @foreach($risks as $key => $label)
                    <div class="risk-row" x-data="{ selected: '{{ $cohaReport->risk_assessment[$key] ?? '' }}' }">
                        <span class="risk-label">{{ $label }}</span>
                        <input type="hidden" name="risk_assessment[{{ $key }}]" :value="selected">
                        <div class="risk-options">
                            @foreach(['low','medium','high'] as $level)
                            <button type="button" class="risk-btn" data-level="{{ $level }}"
                                    :class="selected === '{{ $level }}' ? 'active' : ''"
                                    @click="selected = '{{ $level }}'">
                                {{ ucfirst($level) }}
                            </button>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 8 — MONITORING TEETH
        ════════════════════════════════════════════════════════════ --}}
        <div class="coha-card">
            <div class="coha-card-head" @click="toggleSection('monitoring')">
                <span class="coha-num">8</span>
                <span class="coha-head-label">Teeth to Monitor at Next Visit</span>
                <svg class="coha-chevron" :style="sections.monitoring ? 'transform:rotate(180deg)' : ''"
                     width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
            <div class="coha-body" x-show="sections.monitoring" x-cloak
                 x-data="{ monitorTeeth: {{ json_encode($cohaReport->monitoring_teeth ?? []) }}, newTooth: '' }">
                <div style="font-size:12px;color:#6b7280;font-family:'Inter',sans-serif;margin-bottom:8px;">
                    Add FDI tooth numbers to flag for the next appointment (e.g. 16, 26, 36).
                </div>
                <div style="display:flex;gap:8px;align-items:center;">
                    <input type="text" x-model="newTooth" placeholder="Tooth number (e.g. 16)"
                           class="df-input" style="max-width:200px;"
                           @keydown.enter.prevent="if(newTooth.trim()){monitorTeeth.push(newTooth.trim());newTooth=''}"
                           inputmode="numeric">
                    <button type="button" class="btn-save" style="padding:7px 14px;font-size:12px;"
                            @click="if(newTooth.trim()){monitorTeeth.push(newTooth.trim());newTooth=''}">
                        Add
                    </button>
                </div>
                <div class="monitor-tags">
                    <template x-for="(t,i) in monitorTeeth" :key="i">
                        <span class="monitor-tag">
                            <span>Tooth <span x-text="t"></span></span>
                            <button type="button" @click="monitorTeeth.splice(i,1)">×</button>
                        </span>
                    </template>
                </div>
                {{-- Hidden inputs for form submission --}}
                <template x-for="(t,i) in monitorTeeth" :key="'h'+i">
                    <input type="hidden" :name="'monitoring_teeth[]'" :value="t">
                </template>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════
             SECTION 9 — TREATMENT AWARENESS
        ════════════════════════════════════════════════════════════ --}}
        <div class="coha-card">
            <div class="coha-card-head" @click="toggleSection('awareness')">
                <span class="coha-num">9</span>
                <span class="coha-head-label">Treatment Awareness (Patient Education)</span>
                <svg class="coha-chevron" :style="sections.awareness ? 'transform:rotate(180deg)' : ''"
                     width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 12 15 18 9"/>
                </svg>
            </div>
            <div class="coha-body" x-show="sections.awareness" x-cloak>
                <div style="font-size:12px;color:#6b7280;font-family:'Inter',sans-serif;margin-bottom:10px;">
                    Check all treatments to include in the patient's awareness report. These will be explained in plain language — not as a treatment estimate.
                </div>
                @php
                $awarenessItems = [
                    'cleaning'   => ['Cleaning & Scaling',   'Regular professional cleaning to remove tartar and prevent gum disease.'],
                    'fillings'   => ['Fillings',             'Treating cavities before they become larger problems.'],
                    'rct'        => ['Root Canal Treatment', 'Saving a badly infected or painful tooth.'],
                    'crowns'     => ['Crowns',               'Protecting a weak or broken tooth with a full-coverage cap.'],
                    'extractions'=> ['Extractions',          'Removing teeth that are beyond saving.'],
                    'implants'   => ['Implants',             'Permanently replacing missing teeth with artificial roots.'],
                    'ortho'      => ['Orthodontics',         'Straightening and aligning teeth with braces or aligners.'],
                    'perio'      => ['Gum Treatment',        'Treating gum disease to save your teeth and bone.'],
                    'whitening'  => ['Teeth Whitening',      'Professional whitening for a brighter smile.'],
                    'veneers'    => ['Veneers / Bonding',    'Cosmetic improvements to the shape and appearance of teeth.'],
                    'fluoride'   => ['Fluoride Treatment',   'Strengthening tooth enamel to prevent decay.'],
                ];
                @endphp
                <div class="aware-grid">
                    @foreach($awarenessItems as $key => [$label, $desc])
                    @php $isChecked = !empty($cohaReport->treatment_awareness[$key]); @endphp
                    <label class="aware-item {{ $isChecked ? 'active' : '' }}" x-data="{ on: {{ $isChecked ? 'true' : 'false' }} }"
                           :class="on ? 'active' : ''"
                           @click.prevent="on = !on">
                        <input type="checkbox" class="aware-checkbox"
                               name="treatment_awareness[{{ $key }}]"
                               value="1"
                               x-model="on"
                               {{ $isChecked ? 'checked' : '' }}>
                        <span>{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Doctor Notes ── --}}
        <div class="coha-card">
            <div class="coha-body">
                <label class="df-label">Doctor's Notes for Patient Report</label>
                <textarea name="doctor_notes" class="df-input" rows="4"
                          placeholder="Any additional observations or recommendations for this patient's awareness report…"
                          style="resize:vertical;">{{ old('doctor_notes', $cohaReport->doctor_notes ?? '') }}</textarea>
            </div>
        </div>

    </div>{{-- /coha-wrap --}}
</form>

</div>{{-- /x-data --}}

@push('scripts')
<script>
function cohaApp() {
    return {
        sections: {
            extraoral:       true,
            soft_tissue:     true,
            tooth_assessment:true,
            ortho:           true,
            perio:           true,
            esthetic:        true,
            risk:            true,
            monitoring:      true,
            awareness:       true,
        },

        init() {
            // Highlight tooth selects that have a non-empty finding on load
            document.querySelectorAll('.tooth-select').forEach(el => {
                if (el.value && el.value !== '') el.classList.add('has-finding');
            });
        },

        toggleSection(key) {
            this.sections[key] = !this.sections[key];
        },

        toothChanged(event) {
            const el = event.target;
            if (el.value && el.value !== '') {
                el.classList.add('has-finding');
            } else {
                el.classList.remove('has-finding');
            }
        },

        submitForm() {
            document.getElementById('coha-form').submit();
        },
    };
}
</script>
@endpush
@endsection
