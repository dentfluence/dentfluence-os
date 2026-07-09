@extends('layouts.app')
@section('page-title', 'Smart Presentation')

@section('content')
<div class="p-6 space-y-6">

    {{-- ══ HEADER ══════════════════════════════════════════════════════════ --}}
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-display font-semibold text-brand-700">Smart Presentation</h1>
            <p class="text-sm text-gray-500 mt-0.5">Help patients understand and accept their treatment plans.</p>
        </div>
    </div>

    @include('presentations.partials.tabs', ['active' => 'index'])

    {{-- ══ FLASH ═══════════════════════════════════════════════════════════ --}}
    @if(session('success'))
    <div class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-lg px-4 py-3 text-sm">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">
        {{ session('error') }}
    </div>
    @endif

    {{-- ══ STATUS FILTER ═══════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-2 flex-wrap">
        <a href="{{ route('presentations.index') }}"
           class="px-3 py-1.5 text-xs font-medium rounded-full border {{ !$status ? 'bg-brand-600 text-white border-brand-600' : 'text-gray-500 border-gray-200 hover:border-gray-300' }}">
            All
        </a>
        @foreach(\App\Models\Presentation::STATUS_LABELS as $key => $label)
            <a href="{{ route('presentations.index', ['status' => $key]) }}"
               class="px-3 py-1.5 text-xs font-medium rounded-full border {{ $status === $key ? 'bg-brand-600 text-white border-brand-600' : 'text-gray-500 border-gray-200 hover:border-gray-300' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    {{-- ══ LIST ════════════════════════════════════════════════════════════ --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-100 text-left text-xs text-gray-400 uppercase tracking-wide">
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Plan</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Created by</th>
                    <th class="px-4 py-3">Last updated</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @forelse($presentations as $presentation)
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ $presentation->patient?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-600">{{ $presentation->treatmentPlan?->plan_name ?? '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $presentation->status === 'draft' ? 'bg-gray-100 text-gray-600' : 'bg-green-50 text-green-700' }}">
                                {{ $presentation->status_label }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-gray-500">{{ $presentation->creator?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-gray-400 text-xs">{{ $presentation->updated_at?->format('d M, H:i') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('presentations.builder', $presentation) }}"
                               class="text-brand-600 hover:text-brand-700 font-medium text-xs">Open →</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-gray-400 text-sm">
                            No presentations yet. Open a patient's Treatment Plan tab and click
                            <span class="font-medium text-gray-500">"Create Smart Presentation"</span> to start one.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $presentations->links() }}
</div>
@endsection
