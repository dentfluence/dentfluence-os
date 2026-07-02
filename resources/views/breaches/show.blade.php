{{--
| DPDP — Breach detail + actions
| File: resources/views/breaches/show.blade.php
--}}
@extends('layouts.app')
@section('page-title', 'Breach ' . $breach->reference)
@section('content')
<div class="df-page-header" style="margin-bottom:20px; display:flex; align-items:flex-start; justify-content:space-between;">
    <div>
        <h1 class="df-page-title">{{ $breach->reference }} · {{ $breach->title }}</h1>
        <p class="df-page-subtitle">{{ ucfirst($breach->severity) }} severity · {{ ucfirst($breach->status) }}</p>
    </div>
    <a href="{{ route('breaches.index') }}" style="align-self:center; border:1px solid #d8c7d6; color:#4A1F3D; padding:8px 14px; border-radius:8px; text-decoration:none;">← Register</a>
</div>

<div style="display:flex; gap:18px; align-items:flex-start; flex-wrap:wrap;">
    <div class="df-card" style="flex:2; min-width:320px;"><div class="df-card-body" style="padding:20px 24px;">
        <h3 style="margin:0 0 12px; color:#4A1F3D; font-size:14px;">Details</h3>
        <table style="width:100%; font-size:14px;">
            <tr><td style="padding:6px 0; color:#8a7790; width:150px;">Discovered</td><td>{{ optional($breach->discovered_at)->format('d M Y, H:i') }}</td></tr>
            @if($breach->occurred_at)<tr><td style="padding:6px 0; color:#8a7790;">Occurred</td><td>{{ $breach->occurred_at->format('d M Y, H:i') }}</td></tr>@endif
            <tr><td style="padding:6px 0; color:#8a7790;">Affected</td><td>{{ $breach->affected_count }} — {{ $breach->affected_scope ?: '—' }}</td></tr>
            <tr><td style="padding:6px 0; color:#8a7790;">Reported to Board</td><td>@if($breach->isReported())✓ {{ $breach->reported_to_board_at->format('d M Y') }} {{ $breach->board_reference ? '· '.$breach->board_reference : '' }}@else<span style="color:#9c2b2b;">Not yet</span>@endif</td></tr>
            <tr><td style="padding:6px 0; color:#8a7790;">Patients notified</td><td>@if($breach->patientsNotified())✓ {{ $breach->patients_notified_at->format('d M Y') }}@else<span style="color:#9c2b2b;">Not yet</span>@endif</td></tr>
        </table>
        @if($breach->description)<div style="margin-top:14px; padding:12px; background:#faf5f9; border-radius:8px; font-size:14px;">{{ $breach->description }}</div>@endif
        @if($breach->nature)<div style="margin-top:10px; font-size:13px; color:#6b5b73;"><b>Nature/cause:</b> {{ $breach->nature }}</div>@endif
    </div></div>

    <div class="df-card" style="flex:1; min-width:280px;"><div class="df-card-body" style="padding:20px 24px;">
        <h3 style="margin:0 0 12px; color:#4A1F3D; font-size:14px;">Actions</h3>

        <form action="{{ route('breaches.update', $breach) }}" method="POST" style="margin-bottom:14px;">
            @csrf @method('PATCH')
            <select name="status" style="width:100%; padding:8px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:8px;">
                @foreach(['open','contained','reported','closed'] as $s)<option value="{{ $s }}" @selected($breach->status===$s)>{{ ucfirst($s) }}</option>@endforeach
            </select>
            <button style="width:100%; background:none; border:1px solid #C2185B; color:#C2185B; padding:8px; border-radius:8px; font-weight:600; cursor:pointer;">Update status</button>
        </form>

        @unless($breach->isReported())
            <form action="{{ route('breaches.report-board', $breach) }}" method="POST" style="margin-bottom:10px;">
                @csrf
                <input type="text" name="board_reference" placeholder="Board reference (optional)" style="width:100%; padding:8px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:8px;">
                <button style="width:100%; background:#274690; color:#fff; border:none; padding:10px; border-radius:8px; font-weight:600; cursor:pointer;">Mark reported to Board</button>
            </form>
        @endunless

        @unless($breach->patientsNotified())
            <form action="{{ route('breaches.notify', $breach) }}" method="POST">
                @csrf
                <button style="width:100%; background:#1b7a3d; color:#fff; border:none; padding:10px; border-radius:8px; font-weight:600; cursor:pointer;">Mark patients notified</button>
            </form>
        @endunless
    </div></div>
</div>
@endsection
