@extends('layouts.app')
@section('page-title', $protocol->exists ? 'Edit Protocol' : 'New Protocol')

@php
    $weekdays = [0=>'Sunday',1=>'Monday',2=>'Tuesday',3=>'Wednesday',4=>'Thursday',5=>'Friday',6=>'Saturday'];
@endphp

@section('content')
<div style="font-family:'Inter',sans-serif;padding:24px 28px;max-width:760px;">

    {{-- HEADER --}}
    <div style="margin-bottom:18px;">
        <a href="{{ route('practice-protocols.index') }}" style="font-size:12px;color:#9a7aaa;text-decoration:none;">← Back to protocols</a>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:24px;font-weight:700;color:#1a0320;margin:6px 0 0;">
            {{ $protocol->exists ? 'Edit Protocol' : 'New Protocol' }}
        </h1>
    </div>

    @if(session('success'))
    <div style="margin-bottom:16px;padding:11px 16px;background:#e8f7ef;border:1.5px solid #c8ebd8;border-radius:8px;color:#1a7a45;font-size:13px;">
        {{ session('success') }}
    </div>
    @endif

    @if($errors->any())
    <div style="margin-bottom:16px;padding:11px 16px;background:#fdeaea;border:1.5px solid #f3c0c0;border-radius:8px;color:#b52020;font-size:13px;">
        <strong>Please fix the following:</strong>
        <ul style="margin:6px 0 0;padding-left:18px;">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
    @endif

    {{-- MAIN FORM --}}
    <div x-data="{ frequency: '{{ old('frequency', $protocol->frequency) }}' }"
         style="background:#fff;border:1.5px solid #ede4f3;border-radius:12px;padding:24px;">
        <form action="{{ $protocol->exists ? route('practice-protocols.update', $protocol) : route('practice-protocols.store') }}" method="POST">
            @csrf
            @if($protocol->exists) @method('PUT') @endif

            {{-- Title --}}
            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Title *</label>
                <input type="text" name="title" required value="{{ old('title', $protocol->title) }}"
                       placeholder="e.g. Run autoclave test cycle and log result"
                       style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
            </div>

            {{-- Description --}}
            <div style="margin-bottom:14px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Description</label>
                <textarea name="description" rows="2" placeholder="Short note about what this duty involves"
                          style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;resize:vertical;">{{ old('description', $protocol->description) }}</textarea>
            </div>

            {{-- Role + Branch --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Role (who performs it) *</label>
                    <select name="role_id" required style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        <option value="">— Select role —</option>
                        @foreach($roles as $r)
                        <option value="{{ $r->id }}" @selected(old('role_id', $protocol->role_id) == $r->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Branch</label>
                    <select name="branch_id" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        <option value="">All branches</option>
                        @foreach($branches as $b)
                        <option value="{{ $b->id }}" @selected(old('branch_id', $protocol->branch_id) == $b->id)>{{ $b->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Category + Priority --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Category *</label>
                    <select name="category" required style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        @foreach($categories as $val => $label)
                        <option value="{{ $val }}" @selected(old('category', $protocol->category) == $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Priority *</label>
                    <select name="priority" required style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        @foreach($priorities as $val => $label)
                        <option value="{{ $val }}" @selected(old('priority', $protocol->priority) == $val)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            {{-- Frequency + conditional schedule --}}
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Frequency *</label>
                    <select name="frequency" x-model="frequency" required style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        @foreach($frequencies as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Weekday (weekly only) --}}
                <div x-show="frequency==='weekly'" x-cloak>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Day of week</label>
                    <select name="weekday" style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                        @foreach($weekdays as $num => $name)
                        <option value="{{ $num }}" @selected(old('weekday', $protocol->weekday) === $num)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Day of month (monthly only) --}}
                <div x-show="frequency==='monthly'" x-cloak>
                    <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Day of month (1–28)</label>
                    <input type="number" name="day_of_month" min="1" max="28" value="{{ old('day_of_month', $protocol->day_of_month ?? 1) }}"
                           style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
                </div>
            </div>

            {{-- Due time --}}
            <div style="margin-bottom:18px;max-width:240px;">
                <label style="font-size:12px;font-weight:600;color:#6a0f70;display:block;margin-bottom:5px;">Default due time</label>
                <input type="time" name="default_due_time" value="{{ old('default_due_time', $protocol->default_due_time ? \Carbon\Carbon::parse($protocol->default_due_time)->format('H:i') : '') }}"
                       style="width:100%;padding:10px 13px;border:1.5px solid #ddd;border-radius:7px;font-size:13px;font-family:inherit;outline:none;box-sizing:border-box;">
            </div>

            {{-- Toggles --}}
            <div style="display:flex;gap:24px;margin-bottom:22px;">
                <label style="display:flex;align-items:center;gap:9px;cursor:pointer;">
                    <input type="checkbox" name="requires_evidence" value="1" @checked(old('requires_evidence', $protocol->requires_evidence))
                           style="width:16px;height:16px;accent-color:#6a0f70;cursor:pointer;">
                    <span style="font-size:13px;color:#1a0320;">Require evidence on completion</span>
                </label>
                <label style="display:flex;align-items:center;gap:9px;cursor:pointer;">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $protocol->exists ? $protocol->is_active : true))
                           style="width:16px;height:16px;accent-color:#6a0f70;cursor:pointer;">
                    <span style="font-size:13px;color:#1a0320;">Active (generates tasks)</span>
                </label>
            </div>

            <div style="display:flex;gap:10px;">
                <button type="submit" style="padding:11px 22px;background:#6a0f70;color:#fff;border:none;border-radius:7px;font-size:13.5px;font-weight:600;cursor:pointer;font-family:inherit;">
                    {{ $protocol->exists ? 'Save changes' : 'Create protocol' }}
                </button>
                <a href="{{ route('practice-protocols.index') }}" style="padding:11px 20px;background:#fff;color:#6a0f70;border:1.5px solid #ede4f3;border-radius:7px;font-size:13px;text-decoration:none;">Cancel</a>
            </div>
        </form>
    </div>

    {{-- MATERIALS (edit mode only) --}}
    @if($protocol->exists)
        @include('practice-protocols._materials', ['protocol' => $protocol])
    @endif

</div>

<style>[x-cloak]{display:none!important;}</style>
@endsection
