{{--
| DPDP — Breach Register
| File: resources/views/breaches/index.blade.php
--}}
@extends('layouts.app')
@section('page-title', 'Breach Register')
@section('content')
@php
    $sevColors = ['low'=>['#eef4ff','#274690'],'medium'=>['#fff7e6','#8a6d00'],'high'=>['#ffe9d6','#9a4a00'],'critical'=>['#fbe3ec','#9c2b48']];
@endphp
<div class="df-page-header" style="margin-bottom:20px; display:flex; align-items:flex-start; justify-content:space-between;">
    <div>
        <h1 class="df-page-title">Breach Register</h1>
        <p class="df-page-subtitle">Personal-data breaches and their DPDP reporting status.</p>
    </div>
    <a href="{{ route('breaches.create') }}" style="align-self:center; background:#C2185B; color:#fff; text-decoration:none; padding:10px 18px; border-radius:8px; font-weight:600;">+ Log breach</a>
</div>

<div style="display:flex; gap:12px; margin-bottom:18px;">
    <div class="df-card" style="flex:1;"><div class="df-card-body" style="padding:14px 18px;"><div style="font-size:12px; color:#8a7790;">Open</div><div style="font-size:24px; font-weight:700; color:#4A1F3D;">{{ $counts['open'] }}</div></div></div>
    <div class="df-card" style="flex:1;"><div class="df-card-body" style="padding:14px 18px;"><div style="font-size:12px; color:#8a7790;">Not yet reported</div><div style="font-size:24px; font-weight:700; color:#9c2b2b;">{{ $counts['unreported'] }}</div></div></div>
    <div class="df-card" style="flex:1;"><div class="df-card-body" style="padding:14px 18px;"><div style="font-size:12px; color:#8a7790;">Total</div><div style="font-size:24px; font-weight:700; color:#4A1F3D;">{{ $counts['total'] }}</div></div></div>
</div>

<div class="df-card"><div class="df-card-body" style="padding:0; overflow:auto;">
    <table style="width:100%; border-collapse:collapse; font-size:14px;">
        <thead><tr style="text-align:left; background:#faf5f9; color:#4A1F3D;">
            <th style="padding:12px 16px;">Ref</th><th style="padding:12px 16px;">Title</th>
            <th style="padding:12px 16px;">Severity</th><th style="padding:12px 16px;">Discovered</th>
            <th style="padding:12px 16px;">Reported</th><th style="padding:12px 16px;">Status</th>
        </tr></thead>
        <tbody>
        @forelse($breaches as $b)
            <tr style="border-top:1px solid #f0e6ee; cursor:pointer;" onclick="window.location='{{ route('breaches.show',$b) }}'">
                <td style="padding:12px 16px; font-weight:600; color:#4A1F3D;">{{ $b->reference }}</td>
                <td style="padding:12px 16px;">{{ $b->title }}</td>
                <td style="padding:12px 16px;">@php [$bg,$fg]=$sevColors[$b->severity]??['#eee','#666']; @endphp<span style="background:{{ $bg }}; color:{{ $fg }}; padding:2px 10px; border-radius:10px; font-size:12px; font-weight:600;">{{ ucfirst($b->severity) }}</span></td>
                <td style="padding:12px 16px;">{{ optional($b->discovered_at)->format('d M Y') }}</td>
                <td style="padding:12px 16px;">@if($b->isReported())<span style="color:#1b7a3d;">✓ {{ optional($b->reported_to_board_at)->format('d M') }}</span>@else<span style="color:#9c2b2b;">No</span>@endif</td>
                <td style="padding:12px 16px;">{{ ucfirst($b->status) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" style="padding:24px; text-align:center; color:#8a7790;">No breaches logged. (That's good.)</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div style="margin-top:14px;">{{ $breaches->links() }}</div>
@endsection
