{{--
| DPDP — Raise a Patient Rights request
| File: resources/views/data-requests/create.blade.php
--}}
@extends('layouts.app')
@section('page-title', 'New Patient Rights Request')
@section('content')
<div class="df-page-header" style="margin-bottom:20px;">
    <h1 class="df-page-title">New rights request</h1>
    <p class="df-page-subtitle">Log a DPDP request a patient has made.</p>
</div>

<form action="{{ route('data-rights.store') }}" method="POST" x-data="{ type: '{{ old('type','access') }}' }">
    @csrf
    <div class="df-card" style="max-width:640px;"><div class="df-card-body" style="padding:22px 24px;">

        <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Patient</label>
        @if($patient)
            <div style="padding:10px 12px; border:1px solid #d8c7d6; border-radius:8px; background:#faf5f9; margin-bottom:14px;">
                {{ $patient->name }} @if($patient->patient_id)· {{ $patient->patient_id }}@endif
            </div>
            <input type="hidden" name="patient_id" value="{{ $patient->id }}">
        @else
            <input type="number" name="patient_id" required placeholder="Patient ID (numeric)" value="{{ old('patient_id') }}"
                   style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:14px;">
        @endif

        <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Request type</label>
        <select name="type" x-model="type" style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:14px;">
            <option value="access">Access — give the patient a copy of their data</option>
            <option value="correction">Correction — fix inaccurate data</option>
            <option value="erasure">Erasure — delete personal data</option>
            <option value="grievance">Grievance — a complaint</option>
            <option value="nominee">Nominee — appoint someone to act for them</option>
        </select>

        <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Details</label>
        <textarea name="details" rows="3" maxlength="2000" placeholder="What is the patient asking for?"
                  style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:14px;">{{ old('details') }}</textarea>

        {{-- Nominee extras --}}
        <div x-show="type==='nominee'" x-cloak style="background:#faf5f9; border:1px solid #ead9e6; border-radius:10px; padding:14px; margin-bottom:14px;">
            <div style="font-weight:600; color:#4A1F3D; margin-bottom:8px; font-size:13px;">Nominee details</div>
            <input type="text" name="nominee_name" placeholder="Nominee name" value="{{ old('nominee_name') }}"
                   style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:8px;">
            <input type="text" name="nominee_relationship" placeholder="Relationship" value="{{ old('nominee_relationship') }}"
                   style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:8px;">
            <input type="text" name="nominee_contact" placeholder="Contact (phone / email)" value="{{ old('nominee_contact') }}"
                   style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px;">
        </div>

        <div style="display:flex; gap:14px; margin-bottom:18px;">
            <div style="flex:1;">
                <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Received via</label>
                <select name="requested_via" style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px;">
                    @foreach(['web','portal','email','phone','paper'] as $v)
                        <option value="{{ $v }}" @selected(old('requested_via')===$v)>{{ ucfirst($v) }}</option>
                    @endforeach
                </select>
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Raised by (if not patient)</label>
                <input type="text" name="requester_name" value="{{ old('requester_name') }}"
                       style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px;">
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <a href="{{ route('data-rights.index') }}" style="border:1px solid #d8c7d6; color:#6b5b73; padding:10px 18px; border-radius:8px; text-decoration:none;">Cancel</a>
            <button type="submit" style="background:#C2185B; color:#fff; border:none; padding:10px 22px; border-radius:8px; font-weight:600; cursor:pointer;">Log request</button>
        </div>
    </div></div>
</form>
<style>[x-cloak]{display:none!important;}</style>
@endsection
