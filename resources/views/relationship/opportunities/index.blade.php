{{--
|==========================================================================
| PRE — Opportunity Pipeline (Phase 1 · Workstream D, slice 3;
|        full read/write board 2026-07-06 — replaces the legacy
|        Communication "Opportunity Engine", which now redirects here.)
| Route: GET   /relationship/opportunities                  [relationship.opportunities]
|        POST  /relationship/opportunities                  [relationship.opportunities.store]
|        GET   /relationship/opportunities/patient-search    [relationship.opportunities.patient-search]
|        GET   /relationship/opportunities/{id}/modal        [relationship.opportunities.detail-modal]
|        PATCH /relationship/opportunities/{id}/stage        [relationship.opportunities.update-stage]
|        POST  /relationship/opportunities/{id}/convert      [relationship.opportunities.convert]
|
| Relationship-centric board of treatment opportunities. Grouped by the
| RELIABLE legacy treatment_opportunities.status. Follows the same
| self-contained inline-style/inline-JS pattern as the sibling Lead Pipeline
| board (relationship/pipeline/index.blade.php) — no separate CSS/JS bundle,
| so there's no Vite-rebuild step between an edit and seeing it live.
| Variables from OpportunityPipelineController@index:
|   $columns, $total, $openCount, $pipelineValue, $followUpToday, $convertedMTD,
|   $staff, $showJourney, $journeyByOpportunity
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', 'Opportunity Pipeline')

@section('relationship-content')
<style>
    .op-card:active { cursor: grabbing; }
    .op-card.op-dragging { opacity: 0.4; }
    .op-drop-zone.op-drag-over { background: #eef0fb; }
</style>
<div style="max-width:1400px;margin:0 auto;padding:8px 4px 40px;">

    {{-- Header --}}
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;margin-bottom:18px;">
        <div>
            <h1 style="margin:0;font-size:22px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Opportunity Pipeline</h1>
            <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">
                Treatment opportunities by stage. Relationship-first view.
            </p>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="button" onclick="opOpenAdd()"
               style="background:#534AB7;color:#fff;border:none;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;">
               + Add Opportunity
            </button>
        </div>
    </div>

    {{-- Flash banner --}}
    @if (session('success'))
        <div style="background:#EAF3DE;border:1px solid #c3dba0;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#3B6D11;">
            {{ session('success') }}
        </div>
    @endif

    {{-- Headline stats --}}
    <div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:20px;">
        <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:12px 18px;min-width:130px;">
            <div style="font-size:20px;font-weight:700;color:#1f2937;">{{ number_format($openCount) }}</div>
            <div style="font-size:11.5px;color:#9ca3af;">Total Open</div>
        </div>
        <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:12px 18px;min-width:130px;">
            <div style="font-size:20px;font-weight:700;color:#1f2937;">{{ number_format($followUpToday) }}</div>
            <div style="font-size:11.5px;color:#9ca3af;">Follow-up Due Today</div>
        </div>
        <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:12px 18px;min-width:130px;">
            <div style="font-size:20px;font-weight:700;color:#1f2937;">{{ number_format($convertedMTD) }}</div>
            <div style="font-size:11.5px;color:#9ca3af;">Converted (MTD)</div>
        </div>
        <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:12px 18px;min-width:150px;">
            <div style="font-size:20px;font-weight:700;color:#1f2937;">₹{{ number_format($pipelineValue) }}</div>
            <div style="font-size:11.5px;color:#9ca3af;">Open Pipeline Value</div>
        </div>
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
                     keyboard-friendly fallback. Click anywhere on the card opens the
                     Detail popup instead of navigating to a new page. --}}
                <div class="op-drop-zone" data-stage="{{ $col['key'] }}"
                     ondragover="opAllowDrop(event)"
                     ondragenter="opDragEnterColumn(this)"
                     ondragleave="opDragLeaveColumn(this)"
                     ondrop="opDrop(event, '{{ $col['key'] }}', this)"
                     style="padding:10px;display:flex;flex-direction:column;gap:9px;min-height:60px;border-radius:0 0 12px 12px;transition:background 100ms;">
                    @forelse ($col['items'] as $opp)
                        @php
                            $rel          = $opp->relationship;
                            $personName   = $rel->name ?? $opp->patient?->name ?? 'Unlinked opportunity';
                            $journeyState = $showJourney ? ($journeyByOpportunity[$opp->id] ?? null) : null;
                            $isOverdue    = $opp->follow_up_date
                                && $opp->follow_up_date->isPast()
                                && ! in_array($opp->status, ['completed', 'declined'], true);
                            $pc2 = \App\Models\TreatmentOpportunity::PRIORITY_COLORS[$opp->priority] ?? ['bg' => '#f0f1f3', 'text' => '#6b7280'];
                        @endphp

                        <div class="op-card" draggable="true"
                             onclick="opOpenDetail({{ $opp->id }})"
                             ondragstart="opDragStart(event, {{ $opp->id }}, '{{ $col['key'] }}')"
                             ondragend="opDragEnd(event)"
                             style="background:#fff;border:1px solid #edeef1;border-radius:10px;padding:11px 12px;cursor:grab;">
                            {{-- Person (links to PRE profile when linked) --}}
                            @if ($opp->relationship_id)
                                <a href="{{ route('relationship.profile', $opp->relationship_id) }}"
                                   onclick="event.stopPropagation()"
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
                                    <span style="font-size:10.5px;padding:2px 7px;border-radius:999px;background:{{ $pc2['bg'] }};color:{{ $pc2['text'] }};">
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
                                @if ($opp->assignedStaff)
                                    <span style="font-size:10.5px;padding:2px 7px;border-radius:999px;background:#EEEDFE;color:#534AB7;">
                                        {{ $opp->assignedStaff->name }}
                                    </span>
                                @endif
                            </div>

                            {{-- Shadow journey state (flag-gated, context only) --}}
                            @if ($journeyState)
                                <div style="margin-top:8px;padding-top:7px;border-top:1px dashed #eceef2;color:#9ca3af;font-size:10.5px;">
                                    Journey (shadow): <strong style="color:#6b7280;">{{ str_replace('_', ' ', $journeyState) }}</strong>
                                </div>
                            @endif

                            {{-- Card actions --}}
                            <div style="margin-top:9px;padding-top:8px;border-top:1px solid #f1f2f4;display:flex;flex-direction:column;gap:6px;" onclick="event.stopPropagation()">
                                @if (! in_array($opp->status, ['completed', 'declined'], true))
                                <select onchange="opMoveStage({{ $opp->id }}, this)"
                                        style="width:100%;font-size:11px;padding:5px 6px;border:1px solid #e5e7eb;border-radius:6px;color:#4b5563;background:#fff;">
                                    <option value="">Move to…</option>
                                    @foreach ($columns as $moveCol)
                                        @if ($moveCol['key'] !== $opp->status)
                                            <option value="{{ $moveCol['key'] }}">{{ $moveCol['label'] }}</option>
                                        @endif
                                    @endforeach
                                </select>
                                <button type="button" onclick="opOpenConvert({{ $opp->id }})"
                                        style="width:100%;font-size:11px;padding:5px 6px;border:1px solid #EAF3DE;border-radius:6px;background:#EAF3DE;color:#3B6D11;cursor:pointer;font-weight:600;">
                                    Convert to Lead
                                </button>
                                @elseif ($opp->status === 'completed')
                                <span style="font-size:11px;color:#22c55e;font-weight:600;text-align:center;">✓ Converted</span>
                                @else
                                <span style="font-size:11px;color:#ef4444;font-weight:600;text-align:center;" title="{{ $opp->declined_reason ?: 'No reason given' }}">
                                    ✗ Declined{{ $opp->declined_reason ? ' — '.\Illuminate\Support\Str::limit($opp->declined_reason, 40) : '' }}
                                </span>
                                @endif
                            </div>
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
        PRE (Relationship Platform). Columns use the reliable legacy opportunity status.
        @if ($showJourney)
            Shadow journey state shown for context only.
        @else
            Shadow journey state is hidden (flag <code>relationship.opportunity_journey_column</code> is off).
        @endif
    </p>

    {{-- ══════════════════════════════════════════════════════════════════
         Add Opportunity modal
    ══════════════════════════════════════════════════════════════════ --}}
    @php $addFailed = $errors->any() && old('form_type') === 'add_opportunity'; @endphp
    <div id="opAddModal" style="display:{{ $addFailed ? 'flex' : 'none' }};position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:210;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:12px;padding:24px;width:480px;max-width:100%;max-height:90vh;overflow-y:auto;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <h3 style="margin:0;font-size:18px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Add Opportunity</h3>
                <button type="button" onclick="opCloseAdd()" style="border:none;background:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <p style="color:#6b7280;font-size:13px;margin:0 0 18px;">Track a future treatment interest for a patient.</p>

            @if ($addFailed)
                <div style="background:#FDECEC;border:1px solid #f5b5b5;border-radius:8px;padding:12px 14px;margin-bottom:16px;font-size:13px;color:#8A1F1F;">
                    @foreach ($errors->all() as $error)
                        <div>• {{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('relationship.opportunities.store') }}">
                @csrf
                <input type="hidden" name="form_type" value="add_opportunity">
                <input type="hidden" name="patient_id" id="opPatientId" value="{{ $addFailed ? old('patient_id') : '' }}">

                <div style="margin-bottom:14px;position:relative;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Patient <span style="color:#c0392b;">*</span></label>
                    <input type="text" id="opPatientSearch" placeholder="Search by name or phone…" autocomplete="off"
                           style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                    <div id="opPatientResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:8px;z-index:20;max-height:200px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.1);margin-top:2px;"></div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Treatment Interest <span style="color:#c0392b;">*</span></label>
                        <select name="type" required style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                            <option value="">Select treatment</option>
                            @foreach(\App\Models\TreatmentOpportunity::TREATMENT_TYPES as $key => $label)
                                <option value="{{ $key }}" {{ ($addFailed && old('type') === $key) ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Estimated Value (₹)</label>
                        <input type="number" name="estimated_value" min="0" placeholder="0" value="{{ $addFailed ? old('estimated_value') : '' }}"
                               style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Priority <span style="color:#c0392b;">*</span></label>
                        <select name="priority" required style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                            @foreach(\App\Models\TreatmentOpportunity::PRIORITY_LABELS as $key => $label)
                                <option value="{{ $key }}" {{ (($addFailed ? old('priority', 'medium') : 'medium') === $key) ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Assign To</label>
                        <select name="assigned_to" style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;">
                            <option value="">— Unassigned —</option>
                            @foreach($staff as $user)
                                <option value="{{ $user->id }}" {{ ($addFailed && (int) old('assigned_to') === $user->id) ? 'selected' : '' }}>{{ $user->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Follow-up Date <span style="color:#c0392b;">*</span></label>
                        <input type="date" name="follow_up_date" required value="{{ $addFailed ? old('follow_up_date') : date('Y-m-d', strtotime('+3 days')) }}"
                               style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Follow-up Time</label>
                        <input type="time" name="follow_up_time" value="{{ $addFailed ? old('follow_up_time') : '11:00' }}"
                               style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;">
                    </div>
                </div>

                <div style="margin-bottom:20px;">
                    <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Notes</label>
                    <textarea name="notes" rows="3" placeholder="How did this opportunity come up?"
                              style="width:100%;box-sizing:border-box;padding:11px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;resize:vertical;">{{ $addFailed ? old('notes') : '' }}</textarea>
                </div>

                <div style="display:flex;gap:8px;">
                    <button type="submit" style="flex:1;background:#534AB7;color:#fff;border:none;border-radius:8px;padding:12px;font-size:14px;font-weight:600;cursor:pointer;">
                        Save Opportunity
                    </button>
                    <button type="button" onclick="opCloseAdd()" style="padding:12px 18px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;font-size:14px;color:#6b7280;cursor:pointer;">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         Convert to Lead modal
    ══════════════════════════════════════════════════════════════════ --}}
    <div id="opConvertModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:220;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:12px;padding:24px;width:380px;max-width:100%;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <h3 style="margin:0;font-size:17px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Convert to Lead</h3>
                <button type="button" onclick="opCloseConvert()" style="border:none;background:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;">&times;</button>
            </div>
            {{-- 2026-07-06: copy + default option updated alongside the
                 convertToLead() bug fix (it no longer marks the opportunity
                 "Converted" — see OpportunityPipelineController::convertToLead())
                 and the Lead Pipeline's Appointment/Consultation stage merge
                 (see LeadPipelineController::STAGES) — 'consultation' is no
                 longer a valid Lead stage. --}}
            <p style="color:#6b7280;font-size:13px;margin:0 0 16px;">Creates a new lead in the Lead Pipeline from this opportunity. This opportunity's own stage is left as-is — move or decline it separately if you want it off the open pipeline.</p>
            <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Initial Pipeline Stage</label>
            <select id="opConvertStage" style="width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;background:#fff;margin-bottom:18px;">
                <option value="new_lead">New Lead</option>
                <option value="contacted">Contacted</option>
                <option value="appointment" selected>Appointment / Consultation Booked</option>
            </select>
            <div style="display:flex;gap:8px;">
                <button type="button" id="opConvertSubmit" onclick="opSubmitConvert()"
                        style="flex:1;background:#534AB7;color:#fff;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer;">
                    Convert to Lead
                </button>
                <button type="button" onclick="opCloseConvert()" style="padding:11px 18px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;font-size:14px;color:#6b7280;cursor:pointer;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         Decline reason modal — shown whenever an opportunity is moved to
         "Declined" (dropdown, drag-drop, or the Detail modal's stage buttons).
         Reason is optional; skipping just declines with no reason recorded.
    ══════════════════════════════════════════════════════════════════ --}}
    <div id="opDeclineModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:230;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:12px;padding:24px;width:400px;max-width:100%;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
                <h3 style="margin:0;font-size:17px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Mark as Declined</h3>
                <button type="button" onclick="opCloseDecline()" style="border:none;background:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <p style="color:#6b7280;font-size:13px;margin:0 0 14px;">Optional — note why this opportunity didn't go ahead.</p>
            <label style="display:block;font-size:13px;font-weight:600;color:#1f2937;margin-bottom:6px;">Reason</label>
            <textarea id="opDeclineReason" rows="3" placeholder="e.g. Went with another clinic, cost concern, postponed…"
                      style="width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #e5e7eb;border-radius:8px;font-size:13px;resize:vertical;margin-bottom:18px;"></textarea>
            <div style="display:flex;gap:8px;">
                <button type="button" onclick="opSubmitDecline()"
                        style="flex:1;background:#ef4444;color:#fff;border:none;border-radius:8px;padding:11px;font-size:14px;font-weight:600;cursor:pointer;">
                    Mark as Declined
                </button>
                <button type="button" onclick="opCloseDecline()" style="padding:11px 18px;border:1px solid #e5e7eb;border-radius:8px;background:#fff;font-size:14px;color:#6b7280;cursor:pointer;">
                    Cancel
                </button>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         Opportunity Detail modal — cards open this instead of a new page
    ══════════════════════════════════════════════════════════════════ --}}
    <div id="opDetailModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:200;align-items:center;justify-content:center;padding:20px;">
        <div style="background:#fff;border-radius:12px;width:720px;max-width:100%;max-height:88vh;overflow-y:auto;">
            <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px 0;">
                <h3 style="margin:0;font-size:17px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Opportunity Detail</h3>
                <button type="button" onclick="opCloseDetail()" style="border:none;background:none;font-size:18px;color:#9ca3af;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <div id="opDetailModalBody" style="padding-top:8px;">
                <div style="padding:48px 24px;text-align:center;color:#9ca3af;font-size:13px;">Loading…</div>
            </div>
        </div>
    </div>
</div>

<script>
// PRE Opportunity Pipeline write actions. Plain fetch, same pattern as the
// sibling Lead Pipeline board (relationship/pipeline/index.blade.php) — no
// separate JS bundle, so edits here are live on the next page load.
const opBaseUrl = "{{ url('/relationship/opportunities') }}";

function opCsrf() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}

// ── Stage move — dropdown fallback + drag-and-drop share this ─────────────

function opMoveStage(oppId, select) {
    const newStage = select.value;
    if (!newStage) return;
    if (newStage === 'declined') {
        opOpenDecline(oppId, () => { select.value = ''; });
        return;
    }
    opSubmitMoveStage(oppId, newStage, () => { select.value = ''; });
}

function opMoveStageFromModal(oppId, newStage) {
    if (newStage === 'declined') {
        opOpenDecline(oppId);
        return;
    }
    opSubmitMoveStage(oppId, newStage);
}

function opSubmitMoveStage(oppId, newStage, onFail, reason) {
    const body = { status: newStage };
    if (reason) body.reason = reason;

    fetch(opBaseUrl + '/' + oppId + '/stage', {
        method: 'PATCH',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': opCsrf(), 'Accept': 'application/json' },
        body: JSON.stringify(body),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert(data.message || 'Could not move this opportunity. Please try again.');
                if (onFail) onFail();
            }
        })
        .catch(() => { alert('Network error. Please try again.'); if (onFail) onFail(); });
}

// ── Decline reason modal ────────────────────────────────────────────────────

let opDeclineOppId = null;
let opDeclineOnCancel = null;

function opOpenDecline(oppId, onCancel) {
    opDeclineOppId = oppId;
    opDeclineOnCancel = onCancel || null;
    document.getElementById('opDeclineReason').value = '';
    document.getElementById('opDeclineModal').style.display = 'flex';
}

function opCloseDecline() {
    document.getElementById('opDeclineModal').style.display = 'none';
    if (opDeclineOnCancel) opDeclineOnCancel();
    opDeclineOppId = null;
    opDeclineOnCancel = null;
}

function opSubmitDecline() {
    if (!opDeclineOppId) return;
    const oppId  = opDeclineOppId;
    const reason = document.getElementById('opDeclineReason').value.trim();

    // This is a real submit, not a cancel — don't run the cancel callback.
    opDeclineOnCancel = null;
    document.getElementById('opDeclineModal').style.display = 'none';
    opDeclineOppId = null;

    opSubmitMoveStage(oppId, 'declined', null, reason);
}

// ── Drag-and-drop Kanban ────────────────────────────────────────────────────

let opDraggedId = null;
let opDraggedFromStage = null;
let opDraggedEl = null;

function opDragStart(e, oppId, fromStage) {
    opDraggedId = oppId;
    opDraggedFromStage = fromStage;
    opDraggedEl = e.currentTarget;
    opDraggedEl.classList.add('op-dragging');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', String(oppId));
}

function opDragEnd() {
    if (opDraggedEl) opDraggedEl.classList.remove('op-dragging');
    opDraggedId = null;
    opDraggedFromStage = null;
    opDraggedEl = null;
}

function opAllowDrop(e) { e.preventDefault(); }

function opDragEnterColumn(zoneEl) { zoneEl.classList.add('op-drag-over'); }
function opDragLeaveColumn(zoneEl) { zoneEl.classList.remove('op-drag-over'); }

function opDrop(e, toStage, zoneEl) {
    e.preventDefault();
    zoneEl.classList.remove('op-drag-over');

    if (!opDraggedId || toStage === opDraggedFromStage) {
        opDragEnd();
        return;
    }

    const oppId = opDraggedId;
    opDragEnd();

    if (toStage === 'declined') {
        opOpenDecline(oppId);
        return;
    }
    opSubmitMoveStage(oppId, toStage);
}

// ── Add Opportunity modal + patient autocomplete ───────────────────────────

function opOpenAdd() {
    document.getElementById('opAddModal').style.display = 'flex';
}
function opCloseAdd() {
    document.getElementById('opAddModal').style.display = 'none';
}

(function initPatientSearch() {
    const input   = document.getElementById('opPatientSearch');
    const hidden  = document.getElementById('opPatientId');
    const results = document.getElementById('opPatientResults');
    if (!input) return;

    let debounceTimer;
    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const q = this.value.trim();
        hidden.value = '';

        if (q.length < 2) { results.style.display = 'none'; return; }

        debounceTimer = setTimeout(() => {
            fetch(`${opBaseUrl}/patient-search?q=${encodeURIComponent(q)}`)
                .then(r => r.json())
                .then(patients => {
                    results.innerHTML = '';
                    if (!patients.length) {
                        results.innerHTML = '<div style="padding:10px 14px;font-size:13px;color:#9ca3af">No patients found</div>';
                    } else {
                        patients.forEach(p => {
                            const div = document.createElement('div');
                            div.style.cssText = 'padding:10px 14px;cursor:pointer;font-size:13px;color:#1f2937;border-bottom:1px solid #f3f4f6';
                            div.innerHTML = `<strong>${p.name}</strong> <span style="color:#9ca3af">${p.phone || ''}</span>`;
                            div.addEventListener('click', () => {
                                input.value    = `${p.name} — ${p.phone || ''}`;
                                hidden.value   = p.id;
                                results.style.display = 'none';
                            });
                            div.addEventListener('mouseover', () => div.style.background = '#f9fafb');
                            div.addEventListener('mouseout',  () => div.style.background = '');
                            results.appendChild(div);
                        });
                    }
                    results.style.display = 'block';
                })
                .catch(() => { results.style.display = 'none'; });
        }, 300);
    });

    document.addEventListener('click', e => {
        if (!input.contains(e.target) && !results.contains(e.target)) {
            results.style.display = 'none';
        }
    });
})();

// ── Convert to Lead modal ──────────────────────────────────────────────────

let opConvertOppId = null;

function opOpenConvert(oppId) {
    opConvertOppId = oppId;
    document.getElementById('opConvertModal').style.display = 'flex';
}
function opCloseConvert() {
    document.getElementById('opConvertModal').style.display = 'none';
    opConvertOppId = null;
}

function opSubmitConvert() {
    if (!opConvertOppId) return;
    const stage = document.getElementById('opConvertStage').value;
    const btn   = document.getElementById('opConvertSubmit');
    btn.disabled = true;
    btn.textContent = 'Converting…';

    fetch(`${opBaseUrl}/${opConvertOppId}/convert`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': opCsrf(), 'Accept': 'application/json' },
        body: JSON.stringify({ stage }),
    })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                opCloseConvert();
                opCloseDetail();
                window.location.reload();
            } else {
                alert(data.message || 'Conversion failed. Please try again.');
                btn.disabled = false;
                btn.textContent = 'Convert to Lead';
            }
        })
        .catch(() => {
            alert('Network error. Please try again.');
            btn.disabled = false;
            btn.textContent = 'Convert to Lead';
        });
}

// ── Opportunity Detail modal ───────────────────────────────────────────────

function opOpenDetail(oppId) {
    const modal = document.getElementById('opDetailModal');
    const body  = document.getElementById('opDetailModalBody');
    if (!modal || !body) return;

    body.innerHTML = '<div style="padding:48px 24px;text-align:center;color:#9ca3af;font-size:13px;">Loading…</div>';
    modal.style.display = 'flex';

    fetch(`${opBaseUrl}/${oppId}/modal`)
        .then(r => r.text())
        .then(html => { body.innerHTML = html; })
        .catch(() => {
            body.innerHTML = '<div style="padding:48px 24px;text-align:center;color:#e74c3c;font-size:13px;">Could not load this opportunity. Please try again.</div>';
        });
}

function opCloseDetail() {
    const modal = document.getElementById('opDetailModal');
    if (modal) modal.style.display = 'none';
}

// ── Close on Escape / backdrop click ───────────────────────────────────────

document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    opCloseAdd();
    opCloseConvert();
    opCloseDetail();
    opCloseDecline();
});
['opAddModal', 'opConvertModal', 'opDetailModal'].forEach(function (id) {
    const el = document.getElementById(id);
    if (el) el.addEventListener('click', function (e) { if (e.target === el) el.style.display = 'none'; });
});
// Decline modal gets its own backdrop-click handler (not the generic one above)
// so cancelling by clicking outside still runs the cancel callback — e.g.
// resetting a "Move to…" dropdown back to blank.
(function () {
    const el = document.getElementById('opDeclineModal');
    if (el) el.addEventListener('click', function (e) { if (e.target === el) opCloseDecline(); });
})();
</script>
@endsection
