{{-- ═══════════════════════════════════════════════════════════════════════
     PATIENT TAGS SECTION
     Drop this in place of the existing "Patient Tags" div inside the
     Patient Details & Rapport card (the px-5 py-4 border-t section).
     ═══════════════════════════════════════════════════════════════════════ --}}

<div class="px-5 py-4 border-t border-gray-100 bg-gray-50/60" x-data="patientTags()" x-init="init()">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-3">
        <div class="flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="#5b21b6" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/>
                <path d="M7 7h.01"/>
            </svg>
            <span class="section-title">Patient Tags</span>
            <span class="text-[10px] text-gray-400 font-normal normal-case tracking-normal"
                  x-text="'(' + attachedTags.length + ')'"></span>
        </div>
        <button @click="togglePicker()"
                class="text-xs text-[#6a0f70] border border-[#6a0f70]/30 px-2.5 py-1 hover:bg-[#f5eef9] transition-colors font-medium rounded-sm flex items-center gap-1">
            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/><path d="M12 5v14"/>
            </svg>
            Add Tag 
        </button>
    </div>

    {{-- Attached tags --}}
    <div class="flex flex-wrap gap-2 min-h-[28px]">
        <template x-if="attachedTags.length === 0 && !pickerOpen">
            <span class="text-xs text-gray-400 italic py-1">No tags yet — click Add Tag to assign.</span>
        </template>

        <template x-for="tag in attachedTags" :key="tag.id">
            <div class="relative" x-data="{ dropOpen: false }">
                {{-- Tag pill --}}
                <button
                    @click="dropOpen = !dropOpen"
                    @click.away="dropOpen = false"
                    class="tag-pill text-sm px-3 py-1 rounded-full font-semibold flex items-center gap-1.5 cursor-pointer transition-all hover:shadow-sm"
                    :style="`background:${tag.bg_color};color:${tag.color};border-color:${tag.color}40`">
                    <span x-text="tag.name"></span>
                    <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                         class="opacity-60">
                        <path d="m6 9 6 6 6-6"/>
                    </svg>
                </button>

                {{-- Tag dropdown --}}
                <div x-show="dropOpen"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="opacity-100 scale-100"
                     x-transition:leave-end="opacity-0 scale-95"
                     class="absolute top-full left-0 mt-1.5 z-30 bg-white border border-gray-200 rounded-lg shadow-lg w-52 overflow-hidden"
                     style="display:none;">

                    {{-- Tag preview header --}}
                    <div class="px-3 py-2.5 border-b border-gray-100 flex items-center gap-2">
                        <div class="w-3 h-3 rounded-full flex-shrink-0"
                             :style="`background:${tag.color}`"></div>
                        <span class="text-xs font-semibold text-gray-700" x-text="tag.name"></span>
                        <span class="ml-auto text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded"
                              x-text="tag.group"></span>
                    </div>

                    {{-- Actions --}}
                    <div class="py-1">
                        <a :href="`/settings/tags?search=${encodeURIComponent(tag.name)}`"
                           class="flex items-center gap-2.5 px-3 py-2 text-xs text-gray-600 hover:bg-gray-50 hover:text-[#6a0f70] transition-colors w-full text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            Edit tag in Settings
                        </a>
                        <button @click="detachTag(tag); dropOpen = false"
                                class="flex items-center gap-2.5 px-3 py-2 text-xs text-red-500 hover:bg-red-50 transition-colors w-full text-left">
                            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                            </svg>
                            Remove from patient
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- ── Inline Tag Picker ─────────────────────────────────────────── --}}
    <div x-show="pickerOpen"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 -translate-y-1"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 -translate-y-1"
         class="mt-3 bg-white border border-[#6a0f70]/20 rounded-lg shadow-sm overflow-hidden"
         style="display:none;">

        {{-- Search --}}
        <div class="px-3 py-2.5 border-b border-gray-100 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                 stroke="#9ca3af" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <input type="text"
                   x-model="search"
                   @input="filterTags()"
                   placeholder="Search tags…"
                   class="flex-1 text-sm outline-none text-gray-700 placeholder-gray-400 bg-transparent">
            <button @click="pickerOpen = false; search = ''"
                    class="text-gray-300 hover:text-gray-500 transition-colors flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                </svg>
            </button>
        </div>

        {{-- Grouped tag list --}}
        <div class="max-h-60 overflow-y-auto py-1" x-ref="tagList">
            <template x-if="loading">
                <div class="py-6 text-center text-xs text-gray-400">Loading tags…</div>
            </template>

            <template x-if="!loading && filteredGroups.length === 0">
                <div class="py-6 text-center">
                    <p class="text-xs text-gray-400 mb-2">No tags found for "<span x-text="search"></span>"</p>
                    <a href="/settings/tags" class="text-xs text-[#6a0f70] hover:underline font-medium">
                        + Create tag in Settings →
                    </a>
                </div>
            </template>

            <template x-for="group in filteredGroups" :key="group.name">
                <div>
                    {{-- Group header --}}
                    <div class="px-3 pt-2 pb-1">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider"
                              x-text="group.name"></span>
                    </div>
                    {{-- Tags in group --}}
                    <template x-for="tag in group.tags" :key="tag.id">
                        <button
                            @click="attachTag(tag)"
                            :disabled="isAttached(tag.id)"
                            class="flex items-center gap-2.5 w-full px-3 py-1.5 text-left transition-colors"
                            :class="isAttached(tag.id)
                                ? 'opacity-50 cursor-not-allowed bg-gray-50'
                                : 'hover:bg-gray-50 cursor-pointer'">

                            {{-- Color dot --}}
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                  :style="`background:${tag.color}`"></span>

                            {{-- Tag name --}}
                            <span class="text-sm text-gray-700 flex-1" x-text="tag.name"></span>

                            {{-- Already attached checkmark --}}
                            <template x-if="isAttached(tag.id)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24"
                                     fill="none" stroke="#6a0f70" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M20 6 9 17l-5-5"/>
                                </svg>
                            </template>
                        </button>
                    </template>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div class="px-3 py-2 border-t border-gray-100 flex items-center justify-between bg-gray-50/60">
            <span class="text-[10px] text-gray-400">
                <span x-text="attachedTags.length"></span> tag(s) assigned
            </span>
            <a href="/settings/tags"
               class="text-[10px] text-[#6a0f70] hover:underline font-medium flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                Manage tags in Settings
            </a>
        </div>
    </div>
</div>

{{-- ═══════════════════════════════════════════════════════════════════════
     Alpine component — add inside the existing @push('scripts') block,
     BEFORE the closing </script> tag, as a separate function.
     ═══════════════════════════════════════════════════════════════════════ --}}
<script>
function patientTags() {
    return {
        patientId: {{ $patient->id }},
        attachedTags: @json($patient->tags ?? []),  // eager-load tags in controller
        allGroups: [],       // [{name, tags:[]}]
        filteredGroups: [],
        search: '',
        pickerOpen: false,
        loading: false,

        async init() {
            // Load all available tags once on component init
            await this.loadAllTags();
        },

        async loadAllTags() {
            this.loading = true;
            try {
                const r = await fetch(`/patients/${this.patientId}/tags`, {
                    headers: { 'Accept': 'application/json' }
                });
                const d = await r.json();
                // Build grouped array
                this.allGroups = Object.entries(d.grouped).map(([name, tags]) => ({ name, tags }));
                this.filteredGroups = [...this.allGroups];
                // Sync attached
                this.attachedTags = this.allGroups
                    .flatMap(g => g.tags)
                    .filter(t => t.is_attached);
            } catch(e) {
                console.error('Failed to load tags', e);
            }
            this.loading = false;
        },

        togglePicker() {
            this.pickerOpen = !this.pickerOpen;
            if (this.pickerOpen) {
                this.$nextTick(() => {
                    this.$el.querySelector('input[type=text]')?.focus();
                });
            }
        },

        filterTags() {
            const q = this.search.toLowerCase().trim();
            if (!q) {
                this.filteredGroups = [...this.allGroups];
                return;
            }
            this.filteredGroups = this.allGroups
                .map(g => ({
                    name: g.name,
                    tags: g.tags.filter(t =>
                        t.name.toLowerCase().includes(q) ||
                        t.group.toLowerCase().includes(q) ||
                        (t.description || '').toLowerCase().includes(q)
                    )
                }))
                .filter(g => g.tags.length > 0);
        },

        isAttached(tagId) {
            return this.attachedTags.some(t => t.id === tagId);
        },

        async attachTag(tag) {
            if (this.isAttached(tag.id)) return;
            try {
                const r = await fetch(`/patients/${this.patientId}/tags/attach`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({ tag_id: tag.id }),
                });
                const d = await r.json();
                if (d.success) {
                    this.attachedTags.push(d.tag);
                    // Mark as attached in allGroups too
                    this.allGroups.forEach(g => {
                        const t = g.tags.find(t => t.id === tag.id);
                        if (t) t.is_attached = true;
                    });
                    this.filterTags();
                }
            } catch(e) { console.error(e); }
        },

        async detachTag(tag) {
            try {
                const r = await fetch(`/patients/${this.patientId}/tags/${tag.id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                });
                const d = await r.json();
                if (d.success) {
                    this.attachedTags = this.attachedTags.filter(t => t.id !== tag.id);
                    // Unmark in allGroups
                    this.allGroups.forEach(g => {
                        const t = g.tags.find(t => t.id === tag.id);
                        if (t) t.is_attached = false;
                    });
                    this.filterTags();
                }
            } catch(e) { console.error(e); }
        },
    }
}
</script>
