{{-- ════════════════════════════════════════════════════════════════════
     DOCUMENTS UPLOAD MODAL — DAM Phase 2 (static prototype, no backend)
     Slide-in drawer from right | Alpine.js | Brand color #6a0f70
     State lives in parent x-data (documents-tab.blade.php):
       showUploadModal, uploadStep, uploadHasFiles,
       uploadTags, uploadTagInput, showContentSettings
     ──────────────────────────────────────────────────────────────────
     Part A: Modal shell · Step progress header · Step 1 (file selection)
             · Step 2 placeholder · Step 3 placeholder · Footer nav
     Part B: Step 2 (metadata form + eligibility toggles)
             Step 3 (review + progress bars)  ← next session
════════════════════════════════════════════════════════════════════ --}}

{{-- ── Outer overlay wrapper ────────────────────────────────────────── --}}
<div x-show="showUploadModal"
     style="display:none"
     class="fixed inset-0 z-[60] flex items-center justify-center p-4">

    {{-- ── Backdrop (click to close) ────────────────────────────────── --}}
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"
         x-show="showUploadModal"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @click="showUploadModal = false"></div>

    {{-- ── Centered modal panel ──────────────────────────────────────── --}}
    <div class="relative w-full max-w-2xl max-h-[90vh] bg-white rounded-2xl shadow-2xl
                flex flex-col overflow-hidden"
         x-show="showUploadModal"
         x-transition:enter="transition ease-out duration-250"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95">


        {{-- ════════════════════════════════════
             PANEL HEADER + STEP PROGRESS
        ════════════════════════════════════ --}}
        <div class="flex-shrink-0 px-6 py-4 border-b border-gray-100 bg-white">

            {{-- Title row --}}
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-gray-900">Upload Clinical Files</h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        Step <span x-text="uploadStep"></span> of 3
                    </p>
                </div>
                <button @click="showUploadModal = false"
                        class="w-8 h-8 flex items-center justify-center rounded-full
                               hover:bg-gray-100 text-gray-400 hover:text-gray-600
                               text-xl leading-none transition-colors">
                    &times;
                </button>
            </div>

            {{-- Step progress indicator ──────────────────────────── --}}
            <div class="flex items-center">

                {{-- Step 1 bubble + label --}}
                <div class="flex items-center gap-2 flex-shrink-0">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center
                                text-[10px] font-bold transition-colors"
                         :class="uploadStep >= 1
                             ? 'bg-[#6a0f70] text-white'
                             : 'bg-gray-100 text-gray-400'">1</div>
                    <span class="text-xs font-medium transition-colors"
                          :class="uploadStep >= 1 ? 'text-[#6a0f70]' : 'text-gray-400'">
                        Select Files
                    </span>
                </div>
                {{-- connector line --}}
                <div class="flex-1 h-px mx-3 transition-colors"
                     :class="uploadStep >= 2 ? 'bg-[#6a0f70]' : 'bg-gray-200'"></div>

                {{-- Step 2 --}}
                <div class="flex items-center gap-2 flex-shrink-0">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center
                                text-[10px] font-bold transition-colors"
                         :class="uploadStep >= 2
                             ? 'bg-[#6a0f70] text-white'
                             : 'bg-gray-100 text-gray-400'">2</div>
                    <span class="text-xs font-medium transition-colors"
                          :class="uploadStep >= 2 ? 'text-[#6a0f70]' : 'text-gray-400'">
                        Add Metadata
                    </span>
                </div>
                {{-- connector line --}}
                <div class="flex-1 h-px mx-3 transition-colors"
                     :class="uploadStep >= 3 ? 'bg-[#6a0f70]' : 'bg-gray-200'"></div>

                {{-- Step 3 --}}
                <div class="flex items-center gap-2 flex-shrink-0">
                    <div class="w-6 h-6 rounded-full flex items-center justify-center
                                text-[10px] font-bold transition-colors"
                         :class="uploadStep >= 3
                             ? 'bg-[#6a0f70] text-white'
                             : 'bg-gray-100 text-gray-400'">3</div>
                    <span class="text-xs font-medium transition-colors"
                          :class="uploadStep >= 3 ? 'text-[#6a0f70]' : 'text-gray-400'">
                        Review
                    </span>
                </div>

            </div>
            {{-- /step progress --}}

        </div>
        {{-- /panel header --}}


        {{-- ════════════════════════════════════
             STEP 1 — FILE SELECTION
             Drag-and-drop zone + selected file list
             Note: real file I/O wired in Phase 7.
             Click the zone to toggle mock "files selected" state.
        ════════════════════════════════════ --}}
        <div x-show="uploadStep === 1"
             style="display:none"
             class="flex-1 overflow-y-auto px-6 py-5">

            {{-- ── Hidden real file input ──────────────────────────── --}}
            <input type="file" multiple x-ref="fileInput" class="hidden"
                   accept=".jpg,.jpeg,.png,.mp4,.mov,.pdf,.dcm,.stl,.obj,.tiff,.bmp,.opg"
                   @change="
                       let files = Array.from($event.target.files);
                       uploadFiles = files.map(f => ({
                           name: f.name,
                           size: f.size,
                           ext:  f.name.split('.').pop().toLowerCase()
                       }));
                       uploadHasFiles = uploadFiles.length > 0;
                   ">

            {{-- ── Drag-and-drop zone (click opens file picker) ──── --}}
            <div class="border-2 border-dashed rounded-xl transition-all cursor-pointer
                        flex flex-col items-center justify-center text-center py-12 px-6 mb-5"
                 :class="uploadHasFiles
                     ? 'border-[#6a0f70]/40 bg-[#f5eef9]/30'
                     : 'border-gray-200 bg-gray-50 hover:border-[#6a0f70]/50 hover:bg-[#f5eef9]/20'"
                 @click="$refs.fileInput.click()">

                {{-- Upload icon --}}
                <div class="w-14 h-14 rounded-2xl flex items-center justify-center mb-4 transition-colors"
                     :class="uploadHasFiles
                         ? 'bg-[#6a0f70]/10'
                         : 'bg-white border border-gray-200'">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none"
                         :stroke="uploadHasFiles ? '#6a0f70' : '#9ca3af'"
                         stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                </div>

                {{-- No-files text --}}
                <template x-if="!uploadHasFiles">
                    <div>
                        <p class="text-sm font-semibold text-gray-700 mb-1">
                            Drag files here or click to browse
                        </p>
                        <p class="text-xs text-gray-400">
                            Multi-file support &middot; Up to 100 MB per file
                        </p>
                    </div>
                </template>

                {{-- Files-selected text --}}
                <template x-if="uploadHasFiles">
                    <div>
                        <p class="text-sm font-semibold text-[#6a0f70] mb-1"
                           x-text="uploadFiles.length + ' file' + (uploadFiles.length !== 1 ? 's' : '') + ' selected'"></p>
                        <p class="text-xs text-gray-400">Click to add more files</p>
                    </div>
                </template>

                {{-- Accepted file types legend --}}
                <div class="flex flex-wrap items-center justify-center gap-1.5 mt-4">
                    @foreach(['JPG','PNG','MP4','MOV','PDF','DCM','STL','OBJ','TIFF','BMP'] as $ext)
                    <span class="px-2 py-0.5 text-[10px] text-gray-400 bg-white border border-gray-200 rounded">
                        {{ $ext }}
                    </span>
                    @endforeach
                </div>

            </div>
            {{-- /drag-drop zone --}}


            {{-- ── Selected file list (visible after files chosen) ── --}}
            <div x-show="uploadHasFiles" style="display:none">

                {{-- Batch mode toggle header --}}
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-xs font-bold text-gray-700 uppercase tracking-wide"
                        x-text="'Selected Files (' + uploadFiles.length + ')'"></h3>
                    {{-- Batch metadata toggle --}}
                    <label class="flex items-center gap-2 cursor-pointer select-none">
                        <span class="text-xs text-gray-500">Same metadata for all</span>
                        <div class="relative">
                            <input type="checkbox" class="sr-only peer" checked>
                            <div class="w-8 h-4 bg-gray-200 rounded-full
                                        peer peer-checked:bg-[#6a0f70] transition-colors"></div>
                            <div class="absolute top-0.5 left-0.5 w-3 h-3 bg-white rounded-full shadow
                                        peer-checked:translate-x-4 transition-transform"></div>
                        </div>
                    </label>
                </div>

                {{-- File list rows --}}
                <div class="space-y-1.5">
                    <template x-for="(file, i) in uploadFiles" :key="i">
                        <div class="flex items-center gap-2.5 px-3 py-2
                                    border border-gray-100 rounded-lg bg-gray-50/60">
                            {{-- ext badge --}}
                            <span class="text-[8px] font-bold uppercase px-1.5 py-0.5
                                         bg-[#f5eef9] text-[#6a0f70] border border-[#6a0f70]/20 rounded flex-shrink-0"
                                  x-text="file.ext"></span>
                            <span class="flex-1 text-xs text-gray-700 truncate" x-text="file.name"></span>
                            <span class="text-[10px] text-gray-400 flex-shrink-0"
                                  x-text="file.size > 1048576
                                      ? (file.size / 1048576).toFixed(1) + ' MB'
                                      : (file.size / 1024).toFixed(0) + ' KB'"></span>
                        </div>
                    </template>
                </div>

            </div>
            {{-- /selected file list --}}

        </div>
        {{-- /step 1 --}}


        {{-- ════════════════════════════════════
             STEP 2 — METADATA FORM
             Visit · Procedure · Tooth · Stage · Type override
             Tags · Notes · Content Settings (eligibility flags)
             Note: nested x-data components inherit parent scope
             in Alpine v3, so showContentSettings is accessible.
        ════════════════════════════════════ --}}
        <div x-show="uploadStep === 2"
             style="display:none"
             class="flex-1 overflow-y-auto px-6 py-5">

            {{-- ── Batch apply notice ─────────────────────────────── --}}
            <div class="bg-[#f5eef9]/60 border border-[#6a0f70]/20 rounded-lg
                        px-4 py-2.5 flex items-start gap-2.5 mb-5">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="#6a0f70" stroke-width="2" stroke-linecap="round"
                     stroke-linejoin="round" class="flex-shrink-0 mt-0.5">
                    <circle cx="12" cy="12" r="10"/>
                    <line x1="12" y1="8" x2="12" y2="12"/>
                    <line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <p class="text-xs text-[#6a0f70]">
                    Metadata below applies to all <strong>3 selected files</strong>.
                    Per-file overrides are available after upload.
                </p>
            </div>

            <div class="space-y-5">

                {{-- ── Row 1: Visit + Procedure ──────────────────── --}}
                <div class="grid grid-cols-2 gap-4">

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                            Visit
                            <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <select x-model="uploadVisitId"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs
                                       text-gray-700 bg-white focus:outline-none focus:border-[#6a0f70]">
                            <option value="">— No visit —</option>
                            <option value="v1">12 Jan 2025 — Root Canal</option>
                            <option value="v2">03 Mar 2025 — Crown Prep</option>
                            <option value="v3">28 May 2025 — Follow-up</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                            Treatment / Procedure
                        </label>
                        {{-- Phase 11: @change fetches protocol steps via AJAX --}}
                        <select x-model="uploadProcedure"
                                @change="fetchProtocolSteps($event.target.value)"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs
                                       text-gray-700 bg-white focus:outline-none focus:border-[#6a0f70]">
                            <option value="">— Select procedure —</option>
                            <option value="Root Canal">Root Canal Treatment</option>
                            <option value="Crown">Crown Preparation</option>
                            <option value="Implant">Implant Placement</option>
                            <option value="Scaling">Scaling &amp; Polishing</option>
                            <option value="Extraction">Extraction</option>
                            <option value="Aligner">Aligner Treatment</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                </div>
                {{-- /visit + procedure --}}

                {{-- ── Phase 11: Protocol Steps Suggestion ────────────────── --}}
                {{-- Shows after procedure selected; AJAX-loaded checklist     --}}
                <div x-show="protocolSteps.length > 0 || protocolLoading"
                     style="display:none"
                     class="border border-[#6a0f70]/25 rounded-xl overflow-hidden bg-[#f5eef9]/40">

                    {{-- Header --}}
                    <div class="flex items-center justify-between px-4 py-2.5 border-b border-[#6a0f70]/15">
                        <div class="flex items-center gap-2">
                            {{-- checklist icon --}}
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                 stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/>
                                <rect x="9" y="3" width="6" height="4" rx="1"/>
                                <line x1="9" y1="12" x2="15" y2="12"/>
                                <line x1="9" y1="16" x2="12" y2="16"/>
                            </svg>
                            <span class="text-xs font-bold text-[#6a0f70]">
                                Suggested Documentation
                            </span>
                            <span x-show="protocolName"
                                  x-text="'(' + protocolName + ')'"
                                  class="text-[10px] text-[#6a0f70]/60"></span>
                        </div>
                        <span class="text-[10px] text-[#6a0f70]/60">
                            <span x-text="protocolSteps.filter(s => s.is_required).length"></span> required
                        </span>
                    </div>

                    {{-- Loading spinner --}}
                    <div x-show="protocolLoading" style="display:none"
                         class="flex items-center justify-center py-5 gap-2">
                        <svg class="animate-spin" width="14" height="14" viewBox="0 0 24 24" fill="none"
                             stroke="#6a0f70" stroke-width="2.5" stroke-linecap="round">
                            <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                        </svg>
                        <span class="text-xs text-[#6a0f70]/60">Loading protocol…</span>
                    </div>

                    {{-- Step list --}}
                    <div x-show="!protocolLoading && protocolSteps.length > 0"
                         style="display:none"
                         class="divide-y divide-[#6a0f70]/10">
                        <template x-for="(step, idx) in protocolSteps" :key="step.id">
                            <div class="flex items-start gap-3 px-4 py-2.5">
                                {{-- Step number --}}
                                <span class="flex-shrink-0 w-5 h-5 rounded-full text-[9px] font-bold
                                             flex items-center justify-center mt-0.5
                                             bg-[#6a0f70]/10 text-[#6a0f70]"
                                      x-text="idx + 1"></span>
                                {{-- Step info --}}
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span class="text-xs font-semibold text-gray-800" x-text="step.name"></span>
                                        {{-- Required badge --}}
                                        <span x-show="step.is_required"
                                              class="px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wide
                                                     bg-red-50 text-red-500 border border-red-200 rounded flex-shrink-0">
                                            Required
                                        </span>
                                        {{-- File type badge --}}
                                        <span class="px-1.5 py-0.5 text-[8px] font-bold uppercase tracking-wide
                                                     bg-purple-50 text-purple-600 border border-purple-200 rounded flex-shrink-0"
                                              x-text="step.file_type_label"></span>
                                        {{-- Stage badge --}}
                                        <span class="px-1.5 py-0.5 text-[8px] font-semibold
                                                     bg-gray-100 text-gray-500 rounded flex-shrink-0"
                                              x-text="step.stage_label"></span>
                                    </div>
                                    <p x-show="step.description"
                                       x-text="step.description"
                                       class="text-[10px] text-gray-400 mt-0.5 leading-relaxed"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Footer hint --}}
                    <div x-show="!protocolLoading && protocolSteps.length > 0"
                         style="display:none"
                         class="px-4 py-2 bg-white/60 border-t border-[#6a0f70]/10">
                        <p class="text-[10px] text-gray-400">
                            Upload files and assign them to these steps via <strong>Stage</strong> below.
                            Steps are tracked in the Documents tab.
                        </p>
                    </div>

                </div>
                {{-- /protocol steps suggestion --}}

                {{-- ── Tooth Number — interactive FDI chart ─────────── --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                        Tooth Number(s)
                        <span class="text-gray-400 font-normal text-[10px]">FDI &middot; click to select</span>
                    </label>

                    <div class="border border-gray-200 rounded-xl p-3 bg-gray-50/50 select-none">

                        {{-- Upper arch label --}}
                        <p class="text-[8px] font-bold text-gray-400 uppercase tracking-widest text-center mb-1.5">Upper</p>

                        {{-- Upper row --}}
                        <div class="flex justify-center items-center gap-0.5 mb-0.5">
                            {{-- Upper right: 18 → 11 --}}
                            @foreach([18,17,16,15,14,13,12,11] as $t)
                            <button type="button"
                                    @click="uploadSelectedTeeth.includes({{ $t }})
                                        ? uploadSelectedTeeth = uploadSelectedTeeth.filter(n => n !== {{ $t }})
                                        : uploadSelectedTeeth.push({{ $t }})"
                                    :class="uploadSelectedTeeth.includes({{ $t }})
                                        ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                        : 'bg-white text-gray-500 border-gray-200 hover:border-[#6a0f70]/60 hover:text-[#6a0f70]'"
                                    class="w-[26px] h-[26px] text-[9px] font-bold border rounded
                                           transition-colors flex-shrink-0 flex items-center justify-center">
                                {{ $t }}
                            </button>
                            @endforeach
                            {{-- midline --}}
                            <div class="w-px h-5 bg-gray-300 mx-1 flex-shrink-0"></div>
                            {{-- Upper left: 21 → 28 --}}
                            @foreach([21,22,23,24,25,26,27,28] as $t)
                            <button type="button"
                                    @click="uploadSelectedTeeth.includes({{ $t }})
                                        ? uploadSelectedTeeth = uploadSelectedTeeth.filter(n => n !== {{ $t }})
                                        : uploadSelectedTeeth.push({{ $t }})"
                                    :class="uploadSelectedTeeth.includes({{ $t }})
                                        ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                        : 'bg-white text-gray-500 border-gray-200 hover:border-[#6a0f70]/60 hover:text-[#6a0f70]'"
                                    class="w-[26px] h-[26px] text-[9px] font-bold border rounded
                                           transition-colors flex-shrink-0 flex items-center justify-center">
                                {{ $t }}
                            </button>
                            @endforeach
                        </div>

                        {{-- Arch separator --}}
                        <div class="border-t border-dashed border-gray-200 my-2"></div>

                        {{-- Lower row --}}
                        <div class="flex justify-center items-center gap-0.5 mt-0.5">
                            {{-- Lower right: 48 → 41 --}}
                            @foreach([48,47,46,45,44,43,42,41] as $t)
                            <button type="button"
                                    @click="uploadSelectedTeeth.includes({{ $t }})
                                        ? uploadSelectedTeeth = uploadSelectedTeeth.filter(n => n !== {{ $t }})
                                        : uploadSelectedTeeth.push({{ $t }})"
                                    :class="uploadSelectedTeeth.includes({{ $t }})
                                        ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                        : 'bg-white text-gray-500 border-gray-200 hover:border-[#6a0f70]/60 hover:text-[#6a0f70]'"
                                    class="w-[26px] h-[26px] text-[9px] font-bold border rounded
                                           transition-colors flex-shrink-0 flex items-center justify-center">
                                {{ $t }}
                            </button>
                            @endforeach
                            {{-- midline --}}
                            <div class="w-px h-5 bg-gray-300 mx-1 flex-shrink-0"></div>
                            {{-- Lower left: 31 → 38 --}}
                            @foreach([31,32,33,34,35,36,37,38] as $t)
                            <button type="button"
                                    @click="uploadSelectedTeeth.includes({{ $t }})
                                        ? uploadSelectedTeeth = uploadSelectedTeeth.filter(n => n !== {{ $t }})
                                        : uploadSelectedTeeth.push({{ $t }})"
                                    :class="uploadSelectedTeeth.includes({{ $t }})
                                        ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                        : 'bg-white text-gray-500 border-gray-200 hover:border-[#6a0f70]/60 hover:text-[#6a0f70]'"
                                    class="w-[26px] h-[26px] text-[9px] font-bold border rounded
                                           transition-colors flex-shrink-0 flex items-center justify-center">
                                {{ $t }}
                            </button>
                            @endforeach
                        </div>

                        {{-- Lower arch label --}}
                        <p class="text-[8px] font-bold text-gray-400 uppercase tracking-widest text-center mt-1.5">Lower</p>

                        {{-- Selected teeth badges --}}
                        <div x-show="uploadSelectedTeeth.length > 0"
                             style="display:none"
                             class="mt-2.5 pt-2.5 border-t border-gray-200 flex flex-wrap items-center gap-1">
                            <span class="text-[9px] text-gray-500 font-semibold mr-0.5">Selected:</span>
                            <template x-for="t in uploadSelectedTeeth.slice().sort((a,b)=>a-b)" :key="t">
                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 text-[9px]
                                             font-bold bg-[#6a0f70] text-white rounded">
                                    <span x-text="t"></span>
                                </span>
                            </template>
                            <button type="button"
                                    @click="uploadSelectedTeeth = []"
                                    class="ml-auto text-[9px] text-gray-400 hover:text-red-500 transition-colors">
                                Clear all
                            </button>
                        </div>

                    </div>
                </div>
                {{-- /tooth chart --}}

                {{-- ── Stage — pill-style toggle buttons (bound to parent uploadStage) -- --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Stage</label>
                    <div class="flex flex-wrap gap-2">
                        @foreach([
                            ['val' => 'general',  'label' => 'General'],
                            ['val' => 'before',   'label' => 'Before'],
                            ['val' => 'during',   'label' => 'During'],
                            ['val' => 'after',    'label' => 'After'],
                            ['val' => 'followup', 'label' => 'Follow-up'],
                        ] as $s)
                        <button type="button"
                                @click="uploadStage = '{{ $s['val'] }}'"
                                :class="uploadStage === '{{ $s['val'] }}'
                                    ? 'bg-[#6a0f70] text-white border-[#6a0f70]'
                                    : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300'"
                                class="px-3 py-1.5 text-xs font-medium border rounded-lg transition-colors">
                            {{ $s['label'] }}
                        </button>
                        @endforeach
                    </div>
                </div>
                {{-- /stage --}}

                {{-- ── File type override (collapsible) ──────────────── --}}
                <div x-data="{ showTypeOverride: false }"
                     class="border border-gray-200 rounded-lg overflow-hidden">
                    <button type="button"
                            @click="showTypeOverride = !showTypeOverride"
                            class="w-full flex items-center justify-between px-3.5 py-2.5
                                   text-xs font-medium text-gray-500 hover:bg-gray-50 transition-colors">
                        <span class="flex items-center gap-1.5">
                            {{-- wand icon --}}
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="9 11 12 14 22 4"/>
                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                            </svg>
                            Override auto-detected file type
                        </span>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2.5"
                             stroke-linecap="round" stroke-linejoin="round"
                             :class="showTypeOverride ? 'rotate-180' : ''"
                             class="transition-transform flex-shrink-0">
                            <polyline points="6 9 12 15 18 9"/>
                        </svg>
                    </button>
                    <div x-show="showTypeOverride" style="display:none"
                         class="px-3.5 pb-3.5 border-t border-gray-100 pt-3">
                        <select class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs
                                       text-gray-700 bg-white focus:outline-none focus:border-[#6a0f70]">
                            <option value="">— Keep auto-detected type —</option>
                            <option value="photo">Photo</option>
                            <option value="video">Video</option>
                            <option value="xray">X-ray / IOPA</option>
                            <option value="opg">OPG</option>
                            <option value="cbct">CBCT</option>
                            <option value="stl">STL / Scan</option>
                            <option value="pdf">PDF</option>
                            <option value="consent">Consent</option>
                            <option value="lab_slip">Lab Slip</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                {{-- /type override --}}

                {{-- ── Tags chip input ─────────────────────────────── --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                        Tags
                        <span class="text-gray-400 font-normal">Enter or comma to add</span>
                    </label>
                    {{-- Chip container --}}
                    <div class="flex flex-wrap items-center gap-1.5 px-2.5 py-1.5 min-h-[38px]
                                border border-gray-200 rounded-lg bg-white
                                focus-within:border-[#6a0f70] transition-colors cursor-text">
                        {{-- Existing chips --}}
                        <template x-for="(tag, i) in uploadTags" :key="i">
                            <span class="inline-flex items-center gap-0.5 px-2 py-0.5 text-xs font-medium
                                         bg-[#f5eef9] text-[#6a0f70] border border-[#6a0f70]/25 rounded-full">
                                <span x-text="tag"></span>
                                <button type="button"
                                        @click="uploadTags.splice(i, 1)"
                                        class="ml-0.5 text-[#6a0f70]/50 hover:text-[#6a0f70]
                                               leading-none text-[13px]">&times;</button>
                            </span>
                        </template>
                        {{-- Input --}}
                        <input x-model="uploadTagInput"
                               @keydown.enter.prevent="
                                   let t = uploadTagInput.trim();
                                   if (t) { uploadTags.push(t); uploadTagInput = ''; }
                               "
                               @input="
                                   if (uploadTagInput.endsWith(',')) {
                                       let t = uploadTagInput.slice(0, -1).trim();
                                       if (t) uploadTags.push(t);
                                       uploadTagInput = '';
                                   }
                               "
                               type="text"
                               placeholder="e.g. implant, urgent, follow-up…"
                               class="flex-1 min-w-[100px] text-xs text-gray-700
                                      outline-none bg-transparent py-0.5">
                    </div>
                </div>
                {{-- /tags --}}

                {{-- ── Notes ──────────────────────────────────────── --}}
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">
                        Notes
                        <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <textarea rows="3"
                              x-model="uploadNotes"
                              placeholder="Any clinical notes about these files…"
                              class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs
                                     text-gray-700 bg-white resize-none
                                     focus:outline-none focus:border-[#6a0f70]"></textarea>
                </div>
                {{-- /notes --}}

                {{-- ── Content Settings — eligibility flags (collapsible) ── --}}
                {{-- Uses nested x-data for toggle state (mkt/edu/teach/research/caseLib).    --}}
                {{-- showContentSettings lives in parent x-data (inherited via Alpine v3).     --}}
                <div class="border border-gray-200 rounded-xl overflow-hidden"
                     x-data="{
                         mkt:      false,
                         edu:      false,
                         teach:    false,
                         research: false,
                         caseLib:  false
                     }">

                    {{-- Collapsible header --}}
                    <button type="button"
                            @click="showContentSettings = !showContentSettings"
                            class="w-full flex items-center justify-between px-4 py-3
                                   text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                        <span class="flex items-center gap-2">
                            {{-- settings icon --}}
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                                 stroke="#6a0f70" stroke-width="2"
                                 stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="3"/>
                                <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06
                                         a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0
                                         v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06
                                         a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15
                                         a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9
                                         a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06
                                         A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0
                                         v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06
                                         a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9
                                         a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
                            </svg>
                            Content Settings
                        </span>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-gray-400 font-normal">
                                Mark files for other library surfaces
                            </span>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2.5"
                                 stroke-linecap="round" stroke-linejoin="round"
                                 :class="showContentSettings ? 'rotate-180' : ''"
                                 class="transition-transform flex-shrink-0">
                                <polyline points="6 9 12 15 18 9"/>
                            </svg>
                        </div>
                    </button>

                    {{-- Eligibility toggle rows --}}
                    <div x-show="showContentSettings"
                         style="display:none"
                         class="divide-y divide-gray-100 border-t border-gray-100">

                        {{-- Helper macro: each row is label + description + toggle switch --}}

                        {{-- Marketing Eligible --}}
                        <div class="flex items-center justify-between px-4 py-3">
                            <div class="pr-4">
                                <p class="text-xs font-semibold text-gray-700">Marketing Eligible</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">
                                    May appear in marketing materials pending approval
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input type="checkbox" class="sr-only peer" x-model="mkt">
                                <div class="w-9 h-5 bg-gray-200 rounded-full
                                            peer peer-checked:bg-[#6a0f70] transition-colors"></div>
                                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full
                                            shadow peer-checked:translate-x-4 transition-transform"></div>
                            </label>
                        </div>

                        {{-- Education Eligible --}}
                        <div class="flex items-center justify-between px-4 py-3">
                            <div class="pr-4">
                                <p class="text-xs font-semibold text-gray-700">Education Eligible</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">
                                    Available in the Education Library for patient education
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input type="checkbox" class="sr-only peer" x-model="edu">
                                <div class="w-9 h-5 bg-gray-200 rounded-full
                                            peer peer-checked:bg-[#6a0f70] transition-colors"></div>
                                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full
                                            shadow peer-checked:translate-x-4 transition-transform"></div>
                            </label>
                        </div>

                        {{-- Teaching Eligible --}}
                        <div class="flex items-center justify-between px-4 py-3">
                            <div class="pr-4">
                                <p class="text-xs font-semibold text-gray-700">Teaching Eligible</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">
                                    May be used in training sessions and conference presentations
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input type="checkbox" class="sr-only peer" x-model="teach">
                                <div class="w-9 h-5 bg-gray-200 rounded-full
                                            peer peer-checked:bg-[#6a0f70] transition-colors"></div>
                                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full
                                            shadow peer-checked:translate-x-4 transition-transform"></div>
                            </label>
                        </div>

                        {{-- Research Eligible --}}
                        <div class="flex items-center justify-between px-4 py-3">
                            <div class="pr-4">
                                <p class="text-xs font-semibold text-gray-700">Research Eligible</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">
                                    May be included in anonymised research datasets
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input type="checkbox" class="sr-only peer" x-model="research">
                                <div class="w-9 h-5 bg-gray-200 rounded-full
                                            peer peer-checked:bg-[#6a0f70] transition-colors"></div>
                                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full
                                            shadow peer-checked:translate-x-4 transition-transform"></div>
                            </label>
                        </div>

                        {{-- Case Library Eligible --}}
                        <div class="flex items-center justify-between px-4 py-3">
                            <div class="pr-4">
                                <p class="text-xs font-semibold text-gray-700">Case Library Eligible</p>
                                <p class="text-[10px] text-gray-400 mt-0.5">
                                    Can appear in anonymised before/after case showcases
                                </p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0">
                                <input type="checkbox" class="sr-only peer" x-model="caseLib">
                                <div class="w-9 h-5 bg-gray-200 rounded-full
                                            peer peer-checked:bg-[#6a0f70] transition-colors"></div>
                                <div class="absolute left-0.5 top-0.5 w-4 h-4 bg-white rounded-full
                                            shadow peer-checked:translate-x-4 transition-transform"></div>
                            </label>
                        </div>

                    </div>
                    {{-- /eligibility toggle rows --}}

                </div>
                {{-- /content settings --}}

            </div>
            {{-- /space-y-5 --}}

        </div>
        {{-- /step 2 --}}


        {{-- ════════════════════════════════════
             STEP 3 — REVIEW & UPLOAD
             File summary · Static progress bars
             Note: actual upload POST + progress wired in Phase 7.
        ════════════════════════════════════ --}}
        <div x-show="uploadStep === 3"
             style="display:none"
             class="flex-1 overflow-y-auto px-6 py-5 space-y-5">

            {{-- ── Ready to upload heading ─────────────────────────── --}}
            <div>
                <h3 class="text-sm font-bold text-gray-800 mb-0.5">Ready to upload</h3>
                <p class="text-xs text-gray-400">
                    Review the summary below, then click <strong>Upload Files</strong>.
                </p>
            </div>

            {{-- ── File review cards (dynamic from uploadFiles) ──── --}}
            <div class="space-y-3">
                <template x-for="(file, i) in uploadFiles" :key="i">
                    <div class="bg-white border border-gray-200 rounded-xl p-3.5">
                        <div class="flex items-start gap-3">
                            {{-- Icon — colour by file type --}}
                            <div class="w-9 h-9 rounded-lg flex items-center justify-center flex-shrink-0"
                                 :class="{
                                     'bg-gray-800': ['dcm','dicom'].includes(file.ext),
                                     'bg-rose-50':  ['jpg','jpeg','png','bmp','tiff'].includes(file.ext),
                                     'bg-blue-50':  ['mp4','mov'].includes(file.ext),
                                     'bg-green-50': ['stl','obj'].includes(file.ext),
                                     'bg-red-50':   file.ext === 'pdf',
                                     'bg-gray-100': !['dcm','dicom','jpg','jpeg','png','bmp','tiff','mp4','mov','stl','obj','pdf'].includes(file.ext)
                                 }">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none"
                                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                                     :stroke="['dcm','dicom'].includes(file.ext) ? '#94a3b8'
                                            : ['jpg','jpeg','png','bmp','tiff'].includes(file.ext) ? '#f43f5e'
                                            : file.ext === 'pdf' ? '#ef4444'
                                            : ['mp4','mov'].includes(file.ext) ? '#3b82f6'
                                            : ['stl','obj'].includes(file.ext) ? '#22c55e'
                                            : '#9ca3af'">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                {{-- Filename + type badge --}}
                                <div class="flex items-center gap-2 flex-wrap mb-1.5">
                                    <span class="text-xs font-semibold text-gray-800 truncate max-w-[200px]"
                                          x-text="file.name"></span>
                                    <span class="px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide
                                                 bg-purple-50 text-purple-700 border border-purple-200 rounded"
                                          x-text="{
                                              dcm:'X-Ray', dicom:'X-Ray',
                                              jpg:'Photo', jpeg:'Photo', png:'Photo', bmp:'Photo', tiff:'Photo',
                                              pdf:'PDF', mp4:'Video', mov:'Video',
                                              stl:'STL', obj:'STL', opg:'OPG', cbct:'CBCT'
                                          }[file.ext] ?? file.ext.toUpperCase()">
                                    </span>
                                </div>
                                {{-- Size --}}
                                <div class="flex flex-wrap gap-x-4 gap-y-0.5 mb-2.5">
                                    <span class="text-[10px] text-gray-400"
                                          x-text="file.size > 1048576
                                              ? (file.size / 1048576).toFixed(1) + ' MB'
                                              : (file.size / 1024).toFixed(0) + ' KB'"></span>
                                    {{-- Selected teeth (if any) --}}
                                    <span x-show="uploadSelectedTeeth.length > 0"
                                          class="text-[10px] text-gray-400"
                                          x-text="'Tooth ' + uploadSelectedTeeth.slice().sort((a,b)=>a-b).join(', ')"></span>
                                </div>
                                {{-- Real progress bar --}}
                                <div class="h-1 bg-gray-100 rounded-full overflow-hidden mb-1">
                                    <div class="h-1 bg-[#6a0f70] rounded-full transition-all duration-300"
                                         :style="'width:' + (uploadProgress[i] ?? 0) + '%'"></div>
                                </div>
                                <p class="text-[9px] transition-colors"
                                   :class="(uploadProgress[i] ?? 0) === 100 ? 'text-green-500 font-semibold' : 'text-gray-400'"
                                   x-text="(uploadProgress[i] ?? 0) === 100
                                       ? '✓ Uploaded'
                                       : uploadStatus === 'uploading'
                                           ? (uploadProgress[i] ?? 0) + '% uploading…'
                                           : 'Waiting to upload…'"></p>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
            {{-- /file review cards --}}

            {{-- ── Error banner (if any file failed) ──────────────── --}}
            <template x-if="uploadErrors.length > 0">
                <div class="bg-red-50 border border-red-100 rounded-lg px-4 py-3">
                    <p class="text-[10px] font-semibold text-red-600 mb-1">Some files failed to upload:</p>
                    <template x-for="(err, ei) in uploadErrors" :key="ei">
                        <p class="text-[10px] text-red-500" x-text="err"></p>
                    </template>
                </div>
            </template>

            {{-- ── Total size summary (dynamic) ───────────────────── --}}
            <div class="flex items-center justify-between text-[10px] text-gray-400
                        border-t border-gray-100 pt-3">
                <span x-text="uploadFiles.length + ' file' + (uploadFiles.length !== 1 ? 's' : '')
                    + ' · '
                    + (uploadFiles.reduce((s, f) => s + f.size, 0) / 1048576).toFixed(1)
                    + ' MB total'"></span>
                <span>Storage: Local disk</span>
            </div>


        </div>
        {{-- /step 3 --}}


        {{-- ════════════════════════════════════
             FOOTER — Step navigation
        ════════════════════════════════════ --}}
        <div class="flex-shrink-0 px-6 py-4 border-t border-gray-100 bg-white
                    flex items-center justify-between">

            {{-- Left: Cancel (step 1) / Back (steps 2–3) --}}
            <div>
                <button x-show="uploadStep === 1"
                        @click="showUploadModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-500 border border-gray-200
                               rounded-lg hover:bg-gray-50 transition-colors">
                    Cancel
                </button>
                <button x-show="uploadStep > 1"
                        style="display:none"
                        @click="uploadStep--"
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium
                               text-gray-600 border border-gray-200 rounded-lg
                               hover:bg-gray-50 transition-colors">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"/>
                    </svg>
                    Back
                </button>
            </div>

            {{-- Right: Next (steps 1–2) / Upload (step 3) --}}
            <div>
                {{-- Next — disabled on step 1 until files selected --}}
                <button x-show="uploadStep < 3"
                        @click="uploadStep++"
                        :disabled="uploadStep === 1 && !uploadHasFiles"
                        class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold
                               text-white rounded-lg transition-colors"
                        :class="(uploadStep === 1 && !uploadHasFiles)
                            ? 'bg-gray-200 text-gray-400 cursor-not-allowed'
                            : 'bg-[#6a0f70] hover:bg-[#380740]'">
                    Next
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"/>
                    </svg>
                </button>
                {{-- Upload — step 3 only --}}
                <button x-show="uploadStep === 3"
                        style="display:none"
                        @click="uploadAllFiles('{{ route('clinical-files.store', $patient) }}')"
                        :disabled="uploadStatus === 'uploading' || uploadStatus === 'done'"
                        :class="(uploadStatus === 'uploading' || uploadStatus === 'done')
                            ? 'bg-gray-300 text-gray-500 cursor-not-allowed'
                            : 'bg-[#6a0f70] hover:bg-[#380740] text-white'"
                        class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold
                               rounded-lg transition-colors">
                    <svg x-show="uploadStatus !== 'uploading'" width="13" height="13"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    {{-- Spinner while uploading --}}
                    <svg x-show="uploadStatus === 'uploading'" style="display:none"
                         class="animate-spin" width="13" height="13"
                         viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12a9 9 0 1 1-6.219-8.56"/>
                    </svg>
                    <span x-text="uploadStatus === 'uploading' ? 'Uploading…'
                                : uploadStatus === 'done'      ? 'Done!'
                                : 'Upload Files'"></span>
                </button>
            </div>

        </div>
        {{-- /footer --}}

    </div>
    {{-- /drawer panel --}}

</div>
{{-- /upload modal --}}
