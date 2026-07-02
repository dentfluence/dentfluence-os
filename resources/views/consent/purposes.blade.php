{{--
| DPDP Consent — Purpose Catalogue (admin)
| File: resources/views/consent/purposes.blade.php
|
| The master list of things a patient can consent to. DPDP requires consent to
| be purpose-specific, so each purpose is managed as its own item here.
| Admin screen — richer UI is fine (per the UI-complexity rule).
--}}
@extends('layouts.app')

@section('page-title', 'Consent Purposes')

@section('content')
<div
    x-data="{
        open: false,
        mode: 'create',
        base: '{{ url('/consent/purposes') }}',
        form: { id:null, key:'', name:'', description:'', category:'communication', is_mandatory:false, requires_explicit:true, sort_order:0 },
        openCreate() {
            this.mode = 'create';
            this.form = { id:null, key:'', name:'', description:'', category:'communication', is_mandatory:false, requires_explicit:true, sort_order:0 };
            this.open = true;
        },
        openEdit(p) { this.mode = 'edit'; this.form = Object.assign({}, p); this.open = true; },
        get action() { return this.mode === 'edit' ? this.base + '/' + this.form.id : this.base; }
    }"
>
    {{-- ── Header ─────────────────────────────────────────────────────── --}}
    <div class="df-page-header" style="margin-bottom:24px; display:flex; align-items:flex-start; justify-content:space-between;">
        <div>
            <h1 class="df-page-title">Consent Purposes</h1>
            <p class="df-page-subtitle">Each item is a separate, purpose-specific consent (DPDP). Tweak wording with your compliance advisor.</p>
        </div>
        <div class="df-page-actions">
            <button type="button" @click="openCreate()"
                style="background:#C2185B; color:#fff; border:none; padding:10px 18px; border-radius:8px; font-weight:600; cursor:pointer;">
                + Add purpose
            </button>
        </div>
    </div>

    {{-- ── Table ──────────────────────────────────────────────────────── --}}
    <div class="df-card">
        <div class="df-card-body" style="padding:0; overflow:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="text-align:left; background:#faf5f9; color:#4A1F3D;">
                        <th style="padding:12px 16px;">Purpose</th>
                        <th style="padding:12px 16px;">Category</th>
                        <th style="padding:12px 16px; text-align:center;">Mandatory</th>
                        <th style="padding:12px 16px; text-align:center;">Version</th>
                        <th style="padding:12px 16px; text-align:center;">Status</th>
                        <th style="padding:12px 16px; text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($purposes as $p)
                        <tr style="border-top:1px solid #f0e6ee;">
                            <td style="padding:12px 16px;">
                                <div style="font-weight:600; color:#1e0a2c;">{{ $p->name }}</div>
                                <div style="color:#8a7790; font-size:12px;">{{ $p->key }}</div>
                                @if($p->description)
                                    <div style="color:#6b5b73; font-size:12px; margin-top:4px; max-width:480px;">{{ $p->description }}</div>
                                @endif
                            </td>
                            <td style="padding:12px 16px; text-transform:capitalize;">{{ str_replace('_',' ',$p->category) }}</td>
                            <td style="padding:12px 16px; text-align:center;">
                                @if($p->is_mandatory)
                                    <span style="background:#fbe3ec; color:#9c2b48; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:600;">Required</span>
                                @else
                                    <span style="color:#9aa;">Optional</span>
                                @endif
                            </td>
                            <td style="padding:12px 16px; text-align:center;">v{{ $p->version }}</td>
                            <td style="padding:12px 16px; text-align:center;">
                                @if($p->active)
                                    <span style="background:#dcf3e4; color:#1b7a3d; padding:2px 8px; border-radius:10px; font-size:12px; font-weight:600;">Active</span>
                                @else
                                    <span style="background:#eee; color:#888; padding:2px 8px; border-radius:10px; font-size:12px;">Retired</span>
                                @endif
                            </td>
                            <td style="padding:12px 16px; text-align:right; white-space:nowrap;">
                                @php $pData = [
                                    'id' => $p->id, 'key' => $p->key, 'name' => $p->name,
                                    'description' => $p->description, 'category' => $p->category,
                                    'is_mandatory' => (bool) $p->is_mandatory, 'requires_explicit' => (bool) $p->requires_explicit,
                                    'sort_order' => $p->sort_order,
                                ]; @endphp
                                <button type="button"
                                    @click="openEdit({{ \Illuminate\Support\Js::from($pData) }})"
                                    style="background:none; border:1px solid #d8c7d6; color:#4A1F3D; padding:6px 12px; border-radius:6px; cursor:pointer; margin-right:6px;">
                                    Edit
                                </button>
                                <form action="{{ route('consent.purposes.toggle', $p) }}" method="POST" style="display:inline;">
                                    @csrf
                                    <button type="submit"
                                        style="background:none; border:1px solid #d8c7d6; color:#6b5b73; padding:6px 12px; border-radius:6px; cursor:pointer;">
                                        {{ $p->active ? 'Retire' : 'Activate' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="padding:24px; text-align:center; color:#8a7790;">
                            No purposes yet. Run <code>php artisan db:seed --class=ConsentPurposeSeeder</code> or add one above.
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- ── Add / Edit modal ───────────────────────────────────────────── --}}
    <div x-show="open" x-cloak style="position:fixed; inset:0; background:rgba(20,8,30,0.45); display:flex; align-items:center; justify-content:center; z-index:200;"
         @click.self="open=false">
        <div style="background:#fff; width:520px; max-width:94vw; max-height:90vh; overflow:auto; border-radius:14px; padding:24px;">
            <h2 style="margin:0 0 16px; color:#4A1F3D; font-size:18px;" x-text="mode==='edit' ? 'Edit purpose' : 'Add purpose'"></h2>

            <form :action="action" method="POST">
                @csrf
                <input type="hidden" name="_method" :value="mode==='edit' ? 'PATCH' : 'POST'">

                <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Key (slug)</label>
                <input type="text" name="key" x-model="form.key" :readonly="mode==='edit'" pattern="[a-z0-9_]+" required
                       placeholder="e.g. whatsapp_comms"
                       style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:14px;">

                <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Name</label>
                <input type="text" name="name" x-model="form.name" required maxlength="120"
                       style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:14px;">

                <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Description (what the patient agrees to)</label>
                <textarea name="description" x-model="form.description" rows="3" maxlength="1000"
                       style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:14px;"></textarea>

                <div style="display:flex; gap:14px; margin-bottom:14px;">
                    <div style="flex:1;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Category</label>
                        <select name="category" x-model="form.category" style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px;">
                            <option value="clinical">Clinical</option>
                            <option value="communication">Communication</option>
                            <option value="data_sharing">Data sharing</option>
                            <option value="research">Research</option>
                            <option value="general">General</option>
                        </select>
                    </div>
                    <div style="width:110px;">
                        <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Sort</label>
                        <input type="number" name="sort_order" x-model="form.sort_order" min="0"
                               style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px;">
                    </div>
                </div>

                <label style="display:flex; align-items:center; gap:8px; margin-bottom:8px; font-size:14px; color:#1e0a2c;">
                    <input type="hidden" name="is_mandatory" value="0">
                    <input type="checkbox" name="is_mandatory" value="1" x-model="form.is_mandatory">
                    Mandatory (needed to receive care)
                </label>
                <label style="display:flex; align-items:center; gap:8px; margin-bottom:20px; font-size:14px; color:#1e0a2c;">
                    <input type="hidden" name="requires_explicit" value="0">
                    <input type="checkbox" name="requires_explicit" value="1" x-model="form.requires_explicit">
                    Requires explicit opt-in
                </label>

                <div style="display:flex; justify-content:flex-end; gap:10px;">
                    <button type="button" @click="open=false"
                        style="background:none; border:1px solid #d8c7d6; color:#6b5b73; padding:10px 18px; border-radius:8px; cursor:pointer;">Cancel</button>
                    <button type="submit"
                        style="background:#C2185B; color:#fff; border:none; padding:10px 20px; border-radius:8px; font-weight:600; cursor:pointer;">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>[x-cloak]{display:none!important;}</style>
@endsection
