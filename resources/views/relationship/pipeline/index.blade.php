{{--
|==========================================================================
| PRE — Lead Pipeline (Phase 1 · Workstream D, slice 2)
| Route: GET /relationship/pipeline   [relationship.pipeline]
|
| Read-only, relationship-centric lead board. Grouped by the RELIABLE legacy
| leads.stage column. Additive — the legacy PRM board is untouched.
| Variables from LeadPipelineController@index:
|   $columns, $totalLeads, $activeCount, $pipelineValue,
|   $showJourney, $journeyByRelationship
|==========================================================================
--}}
@extends('layouts.app')

@section('page-title', 'Lead Pipeline')

@section('content')
<div style="max-width:1400px;margin:0 auto;padding:8px 4px 40px;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px;">
        <div>
            <h1 style="margin:0;font-size:22px;font-weight:700;color:#1f2937;">Lead Pipeline</h1>
            <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">
                Every active enquiry, grouped by stage. Relationship-first view.
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <a href="{{ route('relationship.dashboard') }}"
               style="background:#EEEDFE;color:#534AB7;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
               ← Relationships
            </a>
            <a href="{{ route('relationship.today') }}"
               style="background:#534AB7;color:#fff;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
               Today's Actions
            </a>
            @if (! empty($prmSecondary))
                <a href="{{ route('prm.board', ['legacy' => 1]) }}"
                   style="background:#fff;color:#6b7280;border:1px solid #e5e7eb;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
                   Legacy PRM board
                </a>
            @endif
        </div>
    </div>

    {{-- Headline stats --}}
    <div style="display:flex;gap:22px;flex-wrap:wrap;margin-bottom:20px;color:#6b7280;font-size:13px;">
        <span>Total leads: <strong style="color:#1f2937;">{{ number_format($totalLeads) }}</strong></span>
        <span>Active: <strong style="color:#1f2937;">{{ number_format($activeCount) }}</strong></span>
        <span>Open pipeline value: <strong style="color:#1f2937;">₹{{ number_format($pipelineValue) }}</strong></span>
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
                    @if ($col['value'] > 0)
                        <div style="margin-top:5px;color:#9ca3af;font-size:11.5px;">₹{{ number_format($col['value']) }} value</div>
                    @endif
                </div>

                {{-- Cards --}}
                <div style="padding:10px;display:flex;flex-direction:column;gap:9px;min-height:60px;">
                    @forelse ($col['leads'] as $lead)
                        @php
                            $journeyState = $showJourney && $lead->relationship_id
                                ? ($journeyByRelationship[$lead->relationship_id] ?? null)
                                : null;
                            $isOverdue = $lead->followup_date
                                && $lead->followup_date->isPast()
                                && ! in_array($lead->stage, ['converted', 'lost'], true);
                        @endphp

                        <div style="background:#fff;border:1px solid #edeef1;border-radius:10px;padding:11px 12px;">
                            {{-- Name (links to PRE profile when linked) --}}
                            @if ($lead->relationship_id)
                                <a href="{{ route('relationship.profile', $lead->relationship_id) }}"
                                   style="font-weight:600;font-size:13px;color:#1f2937;text-decoration:none;">
                                    {{ $lead->name ?: 'Unnamed lead' }}
                                </a>
                            @else
                                <span style="font-weight:600;font-size:13px;color:#1f2937;">{{ $lead->name ?: 'Unnamed lead' }}</span>
                            @endif

                            {{-- Meta --}}
                            <div style="margin-top:5px;color:#6b7280;font-size:11.5px;line-height:1.55;">
                                @if ($lead->treatment)<div>{{ $lead->treatment }}</div>@endif
                                @if ($lead->phone)<div>{{ $lead->phone }}</div>@endif
                                @if (! is_null($lead->lead_value) && (float) $lead->lead_value > 0)
                                    <div>₹{{ number_format((float) $lead->lead_value) }}</div>
                                @endif
                            </div>

                            {{-- Follow-up + assignee chips --}}
                            <div style="margin-top:7px;display:flex;flex-wrap:wrap;gap:5px;">
                                @if ($lead->followup_date)
                                    <span style="font-size:10.5px;padding:2px 7px;border-radius:999px;
                                        background:{{ $isOverdue ? '#FDECEC' : '#f0f1f3' }};
                                        color:{{ $isOverdue ? '#8A1F1F' : '#6b7280' }};">
                                        {{ $isOverdue ? 'Overdue · ' : '' }}{{ $lead->followup_date->format('d M') }}
                                    </span>
                                @endif
                                @if ($lead->assigned_to)
                                    <span style="font-size:10.5px;padding:2px 7px;border-radius:999px;background:#EEEDFE;color:#534AB7;">
                                        {{ $lead->assigned_to }}
                                    </span>
                                @endif
                            </div>

                            {{-- Shadow journey state (flag-gated, context only) --}}
                            @if ($journeyState)
                                <div style="margin-top:8px;padding-top:7px;border-top:1px dashed #eceef2;color:#9ca3af;font-size:10.5px;">
                                    Journey (shadow): <strong style="color:#6b7280;">{{ str_replace('_', ' ', $journeyState) }}</strong>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div style="color:#c2c6cd;font-size:12px;text-align:center;padding:14px 0;">No leads</div>
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
        PRE (Relationship Platform) · read-only. Columns use the reliable legacy stage.
        @if ($showJourney)
            Shadow journey state shown for context only.
        @else
            Shadow journey state is hidden (flag <code>relationship.pipeline_journey_column</code> is off).
        @endif
        The legacy PRM board remains available and unchanged.
    </p>
</div>
@endsection
