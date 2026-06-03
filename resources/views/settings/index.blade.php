@extends('layouts.app')
@section('page-title', 'Settings')

@push('styles')
<style>
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
    font-family: 'DM Sans', sans-serif;
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
</style>
@endpush

@section('content')
<div x-data="settingsApp()" x-init="init()" style="font-family:'DM Sans',sans-serif;height:100%;display:flex;flex-direction:column;background:#f7f4fa;">

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
        ],
        'Team' => [
            ['id'=>'staff-roles',     'label'=>'Staff & Roles',     'icon'=>'<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
        ],
        'Clinical' => [
            ['id'=>'masters',         'label'=>'Masters',           'icon'=>'<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>'],
            ['id'=>'clinical',        'label'=>'Clinical',          'icon'=>'<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>'],
            ['id'=>'patient-defaults','label'=>'Patient Defaults',  'icon'=>'<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
        ],
        'Finance' => [
            ['id'=>'billing',         'label'=>'Billing & Invoice', 'icon'=>'<line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>'],
            ['id'=>'printing',        'label'=>'Printing',          'icon'=>'<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>'],
        ],
        'Communication' => [
            ['id'=>'notifications',   'label'=>'Notifications',     'icon'=>'<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>'],
            ['id'=>'growth',          'label'=>'Growth & Comms',    'icon'=>'<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>'],
        ],
        'Operations' => [
            ['id'=>'inventory',       'label'=>'Inventory',         'icon'=>'<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 3H8L2 7h20z"/>'],
            ['id'=>'huddle',          'label'=>'Huddle',            'icon'=>'<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 17h7m-3.5-3.5v7"/>'],
        ],
    ];
    @endphp

    @foreach($navGroups as $groupName => $items)
    <div class="snav-group-label">{{ $groupName }}</div>
    @foreach($items as $item)
    <button @click="activeTab='{{ $item['id'] }}'"
            :class="activeTab==='{{ $item['id'] }}' ? 'snav-item snav-item--active' : 'snav-item'">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">{!! $item['icon'] !!}</svg>
        {{ $item['label'] }}
    </button>
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
        <div style="max-width:680px;">
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
                    </div>
                </div>

                <button type="submit" class="settings-save-btn">Save Clinic Profile</button>
            </form>
        </div>
    </div>

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
        <button @click="staffSubTab='roles'"
                :class="staffSubTab==='roles' ? 'srole-tab srole-tab--active' : 'srole-tab'">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Roles & Permissions
        </button>
    </div>
    <style>
    .srole-tab { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;font-size:13px;font-weight:500;color:#7a6080;background:none;border:none;border-bottom:2px solid transparent;cursor:pointer;margin-bottom:-2px;font-family:'DM Sans',sans-serif;transition:color .15s,border-color .15s; }
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
            <div style="display:grid;grid-template-columns:40px 1fr auto auto;gap:12px;align-items:center;padding:13px 20px;border-bottom:1px solid #f5f0f8;">
                {{-- Avatar --}}
                <div style="width:36px;height:36px;border-radius:50%;background:{{ ['#6a0f70','#1a5ea8','#1a7a45','#a05c00','#c0392b'][($loop->index % 5)] }};display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:600;flex-shrink:0;">
                    {{ strtoupper(substr($user->name, 0, 1)) }}
                </div>
                {{-- Info --}}
                <div>
                    <div style="font-size:13.5px;font-weight:500;color:#1a0320;">{{ $user->name }}</div>
                    <div style="font-size:11.5px;color:#9a7aaa;">
                        {{ $user->email }}
                        @if($user->designation) · <span style="color:#7a6a85;">{{ $user->designation }}</span>@endif
                    </div>
                </div>
                {{-- Role badge --}}
                <div>
                    @if($user->roleModel)
                        <span style="padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:{{ $user->roleModel->color }}22;color:{{ $user->roleModel->color }};">{{ $user->roleModel->name }}</span>
                    @elseif($user->role)
                        <span style="padding:3px 10px;border-radius:99px;font-size:11px;font-weight:600;background:#f3eef7;color:#6a0f70;">{{ ucfirst($user->role) }}</span>
                    @endif
                </div>
                {{-- Active toggle --}}
                <div style="display:flex;align-items:center;gap:8px;">
                    <label class="df-toggle {{ $user->is_active ? 'on' : '' }}" style="cursor:pointer;"
                           onclick="toggleStaff({{ $user->id }}, this)">
                        <input type="checkbox" {{ $user->is_active ? 'checked' : '' }} style="display:none;">
                        <span class="df-toggle-track"></span>
                    </label>
                    <span style="font-size:11px;color:#9a7aaa;">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                </div>
            </div>
            @empty
            <div style="padding:48px;text-align:center;color:#b0a0bb;font-size:13px;">No staff yet. Add one above.</div>
            @endforelse
        </div>
    </div>

    </div>{{-- /staffSubTab=staff --}}

    {{-- ── Roles sub-tab ── --}}
    <div x-show="staffSubTab==='roles'" x-cloak
         x-data="rolesManager()" x-init="initRoles()">

        <div style="display:grid;grid-template-columns:260px 1fr;gap:20px;align-items:start;">

            {{-- Role List --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:10px;overflow:hidden;">
                <div style="padding:12px 16px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-size:11px;font-weight:600;letter-spacing:.15em;text-transform:uppercase;color:#9a7aaa;">Roles</span>
                    <button @click="showNewRoleModal=true"
                            style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;background:#6a0f70;color:#fff;border:none;border-radius:5px;font-size:11px;cursor:pointer;">
                        <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                        New
                    </button>
                </div>
                @foreach($roles as $role)
                <div @click="selectRole({{ $role->id }}, '{{ $role->name }}', '{{ $role->slug }}')"
                     class="role-item"
                     :class="selectedRoleId === {{ $role->id }} ? 'active' : ''"
                     style="cursor:pointer;">
                    <div style="display:flex;align-items:center;gap:10px;padding:13px 16px;border-bottom:1px solid #f5f0f8;transition:background 120ms;">
                        <div style="width:9px;height:9px;border-radius:50%;flex-shrink:0;background:{{ $role->color }};"></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:500;color:#1a0320;">{{ $role->name }}</div>
                            <div style="font-size:11px;color:#9a7aaa;">
                                {{ $role->users_count }} {{ Str::plural('staff', $role->users_count) }}
                                @if($role->is_system)· <span style="color:#b0a0bb;">system</span>@endif
                            </div>
                        </div>
                        <svg x-show="selectedRoleId === {{ $role->id }}" width="13" height="13" fill="none" stroke="#6a0f70" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="9 18 15 12 9 6"/></svg>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Permission Grid --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:10px;overflow:hidden;">
                <div style="display:flex;align-items:center;justify-content:space-between;padding:13px 20px;border-bottom:1px solid #ede4f3;">
                    <div>
                        <span style="font-size:11px;font-weight:600;letter-spacing:.15em;text-transform:uppercase;color:#9a7aaa;">Permissions for</span>
                        <span x-text="selectedRoleName || '— select a role'"
                              style="font-size:13.5px;font-weight:600;color:#1a0320;margin-left:8px;"></span>
                    </div>
                    <button x-show="selectedRoleId" @click="savePermissions()" :disabled="saving"
                            style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:500;cursor:pointer;"
                            :style="saving?'opacity:.6;cursor:not-allowed;':''">
                        <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                        <span x-text="saving?'Saving…':'Save Changes'"></span>
                    </button>
                </div>

                <div x-show="!selectedRoleId" style="padding:56px 20px;text-align:center;color:#b0a0bb;">
                    <svg width="38" height="38" fill="none" stroke="currentColor" stroke-width="1.2" viewBox="0 0 24 24" style="margin:0 auto 12px;display:block;opacity:.35;"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    <p style="font-size:13px;margin:0;">Select a role to configure permissions.</p>
                </div>

                <div x-show="selectedRoleId" x-cloak>
                    <div x-show="loadingPerms" style="padding:40px;text-align:center;color:#9a7aaa;font-size:13px;">Loading…</div>

                    <div x-show="!loadingPerms">
                        {{-- Column headers --}}
                        <div class="perm-row perm-header">
                            <div>Module</div>
                            <div style="text-align:center;">View</div>
                            <div style="text-align:center;">Edit</div>
                            <div style="text-align:center;">Delete</div>
                        </div>

                        @foreach($modules as $section => $sectionModules)
                        <div class="perm-section-header">{{ ucfirst($section) }}</div>

                        @foreach($sectionModules as $module)
                        <div class="perm-row" :class="permissions['{{ $module->slug }}']?.view ? '' : 'perm-row--dim'">
                            <div style="display:flex;align-items:center;gap:8px;">
                                <svg width="14" height="14" fill="none" stroke="#9a7aaa" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">{!! $module->icon !!}</svg>
                                <span style="font-size:13px;color:#1a0320;">{{ $module->name }}</span>
                            </div>
                            <div style="text-align:center;">
                                <label class="df-toggle" :class="permissions['{{ $module->slug }}']?.view ? 'on' : ''">
                                    <input type="checkbox" :checked="permissions['{{ $module->slug }}']?.view" @change="toggle('{{ $module->slug }}','view',$event.target.checked)" style="display:none;">
                                    <span class="df-toggle-track"></span>
                                </label>
                            </div>
                            <div style="text-align:center;">
                                <label class="df-toggle" :class="permissions['{{ $module->slug }}']?.edit ? 'on' : ''"
                                       :style="!permissions['{{ $module->slug }}']?.view ? 'pointer-events:none;opacity:.25;' : ''">
                                    <input type="checkbox" :checked="permissions['{{ $module->slug }}']?.edit" @change="toggle('{{ $module->slug }}','edit',$event.target.checked)" :disabled="!permissions['{{ $module->slug }}']?.view" style="display:none;">
                                    <span class="df-toggle-track"></span>
                                </label>
                            </div>
                            <div style="text-align:center;">
                                <label class="df-toggle" :class="permissions['{{ $module->slug }}']?.delete ? 'on' : ''"
                                       :style="!permissions['{{ $module->slug }}']?.edit ? 'pointer-events:none;opacity:.25;' : ''">
                                    <input type="checkbox" :checked="permissions['{{ $module->slug }}']?.delete" @change="toggle('{{ $module->slug }}','delete',$event.target.checked)" :disabled="!permissions['{{ $module->slug }}']?.edit" style="display:none;">
                                    <span class="df-toggle-track"></span>
                                </label>
                            </div>
                        </div>
                        @endforeach
                        @endforeach
                    </div>

                    <div x-show="savedMsg" x-transition
                         style="padding:11px 20px;background:#e8f7ef;color:#1a7a45;font-size:13px;border-top:1px solid #c8ebd8;display:flex;align-items:center;gap:7px;">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                        Permissions saved successfully.
                    </div>
                </div>
            </div>
        </div>

        {{-- New Role Modal --}}
        <div x-show="showNewRoleModal" x-cloak
             style="position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;background:rgba(14,1,24,.45);"
             @click.self="showNewRoleModal=false">
            <div style="background:#fff;border-radius:12px;width:400px;padding:28px;box-shadow:0 20px 60px rgba(14,1,24,.25);">
                <h2 style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:700;color:#1a0320;margin:0 0 20px;">Create New Role</h2>
                <div style="margin-bottom:13px;">
                    <label class="settings-label">Role Name *</label>
                    <input x-model="newRole.name" type="text" placeholder="e.g. Lab Technician" class="settings-input">
                </div>
                <div style="margin-bottom:13px;">
                    <label class="settings-label">Description</label>
                    <input x-model="newRole.description" type="text" placeholder="Brief description" class="settings-input">
                </div>
                <div style="margin-bottom:22px;">
                    <label class="settings-label">Badge Colour</label>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:6px;">
                        <template x-for="c in colours" :key="c">
                            <div @click="newRole.color=c"
                                 :style="`width:24px;height:24px;border-radius:50%;background:${c};cursor:pointer;outline:3px solid ${newRole.color===c?'#1a0320':'transparent'};outline-offset:2px;transition:outline 120ms;`"></div>
                        </template>
                    </div>
                </div>
                <div style="display:flex;gap:10px;justify-content:flex-end;">
                    <button @click="showNewRoleModal=false" style="padding:8px 18px;border:1.5px solid #ddd;background:#fff;border-radius:6px;font-size:13px;cursor:pointer;color:#555;">Cancel</button>
                    <button @click="createRole()" :disabled="!newRole.name"
                            style="padding:8px 18px;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;"
                            :style="!newRole.name?'opacity:.5;cursor:not-allowed;':''">Create Role</button>
                </div>
            </div>
        </div>
    </div>{{-- /staffSubTab=roles --}}
    </div>{{-- /staff-roles panel --}}

    {{-- ════════════════════════════════════════════
         TAB · NOTIFICATIONS
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='notifications'" x-cloak>
        <div style="max-width:620px;">
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
        <div style="max-width:620px;">
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
                            <input type="text" name="currency_symbol" value="{{ $b['currency_symbol'] ?? '₹' }}" class="settings-input" placeholder="₹">
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

                <button type="submit" class="settings-save-btn">Save Billing Settings</button>
            </form>
        </div>
    </div>

    {{-- ════════════════════════════════════════════
         TAB · PRINTING
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='printing'" x-cloak>
        <div style="max-width:720px;">
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
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

            {{-- Treatments --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div style="padding:14px 18px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;">
                    <span class="settings-section-title" style="margin:0;">Treatments</span>
                    <span style="font-size:11px;color:#9a7aaa;">{{ $treatments->count() }}</span>
                </div>
                <form action="{{ route('settings.masters.treatments.store') }}" method="POST" style="padding:12px 18px;border-bottom:1px solid #f5f0f8;display:flex;gap:8px;">
                    @csrf
                    <input name="name" class="settings-input" placeholder="Treatment name" required style="flex:1;">
                    <input name="default_price" type="number" class="settings-input" placeholder="₹" style="width:72px;" min="0">
                    <button type="submit" class="settings-save-btn" style="padding:7px 14px;font-size:12px;">+</button>
                </form>
                <div style="max-height:240px;overflow-y:auto;">
                    @forelse($treatments as $item)
                    <div style="display:flex;align-items:center;justify-content:space-between;padding:9px 18px;border-bottom:1px solid #f9f5fc;">
                        <span style="font-size:13px;color:#1a0320;">{{ $item->name }}</span>
                        <div style="display:flex;align-items:center;gap:10px;">
                            @if(isset($item->default_price) && $item->default_price > 0)
                            <span style="font-size:12px;color:#9a7aaa;">₹{{ number_format($item->default_price,0) }}</span>
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
        <div style="max-width:560px;">

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
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;max-width:900px;">

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
    </div>

    {{-- ════════════════════════════════════════════
         TAB 9 · GROWTH & COMMS
    ════════════════════════════════════════════ --}}
    <div x-show="activeTab==='growth'" x-cloak>
        <div style="max-width:700px;">

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

        {{-- ── Categories + Sub-types ── --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:24px;">

            {{-- Categories --}}
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,#f9f3fa,#f3e8f7);">
                    <div>
                        <h3 class="settings-section-title" style="margin:0 0 2px;">Item Categories</h3>
                        <p style="font-size:12px;color:#9a7aaa;margin:0;">{{ $invCategories->count() }} categories</p>
                    </div>
                    <button onclick="document.getElementById('inv-modal-add-category').style.display='flex'"
                            style="background:#6a0f70;color:#fff;border:none;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:5px;">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add
                    </button>
                </div>
                <div>
                    @forelse($invCategories as $cat)
                    <div style="display:flex;align-items:center;gap:12px;padding:11px 20px;border-bottom:1px solid #f5f0f8;{{ !$cat->is_active ? 'opacity:0.5;' : '' }}">
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
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #e8f7ee;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,#f5fbf7,#e8f7ee);">
                    <div>
                        <h3 class="settings-section-title" style="margin:0 0 2px;color:#1a7a45;">Sub-types</h3>
                        <p style="font-size:12px;color:#5a9a6a;margin:0;">Product sub-types per category</p>
                    </div>
                    <button onclick="document.getElementById('inv-modal-add-subtype').style.display='flex'"
                            style="background:#1a7a45;color:#fff;border:none;border-radius:5px;padding:7px 14px;font-size:12px;font-weight:500;cursor:pointer;">+ Add</button>
                </div>
                <div style="padding:16px 20px;">
                    @forelse($invSubTypes->groupBy(fn($st) => $st->category?->name ?? 'Uncategorised') as $catName => $group)
                    <div style="margin-bottom:16px;">
                        <div style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.06em;color:#1a7a45;margin-bottom:8px;">{{ $catName }}</div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;">
                            @foreach($group as $st)
                            <div style="display:inline-flex;align-items:center;gap:6px;background:{{ $st->is_active ? '#e8f7ee' : '#f5f5f5' }};border:1px solid {{ $st->is_active ? '#a3d9b8' : '#ddd' }};border-radius:20px;padding:4px 10px 4px 12px;font-size:12.5px;color:{{ $st->is_active ? '#1a7a45' : '#888' }};">
                                {{ $st->name }}
                                <button onclick="invOpenEditSubType({{ $st->id }}, '{{ addslashes($st->name) }}', {{ $st->category_id }}, {{ $st->is_active ? 'true' : 'false' }})" style="background:none;border:none;cursor:pointer;font-size:11px;color:#888;padding:0;line-height:1;" title="Edit">✎</button>
                                <form method="POST" action="{{ route('inventory.settings.sub-types.destroy', $st) }}" style="display:inline;" onsubmit="return confirm('Delete?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" style="background:none;border:none;cursor:pointer;font-size:13px;color:#b52020;padding:0;line-height:1;">×</button>
                                </form>
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @empty
                    <div style="padding:24px;text-align:center;font-size:13px;color:#9a7aaa;">No sub-types yet.</div>
                    @endforelse
                </div>
            </div>

        </div>

        {{-- Locations --}}
        <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;overflow:hidden;max-width:640px;">
            <div style="padding:16px 20px;border-bottom:1px solid #ede4f3;display:flex;align-items:center;justify-content:space-between;background:linear-gradient(135deg,#f9f3fa,#f3e8f7);">
                <div>
                    <h3 class="settings-section-title" style="margin:0 0 2px;">Storage Locations</h3>
                    <p style="font-size:12px;color:#9a7aaa;margin:0;">{{ $invLocations->count() }} locations</p>
                </div>
                <button onclick="document.getElementById('inv-modal-add-location').style.display='flex'"
                        style="background:#6a0f70;color:#fff;border:none;border-radius:6px;padding:7px 14px;font-size:12px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:5px;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg> Add
                </button>
            </div>
            <div>
                @forelse($invLocations as $loc)
                <div style="display:flex;align-items:center;gap:12px;padding:11px 20px;border-bottom:1px solid #f5f0f8;{{ !$loc->is_active ? 'opacity:0.5;' : '' }}">
                    <span style="font-size:14px;flex-shrink:0;">
                        @switch($loc->type)
                            @case('main_store') 🏪 @break
                            @case('operatory') 🦷 @break
                            @case('sterilization') 🧪 @break
                            @case('lab') 🔬 @break
                            @case('implant_drawer') 🗂️ @break
                            @case('storage') 📦 @break
                            @default 📍
                        @endswitch
                    </span>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:500;color:#1a0320;">{{ $loc->name }}@if(!$loc->is_active)<span style="font-size:10px;color:#9a7aaa;margin-left:4px;">(inactive)</span>@endif</div>
                        <div style="font-size:11px;color:#9a7aaa;">{{ $loc->getTypeLabel() }}@if($loc->code) · <code style="font-size:11px;">{{ $loc->code }}</code>@endif</div>
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
                            <option value="main_store">🏪 Main Store</option><option value="operatory">🦷 Operatory</option>
                            <option value="sterilization">🧪 Sterilization</option><option value="lab">🔬 Lab</option>
                            <option value="implant_drawer">🗂️ Implant Drawer</option><option value="storage">📦 Storage</option>
                            <option value="other">📍 Other</option>
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
                            <option value="main_store">🏪 Main Store</option><option value="operatory">🦷 Operatory</option>
                            <option value="sterilization">🧪 Sterilization</option><option value="lab">🔬 Lab</option>
                            <option value="implant_drawer">🗂️ Implant Drawer</option><option value="storage">📦 Storage</option>
                            <option value="other">📍 Other</option>
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
        <div style="max-width:600px;">
            <div style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;margin-bottom:20px;">
                <h3 class="settings-section-title">Huddle Board Configuration</h3>
                <p style="font-size:12.5px;color:#9a7aaa;margin:0 0 20px;">These settings control how the daily huddle board behaves for your clinic.</p>

                <form id="huddle-settings-form">
                    @csrf
                    {{-- Display existing huddle settings as key-value fields --}}
                    @if($huddleSettings->isNotEmpty())
                        @foreach($huddleSettings as $key => $value)
                        <div style="margin-bottom:16px;">
                            <label class="settings-label">{{ ucwords(str_replace('_', ' ', $key)) }}</label>
                            @if(is_array($value) || is_object($value))
                                <textarea name="settings[{{ $key }}]" rows="3" class="settings-input" style="resize:vertical;">{{ json_encode($value, JSON_PRETTY_PRINT) }}</textarea>
                            @elseif(in_array(strtolower($value), ['true','false','0','1']))
                                <select name="settings[{{ $key }}]" class="settings-input" style="background:#fff;">
                                    <option value="true" {{ $value === 'true' || $value === '1' ? 'selected' : '' }}>Enabled</option>
                                    <option value="false" {{ $value === 'false' || $value === '0' ? 'selected' : '' }}>Disabled</option>
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

</div>{{-- /tab content --}}
</div>{{-- /sidebar+content row --}}
</div>{{-- /settingsApp --}}

@endsection

@push('scripts')
<script>
function settingsApp() {
    return {
        activeTab: '{{ $activeTab }}',
        staffSubTab: 'staff',  // internal sub-tab within Staff & Roles panel
        letterheadPreview: null,
        printHeader: '{{ $print["print_header_type"] ?? "plain" }}',

        init() {
            // Normalize legacy tab names
            if (this.activeTab === 'staff') { this.activeTab = 'staff-roles'; this.staffSubTab = 'staff'; }
            if (this.activeTab === 'roles') { this.activeTab = 'staff-roles'; this.staffSubTab = 'roles'; }
            // Restore tab from URL hash if present
            const hash = window.location.hash.replace('#', '');
            if (hash) this.activeTab = hash;
        },

        logoPreview(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = e => {
                document.querySelector('.clinic-logo-preview img, .clinic-logo-preview div').outerHTML =
                    `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
            };
            reader.readAsDataURL(file);
        },
    };
}

function rolesManager() {
    return {
        selectedRoleId: null,
        permissions: {},
        loadingPerms: false,
        saving: false,
        savedMsg: false,
        showNewRoleModal: false,
        newRole: { name: '', description: '' },

        initRoles() {},

        async selectRole(id) {
            this.selectedRoleId = id;
            this.loadingPerms  = true;
            const res = await fetch(`/settings/roles/${id}/permissions`);
            const data = await res.json();
            this.permissions  = data.permissions || {};
            this.loadingPerms = false;
        },

        async savePermissions() {
            this.saving = true;
            await fetch(`/settings/roles/${this.selectedRoleId}/permissions`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify({ permissions: this.permissions }),
            });
            this.saving  = false;
            this.savedMsg = true;
            setTimeout(() => this.savedMsg = false, 2500);
        },

        async createRole() {
            const res = await fetch('/settings/roles', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify(this.newRole),
            });
            if (res.ok) window.location.reload();
        },
    };
}
</script>
@endpush
