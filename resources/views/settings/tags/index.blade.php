{{-- resources/views/settings/tags/index.blade.php --}}

@extends('layouts.app')

@push('styles')
<style>
.tag-row { transition: background 0.12s; }
.tag-row:hover { background: #faf5ff; }
.group-header { font-size:11px;font-weight:700;color:#5b21b6;letter-spacing:0.07em;text-transform:uppercase;padding:10px 20px 4px; }
</style>
@endpush

@section('content')
<div x-data="tagSettings()" x-init="init()" class="bg-[#f3f4f8] min-h-screen">

    {{-- Page header --}}
    <div class="bg-white border-b border-gray-200 px-6 py-4">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-lg font-bold text-gray-900" style="font-family:'Cormorant Garamond',serif;">
                    Tag Management
                </h1>
                <p class="text-xs text-gray-400 mt-0.5">
                    Tags are shared across all patient profiles. Search a tag to see which patients and treatments are linked.
                </p>
            </div>
            <button @click="showCreateForm = true"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-sm bg-[#6a0f70] text-white hover:bg-[#380740] transition-colors font-medium">
                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M5 12h14"/><path d="M12 5v14"/>
                </svg>
                New Tag
            </button>
        </div>

        {{-- Search bar --}}
        <div class="mt-4 flex items-center gap-3">
            <div class="relative flex-1 max-w-md">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" xmlns="http://www.w3.org/2000/svg"
                     width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                     stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text"
                       x-model="search"
                       @input.debounce.300ms="doSearch()"
                       placeholder="Search tags, groups, or keywords…"
                       class="w-full pl-9 pr-4 py-2 text-sm border border-gray-200 bg-white rounded-lg focus:outline-none focus:border-[#6a0f70]">
            </div>
            <template x-if="search">
                <div class="text-xs text-gray-500 flex items-center gap-1.5">
                    <span>Showing results for</span>
                    <span class="font-semibold text-[#6a0f70]" x-text="`"${search}"`"></span>
                    <button @click="search=''; doSearch()" class="text-gray-400 hover:text-gray-600 ml-1">
                        <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                        </svg>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <div class="max-w-[1200px] mx-auto px-6 py-5 grid grid-cols-1 xl:grid-cols-[1fr_340px] gap-5 items-start">

        {{-- ── Tag list ──────────────────────────────────────────────── --}}
        <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">

            <template x-if="loading">
                <div class="py-16 text-center text-sm text-gray-400">Loading tags…</div>
            </template>

            <template x-if="!loading && groupedTags.length === 0">
                <div class="py-16 text-center">
                    <p class="text-sm text-gray-500 font-semibold mb-1">No tags found</p>
                    <p class="text-xs text-gray-400 mb-4" x-text="search ? `No results for "${search}"` : 'No tags created yet.'"></p>
                    <button @click="showCreateForm = true"
                            class="inline-flex items-center gap-1 text-xs text-[#6a0f70] hover:underline font-medium">
                        + Create your first tag
                    </button>
                </div>
            </template>

            <template x-for="group in groupedTags" :key="group.name">
                <div>
                    <div class="group-header flex items-center justify-between pr-4">
                        <span x-text="group.name"></span>
                        <span class="text-[10px] font-normal normal-case text-gray-400"
                              x-text="group.tags.length + ' tag' + (group.tags.length !== 1 ? 's' : '')"></span>
                    </div>
                    <template x-for="tag in group.tags" :key="tag.id">
                        <div class="tag-row flex items-center gap-3 px-5 py-3 border-t border-gray-50 group">

                            {{-- Color swatch --}}
                            <div class="w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0"
                                 :style="`background:${tag.bg_color}`">
                                <div class="w-3 h-3 rounded-full" :style="`background:${tag.color}`"></div>
                            </div>

                            {{-- Tag pill preview --}}
                            <div class="flex-shrink-0">
                                <span class="tag-pill text-xs px-2.5 py-1 rounded-full font-semibold"
                                      :style="`background:${tag.bg_color};color:${tag.color};border-color:${tag.color}40`"
                                      x-text="tag.name"></span>
                            </div>

                            {{-- Name + description --}}
                            <div class="flex-1 min-w-0">
                                <div class="text-sm font-medium text-gray-800" x-text="tag.name"></div>
                                <div class="text-xs text-gray-400" x-text="tag.description || '—'"></div>
                            </div>

                            {{-- Patient count --}}
                            <div class="flex-shrink-0 text-center min-w-[60px]">
                                <div class="text-sm font-bold text-gray-700" x-text="tag.patients_count ?? 0"></div>
                                <div class="text-[10px] text-gray-400">patients</div>
                            </div>

                            {{-- System badge --}}
                            <template x-if="tag.is_system">
                                <span class="text-[10px] px-2 py-0.5 bg-amber-50 text-amber-600 border border-amber-200 rounded font-medium flex-shrink-0">
                                    System
                                </span>
                            </template>

                            {{-- Actions --}}
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity flex-shrink-0">
                                <button @click="startEdit(tag)"
                                        :disabled="tag.is_system"
                                        class="p-1.5 text-gray-400 hover:text-[#6a0f70] hover:bg-purple-50 rounded transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                        title="Edit tag">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                </button>
                                <button @click="deleteTag(tag)"
                                        :disabled="tag.is_system"
                                        class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition-colors disabled:opacity-30 disabled:cursor-not-allowed"
                                        title="Delete tag">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none"
                                         stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        {{-- ── Right: Create / Edit form ─────────────────────────────── --}}
        <div class="space-y-4">

            {{-- Create / Edit form --}}
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden"
                 x-show="showCreateForm || editingTag">

                <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                    <span class="section-title" x-text="editingTag ? 'Edit Tag' : 'New Tag'"></span>
                    <button @click="resetForm()" class="text-gray-400 hover:text-gray-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"/><path d="m6 6 12 12"/>
                        </svg>
                    </button>
                </div>

                <div class="p-5 space-y-3">

                    {{-- Name --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Tag Name *</label>
                        <input type="text" x-model="form.name" placeholder="e.g. Implant Prospect"
                               class="w-full text-sm border border-gray-200 px-3 py-2 rounded focus:outline-none focus:border-[#6a0f70]">
                    </div>

                    {{-- Group --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Group *</label>
                        <input type="text" x-model="form.group"
                               list="tag-groups"
                               placeholder="e.g. Treatment Interest, Financial…"
                               class="w-full text-sm border border-gray-200 px-3 py-2 rounded focus:outline-none focus:border-[#6a0f70]">
                        <datalist id="tag-groups">
                            @foreach(\App\Models\Tag::allGroups() as $g)
                            <option value="{{ $g }}">
                            @endforeach
                        </datalist>
                        <p class="text-[10px] text-gray-400 mt-1">Type an existing group or create a new one.</p>
                    </div>

                    {{-- Colors --}}
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Text Color</label>
                            <div class="flex items-center gap-2 border border-gray-200 rounded px-2 py-1.5">
                                <input type="color" x-model="form.color" class="w-6 h-6 rounded cursor-pointer border-0">
                                <span class="text-xs text-gray-500 font-mono" x-text="form.color"></span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Background Color</label>
                            <div class="flex items-center gap-2 border border-gray-200 rounded px-2 py-1.5">
                                <input type="color" x-model="form.bg_color" class="w-6 h-6 rounded cursor-pointer border-0">
                                <span class="text-xs text-gray-500 font-mono" x-text="form.bg_color"></span>
                            </div>
                        </div>
                    </div>

                    {{-- Live preview --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-2">Preview</label>
                        <div class="flex items-center gap-2 p-3 bg-gray-50 rounded border border-dashed border-gray-200">
                            <span class="tag-pill text-sm px-3 py-1 rounded-full font-semibold"
                                  :style="`background:${form.bg_color};color:${form.color};border-color:${form.color}40`"
                                  x-text="form.name || 'Tag Preview'"></span>
                        </div>
                    </div>

                    {{-- Description --}}
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Description <span class="font-normal text-gray-400">(optional)</span></label>
                        <input type="text" x-model="form.description" placeholder="Brief description of when to use this tag…"
                               class="w-full text-sm border border-gray-200 px-3 py-2 rounded focus:outline-none focus:border-[#6a0f70]">
                    </div>

                    {{-- Actions --}}
                    <div class="flex gap-2 pt-1">
                        <button @click="saveTag()"
                                :disabled="!form.name || !form.group || saving"
                                class="flex-1 py-2 text-sm bg-[#380740] text-white hover:bg-[#6a0f70] rounded transition-colors font-medium disabled:opacity-50">
                            <span x-text="saving ? 'Saving…' : (editingTag ? 'Update Tag' : 'Create Tag')"></span>
                        </button>
                        <button @click="resetForm()"
                                class="px-4 py-2 text-sm border border-gray-200 text-gray-500 rounded hover:bg-gray-50">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>

            {{-- Placeholder when form not open --}}
            <template x-if="!showCreateForm && !editingTag">
                <div class="bg-white border border-dashed border-gray-200 rounded-lg py-10 text-center">
                    <div class="w-10 h-10 rounded-full bg-purple-50 flex items-center justify-center mx-auto mb-3">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none"
                             stroke="#7c3aed" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2H2v10l9.29 9.29c.94.94 2.48.94 3.42 0l6.58-6.58c.94-.94.94-2.48 0-3.42L12 2Z"/>
                            <path d="M7 7h.01"/>
                        </svg>
                    </div>
                    <p class="text-sm font-semibold text-gray-600 mb-1">Create a new tag</p>
                    <p class="text-xs text-gray-400 mb-3">Tags are applied to patients across all profiles.</p>
                    <button @click="showCreateForm = true"
                            class="text-xs text-[#6a0f70] hover:underline font-medium">
                        + New Tag
                    </button>
                </div>
            </template>

            {{-- Info card --}}
            <div class="bg-amber-50 border border-amber-200 rounded-lg px-4 py-3">
                <p class="text-xs font-semibold text-amber-700 mb-1">How tags work</p>
                <ul class="text-xs text-amber-700 space-y-1 list-disc list-inside">
                    <li>Tags are <strong>global</strong> — shared across all patient profiles</li>
                    <li>Search a tag name here to see all linked patients</li>
                    <li>System tags (marked) cannot be deleted</li>
                    <li>Group tags logically — e.g. Financial, Behavior, Treatment Interest</li>
                </ul>
            </div>

        </div>

    </div>
</div>

@push('scripts')
<script>
function tagSettings() {
    return {
        groupedTags: @json($tags->map(fn($group, $name) => ['name' => $name, 'tags' => $group])->values()),
        search: '{{ $search ?? '' }}',
        loading: false,
        saving: false,
        showCreateForm: false,
        editingTag: null,
        form: {
            name: '',
            group: '',
            color: '#6a0f70',
            bg_color: '#f5f3ff',
            description: '',
        },

        init() {},

        async doSearch() {
            this.loading = true;
            const params = new URLSearchParams({ search: this.search });
            const r = await fetch(`/settings/tags?${params}`, {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            });
            // For full page (non-AJAX), just reload with param
            window.location.href = `/settings/tags?search=${encodeURIComponent(this.search)}`;
        },

        startEdit(tag) {
            this.editingTag = tag;
            this.form = {
                name: tag.name,
                group: tag.group,
                color: tag.color,
                bg_color: tag.bg_color,
                description: tag.description || '',
            };
            this.showCreateForm = false;
            this.$nextTick(() => {
                this.$el.querySelector('input[x-model="form.name"]')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        },

        resetForm() {
            this.editingTag = null;
            this.showCreateForm = false;
            this.form = { name: '', group: '', color: '#6a0f70', bg_color: '#f5f3ff', description: '' };
        },

        async saveTag() {
            if (!this.form.name || !this.form.group) return;
            this.saving = true;
            const url  = this.editingTag ? `/settings/tags/${this.editingTag.id}` : '/settings/tags';
            const method = this.editingTag ? 'PUT' : 'POST';
            try {
                const r = await fetch(url, {
                    method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify(this.form),
                });
                const d = await r.json();
                if (d.success) {
                    window.location.reload();
                }
            } catch(e) { console.error(e); }
            this.saving = false;
        },

        async deleteTag(tag) {
            if (!confirm(`Delete tag "${tag.name}"? It will be removed from all patients.`)) return;
            const r = await fetch(`/settings/tags/${tag.id}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
            });
            const d = await r.json();
            if (d.success) window.location.reload();
        },
    }
}
</script>
@endpush
@endsection
