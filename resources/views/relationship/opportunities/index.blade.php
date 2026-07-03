{{--
|==========================================================================
| PRE — Opportunity Pipeline (Phase 1 · Workstream D, slice 3)
| Route: GET /relationship/opportunities   [relationship.opportunities]
|
| Read-only. Grouped by the RELIABLE legacy treatment_opportunities.status.
| Additive — the legacy Communication / Opportunity surfaces are untouched.
| Variables from OpportunityPipelineController@index:
|   $columns, $total, $openCount, $pipelineValue,
|   $showJourney, $journeyByOpportunity
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', 'Opportunity Pipeline')

@section('relationship-content')
<div style="max-width:1400px;margin:0 auto;padding:8px 4px 40px;">

    {{-- Header --}}
    <div style="margin-bottom:18px;">
        <h1 style="margin:0;font-size:22px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Opportunity Pipeline</h1>
        <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">
            Treatment opportunities by stage. Relationship-first view.
        </p>
    </div>

    {{-- Headline stats --}}
    <div style="display:flex;gap:22px;flex-wrap:wrap;margin-bottom:20px;color:#6b7280;font-size:13px;">
        <span>Total: <strong style="color:#1f2937;">{{ number_format($total) }}</strong></span>
        <span>Open: <strong style="color:#1f2937;">{{ number_format($openCount) }}</strong></span>
        <span>Open estimated value: <strong style="color:#1f2937;">₹{{ number_format($pipelineValue) }}</strong></span>
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
                    @forelse ($col['items'] as $opp)
                        @php
                            $rel          = $opp->relationship;
                            $personName   = $rel->name ?? 'Unlinked opportunity';
                            $journeyState = $showJourney ? ($journeyByOpportunity[$opp->id] ?? null) : null;
                            $isOverdue    = $opp->follow_up_date
                                && $opp->follow_up_date->isPast()
                                && ! in_array($opp->status, ['completed', 'declined'], true);
                        @endphp

                        <div style="background:#fff;border:1px solid #edeef1;border-radius:10px;padding:11px 12px;">
                            {{-- Person (links to PRE profile when linked) --}}
                            @if ($opp->relationship_id)
                                <a href="{{ route('relationship.profile', $opp->relationship_id) }}"
                                   style="font-weight:600;font-size:13px;color:#1f2937;text-decoration:none;">
                                    {{ $personName }}
                                </a>
                            @else
                                <span style="font-weight:600;font-size:13px;color:#1f2937;">{{ $personName }}</span>
                            @endif

                            {{-- Treatment + value --}}
                            <div style="margin-top:5px;color:#6b7280;font-size:11.5px;line-height:1.55;">
                                <div>{{ $opp->display_label }}</div>
                                @if (! is_null($opp->estimated_value) && (float) $opp->estimated_value > 0)
                                    <div>₹{{ number_format((float) $opp->estimated_value) }}</div>
                                @endif
                            </div>

                            {{-- Chips --}}
                            <div style="margin-top:7px;display:flex;flex-wrap:wrap;gap:5px;">
                                @if ($opp->priority)
                                    @php $pc = \App\Models\TreatmentOpportunity::PRIORITY_COLORS[$opp->priority] ?? ['bg' => '#f0f1f3', 'text' => '#6b7280']; @endphp
                                    <span style="font-size:10.5px;padding:2px 7px;border-radius:999px;background:{{ $pc['bg'] }};color:{{ $pc['text'] }};">
                                        {{ $opp->priority_label }}
                                    </span>
                                @endif
                                @if ($opp->follow_up_date)
                                    <span style="font-size:10.5px;padding:2px 7px;border-radius:999px;
                                        background:{{ $isOverdue ? '#FDECEC' : '#f0f1f3' }};
                                        color:{{ $isOverdue ? '#8A1F1F' : '#6b7280' }};">
                                        {{ $isOverdue ? 'Overdue · ' : '' }}{{ $opp->follow_up_date->format('d M') }}
                                    </span>
                                @endif
                                @if ($opp->assigned_to)
                                    <span style="font-size:10.5px;padding:2px 7px;border-radius:999px;background:#EEEDFE;color:#534AB7;">
                                        {{ $opp->assigned_to }}
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
                        <div style="color:#c2c6cd;font-size:12px;text-align:center;padding:14px 0;">No opportunities</div>
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
        PRE (Relationship Platform) · read-only. Columns use the reliable legacy opportunity status.
        @if ($showJourney)
            Shadow journey state shown for context only.
        @else
            Shadow journey state is hidden (flag <code>relationship.opportunity_journey_column</code> is off).
        @endif
        The legacy Communication / Opportunity surfaces remain available and unchanged.
    </p>
</div>
@endsection
