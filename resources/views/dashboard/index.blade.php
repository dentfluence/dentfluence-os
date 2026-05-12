{{--
|==========================================================================
| Dentfluence OS — Dashboard
| File: resources/views/dashboard/index.blade.php
| Extends: layouts/app
|==========================================================================
--}}
@extends('layouts.app')

@section('page-title', 'Dashboard')

{{-- ── Page-level styles ── --}}
@section('head-extra')
<style>
    /* ════════════════════════════════════════════════════════
       DASHBOARD TOKENS
    ════════════════════════════════════════════════════════ */
    :root {
        --brand-500: #6a0f70;
        --brand-600: #4e0a53;
        --brand-700: #380740;
        --brand-100: #f3e8f4;
        --brand-50:  #f9f3fa;
        --canvas:    #f5eef9;
        --surface:   #ffffff;
        --border:    rgba(185, 92, 183, 0.12);
        --border-md: rgba(185, 92, 183, 0.18);
        --ink:       #1a0a24;
        --ink-mid:   #4a3558;
        --ink-soft:  #7a6884;
        --ink-faint: #b0a4bc;
        --ok:        #1a7a45;  --ok-bg:   #e8f7ef;
        --warn:      #a05c00;  --warn-bg: #fff4e0;
        --err:       #b52020;  --err-bg:  #fdeaea;
        --info:      #1a5ea8;  --info-bg: #e6f0fb;
    }

    /* ════════════════════════════════════════════════════════
       WELCOME STRIP
    ════════════════════════════════════════════════════════ */
    .welcome-strip {
        background:
            radial-gradient(ellipse 80% 100% at 100% 50%, rgba(106,15,112,0.10) 0%, transparent 60%),
            linear-gradient(135deg, #ffffff 0%, #faf4fc 100%);
        border: 1px solid var(--border);
        padding: 22px 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }

    /* ════════════════════════════════════════════════════════
       KPI CARD
    ════════════════════════════════════════════════════════ */
    .kpi-card {
        background: var(--surface);
        border: 1px solid var(--border);
        padding: 20px 22px 18px;
        position: relative;
        overflow: hidden;
        cursor: default;
        transition: border-color 160ms, box-shadow 160ms, transform 120ms;
    }
    .kpi-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: var(--kpi-accent, var(--brand-500));
    }
    .kpi-card:hover {
        border-color: var(--border-md);
        box-shadow: 0 4px 18px rgba(106,15,112,0.08);
        transform: translateY(-1px);
    }
    .kpi-icon-wrap {
        width: 38px; height: 38px;
        display: flex; align-items: center; justify-content: center;
        background: var(--kpi-icon-bg, var(--brand-50));
        flex-shrink: 0;
    }
    .kpi-value {
        font-family: 'Cormorant Garamond', serif;
        font-size: 36px; font-weight: 700;
        color: var(--ink); line-height: 1;
        letter-spacing: -0.02em;
    }
    .kpi-label {
        font-size: 11px; font-weight: 500;
        letter-spacing: 0.07em; text-transform: uppercase;
        color: var(--ink-soft); margin-top: 5px;
    }
    .kpi-delta {
        font-size: 11px; font-weight: 500;
        display: inline-flex; align-items: center; gap: 3px;
    }
    .kpi-delta.up   { color: var(--ok); }
    .kpi-delta.down { color: var(--err); }
    .kpi-delta.flat { color: var(--ink-faint); }

    /* ════════════════════════════════════════════════════════
       SECTION HEADING
    ════════════════════════════════════════════════════════ */
    .section-title {
        font-family: 'DM Sans', sans-serif;
        font-size: 11px; font-weight: 600;
        letter-spacing: 0.10em; text-transform: uppercase;
        color: var(--ink-soft);
        margin-bottom: 12px;
        display: flex; align-items: center; gap: 10px;
    }
    .section-title::after {
        content: ''; flex: 1; height: 1px;
        background: var(--border);
    }

    /* ════════════════════════════════════════════════════════
       SCHEDULE TABLE
    ════════════════════════════════════════════════════════ */
    .schedule-table {
        width: 100%; border-collapse: collapse;
        font-size: 13px; font-family: 'DM Sans', sans-serif;
    }
    .schedule-table thead tr {
        background: var(--brand-50);
        border-bottom: 1px solid var(--border);
    }
    .schedule-table th {
        text-align: left; padding: 8px 14px;
        font-size: 10px; font-weight: 600;
        letter-spacing: 0.09em; text-transform: uppercase;
        color: var(--ink-soft); white-space: nowrap;
    }
    .schedule-table td {
        padding: 11px 14px;
        border-bottom: 1px solid rgba(185,92,183,0.06);
        color: var(--ink); vertical-align: middle;
        line-height: 1.4;
    }
    .schedule-table tbody tr:last-child td { border-bottom: none; }
    .schedule-table tbody tr {
        transition: background 120ms;
        cursor: pointer;
    }
    .schedule-table tbody tr:hover { background: var(--brand-50); }
    .schedule-table .time-cell {
        font-family: 'DM Mono', monospace;
        font-size: 12px; color: var(--ink-mid);
        white-space: nowrap;
    }

    /* ════════════════════════════════════════════════════════
       STATUS PILLS
    ════════════════════════════════════════════════════════ */
    .pill {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 3px 9px; font-size: 11px; font-weight: 600;
        white-space: nowrap;
    }
    .pill-dot { width: 5px; height: 5px; border-radius: 50%; flex-shrink: 0; }
    .pill-confirmed  { background: var(--ok-bg);   color: var(--ok);   }
    .pill-confirmed  .pill-dot { background: var(--ok); }
    .pill-pending    { background: var(--warn-bg);  color: var(--warn); }
    .pill-pending    .pill-dot { background: var(--warn); }
    .pill-inchair    { background: var(--info-bg);  color: var(--info); }
    .pill-inchair    .pill-dot { background: var(--info); }
    .pill-missed     { background: var(--err-bg);   color: var(--err);  }
    .pill-missed     .pill-dot { background: var(--err); }
    .pill-scheduled  { background: var(--brand-100); color: var(--brand-600); }
    .pill-scheduled  .pill-dot { background: var(--brand-500); }
    .pill-completed  { background: #f0f0f0; color: #666; }
    .pill-completed  .pill-dot { background: #999; }

    /* ════════════════════════════════════════════════════════
       PATIENT LIST ITEM
    ════════════════════════════════════════════════════════ */
    .patient-row {
        display: flex; align-items: center; gap: 12px;
        padding: 10px 14px;
        border-bottom: 1px solid rgba(185,92,183,0.06);
        cursor: pointer;
        transition: background 120ms;
    }
    .patient-row:last-child { border-bottom: none; }
    .patient-row:hover { background: var(--brand-50); }
    .patient-avatar {
        width: 34px; height: 34px; flex-shrink: 0;
        background: var(--brand-600);
        display: flex; align-items: center; justify-content: center;
        font-family: 'DM Sans', sans-serif;
        font-size: 12px; font-weight: 600;
        color: rgba(255,255,255,0.90);
        letter-spacing: 0.03em;
    }

    /* ════════════════════════════════════════════════════════
       PIPELINE BAR
    ════════════════════════════════════════════════════════ */
    .pipeline-bar {
        height: 6px; background: #ede4f2;
        position: relative; overflow: hidden;
    }
    .pipeline-bar-fill {
        position: absolute; left: 0; top: 0; bottom: 0;
        background: var(--brand-500);
        transition: width 600ms cubic-bezier(0.16,1,0.3,1);
    }

    /* ════════════════════════════════════════════════════════
       QUICK ACTION
    ════════════════════════════════════════════════════════ */
    .quick-action {
        display: flex; align-items: center; gap: 12px;
        padding: 13px 16px;
        background: var(--surface);
        border: 1px solid var(--border);
        cursor: pointer; text-decoration: none;
        transition: border-color 140ms, background 140ms, box-shadow 140ms;
        color: var(--ink);
    }
    .quick-action:hover {
        border-color: var(--brand-500);
        background: var(--brand-50);
        box-shadow: 0 2px 12px rgba(106,15,112,0.08);
    }
    .quick-action:active { transform: scale(0.99); }
    .qa-icon {
        width: 36px; height: 36px; flex-shrink: 0;
        background: var(--brand-50); border: 1px solid var(--border);
        display: flex; align-items: center; justify-content: center;
        color: var(--brand-500);
        transition: background 140ms;
    }
    .quick-action:hover .qa-icon {
        background: var(--brand-100);
        border-color: var(--brand-500);
    }
    .qa-label {
        font-size: 13px; font-weight: 500; color: var(--ink);
        line-height: 1.3;
    }
    .qa-sub {
        font-size: 11px; font-weight: 300; color: var(--ink-soft);
        margin-top: 1px;
    }

    /* ════════════════════════════════════════════════════════
       ALERT ITEM
    ════════════════════════════════════════════════════════ */
    .alert-item {
        display: flex; align-items: flex-start; gap: 11px;
        padding: 11px 16px;
        border-bottom: 1px solid rgba(185,92,183,0.06);
        transition: background 120ms; cursor: pointer;
    }
    .alert-item:last-child { border-bottom: none; }
    .alert-item:hover { background: var(--brand-50); }
    .alert-dot {
        width: 8px; height: 8px; border-radius: 50%;
        flex-shrink: 0; margin-top: 4px;
    }

    /* ════════════════════════════════════════════════════════
       PERFORMANCE ROW
    ════════════════════════════════════════════════════════ */
    .perf-row {
        display: flex; align-items: center; gap: 12px;
        padding: 10px 0;
        border-bottom: 1px solid rgba(185,92,183,0.06);
    }
    .perf-row:last-child { border-bottom: none; }
    .perf-label {
        font-size: 12px; font-weight: 400; color: var(--ink-mid);
        flex: 1; white-space: nowrap;
    }
    .perf-value {
        font-family: 'DM Mono', monospace;
        font-size: 13px; font-weight: 500; color: var(--ink);
        min-width: 64px; text-align: right; flex-shrink: 0;
    }

    /* ════════════════════════════════════════════════════════
       UTILITY
    ════════════════════════════════════════════════════════ */
    .df-card-header-actions {
        display: flex; align-items: center; gap: 8px;
    }
    .link-sm {
        font-size: 12px; font-weight: 500; color: var(--brand-500);
        text-decoration: none;
        transition: color 140ms;
    }
    .link-sm:hover { color: var(--brand-600); text-decoration: underline; }

    .icon-btn {
        width: 28px; height: 28px; display: flex; align-items: center; justify-content: center;
        background: none; border: 1px solid var(--border); cursor: pointer; color: var(--ink-faint);
        transition: color 140ms, border-color 140ms, background 140ms;
    }
    .icon-btn:hover {
        color: var(--brand-500); border-color: var(--brand-500);
        background: var(--brand-50);
    }

    /* Date badge */
    .date-badge {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px;
        background: var(--brand-50);
        border: 1px solid var(--border);
        font-size: 12px; font-weight: 500; color: var(--ink-mid);
    }

    /* Clinic status badge */
    .clinic-status {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px;
        border: 1px solid;
        font-size: 11.5px; font-weight: 500;
    }
    .clinic-status.open {
        background: var(--ok-bg); color: var(--ok); border-color: var(--ok);
    }
    .status-pulse {
        width: 7px; height: 7px; border-radius: 50%;
        background: var(--ok); flex-shrink: 0;
        animation: statusPulse 2.5s ease-in-out infinite;
    }
    @keyframes statusPulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: 0.55; transform: scale(1.35); }
    }

    /* Treatment pipeline card */
    .pipeline-item {
        display: flex; align-items: center; gap: 12px;
        padding: 11px 0;
        border-bottom: 1px solid rgba(185,92,183,0.06);
    }
    .pipeline-item:last-child { border-bottom: none; }
    .pipeline-stage-dot {
        width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0;
    }

    /* ── Responsive tweaks ── */
    @media (max-width: 1100px) {
        .grid-kpi  { grid-template-columns: repeat(3, 1fr) !important; }
        .grid-main { grid-template-columns: 1fr !important; }
    }
    @media (max-width: 780px) {
        .grid-kpi       { grid-template-columns: repeat(2, 1fr) !important; }
        .grid-bottom    { grid-template-columns: 1fr !important; }
        .welcome-strip  { flex-direction: column; align-items: flex-start; }
    }
    @media (max-width: 480px) {
        .grid-kpi { grid-template-columns: 1fr !important; }
    }

    /* Number counter animation */
    .kpi-value { transition: opacity 400ms; }
    .kpi-card.loading .kpi-value { opacity: 0.35; }
</style>
@endsection

{{-- ════════════════════════════════════════════════════════════════════
     DASHBOARD CONTENT
════════════════════════════════════════════════════════════════════ --}}
@section('content')

{{-- ── 1. WELCOME STRIP ──────────────────────────────────────────── --}}
<div class="welcome-strip" role="banner">

    {{-- Left: Greeting + date --}}
    <div style="display:flex; flex-direction:column; gap:4px; min-width:0;">
        <h1 style="font-family:'Cormorant Garamond',serif; font-size:26px; font-weight:600; color:#1a0a24; line-height:1.1; letter-spacing:-0.01em;">
            Good morning, Dr. Sumit
        </h1>
        <p style="font-size:13px; font-weight:300; color:#7a6884;">
            Here's your clinic overview for today. Tulip Dental is running smoothly.
        </p>
    </div>

    {{-- Right: Date + Clinic status --}}
    <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; flex-shrink:0;">
        <div class="date-badge">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" style="color:#6a0f70;">
                <rect x="3" y="4" width="18" height="18" rx="0"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
            {{ \Carbon\Carbon::now()->format('l, d F Y') }}
        </div>
        <div class="clinic-status open">
            <span class="status-pulse"></span>
            Clinic Open · 10:00 AM – 9:00 PM
        </div>
    </div>

</div>


{{-- ── 2. KPI CARDS GRID ─────────────────────────────────────────── --}}
<div
    class="grid-kpi"
    style="display:grid; grid-template-columns: repeat(6, 1fr); gap:12px; margin-bottom:24px;"
>

    {{-- KPI: Today's Appointments --}}
    <div class="kpi-card" style="--kpi-accent:#6a0f70; --kpi-icon-bg:#f3e8f4;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:14px;">
            <div class="kpi-icon-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="0"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </div>
            <span class="kpi-delta up">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
                +3
            </span>
        </div>
        <div class="kpi-value">24</div>
        <div class="kpi-label">Appointments</div>
        <div style="margin-top:10px; font-size:11px; color:#9e8fa0; font-weight:300;">
            18 confirmed · 4 pending · 2 arrived
        </div>
    </div>

    {{-- KPI: Revenue Today --}}
    <div class="kpi-card" style="--kpi-accent:#1a7a45; --kpi-icon-bg:#e8f7ef;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:14px;">
            <div class="kpi-icon-wrap" style="background:#e8f7ef;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1a7a45" stroke-width="1.75" stroke-linecap="round">
                    <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                </svg>
            </div>
            <span class="kpi-delta up">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
                ₹8.2k
            </span>
        </div>
        <div class="kpi-value" style="font-size:28px; letter-spacing:-0.02em;">₹42,800</div>
        <div class="kpi-label">Revenue Today</div>
        <div style="margin-top:10px; font-size:11px; color:#9e8fa0; font-weight:300;">
            Target: ₹55,000 · 78% achieved
        </div>
    </div>

    {{-- KPI: Pending Treatments --}}
    <div class="kpi-card" style="--kpi-accent:#a05c00; --kpi-icon-bg:#fff4e0;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:14px;">
            <div class="kpi-icon-wrap" style="background:#fff4e0;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#a05c00" stroke-width="1.75" stroke-linecap="round">
                    <path d="M12 22 C12 22 5 17 5 11 C5 7 7.5 4 12 4 C16.5 4 19 7 19 11 C19 17 12 22 12 22Z"/>
                    <path d="M9 11 h6 M12 8 v6" stroke-width="1.5"/>
                </svg>
            </div>
            <span class="kpi-delta down">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
                -2
            </span>
        </div>
        <div class="kpi-value">13</div>
        <div class="kpi-label">Pending Treatments</div>
        <div style="margin-top:10px; font-size:11px; color:#9e8fa0; font-weight:300;">
            5 urgent · 8 scheduled
        </div>
    </div>

    {{-- KPI: Lab Cases --}}
    <div class="kpi-card" style="--kpi-accent:#1a5ea8; --kpi-icon-bg:#e6f0fb;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:14px;">
            <div class="kpi-icon-wrap" style="background:#e6f0fb;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#1a5ea8" stroke-width="1.75" stroke-linecap="round">
                    <path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/>
                </svg>
            </div>
            <span class="kpi-delta flat">—</span>
        </div>
        <div class="kpi-value">7</div>
        <div class="kpi-label">Lab Cases</div>
        <div style="margin-top:10px; font-size:11px; color:#9e8fa0; font-weight:300;">
            3 dispatched · 1 overdue
        </div>
    </div>

    {{-- KPI: New Leads --}}
    <div class="kpi-card" style="--kpi-accent:#8e24aa; --kpi-icon-bg:#f3e8f4;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:14px;">
            <div class="kpi-icon-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#8e24aa" stroke-width="1.75" stroke-linecap="round">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    <line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>
                </svg>
            </div>
            <span class="kpi-delta up">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
                +5
            </span>
        </div>
        <div class="kpi-value">12</div>
        <div class="kpi-label">New Leads</div>
        <div style="margin-top:10px; font-size:11px; color:#9e8fa0; font-weight:300;">
            7 from Google · 5 referrals
        </div>
    </div>

    {{-- KPI: Collections --}}
    <div class="kpi-card" style="--kpi-accent:#6a0f70; --kpi-icon-bg:#f3e8f4;">
        <div style="display:flex; align-items:flex-start; justify-content:space-between; gap:10px; margin-bottom:14px;">
            <div class="kpi-icon-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="1.75" stroke-linecap="round">
                    <rect x="2" y="5" width="20" height="14" rx="0"/><line x1="2" y1="10" x2="22" y2="10"/>
                </svg>
            </div>
            <span class="kpi-delta up">
                <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>
                ₹6.4k
            </span>
        </div>
        <div class="kpi-value" style="font-size:28px;">₹31,500</div>
        <div class="kpi-label">Collections</div>
        <div style="margin-top:10px; font-size:11px; color:#9e8fa0; font-weight:300;">
            Outstanding: ₹14,200
        </div>
    </div>

</div>{{-- /kpi grid --}}


{{-- ── MAIN GRID: Schedule + Right Column ──────────────────────── --}}
<div
    class="grid-main"
    style="display:grid; grid-template-columns: 1fr 320px; gap:16px; margin-bottom:24px;"
>

    {{-- ── 3. TODAY'S SCHEDULE ──────────────────────────────────── --}}
    <div class="df-card" style="display:flex; flex-direction:column; overflow:hidden;">
        <div class="df-card-header">
            <div>
                <span style="font-size:13px; font-weight:600; color:#1a0a24;">Today's Schedule</span>
                <span style="font-size:11px; color:#9e8fa0; margin-left:8px;">Monday, 12 May 2025</span>
            </div>
            <div class="df-card-header-actions">
                <button class="icon-btn" title="Refresh schedule" aria-label="Refresh">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
                </button>
                <a href="{{ route('appointments.index') }}" class="link-sm">View all →</a>
            </div>
        </div>

        <div style="overflow-x:auto;">
            <table class="schedule-table" aria-label="Today's appointment schedule">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Patient</th>
                        <th>Treatment</th>
                        <th>Doctor</th>
                        <th>Duration</th>
                        <th>Status</th>
                        <th style="width:40px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach([
                        ['10:00 AM', 'Riya Sharma',   'RCT — Upper Left',    'Dr. Sumit',   '60 min', 'completed', 'RS'],
                        ['10:30 AM', 'Aman Verma',    'Implant Placement',   'Dr. Priya',   '90 min', 'inchair',   'AV'],
                        ['11:30 AM', 'Neha Patil',    'Crown Placement',     'Dr. Sumit',   '45 min', 'confirmed', 'NP'],
                        ['12:00 PM', 'Karan Mehta',   'Consultation',        'Dr. Priya',   '30 min', 'confirmed', 'KM'],
                        ['01:00 PM', 'Sunita Joshi',  'Scaling & Polishing', 'Dr. Sumit',   '45 min', 'scheduled', 'SJ'],
                        ['02:00 PM', 'Rahul Thakur',  'Composite Filling',   'Dr. Priya',   '60 min', 'scheduled', 'RT'],
                        ['03:30 PM', 'Meera Iyer',    'Teeth Whitening',     'Dr. Sumit',   '60 min', 'pending',   'MI'],
                        ['04:30 PM', 'Vikas Nair',    'Braces Adjustment',   'Dr. Anjali',  '30 min', 'pending',   'VN'],
                        ['05:00 PM', 'Pooja Desai',   'Root Canal — Lower',  'Dr. Sumit',   '75 min', 'scheduled', 'PD'],
                        ['06:30 PM', 'Arjun Gupta',   'Extraction',          'Dr. Priya',   '30 min', 'missed',    'AG'],
                    ] as $appt)
                    <tr>
                        <td class="time-cell">{{ $appt[0] }}</td>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <div style="width:28px; height:28px; background:#5a006e; display:flex; align-items:center; justify-content:center; font-family:'DM Sans',sans-serif; font-size:10px; font-weight:600; color:rgba(255,255,255,0.90); flex-shrink:0;">
                                    {{ $appt[6] }}
                                </div>
                                <span style="font-weight:500;">{{ $appt[1] }}</span>
                            </div>
                        </td>
                        <td style="color:#4a3558; font-size:12.5px;">{{ $appt[2] }}</td>
                        <td style="font-size:12px; color:#7a6884;">{{ $appt[3] }}</td>
                        <td style="font-family:'DM Mono',monospace; font-size:11px; color:#9e8fa0;">{{ $appt[4] }}</td>
                        <td>
                            <span class="pill pill-{{ $appt[5] }}">
                                <span class="pill-dot"></span>
                                {{ ucfirst($appt[5]) }}
                            </span>
                        </td>
                        <td>
                            <button
                                class="icon-btn"
                                title="View appointment"
                                aria-label="View {{ $appt[1] }}'s appointment"
                            >
                                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Schedule footer --}}
        <div style="padding:10px 16px; background:#faf4fb; border-top:1px solid rgba(185,92,183,0.08); display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:8px; flex-shrink:0;">
            <div style="display:flex; gap:16px; flex-wrap:wrap;">
                @foreach([
                    ['Confirmed', '18', '#1a7a45'],
                    ['In Chair',  '1',  '#1a5ea8'],
                    ['Pending',   '2',  '#a05c00'],
                    ['Missed',    '1',  '#b52020'],
                    ['Completed', '2',  '#888'],
                ] as $stat)
                <span style="font-size:11.5px; font-weight:400; color:#7a6884; display:flex; align-items:center; gap:4px;">
                    <span style="width:6px; height:6px; border-radius:50%; background:{{ $stat[2] }}; flex-shrink:0;"></span>
                    {{ $stat[0] }}: <strong style="color:#1a0a24; font-weight:600; margin-left:2px;">{{ $stat[1] }}</strong>
                </span>
                @endforeach
            </div>
            <a href="{{ route('appointments.create') }}" style="display:inline-flex; align-items:center; gap:6px; padding:5px 12px; background:#5a006e; color:#fff; font-family:'DM Sans',sans-serif; font-size:12px; font-weight:500; text-decoration:none; transition:background 140ms;" onmouseover="this.style.background='#480058'" onmouseout="this.style.background='#5a006e'">
                <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Appointment
            </a>
        </div>
    </div>{{-- /schedule card --}}


    {{-- ── RIGHT COLUMN ─────────────────────────────────────────── --}}
    <div style="display:flex; flex-direction:column; gap:16px;">

        {{-- ── 6. QUICK ACTIONS ─────────────────────────────────── --}}
        <div class="df-card" style="overflow:hidden;">
            <div class="df-card-header">
                <span style="font-size:13px; font-weight:600; color:#1a0a24;">Quick Actions</span>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1px; background:rgba(185,92,183,0.08);">
                @foreach([
                    [
                        'label' => 'Add Patient',
                        'sub'   => 'Register new patient',
                        'route' => 'patients.create',
                        'icon'  => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>',
                    ],
                    [
                        'label' => 'Book Appointment',
                        'sub'   => 'Schedule a slot',
                        'route' => 'appointments.create',
                        'icon'  => '<rect x="3" y="4" width="18" height="18" rx="0"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="14" x2="8" y2="14" stroke-width="3"/><line x1="12" y1="14" x2="12" y2="14" stroke-width="3"/><line x1="16" y1="14" x2="16" y2="14" stroke-width="3"/>',
                    ],
                    [
                        'label' => 'Create Bill',
                        'sub'   => 'Raise invoice',
                        'route' => 'billing.create',
                        'icon'  => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>',
                    ],
                    [
                        'label' => 'Add Lab Work',
                        'sub'   => 'New lab order',
                        'route' => 'lab.create',
                        'icon'  => '<path d="M9 3H5a2 2 0 0 0-2 2v4m6-6h10a2 2 0 0 1 2 2v4M9 3v18m0 0h10a2 2 0 0 0 2-2v-4M9 21H5a2 2 0 0 1-2-2v-4m0 0h18"/>',
                    ],
                ] as $qa)
                <a href="{{ route($qa['route']) }}" class="quick-action" style="background:var(--surface);">
                    <div class="qa-icon">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">{!! $qa['icon'] !!}</svg>
                    </div>
                    <div>
                        <div class="qa-label">{{ $qa['label'] }}</div>
                        <div class="qa-sub">{{ $qa['sub'] }}</div>
                    </div>
                </a>
                @endforeach
            </div>
        </div>

        {{-- ── 7. NOTIFICATIONS / ALERTS ────────────────────────── --}}
        <div class="df-card" style="overflow:hidden; flex:1;">
            <div class="df-card-header">
                <span style="font-size:13px; font-weight:600; color:#1a0a24;">Alerts</span>
                <span style="font-size:11px; padding:2px 8px; background:#fdeaea; color:#b52020; font-weight:600;">3 urgent</span>
            </div>

            @foreach([
                [
                    'color' => '#b52020',
                    'title' => 'Overdue Lab — Arjun Gupta',
                    'body'  => 'Crown case overdue by 3 days. Lab: Dombivli Dental Studio.',
                    'time'  => '2h ago',
                    'type'  => 'err',
                ],
                [
                    'color' => '#b52020',
                    'title' => 'Payment Pending — Meera Iyer',
                    'body'  => 'Outstanding balance ₹8,500. Appointment in 2h.',
                    'time'  => '30m ago',
                    'type'  => 'err',
                ],
                [
                    'color' => '#a05c00',
                    'title' => 'Low Stock — Composite Material',
                    'body'  => 'Ivoclar A2 composite down to 2 units. Reorder required.',
                    'time'  => '1h ago',
                    'type'  => 'warn',
                ],
                [
                    'color' => '#a05c00',
                    'title' => 'Missed Follow-up — Rahul Thakur',
                    'body'  => 'Post-RCT follow-up scheduled 3 days ago. Not confirmed.',
                    'time'  => '3h ago',
                    'type'  => 'warn',
                ],
                [
                    'color' => '#1a5ea8',
                    'title' => '5 New Leads Today',
                    'body'  => 'Google Ads campaign generated 5 enquiries. Assign to CRM.',
                    'time'  => 'Today',
                    'type'  => 'info',
                ],
            ] as $alert)
            <div class="alert-item">
                <span class="alert-dot" style="background:{{ $alert['color'] }};"></span>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:12.5px; font-weight:500; color:#1a0a24; line-height:1.3; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $alert['title'] }}</div>
                    <div style="font-size:11.5px; font-weight:300; color:#7a6884; margin-top:2px; line-height:1.4;">{{ $alert['body'] }}</div>
                </div>
                <span style="font-size:10.5px; color:#b0a4bc; flex-shrink:0; margin-left:8px; white-space:nowrap;">{{ $alert['time'] }}</span>
            </div>
            @endforeach

            <div style="padding:9px 16px; border-top:1px solid rgba(185,92,183,0.08); text-align:center; background:#faf4fb;">
                <a href="{{ route('notifications.index') ?? '#' }}" class="link-sm" style="font-size:11.5px;">View all notifications →</a>
            </div>
        </div>

    </div>{{-- /right column --}}
</div>{{-- /main grid --}}


{{-- ── BOTTOM GRID: Patients + Pipeline + Performance ──────────── --}}
<div
    class="grid-bottom"
    style="display:grid; grid-template-columns: 1fr 1fr 300px; gap:16px;"
>

    {{-- ── 4. RECENT PATIENTS ──────────────────────────────────── --}}
    <div class="df-card" style="overflow:hidden;">
        <div class="df-card-header">
            <span style="font-size:13px; font-weight:600; color:#1a0a24;">Recent Patients</span>
            <div class="df-card-header-actions">
                <a href="{{ route('patients.index') }}" class="link-sm">View all →</a>
            </div>
        </div>

        @foreach([
            ['Riya Sharma',   'RS', '#5a006e', 'Last: RCT — Today',          'Confirmed',  'confirmed'],
            ['Aman Verma',    'AV', '#1a5ea8', 'Last: Implant — Today',       'In Chair',   'inchair'],
            ['Neha Patil',    'NP', '#6a0f70', 'Last: Crown — 3 May',         'Scheduled',  'scheduled'],
            ['Karan Mehta',   'KM', '#380740', 'Last: Consultation — Today',  'Confirmed',  'confirmed'],
            ['Sunita Joshi',  'SJ', '#8e24aa', 'Next: Scaling — 1:00 PM',     'Pending',    'pending'],
            ['Rahul Thakur',  'RT', '#4a3558', 'Next: Filling — 2:00 PM',     'Scheduled',  'scheduled'],
            ['Meera Iyer',    'MI', '#b95cb7', 'Next: Whitening — 3:30 PM',   'Pending',    'pending'],
        ] as $patient)
        <div class="patient-row">
            <div class="patient-avatar" style="background:{{ $patient[2] }};">{{ $patient[1] }}</div>
            <div style="flex:1; min-width:0;">
                <div style="font-size:13px; font-weight:500; color:#1a0a24;">{{ $patient[0] }}</div>
                <div style="font-size:11.5px; font-weight:300; color:#7a6884; margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $patient[2] }}</div>
            </div>
            <span class="pill pill-{{ $patient[5] }}" style="font-size:10.5px; flex-shrink:0;">
                <span class="pill-dot"></span>
                {{ $patient[3] }}
            </span>
        </div>
        @endforeach

    </div>{{-- /recent patients --}}


    {{-- ── 5. TREATMENT PIPELINE ──────────────────────────────── --}}
    <div class="df-card" style="overflow:hidden;">
        <div class="df-card-header">
            <span style="font-size:13px; font-weight:600; color:#1a0a24;">Treatment Pipeline</span>
            <div class="df-card-header-actions">
                <a href="{{ route('treatments.index') }}" class="link-sm">Full pipeline →</a>
            </div>
        </div>

        {{-- Stage summary bars --}}
        <div style="padding:14px 18px 0; display:grid; grid-template-columns:1fr 1fr; gap:10px; margin-bottom:4px;">
            @foreach([
                ['Consultation',   8,  22, '#8e24aa'],
                ['Treatment Plan', 14, 22, '#6a0f70'],
                ['In Progress',    7,  22, '#1a5ea8'],
                ['Completed',      11, 22, '#1a7a45'],
                ['On Hold',        3,  22, '#a05c00'],
                ['Awaiting Lab',   4,  22, '#b52020'],
            ] as $stage)
            <div style="padding:0 0 10px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                    <span style="font-size:11px; color:#7a6884; font-weight:400;">{{ $stage[0] }}</span>
                    <span style="font-family:'DM Mono',monospace; font-size:11px; font-weight:500; color:#1a0a24;">{{ $stage[1] }}/{{ $stage[2] }}</span>
                </div>
                <div class="pipeline-bar">
                    <div class="pipeline-bar-fill" style="width:{{ round(($stage[1]/$stage[2])*100) }}%; background:{{ $stage[3] }};"></div>
                </div>
            </div>
            @endforeach
        </div>

        <div style="height:1px; background:rgba(185,92,183,0.08); margin:0 18px;"></div>

        {{-- Case list --}}
        <div style="padding:0 18px 6px;">
            @foreach([
                ['Arjun Gupta',   'Crown — Upper Right',   'Awaiting Lab',   '#b52020', '3 days overdue'],
                ['Pooja Desai',   'Root Canal — Lower 6',  'In Progress',    '#1a5ea8', 'Session 2 of 3'],
                ['Vikas Nair',    'Braces — Phase 2',      'In Progress',    '#1a5ea8', 'Month 4 of 14'],
                ['Sunita Joshi',  'Implant — Upper Front', 'Treatment Plan', '#6a0f70', 'Awaiting approval'],
                ['Deepak Rao',    'Veneer — 4 teeth',      'Consultation',   '#8e24aa', 'Initial visit done'],
            ] as $case)
            <div class="pipeline-item">
                <span class="pipeline-stage-dot" style="background:{{ $case[3] }};"></span>
                <div style="flex:1; min-width:0;">
                    <div style="font-size:12.5px; font-weight:500; color:#1a0a24; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $case[0] }}</div>
                    <div style="font-size:11px; color:#7a6884; font-weight:300; margin-top:1px;">{{ $case[1] }}</div>
                </div>
                <div style="text-align:right; flex-shrink:0;">
                    <div style="font-size:11px; font-weight:500; color:{{ $case[3] }};">{{ $case[2] }}</div>
                    <div style="font-size:10.5px; color:#b0a4bc; font-weight:300; margin-top:1px;">{{ $case[4] }}</div>
                </div>
            </div>
            @endforeach
        </div>
    </div>{{-- /treatment pipeline --}}


    {{-- ── 8. PERFORMANCE SNAPSHOT ────────────────────────────── --}}
    <div class="df-card" style="overflow:hidden; display:flex; flex-direction:column;">
        <div class="df-card-header">
            <span style="font-size:13px; font-weight:600; color:#1a0a24;">Performance</span>
            <span style="font-size:10.5px; color:#9e8fa0; font-weight:300;">This Month</span>
        </div>

        <div style="padding:16px 18px; flex:1;">

            {{-- Monthly target progress --}}
            <div style="margin-bottom:18px;">
                <div style="display:flex; justify-content:space-between; margin-bottom:6px;">
                    <span style="font-size:12px; font-weight:500; color:#1a0a24;">Monthly Revenue</span>
                    <span style="font-family:'DM Mono',monospace; font-size:12px; font-weight:600; color:#1a0a24;">₹8.4L</span>
                </div>
                <div style="height:8px; background:#ede4f2; position:relative; overflow:hidden;">
                    <div style="position:absolute; left:0; top:0; bottom:0; width:68%; background:linear-gradient(90deg, #6a0f70, #8e24aa); transition:width 600ms;"></div>
                </div>
                <div style="display:flex; justify-content:space-between; margin-top:4px;">
                    <span style="font-size:10.5px; color:#9e8fa0;">68% of ₹12L target</span>
                    <span style="font-size:10.5px; color:#1a7a45; font-weight:500;">On track</span>
                </div>
            </div>

            {{-- Stats rows --}}
            @foreach([
                ['Total Appointments',    '312',     null],
                ['Avg per Day',           '14.2',    null],
                ['New Patients',          '48',      '+12% ↑'],
                ['Patient Retention',     '84%',     null],
                ['Avg Treatment Value',   '₹4,200',  null],
                ['Pending Collections',   '₹52,400', null],
                ['Lab Turn-around',       '4.2 days',null],
                ['No-show Rate',          '6.4%',    '-1.2% ↓'],
                ['Chair Utilization',     '78%',     null],
                ['NPS Score',             '72',      null],
            ] as $perf)
            <div class="perf-row">
                <span class="perf-label">{{ $perf[0] }}</span>
                <span class="perf-value">{{ $perf[1] }}</span>
                @if($perf[2])
                    <span style="font-size:10px; font-weight:500; color:{{ str_contains($perf[2], '↑') ? '#1a7a45' : '#b52020' }}; min-width:52px; text-align:right; flex-shrink:0;">{{ $perf[2] }}</span>
                @endif
            </div>
            @endforeach

        </div>

        <div style="padding:10px 18px; background:#faf4fb; border-top:1px solid rgba(185,92,183,0.08); text-align:center;">
            <a href="{{ route('analytics.index') }}" class="link-sm" style="font-size:11.5px;">Full analytics report →</a>
        </div>
    </div>{{-- /performance --}}

</div>{{-- /bottom grid --}}

@endsection


@section('scripts')
<script>
(function() {
    /* ── Animate KPI values on load ── */
    function animateCount(el, target, prefix, suffix, duration) {
        var start = 0;
        var step  = Math.ceil(target / (duration / 16));
        var timer = setInterval(function() {
            start = Math.min(start + step, target);
            el.textContent = prefix + start.toLocaleString('en-IN') + suffix;
            if (start >= target) clearInterval(timer);
        }, 16);
    }

    /* ── Pipeline bar entrance ── */
    document.addEventListener('DOMContentLoaded', function() {

        /* Stagger pipeline bar animation */
        var bars = document.querySelectorAll('.pipeline-bar-fill');
        bars.forEach(function(bar, i) {
            var targetWidth = bar.style.width;
            bar.style.width = '0%';
            setTimeout(function() {
                bar.style.width = targetWidth;
            }, 200 + (i * 80));
        });

        /* Stagger KPI card entrance */
        var kpiCards = document.querySelectorAll('.kpi-card');
        kpiCards.forEach(function(card, i) {
            card.style.opacity  = '0';
            card.style.transform = 'translateY(10px)';
            card.style.transition = 'opacity 360ms ease, transform 360ms cubic-bezier(0.16,1,0.3,1)';
            setTimeout(function() {
                card.style.opacity  = '1';
                card.style.transform = 'translateY(0)';
            }, 80 + (i * 55));
        });

        /* Stagger alert items */
        var alertItems = document.querySelectorAll('.alert-item');
        alertItems.forEach(function(item, i) {
            item.style.opacity  = '0';
            item.style.transition = 'opacity 300ms ease';
            setTimeout(function() { item.style.opacity = '1'; }, 300 + (i * 60));
        });

    });

    /* ── Schedule row click → navigate ── */
    document.querySelectorAll('.schedule-table tbody tr').forEach(function(row) {
        row.setAttribute('role', 'link');
        row.setAttribute('tabindex', '0');
        row.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') this.click();
        });
    });

})();
</script>
@endsection