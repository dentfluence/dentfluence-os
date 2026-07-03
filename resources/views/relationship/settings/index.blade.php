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
            Feature flags that control which parts of PRE are live. These live here (not in the main app Settings)
            so this module can be configured on its own.
        </p>
    </div>

    <style>
        /* Static layout only — dynamic colour/position comes from x-bind:style,
           which REPLACES a plain-string style attribute rather than merging it.
           Keeping the fixed geometry in a class avoids that Alpine gotcha. */
        .pre-switch {
            flex-shrink: 0; width: 44px; height: 24px; border-radius: 999px;
            border: none; position: relative; cursor: pointer; transition: background .15s;
        }
        .pre-switch-knob {
            position: absolute; top: 2px; width: 20px; height: 20px;
            border-radius: 50%; background: #fff; transition: transform .15s;
        }
    </style>

    <div style="background:#fff5e6;border:1px solid #f4d9a8;border-radius:12px;padding:16px 20px;margin-bottom:20px;font-size:12.5px;color:#8a5a00;">
        These switches control which parts of the Relationship Engine (PRE) are live. Most are safe to leave as-is —
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
                    <div style="font-size:13px;font-weight:600;color:#1a0a24;font-family:monospace;">{{ $flagKey }}</div>
                    <div style="font-size:12.5px;color:#7a6884;margin-top:2px;">{{ $flag['description'] }}</div>
                </div>
                <button type="button" class="pre-switch"
                        @click="arm('{{ $flagKey }}', !state['{{ $flagKey }}'])"
                        :disabled="busy === '{{ $flagKey }}'"
                        x-bind:style="'background:' + (state['{{ $flagKey }}'] ? '#1a7a45' : '#c9c3ce')">
                    <span class="pre-switch-knob"
                          x-bind:style="'transform:translateX(' + (state['{{ $flagKey }}'] ? '20px' : '2px') + ')'"></span>
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

<script>
function preFlagsPanel() {
    return {
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
