@extends('layouts.app')

@section('page-title', 'Merge Duplicate')

@section('content')
<div class="max-w-3xl mx-auto p-4" x-data="mergeWizard()">

    <div class="mb-4">
        <a href="{{ route('patients.show', $master) }}" class="text-xs text-gray-500 hover:underline">&larr; Back to profile</a>
        <h1 class="text-lg font-semibold text-gray-800 mt-1">Merge a duplicate record</h1>
        <p class="text-sm text-gray-500">Move another record's entire history into this one. The other record is archived.</p>
    </div>

    {{-- Master (surviving) --}}
    <div class="border border-gray-200 rounded bg-white p-3 mb-4">
        <div class="text-[11px] uppercase tracking-wide text-emerald-600 font-semibold mb-1">Surviving record (master)</div>
        <div class="font-medium text-gray-800">{{ $master->name }}</div>
        <div class="text-xs text-gray-500">{{ $master->patient_id }} · {{ $master->phone }}</div>
    </div>

    {{-- Loser search --}}
    <div class="border border-gray-200 rounded bg-white p-3 mb-4">
        <div class="text-[11px] uppercase tracking-wide text-red-500 font-semibold mb-2">Duplicate to merge in (loser)</div>

        <template x-if="!loser">
            <div class="relative">
                <input type="text" x-model="query" @input.debounce.300ms="search()"
                       placeholder="Search the duplicate by name, ID or phone…"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                <div x-show="results.length" class="absolute z-20 left-0 right-0 mt-1 bg-white border border-gray-200 rounded shadow max-h-64 overflow-auto">
                    <template x-for="r in results" :key="r.id">
                        <button type="button" @click="pick(r)" class="w-full text-left px-3 py-2 text-sm hover:bg-gray-50 border-b border-gray-50">
                            <span class="font-medium text-gray-800" x-text="r.name"></span>
                            <span class="text-xs text-gray-500 block" x-text="r.meta"></span>
                        </button>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="loser">
            <div class="flex items-center justify-between">
                <div>
                    <div class="font-medium text-gray-800" x-text="loser.name"></div>
                    <div class="text-xs text-gray-500"><span x-text="loser.patient_id"></span> · <span x-text="loser.phone"></span></div>
                </div>
                <button type="button" @click="clearLoser()" class="text-xs text-gray-500 hover:underline">Change</button>
            </div>
        </template>

        <p x-show="error" x-text="error" class="text-xs text-red-600 mt-2"></p>
    </div>

    {{-- Preview of what moves --}}
    <div x-show="preview" x-cloak class="border border-gray-200 rounded bg-white p-3 mb-4">
        <div class="text-[11px] uppercase tracking-wide text-gray-500 font-semibold mb-2">Records that will move to the master</div>
        <div class="text-sm text-gray-700 mb-2">
            <span class="font-semibold" x-text="preview ? preview.total : 0"></span> records across
            <span x-text="preview ? (Object.keys(preview.children).length + Object.keys(preview.money).length + Object.keys(preview.special).length) : 0"></span> tables,
            plus <span x-text="preview ? preview.relationship : 0"></span> timeline/relationship entries.
        </div>
        <div class="grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-gray-600">
            <template x-for="(n, t) in mergedRows()" :key="t">
                <div class="flex justify-between border-b border-gray-50 py-0.5">
                    <span x-text="t"></span><span class="font-medium" x-text="n"></span>
                </div>
            </template>
        </div>
    </div>

    {{-- Confirmation --}}
    <form method="POST" action="{{ route('patients.merge.store', $master) }}" x-show="loser" x-cloak
          class="border border-red-200 rounded bg-red-50/40 p-3">
        @csrf
        <input type="hidden" name="loser_id" :value="loser ? loser.id : ''">

        <div class="text-[11px] uppercase tracking-wide text-red-600 font-semibold mb-2">Confirm merge</div>
        <p class="text-xs text-gray-600 mb-3">
            This moves the duplicate's full history into <span class="font-medium">{{ $master->name }}</span> and archives it.
            Allergies and medical alerts are combined; wallet balances are added together.
        </p>

        @error('loser_id')<p class="text-xs text-red-600 mb-2">{{ $message }}</p>@enderror

        {{-- Field-by-field reconciliation (only fields where the two disagree) --}}
        <template x-if="diffs.length">
            <div class="mb-3 bg-white border border-gray-200 rounded p-2">
                <div class="text-[11px] uppercase tracking-wide text-gray-500 font-semibold mb-2">Resolve differences</div>
                <template x-for="d in diffs" :key="d.field">
                    <div class="mb-2 border-b border-gray-100 pb-2 last:border-0 last:pb-0">
                        <div class="text-xs font-medium text-gray-700 mb-1" x-text="d.label"></div>
                        <label class="flex items-start gap-2 text-xs text-gray-700 mb-1 cursor-pointer">
                            <input type="radio" :name="`choices[${d.field}]`" :value="d.master_raw" checked class="mt-0.5">
                            <span>Keep master: <span class="font-medium" x-text="d.master"></span></span>
                        </label>
                        <label class="flex items-start gap-2 text-xs text-gray-700 cursor-pointer">
                            <input type="radio" :name="`choices[${d.field}]`" :value="d.loser_raw" class="mt-0.5">
                            <span>Use duplicate: <span class="font-medium" x-text="d.loser"></span></span>
                        </label>
                    </div>
                </template>
            </div>
        </template>
        <p x-show="loser && !diffs.length" class="text-xs text-gray-500 mb-3">No conflicting fields — the records agree on all details.</p>

        <label class="block text-xs text-gray-600 mb-1">Reason (recorded on the audit trail)</label>
        <input type="text" name="reason" value="{{ old('reason') }}" required minlength="5" maxlength="500"
               class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-3" placeholder="e.g. Same patient registered twice by reception">

        <label class="block text-xs text-gray-600 mb-1">Your password</label>
        <input type="password" name="password" required
               class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-1" autocomplete="off">
        @error('password')<p class="text-xs text-red-600 mb-2">{{ $message }}</p>@enderror

        <button type="submit"
                class="mt-3 w-full bg-red-600 text-white text-sm font-medium py-2 rounded hover:bg-red-700">
            Merge and archive the duplicate
        </button>
    </form>
</div>

<script>
function mergeWizard() {
    return {
        masterId: {{ $master->id }},
        query: '', results: [], loser: null, preview: null, diffs: [], error: '',

        async search() {
            this.error = '';
            if (this.query.trim().length < 2) { this.results = []; return; }
            const res = await fetch(`{{ route('patients.search') }}?q=${encodeURIComponent(this.query)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            this.results = (data || []).filter(r => r.id !== this.masterId);
        },

        async pick(r) {
            this.results = []; this.query = ''; this.error = '';
            const res = await fetch(`{{ route('patients.merge.preview', $master) }}?loser_id=${r.id}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();
            if (!data.ok) { this.error = data.message || 'Cannot merge that record.'; return; }
            this.loser = data.loser;
            this.preview = data.preview;
            this.diffs = data.diffs || [];
        },

        clearLoser() { this.loser = null; this.preview = null; this.diffs = []; this.error = ''; },

        mergedRows() {
            if (!this.preview) return {};
            return { ...this.preview.children, ...this.preview.money, ...this.preview.special };
        },
    };
}
</script>
@endsection
