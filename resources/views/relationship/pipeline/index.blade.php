{{--
|==========================================================================
| PRE — Lead Pipeline (Phase 1 · Workstream D, slice 2;
|        writes added Phase 8 · Slice 1 — PRM Retirement)
| Route: GET  /relationship/pipeline               [relationship.pipeline]
|        POST /relationship/pipeline/{id}/move     [relationship.pipeline.move]
|        POST /relationship/pipeline/{id}/activity [relationship.pipeline.activity]
|        POST /relationship/pipeline/{id}/convert  [relationship.pipeline.convert]
|
| Relationship-centric lead board. Grouped by the RELIABLE legacy leads.stage
| column. Additive — the legacy PRM board is untouched and stays usable.
| Each card now has working Move / + Activity / Convert actions — these call
| the SAME PrmRelationshipAdapter the legacy PRM board's actions use, so both
| boards stay in parity by construction.
| Variables from LeadPipelineController@index:
|   $columns, $totalLeads, $activeCount, $pipelineValue,
|   $showJourney, $journeyByRelationship
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', 'Lead Pipeline')

@section('relationship-content')
<div style="max-width:1400px;margin:0 auto;padding:8px 4px 40px;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px;">
        <div>
            <h1 style="margin:0;font-size:22px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Lead Pipeline</h1>
            <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">
                Every active enquiry, grouped by stage. Relationship-first view.
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            {{-- Phase 8 · Slice 2 — working add-lead entry points --}}
            <a href="{{ route('relationship.pipeline.quick-add') }}"
               style="background:#fff;color:#534AB7;border:1px solid #EEEDFE;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
               + Quick Add
            </a>
            <a href="{{ route('relationship.pipeline.add-lead') }}"
               style="background:#fff;color:#534AB7;border:1px solid #EEEDFE;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;">
               + New Lead
            </a>
            {{-- "Legacy PRM board" link removed — Phase 8 PRM Retirement (Slice 5). --}}
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

                            {{-- Phase 8 · Slice 1 — working card actions --}}
                            <div style="margin-top:9px;padding-top:8px;border-top:1px solid #f1f2f4;display:flex;flex-direction:column;gap:6px;">
                                <select onchange="ppMoveStage({{ $lead->id }}, this)"
                                        style="width:100%;font-size:11px;padding:5px 6px;border:1px solid #e5e7eb;border-radius:6px;color:#4b5563;background:#fff;">
                                    <option value="">Move to…</option>
                                    @foreach ($columns as $moveCol)
                                        @if ($moveCol['key'] !== $lead->stage)
                                            <option value="{{ $moveCol['key'] }}">{{ $moveCol['label'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <div style="display:flex;gap:6px;">
                                    <a href="{{ route('relationship.pipeline.edit-lead', $lead->id) }}"
                                       style="flex:1;text-align:center;font-size:11px;padding:5px 6px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;color:#4b5563;text-decoration:none;">
                                        Edit
                                    </a>
                                    <button type="button" onclick="ppOpenActivity({{ $lead->id }})"
                                            style="flex:1;font-size:11px;padding:5px 6px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;color:#4b5563;cursor:pointer;">
                                        + Activity
                                    </button>
                                    @if (! in_array($lead->stage, ['converted', 'lost'], true))
                                        <button type="button" onclick="ppConvert({{ $lead->id }})"
                                                style="flex:1;font-size:11px;padding:5px 6px;border:1px solid #EAF3DE;border-radius:6px;background:#EAF3DE;color:#3B6D11;cursor:pointer;font-weight:600;">
                                            Convert
                                        </button>
                                    @endif
                                </div>
                            </div>
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
        PRE (Relationship Platform). Columns use the reliable legacy stage.
        @if ($showJourney)
            Shadow journey state shown for context only.
        @else
            Shadow journey state is hidden (flag <code>relationship.pipeline_journey_column</code> is off).
        @endif
    </p>

    {{-- Phase 8 · Slice 1 — Log Activity modal (kept dead simple: type + note). --}}
    <div id="ppActivityModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:200;align-items:center;justify-content:center;">
        <div style="background:#fff;border-radius:12px;padding:20px;width:320px;max-width:92vw;">
            <h3 style="margin:0 0 12px;font-size:15px;color:#1f2937;">Log Activity</h3>
            <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px;">Type</label>
            <select id="ppActType" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;margin-bottom:12px;">
                <option value="note">Note</option>
                <option value="call">Call</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="sms">SMS</option>
                <option value="email">Email</option>
            </select>
            <label style="display:block;font-size:12px;color:#6b7280;margin-bottom:4px;">Note</label>
            <textarea id="ppActNote" rows="3" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;margin-bottom:14px;resize:vertical;" placeholder="What happened?"></textarea>
            <div style="display:flex;gap:8px;justify-content:flex-end;">
                <button type="button" onclick="ppCloseActivity()" style="padding:8px 14px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;font-size:13px;cursor:pointer;">Cancel</button>
                <button type="button" onclick="ppSubmitActivity()" style="padding:8px 14px;border:none;border-radius:8px;background:#534AB7;color:#fff;font-size:13px;font-weight:600;cursor:pointer;">Save</button>
            </div>
        </div>
    </div>
</div>

<script>
// Phase 8 · Slice 1 — PRE lead pipeline write actions. Plain fetch, same
// pattern as the legacy PRM board's working convert button (openConvertToPatient
// in prm-board.js) — keeps this additive and easy to follow for a solo builder.
const ppBaseUrl = "{{ url('/relationship/pipeline') }}";

function ppCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

function ppMoveStage(leadId, select) {
    const newStage = select.value;
    if (!newStage) return;
    const label = select.options[select.selectedIndex].text;

    if (!confirm('Move this lead to "' + label + '"?')) {
        select.value = '';
        return;
    }

    fetch(ppBaseUrl + '/' + leadId + '/move', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ppCsrf(), 'Accept': 'application/json' },
        body: JSON.stringify({ stage: newStage }),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Could not move the lead. Please try again.');
                select.value = '';
            }
        })
        .catch(() => { alert('Network error. Please try again.'); select.value = ''; });
}

let ppActiveLeadId = null;

function ppOpenActivity(leadId) {
    ppActiveLeadId = leadId;
    document.getElementById('ppActNote').value = '';
    document.getElementById('ppActType').value = 'note';
    document.getElementById('ppActivityModal').style.display = 'flex';
}

function ppCloseActivity() {
    document.getElementById('ppActivityModal').style.display = 'none';
    ppActiveLeadId = null;
}

function ppSubmitActivity() {
    if (!ppActiveLeadId) return;

    const type  = document.getElementById('ppActType').value;
    const note  = document.getElementById('ppActNote').value.trim();
    const labelMap = { note: 'Note added', call: 'Call logged', whatsapp: 'WhatsApp sent', sms: 'SMS sent', email: 'Email sent' };

    if (!note) {
        alert('Please add a note before saving.');
        return;
    }

    fetch(ppBaseUrl + '/' + ppActiveLeadId + '/activity', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ppCsrf(), 'Accept': 'application/json' },
        body: JSON.stringify({ type: type, label: labelMap[type] || 'Note added', note: note }),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                ppCloseActivity();
                window.location.reload();
            } else {
                alert(data.message || 'Could not log the activity. Please try again.');
            }
        })
        .catch(() => alert('Network error. Please try again.'));
}

function ppConvert(leadId) {
    if (!confirm('Convert this lead to a patient? This creates a new patient record.')) return;

    fetch(ppBaseUrl + '/' + leadId + '/convert', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': ppCsrf(), 'Accept': 'application/json' },
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Lead converted to patient.');
                window.location.reload();
            } else {
                alert(data.message || 'Conversion failed. Please try again.');
            }
        })
        .catch(() => alert('Conversion failed. Please try again.'));
}
</script>
@endsection
