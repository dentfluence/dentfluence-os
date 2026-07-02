@extends('layouts.app')
@section('page-title', 'CDSS Warning Rules')

@section('content')
<div class="p-6 max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-display font-semibold text-brand-800">CDSS Warning Rules</h1>
            <p class="text-xs text-gray-500 mt-0.5">Drug-condition rules that trigger clinical alerts during prescribing</p>
        </div>
        <a href="{{ route('rx.settings.index') }}" class="text-sm text-gray-500 hover:text-brand-600">← Settings</a>
    </div>

    @if(session('success'))
        <div class="mb-4 px-4 py-2 bg-green-50 text-green-700 rounded-lg border border-green-200 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Add Rule --}}
    <form method="POST" action="{{ route('rx.settings.warning-rules.store') }}"
          class="bg-white rounded-xl border border-gray-200 p-5 mb-6 shadow-sm">
        @csrf
        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">Add Warning Rule</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mb-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Condition Keyword</label>
                <input type="text" name="condition_keyword" required placeholder="gastric ulcer, diabetes, pregnancy…"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Molecule Group (or blank)</label>
                <input type="text" name="molecule_group" placeholder="ibuprofen, paracetamol…"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Drug Class (or blank)</label>
                <input type="text" name="drug_class" placeholder="NSAID, Corticosteroid…"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Severity</label>
                <select name="severity" class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
                    <option value="info">Info</option>
                    <option value="warning" selected>Warning</option>
                    <option value="critical">Critical</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Suggestion (optional)</label>
                <input type="text" name="suggestion" placeholder="Use Paracetamol instead"
                       class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400">
            </div>
            <div class="flex items-end pb-1">
                <label class="flex items-center gap-2 text-sm cursor-pointer">
                    <input type="checkbox" name="blockable" value="1" class="rounded text-red-500">
                    <span>Blockable (requires override reason)</span>
                </label>
            </div>
        </div>
        <div class="mb-3">
            <label class="block text-xs font-medium text-gray-600 mb-1">Alert Message <span class="text-red-500">*</span></label>
            <textarea name="alert_message" required rows="2" placeholder="Patient has Gastric Ulcer. NSAIDs may increase bleeding risk…"
                      class="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-brand-400"></textarea>
        </div>
        <button class="px-4 py-2 bg-brand-600 text-white text-sm rounded-lg hover:bg-brand-700 transition">Add Rule</button>
    </form>

    {{-- Rules List --}}
    <div class="bg-white rounded-xl border border-gray-200 overflow-hidden shadow-sm">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-gray-50 text-xs text-gray-500 uppercase font-semibold tracking-wide">
                <tr>
                    <th class="px-4 py-3 text-left">Condition</th>
                    <th class="px-4 py-3 text-left">Drug / Class</th>
                    <th class="px-4 py-3 text-left">Alert Message</th>
                    <th class="px-4 py-3 text-center">Severity</th>
                    <th class="px-4 py-3 text-center">Blockable</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @forelse($items as $rule)
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800">{{ $rule->condition_keyword }}</td>
                    <td class="px-4 py-3 text-gray-600 text-xs">
                        @if($rule->molecule_group) <span class="px-1.5 py-0.5 bg-blue-50 text-blue-600 rounded mr-1">{{ $rule->molecule_group }}</span> @endif
                        @if($rule->drug_class) <span class="px-1.5 py-0.5 bg-purple-50 text-purple-600 rounded">{{ $rule->drug_class }}</span> @endif
                    </td>
                    <td class="px-4 py-3 text-gray-600 text-xs max-w-xs">{{ Str::limit($rule->alert_message, 80) }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="px-2 py-0.5 text-xs font-semibold rounded-full
                            {{ $rule->severity === 'critical' ? 'bg-red-100 text-red-700' : ($rule->severity === 'warning' ? 'bg-yellow-100 text-yellow-700' : 'bg-blue-100 text-blue-700') }}">
                            {{ ucfirst($rule->severity) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center text-xs">{{ $rule->blockable ? 'Yes' : 'No' }}</td>
                    <td class="px-4 py-3 text-right">
                        <form method="POST" action="{{ route('rx.settings.warning-rules.destroy', $rule) }}" class="inline"
                              onsubmit="return confirm('Delete rule?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-500 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">No warning rules yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
