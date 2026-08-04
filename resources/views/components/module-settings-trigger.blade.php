{{--
|==========================================================================
| Dentfluence OS — Module Settings Trigger (Settings Architecture v2, Phase 1)
| File: resources/views/components/module-settings-trigger.blade.php
|
| Frozen decision #1: no gear icon in the sidebar. Every module exposes its
| own Settings from inside that module's own page header (or its overflow
| menu). This component is the ONE reusable trigger every module uses, so
| the affordance looks and behaves identically everywhere — a dentist who
| has learned it once on Appointments recognises it instantly on Billing.
|
| Variables:
|   $moduleSlug  string       — module slug, checked against
|                                auth()->user()->canAccess($moduleSlug,'settings')
|   $route       string       — route name for that module's Settings page
|   $label       string|null  — visible label (default: "Settings")
|   $variant     string       — 'button' (default, for a page header) |
|                                'menu-item' (for an overflow/kebab menu)
|
| Usage (module page header) — matches the @include convention already used
| by components/sidebar-item.blade.php elsewhere in this codebase:
|
|   @include('components.module-settings-trigger', [
|       'moduleSlug' => 'billing',
|       'route'      => 'billing.settings.index',
|   ])
|
| Usage (inside an overflow menu):
|   @include('components.module-settings-trigger', [
|       'moduleSlug' => 'billing',
|       'route'      => 'billing.settings.index',
|       'variant'    => 'menu-item',
|   ])
|
| (Laravel also auto-registers anything in resources/views/components/ as an
| <x-module-settings-trigger :module-slug="..." :route="..." /> tag component
| — both syntaxes work identically; @include is used in the examples above
| purely to match this codebase's existing convention.)
|
| Renders nothing if the current user lacks the module's `.settings`
| permission — consistent with how the rest of the app hides, rather than
| disables-with-a-lock, actions a user cannot reach at all in read contexts.
|==========================================================================
--}}
@php
    $label   = $label   ?? 'Settings';
    $variant = $variant ?? 'button';
    $user    = auth()->user();
    $allowed = $user && $user->canAccess($moduleSlug, 'settings');
@endphp

@if($allowed)
    @if($variant === 'menu-item')
        <a href="{{ route($route) }}" class="df-module-settings-menu-item" style="display:flex;align-items:center;gap:8px;padding:8px 14px;font-size:13px;color:#3a0050;text-decoration:none;">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
            {{ $label }}
        </a>
    @else
        <a href="{{ route($route) }}"
           class="df-module-settings-btn"
           title="{{ $label }}"
           style="display:inline-flex;align-items:center;justify-content:center;gap:6px;width:32px;height:32px;border-radius:8px;background:#f5f0f8;color:#6a0f70;text-decoration:none;flex-shrink:0;transition:background 120ms,color 120ms;"
           onmouseover="this.style.background='#6a0f70';this.style.color='#fff';"
           onmouseout="this.style.background='#f5f0f8';this.style.color='#6a0f70';">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>
            </svg>
        </a>
    @endif
@endif
