{{--
|==========================================================================
| Global Topbar Search — Patients + Relationships
|
| Searches both /patients/search and /relationship/search in parallel.
| Results are merged and grouped by type in the dropdown.
|
| Minimum 2 chars for patient search, 3 chars for relationship search.
|==========================================================================
--}}
<div class="relative" x-data="dfGlobalSearch()" @click.outside="close()">

    {{-- Input --}}
    <div class="relative">
        <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9d6ea8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
        </span>
        <input
            type="text"
            x-model="query"
            @input.debounce.300ms="search()"
            @keydown.escape="close()"
            @keydown.arrow-down.prevent="moveDown()"
            @keydown.arrow-up.prevent="moveUp()"
            @keydown.enter.prevent="selectActive()"
            placeholder="Search patients, relationships…"
            class="w-full pl-9 pr-4 py-2.5 text-sm border border-purple-200 bg-white focus:outline-none focus:border-purple-500"
            style="font-family:'DM Sans',sans-serif;color:#1a0020;"
            autocomplete="off"
        />
        {{-- Spinner --}}
        <span x-show="loading" class="absolute inset-y-0 right-3 flex items-center">
            <svg class="animate-spin" xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9d6ea8" stroke-width="2"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>
        </span>
        {{-- Clear --}}
        <button x-show="query.length > 0 && !loading" @click="clear()"
                class="absolute inset-y-0 right-3 flex items-center opacity-50 hover:opacity-100">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
    </div>

    {{-- Dropdown --}}
    <div x-show="open" x-cloak
         class="absolute z-50 left-0 right-0 top-full mt-1 bg-white border border-purple-200 shadow-lg overflow-hidden"
         style="max-height:460px;overflow-y:auto;">

        {{-- No results --}}
        <div x-show="patients.length === 0 && relationships.length === 0 && !loading && query.length >= 2"
             class="px-4 py-6 text-center text-sm" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;">
            No results for "<span x-text="query"></span>"
        </div>

        {{-- ── PATIENTS section ── --}}
        <template x-if="patients.length > 0">
            <div>
                <div class="px-4 py-2 text-xs tracking-widest uppercase border-b border-purple-50"
                     style="font-family:'DM Sans',sans-serif;color:#6a0f70;background:#faf5fb;font-weight:600;">
                    Patients
                </div>

                <template x-for="(result, index) in patients" :key="'p-' + result.id">
                    <a :href="result.url"
                       @mouseenter="activeType='patient'; activeIndex=index"
                       :class="(activeType==='patient' && activeIndex===index) ? 'bg-purple-50' : 'bg-white'"
                       class="flex items-center gap-3 px-4 py-3 border-b border-purple-50 hover:bg-purple-50 transition-colors cursor-pointer">
                        <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center border border-purple-200"
                             style="background:#f5eef9;">
                            <span class="text-xs font-semibold" style="color:#6a0f70;font-family:'DM Sans',sans-serif;"
                                  x-text="result.initials"></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate" style="font-family:'DM Sans',sans-serif;color:#1a0020;"
                               x-text="result.name"></p>
                            <p class="text-xs truncate" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;"
                               x-text="result.meta"></p>
                        </div>
                        <span class="flex-shrink-0 text-xs px-2 py-0.5 border border-purple-100"
                              style="font-family:'DM Sans',sans-serif;color:#6a0f70;background:#f5eef9;"
                              x-text="'#' + result.id"></span>
                    </a>
                </template>

                <a :href="'/patients?q=' + encodeURIComponent(query)"
                   class="flex items-center justify-center gap-2 px-4 py-2.5 text-xs hover:bg-purple-50 transition-colors border-t border-purple-50"
                   style="font-family:'DM Sans',sans-serif;color:#6a0f70;">
                    All patients matching "<span x-text="query"></span>"
                    <svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
        </template>

        {{-- ── RELATIONSHIPS section ── --}}
        <template x-if="relationships.length > 0">
            <div>
                <div class="px-4 py-2 text-xs tracking-widest uppercase border-b border-purple-50"
                     style="font-family:'DM Sans',sans-serif;color:#9b26af;background:#fdf5ff;font-weight:600;">
                    Relationships
                </div>

                <template x-for="(result, index) in relationships" :key="'r-' + result.id">
                    <a :href="result.link"
                       @mouseenter="activeType='relationship'; activeIndex=index"
                       :class="(activeType==='relationship' && activeIndex===index) ? 'bg-purple-50' : 'bg-white'"
                       class="flex items-center gap-3 px-4 py-3 border-b border-purple-50 hover:bg-purple-50 transition-colors cursor-pointer">
                        {{-- Avatar --}}
                        <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-content: center border border-purple-200"
                             style="background:linear-gradient(135deg,#6a0f70,#380740);display:flex;align-items:center;justify-content:center;">
                            <span class="text-xs font-semibold" style="color:#fff;font-family:'DM Sans',sans-serif;"
                                  x-text="result.initials"></span>
                        </div>
                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate" style="font-family:'DM Sans',sans-serif;color:#1a0020;"
                               x-text="result.name"></p>
                            <p class="text-xs truncate" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;"
                               x-text="result.meta || result.phone || ''"></p>
                        </div>
                        {{-- Score badge --}}
                        <span class="flex-shrink-0 text-xs px-2 py-0.5 rounded-full"
                              style="font-family:'DM Sans',sans-serif;"
                              :style="result.score >= 75 ? 'background:#e8f7ef;color:#1a7a45' : result.score >= 45 ? 'background:#fff4e0;color:#a05c00' : 'background:#f5eef9;color:#6a0f70'"
                              x-text="result.score + ' pts'"></span>
                    </a>
                </template>
            </div>
        </template>

    </div>
</div>

<script>
function dfGlobalSearch() {
    return {
        query: '',
        patients: [],
        relationships: [],
        loading: false,
        open: false,
        activeType: 'patient',  // 'patient' | 'relationship'
        activeIndex: -1,

        get allResults() {
            // Flat list for keyboard nav: patients first, then relationships
            return [
                ...this.patients.map(r => ({ ...r, _type: 'patient',       _url: r.url  })),
                ...this.relationships.map(r => ({ ...r, _type: 'relationship', _url: r.link })),
            ];
        },

        async search() {
            if (this.query.length < 2) {
                this.patients = [];
                this.relationships = [];
                this.open = false;
                return;
            }
            this.loading = true;
            this.open = true;

            try {
                // Run both searches in parallel
                const [pRes, rRes] = await Promise.allSettled([
                    fetch(`/patients/search?q=${encodeURIComponent(this.query)}`, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    }),
                    this.query.length >= 3
                        ? fetch(`/relationship/search?q=${encodeURIComponent(this.query)}`, {
                              headers: { 'X-Requested-With': 'XMLHttpRequest' }
                          })
                        : Promise.resolve({ ok: false }),
                ]);

                this.patients = pRes.status === 'fulfilled' && pRes.value.ok
                    ? await pRes.value.json()
                    : [];

                this.relationships = rRes.status === 'fulfilled' && rRes.value.ok
                    ? await rRes.value.json()
                    : [];

                this.activeType  = 'patient';
                this.activeIndex = -1;

            } catch(e) {
                this.patients = [];
                this.relationships = [];
            }

            this.loading = false;
        },

        close() { this.open = false; this.activeIndex = -1; },

        clear() {
            this.query = '';
            this.patients = [];
            this.relationships = [];
            this.open = false;
        },

        moveDown() {
            const all = this.allResults;
            const flat = this._flatIndex();
            const next = Math.min(flat + 1, all.length - 1);
            this._setFromFlat(next, all);
        },

        moveUp() {
            const all = this.allResults;
            const flat = this._flatIndex();
            const prev = Math.max(flat - 1, 0);
            this._setFromFlat(prev, all);
        },

        selectActive() {
            const all = this.allResults;
            const flat = this._flatIndex();
            if (flat >= 0 && all[flat]) {
                window.location.href = all[flat]._url;
            }
        },

        // Convert (activeType, activeIndex) → flat position in allResults
        _flatIndex() {
            if (this.activeIndex < 0) return -1;
            if (this.activeType === 'patient') return this.activeIndex;
            return this.patients.length + this.activeIndex;
        },

        // Set (activeType, activeIndex) from flat position
        _setFromFlat(flat, all) {
            if (flat < 0 || flat >= all.length) return;
            if (flat < this.patients.length) {
                this.activeType  = 'patient';
                this.activeIndex = flat;
            } else {
                this.activeType  = 'relationship';
                this.activeIndex = flat - this.patients.length;
            }
        },
    }
}
</script>
