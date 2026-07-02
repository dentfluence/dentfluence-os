@extends('layouts.app')
@section('page-title', 'Food Instructions')

@section('content')
<div class="p-6 max-w-3xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-display font-semibold text-brand-800">Food Instructions</h1>
            <p class="text-xs text-gray-500 mt-0.5">Multilingual food timing labels (English / Marathi / Hindi)</p>
        </div>
        <a href="{{ route('rx.settings.index') }}" class="text-sm text-gray-500 hover:text-brand-600">← Settings</a>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-2 bg-green-50 text-green-700 rounded-lg border border-green-200 text-sm">{{ session('success') }}</div>
    @endif

    <form method="POST" action="{{ route('rx.settings.food-instructions.store') }}"
          class="bg-white rounded-xl border border-gray-200 p-4 mb-6 shadow-sm">
        @csrf
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Code (unique)</label>
                <input type="text" name="code" required placeholder="AFTER_FOOD"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400 font-mono">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">English Label</label>
                <input type="text" name="label" required placeholder="After Food"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Marathi</label>
                <input type="text" name="label_mr" placeholder="जेवणानंतर"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Hindi</label>
                <input type="text" name="label_hi" placeholder="खाने के बाद"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
        </div>
        <button class="px-4 py-2 bg-brand-600 text-white text-sm rounded-lg hover:bg-brand-700 transition">Add Instruction</button>
    </form>

    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase font-semibold tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left">Code</th>
                    <th class="px-4 py-3 text-left">English</th>
                    <th class="px-4 py-3 text-left">Marathi</th>
                    <th class="px-4 py-3 text-left">Hindi</th>
                    <th class="px-4 py-3 text-center">Active</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($items as $item)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-mono text-xs text-gray-500">{{ $item->code }}</td>
                    <td class="px-4 py-3 text-gray-800">{{ $item->label }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $item->label_mr ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-600">{{ $item->label_hi ?? '—' }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 text-xs rounded-full {{ $item->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">{{ $item->is_active ? 'Yes' : 'No' }}</span>
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">No instructions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
