{{--
|==========================================================================
| PRE — Relationship Dashboard (Phase 1 · Workstream D, slice 1)
| Route: GET /relationship/dashboard   [relationship.dashboard]
|
| Read-only overview. Additive — does not replace the legacy PRM board.
| Variables from DashboardController@index: $stats, $journeys, $recent
|==========================================================================
--}}
@extends('layouts.app')

@section('page-title', 'Relationships')

@section('content')
<div style="max-width:1100px;margin:0 auto;padding:8px 4px 40px;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:20px;">
        <div>
            <h1 style="margin:0;font-size:22px;font-weight:700;color:#1f2937;">Relationships</h1>
            <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">Everyone your clinic knows — one record per person.</p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('relationship.list') }}"
               style="background:#EEEDFE;color:#534AB7;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
               All relationships
            </a>
            <a href="{{ route('relationship.today') }}"
               style="background:#534AB7;color:#fff;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
               Today's Actions
            </a>
            <a href="{{ route('relationship.analytics') }}"
               style="background:#EEEDFE;color:#534AB7;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
               Analytics
            </a>
        </div>
    </div>

    {{-- PRE-scoped search (searches relationships only; results open PRE profiles) --}}
    <form method="GET" action="{{ route('relationship.list') }}" style="margin-bottom:22px;display:flex;gap:8px;flex-wrap:wrap;">
        <div style="position:relative;flex:1;min-width:260px;">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round"
                 style="position:absolute;left:13px;top:50%;transform:translateY(-50%);"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" name="q" placeholder="Search relationships by name, phone or email…"
                   style="width:100%;padding:11px 14px 11px 38px;border:1px solid #e5e7eb;border-radius:10px;font-size:13px;">
        </div>
        <button type="submit" style="background:#534AB7;color:#fff;border:none;padding:11px 22px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;">Search</button>
    </form>

    {{-- Stat cards --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:22px;">
        @php
            $cards = [
                ['label' => 'Relationships',      'value' => $stats['relationships'],      'color' => '#534AB7', 'bg' => '#EEEDFE'],
                ['label' => 'Patients',           'value' => $stats['patients'],           'color' => '#0F6E56', 'bg' => '#E1F5EE'],
                ['label' => 'Active leads',       'value' => $stats['active_leads'],       'color' => '#854F0B', 'bg' => '#FAEEDA'],
                ['label' => 'Open opportunities', 'value' => $stats['open_opportunities'], 'color' => '#185FA5', 'bg' => '#E6F1FB'],
            ];
        @endphp
        @foreach ($cards as $c)
            <div style="background:{{ $c['bg'] }};border-radius:12px;padding:16px 18px;">
                <div style="font-size:28px;font-weight:800;color:{{ $c['color'] }};line-height:1;">{{ number_format($c['value']) }}</div>
                <div style="margin-top:6px;color:#4b5563;font-size:13px;font-weight:600;">{{ $c['label'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Journey snapshot (shadow, informational) --}}
    <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:26px;color:#6b7280;font-size:12.5px;">
        <span>Open lead journeys: <strong style="color:#374151;">{{ number_format($journeys['lead']) }}</strong></span>
        <span>Open opportunity journeys: <strong style="color:#374151;">{{ number_format($journeys['opportunity']) }}</strong></span>
    </div>

    {{-- Recent relationships --}}
    <div style="background:#fff;border:1px solid #eceef2;border-radius:12px;overflow:hidden;">
        <div style="padding:14px 18px;border-bottom:1px solid #f1f2f4;font-weight:700;color:#1f2937;font-size:14px;">
            Recent relationships
        </div>

        @if ($recent->isEmpty())
            <div style="padding:24px 18px;color:#9ca3af;font-size:13px;">No relationships yet.</div>
        @else
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="text-align:left;color:#6b7280;background:#fafbfc;">
                        <th style="padding:10px 18px;font-weight:600;">Name</th>
                        <th style="padding:10px 18px;font-weight:600;">Phone</th>
                        <th style="padding:10px 18px;font-weight:600;">Status</th>
                        <th style="padding:10px 18px;font-weight:600;">Score</th>
                        <th style="padding:10px 18px;font-weight:600;">Since</th>
                        <th style="padding:10px 18px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($recent as $r)
                        <tr style="border-top:1px solid #f4f5f7;">
                            <td style="padding:11px 18px;font-weight:600;color:#1f2937;">{{ $r->name }}</td>
                            <td style="padding:11px 18px;color:#4b5563;">{{ $r->phone ?: '—' }}</td>
                            <td style="padding:11px 18px;">
                                <span style="font-size:11.5px;padding:2px 8px;border-radius:999px;
                                    background:{{ $r->status === 'active' ? '#E1F5EE' : '#f3f4f6' }};
                                    color:{{ $r->status === 'active' ? '#0F6E56' : '#6b7280' }};">
                                    {{ ucfirst($r->status ?? 'active') }}
                                </span>
                            </td>
                            <td style="padding:11px 18px;color:#4b5563;">{{ $r->score ?? 0 }}</td>
                            <td style="padding:11px 18px;color:#6b7280;">{{ optional($r->relationship_since)->format('d M Y') ?? '—' }}</td>
                            <td style="padding:11px 18px;text-align:right;">
                                <a href="{{ route('relationship.profile', $r->id) }}"
                                   style="color:#534AB7;text-decoration:none;font-weight:600;">View →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <p style="margin-top:16px;color:#9ca3af;font-size:12px;">
        PRE (Relationship Platform) · the legacy PRM board remains available and unchanged.
    </p>
</div>
@endsection
