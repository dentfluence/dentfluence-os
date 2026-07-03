# Phase 1 · Workstream F — PRE Navigation Cutover

Makes **PRE the front door**: the main sidebar's Communication entry shows **Relationships** (→ `/relationship/dashboard`) as primary, with **PRM demoted to a "Legacy" link** (still reachable — nothing removed). Flag-gated and instantly reversible.

## What changed

| Piece | File |
|---|---|
| Flag (default **off**) | `config/features.php` → `nav.pre_primary` |
| Sidebar swap | `resources/views/partials/sidebar.blade.php` (Communication section) |

**Behaviour:**
- **Flag OFF (default):** sidebar shows **"PRM"** → `communication.index` — exactly as before. Zero change.
- **Flag ON:** section relabels to **"Relationships"**; primary item is **Relationships** → `relationship.dashboard`; a second item **"PRM (Legacy)"** → `communication.index` keeps the old board one click away.

**Safety:** purely presentational + additive. No routes, controllers, PRM pages, or data touched. No migration. The legacy PRM board and all its routes remain fully functional. Reversible instantly by flipping the flag.

**Not changed (intentionally):** the PRM module's *internal* topbar title and sub-sidebar still say "PRM" — correct, since that area *is* the legacy PRM surface, now reached via the Legacy link.

## Verify / enable / rollback (you)

```
# Enable PRE as the primary sidebar entry:
php artisan tinker --execute="\App\Support\Features\Feature::set('nav.pre_primary', true);"
```
Then reload the app — the left sidebar's Communication section should now read **Relationships** (primary) + **PRM (Legacy)**. Click Relationships → lands on `/relationship/dashboard`; click PRM (Legacy) → the old board still works.

```
# Rollback (instant, no data change) — back to PRM primary:
php artisan tinker --execute="\App\Support\Features\Feature::set('nav.pre_primary', false);"
```

Per-clinic is possible too: `Feature::set('nav.pre_primary', true, branchId: <id>)`.

## Note
No automated test added — this is a flag-gated presentational swap and default-off means existing page-render tests are unaffected. Verify visually. Full PRM *removal* is deliberately **not** part of this — that only happens much later after a production soak (blueprint "delete legacy after soak").

## This completes the Phase 1 "receptionist works on PRE" goal
With F, the receptionist's front door becomes PRE (once the flag is flipped), while PRM survives as a compatibility/Legacy surface — exactly the Phase 1 exit criterion.
