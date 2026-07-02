{{--
    B2B Comm — Show/Detail
    Phase 4 · Dentfluence Communication OS
--}}
@extends('layouts.communication')

@push('communication-styles')
<style>
.b2b-show-wrap { max-width:760px; margin:28px auto; padding:0 16px 48px; }
.b2b-show-header { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:20px; }
.b2b-show-header h2 { font-size:17px; font-weight:700; color:#0f172a; margin:0; }
.b2b-show-header .sub { font-size:13px; color:#64748b; margin-top:3px; }

.b2b-panel { background:#fff; border:1px solid #e2e8f0; border-radius:10px; margin-bottom:16px; }
.b2b-panel-head { padding:12px 18px; border-bottom:1px solid #f1f5f9; font-size:12px; font-weight:700; color:#6a0f70; text-transform:uppercase; letter-spacing:.06em; }
.b2b-panel-body { padding:16px 18px; }

.b2b-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 24px; }
.b2b-item label { display:block; font-size:11px; font-weight:600; color:#94a3b8; text-transform:uppercase; letter-spacing:.05em; margin-bottom:3px; }
.b2b-item .val { font-size:14px; color:#0f172a; font-weight:500; }

.b2b-status-badge { font-size:12px; font-weight:700; padding:4px 10px; border-radius:10px; }
.b2b-status--pending   { background:#fffbeb; color:#d97706; }
.b2b-status--waiting   { background:#eff6ff; color:#2563eb; }
.b2b-status--overdue   { background:#fff1f2; color:#dc2626; }
.b2b-status--closed    { background:#f0fdf4; color:#16a34a; }

.b2b-btn { display:inline-flex; align-items:center; gap:5px; padding:7px 16px; font-size:13px; font-weight:600; border-radius:7px; cursor:pointer; border:none; transition:background .15s; text-decoration:none; white-space:nowrap; }
.b2b-btn--primary { background:#6a0f70; color:#fff; }
.b2b-btn--primary:hover { background:#4e0b52; color:#fff; }
.b2b-btn--outline { background:#fff; color:#374151; border:1px solid #d1d5db; }
.b2b-btn--danger  { background:#fff1f2; color:#dc2626; border:1px solid #fecaca; }
.b2b-btn--sm      { padding:5px 10px; font-size:12px; }

.b2b-activity-item { display:flex; gap:10px; padding:10px 0; border-bottom:1px solid #f1f5f9; }
.b2b-activity-item:last-child { border-bottom:none; }
.b2b-activity-dot { width:8px; height:8px; border-radius:50%; background:#6a0f70; margin-top:4px; flex-shrink:0; }
.b2b-activity-body { font-size:13px; color:#374151; flex:1; }
.b2b-activity-time { font-size:11px; color:#94a3b8; margin-top:2px; }

/* Attempt + close forms */
.b2b-action-form { background:#f8fafc; border:1px solid #e2e8f0; border-radius:8px; padding:14px 16px; margin-top:12px; }
.b2b-action-form h4 { margin:0 0 10px; font-size:13px; font-weight:700; color:#374151; }
.b2b-action-form textarea, .b2b-action-form select, .b2b-action-form input { width:100%; padding:8px 10px; font-size:13px; border:1px solid #d1d5db; border-radius:6px; box-sizing:border-box; margin-bottom:8px; }
</style>
@endpush

@section('communication-content')
<div class="b2b-show-wrap">

    {{-- Back link --}}
    <div style="margin-bottom:16px;">
        <a href="{{ route('communication.b2b.index') }}" style="font-size:13px;color:#6a0f70;">← B2B Inbox</a>
    </div>

    {{-- Page header --}}
    <div class="b2b-show-header">
        <div>
            <h2>{{ $comm->person_name }}</h2>
            <div class="sub">
                @switch($comm->contact_type)
                    @case('lab') Lab @break
                    @case('vendor') Vendor @break
                    @case('consultant')Consultant @break
                @endswitch
                &nbsp;·&nbsp;
                {{ \App\Models\CommunicationQueue::B2B_SUBTYPES[$comm->b2b_subtype] ?? '—' }}
                &nbsp;·&nbsp;
                <span class="b2b-status-badge b2b-status--{{ str_replace('_for_patient','',$comm->status) }}">
                    {{ $comm->status_label }}
                </span>
            </div>
        </div>
        @if($comm->sla_breached)
        <span style="font-size:12px;font-weight:700;color:#dc2626;background:#fff1f2;padding:5px 10px;border-radius:6px;">
            SLA Breached
        </span>
        @endif
    </div>

    @if(session('success'))
    <div style="background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;padding:10px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;">
        ✓ {{ session('success') }}
    </div>
    @endif

    {{-- Details panel --}}
    <div class="b2b-panel">
        <div class="b2b-panel-head">Contact Details</div>
        <div class="b2b-panel-body">
            <div class="b2b-grid">
                <div class="b2b-item">
                    <label>Phone</label>
                    <div class="val">{{ $comm->phone ?: '—' }}</div>
                </div>
                <div class="b2b-item">
                    <label>Channel</label>
                    <div class="val">{{ $comm->channel_icon }} {{ $comm->channel_label }}</div>
                </div>
                <div class="b2b-item">
                    <label>Priority</label>
                    <div class="val">{{ ucfirst($comm->priority ?? 'medium') }}</div>
                </div>
                <div class="b2b-item">
                    <label>SLA Status</label>
                    <div class="val">{{ $comm->sla_status }}</div>
                </div>
                <div class="b2b-item">
                    <label>Attempts Made</label>
                    <div class="val">{{ $comm->attempt_count }}</div>
                </div>
                <div class="b2b-item">
                    <label>Last Attempt</label>
                    <div class="val">{{ $comm->last_attempt_at ? $comm->last_attempt_at->format('d M Y, H:i') : '—' }}</div>
                </div>
                @if($comm->follow_up_date)
                <div class="b2b-item">
                    <label>Follow-up Date</label>
                    <div class="val">{{ $comm->follow_up_date->format('d M Y') }}</div>
                </div>
                @endif
                @if($comm->opportunity_value)
                <div class="b2b-item">
                    <label>Value (Rs. )</label>
                    <div class="val" style="color:#6a0f70;font-weight:700;">Rs. {{ number_format($comm->opportunity_value) }}</div>
                </div>
                @endif
            </div>
            @if($comm->note)
            <div style="margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;">
                <div style="font-size:11px;font-weight:600;color:#94a3b8;text-transform:uppercase;margin-bottom:5px;">Notes</div>
                <div style="font-size:14px;color:#374151;">{{ $comm->note }}</div>
            </div>
            @endif
        </div>
    </div>

    {{-- Lab case panel --}}
    @if($comm->labCase)
    <div class="b2b-panel">
        <div class="b2b-panel-head">Linked Lab Case</div>
        <div class="b2b-panel-body">
            <div class="b2b-grid">
                <div class="b2b-item">
                    <label>Case Number</label>
                    <div class="val" style="font-weight:700;color:#7c3aed;">#{{ $comm->labCase->case_number }}</div>
                </div>
                <div class="b2b-item">
                    <label>Lab</label>
                    <div class="val">{{ $comm->labCase->labVendor?->name ?? '—' }}</div>
                </div>
                <div class="b2b-item">
                    <label>Status</label>
                    <div class="val">{{ \App\Models\LabCase::STATUS_LABELS[$comm->labCase->status] ?? $comm->labCase->status }}</div>
                </div>
                <div class="b2b-item">
                    <label>Expected Date</label>
                    <div class="val">{{ $comm->labCase->expected_date ? \Carbon\Carbon::parse($comm->labCase->expected_date)->format('d M Y') : '—' }}</div>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Outcome (if closed) --}}
    @if($comm->status === 'closed' && $comm->outcome)
    <div class="b2b-panel">
        <div class="b2b-panel-head">Outcome</div>
        <div class="b2b-panel-body">
            <div style="font-size:15px;font-weight:700;color:#15803d;">
                {{ \App\Models\CommunicationQueue::OUTCOMES[$comm->outcome]
                    ?? \App\Models\CommunicationQueue::B2B_OUTCOMES[$comm->outcome]
                    ?? $comm->outcome }}
            </div>
            @if($comm->outcome_reason)
            <div style="font-size:13px;color:#64748b;margin-top:6px;">{{ $comm->outcome_reason }}</div>
            @endif
        </div>
    </div>
    @endif

    {{-- Actions --}}
    @if($comm->status !== 'closed')
    <div class="b2b-panel">
        <div class="b2b-panel-head">Actions</div>
        <div class="b2b-panel-body">
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button onclick="toggleForm('attempt-form')" class="b2b-btn b2b-btn--outline">
                    Log Attempt
                </button>
                <button onclick="toggleForm('close-form')" class="b2b-btn b2b-btn--danger">
                    ✓ Close with Outcome
                </button>
            </div>

            {{-- Attempt form --}}
            <div id="attempt-form" class="b2b-action-form" style="display:none;">
                <h4>Log Contact Attempt</h4>
                <form method="POST" action="{{ route('communication.b2b.attempt', $comm->id) }}">
                    @csrf
                    <textarea name="notes" placeholder="What happened? e.g. No answer, Left voicemail, Spoke to Ravi..." rows="3"></textarea>
                    <button type="submit" class="b2b-btn b2b-btn--primary b2b-btn--sm">Save Attempt</button>
                </form>
            </div>

            {{-- Close form --}}
            <div id="close-form" class="b2b-action-form" style="display:none;">
                <h4>Close this Communication</h4>
                <form method="POST" action="{{ route('communication.b2b.close', $comm->id) }}">
                    @csrf
                    <select name="outcome" required>
                        <option value="">— Select outcome —</option>
                        @foreach(\App\Models\CommunicationQueue::OUTCOMES as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                        <optgroup label="B2B Specific">
                        @foreach(\App\Models\CommunicationQueue::B2B_OUTCOMES as $val => $label)
                        <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                        </optgroup>
                    </select>
                    <textarea name="outcome_reason" placeholder="Brief reason (optional)..." rows="2"></textarea>
                    <button type="submit" class="b2b-btn b2b-btn--primary b2b-btn--sm">Close Communication</button>
                </form>
            </div>
        </div>
    </div>
    @endif

    {{-- Activity log --}}
    @if($comm->activityLogs->isNotEmpty())
    <div class="b2b-panel">
        <div class="b2b-panel-head">Activity Timeline</div>
        <div class="b2b-panel-body">
            @foreach($comm->activityLogs as $log)
            <div class="b2b-activity-item">
                <div class="b2b-activity-dot"></div>
                <div>
                    <div class="b2b-activity-body">{{ $log->message }}</div>
                    <div class="b2b-activity-time">
                        {{ $log->logged_at->format('d M Y, H:i') }}
                        @if($log->actor_name) · {{ $log->actor_name }} @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

</div>
@endsection

@push('scripts')
<script>
function toggleForm(id) {
    const el = document.getElementById(id);
    el.style.display = el.style.display === 'none' ? 'block' : 'none';
}
</script>
@endpush
