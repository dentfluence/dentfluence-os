# Phase 1 — Execution Backlog (prioritized, execute in order)

Single prioritized plan from the Product Audit (`docs/product-audit-dashboard.md`), ordered by
**business impact for a single clinic running daily**. Priority order per CEO Directive #003:
Stability → Completeness → Polish → Canonical/AI-readiness. Execute tiers top-down; within a tier,
top-down. Nothing here is a new feature — all polish/harden/consolidate.

Legend: **impact** = why it matters to Tulip · *(rt)* = needs a runtime check first · module shown.

---

## P0 — Trust & Integrity blockers  (a clinic's money, data & access must be safe)

1. ✅ **[COMPLETE — runtime-verified]** **Inventory — transaction on web GRN-receive.** `InventoryController::receivePO()`
   wrapped in `DB::transaction()` (2026-07-18). **Verified on local:** forced failure → full rollback (stock stayed 0,
   no GRN/movement/bill); success → atomic commit (stock 0→5, PO→Completed, vendor bill created). Controller restored clean.
   *Impact: partial-write stock corruption on a daily path — resolved & verified.*
2. **Lab — fix reconciliation-eligibility bug.** Reconcile `received_date` vs `final_received_date` so
   normal-lifecycle cases actually become reconciliation-eligible. *Impact: lab money silently untracked.*
3. **Billing — lock coupon redemption.** Add a row lock / atomic check to `CouponCode::canBeUsedByPatient()`
   redemption. *Impact: over-redemption beyond max-uses = revenue leakage.*
4. **Lab — transactional `transition()`.** Wrap status+task+expense writes in `DB::transaction()`.
   *Impact: partial lab-case state on failure.*
5. **Settings — enforce `can_edit` on write routes.** Feature-flag toggle, role changes, billing/masters
   saves must require edit (not view) + a controller `authorize()` backstop. *Impact: privilege escalation
   (view-only user changing flags/roles) — security, affects Tulip.*
6. **Auth — rate-limit PIN/OTP verify.** Add throttling + lockout to forgot-password PIN and
   `MobileOtpController` verify. *(rt: confirm nothing at WAF.)* *Impact: brute-forceable 6-digit code.*
7. **Treatment Plans — resolve migration risk** *(rt)*. Run/verify the `doctor_id` + `plan_date`
   migrations, or add the `Schema::hasColumn` guard already used for `material_variants`. *Impact: plan
   save may throw SQL errors in prod.*

## P1 — Silent-failure trust & comms reachability  (staff must be able to trust the screen)

8. **Reviews — bring back onto the P1 channel.** Design + build the review-request loop under
   WhatsApp-Web-only: re-point `ReviewService` staff-sends to `WhatsAppLinkService` (wa.me), and decide
   what the automated `reviews:request` command becomes (staff-actioned queue, not auto-send).
   *Impact: today the review workflow can't reach a patient at all.*
9. **WhatsApp — "opened ≠ sent".** Mark a row sent only after staff confirm, not when the link is built.
   *Impact: false "sent" state erodes trust.*
10. **Automation — surface silent skips.** Turn the `relationship_id`-unresolved `Log::debug` skip into a
    visible warning/metric. *Impact: rules silently not firing, invisible to staff.*
11. **Inventory — kill the fake KPI.** Replace/remove the hardcoded implant-stock-health `0`.
12. **Marketing — no placeholder metrics.** Replace hardcoded Campaign Reach/Impressions/Engagement with
    real data or clearly-labeled "pending".
13. **DPDP — enforce the guard structurally.** Make `CommunicationGuard` unbypassable (middleware / model
    event), close the mobile WhatsApp deep-link bypass, and capture consent at registration.
    *Impact: compliance + real patient-communication consent, affects Tulip.*

## P2 — Canonical architecture  (CEO priority #4 — the real "AI-readiness")

14. **Collapse duplicate web↔API business logic onto one service each** (sequence one module at a time,
    test after each): Billing `recordPayment`/`store` → `InvoicePaymentService`; Inventory
    stock-in/out/PO → `InventoryService`; Consultations → new `ConsultationService`; Treatment Plans →
    `TreatmentPlanService`; Prescriptions → `PrescriptionService`; Dashboard → `DashboardMetricsService`;
    Huddle revenue → `ReportMetricsService`. *Model to copy: Treatment Visits / Procurement.*
15. **Per-action permission checks (`User::canAccess`)** across write actions, starting with money/
    security-sensitive ones: billing pay/coupon, wallet credit/campaign, inventory writes, lab
    destroy/approve, vendor-invoice cancel, prescription cancel/send, treatment-plan/consultation edits.
    *This is both the security backstop and the permission-awareness AI needs.*

## P3 — Polish, cleanup & runtime verification

16. **Delete dead/legacy paths:** `clinical_media` upload/serve, `ClinicalFinding`, retired
    `marketing/blog/calendar.blade.php`, and git-delete the tombstoned `ContentManagement/TreatmentVisitController.php`.
17. **Split monoliths:** `patients/show.blade.php` Alpine scope (per-tab components), Huddle controller.
18. **Cosmetics:** ₹/Rs. consistency, hardcoded "kg" suffix, `treatment_options` pricing admin UI (Case
    Acceptance prereq), stray `.bak_v1` file.
19. **Runtime verification pass:** `app:crawl-routes`, `security:selftest`, `automation:parity`, browser
    walkthroughs of every *(rt)* score; confirm HR role-backfill + membership-receipt backfill ran in prod.

---

### Suggested execution rhythm
Ship **P0** as small, individually-tested fixes (each is isolated, low-risk). Then **P1** (each a contained
module change). **P2** is the deliberate, one-module-at-a-time canonical work — *this is where "AI-ready"
is actually earned*, so it gets care, not speed. **P3** last. Re-score the dashboard after each tier so
"are we a product yet?" stays measurable.

---

## Lessons Learned

### P0 #1 — Inventory GRN transaction (2026-07-18)
- **Why the bug existed:** The API path (`InventoryService::receivePurchaseOrder()`) was built *with* a transaction, but the web controller's `receivePO()` was written separately as inline multi-table writes *without* one. Same capability, two implementations — the exact "duplicate web-vs-API logic" pattern the audit flagged — and the web path (the one staff actually use) silently lacked the safety its API twin had.
- **Engineering rule derived:** Any method that performs more than one related write across tables (create/update/increment) **must** be wrapped in a single `DB::transaction()` — no exception for "the web path." Better still: one canonical service owns the write + its transaction, and both web and API call it (per CEO Directive #003).
- **Search elsewhere — yes.** This is a systemic pattern, not a one-off. The audit already found the same missing-transaction risk in **Lab** (`transition()` — backlog P0 #4) and the same web/API duplication in **Billing, Consultations, Prescriptions, Treatment Plans, Dashboard, Huddle** (backlog P2 #14). Recommended: a targeted grep for controller methods doing multiple `->create()/->update()/->save()` without an enclosing `DB::transaction`, folded into the P2 canonical-consolidation work (not opportunistically now).
