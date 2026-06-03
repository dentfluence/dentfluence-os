@extends('layouts.app')

@section('title', 'Daily Huddle — ' . $today->format('l, d F Y'))

@section('head-extra')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@vite(['resources/css/communication/huddle.css'])
@endsection

@push('scripts')
<style>
/* ─── Reset & Root ─────────────────────────────────────────── */
.hd-escape { margin: -28px -32px -48px; }
@media(max-width:767px){ .hd-escape { margin: -20px -16px -40px; } }

.hd {
    --c-sidebar: #1a1d2e;
    --c-bg:      #f0f2f7;
    --c-white:   #ffffff;
    --c-border:  #e4e8f0;
    --c-text:    #1a1d2e;
    --c-muted:   #6b7280;
    --c-accent:  #4f46e5;
    --c-accent2: #7c3aed;
    --c-green:   #16a34a;
    --c-red:     #dc2626;
    --c-amber:   #d97706;
    --c-blue:    #2563eb;
    --c-teal:    #0891b2;

    font-family: 'Inter', sans-serif;
    background: var(--c-bg);
    min-height: 100vh;
    color: var(--c-text);
    font-size: 13px;
}

/* ─── Top Navigation Bar ───────────────────────────────────── */
.hd-topbar {
    background: var(--c-white);
    border-bottom: 1px solid var(--c-border);
    padding: 0 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    height: 52px;
    position: sticky;
    top: 0;
    z-index: 50;
}
.hd-topbar-brand {
    display: flex;
    align-items: center;
    gap: .5rem;
    font-weight: 700;
    font-size: .85rem;
    color: var(--c-text);
    white-space: nowrap;
    padding-right: 1rem;
    border-right: 1px solid var(--c-border);
    margin-right: .25rem;
}
.hd-topbar-brand svg { color: var(--c-accent); }
.hd-nav-tabs {
    display: flex;
    gap: .15rem;
    flex: 1;
}
.hd-nav-tab {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .32rem .75rem;
    border-radius: 6px;
    font-size: .78rem;
    font-weight: 500;
    color: var(--c-muted);
    text-decoration: none;
    white-space: nowrap;
    transition: background .12s, color .12s;
    border: 1px solid transparent;
}
.hd-nav-tab:hover { background: #f3f4f6; color: var(--c-text); }
.hd-nav-tab.active {
    background: var(--c-accent);
    color: #fff;
    border-color: var(--c-accent);
}
.hd-topbar-right {
    display: flex;
    align-items: center;
    gap: .55rem;
    margin-left: auto;
}
.hd-search {
    display: flex;
    align-items: center;
    gap: .4rem;
    background: var(--c-bg);
    border: 1px solid var(--c-border);
    border-radius: 7px;
    padding: .3rem .7rem;
    font-size: .78rem;
    color: var(--c-muted);
    width: 180px;
}
.hd-search input {
    border: none;
    background: transparent;
    outline: none;
    font-size: .78rem;
    font-family: inherit;
    color: var(--c-text);
    width: 100%;
}
.hd-icon-btn {
    width: 32px; height: 32px;
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    background: var(--c-bg);
    border: 1px solid var(--c-border);
    cursor: pointer;
    color: var(--c-muted);
    position: relative;
    transition: background .12s;
}
.hd-icon-btn:hover { background: #e9ebf0; color: var(--c-text); }
.hd-notif-dot {
    position: absolute;
    top: 5px; right: 5px;
    width: 7px; height: 7px;
    background: var(--c-red);
    border-radius: 50%;
    border: 1.5px solid white;
}
.hd-add-task-btn {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    background: var(--c-accent);
    color: #fff;
    border: none;
    border-radius: 7px;
    padding: .38rem .85rem;
    font-size: .78rem;
    font-weight: 600;
    font-family: inherit;
    cursor: pointer;
    white-space: nowrap;
    transition: background .12s;
}
.hd-add-task-btn:hover { background: var(--c-accent2); }

/* ─── Header Row (greeting + date) ────────────────────────── */
.hd-header {
    padding: .85rem 1.5rem .6rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
}
.hd-greeting { font-size: 1.05rem; font-weight: 700; color: var(--c-text); }
.hd-greeting span { font-weight: 400; color: var(--c-muted); font-size: .85rem; margin-left: .5rem; }
.hd-header-meta { font-size: .75rem; color: var(--c-muted); display: flex; align-items: center; gap: .8rem; }
.hd-live-dot { width: 7px; height: 7px; background: var(--c-green); border-radius: 50%; display: inline-block; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

/* ─── Stats Strip ──────────────────────────────────────────── */
.hd-stats-strip {
    display: flex;
    gap: .6rem;
    padding: 0 1.5rem .85rem;
    overflow-x: auto;
    scrollbar-width: none;
}
.hd-stats-strip::-webkit-scrollbar { display: none; }
.hd-stat-pill {
    background: var(--c-white);
    border: 1px solid var(--c-border);
    border-radius: 10px;
    padding: .65rem .9rem;
    min-width: 120px;
    flex-shrink: 0;
    display: flex;
    align-items: flex-start;
    gap: .6rem;
}
.hd-stat-ico {
    width: 32px; height: 32px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
}
.hd-stat-ico-blue  { background: #eff6ff; color: var(--c-blue); }
.hd-stat-ico-green { background: #f0fdf4; color: var(--c-green); }
.hd-stat-ico-amber { background: #fffbeb; color: var(--c-amber); }
.hd-stat-ico-red   { background: #fef2f2; color: var(--c-red); }
.hd-stat-ico-teal  { background: #ecfeff; color: var(--c-teal); }
.hd-stat-ico-purple{ background: #f5f3ff; color: var(--c-accent); }
.hd-stat-val { font-size: 1.3rem; font-weight: 700; line-height: 1; color: var(--c-text); }
.hd-stat-label { font-size: .67rem; color: var(--c-muted); margin-top: .18rem; line-height: 1.3; }
.hd-stat-sub { font-size: .65rem; color: var(--c-green); margin-top: .1rem; }
.hd-stat-sub.warn { color: var(--c-red); }

/* ─── Kanban Board ─────────────────────────────────────────── */
.hd-board-wrap {
    padding: 0 1.5rem 1.5rem;
    overflow-x: auto;
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
}
.hd-board {
    display: flex;
    gap: .75rem;
    align-items: flex-start;
    min-width: max-content;
}
.hd-col {
    width: 220px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    gap: .5rem;
}
.hd-col-wide { width: 240px; }

/* ─── Column Header ────────────────────────────────────────── */
.hd-col-hdr {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .4rem .6rem;
}
.hd-col-title {
    font-size: .72rem;
    font-weight: 700;
    color: var(--c-text);
    letter-spacing: .03em;
    display: flex;
    align-items: center;
    gap: .35rem;
}
.hd-col-count {
    background: #e9ebf0;
    color: var(--c-muted);
    font-size: .62rem;
    font-weight: 700;
    padding: .1rem .42rem;
    border-radius: 999px;
}
.hd-col-count.red { background: #fee2e2; color: var(--c-red); }
.hd-col-count.green { background: #dcfce7; color: var(--c-green); }
.hd-col-menu {
    color: var(--c-muted);
    cursor: pointer;
    padding: .15rem;
    border-radius: 4px;
    transition: background .12s;
}
.hd-col-menu:hover { background: var(--c-bg); color: var(--c-text); }

/* ─── Card Base ────────────────────────────────────────────── */
.hd-card {
    background: var(--c-white);
    border: 1px solid var(--c-border);
    border-radius: 10px;
    overflow: hidden;
    transition: box-shadow .15s;
}
.hd-card:hover { box-shadow: 0 3px 12px rgba(0,0,0,.08); }

/* ─── Patient Flow Card ────────────────────────────────────── */
.hd-pfc {
    padding: .62rem .75rem;
    cursor: default;
}
.hd-pfc-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: .4rem;
    margin-bottom: .32rem;
}
.hd-pfc-time {
    font-size: .72rem;
    font-weight: 700;
    color: var(--c-accent);
    white-space: nowrap;
}
.hd-pfc-name {
    font-size: .82rem;
    font-weight: 600;
    color: var(--c-text);
    text-decoration: none;
    line-height: 1.25;
    flex: 1;
}
.hd-pfc-name:hover { color: var(--c-accent); }
.hd-pfc-star { color: #f59e0b; font-size: .8rem; }
.hd-pfc-meta {
    display: flex;
    align-items: center;
    gap: .35rem;
    font-size: .7rem;
    color: var(--c-muted);
    margin-bottom: .28rem;
    flex-wrap: wrap;
}
.hd-pfc-meta svg { flex-shrink: 0; }
.hd-pfc-alert {
    display: inline-flex;
    align-items: center;
    gap: .2rem;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: var(--c-red);
    font-size: .63rem;
    font-weight: 600;
    padding: .1rem .38rem;
    border-radius: 4px;
    margin-bottom: .3rem;
}
.hd-pfc-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: .3rem;
}
.hd-pfc-arrived {
    font-size: .65rem;
    color: var(--c-green);
    font-weight: 500;
}
.hd-pfc-instr {
    font-size: .67rem;
    color: var(--c-muted);
    font-style: italic;
    cursor: pointer;
    flex: 1;
    min-height: 1em;
}
.hd-pfc-instr:empty::before { content: 'Add note…'; opacity: .4; }
.hd-pfc-instr-inp {
    font-size: .67rem;
    font-family: inherit;
    border: 1px solid var(--c-accent);
    border-radius: 4px;
    padding: .12rem .32rem;
    outline: none;
    width: 100%;
}

/* ─── Status / Priority Badges ────────────────────────────── */
.hd-badge {
    display: inline-flex;
    align-items: center;
    padding: .1rem .42rem;
    border-radius: 4px;
    font-size: .63rem;
    font-weight: 700;
    white-space: nowrap;
}
.hd-b-scheduled  { background: #f3f0ff; color: #5b21b6; }
.hd-b-checkin    { background: #dbeafe; color: #1e40af; }
.hd-b-in_chair   { background: #e0f2fe; color: #0369a1; }
.hd-b-checkout   { background: #fef9c3; color: #854d0e; }
.hd-b-done       { background: #dcfce7; color: #166534; }
.hd-b-cancelled  { background: #fee2e2; color: #991b1b; }
.hd-b-no_show    { background: #f3f4f6; color: #6b7280; }
.hd-b-confirmed  { background: #dcfce7; color: #166534; }
.hd-b-high   { background: #fef2f2; color: var(--c-red); }
.hd-b-medium { background: #fffbeb; color: var(--c-amber); }
.hd-b-low    { background: #f0fdf4; color: var(--c-green); }
.hd-b-pending{ background: #f3f0ff; color: #5b21b6; }
.hd-b-in_progress { background: #dbeafe; color: #1e40af; }
.hd-b-overdue{ background: #fef2f2; color: var(--c-red); }

/* ─── Task Card ────────────────────────────────────────────── */
.hd-tc {
    padding: .58rem .75rem;
    display: flex;
    align-items: flex-start;
    gap: .52rem;
    border-bottom: 1px solid #f5f5f7;
}
.hd-tc:last-child { border-bottom: none; }
.hd-tc-check {
    width: 15px; height: 15px;
    border: 2px solid var(--c-border);
    border-radius: 4px;
    flex-shrink: 0;
    margin-top: 1px;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    transition: background .12s, border-color .12s;
}
.hd-tc-check.done { background: var(--c-green); border-color: var(--c-green); }
.hd-tc-check svg { display: none; }
.hd-tc-check.done svg { display: block; }
.hd-tc-body { flex: 1; min-width: 0; }
.hd-tc-title { font-size: .78rem; font-weight: 500; color: var(--c-text); line-height: 1.3; }
.hd-tc-title.done { text-decoration: line-through; color: var(--c-muted); }
.hd-tc-meta { display: flex; align-items: center; gap: .3rem; margin-top: .22rem; flex-wrap: wrap; }
.hd-tc-assignee { font-size: .66rem; color: var(--c-muted); }
.hd-tc-time { font-size: .66rem; color: var(--c-muted); }

/* ─── Comms Card ───────────────────────────────────────────── */
.hd-cc {
    padding: .58rem .75rem;
    display: flex;
    align-items: flex-start;
    gap: .5rem;
    border-bottom: 1px solid #f5f5f7;
}
.hd-cc:last-child { border-bottom: none; }
.hd-cc-ico {
    width: 26px; height: 26px;
    border-radius: 6px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}
.hd-cc-ico-call  { background: #eff6ff; color: var(--c-blue); }
.hd-cc-ico-wa    { background: #f0fdf4; color: var(--c-green); }
.hd-cc-ico-missed{ background: #fef2f2; color: var(--c-red); }
.hd-cc-ico-email { background: #f5f3ff; color: var(--c-accent); }
.hd-cc-ico-msg   { background: #fffbeb; color: var(--c-amber); }
.hd-cc-body { flex: 1; min-width: 0; }
.hd-cc-type { font-size: .72rem; font-weight: 600; color: var(--c-text); }
.hd-cc-desc { font-size: .68rem; color: var(--c-muted); margin-top: .1rem; line-height: 1.35; }
.hd-cc-footer { display: flex; align-items: center; justify-content: space-between; margin-top: .25rem; gap: .3rem; }
.hd-cc-by { font-size: .64rem; color: var(--c-muted); }
.hd-cc-time { font-size: .64rem; color: var(--c-muted); }
.hd-cc-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--c-red); flex-shrink: 0; margin-top: 3px; }
.hd-cc-cback {
    font-size: .63rem;
    padding: .1rem .42rem;
    border: 1px solid var(--c-border);
    border-radius: 4px;
    background: white;
    cursor: pointer;
    color: var(--c-accent);
    font-weight: 600;
    white-space: nowrap;
    transition: background .12s;
}
.hd-cc-cback:hover { background: #eff6ff; }

/* ─── Lab Card ─────────────────────────────────────────────── */
.hd-lc {
    padding: .6rem .75rem;
    border-bottom: 1px solid #f5f5f7;
}
.hd-lc:last-child { border-bottom: none; }
.hd-lc-name { font-size: .8rem; font-weight: 600; color: var(--c-text); margin-bottom: .15rem; }
.hd-lc-lab  { font-size: .66rem; color: var(--c-muted); margin-bottom: .2rem; }
.hd-lc-footer { display: flex; align-items: center; justify-content: space-between; gap: .4rem; }
.hd-lc-due { font-size: .63rem; color: var(--c-red); font-weight: 600; }
.hd-lc-due.ok { color: var(--c-green); }

/* ─── Inventory Card ───────────────────────────────────────── */
.hd-ic {
    padding: .6rem .75rem;
    border-bottom: 1px solid #f5f5f7;
}
.hd-ic:last-child { border-bottom: none; }
.hd-ic-name { font-size: .78rem; font-weight: 600; color: var(--c-text); }
.hd-ic-qty  { font-size: .66rem; color: var(--c-muted); margin: .1rem 0 .22rem; }
.hd-ic-ico  { width: 18px; height: 18px; flex-shrink: 0; }
.hd-ic-row  { display: flex; align-items: center; justify-content: space-between; gap: .4rem; }

/* ─── Marketing Card ───────────────────────────────────────── */
.hd-mc {
    padding: .58rem .75rem;
    border-bottom: 1px solid #f5f5f7;
    display: flex;
    align-items: flex-start;
    gap: .5rem;
}
.hd-mc:last-child { border-bottom: none; }
.hd-mc-ico {
    width: 22px; height: 22px;
    border-radius: 5px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    font-size: .7rem;
}
.hd-mc-ig { background: #fce7f3; }
.hd-mc-fb { background: #dbeafe; }
.hd-mc-gg { background: #fef9c3; }
.hd-mc-wa { background: #dcfce7; }
.hd-mc-body { flex: 1; min-width: 0; }
.hd-mc-title { font-size: .74rem; font-weight: 500; color: var(--c-text); line-height: 1.3; }
.hd-mc-time  { font-size: .63rem; color: var(--c-muted); margin-top: .1rem; }
.hd-mc-check { color: var(--c-green); flex-shrink: 0; }

/* ─── Failure / Maintenance Card ──────────────────────────── */
.hd-fc {
    padding: .58rem .75rem;
    border-bottom: 1px solid #f5f5f7;
    display: flex;
    align-items: flex-start;
    gap: .5rem;
}
.hd-fc:last-child { border-bottom: none; }
.hd-fc-ico {
    width: 18px; height: 18px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    margin-top: 1px;
}
.hd-fc-ico-high   { color: var(--c-red); }
.hd-fc-ico-medium { color: var(--c-amber); }
.hd-fc-ico-low    { color: var(--c-blue); }
.hd-fc-body { flex: 1; min-width: 0; }
.hd-fc-title { font-size: .78rem; font-weight: 600; color: var(--c-text); }
.hd-fc-desc  { font-size: .66rem; color: var(--c-muted); margin-top: .1rem; }
.hd-fc-footer{ display: flex; align-items: center; justify-content: space-between; margin-top: .2rem; }
.hd-fc-reported { font-size: .63rem; color: var(--c-muted); }

/* ─── Quick Actions Panel ──────────────────────────────────── */
.hd-qa-panel {
    background: var(--c-white);
    border: 1px solid var(--c-border);
    border-radius: 10px;
    padding: .75rem;
}
.hd-qa-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: .4rem;
}
.hd-qa-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: .28rem;
    padding: .55rem .3rem;
    border-radius: 8px;
    border: 1px solid var(--c-border);
    background: var(--c-bg);
    cursor: pointer;
    transition: background .12s, border-color .12s;
    text-decoration: none;
    color: var(--c-text);
}
.hd-qa-btn:hover { background: #e9ebf0; border-color: #c9cdd6; }
.hd-qa-btn svg { color: var(--c-accent); }
.hd-qa-btn span { font-size: .62rem; font-weight: 500; text-align: center; color: var(--c-muted); line-height: 1.2; }

/* ─── "View All" / "Add" links ─────────────────────────────── */
.hd-view-all {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: .55rem;
    font-size: .72rem;
    color: var(--c-accent);
    font-weight: 500;
    cursor: pointer;
    border-top: 1px solid var(--c-border);
    text-decoration: none;
    transition: background .12s;
}
.hd-view-all:hover { background: #f5f5fa; }

/* ─── Utility ──────────────────────────────────────────────── */
.hd-empty-col {
    text-align: center;
    padding: 1.8rem .75rem;
    color: var(--c-muted);
    font-size: .75rem;
    background: var(--c-white);
    border: 1px solid var(--c-border);
    border-radius: 10px;
}
.hd-bottom-row {
    display: flex;
    gap: .75rem;
    padding: 0 1.5rem 1.5rem;
    align-items: flex-start;
}
.hd-bottom-col { flex: 1; min-width: 0; }
.hd-bottom-wide { flex: 1.4; min-width: 0; }
.hd-section-hdr {
    font-size: .68rem;
    font-weight: 700;
    color: var(--c-muted);
    letter-spacing: .07em;
    text-transform: uppercase;
    margin-bottom: .45rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.hd-section-hdr-count {
    background: #e9ebf0;
    color: var(--c-muted);
    font-size: .6rem;
    font-weight: 700;
    padding: .08rem .38rem;
    border-radius: 999px;
}
.hd-footer-bar {
    background: var(--c-white);
    border-top: 1px solid var(--c-border);
    padding: .5rem 1.5rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: .7rem;
    color: var(--c-muted);
}
.hd-footer-bar span { display: flex; align-items: center; gap: .3rem; }
</style>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('huddleBooking', () => ({
        treatmentCategories: @json($treatmentCategories),
        timeSlots: @json($timeSlots),
        newAppt: {
            open: false, date: '{{ $today->toDateString() }}', time: '09:00',
            patientId: null, patientSearch: '', patientPhone: '',
            patientResults: [], showPatientDropdown: false,
            doctorId: '', duration: '30', type: 'consultation',
            treatmentCategoryId: '', treatmentId: '',
            notes: '', submitting: false, errors: [],
        },
        filteredTreatments() {
            if (!this.newAppt.treatmentCategoryId) return [];
            const cat = this.treatmentCategories.find(c => String(c.id) === String(this.newAppt.treatmentCategoryId));
            return cat ? (cat.treatments ?? []) : [];
        },
        applyTreatmentDefaults() {
            const t = this.filteredTreatments().find(t => String(t.id) === String(this.newAppt.treatmentId));
            if (t?.default_duration_minutes) {
                const d = [15,30,45,60,90,120].reduce((p,c) => Math.abs(c-t.default_duration_minutes) < Math.abs(p-t.default_duration_minutes) ? c : p);
                this.newAppt.duration = String(d);
            }
        },
        async searchPatients() {
            const q = this.newAppt.patientSearch.trim();
            if (q.length < 2) { this.newAppt.patientResults = []; return; }
            try {
                const res = await fetch(`/patients/search?q=${encodeURIComponent(q)}&json=1`);
                if (res.ok) this.newAppt.patientResults = await res.json();
            } catch(e) {}
        },
        selectPatient(p) {
            this.newAppt.patientId = p.id;
            this.newAppt.patientSearch = p.name;
            this.newAppt.patientPhone = p.phone ?? '';
            this.newAppt.patientResults = [];
            this.newAppt.showPatientDropdown = false;
        },
        async submitAppointment() {
            this.newAppt.errors = [];
            if (!this.newAppt.patientId)    this.newAppt.errors.push('Please select a patient.');
            if (!this.newAppt.doctorId)     this.newAppt.errors.push('Please select a doctor.');
            if (!this.newAppt.date)         this.newAppt.errors.push('Date is required.');
            if (!this.newAppt.time)         this.newAppt.errors.push('Time is required.');
            if (!this.newAppt.notes.trim()) this.newAppt.errors.push('Notes are required.');
            if (this.newAppt.type === 'treatment') {
                if (!this.newAppt.treatmentCategoryId) this.newAppt.errors.push('Please select a treatment category.');
                if (!this.newAppt.treatmentId)         this.newAppt.errors.push('Please select a treatment.');
            }
            if (this.newAppt.errors.length) return;
            this.newAppt.submitting = true;
            try {
                const res = await fetch('/appointments', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({
                        patient_id: this.newAppt.patientId,
                        doctor_id: this.newAppt.doctorId,
                        appointment_date: this.newAppt.date,
                        appointment_time: this.newAppt.time,
                        duration_minutes: this.newAppt.duration,
                        type: this.newAppt.type,
                        notes: this.newAppt.notes,
                        treatment_category_id: this.newAppt.treatmentCategoryId || null,
                        treatment_id: this.newAppt.treatmentId || null,
                    }),
                });
                const data = await res.json();
                if (res.ok) {
                    this.newAppt.open = false;
                    window.location.reload();
                } else {
                    this.newAppt.errors = data.errors ? Object.values(data.errors).flat() : [data.message ?? 'Something went wrong.'];
                }
            } catch(e) {
                this.newAppt.errors = ['Network error. Please try again.'];
            } finally {
                this.newAppt.submitting = false;
            }
        },
        init() {
            window.addEventListener('open-booking-modal', () => {
                this.newAppt.open = true;
                this.newAppt.date = '{{ $today->toDateString() }}';
            });
        }
    }));
});
</script>
@endpush

@section('content')
<div class="hd hd-escape"
     x-data="{ showAddAppointment: false, showAddPatient: false }"
     x-on:open-add-appointment.window="showAddAppointment = true"
     x-on:open-add-patient.window="showAddPatient = true">

{{-- ══ TOP NAV BAR ══════════════════════════════════════════════════════════ --}}
<div class="hd-topbar">
    <div class="hd-topbar-brand">
        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        Tulip Dental
    </div>
    <nav class="hd-nav-tabs">
        <a href="#" class="hd-nav-tab active">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Daily Huddle
        </a>
        <a href="#" class="hd-nav-tab">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Weekly Report
        </a>
        <a href="#" class="hd-nav-tab">Monthly Report</a>
        <a href="#" class="hd-nav-tab">Quarterly Report</a>
        <a href="#" class="hd-nav-tab">Annual Report</a>
    </nav>
    <div class="hd-topbar-right">
        <div class="hd-search">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" placeholder="Search patient, call, task…">
        </div>
        <div class="hd-icon-btn">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
            @if($criticalAlerts->isNotEmpty())
                <span class="hd-notif-dot"></span>
            @endif
        </div>
        <div class="hd-icon-btn" @click="window.location.reload()">
            <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        </div>
        <button class="hd-add-task-btn" @click.prevent="openCombinedModal('appointment')">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
            Add Task
        </button>
    </div>
</div>

{{-- ══ GREETING + DATE ══════════════════════════════════════════════════════ --}}
<div class="hd-header">
    <div>
        <div class="hd-greeting">
            Good Morning, {{ ucfirst(strtok(auth()->user()->name, ' ')) }}! 👋
            <span>Front Desk Huddle</span>
        </div>
        <div style="font-size:.72rem;color:var(--c-muted);margin-top:.15rem;">
            {{ $today->format('l, d F Y') }}
            &nbsp;•&nbsp;
            <span x-data
                  x-text="new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'})"
                  x-init="setInterval(()=>$el.textContent=new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'}),10000)">
            </span>
        </div>
    </div>
    <div class="hd-header-meta">
        <span class="hd-live-dot"></span>
        Last synced:
        <span x-data
              x-text="new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'})"
              x-init="setInterval(()=>$el.textContent=new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'}),30000)">
        </span>
    </div>
</div>

{{-- ══ STATS STRIP ══════════════════════════════════════════════════════════ --}}
<div class="hd-stats-strip">

    {{-- Today's Appointments --}}
    <div class="hd-stat-pill">
        <div class="hd-stat-ico hd-stat-ico-blue">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
        </div>
        <div>
            <div class="hd-stat-val">{{ $todaysAppointments->count() }}</div>
            <div class="hd-stat-label">Today's Appointments</div>
            <div class="hd-stat-sub">+3 vs yesterday</div>
        </div>
    </div>

    {{-- Today's Calls --}}
    <div class="hd-stat-pill">
        <div class="hd-stat-ico hd-stat-ico-amber">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
        </div>
        <div>
            <div class="hd-stat-val">{{ $commList->count() }}</div>
            <div class="hd-stat-label">Today's Calls</div>
            <div class="hd-stat-sub">{{ $commList->where('status','pending')->count() }} pending</div>
        </div>
    </div>

    {{-- Collections --}}
    <div class="hd-stat-pill">
        <div class="hd-stat-ico hd-stat-ico-green">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
        <div>
            <div class="hd-stat-val">—</div>
            <div class="hd-stat-label">Collections (Today)</div>
            <div class="hd-stat-sub" style="color:var(--c-muted);">34% of target</div>
        </div>
    </div>

    {{-- Pending Tasks --}}
    <div class="hd-stat-pill">
        <div class="hd-stat-ico hd-stat-ico-purple">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        </div>
        <div>
            <div class="hd-stat-val">{{ $myTasks->count() }}</div>
            <div class="hd-stat-label">Pending Tasks</div>
            <div class="hd-stat-sub" style="color:var(--c-muted);">Across team</div>
        </div>
    </div>

    {{-- Critical Alerts --}}
    <div class="hd-stat-pill">
        <div class="hd-stat-ico hd-stat-ico-red">
            <svg width="15" height="15" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <div>
            <div class="hd-stat-val" style="color:var(--c-red);">{{ $criticalAlerts->count() }}</div>
            <div class="hd-stat-label">Critical Alerts</div>
            @if($criticalAlerts->isNotEmpty())
                <div class="hd-stat-sub warn">Needs attention</div>
            @else
                <div class="hd-stat-sub">All clear</div>
            @endif
        </div>
    </div>
 
</div>

{{-- ══ KANBAN BOARD ════════════════════════════════════════════════════════ --}}
   <div style="padding: 0 1.5rem .85rem;">
    @include('communication.huddle.communication-alerts', ['alerts' => $commAlerts, 'counts' => $commCounts])

</div>
<div class="hd-board-wrap">
<div class="hd-board">

    {{-- ── COL 1: TODAY'S PATIENT FLOW ── --}}
    <div class="hd-col hd-col-wide">
        <div class="hd-col-hdr">
            <div class="hd-col-title">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Today's Patient Flow
            </div>
            <div style="display:flex;align-items:center;gap:.3rem;">
                <span class="hd-col-count">{{ $todaysAppointments->count() }}</span>
                <span class="hd-col-menu">•••</span>
            </div>
        </div>

        @forelse($todaysAppointments as $appt)
        <div class="hd-card"
             x-data="{
                editing: false,
                instruction: '{{ addslashes($appt->staff_instruction ?? '') }}',
                original: '{{ addslashes($appt->staff_instruction ?? '') }}',
                saving: false,
                status: '{{ $appt->status }}',
                statusSaving: false,
                statusFlow: ['scheduled','checkin','in_chair','done'],
                async cycleStatus() {
                    if (this.statusSaving) return;
                    const idx = this.statusFlow.indexOf(this.status);
                    if (idx === this.statusFlow.length - 1) return;
                    const next = this.statusFlow[idx + 1];
                    this.statusSaving = true;
                    this.status = next;
                    await fetch('{{ route('appointments.updateStatus', $appt->id) }}', {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ status: next })
                    });
                    this.statusSaving = false;
                },
                async save() {
                    this.saving = true;
                    await fetch('{{ route('huddle.appointments.instruction', $appt->id) }}', {
                        method: 'PATCH',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                        body: JSON.stringify({ staff_instruction: this.instruction })
                    });
                    this.original = this.instruction;
                    this.editing = false;
                    this.saving = false;
                }
             }">
            <div class="hd-pfc">
                <div class="hd-pfc-top">
                    <span class="hd-pfc-time">{{ $appt->appointment_time ? \Carbon\Carbon::parse($appt->appointment_time)->format('H:i') : '—' }}</span>
                    <div style="flex:1;min-width:0;padding:0 .3rem;">
                        <div style="font-size:.72rem;color:var(--c-muted);font-weight:500;">
                            {{ $appt->type ? ucfirst(str_replace('_',' ',$appt->type)) : 'Consultation' }}
                        </div>
                        <a href="{{ route('patients.show', $appt->patient_id) }}" class="hd-pfc-name">{{ $appt->patient->name ?? '—' }}</a>
                    </div>
                    @if(!empty($appt->patient->medical_alert))
                        <span class="hd-pfc-star" title="{{ $appt->patient->medical_alert }}">★</span>
                    @endif
                </div>

                <div class="hd-pfc-meta">
                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="2" y="3" width="20" height="14" rx="2" stroke-width="2"/><path d="M8 21h8m-4-4v4" stroke-width="2" stroke-linecap="round"/></svg>
                    {{ $appt->chair ?? 'Chair —' }}
                    &nbsp;·&nbsp;
                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ $appt->doctor->name ?? '—' }}
                </div>

                @if(!empty($appt->patient->medical_alert))
                <div class="hd-pfc-alert">
                    <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                    {{ Str::limit($appt->patient->medical_alert, 30) }}
                </div>
                @endif

                <div class="hd-pfc-footer">
                    <span class="hd-badge"
                          :class="'hd-b-' + status"
                          :style="statusSaving ? 'opacity:.6' : 'cursor:pointer'"
                          x-text="status.replace(/_/g,' ').replace(/\b\w/g,l=>l.toUpperCase())"
                          @click="cycleStatus()"
                          title="Click to advance status"></span>
                    <span class="hd-pfc-arrived" x-show="status === 'checkin'">✓ Arrived</span>
                </div>

                {{-- Inline instruction note --}}
                <div style="margin-top:.3rem;">
                    <div x-show="!editing" @click="editing=true" class="hd-pfc-instr" x-text="instruction || ''"></div>
                    <template x-if="editing">
                        <div>
                            <input x-model="instruction"
                                   @keydown.enter="save()"
                                   @keydown.escape="instruction=original;editing=false"
                                   class="hd-pfc-instr-inp"
                                   placeholder="Add note…"
                                   x-init="$el.focus()"
                                   :disabled="saving">
                            <span @click="save()" style="font-size:.62rem;color:var(--c-accent);cursor:pointer;" x-text="saving ? 'Saving…' : 'Save'"></span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
        @empty
        <div class="hd-empty-col">No appointments today.</div>
        @endforelse

        {{-- ── Today: Treatment Visits ── --}}
        @if($todaysTreatmentVisits->isNotEmpty())
        <div style="padding:.3rem .6rem .1rem;border-top:1px solid var(--c-border);margin-top:.4rem;">
            <span style="font-size:.63rem;font-weight:700;color:var(--c-green);text-transform:uppercase;letter-spacing:.07em;">Treatment Visits ({{ $todaysTreatmentVisits->count() }})</span>
        </div>
        @foreach($todaysTreatmentVisits as $tv)
        <div class="hd-card" style="border-left:3px solid var(--c-green);">
            <div class="hd-pfc">
                <div class="hd-pfc-top">
                    <span class="hd-pfc-time" style="background:#f0fdf4;color:var(--c-green);">
                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </span>
                    <div style="flex:1;min-width:0;padding:0 .3rem;">
                        <div style="font-size:.72rem;color:var(--c-muted);font-weight:500;">
                            {{ $tv->treatment_name ?? ucfirst(str_replace('_',' ',$tv->visit_type ?? 'Treatment')) }}
                        </div>
                        <a href="{{ route('patients.show', $tv->patient_id) }}" class="hd-pfc-name">{{ $tv->patient->name ?? '—' }}</a>
                    </div>
                </div>
                <div class="hd-pfc-meta">
                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ $tv->doctor->name ?? '—' }}
                </div>
                <div class="hd-pfc-footer">
                    <span class="hd-badge" style="background:#dcfce7;color:#16a34a;border-color:#bbf7d0;">
                        {{ ucfirst(str_replace('_',' ',$tv->status ?? 'scheduled')) }}
                    </span>
                </div>
            </div>
        </div>
        @endforeach
        @endif

        {{-- ── Today: Consultations ── --}}
        @if($todaysConsultations->isNotEmpty())
        <div style="padding:.3rem .6rem .1rem;border-top:1px solid var(--c-border);margin-top:.4rem;">
            <span style="font-size:.63rem;font-weight:700;color:var(--c-accent2);text-transform:uppercase;letter-spacing:.07em;">Consultations ({{ $todaysConsultations->count() }})</span>
        </div>
        @foreach($todaysConsultations as $tc)
        <div class="hd-card" style="border-left:3px solid var(--c-accent2);">
            <div class="hd-pfc">
                <div class="hd-pfc-top">
                    <span class="hd-pfc-time" style="background:#f5f3ff;color:var(--c-accent2);">
                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </span>
                    <div style="flex:1;min-width:0;padding:0 .3rem;">
                        <div style="font-size:.72rem;color:var(--c-muted);font-weight:500;">
                            {{ $tc->visit_type ? ucwords(str_replace('_',' ',$tc->visit_type)) : 'Consultation' }}
                        </div>
                        <a href="{{ route('patients.show', $tc->patient_id) }}" class="hd-pfc-name">{{ $tc->patient->name ?? '—' }}</a>
                    </div>
                </div>
                @if($tc->chief_complaint)
                <div style="font-size:.68rem;color:var(--c-muted);margin:.2rem 0 .15rem;line-height:1.35;">
                    <span style="font-weight:500;color:var(--c-text);">CC:</span> {{ Str::limit($tc->chief_complaint, 55) }}
                </div>
                @endif
                <div class="hd-pfc-meta">
                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ $tc->doctor->name ?? '—' }}
                </div>
                <div class="hd-pfc-footer">
                    <span class="hd-badge" style="background:#ede9fe;color:#7c3aed;border-color:#c4b5fd;">
                        {{ ucfirst($tc->status ?? 'draft') }}
                    </span>
                </div>
            </div>
        </div>
        @endforeach
        @endif

        <a href="#" class="hd-view-all" @click.prevent="window.dispatchEvent(new CustomEvent('open-booking-modal'))">
            + Add Appointment
        </a>
    </div>

    {{-- ── COL 2: YESTERDAY'S FLOW ── --}}
    <div class="hd-col hd-col-wide">
        <div class="hd-col-hdr">
            <div class="hd-col-title">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                Yesterday's Flow
            </div>
            <div style="display:flex;align-items:center;gap:.3rem;">
                <span class="hd-col-count">{{ $yesterdaysAppointments->count() }}</span>
                <span class="hd-col-menu">•••</span>
            </div>
        </div>

        @forelse($yesterdaysAppointments as $yAppt)
        <div class="hd-card">
            <div class="hd-pfc">

                {{-- Top row: time + type + patient name --}}
                <div class="hd-pfc-top">
                    <span class="hd-pfc-time">{{ $yAppt->appointment_time ? \Carbon\Carbon::parse($yAppt->appointment_time)->format('H:i') : '—' }}</span>
                    <div style="flex:1;min-width:0;padding:0 .3rem;">
                        <div style="font-size:.72rem;color:var(--c-muted);font-weight:500;">
                            {{ $yAppt->type ? ucfirst(str_replace('_',' ',$yAppt->type)) : 'Consultation' }}
                        </div>
                        <a href="{{ route('patients.show', $yAppt->patient_id) }}" class="hd-pfc-name">{{ $yAppt->patient->name ?? '—' }}</a>
                    </div>
                    @if(!empty($yAppt->patient->medical_alert))
                        <span class="hd-pfc-star" title="{{ $yAppt->patient->medical_alert }}">★</span>
                    @endif
                </div>

                {{-- Doctor row --}}
                <div class="hd-pfc-meta">
                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    Dr. {{ $yAppt->doctor->name ?? '—' }}
                    @if($yAppt->treatment_name)
                        &nbsp;·&nbsp; {{ $yAppt->treatment_name }}
                    @endif
                </div>

                {{-- Visit log status -- the core of this column --}}
                @if(in_array($yAppt->appointment_status, ['cancelled', 'no_show']))
                    {{-- Cancelled/no-show: grey, no flag --}}
                    <div style="display:flex;align-items:center;gap:.3rem;margin-top:.35rem;">
                        <span class="hd-badge hd-b-{{ $yAppt->appointment_status }}">
                            {{ str_replace('_',' ', ucfirst($yAppt->appointment_status)) }}
                        </span>
                    </div>

                @elseif($yAppt->visit_logged)
                    {{-- Visit WAS logged by doctor ✅ --}}
                    <div style="display:flex;align-items:center;gap:.35rem;margin-top:.35rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:6px;padding:.3rem .5rem;">
                        <svg width="11" height="11" fill="none" stroke="#16a34a" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span style="font-size:.68rem;font-weight:600;color:#16a34a;">Visit Logged</span>
                        @if($yAppt->consultation_status === 'completed')
                            <span style="font-size:.63rem;color:#16a34a;">· Completed</span>
                        @else
                            <span style="font-size:.63rem;color:#d97706;">· Draft</span>
                        @endif
                    </div>
                    {{-- What was done --}}
                    @if($yAppt->chief_complaint)
                        <div style="font-size:.68rem;color:var(--c-muted);margin-top:.25rem;line-height:1.35;">
                            <span style="font-weight:500;color:var(--c-text);">CC:</span> {{ Str::limit($yAppt->chief_complaint, 60) }}
                        </div>
                    @endif
                    @if($yAppt->primary_diagnosis)
                        <div style="font-size:.68rem;color:var(--c-muted);margin-top:.15rem;line-height:1.35;">
                            <span style="font-weight:500;color:var(--c-text);">Dx:</span> {{ Str::limit($yAppt->primary_diagnosis, 60) }}
                        </div>
                    @endif
                    @if($yAppt->finishing_notes)
                        <div style="font-size:.68rem;color:var(--c-muted);margin-top:.15rem;line-height:1.35;">
                            <span style="font-weight:500;color:var(--c-text);">Note:</span> {{ Str::limit($yAppt->finishing_notes, 60) }}
                        </div>
                    @endif

                @else
                    {{-- Visit NOT logged — red flag ⚠️ --}}
                    <div style="display:flex;align-items:center;gap:.35rem;margin-top:.35rem;background:#fef2f2;border:1px solid #fecaca;border-radius:6px;padding:.3rem .5rem;">
                        <svg width="11" height="11" fill="none" stroke="#dc2626" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        <span style="font-size:.68rem;font-weight:600;color:#dc2626;">Visit Not Logged</span>
                    </div>
                    <div style="font-size:.65rem;color:var(--c-muted);margin-top:.18rem;">
                        Appointment: <span style="font-weight:500;">{{ str_replace('_',' ',ucfirst($yAppt->appointment_status)) }}</span>
                    </div>
                @endif

            </div>
        </div>
        @empty
        <div class="hd-empty-col">No appointments yesterday.</div>
        @endforelse

        {{-- ── Yesterday: Treatment Visits ── --}}
        @if($yesterdaysTreatmentVisits->isNotEmpty())
        <div style="padding:.3rem .6rem .1rem;border-top:1px solid var(--c-border);margin-top:.4rem;">
            <span style="font-size:.63rem;font-weight:700;color:var(--c-green);text-transform:uppercase;letter-spacing:.07em;">Treatment Visits ({{ $yesterdaysTreatmentVisits->count() }})</span>
        </div>
        @foreach($yesterdaysTreatmentVisits as $ytv)
        <div class="hd-card" style="border-left:3px solid var(--c-green);">
            <div class="hd-pfc">
                <div class="hd-pfc-top">
                    <span class="hd-pfc-time" style="background:#f0fdf4;color:var(--c-green);">
                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </span>
                    <div style="flex:1;min-width:0;padding:0 .3rem;">
                        <div style="font-size:.72rem;color:var(--c-muted);font-weight:500;">
                            {{ $ytv->treatment_name ?? ucfirst(str_replace('_',' ',$ytv->visit_type ?? 'Treatment')) }}
                        </div>
                        <a href="{{ route('patients.show', $ytv->patient_id) }}" class="hd-pfc-name">{{ $ytv->patient->name ?? '—' }}</a>
                    </div>
                </div>
                <div class="hd-pfc-meta">
                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ $ytv->doctor->name ?? '—' }}
                </div>
                <div class="hd-pfc-footer">
                    <span class="hd-badge" style="background:#dcfce7;color:#16a34a;border-color:#bbf7d0;">
                        {{ ucfirst(str_replace('_',' ',$ytv->status ?? 'completed')) }}
                    </span>
                </div>
            </div>
        </div>
        @endforeach
        @endif

        {{-- ── Yesterday: Consultations ── --}}
        @if($yesterdaysConsultations->isNotEmpty())
        <div style="padding:.3rem .6rem .1rem;border-top:1px solid var(--c-border);margin-top:.4rem;">
            <span style="font-size:.63rem;font-weight:700;color:var(--c-accent2);text-transform:uppercase;letter-spacing:.07em;">Consultations ({{ $yesterdaysConsultations->count() }})</span>
        </div>
        @foreach($yesterdaysConsultations as $yc)
        <div class="hd-card" style="border-left:3px solid var(--c-accent2);">
            <div class="hd-pfc">
                <div class="hd-pfc-top">
                    <span class="hd-pfc-time" style="background:#f5f3ff;color:var(--c-accent2);">
                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </span>
                    <div style="flex:1;min-width:0;padding:0 .3rem;">
                        <div style="font-size:.72rem;color:var(--c-muted);font-weight:500;">
                            {{ $yc->visit_type ? ucwords(str_replace('_',' ',$yc->visit_type)) : 'Consultation' }}
                        </div>
                        <a href="{{ route('patients.show', $yc->patient_id) }}" class="hd-pfc-name">{{ $yc->patient->name ?? '—' }}</a>
                    </div>
                </div>
                @if($yc->chief_complaint)
                <div style="font-size:.68rem;color:var(--c-muted);margin:.2rem 0 .15rem;line-height:1.35;">
                    <span style="font-weight:500;color:var(--c-text);">CC:</span> {{ Str::limit($yc->chief_complaint, 55) }}
                </div>
                @endif
                @if($yc->primary_diagnosis)
                <div style="font-size:.68rem;color:var(--c-muted);margin:.1rem 0;line-height:1.35;">
                    <span style="font-weight:500;color:var(--c-text);">Dx:</span> {{ Str::limit($yc->primary_diagnosis, 55) }}
                </div>
                @endif
                <div class="hd-pfc-meta">
                    <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                    {{ $yc->doctor->name ?? '—' }}
                </div>
                <div class="hd-pfc-footer">
                    <span class="hd-badge" style="background:#ede9fe;color:#7c3aed;border-color:#c4b5fd;">
                        {{ ucfirst($yc->status ?? 'completed') }}
                    </span>
                </div>
            </div>
        </div>
        @endforeach
        @endif

        <a href="{{ route('huddle.accountability') }}" class="hd-view-all">View All Yesterday</a>
    </div>

    {{-- ── COL 3: COMMS LIST ── --}}
    <div class="hd-col"
         x-data="{
            items: @json($commList->values()),
            markDone(id, i) {
                this.items.splice(i, 1);
                fetch('/communication-entries/' + id + '/done', {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                });
            }
         }">
        <div class="hd-col-hdr">
            <div class="hd-col-title">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                Comms List
            </div>
            <div style="display:flex;align-items:center;gap:.3rem;">
                <span class="hd-col-count" x-text="items.length"></span>
                <span class="hd-col-menu">•••</span>
            </div>
        </div>

        <template x-if="items.length === 0">
            <div class="hd-empty-col">No pending communications.</div>
        </template>

        <template x-for="(item, i) in items.slice(0,6)" :key="item.id">
            <div class="hd-card">
                <div class="hd-cc">
                    <div class="hd-cc-ico" :class="{
                        'hd-cc-ico-call':   item.type === 'call' || item.type === 'referral',
                        'hd-cc-ico-wa':     item.type === 'whatsapp' || item.type === 'testimonial',
                        'hd-cc-ico-missed': item.type === 'missed_call',
                        'hd-cc-ico-email':  item.type === 'email',
                        'hd-cc-ico-msg':    item.type === 'follow_up',
                    }">
                        <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    </div>
                    <div class="hd-cc-body">
                        <div class="hd-cc-type" x-text="item.type ? item.type.replace('_',' ').replace(/\b\w/g,l=>l.toUpperCase()) : 'Call'"></div>
                        <div class="hd-cc-desc" x-text="item.patient_name"></div>
                        <div class="hd-cc-footer">
                            <span class="hd-cc-by" x-text="item.phone ?? ''"></span>
                            <template x-if="item.type === 'missed_call'">
                                <button class="hd-cc-cback" @click="markDone(item.id, i)">Call Back</button>
                            </template>
                            <template x-if="item.type !== 'missed_call'">
                                <button class="hd-cc-cback" @click="markDone(item.id, i)">Done ✓</button>
                            </template>
                        </div>
                    </div>
                    <template x-if="item.type === 'missed_call'">
                        <div class="hd-cc-dot"></div>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="items.length > 6">
            <div class="hd-view-all" style="background:white;border:1px solid var(--c-border);border-radius:10px;cursor:pointer;">
                + <span x-text="items.length - 6"></span> more communications
            </div>
        </template>
    </div>

    {{-- ── COL 4: TASKS ── --}}
    <div class="hd-col"
         x-data="{
            tasks: @json($myTasks->values()),
            toggle(i) {
                this.tasks[i].done = !this.tasks[i].done;
                fetch('/huddle/tasks/' + this.tasks[i].id + '/status', {
                    method: 'PATCH',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                    body: JSON.stringify({ status: this.tasks[i].done ? 'done' : 'pending' })
                });
            }
         }">
        <div class="hd-col-hdr">
            <div class="hd-col-title">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Tasks
            </div>
            <div style="display:flex;align-items:center;gap:.3rem;">
                <span class="hd-col-count" x-text="tasks.filter(t=>!t.done).length + ' left'"></span>
                <span class="hd-col-menu">•••</span>
            </div>
        </div>

        <template x-if="tasks.length === 0">
            <div class="hd-empty-col">No pending tasks.</div>
        </template>

        <div class="hd-card" x-show="tasks.length > 0">
            <template x-for="(task, i) in tasks.slice(0,7)" :key="task.id">
                <div class="hd-tc">
                    <div class="hd-tc-check" :class="{ done: task.done }" @click="toggle(i)">
                        <svg width="8" height="8" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="hd-tc-body">
                        <div class="hd-tc-title" :class="{ done: task.done }" x-text="task.title"></div>
                        <div class="hd-tc-meta">
                            <span class="hd-badge" :class="'hd-b-' + (task.priority || 'medium')" x-text="task.priority || 'medium'"></span>
                            <span class="hd-tc-assignee" x-text="task.assignee_name ?? ''"></span>
                            <span class="hd-tc-time" x-text="task.due_time ? task.due_time.substring(0,5) : ''"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <template x-if="tasks.length > 7">
            <div class="hd-view-all" style="background:white;border:1px solid var(--c-border);border-radius:10px;cursor:pointer;">
                + <span x-text="tasks.length - 7"></span> more tasks
            </div>
        </template>
    </div>

    {{-- ── COL 5: LAB UPDATES ── --}}
    <div class="hd-col">
        <div class="hd-col-hdr">
            <div class="hd-col-title">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                Lab Updates
            </div>
            <div style="display:flex;align-items:center;gap:.3rem;">
                <span class="hd-col-count">{{ $labsDueToday->count() }}</span>
                <span class="hd-col-menu">•••</span>
            </div>
        </div>

        @forelse($labsDueToday as $lab)
        <div class="hd-card">
            <div class="hd-lc">
                <div class="hd-lc-name">{{ $lab->patient_name ?? 'Patient' }}</div>
                <div class="hd-lc-lab">{{ $lab->case_number }} · Lab: {{ $lab->lab_name ?? '—' }}</div>
                <div class="hd-lc-footer">
                    <span class="hd-badge hd-b-{{ $lab->status }}">{{ ucfirst(str_replace('_',' ',$lab->status)) }}</span>
                    @if(isset($lab->due_date) && \Carbon\Carbon::parse($lab->due_date)->isToday())
                        <span class="hd-lc-due">Due: Today</span>
                    @elseif(isset($lab->due_date))
                        <span class="hd-lc-due ok">Due: {{ \Carbon\Carbon::parse($lab->due_date)->format('d M') }}</span>
                    @endif
                </div>
            </div>
        </div>
        @empty
        <div class="hd-empty-col">No lab cases due today.</div>
        @endforelse

        @if($labsDueToday->count() > 5)
        <a href="#" class="hd-view-all">+ {{ $labsDueToday->count() - 5 }} more lab cases</a>
        @endif
    </div>

    {{-- ── COL 6: INVENTORY ALERTS ── --}}
    <div class="hd-col">
        <div class="hd-col-hdr">
            <div class="hd-col-title">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                Inventory Alerts
            </div>
            <div style="display:flex;align-items:center;gap:.3rem;">
                @if($criticalAlerts->where('type','inventory')->isNotEmpty())
                    <span class="hd-col-count red">{{ $criticalAlerts->where('type','inventory')->count() }}</span>
                @else
                    <span class="hd-col-count">0</span>
                @endif
                <span class="hd-col-menu">•••</span>
            </div>
        </div>

        @forelse($criticalAlerts->where('type','inventory')->where('level','error') as $inv)
        <div class="hd-card">
            <div class="hd-ic">
                <div class="hd-ic-row">
                    <div>
                        <div class="hd-ic-name">{{ $inv['message'] }}</div>
                        <div class="hd-ic-qty" style="color:var(--c-red);">
                            {{ isset($inv['qty']) ? 'Qty: ' . $inv['qty'] . ' / Min: ' . $inv['min_qty'] : 'Out of stock' }}
                        </div>
                    </div>
                    <a href="{{ route('inventory.index') }}" style="flex-shrink:0;">
                        <svg class="hd-ic-ico" fill="none" stroke="var(--c-red)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </a>
                </div>
            </div>
        </div>
        @empty
        <div class="hd-empty-col">
            <svg width="22" height="22" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="margin:0 auto .4rem;display:block;opacity:.4;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            All stock levels OK
        </div>
        @endforelse

        @foreach($criticalAlerts->where('type','inventory')->where('level','warning') as $warn)
        <div class="hd-card">
            <div class="hd-ic">
                <div class="hd-ic-row">
                    <div>
                        <div class="hd-ic-name">{{ $warn['message'] }}</div>
                        <div class="hd-ic-qty" style="color:var(--c-amber);">
                            Qty: {{ $warn['qty'] ?? 0 }} / Min: {{ $warn['min_qty'] ?? 0 }} — Reorder soon
                        </div>
                    </div>
                    <a href="{{ route('inventory.index') }}" style="flex-shrink:0;">
                        <svg class="hd-ic-ico" fill="none" stroke="var(--c-amber)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    </a>
                </div>
            </div>
        </div>
        @endforeach

        @php $invAlertCount = $criticalAlerts->where('type','inventory')->count(); @endphp
        @if($invAlertCount > 4)
        <a href="{{ route('inventory.index') }}" class="hd-view-all">+ {{ $invAlertCount - 4 }} more items →</a>
        @endif
    </div>

</div>{{-- /hd-board --}}
</div>{{-- /hd-board-wrap --}}

{{-- ══ BOTTOM ROW: MARKETING + FAILURES + QUICK ACTIONS ══════════════════ --}}
<div class="hd-bottom-row">

    {{-- Marketing --}}
    <div class="hd-bottom-col">
        <div class="hd-section-hdr">
            <span>
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:middle;margin-right:.3rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                Marketing – What to Post
            </span>
            <span class="hd-section-hdr-count">4</span>
        </div>
        <div class="hd-card">
            <div class="hd-mc">
                <div class="hd-mc-ico hd-mc-ig">📷</div>
                <div class="hd-mc-body">
                    <div class="hd-mc-title">Instagram – Before/After Smile Makeover</div>
                    <div class="hd-mc-time">Today 11:00 AM</div>
                </div>
                <svg class="hd-mc-check" width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div class="hd-mc">
                <div class="hd-mc-ico hd-mc-fb">f</div>
                <div class="hd-mc-body">
                    <div class="hd-mc-title">Facebook Post – Dental Implant Awareness</div>
                    <div class="hd-mc-time">Tomorrow 10:00 AM</div>
                </div>
            </div>
            <div class="hd-mc">
                <div class="hd-mc-ico hd-mc-gg">G</div>
                <div class="hd-mc-body">
                    <div class="hd-mc-title">Google Post – Mon Offer (Teeth Whitening)</div>
                    <div class="hd-mc-time">22 May, 09:00 AM</div>
                </div>
            </div>
            <div class="hd-mc">
                <div class="hd-mc-ico hd-mc-wa">W</div>
                <div class="hd-mc-body">
                    <div class="hd-mc-title">WhatsApp Broadcast – Scaling Offer</div>
                    <div class="hd-mc-time">23 May, 10:00 AM</div>
                </div>
            </div>
            <a href="#" class="hd-view-all">+ 2 more content ideas</a>
        </div>
    </div>

    {{-- Failures / Maintenance --}}
    <div class="hd-bottom-col">
        <div class="hd-section-hdr">
            <span>
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:middle;margin-right:.3rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                Failures / Maintenance
            </span>
            <span class="hd-section-hdr-count">{{ $criticalAlerts->count() }}</span>
        </div>
        <div class="hd-card">
            @forelse($criticalAlerts as $alert)
            <div class="hd-fc">
                <div class="hd-fc-ico hd-fc-ico-{{ $alert['level'] === 'error' ? 'high' : ($alert['level'] === 'warning' ? 'medium' : 'low') }}">
                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div class="hd-fc-body">
                    <div class="hd-fc-title">{{ $alert['message'] }}</div>
                    @if(!empty($alert['detail']))
                    <div class="hd-fc-desc">{{ $alert['detail'] }}</div>
                    @endif
                    <div class="hd-fc-footer">
                        <span class="hd-fc-reported">Reported {{ now()->subHours(rand(1,24))->diffForHumans() }}</span>
                        <span class="hd-badge hd-b-{{ $alert['level'] === 'error' ? 'high' : 'medium' }}">{{ $alert['level'] === 'error' ? 'High' : 'Medium' }}</span>
                    </div>
                </div>
            </div>
            @empty
            <div class="hd-fc">
                <div class="hd-fc-body" style="text-align:center;padding:.5rem 0;color:var(--c-muted);font-size:.78rem;">
                    ✓ No active issues
                </div>
            </div>
            @endforelse
            <a href="#" class="hd-view-all">+ Add New Issue</a>
        </div>
    </div>

    {{-- Quick Actions --}}
    <div class="hd-bottom-col">
        <div class="hd-section-hdr">Quick Actions</div>
        <div class="hd-qa-panel">
            <div class="hd-qa-grid">
                <a href="#" class="hd-qa-btn" @click.prevent="$dispatch('open-add-patient')">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    <span>Add Patient</span>
                </a>
                <a href="#" class="hd-qa-btn" @click.prevent="window.dispatchEvent(new CustomEvent('open-booking-modal'))">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>Add Appointment</span>
                </a>
                <a href="#" class="hd-qa-btn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span>Add Task</span>
                </a>
                <a href="#" class="hd-qa-btn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <span>Take Payment</span>
                </a>
                <a href="#" class="hd-qa-btn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <span>Send Estimate</span>
                </a>
                <a href="#" class="hd-qa-btn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span>Send Recall</span>
                </a>
                <a href="#" class="hd-qa-btn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    <span>Call Patient</span>
                </a>
                <a href="#" class="hd-qa-btn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    <span>Internal Note</span>
                </a>
            </div>
        </div>
    </div>

</div>{{-- /hd-bottom-row --}}

{{-- ══ FOOTER BAR ══════════════════════════════════════════════════════════ --}}
<div class="hd-footer-bar">
    <span>
        <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        All times are local
        &nbsp;•&nbsp;
        Data auto-syncs across all modules
    </span>
    <span>
        <span class="hd-live-dot"></span>
        Last synced:
        <span x-data
              x-text="new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'})"
              x-init="setInterval(()=>$el.textContent=new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit'}),30000)">
        </span>
    </span>
</div>

{{-- Modals --}}
<script>
window.__APPT_DATA = {
    csrfToken: '{{ csrf_token() }}',
    routes: {
        store:         '{{ route("appointments.store") }}',
        checkConflict: '{{ route("appointments.check.conflict") }}',
        patientSearch: '/patients/search'
    }
};
</script>
@include('appointments._modal')
@include('partials.add-patient-modal')

</div>{{-- /hd hd-escape --}}
@endsection