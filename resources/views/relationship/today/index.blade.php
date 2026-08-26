{{--
|==========================================================================
| Today's Actions — Relationship Engine Phase 2
| /relationship/today
|
| Variables from TodayController::index():
|   $groups       — array keyed by category, each with: key, label, icon,
|                   items (array of action items), count, priority
|   $totalCount   — int
|   $checklists   — config array, keyed by category
|   $responseOpts — config array, keyed by category (falls back to 'default')
|   $nextActions  — config array, keyed by response key
|
| Uses:  layouts.app
| Icons: Tabler Icons (loaded via @vite or CDN — check existing views)
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', "Today's Actions")

@section('head-extra')
{{-- Tabler Icons CDN (already loaded globally in most pages — guard prevents double-load) --}}
@unless(app()->has('tabler_icons_loaded'))
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css">
@endunless
<style>
    /* ── Today's Actions page styles ── */
    .ta-page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 24px;
        flex-wrap: nowrap;
        gap: 16px;
    }

    .ta-page-header-title-col {
        flex: 1 1 auto;
        min-width: 0;
        max-width: 620px;
    }

    .ta-page-header-controls-col {
        flex: 0 0 auto;
    }

    .ta-page-title {
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-size: 30px;
        font-weight: 600;
        color: #1a0320;
        margin: 0 0 4px;
        line-height: 1.2;
    }

    .ta-page-sub {
        font-size: 13px;
        color: #9a7aaa;
        margin: 0;
    }

    .ta-total-badge {
        font-size: 13px;
        font-weight: 600;
        color: #6a0f70;
        background: #f3e8f4;
        border: 1px solid #dfc5e1;
        border-radius: 99px;
        padding: 4px 14px;
        white-space: nowrap;
    }

    /* ── Category sections ── */
    .ta-section {
        background: #fff;
        border: 1px solid #e8dff0;
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 16px;
    }

    .ta-section.ta-section--empty {
        opacity: 0.55;
    }

    .ta-section-header {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 12px 18px;
        border-bottom: 1px solid #f0e8f5;
        background: #faf5fc;
        cursor: pointer;
        user-select: none;
    }

    .ta-section-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        background: #ede4f7;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        color: #6a0f70;
        flex-shrink: 0;
    }

    .ta-section-label {
        font-weight: 600;
        font-size: 14px;
        color: #1a0320;
        flex: 1;
    }

    .ta-section-count {
        font-size: 11px;
        font-weight: 700;
        padding: 2px 10px;
        border-radius: 99px;
    }

    .ta-section-count--has { background: #ede4f7; color: #6a0f70; }
    .ta-section-count--empty { background: #f0f0f0; color: #aaa; }

    .ta-chevron {
        color: #aaa;
        font-size: 14px;
        transition: transform 200ms ease;
    }

    /* ── Action item rows ── */
    .ta-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 13px 18px;
        border-bottom: 1px solid #f8f4fc;
        transition: background 100ms;
    }

    .ta-item:last-child { border-bottom: none; }
    .ta-item:hover { background: #fdf9ff; }
    .ta-item.ta-item--actioned { opacity: 0.45; }

    .ta-item-body {
        flex: 1;
        min-width: 0;
    }

    .ta-item-name {
        font-weight: 600;
        font-size: 14px;
        color: #1a0320;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .ta-item-reason {
        font-size: 12px;
        color: #6a5a76;
        margin-top: 2px;
    }

    .ta-item-actions {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-shrink: 0;
    }

    /* Priority badges */
    .ta-priority {
        font-size: 10px;
        font-weight: 700;
        padding: 2px 8px;
        border-radius: 99px;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .ta-priority--high   { background: #fdeaea; color: #b52020; }
    .ta-priority--medium { background: #fff4e0; color: #a05c00; }
    .ta-priority--low    { background: #e8f7ef; color: #1a7a45; }

    /* Action buttons */
    .ta-btn-call {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        font-weight: 600;
        padding: 5px 12px;
        border-radius: 7px;
        background: #6a0f70;
        color: #fff;
        border: none;
        cursor: pointer;
        white-space: nowrap;
        transition: background 150ms;
    }

    .ta-btn-call:hover { background: #4e0a53; }

    .ta-btn-open {
        display: inline-flex;
        align-items: center;
        font-size: 12px;
        color: #6a0f70;
        text-decoration: none;
        padding: 5px 8px;
        border-radius: 7px;
        border: 1px solid #dfc5e1;
        transition: background 100ms;
    }

    .ta-btn-open:hover { background: #f3e8f4; }

    .ta-btn-done {
        display: inline-flex;
        align-items: center;
        font-size: 12px;
        color: #1a7a45;
        background: #e8f7ef;
        border: 1px solid #b8e0ca;
        padding: 4px 8px;
        border-radius: 7px;
        cursor: pointer;
        font-weight: 600;
        gap: 4px;
    }

    /* ══ CALL WORKFLOW DRAWER ══════════════════════════════════════════════ */
    .ta-drawer-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(26, 3, 32, 0.35);
        z-index: 60;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 20px;
    }

    .ta-drawer {
        width: 490px;
        max-width: 100%;
        max-height: 90vh;
        background: #fff;
        display: flex;
        flex-direction: column;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(26, 3, 32, 0.25);
        overflow: hidden;
    }

    .ta-drawer-header {
        padding: 13px 18px 12px;
        background: linear-gradient(135deg, #4e0a53, #6a0f70);
        color: #fff;
        flex-shrink: 0;
    }

    .ta-drawer-title {
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-size: 19px;
        font-weight: 600;
        margin: 0 0 2px;
    }

    .ta-drawer-sub {
        font-size: 12px;
        opacity: 0.75;
        margin: 0;
    }

    .ta-drawer-body {
        flex: 1;
        overflow-y: auto;
        padding: 14px 18px 16px;
    }

    .ta-drawer-section {
        margin-bottom: 14px;
    }

    .ta-drawer-section:last-child { margin-bottom: 0; }

    .ta-drawer-section-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #9a7aaa;
        margin-bottom: 8px;
    }

    /* ══ DRAWER — compact call workflow (redesign 2026-08-26, Sumit) ══════
       The old drawer stacked a 2-column summary grid, a full note log with
       its own editor, a decorative checklist, a Log/Close tab strip, an
       outcome dropdown, a notes textarea and a suggestion box — every one of
       them always on screen, so the normal "ring patient, tick the result"
       job needed scrolling. Everything below is sized so that job fits in
       the viewport with nothing hidden: context is one wrapped line, the
       result is two rows of chips, and the note + history only take space
       once they are actually wanted.
    ═══════════════════════════════════════════════════════════════════════ */

    /* Context strip — one dense line, not cards */
    .ta-ctx {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 14px;
        font-size: 12.5px;
        color: #4a3350;
        background: #faf7fc;
        border: 1px solid #f0e8f5;
        border-radius: 9px;
        padding: 8px 11px;
    }

    .ta-ctx-item { display: inline-flex; align-items: baseline; gap: 5px; min-width: 0; }
    .ta-ctx-label { font-size: 10.5px; text-transform: uppercase; letter-spacing: .05em; color: #a58bb0; }
    .ta-ctx-value { font-weight: 600; color: #1a0320; }
    .ta-ctx-value a { color: #6a0f70; text-decoration: none; font-weight: 700; }

    /* Call guidance — what to say. Never mixed with what happened. */
    .ta-guide {
        display: flex;
        gap: 8px;
        align-items: flex-start;
        background: #f5eef9;
        border-radius: 9px;
        padding: 8px 11px;
        font-size: 12.5px;
        line-height: 1.45;
        color: #5a2a63;
        margin-top: 8px;
    }

    .ta-guide i { color: #9a4aa2; margin-top: 2px; flex-shrink: 0; }
    .ta-guide ul { margin: 3px 0 0; padding-left: 15px; }
    .ta-guide li { margin: 1px 0; }

    /* Direction toggle — "we called" vs "patient called us" */
    .ta-seg { display: inline-flex; background: #f3e8f4; border-radius: 8px; padding: 2px; gap: 2px; }

    .ta-seg button {
        border: none;
        background: transparent;
        font-family: 'DM Sans', system-ui, sans-serif;
        font-size: 11.5px;
        font-weight: 600;
        color: #8a6d94;
        padding: 4px 10px;
        border-radius: 6px;
        cursor: pointer;
        white-space: nowrap;
    }

    .ta-seg button.on { background: #fff; color: #4e0a53; box-shadow: 0 1px 3px rgba(78,10,83,.14); }

    /* Result / response chips */
    .ta-opt-row { display: flex; flex-wrap: wrap; gap: 7px; }

    .ta-opt {
        border: 1.5px solid #e2d4e8;
        background: #fff;
        border-radius: 9px;
        padding: 8px 13px;
        font-family: 'DM Sans', system-ui, sans-serif;
        font-size: 13px;
        font-weight: 600;
        color: #4a3350;
        cursor: pointer;
        transition: border-color 120ms, background 120ms, color 120ms;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .ta-opt:hover { border-color: #b95cb7; }

    .ta-opt.on {
        border-color: #6a0f70;
        background: #6a0f70;
        color: #fff;
        box-shadow: 0 2px 8px rgba(106,15,112,.22);
    }

    .ta-opt.on.ta-opt-warn { border-color: #8a5a2a; background: #8a5a2a; box-shadow: 0 2px 8px rgba(138,90,42,.22); }

    /* Plain-language consequence line — replaces "Suggested Next Action" */
    .ta-outcome-hint {
        display: flex;
        gap: 7px;
        align-items: flex-start;
        border-radius: 9px;
        padding: 8px 11px;
        font-size: 12.5px;
        line-height: 1.45;
        margin-top: 10px;
    }

    .ta-outcome-hint.done  { background: #f0f9f4; border: 1px solid #b8e0ca; color: #1a7a45; }
    .ta-outcome-hint.retry { background: #fdf6ec; border: 1px solid #ecd9b8; color: #8a5a2a; }

    /* Secondary toggles — note + history stay collapsed by default */
    .ta-more { display: flex; gap: 16px; margin-top: 12px; }

    .ta-more button {
        background: none;
        border: none;
        padding: 0;
        font-family: 'DM Sans', system-ui, sans-serif;
        font-size: 12px;
        font-weight: 600;
        color: #8a6d94;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }

    .ta-more button:hover { color: #6a0f70; }

    /* Interaction history — the owner audit trail */
    .ta-hist { margin-top: 10px; border-top: 1px solid #f0e8f5; padding-top: 10px; max-height: 168px; overflow-y: auto; }
    .ta-hist-row { display: flex; gap: 8px; padding: 5px 0; font-size: 12.5px; line-height: 1.4; }
    .ta-hist-time { color: #a58bb0; font-variant-numeric: tabular-nums; white-space: nowrap; font-size: 11.5px; padding-top: 1px; }
    .ta-hist-body { flex: 1; min-width: 0; color: #3a1140; }
    .ta-hist-who { color: #9a7aaa; font-size: 11.5px; }
    .ta-hist-note { color: #6b7280; font-size: 11.5px; margin-top: 1px; }

    .ta-hist-dir {
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        border-radius: 20px;
        padding: 1px 7px;
        white-space: nowrap;
        height: fit-content;
        margin-top: 1px;
    }

    .ta-hist-dir.out  { background: #f0eefc; color: #534AB7; }
    .ta-hist-dir.in   { background: #eef6ee; color: #2f7a3d; }
    .ta-hist-dir.note { background: #f5f2f6; color: #7a6d80; }

    .ta-drawer-footer {
        padding: 11px 18px;
        border-top: 1px solid #f0e8f5;
        display: flex;
        gap: 10px;
        flex-shrink: 0;
    }

    .ta-btn-submit {
        flex: 1;
        padding: 10px;
        background: #6a0f70;
        color: #fff;
        border: none;
        border-radius: 9px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        font-family: 'DM Sans', system-ui, sans-serif;
        transition: background 150ms;
    }

    .ta-btn-submit:hover { background: #4e0a53; }
    .ta-btn-submit:disabled { background: #b3a0b8; cursor: not-allowed; }

    .ta-btn-cancel {
        padding: 10px 18px;
        background: #f3e8f4;
        color: #6a0f70;
        border: 1px solid #dfc5e1;
        border-radius: 9px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        font-family: 'DM Sans', system-ui, sans-serif;
    }

    .ta-btn-cancel:hover { background: #dfc5e1; }

    /* Empty state */
    .ta-empty {
        padding: 24px;
        text-align: center;
        color: #b3a0b8;
        font-size: 13px;
    }

    .ta-empty-icon {
        font-size: 32px;
        color: #dfc5e1;
        margin-bottom: 6px;
    }

    /* ══════════════════════════════════════════════════════════════════
       WORKLIST — dense operational table (visual redevelopment 2026-08-25)
       One row = one action. Column order follows how the day is worked:
       urgency → who → what to do → when → who owns it → context.
       Purely presentational: no new data, no new endpoints.
    ══════════════════════════════════════════════════════════════════ */
    #df-content-inner { padding: 10px 20px 8px !important; }

    .taw { max-width: 1560px; margin: 0 auto; }

    /* ── Header ── */
    .taw-head { display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:9px; }
    .taw-title { font-family:'Cormorant Garamond', Georgia, serif; font-size:24px; font-weight:600; color:#1a0320; margin:0; line-height:1.15; }
    .taw-sub { font-size:11.5px; color:#9a7aaa; margin:2px 0 0; }
    .taw-count { font-size:11px; font-weight:700; color:#6a0f70; background:#f3e8f4; border:1px solid #e2cfe6; border-radius:99px; padding:2px 9px; white-space:nowrap; }
    .taw-spacer { flex:1 1 auto; }
    .taw-search { position:relative; }
    .taw-search i { position:absolute; left:8px; top:50%; transform:translateY(-50%); font-size:13px; color:#b3a0b8; }
    .taw-search input { width:236px; padding:5px 8px 5px 26px; border:1px solid #e2d4e8; border-radius:7px; font-size:12px; color:#3a1140; background:#fff; }
    .taw-search input:focus { outline:none; border-color:#6a0f70; box-shadow:0 0 0 2px #f3e8f4; }

    /* ── Toolbar ── */
    .taw-bar { display:flex; align-items:center; gap:6px; flex-wrap:wrap; padding:7px 9px; background:#fff; border:1px solid #ece2f1; border-radius:9px; margin-bottom:8px; }
    .taw-lbl { font-size:10px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#a892b0; margin-right:2px; }
    .taw-btn { display:inline-flex; align-items:center; gap:5px; padding:5px 10px; border:1px solid #e2d4e8; border-radius:7px; background:#fff; color:#5a2a62; font-size:11.5px; font-weight:600; text-decoration:none; cursor:pointer; white-space:nowrap; line-height:1.3; }
    .taw-btn:hover { background:#faf5fc; }
    .taw-btn--on { border-color:#6a0f70; background:#f3e8f4; color:#6a0f70; }
    .taw-btn--primary { background:#6a0f70; border-color:#6a0f70; color:#fff; }
    .taw-btn--primary:hover { background:#4e0a53; }
    .taw-btn--alert { border-color:#e3b3b3; background:#fff6f6; color:#c92a2a; }
    .taw-pill { min-width:16px; text-align:center; border-radius:9px; padding:0 5px; font-size:10px; font-weight:700; background:#ede4f7; color:#6a0f70; }
    .taw-btn--alert .taw-pill { background:#c92a2a; color:#fff; }
    .taw-date { padding:5px 8px; border:1px solid #e2d4e8; border-radius:7px; font-size:11.5px; color:#3a1140; background:#fff; }
    .taw-sep { width:1px; height:18px; background:#ece2f1; margin:0 2px; }

    /* ── Category strip ── */
    .taw-cats { display:flex; gap:5px; overflow-x:auto; padding-bottom:5px; margin-bottom:8px; }
    .taw-cats::-webkit-scrollbar { height:5px; }
    .taw-cats::-webkit-scrollbar-thumb { background:#e6dced; border-radius:3px; }
    .taw-cat { display:inline-flex; align-items:center; gap:5px; padding:4px 9px; border:1px solid #e6dced; border-radius:99px; background:#fff; font-size:11px; font-weight:600; color:#6b5573; cursor:pointer; white-space:nowrap; line-height:1.4; }
    .taw-cat:hover { border-color:#cfb4d6; }
    .taw-cat--on { background:#6a0f70; border-color:#6a0f70; color:#fff; }
    .taw-cat-n { font-size:10px; font-weight:700; color:#9a84a2; }
    .taw-cat--on .taw-cat-n { color:#e9d3ee; }

    /* ── Bands: the three sections that make the hierarchy legible ── */
    .taw-band { margin-bottom:14px; }
    .taw-band-head { display:flex; align-items:flex-end; gap:12px; padding:0 2px 6px; border-bottom:2px solid #ece2f1; margin-bottom:8px; }
    .taw-band-title { font-size:12px; font-weight:800; letter-spacing:.09em; text-transform:uppercase; color:#4e0a53; margin:0; line-height:1.2; }
    .taw-band-sub { font-size:11.5px; color:#9a8aa2; margin:2px 0 0; }
    .taw-band-n { margin-left:auto; min-width:26px; text-align:center; font-size:12.5px; font-weight:800; color:#6a0f70; background:#f3e8f4; border:1px solid #e2cfe6; border-radius:99px; padding:1px 9px; font-variant-numeric:tabular-nums; }

    /* Completed — deliberately quiet and outside the queue */
    .taw-done-toggle { display:inline-flex; align-items:center; gap:7px; padding:5px 10px; border:1px dashed #dcd0e4; border-radius:8px; background:#fbf9fc; color:#7a6a82; font-size:11.5px; font-weight:600; cursor:pointer; font-family:inherit; }
    .taw-done-toggle:hover { background:#f5eff8; }
    .taw-done-n { font-weight:800; color:#1a7a45; background:#e8f7ef; border-radius:99px; padding:0 7px; }
    .taw-done-hint { font-weight:500; color:#a99bb0; }

    /* ── Table ── */
    .taw-wrap { background:#fff; border:1px solid #ece2f1; border-radius:10px; overflow:hidden; }
    .taw-scroll { overflow:auto; max-height:clamp(220px, calc(100vh - 296px), 900px); }
    table.taw-t { width:100%; border-collapse:separate; border-spacing:0; font-size:12px; }
    .taw-t thead th { position:sticky; top:0; z-index:2; background:#faf7fc; border-bottom:1px solid #ece2f1; padding:7px 10px; text-align:left; font-size:10px; font-weight:700; letter-spacing:.05em; text-transform:uppercase; color:#8a6f92; white-space:nowrap; }
    .taw-t tbody td { border-bottom:1px solid #f4eef7; padding:6px 10px; vertical-align:middle; color:#3a1140; }
    .taw-t tbody tr:last-child td { border-bottom:none; }
    .taw-t tbody tr:hover td { background:#fdfaff; }
    .taw-t tbody tr.is-done td { background:#fbfdfb; }
    .taw-t tbody tr.is-done .taw-name { color:#7d8a82; }
    .taw-t tbody tr.is-done .taw-do { color:#6f7d75; font-weight:500; }

    /* ── Cells ── */
    .taw-pr { display:inline-flex; align-items:center; gap:5px; font-size:10.5px; font-weight:700; white-space:nowrap; }
    .taw-dot { width:7px; height:7px; border-radius:50%; flex:0 0 auto; }
    .taw-pr--high   { color:#b52020; } .taw-pr--high .taw-dot   { background:#b52020; }
    .taw-pr--medium { color:#a05c00; } .taw-pr--medium .taw-dot { background:#d98d1a; }
    .taw-pr--low    { color:#1a7a45; } .taw-pr--low .taw-dot    { background:#35a06a; }

    .taw-name { font-weight:600; color:#1a0320; white-space:nowrap; max-width:180px; overflow:hidden; text-overflow:ellipsis; }
    .taw-name a { color:inherit; text-decoration:none; }
    .taw-name a:hover { color:#6a0f70; text-decoration:underline; }
    .taw-do { font-weight:600; color:#2c1033; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:400px; }
    .taw-why { display:block; font-size:11px; color:#8a7a92; margin-top:1px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:400px; }
    .taw-why--ok { color:#1a7a45; }
    .taw-why--try { color:#a05c00; }
    .taw-due { white-space:nowrap; font-variant-numeric:tabular-nums; color:#4a3350; }
    .taw-due--over { color:#c92a2a; font-weight:700; }
    .taw-due--none { color:#b3a0b8; }
    .taw-owner { white-space:nowrap; color:#5a4a62; max-width:120px; overflow:hidden; text-overflow:ellipsis; }
    .taw-owner--none { color:#b8a8c0; }
    .taw-tag { display:inline-block; padding:2px 7px; border-radius:5px; background:#f4eef7; color:#684a72; font-size:10.5px; font-weight:600; white-space:nowrap; max-width:150px; overflow:hidden; text-overflow:ellipsis; }
    .taw-ch { display:inline-flex; align-items:center; gap:4px; font-size:11px; color:#6b5573; white-space:nowrap; }
    .taw-st { display:inline-flex; align-items:center; gap:4px; padding:2px 8px; border-radius:99px; font-size:10.5px; font-weight:700; white-space:nowrap; }
    .taw-st--open  { background:#f3e8f4; color:#6a0f70; }
    .taw-st--tried { background:#fff4e0; color:#a05c00; }
    .taw-st--over  { background:#fdeaea; color:#b52020; }
    .taw-st--done  { background:#e8f7ef; color:#1a7a45; }
    .taw-acts { display:flex; align-items:center; gap:4px; justify-content:flex-end; }
    .taw-ib { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; border-radius:6px; border:1px solid #e2d4e8; background:#fff; color:#6a0f70; font-size:13px; cursor:pointer; text-decoration:none; flex:0 0 auto; }
    .taw-ib:hover { background:#f3e8f4; }
    .taw-ib--go { background:#6a0f70; border-color:#6a0f70; color:#fff; }
    .taw-ib--go:hover { background:#4e0a53; }
    /* The tick is clickable now — it opens that action's history — so it
       keeps a pointer and a hover state instead of reading as inert. */
    .taw-ib--ok { border-color:#cfe9db; background:#f2fbf6; color:#1a7a45; cursor:pointer; font-family:inherit; padding:0; }
    .taw-ib--ok:hover { background:#e2f5ea; border-color:#a9d9c0; }
    .taw-ib:disabled { opacity:.55; cursor:not-allowed; }

    /* ── Footer / pagination ── */
    .taw-foot { display:flex; align-items:center; gap:10px; padding:6px 10px; border-top:1px solid #f0e8f5; background:#fdfbfe; font-size:11px; color:#8a7a92; flex-wrap:wrap; }
    .taw-foot a { color:#6a0f70; text-decoration:none; font-weight:600; }
    .taw-foot a:hover { text-decoration:underline; }
    .taw-pg { display:flex; align-items:center; gap:4px; margin-left:auto; }
    .taw-pgb { min-width:24px; height:24px; padding:0 7px; border:1px solid #e2d4e8; border-radius:6px; background:#fff; color:#5a2a62; font-size:11px; font-weight:600; cursor:pointer; }
    .taw-pgb:hover:not(:disabled) { background:#f3e8f4; }
    .taw-pgb:disabled { opacity:.4; cursor:not-allowed; }
    .taw-pgn { font-variant-numeric:tabular-nums; }
    .taw-none { padding:26px 16px; text-align:center; color:#9a8aa2; font-size:12px; }

    .ta-empty-footnote { font-size: 11.5px; color: #b3a0b8; text-align: center; padding: 6px 0 2px; }

    /* All done banner */
    .ta-all-done {
        background: linear-gradient(135deg, #e8f7ef, #f0faf4);
        border: 1px solid #b8e0ca;
        border-radius: 14px;
        padding: 36px;
        text-align: center;
        margin-bottom: 24px;
    }

    .ta-all-done-icon { font-size: 40px; color: #1a7a45; }
    .ta-all-done-title { font-family: 'Cormorant Garamond', Georgia, serif; font-size: 22px; color: #1a0320; margin: 8px 0 4px; }
    .ta-all-done-sub { font-size: 13px; color: #4a8a64; }
</style>
@endsection

@section('relationship-content')

{{-- ══════════════════════════════════════════════════════════════════════
     ALPINE.JS CONTROLLER
     All drawer state lives here. One `open` item at a time.
══════════════════════════════════════════════════════════════════════ --}}
@php
    // Sprint A (2026-08-24): this template renders BOTH boards.
    // boardMode 'today'   → Today's Actions (due today)
    // boardMode 'pending' → Pending Calls (open, due before today)
    $boardMode    = $boardMode    ?? 'today';
    $pendingCount = $pendingCount ?? 0;
@endphp
<div
    x-data="todayActions()"
    @keydown.escape.window="closeDrawer()"
>

    {{-- ══════════════════════════════════════════════════════════════════
         WORKLIST DATA PREP (view layer only)
         Flattens the category groups into one operational list and cleans
         internal wording for display. No queries, no engine calls.
    ══════════════════════════════════════════════════════════════════ --}}
    @php
        // Internal automation-rule names must never surface to reception.
        // Display-only translation — the stored text itself is untouched.
        $ruleLabels = [
            'implant_followup'                => 'Implant follow-up',
            'post_treatment_followup'         => 'Post-treatment follow-up',
            'recall_6months'                  => 'Six-month recall due',
            'membership_renewal_30d'          => 'Membership renewal due',
            'birthday_3d'                     => 'Birthday in 3 days',
            'opportunity_nudge_7d'            => 'Treatment decision follow-up',
            'estimate_followup_3d'            => 'Estimate follow-up',
            'missed_appointment_followup'     => 'Missed appointment follow-up',
            'lab_ready_call'                  => 'Lab work ready',
            'payment_overdue_3d'              => 'Payment follow-up',
            'presentation_callback_requested' => 'Call-back requested by patient',
            'case_opened_followup_2d'         => 'Case follow-up',
            'case_more_time_requested'        => 'Patient asked for more time',
        ];

        $humanise = function (?string $text) use ($ruleLabels) {
            $text = trim((string) $text);
            if ($text === '') { return ''; }
            $text = preg_replace('/\[\s*auto\s*\]\s*/i', '', $text);
            $text = preg_replace_callback('/rule\s*:\s*([a-z0-9_]+)/i', function ($m) use ($ruleLabels) {
                $key = strtolower($m[1]);
                return $ruleLabels[$key] ?? ucfirst(str_replace('_', ' ', $key));
            }, $text);
            return trim($text);
        };

        $channelMeta = [
            'call'     => ['ti-phone',          'Call'],
            'phone'    => ['ti-phone',          'Call'],
            'whatsapp' => ['ti-brand-whatsapp', 'WhatsApp'],
            'sms'      => ['ti-message-2',      'SMS'],
            'email'    => ['ti-mail',           'Email'],
            'visit'    => ['ti-building-store', 'In clinic'],
        ];

        $todayStr    = $today->toDateString();
        $tomorrowStr = $today->copy()->addDay()->toDateString();
        $prRank      = ['high' => 0, 'medium' => 1, 'low' => 2];

        $rows = [];
        foreach ($groups as $catKey => $group) {
            foreach ($group['items'] as $idx => $item) {
                $done     = $item['done'] ?? null;
                $lastCall = $item['last_call'] ?? null;

                // ── WHEN ── the item's own due date, else the most
                // meaningful date its meta already carries. Never invented.
                $parse = function ($v) {
                    try { return $v ? \Illuminate\Support\Carbon::parse($v) : null; }
                    catch (\Throwable $e) { return null; }
                };
                $dueTxt = '—'; $dueCls = 'taw-due--none'; $dueTip = 'No date on record';
                $d = $parse($item['due_date'] ?? null);
                $kind = 'due';
                if (! $d) {
                    foreach ([['due_date','due'], ['follow_up_date','due'], ['appointment_date','appt'],
                              ['end_date','expires'], ['ready_since','ready'], ['visit_date','visit']] as $probe) {
                        if (! empty($item['meta'][$probe[0]])) {
                            $d = $parse($item['meta'][$probe[0]]);
                            if ($d) { $kind = $probe[1]; break; }
                        }
                    }
                }
                if ($d) {
                    $ds = $d->toDateString();
                    $long = $d->format('D, d M Y');
                    if ($kind === 'due') {
                        if ($ds === $todayStr)          { $dueTxt = 'Today';    $dueCls = ''; $dueTip = 'Due today'; }
                        elseif ($ds === $tomorrowStr)   { $dueTxt = 'Tomorrow'; $dueCls = ''; $dueTip = 'Due ' . $long; }
                        elseif ($ds < $todayStr)        { $dueTxt = $d->format('d M'); $dueCls = 'taw-due--over'; $dueTip = 'Overdue since ' . $long; }
                        else                            { $dueTxt = $d->format('d M'); $dueCls = ''; $dueTip = 'Due ' . $long; }
                    } else {
                        $prefix = ['appt' => 'Appt', 'expires' => 'Expires', 'ready' => 'Ready', 'visit' => 'Visit'][$kind] ?? '';
                        $when   = $ds === $todayStr ? 'today' : ($ds === $tomorrowStr ? 'tomorrow' : $d->format('d M'));
                        $dueTxt = trim($prefix . ' ' . $when);
                        $dueCls = ($kind === 'expires' && $ds < $todayStr) ? 'taw-due--over' : '';
                        $dueTip = $prefix . ': ' . $long;

                        // An appointment's TIME is the thing reception works to
                        // (2026-08-26, Sumit) — the day alone tells them nothing
                        // about which call is next. Shown on the row for today's
                        // and tomorrow's sessions, always in the tooltip.
                        if ($kind === 'appt' && ! empty($item['meta']['appointment_time'])) {
                            $apptTime = $parse($item['meta']['appointment_time']);
                            $timeTxt  = $apptTime ? $apptTime->format('g:i A') : $item['meta']['appointment_time'];
                            $dueTip  .= ' at ' . $timeTxt;

                            if ($ds === $todayStr || $ds === $tomorrowStr) {
                                $dueTxt = trim($prefix . ' ' . ($ds === $todayStr ? '' : 'tmrw') . ' ' . $timeTxt);
                            }
                        }
                    }
                }
                $isOverdue = $dueCls === 'taw-due--over';

                // ── CHANNEL ── real values only: the queue's own channel, the
                // WhatsApp-first birthday path, otherwise this board's call action.
                $chKey = strtolower((string) ($item['meta']['channel'] ?? ''));
                if (($item['primary_action'] ?? null) === 'whatsapp') { $chKey = 'whatsapp'; }
                if ($chKey === '' || ! isset($channelMeta[$chKey]))   { $chKey = 'call'; }

                // ── OWNER ── whoever actually handled/attempted it. PRE has no
                // assignment field, so unworked rows read "Unassigned" rather
                // than inventing a name.
                $owner = $done['by'] ?? $lastCall['by'] ?? null;

                // ── STATUS ──
                if ($done)          { $stCls = 'taw-st--done';  $stTxt = 'Done'; }
                elseif ($lastCall)  { $stCls = 'taw-st--tried'; $stTxt = 'Attempted'; }
                elseif ($isOverdue) { $stCls = 'taw-st--over';  $stTxt = 'Overdue'; }
                else                { $stCls = 'taw-st--open';  $stTxt = 'Open'; }

                $doText  = $humanise($item['suggested_action'] ?? '') ?: 'Call the patient';
                $whyText = $humanise($item['reason'] ?? '');

                $rows[] = [
                    'id' => $catKey . '_' . $idx, 'cat' => $catKey, 'catLabel' => $group['label'],
                    'item' => $item, 'done' => $done, 'lastCall' => $lastCall,
                    'dueTxt' => $dueTxt, 'dueCls' => $dueCls, 'dueTip' => $dueTip,
                    'dueSort' => $d ? $d->toDateString() : '9999-12-31',
                    'chIcon' => $channelMeta[$chKey][0], 'chLabel' => $channelMeta[$chKey][1],
                    'owner' => $owner, 'stCls' => $stCls, 'stTxt' => $stTxt,
                    'doText' => $doText, 'whyText' => $whyText,
                    'isDone'    => (bool) $done,
                    'band'      => $group['group'] ?? 'other',
                    'bandRank'  => $group['group_rank'] ?? 3,
                    'bandOrder' => $group['group_order'] ?? 99,
                    'sortPr'    => $prRank[$item['priority'] ?? 'low'] ?? 3,
                    // Chronological tiebreaker. Only appointment rows carry a
                    // time; every other category gets the same sentinel, so
                    // their existing order is untouched.
                    'timeSort'  => (string) ($item['meta']['appointment_time'] ?? '99:99:99'),
                ];
            }
        }

        // Worked order: open work first, then urgency, then the clinic's own
        // category order, then oldest due date.
        usort($rows, fn ($a, $b) =>
            [$a['bandRank'], $a['bandOrder'], $a['sortPr'], $a['dueSort'], $a['timeSort']]
            <=> [$b['bandRank'], $b['bandOrder'], $b['sortPr'], $b['dueSort'], $b['timeSort']]);

        // ── ACTIVE vs COMPLETED ──────────────────────────────────────────
        // The queue answers "what does the team need to do now?", so handled
        // rows leave it entirely and live in their own section below. The
        // header count and every band count are ACTIVE rows only.
        $doneRows   = array_values(array_filter($rows, fn ($r) => $r['isDone']));
        $rows       = array_values(array_filter($rows, fn ($r) => ! $r['isDone']));

        // Rows split into the three bands, each keeping the order above.
        $bands = [];
        foreach (\App\Http\Controllers\Relationship\TodayController::GROUP_ORDER as $bandKey => $meta) {
            $bandRows = array_values(array_filter($rows, fn ($r) => $r['band'] === $bandKey));
            if ($bandRows) {
                $bands[$bandKey] = $meta + ['rows' => $bandRows];
            }
        }

        $rowMeta = array_map(fn ($r) => [
            'id'  => $r['id'],
            'cat' => $r['cat'],
            'band' => $r['band'],
            's'   => mb_strtolower(($r['item']['patient_name'] ?? '') . ' ' . $r['doText'] . ' ' . $r['whyText'] . ' ' . $r['catLabel']),
        ], $rows);

        // Chips filter the ACTIVE queue only — completed rows live in their own
        // section and must never inflate a chip count.
        $activeGroups = collect($groups)->filter(fn ($g) => $g['count'] > 0);
        $emptyCount   = collect($groups)->filter(fn ($g) => $g['count'] === 0 && ($g['done_count'] ?? 0) === 0)->count();
    @endphp

    <script>window.__TA_ROWS = @json($rowMeta);</script>

    <div class="taw" x-data="taWorklist(window.__TA_ROWS || [])">

        {{-- ── Header: what this is, how much of it, and find-a-patient ── --}}
        <div class="taw-head">
            <div style="min-width:0;">
                <h1 class="taw-title">
                    @if($boardMode === 'pending') Pending Calls
                    @elseif($mode === 'today') Today's Actions
                    @elseif($mode === 'future') Upcoming — {{ $selectedDate->format('d M Y') }}
                    @else Completed — {{ $selectedDate->format('d M Y') }}
                    @endif
                </h1>
                <p class="taw-sub">
                    @if($boardMode === 'pending')
                        Calls due before today that are still open — work these down to zero.
                    @elseif($mode === 'today')
                        {{ now()->format('l, d F Y') }} &nbsp;·&nbsp; Generated from live patient data
                    @elseif($mode === 'future')
                        Preview from today's data — a patient could still visit before this date and drop off.
                    @else
                        Calls logged as completed on this date, with their outcome. Read-only.
                    @endif
                </p>
            </div>

            <span class="taw-count">
                {{ $totalCount }} open {{ Str::plural(($mode === 'today' ? 'action' : 'call'), $totalCount) }}
            </span>

            <div class="taw-spacer"></div>

            <div class="taw-search">
                <i class="ti ti-search"></i>
                <input type="search" x-model="q" placeholder="Search patient or reason…" aria-label="Search actions">
            </div>
        </div>

        {{-- ── Toolbar: date scope, board switch, add, refresh ──────────── --}}
        <div class="taw-bar">
            @if($boardMode === 'today')
                <span class="taw-lbl">Date</span>
                <a href="{{ route('relationship.today') }}" class="taw-btn {{ $mode === 'today' ? 'taw-btn--on' : '' }}">Today</a>
                <a href="{{ route('relationship.today') }}?date={{ $today->copy()->addDay()->toDateString() }}" class="taw-btn">Tomorrow</a>
                <input type="date" class="taw-date" value="{{ $selectedDate->toDateString() }}"
                       onchange="window.location.href = '{{ route('relationship.today') }}?date=' + this.value"
                       aria-label="Pick a date">
                @if($mode === 'today')<span class="taw-sep"></span>@endif
            @endif

            @if($boardMode === 'today' && $mode === 'today')
                <a href="{{ route('relationship.today.pending') }}"
                   class="taw-btn {{ $pendingCount > 0 ? 'taw-btn--alert' : '' }}">
                    <i class="ti ti-phone-pause"></i> Pending Calls
                    <span class="taw-pill">{{ $pendingCount }}</span>
                </a>
            @elseif($boardMode === 'pending')
                <a href="{{ route('relationship.today') }}" class="taw-btn">
                    <i class="ti ti-arrow-left"></i> Back to Today's Actions
                </a>
            @endif

            <div class="taw-spacer"></div>

            <button type="button" class="taw-btn taw-btn--primary"
                    onclick="window.dispatchEvent(new CustomEvent('open-create-task', { detail: { category: 'call' } }))">
                <i class="ti ti-plus"></i> Add Call
            </button>
            <button type="button" class="taw-btn" onclick="window.location.reload()">
                <i class="ti ti-refresh"></i> Refresh
            </button>
        </div>

        {{-- ── Category strip — real categories, real counts ─────────────── --}}
        @if($activeGroups->isNotEmpty())
        <div class="taw-cats">
            <button type="button" class="taw-cat" :class="cat === 'all' ? 'taw-cat--on' : ''" @click="setCat('all')">
                All <span class="taw-cat-n">{{ count($rows) }}</span>
            </button>
            @foreach($activeGroups as $catKey => $group)
            <button type="button" class="taw-cat"
                    :class="cat === '{{ $catKey }}' ? 'taw-cat--on' : ''"
                    @click="setCat('{{ $catKey }}')">
                <i class="ti {{ $group['icon'] }}"></i>
                {{ $group['label'] }}
                <span class="taw-cat-n">{{ $group['count'] }}</span>
            </button>
            @endforeach
        </div>
        @endif

        {{-- ── All done / nothing found ─────────────────────────────────── --}}
        @if(empty($rows))
        <div class="ta-all-done">
            <div class="ta-all-done-icon"><i class="ti ti-circle-check"></i></div>
            @if($boardMode === 'pending')
                <div class="ta-all-done-title">No pending calls — nothing was missed</div>
                <div class="ta-all-done-sub">Every call due before today has been completed or resolved.</div>
            @elseif($mode === 'today')
                <div class="ta-all-done-title">All done — you're caught up!</div>
                <div class="ta-all-done-sub">No outstanding actions right now. Check back tomorrow morning.</div>
            @elseif($mode === 'future')
                <div class="ta-all-done-title">Nothing scheduled yet for this date</div>
                <div class="ta-all-done-sub">No recall, follow-up, birthday, or renewal dates fall on {{ $selectedDate->format('d M Y') }}.</div>
            @else
                <div class="ta-all-done-title">No completed calls logged</div>
                <div class="ta-all-done-sub">Nothing was marked completed on {{ $selectedDate->format('d M Y') }}.</div>
            @endif
        </div>
        @endif

        {{-- ══════════════════════════════════════════════════════════════
             THE QUEUE — three bands, each its own titled section.
             Order inside a band is the clinic's category order
             (TodayController::CATEGORY_GROUPS), not counts or DB order.
             Completed rows are NOT here — they have their own section below.
        ══════════════════════════════════════════════════════════════ --}}
        @forelse($bands as $bandKey => $band)
        <section class="taw-band" x-show="bandVisible('{{ $bandKey }}')">
            <div class="taw-band-head">
                <div>
                    <h2 class="taw-band-title">{{ $band['label'] }}</h2>
                    <p class="taw-band-sub">{{ $band['sub'] }}</p>
                </div>
                <span class="taw-band-n" x-text="bandCount('{{ $bandKey }}')">{{ count($band['rows']) }}</span>
            </div>

            <div class="taw-wrap">
                <table class="taw-t">
                    <thead>
                        <tr>
                            <th style="width:78px;">Priority</th>
                            <th style="width:190px;">Patient</th>
                            <th>Action &amp; Reason</th>
                            <th style="width:112px;">Due</th>
                            <th style="width:118px;">Owner</th>
                            <th style="width:172px;">Category</th>
                            <th style="width:96px;">Channel</th>
                            <th style="width:104px;">Status</th>
                            <th style="width:68px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($band['rows'] as $row)
                            @include('relationship.today._row', ['row' => $row])
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @empty
        <div class="taw-wrap">
            <div class="taw-none" style="display:block;">
                Nothing outstanding right now — the queue is clear.
            </div>
        </div>
        @endforelse

        {{-- Nothing left after filtering (all bands hidden) --}}
        <div class="taw-wrap" x-show="total === 0" x-cloak style="margin-top:8px;">
            <div class="taw-none" style="display:block;">
                No actions match this filter. <a href="#" @click.prevent="reset()">Clear filters</a>
            </div>
        </div>

        {{-- ══════════════════════════════════════════════════════════════
             COMPLETED TODAY — deliberately outside the queue so it can
             never inflate or confuse the active count. Collapsed by
             default; the engine still returns these rows unchanged.
        ══════════════════════════════════════════════════════════════ --}}
        @if(! empty($doneRows))
        <section class="taw-band" x-data="{ open: false }">
            <button type="button" class="taw-done-toggle" @click="open = !open">
                <i class="ti" :class="open ? 'ti-chevron-down' : 'ti-chevron-right'"></i>
                Completed today
                <span class="taw-done-n">{{ count($doneRows) }}</span>
                <span class="taw-done-hint" x-show="!open">handled — not part of the queue</span>
            </button>

            <div class="taw-wrap" x-show="open" x-cloak style="margin-top:6px;">
                <table class="taw-t">
                    <thead>
                        <tr>
                            <th style="width:78px;">Priority</th>
                            <th style="width:190px;">Patient</th>
                            <th>Outcome</th>
                            <th style="width:112px;">Due</th>
                            <th style="width:118px;">Owner</th>
                            <th style="width:172px;">Category</th>
                            <th style="width:96px;">Channel</th>
                            <th style="width:104px;">Status</th>
                            <th style="width:68px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($doneRows as $row)
                            @include('relationship.today._row', ['row' => $row])
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
        @endif

        {{-- ── Footer ── --}}
        <div class="taw-foot" style="border:1px solid #ece2f1;border-radius:10px;margin-top:8px;">
            <span>
                <strong class="taw-pgn" x-text="total">{{ count($rows) }}</strong>
                active {{ Str::plural('action', count($rows)) }} in the queue
            </span>
            @if(! empty($doneRows))
                <span class="taw-muted">· {{ count($doneRows) }} completed today</span>
            @endif
            @if($emptyCount > 0)
                <span class="taw-muted">· {{ $emptyCount }} {{ Str::plural('category', $emptyCount) }} with nothing to show</span>
            @endif
            @if(isset($groups['missed_calls_yesterday']) && $groups['missed_calls_yesterday']['count'] > 0 && $mode === 'today')
                <span>· <a href="{{ route('relationship.today.missed-calls') }}">View full missed-calls list</a></span>
            @endif
        </div>

    </div>{{-- /.taw --}}

    {{-- ══════════════════════════════════════════════════════════════════
         CALL WORKFLOW DRAWER (Alpine-driven)
    ══════════════════════════════════════════════════════════════════ --}}
    <template x-if="drawer.open">
        <div class="ta-drawer-backdrop" @click.self="closeDrawer()">
            <div class="ta-drawer" @click.stop>

                {{-- ── 1. HEADER — category · patient · action ───────────── --}}
                <div class="ta-drawer-header">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                        <span style="font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;opacity:0.75;"
                              x-text="categoryLabel(drawer.item?.category)"></span>
                        <button @click="closeDrawer()"
                                style="background:rgba(255,255,255,0.15);border:none;border-radius:6px;padding:3px 7px;color:#fff;cursor:pointer;font-size:13px;line-height:1;">
                            <i class="ti ti-x"></i>
                        </button>
                    </div>
                    <div class="ta-drawer-title" x-text="drawer.item?.patient_name"></div>
                    <p class="ta-drawer-sub" x-text="drawer.item?.suggested_action"></p>
                </div>

                <div class="ta-drawer-body">

                    {{-- ── 2. CONTEXT — one dense line, no cards ─────────── --}}
                    <div class="ta-ctx">
                        <div class="ta-ctx-item" x-show="drawer.item?.reason">
                            <span class="ta-ctx-label">Reason</span>
                            <span class="ta-ctx-value" x-text="drawer.item?.reason"></span>
                        </div>
                        <div class="ta-ctx-item">
                            <span class="ta-ctx-label">Priority</span>
                            <span class="ta-ctx-value" style="text-transform:capitalize;" x-text="drawer.item?.priority"></span>
                        </div>
                        <template x-if="drawer.item?.meta?.phone">
                            <div class="ta-ctx-item">
                                <span class="ta-ctx-label">Phone</span>
                                <span class="ta-ctx-value">
                                    <a :href="'tel:' + drawer.item.meta.phone" x-text="drawer.item.meta.phone"></a>
                                </span>
                            </div>
                        </template>
                        <template x-if="drawer.item?.meta?.appointment_date">
                            <div class="ta-ctx-item">
                                <span class="ta-ctx-label">Appointment</span>
                                <span class="ta-ctx-value" x-text="apptWhen"></span>
                            </div>
                        </template>
                        <template x-if="drawer.item?.meta?.treatment">
                            <div class="ta-ctx-item">
                                <span class="ta-ctx-label">Treatment</span>
                                <span class="ta-ctx-value" x-text="drawer.item.meta.treatment"></span>
                            </div>
                        </template>
                        <template x-if="drawer.item?.meta?.balance_due">
                            <div class="ta-ctx-item">
                                <span class="ta-ctx-label">Balance</span>
                                <span class="ta-ctx-value" x-text="'₹' + Number(drawer.item.meta.balance_due).toLocaleString('en-IN')"></span>
                            </div>
                        </template>
                        <template x-if="drawer.item?.meta?.due_date">
                            <div class="ta-ctx-item">
                                <span class="ta-ctx-label">Due</span>
                                <span class="ta-ctx-value" x-text="drawer.item.meta.due_date"></span>
                            </div>
                        </template>
                        <template x-if="drawer.item?.meta?.end_date">
                            <div class="ta-ctx-item">
                                <span class="ta-ctx-label">Expires</span>
                                <span class="ta-ctx-value" x-text="drawer.item.meta.end_date"></span>
                            </div>
                        </template>
                    </div>

                    {{-- ── 3. CALL GUIDANCE — what to say. Guidance ONLY;
                         never mixed with what happened or with task status. --}}
                    <div class="ta-guide" x-show="guidance || checklist.length > 0" x-cloak>
                        <i class="ti ti-bulb"></i>
                        <div style="min-width:0;">
                            <span x-text="guidance"></span>
                            <ul x-show="checklist.length > 0">
                                <template x-for="(point, i) in checklist" :key="i">
                                    <li x-text="point"></li>
                                </template>
                            </ul>
                        </div>
                    </div>

                    {{-- ── 4. CALL RESULT ────────────────────────────────────
                         One question first: did the call connect? PATIENT
                         RESPONSE only appears once it did. The direction
                         toggle is what makes the callback scenario work —
                         "Patient called us" records a NEW interaction against
                         the same open action; the earlier attempt is a
                         separate activity row and is never overwritten. --}}
                    <div class="ta-drawer-section" style="margin-top:12px;" x-show="!dismissMode && !closeMode && !historyOnly">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px;">
                            <div class="ta-drawer-section-label" style="margin:0;">Call result</div>
                            <div class="ta-seg">
                                <button type="button" :class="direction === 'outbound' ? 'on' : ''"
                                        @click="setDirection('outbound')">We called</button>
                                <button type="button" :class="direction === 'inbound' ? 'on' : ''"
                                        @click="setDirection('inbound')">Patient called us</button>
                            </div>
                        </div>

                        <div class="ta-opt-row" x-show="direction === 'outbound'">
                            <template x-for="r in contactResults" :key="r.key">
                                <button type="button" class="ta-opt"
                                        :class="contactResult === r.key ? 'on' : ''"
                                        @click="pickContactResult(r.key)"
                                        x-text="r.label"></button>
                            </template>
                        </div>

                        {{-- Which not-connected outcome, when the clinic has
                             configured more than one behind the same button. --}}
                        <div x-show="detailOptions.length > 1" x-cloak style="margin-top:9px;">
                            <div class="ta-drawer-section-label">Detail</div>
                            <div class="ta-opt-row">
                                <template x-for="o in detailOptions" :key="o.key">
                                    <button type="button" class="ta-opt"
                                            style="padding:5px 10px;font-size:12px;"
                                            :class="form.response === o.key ? 'on' : ''"
                                            @click="pickOutcome(o.key)"
                                            x-text="o.label"></button>
                                </template>
                            </div>
                        </div>

                        {{-- ── 5. PATIENT RESPONSE — contact happened only ── --}}
                        <div x-show="showPatientResponse" x-cloak style="margin-top:13px;">
                            <div class="ta-drawer-section-label">Patient response</div>
                            <div class="ta-opt-row">
                                <template x-for="o in answeredOptions" :key="o.key">
                                    <button type="button" class="ta-opt"
                                            :class="(form.response === o.key ? 'on ' : '') + (o.closes ? '' : 'ta-opt-warn')"
                                            @click="pickOutcome(o.key)"
                                            x-text="o.label"></button>
                                </template>
                            </div>
                        </div>

                        {{-- Plain-language consequence. Staff read what will
                             happen; they never pick a status themselves. --}}
                        <div class="ta-outcome-hint" :class="outcomeClosesTask ? 'done' : 'retry'"
                             x-show="form.response" x-cloak>
                            <i :class="outcomeClosesTask ? 'ti ti-circle-check' : 'ti ti-refresh'" style="margin-top:1px;flex-shrink:0;"></i>
                            <span x-text="outcomeHint"></span>
                        </div>

                        {{-- Note — secondary. Only on screen when the outcome
                             demands one, or staff ask for it. --}}
                        <div x-show="requiresNotes || showNote" x-cloak style="margin-top:11px;">
                            <label class="ta-form-label" x-text="requiresNotes ? 'Note (required for this response)' : 'Note (optional)'"></label>
                            <textarea class="ta-form-textarea" rows="2" style="min-height:54px;"
                                      placeholder="Anything worth recording from this call…"
                                      x-model="form.notes"
                                      :style="requiresNotes && !form.notes ? 'border-color:#e0a0a0;min-height:54px;' : 'min-height:54px;'"></textarea>
                        </div>

                    </div>

                    {{-- History lives OUTSIDE the call-result block on purpose:
                         a completed row opens straight into it with no form
                         attached (the green tick in the worklist), and an open
                         row can still glance at it mid-call. --}}
                    <div x-show="!dismissMode && !closeMode">
                        <div class="ta-more" x-show="!historyOnly">
                            <button type="button" x-show="!requiresNotes" @click="showNote = !showNote">
                                <i class="ti ti-note"></i>
                                <span x-text="showNote ? 'Hide note' : 'Add note'"></span>
                            </button>
                            <button type="button" @click="showHistory = !showHistory">
                                <i class="ti ti-history"></i>
                                <span x-text="(showHistory ? 'Hide history' : 'History') + (interactions.length ? ' (' + interactions.length + ')' : '')"></span>
                            </button>
                        </div>

                        {{-- ── 6. INTERACTION HISTORY — the owner audit trail.
                             Oldest first, so the original attempt stays at the
                             top: "10:14 Outbound — No answer — Neha" is still
                             there after "11:02 Inbound — Confirmed — Reception"
                             lands. Nothing here is ever overwritten. --}}
                        <div class="ta-hist" x-show="showHistory" x-cloak>
                            <template x-if="historyLoading">
                                <p style="font-size:12.5px;color:#9ca3af;margin:0;">Loading…</p>
                            </template>
                            <template x-if="!historyLoading && interactions.length === 0">
                                <p style="font-size:12.5px;color:#9ca3af;margin:0;">No interactions recorded yet.</p>
                            </template>
                            <template x-for="(h, i) in interactions" :key="i">
                                <div class="ta-hist-row">
                                    <span class="ta-hist-time" x-text="h.at"></span>
                                    <span class="ta-hist-dir"
                                          :class="h.kind === 'note' ? 'note' : (h.direction === 'inbound' ? 'in' : 'out')"
                                          x-text="h.kind === 'note' ? 'Note' : (h.direction === 'inbound' ? 'In' : 'Out')"></span>
                                    <span class="ta-hist-body">
                                        <span x-text="h.label"></span>
                                        <span class="ta-hist-who" x-text="' · ' + h.actor"></span>
                                        <span class="ta-hist-note" x-show="h.notes" x-text="h.notes"></span>
                                    </span>
                                </div>
                            </template>
                            <div style="font-size:11.5px;color:#9a7aaa;border-top:1px dashed #efe6f3;margin-top:6px;padding-top:6px;">
                                Current status:
                                <strong x-text="drawer.item?.done ? 'Completed' : 'Due'"></strong>
                            </div>
                        </div>
                    </div>

                </div>{{-- /drawer-body --}}

                {{-- Dismiss panel — this row should not have been here at all.
                     Requires a reason so a queue can't be cleared with a fake
                     call outcome. Unchanged behaviour, same endpoint. --}}
                <div x-show="dismissMode" x-cloak style="padding:12px 18px;border-top:1px solid #f3f4f6;background:#fafafa;">
                    <div class="ta-form-group">
                        <label class="ta-form-label">Why is this not needed?</label>
                        <select class="ta-form-select" x-model="dismissReason">
                            <option value="">— Select a reason —</option>
                            <template x-for="r in dismissReasons" :key="r.key">
                                <option :value="r.key" x-text="r.label"></option>
                            </template>
                        </select>
                    </div>
                    <div class="ta-form-group" x-show="dismissReason">
                        <label class="ta-form-label" x-text="dismissRequiresNotes ? 'Note (required)' : 'Note (optional)'"></label>
                        <textarea class="ta-form-textarea" rows="2" style="min-height:54px;" x-model="dismissNotes"></textarea>
                    </div>
                    <template x-if="dismissError">
                        <div style="background:#fdeaea;border:1px solid #f5a0a0;border-radius:8px;padding:8px 12px;font-size:12.5px;color:#b52020;margin-bottom:8px;">
                            <span x-text="dismissError"></span>
                        </div>
                    </template>
                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button class="ta-btn-cancel" @click="dismissMode = false" :disabled="dismissing">Back</button>
                        <button class="ta-btn-submit" style="flex:0 0 auto;padding:10px 18px;background:#8a5a5a;"
                                @click="confirmDismiss()"
                                :disabled="!dismissReason || dismissing || (dismissRequiresNotes && !dismissNotes)">
                            <span x-show="!dismissing">Dismiss</span>
                            <span x-show="dismissing">Dismissing…</span>
                        </button>
                    </div>
                </div>

                {{-- Stop chasing — the manual give-up after enough attempts.
                     Was the "Close" tab; demoted to a footer link because it is
                     the exception, not part of the normal call workflow. Same
                     endpoint, same server behaviour. --}}
                <div x-show="closeMode" x-cloak style="padding:12px 18px;border-top:1px solid #f3f4f6;background:#fafafa;">
                    <p style="font-size:12.5px;color:#6b7280;margin:0 0 9px;">
                        Marks this action finished without a call outcome — use it once you have
                        tried enough times, or the action has been handled some other way.
                    </p>
                    <div class="ta-form-group">
                        <label class="ta-form-label">Note (optional)</label>
                        <textarea class="ta-form-textarea" rows="2" style="min-height:54px;" x-model="closeNotes"></textarea>
                    </div>
                    <template x-if="closeError">
                        <div style="background:#fdeaea;border:1px solid #f5a0a0;border-radius:8px;padding:8px 12px;font-size:12.5px;color:#b52020;margin-bottom:8px;">
                            <span x-text="closeError"></span>
                        </div>
                    </template>
                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button class="ta-btn-cancel" @click="closeMode = false" :disabled="closing">Back</button>
                        <button class="ta-btn-submit" style="flex:0 0 auto;padding:10px 18px;background:#4a7a5a;"
                                @click="confirmClose()" :disabled="closing">
                            <span x-show="!closing">Stop chasing</span>
                            <span x-show="closing">Saving…</span>
                        </button>
                    </div>
                </div>

                {{-- Footer — one primary action. Save records what happened;
                     the SERVER decides whether that completes the action. --}}
                <div class="ta-drawer-footer" x-show="!dismissMode && !closeMode" style="flex-direction:column;align-items:stretch;gap:8px;">
                    <template x-if="submitError">
                        <div style="background:#fdeaea;border:1px solid #f5a0a0;border-radius:8px;padding:8px 12px;font-size:12.5px;color:#b52020;">
                            <i class="ti ti-alert-circle"></i>
                            <span x-text="submitError"></span>
                        </div>
                    </template>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <template x-if="!historyOnly">
                            <button type="button" @click="dismissMode = true"
                                    style="background:none;border:none;color:#9a7aaa;font-size:12px;cursor:pointer;text-decoration:underline;padding:0;">
                                Not needed
                            </button>
                        </template>
                        <template x-if="!historyOnly">
                            <button type="button" @click="closeMode = true"
                                    style="background:none;border:none;color:#9a7aaa;font-size:12px;cursor:pointer;text-decoration:underline;padding:0;">
                                Stop chasing
                            </button>
                        </template>
                        <span style="flex:1 1 auto;"></span>
                        <button class="ta-btn-cancel" @click="closeDrawer()"
                                x-text="historyOnly ? 'Close' : 'Cancel'"></button>
                        <template x-if="!historyOnly">
                            <button class="ta-btn-submit" style="flex:0 0 auto;padding:10px 22px;"
                                    @click="submitLog()"
                                    :disabled="!canSave || submitting">
                                <span x-show="!submitting"><i class="ti ti-check"></i> Save</span>
                                <span x-show="submitting"><i class="ti ti-loader-2" style="animation:spin 1s linear infinite;"></i> Saving…</span>
                            </button>
                        </template>
                    </div>
                </div>

            </div>{{-- /drawer --}}
        </div>{{-- /backdrop --}}
    </template>

</div>{{-- /x-data --}}

<style>
@keyframes spin { to { transform: rotate(360deg); } }
</style>

{{-- ══════════════════════════════════════════════════════════════════════
     ALPINE.JS — TodayActions component
══════════════════════════════════════════════════════════════════════ --}}
<script>
// Checklists and response options injected from PHP config
const CHECKLISTS    = @json($checklists);
const RESPONSE_OPTS = @json($responseOpts);
const NEXT_ACTIONS  = @json($nextActions);
const REQUIRES_NOTES = @json($requiresNotesMap ?? []);

// CALL RESULT vocabulary (2026-08-26). category => bucket => {key: label}.
// Built server-side from the SAME ActionOptionList rows Settings > Call
// Outcomes manages — the drawer only decides which of the four buttons each
// existing outcome sits under. See TodayController::buildCallResults().
const CALL_RESULTS  = @json($callResults ?? []);
// category => {key: bool} — does logging this outcome complete the action?
// Straight off action_option_lists.closes_task, so the drawer can say in
// plain words what will happen before staff press Save.
const CLOSES_TASK   = @json($closesTaskMap ?? []);
const CONTACT_RESULTS = @json(\App\Http\Controllers\Relationship\TodayController::CONTACT_RESULTS);

// Category labels (mirroring controller constant)
const CATEGORY_LABELS = {
    new_enquiries:                 'New Enquiries',
    lead_followups:                'Lead Follow-ups',
    opportunities:                 'Treatment Opportunities',
    recall_calls:                  'Recall Calls',
    follow_up_calls:               'Follow-up Calls',
    appointment_reminders:         'Appointment Reminders',
    missed_calls_yesterday:        "Yesterday's Missed Calls",
    missed_appointments_yesterday: "Yesterday's Missed Appointments",
    pending_estimates:             'Pending Estimates',
    membership_renewals:           'Membership Renewals',
    birthdays:                     'Birthday Wishes',
    lab_ready:                     'Lab Work Ready',
    payment_reminders:             'Payment Reminders',
    logged_communications:         'Other Calls',
};

// ── Worklist (search · category filter · client-side paging) ──────────
// Presentation only: it filters and pages the rows already rendered by
// Blade. No fetch, no endpoint, no change to what the server returned.
function taWorklist(rows) {
    return {
        rows:  rows || [],
        q:     '',
        cat:   'all',
        per:   50,
        page:  1,
        total: 0,
        pages: 1,
        vis:   {},
        bands: {},

        init() {
            this.recompute();
            this.$watch('q',    () => { this.page = 1; this.recompute(); });
            this.$watch('cat',  () => { this.page = 1; this.recompute(); });
            this.$watch('page', () => { this.recompute(); });
        },

        matches() {
            const needle = this.q.trim().toLowerCase();
            return this.rows.filter(r =>
                (this.cat === 'all' || r.cat === this.cat) &&
                (needle === '' || (r.s || '').indexOf(needle) !== -1)
            );
        },

        recompute() {
            // Every matching row is shown — the queue is banded, not paged,
            // so a section never hides work behind a page number.
            const list = this.matches();
            this.total = list.length;

            const vis = {};
            const bands = {};
            list.forEach(r => {
                vis[r.id] = true;
                bands[r.band] = (bands[r.band] || 0) + 1;
            });
            this.vis   = vis;
            this.bands = bands;
        },

        /** Live count for a band header, honouring search + chip filters. */
        bandCount(band) { return this.bands[band] || 0; },

        /** A band with nothing left after filtering hides its whole section. */
        bandVisible(band) { return (this.bands[band] || 0) > 0; },

        show(id)  { return this.vis[id] === true; },
        setCat(c) { this.cat = c; },
        reset()   { this.q = ''; this.cat = 'all'; this.page = 1; this.recompute(); },
        from()    { return this.total === 0 ? 0 : ((this.page - 1) * this.per) + 1; },
        to()      { return Math.min(this.page * this.per, this.total); },
    };
}

function todayActions() {
    return {

        // ── Drawer state ────────────────────────────────────────────────
        drawer: {
            open: false,
            item: null,
            itemId: null,
        },

        // ── CALL RESULT (redesign 2026-08-26) ───────────────────────────
        // Two steps, not three overlapping concepts. `direction` is who
        // placed the call; `contactResult` is whether it connected;
        // `form.response` is the outcome key actually submitted — always an
        // existing ActionOptionList key, never anything new.
        //
        // The Log/Close tab strip that used to live here is gone: Log was the
        // normal path and Close the rare give-up, so Close moved to a footer
        // link (closeMode) and stopped competing for attention with the work
        // reception actually does all day.
        direction:     'outbound',
        contactResult: '',

        // ── "Stop chasing" panel (was the Close tab) ────────────────────
        closeMode:  false,
        closeNotes: '',
        closing:    false,
        closeError: '',

        // ── Call guidance (staff-facing only — never an outcome) ────────
        guidance:  '',
        checklist: [],

        // Read-only mode: opened from the green tick on a completed row.
        // Same drawer, same history read — just no form, because there is
        // nothing left to record.
        historyOnly: false,

        // ── Secondary, collapsed by default ─────────────────────────────
        showNote:       false,
        showHistory:    false,
        interactions:   [],
        historyLoading: false,

        // ── Response options (for current category) ─────────────────────
        responseOptions: {},

        // ── Form ────────────────────────────────────────────────────────
        form: {
            response:    '',
            next_action: '',
            notes:       '',
        },

        // ── Derived next action label ───────────────────────────────────
        nextActionLabel: '',

        // ── Whether the currently-selected outcome requires a note ───────
        requiresNotes: false,

        // ── Dismiss sub-state (clears a row without a call outcome) ─────
        dismissMode:   false,
        dismissReason: '',
        dismissNotes:  '',
        dismissing:    false,
        dismissError:  '',
        dismissReasons: @json($dismissReasons ?? []),

        // The standalone Suggestion / Patient Response note editor was removed
        // from this drawer on 2026-08-26 — it duplicated the outcome note and
        // was the single biggest source of drawer height. The endpoint
        // (today.notes.add) and the Lead & Opportunity Pipeline log that uses
        // it are untouched; the notes themselves still appear here, inside
        // the interaction history.

        // ── Per-item actioned tracker (itemId → bool) ───────────────────
        actioned: {},

        // ── Per-item last logged response label (itemId → "Confirmed · 11:43 AM")
        // Live within this page load; on refresh the server re-renders the
        // same info from the activity log / dismissal rows. ──────────────
        lastResponse: {},

        // ── Birthday WhatsApp send state (itemId → bool / message) ──────
        sendingWhatsapp: {},
        whatsappError:   {},

        // ── Submission state ────────────────────────────────────────────
        submitting:  false,
        submitError: '',

        // ─────────────────────────────────────────────────────────────────
        // Open the Call Workflow drawer for a given item
        // ─────────────────────────────────────────────────────────────────
        openDrawer(item, itemId) {
            const cat = item.category;

            this.drawer.item   = item;
            this.drawer.itemId = itemId;
            this.drawer.open   = true;

            // Reset the two secondary panels
            this.closeMode  = false;
            this.closeNotes = '';
            this.closing    = false;
            this.closeError = '';

            // Call guidance — staff-facing only. `ai_summary` is real
            // engine-supplied context when present; the bullet list is the
            // clinic's own config('relationship_rules.call_checklists').
            // The singular fallback is deliberate: the config ships an
            // 'appointment_reminder' key while the engine emits
            // 'appointment_reminders', so that guidance never reached the
            // drawer before.
            this.guidance  = item?.meta?.ai_summary || '';
            this.checklist = CHECKLISTS[cat] || CHECKLISTS[(cat || '').replace(/s$/, '')] || [];

            // Outcome vocabulary for this category (labels for the row badge)
            this.responseOptions = RESPONSE_OPTS[cat] || RESPONSE_OPTS['default'] || {};

            // Reset the call-result form
            this.direction       = 'outbound';
            this.contactResult   = '';
            this.form            = { response: '', next_action: '', notes: '' };
            this.nextActionLabel = '';
            this.requiresNotes   = false;
            this.submitError     = '';
            this.showNote        = false;

            // Reset dismiss sub-state
            this.dismissMode   = false;
            this.dismissReason = '';
            this.dismissNotes  = '';
            this.dismissError  = '';

            // History stays collapsed, but load it now so the count is honest
            // the moment the drawer opens.
            this.historyOnly  = false;
            this.showHistory  = false;
            this.interactions = [];
            this.fetchHistory();
        },

        // ─────────────────────────────────────────────────────────────────
        // Green tick on a completed row -> the story of that action.
        // Deliberately the SAME drawer rather than a second component: one
        // header, one history read, one place to look. No form is rendered,
        // so a finished action can't be silently re-logged from here.
        // ─────────────────────────────────────────────────────────────────
        openHistory(item, itemId) {
            this.openDrawer(item, itemId);

            this.historyOnly = true;
            this.showHistory = true;
        },

        // ─────────────────────────────────────────────────────────────────
        // The four CALL RESULT buttons, minus any the clinic has no outcome
        // configured for. We never render a button with nothing behind it:
        // submitting an outcome key with no ActionOptionList row means no
        // closes_task rule, and therefore no defined task status.
        // ─────────────────────────────────────────────────────────────────
        get resultBuckets() {
            const cat = this.drawer.item?.category;
            return CALL_RESULTS[cat] || CALL_RESULTS['default'] || {};
        },

        get contactResults() {
            const buckets = this.resultBuckets;
            return Object.entries(CONTACT_RESULTS)
                .filter(([key]) => Object.keys(buckets[key] || {}).length > 0)
                .map(([key, label]) => ({ key, label }));
        },

        /** PATIENT RESPONSE choices — the category's connected outcomes. */
        get answeredOptions() {
            const cat  = this.drawer.item?.category;
            const opts = this.resultBuckets['answered'] || {};

            return Object.entries(opts)
                // Reached via the "Patient called us" toggle instead, so it is
                // not offered as an outbound response.
                .filter(([key]) => key !== 'patient_called_back_confirmed')
                .map(([key, label]) => ({
                    key,
                    label,
                    closes: (CLOSES_TASK[cat] || {})[key] !== false,
                }));
        },

        /** Secondary chips when one button covers more than one outcome. */
        get detailOptions() {
            if (!this.contactResult || this.contactResult === 'answered') return [];

            const opts = this.resultBuckets[this.contactResult] || {};
            return Object.entries(opts).map(([key, label]) => ({ key, label }));
        },

        /** "26 Aug 2026 · 5:30 PM" — the time matters as much as the date. */
        get apptWhen() {
            const m = this.drawer.item?.meta;
            if (!m?.appointment_date) return '';

            const raw = m.appointment_time || m.time;
            if (!raw) return m.appointment_date;

            const [h, min] = String(raw).split(':');
            const hh = parseInt(h, 10);
            if (isNaN(hh)) return m.appointment_date;

            const suffix = hh >= 12 ? 'PM' : 'AM';
            const h12    = hh % 12 === 0 ? 12 : hh % 12;

            return m.appointment_date + ' · ' + h12 + ':' + (min ?? '00') + ' ' + suffix;
        },

        get showPatientResponse() {
            return this.direction === 'inbound' || this.contactResult === 'answered';
        },

        /** Does the chosen outcome complete the action? The server decides;
         *  this mirrors the same column so staff are told in advance. */
        get outcomeClosesTask() {
            const cat = this.drawer.item?.category;
            return (CLOSES_TASK[cat] || {})[this.form.response] !== false;
        },

        /** Plain language. No CRM terms, no database states. */
        get outcomeHint() {
            if (!this.form.response) return '';

            const next = NEXT_ACTIONS[this.form.response] || '';

            if (!this.outcomeClosesTask) {
                return 'Attempt recorded. This stays due so someone can try again.'
                    + (next ? ' Next: ' + next.toLowerCase() + '.' : '');
            }

            return 'Marks this action complete.'
                + (next ? ' Next: ' + next.toLowerCase() + '.' : '');
        },

        get canSave() {
            if (!this.form.response) return false;
            if (this.requiresNotes && !this.form.notes) return false;
            return true;
        },

        // ─────────────────────────────────────────────────────────────────
        setDirection(dir) {
            this.direction = dir;

            if (dir === 'inbound') {
                // The patient rang us — contact happened by definition, so
                // skip straight to what they said. This is the callback path:
                // it records a NEW interaction against the SAME open action.
                // The earlier outbound attempt is its own activity row and is
                // never edited or overwritten.
                this.contactResult = 'answered';
            } else {
                this.contactResult = '';
            }

            this.pickOutcome('');
        },

        pickContactResult(key) {
            this.contactResult = key;

            const opts = Object.keys(this.resultBuckets[key] || {});

            // A bucket with exactly one outcome behind it needs no second
            // step — the button IS the answer.
            this.pickOutcome(key === 'answered' ? '' : (opts.length ? opts[0] : ''));
        },

        pickOutcome(key) {
            this.form.response = key;
            this.updateNextAction();
        },

        // ─────────────────────────────────────────────────────────────────
        // Interaction history — the owner/admin audit trail for this action.
        // Same endpoint the note log already used; it now returns the call
        // events alongside the notes. Read-only.
        // ─────────────────────────────────────────────────────────────────
        async fetchHistory() {
            const item = this.drawer.item;
            if (!item?.patient_id && !item?.lead_id) {
                this.interactions = [];
                return;
            }

            this.historyLoading = true;
            try {
                const params = new URLSearchParams({
                    patient_id: item.patient_id ?? '',
                    lead_id:    item.lead_id ?? '',
                    category:   item.category ?? '',
                });
                const res  = await fetch('{{ route('relationship.today.notes.index') }}?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                this.interactions = data.interactions || [];
            } catch (err) {
                // Silent — the history panel just stays empty. Never block the
                // call workflow on an audit read.
            } finally {
                this.historyLoading = false;
            }
        },

        // ─────────────────────────────────────────────────────────────────
        // Whether the current outcome-required-note applies to the chosen
        // dismiss reason (mirrors requiresNotes, but for DISMISS_REASONS).
        // ─────────────────────────────────────────────────────────────────
        get dismissRequiresNotes() {
            const r = this.dismissReasons.find(r => r.key === this.dismissReason);
            return !!(r && r.requires_notes);
        },

        // ─────────────────────────────────────────────────────────────────
        // Which record backs this card? ONE resolver, used by Log, Close and
        // Dismiss alike, mirroring the server's category→record mapping:
        //   queue-backed (recall_calls / missed_calls_yesterday /
        //     logged_communications)      → meta.comm_queue_id
        //   follow_up_calls               → meta.follow_up_id
        //   new_enquiries / lead_followups→ lead_id   (Lead-keyed)
        //   birthdays                     → patient_id (Patient-keyed)
        //   everything else               → meta.id
        // Added 2026-07-26 during the Phase 1 dismiss defect fix, so the three
        // actions can never drift apart again.
        // ─────────────────────────────────────────────────────────────────
        subjectIdFor(item) {
            if (!item) return null;

            const queueBacked = ['recall_calls', 'missed_calls_yesterday', 'logged_communications'];
            if (queueBacked.includes(item.category))       return item.meta?.comm_queue_id ?? null;
            if (item.category === 'follow_up_calls')       return item.meta?.follow_up_id ?? null;
            if (item.category === 'new_enquiries' ||
                item.category === 'lead_followups')        return item.lead_id ?? null;
            if (item.category === 'birthdays')             return item.patient_id ?? null;

            return item.meta?.id ?? null;
        },

        // ─────────────────────────────────────────────────────────────────
        // Submit a Dismiss instead of a logged call outcome.
        // ─────────────────────────────────────────────────────────────────
        async confirmDismiss() {
            if (!this.dismissReason || this.dismissing) return;
            if (this.dismissRequiresNotes && !this.dismissNotes) return;

            this.dismissing   = true;
            this.dismissError = '';

            const item   = this.drawer.item;
            const itemId = this.drawer.itemId;

            // Subject resolution MUST mirror the server's category→record map
            // (TodayController: QUEUE_BACKED_CATEGORIES / follow_up_calls /
            // DISMISSIBLE_MODELS). Fixed 2026-07-26: this used a two-way check
            // that omitted logged_communications and follow_up_calls, and never
            // handled the lead- and patient-keyed categories — so Dismiss failed
            // with "missing reference id" on five of the fifteen categories.
            // logAction()/confirmClose() already resolve via subjectIdFor().
            const subjectId = this.subjectIdFor(item);

            if (!subjectId) {
                this.dismissError = 'This item cannot be dismissed (missing reference id).';
                this.dismissing = false;
                return;
            }

            try {
                const res = await fetch('{{ route('relationship.today.dismiss') }}', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body:    JSON.stringify({
                        _token:          document.querySelector('meta[name="csrf-token"]').content,
                        category:        item.category,
                        subject_id:      subjectId,
                        reason_key:      this.dismissReason,
                        notes:           this.dismissNotes,
                        patient_id:      item.patient_id,
                        relationship_id: item.relationship_id,
                    }),
                });

                const data = await res.json();

                if (data.success) {
                    this.actioned[itemId] = true;
                    this.closeDrawer();
                } else {
                    this.dismissError = data.message || 'Could not dismiss. Please try again.';
                }
            } catch (err) {
                this.dismissError = 'Network error. Please check your connection.';
            } finally {
                this.dismissing = false;
            }
        },

        // ─────────────────────────────────────────────────────────────────
        closeDrawer() {
            this.drawer.open  = false;
            this.drawer.item  = null;
            this.closeMode    = false;
            this.dismissMode  = false;
            this.historyOnly  = false;
            this.showHistory  = false;
            this.showNote     = false;
        },

        // ─────────────────────────────────────────────────────────────────
        // Birthday Wishes only — one-click WhatsApp send, no drawer/checklist.
        // Marks the row actioned (same convention as a logged call) on success.
        // ─────────────────────────────────────────────────────────────────
        async sendBirthdayWhatsapp(item, itemId) {
            if (this.sendingWhatsapp[itemId]) return;

            this.sendingWhatsapp[itemId] = true;
            this.whatsappError[itemId]   = '';

            try {
                const res = await fetch('{{ route('relationship.today.birthday-whatsapp') }}', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body:    JSON.stringify({
                        _token:     document.querySelector('meta[name="csrf-token"]').content,
                        patient_id: item.patient_id,
                    }),
                });

                const data = await res.json();

                if (data.success) {
                    // Interim click-to-chat: open WhatsApp with the greeting
                    // pre-filled so staff can press send from their own account.
                    if (data.url) { window.open(data.url, '_blank', 'noopener'); }
                    this.actioned[itemId] = true;
                    this.lastResponse[itemId] = 'WhatsApp opened · '
                        + new Date().toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' });
                } else {
                    this.whatsappError[itemId] = data.message || 'Could not send. Please try again.';
                    alert(this.whatsappError[itemId]);
                }
            } catch (err) {
                this.whatsappError[itemId] = 'Network error. Please check your connection.';
                alert(this.whatsappError[itemId]);
            } finally {
                this.sendingWhatsapp[itemId] = false;
            }
        },

        // ─────────────────────────────────────────────────────────────────
        // Update next action label when response changes
        // ─────────────────────────────────────────────────────────────────
        updateNextAction() {
            this.nextActionLabel  = this.form.response ? (NEXT_ACTIONS[this.form.response] || '') : '';
            this.form.next_action = this.nextActionLabel;

            const cat = this.drawer.item?.category;
            this.requiresNotes = !!((REQUIRES_NOTES[cat] || {})[this.form.response]);

            // A required note must be visible the moment it becomes required.
            if (this.requiresNotes) { this.showNote = true; }
        },

        // ─────────────────────────────────────────────────────────────────
        // Human-readable category label
        // ─────────────────────────────────────────────────────────────────
        categoryLabel(key) {
            return CATEGORY_LABELS[key] || (key || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        },

        // ─────────────────────────────────────────────────────────────────
        // Submit the logged call to the server
        // ─────────────────────────────────────────────────────────────────
        async submitLog() {
            if (!this.canSave || this.submitting) return;

            this.submitting  = true;
            this.submitError = '';

            const item   = this.drawer.item;
            const itemId = this.drawer.itemId;

            // subject_id identifies which record this row is backed by, so
            // the server can auto-close it when the logged outcome's
            // closes_task is true (2026-07-10). Mirrors confirmClose()'s
            // identical subject resolution just below.
            // One shared resolver — see subjectIdFor(). Previously duplicated
            // here and in confirmClose(), and a third (stale) copy in
            // confirmDismiss() caused the "missing reference id" defect.
            const subjectId = this.subjectIdFor(item);

            const payload = {
                _token:          document.querySelector('meta[name="csrf-token"]').content,
                category:        item.category,
                patient_id:      item.patient_id,
                lead_id:         item.lead_id,
                relationship_id: item.relationship_id,
                subject_id:      subjectId,
                response:        this.form.response,
                next_action:     this.form.next_action,
                notes:           this.form.notes,
                // Who placed the call. 'inbound' is the callback case: the
                // server records it as a distinct interaction and resolves
                // THIS open action, leaving the earlier attempt intact.
                direction:       this.direction,
            };

            try {
                const res = await fetch('{{ route('relationship.today.action') }}', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body:    JSON.stringify(payload),
                });

                const data = await res.json();

                if (data.success) {
                    // 2026-07-10: whether the row fades as done now depends
                    // on the outcome — resolved outcomes (booked, confirmed,
                    // declined...) auto-close server-side and report
                    // `closed: true`; "needs retry" outcomes (no answer,
                    // still deciding...) report `closed: false` and the row
                    // stays for staff to log again or Close manually later.
                    // Either way the logged response shows on the row
                    // immediately (2026-07-14) so staff can see the call
                    // happened and what the patient said.
                    this.lastResponse[itemId] =
                        (this.direction === 'inbound' ? 'Patient called back — ' : '')
                        + (this.responseOptions[this.form.response] || this.form.response)
                        + ' · ' + new Date().toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' });
                    if (data.closed) {
                        this.actioned[itemId] = true;
                    }
                    this.closeDrawer();
                } else {
                    this.submitError = data.message || 'Could not save. Please try again.';
                }
            } catch (err) {
                this.submitError = 'Network error. Please check your connection.';
            } finally {
                this.submitting = false;
            }
        },

        // ─────────────────────────────────────────────────────────────────
        // Close tab — explicit "done with this one," no outcome required.
        // Reuses the exact same per-category subject resolution submitLog()
        // uses (mirrors confirmDismiss() too) so the server's
        // closeUnderlyingRecord() can suppress/close the right record.
        // ─────────────────────────────────────────────────────────────────
        async confirmClose() {
            if (this.closing) return;

            this.closing    = true;
            this.closeError = '';

            const item   = this.drawer.item;
            const itemId = this.drawer.itemId;

            // One shared resolver — see subjectIdFor(). Previously duplicated
            // here and in confirmClose(), and a third (stale) copy in
            // confirmDismiss() caused the "missing reference id" defect.
            const subjectId = this.subjectIdFor(item);

            try {
                const res = await fetch('{{ route('relationship.today.close') }}', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body:    JSON.stringify({
                        _token:          document.querySelector('meta[name="csrf-token"]').content,
                        category:        item.category,
                        patient_id:      item.patient_id,
                        lead_id:         item.lead_id,
                        relationship_id: item.relationship_id,
                        subject_id:      subjectId,
                        notes:           this.closeNotes,
                    }),
                });

                const data = await res.json();

                if (data.success) {
                    this.actioned[itemId] = true;
                    this.lastResponse[itemId] = 'Closed · '
                        + new Date().toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' });
                    this.closeDrawer();
                } else {
                    this.closeError = data.message || 'Could not close. Please try again.';
                }
            } catch (err) {
                this.closeError = 'Network error. Please check your connection.';
            } finally {
                this.closing = false;
            }
        },

    };
}
</script>

@endsection
