@extends('layouts.app')
@section('page-title', 'Prescription Templates')

@section('content')
<div class="p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-display font-semibold text-brand-800">Prescription Templates</h1>
            <p class="text-xs text-gray-500 mt-0.5">Clinic-level presets — RCT Pain, Post-Extraction, Pericoronitis…</p>
        </div>
        <div class="flex gap-3">
            <a href="{{ route('rx.settings.prescription-templates.create') }}"
               class="inline-flex items-center gap-1.5 px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition">
                + New Template
            </a>
            <a href="{{ route('rx.settings.index') }}" class="text-sm text-gray-500 hover:text-brand-600 flex items-center">← Settings</a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-2 bg-green-50 text-green-700 rounded-lg border border-green-200 text-sm">{{ session('success') }}</div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($items as $template)
        <div class="bg-white rounded-xl border border-gray-200 p-4 hover:border-brand-300 hover:shadow-md transition">
            <div class="flex items-start justify-between mb-2">
                <h3 class="font-semibold text-gray-800 text-sm">{{ $template->name }}</h3>
                @if($template->category)
                    <span class="px-2 py-0.5 text-[10px] bg-brand-100 text-brand-700 rounded-full">{{ $template->category }}</span>
                @endif
            </div>
            @if($template->description)
                <p class="text-xs text-gray-500 mb-2">{{ $template->description }}</p>
            @endif
            <p class="text-xs text-gray-400">{{ $template->items_count }} medicine{{ $template->items_count != 1 ? 's' : '' }}</p>
            <div class="mt-3 flex gap-2">
                <form method="POST" action="{{ route('rx.settings.prescription-templates.destroy', $template) }}" class="inline"
                      onsubmit="return confirm('Delete template {{ addslashes($template->name) }}?')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-400 hover:text-red-600">Delete</button>
                </form>
            </div>
        </div>
        @empty
        <div class="col-span-3 py-12 text-center text-gray-400">
            <p class="mb-2">No templates yet.</p>
            <a href="{{ route('rx.settings.prescription-templates.create') }}" class="text-brand-600 hover:underline text-sm">Create first template</a>
        </div>
        @endforelse
    </div>
</div>
@endsection
