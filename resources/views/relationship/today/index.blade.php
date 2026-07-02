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
@extends('layouts.app')

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
        align-items: flex-end;
        justify-content: space-between;
        margin-bottom: 24px;
        flex-wrap: wrap;
        gap: 12px;
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
        justify-content: flex-end;
    }

    .ta-drawer {
        width: 420px;
        max-width: 96vw;
        height: 100vh;
        background: #fff;
        display: flex;
        flex-direction: column;
        box-shadow: -4px 0 24px rgba(106, 15, 112, 0.12);
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

@section('content')

{{-- ══════════════════════════════════════════════════════════════════════
     ALPINE.JS CONTROLLER
     All drawer state lives here. One `open` item at a time.
══════════════════════════════════════════════════════════════════════ --}}
<div
    x-data="todayActions()"
    @keydown.escape.window="closeDrawer()"
    class="pa-6"
    style="padding: 28px 28px 60px; max-width: 1100px;"
>

    {{-- ── Page Header ─────────────────────────────────────────────────── --}}
    <div class="ta-page-header">
        <div>
            <h1 class="ta-page-title">Today's Actions</h1>
            <p class="ta-page-sub">
                {{ now()->format('l, d F Y') }} &nbsp;·&nbsp;
                Generated from live patient data
            </p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="ta-total-badge">
                <i class="ti ti-list-check"></i>
                {{ $totalCount }} {{ Str::plural('action', $totalCount) }} today
            </span>
            <button
                onclick="window.location.reload()"
                style="padding:6px 12px;border:1px solid #dfc5e1;border-radius:8px;background:#fff;color:#6a0f70;font-size:12px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;"
            >
                <i class="ti ti-refresh"></i> Refresh
            </button>
        </div>
    </div>

    {{-- ── All Done Banner ─────────────────────────────────────────────── --}}
    @if($totalCount === 0)
    <div class="ta-all-done">
        <div class="ta-all-done-icon"><i class="ti ti-circle-check"></i></div>
        <div class="ta-all-done-title">All done — you're caught up!</div>
        <div class="ta-all-done-sub">No outstanding actions right now. Check back tomorrow morning.</div>
    </div>
    @endif

    {{-- ── Category Groups ─────────────────────────────────────────────── --}}
    @foreach($groups as $catKey => $group)
    <div
        class="ta-section {{ $group['count'] === 0 ? 'ta-section--empty' : '' }}"
        x-data="{ open: {{ $group['count'] > 0 ? 'true' : 'false' }} }"
    >
        {{-- Section header — click to collapse --}}
        <div class="ta-section-header" @click="open = !open">
            <div class="ta-section-icon">
                <i class="ti {{ $group['icon'] }}"></i>
            </div>
            <span class="ta-section-label">{{ $group['label'] }}</span>
            <span class="ta-section-count {{ $group['count'] > 0 ? 'ta-section-count--has' : 'ta-section-count--empty' }}">
                {{ $group['count'] }}
            </span>
            <i class="ti ti-chevron-down ta-chevron" :style="open ? 'transform:rotate(180deg)' : ''"></i>
        </div>

        {{-- Items list --}}
        <div x-show="open" x-collapse>
            @if($group['count'] === 0)
                <div class="ta-empty">
                    <div class="ta-empty-icon"><i class="ti ti-circle-check"></i></div>
                    Nothing to action here today.
                </div>
            @else
                @foreach($group['items'] as $idx => $item)
                @php
                    $itemId = $catKey . '_' . $idx;
                    $phone  = $item['meta']['phone'] ?? null;
                @endphp
                <div
                    class="ta-item"
                    :class="actioned['{{ $itemId }}'] ? 'ta-item--actioned' : ''"
                    id="item-{{ $itemId }}"
                >
                    {{-- Body --}}
                    <div class="ta-item-body">
                        <div class="ta-item-name">{{ $item['patient_name'] }}</div>
                        <div class="ta-item-reason">{{ $item['reason'] }}</div>
                        @if($phone)
                        <div style="font-size:11px;color:#9a7aaa;margin-top:2px;">
                            <i class="ti ti-phone" style="font-size:10px;"></i>
                            <a href="tel:{{ $phone }}" style="color:inherit;text-decoration:none;">{{ $phone }}</a>
                        </div>
                        @endif
                    </div>

                    {{-- Actions --}}
                    <div class="ta-item-actions">
                        {{-- Priority badge --}}
                        <span class="ta-priority ta-priority--{{ $item['priority'] }}">
                            {{ $item['priority'] }}
                        </span>

                        {{-- Call Workflow button --}}
                        <template x-if="!actioned['{{ $itemId }}']">
                            <button
                                class="ta-btn-call"
                                @click="openDrawer({{ json_encode($item) }}, '{{ $itemId }}')"
                            >
                                <i class="ti ti-phone"></i> Call
                            </button>
                        </template>

                        {{-- Done badge (post-action) --}}
                        <template x-if="actioned['{{ $itemId }}']">
                            <span class="ta-btn-done">
                                <i class="ti ti-check"></i> Done
                            </span>
                        </template>

                        {{-- Open record link --}}
                        <a
                            href="{{ $item['link'] }}"
                            class="ta-btn-open"
                            title="Open record"
                        >
                            <i class="ti ti-external-link"></i>
                        </a>
                    </div>
                </div>
                @endforeach
            @endif
        </div>
    </div>
    @endforeach

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
    appointment_reminders:         'Appointment Reminders',
    missed_calls_yesterday:        "Yesterday's Missed Calls",
    missed_appointments_yesterday: "Yesterday's Missed Appointments",
    pending_estimates:             'Pending Estimates',
    membership_renewals:           'Membership Renewals',
    birthdays:                     'Birthday Wishes',
    lab_ready:                     'Lab Work Ready',
    payment_reminders:             'Payment Reminders',
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
