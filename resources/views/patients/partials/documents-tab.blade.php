{{-- ════════════════════════════════════════════════════════════════════
     DOCUMENTS TAB — Clinical Library Integration Point
     Phase 7E: Wired to real $clinicalFiles data from PatientProfileService
     Alpine.js for all interactivity | Brand color #6a0f70
════════════════════════════════════════════════════════════════════ --}}

{{-- ── Pre-compute counts and groupings (PHP, server-side) ── --}}
@php
    // Sub-tab counts
    $totalCount      = $clinicalFiles->count();
    $photoVideoCount = $clinicalFiles->whereIn('file_type', ['photo', 'video'])->count();
    $xrayCount       = $clinicalFiles->whereIn('file_type', ['xray', 'opg', 'cbct', 'stl', 'intraoral_scan'])->count();
    $docCount        = $clinicalFiles->whereIn('file_type', ['pdf', 'estimate', 'invoice', 'lab_slip', 'other'])->count();
    $consentCount    = $clinicalFiles->where('file_type', 'consent')->count();

    // Group by visit_id for gallery + timeline views
    // null visit_id = patient-scoped (General Documents) — put at end
    $visitGroups = $clinicalFiles->groupBy(fn($f) => $f->visit_id ?? 'general');
    $visitCount  = $visitGroups->reject(fn($g, $k) => $k === 'general')->count();

    // Colour maps for file type badges
    $typeBadge = [
        'photo'          => 'bg-blue-50 text-blue-700 border-blue-200',
        'video'          => 'bg-green-50 text-green-700 border-green-200',
        'xray'           => 'bg-purple-50 text-purple-700 border-purple-200',
        'opg'            => 'bg-purple-50 text-purple-700 border-purple-200',
        'cbct'           => 'bg-purple-50 text-purple-700 border-purple-200',
        'stl'            => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'intraoral_scan' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
        'pdf'            => 'bg-orange-50 text-orange-600 border-orange-200',
        'consent'        => 'bg-red-50 text-red-600 border-red-200',
        'estimate'       => 'bg-yellow-50 text-yellow-700 border-yellow-200',
        'invoice'        => 'bg-yellow-50 text-yellow-700 border-yellow-200',
        'lab_slip'       => 'bg-orange-50 text-orange-600 border-orange-200',
        'other'          => 'bg-gray-100 text-gray-500 border-gray-200',
    ];
    $typeLabel = [
        'photo' => 'Photo', 'video' => 'Video', 'xray' => 'X-ray',
        'opg' => 'OPG', 'cbct' => 'CBCT', 'stl' => 'STL',
        'intraoral_scan' => 'Scan', 'pdf' => 'PDF', 'consent' => 'Consent',
        'estimate' => 'Estimate', 'invoice' => 'Invoice',
        'lab_slip' => 'Lab Slip', 'other' => 'File',
    ];
    // Thumbnail background for non-image types
    $typeThumbnailBg = [
        'photo' => 'bg-rose-50', 'video' => 'bg-green-50',
        'xray' => 'bg-gray-800', 'opg' => 'bg-gray-800', 'cbct' => 'bg-gray-800',
        'stl' => 'bg-indigo-50', 'intraoral_scan' => 'bg-indigo-50',
        'pdf' => 'bg-orange-50', 'consent' => 'bg-red-50',
        'estimate' => 'bg-yellow-50', 'invoice' => 'bg-yellow-50',
        'lab_slip' => 'bg-orange-50', 'other' => 'bg-gray-100',
    ];

    // Stage badge colours
    $stageBadge = [
        'general'  => 'bg-gray-50 text-gray-500 border-gray-200',
        'before'   => 'bg-blue-50 text-blue-700 border-blue-200',
        'during'   => 'bg-amber-50 text-amber-700 border-amber-200',
        'after'    => 'bg-green-50 text-green-700 border-green-200',
        'followup' => 'bg-violet-50 text-violet-700 border-violet-200',
    ];
    $stageLabel = [
        'general' => 'General', 'before' => 'Before',
        'during' => 'During', 'after' => 'After', 'followup' => 'Follow-up',
    ];

    // Image types that render a real <img> thumbnail
    $imageTypes = ['photo', 'xray', 'opg', 'cbct', 'intraoral_scan'];

    // Phase 11 — Pre-compute protocol completion per visit group
    // key = visit_id, value = ['total'=>N, 'completed'=>N, 'percent'=>N, 'protocol_name'=>'']
    $protocolService   = app(\App\Services\ClinicalLibrary\ProtocolService::class);
    $visitCompletions  = [];
    foreach ($visitGroups as $groupKey => $groupFiles) {
        if ($groupKey === 'general') continue;
        $gVisit = $groupFiles->first()?->visit;
        if (!$gVisit || !$gVisit->treatment_name) continue;
        $visitCompletions[$groupKey] = $protocolService->completionForVisit(
            (int) $groupKey,
            $gVisit->treatment_name
        );
    }
@endphp

<div x-show="activeTab === 'documents'" style="display:none" class="w-full">

{{-- ── Alpine state for the entire Documents tab ── --}}
<div x-data="{
    docSubTab:   'all',
    docView:     'gallery',
    showFilters: false,
    filterVisit: '',
    filterStage: 'all',
    filterType:  'all',
    filterFrom:  '',
    filterTo:    '',
    openGroups:  {},
    // All groups open by default (openGroups[id] is undefined = open)
    isGroupOpen(id) { return this.openGroups[id] !== false; },
    toggleGroup(id) { this.openGroups[id] = !this.isGroupOpen(id); },
    // Upload Modal state (Phase 2)
    showUploadModal:     false,
    uploadStep:          1,
    uploadHasFiles:      false,
    uploadTags:          [],
    uploadTagInput:      '',
    showContentSettings: false,
    uploadFiles:         [],   // simplified {name,size,ext} for display
    uploadSelectedTeeth: [],   // FDI tooth numbers
    // Upload form field bindings (Step 2)
    uploadStage:         'general',
    uploadProcedure:     '',
    uploadVisitId:       '',
    uploadNotes:         '',
    // Upload progress tracking
    uploadProgress:      {},   // { fileIndex: 0-100 }
    uploadStatus:        'idle', // idle | uploading | done | error
    uploadErrors:        [],
    // Real multi-file upload — one POST per file, XHR for progress
    async uploadAllFiles(uploadUrl) {
        const fileInput = this.$refs.fileInput;
        if (!fileInput || !fileInput.files.length) return;
        const token = document.querySelector('meta[name=\'csrf-token\']')?.content;
        this.uploadStatus  = 'uploading';
        this.uploadErrors  = [];
        this.uploadProgress = {};
        const files = Array.from(fileInput.files);
        for (let i = 0; i < files.length; i++) {
            this.uploadProgress[i] = 0;
            const formData = new FormData();
            formData.append('file',          files[i]);
            formData.append('stage',         this.uploadStage);
            formData.append('procedure',     this.uploadProcedure);
            formData.append('visit_id',      this.uploadVisitId ?? '');
            formData.append('tooth_number',  this.uploadSelectedTeeth.join(', '));
            formData.append('notes',         this.uploadNotes);
            this.uploadTags.forEach(t => formData.append('tags[]', t));
            await new Promise((resolve) => {
                const xhr = new XMLHttpRequest();
                xhr.open('POST', uploadUrl);
                xhr.setRequestHeader('X-CSRF-TOKEN', token);
                xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                xhr.upload.addEventListener('progress', (e) => {
                    if (e.lengthComputable) {
                        this.uploadProgress[i] = Math.round((e.loaded / e.total) * 100);
                    }
                });
                xhr.addEventListener('load', () => {
                    if (xhr.status === 201) {
                        this.uploadProgress[i] = 100;
                    } else {
                        this.uploadErrors.push(files[i].name + ': upload failed');
                    }
                    resolve();
                });
                xhr.addEventListener('error', () => {
                    this.uploadErrors.push(files[i].name + ': network error');
                    resolve();
                });
                xhr.send(formData);
            });
        }
        this.uploadStatus = this.uploadErrors.length ? 'error' : 'done';
        if (!this.uploadErrors.length) {
            setTimeout(() => window.location.reload(), 600);
        }
    },
    // Phase 11 — Protocol Steps suggestion (upload modal)
    protocolSteps:        [],
    protocolName:         '',
    protocolLoading:      false,
    async fetchProtocolSteps(procedure) {
        if (!procedure) { this.protocolSteps = []; this.protocolName = ''; return; }
        this.protocolLoading = true;
        try {
            const url = '/clinical-library/protocol-steps?procedure=' + encodeURIComponent(procedure);
            const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
            const data = await res.json();
            this.protocolSteps = data.steps || [];
            this.protocolName  = data.protocol ? data.protocol.name : '';
        } catch(e) {
            this.protocolSteps = []; this.protocolName = '';
        } finally { this.protocolLoading = false; }
    },
    // Sub-tab filtering helper: returns true if this file should show in the active sub-tab
    matchesSubTab(fileType) {
        if (this.docSubTab === 'all')      return true;
        if (this.docSubTab === 'photos')   return ['photo','video'].includes(fileType);
        if (this.docSubTab === 'xrays')    return ['xray','opg','cbct','stl','intraoral_scan'].includes(fileType);
        if (this.docSubTab === 'docs')     return ['pdf','estimate','invoice','lab_slip','other'].includes(fileType);
        if (this.docSubTab === 'consents') return fileType === 'consent';
        return true;
    },
    // AJAX soft-delete: sends DELETE then reloads the tab section
    async deleteFile(fileId, deleteUrl) {
        if (!confirm('Delete this file? This cannot be undone.')) return;
        const token = document.querySelector('meta[name=\'csrf-token\']')?.content;
        try {
            const res = await fetch(deleteUrl, {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': token, 'Accept': 'application/json' },
            });
            if (res.ok) { window.location.reload(); }
            else { alert('Could not delete file. Please try again.'); }
        } catch(e) { alert('Network error. Please try again.'); }
    }
}" class="w-full">

    {{-- ════════════════════════════════════
         SUB-NAVIGATION BAR
    ════════════════════════════════════ --}}
    <div class="border-b border-gray-200 bg-white px-6">
        <nav class="flex gap-1 -mb-px overflow-x-auto">

            {{-- Tab: All Files --}}
            <button @click="docSubTab = 'all'"
                    :class="docSubTab === 'all'
                        ? 'border-[#6a0f70] text-[#6a0f70]'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="flex items-center gap-1.5 whitespace-nowrap px-3 py-3 text-sm font-medium border-b-2 transition-colors">
                All Files
                <span :class="docSubTab === 'all' ? 'bg-[#6a0f70] text-white' : 'bg-gray-100 text-gray-500'"
                      class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full transition-colors">{{ $totalCount }}</span>
            </button>

            {{-- Tab: Photos & Videos --}}
            <button @click="docSubTab = 'photos'"
                    :class="docSubTab === 'photos'
                        ? 'border-[#6a0f70] text-[#6a0f70]'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="flex items-center gap-1.5 whitespace-nowrap px-3 py-3 text-sm font-medium border-b-2 transition-colors">
                Photos &amp; Videos
                <span :class="docSubTab === 'photos' ? 'bg-[#6a0f70] text-white' : 'bg-gray-100 text-gray-500'"
                      class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full transition-colors">{{ $photoVideoCount }}</span>
            </button>

            {{-- Tab: X-rays & Scans --}}
            <button @click="docSubTab = 'xrays'"
                    :class="docSubTab === 'xrays'
                        ? 'border-[#6a0f70] text-[#6a0f70]'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="flex items-center gap-1.5 whitespace-nowrap px-3 py-3 text-sm font-medium border-b-2 transition-colors">
                X-rays &amp; Scans
                <span :class="docSubTab === 'xrays' ? 'bg-[#6a0f70] text-white' : 'bg-gray-100 text-gray-500'"
                      class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full transition-colors">{{ $xrayCount }}</span>
            </button>

            {{-- Tab: Documents --}}
            <button @click="docSubTab = 'docs'"
                    :class="docSubTab === 'docs'
                        ? 'border-[#6a0f70] text-[#6a0f70]'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="flex items-center gap-1.5 whitespace-nowrap px-3 py-3 text-sm font-medium border-b-2 transition-colors">
                Documents
                <span :class="docSubTab === 'docs' ? 'bg-[#6a0f70] text-white' : 'bg-gray-100 text-gray-500'"
                      class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full transition-colors">{{ $docCount }}</span>
            </button>

            {{-- Tab: Consents --}}
            <button @click="docSubTab = 'consents'"
                    :class="docSubTab === 'consents'
                        ? 'border-[#6a0f70] text-[#6a0f70]'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                    class="flex items-center gap-1.5 whitespace-nowrap px-3 py-3 text-sm font-medium border-b-2 transition-colors">
                Consents
                <span :class="docSubTab === 'consents' ? 'bg-[#6a0f70] text-white' : 'bg-gray-100 text-gray-500'"
                      class="px-1.5 py-0.5 text-[10px] font-semibold rounded-full transition-colors">{{ $consentCount }}</span>
            </button>

        </nav>
    </div>
    {{-- /sub-navigation --}}


    {{-- ════════════════════════════════════
         TOOLBAR
    ════════════════════════════════════ --}}
    <div class="flex items-center justify-between px-6 py-3 bg-white border-b border-gray-100">

        {{-- Left: results count + filter toggle --}}
        <div class="flex items-center gap-3">
            <span class="text-xs text-gray-500">
                {{ $totalCount }} {{ Str::plural('file', $totalCount) }}
                @if($visitCount > 0)&middot; {{ $visitCount }} {{ Str::plural('visit', $visitCount) }}@endif
            </span>
            <button @click="showFilters = !showFilters"
                    :class="showFilters ? 'bg-[#f5eef9] border-[#6a0f70] text-[#6a0f70]' : 'bg-white border-gray-200 text-gray-500 hover:border-gray-300'"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border rounded-lg transition-colors">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
                </svg>
                Filters
                <span x-show="showFilters" class="w-1.5 h-1.5 rounded-full bg-[#6a0f70]"></span>
            </button>
        </div>

        {{-- Right: Upload button + view toggle --}}
        <div class="flex items-center gap-2">

            {{-- View toggle: gallery / timeline --}}
            <div class="flex items-center border border-gray-200 rounded-lg overflow-hidden">
                <button @click="docView = 'gallery'"
                        :class="docView === 'gallery' ? 'bg-[#6a0f70] text-white' : 'bg-white text-gray-400 hover:bg-gray-50'"
                        class="p-1.5 transition-colors" title="Gallery view">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
                        <rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/>
                    </svg>
                </button>
                <button @click="docView = 'timeline'"
                        :class="docView === 'timeline' ? 'bg-[#6a0f70] text-white' : 'bg-white text-gray-400 hover:bg-gray-50'"
                        class="p-1.5 transition-colors" title="Timeline view">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/>
                        <line x1="8" y1="18" x2="21" y2="18"/>
                        <circle cx="3" cy="6" r="1.5" fill="currentColor" stroke="none"/>
                        <circle cx="3" cy="12" r="1.5" fill="currentColor" stroke="none"/>
                        <circle cx="3" cy="18" r="1.5" fill="currentColor" stroke="none"/>
                    </svg>
                </button>
            </div>

            {{-- Upload button — triggers Phase 2 upload modal --}}
            <button @click="showUploadModal = true; uploadStep = 1; uploadHasFiles = false; uploadFiles = []; uploadSelectedTeeth = []; uploadStage = 'general'; uploadProcedure = ''; uploadVisitId = ''; uploadNotes = ''; uploadTags = []; uploadProgress = {}; uploadStatus = 'idle'; uploadErrors = []"
                    class="inline-flex items-center gap-1.5 px-4 py-1.5 text-sm font-medium text-white
                           bg-[#6a0f70] hover:bg-[#380740] rounded-lg transition-colors">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                Upload Files
            </button>

        </div>
    </div>
    {{-- /toolbar --}}


    {{-- ════════════════════════════════════
         FILTER BAR (collapsible)
    ════════════════════════════════════ --}}
    <div x-show="showFilters"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-1"
         class="bg-gray-50 border-b border-gray-200 px-6 py-4">

        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">

            {{-- Visit selector — populated from real treatmentVisits --}}
            <div>
                <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">Visit</label>
                <select x-model="filterVisit"
                        class="w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs text-gray-700
                               focus:outline-none focus:border-[#6a0f70] bg-white">
                    <option value="">All Visits</option>
                    @foreach($treatmentVisits as $v)
                        <option value="{{ $v->id }}">
                            {{ $v->visit_date?->format('d M Y') }} — {{ Str::limit($v->treatment_name, 28) }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Stage selector --}}
            <div>
                <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">Stage</label>
                <select x-model="filterStage"
                        class="w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs text-gray-700
                               focus:outline-none focus:border-[#6a0f70] bg-white">
                    <option value="all">All Stages</option>
                    <option value="general">General</option>
                    <option value="before">Before</option>
                    <option value="during">During</option>
                    <option value="after">After</option>
                    <option value="followup">Follow-up</option>
                </select>
            </div>

            {{-- File type selector --}}
            <div>
                <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">File Type</label>
                <select x-model="filterType"
                        class="w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs text-gray-700
                               focus:outline-none focus:border-[#6a0f70] bg-white">
                    <option value="all">All Types</option>
                    <option value="photo">Photo</option>
                    <option value="video">Video</option>
                    <option value="xray">X-ray / IOPA</option>
                    <option value="opg">OPG</option>
                    <option value="cbct">CBCT</option>
                    <option value="stl">STL / Scan</option>
                    <option value="pdf">PDF</option>
                    <option value="consent">Consent</option>
                    <option value="estimate">Estimate</option>
                    <option value="invoice">Invoice</option>
                    <option value="lab_slip">Lab Slip</option>
                </select>
            </div>

            {{-- Date range: From --}}
            <div>
                <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">From</label>
                <input type="date" x-model="filterFrom"
                       class="w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs text-gray-700
                              focus:outline-none focus:border-[#6a0f70] bg-white">
            </div>

            {{-- Date range: To --}}
            <div>
                <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">To</label>
                <input type="date" x-model="filterTo"
                       class="w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs text-gray-700
                              focus:outline-none focus:border-[#6a0f70] bg-white">
            </div>

        </div>

        {{-- Filter actions row --}}
        <div class="flex items-center gap-3 mt-3">
            <button @click="filterVisit=''; filterStage='all'; filterType='all'; filterFrom=''; filterTo=''"
                    class="px-4 py-1.5 text-xs font-medium border border-gray-200 text-gray-500 rounded-lg hover:bg-gray-100 transition-colors">
                Reset
            </button>
            <p class="text-[10px] text-gray-400">Filters apply client-side. Use sub-tabs above for quick filtering.</p>
        </div>

    </div>
    {{-- /filter bar --}}


    {{-- ════════════════════════════════════════════════════════════
         EMPTY STATE 1: No files at all
         Shown when this patient has zero clinical files.
    ════════════════════════════════════════════════════════════════ --}}
    @if($clinicalFiles->isEmpty())
        <div class="px-6 py-16 text-center">
            <div class="w-16 h-16 rounded-2xl bg-[#f5eef9] flex items-center justify-center mx-auto mb-4">
                <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="#6a0f70"
                     stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                    <circle cx="8.5" cy="8.5" r="1.5"/>
                    <polyline points="21 15 16 10 5 21"/>
                </svg>
            </div>
            <h3 class="text-sm font-bold text-gray-700 mb-1">No clinical files yet</h3>
            <p class="text-xs text-gray-400 mb-5 max-w-xs mx-auto">
                Upload X-rays, photos, consent forms, and other clinical documents for this patient.
            </p>
            <button @click="showUploadModal = true; uploadStep = 1; uploadHasFiles = false; uploadFiles = []; uploadSelectedTeeth = []; uploadStage = 'general'; uploadProcedure = ''; uploadVisitId = ''; uploadNotes = ''; uploadTags = []; uploadProgress = {}; uploadStatus = 'idle'; uploadErrors = []"
                    class="inline-flex items-center gap-1.5 px-5 py-2 text-sm font-semibold text-white
                           bg-[#6a0f70] hover:bg-[#380740] rounded-lg transition-colors">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                    <polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                </svg>
                Upload First File
            </button>
        </div>

    @else

    {{-- ════════════════════════════════════
         GALLERY VIEW (default)
         Files grouped by visit, 3–5 col grid.
         Sub-tab filtering via Alpine matchesSubTab().
    ════════════════════════════════════ --}}
    <div x-show="docView === 'gallery'" style="display:none" class="px-6 py-5 space-y-6">

        @foreach($visitGroups as $visitGroupKey => $groupFiles)
        @php
            $firstFile  = $groupFiles->first();
            $visit      = $firstFile->visit;
            $isGeneral  = ($visitGroupKey === 'general');
            $groupId    = 'group-' . ($isGeneral ? 'general' : $visitGroupKey);

            // Group header metadata
            $groupDate     = $visit?->visit_date?->format('d M Y') ?? 'General';
            $groupProc     = $visit?->treatment_name ?? 'General Documents';
            $groupDoctor   = $visit?->doctor?->name ?? null;
            $groupCount    = $groupFiles->count();

            // Procedure badge colour (cycle through a small set)
            $procBadgeColors = [
                'bg-purple-50 text-purple-700 border-purple-200',
                'bg-amber-50 text-amber-700 border-amber-200',
                'bg-blue-50 text-blue-700 border-blue-200',
                'bg-teal-50 text-teal-700 border-teal-200',
                'bg-rose-50 text-rose-700 border-rose-200',
            ];
            $procBadge = $isGeneral
                ? 'bg-gray-100 text-gray-500 border-gray-200'
                : $procBadgeColors[abs(crc32($groupProc)) % count($procBadgeColors)];
        @endphp

        {{-- ── Visit Group ── --}}
        <div>

            {{-- Group header --}}
            <div class="flex items-center gap-3 mb-3 cursor-pointer select-none"
                 @click="toggleGroup('{{ $groupId }}')">
                <button class="w-6 h-6 flex items-center justify-center rounded transition-transform"
                        :class="isGroupOpen('{{ $groupId }}') ? 'rotate-0' : '-rotate-90'">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#6b7280"
                         stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"/>
                    </svg>
                </button>
                <div class="flex items-center gap-2 flex-1 min-w-0">
                    <span class="text-xs font-bold text-gray-700 flex-shrink-0">{{ $groupDate }}</span>
                    <span class="px-2 py-0.5 text-[10px] font-semibold border rounded-full flex-shrink-0 {{ $procBadge }}">
                        {{ Str::limit($groupProc, 35) }}
                    </span>
                    @if($groupDoctor)
                        <span class="text-xs text-gray-400 truncate">{{ \Illuminate\Support\Str::startsWith(strtolower(trim($groupDoctor)), 'dr') ? $groupDoctor : 'Dr. '.$groupDoctor }}</span>
                    @endif
                    <span class="ml-auto text-[10px] text-gray-400 flex-shrink-0">{{ $groupCount }} {{ Str::plural('file', $groupCount) }}</span>
                </div>
                <div class="w-px h-4 bg-gray-200 flex-shrink-0"></div>
            </div>

            {{-- ── Phase 11: Protocol completion bar ── --}}
            @if(!$isGeneral && isset($visitCompletions[$visitGroupKey]) && $visitCompletions[$visitGroupKey]['total'] > 0)
            @php
                $comp = $visitCompletions[$visitGroupKey];
                $barColor = $comp['percent'] === 100 ? 'bg-green-500' : ($comp['percent'] >= 50 ? 'bg-[#6a0f70]' : 'bg-amber-400');
                $textColor = $comp['percent'] === 100 ? 'text-green-600' : 'text-[#6a0f70]';
            @endphp
            <div class="flex items-center gap-2 mb-3 px-1" title="{{ $comp['protocol_name'] }}">
                {{-- Label --}}
                <span class="text-[10px] font-medium {{ $textColor }} whitespace-nowrap flex-shrink-0">
                    {{ $comp['completed'] }} of {{ $comp['total'] }} steps
                    @if($comp['percent'] === 100)
                        <span class="ml-1">✓</span>
                    @endif
                </span>
                {{-- Progress track --}}
                <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full {{ $barColor }} rounded-full transition-all"
                         style="width: {{ $comp['percent'] }}%"></div>
                </div>
                {{-- Percent --}}
                <span class="text-[10px] text-gray-400 flex-shrink-0">{{ $comp['percent'] }}%</span>
            </div>
            @endif
            {{-- /protocol completion bar --}}

            {{-- File grid --}}
            <div x-show="isGroupOpen('{{ $groupId }}')"
                 class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-3">

                @foreach($groupFiles as $file)
                @php
                    $ft         = $file->file_type ?? 'other';
                    $stg        = $file->stage ?? 'general';
                    $isImg      = in_array($ft, $imageTypes);
                    $thumbBg    = $typeThumbnailBg[$ft] ?? 'bg-gray-100';
                    $ftBadge    = $typeBadge[$ft] ?? 'bg-gray-100 text-gray-500 border-gray-200';
                    $ftLabel    = $typeLabel[$ft] ?? 'File';
                    $stgBadge   = $stageBadge[$stg] ?? 'bg-gray-50 text-gray-500 border-gray-200';
                    $stgLabel   = $stageLabel[$stg] ?? 'General';
                    $displayTitle = $file->title ?: $file->original_filename;
                    $deleteUrl  = route('clinical-files.destroy', [$patient, $file]);
                @endphp

                {{-- File card — x-show: sub-tab + advanced filter bar --}}
                <div x-show="matchesSubTab('{{ $ft }}')
                    && (filterStage === 'all'  || filterStage === '{{ $stg }}')
                    && (filterType  === 'all'  || filterType  === '{{ $ft }}')
                    && (filterVisit === ''     || filterVisit == '{{ $file->visit_id ?? '' }}')
                    && (filterFrom  === ''     || '{{ $file->captured_at?->format('Y-m-d') ?? '' }}' >= filterFrom)
                    && (filterTo    === ''     || '{{ $file->captured_at?->format('Y-m-d') ?? '' }}' <= filterTo)"
                     @click="window.dispatchEvent(new CustomEvent('open-file-viewer', { detail: { id: {{ $file->id }} } }))"
                     class="group relative bg-white border border-gray-200 rounded-xl overflow-hidden
                            hover:border-[#6a0f70]/40 hover:shadow-md transition-all cursor-pointer">

                    {{-- Thumbnail --}}
                    <div class="aspect-square {{ $thumbBg }} flex items-center justify-center relative overflow-hidden">
                        @if($isImg && $file->path)
                            <img src="{{ $file->display_url }}"
                                 alt="{{ $displayTitle }}"
                                 class="w-full h-full object-cover"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                            {{-- Fallback icon if image fails to load --}}
                            <div style="display:none" class="absolute inset-0 items-center justify-center">
                                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#94a3b8"
                                     stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="3" width="18" height="18" rx="2"/>
                                    <circle cx="8.5" cy="8.5" r="1.5"/>
                                    <polyline points="21 15 16 10 5 21"/>
                                </svg>
                            </div>
                        @elseif($ft === 'video')
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#22c55e"
                                 stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="2" y="7" width="15" height="10" rx="2"/>
                                <polygon points="17 9 22 7 22 17 17 15 17 9"/>
                            </svg>
                        @elseif(in_array($ft, ['pdf','consent','estimate','invoice','lab_slip']))
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none"
                                 stroke="{{ $ft === 'consent' ? '#ef4444' : '#f97316' }}"
                                 stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                                <polyline points="14 2 14 8 20 8"/>
                                <line x1="16" y1="13" x2="8" y2="13"/>
                                <line x1="16" y1="17" x2="8" y2="17"/>
                            </svg>
                        @elseif(in_array($ft, ['stl','intraoral_scan']))
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#6366f1"
                                 stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/>
                            </svg>
                        @else
                            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#9ca3af"
                                 stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/>
                                <polyline points="13 2 13 9 20 9"/>
                            </svg>
                        @endif

                        {{-- Hover overlay: quick actions --}}
                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity
                                    flex items-center justify-center gap-2"
                             @click.stop>
                            {{-- View --}}
                            <button @click="window.dispatchEvent(new CustomEvent('open-file-viewer', { detail: { id: {{ $file->id }} } }))"
                                    class="w-7 h-7 rounded-full bg-white/90 flex items-center justify-center hover:bg-white transition-colors"
                                    title="View">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#374151"
                                     stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                            </button>
                            {{-- Download --}}
                            <a href="{{ $file->original_url }}" download="{{ $file->original_filename }}"
                               class="w-7 h-7 rounded-full bg-white/90 flex items-center justify-center hover:bg-white transition-colors"
                               title="Download" @click.stop>
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#374151"
                                     stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                                    <polyline points="7 10 12 15 17 10"/>
                                    <line x1="12" y1="15" x2="12" y2="3"/>
                                </svg>
                            </a>
                            {{-- Delete --}}
                            <button @click.stop="deleteFile({{ $file->id }}, '{{ $deleteUrl }}')"
                                    class="w-7 h-7 rounded-full bg-white/90 flex items-center justify-center hover:bg-red-50 transition-colors"
                                    title="Delete">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#ef4444"
                                     stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/>
                                    <path d="M10 11v6M14 11v6"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                    {{-- /thumbnail --}}

                    {{-- Card body --}}
                    <div class="p-2.5">
                        <div class="flex items-center gap-1 flex-wrap mb-1">
                            <span class="px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide border rounded {{ $ftBadge }}">
                                {{ $ftLabel }}
                            </span>
                            <span class="px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide border rounded {{ $stgBadge }}">
                                {{ $stgLabel }}
                            </span>
                        </div>
                        <p class="text-[11px] font-semibold text-gray-800 leading-tight truncate" title="{{ $displayTitle }}">
                            {{ $displayTitle }}
                        </p>
                        @if($file->tooth_number)
                            <span class="inline-block mt-1 px-1.5 py-0.5 text-[9px] font-medium bg-gray-100 text-gray-600 rounded">
                                Tooth {{ $file->tooth_number }}
                            </span>
                        @endif
                    </div>
                    {{-- /card body --}}

                </div>
                {{-- /file card --}}

                @endforeach

            </div>
            {{-- /file grid --}}

        </div>
        {{-- /visit group --}}

        @endforeach

    </div>
    {{-- /gallery view --}}


    {{-- ════════════════════════════════════
         TIMELINE VIEW (toggle)
         Chronological list, visit-anchored.
    ════════════════════════════════════ --}}
    <div x-show="docView === 'timeline'" style="display:none" class="px-6 py-5">

        <div class="relative">
            {{-- Vertical timeline spine --}}
            <div class="absolute left-[72px] top-0 bottom-0 w-px bg-gray-200"></div>

            <div class="space-y-6">

                @foreach($visitGroups as $visitGroupKey => $groupFiles)
                @php
                    $firstFile  = $groupFiles->first();
                    $visit      = $firstFile->visit;
                    $isGeneral  = ($visitGroupKey === 'general');
                    $groupDate  = $visit?->visit_date?->format('d M Y') ?? 'General';
                    $groupMonth = $visit?->visit_date?->format('d M') ?? 'General';
                    $groupYear  = $visit?->visit_date?->format('Y') ?? '';
                    $groupProc  = $visit?->treatment_name ?? 'General Documents';
                    $groupDoctor= $visit?->doctor?->name ?? null;
                    $groupCount = $groupFiles->count();
                    $dotColor   = $isGeneral ? 'bg-gray-400' : 'bg-[#6a0f70]';
                    // Thumb strip: first 4 files
                    $thumbFiles = $groupFiles->take(4);
                    $overflow   = max(0, $groupCount - 4);
                @endphp

                {{-- Timeline row --}}
                <div class="flex gap-4">

                    {{-- Date column --}}
                    <div class="w-[68px] flex-shrink-0 text-right pt-1">
                        <span class="text-[10px] font-bold text-gray-500 leading-tight block">{{ $groupMonth }}</span>
                        @if($groupYear)
                            <span class="text-[10px] text-gray-400 block">{{ $groupYear }}</span>
                        @endif
                    </div>

                    {{-- Timeline dot --}}
                    <div class="flex-shrink-0 w-4 flex justify-center pt-1.5 relative z-10">
                        <div class="w-3 h-3 rounded-full {{ $dotColor }} border-2 border-white shadow"></div>
                    </div>

                    {{-- Card --}}
                    <div class="flex-1 bg-white border border-gray-200 rounded-xl p-3 hover:border-[#6a0f70]/30 transition-colors min-w-0">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div>
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-xs font-bold text-gray-800">{{ Str::limit($groupProc, 40) }}</span>
                                    @if($firstFile->tooth_number)
                                        <span class="px-2 py-0.5 text-[9px] font-semibold bg-gray-100 text-gray-600 border border-gray-200 rounded-full">
                                            Tooth {{ $firstFile->tooth_number }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-[10px] text-gray-400 mt-0.5">
                                    @if($groupDoctor){{ \Illuminate\Support\Str::startsWith(strtolower(trim($groupDoctor)), 'dr') ? $groupDoctor : 'Dr. '.$groupDoctor }} &middot; @endif
                                    {{ $groupCount }} {{ Str::plural('file', $groupCount) }}
                                </p>
                            </div>
                            <button @click="docView = 'gallery'"
                                    class="text-[10px] text-[#6a0f70] hover:underline flex-shrink-0">View all</button>
                        </div>

                        {{-- Thumbnail strip (up to 4) --}}
                        <div class="flex items-center gap-2">
                            @foreach($thumbFiles as $tf)
                            @php
                                $tfBg   = $typeThumbnailBg[$tf->file_type ?? 'other'] ?? 'bg-gray-100';
                                $tfIsImg = in_array($tf->file_type, $imageTypes);
                            @endphp
                            <div class="w-12 h-12 rounded-lg {{ $tfBg }} flex items-center justify-center flex-shrink-0 overflow-hidden">
                                @if($tfIsImg && $tf->path)
                                    <img src="{{ $tf->display_url }}" alt="" class="w-full h-full object-cover">
                                @elseif($tf->file_type === 'video')
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="15" height="10" rx="2"/><polygon points="17 9 22 7 22 17 17 15 17 9"/></svg>
                                @elseif(in_array($tf->file_type, ['pdf','consent','estimate','invoice','lab_slip']))
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#f97316" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                @else
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M8 12h8M12 8v8"/></svg>
                                @endif
                            </div>
                            @endforeach

                            @if($overflow > 0)
                                <div class="w-12 h-12 rounded-lg bg-gray-100 flex items-center justify-center flex-shrink-0">
                                    <span class="text-[10px] font-bold text-gray-500">+{{ $overflow }}</span>
                                </div>
                            @endif
                        </div>
                        {{-- /thumbnail strip --}}

                    </div>
                    {{-- /card --}}

                </div>
                {{-- /timeline row --}}

                @endforeach

            </div>
        </div>

    </div>
    {{-- /timeline view --}}

    @endif {{-- /clinicalFiles empty check --}}


    {{-- ── Phase 2: Upload Modal (inside x-data scope to share Alpine state) ── --}}
    @include('patients.partials.documents-upload-modal')

</div>
{{-- /x-data documents --}}

</div>
{{-- /x-show activeTab === 'documents' --}}
