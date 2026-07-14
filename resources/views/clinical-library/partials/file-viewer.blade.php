{{-- ════════════════════════════════════════════════════════════════════
     GLOBAL FILE VIEWER — Clinical Library
     File: resources/views/clinical-library/partials/file-viewer.blade.php

     Included ONCE in layouts/app.blade.php (globally available).
     Opens via:
       window.dispatchEvent(new CustomEvent('open-file-viewer',
           { detail: { id: 123, patientId: 45 } }))

     Production hardening 2026-07-14: this drawer previously rendered STATIC
     PLACEHOLDER content (fake patient, fake notes, fake tags) regardless of
     which file was clicked. It now fetches the real file via the existing
     JSON endpoint  GET /patients/{patient}/clinical-files/{file}  and wires
     notes / tags / eligibility flags to PUT, delete to DELETE.
════════════════════════════════════════════════════════════════════ --}}

<div
    x-data="dfFileViewer()"
    @keydown.escape.window="open && close()"
    style="display:contents"
>

    {{-- BACKDROP --}}
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

    {{-- DRAWER PANEL --}}
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

        {{-- DRAWER HEADER --}}
        <div class="flex items-center justify-between px-5 py-3 border-b border-gray-200 flex-shrink-0 bg-white"
             style="min-height:52px;">

            {{-- Left: breadcrumb context (real file data) --}}
            <div class="flex items-center gap-2 text-xs text-gray-500 min-w-0">
                <template x-if="file">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="font-medium text-gray-700 truncate" x-text="file.title || file.original_filename"></span>
                        <template x-if="file.procedure">
                            <span class="flex items-center gap-2 min-w-0">
                                <span class="text-gray-300">/</span>
                                <span class="truncate" x-text="file.procedure"></span>
                            </span>
                        </template>
                        <template x-if="file.captured_at">
                            <span class="flex items-center gap-2 min-w-0">
                                <span class="text-gray-300">/</span>
                                <span class="truncate" x-text="file.captured_at"></span>
                            </span>
                        </template>
                    </div>
                </template>
                <span x-show="loading" class="text-gray-400">Loading…</span>
            </div>

            {{-- Right: close --}}
            <div class="flex items-center gap-3 flex-shrink-0">
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

        {{-- ERROR STATE --}}
        <div x-show="error" style="display:none"
             class="flex-1 flex items-center justify-center p-8">
            <div class="text-center">
                <p class="text-sm font-medium text-gray-700 mb-1">Couldn't load this file</p>
                <p class="text-xs text-gray-400" x-text="error"></p>
                <button @click="fetchFile()"
                        class="mt-4 px-4 py-1.5 text-xs font-medium bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                    Retry
                </button>
            </div>
        </div>

        {{-- SPLIT BODY — left 60% / right 40% --}}
        <div class="flex flex-1 min-h-0" x-show="!error">

            {{-- LEFT PANE — media display --}}
            <div class="relative flex flex-col bg-gray-950" style="width:60%; flex-shrink:0;">

                <div class="flex-1 flex items-center justify-center overflow-hidden relative min-h-0">

                    {{-- Loading spinner --}}
                    <div x-show="loading" class="text-gray-500 text-xs">Loading file…</div>

                    {{-- IMAGE --}}
                    <template x-if="!loading && file && mediaKind() === 'image'">
                        <div class="w-full h-full flex items-center justify-center p-6 overflow-auto">
                            <div class="relative"
                                 :style="'transform: scale(' + zoomLevel + '); transition: transform 0.2s ease; transform-origin: center center;'">
                                <img :src="file.display_url || file.original_url"
                                     :alt="file.title || 'Clinical file'"
                                     class="max-w-full max-h-[75vh] rounded-lg object-contain select-none"
                                     draggable="false">
                                {{-- Watermark overlay (toggleable, real data) --}}
                                <div x-show="showWatermark"
                                     class="absolute inset-0 flex items-center justify-center pointer-events-none">
                                    <div class="text-white/30 text-lg font-bold tracking-widest rotate-[-30deg] text-center"
                                         style="font-family: monospace; font-size: 11px; line-height: 2;">
                                        {{ strtoupper(\App\Models\AppSetting::get('clinic_name', config('app.name'))) }}<br>
                                        <span x-text="(file.patient ? file.patient.name : '') + (file.captured_at ? ' · ' + file.captured_at : '')"></span><br>
                                        <span x-text="(file.procedure || '') + (file.tooth_number ? ' · Tooth ' + file.tooth_number : '')"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    {{-- VIDEO --}}
                    <template x-if="!loading && file && mediaKind() === 'video'">
                        <div class="w-full h-full flex items-center justify-center p-6">
                            <video :src="file.display_url || file.original_url" controls
                                   class="w-full max-w-2xl max-h-[75vh] rounded-lg"></video>
                        </div>
                    </template>

                    {{-- PDF --}}
                    <template x-if="!loading && file && mediaKind() === 'pdf'">
                        <div class="w-full h-full p-4">
                            <iframe :src="file.display_url || file.original_url"
                                    class="w-full h-full rounded-lg bg-white"
                                    title="PDF preview"></iframe>
                        </div>
                    </template>

                    {{-- OTHER (no inline preview) --}}
                    <template x-if="!loading && file && mediaKind() === 'other'">
                        <div class="text-center p-6">
                            <div class="w-24 h-24 rounded-2xl bg-gray-800 flex items-center justify-center mx-auto mb-4 border border-gray-700">
                                <svg width="40" height="40" viewBox="0 0 24 24" fill="none"
                                     stroke="#6b7280" stroke-width="1"
                                     stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                    <polyline points="14 2 14 8 20 8"/>
                                </svg>
                            </div>
                            <p class="text-gray-400 text-sm font-medium" x-text="file.original_filename"></p>
                            <p class="text-gray-600 text-xs mt-1">
                                No inline preview for this file type — use Download.
                            </p>
                        </div>
                    </template>

                </div>

                {{-- LEFT PANE CONTROLS BAR --}}
                <div class="flex items-center justify-between px-4 py-2.5 border-t border-gray-800 flex-shrink-0 bg-gray-900">

                    {{-- Left: file type label --}}
                    <div class="flex items-center gap-1">
                        <span class="text-[10px] text-gray-500"
                              x-text="file ? (file.file_type_label || file.mime_type || '') : ''"></span>
                    </div>

                    {{-- Center: zoom controls (images only) --}}
                    <div x-show="file && mediaKind() === 'image'"
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

                    {{-- Right: watermark toggle (images only) --}}
                    <div class="flex items-center gap-2" x-show="file && mediaKind() === 'image'">
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

            </div>
            {{-- /left pane --}}


            {{-- RIGHT PANE — metadata + actions --}}
            <div class="flex flex-col flex-1 min-w-0 bg-white border-l border-gray-100">

                <div class="flex-1 overflow-y-auto" x-show="file">

                    {{-- PATIENT + VISIT BLOCK --}}
                    <div class="px-5 pt-5 pb-4 border-b border-gray-100">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="w-8 h-8 rounded-full bg-[#f5eef9] flex items-center justify-center flex-shrink-0">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                            </div>
                            <div class="min-w-0">
                                <a :href="file && file.patient ? ('{{ url('/patients') }}/' + file.patient.id) : '#'"
                                   class="text-sm font-semibold text-[#6a0f70] hover:underline truncate block"
                                   x-text="file && file.patient ? file.patient.name : '—'"></a>
                                <span class="text-[10px] text-gray-400"
                                      x-text="file && file.patient
                                          ? ['PID-' + String(file.patient.id).padStart(5, '0'), file.patient.gender, (file.patient.age !== null && file.patient.age !== undefined) ? file.patient.age + ' yrs' : null].filter(Boolean).join(' · ')
                                          : ''"></span>
                            </div>
                        </div>

                        {{-- Visit link (only when the file is tied to a visit) --}}
                        <template x-if="file && file.visit">
                            <div class="flex items-start gap-2">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9ca3af"
                                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                     class="flex-shrink-0 mt-0.5">
                                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                    <line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/>
                                    <line x1="3" y1="10" x2="21" y2="10"/>
                                </svg>
                                <div>
                                    <span class="text-xs font-medium text-gray-700"
                                          x-text="[file.visit.visit_date, file.visit.treatment].filter(Boolean).join(' — ')"></span>
                                    <p class="text-[10px] text-gray-400 mt-0.5"
                                       x-text="file.visit.doctor_name ? file.visit.doctor_name : ''"></p>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- FILE METADATA --}}
                    <div class="px-5 py-4 border-b border-gray-100 space-y-2.5">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <template x-if="file && file.procedure">
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200 rounded-full"
                                      x-text="file.procedure"></span>
                            </template>
                            <template x-if="file && file.stage_label">
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200 rounded-full"
                                      x-text="file.stage_label"></span>
                            </template>
                            <template x-if="file && file.tooth_number">
                                <span class="px-2 py-0.5 text-[10px] font-semibold bg-gray-100 text-gray-600 border border-gray-200 rounded-full"
                                      x-text="'Tooth ' + file.tooth_number"></span>
                            </template>
                        </div>

                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400 w-20 flex-shrink-0">File type</span>
                                <span class="font-medium text-gray-700" x-text="file ? (file.file_type_label || '—') : ''"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400 w-20 flex-shrink-0">Uploaded</span>
                                <span class="font-medium text-gray-700" x-text="file ? (file.uploaded_at || '—') : ''"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400 w-20 flex-shrink-0">Uploaded by</span>
                                <span class="font-medium text-gray-700"
                                      x-text="file && file.uploaded_by ? file.uploaded_by.name : '—'"></span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-400 w-20 flex-shrink-0">File size</span>
                                <span class="font-medium text-gray-700" x-text="file ? (file.file_size_human || '—') : ''"></span>
                            </div>
                        </div>
                    </div>

                    {{-- NOTES (editable inline, persisted via PUT) --}}
                    <div class="px-5 py-4 border-b border-gray-100">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Notes</span>
                            <button @click="editingNotes ? saveNotes() : startEditNotes()"
                                    :disabled="saving"
                                    class="text-[10px] text-[#6a0f70] hover:underline disabled:opacity-50">
                                <span x-text="editingNotes ? (saving ? 'Saving…' : 'Save') : 'Edit'"></span>
                            </button>
                        </div>
                        <p x-show="!editingNotes"
                           class="text-xs text-gray-600 leading-relaxed"
                           x-text="file && file.notes ? file.notes : 'No notes added.'"></p>
                        <textarea x-show="editingNotes"
                                  x-model="notesDraft"
                                  style="display:none"
                                  rows="4"
                                  placeholder="Add clinical notes..."
                                  class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-700
                                         leading-relaxed resize-none focus:outline-none focus:border-[#6a0f70]"></textarea>
                    </div>

                    {{-- TAGS (editable chips, persisted via PUT) --}}
                    <div class="px-5 py-4 border-b border-gray-100">
                        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide block mb-2">Tags</span>
                        <div class="flex flex-wrap gap-1.5 mb-2">
                            <template x-for="tag in (file ? (file.tags || []) : [])" :key="tag">
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
                            <span x-show="file && (!file.tags || file.tags.length === 0)"
                                  class="text-[10px] text-gray-400">No tags.</span>
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
                                    :disabled="saving"
                                    class="px-2.5 py-1 text-[10px] font-semibold bg-gray-100 text-gray-600
                                           rounded-lg hover:bg-gray-200 transition-colors disabled:opacity-50">Add</button>
                        </div>
                    </div>

                    {{-- ELIGIBILITY FLAGS (persisted via PUT) --}}
                    <div class="px-5 py-4 border-b border-gray-100">
                        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide block mb-3">
                            Eligibility Flags
                        </span>
                        <div class="space-y-2.5">
                            <template x-for="flag in flagDefs" :key="flag.key">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs text-gray-700" x-text="flag.label"></span>
                                        <span x-show="flag.hint" class="text-[9px] text-gray-400" x-text="flag.hint"></span>
                                    </div>
                                    <button @click="toggleFlag(flag.key)"
                                            :disabled="saving"
                                            :class="file && file[flag.key] ? 'bg-[#6a0f70]' : 'bg-gray-200'"
                                            class="relative inline-flex h-4 w-7 items-center rounded-full transition-colors flex-shrink-0 disabled:opacity-50"
                                            :aria-pressed="file ? !!file[flag.key] : false">
                                        <span :class="file && file[flag.key] ? 'translate-x-3.5' : 'translate-x-0.5'"
                                              class="inline-block h-3 w-3 rounded-full bg-white shadow transition-transform"></span>
                                    </button>
                                </div>
                            </template>
                        </div>
                    </div>

                    {{-- CONSENT + MARKETING STATUS --}}
                    <div class="px-5 py-4">
                        <span class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide block mb-2">Status</span>
                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Consent</span>
                                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full border"
                                      :class="{
                                          'bg-green-50 text-green-700 border-green-200':  file && file.consent_status === 'given',
                                          'bg-amber-50 text-amber-700 border-amber-200':  file && file.consent_status === 'pending',
                                          'bg-gray-50 text-gray-500 border-gray-200':     !file || file.consent_status === 'not_given' || !file.consent_status,
                                      }"
                                      x-text="file ? ({given:'Given', pending:'Pending', not_given:'Not given'}[file.consent_status] || 'Not given') : ''"></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-gray-500">Marketing approval</span>
                                <span class="px-2 py-0.5 text-[10px] font-semibold rounded-full border"
                                      :class="{
                                          'bg-green-50 text-green-700 border-green-200':  file && file.marketing_status === 'approved',
                                          'bg-amber-50 text-amber-700 border-amber-200':  file && file.marketing_status === 'pending',
                                          'bg-red-50 text-red-600 border-red-200':        file && file.marketing_status === 'rejected',
                                          'bg-gray-50 text-gray-500 border-gray-200':     !file || !file.marketing_status,
                                      }"
                                      x-text="file ? ({approved:'Approved', pending:'Pending', rejected:'Rejected'}[file.marketing_status] || '—') : ''"></span>
                            </div>
                        </div>
                    </div>

                </div>
                {{-- /scrollable body --}}

                {{-- FOOTER ACTIONS --}}
                <div class="border-t border-gray-200 bg-gray-50 flex-shrink-0" x-show="file">

                    {{-- Delete confirmation state --}}
                    <div x-show="deleteConfirm"
                         style="display:none"
                         class="px-5 py-3 bg-red-50 border-b border-red-100 flex items-center justify-between gap-3">
                        <p class="text-xs text-red-700 font-medium">Delete this file?</p>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <button @click="deleteConfirm = false"
                                    class="px-3 py-1 text-xs border border-gray-300 rounded-lg bg-white text-gray-600
                                           hover:bg-gray-50 transition-colors">Cancel</button>
                            <button @click="destroyFile()"
                                    :disabled="saving"
                                    class="px-3 py-1 text-xs font-semibold bg-red-600 text-white rounded-lg
                                           hover:bg-red-700 transition-colors disabled:opacity-50">
                                <span x-text="saving ? 'Deleting…' : 'Delete'"></span>
                            </button>
                        </div>
                    </div>

                    <div class="px-5 py-3 flex items-center gap-2 flex-wrap">

                        {{-- Download original --}}
                        <a :href="file ? (file.original_url || file.display_url) : '#'"
                           target="_blank" rel="noopener"
                           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                                  bg-[#6a0f70] text-white rounded-lg hover:bg-[#380740] transition-colors">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                <polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>
                            </svg>
                            Download
                        </a>

                        {{-- Add to Case Library toggle --}}
                        <button @click="toggleFlag('is_case_library_eligible')"
                                :disabled="saving"
                                :class="file && file.is_case_library_eligible
                                    ? 'bg-purple-50 border-purple-300 text-purple-700'
                                    : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium
                                       border rounded-lg transition-colors disabled:opacity-50">
                            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                 stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                            </svg>
                            <span x-text="file && file.is_case_library_eligible ? 'In Case Library' : 'Case Library'"></span>
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

<script>
function dfFileViewer() {
    return {
        open:          false,
        fileId:        null,
        patientId:     null,
        file:          null,
        loading:       false,
        error:         null,
        saving:        false,

        zoomLevel:     1,
        showWatermark: false,

        editingNotes:  false,
        notesDraft:    '',
        newTag:        '',
        deleteConfirm: false,

        flagDefs: [
            { key: 'is_marketing_eligible',    label: 'Marketing',    hint: '(consent required)' },
            { key: 'is_education_eligible',    label: 'Education',    hint: null },
            { key: 'is_teaching_eligible',     label: 'Teaching',     hint: null },
            { key: 'is_research_eligible',     label: 'Research',     hint: null },
            { key: 'is_case_library_eligible', label: 'Case Library', hint: null },
        ],

        init() {
            window.addEventListener('open-file-viewer', (e) => {
                this.fileId        = e.detail.id ?? null;
                this.patientId     = e.detail.patientId ?? null;
                this.file          = null;
                this.error         = null;
                this.open          = true;
                this.deleteConfirm = false;
                this.editingNotes  = false;
                this.zoomLevel     = 1;
                this.showWatermark = false;
                document.body.style.overflow = 'hidden';
                this.fetchFile();
            });
        },

        close() {
            this.open = false;
            document.body.style.overflow = '';
        },

        fileUrl() {
            return '{{ url('/patients') }}/' + this.patientId + '/clinical-files/' + this.fileId;
        },

        async fetchFile() {
            if (!this.fileId || !this.patientId) {
                this.error = 'Missing file reference.';
                return;
            }
            this.loading = true;
            this.error   = null;
            try {
                const res = await fetch(this.fileUrl(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                this.file       = json.file;
                this.notesDraft = json.file.notes || '';
            } catch (err) {
                this.error = 'The file could not be loaded (' + err.message + ').';
            } finally {
                this.loading = false;
            }
        },

        mediaKind() {
            if (!this.file) return null;
            if (this.file.is_image) return 'image';
            const mime = (this.file.mime_type || '').toLowerCase();
            if (mime.includes('pdf'))       return 'pdf';
            if (mime.startsWith('video/'))  return 'video';
            return 'other';
        },

        zoomIn()    { this.zoomLevel = Math.min(this.zoomLevel + 0.25, 4); },
        zoomOut()   { this.zoomLevel = Math.max(this.zoomLevel - 0.25, 0.5); },
        resetZoom() { this.zoomLevel = 1; },

        async saveMeta(patch) {
            if (!this.file) return false;
            this.saving = true;
            try {
                const res = await fetch(this.fileUrl(), {
                    method:  'PUT',
                    headers: {
                        'Accept':           'application/json',
                        'Content-Type':     'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':     '{{ csrf_token() }}',
                    },
                    body: JSON.stringify(patch),
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                const json = await res.json();
                // Merge the fresh server copy but keep the patient block
                // (update() responses don't include it).
                const patient = this.file.patient;
                this.file = Object.assign({}, this.file, json.file, { patient });
                return true;
            } catch (err) {
                alert('Could not save changes (' + err.message + '). Please retry.');
                return false;
            } finally {
                this.saving = false;
            }
        },

        startEditNotes() {
            this.notesDraft   = this.file?.notes || '';
            this.editingNotes = true;
        },
        async saveNotes() {
            const ok = await this.saveMeta({ notes: this.notesDraft });
            if (ok) this.editingNotes = false;
        },

        addTag() {
            const t = this.newTag.trim().toLowerCase();
            if (!t || !this.file) return;
            const tags = [...(this.file.tags || [])];
            if (!tags.includes(t)) {
                tags.push(t);
                this.saveMeta({ tags });
            }
            this.newTag = '';
        },
        removeTag(tag) {
            if (!this.file) return;
            const tags = (this.file.tags || []).filter(t => t !== tag);
            this.saveMeta({ tags });
        },

        toggleFlag(key) {
            if (!this.file) return;
            this.saveMeta({ [key]: !this.file[key] });
        },

        async destroyFile() {
            if (!this.file) return;
            this.saving = true;
            try {
                const res = await fetch(this.fileUrl(), {
                    method:  'DELETE',
                    headers: {
                        'Accept':           'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN':     '{{ csrf_token() }}',
                    },
                });
                if (!res.ok) throw new Error('HTTP ' + res.status);
                this.close();
                window.dispatchEvent(new CustomEvent('file-viewer-deleted', { detail: { id: this.fileId } }));
                window.location.reload();
            } catch (err) {
                alert('Could not delete the file (' + err.message + ').');
            } finally {
                this.saving = false;
            }
        },
    };
}
</script>
