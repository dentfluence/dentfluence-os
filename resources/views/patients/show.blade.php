@extends('layouts.app')

@section('content')
<div class="min-h-screen" style="background:#f5eef9;">

    {{-- Topbar --}}
    <div class="flex items-center justify-between px-8 py-5 border-b border-purple-100 bg-white">
        <div>
            <p class="text-xs tracking-widest uppercase" style="color:#6a0f70;font-family:'DM Sans',sans-serif;">Patient Profile</p>
            <h1 class="text-2xl" style="font-family:'Cormorant Garamond',serif;color:#380740;font-weight:600;">{{ $patient->name }}</h1>
        </div>
        <div class="flex items-center gap-3">
            <a href="{{ route('patients.edit', $patient) }}"
               class="flex items-center gap-2 px-4 py-2 text-sm text-white transition-opacity hover:opacity-90"
               style="background:#6a0f70;font-family:'DM Sans',sans-serif;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Edit Patient
            </a>
            <a href="{{ route('patients.index') }}"
               class="flex items-center gap-2 px-4 py-2 text-sm border border-purple-200 bg-white hover:bg-purple-50 transition-colors"
               style="font-family:'DM Sans',sans-serif;color:#6a0f70;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                All Patients
            </a>
        </div>
    </div>

    @if(session('success'))
    <div class="mx-8 mt-6 px-5 py-3 border border-green-300 bg-green-50 text-sm" style="font-family:'DM Sans',sans-serif;color:#166534;">
        {{ session('success') }}
    </div>
    @endif

    <div class="px-8 py-8 max-w-5xl mx-auto space-y-8">

        {{-- PERSONAL + CLINICAL GRID --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

            {{-- Personal --}}
            <div class="md:col-span-2 bg-white border border-purple-100">
                <div class="px-6 py-4 border-b border-purple-100 flex items-center gap-3" style="background:#faf5fb;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M3 21a9 9 0 0 1 18 0"/></svg>
                    <span class="text-xs tracking-widest uppercase" style="font-family:'DM Sans',sans-serif;color:#6a0f70;font-weight:600;">Personal</span>
                </div>
                <div class="p-6 grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-xs tracking-widest uppercase mb-1" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;">Phone</p>
                        <p class="text-sm" style="font-family:'DM Sans',sans-serif;color:#1a0020;">{{ $patient->phone ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-widest uppercase mb-1" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;">Email</p>
                        <p class="text-sm" style="font-family:'DM Sans',sans-serif;color:#1a0020;">{{ $patient->email ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-widest uppercase mb-1" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;">Date of Birth</p>
                        <p class="text-sm" style="font-family:'DM Sans',sans-serif;color:#1a0020;">{{ $patient->dob ? $patient->dob->format('d M Y') : '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-widest uppercase mb-1" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;">Gender</p>
                        <p class="text-sm" style="font-family:'DM Sans',sans-serif;color:#1a0020;">{{ $patient->gender ?: '—' }}</p>
                    </div>
                    <div class="col-span-2">
                        <p class="text-xs tracking-widest uppercase mb-1" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;">Address</p>
                        <p class="text-sm" style="font-family:'DM Sans',sans-serif;color:#1a0020;">
                            {{ collect([$patient->address, $patient->city, $patient->state, $patient->pincode])->filter()->implode(', ') ?: '—' }}
                        </p>
                    </div>
                    <div>
                        <p class="text-xs tracking-widest uppercase mb-1" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;">Source</p>
                        <p class="text-sm" style="font-family:'DM Sans',sans-serif;color:#1a0020;">{{ $patient->source ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-widest uppercase mb-1" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;">Referred By</p>
                        <p class="text-sm" style="font-family:'DM Sans',sans-serif;color:#1a0020;">{{ $patient->referred_by ?: '—' }}</p>
                    </div>
                </div>
            </div>

            {{-- Clinical --}}
            <div class="bg-white border border-purple-100">
                <div class="px-6 py-4 border-b border-purple-100 flex items-center gap-3" style="background:#faf5fb;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    <span class="text-xs tracking-widest uppercase" style="font-family:'DM Sans',sans-serif;color:#6a0f70;font-weight:600;">Clinical</span>
                </div>
                <div class="p-6 space-y-5">
                    <div>
                        <p class="text-xs tracking-widest uppercase mb-1" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;">Chief Complaint</p>
                        <p class="text-sm leading-relaxed" style="font-family:'DM Sans',sans-serif;color:#1a0020;">{{ $patient->chief_complaint ?: '—' }}</p>
                    </div>
                    <div>
                        <p class="text-xs tracking-widest uppercase mb-1" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;">Medical Alert</p>
                        @if($patient->medical_alert)
                            <p class="text-sm leading-relaxed px-3 py-2 border border-red-200 bg-red-50" style="font-family:'DM Sans',sans-serif;color:#991b1b;">
                                ⚠ {{ $patient->medical_alert }}
                            </p>
                        @else
                            <p class="text-sm" style="font-family:'DM Sans',sans-serif;color:#1a0020;">—</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- NOTES SECTION --}}
        <div class="bg-white border border-purple-100">
            <div class="px-6 py-4 border-b border-purple-100 flex items-center justify-between" style="background:#faf5fb;">
                <div class="flex items-center gap-3">
                    <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                    <span class="text-xs tracking-widest uppercase" style="font-family:'DM Sans',sans-serif;color:#6a0f70;font-weight:600;">Notes</span>
                    <span class="text-xs px-2 py-0.5 border border-purple-200" style="font-family:'DM Sans',sans-serif;color:#6a0f70;background:#f5eef9;">{{ $patient->notes->count() }}</span>
                </div>
            </div>

            {{-- Add Note Form --}}
            <div class="px-6 pt-5 pb-4 border-b border-purple-50">
                <form action="{{ route('patient-notes.store', $patient) }}" method="POST" class="flex gap-3 items-start">
                    @csrf
                    <input type="hidden" name="note_type" value="general">
                    <textarea name="note" rows="2" required
                              placeholder="Add a note…"
                              class="flex-1 border border-purple-200 px-3 py-2 text-sm focus:outline-none focus:border-purple-500 bg-white resize-none"
                              style="font-family:'DM Sans',sans-serif;color:#1a0020;"></textarea>
                    <button type="submit"
                            class="px-5 py-2 text-sm text-white whitespace-nowrap hover:opacity-90 transition-opacity"
                            style="background:#6a0f70;font-family:'DM Sans',sans-serif;">
                        + Add Note
                    </button>
                </form>
                @error('note')
                    <p class="mt-1 text-xs" style="color:#991b1b;font-family:'DM Sans',sans-serif;">{{ $message }}</p>
                @enderror
            </div>

            {{-- Notes List --}}
            <div class="divide-y divide-purple-50">
                @forelse($patient->notes()->latest()->get() as $note)
                <div class="px-6 py-4 flex items-start justify-between gap-4">
                    <div class="flex-1">
                        <p class="text-sm leading-relaxed" style="font-family:'DM Sans',sans-serif;color:#1a0020;">{{ $note->note }}</p>
                        <p class="text-xs mt-1.5" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;">
                            {{ $note->note_type ?? 'general' }} &middot; {{ $note->created_at->format('d M Y, h:i A') }}
                            @if($note->createdBy)
                                &middot; {{ $note->createdBy->name }}
                            @endif
                        </p>
                    </div>
                    <form action="{{ route('patient-notes.destroy', [$patient, $note]) }}" method="POST"
                          onsubmit="return confirm('Delete this note?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="flex-shrink-0 hover:opacity-70 transition-opacity mt-0.5" title="Delete note">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9d6ea8" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                        </button>
                    </form>
                </div>
                @empty
                <div class="px-6 py-8 text-center">
                    <p class="text-sm" style="font-family:'DM Sans',sans-serif;color:#9d6ea8;">No notes yet.</p>
                </div>
                @endforelse
            </div>
        </div>

        {{-- ALERTS SECTION --}}
        @if($patient->alerts && $patient->alerts->count())
        <div class="bg-white border border-purple-100">
            <div class="px-6 py-4 border-b border-purple-100 flex items-center gap-3" style="background:#faf5fb;">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                <span class="text-xs tracking-widest uppercase" style="font-family:'DM Sans',sans-serif;color:#6a0f70;font-weight:600;">Alerts</span>
            </div>
            <div class="divide-y divide-purple-50">
                @foreach($patient->alerts->where('is_active', true) as $alert)
                <div class="px-6 py-4 flex items-center gap-4">
                    <span class="text-xs px-2 py-0.5 border
                        @if($alert->severity === 'high') border-red-300 bg-red-50 text-red-700
                        @elseif($alert->severity === 'medium') border-yellow-300 bg-yellow-50 text-yellow-700
                        @else border-blue-200 bg-blue-50 text-blue-700 @endif"
                        style="font-family:'DM Sans',sans-serif;">
                        {{ strtoupper($alert->severity) }}
                    </span>
                    <p class="text-sm flex-1" style="font-family:'DM Sans',sans-serif;color:#1a0020;">{{ $alert->alert }}</p>
                </div>
                @endforeach
            </div>
        </div>
        @endif

    </div>
</div>
@endsection
