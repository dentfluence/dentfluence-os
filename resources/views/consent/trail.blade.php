{{--
| DPDP Consent — Tamper-evident history (trail)
| File: resources/views/consent/trail.blade.php
|
| Read-only audit view of every consent event for one patient, plus a banner
| confirming the hash chain is intact (DPDP item 5.6).
--}}
@extends('layouts.app')

@section('page-title', 'Consent history — ' . ($patient->name ?? 'Patient'))

@section('content')
<div class="df-page-header" style="margin-bottom:20px; display:flex; align-items:flex-start; justify-content:space-between;">
    <div>
        <h1 class="df-page-title">Consent history</h1>
        <p class="df-page-subtitle">{{ $patient->name }} @if($patient->patient_id)· {{ $patient->patient_id }}@endif</p>
    </div>
    <a href="{{ route('consent.patient', $patient) }}"
       style="align-self:center; color:#4A1F3D; text-decoration:none; font-weight:600; border:1px solid #d8c7d6; padding:8px 14px; border-radius:8px;">
        ← Back to consent
    </a>
</div>

{{-- ── Integrity banner ───────────────────────────────────────────────── --}}
@if($valid)
    <div style="background:#dcf3e4; border:1px solid #9ed8b3; color:#1b7a3d; padding:12px 16px; border-radius:10px; margin-bottom:18px; font-size:14px; font-weight:600;">
        ✓ Record intact — the consent history has not been altered.
    </div>
@else
    <div style="background:#fbe3ec; border:1px solid #e8a6bc; color:#9c2b48; padding:12px 16px; border-radius:10px; margin-bottom:18px; font-size:14px; font-weight:600;">
        ⚠️ Integrity check failed — the hash chain does not match. This record may have been tampered with. Investigate before relying on it.
    </div>
@endif

{{-- ── Event log ──────────────────────────────────────────────────────── --}}
<div class="df-card">
    <div class="df-card-body" style="padding:0; overflow:auto;">
        <table style="width:100%; border-collapse:collapse; font-size:14px;">
            <thead>
                <tr style="text-align:left; background:#faf5f9; color:#4A1F3D;">
                    <th style="padding:12px 16px;">When</th>
                    <th style="padding:12px 16px;">Event</th>
                    <th style="padding:12px 16px;">Purpose</th>
                    <th style="padding:12px 16px; text-align:center;">Ver.</th>
                    <th style="padding:12px 16px;">Via</th>
                    <th style="padding:12px 16px;">By</th>
                    <th style="padding:12px 16px;">Fingerprint</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr style="border-top:1px solid #f0e6ee;">
                        <td style="padding:12px 16px; white-space:nowrap;">{{ optional($log->created_at)->format('d M Y, H:i') }}</td>
                        <td style="padding:12px 16px;">
                            @if($log->event === 'granted')
                                <span style="background:#dcf3e4; color:#1b7a3d; padding:2px 9px; border-radius:10px; font-size:12px; font-weight:600;">Granted</span>
                            @elseif($log->event === 'withdrawn')
                                <span style="background:#fbe3ec; color:#9c2b48; padding:2px 9px; border-radius:10px; font-size:12px; font-weight:600;">Withdrawn</span>
                            @else
                                <span style="background:#eee; color:#666; padding:2px 9px; border-radius:10px; font-size:12px;">{{ ucfirst($log->event) }}</span>
                            @endif
                        </td>
                        <td style="padding:12px 16px;">
                            {{ $log->purpose->name ?? data_get($log->snapshot, 'purpose_name', $log->purpose_key) }}
                        </td>
                        <td style="padding:12px 16px; text-align:center;">v{{ $log->purpose_version }}</td>
                        <td style="padding:12px 16px; text-transform:capitalize;">{{ $log->capture_method }}</td>
                        <td style="padding:12px 16px;">{{ $log->capturedBy->name ?? 'System' }}</td>
                        <td style="padding:12px 16px;">
                            <code title="{{ $log->hash }}" style="font-size:12px; color:#8a7790;">{{ substr($log->hash, 0, 10) }}…</code>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="padding:24px; text-align:center; color:#8a7790;">
                        No consent events recorded yet for this patient.
                    </td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<p style="color:#9aa; font-size:12px; margin-top:12px;">
    Each row is hash-chained to the one before it. Editing or deleting any row breaks the chain, which is what the integrity banner above checks.
</p>
@endsection
