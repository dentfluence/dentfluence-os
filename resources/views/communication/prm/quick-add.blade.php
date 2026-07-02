@extends('layouts.communication')
@section('title', 'Quick Add Lead')

@section('communication-content')

{{-- ── DEAD SIMPLE STAFF ENTRY FORM ────────────────────────────────────────
     Rule: max 4 visible fields, large tap targets, one obvious CTA.
     Target user: receptionist / call handler (12th-pass, not tech-savvy).
─────────────────────────────────────────────────────────────────────────── --}}

<style>
.qa-wrap {
    min-height: calc(100vh - 120px);
    display: flex;
    align-items: flex-start;
    justify-content: center;
    padding: 32px 20px 60px;
    background: #F9F7FC;
}
.qa-card {
    width: 100%;
    max-width: 480px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 2px 16px rgba(90,20,140,0.08);
    padding: 32px 28px 28px;
}
.qa-back {
    font-size: 12px;
    color: #9a7aaa;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 24px;
}
.qa-title {
    font-family: 'Cormorant Garamond', serif;
    font-size: 26px;
    color: #1a0320;
    margin: 0 0 4px;
}
.qa-sub {
    font-size: 13px;
    color: #9a7aaa;
    margin: 0 0 28px;
}
.qa-field { margin-bottom: 20px; }
.qa-label {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: #3D2B50;
    margin-bottom: 7px;
}
.qa-input,
.qa-select {
    width: 100%;
    border: 1.5px solid #DDD6E8;
    border-radius: 10px;
    padding: 13px 14px;
    font-size: 15px;
    color: #1a0320;
    background: #FDFCFF;
    outline: none;
    font-family: inherit;
    box-sizing: border-box;
    transition: border-color 0.15s;
    -webkit-appearance: none;
}
.qa-input:focus,
.qa-select:focus {
    border-color: #8B5CF6;
    box-shadow: 0 0 0 4px rgba(139,92,246,0.1);
}
.qa-submit {
    width: 100%;
    background: #5B21B6;
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: 15px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    font-family: inherit;
    margin-top: 8px;
    transition: background 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.qa-submit:hover { background: #4C1D95; }
.qa-fullform {
    display: block;
    text-align: center;
    margin-top: 14px;
    font-size: 12px;
    color: #9a7aaa;
    text-decoration: none;
}
.qa-fullform:hover { color: #5B21B6; }
.qa-error {
    background: #FEF2F2;
    border: 1px solid #FCA5A5;
    border-radius: 8px;
    padding: 10px 14px;
    margin-bottom: 20px;
    font-size: 13px;
    color: #B91C1C;
}
</style>

<div class="qa-wrap">
    <div class="qa-card">

        <a href="{{ route('prm.index') }}" class="qa-back">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
            Back to Pipeline
        </a>

        <h1 class="qa-title">New Lead</h1>
        <p class="qa-sub">Fill in these 4 details and you're done.</p>

        @if($errors->any())
            <div class="qa-error">
                @foreach($errors->all() as $err)
                    <div>{{ $err }}</div>
                @endforeach
            </div>
        @endif

        @if(session('success'))
            <div style="background:#F0FDF4;border:1px solid #86EFAC;border-radius:8px;padding:10px 14px;margin-bottom:20px;font-size:13px;color:#15803D;">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('prm.store-quick-lead') }}">
            @csrf

            {{-- 1. Name --}}
            <div class="qa-field">
                <label class="qa-label" for="qa-name">
                    Patient's Name <span style="color:#E24B4A;">*</span>
                </label>
                <input id="qa-name" type="text" name="name" class="qa-input"
                       value="{{ old('name') }}"
                       placeholder="e.g. Priya Sharma"
                       autocomplete="off" required autofocus>
            </div>

            {{-- 2. Phone --}}
            <div class="qa-field">
                <label class="qa-label" for="qa-phone">
                    Phone Number <span style="color:#E24B4A;">*</span>
                </label>
                <input id="qa-phone" type="tel" name="phone" class="qa-input"
                       value="{{ old('phone') }}"
                       placeholder="e.g. 9876543210"
                       inputmode="numeric" required>
            </div>

            {{-- 3. Source --}}
            <div class="qa-field">
                <label class="qa-label" for="qa-source">
                    How did they find us? <span style="color:#E24B4A;">*</span>
                </label>
                <select id="qa-source" name="lead_source" class="qa-select" required>
                    <option value="">— Choose one —</option>
                    @foreach($leadSources as $key => $label)
                        <option value="{{ $key }}"
                            {{ old('lead_source') === $key ? 'selected' : '' }}>
                            {{ $label }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- 4. Treatment --}}
            <div class="qa-field">
                <label class="qa-label" for="qa-treatment">
                    What treatment do they want?
                </label>
                <select id="qa-treatment" name="treatment" class="qa-select">
                    <option value="">— Don't know yet —</option>
                    @foreach($treatments as $t)
                        <option value="{{ $t }}"
                            {{ old('treatment') === $t ? 'selected' : '' }}>
                            {{ $t }}
                        </option>
                    @endforeach
                </select>
            </div>

            <button type="submit" class="qa-submit">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                Add to Pipeline
            </button>

        </form>

        <a href="{{ route('prm.add-lead') }}" class="qa-fullform">
            Need to add more details? Use the full form →
        </a>

    </div>
</div>

@endsection
