# Patients Module — Phase 2 Freeze (Validation & Permissions)

> This document is the **permanent engineering template for Dentfluence**. Every module phase-freeze follows this structure (see §Engineering Standards).

---

## Module Metadata

| | |
|---|---|
| **Module** | Patients |
| **Version** | V1.0 |
| **Status** | 🔒 FROZEN |
| **Module Owner** | Dentfluence Core |
| **Last Updated** | 20 July 2026 |

**Completed Phases**
- ✅ Audit & Design
- ✅ Duplicate Merge
- ✅ Validation & Permissions

**Upcoming Phase**
- ⏳ Phase 3 — Family / Guardian

**Dependencies**
- Inventory ✅
- Appointments ⏳
- Consultations ⏳
- Treatment Plans ⏳
- Billing ⏳

> **Freeze note:** Approved by Sumit. Baseline docs: `patients-module-audit.md`, `patients-module-design.md`. Prior phase: Duplicate Merge (closed). Authoritative for future work touching patient creation, validation, or authorization.

---

## Module Lifecycle (permanent engineering process)

Every Dentfluence module phase moves through this lifecycle. A phase is not complete until it is **frozen**.

```
Audit
  ↓
Architecture Design
  ↓
Implementation
  ↓
Small Tested Slices
  ↓
Runtime Verification
  ↓
Self Audit
  ↓
Regression Review
  ↓
Architecture Guard
  ↓
Freeze
  ↓
Next Module
```

---

## 1. Scope Completed

- **One canonical validation contract (Web + API).** A shared `ProvidesPatientRules` trait holds the single rule set, consumed by web `StorePatientRequest`/`UpdatePatientRequest` and the API V1 requests; each keeps its own failure response (web redirect / API JSON envelope).
- **`mobile`/`phone` drift eliminated.** Canonical field is `phone`; `normalizePatientAliases()` folds legacy `mobile`→`phone`, `dob`→`date_of_birth`, `notes`→`chief_complaint` (conditionally, so partial updates never blank a field).
- **DOB optional, age always captured.** DOB is optional; age is required only when no DOB (`required_without:date_of_birth`), enforced server-side + a client check in the modal.
- **Canonical `PatientService::register()`.** The single mint point (`register($input,$actor,?Appointment)`). Web/API `store`, `quickStore`, lead conversion (web+API), and bulk import all route through it. `createFromInput` remains a thin `@deprecated` alias; `quickCreate` removed. `register()` was completed with `patient_id` (import source-ID passthrough), `state`, and `referred_by`.
- **Per-action authorization.** Route middleware `module:patients,edit` (create/update/deactivate/reactivate/quick-store), `module:patients,delete` (destroy), `admin.only` (merge); Form Request `authorize()` + controller `abort_unless` backstops.
- **Architecture Invariant Guard.** `patients:invariant-check` enforces the Patient Creation Policy (exit 1 on any bypass); currently PASS.
- **Lifecycle readiness.** `register()` carries the `?Appointment` link-back hook for the future Appointment→Arrived→Registration flow, without changing any appointment-side code.

*Files:* `app/Http/Requests/Patient/{ProvidesPatientRules,StorePatientRequest,UpdatePatientRequest}.php`; `app/Http/Requests/Api/V1/{StorePatientRequest,UpdatePatientRequest}.php`; `app/Services/PatientService.php`; `app/Http/Controllers/{PatientController, Api/V1/PatientController, Relationship/LeadPipelineController, Api/V1/RelationshipController, PatientImportExportController}.php`; `routes/web.php`; `resources/views/partials/{add-patient-modal, appointment-modal-global}.blade.php`; `app/Console/Commands/{PatientInvariantCheck, PatientRegisterSmokeTest, PatientPhase2Verify}.php`.

---

## 2. Architecture Decisions

1. **Shared rules via a trait, not a shared base class** — web and API need different failure responses, so the *rules* are shared while each Form Request keeps its base (`FormRequest` vs `ApiFormRequest`).
2. **Normalize aliases in `prepareForValidation`, conditionally** — legacy front-end field names keep working; canonical keys are only injected when an alias was actually sent, protecting partial updates.
3. **`register()` is the sole mint point; `createFromInput` demoted to an alias** — one canonical creation path; the alias is a safety shim, not a second path.
4. **`register()` passes through a supplied `patient_id`** — the model boot only auto-assigns a TDC when none is given, so bulk import/migration can preserve external IDs through the canonical path.
5. **Per-action authorization at the route layer, with backstops** — reuses the existing `CheckModulePermission` (`module:x,action`) and `admin.only`; controller/request backstops for defense in depth.
6. **Lead conversion is a registration event** — folded into `register()` (both web + API) while preserving idempotent reuse-by-`relationship_id`, lead stage/activity, and PRM spine mirroring.
7. **Bulk import uses `register()` per row** — dedup/skip/sanitization/chunking stay in the controller; the minor per-row `fresh()` cost is accepted for an admin one-time import.
8. **Appointment booking is NOT changed** — the three booking paths that mint a Patient+TDC remain as documented temporary debt (see §4), to be removed by the Appointments module.
9. **The invariant is enforced by tooling, not just docs** — `patients:invariant-check` fails CI/pre-commit on any new bypass.

---

## 3. Engineering Invariants (permanent)

- **Single Patient Mint Point** — patients are created only via `PatientService::register()` (or a service that delegates to it).
- **Patient Creation Policy** — no business workflow may use `Patient::create()` / relation `->create()` / raw `DB::table('patients')->insert()`. Permitted exceptions: seeders, factories, test fixtures; plus the two temporary appointment paths until the Appointments lifecycle ships.
- **Shared Validation Contract** — one rule set (`ProvidesPatientRules`) governs web + API; canonical field names are `phone` / `date_of_birth` / `chief_complaint`.
- **Per-action Authorization** — patient write actions are gated by `module:patients,{edit|delete}`; merge is `admin.only`.
- **Architecture Invariant Guard** — `patients:invariant-check` is the automated enforcer; it must stay green.

---

## 4. Technical Debt

| ID | Description | Responsible module | Priority |
|---|---|---|---|
| PD-01 | Appointment → Arrived → Registration lifecycle | Appointments | High |
| PD-02 | TDC generated at booking (3 paths: `AppointmentController::store`, `AppointmentService`, `PatientController::quickStore`) | Appointments | High |
| PD-03 | Per-action authorization unverified against a non-admin (single-login phase → admin bypass) | Multi-role / HR | Medium |
| PD-04 | API authorization is route-level (`api.role`), not per-action `canAccess` | API hardening | Low |
| PD-05 | `createFromInput` deprecated alias still present | Cleanup | Low |
| PD-06 | Front-end modal still posts legacy field names (`mobile`/`dob`/`notes`) | Profile refactor / cleanup | Low |
| PD-07 | Patient creation not yet on the Journey Timeline (no `patient.registered` activity) | Profile refactor | Medium |
| PD-08 | Duplicate detection is phone-exact + name/DOB soft hint (no dup-screen merge entry, no Potential-Duplicates report) | Later Patients polish | Low |
| PD-09 | Guard doesn't match `Patient::query()->create()` (no current usage) | If/when needed | Low |

---

## 5. Regression / Runtime Verification Checklist

Automated (rollback smoke tests, all PASS): `patients:register-smoketest` (5), `patients:phase2-verify` (13 — lead conversion + import), `patients:merge-smoketest` (16, Phase 1), `patients:invariant-check` (guard green), `patients:merge-coverage` (green).

Manual (for Tulip):

| Scenario | Expected outcome |
|---|---|
| Register new patient (name + phone, no DOB) | Saves; TDC assigned; age optional |
| Register with DOB Unknown + age | Saves; age stored |
| Register with neither DOB nor age | Blocked ("Enter date of birth or age") |
| Quick registration (appointment modal) | Patient created + selected; existing phone → duplicate prompt |
| Edit patient (one field) | Saves; other fields untouched |
| Deactivate → Reactivate | Status toggles; reason recorded |
| Delete (password + reason) | Soft-deleted; wrong password rejected |
| Merge duplicates | Loser archived; history moved; old URL redirects to master |
| Import CSV (with `patient_id` column) | Rows imported; source IDs preserved; duplicates skipped |
| Lead conversion | Lead → converted; patient created + linked; re-convert reuses same patient |
| API registration (`POST /api/v1/patients`) | Success; validation errors return JSON envelope |
| Permission (once a non-admin role exists) | View-only user blocked from create/edit/delete |

---

## 6. Future Dependencies

- **Appointments module** — must remove the three TDC-at-booking paths (PD-01/PD-02) and mint the patient at Registration via `register($input,$actor,$appointment)` (the link-back hook is ready).
- **Family / Guardian (Phase 3)** — builds on `register()` for guardian creation and on the shared validation contract.
- **Profile refactor phase** — will add the `patient.registered` Journey Timeline producer (PD-07).
- **Multi-role / HR phase** — activates the per-action authorization already wired (PD-03).
- **Any new patient-creating feature (any module)** — must go through `register()`; the guard enforces it.

---

## 7. Freeze Statement

**Phase 2 – Validation & Permissions is frozen.**
No further modifications should be made unless a production bug is discovered or a future module requires an approved architectural change.

---

## 8. Lessons Learned

- **Canonical services reduce duplication.** One `register()` mint point removed five divergent creation paths and made field/behaviour changes single-edit.
- **Shared validation prevents drift.** A single rule set (trait) means web and API can never disagree again; the old `mobile`/`phone` split can't recur.
- **Architecture guards are worth building.** `patients:invariant-check` turns a rule from "documented" into "enforced" — it fails the moment anyone reintroduces a bypass.
- **Scope discipline avoided unnecessary complexity.** Keeping the appointment lifecycle out of this phase (documented as debt) prevented an inferior half-measure and preserved clean module boundaries.
- **Runtime verification caught issues before freeze.** The rollback smoke tests surfaced a real setup nuance (`Lead.relationship_id` not mass-assignable) and proved the lead-conversion + import refactors end-to-end — before, not after, the freeze.
- **Read before writing.** Auditing every caller (and the whole schema) before refactoring prevented data loss (e.g., `register()` was silently dropping `patient_id`/`state`/`referred_by`, caught and fixed).

---

## 9. Engineering Standards (mandatory for every Dentfluence module)

Every future Dentfluence module must follow the same process. Each module must have:

- An **architecture document** (audit + design)
- An **implementation** built in small, tested slices
- **Runtime verification** (automated smoke tests + a manual checklist)
- A **self audit**
- A **regression review**
- A **freeze document** (this template)
- **Architecture guards** where appropriate (automated invariant enforcement)

**A module is NOT considered complete until it is frozen.**

---

## Engineering Index

**Next Module**
- Patients Phase 3 — Family / Guardian

**Future Documents** (follow this naming convention across the platform)
- Patients Phase 3 Freeze
- Appointments Phase 1 Freeze
- Consultations Phase 1 Freeze
- Treatment Plans Phase 1 Freeze
- Billing Phase 1 Freeze
- Clinical Media Phase 1 Freeze
- … continue `\<Module\> Phase \<N\> Freeze` for the entire Dentfluence platform.
