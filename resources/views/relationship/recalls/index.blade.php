{{--
|==========================================================================
| PRE — Recall Pipeline (Phase 1 · Workstream D, slice 3)
| Route: GET /relationship/recalls   [relationship.recalls]
|
| Read-only. Recall rows from the legacy communication_queue, grouped by the
| RELIABLE legacy status. Additive — the legacy Communication List is untouched.
| Variables from RecallPipelineController@index:
|   $columns, $total, $openCount, $overdueCount
|==========================================================================
--}}
@extends('layouts.app')

@section('page-title', 'Recall Pipeline')

@section('content')
<div style="max-width:1400px;margin:0 auto;padding:8px 4px 40px;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px;">
        <div>
            <h1 style="margin:0;font-size:22px;font-weight:700;color:#1f2937;">Recall Pipeline</h1>
            <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">
                Patients due to return, by status.
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('relationship.dashboard') }}"
               style="background:#EEEDFE;color:#534AB7;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
               ← Relationships
            </a>
            <a href="{{ route('relationship.pipeline') }}"
               style="background:#EEEDFE;color:#534AB7;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
               Leads
            </a>
            <a href="{{ route('relationship.opportunities') }}"
               style="background:#EEEDFE;color:#534AB7;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
               Opportunities
            </a>
        </div>
    </div>

    {{-- Headline stats --}}
    <div style="display:flex;gap:22px;flex-wrap:wrap;margin-bottom:20px;color:#6b7280;font-size:13px;">
        <span>Total recalls: <strong style="color:#1f2937;">{{ number_format($total) }}</strong></span>
        <span>Open: <strong style="color:#1f2937;">{{ number_format($openCount) }}</strong></span>
        <span>Overdue: <strong style="color:{{ $overdueCount > 0 ? '#8A1F1F' : '#1f2937' }};">{{ number_format($overdueCount) }}</strong></span>
    </div>

    {{-- Kanban board (horizontal scroll) --}}
    <div style="display:flex;gap:14px;overflow-x:auto;padding-bottom:12px;align-items:flex-start;">
        @foreach ($columns as $col)
            <div style="flex:0 0 264px;width:264px;background:#f7f8fa;border:1px solid #eceef2;border-radius:12px;">

                {{-- Column header --}}
                <div style="padding:12px 14px;border-bottom:1px solid #eceef2;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;">
                        <span style="display:inline-flex;align-items:center;gap:7px;font-weight:700;font-size:13px;color:{{ $col['color'] }};">
                            <span style="width:9px;height:9px;border-radius:999px;background:{{ $col['color'] }};display:inline-block;"></span>
                            {{ $col['label'] }}
                        </span>
                        <span style="background:{{ $col['bg'] }};color:{{ $col['color'] }};font-size:11.5px;font-weight:700;padding:2px 9px;border-radius:999px;">
                            {{ $col['count'] }}
                        </span>
                    </div>
                </div>

                {{-- Cards --}}
                <div style="padding:10px;display:flex;flex-direction:column;gap:9px;min-height:60px;">
                    @forelse ($col['items'] as $recall)
                        @php
                            $due       = $recall->follow_up_date ?? $recall->due_at;
                            $isOverdue = ($recall->is_overdue || $recall->status === 'overdue') && $recall->status !== 'closed';
                        @endphp

                        <div style="background:#fff;border:1px solid #edeef1;border-radius:10px;padding:11px 12px;">
                            <div style="font-weight:600;font-size:13px;color:#1f2937;">
                                {{ $recall->person_name ?: 'Unnamed' }}
                            </div>

                            {{-- Meta --}}
                            <div style="margin-top:5px;color:#6b7280;font-size:11.5px;line-height:1.55;">
                                @if ($recall->phone)<div>{{ $recall->phone }}</div>@endif
                                @if ($recall->channel)<div>{{ \App\Models\CommunicationQueue::CHANNELS[$recall->channel] ?? ucfirst($recall->channel) }}</div>@endif
                            </div>

                            {{-- Chips --}}
                            <div style="margin-top:7px;display:flex;flex-wrap:wrap;gap:5px;">
                                @if ($due)
                                    <span style="font-size:10.5px;padding:2px 7px;border-radius:999px;
                                        background:{{ $isOverdue ? '#FDECEC' : '#f0f1f3' }};
                                        color:{{ $isOverdue ? '#8A1F1F' : '#6b7280' }};">
                                        {{ $isOverdue ? 'Overdue · ' : '' }}{{ \Illuminate\Support\Carbon::parse($due)->format('d M') }}
                                    </span>
                                @endif
                                @if (($recall->attempt_count ?? 0) > 0)
                                    <span style="font-size:10.5px;padding:2px 7px;border-radius:999px;background:#f0f1f3;color:#6b7280;">
                                        {{ $recall->attempt_count }} attempt{{ $recall->attempt_count > 1 ? 's' : '' }}
                                    </span>
                                @endif
                                @if ($recall->assigned_to)
                                    <span style="font-size:10.5px;padding:2px 7px;border-radius:999px;background:#EEEDFE;color:#534AB7;">
                                        {{ $recall->assigned_to }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div style="color:#c2c6cd;font-size:12px;text-align:center;padding:14px 0;">No recalls</div>
                    @endforelse

                    @if ($col['hidden'] > 0)
                        <div style="color:#9ca3af;font-size:11.5px;text-align:center;padding:4px 0;">
                            +{{ $col['hidden'] }} more
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <p style="margin-top:16px;color:#9ca3af;font-size:12px;">
        PRE (Relationship Platform) · read-only. Recalls are read from the legacy communication queue
        (grouped by the reliable legacy status). The legacy Communication List remains available and unchanged.
    </p>
</div>
@endsection
