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

    /* ── Category card grid (quick glimpse — each card scrolls internally) ── */
    #df-content-inner { padding: 10px 24px 8px !important; }

    .ta-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
        gap: 12px;
        margin-bottom: 12px;
    }

    .ta-card {
        background: #fff;
        border: 1px solid #e8dff0;
        border-radius: 12px;
        display: flex;
        flex-direction: column;
        height: 250px;
        overflow: hidden;
    }

    .ta-card-head {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 9px 14px;
        border-bottom: 1px solid #f0e8f5;
        background: #faf5fc;
        flex-shrink: 0;
    }

    .ta-card-icon {
        width: 26px; height: 26px; border-radius: 7px; background: #ede4f7;
        display: flex; align-items: center; justify-content: center;
        font-size: 13px; color: #6a0f70; flex-shrink: 0;
    }

    .ta-card-label {
        font-weight: 600; font-size: 12.5px; color: #1a0320; flex: 1; min-width: 0;
        overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    .ta-card-count {
        font-size: 10.5px; font-weight: 700; padding: 2px 8px; border-radius: 99px;
        background: #ede4f7; color: #6a0f70; flex-shrink: 0;
    }

    .ta-card-list { flex: 1; overflow-y: auto; }

    .ta-row {
        display: flex; align-items: center; gap: 8px;
        padding: 7px 12px; border-bottom: 1px solid #f8f4fc;
        transition: background 100ms;
    }

    .ta-row:last-child { border-bottom: none; }
    .ta-row:hover { background: #fdf9ff; }
    .ta-row.ta-row--actioned { opacity: 0.4; }

    .ta-row-dot { width: 7px; height: 7px; border-radius: 50%; flex-shrink: 0; }
    .ta-row-dot--high   { background: #b52020; }
    .ta-row-dot--medium { background: #a05c00; }
    .ta-row-dot--low    { background: #1a7a45; }

    .ta-row-body { flex: 1; min-width: 0; }
    .ta-row-name {
        font-weight: 600; font-size: 12.5px; color: #1a0320;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ta-row-reason {
        font-size: 11px; color: #6a5a76;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    .ta-row-actions { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }

    .ta-row-btn-call {
        display: inline-flex; align-items: center; justify-content: center;
        width: 23px; height: 23px; border-radius: 6px; background: #6a0f70;
        color: #fff; border: none; cursor: pointer; font-size: 11px;
    }
    .ta-row-btn-call:hover { background: #4e0a53; }

    .ta-row-btn-open {
        display: inline-flex; align-items: center; justify-content: center;
        width: 23px; height: 23px; border-radius: 6px; color: #6a0f70;
        border: 1px solid #dfc5e1; text-decoration: none; font-size: 11px;
    }

    .ta-row-btn-done {
        display: inline-flex; align-items: center; justify-content: center;
        width: 23px; height: 23px; color: #1a7a45; font-size: 13px;
    }

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
<div
    x-data="todayActions()"
    @keydown.escape.window="closeDrawer()"
>

    {{-- ── Page Header ─────────────────────────────────────────────────── --}}
    <div class="ta-page-header">
        <div class="ta-page-header-title-col">
            <h1 class="ta-page-title">
                @if($mode === 'today') Today's Actions
                @elseif($mode === 'future') Upcoming — {{ $selectedDate->format('d M Y') }}
                @else Completed — {{ $selectedDate->format('d M Y') }}
                @endif
            </h1>
            <p class="ta-page-sub">
                @if($mode === 'today')
                    {{ now()->format('l, d F Y') }} &nbsp;·&nbsp; Generated from live patient data
                @elseif($mode === 'future')
                    Preview based on today's data — call, follow-up, and recall dates already on file. A patient could still visit before then and drop off this list.
                @else
                    Calls logged as completed on this date, with their outcome. Read-only.
                @endif
            </p>
        </div>
        <div class="ta-page-header-controls-col" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span class="ta-total-badge">
                <i class="ti ti-list-check"></i>
                {{ $totalCount }} {{ Str::plural(($mode === 'today' ? 'action' : 'call'), $totalCount) }}
            </span>

            {{-- Date picker — quick chips + a native date input for any day --}}
            <div style="display:flex;align-items:center;gap:6px;">
                <a href="{{ route('relationship.today') }}"
                   style="padding:6px 10px;border:1px solid {{ $mode === 'today' ? '#6a0f70' : '#dfc5e1' }};border-radius:8px;background:{{ $mode === 'today' ? '#f3e8f4' : '#fff' }};color:#6a0f70;font-size:12px;font-weight:600;text-decoration:none;">
                    Today
                </a>
                <a href="{{ route('relationship.today') }}?date={{ $today->copy()->addDay()->toDateString() }}"
                   style="padding:6px 10px;border:1px solid #dfc5e1;border-radius:8px;background:#fff;color:#6a0f70;font-size:12px;font-weight:600;text-decoration:none;">
                    Tomorrow
                </a>
                <input type="date" value="{{ $selectedDate->toDateString() }}"
                       onchange="window.location.href = '{{ route('relationship.today') }}?date=' + this.value"
                       style="padding:6px 10px;border:1px solid #dfc5e1;border-radius:8px;font-size:12px;color:#4e0a53;background:#fff;">
            </div>

            <button
                onclick="window.location.reload()"
                style="padding:6px 12px;border:1px solid #dfc5e1;border-radius:8px;background:#fff;color:#6a0f70;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;"
            >
                <i class="ti ti-refresh"></i> Refresh
            </button>
        </div>
    </div>

    {{-- ── All Done / Nothing Found Banner ─────────────────────────────── --}}
    @if($totalCount === 0)
    <div class="ta-all-done">
        <div class="ta-all-done-icon"><i class="ti ti-circle-check"></i></div>
        @if($mode === 'today')
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

    {{-- ── Category Cards — grid, one card per active category ──────────── --}}
    {{-- Each card is a fixed height; its item list scrolls internally. This
         means the page height depends on the NUMBER of active categories,
         never on how many items are inside one of them (a category with
         1,000+ items still fits in one card). Empty categories are skipped
         entirely rather than shown as dimmed collapsed sections. --}}
    @php $nonEmptyGroups = collect($groups)->filter(fn($g) => $g['count'] > 0); @endphp

    @if($nonEmptyGroups->isNotEmpty())
    <div class="ta-grid">
        @foreach($nonEmptyGroups as $catKey => $group)
        <div class="ta-card">
            <div class="ta-card-head">
                <div class="ta-card-icon"><i class="ti {{ $group['icon'] }}"></i></div>
                <span class="ta-card-label">{{ $group['label'] }}</span>
                <span class="ta-card-count">{{ $group['count'] }}</span>
                @if($catKey === 'missed_calls_yesterday' && $mode === 'today')
                    {{-- This card only samples up to max_per_category rows — the full backlog lives here. --}}
                    <a href="{{ route('relationship.today.missed-calls') }}" title="View full missed-calls list"
                       style="font-size:11px;color:#6a0f70;text-decoration:none;flex-shrink:0;margin-left:2px;">
                        <i class="ti ti-arrows-maximize"></i>
                    </a>
                @endif
            </div>
            <div class="ta-card-list">
                @foreach($group['items'] as $idx => $item)
                @php $itemId = $catKey . '_' . $idx; @endphp
                <div
                    class="ta-row"
                    :class="actioned['{{ $itemId }}'] ? 'ta-row--actioned' : ''"
                    id="item-{{ $itemId }}"
                >
                    <span class="ta-row-dot ta-row-dot--{{ $item['priority'] }}" title="{{ ucfirst($item['priority']) }} priority"></span>

                    <div class="ta-row-body">
                        <div class="ta-row-name">{{ $item['patient_name'] }}</div>
                        <div class="ta-row-reason">{{ $item['reason'] }}</div>
                    </div>

                    <div class="ta-row-actions">
                        @if($mode === 'past')
                            {{-- Read-only history: show the logged outcome instead of a Call button --}}
                            @php $outcome = $item['meta']['outcome'] ?? null; @endphp
                            <span
                                title="{{ $outcome ? ucwords(str_replace('_', ' ', $outcome)) : 'Completed' }}"
                                style="font-size:10px;font-weight:700;padding:2px 8px;border-radius:99px;background:#ede4f7;color:#6a0f70;white-space:nowrap;"
                            >{{ $outcome ? ucwords(str_replace('_', ' ', $outcome)) : 'Completed' }}</span>
                        @elseif(($item['primary_action'] ?? null) === 'whatsapp')
                            {{-- Birthday Wishes only: one-click WhatsApp send, no call drawer --}}
                            <template x-if="!actioned['{{ $itemId }}']">
                                <button
                                    class="ta-row-btn-call"
                                    title="Send WhatsApp birthday greeting"
                                    :disabled="sendingWhatsapp['{{ $itemId }}']"
                                    @click="sendBirthdayWhatsapp({{ json_encode($item) }}, '{{ $itemId }}')"
                                >
                                    <i class="ti" :class="sendingWhatsapp['{{ $itemId }}'] ? 'ti-loader-2' : 'ti-brand-whatsapp'" :style="sendingWhatsapp['{{ $itemId }}'] ? 'animation:spin 1s linear infinite;' : ''"></i>
                                </button>
                            </template>

                            <template x-if="actioned['{{ $itemId }}']">
                                <span class="ta-row-btn-done" title="Sent"><i class="ti ti-check"></i></span>
                            </template>
                        @else
                            <template x-if="!actioned['{{ $itemId }}']">
                                <button
                                    class="ta-row-btn-call"
                                    title="Log call"
                                    @click="openDrawer({{ json_encode($item) }}, '{{ $itemId }}')"
                                >
                                    <i class="ti ti-phone"></i>
                                </button>
                            </template>

                            <template x-if="actioned['{{ $itemId }}']">
                                <span class="ta-row-btn-done" title="Done"><i class="ti ti-check"></i></span>
                            </template>
                        @endif

                        <a href="{{ $item['link'] }}" class="ta-row-btn-open" title="Open record">
                            <i class="ti ti-external-link"></i>
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @php $emptyCount = collect($groups)->filter(fn($g) => $g['count'] === 0)->count(); @endphp
    @if($emptyCount > 0)
    <p class="ta-empty-footnote">
        {{ $emptyCount }} other {{ Str::plural('category', $emptyCount) }} with nothing to
        {{ $mode === 'today' ? 'action today' : ($mode === 'future' ? 'show for this date' : 'show') }}.
    </p>
    @endif

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

                    {{-- 2. Dynamic Checklist --}}
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

                    {{-- 3. Log Response --}}
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
                            <label class="ta-form-label">Notes (optional)</label>
                            <textarea
                                class="ta-form-textarea"
                                placeholder="Any notes from this call..."
                                x-model="form.notes"
                            ></textarea>
                        </div>
                    </div>

                    {{-- 4. Next Action (auto-suggested) --}}
                    <div class="ta-drawer-section" x-show="nextActionLabel">
                        <div class="ta-drawer-section-label">Suggested Next Action</div>
                        <div class="ta-next-action-box">
                            <i class="ti ti-arrow-right" style="margin-top:1px;flex-shrink:0;"></i>
                            <span x-text="nextActionLabel"></span>
                        </div>
                    </div>

                    {{-- Error message --}}
                    <template x-if="submitError">
                        <div style="background:#fdeaea;border:1px solid #f5a0a0;border-radius:8px;padding:10px 14px;font-size:13px;color:#b52020;margin-top:8px;">
                            <i class="ti ti-alert-circle"></i>
                            <span x-text="submitError"></span>
                        </div>
                    </template>

                </div>{{-- /drawer-body --}}

                {{-- Drawer Footer --}}
                <div class="ta-drawer-footer">
                    <button class="ta-btn-cancel" @click="closeDrawer()">Cancel</button>
                    <button
                        class="ta-btn-submit"
                        @click="submitLog()"
                        :disabled="!form.response || submitting"
                    >
                        <span x-show="!submitting"><i class="ti ti-check"></i> Log & Close</span>
                        <span x-show="submitting"><i class="ti ti-loader-2" style="animation:spin 1s linear infinite;"></i> Saving...</span>
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
    logged_communications:         'Logged Communications',
};

function todayActions() {
    return {

        // ── Drawer state ────────────────────────────────────────────────
        drawer: {
            open: false,
            item: null,
            itemId: null,
        },

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

        // ── Per-item actioned tracker (itemId → bool) ───────────────────
        actioned: {},

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

            // Load checklist for this category (fall back to empty)
            this.checklist = CHECKLISTS[cat] || [];
            this.checks    = new Array(this.checklist.length).fill(false);

            // Load response options: category-specific or default
            this.responseOptions = RESPONSE_OPTS[cat] || RESPONSE_OPTS['default'] || {};

            // Reset form
            this.form = { response: '', next_action: '', notes: '' };
            this.nextActionLabel = '';
            this.submitError     = '';
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
                    this.actioned[itemId] = true;
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

            const payload = {
                _token:          document.querySelector('meta[name="csrf-token"]').content,
                category:        item.category,
                patient_id:      item.patient_id,
                lead_id:         item.lead_id,
                relationship_id: item.relationship_id,
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
                    // Mark item as actioned in the UI
                    this.actioned[itemId] = true;
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

    };
}
</script>

@endsection
