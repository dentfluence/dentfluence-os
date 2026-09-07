@extends('layouts.app')

{{--
    Unverified visits — completed work nobody has checked yet.

    If this screen is empty the clinic is current. If it is long, the owner is
    about to pay out on work he has not looked at. Oldest first, on purpose.
--}}

@section('content')
@php
    $isAdmin = auth()->user()->isAdminRole();
    $oldest  = $visits->first();
    $days    = $oldest && $oldest->visit_date ? $oldest->visit_date->diffInDays(now()) : null;
@endphp

<div class="p-6 space-y-5">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">Unverified Visits</h1>
            <p class="text-xs text-gray-400 uppercase tracking-widest font-[DM_Sans] mt-1">
                Completed work awaiting an administrative check
            </p>
        </div>
        <div class="text-right">
            <p class="text-3xl font-semibold text-[#380740] font-[Cormorant_Garamond]">{{ $visits->total() }}</p>
            <p class="text-xs font-[DM_Sans] {{ $days !== null && $days > 7 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                @if($days === null) nothing outstanding
                @else oldest is {{ $days }} day{{ $days === 1 ? '' : 's' }} old @endif
            </p>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm font-[DM_Sans]">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm font-[DM_Sans]">
        @foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach
    </div>
    @endif

    {{-- ── Filters ─────────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('visits.unverified') }}"
          class="flex flex-wrap items-center gap-2 bg-white border border-[#e8d5f0] p-4">
        <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
               class="border border-[#e8d5f0] px-2 py-1.5 text-xs font-[DM_Sans] focus:border-[#6a0f70] focus:outline-none">
        <span class="text-xs text-gray-400">to</span>
        <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
               class="border border-[#e8d5f0] px-2 py-1.5 text-xs font-[DM_Sans] focus:border-[#6a0f70] focus:outline-none">

        <select name="doctor_id"
                class="border border-[#e8d5f0] px-2 py-1.5 text-xs font-[DM_Sans] focus:border-[#6a0f70] focus:outline-none">
            <option value="">All doctors</option>
            @foreach($doctors as $doctor)
            <option value="{{ $doctor->id }}" @selected(($filters['doctor_id'] ?? null) == $doctor->id)>{{ $doctor->name }}</option>
            @endforeach
        </select>

        <button type="submit"
                class="px-3 py-1.5 text-xs font-semibold uppercase tracking-widest font-[DM_Sans] border border-[#6a0f70] text-[#6a0f70] hover:bg-[#f5eef9] transition">
            Filter
        </button>
        <a href="{{ route('visits.unverified') }}"
           class="px-3 py-1.5 text-xs font-[DM_Sans] text-gray-400 hover:text-[#6a0f70]">Clear</a>
    </form>

    {{-- ── List ────────────────────────────────────────────────────────────── --}}
    <div class="bg-white border border-[#e8d5f0]">
        @if($visits->isEmpty())
        <div class="px-5 py-12 text-center">
            <p class="text-sm text-gray-400 font-[DM_Sans]">Nothing is waiting. Every completed visit has been verified.</p>
        </div>
        @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm font-[DM_Sans]">
                <thead>
                    <tr class="text-[11px] text-gray-400 uppercase tracking-widest border-b border-[#f0e4f5]">
                        <th class="text-left  px-5 py-2.5 font-semibold">Visit date</th>
                        <th class="text-left  px-5 py-2.5 font-semibold">Patient</th>
                        <th class="text-left  px-5 py-2.5 font-semibold">Doctor</th>
                        <th class="text-left  px-5 py-2.5 font-semibold">Treatment</th>
                        <th class="text-right px-5 py-2.5 font-semibold">Items</th>
                        <th class="text-right px-5 py-2.5 font-semibold">Waiting</th>
                        @if($isAdmin)<th class="text-right px-5 py-2.5 font-semibold">Verify</th>@endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f7f0fa]">
                    @foreach($visits as $visit)
                    @php $wait = $visit->visit_date ? $visit->visit_date->diffInDays(now()) : null; @endphp
                    <tr class="hover:bg-[#faf5fc]">
                        <td class="px-5 py-2.5 whitespace-nowrap">{{ optional($visit->visit_date)->format('d M Y') ?? '—' }}</td>
                        <td class="px-5 py-2.5">
                            <a href="{{ route('patients.show', $visit->patient_id) }}"
                               class="text-[#380740] font-medium hover:text-[#6a0f70]">{{ $visit->patient->name ?? '—' }}</a>
                        </td>
                        <td class="px-5 py-2.5 text-gray-500">{{ $visit->doctor->name ?? 'Unassigned' }}</td>
                        <td class="px-5 py-2.5 text-gray-500">{{ $visit->treatment_name ?: '—' }}</td>
                        <td class="px-5 py-2.5 text-right text-gray-500">{{ $visit->visit_items_count }}</td>
                        <td class="px-5 py-2.5 text-right {{ $wait !== null && $wait > 7 ? 'text-red-600 font-semibold' : 'text-gray-500' }}">
                            {{ $wait === null ? '—' : $wait . 'd' }}
                        </td>
                        @if($isAdmin)
                        <td class="px-5 py-2.5 text-right">
                            <form method="POST" action="{{ route('visits.verify', $visit) }}" class="inline-flex items-center gap-2 justify-end">
                                @csrf
                                <input type="text" name="note" maxlength="255" placeholder="note (optional)"
                                       class="w-36 border border-[#e8d5f0] px-2 py-1 text-xs font-[DM_Sans] focus:border-[#6a0f70] focus:outline-none">
                                <button type="submit"
                                        class="px-3 py-1 text-xs font-semibold uppercase tracking-widest font-[DM_Sans] bg-[#6a0f70] text-white hover:bg-[#380740] transition">
                                    Verify
                                </button>
                            </form>
                        </td>
                        @endif
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-5 py-3 border-t border-[#f0e4f5]">
            {{ $visits->links() }}
        </div>
        @endif
    </div>

    @unless($isAdmin)
    <p class="text-xs text-gray-400 font-[DM_Sans]">
        Only an admin can verify a visit. This list is here so you can see what is still outstanding.
    </p>
    @endunless

</div>
@endsection
