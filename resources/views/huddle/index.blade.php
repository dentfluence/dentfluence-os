@extends('layouts.app')

@section('title', 'Daily Huddle — ' . $today->format('l, d F Y'))

@section('head-extra')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
@endsection

@push('styles')
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
    transition: box-shadow .15s, border-color .15s;
    cursor: pointer;
}
.hd-stat-pill:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
    border-color: #c7c7d4;
    text-decoration: none;
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
    padding: 0 1.2rem 1rem;
    overflow-x: auto;
    overflow-y: hidden;
    height: calc(100vh - 208px);
    scrollbar-width: thin;
    scrollbar-color: #d1d5db transparent;
}
.hd-board {
    display: flex;
    gap: .65rem;
    align-items: stretch;
    min-width: max-content;
    height: 100%;
}
.hd-col {
    width: 220px;
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
    min-height: 0;
    background: var(--c-white);
    border: 1.5px solid var(--c-border);
    border-radius: 12px;
    overflow: hidden;
}
.hd-col-wide { width: 245px; }

/* ─── Column Header ────────────────────────────────────────── */
.hd-col-hdr {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .45rem .7rem;
    border-bottom: 1px solid var(--c-border);
    flex-shrink: 0;
    background: var(--c-white);
}

/* ─── Column Scrollable Body ───────────────────────────────── */
.hd-col-body {
    flex: 1;
    min-height: 0;
    overflow-y: auto;
    padding: .5rem;
    display: block;        /* block avoids flex-shrink collapsing cards */
    scrollbar-width: thin;
    scrollbar-color: #d8dae0 transparent;
}
.hd-col-body::-webkit-scrollbar { width: 4px; }
.hd-col-body::-webkit-scrollbar-track { background: transparent; }
.hd-col-body::-webkit-scrollbar-thumb { background: #d8dae0; border-radius: 2px; }
/* spacing between items inside the scrollable body */
.hd-col-body > * + * { margin-top: .45rem; }
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
    cursor: pointer;
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

/* ─── Comms: Collapsible Section Headers ───────────────────── */
.hd-cs-hdr {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .32rem .45rem;
    border-radius: 7px;
    cursor: pointer;
    user-select: none;
    transition: background .12s;
    margin-bottom: .2rem;
}
.hd-cs-hdr:hover { background: var(--c-bg); }
.hd-cs-left  { display: flex; align-items: center; gap: .35rem; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; }
.hd-cs-count { font-size: .6rem; font-weight: 700; padding: .1rem .35rem; border-radius: 999px; }
.hd-cs-chevron { transition: transform .2s; flex-shrink: 0; }
.hd-cs-chevron.open { transform: rotate(90deg); }
.hd-cs-body { overflow: hidden; display: flex; flex-direction: column; gap: .35rem; }

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

/* ─── Communication Alert Pill (urgent state) ──────────────── */
.hd-stat-pill--urgent {
    border-color: #fca5a5;
    background: #fff8f8;
}
/* (kept for any other partials that may still reference these) */
.comm-huddle-alerts {
    background: var(--c-white);
    border: 1px solid var(--c-border);
    border-radius: 12px;
    padding: .85rem 1rem;
}
.comm-huddle-alerts__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .75rem;
}
.comm-huddle-alerts__title-wrap {
    display: flex;
    align-items: center;
    gap: .45rem;
}
.comm-huddle-alerts__icon { color: var(--c-accent); display: flex; }
.comm-huddle-alerts__title {
    font-size: .8rem;
    font-weight: 700;
    color: var(--c-text);
    margin: 0;
}
.comm-huddle-alerts__view-all {
    font-size: .72rem;
    color: var(--c-accent);
    font-weight: 500;
    text-decoration: none;
    transition: opacity .12s;
}
.comm-huddle-alerts__view-all:hover { opacity: .75; }

/* Alert Cards Grid */
.comm-huddle-alerts__grid {
    display: flex;
    gap: .55rem;
    flex-wrap: wrap;
    margin-bottom: .75rem;
}
.comm-alert-card {
    background: var(--c-bg);
    border: 1px solid var(--c-border);
    border-radius: 9px;
    padding: .6rem .8rem;
    min-width: 110px;
    flex: 1;
    transition: box-shadow .12s;
}
.comm-alert-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,.07); }
.comm-alert-card--urgent {
    border-color: #fca5a5;
    background: #fff8f8;
}
.comm-alert-card__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .28rem;
}
.comm-alert-card__emoji { font-size: 1rem; line-height: 1; }
.comm-alert-card__count {
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1;
}
.comm-alert-card__title {
    font-size: .7rem;
    font-weight: 600;
    color: var(--c-text);
    margin-bottom: .18rem;
    line-height: 1.3;
}
.comm-alert-card__names {
    font-size: .63rem;
    color: var(--c-muted);
    margin-bottom: .28rem;
    line-height: 1.35;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.comm-alert-card__more {
    font-size: .6rem;
    color: var(--c-accent);
    font-weight: 600;
}
.comm-alert-card__action {
    display: inline-block;
    font-size: .62rem;
    font-weight: 600;
    font-family: inherit;
    padding: .18rem .52rem;
    border-radius: 5px;
    background: transparent;
    cursor: pointer;
    transition: background .12s;
    white-space: nowrap;
}
.comm-alert-card__action:hover { background: rgba(0,0,0,.05); }

/* Summary Bar */
.comm-huddle-alerts__summary-bar {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding-top: .65rem;
    border-top: 1px solid var(--c-border);
    flex-wrap: wrap;
}
.comm-summary-stat {
    display: flex;
    align-items: baseline;
    gap: .28rem;
}
.comm-summary-stat__num {
    font-size: .95rem;
    font-weight: 700;
    line-height: 1;
}
.comm-summary-stat__num--red    { color: var(--c-red); }
.comm-summary-stat__num--blue   { color: var(--c-blue); }
.comm-summary-stat__num--purple { color: var(--c-accent2); }
.comm-summary-stat__num--green  { color: var(--c-green); }
.comm-summary-stat__num--orange { color: var(--c-amber); }
.comm-summary-stat__label {
    font-size: .65rem;
    color: var(--c-muted);
    font-weight: 500;
}
.comm-summary-divider {
    width: 1px;
    height: 14px;
    background: var(--c-border);
    flex-shrink: 0;
}
</style>
@endpush

@push('scripts')
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
                openAppointmentModal('appointment', '{{ $today->toDateString() }}');
            });
        }
    }));


    // ── Huddle Comm List Widget ─────────────────────────────────────────────
    Alpine.data('huddleCommListWidget', () => ({
    items:   window.__huddleCommList ?? [],
    pushing: false,
    pushed:  false,

    get reminders()      { return this.items.filter(i => i.comm_type === 'reminder'); },
    get followUps()      { return this.items.filter(i => i.comm_type === 'follow_up'); },
    get specialDayCalls(){ return this.items.filter(i => ['birthday','anniversary','special_day'].includes(i.comm_type)); },
    get labVendorComms() { return this.items.filter(i => ['lab','vendor'].includes(i.comm_type)); },
    get otherComms()     { return this.items.filter(i => !['reminder','follow_up','prm','birthday','anniversary','special_day','lab','vendor'].includes(i.comm_type)); },
    get prmComms()       { return this.items.filter(i => i.comm_type === 'prm'); },
    get selectedCount()  { return this.items.filter(i => i.selected).length; },

    toggle(id) {
        const item = this.items.find(i => i.id === id);
        if (item) item.selected = !item.selected;
    },
    selectAll(type) {
        this.items.filter(i => i.comm_type === type).forEach(i => i.selected = true);
    },
    deselectAll(type) {
        this.items.filter(i => i.comm_type === type).forEach(i => i.selected = false);
    },
    async pushToCommList() {
        // Only push reminders and follow-ups — PRM items are already in the queue
        const selected = this.items.filter(i => i.selected && i.comm_type !== 'prm');
        if (!selected.length) return;
        this.pushing = true;
        try {
            const res = await fetch(window.__huddleCommPushUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({
                    items: selected.map(i => ({
                        patient_id: i.patient_id,
                        comm_type:  i.comm_type,
                        note:       i.note,
                    })),
                }),
            });
            if (res.ok) {
                this.pushed = true;
                selected.forEach(i => i.pushed = true);
            }
        } finally {
            this.pushing = false;
        }
    },
    }));

}); // end alpine:init
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
        <a href="{{ route('huddle.index') }}" class="hd-nav-tab active">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
            Daily Huddle
        </a>
        <a href="{{ route('huddle.report', ['period' => 'week']) }}" class="hd-nav-tab">
            <svg width="12" height="12" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            Weekly Report
        </a>
        <a href="{{ route('huddle.report', ['period' => 'month']) }}" class="hd-nav-tab">Monthly Report</a>
        <a href="{{ route('huddle.report', ['period' => 'quarter']) }}" class="hd-nav-tab">Quarterly Report</a>
        <a href="{{ route('huddle.report', ['period' => 'year']) }}" class="hd-nav-tab">Annual Report</a>
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
    </div>
</div>

{{-- ══ GREETING + DATE ══════════════════════════════════════════════════════ --}}
<div class="hd-header">
    <div>
        <div class="hd-greeting">
            Good Morning, {{ ucfirst(strtok(auth()->user()->name, ' ')) }}!
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

{{-- ══ TODAY'S ACTIONS SNAPSHOT (PRE · Workstream E, slice E4) ═══════════════ --}}
@isset($todaySnapshot)
<div style="margin:0 0 14px;">
    @include('relationship.today._snapshot', ['snapshot' => $todaySnapshot])
</div>
@endisset

{{-- ══ STATS STRIP ══════════════════════════════════════════════════════════ --}}
<div class="hd-stats-strip">

    {{-- Today's Appointments --}}
    <a href="{{ route('appointments.index', ['date' => today()->toDateString()]) }}" class="hd-stat-pill" style="text-decoration:none;color:inherit;">
        <div>
            <div class="hd-stat-val">{{ $todaysAppointments->count() }}</div>
            <div class="hd-stat-label">Today's Appointments</div>
            <div class="hd-stat-sub">+3 vs yesterday</div>
        </div>
    </a>

    {{-- Today's Calls --}}
    <a href="{{ route('communication.manager.index') }}" class="hd-stat-pill" style="text-decoration:none;color:inherit;">
        <div>
            <div class="hd-stat-val">{{ $commList->count() }}</div>
            <div class="hd-stat-label">Today's Calls</div>
            <div class="hd-stat-sub">{{ $commList->where('status','pending')->count() }} pending</div>
        </div>
    </a>

    {{-- Collections --}}
    <a href="{{ route('analytics.index') }}" class="hd-stat-pill" style="text-decoration:none;color:inherit;">
        <div>
            <div class="hd-stat-val">—</div>
            <div class="hd-stat-label">Collections (Today)</div>
            <div class="hd-stat-sub" style="color:var(--c-muted);">34% of target</div>
        </div>
    </a>

    {{-- Pending Tasks --}}
    <a href="{{ route('tasks.index') }}" class="hd-stat-pill" style="text-decoration:none;color:inherit;">
        <div>
            <div class="hd-stat-val">{{ $myTasks->count() }}</div>
            <div class="hd-stat-label">Pending Tasks</div>
            <div class="hd-stat-sub" style="color:var(--c-muted);">Across team</div>
        </div>
    </a>

    {{-- Critical Alerts --}}
    <a href="{{ route('notifications.index') }}" class="hd-stat-pill" style="text-decoration:none;color:inherit;">
        <div>
            <div class="hd-stat-val" style="color:var(--c-red);">{{ $criticalAlerts->count() }}</div>
            <div class="hd-stat-label">Critical Alerts</div>
            @if($criticalAlerts->isNotEmpty())
                <div class="hd-stat-sub warn">Needs attention</div>
            @else
                <div class="hd-stat-sub">All clear</div>
            @endif
        </div>
    </a>

    {{-- Communication Alert Pills --}}
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
        <div class="hd-col-body">

        @forelse($todaysAppointments as $appt)
        @php
            $hdDocHex = $appt->doctor_color ?? '#94a3b8';
            [$r,$g,$b] = sscanf($hdDocHex, '#%02x%02x%02x');
        @endphp
        <div class="hd-card"
             style="background:rgba({{ $r }},{{ $g }},{{ $b }},0.07);border-left:3px solid {{ $hdDocHex }};"
             @click="window.location.href='{{ route('patients.show', $appt->patient_id) }}'"
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
                          @click.stop="cycleStatus()"
                          title="Click to advance status"></span>
                    <span class="hd-pfc-arrived" x-show="status === 'checkin'">✓ Arrived</span>
                </div>

                {{-- Inline instruction note --}}
                <div style="margin-top:.3rem;">
                    <div x-show="!editing" @click.stop="editing=true" class="hd-pfc-instr" x-text="instruction || ''"></div>
                    <template x-if="editing">
                        <div @click.stop>
                            <input x-model="instruction"
                                   @keydown.enter="save()"
                                   @keydown.escape="instruction=original;editing=false"
                                   class="hd-pfc-instr-inp"
                                   placeholder="Add note…"
                                   x-init="$el.focus()"
                                   :disabled="saving">
                            <span @click.stop="save()" style="font-size:.62rem;color:var(--c-accent);cursor:pointer;" x-text="saving ? 'Saving…' : 'Save'"></span>
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
        <div class="hd-card" style="border-left:3px solid var(--c-green);" onclick="window.location.href='{{ route('patients.show', $tv->patient_id) }}'">
            <div class="hd-pfc">
                <div class="hd-pfc-top">
                    <span class="hd-pfc-time" style="background:#f0fdf4;color:var(--c-green);">
                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </span>
                    <div style="flex:1;min-width:0;padding:0 .3rem;">
                        <div style="font-size:.72rem;color:var(--c-muted);font-weight:500;">
                            {{ $tv->treatment_name ?? ucfirst(str_replace('_',' ',$tv->visit_type ?? 'Treatment')) }}
                        </div>
                        <a href="{{ route('patients.show', $tv->patient_id) }}" class="hd-pfc-name" @click.stop>{{ $tv->patient->name ?? '—' }}</a>
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
        <div class="hd-card" style="border-left:3px solid var(--c-accent2);" onclick="window.location.href='{{ route('patients.show', $tc->patient_id) }}'">
            <div class="hd-pfc">
                <div class="hd-pfc-top">
                    <span class="hd-pfc-time" style="background:#f5f3ff;color:var(--c-accent2);">
                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </span>
                    <div style="flex:1;min-width:0;padding:0 .3rem;">
                        <div style="font-size:.72rem;color:var(--c-muted);font-weight:500;">
                            {{ $tc->visit_type ? ucwords(str_replace('_',' ',$tc->visit_type)) : 'Consultation' }}
                        </div>
                        <a href="{{ route('patients.show', $tc->patient_id) }}" class="hd-pfc-name" @click.stop>{{ $tc->patient->name ?? '—' }}</a>
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

        <a href="#" class="hd-view-all" @click.prevent="openAppointmentModal('appointment', '{{ $today->toDateString() }}')">
            + Add Appointment
        </a>

        </div>{{-- /hd-col-body --}}
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
        <div class="hd-col-body">

        @forelse($yesterdaysAppointments as $yAppt)
        <div class="hd-card" x-data="{ calling: false, called: false }"
             @click="window.location.href='{{ route('patients.show', $yAppt->patient_id) }}'"
             style="cursor:pointer;">
            <div class="hd-pfc">

                {{-- ── Row 1: Patient name + visit type badge ── --}}
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:.4rem;">
                    <div style="min-width:0;">
                        <a href="{{ route('patients.show', $yAppt->patient_id) }}" class="hd-pfc-name" style="font-size:.8rem;">
                            {{ $yAppt->patient->name ?? '—' }}
                            @if(!empty($yAppt->patient->medical_alert))
                                <span class="hd-pfc-star" title="{{ $yAppt->patient->medical_alert }}" style="font-size:.65rem;">★</span>
                            @endif
                        </a>
                        <div style="font-size:.66rem;color:var(--c-muted);margin-top:.1rem;display:flex;align-items:center;gap:.3rem;">
                            <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            {{ $yAppt->doctor->doctor_name ?? '—' }}
                        </div>
                    </div>
                    <span style="font-size:.62rem;font-weight:600;color:var(--c-accent);background:#ede9fe;border-radius:4px;padding:.15rem .4rem;white-space:nowrap;flex-shrink:0;">
                        {{ $yAppt->type ? ucfirst(str_replace('_',' ',$yAppt->type)) : 'Consult' }}
                    </span>
                </div>

                {{-- ── Row 2: Visit summary ── --}}
                <div style="margin-top:.45rem;">
                    @if(in_array($yAppt->appointment_status, ['cancelled', 'no_show']))
                        <span class="hd-badge hd-b-{{ $yAppt->appointment_status }}" style="font-size:.64rem;">
                            {{ str_replace('_',' ', ucfirst($yAppt->appointment_status)) }}
                        </span>
                    @elseif($yAppt->visit_logged)
                        <div style="display:flex;align-items:center;gap:.3rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:5px;padding:.25rem .4rem;">
                            <svg width="10" height="10" fill="none" stroke="#16a34a" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            <span style="font-size:.65rem;font-weight:600;color:#16a34a;">Visit Logged</span>
                            @if(($yAppt->visit_source ?? null) === 'treatment_visit')
                                <span style="font-size:.62rem;color:#16a34a;margin-left:.1rem;">· Treatment</span>
                            @elseif($yAppt->consultation_status === 'completed')
                                <span style="font-size:.62rem;color:#16a34a;margin-left:.1rem;">· Done</span>
                            @elseif($yAppt->consultation_status)
                                <span style="font-size:.62rem;color:#d97706;margin-left:.1rem;">· Draft</span>
                            @endif
                        </div>
                        {{-- Short clinical summary --}}
                        @if($yAppt->chief_complaint || $yAppt->primary_diagnosis)
                        <div style="font-size:.67rem;color:var(--c-muted);margin-top:.25rem;line-height:1.4;background:var(--c-bg-alt, #f8f8f9);border-radius:4px;padding:.2rem .35rem;">
                            @if($yAppt->chief_complaint)
                                <span style="font-weight:600;color:var(--c-text);">CC:</span> {{ Str::limit($yAppt->chief_complaint, 50) }}<br>
                            @endif
                            @if($yAppt->primary_diagnosis)
                                <span style="font-weight:600;color:var(--c-text);">Dx:</span> {{ Str::limit($yAppt->primary_diagnosis, 50) }}
                            @endif
                        </div>
                        @endif
                    @else
                        <div style="display:flex;align-items:center;gap:.3rem;background:#fef2f2;border:1px solid #fecaca;border-radius:5px;padding:.25rem .4rem;">
                            <svg width="10" height="10" fill="none" stroke="#dc2626" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                            <span style="font-size:.65rem;font-weight:600;color:#dc2626;">Visit Not Logged</span>
                        </div>
                    @endif
                </div>

                {{-- ── Row 3: Next appointment ── --}}
                <div style="display:flex;align-items:center;gap:.3rem;margin-top:.4rem;font-size:.66rem;">
                    @if($yAppt->next_appt)
                        <svg width="10" height="10" fill="none" stroke="var(--c-accent2)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span style="color:var(--c-accent2);font-weight:600;">Next:</span>
                        <span style="color:var(--c-text);">
                            {{ \Carbon\Carbon::parse($yAppt->next_appt->appointment_date)->format('d M') }}
                            @if($yAppt->next_appt->appointment_time)
                                · {{ \Carbon\Carbon::parse($yAppt->next_appt->appointment_time)->format('H:i') }}
                            @endif
                            @if($yAppt->next_appt->treatment_name)
                                · {{ Str::limit($yAppt->next_appt->treatment_name, 22) }}
                            @elseif($yAppt->next_appt->type)
                                · {{ ucfirst(str_replace('_',' ',$yAppt->next_appt->type)) }}
                            @endif
                        </span>
                    @else
                        <div style="display:flex;align-items:center;justify-content:space-between;width:100%;gap:.4rem;">
                            <div style="display:flex;align-items:center;gap:.3rem;">
                                <svg width="10" height="10" fill="none" stroke="var(--c-muted)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span style="color:var(--c-muted);">No next appointment</span>
                            </div>
                            <div style="display:flex;align-items:center;gap:.3rem;">
                                <button
                                    @click.stop.prevent="openAppointmentModal('appointment', '{{ $today->addDay()->toDateString() }}', {{ $yAppt->patient_id }})"
                                    style="font-size:.6rem;font-weight:700;color:var(--c-green);background:#f0fdf4;border:1px solid #86efac;border-radius:999px;padding:.15rem .5rem;cursor:pointer;white-space:nowrap;transition:background .12s;font-family:inherit;"
                                    onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='#f0fdf4'"
                                >+ Book</button>
                                <button
                                    @click.stop="$dispatch('open-create-task', { patient_id: {{ $yAppt->patient_id }}, patient_name: '{{ addslashes($yAppt->patient->name ?? '') }}' }); window.dispatchEvent(new CustomEvent('open-create-task', { detail: { patient_id: {{ $yAppt->patient_id }}, patient_name: '{{ addslashes($yAppt->patient->name ?? '') }}' } }))"
                                    style="font-size:.6rem;font-weight:700;color:var(--c-accent);background:#f5f0ff;border:1px solid #c4b5fd;border-radius:999px;padding:.15rem .5rem;cursor:pointer;white-space:nowrap;transition:background .12s;font-family:inherit;"
                                    onmouseover="this.style.background='#ede9fe'" onmouseout="this.style.background='#f5f0ff'"
                                >+ Task</button>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- ── Row 4: Next work (from finishing notes) ── --}}
                @if($yAppt->finishing_notes)
                <div style="display:flex;align-items:flex-start;gap:.3rem;margin-top:.3rem;font-size:.66rem;color:var(--c-muted);">
                    <svg width="10" height="10" fill="none" stroke="var(--c-muted)" viewBox="0 0 24 24" style="margin-top:.1rem;flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span><span style="font-weight:600;color:var(--c-text);">Next work:</span> {{ Str::limit($yAppt->finishing_notes, 55) }}</span>
                </div>
                @endif

                {{-- ── Row 5: Call button ── --}}
                <div style="margin-top:.5rem;border-top:1px solid var(--c-border);padding-top:.4rem;">
                    <button
                        x-show="!called"
                        :disabled="calling"
                        @click.stop.prevent="
                            calling = true;
                            fetch('{{ route('huddle.comms.push') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                },
                                body: JSON.stringify({ items: [{ patient_id: {{ $yAppt->patient_id }}, comm_type: 'follow_up', note: 'Follow-up call for {{ addslashes($yAppt->patient->name ?? '') }}' }] })
                            }).then(r => { if(r.ok) { calling = false; called = true; } else { calling = false; } })
                        "
                        style="width:100%;text-align:center;font-size:.67rem;font-weight:600;color:var(--c-accent2);background:#f5f3ff;border:1px solid #c4b5fd;border-radius:5px;padding:.28rem .5rem;cursor:pointer;transition:background .15s;"
                        onmouseover="this.style.background='#ede9fe'" onmouseout="this.style.background='#f5f3ff'"
                    >
                        <span style="display:inline-flex;align-items:center;justify-content:center;gap:.35rem;">
                            <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="flex-shrink:0;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            <span x-text="calling ? 'Adding…' : 'Add to Call List'"></span>
                        </span>
                    </button>
                    <div x-show="called" style="text-align:center;font-size:.65rem;font-weight:600;color:#16a34a;">
                        ✓ Added to call list
                    </div>
                </div>

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
        <div class="hd-card" style="border-left:3px solid var(--c-green);" onclick="window.location.href='{{ route('patients.show', $ytv->patient_id) }}'">
            <div class="hd-pfc">
                <div class="hd-pfc-top">
                    <span class="hd-pfc-time" style="background:#f0fdf4;color:var(--c-green);">
                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </span>
                    <div style="flex:1;min-width:0;padding:0 .3rem;">
                        <div style="font-size:.72rem;color:var(--c-muted);font-weight:500;">
                            {{ $ytv->treatment_name ?? ucfirst(str_replace('_',' ',$ytv->visit_type ?? 'Treatment')) }}
                        </div>
                        <a href="{{ route('patients.show', $ytv->patient_id) }}" class="hd-pfc-name" onclick="event.stopPropagation()">{{ $ytv->patient->name ?? '—' }}</a>
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
        <div class="hd-card" style="border-left:3px solid var(--c-accent2);" onclick="window.location.href='{{ route('patients.show', $yc->patient_id) }}'">
            <div class="hd-pfc">
                <div class="hd-pfc-top">
                    <span class="hd-pfc-time" style="background:#f5f3ff;color:var(--c-accent2);">
                        <svg width="10" height="10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </span>
                    <div style="flex:1;min-width:0;padding:0 .3rem;">
                        <div style="font-size:.72rem;color:var(--c-muted);font-weight:500;">
                            {{ $yc->visit_type ? ucwords(str_replace('_',' ',$yc->visit_type)) : 'Consultation' }}
                        </div>
                        <a href="{{ route('patients.show', $yc->patient_id) }}" class="hd-pfc-name" onclick="event.stopPropagation()">{{ $yc->patient->name ?? '—' }}</a>
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

        </div>{{-- /hd-col-body --}}
    </div>

    {{-- ── COL 3: COMMS LIST ── --}}
    {{-- Isolate JSON from HTML attribute to avoid quote-breaking issues --}}
    <script>
        window.__huddleCommList = {!! json_encode($commList->values(), JSON_UNESCAPED_UNICODE) !!};
        window.__huddleCommPushUrl = '{{ route('huddle.comms.push') }}';
    </script>

    <div class="hd-col" x-data="huddleCommListWidget()">
        <div class="hd-col-hdr">
            <div class="hd-col-title">
                <a href="{{ route('communication.manager.index') }}" title="Open Communication List"
                   style="display:flex;align-items:center;gap:.3rem;color:inherit;text-decoration:none;"
                   onmouseover="this.style.opacity='.7'" onmouseout="this.style.opacity='1'">
                    <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                    Comms List
                </a>
            </div>
            <div style="display:flex;align-items:center;gap:.3rem;">
                <span class="hd-col-count" x-text="items.length"></span>
            </div>
        </div>
        <div class="hd-col-body">

        {{-- Empty state --}}
        <template x-if="items.length === 0">
            <div class="hd-empty-col">No reminders, follow-ups, or pending comms for today.</div>
        </template>

        {{-- ════ MACRO for a comm card ════
             Reused across all sections — pass item + checkColor --}}

        {{-- ── SECTION 1: REMINDERS ── --}}
        <div x-data="{ open: true }">
            <div class="hd-cs-hdr" @click="open = !open"
                 style="background: open ? '#eff6ff22' : '';">
                <div class="hd-cs-left" style="color:var(--c-blue);">
                    <span>Reminders</span>
                    <span class="hd-cs-count" style="background:#dbeafe;color:var(--c-blue);" x-text="reminders.length"></span>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <template x-if="open && reminders.length > 0">
                        <div style="display:flex;gap:.3rem;" @click.stop>
                            <button @click="selectAll('reminder')"   style="font-size:.6rem;color:var(--c-blue);background:none;border:none;cursor:pointer;padding:0;font-weight:600;">All</button>
                            <button @click="deselectAll('reminder')" style="font-size:.6rem;color:var(--c-muted);background:none;border:none;cursor:pointer;padding:0;">None</button>
                        </div>
                    </template>
                    <svg class="hd-cs-chevron" :class="open ? 'open' : ''" width="11" height="11" fill="none" stroke="var(--c-muted)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </div>
            </div>
            <div class="hd-cs-body" x-show="open">
                <template x-if="reminders.length === 0">
                    <div style="font-size:.68rem;color:var(--c-muted);text-align:center;padding:.3rem 0;">No reminders today</div>
                </template>
                <template x-for="item in reminders" :key="item.id">
                    <div class="hd-card" :style="item.pushed ? 'opacity:.5;' : ''" style="cursor:pointer;" @click="toggle(item.id)">
                        <div class="hd-cc" style="gap:.5rem;">
                            <div style="flex-shrink:0;width:15px;height:15px;border-radius:4px;border:2px solid;display:flex;align-items:center;justify-content:center;transition:.15s;"
                                 :style="item.selected ? 'background:var(--c-blue);border-color:var(--c-blue);' : 'background:#fff;border-color:#d1d5db;'">
                                <svg x-show="item.selected" width="8" height="8" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="hd-cc-ico hd-cc-ico-call" style="flex-shrink:0;">
                                <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </div>
                            <div class="hd-cc-body">
                                <div class="hd-cc-type" x-text="item.label"></div>
                                <div class="hd-cc-desc" x-text="item.patient_name"></div>
                                <div class="hd-cc-footer"><span class="hd-cc-by" x-text="item.phone"></span><template x-if="item.pushed"><span style="font-size:.62rem;color:var(--c-green);font-weight:600;">✓ Added</span></template></div>
                                <div style="font-size:.63rem;color:var(--c-muted);margin-top:.1rem;" x-text="item.note"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ── SECTION 2: YESTERDAY'S FOLLOW-UPS ── --}}
        <div x-data="{ open: true }">
            <div class="hd-cs-hdr" @click="open = !open">
                <div class="hd-cs-left" style="color:var(--c-green);">
                    <span>Yesterday's Follow-ups</span>
                    <span class="hd-cs-count" style="background:#dcfce7;color:var(--c-green);" x-text="followUps.length"></span>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <template x-if="open && followUps.length > 0">
                        <div style="display:flex;gap:.3rem;" @click.stop>
                            <button @click="selectAll('follow_up')"   style="font-size:.6rem;color:var(--c-green);background:none;border:none;cursor:pointer;padding:0;font-weight:600;">All</button>
                            <button @click="deselectAll('follow_up')" style="font-size:.6rem;color:var(--c-muted);background:none;border:none;cursor:pointer;padding:0;">None</button>
                        </div>
                    </template>
                    <svg class="hd-cs-chevron" :class="open ? 'open' : ''" width="11" height="11" fill="none" stroke="var(--c-muted)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </div>
            </div>
            <div class="hd-cs-body" x-show="open">
                <template x-if="followUps.length === 0">
                    <div style="font-size:.68rem;color:var(--c-muted);text-align:center;padding:.3rem 0;">No follow-ups</div>
                </template>
                <template x-for="item in followUps" :key="item.id">
                    <div class="hd-card" :style="item.pushed ? 'opacity:.5;' : ''" style="cursor:pointer;" @click="toggle(item.id)">
                        <div class="hd-cc" style="gap:.5rem;">
                            <div style="flex-shrink:0;width:15px;height:15px;border-radius:4px;border:2px solid;display:flex;align-items:center;justify-content:center;transition:.15s;"
                                 :style="item.selected ? 'background:var(--c-green);border-color:var(--c-green);' : 'background:#fff;border-color:#d1d5db;'">
                                <svg x-show="item.selected" width="8" height="8" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="hd-cc-ico hd-cc-ico-msg" style="flex-shrink:0;">
                                <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                            </div>
                            <div class="hd-cc-body">
                                <div class="hd-cc-type" x-text="item.label"></div>
                                <div class="hd-cc-desc" x-text="item.patient_name"></div>
                                <div class="hd-cc-footer"><span class="hd-cc-by" x-text="item.phone"></span><template x-if="item.pushed"><span style="font-size:.62rem;color:var(--c-green);font-weight:600;">✓ Added</span></template></div>
                                <div style="font-size:.63rem;color:var(--c-muted);margin-top:.1rem;" x-text="item.note"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ── SECTION 3: SPECIAL DAY CALLS ── --}}
        <div x-data="{ open: true }" x-show="specialDayCalls.length > 0">
            <div class="hd-cs-hdr" @click="open = !open">
                <div class="hd-cs-left" style="color:#d97706;">
                    <span>Special Day Calls</span>
                    <span class="hd-cs-count" style="background:#fef3c7;color:#d97706;" x-text="specialDayCalls.length"></span>
                </div>
                <svg class="hd-cs-chevron" :class="open ? 'open' : ''" width="11" height="11" fill="none" stroke="var(--c-muted)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </div>
            <div class="hd-cs-body" x-show="open">
                <template x-for="item in specialDayCalls" :key="item.id">
                    <div class="hd-card" :style="item.pushed ? 'opacity:.5;' : ''" style="cursor:pointer;" @click="toggle(item.id)">
                        <div class="hd-cc" style="gap:.5rem;">
                            <div style="flex-shrink:0;width:15px;height:15px;border-radius:4px;border:2px solid;display:flex;align-items:center;justify-content:center;transition:.15s;"
                                 :style="item.selected ? 'background:#d97706;border-color:#d97706;' : 'background:#fff;border-color:#d1d5db;'">
                                <svg x-show="item.selected" width="8" height="8" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="hd-cc-ico" style="flex-shrink:0;background:#fef3c7;">
                                <svg width="11" height="11" fill="none" stroke="#d97706" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15.546c-.523 0-1.046.151-1.5.454a2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-3 0 2.704 2.704 0 00-3 0 2.704 2.704 0 01-1.5-.454M9 6l3-3 3 3M12 3v4M9 10h.01M15 10h.01M12 10h.01"/></svg>
                            </div>
                            <div class="hd-cc-body">
                                <div class="hd-cc-type" x-text="item.label"></div>
                                <div class="hd-cc-desc" x-text="item.patient_name"></div>
                                <div class="hd-cc-footer"><span class="hd-cc-by" x-text="item.phone"></span></div>
                                <div style="font-size:.63rem;color:var(--c-muted);margin-top:.1rem;" x-text="item.note"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ── SECTION 4: LAB / VENDOR COMMS ── --}}
        <div x-data="{ open: true }" x-show="labVendorComms.length > 0">
            <div class="hd-cs-hdr" @click="open = !open">
                <div class="hd-cs-left" style="color:var(--c-teal);">
                    <span>Lab / Vendor</span>
                    <span class="hd-cs-count" style="background:#ecfeff;color:var(--c-teal);" x-text="labVendorComms.length"></span>
                </div>
                <svg class="hd-cs-chevron" :class="open ? 'open' : ''" width="11" height="11" fill="none" stroke="var(--c-muted)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </div>
            <div class="hd-cs-body" x-show="open">
                <template x-for="item in labVendorComms" :key="item.id">
                    <div class="hd-card" style="cursor:default;">
                        <div class="hd-cc" style="gap:.5rem;">
                            <div class="hd-cc-ico" style="flex-shrink:0;background:#ecfeff;">
                                <svg width="11" height="11" fill="none" stroke="var(--c-teal)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                            </div>
                            <div class="hd-cc-body">
                                <div class="hd-cc-type" x-text="item.label"></div>
                                <div class="hd-cc-desc" x-text="item.patient_name"></div>
                                <div class="hd-cc-footer"><span class="hd-cc-by" x-text="item.phone"></span></div>
                                <div style="font-size:.63rem;color:var(--c-muted);margin-top:.1rem;" x-text="item.note"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ── SECTION 5: OTHER ── --}}
        <div x-data="{ open: false }" x-show="otherComms.length > 0">
            <div class="hd-cs-hdr" @click="open = !open">
                <div class="hd-cs-left" style="color:var(--c-muted);">
                    <span>Other</span>
                    <span class="hd-cs-count" style="background:#e9ebf0;color:var(--c-muted);" x-text="otherComms.length"></span>
                </div>
                <svg class="hd-cs-chevron" :class="open ? 'open' : ''" width="11" height="11" fill="none" stroke="var(--c-muted)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </div>
            <div class="hd-cs-body" x-show="open">
                <template x-for="item in otherComms" :key="item.id">
                    <div class="hd-card" :style="item.pushed ? 'opacity:.5;' : ''" style="cursor:pointer;" @click="toggle(item.id)">
                        <div class="hd-cc" style="gap:.5rem;">
                            <div style="flex-shrink:0;width:15px;height:15px;border-radius:4px;border:2px solid;display:flex;align-items:center;justify-content:center;transition:.15s;"
                                 :style="item.selected ? 'background:var(--c-muted);border-color:var(--c-muted);' : 'background:#fff;border-color:#d1d5db;'">
                                <svg x-show="item.selected" width="8" height="8" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                            </div>
                            <div class="hd-cc-body">
                                <div class="hd-cc-type" x-text="item.label"></div>
                                <div class="hd-cc-desc" x-text="item.patient_name"></div>
                                <div class="hd-cc-footer"><span class="hd-cc-by" x-text="item.phone"></span></div>
                                <div style="font-size:.63rem;color:var(--c-muted);margin-top:.1rem;" x-text="item.note"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ── SECTION 6: PRM TASKS ── --}}
        <div x-data="{ open: true }">
            <div class="hd-cs-hdr" @click="open = !open">
                <div class="hd-cs-left" style="color:var(--c-accent);">
                    <span>PRM Tasks</span>
                    <span class="hd-cs-count" style="background:#ede9fe;color:var(--c-accent);" x-text="prmComms.length"></span>
                </div>
                <div style="display:flex;align-items:center;gap:.5rem;">
                    <a href="{{ route('communication.manager.index') }}"
                       style="font-size:.6rem;color:var(--c-accent);text-decoration:none;font-weight:600;" @click.stop>View all →</a>
                    <svg class="hd-cs-chevron" :class="open ? 'open' : ''" width="11" height="11" fill="none" stroke="var(--c-muted)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                </div>
            </div>
            <div class="hd-cs-body" x-show="open">
                <template x-if="prmComms.length === 0">
                    <div style="font-size:.68rem;color:var(--c-muted);text-align:center;padding:.3rem 0;">No pending PRM tasks</div>
                </template>
                <template x-for="item in prmComms" :key="item.id">
                    <div class="hd-card" style="cursor:default;">
                        <div class="hd-cc" style="gap:.5rem;">
                            <div style="flex-shrink:0;width:8px;height:8px;border-radius:50%;margin-top:4px;"
                                 :style="item.note && item.note.includes('Overdue') ? 'background:#ef4444;' : 'background:var(--c-accent);'"></div>
                            <div class="hd-cc-ico" style="flex-shrink:0;background:#f3f0ff;">
                                <svg width="11" height="11" fill="none" stroke="var(--c-accent)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
                            </div>
                            <div class="hd-cc-body">
                                <div class="hd-cc-type" x-text="item.label"></div>
                                <div class="hd-cc-desc" x-text="item.patient_name"></div>
                                <div class="hd-cc-footer"><span class="hd-cc-by" x-text="item.phone"></span></div>
                                <div style="font-size:.63rem;color:var(--c-muted);margin-top:.1rem;" x-text="item.note"></div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        {{-- ── ADD COMMUNICATION LINK ── --}}
        <a href="{{ route('communication.manager.log.form') }}"
           class="hd-view-all"
           style="background:#f5f0ff;border:1px solid #e0d4ff;border-radius:10px;text-decoration:none;display:block;text-align:center;color:var(--c-accent);font-weight:600;margin-top:.5rem;">
            + Add Communication
        </a>

        {{-- ── PUSH BUTTON ── --}}
        <template x-if="items.length > 0">
            <div style="margin-top:.5rem;">
                <template x-if="!pushed">
                    <button
                        @click.stop="pushToCommList()"
                        :disabled="pushing || selectedCount === 0"
                        :style="selectedCount === 0
                            ? 'width:100%;padding:.45rem;font-size:.73rem;font-weight:600;font-family:inherit;cursor:not-allowed;background:#f0ecff;border:1px solid #d8ccf5;border-radius:10px;text-align:center;color:var(--c-accent);opacity:.45;'
                            : 'width:100%;padding:.45rem;font-size:.73rem;font-weight:600;font-family:inherit;cursor:pointer;background:#edf9f0;border:1px solid #b7e8c6;border-radius:10px;text-align:center;color:#16a34a;opacity:1;'">
                        <span x-show="!pushing">Add <span x-text="selectedCount"></span> to Comm List →</span>
                        <span x-show="pushing">Adding…</span>
                    </button>
                </template>
                <template x-if="pushed">
                    <div style="text-align:center;padding:.45rem;font-size:.73rem;color:var(--c-green);font-weight:600;
                                background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
                        ✓ Added to Communication List
                    </div>
                </template>
            </div>
        </template>

        </div>{{-- /hd-col-body --}}
    </div>

    {{-- ── COL 4: TASKS ── --}}
    {{-- JSON in script tag — avoids double-quote breakage inside x-data="" attribute --}}
    <script>window.__huddleTasks = {!! json_encode($myTasks->values()) !!};</script>
    <div class="hd-col"
         x-data="{
            tasks: window.__huddleTasks,
            toggle(i) {
                if (this.tasks[i].done) return;
                this.tasks[i].done = true;
                fetch('/tasks/' + this.tasks[i].id + '/done', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }
                }).catch(() => { this.tasks[i].done = false; });
            }
         }">
        <div class="hd-col-hdr">
            <div class="hd-col-title">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Tasks
            </div>
            <div style="display:flex;align-items:center;gap:.3rem;">
                <span class="hd-col-count" x-text="tasks.filter(t=>!t.done).length + ' left'"></span>
                <a href="{{ route('tasks.index') }}" title="View all tasks"
                   style="color:var(--c-muted);font-size:.72rem;text-decoration:none;white-space:nowrap;" >
                   View all →
                </a>
            </div>
        </div>
        <div class="hd-col-body">

        <template x-if="tasks.length === 0">
            <div class="hd-empty-col">No pending tasks.</div>
        </template>

        <div class="hd-card" x-show="tasks.length > 0">
            <template x-for="(task, i) in tasks.slice(0,7)" :key="task.id">
                <div class="hd-tc">
                    <div class="hd-tc-check" :class="{ done: task.done }" @click="toggle(i)"
                         :style="task.done ? 'cursor:default' : 'cursor:pointer'">
                        <svg width="8" height="8" fill="none" stroke="white" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                    </div>
                    <div class="hd-tc-body">
                        <div class="hd-tc-title" :class="{ done: task.done }" x-text="task.title"></div>
                        <div class="hd-tc-meta">
                            <span class="hd-badge" :class="'hd-b-' + (task.priority || 'medium')" x-text="task.priority || 'medium'"></span>
                            {{-- Show category badge if overdue --}}
                            <span x-show="task.due_date < '{{ today()->toDateString() }}'"
                                  style="font-size:.65rem;background:#fee2e2;color:#dc2626;border-radius:4px;padding:1px 5px;font-weight:600;">
                                Overdue
                            </span>
                            <span class="hd-tc-assignee" x-text="task.assignee_name ?? ''"></span>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <template x-if="tasks.length > 7">
            <a href="{{ route('tasks.index') }}"
               class="hd-view-all" style="background:white;border:1px solid var(--c-border);border-radius:10px;text-decoration:none;display:block;text-align:center;">
                + <span x-text="tasks.length - 7"></span> more tasks
            </a>
        </template>

        {{-- ── ADD TASK → opens global Create Task modal ── --}}
        <button
            @click="window.dispatchEvent(new CustomEvent('open-create-task', { detail: {} }))"
            style="display:block;margin-top:.4rem;width:100%;padding:.45rem;border-radius:8px;font-size:.73rem;font-weight:600;
                   font-family:inherit;cursor:pointer;transition:.15s;text-align:center;
                   background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;box-sizing:border-box;"
            onmouseover="this.style.background='#dcfce7'" onmouseout="this.style.background='#f0fdf4'">
            + Add Task
        </button>

        </div>{{-- /hd-col-body --}}
    </div>

    {{-- ── COL 5: LAB UPDATES ── --}}
    <div class="hd-col">
        <div class="hd-col-hdr">
            <div class="hd-col-title">
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                Lab Updates
            </div>
            <div style="display:flex;align-items:center;gap:.3rem;">
                <span class="hd-col-count {{ $labsDueToday->where('is_overdue',true)->count() > 0 ? 'red' : '' }}">
                    {{ $labsDueToday->count() }}
                </span>
                <span class="hd-col-menu">•••</span>
            </div>
        </div>
        <div class="hd-col-body">

        {{-- ▸ Remake / Repeat work alert banner ──────────────────────── --}}
        @if(($labRemakesOpen->cnt ?? 0) > 0)
        <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;padding:8px 10px;margin-bottom:8px;font-size:11px;color:#92400e;display:flex;align-items:center;gap:6px;">
            <span><strong>{{ $labRemakesOpen->cnt }} repeat work</strong> case{{ $labRemakesOpen->cnt > 1 ? 's' : '' }} in progress
            @if($labRemakesOpen->total_cost > 0)
            — est. Rs.{{ number_format($labRemakesOpen->total_cost, 0) }} loss
            @endif
            </span>
            <a href="{{ route('lab.index', ['status' => 'active']) }}" style="margin-left:auto;color:#92400e;font-weight:600;text-decoration:none;">View →</a>
        </div>
        @endif

        {{-- ▸ Trial work awaiting doctor review ──────────────────────── --}}
        @if($labTrialPending->count() > 0)
        <div style="margin-bottom:8px;">
            <div style="font-size:10px;font-weight:700;color:#92400e;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">
                Trial Received — Needs Doctor Review ({{ $labTrialPending->count() }})
            </div>
            @foreach($labTrialPending as $trial)
            <a href="{{ route('lab.show', $trial->id) }}" style="text-decoration:none;display:block;">
            <div class="hd-card" style="cursor:pointer;background:#fffbeb;border:1px solid #fde68a;margin-bottom:4px;"
                 onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow=''">
                <div class="hd-lc">
                    <div class="hd-lc-name">{{ $trial->patient_name }}</div>
                    <div class="hd-lc-lab">{{ $trial->case_number }} · {{ $trial->lab_name ?? '—' }}</div>
                    <div class="hd-lc-footer">
                        <span class="hd-badge" style="background:#fef3c7;color:#92400e;">Trial #{{ $trial->trial_round ?? 1 }}</span>
                        <span style="font-size:10px;color:#92400e;font-weight:600;">Doctor review needed</span>
                    </div>
                </div>
            </div>
            </a>
            @endforeach
        </div>
        @endif

        {{-- ▸ Overdue / Due today cases ───────────────────────────────── --}}
        @php
            $huddleLabStatuses = \App\Models\LabCase::STATUS_LABELS;
        @endphp
        @forelse($labsDueToday as $lab)
        <a href="{{ route('lab.show', $lab->id) }}" style="text-decoration:none;display:block;" title="Open lab case">
        <div class="hd-card" style="cursor:pointer;transition:box-shadow .15s;{{ $lab->is_overdue ? 'border-left:3px solid var(--c-red);' : '' }}"
             onmouseover="this.style.boxShadow='0 2px 8px rgba(0,0,0,.08)'" onmouseout="this.style.boxShadow=''">
            <div class="hd-lc">
                <div class="hd-lc-name">{{ $lab->patient_name ?? 'Patient' }}</div>
                <div class="hd-lc-lab">{{ $lab->case_number }} · Lab: {{ $lab->lab_name ?? '—' }}</div>
                <div class="hd-lc-footer">
                    {{-- Status badge --}}
                    <span class="hd-badge" style="background:#f3e8ff;color:#6b21a8;">
                        {{ $huddleLabStatuses[$lab->status] ?? ucfirst(str_replace('_',' ',$lab->status)) }}
                    </span>
                    {{-- Trial round --}}
                    @if(($lab->trial_round ?? 0) > 0)
                    <span class="hd-badge" style="background:#fef3c7;color:#92400e;">T#{{ $lab->trial_round }}</span>
                    @endif
                    {{-- Priority --}}
                    @if(in_array($lab->priority ?? '', ['urgent','express']))
                        <span class="hd-badge" style="background:{{ $lab->priority==='express' ? '#fee2e2' : '#fef3c7' }};color:{{ $lab->priority==='express' ? '#b91c1c' : '#92400e' }};">
                            {{ ucfirst($lab->priority) }}
                        </span>
                    @endif
                    {{-- Remake flag --}}
                    @if($lab->is_remake ?? false)
                    <span class="hd-badge" style="background:#fde8e8;color:#b52020;font-weight:700;">REPEAT</span>
                    @endif
                    {{-- Due / Overdue --}}
                    @if(!empty($lab->is_overdue))
                        <span class="hd-lc-due" style="color:var(--c-red);font-weight:600;">
                            {{ $lab->overdue_days }}d overdue
                        </span>
                    @elseif(isset($lab->due_date) && \Carbon\Carbon::parse($lab->due_date)->isToday())
                        <span class="hd-lc-due" style="color:var(--c-amber);font-weight:600;">Due Today</span>
                    @endif
                </div>
            </div>
        </div>
        </a>
        @empty
        @if($labTrialPending->count() === 0)
        <div class="hd-empty-col">No overdue or due lab cases today.</div>
        @endif
        @endforelse

        @if($labsDueToday->count() >= 10)
        <a href="{{ route('lab.index', ['status' => 'overdue']) }}" class="hd-view-all">View all overdue cases →</a>
        @endif

        {{-- New Lab Case --}}
        <a href="{{ route('lab.index') }}"
           class="hd-view-all"
           style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;text-decoration:none;display:block;text-align:center;color:#16a34a;font-weight:600;margin-top:.3rem;">
            Open Lab Module →
        </a>

        </div>{{-- /hd-col-body --}}
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
        <div class="hd-col-body">

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

        </div>{{-- /hd-col-body --}}
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
                <div class="hd-mc-ico hd-mc-ig"></div>
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

    {{-- ── RELATIONSHIP ACTIONS (Phase 7 — TodayActionsEngine) ─────────── --}}
    {{-- Additive section: recall calls, missed appts, lead follow-ups, renewals --}}
    @php
        $relItemsByCategory = collect($relationshipItems ?? [])->groupBy('categoryName');
        $relTotal = count($relationshipItems ?? []);
        $relHighCount = collect($relationshipItems ?? [])->filter(fn($c) => $c->status === 'high')->count();
    @endphp
    <div class="hd-bottom-wide">
        <div class="hd-section-hdr">
            <span>
                <svg width="13" height="13" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:inline;vertical-align:middle;margin-right:.3rem;"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                Relationship Actions
            </span>
            <div style="display:flex;align-items:center;gap:.4rem;">
                @if($relHighCount > 0)
                    <span class="hd-section-hdr-count" style="background:#fee2e2;color:#991b1b;">{{ $relHighCount }} urgent</span>
                @endif
                <span class="hd-section-hdr-count">{{ $relTotal }} total</span>
                <a href="{{ route('relationship.today') }}" style="font-size:.65rem;color:var(--c-accent);font-weight:600;text-decoration:none;">View All →</a>
            </div>
        </div>

        @if($relTotal === 0)
            <div class="hd-card">
                <div style="text-align:center;padding:1rem .75rem;color:var(--c-muted);font-size:.75rem;">
                    ✓ No relationship actions for today
                </div>
            </div>
        @else
        <div class="hd-card">
        @php
            $categoryIcons = [
                'recall_calls'                  => ['🔔', '#eff6ff', '#1e40af'],
                'missed_appointments_yesterday' => ['⚠️',  '#fef2f2', '#991b1b'],
                'lead_followups'                => ['👤', '#f5f3ff', '#5b21b6'],
                'membership_renewals'           => ['🏅', '#fffbeb', '#854d0e'],
            ];
            $categoryLabels = [
                'recall_calls'                  => 'Recall Calls',
                'missed_appointments_yesterday' => 'Missed Yesterday',
                'lead_followups'                => 'Lead Follow-ups',
                'membership_renewals'           => 'Membership Renewals',
            ];
        @endphp

        @foreach($relItemsByCategory as $catKey => $catItems)
        @php
            [$ico, $bgColor, $textColor] = $categoryIcons[$catKey] ?? ['📋', '#f3f4f6', '#374151'];
            $catLabel = $categoryLabels[$catKey] ?? ucwords(str_replace('_', ' ', $catKey));
            $showCount = $catItems->count();
        @endphp

        {{-- Category sub-header --}}
        <div class="hd-cs-hdr" x-data="{ open: true }" @click="open = !open" style="margin:.3rem .5rem 0;">
            <div class="hd-cs-left">
                <span style="background:{{ $bgColor }};color:{{ $textColor }};padding:.15rem .45rem;border-radius:999px;font-size:.65rem;font-weight:700;">
                    {{ $ico }} {{ $catLabel }}
                </span>
                <span class="hd-cs-count" style="background:{{ $bgColor }};color:{{ $textColor }};">{{ $showCount }}</span>
            </div>
            <svg class="hd-cs-chevron" :class="open ? 'open' : ''" width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
        </div>

        <div class="hd-cs-body" x-show="open" x-transition style="padding:.3rem .5rem .4rem;">
            @foreach($catItems->take(5) as $relCard)
            <div style="display:flex;align-items:flex-start;gap:.55rem;padding:.5rem .55rem;border-radius:8px;border:1px solid var(--c-border);background:var(--c-bg);margin-bottom:.3rem;">
                {{-- Priority dot --}}
                <div style="width:8px;height:8px;border-radius:50%;flex-shrink:0;margin-top:4px;
                    background: {{ $relCard->status === 'high' ? 'var(--c-red)' : ($relCard->status === 'medium' ? 'var(--c-amber)' : 'var(--c-green)') }};">
                </div>
                <div style="flex:1;min-width:0;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:.4rem;margin-bottom:.15rem;">
                        <a href="{{ $relCard->meta['link'] ?? '#' }}"
                           style="font-size:.78rem;font-weight:600;color:var(--c-text);text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"
                           title="{{ $relCard->patientName }}">
                            {{ $relCard->patientName ?? '—' }}
                        </a>
                        <span class="hd-badge hd-b-{{ $relCard->status }}" style="flex-shrink:0;">
                            {{ ucfirst($relCard->status) }}
                        </span>
                    </div>
                    <div style="font-size:.68rem;color:var(--c-muted);line-height:1.35;">
                        {{ $relCard->chiefComplaint }}
                    </div>
                    @if($relCard->notes)
                    <div style="font-size:.65rem;color:var(--c-accent);margin-top:.12rem;">
                        → {{ $relCard->notes }}
                    </div>
                    @endif
                </div>
            </div>
            @endforeach

            @if($catItems->count() > 5)
            <a href="{{ route('relationship.today') }}" class="hd-view-all" style="font-size:.68rem;">
                + {{ $catItems->count() - 5 }} more in this category →
            </a>
            @endif
        </div>
        @endforeach

        <a href="{{ route('relationship.today') }}" class="hd-view-all">
            Open Today's Actions (full view) →
        </a>
        </div>{{-- /hd-card --}}
        @endif
    </div>{{-- /relationship actions --}}

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


</div>{{-- /hd-bottom-row --}}

{{-- ══ FOOTER BAR ══════════════════════════════════════════════════════════ --}}
<div class="hd-footer-bar">
    <span>
        <svg width="11" height="11" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        All times are local
        &nbsp;•&nbsp;
        Dentfluence OS
    </span>
    <span>Last synced: {{ now()->format('h:i a') }}</span>
</div>

</div>{{-- /.hd.hd-escape --}}
@endsection
