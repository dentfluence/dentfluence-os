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
| Variables from Relationship\SettingsController@index: $featureFlags, $flagGroups
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

    <div style="background:#fff;border:1px solid #eceef2;border-radius:10px;padding:18px 20px;margin-bottom:24px;">
        <div style="font-size:14px;font-weight:700;color:#1f2937;margin-bottom:4px;">
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
        <div style="font-size:12.5px;color:#7a6884;margin-bottom:14px;">
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
            <button type="submit" style="padding:7px 16px;background:#6a0f70;color:#fff;border:none;border-radius:6px;font-size:12.5px;font-weight:600;cursor:pointer;">
                Save
            </button>
            @if($recallEffectiveFrom)
            <span style="font-size:12px;color:#9ca3af;">Currently active — clear the date and save to remove.</span>
            @endif
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
