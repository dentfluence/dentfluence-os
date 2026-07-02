{{--
| Lab Cases Tab — shown inside the patient profile (/patients/{id})
| Loaded via AJAX from LabController@patientCases
| Also rendered directly when the patient show page includes it statically.
--}}
<div
    x-show="activeTab === 'lab'"
    style="display:none"
    x-data="patientLabTab({{ $patient->id }}, {{ $cases->toJson() }})"
    class="w-full px-6 py-5 space-y-5"
>
    {{-- Header row --}}
    <div class="flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-800">Lab Cases</h3>
        <button
            @click="openDrawer()"
            dusk="lab-new"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-[#6a0f70] hover:bg-[#380740] text-white text-xs font-medium rounded-lg shadow-sm transition"
        >
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Lab Case
        </button>
    </div>

    {{-- Cases list --}}
    <template x-if="cases.length === 0">
        <div class="text-center py-12 text-gray-400 text-sm">
            No lab cases yet.
            <button @click="openDrawer()" class="ml-1 text-[#6a0f70] hover:underline">Add one</button>
        </div>
    </template>

    <div class="space-y-3">
        <template x-for="c in cases" :key="c.id">
            <div class="bg-white border border-gray-200 rounded-xl p-4 shadow-sm hover:border-[#d8b4e2] transition group">
                <div class="flex flex-wrap items-start justify-between gap-3">

                    {{-- Left: work details --}}
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-gray-800 text-sm" x-text="workTypeLabel(c.work_type)"></span>
                            <template x-if="c.work_subtype">
                                <span class="text-xs text-gray-400" x-text="'— ' + c.work_subtype"></span>
                            </template>
                        </div>
                        <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                            <template x-if="c.tooth_number">
                                <span>Tooth: <strong x-text="c.tooth_number"></strong></span>
                            </template>
                            <template x-if="c.shade">
                                <span>Shade: <strong x-text="c.shade"></strong></span>
                            </template>
                            <template x-if="c.lab_vendor">
                                <span>Lab: <strong x-text="c.lab_vendor"></strong></span>
                            </template>
                            <template x-if="c.lab_cost">
                                <span>Cost: <strong x-text="'Rs. ' + Number(c.lab_cost).toLocaleString('en-IN')"></strong></span>
                            </template>
                        </div>
                        <div class="flex flex-wrap gap-3 text-xs text-gray-400 mt-1">
                            <span>Sent: <span x-text="formatDate(c.sent_date)"></span></span>
                            <template x-if="c.expected_return_date">
                                <span :class="isOverdue(c) ? 'text-red-500 font-medium' : ''">
                                    Expected: <span x-text="formatDate(c.expected_return_date)"></span>
                                    <span x-show="isOverdue(c)">(overdue)</span>
                                </span>
                            </template>
                            <template x-if="c.received_date">
                                <span class="text-green-600">Received: <span x-text="formatDate(c.received_date)"></span></span>
                            </template>
                        </div>
                        <template x-if="c.instructions">
                            <p class="text-xs text-gray-500 mt-1 italic" x-text="'Instructions: ' + c.instructions"></p>
                        </template>
                    </div>

                    {{-- Right: status badge + quick actions --}}
                    <div class="flex flex-col items-end gap-2">
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                            :class="statusColor(c.status)"
                            x-text="statusLabel(c.status)"
                        ></span>

                        {{-- Quick status change --}}
                        <select
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1 focus:ring-brand-300 focus:outline-none opacity-0 group-hover:opacity-100 transition"
                            @change="quickStatus(c, $event.target.value)"
                            :value="c.status"
                        >
                            <option value="sent">Sent</option>
                            <option value="in_progress">In Progress</option>
                            <option value="received">Received</option>
                            <option value="rejected">Rejected</option>
                        </select>

                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                            <button
                                @click="editCase(c)"
                                class="text-xs text-[#6a0f70] hover:underline"
                            >Edit</button>
                            <button
                                @click="deleteCase(c)"
                                class="text-xs text-red-500 hover:underline"
                            >Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- ══ INLINE DRAWER (slides over the tab area) ══════════════════════ --}}
    <div x-show="drawerOpen" style="display:none"
        class="fixed inset-0 bg-black/40 z-40"
        @click.self="closeDrawer()">
    </div>

    <div
        x-show="drawerOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[95vw] max-w-lg max-h-[90vh] bg-white shadow-2xl z-50 flex flex-col overflow-hidden rounded-2xl"
        style="display:none"
    >
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <h2 class="text-lg font-semibold text-gray-800" x-text="editingId ? 'Edit Lab Case' : 'New Lab Case'"></h2>
            <button @click="closeDrawer()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-5">
            <form id="patient-lab-form" class="space-y-5" @submit.prevent="submitForm()">
                {{-- Hidden patient_id --}}
                <input type="hidden" name="patient_id" :value="patientId">

                {{-- Doctor --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Doctor</label>
                    <select name="doctor_id" x-model="form.doctor_id"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                        <option value="">— Select doctor —</option>
                        @foreach($doctors as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Work type + subtype --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Work Type *</label>
                        <select name="work_type" required x-model="form.work_type" @change="form.work_subtype = ''"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            <option value="">— Select —</option>
                            <option value="crown_bridge">Crown / Bridge</option>
                            <option value="denture">Denture / Partial</option>
                            <option value="implant">Implant Component</option>
                            <option value="ortho">Orthodontic / Aligner</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Subtype</label>
                        <select name="work_subtype" x-model="form.work_subtype"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            <option value="">— Select —</option>
                            <template x-for="sub in subtypesFor(form.work_type)" :key="sub">
                                <option :value="sub" x-text="sub"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Tooth & Shade --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Tooth Number</label>
                        <input type="text" name="tooth_number" placeholder="e.g. 14" x-model="form.tooth_number"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Shade</label>
                        <input type="text" name="shade" placeholder="e.g. A2" x-model="form.shade"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                </div>

                {{-- Lab vendor & cost --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Lab Vendor</label>
                        <input type="text" name="lab_vendor" placeholder="Lab name" x-model="form.lab_vendor"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Lab Cost (Rs. )</label>
                        <input type="number" name="lab_cost" placeholder="0.00" step="0.01" min="0" x-model="form.lab_cost"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Sent Date *</label>
                        <input type="date" name="sent_date" required x-model="form.sent_date"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Expected Return</label>
                        <input type="date" name="expected_return_date" x-model="form.expected_return_date"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Received Date</label>
                        <input type="date" name="received_date" x-model="form.received_date"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Status *</label>
                    <div class="flex gap-4 flex-wrap">
                        @foreach(['order_placed'=>'Order Placed','impression_sent'=>'Impression Sent','trial_received'=>'Trial Received','final_received'=>'Final Received','rejected'=>'Rejected'] as $val => $lbl)
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="status" value="{{ $val }}" x-model="form.status" class="text-[#6a0f70]">
                            <span class="text-sm text-gray-700">{{ $lbl }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Instructions --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Instructions to Lab</label>
                    <textarea name="instructions" rows="3" placeholder="Specific instructions for the lab…" x-model="form.instructions"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Internal Notes</label>
                    <textarea name="notes" rows="2" placeholder="Notes for clinic use only…" x-model="form.notes"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none resize-none"></textarea>
                </div>
            </form>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 shrink-0">
            <button @click="closeDrawer()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
            <button
                @click="submitForm()"
                :disabled="saving"
                dusk="lab-save"
                class="px-5 py-2 bg-[#6a0f70] hover:bg-[#380740] disabled:opacity-60 text-white text-sm font-medium rounded-lg shadow-sm transition"
            >
                <span x-text="saving ? 'Saving…' : (editingId ? 'Save Changes' : 'Create Lab Case')"></span>
            </button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function patientLabTab(patientId, initialCases) {
    return {
        patientId,
        cases: initialCases,
        drawerOpen: false,
        editingId: null,
        saving: false,

        form: {
            doctor_id: '', work_type: '', work_subtype: '', tooth_number: '',
            shade: '', lab_vendor: '', lab_cost: '',
            sent_date: new Date().toISOString().slice(0,10),
            expected_return_date: '', received_date: '',
            status: 'order_placed', instructions: '', notes: '',
        },

        subtypes: {
            crown_bridge: ['PFM','Zirconia','All-Ceramic (Emax)','Metal','Temporary'],
            denture:      ['Full Denture','Partial Denture (Metal)','Partial Denture (Acrylic)','Flexible Denture','Immediate Denture'],
            implant:      ['Implant Crown','Custom Abutment','Stock Abutment','Bar Overdenture'],
            ortho:        ['Study Model','Retainer (Hawley)','Retainer (Essix)','Night Guard','Aligner'],
        },

        subtypesFor(type) { return this.subtypes[type] ?? []; },

        openDrawer() {
            this.editingId = null;
            this.resetForm();
            this.drawerOpen = true;
        },

        editCase(c) {
            this.editingId = c.id;
            this.form = {
                doctor_id:            String(c.doctor_id ?? ''),
                work_type:            c.work_type ?? '',
                work_subtype:         c.work_subtype ?? '',
                tooth_number:         c.tooth_number ?? '',
                shade:                c.shade ?? '',
                lab_vendor:           c.lab_vendor ?? '',
                lab_cost:             c.lab_cost ?? '',
                sent_date:            c.sent_date ?? '',
                expected_return_date: c.expected_return_date ?? '',
                received_date:        c.received_date ?? '',
                status:               c.status ?? 'sent',
                instructions:         c.instructions ?? '',
                notes:                c.notes ?? '',
            };
            this.drawerOpen = true;
        },

        closeDrawer() { this.drawerOpen = false; },

        resetForm() {
            this.form = {
                doctor_id:'', work_type:'', work_subtype:'', tooth_number:'',
                shade:'', lab_vendor:'', lab_cost:'',
                sent_date: new Date().toISOString().slice(0,10),
                expected_return_date:'', received_date:'',
                status:'order_placed', instructions:'', notes:'',
            };
        },

        async submitForm() {
            this.saving = true;
            const url    = this.editingId ? `/lab/${this.editingId}` : `/patients/${this.patientId}/lab-cases`;
            const method = this.editingId ? 'PUT' : 'POST';
            const body   = { ...this.form, patient_id: this.patientId, _token: document.querySelector('meta[name=csrf-token]').content };

            try {
                const res = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': body._token },
                    body: JSON.stringify(body),
                });
                if (!res.ok) throw new Error('Server error');
                // Reload list from server
                const listRes = await fetch(`/patients/${this.patientId}/lab-cases`, { headers: { 'Accept': 'application/json' } });
                // Re-fetch rendered partial via page reload of just the data
                window.location.reload();
            } catch (e) {
                alert('Something went wrong. Please try again.');
            } finally {
                this.saving = false;
            }
        },

        async quickStatus(c, newStatus) {
            const token = document.querySelector('meta[name=csrf-token]').content;
            await fetch(`/lab/${c.id}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ status: newStatus, _token: token }),
            });
            c.status = newStatus;
        },

        async deleteCase(c) {
            if (!confirm('Delete this lab case?')) return;
            const token = document.querySelector('meta[name=csrf-token]').content;
            await fetch(`/lab/${c.id}`, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                body: JSON.stringify({ _token: token }),
            });
            this.cases = this.cases.filter(x => x.id !== c.id);
        },

        // ── Helpers ─────────────────────────────────────────────────────────

        workTypeLabel(type) {
            return { crown_bridge:'Crown / Bridge', denture:'Denture / Partial', implant:'Implant Component', ortho:'Orthodontic / Aligner' }[type] ?? type;
        },

        statusLabel(s) {
            return { sent:'Sent', in_progress:'In Progress', received:'Received', rejected:'Rejected' }[s] ?? s;
        },

        statusColor(s) {
            return {
                sent:        'bg-blue-100 text-blue-700',
                in_progress: 'bg-yellow-100 text-yellow-700',
                received:    'bg-green-100 text-green-700',
                rejected:    'bg-red-100 text-red-700',
            }[s] ?? 'bg-gray-100 text-gray-700';
        },

        formatDate(d) {
            if (!d) return '';
            const dt = new Date(d);
            return dt.toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });
        },

        isOverdue(c) {
            if (!c.expected_return_date || c.received_date) return false;
            return new Date(c.expected_return_date) < new Date();
        },
    };
}
</script>
@endpush
