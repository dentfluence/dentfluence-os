@extends('layouts.app')

@section('title', $treatment->name . ' — Treatment Detail')

@section('content')
<div class="p-6 space-y-5"
     x-data="{
        activeTab: '{{ $tab }}',
        // SOP step management
        doctorSteps: {{ json_encode($treatment->activeSop?->doctor_steps ?? []) }},
        assistantSteps: {{ json_encode($treatment->activeSop?->assistant_steps ?? []) }},
        newDoctorStep: '',
        newAssistantStep: '',
        addStep(list, val) { if (val.trim()) { this[list].push(val.trim()); this['new' + list.charAt(0).toUpperCase() + list.slice(1)] = ''; } },
        removeStep(list, i) { this[list].splice(i, 1); },
     }">

    {{-- ── Breadcrumb ── --}}
    <div class="flex items-center gap-2 text-xs text-gray-400 font-[DM_Sans]">
        <a href="{{ route('treatments.index') }}" class="hover:text-[#6a0f70]">Treatments</a>
        <span>/</span>
        <span class="text-gray-600">{{ $treatment->name }}</span>
    </div>

    {{-- ── Page Header ── --}}
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="w-4 h-4 rounded-full flex-shrink-0" style="background: {{ $treatment->color }}"></div>
            <div>
                <h1 class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">
                    {{ $treatment->name }}
                </h1>
                <div class="flex items-center gap-3 mt-0.5">
                    @if($treatment->code)
                    <span class="text-xs text-gray-400 font-[DM_Sans]">{{ $treatment->code }}</span>
                    @endif
                    <span class="text-xs font-[DM_Sans] {{ $treatment->is_active ? 'text-green-600' : 'text-gray-400' }}">
                        {{ $treatment->is_active ? '● Active' : '○ Inactive' }}
                    </span>
                    <span class="text-xs text-gray-400 font-[DM_Sans]">{{ $treatment->category->name }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Flash ── --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm font-[DM_Sans]">{{ session('success') }}</div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-[DM_Sans]">{{ session('error') }}</div>
    @endif

    {{-- ── Tabs ── --}}
    <div class="border-b border-[#e8d5f0]">
        <nav class="flex gap-0 -mb-px">
            @foreach([
                ['overview',  'Overview'],
                ['sop',       'SOP'],
                ['rules',     'Rules'],
                ['media',     'Media'],
                ['review',    'Review'],
                ['usage',     'Usage'],
            ] as [$key, $label])
            <button @click="activeTab = '{{ $key }}'"
                    :class="activeTab === '{{ $key }}'
                        ? 'border-b-2 border-[#6a0f70] text-[#6a0f70] font-medium'
                        : 'text-gray-400 hover:text-gray-600'"
                    class="px-5 py-3 text-sm font-[DM_Sans] transition-colors whitespace-nowrap">
                {{ $label }}
                @if($key === 'sop')
                    @php $sop = $treatment->activeSop; @endphp
                    @if(!$sop)
                        <span class="ml-1 text-xs text-yellow-500">!</span>
                    @endif
                @endif
                @if($key === 'review' && $treatment->activeSop && $treatment->activeSop->next_review_at && $treatment->activeSop->next_review_at->isPast())
                    <span class="ml-1 text-xs text-red-500">!</span>
                @endif
            </button>
            @endforeach
        </nav>
    </div>

    {{-- ════════════════════════════════════════════════════
         TAB: OVERVIEW — basic info + pricing
    ════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'overview'" class="space-y-5">
        <form method="POST" action="{{ route('treatments.update', $treatment) }}" class="space-y-5">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Left: Basic Info --}}
                <div class="bg-white border border-[#e8d5f0] p-5 space-y-4">
                    <h3 class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond]">Basic Information</h3>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Category *</label>
                        <select name="treatment_category_id" required
                                class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] text-gray-700 focus:outline-none focus:border-[#6a0f70] bg-white">
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ $treatment->treatment_category_id == $cat->id ? 'selected' : '' }}>
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Name *</label>
                            <input type="text" name="name" value="{{ $treatment->name }}" required
                                   class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Code</label>
                            <input type="text" name="code" value="{{ $treatment->code }}" placeholder="RCT-01"
                                   class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Description</label>
                        <textarea name="description" rows="3"
                                  class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70] resize-none">{{ $treatment->description }}</textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Duration (min) *</label>
                            <input type="number" name="default_duration_minutes" value="{{ $treatment->default_duration_minutes }}"
                                   required min="5" max="480"
                                   class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Colour</label>
                            <div class="flex gap-2">
                                <input type="color" name="color" value="{{ $treatment->color }}"
                                       class="h-9 w-12 border border-[#e8d5f0] cursor-pointer p-0.5">
                                <input type="text" x-ref="colorText" value="{{ $treatment->color }}"
                                       class="flex-1 border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]"
                                       placeholder="#6a0f70">
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" id="is_active"
                               {{ $treatment->is_active ? 'checked' : '' }}
                               class="w-4 h-4 accent-[#6a0f70]">
                        <label for="is_active" class="text-sm text-gray-600 font-[DM_Sans]">Active (visible in treatment plans & billing)</label>
                    </div>
                </div>

                {{-- Right: Pricing --}}
                <div class="bg-white border border-[#e8d5f0] p-5 space-y-4">
                    <h3 class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond]">Pricing</h3>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Base Price (₹) *</label>
                        <input type="number" name="default_price" value="{{ $treatment->default_price }}"
                               required min="0" step="0.01"
                               class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                        <p class="text-xs text-gray-400 mt-1 font-[DM_Sans]">This auto-fills when selecting this treatment in a plan or invoice.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Min Price (₹)</label>
                            <input type="number" name="min_price" value="{{ $treatment->min_price }}"
                                   min="0" step="0.01" placeholder="0.00"
                                   class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Max Price (₹)</label>
                            <input type="number" name="max_price" value="{{ $treatment->max_price }}"
                                   min="0" step="0.01" placeholder="0.00"
                                   class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">GST %</label>
                        <input type="number" name="gst_pct" value="{{ $treatment->gst_pct }}"
                               min="0" max="100" step="0.01"
                               class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                        <p class="text-xs text-gray-400 mt-1 font-[DM_Sans]">Auto-applied in billing. 0 = exempt.</p>
                    </div>

                    {{-- Price summary card --}}
                    <div class="bg-[#faf5ff] border border-[#e8d5f0] p-4 space-y-1">
                        <p class="text-xs text-gray-400 uppercase tracking-wider font-[DM_Sans]">Total with GST (at base price)</p>
                        @php
                            $gstAmt = $treatment->default_price * ($treatment->gst_pct / 100);
                            $total  = $treatment->default_price + $gstAmt;
                        @endphp
                        <p class="text-2xl font-semibold text-[#380740] font-[Cormorant_Garamond]">
                            ₹{{ number_format($total, 2) }}
                        </p>
                        <p class="text-xs text-gray-400 font-[DM_Sans]">
                            ₹{{ number_format($treatment->default_price, 2) }} + ₹{{ number_format($gstAmt, 2) }} GST
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Sort Order</label>
                        <input type="number" name="sort_order" value="{{ $treatment->sort_order }}" min="0"
                               class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                        <p class="text-xs text-gray-400 mt-1 font-[DM_Sans]">Lower = appears first in category list.</p>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('treatments.index') }}"
                   class="px-4 py-2 text-sm text-gray-500 font-[DM_Sans] border border-[#e8d5f0] hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit"
                        class="px-5 py-2 bg-[#6a0f70] text-white text-sm font-[DM_Sans] hover:bg-[#52095a] transition">
                    Save Changes
                </button>
            </div>
        </form>

        {{-- Danger zone --}}
        <div class="border border-red-100 bg-red-50 p-4 flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-red-800 font-[DM_Sans]">Delete Treatment</p>
                <p class="text-xs text-red-500 font-[DM_Sans]">Soft-deleted — existing patient plans are not affected.</p>
            </div>
            <form method="POST" action="{{ route('treatments.destroy', $treatment) }}"
                  onsubmit="return confirm('Delete {{ addslashes($treatment->name) }}?')">
                @csrf @method('DELETE')
                <button type="submit"
                        class="px-4 py-2 bg-red-600 text-white text-sm font-[DM_Sans] hover:bg-red-700 transition">
                    Delete
                </button>
            </form>
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════
         TAB: SOP
    ════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'sop'" class="space-y-5">
        @php $sop = $treatment->activeSop ?? $treatment->sops->first(); @endphp

        {{-- SOP Status Banner --}}
        @if($sop)
        <div class="flex items-center gap-3 px-4 py-2 border
            {{ $sop->status === 'active' ? 'bg-green-50 border-green-200' : ($sop->status === 'under_review' ? 'bg-yellow-50 border-yellow-200' : 'bg-gray-50 border-gray-200') }}">
            <span class="text-xs font-[DM_Sans] font-medium {{ $sop->status === 'active' ? 'text-green-700' : ($sop->status === 'under_review' ? 'text-yellow-700' : 'text-gray-600') }}">
                v{{ $sop->version }} — {{ ucfirst(str_replace('_', ' ', $sop->status)) }}
            </span>
            @if($sop->last_reviewed_at)
            <span class="text-xs text-gray-400 font-[DM_Sans]">
                Last reviewed: {{ $sop->last_reviewed_at->format('d M Y') }}
                @if($sop->reviewer) by {{ $sop->reviewer->name }} @endif
            </span>
            @endif
            @if($sop->next_review_at)
            <span class="text-xs font-[DM_Sans] {{ $sop->next_review_at->isPast() ? 'text-red-600 font-medium' : 'text-gray-400' }}">
                Next review: {{ $sop->next_review_at->format('d M Y') }}
                {{ $sop->next_review_at->isPast() ? '(OVERDUE)' : '' }}
            </span>
            @endif
        </div>
        @else
        <div class="bg-yellow-50 border border-yellow-200 px-4 py-3 text-sm text-yellow-800 font-[DM_Sans]">
            No SOP exists yet for this treatment. Fill in the details below to create one.
        </div>
        @endif

        <form method="POST" action="{{ route('treatments.sop.save', $treatment) }}" class="space-y-5">
            @csrf

            {{-- Doctor Steps --}}
            <div class="bg-white border border-[#e8d5f0] p-5 space-y-3">
                <h3 class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond]">
                    Doctor Steps
                    <span class="text-xs text-gray-400 font-[DM_Sans] font-normal ml-2">Ordered checklist for the treating doctor</span>
                </h3>
                <div class="space-y-2">
                    <template x-for="(step, i) in doctorSteps" :key="i">
                        <div class="flex items-start gap-2">
                            <span class="w-5 h-5 flex-shrink-0 bg-[#f3e8f9] text-[#6a0f70] text-xs flex items-center justify-center mt-2 font-[DM_Sans] font-medium" x-text="i+1"></span>
                            <input type="text" :name="`doctor_steps[${i}]`" x-model="doctorSteps[i]"
                                   class="flex-1 border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                            <button type="button" @click="removeStep('doctorSteps', i)"
                                    class="text-red-300 hover:text-red-500 mt-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
                <div class="flex gap-2 mt-2">
                    <input type="text" x-model="newDoctorStep"
                           placeholder="Type step and press Add…"
                           @keydown.enter.prevent="addStep('doctorSteps', newDoctorStep)"
                           class="flex-1 border border-dashed border-[#c9a8e0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70] bg-[#faf5ff]">
                    <button type="button" @click="addStep('doctorSteps', newDoctorStep)"
                            class="px-3 py-2 text-sm font-[DM_Sans] bg-[#f3e8f9] text-[#6a0f70] border border-[#e8d5f0] hover:bg-[#e8d5f0]">
                        + Add
                    </button>
                </div>
            </div>

            {{-- Assistant Steps --}}
            <div class="bg-white border border-[#e8d5f0] p-5 space-y-3">
                <h3 class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond]">
                    Assistant / Chairside Steps
                    <span class="text-xs text-gray-400 font-[DM_Sans] font-normal ml-2">Preparation and assist checklist</span>
                </h3>
                <div class="space-y-2">
                    <template x-for="(step, i) in assistantSteps" :key="i">
                        <div class="flex items-start gap-2">
                            <span class="w-5 h-5 flex-shrink-0 bg-blue-50 text-blue-600 text-xs flex items-center justify-center mt-2 font-[DM_Sans] font-medium" x-text="i+1"></span>
                            <input type="text" :name="`assistant_steps[${i}]`" x-model="assistantSteps[i]"
                                   class="flex-1 border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                            <button type="button" @click="removeStep('assistantSteps', i)"
                                    class="text-red-300 hover:text-red-500 mt-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>
                <div class="flex gap-2 mt-2">
                    <input type="text" x-model="newAssistantStep"
                           placeholder="Type step and press Add…"
                           @keydown.enter.prevent="addStep('assistantSteps', newAssistantStep)"
                           class="flex-1 border border-dashed border-[#bfdbfe] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-blue-400 bg-blue-50">
                    <button type="button" @click="addStep('assistantSteps', newAssistantStep)"
                            class="px-3 py-2 text-sm font-[DM_Sans] bg-blue-50 text-blue-700 border border-blue-200 hover:bg-blue-100">
                        + Add
                    </button>
                </div>
            </div>

            {{-- Pre / Post Care --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="bg-white border border-[#e8d5f0] p-5">
                    <label class="block text-base font-semibold text-[#380740] font-[Cormorant_Garamond] mb-2">
                        Pre-Care Instructions
                        <span class="text-xs text-gray-400 font-[DM_Sans] font-normal ml-1">Shown to patient before visit</span>
                    </label>
                    <textarea name="pre_instructions" rows="6"
                              placeholder="What should the patient do / avoid before this treatment…"
                              class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70] resize-none">{{ $sop?->pre_instructions }}</textarea>
                </div>
                <div class="bg-white border border-[#e8d5f0] p-5">
                    <label class="block text-base font-semibold text-[#380740] font-[Cormorant_Garamond] mb-2">
                        Post-Care Instructions
                        <span class="text-xs text-gray-400 font-[DM_Sans] font-normal ml-1">Shown to patient after visit</span>
                    </label>
                    <textarea name="post_instructions" rows="6"
                              placeholder="What should the patient do / avoid after this treatment…"
                              class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70] resize-none">{{ $sop?->post_instructions }}</textarea>
                </div>
            </div>

            {{-- Clinical Notes / Consent Notes --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div class="bg-white border border-[#e8d5f0] p-5">
                    <label class="block text-base font-semibold text-[#380740] font-[Cormorant_Garamond] mb-2">
                        Clinical Notes
                        <span class="text-xs text-gray-400 font-[DM_Sans] font-normal ml-1">Internal tips for doctors</span>
                    </label>
                    <textarea name="clinical_notes" rows="5"
                              placeholder="Clinical tips, contraindications, materials used…"
                              class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70] resize-none">{{ $sop?->clinical_notes }}</textarea>
                </div>
                <div class="bg-white border border-[#e8d5f0] p-5">
                    <label class="block text-base font-semibold text-[#380740] font-[Cormorant_Garamond] mb-2">
                        Consent Explanation
                        <span class="text-xs text-gray-400 font-[DM_Sans] font-normal ml-1">What to explain before consent</span>
                    </label>
                    <textarea name="consent_notes" rows="5"
                              placeholder="Key points to explain to the patient before obtaining consent…"
                              class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70] resize-none">{{ $sop?->consent_notes }}</textarea>
                </div>
            </div>

            {{-- SOP Status & Save --}}
            <div class="flex items-center justify-between bg-white border border-[#e8d5f0] px-5 py-4">
                <div class="flex items-center gap-3">
                    <label class="text-sm text-gray-600 font-[DM_Sans]">SOP Status:</label>
                    <select name="status"
                            class="border border-[#e8d5f0] px-3 py-1.5 text-sm font-[DM_Sans] bg-white focus:outline-none focus:border-[#6a0f70]">
                        <option value="draft"        {{ ($sop?->status ?? 'draft') === 'draft'        ? 'selected' : '' }}>Draft</option>
                        <option value="under_review" {{ ($sop?->status ?? '') === 'under_review'      ? 'selected' : '' }}>Under Review</option>
                        <option value="active"       {{ ($sop?->status ?? '') === 'active'            ? 'selected' : '' }}>Active (Published)</option>
                    </select>
                </div>
                <button type="submit"
                        class="px-5 py-2 bg-[#6a0f70] text-white text-sm font-[DM_Sans] hover:bg-[#52095a] transition">
                    Save SOP
                </button>
            </div>
        </form>
    </div>

    {{-- ════════════════════════════════════════════════════
         TAB: RULES
    ════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'rules'" class="space-y-5">
        <p class="text-sm text-gray-500 font-[DM_Sans]">
            Rules drive automatic behaviour across the app — lab case creation, consent prompts, billing caps, appointment scheduling.
        </p>

        <form method="POST" action="{{ route('treatments.rules.save', $treatment) }}" class="space-y-3">
            @csrf

            @php
                $ruleTypes = \App\Models\TreatmentRule::LABELS;
                $booleanRules = \App\Models\TreatmentRule::BOOLEAN_RULES;
                $activeRules = $treatment->rules->keyBy('rule_type');
            @endphp

            {{-- Boolean / toggle rules --}}
            <div class="bg-white border border-[#e8d5f0] p-5 space-y-3">
                <h3 class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond]">Requirements</h3>
                @foreach($booleanRules as $ruleType)
                @php $rule = $activeRules->get($ruleType); @endphp
                <div class="flex items-center justify-between py-2 border-b border-[#f5eef9] last:border-0">
                    <div>
                        <p class="text-sm font-medium text-gray-700 font-[DM_Sans]">{{ $ruleTypes[$ruleType] }}</p>
                        @if($rule?->note)
                        <p class="text-xs text-gray-400 font-[DM_Sans]">{{ $rule->note }}</p>
                        @endif
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="rules[{{ $loop->index }}][rule_type]" value="{{ $ruleType }}">
                        <input type="hidden" name="rules[{{ $loop->index }}][is_active]" value="0">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox"
                                   name="rules[{{ $loop->index }}][is_active]"
                                   value="1"
                                   {{ $rule ? 'checked' : '' }}
                                   class="w-4 h-4 accent-[#6a0f70]">
                            <span class="text-xs text-gray-500 font-[DM_Sans]">{{ $rule ? 'Required' : 'Not required' }}</span>
                        </label>
                        <input type="text"
                               name="rules[{{ $loop->index }}][note]"
                               value="{{ $rule?->note }}"
                               placeholder="Note (optional)"
                               class="border border-[#e8d5f0] px-2 py-1 text-xs font-[DM_Sans] w-48 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                </div>
                @endforeach
            </div>

            {{-- Value-based rules --}}
            @php
                $valueRules = array_diff_key($ruleTypes, array_flip($booleanRules));
                $vOffset = count($booleanRules);
            @endphp
            <div class="bg-white border border-[#e8d5f0] p-5 space-y-3">
                <h3 class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond]">Constraints & Limits</h3>
                @foreach($valueRules as $ruleType => $label)
                @php
                    $idx  = $vOffset + $loop->index;
                    $rule = $activeRules->get($ruleType);
                    $val  = $rule?->value ?? [];
                @endphp
                <div class="flex items-center justify-between py-2 border-b border-[#f5eef9] last:border-0">
                    <div>
                        <p class="text-sm font-medium text-gray-700 font-[DM_Sans]">{{ $label }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <input type="hidden" name="rules[{{ $idx }}][rule_type]" value="{{ $ruleType }}">
                        <input type="hidden" name="rules[{{ $idx }}][is_active]" value="0">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox"
                                   name="rules[{{ $idx }}][is_active]"
                                   value="1"
                                   {{ $rule ? 'checked' : '' }}
                                   class="w-4 h-4 accent-[#6a0f70]">
                        </label>
                        @if(in_array($ruleType, ['min_visits', 'max_visits', 'follow_up_days']))
                        <input type="number"
                               name="rules[{{ $idx }}][value][count]"
                               value="{{ $val['count'] ?? '' }}"
                               min="1" placeholder="Count"
                               class="border border-[#e8d5f0] px-2 py-1 text-sm font-[DM_Sans] w-24 focus:outline-none focus:border-[#6a0f70]">
                        @elseif($ruleType === 'max_discount_pct')
                        <input type="number"
                               name="rules[{{ $idx }}][value][pct]"
                               value="{{ $val['pct'] ?? '' }}"
                               min="0" max="100" step="0.01" placeholder="Max %"
                               class="border border-[#e8d5f0] px-2 py-1 text-sm font-[DM_Sans] w-24 focus:outline-none focus:border-[#6a0f70]">
                        @elseif($ruleType === 'age_restriction')
                        <input type="number" name="rules[{{ $idx }}][value][min_age]" value="{{ $val['min_age'] ?? '' }}"
                               min="0" max="120" placeholder="Min age"
                               class="border border-[#e8d5f0] px-2 py-1 text-sm font-[DM_Sans] w-20 focus:outline-none focus:border-[#6a0f70]">
                        <input type="number" name="rules[{{ $idx }}][value][max_age]" value="{{ $val['max_age'] ?? '' }}"
                               min="0" max="120" placeholder="Max age"
                               class="border border-[#e8d5f0] px-2 py-1 text-sm font-[DM_Sans] w-20 focus:outline-none focus:border-[#6a0f70]">
                        @else
                        <input type="text" name="rules[{{ $idx }}][value][text]" value="{{ $val['text'] ?? '' }}"
                               placeholder="Value"
                               class="border border-[#e8d5f0] px-2 py-1 text-sm font-[DM_Sans] w-40 focus:outline-none focus:border-[#6a0f70]">
                        @endif
                        <input type="text" name="rules[{{ $idx }}][note]"
                               value="{{ $rule?->note }}"
                               placeholder="Note"
                               class="border border-[#e8d5f0] px-2 py-1 text-xs font-[DM_Sans] w-40 focus:outline-none focus:border-[#6a0f70]">
                    </div>
                </div>
                @endforeach
            </div>

            <div class="flex justify-end">
                <button type="submit"
                        class="px-5 py-2 bg-[#6a0f70] text-white text-sm font-[DM_Sans] hover:bg-[#52095a] transition">
                    Save Rules
                </button>
            </div>
        </form>
    </div>

    {{-- ════════════════════════════════════════════════════
         TAB: MEDIA
    ════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'media'" x-data="{ showUpload: false }" class="space-y-5">

        <div class="flex justify-end">
            <button @click="showUpload = !showUpload"
                    class="flex items-center gap-2 px-4 py-2 bg-[#6a0f70] text-white text-sm font-[DM_Sans] hover:bg-[#52095a] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add Media
            </button>
        </div>

        {{-- Upload form --}}
        <div x-show="showUpload" class="bg-white border border-[#e8d5f0] p-5 space-y-4">
            <h3 class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond]">Add Media / Document</h3>
            <form method="POST" action="{{ route('treatments.media.upload', $treatment) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Type *</label>
                        <select name="media_type" required
                                class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] bg-white focus:outline-none focus:border-[#6a0f70]">
                            @foreach(\App\Models\TreatmentMedia::TYPE_LABELS as $val => $lbl)
                                <option value="{{ $val }}">{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Label *</label>
                        <input type="text" name="label" required placeholder="e.g. Pre-Op X-Ray Protocol"
                               class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Upload File</label>
                        <input type="file" name="file" class="w-full text-sm font-[DM_Sans] text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:border file:border-[#e8d5f0] file:text-xs file:font-[DM_Sans] file:bg-[#f3e8f9] file:text-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Or External URL</label>
                        <input type="url" name="external_url" placeholder="https://youtube.com/…"
                               class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="showUpload = false"
                            class="px-4 py-2 text-sm text-gray-500 font-[DM_Sans] border border-[#e8d5f0] hover:bg-gray-50">
                        Cancel
                    </button>
                    <button type="submit"
                            class="px-5 py-2 bg-[#6a0f70] text-white text-sm font-[DM_Sans] hover:bg-[#52095a] transition">
                        Upload
                    </button>
                </div>
            </form>
        </div>

        {{-- Media Grid --}}
        @if($treatment->media->isEmpty())
        <div class="text-center py-12 text-gray-400 font-[DM_Sans] border border-dashed border-[#e8d5f0]">
            No media yet. Add images, videos, PDFs, consent forms, or instruction sheets.
        </div>
        @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($treatment->media as $media)
            <div class="bg-white border border-[#e8d5f0] p-4 flex gap-3">
                {{-- Icon --}}
                <div class="w-10 h-10 flex-shrink-0 bg-[#f3e8f9] flex items-center justify-center">
                    <svg class="w-5 h-5 text-[#6a0f70]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                        {!! $media->icon !!}
                    </svg>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800 font-[DM_Sans] truncate">{{ $media->label }}</p>
                    <p class="text-xs text-gray-400 font-[DM_Sans]">{{ $media->type_label }}</p>
                    @if($media->file_size)
                    <p class="text-xs text-gray-400 font-[DM_Sans]">{{ $media->file_size_human }}</p>
                    @endif
                </div>
                <div class="flex flex-col gap-1 flex-shrink-0">
                    @if($media->url)
                    <a href="{{ $media->url }}" target="_blank"
                       class="text-xs text-[#6a0f70] hover:underline font-[DM_Sans]">View</a>
                    @endif
                    <form method="POST" action="{{ route('treatments.media.delete', $media) }}"
                          onsubmit="return confirm('Remove this file?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-400 hover:text-red-600 font-[DM_Sans]">Remove</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- ════════════════════════════════════════════════════
         TAB: REVIEW
    ════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'review'" class="space-y-5">

        {{-- Mark Reviewed form --}}
        <div class="bg-white border border-[#e8d5f0] p-5 space-y-4">
            <h3 class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond]">Mark SOP as Reviewed</h3>
            <form method="POST" action="{{ route('treatments.review.mark', $treatment) }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Next Review Date</label>
                        <input type="date" name="next_review_at"
                               value="{{ optional($treatment->activeSop?->next_review_at)->format('Y-m-d') }}"
                               class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Review Notes</label>
                        <input type="text" name="review_notes" placeholder="What was checked / updated…"
                               class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit"
                            class="px-5 py-2 bg-[#6a0f70] text-white text-sm font-[DM_Sans] hover:bg-[#52095a] transition">
                        Mark Reviewed Today
                    </button>
                </div>
            </form>
        </div>

        {{-- Review history --}}
        <div class="bg-white border border-[#e8d5f0] p-5">
            <h3 class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond] mb-4">Version History</h3>
            @forelse($treatment->sops as $sopVersion)
            <div class="flex items-start gap-4 py-3 border-b border-[#f5eef9] last:border-0">
                <div class="flex-shrink-0 text-center">
                    <span class="block text-lg font-semibold text-[#380740] font-[Cormorant_Garamond]">v{{ $sopVersion->version }}</span>
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="px-2 py-0.5 text-xs font-[DM_Sans]
                            {{ $sopVersion->status === 'active' ? 'bg-green-50 text-green-700 border border-green-200' :
                               ($sopVersion->status === 'under_review' ? 'bg-yellow-50 text-yellow-700 border border-yellow-200' :
                               ($sopVersion->status === 'draft' ? 'bg-gray-50 text-gray-500 border border-gray-200' :
                               'bg-gray-100 text-gray-400 border border-gray-200')) }}">
                            {{ ucfirst(str_replace('_', ' ', $sopVersion->status)) }}
                        </span>
                        @if($sopVersion->last_reviewed_at)
                        <span class="text-xs text-gray-400 font-[DM_Sans]">
                            Reviewed {{ $sopVersion->last_reviewed_at->format('d M Y') }}
                            @if($sopVersion->reviewer) by {{ $sopVersion->reviewer->name }} @endif
                        </span>
                        @endif
                    </div>
                    @if($sopVersion->review_notes)
                    <p class="text-xs text-gray-500 font-[DM_Sans]">{{ $sopVersion->review_notes }}</p>
                    @endif
                    @if($sopVersion->next_review_at)
                    <p class="text-xs {{ $sopVersion->next_review_at->isPast() ? 'text-red-500' : 'text-gray-400' }} font-[DM_Sans]">
                        Next review: {{ $sopVersion->next_review_at->format('d M Y') }}
                    </p>
                    @endif
                </div>
                <div class="text-xs text-gray-400 font-[DM_Sans] flex-shrink-0">
                    {{ $sopVersion->created_at->format('d M Y') }}
                </div>
            </div>
            @empty
            <p class="text-sm text-gray-400 font-[DM_Sans]">No review history yet.</p>
            @endforelse
        </div>
    </div>

    {{-- ════════════════════════════════════════════════════
         TAB: USAGE
    ════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'usage'" class="space-y-5">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
            <div class="bg-white border border-[#e8d5f0] p-5">
                <p class="text-xs text-gray-400 uppercase tracking-wider font-[DM_Sans] mb-1">Used in Treatment Plans</p>
                <p class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">{{ $usageCount }}</p>
            </div>
            <div class="bg-white border border-[#e8d5f0] p-5">
                <p class="text-xs text-gray-400 uppercase tracking-wider font-[DM_Sans] mb-1">Rules Active</p>
                <p class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">{{ $treatment->rules->count() }}</p>
            </div>
            <div class="bg-white border border-[#e8d5f0] p-5">
                <p class="text-xs text-gray-400 uppercase tracking-wider font-[DM_Sans] mb-1">Media Files</p>
                <p class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">{{ $treatment->media->count() }}</p>
            </div>
        </div>

        <div class="bg-white border border-[#e8d5f0] p-5">
            <h3 class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond] mb-3">Active Rules Summary</h3>
            @forelse($treatment->rules as $rule)
            <div class="flex items-center justify-between py-2 border-b border-[#f5eef9] last:border-0">
                <span class="text-sm text-gray-700 font-[DM_Sans]">{{ $rule->label }}</span>
                @if(!$rule->isBooleanRule() && $rule->value)
                <span class="text-xs text-gray-500 font-[DM_Sans]">{{ implode(', ', array_map(fn($k,$v) => "$k: $v", array_keys($rule->value), $rule->value)) }}</span>
                @else
                <span class="text-xs text-green-600 font-[DM_Sans]">Required ✓</span>
                @endif
            </div>
            @empty
            <p class="text-sm text-gray-400 font-[DM_Sans]">No rules configured.</p>
            @endforelse
        </div>

        <div class="bg-[#faf5ff] border border-[#e8d5f0] p-4 text-sm text-gray-500 font-[DM_Sans]">
            <p class="font-medium text-gray-700 mb-1">Connected to:</p>
            <ul class="space-y-1 list-disc list-inside">
                <li>Patient treatment plans — price &amp; duration auto-fill from this record</li>
                <li>Billing — GST % and pricing rules applied automatically</li>
                @if($treatment->hasRule('lab_required'))
                <li class="text-purple-700">Lab — lab case prompt is triggered when this treatment is added</li>
                @endif
                @if($treatment->hasRule('consent_required'))
                <li class="text-blue-700">Consent — consent form required flag is active</li>
                @endif
                @if($treatment->hasRule('xray_required'))
                <li class="text-amber-700">Clinical — X-ray required flag is active</li>
                @endif
            </ul>
        </div>
    </div>

</div>
@endsection
