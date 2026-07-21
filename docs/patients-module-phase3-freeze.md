# Patients Module — Phase 3 Freeze (Family / Guardian)

> Freeze document per the permanent Dentfluence engineering template (see Phase 2 freeze §Engineering Standards).

---

## Module Metadata

| | |
|---|---|
| **Module** | Patients |
| **Phase** | 3 — Family / Guardian |
| **Status** | 🔒 FROZEN (final run green 2026-07-22: 44 tests + 4 guard commands) |
| **Module Owner** | Dentfluence Core |
| **Last Updated** | 22 July 2026 |

**Completed Phases:** Audit & Design ✅ · Duplicate Merge ✅ · Validation & Permissions ✅ · **Family / Guardian ✅**
**Upcoming:** Phase 4 — Profile refactor (+ Patient Journey Timeline)

---

## 1. Executive summary

Phase 3 turned the dormant `patient_links` table into the **canonical interpersonal family graph**: typed, guardian-aware, single-row with derived inverse labels, owned by one service, surfaced on the patient profile, wired into duplicate detection and DPDP consent, and exposed read-only on the API. Three previously disconnected "family" concepts (links / household-phone / membership-billing) now have declared owners and are referenced, never merged. Guardianship is a single capacity flag (`is_guardian`); "ward" is always derived. A latent production bug in the merge service (array-`orWhere` over-matching) was found and fixed along the way.

## 2. Architecture implemented

Per the frozen design (`patients-module-phase3-family-guardian-design.md`), including all review corrections: kinship vocabulary `mother/father/spouse/child/sibling/other` only; `is_guardian` as the single guardian representation; one active label column (legacy backfilled, read-only); DB-level ordered-pair uniqueness; pure-graph service with presentation concerns left to consumers; consent reads the graph but stores its own immutable snapshot.

## 3. Slices completed

1. **Foundation** — schema + backfill + functional unique index + `PatientLink` model + merge precedence rules.
2. **`FamilyLinkService`** — canonical reader/writer (add/update/remove/linksFor/guardiansFor/wardsFor/attachGuardian, inverse-label map).
3. **Profile "Family & Contacts"** — read/add/edit/remove UI + guardian workflow + duplicate-screen "Register + link family".
4. **Consent anchor** — minor consent prefills the consenting guardian from the graph (snapshot persistence untouched).
5. **API read exposure** — `linked_members` / `is_minor` / `guardian_required` on the detail endpoint only.
6. **Hardening + freeze** — F1 guardian demotion path, F2 canonical validation, F3 no-silent-link-failure; this document.

## 4. Features delivered

Typed bidirectional family links with derived, gender-refined labels · guardian designation + minor detection + DPDP prompt · profile Family & Contacts card (emergency contact, nominee, household/membership reference chips) · duplicate-registration family linking · consent guardian prefill with multi-guardian picker · read-only mobile/API family graph.

## 5. Database changes

`patient_links` (additive, reversible): `relationship_type` (string 30, kinship only), `is_guardian` (bool), `notes` (string 150); legacy `relationship` backfilled → read-only; reverse-duplicate pairs collapsed; functional UNIQUE index `patient_links_pair_unique` on `(LEAST(patient_id,linked_patient_id), GREATEST(...))` (MySQL 8). No other tables touched.

## 6. Services

- **`FamilyLinkService` (new, canonical):** `addLink` (never demotes — OR), `updateLink` (explicit edit — may demote, F1), `removeLink`, `linksFor` (pure graph + inverse labels), `guardiansFor`/`wardsFor`, `attachGuardian` (new guardians minted via `PatientService::register()` in one transaction). Producers: `family.link_added/updated/removed`, `guardian.assigned`.
- **`PatientMergeService` (extended):** typed/guardian link reconciliation (guardian = OR, specific-type-over-`other`, master notes), guardian↔ward merge blocked, canonical `scopeLinkPair()` (fixed the pre-existing array-`orWhere` over-match bug).
- Unchanged: `PatientService` (still the sole mint point), `ConsentService` (snapshot persistence untouched).

## 7. Controllers

- **`Patient\FamilyController` (new, thin):** storeLink/updateLink/destroyLink/storeGuardian — shape validation + delegate only.
- **`PatientController`:** `show()` preps read-only family data; `store()` gained the duplicate-screen link branch (shape-validated, outcome reported).
- **`ConsentController`:** `patient()` passes guardian links (minors only).
- **`Api\V1\PatientController`:** `show()` attaches the family graph as a loaded relation.

## 8. UI delivered

`patients/partials/family-contacts.blade.php` (isolated Alpine; guardian alert, member list with badges, add/search panel, guardian create-new, row edit/remove, empty/error states, chips, collapsed nominee) · duplicate-picker overlay in `add-patient-modal` (replaces native `confirm()`) with link-failure warning on the success panel (F3) · consent guardian block (prefill precedence: linked guardian → previous snapshot → blank; multi-guardian picker; no-guardian nudge).

## 9. Consent integration

The guardian link is the DPDP anchor: minors' consent capture prefills the consenting party from the graph. Consent **stores its own free-text snapshot** (`on_behalf_of`, `guardian_name`, `guardian_relationship`) — later graph changes never mutate past consents. Free text remains the fallback for non-patient guardians.

## 10. API

Detail endpoint only (`GET /api/v1/patients/{id}`): `linked_members[] {id,name,relationship,is_guardian,is_ward,is_minor}`, `is_minor`, `guardian_required` (= minor && no guardian link). Gated via loaded-relation check — list endpoints never carry family fields; one service call, no N+1; **no `guardians[]` array** (single representation); **no write endpoints**.

## 11. Permissions

Reuses Phase 2 exactly: view = `module:patients`; all family writes = `module:patients,edit` (route middleware + Blade hide via `canEditFamily`); merge = `admin.only`. No new primitives. Note: module denial = redirect (not 403) — asserted accordingly in tests.

## 12. Merge compatibility

Typed links re-parented with precedence rules; guardian authority never dropped (OR-union); guardian↔ward merges blocked with an explicit reason; manifest records deleted + reconciled rows (un-merge-ready). 16-check merge smoke test green post-change.

## 13. Regression results

**Final run (2026-07-22) — ALL GREEN:** `FamilyLinkServiceTest` 13/13 · `FamilyContactsSectionTest` 13/13 · `ConsentGuardianAnchorTest` 5/5 · `FamilyGuardianFoundationTest` 8/8 · `PatientFamilyApiTest` 5/5 · `patients:merge-smoketest` 16/16 · `patients:register-smoketest` 5/5 · `patients:invariant-check` ✓ · `patients:merge-coverage` ✓ (51 + 15 tables classified).
Commits: `fd3b6d5` (foundation, Slices 1–2), `73fbbf9` (Slice 3), `12506f1` (Slice 4), `6860480` (hardening F1–F3), `8fda676` (Slices 5–6).

## 14. Known technical debt (P2/P3 only — accepted)

| ID | Debt | Priority |
|---|---|---|
| FD-01 | Household count queried inline in `PatientController::show` (second implementation of the household read; PRE has its own) | P2 |
| FD-02 | `guardiansFor()` called alongside `linksFor()` on profile open (one redundant query; derivable from links) | P2 |
| FD-03 | Merge writes `patient_links` via `DB::table` (bypasses model Auditable; manifest compensates) | P2 |
| FD-04 | Controller namespace inconsistency (`Patient\FamilyController` vs root `PatientMergeController`) | P2 |
| FD-05 | Consent routes carry auth but no `module:` middleware (pre-existing; multi-role phase) | P2 |
| FD-06 | Missing negative permission tests for updateLink/destroyLink/storeGuardian; no cross-branch link test | P2 |
| FD-07 | `_guardian_pick` helper radio submits with consent form (ignored server-side) | P3 |
| FD-08 | Legacy `relationship` column retained read-only (drop in a future cleanup) | P3 |
| FD-09 | `linksFor()` has no `origin` key (design-doc wording drift; nothing consumes it) | P3 |
| FD-10 | Primary-guardian designation is notes-based; consent picker defaults to first linked | P3 |

## 15. Future work (Phase 4+ only)

Profile refactor: Family & Contacts becomes a lazy per-tab component; fold FD-01/FD-02 into a shared reader · Patient Journey Timeline consumes the family/guardian activity events already being produced · patient-app family writes (deferred) · family membership suggestions reading the graph (Membership-owned) · un-merge UI (data-ready).

## 16. Final architecture (text diagram)

```
                    ┌────────────────────────────────────────┐
                    │      patient_links (canonical graph)    │
                    │ relationship_type · is_guardian · notes │
                    │ UNIQUE(LEAST(a,b), GREATEST(a,b))       │
                    └───────────────▲────────────────────────┘
                                    │ sole writer/reader
                        ┌───────────┴───────────┐
                        │   FamilyLinkService    │──── activity: family.link_* / guardian.assigned
                        │ add/update/remove/     │──── new guardians → PatientService::register()
                        │ linksFor/guardiansFor  │     (single mint point, atomic)
                        └──▲───────▲────────▲───┘
             web UI        │       │        │        read-only
  ┌────────────────────────┴─┐ ┌───┴─────┐ ┌┴─────────────────────┐
  │ Patient\FamilyController │ │ Consent │ │ Api\V1\PatientCtrl    │
  │ (links CRUD, guardian)   │ │ prefill │ │ show → familyGraph    │
  │ + PatientController::    │ │ (snap-  │ │ → PatientResource     │
  │   store dup-link branch  │ │  shot   │ │ linked_members et al. │
  └───────────▲──────────────┘ │ stored) │ └──────────────────────┘
              │                └─────────┘
  ┌───────────┴──────────────┐    referenced, never merged:
  │ family-contacts.blade    │    household (relationship_id, PRM)
  │ + dup-picker overlay     │    family membership (finance, Membership)
  └──────────────────────────┘
  Merge: PatientMergeService reconciles links (guardian OR, specific>other),
         blocks guardian↔ward collapse, scopeLinkPair() everywhere.
```

## 17. Verification checklist

- [x] Canonical services (one graph writer; mint invariant green)
- [x] Transaction boundaries (attachGuardian atomic; merge locked single-tx)
- [x] Merge safety (precedence + guard + smoke test)
- [x] Permissions (Phase 2 model reused; Blade + route gated)
- [x] Audit trail (PatientLink Auditable; merge manifest)
- [x] Timeline events (4 producers, keyed on relationship_id)
- [x] API (detail-only, whenLoaded-gated, no N+1, no dup array, no writes)
- [x] No duplicate business logic (vocab owned by service; F2 closed)
- [x] No dead code / unused imports (swept)
- [x] Route consistency (patients.family.*, module:patients,edit)
- [x] Controllers thin (validate + delegate)
- [x] Blade presentation-only
- [x] Backward compatibility (adults/consent/legacy column unchanged)
- [x] Final full test run green (2026-07-22, 44 tests + 4 guard commands)

## 18. Final scorecard

| Dimension | Score |
|---|---|
| Architecture fidelity to frozen design | 10/10 |
| Test coverage (unit-ish + web + API + smoke) | 9/10 |
| Merge/data safety | 10/10 |
| UI (clinic-friendly, progressive) | 9/10 |
| Debt honesty (all P2/P3 logged) | 10/10 |
| **Phase 3 overall** | **9.5/10** |

---

## Freeze statement

**Phase 3 — Family / Guardian is FROZEN (2026-07-22).** No further modifications unless a production bug or an approved architectural change requires it. Next: Phase 4 — Profile refactor (+ Journey Timeline).
