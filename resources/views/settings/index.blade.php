@extends('layouts.app')
@section('page-title', 'Settings')

@push('styles')
<style>
/* ── Override content-inner padding so settings fills full height ── */
#df-content-inner {
    padding: 0 !important;
    height: 100%;
    display: flex;
    flex-direction: column;
}

/* ── Settings page shared styles ── */
[x-cloak] { display: none !important; }

/* ── Sidebar nav ── */
.snav-item {
    display: flex;
    align-items: center;
    gap: 9px;
    width: 100%;
    padding: 8px 20px;
    font-size: 13px;
    color: #6a5870;
    background: none;
    border: none;
    border-right: 3px solid transparent;
    cursor: pointer;
    text-align: left;
    transition: background 120ms, color 120ms;
    font-family: 'Inter', sans-serif;
    box-sizing: border-box;
}
.snav-item:hover { background: #f5f0f8; color: #3a0050; }
.snav-item--active {
    background: #f0e6f6;
    color: #6a0f70;
    font-weight: 600;
    border-right-color: #6a0f70;
}
.snav-group-label {
    padding: 10px 20px 4px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .08em;
    color: #c5a8d8;
}

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
.settings-save-btn:disabled { opacity: .6; cursor: not-allowed; }

/* Toggle switch used in notifications */
.settings-toggle {
    position: relative;
    display: inline-block;
    width: 38px;
    height: 21px;
    flex-shrink: 0;
}
.settings-toggle input { opacity: 0; width: 0; height: 0; }
.settings-toggle-slider {
    position: absolute;
    inset: 0;
    background: #ddd;
    border-radius: 21px;
    cursor: pointer;
    transition: background .2s;
}
.settings-toggle-slider::before {
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
.settings-toggle input:checked + .settings-toggle-slider { background: #6a0f70; }
.settings-toggle input:checked + .settings-toggle-slider::before { transform: translateX(17px); }

/* ── Roles & Permissions grid ── */
.role-item > div { transition: background 120ms; }
.role-item:hover > div { background: #faf5fc; }
.role-item.active > div { background: #f0e6f6; }

.perm-row {
    display: grid;
    grid-template-columns: 1fr 90px 90px 90px;
    align-items: center;
    padding: 10px 20px;
    border-bottom: 1px solid #f5f0f8;
}
.perm-header {
    background: #f9f5fc;
    border-bottom: 1px solid #ede4f3;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #9a7aaa;
}
.perm-section-header {
    padding: 7px 20px 4px;
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .15em;
    text-transform: uppercase;
    color: #c5a8d8;
    background: #fdf9ff;
    border-bottom: 1px solid #f5f0f8;
}
.perm-row--dim { opacity: .5; }

/* ── df-toggle switch (used in roles + notifications) ── */
.df-toggle { display: inline-flex; align-items: center; cursor: pointer; }
.df-toggle-track {
    width: 36px; height: 20px; border-radius: 10px;
    background: #e0d5e8; display: inline-block;
    position: relative; transition: background 180ms;
}
.df-toggle-track::after {
    content: ''; position: absolute;
    top: 3px; left: 3px;
    width: 14px; height: 14px; border-radius: 50%;
    background: #fff; transition: transform 180ms;
    box-shadow: 0 1px 3px rgba(0,0,0,.18);
}
.df-toggle.on .df-toggle-track { background: #6a0f70; }
.df-toggle.on .df-toggle-track::after { transform: translateX(16px); }
</style>
@endpush

@section('content')
<div x-data="settingsApp()" x-init="init()" style="font-family:'Inter',sans-serif;height:100%;display:flex;flex-direction:column;background:#f7f4fa;">

{{-- ── PAGE HEADER ── --}}
<div style="padding:18px 28px 16px;background:#fff;border-bottom:1px solid #ede4f3;flex-shrink:0;">
    <h1 style="font-family:'Cormorant Garamond',serif;font-size:24px;font-weight:700;color:#1a0320;margin:0 0 2px;">Settings</h1>
    <p style="font-size:12.5px;color:#9a7aaa;margin:0;">Manage your clinic configuration, staff, and system preferences.</p>
</div>

{{-- ── SIDEBAR + CONTENT ── --}}
<div style="flex:1;display:flex;overflow:hidden;">

{{-- ── SIDEBAR NAV ── --}}
<div style="width:210px;border-right:1px solid #ede4f3;background:#fff;overflow-y:auto;flex-shrink:0;">
    @php
    $navGroups = [
        'Clinic' => [
            ['id'=>'clinic',          'label'=>'Clinic Profile',    'icon'=>'<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>'],
            ['id'=>'operatories',     'label'=>'Operatories',       'icon'=>'<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 4 0v2m0 0V5a2 2 0 0 1 4 0v2"/><line x1="12" y1="12" x2="12" y2="16"/>'],
            ['id'=>'abdm-hfr',        'label'=>'Health Facility (HFR)', 'icon'=>'<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>', 'href' => 'settings.clinic.hfr.edit'],
        ],
        'Team' => [
            ['id'=>'staff-roles',     'label'=>'Staff & Roles',     'icon'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
        ],
        'Clinical' => [
            ['id'=>'masters',         'label'=>'Masters',           'icon'=>'<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'],
            ['id'=>'clinical',        'label'=>'Clinical',          'icon'=>'<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>'],
            ['id'=>'patient-defaults','label'=>'Patient Defaults',  'icon'=>'<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
            ['id'=>'clinical-library-link', 'label'=>'Clinical Library', 'icon'=>'<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8m-4-4v4"/><path d="M7 7h2m0 0h2m-2 0v4"/>', 'href' => 'settings.clinical-library'],
        ],
        'Finance' => [
            ['id'=>'billing',         'label'=>'Billing & Invoice', 'icon'=>'<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
            ['id'=>'printing',        'label'=>'Printing',          'icon'=>'<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>'],
            ['id'=>'banking-link',    'label'=>'Banking',           'icon'=>'<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>', 'href' => 'settings.banking'],
        ],
        'Communication' => [
            ['id'=>'notifications',   'label'=>'Notifications',     'icon'=>'<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'],
            ['id'=>'growth',          'label'=>'Growth & Comms',    'icon'=>'<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
        ],
        'Operations' => [
            ['id'=>'inventory',       'label'=>'Inventory',         'icon'=>'<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3H8L2 7h20z"/>'],
            ['id'=>'huddle',          'label'=>'Huddle',            'icon'=>'<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 17h7m-3.5-3.5v7"/>'],
        ],
        'App' => [
            ['id'=>'personalisation', 'label'=>'Personalisation',   'icon'=>'<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>'],
            ['id'=>'calendar',        'label'=>'Calendar',          'icon'=>'<rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>'],
            ['id'=>'cross-app-flags', 'label'=>'Feature Flags',     'icon'=>'<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>'],
        ],
        'Data' => [
            ['id'=>'data',            'label'=>'Import / Export',   'icon'=>'<polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/>'],
        ],
        'Security' => [
            ['id'=>'activity-log-link', 'label'=>'Activity Log', 'icon'=>'<path d="M12 8v4l3 3"/><circle cx="12" cy="12" r="9"/>', 'href' => 'settings.activity-log'],
        ],
    ];
    @endphp

    @foreach($navGroups as $groupName => $items)
    <div class="snav-group-label">{{ $groupName }}</div>
    @foreach($items as $item)
    @if(isset($item['href']))
    {{-- External page link (e.g. Banking) — navigates away instead of switching tabs --}}
    <a href="{{ route($item['href']) }}" class="snav-item" style="text-decoration:none;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">{!! $item['icon'] !!}</svg>
        {{ $item['label'] }}
    </a>
    @else
    <button @click="activeTab='{{ $item['id'] }}'"
            :class="activeTab==='{{ $item['id'] }}' ? 'snav-item snav-item--active' : 'snav-item'">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">{!! $item['icon'] !!}</svg>
        {{ $item['label'] }}
    </button>
    @endif
    @endforeach
    @endforeach
</div>

{{-- ── TAB CONTENT ── --}}
<div style="flex:1;overflow-y:auto;padding:28px 36px;">

    @if(session('success'))
    <div style="margin-bottom:20px;padding:11px 16px;background:#e8f7ef;border:1px solid #b8e8cc;border-radius:8px;color:#1a7a45;font-size:13px;display:flex;align-items:center;gap:8px;">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
        {{ session('success') }}
    </div>
    @endif

    {{-- ════════════════════════════════════════════
         TAB 1 · CLINIC PROFILE
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='clinic'" x-cloak>
        <div style="max-width:680px;margin:0 auto;">
            <form action="{{ route('settings.clinic.save') }}" method="POST" enctype="multipart/form-data">
                @csrf

                {{-- Logo upload --}}
                <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:20px;">
                    <h3 class="settings-section-title">Clinic Identity</h3>
                    <div style="display:flex;align-items:center;gap:20px;margin-bottom:20px;">
                        <div style="width:80px;height:80px;border-radius:12px;background:#f3eef7;border:2px dashed #d4b8e0;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                            @php $logo = $clinic['clinic_logo'] ?? null; @endphp
                            @if($logo)
                                <img src="{{ Storage::url($logo) }}" style="width:100%;height:100%;object-fit:cover;">
                            @else
                                <svg width="28" height="28" fill="none" stroke="#c5a8d8" stroke-width="1.5" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                            @endif
                        </div>
                        <div>
                            <label style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:1.5px solid #ddd;border-radius:6px;font-size:12.5px;cursor:pointer;color:#555;background:#fafafa;">
                                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                Upload Logo
                                <input type="file" name="clinic_logo" accept="image/*" style="display:none;" @change="logoPreview($event)">
                            </label>
                            <p style="font-size:11.5px;color:#b0a0bb;margin:5px 0 0;">PNG or JPG, max 2MB. Appears on invoices & reports.</p>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div style="grid-column:1/-1;">
                            <label class="settings-label">Clinic Name *</label>
                            <input type="text" name="clinic_name" value="{{ $clinic['clinic_name'] ?? '' }}" required class="settings-input" placeholder="e.g. Tulip Dental">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label class="settings-label">Tagline / Specialty</label>
                            <input type="text" name="clinic_tagline" value="{{ $clinic['clinic_tagline'] ?? '' }}" class="settings-input" placeholder="e.g. Smile with Confidence">
                        </div>
                    </div>
                </div>

                {{-- Contact --}}
                <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:20px;">
                    <h3 class="settings-section-title">Contact Details</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label class="settings-label">Phone</label>
                            <input type="text" name="clinic_phone" value="{{ $clinic['clinic_phone'] ?? '' }}" class="settings-input" placeholder="+91 98765 43210">
                        </div>
                        <div>
                            <label class="settings-label">Email</label>
                            <input type="email" name="clinic_email" value="{{ $clinic['clinic_email'] ?? '' }}" class="settings-input" placeholder="hello@clinic.com">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label class="settings-label">Address</label>
                            <input type="text" name="clinic_address" value="{{ $clinic['clinic_address'] ?? '' }}" class="settings-input" placeholder="Building, Street, Area">
                        </div>
                        <div>
                            <label class="settings-label">City</label>
                            <input type="text" name="clinic_city" value="{{ $clinic['clinic_city'] ?? '' }}" class="settings-input" placeholder="Mumbai">
                        </div>
                        <div>
                            <label class="settings-label">GST / GSTIN Number</label>
                            <input type="text" name="clinic_gst_no" value="{{ $clinic['clinic_gst_no'] ?? '' }}" class="settings-input" placeholder="22AAAAA0000A1Z5">
                            <p style="font-size:11px;color:#b0a0bb;margin:4px 0 0;">Printed on invoices if filled.</p>
                        </div>
                    </div>
                </div>

                <button type="submit" class="settings-save-btn">Save Clinic Profile</button>
            </form>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         TAB · OPERATORIES
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='operatories'" x-cloak
         x-data="operatoryManager()"
         x-init="init()">

        <div style="max-width:640px;">
            <h2 style="font-size:15px;font-weight:600;color:#380740;margin:0 0 4px;">Operatories</h2>
            <p style="font-size:12.5px;color:#9a7aaa;margin:0 0 20px;">
                Define the chairs and rooms in your clinic. Assign them to appointments from the appointment form.
            </p>

            {{-- ── Add new operatory ── --}}
            <div style="display:flex;gap:8px;margin-bottom:20px;">
                <input type="text"
                       x-model="newName"
                       @keydown.enter.prevent="addOperatory()"
                       placeholder="e.g. Chair 1, Implant Room, Pedo Room…"
                       maxlength="100"
                       style="flex:1;padding:8px 12px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-family:'Inter',sans-serif;outline:none;">
                <button @click="addOperatory()"
                        :disabled="saving || !newName.trim()"
                        style="padding:8px 18px;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;font-family:'Inter',sans-serif;white-space:nowrap;"
                        :style="saving || !newName.trim() ? 'opacity:.5;cursor:not-allowed;' : ''">
                    + Add
                </button>
            </div>

            {{-- ── Operatory list ── --}}
            <div x-show="operatories.length === 0 && !loading"
                 style="text-align:center;padding:32px;color:#9a7aaa;font-size:13px;border:1px dashed #d1d5db;border-radius:8px;">
                No operatories yet. Add your first chair or room above.
            </div>

            <div x-show="loading" style="text-align:center;padding:24px;color:#9a7aaa;font-size:13px;">
                Loading…
            </div>

            <ul style="list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:6px;"
                x-show="operatories.length > 0">
                <template x-for="op in operatories" :key="op.id">
                    <li style="display:flex;align-items:center;gap:10px;padding:10px 12px;background:#fff;border:1px solid #ede4f3;border-radius:8px;">

                        {{-- Name / Inline edit --}}
                        <div style="flex:1;min-width:0;">
                            <template x-if="editingId !== op.id">
                                <span x-text="op.name"
                                      style="font-size:13px;font-weight:500;color:#380740;"></span>
                            </template>
                            <template x-if="editingId === op.id">
                                <input type="text"
                                       x-model="editName"
                                       @keydown.enter.prevent="saveEdit(op)"
                                       @keydown.escape.prevent="cancelEdit()"
                                       style="width:100%;padding:4px 8px;border:1px solid #6a0f70;border-radius:4px;font-size:13px;font-family:'Inter',sans-serif;outline:none;box-sizing:border-box;"
                                       x-ref="editInput">
                            </template>
                        </div>

                        {{-- Active badge --}}
                        <span x-text="op.is_active ? 'Active' : 'Inactive'"
                              :style="op.is_active
                                  ? 'font-size:10px;font-weight:600;color:#059669;background:#d1fae5;padding:2px 8px;border-radius:99px;'
                                  : 'font-size:10px;font-weight:600;color:#6b7280;background:#f3f4f6;padding:2px 8px;border-radius:99px;'">
                        </span>

                        {{-- Inline edit save/cancel --}}
                        <template x-if="editingId === op.id">
                            <div style="display:flex;gap:4px;">
                                <button @click="saveEdit(op)"
                                        style="padding:4px 10px;background:#6a0f70;color:#fff;border:none;border-radius:5px;font-size:12px;cursor:pointer;">
                                    Save
                                </button>
                                <button @click="cancelEdit()"
                                        style="padding:4px 10px;background:#f3f4f6;color:#374151;border:none;border-radius:5px;font-size:12px;cursor:pointer;">
                                    Cancel
                                </button>
                            </div>
                        </template>

                        {{-- Action buttons (shown when not editing) --}}
                        <template x-if="editingId !== op.id">
                            <div style="display:flex;gap:4px;">
                                {{-- Edit --}}
                                <button @click="startEdit(op)"
                                        title="Rename"
                                        style="padding:5px 8px;background:#f3f4f6;border:none;border-radius:5px;cursor:pointer;font-size:11px;color:#374151;">
                                    ✏️
                                </button>
                                {{-- Toggle active --}}
                                <button @click="toggleActive(op)"
                                        :title="op.is_active ? 'Deactivate' : 'Activate'"
                                        style="padding:5px 8px;background:#f3f4f6;border:none;border-radius:5px;cursor:pointer;font-size:11px;color:#374151;">
                                    <span x-text="op.is_active ? '' : ''"></span>
                                </button>
                                {{-- Delete --}}
                                <button @click="deleteOperatory(op)"
                                        title="Delete"
                                        style="padding:5px 8px;background:#fee2e2;border:none;border-radius:5px;cursor:pointer;font-size:11px;color:#dc2626;">
                                   
                                </button>
                            </div>
                        </template>

                    </li>
                </template>
            </ul>

            <p style="font-size:11px;color:#c4b5d4;margin-top:16px;">
                Inactive operatories are hidden from appointment forms. Deleting an operatory clears it from any appointments (they are not deleted).
            </p>
        </div>

    </div>

    {{-- ── Operatory JS ─────────────────────────────────────────── --}}
    <script>
    function operatoryManager() {
        return {
            operatories: @json($operatories ?? []),
            newName:     '',
            editingId:   null,
            editName:    '',
            saving:      false,
            loading:     false,

            init() {
                // Data is already server-rendered; nothing to fetch on load
            },

            async addOperatory() {
                if (!this.newName.trim() || this.saving) return;
                this.saving = true;
                try {
                    const res = await fetch('{{ route('settings.operatories.store') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({ name: this.newName.trim() }),
                    });
                    const data = await res.json();
                    if (data.ok) {
                        this.operatories.push(data.operatory);
                        this.newName = '';
                    }
                } finally {
                    this.saving = false;
                }
            },

            startEdit(op) {
                this.editingId = op.id;
                this.editName  = op.name;
                this.$nextTick(() => this.$refs.editInput?.focus());
            },

            cancelEdit() {
                this.editingId = null;
                this.editName  = '';
            },

            async saveEdit(op) {
                if (!this.editName.trim()) return;
                const res = await fetch(`{{ url('settings/operatories') }}/${op.id}`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ name: this.editName.trim() }),
                });
                const data = await res.json();
                if (data.ok) {
                    const idx = this.operatories.findIndex(o => o.id === op.id);
                    if (idx !== -1) this.operatories[idx] = data.operatory;
                    this.cancelEdit();
                }
            },

            async toggleActive(op) {
                const res = await fetch(`{{ url('settings/operatories') }}/${op.id}/toggle`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (data.ok) {
                    const idx = this.operatories.findIndex(o => o.id === op.id);
                    if (idx !== -1) this.operatories[idx].is_active = data.is_active;
                }
            },

            async deleteOperatory(op) {
                if (!confirm(`Delete "${op.name}"? This cannot be undone.`)) return;
                const res = await fetch(`{{ url('settings/operatories') }}/${op.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                const data = await res.json();
                if (data.ok) {
                    this.operatories = this.operatories.filter(o => o.id !== op.id);
                }
            },
        };
    }
    </script>

    {{-- ════════════════════════════════════════════
         TAB · STAFF & ROLES (merged)
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='staff-roles'" x-cloak>

    {{-- Internal sub-tab switcher --}}
    <div style="display:flex;gap:0;margin-bottom:24px;border-bottom:2px solid #ede4f3;">
        <button @click="staffSubTab='staff'"
                :class="staffSubTab==='staff' ? 'srole-tab srole-tab--active' : 'srole-tab'">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
            Staff Members
        </button>
        <a href="{{ route('hr.roles.index') }}"
           style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;font-size:13px;font-weight:500;color:#7a6080;border-bottom:2px solid transparent;margin-bottom:-2px;text-decoration:none;transition:color .15s;">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Roles &amp; Permissions
            <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
        </a>
    </div>
    <style>
    .srole-tab { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;font-size:13px;font-weight:500;color:#7a6080;background:none;border:none;border-bottom:2px solid transparent;cursor:pointer;margin-bottom:-2px;font-family:'Inter',sans-serif;transition:color .15s,border-color .15s; }
    .srole-tab:hover { color:#3a0050; }
    .srole-tab--active { color:#6a0f70;border-bottom-color:#6a0f70;font-weight:600; }
    </style>

    {{-- ── Staff sub-tab ── --}}
    <div x-show="staffSubTab==='staff'" x-cloak>

        {{-- Add staff form --}}
        <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:24px;max-width:700px;">
            <h3 class="settings-section-title" style="margin-bottom:16px;">Add Staff Member</h3>
            <form action="{{ route('settings.staff.store') }}" method="POST">
                @csrf
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div>
                        <label class="settings-label">Full Name *</label>
                        <input type="text" name="name" required class="settings-input" placeholder="Dr. Priya Mehta">
                    </div>
                    <div>
                        <label class="settings-label">Email *</label>
                        <input type="email" name="email" required class="settings-input" placeholder="priya@clinic.com">
                    </div>
                    <div>
                        <label class="settings-label">Phone</label>
                        <input type="text" name="phone" class="settings-input" placeholder="+91 98765 43210">
                    </div>
                    <div>
                        <label class="settings-label">Designation</label>
                        <input type="text" name="designation" class="settings-input" placeholder="Senior Dentist">
                    </div>
                    <div>
                        <label class="settings-label">Role</label>
                        <select name="role_id" class="settings-select">
                            <option value="">— Select role —</option>
                            @foreach($roles as $role)
                            <option value="{{ $role->id }}">{{ $role->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="settings-label">Temporary Password *</label>
                        <input type="password" name="password" required class="settings-input" placeholder="Min 6 characters">
                    </div>
                </div>
                <div style="margin-top:16px;">
                    <button type="submit" class="settings-save-btn">Add Staff Member</button>
                </div>
            </form>
        </div>

        {{-- Staff list --}}
        <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
            <div style="padding:14px 20px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:11px;font-weight:600;letter-spacing:.15em;text-transform:uppercase;color:#9a7aaa;">All Staff ({{ $staff->count() }})</span>
            </div>

            @forelse($staff as $user)
            <div data-staff-id="{{ $user->id }}" style="display:grid;grid-template-columns:40px 1fr auto auto auto;gap:12px;align-items:center;padding:13px 20px;border-bottom:1px solid #f5f0f8;">
                {{-- Avatar --}}
                <div style="width:36px;height:36px;border-radius:50%;background:{{ ['#6a0f70','#1a5ea8','#1a7a45','#a05c00','#c0392b'][($loop->index % 5)] }};display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:600;flex-shrink:0;">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                {{-- Info --}}
                <div>
                    <div class="staff-name" style="font-size:13.5px;font-weight:500;color:#1a0320;">{{ $user->name }}</div>
                    <div style="font-size:11.5px;color:#9a7aaa;">
                        <span class="staff-email">{{ $user->email }}</span><span class="staff-phone">@if($user->phone) · {{ $user->phone }}@endif</span><span class="staff-desig">@if($user->designation) · <span style="color:#7a6a85;">{{ $user->designation }}</span>@endif</span>
                    </div>
                </div>
                {{-- Role badge --}}
                <div>
                    @if($user->roleModel)
                        <span id="role-badge-{{ $user->id }}" style="padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $user->roleModel->color }}22;color:{{ $user->roleModel->color }};">{{ $user->roleModel->name }}</span>
                    @elseif($user->role)
                        <span id="role-badge-{{ $user->id }}" style="padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:#f3eef7;color:#6a0f70;">{{ ucfirst($user->role) }}</span>
                    @else
                        <span id="role-badge-{{ $user->id }}" style="padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:#f5f5f5;color:#aaa;">No role</span>
                    @endif
                </div>
                {{-- Edit button --}}
                <div>
                    <button onclick="openStaffEdit(
                                {{ $user->id }},
                                '{{ addslashes($user->name) }}',
                                '{{ addslashes($user->email) }}',
                                '{{ addslashes($user->phone ?? '') }}',
                                '{{ addslashes($user->designation ?? '') }}',
                                {{ $user->role_id ?? 'null' }},
                                '{{ $user->color ?? '#3b82f6' }}'
                            )"
                            style="display:inline-flex;align-items:center;gap:5px;padding:5px 11px;border:1.5px solid #ede4f3;background:#faf5fc;border-radius:6px;font-size:11.5px;font-weight:500;color:#6a0f70;cursor:pointer;transition:border-color .15s;"
                            onmouseover="this.style.borderColor='#6a0f70'" onmouseout="this.style.borderColor='#ede4f3'">
                        <svg width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        Edit
                    </button>
                </div>
                {{-- Active toggle (password-gated) --}}
                <div style="display:flex;align-items:center;gap:8px;">
                    <label class="df-toggle {{ $user->is_active ? 'on' : '' }}" id="toggle-{{ $user->id }}" style="cursor:pointer;"
                           onclick="askToggleStaff(event, {{ $user->id }}, {{ $user->is_active ? 'true' : 'false' }}, '{{ addslashes($user->name) }}')">
                        <input type="checkbox" {{ $user->is_active ? 'checked' : '' }} style="display:none;">
                        <span class="df-toggle-track"></span>
                    </label>
                    <span id="toggle-label-{{ $user->id }}" style="font-size:11px;color:#9a7aaa;">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                </div>
            </div>
            @empty
            <div style="padding:48px;text-align:center;color:#b0a0bb;font-size:13px;">No staff yet. Add one above.</div>
            @endforelse

            {{-- ── Edit Staff Modal ── --}}
            <div id="editStaffModal"
                 style="display:none;position:fixed;inset:0;z-index:70;align-items:center;justify-content:center;background:rgba(14,1,24,.45);"
                 onclick="if(event.target===this)closeStaffEdit()">
                <div style="background:#fff;border-radius:12px;width:460px;padding:28px;box-shadow:0 20px 60px rgba(14,1,24,.25);">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;">
                        <h3 style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:700;color:#1a0320;margin:0;">Edit Staff Member</h3>
                        <button onclick="closeStaffEdit()" style="border:none;background:none;cursor:pointer;color:#9a7aaa;padding:2px;">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div style="grid-column:1/-1;">
                            <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Full Name *</label>
                            <input id="sedit_name" type="text" placeholder="Dr. Priya Mehta"
                                   style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;outline:none;box-sizing:border-box;"
                                   onfocus="this.style.borderColor='#6a0f70'" onblur="this.style.borderColor='#ddd'">
                        </div>
                        <div>
                            <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Email *</label>
                            <input id="sedit_email" type="email" placeholder="priya@clinic.com"
                                   style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;outline:none;box-sizing:border-box;"
                                   onfocus="this.style.borderColor='#6a0f70'" onblur="this.style.borderColor='#ddd'">
                        </div>
                        <div>
                            <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Phone</label>
                            <input id="sedit_phone" type="text" placeholder="+91 98765 43210"
                                   style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;outline:none;box-sizing:border-box;"
                                   onfocus="this.style.borderColor='#6a0f70'" onblur="this.style.borderColor='#ddd'">
                        </div>
                        <div>
                            <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Designation</label>
                            <input id="sedit_designation" type="text" placeholder="Senior Dentist"
                                   style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;outline:none;box-sizing:border-box;"
                                   onfocus="this.style.borderColor='#6a0f70'" onblur="this.style.borderColor='#ddd'">
                        </div>
                        <div>
                            <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Calendar Color</label>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <input id="sedit_color" type="color" value="#3b82f6"
                                       style="width:42px;height:36px;padding:2px;border:1.5px solid #ddd;border-radius:6px;cursor:pointer;">
                                <span style="font-size:11.5px;color:#9a7aaa;">Doctor's border on appointment cards</span>
                            </div>
                        </div>
                        <div style="grid-column:1/-1;">
                            <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Role</label>
                            <select id="sedit_role_id"
                                    style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;outline:none;box-sizing:border-box;background:#fff;"
                                    onfocus="this.style.borderColor='#6a0f70'" onblur="this.style.borderColor='#ddd'">
                                <option value="">— No role assigned —</option>
                                @foreach($roles as $role)
                                <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div id="sedit_error" style="display:none;margin-top:12px;padding:8px 12px;background:#fef2f2;border-radius:6px;font-size:12.5px;color:#b91c1c;"></div>

                    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:22px;">
                        <button onclick="closeStaffEdit()"
                                style="padding:8px 18px;border:1.5px solid #ddd;background:#fff;border-radius:6px;font-size:13px;cursor:pointer;color:#555;">
                            Cancel
                        </button>
                        <button onclick="saveStaffEdit()"
                                id="saveStaffBtn"
                                style="padding:8px 18px;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;">
                            Save Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>

        {{-- ── Password Confirm Modal (activate/deactivate) ── --}}
        <div id="toggleConfirmModal"
             style="display:none;position:fixed;inset:0;z-index:80;align-items:center;justify-content:center;background:rgba(14,1,24,.50);"
             onclick="if(event.target===this)closeToggleConfirm()">
            <div style="background:#fff;border-radius:12px;width:400px;padding:28px;box-shadow:0 20px 60px rgba(14,1,24,.3);">

                <div style="display:flex;align-items:flex-start;gap:14px;margin-bottom:20px;">
                    <div id="tcm-icon" style="width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                        <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="tcm-title" style="font-family:'Cormorant Garamond',serif;font-size:18px;font-weight:700;color:#1a0320;margin:0 0 4px;">Confirm Action</h3>
                        <p id="tcm-desc" style="font-size:12.5px;color:#7a6a85;margin:0;"></p>
                    </div>
                </div>

                <label style="font-size:11.5px;font-weight:600;color:#6a0f70;display:block;margin-bottom:6px;">Enter your password to confirm</label>
                <input id="tcm-password" type="password" placeholder="Your login password"
                       style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;outline:none;box-sizing:border-box;"
                       onfocus="this.style.borderColor='#6a0f70'" onblur="this.style.borderColor='#ddd'"
                       onkeydown="if(event.key==='Enter')confirmToggleStaff()">

                <div id="tcm-error" style="display:none;margin-top:10px;padding:7px 11px;background:#fef2f2;border-radius:6px;font-size:12px;color:#b91c1c;"></div>

                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:20px;">
                    <button onclick="closeToggleConfirm()" style="padding:8px 18px;border:1.5px solid #ddd;background:#fff;border-radius:6px;font-size:13px;cursor:pointer;color:#555;">Cancel</button>
                    <button id="tcm-confirm-btn" onclick="confirmToggleStaff()"
                            style="padding:8px 18px;border:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;color:#fff;">
                        Confirm
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Audit Log ── --}}
        <div style="margin-top:32px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                <div>
                    <h3 style="font-size:14px;font-weight:600;color:#1a0320;margin:0 0 2px;">Activity Log</h3>
                    <p style="font-size:12px;color:#9a7aaa;margin:0;">All activate / deactivate / role / profile changes — most recent first.</p>
                </div>
                <button onclick="loadActivityLog()"
                        style="display:inline-flex;align-items:center;gap:5px;padding:6px 13px;border:1.5px solid #ede4f3;background:#faf5fc;border-radius:6px;font-size:12px;font-weight:500;color:#6a0f70;cursor:pointer;">
                    <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-.18-8.62"/></svg>
                    Refresh
                </button>
            </div>

            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div id="activity-log-loading" style="padding:40px;text-align:center;color:#b0a0bb;font-size:13px;">
                    Loading activity log…
                </div>
                <div id="activity-log-body" style="display:none;">
                    <div style="display:grid;grid-template-columns:1fr 1fr 130px 1fr 100px;gap:0;padding:9px 18px;background:#f9f5fc;border-bottom:1px solid #ede4f3;font-size:10.5px;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:#9a7aaa;">
                        <div>Staff Member</div>
                        <div>Changed By</div>
                        <div>Action</div>
                        <div>Detail</div>
                        <div>When</div>
                    </div>
                    <div id="activity-log-rows"></div>
                    <div id="activity-log-empty" style="display:none;padding:40px;text-align:center;color:#b0a0bb;font-size:13px;">No activity recorded yet.</div>
                </div>
            </div>
        </div>

    </div>


    {{-- ── Roles sub-tab ── moved to HR module --}}
    <div x-show="staffSubTab==='roles'" x-cloak>
        <div style="display:flex;align-items:center;gap:16px;padding:20px 24px;background:#faf5fc;border:1.5px solid #e5d5f0;border-radius:10px;margin-top:4px;">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.5" stroke-linecap="round" style="flex-shrink:0;opacity:.7;">
                <rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
            </svg>
            <div style="flex:1;">
                <p style="font-size:14px;font-weight:600;color:#1a0320;margin:0 0 3px;">Roles &amp; Permissions has moved to HR</p>
                <p style="font-size:13px;color:#7a6080;margin:0;">Manage doctor and staff roles, and control module-level permissions from the HR module.</p>
            </div>
            <a href="{{ route('hr.roles.index') }}"
               style="display:inline-flex;align-items:center;gap:6px;padding:9px 18px;background:#6a0f70;color:#fff;border-radius:6px;font-size:13px;font-weight:500;text-decoration:none;white-space:nowrap;flex-shrink:0;">
                Go to HR → Roles
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
            </a>
        </div>
    </div>{{-- /staffSubTab=roles --}}

    </div>{{-- /staff-roles panel --}}

    {{-- ════════════════════════════════════════════
         TAB · NOTIFICATIONS
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='notifications'" x-cloak>
        <div style="max-width:620px;margin:0 auto;">
            <form action="{{ route('settings.notifications.save') }}" method="POST">
                @csrf
                @php
                $n = $notifications;
                $on = fn($k) => ($n[$k] ?? '0') === '1';
                @endphp

                <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:20px;">
                    <h3 class="settings-section-title">Delivery Channels</h3>
                    <div style="display:flex;flex-direction:column;gap:16px;">
                        @foreach([
                            ['notif_whatsapp','WhatsApp Notifications','Send automated follow-up and reminder messages via WhatsApp.'],
                            ['notif_sms','SMS Notifications','Send SMS alerts for appointments and follow-ups.'],
                            ['notif_email','Email Notifications','Send email digests and system alerts.'],
                        ] as [$key,$label,$desc])
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border:1.5px solid #ede4f3;border-radius:8px;">
                            <div>
                                <div style="font-size:13.5px;font-weight:500;color:#1a0320;">{{ $label }}</div>
                                <div style="font-size:12px;color:#9a7aaa;margin-top:2px;">{{ $desc }}</div>
                            </div>
                            <label class="df-toggle {{ $on($key) ? 'on' : '' }}">
                                <input type="checkbox" name="{{ $key }}" value="1" {{ $on($key) ? 'checked' : '' }} style="display:none;" onchange="this.parentElement.classList.toggle('on', this.checked)">
                                <span class="df-toggle-track"></span>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:20px;">
                    <h3 class="settings-section-title">Alert Triggers</h3>
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        @foreach([
                            ['notif_appointment_reminder','Appointment Reminders','24hr reminder sent to patients before their appointment.'],
                            ['notif_followup_due','Follow-up Due Alerts','Alert staff when a follow-up is due or overdue.'],
                            ['notif_new_lead','New Lead Alerts','Notify team when a new lead is added to PRM.'],
                            ['notif_task_assigned','Task Assignment','Notify staff when a task is assigned to them.'],
                        ] as [$key,$label,$desc])
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;border-bottom:1px solid #f5f0f8;">
                            <div>
                                <div style="font-size:13px;font-weight:500;color:#1a0320;">{{ $label }}</div>
                                <div style="font-size:11.5px;color:#9a7aaa;margin-top:1px;">{{ $desc }}</div>
                            </div>
                            <label class="df-toggle {{ $on($key) ? 'on' : '' }}">
                                <input type="checkbox" name="{{ $key }}" value="1" {{ $on($key) ? 'checked' : '' }} style="display:none;" onchange="this.parentElement.classList.toggle('on', this.checked)">
                                <span class="df-toggle-track"></span>
                            </label>
                        </div>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="settings-save-btn">Save Notification Settings</button>
            </form>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         TAB 5 · BILLING & INVOICE
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='billing'" x-cloak>
        <div style="max-width:620px;margin:0 auto;">
            <form action="{{ route('settings.billing.save') }}" method="POST">
                @csrf
                @php $b = $billing; @endphp

                <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:20px;">
                    <h3 class="settings-section-title">Invoice Settings</h3>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label class="settings-label">Invoice Prefix</label>
                            <input type="text" name="invoice_prefix" value="{{ $b['invoice_prefix'] ?? 'INV' }}" class="settings-input" placeholder="INV">
                            <p style="font-size:11px;color:#b0a0bb;margin:4px 0 0;">e.g. INV-2024-001</p>
                        </div>
                        <div>
                            <label class="settings-label">Next Invoice Number</label>
                            <input type="number" name="invoice_next_no" value="{{ $b['invoice_next_no'] ?? '1' }}" class="settings-input" min="1">
                        </div>
                        <div>
                            <label class="settings-label">Currency Symbol</label>
                            <input type="text" name="currency_symbol" value="{{ $b['currency_symbol'] ?? 'Rs. ' }}" class="settings-input" placeholder="Rs. ">
                        </div>
                        <div>
                            <label class="settings-label">Tax Label</label>
                            <input type="text" name="tax_label" value="{{ $b['tax_label'] ?? 'GST' }}" class="settings-input" placeholder="GST">
                        </div>
                        <div style="grid-column:1/-1;">
                            <label class="settings-label">Default Tax Rate (%)</label>
                            <input type="number" name="tax_rate" value="{{ $b['tax_rate'] ?? '18' }}" class="settings-input" min="0" max="100" step="0.01" style="max-width:140px;">
                        </div>
                    </div>
                </div>

                <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:20px;">
                    <h3 class="settings-section-title">Payment Details</h3>
                    <div style="display:flex;flex-direction:column;gap:14px;">
                        <div>
                            <label class="settings-label">UPI ID</label>
                            <input type="text" name="payment_upi" value="{{ $b['payment_upi'] ?? '' }}" class="settings-input" placeholder="clinic@upi">
                        </div>
                        <div>
                            <label class="settings-label">Bank Account Details</label>
                            <textarea name="payment_bank" rows="3" class="settings-input" placeholder="Bank name, Account no., IFSC code…" style="resize:vertical;">{{ $b['payment_bank'] ?? '' }}</textarea>
                        </div>
                    </div>
                </div>

                <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:20px;">
                    <h3 class="settings-section-title">Credit Card Convenience Fee</h3>
                    <p style="font-size:12px;color:#8a7a95;margin:-6px 0 16px;">
                        When a credit-card payment is above the threshold, this % is added to the amount as a convenience fee.
                    </p>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                        <div>
                            <label class="settings-label">Threshold Amount (Rs.)</label>
                            <input type="number" name="cc_convenience_threshold" value="{{ $b['cc_convenience_threshold'] ?? '10000' }}" class="settings-input" min="0" step="1" placeholder="10000">
                            <p style="font-size:11px;color:#b0a0bb;margin:4px 0 0;">Fee applies only when a single payment exceeds this.</p>
                        </div>
                        <div>
                            <label class="settings-label">Convenience Fee Rate (%)</label>
                            <input type="number" name="cc_convenience_rate" value="{{ $b['cc_convenience_rate'] ?? '2.5' }}" class="settings-input" min="0" max="100" step="0.01" placeholder="2.5">
                            <p style="font-size:11px;color:#b0a0bb;margin:4px 0 0;">e.g. 2.5 = 2.5% of the payment amount.</p>
                        </div>
                    </div>
                </div>

                <button type="submit" class="settings-save-btn">Save Billing Settings</button>
            </form>

            {{-- ── EMI Providers ─────────────────────────────────────────── --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-top:24px;max-width:620px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;">
                    <div>
                        <h3 class="settings-section-title" style="margin:0;">EMI Providers & Schemes</h3>
                        <p style="font-size:12px;color:#9a7aaa;margin:4px 0 0;">Configure provider-financed EMI options shown at payment time.</p>
                    </div>
                    <button onclick="document.getElementById('addProviderModal').classList.toggle('hidden')"
                            type="button"
                            style="background:#6a0f70;color:#fff;border:none;border-radius:8px;padding:7px 14px;font-size:13px;cursor:pointer;">
                        + Add Provider
                    </button>
                </div>

                {{-- Add Provider inline form --}}
                <div id="addProviderModal" class="hidden" style="background:#fdf8ff;border:1px solid #ede4f3;border-radius:10px;padding:16px;margin-bottom:18px;">
                    <form method="POST" action="{{ route('settings.emi.provider.store') }}" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;">
                        @csrf
                        <div style="flex:1;min-width:160px;">
                            <label class="settings-label">Provider Name *</label>
                            <input type="text" name="name" required class="settings-input" placeholder="e.g. Bajaj Finserv">
                        </div>
                        <div style="flex:1;min-width:160px;">
                            <label class="settings-label">Contact / Rep (optional)</label>
                            <input type="text" name="contact" class="settings-input" placeholder="Phone or email">
                        </div>
                        <button type="submit" style="background:#6a0f70;color:#fff;border:none;border-radius:8px;padding:9px 16px;font-size:13px;cursor:pointer;white-space:nowrap;">
                            Save Provider
                        </button>
                    </form>
                </div>

                {{-- Provider list --}}
                @forelse($emiProviders ?? [] as $provider)
                <div style="border:1px solid #ede4f3;border-radius:10px;margin-bottom:14px;overflow:hidden;">
                    {{-- Provider header --}}
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 16px;background:#fdf8ff;border-bottom:1px solid #ede4f3;">
                        <div>
                            <span style="font-size:14px;font-weight:600;color:#1a0320;">{{ $provider->name }}</span>
                            @if($provider->contact)
                            <span style="font-size:11px;color:#9a7aaa;margin-left:8px;">{{ $provider->contact }}</span>
                            @endif
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="font-size:11px;padding:3px 8px;border-radius:20px;
                                         {{ $provider->is_active ? 'background:#d1fae5;color:#065f46;' : 'background:#f3f4f6;color:#6b7280;' }}">
                                {{ $provider->is_active ? 'Active' : 'Inactive' }}
                            </span>
                            <form method="POST" action="{{ route('settings.emi.provider.toggle', $provider) }}" style="display:inline;">
                                @csrf
                                <button type="submit" style="background:none;border:1px solid #ede4f3;border-radius:6px;padding:4px 10px;font-size:11px;cursor:pointer;color:#6b7280;">
                                    {{ $provider->is_active ? 'Disable' : 'Enable' }}
                                </button>
                            </form>
                            <button onclick="document.getElementById('addScheme_{{ $provider->id }}').classList.toggle('hidden')"
                                    type="button"
                                    style="background:none;border:1px solid #6a0f70;border-radius:6px;padding:4px 10px;font-size:11px;cursor:pointer;color:#6a0f70;">
                                + Scheme
                            </button>
                        </div>
                    </div>

                    {{-- Add scheme inline form --}}
                    <div id="addScheme_{{ $provider->id }}" class="hidden"
                         style="padding:14px 16px;background:#fffbff;border-bottom:1px solid #ede4f3;">
                        <form method="POST" action="{{ route('settings.emi.scheme.store', $provider) }}"
                              style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            @csrf
                            <div style="grid-column:1/-1;">
                                <label class="settings-label">Scheme Name *</label>
                                <input type="text" name="scheme_name" required class="settings-input" placeholder='e.g. "No Cost 12M (10+2)"'>
                            </div>
                            <div>
                                <label class="settings-label">Tenure (months) *</label>
                                <select name="tenure_months" required class="settings-input">
                                    @foreach([3,6,9,12,18,24,36] as $m)
                                    <option value="{{ $m }}">{{ $m }} months</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="settings-label">Upfront EMIs (patient pays today)</label>
                                <input type="number" name="upfront_emis" value="0" min="0" max="12" class="settings-input">
                                <p style="font-size:10px;color:#9a7aaa;margin:2px 0 0;">e.g. 2 for "10+2" scheme</p>
                            </div>
                            <div>
                                <label class="settings-label">Clinic Interest Cost (% of invoice) *</label>
                                <input type="number" name="clinic_interest_rate" value="0" min="0" max="50" step="0.01" class="settings-input">
                                <p style="font-size:10px;color:#9a7aaa;margin:2px 0 0;">Provider charges this % to clinic</p>
                            </div>
                            <div>
                                <label class="settings-label">GST on Interest (%)</label>
                                <input type="number" name="gst_on_interest" value="18" min="0" max="30" step="0.01" class="settings-input">
                            </div>
                            <div style="grid-column:1/-1;display:flex;align-items:center;gap:10px;">
                                <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                    <input type="checkbox" name="pass_cost_to_patient" value="1"
                                           style="accent-color:#6a0f70;width:16px;height:16px;">
                                    <span style="font-size:12px;color:#374151;">Pass clinic cost to patient as convenience charge</span>
                                </label>
                            </div>
                            <div style="grid-column:1/-1;">
                                <button type="submit" style="background:#6a0f70;color:#fff;border:none;border-radius:8px;padding:8px 18px;font-size:13px;cursor:pointer;">
                                    Save Scheme
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- Schemes list --}}
                    @if($provider->schemes->count())
                    <div style="padding:0;">
                        @foreach($provider->schemes as $scheme)
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 16px;border-bottom:1px solid #f5f0f8;font-size:12.5px;{{ $loop->last ? 'border-bottom:none;' : '' }}">
                            <div>
                                <span style="font-weight:600;color:#{{ $scheme->is_active ? '1a0320' : '9ca3af' }};">{{ $scheme->scheme_name }}</span>
                                <span style="color:#9a7aaa;margin-left:8px;">{{ $scheme->tenure_months }}M</span>
                                @if($scheme->upfront_emis)
                                <span style="color:#b45309;margin-left:4px;">({{ $scheme->upfront_emis }} upfront)</span>
                                @endif
                                <span style="color:#6b7280;margin-left:6px;">· Clinic cost: {{ $scheme->clinic_interest_rate }}% + {{ $scheme->gst_on_interest }}% GST</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:6px;">
                                {{-- Convenience charge toggle --}}
                                <form method="POST" action="{{ route('settings.emi.scheme.passthrough', $scheme) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit"
                                            title="{{ $scheme->pass_cost_to_patient ? 'Currently: passing cost to patient. Click to absorb.' : 'Currently: clinic absorbs cost. Click to pass to patient.' }}"
                                            style="background:{{ $scheme->pass_cost_to_patient ? '#fef3c7' : '#f3f4f6' }};border:1px solid {{ $scheme->pass_cost_to_patient ? '#d97706' : '#d1d5db' }};border-radius:6px;padding:3px 8px;font-size:10px;cursor:pointer;color:{{ $scheme->pass_cost_to_patient ? '#92400e' : '#6b7280' }};">
                                        {{ $scheme->pass_cost_to_patient ? 'Passes to patient' : 'Clinic absorbs' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('settings.emi.scheme.toggle', $scheme) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit" style="background:none;border:1px solid #ede4f3;border-radius:6px;padding:3px 8px;font-size:10px;cursor:pointer;color:#6b7280;">
                                        {{ $scheme->is_active ? 'Disable' : 'Enable' }}
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div style="padding:12px 16px;font-size:12px;color:#9a7aaa;">No schemes yet — click "+ Scheme" to add one.</div>
                    @endif
                </div>
                @empty
                <div style="text-align:center;padding:28px;color:#9a7aaa;font-size:13px;">
                    No EMI providers configured yet. Add one above to enable provider-financed EMI payments.
                </div>
                @endforelse
            </div>

        </div>
    </div>

    {{-- ════════════════════════════════════════════
         TAB · PRINTING
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='printing'" x-cloak>
        <div style="max-width:720px;margin:0 auto;">
            <form action="{{ route('settings.print.save') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @php $p = $print; @endphp

                {{-- ── Document Header ── --}}
                <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:20px;">
                    <h3 class="settings-section-title">Document Header</h3>
                    <p style="font-size:12.5px;color:#9a7aaa;margin:0 0 18px;">Choose what appears at the top of printed documents.</p>

                    <div style="display:flex;flex-direction:column;gap:12px;">

                        {{-- Plain Paper --}}
                        <label style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border:1.5px solid #ede4f3;border-radius:10px;cursor:pointer;"
                               :style="printHeader==='plain' ? 'border-color:#8b44aa;background:#fdf8ff;' : ''">
                            <input type="radio" name="print_header_type" value="plain"
                                   x-model="printHeader" style="margin-top:2px;accent-color:#8b44aa;">
                            <div>
                                <div style="font-size:13.5px;font-weight:600;color:#1a0320;">Plain Paper</div>
                                <div style="font-size:12px;color:#9a7aaa;margin-top:2px;">No header — use your pre-printed stationery.</div>
                            </div>
                        </label>

                        {{-- Clinic Logo --}}
                        <label style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border:1.5px solid #ede4f3;border-radius:10px;cursor:pointer;"
                               :style="printHeader==='logo' ? 'border-color:#8b44aa;background:#fdf8ff;' : ''">
                            <input type="radio" name="print_header_type" value="logo"
                                   x-model="printHeader" style="margin-top:2px;accent-color:#8b44aa;">
                            <div>
                                <div style="font-size:13.5px;font-weight:600;color:#1a0320;">Clinic Logo</div>
                                <div style="font-size:12px;color:#9a7aaa;margin-top:2px;">Print your clinic logo + name/address from Clinic Profile.</div>
                            </div>
                        </label>

                        {{-- Clinic Letterhead --}}
                        <label style="display:flex;align-items:flex-start;gap:12px;padding:14px 16px;border:1.5px solid #ede4f3;border-radius:10px;cursor:pointer;"
                               :style="printHeader==='letterhead' ? 'border-color:#8b44aa;background:#fdf8ff;' : ''">
                            <input type="radio" name="print_header_type" value="letterhead"
                                   x-model="printHeader" style="margin-top:2px;accent-color:#8b44aa;">
                            <div style="flex:1;">
                                <div style="font-size:13.5px;font-weight:600;color:#1a0320;">Clinic Letterhead</div>
                                <div style="font-size:12px;color:#9a7aaa;margin:2px 0 10px;">Upload a full-width letterhead image (recommended: 120 × 900 px).</div>

                                {{-- Letterhead upload + preview --}}
                                <div x-show="printHeader==='letterhead'" style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                                    @php $lh = $p['print_letterhead'] ?? null; @endphp
                                    <div style="border:1.5px dashed #d4b8e0;border-radius:8px;overflow:hidden;background:#fafafa;min-width:160px;max-width:280px;">
                                        <template x-if="letterheadPreview">
                                            <img :src="letterheadPreview" style="width:100%;display:block;">
                                        </template>
                                        <template x-if="!letterheadPreview">
                                            @if($lh)
                                                <img src="{{ Storage::url($lh) }}" style="width:100%;display:block;">
                                            @else
                                                <div style="padding:20px;text-align:center;color:#c5a8d8;font-size:12px;">No image uploaded</div>
                                            @endif
                                        </template>
                                    </div>
                                    <div>
                                        <label style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border:1.5px solid #ddd;border-radius:6px;font-size:12.5px;cursor:pointer;color:#555;background:#fafafa;">
                                            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                            Upload Letterhead
                                            <input type="file" name="print_letterhead" accept="image/*" style="display:none;"
                                                   @change="letterheadPreview = URL.createObjectURL($event.target.files[0])">
                                        </label>
                                        <p style="font-size:11px;color:#b0a0bb;margin:5px 0 0;">PNG or JPG · 120 × 900 px recommended</p>
                                    </div>
                                </div>
                            </div>
                        </label>

                    </div>
                </div>

                {{-- ── Margins (for pre-printed stationery) ── --}}
                <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:20px;">
                    <h3 class="settings-section-title">Use Printed Stationery — Leave Space</h3>
                    <p style="font-size:12.5px;color:#9a7aaa;margin:0 0 16px;">If your stationery already has a header/footer, set margins so content doesn't overlap.</p>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        @foreach([
                            ['top',    'from top'],
                            ['bottom', 'from bottom'],
                            ['left',   'from left'],
                            ['right',  'from right'],
                        ] as [$side, $label])
                        @php
                            $enabled = !empty($p["print_margin_{$side}"]);
                            $val     = $p["print_margin_{$side}"] ?? '';
                        @endphp
                        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#fdf8ff;border:1.5px solid #ede4f3;border-radius:8px;">
                            <input type="checkbox" name="print_margin_{{ $side }}_enabled"
                                   {{ $enabled ? 'checked' : '' }}
                                   style="accent-color:#8b44aa;width:15px;height:15px;flex-shrink:0;">
                            <span style="font-size:13px;color:#555;">Leave</span>
                            <input type="number" name="print_margin_{{ $side }}" value="{{ $val }}"
                                   min="0" max="5" step="0.25"
                                   style="width:56px;padding:4px 8px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;text-align:center;">
                            <span style="font-size:13px;color:#555;">inches {{ $label }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                {{-- ── Sections to include ── --}}
                <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:20px;">
                    <h3 class="settings-section-title">Sections to Include on Prescription / Visit Print</h3>
                    <p style="font-size:12.5px;color:#9a7aaa;margin:0 0 16px;">Uncheck sections you never want printed.</p>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        @php
                        $sectionDefs = [
                            'vital_signs'    => 'Vital Signs',
                            'complaints'     => 'Complaints',
                            'notes'          => 'Notes',
                            'investigations' => 'Investigations',
                            'diagnosis'      => 'Diagnosis',
                            'treatments'     => 'Treatments',
                            'remarks'        => 'Remarks / Advice',
                            'followup'       => 'Follow-up Date',
                        ];
                        @endphp
                        @foreach($sectionDefs as $key => $label)
                        @php $checked = ($p["print_section_{$key}"] ?? '1') === '1'; @endphp
                        <label style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:#fdf8ff;border:1.5px solid #ede4f3;border-radius:8px;cursor:pointer;">
                            <input type="checkbox" name="print_section_{{ $key }}"
                                   {{ $checked ? 'checked' : '' }}
                                   style="accent-color:#8b44aa;width:15px;height:15px;flex-shrink:0;">
                            <span style="font-size:13px;color:#1a0320;">{{ $label }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <button type="submit" class="settings-save-btn">Save Print Settings</button>
            </form>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         TAB 6 · MASTERS
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='masters'" x-cloak>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px;margin:0 auto;">

            {{-- Treatments --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div style="padding:14px 18px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;">
                    <span class="settings-section-title" style="margin:0;">Treatments</span>
                    <span style="font-size:11px;color:#9a7aaa;">{{ $treatments->count() }}</span>
                </div>
                <form action="{{ route('settings.masters.treatments.store') }}" method="POST" style="padding:12px 18px;border-bottom:1px solid #f5f0f8;display:flex;gap:8px;">
                    @csrf
                    <input name="name" class="settings-input" placeholder="Treatment name" required style="flex:1;">
                    <input name="default_price" type="number" class="settings-input" placeholder="Rs. " style="width:72px;" min="0">
                    <button type="submit" class="settings-save-btn" style="padding:7px 14px;font-size:12px;">+</button>
                </form>
                <div style="max-height:240px;overflow-y:auto;">
                    @forelse($treatments as $item)
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 18px;border-bottom:1px solid #f9f5fc;">
                        <span style="font-size:13px;color:#1a0320;">{{ $item->name }}</span>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if(isset($item->default_price) && $item->default_price > 0)
                            <span style="font-size:12px;color:#9a7aaa;">Rs. {{ number_format($item->default_price,0) }}</span>
                            @endif
                            <form action="{{ route('settings.masters.treatments.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Remove?')">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:none;border:none;cursor:pointer;color:#c5a8d8;font-size:18px;line-height:1;padding:0;">×</button>
                            </form>
                        </div>
                    </div>
                    @empty
                    <p style="padding:16px 18px;color:#b0a0bb;font-size:12.5px;margin:0;">No treatments yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Complaints --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div style="padding:14px 18px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;">
                    <span class="settings-section-title" style="margin:0;">Complaints</span>
                    <span style="font-size:11px;color:#9a7aaa;">{{ $complaints->count() }}</span>
                </div>
                <form action="{{ route('settings.masters.complaints.store') }}" method="POST" style="padding:12px 18px;border-bottom:1px solid #f5f0f8;display:flex;gap:8px;">
                    @csrf
                    <input name="name" class="settings-input" placeholder="Chief complaint" required style="flex:1;">
                    <button type="submit" class="settings-save-btn" style="padding:7px 14px;font-size:12px;">+</button>
                </form>
                <div style="max-height:240px;overflow-y:auto;">
                    @forelse($complaints as $item)
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 18px;border-bottom:1px solid #f9f5fc;">
                        <span style="font-size:13px;color:#1a0320;">{{ $item->name }}</span>
                        <form action="{{ route('settings.masters.complaints.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Remove?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none;border:none;cursor:pointer;color:#c5a8d8;font-size:18px;line-height:1;padding:0;">×</button>
                        </form>
                    </div>
                    @empty
                    <p style="padding:16px 18px;color:#b0a0bb;font-size:12.5px;margin:0;">No complaints yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Diagnoses --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div style="padding:14px 18px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;">
                    <span class="settings-section-title" style="margin:0;">Diagnoses</span>
                    <span style="font-size:11px;color:#9a7aaa;">{{ $diagnoses->count() }}</span>
                </div>
                <form action="{{ route('settings.masters.diagnoses.store') }}" method="POST" style="padding:12px 18px;border-bottom:1px solid #f5f0f8;display:flex;gap:8px;">
                    @csrf
                    <input name="name" class="settings-input" placeholder="Diagnosis name" required style="flex:1;">
                    <button type="submit" class="settings-save-btn" style="padding:7px 14px;font-size:12px;">+</button>
                </form>
                <div style="max-height:240px;overflow-y:auto;">
                    @forelse($diagnoses as $item)
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 18px;border-bottom:1px solid #f9f5fc;">
                        <span style="font-size:13px;color:#1a0320;">{{ $item->name }}</span>
                        <form action="{{ route('settings.masters.diagnoses.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Remove?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none;border:none;cursor:pointer;color:#c5a8d8;font-size:18px;line-height:1;padding:0;">×</button>
                        </form>
                    </div>
                    @empty
                    <p style="padding:16px 18px;color:#b0a0bb;font-size:12.5px;margin:0;">No diagnoses yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Investigations --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div style="padding:14px 18px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;">
                    <span class="settings-section-title" style="margin:0;">Investigations</span>
                    <span style="font-size:11px;color:#9a7aaa;">{{ $investigations->count() }}</span>
                </div>
                <form action="{{ route('settings.masters.investigations.store') }}" method="POST" style="padding:12px 18px;border-bottom:1px solid #f5f0f8;display:flex;gap:8px;">
                    @csrf
                    <input name="name" class="settings-input" placeholder="Investigation name" required style="flex:1;">
                    <button type="submit" class="settings-save-btn" style="padding:7px 14px;font-size:12px;">+</button>
                </form>
                <div style="max-height:240px;overflow-y:auto;">
                    @forelse($investigations as $item)
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 18px;border-bottom:1px solid #f9f5fc;">
                        <span style="font-size:13px;color:#1a0320;">{{ $item->name }}</span>
                        <form action="{{ route('settings.masters.investigations.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Remove?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none;border:none;cursor:pointer;color:#c5a8d8;font-size:18px;line-height:1;padding:0;">×</button>
                        </form>
                    </div>
                    @empty
                    <p style="padding:16px 18px;color:#b0a0bb;font-size:12.5px;margin:0;">No investigations yet.</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    {{-- ════════════════════════════════════════════
         TAB 7 · CLINICAL
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='clinical'" x-cloak>
        <div style="max-width:560px;margin:0 auto;">

            {{-- Medicines --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div style="padding:14px 18px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;">
                    <span class="settings-section-title" style="margin:0;">Medicines / Drugs</span>
                    <span style="font-size:11px;color:#9a7aaa;">{{ $medicines->count() }}</span>
                </div>
                <form action="{{ route('settings.masters.medicines.store') }}" method="POST" style="padding:12px 18px;border-bottom:1px solid #f5f0f8;display:flex;gap:8px;flex-wrap:wrap;">
                    @csrf
                    <input name="name" class="settings-input" placeholder="Medicine name" required style="flex:1;min-width:160px;">
                    <input name="default_dose" class="settings-input" placeholder="Default dose (e.g. 500mg)" style="width:160px;">
                    <button type="submit" class="settings-save-btn" style="padding:7px 14px;font-size:12px;">Add</button>
                </form>
                <div style="max-height:320px;overflow-y:auto;">
                    @forelse($medicines as $item)
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 18px;border-bottom:1px solid #f9f5fc;">
                        <div>
                            <span style="font-size:13px;color:#1a0320;">{{ $item->name }}</span>
                            @if(isset($item->default_dose) && $item->default_dose)
                            <span style="font-size:11.5px;color:#9a7aaa;margin-left:8px;">{{ $item->default_dose }}</span>
                            @endif
                        </div>
                        <form action="{{ route('settings.masters.medicines.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Remove?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none;border:none;cursor:pointer;color:#c5a8d8;font-size:18px;line-height:1;padding:0;">×</button>
                        </form>
                    </div>
                    @empty
                    <p style="padding:16px 18px;color:#b0a0bb;font-size:12.5px;margin:0;">No medicines yet.</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>

    {{-- ════════════════════════════════════════════
         TAB 8 · PATIENT DEFAULTS
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='patient-defaults'" x-cloak>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px;margin:0 auto;">

            {{-- Medical Conditions --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div style="padding:14px 18px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;">
                    <span class="settings-section-title" style="margin:0;">Medical Conditions</span>
                    <span style="font-size:11px;color:#9a7aaa;">{{ $medicalConditions->count() }}</span>
                </div>
                <form action="{{ route('settings.masters.medical_conditions.store') }}" method="POST" style="padding:12px 18px;border-bottom:1px solid #f5f0f8;display:flex;gap:8px;">
                    @csrf
                    <input name="name" class="settings-input" placeholder="e.g. Diabetes, Hypertension" required style="flex:1;">
                    <button type="submit" class="settings-save-btn" style="padding:7px 14px;font-size:12px;">+</button>
                </form>
                <div style="max-height:240px;overflow-y:auto;">
                    @forelse($medicalConditions as $item)
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 18px;border-bottom:1px solid #f9f5fc;">
                        <span style="font-size:13px;color:#1a0320;">{{ $item->name }}</span>
                        <form action="{{ route('settings.masters.medical_conditions.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Remove?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none;border:none;cursor:pointer;color:#c5a8d8;font-size:18px;line-height:1;padding:0;">×</button>
                        </form>
                    </div>
                    @empty
                    <p style="padding:16px 18px;color:#b0a0bb;font-size:12.5px;margin:0;">No conditions yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Dental Conditions --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div style="padding:14px 18px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;">
                    <span class="settings-section-title" style="margin:0;">Dental Conditions</span>
                    <span style="font-size:11px;color:#9a7aaa;">{{ $dentalConditions->count() }}</span>
                </div>
                <form action="{{ route('settings.masters.dental_conditions.store') }}" method="POST" style="padding:12px 18px;border-bottom:1px solid #f5f0f8;display:flex;gap:8px;">
                    @csrf
                    <input name="name" class="settings-input" placeholder="e.g. Caries, Periodontitis" required style="flex:1;">
                    <button type="submit" class="settings-save-btn" style="padding:7px 14px;font-size:12px;">+</button>
                </form>
                <div style="max-height:240px;overflow-y:auto;">
                    @forelse($dentalConditions as $item)
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 18px;border-bottom:1px solid #f9f5fc;">
                        <span style="font-size:13px;color:#1a0320;">{{ $item->name }}</span>
                        <form action="{{ route('settings.masters.dental_conditions.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Remove?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none;border:none;cursor:pointer;color:#c5a8d8;font-size:18px;line-height:1;padding:0;">×</button>
                        </form>
                    </div>
                    @empty
                    <p style="padding:16px 18px;color:#b0a0bb;font-size:12.5px;margin:0;">No conditions yet.</p>
                    @endforelse
                </div>
            </div>

            {{-- Patient Sources --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div style="padding:14px 18px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;">
                    <span class="settings-section-title" style="margin:0;">Patient Sources</span>
                    <span style="font-size:11px;color:#9a7aaa;">{{ $patientSources->count() }}</span>
                </div>
                <form action="{{ route('settings.masters.patient_sources.store') }}" method="POST" style="padding:12px 18px;border-bottom:1px solid #f5f0f8;display:flex;gap:8px;">
                    @csrf
                    <input name="name" class="settings-input" placeholder="e.g. Referral, Walk-in, Instagram" required style="flex:1;">
                    <button type="submit" class="settings-save-btn" style="padding:7px 14px;font-size:12px;">+</button>
                </form>
                <div style="max-height:240px;overflow-y:auto;">
                    @forelse($patientSources as $item)
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 18px;border-bottom:1px solid #f9f5fc;">
                        <span style="font-size:13px;color:#1a0320;">{{ $item->name }}</span>
                        <form action="{{ route('settings.masters.patient_sources.destroy', $item->id) }}" method="POST" onsubmit="return confirm('Remove?')">
                            @csrf @method('DELETE')
                            <button type="submit" style="background:none;border:none;cursor:pointer;color:#c5a8d8;font-size:18px;line-height:1;padding:0;">×</button>
                        </form>
                    </div>
                    @empty
                    <p style="padding:16px 18px;color:#b0a0bb;font-size:12.5px;margin:0;">No sources yet.</p>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- ── Patient ID Settings ── --}}
        @php $patientSettings = \App\Models\AppSetting::group('patients'); @endphp
        <div style="max-width:560px;margin:20px auto 0;">
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;">
                <h3 class="settings-section-title">Patient ID</h3>
                <p style="font-size:12.5px;color:#9a7aaa;margin:-6px 0 18px;">Configure how Patient IDs are generated for new registrations.</p>
                <form action="{{ route('settings.patient_id.save') }}" method="POST">
                    @csrf

                    {{-- Auto-generate toggle --}}
                    <div style="margin-bottom:18px;">
                        <label style="display:flex;align-items:center;gap:10px;cursor:pointer;">
                            <input type="checkbox" name="patient_id_auto" value="1"
                                {{ ($patientSettings['patient_id_auto'] ?? '1') === '1' ? 'checked' : '' }}
                                style="width:16px;height:16px;accent-color:#6a0f70;">
                            <span style="font-size:13px;color:#1a0320;font-weight:500;">Auto-generate Patient ID</span>
                        </label>
                        <p style="font-size:11.5px;color:#b0a0bb;margin:5px 0 0 26px;">When checked, a unique ID is assigned on registration. Uncheck to enter IDs manually.</p>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:18px;">
                        <div>
                            <label class="settings-label">Prefix</label>
                            <input type="text" name="patient_id_prefix" maxlength="10"
                                value="{{ $patientSettings['patient_id_prefix'] ?? 'DF' }}"
                                placeholder="e.g. DF, TDC, PT"
                                class="settings-input" style="text-transform:uppercase;">
                            <p style="font-size:11px;color:#b0a0bb;margin-top:4px;">Letters/numbers only.</p>
                        </div>
                        <div>
                            <label class="settings-label">Start From Number</label>
                            <input type="number" name="patient_id_start" min="1"
                                value="{{ $patientSettings['patient_id_start'] ?? 1 }}"
                                class="settings-input" placeholder="e.g. 3201">
                            <p style="font-size:11px;color:#b0a0bb;margin-top:4px;">Next new patient gets this number.</p>
                        </div>
                        <div>
                            <label class="settings-label">Number Digits</label>
                            <select name="patient_id_digits" class="settings-input">
                                @foreach([3,4,5,6] as $d)
                                    <option value="{{ $d }}" {{ ($patientSettings['patient_id_digits'] ?? '5') == $d ? 'selected' : '' }}>
                                        {{ $d }} digits
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Live preview --}}
                    <div style="background:#f5eef9;border:1px solid #e0d4ea;border-radius:8px;padding:10px 14px;margin-bottom:18px;font-size:12.5px;color:#6a0f70;">
                        Next patient will get:
                        <strong>{{ ($patientSettings['patient_id_prefix'] ?? 'DF') }}-{{ str_pad((int)($patientSettings['patient_id_start'] ?? 1), (int)($patientSettings['patient_id_digits'] ?? 5), '0', STR_PAD_LEFT) }}</strong>
                    </div>

                    <button type="submit" class="settings-save-btn">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        Save Patient ID Settings
                    </button>
                </form>
            </div>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         TAB 9 · GROWTH & COMMS
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='growth'" x-cloak>
        <div style="max-width:700px;margin:0 auto;">

            {{-- Message Templates --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div style="padding:14px 18px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;">
                    <span class="settings-section-title" style="margin:0;">Message Templates</span>
                    <span style="font-size:11px;color:#9a7aaa;">{{ $messageTemplates->count() }}</span>
                </div>
                <form action="{{ route('settings.masters.message_templates.store') }}" method="POST" style="padding:14px 18px;border-bottom:1px solid #f5f0f8;">
                    @csrf
                    <div style="display:flex;gap:8px;margin-bottom:8px;">
                        <input name="name" class="settings-input" placeholder="Template name (e.g. Appointment Reminder)" required style="flex:1;">
                        <select name="type" class="settings-input" style="width:140px;">
                            <option value="whatsapp">WhatsApp</option>
                            <option value="sms">SMS</option>
                            <option value="email">Email</option>
                        </select>
                    </div>
                    <textarea name="body" class="settings-input" rows="3" placeholder="Message body. Use {patient_name}, {date}, {clinic_name} as placeholders." style="resize:vertical;width:100%;box-sizing:border-box;margin-bottom:8px;"></textarea>
                    <button type="submit" class="settings-save-btn" style="padding:7px 16px;font-size:12px;">Add Template</button>
                </form>
                <div style="max-height:320px;overflow-y:auto;">
                    @forelse($messageTemplates as $tpl)
                    <div style="padding:12px 18px;border-bottom:1px solid #f9f5fc;">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                            <span style="font-size:13px;font-weight:600;color:#1a0320;">{{ $tpl->name }}</span>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <span style="font-size:10.5px;background:#f3eef7;color:#8b44aa;padding:2px 8px;border-radius:20px;text-transform:uppercase;font-weight:600;">{{ $tpl->type ?? 'general' }}</span>
                                <form action="{{ route('settings.masters.message_templates.destroy', $tpl->id) }}" method="POST" onsubmit="return confirm('Remove?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="background:none;border:none;cursor:pointer;color:#c5a8d8;font-size:18px;line-height:1;padding:0;">×</button>
                                </form>
                            </div>
                        </div>
                        <p style="font-size:12px;color:#9a7aaa;margin:0;white-space:pre-wrap;">{{ $tpl->body ?? $tpl->content ?? '' }}</p>
                    </div>
                    @empty
                    <p style="padding:16px 18px;color:#b0a0bb;font-size:12.5px;margin:0;">No templates yet.</p>
                    @endforelse
                </div>
            </div>

        </div>
    </div>


    {{-- ════════════════════════════════════════════
         TAB · INVENTORY SETTINGS
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='inventory'" x-cloak>

        {{-- ── Categories + Sub-types + Locations — squeezed into one row, like the Huddle board columns ── --}}
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px;margin-bottom:24px;max-width:1360px;margin-left:auto;margin-right:auto;">

            {{-- Categories --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;">
                <div style="padding:14px 20px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;background:#faf6fc;">
                    <div>
                        <h3 class="settings-section-title" style="margin:0 0 2px;">Item Categories</h3>
                        <p style="font-size:12px;color:#9a7aaa;margin:0;">{{ $invCategories->count() }} categories</p>
                    </div>
                    <button onclick="document.getElementById('inv-modal-add-category').style.display='flex'"
                            style="background:#6a0f70;color:#fff;border:none;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:5px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add
                    </button>
                </div>
                <div style="padding:10px 20px;border-bottom:1px solid #f5f0f8;">
                    <input type="text" id="inv-cat-filter" oninput="invFilterCategories()"
                           placeholder="Filter categories…" class="settings-input" style="font-size:12.5px;padding:7px 10px;">
                </div>
                <div id="inv-cat-list" style="max-height:420px;overflow-y:auto;">
                    @forelse($invCategories as $cat)
                    <div data-name="{{ strtolower($cat->name) }}" style="display:flex;align-items:center;gap:12px;padding:11px 20px;border-bottom:1px solid #f5f0f8;{{ !$cat->is_active ? 'opacity:0.5;' : '' }}">
                        <span style="width:10px;height:10px;border-radius:50%;flex-shrink:0;background:{{ $cat->color ?: '#ccc' }};border:1px solid rgba(0,0,0,0.08);"></span>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:500;color:#1a0320;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                                {{ $cat->name }}@if(!$cat->is_active)<span style="font-size:10px;color:#9a7aaa;margin-left:4px;">(inactive)</span>@endif
                            </div>
                            @if($cat->description)<div style="font-size:11px;color:#9a7aaa;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $cat->description }}</div>@endif
                        </div>
                        <span style="font-size:11px;color:#9a7aaa;flex-shrink:0;">{{ $cat->items_count }} items</span>
                        <div style="display:flex;gap:4px;flex-shrink:0;">
                            <button onclick='invOpenEditCategory({{ $cat->toJson() }})' style="background:#f5f0f8;border:none;border-radius:4px;padding:5px 9px;cursor:pointer;font-size:11px;color:#6a0f70;">Edit</button>
                            @if($cat->items_count == 0)
                            <form method="POST" action="{{ route('inventory.settings.categories.destroy', $cat) }}" onsubmit="return confirm('Delete category \'{{ $cat->name }}\'?')">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:#fdeaea;border:none;border-radius:4px;padding:5px 9px;cursor:pointer;font-size:11px;color:#b52020;">✕</button>
                            </form>
                            @endif
                        </div>
                    </div>
                    @empty
                    <div style="padding:32px;text-align:center;font-size:13px;color:#9a7aaa;">No categories yet.</div>
                    @endforelse
                </div>
            </div>

            {{-- Sub-types --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;">
                <div style="padding:14px 20px;border-bottom:1px solid #e8f7ee;display:flex;align-items:center;justify-content:space-between;background:#f5fbf8;">
                    <div>
                        <h3 class="settings-section-title" style="margin:0 0 2px;color:#1a7a45;">Sub-types</h3>
                        <p style="font-size:12px;color:#5a9a6a;margin:0;">Product sub-types per category</p>
                    </div>
                    <button onclick="document.getElementById('inv-modal-add-subtype').style.display='flex'"
                            style="background:#1a7a45;color:#fff;border:none;border-radius:5px;padding:7px 14px;font-size:12px;font-weight:500;cursor:pointer;">+ Add</button>
                </div>
                <div style="padding:10px 20px;border-bottom:1px solid #f5f0f8;">
                    <input type="text" id="inv-sub-filter" oninput="invFilterSubTypes()"
                           placeholder="Filter sub-types…" class="settings-input" style="font-size:12.5px;padding:7px 10px;">
                </div>
                <div id="inv-sub-list" style="max-height:420px;overflow-y:auto;padding:10px 20px;">
                    @forelse($invSubTypes->groupBy(fn($st) => $st->category?->name ?? 'Uncategorised') as $catName => $group)
                    <details data-name="{{ strtolower($catName) }}" style="margin-bottom:6px;">
                        <summary style="cursor:pointer;font-size:12px;font-weight:600;color:#1a7a45;padding:6px 0;list-style:none;display:flex;align-items:center;gap:6px;">
                            <span style="display:inline-block;width:0;height:0;border-top:4px solid transparent;border-bottom:4px solid transparent;border-left:5px solid #5a9a6a;transition:transform .12s;"></span>
                            {{ $catName }}
                            <span style="font-weight:400;color:#9a7aaa;">({{ $group->count() }})</span>
                        </summary>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;padding:4px 0 10px 11px;">
                            @foreach($group as $st)
                            <div data-tag="{{ strtolower($st->name) }}" style="display:inline-flex;align-items:center;gap:6px;background:{{ $st->is_active ? '#e8f7ee' : '#f5f5f5' }};border:1px solid {{ $st->is_active ? '#a3d9b8' : '#ddd' }};border-radius:20px;padding:4px 10px 4px 12px;font-size:12.5px;color:{{ $st->is_active ? '#1a7a45' : '#888' }};">
                                {{ $st->name }}
                                <button onclick="invOpenEditSubType({{ $st->id }}, '{{ addslashes($st->name) }}', {{ $st->category_id }}, {{ $st->is_active ? 'true' : 'false' }})" style="background:none;border:none;cursor:pointer;font-size:11px;color:#888;padding:0;line-height:1;" title="Edit">✎</button>
                                <form method="POST" action="{{ route('inventory.settings.sub-types.destroy', $st) }}" style="display:inline;" onsubmit="return confirm('Delete?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="background:none;border:none;cursor:pointer;font-size:13px;color:#b52020;padding:0;line-height:1;">×</button>
                                </form>
                            </div>
                            @endforeach
                        </div>
                    </details>
                    @empty
                    <div style="padding:24px;text-align:center;font-size:13px;color:#9a7aaa;">No sub-types yet.</div>
                    @endforelse
                </div>
            </div>

            {{-- Locations — third column in the same row --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;display:flex;flex-direction:column;">
                <div style="padding:14px 20px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;background:#faf6fc;">
                    <div>
                        <h3 class="settings-section-title" style="margin:0 0 2px;">Storage Locations</h3>
                        <p style="font-size:12px;color:#9a7aaa;margin:0;">{{ $invLocations->count() }} locations</p>
                    </div>
                    <button onclick="document.getElementById('inv-modal-add-location').style.display='flex'"
                            style="background:#6a0f70;color:#fff;border:none;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:5px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add
                    </button>
                </div>
                <div style="max-height:420px;overflow-y:auto;">
                    @forelse($invLocations as $loc)
                    <div style="display:flex;align-items:center;gap:10px;padding:11px 20px;border-bottom:1px solid #f5f0f8;{{ !$loc->is_active ? 'opacity:0.5;' : '' }}">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:500;color:#1a0320;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $loc->name }}@if(!$loc->is_active)<span style="font-size:10px;color:#9a7aaa;margin-left:4px;">(inactive)</span>@endif</div>
                            <div style="font-size:11px;color:#9a7aaa;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $loc->getTypeLabel() }}@if($loc->code) · <code style="font-size:11px;">{{ $loc->code }}</code>@endif</div>
                        </div>
                        <div style="display:flex;gap:4px;flex-shrink:0;">
                            <button onclick='invOpenEditLocation({{ $loc->toJson() }})' style="background:#f5f0f8;border:none;border-radius:4px;padding:5px 9px;cursor:pointer;font-size:11px;color:#6a0f70;">Edit</button>
                            <form method="POST" action="{{ route('inventory.settings.locations.destroy', $loc) }}" onsubmit="return confirm('Deactivate \'{{ $loc->name }}\'?')">
                                @csrf @method('DELETE')
                                <button type="submit" style="background:#fdeaea;border:none;border-radius:4px;padding:5px 9px;cursor:pointer;font-size:11px;color:#b52020;">✕</button>
                            </form>
                        </div>
                    </div>
                    @empty
                    <div style="padding:32px;text-align:center;font-size:13px;color:#9a7aaa;">No locations yet.</div>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- ── Procurement Controls ── --}}
        @php $grnWindow = (int) \App\Models\AppSetting::get('grn_correction_window_hours', 0); @endphp
        <div style="max-width:960px;margin:24px auto 0;">
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #ede4f3;background:#fdf9f0;">
                    <h3 class="settings-section-title" style="margin:0 0 2px;color:#7a4500;">Procurement Controls</h3>
                    <p style="font-size:12px;color:#9a7a50;margin:0;">Time window to allow staff to undo an accidental GRN (goods receipt). After this period the receipt is locked.</p>
                </div>
                <form method="POST" action="{{ route('settings.inventory.save') }}" style="padding:20px 24px;">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:end;">
                        <div>
                            <label style="display:block;font-size:12px;font-weight:600;color:#7a4500;margin-bottom:6px;">
                                GRN Correction Window
                            </label>
                            <p style="font-size:11.5px;color:#9a7aaa;margin:0 0 8px;">
                                Staff can undo the most recent receipt within this time after recording it.
                                Set to <strong>Disabled</strong> to lock GRNs immediately.
                            </p>
                            <select name="grn_correction_window_hours"
                                    style="width:100%;max-width:240px;padding:9px 12px;border:1.5px solid #e8d8b0;
                                           border-radius:6px;font-size:13px;font-family:'Inter',sans-serif;
                                           color:#1e0a2c;outline:none;background:#fff;">
                                <option value="0"  {{ $grnWindow === 0  ? 'selected' : '' }}>Disabled (GRNs locked immediately)</option>
                                <option value="1"  {{ $grnWindow === 1  ? 'selected' : '' }}>1 hour</option>
                                <option value="2"  {{ $grnWindow === 2  ? 'selected' : '' }}>2 hours</option>
                                <option value="4"  {{ $grnWindow === 4  ? 'selected' : '' }}>4 hours</option>
                                <option value="8"  {{ $grnWindow === 8  ? 'selected' : '' }}>8 hours</option>
                                <option value="12" {{ $grnWindow === 12 ? 'selected' : '' }}>12 hours</option>
                                <option value="24" {{ $grnWindow === 24 ? 'selected' : '' }}>24 hours (1 day)</option>
                                <option value="48" {{ $grnWindow === 48 ? 'selected' : '' }}>48 hours (2 days)</option>
                            </select>
                        </div>
                        <div>
                            @if($grnWindow > 0)
                            <div style="background:#fff4e0;border:1px solid #f0d080;border-radius:8px;padding:12px 14px;font-size:12.5px;color:#7a4500;line-height:1.5;">
                                <strong>Currently active:</strong> Staff can undo the last receipt within
                                <strong>{{ $grnWindow }} hour{{ $grnWindow > 1 ? 's' : '' }}</strong> of recording it.
                                After that, only an admin can make corrections manually.
                            </div>
                            @else
                            <div style="background:#f5f0f8;border:1px solid #ede4f3;border-radius:8px;padding:12px 14px;font-size:12.5px;color:#9a7aaa;line-height:1.5;">
                                GRN corrections are <strong>disabled</strong>. Once a receipt is recorded it is permanent.
                            </div>
                            @endif
                        </div>
                    </div>
                    <div style="margin-top:16px;">
                        <button type="submit"
                                style="padding:8px 22px;background:#6a0f70;color:#fff;border:none;
                                       border-radius:6px;font-size:13px;font-weight:500;
                                       cursor:pointer;font-family:'Inter',sans-serif;">
                            Save Procurement Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ── Inventory modals (copied from old inventory/settings page) ── --}}
        {{-- Add Category --}}
        <div id="inv-modal-add-category" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:10px;width:420px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
                <div style="padding:18px 24px;border-bottom:1px solid #f0e8f4;display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="font-size:15px;font-weight:600;color:#1a0320;margin:0;">Add Category</h3>
                    <button onclick="document.getElementById('inv-modal-add-category').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#9a7aaa;">&times;</button>
                </div>
                <form method="POST" action="{{ route('inventory.settings.categories.store') }}" style="padding:20px 24px;">
                    @csrf
                    <div style="margin-bottom:14px;"><label class="settings-label">Name *</label><input type="text" name="name" required class="settings-input"></div>
                    <div style="margin-bottom:14px;"><label class="settings-label">Colour Tag</label><div style="display:flex;align-items:center;gap:10px;"><input type="color" name="color" value="#6a0f70" style="width:36px;height:36px;border:1px solid #d8c8e4;border-radius:4px;cursor:pointer;padding:2px;"><span style="font-size:12px;color:#9a7aaa;">Pick a colour for charts</span></div></div>
                    <div style="margin-bottom:18px;"><label class="settings-label">Description</label><input type="text" name="description" maxlength="255" class="settings-input"></div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;">
                        <button type="button" onclick="document.getElementById('inv-modal-add-category').style.display='none'" style="background:#f5f0f8;border:none;border-radius:6px;padding:8px 18px;font-size:13px;cursor:pointer;color:#6a0f70;">Cancel</button>
                        <button type="submit" class="settings-save-btn">Add Category</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Category --}}
        <div id="inv-modal-edit-category" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:10px;width:420px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
                <div style="padding:18px 24px;border-bottom:1px solid #f0e8f4;display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="font-size:15px;font-weight:600;color:#1a0320;margin:0;">Edit Category</h3>
                    <button onclick="document.getElementById('inv-modal-edit-category').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#9a7aaa;">&times;</button>
                </div>
                <form id="inv-form-edit-category" method="POST" action="" style="padding:20px 24px;">
                    @csrf @method('PUT')
                    <div style="margin-bottom:14px;"><label class="settings-label">Name *</label><input type="text" id="inv-edit-cat-name" name="name" required class="settings-input"></div>
                    <div style="margin-bottom:14px;"><label class="settings-label">Colour Tag</label><input type="color" id="inv-edit-cat-color" name="color" style="width:36px;height:36px;border:1px solid #d8c8e4;border-radius:4px;cursor:pointer;padding:2px;"></div>
                    <div style="margin-bottom:14px;"><label class="settings-label">Description</label><input type="text" id="inv-edit-cat-desc" name="description" maxlength="255" class="settings-input"></div>
                    <div style="margin-bottom:18px;"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" id="inv-edit-cat-active" name="is_active" value="1" style="accent-color:#6a0f70;"><span style="font-size:13px;color:#1a0320;">Active</span></label></div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;">
                        <button type="button" onclick="document.getElementById('inv-modal-edit-category').style.display='none'" style="background:#f5f0f8;border:none;border-radius:6px;padding:8px 18px;font-size:13px;cursor:pointer;color:#6a0f70;">Cancel</button>
                        <button type="submit" class="settings-save-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Add Location --}}
        <div id="inv-modal-add-location" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:10px;width:440px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
                <div style="padding:18px 24px;border-bottom:1px solid #f0e8f4;display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="font-size:15px;font-weight:600;color:#1a0320;margin:0;">Add Storage Location</h3>
                    <button onclick="document.getElementById('inv-modal-add-location').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#9a7aaa;">&times;</button>
                </div>
                <form method="POST" action="{{ route('inventory.settings.locations.store') }}" style="padding:20px 24px;">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                        <div><label class="settings-label">Name *</label><input type="text" name="name" required class="settings-input"></div>
                        <div><label class="settings-label">Code (optional)</label><input type="text" name="code" maxlength="20" class="settings-input" placeholder="e.g. OP-1"></div>
                    </div>
                    <div style="margin-bottom:14px;"><label class="settings-label">Type *</label>
                        <select name="type" required class="settings-input" style="background:#fff;">
                            <option value="main_store">Main Store</option><option value="operatory">Operatory</option>
                            <option value="sterilization">Sterilization</option><option value="lab">Lab</option>
                            <option value="implant_drawer">Implant Drawer</option><option value="storage">Storage</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div style="margin-bottom:18px;"><label class="settings-label">Description</label><input type="text" name="description" maxlength="255" class="settings-input"></div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;">
                        <button type="button" onclick="document.getElementById('inv-modal-add-location').style.display='none'" style="background:#f5f0f8;border:none;border-radius:6px;padding:8px 18px;font-size:13px;cursor:pointer;color:#6a0f70;">Cancel</button>
                        <button type="submit" class="settings-save-btn">Add Location</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Location --}}
        <div id="inv-modal-edit-location" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:10px;width:440px;max-width:94vw;box-shadow:0 20px 60px rgba(0,0,0,0.2);">
                <div style="padding:18px 24px;border-bottom:1px solid #f0e8f4;display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="font-size:15px;font-weight:600;color:#1a0320;margin:0;">Edit Location</h3>
                    <button onclick="document.getElementById('inv-modal-edit-location').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#9a7aaa;">&times;</button>
                </div>
                <form id="inv-form-edit-location" method="POST" action="" style="padding:20px 24px;">
                    @csrf @method('PUT')
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                        <div><label class="settings-label">Name *</label><input type="text" id="inv-edit-loc-name" name="name" required class="settings-input"></div>
                        <div><label class="settings-label">Code</label><input type="text" id="inv-edit-loc-code" name="code" maxlength="20" class="settings-input"></div>
                    </div>
                    <div style="margin-bottom:14px;"><label class="settings-label">Type *</label>
                        <select id="inv-edit-loc-type" name="type" required class="settings-input" style="background:#fff;">
                            <option value="main_store">Main Store</option><option value="operatory">Operatory</option>
                            <option value="sterilization">Sterilization</option><option value="lab">Lab</option>
                            <option value="implant_drawer">Implant Drawer</option><option value="storage">Storage</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div style="margin-bottom:14px;"><label class="settings-label">Description</label><input type="text" id="inv-edit-loc-desc" name="description" maxlength="255" class="settings-input"></div>
                    <div style="margin-bottom:18px;"><label style="display:flex;align-items:center;gap:8px;cursor:pointer;"><input type="checkbox" id="inv-edit-loc-active" name="is_active" value="1" style="accent-color:#6a0f70;"><span style="font-size:13px;color:#1a0320;">Active</span></label></div>
                    <div style="display:flex;justify-content:flex-end;gap:8px;">
                        <button type="button" onclick="document.getElementById('inv-modal-edit-location').style.display='none'" style="background:#f5f0f8;border:none;border-radius:6px;padding:8px 18px;font-size:13px;cursor:pointer;color:#6a0f70;">Cancel</button>
                        <button type="submit" class="settings-save-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Add Sub-type --}}
        <div id="inv-modal-add-subtype" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:8px;width:400px;max-width:94vw;box-shadow:0 16px 48px rgba(0,0,0,0.18);">
                <div style="padding:16px 22px;border-bottom:1px solid #e8f7ee;background:#f5fbf7;display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="font-size:16px;font-weight:600;color:#1a7a45;margin:0;">Add Sub-type</h3>
                    <button onclick="document.getElementById('inv-modal-add-subtype').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:22px;color:#5a9a6a;">&times;</button>
                </div>
                <form method="POST" action="{{ route('inventory.settings.sub-types.store') }}" style="padding:20px 22px;">
                    @csrf
                    <div style="margin-bottom:12px;"><label class="settings-label">Category *</label>
                        <select name="category_id" required class="settings-input" style="background:#fff;">
                            <option value="">— Select —</option>
                            @foreach(\App\Models\Inventory\InventoryCategory::orderBy('name')->get() as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="margin-bottom:18px;"><label class="settings-label">Sub-type Name *</label><input type="text" name="name" required class="settings-input" placeholder="e.g. Composite, GIC"></div>
                    <div style="display:flex;gap:8px;">
                        <button type="button" onclick="document.getElementById('inv-modal-add-subtype').style.display='none'" style="flex:1;padding:9px;border:1px solid #c0d8c8;border-radius:5px;font-size:13px;background:#fff;color:#1a7a45;cursor:pointer;">Cancel</button>
                        <button type="submit" style="flex:2;padding:9px;background:#1a7a45;border:none;border-radius:5px;font-size:13px;color:#fff;cursor:pointer;">Add Sub-type</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Edit Sub-type --}}
        <div id="inv-modal-edit-subtype" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1000;align-items:center;justify-content:center;">
            <div style="background:#fff;border-radius:8px;width:400px;max-width:94vw;box-shadow:0 16px 48px rgba(0,0,0,0.18);">
                <div style="padding:16px 22px;border-bottom:1px solid #e8f7ee;background:#f5fbf7;display:flex;justify-content:space-between;align-items:center;">
                    <h3 style="font-size:16px;font-weight:600;color:#1a7a45;margin:0;">Edit Sub-type</h3>
                    <button onclick="document.getElementById('inv-modal-edit-subtype').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:22px;color:#5a9a6a;">&times;</button>
                </div>
                <form id="inv-form-edit-subtype" method="POST" action="" style="padding:20px 22px;">
                    @csrf @method('PUT')
                    <input type="hidden" name="category_id" id="inv-est-cat">
                    <div style="margin-bottom:12px;"><label class="settings-label">Sub-type Name *</label><input type="text" name="name" id="inv-est-name" required class="settings-input"></div>
                    <div style="margin-bottom:18px;display:flex;align-items:center;gap:8px;"><input type="checkbox" name="is_active" id="inv-est-active" value="1" style="width:14px;height:14px;accent-color:#1a7a45;"><label for="inv-est-active" style="font-size:13px;cursor:pointer;">Active</label></div>
                    <div style="display:flex;gap:8px;">
                        <button type="button" onclick="document.getElementById('inv-modal-edit-subtype').style.display='none'" style="flex:1;padding:9px;border:1px solid #c0d8c8;border-radius:5px;font-size:13px;background:#fff;color:#1a7a45;cursor:pointer;">Cancel</button>
                        <button type="submit" style="flex:2;padding:9px;background:#1a7a45;border:none;border-radius:5px;font-size:13px;color:#fff;cursor:pointer;">Update</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
        function invOpenEditCategory(cat) {
            document.getElementById('inv-edit-cat-name').value  = cat.name || '';
            document.getElementById('inv-edit-cat-color').value = cat.color || '#6a0f70';
            document.getElementById('inv-edit-cat-desc').value  = cat.description || '';
            document.getElementById('inv-edit-cat-active').checked = !!cat.is_active;
            document.getElementById('inv-form-edit-category').action = '/inventory/settings/categories/' + cat.id;
            document.getElementById('inv-modal-edit-category').style.display = 'flex';
        }
        function invOpenEditLocation(loc) {
            document.getElementById('inv-edit-loc-name').value   = loc.name || '';
            document.getElementById('inv-edit-loc-code').value   = loc.code || '';
            document.getElementById('inv-edit-loc-desc').value   = loc.description || '';
            document.getElementById('inv-edit-loc-active').checked = !!loc.is_active;
            const sel = document.getElementById('inv-edit-loc-type');
            for (let o of sel.options) o.selected = (o.value === loc.type);
            document.getElementById('inv-form-edit-location').action = '/inventory/settings/locations/' + loc.id;
            document.getElementById('inv-modal-edit-location').style.display = 'flex';
        }
        function invOpenEditSubType(id, name, catId, isActive) {
            document.getElementById('inv-est-name').value    = name;
            document.getElementById('inv-est-active').checked = isActive;
            document.getElementById('inv-est-cat').value     = catId;
            document.getElementById('inv-form-edit-subtype').action = `/inventory/settings/sub-types/${id}`;
            document.getElementById('inv-modal-edit-subtype').style.display = 'flex';
        }

        // ── Quick client-side filters (Categories / Sub-types) ──
        function invFilterCategories() {
            const q = document.getElementById('inv-cat-filter').value.trim().toLowerCase();
            document.querySelectorAll('#inv-cat-list [data-name]').forEach(row => {
                row.style.display = (!q || row.dataset.name.includes(q)) ? '' : 'none';
            });
        }
        function invFilterSubTypes() {
            const q = document.getElementById('inv-sub-filter').value.trim().toLowerCase();
            document.querySelectorAll('#inv-sub-list details').forEach(det => {
                const catMatch = det.dataset.name.includes(q);
                const tags = det.querySelectorAll('[data-tag]');
                let anyTagMatch = false;
                tags.forEach(t => {
                    const show = !q || t.dataset.tag.includes(q);
                    t.style.display = show ? '' : 'none';
                    if (show && q) anyTagMatch = true;
                });
                const show = !q || catMatch || anyTagMatch;
                det.style.display = show ? '' : 'none';
                if (q) { det.open = show; }
                else { det.open = false; tags.forEach(t => t.style.display = ''); }
            });
        }

        ['inv-modal-add-category','inv-modal-edit-category','inv-modal-add-location',
         'inv-modal-edit-location','inv-modal-add-subtype','inv-modal-edit-subtype'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.addEventListener('click', e => { if (e.target === el) el.style.display = 'none'; });
        });
        </script>

    </div>

    {{-- ════════════════════════════════════════════
         TAB · HUDDLE SETTINGS
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='huddle'" x-cloak>
        <div style="max-width:600px;margin:0 auto;">
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:20px;">
                <h3 class="settings-section-title">Huddle Board Configuration</h3>
                <p style="font-size:12.5px;color:#9a7aaa;margin:0 0 20px;">These settings control how the daily huddle board behaves for your clinic.</p>

                <form id="huddle-settings-form">
                    @csrf
                    @if($huddleSettings->isNotEmpty())
                        @foreach($huddleSettings as $key => $value)
                        <div style="margin-bottom:16px;">
                            <label class="settings-label">{{ ucwords(str_replace('_', ' ', $key)) }}</label>
                            @if(is_array($value) || is_object($value))
                                <textarea name="settings[{{ $key }}]" rows="3" class="settings-input" style="resize:vertical;">{{ json_encode($value, JSON_PRETTY_PRINT) }}</textarea>
                            @elseif(in_array(strtolower((string)$value), ['true','false','0','1']))
                                <select name="settings[{{ $key }}]" class="settings-input" style="background:#fff;">
                                    <option value="true" {{ in_array($value, ['true','1']) ? 'selected' : '' }}>Enabled</option>
                                    <option value="false" {{ in_array($value, ['false','0']) ? 'selected' : '' }}>Disabled</option>
                                </select>
                            @else
                                <input type="text" name="settings[{{ $key }}]" value="{{ $value }}" class="settings-input">
                            @endif
                        </div>
                        @endforeach
                    @else
                        <div style="padding:20px;background:#f9f5fc;border-radius:8px;text-align:center;color:#9a7aaa;font-size:13px;margin-bottom:20px;">
                            No huddle settings configured yet. Default settings are in use.
                        </div>
                    @endif

                    <button type="button" onclick="saveHuddleSettings()" class="settings-save-btn">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        Save Huddle Settings
                    </button>
                </form>
            </div>
        </div>

        <script>
        function saveHuddleSettings() {
            const form = document.getElementById('huddle-settings-form');
            const formData = new FormData(form);
            const settings = {};
            for (const [k, v] of formData.entries()) {
                const m = k.match(/^settings\[(.+)\]$/);
                if (m) settings[m[1]] = v;
            }
            fetch('/huddle/settings', {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('[name=_token]').value },
                body: JSON.stringify({ settings })
            }).then(r => r.json()).then(data => {
                alert(data.message || 'Saved!');
            }).catch(() => alert('Error saving settings.'));
        }
        </script>
    </div>

    {{-- ════════════════════════════════════════════
         TAB · PRE (RELATIONSHIP ENGINE) — feature flags
         Read-only values from FeatureFlagService::all(); each switch calls
         POST /settings/feature-flags/toggle (whitelist-checked server-side).
         Purely a Settings-module addition — no engine files touched.

         NOTE: PRE (Relationship Engine)-specific flags moved to their own
         module-scoped page at /relationship/settings (2026-07-03), so PRE
         can be sold/configured standalone. Only genuinely cross-app flags
         (Communication Guard, Integrations, Workflow Engine, Search) stay here.
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='cross-app-flags'" x-cloak x-data="preFlagsPanel()">
        <style>
            /* Static layout only — dynamic colour/position comes from x-bind:style,
               which REPLACES a plain-string style attribute rather than merging it.
               Keeping the fixed geometry in a class avoids that Alpine gotcha. */
            .pre-switch {
                flex-shrink: 0; width: 44px; height: 24px; border-radius: 999px;
                border: none; position: relative; cursor: pointer; transition: background .15s;
            }
            .pre-switch-knob {
                position: absolute; top: 2px; width: 20px; height: 20px;
                border-radius: 50%; background: #fff; transition: transform .15s;
            }
        </style>
        <div style="max-width:760px;margin:0 auto;">

            <div style="background:#fff5e6;border:1px solid #f4d9a8;border-radius:12px;padding:16px 20px;margin-bottom:20px;font-size:12.5px;color:#8a5a00;">
                These switches control app-wide behaviour shared across every module (Communication Guard, Integrations,
                Workflow Engine, Search). Most are safe to leave as-is — only change one if you understand what it does.
                Every change here is logged. (Looking for PRE/Relationship Engine flags? Those moved to
                Relationships → Settings.)
            </div>

            @foreach($flagGroups as $groupLabel => $keys)
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:20px 24px;margin-bottom:16px;">
                <h3 class="settings-section-title">{{ $groupLabel }}</h3>

                @foreach($keys as $flagKey)
                @php $flag = $featureFlags[$flagKey] ?? null; @endphp
                @continue(!$flag)
                <div style="padding:12px 0;border-top:1px solid #f3ecf7;"
                     x-init="state['{{ $flagKey }}'] = {{ $flag['resolved'] ? 'true' : 'false' }}">
                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;">
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:600;color:#1a0a24;font-family:monospace;">{{ $flagKey }}</div>
                            <div style="font-size:12.5px;color:#7a6884;margin-top:2px;">{{ $flag['description'] }}</div>
                        </div>
                        <button type="button" class="pre-switch"
                                @click="arm('{{ $flagKey }}', !state['{{ $flagKey }}'])"
                                :disabled="busy === '{{ $flagKey }}'"
                                x-bind:style="'background:' + (state['{{ $flagKey }}'] ? '#1a7a45' : '#c9c3ce')">
                            <span class="pre-switch-knob"
                                  x-bind:style="'transform:translateX(' + (state['{{ $flagKey }}'] ? '20px' : '2px') + ')'"></span>
                        </button>
                    </div>
                    {{-- Inline confirm bar — replaces window.confirm(), which was found to
                         freeze the page (a blocking native dialog is bad UX either way). --}}
                    <div x-show="pendingKey === '{{ $flagKey }}'" x-cloak x-transition
                         style="margin-top:8px;display:flex;align-items:center;gap:10px;background:#f9f5fc;border:1px solid #e8d5f0;border-radius:8px;padding:8px 12px;">
                        <span style="font-size:12.5px;color:#4a3a54;" x-text="'Turn ' + (pendingNext ? 'ON' : 'OFF') + ' ' + pendingKey + '?'"></span>
                        <button type="button" @click="confirmToggle()" :disabled="busy === '{{ $flagKey }}'"
                                style="margin-left:auto;padding:4px 12px;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
                            <span x-show="busy !== '{{ $flagKey }}'">Yes, confirm</span>
                            <span x-show="busy === '{{ $flagKey }}'">Working…</span>
                        </button>
                        <button type="button" @click="cancelToggle()" :disabled="busy === '{{ $flagKey }}'"
                                style="padding:4px 12px;background:#fff;color:#6b7280;border:1px solid #d1d5db;border-radius:6px;font-size:12px;cursor:pointer;">
                            Cancel
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
            @endforeach

        </div>

        <script>
        function preFlagsPanel() {
            return {
                state: {},
                busy: null,
                pendingKey: null,
                pendingNext: null,
                arm(key, next) {
                    if (this.busy) return;
                    this.pendingKey = key;
                    this.pendingNext = next;
                },
                cancelToggle() {
                    this.pendingKey = null;
                    this.pendingNext = null;
                },
                confirmToggle() {
                    const key = this.pendingKey, next = this.pendingNext;
                    if (!key) return;
                    this.busy = key;
                    fetch('{{ route('settings.feature-flags.toggle') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('[name=_token]').value,
                        },
                        body: JSON.stringify({ key: key, enabled: next }),
                    })
                        .then(r => r.json())
                        .then(data => {
                            if (data.ok) {
                                this.state[key] = data.enabled;
                            } else {
                                alert(data.message || 'Could not update that flag.');
                            }
                        })
                        .catch(() => alert('Network error — flag not changed.'))
                        .finally(() => {
                            this.busy = null;
                            this.pendingKey = null;
                            this.pendingNext = null;
                        });
                },
            };
        }
        </script>
    </div>

    {{-- ════════════════════════════════════════════
         TAB · DATA (Import / Export)
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='data'" x-cloak>
        <div style="max-width:680px;margin:0 auto;">

            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:20px;">
                <h3 class="settings-section-title">Import Patients</h3>
                <p style="font-size:13px;color:#6b7280;margin-bottom:18px;line-height:1.6;">
                    Bulk-import patient records from another app or a spreadsheet.
                    Upload an <strong>Excel (.xlsx)</strong> or <strong>CSV</strong> file — columns are auto-mapped.
                    You'll see a preview before anything is saved.
                </p>

                @if(session('import_success'))
                <div style="margin-bottom:16px;padding:11px 16px;background:#e8f7ef;border:1px solid #b8e8cc;border-radius:8px;color:#1a7a45;font-size:13px;">
                    {{ session('import_success') }}
                </div>
                @endif

                @if($errors->has('file'))
                <div style="margin-bottom:16px;padding:11px 16px;background:#fef2f2;border:1px solid #fecaca;border-radius:8px;color:#b91c1c;font-size:13px;">
                    {{ $errors->first('file') }}
                </div>
                @endif

                <form action="{{ route('settings.data.import.preview') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
                        <div>
                            <label class="settings-label">File Format</label>
                            <select name="source" class="settings-input">
                                <option value="generic">Generic / Other App</option>
                                <option value="clinicia">Clinicia-style columns</option>
                                <option value="bestosys">Bestosys-style columns</option>
                            </select>
                            <p style="font-size:11px;color:#9ca3af;margin-top:4px;">Choose whichever matches your file's column names.</p>
                        </div>
                        <div>
                            <label class="settings-label">Upload File</label>
                            <input type="file" name="file" accept=".xlsx,.xls,.csv" class="settings-input" style="padding:6px 10px;cursor:pointer;">
                            <p style="font-size:11px;color:#9ca3af;margin-top:4px;">.xlsx, .xls or .csv — max 5 MB</p>
                        </div>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <button type="submit" class="settings-save-btn">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
                            Preview Import
                        </button>
                        <div style="display:flex;gap:12px;font-size:12px;">
                            <a href="{{ route('settings.data.import.template', 'generic') }}" style="color:#6a0f70;text-decoration:underline;">Generic template</a>
                            <a href="{{ route('settings.data.import.template', 'clinicia') }}" style="color:#6a0f70;text-decoration:underline;">Clinicia template</a>
                            <a href="{{ route('settings.data.import.template', 'bestosys') }}" style="color:#6a0f70;text-decoration:underline;">Bestosys template</a>
                        </div>
                    </div>
                </form>
            </div>

            @if(auth()->user()->isAdminRole())
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;">
                <h3 class="settings-section-title">Export Patients</h3>
                <p style="font-size:13px;color:#6b7280;margin-bottom:18px;line-height:1.6;">
                    Download a CSV of all patient records from this branch.
                    The file includes name, contact, address, source, membership status, and last visit date.
                </p>
                <div style="display:flex;align-items:center;gap:16px;">
                    <a href="{{ route('settings.data.export') }}" class="settings-save-btn" style="text-decoration:none;">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="8 17 12 21 16 17"/><line x1="12" y1="3" x2="12" y2="15"/><path d="M20.88 18.09A5 5 0 0 0 18 9h-1.26A8 8 0 1 0 3 16.29"/></svg>
                        Export All Patients (.csv)
                    </a>
                    <span style="font-size:12px;color:#9ca3af;">Only visible to admin</span>
                </div>
            </div>
            @else
            <div style="background:#fafafa;border:1.5px dashed #e0d4ea;border-radius:12px;padding:24px;text-align:center;">
                <p style="font-size:13px;color:#9ca3af;">Patient export is restricted to admin users.</p>
            </div>
            @endif

        </div>
    </div>

    {{-- ════════════════════════════════════════════
         TAB · PERSONALISATION
    ═══════════════════════════════════════════════ --}}
    <div x-show="activeTab==='personalisation'" x-cloak>

        <p class="settings-section-title">App Personalisation</p>
        <p style="font-size:13px;color:#7a6884;margin:-8px 0 24px;">Select your preferences below and click <strong>Apply Preferences</strong> to save.</p>

        {{-- ── Theme ── --}}
        <div style="margin-bottom:32px;">
            <p class="settings-section-title" style="margin-bottom:10px;">Theme</p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;" id="df-theme-grid">
                @foreach([
                    ['key'=>'light',  'label'=>'Light',          'icon'=>'<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>'],
                    ['key'=>'dark',   'label'=>'Dark',           'icon'=>'<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>'],
                    ['key'=>'system', 'label'=>'System default', 'icon'=>'<rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/>'],
                ] as $t)
                <button onclick="dfPrefs.select('theme','{{ $t['key'] }}')"
                        data-theme-btn="{{ $t['key'] }}"
                        style="display:flex;align-items:center;gap:10px;padding:10px 18px;border:2px solid #e0d4ea;border-radius:10px;background:#fff;cursor:pointer;transition:border-color 150ms,background 150ms;"
                        onmouseover="if(!this.classList.contains('pref-active'))this.style.borderColor='#b08ec0';"
                        onmouseout="if(!this.classList.contains('pref-active'))this.style.borderColor='#e0d4ea';">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">{!! $t['icon'] !!}</svg>
                    <span style="font-size:13px;color:#2a1440;">{{ $t['label'] }}</span>
                </button>
                @endforeach
            </div>
        </div>


        {{-- ── Font ── --}}
        <div style="margin-bottom:32px;">
            <p class="settings-section-title" style="margin-bottom:4px;">Font</p>
            <p style="font-size:12px;color:#9a8aaa;margin-bottom:12px;">Sets the typeface across the entire app.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;" id="df-font-grid">
                @php $fonts = [
                    ['key'=>'dm-sans', 'label'=>'Inter',  'sub'=>'Default'],
                    ['key'=>'inter',   'label'=>'Inter',    'sub'=>'Clean'],
                    ['key'=>'nunito',  'label'=>'Nunito',   'sub'=>'Rounded'],
                    ['key'=>'roboto',  'label'=>'Roboto',   'sub'=>'Classic'],
                    ['key'=>'poppins', 'label'=>'Poppins',  'sub'=>'Modern'],
                ]; @endphp
                @foreach($fonts as $f)
                <button onclick="dfPrefs.select('font','{{ $f['key'] }}')"
                        data-font-btn="{{ $f['key'] }}"
                        style="display:flex;flex-direction:column;align-items:flex-start;padding:10px 16px;border:2px solid #e0d4ea;border-radius:10px;background:#fff;cursor:pointer;transition:border-color 150ms;min-width:110px;"
                        onmouseover="if(!this.classList.contains('pref-active'))this.style.borderColor='#b08ec0';"
                        onmouseout="if(!this.classList.contains('pref-active'))this.style.borderColor='#e0d4ea';">
                    <span style="font-size:15px;font-weight:600;color:#2a1440;">Aa</span>
                    <span style="font-size:12px;color:#2a1440;margin-top:2px;">{{ $f['label'] }}</span>
                    <span style="font-size:11px;color:#9a8aaa;">{{ $f['sub'] }}</span>
                </button>
                @endforeach
            </div>
        </div>

        {{-- ── Colour Scheme ── --}}
        <div style="margin-bottom:32px;">
            <p class="settings-section-title" style="margin-bottom:4px;">Colour Scheme</p>
            <p style="font-size:12px;color:#9a8aaa;margin-bottom:12px;">Changes buttons, active states, and accent colours throughout the app.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;" id="df-color-grid">
                @php $schemes = [
                    ['key'=>'default', 'label'=>'Default', 'hex'=>'#6a0f70'],
                    ['key'=>'blue',    'label'=>'Blue',    'hex'=>'#1558b0'],
                    ['key'=>'teal',    'label'=>'Teal',    'hex'=>'#0d7a6a'],
                    ['key'=>'green',   'label'=>'Green',   'hex'=>'#1a7a45'],
                    ['key'=>'rose',    'label'=>'Rose',    'hex'=>'#b52058'],
                ]; @endphp
                @foreach($schemes as $c)
                <button onclick="dfPrefs.select('color','{{ $c['key'] }}')"
                        data-color-btn="{{ $c['key'] }}"
                        style="display:flex;align-items:center;gap:10px;padding:10px 16px;border:2px solid #e0d4ea;border-radius:10px;background:#fff;cursor:pointer;transition:border-color 150ms;"
                        onmouseover="if(!this.classList.contains('pref-active'))this.style.borderColor='#b08ec0';"
                        onmouseout="if(!this.classList.contains('pref-active'))this.style.borderColor='#e0d4ea';">
                    <span style="width:20px;height:20px;border-radius:50%;background:{{ $c['hex'] }};display:inline-block;flex-shrink:0;"></span>
                    <span style="font-size:13px;color:#2a1440;">{{ $c['label'] }}</span>
                </button>
                @endforeach
            </div>
        </div>

        {{-- ── Currency ── --}}
        <div style="margin-bottom:32px;">
            <p class="settings-section-title" style="margin-bottom:4px;">Currency</p>
            <p style="font-size:12px;color:#9a8aaa;margin-bottom:12px;">Symbol shown on invoices, billing prompts, and finance cards.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;" id="df-currency-grid">
                @php $currencies = [
                    ['code'=>'INR', 'symbol'=>'Rs. ',   'label'=>'Indian Rupee'],
                    ['code'=>'USD', 'symbol'=>'$',   'label'=>'US Dollar'],
                    ['code'=>'EUR', 'symbol'=>'€',   'label'=>'Euro'],
                    ['code'=>'GBP', 'symbol'=>'£',   'label'=>'British Pound'],
                    ['code'=>'AED', 'symbol'=>'د.إ', 'label'=>'UAE Dirham'],
                    ['code'=>'SGD', 'symbol'=>'S$',  'label'=>'Singapore Dollar'],
                ]; @endphp
                @foreach($currencies as $cur)
                <button onclick="dfPrefs.select('currency','{{ $cur['code'] }}','{{ $cur['symbol'] }}')"
                        data-currency-btn="{{ $cur['code'] }}"
                        style="display:flex;align-items:center;gap:10px;padding:10px 16px;border:2px solid #e0d4ea;border-radius:10px;background:#fff;cursor:pointer;transition:border-color 150ms;"
                        onmouseover="if(!this.classList.contains('pref-active'))this.style.borderColor='#b08ec0';"
                        onmouseout="if(!this.classList.contains('pref-active'))this.style.borderColor='#e0d4ea';">
                    <span style="font-size:17px;font-weight:600;color:#6a0f70;width:26px;text-align:center;flex-shrink:0;">{{ $cur['symbol'] }}</span>
                    <div>
                        <span style="font-size:13px;color:#2a1440;display:block;">{{ $cur['code'] }}</span>
                        <span style="font-size:11px;color:#9a8aaa;">{{ $cur['label'] }}</span>
                    </div>
                </button>
                @endforeach
            </div>
        </div>

        {{-- ── Apply + Reset ── --}}
        <div style="padding-top:20px;border-top:1px solid #ede4f3;display:flex;align-items:center;gap:16px;">
            <button onclick="dfPrefs.applySelected()"
                    id="df-apply-btn"
                    style="padding:10px 28px;background:#6a0f70;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;transition:background 150ms;"
                    onmouseover="this.style.background='#530b5a';"
                    onmouseout="this.style.background='#6a0f70';">
                Apply Preferences
            </button>
            <button onclick="dfPrefs.reset()"
                    style="font-size:12px;color:#9a8aaa;background:none;border:none;cursor:pointer;text-decoration:underline;text-underline-offset:3px;">
                Reset to defaults
            </button>
        </div>

        <script>
        window.dfPrefs = (function () {
            var KEY = 'df_prefs';
            var FONTS = {
                'dm-sans':"'Inter',sans-serif",
                'inter':  "'Inter',sans-serif",
                'nunito': "'Nunito',sans-serif",
                'roboto': "'Roboto',sans-serif",
                'poppins':"'Poppins',sans-serif",
            };
            var COLORS = {
                'default':{ primary:'#6a0f70',hover:'#3a0050',light:'#f9f3fa',border:'rgba(185,92,183,0.18)', sBase:'#200a2e',sBase2:'#0e0118',sGlow1:'rgba(140,30,170,0.40)',sGlow2:'rgba(90,10,120,0.32)' },
                'blue':   { primary:'#1558b0',hover:'#0d3d80',light:'#f0f5ff',border:'rgba(21,88,176,0.18)',  sBase:'#08142a',sBase2:'#040c1a',sGlow1:'rgba(21,88,176,0.45)', sGlow2:'rgba(13,61,128,0.35)' },
                'teal':   { primary:'#0d7a6a',hover:'#095a4e',light:'#f0faf8',border:'rgba(13,122,106,0.18)',sBase:'#041e1a',sBase2:'#020e0c',sGlow1:'rgba(13,122,106,0.45)',sGlow2:'rgba(9,90,78,0.35)'   },
                'green':  { primary:'#1a7a45',hover:'#0f5030',light:'#f0faf4',border:'rgba(26,122,69,0.18)', sBase:'#061a0e',sBase2:'#020e06',sGlow1:'rgba(26,122,69,0.45)', sGlow2:'rgba(15,80,48,0.35)'  },
                'rose':   { primary:'#b52058',hover:'#821040',light:'#fff0f5',border:'rgba(181,32,88,0.18)', sBase:'#200814',sBase2:'#0e0208',sGlow1:'rgba(181,32,88,0.45)', sGlow2:'rgba(130,16,64,0.35)' },
            };
            function load(){ try{return JSON.parse(localStorage.getItem(KEY)||'{}');}catch(e){return{};} }
            function save(p){ localStorage.setItem(KEY,JSON.stringify(p)); }
            function mark(attr,val){
                document.querySelectorAll('['+attr+']').forEach(function(b){
                    var on=b.getAttribute(attr)===val;
                    b.classList.toggle('pref-active',on);
                    b.style.borderColor=on?'var(--df-color-primary,#6a0f70)':'#e0d4ea';
                    b.style.background =on?'var(--df-color-light,#f9f3fa)':'#fff';
                });
            }
            function applyFont(key){
                var v=FONTS[key]||FONTS['dm-sans'];
                document.documentElement.style.setProperty('--df-font-body',v);
                document.body.style.fontFamily=v;
                mark('data-font-btn',key);
                var g={inter:'Inter:wght@400;500;600;700',nunito:'Nunito:wght@400;500;600;700',roboto:'Roboto:wght@400;500;700',poppins:'Poppins:wght@400;500;600;700'};
                if(g[key]&&!document.querySelector('link[data-df-font="'+key+'"]')){
                    var l=document.createElement('link');l.rel='stylesheet';l.dataset.dfFont=key;
                    l.href='https://fonts.googleapis.com/css2?family='+g[key]+'&display=swap';
                    document.head.appendChild(l);
                }
            }
            function applyColor(key){
                var s=COLORS[key]||COLORS['default'];
                document.documentElement.style.setProperty('--df-color-primary',  s.primary);
                document.documentElement.style.setProperty('--df-color-hover',    s.hover);
                document.documentElement.style.setProperty('--df-color-light',    s.light);
                document.documentElement.style.setProperty('--df-color-border',   s.border);
                document.documentElement.style.setProperty('--df-sidebar-base',   s.sBase);
                document.documentElement.style.setProperty('--df-sidebar-base-2', s.sBase2);
                document.documentElement.style.setProperty('--df-sidebar-glow-1', s.sGlow1);
                document.documentElement.style.setProperty('--df-sidebar-glow-2', s.sGlow2);
                mark('data-color-btn',key);
            }
            function applyTheme(key){
                var dark=(key==='dark')||(key==='system'&&window.matchMedia('(prefers-color-scheme:dark)').matches);
                document.documentElement.setAttribute('data-theme',dark?'dark':'light');
                mark('data-theme-btn',key);
            }
            /* pending: tracks UI selections before Apply is clicked */
            var pending = {};
            return {
                init:function(){
                    var p=load();
                    applyFont(p.font||'dm-sans');
                    applyColor(p.color||'default');
                    applyTheme(p.theme||'system');
                    mark('data-currency-btn',(p.currency||{}).code||'INR');
                },
                /* called by card clicks — visual selection only, no save yet */
                select:function(type,key,extra){
                    pending[type]={key:key,extra:extra};
                    if(type==='font')     mark('data-font-btn',key);
                    else if(type==='color')    mark('data-color-btn',key);
                    else if(type==='theme')    mark('data-theme-btn',key);
                    else if(type==='currency') mark('data-currency-btn',key);
                },
                /* called by Apply Preferences button */
                applySelected:function(){
                    var p=load(); var changed=false;
                    if(pending.font)     { p.font=pending.font.key; applyFont(p.font); changed=true; }
                    if(pending.color)    { p.color=pending.color.key; applyColor(p.color); changed=true; }
                    if(pending.theme)    { p.theme=pending.theme.key; applyTheme(p.theme); changed=true; }
                    if(pending.currency) { p.currency={code:pending.currency.key,symbol:pending.currency.extra}; window.__DF_CURRENCY=p.currency; changed=true; }
                    if(changed){ save(p); pending={}; dfPrefs._toast('✓ Preferences saved!',true); }
                    else { dfPrefs._toast('No changes to apply'); }
                },
                /* still used by reset */
                setFont:function(k){ var p=load();p.font=k;save(p);applyFont(k); },
                setColor:function(k){ var p=load();p.color=k;save(p);applyColor(k); },
                setTheme:function(k){ var p=load();p.theme=k;save(p);applyTheme(k); },
                setCurrency:function(code,sym){ var p=load();p.currency={code:code,symbol:sym};save(p);window.__DF_CURRENCY={code:code,symbol:sym};mark('data-currency-btn',code); },
                reset:function(){
                    localStorage.removeItem(KEY);
                    pending={};
                    window.__DF_CURRENCY={symbol:'Rs. ',code:'INR'};
                    applyFont('dm-sans'); applyColor('default'); applyTheme('system'); mark('data-currency-btn','INR');
                    dfPrefs._toast('Reset to defaults');
                },
                _toast:function(msg,success){
                    var t=document.createElement('div');
                    t.textContent=msg;
                    var bg=success?'#1a6a40':'#1a0a24';
                    t.style.cssText='position:fixed;bottom:28px;right:28px;z-index:9999;background:'+bg+';color:#fff;font-size:14px;font-weight:500;padding:12px 22px;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,.3);transition:opacity 400ms;';
                    document.body.appendChild(t);
                    setTimeout(function(){ t.style.opacity='0'; setTimeout(function(){ t.remove(); },400); }, 2500);
                },
            };
        })();
        document.addEventListener('DOMContentLoaded',function(){ dfPrefs.init(); });
        </script>

    </div>{{-- /TAB PERSONALISATION --}}

    {{-- ════════════════════════════════════════════
         TAB · CALENDAR PREFERENCES
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='calendar'" x-cloak x-data="calendarPrefsPanel()">
        <div style="max-width:700px;margin:0 auto;">

            <form action="{{ route('settings.calendar.save') }}" method="POST" id="calendar-prefs-form">
                @csrf

                {{-- ── Card Style ── --}}
                <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:18px;">
                    <p class="settings-section-title">Appointment Card Style</p>
                    <p style="font-size:12.5px;color:#7a6080;margin:0 0 18px;">Choose how appointments appear on the calendar. Strip keeps it minimal; Filled gives more visual richness.</p>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:20px;">
                        {{-- Strip option --}}
                        <label :class="cardStyle==='strip' ? 'cal-style-card cal-style-card--active' : 'cal-style-card'" style="cursor:pointer;">
                            <input type="radio" name="card_style" value="strip" x-model="cardStyle" style="display:none;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                                <div :class="cardStyle==='strip' ? 'cal-radio-dot cal-radio-dot--active' : 'cal-radio-dot'"></div>
                                <span style="font-size:13px;font-weight:600;color:#2d1845;">Strip</span>
                                <span style="font-size:11px;color:#a090b0;margin-left:auto;">Minimal</span>
                            </div>
                            {{-- Strip preview --}}
                            <div style="background:#fff;border-radius:6px;padding:8px;border:1px solid #eee;">
                                <div style="background:#fff;border-left:3px solid #6a0f70;border-radius:3px;padding:5px 8px;font-size:11px;box-shadow:0 1px 3px rgba(0,0,0,.08);">
                                    <div style="font-weight:600;color:#1a0320;letter-spacing:.01em;">10:00 – John Doe</div>
                                    <div style="color:#6a0f70;margin-top:1px;">Root Canal</div>
                                </div>
                            </div>
                        </label>

                        {{-- Filled option --}}
                        <label :class="cardStyle==='filled' ? 'cal-style-card cal-style-card--active' : 'cal-style-card'" style="cursor:pointer;">
                            <input type="radio" name="card_style" value="filled" x-model="cardStyle" style="display:none;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                                <div :class="cardStyle==='filled' ? 'cal-radio-dot cal-radio-dot--active' : 'cal-radio-dot'"></div>
                                <span style="font-size:13px;font-weight:600;color:#2d1845;">Filled</span>
                                <span style="font-size:11px;color:#a090b0;margin-left:auto;">Rich</span>
                            </div>
                            {{-- Filled preview --}}
                            <div style="background:#fff;border-radius:6px;padding:8px;border:1px solid #eee;">
                                <div style="background:#f97316;background:rgba(249,115,22,.13);border-left:3px solid #6a0f70;border-radius:3px;padding:5px 8px;font-size:11px;">
                                    <div style="font-weight:600;color:#1a0320;letter-spacing:.01em;">10:00 – John Doe</div>
                                    <div style="color:#7a4010;margin-top:1px;">Root Canal</div>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>

                {{-- ── Color Source ── --}}
                <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:18px;">
                    <p class="settings-section-title">Color Source</p>
                    <p style="font-size:12.5px;color:#7a6080;margin:0 0 18px;">The card background tint comes from the treatment category. The left border accent comes from the assigned doctor. Choose which drives the <em>primary</em> color identity of each card.</p>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:6px;">
                        {{-- Treatment color --}}
                        <label :class="colorSource==='treatment' ? 'cal-style-card cal-style-card--active' : 'cal-style-card'" style="cursor:pointer;">
                            <input type="radio" name="color_source" value="treatment" x-model="colorSource" style="display:none;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                                <div :class="colorSource==='treatment' ? 'cal-radio-dot cal-radio-dot--active' : 'cal-radio-dot'"></div>
                                <span style="font-size:13px;font-weight:600;color:#2d1845;">Treatment Category</span>
                            </div>
                            <p style="font-size:12px;color:#7a6080;margin:0;">Background tint = Treatment color<br>Border accent = Doctor color</p>
                        </label>

                        {{-- Doctor color --}}
                        <label :class="colorSource==='doctor' ? 'cal-style-card cal-style-card--active' : 'cal-style-card'" style="cursor:pointer;">
                            <input type="radio" name="color_source" value="doctor" x-model="colorSource" style="display:none;">
                            <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                                <div :class="colorSource==='doctor' ? 'cal-radio-dot cal-radio-dot--active' : 'cal-radio-dot'"></div>
                                <span style="font-size:13px;font-weight:600;color:#2d1845;">Doctor</span>
                            </div>
                            <p style="font-size:12px;color:#7a6080;margin:0;">Background tint = Doctor color<br>Border accent = Treatment color</p>
                        </label>
                    </div>
                </div>

                {{-- ── Live Preview ── --}}
                <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:18px;">
                    <p class="settings-section-title">Live Preview</p>
                    <p style="font-size:12.5px;color:#7a6080;margin:0 0 16px;">This is how your appointments will appear on the calendar with the current settings.</p>

                    {{-- Simulated calendar slot --}}
                    <div style="background:#f8f4fc;border-radius:8px;padding:16px 14px;display:flex;flex-direction:column;gap:6px;">
                        <template x-for="(demo, idx) in demoCards" :key="idx">
                            <div :style="buildCardStyle(demo)" style="border-radius:4px;padding:6px 9px;font-size:12px;position:relative;overflow:hidden;">
                                {{-- Cancelled overlay --}}
                                <template x-if="demo.status==='cancelled'">
                                    <div style="position:absolute;inset:0;background:rgba(255,255,255,.55);display:flex;align-items:center;justify-content:center;border-radius:4px;">
                                        <span style="font-size:10px;font-weight:700;color:#dc2626;letter-spacing:.06em;text-transform:uppercase;">Cancelled</span>
                                    </div>
                                </template>
                                <div style="font-weight:600;color:#1a0320;letter-spacing:.01em;" :style="demo.status==='cancelled'?'opacity:.4':''">
                                    <span x-text="demo.time"></span> &ndash; <span x-text="demo.patient"></span>
                                </div>
                                <div style="margin-top:2px;font-size:11px;" :style="buildSubStyle(demo)" x-text="demo.treatment"></div>
                                <template x-if="demo.isWalkin">
                                    <span style="position:absolute;top:4px;right:6px;font-size:9px;font-weight:700;background:#f59e0b;color:#fff;border-radius:3px;padding:1px 5px;letter-spacing:.05em;">WALK-IN</span>
                                </template>
                                <template x-if="demo.status==='done'">
                                    <span style="position:absolute;top:4px;right:6px;font-size:9px;font-weight:700;background:#10b981;color:#fff;border-radius:3px;padding:1px 5px;letter-spacing:.05em;">DONE</span>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>

                <div style="display:flex;justify-content:flex-end;">
                    <button type="submit" class="settings-save-btn">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        Save Calendar Preferences
                    </button>
                </div>
            </form>

        </div>

        <style>
        .cal-style-card {
            border: 1.5px solid #e0d4ea;
            border-radius: 10px;
            padding: 14px;
            transition: border-color .15s, box-shadow .15s;
            display: block;
        }
        .cal-style-card:hover { border-color: #9b59b6; }
        .cal-style-card--active {
            border-color: #6a0f70;
            box-shadow: 0 0 0 2px rgba(106,15,112,.12);
            background: #fbf6ff;
        }
        .cal-radio-dot {
            width: 15px; height: 15px;
            border-radius: 50%;
            border: 2px solid #c5a8d8;
            flex-shrink: 0;
            position: relative;
        }
        .cal-radio-dot--active {
            border-color: #6a0f70;
            background: #6a0f70;
        }
        .cal-radio-dot--active::after {
            content: '';
            position: absolute;
            inset: 3px;
            background: #fff;
            border-radius: 50%;
        }
        </style>

        <script>
        function calendarPrefsPanel() {
            // Demo data for the live preview
            const demos = [
                { time:'09:00', patient:'Riya Sharma',   treatment:'Root Canal',     treatColor:'#f97316', doctorColor:'#6366f1', status:'scheduled', isWalkin:false },
                { time:'10:30', patient:'Arun Mehta',    treatment:'Scaling',        treatColor:'#14b8a6', doctorColor:'#f59e0b', status:'done',      isWalkin:false },
                { time:'11:15', patient:'Priya Patel',   treatment:'Orthodontics',   treatColor:'#8b5cf6', doctorColor:'#10b981', status:'cancelled', isWalkin:false },
                { time:'12:00', patient:'Walk-In',       treatment:'Consultation',   treatColor:'#3b82f6', doctorColor:'#ec4899', status:'scheduled', isWalkin:true  },
            ];

            return {
                cardStyle:   '{{ $calendarPrefs["calendar_card_style"]   ?? "strip" }}',
                colorSource: '{{ $calendarPrefs["calendar_color_source"] ?? "treatment" }}',
                demoCards: demos,

                buildCardStyle(demo) {
                    const primaryColor  = this.colorSource === 'treatment' ? demo.treatColor  : demo.doctorColor;
                    const accentColor   = this.colorSource === 'treatment' ? demo.doctorColor : demo.treatColor;
                    const isCancelled   = demo.status === 'cancelled';
                    const isDone        = demo.status === 'done';

                    let bg, border;
                    if (isCancelled) {
                        bg = '#fee2e2'; border = '#fca5a5';
                    } else if (isDone) {
                        bg = '#f0fdf4'; border = '#86efac';
                    } else if (this.cardStyle === 'filled') {
                        bg = primaryColor + '20';
                        border = accentColor;
                    } else {
                        // strip — white bg, accent left border
                        bg = '#ffffff';
                        border = accentColor;
                    }

                    return `background:${bg};border-left:3px solid ${border};box-shadow:0 1px 3px rgba(0,0,0,.07);position:relative;`;
                },

                buildSubStyle(demo) {
                    if (demo.status === 'cancelled') return 'color:#ef4444;opacity:.4;';
                    if (demo.status === 'done')      return 'color:#15803d;';
                    const primaryColor = this.colorSource === 'treatment' ? demo.treatColor : demo.doctorColor;
                    // darken for text: just reduce opacity on the raw color via CSS
                    return `color:${primaryColor};filter:brightness(.75);`;
                },
            };
        }
        </script>

    </div>{{-- /TAB CALENDAR --}}

</div>{{-- /content-area --}}
</div>{{-- /sidebar+content flex --}}
</div>{{-- /settingsApp --}}

@endsection

@push('scripts')
<script>
function settingsApp() {
    return {
        activeTab: '{{ $activeTab }}',
        staffSubTab: 'staff',
        letterheadPreview: null,
        printHeader: '{{ $print["print_header_type"] ?? "plain" }}',

        init() {
            if (this.activeTab === 'staff') { this.activeTab = 'staff-roles'; this.staffSubTab = 'staff'; }
            if (this.activeTab === 'roles') { this.activeTab = 'staff-roles'; this.staffSubTab = 'roles'; }
            const hash = window.location.hash.replace('#', '');
            if (hash) this.activeTab = hash;
        },

        logoPreview(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                const el = document.querySelector('.clinic-logo-preview img, .clinic-logo-preview div');
                if (el) el.outerHTML = `<img src="${e.target.result}" style="height:60px;border-radius:6px;object-fit:contain;">`;
            };
            reader.readAsDataURL(file);
        },
    };
}

// ── Staff: activate/deactivate — password gated ──────────────────────────────
let _toggleStaffId   = null;
let _toggleIsActive  = null; // current state before toggle

function askToggleStaff(e, userId, isActive, name) {
    e.preventDefault();
    _toggleStaffId  = userId;
    _toggleIsActive = isActive;

    const deactivating = isActive; // true = we are about to deactivate
    const icon  = document.getElementById('tcm-icon');
    const title = document.getElementById('tcm-title');
    const desc  = document.getElementById('tcm-desc');
    const btn   = document.getElementById('tcm-confirm-btn');

    if (deactivating) {
        icon.style.background  = '#fef2f2';
        icon.querySelector('svg').setAttribute('stroke', '#c0392b');
        title.textContent = 'Deactivate Staff Member';
        desc.textContent  = `${name} will lose all access immediately. Enter your password to confirm.`;
        btn.style.background = '#c0392b';
        btn.textContent      = 'Yes, Deactivate';
    } else {
        icon.style.background  = '#e8f7ef';
        icon.querySelector('svg').setAttribute('stroke', '#1a7a45');
        title.textContent = 'Activate Staff Member';
        desc.textContent  = `${name} will regain access to the system. Enter your password to confirm.`;
        btn.style.background = '#1a7a45';
        btn.textContent      = 'Yes, Activate';
    }

    document.getElementById('tcm-password').value = '';
    document.getElementById('tcm-error').style.display = 'none';
    document.getElementById('toggleConfirmModal').style.display = 'flex';
    setTimeout(() => document.getElementById('tcm-password').focus(), 100);
}

function closeToggleConfirm() {
    document.getElementById('toggleConfirmModal').style.display = 'none';
    _toggleStaffId  = null;
    _toggleIsActive = null;
}

async function confirmToggleStaff() {
    if (!_toggleStaffId) return;

    const password = document.getElementById('tcm-password').value.trim();
    const errEl    = document.getElementById('tcm-error');
    const btn      = document.getElementById('tcm-confirm-btn');

    if (!password) {
        errEl.textContent = 'Please enter your password.';
        errEl.style.display = 'block';
        return;
    }

    btn.textContent  = 'Verifying…';
    btn.disabled     = true;
    errEl.style.display = 'none';

    try {
        const res  = await fetch(`/settings/staff/${_toggleStaffId}/toggle`, {
            method:  'POST',
            headers: {
                'Content-Type':  'application/json',
                'X-CSRF-TOKEN':  document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify({ password }),
        });
        const data = await res.json();

        if (res.ok && data.ok) {
            // Update toggle UI
            const toggle = document.getElementById(`toggle-${_toggleStaffId}`);
            const label  = document.getElementById(`toggle-label-${_toggleStaffId}`);
            if (toggle) { data.is_active ? toggle.classList.add('on') : toggle.classList.remove('on'); }
            if (label)  { label.textContent = data.is_active ? 'Active' : 'Inactive'; }
            closeToggleConfirm();
            // Refresh audit log
            loadActivityLog();
        } else {
            errEl.textContent   = data.message || 'Incorrect password. Action not allowed.';
            errEl.style.display = 'block';
        }
    } catch (e) {
        errEl.textContent   = 'Network error. Please try again.';
        errEl.style.display = 'block';
    } finally {
        const deactivating   = _toggleIsActive;
        btn.textContent  = deactivating ? 'Yes, Deactivate' : 'Yes, Activate';
        btn.disabled     = false;
    }
}

// ── Activity Log ──────────────────────────────────────────────────────────────
async function loadActivityLog() {
    const loading = document.getElementById('activity-log-loading');
    const body    = document.getElementById('activity-log-body');
    const rows    = document.getElementById('activity-log-rows');
    const empty   = document.getElementById('activity-log-empty');

    loading.style.display = 'block';
    body.style.display    = 'none';

    try {
        const res  = await fetch('/settings/staff/activity-log', {
            headers: { 'Accept': 'application/json' },
        });
        const logs = await res.json();

        rows.innerHTML = '';
        if (!logs.length) {
            empty.style.display = 'block';
        } else {
            empty.style.display = 'none';
            logs.forEach(l => {
                let detail = '';
                if (l.old_value && l.new_value) detail = `${l.old_value} → ${l.new_value}`;
                else if (l.note) detail = l.note;

                rows.insertAdjacentHTML('beforeend', `
                    <div style="display:grid;grid-template-columns:1fr 1fr 130px 1fr 100px;gap:0;padding:11px 18px;border-bottom:1px solid #f5f0f8;align-items:center;font-size:12.5px;">
                        <div style="font-weight:500;color:#1a0320;">${escHtml(l.staff_name)}</div>
                        <div style="color:#7a6a85;">${escHtml(l.by_name)}</div>
                        <div>
                            <span style="padding:2px 9px;border-radius:99px;font-size:11px;font-weight:600;background:${l.action_color}18;color:${l.action_color};">
                                ${escHtml(l.action_label)}
                            </span>
                        </div>
                        <div style="color:#7a6a85;font-size:12px;">${escHtml(detail)}</div>
                        <div style="color:#b0a0bb;font-size:11.5px;" title="${escHtml(l.time)}">${escHtml(l.time_ago)}</div>
                    </div>
                `);
            });
        }

        loading.style.display = 'none';
        body.style.display    = 'block';
    } catch(e) {
        loading.textContent = 'Failed to load activity log.';
    }
}

function escHtml(str) {
    if (!str) return '—';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Auto-load the activity log when the Staff tab is visible
document.addEventListener('DOMContentLoaded', () => {
    // Small delay to let Alpine render first
    setTimeout(loadActivityLog, 600);
});

// ── Staff: full edit modal ────────────────────────────────────────────────────
let _editStaffUserId = null;

function openStaffEdit(userId, name, email, phone, designation, roleId, color) {
    _editStaffUserId = userId;
    document.getElementById('sedit_name').value        = name;
    document.getElementById('sedit_email').value       = email;
    document.getElementById('sedit_phone').value       = phone;
    document.getElementById('sedit_designation').value = designation;
    document.getElementById('sedit_color').value       = color || '#3b82f6';
    document.getElementById('sedit_role_id').value     = roleId ?? '';
    document.getElementById('sedit_error').style.display = 'none';
    document.getElementById('editStaffModal').style.display = 'flex';
}

function closeStaffEdit() {
    document.getElementById('editStaffModal').style.display = 'none';
    _editStaffUserId = null;
}

async function saveStaffEdit() {
    if (!_editStaffUserId) return;
    const btn = document.getElementById('saveStaffBtn');
    const errEl = document.getElementById('sedit_error');
    errEl.style.display = 'none';

    const payload = {
        name:        document.getElementById('sedit_name').value.trim(),
        email:       document.getElementById('sedit_email').value.trim(),
        phone:       document.getElementById('sedit_phone').value.trim(),
        designation: document.getElementById('sedit_designation').value.trim(),
        color:       document.getElementById('sedit_color').value || null,
        role_id:     document.getElementById('sedit_role_id').value || null,
    };

    if (!payload.name || !payload.email) {
        errEl.textContent = 'Name and Email are required.';
        errEl.style.display = 'block';
        return;
    }

    btn.textContent = 'Saving…';
    btn.disabled = true;

    try {
        const res = await fetch(`/settings/staff/${_editStaffUserId}/update`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();

        if (res.ok && data.ok) {
            // Update the row in the DOM
            const row = document.querySelector(`[data-staff-id="${_editStaffUserId}"]`);
            if (row) {
                row.querySelector('.staff-name').textContent  = data.user.name;
                row.querySelector('.staff-email').textContent = data.user.email;
                const phoneEl = row.querySelector('.staff-phone');
                if (phoneEl) phoneEl.textContent = data.user.phone ? ' · ' + data.user.phone : '';
                const desigEl = row.querySelector('.staff-desig');
                if (desigEl) desigEl.textContent = data.user.designation ? ' · ' + data.user.designation : '';
                const badge = document.getElementById('role-badge-' + _editStaffUserId);
                if (badge) {
                    badge.textContent = data.user.role_name || 'No role';
                    badge.style.color = data.user.role_color || '#6a0f70';
                    badge.style.background = data.user.role_color ? data.user.role_color + '22' : '#f3eef7';
                }
            } else {
                // fallback: reload
                window.location.reload();
            }
            closeStaffEdit();
        } else {
            const msg = data.errors
                ? Object.values(data.errors).flat().join(' ')
                : (data.message || 'Failed to save. Please try again.');
            errEl.textContent = msg;
            errEl.style.display = 'block';
        }
    } catch(e) {
        errEl.textContent = 'Network error. Please try again.';
        errEl.style.display = 'block';
    } finally {
        btn.textContent = 'Save Changes';
        btn.disabled = false;
    }
}
</script>
@endpush
