# Patients Module — Baseline Audit

*Baseline reference for the Patients module completion (parallel to the Inventory audit). Evidence-based from code review on 2026-07-20. Follows CEO Directive #003: quality over breadth, one canonical implementation, module-owned lifecycle.*

---

## Verdict

Patients is one of the **healthier** modules. Write/read business logic is already canonical — web (`PatientController`) and API (`Api/V1/PatientController`) both go through a single `PatientService`. The completion work is **consolidation, one missing workflow (duplicate merge), a reduced-scope family/guardian model, and modularising a monolith view** — not a rebuild.

Audit-dashboard score: **7/10** (Stab 7 · Compl 8 · Polish 5 · AI-Rdy 8).

---

## 1. What exists today

### Backend (strong)
- **`PatientService` (23K) is the single source of truth** for reads and writes: `filteredQuery`, `applyMembershipFilter`, `applySort`, `distinctAreas`, `suggest`, `createFromInput`, `findDuplicatesByPhone`, `quickCreate`, `updateFromInput`, `deactivate`, `reactivate`, `softDelete`, `buildName`, `syncTags`.
- **Web and API share it.** `Api/V1/PatientController` calls `createFromInput`/`updateFromInput`/`filteredQuery` with Form Requests. No duplicate write logic (the reason AI-Readiness scores 8).

### Model — `Patient` (475 lines)
- `SoftDeletes` + `Auditable` + `BelongsToBranch`.
- Resilient PHI-encryption casts on name fields, with a `patients:encrypt-phi` backfill command. `phone`/`email` intentionally left plaintext for partial search (rely on encryption-at-rest).
- ~20 relations: appointments, notes, relationshipNotes, opportunities, alerts, referrals/referredPatient, identifiers (ABHA), allergyRecords, treatmentVisits, treatmentPlans, consultations, labCases, clinicalFiles, communications, consents, consentLogs, dataRequests, voiceNotes, tags, linkedPatients / linkedByPatients (family).
- Computed attributes: age / ageNumeric, initials, recall badge colour, AOCP/membership status. Automation scopes; `markContactInvalid` / `disableAutomations`; DPDP `isMinor()`.

### Controller — `PatientController` (21K)
- `index` → `PatientService::filteredQuery` (list + filters, branch-scoped, paginated 30).
- `create` → redirects to the Add-Patient modal (`?new=1`); the old `patients/create.blade.php` is a legacy consultation form, no longer used.
- `store` → validates, **duplicate-phone guard** (soft, staff can confirm), `createFromInput`.
- `show` → **access-trail audit log** (`viewed`) + `PatientProfileService::loadProfile`.
- `edit` → `patients/edit.blade.php` (orphan — see dead views), `update` → **optimistic lock** (`assertNotStale`) + `updateFromInput`.
- `destroy` / `deactivate` → **password + reason** gated; soft delete / reason saved. `reactivate`, `print`, `scanForm` (photo intake form → local vision pre-fill, extraction-only), `search` (`suggest`), `quickStore` (`quickCreate` from appointment modal).

### Views
- `index.blade.php` (468) — list + filters. `_search.blade.php` (257) — global topbar search dropdown.
- `add-patient-modal.blade.php` (1,392) — the real, dual create/edit 5-tab modal.
- `show.blade.php` (**3,674 lines / 239 KB**) — profile, 10 tabs: profile, consultation, treatment-plan, visits, prescriptions, billing, wallet, membership, documents, notes. 8 nested Alpine `x-data` scopes, 6 inline `<script>` blocks.
- `print`, `import` / `import-preview`, `abha`. Legacy: `create.blade.php`, `edit.blade.php`, `partials/edit-patient-drawer.blade.php`.

### Workflows that work well (protect these)
Photo-form scan pre-fill; duplicate-phone soft guard on full + quick create; optimistic locking on update; password+reason on delete/deactivate; access-trail on every profile view; soft-delete throughout.

---

## 2. Gaps & findings

### Functional (the module can't close without these)
- **Duplicate MERGE is missing.** The system *detects* duplicates but cannot *merge* them → returning patients get permanently split history (consultations, plans, visits, billing, wallet, membership, media, documents, notes, communications). **Headline gap.**
- **No first-class family / guardian model.** `linkedPatients` exists in the model but has no workflow. Minors: `isMinor()` exists, no guardian linkage enforced (DPDP consent for minors depends on it).

### UX
- `show.blade.php` is a 3,674-line monolith with nested Alpine scopes (fragile to edit) — maintainability + regression risk.
- `PatientProfileService::loadProfile()` **eager-loads all 10 tabs' data on every profile open** (all appointments, visits+items+implants, plans+items, every invoice+items+payments+receipts, prescriptions, full membership history, benefit logs, clinical files, family lists) — heavy as histories grow.
- Registration is a 5-tab modal; only name + mobile are truly needed at reception.
- Field-name drift: create posts `mobile`, edit posts `phone` (same column).

### Architecture
- **Validation duplicated in 4 places** with drifting field names: web `store()` (inline, `mobile`), web `update()` (inline, `phone`), API `StorePatientRequest`, API `UpdatePatientRequest`. (Write *logic* is canonical; the input *contract* is not.)
- **No per-action permissions** in `PatientController` (`grep`: none) — any `module:patients` user can delete/deactivate (password-gated, not role-gated).
- Dead views: `patients/create.blade.php` (old consultation form), orphan `patients/edit.blade.php`, retired `edit-patient-drawer.blade.php`.
- Scoping is **`branch_id` only** — not safe for a second clinic until `clinic_id` isolation (future phase).

### Security / data integrity
- `phone`/`email` stored plaintext for search → depends on encryption-at-rest; must confirm `patients:encrypt-phi` backfill ran.
- Merge gap is a **clinical-safety** risk (split allergy/medication history).

---

## 3. Approved scope for completion

1. **Duplicate Merge** — atomic, admin-only, full audit, no partial/silent merge. Master selection → field diff → moved-records preview → confirmation → transactional execute.
2. **Family / Guardian (reduced)** — guardian, mother, father, spouse, emergency contact, relationship, linked family members; minor support via guardian linkage. Deferred: family wallet/billing/membership/household accounts.
3. **Validation & Permissions** — shared Form Requests (web+API), normalise `phone`, per-action permissions.
4. **Profile refactor** — split each tab into its own component; lazy per-tab loading; isolated Alpine scope. Keep the existing UX (no visual redesign).
5. **Cleanup** — delete dead views, unify edit surface.

**Approved build order:** Design → Merge → Validation/Permissions → Family/Guardian → Profile refactor → Cleanup → Runtime test → Self-audit → Fix → Close.

**Explicitly out of Phase-1 scope:** patient portal / self-registration, `clinic_id` multi-tenant isolation, family financial features.

---

*Status: audit approved 2026-07-20. Design phase next; design to be frozen before implementation.*
