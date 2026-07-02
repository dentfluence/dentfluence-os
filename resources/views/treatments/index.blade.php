@extends('layouts.app')

@section('title', 'Treatments — Clinic Knowledge Base')

@section('head-extra')
<style>[x-cloak]{display:none!important}</style>
@endsection

@section('content')
<div class="p-6 space-y-6"
     x-data="{
        showCreate: false,
        showManageCategories: false,
        editCategory: null,
        openEditCategory(cat) { this.editCategory = cat; },
        closeEditCategory() { this.editCategory = null; }
     }">

    {{-- ── Header ── --}}
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest font-[DM_Sans]">Clinic Knowledge Base</p>
            <h1 class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">Treatments</h1>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            {{-- Price List --}}
            <a href="{{ route('treatments.price-list') }}"
               class="flex items-center gap-2 px-4 py-2 bg-white text-[#6a0f70] text-sm font-[DM_Sans] border border-[#6a0f70] hover:bg-[#f3e8f9] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path d="M9 7H6a2 2 0 00-2 2v9a2 2 0 002 2h9a2 2 0 002-2v-3M9 7l6-4 4 4-6 4-4-4zM9 7v10"/>
                </svg>
                Price List
            </a>
            {{-- Manage Categories --}}
            <button @click="showManageCategories = true"
                    class="flex items-center gap-2 px-4 py-2 bg-[#f3e8f9] text-[#6a0f70] text-sm font-[DM_Sans] border border-[#e8d5f0] hover:bg-[#e8d5f0] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path d="M4 6h16M4 10h16M4 14h10"/>
                </svg>
                Manage Categories
            </button>
            {{-- Add Treatment --}}
            <button @click="showCreate = true"
                    class="flex items-center gap-2 px-4 py-2 bg-[#6a0f70] text-white text-sm font-[DM_Sans] hover:bg-[#52095a] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add Treatment
            </button>
        </div>
    </div>

    {{-- ── Stats Row ── --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider font-[DM_Sans] mb-1">Total</p>
            <p class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">{{ $totalTreatments }}</p>
        </div>
        <div class="bg-white border border-[#e8d5f0] p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider font-[DM_Sans] mb-1">Active</p>
            <p class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">{{ $activeCount }}</p>
        </div>
        <div class="bg-white border border-{{ $sopPending > 0 ? '[#f59e0b]' : '[#e8d5f0]' }} p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider font-[DM_Sans] mb-1">No SOP Yet</p>
            <p class="text-3xl font-semibold text-{{ $sopPending > 0 ? '[#b45309]' : '[#380740]' }} font-[Cormorant_Garamond]">{{ $sopPending }}</p>
        </div>
        <div class="bg-white border border-{{ $reviewDue > 0 ? '[#ef4444]' : '[#e8d5f0]' }} p-4">
            <p class="text-xs text-gray-400 uppercase tracking-wider font-[DM_Sans] mb-1">Review Due (30d)</p>
            <p class="text-3xl font-semibold text-{{ $reviewDue > 0 ? '[#b91c1c]' : '[#380740]' }} font-[Cormorant_Garamond]">{{ $reviewDue }}</p>
        </div>
    </div>

    {{-- ── Search ── --}}
    <form method="GET" action="{{ route('treatments.index') }}" class="flex gap-3">
        <input type="text" name="q" value="{{ $search }}"
               placeholder="Search by name or code…"
               class="flex-1 border border-[#e8d5f0] px-4 py-2 text-sm font-[DM_Sans] text-gray-700 focus:outline-none focus:border-[#6a0f70]">
        <button type="submit"
                class="px-4 py-2 bg-[#f3e8f9] text-[#6a0f70] text-sm font-[DM_Sans] border border-[#e8d5f0] hover:bg-[#e8d5f0] transition">
            Search
        </button>
        @if($search)
        <a href="{{ route('treatments.index') }}"
           class="px-4 py-2 text-gray-400 text-sm font-[DM_Sans] border border-[#e8d5f0] hover:text-gray-600 transition">
            Clear
        </a>
        @endif
    </form>

    {{-- ── Flash ── --}}
    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm font-[DM_Sans]">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm font-[DM_Sans]">
        {{ session('error') }}
    </div>
    @endif

    {{-- ── Treatment Catalog — grouped by category ── --}}
    @forelse($categories as $category)
    <div class="space-y-2">
        {{-- Category header --}}
        <div class="flex items-center justify-between border-b border-[#e8d5f0] pb-2">
            <h2 class="text-lg font-semibold text-[#380740] font-[Cormorant_Garamond]">
                {{ $category->name }}
                @unless($category->is_active)
                    <span class="ml-2 text-xs font-[DM_Sans] text-gray-400 bg-gray-100 px-2 py-0.5">Inactive</span>
                @endunless
            </h2>
            <span class="text-xs text-gray-400 font-[DM_Sans]">
                {{ $category->allTreatments->count() }} treatment{{ $category->allTreatments->count() !== 1 ? 's' : '' }}
            </span>
        </div>

        {{-- Treatment rows --}}
        <div class="space-y-1">
            @foreach($category->allTreatments as $treatment)
            <a href="{{ route('treatments.show', $treatment) }}"
               class="flex items-center gap-4 px-4 py-3 bg-white border border-[#f0e0f8] hover:border-[#6a0f70] hover:bg-[#faf5ff] transition group">

                <div class="w-3 h-3 rounded-full flex-shrink-0"
                     style="background: {{ $treatment->color ?? '#6a0f70' }}"></div>

                <div class="flex-1 min-w-0">
                    <span class="text-sm font-medium text-gray-800 font-[DM_Sans] group-hover:text-[#6a0f70]">
                        {{ $treatment->name }}
                    </span>
                    @if($treatment->code)
                    <span class="ml-2 text-xs text-gray-400 font-[DM_Sans]">({{ $treatment->code }})</span>
                    @endif
                    @if($treatment->description)
                    <p class="text-xs text-gray-400 mt-0.5 font-[DM_Sans] truncate">{{ $treatment->description }}</p>
                    @endif
                </div>

                <div class="text-xs text-gray-400 font-[DM_Sans] flex-shrink-0 hidden md:block">
                    {{ $treatment->default_duration_minutes }} min
                </div>

                <div class="text-sm text-gray-700 font-[DM_Sans] flex-shrink-0 text-right min-w-[80px]">
                    Rs. {{ number_format($treatment->default_price, 0) }}
                    @if($treatment->gst_pct > 0)
                    <span class="block text-xs text-gray-400">+{{ $treatment->gst_pct }}% GST</span>
                    @endif
                </div>

                <div class="flex-shrink-0">
                    @if($treatment->activeSop)
                        <span class="px-2 py-0.5 text-xs font-[DM_Sans] bg-green-50 text-green-700 border border-green-200">SOP ✓</span>
                    @elseif($treatment->sops->count() > 0)
                        <span class="px-2 py-0.5 text-xs font-[DM_Sans] bg-yellow-50 text-yellow-700 border border-yellow-200">Draft</span>
                    @else
                        <span class="px-2 py-0.5 text-xs font-[DM_Sans] bg-gray-50 text-gray-400 border border-gray-200">No SOP</span>
                    @endif
                </div>

                @if($treatment->rules->count() > 0)
                <div class="flex-shrink-0">
                    <span class="px-2 py-0.5 text-xs font-[DM_Sans] bg-purple-50 text-purple-700 border border-purple-200">
                        {{ $treatment->rules->count() }} rule{{ $treatment->rules->count() !== 1 ? 's' : '' }}
                    </span>
                </div>
                @endif

                <div class="flex-shrink-0">
                    @if($treatment->is_active)
                        <span class="w-2 h-2 rounded-full bg-green-400 inline-block" title="Active"></span>
                    @else
                        <span class="w-2 h-2 rounded-full bg-gray-300 inline-block" title="Inactive"></span>
                    @endif
                </div>

                <svg class="w-4 h-4 text-gray-300 group-hover:text-[#6a0f70] flex-shrink-0 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <polyline points="9 18 15 12 9 6"/>
                </svg>
            </a>
            @endforeach
        </div>
    </div>
    @empty
    <div class="text-center py-16 text-gray-400 font-[DM_Sans]">
        @if($search)
            No treatments match "{{ $search }}"
        @else
            No treatments yet. Add a category first, then add treatments.
        @endif
    </div>
    @endforelse


    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- MANAGE CATEGORIES MODAL                                               --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div x-show="showManageCategories" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center"
         style="background: rgba(14,1,24,0.55);"
         @click.self="showManageCategories = false"
         @keydown.escape.window="showManageCategories = false">

        <div class="bg-white w-full max-w-2xl mx-4 shadow-xl max-h-[90vh] flex flex-col" @click.stop>

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#e8d5f0] flex-shrink-0">
                <h3 class="text-xl font-semibold text-[#380740] font-[Cormorant_Garamond]">Manage Treatment Categories</h3>
                <button type="button" @click="showManageCategories = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <div class="overflow-y-auto flex-1 p-6 space-y-6">

                {{-- ── Add New Category Form ── --}}
                <div class="bg-[#faf5ff] border border-[#e8d5f0] p-4">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-3">Add New Category</p>
                    <form method="POST" action="{{ route('treatment-categories.store') }}" class="space-y-3">
                        @csrf
                        <div class="grid grid-cols-1 gap-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs text-gray-500 font-[DM_Sans] mb-1">Name *</label>
                                    <input type="text" name="name" required placeholder="e.g. Orthodontics"
                                           class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 font-[DM_Sans] mb-1">Calendar Color</label>
                                    <div class="flex items-center gap-2">
                                        <input type="color" name="color" value="#6a0f70"
                                               style="width:38px;height:34px;padding:2px;border:1px solid #e8d5f0;border-radius:4px;cursor:pointer;">
                                        <span class="text-xs text-gray-400 font-[DM_Sans]">Appointment card color</span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 font-[DM_Sans] mb-1">Description</label>
                                <input type="text" name="description" placeholder="Optional short description"
                                       class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                            </div>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit"
                                    class="px-4 py-2 bg-[#6a0f70] text-white text-sm font-[DM_Sans] hover:bg-[#52095a] transition">
                                Add Category
                            </button>
                        </div>
                    </form>
                </div>

                {{-- ── Existing Categories List ── --}}
                <div class="space-y-2">
                    <p class="text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans]">Existing Categories</p>

                    @forelse(\App\Models\TreatmentCategory::orderBy('name')->withCount('allTreatments')->get() as $cat)
                    <div class="bg-white border border-[#e8d5f0]" x-data="{ editing: false }">

                        {{-- View row --}}
                        <div class="flex items-center gap-3 px-4 py-3" x-show="!editing">
                            {{-- Color swatch --}}
                            <div style="width:14px;height:14px;border-radius:3px;flex-shrink:0;background:{{ $cat->color ?? '#6a0f70' }};"></div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800 font-[DM_Sans]">
                                    {{ $cat->name }}
                                    @unless($cat->is_active)
                                        <span class="ml-2 text-xs text-gray-400">(inactive)</span>
                                    @endunless
                                </p>
                                @if($cat->description)
                                <p class="text-xs text-gray-400 font-[DM_Sans]">{{ $cat->description }}</p>
                                @endif
                            </div>
                            <span class="text-xs text-gray-400 font-[DM_Sans] flex-shrink-0">
                                {{ $cat->all_treatments_count }} treatment{{ $cat->all_treatments_count !== 1 ? 's' : '' }}
                            </span>
                            <button @click="editing = true"
                                    class="text-xs text-[#6a0f70] font-[DM_Sans] hover:underline flex-shrink-0">
                                Edit
                            </button>
                            @if($cat->all_treatments_count === 0)
                            <form method="POST" action="{{ route('treatment-categories.destroy', $cat) }}"
                                  onsubmit="return confirm('Delete category \'{{ addslashes($cat->name) }}\'?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-400 font-[DM_Sans] hover:underline flex-shrink-0">
                                    Delete
                                </button>
                            </form>
                            @else
                            <span class="text-xs text-gray-300 font-[DM_Sans] flex-shrink-0" title="Move treatments out first to delete">
                                Delete
                            </span>
                            @endif
                        </div>

                        {{-- Edit row --}}
                        <form method="POST" action="{{ route('treatment-categories.update', $cat) }}"
                              x-show="editing" class="px-4 py-3 space-y-2 bg-[#faf5ff]">
                            @csrf @method('PUT')
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-xs text-gray-500 font-[DM_Sans] mb-1">Name *</label>
                                    <input type="text" name="name" value="{{ $cat->name }}" required
                                           class="w-full border border-[#e8d5f0] px-3 py-1.5 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 font-[DM_Sans] mb-1">Description</label>
                                    <input type="text" name="description" value="{{ $cat->description }}"
                                           class="w-full border border-[#e8d5f0] px-3 py-1.5 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                                </div>
                                <div>
                                    <label class="block text-xs text-gray-500 font-[DM_Sans] mb-1">Color</label>
                                    <input type="color" name="color" value="{{ $cat->color ?? '#6a0f70' }}"
                                           style="width:100%;height:32px;padding:2px;border:1px solid #e8d5f0;border-radius:4px;cursor:pointer;">
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <label class="flex items-center gap-2 text-sm font-[DM_Sans] text-gray-600 cursor-pointer">
                                    <input type="hidden" name="is_active" value="0">
                                    <input type="checkbox" name="is_active" value="1" {{ $cat->is_active ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-[#6a0f70]">
                                    Active
                                </label>
                                <div class="flex gap-2">
                                    <button type="button" @click="editing = false"
                                            class="px-3 py-1.5 text-xs text-gray-500 font-[DM_Sans] border border-[#e8d5f0] hover:bg-gray-50 transition">
                                        Cancel
                                    </button>
                                    <button type="submit"
                                            class="px-3 py-1.5 text-xs bg-[#6a0f70] text-white font-[DM_Sans] hover:bg-[#52095a] transition">
                                        Save
                                    </button>
                                </div>
                            </div>
                        </form>

                    </div>
                    @empty
                    <p class="text-sm text-gray-400 font-[DM_Sans] text-center py-4">No categories yet.</p>
                    @endforelse
                </div>

            </div>{{-- /overflow scroll --}}
        </div>{{-- /modal inner --}}
    </div>{{-- /manage categories modal --}}


    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- ADD TREATMENT MODAL                                                   --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    <div x-show="showCreate" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center"
         style="background: rgba(14,1,24,0.55);"
         @click="showCreate = false"
         @keydown.escape.window="showCreate = false">

        <div class="bg-white w-full max-w-lg mx-4 shadow-xl" @click.stop>
            <div class="flex items-center justify-between px-6 py-4 border-b border-[#e8d5f0]">
                <h3 class="text-xl font-semibold text-[#380740] font-[Cormorant_Garamond]">Add Treatment</h3>
                <button type="button" @click="showCreate = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <form method="POST" action="{{ route('treatments.store') }}" class="p-6 space-y-4">
            @csrf

            <div class="grid grid-cols-2 gap-4">
                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Category *</label>
                    <select name="treatment_category_id" required
                            class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] text-gray-700 focus:outline-none focus:border-[#6a0f70] bg-white">
                        <option value="">Select category…</option>
                        @foreach(\App\Models\TreatmentCategory::active()->orderBy('name')->get() as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-400 font-[DM_Sans]">
                        Don't see your category?
                        <button type="button" @click="showCreate = false; showManageCategories = true"
                                class="text-[#6a0f70] underline">Add it first</button>
                    </p>
                </div>

                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Treatment Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Root Canal Treatment"
                           class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Code</label>
                    <input type="text" name="code" placeholder="e.g. RCT-01"
                           class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Duration (min) *</label>
                    <input type="number" name="default_duration_minutes" value="30" required min="5" max="480"
                           class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Base Price (Rs. ) *</label>
                    <input type="number" name="default_price" value="0" required min="0" step="0.01"
                           class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                </div>

                <div>
                    <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">GST %</label>
                    <input type="number" name="gst_pct" value="0" min="0" max="100" step="0.01"
                           class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70]">
                </div>

                <div class="col-span-2">
                    <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Description</label>
                    <textarea name="description" rows="2" placeholder="Brief description for staff…"
                              class="w-full border border-[#e8d5f0] px-3 py-2 text-sm font-[DM_Sans] focus:outline-none focus:border-[#6a0f70] resize-none"></textarea>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="showCreate = false"
                        class="px-4 py-2 text-sm text-gray-500 font-[DM_Sans] border border-[#e8d5f0] hover:bg-gray-50 transition">
                    Cancel
                </button>
                <button type="submit"
                        class="px-5 py-2 bg-[#6a0f70] text-white text-sm font-[DM_Sans] hover:bg-[#52095a] transition">
                    Create Treatment
                </button>
            </div>
            </form>
        </div>
    </div>

</div>{{-- /x-data wrapper --}}
@endsection
