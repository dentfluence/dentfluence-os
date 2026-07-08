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
<style>
    .pp-card:active { cursor: grabbing; }
    .pp-card.pp-dragging { opacity: 0.4; }
    .pp-drop-zone.pp-drag-over { background: #eef0fb; }
</style>
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
            {{-- Quick Add / New Lead now open as in-page modals instead of separate screens. --}}
            <button type="button" onclick="ppOpenQuickAdd()"
               style="background:#fff;color:#534AB7;border:1px solid #EEEDFE;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
               + Quick Add
            </button>
            <button type="button" onclick="ppOpenNewLead()"
               style="background:#fff;color:#534AB7;border:1px solid #EEEDFE;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
               + New Lead
            </button>
            {{-- "Legacy PRM board" link removed — Phase 8 PRM Retirement (Slice 5). --}}
        </div>
    </div>

    {{-- Flash banner — quick-add/new-lead forms now submit here instead of a separate page --}}
    @if (session('success'))
        <div style="background:#EAF3DE;border:1px solid #c3dba0;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#3B6D11;">
            {{ session('success') }}
        </div>
    @endif

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

                {{-- Cards — drag-and-drop Kanban between columns, "Move to…" kept as a
                     keyboard-friendly fallback for the same action. --}}
                <div class="pp-drop-zone" data-stage="{{ $col['key'] }}"
                     ondragover="ppAllowDrop(event)"
                     ondragenter="ppDragEnterColumn(this)"
                     ondragleave="ppDragLeaveColumn(this)"
                     ondrop="ppDrop(event, '{{ $col['key'] }}', this)"
                     style="padding:10px;display:flex;flex-direction:column;gap:9px;min-height:60px;border-radius:0 0 12px 12px;transition:background 100ms;">
                    @forelse ($col['leads'] as $lead)
                        @php
                            $journeyState = $showJourney && $lead->relationship_id
                                ? ($journeyByRelationship[$lead->relationship_id] ?? null)
                                : null;
                            $isOverdue = $lead->followup_date
                                && $lead->followup_date->isPast()
                                && ! in_array($lead->stage, ['converted', 'lost'], true);
                        @endphp

                        <div class="pp-card" draggable="true"
                             ondragstart="ppDragStart(event, {{ $lead->id }}, '{{ $col['key'] }}')"
                             ondragend="ppDragEnd(event)"
                             style="background:#fff;border:1px solid #edeef1;border-radius:10px;padding:11px 12px;cursor:grab;">
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
                                    <button type="button" onclick="ppOpenDetail({{ $lead->id }})"
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

    {{-- Lead Detail modal (2026-07-08) — replaces the old "blind" Log Activity
         modal. Same Log Activity form, but now shown alongside the full,
         attributed activity history (who logged what, and when) instead of
         firing into the void. Mirrors the Opportunity Pipeline's
         "Opportunity Detail" modal (relationship/opportunities/index.blade.php).
    --}}
    <div id="ppDetailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:200;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:12px;width:640px;max-width:100%;max-height:88vh;overflow-y:auto;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px 0;">
                <h3 style="margin:0;font-size:17px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Lead Detail</h3>
                <button type="button" onclick="ppCloseDetail()" style="border:none;background:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <div id="ppDetailModalBody" style="padding-top:8px;">
                <div style="padding:48px 24px;text-align:center;color:#9ca3af;font-size:13px;">Loading…</div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         Quick Add modal — same 4-field form + validation as before
         (LeadPipelineController::storeQuickLead), now in-page instead of
         a separate screen. Auto-reopens if this form's validation failed.
    ══════════════════════════════════════════════════════════════════ --}}
    @php $quickAddFailed = $errors->any() && old('form_type') === 'quick_add'; @endphp
    <div id="ppQuickAddModal" style="display:{{ $quickAddFailed ? 'flex' : 'none' }};position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:210;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:12px;padding:24px;width:440px;max-width:100%;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <h3 style="margin:0;font-size:18px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">New Lead</h3>
                <button type="button" onclick="ppCloseQuickAdd()" style="border:none;background:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <p style="color:#6b7280;font-size:13px;margin:0 0 18px;">Fill in these 4 details and you're done.</p>

            @if ($quickAddFailed)
                <div style="background:#FDECEC;border:1px solid #f5b5b5;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#8A1F1F;">
                    @foreach ($errors->all() as $error)
                        <div>• {{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('relationship.pipeline.store-quick-lead') }}">
                @csrf
                <input type="hidden" name="form_type" value="quick_add">

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">
                        Patient's Name <span style="color:#c0392b;">*</span>
                    </label>
                    <input type="text" name="name" value="{{ $quickAddFailed ? old('name') : '' }}" placeholder="e.g. Priya Sharma" required
                           style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">
                        Phone Number <span style="color:#c0392b;">*</span>
                    </label>
                    <input type="tel" name="phone" value="{{ $quickAddFailed ? old('phone') : '' }}" placeholder="e.g. 9876543210" required
                           style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                </div>

                <div style="margin-bottom:16px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">
                        How did they find us? <span style="color:#c0392b;">*</span>
                    </label>
                    <select name="lead_source" required
                            style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                        <option value="">— Choose one —</option>
                        @foreach ($leadSources as $key => $label)
                            <option value="{{ $key }}" {{ ($quickAddFailed && old('lead_source') === $key) ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">
                        What treatment do they want?
                    </label>
                    <select name="treatment"
                            style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                        <option value="">— Don't know yet —</option>
                        @foreach ($treatments as $t)
                            <option value="{{ $t }}" {{ ($quickAddFailed && old('treatment') === $t) ? 'selected' : '' }}>{{ $t }}</option>
                        @endforeach
                    </select>
                </div>

                <div style="display:flex;gap:8px;">
                    <button type="submit"
                            style="flex:1;background:#534AB7;color:#fff;border:none;border-radius:8px;padding:12px;font-size:14px;font-weight:600;cursor:pointer;">
                        Add to Pipeline
                    </button>
                    <button type="button" onclick="ppCloseQuickAdd()"
                            style="padding:12px 18px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;font-size:14px;color:#6b7280;cursor:pointer;">
                        Cancel
                    </button>
                </div>
            </form>

            <button type="button" onclick="ppSwitchToNewLead()" style="display:block;width:100%;text-align:center;margin-top:14px;font-size:12px;color:#6b7280;background:none;border:none;cursor:pointer;">
                Need to add more details? Use the full form →
            </button>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         New Lead modal — same full form + validation as before
         (LeadPipelineController::storeLead), now in-page. Auto-reopens if
         this form's validation failed. Editing an existing lead still uses
         its own page (relationship.pipeline.edit-lead) — out of scope here.
    ══════════════════════════════════════════════════════════════════ --}}
    @php
        $newLeadFailed = $errors->any() && old('form_type') === 'new_lead';
        $inputStyle = 'width:100%;box-sizing:border-box;padding:9px 12px;border:1px solid #e5e7eb;border-radius:7px;font-size:13px;background:#fff;';
    @endphp
    <div id="ppNewLeadModal" style="display:{{ $newLeadFailed ? 'flex' : 'none' }};position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:210;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#f7f8fa;border-radius:12px;padding:24px;width:720px;max-width:100%;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <h3 style="margin:0;font-size:18px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Add New Lead</h3>
                <button type="button" onclick="ppCloseNewLead()" style="border:none;background:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <p style="color:#6b7280;font-size:13px;margin:0 0 18px;">Capture a new patient lead into the pipeline.</p>

            @if ($newLeadFailed)
                <div style="background:#FDECEC;border:1px solid #f5b5b5;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#8A1F1F;">
                    @foreach ($errors->all() as $error)
                        <div>• {{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('relationship.pipeline.store-lead') }}">
                @csrf
                <input type="hidden" name="form_type" value="new_lead">

                {{-- Contact info --}}
                <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:18px;margin-bottom:14px;">
                    <div style="font-size:11.5px;font-weight:700;color:#534AB7;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Contact Info</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Name <span style="color:#c0392b;">*</span></label>
                            <input type="text" name="name" value="{{ $newLeadFailed ? old('name') : '' }}" placeholder="Patient's full name" required style="{{ $inputStyle }}">
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Phone <span style="color:#c0392b;">*</span></label>
                            <input type="tel" name="phone" value="{{ $newLeadFailed ? old('phone') : '' }}" placeholder="e.g. 9876543210" required style="{{ $inputStyle }}">
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Alternate Phone</label>
                            <input type="tel" name="alt_phone" value="{{ $newLeadFailed ? old('alt_phone') : '' }}" placeholder="Optional" style="{{ $inputStyle }}">
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Email</label>
                            <input type="email" name="email" value="{{ $newLeadFailed ? old('email') : '' }}" placeholder="Optional" style="{{ $inputStyle }}">
                        </div>
                    </div>
                </div>

                {{-- Source --}}
                <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:18px;margin-bottom:14px;">
                    <div style="font-size:11.5px;font-weight:700;color:#534AB7;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Where did this lead come from?</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Lead Source</label>
                            <select name="lead_source" style="{{ $inputStyle }}">
                                <option value="">— Select channel —</option>
                                @foreach ($leadSources as $key => $label)
                                    <option value="{{ $key }}" {{ ($newLeadFailed && old('lead_source') === $key) ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Referred By <small style="color:#9ca3af;">(if Referral)</small></label>
                            <input type="text" name="referred_by" value="{{ $newLeadFailed ? old('referred_by') : '' }}" placeholder="Referring patient or doctor name" style="{{ $inputStyle }}">
                        </div>
                    </div>
                </div>

                {{-- Treatment + value --}}
                <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:18px;margin-bottom:14px;">
                    <div style="font-size:11.5px;font-weight:700;color:#534AB7;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Treatment Interest &amp; Value</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Primary Treatment</label>
                            <select name="treatment" style="{{ $inputStyle }}">
                                <option value="">— Select treatment —</option>
                                @foreach ($treatments as $t)
                                    <option value="{{ $t }}" {{ ($newLeadFailed && old('treatment') === $t) ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Secondary Treatment</label>
                            <select name="secondary_treatment" style="{{ $inputStyle }}">
                                <option value="">— None —</option>
                                @foreach ($treatments as $t)
                                    <option value="{{ $t }}" {{ ($newLeadFailed && old('secondary_treatment') === $t) ? 'selected' : '' }}>{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Estimated Value (₹)</label>
                            <input type="number" name="lead_value" value="{{ $newLeadFailed ? old('lead_value') : '' }}" placeholder="e.g. 45000" min="0" step="500" style="{{ $inputStyle }}">
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Urgency</label>
                            <select name="urgency" style="{{ $inputStyle }}">
                                @foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High'] as $val => $lbl)
                                    <option value="{{ $val }}" {{ ($newLeadFailed ? old('urgency', 'medium') : 'medium') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Pipeline --}}
                <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:18px;margin-bottom:14px;">
                    <div style="font-size:11.5px;font-weight:700;color:#534AB7;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Pipeline Stage &amp; Assignment</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Pipeline Stage</label>
                            <select name="stage" style="{{ $inputStyle }}">
                                @foreach ($stages as $key => $info)
                                    <option value="{{ $key }}" {{ ($newLeadFailed ? old('stage', 'new_lead') : 'new_lead') === $key ? 'selected' : '' }}>{{ $info['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Assigned To</label>
                            <select name="assigned_to" style="{{ $inputStyle }}">
                                <option value="">— Unassigned —</option>
                                @foreach ($staff as $s)
                                    <option value="{{ $s }}" {{ ($newLeadFailed && old('assigned_to') === $s) ? 'selected' : '' }}>{{ $s }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Follow-up Date</label>
                            <input type="date" name="followup_date" value="{{ $newLeadFailed ? old('followup_date') : '' }}" style="{{ $inputStyle }}">
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Preferred Time</label>
                            <select name="followup_time" style="{{ $inputStyle }}">
                                <option value="">— Any time —</option>
                                @foreach ($timeSlots as $slot)
                                    <option value="{{ $slot }}" {{ ($newLeadFailed && old('followup_time') === $slot) ? 'selected' : '' }}>{{ $slot }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                {{-- Contact preference + notes --}}
                <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:18px;margin-bottom:20px;">
                    <div style="font-size:11.5px;font-weight:700;color:#534AB7;text-transform:uppercase;letter-spacing:.04em;margin-bottom:12px;">Contact Preference &amp; Notes</div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px;">
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Preferred Contact Method</label>
                            <select name="preferred_contact" style="{{ $inputStyle }}">
                                @foreach (['call' => 'Call', 'whatsapp' => 'WhatsApp', 'email' => 'Email'] as $val => $lbl)
                                    <option value="{{ $val }}" {{ ($newLeadFailed ? old('preferred_contact', 'call') : 'call') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Preferred Language</label>
                            <select name="language" style="{{ $inputStyle }}">
                                <option value="">— Any —</option>
                                @foreach ($languages as $lang)
                                    <option value="{{ $lang }}" {{ ($newLeadFailed && old('language') === $lang) ? 'selected' : '' }}>{{ $lang }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label style="display:block;font-size:12px;color:#4b5563;margin-bottom:5px;">Notes</label>
                        <textarea name="notes" rows="3" placeholder="Any context about this lead — what they asked, concerns, etc." style="{{ $inputStyle }}resize:vertical;">{{ $newLeadFailed ? old('notes') : '' }}</textarea>
                    </div>
                </div>

                <div style="display:flex;gap:10px;">
                    <button type="submit" style="background:#534AB7;color:#fff;border:none;border-radius:8px;padding:11px 26px;font-size:14px;font-weight:600;cursor:pointer;">
                        Add to Pipeline
                    </button>
                    <button type="button" onclick="ppCloseNewLead()" style="padding:11px 20px;font-size:14px;color:#6b7280;background:none;border:none;cursor:pointer;">Cancel</button>
                </div>
            </form>
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

    ppSubmitMoveStage(leadId, newStage, () => { select.value = ''; });
}

/** Shared POST to /pipeline/{id}/move — used by both the dropdown and drag-and-drop. */
function ppSubmitMoveStage(leadId, newStage, onFail) {
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
                if (onFail) onFail();
            }
        })
        .catch(() => { alert('Network error. Please try again.'); if (onFail) onFail(); });
}

// ── Drag-and-drop Kanban — grab a card, drop it on another column ──────────
let ppDraggedLeadId = null;
let ppDraggedFromStage = null;
let ppDraggedEl = null;

function ppDragStart(e, leadId, fromStage) {
    ppDraggedLeadId = leadId;
    ppDraggedFromStage = fromStage;
    ppDraggedEl = e.currentTarget;
    ppDraggedEl.classList.add('pp-dragging');
    e.dataTransfer.effectAllowed = 'move';
    // Firefox requires setData to be called for drag to start.
    e.dataTransfer.setData('text/plain', String(leadId));
}

function ppDragEnd() {
    if (ppDraggedEl) ppDraggedEl.classList.remove('pp-dragging');
    ppDraggedLeadId = null;
    ppDraggedFromStage = null;
    ppDraggedEl = null;
}

function ppAllowDrop(e) {
    e.preventDefault();
}

function ppDragEnterColumn(zoneEl) {
    zoneEl.classList.add('pp-drag-over');
}

function ppDragLeaveColumn(zoneEl) {
    zoneEl.classList.remove('pp-drag-over');
}

function ppDrop(e, toStage, zoneEl) {
    e.preventDefault();
    zoneEl.classList.remove('pp-drag-over');

    if (!ppDraggedLeadId || toStage === ppDraggedFromStage) {
        ppDragEnd();
        return;
    }

    const leadId = ppDraggedLeadId;
    ppDragEnd();
    ppSubmitMoveStage(leadId, toStage);
}

// ── Lead Detail modal — shows the attributed activity log (who/when),
// replacing the old blind Log Activity modal. Mirrors the Opportunity
// Pipeline's opOpenDetail()/opCloseDetail().
function ppOpenDetail(leadId) {
    const modal = document.getElementById('ppDetailModal');
    const body  = document.getElementById('ppDetailModalBody');
    if (!modal || !body) return;

    body.innerHTML = '<div style="padding:48px 24px;text-align:center;color:#9ca3af;font-size:13px;">Loading…</div>';
    modal.style.display = 'flex';

    fetch(ppBaseUrl + '/' + leadId + '/modal')
        .then(r => r.text())
        .then(html => { body.innerHTML = html; })
        .catch(() => {
            body.innerHTML = '<div style="padding:48px 24px;text-align:center;color:#e74c3c;font-size:13px;">Could not load this lead. Please try again.</div>';
        });
}

function ppCloseDetail() {
    const modal = document.getElementById('ppDetailModal');
    if (modal) modal.style.display = 'none';
}

function ppOpenQuickAdd() {
    document.getElementById('ppQuickAddModal').style.display = 'flex';
}
function ppCloseQuickAdd() {
    document.getElementById('ppQuickAddModal').style.display = 'none';
}
function ppOpenNewLead() {
    document.getElementById('ppNewLeadModal').style.display = 'flex';
}
function ppCloseNewLead() {
    document.getElementById('ppNewLeadModal').style.display = 'none';
}
function ppSwitchToNewLead() {
    ppCloseQuickAdd();
    ppOpenNewLead();
}

// Close on Escape / backdrop click.
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    ppCloseQuickAdd();
    ppCloseNewLead();
    ppCloseDetail();
});
['ppQuickAddModal', 'ppNewLeadModal', 'ppDetailModal'].forEach(function (id) {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', function (e) { if (e.target === el) el.style.display = 'none'; });
});

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
