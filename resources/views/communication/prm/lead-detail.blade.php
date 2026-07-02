@extends('layouts.app')
@section('title', ($lead['name'] ?? 'Lead') . ' — Lead Details')

@push('styles')
    @vite('resources/css/communication/prm.css')
@endpush

@section('content')

<div class="prm-topbar">
    <div class="prm-brand">
        <div class="prm-logo"><i class="ti ti-tooth" aria-hidden="true"></i></div>
        <div>
            <div class="prm-brand-name">PRM</div>
            <div class="prm-brand-sub">Patient Relationship Manager</div>
        </div>
    </div>
    <x-communication.top-nav-tabs :counts="$navCounts ?? []" active="pipeline" />
    <div class="prm-topbar-right">
        <div class="prm-search">
            <i class="ti ti-search" aria-hidden="true"></i>
            <input type="text" placeholder="Search leads by name or phone">
        </div>
        <div class="prm-notif"><i class="ti ti-bell" aria-hidden="true"></i><span class="notif-badge">0</span></div>
        <div class="prm-user">
            <div class="prm-avatar">N</div>
            <div><div class="prm-user-name">Dr. Neha</div><div class="prm-user-role">Front Desk</div></div>
            <i class="ti ti-chevron-down" aria-hidden="true"></i>
        </div>
    </div>
</div>

<div class="ld-page">

    {{-- Back + title --}}
    <div class="ld-page-header">
        <a href="{{ route('prm.index') }}" class="back-btn">
            <i class="ti ti-arrow-left" aria-hidden="true"></i> Pipeline Board
        </a>
        <div class="ld-page-actions">
            <a href="/communication/prm/lead/{{ $lead['id'] }}/edit" class="btn-outline-sm">
                <i class="ti ti-edit" aria-hidden="true"></i> Edit Lead
            </a>
            <button class="btn-outline-sm btn-danger-outline" onclick="confirmDeleteLead({{ $lead['id'] }})">
                <i class="ti ti-trash" aria-hidden="true"></i> Delete
            </button>
        </div>
    </div>

    <div class="ld-layout">

        {{-- ── LEFT COLUMN ──────────────────────────────────── --}}
        <div class="ld-left">

            {{-- Lead hero card --}}
            <div class="ld-hero-card">
                <div class="ld-hero-top">
                    <div class="ld-hero-avatar">
                        {{ strtoupper(substr($lead['name'], 0, 1)) }}{{ strtoupper(substr(explode(' ', $lead['name'])[1] ?? '', 0, 1)) }}
                    </div>
                    <div class="ld-hero-info">
                        <div class="ld-hero-name">{{ $lead['name'] }}</div>
                        <x-prm.stage-badge :stage="$lead['stage']" />
                        <div class="ld-hero-phone">
                            <i class="ti ti-phone" aria-hidden="true"></i> {{ $lead['phone'] }}
                        </div>
                        @if(!empty($lead['alt_phone']))
                            <div class="ld-hero-phone">
                                <i class="ti ti-phone" aria-hidden="true"></i> {{ $lead['alt_phone'] }}
                            </div>
                        @endif
                    </div>
                    <div class="ld-hero-meta">
                        <div class="ld-meta-row"><span>Status</span> <x-prm.stage-badge :stage="$lead['stage']" /></div>
                        <div class="ld-meta-row"><span>Pipeline Stage</span> <strong>2 / 6</strong></div>
                        <div class="ld-meta-row"><span>Lead ID</span> <strong>LD-{{ str_pad($lead['id'], 6, '0', STR_PAD_LEFT) }}</strong></div>
                    </div>
                </div>
                <div class="ld-hero-actions">
                    <a href="tel:{{ preg_replace('/\s+/', '', $lead['phone']) }}" class="ld-action-btn btn-call">
                        <i class="ti ti-phone" aria-hidden="true"></i> Call
                        <i class="ti ti-chevron-down" aria-hidden="true"></i>
                    </a>
                    <a href="https://wa.me/91{{ preg_replace('/\s+/', '', $lead['phone']) }}" target="_blank" class="ld-action-btn btn-wa">
                        <i class="ti ti-brand-whatsapp" aria-hidden="true"></i> WhatsApp
                    </a>
                </div>
            </div>

            {{-- Info strip --}}
            <div class="ld-info-strip">
                <div class="ld-info-item">
                    <i class="ti ti-dental" aria-hidden="true"></i>
                    <div class="info-label">Treatment Interest</div>
                    <div class="info-val">{{ $lead['treatment'] }}</div>
                </div>
                <div class="ld-info-item">
                    <i class="ti ti-phone" aria-hidden="true"></i>
                    <div class="info-label">Source</div>
                    <div class="info-val">{{ $lead['source'] }}</div>
                </div>
                <div class="ld-info-item">
                    <i class="ti ti-user" aria-hidden="true"></i>
                    <div class="info-label">Assigned To</div>
                    <div class="info-val">{{ $lead['assigned_to'] }}</div>
                </div>
                <div class="ld-info-item">
                    <i class="ti ti-calendar" aria-hidden="true"></i>
                    <div class="info-label">Lead Created</div>
                    <div class="info-val">{{ \Carbon\Carbon::parse($lead['created_at'])->format('d M Y, h:i A') }}</div>
                </div>
            </div>

            {{-- ── AI Insight (Phase 1) ───────────────────────────────────── --}}
            @if(config('prm.ai.enabled'))
            <div class="ld-ai-card" id="ldAiCard"
                 style="border:1px solid #E6E2FB;background:#F7F6FE;border-radius:10px;padding:12px 14px;margin-bottom:14px;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:6px;">
                    <span style="display:inline-flex;align-items:center;gap:6px;font-size:12px;font-weight:600;color:#534AB7;">
                        <i class="ti ti-sparkles" aria-hidden="true"></i> AI Insight
                    </span>
                    <button class="btn-outline-sm" id="ldAiRerun"
                            data-lead="{{ $lead['id'] }}"
                            data-token="{{ csrf_token() }}"
                            onclick="rerunLeadAi(this)">
                        <i class="ti ti-refresh" aria-hidden="true"></i> Re-run AI
                    </button>
                </div>

                <div id="ldAiBody">
                    @if(!empty($lead['ai_summary']))
                        <div style="font-size:13px;color:#333;margin-bottom:6px;">“{{ $lead['ai_summary'] }}”</div>
                        <div style="display:flex;flex-wrap:wrap;gap:6px;">
                            @if(!empty($lead['ai_treatment_label']))
                                <span style="font-size:11px;font-weight:600;color:#534AB7;background:#EEEDFE;border-radius:20px;padding:3px 9px;">
                                    {{ $lead['ai_treatment_label'] }}
                                </span>
                            @endif
                            @if(!empty($lead['ai_urgency']))
                                <span style="font-size:11px;font-weight:600;border-radius:20px;padding:3px 9px;
                                    color:{{ $lead['ai_urgency']==='high' ? '#E24B4A' : ($lead['ai_urgency']==='medium' ? '#854F0B' : '#1D9E75') }};
                                    background:{{ $lead['ai_urgency']==='high' ? '#FBE9E9' : ($lead['ai_urgency']==='medium' ? '#FAEEDA' : '#E1F5EE') }};">
                                    {{ ucfirst($lead['ai_urgency']) }} urgency
                                </span>
                            @endif
                            @if(!empty($lead['ai_estimated_value']))
                                <span style="font-size:11px;font-weight:600;color:#0F6E56;background:#E1F5EE;border-radius:20px;padding:3px 9px;">
                                    Est. Rs. {{ number_format($lead['ai_estimated_value']) }}
                                </span>
                            @endif
                        </div>
                    @else
                        <div style="font-size:12px;color:#8A8A86;">Not analysed yet — click “Re-run AI”.</div>
                    @endif
                </div>
            </div>
            @endif

            {{-- Next follow-up --}}
            @if(!empty($lead['followup_date']))
            <div class="ld-followup-card {{ $lead['is_overdue'] ? 'fu-overdue' : '' }}">
                <div class="fu-icon"><i class="ti ti-calendar-event" aria-hidden="true"></i></div>
                <div class="fu-info">
                    <div class="fu-label">Next Follow-up</div>
                    <div class="fu-date">
                        {{ \Carbon\Carbon::parse($lead['followup_date'])->format('d M Y (D)') }}
                        @if($lead['is_overdue'])
                            <span class="fu-due-tag">
                                <i class="ti ti-alert-triangle" aria-hidden="true"></i> Overdue {{ $lead['overdue_days'] }}d
                            </span>
                        @elseif(\Carbon\Carbon::parse($lead['followup_date'])->isToday())
                            <span class="fu-due-tag fu-today">
                                <i class="ti ti-bell" aria-hidden="true"></i> Due Today
                            </span>
                        @endif
                    </div>
                    <div class="fu-time">{{ $lead['followup_time'] }}</div>
                </div>
                <button class="btn-outline-sm" onclick="openReschedule({{ $lead['id'] }})">
                    <i class="ti ti-calendar-plus" aria-hidden="true"></i> Reschedule
                </button>
            </div>
            @endif

            {{-- Last interaction --}}
            @if(!empty($lead['activity']))
            @php $last = $lead['activity'][0]; @endphp
            <div class="ld-last-interaction">
                <div class="li-head">
                    <span><i class="ti ti-message" aria-hidden="true"></i> Last Interaction</span>
                    <div class="li-meta">
                        <i class="ti ti-calendar" aria-hidden="true"></i> {{ \Carbon\Carbon::parse($last['date'])->format('d M Y') }}
                        &nbsp; <i class="ti ti-clock" aria-hidden="true"></i> {{ $last['time'] }}
                        &nbsp; by {{ $last['by'] }}
                    </div>
                </div>
                <p class="li-note">{{ $last['note'] }}</p>
            </div>
            @endif

            {{-- Tabs: Activity / Patient Info / Documents / Tasks --}}
            <div class="ld-tabs">
                <button class="ld-tab active" onclick="switchLdTab('activity', this)">
                    <i class="ti ti-clipboard-list" aria-hidden="true"></i> Activity & Notes
                </button>
                <button class="ld-tab" onclick="switchLdTab('patient', this)">
                    <i class="ti ti-user" aria-hidden="true"></i> Patient Info
                </button>
                <button class="ld-tab" onclick="switchLdTab('documents', this)">
                    <i class="ti ti-file" aria-hidden="true"></i> Documents (0)
                </button>
                <button class="ld-tab" onclick="switchLdTab('tasks', this)">
                    <i class="ti ti-checkbox" aria-hidden="true"></i> Tasks (0)
                </button>
            </div>

            {{-- Activity tab --}}
            <div id="tab-activity" class="ld-tab-panel">
                <div class="activity-timeline">
                    @forelse($lead['activity'] as $act)
                    @php
                    $iconMap = ['call'=>'phone','followup'=>'calendar-event','whatsapp'=>'brand-whatsapp','note'=>'notes','appointment'=>'calendar'];
                    $colorMap = ['call'=>'act-call','followup'=>'act-fu','whatsapp'=>'act-wa','note'=>'act-note','appointment'=>'act-appt'];
                    @endphp
                    <div class="act-item">
                        <div class="act-icon-wrap {{ $colorMap[$act['type']] ?? 'act-note' }}">
                            <i class="ti ti-{{ $iconMap[$act['type']] ?? 'notes' }}" aria-hidden="true"></i>
                        </div>
                        <div class="act-body">
                            <div class="act-label">
                                {{ $act['label'] }}
                                @if(!empty($act['outcome']))
                                    <span class="act-outcome">{{ $act['outcome'] }}</span>
                                @endif
                            </div>
                            <div class="act-note">{{ $act['note'] }}</div>
                        </div>
                        <div class="act-meta">
                            {{ \Carbon\Carbon::parse($act['date'])->format('d M Y') }}, {{ $act['time'] }}<br>
                            by {{ $act['by'] }}
                        </div>
                    </div>
                    @empty
                    <div class="act-empty">No activity recorded yet.</div>
                    @endforelse
                </div>
                @if(count($lead['activity']) > 3)
                <button class="view-all-act">
                    View All Activities <i class="ti ti-chevron-down" aria-hidden="true"></i>
                </button>
                @endif
            </div>

            {{-- Patient info tab --}}
            <div id="tab-patient" class="ld-tab-panel" style="display:none">
                <div class="info-grid">
                    <div class="info-kv"><span>Email</span><strong>{{ $lead['email'] ?: '—' }}</strong></div>
                    <div class="info-kv"><span>Date of Birth</span><strong>{{ $lead['dob'] ? \Carbon\Carbon::parse($lead['dob'])->format('d M Y') : '—' }}</strong></div>
                    <div class="info-kv"><span>Gender</span><strong>{{ $lead['gender'] ?: '—' }}</strong></div>
                    <div class="info-kv"><span>Occupation</span><strong>{{ $lead['occupation'] ?: '—' }}</strong></div>
                    <div class="info-kv"><span>Location</span><strong>{{ $lead['location'] ?: '—' }}</strong></div>
                    <div class="info-kv"><span>Language</span><strong>{{ $lead['language'] ?: '—' }}</strong></div>
                    <div class="info-kv"><span>Preferred Contact</span><strong>{{ ucfirst($lead['preferred_contact']) }}</strong></div>
                    <div class="info-kv"><span>Referred By</span><strong>{{ $lead['referred_by'] ?: '—' }}</strong></div>
                </div>
                @if(!empty($lead['notes']))
                <div class="info-notes-block">
                    <div class="info-notes-label">Notes</div>
                    <p>{{ $lead['notes'] }}</p>
                </div>
                @endif
                @if(!empty($lead['tags']))
                <div class="info-tags-block">
                    @foreach($lead['tags'] as $tag)
                        <span class="lead-tag">{{ $tag }}</span>
                    @endforeach
                </div>
                @endif
            </div>

            <div id="tab-documents" class="ld-tab-panel" style="display:none">
                <div class="act-empty">No documents uploaded yet.</div>
            </div>
            <div id="tab-tasks" class="ld-tab-panel" style="display:none">
                <div class="act-empty">No tasks assigned yet.</div>
            </div>

        </div>

        {{-- ── RIGHT COLUMN ─────────────────────────────────── --}}
        <div class="ld-right">
            <x-prm.stage-selector :current="$lead['stage']" :leadId="$lead['id']" />

            <div class="ld-quick-actions">
                <div class="qa-title">Quick Actions</div>
                @if(config('prm.replies.enabled'))
                <button class="qa-btn" style="color:#534AB7;"
                        onclick="openDraftReply()"
                        data-lead="{{ $lead['id'] }}"
                        data-name="{{ $lead['name'] }}"
                        data-phone="{{ preg_replace('/\s+/', '', $lead['phone']) }}"
                        data-email="{{ $lead['email'] ?? '' }}"
                        data-token="{{ csrf_token() }}"
                        id="draftReplyTrigger">
                    <i class="ti ti-sparkles" aria-hidden="true"></i> AI Draft Reply
                </button>
                @endif
                <button class="qa-btn" onclick="openAddNote({{ $lead['id'] }})">
                    <i class="ti ti-notes" aria-hidden="true"></i> Add Note
                </button>
                <button class="qa-btn qa-btn-danger" onclick="markNotReachable({{ $lead['id'] }})">
                    <i class="ti ti-x" aria-hidden="true"></i> Not Reachable
                </button>
                <button class="qa-btn qa-btn-amber" onclick="openReschedule({{ $lead['id'] }})">
                    <i class="ti ti-calendar-plus" aria-hidden="true"></i> Reschedule
                </button>
                <button class="qa-btn qa-btn-success" onclick="markAsDone({{ $lead['id'] }})">
                    <i class="ti ti-check" aria-hidden="true"></i> Mark as Done
                </button>
            </div>

            <div class="ld-convert-btn">
                <button class="btn-convert-patient" onclick="openConvertToPatient({{ $lead['id'] }})">
                    <i class="ti ti-user-plus" aria-hidden="true"></i> Convert to Patient
                </button>
            </div>
        </div>

    </div>
</div>

{{-- ── AI Draft Reply modal (Phase 3) ─────────────────────────────────── --}}
@if(config('prm.replies.enabled'))
<div id="draftReplyOverlay"
     style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,0.45);align-items:center;justify-content:center;padding:20px;">
    <div style="background:#fff;border-radius:14px;width:100%;max-width:520px;box-shadow:0 20px 60px rgba(0,0,0,0.25);overflow:hidden;">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 18px;border-bottom:1px solid #eee;">
            <span style="display:inline-flex;align-items:center;gap:8px;font-weight:600;color:#534AB7;">
                <i class="ti ti-sparkles"></i> AI Draft Reply
            </span>
            <button onclick="closeDraftReply()" style="background:none;border:none;cursor:pointer;font-size:18px;color:#888;"><i class="ti ti-x"></i></button>
        </div>
        <div style="padding:16px 18px;">
            <div style="display:flex;gap:8px;margin-bottom:12px;align-items:center;">
                <label style="font-size:12px;color:#5A5A56;">Channel</label>
                <select id="drChannel" style="flex:1;padding:7px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px;">
                    <option value="whatsapp">WhatsApp</option>
                    <option value="sms">SMS</option>
                    <option value="email">Email</option>
                </select>
                <button onclick="generateDraft()" id="drGenerateBtn"
                        style="padding:7px 12px;border:none;border-radius:8px;background:#534AB7;color:#fff;font-size:13px;font-weight:600;cursor:pointer;white-space:nowrap;">
                    <i class="ti ti-wand"></i> Generate
                </button>
            </div>
            <textarea id="drText" rows="7" placeholder="Click Generate to draft a reply, then edit before sending…"
                      style="width:100%;padding:10px 12px;border:1px solid #ddd;border-radius:8px;font-size:13px;line-height:1.5;resize:vertical;font-family:inherit;"></textarea>
            <div style="font-size:11px;color:#8A8A86;margin-top:6px;">
                <i class="ti ti-info-circle"></i> AI drafts for your review — nothing is sent until you send it.
            </div>
        </div>
        <div style="display:flex;gap:8px;justify-content:flex-end;padding:14px 18px;border-top:1px solid #eee;background:#FAFAFB;">
            <button onclick="copyDraft()" style="padding:8px 14px;border:1px solid #ddd;border-radius:8px;background:#fff;font-size:13px;cursor:pointer;">
                <i class="ti ti-copy"></i> Copy
            </button>
            <button onclick="sendDraft()" id="drSendBtn" style="padding:8px 14px;border:none;border-radius:8px;background:#0F6E56;color:#fff;font-size:13px;font-weight:600;cursor:pointer;">
                <i class="ti ti-send"></i> Open &amp; Send
            </button>
        </div>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script src="{{ asset('js/communication/lead-drawer.js') }}"></script>
<script>
// PRM AI — re-run enrichment on this lead and refresh the AI card in place.
function rerunLeadAi(btn) {
    const id    = btn.dataset.lead;
    const token = btn.dataset.token;
    const body  = document.getElementById('ldAiBody');
    const orig  = btn.innerHTML;

    btn.disabled  = true;
    btn.innerHTML = '<i class="ti ti-loader-2"></i> Analysing…';
    if (body) body.style.opacity = '0.5';

    fetch(`/communication/prm/lead/${id}/enrich`, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': token,
            'Accept': 'application/json',
        },
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { alert(data.message || 'AI run failed.'); return; }
        const e = data.enrichment || {};
        let html = '';
        if (e.ai_summary) {
            html += `<div style="font-size:13px;color:#333;margin-bottom:6px;">“${e.ai_summary}”</div>`;
            html += '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
            if (e.ai_treatment_label) {
                html += `<span style="font-size:11px;font-weight:600;color:#534AB7;background:#EEEDFE;border-radius:20px;padding:3px 9px;">${e.ai_treatment_label}</span>`;
            }
            if (e.ai_urgency) {
                const uc = e.ai_urgency==='high' ? '#E24B4A' : (e.ai_urgency==='medium' ? '#854F0B' : '#1D9E75');
                const ub = e.ai_urgency==='high' ? '#FBE9E9' : (e.ai_urgency==='medium' ? '#FAEEDA' : '#E1F5EE');
                const ut = e.ai_urgency.charAt(0).toUpperCase() + e.ai_urgency.slice(1);
                html += `<span style="font-size:11px;font-weight:600;color:${uc};background:${ub};border-radius:20px;padding:3px 9px;">${ut} urgency</span>`;
            }
            if (e.ai_estimated_value) {
                const v = Number(e.ai_estimated_value).toLocaleString('en-IN');
                html += `<span style="font-size:11px;font-weight:600;color:#0F6E56;background:#E1F5EE;border-radius:20px;padding:3px 9px;">Est. Rs. ${v}</span>`;
            }
            html += '</div>';
        } else {
            html = '<div style="font-size:12px;color:#8A8A86;">AI could not classify this lead (too little info).</div>';
        }
        if (body) body.innerHTML = html;
    })
    .catch(() => alert('Could not reach the server. Is the AI engine running?'))
    .finally(() => {
        btn.disabled  = false;
        btn.innerHTML = orig;
        if (body) body.style.opacity = '1';
    });
}
</script>
<script>
// PRM AI Draft Reply (Phase 3) — generate, edit, then send via the chosen channel.
let _dr = { id: null, name: '', phone: '', email: '', token: '' };

function openDraftReply() {
    const t = document.getElementById('draftReplyTrigger');
    if (!t) return;
    _dr = {
        id: t.dataset.lead, name: t.dataset.name, phone: t.dataset.phone,
        email: t.dataset.email, token: t.dataset.token,
    };
    document.getElementById('drText').value = '';
    // Default channel to email only if there's no phone.
    document.getElementById('drChannel').value = _dr.phone ? 'whatsapp' : 'email';
    document.getElementById('draftReplyOverlay').style.display = 'flex';
}
function closeDraftReply() {
    document.getElementById('draftReplyOverlay').style.display = 'none';
}

function generateDraft() {
    const btn = document.getElementById('drGenerateBtn');
    const ta  = document.getElementById('drText');
    const channel = document.getElementById('drChannel').value;
    const orig = btn.innerHTML;
    btn.disabled = true; btn.innerHTML = '<i class="ti ti-loader-2"></i> Drafting…';

    fetch(`/communication/prm/lead/${_dr.id}/draft-reply`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': _dr.token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ channel }),
    })
    .then(r => r.json())
    .then(d => {
        if (!d.success) { alert(d.message || 'Draft failed.'); return; }
        ta.value = d.draft || '';
        if (d.email) _dr.email = d.email;
    })
    .catch(() => alert('Could not reach the server. Is the AI engine running?'))
    .finally(() => { btn.disabled = false; btn.innerHTML = orig; });
}

function copyDraft() {
    const ta = document.getElementById('drText');
    ta.select();
    navigator.clipboard.writeText(ta.value).then(() => {
        const b = event.target.closest('button'); const o = b.innerHTML;
        b.innerHTML = '<i class="ti ti-check"></i> Copied'; setTimeout(() => b.innerHTML = o, 1500);
    });
}

function sendDraft() {
    const channel = document.getElementById('drChannel').value;
    const text = document.getElementById('drText').value.trim();
    if (!text) { alert('Nothing to send — generate or type a message first.'); return; }
    const enc = encodeURIComponent(text);

    let url = '';
    if (channel === 'whatsapp') url = `https://wa.me/91${_dr.phone}?text=${enc}`;
    else if (channel === 'sms') url = `sms:${_dr.phone}?body=${enc}`;
    else url = `mailto:${_dr.email || ''}?subject=${encodeURIComponent('Regarding your enquiry')}&body=${enc}`;
    window.open(url, '_blank');

    // Log the send to the activity timeline (best-effort).
    fetch(`/communication/prm/lead/${_dr.id}/log-reply`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': _dr.token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ channel, message: text }),
    }).finally(() => closeDraftReply());
}
</script>
@endpush
