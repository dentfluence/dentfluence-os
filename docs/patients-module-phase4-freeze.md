# Patients Module — Phase 4 FREEZE
## Patient Profile Refactor + Journey Timeline

> **Status:** CODE COMPLETE — all three slices implemented per the frozen design
> (`docs/patients-module-phase4-profile-timeline-design.md`, incl. Amendment 1).
> **Freeze gate:** green test run + manual QA below, then commit per slice.
> **Date:** 2026-07-22

---

## 1. What shipped

**Slice 1 — Profile refactor.** `patients/show.blade.php` went from **3,705 → ~300 lines** (orchestrator + Alpine root). All markup was moved **verbatim** into scoped partials; the four large clinical partials (treatment-plan, visits, documents, membership) were wrapped, not rewritten. Ten tabs are now **lazy fragments** served by `PatientController@tab`; only the Profile tab renders eagerly. `loadProfile()` was split into `coreProfile()` + per-tab `tabData()`; the Family/Guardian view-model moved from the controller into `PatientProfileService::familyPanel()`. Note/opportunity write routes are now gated by `module:patients,edit` (previously view-only users could write). Dead partials deleted.

**Slice 2 — Journey Timeline.** `UnifiedTimelineService` gained `forPatient()` (clinical scope) with 12 guarded source adapters — registration, consultations/COHA, treatment plans **+ accepted/rejected/deferred decision events (Amendment 1)**, treatment visits, invoices, payments, clinical files, lab events, memberships + benefit logs, reviews, consent logs — merged with the Activity ledger and the four patient-scoped comms sources. The relationship-scoped `for()` used by the PRE profile is **untouched**. `PatientJourneyService` (canonical patient-history read model) adds permission filtering, group filtering and cursor pagination on top. The Profile tab's old two-source "Visit Log" card was replaced by the Journey Timeline card (filters: All / Clinical / Financial / Comms / Consent / Reviews; "Load older" pagination; permission-aware per event).

**Slice 3 — Hardening.** Structural audit (tag/brace balance on every extracted file), perf contract test (profile open queries no lazy-tab tables), permission tests, fragment tests, decision-event tests, this freeze doc.

## 2. Files

**New:** `resources/views/patients/profile/{styles,header,tab-profile,quick-pay-modal,edit-patient-prefill,action-modal,journey-timeline,journey-timeline-events}.blade.php` · `resources/views/patients/tabs/{_fragment,consultation,treatment-plan,visits,lab,prescriptions,billing,wallet,membership,documents,notes}.blade.php` · `app/Services/Patient/PatientJourneyService.php` · `tests/Feature/Patients/{ProfileRefactorTest,JourneyTimelineTest}.php` · this doc.

**Modified:** `resources/views/patients/show.blade.php` (rewritten as orchestrator) · `app/Services/PatientProfileService.php` (coreProfile/tabData/familyPanel split; `loadProfile()` kept as BC composition) · `app/Http/Controllers/PatientController.php` (slim `show()`, new `tab()`, new `timeline()`) · `app/Services/Relationship/UnifiedTimelineService.php` (clinical scope; `for()` unchanged) · `routes/web.php` (2 new GET routes; 5 write routes moved under `,edit`) · design doc (Amendment 1).

**Deleted:** `patients/partials/edit-patient-drawer.blade.php`, `patients/partials/communications-tab.blade.php` (verified unreferenced).

## 3. Behaviour deltas (everything else is verbatim)

1. Tabs load on first activation (fragment fetch) instead of all-at-once; failed loads show an inline retry message.
2. "New Visit" / "Add Follow-up" / "Membership" quick actions now await the tab fragment before dispatching their open-form events (previously a 50 ms `setTimeout` race).
3. The Profile tab's Visit Log card is replaced by the Journey Timeline (by design). Per-row edit/delete moved with the data to the Visits/Consultation tabs; "Manage Visits" links there.
4. View-only users can no longer create/delete rapport notes and opportunities (P1 permission leak closed).
5. Fixed a latent JS bug: profile-tab opportunity delete called undefined `deleteOpportunity()`; an alias now maps it to `deleteOpp()`.
6. Timeline events the viewer lacks module permission for (billing, lab, consent) are filtered out server-side.

## 4. Known items / debt (accepted at freeze)

- **Duplicate Wallet panel** — the original page contained two `activeTab === 'wallet'` blocks; both were preserved verbatim inside the wallet fragment. Removal needs CEO sign-off (visual change).
- `treatment-plan-tab.blade.php.bak_v1` — dead backup file, not deleted (not in authorized scope).
- Timeline caching + `ActivityRecorded` cache-bust — deferred: per-source LIMITs keep the endpoint bounded; caching adds staleness risk for no measured need. Revisit if slow on long-tenured patients.
- Assistant tools still query models directly — repointing to `PatientJourneyService::summarize()` is V3 (Copilot) work, per design §8.
- `plan_date`/`doctor_id` on treatment_plans depend on earlier unrun-migration status (see 07-15 memory); the plan adapter is `guard()`ed, so missing columns degrade silently rather than erroring.

## 5. Commands to run (manually — in order)

```bash
php artisan optimize:clear                      # views/routes/config re-cached
php artisan test --filter=ProfileRefactorTest
php artisan test --filter=JourneyTimelineTest
php artisan test tests/Feature/Patients         # full Phase 3+4 regression
php artisan app:crawl-routes                    # page-level smoke
```
No migrations. If Laragon CLI serves stale bytecode: `php -d opcache.enable_cli=0 artisan test …`.

## 6. Manual QA checklist

1. Open a long-tenured patient → page loads fast; Journey Timeline populates; "Load older" works; filters work.
2. Click through all 11 tabs → each renders on first click and instantly on the second; deep-link `…#billing` opens Billing.
3. Header "New Visit" → Visits tab opens **with the add-visit form open**. Quick action "Membership" → enroll modal opens.
4. Record Payment (Quick Pay) from a non-billing tab still works (modal is eager).
5. Edit Patient modal opens pre-filled; deactivate/delete modal works; Print unaffected.
6. As a role without billing access: profile timeline shows no invoice/payment rows.
7. PRE Relationship Profile timeline unchanged (spot-check one linked patient).
8. Mobile API smoke: `/api/v1/patients/{id}` endpoints unaffected (no shared code paths changed — `Api/V1/PatientProfileController` untouched).

## 7. Commit plan (one revertable commit per slice)

1. `patients-p4-s1: profile refactor — thin orchestrator + lazy tabs + perms fix`
2. `patients-p4-s2: journey timeline — PatientJourneyService + clinical scope + UI`
3. `patients-p4-s3: hardening — tests + freeze doc`

Rollback = `git revert` of the offending slice commit (no flags, per Amendment 1).

---

**PHASE 4: CODE COMPLETE.** Freeze is declared when §5 runs green and §6 passes. No Phase 5 work begun.
