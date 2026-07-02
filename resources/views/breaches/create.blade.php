{{--
| DPDP — Log a breach
| File: resources/views/breaches/create.blade.php
--}}
@extends('layouts.app')
@section('page-title', 'Log a Breach')
@section('content')
<div class="df-page-header" style="margin-bottom:20px;">
    <h1 class="df-page-title">Log a breach</h1>
    <p class="df-page-subtitle">Record a personal-data breach. You can mark it reported / notified afterwards.</p>
</div>

<form action="{{ route('breaches.store') }}" method="POST">
    @csrf
    <div class="df-card" style="max-width:680px;"><div class="df-card-body" style="padding:22px 24px;">

        <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Title</label>
        <input type="text" name="title" required maxlength="160" value="{{ old('title') }}"
               style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:14px;">

        <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">What happened</label>
        <textarea name="description" rows="3" maxlength="4000"
                  style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:14px;">{{ old('description') }}</textarea>

        <div style="display:flex; gap:14px; margin-bottom:14px;">
            <div style="flex:1;">
                <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Severity</label>
                <select name="severity" style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px;">
                    @foreach(['low','medium','high','critical'] as $s)<option value="{{ $s }}" @selected(old('severity','medium')===$s)>{{ ucfirst($s) }}</option>@endforeach
                </select>
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Affected count</label>
                <input type="number" name="affected_count" min="0" value="{{ old('affected_count',0) }}"
                       style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px;">
            </div>
        </div>

        <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Affected scope</label>
        <input type="text" name="affected_scope" maxlength="160" placeholder="e.g. approx 120 patient records in Branch A" value="{{ old('affected_scope') }}"
               style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:14px;">

        <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Nature of data / cause</label>
        <textarea name="nature" rows="2" maxlength="2000"
                  style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:14px;">{{ old('nature') }}</textarea>

        <div style="display:flex; gap:14px; margin-bottom:18px;">
            <div style="flex:1;">
                <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Occurred at</label>
                <input type="datetime-local" name="occurred_at" value="{{ old('occurred_at') }}"
                       style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px;">
            </div>
            <div style="flex:1;">
                <label style="display:block; font-size:13px; font-weight:600; color:#4A1F3D; margin-bottom:4px;">Discovered at</label>
                <input type="datetime-local" name="discovered_at" required value="{{ old('discovered_at') }}"
                       style="width:100%; padding:9px 12px; border:1px solid #d8c7d6; border-radius:8px;">
            </div>
        </div>

        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <a href="{{ route('breaches.index') }}" style="border:1px solid #d8c7d6; color:#6b5b73; padding:10px 18px; border-radius:8px; text-decoration:none;">Cancel</a>
            <button type="submit" style="background:#C2185B; color:#fff; border:none; padding:10px 22px; border-radius:8px; font-weight:600; cursor:pointer;">Log breach</button>
        </div>
    </div></div>
</form>
@endsection
