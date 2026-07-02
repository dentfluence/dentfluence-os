@extends('layouts.app')
@section('page-title', isset($drug) ? 'Edit Drug' : 'Add Drug')

@section('content')
<div class="p-6 max-w-4xl mx-auto">

    <div class="mb-6">
        <h1 class="text-2xl font-display font-semibold text-brand-800">{{ isset($drug) ? 'Edit Drug: '.$drug->brand_name : 'Add New Drug' }}</h1>
        <p class="text-sm text-gray-500 mt-0.5">Drug Master — comprehensive drug profile with clinical safety</p>
    </div>

    @if($errors->any())
        <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">
            <ul class="list-disc pl-4">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
        </div>
    @endif

    <form method="POST"
          action="{{ isset($drug) ? route('rx.drugs.update', $drug) : route('rx.drugs.store') }}"
          class="space-y-6">
        @csrf
        @if(isset($drug)) @method('PATCH') @endif

        {{-- Section: Identity --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b border-gray-100">Identity</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Drug Code</label>
                    <input type="text" name="drug_code" value="{{ old('drug_code', $drug->drug_code ?? '') }}"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"
                           placeholder="Auto-generated if blank">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Brand Name <span class="text-red-500">*</span></label>
                    <input type="text" name="brand_name" value="{{ old('brand_name', $drug->brand_name ?? '') }}" required
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Generic Name</label>
                    <select name="generic_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                        <option value="">— Select Generic —</option>
                        @foreach($generics as $g)
                            <option value="{{ $g->id }}" @selected(old('generic_id', $drug->generic_id ?? '') == $g->id)>{{ $g->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Category</label>
                    <select name="category_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                        <option value="">— Select Category —</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->id }}" @selected(old('category_id', $drug->category_id ?? '') == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Strength</label>
                    <input type="text" name="strength" value="{{ old('strength', $drug->strength ?? '') }}"
                           placeholder="500mg, 0.2%, 60000 IU…"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Dosage Form</label>
                    <input type="text" name="dosage_form" value="{{ old('dosage_form', $drug->dosage_form ?? '') }}"
                           placeholder="Tablet, Capsule, Gel, Mouthwash…"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Composition</label>
                    <input type="text" name="composition" value="{{ old('composition', $drug->composition ?? '') }}"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Route of Administration</label>
                    <select name="route_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                        <option value="">— Select Route —</option>
                        @foreach($routes as $r)
                            <option value="{{ $r->id }}" @selected(old('route_id', $drug->route_id ?? '') == $r->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        {{-- Section: Defaults --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b border-gray-100">Prescription Defaults</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Default Duration</label>
                    <div class="flex gap-2">
                        <input type="number" name="default_duration" value="{{ old('default_duration', $drug->default_duration ?? '') }}"
                               min="1" placeholder="5"
                               class="w-24 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                        <select name="default_duration_unit" class="flex-1 px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                            <option value="days"   @selected(old('default_duration_unit', $drug->default_duration_unit ?? 'days') == 'days')>Days</option>
                            <option value="weeks"  @selected(old('default_duration_unit', $drug->default_duration_unit ?? '') == 'weeks')>Weeks</option>
                            <option value="months" @selected(old('default_duration_unit', $drug->default_duration_unit ?? '') == 'months')>Months</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Default Food Advice</label>
                    <select name="default_food_instruction_id" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                        <option value="">— None —</option>
                        @foreach($foodInst as $fi)
                            <option value="{{ $fi->id }}" @selected(old('default_food_instruction_id', $drug->default_food_instruction_id ?? '') == $fi->id)>{{ $fi->label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Max Daily Dose</label>
                    <input type="text" name="max_daily_dose" value="{{ old('max_daily_dose', $drug->max_daily_dose ?? '') }}"
                           placeholder="4g/day"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                </div>
                <div class="sm:col-span-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Default Instructions</label>
                    <textarea name="default_instructions" rows="2"
                              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">{{ old('default_instructions', $drug->default_instructions ?? '') }}</textarea>
                </div>
            </div>
        </div>

        {{-- Section: CDSS Safety --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b border-gray-100">Clinical Safety (CDSS)</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Duplicate Molecule Group</label>
                    <input type="text" name="duplicate_molecule_group" value="{{ old('duplicate_molecule_group', $drug->duplicate_molecule_group ?? '') }}"
                           placeholder="paracetamol, ibuprofen…"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                    <p class="text-xs text-gray-400 mt-1">Used for duplicate molecule detection</p>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Antibiotic Class</label>
                    <input type="text" name="antibiotic_class" value="{{ old('antibiotic_class', $drug->antibiotic_class ?? '') }}"
                           placeholder="penicillin, macrolide, NSAID…"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Pregnancy Category</label>
                    <select name="pregnancy_category" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                        <option value="">—</option>
                        @foreach(['A','B','C','D','X','N'] as $cat)
                            <option value="{{ $cat }}" @selected(old('pregnancy_category', $drug->pregnancy_category ?? '') == $cat)>Category {{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Breastfeeding Safety</label>
                    <select name="breastfeeding_safety" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                        <option value="">—</option>
                        @foreach(['safe','caution','avoid','unknown'] as $v)
                            <option value="{{ $v }}" @selected(old('breastfeeding_safety', $drug->breastfeeding_safety ?? '') == $v)>{{ ucfirst($v) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Pediatric Safety</label>
                    <select name="pediatric_safety" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                        <option value="">—</option>
                        @foreach(['safe','caution','avoid','unknown'] as $v)
                            <option value="{{ $v }}" @selected(old('pediatric_safety', $drug->pediatric_safety ?? '') == $v)>{{ ucfirst($v) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Geriatric Caution</label>
                    <select name="geriatric_caution" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                        <option value="">—</option>
                        @foreach(['normal','caution','avoid'] as $v)
                            <option value="{{ $v }}" @selected(old('geriatric_caution', $drug->geriatric_caution ?? '') == $v)>{{ ucfirst($v) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Renal Dose Adjustment</label>
                    <input type="text" name="renal_dose_adjustment" value="{{ old('renal_dose_adjustment', $drug->renal_dose_adjustment ?? '') }}"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Hepatic Dose Adjustment</label>
                    <input type="text" name="hepatic_dose_adjustment" value="{{ old('hepatic_dose_adjustment', $drug->hepatic_dose_adjustment ?? '') }}"
                           class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Contraindications</label>
                    <textarea name="contraindications" rows="2"
                              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">{{ old('contraindications', $drug->contraindications ?? '') }}</textarea>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">Drug Interactions Note</label>
                    <textarea name="drug_interactions_note" rows="2"
                              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">{{ old('drug_interactions_note', $drug->drug_interactions_note ?? '') }}</textarea>
                </div>
                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_controlled" value="0">
                        <input type="checkbox" name="is_controlled" value="1" @checked(old('is_controlled', $drug->is_controlled ?? false))
                               class="rounded text-red-500">
                        <span class="text-sm text-gray-700">Controlled Drug (Schedule H/H1)</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Section: Dental Context --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 shadow-sm">
            <h2 class="text-sm font-semibold text-gray-700 mb-4 pb-2 border-b border-gray-100">Dental Context</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Common Dental Uses</label>
                    <textarea name="common_dental_uses" rows="3"
                              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">{{ old('common_dental_uses', $drug->common_dental_uses ?? '') }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Notes</label>
                    <textarea name="notes" rows="3"
                              class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">{{ old('notes', $drug->notes ?? '') }}</textarea>
                </div>
                <div>
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $drug->is_active ?? true))
                               class="rounded text-brand-600">
                        <span class="text-sm text-gray-700">Active (appears in prescription search)</span>
                    </label>
                </div>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3">
            <button type="submit" dusk="drug-save"
                    class="px-6 py-2.5 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition">
                {{ isset($drug) ? 'Update Drug' : 'Save Drug' }}
            </button>
            <a href="{{ route('rx.drugs.index') }}" class="px-4 py-2.5 text-sm text-gray-600 hover:text-gray-800 transition">Cancel</a>
        </div>
    </form>
</div>
@endsection
