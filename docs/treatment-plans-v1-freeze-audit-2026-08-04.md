# Treatment Plans — V1 Production Freeze Audit (CEO Directive #005)

**Date:** 2026-08-04
**Auditor:** Principal Laravel Architect (AI), 4 parallel audit streams + direct verification of every Critical/High claim
**Scope:** Audit only. No code was modified. Covers all 15 directive areas.
**Baseline:** Module live in production at Tulip Dental, daily real-patient use.

---

## VERDICT (read this first)

| Question | Answer |
|---|---|
| **Production Ready Score** | **74 / 100** |
| **Safe to freeze today?** | **NO — conditional freeze after 6 blocker groups are fixed** |
| **Is the architecture sound?** | **YES.** Decision ledger, single acceptance door, immutable-first-presentation, DerivedProgressService — the spine is correct and the best-tested in the repo. Nothing needs redesign. |
| **What's wrong?** | Integrity gaps *around* the spine: billing has no rollback path, completion has two uncoordinated writers, the acceptance door has no idempotency/guard on its busiest verb, the API leaks PHI on one route, and the web edit path accepts unvalidated financial fields. |
| **Estimated effort to freeze** | **5–7 focused engineering days** (6 slices, each independently testable, no migrations that touch existing data except adding indexes/constraints) |

Why these bugs haven't bitten at Tulip: one clinic, trusted staff, near-zero concurrency, no hostile API clients, invoices rarely cancelled. Every one of them becomes probable at SaaS scale. **This is exactly the right moment to fix them — before freeze, not after clinic #2.**

---

## Per-area scorecard

| # | Area | Verdict |
|---|---|---|
| 1 | Domain integrity | 🟡 Ownership boundaries right; enforcement app-level only; one FK cascade endangers ledger history |
| 2 | Workflow validation | 🟡 Happy path solid; several invalid transitions physically possible (accept cancelled/completed, bill unaccepted, revert completed) |
| 3 | Status ownership | 🔴 `status='completed'` has TWO writers with incompatible criteria and NO un-writer; `'cancelled'` has ZERO writers (dead state) |
| 4 | Decision ledger | 🟡 Append-only guards work (model-level); revert writes no ledger row; DB `CASCADE` can silently erase history |
| 5 | Presentation | 🟡 Single writer, write-once condition correct — but enforced by one `if` on a stale in-memory model; still in `$fillable`; stampable by link-preview bots via public GET |
| 6 | Billing integration | 🔴 Billing does NOT own clinical truth in code intent, but: no rollback on invoice cancel, double-billing race, unaccepted plans billable, `finance.view` can create invoices |
| 7 | Visit integration | 🔴 Visit checkbox completes plans with zero criteria via event-bypassing mass update; visit edit hard-deletes `work_outcome` facts; visit delete regresses progress silently |
| 8 | Consultation integration | 🟢 Clean. Ownership-checked redirect, deterministic prefill, no writes, deterministic aiSuggest |
| 9 | PRE integration | 🟡 Stage map correct for present/accept/reject; revert & delete never sync (orphan/committed-forever cards); defer blind-updates; CONVERTED never written by treatment flow |
| 10 | API parity | 🟡 Verbs mirrored well (validation + lockdown identical); response shapes diverge; mobile can't delete/consent; API drops doctor_id/plan_date |
| 11 | Authorization | 🔴 One ungated API PHI route; legacy admin bypass live in `EnsureApiRole`; no branch/ownership checks on any web plan route; invoice creation behind a *view* gate |
| 12 | Transactions | 🟡 Core services transactional (good); public controllers, `ensureTeeth`, `destroy`, `destroyItem` are not; several guards sit outside their transaction |
| 13 | Concurrency | 🔴 Zero locking in the module; no idempotency on any verb; unique-index backstops missing on 3 of 4 invariants |
| 14 | Reports & analytics | 🔴 Six readers trust the dual-writer status column or the inert item-status column; three mutually inconsistent "case acceptance" definitions live simultaneously |
| 15 | Production hardening | 🟡 Events/queue discipline is exemplary (`DB::afterCommit` throughout); N+1 on derived progress (4 queries × N plans); unbounded inline payload in plan tab; no throttle on public routes |

---

## FREEZE BLOCKERS — 6 groups

Every finding below includes: Severity · Location · Problem · Production impact · Fix · Blocks freeze.

### B1 — Billing truth & rollback (CRITICAL)

**B1.1 · CRITICAL · Double-billing race.**
`app/Services/Billing/TreatmentPlanBillingService.php:76-80` (`createInvoiceFromSelection`)
Teeth are re-validated inside the transaction but with a **non-locking read**; the tooth flip at `:125-129` is by primary key with **no status predicate**. Two concurrent POSTs (double-click, web+mobile) both pass validation → **two invoices for the same teeth**; Tx1's invoice line is orphaned but fully payable. Patient charged twice.
**Fix:** `->lockForUpdate()` on the `:76` read; make `:125` a conditional `UPDATE … WHERE status='pending'` and assert affected rows; add unique index on `treatment_plan_item_teeth.invoice_item_id`. **BLOCKS FREEZE.**

**B1.2 · CRITICAL · No billing rollback path — cancelled invoices strand clinical/billing state forever.**
`BillingController.php:732-767, :771-776, :1446-1586, :1763-1787`; `Api/V1/BillingController.php:896-949`; `BillingController@update:596`
No code anywhere resets `treatment_plan_item_teeth.status` from `invoiced`, decrements `invoiced_units`, recomputes `billing_progress`, or un-completes `treatment_plans.status` when an invoice is cancelled/deleted/edited. `refreshItemProgress` has exactly one caller (invoice creation).
**Impact:** cancel an invoice → those teeth are **permanently unbillable through the UI** (creation only selects `pending` teeth); the plan stays `completed` on a voided invoice; `BillingController@update:596` hard-deletes invoice_items leaving teeth claiming `invoiced` with no invoice at all. This is reachable **serially** — no race needed. Real revenue loss per occurrence.
**Fix:** on invoice cancel/delete/item-delete, reset linked teeth to `pending`, null `invoice_item_id`, re-run `refreshItemProgress`, and re-evaluate plan completion. One service method, called from all five cancel paths. **BLOCKS FREEZE.**

**B1.3 · HIGH · Soft-deleted invoices defeat the revert/destroy guards.**
`TreatmentPlanController.php:491`, `TreatmentPlanAcceptanceService.php:123`
Both guards use `$plan->invoices()->exists()`; `Invoice` soft-deletes, so once every invoice is cancelled, a previously-billed plan can be reverted to `pending` or deleted outright while its teeth remain `invoiced`.
**Fix:** `invoices()->withTrashed()->exists()` (or explicit policy decision that cancelled invoices unlock the plan — but then B1.2 must run first). **BLOCKS FREEZE** (rides with B1.2).

**B1.4 · HIGH · Unaccepted/cancelled plans can be invoiced; invoice creation behind a view-only gate.**
`BillingController@billFromPlan:223` / `@storeFromPlan:239` (route `routes/web.php:542-543`, gate `module:finance` **view**); `Api/V1/TreatmentPlanController@bill:331` (no accepted_at check — only the `billable-teeth` GET checks, `:301-303`)
**Fix:** add the `accepted_at` guard to both POST paths; change route gate to `module:finance,edit`. **BLOCKS FREEZE.**

**B1.5 · MEDIUM · `ensureTeeth` — unguarded check-then-create on GET requests.**
`TreatmentPlanBillingService.php:28-56`; invoked from `BillingController.php:228` (GET) and `Api/V1/TreatmentPlanController.php:305` (GET)
No transaction, no lock, no unique on `(treatment_plan_item_id, tooth_number)`. Concurrent opens duplicate tooth rows → inflated billable teeth, `refreshItemProgress` denominator broken, plan never reaches completed.
**Fix:** wrap in transaction + unique index `(treatment_plan_item_id, tooth_number)`; ideally move creation to the POST. **BLOCKS FREEZE** (cheap, rides with B1).

### B2 — Completion ownership (CRITICAL — the CEO-flagged blocker, confirmed)

**B2.1 · CRITICAL · Two uncoordinated writers of `treatment_plans.status='completed'`.**
Writer 1: `TreatmentPlanBillingService.php:142` — all items fully invoiced (money criterion).
Writer 2: `TreatmentVisitService.php:359` — `mark_treatment_complete` checkbox, **zero criteria**, via `TreatmentPlan::where(...)->update()` which **bypasses model events → no audit_logs row**.
Neither knows about the other; neither has an inverse; `completed` can be set while clinical work is outstanding or while nothing is billed. Six reporting surfaces consume this column (see B7 list) — the number means "either fully billed, or somebody ticked a box, and possibly neither is still true".
**Fix (minimal, no redesign):** route both writers through one `markCompleted(reason)` door on the plan side that (a) uses `$plan->update()` so audit fires, (b) records *which* criterion completed it, (c) is idempotent. Visit-checkbox path should require the plan to be accepted. Decide explicitly (CEO call): does the checkbox complete the *plan* or only record clinical work? Frozen contract says billing progress ≠ clinical progress — today's checkbox conflates them.
**BLOCKS FREEZE.**

**B2.2 · HIGH · `revert()` can un-complete a completed plan silently; recall/branch bugs in the visit writer.**
`TreatmentPlanAcceptanceService.php:113-133` — revert never inspects `status`; a checkbox-completed plan (no invoices) reverts to `pending`, discarding completion with no ledger row. `TreatmentVisitService.php:361-368` — recall dedup matches `%recall%` on ANY patient task, so plan A's recall suppresses plan B's; `:378 Auth::user()->branch_id` fatals in queue/console context.
**Fix:** status-aware revert guard; scope recall dedup to the plan; null-safe actor. **BLOCKS FREEZE** (rides with B2.1/B3).

### B3 — Acceptance door integrity (CRITICAL)

**B3.1 · CRITICAL · `accept()` has no guard and no idempotency — on staff, mobile AND public channels.**
`TreatmentPlanAcceptanceService.php:48-100` (verified directly)
Unlike `reject:239`/`defer:278`/`acceptPartially:179`, `accept()` never calls `guardDecidable()` and never checks `accepted_at`. Consequences: a cancelled/rejected/**completed** plan can be accepted (completed → flips back to `ongoing`); every re-POST appends a duplicate `PlanDecision::ACCEPTED` row and **overwrites the original `accepted_at`** — destroying the legally-relevant acceptance timestamp. Reachable unauthenticated via `PublicPresentationController::accept:112` and `PublicCaseController::accept:148` (no status guard, no throttle — see B6).
**Fix:** call `guardDecidable()`; early-return (idempotent success) when already accepted; inside the transaction re-read the plan with `lockForUpdate`. **BLOCKS FREEZE.**

**B3.2 · HIGH · `revert()` writes no ledger row and its own audit comment is false.**
`TreatmentPlanAcceptanceService.php:113-165` (verified directly)
No `PlanDecision` row, no ActivityEngine event; the comment at `:150` claims "The Activity ledger above is the audit" — **there is no ActivityEngine call in the method**. StaffActivityLog only fires with a web session (`:153`), so the mandatory reason is discarded in any non-session context. After revert: ledger head still says ACCEPTED, mirror says not-accepted, `is_decision_pending` returns false → the plan is in a gap state; `DerivedProgressService` still scores it as accepted; the PRE opportunity stays COMMITTED (closed) forever → **plan back in play, nobody ever chases it**.
**Fix:** append a ledger `reverted`/decision row (or explicit ActivityEngine `treatment_plan.reverted` event) inside the transaction; sync opportunity back to `quoted`; move both guards (`:119`, `:123`) inside the transaction with a locked re-read. **BLOCKS FREEZE.**

**B3.3 · MEDIUM · `defer()` blind-updates a possibly-nonexistent opportunity.**
`TreatmentPlanAcceptanceService.php:291-294` — mass update, no affected-rows check; deferring a never-presented plan is a silent no-op while the UI tells the user "Nobody will chase until {date}".
**Fix:** route through `syncStage` or `firstOrCreate` then set `follow_up_date`. **BLOCKS FREEZE** (small, rides with B3).

**B3.4 · MEDIUM · `markPresented` reads `presented_at` from the stale bound model.**
`TreatmentPlanPresentationService.php:67-71` — two concurrent calls both see null → both "first presentation". Also `presented_at` remains in `$fillable` (`TreatmentPlan.php:31`) with no model-level `updating` guard — immutability rests on one `if` in one service.
**Fix:** conditional `whereNull('presented_at')->update(...)` using affected-rows as `$isFirst`; add an `updating` guard on the model rejecting changes to a non-null `presented_at`. **BLOCKS FREEZE** (cheap; this is the module's flagship invariant).

### B4 — API authorization (CRITICAL)

**B4.1 · CRITICAL · `GET /v1/treatment-plans/{plan}` is ungated PHI.**
`routes/api.php:198` (verified — no `api.role` middleware), `Api/V1/TreatmentPlanController::show:42-48` → `payload(withClinic:true)` returns patient name, phone, age, gender, full address plus all priced items. Any Sanctum token — including roles with `patients.view=false`, which the Slice 1.4 gate at `:103` explicitly blocks — reads it. Defeats Phase-1 access control.
**Fix:** add `api.role:module:patients,view`. One line. **BLOCKS FREEZE.**

**B4.2 · CRITICAL · Retired legacy admin bypass still live on every API route.**
`app/Http/Middleware/EnsureApiRole.php:44-46` (verified) — `if ($user->isAdminRole()) return $next($request);` honours the **legacy `users.role` string column** that `User::canAccess()` retired in Phase 1. A user with `users.role='admin'` but a restricted assigned role: denied on web, allowed on **every** API endpoint. Web and API gates are not equivalent.
**Fix:** delete the short-circuit (canAccess already grants the real ADMIN slug blanket access). **BLOCKS FREEZE.**

**B4.3 · HIGH · No object-level ownership on any web plan route (IDOR).**
`TreatmentPlanController` — `printView:31` accepts up to 30 arbitrary `ids[]` (bulk cross-patient harvesting incl. patient identity); `getItems:125`, `consentPrint:96`, and every verb take a route-bound plan with no patient/branch check; `destroyItem:505` binds an item with no check at all. The API does check (`findPlan():399-401` compares branch) — web does not.
**Context:** clinic_id tenancy is a known platform-wide gap (encryption/access-hardening parked). But the *asymmetry* is fixable now and `getItems`/`printView` are trivially harvestable.
**Fix:** small `assertPlanAccessible($plan)` helper (patient branch vs user branch, admin exempt) applied across the controller; cap and scope `printView` ids to one patient. **BLOCKS FREEZE** (scoped to the helper — full tenancy remains the platform project).

**B4.4 · MEDIUM · `GET /v1/treatment-plans/{plan}/billable-teeth` ungated AND state-writing.**
`routes/api.php:220`, controller `:305` (`ensurePlanTeeth` writes rows on GET).
**Fix:** gate with `module:finance,view` (or patients,view) and stop writing on GET (B1.5). **BLOCKS FREEZE** (rides with B1.5).

### B5 — Edit-path input integrity (CRITICAL)

**B5.1 · CRITICAL · Web `syncItems` writes six unvalidated client fields — including item `status` and `disc_pct`.**
`TreatmentPlanController.php:710-730` (verified) — validation never declares `items.*.disc_pct/gst_pct/option_rank/status/aocp_applied/variants`, but syncItems reads them raw. Any user with `patients,edit` can POST `disc_pct=100` (free treatment, no audit — `TreatmentPlanItem` is not Auditable) or `status=completed` (which then flips the item into the deletion-protection set at `:281` — an unvalidated field controls a data-protection decision). API side is clean (hardcodes safe defaults, `Api/V1:374-377`) — a parity divergence proving the fix shape.
**Fix:** whitelist exactly like the API does, or validate the six fields with strict rules. **BLOCKS FREEZE.**

**B5.2 · HIGH · Cross-plan item hijack.**
Web `:711-713` / API `:360-362` — `TreatmentPlanItem::find($row['id'])` with no plan scoping, then `treatment_plan_id` overwritten to the current plan. Posting another patient's item id silently re-parents and rewrites it (price, name, status), defeating the protected-items logic (which only guards deletion). API has no `exists` rule at all.
**Fix:** `$plan->items()->find($row['id'])`. One line each side. **BLOCKS FREEZE.**

**B5.3 · MEDIUM · `consentPrint` is a state-writing GET with no ownership check.**
`TreatmentPlanController.php:96-121` — `generateAndPersist()` creates consent rows on every GET.
**Fix:** persist only on explicit POST, or make GET idempotent (reuse latest snapshot); add ownership check (B4.3). **BLOCKS FREEZE** (rides with B4.3).

### B6 — Public patient surface (HIGH)

**B6.1 · HIGH · Public accept/decline replayable; no throttle anywhere on `/present/*` and `/p/*`.**
`routes/web.php:808-828` (no `throttle:`); `PublicPresentationController::accept:112` (no status guard, and `:120` null-plan → TypeError 500 on a patient-facing page); `PublicCaseController::accept:148` (no closed-case guard — `select:120-122` has one, accept doesn't). Each replay: duplicate ledger row, reset `accepted_at`, re-synced opportunity, and on the case path a **duplicate immutable consent snapshot** (`:168`) — a medico-legal record becoming ambiguous. Token entropy itself is fine (48/64 random chars).
**Fix:** `throttle:10,1` on both groups; closed-state guards on accept/decline (mirroring `select`/`requestCallback` which already have them); null-plan check; B3.1 idempotency covers the service layer.
**BLOCKS FREEZE.**

**B6.2 · HIGH · Link-preview bots stamp clinical truth.**
`PublicPresentationController::show:48-78`, `PublicCaseController::show:49-79` — a GET (WhatsApp/Slack unfurler, email scanner, browser prefetch) records the view, mutates status, AND stamps `presented_at` + creates the PRE opportunity.
**Fix:** stamp presentation on first *interactive* signal (POST beacon / explicit button) or at send-time only; at minimum filter known bot user-agents and HEAD requests. Decide policy consciously — today "presented" can mean "a bot fetched the link preview". **BLOCKS FREEZE** (policy + small change).

**B6.3 · MEDIUM · Patient declines on the case path never reach the decision ledger.**
`PublicCaseController::decline:186-196` — journey status + opportunity sync only; no `PlanDecision` row. The patient's rejection exists only as pipeline state — the exact failure mode Slice 2.3 eliminated for staff verbs.
**Fix:** route through `TreatmentPlanAcceptanceService::reject(via:'case_acceptance')`. **BLOCKS FREEZE** (small).

---

## NON-BLOCKING — fix soon after freeze (High → Low)

**H1 · Ledger has no DB-level protection + `plan_decisions` cascade erases history.**
`2026_07_27_140000:26-28` — `cascadeOnDelete` from `treatment_plans` (verified). Model guards (`PlanDecision::booted` throws on update/delete) don't cover raw SQL, `DB::table`, or FK cascades. Today only SoftDeletes stands between the ledger and erasure; two seeders already hard-delete plans via `DB::table`. Fix: change FK to `restrictOnDelete` (plans are soft-deleted anyway, so RESTRICT costs nothing in prod flows). Small migration, zero data touched.

**H2 · Plan soft-delete orphans everything.** `TreatmentPlanController::destroy:487-501` — no observer, no cascade: items, teeth, consents, presentations, journeys stay live; **the PRE opportunity is never closed** and keeps nudging staff about a plan that no longer resolves. Fix: `deleting` hook — close opportunity, soft-delete items.

**H3 · Visit edit destroys clinical facts; visit delete regresses progress silently.**
`TreatmentVisitService::update:216-228` hard-deletes ALL `treatment_visit_items` (no SoftDeletes) and recreates from payload — a PUT without `work_outcome` erases recorded clinical truth, including rows carrying invoice links. `TreatmentVisitController::destroy:47` / API `:129` — bare delete, no recompute, no event; derived progress regresses with no trail while plan status stays `completed`. *Owned by the Visits module (next in CEO order) but it corrupts plan truth — schedule it as Visits slice 1.*

**H4 · Reporting reads the wrong columns (wrong numbers on screen today).**
- `RelationshipScoreEngine:172-185` — raw `status='completed'` incl. soft-deleted plans → relationship score wrong.
- `Communication/HuddleController:77` — "Pending Estimates" counts every rejected/deferred plan in history, forever (reject/defer never change plan status). Number only grows.
- `Relationship/ProfileController:144-148` — reads the inert `treatment_plan_items.status` → counts every item ever created.
- `ConsultationController:226-230` — filters on `'in_progress'`,`'approved'` — enum values that don't exist; accepted plans vanish from the consultation sidebar.
- `Communication/OpportunityController:162` — stale duplicate of the convertToLead bug its Relationship sibling fixed 07-06 → "Converted (MTD)" measures lead routing, not treatment delivery, on both dashboards.
- `Api/V1/PatientProfileController:79` — mobile receives raw `status` and no derived progress (contract R-7 broken on mobile).
- Three simultaneous, mutually inconsistent case-acceptance definitions: `KpiReportTool:118` (accepted_at over all drafts — understates ~3x), opportunity `status='completed'` (only convertToLead writes), `treatment_plans.status='completed'` (dual-writer). Pick one (ledger/accepted_at based) post-freeze.

**H5 · Missing unique-index backstops.** `treatment_opportunities.treatment_plan_id` (dup cards under race — `syncStage:51` find-or-create unlocked; public controllers `:179-201`,`:255-279` bypass the sync service with their own find-or-create), `treatment_plan_item_teeth(item,tooth)`, `(invoice_item_id)`. One additive migration.

**M1 · Mobile parity.** API store drops `doctor_id`/`plan_date` (mobile plans have no treating doctor); API `update` hard-resets item `disc_pct/gst_pct/option_rank` to defaults (mobile edit wipes web-set discounts — and can rewrite prices on already-invoiced items); no API destroy/destroyItem/consent; response payload lacks `is_presented`/`decision_pending`/`progress`; web `{plan}` vs API `{data}` envelope. Ship as one API-parity slice + the missing `ApiTreatmentPlanParityTest` (Consultations has one; this module doesn't).

**M2 · `destroyItem` gaps.** `TreatmentPlanController:505-528` — no transaction (item delete + total update), guard weaker than `update()`'s teeth check, `TreatmentPlanItem` not Auditable → wholly unaudited deletion of priced clinical data.

**M3 · Validation bounds.** No `max` on `unit_price`/`units`/`doctor_notes`/`notes` anywhere → decimal overflow at scale.

**M4 · Performance.** `DerivedProgressService` = 4 queries/plan, no batch API; plan tab runs it in a Blade `@php` map → ~48 extra queries for a 12-plan patient (`treatment-plan-tab.blade.php:33`, `treatment-visits-tab.blade.php:148`, every `formatPlan` response). Plan tab inlines full treatment master + all consultations + all plans as JSON (multi-hundred-KB for long-standing patients). Missing composite `(treatment_plan_item_id, work_outcome)` on `treatment_visit_items`. Core indexes otherwise present.

**L1 · Dead code/state.** `status='cancelled'`: zero writers, three guards that can never fire — either build a cancel verb (V1.1) or document as reserved. `TreatmentPlanItem::PROGRESS_COMPLETED` and `ToothStatus COMPLETED`: declared, never written, but read in guards. `computed_total` accessor: zero readers. `rows` legacy JSON: zero app readers/writers, still fillable. `overall_disc_pct` hardcoded to 0. Stale docblocks: `TreatmentPlanOpportunitySync:21-25` (accepted→Converted — code correctly says COMMITTED), `PublicCaseController:158`, `TreatmentPlan.php:148` (claims visits lack plan FK — false).

**Positive findings worth freezing AS-IS:** consultation integration (clean, deterministic, ownership-checked); event/queue discipline (`ActivityEngine` uses `DB::afterCommit` everywhere — no listener can see uncommitted state); invoice number generation (proper `lockForUpdate` + unique index); status lockdown on both update endpoints (Slice 2.3e verified intact — `'status'=>['prohibited']`, controllers whitelist fields, mass-assignment of lifecycle fields impossible through update); store/update item sync fully transactional; validation parity on decision verbs exact.

---

## Recommended freeze plan (slices, CEO Order #3 continuation)

| Slice | Contents | Est. |
|---|---|---|
| S1 | B1 billing truth: lock + conditional flip, rollback service on all 5 cancel paths, withTrashed guards, accepted_at gate on both bill POSTs, finance,edit gate, ensureTeeth txn + unique indexes | 1.5–2 d |
| S2 | B2+B3 acceptance/completion: single completion door, guardDecidable+idempotent accept, revert ledger row + opportunity sync + in-txn guards, defer via syncStage, presented_at conditional write + model guard | 1.5 d |
| S3 | B4 authz: gate api show + billable-teeth, delete EnsureApiRole bypass, web `assertPlanAccessible` helper, printView scoping | 0.5–1 d |
| S4 | B5 input integrity: whitelist web syncItems, scope item find(), consentPrint idempotent | 0.5 d |
| S5 | B6 public surface: throttle, closed-state guards, null-plan check, decline→ledger, presented-at stamping policy | 0.5–1 d |
| S6 | Regression: full clinical suite + new concurrency/idempotency tests + `ApiTreatmentPlanParityTest`; crawler; Tulip real-use validation | 0.5–1 d |

**Total: 5–7 days.** No schema redesign; migrations are additive (indexes, one FK behavior change). After S6 green + Tulip validation → **FREEZE**. H1–H5/M-items become the post-freeze hardening backlog (H3 lands as Treatment Visits slice 1, next module in CEO order).

---

*All Critical/High findings in this report were verified directly against source (not just sub-agent claims): TreatmentPlanBillingService:76-146, TreatmentPlanAcceptanceService:48-165, TreatmentVisitService:350-390, EnsureApiRole:30-59, routes/api.php:190-224, TreatmentPlanController:700-744, plan_decisions migration FK.*
