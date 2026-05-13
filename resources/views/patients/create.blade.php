@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-[#f5eef9] py-8 px-6">

    {{-- Page Header --}}
    <div class="mb-8 flex items-center justify-between">
        <div>
            <p class="text-xs uppercase tracking-[0.2em] text-[#6a0f70] font-semibold mb-1" style="font-family: 'DM Sans', sans-serif;">Tulip Dental · Patients</p>
            <h1 class="text-3xl text-[#380740]" style="font-family: 'Cormorant Garamond', serif; font-weight: 600; letter-spacing: -0.01em;">Register New Patient</h1>
        </div>
        <a href="{{ route('patients.index') }}"
           class="flex items-center gap-2 text-sm text-[#6a0f70] border border-[#6a0f70] px-4 py-2 hover:bg-[#6a0f70] hover:text-white transition-colors duration-200"
           style="font-family: 'DM Sans', sans-serif;">
            <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
            Back to Patients
        </a>
    </div>

    {{-- Validation Errors --}}
    @if($errors->any())
    <div class="mb-6 border-l-4 border-red-500 bg-red-50 px-5 py-4" style="font-family: 'DM Sans', sans-serif;">
        <p class="text-sm font-semibold text-red-700 mb-1">Please fix the following errors:</p>
        <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form action="{{ route('patients.store') }}" method="POST" style="font-family: 'DM Sans', sans-serif;">
        @csrf

        {{-- SECTION 1: Personal Information --}}
        <div class="bg-white border border-[#e8d5f0] mb-6">
            <div class="border-b border-[#e8d5f0] px-6 py-4 flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 1 0-16 0"/></svg>
                <h2 class="text-sm font-semibold uppercase tracking-widest text-[#6a0f70]">Personal Information</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-5">

                {{-- Full Name --}}
                <div class="md:col-span-2">
                    <label class="block text-xs uppercase tracking-widest text-[#380740] mb-1.5 font-semibold">Full Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}"
                           class="w-full border border-[#d8bfe8] bg-[#faf7fc] px-4 py-2.5 text-sm text-[#380740] placeholder-[#b89fc5] focus:outline-none focus:border-[#6a0f70] focus:bg-white transition-colors"
                           placeholder="e.g. Priya Sharma">
                </div>

                {{-- Phone --}}
                <div>
                    <label class="block text-xs uppercase tracking-widest text-[#380740] mb-1.5 font-semibold">Phone <span class="text-red-500">*</span></label>
                    <input type="text" name="phone" value="{{ old('phone') }}"
                           class="w-full border border-[#d8bfe8] bg-[#faf7fc] px-4 py-2.5 text-sm text-[#380740] placeholder-[#b89fc5] focus:outline-none focus:border-[#6a0f70] focus:bg-white transition-colors"
                           placeholder="10-digit mobile">
                </div>

                {{-- Date of Birth --}}
                <div>
                    <label class="block text-xs uppercase tracking-widest text-[#380740] mb-1.5 font-semibold">Date of Birth</label>
                    <input type="date" name="dob" value="{{ old('dob') }}"
                           class="w-full border border-[#d8bfe8] bg-[#faf7fc] px-4 py-2.5 text-sm text-[#380740] focus:outline-none focus:border-[#6a0f70] focus:bg-white transition-colors">
                </div>

                {{-- Gender --}}
                <div>
                    <label class="block text-xs uppercase tracking-widest text-[#380740] mb-1.5 font-semibold">Gender</label>
                    <select name="gender"
                            class="w-full border border-[#d8bfe8] bg-[#faf7fc] px-4 py-2.5 text-sm text-[#380740] focus:outline-none focus:border-[#6a0f70] focus:bg-white transition-colors appearance-none">
                        <option value="">— Select —</option>
                        <option value="male"   {{ old('gender') == 'male'   ? 'selected' : '' }}>Male</option>
                        <option value="female" {{ old('gender') == 'female' ? 'selected' : '' }}>Female</option>
                        <option value="other"  {{ old('gender') == 'other'  ? 'selected' : '' }}>Other</option>
                    </select>
                </div>

                {{-- Email --}}
                <div>
                    <label class="block text-xs uppercase tracking-widest text-[#380740] mb-1.5 font-semibold">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}"
                           class="w-full border border-[#d8bfe8] bg-[#faf7fc] px-4 py-2.5 text-sm text-[#380740] placeholder-[#b89fc5] focus:outline-none focus:border-[#6a0f70] focus:bg-white transition-colors"
                           placeholder="optional">
                </div>

            </div>
        </div>

        {{-- SECTION 2: Address --}}
        <div class="bg-white border border-[#e8d5f0] mb-6">
            <div class="border-b border-[#e8d5f0] px-6 py-4 flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                <h2 class="text-sm font-semibold uppercase tracking-widest text-[#6a0f70]">Address</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-3 gap-5">

                <div class="md:col-span-3">
                    <label class="block text-xs uppercase tracking-widest text-[#380740] mb-1.5 font-semibold">Street Address</label>
                    <input type="text" name="address" value="{{ old('address') }}"
                           class="w-full border border-[#d8bfe8] bg-[#faf7fc] px-4 py-2.5 text-sm text-[#380740] placeholder-[#b89fc5] focus:outline-none focus:border-[#6a0f70] focus:bg-white transition-colors"
                           placeholder="Flat / Building / Street">
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-widest text-[#380740] mb-1.5 font-semibold">City</label>
                    <input type="text" name="city" value="{{ old('city') }}"
                           class="w-full border border-[#d8bfe8] bg-[#faf7fc] px-4 py-2.5 text-sm text-[#380740] placeholder-[#b89fc5] focus:outline-none focus:border-[#6a0f70] focus:bg-white transition-colors"
                           placeholder="Dombivli">
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-widest text-[#380740] mb-1.5 font-semibold">State</label>
                    <input type="text" name="state" value="{{ old('state') }}"
                           class="w-full border border-[#d8bfe8] bg-[#faf7fc] px-4 py-2.5 text-sm text-[#380740] placeholder-[#b89fc5] focus:outline-none focus:border-[#6a0f70] focus:bg-white transition-colors"
                           placeholder="Maharashtra">
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-widest text-[#380740] mb-1.5 font-semibold">Pincode</label>
                    <input type="text" name="pincode" value="{{ old('pincode') }}"
                           class="w-full border border-[#d8bfe8] bg-[#faf7fc] px-4 py-2.5 text-sm text-[#380740] placeholder-[#b89fc5] focus:outline-none focus:border-[#6a0f70] focus:bg-white transition-colors"
                           placeholder="421201">
                </div>

            </div>
        </div>

        {{-- SECTION 3: Clinical --}}
        <div class="bg-white border border-[#e8d5f0] mb-6">
            <div class="border-b border-[#e8d5f0] px-6 py-4 flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                <h2 class="text-sm font-semibold uppercase tracking-widest text-[#6a0f70]">Clinical Details</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">

                <div>
                    <label class="block text-xs uppercase tracking-widest text-[#380740] mb-1.5 font-semibold">Chief Complaint</label>
                    <textarea name="chief_complaint" rows="3"
                              class="w-full border border-[#d8bfe8] bg-[#faf7fc] px-4 py-2.5 text-sm text-[#380740] placeholder-[#b89fc5] focus:outline-none focus:border-[#6a0f70] focus:bg-white transition-colors resize-none"
                              placeholder="Primary reason for visit...">{{ old('chief_complaint') }}</textarea>
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-widest text-[#380740] mb-1.5 font-semibold">
                        Medical Alert
                        <span class="ml-1 text-[10px] normal-case tracking-normal text-red-500 font-normal">(allergies, conditions)</span>
                    </label>
                    <textarea name="medical_alert" rows="3"
                              class="w-full border border-[#d8bfe8] bg-[#faf7fc] px-4 py-2.5 text-sm text-[#380740] placeholder-[#b89fc5] focus:outline-none focus:border-[#6a0f70] focus:bg-white transition-colors resize-none"
                              placeholder="e.g. Diabetic, Penicillin allergy...">{{ old('medical_alert') }}</textarea>
                </div>

            </div>
        </div>

        {{-- SECTION 4: Source --}}
        <div class="bg-white border border-[#e8d5f0] mb-8">
            <div class="border-b border-[#e8d5f0] px-6 py-4 flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                <h2 class="text-sm font-semibold uppercase tracking-widest text-[#6a0f70]">Source & Referral</h2>
            </div>
            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-5">

                <div>
                    <label class="block text-xs uppercase tracking-widest text-[#380740] mb-1.5 font-semibold">Source</label>
                    <select name="source"
                            class="w-full border border-[#d8bfe8] bg-[#faf7fc] px-4 py-2.5 text-sm text-[#380740] focus:outline-none focus:border-[#6a0f70] focus:bg-white transition-colors appearance-none">
                        <option value="">— How did they find us? —</option>
                        <option value="walk_in"    {{ old('source') == 'walk_in'    ? 'selected' : '' }}>Walk-in</option>
                        <option value="referral"   {{ old('source') == 'referral'   ? 'selected' : '' }}>Referral</option>
                        <option value="google"     {{ old('source') == 'google'     ? 'selected' : '' }}>Google</option>
                        <option value="instagram"  {{ old('source') == 'instagram'  ? 'selected' : '' }}>Instagram</option>
                        <option value="facebook"   {{ old('source') == 'facebook'   ? 'selected' : '' }}>Facebook</option>
                        <option value="justdial"   {{ old('source') == 'justdial'   ? 'selected' : '' }}>JustDial</option>
                        <option value="practo"     {{ old('source') == 'practo'     ? 'selected' : '' }}>Practo</option>
                        <option value="other"      {{ old('source') == 'other'      ? 'selected' : '' }}>Other</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-widest text-[#380740] mb-1.5 font-semibold">Referred By</label>
                    <input type="text" name="referred_by" value="{{ old('referred_by') }}"
                           class="w-full border border-[#d8bfe8] bg-[#faf7fc] px-4 py-2.5 text-sm text-[#380740] placeholder-[#b89fc5] focus:outline-none focus:border-[#6a0f70] focus:bg-white transition-colors"
                           placeholder="Name of referring patient / doctor">
                </div>

            </div>
        </div>

        {{-- Submit --}}
        <div class="flex items-center gap-4">
            <button type="submit"
                    class="bg-[#6a0f70] text-white px-8 py-3 text-sm font-semibold uppercase tracking-widest hover:bg-[#380740] transition-colors duration-200 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                Register Patient
            </button>
            <a href="{{ route('patients.index') }}"
               class="text-sm text-[#6a0f70] underline underline-offset-4 hover:text-[#380740] transition-colors">
                Cancel
            </a>
        </div>

    </form>
</div>
@endsection
