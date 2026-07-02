@extends('layouts.app')
@section('page-title', 'Drug Categories')

@section('content')
<div class="p-6 max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-display font-semibold text-brand-800">Drug Categories</h1>
            <p class="text-xs text-gray-500 mt-0.5">Analgesic, Antibiotic, Antifungal, Antiseptic, PPI…</p>
        </div>
        <a href="{{ route('rx.settings.index') }}" class="text-sm text-gray-500 hover:text-brand-600">← Settings</a>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-2 bg-green-50 text-green-700 rounded-lg border border-green-200 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Add form --}}
    <form method="POST" action="{{ route('rx.settings.categories.store') }}"
          class="bg-white rounded-xl border border-gray-200 p-4 mb-6 flex gap-3 items-end shadow-sm">
        @csrf
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-600 mb-1">Category Name</label>
            <input type="text" name="name" required placeholder="e.g. Antiviral"
                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        <div class="flex-1">
            <label class="block text-xs font-medium text-gray-600 mb-1">Description (optional)</label>
            <input type="text" name="description" placeholder="Short description"
                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        <button class="px-4 py-2 bg-brand-600 text-white text-sm rounded-lg hover:bg-brand-700 transition">Add</button>
    </form>

    {{-- List --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase font-semibold tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Description</th>
                    <th class="px-4 py-3 text-center">Active</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($items as $item)
                <tr class="{{ $item->trashed() ? 'opacity-50' : 'hover:bg-gray-50' }}">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $item->name }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $item->description ?? '—' }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $item->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $item->is_active ? 'Yes' : 'No' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        @unless($item->trashed())
                        <form method="POST" action="{{ route('rx.settings.categories.destroy', $item) }}" class="inline"
                              onsubmit="return confirm('Delete {{ addslashes($item->name) }}?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-500 hover:underline">Delete</button>
                        </form>
                        @endunless
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No categories yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
