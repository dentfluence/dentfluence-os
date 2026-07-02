@extends('layouts.app')
@section('page-title', 'Clinical Library Settings')

@push('styles')
<style>
/* ── Override content-inner padding so settings fills full height ── */
#df-content-inner {
    padding: 0 !important;
    height: 100%;
    display: flex;
    flex-direction: column;
}

[x-cloak] { display: none !important; }

/* ── Reuse settings design language ── */
.settings-section-title {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: #6a0f70;
    margin: 0 0 14px;
}
.settings-label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #5a4060;
    margin-bottom: 5px;
}
.settings-input {
    width: 100%;
    padding: 8px 12px;
    border: 1.5px solid #e0d4ea;
    border-radius: 7px;
    font-size: 13px;
    color: #1a0320;
    background: #fff;
    outline: none;
    transition: border-color .15s;
    font-family: inherit;
    box-sizing: border-box;
}
.settings-input:focus { border-color: #8b44aa; }
.settings-save-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 9px 20px;
    background: #6a0f70;
    color: #fff;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
    font-family: inherit;
}
.settings-save-btn:hover { background: #3a0050; }

/* ── Toggle switch ── */
.cl-toggle {
    position: relative;
    display: inline-block;
    width: 38px;
    height: 21px;
    flex-shrink: 0;
}
.cl-toggle input { opacity: 0; width: 0; height: 0; }
.cl-toggle-slider {
    position: absolute;
    inset: 0;
    background: #ddd;
    border-radius: 21px;
    cursor: pointer;
    transition: background .2s;
}
.cl-toggle-slider::before {
    content: '';
    position: absolute;
    width: 15px;
    height: 15px;
    left: 3px;
    top: 3px;
    background: #fff;
    border-radius: 50%;
    transition: transform .2s;
}
.cl-toggle input:checked + .cl-toggle-slider { background: #6a0f70; }
.cl-toggle input:checked + .cl-toggle-slider::before { transform: translateX(17px); }

/* ── Section cards ── */
.cl-card {
    background: #fff;
    border: 1.5px solid #ede4f3;
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 20px;
}

/* ── Protocol step rows ── */
.protocol-step-row {
    display: grid;
    grid-template-columns: 1fr 120px 100px 90px 32px;
    gap: 8px;
    align-items: center;
    padding: 8px 12px;
    border-radius: 8px;
    border: 1px solid #f0e8f8;
    background: #fdf9ff;
    margin-bottom: 6px;
}
.protocol-step-row:hover { border-color: #d4b8e0; }

/* ── Media category rows ── */
.media-cat-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 16px;
    border-bottom: 1px solid #f5f0f8;
}
.media-cat-row:last-child { border-bottom: none; }

/* ── Watermark template tab ── */
.wm-tab-btn {
    padding: 8px 16px;
    font-size: 12.5px;
    font-weight: 500;
    border: 1.5px solid #ede4f3;
    border-radius: 7px;
    background: #faf7fc;
    color: #7a6080;
    cursor: pointer;
    transition: all .15s;
    font-family: inherit;
}
.wm-tab-btn.active, .wm-tab-btn:hover {
    background: #f0e6f6;
    color: #6a0f70;
    border-color: #c09ad8;
}
.wm-tab-btn.active { font-weight: 700; }

/* ── Watermark preview panel ── */
.wm-preview {
    background: #1a1a2e;
    border-radius: 10px;
    position: relative;
    overflow: hidden;
    aspect-ratio: 4/3;
    display: flex;
    align-items: center;
    justify-content: center;
}
.wm-preview-img-placeholder {
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #1a1a2e 0%, #2d1b3d 50%, #1a2a1e 100%);
    display: flex;
    align-items: center;
    justify-content: center;
}
.wm-overlay-text {
    position: absolute;
    font-size: 11px;
    color: rgba(255,255,255,0.82);
    font-weight: 600;
    text-shadow: 0 1px 3px rgba(0,0,0,.7);
    pointer-events: none;
    letter-spacing: .04em;
    line-height: 1.5;
    text-align: center;
    white-space: nowrap;
}

/* ── Coming soon badge ── */
.coming-soon-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    background: #fef3c7;
    color: #92400e;
    border: 1px solid #fcd34d;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .04em;
}

/* ── Storage usage bar ── */
.storage-bar-track {
    width: 100%;
    height: 8px;
    background: #ede4f3;
    border-radius: 8px;
    overflow: hidden;
}
.storage-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #6a0f70, #9b44b4);
    border-radius: 8px;
    transition: width .4s;
}
</style>
@endpush

@section('content')
{{-- ════════════════════════════════════════════════════════════════
     CLINICAL LIBRARY SETTINGS PAGE
     Phase 6 — Static UI only. No backend wired.
════════════════════════════════════════════════════════════════ --}}
<div x-data="clinicalLibrarySettings()" x-init="init()"
     style="font-family:'Inter',sans-serif;height:100%;display:flex;flex-direction:column;background:#f7f4fa;">

{{-- ── PAGE HEADER ── --}}
<div style="padding:18px 28px 16px;background:#fff;border-bottom:1px solid #ede4f3;flex-shrink:0;display:flex;align-items:center;gap:14px;">
    <a href="{{ route('settings.index') }}"
       style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1.5px solid #e0d4ea;border-radius:8px;color:#7a6080;text-decoration:none;flex-shrink:0;transition:background .15s;"
       title="Back to Settings">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <div>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:24px;font-weight:700;color:#1a0320;margin:0 0 2px;">Clinical Library Settings</h1>
        <p style="font-size:12.5px;color:#9a7aaa;margin:0;">Configure documentation protocols, media categories, watermark templates, and storage.</p>
    </div>
</div>

{{-- ── SIDEBAR + CONTENT LAYOUT ── --}}
<div style="flex:1;display:flex;overflow:hidden;">

    {{-- ── LEFT SIDEBAR NAV ── --}}
    <div style="width:210px;border-right:1px solid #ede4f3;background:#fff;overflow-y:auto;flex-shrink:0;padding:10px 0;">

        <div style="padding:10px 20px 4px;font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#c5a8d8;">Sections</div>

        @php
        $clSections = [
            ['id' => 'protocols',       'label' => 'Documentation Protocols', 'icon' => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><line x1="9" y1="12" x2="15" y2="12"/><line x1="9" y1="16" x2="12" y2="16"/>'],
            ['id' => 'media-categories','label' => 'Media Categories',        'icon' => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8m-4-4v4"/><circle cx="8" cy="8" r="1.5"/><polyline points="21 15 16 10 8 15"/>'],
            ['id' => 'watermarks',      'label' => 'Watermark Templates',     'icon' => '<circle cx="12" cy="12" r="10"/><path d="M8 12l3 3 5-5"/>'],
            ['id' => 'classification',  'label' => 'Classification Rules',    'icon' => '<path d="M10 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/><path d="M14 3h4v4"/><path d="M14 3l6 6"/>'],
            ['id' => 'storage',         'label' => 'Storage Settings',        'icon' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/>'],
        ];
        @endphp

        @foreach($clSections as $s)
        <button @click="activeSection='{{ $s['id'] }}'"
                :class="activeSection==='{{ $s['id'] }}' ? 'snav-item snav-item--active' : 'snav-item'"
                style="display:flex;align-items:center;gap:9px;width:100%;padding:8px 20px;font-size:13px;color:#6a5870;background:none;border:none;border-right:3px solid transparent;cursor:pointer;text-align:left;transition:background 120ms,color 120ms;font-family:inherit;box-sizing:border-box;"
                :style="activeSection==='{{ $s['id'] }}' ? 'background:#f0e6f6;color:#6a0f70;font-weight:600;border-right-color:#6a0f70;' : 'background:none;color:#6a5870;border-right-color:transparent;'">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">{!! $s['icon'] !!}</svg>
            {{ $s['label'] }}
        </button>
        @endforeach
    </div>

    {{-- ── MAIN CONTENT AREA ── --}}
    <div style="flex:1;overflow-y:auto;padding:28px 36px;max-width:900px;">


        {{-- ════════════════════════════════════════════
             SECTION 1 · TREATMENT DOCUMENTATION PROTOCOLS
        ════════════════════════════════════════════ --}}
        <div x-show="activeSection==='protocols'" x-cloak>

            <div class="cl-card">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:20px;">
                    <div>
                        <h3 class="settings-section-title" style="margin:0 0 4px;">Treatment Documentation Protocols</h3>
                        <p style="font-size:12.5px;color:#9a7aaa;margin:0;">Define which files are required at each stage of a treatment. Applied automatically when a visit is created.</p>
                    </div>
                    <button @click="showAddProtocol=true"
                            style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;background:#6a0f70;color:#fff;border:none;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;margin-left:20px;">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                        New Protocol
                    </button>
                </div>

                {{-- "Apply to new visits" global toggle --}}
                <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#fdf8ff;border:1.5px solid #ede4f3;border-radius:8px;margin-bottom:20px;">
                    <div>
                        <div style="font-size:13px;font-weight:600;color:#1a0320;">Apply protocols to new visits automatically</div>
                        <div style="font-size:12px;color:#9a7aaa;margin-top:2px;">When a new visit is created, the matching protocol's steps are pre-populated in the Documents tab.</div>
                    </div>
                    <label class="cl-toggle" style="margin-left:20px;">
                        <input type="checkbox" checked>
                        <span class="cl-toggle-slider"></span>
                    </label>
                </div>

                {{-- Protocol list (static placeholder) --}}
                @php
                $protocols = [
                    ['name' => 'Root Canal Treatment', 'procedure' => 'Endodontics', 'steps' => 5, 'active' => true],
                    ['name' => 'Dental Implant Protocol', 'procedure' => 'Implantology', 'steps' => 7, 'active' => true],
                    ['name' => 'Crown & Bridge', 'procedure' => 'Prosthodontics', 'steps' => 4, 'active' => true],
                    ['name' => 'Tooth Extraction', 'procedure' => 'Oral Surgery', 'steps' => 3, 'active' => false],
                    ['name' => 'Clear Aligner Treatment', 'procedure' => 'Orthodontics', 'steps' => 6, 'active' => true],
                    ['name' => 'Scaling & Polishing', 'procedure' => 'Preventive', 'steps' => 2, 'active' => true],
                ];
                @endphp

                <div style="border:1.5px solid #ede4f3;border-radius:10px;overflow:hidden;">
                    <div style="display:grid;grid-template-columns:1fr 140px 60px 80px 80px;gap:0;padding:9px 16px;background:#faf7fc;border-bottom:1px solid #ede4f3;">
                        <span style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9a7aaa;">Protocol</span>
                        <span style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9a7aaa;">Procedure</span>
                        <span style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9a7aaa;text-align:center;">Steps</span>
                        <span style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9a7aaa;text-align:center;">Active</span>
                        <span style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9a7aaa;text-align:center;">Actions</span>
                    </div>

                    @foreach($protocols as $i => $proto)
                    <div style="display:grid;grid-template-columns:1fr 140px 60px 80px 80px;gap:0;align-items:center;padding:13px 16px;border-bottom:{{ !$loop->last ? '1px solid #f5f0f8' : 'none' }};{{ !$proto['active'] ? 'opacity:.55;' : '' }}">
                        <div>
                            <div style="font-size:13px;font-weight:500;color:#1a0320;">{{ $proto['name'] }}</div>
                        </div>
                        <div>
                            <span style="font-size:12px;color:#7a6080;padding:2px 8px;background:#f0e8f8;border-radius:4px;">{{ $proto['procedure'] }}</span>
                        </div>
                        <div style="text-align:center;">
                            <span style="font-size:13px;color:#6a0f70;font-weight:700;">{{ $proto['steps'] }}</span>
                        </div>
                        <div style="text-align:center;">
                            <label class="cl-toggle">
                                <input type="checkbox" {{ $proto['active'] ? 'checked' : '' }}>
                                <span class="cl-toggle-slider"></span>
                            </label>
                        </div>
                        <div style="display:flex;align-items:center;gap:6px;justify-content:center;">
                            <button @click="openProtocol({{ $i }})"
                                    style="padding:4px 10px;border:1.5px solid #d4b8e0;border-radius:6px;background:#fff;color:#6a0f70;font-size:11px;font-weight:600;cursor:pointer;">
                                Edit Steps
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            {{-- ── Step Builder Panel (shown when a protocol is selected) ── --}}
            <div x-show="selectedProtocol !== null" x-cloak class="cl-card" style="border-color:#c09ad8;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                    <div>
                        <h3 class="settings-section-title" style="margin:0 0 2px;">Step Builder</h3>
                        <p style="font-size:12.5px;color:#9a7aaa;margin:0;" x-text="'Protocol: ' + (selectedProtocol !== null ? protocols[selectedProtocol].name : '')"></p>
                    </div>
                    <button @click="selectedProtocol=null"
                            style="display:inline-flex;align-items:center;gap:5px;padding:6px 12px;border:1.5px solid #ede4f3;border-radius:7px;background:#fff;color:#7a6080;font-size:12px;cursor:pointer;">
                        <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 6L6 18m0-12l12 12"/></svg>
                        Close
                    </button>
                </div>

                {{-- Step rows (static placeholder for the selected protocol) --}}
                <div style="margin-bottom:14px;">
                    <div style="display:grid;grid-template-columns:1fr 120px 100px 90px 32px;gap:8px;padding:7px 12px;background:#faf7fc;border-radius:6px;margin-bottom:8px;">
                        <span style="font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9a7aaa;">Step Name</span>
                        <span style="font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9a7aaa;">File Type</span>
                        <span style="font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9a7aaa;">Stage</span>
                        <span style="font-size:10.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9a7aaa;">Required?</span>
                        <span></span>
                    </div>

                    @php
                    $sampleSteps = [
                        ['name' => 'Pre-treatment X-ray', 'type' => 'X-ray', 'stage' => 'Before', 'required' => true],
                        ['name' => 'Consent Form', 'type' => 'Consent', 'stage' => 'Before', 'required' => true],
                        ['name' => 'Working Length X-ray', 'type' => 'X-ray', 'stage' => 'During', 'required' => true],
                        ['name' => 'Procedure Photo', 'type' => 'Photo', 'stage' => 'During', 'required' => false],
                        ['name' => 'Post-op X-ray', 'type' => 'X-ray', 'stage' => 'After', 'required' => true],
                    ];
                    @endphp

                    @foreach($sampleSteps as $step)
                    <div class="protocol-step-row">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <svg width="12" height="12" fill="none" stroke="#c5a8d8" stroke-width="2" viewBox="0 0 24 24" style="cursor:grab;flex-shrink:0;"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>
                            <span style="font-size:13px;color:#1a0320;">{{ $step['name'] }}</span>
                        </div>
                        <span style="font-size:12px;padding:2px 8px;background:#e8e0f4;color:#5a3880;border-radius:4px;display:inline-block;">{{ $step['type'] }}</span>
                        <span style="font-size:12px;padding:2px 8px;background:{{ $step['stage']==='Before' ? '#dbeafe' : ($step['stage']==='During' ? '#fef3c7' : '#d1fae5') }};color:{{ $step['stage']==='Before' ? '#1e40af' : ($step['stage']==='During' ? '#92400e' : '#065f46') }};border-radius:4px;display:inline-block;">{{ $step['stage'] }}</span>
                        <span style="font-size:12px;color:{{ $step['required'] ? '#6a0f70' : '#9a7aaa' }};font-weight:{{ $step['required'] ? '600' : '400' }};">{{ $step['required'] ? 'Required' : 'Optional' }}</span>
                        <button style="background:none;border:none;cursor:pointer;color:#d4b8e0;padding:0;line-height:1;font-size:16px;" title="Remove step">×</button>
                    </div>
                    @endforeach
                </div>

                {{-- Add Step form --}}
                <div style="background:#fdf8ff;border:1.5px dashed #d4b8e0;border-radius:8px;padding:14px;">
                    <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9a7aaa;margin:0 0 10px;">Add Step</p>
                    <div style="display:grid;grid-template-columns:1fr 120px 100px 90px auto;gap:8px;align-items:flex-end;">
                        <div>
                            <label class="settings-label">Step Name</label>
                            <input type="text" class="settings-input" placeholder="e.g. Pre-op photo">
                        </div>
                        <div>
                            <label class="settings-label">File Type</label>
                            <select class="settings-input">
                                <option>Photo</option>
                                <option>Video</option>
                                <option>X-ray</option>
                                <option>OPG</option>
                                <option>CBCT</option>
                                <option>STL</option>
                                <option>PDF</option>
                                <option>Consent</option>
                            </select>
                        </div>
                        <div>
                            <label class="settings-label">Stage</label>
                            <select class="settings-input">
                                <option>Before</option>
                                <option>During</option>
                                <option>After</option>
                                <option>Follow-up</option>
                                <option>General</option>
                            </select>
                        </div>
                        <div>
                            <label class="settings-label">Required?</label>
                            <select class="settings-input">
                                <option>Required</option>
                                <option>Optional</option>
                            </select>
                        </div>
                        <button class="settings-save-btn" style="padding:8px 16px;white-space:nowrap;margin-top:auto;">
                            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                            Add
                        </button>
                    </div>
                </div>

                <div style="margin-top:16px;display:flex;gap:10px;">
                    <button class="settings-save-btn">Save Protocol Steps</button>
                    <button @click="selectedProtocol=null"
                            style="padding:9px 20px;border:1.5px solid #ddd;background:#fff;border-radius:8px;font-size:13px;color:#7a6080;cursor:pointer;">Cancel</button>
                </div>
            </div>

            {{-- Add Protocol Modal --}}
            <div x-show="showAddProtocol" x-cloak
                 style="position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;background:rgba(14,1,24,.45);"
                 @click.self="showAddProtocol=false">
                <div style="background:#fff;border-radius:12px;width:440px;padding:28px;box-shadow:0 20px 60px rgba(14,1,24,.25);">
                    <h2 style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:700;color:#1a0320;margin:0 0 20px;">New Documentation Protocol</h2>
                    <div style="margin-bottom:13px;">
                        <label class="settings-label">Protocol Name *</label>
                        <input type="text" class="settings-input" placeholder="e.g. Dental Implant Protocol">
                    </div>
                    <div style="margin-bottom:13px;">
                        <label class="settings-label">Procedure / Treatment Type</label>
                        <select class="settings-input">
                            <option value="">— Select procedure —</option>
                            <option>Endodontics</option>
                            <option>Implantology</option>
                            <option>Prosthodontics</option>
                            <option>Oral Surgery</option>
                            <option>Orthodontics</option>
                            <option>Preventive</option>
                            <option>Periodontics</option>
                            <option>Cosmetic Dentistry</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div style="margin-bottom:20px;">
                        <label class="settings-label">Description (optional)</label>
                        <input type="text" class="settings-input" placeholder="Brief description of this protocol">
                    </div>
                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button @click="showAddProtocol=false" style="padding:8px 18px;border:1.5px solid #ddd;background:#fff;border-radius:6px;font-size:13px;cursor:pointer;color:#555;">Cancel</button>
                        <button @click="showAddProtocol=false" class="settings-save-btn" style="padding:8px 18px;">Create Protocol</button>
                    </div>
                </div>
            </div>

        </div>{{-- /protocols --}}


        {{-- ════════════════════════════════════════════
             SECTION 2 · MEDIA CATEGORIES
        ════════════════════════════════════════════ --}}
        <div x-show="activeSection==='media-categories'" x-cloak>

            <div class="cl-card">
                <div style="margin-bottom:18px;">
                    <h3 class="settings-section-title" style="margin:0 0 4px;">Media Categories</h3>
                    <p style="font-size:12.5px;color:#9a7aaa;margin:0;">Control which file types appear in the upload form. Disabled categories are hidden from staff during upload.</p>
                </div>

                @php
                $mediaCategories = [
                    ['type' => 'Photo',         'icon_bg' => '#dbeafe', 'icon_color' => '#1e40af', 'desc' => 'Clinical photos, before/after images',    'enabled' => true],
                    ['type' => 'Video',         'icon_bg' => '#d1fae5', 'icon_color' => '#065f46', 'desc' => 'Procedure recordings, patient videos',       'enabled' => true],
                    ['type' => 'X-ray',         'icon_bg' => '#ede9fe', 'icon_color' => '#5b21b6', 'desc' => 'Periapical, bitewing X-rays',               'enabled' => true],
                    ['type' => 'OPG',           'icon_bg' => '#fce7f3', 'icon_color' => '#9d174d', 'desc' => 'Panoramic radiographs',                      'enabled' => true],
                    ['type' => 'CBCT',          'icon_bg' => '#fef3c7', 'icon_color' => '#92400e', 'desc' => 'Cone beam CT scans',                         'enabled' => true],
                    ['type' => 'STL / 3D Scan', 'icon_bg' => '#ecfdf5', 'icon_color' => '#064e3b', 'desc' => 'Intraoral scans, study models',             'enabled' => false],
                    ['type' => 'PDF Document',  'icon_bg' => '#fff7ed', 'icon_color' => '#9a3412', 'desc' => 'Lab slips, referral letters, reports',       'enabled' => true],
                    ['type' => 'Consent Form',  'icon_bg' => '#fef2f2', 'icon_color' => '#991b1b', 'desc' => 'Signed patient consent documents',           'enabled' => true],
                    ['type' => 'Other',         'icon_bg' => '#f9fafb', 'icon_color' => '#6b7280', 'desc' => 'Miscellaneous clinical files',               'enabled' => true],
                ];
                @endphp

                <div style="border:1.5px solid #ede4f3;border-radius:10px;overflow:hidden;">
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 16px;background:#faf7fc;border-bottom:1px solid #ede4f3;">
                        <span style="font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#9a7aaa;">{{ count($mediaCategories) }} file types configured</span>
                        <button style="font-size:11.5px;color:#6a0f70;background:none;border:none;cursor:pointer;font-weight:600;padding:0;">Enable All</button>
                    </div>

                    @foreach($mediaCategories as $cat)
                    <div class="media-cat-row">
                        <div style="display:flex;align-items:center;gap:12px;flex:1;">
                            {{-- Drag handle --}}
                            <svg width="12" height="12" fill="none" stroke="#c5a8d8" stroke-width="2" viewBox="0 0 24 24" style="cursor:grab;flex-shrink:0;"><circle cx="9" cy="5" r="1"/><circle cx="9" cy="12" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="19" r="1"/></svg>
                            {{-- Color dot --}}
                            <div style="width:32px;height:32px;border-radius:8px;background:{{ $cat['icon_bg'] }};display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                                <svg width="14" height="14" fill="none" stroke="{{ $cat['icon_color'] }}" stroke-width="2" viewBox="0 0 24 24" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            </div>
                            <div>
                                <div style="font-size:13.5px;font-weight:500;color:#1a0320;{{ !$cat['enabled'] ? 'color:#b0a0bb;' : '' }}">{{ $cat['type'] }}</div>
                                <div style="font-size:12px;color:#9a7aaa;margin-top:1px;">{{ $cat['desc'] }}</div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:12px;">
                            @if(!$cat['enabled'])
                            <span style="font-size:11px;color:#b0a0bb;padding:2px 8px;background:#f5f0f8;border-radius:4px;">Disabled</span>
                            @endif
                            <label class="cl-toggle">
                                <input type="checkbox" {{ $cat['enabled'] ? 'checked' : '' }}>
                                <span class="cl-toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div style="margin-top:18px;display:flex;align-items:center;gap:12px;">
                    <button class="settings-save-btn">Save Category Settings</button>
                    <p style="font-size:12px;color:#9a7aaa;margin:0;">Drag rows to reorder how categories appear in the upload form.</p>
                </div>
            </div>

        </div>{{-- /media-categories --}}


        {{-- ════════════════════════════════════════════
             SECTION 3 · WATERMARK TEMPLATES
        ════════════════════════════════════════════ --}}
        <div x-show="activeSection==='watermarks'" x-cloak>

            <div class="cl-card">
                <h3 class="settings-section-title" style="margin-bottom:6px;">Watermark Templates</h3>
                <p style="font-size:12.5px;color:#9a7aaa;margin:0 0 20px;">Configure text overlays applied when exporting or sharing clinical files. The original file is never modified.</p>

                {{-- Template selector tabs --}}
                <div style="display:flex;gap:8px;margin-bottom:24px;flex-wrap:wrap;">
                    @php
                    $wmTemplates = ['Marketing', 'Education', 'Conference', 'Internal Review'];
                    @endphp
                    @foreach($wmTemplates as $wmt)
                    <button @click="activeWmTemplate='{{ $wmt }}'"
                            :class="activeWmTemplate==='{{ $wmt }}' ? 'wm-tab-btn active' : 'wm-tab-btn'">
                        {{ $wmt }}
                    </button>
                    @endforeach
                </div>

                {{-- Two-column layout: controls left, preview right --}}
                <div style="display:grid;grid-template-columns:1fr 320px;gap:24px;align-items:start;">

                    {{-- ── Left: element toggles + position + opacity ── --}}
                    <div>
                        {{-- Element toggles --}}
                        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9a7aaa;margin:0 0 10px;">Elements to include</p>

                        @php
                        $wmElements = [
                            ['key' => 'clinic_name',   'label' => 'Clinic Name',    'default' => true],
                            ['key' => 'doctor_name',   'label' => 'Doctor Name',    'default' => true],
                            ['key' => 'treatment',     'label' => 'Treatment',      'default' => false],
                            ['key' => 'stage',         'label' => 'Stage',          'default' => false],
                            ['key' => 'tooth_number',  'label' => 'Tooth Number',   'default' => false],
                            ['key' => 'date',          'label' => 'Date',           'default' => true],
                        ];
                        @endphp

                        <div style="border:1.5px solid #ede4f3;border-radius:10px;overflow:hidden;margin-bottom:20px;">
                            @foreach($wmElements as $el)
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:11px 16px;border-bottom:{{ !$loop->last ? '1px solid #f5f0f8' : 'none' }};">
                                <span style="font-size:13px;color:#1a0320;">{{ $el['label'] }}</span>
                                <label class="cl-toggle">
                                    <input type="checkbox" {{ $el['default'] ? 'checked' : '' }}
                                           @change="refreshWatermarkPreview()">
                                    <span class="cl-toggle-slider"></span>
                                </label>
                            </div>
                            @endforeach
                        </div>

                        {{-- Position selector --}}
                        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9a7aaa;margin:0 0 10px;">Position</p>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:20px;">
                            @php
                            $positions = ['Top Left', 'Top Right', 'Bottom Left', 'Bottom Right'];
                            @endphp
                            @foreach($positions as $pos)
                            <label style="display:flex;align-items:center;gap:8px;padding:9px 12px;border:1.5px solid #ede4f3;border-radius:8px;cursor:pointer;">
                                <input type="radio" name="wm_position" value="{{ $pos }}"
                                       {{ $pos === 'Bottom Right' ? 'checked' : '' }}
                                       @change="wmPosition='{{ $pos }}';refreshWatermarkPreview()"
                                       style="accent-color:#6a0f70;">
                                <span style="font-size:13px;color:#1a0320;">{{ $pos }}</span>
                            </label>
                            @endforeach
                        </div>

                        {{-- Opacity slider --}}
                        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9a7aaa;margin:0 0 10px;">Opacity</p>
                        <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;">
                            <input type="range" min="10" max="100" x-model="wmOpacity" @input="refreshWatermarkPreview()"
                                   style="flex:1;accent-color:#6a0f70;height:4px;">
                            <span style="font-size:13px;font-weight:600;color:#6a0f70;min-width:36px;text-align:right;" x-text="wmOpacity + '%'"></span>
                        </div>

                        <button class="settings-save-btn" style="margin-top:4px;">
                            <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            Save Template
                        </button>
                    </div>

                    {{-- ── Right: live preview panel ── --}}
                    <div>
                        <p style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#9a7aaa;margin:0 0 10px;">Preview</p>
                        <div class="wm-preview">
                            {{-- Placeholder clinical image background --}}
                            <div class="wm-preview-img-placeholder">
                                <svg width="64" height="64" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="1" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            </div>
                            {{-- Dynamic watermark overlay --}}
                            <div class="wm-overlay-text"
                                 :style="`opacity: ${wmOpacity/100}; ${wmPositionStyle}`"
                                 x-text="wmPreviewText">
                            </div>
                        </div>
                        <p style="font-size:11.5px;color:#9a7aaa;margin:10px 0 0;text-align:center;">
                            Preview uses sample clinic data.<br>
                            Original files are never modified.
                        </p>
                    </div>

                </div>{{-- /grid --}}
            </div>

        </div>{{-- /watermarks --}}


        {{-- ════════════════════════════════════════════
             SECTION 4 · CONTENT CLASSIFICATION RULES
        ════════════════════════════════════════════ --}}
        <div x-show="activeSection==='classification'" x-cloak>

            <div class="cl-card">
                <h3 class="settings-section-title">Content Classification Rules</h3>
                <p style="font-size:12.5px;color:#9a7aaa;margin:0 0 20px;">Define what makes a file eligible for marketing, education, or research use. Auto-tagging rules will be applied during upload.</p>

                {{-- Marketing eligibility rules --}}
                <div style="border:1.5px solid #ede4f3;border-radius:10px;padding:20px;margin-bottom:16px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                        <div style="width:8px;height:8px;border-radius:50%;background:#6a0f70;flex-shrink:0;"></div>
                        <h4 style="font-size:13px;font-weight:700;color:#1a0320;margin:0;">Marketing Eligible</h4>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#1a0320;cursor:pointer;">
                            <input type="checkbox" checked style="accent-color:#6a0f70;width:15px;height:15px;">
                            Require patient consent before allowing marketing approval
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#1a0320;cursor:pointer;">
                            <input type="checkbox" checked style="accent-color:#6a0f70;width:15px;height:15px;">
                            Auto-suggest marketing eligible for "After" stage photos
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#1a0320;cursor:pointer;">
                            <input type="checkbox" style="accent-color:#6a0f70;width:15px;height:15px;">
                            Auto-suggest for cosmetic procedure files
                        </label>
                    </div>
                </div>

                {{-- Education eligibility rules --}}
                <div style="border:1.5px solid #ede4f3;border-radius:10px;padding:20px;margin-bottom:16px;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;">
                        <div style="width:8px;height:8px;border-radius:50%;background:#1a5ea8;flex-shrink:0;"></div>
                        <h4 style="font-size:13px;font-weight:700;color:#1a0320;margin:0;">Education Eligible</h4>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#1a0320;cursor:pointer;">
                            <input type="checkbox" checked style="accent-color:#1a5ea8;width:15px;height:15px;">
                            Anonymise patient identity automatically in Education view
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#1a0320;cursor:pointer;">
                            <input type="checkbox" checked style="accent-color:#1a5ea8;width:15px;height:15px;">
                            Auto-suggest education eligible for X-ray and scan files
                        </label>
                    </div>
                </div>

                {{-- AI Auto-tagging (future) --}}
                <div style="border:1.5px dashed #d4b8e0;border-radius:10px;padding:20px;background:#fdf8ff;opacity:.65;">
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                        <svg width="16" height="16" fill="none" stroke="#8b44aa" stroke-width="1.8" viewBox="0 0 24 24" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
                        <h4 style="font-size:13px;font-weight:700;color:#6a0f70;margin:0;">AI Auto-tagging Rules</h4>
                        <span class="coming-soon-badge">Coming Soon</span>
                    </div>
                    <p style="font-size:12.5px;color:#9a7aaa;margin:0;line-height:1.6;">
                        In a future update, AI will analyse uploaded files and automatically suggest eligibility flags, content ratings, and treatment tags based on image content.
                    </p>
                </div>

                <div style="margin-top:20px;">
                    <button class="settings-save-btn">Save Classification Rules</button>
                </div>
            </div>

        </div>{{-- /classification --}}


        {{-- ════════════════════════════════════════════
             SECTION 5 · STORAGE SETTINGS
        ════════════════════════════════════════════ --}}
        <div x-show="activeSection==='storage'" x-cloak>

            {{-- Current storage --}}
            <div class="cl-card">
                <h3 class="settings-section-title">Storage Settings</h3>
                <p style="font-size:12.5px;color:#9a7aaa;margin:0 0 20px;">Clinical files are stored on your local server. Cloud backup and hybrid storage options are in development.</p>

                {{-- Local storage (active) --}}
                <div style="border:2px solid #6a0f70;border-radius:10px;padding:18px 20px;margin-bottom:16px;background:#fdf8ff;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <svg width="20" height="20" fill="none" stroke="#6a0f70" stroke-width="1.8" viewBox="0 0 24 24" stroke-linecap="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="18" x2="6.01" y2="18"/></svg>
                            <div>
                                <div style="font-size:14px;font-weight:700;color:#1a0320;">Local Storage</div>
                                <div style="font-size:12px;color:#9a7aaa;">Files stored on this server</div>
                            </div>
                        </div>
                        <span style="padding:4px 12px;background:#e8f7ef;color:#065f46;border:1px solid #bbf7d0;border-radius:20px;font-size:12px;font-weight:700;">Active</span>
                    </div>

                    {{-- Storage usage bar --}}
                    <div style="margin-bottom:6px;display:flex;align-items:center;justify-content:space-between;">
                        <span style="font-size:12px;color:#7a6080;">Storage used</span>
                        <span style="font-size:12px;font-weight:600;color:#1a0320;">2.4 GB of 50 GB</span>
                    </div>
                    <div class="storage-bar-track" style="margin-bottom:4px;">
                        <div class="storage-bar-fill" style="width:4.8%;"></div>
                    </div>
                    <p style="font-size:11.5px;color:#b0a0bb;margin:0;">4.8% used · 47.6 GB available</p>
                </div>

                {{-- Cloud storage options (disabled / coming soon) --}}
                @php
                $cloudOptions = [
                    ['name' => 'Amazon S3',        'desc' => 'AWS Simple Storage Service', 'icon_color' => '#f59e0b'],
                    ['name' => 'Azure Blob Storage','desc' => 'Microsoft Azure',             'icon_color' => '#0078d4'],
                    ['name' => 'Google Cloud Storage','desc' => 'Google Cloud Platform',    'icon_color' => '#4285f4'],
                ];
                @endphp

                @foreach($cloudOptions as $cloud)
                <div style="border:1.5px solid #ede4f3;border-radius:10px;padding:16px 20px;margin-bottom:12px;opacity:.6;cursor:not-allowed;">
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <div style="width:36px;height:36px;border-radius:8px;background:#f9f5fc;border:1.5px solid #ede4f3;display:flex;align-items:center;justify-content:center;">
                                <svg width="18" height="18" fill="none" stroke="{{ $cloud['icon_color'] }}" stroke-width="1.8" viewBox="0 0 24 24" stroke-linecap="round"><path d="M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z"/></svg>
                            </div>
                            <div>
                                <div style="font-size:13.5px;font-weight:600;color:#9a7aaa;">{{ $cloud['name'] }}</div>
                                <div style="font-size:12px;color:#b0a0bb;">{{ $cloud['desc'] }}</div>
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span class="coming-soon-badge">Coming Soon</span>
                            <button disabled
                                    style="padding:7px 16px;background:#f5f0f8;color:#c5a8d8;border:1.5px solid #ede4f3;border-radius:7px;font-size:12.5px;font-weight:600;cursor:not-allowed;">
                                Enable
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach

                {{-- Sync settings note --}}
                <div style="background:#f9f5fc;border:1.5px solid #ede4f3;border-radius:10px;padding:16px 20px;margin-top:4px;display:flex;gap:12px;align-items:flex-start;">
                    <svg width="16" height="16" fill="none" stroke="#8b44aa" stroke-width="1.8" viewBox="0 0 24 24" stroke-linecap="round" style="flex-shrink:0;margin-top:1px;"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
                    <div>
                        <div style="font-size:13px;font-weight:600;color:#1a0320;margin-bottom:3px;">About Hybrid Storage</div>
                        <p style="font-size:12.5px;color:#9a7aaa;margin:0;line-height:1.6;">
                            When cloud is enabled, each file independently tracks its storage location via the <code style="background:#ede4f3;padding:1px 5px;border-radius:3px;font-size:11.5px;">sync_status</code> column.
                            Files can be on local, cloud, or both. No file is ever stored twice — only the location pointer changes.
                        </p>
                    </div>
                </div>
            </div>

        </div>{{-- /storage --}}


    </div>{{-- /main content --}}
</div>{{-- /sidebar+content --}}
</div>{{-- /x-data --}}
@endsection

@push('scripts')
<script>
function clinicalLibrarySettings() {
    return {
        // ── Active section ──────────────────────────────────────────
        activeSection: 'protocols',

        // ── Protocols ──────────────────────────────────────────────
        showAddProtocol: false,
        selectedProtocol: null,
        protocols: {!! json_encode([
            ['name' => 'Root Canal Treatment',   'procedure' => 'Endodontics',    'steps' => 5, 'active' => true],
            ['name' => 'Dental Implant Protocol','procedure' => 'Implantology',   'steps' => 7, 'active' => true],
            ['name' => 'Crown & Bridge',         'procedure' => 'Prosthodontics', 'steps' => 4, 'active' => true],
            ['name' => 'Tooth Extraction',       'procedure' => 'Oral Surgery',   'steps' => 3, 'active' => false],
            ['name' => 'Clear Aligner Treatment','procedure' => 'Orthodontics',   'steps' => 6, 'active' => true],
            ['name' => 'Scaling & Polishing',    'procedure' => 'Preventive',     'steps' => 2, 'active' => true],
        ]) !!},

        openProtocol(index) {
            this.selectedProtocol = index;
        },

        // ── Watermark templates ─────────────────────────────────────
        activeWmTemplate: 'Marketing',
        wmOpacity: 80,
        wmPosition: 'Bottom Right',
        wmPreviewText: 'Tulip Dental\nDr. Priya Mehta • 14 Jun 2026',
        wmPositionStyle: 'bottom:16px;right:16px;',

        refreshWatermarkPreview() {
            const posMap = {
                'Top Left':     'top:16px;left:16px;',
                'Top Right':    'top:16px;right:16px;',
                'Bottom Left':  'bottom:16px;left:16px;',
                'Bottom Right': 'bottom:16px;right:16px;',
            };
            this.wmPositionStyle = posMap[this.wmPosition] || 'bottom:16px;right:16px;';
        },

        // ── Init ────────────────────────────────────────────────────
        init() {
            // Check URL hash for deep-link to a section
            const hash = window.location.hash.replace('#', '');
            if (hash) this.activeSection = hash;
            this.refreshWatermarkPreview();
        },
    };
}
</script>
@endpush
