@extends('layouts.app')
@section('page-title', 'Create Prescription Template')

@section('content')
<div class="p-6 max-w-4xl mx-auto" x-data="templateForm()">
    <div class="mb-6">
        <h1 class="text-xl font-display font-semibold text-brand-800">Create Prescription Template</h1>
        <p class="text-xs text-gray-500 mt-0.5">Define a preset set of medicines that loads into the prescription form</p>
    </div>

    <form method="POST" action="{{ route('rx.settings.prescription-templates.store') }}">
        @csrf

        {{-- Template Meta --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5 shadow-sm">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Template Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required placeholder="RCT Pain Management"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Category</label>
                    <input type="text" name="category" placeholder="endodontics, surgical, periodontal…"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                    <input type="text" name="description" placeholder="Optional description"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                </div>
            </div>
        </div>

        {{-- Drug Rows --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-5 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-sm font-semibold text-gray-700">Medicines</h2>
                <button type="button" @click="addRow()"
                        class="text-sm text-brand-600 hover:text-brand-800 font-medium">+ Add Medicine</button>
            </div>

            <div class="space-y-3" id="drug-rows">
                <template x-for="(row, i) in rows" :key="i">
                    <div class="grid grid-cols-12 gap-2 items-center bg-gray-50 rounded-lg p-3">
                        <div class="col-span-3">
                            <select :name="`items[${i}][drug_id]`" required
                                    class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                                <option value="">Select Drug…</option>
                                @foreach($drugs as $drug)
                                    <option value="{{ $drug->id }}">{{ $drug->brand_name }} {{ $drug->strength ? '('.$drug->strength.')' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-1">
                            <input type="number" :name="`items[${i}][morning]`" x-model="row.morning" min="0" placeholder="M"
                                   class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm text-center focus:outline-none focus:ring-2 focus:ring-brand-400">
                        </div>
                        <div class="col-span-1">
                            <input type="number" :name="`items[${i}][afternoon]`" x-model="row.afternoon" min="0" placeholder="A"
                                   class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm text-center focus:outline-none focus:ring-2 focus:ring-brand-400">
                        </div>
                        <div class="col-span-1">
                            <input type="number" :name="`items[${i}][night]`" x-model="row.night" min="0" placeholder="N"
                                   class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm text-center focus:outline-none focus:ring-2 focus:ring-brand-400">
                        </div>
                        <div class="col-span-2 flex gap-1">
                            <input type="number" :name="`items[${i}][duration]`" x-model="row.duration" min="1" placeholder="Days"
                                   class="w-16 px-2 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                            <select :name="`items[${i}][duration_unit]`"
                                    class="flex-1 px-2 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                                <option value="days">D</option>
                                <option value="weeks">W</option>
                                <option value="months">M</option>
                            </select>
                        </div>
                        <div class="col-span-2">
                            <select :name="`items[${i}][food_instruction_id]`"
                                    class="w-full px-2 py-1.5 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                                <option value="">Food advice…</option>
                                @foreach($foodInst as $fi)
                                    <option value="{{ $fi->id }}">{{ $fi->label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-span-1">
                            <button type="button" @click="rows.splice(i, 1)"
                                    class="text-red-400 hover:text-red-600 text-lg leading-none">×</button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="mt-3 grid grid-cols-12 gap-2 px-3">
                <div class="col-span-3 text-xs text-gray-400">Drug</div>
                <div class="col-span-1 text-xs text-gray-400 text-center">Morn</div>
                <div class="col-span-1 text-xs text-gray-400 text-center">Aft</div>
                <div class="col-span-1 text-xs text-gray-400 text-center">Night</div>
                <div class="col-span-2 text-xs text-gray-400">Duration</div>
                <div class="col-span-2 text-xs text-gray-400">Food</div>
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="px-6 py-2.5 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition">Save Template</button>
            <a href="{{ route('rx.settings.prescription-templates') }}" class="px-4 py-2.5 text-sm text-gray-600 hover:text-gray-800">Cancel</a>
        </div>
    </form>
</div>

<script>
function templateForm() {
    return {
        rows: [{ morning: 1, afternoon: 0, night: 1, duration: 5 }],
        addRow() {
            this.rows.push({ morning: 1, afternoon: 0, night: 1, duration: 5 });
        }
    }
}
</script>
@endsection
