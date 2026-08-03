# Patient Management — Variants Completion + Final Production Audit
**Date:** 3 August 2026 · **Mode:** fix-only re-entry against FROZEN V1.0 · **Recorded as:** Amendment 3 in `patients-module-master.md`

## 1. Scope ruling per variant category

| Variant category | V1 status | Ruling |
|---|---|---|
| Entry-path variants | ✅ Verified + hardened | All 9 minting paths route through `PatientService::register()`; invariant guard (`patients:invariant-check`) confirmed sound; perimeter fixed (import gate). |
| Role/permission variants | ✅ Verified + hardened | PM-003 fixed; tags-route override retired; import/export regated; 4 API PHI reads gated; route-table guard test added. |
| Data edge-case variants | ✅ Verified + hardened | Gender enum crash fixed; future DOB, phone blanking, garbage phone (create) rejected; PM-006 dedupe normalization shipped; stale-edit conflict now visible. |
| UI/device variants | ✅ Verified (web print + responsive); **mobile-app parity explicitly OUT OF SCOPE** | This is a desktop-first V1; the Flutter app is a separate parity program (~50% per 07-14 audit). Web: print crash fixed, list/tab overflow OK, dead-view sweep left zero dangling includes. |

## 2. Defects fixed (all directly blocking Production Ready)

| # | Severity | Defect | Fix |
|---|---|---|---|
| 1 | **P0** | `gender=prefer_not_to_say` offered in UI + validation but rejected by DB enum → user-reachable 500 on save (web + API) | Removed from `ProvidesPatientRules` + modal option. Re-adding needs an additive enum migration (deferred — no schema changes allowed this pass). |
| 2 | **P0** | Print view echoed array-cast `allergies` → fatal TypeError for any patient with an allergy | Implode guard (same pattern as consultation tab); also fixed dead `medical_history`/`blood_group` fields → real `medical_alert`/`medical_conditions`/`dental_conditions`. |
| 3 | **P1** | `routes/tags-routes.php` loaded after `web.php` and silently **replaced the gated `patients.tags.*` routes with auth-only ones** — any logged-in user could tag/untag any patient | Duplicate registrations removed (canonical gated routes in web.php stand); `settings/tags` CRUD gated `module:settings`. |
| 4 | **P1** | CSV **import** reachable with settings *view* only — a read-level grant could bulk-create PHI | `module:patients,edit` on `import.preview` + `import.store`. |
| 5 | **P1** | CSV **export** (bulk PHI) gated only by an in-controller `isAdminRole()` 403 | `admin.only` middleware (proper 302 denial semantics); controller check kept as defence-in-depth. (PM-007) |
| 6 | **P1** | PM-003: `relationship-notes.destroy` + `opportunities.destroy` (hard deletes) gated `,edit` | Regated `,delete`. (`family.links.destroy` stays `,edit` — reconstructable graph edit; `tags.detach` correct by design.) |
| 7 | **P1** | 4 API PHI endpoints auth-only: `GET /api/v1/patients`, `/patients/search`, `…/same-issue-context`, `/coha/{c}` | All four now `api.role:module:patients,view` (completes the Slice 1.4 rule). |
| 8 | **P1** | PM-006: duplicate-phone detection = exact string match; `+91`/spaces/hyphens/leading zero all evaded it | `findDuplicatesByPhone()` matches on normalized last-10 digits (SQL nested-REPLACE, portable; strictly broader than old behavior — no lost matches). |
| 9 | **P1** | Optimistic-lock conflict (stale edit 422) invisible in the modal — user's edit silently discarded | `updated_at` error folded into the `_general` banner. |
| 10 | **P1** | Phone blankable via partial update → record permanently invisible to dedupe + messaging | Update rule `phone` → `sometimes|filled`. |
| 11 | **P1** | Import summary attributed **all** skipped rows to "duplicates" | Per-reason counters: duplicate phone / duplicate ID / missing name+phone / summary rows. |
| 12 | P1→P2 | Garbage phone ("abc") passed validation | Create-rule regex (≥7 digits, digits+separators). Deliberately NOT on update, so legacy-formatted records stay editable. |
| 13 | P2 | Future DOB accepted → negative age, false DPDP-minor flag | `before_or_equal:today` (create + update). |
| 14 | P2 | Print reachable for merged/trashed records | Same 404 guards as `show()`/`tab()`. |
| 15 | P2 | `layouts/print.blade.php` malformed `font-family` declaration | Fixed (affects all print views, one word). |
| 16 | P2 | Dead `importForm()` referencing a deleted view | Removed (was unrouted). |

## 3. Files modified (11)

- `routes/tags-routes.php` — override removed, settings/tags gated
- `routes/web.php` — PM-003 (2 gates), import/export gates
- `routes/api.php` — 4 PHI read gates + comment correction
- `app/Http/Requests/Patient/ProvidesPatientRules.php` — gender list, DOB bound, phone filled/regex
- `app/Services/PatientService.php` — normalized duplicate detection
- `app/Http/Controllers/PatientController.php` — print merged/trashed guard
- `app/Http/Controllers/PatientImportExportController.php` — skip-reason counters, honest summary, dead method removed
- `resources/views/partials/add-patient-modal.blade.php` — gender option removed, stale-conflict banner
- `resources/views/patients/print.blade.php` — array guards, real medical fields, blood-group row removed
- `resources/views/layouts/print.blade.php` — CSS fix
- `docs/patients-module-master.md` — Amendment 3

**New:** `tests/Feature/Patients/VariantHardeningTest.php` (13 tests: gate regressions incl. a route-table guard asserting every `patients.*` route declares a module/admin gate, validation edges, dedupe normalization, print rendering).

Zero schema changes. Zero new endpoints. No other module touched (lead-conversion atomicity gap noted below lives in the Relationship module and was left alone per instruction).

## 4. Verification to run (I do not execute artisan per project policy)

```
php -d opcache.enable_cli=0 artisan test tests/Feature/Patients/VariantHardeningTest.php
php artisan test tests/Feature/Patients tests/Feature/Access
php artisan patients:invariant-check
php artisan app:crawl-routes
```
All four must be green before "Production Ready" is checked. On deploy, `bash deploy.sh` (route changes require the container's route cache rebuild, which deploy already does).

**Staff release notes (behavior changes):** more duplicate warnings will appear (that's PM-006 working); "Prefer not to say" gender removed — leave blank; new patients need a real phone number; non-admins hitting Export now get the standard access-denied redirect; the mobile app now requires the patients View permission for patient list/search.

## 5. Remaining items — none are P0/P1 engineering defects in this module

| Item | Class | Why open |
|---|---|---|
| **PM-005** — require guardian at **registration** for minors? | Policy decision (CEO + clinical) | Cannot be engineered around. Current behavior: minor registers freely; guardian enforced at consent capture. Either ruling is a small follow-up. |
| API `store` has no duplicate-phone warning (web does) | Deferred parity gap | Adding a 409 flow changes the mobile API contract; needs app-side handling. Schedule with mobile parity program. |
| TDC generator race under simultaneous multi-terminal registration | P2, watched | 500 (not corruption) in a narrow window; real fix wants a lock — behavior change beyond fix-only. |
| quickStore inline validation drift; billing tab not `billing.view`-filtered (timeline is); reactivate lacks re-auth; export CSV missing UTF-8 BOM; import forces `phone=''`; lead-conversion non-transactional (Relationship module); PM-008 broader merge coverage; PM-010/011 (011 = schema); KD-6 `#membership` deep-link | P2 / other-module / schema-gated | Logged, none block production for a single-tenant V1. |

## 6. Verdict

Variants: **COMPLETE** (all four categories verified; out-of-scope declared).
Production Ready: **YES, conditional** — check it once (a) the four commands above run green locally, and (b) you give a one-line PM-005 ruling ("current consent-time enforcement is the V1 policy" is sufficient and requires zero code). Both P0s and every P1 engineering defect found in this module are fixed in this pass.
