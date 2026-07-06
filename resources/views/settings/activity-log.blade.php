@extends('layouts.app')
@section('page-title', 'Activity Log')

@push('styles')
<style>
#df-content-inner { padding: 0 !important; height: 100%; display: flex; flex-direction: column; }
[x-cloak] { display: none !important; }

.al-filter-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    padding: 16px 28px;
    background: #fff;
    border-bottom: 1px solid #ede4f3;
}
.al-filter-bar select,
.al-filter-bar input[type="date"] {
    padding: 7px 10px;
    border: 1.5px solid #e0d4ea;
    border-radius: 7px;
    font-size: 12.5px;
    color: #1a0320;
    background: #fff;
    font-family: inherit;
    outline: none;
}
.al-filter-bar select:focus,
.al-filter-bar input[type="date"]:focus { border-color: #8b44aa; }
.al-filter-btn {
    padding: 7px 16px;
    background: #6a0f70;
    color: #fff;
    border: none;
    border-radius: 7px;
    font-size: 12.5px;
    font-weight: 600;
    cursor: pointer;
}
.al-filter-clear {
    padding: 7px 14px;
    background: #fff;
    color: #7a6080;
    border: 1.5px solid #e0d4ea;
    border-radius: 7px;
    font-size: 12.5px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
}

.al-table { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.al-table th {
    text-align: left;
    padding: 9px 14px;
    background: #faf6fc;
    color: #7a6080;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .05em;
    border-bottom: 1.5px solid #ede4f3;
    white-space: nowrap;
}
.al-table td {
    padding: 10px 14px;
    border-bottom: 1px solid #f5f0f8;
    color: #2a1830;
    vertical-align: top;
}
.al-table tr:hover td { background: #fdf9ff; }

.al-chip {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 10.5px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .03em;
}
.al-chip--created  { background: #e6f6ec; color: #1a7a45; }
.al-chip--updated  { background: #fef3e0; color: #a05c00; }
.al-chip--deleted  { background: #fbe8e8; color: #c0392b; }
.al-chip--security { background: #e8eefb; color: #1558b0; }

.al-device {
    font-size: 10.5px;
    color: #9a7aaa;
    text-transform: uppercase;
    letter-spacing: .03em;
}

.al-empty { padding: 60px 20px; text-align: center; color: #b09ac0; font-size: 13px; }

.al-panel {
    margin: 20px 28px 28px;
    background: #fff;
    border: 1.5px solid #ede4f3;
    border-radius: 12px;
    padding: 20px 22px;
}
</style>
@endpush

@section('content')
<div style="font-family:'Inter',sans-serif;height:100%;display:flex;flex-direction:column;background:#f7f4fa;overflow-y:auto;">

{{-- ── PAGE HEADER ── --}}
<div style="padding:18px 28px 16px;background:#fff;border-bottom:1px solid #ede4f3;flex-shrink:0;display:flex;align-items:center;gap:14px;">
    <a href="{{ route('settings.index') }}"
       style="display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border:1.5px solid #e0d4ea;border-radius:8px;color:#7a6080;text-decoration:none;flex-shrink:0;"
       title="Back to Settings">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
    </a>
    <div>
        <h1 style="font-family:'Cormorant Garamond',serif;font-size:24px;font-weight:700;color:#1a0320;margin:0 0 2px;">Activity Log</h1>
        <p style="font-size:12.5px;color:#9a7aaa;margin:0;">Who did what, when, and from which device — across appointments, patients, billing, prescriptions, treatment plans, and staff accounts.</p>
    </div>
</div>

{{-- ── FILTER BAR ── --}}
<form method="GET" action="{{ route('settings.activity-log') }}" class="al-filter-bar">
    <select name="user_id">
        <option value="">All staff</option>
        @foreach($users as $u)
            <option value="{{ $u->id }}" @selected(request('user_id') == $u->id)>{{ $u->name }}</option>
        @endforeach
    </select>

    <select name="module">
        <option value="">All modules</option>
        @foreach($modules as $m)
            <option value="{{ $m }}" @selected(request('module') === $m)>{{ ucfirst(str_replace('_', ' ', $m)) }}</option>
        @endforeach
    </select>

    <select name="action">
        <option value="">All actions</option>
        @foreach($actions as $a)
            <option value="{{ $a }}" @selected(request('action') === $a)>{{ ucfirst(str_replace('_', ' ', $a)) }}</option>
        @endforeach
    </select>

    <input type="date" name="date_from" value="{{ request('date_from') }}" title="From date">
    <input type="date" name="date_to" value="{{ request('date_to') }}" title="To date">

    <button type="submit" class="al-filter-btn">Filter</button>
    @if(request()->anyFilled(['user_id', 'module', 'action', 'date_from', 'date_to']))
        <a href="{{ route('settings.activity-log') }}" class="al-filter-clear">Clear</a>
    @endif
</form>

{{-- ── TABLE ── --}}
<div style="flex:1;overflow-y:auto;">
    @if($logs->isEmpty())
        <div class="al-empty">No activity recorded for these filters.</div>
    @else
        <table class="al-table">
            <thead>
                <tr>
                    <th>When</th>
                    <th>Staff</th>
                    <th>Action</th>
                    <th>Module</th>
                    <th>What changed</th>
                    <th>Device</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                <tr>
                    <td style="white-space:nowrap;">{{ $log->created_at->format('d M, h:i A') }}</td>
                    <td style="white-space:nowrap;">{{ $log->user->name ?? 'System' }}</td>
                    <td>
                        @php
                        $chipClass = match(true) {
                            $log->action === 'created' => 'al-chip--created',
                            $log->action === 'updated' => 'al-chip--updated',
                            $log->action === 'deleted' => 'al-chip--deleted',
                            default => 'al-chip--security',
                        };
                        @endphp
                        <span class="al-chip {{ $chipClass }}">{{ str_replace('_', ' ', $log->action) }}</span>
                    </td>
                    <td style="white-space:nowrap;color:#7a6080;">{{ $log->module ? ucfirst(str_replace('_', ' ', $log->module)) : '—' }}</td>
                    <td style="max-width:420px;">{{ $log->summary }}</td>
                    <td>
                        <div class="al-device">{{ $log->device_type ?? '—' }}</div>
                        <div style="color:#c5a8d8;font-size:10px;">{{ $log->ip_address }}</div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding:16px 28px;">{{ $logs->links() }}</div>
    @endif

    {{-- ── STAFF ACCOUNT CHANGES (separate, narrower log) ── --}}
    <div class="al-panel">
        <h3 style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#6a0f70;margin:0 0 4px;">Staff Account Changes</h3>
        <p style="font-size:12px;color:#9a7aaa;margin:0 0 14px;">Activations, deactivations, and role changes on staff accounts — most recent 25.</p>

        @if($staffChanges->isEmpty())
            <div style="font-size:12.5px;color:#b09ac0;">No staff account changes recorded.</div>
        @else
            <table class="al-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>By</th>
                        <th>Staff Member</th>
                        <th>Action</th>
                        <th>Change</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($staffChanges as $sc)
                    <tr>
                        <td style="white-space:nowrap;">{{ $sc->created_at->format('d M, h:i A') }}</td>
                        <td style="white-space:nowrap;">{{ $sc->performer->name ?? 'System' }}</td>
                        <td style="white-space:nowrap;">{{ $sc->user->name ?? '—' }}</td>
                        <td><span class="al-chip al-chip--updated">{{ $sc->actionLabel() }}</span></td>
                        <td>
                            @if($sc->old_value || $sc->new_value)
                                {{ $sc->old_value ?? '—' }} → {{ $sc->new_value ?? '—' }}
                            @else
                                {{ $sc->note ?? '—' }}
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>

</div>
@endsection
