{{--
|==========================================================================
| Dentfluence OS — Module Settings Breadcrumb (Settings Architecture v2, Phase 1)
| File: resources/views/components/module-settings-breadcrumb.blade.php
|
| Frozen decision (spec Section 2, Q5): every module-owned settings page
| shows "<Module> > Settings > [Section]" and the first segment always links
| back to the module itself — never to a generic settings homepage. This is
| what lets a dentist who opened Billing, then opened its settings, land
| back in Billing with one click instead of navigating out to a hub first.
|
| Variables:
|   $moduleLabel  string       — e.g. "Billing"
|   $moduleRoute  string       — route name for the module's own home page
|   $section      string|null — e.g. "Tax & Invoicing" (omit for the
|                                module's settings landing page itself)
|
| Usage:
|   @include('components.module-settings-breadcrumb', [
|       'moduleLabel' => 'Billing',
|       'moduleRoute' => 'finance.dashboard',
|       'section'     => 'Tax & Invoicing',
|   ])
|==========================================================================
--}}
<nav aria-label="Breadcrumb" style="font-size:11.5px;color:#9a7aaa;margin-bottom:14px;font-family:'Inter',sans-serif;">
    <a href="{{ route($moduleRoute) }}" style="color:#9a7aaa;text-decoration:none;">{{ $moduleLabel }}</a>
    <span style="margin:0 6px;opacity:.6;">&gt;</span>
    @if(!empty($section))
        <span style="color:#9a7aaa;">Settings</span>
        <span style="margin:0 6px;opacity:.6;">&gt;</span>
        <span style="color:#3a0050;font-weight:600;">{{ $section }}</span>
    @else
        <span style="color:#3a0050;font-weight:600;">Settings</span>
    @endif
</nav>
