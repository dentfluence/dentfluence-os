{{--
|==========================================================================
| PRE — Relationship Dashboard (Phase 1 · Workstream D, slice 1)
| Route: GET /relationship/dashboard   [relationship.dashboard]
|
| Read-only overview. Additive — does not replace the legacy PRM board.
| Variables from DashboardController@index: $stats, $journeys, $recent
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', 'Relationships')

@section('relationship-content')
<style>
    /* Squeeze this page into one non-scrolling viewport, same technique as Analytics. */
    #df-content-inner { padding: 10px 24px 6px !important; }
    .db-card {
        display: block; text-decoration: none; transition: transform .12s, box-shadow .12s;
    }
    .db-card:hover { transform: translateY(-1px); box-shadow: 0 4px 14px rgba(83,74,183,0.10); }
    .db-recent-row:hover { background: #fdf9ff; }
</style>
<div style="max-width:1100px;margin:0 auto;padding:4px 4px 8px;">

    {{-- Header + search on one row to save vertical space --}}
    <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:10px;flex-wrap:wrap;">
        <div>
            <h1 style="margin:0;font-size:19px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Relationships</h1>
            <p style="margin:2px 0 0;color:#6b7280;font-size:12px;">Everyone your clinic knows — one record per person.</p>
        </div>
        <form method="GET" action="{{ route('relationship.list') }}" style="display:flex;gap:8px;flex:1;max-width:460px;min-width:240px;">
            <div style="position:relative;flex:1;">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9ca3af" stroke-width="2" stroke-linecap="round"
                     style="position:absolute;left:11px;top:50%;transform:translateY(-50%);"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
                <input type="text" name="q" placeholder="Search by name, phone or email…"
                       style="width:100%;box-sizing:border-box;padding:8px 12px 8px 32px;border:1px solid #e5e7eb;border-radius:8px;font-size:12.5px;">
            </div>
            <button type="submit" style="background:#534AB7;color:#fff;border:none;padding:8px 16px;border-radius:8px;font-size:12.5px;font-weight:600;cursor:pointer;">Search</button>
        </form>
    </div>

    {{-- Headline stat cards — clickable, each opens the relevant list --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-bottom:8px;">
        @php
            $cards = [
                ['label' => 'Relationships',      'value' => $stats['relationships'],      'color' => '#534AB7', 'route' => 'relationship.list'],
                ['label' => 'Patients',           'value' => $stats['patients'],           'color' => '#0F6E56', 'route' => 'patients.index'],
                ['label' => 'Active Leads',       'value' => $stats['active_leads'],       'color' => '#854F0B', 'route' => 'relationship.pipeline'],
                ['label' => 'Open Opportunities', 'value' => $stats['open_opportunities'], 'color' => '#185FA5', 'route' => 'relationship.opportunities'],
            ];
        @endphp
        @foreach ($cards as $c)
            <a href="{{ route($c['route']) }}" class="db-card" style="background:#fff;border:1px solid rgba(83,74,183,0.12);border-radius:10px;padding:12px 16px;">
                <div style="font-size:23px;font-weight:800;color:{{ $c['color'] }};line-height:1;">{{ number_format($c['value']) }}</div>
                <div style="margin-top:5px;color:#4b5563;font-size:12px;font-weight:600;">{{ $c['label'] }}</div>
            </a>
        @endforeach
    </div>

    {{-- Second row: shadow journey context (left) + two glance metrics Dashboard
         didn't surface before (right). Previously this row's right side was
         "Quick Add Lead" / "New Lead (Full)" — both just different ways to add
         a lead, and the Pipeline tab already has both of those buttons, so they
         added nothing here. High Priority / Open Recalls instead give a reason
         to glance at THIS page daily, without duplicating another tab's job. --}}
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;gap:10px;margin-bottom:10px;">
        <a href="{{ route('relationship.pipeline') }}" class="db-card" style="background:#F5F3FF;border-radius:10px;padding:10px 16px;">
            <div style="font-size:17px;font-weight:800;color:#5b21b6;line-height:1;">{{ number_format($journeys['lead']) }}</div>
            <div style="margin-top:4px;color:#4b5563;font-size:11.5px;">Open Lead Journeys</div>
        </a>
        <a href="{{ route('relationship.opportunities') }}" class="db-card" style="background:#EFF6FF;border-radius:10px;padding:10px 16px;">
            <div style="font-size:17px;font-weight:800;color:#1e40af;line-height:1;">{{ number_format($journeys['opportunity']) }}</div>
            <div style="margin-top:4px;color:#4b5563;font-size:11.5px;">Open Opportunity Journeys</div>
        </a>
        <a href="{{ route('relationship.today') }}" class="db-card" style="background:{{ $highPriorityToday > 0 ? '#FDECEC' : '#F4F4F5' }};border-radius:10px;padding:10px 16px;">
            <div style="font-size:17px;font-weight:800;color:{{ $highPriorityToday > 0 ? '#8A1F1F' : '#4b5563' }};line-height:1;">{{ number_format($highPriorityToday) }}</div>
            <div style="margin-top:4px;color:#4b5563;font-size:11.5px;">High Priority Today</div>
        </a>
        <a href="{{ route('relationship.recalls') }}" class="db-card" style="background:#FFF7ED;border-radius:10px;padding:10px 16px;">
            <div style="font-size:17px;font-weight:800;color:#9a3412;line-height:1;">{{ number_format($openRecalls) }}</div>
            <div style="margin-top:4px;color:#4b5563;font-size:11.5px;">Open Recalls</div>
        </a>
    </div>

    {{-- Recent relationships — fixed height, scrolls internally so the page itself doesn't --}}
    <div style="background:#fff;border:1px solid #eceef2;border-radius:12px;overflow:hidden;">
        <div style="padding:10px 16px;border-bottom:1px solid #f1f2f4;font-weight:700;color:#1f2937;font-size:13px;">
            Recent relationships
        </div>

        @if ($recent->isEmpty())
            <div style="padding:20px 16px;color:#9ca3af;font-size:13px;">No relationships yet.</div>
        @else
            <div style="max-height:260px;overflow-y:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
                    <thead>
                        <tr style="text-align:left;color:#6b7280;background:#fafbfc;position:sticky;top:0;">
                            <th style="padding:7px 16px;font-weight:600;">Name</th>
                            <th style="padding:7px 16px;font-weight:600;">Phone</th>
                            <th style="padding:7px 16px;font-weight:600;">Status</th>
                            <th style="padding:7px 16px;font-weight:600;">Score</th>
                            <th style="padding:7px 16px;font-weight:600;">Since</th>
                            <th style="padding:7px 16px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($recent as $r)
                            <tr class="db-recent-row" onclick="window.location='{{ route('relationship.profile', $r->id) }}'"
                                title="Open {{ $r->name }}'s profile"
                                style="border-top:1px solid #f4f5f7;cursor:pointer;">
                                <td style="padding:8px 16px;font-weight:600;color:#1f2937;">{{ $r->name }}</td>
                                <td style="padding:8px 16px;color:#4b5563;">{{ $r->phone ?: '—' }}</td>
                                <td style="padding:8px 16px;">
                                    <span style="font-size:11px;padding:2px 8px;border-radius:999px;
                                        background:{{ $r->status === 'active' ? '#E1F5EE' : '#f3f4f6' }};
                                        color:{{ $r->status === 'active' ? '#0F6E56' : '#6b7280' }};">
                                        {{ ucfirst($r->status ?? 'active') }}
                                    </span>
                                </td>
                                <td style="padding:8px 16px;color:#4b5563;">{{ $r->score ?? 0 }}</td>
                                <td style="padding:8px 16px;color:#6b7280;">{{ optional($r->relationship_since)->format('d M Y') ?? '—' }}</td>
                                <td style="padding:8px 16px;text-align:right;">
                                    <span style="color:#534AB7;font-weight:600;">View →</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection

