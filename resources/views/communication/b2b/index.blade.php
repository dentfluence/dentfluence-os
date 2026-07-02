{{--
    B2B Comm Module — Inbox
    Phase 4 · Dentfluence Communication OS

    Admin view: full detail, filters, status badges.
    Entry point for vendor / lab / consultant comms.
--}}
@extends('layouts.communication')

@push('communication-styles')
<style>
.b2b-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; padding:14px 24px; background:#fff; border-bottom:1px solid #e2e8f0; }
.b2b-header__title { font-size:15px; font-weight:600; color:#0f172a; margin:0; }
.b2b-chip { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:500; padding:3px 10px; border-radius:20px; border:1px solid #e2e8f0; background:#f8fafc; color:#475569; white-space:nowrap; }
.b2b-chip__count { font-weight:700; }
.b2b-chip--danger  { background:#fff1f2; border-color:#fecaca; color:#dc2626; }
.b2b-chip--orange  { background:#fff7ed; border-color:#fed7aa; color:#ea580c; }
.b2b-chip--blue    { background:#eff6ff; border-color:#bfdbfe; color:#2563eb; }
.b2b-chip--purple  { background:#faf5ff; border-color:#ddd6fe; color:#7c3aed; }

.b2b-filters { background:#f8fafc; border-bottom:1px solid #e2e8f0; padding:10px 24px; display:flex; align-items:flex-end; flex-wrap:wrap; gap:10px; }
.b2b-fl { display:flex; flex-direction:column; gap:3px; }
.b2b-fl label { font-size:10px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; }
.b2b-fl select, .b2b-fl input { border:1px solid #e2e8f0; padding:5px 10px; font-size:12px; border-radius:5px; background:#fff; outline:none; min-width:120px; }

.b2b-table-wrap { overflow-x:auto; }
table.b2b-table { width:100%; border-collapse:collapse; font-size:13px; }
table.b2b-table th { background:#f8fafc; padding:8px 12px; text-align:left; font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; border-bottom:2px solid #e2e8f0; white-space:nowrap; }
table.b2b-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
table.b2b-table tr:hover td { background:#f8fafc; }

.b2b-type-badge { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:600; padding:2px 8px; border-radius:12px; }
.b2b-type--lab        { background:#faf5ff; color:#7c3aed; }
.b2b-type--vendor     { background:#fff7ed; color:#ea580c; }
.b2b-type--consultant { background:#f0fdf4; color:#16a34a; }

.b2b-status-badge { font-size:11px; font-weight:600; padding:2px 8px; border-radius:10px; }
.b2b-status--pending   { background:#fffbeb; color:#d97706; }
.b2b-status--waiting   { background:#eff6ff; color:#2563eb; }
.b2b-status--overdue   { background:#fff1f2; color:#dc2626; }
.b2b-status--closed    { background:#f0fdf4; color:#16a34a; }

.b2b-btn { display:inline-flex; align-items:center; gap:5px; padding:5px 12px; font-size:12px; font-weight:500; border-radius:6px; cursor:pointer; border:none; transition:background .15s; text-decoration:none; white-space:nowrap; }
.b2b-btn--primary { background:#6a0f70; color:#fff; }
.b2b-btn--primary:hover { background:#4e0b52; color:#fff; }
.b2b-btn--outline { background:#fff; color:#374151; border:1px solid #d1d5db; }
.b2b-btn--sm { padding:3px 8px; font-size:11px; }

.b2b-empty { padding:48px 24px; text-align:center; color:#94a3b8; }
.b2b-empty h3 { font-size:15px; font-weight:600; color:#64748b; margin-bottom:6px; }
</style>
@endpush

@section('communication-content')

<div class="b2b-header">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
        <h1 class="b2b-header__title">B2B Communications</h1>

        <span class="b2b-chip b2b-chip--orange">
            Open <span class="b2b-chip__count">{{ $counts['open'] }}</span>
        </span>
        @if($counts['overdue'] > 0)
        <span class="b2b-chip b2b-chip--danger">
            SLA Breached <span class="b2b-chip__count">{{ $counts['overdue'] }}</span>
        </span>
        @endif
        <span class="b2b-chip b2b-chip--purple">
            Lab <span class="b2b-chip__count">{{ $counts['lab'] }}</span>
        </span>
        <span class="b2b-chip b2b-chip--orange">
            Vendor <span class="b2b-chip__count">{{ $counts['vendor'] }}</span>
        </span>
    </div>
    <a href="{{ route('communication.b2b.create') }}" class="b2b-btn b2b-btn--primary">
        + Log B2B Comm
    </a>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('communication.b2b.index') }}" class="b2b-filters">
    <div class="b2b-fl">
        <label>Type</label>
        <select name="contact_type" onchange="this.form.submit()">
            <option value="">All Types</option>
            @foreach(\App\Models\CommunicationQueue::CONTACT_TYPES as $val => $label)
                @if($val !== 'patient')
                <option value="{{ $val }}" {{ request('contact_type') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endif
            @endforeach
        </select>
    </div>
    <div class="b2b-fl">
        <label>Subtype</label>
        <select name="b2b_subtype" onchange="this.form.submit()">
            <option value="">All Subtypes</option>
            @foreach(\App\Models\CommunicationQueue::B2B_SUBTYPES as $val => $label)
            <option value="{{ $val }}" {{ request('b2b_subtype') == $val ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="b2b-fl">
        <label>Status</label>
        <select name="status" onchange="this.form.submit()">
            <option value="">All Statuses</option>
            <option value="pending"             {{ request('status') == 'pending'             ? 'selected' : '' }}>Pending</option>
            <option value="waiting_for_patient" {{ request('status') == 'waiting_for_patient' ? 'selected' : '' }}>Waiting</option>
            <option value="overdue"             {{ request('status') == 'overdue'             ? 'selected' : '' }}>Overdue</option>
            <option value="closed"              {{ request('status') == 'closed'              ? 'selected' : '' }}>Closed</option>
        </select>
    </div>
    <div class="b2b-fl">
        <label>SLA</label>
        <select name="sla_breached" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="1" {{ request('sla_breached') ? 'selected' : '' }}>Breached Only</option>
        </select>
    </div>
    @if(request()->hasAny(['contact_type','b2b_subtype','status','sla_breached']))
    <a href="{{ route('communication.b2b.index') }}" class="b2b-btn b2b-btn--outline b2b-btn--sm" style="margin-top:16px;">✕ Clear</a>
    @endif
</form>

{{-- Table --}}
<div class="b2b-table-wrap">
    @if($items->isEmpty())
        <div class="b2b-empty">
            <h3>No B2B communications found</h3>
            <p>Use "+ Log B2B Comm" to add a vendor, lab, or consultant communication.</p>
        </div>
    @else
    <table class="b2b-table">
        <thead>
            <tr>
                <th>Type</th>
                <th>Contact / Subtype</th>
                <th>Lab Case</th>
                <th>Channel</th>
                <th>Attempts</th>
                <th>SLA</th>
                <th>Status</th>
                <th>Assigned</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        @foreach($items as $item)
            <tr>
                <td>
                    <span class="b2b-type-badge b2b-type--{{ $item->contact_type }}">
                        @switch($item->contact_type)
                            @case('lab') Lab @break
                            @case('vendor') Vendor @break
                            @case('consultant')Consultant @break
                            @default {{ ucfirst($item->contact_type) }}
                        @endswitch
                    </span>
                </td>
                <td>
                    <div style="font-weight:600;color:#0f172a;">{{ $item->person_name }}</div>
                    @if($item->b2b_subtype)
                    <div style="font-size:11px;color:#64748b;">
                        {{ \App\Models\CommunicationQueue::B2B_SUBTYPES[$item->b2b_subtype] ?? $item->b2b_subtype }}
                    </div>
                    @endif
                    @if($item->note)
                    <div style="font-size:11px;color:#94a3b8;margin-top:2px;">{{ Str::limit($item->note, 60) }}</div>
                    @endif
                </td>
                <td>
                    @if($item->labCase)
                        <a href="#" style="font-size:12px;font-weight:600;color:#7c3aed;">#{{ $item->labCase->case_number }}</a>
                        <div style="font-size:10px;color:#94a3b8;">{{ \App\Models\LabCase::STATUS_LABELS[$item->labCase->status] ?? '' }}</div>
                    @else
                        <span style="color:#cbd5e1;">—</span>
                    @endif
                </td>
                <td>{{ $item->channel_icon }} {{ $item->channel_label }}</td>
                <td style="text-align:center;font-weight:600;color:{{ $item->attempt_count > 3 ? '#dc2626' : '#374151' }}">
                    {{ $item->attempt_count }}
                </td>
                <td>
                    @if($item->sla_breached)
                        <span style="font-size:11px;font-weight:600;color:#dc2626;">Breached</span>
                    @elseif($item->sla_deadline && $item->status !== 'closed')
                        <span style="font-size:11px;color:#64748b;">{{ $item->sla_status }}</span>
                    @else
                        <span style="color:#cbd5e1;font-size:11px;">—</span>
                    @endif
                </td>
                <td>
                    <span class="b2b-status-badge b2b-status--{{ str_replace('_for_patient','',$item->status) }}">
                        {{ $item->status_label }}
                    </span>
                </td>
                <td style="font-size:12px;color:#64748b;">{{ $item->assigned_to ?? '—' }}</td>
                <td style="font-size:11px;color:#94a3b8;white-space:nowrap;">{{ $item->created_at->format('d M, H:i') }}</td>
                <td>
                    <a href="{{ route('communication.b2b.show', $item->id) }}" class="b2b-btn b2b-btn--outline b2b-btn--sm">View</a>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <div style="padding:12px 24px;">
        {{ $items->links() }}
    </div>
    @endif
</div>

@endsection
