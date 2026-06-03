@extends('layouts.app')
@section('page-title', 'Lab Cases')

@section('content')
<div
    x-data="labModule()"
    class="p-6 space-y-6"
>

    {{-- ══ HEADER ══════════════════════════════════════════════════════════ --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-brand-700">Lab Cases</h1>
            <p class="text-sm text-gray-500 mt-0.5">Track dental lab work sent to external labs</p>
        </div>
        <button
            @click="openDrawer()"
            class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg shadow-sm transition"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            New Lab Case
        </button>
    </div>

    {{-- ══ FLASH ════════════════════════════════════════════════════════════ --}}
    @if(session('success'))
    <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        {{ session('success') }}
    </div>
    @endif

    {{-- ══ FILTER TABS + SEARCH ════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 p-4 border-b border-gray-100">

            {{-- Status tabs --}}
            <div class="flex gap-1 flex-wrap">
                @php
                $tabs = [
                    ['key'=>'all',         'label'=>'All'],
                    ['key'=>'sent',        'label'=>'Sent'],
                    ['key'=>'in_progress', 'label'=>'In Progress'],
                    ['key'=>'received',    'label'=>'Received'],
                    ['key'=>'rejected',    'label'=>'Rejected'],
                ];
                @endphp
                @foreach($tabs as $tab)
                <a
                    href="{{ route('lab.index', array_merge(request()->query(), ['status' => $tab['key']])) }}"
                    class="px-3 py-1.5 rounded-lg text-sm font-medium transition
                        {{ $status === $tab['key']
                            ? 'bg-brand-100 text-brand-700'
                            : 'text-gray-500 hover:bg-gray-50' }}"
                >
                    {{ $tab['label'] }}
                    <span class="ml-1 text-xs font-semibold {{ $status === $tab['key'] ? 'text-brand-500' : 'text-gray-400' }}">
                        {{ $counts[$tab['key']] }}
                    </span>
                </a>
                @endforeach
            </div>

            {{-- Search --}}
            <form method="GET" class="flex items-center gap-2">
                <input type="hidden" name="status" value="{{ $status }}">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Search patient, lab vendor…"
                    class="w-56 px-3 py-1.5 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-brand-300 focus:outline-none"
                >
                <button type="submit" class="px-3 py-1.5 text-sm bg-gray-100 hover:bg-gray-200 rounded-lg transition">Search</button>
            </form>
        </div>

        {{-- ══ TABLE ══════════════════════════════════════════════════════ --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs font-semibold text-gray-400 uppercase tracking-wide border-b border-gray-100">
                        <th class="px-4 py-3 text-left">Patient</th>
                        <th class="px-4 py-3 text-left">Work Type</th>
                        <th class="px-4 py-3 text-left">Tooth / Shade</th>
                        <th class="px-4 py-3 text-left">Lab Vendor</th>
                        <th class="px-4 py-3 text-left">Sent</th>
                        <th class="px-4 py-3 text-left">Expected</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-left">Cost</th>
                        <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @forelse($cases as $case)
                    <tr class="hover:bg-gray-50 group">
                        <td class="px-4 py-3">
                            <a href="{{ route('patients.show', $case->patient_id) }}"
                               class="font-medium text-brand-700 hover:underline">
                                {{ $case->patient->name ?? '—' }}
                            </a>
                            @if($case->doctor)
                            <div class="text-xs text-gray-400">Dr. {{ $case->doctor->name }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="font-medium text-gray-700">{{ $case->workTypeLabel() }}</span>
                            @if($case->work_subtype)
                            <div class="text-xs text-gray-400">{{ $case->work_subtype }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $case->tooth_number ?: '—' }}
                            @if($case->shade)
                            <span class="ml-1 text-xs text-gray-400">{{ $case->shade }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ $case->lab_vendor ?: '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $case->sent_date?->format('d M Y') }}</td>
                        <td class="px-4 py-3">
                            @if($case->expected_return_date)
                                @php $overdue = !$case->received_date && $case->expected_return_date->isPast(); @endphp
                                <span class="{{ $overdue ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                                    {{ $case->expected_return_date->format('d M Y') }}
                                    @if($overdue) <span class="text-xs">(overdue)</span> @endif
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $case->statusColor() }}">
                                {{ ucfirst(str_replace('_', ' ', $case->status)) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            {{ $case->lab_cost ? '₹' . number_format($case->lab_cost, 0) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition">
                                <button
                                    @click="editCase({{ $case->toJson() }})"
                                    class="text-xs px-2 py-1 bg-brand-50 text-brand-700 rounded hover:bg-brand-100 transition"
                                >Edit</button>
                                <form method="POST" action="{{ route('lab.destroy', $case) }}"
                                      onsubmit="return confirm('Delete this lab case?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-xs px-2 py-1 bg-red-50 text-red-600 rounded hover:bg-red-100 transition">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="px-4 py-16 text-center">
                            <div class="text-gray-400 text-sm">No lab cases found.</div>
                            <button @click="openDrawer()" class="mt-3 text-brand-600 text-sm hover:underline">
                                + Create your first lab case
                            </button>
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($cases->hasPages())
        <div class="px-4 py-3 border-t border-gray-100">
            {{ $cases->links() }}
        </div>
        @endif
    </div>

    {{-- ══ DRAWER BACKDROP ════════════════════════════════════════════════ --}}
    <div
        x-show="drawerOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-black/40 z-40"
        @click.self="closeDrawer()"
        style="display:none"
    ></div>

    {{-- ══ DRAWER PANEL ═══════════════════════════════════════════════════ --}}
    <div
        x-show="drawerOpen"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 h-full w-full max-w-lg bg-white shadow-2xl z-50 flex flex-col overflow-hidden"
        style="display:none"
    >
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
            <h2 class="text-lg font-semibold text-gray-800" x-text="editingId ? 'Edit Lab Case' : 'New Lab Case'"></h2>
            <button @click="closeDrawer()" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>

        <div class="flex-1 overflow-y-auto px-6 py-5">
            <form
                :action="editingId ? '/lab/' + editingId : '/lab'"
                method="POST"
                id="lab-form"
                class="space-y-5"
            >
                @csrf
                <input type="hidden" name="_method" :value="editingId ? 'PUT' : 'POST'">

                {{-- Patient --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Patient *</label>
                    <select name="patient_id" required x-model="form.patient_id"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                        <option value="">— Select patient —</option>
                        @foreach($patients as $p)
                        <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->phone }})</option>
                        @endforeach
                    </select>
                </div>

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
                        <input type="text" name="tooth_number" placeholder="e.g. 14, 14-16" x-model="form.tooth_number"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Shade</label>
                        <input type="text" name="shade" placeholder="e.g. A2, B1" x-model="form.shade"
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
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Lab Cost (₹)</label>
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
                        @foreach(['sent'=>'Sent','in_progress'=>'In Progress','received'=>'Received','rejected'=>'Rejected'] as $val => $label)
                        <label class="flex items-center gap-1.5 cursor-pointer">
                            <input type="radio" name="status" value="{{ $val }}" x-model="form.status" class="text-brand-600">
                            <span class="text-sm text-gray-700">{{ $label }}</span>
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

                {{-- Internal notes --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Internal Notes</label>
                    <textarea name="notes" rows="2" placeholder="Notes for clinic use only…" x-model="form.notes"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-300 focus:outline-none resize-none"></textarea>
                </div>
            </form>
        </div>

        <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3 shrink-0">
            <button @click="closeDrawer()" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</button>
            <button type="submit" form="lab-form"
                class="px-5 py-2 bg-brand-600 hover:bg-brand-700 text-white text-sm font-medium rounded-lg shadow-sm transition">
                <span x-text="editingId ? 'Save Changes' : 'Create Lab Case'"></span>
            </button>
        </div>
    </div>

</div>
@endsection

@section('head-extra')
<script>
function labModule() {
    return {
        drawerOpen: false,
        editingId: null,
        form: {
            patient_id: '', doctor_id: '', work_type: '', work_subtype: '',
            tooth_number: '', shade: '', lab_vendor: '', lab_cost: '',
            sent_date: '{{ now()->format("Y-m-d") }}',
            expected_return_date: '', received_date: '',
            status: 'sent', instructions: '', notes: '',
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
                patient_id:           String(c.patient_id ?? ''),
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
                patient_id:'', doctor_id:'', work_type:'', work_subtype:'',
                tooth_number:'', shade:'', lab_vendor:'', lab_cost:'',
                sent_date:'{{ now()->format("Y-m-d") }}',
                expected_return_date:'', received_date:'',
                status:'sent', instructions:'', notes:'',
            };
        },
    };
}
</script>
@endsection
