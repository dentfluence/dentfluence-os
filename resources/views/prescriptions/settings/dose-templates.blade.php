@extends('layouts.app')
@section('page-title', 'Dose Templates')

@section('content')
<div class="p-6 max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-display font-semibold text-brand-800">Dose Templates</h1>
            <p class="text-xs text-gray-500 mt-0.5">OD, BD, TDS, SOS — frequency presets</p>
        </div>
        <a href="{{ route('rx.settings.index') }}" class="text-sm text-gray-500 hover:text-brand-600">← Settings</a>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-2 bg-green-50 text-green-700 rounded-lg border border-green-200 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('rx.settings.dose-templates.store') }}"
          class="bg-white rounded-xl border border-gray-200 p-4 mb-6 shadow-sm">
        @csrf
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Name <span class="text-red-500">*</span></label>
                <input type="text" name="name" required placeholder="Twice Daily"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Abbreviation <span class="text-red-500">*</span></label>
                <input type="text" name="abbreviation" required placeholder="BD"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Morning</label>
                <input type="number" name="morning" value="0" min="0"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Afternoon</label>
                <input type="number" name="afternoon" value="0" min="0"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Night</label>
                <input type="number" name="night" value="0" min="0"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
            <div class="flex items-end pb-1">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="is_sos" value="1" class="rounded text-brand-600">
                    SOS
                </label>
            </div>
        </div>
        <button class="px-4 py-2 bg-brand-600 text-white text-sm rounded-lg hover:bg-brand-700 transition">Add Template</button>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase font-semibold tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-center">Abbr</th>
                    <th class="px-4 py-3 text-center">M</th>
                    <th class="px-4 py-3 text-center">A</th>
                    <th class="px-4 py-3 text-center">N</th>
                    <th class="px-4 py-3 text-center">SOS</th>
                    <th class="px-4 py-3 text-center">Active</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($items as $item)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $item->name }}</td>
                    <td class="px-4 py-3 text-center"><span class="font-mono text-brand-700 font-semibold">{{ $item->abbreviation }}</span></td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $item->morning }}</td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $item->afternoon }}</td>
                    <td class="px-4 py-3 text-center text-gray-600">{{ $item->night }}</td>
                    <td class="px-4 py-3 text-center">{{ $item->is_sos ? '✓' : '—' }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $item->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $item->is_active ? 'Yes' : 'No' }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('rx.settings.dose-templates.destroy', $item) }}" class="inline"
                              onsubmit="return confirm('Delete?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-500 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">No dose templates yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
