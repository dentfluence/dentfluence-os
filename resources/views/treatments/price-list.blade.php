@extends('layouts.app')

@section('title', 'Treatment Price List')

@section('content')
<div class="p-6 space-y-6">

    {{-- ── Header ── --}}
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-widest font-[DM_Sans]">Clinic Knowledge Base</p>
            <h1 class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">Treatment Price List</h1>
            <p class="text-sm text-gray-500 font-[DM_Sans] mt-1">All active treatments with charges. Staff reference only — prices may vary per patient.</p>
        </div>
        <div class="flex items-center gap-3">
            <button onclick="window.print()"
                    class="flex items-center gap-2 px-4 py-2 bg-white border border-[#e8d5f0] text-[#6a0f70] text-sm font-[DM_Sans] hover:bg-[#f3e8f9] transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>
                </svg>
                Print
            </button>
            <a href="{{ route('treatments.index') }}"
               class="flex items-center gap-2 px-4 py-2 bg-[#f3e8f9] text-[#6a0f70] text-sm font-[DM_Sans] border border-[#e8d5f0] hover:bg-[#e8d5f0] transition">
                ← Back to Treatments
            </a>
        </div>
    </div>

    {{-- ── Category tabs / filter ── --}}
    <div class="flex items-center gap-2 flex-wrap border-b border-[#e8d5f0] pb-0">
        <a href="{{ route('treatments.price-list') }}"
           class="px-4 py-2 text-sm font-[DM_Sans] border-b-2 {{ !request('cat') ? 'border-[#6a0f70] text-[#6a0f70]' : 'border-transparent text-gray-500 hover:text-[#6a0f70]' }} transition -mb-px">
            All
        </a>
        @foreach($categories as $cat)
        @if($cat->allTreatments->count())
        <a href="{{ route('treatments.price-list', ['cat' => $cat->id]) }}"
           class="px-4 py-2 text-sm font-[DM_Sans] border-b-2 {{ request('cat') == $cat->id ? 'border-[#6a0f70] text-[#6a0f70]' : 'border-transparent text-gray-500 hover:text-[#6a0f70]' }} transition -mb-px whitespace-nowrap">
            {{ $cat->name }}
            <span class="text-xs text-gray-400">({{ $cat->allTreatments->count() }})</span>
        </a>
        @endif
        @endforeach
    </div>

    {{-- ── Price tables per category ── --}}
    @php $filterCat = request('cat'); @endphp

    @forelse($categories as $category)
    @if($filterCat && $filterCat != $category->id) @continue @endif
    @if($category->allTreatments->isEmpty()) @continue @endif

    <div class="space-y-2">
        {{-- Category heading --}}
        <div class="flex items-center gap-3">
            <h2 class="text-xl font-semibold text-[#380740] font-[Cormorant_Garamond]">
                {{ $category->name }}
            </h2>
            <span class="text-xs text-gray-400 font-[DM_Sans]">
                {{ $category->allTreatments->where('is_active', true)->count() }} active
                @if($category->allTreatments->where('is_active', false)->count())
                    / {{ $category->allTreatments->where('is_active', false)->count() }} inactive
                @endif
            </span>
        </div>

        {{-- Price table --}}
        <div class="bg-white border border-[#e8d5f0] overflow-hidden">
            <table class="w-full text-sm font-[DM_Sans]">
                <thead>
                    <tr class="bg-[#f3e8f9] border-b border-[#e8d5f0]">
                        <th class="text-left px-4 py-2.5 text-xs text-[#380740] uppercase tracking-wider font-semibold">Treatment</th>
                        <th class="text-left px-4 py-2.5 text-xs text-[#380740] uppercase tracking-wider font-semibold hidden md:table-cell">Code</th>
                        <th class="text-right px-4 py-2.5 text-xs text-[#380740] uppercase tracking-wider font-semibold">Base Price</th>
                        <th class="text-right px-4 py-2.5 text-xs text-[#380740] uppercase tracking-wider font-semibold hidden sm:table-cell">Min</th>
                        <th class="text-right px-4 py-2.5 text-xs text-[#380740] uppercase tracking-wider font-semibold hidden sm:table-cell">Max</th>
                        <th class="text-right px-4 py-2.5 text-xs text-[#380740] uppercase tracking-wider font-semibold hidden md:table-cell">GST</th>
                        <th class="text-right px-4 py-2.5 text-xs text-[#380740] uppercase tracking-wider font-semibold hidden md:table-cell">Duration</th>
                        <th class="text-center px-4 py-2.5 text-xs text-[#380740] uppercase tracking-wider font-semibold">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f0e0f8]">
                    @foreach($category->allTreatments as $t)
                    <tr class="{{ $t->is_active ? '' : 'opacity-50' }} hover:bg-[#faf5ff] transition">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full flex-shrink-0"
                                      style="background: {{ $t->color ?? '#6a0f70' }}"></span>
                                <span class="font-medium text-gray-800">{{ $t->name }}</span>
                            </div>
                            @if($t->description)
                            <p class="text-xs text-gray-400 ml-4 mt-0.5">{{ Str::limit($t->description, 60) }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-400 hidden md:table-cell">
                            {{ $t->code ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-gray-800">
                            Rs. {{ number_format($t->default_price, 0) }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-500 hidden sm:table-cell">
                            {{ $t->min_price ? 'Rs. '.number_format($t->min_price, 0) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-500 hidden sm:table-cell">
                            {{ $t->max_price ? 'Rs. '.number_format($t->max_price, 0) : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-500 hidden md:table-cell">
                            {{ $t->gst_pct > 0 ? $t->gst_pct.'%' : '—' }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-500 hidden md:table-cell">
                            {{ $t->default_duration_minutes }} min
                        </td>
                        <td class="px-4 py-3 text-center">
                            @if($t->is_active)
                                <span class="inline-block w-2 h-2 rounded-full bg-green-400" title="Active"></span>
                            @else
                                <span class="inline-block px-2 py-0.5 text-xs text-gray-400 bg-gray-100">Inactive</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
                {{-- Category total row --}}
                <tfoot>
                    <tr class="bg-[#faf5ff] border-t border-[#e8d5f0]">
                        <td colspan="2" class="px-4 py-2 text-xs text-gray-500 font-[DM_Sans]">
                            {{ $category->allTreatments->where('is_active', true)->count() }} active treatments in this category
                        </td>
                        <td class="px-4 py-2 text-right text-xs text-gray-600 font-semibold">
                            Avg Rs. {{ number_format($category->allTreatments->where('is_active', true)->avg('default_price'), 0) }}
                        </td>
                        <td colspan="5" class="hidden sm:table-cell"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @empty
    <div class="text-center py-16 text-gray-400 font-[DM_Sans]">
        No active treatment categories found.
    </div>
    @endforelse

    {{-- ── Grand summary ── --}}
    @if(!$filterCat)
    <div class="bg-white border border-[#e8d5f0] p-4 flex flex-wrap gap-8">
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-wider font-[DM_Sans] mb-1">Total Treatments</p>
            <p class="text-2xl font-semibold text-[#380740] font-[Cormorant_Garamond]">
                {{ $categories->sum(fn($c) => $c->allTreatments->where('is_active', true)->count()) }}
            </p>
        </div>
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-wider font-[DM_Sans] mb-1">Categories</p>
            <p class="text-2xl font-semibold text-[#380740] font-[Cormorant_Garamond]">{{ $categories->count() }}</p>
        </div>
        <div>
            <p class="text-xs text-gray-400 uppercase tracking-wider font-[DM_Sans] mb-1">Price Range</p>
            @php
                $allActive = $categories->flatMap(fn($c) => $c->allTreatments->where('is_active', true));
                $minP = $allActive->min('default_price');
                $maxP = $allActive->max('default_price');
            @endphp
            <p class="text-2xl font-semibold text-[#380740] font-[Cormorant_Garamond]">
                Rs. {{ number_format($minP, 0) }} – Rs. {{ number_format($maxP, 0) }}
            </p>
        </div>
    </div>
    @endif

</div>

<style>
@media print {
    nav, aside, header, .no-print { display: none !important; }
    body { font-size: 12px; }
    .p-6 { padding: 0; }
}
</style>
@endsection
