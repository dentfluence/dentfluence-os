@extends('layouts.app')
@section('page-title', 'Training Sessions')

@section('content')
<div class="p-6 space-y-6" x-data="{ showCreate: false }">

    @include('hr.partials.subnav', ['active' => 'training'])

    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-display font-semibold text-gray-900">Training Sessions</h1>
            <p class="text-sm text-gray-500 mt-0.5">Schedule, track, and manage staff training</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('hr.periodic.index') }}"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                </svg>
                Periodic Tracker
            </a>
            <button @click="showCreate = true"
                class="inline-flex items-center gap-2 px-4 py-2 bg-purple-700 rounded-lg text-sm font-medium text-white hover:bg-purple-800 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                New Session
            </button>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">
        {{ session('success') }}
    </div>
    @endif

    {{-- ══════════════════════════════════════════
         NEW SESSION MODAL
    ══════════════════════════════════════════ --}}
    <template x-teleport="body">
    <div x-show="showCreate" x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/40"
         @keydown.escape.window="showCreate = false"
         @click.self="showCreate = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

            {{-- Modal header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white rounded-t-2xl z-10">
                <h2 class="font-semibold text-gray-900 text-lg">New Training Session</h2>
                <button @click="showCreate = false" class="text-gray-400 hover:text-gray-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            {{-- Modal body --}}
            <form action="{{ route('hr.training.store') }}" method="POST" class="p-6 space-y-5">
                @csrf

                {{-- Title --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title') }}" required autofocus
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400 outline-none"
                        placeholder="e.g. BLS Renewal, Infection Control Training">
                    @error('title')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                </div>

                {{-- Description --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none resize-none"
                        placeholder="Topics covered, objectives...">{{ old('description') }}</textarea>
                </div>

                {{-- Type + Venue --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                            <option value="one_time">One-Time</option>
                            <option value="periodic">Periodic / Recurring</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Venue</label>
                        <input type="text" name="venue" value="{{ old('venue') }}"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none"
                            placeholder="Conference Room / Online">
                    </div>
                </div>

                {{-- Date + Times --}}
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="scheduled_date" value="{{ old('scheduled_date') }}" required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                        <input type="time" name="start_time" value="{{ old('start_time') }}"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                        <input type="time" name="end_time" value="{{ old('end_time') }}"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                    </div>
                </div>

                {{-- Trainer --}}
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">External Trainer</label>
                        <input type="text" name="trainer_name" value="{{ old('trainer_name') }}"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none"
                            placeholder="Leave blank if internal">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Internal Trainer (Staff)</label>
                        <select name="trainer_user_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                            <option value="">— None —</option>
                            @foreach($staff as $s)
                            <option value="{{ $s->id }}">{{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                {{-- Notes --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none resize-none"
                        placeholder="Any internal notes or reminders...">{{ old('notes') }}</textarea>
                </div>

                {{-- Enroll Staff --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Enroll Staff</label>
                    <p class="text-xs text-gray-400 mb-2">You can add more staff later from the session page.</p>
                    <div class="grid grid-cols-2 gap-1.5 max-h-40 overflow-y-auto border border-gray-100 rounded-lg p-2">
                        @foreach($staff as $s)
                        <label class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50 cursor-pointer text-sm">
                            <input type="checkbox" name="staff_ids[]" value="{{ $s->id }}"
                                class="rounded border-gray-300 text-purple-600 focus:ring-purple-300">
                            <span class="text-gray-700">{{ $s->name }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                {{-- Footer --}}
                <div class="flex justify-end gap-3 pt-2 border-t border-gray-50">
                    <button type="button" @click="showCreate = false"
                        class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-5 py-2.5 text-sm font-medium text-white bg-purple-700 rounded-lg hover:bg-purple-800 transition">
                        Create Session
                    </button>
                </div>
            </form>
        </div>
    </div>
    </template>{{-- /x-teleport --}}

    {{-- Upcoming Sessions --}}
    @if($upcoming->count())
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="p-5 border-b border-gray-50">
            <h2 class="font-semibold text-gray-800">Upcoming Sessions</h2>
        </div>
        <div class="divide-y divide-gray-50">
            @foreach($upcoming as $s)
            <div class="flex items-center justify-between px-5 py-4">
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 rounded-xl bg-purple-100 flex items-center justify-center">
                        <svg class="w-5 h-5 text-purple-700" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M12 14l9-5-9-5-9 5 9 5z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="font-medium text-gray-900">{{ $s->title }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $s->scheduled_date->format('D, d M Y') }}
                            @if($s->start_time) · {{ \Carbon\Carbon::parse($s->start_time)->format('h:i A') }} @endif
                            @if($s->venue) · {{ $s->venue }} @endif
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500">{{ $s->enrolled_count }} enrolled</span>
                    <a href="{{ route('hr.training.show', $s) }}" class="text-sm text-purple-600 hover:underline font-medium">View →</a>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- All Sessions Table --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="p-5 border-b border-gray-50">
            <h2 class="font-semibold text-gray-800">All Sessions</h2>
        </div>

        @if($sessions->isEmpty())
        <div class="text-center py-16 text-gray-400">
            <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                <path d="M12 14l9-5-9-5-9 5 9 5z"/>
            </svg>
            <p class="text-sm">No training sessions yet.</p>
            <button @click="showCreate = true" class="text-purple-600 text-sm hover:underline mt-1 inline-block">Create the first one →</button>
        </div>
        @else
        <table class="w-full text-sm">
            <thead>
                <tr class="text-xs text-gray-500 uppercase tracking-wide border-b border-gray-50">
                    <th class="px-5 py-3 text-left font-medium">Title</th>
                    <th class="px-5 py-3 text-left font-medium">Date</th>
                    <th class="px-5 py-3 text-left font-medium">Type</th>
                    <th class="px-5 py-3 text-left font-medium">Status</th>
                    <th class="px-5 py-3 text-left font-medium">Enrolled</th>
                    <th class="px-5 py-3 text-left font-medium">Attended</th>
                    <th class="px-5 py-3 text-left font-medium"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                @foreach($sessions as $s)
                <tr class="hover:bg-gray-50/50 transition">
                    <td class="px-5 py-3 font-medium text-gray-900">{{ $s->title }}</td>
                    <td class="px-5 py-3 text-gray-600">{{ $s->scheduled_date->format('d M Y') }}</td>
                    <td class="px-5 py-3 text-gray-500 capitalize">{{ str_replace('_', '-', $s->type) }}</td>
                    <td class="px-5 py-3">
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium {{ $s->statusBadgeClass() }} capitalize">
                            {{ $s->status }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-gray-600">{{ $s->enrolled_count }}</td>
                    <td class="px-5 py-3 text-gray-600">{{ $s->attended_count }}</td>
                    <td class="px-5 py-3 text-right">
                        <a href="{{ route('hr.training.show', $s) }}"
                           class="text-purple-600 hover:underline text-xs font-medium">View →</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-5 py-4 border-t border-gray-50">{{ $sessions->links() }}</div>
        @endif
    </div>

</div>
@endsection
