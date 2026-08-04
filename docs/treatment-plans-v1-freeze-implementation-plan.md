# Treatment Plans V1 Freeze — Implementation Plan (S1–S6)

**Date:** 2026-08-04
**Source:** `docs/treatment-plans-v1-freeze-audit-2026-08-04.md` (CEO Directive #005)
**Governing law:** CEO Directive #004 — every PR below answers "does this move V1 to production?" with yes.
**Mandate:** fix production risk only. No new features. No architectural redesign. No cosmetic refactoring.

## CEO decisions locked (2026-08-04)

| # | Decision | Chosen | Impact on plan |
|---|---|---|---|
| D1 | Visit "mark treatment complete" semantics | **Keep it, but guard it** — checkbox still completes the plan, routed through one audited door; requires accepted plan; records completion reason; idempotent | S2 builds `PlanCompletionService` with two callers, no workflow change for Tulip staff |
| D2 | Invoice cancellation | **Full rollback** — teeth reset to pending, progress recomputed, plan un-completed; guards use `withTrashed` so only *live* invoices block revert/delete | S1 includes `BillingController@update` path; plan becomes correctly re-billable |
| D3 | `presented_at` stamping | **Stamp at send time** — staff send action stamps; public GET stops stamping | S5 removes GET stamping, guarantees send-time stamp on both public channels |

---

## Ground rules applied to every PR

1. **Atomic** — one PR = one deployable unit that leaves the system correct whether or not the next PR ever ships.
2. **Independently testable** — each PR names its own tests; the full clinical suite must stay green at every step.
3. **Backward compatible** — no existing request payload, route name, response key, or DB column is removed. Additive only. Mobile app on the current build keeps working through all six PRs.
4. **Production-safe deploy** — migrations are additive (indexes + one FK behaviour change); no data backfill; no `migrate:fresh`, no rollback of existing migrations. Every PR is independently revertible by `git revert` without leaving the DB inconsistent.
5. **Sequence is mandatory.** S1 before S2 (completion logic depends on billing rollback existing), S2 before S3 is convenience only, S6 last.
6. **You run all artisan commands manually.** Each PR lists exactly what to run.

Branch naming: `freeze/tp-s1-billing-truth` … `freeze/tp-s6-regression`. Tag after S6: `treatment-plans-v1-frozen`.

---

# S1 — Billing truth & rollback

**Fixes:** B1.1, B1.2, B1.3, B1.4, B1.5 · **Severity:** CRITICAL · **Est: 1.5–2 days**
**Why first:** the only findings that lose money and permanently break re-billing, and the only ones reachable without any race (B1.2 is serial).

### Changes

**1. New: `app/Services/Billing/PlanBillingRollbackService.php`**
Single reverse door mirroring `TreatmentPlanBillingService`. Methods:
- `rollbackInvoice(Invoice $invoice): void` — inside `DB::transaction`: find all `treatment_plan_item_teeth` where `invoice_item_id` ∈ invoice's line ids; reset `status = STATUS_PENDING`, `invoice_item_id = null`, `invoiced_at = null`; collect affected `treatment_plan_item_id`s; call `TreatmentPlanBillingService::refreshItemProgress()` on each; then re-evaluate plan completion via `PlanCompletionService` (S2) — in S1, inline the inverse: if plan `status === 'completed'` and any item is no longer `PROGRESS_INVOICED`, set back to `'ongoing'` when `accepted_at` is set, else `'pending'`.
- `rollbackInvoiceItems(Collection $items): void` — same, scoped to specific lines (for `BillingController@update`).

**2. `app/Services/Billing/TreatmentPlanBillingService.php`**
- `:76-80` — add `->lockForUpdate()` to the teeth select.
- `:124-130` — replace `$tooth->update([...])` with a conditional update returning affected rows: `TreatmentPlanItemTooth::whereKey($tooth->id)->where('status', STATUS_PENDING)->update([...])`; if affected ≠ 1, throw `ValidationException` (aborts the transaction — nothing partially billed).
- `:28-56 ensureTeeth()` — wrap the whole check-then-create in `DB::transaction` + `lockForUpdate` on the parent item row. Use `firstOrCreate` per tooth so the new unique index is a hard backstop.
- Extract the completion block at `:137-143` into a private method so S2 can replace its body without re-touching this file.

**3. `app/Http/Controllers/BillingController.php`**
- `@destroy:732-767`, `@cancel:771-776`, `@cancelInvoice:1446-1586`, `@destroyWithAuth:1763-1787` — call `PlanBillingRollbackService::rollbackInvoice($invoice)` inside each existing transaction, before the soft-delete/status write.
- `@update:596` — before `$invoice->items()->delete()`, call `rollbackInvoiceItems($invoice->items)`.
- `@billFromPlan:223` — remove the `ensurePlanTeeth()` call from the GET; instead have the view request teeth via the existing POST/ajax path, **or** keep the call but it is now transactional+idempotent (acceptable interim — the unique index makes it safe). *Prefer: keep the call, note as safe; moving it changes the UI contract and is not required for correctness once B1.5 is fixed.*
- `@storeFromPlan:239` — add guard: `abort_if(is_null($plan->accepted_at), 422, 'This plan must be accepted before billing.')` — same message as the API for parity.

**4. `app/Http/Controllers/Api/V1/BillingController.php`**
- `@cancelInvoice:896-949` — same rollback call.

**5. `app/Http/Controllers/Api/V1/TreatmentPlanController.php`**
- `@bill:331-353` — add the same `accepted_at` guard that `billableTeeth:301-303` already has.

**6. Guards using `invoices()->exists()` (D2 — only live invoices block)**
- `app/Http/Controllers/TreatmentPlanController.php:491` (`destroy`) — leave as `exists()` semantics but make explicit: `$plan->invoices()->exists()` already excludes soft-deleted. With rollback in place this is now *correct* — a fully-cancelled plan is genuinely unbilled. Add an inline comment stating the dependency on rollback.
- `app/Services/TreatmentPlan/TreatmentPlanAcceptanceService.php:123` — same; comment updated.
*(No code change needed here beyond comments once D2 rollback exists — recorded so the reviewer knows it was considered, not missed.)*

**7. `routes/web.php:542-543`** — change `module:finance` group membership for these two routes to `module:finance,edit` (add per-route middleware; do not move them out of the group).

### Migration (additive only)
`database/migrations/2026_08_04_100000_add_billing_integrity_indexes.php`
- unique `treatment_plan_item_teeth (treatment_plan_item_id, tooth_number)` — guarded by a duplicate pre-check; **if duplicates exist in prod, the migration must fail loudly rather than dedupe silently.** Ship a read-only checker first (below).
- unique `treatment_plan_item_teeth (invoice_item_id)` where not null — MySQL treats multiple NULLs as distinct, so a plain unique index is correct.

### Pre-deploy check (read-only, run on prod before migrating)
`app/Console/Commands/BillingIntegrityCheck.php` → `php artisan billing:integrity-check`
Reports: duplicate `(item, tooth)` rows; teeth `invoiced` whose invoice_item is missing or whose invoice is cancelled/trashed; items whose `invoiced_units` ≠ actual invoiced teeth count; plans `completed` with non-invoiced items. **Report only, no writes.** This both gates the migration and quantifies existing damage at Tulip.

### Tests (new: `tests/Feature/Billing/PlanBillingRollbackTest.php`)
- cancel invoice → teeth back to `pending`, `billing_progress` recomputed, plan un-completed, re-billing the same teeth succeeds
- delete invoice (soft) → same
- `BillingController@update` replacing lines → old teeth released, new ones invoiced, no stranded rows
- billing an unaccepted plan → 422 on web and API
- concurrent-selection simulation: second call with the same tooth ids after the first commits → `ValidationException`, no second invoice
- `ensureTeeth` called twice → exactly N tooth rows (unique index holds)

### Deploy & rollback
Run checker → fix any reported duplicates manually → `php artisan migrate` → deploy. Revert = `git revert` + drop the two indexes; no data is transformed, so revert is clean.

**Artisan (you run):** `php artisan billing:integrity-check` → `php artisan migrate` → `php artisan test --filter=PlanBillingRollback` → `php artisan optimize:clear`

---

# S2 — Completion ownership & acceptance-door integrity

**Fixes:** B2.1, B2.2, B3.1, B3.2, B3.3, B3.4 · **Severity:** CRITICAL · **Est: 1.5 days**
**Depends on S1** (completion inverse lives in the rollback path).

### Changes

**1. New: `app/Services/TreatmentPlan/PlanCompletionService.php`** (D1)
The single completion door. `complete(TreatmentPlan $plan, string $reason, ?User $actor): TreatmentPlan` where `$reason ∈ ['billing_fully_invoiced', 'clinical_work_complete']`.
- Idempotent: returns early if already `completed`.
- Guard: plan must have `accepted_at` (a never-accepted plan cannot complete).
- Writes via `$plan->update()` — **so `Auditable` fires** (fixes the event-bypassing mass update).
- Logs ActivityEngine `treatment_plan.completed` with the reason in metadata.
- `uncomplete(TreatmentPlan $plan, string $reason)` — used by S1 rollback and by revert.

**2. `app/Services/TreatmentVisitService.php`**
- `:359` — replace `TreatmentPlan::where('id',…)->update(['status'=>'completed'])` with `PlanCompletionService::complete($plan, 'clinical_work_complete', $actor)`.
- `:361-368` — scope the recall dedup to the plan: add `->where('treatment_plan_id', $treatmentPlanId)` if the column exists on tasks, else match on the plan id embedded in the title; **do not** loosen the existing behaviour beyond scoping.
- `:378` — `Auth::user()?->branch_id ?? $patient->branch_id` (null-safe for queue/console).

**3. `app/Services/Billing/TreatmentPlanBillingService.php`**
- The completion block extracted in S1 now calls `PlanCompletionService::complete($plan, 'billing_fully_invoiced')`.

**4. `app/Services/TreatmentPlan/TreatmentPlanAcceptanceService.php`**
- `accept():48` — call `guardDecidable($plan)` (parity with reject/defer/acceptPartially); **early-return the plan unchanged if `accepted_at` is already set** (idempotent — a double-click is success, not a duplicate ledger row). Move the plan read inside the transaction with `lockForUpdate`.
- `revert():113-165` —
  - move both guards (`:119`, `:123`) **inside** the transaction, after a `lockForUpdate` re-read;
  - append a `PlanDecision` row recording the reversal *or* (if the enum must not change — it must not, D-rule: no schema redesign) emit `ActivityEngine 'treatment_plan.reverted'` with the reason, and make the ledger-head reader aware. **Chosen: ActivityEngine event + `TreatmentOpportunity` sync.** The `plan_decisions.decision` enum stays untouched (backward compatible).
  - call `TreatmentPlanOpportunitySync::syncStage($plan, 'quoted', …)` so the card re-opens and staff chase it again;
  - status-aware: refuse revert when `status === 'completed'` with a clear message (prevents silently discarding a completion);
  - fix the false comment at `:150`.
- `defer():291-294` — route through `syncStage` (or `firstOrCreate` then set `follow_up_date`); if no opportunity could be created, do not claim suppression in the response message.

**5. `app/Services/TreatmentPlan/TreatmentPlanPresentationService.php`**
- `:67-71` — replace the stale in-memory check with a conditional write: `TreatmentPlan::whereKey($plan->id)->whereNull('presented_at')->update(['presented_at' => now()])`; `$isFirst = affected === 1`; then `$plan->refresh()`.

**6. `app/Models/TreatmentPlan.php`**
- Add a `booted()` `updating` guard: reject any change to `presented_at` when the original value is non-null (throw `RuntimeException`, matching the `PlanDecision` pattern already in the codebase). `presented_at` stays in `$fillable` for backward compatibility — the guard is the enforcement.

### Tests
Extend `tests/Feature/Clinical/PlanPresentationTest.php` and `PlanDecisionLedgerTest.php`; new `tests/Feature/Clinical/PlanCompletionOwnershipTest.php`:
- double accept → one ledger row, `accepted_at` unchanged, 200 both times
- accept a cancelled/completed plan → rejected
- revert on a completed plan → rejected; revert on accepted plan → opportunity returns to `quoted`, ActivityEngine event present
- both completion writers → identical end state, audit_logs row present for each, second call idempotent
- completion of a never-accepted plan → rejected
- concurrent markPresented → `presented_at` written once, one `first_presentation` log
- direct `$plan->update(['presented_at' => …])` on a presented plan → throws

### Deploy & rollback
No migration. Pure service-layer. Revert = `git revert`; the only durable artefacts are new ActivityEngine rows (harmless).

**Artisan:** `php artisan test --filter="PlanCompletionOwnership|PlanPresentation|PlanDecision"` → `php artisan optimize:clear`

---

# S3 — Authorization

**Fixes:** B4.1, B4.2, B4.3, B4.4 · **Severity:** CRITICAL · **Est: 0.5–1 day**

### Changes

**1. `routes/api.php`**
- `:198` — add `->middleware('api.role:module:patients,view')` to `GET /treatment-plans/{plan}`.
- `:220` — add `->middleware('api.role:module:patients,view')` to `billable-teeth`.

**2. `app/Http/Middleware/EnsureApiRole.php:43-46`** — delete the `isAdminRole()` short-circuit. `User::canAccess()` already grants blanket access to the real ADMIN role slug, so genuine admins are unaffected; only the retired legacy `users.role` string bypass dies.
> ⚠️ **Deployment note:** if any production user has `users.role='admin'` but a restricted assigned role, they lose API access they should never have had. Run the checker below before deploying.

**3. New: `app/Console/Commands/ApiAdminBypassCheck.php`** → `php artisan access:api-bypass-check`
Read-only. Lists users where `users.role === 'admin'` but `roleModel->slug !== Role::ADMIN` — i.e. exactly who is currently relying on the bypass. Run on prod first; if the list is non-empty, fix those users' assigned roles *before* shipping S3.

**4. `app/Http/Controllers/TreatmentPlanController.php`** — add one private helper and call it at the top of every plan-scoped method:
```
private function assertPlanAccessible(TreatmentPlan $plan): void
```
Mirrors the existing API logic at `Api/V1/TreatmentPlanController::findPlan():399-401` (compare `$plan->patient->branch_id` to `auth()->user()->branch_id`, admin exempt, null branch = pass — matching `BranchScope`'s current documented behaviour so nothing breaks for single-branch Tulip).
Applied in: `printView`, `getItems`, `consentPrint`, `update`, `accept`, `markPresented`, `reject`, `defer`, `partialAccept`, `revert`, `destroy`. For `destroyItem`, resolve `$item->treatmentPlan` first, then assert.
- `printView:31-45` — additionally require all requested ids to belong to **one** patient; keep the 30-id cap.

> This is deliberately *not* the clinic_id multi-tenancy project (still parked). It closes the web/API asymmetry using logic that already exists and ships today.

### Tests
New `tests/Feature/Access/TreatmentPlanAuthorizationTest.php`:
- token without `patients.view` → 403 on `GET /v1/treatment-plans/{id}` and `billable-teeth`
- legacy-admin user (users.role='admin', restricted assigned role) → denied on API (post-fix)
- user from branch B → 403/404 on branch A's plan across all 11 web methods
- `printView` with mixed-patient ids → rejected
- genuine ADMIN slug → unaffected everywhere (regression guard)

### Deploy & rollback
No migration. Revert = `git revert`. **Run `access:api-bypass-check` on prod first.**

**Artisan:** `php artisan access:api-bypass-check` → fix any users → `php artisan test --filter=TreatmentPlanAuthorization` → `php artisan route:clear && php artisan optimize:clear`

---

# S4 — Edit-path input integrity

**Fixes:** B5.1, B5.2, B5.3 · **Severity:** CRITICAL · **Est: 0.5 day**

### Changes

**1. `app/Http/Controllers/TreatmentPlanController.php:702-743` (`syncItems`)**
Adopt the API's already-safe shape (`Api/V1:374-377`) rather than inventing anything:
- `disc_pct` — accept only if explicitly validated; add `items.*.disc_pct => ['nullable','numeric','min:0','max:100']` to both `store` and `update` rules. (The web UI does send discounts — this is why we validate rather than hardcode 0 as the API does.)
- `gst_pct` — `['nullable','numeric','min:0','max:100']`.
- `option_rank` — `['nullable','in:best,acceptable,alternative']`.
- **`status` — remove from the write payload entirely.** Item lifecycle is not client-controlled. Existing rows keep their values; nothing is migrated. This also un-breaks the deletion-protection set at `:281`, which was reading a client-supplied value.
- `aocp_applied`, `consent_required` — `['nullable','boolean']` (already partly present).
- `variants` — `['nullable','array']`.
- Add `items.*.unit_price => max:9999999`, `items.*.units => max:999`, `doctor_notes`/`items.*.notes => max:2000` on **both** controllers (B-list M3, trivial and belongs here).

**2. Cross-plan hijack — one line each side**
- Web `:711-713` and API `:360-362` — `$plan->items()->find($row['id'])` instead of `TreatmentPlanItem::find(...)`.

**3. `app/Http/Controllers/TreatmentPlanController.php:96-121` (`consentPrint`)**
Make the GET idempotent: if a consent snapshot already exists for this plan + the same selected keys, return it instead of persisting a new one. No route change, no UI change, no new endpoint.

### Backward-compatibility note
Removing item `status` from the write payload is the only behaviour change. Verified: the web UI does not send it deliberately (it round-trips whatever `getItems` returned), and the API already hardcodes `'pending'`. Mobile is unaffected. No stored data changes.

### Tests
Extend `tests/Feature/Clinical/` with `PlanItemWriteGuardTest.php`:
- POST `items[0][status]=completed` → item status unchanged, request still succeeds (field ignored, not a 422 — preserves backward compatibility)
- POST `disc_pct=150` → 422
- POST another plan's item id → item is untouched, no re-parenting
- `consentPrint` called twice with same keys → one consent row
- oversized price/units/notes → 422

**Artisan:** `php artisan test --filter="PlanItemWriteGuard|TreatmentPlan"` → `php artisan optimize:clear`

---

# S5 — Public patient surface

**Fixes:** B6.1, B6.2 (per D3), B6.3 · **Severity:** HIGH · **Est: 0.5–1 day**

### Changes

**1. `routes/web.php:808-828`** — add `->middleware('throttle:10,1')` to both public groups (`presentations.public.*`, `case.public.*`). Generous enough for real patients, fatal to replay.

**2. `app/Http/Controllers/PublicPresentationController.php`**
- `accept:112-147` — wrap in `DB::transaction`; add closed-state guard mirroring the one `requestCallback:238` already has; add null-plan check (`:120`) mirroring `PublicCaseController:159`.
- `decline:149-214` — same closed-state guard; route the opportunity write through `TreatmentPlanOpportunitySync` instead of its own find-or-create at `:179-201`; same for `requestCallback:255-279`.
- `show:48-78` — **remove the `markPresented` call** (D3). Keep view-count/status recording.

**3. `app/Http/Controllers/PublicCaseController.php`**
- `accept:148-180` — wrap in `DB::transaction`; add closed-state guard mirroring `select:120-122`; make the consent snapshot at `:168` idempotent (one snapshot per journey acceptance).
- `decline:186-196` — route through `TreatmentPlanAcceptanceService::reject($plan, via:'case_acceptance')` so the patient's rejection lands in the decision ledger (B6.3).
- `show:49-79` — remove the `markPresented` call (D3).

**4. Send-time stamping guaranteed (D3)**
- `CaseJourneyController::send:215-221` already calls `markPresented` — **remove the silent `report($e)` swallow** so a failed stamp surfaces; it is now the only stamping path for this channel.
- `PresentationLinkService` / wherever a presentation link is issued — add the equivalent `markPresented` call at send. (S2's conditional write makes it idempotent, so a plan already presented via another channel is unaffected.)

### Backward-compatibility note
No route names, tokens, or URLs change. Existing live patient links keep working. The only visible difference: opening a link no longer marks the plan presented — it was already marked when staff sent it.

### Tests
New `tests/Feature/Clinical/PublicPlanSurfaceTest.php`:
- replayed public accept → one ledger row, one consent snapshot, second call returns "already closed"
- public decline → `PlanDecision` REJECTED row exists
- GET the public link → `presented_at` unchanged
- send a case journey / presentation → `presented_at` stamped exactly once
- presentation with null/soft-deleted plan → graceful 404, not 500
- 11th request in a minute → 429

**Artisan:** `php artisan test --filter="PublicPlanSurface|PlanPresentation"` → `php artisan route:clear && php artisan optimize:clear`

---

# S6 — Regression, parity test, freeze

**Est: 0.5–1 day** · No production code changes except the parity test's findings (if any, they get folded back into the relevant PR — do not fix them here).

### Contents
1. **New `tests/Feature/Api/ApiTreatmentPlanParityTest.php`** — the module's missing parity test (Consultations has `ApiConsultationParityTest`). Asserts web and API produce the same lifecycle outcome for: store, update, accept, markPresented, reject, defer, partialAccept, revert. Documents (does not fix) known intentional divergences: response envelope `plan` vs `data`, mobile-absent `destroy`/`destroyItem`/`consent`/`aiSuggest`, API-dropped `doctor_id`/`plan_date`. Those become the post-freeze M1 slice.
2. **Full suite** — `php artisan test` (expect 439+ green; only the pre-existing finance `/wallet-campaigns` 500 tolerated, per the 08-03 note).
3. **Route crawler** — `php artisan app:crawl-routes`, compare against the known-clean baseline.
4. **Smoke suite** — `php artisan dentfluence:smoke`.
5. **Integrity checkers re-run on prod** — `billing:integrity-check` should now report zero new stranding; `progress:invariant-check` and `patients:invariant-check` green.
6. **Tulip real-use validation (CEO gate)** — one full real cycle on production: consultation → plan → present → accept → visit with work recorded → partial bill → cancel that invoice → re-bill → complete. Confirms D2 rollback and D1 completion behave as the clinic expects.
7. **Freeze** — tag `treatment-plans-v1-frozen`, update the Master Register (Treatment Plans → Frozen, 4 dependents notified per the Freeze-Amendment rule), move to CEO Order #4 (Treatment Visits) with H3 (visit edit destroying `work_outcome`, visit delete regressing progress) as its slice 1.

**Artisan:** `php artisan test` → `php artisan app:crawl-routes` → `php artisan dentfluence:smoke` → `php artisan billing:integrity-check` → `php artisan progress:invariant-check`

---

## Summary

| PR | Fixes | Migration | Risk | Est |
|---|---|---|---|---|
| S1 Billing truth & rollback | B1.1–B1.5 | 2 unique indexes (additive, pre-checked) | Medium — touches 6 cancel paths | 1.5–2 d |
| S2 Completion & acceptance | B2.1–B2.2, B3.1–B3.4 | none | Medium — core service behaviour | 1.5 d |
| S3 Authorization | B4.1–B4.4 | none | Low-Medium — run bypass checker first | 0.5–1 d |
| S4 Input integrity | B5.1–B5.3 | none | Low | 0.5 d |
| S5 Public surface | B6.1–B6.3 | none | Low | 0.5–1 d |
| S6 Regression & freeze | — | none | None | 0.5–1 d |
| **Total** | **all 6 blocker groups** | **1 additive migration** | | **5–7 d** |

**Explicitly out of scope** (post-freeze backlog, unchanged from the audit): H1 ledger FK `restrictOnDelete`, H2 plan-delete orphan cascade, H3 visit-module fact destruction, H4 six wrong-number report readers + three case-acceptance definitions, H5 opportunity unique index, M1 mobile parity, M2 `destroyItem` audit, M4 DerivedProgress N+1 and plan-tab payload, L1 dead code (`cancelled` state, `computed_total`, `rows`, stale docblocks).

**Deployment cadence:** deploy each PR to prod independently (`bash deploy.sh` on the VPS, per `reference_vps_deploy_gotchas`) and let it sit one working day at Tulip before the next. Six small deploys beat one large one; each is independently revertible.
