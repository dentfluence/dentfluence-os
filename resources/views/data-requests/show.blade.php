{{--
| DPDP — Handle a Patient Rights request
| File: resources/views/data-requests/show.blade.php
| $req = DataRequest
--}}
@extends('layouts.app')
@section('page-title', 'Request ' . $req->reference)
@section('content')
@php
    $typeLabels = ['access'=>'Access','correction'=>'Correction','erasure'=>'Erasure','grievance'=>'Grievance','nominee'=>'Nominee'];
@endphp

<div class="df-page-header" style="margin-bottom:20px; display:flex; align-items:flex-start; justify-content:space-between;">
    <div>
        <h1 class="df-page-title">{{ $req->reference }} · {{ $typeLabels[$req->type] ?? ucfirst($req->type) }}</h1>
        <p class="df-page-subtitle">
            {{ $req->patient->name ?? '—' }}
            @if($req->patient)· <a href="{{ route('consent.patient',$req->patient) }}" style="color:#C2185B;">view consent</a>@endif
        </p>
    </div>
    <a href="{{ route('data-rights.index') }}" style="align-self:center; border:1px solid #d8c7d6; color:#4A1F3D; padding:8px 14px; border-radius:8px; text-decoration:none;">← Queue</a>
</div>

<div style="display:flex; gap:18px; align-items:flex-start; flex-wrap:wrap;">
    {{-- Left: details --}}
    <div class="df-card" style="flex:2; min-width:320px;"><div class="df-card-body" style="padding:20px 24px;">
        <h3 style="margin:0 0 12px; color:#4A1F3D; font-size:14px;">Request details</h3>
        <table style="width:100%; font-size:14px;">
            <tr><td style="padding:6px 0; color:#8a7790; width:140px;">Status</td><td style="font-weight:600;">{{ ucfirst(str_replace('_',' ',$req->status)) }}</td></tr>
            <tr><td style="padding:6px 0; color:#8a7790;">Requested</td><td>{{ optional($req->requested_at)->format('d M Y, H:i') }} · via {{ $req->requested_via }}</td></tr>
            <tr><td style="padding:6px 0; color:#8a7790;">Due</td><td>{{ optional($req->due_at)->format('d M Y') }} @if($req->isOverdue())<span style="color:#9c2b2b; font-weight:600;">· overdue</span>@endif</td></tr>
            @if($req->requester_name)<tr><td style="padding:6px 0; color:#8a7790;">Raised by</td><td>{{ $req->requester_name }}</td></tr>@endif
            @if($req->assignedTo)<tr><td style="padding:6px 0; color:#8a7790;">Assigned</td><td>{{ $req->assignedTo->name }}</td></tr>@endif
            @if($req->resolved_at)<tr><td style="padding:6px 0; color:#8a7790;">Resolved</td><td>{{ $req->resolved_at->format('d M Y, H:i') }} by {{ $req->resolvedBy->name ?? '—' }}</td></tr>@endif
        </table>
        @if($req->details)
            <div style="margin-top:14px; padding:12px; background:#faf5f9; border-radius:8px; color:#4a3a52; font-size:14px;">{{ $req->details }}</div>
        @endif
        @if($req->payload)
            <div style="margin-top:10px; font-size:13px; color:#6b5b73;">Nominee: {{ $req->payload['nominee_name'] ?? '—' }} ({{ $req->payload['nominee_relationship'] ?? '—' }}) · {{ $req->payload['nominee_contact'] ?? '—' }}</div>
        @endif
        @if($req->resolution)
            <div style="margin-top:14px;"><div style="font-size:12px; color:#8a7790;">Resolution</div><div style="font-size:14px;">{{ $req->resolution }}</div></div>
        @endif
    </div></div>

    {{-- Right: actions --}}
    <div class="df-card" style="flex:1; min-width:280px;"><div class="df-card-body" style="padding:20px 24px;">
        <h3 style="margin:0 0 12px; color:#4A1F3D; font-size:14px;">Actions</h3>

        @if(! in_array($req->status, ['completed','rejected']))
            {{-- Update status / resolution --}}
            <form action="{{ route('data-rights.update', $req) }}" method="POST" style="margin-bottom:14px;">
                @csrf @method('PATCH')
                <input type="hidden" name="assigned_to" value="{{ auth()->id() }}">
                <select name="status" style="width:100%; padding:8px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:8px;">
                    @foreach(['pending','in_progress'] as $s)<option value="{{ $s }}" @selected($req->status===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach
                </select>
                <textarea name="resolution" rows="2" placeholder="Working notes" style="width:100%; padding:8px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:8px;">{{ $req->resolution }}</textarea>
                <button style="width:100%; background:none; border:1px solid #C2185B; color:#C2185B; padding:8px; border-radius:8px; font-weight:600; cursor:pointer;">Save & assign to me</button>
            </form>

            {{-- Type-specific fulfilment --}}
            @if($req->type==='access')
                <a href="{{ route('data-rights.download', $req) }}"
                   style="display:block; text-align:center; background:#274690; color:#fff; padding:10px; border-radius:8px; text-decoration:none; font-weight:600; margin-bottom:10px;">⬇ Download data export (JSON)</a>
            @endif

            @if($req->type==='erasure')
                <form action="{{ route('data-rights.erase', $req) }}" method="POST" style="margin-bottom:10px;"
                      onsubmit="return confirm('This permanently anonymises the patient\'s personal data. Continue?');">
                    @csrf
                    <div style="background:#fbe3ec; border:1px solid #e8a6bc; color:#9c2b48; padding:10px; border-radius:8px; font-size:12px; margin-bottom:8px;">
                        Erasure anonymises name &amp; contact details. Clinical/financial records are kept per retention law. Type <b>ERASE</b> to confirm.
                    </div>
                    <input type="text" name="confirm" placeholder="Type ERASE" style="width:100%; padding:8px 12px; border:1px solid #e8a6bc; border-radius:8px; margin-bottom:8px;">
                    <button style="width:100%; background:#9c2b48; color:#fff; border:none; padding:10px; border-radius:8px; font-weight:600; cursor:pointer;">Anonymise &amp; complete</button>
                </form>
            @endif

            {{-- Complete (for non-erasure) --}}
            @if($req->type!=='erasure')
                <form action="{{ route('data-rights.complete', $req) }}" method="POST" style="margin-bottom:10px;">
                    @csrf
                    <button style="width:100%; background:#1b7a3d; color:#fff; border:none; padding:10px; border-radius:8px; font-weight:600; cursor:pointer;">✓ Mark completed</button>
                </form>
            @endif

            {{-- Reject --}}
            <form action="{{ route('data-rights.reject', $req) }}" method="POST">
                @csrf
                <input type="text" name="reason" required placeholder="Reason for rejection" style="width:100%; padding:8px 12px; border:1px solid #d8c7d6; border-radius:8px; margin-bottom:8px;">
                <button style="width:100%; background:none; border:1px solid #9c2b2b; color:#9c2b2b; padding:8px; border-radius:8px; font-weight:600; cursor:pointer;">Reject request</button>
            </form>
        @else
            <p style="color:#6b5b73; font-size:14px;">This request is {{ $req->status }} — no further actions.</p>
        @endif
    </div></div>
</div>
@endsection
