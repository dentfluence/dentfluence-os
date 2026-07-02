{{--
    Communication OS — Recall Engine Dashboard (Phase 2)
    Admin-density view per UI complexity rule:
    Data entry = dead simple. Admin/KPI views = full detail.
    This is an admin view — full table density, filters, run-now button.
--}}
@extends('layouts.communication')

@section('title', 'Recall Engine — Communication OS')

@push('communication-styles')
<style>
/* ── Recall Engine Admin View ─────────────────────────────────────── */
.re-page   { font-family: var(--comm-font,'Inter',sans-serif); min-height:100vh; background:#f8fafc; }

/* Header */
.re-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; padding:14px 24px; background:#fff; border-bottom:1px solid #e2e8f0; }
.re-header__left  { display:flex; align-items:center; gap:10px; }
.re-header__icon  { width:36px; height:36px; background:#f3e8ff; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#7c3aed; }
.re-header__title { font-size:15px; font-weight:700; color:#0f172a; margin:0; }
.re-header__sub   { font-size:12px; color:#64748b; margin:2px 0 0; }
.re-header__right { display:flex; align-items:center; gap:8px; }

/* Buttons */
.re-btn { display:inline-flex; align-items:center; gap:5px; padding:7px 14px; font-size:13px; font-weight:500; border-radius:6px; cursor:pointer; border:none; text-decoration:none; transition:background .15s; white-space:nowrap; }
.re-btn--primary  { background:#6a0f70; color:#fff; }
.re-btn--primary:hover { background:#4e0b52; color:#fff; }
.re-btn--outline  { background:#fff; color:#374151; border:1px solid #d1d5db; }
.re-btn--outline:hover { background:#f9fafb; }
.re-btn--sm       { padding:5px 10px; font-size:12px; }
.re-btn--danger   { background:#dc2626; color:#fff; }
.re-btn--danger:hover { background:#b91c1c; color:#fff; }

/* Stat cards row */
.re-stats { display:flex; flex-wrap:wrap; gap:10px; padding:16px 24px; background:#fff; border-bottom:1px solid #e2e8f0; }
.re-stat-card { flex:1 1 130px; border:1px solid #e2e8f0; border-radius:8px; padding:10px 14px; min-width:120px; cursor:pointer; transition:border-color .15s, box-shadow .15s; text-decoration:none; }
.re-stat-card:hover, .re-stat-card.is-active { border-color:#6a0f70; box-shadow:0 0 0 2px #f3e8ff; }
.re-stat-card.is-active { background:#faf5ff; }
.re-stat-card__label  { font-size:11px; color:#64748b; font-weight:500; margin-bottom:4px; }
.re-stat-card__count  { font-size:20px; font-weight:700; color:#0f172a; line-height:1; }
.re-stat-card__high   { font-size:10px; color:#dc2626; margin-top:2px; }
.re-stat-card--all    { background:#f8fafc; }

/* Filter bar */
.re-filters { display:flex; align-items:flex-end; flex-wrap:wrap; gap:10px; padding:10px 24px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
.re-filter-group { display:flex; flex-direction:column; gap:3px; }
.re-filter-label  { font-size:10px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; }
.re-filter-select { border:1px solid #e2e8f0; padding:5px 10px; font-size:12px; border-radius:5px; background:#fff; outline:none; min-width:120px; }
.re-filter-select:focus { border-color:#6a0f70; }

/* Table */
.re-table-wrap { padding:16px 24px; }
.re-table { width:100%; border-collapse:collapse; font-size:13px; background:#fff; border:1px solid #e2e8f0; border-radius:8px; overflow:hidden; }
.re-table th { background:#f8fafc; padding:9px 12px; text-align:left; font-size:11px; font-weight:600; color:#64748b; text-transform:uppercase; letter-spacing:.05em; border-bottom:1px solid #e2e8f0; white-space:nowrap; }
.re-table td { padding:10px 12px; border-bottom:1px solid #f1f5f9; vertical-align:middle; }
.re-table tr:last-child td { border-bottom:none; }
.re-table tr:hover td { background:#faf5ff; }
.re-table__empty { text-align:center; padding:40px 20px; color:#94a3b8; }

/* Badges */
.re-badge { display:inline-flex; align-items:center; padding:2px 8px; font-size:11px; font-weight:600; border-radius:12px; white-space:nowrap; }
.re-badge--high   { background:#fee2e2; color:#dc2626; }
.re-badge--medium { background:#fef9c3; color:#92400e; }
.re-badge--low    { background:#f0fdf4; color:#166534; }
.re-badge--pending { background:#eff6ff; color:#1d4ed8; }
.re-badge--overdue { background:#fff1f2; color:#dc2626; }
.re-badge--closed  { background:#f1f5f9; color:#64748b; }

/* Trigger pill */
.re-trigger { display:inline-flex; align-items:center; gap:4px; font-size:11px; font-weight:500; padding:2px 8px; border-radius:12px; background:#f3e8ff; color:#6d28d9; white-space:nowrap; }

/* Note cell */
.re-note { max-width:260px; font-size:12px; color:#475569; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }

/* Patient cell */
.re-patient-name { font-weight:600; color:#0f172a; font-size:13px; }
.re-patient-phone { font-size:11px; color:#64748b; }

/* Run summary flash */
.re-run-summary { background:#f0fdf4; border:1px solid #86efac; border-radius:8px; padding:14px 20px; margin:12px 24px 0; display:flex; align-items:flex-start; gap:12px; }
.re-run-summary__icon { color:#16a34a; margin-top:1px; flex-shrink:0; }
.re-run-summary__title { font-size:14px; font-weight:600; color:#15803d; margin:0 0 6px; }
.re-run-summary__grid { display:flex; flex-wrap:wrap; gap:6px 16px; }
.re-run-summary__item { font-size:12px; color:#166534; }

/* Pagination */
.re-pagination { display:flex; justify-content:space-between; align-items:center; padding:10px 24px 16px; font-size:12px; color:#64748b; }

/* Alert banner */
.re-alert { margin:10px 24px 0; padding:10px 16px; border-radius:6px; font-size:13px; display:flex; align-items:center; gap:8px; }
.re-alert--success { background:#f0fdf4; border:1px solid #86efac; color:#15803d; }
.re-alert--info    { background:#eff6ff; border:1px solid #bfdbfe; color:#1d4ed8; }
</style>
@endpush

@section('communication-content')

{{-- Back nav --}}
<div style="padding:10px 20px 10px 28px;border-bottom:1px solid rgba(0,0,0,0.06);background:#fff;position:relative;z-index:10;">
    <a href="{{ route('communication.index') }}" style="font-size:12px;color:#5A5A56;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
        Back to Communication OS
    </a>
</div>

<div class="re-page">

    {{-- ── Page Header ──────────────────────────────────────────────────────── --}}
    <div class="re-header">
        <div class="re-header__left">
            <div class="re-header__icon">
                {{-- Bell icon --}}
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
                </svg>
            </div>
            <div>
                <h1 class="re-header__title">Recall Engine</h1>
                <p class="re-header__sub">Auto-generated patient follow-up queue — {{ $openTotal }} open items</p>
            </div>
        </div>
        <div class="re-header__right">
            <span style="font-size:11px;color:#64748b;">Last run: <strong>{{ now()->format('d M Y, H:i') }}</strong></span>
            {{-- Manual Run button — admin only --}}
            <form action="{{ route('communication.recall.run-now') }}" method="POST" id="runNowForm" style="display:inline;">
                @csrf
                <button type="submit" class="re-btn re-btn--primary"
                        onclick="return confirm('Run Recall Engine now? This will scan all patients and create new queue items.');">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24">
                        <polygon points="5 3 19 12 5 21 5 3"/>
                    </svg>
                    Run Now
                </button>
            </form>
        </div>
    </div>

    {{-- ── Flash messages ──────────────────────────────────────────────────── --}}
    @if(session('success'))
        <div class="re-alert re-alert--success" style="margin-top:12px;">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            {{ session('success') }}
        </div>
    @endif

    {{-- ── Run summary (after manual run) ─────────────────────────────────── --}}
    @if(session('recall_run_summary'))
        @php $rs = session('recall_run_summary'); @endphp
        <div class="re-run-summary" style="margin-top:12px;">
            <div class="re-run-summary__icon">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            </div>
            <div>
                <p class="re-run-summary__title">Engine ran — {{ $rs['total'] ?? 0 }} item(s) queued</p>
                <div class="re-run-summary__grid">
                    <span class="re-run-summary__item">6-Month No Visit: <strong>{{ $rs['no_visit_6months'] ?? 0 }}</strong></span>
                    <span class="re-run-summary__item">Approved Plan: <strong>{{ $rs['approved_plan_no_appt'] ?? 0 }}</strong></span>
                    <span class="re-run-summary__item">Post-Op: <strong>{{ $rs['post_op_followup'] ?? 0 }}</strong></span>
                    <span class="re-run-summary__item">Lab Ready: <strong>{{ $rs['lab_received_no_appt'] ?? 0 }}</strong></span>
                    <span class="re-run-summary__item">7-Day Follow-Up: <strong>{{ $rs['recent_tx_followup'] ?? 0 }}</strong></span>
                    <span class="re-run-summary__item">Birthday: <strong>{{ $rs['birthday_anniversary'] ?? 0 }}</strong></span>
                </div>
            </div>
        </div>
    @endif

    {{-- ── Stat Cards (one per trigger type) ──────────────────────────────── --}}
    <div class="re-stats">
        {{-- All --}}
        <a href="{{ route('communication.recall.index') }}"
           class="re-stat-card re-stat-card--all {{ !$triggerFilter ? 'is-active' : '' }}">
            <div class="re-stat-card__label">All Triggers</div>
            <div class="re-stat-card__count">{{ $openTotal }}</div>
        </a>

        @foreach($triggerLabels as $purposeKey => $purposeLabel)
            @php
                $stat     = $stats->get($purposeKey);
                $cnt      = $stat ? $stat->total : 0;
                $highCnt  = $stat ? $stat->high_count : 0;
            @endphp
            <a href="{{ route('communication.recall.index', ['trigger' => $purposeKey]) }}"
               class="re-stat-card {{ $triggerFilter === $purposeKey ? 'is-active' : '' }}">
                <div class="re-stat-card__label">{{ $purposeLabel }}</div>
                <div class="re-stat-card__count">{{ $cnt }}</div>
                @if($highCnt > 0)
                    <div class="re-stat-card__high">{{ $highCnt }} high priority</div>
                @endif
            </a>
        @endforeach
    </div>

    {{-- ── Filter Bar ───────────────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('communication.recall.index') }}">
        @if($triggerFilter)
            <input type="hidden" name="trigger" value="{{ $triggerFilter }}">
        @endif
        <div class="re-filters">
            <div class="re-filter-group">
                <label class="re-filter-label">Status</label>
                <select name="status" class="re-filter-select" onchange="this.form.submit()">
                    <option value="all"     {{ $statusFilter === 'all'     ? 'selected' : '' }}>All Statuses</option>
                    <option value="pending" {{ $statusFilter === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="overdue" {{ $statusFilter === 'overdue' ? 'selected' : '' }}>Overdue</option>
                    <option value="waiting_for_patient" {{ $statusFilter === 'waiting_for_patient' ? 'selected' : '' }}>Waiting</option>
                    <option value="closed"  {{ $statusFilter === 'closed'  ? 'selected' : '' }}>Closed</option>
                </select>
            </div>

            <div class="re-filter-group">
                <label class="re-filter-label">Trigger</label>
                <select name="trigger" class="re-filter-select" onchange="this.form.submit()">
                    <option value="">All Triggers</option>
                    @foreach($triggerLabels as $k => $v)
                        <option value="{{ $k }}" {{ $triggerFilter === $k ? 'selected' : '' }}>{{ $v }}</option>
                    @endforeach
                </select>
            </div>

            <a href="{{ route('communication.recall.index') }}" class="re-btn re-btn--outline re-btn--sm" style="align-self:flex-end;">
                Clear
            </a>
        </div>
    </form>

    {{-- ── Table ────────────────────────────────────────────────────────────── --}}
    <div class="re-table-wrap">
        <table class="re-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Trigger</th>
                    <th>Priority</th>
                    <th>Note</th>
                    <th>Status</th>
                    <th>SLA Deadline</th>
                    <th>Queued At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                    <tr>
                        {{-- Patient --}}
                        <td>
                            <div class="re-patient-name">{{ $item->person_name }}</div>
                            <div class="re-patient-phone">{{ $item->phone }}</div>
                        </td>

                        {{-- Trigger type --}}
                        <td>
                            <span class="re-trigger">
                                {{ $triggerLabels[$item->purpose] ?? $item->purpose }}
                            </span>
                        </td>

                        {{-- Priority --}}
                        <td>
                            <span class="re-badge re-badge--{{ $item->priority ?? 'low' }}">
                                {{ ucfirst($item->priority ?? 'low') }}
                            </span>
                        </td>

                        {{-- Note --}}
                        <td>
                            <div class="re-note" title="{{ $item->note }}">{{ $item->note }}</div>
                        </td>

                        {{-- Status --}}
                        <td>
                            @php
                                $statusClass = match($item->status) {
                                    'pending' => 're-badge--pending',
                                    'overdue' => 're-badge--overdue',
                                    'closed'  => 're-badge--closed',
                                    default   => 're-badge--pending',
                                };
                            @endphp
                            <span class="re-badge {{ $statusClass }}">
                                {{ ucfirst(str_replace('_', ' ', $item->status)) }}
                            </span>
                            @if($item->sla_breached)
                                <span class="re-badge re-badge--overdue" style="margin-left:3px;">SLA!</span>
                            @endif
                        </td>

                        {{-- SLA Deadline --}}
                        <td style="font-size:12px; color:{{ $item->sla_breached ? '#dc2626' : '#64748b' }}; white-space:nowrap;">
                            {{ $item->sla_deadline ? $item->sla_deadline->format('d M, H:i') : '—' }}
                        </td>

                        {{-- Queued At --}}
                        <td style="font-size:12px; color:#64748b; white-space:nowrap;">
                            {{ $item->created_at->format('d M Y, H:i') }}
                        </td>

                        {{-- Actions --}}
                        <td>
                            <div style="display:flex; gap:5px; align-items:center;">
                                <a href="{{ route('communication.manager.show', $item->id) }}"
                                   class="re-btn re-btn--outline re-btn--sm">View</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="re-table__empty">
                            <svg width="32" height="32" fill="none" stroke="#94a3b8" stroke-width="1.5" viewBox="0 0 24 24" style="margin:0 auto 8px;display:block;">
                                <path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/>
                            </svg>
                            <p style="margin:0;font-size:14px;font-weight:600;color:#64748b;">No recall items found</p>
                            <p style="margin:4px 0 0;font-size:12px;color:#94a3b8;">
                                @if($triggerFilter)
                                    No items for this trigger. Try "All Triggers" or run the engine.
                                @else
                                    Run the Recall Engine to generate patient follow-up items.
                                @endif
                            </p>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- Pagination --}}
        @if($items->hasPages())
            <div class="re-pagination">
                <span>Showing {{ $items->firstItem() }}–{{ $items->lastItem() }} of {{ $items->total() }} items</span>
                {{ $items->links() }}
            </div>
        @endif
    </div>

    {{-- ── Engine Info Card ─────────────────────────────────────────────────── --}}
    <div style="margin:0 24px 24px; padding:16px 20px; background:#fff; border:1px solid #e2e8f0; border-radius:8px;">
        <p style="font-size:12px; font-weight:700; color:#374151; margin:0 0 10px; text-transform:uppercase; letter-spacing:.05em;">Recall Engine — Schedule</p>
        <div style="display:flex; flex-wrap:wrap; gap:16px;">
            @foreach([
                ['', '6-Month No Visit', 'Patients with no visit ≥6 months. Cooldown: 30 days.'],
                ['', 'Approved Plan', 'Treatment plan approved but no appointment booked (last 90 days).'],
                ['', 'Post-Op Follow-Up', 'Surgical visits (implant/RCT/extraction) — 14 days after visit.'],
                ['', 'Lab Ready', 'Lab case status = received, no upcoming appointment.'],
                ['', '7-Day Follow-Up', 'Any treatment visit 7 days ago (excludes consultations).'],
                ['', 'Birthday', 'Patients with birthday in next 3 days. Once per year.'],
            ] as [$icon, $name, $desc])
                <div style="flex:1 1 200px; min-width:160px;">
                    <p style="font-size:12px; font-weight:600; color:#0f172a; margin:0 0 2px;">{{ $icon }} {{ $name }}</p>
                    <p style="font-size:11px; color:#64748b; margin:0;">{{ $desc }}</p>
                </div>
            @endforeach
        </div>
        <p style="font-size:11px; color:#94a3b8; margin:12px 0 0;">
            Auto-runs daily at 7:00am via Laravel Scheduler. Manual override: <code style="background:#f1f5f9;padding:1px 5px;border-radius:3px;">php artisan recall:run</code>
        </p>
    </div>

</div>
@endsection
