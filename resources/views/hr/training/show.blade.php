@extends('layouts.app')
@section('page-title', $session->title)

@section('content')
<div class="p-6 space-y-6"
     x-data="{
         showEdit:   false,
         showEnroll: false,
     }">

    @include('hr.partials.subnav', ['active' => 'training'])

    {{-- Header --}}
    <div class="flex items-start justify-between">
        <div class="flex items-center gap-3">
            <a href="{{ route('hr.training.index') }}" class="text-gray-400 hover:text-gray-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M15 19l-7-7 7-7"/></svg>
            </a>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-2xl font-display font-semibold text-gray-900">{{ $session->title }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $session->statusBadgeClass() }} capitalize">
                        {{ $session->status }}
                    </span>
                </div>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $session->scheduled_date->format('l, d M Y') }}
                    @if($session->start_time) · {{ \Carbon\Carbon::parse($session->start_time)->format('h:i A') }} @endif
                    @if($session->end_time) – {{ \Carbon\Carbon::parse($session->end_time)->format('h:i A') }} @endif
                    @if($session->venue) · {{ $session->venue }} @endif
                </p>
            </div>
        </div>
        <div class="flex gap-2">
            @if($session->status === 'scheduled')
            <form action="{{ route('hr.training.complete', $session) }}" method="POST">
                @csrf
                <button type="submit"
                    onclick="return confirm('Mark this session as completed? All present staff will be marked as completed.')"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition">
                    ✓ Mark Complete
                </button>
            </form>
            @endif
            <button @click="showEdit = true"
                class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-50 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Edit Session
            </button>
        </div>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif

    {{-- ══════════════════════════════════════════
         EDIT SESSION MODAL
    ══════════════════════════════════════════ --}}
    <template x-teleport="body">
    <div x-show="showEdit" x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/40"
         @keydown.escape.window="showEdit = false"
         @click.self="showEdit = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white rounded-t-2xl z-10">
                <h2 class="font-semibold text-gray-900 text-lg">Edit Session</h2>
                <button @click="showEdit = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            <form action="{{ route('hr.training.update', $session) }}" method="POST" class="p-6 space-y-4">
                @csrf @method('PUT')

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title <span class="text-red-500">*</span></label>
                    <input type="text" name="title" value="{{ old('title', $session->title) }}" required
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea name="description" rows="2"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none resize-none">{{ old('description', $session->description) }}</textarea>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                        <select name="type" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                            <option value="one_time" {{ old('type', $session->type) == 'one_time' ? 'selected' : '' }}>One-Time</option>
                            <option value="periodic" {{ old('type', $session->type) == 'periodic' ? 'selected' : '' }}>Periodic</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select name="status" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                            <option value="scheduled" {{ old('status', $session->status) == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                            <option value="completed" {{ old('status', $session->status) == 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="cancelled" {{ old('status', $session->status) == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Venue</label>
                        <input type="text" name="venue" value="{{ old('venue', $session->venue) }}"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date <span class="text-red-500">*</span></label>
                        <input type="date" name="scheduled_date" value="{{ old('scheduled_date', $session->scheduled_date->format('Y-m-d')) }}" required
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Time</label>
                        <input type="time" name="start_time" value="{{ old('start_time', $session->start_time) }}"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Time</label>
                        <input type="time" name="end_time" value="{{ old('end_time', $session->end_time) }}"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">External Trainer</label>
                    <input type="text" name="trainer_name" value="{{ old('trainer_name', $session->trainer_name) }}"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Notes</label>
                    <textarea name="notes" rows="2"
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-purple-300 outline-none resize-none">{{ old('notes', $session->notes) }}</textarea>
                </div>

                <div class="flex justify-end gap-3 pt-2 border-t border-gray-50">
                    <button type="button" @click="showEdit = false"
                        class="px-4 py-2.5 text-sm font-medium text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition">
                        Cancel
                    </button>
                    <button type="submit"
                        class="px-4 py-2.5 text-sm font-medium text-white bg-purple-700 rounded-lg hover:bg-purple-800 transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
    </template>{{-- /x-teleport edit --}}

    {{-- ══════════════════════════════════════════
         ENROLL STAFF MODAL
    ══════════════════════════════════════════ --}}
    <template x-teleport="body">
    <div x-show="showEnroll" x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center p-4 bg-black/40"
         @keydown.escape.window="showEnroll = false"
         @click.self="showEnroll = false">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[80vh] overflow-y-auto">

            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 sticky top-0 bg-white rounded-t-2xl z-10">
                <h2 class="font-semibold text-gray-900">Enroll Staff</h2>
                <button @click="showEnroll = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>

            @if($availableStaff->isEmpty())
            <div class="p-8 text-center text-gray-400 text-sm">All active staff are already enrolled.</div>
            @else
            <form action="{{ route('hr.training.enroll', $session) }}" method="POST" class="p-5 space-y-3">
                @csrf
                <div class="space-y-1">
                    @foreach($availableStaff as $s)
                    <label class="flex items-center gap-3 p-2.5 rounded-lg hover:bg-gray-50 cursor-pointer">
                        <input type="checkbox" name="user_ids[]" value="{{ $s->id }}"
                            class="rounded border-gray-300 text-purple-600 focus:ring-purple-300">
                        <div class="w-7 h-7 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 flex-shrink-0">
                            {{ strtoupper(substr($s->name, 0, 1)) }}
                        </div>
                        <span class="text-sm text-gray-700">{{ $s->name }}</span>
                    </label>
                    @endforeach
                </div>
                <div class="flex justify-end gap-3 pt-2 border-t border-gray-50">
                    <button type="button" @click="showEnroll = false"
                        class="px-4 py-2 text-sm text-gray-600 border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="submit"
                        class="px-4 py-2 text-sm text-white bg-purple-700 rounded-lg hover:bg-purple-800">Enroll Selected</button>
                </div>
            </form>
            @endif
        </div>
    </div>
    </template>{{-- /x-teleport enroll --}}

    {{-- ══════════════════════════════════════════
         PAGE BODY
    ══════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Left: Info + Attendance --}}
        <div class="lg:col-span-2 space-y-6">

            {{-- Details Card --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h2 class="font-semibold text-gray-800 mb-4">Session Info</h2>
                <dl class="space-y-3 text-sm">
                    @if($session->description)
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Description</dt>
                        <dd class="text-gray-700 mt-1">{{ $session->description }}</dd>
                    </div>
                    @endif
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Type</dt>
                            <dd class="text-gray-700 mt-1 capitalize">{{ str_replace('_', '-', $session->type) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Trainer</dt>
                            <dd class="text-gray-700 mt-1">{{ $session->trainer_name ?? ($session->internalTrainer?->name ?? '—') }}</dd>
                        </div>
                    </div>
                    @if($session->notes)
                    <div>
                        <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Notes</dt>
                        <dd class="text-gray-600 mt-1 text-xs">{{ $session->notes }}</dd>
                    </div>
                    @endif
                    <div class="text-xs text-gray-400 pt-1">
                        Created by {{ $session->createdBy?->name }} on {{ $session->created_at->format('d M Y') }}
                    </div>
                </dl>
            </div>

            {{-- Attendance --}}
            @if($session->enrollments->count())
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                <div class="flex items-center justify-between p-5 border-b border-gray-50">
                    <h2 class="font-semibold text-gray-800">Attendance</h2>
                    <span class="text-xs text-gray-500">{{ $session->attended_count }}/{{ $session->enrolled_count }} present</span>
                </div>
                <form action="{{ route('hr.training.attendance', $session) }}" method="POST">
                    @csrf
                    <div class="divide-y divide-gray-50">
                        @foreach($session->enrollments as $enrollment)
                        <div class="flex items-center justify-between px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700">
                                    {{ strtoupper(substr($enrollment->user->name ?? '?', 0, 1)) }}
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-800">{{ $enrollment->user->name ?? 'Unknown' }}</p>
                                    @if($enrollment->completed)
                                    <p class="text-xs text-green-600">✓ Completed {{ $enrollment->completed_at?->format('d M Y') }}</p>
                                    @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <select name="attendance[{{ $enrollment->id }}]"
                                    class="border border-gray-200 rounded-lg text-xs px-2 py-1.5 focus:ring-2 focus:ring-purple-300 outline-none">
                                    <option value="pending" {{ $enrollment->attendance === 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="present" {{ $enrollment->attendance === 'present' ? 'selected' : '' }}>Present</option>
                                    <option value="absent"  {{ $enrollment->attendance === 'absent'  ? 'selected' : '' }}>Absent</option>
                                </select>
                                <form action="{{ route('hr.training.unenroll', [$session, $enrollment->user_id]) }}" method="POST">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="text-gray-300 hover:text-red-400 transition" title="Remove">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                                        </svg>
                                    </button>
                                </form>
                            </div>
                        </div>
                        @endforeach
                    </div>
                    <div class="p-4 border-t border-gray-50">
                        <button type="submit"
                            class="px-4 py-2 bg-purple-700 text-white text-sm font-medium rounded-lg hover:bg-purple-800 transition">
                            Save Attendance
                        </button>
                    </div>
                </form>
            </div>
            @endif

        </div>

        {{-- Right sidebar --}}
        <div class="space-y-4">

            {{-- Stats --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <h2 class="font-semibold text-gray-800 mb-3 text-sm">Quick Stats</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Enrolled</dt>
                        <dd class="font-semibold text-gray-900">{{ $session->enrolled_count }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Present</dt>
                        <dd class="font-semibold text-green-600">{{ $session->attended_count }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Absent</dt>
                        <dd class="font-semibold text-red-500">{{ $session->enrollments->where('attendance','absent')->count() }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Completed</dt>
                        <dd class="font-semibold text-purple-700">{{ $session->enrollments->where('completed',true)->count() }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Enroll more button --}}
            <button @click="showEnroll = true"
                class="w-full py-2.5 px-4 text-sm font-medium text-purple-700 bg-purple-50 border border-purple-200 rounded-xl hover:bg-purple-100 transition flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add More Staff
            </button>

            {{-- Danger zone --}}
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">Danger Zone</p>
                <form action="{{ route('hr.training.destroy', $session) }}" method="POST">
                    @csrf @method('DELETE')
                    <button type="submit"
                        onclick="return confirm('Delete this training session? This cannot be undone.')"
                        class="w-full py-2 text-sm text-red-600 border border-red-200 rounded-lg hover:bg-red-50 transition">
                        Delete Session
                    </button>
                </form>
            </div>

        </div>
    </div>

</div>
@endsection
