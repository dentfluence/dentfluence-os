<div class="relative" x-data="patientSearch()" @click.outside="close()">

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
            placeholder="Search patient — name, phone, ID…"
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
         style="max-height:420px;overflow-y:auto;">

        {{-- No results --}}
        <div x-show="results.length === 0 && !loading && query.length >= 2"
             class="px-4 py-6 text-center text-sm" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;">
            No patients found for "<span x-text="query"></span>"
        </div>

        {{-- Results --}}
        <template x-if="results.length > 0">
            <div>
                {{-- Section header --}}
                <div class="px-4 py-2 text-xs tracking-widest uppercase border-b border-purple-50"
                     style="font-family:'DM Sans',sans-serif;color:#6a0f70;background:#faf5fb;font-weight:600;">
                    Patients
                </div>

                <template x-for="(result, index) in results" :key="result.id">
                    <a :href="result.url"
                       @mouseenter="activeIndex = index"
                       :class="activeIndex === index ? 'bg-purple-50' : 'bg-white'"
                       class="flex items-center gap-3 px-4 py-3 border-b border-purple-50 hover:bg-purple-50 transition-colors cursor-pointer">

                        {{-- Avatar --}}
                        <div class="flex-shrink-0 w-8 h-8 flex items-center justify-center border border-purple-200"
                             style="background:#f5eef9;">
                            <span class="text-xs font-semibold" style="color:#6a0f70;font-family:'DM Sans',sans-serif;"
                                  x-text="result.initials"></span>
                        </div>

                        {{-- Info --}}
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium truncate" style="font-family:'DM Sans',sans-serif;color:#1a0020;"
                               x-text="result.name"></p>
                            <p class="text-xs truncate" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;"
                               x-text="result.meta"></p>
                        </div>

                        {{-- ID badge --}}
                        <span class="flex-shrink-0 text-xs px-2 py-0.5 border border-purple-100"
                              style="font-family:'DM Sans',sans-serif;color:#6a0f70;background:#f5eef9;"
                              x-text="'#' + result.id"></span>
                    </a>
                </template>

                {{-- View all --}}
                <a :href="'/patients?q=' + encodeURIComponent(query)"
                   class="flex items-center justify-center gap-2 px-4 py-3 text-xs hover:bg-purple-50 transition-colors"
                   style="font-family:'DM Sans',sans-serif;color:#6a0f70;">
                    View all results for "<span x-text="query"></span>"
                    <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                </a>
            </div>
        </template>
    </div>
</div>

<script>
function patientSearch() {
    return {
        query: '',
        results: [],
        loading: false,
        open: false,
        activeIndex: -1,

        async search() {
            if (this.query.length < 2) {
                this.results = [];
                this.open = false;
                return;
            }
            this.loading = true;
            this.open = true;
            try {
                const res = await fetch(`/patients/search?q=${encodeURIComponent(this.query)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                this.results = await res.json();
                this.activeIndex = -1;
            } catch(e) {
                this.results = [];
            }
            this.loading = false;
        },

        close() {
            this.open = false;
            this.activeIndex = -1;
        },

        clear() {
            this.query = '';
            this.results = [];
            this.open = false;
        },

        moveDown() {
            if (this.activeIndex < this.results.length - 1) this.activeIndex++;
        },

        moveUp() {
            if (this.activeIndex > 0) this.activeIndex--;
        },

        selectActive() {
            if (this.activeIndex >= 0 && this.results[this.activeIndex]) {
                window.location.href = this.results[this.activeIndex].url;
            }
        }
    }
}
</script>
