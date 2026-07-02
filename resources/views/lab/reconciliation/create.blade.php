@extends('layouts.app')
@section('page-title', 'New Lab Reconciliation')

@section('content')
<div class="p-6 max-w-5xl mx-auto space-y-6" x-data="reconciliationCreate()">

    {{-- HEADER --}}
    <div>
        <p class="text-xs text-gray-400 uppercase tracking-widest">
            <a href="{{ route('lab.reconciliation.index') }}" class="hover:text-indigo-600">Reconciliation</a>
            &nbsp;/&nbsp; New
        </p>
        <h1 class="text-2xl font-semibold text-indigo-700 mt-0.5" style="font-family:'Cormorant Garamond',serif;">
            Start Monthly Reconciliation
        </h1>
    </div>

    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm space-y-1">
            @foreach($errors->all() as $e)<p>• {{ $e }}</p>@endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('lab.reconciliation.store') }}" x-ref="form">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">

            {{-- LEFT: Step 1 — period + vendor --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-4">
                <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-widest">1 · Period & Vendor</h2>

                <div>
                    <label class="block text-xs text-gray-500 mb-1">Lab Vendor <span class="text-red-500">*</span></label>
                    <select name="lab_vendor_id" x-model="vendorId" required
                            @change="loadCases()"
                            class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">— Select vendor —</option>
                        @foreach($vendors as $v)
                            <option value="{{ $v->id }}" @selected(old('lab_vendor_id', $selectedVendor?->id) == $v->id)>{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Billing Month <span class="text-red-500">*</span></label>
                        <select name="billing_month" x-model="month" @change="loadCases()" required
                                class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach(range(1,12) as $m)
                                <option value="{{ $m }}" @selected(old('billing_month', $defaultMonth) == $m)>
                                    {{ date('F', mktime(0,0,0,$m,1)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Year <span class="text-red-500">*</span></label>
                        <select name="billing_year" x-model="year" @change="loadCases()" required
                                class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @foreach(range(now()->year, 2023) as $y)
                                <option value="{{ $y }}" @selected(old('billing_year', $defaultYear) == $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="border-t border-gray-100 pt-4">
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-widest mb-3">Vendor Bill Details</h3>
                    <div class="space-y-3">
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Vendor Bill Number</label>
                            <input type="text" name="vendor_bill_number" value="{{ old('vendor_bill_number') }}"
                                   class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                                   placeholder="e.g. INV-2024-001">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Vendor Bill Date</label>
                            <input type="date" name="vendor_bill_date" value="{{ old('vendor_bill_date') }}"
                                   class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Vendor Total (Rs. ) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" name="vendor_total" x-model="vendorTotal"
                                   value="{{ old('vendor_total') }}" required
                                   class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                                   placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Notes</label>
                            <textarea name="notes" rows="2"
                                      class="w-full text-sm border-gray-200 rounded-lg px-3 py-2 focus:ring-indigo-500 focus:border-indigo-500"
                                      placeholder="Optional notes...">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            {{-- RIGHT: Step 2 — cases --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-700 uppercase tracking-widest">2 · Select Cases</h2>
                    <div x-show="loading" class="text-xs text-gray-400">Loading…</div>
                </div>

                {{-- Summary bar --}}
                <div x-show="cases.length > 0" class="bg-indigo-50 rounded-lg px-4 py-2 text-sm flex flex-wrap gap-4">
                    <span class="text-gray-600">Cases: <strong x-text="selectedCount"></strong></span>
                    <span class="text-gray-600">Our Total: <strong class="text-indigo-700" x-text="'Rs. ' + ourTotal.toFixed(2)"></strong></span>
                    <span class="text-gray-600" x-show="vendorTotal > 0">
                        Diff: <strong :class="Math.abs(ourTotal - parseFloat(vendorTotal)) < 1 ? 'text-green-600' : 'text-red-600'"
                                     x-text="'Rs. ' + (parseFloat(vendorTotal || 0) - ourTotal).toFixed(2)"></strong>
                    </span>
                </div>

                {{-- No cases message --}}
                <div x-show="!loading && cases.length === 0" class="text-center py-8 text-gray-400 text-sm">
                    <p x-show="vendorId">No unbilled cases found for this vendor + period.</p>
                    <p x-show="!vendorId">Select a vendor and period to load cases.</p>
                </div>

                {{-- Cases list --}}
                <div x-show="cases.length > 0" class="space-y-2 max-h-80 overflow-y-auto pr-1">
                    <template x-for="(c, idx) in cases" :key="c.id">
                        <div class="border border-gray-100 rounded-lg p-3 text-sm hover:border-indigo-200 transition">
                            <div class="flex items-start gap-2">
                                <input type="checkbox" :name="'case_ids[' + idx + ']'" :value="c.id"
                                       x-model="c.selected" @change="recalc()"
                                       class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <div class="flex-1">
                                    <div class="flex justify-between">
                                        <span class="font-mono text-xs text-gray-500" x-text="c.case_number"></span>
                                        <span class="text-xs text-gray-500" x-text="c.received_date"></span>
                                    </div>
                                    <p class="font-medium text-gray-800 text-xs" x-text="c.patient || 'Unknown Patient'"></p>
                                    <p class="text-xs text-gray-500" x-text="(c.work_category || '') + (c.work_subtype ? ' · ' + c.work_subtype : '')"></p>
                                    <div class="flex items-center gap-3 mt-1">
                                        <span class="text-xs text-gray-500">Our: <strong x-text="'Rs. ' + parseFloat(c.lab_cost || 0).toFixed(2)"></strong></span>
                                        <span class="text-xs text-gray-500">Vendor:</span>
                                        <input type="number" step="0.01"
                                               :name="'vendor_amounts[' + idx + ']'"
                                               x-model="c.vendor_amount"
                                               @input="recalc()"
                                               :disabled="!c.selected"
                                               class="w-24 text-xs border-gray-200 rounded px-2 py-1 disabled:bg-gray-50 disabled:text-gray-400 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Select all --}}
                <div x-show="cases.length > 0" class="text-right">
                    <button type="button" @click="toggleAll()" class="text-xs text-indigo-600 hover:underline" x-text="allSelected ? 'Deselect All' : 'Select All'"></button>
                </div>
            </div>
        </div>

        {{-- SUBMIT --}}
        <div class="flex justify-end gap-3 pt-2">
            <a href="{{ route('lab.reconciliation.index') }}" class="px-4 py-2 text-sm text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</a>
            <button type="submit" :disabled="selectedCount === 0"
                    class="px-6 py-2 text-sm bg-indigo-600 text-white font-medium rounded-lg shadow hover:bg-indigo-700 disabled:opacity-50 transition">
                Create Reconciliation
            </button>
        </div>
    </form>
</div>

@push('scripts')
<script>
function reconciliationCreate() {
    return {
        vendorId: '{{ old('lab_vendor_id', $selectedVendor?->id ?? '') }}',
        month:    '{{ old('billing_month', $defaultMonth) }}',
        year:     '{{ old('billing_year', $defaultYear) }}',
        vendorTotal: '{{ old('vendor_total', '') }}',
        cases:    [],
        loading:  false,
        ourTotal: 0,
        selectedCount: 0,
        get allSelected() { return this.cases.length > 0 && this.cases.every(c => c.selected); },

        async loadCases() {
            if (!this.vendorId || !this.month || !this.year) { this.cases = []; return; }
            this.loading = true;
            try {
                const url = `{{ route('lab.reconciliation.eligible-cases') }}?vendor_id=${this.vendorId}&billing_month=${this.month}&billing_year=${this.year}`;
                const r   = await fetch(url, {headers: {'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json'}});
                const data = await r.json();
                this.cases = data.map(c => ({...c, selected: true, vendor_amount: c.lab_cost || 0}));
                this.recalc();
            } catch(e) { console.error(e); this.cases = []; }
            this.loading = false;
        },

        recalc() {
            this.ourTotal      = this.cases.filter(c => c.selected).reduce((s, c) => s + parseFloat(c.lab_cost || 0), 0);
            this.selectedCount = this.cases.filter(c => c.selected).length;
        },

        toggleAll() {
            const all = this.allSelected;
            this.cases.forEach(c => c.selected = !all);
            this.recalc();
        },

        init() {
            if (this.vendorId) { this.loadCases(); }
        }
    }
}
</script>
@endpush
@endsection
