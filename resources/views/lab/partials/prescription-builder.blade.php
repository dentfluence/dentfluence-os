{{--
| Lab Prescription Builder — structured clinical prescription form
|
| Usage:
|   @include('lab.partials.prescription-builder', [
|       'labCase'       => $labCase,          // LabCase model (loaded with ->prescription)
|       'formAction'    => route(...),         // POST or PUT URL
|       'formMethod'    => 'POST'|'PUT',
|       'compact'       => false,              // optional: compact mode for drawers
|   ])
|
| Requires: Alpine.js (loaded in layouts.app)
--}}

@php
    $rx          = $labCase->prescription ?? null;
    $category    = $labCase->work_category ?? 'Other';
    $fieldSchema = \App\Models\LabCasePrescription::FIELD_SCHEMA[$category]
                   ?? \App\Models\LabCasePrescription::FIELD_SCHEMA['Other'];
    $suggestions = \App\Models\LabCasePrescription::SMART_SUGGESTIONS[$category] ?? [];
    $shades      = \App\Models\LabCase::SHADES;
    $formMethod  = $formMethod ?? 'POST';
    $formAction  = $formAction ?? route('lab.prescription.store', $labCase);
    $compact     = $compact ?? false;

    // Pre-fill clinical_fields from existing prescription
    $existingFields = $rx ? ($rx->clinical_fields ?? []) : [];
@endphp

<div
    x-data="prescriptionBuilder(
        {{ json_encode($fieldSchema) }},
        {{ json_encode($existingFields) }},
        {{ json_encode($suggestions) }},
        {{ json_encode($rx?->suggestions_acknowledged ?? false) }}
    )"
    x-init="init()"
    class="space-y-5"
>

    {{-- ── TEMPLATE SELECTOR ────────────────────────────────────────────── --}}
    <div class="bg-brand-50 border border-brand-200 rounded-xl p-4" x-show="!templateApplied">
        <div class="flex items-center justify-between mb-2">
            <p class="text-sm font-semibold text-brand-700">⚡ Apply a Prescription Template</p>
            <button type="button" @click="toggleTemplatePanel()"
                class="text-xs text-brand-500 hover:text-brand-700 underline">
                <span x-text="showTemplates ? 'Hide' : 'Show Templates'"></span>
            </button>
        </div>
        <div x-show="showTemplates" class="mt-2 space-y-2">
            <div x-show="loadingTemplates" class="text-xs text-gray-400">Loading templates…</div>
            <div x-show="!loadingTemplates && templates.length === 0" class="text-xs text-gray-400">
                No templates saved yet for this category.
            </div>
            <div class="flex flex-wrap gap-2" x-show="!loadingTemplates && templates.length > 0">
                <template x-for="t in templates" :key="t.id">
                    <button type="button"
                        @click="applyTemplate(t)"
                        class="px-3 py-1.5 bg-white border border-brand-300 text-brand-700 text-xs font-medium rounded-lg hover:bg-brand-100 transition shadow-sm">
                        <span x-text="t.name"></span>
                        <span x-if="t.material" class="ml-1 text-brand-400" x-text="'· ' + t.material"></span>
                    </button>
                </template>
            </div>
        </div>
        {{-- Applied template badge --}}
        <div x-show="templateApplied" class="flex items-center gap-2 mt-1">
            <span class="text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full font-medium">
                ✓ Template: <span x-text="appliedTemplateName"></span>
            </span>
            <button type="button" @click="clearTemplate()" class="text-xs text-gray-400 hover:text-red-500">Remove</button>
        </div>
    </div>

    {{-- Applied template name (hidden input) --}}
    <input type="hidden" name="template_id" :value="appliedTemplateId">

    {{-- ── SMART SUGGESTIONS ──────────────────────────────────────────────── --}}
    @if(count($suggestions) > 0)
    <div
        x-show="!suggestionsAcknowledged"
        class="bg-amber-50 border border-amber-200 rounded-xl p-4"
    >
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-semibold text-amber-800 mb-2">💡 Recommended Records for this Case Type</p>
                <ul class="space-y-1">
                    @foreach($suggestions as $s)
                    <li class="text-xs text-amber-700 flex items-center gap-1.5">
                        <span>{{ $s }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
            <button type="button"
                @click="suggestionsAcknowledged = true"
                class="shrink-0 text-xs text-amber-600 hover:text-amber-800 font-medium underline mt-0.5">
                Got it
            </button>
        </div>
    </div>
    <input type="hidden" name="suggestions_acknowledged" :value="suggestionsAcknowledged ? 1 : 0">
    @endif

    {{-- ── MATERIAL + SHADE ROW ───────────────────────────────────────────── --}}
    <div class="{{ $compact ? 'grid grid-cols-1 gap-3' : 'grid grid-cols-2 md:grid-cols-3 gap-4' }}">

        {{-- Material --}}
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Material</label>
            <input type="text" name="material"
                x-model="material"
                placeholder="e.g. Zirconia, E-max, PFM…"
                value="{{ old('material', $rx?->material) }}"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 focus:border-transparent transition">
        </div>

        {{-- Shade --}}
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Shade</label>
            <div class="flex gap-2">
                <select name="shade" x-model="shade"
                    class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                    <option value="">Select shade…</option>
                    @foreach($shades as $s)
                    <option value="{{ $s }}" {{ old('shade', $rx?->shade) === $s ? 'selected' : '' }}>{{ $s }}</option>
                    @endforeach
                    <option value="custom">Custom…</option>
                </select>
                {{-- Custom shade input --}}
                <input type="text" x-show="shade === 'custom'" name="shade_custom"
                    placeholder="Type shade"
                    class="w-24 border border-gray-300 rounded-lg px-2 py-2 text-sm focus:ring-2 focus:ring-brand-400">
            </div>
        </div>

        {{-- Stump Shade --}}
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Stump Shade <span class="text-gray-400">(optional)</span></label>
            <select name="stump_shade"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                <option value="">—</option>
                @foreach($shades as $s)
                <option value="{{ $s }}" {{ old('stump_shade', $rx?->stump_shade) === $s ? 'selected' : '' }}>{{ $s }}</option>
                @endforeach
            </select>
        </div>

    </div>

    {{-- ── DYNAMIC CLINICAL FIELDS ────────────────────────────────────────── --}}
    @if(count($fieldSchema) > 0)
    <div class="border border-gray-200 rounded-xl overflow-hidden">
        <div class="bg-gray-50 px-4 py-2.5 border-b border-gray-200">
            <p class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Clinical Parameters</p>
        </div>
        <div class="{{ $compact ? 'p-4 space-y-3' : 'p-5 grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4' }}">
            @foreach($fieldSchema as $field)
            @php
                $key      = $field['key'];
                $label    = $field['label'];
                $type     = $field['type'];
                $options  = $field['options'] ?? [];
                $ph       = $field['placeholder'] ?? '';
                $existing = $existingFields[$key] ?? old("clinical_fields.$key", '');
            @endphp

            {{-- Boolean (checkbox toggle) --}}
            @if($type === 'boolean')
            <div class="flex items-center gap-3 py-1">
                <label class="relative inline-flex items-center cursor-pointer">
                    <input type="hidden" name="clinical_fields[{{ $key }}]" value="0">
                    <input type="checkbox"
                        name="clinical_fields[{{ $key }}]"
                        value="1"
                        {{ $existing ? 'checked' : '' }}
                        class="sr-only peer">
                    <div class="w-9 h-5 bg-gray-200 peer-focus:ring-2 peer-focus:ring-brand-400 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-brand-600"></div>
                </label>
                <span class="text-sm text-gray-700">{{ $label }}</span>
            </div>

            {{-- Select --}}
            @elseif($type === 'select')
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                <select name="clinical_fields[{{ $key }}]"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 focus:border-transparent">
                    <option value="">— Select —</option>
                    @foreach($options as $opt)
                    @if($opt !== '')
                    <option value="{{ $opt }}" {{ $existing === $opt ? 'selected' : '' }}>{{ $opt }}</option>
                    @endif
                    @endforeach
                </select>
            </div>

            {{-- Textarea --}}
            @elseif($type === 'textarea')
            <div class="{{ $compact ? '' : 'md:col-span-2' }}">
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                <textarea name="clinical_fields[{{ $key }}]"
                    rows="{{ $compact ? 2 : 3 }}"
                    placeholder="{{ $ph }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 focus:border-transparent resize-none">{{ $existing }}</textarea>
            </div>

            {{-- Number --}}
            @elseif($type === 'number')
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                <input type="number" name="clinical_fields[{{ $key }}]"
                    value="{{ $existing }}"
                    placeholder="{{ $ph }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 focus:border-transparent">
            </div>

            {{-- Text (default) --}}
            @else
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">{{ $label }}</label>
                <input type="text" name="clinical_fields[{{ $key }}]"
                    value="{{ $existing }}"
                    placeholder="{{ $ph }}"
                    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 focus:border-transparent">
            </div>
            @endif

            @endforeach
        </div>
    </div>
    @endif

    {{-- ── SPECIAL INSTRUCTIONS ────────────────────────────────────────────── --}}
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">
            Special Instructions <span class="text-gray-400">(free text — anything not covered above)</span>
        </label>
        <textarea name="special_instructions" rows="{{ $compact ? 2 : 4 }}"
            placeholder="Any additional instructions for the lab…"
            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-400 focus:border-transparent resize-none">{{ old('special_instructions', $rx?->special_instructions) }}</textarea>
    </div>

    {{-- ── SAVE PRESCRIPTION BUTTON ─────────────────────────────────────────── --}}
    @if(!($hideSubmit ?? false))
    <div class="flex items-center gap-3 pt-1">
        <button type="submit"
            class="px-5 py-2.5 bg-brand-700 hover:bg-brand-800 text-white text-sm font-semibold rounded-lg shadow transition">
            Save Prescription
        </button>
        @if($rx)
        <span class="text-xs text-gray-400">
            Last updated {{ $rx->updated_at->diffForHumans() }}
            @if($rx->createdBy) by {{ $rx->createdBy->name }} @endif
        </span>
        @endif
    </div>
    @endif

</div>

@once
@push('scripts')
<script>
function prescriptionBuilder(fieldSchema, existingFields, suggestions, suggestionsAcknowledged) {
    return {
        fieldSchema,
        existingFields,
        suggestions,
        suggestionsAcknowledged,

        material: existingFields.material ?? '',
        shade: existingFields.shade ?? '',
        showTemplates: false,
        loadingTemplates: false,
        templates: [],
        templateApplied: false,
        appliedTemplateId: null,
        appliedTemplateName: '',

        init() {
            // Auto-load templates on mount
            this.fetchTemplates();
        },

        async fetchTemplates() {
            this.loadingTemplates = true;
            try {
                const category = encodeURIComponent('{{ $category }}');
                const res = await fetch(`/lab/templates?category=${category}`);
                this.templates = await res.json();
            } catch (e) {
                this.templates = [];
            }
            this.loadingTemplates = false;
        },

        toggleTemplatePanel() {
            this.showTemplates = !this.showTemplates;
        },

        applyTemplate(t) {
            this.appliedTemplateId   = t.id;
            this.appliedTemplateName = t.name;
            this.templateApplied     = true;

            if (t.material) this.material = t.material;
            if (t.shade)    this.shade    = t.shade;

            // Fill clinical fields from template
            if (t.clinical_fields) {
                Object.entries(t.clinical_fields).forEach(([key, val]) => {
                    const el = document.querySelector(`[name="clinical_fields[${key}]"]`);
                    if (el) {
                        if (el.type === 'checkbox') {
                            el.checked = !!val;
                        } else {
                            el.value = val;
                        }
                    }
                });
            }
            this.showTemplates = false;
        },

        clearTemplate() {
            this.templateApplied     = false;
            this.appliedTemplateId   = null;
            this.appliedTemplateName = '';
        },
    };
}
</script>
@endpush
@endonce
