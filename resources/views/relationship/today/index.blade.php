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
        width: 460px;
        max-width: 100%;
        max-height: 88vh;
        background: #fff;
        display: flex;
        flex-direction: column;
        border-radius: 16px;
        box-shadow: 0 20px 60px rgba(26, 3, 32, 0.25);
        overflow: hidden;
    }

    .ta-drawer-header {
        padding: 18px 20px 14px;
        background: linear-gradient(135deg, #4e0a53, #6a0f70);
        color: #fff;
        flex-shrink: 0;
    }

    .ta-drawer-title {
        font-family: 'Cormorant Garamond', Georgia, serif;
        font-size: 20px;
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
        padding: 20px;
    }

    .ta-drawer-section {
        margin-bottom: 20px;
    }

    .ta-drawer-section-label {
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        color: #9a7aaa;
        margin-bottom: 8px;
    }

    .ta-summary-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 8px;
    }

    .ta-summary-item {
        background: #f8f4fc;
        border-radius: 8px;
        padding: 8px 10px;
    }

    .ta-summary-item-label {
        font-size: 10px;
        color: #9a7aaa;
        text-transform: uppercase;
        letter-spacing: 0.06em;
    }

    .ta-summary-item-value {
        font-size: 13px;
        font-weight: 600;
        color: #1a0320;
        margin-top: 2px;
    }

    /* Checklist */
    .ta-checklist { list-style: none; padding: 0; margin: 0; }

    .ta-checklist li {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid #f5f0fa;
        font-size: 13px;
        color: #2d0538;
    }

    .ta-checklist li:last-child { border-bottom: none; }

    .ta-checklist-check {
        width: 16px;
        height: 16px;
        border: 1.5px solid #b95cb7;
        border-radius: 4px;
        flex-shrink: 0;
        margin-top: 1px;
        cursor: pointer;
        accent-color: #6a0f70;
    }

    /* Log response form */
    .ta-form-group { margin-bottom: 14px; }

    .ta-form-label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: #4e0a53;
        margin-bottom: 5px;
    }

    .ta-form-select, .ta-form-textarea {
        width: 100%;
        border: 1.5px solid #dfc5e1;
        border-radius: 8px;
        padding: 8px 10px;
        font-size: 13px;
        color: #1a0320;
        font-family: 'DM Sans', system-ui, sans-serif;
        background: #fff;
        outline: none;
        transition: border-color 150ms;
    }

    .ta-form-select:focus, .ta-form-textarea:focus {
        border-color: #6a0f70;
        box-shadow: 0 0 0 3px rgba(106, 15, 112, 0.10);
    }

    .ta-form-textarea { resize: vertical; min-height: 72px; }

    /* Next action suggestion box */
    .ta-next-action-box {
        background: #f0f9f4;
        border: 1px solid #b8e0ca;
        border-radius: 8px;
        padding: 10px 14px;
        font-size: 13px;
        color: #1a7a45;
        display: flex;
        align-items: flex-start;
        gap: 8px;
        margin-top: 12px;
    }

    .ta-drawer-footer {
        padding: 14px 20px;
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
    .taw-ib--ok { border-color:#cfe9db; background:#f2fbf6; color:#1a7a45; cursor:default; }
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
                        if ($kind === 'appt' && ! empty($item['meta']['appointment_time'])) {
                            $dueTip .= ' at ' . $item['meta']['appointment_time'];
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
                    'sortDone' => $done ? 1 : 0,
                    'sortPr' => $prRank[$item['priority'] ?? 'low'] ?? 3,
                    'sortCat' => $group['priority'] ?? 99,
                ];
            }
        }

        // Worked order: open work first, then urgency, then the clinic's own
        // category order, then oldest due date.
        usort($rows, fn ($a, $b) =>
            [$a['sortDone'], $a['sortPr'], $a['sortCat'], $a['dueSort']]
            <=> [$b['sortDone'], $b['sortPr'], $b['sortCat'], $b['dueSort']]);

        $rowMeta = array_map(fn ($r) => [
            'id'  => $r['id'],
            'cat' => $r['cat'],
            's'   => mb_strtolower(($r['item']['patient_name'] ?? '') . ' ' . $r['doText'] . ' ' . $r['whyText'] . ' ' . $r['catLabel']),
        ], $rows);

        $activeGroups = collect($groups)->filter(fn ($g) => $g['count'] > 0 || ($g['done_count'] ?? 0) > 0);
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
                <span class="taw-cat-n">{{ $group['count'] + ($group['done_count'] ?? 0) }}</span>
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

        {{-- ── The worklist ─────────────────────────────────────────────── --}}
        @if(! empty($rows))
        <div class="taw-wrap">
            <div class="taw-scroll">
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
                    @foreach($rows as $row)
                        @php
                            $item     = $row['item'];
                            $itemId   = $row['id'];
                            $done     = $row['done'];
                            $lastCall = $row['lastCall'];
                            $pr       = $item['priority'] ?? 'low';
                            // Drawer payload: the same item with only the two
                            // display strings cleaned. 'reason' and
                            // 'suggested_action' are shown, never submitted —
                            // logAction/dismiss read category + ids only.
                            $drawerItem = array_merge($item, [
                                'reason'           => $row['whyText'] ?: ($item['reason'] ?? ''),
                                'suggested_action' => $row['doText'],
                            ]);
                        @endphp
                        <tr id="item-{{ $itemId }}"
                            class="{{ $done ? 'is-done' : '' }}"
                            :class="actioned['{{ $itemId }}'] ? 'is-done' : ''"
                            x-show="show('{{ $itemId }}')">

                            {{-- 1 · PRIORITY --}}
                            <td>
                                <span class="taw-pr taw-pr--{{ $pr }}" title="{{ ucfirst($pr) }} priority">
                                    <span class="taw-dot"></span>{{ ucfirst($pr) }}
                                </span>
                            </td>

                            {{-- 2 · PATIENT --}}
                            <td>
                                <div class="taw-name" title="{{ $item['patient_name'] }}">
                                    <a href="{{ $item['link'] }}">{{ $item['patient_name'] }}</a>
                                </div>
                            </td>

                            {{-- 3 · ACTION & REASON --}}
                            <td>
                                @if($done)
                                    <div class="taw-do" title="{{ $done['label'] }}{{ !empty($done['notes']) ? ' — ' . $done['notes'] : '' }}">{{ $done['label'] }}</div>
                                    <span class="taw-why taw-why--ok">{{ $row['whyText'] }}{{ !empty($done['at']) ? ' · ' . $done['at'] : '' }}</span>
                                @else
                                    <div class="taw-do" title="{{ $row['doText'] }}">{{ $row['doText'] }}</div>
                                    @if($lastCall)
                                        <span class="taw-why taw-why--try" x-show="!lastResponse['{{ $itemId }}']"
                                              title="{{ $row['whyText'] }} — last attempt: {{ $lastCall['label'] }}{{ !empty($lastCall['at']) ? ' at ' . $lastCall['at'] : '' }}{{ !empty($lastCall['notes']) ? ' — ' . $lastCall['notes'] : '' }}">
                                            {{ $row['whyText'] }} · last attempt: {{ $lastCall['label'] }}{{ !empty($lastCall['at']) ? ' ' . $lastCall['at'] : '' }}
                                        </span>
                                    @else
                                        <span class="taw-why" x-show="!lastResponse['{{ $itemId }}']" title="{{ $row['whyText'] }}">{{ $row['whyText'] }}</span>
                                    @endif
                                    <span class="taw-why taw-why--ok" x-show="lastResponse['{{ $itemId }}']" x-cloak>
                                        <span x-text="lastResponse['{{ $itemId }}']"></span>
                                    </span>
                                @endif
                            </td>

                            {{-- 4 · DUE --}}
                            <td><span class="taw-due {{ $row['dueCls'] }}" title="{{ $row['dueTip'] }}">{{ $row['dueTxt'] }}</span></td>

                            {{-- 5 · OWNER --}}
                            <td>
                                @if($row['owner'])
                                    <span class="taw-owner" title="Handled by {{ $row['owner'] }}">{{ $row['owner'] }}</span>
                                @else
                                    <span class="taw-owner taw-owner--none" title="Not yet picked up by anyone">Unassigned</span>
                                @endif
                            </td>

                            {{-- 6 · CATEGORY --}}
                            <td><span class="taw-tag" title="{{ $row['catLabel'] }}">{{ $row['catLabel'] }}</span></td>

                            {{-- 7 · CHANNEL --}}
                            <td><span class="taw-ch"><i class="ti {{ $row['chIcon'] }}"></i>{{ $row['chLabel'] }}</span></td>

                            {{-- 8 · STATUS --}}
                            <td>
                                <span class="taw-st {{ $row['stCls'] }}">
                                    <span x-show="!actioned['{{ $itemId }}']">{{ $row['stTxt'] }}</span>
                                    <span x-show="actioned['{{ $itemId }}']" x-cloak>Done</span>
                                </span>
                            </td>

                            {{-- 9 · ACTIONS --}}
                            <td>
                                <div class="taw-acts">
                                    @if($done)
                                        <span class="taw-ib taw-ib--ok" title="Done — {{ $done['label'] }}{{ !empty($done['at']) ? ' at ' . $done['at'] : '' }}"><i class="ti ti-check"></i></span>
                                    @elseif($mode === 'past')
                                        @php $outcome = $item['meta']['outcome'] ?? null; @endphp
                                        <span class="taw-ib taw-ib--ok" title="{{ $outcome ? ucwords(str_replace('_', ' ', $outcome)) : 'Completed' }}"><i class="ti ti-check"></i></span>
                                    @elseif(($item['primary_action'] ?? null) === 'whatsapp')
                                        <template x-if="!actioned['{{ $itemId }}']">
                                            <button type="button" class="taw-ib taw-ib--go" title="Send WhatsApp birthday greeting"
                                                    :disabled="sendingWhatsapp['{{ $itemId }}']"
                                                    @click="sendBirthdayWhatsapp({{ json_encode($drawerItem) }}, '{{ $itemId }}')">
                                                <i class="ti" :class="sendingWhatsapp['{{ $itemId }}'] ? 'ti-loader-2' : 'ti-brand-whatsapp'"
                                                   :style="sendingWhatsapp['{{ $itemId }}'] ? 'animation:spin 1s linear infinite;' : ''"></i>
                                            </button>
                                        </template>
                                        <template x-if="actioned['{{ $itemId }}']">
                                            <span class="taw-ib taw-ib--ok" title="Sent"><i class="ti ti-check"></i></span>
                                        </template>
                                    @else
                                        <template x-if="!actioned['{{ $itemId }}']">
                                            <button type="button" class="taw-ib taw-ib--go" title="Log call"
                                                    @click="openDrawer({{ json_encode($drawerItem) }}, '{{ $itemId }}')">
                                                <i class="ti ti-phone"></i>
                                            </button>
                                        </template>
                                        <template x-if="actioned['{{ $itemId }}']">
                                            <span class="taw-ib taw-ib--ok" title="Done"><i class="ti ti-check"></i></span>
                                        </template>
                                    @endif

                                    <a href="{{ $item['link'] }}" class="taw-ib" title="Open record"><i class="ti ti-external-link"></i></a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

                <div class="taw-none" x-show="total === 0" x-cloak>
                    No actions match this filter. <a href="#" @click.prevent="reset()">Clear filters</a>
                </div>
            </div>

            {{-- ── Footer ── --}}
            <div class="taw-foot">
                <span>
                    Showing <strong class="taw-pgn" x-text="from()"></strong>–<strong class="taw-pgn" x-text="to()"></strong>
                    of <strong class="taw-pgn" x-text="total"></strong>
                </span>
                @if($emptyCount > 0)
                    <span>· {{ $emptyCount }} other {{ Str::plural('category', $emptyCount) }} with nothing to show</span>
                @endif
                @if(isset($groups['missed_calls_yesterday']) && $groups['missed_calls_yesterday']['count'] > 0 && $mode === 'today')
                    <span>· <a href="{{ route('relationship.today.missed-calls') }}">View full missed-calls list</a></span>
                @endif

                <div class="taw-pg">
                    <button type="button" class="taw-pgb" @click="page = 1"        :disabled="page === 1">&laquo;</button>
                    <button type="button" class="taw-pgb" @click="page = page - 1" :disabled="page === 1">&lsaquo;</button>
                    <span class="taw-pgn">Page <strong x-text="page"></strong> / <span x-text="pages"></span></span>
                    <button type="button" class="taw-pgb" @click="page = page + 1" :disabled="page >= pages">&rsaquo;</button>
                    <button type="button" class="taw-pgb" @click="page = pages"    :disabled="page >= pages">&raquo;</button>
                </div>
            </div>
        </div>
        @endif

    </div>{{-- /.taw --}}

    {{-- ══════════════════════════════════════════════════════════════════
         CALL WORKFLOW DRAWER (Alpine-driven)
    ══════════════════════════════════════════════════════════════════ --}}
    <template x-if="drawer.open">
        <div class="ta-drawer-backdrop" @click.self="closeDrawer()">
            <div class="ta-drawer" @click.stop>

                {{-- Drawer Header --}}
                <div class="ta-drawer-header">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                        <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.08em;opacity:0.75;"
                              x-text="categoryLabel(drawer.item?.category)"></span>
                        <button
                            @click="closeDrawer()"
                            style="background:rgba(255,255,255,0.15);border:none;border-radius:6px;padding:4px 8px;color:#fff;cursor:pointer;font-size:13px;"
                        >
                            <i class="ti ti-x"></i>
                        </button>
                    </div>
                    <div class="ta-drawer-title" x-text="drawer.item?.patient_name"></div>
                    <p class="ta-drawer-sub" x-text="drawer.item?.suggested_action"></p>
                </div>

                {{-- Drawer Body --}}
                <div class="ta-drawer-body">

                    {{-- 1. Relationship Summary --}}
                    <div class="ta-drawer-section">
                        <div class="ta-drawer-section-label">Summary</div>
                        <div class="ta-summary-grid">
                            <div class="ta-summary-item">
                                <div class="ta-summary-item-label">Reason for call</div>
                                <div class="ta-summary-item-value" x-text="drawer.item?.reason"></div>
                            </div>
                            <div class="ta-summary-item">
                                <div class="ta-summary-item-label">Priority</div>
                                <div class="ta-summary-item-value" style="text-transform:capitalize;" x-text="drawer.item?.priority"></div>
                            </div>
                            <template x-if="drawer.item?.meta?.phone">
                                <div class="ta-summary-item">
                                    <div class="ta-summary-item-label">Phone</div>
                                    <div class="ta-summary-item-value">
                                        <a :href="'tel:' + drawer.item.meta.phone"
                                           x-text="drawer.item.meta.phone"
                                           style="color:#6a0f70;text-decoration:none;"></a>
                                    </div>
                                </div>
                            </template>
                            <template x-if="drawer.item?.meta?.treatment">
                                <div class="ta-summary-item">
                                    <div class="ta-summary-item-label">Treatment</div>
                                    <div class="ta-summary-item-value" x-text="drawer.item.meta.treatment"></div>
                                </div>
                            </template>
                            <template x-if="drawer.item?.meta?.appointment_date">
                                <div class="ta-summary-item">
                                    <div class="ta-summary-item-label">Appointment</div>
                                    <div class="ta-summary-item-value" x-text="drawer.item.meta.appointment_date"></div>
                                </div>
                            </template>
                            <template x-if="drawer.item?.meta?.end_date">
                                <div class="ta-summary-item">
                                    <div class="ta-summary-item-label">Expiry</div>
                                    <div class="ta-summary-item-value" x-text="drawer.item.meta.end_date"></div>
                                </div>
                            </template>
                            <template x-if="drawer.item?.meta?.balance_due">
                                <div class="ta-summary-item">
                                    <div class="ta-summary-item-label">Balance Due</div>
                                    <div class="ta-summary-item-value" x-text="'₹' + Number(drawer.item.meta.balance_due).toLocaleString('en-IN')"></div>
                                </div>
                            </template>
                            <template x-if="drawer.item?.meta?.due_date">
                                <div class="ta-summary-item">
                                    <div class="ta-summary-item-label">Due Date</div>
                                    <div class="ta-summary-item-value" x-text="drawer.item.meta.due_date"></div>
                                </div>
                            </template>
                        </div>
                        {{-- AI summary if present --}}
                        <template x-if="drawer.item?.meta?.ai_summary">
                            <div style="margin-top:8px;padding:8px 10px;background:#f5eef9;border-radius:8px;font-size:12px;color:#6a0f70;">
                                <i class="ti ti-sparkles" style="margin-right:4px;"></i>
                                <span x-text="drawer.item.meta.ai_summary"></span>
                            </div>
                        </template>
                    </div>

                    {{-- 2. Notes — same Suggestion / Patient-Response log already live on
                         Lead & Opportunity Pipeline, ported here as-is. Reuses
                         ActivityEngine (event: today_action.note_added), no new table.
                         See docs/feature-specs/feature-spec-action-board-instruction-log.md. --}}
                    <div class="ta-drawer-section">
                        <div class="ta-drawer-section-label">Notes</div>

                        <template x-if="notesLoading">
                            <p style="font-size:13px;color:#9ca3af;margin:0 0 12px;">Loading notes…</p>
                        </template>

                        <template x-if="!notesLoading && notes.length === 0">
                            <p style="font-size:13px;color:#9ca3af;margin:0 0 12px;">No notes logged yet.</p>
                        </template>

                        <template x-for="(note, i) in notes" :key="i">
                            <div style="display:flex;gap:10px;margin-bottom:12px;">
                                <span :style="(note.note_type === 'response'
                                        ? 'background:#eef6ee;color:#2f7a3d;'
                                        : 'background:#f0eefc;color:#534AB7;') +
                                        'font-size:10.5px;font-weight:700;padding:2px 8px;border-radius:20px;white-space:nowrap;height:fit-content;'"
                                      x-text="note.note_type === 'response' ? 'Patient Response' : 'Suggestion'"></span>
                                <div style="flex:1;min-width:0;">
                                    <p style="font-size:13.5px;color:#374151;line-height:1.5;margin:0;" x-text="note.text"></p>
                                    <div style="font-size:11px;color:#9ca3af;margin-top:3px;">
                                        <span x-text="note.author"></span> · <span x-text="note.occurred_at"></span>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div style="display:flex;gap:8px;align-items:flex-start;margin-top:8px;">
                            <select class="ta-form-select" x-model="noteType" style="flex-shrink:0;width:auto;">
                                <option value="suggestion">Suggestion</option>
                                <option value="response">Patient Response</option>
                            </select>
                            <textarea class="ta-form-textarea" x-model="noteText" rows="2"
                                      placeholder="Add a note…" style="flex:1;margin:0;"></textarea>
                        </div>
                        <template x-if="notesError">
                            <div style="font-size:12px;color:#b52020;margin-top:6px;" x-text="notesError"></div>
                        </template>
                        <div style="display:flex;justify-content:flex-end;margin-top:8px;">
                            <button type="button" @click="addNote()" :disabled="!noteText.trim() || notesSaving"
                                    style="padding:7px 16px;border:none;border-radius:8px;background:#534AB7;color:#fff;font-size:12.5px;font-weight:600;cursor:pointer;">
                                <span x-show="!notesSaving">Add Note</span>
                                <span x-show="notesSaving">Saving…</span>
                            </button>
                        </div>
                    </div>

                    {{-- 3. Dynamic Checklist --}}
                    <div class="ta-drawer-section" x-show="checklist.length > 0">
                        <div class="ta-drawer-section-label">Call Checklist</div>
                        <ul class="ta-checklist">
                            <template x-for="(item, i) in checklist" :key="i">
                                <li>
                                    <input
                                        type="checkbox"
                                        class="ta-checklist-check"
                                        :id="'chk_' + i"
                                        x-model="checks[i]"
                                    >
                                    <label :for="'chk_' + i" x-text="item" style="cursor:pointer;"></label>
                                </li>
                            </template>
                        </ul>
                    </div>

                    {{-- Log / Close tabs (2026-07-08) — previously one combined
                         "Log & Close" button, which force-closed the row for
                         every outcome (including "No answer"/"Not connected"),
                         so a failed attempt vanished instead of staying open
                         for a retry. Log now only records an outcome; Close is
                         a separate, explicit action. --}}
                    <div style="display:flex;gap:22px;padding:0 24px;border-bottom:1px solid #f0ebf5;">
                        <button type="button" @click="activeTab = 'log'"
                                :style="activeTab === 'log'
                                    ? 'color:#534AB7;font-weight:700;border-bottom-color:#534AB7;'
                                    : 'color:#b3a6bf;font-weight:600;border-bottom-color:transparent;'"
                                style="background:none;border:none;border-bottom:2.5px solid transparent;margin-bottom:-1px;padding:9px 2px;font-size:13px;letter-spacing:.01em;cursor:pointer;transition:color .15s ease;">
                            Log
                        </button>
                        <button type="button" @click="activeTab = 'close'"
                                :style="activeTab === 'close'
                                    ? 'color:#534AB7;font-weight:700;border-bottom-color:#534AB7;'
                                    : 'color:#b3a6bf;font-weight:600;border-bottom-color:transparent;'"
                                style="background:none;border:none;border-bottom:2.5px solid transparent;margin-bottom:-1px;padding:9px 2px;font-size:13px;letter-spacing:.01em;cursor:pointer;transition:color .15s ease;">
                            Close
                        </button>
                    </div>

                    {{-- 4. Log tab — records a call outcome, never closes the row --}}
                    <div x-show="activeTab === 'log'" style="padding-top:16px;">
                        <div class="ta-drawer-section">
                            <div class="ta-drawer-section-label">Log Response</div>

                            <div class="ta-form-group">
                                <label class="ta-form-label">Call outcome</label>
                                <select
                                    class="ta-form-select"
                                    x-model="form.response"
                                    @change="updateNextAction()"
                                >
                                    <option value="">— Select outcome —</option>
                                    <template x-for="(label, key) in responseOptions" :key="key">
                                        <option :value="key" x-text="label"></option>
                                    </template>
                                </select>
                            </div>

                            <div class="ta-form-group">
                                <label class="ta-form-label">
                                    <span x-text="requiresNotes ? 'Notes (required for this outcome)' : 'Notes (optional)'"></span>
                                </label>
                                <textarea
                                    class="ta-form-textarea"
                                    placeholder="Any notes from this call..."
                                    x-model="form.notes"
                                    :style="requiresNotes && !form.notes ? 'border-color:#e0a0a0;' : ''"
                                ></textarea>
                            </div>
                        </div>

                        {{-- Next Action (auto-suggested) --}}
                        <div class="ta-drawer-section" x-show="nextActionLabel">
                            <div class="ta-drawer-section-label">Suggested Next Action</div>
                            <div class="ta-next-action-box">
                                <i class="ti ti-arrow-right" style="margin-top:1px;flex-shrink:0;"></i>
                                <span x-text="nextActionLabel"></span>
                            </div>
                        </div>

                        <template x-if="submitError">
                            <div style="background:#fdeaea;border:1px solid #f5a0a0;border-radius:8px;padding:10px 14px;font-size:13px;color:#b52020;margin:0 24px 8px;">
                                <i class="ti ti-alert-circle"></i>
                                <span x-text="submitError"></span>
                            </div>
                        </template>
                    </div>

                    {{-- Close tab — explicit "done with this one," no outcome required --}}
                    <div x-show="activeTab === 'close'" x-cloak style="padding-top:16px;">
                        <div class="ta-drawer-section">
                            <div class="ta-drawer-section-label">Close this item</div>
                            <p style="font-size:13px;color:#6b7280;margin:0 0 10px;">
                                Removes it from today's list. Use this once you're done — after a call went
                                through, or after enough retry attempts. It does not require a call outcome.
                            </p>
                            <div class="ta-form-group">
                                <label class="ta-form-label">Notes (optional)</label>
                                <textarea
                                    class="ta-form-textarea"
                                    placeholder="Why is this being closed?"
                                    x-model="closeNotes"
                                ></textarea>
                            </div>
                        </div>

                        <template x-if="closeError">
                            <div style="background:#fdeaea;border:1px solid #f5a0a0;border-radius:8px;padding:10px 14px;font-size:13px;color:#b52020;margin:0 24px 8px;">
                                <i class="ti ti-alert-circle"></i>
                                <span x-text="closeError"></span>
                            </div>
                        </template>
                    </div>

                </div>{{-- /drawer-body --}}

                {{-- Dismiss panel — replaces the footer while active. Requires a
                     reason so a row can't be cleared with a fake call outcome.
                     See docs/feature-specs/feature-spec-action-board-dismiss.md. --}}
                <div x-show="dismissMode" x-cloak style="padding:14px 24px;border-top:1px solid #f3f4f6;background:#fafafa;">
                    <div class="ta-form-group">
                        <label class="ta-form-label">Dismiss reason</label>
                        <select class="ta-form-select" x-model="dismissReason">
                            <option value="">— Select a reason —</option>
                            <template x-for="r in dismissReasons" :key="r.key">
                                <option :value="r.key" x-text="r.label"></option>
                            </template>
                        </select>
                    </div>
                    <div class="ta-form-group" x-show="dismissReason">
                        <label class="ta-form-label">
                            <span x-text="dismissRequiresNotes ? 'Notes (required for this reason)' : 'Notes (optional)'"></span>
                        </label>
                        <textarea class="ta-form-textarea" placeholder="Why is this being dismissed?" x-model="dismissNotes"></textarea>
                    </div>
                    <template x-if="dismissError">
                        <div style="background:#fdeaea;border:1px solid #f5a0a0;border-radius:8px;padding:8px 12px;font-size:12.5px;color:#b52020;margin-bottom:8px;">
                            <span x-text="dismissError"></span>
                        </div>
                    </template>
                    <div style="display:flex;gap:10px;justify-content:flex-end;">
                        <button class="ta-btn-cancel" @click="dismissMode = false" :disabled="dismissing">Back</button>
                        <button
                            class="ta-btn-submit"
                            style="background:#8a5a5a;"
                            @click="confirmDismiss()"
                            :disabled="!dismissReason || dismissing || (dismissRequiresNotes && !dismissNotes)"
                        >
                            <span x-show="!dismissing">Dismiss</span>
                            <span x-show="dismissing">Dismissing…</span>
                        </button>
                    </div>
                </div>

                {{-- Drawer Footer — Log/Close button swaps with the active tab
                     (2026-07-08). "Dismiss instead" stays available from either
                     tab: for rows that shouldn't have been on the list at all,
                     not ones where a real attempt (logged or closed) happened. --}}
                <div class="ta-drawer-footer" x-show="!dismissMode">
                    <button class="ta-btn-cancel" @click="closeDrawer()">Cancel</button>
                    <button
                        type="button"
                        @click="dismissMode = true"
                        style="background:none;border:none;color:#9a7aaa;font-size:12.5px;cursor:pointer;text-decoration:underline;margin-right:auto;margin-left:10px;"
                    >
                        Dismiss instead
                    </button>
                    <button
                        x-show="activeTab === 'log'"
                        class="ta-btn-submit"
                        @click="submitLog()"
                        :disabled="!form.response || submitting || (requiresNotes && !form.notes)"
                    >
                        <span x-show="!submitting"><i class="ti ti-check"></i> Log</span>
                        <span x-show="submitting"><i class="ti ti-loader-2" style="animation:spin 1s linear infinite;"></i> Saving...</span>
                    </button>
                    <button
                        x-show="activeTab === 'close'"
                        class="ta-btn-submit"
                        style="background:#4a7a5a;"
                        @click="confirmClose()"
                        :disabled="closing"
                    >
                        <span x-show="!closing"><i class="ti ti-check"></i> Close</span>
                        <span x-show="closing"><i class="ti ti-loader-2" style="animation:spin 1s linear infinite;"></i> Closing...</span>
                    </button>
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
            const list = this.matches();
            this.total = list.length;
            this.pages = Math.max(1, Math.ceil(this.total / this.per));
            if (this.page > this.pages) { this.page = this.pages; return; }
            if (this.page < 1)          { this.page = 1;          return; }
            const start = (this.page - 1) * this.per;
            const vis = {};
            list.slice(start, start + this.per).forEach(r => { vis[r.id] = true; });
            this.vis = vis;
        },

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

        // ── Active tab: 'log' (record an outcome, never closes) or 'close'
        // (explicit "done with this one", no outcome required) — split
        // 2026-07-08, see closeAction() in TodayController. ─────────────
        activeTab: 'log',

        // ── Close tab state ──────────────────────────────────────────────
        closeNotes: '',
        closing:    false,
        closeError: '',

        // ── Checklist ───────────────────────────────────────────────────
        checklist: [],
        checks: [],

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

        // ── Notes sub-state (Suggestion / Patient Response log) ─────────
        // Same log already live on Lead & Opportunity Pipeline, ported here.
        // See docs/feature-specs/feature-spec-action-board-instruction-log.md.
        notes:        [],
        notesLoading: false,
        notesSaving:  false,
        notesError:   '',
        noteType:     'suggestion',
        noteText:     '',

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

            // Reset Log/Close tab state
            this.activeTab  = 'log';
            this.closeNotes = '';
            this.closing    = false;
            this.closeError = '';

            // Load checklist for this category (fall back to empty)
            this.checklist = CHECKLISTS[cat] || [];
            this.checks    = new Array(this.checklist.length).fill(false);

            // Load response options: category-specific or default
            this.responseOptions = RESPONSE_OPTS[cat] || RESPONSE_OPTS['default'] || {};

            // Reset form
            this.form = { response: '', next_action: '', notes: '' };
            this.nextActionLabel = '';
            this.requiresNotes   = false;
            this.submitError     = '';

            // Reset dismiss sub-state
            this.dismissMode   = false;
            this.dismissReason = '';
            this.dismissNotes  = '';
            this.dismissError  = '';

            // Reset Notes sub-state and load this item's note log
            this.notes        = [];
            this.notesError   = '';
            this.noteType     = 'suggestion';
            this.noteText     = '';
            this.fetchNotes();
        },

        // ─────────────────────────────────────────────────────────────────
        // Load the Suggestion / Patient Response note log for this item.
        // ─────────────────────────────────────────────────────────────────
        async fetchNotes() {
            const item = this.drawer.item;
            if (!item?.patient_id && !item?.lead_id) {
                this.notes = [];
                return;
            }

            this.notesLoading = true;
            try {
                const params = new URLSearchParams({
                    patient_id: item.patient_id ?? '',
                    lead_id:    item.lead_id ?? '',
                });
                const res = await fetch('{{ route('relationship.today.notes.index') }}?' + params.toString(), {
                    headers: { 'Accept': 'application/json' },
                });
                const data = await res.json();
                this.notes = data.notes || [];
            } catch (err) {
                // Silent — the note list just stays empty; not worth blocking the drawer for.
            } finally {
                this.notesLoading = false;
            }
        },

        // ─────────────────────────────────────────────────────────────────
        // Add a Suggestion / Patient Response note to the currently open item.
        // ─────────────────────────────────────────────────────────────────
        async addNote() {
            if (!this.noteText.trim() || this.notesSaving) return;

            const item = this.drawer.item;
            this.notesSaving = true;
            this.notesError  = '';

            try {
                const res = await fetch('{{ route('relationship.today.notes.add') }}', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body:    JSON.stringify({
                        _token:          document.querySelector('meta[name="csrf-token"]').content,
                        note_type:       this.noteType,
                        text:            this.noteText.trim(),
                        category:        item.category,
                        patient_id:      item.patient_id,
                        lead_id:         item.lead_id,
                        relationship_id: item.relationship_id,
                    }),
                });

                const data = await res.json();

                if (data.success) {
                    this.noteText = '';
                    await this.fetchNotes();
                } else {
                    this.notesError = data.message || 'Could not save this note.';
                }
            } catch (err) {
                this.notesError = 'Network error. Please try again.';
            } finally {
                this.notesSaving = false;
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
            this.drawer.open = false;
            this.drawer.item = null;
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
            this.nextActionLabel = NEXT_ACTIONS[this.form.response] || '';
            this.form.next_action = this.nextActionLabel;

            const cat = this.drawer.item?.category;
            this.requiresNotes = !!((REQUIRES_NOTES[cat] || {})[this.form.response]);
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
            if (!this.form.response || this.submitting) return;

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
                        (this.responseOptions[this.form.response] || this.form.response)
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
