@extends('layouts.app')

@section('content')
<div class="min-h-screen" style="background:#f5eef9;">

    {{-- Topbar --}}
    <div class="flex items-center justify-between px-8 py-5 border-b border-purple-100 bg-white">
        <div>
            <p class="text-xs tracking-widest uppercase" style="color:#6a0f70;font-family:'Inter',sans-serif;">Health ID · ABHA</p>
            <h1 class="text-2xl" style="font-family:'Cormorant Garamond',serif;color:#380740;font-weight:600;">ABHA Details — {{ $patient->name }}</h1>
        </div>
        <a href="{{ route('patients.show', $patient) }}"
           class="flex items-center gap-2 px-4 py-2 text-sm border border-purple-200 bg-white hover:bg-purple-50 transition-colors"
           style="font-family:'Inter',sans-serif;color:#6a0f70;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Back to Profile
        </a>
    </div>

    <div class="px-8 py-8 max-w-2xl mx-auto space-y-6">

        {{-- Success flash --}}
        @if(session('success'))
        <div class="border border-green-300 bg-green-50 px-5 py-3 text-sm" style="font-family:'Inter',sans-serif;color:#166534;">
            {{ session('success') }}
        </div>
        @endif

        {{-- Errors --}}
        @if($errors->any())
        <div class="border border-red-300 bg-red-50 px-5 py-4 text-sm" style="font-family:'Inter',sans-serif;color:#991b1b;">
            <p class="font-semibold mb-1">Please fix the following:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
        @endif

        {{-- Info note --}}
        <div class="border border-purple-100 bg-white px-5 py-3 text-xs" style="font-family:'Inter',sans-serif;color:#6a0f70;line-height:1.6;">
            Record the patient's ABHA (Ayushman Bharat Health Account) details here. This is stored locally only —
            it is <strong>not</strong> verified against ABDM yet. Set the status by hand for now.
        </div>

        <form action="{{ route('patients.abha.update', $patient) }}" method="POST" class="bg-white border border-purple-100">
            @csrf
            @method('PATCH')

            <div class="px-6 py-4 border-b border-purple-100 flex items-center gap-3" style="background:#faf5fb;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h.01M7 12h5"/></svg>
                <span class="text-xs tracking-widest uppercase" style="font-family:'Inter',sans-serif;color:#6a0f70;font-weight:600;">Health ID</span>
            </div>

            <div class="p-6 space-y-5">

                {{-- Internal id (read-only) --}}
                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Internal Patient ID</label>
                    <div class="px-3 py-2 text-sm bg-gray-50 border border-gray-200" style="font-family:'Inter',sans-serif;color:#374151;">{{ $patient->patient_id ?? '—' }}</div>
                </div>

                {{-- ABHA number --}}
                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">ABHA Number <span class="text-gray-400">(14 digits)</span></label>
                    <input type="text" name="abha_number" value="{{ old('abha_number', $patient->abha_number) }}"
                           placeholder="12-3456-7890-1234"
                           class="w-full px-3 py-2 text-sm border border-purple-200 focus:outline-none focus:border-purple-400"
                           style="font-family:'Inter',sans-serif;">
                    <p class="text-xs text-gray-400 mt-1">Digits only; hyphens or spaces are fine.</p>
                </div>

                {{-- ABHA address --}}
                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">ABHA Address</label>
                    <input type="text" name="abha_address" value="{{ old('abha_address', $patient->abha_address) }}"
                           placeholder="name@abdm"
                           class="w-full px-3 py-2 text-sm border border-purple-200 focus:outline-none focus:border-purple-400"
                           style="font-family:'Inter',sans-serif;">
                </div>

                {{-- Status + language --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Verification Status</label>
                        <select name="abha_verification_status"
                                class="w-full px-3 py-2 text-sm border border-purple-200 focus:outline-none focus:border-purple-400 bg-white"
                                style="font-family:'Inter',sans-serif;">
                            @foreach($statuses as $s)
                                <option value="{{ $s }}" @selected(old('abha_verification_status', $patient->abha_verification_status ?? 'unlinked') === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Preferred Language</label>
                        <select name="preferred_language"
                                class="w-full px-3 py-2 text-sm border border-purple-200 focus:outline-none focus:border-purple-400 bg-white"
                                style="font-family:'Inter',sans-serif;">
                            <option value="">—</option>
                            @foreach($languages as $code => $label)
                                <option value="{{ $code }}" @selected(old('preferred_language', $patient->preferred_language) === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if($patient->abha_linked_at)
                <p class="text-xs text-gray-400">Linked on {{ $patient->abha_linked_at->format('d M Y, H:i') }}</p>
                @endif

            </div>

            <div class="px-6 py-4 border-t border-purple-100 flex justify-end" style="background:#faf5fb;">
                <button type="submit"
                        class="px-6 py-2 text-sm text-white transition-colors"
                        style="font-family:'Inter',sans-serif;background:#6a0f70;">
                    Save ABHA Details
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
