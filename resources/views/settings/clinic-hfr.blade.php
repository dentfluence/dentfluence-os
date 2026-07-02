@extends('layouts.app')
@section('page-title', 'Health Facility (HFR)')

@section('content')
<div class="min-h-screen" style="background:#f5eef9;">

    {{-- Topbar --}}
    <div class="flex items-center justify-between px-8 py-5 border-b border-purple-100 bg-white">
        <div>
            <p class="text-xs tracking-widest uppercase" style="color:#6a0f70;font-family:'Inter',sans-serif;">Clinic · Health Facility</p>
            <h1 class="text-2xl" style="font-family:'Cormorant Garamond',serif;color:#380740;font-weight:600;">HFR — {{ $branch->name }}</h1>
        </div>
        <a href="{{ route('settings.index') }}"
           class="flex items-center gap-2 px-4 py-2 text-sm border border-purple-200 bg-white hover:bg-purple-50 transition-colors"
           style="font-family:'Inter',sans-serif;color:#6a0f70;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Back to Settings
        </a>
    </div>

    <div class="px-8 py-8 max-w-2xl mx-auto space-y-6">

        @if(session('success'))
        <div class="border border-green-300 bg-green-50 px-5 py-3 text-sm" style="font-family:'Inter',sans-serif;color:#166534;">{{ session('success') }}</div>
        @endif

        @if($errors->any())
        <div class="border border-red-300 bg-red-50 px-5 py-4 text-sm" style="font-family:'Inter',sans-serif;color:#991b1b;">
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
        @endif

        <div class="border border-purple-100 bg-white px-5 py-3 text-xs" style="font-family:'Inter',sans-serif;color:#6a0f70;line-height:1.6;">
            Record your clinic's HFR (Health Facility Registry) id and facility details. Stored locally only —
            <strong>not</strong> verified against ABDM yet. Set the status by hand.
        </div>

        <form action="{{ route('settings.clinic.hfr.update') }}" method="POST" class="bg-white border border-purple-100">
            @csrf
            @method('PATCH')

            <div class="px-6 py-4 border-b border-purple-100 flex items-center gap-3" style="background:#faf5fb;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                <span class="text-xs tracking-widest uppercase" style="font-family:'Inter',sans-serif;color:#6a0f70;font-weight:600;">Health Facility Registry</span>
            </div>

            <div class="p-6 space-y-5">

                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">HFR ID</label>
                    <input type="text" name="hfr_id" value="{{ old('hfr_id', $branch->hfr_id) }}"
                           placeholder="e.g. IN-1234567890"
                           class="w-full px-3 py-2 text-sm border border-purple-200 focus:outline-none focus:border-purple-400"
                           style="font-family:'Inter',sans-serif;">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Facility Type</label>
                        <select name="facility_type"
                                class="w-full px-3 py-2 text-sm border border-purple-200 focus:outline-none focus:border-purple-400 bg-white"
                                style="font-family:'Inter',sans-serif;">
                            <option value="">—</option>
                            @foreach($facilityTypes as $t)
                                <option value="{{ $t }}" @selected(old('facility_type', $branch->facility_type) === $t)>{{ ucwords(str_replace('_',' ',$t)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Verification Status</label>
                        <select name="facility_verification_status"
                                class="w-full px-3 py-2 text-sm border border-purple-200 focus:outline-none focus:border-purple-400 bg-white"
                                style="font-family:'Inter',sans-serif;">
                            @foreach($statuses as $s)
                                <option value="{{ $s }}" @selected(old('facility_verification_status', $branch->facility_verification_status ?? 'unlinked') === $s)>{{ ucfirst($s) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    <div>
                        <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Latitude</label>
                        <input type="text" name="geo_lat" value="{{ old('geo_lat', $branch->geo_lat) }}"
                               placeholder="19.0760"
                               class="w-full px-3 py-2 text-sm border border-purple-200 focus:outline-none focus:border-purple-400"
                               style="font-family:'Inter',sans-serif;">
                    </div>
                    <div>
                        <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Longitude</label>
                        <input type="text" name="geo_lng" value="{{ old('geo_lng', $branch->geo_lng) }}"
                               placeholder="72.8777"
                               class="w-full px-3 py-2 text-sm border border-purple-200 focus:outline-none focus:border-purple-400"
                               style="font-family:'Inter',sans-serif;">
                    </div>
                </div>

            </div>

            <div class="px-6 py-4 border-t border-purple-100 flex justify-end" style="background:#faf5fb;">
                <button type="submit" class="px-6 py-2 text-sm text-white transition-colors" style="font-family:'Inter',sans-serif;background:#6a0f70;">Save HFR Details</button>
            </div>
        </form>
    </div>
</div>
@endsection
