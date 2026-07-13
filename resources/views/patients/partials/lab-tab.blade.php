{{--
| Lab Cases Tab — shown inside the patient profile (/patients/{id})
| This is the "full detail" lab case form (tooth chart + shade guide +
| real vendor dropdown), as opposed to the Treatment Visit tab's quick-add
| lab card. Both write to the same LabCase/LabCaseItem tables via
| LabController — see partials.tooth-chart / partials.shade-select for the
| shared tooth+shade UI also used on the main /lab page.
--}}
@include('partials.tooth-chart-assets')
@include('partials.shade-select-assets')

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
                            <span class="text-xs font-mono text-gray-400" x-text="c.case_number"></span>
                            <span class="font-semibold text-gray-800 text-sm" x-text="c.work_category || '—'"></span>
                            <template x-if="c.work_subtype">
                                <span class="text-xs text-gray-400" x-text="'— ' + c.work_subtype"></span>
                            </template>
                        </div>
                        <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                            <template x-if="toothSummary(c)">
                                <span>Tooth: <strong x-text="toothSummary(c)"></strong></span>
                            </template>
                            <template x-if="shadeSummary(c)">
                                <span>Shade: <strong x-text="shadeSummary(c)"></strong></span>
                            </template>
                            <template x-if="c.vendor">
                                <span>Lab: <strong x-text="c.vendor.name"></strong></span>
                            </template>
                            <template x-if="c.lab_cost">
                                <span>Cost: <strong x-text="'Rs. ' + Number(c.lab_cost).toLocaleString('en-IN')"></strong></span>
                            </template>
                        </div>
                        <div class="flex flex-wrap gap-3 text-xs text-gray-400 mt-1">
                            <template x-if="c.sent_date">
                                <span>Sent: <span x-text="formatDate(c.sent_date)"></span></span>
                            </template>
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

                        {{-- One-click next-step transitions (mirrors /lab) --}}
                        <div class="flex gap-1 flex-wrap justify-end opacity-0 group-hover:opacity-100 transition">
                            <template x-for="next in nextStatuses(c.status)" :key="next">
                                <button @click="transition(c, next)"
                                    class="text-[10px] px-2 py-0.5 rounded border border-gray-200 text-gray-600 hover:bg-gray-50">
                                    <span x-text="statusLabel(next)"></span>
                                </button>
                            </template>
                        </div>

                        <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition">
                            <a :href="'/lab/' + c.id" class="text-xs text-gray-500 hover:underline">View</a>
                            <button @click="editCase(c)" class="text-xs text-[#6a0f70] hover:underline">Edit</button>
                            <button @click="deleteCase(c)" class="text-xs text-red-500 hover:underline">Delete</button>
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
                {{-- Doctor --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Doctor</label>
                    <select x-model="form.doctor_id"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                        <option value="">— Select doctor —</option>
                        @foreach($doctors as $d)
                        <option value="{{ $d->id }}">{{ $d->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Work category + subtype --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Work Category *</label>
                        <select required x-model="form.work_category" @change="form.work_subtype = ''; autoFillCost()"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            <option value="">— Select category —</option>
                            @foreach(\App\Models\LabCase::WORK_CATEGORIES as $cat => $subs)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Subtype / Material</label>
                        <select x-model="form.work_subtype" :disabled="!form.work_category" @change="autoFillCost()"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            <option value="">— Select —</option>
                            <template x-for="sub in subtypesFor(form.work_category)" :key="sub">
                                <option :value="sub" x-text="sub"></option>
                            </template>
                        </select>
                    </div>
                </div>

                {{-- Tooth chart --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Tooth Selection</label>
                    @include('partials.tooth-chart', ['target' => 'form.item', 'pickerId' => "'lab'"])
                </div>

                {{-- Shade guide --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Shade</label>
                    @include('partials.shade-select', ['target' => 'form.item'])
                </div>

                {{-- Lab vendor & priority --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Lab Vendor</label>
                        <select x-model="form.lab_vendor_id" @change="autoFillCost()"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                            <option value="">— Select Lab —</option>
                            @foreach($vendors as $vnd)
                            <option value="{{ $vnd->id }}">{{ $vnd->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Priority</label>
                        <div class="flex gap-1.5">
                            <template x-for="p in ['routine','urgent','express']" :key="p">
                                <button type="button" @click="form.priority = p"
                                    :class="form.priority === p ? 'bg-[#6a0f70] text-white border-[#6a0f70]' : 'bg-white text-gray-600 border-gray-200'"
                                    class="flex-1 text-xs font-medium py-2 rounded-lg border capitalize transition" x-text="p"></button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Cost --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Lab Cost (Rs. )</label>
                    <input type="number" placeholder="0.00" step="0.01" min="0" x-model="form.lab_cost"
                        @input="costAutoFilled = false"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    <p x-show="costAutoFilled" class="text-[10px] text-gray-400 mt-0.5">From the vendor's price list — edit if this job differs.</p>
                </div>

                {{-- Dates --}}
                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Sent Date</label>
                        <input type="date" x-model="form.sent_date"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Expected Return</label>
                        <input type="date" x-model="form.expected_return_date"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Received Date</label>
                        <input type="date" x-model="form.received_date"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                </div>

                {{-- Status --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Status *</label>
                    <select x-model="form.status"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                        @foreach(\App\Models\LabCase::STATUS_LABELS as $val => $lbl)
                        <option value="{{ $val }}">{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Instructions --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Instructions to Lab</label>
                    <textarea rows="3" placeholder="Specific instructions for the lab…" x-model="form.instructions"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none resize-none"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Internal Notes</label>
                    <textarea rows="2" placeholder="Notes for clinic use only…" x-model="form.internal_notes"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none resize-none"></textarea>
                </div>

                <template x-if="formError">
                    <p class="text-xs text-red-500" x-text="formError"></p>
                </template>
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
// Vendor id => active services ([{category, service_name, default_rate}]) — used to
// auto-fill the lab cost from the vendor's own price list instead of typing it blind.
const LAB_VENDOR_SERVICES = @json($vendors->mapWithKeys(fn ($v) => [
    $v->id => $v->services->map(fn ($s) => [
        'category'     => $s->category,
        'service_name' => $s->service_name,
        'default_rate' => (float) $s->default_rate,
    ]),
]));

function patientLabTab(patientId, initialCases) {
    return {
        ...toothChartMixin(),
        ...shadeSelectMixin(),

        patientId,
        cases: initialCases,
        drawerOpen: false,
        editingId: null,
        saving: false,
        formError: '',
        costAutoFilled: false,

        form: {},

        subtypes: @json(\App\Models\LabCase::WORK_CATEGORIES),
        subtypesFor(cat) { return this.subtypes[cat] ?? []; },

        // Look up the vendor's priced service for the selected category/subtype and
        // fill the cost field — only while the field is still empty or holds a value
        // we auto-filled ourselves, so it never overwrites a manual edit.
        autoFillCost() {
            if (!this.form.lab_vendor_id || !this.form.work_category) return;
            if (this.form.lab_cost && !this.costAutoFilled) return;

            const services = LAB_VENDOR_SERVICES[this.form.lab_vendor_id] || [];
            const match = services.find(s => s.category === this.form.work_category
                    && (!this.form.work_subtype || s.service_name === this.form.work_subtype))
                || services.find(s => s.category === this.form.work_category);

            if (match) {
                this.form.lab_cost = match.default_rate;
                this.costAutoFilled = true;
            }
        },

        _blankForm() {
            return {
                doctor_id: '', work_category: '', work_subtype: '',
                lab_vendor_id: '', priority: 'routine', lab_cost: '',
                sent_date: new Date().toISOString().slice(0, 10),
                expected_return_date: '', received_date: '',
                status: 'draft', instructions: '', internal_notes: '',
                item: { teeth: [], tooth_number: '', shade_guide: 'vita_classical', shade: '', per_tooth_shade: false, tooth_shades: {} },
            };
        },

        openDrawer() {
            this.editingId = null;
            this.formError = '';
            this.costAutoFilled = false;
            this.form = this._blankForm();
            this.drawerOpen = true;
        },

        editCase(c) {
            this.editingId = c.id;
            this.formError = '';
            this.costAutoFilled = false;
            this.form = {
                doctor_id:            c.doctor_id ? String(c.doctor_id) : '',
                work_category:        c.work_category ?? '',
                work_subtype:         c.work_subtype ?? '',
                lab_vendor_id:        c.lab_vendor_id ? String(c.lab_vendor_id) : '',
                priority:             c.priority ?? 'routine',
                lab_cost:             c.lab_cost ?? '',
                sent_date:            c.sent_date ?? '',
                expected_return_date: c.expected_return_date ?? '',
                received_date:        c.received_date ?? '',
                status:               c.status ?? 'draft',
                instructions:         c.instructions ?? '',
                internal_notes:       c.internal_notes ?? '',
                item: itemStateFromCaseItems(c.items),
            };
            this.drawerOpen = true;
        },

        closeDrawer() { this.drawerOpen = false; },

        async submitForm() {
            this.saving = true;
            this.formError = '';
            const url    = this.editingId ? `/lab/${this.editingId}` : `/patients/${this.patientId}/lab-cases`;
            const method = this.editingId ? 'PUT' : 'POST';
            const items  = itemsPayloadFromState(this.form.item, this.form.work_subtype, this.form.work_subtype);
            const token  = document.querySelector('meta[name=csrf-token]').content;

            const body = {
                ...this.form,
                patient_id: this.patientId,
                items,
            };
            delete body.item;

            try {
                const res = await fetch(url, {
                    method,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify(body),
                });
                if (!res.ok) {
                    const payload = await res.json().catch(() => null);
                    throw new Error(payload?.message || 'Server error');
                }
                window.location.reload();
            } catch (e) {
                this.formError = e.message || 'Something went wrong. Please try again.';
            } finally {
                this.saving = false;
            }
        },

        async transition(c, toStatus) {
            const token = document.querySelector('meta[name=csrf-token]').content;
            const res = await fetch(`/lab/${c.id}/status/${toStatus}`, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
            });
            if (res.ok) c.status = toStatus;
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

        statusLabel(s) {
            return ({!! json_encode(\App\Models\LabCase::STATUS_LABELS) !!})[s] ?? s;
        },

        statusColor(s) {
            return ({!! json_encode(\App\Models\LabCase::STATUS_COLORS) !!})[s] ?? 'bg-gray-100 text-gray-700';
        },

        nextStatuses(status) {
            return ({!! json_encode(\App\Models\LabCase::STATUS_FLOW) !!})[status] ?? [];
        },

        toothSummary(c) {
            return (c.items || []).map(i => i.tooth_number).filter(Boolean).join(', ');
        },

        shadeSummary(c) {
            const shades = [...new Set((c.items || []).map(i => i.shade).filter(Boolean))];
            return shades.join(', ');
        },

        formatDate(d) {
            if (!d) return '';
            const dt = new Date(d);
            return dt.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
        },

        isOverdue(c) {
            if (!c.expected_return_date || c.received_date || c.final_received_date) return false;
            return new Date(c.expected_return_date) < new Date();
        },
    };
}
</script>
@endpush
