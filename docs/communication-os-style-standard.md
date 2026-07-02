# Communication OS — Style Standard & Conversion Checklist

_Created 2026-06-26 as part of the Communication OS v2.0 cleanup. The mechanical, zero-risk parts are already applied (see "Done"). The per-page CSS conversions are listed here for a supervised pass — they change how pages **look**, so they want eyes on a running clinic before merge._

## The standard (what every Communication page should follow)

1. **Layout contract:** `@extends('layouts.communication')` + `@section('communication-content')`. Never `@section('content')` (that silently overrides the module chrome — back-button + add-lead modal).
2. **Page header:** one shared pattern. Use `<x-communication.top-nav-tabs>` where a tab bar belongs, and a single header block (target: a shared `.co-page-header`) instead of per-page `page-header` / `cl-*` / `b2b-*` / `re-*` / `opp-*` / `fu-*` classes.
3. **Components are the vocabulary:** reuse `components/communication/*` (filter-bar, queue-card, status-chip, empty-state, overdue-badge…) and `components/prm/*` (lead-card, stage-badge, add-lead-modal…). Don't hand-roll cards/badges per page.
4. **No per-page `<style>` blocks and no inline `style=` soup.** Shared rules live in `resources/css/communication/module.css`.
5. **One font:** DM Sans via `var(--comm-font)`. No serif, no Inter.
6. **One purple.** Use `var(--comm-brand)` (plum `#6a0f70`). See the switch below.
7. **Tokens, not hex:** colors come from the `--comm-*` / `--c-*` variables in `module.css`.

## Already done (safe, applied)

- 16 views moved off the wrong `@section('content')` onto `@section('communication-content')` so they inherit the module shell.
- `templates/index` rogue serif/`Inter`/`#1a0320` header → realigned to DM Sans + module tokens.
- Added a single canonical brand token `--comm-brand` (+ `-bg`, `-border`) to `module.css`; the layout back-button now references it (no visual change).

## The "one purple" switch (one-line, do when you can eyeball it)

The module still ships two purples: the plum brand (`--comm-brand` `#6a0f70`) and the indigo `--prm-primary` (`#534AB7`) used across the PRM pages. To unify on the plum brand, in `module.css` set:

```css
--prm-primary:        #6a0f70;   /* was #534AB7 */
--prm-primary-light:  #fdf4ff;   /* was #EEEDFE  */
--prm-primary-text:   #4a0a4f;   /* was #3C3489  */
```

Left as a deliberate decision because it recolors every PRM page — flip it, load the PRM board, and confirm you like the plum before committing.

## Per-page conversion checklist (divergent pages → standard)

Each of these defines its own header namespace and/or `<style>` block / inline styles. Convert to `co-page-header` + shared components, move CSS into `module.css`, swap hex for tokens.

- [ ] `manager/index`, `manager/show`, `manager/queue`, `manager/overdue`, `manager/log-form` — `cl-*` classes + heavy inline styles (`show` has ~67 inline `style=`).
- [ ] `b2b/index`, `b2b/create`, `b2b/show` — `b2b-*` classes + inline `<style>`.
- [ ] `recall/index` — `re-*` classes + inline `<style>`.
- [ ] `opportunities/index`, `opportunities/board`, `opportunities/detail` — `opp-*` + inline styles (`detail` ~31).
- [ ] `followup/index`, `queue`, `overdue`, `calendar`, `recalls` — `fu-*` + inline.
- [ ] `kpi/index` — plain `<h1>` + inline `<style>` + ~54 inline `style=`.
- [ ] PRM analytics pages — `prm/inbox`, `prm/channel-roi`, `prm/team-performance`, `prm/source-analytics` — 30+ inline styles each.
- [ ] `prm/lead-detail` and `huddle/widgets` — currently `@extends('layouts.app')` with their own branded topbar (`prm.css`). Decide: adopt the module shell, or promote their topbar to the module standard. (This is the one case where the bespoke version may be the nicer target — a design call.)

## Suggested approach

Do it one namespace at a time (manager, then b2b, then recall…), each as its own small PR you can visually verify. Start by lifting the repeated card/header CSS into `module.css` as `co-*` rules, then delete the per-page `<style>` block and rename classes. The component set already exists — most pages can drop their bespoke cards for `<x-communication.queue-card>` etc.
