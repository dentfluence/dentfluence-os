@extends('layouts.app')
@section('page-title', 'New Training Session')

@section('content')
<div class="p-6 max-w-3xl mx-auto space-y-6">

    @include('hr.partials.subnav', ['active' => 'training'])

    <div class="flex items-center gap-3">
        <a href="{{ route('hr.training.index') }}" class="text-gray-400 hover:text-gray-600">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <h1 class="text-2xl font-display font-semibold text-gray-900">New Training Session</h1>
    </div>

    <form action="{{ route('hr.training.store') }}" method="POST" class="space-y-6">
        @csrf

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-5">
            <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Session Details</h2>

            {{-- Title --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400 outline-none"
                    placeholder="e.g. BLS Renewal, Infection Control Training">
                @error('title')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>

            {{-- Description --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                <textarea name="description" rows="3"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 focus:border-purple-400 outline-none resize-none"
                    placeholder="Topics covered, objectives, materials needed...">{{ old('description') }}</textarea>
            </div>

            {{-- Type + Venue (row) --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                    <select name="type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                        <option value="one_time" {{ old('type') == 'one_time' ? 'selected' : '' }}>One-Time</option>
                        <option value="periodic" {{ old('type') == 'periodic' ? 'selected' : '' }}>Periodic / Recurring</option>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">External Trainer Name</label>
                    <input type="text" name="trainer_name" value="{{ old('trainer_name') }}"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none"
                        placeholder="Leave blank if internal staff">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Internal Trainer (Staff)</label>
                    <select name="trainer_user_id" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                        <option value="">— None —</option>
                        @foreach($staff as $s)
                        <option value="{{ $s->id }}" {{ old('trainer_user_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Notes --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Internal Notes</label>
                <textarea name="notes" rows="2"
                    class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none resize-none"
                    placeholder="Any internal notes or reminders...">{{ old('notes') }}</textarea>
            </div>
        </div>

        {{-- Enroll Staff --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
            <h2 class="font-semibold text-gray-700 text-sm uppercase tracking-wide">Enroll Staff</h2>
            <p class="text-xs text-gray-500">Select staff to enroll now. You can add more later from the session page.</p>

            <div class="grid grid-cols-2 gap-2 max-h-64 overflow-y-auto pr-1">
                @foreach($staff as $s)
                <label class="flex items-center gap-2 p-2 rounded-lg border border-gray-100 hover:bg-gray-50 cursor-pointer text-sm">
                    <input type="checkbox" name="staff_ids[]" value="{{ $s->id }}"
                        class="rounded border-gray-300 text-purple-600 focus:ring-purple-300"
                        {{ in_array($s->id, old('staff_ids', [])) ? 'checked' : '' }}>
                    <div class="w-6 h-6 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 flex-shrink-0">
                        {{ strtoupper(substr($s->name, 0, 1)) }}
                    </div>
                    <span class="text-gray-700">{{ $s->name }}</span>
                </label>
                @endforeach
            </div>
        </div>

        {{-- Submit --}}
        <div class="flex justify-end gap-3">
            <a href="{{ route('hr.training.index') }}"
               class="px-5 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                Cancel
            </a>
            <button type="submit" dusk="training-save"
                class="px-5 py-2.5 text-sm font-medium text-white bg-purple-700 rounded-lg hover:bg-purple-800 transition">
                Create Session
            </button>
        </div>
    </form>

</div>
@endsection
