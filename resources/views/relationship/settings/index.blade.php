{{--
|==========================================================================
| PRE — Relationship Engine Settings (module-scoped)
| Route: GET /relationship/settings   [relationship.settings]
|
| Moved out of the global Settings module (2026-07-03) so PRE can be sold
| and configured as a standalone module, independent of the rest of the
| Settings screen. Same toggle UI/JS pattern as the original Settings tab
| (custom inline confirm bar instead of window.confirm() — a native confirm
| dialog was found to freeze the page and block Chrome DevTools commands).
|
| Variables from Relationship\SettingsController@index: $featureFlags,
| $flagGroups, $referralRewardEnabled, $referralRewardAmount,
| $recallEffectiveFrom, $recallGeneralDays, $recallChannels,
| $recallTreatmentTypes, $birthdayEnabled, $birthdayWindowDays,
| $recallTemplate, $birthdayTemplate, $flagHelp
|==========================================================================
--}}
@extends('relationship.layouts.app')

@section('page-title', 'Settings')

@section('relationship-content')
<div style="max-width:760px;margin:0 auto;padding:8px 4px 40px;" x-data="preFlagsPanel()">

    {{-- Header --}}
    <div style="margin-bottom:20px;">
        <h1 style="margin:0;font-size:22px;font-weight:700;color:#1f2937;font-family:'Cormorant Garamond',serif;">Relationship Engine Settings</h1>
        <p style="margin:4px 0 0;color:#6b7280;font-size:13px;">
            Settings for how the Relationship Engine (PRE) behaves day to day.
        </p>
    </div>

    <style>
        /* Static layout only — dynamic colour/position comes from x-bind:style,
           which REPLACES a plain-string style attribute rather than merging it.
           Keeping the fixed geometry in a class avoids that Alpine gotcha. */
        .pre-switch {
            flex-shrink: 0; width: 46px; height: 26px; border-radius: 999px;
            border: none; position: relative; cursor: pointer; transition: background .15s;
            box-shadow: inset 0 1px 2px rgba(0,0,0,0.08);
        }
        .pre-switch-knob {
            position: absolute; top: 3px; left: 3px; width: 20px; height: 20px;
            border-radius: 50%; background: #fff; transition: transform .15s;
            box-shadow: 0 1px 3px rgba(0,0,0,0.25);
        }
        .pre-flag-key {
            display: inline-block; font-family: monospace; font-size: 10.5px;
            font-weight: 600; color: #6a0f70; background: #f5eef9;
            padding: 2px 7px; border-radius: 4px; margin-top: 5px;
        }

        /* Help hint: "?" icon + hover card. CSS-only (no Alpine state needed) —
           the card is a sibling of the icon, shown via :hover/:focus-within so
           it also works via keyboard tab, not just mouse hover. */
        .help-hint { position: relative; display: inline-flex; vertical-align: middle; margin-left: 6px; }
        .help-icon {
            width: 16px; height: 16px; border-radius: 50%; background: #f0e6f2; color: #6a0f70;
            font-size: 11px; font-weight: 700; line-height: 16px; text-align: center;
            cursor: help; user-select: none;
        }
        .help-card {
            position: absolute; left: 0; top: 22px; width: 300px; max-width: 78vw;
            background: #fff; border: 1px solid #e8d5f0; border-radius: 8px;
            box-shadow: 0 10px 28px rgba(106,15,112,0.2); padding: 12px 14px;
            font-size: 12px; line-height: 1.5; color: #4a3a54; font-weight: 400;
            z-index: 60; opacity: 0; visibility: hidden; transform: translateY(-4px);
            transition: opacity .12s ease, transform .12s ease; pointer-events: none;
        }
        .help-hint:hover .help-card, .help-hint:focus-within .help-card {
            opacity: 1; visibility: visible; transform: translateY(0); pointer-events: auto;
        }
        .help-card strong { color: #1a0a24; }
        .help-card .help-example {
            margin: 8px 0 0; padding-top: 8px; border-top: 1px dashed #e8d5f0; color: #6a0f70;
        }
    </style>

    {{-- ── Business Settings — plain, no jargon ── --}}
    <div style="font-size:11px;font-weight:700;color:#9a7aaa;letter-spacing:0.08em;text-transform:uppercase;margin:4px 0 10px;">Business Settings</div>

    <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:18px 20px;margin-bottom:24px;">
        <div style="font-size:14px;font-weight:700;color:#1f2937;margin-bottom:4px;">
            Referral Rewards
            <div class="help-hint" tabindex="0">
                <span class="help-icon">?</span>
                <div class="help-card">
                    <strong>What this does</strong>
                    <p style="margin:6px 0 0;">Lets staff give a wallet credit to a patient who referred someone, but only once that referred patient has actually paid for a visit — so rewards only go out for real, converted referrals.</p>
                    <p class="help-example">Example: Priya refers her friend Raj. Raj visits and pays ₹2,000 for a cleaning. Now a "Reward ₹500" button appears on Priya's profile — click it once and ₹500 is added to her wallet, usable on her next bill.</p>
                </div>
            </div>
        </div>
        <div style="font-size:12.5px;color:#7a6884;margin-bottom:14px;">
            When on, a "Reward" button appears on a patient's Referral panel once someone they referred has paid
            for at least one visit. Staff click it once to credit the amount below to the referrer's wallet.
        </div>

        <form action="{{ route('relationship.settings.referral') }}" method="POST"
              style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
            @csrf
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#1a0a24;">
                <input type="checkbox" name="enabled" value="1" {{ $referralRewardEnabled ? 'checked' : '' }} style="width:16px;height:16px;">
                Enabled
            </label>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#1a0a24;">
                Amount (₹)
                <input type="number" name="amount" value="{{ $referralRewardAmount }}" min="0" step="1"
                       style="width:110px;padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
            </label>
            <button type="submit" style="padding:7px 16px;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:12.5px;font-weight:600;cursor:pointer;">
                Save
            </button>
        </form>
    </div>

    {{-- ══════════════════════ RECALL ══════════════════════
         One organized "Recall" area — merges what used to be two separate
         recall-ish blocks: the Go-Live Date (below) plus General/Treatment-
         wise/Birthday settings (moved from Communication OS's
         standalone Recall & Birthday Settings page, 2026-07-06 — see
         under_review/pre_consolidation_2026_07_06/ for the archived original).
         Same AppSetting keys throughout, so RecallEngineService /
         RecallAutomationRunner behaviour is unchanged — only the settings UI
         moved and got tidier. --}}
    <div style="font-size:11px;font-weight:700;color:#9a7aaa;letter-spacing:0.08em;text-transform:uppercase;margin:4px 0 10px;">Recall</div>

    <style>
        .rs-card { background:#fff; border:1px solid #eceef2; border-radius:10px; padding:18px 20px; margin-bottom:24px; }
        .rs-card__title { display:flex; align-items:center; gap:8px; font-size:14px; font-weight:700; color:#1f2937; margin-bottom:4px; }
        .rs-card__desc { font-size:12.5px; color:#7a6884; margin-bottom:14px; }
        .rs-gear {
            display:inline-flex; align-items:center; justify-content:center;
            width:26px; height:26px; border-radius:6px; background:#f5f0f8; color:#6a0f70;
            text-decoration:none; flex-shrink:0;
        }
        .rs-gear:hover { background:#ede0f3; }
        .rs-input { padding:6px 10px; border:1px solid #d1d5db; border-radius:6px; font-size:13px; width:110px; }
        .rs-save-btn { padding:7px 16px; background:#6a0f70; color:#fff; border:none; border-radius:6px; font-size:12.5px; font-weight:600; cursor:pointer; }
        .rs-configured-hint { font-size:11px; color:#1a7a45; margin-left:6px; }
        .rs-unconfigured-hint { font-size:11px; color:#b5842a; margin-left:6px; }
        .rs-table { width:100%; border-collapse:collapse; }
        .rs-table th { text-align:left; font-size:11px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:#9a7aaa; padding:8px 10px; border-bottom:1px solid #eceef2; }
        .rs-table td { padding:8px 10px; border-bottom:1px solid #f5f0f8; font-size:13px; color:#1a0320; }
    </style>

    {{-- ── Recall Automation Go-Live Date ── --}}
    <div class="rs-card">
        <div class="rs-card__title">
            Recall Automation Go-Live Date
            <div class="help-hint" tabindex="0">
                <span class="help-icon">?</span>
                <div class="help-card">
                    <strong>What this does</strong>
                    <p style="margin:6px 0 0;">The "no visit in 6 months" recall only auto-queues patients whose last visit falls on or after this date. Patients with no recorded visit, or whose last visit predates it (old/migrated history), are left out of automatic recall — but the moment a real visit is logged for them, they're back in scope like anyone else.</p>
                    <p class="help-example">Example: you import years of old patient records on 4 July. Without a go-live date, the system sees all of them as "6+ months overdue" and dumps the entire history into today's call list at once. Set the go-live date to 4 July, and only patients who've actually visited since then — and have now genuinely gone quiet for 6 months — get auto-queued.</p>
                </div>
            </div>
        </div>
        <div class="rs-card__desc">
            Leave blank for the old, unrestricted behaviour. Historical patients excluded here aren't lost — they can still
            be called from a manual list; this only controls what the automation queues by itself.
        </div>

        <form action="{{ route('relationship.settings.recall-effective-from') }}" method="POST"
              style="display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            @csrf
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#1a0a24;">
                Starts from
                <input type="date" name="effective_from" value="{{ $recallEffectiveFrom }}"
                       style="padding:6px 10px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;">
            </label>
            <button type="submit" class="rs-save-btn">Save</button>
            @if($recallEffectiveFrom)
            <span style="font-size:12px;color:#9ca3af;">Currently active — clear the date and save to remove.</span>
            @endif
        </form>
    </div>

    {{-- ── General Recall ── --}}
    <div class="rs-card">
        <div class="rs-card__title">
            General Recall
            <a href="{{ route('relationship.templates.forType', 'recall') }}" class="rs-gear" title="Edit the Recall message template">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            </a>
            @if($recallTemplate)
                <span class="rs-configured-hint">Template configured</span>
            @else
                <span class="rs-unconfigured-hint">Using default message</span>
            @endif
        </div>
        <div class="rs-card__desc">
            How many days of inactivity before a patient is queued for a general recall, and which
            channels the Recall Engine is allowed to use. (Channels here only gate the engine's own
            behaviour — sending itself is still a manual staff action from the Recall queue today.)
        </div>

        <form action="{{ route('relationship.settings.recall-general') }}" method="POST" style="display:flex;flex-wrap:wrap;align-items:center;gap:20px;">
            @csrf
            <label style="font-size:13px;color:#1a0a24;display:flex;align-items:center;gap:8px;">
                Recall after
                <input type="number" name="general_days" value="{{ $recallGeneralDays }}" min="1" max="3650" class="rs-input" style="width:80px;">
                days
            </label>

            <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#1a0a24;">
                <input type="checkbox" name="channel_whatsapp" value="1" {{ $recallChannels['whatsapp'] ? 'checked' : '' }} style="width:15px;height:15px;">
                WhatsApp
            </label>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#1a0a24;">
                <input type="checkbox" name="channel_sms" value="1" {{ $recallChannels['sms'] ? 'checked' : '' }} style="width:15px;height:15px;">
                SMS
            </label>
            <label style="display:flex;align-items:center;gap:6px;font-size:13px;color:#1a0a24;">
                <input type="checkbox" name="channel_email" value="1" {{ $recallChannels['email'] ? 'checked' : '' }} style="width:15px;height:15px;">
                Email
            </label>

            <button type="submit" class="rs-save-btn">Save</button>
        </form>
    </div>

    {{-- ── Treatment-wise Recall ── --}}
    <div class="rs-card">
        <div class="rs-card__title">Treatment-wise Recall</div>
        <div class="rs-card__desc">
            Override the recall periodicity for specific treatment types (e.g. cleanings every 180
            days, implants every 365 days). Leave blank to use the General Recall periodicity above.
            All treatment types share the same Recall message template (edit it via the gear icon
            above) — only the timing differs per treatment, to avoid a template per treatment type.
        </div>

        @if($recallTreatmentTypes->isEmpty())
            <div style="font-size:12.5px;color:#9a7aaa;">No treatment types configured yet.</div>
        @else
        <table class="rs-table">
            <thead>
                <tr>
                    <th>Treatment</th>
                    <th style="width:200px;">Recall after (days)</th>
                    <th style="width:90px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($recallTreatmentTypes as $tt)
                <tr>
                    <form action="{{ route('relationship.settings.recall-treatment', $tt->id) }}" method="POST">
                        @csrf
                        <td>{{ $tt->name }}</td>
                        <td>
                            <input type="number" name="recall_after_days" value="{{ $tt->recall_after_days }}"
                                   min="1" max="3650" placeholder="{{ $recallGeneralDays }} (default)" class="rs-input">
                        </td>
                        <td><button type="submit" class="rs-save-btn">Save</button></td>
                    </form>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>

    {{-- ── Birthday Reminder ── --}}
    <div class="rs-card">
        <div class="rs-card__title">
            Birthday Reminder
            <a href="{{ route('relationship.templates.forType', 'birthday') }}" class="rs-gear" title="Edit the Birthday message template">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
            </a>
            @if($birthdayTemplate)
                <span class="rs-configured-hint">Template configured</span>
            @else
                <span class="rs-unconfigured-hint">Using default message</span>
            @endif
        </div>
        <div class="rs-card__desc">
            Queues a recall (and shows on Today's Actions) when a patient's birthday falls within
            the window below. Turning this off hides birthdays from both the Recall Engine and
            Today's Actions.
        </div>

        <form action="{{ route('relationship.settings.recall-birthday') }}" method="POST" style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
            @csrf
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#1a0a24;">
                <input type="checkbox" name="enabled" value="1" {{ $birthdayEnabled ? 'checked' : '' }} style="width:16px;height:16px;">
                Enabled
            </label>
            <label style="font-size:13px;color:#1a0a24;display:flex;align-items:center;gap:8px;">
                Window
                <input type="number" name="window_days" value="{{ $birthdayWindowDays }}" min="0" max="30" class="rs-input" style="width:70px;">
                day(s) before/after
            </label>
            <button type="submit" class="rs-save-btn">Save</button>
        </form>
    </div>

    {{-- ══════════════════════ CALL OUTCOMES & DISMISS REASONS (2026-07-06) ══════════════════════
         See docs/feature-specs/feature-spec-custom-call-outcomes.md and
         feature-spec-action-board-dismiss.md. Each category's outcome set
         feeds the "Call outcome" dropdown in the Today's Actions drawer for
         that category; if a category has zero active rows here, the drawer
         falls back to the built-in default set (config/relationship_rules.php)
         — nothing breaks if you don't touch this section. --}}
    <div style="font-size:11px;font-weight:700;color:#9a7aaa;letter-spacing:0.08em;text-transform:uppercase;margin:4px 0 10px;">Call Outcomes</div>

    @php $categoryLabels = \App\Http\Controllers\Relationship\SettingsController::callOutcomeCategoryLabels(); @endphp

    @foreach($categoryLabels as $catKey => $catLabel)
    @php $rows = $callOutcomeCategories[$catKey] ?? collect(); @endphp
    <div class="rs-card">
        <div class="rs-card__title">{{ $catLabel }}</div>
        <div class="rs-card__desc">
            @if($rows->isEmpty())
                No custom outcomes configured yet — the drawer is using the built-in default set for this category.
            @else
                Shown in this order in the "Call outcome" dropdown. Untick Active to hide an option without losing its history.
            @endif
        </div>

        @if($rows->isNotEmpty())
        <table class="rs-table">
            <thead>
                <tr>
                    <th>Label</th>
                    <th style="width:80px;">Order</th>
                    <th style="width:90px;">Requires note</th>
                    <th style="width:90px;">
                        Auto-closes
                        <div class="help-hint" tabindex="0">
                            <span class="help-icon">?</span>
                            <div class="help-card">
                                <strong>What this does</strong>
                                <p style="margin:6px 0 0;">When staff log this outcome on the Action Board, ticked = the row is removed automatically (call is resolved). Unticked = the row stays so staff can retry or Close it manually — use this for outcomes like "No answer" where nothing was actually accomplished.</p>
                            </div>
                        </div>
                    </th>
                    <th style="width:70px;">Active</th>
                    <th style="width:70px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $opt)
                <tr>
                    <form action="{{ route('relationship.settings.call-outcomes.save', $opt->id) }}" method="POST">
                        @csrf
                        <td><input type="text" name="label" value="{{ $opt->label }}" class="rs-input" style="width:100%;"></td>
                        <td><input type="number" name="sort_order" value="{{ $opt->sort_order }}" min="0" class="rs-input" style="width:60px;"></td>
                        <td style="text-align:center;"><input type="checkbox" name="requires_notes" value="1" {{ $opt->requires_notes ? 'checked' : '' }}></td>
                        <td style="text-align:center;"><input type="checkbox" name="closes_task" value="1" {{ $opt->closes_task ? 'checked' : '' }}></td>
                        <td style="text-align:center;"><input type="checkbox" name="is_active" value="1" {{ $opt->is_active ? 'checked' : '' }}></td>
                        <td><button type="submit" class="rs-save-btn">Save</button></td>
                    </form>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif

        <form action="{{ route('relationship.settings.call-outcomes.add', $catKey) }}" method="POST"
              style="display:flex;align-items:center;gap:10px;margin-top:12px;">
            @csrf
            <input type="text" name="label" placeholder="Add a new outcome for this category…" required
                   class="rs-input" style="width:280px;">
            <button type="submit" class="rs-save-btn" style="background:#fff;color:#6a0f70;border:1px solid #6a0f70;">+ Add</button>
        </form>
    </div>
    @endforeach

    {{-- ── Dismiss Reasons — shared across every Action Board category ── --}}
    <div class="rs-card">
        <div class="rs-card__title">
            Dismiss Reasons
            <div class="help-hint" tabindex="0">
                <span class="help-icon">?</span>
                <div class="help-card">
                    <strong>What this does</strong>
                    <p style="margin:6px 0 0;">When staff clear a Today's Actions row without logging a call outcome (e.g. a duplicate, or the patient was already handled elsewhere), they must pick one of these reasons — so the outcome data above stays honest and rows aren't cleared with a fake call result.</p>
                </div>
            </div>
        </div>
        <div class="rs-card__desc">Same list shown for every category on the Action Board — one shared set, not per-category.</div>

        <table class="rs-table">
            <thead>
                <tr>
                    <th>Label</th>
                    <th style="width:80px;">Order</th>
                    <th style="width:90px;">Requires note</th>
                    <th style="width:70px;">Active</th>
                    <th style="width:70px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($dismissReasonOptions as $opt)
                <tr>
                    <form action="{{ route('relationship.settings.dismiss-reasons.save', $opt->id) }}" method="POST">
                        @csrf
                        <td><input type="text" name="label" value="{{ $opt->label }}" class="rs-input" style="width:100%;"></td>
                        <td><input type="number" name="sort_order" value="{{ $opt->sort_order }}" min="0" class="rs-input" style="width:60px;"></td>
                        <td style="text-align:center;"><input type="checkbox" name="requires_notes" value="1" {{ $opt->requires_notes ? 'checked' : '' }}></td>
                        <td style="text-align:center;"><input type="checkbox" name="is_active" value="1" {{ $opt->is_active ? 'checked' : '' }}></td>
                        <td><button type="submit" class="rs-save-btn">Save</button></td>
                    </form>
                </tr>
                @endforeach
            </tbody>
        </table>

        <form action="{{ route('relationship.settings.dismiss-reasons.add') }}" method="POST"
              style="display:flex;align-items:center;gap:10px;margin-top:12px;">
            @csrf
            <input type="text" name="label" placeholder="Add a new dismiss reason…" required
                   class="rs-input" style="width:280px;">
            <button type="submit" class="rs-save-btn" style="background:#fff;color:#6a0f70;border:1px solid #6a0f70;">+ Add</button>
        </form>
    </div>

    {{-- ── Advanced / Engineering — collapsed by default ── --}}
    <button type="button" @click="advancedOpen = !advancedOpen"
            style="width:100%;display:flex;align-items:center;justify-content:space-between;gap:12px;background:#faf7fb;border:1px solid #eceef2;border-radius:10px;padding:14px 18px;cursor:pointer;margin-bottom:2px;">
        <span style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:13.5px;font-weight:700;color:#1f2937;">Advanced / Engineering</span>
            <span style="font-size:11px;color:#9a7aaa;">Internal cutover switches — leave as-is unless you know what you're changing</span>
        </span>
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#6a0f70" stroke-width="2.5" stroke-linecap="round"
             x-bind:style="'transition:transform .15s;transform:rotate(' + (advancedOpen ? '180deg' : '0deg') + ')'">
            <path d="m6 9 6 6 6-6"/>
        </svg>
    </button>

    <div x-show="advancedOpen" x-cloak x-transition style="margin-top:14px;">

        <div style="background:#fff5e6;border:1px solid #f4d9a8;border-radius:12px;padding:14px 18px;margin-bottom:16px;font-size:12.5px;color:#8a5a00;">
            These switch on new engine behavior one piece at a time. Most clinics never need to touch these —
            only change one if you understand what it does. Every change here is logged.
        </div>

        @foreach($flagGroups as $groupLabel => $keys)
        <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:18px 20px;margin-bottom:14px;">
            <div style="font-size:13px;font-weight:700;color:#1f2937;margin-bottom:4px;">{{ $groupLabel }}</div>

            @foreach($keys as $flagKey)
            @php $flag = $featureFlags[$flagKey] ?? null; @endphp
            @continue(!$flag)
            <div style="padding:12px 0;border-top:1px solid #f3ecf7;"
                 x-init="state['{{ $flagKey }}'] = {{ $flag['resolved'] ? 'true' : 'false' }}">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13.5px;font-weight:600;color:#1a0a24;">
                            {{ $flag['description'] }}
                            @if(isset($flagHelp[$flagKey]))
                            <div class="help-hint" tabindex="0">
                                <span class="help-icon">?</span>
                                <div class="help-card">
                                    <strong>{{ $flagHelp[$flagKey]['title'] }}</strong>
                                    <p style="margin:6px 0 0;">{{ $flagHelp[$flagKey]['explain'] }}</p>
                                    <p class="help-example">Example: {{ $flagHelp[$flagKey]['example'] }}</p>
                                </div>
                            </div>
                            @endif
                        </div>
                        <div class="pre-flag-key">{{ $flagKey }}</div>
                    </div>
                    <button type="button" class="pre-switch"
                            @click="arm('{{ $flagKey }}', !state['{{ $flagKey }}'])"
                            :disabled="busy === '{{ $flagKey }}'"
                            x-bind:style="'background:' + (state['{{ $flagKey }}'] ? '#6a0f70' : '#d8d2de')">
                        <span class="pre-switch-knob"
                              x-bind:style="'transform:translateX(' + (state['{{ $flagKey }}'] ? '20px' : '0px') + ')'"></span>
                    </button>
                </div>
                {{-- Inline confirm bar — replaces window.confirm(), which was found to
                     freeze the page (a blocking native dialog is bad UX either way). --}}
                <div x-show="pendingKey === '{{ $flagKey }}'" x-cloak x-transition
                     style="margin-top:8px;display:flex;align-items:center;gap:10px;background:#f9f5fc;border:1px solid #e8d5f0;border-radius:8px;padding:8px 12px;">
                    <span style="font-size:12.5px;color:#4a3a54;" x-text="'Turn ' + (pendingNext ? 'ON' : 'OFF') + ' ' + pendingKey + '?'"></span>
                    <button type="button" @click="confirmToggle()" :disabled="busy === '{{ $flagKey }}'"
                            style="margin-left:auto;padding:4px 12px;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:12px;font-weight:600;cursor:pointer;">
                        <span x-show="busy !== '{{ $flagKey }}'">Yes, confirm</span>
                        <span x-show="busy === '{{ $flagKey }}'">Working…</span>
                    </button>
                    <button type="button" @click="cancelToggle()" :disabled="busy === '{{ $flagKey }}'"
                            style="padding:4px 12px;background:#fff;color:#6b7280;border:1px solid #d1d5db;border-radius:6px;font-size:12px;cursor:pointer;">
                        Cancel
                    </button>
                </div>
            </div>
            @endforeach
        </div>
        @endforeach

        <p style="margin-top:8px;color:#9ca3af;font-size:12px;">
            Looking for Communication Guard, Integrations, Workflow Engine or Search flags? Those are shared across
            the whole app (not just PRE) and stay in the main app Settings page.
        </p>
    </div>

</div>

<script>
function preFlagsPanel() {
    return {
        advancedOpen: false,
        state: {},
        busy: null,
        pendingKey: null,
        pendingNext: null,
        arm(key, next) {
            if (this.busy) return;
            this.pendingKey = key;
            this.pendingNext = next;
        },
        cancelToggle() {
            this.pendingKey = null;
            this.pendingNext = null;
        },
        confirmToggle() {
            const key = this.pendingKey, next = this.pendingNext;
            if (!key) return;
            this.busy = key;
            fetch('{{ route('relationship.settings.toggle') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ key: key, enabled: next }),
            })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        this.state[key] = data.enabled;
                    } else {
                        alert(data.message || 'Could not update that flag.');
                    }
                })
                .catch(() => alert('Network error — flag not changed.'))
                .finally(() => {
                    this.busy = null;
                    this.pendingKey = null;
                    this.pendingNext = null;
                });
        },
    };
}
</script>
@endsection
