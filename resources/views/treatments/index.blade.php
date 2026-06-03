@extends('layouts.app')

@section('title', 'Treatments — Clinic Knowledge Base')

@section('head-extra')
<style>[x-cloak]{display:none!important}</style>
@endsection

@section('content')
<div class="p-6 space-y-6" x-data="{ showCreate: false }">

    {{-- ── Header ── --}}
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest font-[DM_Sans]">Clinic Knowledge Base</p>
            <h1 class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">Treatments</h1>
        </div>
        <button @click="showCreate = true"
                class="flex items-center gap-2 px-4 py-2 bg-[#6a0f70] text-white text-sm font-[DM_Sans] hover:bg-[#52095a] transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
            </svg>
            Add Treatment
        </button>
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

    {{-- ── Treatment Catalog — grouped by category ── --}}
    @forelse($categories as $category)
    <div class="space-y-2">
        {{-- Category header --}}
        <div class="flex items-center justify-between border-b border-[#e8d5f0] pb-2">
            <h2 class="text-lg font-semibold text-[#380740] font-[Cormorant_Garamond]">
                {{ $category->name }}
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

                {{-- Color dot --}}
                <div class="w-3 h-3 rounded-full flex-shrink-0"
                     style="background: {{ $treatment->color ?? '#6a0f70' }}"></div>

                {{-- Name + code --}}
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

                {{-- Duration --}}
                <div class="text-xs text-gray-400 font-[DM_Sans] flex-shrink-0 hidden md:block">
                    {{ $treatment->default_duration_minutes }} min
                </div>

                {{-- Price --}}
                <div class="text-sm text-gray-700 font-[DM_Sans] flex-shrink-0 text-right min-w-[80px]">
                    ₹{{ number_format($treatment->default_price, 0) }}
                    @if($treatment->gst_pct > 0)
                    <span class="block text-xs text-gray-400">+{{ $treatment->gst_pct }}% GST</span>
                    @endif
                </div>

                {{-- SOP status badge --}}
                <div class="flex-shrink-0">
                    @if($treatment->activeSop)
                        <span class="px-2 py-0.5 text-xs font-[DM_Sans] bg-green-50 text-green-700 border border-green-200">SOP ✓</span>
                    @elseif($treatment->sops->count() > 0)
                        <span class="px-2 py-0.5 text-xs font-[DM_Sans] bg-yellow-50 text-yellow-700 border border-yellow-200">Draft</span>
                    @else
                        <span class="px-2 py-0.5 text-xs font-[DM_Sans] bg-gray-50 text-gray-400 border border-gray-200">No SOP</span>
                    @endif
                </div>

                {{-- Rules count --}}
                @if($treatment->rules->count() > 0)
                <div class="flex-shrink-0">
                    <span class="px-2 py-0.5 text-xs font-[DM_Sans] bg-purple-50 text-purple-700 border border-purple-200">
                        {{ $treatment->rules->count() }} rule{{ $treatment->rules->count() !== 1 ? 's' : '' }}
                    </span>
                </div>
                @endif

                {{-- Active / inactive --}}
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
            No treatments yet. Add your first treatment to get started.
        @endif
    </div>
    @endforelse

    {{-- ── Create Treatment Modal — inside x-data scope ── --}}
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
                    <label class="block text-xs text-gray-500 uppercase tracking-wider font-[DM_Sans] mb-1">Base Price (₹) *</label>
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
        </div>{{-- /.bg-white modal inner --}}
    </div>{{-- /.modal backdrop --}}

</div>{{-- /x-data wrapper --}}
@endsection
