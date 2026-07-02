@extends('layouts.app')
@section('page-title', 'Consultations — {{ $patient->name }}')
@section('content')
<div style="padding:28px;font-family:'Inter',sans-serif;">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
        <a href="{{ route('patients.show', $patient) }}" style="color:#6a0f70;font-size:13px;">← Back to {{ $patient->name }}</a>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:24px;color:#1a0320;margin:0;">Consultations</h1>
    </div>
    @forelse($consultations as $c)
    <div style="padding:14px 18px;border:1px solid #ede4f3;border-radius:8px;margin-bottom:10px;">
        <div style="display:flex;justify-content:space-between;">
            <strong>{{ $c->visit_date?->format('d M Y') ?? '—' }}</strong>
            <a href="{{ route('consultations.show', [$patient, $c]) }}" style="color:#6a0f70;font-size:12px;">View →</a>
        </div>
        <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">{{ $c->chief_complaint ?? 'No chief complaint recorded.' }}</p>
    </div>
    @empty
    <p style="color:#9a7aaa;">No consultations found.</p>
    @endforelse
</div>
@endsection
