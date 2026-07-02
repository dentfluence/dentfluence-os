@extends('layouts.app')
@section('page-title', 'Prescriptions')

@section('content')
<div class="p-4 md:p-6 max-w-7xl mx-auto">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-5 gap-4 flex-wrap">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">Prescriptions</h1>
            <p class="text-sm text-gray-400 mt-0.5">All prescriptions across all patients</p>
        </div>
        <a href="{{ route('rx.settings.index') }}"
           class="text-xs text-gray-500 hover:text-brand-700 border border-gray-200 px-3 py-1.5 rounded-lg hover:bg-gray-50 transition flex items-center gap-1.5">
            Rx Settings
        </a>
    </div>

    {{-- Stat cards --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Total</p>
            <p class="text-2xl font-bold text-gray-800 mt-0.5">{{ number_format($stats['total']) }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Today</p>
            <p class="text-2xl font-bold text-brand-700 mt-0.5">{{ $stats['today'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Issued</p>
            <p class="text-2xl font-bold text-green-700 mt-0.5">{{ $stats['issued'] }}</p>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3">
            <p class="text-xs text-gray-400 uppercase tracking-wide font-medium">Drafts</p>
            <p class="text-2xl font-bold text-amber-600 mt-0.5">{{ $stats['draft'] }}</p>
        </div>
    </div>

    {{-- Filters --}}
    <form method="GET" action="{{ route('prescriptions.index') }}"
          class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 mb-4 flex flex-wrap gap-3 items-end">

        {{-- Search --}}
        <div class="flex-1 min-w-[180px]">
            <label class="block text-xs text-gray-400 font-medium mb-1">Search</label>
            <input type="text" name="q" value="{{ request('q') }}"
                   placeholder="Rx number or patient name…"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-300">
        </div>

        {{-- Status --}}
        <div class="min-w-[140px]">
            <label class="block text-xs text-gray-400 font-medium mb-1">Status</label>
            <select name="status"
                    class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-300">
                <option value="">All statuses</option>
                <option value="draft"         @selected(request('status') === 'draft')>Draft</option>
                <option value="issued"        @selected(request('status') === 'issued')>Issued</option>
                <option value="printed"       @selected(request('status') === 'printed')>Printed</option>
                <option value="whatsapp_sent" @selected(request('status') === 'whatsapp_sent')>WhatsApp Sent</option>
                <option value="email_sent"    @selected(request('status') === 'email_sent')>Email Sent</option>
                <option value="revised"       @selected(request('status') === 'revised')>Revised</option>
                <option value="cancelled"     @selected(request('status') === 'cancelled')>Cancelled</option>
            </select>
        </div>

        {{-- From --}}
        <div class="min-w-[140px]">
            <label class="block text-xs text-gray-400 font-medium mb-1">From</label>
            <input type="date" name="from" value="{{ request('from') }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-300">
        </div>

        {{-- To --}}
        <div class="min-w-[140px]">
            <label class="block text-xs text-gray-400 font-medium mb-1">To</label>
            <input type="date" name="to" value="{{ request('to') }}"
                   class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-brand-300">
        </div>

        <button type="submit"
                class="px-4 py-2 text-sm bg-brand-600 text-white rounded-lg hover:bg-brand-700 transition font-medium">
            Filter
        </button>
        @if(request()->hasAny(['q','status','from','to']))
            <a href="{{ route('prescriptions.index') }}"
               class="px-4 py-2 text-sm text-gray-500 border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                Clear
            </a>
        @endif
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        @if($prescriptions->isEmpty())
            <div class="text-center py-16 text-gray-400">
                <svg class="w-10 h-10 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor"
                     stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414A1 1 0 0 1 19 8.414V19a2 2 0 0 1-2 2z"/>
                </svg>
                <p class="text-sm font-medium">No prescriptions found</p>
                @if(request()->hasAny(['q','status','from','to']))
                    <p class="text-xs mt-1">Try adjusting your filters</p>
                @endif
            </div>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-xs text-gray-400 uppercase tracking-wide font-semibold">
                        <th class="text-left px-4 py-3">Rx Number</th>
                        <th class="text-left px-4 py-3">Patient</th>
                        <th class="text-left px-4 py-3 hidden sm:table-cell">Date</th>
                        <th class="text-left px-4 py-3 hidden md:table-cell">Prescribed By</th>
                        <th class="text-center px-4 py-3 hidden lg:table-cell">Drugs</th>
                        <th class="text-left px-4 py-3">Status</th>
                        <th class="text-right px-4 py-3">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($prescriptions as $rx)
                    @php
                        $statusStyle = match($rx->status) {
                            'issued','printed'       => 'bg-green-100 text-green-700',
                            'draft'                  => 'bg-amber-100 text-amber-700',
                            'whatsapp_sent'          => 'bg-lime-100 text-lime-700',
                            'email_sent'             => 'bg-sky-100 text-sky-700',
                            'revised'                => 'bg-purple-100 text-purple-700',
                            'cancelled'              => 'bg-red-100 text-red-400',
                            default                  => 'bg-gray-100 text-gray-500',
                        };
                        $statusLabel = match($rx->status) {
                            'whatsapp_sent' => 'WhatsApp',
                            'email_sent'    => 'Emailed',
                            default         => ucfirst($rx->status),
                        };
                    @endphp
                    <tr class="hover:bg-gray-50 transition {{ $rx->trashed() ? 'opacity-60' : '' }}">

                        {{-- Rx Number --}}
                        <td class="px-4 py-3">
                            <a href="{{ route('patients.prescriptions.show', [$rx->patient_id, $rx]) }}"
                               class="font-mono text-xs font-semibold text-brand-700 hover:underline">
                                {{ $rx->prescription_number }}
                            </a>
                        </td>

                        {{-- Patient --}}
                        <td class="px-4 py-3">
                            @if($rx->patient)
                                <a href="{{ route('patients.show', $rx->patient_id) }}"
                                   class="font-medium text-gray-800 hover:text-brand-700 transition">
                                    {{ $rx->patient->name }}
                                </a>
                                @if($rx->patient->phone)
                                    <p class="text-xs text-gray-400">{{ $rx->patient->phone }}</p>
                                @endif
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>

                        {{-- Date --}}
                        <td class="px-4 py-3 hidden sm:table-cell text-gray-500">
                            {{ $rx->created_at->format('d M Y') }}
                            <p class="text-xs text-gray-400">{{ $rx->created_at->format('h:i A') }}</p>
                        </td>

                        {{-- Prescribed By --}}
                        <td class="px-4 py-3 hidden md:table-cell text-gray-600">
                            {{ $rx->prescribedBy?->name ?? '—' }}
                        </td>

                        {{-- Drug count --}}
                        <td class="px-4 py-3 hidden lg:table-cell text-center">
                            <span class="text-xs font-semibold text-gray-700 bg-gray-100 rounded-full px-2.5 py-0.5">
                                {{ $rx->items_count ?? '—' }}
                            </span>
                        </td>

                        {{-- Status badge --}}
                        <td class="px-4 py-3">
                            <span class="text-xs font-medium px-2.5 py-0.5 rounded-full {{ $statusStyle }}">
                                {{ $statusLabel }}
                            </span>
                        </td>

                        {{-- Actions --}}
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('patients.prescriptions.show', [$rx->patient_id, $rx]) }}"
                                   class="text-xs text-gray-500 hover:text-brand-700 transition">View</a>
                                @if(!$rx->trashed() && $rx->status !== 'cancelled')
                                    <a href="{{ route('patients.prescriptions.print', [$rx->patient_id, $rx]) }}"
                                       target="_blank"
                                       class="text-xs text-gray-500 hover:text-brand-700 transition">Print</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- Pagination --}}
            @if($prescriptions->hasPages())
                <div class="px-4 py-3 border-t border-gray-100">
                    {{ $prescriptions->links() }}
                </div>
            @endif
        @endif
    </div>

    {{-- Result count --}}
    @if($prescriptions->isNotEmpty())
        <p class="text-xs text-gray-400 mt-2 text-right">
            Showing {{ $prescriptions->firstItem() }}–{{ $prescriptions->lastItem() }}
            of {{ $prescriptions->total() }} prescriptions
        </p>
    @endif

</div>
@endsection
