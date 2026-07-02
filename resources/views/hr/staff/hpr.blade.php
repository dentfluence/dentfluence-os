@extends('layouts.app')
@section('page-title', $user->name . ' — HPR / Health ID')

@section('content')
<div class="p-6 space-y-6 max-w-3xl">

    {{-- Back --}}
    <div class="flex items-center justify-between">
        <a href="{{ route('hr.staff.show', $user) }}" class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Profile
        </a>
        <h1 class="text-sm font-semibold text-gray-700">HPR — Healthcare Professional Registry</h1>
    </div>

    @if(session('success'))
    <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-lg px-4 py-3">{{ session('success') }}</div>
    @endif

    @if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-lg px-4 py-3">
        <ul class="list-disc list-inside space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    <div class="bg-purple-50 border border-purple-100 text-purple-800 text-xs rounded-lg px-4 py-3 leading-relaxed">
        Record this clinician's HPR (Healthcare Professional Registry) id. Stored locally only — <strong>not</strong> verified
        against ABDM yet. Set the status by hand.
    </div>

    <form method="POST" action="{{ route('hr.staff.hpr.update', $user) }}" class="bg-white border border-gray-200 rounded-lg">
        @csrf
        @method('PATCH')

        <div class="px-5 py-4 border-b border-gray-100">
            <h2 class="font-semibold text-gray-800">Health ID — {{ $user->name }}</h2>
            <p class="text-xs text-gray-400 mt-0.5">Council registration (license) stays in the HR profile; HPR is the national id.</p>
        </div>

        <div class="p-5 space-y-5">

            <div>
                <label class="block text-xs text-gray-500 mb-1">HPR ID</label>
                <input type="text" name="hpr_id" value="{{ old('hpr_id', $profile->hpr_id) }}"
                       placeholder="e.g. 71-2345-6789-0123"
                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-purple-400">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Medical / Dental Council</label>
                    <input type="text" name="medical_council_name" value="{{ old('medical_council_name', $profile->medical_council_name) }}"
                           placeholder="e.g. Maharashtra State Dental Council"
                           class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-purple-400">
                </div>
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Registration Year</label>
                    <input type="number" name="registration_year" value="{{ old('registration_year', $profile->registration_year) }}"
                           placeholder="2015" min="1950" max="{{ date('Y') }}"
                           class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-purple-400">
                </div>
            </div>

            <div class="md:w-1/2">
                <label class="block text-xs text-gray-500 mb-1">Verification Status</label>
                <select name="hpr_verification_status"
                        class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg bg-white focus:outline-none focus:border-purple-400">
                    @foreach($statuses as $s)
                        <option value="{{ $s }}" @selected(old('hpr_verification_status', $profile->hpr_verification_status ?? 'unlinked') === $s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>

            @if($profile->hpr_linked_at)
            <p class="text-xs text-gray-400">Linked on {{ $profile->hpr_linked_at->format('d M Y, H:i') }}</p>
            @endif

        </div>

        <div class="px-5 py-4 border-t border-gray-100 flex justify-end">
            <button type="submit" class="px-6 py-2 text-sm text-white rounded-lg" style="background:#6a0f70;">Save HPR Details</button>
        </div>
    </form>
</div>
@endsection
