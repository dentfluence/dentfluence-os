{{--
| DPDP — Patient Rights queue (DSAR)
| File: resources/views/data-requests/index.blade.php
--}}
@extends('layouts.app')
@section('page-title', 'Patient Rights Requests')
@section('content')
@php
    $typeLabels = ['access'=>'Access','correction'=>'Correction','erasure'=>'Erasure','grievance'=>'Grievance','nominee'=>'Nominee'];
    $statusColors = ['pending'=>['#fff7e6','#8a6d00'],'in_progress'=>['#eef4ff','#274690'],'completed'=>['#dcf3e4','#1b7a3d'],'rejected'=>['#f4d9d9','#9c2b2b']];
@endphp

<div class="df-page-header" style="margin-bottom:20px; display:flex; align-items:flex-start; justify-content:space-between;">
    <div>
        <h1 class="df-page-title">Patient Rights Requests</h1>
        <p class="df-page-subtitle">DPDP data-rights queue: access, correction, erasure, grievance, nominee.</p>
    </div>
    <a href="{{ route('data-rights.create') }}"
       style="align-self:center; background:#C2185B; color:#fff; text-decoration:none; padding:10px 18px; border-radius:8px; font-weight:600;">+ New request</a>
</div>

{{-- Stat chips --}}
<div style="display:flex; gap:12px; margin-bottom:18px;">
    <div class="df-card" style="flex:1;"><div class="df-card-body" style="padding:14px 18px;">
        <div style="font-size:12px; color:#8a7790;">Open</div><div style="font-size:24px; font-weight:700; color:#4A1F3D;">{{ $counts['open'] }}</div>
    </div></div>
    <div class="df-card" style="flex:1;"><div class="df-card-body" style="padding:14px 18px;">
        <div style="font-size:12px; color:#8a7790;">Overdue</div><div style="font-size:24px; font-weight:700; color:#9c2b2b;">{{ $counts['overdue'] }}</div>
    </div></div>
    <div class="df-card" style="flex:1;"><div class="df-card-body" style="padding:14px 18px;">
        <div style="font-size:12px; color:#8a7790;">Completed</div><div style="font-size:24px; font-weight:700; color:#1b7a3d;">{{ $counts['completed'] }}</div>
    </div></div>
</div>

{{-- Filters --}}
<form method="GET" style="display:flex; gap:10px; margin-bottom:14px;">
    <select name="status" onchange="this.form.submit()" style="padding:8px 12px; border:1px solid #d8c7d6; border-radius:8px;">
        <option value="">All statuses</option>
        @foreach(['pending','in_progress','completed','rejected'] as $s)
            <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>
        @endforeach
    </select>
    <select name="type" onchange="this.form.submit()" style="padding:8px 12px; border:1px solid #d8c7d6; border-radius:8px;">
        <option value="">All types</option>
        @foreach($typeLabels as $k=>$v)
            <option value="{{ $k }}" @selected(request('type')===$k)>{{ $v }}</option>
        @endforeach
    </select>
</form>

<div class="df-card"><div class="df-card-body" style="padding:0; overflow:auto;">
    <table style="width:100%; border-collapse:collapse; font-size:14px;">
        <thead><tr style="text-align:left; background:#faf5f9; color:#4A1F3D;">
            <th style="padding:12px 16px;">Reference</th><th style="padding:12px 16px;">Patient</th>
            <th style="padding:12px 16px;">Type</th><th style="padding:12px 16px;">Requested</th>
            <th style="padding:12px 16px;">Due</th><th style="padding:12px 16px;">Status</th>
        </tr></thead>
        <tbody>
        @forelse($requests as $r)
            <tr style="border-top:1px solid #f0e6ee; cursor:pointer;" onclick="window.location='{{ route('data-rights.show',$r) }}'">
                <td style="padding:12px 16px; font-weight:600; color:#4A1F3D;">{{ $r->reference }}</td>
                <td style="padding:12px 16px;">{{ $r->patient->name ?? '—' }}</td>
                <td style="padding:12px 16px;">{{ $typeLabels[$r->type] ?? ucfirst($r->type) }}</td>
                <td style="padding:12px 16px;">{{ optional($r->requested_at)->format('d M Y') }}</td>
                <td style="padding:12px 16px;">
                    @if($r->isOverdue())
                        <span style="color:#9c2b2b; font-weight:600;">{{ optional($r->due_at)->format('d M') }} · overdue</span>
                    @else
                        {{ optional($r->due_at)->format('d M Y') }}
                    @endif
                </td>
                <td style="padding:12px 16px;">
                    @php [$bg,$fg] = $statusColors[$r->status] ?? ['#eee','#666']; @endphp
                    <span style="background:{{ $bg }}; color:{{ $fg }}; padding:2px 10px; border-radius:10px; font-size:12px; font-weight:600;">{{ ucfirst(str_replace('_',' ',$r->status)) }}</span>
                </td>
            </tr>
        @empty
            <tr><td colspan="6" style="padding:24px; text-align:center; color:#8a7790;">No requests yet.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>

<div style="margin-top:14px;">{{ $requests->links() }}</div>
@endsection
