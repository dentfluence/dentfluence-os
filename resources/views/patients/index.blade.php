@extends('layouts.app')

@section('content')
{{--
    ─────────────────────────────────────────────────────────────
    Patient list — "Add Patient" now opens the modal.
    The modal dispatches 'patient-added' on success, which
    triggers a page reload so the table stays fresh.
    ─────────────────────────────────────────────────────────────
--}}
<div
    x-data="{ init() {
        window.addEventListener('patient-added', () => window.location.reload());
    } }"
    x-init="init()"
>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-[#6a0f70]" style="font-family: 'Cormorant Garamond', serif;">
                Patients
            </h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ $patients->total() }} patient{{ $patients->total() !== 1 ? 's' : '' }} registered</p>
        </div>

        {{-- ← Changed: was <a href="patients.create">, now dispatches modal open --}}
        <button
            type="button"
            x-on:click="$dispatch('open-add-patient')"
            class="inline-flex items-center gap-2 bg-[#6a0f70] text-white text-sm px-4 py-2 hover:bg-[#380740] transition-colors"
        >
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24"
                 fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M5 12h14"/><path d="M12 5v14"/>
            </svg>
            Add Patient
        </button>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('patients.index') }}" class="mb-5">
        <div class="flex gap-2">
            <div class="relative flex-1 max-w-sm">
                <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none text-gray-400">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24"
                         fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                    </svg>
                </span>
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Search name, phone or email…"
                    class="w-full pl-9 pr-3 py-2 text-sm border border-gray-300 bg-white focus:outline-none focus:border-[#6a0f70] focus:ring-1 focus:ring-[#6a0f70]"
                />
            </div>
            <button type="submit"
                    class="px-4 py-2 text-sm bg-[#6a0f70] text-white hover:bg-[#380740] transition-colors">
                Search
            </button>
            @if(request('q'))
                <a href="{{ route('patients.index') }}"
                   class="px-4 py-2 text-sm border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors">
                    Clear
                </a>
            @endif
        </div>
    </form>

    {{-- Success flash (for non-modal flows) --}}
    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Table --}}
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
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-[#f5eef9] border-b border-gray-200 text-left text-xs uppercase tracking-wide text-[#6a0f70]">
                        <th class="px-4 py-3 font-semibold">#</th>
                        <th class="px-4 py-3 font-semibold">Name</th>
                        <th class="px-4 py-3 font-semibold">Phone</th>
                        <th class="px-4 py-3 font-semibold">Gender</th>
                        <th class="px-4 py-3 font-semibold">City</th>
                        <th class="px-4 py-3 font-semibold">Source</th>
                        <th class="px-4 py-3 font-semibold">Registered</th>
                        <th class="px-4 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($patients as $patient)
                    <tr class="hover:bg-[#f5eef9]/40 transition-colors">
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $patient->id }}</td>
                        <td class="px-4 py-3">
                            <div class="font-medium text-gray-800">{{ $patient->name }}</div>
                            @if($patient->chief_complaint)
                                <div class="text-xs text-gray-400 truncate max-w-[200px]">{{ $patient->chief_complaint }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $patient->phone }}</td>
                        <td class="px-4 py-3 text-gray-600 capitalize">{{ $patient->gender ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $patient->city ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if($patient->source)
                                <span class="inline-block px-2 py-0.5 text-xs bg-[#f5eef9] text-[#6a0f70] border border-[#6a0f70]/20">
                                    {{ $patient->source }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $patient->created_at->format('d M Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('patients.show', $patient) }}"
                               class="inline-flex items-center gap-1 text-xs text-[#6a0f70] hover:underline">
                                View
                                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 24 24"
                                     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>
                                </svg>
                            </a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Pagination --}}
            @if($patients->hasPages())
                <div class="px-4 py-3 border-t border-gray-100 flex items-center justify-between text-xs text-gray-500">
                    <span>Showing {{ $patients->firstItem() }}–{{ $patients->lastItem() }} of {{ $patients->total() }}</span>
                    <div class="flex gap-1">
                        @if($patients->onFirstPage())
                            <span class="px-3 py-1 border border-gray-200 text-gray-300 cursor-not-allowed">Prev</span>
                        @else
                            <a href="{{ $patients->previousPageUrl() }}"
                               class="px-3 py-1 border border-gray-200 hover:bg-[#f5eef9] hover:border-[#6a0f70] transition-colors">Prev</a>
                        @endif

                        @if($patients->hasMorePages())
                            <a href="{{ $patients->nextPageUrl() }}"
                               class="px-3 py-1 border border-gray-200 hover:bg-[#f5eef9] hover:border-[#6a0f70] transition-colors">Next</a>
                        @else
                            <span class="px-3 py-1 border border-gray-200 text-gray-300 cursor-not-allowed">Next</span>
                        @endif
                    </div>
                </div>
            @endif
        @endif
    </div>

</div>{{-- /x-data --}}

{{-- ── ADD PATIENT MODAL (lives at the bottom, outside the table wrapper) ── --}}
@include('partials.add-patient-modal')

@endsection