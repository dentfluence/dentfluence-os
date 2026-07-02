@extends('layouts.app')

@section('content')
<div class="min-h-screen" style="background:#f5eef9;">

    {{-- Topbar --}}
    <div class="flex items-center justify-between px-8 py-5 border-b border-purple-100 bg-white">
        <div>
            <p class="text-xs tracking-widest uppercase" style="color:#6a0f70;font-family:'Inter',sans-serif;">Patient Record</p>
            <h1 class="text-2xl" style="font-family:'Cormorant Garamond',serif;color:#380740;font-weight:600;">Edit — {{ $patient->name }}</h1>
        </div>
        <a href="{{ route('patients.show', $patient) }}"
           class="flex items-center gap-2 px-4 py-2 text-sm border border-purple-200 bg-white hover:bg-purple-50 transition-colors"
           style="font-family:'Inter',sans-serif;color:#6a0f70;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
            Back to Profile
        </a>
    </div>

    <form action="{{ route('patients.update', $patient) }}" method="POST" class="px-8 py-8 max-w-5xl mx-auto space-y-8">
        @csrf
        @method('PUT')

        @if($errors->any())
        <div class="border border-red-300 bg-red-50 px-5 py-4 text-sm" style="font-family:'Inter',sans-serif;color:#991b1b;">
            <p class="font-semibold mb-1">Please fix the following:</p>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- SECTION 1: Personal Information --}}
        <div class="bg-white border border-purple-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-purple-100 flex items-center gap-3" style="background:#faf5fb;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M3 21a9 9 0 0 1 18 0"/></svg>
                <span class="text-xs tracking-widest uppercase" style="font-family:'Inter',sans-serif;color:#6a0f70;font-weight:600;">Personal Information</span>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-5">

                <div class="md:col-span-2">
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $patient->name) }}" required
                           class="w-full border border-purple-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-500 bg-white"
                           style="font-family:'Inter',sans-serif;color:#1a0020;">
                </div>

                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Phone <span class="text-red-500">*</span></label>
                    <input type="text" name="phone" value="{{ old('phone', $patient->phone) }}" required
                           class="w-full border border-purple-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-500 bg-white"
                           style="font-family:'Inter',sans-serif;color:#1a0020;">
                </div>

                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Date of Birth</label>
                    <input type="date" name="dob" value="{{ old('dob', $patient->dob ? $patient->dob->format('Y-m-d') : '') }}"
                           class="w-full border border-purple-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-500 bg-white"
                           style="font-family:'Inter',sans-serif;color:#1a0020;">
                </div>

                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Gender</label>
                    <select name="gender" class="w-full border border-purple-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-500 bg-white" style="font-family:'Inter',sans-serif;color:#1a0020;">
                        <option value="">— Select —</option>
                        @foreach(['Male','Female','Other','Prefer not to say'] as $g)
                            <option value="{{ $g }}" {{ old('gender', $patient->gender) === $g ? 'selected' : '' }}>{{ $g }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Email</label>
                    <input type="email" name="email" value="{{ old('email', $patient->email) }}"
                           class="w-full border border-purple-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-500 bg-white"
                           style="font-family:'Inter',sans-serif;color:#1a0020;">
                </div>

            </div>
        </div>

        {{-- SECTION 2: Address --}}
        <div class="bg-white border border-purple-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-purple-100 flex items-center gap-3" style="background:#faf5fb;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                <span class="text-xs tracking-widest uppercase" style="font-family:'Inter',sans-serif;color:#6a0f70;font-weight:600;">Address</span>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-5">

                <div class="md:col-span-3">
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Street Address</label>
                    <input type="text" name="address" value="{{ old('address', $patient->address) }}"
                           class="w-full border border-purple-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-500 bg-white"
                           style="font-family:'Inter',sans-serif;color:#1a0020;">
                </div>

                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">City</label>
                    <input type="text" name="city" value="{{ old('city', $patient->city) }}"
                           class="w-full border border-purple-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-500 bg-white"
                           style="font-family:'Inter',sans-serif;color:#1a0020;">
                </div>

                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">State</label>
                    <input type="text" name="state" value="{{ old('state', $patient->state) }}"
                           class="w-full border border-purple-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-500 bg-white"
                           style="font-family:'Inter',sans-serif;color:#1a0020;">
                </div>

                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Pincode</label>
                    <input type="text" name="pincode" value="{{ old('pincode', $patient->pincode) }}"
                           class="w-full border border-purple-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-500 bg-white"
                           style="font-family:'Inter',sans-serif;color:#1a0020;">
                </div>

            </div>
        </div>

        {{-- SECTION 3: Clinical --}}
        <div class="bg-white border border-purple-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-purple-100 flex items-center gap-3" style="background:#faf5fb;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                <span class="text-xs tracking-widest uppercase" style="font-family:'Inter',sans-serif;color:#6a0f70;font-weight:600;">Clinical</span>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">

                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Chief Complaint</label>
                    <textarea name="chief_complaint" rows="3"
                              class="w-full border border-purple-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-500 bg-white resize-none"
                              style="font-family:'Inter',sans-serif;color:#1a0020;">{{ old('chief_complaint', $patient->chief_complaint) }}</textarea>
                </div>

                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Medical Alert</label>
                    <textarea name="medical_alert" rows="3"
                              class="w-full border border-purple-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-500 bg-white resize-none"
                              style="font-family:'Inter',sans-serif;color:#1a0020;">{{ old('medical_alert', $patient->medical_alert) }}</textarea>
                    <p class="mt-1 text-xs" style="font-family:'Inter',sans-serif;color:#9d6ea8;">Allergies, conditions, medications</p>
                </div>

            </div>
        </div>

        {{-- SECTION 4: Source --}}
        <div class="bg-white border border-purple-100 overflow-hidden">
            <div class="px-6 py-4 border-b border-purple-100 flex items-center gap-3" style="background:#faf5fb;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <span class="text-xs tracking-widest uppercase" style="font-family:'Inter',sans-serif;color:#6a0f70;font-weight:600;">Source & Referral</span>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">

                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Source</label>
                    <select name="source" class="w-full border border-purple-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-500 bg-white" style="font-family:'Inter',sans-serif;color:#1a0020;">
                        <option value="">— Select —</option>
                        @foreach(['Walk-in','Referral','Google','Instagram','Facebook','Just Dial','Other'] as $src)
                            <option value="{{ $src }}" {{ old('source', $patient->source) === $src ? 'selected' : '' }}>{{ $src }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs tracking-widest uppercase mb-1.5" style="font-family:'Inter',sans-serif;color:#380740;">Referred By</label>
                    <input type="text" name="referred_by" value="{{ old('referred_by', $patient->referred_by) }}"
                           class="w-full border border-purple-200 px-3 py-2.5 text-sm focus:outline-none focus:border-purple-500 bg-white"
                           style="font-family:'Inter',sans-serif;color:#1a0020;"
                           placeholder="Name of referring patient or doctor">
                </div>

            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('patients.show', $patient) }}"
               class="px-5 py-2.5 text-sm border border-purple-200 bg-white hover:bg-purple-50 transition-colors"
               style="font-family:'Inter',sans-serif;color:#6a0f70;">
                Cancel
            </a>
            <button type="submit"
                    class="px-8 py-2.5 text-sm text-white transition-opacity hover:opacity-90"
                    style="background:#6a0f70;font-family:'Inter',sans-serif;font-weight:500;">
                Save Changes
            </button>
        </div>

    </form>
</div>
@endsection
