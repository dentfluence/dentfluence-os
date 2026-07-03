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
<div style="max-width:1100px;margin:0 auto;padding:8px 4px 40px;">

    {{-- Header --}}
    <div style="margin-bottom:20px;">
        <h1 style="margin:0;font-size:22px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Relationships</h1>
        <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">Everyone your clinic knows — one record per person.</p>
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

    {{-- Tier 1 — headline stat cards (white, matching the Inventory dashboard style) --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:14px;">
        @php
            $cards = [
                ['label' => 'Relationships',      'value' => $stats['relationships'],      'color' => '#534AB7'],
                ['label' => 'Patients',           'value' => $stats['patients'],           'color' => '#0F6E56'],
                ['label' => 'Active Leads',       'value' => $stats['active_leads'],       'color' => '#854F0B'],
                ['label' => 'Open Opportunities', 'value' => $stats['open_opportunities'], 'color' => '#185FA5'],
            ];
        @endphp
        @foreach ($cards as $c)
            <div style="background:#fff;border:1px solid rgba(83,74,183,0.12);border-radius:10px;padding:16px 18px;">
                <div style="font-size:28px;font-weight:800;color:{{ $c['color'] }};line-height:1;">{{ number_format($c['value']) }}</div>
                <div style="margin-top:6px;color:#4b5563;font-size:13px;font-weight:600;">{{ $c['label'] }}</div>
            </div>
        @endforeach
    </div>

    {{-- Tier 2 — journey snapshot (shadow, informational; tinted like Inventory's second row) --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:26px;">
        <div style="background:#F5F3FF;border-radius:10px;padding:14px 18px;">
            <div style="font-size:20px;font-weight:800;color:#5b21b6;line-height:1;">{{ number_format($journeys['lead']) }}</div>
            <div style="margin-top:5px;color:#4b5563;font-size:12.5px;">Open Lead Journeys</div>
        </div>
        <div style="background:#EFF6FF;border-radius:10px;padding:14px 18px;">
            <div style="font-size:20px;font-weight:800;color:#1e40af;line-height:1;">{{ number_format($journeys['opportunity']) }}</div>
            <div style="margin-top:5px;color:#4b5563;font-size:12.5px;">Open Opportunity Journeys</div>
        </div>
    </div>

    {{-- Quick actions — just the two lead-entry forms. Pipeline/Opportunities/Recalls
         dropped from here since they're already tabs at the top (was duplicate nav). --}}
    <div style="margin-bottom:26px;">
        <div style="font-size:12.5px;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.04em;margin-bottom:10px;">Quick Actions</div>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;">
            @php
                $quickActions = [
                    ['route' => 'relationship.pipeline.quick-add', 'label' => 'Quick Add Lead', 'icon' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>'],
                    ['route' => 'relationship.pipeline.add-lead',  'label' => 'New Lead (Full)', 'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
                ];
            @endphp
            @foreach ($quickActions as $qa)
            <a href="{{ route($qa['route']) }}"
               style="display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 8px;background:#fff;border:1px solid #eceef2;border-radius:10px;text-decoration:none;color:#4b5563;font-size:12px;font-weight:600;text-align:center;transition:background .15s,color .15s,transform .15s;"
               onmouseover="this.style.background='#f9f3fa';this.style.color='#534AB7';this.style.transform='translateY(-1px)';"
               onmouseout="this.style.background='#fff';this.style.color='#4b5563';this.style.transform='translateY(0)';">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $qa['icon'] !!}</svg>
                {{ $qa['label'] }}
            </a>
            @endforeach
        </div>
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

