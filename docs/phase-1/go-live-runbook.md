# Phase 1 (PRE) — Go-Live Runbook

**Goal:** take the built-and-tested PRE from "works on Laragon" to "a receptionist runs their clinic day on PRE," reversibly, without losing any PRM workflow or breaking the mobile app.

**Golden rule:** every step is behind a flag or is additive. If anything looks wrong, flip the flag off — reads return to the previous behaviour instantly. Do **one** flag at a time, verify, then move on.

Flags are set from tinker:

```php
// global (all clinics)
\App\Support\Features\Feature::set('flag.key', true);
// per clinic (preferred during rollout)
\App\Support\Features\Feature::set('flag.key', true, $branchId);
// rollback
\App\Support\Features\Feature::set('flag.key', false);
```

Current live state to preserve: `activity.single_ledger_reads = ON`. All other PRE flags start **off**.

---

## Stage 0 — Deploy the code (infra track, do first)

PRE currently runs on Laragon (`dentfluence.test`). Nothing is live to real users until the branch is deployed to the Hostinger VPS and the domain points at it.

The Docker kit does the heavy lifting — `./deploy.sh` on the VPS: pulls the branch, builds images, brings up the stack (`app`, `nginx`, `mysql`, `queue`, `scheduler`), runs `migrate --force` (creates `today_actions` etc.), caches config + views, **warms the Today's Actions projection**, and restarts `queue` + `scheduler`. (`route:cache` is deliberately skipped — a few redirect-closure routes can't be cached; see the note in `deploy.sh`.)

- [ ] Copy a filled `.env.production` onto the VPS (real `APP_KEY`, DB creds, domain).
- [ ] `./deploy.sh` — one command; watch it finish "Deploy complete".
- [ ] `docker compose --env-file .env.production ps` — confirm the **scheduler** and **queue** containers are `Up` (E5's 15-min rebuild + the score/notification jobs depend on them).
- [ ] Point DNS (`dentfluence.in`) → VPS and put SSL in front of nginx (host port 8080); confirm HTTPS.
- [ ] Smoke-test: log in, open `/relationship/dashboard`, `/relationship/today`, `/huddle`; hit `/api/v1/ping`.

---

## Stage 1 — Validate identity + journeys on production data

We proved these locally (3,814 relationships, 0 unlinked; 0 journey divergence). Re-prove on prod **before** flipping any read flag. All dry-runs are read-only.

- [ ] `php artisan relationship:backfill` — dry-run. Expect **0 unlinked** leads/patients after review.
- [ ] If anything is unlinked: `php artisan relationship:backfill --apply` (idempotent), then re-run the dry-run to confirm 0.
- [ ] `php artisan relationship:sync-journeys` — dry-run. Expect **Would reconcile (diverged) = 0** and **Skipped = 0**. "Would create" is fine (new leads).
- [ ] If "would create" > 0: `php artisan relationship:sync-journeys --apply`, then re-run the dry-run.
- [ ] `php artisan today:rebuild-projection` then `--check` → expect **Parity OK**.

**Gate:** do not proceed past Stage 1 until backfill = 0 unlinked and journey divergence = 0.

---

## Stage 2 — Flip the flags (one at a time, per clinic)

Order matters: link new records → make PRE reads primary → serve Today's Actions from the projection → demote PRM. Verify after each; rollback = set the flag `false`.

| # | Flag | What it does | Verify | Rollback |
|---|---|---|---|---|
| 2.1 | `identity.link_patient` | New patients auto-link to a Master Relationship (existing data already backfilled) | Create a test patient → confirm a `relationships` row links to it; check `/relationship/{id}` | set `false` (existing links untouched) |
| 2.2 | `today.projection` | `/relationship/today` served from the projection, not the live 12-domain reader | Open `/relationship/today` — same items as before; `/relationship/reception` shows a fresh "updated … ago" | set `false` → live engine |
| 2.3 | `identity.reads_relationship` | PRE becomes the primary read surface | Open several `/relationship/{id}` profiles + pipelines — data correct, timeline intact | set `false` |
| 2.4 | `prm.secondary` | Legacy PRM board redirects to the PRE pipeline (still reachable via `?legacy=1`) | `/communication/prm/board` → lands on `/relationship/pipeline`; "Legacy PRM board" link reopens PRM | set `false` → PRM primary |

Notes:
- After 2.1, re-run `relationship:backfill --apply` once to sweep up anything created during the flip window.
- 2.2 requires the scheduler running (Stage 0) so the projection stays fresh.
- Keep `activity.single_ledger_reads` **on** throughout.

**Do NOT flip** `journey.authoritative` — journeys becoming the pipeline's single source of truth is Blueprint **Phase 4**, not this cutover. PRE Phase 1 keeps journeys in shadow.

---

## Stage 3 — Acceptance & cutover

- [ ] Dogfood a full reception day at **Tulip Dental**: new enquiry → lead pipeline → book → convert; recall + opportunity; Today's Actions / reception queue; a payment reminder.
- [ ] Confirm PRM still works when reached via `?legacy=1`, and the mobile app still logs in and lists patients (the `/api/v1` contract test guards this, but smoke-test the real app).
- [ ] Roll the four flags out to remaining clinics (per `branchId`), one clinic at a time.

**Exit criterion (Phase 1 done):** a receptionist runs the whole day on PRE, PRM is compat-only but reachable, `/api/v1` unchanged, zero lost data or workflow.

---

## Rollback cheat-sheet

| Symptom | Action |
|---|---|
| Today's Actions wrong/stale | `Feature::set('today.projection', false)` + check scheduler; `today:rebuild-projection` |
| Profile/timeline reads look wrong | `Feature::set('identity.reads_relationship', false)` |
| New patients not linking | `Feature::set('identity.link_patient', false)` (safe; existing links stay) |
| Staff want the old PRM board back | `Feature::set('prm.secondary', false)` |
| Total revert to pre-PRE reads | set all four above `false` (leave `activity.single_ledger_reads` as-is) |

Every rollback is a flag write — no migration, no data change, effective immediately.

---

## Post-cutover monitoring (first week)

- Daily: `relationship:sync-journeys` dry-run stays at 0 diverged.
- Daily: `today:rebuild-projection --check` stays "Parity OK".
- Watch `storage/logs/today-actions-projection.log` for scheduler errors.
- Spot-check the unified timeline on a few active relationships vs. what staff did that day.
