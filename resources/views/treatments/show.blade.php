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
        // Share modal
        shareModal: false,
        shareType: '',
        shareTitle: '',
        shareText: '',
        sharePdfUrl: '',
        patientSearch: '',
        patientResults: [],
        selectedPatient: null,
        searchTimeout: null,
        openShare(type, title, text, pdfUrl) {
            this.shareType = type;
            this.shareTitle = title;
            this.shareText = text;
            this.sharePdfUrl = pdfUrl;
            this.selectedPatient = null;
            this.patientSearch = '';
            this.patientResults = [];
            this.shareModal = true;
        },
        searchPatients() {
            clearTimeout(this.searchTimeout);
            if (this.patientSearch.length < 2) { this.patientResults = []; return; }
            this.searchTimeout = setTimeout(async () => {
                const res = await fetch(`{{ route('treatments.patients.search') }}?q=` + encodeURIComponent(this.patientSearch));
                this.patientResults = await res.json();
            }, 300);
        },
        selectPatient(p) { this.selectedPatient = p; this.patientSearch = p.name; this.patientResults = []; },
        whatsappUrl() {
            if (!this.selectedPatient?.phone) return null;
            const phone = this.selectedPatient.phone.replace(/\D/g,'');
            const text  = encodeURIComponent(this.shareTitle + '\nTreatment: {{ $treatment->name }}\n\n' + this.shareText + (this.sharePdfUrl ? '\n\nDocument: ' + this.sharePdfUrl : ''));
            return 'https://wa.me/' + phone + '?text=' + text;
        },
        emailUrl() {
            if (!this.selectedPatient?.email) return null;
            const subject = encodeURIComponent('{{ $treatment->name }} — ' + this.shareTitle);
            const body    = encodeURIComponent(this.shareTitle + '\nTreatment: {{ $treatment->name }}\n\n' + this.shareText + (this.sharePdfUrl ? '\n\nPlease refer to the document shared by your clinic.' : ''));
            return 'mailto:' + this.selectedPatient.email + '?subject=' + subject + '&body=' + body;
        },
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
                ['overview',      'Overview'],
                ['intelligence',  'Intelligence'],
                ['sop',           'SOP'],
                ['stages',     'Stages'],
                ['rules',      'Rules'],
                ['media',      'Media'],
                ['materials',  'Patient Materials'],
                ['review',     'Review'],
                ['usage',      'Usage'],
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

                    {{-- Lab linkage --}}
                    <div class="border border-amber-200 bg-amber-50 rounded-lg p-4 space-y-3"
                         x-data="{ needsLab: {{ $treatment->needs_lab ? 'true' : 'false' }} }">
                        <div class="flex items-center gap-3">
                            <input type="hidden" name="needs_lab" value="0">
                            <input type="checkbox" name="needs_lab" value="1" id="needs_lab"
                                   x-model="needsLab"
                                   {{ $treatment->needs_lab ? 'checked' : '' }}
                                   class="w-4 h-4 accent-amber-600">
                            <label for="needs_lab" class="text-sm font-semibold text-amber-800 font-[DM_Sans]">
                                Requires Lab Work
                            </label>
                        </div>
                        <p class="text-xs text-amber-700 font-[DM_Sans]">
                            When checked, selecting this treatment in a visit will prompt the doctor to create a lab case.
                        </p>
                        <div x-show="needsLab" x-transition class="space-y-1">
                            <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Default Lab Work Category</label>
                            <select name="lab_work_category"
                                    class="w-full border border-amber-200 bg-white px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-amber-500 rounded">
                                <option value="">— Select default category —</option>
                                @foreach(\App\Models\LabCase::WORK_CATEGORIES as $cat => $subtypes)
                                <option value="{{ $cat }}" {{ $treatment->lab_work_category === $cat ? 'selected' : '' }}>
                                    {{ $cat }}
                                </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-400 font-[DM_Sans]">Pre-fills the work category in the lab case prompt.</p>
                        </div>
                    </div>
                </div>

                {{-- Right: Pricing --}}
                <div class="bg-white border border-[#e8d5f0] p-5 space-y-4">
                    <h3 class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond]">Pricing</h3>

                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Base Price (Rs. ) *</label>
                        <input type="number" name="default_price" value="{{ $treatment->default_price }}"
                               required min="0" step="0.01"
                               class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                        <p class="text-xs text-gray-400 mt-1 font-[DM_Sans]">This auto-fills when selecting this treatment in a plan or invoice.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Min Price (Rs. )</label>
                            <input type="number" name="min_price" value="{{ $treatment->min_price }}"
                                   min="0" step="0.01" placeholder="0.00"
                                   class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                        </div>
                        <div>
                            <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Max Price (Rs. )</label>
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
                            Rs. {{ number_format($total, 2) }}
                        </p>
                        <p class="text-xs text-gray-400 font-[DM_Sans]">
                            Rs. {{ number_format($treatment->default_price, 2) }} + Rs. {{ number_format($gstAmt, 2) }} GST
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
         TAB: INTELLIGENCE
    ════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'intelligence'" class="space-y-5">

        <div class="bg-purple-50 border border-purple-100 rounded-lg px-4 py-3 text-sm text-purple-700 font-[DM_Sans]">
            This tab turns the treatment into a <strong>knowledge base entry</strong>. The Consult Assist engine uses this data to suggest the right specialty module when a patient describes their problem.
        </div>

        {{-- POST to saveIntelligence; method spoofed as PUT for clarity but POST route handles it --}}
        <form method="POST" action="{{ route('treatments.intelligence.save', $treatment) }}" class="space-y-5">
            @csrf

            {{-- ── Trigger Keywords ── --}}
            <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                <div class="px-4 py-3 bg-[#faf5fb] border-b border-[#f3e8ff] flex items-center gap-2">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#6a0f70] font-[DM_Sans]">
                        Trigger Keywords
                    </span>
                    <span class="text-[10px] text-gray-400 font-[DM_Sans] font-normal normal-case tracking-normal">
                        — words in the chief complaint that activate this treatment's specialty module
                    </span>
                </div>
                <div class="p-4">
                    <input type="text" name="trigger_keywords"
                           value="{{ is_array($treatment->trigger_keywords) ? implode(', ', $treatment->trigger_keywords) : '' }}"
                           placeholder="e.g. braces, aligners, crooked, crowding, spacing, overjet"
                           class="w-full border border-gray-200 rounded-md px-3 py-2 text-sm font-[DM_Sans] text-gray-700 focus:outline-none focus:border-[#6a0f70]">
                    <p class="text-[10px] text-gray-400 font-[DM_Sans] mt-2">Comma-separated. Case-insensitive. The more specific, the better.</p>
                </div>
            </div>

            {{-- ── Patient Concerns ── --}}
            <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                <div class="px-4 py-3 bg-[#faf5fb] border-b border-[#f3e8ff]">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#6a0f70] font-[DM_Sans]">
                        Patient Concerns
                    </span>
                </div>
                <div class="p-4">
                    <div class="flex flex-wrap gap-3">
                        @php $savedConcerns = $treatment->patient_concerns ?? []; @endphp
                        @foreach(['Cosmetic concern','Functional concern','Pain concern','Preventive','Emotional / Confidence'] as $concern)
                        <label class="flex items-center gap-2 cursor-pointer font-[DM_Sans] text-sm text-gray-600">
                            <input type="checkbox" name="patient_concerns[]" value="{{ $concern }}"
                                   style="accent-color:#6a0f70;width:13px;height:13px;"
                                   {{ in_array($concern, $savedConcerns) ? 'checked' : '' }}>
                            {{ $concern }}
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ── Suggested Questions ── --}}
            @php
                $savedQuestions = $treatment->suggested_questions ?? [];
                $initQuestions  = count($savedQuestions) ? $savedQuestions : ['', '', ''];
            @endphp
            <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden"
                 x-data="{ questions: {{ json_encode($initQuestions) }} }">
                <div class="px-4 py-3 bg-[#faf5fb] border-b border-[#f3e8ff] flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#6a0f70] font-[DM_Sans]">
                        Suggested Questions
                    </span>
                    <span class="text-[10px] text-gray-400 font-[DM_Sans]">
                        Shown in Consult Assist panel during consultation
                    </span>
                </div>
                <div class="p-4 space-y-2">
                    <template x-for="(q, i) in questions" :key="i">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-purple-300 font-[DM_Sans] w-4 text-right flex-shrink-0" x-text="i+1+'.'"></span>
                            <input type="text" :name="'suggested_questions['+i+']'" x-model="questions[i]"
                                   placeholder="e.g. How long have you had crowding?"
                                   class="flex-1 border border-gray-200 rounded px-3 py-1.5 text-sm font-[DM_Sans] text-gray-700 focus:outline-none focus:border-[#6a0f70]">
                            <button type="button" @click="questions.splice(i,1)"
                                    class="text-gray-300 hover:text-red-400 text-xs font-[DM_Sans] flex-shrink-0">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="questions.push('')"
                            class="text-xs font-semibold text-[#6a0f70] font-[DM_Sans] border border-dashed border-purple-200 px-3 py-1.5 rounded hover:bg-purple-50 transition-colors">
                        + Add question
                    </button>
                </div>
            </div>

            {{-- ── Suggested Investigations ── --}}
            @php $savedInvestigations = $treatment->suggested_investigations ?? []; @endphp
            <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                <div class="px-4 py-3 bg-[#faf5fb] border-b border-[#f3e8ff]">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#6a0f70] font-[DM_Sans]">
                        Suggested Investigations
                    </span>
                </div>
                <div class="p-4">
                    <div class="flex flex-wrap gap-3">
                        @foreach(['IOPA','OPG','CBCT / RVG','Clinical photographs','Study models','Lateral ceph','Blood tests','Salivary flow test','Biopsy'] as $inv)
                        <label class="flex items-center gap-2 cursor-pointer font-[DM_Sans] text-sm text-gray-600">
                            <input type="checkbox" name="suggested_investigations[]" value="{{ $inv }}"
                                   style="accent-color:#6a0f70;width:13px;height:13px;"
                                   {{ in_array($inv, $savedInvestigations) ? 'checked' : '' }}>
                            {{ $inv }}
                        </label>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- ── Possible Diagnoses ── --}}
            @php
                $savedDiagnoses = $treatment->possible_diagnoses ?? [];
                $initDiagnoses  = count($savedDiagnoses) ? $savedDiagnoses : ['', ''];
            @endphp
            <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden"
                 x-data="{ diagnoses: {{ json_encode($initDiagnoses) }} }">
                <div class="px-4 py-3 bg-[#faf5fb] border-b border-[#f3e8ff] flex items-center justify-between">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#6a0f70] font-[DM_Sans]">
                        Possible Diagnoses
                    </span>
                    <span class="text-[10px] text-gray-400 font-[DM_Sans]">
                        Shown as suggestions in the Diagnosis section
                    </span>
                </div>
                <div class="p-4 space-y-2">
                    <template x-for="(d, i) in diagnoses" :key="i">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-purple-300 font-[DM_Sans] w-4 text-right flex-shrink-0" x-text="i+1+'.'"></span>
                            <input type="text" :name="'possible_diagnoses['+i+']'" x-model="diagnoses[i]"
                                   placeholder="e.g. Class II Div 1 Malocclusion"
                                   class="flex-1 border border-gray-200 rounded px-3 py-1.5 text-sm font-[DM_Sans] text-gray-700 focus:outline-none focus:border-[#6a0f70]">
                            <button type="button" @click="diagnoses.splice(i,1)"
                                    class="text-gray-300 hover:text-red-400 text-xs font-[DM_Sans] flex-shrink-0">✕</button>
                        </div>
                    </template>
                    <button type="button" @click="diagnoses.push('')"
                            class="text-xs font-semibold text-[#6a0f70] font-[DM_Sans] border border-dashed border-purple-200 px-3 py-1.5 rounded hover:bg-purple-50 transition-colors">
                        + Add diagnosis
                    </button>
                </div>
            </div>

            {{-- ── Specialty Tag ── --}}
            <div class="bg-white border border-[#e8d5f0] rounded-lg overflow-hidden">
                <div class="px-4 py-3 bg-[#faf5fb] border-b border-[#f3e8ff]">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#6a0f70] font-[DM_Sans]">
                        Specialty Tag
                    </span>
                </div>
                <div class="p-4 flex items-center gap-4">
                    <select name="specialty_tag"
                            class="border border-gray-200 rounded-md px-3 py-2 text-sm font-[DM_Sans] text-gray-700 focus:outline-none focus:border-[#6a0f70] min-w-[220px]">
                        <option value="">— No specialty tag —</option>
                        @foreach(['orthodontics'=>'Orthodontics','periodontics'=>'Periodontics','endodontics'=>'Endodontics','smile_design'=>'Smile Design','prosthodontics'=>'Prosthodontics','tmj'=>'TMJ','pediatric'=>'Pediatric Dentistry','oral_surgery'=>'Oral Surgery'] as $val=>$lbl)
                        <option value="{{ $val }}" {{ ($treatment->specialty_tag ?? '') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                    <p class="text-xs text-gray-400 font-[DM_Sans]">Links this treatment to a specialty module in the consultation engine.</p>
                </div>
            </div>

            {{-- Save --}}
            <div class="flex justify-end">
                <button type="submit"
                        class="px-5 py-2 bg-[#6a0f70] text-white text-sm font-semibold font-[DM_Sans] rounded-md hover:bg-[#380740] transition-colors">
                    Save Intelligence
                </button>
            </div>

        </form>

        {{-- ══════════════════════════════════════════════════════════════════
             P2C8: Performance Insights (read-only analytics)
        ══════════════════════════════════════════════════════════════════ --}}
        @if(!empty($intelligenceData))
        <div class="mt-6">
            <div class="flex items-center gap-2 mb-3">
                <span class="text-[10px] font-bold uppercase tracking-wider text-[#6a0f70] font-[DM_Sans]">Performance Insights</span>
                <span class="text-[10px] text-gray-400 font-[DM_Sans] font-normal normal-case tracking-normal">
                    — live analytics from treatment plans &amp; consultations
                </span>
            </div>

            {{-- KPI row --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                @php
                    $kpis = [
                        ['Total Uses',        $intelligenceData['total_uses'],              null,     'Times added to a treatment plan'],
                        ['Last 30 Days',      $intelligenceData['last_30d_uses'],           null,     'Uses in the last 30 days'],
                        ['Avg Price',         $intelligenceData['revenue_avg'] ? 'Rs. '.number_format($intelligenceData['revenue_avg']) : '—', null, 'Average billed price'],
                        ['Total Revenue',     $intelligenceData['revenue_total'] ? 'Rs. '.number_format($intelligenceData['revenue_total']) : '—', null, 'Sum of all billed amounts'],
                    ];
                @endphp
                @foreach($kpis as [$label, $value, $sub, $tip])
                <div class="bg-white border border-[#e8d5f0] rounded-lg p-4" title="{{ $tip }}">
                    <p class="text-[10px] text-gray-400 uppercase tracking-wider font-[DM_Sans] mb-1">{{ $label }}</p>
                    <p class="text-2xl font-semibold text-[#380740] font-[Cormorant_Garamond]">{{ $value }}</p>
                    @if($sub)<p class="text-[10px] text-gray-400 font-[DM_Sans] mt-0.5">{{ $sub }}</p>@endif
                </div>
                @endforeach
            </div>

            {{-- Price range bar --}}
            @if($intelligenceData['revenue_min'] || $intelligenceData['revenue_max'])
            <div class="bg-white border border-[#e8d5f0] rounded-lg p-4 mb-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-[#6a0f70] font-[DM_Sans] mb-3">Price Range (billed)</p>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500 font-[DM_Sans] w-16 text-right flex-shrink-0">
                        Min Rs. {{ number_format($intelligenceData['revenue_min']) }}
                    </span>
                    <div class="flex-1 h-2 bg-[#f3e8ff] rounded-full relative">
                        @php
                            $range = $intelligenceData['revenue_max'] - $intelligenceData['revenue_min'];
                            $avgPct = $range > 0
                                ? round((($intelligenceData['revenue_avg'] - $intelligenceData['revenue_min']) / $range) * 100)
                                : 50;
                            $defPct = $range > 0 && $treatment->default_price
                                ? round((($treatment->default_price - $intelligenceData['revenue_min']) / $range) * 100)
                                : null;
                        @endphp
                        <div class="h-2 bg-[#6a0f70] rounded-full" style="width:{{ $avgPct }}%;"></div>
                        @if($defPct !== null)
                        <div class="absolute top-0 h-2 w-0.5 bg-amber-500 rounded-full" style="left:{{ $defPct }}%;" title="Default price"></div>
                        @endif
                    </div>
                    <span class="text-xs text-gray-500 font-[DM_Sans] w-16 flex-shrink-0">
                        Max Rs. {{ number_format($intelligenceData['revenue_max']) }}
                    </span>
                </div>
                <p class="text-[10px] text-gray-400 font-[DM_Sans] mt-2">
                    Purple fill = avg billed price (Rs. {{ number_format($intelligenceData['revenue_avg']) }}).
                    @if($defPct !== null) Amber line = default price.@endif
                </p>
            </div>
            @endif

            {{-- Co-occurring treatments --}}
            @if($intelligenceData['co_treatments']->isNotEmpty())
            <div class="bg-white border border-[#e8d5f0] rounded-lg p-4 mb-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-[#6a0f70] font-[DM_Sans] mb-3">
                    Most Prescribed Alongside
                </p>
                <div class="space-y-2">
                    @php $maxCo = $intelligenceData['co_treatments']->first()?->cnt ?? 1; @endphp
                    @foreach($intelligenceData['co_treatments'] as $co)
                    <div class="flex items-center gap-3">
                        <span class="text-sm text-gray-700 font-[DM_Sans] w-48 flex-shrink-0 truncate">{{ $co->treatment_name }}</span>
                        <div class="flex-1 h-1.5 bg-[#f3e8ff] rounded-full">
                            <div class="h-1.5 bg-[#b95cb7] rounded-full" style="width:{{ round(($co->cnt / $maxCo) * 100) }}%;"></div>
                        </div>
                        <span class="text-[11px] text-gray-400 font-[DM_Sans] w-8 text-right flex-shrink-0">{{ $co->cnt }}×</span>
                    </div>
                    @endforeach
                </div>
                <p class="text-[10px] text-gray-400 font-[DM_Sans] mt-2">Treatments that appear in the same plans as {{ $treatment->name }}.</p>
            </div>
            @endif

            {{-- Keyword match rate --}}
            @if(!empty($treatment->trigger_keywords) && $intelligenceData['keyword_match_count'] > 0)
            <div class="bg-[#faf5fb] border border-[#e8d5f0] rounded-lg p-4">
                <p class="text-[10px] font-bold uppercase tracking-wider text-[#6a0f70] font-[DM_Sans] mb-1">
                    Keyword Reach
                </p>
                <p class="text-sm text-gray-700 font-[DM_Sans]">
                    <strong class="text-[#6a0f70]">{{ number_format($intelligenceData['keyword_match_count']) }}</strong>
                    consultation(s) contained your trigger keywords in the chief complaint.
                    This is the pool from which this treatment should ideally be suggested by Consult Assist.
                </p>
            </div>
            @endif

            @if($intelligenceData['last_used_at'])
            <p class="text-[10px] text-gray-400 font-[DM_Sans] mt-3">
                Last used: {{ \Carbon\Carbon::parse($intelligenceData['last_used_at'])->diffForHumans() }}
                ({{ \Carbon\Carbon::parse($intelligenceData['last_used_at'])->format('d M Y') }})
            </p>
            @endif
        </div>
        @else
        <div class="mt-4 text-xs text-gray-400 font-[DM_Sans] italic">
            Performance data is loaded when you navigate directly to the Intelligence tab.
        </div>
        @endif

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

            {{-- ── Helper macro: instruction section with Print / Upload PDF / Share ── --}}
            @php
                $preSheet   = $treatment->media->where('media_type','pre_care_sheet')->first();
                $postSheet  = $treatment->media->where('media_type','post_care_sheet')->first();
                $consentDoc = $treatment->media->where('media_type','consent_template')->first();
            @endphp

            {{-- Pre-Care Instructions --}}
            <div class="bg-white border border-[#e8d5f0] p-5 space-y-3">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <p class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond]">Pre-Treatment Instructions</p>
                        <p class="text-xs text-gray-400 font-[DM_Sans]">Shown / given to patient before visit</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0 flex-wrap">
                        {{-- Print --}}
                        <a href="{{ route('treatments.print', [$treatment, 'pre_op']) }}" target="_blank"
                           class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-[DM_Sans] border border-[#e8d5f0] text-[#6a0f70] hover:bg-[#f3e8f9] transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
                            </svg>
                            Print
                        </a>
                        {{-- Upload PDF --}}
                        <button type="button" @click="$dispatch('open-upload', {type:'pre_care_sheet', label:'Pre-Care Instruction Sheet'})"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-[DM_Sans] border border-[#e8d5f0] text-gray-600 hover:bg-gray-50 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            {{ $preSheet ? 'Replace PDF' : 'Upload PDF' }}
                        </button>
                        @if($preSheet)
                        <a href="{{ $preSheet->url }}" target="_blank"
                           class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-[DM_Sans] bg-green-50 border border-green-200 text-green-700 hover:bg-green-100 transition">
                            View PDF
                        </a>
                        @endif
                        {{-- Share --}}
                        <button type="button"
                                @click="openShare('pre_op','Pre-Treatment Instructions',{{ json_encode($sop?->pre_instructions ?? '') }},'{{ $preSheet?->url ?? '' }}')"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-[DM_Sans] bg-[#6a0f70] text-white hover:bg-[#52095a] transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                            </svg>
                            Share
                        </button>
                    </div>
                </div>
                <textarea name="pre_instructions" rows="6"
                          placeholder="What should the patient do / avoid before this treatment…"
                          class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70] resize-none">{{ $sop?->pre_instructions }}</textarea>
            </div>

            {{-- Post-Care Instructions --}}
            <div class="bg-white border border-[#e8d5f0] p-5 space-y-3">
                <div class="flex items-start justify-between gap-4 flex-wrap">
                    <div>
                        <p class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond]">Post-Treatment Instructions</p>
                        <p class="text-xs text-gray-400 font-[DM_Sans]">Given to patient after visit / procedure</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0 flex-wrap">
                        <a href="{{ route('treatments.print', [$treatment, 'post_op']) }}" target="_blank"
                           class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-[DM_Sans] border border-[#e8d5f0] text-[#6a0f70] hover:bg-[#f3e8f9] transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
                            </svg>
                            Print
                        </a>
                        <button type="button" @click="$dispatch('open-upload', {type:'post_care_sheet', label:'Post-Care Instruction Sheet'})"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-[DM_Sans] border border-[#e8d5f0] text-gray-600 hover:bg-gray-50 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                            </svg>
                            {{ $postSheet ? 'Replace PDF' : 'Upload PDF' }}
                        </button>
                        @if($postSheet)
                        <a href="{{ $postSheet->url }}" target="_blank"
                           class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-[DM_Sans] bg-green-50 border border-green-200 text-green-700 hover:bg-green-100 transition">
                            View PDF
                        </a>
                        @endif
                        <button type="button"
                                @click="openShare('post_op','Post-Treatment Instructions',{{ json_encode($sop?->post_instructions ?? '') }},'{{ $postSheet?->url ?? '' }}')"
                                class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-[DM_Sans] bg-[#6a0f70] text-white hover:bg-[#52095a] transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                                <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                            </svg>
                            Share
                        </button>
                    </div>
                </div>
                <textarea name="post_instructions" rows="6"
                          placeholder="What should the patient do / avoid after this treatment…"
                          class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70] resize-none">{{ $sop?->post_instructions }}</textarea>
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

                {{-- Consent Notes with actions --}}
                <div class="bg-white border border-[#e8d5f0] p-5 space-y-3">
                    <div class="flex items-start justify-between gap-3 flex-wrap">
                        <div>
                            <p class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond]">Consent Explanation</p>
                            <p class="text-xs text-gray-400 font-[DM_Sans]">Explain to patient before obtaining consent</p>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <a href="{{ route('treatments.print', [$treatment, 'consent']) }}" target="_blank"
                               class="flex items-center gap-1.5 px-2 py-1 text-xs font-[DM_Sans] border border-[#e8d5f0] text-[#6a0f70] hover:bg-[#f3e8f9] transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
                                </svg>
                                Print
                            </a>
                            <button type="button" @click="$dispatch('open-upload', {type:'consent_template', label:'Consent Form PDF'})"
                                    class="flex items-center gap-1.5 px-2 py-1 text-xs font-[DM_Sans] border border-[#e8d5f0] text-gray-600 hover:bg-gray-50 transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                                {{ $consentDoc ? 'Replace PDF' : 'Upload PDF' }}
                            </button>
                            @if($consentDoc)
                            <a href="{{ $consentDoc->url }}" target="_blank"
                               class="flex items-center gap-1.5 px-2 py-1 text-xs font-[DM_Sans] bg-green-50 border border-green-200 text-green-700 hover:bg-green-100 transition">
                                View
                            </a>
                            @endif
                            <button type="button"
                                    @click="openShare('consent','Consent Information',{{ json_encode($sop?->consent_notes ?? '') }},'{{ $consentDoc?->url ?? '' }}')"
                                    class="flex items-center gap-1.5 px-2 py-1 text-xs font-[DM_Sans] bg-[#6a0f70] text-white hover:bg-[#52095a] transition">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>
                                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/><line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                                </svg>
                                Share
                            </button>
                        </div>
                    </div>
                    <textarea name="consent_notes" rows="5"
                              placeholder="Key points to explain to the patient before obtaining consent…"
                              class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70] resize-none">{{ $sop?->consent_notes }}</textarea>
                </div>
            </div>

            {{-- ── Quick Upload PDF modal (triggered from Upload PDF buttons) ── --}}
            <div x-data="{
                    showUploadPdf: false,
                    uploadType: '',
                    uploadLabel: ''
                 }"
                 @open-upload.window="uploadType = $event.detail.type; uploadLabel = $event.detail.label; showUploadPdf = true">
                <div x-show="showUploadPdf" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center"
                     style="background:rgba(14,1,24,.55);"
                     @click.self="showUploadPdf=false">
                    <div class="bg-white w-full max-w-md mx-4 shadow-xl p-6 space-y-4" @click.stop>
                        <div class="flex items-center justify-between">
                            <h3 class="text-lg font-semibold text-[#380740] font-[Cormorant_Garamond]" x-text="'Upload PDF — ' + uploadLabel"></h3>
                            <button type="button" @click="showUploadPdf=false" class="text-gray-400 hover:text-gray-600">✕</button>
                        </div>
                        <form method="POST" action="{{ route('treatments.media.upload', $treatment) }}" enctype="multipart/form-data" class="space-y-3">
                            @csrf
                            <input type="hidden" name="media_type" :value="uploadType">
                            <input type="hidden" name="label" :value="uploadLabel">
                            <div>
                                <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Select PDF file</label>
                                <input type="file" name="file" accept=".pdf" required
                                       class="w-full text-sm font-[DM_Sans] text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:border file:border-[#e8d5f0] file:text-xs file:font-[DM_Sans] file:bg-[#f3e8f9] file:text-[#6a0f70]">
                            </div>
                            <p class="text-xs text-gray-400 font-[DM_Sans]">This PDF will appear as a downloadable/viewable attachment alongside the text instructions above.</p>
                            <div class="flex justify-end gap-3">
                                <button type="button" @click="showUploadPdf=false"
                                        class="px-4 py-2 text-sm text-gray-500 font-[DM_Sans] border border-[#e8d5f0] hover:bg-gray-50">Cancel</button>
                                <button type="submit"
                                        class="px-5 py-2 bg-[#6a0f70] text-white text-sm font-[DM_Sans] hover:bg-[#52095a]">Upload</button>
                            </div>
                        </form>
                    </div>
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
         TAB: STAGES
    ════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'stages'" class="space-y-5"
         x-data="{
             stages: {{ Js::from($treatment->stages ?? []) }},
             newLabel: '',
             addStage() {
                 const label = this.newLabel.trim();
                 if (!label) return;
                 // auto-generate key from label: lowercase, spaces→underscores, strip non-alnum
                 const key = label.toLowerCase().replace(/\s+/g,'_').replace(/[^a-z0-9_]/g,'');
                 if (this.stages.find(s => s.key === key)) { alert('A stage with this key already exists.'); return; }
                 this.stages.push({ key, label });
                 this.newLabel = '';
             },
             removeStage(i) { this.stages.splice(i, 1); },
             moveUp(i)   { if (i > 0) [this.stages[i-1], this.stages[i]] = [this.stages[i], this.stages[i-1]]; },
             moveDown(i) { if (i < this.stages.length-1) [this.stages[i+1], this.stages[i]] = [this.stages[i], this.stages[i+1]]; },
         }">

        <p class="text-sm text-gray-500 font-[DM_Sans]">
            Define the stages a patient goes through for <strong>{{ $treatment->name }}</strong>.
            These appear as progress checkpoints on every visit record.
            Order matters — drag or use arrows to reorder.
        </p>

        <form method="POST" action="{{ route('treatments.stages.save', $treatment) }}">
            @csrf

            {{-- Hidden inputs built from Alpine stages array --}}
            <template x-for="(stage, i) in stages" :key="i">
                <span>
                    <input type="hidden" :name="`stages[${i}][key]`"   :value="stage.key">
                    <input type="hidden" :name="`stages[${i}][label]`" :value="stage.label">
                </span>
            </template>

            {{-- Current stage list --}}
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-700 font-[DM_Sans]">Defined Stages</span>
                    <span class="text-xs text-gray-400 font-[DM_Sans]" x-text="stages.length + ' stage(s)'"></span>
                </div>

                <template x-if="stages.length === 0">
                    <div class="py-10 text-center text-sm text-gray-400 font-[DM_Sans]">
                        No stages defined yet. Add the first stage below.
                    </div>
                </template>

                <div class="divide-y divide-gray-100">
                    <template x-for="(stage, i) in stages" :key="stage.key">
                        <div class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 group">
                            {{-- Order badge --}}
                            <div class="flex-shrink-0 w-6 h-6 rounded-full bg-[#f3e8f5] text-[#6a0f70] text-[10px] font-bold flex items-center justify-center"
                                 x-text="i + 1"></div>

                            {{-- Key + label --}}
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-semibold text-gray-800 font-[DM_Sans]" x-text="stage.label"></div>
                                <div class="text-[10px] text-gray-400 font-mono" x-text="stage.key"></div>
                            </div>

                            {{-- Edit label inline --}}
                            <input type="text" x-model="stage.label"
                                   class="hidden group-hover:block text-sm border border-gray-200 rounded px-2 py-1 w-48 focus:outline-none focus:border-[#6a0f70] font-[DM_Sans]"
                                   placeholder="Edit label">

                            {{-- Move up/down --}}
                            <div class="flex flex-col gap-0.5 flex-shrink-0">
                                <button type="button" @click="moveUp(i)" :disabled="i === 0"
                                        class="w-5 h-5 flex items-center justify-center text-gray-300 hover:text-[#6a0f70] disabled:opacity-20 transition-colors">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>
                                </button>
                                <button type="button" @click="moveDown(i)" :disabled="i === stages.length - 1"
                                        class="w-5 h-5 flex items-center justify-center text-gray-300 hover:text-[#6a0f70] disabled:opacity-20 transition-colors">
                                    <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>
                                </button>
                            </div>

                            {{-- Remove --}}
                            <button type="button" @click="removeStage(i)"
                                    class="flex-shrink-0 p-1 text-gray-300 hover:text-red-400 hover:bg-red-50 rounded transition-colors">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                </svg>
                            </button>
                        </div>
                    </template>
                </div>

                {{-- Add new stage --}}
                <div class="px-5 py-4 border-t border-dashed border-gray-200 bg-gray-50 flex items-center gap-3">
                    <input type="text" x-model="newLabel"
                           @keydown.enter.prevent="addStage()"
                           placeholder="New stage label… (e.g. Impression Taken)"
                           class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:border-[#6a0f70] font-[DM_Sans]">
                    <button type="button" @click="addStage()"
                            class="px-4 py-2 text-sm font-semibold text-white bg-[#6a0f70] hover:bg-[#570c5d] rounded-lg transition-colors font-[DM_Sans]">
                        Add
                    </button>
                </div>
            </div>

            {{-- Save --}}
            <div class="flex justify-end pt-2">
                <button type="submit"
                        class="px-5 py-2 text-sm font-semibold text-white bg-[#6a0f70] hover:bg-[#570c5d] rounded-lg transition-colors font-[DM_Sans]">
                    Save Stages
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
         TAB: PATIENT MATERIALS (marketing docs, videos, education)
    ════════════════════════════════════════════════════ --}}
    <div x-show="activeTab === 'materials'" x-data="{ showMatUpload: false }" class="space-y-5">

        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm text-gray-500 font-[DM_Sans]">
                    Marketing documents, patient education videos, and before/after images linked to this treatment.
                    Staff can share these directly with patients via WhatsApp or Email.
                </p>
            </div>
            <button @click="showMatUpload = !showMatUpload"
                    class="flex items-center gap-2 px-4 py-2 bg-[#6a0f70] text-white text-sm font-[DM_Sans] hover:bg-[#52095a] transition flex-shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add Material
            </button>
        </div>

        {{-- Upload form --}}
        <div x-show="showMatUpload" class="bg-white border border-[#e8d5f0] p-5 space-y-4">
            <h3 class="text-base font-semibold text-[#380740] font-[Cormorant_Garamond]">Add Patient Material</h3>
            <form method="POST" action="{{ route('treatments.media.upload', $treatment) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Type *</label>
                        <select name="media_type" required
                                class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] bg-white focus:outline-none focus:border-[#6a0f70]">
                            <option value="marketing_doc">Marketing / Patient Education Doc</option>
                            <option value="video">Video (YouTube / Vimeo link)</option>
                            <option value="image">Before / After Image</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Label *</label>
                        <input type="text" name="label" required placeholder="e.g. RCT Patient Guide"
                               class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Upload File (PDF / Image)</label>
                        <input type="file" name="file" accept=".pdf,.jpg,.jpeg,.png,.gif"
                               class="w-full text-sm font-[DM_Sans] text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:border file:border-[#e8d5f0] file:text-xs file:font-[DM_Sans] file:bg-[#f3e8f9] file:text-[#6a0f70]">
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Or External URL (video / doc link)</label>
                        <input type="url" name="external_url" placeholder="https://youtube.com/…"
                               class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                    </div>
                </div>
                <div class="flex justify-end gap-3">
                    <button type="button" @click="showMatUpload = false"
                            class="px-4 py-2 text-sm text-gray-500 font-[DM_Sans] border border-[#e8d5f0] hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                            class="px-5 py-2 bg-[#6a0f70] text-white text-sm font-[DM_Sans] hover:bg-[#52095a]">Upload</button>
                </div>
            </form>
        </div>

        {{-- Materials grid --}}
        @php
            $materials = $treatment->media->whereIn('media_type', ['marketing_doc','video','image']);
        @endphp

        @if($materials->isEmpty())
        <div class="text-center py-12 text-gray-400 font-[DM_Sans] border border-dashed border-[#e8d5f0]">
            No patient materials yet. Add brochures, videos, or education docs to share with patients.
        </div>
        @else
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
            @foreach($materials as $mat)
            <div class="bg-white border border-[#e8d5f0] p-4 space-y-3">
                {{-- Type badge + label --}}
                <div class="flex items-start gap-3">
                    <div class="w-10 h-10 flex-shrink-0 bg-[#f3e8f9] flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#6a0f70]" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                            {!! $mat->icon !!}
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-medium text-gray-800 font-[DM_Sans]">{{ $mat->label }}</p>
                        <p class="text-xs text-gray-400 font-[DM_Sans]">{{ $mat->type_label }}</p>
                        @if($mat->file_size)
                        <p class="text-xs text-gray-400 font-[DM_Sans]">{{ $mat->file_size_human }}</p>
                        @endif
                    </div>
                </div>

                {{-- Action buttons --}}
                <div class="flex items-center gap-2 flex-wrap border-t border-[#f0e0f8] pt-3">
                    @if($mat->url)
                    <a href="{{ $mat->url }}" target="_blank"
                       class="flex-1 text-center px-2 py-1.5 text-xs font-[DM_Sans] border border-[#e8d5f0] text-[#6a0f70] hover:bg-[#f3e8f9] transition">
                        View / Open
                    </a>
                    @php
                        $waText = urlencode("*{$treatment->name}*\n{$mat->label}\n\n" . ($mat->url ?? ''));
                    @endphp
                    {{-- Share WhatsApp (generic — no patient pre-selected) --}}
                    <button type="button"
                            @click="openShare('marketing','{{ addslashes($mat->label) }}','{{ addslashes($mat->label) }}','{{ $mat->url }}')"
                            class="flex-1 text-center px-2 py-1.5 text-xs font-[DM_Sans] bg-[#6a0f70] text-white hover:bg-[#52095a] transition">
                        Share →
                    </button>
                    @endif
                    <form method="POST" action="{{ route('treatments.media.delete', $mat) }}"
                          onsubmit="return confirm('Remove this material?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-2 py-1.5 text-xs text-red-400 font-[DM_Sans] hover:text-red-600">Remove</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif
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

    {{-- ════════════════════════════════════════════════════
         SHARE MODAL (global — used by all Share buttons)
    ════════════════════════════════════════════════════ --}}
    <div x-show="shareModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center"
         style="background:rgba(14,1,24,.6);"
         @click.self="shareModal=false"
         @keydown.escape.window="shareModal=false">

        <div class="bg-white w-full max-w-lg mx-4 shadow-xl" @click.stop>
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#e8d5f0]">
                <div>
                    <h3 class="text-xl font-semibold text-[#380740] font-[Cormorant_Garamond]" x-text="'Share — ' + shareTitle"></h3>
                    <p class="text-xs text-gray-400 font-[DM_Sans] mt-0.5">Search for a patient, then choose how to send</p>
                </div>
                <button type="button" @click="shareModal=false" class="text-gray-400 hover:text-gray-600 text-xl">✕</button>
            </div>

            <div class="p-6 space-y-5">

                {{-- Patient search --}}
                <div class="relative">
                    <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Patient</label>
                    <input type="text"
                           x-model="patientSearch"
                           @input="searchPatients()"
                           @focus="if (selectedPatient) { selectedPatient = null; patientSearch = ''; }"
                           autocomplete="off"
                           placeholder="Search by name, phone, or email…"
                           class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">

                    {{-- Results dropdown --}}
                    <div x-show="patientResults.length > 0"
                         x-cloak
                         class="absolute z-10 top-full left-0 right-0 mt-1 bg-white border border-[#e8d5f0] shadow-lg max-h-56 overflow-y-auto">
                        <template x-for="p in patientResults" :key="p.id">
                            <button type="button"
                                    @click="selectPatient(p)"
                                    class="w-full text-left px-3 py-2 text-sm font-[DM_Sans] hover:bg-[#f3e8f9] border-b border-[#f5eef9] last:border-0">
                                <span class="block text-gray-800" x-text="p.name"></span>
                                <span class="block text-xs text-gray-400" x-text="[p.phone, p.email].filter(Boolean).join(' · ') || 'No contact details'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- Selected patient --}}
                <div x-show="selectedPatient" x-cloak
                     class="flex items-center justify-between bg-[#faf5ff] border border-[#e8d5f0] px-3 py-2">
                    <div>
                        <p class="text-sm font-medium text-gray-800 font-[DM_Sans]" x-text="selectedPatient?.name"></p>
                        <p class="text-xs text-gray-400 font-[DM_Sans]" x-text="[selectedPatient?.phone, selectedPatient?.email].filter(Boolean).join(' · ')"></p>
                    </div>
                    <button type="button" @click="selectedPatient=null; patientSearch=''"
                            class="text-xs text-gray-400 hover:text-red-500 font-[DM_Sans]">Change</button>
                </div>

                {{-- Message preview (editable) --}}
                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Message</label>
                    <textarea x-model="shareText" rows="5"
                              class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
                    <p class="text-xs text-gray-400 font-[DM_Sans] mt-1" x-show="sharePdfUrl" x-cloak>
                        A document link will be appended to the message.
                    </p>
                </div>

                {{-- Send actions --}}
                <div class="flex items-center gap-3">
                    <a :href="selectedPatient ? whatsappUrl() : null"
                       target="_blank"
                       :class="selectedPatient && selectedPatient.phone
                            ? 'bg-green-600 hover:bg-green-700 text-white cursor-pointer'
                            : 'bg-gray-100 text-gray-400 pointer-events-none cursor-not-allowed'"
                       class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-[DM_Sans] transition">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12.017 2C6.5 2 2.017 6.483 2.017 12c0 1.86.505 3.65 1.462 5.212L2 22l4.905-1.44A9.94 9.94 0 0012.017 22C17.535 22 22.017 17.518 22.017 12S17.535 2 12.017 2z"/></svg>
                        WhatsApp
                    </a>
                    <a :href="selectedPatient ? emailUrl() : null"
                       :class="selectedPatient && selectedPatient.email
                            ? 'border border-[#6a0f70] text-[#6a0f70] hover:bg-[#f3e8f9] cursor-pointer'
                            : 'border border-gray-200 text-gray-300 pointer-events-none cursor-not-allowed'"
                       class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-[DM_Sans] transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2"><path d="M4 4h16v16H4z" opacity="0"/><path d="M22 6c0-1.1-.9-2-2-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6zm-2 0l-8 5-8-5h16zm0 12H4V8l8 5 8-5v10z"/></svg>
                        Email
                    </a>
                </div>
                <p class="text-xs text-gray-400 font-[DM_Sans]" x-show="selectedPatient && !selectedPatient.phone && !selectedPatient.email" x-cloak>
                    This patient has no phone or email on file.
                </p>

            </div>
        </div>
    </div>

</div>
@endsection