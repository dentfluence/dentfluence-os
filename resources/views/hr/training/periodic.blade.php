@extends('layouts.app')
@section('page-title', 'Periodic Training Tracker')

@section('content')
<div class="p-6 space-y-6" x-data="{ showAddReq: false, showAddRecord: false, selectedReq: null, selectedStaff: null }">

    @include('hr.partials.subnav', ['active' => 'training'])

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-display font-semibold text-gray-900">Periodic Training Tracker</h1>
            <p class="text-sm text-gray-500 mt-0.5">Track mandatory recurring certifications and compliance</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('hr.training.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                ← Sessions
            </a>
            <button @click="showAddReq = true"
                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-700 rounded-lg text-sm font-medium text-white hover:bg-purple-800 transition">
                + Add Requirement
            </button>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    {{-- Add Requirement Modal --}}
    <template x-teleport="body">
    <div x-show="showAddReq" x-cloak
         class="fixed inset-0 bg-black/40 z-[9999] flex items-center justify-center p-4"
         @keydown.escape.window="showAddReq = false"
         @click.self="showAddReq = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
            <h2 class="font-semibold text-gray-900">New Training Requirement</h2>
            <form action="{{ route('hr.periodic.requirements.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Requirement Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none"
                        placeholder="e.g. BLS Renewal, Fire Safety">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none resize-none"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Applies To</label>
                        <input type="text" name="applies_to"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none"
                            placeholder="All / Clinical / Specific role">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Frequency (months) <span class="text-red-500">*</span></label>
                        <input type="number" name="frequency_months" min="1" required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none"
                            placeholder="12 = yearly">
                    </div>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showAddReq = false"
                        class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 text-sm text-white bg-purple-700 rounded-lg hover:bg-purple-800">Add</button>
                </div>
            </form>
        </div>
    </div>
    </template>{{-- /x-teleport req --}}

    {{-- Add Record Modal --}}
    <template x-teleport="body">
    <div x-show="showAddRecord" x-cloak
         class="fixed inset-0 bg-black/40 z-[9999] flex items-center justify-center p-4"
         @keydown.escape.window="showAddRecord = false"
         @click.self="showAddRecord = false">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md p-6 space-y-4">
            <h2 class="font-semibold text-gray-900">Log Completed Training</h2>
            <form action="{{ route('hr.periodic.records.store') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Requirement</label>
                    <select name="requirement_id" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                        @foreach($requirements as $req)
                        <option value="{{ $req->id }}" :selected="selectedReq == {{ $req->id }}">{{ $req->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Staff Member</label>
                    <select name="user_id" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                        @foreach($allStaff as $s)
                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date Completed</label>
                    <input type="date" name="completed_on" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Link to Training Session (optional)</label>
                    <select name="training_session_id"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                        <option value="">— None —</option>
                        @foreach($sessions as $s)
                        <option value="{{ $s->id }}">{{ $s->title }} ({{ $s->scheduled_date->format('d M Y') }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none resize-none"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" @click="showAddRecord = false"
                        class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 text-sm text-white bg-purple-700 rounded-lg hover:bg-purple-800">Save Record</button>
                </div>
            </form>
        </div>
    </div>
    </template>{{-- /x-teleport record --}}

    {{-- Requirements with compliance grid --}}
    @forelse($requirements as $req)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="flex items-center justify-between p-5 border-b border-gray-50">
            <div>
                <h2 class="font-semibold text-gray-800">{{ $req->name }}</h2>
                <p class="text-xs text-gray-500 mt-0.5">
                    {{ $req->frequency_label }} · Applies to: {{ $req->applies_to ?? 'All staff' }}
                    @if($req->description) · {{ $req->description }} @endif
                </p>
            </div>
            <div class="flex gap-2">
                <button @click="showAddRecord = true; selectedReq = {{ $req->id }}"
                    class="text-xs text-purple-600 hover:underline font-medium px-3 py-1.5 border border-purple-200 rounded-lg hover:bg-purple-50 transition">
                    + Log Completion
                </button>
                <form action="{{ route('hr.periodic.requirements.destroy', $req) }}" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit" onclick="return confirm('Archive this requirement?')"
                        class="text-xs text-gray-400 hover:text-red-500 px-2 py-1.5">Archive</button>
                </form>
            </div>
        </div>

        {{-- Staff compliance rows --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-xs text-gray-500 border-b border-gray-50">
                        <th class="px-5 py-2.5 text-left font-medium">Staff</th>
                        <th class="px-5 py-2.5 text-left font-medium">Last Completed</th>
                        <th class="px-5 py-2.5 text-left font-medium">Next Due</th>
                        <th class="px-5 py-2.5 text-left font-medium">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($allStaff as $s)
                    @php $record = $compliance[$req->id][$s->id] ?? null; @endphp
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700">
                                    {{ strtoupper(substr($s->name, 0, 1)) }}
                                </div>
                                {{ $s->name }}
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-600">
                            {{ $record ? $record->completed_on->format('d M Y') : '—' }}
                        </td>
                        <td class="px-5 py-3 text-gray-600">
                            {{ $record ? $record->next_due_on->format('d M Y') : '—' }}
                        </td>
                        <td class="px-5 py-3">
                            @if(!$record)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">No Record</span>
                            @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $record->complianceBadgeClass() }}">
                                {{ ucfirst(str_replace('_', ' ', $record->compliance_status)) }}
                            </span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @empty
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm text-center py-16 text-gray-400">
        <p class="text-sm">No periodic training requirements defined yet.</p>
        <button @click="showAddReq = true" class="text-purple-600 text-sm hover:underline mt-1 inline-block">
            Add the first requirement →
        </button>
    </div>
    @endforelse

</div>
@endsection
