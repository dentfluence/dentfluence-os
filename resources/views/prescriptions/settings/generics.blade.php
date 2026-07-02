@extends('layouts.app')
@section('page-title', 'Generic Master')

@section('content')
<div class="p-6 max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-display font-semibold text-brand-800">Generic Master</h1>
            <p class="text-xs text-gray-500 mt-0.5">Generic drug names and drug classes</p>
        </div>
        <a href="{{ route('rx.settings.index') }}" class="text-sm text-gray-500 hover:text-brand-600">← Settings</a>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-2 bg-green-50 text-green-700 rounded-lg border border-green-200 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('rx.settings.generics.store') }}"
          class="bg-white rounded-xl border border-gray-200 p-4 mb-6 grid grid-cols-3 gap-3 items-end shadow-sm">
        @csrf
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Generic Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" required placeholder="Paracetamol"
                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Drug Class</label>
            <input type="text" name="drug_class" placeholder="NSAID, Penicillin…"
                   class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
        </div>
        <div>
            <button class="w-full px-4 py-2 bg-brand-600 text-white text-sm rounded-lg hover:bg-brand-700 transition">Add Generic</button>
        </div>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase font-semibold tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left">Generic Name</th>
                    <th class="px-4 py-3 text-left">Drug Class</th>
                    <th class="px-4 py-3 text-center">Active</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($items as $item)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $item->name }}</td>
                    <td class="px-4 py-3 text-gray-500">
                        @if($item->drug_class)
                            <span class="px-2 py-0.5 text-xs bg-blue-50 text-blue-600 rounded-full">{{ $item->drug_class }}</span>
                        @else —
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $item->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $item->is_active ? 'Yes' : 'No' }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('rx.settings.generics.destroy', $item) }}" class="inline"
                              onsubmit="return confirm('Delete?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-500 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-gray-400">No generics yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
