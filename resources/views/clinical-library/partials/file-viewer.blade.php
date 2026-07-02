{{-- ════════════════════════════════════════════════════════════════════
     GLOBAL FILE VIEWER — Clinical Library (Phase 5)
     File: resources/views/clinical-library/partials/file-viewer.blade.php

     Included ONCE in layouts/app.blade.php (globally available).
     Opens via:
       window.dispatchEvent(new CustomEvent('open-file-viewer', { detail: { id: 123 } }))

     Architecture:
       - Full-screen backdrop
       - Slide-in drawer from right (~80vw, split 60/40)
       - Left pane:  media display + nav arrows + zoom + watermark toggle
       - Right pane: patient info, metadata, notes, tags, flags, footer  ← Part B
     Alpine.js only. Static placeholder content. No real file serving.
════════════════════════════════════════════════════════════════════ --}}

<div
    x-data="{
        {{-- ── Open / close state ── --}}
        open:        false,
        fileId:      null,

        {{-- ── Left pane state ── --}}
        mediaType:   'image',
        zoomLevel:   1,
        showWatermark: false,
        currentIndex: 1,
        totalFiles:   9,

        {{-- ── Right pane state (used in Part B) ── --}}
        editingNotes: false,
        notesValue:   'Pre-operative IOPA taken before root canal treatment on tooth 26. Good bone levels observed. No periapical pathology visible.',
        newTag:       '',
        tags:         ['rct', 'iopa', 'pre-op'],
        deleteConfirm: false,

        {{-- ── Eligibility flags (placeholder booleans) ── --}}
        flagMarketing:  false,
        flagEducation:  true,
        flagTeaching:   false,
        flagResearch:   false,
        flagCaseLib:    false,

        {{-- ── Open handler — listens for global event ── --}}
        init() {
            window.addEventListener('open-file-viewer', (e) => {
                this.fileId      = e.detail.id ?? null;
                this.open        = true;
                this.deleteConfirm = false;
                this.editingNotes  = false;
                this.zoomLevel     = 1;
                this.showWatermark = false;
                document.body.style.overflow = 'hidden';
            });
        },

        {{-- ── Close handler ── --}}
        close() {
            this.open = false;
            document.body.style.overflow = '';
        },

        {{-- ── Zoom helpers ── --}}
        zoomIn()    { this.zoomLevel = Math.min(this.zoomLevel + 0.25, 4); },
        zoomOut()   { this.zoomLevel = Math.max(this.zoomLevel - 0.25, 0.5); },
        resetZoom() { this.zoomLevel = 1; },

        {{-- ── Tag helpers ── --}}
        addTag() {
            const t = this.newTag.trim().toLowerCase();
            if (t && !this.tags.includes(t)) { this.tags.push(t); }
            this.newTag = '';
        },
        removeTag(tag) { this.tags = this.tags.filter(t => t !== tag); }
    }"
    @keydown.escape.window="open && close()"
    style="display:contents"
>

    {{-- ════════════════════════════════════
         BACKDROP
    ════════════════════════════════════ --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        @click="close()"
        class="fixed inset-0 bg-black/70 backdrop-blur-sm"
        style="display:none; z-index:65;"
        aria-hidden="true"
    ></div>

    {{-- ════════════════════════════════════
         DRAWER PANEL
         Slides in from the right
    ════════════════════════════════════ --}}
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-250"
        x-transition:enter-start="translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="translate-x-full"
        class="fixed top-0 right-0 bottom-0 flex flex-col bg-white shadow-2xl"
        style="display:none; z-index:66; width:min(82vw, 1200px);"
        role="dialog"
        aria-modal="true"
        aria-label="File viewer"
        @click.stop
    >

        {{-- ════════════════════════════════════
             DRAWER HEADER
        ════════════════════════════════════ --}}
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 flex-shrink-0 bg-white"
             style="min-height:52px;">

            {{-- Left: breadcrumb context --}}
            <div class="flex items-center gap-2 text-xs text-gray-500 min-w-0">
                <span class="font-medium text-gray-700 truncate">Pre-op IOPA</span>
                <span class="text-gray-300">/</span>
                <span class="truncate">Root Canal Treatment</span>
                <span class="text-gray-300">/</span>
                <span class="truncate">12 Jan 2025</span>
            </div>

            {{-- Right: file counter + close --}}
            <div class="flex items-center gap-3 flex-shrink-0">
                <span class="text-[11px] text-gray-400">
                    <span x-text="currentIndex"></span> of <span x-text="totalFiles"></span>
                </span>
                <button @click="close()"
                        class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400
                               hover:bg-gray-100 hover:text-gray-600 transition-colors"
                        aria-label="Close viewer">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
        </div>
        {{-- /drawer header --}}


        {{-- ════════════════════════════════════
             SPLIT BODY — left 60% / right 40%
        ════════════════════════════════════ --}}
        <div class="flex flex-1 min-h-0">

            {{-- ════════════════════════════════════
                 LEFT PANE — media display
                 60% width, dark background
            ════════════════════════════════════ --}}
            <div class="relative flex flex-col bg-gray-950" style="width:60%; flex-shrink:0;">

                {{-- ── Media display area ── --}}
                <div class="flex-1 flex items-center justify-center overflow-hidden relative min-h-0">

                    {{-- ── IMAGE placeholder ── --}}
                    <div x-show="mediaType === 'image'"
                         class="w-full h-full flex items-center justify-center p-6">
                        {{-- Placeholder: simulated X-ray image --}}
                        <div class="relative"
                             :style="'transform: scale(' + zoomLevel + '); transition: transform 0.2s ease; transform-origin: center center;'">
                            <div class="w-80 h-80 bg-gray-800 rounded-lg flex flex-col items-center justify-center
                                        border border-gray-700 relative overflow-hidden">
                                {{-- Simulated X-ray scan lines --}}
                                <div class="absolute inset-0 opacity-20"
                                     style="background: repeating-linear-gradient(0deg, transparent, transparent 3px, rgba(255,255,255,0.04) 3px, rgba(255,255,255,0.04) 4px);"></div>
                                <svg width="80" height="80" viewBox="0 0 24 24" fill="none"
                                     stroke="#4b5563" stroke-width="0.8"
                                     stroke-linecap="round" stroke-linejoin="round" class="opacity-60">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <path d="M8 12h8M12 8v8"/>
                                    <circle cx="12" cy="12" r="4"/>
                                    <path d="M9 9l6 6M15 9l-6 6"/>
                                </svg>
                                <p class="text-gray-600 text-[10px] mt-3 font-mono">IOPA · Tooth 26 · Before</p>
                                {{-- Watermark overlay (toggleable) --}}
                                <div x-show="showWatermark"
                                     class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <div class="text-white/20 text-lg font-bold tracking-widest rotate-[-30deg] text-center"
                                         style="font-family: monospace; font-size: 11px; line-height: 2;">
                                        TULIP DENTAL CLINIC<br>
                                        Dr. Sharma · 12 Jan 2025<br>
                                        Root Canal · Tooth 26
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- /image --}}

                    {{-- ── VIDEO placeholder ── --}}
                    <div x-show="mediaType === 'video'" style="display:none"
                         class="w-full h-full flex items-center justify-center p-6">
                        <div class="w-full max-w-lg aspect-video bg-gray-900 rounded-lg flex items-center justify-center border border-gray-700">
                            <div class="text-center">
                                <div class="w-14 h-14 rounded-full bg-white/10 flex items-center justify-center mx-auto mb-3 cursor-pointer hover:bg-white/20 transition-colors">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="white" stroke="none">
                                        <polygon points="5 3 19 12 5 21 5 3"/>
                                    </svg>
                                </div>
                                <p class="text-gray-500 text-xs">Impression recording · 2m 14s</p>
                            </div>
                        </div>
                    </div>
                    {{-- /video --}}

                    {{-- ── PDF placeholder ── --}}
                    <div x-show="mediaType === 'pdf'" style="display:none"
                         class="w-full h-full flex items-center justify-center p-6">
                        <div class="w-full max-w-sm bg-white rounded-lg shadow-xl p-8 flex flex-col items-center">
                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none"
                                 stroke="#f97316" stroke-width="1.2"
                                 stroke-linecap="round" stroke-linejoin="round" class="mb-3">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                            <p class="text-sm font-semibold text-gray-700">RCT Consent Form.pdf</p>
                            <p class="text-xs text-gray-400 mt-1">PDF · 234 KB</p>
                            <p class="text-[10px] text-gray-400 mt-4 text-center">
                                PDF preview will render here via embed in Phase 7
                            </p>
                        </div>
                    </div>
                    {{-- /pdf --}}

                    {{-- ── STL / Scan placeholder ── --}}
                    <div x-show="mediaType === 'stl'" style="display:none"
                         class="w-full h-full flex items-center justify-center p-6">
                        <div class="text-center">
                            <div class="w-24 h-24 rounded-2xl bg-gray-800 flex items-center justify-center mx-auto mb-4 border border-gray-700">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
                                     stroke="#6b7280" stroke-width="1"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5 12 2"/>
                                    <line x1="12" y1="22" x2="12" y2="15.5"/>
                                    <polyline points="22 8.5 12 15.5 2 8.5"/>
                                </svg>
                            </div>
                            <p class="text-gray-500 text-sm font-medium">3D STL Viewer</p>
                            <p class="text-gray-600 text-xs mt-1">3D rendering will be available in Phase 7</p>
                        </div>
                    </div>
                    {{-- /stl --}}

                    {{-- ── NAVIGATION ARROWS ── --}}
                    {{-- Previous --}}
                    <button @click="currentIndex = Math.max(currentIndex - 1, 1)"
                            :disabled="currentIndex <= 1"
                            :class="currentIndex <= 1 ? 'opacity-30 cursor-not-allowed' : 'hover:bg-white/20'"
                            class="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full
                                   bg-white/10 flex items-center justify-center transition-colors"
                            aria-label="Previous file">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white"
                             stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="15 18 9 12 15 6"/>
                        </svg>
                    </button>
                    {{-- Next --}}
                    <button @click="currentIndex = Math.min(currentIndex + 1, totalFiles)"
                            :disabled="currentIndex >= totalFiles"
                            :class="currentIndex >= totalFiles ? 'opacity-30 cursor-not-allowed' : 'hover:bg-white/20'"
                            class="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full
                                   bg-white/10 flex items-center justify-center transition-colors"
                            aria-label="Next file">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="white"
                             stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="9 18 15 12 9 6"/>
                        </svg>
                    </button>

                </div>
                {{-- /media display area --}}


                {{-- ── LEFT PANE CONTROLS BAR ── --}}
                <div class="flex items-center justify-between px-4 py-2.5 border-t border-gray-800 flex-shrink-0 bg-gray-900">

                    {{-- Left: media type switcher (dev helper, remove in Phase 7) --}}
                    <div class="flex items-center gap-1">
                        <span class="text-[10px] text-gray-600 mr-1">Preview:</span>
                        <button @click="mediaType='image'"
                                :class="mediaType==='image' ? 'bg-gray-700 text-gray-200' : 'text-gray-500 hover:text-gray-300'"
                                class="px-2 py-0.5 text-[10px] rounded transition-colors">Img</button>
                        <button @click="mediaType='video'"
                                :class="mediaType==='video' ? 'bg-gray-700 text-gray-200' : 'text-gray-500 hover:text-gray-300'"
                                class="px-2 py-0.5 text-[10px] rounded transition-colors">Vid</button>
                        <button @click="mediaType='pdf'"
                                :class="mediaType==='pdf' ? 'bg-gray-700 text-gray-200' : 'text-gray-500 hover:text-gray-300'"
                                class="px-2 py-0.5 text-[10px] rounded transition-colors">PDF</button>
                        <button @click="mediaType='stl'"
                                :class="mediaType==='stl' ? 'bg-gray-700 text-gray-200' : 'text-gray-500 hover:text-gray-300'"
                                class="px-2 py-0.5 text-[10px] rounded transition-colors">STL</button>
                    </div>

                    {{-- Center: zoom controls (images only) --}}
                    <div x-show="mediaType === 'image'"
                         class="flex items-center gap-1">
                        <button @click="zoomOut()"
                                class="w-7 h-7 flex items-center justify-center rounded text-gray-400
                                       hover:bg-gray-700 hover:text-gray-200 transition-colors"
                                title="Zoom out">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                <line x1="8" y1="11" x2="14" y2="11"/>
                            </svg>
                        </button>
                        <span class="text-[10px] text-gray-500 w-10 text-center font-mono"
                              x-text="Math.round(zoomLevel * 100) + '%'"></span>
                        <button @click="zoomIn()"
                                class="w-7 h-7 flex items-center justify-center rounded text-gray-400
                                       hover:bg-gray-700 hover:text-gray-200 transition-colors"
                                title="Zoom in">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                                <line x1="11" y1="8" x2="11" y2="14"/><line x1="8" y1="11" x2="14" y2="11"/>
                            </svg>
                        </button>
                        <button @click="resetZoom()"
                                class="w-7 h-7 flex items-center justify-center rounded text-gray-400
                                       hover:bg-gray-700 hover:text-gray-200 transition-colors"
                                title="Reset zoom">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                <path d="M3 3v5h5"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Right: watermark toggle --}}
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] text-gray-500">Watermark</span>
                        <button @click="showWatermark = !showWatermark"
                                :class="showWatermark
                                    ? 'bg-[#6a0f70]'
                                    : 'bg-gray-700'"
                                class="relative inline-flex h-4 w-7 items-center rounded-full transition-colors flex-shrink-0"
                                :aria-pressed="showWatermark"
                                aria-label="Toggle watermark">
                            <span :class="showWatermark ? 'translate-x-3.5' : 'translate-x-0.5'"
                                  class="inline-block h-3 w-3 rounded-full bg-white shadow transition-transform"></span>
                        </button>
                        <span class="text-[10px]"
                              :class="showWatermark ? 'text-purple-400' : 'text-gray-600'"
                              x-text="showWatermark ? 'On' : 'Off'"></span>
                    </div>

                </div>
                {{-- /left pane controls bar --}}

            </div>
            {{-- /left pane --}}


            {{-- ════════════════════════════════════
                 RIGHT PANE — metadata + actions
            ════════════════════════════════════ --}}
            <div class="flex flex-col flex-1 min-w-0 bg-white border-l border-gray-100">

                {{-- Scrollable metadata body --}}
                <div class="flex-1 overflow-y-auto">

                    {{-- ── PATIENT + VISIT BLOCK ── --}}
                    <div class="px-5 pt-5 pb-4 border-b border-gray-100">

                        {{-- Patient --}}
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-full bg-[#f5eef9] flex items-center justify-center flex-shrink-0">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <a href="#" class="text-sm font-semibold text-[#6a0f70] hover:underline truncate block">
                                    Priya Mehta
                                </a>
                                <span class="text-[10px] text-gray-400">PID-00142 &middot; F &middot; 34 yrs</span>
                            </div>
                        </div>

                        {{-- Visit link --}}
                        <div class="flex items-start gap-2">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af"
                                 stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                 class="flex-shrink-0 mt-0.5">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                                <line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            <div>
                                <a href="#" class="text-xs font-medium text-gray-700 hover:text-[#6a0f70] hover:underline">
                                    12 Jan 2025 — Root Canal Treatment
                                </a>
                                <p class="text-[10px] text-gray-400 mt-0.5">Dr. Sharma &middot; Visit #V-0038</p>
                            </div>
                        </div>
                    </div>
                    {{-- /patient + visit --}}


                    {{-- ── FILE METADATA ── --}}
                    <div class="px-5 py-4 border-b border-gray-100 space-y-2.5">

                        {{-- Procedure + stage + tooth row --}}
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="px-2 py-0.5 text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200 rounded-full">
                                Root Canal Treatment
                            </span>
                            <span class="px-2 py-0.5 text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200 rounded-full">
                                Before
                            </span>
                            <span class="px-2 py-0.5 text-[10px] font-semibold bg-gray-100 text-gray-600 border border-gray-200 rounded-full">
                                Tooth 26
                            </span>
                        </div>

                        {{-- Meta rows --}}
                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400 w-20 flex-shrink-0">File type</span>
                                <span class="font-medium text-gray-700">X-ray / IOPA</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400 w-20 flex-shrink-0">Uploaded</span>
                                <span class="font-medium text-gray-700">12 Jan 2025, 10:42 AM</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400 w-20 flex-shrink-0">Uploaded by</span>
                                <span class="font-medium text-gray-700">Dr. Sharma</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400 w-20 flex-shrink-0">File size</span>
                                <span class="font-medium text-gray-700">1.2 MB · JPG</span>
                            </div>
                        </div>
                    </div>
                    {{-- /file metadata --}}


                    {{-- ── NOTES (editable inline) ── --}}
                    <div class="px-5 py-4 border-b border-gray-100">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Notes</span>
                            <button @click="editingNotes = !editingNotes"
                                    class="text-[10px] text-[#6a0f70] hover:underline">
                                <span x-text="editingNotes ? 'Save' : 'Edit'"></span>
                            </button>
                        </div>
                        {{-- View mode --}}
                        <p x-show="!editingNotes"
                           class="text-xs text-gray-600 leading-relaxed"
                           x-text="notesValue || 'No notes added.'"></p>
                        {{-- Edit mode --}}
                        <textarea x-show="editingNotes"
                                  x-model="notesValue"
                                  style="display:none"
                                  rows="4"
                                  placeholder="Add clinical notes..."
                                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-700
                                         leading-relaxed resize-none focus:outline-none focus:border-[#6a0f70]"></textarea>
                    </div>
                    {{-- /notes --}}


                    {{-- ── TAGS (editable chips) ── --}}
                    <div class="px-5 py-4 border-b border-gray-100">
                        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide block mb-2">Tags</span>
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            <template x-for="tag in tags" :key="tag">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-[#f5eef9] text-[#6a0f70]
                                             text-[10px] font-medium rounded-full border border-purple-200">
                                    <span x-text="tag"></span>
                                    <button @click="removeTag(tag)"
                                            class="hover:text-red-500 transition-colors leading-none"
                                            aria-label="Remove tag">
                                        <svg width="8" height="8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                                    </button>
                                </span>
                            </template>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <input type="text"
                                   x-model="newTag"
                                   @keydown.enter.prevent="addTag()"
                                   @keydown.comma.prevent="addTag()"
                                   placeholder="Add tag…"
                                   class="flex-1 border border-gray-200 rounded-lg px-2.5 py-1 text-[11px]
                                          text-gray-700 focus:outline-none focus:border-[#6a0f70] bg-white">
                            <button @click="addTag()"
                                    class="px-2.5 py-1 text-[10px] font-semibold bg-gray-100 text-gray-600
                                           rounded-lg hover:bg-gray-200 transition-colors">Add</button>
                        </div>
                    </div>
                    {{-- /tags --}}


                    {{-- ── ELIGIBILITY FLAGS ── --}}
                    <div class="px-5 py-4 border-b border-gray-100">
                        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide block mb-3">
                            Eligibility Flags
                        </span>
                        <div class="space-y-2.5">

                            {{-- Marketing --}}
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs text-gray-700">Marketing</span>
                                    <span class="text-[9px] text-gray-400">(consent required)</span>
                                </div>
                                <button @click="flagMarketing = !flagMarketing"
                                        :class="flagMarketing ? 'bg-[#6a0f70]' : 'bg-gray-200'"
                                        class="relative inline-flex h-4 w-7 items-center rounded-full transition-colors flex-shrink-0"
                                        :aria-pressed="flagMarketing">
                                    <span :class="flagMarketing ? 'translate-x-3.5' : 'translate-x-0.5'"
                                          class="inline-block h-3 w-3 rounded-full bg-white shadow transition-transform"></span>
                                </button>
                            </div>

                            {{-- Education --}}
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-700">Education</span>
                                <button @click="flagEducation = !flagEducation"
                                        :class="flagEducation ? 'bg-[#6a0f70]' : 'bg-gray-200'"
                                        class="relative inline-flex h-4 w-7 items-center rounded-full transition-colors flex-shrink-0"
                                        :aria-pressed="flagEducation">
                                    <span :class="flagEducation ? 'translate-x-3.5' : 'translate-x-0.5'"
                                          class="inline-block h-3 w-3 rounded-full bg-white shadow transition-transform"></span>
                                </button>
                            </div>

                            {{-- Teaching --}}
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-700">Teaching</span>
                                <button @click="flagTeaching = !flagTeaching"
                                        :class="flagTeaching ? 'bg-[#6a0f70]' : 'bg-gray-200'"
                                        class="relative inline-flex h-4 w-7 items-center rounded-full transition-colors flex-shrink-0"
                                        :aria-pressed="flagTeaching">
                                    <span :class="flagTeaching ? 'translate-x-3.5' : 'translate-x-0.5'"
                                          class="inline-block h-3 w-3 rounded-full bg-white shadow transition-transform"></span>
                                </button>
                            </div>

                            {{-- Research --}}
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-700">Research</span>
                                <button @click="flagResearch = !flagResearch"
                                        :class="flagResearch ? 'bg-[#6a0f70]' : 'bg-gray-200'"
                                        class="relative inline-flex h-4 w-7 items-center rounded-full transition-colors flex-shrink-0"
                                        :aria-pressed="flagResearch">
                                    <span :class="flagResearch ? 'translate-x-3.5' : 'translate-x-0.5'"
                                          class="inline-block h-3 w-3 rounded-full bg-white shadow transition-transform"></span>
                                </button>
                            </div>

                            {{-- Case Library --}}
                            <div class="flex items-center justify-between">
                                <span class="text-xs text-gray-700">Case Library</span>
                                <button @click="flagCaseLib = !flagCaseLib"
                                        :class="flagCaseLib ? 'bg-[#6a0f70]' : 'bg-gray-200'"
                                        class="relative inline-flex h-4 w-7 items-center rounded-full transition-colors flex-shrink-0"
                                        :aria-pressed="flagCaseLib">
                                    <span :class="flagCaseLib ? 'translate-x-3.5' : 'translate-x-0.5'"
                                          class="inline-block h-3 w-3 rounded-full bg-white shadow transition-transform"></span>
                                </button>
                            </div>

                        </div>
                    </div>
                    {{-- /eligibility flags --}}


                    {{-- ── CONSENT + MARKETING STATUS ── --}}
                    <div class="px-5 py-4">
                        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide block mb-2">Status</span>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Consent</span>
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-green-50 text-green-700 border border-green-200 rounded-full">
                                    Given
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Marketing approval</span>
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200 rounded-full">
                                    Pending
                                </span>
                            </div>
                        </div>
                    </div>
                    {{-- /status --}}

                </div>
                {{-- /scrollable body --}}


                {{-- ── FOOTER ACTIONS ── --}}
                <div class="border-t border-gray-200 bg-gray-50 flex-shrink-0">

                    {{-- Delete confirmation state --}}
                    <div x-show="deleteConfirm"
                         style="display:none"
                         class="px-5 py-3 bg-red-50 border-b border-red-100 flex items-center justify-between gap-3">
                        <p class="text-xs text-red-700 font-medium">Delete this file permanently?</p>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button @click="deleteConfirm = false"
                                    class="px-3 py-1 text-xs border border-gray-300 rounded-lg bg-white text-gray-600
                                           hover:bg-gray-50 transition-colors">Cancel</button>
                            <button class="px-3 py-1 text-xs font-semibold bg-red-600 text-white rounded-lg
                                           hover:bg-red-700 transition-colors cursor-not-allowed opacity-70"
                                    title="Not wired — Phase 7">
                                Delete
                            </button>
                        </div>
                    </div>

                    {{-- Primary action buttons --}}
                    <div class="px-5 py-3 flex items-center gap-2 flex-wrap">

                        {{-- Download original --}}
                        <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                                       bg-[#6a0f70] text-white rounded-lg hover:bg-[#380740] transition-colors
                                       cursor-not-allowed opacity-70"
                                title="File serving not wired — Phase 7">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Download
                        </button>

                        {{-- Download watermarked --}}
                        <button class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                                       border border-gray-200 bg-white text-gray-600 rounded-lg
                                       hover:bg-gray-50 transition-colors cursor-not-allowed opacity-70"
                                title="Watermark engine — Phase 10">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                            </svg>
                            Watermarked
                        </button>

                        {{-- Add to Case Library toggle --}}
                        <button @click="flagCaseLib = !flagCaseLib"
                                :class="flagCaseLib
                                    ? 'bg-purple-50 border-purple-300 text-purple-700'
                                    : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                                       border rounded-lg transition-colors">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                            </svg>
                            <span x-text="flagCaseLib ? 'In Case Library' : 'Case Library'"></span>
                        </button>

                        {{-- Delete --}}
                        <button @click="deleteConfirm = true"
                                x-show="!deleteConfirm"
                                class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                                       border border-red-200 text-red-600 rounded-lg hover:bg-red-50 transition-colors">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                <path d="M10 11v6"/><path d="M14 11v6"/>
                            </svg>
                            Delete
                        </button>

                    </div>
                </div>
                {{-- /footer --}}

            </div>
            {{-- /right pane --}}

        </div>
        {{-- /split body --}}

    </div>
    {{-- /drawer panel --}}

</div>
{{-- /x-data file-viewer --}}
