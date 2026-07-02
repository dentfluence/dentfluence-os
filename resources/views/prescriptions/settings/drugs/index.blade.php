@extends('layouts.app')
@section('page-title', 'Drug Master')

@section('content')
<div class="p-6 max-w-7xl mx-auto">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-display font-semibold text-brand-800">Drug Master</h1>
            <p class="text-sm text-gray-500 mt-0.5">Manage all drugs, safety profiles, and defaults</p>
        </div>
        <a href="{{ route('rx.drugs.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2 bg-brand-600 text-white text-sm font-medium rounded-lg hover:bg-brand-700 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            Add Drug
        </a>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 text-green-700 rounded-lg border border-green-200 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Filters --}}
    <form method="GET" class="flex flex-wrap gap-3 mb-5">
        <input type="text" name="search" value="{{ request('search') }}"
               placeholder="Brand name, generic, composition…"
               class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400 w-64">
        <select name="category_id" class="px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
            <option value="">All Categories</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->id }}" @selected(request('category_id') == $cat->id)>{{ $cat->name }}</option>
            @endforeach
        </select>
        <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
            <input type="checkbox" name="active_only" value="1" @checked(request('active_only')) class="rounded text-brand-600">
            Active only
        </label>
        <button class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm rounded-lg transition">Search</button>
        <a href="{{ route('rx.drugs.index') }}" class="px-4 py-2 text-gray-500 text-sm hover:text-gray-700">Clear</a>
    </form>

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-xs font-semibold text-gray-500 uppercase tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left">Brand Name</th>
                    <th class="px-4 py-3 text-left">Generic</th>
                    <th class="px-4 py-3 text-left">Strength / Form</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-left">Antibiotic Class</th>
                    <th class="px-4 py-3 text-left">Pregnancy</th>
                    <th class="px-4 py-3 text-center">Controlled</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($drugs as $drug)
                <tr class="{{ $drug->trashed() ? 'opacity-50 bg-gray-50' : 'hover:bg-brand-50/30' }} transition">
                    <td class="px-4 py-3 font-medium text-gray-900">
                        {{ $drug->brand_name }}
                        @if($drug->is_controlled)
                            <span class="ml-1 px-1.5 py-0.5 text-[10px] font-semibold bg-red-100 text-red-700 rounded">CD</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $drug->generic?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">
                        {{ $drug->strength ?? '' }}
                        @if($drug->dosage_form) <span class="text-gray-400">· {{ $drug->dosage_form }}</span> @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600">{{ $drug->category?->name ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">
                        @if($drug->antibiotic_class)
                            <span class="px-2 py-0.5 text-xs bg-amber-100 text-amber-700 rounded-full">{{ $drug->antibiotic_class }}</span>
                        @else —
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if($drug->pregnancy_category)
                            <span class="px-2 py-0.5 text-xs font-semibold rounded-full
                                {{ in_array($drug->pregnancy_category, ['D','X']) ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                                Cat {{ $drug->pregnancy_category }}
                            </span>
                        @else <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        {{ $drug->is_controlled ? '' : '—' }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($drug->trashed())
                            <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-500 rounded-full">Deleted</span>
                        @elseif($drug->is_active)
                            <span class="px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded-full">Active</span>
                        @else
                            <span class="px-2 py-0.5 text-xs bg-yellow-100 text-yellow-700 rounded-full">Inactive</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                        @if($drug->trashed())
                            <form method="POST" action="{{ route('rx.drugs.restore', $drug->id) }}" class="inline">
                                @csrf
                                <button class="text-xs text-green-600 hover:underline">Restore</button>
                            </form>
                        @else
                            <a href="{{ route('rx.drugs.edit', $drug) }}" class="text-xs text-brand-600 hover:underline">Edit</a>
                            <form method="POST" action="{{ route('rx.drugs.destroy', $drug) }}" class="inline"
                                  onsubmit="return confirm('Deactivate {{ addslashes($drug->brand_name) }}?')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-500 hover:underline">Delete</button>
                            </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="px-4 py-10 text-center text-gray-400">No drugs found. <a href="{{ route('rx.drugs.create') }}" class="text-brand-600 hover:underline">Add the first drug</a>.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    <div class="mt-4">{{ $drugs->links() }}</div>

    {{-- Back to settings --}}
    <div class="mt-6">
        <a href="{{ route('rx.settings.index') }}" class="text-sm text-gray-500 hover:text-brand-600">← Back to Prescription Settings</a>
    </div>

</div>
@endsection
