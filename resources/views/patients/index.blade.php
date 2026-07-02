@extends('layouts.app')

@push('styles')
<style>
    .patient-row { cursor: pointer; transition: background 0.1s; }
    .patient-row:hover { background: #faf5ff; }
    .aocp-badge { display:inline-flex; align-items:center; gap:3px; font-size:10px; font-weight:700;
        padding:2px 7px; border-radius:99px; background:#fdf3ff; color:#6a0f70;
        border:1px solid #d8b4fe; white-space:nowrap; }
    .membership-expired { background:#fef2f2; color:#b91c1c; border-color:#fecaca; }
    .membership-none    { background:#f9fafb; color:#9ca3af; border-color:#e5e7eb; }
    .followup-due       { background:#fff7ed; color:#c2410c; border-color:#fed7aa; }
    .followup-pending   { background:#fffbeb; color:#b45309; border-color:#fde68a; }
    .followup-done      { background:#f0fdf4; color:#15803d; border-color:#bbf7d0; }
    .filter-chip { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border:1px solid #e5e7eb;
        font-size:11px; font-weight:500; color:#6b7280; background:white; cursor:pointer; transition:all .15s; }
    .filter-chip:hover { border-color:#6a0f70; color:#6a0f70; }
    .filter-chip.active { border-color:#6a0f70; background:#f5eef9; color:#6a0f70; font-weight:600; }
    [x-cloak] { display:none !important; }
</style>
@endpush

@section('content')
@php
    // Only open filters if at least one filter has a non-empty value
    $hasActiveFilter = collect(['gender','area','age_min','age_max','membership','follow_up','source','birthday_month','family'])
        ->contains(fn($k) => request($k) !== null && request($k) !== '');
@endphp
<div
    x-data="{
        filtersOpen: {{ $hasActiveFilter ? 'true' : 'false' }},
        init() { window.addEventListener('patient-added', () => window.location.reload()); }
    }"
>

{{-- ══════════════════════════════════════════════════════
     HEADER
══════════════════════════════════════════════════════ --}}
<div class="flex items-center justify-between mb-5">
    <div>
        <h1 class="text-2xl font-semibold text-[#6a0f70]" style="font-family:'Cormorant Garamond',serif;">
            Patients
        </h1>
        <p class="text-sm text-gray-500 mt-0.5">
            {{ $patients->total() }} patient{{ $patients->total() !== 1 ? 's' : '' }} registered
            @if(request()->hasAny(['q','gender','area','membership','follow_up','source','birthday_month','age_min','age_max','family']))
                <span class="text-[#6a0f70] font-medium">· filtered</span>
            @endif
        </p>
    </div>
    <button
        type="button"
        dusk="add-patient-btn"
        x-on:click="$dispatch('open-add-patient')"
        class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-4 py-2.5 hover:bg-[#380740] transition-colors font-medium"
    >
        <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none"
             stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M5 12h14"/><path d="M12 5v14"/>
        </svg>
        Add Patient
    </button>
</div>

{{-- ══════════════════════════════════════════════════════
     SEARCH + SORT BAR
══════════════════════════════════════════════════════ --}}
<form method="GET" action="{{ route('patients.index') }}" id="filter-form">

    {{-- Preserve non-empty filter values when searching/sorting --}}
    @foreach(request()->except('q','sort','page') as $key => $value)
        @if(is_string($value) && trim($value) !== '')
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endif
    @endforeach

    <div class="flex gap-2 mb-3">
        {{-- Search --}}
        <div class="relative flex-1 max-w-sm">
            <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                </svg>
            </span>
            <input
                type="text"
                name="q"
                value="{{ request('q') }}"
                placeholder="Name, phone or patient ID…"
                class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 bg-white focus:outline-none focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]"
            />
        </div>

        {{-- Sort --}}
        <select name="sort" onchange="this.form.submit()"
            class="border border-gray-300 text-sm text-gray-700 bg-white px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
            <optgroup label="Date Registered">
                <option value="newest" {{ request('sort','newest') === 'newest' ? 'selected' : '' }}>Newest First</option>
                <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest First</option>
            </optgroup>
            <optgroup label="Name">
                <option value="name"      {{ request('sort') === 'name'      ? 'selected' : '' }}>Name A–Z</option>
                <option value="name_desc" {{ request('sort') === 'name_desc' ? 'selected' : '' }}>Name Z–A</option>
            </optgroup>
            <optgroup label="Patient ID">
                <option value="patient_id"      {{ request('sort') === 'patient_id'      ? 'selected' : '' }}>Patient ID ↑ (Asc)</option>
                <option value="patient_id_desc" {{ request('sort') === 'patient_id_desc' ? 'selected' : '' }}>Patient ID ↓ (Desc)</option>
            </optgroup>
            <optgroup label="Last Visit">
                <option value="last_visit"     {{ request('sort') === 'last_visit'     ? 'selected' : '' }}>Last Visit (Recent)</option>
                <option value="last_visit_asc" {{ request('sort') === 'last_visit_asc' ? 'selected' : '' }}>Last Visit (Oldest)</option>
            </optgroup>
        </select>

        {{-- Search button --}}
        <button type="submit"
            class="px-4 py-2 text-sm bg-[#6a0f70] text-white hover:bg-[#380740] transition-colors font-medium">
            Search
        </button>

        {{-- Filter toggle --}}
        <button type="button"
            x-on:click="filtersOpen = !filtersOpen"
            :class="filtersOpen ? 'border-[#6a0f70] text-[#6a0f70] bg-[#f5eef9]' : 'border-gray-300 text-gray-600'"
            class="inline-flex items-center gap-1.5 px-4 py-2 text-sm border transition-colors">
            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none"
                 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/>
            </svg>
            Filters
            @php
                $activeFilters = collect(['gender','area','age_min','age_max','membership','follow_up','source','birthday_month','family'])
                    ->filter(fn($k) => request($k))->count();
            @endphp
            @if($activeFilters > 0)
                <span class="w-4 h-4 rounded-full bg-[#6a0f70] text-white text-[9px] font-bold flex items-center justify-center">
                    {{ $activeFilters }}
                </span>
            @endif
        </button>

        @if(request()->hasAny(['q','gender','area','age_min','age_max','membership','follow_up','source','birthday_month','family']))
            <a href="{{ route('patients.index') }}"
               class="px-4 py-2 text-sm border border-gray-300 text-gray-500 hover:bg-gray-50 transition-colors">
                Clear all
            </a>
        @endif
    </div>

    {{-- ── FILTER PANEL ── --}}
    <div x-show="filtersOpen" x-transition class="mb-4 border border-gray-200 bg-white p-4 grid grid-cols-2 md:grid-cols-4 gap-4" x-cloak>

        {{-- Gender --}}
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Gender</label>
            <select name="gender" class="w-full border border-gray-200 text-sm text-gray-700 bg-white px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                <option value="">All</option>
                <option value="male"   {{ request('gender') === 'male'   ? 'selected' : '' }}>Male</option>
                <option value="female" {{ request('gender') === 'female' ? 'selected' : '' }}>Female</option>
                <option value="other"  {{ request('gender') === 'other'  ? 'selected' : '' }}>Other</option>
            </select>
        </div>

        {{-- Age range --}}
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Age Range</label>
            <div class="flex gap-2">
                <input type="number" name="age_min" value="{{ request('age_min') }}" placeholder="Min"
                    class="w-full border border-gray-200 text-sm px-2 py-2 focus:outline-none focus:border-[#6a0f70]" min="0" max="150" />
                <input type="number" name="age_max" value="{{ request('age_max') }}" placeholder="Max"
                    class="w-full border border-gray-200 text-sm px-2 py-2 focus:outline-none focus:border-[#6a0f70]" min="0" max="150" />
            </div>
        </div>

        {{-- Membership --}}
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Membership</label>
            <select name="membership" class="w-full border border-gray-200 text-sm text-gray-700 bg-white px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                <option value="">All</option>
                <option value="active"      {{ request('membership') === 'active'      ? 'selected' : '' }}>AOCP Active</option>
                <option value="expired"     {{ request('membership') === 'expired'     ? 'selected' : '' }}>AOCP Expired</option>
                <option value="not_enrolled"{{ request('membership') === 'not_enrolled'? 'selected' : '' }}>Not Enrolled</option>
            </select>
        </div>

        {{-- Follow-up --}}
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Follow-up Status</label>
            <select name="follow_up" class="w-full border border-gray-200 text-sm text-gray-700 bg-white px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                <option value="">All</option>
                <option value="due"       {{ request('follow_up') === 'due'       ? 'selected' : '' }}>Due</option>
                <option value="pending"   {{ request('follow_up') === 'pending'   ? 'selected' : '' }}>Pending</option>
                <option value="completed" {{ request('follow_up') === 'completed' ? 'selected' : '' }}>Completed</option>
            </select>
        </div>

        {{-- Source --}}
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Source</label>
            <select name="source" class="w-full border border-gray-200 text-sm text-gray-700 bg-white px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                <option value="">All</option>
                @foreach(['Google','Instagram','Facebook','Referral','Walk-In','Camp','Website','Other'] as $src)
                    <option value="{{ $src }}" {{ request('source') === $src ? 'selected' : '' }}>{{ $src }}</option>
                @endforeach
            </select>
        </div>

        {{-- Area / Locality (dropdown) --}}
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Area / Locality</label>
            <select name="area" class="w-full border border-gray-200 text-sm text-gray-700 bg-white px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                <option value="">All Areas</option>
                @foreach($areas as $areaOption)
                    <option value="{{ $areaOption }}" {{ request('area') === $areaOption ? 'selected' : '' }}>
                        {{ $areaOption }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Birthday month --}}
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Birthday Month</label>
            <select name="birthday_month" class="w-full border border-gray-200 text-sm text-gray-700 bg-white px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                <option value="">Any</option>
                @foreach(['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $month)
                    <option value="{{ $i + 1 }}" {{ request('birthday_month') == ($i+1) ? 'selected' : '' }}>{{ $month }}</option>
                @endforeach
            </select>
        </div>

        {{-- Family --}}
        <div>
            <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wider mb-1">Family</label>
            <select name="family" class="w-full border border-gray-200 text-sm text-gray-700 bg-white px-3 py-2 focus:outline-none focus:border-[#6a0f70]">
                <option value="">All</option>
                <option value="has_family" {{ request('family') === 'has_family' ? 'selected' : '' }}>Has Family</option>
                <option value="no_family"  {{ request('family') === 'no_family'  ? 'selected' : '' }}>No Family</option>
            </select>
        </div>

        {{-- Apply button --}}
        <div class="flex items-end">
            <button type="submit"
                class="w-full px-4 py-2 bg-[#6a0f70] text-white text-sm font-semibold hover:bg-[#380740] transition">
                Apply Filters
            </button>
        </div>
    </div>

</form>

{{-- Flash --}}
@if(session('success'))
    <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm">
        {{ session('success') }}
    </div>
@endif

{{-- ══════════════════════════════════════════════════════
     TABLE
══════════════════════════════════════════════════════ --}}
<div class="bg-white border border-gray-200 overflow-hidden">
    @if($patients->isEmpty())
        <div class="flex flex-col items-center justify-center py-20 text-gray-400">
            <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"
                 class="mb-3 opacity-40">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            <p class="text-sm">No patients found{{ request('q') ? ' for "'.request('q').'"' : '' }}</p>
            <button
                type="button"
                x-on:click="$dispatch('open-add-patient')"
                class="mt-4 inline-flex items-center gap-1.5 text-xs text-[#6a0f70] underline underline-offset-2 hover:text-[#380740]"
            >
                Register your first patient →
            </button>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm" style="min-width:900px;">
                <thead>
                    <tr class="bg-[#f5eef9] border-b border-gray-200 text-left text-xs uppercase tracking-wide text-[#6a0f70]">
                        <th class="px-4 py-3 font-semibold">Patient ID</th>
                        <th class="px-4 py-3 font-semibold">Name</th>
                        <th class="px-4 py-3 font-semibold">Age</th>
                        <th class="px-4 py-3 font-semibold">Gender</th>
                        <th class="px-4 py-3 font-semibold">Phone</th>
                        <th class="px-4 py-3 font-semibold">Area</th>
                        <th class="px-4 py-3 font-semibold">Source</th>
                        <th class="px-4 py-3 font-semibold">Membership</th>
                        <th class="px-4 py-3 font-semibold">Follow-up</th>
                        <th class="px-4 py-3 font-semibold">Registered</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($patients as $patient)
                    @php
                        $memberStatus = $patient->effective_membership_status;
                        $followStatus = $patient->follow_up_status ?? 'none';
                        $profileUrl   = route('patients.show', $patient);
                    @endphp
                    <tr
                        class="patient-row"
                        onclick="window.location='{{ $profileUrl }}'"
                        title="Open {{ $patient->name }}'s profile"
                    >
                        {{-- Patient ID --}}
                        <td class="px-4 py-3">
                            <span class="text-xs text-gray-400 font-mono">{{ $patient->patient_id ?? ('DF-'.str_pad($patient->id,5,'0',STR_PAD_LEFT)) }}</span>
                        </td>

                        {{-- Name + AOCP badge --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                {{-- Avatar --}}
                                <div class="w-7 h-7 rounded-full flex-shrink-0 flex items-center justify-center text-white text-xs font-semibold"
                                     style="background: linear-gradient(135deg,#6a0f70,#380740); font-family:'Cormorant Garamond',serif;">
                                    {{ $patient->initials }}
                                </div>
                                <div>
                                    <div class="font-medium text-gray-800 flex items-center gap-1.5">
                                        {{ $patient->name }}
                                        @if($patient->is_aocp_active)
                                            <span class="aocp-badge">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" fill="currentColor">
                                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                                </svg>
                                                AOCP
                                            </span>
                                        @endif
                                    </div>
                                    @if($patient->tags->count() > 0)
                                        <div class="flex gap-1 mt-0.5 flex-wrap">
                                            @foreach($patient->tags->take(2) as $tag)
                                                <span class="text-[10px] px-1.5 py-0.5 bg-[#f5eef9] text-[#6a0f70] border border-[#6a0f70]/10">{{ $tag->name }}</span>
                                            @endforeach
                                            @if($patient->tags->count() > 2)
                                                <span class="text-[10px] text-gray-400">+{{ $patient->tags->count() - 2 }}</span>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>

                        {{-- Age --}}
                        <td class="px-4 py-3 text-gray-600 text-xs">
                            {{ $patient->age ?? '—' }}
                        </td>

                        {{-- Gender --}}
                        <td class="px-4 py-3 text-gray-600 text-xs capitalize">
                            {{ $patient->gender ?? '—' }}
                        </td>

                        {{-- Phone --}}
                        <td class="px-4 py-3 text-gray-700 text-xs font-mono">
                            {{ $patient->phone }}
                        </td>

                        {{-- Area --}}
                        <td class="px-4 py-3 text-gray-600 text-xs">
                            {{ $patient->area ?? $patient->city ?? '—' }}
                        </td>

                        {{-- Source --}}
                        <td class="px-4 py-3">
                            @if($patient->source)
                                <span class="inline-block px-2 py-0.5 text-[10px] bg-[#f5eef9] text-[#6a0f70] border border-[#6a0f70]/20 font-medium">
                                    {{ $patient->source }}
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>

                        {{-- Membership --}}
                        <td class="px-4 py-3">
                            @if($memberStatus === 'active')
                                <div>
                                    <span class="aocp-badge">★ AOCP Active</span>
                                    @if($patient->membership_expires_at)
                                        <div class="text-[10px] text-purple-500 mt-0.5">
                                            Exp: {{ $patient->membership_expires_at->format('d M Y') }}
                                        </div>
                                    @endif
                                </div>
                            @elseif($memberStatus === 'expired')
                                <div>
                                    <span class="aocp-badge membership-expired">AOCP Expired</span>
                                    @if($patient->membership_expires_at)
                                        <div class="text-[10px] text-red-400 mt-0.5">
                                            {{ $patient->membership_expires_at->format('d M Y') }}
                                        </div>
                                    @endif
                                </div>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>

                        {{-- Follow-up --}}
                        <td class="px-4 py-3">
                            @if($followStatus === 'due')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold followup-due border" style="border-radius:2px;">
                                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span> Due
                                </span>
                            @elseif($followStatus === 'pending')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold followup-pending border" style="border-radius:2px;">
                                    <span class="w-1.5 h-1.5 rounded-full bg-yellow-500"></span> Pending
                                </span>
                            @elseif($followStatus === 'completed')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-semibold followup-done border" style="border-radius:2px;">
                                    <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Done
                                </span>
                            @else
                                <span class="text-gray-300 text-xs">—</span>
                            @endif
                        </td>

                        {{-- Registered date --}}
                        <td class="px-4 py-3 text-gray-400 text-xs">
                            {{ $patient->created_at->format('d M Y') }}
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if($patients->hasPages())
            <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                <span>Showing {{ $patients->firstItem() }}–{{ $patients->lastItem() }} of {{ $patients->total() }}</span>
                <div class="flex gap-1">
                    @if($patients->onFirstPage())
                        <span class="px-3 py-1.5 border border-gray-200 text-gray-300 cursor-not-allowed">← Prev</span>
                    @else
                        <a href="{{ $patients->previousPageUrl() }}"
                           class="px-3 py-1.5 border border-gray-200 hover:bg-[#f5eef9] hover:border-[#6a0f70] transition-colors">← Prev</a>
                    @endif
                    @if($patients->hasMorePages())
                        <a href="{{ $patients->nextPageUrl() }}"
                           class="px-3 py-1.5 border border-gray-200 hover:bg-[#f5eef9] hover:border-[#6a0f70] transition-colors">Next →</a>
                    @else
                        <span class="px-3 py-1.5 border border-gray-200 text-gray-300 cursor-not-allowed">Next →</span>
                    @endif
                </div>
            </div>
        @endif
    @endif
</div>

</div>{{-- /x-data --}}

@include('partials.add-patient-modal')

{{-- Auto-open the Add Patient modal when arriving via ?new=1
     (e.g. the "Create new patient" link in appointment booking / topbar). --}}
@if(request('new'))
<script>window.addEventListener('load', () => window.dispatchEvent(new CustomEvent('open-add-patient')));</script>
@endif
@endsection
