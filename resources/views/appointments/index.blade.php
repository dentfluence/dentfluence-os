{{-- resources/views/appointments/index.blade.php --}}
@extends('layouts.app')

@push('styles')
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css' rel='stylesheet' />
<link href='https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css' rel='stylesheet' />
<style>
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

/* ─── Queue + Reminders side-by-side layout ─────────────────── */
.sb-lower {
    flex: 1;
    display: flex;
    flex-direction: row;
    overflow: hidden;
    min-height: 0;
}

/* Queue column: takes remaining width minus reminders */
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

/* Reminders column: fixed narrow width */
.sb-reminders-col {
    width: 106px;
    flex-shrink: 0;
    border-left: 1px solid #f1f5f9;
    background: #fafbfc;
    display: flex;
    flex-direction: column;
    padding: 8px 8px 8px 7px;
    overflow: hidden;
}

.sb-reminders-title {
    font-size: 9.5px;
    font-weight: 700;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 6px;
    white-space: nowrap;
}

.reminder-list {
    flex: 1;
    overflow-y: auto;
    margin-bottom: 6px;
}

.reminder-list::-webkit-scrollbar { width: 2px; }
.reminder-list::-webkit-scrollbar-thumb { background: #cbd5e1; }

.reminder-item {
    display: flex;
    align-items: flex-start;
    gap: 4px;
    margin-bottom: 5px;
}

.reminder-item input[type=checkbox] {
    accent-color: #3b82f6;
    cursor: pointer;
    margin-top: 2px;
    flex-shrink: 0;
    width: 11px;
    height: 11px;
}

.reminder-item label {
    font-size: 10px;
    color: #475569;
    cursor: pointer;
    flex: 1;
    line-height: 1.3;
    word-break: break-word;
}

.reminder-item.done label {
    text-decoration: line-through;
    color: #94a3b8;
}

.reminder-del {
    border: none;
    background: none;
    color: #cbd5e1;
    cursor: pointer;
    font-size: 10px;
    line-height: 1;
    padding: 0;
    flex-shrink: 0;
}

.reminder-del:hover { color: #ef4444; }

.reminder-add-area {
    display: flex;
    flex-direction: column;
    gap: 4px;
    border-top: 1px solid #f1f5f9;
    padding-top: 6px;
}

.reminder-add-input {
    width: 100%;
    font-size: 10px;
    padding: 4px 6px;
    border: 1px solid #e2e8f0;
    border-radius: 5px;
    outline: none;
    background: #fff;
    color: #1e293b;
    box-sizing: border-box;
}

.reminder-add-input:focus { border-color: #3b82f6; }

.reminder-add-btn {
    font-size: 10px;
    font-weight: 700;
    padding: 3px 6px;
    background: #3b82f6;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    width: 100%;
}

.reminder-add-btn:hover { background: #2563eb; }

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

.fc-event {
    border-radius: 6px !important;
    border-width: 0 0 0 3px !important;
    border-left-style: solid !important;
    cursor: pointer !important;
    font-size: 11px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,.08) !important;
    padding: 2px 5px !important;
}

.fc-event-title { font-weight: 600 !important; }
.fc-event-time  { font-size: 10px !important; opacity: .8; }

.fc-event.status-done      { opacity: .55; }
.fc-event.status-cancelled { opacity: .35; text-decoration: line-through; }
.fc-event.status-no_show   { opacity: .35; border-style: dashed !important; }
.fc-event.status-in_chair  { box-shadow: 0 0 0 2px rgba(139,92,246,.25), 0 1px 4px rgba(0,0,0,.1) !important; }

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
}

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
    font-family: 'DM Sans', sans-serif !important;
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
        checkConflict:"{{ route('appointments.check.conflict') }}",
        quickView:    "{{ url('/appointments') }}/{{'{id}'}}/quick",
    }
};
</script>

{{-- ════════════════════════════════════════════════════════════
     SHELL
══════════════════════════════════════════════════════════════ --}}
<div class="appt-shell" x-data="appointmentApp()" x-init="init()">

    {{-- ── STICKY TOP BAR ──────────────────────────────────── --}}
    <div class="appt-topbar">

        {{-- Sidebar toggle --}}
        <button class="sidebar-toggle-btn" @click="sidebarOpen = !sidebarOpen" title="Toggle Sidebar">☰</button>

        {{-- Title --}}
        <span style="font-size:13px;font-weight:800;color:#1e293b;white-space:nowrap;">📅 Appointments</span>

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

        {{-- Walk-In button (subtle, beside Add Appointment) --}}
        <button class="btn-walkin" onclick="openCombinedModal('walkin')">
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

            {{-- ── LOWER: Queue + Reminders side-by-side ────── --}}
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
                                 @click="showQuickView(apt, $event)">

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
                                    </div>
                                </div>

                                {{-- Inline action buttons --}}
                                <div class="q-card-actions" @click.stop="">
                                    <template x-if="apt.status === 'scheduled'">
                                        <button class="q-action-btn q-btn-checkin"
                                                @click.stop="updateStatus(apt.id, 'checkin')">✓ In</button>
                                    </template>
                                    <template x-if="apt.status === 'checkin'">
                                        <button class="q-action-btn q-btn-inchair"
                                                @click.stop="updateStatus(apt.id, 'in_chair')">🦷 Chair</button>
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
                                        👤
                                    </a>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                {{-- Reminders column --}}
                <div class="sb-reminders-col">
                    <div class="sb-reminders-title">🗒 Notes</div>

                    <div class="reminder-list">
                        <template x-for="(r, i) in reminders" :key="i">
                            <div class="reminder-item" :class="{ done: r.done }">
                                <input type="checkbox" :id="`rem-${i}`" x-model="r.done" @change="saveReminders()">
                                <label :for="`rem-${i}`" x-text="r.text"></label>
                                <button class="reminder-del" @click="reminders.splice(i,1); saveReminders()">✕</button>
                            </div>
                        </template>
                    </div>

                    <div class="reminder-add-area">
                        <input class="reminder-add-input"
                               type="text"
                               placeholder="Add note…"
                               x-model="newReminder"
                               @keydown.enter="addReminder()">
                        <button class="reminder-add-btn" @click="addReminder()">+ Add</button>
                    </div>
                </div>

            </div>{{-- /sb-lower --}}

        </div>{{-- /sidebar --}}

    </div>{{-- /body --}}

</div>{{-- /shell --}}


{{-- ════════════════════════════════════════════════════════════
     QUICK VIEW FLOATING CARD
══════════════════════════════════════════════════════════════ --}}
<div id="quick-view-card">
    <div class="qvc-header" id="qvc-header">
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
    <div style="padding:0 12px 10px;display:flex;gap:6px;border-top:1px solid #f8fafc;padding-top:8px;">
        <a id="qvc-open-patient" href="#" target="_blank"
           style="flex:1;text-align:center;font-size:10px;font-weight:700;padding:5px 8px;border-radius:6px;background:#f1f5f9;color:#475569;text-decoration:none;transition:background .12s;"
           onmouseover="this.style.background='#e2e8f0'" onmouseout="this.style.background='#f1f5f9'">
            👤 Open Patient
        </a>
        <button id="qvc-reschedule-btn" onclick="qvcReschedule()"
                style="flex:1;font-size:10px;font-weight:700;padding:5px 8px;border-radius:6px;background:#eff6ff;color:#1d4ed8;border:none;cursor:pointer;transition:background .12s;"
                onmouseover="this.style.background='#dbeafe'" onmouseout="this.style.background='#eff6ff'">
            📅 Reschedule
        </button>
    </div>
</div>


{{-- ════════════════════════════════════════════════════════════
     COMBINED APPOINTMENT + WALK-IN MODAL (tabbed)
══════════════════════════════════════════════════════════════ --}}
<div id="combined-modal-backdrop" class="modal-backdrop-custom" onclick="closeCombinedModal()"></div>
<div id="combined-modal" class="modal-custom">

    {{-- Header --}}
    <div class="modal-custom-header">
        <div class="modal-custom-title" id="combined-modal-title">New Appointment</div>
        <button onclick="closeCombinedModal()"
                style="border:none;background:none;font-size:18px;color:#94a3b8;cursor:pointer;">✕</button>
    </div>

    {{-- Tabs --}}
    <div class="modal-tabs">
        <button class="modal-tab-btn active" id="tab-btn-appointment" onclick="switchModalTab('appointment')">
            📅 Appointment
        </button>
        <button class="modal-tab-btn" id="tab-btn-walkin" onclick="switchModalTab('walkin')">
            🚶 Walk-In
        </button>
    </div>

    {{-- ── TAB: Appointment ─────────────────────────────────── --}}
    <div id="tab-appointment" class="modal-tab-panel active">
        <div class="modal-custom-body">
            <div style="margin-bottom:10px;">
                <label class="form-label-sm">Patient *</label>
                <select class="form-control-sm" id="am-patient">
                    <option value="">— Search Patient —</option>
                    @foreach(\App\Models\Patient::where('branch_id', Auth::user()->branch_id)->orderBy('name')->get(['id','name','phone']) as $p)
                    <option value="{{ $p->id }}">{{ $p->name }} ({{ $p->phone }})</option>
                    @endforeach
                </select>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div>
                    <label class="form-label-sm">Doctor *</label>
                    <select class="form-control-sm" id="am-doctor">
                        @foreach($doctors as $doc)
                        <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label-sm">Type *</label>
                    <select class="form-control-sm" id="am-type">
                        <option value="consultation">Consultation</option>
                        <option value="treatment">Treatment</option>
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div>
                    <label class="form-label-sm">Date *</label>
                    <div class="date-input-wrap">
                        <input class="form-control-sm" id="am-date" type="text"
                               placeholder="Select date…" autocomplete="off" readonly style="cursor:pointer;">
                        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>
                        </svg>
                    </div>
                </div>
                <div>
                    <label class="form-label-sm">Time *</label>
                    <select class="time-slot-select" id="am-time"></select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div>
                    <label class="form-label-sm">Treatment Category</label>
                    <select class="form-control-sm" id="am-category" onchange="amAutoFill()">
                        <option value="">— Select —</option>
                        @foreach($treatmentCategories as $cat)
                        <option value="{{ $cat->id }}" data-duration="{{ $cat->default_duration ?? '' }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label-sm">Duration (min)</label>
                    <input class="form-control-sm" id="am-duration" type="number" value="30" min="10" max="240">
                </div>
            </div>
            <div style="margin-bottom:6px;">
                <label class="form-label-sm">Notes</label>
                <textarea class="form-control-sm" id="am-notes" rows="2" placeholder="Notes / chief complaint"></textarea>
            </div>
            <div id="am-conflict-warn" class="conflict-warning">
                <span>⚠️</span>
                <span id="am-conflict-text">Potential scheduling conflict detected.</span>
            </div>
        </div>
        <div class="modal-custom-footer">
            <button class="btn-secondary-sm" onclick="closeCombinedModal()">Cancel</button>
            <button class="btn-primary-sm" onclick="submitApptModal()" id="am-submit-btn">
                Book Appointment
            </button>
        </div>
    </div>

    {{-- ── TAB: Walk-In ─────────────────────────────────────── --}}
    <div id="tab-walkin" class="modal-tab-panel">
        <div class="modal-custom-body">
            {{-- Mobile-first search: finds existing patient or creates new --}}
            <div style="margin-bottom:10px;">
                <label class="form-label-sm">Mobile Number *</label>
                <div style="display:flex;gap:6px;">
                    <input class="form-control-sm" id="wi-mobile" type="tel" placeholder="9876543210"
                           oninput="wiSearchPatient(this.value)" style="flex:1;">
                    <div id="wi-search-status"
                         style="display:flex;align-items:center;font-size:11px;font-weight:600;color:#94a3b8;white-space:nowrap;min-width:60px;padding-right:4px;"></div>
                </div>
                <div id="wi-existing-notice"
                     style="display:none;margin-top:5px;padding:7px 10px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;font-size:11.5px;color:#15803d;font-weight:600;">
                    ✓ Existing patient found — booking as walk-in
                </div>
            </div>
            <input type="hidden" id="wi-existing-patient-id" value="">

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;" id="wi-name-section">
                <div>
                    <label class="form-label-sm">First Name *</label>
                    <input class="form-control-sm" id="wi-first" type="text" placeholder="Rahul">
                </div>
                <div>
                    <label class="form-label-sm">Last Name *</label>
                    <input class="form-control-sm" id="wi-last" type="text" placeholder="Sharma">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px;">
                <div>
                    <label class="form-label-sm">Doctor</label>
                    <select class="form-control-sm" id="wi-doctor">
                        @foreach($doctors as $doc)
                        <option value="{{ $doc->id }}">{{ $doc->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label-sm">Time</label>
                    <select class="time-slot-select" id="wi-time"></select>
                </div>
            </div>
            <div style="margin-bottom:10px;">
                <label class="form-label-sm">Treatment Category</label>
                <select class="form-control-sm" id="wi-category">
                    <option value="">— Select —</option>
                    @foreach($treatmentCategories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div style="margin-bottom:6px;">
                <label class="form-label-sm">Notes</label>
                <input class="form-control-sm" id="wi-notes" type="text" placeholder="Chief complaint or note">
            </div>
            <div id="wi-success">✓ Walk-in added successfully!</div>
        </div>
        <div class="modal-custom-footer">
            <button class="btn-secondary-sm" onclick="closeCombinedModal()">Cancel</button>
            <button class="btn-primary-sm" onclick="submitWalkin()" id="wi-submit-btn">
                Add Walk-In
            </button>
        </div>
    </div>

</div>{{-- /combined-modal --}}


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

function initFlatpickrs() {
    amDatePicker = flatpickr('#am-date', {
        dateFormat: 'd M Y',        // display: "26 May 2026"
        altInput:   false,
        // No minDate — backdated appointments allowed for rescheduling and data entry
        disableMobile: true,
        onChange(selectedDates, dateStr, instance) {
            // Store ISO value as data attribute for submitApptModal to read
            if (selectedDates[0]) {
                instance.element.dataset.isoDate =
                    selectedDates[0].toISOString().split('T')[0];
            }
        },
    });
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

function initCalendar(appointments) {
    const el = document.getElementById('dentfluence-calendar');

    calendar = new FullCalendar.Calendar(el, {
        initialView:  'timeGridWeek',
        headerToolbar: {
            left:   'prev,next today',
            center: 'title',
            right:  'dayGridMonth,timeGridWeek,timeGridDay',
        },
        slotMinTime:    '08:00:00',
        slotMaxTime:    '22:00:00',
        slotDuration:   '00:30:00',
        allDaySlot:     false,
        nowIndicator:   true,
        height:         '100%',
        events:         buildCalendarEvents(appointments),
        eventContent:   renderEvent,
        eventClick:     onEventClick,
        dateClick:      onDateClick,
        eventMouseEnter: onEventHover,
        eventMouseLeave: hideQuickView,
    });

    calendar.render();

    // Init date pickers and time slot selects after DOM is ready
    initFlatpickrs();
    const now = nearestSlot();
    buildTimeSlots('am-time', now);
    buildTimeSlots('wi-time', nearestSlot());
}

function renderEvent(info) {
    const apt    = info.event.extendedProps;
    const status = apt.status;
    const sm     = STATUS_META[status] || STATUS_META.scheduled;
    const mins   = apt.duration_minutes || 30;

    let statusStripe = '';
    if (status === 'checkin') {
        statusStripe = `<div style="height:3px;background:${sm.bg};border-radius:2px 2px 0 0;margin:-2px -5px 3px;"></div>`;
    }

    return { html: `
        <div style="padding:1px 0; line-height:1.25; overflow:hidden; height:100%;">
            ${statusStripe}
            <div style="font-size:11px;font-weight:700;color:#1e293b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                ${apt.patient_name}
            </div>
            <div style="font-size:10px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                ${apt.treatment_category || apt.type}
            </div>
            <div style="font-size:9.5px;color:#94a3b8;">${mins}min · ${apt.doctor_name?.split(' ').slice(-1)[0] || ''}</div>
        </div>
    `};
}

// ─── Quick View Card ──────────────────────────────────────────
let qvcHideTimeout;
let qvcCurrentApt = null;
const qvc = document.getElementById('quick-view-card');

function qvcReschedule() {
    if (!qvcCurrentApt) return;
    // Pre-fill the appointment modal with this appointment's data for rescheduling
    hideQuickView();
    openCombinedModal('appointment', qvcCurrentApt.appointment_date);
    // Pre-select patient and doctor after modal opens
    setTimeout(() => {
        const patSel = document.getElementById('am-patient');
        if (patSel) patSel.value = qvcCurrentApt.patient_id;
        const docSel = document.getElementById('am-doctor');
        if (docSel) docSel.value = qvcCurrentApt.doctor_id;
        const catSel = document.getElementById('am-category');
        if (catSel && qvcCurrentApt.treatment_category_id) catSel.value = qvcCurrentApt.treatment_category_id;
    }, 50);
}

function showQuickView(apt, event) {
    clearTimeout(qvcHideTimeout);
    qvcCurrentApt = apt;

    document.getElementById('qvc-name').textContent      = apt.patient_name;
    document.getElementById('qvc-sub').textContent       = `${apt.appointment_time} · ${apt.appointment_date}`;
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

    // Open Patient link
    document.getElementById('qvc-open-patient').href = `/patients/${apt.patient_id}`;

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

    if (apt.status === 'scheduled') makeBtn('✓ Check In','#92400e','#fef3c7',() => { window._apptApp.updateStatus(apt.id,'checkin'); hideQuickView(); });
    if (apt.status === 'checkin')   makeBtn('🦷 In Chair','#5b21b6','#ede9fe',() => { window._apptApp.updateStatus(apt.id,'in_chair'); hideQuickView(); });
    if (apt.status === 'in_chair')  makeBtn('✓ Done','#14532d','#dcfce7',() => { window._apptApp.updateStatus(apt.id,'done'); hideQuickView(); });
    if (apt.patient_phone) makeBtn('WA','#15803d','#dcfce7',() => window._apptApp.waContact(apt.patient_phone));

    // Position
    qvc.style.display = 'block';
    let x, y;
    if (event instanceof MouseEvent) {
        x = event.clientX + 12;
        y = event.clientY - 20;
    } else if (event && event.target) {
        const rect = event.target.getBoundingClientRect();
        x = rect.right + 8;
        y = rect.top;
    } else {
        x = window.innerWidth / 2 - 140;
        y = window.innerHeight / 2 - 150;
    }

    if (x + 290 > window.innerWidth)  x = x - 300;
    if (y + 350 > window.innerHeight) y = window.innerHeight - 360;
    if (x < 8) x = 8;
    if (y < 8) y = 8;

    qvc.style.left = x + 'px';
    qvc.style.top  = y + 'px';
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
let wiSearchTimer = null;

function wiSearchPatient(mobile) {
    clearTimeout(wiSearchTimer);
    const status  = document.getElementById('wi-search-status');
    const notice  = document.getElementById('wi-existing-notice');
    const nameSection = document.getElementById('wi-name-section');
    const hiddenId = document.getElementById('wi-existing-patient-id');

    if (mobile.replace(/\D/g,'').length < 8) {
        status.textContent = '';
        notice.style.display = 'none';
        nameSection.style.display = 'grid';
        hiddenId.value = '';
        return;
    }

    status.textContent = '…';
    wiSearchTimer = setTimeout(async () => {
        try {
            const res  = await fetch(`/patients/search?q=${encodeURIComponent(mobile)}`, {
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken }
            });
            const data = await res.json();
            const patients = Array.isArray(data) ? data : (data.patients || data.data || []);

            if (patients.length > 0) {
                const p = patients[0];
                hiddenId.value = p.id;
                // Auto-fill name fields (readonly when found)
                const nameParts = (p.name || '').split(' ');
                document.getElementById('wi-first').value = nameParts[0] || '';
                document.getElementById('wi-last').value  = nameParts.slice(1).join(' ') || '';
                document.getElementById('wi-first').readOnly = true;
                document.getElementById('wi-last').readOnly  = true;
                notice.style.display = 'block';
                status.textContent = '✓ Found';
                status.style.color = '#15803d';
            } else {
                hiddenId.value = '';
                document.getElementById('wi-first').readOnly = false;
                document.getElementById('wi-last').readOnly  = false;
                notice.style.display = 'none';
                status.textContent = 'New';
                status.style.color = '#94a3b8';
            }
        } catch (e) {
            status.textContent = '';
        }
    }, 400);
}

// ─── Combined Modal (tabbed) ──────────────────────────────────
let activeModalTab = 'appointment';

function openCombinedModal(tab = 'appointment', dateStr = null, dateObj = null) {
    switchModalTab(tab);

    if (tab === 'appointment') {
        // Determine ISO date string
        const isoDate = dateStr
            ? dateStr.split('T')[0]
            : new Date().toISOString().split('T')[0];

        // Set flatpickr to the date
        if (amDatePicker) {
            amDatePicker.setDate(isoDate, true, 'Y-m-d');
            amDatePicker.element.dataset.isoDate = isoDate;
        }

        // Set time slot to the clicked time or nearest slot
        const timeVal = dateObj
            ? (() => {
                const h = dateObj.getHours();
                const m = dateObj.getMinutes() < 30 ? '00' : '30';
                return `${h.toString().padStart(2,'0')}:${m}`;
            })()
            : nearestSlot();

        const amSel = document.getElementById('am-time');
        if (amSel) amSel.value = timeVal;

        document.getElementById('am-conflict-warn').style.display = 'none';
    }

    document.getElementById('combined-modal-backdrop').style.display = 'block';
    document.getElementById('combined-modal').style.display = 'block';
}

function closeCombinedModal() {
    document.getElementById('combined-modal-backdrop').style.display = 'none';
    document.getElementById('combined-modal').style.display = 'none';
}

function switchModalTab(tab) {
    activeModalTab = tab;
    ['appointment', 'walkin'].forEach(t => {
        document.getElementById(`tab-${t}`).classList.toggle('active', t === tab);
        document.getElementById(`tab-btn-${t}`).classList.toggle('active', t === tab);
    });
}

async function amAutoFill() {
    const sel = document.getElementById('am-category');
    const opt = sel.options[sel.selectedIndex];
    const catName = opt.text;
    const dur = autoDuration(catName);
    document.getElementById('am-duration').value = dur;

    const doctorId = document.getElementById('am-doctor').value;
    const date     = document.getElementById('am-date').value;
    const time     = document.getElementById('am-time').value;
    if (doctorId && date && time) checkConflict(doctorId, date, time, dur);
}

async function checkConflict(doctorId, date, time, duration) {
    try {
        const url = new URL(window.__APPT_DATA.routes.checkConflict);
        url.searchParams.set('doctor_id', doctorId);
        url.searchParams.set('appointment_date', date);
        url.searchParams.set('appointment_time', time);
        url.searchParams.set('duration_minutes', duration);

        const res  = await fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken } });
        const data = await res.json();

        const warn = document.getElementById('am-conflict-warn');
        if (data.has_conflict) {
            const c = data.conflicts[0];
            document.getElementById('am-conflict-text').textContent =
                `Conflict: ${c.patient_name} at ${c.time} (${c.duration} min)`;
            warn.style.display = 'flex';
        } else {
            warn.style.display = 'none';
        }
    } catch {}
}

async function submitApptModal() {
    const patient = document.getElementById('am-patient').value;
    const doctor  = document.getElementById('am-doctor').value;
    // Read ISO date from flatpickr data attribute; fall back to raw value if somehow set directly
    const dateEl  = document.getElementById('am-date');
    const date    = dateEl.dataset.isoDate || dateEl.value;
    const time    = document.getElementById('am-time').value;
    const type    = document.getElementById('am-type').value;

    if (!patient || !doctor || !date || !time) {
        alert('Please fill in all required fields.');
        return;
    }

    const btn = document.getElementById('am-submit-btn');
    btn.textContent = 'Booking…';
    btn.disabled    = true;

    try {
        const res = await fetch(window.__APPT_DATA.routes.store, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken,
                'Accept':       'application/json',
            },
            body: JSON.stringify({
                patient_id:            patient,
                doctor_id:             doctor,
                appointment_date:      date,
                appointment_time:      time,
                type,
                duration_minutes:      parseInt(document.getElementById('am-duration').value) || 30,
                treatment_category_id: document.getElementById('am-category').value || null,
                notes:                 document.getElementById('am-notes').value || ' ',
            }),
        });

        const data = await res.json();
        if (data.ok || data.success) {
            closeCombinedModal();
            if (data.appointment) {
                window._apptApp.addAppointmentToCalendar(data.appointment);
            }
            window._apptApp.refreshQueue();
        } else {
            alert(data.message || 'Failed to book appointment.');
        }
    } catch (e) {
        alert('Network error.');
    } finally {
        btn.textContent = 'Book Appointment';
        btn.disabled    = false;
    }
}

async function submitWalkin() {
    const mobile  = document.getElementById('wi-mobile').value.trim();
    const time    = document.getElementById('wi-time').value;
    const existingId = document.getElementById('wi-existing-patient-id').value;

    // Validation: mobile always required
    if (!mobile) {
        alert('Please enter the patient mobile number.');
        return;
    }

    // If no existing patient found, require name fields
    if (!existingId) {
        const first = document.getElementById('wi-first').value.trim();
        const last  = document.getElementById('wi-last').value.trim();
        if (!first || !last) {
            alert('Please fill in First Name and Last Name.');
            return;
        }
    }

    const btn = document.getElementById('wi-submit-btn');
    btn.textContent = 'Adding…';
    btn.disabled = true;

    try {
        const today = new Date().toISOString().split('T')[0];
        const first = document.getElementById('wi-first').value.trim();
        const last  = document.getElementById('wi-last').value.trim();

        const payload = {
            doctor_id:             document.getElementById('wi-doctor').value,
            appointment_date:      today,
            appointment_time:      time,
            treatment_category_id: document.getElementById('wi-category').value || null,
            notes:                 document.getElementById('wi-notes').value || '',
            is_walkin:             true,
        };

        if (existingId) {
            // Existing patient path — send patient_id directly
            payload.patient_id = existingId;
            payload.type       = 'consultation';
        } else {
            // New patient path — send name fields
            payload.first_name = first;
            payload.last_name  = last;
            payload.mobile     = mobile;
        }

        const res = await fetch(window.__APPT_DATA.routes.store, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify(payload),
        });

        const data = await res.json();
        if (data.ok || data.success) {
            document.getElementById('wi-success').style.display = 'block';
            if (data.appointment) {
                window._apptApp.addAppointmentToCalendar(data.appointment);
            }
            setTimeout(() => {
                closeCombinedModal();
                window._apptApp.refreshQueue();
                // Reset form
                ['wi-mobile','wi-notes'].forEach(id => document.getElementById(id).value = '');
                ['wi-first','wi-last'].forEach(id => {
                    document.getElementById(id).value = '';
                    document.getElementById(id).readOnly = false;
                });
                document.getElementById('wi-category').value = '';
                document.getElementById('wi-existing-patient-id').value = '';
                document.getElementById('wi-existing-notice').style.display = 'none';
                document.getElementById('wi-search-status').textContent = '';
                document.getElementById('wi-success').style.display = 'none';
            }, 1200);
        } else {
            alert('Error: ' + (data.message || 'Failed to add walk-in'));
        }
    } catch (e) {
        alert('Network error. Please try again.');
    } finally {
        btn.textContent = 'Add Walk-In';
        btn.disabled = false;
    }
}


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
        newReminder:        '',
        reminders: [
            { text: 'Call lab for implant kit', done: false },
            { text: 'Patient payment follow-up', done: false },
        ],

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

            // Load persisted reminders from localStorage
            try {
                const saved = localStorage.getItem('dentfluence_reminders');
                if (saved) this.reminders = JSON.parse(saved);
            } catch(e) {}

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
            this.clockTime      = now.toLocaleTimeString('en-IN', { hour12: true, hour: '2-digit', minute: '2-digit', second: '2-digit' });
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
                const url = new URL(window.__APPT_DATA.routes.todayQueue);
                if (this.queueDoctorId) url.searchParams.set('doctor_id', this.queueDoctorId);
                if (this.activeStatusFilter && this.activeStatusFilter !== 'total' && this.activeStatusFilter !== 'walkin') {
                    url.searchParams.set('status', this.activeStatusFilter);
                }

                const res  = await fetch(url, { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken } });
                const data = await res.json();

                let list = data.appointments || data;
                if (this.activeStatusFilter === 'walkin') {
                    list = list.filter(a => a.is_walkin);
                }
                this.queueList = this.sortQueue(list);
                this.counts    = data.counts || this.counts;
            } catch (e) {
                console.error('Queue refresh failed', e);
            }
        },

        toggleStatusFilter(key) {
            this.activeStatusFilter = this.activeStatusFilter === key ? '' : key;
            this.refreshQueue();
            this.applyCalendarFilter();
        },

        applyFilters() {
            this.applyCalendarFilter();
        },

        applyCalendarFilter() {
            if (!calendar) return;
            const events = buildCalendarEvents(
                window.__APPT_DATA.appointments.filter(apt => {
                    if (this.filterDoctorId && apt.doctor_id != this.filterDoctorId) return false;
                    if (this.activeStatusFilter && this.activeStatusFilter !== 'total') {
                        if (this.activeStatusFilter === 'walkin') {
                            if (!apt.is_walkin) return false;
                        } else if (apt.status !== this.activeStatusFilter) return false;
                    }
                    if (this.searchQuery) {
                        const q = this.searchQuery.toLowerCase();
                        const match = [apt.patient_name, apt.patient_phone, apt.treatment_category, apt.treatment, apt.doctor_name]
                            .some(v => v && v.toLowerCase().includes(q));
                        if (!match) return false;
                    }
                    return true;
                })
            );
            calendar.removeAllEvents();
            calendar.addEventSource(events);
        },

        async updateStatus(id, status) {
            try {
                const url = window.__APPT_DATA.routes.statusUpdate.replace('{id}', id);
                const res = await fetch(url, {
                    method:  'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': window.__APPT_DATA.csrfToken, 'Accept': 'application/json' },
                    body: JSON.stringify({ status }),
                });
                const data = await res.json();
                if (data.ok) {
                    const idx = this.queueList.findIndex(a => a.id == id);
                    if (idx !== -1 && data.appointment) {
                        this.queueList[idx] = data.appointment;
                        this.queueList = this.sortQueue([...this.queueList]);
                    }
                    if (data.appointment) this.updateCalendarEvent(data.appointment);
                    if (data.counts) this.counts = data.counts;
                    const mi = window.__APPT_DATA.appointments.findIndex(a => a.id == id);
                    if (mi !== -1 && data.appointment) window.__APPT_DATA.appointments[mi] = data.appointment;
                }
            } catch (e) {
                console.error('Status update failed', e);
            }
        },

        updateCalendarEvent(apt) {
            if (!calendar) return;
            const ev = calendar.getEventById(apt.id);
            if (!ev) return;
            ev.setExtendedProp('status', apt.status);
            ev.setProp('classNames', [`status-${apt.status}`]);
            ev.setProp('backgroundColor', getTreatmentFill(apt.treatment_category));
        },

        addAppointmentToCalendar(apt) {
            if (!calendar) return;
            window.__APPT_DATA.appointments.push(apt);
            const events = buildCalendarEvents([apt]);
            if (events.length) calendar.addEvent(events[0]);
        },

        confirmCancel(id) {
            if (confirm('Cancel this appointment?')) {
                this.updateStatus(id, 'cancelled');
            }
        },

        waContact(phone) {
            if (!phone) return;
            const clean = phone.replace(/\D/g, '');
            const num   = clean.startsWith('91') ? clean : '91' + clean;
            window.open(`https://wa.me/${num}`, '_blank');
        },

        showQuickView(apt, event) {
            showQuickView(apt, event);
        },

        addReminder() {
            if (this.newReminder.trim()) {
                this.reminders.push({ text: this.newReminder.trim(), done: false });
                this.newReminder = '';
                this.saveReminders();
            }
        },

        saveReminders() {
            try { localStorage.setItem('dentfluence_reminders', JSON.stringify(this.reminders)); } catch(e) {}
        },

        // Helpers exposed to templates
        getStatusLabel(s) { return STATUS_META[s]?.label || s; },
        getStatusColor(s) { return STATUS_META[s]?.color || '#64748b'; },
        getStatusBg(s)    { return STATUS_META[s]?.bg    || '#f1f5f9'; },
        getDoctorColor,
        getTreatmentFill,
    };
}
</script>
@endpush
@endsection