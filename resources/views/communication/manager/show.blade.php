{{-- Communication Detail — PRM Update 2026-06-13 --}}
@extends('layouts.communication')

@push('communication-styles')
<style>
/* ── Communication Detail ── */
.cd { max-width:860px; margin:0 auto; padding:20px 24px 48px; font-family:var(--comm-font,'Inter',sans-serif); }
.cd__back { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:#64748b; text-decoration:none; margin-bottom:14px; }
.cd__back:hover { color:#6a0f70; }

/* Header */
.cd__header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.cd__header-left h2 { font-size:18px; font-weight:600; color:#0f172a; margin:0 0 6px; }
.cd__header-left p { font-size:12px; color:#94a3b8; margin:0; }
.cd__header-actions { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }

.cd__badge { display:inline-block; font-size:11px; font-weight:600; padding:3px 10px; border-radius:9px; }
.cd__badge--warning  { background:#fffbeb; color:#b45309; border:1px solid #fde68a; }
.cd__badge--danger   { background:#fff1f2; color:#dc2626; border:1px solid #fecaca; }
.cd__badge--info     { background:#eff6ff; color:#2563eb; border:1px solid #bfdbfe; }
.cd__badge--success  { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; }
.cd__badge--secondary{ background:#f1f5f9; color:#475569; border:1px solid #e2e8f0; }

/* Quick actions */
.cd__qbtn { display:inline-flex; align-items:center; gap:5px; padding:6px 14px; font-size:12px; font-weight:500; border-radius:6px; border:1px solid #e2e8f0; background:#fff; color:#374151; cursor:pointer; text-decoration:none; transition:all .15s; }
.cd__qbtn:hover { border-color:#6a0f70; color:#6a0f70; }
.cd__qbtn--primary { background:#6a0f70; color:#fff; border-color:#6a0f70; }
.cd__qbtn--primary:hover { background:#4e0b52; color:#fff; }
.cd__qbtn--success { border-color:#bbf7d0; color:#16a34a; }
.cd__qbtn--success:hover { background:#f0fdf4; }
.cd__qbtn--danger { border-color:#fecaca; color:#dc2626; }
.cd__qbtn--danger:hover { background:#fff1f2; }

/* Grid layout */
.cd__grid { display:grid; grid-template-columns:1.6fr 1fr; gap:16px; align-items:start; }
@media(max-width:680px){ .cd__grid { grid-template-columns:1fr; } }

/* Cards */
.cd__card { background:#fff; border:1px solid #e2e8f0; border-radius:8px; padding:18px; }
.cd__card-title { font-size:10px; font-weight:700; color:#94a3b8; text-transform:uppercase; letter-spacing:.08em; margin:0 0 14px; }

/* Detail rows */
.cd__row { display:flex; justify-content:space-between; align-items:flex-start; padding:7px 0; border-bottom:1px solid #f1f5f9; gap:12px; }
.cd__row:last-child { border-bottom:none; }
.cd__row-key { font-size:11px; color:#94a3b8; font-weight:500; white-space:nowrap; }
.cd__row-val { font-size:13px; color:#0f172a; text-align:right; font-weight:500; }

/* Edit form */
.cd__edit-form { margin-top:12px; border-top:1px solid #e2e8f0; padding-top:12px; }
.cd__field { margin-bottom:12px; }
.cd__field label { display:block; font-size:11px; font-weight:600; color:#374151; margin-bottom:3px; }
.cd__field input, .cd__field select, .cd__field textarea {
    width:100%; border:1px solid #e2e8f0; border-radius:5px; padding:7px 10px;
    font-size:13px; outline:none; box-sizing:border-box;
}
.cd__field input:focus, .cd__field select:focus, .cd__field textarea:focus { border-color:#6a0f70; }
.cd__field textarea { resize:vertical; min-height:70px; }
.cd__toggle-group { display:flex; flex-wrap:wrap; gap:6px; }
.cd__toggle-btn { padding:5px 12px; font-size:12px; font-weight:500; border:1px solid #e2e8f0; border-radius:5px; cursor:pointer; background:#fff; color:#374151; }
.cd__toggle-btn:hover { border-color:#6a0f70; }
.cd__toggle-btn.is-active { background:#6a0f70; color:#fff; border-color:#6a0f70; }

/* Patient card */
.cd__patient-card { display:flex; align-items:center; gap:10px; padding:10px 14px; background:#f8f4fa; border:1px solid #d8b4e2; border-radius:6px; text-decoration:none; color:inherit; }
.cd__patient-card:hover { background:#f0e6f5; }
.cd__patient-avatar { width:36px; height:36px; border-radius:50%; background:#6a0f70; color:#fff; font-size:14px; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.cd__patient-name { font-size:13px; font-weight:600; color:#4e0b52; }
.cd__patient-meta { font-size:11px; color:#94a3b8; }

/* Timeline */
.cd__timeline { margin-top:4px; }
.cd__tl-item { display:flex; gap:10px; padding:8px 0; position:relative; }
.cd__tl-item:not(:last-child)::after { content:''; position:absolute; left:11px; top:28px; bottom:-8px; width:1px; background:#e2e8f0; }
.cd__tl-dot { width:22px; height:22px; border-radius:50%; background:#f1f5f9; border:2px solid #e2e8f0; display:flex; align-items:center; justify-content:center; font-size:10px; flex-shrink:0; }
.cd__tl-body { flex:1; min-width:0; }
.cd__tl-action { font-size:12px; font-weight:600; color:#374151; }
.cd__tl-desc { font-size:11px; color:#64748b; margin-top:1px; }
.cd__tl-meta { font-size:10px; color:#94a3b8; margin-top:1px; }

.cd__flash { padding:9px 16px; border-radius:6px; font-size:13px; font-weight:500; margin-bottom:14px; }
.cd__flash--success { background:#f0fdf4; border:1px solid #bbf7d0; color:#15803d; }
</style>
@endpush

@section('communication-content')
<div class="cd" x-data="{
    editMode: {{ request()->get('edit') ? 'true' : 'false' }},
    nextAction: '{{ $comm->next_action }}',
    priority: '{{ $comm->priority }}',
    showAssign: false,
    showMove: false,
    showOutcomeModal: false,
    showAttemptPanel: false,
}">

    <a href="{{ route('communication.manager.index') }}" class="cd__back">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Communication List
    </a>

    @if(session('success'))
        <div class="cd__flash cd__flash--success">✓ {{ session('success') }}</div>
    @endif

    {{-- ── Header ── --}}
    <div class="cd__header">
        <div class="cd__header-left">
            <h2>{{ $comm->person_name }}
                @if($comm->phone)
                    <span style="font-size:14px;font-weight:400;color:#64748b;"> · {{ $comm->phone }}</span>
                @endif
            </h2>
            <p>
                <span class="cd__badge {{ $comm->status_badge_class }}">{{ $comm->status_label }}</span>
                &nbsp;
                <span class="cd__badge cd__badge--secondary">{{ $comm->comm_type_label }}</span>
                &nbsp;
                <span class="cd__badge cd__badge--secondary">{{ $comm->channel_label }}</span>
                &nbsp;
                @if($comm->priority === 'high')
                    <span class="cd__badge cd__badge--danger">High Priority</span>
                @elseif($comm->priority === 'medium')
                    <span class="cd__badge cd__badge--warning">Medium</span>
                @else
                    <span class="cd__badge cd__badge--secondary">Low</span>
                @endif
                {{-- Phase 1: SLA badge --}}
                @if($comm->status !== 'closed' && $comm->sla_deadline)
                    &nbsp;
                    @if($comm->sla_breached || now()->gt($comm->sla_deadline))
                        <span class="cd__badge cd__badge--danger" title="SLA breached — {{ $comm->sla_deadline->format('d M, g:ia') }}">
                            SLA Breached
                        </span>
                    @else
                        <span class="cd__badge cd__badge--info" title="SLA deadline: {{ $comm->sla_deadline->format('d M, g:ia') }}">
                            {{ now()->diffForHumans($comm->sla_deadline, true) }} left
                        </span>
                    @endif
                @endif
                {{-- Phase 1: attempt counter --}}
                @if($comm->attempt_count > 0)
                    &nbsp;
                    <span class="cd__badge cd__badge--secondary">{{ $comm->attempt_count }} attempt{{ $comm->attempt_count > 1 ? 's' : '' }}</span>
                @endif
            </p>
        </div>

        <div class="cd__header-actions">
            <button class="cd__qbtn" @click="editMode = !editMode">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                <span x-text="editMode ? 'Cancel Edit' : 'Edit'"></span>
            </button>

            @if($comm->status !== 'closed')
            {{-- Phase 1: Log Attempt button --}}
            <button class="cd__qbtn" @click="showAttemptPanel = !showAttemptPanel">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 11.5 19.79 19.79 0 0 1 1.62 2.84 2 2 0 0 1 3.62 1h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.6a16 16 0 0 0 6 6l.96-1.06a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 21.72 16z"/></svg>
                Log Attempt
            </button>
            {{-- Phase 1: Close with Outcome — opens modal --}}
            <button class="cd__qbtn cd__qbtn--success" @click="showOutcomeModal = true">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                Close
            </button>
            @endif

            {{-- Move button with dropdown --}}
            <div style="position:relative;" x-data="{ open:false }">
                <button class="cd__qbtn" @click="open=!open">
                    Move To ▾
                </button>
                <div x-show="open" @click.away="open=false" x-transition
                     style="position:absolute;right:0;top:100%;background:#fff;border:1px solid #e2e8f0;border-radius:6px;min-width:168px;box-shadow:0 4px 16px rgba(0,0,0,.1);z-index:50;padding:4px 0;margin-top:4px;">
                    <form method="POST" action="{{ route('communication.manager.move', $comm->id) }}">
                        @csrf
                        @foreach(['prm_pipeline'=>'PRM Pipeline','follow_ups'=>'Follow-ups','calendar'=>'Calendar/Appointment','task'=>'Create Task','archive'=>'Archive'] as $d => $dl)
                        <button type="submit" name="move_to" value="{{ $d }}"
                            style="display:block;width:100%;text-align:left;padding:8px 16px;font-size:13px;background:none;border:none;cursor:pointer;color:#374151;"
                            onmouseover="this.style.background='#f8f4fa'" onmouseout="this.style.background='none'">
                            {{ $dl }}
                        </button>
                        @endforeach
                    </form>
                </div>
            </div>
        </div>
    </div>

    {{-- ── Phase 1: Log Attempt Panel (inline, collapsible) ── --}}
    @if($comm->status !== 'closed')
    <div x-show="showAttemptPanel" x-transition style="margin-bottom:14px;">
        <div class="cd__card" style="border-color:#d8b4e2;">
            <p class="cd__card-title">Log Contact Attempt</p>
            <form method="POST" action="{{ route('communication.manager.attempt', $comm->id) }}">
                @csrf
                <div style="display:flex;gap:10px;align-items:flex-end;">
                    <div style="flex:1;">
                        <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:3px;">
                            Notes <span style="font-weight:400;color:#94a3b8;">(optional — what happened?)</span>
                        </label>
                        <input type="text" name="attempt_notes" placeholder="e.g. No answer. Tried WhatsApp also."
                               style="width:100%;border:1px solid #e2e8f0;border-radius:5px;padding:8px 10px;font-size:13px;box-sizing:border-box;">
                    </div>
                    <button type="submit" class="cd__qbtn cd__qbtn--primary" style="white-space:nowrap;">
                        Record Attempt
                    </button>
                    <button type="button" class="cd__qbtn" @click="showAttemptPanel=false">Cancel</button>
                </div>
                <p style="font-size:11px;color:#94a3b8;margin:6px 0 0;">
                    Attempt count: <strong>{{ $comm->attempt_count }}</strong>
                    @if($comm->last_attempt_at)
                        · Last: {{ $comm->last_attempt_at->diffForHumans() }}
                    @endif
                </p>
            </form>
        </div>
    </div>
    @endif

    {{-- ── Phase 1: Outcome Modal ── --}}
    <div x-show="showOutcomeModal" x-transition
         style="position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45);"
         @click.self="showOutcomeModal=false">
        <div style="background:#fff;border-radius:10px;padding:24px 28px;max-width:460px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,.2);">
            <h3 style="font-size:15px;font-weight:700;color:#0f172a;margin:0 0 4px;">Close Communication</h3>
            <p style="font-size:12px;color:#94a3b8;margin:0 0 18px;">Select an outcome — this is required and cannot be skipped.</p>

            <form method="POST" action="{{ route('communication.manager.close', $comm->id) }}">
                @csrf

                <div style="margin-bottom:12px;">
                    <label style="font-size:11px;font-weight:700;color:#374151;display:block;margin-bottom:6px;">Outcome *</label>
                    <div style="display:flex;flex-direction:column;gap:6px;">
                        @foreach(App\Models\CommunicationQueue::OUTCOMES as $key => $label)
                        <label style="display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid #e2e8f0;border-radius:6px;cursor:pointer;font-size:13px;color:#374151;"
                               onmouseover="this.style.borderColor='#6a0f70'" onmouseout="this.style.borderColor='#e2e8f0'">
                            <input type="radio" name="outcome" value="{{ $key }}" required style="accent-color:#6a0f70;">
                            {{ $label }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div style="margin-bottom:16px;">
                    <label style="font-size:11px;font-weight:600;color:#374151;display:block;margin-bottom:3px;">
                        Notes <span style="font-weight:400;color:#94a3b8;">(optional — what was said, why lost, etc.)</span>
                    </label>
                    <textarea name="outcome_reason" rows="2"
                              placeholder="e.g. Patient said too expensive. Asked to call next month."
                              style="width:100%;border:1px solid #e2e8f0;border-radius:5px;padding:8px 10px;font-size:13px;resize:vertical;box-sizing:border-box;"></textarea>
                </div>

                <div style="display:flex;gap:8px;justify-content:flex-end;">
                    <button type="button" class="cd__qbtn" @click="showOutcomeModal=false">Cancel</button>
                    <button type="submit" class="cd__qbtn cd__qbtn--primary">Close & Record</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ── Main grid ── --}}
    <div class="cd__grid">

        {{-- Left col: Details + Edit ── --}}
        <div>
            {{-- Detail card ── --}}
            <div class="cd__card" x-show="!editMode">
                <p class="cd__card-title">Details</p>

                <div class="cd__row">
                    <span class="cd__row-key">Channel</span>
                    <span class="cd__row-val">{{ $comm->channel_icon }} {{ $comm->channel_label }}</span>
                </div>
                <div class="cd__row">
                    <span class="cd__row-key">Direction</span>
                    <span class="cd__row-val">{{ ucfirst($comm->direction) }}</span>
                </div>
                <div class="cd__row">
                    <span class="cd__row-key">Type</span>
                    <span class="cd__row-val">{{ $comm->comm_type_label }}</span>
                </div>
                @if($comm->purpose)
                <div class="cd__row">
                    <span class="cd__row-key">Purpose</span>
                    <span class="cd__row-val">{{ $comm->purpose_label }}</span>
                </div>
                @endif
                <div class="cd__row">
                    <span class="cd__row-key">Next Action</span>
                    <span class="cd__row-val">{{ $comm->next_action_label }}</span>
                </div>
                @if($comm->follow_up_date)
                <div class="cd__row">
                    <span class="cd__row-key">Follow-up</span>
                    <span class="cd__row-val" style="{{ $comm->is_overdue ? 'color:#dc2626;' : '' }}">
                        {{ $comm->follow_up_date->format('d M Y') }}
                        @if($comm->follow_up_time) · {{ $comm->follow_up_time }}@endif
                        @if($comm->is_overdue) ({{ $comm->overdue_since }} overdue)@endif
                    </span>
                </div>
                @endif
                <div class="cd__row">
                    <span class="cd__row-key">Priority</span>
                    <span class="cd__row-val">{{ ucfirst($comm->priority) }}</span>
                </div>
                <div class="cd__row">
                    <span class="cd__row-key">Owner</span>
                    <span class="cd__row-val">{{ $comm->assigned_to ?? '—' }}</span>
                </div>
                <div class="cd__row">
                    <span class="cd__row-key">Created</span>
                    <span class="cd__row-val" style="font-size:11px;">
                        {{ $comm->created_at->format('d M Y, g:ia') }}
                        @if($comm->createdByUser) by {{ $comm->createdByUser->name }}@endif
                    </span>
                </div>

                {{-- Phase 1: SLA row --}}
                @if($comm->sla_deadline)
                <div class="cd__row">
                    <span class="cd__row-key">SLA</span>
                    <span class="cd__row-val" style="{{ ($comm->sla_breached || ($comm->status !== 'closed' && now()->gt($comm->sla_deadline))) ? 'color:#dc2626;font-weight:600;' : '' }}">
                        {{ $comm->sla_status }}
                        <span style="font-size:10px;color:#94a3b8;font-weight:400;"> ({{ $comm->sla_deadline->format('d M, g:ia') }})</span>
                    </span>
                </div>
                @endif
                {{-- Phase 1: Attempt row --}}
                <div class="cd__row">
                    <span class="cd__row-key">Attempts</span>
                    <span class="cd__row-val">
                        {{ $comm->attempt_count ?: '0' }}
                        @if($comm->last_attempt_at)
                            <span style="font-size:10px;color:#94a3b8;font-weight:400;"> · last {{ $comm->last_attempt_at->diffForHumans() }}</span>
                        @endif
                    </span>
                </div>
                {{-- Phase 1: Outcome row (only when closed) --}}
                @if($comm->outcome)
                <div class="cd__row">
                    <span class="cd__row-key">Outcome</span>
                    <span class="cd__row-val" style="{{ str_starts_with($comm->outcome, 'appointment') || str_starts_with($comm->outcome, 'treatment') ? 'color:#16a34a;' : (str_starts_with($comm->outcome, 'lost') || str_starts_with($comm->outcome, 'unreachable') || str_starts_with($comm->outcome, 'not') ? 'color:#dc2626;' : '') }}">
                        {{ $comm->outcome_label }}
                    </span>
                </div>
                @if($comm->outcome_reason)
                <div style="padding:8px 0 4px;border-bottom:1px solid #f1f5f9;">
                    <p style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;margin:0 0 4px;">Outcome Notes</p>
                    <p style="font-size:12px;color:#374151;margin:0;white-space:pre-line;">{{ $comm->outcome_reason }}</p>
                </div>
                @endif
                @endif

                @if($comm->note)
                <div style="margin-top:12px;padding-top:12px;border-top:1px solid #f1f5f9;">
                    <p style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin:0 0 6px;">Note</p>
                    <p style="font-size:13px;color:#374151;line-height:1.6;margin:0;white-space:pre-line;">{{ $comm->note }}</p>
                </div>
                @endif
                @if($comm->response_notes)
                <div style="margin-top:8px;padding-top:8px;border-top:1px solid #f1f5f9;">
                    <p style="font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.08em;margin:0 0 6px;">Last Attempt Note</p>
                    <p style="font-size:12px;color:#374151;line-height:1.6;margin:0;white-space:pre-line;">{{ $comm->response_notes }}</p>
                </div>
                @endif
            </div>

            {{-- Edit form ── --}}
            <div class="cd__card" x-show="editMode" style="display:none;" x-transition>
                <p class="cd__card-title">Edit Communication</p>
                <form method="POST" action="{{ route('communication.manager.update', $comm->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="cd__field">
                        <label>Name *</label>
                        <input type="text" name="person_name" value="{{ $comm->person_name }}" required>
                    </div>
                    <div class="cd__field">
                        <label>Mobile *</label>
                        <input type="tel" name="phone" value="{{ $comm->phone }}" required>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div class="cd__field">
                            <label>Channel *</label>
                            <select name="channel" required>
                                @foreach($channels as $key => $label)
                                    <option value="{{ $key }}" {{ $comm->channel === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="cd__field">
                            <label>Direction *</label>
                            <select name="direction" required>
                                <option value="incoming" {{ $comm->direction === 'incoming' ? 'selected' : '' }}>Incoming</option>
                                <option value="outgoing" {{ $comm->direction === 'outgoing' ? 'selected' : '' }}>Outgoing</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div class="cd__field">
                            <label>Type *</label>
                            <select name="comm_type" required>
                                @foreach($commTypes as $key => $label)
                                    <option value="{{ $key }}" {{ $comm->comm_type === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="cd__field">
                            <label>Purpose</label>
                            <select name="purpose">
                                <option value="">—</option>
                                @foreach($purposes as $key => $label)
                                    <option value="{{ $key }}" {{ $comm->purpose === $key ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="cd__field">
                        <label>Next Action</label>
                        <input type="hidden" name="next_action" :value="nextAction">
                        <div class="cd__toggle-group">
                            @foreach($nextActions as $key => $label)
                            <button type="button" class="cd__toggle-btn"
                                :class="nextAction === '{{ $key }}' ? 'is-active' : ''"
                                @click="nextAction = nextAction === '{{ $key }}' ? '' : '{{ $key }}'">
                                {{ $label }}
                            </button>
                            @endforeach
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                        <div class="cd__field">
                            <label>Follow-up Date</label>
                            <input type="date" name="follow_up_date" value="{{ $comm->follow_up_date?->format('Y-m-d') }}">
                        </div>
                        <div class="cd__field">
                            <label>Follow-up Time</label>
                            <input type="time" name="follow_up_time" value="{{ $comm->follow_up_time }}">
                        </div>
                    </div>

                    <div class="cd__field">
                        <label>Priority</label>
                        <input type="hidden" name="priority" :value="priority">
                        <div class="cd__toggle-group">
                            <button type="button" class="cd__toggle-btn" :class="priority==='high'?'is-active':''" @click="priority='high'" style="border-color:#fecaca;">High</button>
                            <button type="button" class="cd__toggle-btn" :class="priority==='medium'?'is-active':''" @click="priority='medium'" style="border-color:#fde68a;">Medium</button>
                            <button type="button" class="cd__toggle-btn" :class="priority==='low'?'is-active':''" @click="priority='low'">Low</button>
                        </div>
                    </div>

                    <div class="cd__field">
                        <label>Assign To</label>
                        <select name="assigned_to">
                            <option value="">— Keep current ({{ $comm->assigned_to }}) —</option>
                            @foreach($users as $u)
                                <option value="{{ $u->name }}" {{ $comm->assigned_to === $u->name ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="cd__field">
                        <label>Note</label>
                        <textarea name="note" rows="3">{{ $comm->note }}</textarea>
                    </div>

                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:4px;">
                        <button type="button" class="cd__qbtn" @click="editMode=false">Cancel</button>
                        <button type="submit" class="cd__qbtn cd__qbtn--primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Right col: Patient + Activity Log ── --}}
        <div style="display:flex;flex-direction:column;gap:16px;">

            {{-- Patient card ── --}}
            <div class="cd__card">
                <p class="cd__card-title">Patient</p>
                @if($comm->patient)
                    <a href="{{ route('patients.show', $comm->patient_id) }}" class="cd__patient-card">
                        <div class="cd__patient-avatar">{{ strtoupper(substr($comm->patient->first_name,0,1)) }}</div>
                        <div>
                            <div class="cd__patient-name">{{ $comm->patient->first_name }} {{ $comm->patient->last_name }}</div>
                            <div class="cd__patient-meta">{{ $comm->patient->phone }} · #{{ $comm->patient_id }}</div>
                        </div>
                    </a>
                @else
                    <p style="font-size:13px;color:#94a3b8;margin:0;">Not linked to a patient.</p>
                    @if(in_array($comm->comm_type, ['new_lead']))
                        <p style="font-size:11px;color:#cbd5e1;margin:4px 0 0;">When this lead converts, link them here.</p>
                    @endif
                @endif
            </div>

            {{-- Move To info ── --}}
            @if($comm->move_to && $comm->move_to !== 'stay')
            <div class="cd__card" style="border-color:#d8b4e2;">
                <p class="cd__card-title">Routing</p>
                <div style="font-size:13px;color:#4e0b52;font-weight:500;">
                    @php $moveLabels = ['prm_pipeline'=>'PRM Pipeline','follow_ups'=>'Follow-ups','calendar'=>'Calendar','task'=>'Create Task','archive'=>'Archive']; @endphp
                    Sent to: {{ $moveLabels[$comm->move_to] ?? ucfirst($comm->move_to) }}
                </div>
            </div>
            @endif

            {{-- Phase 1: Attempt + SLA summary card ── --}}
            <div class="cd__card" style="{{ $comm->sla_breached ? 'border-color:#fecaca;' : '' }}">
                <p class="cd__card-title">Tracking</p>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;text-align:center;margin-bottom:12px;">
                    <div style="padding:10px;background:#f8f4fa;border-radius:6px;">
                        <div style="font-size:22px;font-weight:700;color:#6a0f70;">{{ $comm->attempt_count ?: '0' }}</div>
                        <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">Attempts</div>
                    </div>
                    <div style="padding:10px;background:{{ $comm->sla_breached ? '#fff1f2' : '#f0fdf4' }};border-radius:6px;">
                        <div style="font-size:13px;font-weight:700;color:{{ $comm->sla_breached ? '#dc2626' : '#16a34a' }};">
                            @if($comm->sla_deadline)
                                {{ $comm->sla_breached ? 'Breached' : '✓ On time' }}
                            @else
                                —
                            @endif
                        </div>
                        <div style="font-size:10px;color:#94a3b8;text-transform:uppercase;letter-spacing:.05em;">SLA</div>
                    </div>
                </div>

                @if($comm->sla_deadline && $comm->status !== 'closed')
                <p style="font-size:11px;color:#64748b;margin:0 0 8px;">
                    SLA deadline: <strong>{{ $comm->sla_deadline->format('d M Y, g:ia') }}</strong>
                    ({{ $comm->sla_status }})
                </p>
                @endif

                @if($comm->last_attempt_at)
                <p style="font-size:11px;color:#64748b;margin:0;">
                    Last attempt: {{ $comm->last_attempt_at->format('d M Y, g:ia') }}
                    ({{ $comm->last_attempt_at->diffForHumans() }})
                </p>
                @else
                <p style="font-size:11px;color:#cbd5e1;margin:0;">No attempts logged yet.</p>
                @endif

                @if($comm->status !== 'closed')
                <button class="cd__qbtn" style="margin-top:10px;width:100%;justify-content:center;"
                        @click="showAttemptPanel=true; $nextTick(()=> document.querySelector('[name=attempt_notes]')?.focus())">
                    + Log Another Attempt
                </button>
                @endif
            </div>

            {{-- Activity Log ── --}}
            <div class="cd__card">
                <p class="cd__card-title">Activity Log</p>
                @if($comm->activityLogs->isEmpty())
                    <p style="font-size:12px;color:#cbd5e1;margin:0;">No activity recorded yet.</p>
                @else
                <div class="cd__timeline">
                    @foreach($comm->activityLogs as $log)
                    <div class="cd__tl-item">
                        <div class="cd__tl-dot">{{ $log->action_icon }}</div>
                        <div class="cd__tl-body">
                            <div class="cd__tl-action">{{ ucfirst($log->action) }}</div>
                            @if($log->description)
                                <div class="cd__tl-desc">{{ $log->description }}</div>
                            @endif
                            <div class="cd__tl-meta">
                                {{ $log->user_name ?? 'System' }} · {{ $log->logged_at->format('d M Y, g:ia') }}
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

        </div>
    </div>

</div>
@endsection
