@extends('layouts.app')

{{-- ══════════════════════════════════════════════════════════════════════
     PATIENT PROFILE — thin orchestrator (Patients Module Phase 4, Slice 1)

     The former 3,700-line monolith is decomposed into scoped partials:
       patients/profile/*  — eager pieces (header, profile tab, shared modals)
       patients/tabs/*     — lazy tab bodies, served by PatientController@tab
                             as HTML fragments on first activation.

     Data contract: PatientProfileService::coreProfile() supplies everything
     the eager pieces need; each lazy tab's data comes from tabData().
══════════════════════════════════════════════════════════════════════ --}}

@include('patients.profile.styles')

@section('content')
<div x-data="patientProfile()" x-init="init()">

@include('patients.profile.header')

{{-- Tab content scroll area — sits below sticky header --}}
<div style="padding-top:4px;"></div>

{{-- Profile tab (default) — always rendered server-side --}}
@include('patients.profile.tab-profile')

{{-- Lazy tabs (Phase 4) — empty containers; fragment HTML is fetched into
     them on first activation by ensureTab(). Injected markup keeps its own
     x-show="activeTab === '…'" wrapper, so visibility still follows the
     same Alpine state as before the refactor. --}}
@foreach(\App\Services\PatientProfileService::LAZY_TABS as $lazyTab)
<div id="tab-panel-{{ $lazyTab }}" data-lazy-tab="{{ $lazyTab }}"></div>
@endforeach

@include('patients.profile.quick-pay-modal')

@include('patients.profile.edit-patient-prefill')

</div>{{-- /x-data patientProfile --}}

@include('patients.profile.action-modal')

@endsection

@push('scripts')
<script>
function patientProfile() {
    return {
        activeTab: 'profile',
        aocpModalOpen: false,
        showNoteForm: false,
        showOppForm: false,
        noteType: 'internal',

        relationshipNotes: @json($relationshipNotes ?? []),
        newNote: '',
        newNoteTags: [],
        noteSaving: false,
        noteSaveError: '',

        opportunities: @json($opportunities ?? []),
        newOpp: { type:'', status:'prospect', priority:'medium', estimated_value:'', follow_up_date:'' },
        oppSaving: false,
        oppEditId: null,
        oppEditData: {},

        // ── Phase 4: lazy tab fragments ─────────────────────────────────
        loadedTabs: {},

        oppTypeColors: {
            implant:          { color:'#6a0f70', bg:'#f5f3ff' },
            aligner:          { color:'#2563eb', bg:'#dbeafe' },
            veneers:          { color:'#0891b2', bg:'#e0f2fe' },
            full_mouth_rehab: { color:'#7c3aed', bg:'#ede9fe' },
            whitening:        { color:'#ca8a04', bg:'#fef9c3' },
            crown:            { color:'#b45309', bg:'#fef3c7' },
            bridge:           { color:'#0d9488', bg:'#ccfbf1' },
            rct:              { color:'#dc2626', bg:'#fee2e2' },
            smile_design:     { color:'#db2777', bg:'#fce7f3' },
            gum_treatment:    { color:'#16a34a', bg:'#dcfce7' },
        },

        oppIcons: {
            implant:'', aligner:'', veneers:'✨', full_mouth_rehab:'',
            whitening:'', crown:'', bridge:'', rct:'',
            smile_design:'', gum_treatment:'',
        },

        init() {
            const hash = window.location.hash.replace('#','');
            const validTabs = ['profile','consultation','treatment-plan','visits','lab','prescriptions','billing','wallet','documents','notes'];
            if (validTabs.includes(hash)) {
                this.activeTab = hash;
            }
            // Lazy loader: fetch a tab's fragment the first time it opens.
            this.$watch('activeTab', (tab) => this.ensureTab(tab));
            if (this.activeTab !== 'profile') this.ensureTab(this.activeTab);
        },

        /**
         * Fetch + inject a lazy tab fragment (once). Injected <script> tags are
         * recreated because innerHTML never executes them; Alpine picks up the
         * new DOM automatically since it lands inside this component's scope.
         */
        ensureTab(tab) {
            if (tab === 'profile' || this.loadedTabs[tab] === true) return Promise.resolve();
            if (this.loadedTabs[tab]) return this.loadedTabs[tab]; // in-flight fetch — share the same promise
            const panel = document.getElementById('tab-panel-' + tab);
            if (!panel) return Promise.resolve();

            const inflight = (async () => {
                try {
                    const r = await fetch('{{ url("patients/{$patient->id}/tab") }}/' + tab, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/html' },
                    });
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    panel.innerHTML = await r.text();
                    panel.querySelectorAll('script').forEach(old => {
                        const s = document.createElement('script');
                        Array.from(old.attributes).forEach(a => s.setAttribute(a.name, a.value));
                        s.textContent = old.textContent;
                        old.replaceWith(s);
                    });
                    this.loadedTabs[tab] = true;
                    window.dispatchEvent(new CustomEvent('patient-tab-loaded', { detail: { tab } }));
                } catch (e) {
                    this.loadedTabs[tab] = false; // allow retry on next click
                    panel.innerHTML = '<div class="mx-6 my-6 text-sm text-red-600 bg-red-50 border border-red-200 rounded px-4 py-3">Could not load this tab. Check your connection and click the tab again.</div>';
                    console.error('Patient tab load failed:', tab, e);
                }
            })();

            this.loadedTabs[tab] = inflight;
            return inflight;
        },

        /** Switch to a tab, wait for its fragment, then run a callback. */
        async openTabThen(tab, fn) {
            this.activeTab = tab;
            await this.ensureTab(tab);
            this.$nextTick(() => { if (fn) fn(); });
        },

        /** "New Visit" / "Add Follow-up" — open Visits tab, then its add form. */
        openVisitForm() {
            this.openTabThen('visits', () => window.dispatchEvent(new CustomEvent('open-visit-form')));
        },

        /** Quick action — open Membership tab, then the enroll modal. */
        openMembershipEnroll() {
            this.openTabThen('membership', () => document.getElementById('enrollModal')?.classList.remove('hidden'));
        },

        oppStageLabel(s) {
            return {prospect:'Identified',discussed:'Discussed',quoted:'Financial Discussion',accepted:'Planned',completed:'Completed'}[s] || s;
        },

        // Patient editing now opens the shared New/Edit Patient modal
        // (partials/add-patient-modal.blade.php) via the 'open-edit-patient'
        // window event — the old edit drawer and submitEditPatient() are gone.

        toggleNoteTag(t) {
            this.newNoteTags.includes(t)
                ? this.newNoteTags = this.newNoteTags.filter(x => x !== t)
                : this.newNoteTags.push(t);
        },

        async saveNote() {
            if (!this.newNote.trim()) return;
            this.noteSaving = true;
            this.noteSaveError = '';
            try {
                const r = await fetch(`/patients/{{ $patient->id }}/relationship-notes`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ note: this.newNote, tags: this.newNoteTags, type: this.noteType }),
                });
                if (!r.ok) {
                    const errBody = await r.text();
                    this.noteSaveError = `Save failed (${r.status}). Please try again.`;
                    console.error('saveNote HTTP error', r.status, errBody);
                    this.noteSaving = false;
                    return;
                }
                const d = await r.json();
                if (d.success && d.note) {
                    const newEntry = {
                        id: d.note.id,
                        note: d.note.note,
                        note_type: d.note.note_type || 'internal',
                        tags: Array.isArray(d.note.tags) ? d.note.tags : [],
                        created_at: d.note.created_at || new Date().toISOString(),
                    };
                    this.relationshipNotes.unshift(newEntry);
                    this.newNote = '';
                    this.newNoteTags = [];
                    this.showNoteForm = false;
                } else {
                    this.noteSaveError = 'Unexpected response from server.';
                }
            } catch(e) {
                this.noteSaveError = 'Network error. Please check your connection.';
                console.error('saveNote error', e);
            }
            this.noteSaving = false;
        },

        async deleteNote(id) {
            if (!confirm('Delete this note?')) return;
            const r = await fetch(`/patients/{{ $patient->id }}/relationship-notes/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
            });
            if ((await r.json()).success) {
                this.relationshipNotes = this.relationshipNotes.filter(n => n.id !== id);
            }
        },

        async saveOpportunity() {
            if (!this.newOpp.type) return;
            const r = await fetch(`/patients/{{ $patient->id }}/opportunities`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(this.newOpp),
            });
            const d = await r.json();
            if (d.success) {
                this.opportunities.unshift(d.opportunity);
                this.newOpp = { type:'', status:'prospect', priority:'medium', estimated_value:'', follow_up_date:'' };
                this.showOppForm = false;
            }
        },

        openOppEdit(opp) {
            this.oppEditId = opp.id;
            this.oppEditData = { ...opp };
        },

        cancelOppEdit() {
            this.oppEditId = null;
            this.oppEditData = {};
        },

        async saveOppEdit() {
            if (!this.oppEditData.type) return;
            this.oppSaving = true;
            try {
                const r = await fetch(`/patients/{{ $patient->id }}/opportunities/${this.oppEditId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ ...this.oppEditData, _method: 'PATCH' }),
                });
                const d = await r.json();
                if (d.success) {
                    const idx = this.opportunities.findIndex(o => o.id === this.oppEditId);
                    if (idx !== -1) this.opportunities[idx] = d.opportunity;
                    this.oppEditId = null;
                    this.oppEditData = {};
                }
            } catch(e) {
                console.error(e);
            } finally {
                this.oppSaving = false;
            }
        },

        async deleteOpp(id) {
            if (!confirm('Delete this opportunity?')) return;
            try {
                await fetch(`/patients/{{ $patient->id }}/opportunities/${id}`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ _method: 'DELETE' }),
                });
                this.opportunities = this.opportunities.filter(o => o.id !== id);
            } catch(e) {
                console.error(e);
            }
        },

        // Alias — some extracted markup calls deleteOpportunity(); keep both names.
        deleteOpportunity(id) { return this.deleteOpp(id); },
    }
}

/**
 * Open WhatsApp (click-to-chat) for this patient via the single consent-gated
 * endpoint. Context 'generic' = no pre-filled template; staff types their message.
 */
async function patientWhatsApp(patientId, btn) {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    if (btn) btn.disabled = true;
    try {
        const res = await fetch(@js(route('communication.whatsapp.link')), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            body: JSON.stringify({ context: 'generic', patient_id: patientId })
        });
        const data = await res.json();
        if (res.ok && data.success && data.url) { window.open(data.url, '_blank', 'noopener'); }
        else { alert(data.message || 'Could not open WhatsApp for this patient.'); }
    } catch (e) {
        alert('Could not reach WhatsApp send. Please try again.');
    } finally {
        if (btn) btn.disabled = false;
    }
}
</script>
@endpush
