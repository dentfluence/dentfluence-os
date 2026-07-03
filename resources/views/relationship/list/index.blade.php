{{--
|==========================================================================
| PRE — Relationships index (Phase 1 · Workstream D, slice 5)
| Route: GET /relationship/list   [relationship.list]
|
| Searchable / filterable / paginated browse. Read-only.
| Variables from RelationshipListController@index:
|   $relationships (paginator), $q, $status, $has, $sort, $dir, $total
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', 'All Relationships')

@php
    // Current filter state, minus defaults, for building links.
    $base = array_filter([
        'q'      => $q ?: null,
        'status' => $status,
        'has'    => $has,
        'sort'   => $sort !== 'created_at' ? $sort : null,
        'dir'    => $dir !== 'desc' ? $dir : null,
    ], fn ($v) => $v !== null && $v !== '');

    $link = function (array $over) use ($base) {
        $merged = array_filter(array_merge($base, $over), fn ($v) => $v !== null && $v !== '');
        return route('relationship.list', $merged);
    };

    $chip = function (string $label, string $url, bool $active) {
        $bg = $active ? '#534AB7' : '#EEEDFE';
        $fg = $active ? '#fff' : '#534AB7';
        return "<a href=\"{$url}\" style=\"background:{$bg};color:{$fg};padding:6px 13px;border-radius:999px;text-decoration:none;font-size:12.5px;font-weight:600;\">{$label}</a>";
    };
@endphp

@section('relationship-content')
<div style="max-width:1100px;margin:0 auto;padding:8px 4px 40px;">

    {{-- Header --}}
    <div style="margin-bottom:16px;">
        <h1 style="margin:0;font-size:22px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">All Relationships</h1>
        <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">{{ number_format($total) }} people your clinic knows — search and filter the whole base.</p>
    </div>

    {{-- Search --}}
    <form method="GET" action="{{ route('relationship.list') }}" style="margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;">
        <input type="text" name="q" value="{{ $q }}" placeholder="Search name, phone or email…"
               style="flex:1;min-width:240px;padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;">
        @if ($status)<input type="hidden" name="status" value="{{ $status }}">@endif
        @if ($has)<input type="hidden" name="has" value="{{ $has }}">@endif
        <button type="submit" style="background:#534AB7;color:#fff;border:none;padding:10px 20px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">Search</button>
        @if ($q !== '' || $status || $has)
            <a href="{{ route('relationship.list') }}" style="align-self:center;color:#9ca3af;font-size:12.5px;text-decoration:none;">Clear</a>
        @endif
    </form>

    {{-- Filter chips --}}
    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:18px;align-items:center;">
        <span style="color:#9ca3af;font-size:12px;font-weight:600;">Status:</span>
        {!! $chip('Active',  $link(['status' => $status === 'active'  ? null : 'active']),  $status === 'active') !!}
        {!! $chip('Dormant', $link(['status' => $status === 'dormant' ? null : 'dormant']), $status === 'dormant') !!}
        {!! $chip('Lost',    $link(['status' => $status === 'lost'    ? null : 'lost']),    $status === 'lost') !!}
        <span style="width:1px;height:18px;background:#e5e7eb;margin:0 4px;"></span>
        {!! $chip('Has lead',    $link(['has' => $has === 'lead'    ? null : 'lead']),    $has === 'lead') !!}
        {!! $chip('Has patient', $link(['has' => $has === 'patient' ? null : 'patient']), $has === 'patient') !!}
    </div>

    {{-- Table --}}
    <div style="background:#fff;border:1px solid #eceef2;border-radius:12px;overflow:hidden;">
        @if ($relationships->isEmpty())
            <div style="padding:26px 18px;color:#9ca3af;font-size:13px;">No relationships match your search.</div>
        @else
            @php
                $head = function (string $label, string $col) use ($link, $sort, $dir) {
                    $isActive = $sort === $col;
                    $nextDir  = ($isActive && $dir === 'asc') ? 'desc' : 'asc';
                    $arrow    = $isActive ? ($dir === 'asc' ? ' ▲' : ' ▼') : '';
                    $url      = $link(['sort' => $col, 'dir' => $nextDir]);
                    return "<a href=\"{$url}\" style=\"color:#6b7280;text-decoration:none;font-weight:600;\">{$label}{$arrow}</a>";
                };
            @endphp
            <table style="width:100%;border-collapse:collapse;font-size:13px;">
                <thead>
                    <tr style="text-align:left;color:#6b7280;background:#fafbfc;">
                        <th style="padding:10px 18px;">{!! $head('Name', 'name') !!}</th>
                        <th style="padding:10px 18px;font-weight:600;">Phone</th>
                        <th style="padding:10px 18px;font-weight:600;">Status</th>
                        <th style="padding:10px 18px;">{!! $head('Score', 'score') !!}</th>
                        <th style="padding:10px 18px;">{!! $head('Since', 'relationship_since') !!}</th>
                        <th style="padding:10px 18px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($relationships as $r)
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
                                <a href="{{ route('relationship.profile', $r->id) }}" style="color:#534AB7;text-decoration:none;font-weight:600;">View →</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Pagination --}}
    @if ($relationships->hasPages() || $relationships->total() > 0)
        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:14px;flex-wrap:wrap;">
            <span style="color:#9ca3af;font-size:12.5px;">
                Showing {{ $relationships->firstItem() ?? 0 }}–{{ $relationships->lastItem() ?? 0 }} of {{ number_format($relationships->total()) }}
            </span>
            <div style="display:flex;gap:8px;">
                @if ($relationships->previousPageUrl())
                    <a href="{{ $relationships->previousPageUrl() }}" style="background:#EEEDFE;color:#534AB7;padding:7px 14px;border-radius:8px;text-decoration:none;font-size:12.5px;font-weight:600;">← Prev</a>
                @endif
                @if ($relationships->nextPageUrl())
                    <a href="{{ $relationships->nextPageUrl() }}" style="background:#EEEDFE;color:#534AB7;padding:7px 14px;border-radius:8px;text-decoration:none;font-size:12.5px;font-weight:600;">Next →</a>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
