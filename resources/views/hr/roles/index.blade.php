@extends('layouts.app')
@section('page-title', 'Roles & Permissions')

@section('content')
<div x-data="rolesManager()" x-init="init()"
     style="font-family:'Inter',sans-serif; padding:28px 32px; max-width:1200px;">

    @include('hr.partials.subnav', ['active' => 'roles'])

    {{-- ── Page Header ── --}}
    <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:28px;">
        <div>
            <h1 style="font-family:'Cormorant Garamond',serif; font-size:26px; font-weight:700; color:#1a0320; margin:0 0 4px;">
                Roles &amp; Permissions
            </h1>
            <p style="font-size:13px; color:#7a6a85; margin:0;">
                Control what each role can view, edit, or delete across Dentfluence OS.
            </p>
        </div>
        <button @click="showNewRoleModal = true"
                style="display:inline-flex; align-items:center; gap:7px; padding:9px 18px; background:#6a0f70; color:#fff; border:none; border-radius:6px; font-size:13px; font-weight:500; cursor:pointer;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 4v16m8-8H4"/></svg>
            New Role
        </button>
    </div>

    {{-- ── Layout: Role List (left) + Permission Grid (right) ── --}}
    <div style="display:grid; grid-template-columns:260px 1fr; gap:20px; align-items:start;">

        {{-- ── Role List ── --}}
        <div style="background:#fff; border:1px solid #ede4f3; border-radius:10px; overflow:hidden;">

            {{-- Doctors section --}}
            @if(isset($rolesByCategory['doctor']) && $rolesByCategory['doctor']->isNotEmpty())
            <div style="padding:10px 16px 6px; font-size:10px; font-weight:700; letter-spacing:.18em; text-transform:uppercase; color:#1a7a45; background:#f2fbf5; border-bottom:1px solid #d4edda; display:flex; align-items:center; gap:6px;">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="2.5" stroke-linecap="round">
                    <path d="M12 22C12 22 5 17 5 11C5 7 7.5 4 12 4C16.5 4 19 7 19 11C19 17 12 22 12 22Z"/>
                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="10" y1="11" x2="14" y2="11"/>
                </svg>
                Doctors
            </div>
            @foreach($rolesByCategory['doctor'] as $role)
            <div @click="selectRole({{ $role->id }}, '{{ $role->name }}', '{{ $role->slug }}', {{ $role->is_system ? 'true' : 'false' }})"
                 :class="selectedRoleId === {{ $role->id }} ? 'role-item active' : 'role-item'"
                 style="cursor:pointer;">
                <div style="display:flex; align-items:center; gap:10px; padding:13px 16px; border-bottom:1px solid #f5f0f8; transition:background 120ms;">
                    <div style="width:9px; height:9px; border-radius:50%; flex-shrink:0; background:{{ $role->color }};"></div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:13.5px; font-weight:500; color:#1a0320; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $role->name }}</div>
                        <div style="font-size:11px; color:#9a7aaa; margin-top:1px;">
                            {{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}
                            @if($role->is_system) · <span style="color:#b0a0bb;">system</span>@endif
                        </div>
                    </div>
                    <svg x-show="selectedRoleId === {{ $role->id }}" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </div>
            </div>
            @endforeach
            @endif

            {{-- Staff section --}}
            @if(isset($rolesByCategory['staff']) && $rolesByCategory['staff']->isNotEmpty())
            <div style="padding:10px 16px 6px; font-size:10px; font-weight:700; letter-spacing:.18em; text-transform:uppercase; color:#1a5ea8; background:#f0f6ff; border-bottom:1px solid #c9ddf5; border-top:1px solid #ede4f3; display:flex; align-items:center; gap:6px;">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="#1a5ea8" stroke-width="2.5" stroke-linecap="round">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                Staff
            </div>
            @foreach($rolesByCategory['staff'] as $role)
            <div @click="selectRole({{ $role->id }}, '{{ $role->name }}', '{{ $role->slug }}', {{ $role->is_system ? 'true' : 'false' }})"
                 :class="selectedRoleId === {{ $role->id }} ? 'role-item active' : 'role-item'"
                 style="cursor:pointer;">
                <div style="display:flex; align-items:center; gap:10px; padding:13px 16px; border-bottom:1px solid #f5f0f8; transition:background 120ms;">
                    <div style="width:9px; height:9px; border-radius:50%; flex-shrink:0; background:{{ $role->color }};"></div>
                    <div style="flex:1; min-width:0;">
                        <div style="font-size:13.5px; font-weight:500; color:#1a0320; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $role->name }}</div>
                        <div style="font-size:11px; color:#9a7aaa; margin-top:1px;">
                            {{ $role->users_count }} {{ Str::plural('user', $role->users_count) }}
                            @if($role->is_system) · <span style="color:#b0a0bb;">system</span>@endif
                        </div>
                    </div>
                    <svg x-show="selectedRoleId === {{ $role->id }}" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg>
                </div>
            </div>
            @endforeach
            @endif

        </div>

        {{-- ── Permission Grid ── --}}
        <div style="background:#fff; border:1px solid #ede4f3; border-radius:10px; overflow:hidden;">

            {{-- Header row --}}
            <div style="display:flex; align-items:center; justify-content:space-between; padding:14px 20px; border-bottom:1px solid #ede4f3;">
                <div>
                    <span style="font-size:11px; font-weight:600; letter-spacing:.15em; text-transform:uppercase; color:#9a7aaa;">Permissions for</span>
                    <span x-text="selectedRoleName || '— select a role'"
                          style="font-size:14px; font-weight:600; color:#1a0320; margin-left:8px;"></span>
                </div>
                <div style="display:flex; align-items:center; gap:8px;">
                    <button x-show="selectedRoleId && !selectedRoleIsSystem" @click="deleteRole()" :disabled="deleting"
                            style="display:inline-flex; align-items:center; gap:6px; padding:7px 14px; background:#fff; color:#c0392b; border:1px solid #f0c8c8; border-radius:6px; font-size:12.5px; font-weight:500; cursor:pointer;"
                            :style="deleting ? 'opacity:.6;cursor:not-allowed;' : ''">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                        <span x-text="deleting ? 'Deleting…' : 'Delete Role'"></span>
                    </button>
                    <button x-show="selectedRoleId" @click="savePermissions()" :disabled="saving"
                            style="display:inline-flex; align-items:center; gap:6px; padding:7px 16px; background:#6a0f70; color:#fff; border:none; border-radius:6px; font-size:12.5px; font-weight:500; cursor:pointer;"
                            :style="saving ? 'opacity:.6;cursor:not-allowed;' : ''">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                            <polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                        </svg>
                        <span x-text="saving ? 'Saving…' : 'Save Changes'"></span>
                    </button>
                </div>
            </div>

            {{-- No role selected state --}}
            <div x-show="!selectedRoleId" style="padding:60px 20px; text-align:center; color:#b0a0bb;">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.2" style="margin:0 auto 12px; display:block; opacity:.4;">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                </svg>
                <p style="font-size:13px;">Select a role on the left to configure permissions.</p>
            </div>

            {{-- Permission table --}}
            <div x-show="selectedRoleId" x-cloak>
                <div>
                    {{-- Column headers --}}
                    <div style="display:grid; grid-template-columns:1fr 90px 90px 90px; padding:10px 20px; background:#f9f5fc; border-bottom:1px solid #ede4f3; font-size:11px; font-weight:600; letter-spacing:.12em; text-transform:uppercase; color:#9a7aaa;">
                        <div>Module</div>
                        <div style="text-align:center;">View</div>
                        <div style="text-align:center;">Edit</div>
                        <div style="text-align:center;">Delete</div>
                    </div>

                    @foreach($modules as $section => $sectionModules)
                    <div style="padding:8px 20px 4px; font-size:10.5px; font-weight:600; letter-spacing:.18em; text-transform:uppercase; color:#c5a8d8; background:#fdf9ff; border-bottom:1px solid #f5f0f8;">
                        {{ ucfirst($section) }}
                    </div>
                    @foreach($sectionModules as $module)
                    <div style="display:grid; grid-template-columns:1fr 90px 90px 90px; padding:11px 20px; border-bottom:1px solid #f5f0f8; align-items:center;"
                         :style="permissions['{{ $module->slug }}']?.view ? '' : 'opacity:.55;'">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9a7aaa" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                {!! $module->icon !!}
                            </svg>
                            <span style="font-size:13px; color:#1a0320;">{{ $module->name }}</span>
                        </div>
                        {{-- View --}}
                        <div style="text-align:center;">
                            <label class="df-toggle" :class="permissions['{{ $module->slug }}']?.view ? 'on' : ''">
                                <input type="checkbox" :checked="permissions['{{ $module->slug }}']?.view"
                                       @change="toggle('{{ $module->slug }}', 'view', $event.target.checked)" style="display:none;">
                                <span class="df-toggle-track"></span>
                            </label>
                        </div>
                        {{-- Edit --}}
                        <div style="text-align:center;">
                            <label class="df-toggle" :class="permissions['{{ $module->slug }}']?.edit ? 'on' : ''"
                                   :style="!permissions['{{ $module->slug }}']?.view ? 'pointer-events:none;opacity:.3;' : ''">
                                <input type="checkbox" :checked="permissions['{{ $module->slug }}']?.edit"
                                       @change="toggle('{{ $module->slug }}', 'edit', $event.target.checked)"
                                       :disabled="!permissions['{{ $module->slug }}']?.view" style="display:none;">
                                <span class="df-toggle-track"></span>
                            </label>
                        </div>
                        {{-- Delete --}}
                        <div style="text-align:center;">
                            <label class="df-toggle" :class="permissions['{{ $module->slug }}']?.delete ? 'on' : ''"
                                   :style="!permissions['{{ $module->slug }}']?.edit ? 'pointer-events:none;opacity:.3;' : ''">
                                <input type="checkbox" :checked="permissions['{{ $module->slug }}']?.delete"
                                       @change="toggle('{{ $module->slug }}', 'delete', $event.target.checked)"
                                       :disabled="!permissions['{{ $module->slug }}']?.edit" style="display:none;">
                                <span class="df-toggle-track"></span>
                            </label>
                        </div>
                    </div>
                    @endforeach
                    @endforeach
                </div>
            </div>

            {{-- Save feedback --}}
            <div x-show="savedMsg" x-transition
                 style="padding:12px 20px; background:#e8f7ef; color:#1a7a45; font-size:13px; border-top:1px solid #c8ebd8; display:flex; align-items:center; gap:7px;">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Permissions saved successfully.
            </div>
        </div>{{-- /permission grid --}}
    </div>{{-- /grid --}}

    {{-- ── New Role Modal — teleported to body to escape layout transform stacking context ── --}}
    <template x-teleport="body">
    <div x-show="showNewRoleModal" x-cloak
         style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(14,1,24,.45);"
         @keydown.escape.window="showNewRoleModal = false"
         @click.self="showNewRoleModal = false">
        <div style="background:#fff;border-radius:12px;width:420px;padding:28px;box-shadow:0 20px 60px rgba(14,1,24,.25);">
            <h2 style="font-family:'Cormorant Garamond',serif;font-size:20px;font-weight:700;color:#1a0320;margin:0 0 20px;">Create New Role</h2>
            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Role Name *</label>
                <input x-model="newRole.name" type="text" placeholder="e.g. Lab Technician"
                       style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;outline:none;box-sizing:border-box;"
                       @focus="$el.style.borderColor='#6a0f70'" @blur="$el.style.borderColor='#ddd'">
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Description</label>
                <input x-model="newRole.description" type="text" placeholder="Brief description"
                       style="width:100%;padding:9px 12px;border:1.5px solid #ddd;border-radius:6px;font-size:13px;outline:none;box-sizing:border-box;"
                       @focus="$el.style.borderColor='#6a0f70'" @blur="$el.style.borderColor='#ddd'">
            </div>
            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:6px;">Role Type</label>
                <div style="display:flex;gap:8px;">
                    <label :style="newRole.category==='staff' ? 'border-color:#6a0f70;background:#faf0ff;' : 'border-color:#ddd;background:#fff;'"
                           style="flex:1;display:flex;align-items:center;gap:7px;padding:8px 12px;border:1.5px solid #ddd;border-radius:6px;cursor:pointer;font-size:13px;color:#1a0320;transition:all 120ms;">
                        <input type="radio" x-model="newRole.category" value="staff" style="accent-color:#6a0f70;"> Staff
                    </label>
                    <label :style="newRole.category==='doctor' ? 'border-color:#1a7a45;background:#f2fbf5;' : 'border-color:#ddd;background:#fff;'"
                           style="flex:1;display:flex;align-items:center;gap:7px;padding:8px 12px;border:1.5px solid #ddd;border-radius:6px;cursor:pointer;font-size:13px;color:#1a0320;transition:all 120ms;">
                        <input type="radio" x-model="newRole.category" value="doctor" style="accent-color:#1a7a45;"> Doctor
                    </label>
                </div>
            </div>
            <div style="margin-bottom:24px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:8px;">Badge Colour</label>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <template x-for="c in colours" :key="c">
                        <div @click="newRole.color = c"
                             :style="`width:24px;height:24px;border-radius:50%;background:${c};cursor:pointer;border:2px solid ${newRole.color===c ? '#1a0320' : 'transparent'};transition:border 120ms;`">
                        </div>
                    </template>
                </div>
            </div>
            <div style="display:flex;gap:10px;justify-content:flex-end;">
                <button @click="showNewRoleModal=false"
                        style="padding:8px 18px;border:1.5px solid #ddd;background:#fff;border-radius:6px;font-size:13px;cursor:pointer;color:#555;">Cancel</button>
                <button @click="createRole()" :disabled="!newRole.name"
                        style="padding:8px 18px;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:500;cursor:pointer;"
                        :style="!newRole.name ? 'opacity:.5;cursor:not-allowed;' : ''">Create Role</button>
            </div>
        </div>
    </div>
    </template>{{-- /x-teleport --}}

</div>{{-- /x-data --}}

<style>
    [x-cloak] { display:none !important; }
    .role-item:hover > div { background:#faf5fc; }
    .role-item.active > div { background:#f3e8f9; }
    .df-toggle { display:inline-flex; align-items:center; cursor:pointer; }
    .df-toggle-track { width:36px; height:20px; border-radius:10px; background:#e0d5e8; display:inline-block; position:relative; transition:background 180ms; }
    .df-toggle-track::after { content:''; position:absolute; top:3px; left:3px; width:14px; height:14px; border-radius:50%; background:#fff; transition:transform 180ms, background 180ms; box-shadow:0 1px 3px rgba(0,0,0,.18); }
    .df-toggle.on .df-toggle-track { background:#6a0f70; }
    .df-toggle.on .df-toggle-track::after { transform:translateX(16px); }
</style>

<script>
function rolesManager() {
    return {
        selectedRoleId:       null,
        selectedRoleName:     '',
        selectedRoleSlug:     '',
        selectedRoleIsSystem: false,
        permissions:      {},
        saving:           false,
        deleting:         false,
        savedMsg:         false,
        showNewRoleModal: false,
        newRole:          { name:'', description:'', color:'#6a0f70', category:'staff' },
        colours: ['#6a0f70','#1a5ea8','#1a7a45','#a05c00','#c0392b','#2c3e50','#8e24aa','#00695c','#ad1457'],
        allPermissions: @json($allPermissions),

        init() {
            @if($roles->isNotEmpty())
            this.selectRole({{ $roles->first()->id }}, '{{ $roles->first()->name }}', '{{ $roles->first()->slug }}', {{ $roles->first()->is_system ? 'true' : 'false' }});
            @endif
        },

        selectRole(id, name, slug, isSystem) {
            this.selectedRoleId       = id;
            this.selectedRoleName     = name;
            this.selectedRoleSlug     = slug;
            this.selectedRoleIsSystem = !!isSystem;
            this.savedMsg         = false;
            this.permissions      = this.allPermissions[id] ?? {};
        },

        toggle(slug, action, value) {
            if (!this.permissions[slug]) {
                this.permissions[slug] = { view: false, edit: false, delete: false };
            }
            this.permissions[slug][action] = value;
            if (action === 'view' && !value) { this.permissions[slug].edit = false; this.permissions[slug].delete = false; }
            if (action === 'edit' && !value)  { this.permissions[slug].delete = false; }
            if (action === 'edit' && value)   { this.permissions[slug].view = true; }
            if (action === 'delete' && value) { this.permissions[slug].view = true; this.permissions[slug].edit = true; }
            this.permissions = { ...this.permissions };
        },

        async savePermissions() {
            this.saving = true;
            try {
                const res = await fetch(`/settings/roles/${this.selectedRoleId}`, {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ permissions: this.permissions }),
                });
                if (!res.ok) throw new Error('Server error ' + res.status);
                this.allPermissions[this.selectedRoleId] = { ...this.permissions };
                this.savedMsg = true;
                setTimeout(() => this.savedMsg = false, 3000);
            } catch(e) {
                alert('Error saving permissions: ' + e.message);
            } finally {
                this.saving = false;
            }
        },

        async deleteRole() {
            if (this.selectedRoleIsSystem) return; // guarded server-side too, but never even try
            if (!confirm(`Delete the role "${this.selectedRoleName}"? Any users currently on it will need to be reassigned a role separately.`)) return;

            this.deleting = true;
            try {
                const res = await fetch(`/settings/roles/${this.selectedRoleId}`, {
                    method:  'DELETE',
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                });
                if (!res.ok) {
                    const body = await res.json().catch(() => ({}));
                    throw new Error(body.message || ('Server error ' + res.status));
                }
                window.location.reload();
            } catch(e) {
                alert('Error deleting role: ' + e.message);
                this.deleting = false;
            }
        },

        async createRole() {
            if (!this.newRole.name) return;
            try {
                const res  = await fetch('/settings/roles', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify(this.newRole),
                });
                const data = await res.json();
                if (data.role) { window.location.reload(); }
            } catch(e) {
                alert('Error creating role.');
            }
        },
    };
}
</script>
@endsection
