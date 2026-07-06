{{-- resources/views/appointments/index.blade.php --}}
@extends('layouts.app')

@push('styles')
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css' rel='stylesheet' />
<link href='https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css' rel='stylesheet' />
<style>
/* ─── Escape the global layout constraints for full-viewport calendar ── */
#df-content-area {
    overflow: hidden !important;
    display: flex !important;
    flex-direction: column !important;
}

#df-content-inner {
    padding: 0 !important;
    max-width: none !important;
    flex: 1 !important;
    display: flex !important;
    flex-direction: column !important;
    overflow: hidden !important;
    min-height: 0 !important;
}

/* ─── CSS Variables ─────────────────────────────────────────── */
:root {
    --sidebar-w: 320px;
    --header-h: 56px;
    --topbar-h: 52px;

    /* Status colours */
    --c-scheduled: #3b82f6;
    --c-checkin:   #f59e0b;
    --c-in-chair:  #8b5cf6;
    --c-done:      #10b981;
    --c-cancelled: #ef4444;
    --c-no-show:   #6b7280;
    --c-walkin:    #14b8a6;

    /* Treatment category fills (pastel) */
    --t-consultation: #eff6ff;
    --t-implant:      #f5f3ff;
    --t-rct:          #fff7ed;
    --t-surgery:      #fef2f2;
    --t-cleaning:     #f0fdf4;
    --t-followup:     #f8fafc;
    --t-crown:        #fdf4ff;
    --t-orthodontic:  #ecfdf5;
    --t-default:      #f8fafc;

    /* Doctor colours */
    --doc-0: #2563eb;
    --doc-1: #059669;
    --doc-2: #d97706;
    --doc-3: #dc2626;
    --doc-4: #7c3aed;
    --doc-5: #0891b2;
}

/* ─── Layout Shell ──────────────────────────────────────────── */
.appt-shell {
    display: flex;
    flex-direction: column;
    height: calc(100vh - var(--header-h));
    overflow: hidden;
    background: #f1f5f9;
}

/* ─── STICKY Top Bar ────────────────────────────────────────── */
.appt-topbar {
    position: sticky;
    top: 0;
    z-index: 200;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 16px;
    height: var(--topbar-h);
    background: #fff;
    border-bottom: 1px solid #e2e8f0;
    flex-shrink: 0;
    box-shadow: 0 1px 4px rgba(0,0,0,.06);
}

.appt-body {
    display: flex;
    flex-direction: row;
    flex: 1;
    overflow: hidden;
    min-height: 0;
}

/* ─── Calendar area ─────────────────────────────────────────── */
.appt-calendar-area {
    flex: 1;
    overflow: auto;
    padding: 12px;
    min-width: 0;
}

/* ─── Right Sidebar ─────────────────────────────────────────── */
.appt-sidebar {
    width: var(--sidebar-w);
    flex-shrink: 0;
    background: #fff;
    border-left: 1px solid #e2e8f0;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    transition: width .25s ease;
}

.appt-sidebar.collapsed {
    width: 0;
    border-left: none;
}

/* ─── Sidebar Header (no clock — just TODAY label + date) ───── */
.sb-header {
    padding: 12px 16px 10px;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}

.sb-header-left {}

.sb-today-label {
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
    color: #94a3b8;
}

.sb-date {
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    margin-top: 1px;
    line-height: 1.2;
}

.sb-total-pill {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 10px;
    background: #f1f5f9;
    color: #475569;
    border-radius: 20px;
    white-space: nowrap;
}

/* ─── Status Counter Grid ───────────────────────────────────── */
.sb-counters {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 3px;
    padding: 8px 10px;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
}

.sb-counter-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 5px 3px;
    border-radius: 8px;
    border: 1.5px solid transparent;
    cursor: pointer;
    transition: all .15s;
    background: #f8fafc;
}

.sb-counter-btn:hover { background: #f1f5f9; transform: translateY(-1px); }
.sb-counter-btn.active { border-color: currentColor; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.08); }

.sb-counter-dot {
    width: 5px; height: 5px;
    border-radius: 50%;
    margin-bottom: 2px;
}

.sb-counter-num {
    font-size: 14px;
    font-weight: 800;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

.sb-counter-lbl {
    font-size: 8.5px;
    font-weight: 600;
    letter-spacing: .02em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-top: 2px;
    text-align: center;
}

/* Status-specific colours */
.cnt-total     { color: #1e293b; }  .cnt-total     .sb-counter-dot { background: #1e293b; }
.cnt-scheduled { color: var(--c-scheduled); } .cnt-scheduled .sb-counter-dot { background: var(--c-scheduled); }
.cnt-checkin   { color: var(--c-checkin); }   .cnt-checkin   .sb-counter-dot { background: var(--c-checkin); }
.cnt-in_chair  { color: var(--c-in-chair); }  .cnt-in_chair  .sb-counter-dot { background: var(--c-in-chair); }
.cnt-done      { color: var(--c-done); }      .cnt-done      .sb-counter-dot { background: var(--c-done); }
.cnt-cancelled { color: var(--c-cancelled); } .cnt-cancelled .sb-counter-dot { background: var(--c-cancelled); }
.cnt-no_show   { color: var(--c-no-show); }   .cnt-no_show   .sb-counter-dot { background: var(--c-no-show); }
.cnt-walkin    { color: var(--c-walkin); }    .cnt-walkin    .sb-counter-dot { background: var(--c-walkin); }

/* ─── Doctor Filter Chips ───────────────────────────────────── */
.sb-doctor-filter {
    padding: 6px 10px;
    border-bottom: 1px solid #f1f5f9;
    flex-shrink: 0;
}

.sb-doctor-chips {
    display: flex;
    gap: 4px;
    flex-wrap: wrap;
}

.doc-chip {
    padding: 3px 9px;
    border-radius: 20px;
    font-size: 10.5px;
    font-weight: 600;
    cursor: pointer;
    border: 1.5px solid transparent;
    background: #f1f5f9;
    color: #475569;
    transition: all .15s;
}

.doc-chip:hover { background: #e2e8f0; }
.doc-chip.active { color: #fff; border-color: transparent; }

/* ─── Queue header ──────────────────────────────────────────── */
.sb-queue-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 7px 12px 3px;
    flex-shrink: 0;
}

.sb-queue-title {
    font-size: 10.5px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #64748b;
}

/* ─── Queue layout (full width — Notes panel removed) ────────── */
.sb-lower {
    flex: 1;
    display: flex;
    flex-direction: row;
    overflow: hidden;
    min-height: 0;
}

/* Queue column: now takes full width of sb-lower (Notes panel removed) */
.sb-queue-col {
    flex: 1;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
}

.sb-queue-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 4px 6px 10px 10px;
}

.sb-queue-scroll::-webkit-scrollbar { width: 3px; }
.sb-queue-scroll::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }

/* ─── Queue Card ────────────────────────────────────────────── */
.q-card {
    border-radius: 9px;
    border: 1px solid #e2e8f0;
    margin-bottom: 6px;
    overflow: hidden;
    position: relative;
    transition: box-shadow .15s, transform .15s;
    cursor: pointer;
}

.q-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,.09); transform: translateY(-1px); }

.q-card-body { padding: 7px 8px 5px 13px; }

.q-card-left-bar {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    border-radius: 9px 0 0 9px;
}

.q-card-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 4px;
}

.q-card-name {
    font-size: 12px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
    flex: 1;
    min-width: 0;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.q-card-time {
    font-size: 10.5px;
    font-weight: 700;
    color: #475569;
    white-space: nowrap;
    font-variant-numeric: tabular-nums;
}

.q-card-meta {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
    flex-wrap: wrap;
}

.q-card-treatment { font-size: 10px; color: #64748b; font-weight: 500; }
.q-card-doctor    { font-size: 9.5px; color: #94a3b8; }

.q-card-walkin-badge {
    font-size: 8.5px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    background: #ccfbf1;
    color: #0f766e;
    padding: 1px 4px;
    border-radius: 3px;
}

.q-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 2px;
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .04em;
    text-transform: uppercase;
    padding: 2px 6px;
    border-radius: 20px;
    margin-top: 3px;
}

/* Action buttons row */
.q-card-actions {
    display: flex;
    gap: 3px;
    padding: 4px 8px 6px 13px;
    flex-wrap: wrap;
    border-top: 1px solid rgba(0,0,0,.04);
}

.q-action-btn {
    font-size: 9px;
    font-weight: 700;
    padding: 2px 7px;
    border-radius: 4px;
    cursor: pointer;
    border: none;
    transition: all .12s;
    letter-spacing: .02em;
}

.q-action-btn:hover { opacity: .85; transform: scale(1.03); }

.q-btn-checkin { background: #fef3c7; color: #92400e; }
.q-btn-inchair { background: #ede9fe; color: #5b21b6; }
.q-btn-done    { background: #dcfce7; color: #14532d; }
.q-btn-cancel  { background: #fee2e2; color: #991b1b; }
.q-btn-noshow  { background: #f1f5f9; color: #475569; }
.q-btn-edit    { background: #e0f2fe; color: #075985; }
.q-btn-wa      { background: #dcfce7; color: #15803d; }

/* ─── FullCalendar overrides ────────────────────────────────── */
.fc {
    font-family: inherit;
    height: 100%;
    min-height: 600px;
}

.fc-toolbar { margin-bottom: 10px !important; }

.fc-toolbar-title {
    font-size: 15px !important;
    font-weight: 700 !important;
    color: #1e293b !important;
}

.fc-button-primary {
    background: #fff !important;
    border: 1px solid #e2e8f0 !important;
    color: #475569 !important;
    font-size: 12px !important;
    font-weight: 600 !important;
    box-shadow: none !important;
    padding: 4px 10px !important;
}

.fc-button-primary:hover { background: #f8fafc !important; }
.fc-button-primary.fc-button-active { background: #eff6ff !important; color: #2563eb !important; border-color: #bfdbfe !important; }

.fc-timegrid-slot { height: 28px !important; }

/* Blocked slot background band label */
.fc-blocked-slot .fc-event-title {
    font-size: 10px;
    font-weight: 600;
    color: #dc2626;
    padding: 1px 4px;
    opacity: .85;
}

.fc-event {
    border: none !important;       /* kill ALL fc-event borders — our inner div handles the left border */
    border-radius: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
    cursor: pointer !important;
    font-size: 11px !important;
    background: transparent !important; /* let our inner div control bg */
}
.fc-event-main {
    padding: 0 !important;
    height: 100%;
}

.fc-event-title { font-weight: 600 !important; }
.fc-event-time  { font-size: 10px !important; opacity: .8; }

.fc-event.status-done      { opacity: .55; }
.fc-event.status-cancelled { opacity: .35; text-decoration: line-through; }
.fc-event.status-no_show   { opacity: .35; border-style: dashed !important; }
.fc-event.status-in_chair  { outline: 2px solid rgba(139,92,246,.4); outline-offset: 1px; box-shadow: none !important; }

/* ─── Compact card via CSS Container Queries ────────────────── */
.ev-container {
    container-type: inline-size;
    container-name: apt-card;
    height: 100%;
}
/* 3+ overlaps: hide treatment + meta, name only */
@container apt-card (max-width: 88px) {
    .ev-inner     { padding: 2px 3px !important; }
    .ev-treatment { display: none !important; }
    .ev-meta      { display: none !important; }
    .ev-name      { font-size: 10px !important; }
}
/* 4+ overlaps: shrink name further */
@container apt-card (max-width: 55px) {
    .ev-name { font-size: 9px !important; }
}

/* ─── Quick Hover Card ──────────────────────────────────────── */
#quick-view-card {
    position: fixed;
    z-index: 1050;
    width: 280px;
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    box-shadow: 0 10px 40px rgba(0,0,0,.14);
    pointer-events: auto;
    display: none;
    font-size: 12.5px;
    overflow: hidden;
    animation: qvcFadeIn .12s ease;
}

@keyframes qvcFadeIn {
    from { opacity:0; transform: translateY(4px) scale(.98); }
    to   { opacity:1; transform: translateY(0) scale(1); }
}

.qvc-header {
    padding: 12px 14px 8px;
    border-bottom: 1px solid #f1f5f9;
    cursor: pointer;
    transition: background .15s;
}
.qvc-header:hover { background: #f8fafc; }

.qvc-name { font-size: 14px; font-weight: 800; color: #1e293b; }
.qvc-sub  { font-size: 11px; color: #64748b; margin-top: 1px; }

.qvc-body { padding: 10px 14px; }

.qvc-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 3px 0;
    border-bottom: 1px solid #f8fafc;
    font-size: 11.5px;
}

.qvc-row:last-child { border-bottom: none; }
.qvc-row-key { color: #94a3b8; font-weight: 500; }
.qvc-row-val { color: #1e293b; font-weight: 600; text-align: right; }

.qvc-actions {
    padding: 8px 12px 10px;
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    border-top: 1px solid #f1f5f9;
}

.qvc-btn {
    font-size: 10.5px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 6px;
    cursor: pointer;
    border: none;
    transition: all .12s;
    letter-spacing: .02em;
}

/* ─── Modal backdrop + shell ────────────────────────────────── */
.modal-backdrop-custom {
    position: fixed; inset: 0;
    background: rgba(15,23,42,.4);
    z-index: 1040;
    display: none;
    backdrop-filter: blur(2px);
}

.modal-custom {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    z-index: 1050;
    width: 460px;
    max-width: 95vw;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 20px 60px rgba(0,0,0,.18);
    display: none;
    animation: modalIn .2s ease;
}

@keyframes modalIn {
    from { opacity:0; transform: translate(-50%, calc(-50% + 12px)); }
    to   { opacity:1; transform: translate(-50%, -50%); }
}

.modal-custom-header {
    padding: 18px 20px 14px;
    border-bottom: 1px solid #f1f5f9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-custom-title {
    font-size: 15px;
    font-weight: 800;
    color: #1e293b;
}

/* Tab strip inside modal */
.modal-tabs {
    display: flex;
    gap: 0;
    border-bottom: 1px solid #f1f5f9;
}

.modal-tab-btn {
    flex: 1;
    padding: 10px 14px;
    font-size: 12.5px;
    font-weight: 700;
    color: #94a3b8;
    border: none;
    background: none;
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: all .15s;
    letter-spacing: .01em;
}

.modal-tab-btn:hover { color: #475569; background: #fafafa; }
.modal-tab-btn.active { color: #2563eb; border-bottom-color: #2563eb; background: none; }

.modal-tab-panel { display: none; }
.modal-tab-panel.active { display: block; }

.modal-custom-body { padding: 16px 20px; }
.modal-custom-footer {
    padding: 12px 20px 16px;
    border-top: 1px solid #f1f5f9;
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

/* ─── Form elements ─────────────────────────────────────────── */
.form-label-sm {
    font-size: 11px;
    font-weight: 700;
    color: #64748b;
    letter-spacing: .04em;
    text-transform: uppercase;
    display: block;
    margin-bottom: 4px;
}

.form-control-sm {
    width: 100%;
    padding: 7px 10px;
    font-size: 13px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    outline: none;
    transition: border-color .15s;
    background: #fff;
    color: #1e293b;
}

.form-control-sm:focus { border-color: #3b82f6; }

.btn-primary-sm {
    padding: 8px 20px;
    background: #2563eb;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: background .15s;
}

.btn-primary-sm:hover { background: #1d4ed8; }

.btn-secondary-sm {
    padding: 8px 20px;
    background: #f1f5f9;
    color: #475569;
    font-size: 13px;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    cursor: pointer;
}

/* ─── Top bar elements ──────────────────────────────────────── */
.topbar-search {
    flex: 1;
    max-width: 240px;
    position: relative;
}

.topbar-search input {
    width: 100%;
    padding: 6px 10px 6px 30px;
    font-size: 12.5px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    outline: none;
    background: #f8fafc;
}

.topbar-search input:focus { border-color: #3b82f6; background: #fff; }

.topbar-search svg {
    position: absolute;
    left: 9px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
}

/* Single compact clock (no date) */
.topbar-clock {
    font-size: 13px;
    font-weight: 700;
    color: #1e293b;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
    letter-spacing: -.2px;
}

.topbar-date-label {
    font-size: 11px;
    color: #94a3b8;
    white-space: nowrap;
}

.topbar-divider {
    width: 1px;
    height: 22px;
    background: #e2e8f0;
    flex-shrink: 0;
}

/* Add Appointment button */
.btn-add-appt {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 6px 14px;
    background: #2563eb;
    color: #fff;
    font-size: 12.5px;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    text-decoration: none;
    white-space: nowrap;
    transition: background .15s;
}

.btn-add-appt:hover { background: #1d4ed8; color: #fff; }

/* Walk-In button — subtle, beside Add Appointment */
.btn-walkin {
    display: flex;
    align-items: center;
    gap: 4px;
    padding: 6px 12px;
    background: #f0fdf4;
    color: #15803d;
    font-size: 12px;
    font-weight: 700;
    border: 1.5px solid #bbf7d0;
    border-radius: 8px;
    cursor: pointer;
    white-space: nowrap;
    transition: all .15s;
}

.btn-walkin:hover { background: #dcfce7; border-color: #86efac; }

.sidebar-toggle-btn {
    background: #f1f5f9;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    padding: 5px 9px;
    cursor: pointer;
    font-size: 14px;
    color: #475569;
    line-height: 1;
    transition: background .15s;
}

.sidebar-toggle-btn:hover { background: #e2e8f0; }

/* Conflict warning banner */
.conflict-warning {
    display: none;
    align-items: center;
    gap: 7px;
    padding: 6px 12px;
    background: #fef3c7;
    border: 1px solid #fcd34d;
    border-radius: 7px;
    font-size: 11.5px;
    font-weight: 600;
    color: #92400e;
    margin-top: 4px;
}

/* Walkin tab success notice */
#wi-success {
    display:none;
    margin-top:8px;
    padding:8px 12px;
    background:#dcfce7;
    border-radius:8px;
    font-size:12px;
    color:#15803d;
    font-weight:600;
}

/* Responsive */
@media (max-width: 900px) {
    .appt-sidebar { display: none; }
}

/* ─── Flatpickr overrides ───────────────────────────────────── */
.flatpickr-calendar {
    font-family: 'Inter', sans-serif !important;
    font-size: 13px !important;
    border-radius: 12px !important;
    box-shadow: 0 8px 30px rgba(0,0,0,.14) !important;
    border: 1px solid #e2e8f0 !important;
}
.flatpickr-day.selected,
.flatpickr-day.selected:hover {
    background: #2563eb !important;
    border-color: #2563eb !important;
}
.flatpickr-day:hover { background: #eff6ff !important; }
.flatpickr-months .flatpickr-month { background: #f8fafc !important; }
.flatpickr-current-month { font-size: 13px !important; font-weight: 700 !important; }
.flatpickr-weekday { font-size: 11px !important; font-weight: 700 !important; color: #94a3b8 !important; }
.flatpickr-day.today { border-color: #2563eb !important; }

/* Date input wrapper — shows calendar icon */
.date-input-wrap {
    position: relative;
}
.date-input-wrap svg {
    position: absolute;
    right: 9px;
    top: 50%;
    transform: translateY(-50%);
    color: #94a3b8;
    pointer-events: none;
}
.date-input-wrap input {
    padding-right: 30px !important;
    cursor: pointer;
}

/* Time slot select */
.time-slot-select {
    width: 100%;
    padding: 7px 10px;
    font-size: 13px;
    border: 1.5px solid #e2e8f0;
    border-radius: 8px;
    outline: none;
    transition: border-color .15s;
    background: #fff;
    color: #1e293b;
    cursor: pointer;
    appearance: auto;
}
.time-slot-select:focus { border-color: #3b82f6; }
</style>
@endpush

@section('content')
{{-- ════════════════════════════════════════════════════════════
     DATA ENCODING FOR JS
══════════════════════════════════════════════════════════════ --}}
<script>
window.__APPT_DATA = {
    appointments:  @json($appointments),
    todayQueue:    @json($todayAppointments),
    doctors:       @json($doctors),
    statusCounts:  @json($statusCounts),
    categories:    @json($treatmentCategories),
    today:         "{{ today()->toDateString() }}",
    csrfToken:     "{{ csrf_token() }}",
    routes: {
        store:        "{{ route('appointments.store') }}",
        todayQueue:   "{{ route('appointments.queue.today') }}",
        statusCounts: "{{ route('appointments.status.counts') }}",
        statusUpdate: "{{ url('/appointments') }}/{{'{id}'}}/status",
        cancelAppt:       "{{ url('/appointments') }}/{{'{id}'}}/cancel",
        revertAppt:       "{{ url('/appointments') }}/{{'{id}'}}/revert",
        checkConflict:    "{{ route('appointments.check.conflict') }}",
        quickView:        "{{ url('/appointments') }}/{{'{id}'}}/quick",
        assignOperatory:  "{{ url('/appointments') }}/{{'{id}'}}/operatory",
        hideAppt:         "{{ url('/appointments') }}/{{'{id}'}}/hide",
        deleteAppt:       "{{ url('/appointments') }}/{{'{id}'}}",
        reschedule:       "{{ url('/appointments') }}/{{'{id}'}}/reschedule",
        patientQuickStore: "{{ route('patients.quick-store') }}",
    },
    operatories: @json(\App\Models\Operatory::forBranch(auth()->user()->branch_id)->active()->ordered()->get(['id','name'])),
    calendarPrefs: {
        cardStyle:   "{{ $calendarPrefs['calendar_card_style']   ?? 'strip' }}",
        colorSource: "{{ $calendarPrefs['calendar_color_source'] ?? 'treatment' }}",
    },
};
</script>

{{-- ════════════════════════════════════════════════════════════
     SHELL
══════════════════════════════════════════════════════════════ --}}
<div class="appt-shell" x-data="appointmentApp()" x-init="init()">

    {{-- ── STICKY TOP BAR ──────────────────────────────────── --}}
    <div class="appt-topbar">

        {{-- Sidebar toggle --}}
        <button class="sidebar-toggle-btn" @click="sidebarOpen = !sidebarOpen; setTimeout(() => { if (window.calendar) window.calendar.updateSize(); }, 280);" title="Toggle Sidebar">☰</button>

        {{-- Title --}}
        <span style="font-size:13px;font-weight:800;color:#1e293b;white-space:nowrap;">Appointments</span>

        <div class="topbar-divider"></div>

        {{-- Search --}}
        <div class="topbar-search">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <input type="text"
                   placeholder="Search patient, phone, treatment…"
                   x-model="searchQuery"
                   @input.debounce.200ms="applyFilters()">
        </div>

        {{-- Doctor filter --}}
        <select class="form-control-sm" style="width:150px;padding:5px 8px;font-size:12px;"
                x-model="filterDoctorId"
                @change="applyFilters(); refreshQueue()">
            <option value="">All Doctors</option>
            @foreach($doctors as $doc)
            <option value="{{ $doc->id }}">{{ $doc->name }}</option>
            @endforeach
        </select>

        <div class="topbar-divider"></div>

        {{-- Single clock (time only — compact) --}}
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:0;">
            <div class="topbar-clock" x-text="clockTime"></div>
            <div class="topbar-date-label" x-text="clockDateShort"></div>
        </div>

        <div class="topbar-divider"></div>

        {{-- Walk-In button (subtle, beside Add Appointment) — pushed to right corner --}}
        <button class="btn-walkin" style="margin-left:auto;" onclick="openCombinedModal('walkin')">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/>
            </svg>
            Walk-In
        </button>

        {{-- Add Appointment --}}
        <button class="btn-add-appt" onclick="openCombinedModal('appointment')">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path d="M12 5v14M5 12h14"/>
            </svg>
            Add Appointment
        </button>

    </div>

    {{-- ── BODY ─────────────────────────────────────────────── --}}
    <div class="appt-body">

        {{-- ── CALENDAR ─────────────────────────────────────── --}}
        <div class="appt-calendar-area">
            <div id="dentfluence-calendar"></div>
        </div>

        {{-- ── RIGHT SIDEBAR ────────────────────────────────── --}}
        <div class="appt-sidebar" :class="{ collapsed: !sidebarOpen }">

            {{-- Sidebar Header: TODAY label + date only (clock is in topbar) --}}
            <div class="sb-header">
                <div class="sb-header-left">
                    <div class="sb-today-label">Today</div>
                    <div class="sb-date" x-text="fullDate"></div>
                </div>
                <div class="sb-total-pill" x-text="(counts.total ?? 0) + ' patients'"></div>
            </div>

            {{-- Status Counters --}}
            <div class="sb-counters">
                <template x-for="c in counterDefs" :key="c.key">
                    <button class="sb-counter-btn"
                            :class="[`cnt-${c.key}`, activeStatusFilter === c.key ? 'active' : '']"
                            @click="toggleStatusFilter(c.key)">
                        <div class="sb-counter-dot"></div>
                        <div class="sb-counter-num" x-text="counts[c.key] ?? 0"></div>
                        <div class="sb-counter-lbl" x-text="c.label"></div>
                    </button>
                </template>
            </div>

            {{-- Doctor Chips --}}
            <div class="sb-doctor-filter">
                <div class="sb-doctor-chips">
                    <button class="doc-chip"
                            :class="{ active: queueDoctorId === '' }"
                            :style="queueDoctorId === '' ? 'background:#1e293b;color:#fff;' : ''"
                            @click="queueDoctorId = ''; refreshQueue()">
                        All
                    </button>
                    @foreach($doctors as $i => $doc)
                    <button class="doc-chip"
                            :class="{ active: queueDoctorId === '{{ $doc->id }}' }"
                            :style="queueDoctorId === '{{ $doc->id }}' ? `background: var(--doc-{{ $i % 6 }});color:#fff;` : ''"
                            @click="queueDoctorId = '{{ $doc->id }}'; refreshQueue()">
                        {{ Str::limit($doc->name, 11) }}
                    </button>
                    @endforeach
                </div>
            </div>

            {{-- Queue label row --}}
            <div class="sb-queue-header">
                <span class="sb-queue-title">
                    Patient Queue
                    <span x-show="activeStatusFilter"
                          style="font-size:8.5px;background:#eff6ff;color:#2563eb;padding:1px 5px;border-radius:4px;margin-left:4px;"
                          x-text="activeStatusFilter"></span>
                </span>
                <span style="font-size:10.5px;color:#94a3b8;font-weight:600;" x-text="queueList.length + ' in queue'"></span>
            </div>

            {{-- ── LOWER: Queue (full width) ────── --}}
            <div class="sb-lower">

                {{-- Queue column --}}
                <div class="sb-queue-col">
                    <div class="sb-queue-scroll">

                        <template x-if="queueList.length === 0">
                            <div style="text-align:center;padding:30px 0;color:#94a3b8;font-size:11.5px;">
                                No patients in queue
                            </div>
                        </template>

                        <template x-for="apt in queueList" :key="apt.id">
                            <div class="q-card"
                                 :style="`background: ${getTreatmentFill(apt.treatment_category)}; border-color: rgba(0,0,0,.06);`"
                                 @click.stop="showQuickView(apt, $event)">

                                <div class="q-card-left-bar"
                                     :style="`background: ${getDoctorColor(apt.doctor_id)};`"></div>

                                <div class="q-card-body">
                                    <div class="q-card-top">
                                        <div class="q-card-name" x-text="apt.patient_name"></div>
                                        <div class="q-card-time" x-text="apt.appointment_time"></div>
                                    </div>

                                    <div class="q-card-meta">
                                        <span class="q-card-treatment"
                                              x-text="apt.treatment_category || apt.treatment || apt.type"></span>
                                        <span style="color:#cbd5e1">·</span>
                                        <span class="q-card-doctor" x-text="apt.doctor_name"></span>
                                        <span x-show="apt.is_walkin" class="q-card-walkin-badge">Walk-in</span>
                                    </div>

                                    <div>
                                        <span class="q-status-badge"
                                              :style="`background: ${getStatusBg(apt.status)}; color: ${getStatusColor(apt.status)};`"
                                              x-text="getStatusLabel(apt.status)"></span>
                                        <span style="font-size:9.5px;color:#94a3b8;margin-left:4px;"
                                              x-text="apt.duration_minutes + 'min'"></span>
                                        <span x-show="apt.chair_number"
                                              style="font-size:9.5px;color:#94a3b8;margin-left:4px;"
                                              x-text="'Ch.' + apt.chair_number"></span>
                                        {{-- Operatory pill — click to reassign --}}
                                        <span x-show="apt.operatory_name"
                                              @click.stop="openOperatoryPicker(apt)"
                                              title="Click to change operatory"
                                              style="font-size:9.5px;color:#7c3aed;background:#ede9fe;padding:1px 5px;border-radius:4px;margin-left:4px;font-weight:500;cursor:pointer;"
                                              x-text="apt.operatory_name"></span>
                                        <span x-show="!apt.operatory_name && operatories.length > 0"
                                              @click.stop="openOperatoryPicker(apt)"
                                              title="Assign operatory"
                                              style="font-size:9.5px;color:#c4b5d4;background:#f5f3ff;padding:1px 5px;border-radius:4px;margin-left:4px;cursor:pointer;">
                                            + chair
                                        </span>
                                    </div>
                                </div>

                                {{-- Inline action buttons --}}
                                <div class="q-card-actions" @click.stop="">
                                    <template x-if="apt.status === 'scheduled'">
                                        <button class="q-action-btn q-btn-checkin"
                                                @click.stop="checkInWithOperatory(apt, 'checkin')">✓ In</button>
                                    </template>
                                    <template x-if="apt.status === 'checkin'">
                                        <button class="q-action-btn q-btn-inchair"
                                                @click.stop="checkInWithOperatory(apt, 'in_chair')">Chair</button>
                                    </template>
                                    <template x-if="apt.status === 'in_chair'">
                                        <button class="q-action-btn q-btn-done"
                                                @click.stop="updateStatus(apt.id, 'done')">✓ Done</button>
                                    </template>
                                    <template x-if="['scheduled','checkin'].includes(apt.status)">
                                        <button class="q-action-btn q-btn-noshow"
                                                @click.stop="updateStatus(apt.id, 'no_show')">N/S</button>
                                    </template>
                                    <template x-if="!['done','cancelled'].includes(apt.status)">
                                        <button class="q-action-btn q-btn-cancel"
                                                @click.stop="confirmCancel(apt.id)">✕</button>
                                    </template>
                                    <button class="q-action-btn q-btn-wa"
                                            @click.stop="waContact(apt.patient_phone)"
                                            x-show="apt.patient_phone">
                                        WA
                                    </button>
                                    <a class="q-action-btn q-btn-edit"
                                       :href="`/patients/${apt.patient_id}`"
                                       @click.stop=""
                                       style="text-decoration:none;display:inline-flex;align-items:center;">
                                       
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

            </div>{{-- /sb-lower --}}

        </div>{{-- /sidebar --}}

    </div>{{-- /body --}}

    {{-- ── Operatory Picker Mini-Modal ─────────────────────────────
         Shown when front desk clicks "✓ In" or "Chair" buttons.
         Lets them confirm / change the assigned chair before the
         status update fires. Skip button goes straight through.
    ─────────────────────────────────────────────────────────────── --}}
    <div x-show="opPicker.show"
         x-cloak
         @click.self="opPicker.show = false"
         style="position:fixed;inset:0;background:rgba(0,0,0,.35);z-index:9999;display:flex;align-items:center;justify-content:center;">

        <div @click.stop
             style="background:#fff;border-radius:12px;padding:24px;width:320px;box-shadow:0 8px 32px rgba(0,0,0,.18);font-family:'Inter',sans-serif;">

            {{-- Header --}}
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <div>
                    <p style="font-size:13px;font-weight:700;color:#380740;margin:0;" x-text="opPicker.patientName"></p>
                    <p style="font-size:11px;color:#9a7aaa;margin:2px 0 0;"
                       x-text="opPicker.nextStatus === 'checkin' ? 'Check In → Select Operatory' : 'Moving to Chair → Confirm Operatory'"></p>
                </div>
                <button @click="opPicker.show = false"
                        style="border:none;background:none;color:#9a7aaa;font-size:18px;cursor:pointer;line-height:1;">✕</button>
            </div>

            {{-- Operatory select --}}
            <div style="margin-bottom:16px;">
                <label style="font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#9a7aaa;display:block;margin-bottom:6px;">
                    Operatory
                </label>
                <select x-model="opPicker.selectedId"
                        style="width:100%;padding:9px 12px;border:1.5px solid #d4b8dc;border-radius:7px;font-size:13px;color:#380740;background:#fff;font-family:'Inter',sans-serif;outline:none;">
                    <option value="">— None —</option>
                    <template x-for="op in operatories" :key="op.id">
                        <option :value="op.id" x-text="op.name"></option>
                    </template>
                </select>
            </div>

            {{-- Buttons --}}
            <div style="display:flex;gap:8px;">
                <button @click="confirmCheckIn()"
                        :disabled="opPicker.saving"
                        style="flex:1;padding:9px;background:#6a0f70;color:#fff;border:none;border-radius:7px;font-size:13px;font-weight:600;cursor:pointer;"
                        :style="opPicker.saving ? 'opacity:.6;cursor:not-allowed;' : ''">
                    <span x-text="opPicker.saving ? 'Saving…' : (opPicker.nextStatus === 'checkin' ? '✓ Check In' : 'In Chair')"></span>
                </button>
                <button @click="skipOperatoryAndCheckIn()"
                        :disabled="opPicker.saving"
                        style="padding:9px 14px;background:#f3f4f6;color:#374151;border:none;border-radius:7px;font-size:12px;cursor:pointer;">
                    Skip
                </button>
            </div>

        </div>
    </div>

</div>{{-- /shell --}}


{{-- ════════════════════════════════════════════════════════════
     QUICK VIEW FLOATING CARD
══════════════════════════════════════════════════════════════ --}}
{{-- ── Cancel with Reason Modal ─────────────────────────────── --}}
<div id="cancel-reason-modal"
     style="display:none;position:fixed;inset:0;z-index:10000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
    <div style="background:#fff;border-radius:10px;width:380px;padding:24px;box-shadow:0 20px 60px rgba(0,0,0,.25);">
        <h3 style="font-size:15px;font-weight:700;color:#1e293b;margin:0 0 4px;">Cancel Appointment</h3>
        <p style="font-size:12px;color:#64748b;margin:0 0 16px;" id="crm-patient-name">—</p>

        <label style="font-size:11.5px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Cancelled by *</label>
        <div style="display:flex;gap:8px;margin-bottom:14px;">
            <button type="button" id="crm-party-patient" onclick="setCancelParty('patient')"
                    style="flex:1;padding:8px;border:1.5px solid #d1d5db;background:#fff;border-radius:6px;font-size:12.5px;font-weight:600;color:#374151;cursor:pointer;">
                Patient
            </button>
            <button type="button" id="crm-party-clinic" onclick="setCancelParty('clinic')"
                    style="flex:1;padding:8px;border:1.5px solid #d1d5db;background:#fff;border-radius:6px;font-size:12.5px;font-weight:600;color:#374151;cursor:pointer;">
                Clinic
            </button>
        </div>

        <label style="font-size:11.5px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Reason for cancellation *</label>
        <textarea id="crm-reason" rows="3" placeholder="e.g. Patient requested reschedule, Doctor unavailable…"
                  style="width:100%;padding:8px 10px;border:1.5px solid #d1d5db;border-radius:6px;font-size:13px;resize:none;outline:none;box-sizing:border-box;font-family:inherit;"
                  onfocus="this.style.borderColor='#6a0f70'" onblur="this.style.borderColor='#d1d5db'"></textarea>
        <div id="crm-error" style="display:none;font-size:11.5px;color:#dc2626;margin-top:4px;"></div>
        <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:16px;">
            <button onclick="closeCancelModal()"
                    style="padding:7px 16px;border:1.5px solid #d1d5db;background:#fff;border-radius:6px;font-size:13px;cursor:pointer;color:#374151;">
                Keep Appointment
            </button>
            <button onclick="submitCancel()"
                    style="padding:7px 16px;background:#dc2626;color:#fff;border:none;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">
                Cancel Appointment
            </button>
        </div>
    </div>
</div>

<div id="quick-view-card">
    <div class="qvc-header" id="qvc-header" title="Go to patient profile">
        <div class="qvc-name" id="qvc-name">—</div>
        <div class="qvc-sub" id="qvc-sub">—</div>
    </div>
    <div class="qvc-body">
        <div class="qvc-row"><span class="qvc-row-key">Doctor</span><span class="qvc-row-val" id="qvc-doctor">—</span></div>
        <div class="qvc-row"><span class="qvc-row-key">Treatment</span><span class="qvc-row-val" id="qvc-treatment">—</span></div>
        <div class="qvc-row"><span class="qvc-row-key">Duration</span><span class="qvc-row-val" id="qvc-duration">—</span></div>
        <div class="qvc-row"><span class="qvc-row-key">Status</span><span class="qvc-row-val" id="qvc-status">—</span></div>
        <div class="qvc-row" id="qvc-age-row" style="display:none;"><span class="qvc-row-key">Age</span><span class="qvc-row-val" id="qvc-age">—</span></div>
        <div class="qvc-row"><span class="qvc-row-key">Chair</span><span class="qvc-row-val" id="qvc-chair">—</span></div>
        <div class="qvc-row"><span class="qvc-row-key">Phone</span><span class="qvc-row-val" id="qvc-phone">—</span></div>
        <div class="qvc-row" id="qvc-checkin-row" style="display:none;"><span class="qvc-row-key">Checked In</span><span class="qvc-row-val" id="qvc-checkin-time">—</span></div>
        <div class="qvc-row" id="qvc-inchair-row" style="display:none;"><span class="qvc-row-key">In Chair At</span><span class="qvc-row-val" id="qvc-inchair-time">—</span></div>
        <div class="qvc-row" id="qvc-done-row" style="display:none;"><span class="qvc-row-key">Completed At</span><span class="qvc-row-val" id="qvc-done-time">—</span></div>
        <div class="qvc-row" id="qvc-notes-row" style="display:none;">
            <span class="qvc-row-key">Notes</span><span class="qvc-row-val" id="qvc-notes">—</span>
        </div>
    </div>
    <div class="qvc-actions" id="qvc-actions"></div>
    <div style="padding:8px 12px 6px;display:flex;gap:6px;border-top:1px solid #f1f5f9;flex-wrap:wrap;">
        <button id="qvc-edit-btn" onclick="qvcEdit()"
                style="flex:1;font-size:10px;font-weight:700;padding:5px 8px;border-radius:6px;background:#fef9c3;color:#854d0e;border:none;cursor:pointer;"
                onmouseover="this.style.background='#fef08a'" onmouseout="this.style.background='#fef9c3'">
            ✏️ Edit
        </button>
        <button id="qvc-reschedule-btn" onclick="qvcReschedule()"
                style="flex:1;font-size:10px;font-weight:700;padding:5px 8px;border-radius:6px;background:#eff6ff;color:#1d4ed8;border:none;cursor:pointer;"
                onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
            Reschedule
        </button>
    </div>
    <div style="padding:0 12px 10px;display:flex;gap:6px;flex-wrap:wrap;">
        <button id="qvc-cancel-btn" onclick="qvcCancel()"
                style="flex:1;font-size:10px;font-weight:700;padding:5px 8px;border-radius:6px;background:#fee2e2;color:#991b1b;border:none;cursor:pointer;"
                onmouseover="this.style.background='#fecaca'" onmouseout="this.style.background='#fee2e2'">
            ✕ Cancel Appt
        </button>
        <button id="qvc-revert-btn" onclick="qvcRevert()"
                style="flex:1;font-size:10px;font-weight:700;padding:5px 8px;border-radius:6px;background:#f0fdf4;color:#166534;border:none;cursor:pointer;display:none;"
                onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='#f0fdf4'">
            ↩ Revert Status
        </button>
    </div>
    {{-- Shown only for cancelled appointments --}}
    <div id="qvc-cancelled-actions" style="display:none;padding:0 12px 10px;display:none;gap:6px;flex-wrap:wrap;border-top:1px solid #fee2e2;padding-top:8px;">
        <button onclick="qvcHideFromCalendar()"
                style="flex:1;font-size:10px;font-weight:700;padding:5px 8px;border-radius:6px;background:#fff7ed;color:#9a3412;border:1.5px solid #fed7aa;cursor:pointer;"
                onmouseover="this.style.background='#ffedd5'" onmouseout="this.style.background='#fff7ed'"
                title="Keep in records but hide from calendar view">
            Hide from Calendar
        </button>
        <button onclick="qvcDeleteAppt()"
                style="flex:1;font-size:10px;font-weight:700;padding:5px 8px;border-radius:6px;background:#fef2f2;color:#7f1d1d;border:1.5px solid #fca5a5;cursor:pointer;"
                onmouseover="this.style.background='#fee2e2'" onmouseout="this.style.background='#fef2f2'"
                title="Permanently delete this appointment">
            Delete Permanently
        </button>
    </div>
</div>


{{-- Appointment modal is global (injected via layouts/app.blade.php) --}}


{{-- ════════════════════════════════════════════════════════════
     SCRIPTS
══════════════════════════════════════════════════════════════ --}}
@push('scripts')
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>
<script src='https://cdn.jsdelivr.net/npm/flatpickr'></script>

<script>
// ─── 12-hr Time Slot Builder ──────────────────────────────────
// Generates options from 8:00 AM to 9:30 PM in 30-min steps
function buildTimeSlots(selectId, selectedValue) {
    const sel = document.getElementById(selectId);
    if (!sel) return;
    sel.innerHTML = '';
    for (let h = 8; h <= 21; h++) {
        for (let m = 0; m < 60; m += 30) {
            const h24  = h.toString().padStart(2, '0');
            const mm   = m.toString().padStart(2, '0');
            const val  = `${h24}:${mm}`;             // 24hr for backend
            const ampm = h < 12 ? 'AM' : 'PM';
            const h12  = h === 0 ? 12 : h > 12 ? h - 12 : h;
            const lbl  = `${h12}:${mm} ${ampm}`;     // 12hr for display
            const opt  = document.createElement('option');
            opt.value = val;
            opt.textContent = lbl;
            if (val === selectedValue) opt.selected = true;
            sel.appendChild(opt);
        }
    }
}

// Nearest 30-min slot to now
function nearestSlot() {
    const now  = new Date();
    const mins = now.getHours() * 60 + now.getMinutes();
    const snap = Math.ceil(mins / 30) * 30;
    const h    = Math.min(Math.floor(snap / 60), 21);
    const m    = snap % 60 === 0 ? '00' : '30';
    return `${h.toString().padStart(2,'0')}:${m}`;
}

// ─── Flatpickr instances ──────────────────────────────────────
let amDatePicker = null;
window.amDatePicker = null; // exposed globally for _modal.blade.php

function initFlatpickrs() {
    amDatePicker = window.amDatePicker = flatpickr('#am-date', {
        dateFormat:   'Y-m-d',      // underlying value sent to server (YYYY-MM-DD)
        altInput:     true,
        altFormat:    'd-m-Y',      // display to user: DD-MM-YYYY
        // No minDate — backdated appointments allowed for rescheduling and data entry
        disableMobile: true,
        onChange(selectedDates, dateStr, instance) {
            if (selectedDates[0]) {
                instance.element.dataset.isoDate =
                    selectedDates[0].toISOString().split('T')[0];
            }
        },
    });
}

// ─── Date format helper: YYYY-MM-DD → DD/MM ────────────────────
function fmtDate(str) {
    if (!str) return '—';
    const [y, m, d] = str.split('-');
    return `${d}/${m}/${y.slice(2)}`; // e.g. 18/06/26
}

// ─── Doctor color palette ──────────────────────────────────────
const DOC_COLORS = ['#2563eb','#059669','#d97706','#dc2626','#7c3aed','#0891b2','#be185d','#0284c7'];

// Treatment category → pastel fill
const TREAT_FILLS = {
    consultation: '#eff6ff',
    implant:      '#f5f3ff',
    rct:          '#fff7ed',
    'root canal': '#fff7ed',
    surgery:      '#fef2f2',
    cleaning:     '#f0fdf4',
    scaling:      '#f0fdf4',
    follow:       '#f8fafc',
    crown:        '#fdf4ff',
    orthodontic:  '#ecfdf5',
    braces:       '#ecfdf5',
    filling:      '#fffbeb',
    extraction:   '#fff1f2',
    whitening:    '#fefce8',
    default:      '#f8fafc',
};

const STATUS_META = {
    scheduled: { label: 'Scheduled',   color: '#2563eb', bg: '#eff6ff' },
    checkin:   { label: 'Checked In',  color: '#92400e', bg: '#fef3c7' },
    in_chair:  { label: 'In Chair',    color: '#5b21b6', bg: '#ede9fe' },
    done:      { label: 'Completed',   color: '#14532d', bg: '#dcfce7' },
    cancelled: { label: 'Cancelled',   color: '#991b1b', bg: '#fee2e2' },
    no_show:   { label: 'No Show',     color: '#374151', bg: '#f1f5f9' },
    checkout:  { label: 'Checked Out', color: '#14532d', bg: '#dcfce7' },
};

// Map doctor id → color index
const doctorColorMap = {};
window.__APPT_DATA.doctors.forEach((d, i) => {
    doctorColorMap[d.id] = DOC_COLORS[i % DOC_COLORS.length];
});

function getDoctorColor(docId) {
    return doctorColorMap[docId] || '#94a3b8';
}

function getTreatmentFill(catName) {
    if (!catName) return TREAT_FILLS.default;
    const k = Object.keys(TREAT_FILLS).find(k => catName.toLowerCase().includes(k));
    return k ? TREAT_FILLS[k] : TREAT_FILLS.default;
}

// ─── AutoDuration map ─────────────────────────────────────────
const AUTO_DURATION = {
    consultation: 30, rct: 60, 'root canal': 60,
    implant: 90, surgery: 90, cleaning: 45, scaling: 45,
    follow: 30, crown: 60, extraction: 30, filling: 45,
    orthodontic: 30, braces: 30, xray: 15, whitening: 60,
};

function autoDuration(catName) {
    if (!catName) return 30;
    const lower = catName.toLowerCase();
    for (const [k, v] of Object.entries(AUTO_DURATION)) {
        if (lower.includes(k)) return v;
    }
    return 30;
}

// ─── FullCalendar ─────────────────────────────────────────────
let calendar;

function buildCalendarEvents(appointments) {
    return appointments.map(apt => {
        const [h, m] = apt.appointment_time.split(':').map(Number);
        const start  = `${apt.appointment_date}T${apt.appointment_time}`;
        const endMin = h * 60 + m + (apt.duration_minutes || 30);
        const endH   = Math.floor(endMin / 60).toString().padStart(2,'0');
        const endM   = (endMin % 60).toString().padStart(2,'0');
        const end    = `${apt.appointment_date}T${endH}:${endM}`;

        const docColor  = getDoctorColor(apt.doctor_id);
        const treatFill = getTreatmentFill(apt.treatment_category);

        return {
            id:              apt.id,
            title:           apt.patient_name,
            start, end,
            backgroundColor: treatFill,
            borderColor:     docColor,
            textColor:       '#1e293b',
            classNames:      [`status-${apt.status}`],
            extendedProps:   apt,
        };
    });
}

// ─── Blocked Slots ─────────────────────────────────────────────
let blockedSlotSource = null; // FullCalendar event source reference

function buildBlockedEvents(slots) {
    return slots.map(s => ({
        id:              'block_' + s.id,
        title:           '' + (s.reason || s.block_type),
        start:           s.start,
        end:             s.end,
        display:         'background',        // renders as a shaded background band
        backgroundColor: 'rgba(239,68,68,0.15)',
        borderColor:     '#ef4444',
        classNames:      ['fc-blocked-slot'],
        extendedProps:   { ...s, _isBlock: true },
    }));
}

async function fetchAndRenderBlockedSlots(startStr, endStr) {
    try {
        const url = `/appointments/blocked-slots?start=${startStr}&end=${endStr}`;
        const r   = await fetch(url, { headers: { 'Accept': 'application/json' } });
        if (!r.ok) return;
        const slots = await r.json();
        // Remove old block source, add fresh one
        if (blockedSlotSource) {
            blockedSlotSource.remove();
        }
        blockedSlotSource = calendar.addEventSource(buildBlockedEvents(slots));
    } catch {}
}

function initCalendar(appointments) {
    const el = document.getElementById('dentfluence-calendar');

    calendar = new FullCalendar.Calendar(el, {
        initialView:  'timeGridWeek',
        locale:       'en-US',      // AM/PM time format
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,timeGridDay',
        },
        slotMinTime:    '08:00:00',
        slotMaxTime:    '22:00:00',
        slotDuration:   '00:30:00',
        slotLabelFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
        eventTimeFormat: { hour: 'numeric', minute: '2-digit', hour12: true },
        allDaySlot:     false,
        nowIndicator:   true,
        height:         '100%',
        events:         buildCalendarEvents(appointments),
        eventContent:   renderEvent,
        eventClick:     onEventClick,
        dateClick:      onDateClick,
        eventMouseEnter: onEventHover,
        eventMouseLeave: hideQuickView,
        // ── Drag-drop ──────────────────────────────────────────
        editable:       true,   // enables drag AND resize
        eventDrop:      onEventDrop,
        eventResize:    onEventResize,
        // Compact card handled entirely by CSS container queries — no JS needed
        // Reload blocked slots whenever the calendar view changes date range
        datesSet: function(info) {
            const start = info.startStr.split('T')[0];
            const end   = info.endStr.split('T')[0];
            fetchAndRenderBlockedSlots(start, end);
        },
    });

    calendar.render();
    window.calendar = calendar; // expose globally so sidebar toggle can call updateSize()

    // Init date pickers and time slot selects after DOM is ready
    initFlatpickrs();
    const now = nearestSlot();
    buildTimeSlots('am-time', now);
    buildTimeSlots('wi-time', nearestSlot());
}

// Refresh blocked slots immediately when a slot is saved from the modal
window.addEventListener('df:slot-blocked', function() {
    if (!calendar) return;
    const view  = calendar.view;
    const start = view.activeStart.toISOString().split('T')[0];
    const end   = view.activeEnd.toISOString().split('T')[0];
    fetchAndRenderBlockedSlots(start, end);
});

function renderEvent(info) {
    const apt  = info.event.extendedProps;
    if (apt._isBlock) { return; }

    const prefs       = window.__APPT_DATA.calendarPrefs;
    const cardStyle   = prefs.cardStyle   || 'strip';
    const colorSource = prefs.colorSource || 'treatment';

    const treatColor  = apt.treatment_color || '#6a0f70';
    const doctorColor = apt.doctor_color    || '#94a3b8';

    // Primary color drives the background tint; accent drives the left border
    const primaryColor = colorSource === 'doctor' ? doctorColor : treatColor;
    const accentColor  = colorSource === 'doctor' ? treatColor  : doctorColor;

    const status      = apt.status;
    const isCancelled = status === 'cancelled';
    const isDone      = status === 'done';
    const isWalkin    = apt.type === 'walk-in';
    const mins        = apt.duration_minutes || 30;
    const docLast     = apt.doctor_name?.split(' ').slice(-1)[0] || '';

    // ── Compute background and border ──────────────────────────
    let bg, borderColor, textOpacity = '1', strikethrough = '';
    if (isCancelled) {
        bg = '#fee2e2'; borderColor = '#fca5a5'; textOpacity = '.55'; strikethrough = 'text-decoration:line-through;';
    } else if (isDone) {
        bg = '#f0fdf4'; borderColor = '#86efac';
    } else if (cardStyle === 'filled') {
        // Filled: solid tinted background + accent left border
        bg = primaryColor + '33'; // ~20% opacity — visible but readable
        borderColor = accentColor;
    } else {
        // Strip: white background + bold accent left border
        bg = '#ffffff';
        borderColor = accentColor;
    }

    // ── Treatment label color ───────────────────────────────────
    // Darken the primary color for text by mixing toward black (use filter via inline style below)
    const treatLabelColor = isCancelled ? '#b91c1c' : isDone ? '#15803d' : primaryColor;

    // ── Badges ─────────────────────────────────────────────────
    const walkinBadge = isWalkin
        ? `<span style="font-size:8.5px;font-weight:700;background:#f59e0b;color:#fff;border-radius:3px;padding:1px 4px;letter-spacing:.04em;line-height:1;flex-shrink:0;">WALK-IN</span>` : '';
    const doneBadge = isDone
        ? `<span style="font-size:8.5px;font-weight:700;background:#10b981;color:#fff;border-radius:3px;padding:1px 4px;letter-spacing:.04em;line-height:1;flex-shrink:0;">✓</span>` : '';
    const cancelledBadge = isCancelled
        ? `<span style="font-size:8.5px;font-weight:700;background:#ef4444;color:#fff;border-radius:3px;padding:1px 4px;letter-spacing:.04em;line-height:1;flex-shrink:0;">✕</span>` : '';

    const badge = walkinBadge || cancelledBadge || doneBadge;

    return { html: `
        <div class="ev-container">
            <div class="ev-inner" style="
                background:${bg};
                border-left:3px solid ${borderColor};
                border-radius:3px;
                padding:3px 5px;
                height:100%;
                overflow:hidden;
                line-height:1.35;
                opacity:${textOpacity};
                ${strikethrough}
            ">
                <div style="display:flex;align-items:center;gap:4px;overflow:hidden;">
                    <span class="ev-name" style="font-size:11px;font-weight:700;color:#1a0320;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;">${apt.patient_name}</span>
                    ${badge}
                </div>
                <div class="ev-treatment" style="font-size:10px;font-weight:600;color:${treatLabelColor};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;filter:${(!isCancelled && !isDone) ? 'brightness(.7)' : 'none'};">${apt.treatment_category || apt.type}</div>
                <div class="ev-meta" style="font-size:9.5px;color:#78716c;">${mins}min · ${docLast}</div>
            </div>
        </div>
    `};
}

// ─── Drag-Drop Toast ─────────────────────────────────────────
function showDragToast(msg, type = 'success') {
    let toast = document.getElementById('df-drag-toast');
    if (!toast) {
        toast = document.createElement('div');
        toast.id = 'df-drag-toast';
        toast.style.cssText = `
            position:fixed;bottom:24px;left:50%;transform:translateX(-50%);
            padding:10px 22px;border-radius:9px;font-size:13px;font-weight:600;
            z-index:9999;box-shadow:0 4px 20px rgba(0,0,0,.18);
            transition:opacity .3s;pointer-events:none;font-family:inherit;
        `;
        document.body.appendChild(toast);
    }
    toast.textContent   = msg;
    toast.style.opacity = '1';
    toast.style.background = type === 'success' ? '#10b981' : '#ef4444';
    toast.style.color      = '#fff';
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { toast.style.opacity = '0'; }, 2800);
}

// ─── Drag-drop handler ────────────────────────────────────────
async function onEventDrop(info) {
    const apt  = info.event.extendedProps;
    if (apt._isBlock) { info.revert(); return; }  // never drag blocked-slot backgrounds

    const newStart = info.event.start;
    const newDate  = newStart.toLocaleDateString('en-CA'); // YYYY-MM-DD
    const hh       = newStart.getHours().toString().padStart(2,'0');
    const mm       = newStart.getMinutes().toString().padStart(2,'0');
    const newTime  = `${hh}:${mm}`;

    try {
        const url = window.__APPT_DATA.routes.reschedule.replace('{id}', info.event.id);
        const r   = await fetch(url, {
            method:  'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Accept':       'application/json',
                'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken,
            },
            body: JSON.stringify({ appointment_date: newDate, appointment_time: newTime }),
        });
        const data = await r.json();
        if (data.ok) {
            // Sync in-memory record
            const local = window.__APPT_DATA.appointments.find(a => a.id == info.event.id);
            if (local) { local.appointment_date = newDate; local.appointment_time = newTime; }
            showDragToast(`✓ Moved to ${newDate} ${hh}:${mm}`);
            if (window._apptApp) { window._apptApp.refreshQueue(); window._apptApp.refreshCounts(); }
        } else {
            info.revert();
            showDragToast(data.message || 'Move failed', 'error');
        }
    } catch(e) {
        info.revert();
        showDragToast('Network error — move reverted', 'error');
    }
}

// ─── Resize handler (changes duration) ───────────────────────
async function onEventResize(info) {
    const apt = info.event.extendedProps;
    if (apt._isBlock) { info.revert(); return; }

    const start    = info.event.start;
    const end      = info.event.end;
    const diffMins = Math.round((end - start) / 60000);
    const hh       = start.getHours().toString().padStart(2,'0');
    const mm       = start.getMinutes().toString().padStart(2,'0');

    try {
        const url = window.__APPT_DATA.routes.reschedule.replace('{id}', info.event.id);
        const r   = await fetch(url, {
            method:  'PATCH',
            headers: { 'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':window.__APPT_DATA.csrfToken },
            body: JSON.stringify({
                appointment_date:  start.toLocaleDateString('en-CA'),
                appointment_time:  `${hh}:${mm}`,
                duration_minutes:  diffMins,
            }),
        });
        const data = await r.json();
        if (data.ok) {
            const local = window.__APPT_DATA.appointments.find(a => a.id == info.event.id);
            if (local) local.duration_minutes = diffMins;
            showDragToast(`✓ Duration set to ${diffMins} min`);
        } else {
            info.revert();
            showDragToast(data.message || 'Resize failed', 'error');
        }
    } catch(e) {
        info.revert();
        showDragToast('Network error — resize reverted', 'error');
    }
}

// ─── Quick View Card ──────────────────────────────────────────
let qvcHideTimeout;
let qvcCurrentApt = null;
const qvc = document.getElementById('quick-view-card');

function qvcEdit() {
    if (!qvcCurrentApt) return;
    hideQuickView();
    window.openEditAppointmentModal(qvcCurrentApt.id);
}

// ── Cancel with reason ───────────────────────────────────────────
let _cancelAptId = null;
let _cancelParty = null;

function _styleCancelPartyBtn(btn, active) {
    btn.style.borderColor = active ? '#6a0f70' : '#d1d5db';
    btn.style.background  = active ? '#f5eefb' : '#fff';
    btn.style.color       = active ? '#6a0f70' : '#374151';
}

function setCancelParty(party) {
    _cancelParty = party;
    _styleCancelPartyBtn(document.getElementById('crm-party-patient'), party === 'patient');
    _styleCancelPartyBtn(document.getElementById('crm-party-clinic'), party === 'clinic');
    document.getElementById('crm-error').style.display = 'none';
}

function qvcCancel() {
    if (!qvcCurrentApt) return;
    _cancelAptId = qvcCurrentApt.id;
    _cancelParty = null;
    document.getElementById('crm-patient-name').textContent = qvcCurrentApt.patient_name + ' — ' + qvcCurrentApt.appointment_time;
    document.getElementById('crm-reason').value = '';
    document.getElementById('crm-error').style.display = 'none';
    setCancelParty(null); // reset button styling
    hideQuickView();
    const modal = document.getElementById('cancel-reason-modal');
    modal.style.display = 'flex';
}

function closeCancelModal() {
    document.getElementById('cancel-reason-modal').style.display = 'none';
    _cancelAptId = null;
    _cancelParty = null;
}

async function submitCancel() {
    const reason = document.getElementById('crm-reason').value.trim();
    const errEl  = document.getElementById('crm-error');
    if (!_cancelParty) { errEl.textContent = 'Please select who cancelled — Patient or Clinic.'; errEl.style.display = 'block'; return; }
    if (!reason) { errEl.textContent = 'Please enter a reason.'; errEl.style.display = 'block'; return; }
    errEl.style.display = 'none';

    try {
        const url = window.__APPT_DATA.routes.cancelAppt.replace('{id}', _cancelAptId);
        const r = await fetch(url, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken },
            body: JSON.stringify({ cancel_reason: reason, cancelled_party: _cancelParty }),
        });
        const data = await r.json();
        if (data.ok) {
            closeCancelModal();
            // Update in-memory + calendar
            const apt = window.__APPT_DATA.appointments.find(a => a.id === _cancelAptId);
            if (apt) { apt.status = 'cancelled'; apt.cancel_reason = reason; apt.cancelled_party = data.appointment?.cancelled_party; }
            if (window.calendar) window.calendar.refetchEvents();
            if (window._apptApp) { window._apptApp.refreshQueue(); window._apptApp.refreshCounts(); }
        } else {
            errEl.textContent = data.message || 'Failed to cancel.';
            errEl.style.display = 'block';
        }
    } catch { errEl.textContent = 'Network error.'; errEl.style.display = 'block'; }
}

// ── Revert status ─────────────────────────────────────────────────
async function qvcRevert() {
    if (!qvcCurrentApt) return;
    const aptId = qvcCurrentApt.id;
    hideQuickView();
    try {
        const url = window.__APPT_DATA.routes.revertAppt.replace('{id}', aptId);
        const r = await fetch(url, {
            method: 'PATCH',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken },
        });
        const data = await r.json();
        if (data.ok) {
            const apt = window.__APPT_DATA.appointments.find(a => a.id === aptId);
            if (apt) { apt.status = data.appointment.status; apt.previous_status = null; }
            if (window.calendar) window.calendar.refetchEvents();
            if (window._apptApp) { window._apptApp.refreshQueue(); window._apptApp.refreshCounts(); }
        } else { alert(data.message || 'Cannot revert.'); }
    } catch { alert('Network error.'); }
}

function qvcReschedule() {
    if (!qvcCurrentApt) return;
    hideQuickView();
    // Open global modal pre-filled with this patient + date
    openAppointmentModal('appointment', qvcCurrentApt.appointment_date, qvcCurrentApt.patient_id);
}

// ── Hide cancelled appointment from calendar (keeps record) ──────
async function qvcHideFromCalendar() {
    if (!qvcCurrentApt) return;
    const aptId = qvcCurrentApt.id;
    hideQuickView();
    try {
        const url = window.__APPT_DATA.routes.hideAppt.replace('{id}', aptId);
        const r = await fetch(url, {
            method: 'PATCH',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken },
        });
        const data = await r.json();
        if (data.ok) {
            // Remove from in-memory list so calendar doesn't show it
            window.__APPT_DATA.appointments = window.__APPT_DATA.appointments.filter(a => a.id !== aptId);
            if (window.calendar) window.calendar.refetchEvents();
            if (window._apptApp) { window._apptApp.refreshQueue(); window._apptApp.refreshCounts(); }
        } else { alert('Failed to hide appointment.'); }
    } catch { alert('Network error.'); }
}

// ── Permanently delete a cancelled appointment ────────────────────
async function qvcDeleteAppt() {
    if (!qvcCurrentApt) return;
    const aptId   = qvcCurrentApt.id;
    const name    = qvcCurrentApt.patient_name;
    if (!confirm(`Permanently delete the cancelled appointment for ${name}?\n\nThis cannot be undone.`)) return;
    hideQuickView();
    try {
        const url = window.__APPT_DATA.routes.deleteAppt.replace('{id}', aptId);
        const r = await fetch(url, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken },
        });
        const data = await r.json();
        if (data.ok) {
            window.__APPT_DATA.appointments = window.__APPT_DATA.appointments.filter(a => a.id !== aptId);
            if (window.calendar) window.calendar.refetchEvents();
            if (window._apptApp) { window._apptApp.refreshQueue(); window._apptApp.refreshCounts(); }
        } else { alert('Failed to delete appointment.'); }
    } catch { alert('Network error.'); }
}

function showQuickView(apt, event) {
    clearTimeout(qvcHideTimeout);
    qvcCurrentApt = apt;
    if (event && event.stopPropagation) event.stopPropagation();

    document.getElementById('qvc-name').textContent      = apt.patient_name;
    document.getElementById('qvc-sub').textContent       = `${apt.appointment_time} · ${fmtDate(apt.appointment_date)}`;
    document.getElementById('qvc-doctor').textContent    = apt.doctor_name;
    document.getElementById('qvc-treatment').textContent = apt.treatment_category || apt.treatment || apt.type;
    document.getElementById('qvc-duration').textContent  = apt.duration_minutes + ' min';
    document.getElementById('qvc-status').textContent    = STATUS_META[apt.status]?.label || apt.status;
    document.getElementById('qvc-chair').textContent     = apt.chair_number ? 'Chair ' + apt.chair_number : '—';
    document.getElementById('qvc-phone').textContent     = apt.patient_phone || '—';

    // Age
    const ageRow = document.getElementById('qvc-age-row');
    if (apt.patient_age) {
        document.getElementById('qvc-age').textContent = apt.patient_age + ' yrs';
        ageRow.style.display = 'flex';
    } else {
        ageRow.style.display = 'none';
    }

    // Timestamps
    const showTs = (rowId, valId, val) => {
        const row = document.getElementById(rowId);
        if (val) { document.getElementById(valId).textContent = val; row.style.display = 'flex'; }
        else row.style.display = 'none';
    };
    showTs('qvc-checkin-row', 'qvc-checkin-time', apt.checked_in_at);
    showTs('qvc-inchair-row', 'qvc-inchair-time', apt.in_chair_at);
    showTs('qvc-done-row',    'qvc-done-time',    apt.completed_at);

    // Notes
    if (apt.notes) {
        document.getElementById('qvc-notes').textContent       = apt.notes;
        document.getElementById('qvc-notes-row').style.display = 'flex';
    } else {
        document.getElementById('qvc-notes-row').style.display = 'none';
    }

    // Clicking the header navigates to the patient profile
    const qvcHeader = document.getElementById('qvc-header');
    qvcHeader.onclick = () => { window.location.href = `/patients/${apt.patient_id}`; };

    // Cancel button — hide if already cancelled/done
    const cancelBtn = document.getElementById('qvc-cancel-btn');
    const revertBtn = document.getElementById('qvc-revert-btn');
    cancelBtn.style.display = ['cancelled','done','no_show'].includes(apt.status) ? 'none' : '';
    // Revert button — show only when previous_status exists
    revertBtn.style.display = apt.previous_status ? '' : 'none';

    // Cancelled-only actions: Hide from Calendar + Delete
    const cancelledActions = document.getElementById('qvc-cancelled-actions');
    cancelledActions.style.display = (apt.status === 'cancelled') ? 'flex' : 'none';

    // (edit button uses qvcEdit() which reads qvcCurrentApt.id)

    // Actions
    const actions = document.getElementById('qvc-actions');
    actions.innerHTML = '';
    const makeBtn = (label, color, bg, fn) => {
        const b = document.createElement('button');
        b.className = 'qvc-btn';
        b.style.cssText = `background:${bg};color:${color};`;
        b.textContent = label;
        b.onclick = fn;
        actions.appendChild(b);
    };

    if (apt.status === 'scheduled') makeBtn('✓ Check In','#92400e','#fef3c7',() => { hideQuickView(); window._apptApp.checkInWithOperatory(apt,'checkin'); });
    if (apt.status === 'checkin')   makeBtn('In Chair','#5b21b6','#ede9fe',() => { hideQuickView(); window._apptApp.checkInWithOperatory(apt,'in_chair'); });
    if (apt.status === 'in_chair')  makeBtn('✓ Done','#14532d','#dcfce7',() => { window._apptApp.updateStatus(apt.id,'done'); hideQuickView(); });
    if (apt.patient_phone) makeBtn('WA','#15803d','#dcfce7',() => window._apptApp.waContact(apt.patient_phone));

    // Position — make visible off-screen first to measure real height
    qvc.style.visibility = 'hidden';
    qvc.style.display    = 'block';
    const cardW = qvc.offsetWidth  || 290;
    const cardH = qvc.offsetHeight || 400;
    const margin = 10;

    let x, y;
    if (event instanceof MouseEvent) {
        x = event.clientX + 14;
        y = event.clientY - 20;
    } else if (event && event.target) {
        const rect = event.target.getBoundingClientRect();
        x = rect.right + 8;
        y = rect.top;
    } else {
        x = window.innerWidth  / 2 - cardW / 2;
        y = window.innerHeight / 2 - cardH / 2;
    }

    // Keep fully within viewport
    if (x + cardW + margin > window.innerWidth)  x = x - cardW - 16;
    if (x < margin) x = margin;
    if (y + cardH + margin > window.innerHeight) y = window.innerHeight - cardH - margin;
    if (y < margin) y = margin;

    qvc.style.left       = x + 'px';
    qvc.style.top        = y + 'px';
    qvc.style.visibility = 'visible';
}

function hideQuickView() {
    qvcHideTimeout = setTimeout(() => { qvc.style.display = 'none'; }, 120);
}

qvc.addEventListener('mouseenter', () => clearTimeout(qvcHideTimeout));
qvc.addEventListener('mouseleave', hideQuickView);
document.addEventListener('click', e => {
    if (!qvc.contains(e.target)) hideQuickView();
});

function onEventHover(info) {
    const apt = info.event.extendedProps;
    showQuickView(apt, info.jsEvent);
}

function onEventClick(info) {
    const apt = info.event.extendedProps;
    showQuickView(apt, info.jsEvent);
}

function onDateClick(info) {
    openCombinedModal('appointment', info.dateStr, info.date);
}


// ─── Walk-In: patient mobile search ──────────────────────────

// ─── Combined Modal (tabbed) ──────────────────────────────────
// ── Compatibility shim → forwards to global appointment modal ──────────────
// The global modal is injected by layouts/app.blade.php
function openCombinedModal(tab, dateStr, dateObj) {
    let time = null;
    if (dateObj instanceof Date) {
        const h = dateObj.getHours();
        const m = dateObj.getMinutes() < 30 ? '00' : '30';
        time = `${h.toString().padStart(2,'0')}:${m}`;
    }
    openAppointmentModal(tab || 'appointment', dateStr || null, null, time);
}

// ── Listen for successful booking → update calendar + queue ─────────────
window.addEventListener('df:appointment-booked', function(e) {
    const apt = e.detail?.appointment;
    if (apt && window._apptApp) {
        window._apptApp.addAppointmentToCalendar(apt);
        window._apptApp.refreshQueue();
    }
});

// ── Listen for appointment update → refresh calendar ─────────────────────
window.addEventListener('df:appointment-updated', function(e) {
    if (window.calendar) {
        // Refetch all events to reflect the update
        window.calendar.refetchEvents();
    }
    if (window._apptApp) {
        window._apptApp.refreshQueue();
    }
});





// ─── AlpineJS App ─────────────────────────────────────────────
function appointmentApp() {
    return {
        // State
        sidebarOpen:        true,
        clockTime:          '',
        clockDateShort:     '',
        fullDate:           '',
        filterDoctorId:     '',
        queueDoctorId:      '',
        searchQuery:        '',
        activeStatusFilter: '',
        queueList:          [],
        counts:             {},

        // Operatory list (pre-loaded from server)
        operatories: window.__APPT_DATA.operatories ?? [],

        // Operatory picker mini-modal state
        opPicker: {
            show:        false,
            aptId:       null,
            patientName: '',
            nextStatus:  'checkin',
            selectedId:  '',
            saving:      false,
        },

        counterDefs: [
            { key: 'total',     label: 'Total'     },
            { key: 'scheduled', label: 'Scheduled' },
            { key: 'checkin',   label: 'Checked In'},
            { key: 'in_chair',  label: 'In Chair'  },
            { key: 'done',      label: 'Done'      },
            { key: 'cancelled', label: 'Cancelled' },
            { key: 'no_show',   label: 'No Show'   },
            { key: 'walkin',    label: 'Walk-In'   },
        ],

        init() {
            window._apptApp = this;

            this.counts    = window.__APPT_DATA.statusCounts;
            this.queueList = this.sortQueue([...window.__APPT_DATA.todayQueue]);

            initCalendar(window.__APPT_DATA.appointments);

            // Clock tick — only update what we need
            this.tickClock();
            setInterval(() => this.tickClock(), 1000);

            // Auto-refresh queue every 60s
            setInterval(() => this.refreshQueue(), 60000);
        },

        tickClock() {
            const now = new Date();
            // Time only for topbar (compact)
            this.clockTime      = now.toLocaleTimeString('en-IN', { hour12: true, hour: '2-digit', minute: '2-digit' });
            // Short date for under topbar clock
            this.clockDateShort = now.toLocaleDateString('en-IN', { weekday: 'short', day: 'numeric', month: 'short' });
            // Full date for sidebar header
            this.fullDate       = now.toLocaleDateString('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        },


        sortQueue(list) {
            const order = { checkin: 0, in_chair: 1, scheduled: 2, done: 3, cancelled: 4, no_show: 4 };
            return list.sort((a, b) => {
                const oa = order[a.status] ?? 5;
                const ob = order[b.status] ?? 5;
                if (oa !== ob) return oa - ob;
                return a.appointment_time.localeCompare(b.appointment_time);
            });
        },

        async refreshQueue() {
            try {
                const params = new URLSearchParams({ date: window.__APPT_DATA.today });
                if (this.queueDoctorId) params.append('doctor_id', this.queueDoctorId);
                if (this.activeStatusFilter && this.activeStatusFilter !== 'total') {
                    params.append('status', this.activeStatusFilter);
                }
                const r = await fetch(`${window.__APPT_DATA.routes.todayQueue}?${params}`, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken }
                });
                if (!r.ok) return;
                const data = await r.json();
                this.queueList = this.sortQueue(data.appointments ?? data);
            } catch(e) { console.error('Queue refresh error:', e); }
        },

        async refreshCounts() {
            try {
                const r = await fetch(window.__APPT_DATA.routes.statusCounts, {
                    headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken }
                });
                if (!r.ok) return;
                const data = await r.json();
                this.counts = data;
            } catch(e) {}
        },

        applyFilters() {
            let evts = buildCalendarEvents(window.__APPT_DATA.appointments);

            if (this.filterDoctorId) {
                evts = evts.filter(e => String(e.extendedProps.doctor_id) === String(this.filterDoctorId));
            }

            if (this.searchQuery.trim()) {
                const q = this.searchQuery.toLowerCase();
                evts = evts.filter(e => {
                    const ap = e.extendedProps;
                    return (ap.patient_name || '').toLowerCase().includes(q)
                        || (ap.patient_phone || '').includes(q)
                        || (ap.treatment_category || ap.type || '').toLowerCase().includes(q)
                        || (ap.doctor_name || '').toLowerCase().includes(q);
                });
            }

            calendar.removeAllEvents();
            calendar.addEventSource(evts);

            // Re-render blocked slots
            if (blockedSlotSource) {
                const v = calendar.view;
                fetchAndRenderBlockedSlots(
                    v.activeStart.toISOString().split('T')[0],
                    v.activeEnd.toISOString().split('T')[0]
                );
            }
        },

        toggleStatusFilter(key) {
            this.activeStatusFilter = (this.activeStatusFilter === key) ? '' : key;
            this.refreshQueue();
        },

        async updateStatus(aptId, newStatus) {
            try {
                const url = window.__APPT_DATA.routes.statusUpdate.replace('{id}', aptId);
                const r = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken
                    },
                    body: JSON.stringify({ status: newStatus })
                });
                if (!r.ok) throw new Error('Status update failed');
                const data = await r.json();

                // Update in-memory appointments list
                const apt = window.__APPT_DATA.appointments.find(a => a.id === aptId);
                if (apt) apt.status = newStatus;

                // Update calendar event class
                const calEvt = calendar.getEventById(aptId);
                if (calEvt) {
                    calEvt.setProp('classNames', [`status-${newStatus}`]);
                }

                // Refresh queue and counts
                this.refreshQueue();
                this.refreshCounts();
            } catch(e) {
                console.error('Status update error:', e);
                alert('Failed to update status. Please try again.');
            }
        },

        confirmCancel(aptId) {
            if (confirm('Cancel this appointment?')) {
                this.updateStatus(aptId, 'cancelled');
            }
        },

        waContact(phone) {
            if (!phone) return;
            const clean = phone.replace(/\D/g, '');
            const num   = clean.startsWith('91') ? clean : '91' + clean;
            window.open(`https://wa.me/${num}`, '_blank');
        },

        openOperatoryPicker(apt) {
            this.opPicker.aptId       = apt.id;
            this.opPicker.patientName = apt.patient_name;
            this.opPicker.nextStatus  = apt.status === 'scheduled' ? 'checkin' : 'in_chair';
            this.opPicker.selectedId  = apt.operatory_id ?? '';
            this.opPicker.saving      = false;
            this.opPicker.show        = true;
        },

        checkInWithOperatory(apt, nextStatus) {
            if (this.operatories.length === 0) {
                // No operatories configured — update status directly
                this.updateStatus(apt.id, nextStatus);
                return;
            }
            this.opPicker.aptId       = apt.id;
            this.opPicker.patientName = apt.patient_name;
            this.opPicker.nextStatus  = nextStatus;
            this.opPicker.selectedId  = apt.operatory_id ?? '';
            this.opPicker.saving      = false;
            this.opPicker.show        = true;
        },

        async confirmCheckIn() {
            this.opPicker.saving = true;
            try {
                // 1. Assign operatory if selected
                if (this.opPicker.selectedId) {
                    const opUrl = window.__APPT_DATA.routes.assignOperatory.replace('{id}', this.opPicker.aptId);
                    await fetch(opUrl, {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken
                        },
                        body: JSON.stringify({ operatory_id: this.opPicker.selectedId })
                    });

                    // Update local apt object with operatory info
                    const op = this.operatories.find(o => String(o.id) === String(this.opPicker.selectedId));
                    const apt = window.__APPT_DATA.appointments.find(a => a.id === this.opPicker.aptId);
                    if (apt && op) {
                        apt.operatory_id   = op.id;
                        apt.operatory_name = op.name;
                    }
                }

                // 2. Update status
                await this.updateStatus(this.opPicker.aptId, this.opPicker.nextStatus);
            } catch(e) {
                console.error('Check-in error:', e);
                alert('Check-in failed. Please try again.');
            } finally {
                this.opPicker.saving = false;
                this.opPicker.show   = false;
            }
        },

        async skipOperatoryAndCheckIn() {
            this.opPicker.saving = true;
            try {
                await this.updateStatus(this.opPicker.aptId, this.opPicker.nextStatus);
            } finally {
                this.opPicker.saving = false;
                this.opPicker.show   = false;
            }
        },

        addAppointmentToCalendar(apt) {
            // Add to in-memory list
            window.__APPT_DATA.appointments.push(apt);

            // Add to calendar
            const events = buildCalendarEvents([apt]);
            if (events.length) calendar.addEventSource(events);

            // Refresh sidebar
            this.refreshQueue();
            this.refreshCounts();
        },

        // ── Status helpers ──────────────────────────────────────

        // ── Status helpers ──────────────────────────────────────
        getStatusColor(status) {
            return (STATUS_META[status] || STATUS_META.scheduled).color;
        },

        getStatusBg(status) {
            return (STATUS_META[status] || STATUS_META.scheduled).bg;
        },

        getStatusLabel(status) {
            return (STATUS_META[status] || STATUS_META.scheduled).label;
        },

        getDoctorColor(docId) {
            return getDoctorColor(docId);
        },

        getTreatmentFill(catName) {
            return getTreatmentFill(catName);
        },
    };
}
</script>
@endpush

@endsection
