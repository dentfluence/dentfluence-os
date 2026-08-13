# Patients Module — Phase 3: Family / Guardian (Architecture & Design)

> **Status: 🟢 DESIGN — FREEZE-READY (accepted corrections applied).** Awaiting final approval by Sumit. No implementation code is written yet.
> Baseline: `patients-module-audit.md`, `patients-module-design.md` (§5), `patients-module-phase2-freeze.md`.
> Governs: CEO Directive #003 (quality > breadth, one canonical implementation, no future-early), Single Patient Mint Point invariant (`PatientService::register()`).
> Evidence-based from code review on 2026-07-21.

---

## Module Metadata

| | |
|---|---|
| **Module** | Patients |
| **Phase** | 3 — Family / Guardian |
| **Objective (today)** | Architecture & Design only |
| **Roadmap position (CEO #003)** | P1 — polish existing, no new modules |
| **Depends on (frozen)** | Phase 1 Merge ✅, Phase 2 Validation & Permissions ✅ |
| **Feeds** | Profile refactor (Phase 4), DPDP Consent, Membership, Billing, Patient App, PRM, AI, Timeline |

---

# PART A — ARCHITECTURAL AUDIT

## 1. Current state of patient relationships

Relationships between people in Dentfluence are **fragmented across three disconnected concepts** that were each built for a different reason and never reconciled:

| # | Concept | Where it lives | What it actually means | State |
|---|---|---|---|---|
| A | **`patient_links`** | `patient_links` pivot + `Patient::linkedPatients()/linkedByPatients()` | Intended interpersonal family graph (A is spouse/parent/child of B) | **Dormant** — table + relations + read paths exist, but there is **no write workflow anywhere** (no service method, no route, no UI to add/remove a link) |
| B | **Relationship "household"** | `relationships` spine + `patients.relationship_id` | Coincidence of a **shared phone number** — ~18 relationships group several patients under one phone | Live (read-only surface in the PRE profile) |
| C | **Family membership** | `finance_patient_memberships.family_head_membership_id`, `family_name`, `member_type` | A **billing/enrollment** grouping under one paying head | Live inside Membership; **not linked to A or B** |

The result: the app can *display* families three different ways but has **no single, authoritative answer to "who is this person's family?"** and **no way for staff to record it deliberately.** Family is currently inferred (shared phone) or typed as a free-text note (`patients.family_notes`, e.g. *"Mother also a patient, ID#123"*), never structured.

**Guardian** is a fourth, separate story: it exists only as **free text on the consent record** (`patient_consents.guardian_name`, `guardian_relationship`, `on_behalf_of`) captured at consent time. It is **not** connected to any patient record, so a minor's guardian is re-typed on every consent and can never be validated, reused, or reported on.

## 2. Existing tables and models

**`patient_links`** (migration `2026_06_02_000002`)

```
id, patient_id (FK→patients, cascade), linked_patient_id (FK→patients, cascade),
relationship VARCHAR(50) nullable  [free text — "Husband, Wife, Son…"],
added_by (unsignedBigInteger, nullable), timestamps
UNIQUE(patient_id, linked_patient_id)
```

- **Directional single-row model.** One row per link, owned by `patient_id`. Reads UNION both directions (`linkedPatients` + `linkedByPatients`). There is **no reciprocal row and no inverse label** — `relationship` is stored once, from one side's perspective, and is ambiguous (is "Son" *this patient's* son, or *is* this patient the son?).
- **No `PatientLink` model.** Accessed only through the two `belongsToMany` relations on `Patient` and via raw `DB::table('patient_links')` in the merge service.
- **No controlled vocabulary, no type, no guardian flag, no validity/consent metadata.**

**`patients`** relevant scalar columns
- `emergency_contact_name`, `emergency_contact_relationship`, `emergency_contact_number` (number encrypted). Emergency contact is a *contact*, not necessarily a patient — correct as-is.
- `nominee_name`, `nominee_relationship`, `nominee_contact` (DPDP nominee, migration `2026_06_27_120002`).
- `family_notes` (free text), `referred_patient_id` (referral chain, separate graph), `relationship_id` (→ household spine).
- `date_of_birth`, `age_years`, `dob_unknown` → drive `ageInYears()` and `isMinor()` (`< 18`).

**`patient_consents`** — `on_behalf_of` (`self|guardian`), `guardian_name`, `guardian_relationship` (free text, migration `2026_06_27_110001`).

**`finance_patient_memberships` / `finance_membership_plans`** — `member_type` (`individual|head|addon`), `family_head_membership_id`, `family_name`, plan `family_option` (`none|addon|bundle`).

## 3. Existing services

- **`PatientService`** — the canonical patient read/write engine. Touches family **only for filtering**: `family=has_family|no_family` via `whereHas('linkedPatients')`. **No `addLink`, `removeLink`, `attachGuardian`, or any relationship write.** `register()` accepts `family_notes` but nothing structured.
- **`PatientMergeService`** — the *only* code that writes `patient_links`: on merge it re-parents loser→master rows in both directions, de-dupes, and drops self-links (`PatientMergeManifest` counts `patient_links`). This is important: **any Phase 3 schema change to `patient_links` must be reflected in the merge re-parenting logic**, or merges will corrupt or orphan links.
- **`ConsentService`** — persists guardian free-text onto the consent; no lookup against a real guardian record.
- **`RelationshipBackfillService` / `PatientRelationshipLinker`** — maintain the household/PRE spine (concept B), independent of `patient_links`.
- **No family/guardian domain service exists.** This is the central build gap.

## 4. Existing APIs

- **`PatientResource`** (`/api/v1/patients`) exposes `emergency_contact_*`, `nominee` (not shown but present), and `family_notes` — but **no linked-members array, no guardian, no is_minor/guardian-required flag.**
- **No family endpoints** (no add/remove link, no household fetch) on web or API. Routes grep is empty for family/guardian/link writes.
- Mobile parity: nothing to port yet — the feature isn't built.

## 5. Existing UI

- **Registration modal** (`add-patient-modal.blade.php`) captures `emergency_contact_*` and a free-text `family_notes` box (*"e.g. Mother also a patient, ID#123"*). No structured linking, no guardian capture, no minor prompt.
- **Duplicate-detection screen** (design §3) already contemplates a *"Family member sharing this number → Register + link family"* action — but the linking half was never built, so that branch currently has nowhere to go.
- **PRE / Relationship profile** (`relationship/profile/index.blade.php`) is the only place family is *shown*: a **Household panel** (concept B) and an **Extended Family** list (concept A, `linkedPatients ∪ linkedByPatients` minus household). Read-only; no add/remove.
- **Patient profile** (`patients/show.blade.php`, the 3,674-line monolith) shows a *"family patient"* tag chip but **no Family & Contacts section.** The design (§5) calls for one; it does not exist yet.
- **Consent screen** (`consent/patient.blade.php`) prompts for guardian name/relationship **only as free text** when `isMinor()`.

## 6. Current strengths

- **The pivot and both relations already exist and survive merge** — no new plumbing needed for the basic link store (matches design §5 intent).
- **Emergency contact and nominee are correctly modelled as contacts, not forced patient records** — an emergency contact need not exist in the system.
- **`isMinor()` is centralised and DPDP-aware** — one trustworthy predicate to hang the guardian gate on.
- **Merge already treats `patient_links` as first-class** (re-parents, de-dupes, drops self-links) — the reversibility/no-data-loss bar is already met for links.
- **Canonical `register()` mint point** — guardian-as-new-patient can reuse it without inventing a second creation path (invariant safe).
- **A single Relationship per patient** gives every patient a stable spine to hang household/timeline events on.

## 7. Current weaknesses

- **No write workflow** — the family graph is unreachable by staff. The feature is effectively 0% built despite the table existing.
- **Ambiguous, un-typed relationship label** — free `VARCHAR(50)`, single-sided, so "Son"/"Father" can't be interpreted, reversed, validated, or aggregated.
- **Three disconnected family concepts** (patient_links / household / membership) with no canonical reconciliation — a real maintainability and "which is the truth?" risk.
- **Guardian is free text on consent, divorced from the patient graph** — re-typed every time, un-reusable, un-auditable, can't drive a minor gate or a household consent view.
- **No minor→guardian enforcement** — `isMinor()` exists but nothing requires or prompts a guardian; DPDP lawful-basis-for-minors depends on it.
- **Family visible only in PRE**, not on the clinical patient profile where reception/dentist work.
- **No activity/audit producer** for link add/remove (Phase 4 timeline & the module's audit bar both expect one).

## 8. Missing features

Structured, typed, bidirectional family links · guardian designation (a typed link, not free text) · minor→guardian prompt & (soft) gate · a Family & Contacts section on the patient profile · add/remove-link workflow (with duplicate-screen "link family" entry finally wired) · guardian-aware consent (prefill/validate against the real guardian) · API/mobile exposure of linked members & `guardian_required` · a canonical reconciliation of household vs links vs membership family.

## 9. Data model improvements

Recommended (detail in Part B §D):

1. **Add a controlled `relationship_type` (enum-backed string)** to `patient_links`, replacing reliance on free text; **backfill** the legacy `relationship` free-text column into it and stop writing the legacy column (kept read-only until a future removal).
2. **Add `is_guardian` (bool)** as the *single* representation of legal/consent guardian authority — a capacity flag on the link (distinct from the biological/social `relationship_type`), and the DPDP consent anchor. `ward` is always derived, never stored.
3. **Keep the single-row directional model; add a canonical inverse map in the service** so the correct label is *derived* per viewer (avoids reciprocal-row dual-write drift — see §11/§Risks).
4. Optional `notes` on the link (e.g. "primary guardian", "non-custodial").
5. Introduce a thin **`PatientLink` Eloquent model** so links are auditable/observable and the merge service can move off raw `DB::table`.
6. **Do NOT** collapse household (B) or family-membership (C) into `patient_links`. Reconcile by *reference*, not by merge (see §13).

## 10. Future compatibility

| Surface | How Phase 3 must stay compatible | Design consequence |
|---|---|---|
| **Membership** | Family membership (concept C) already groups payers via `family_head_membership_id`. Phase 3 must **not** duplicate or fight it. | The family **graph** (who is related) is Patients-owned; the family **billing group** stays Membership-owned. A future "enroll this patient's family" can *read* the graph to suggest members — read-only, deferred. |
| **Billing** | Family wallet/billing is **explicitly deferred** (design §5, §9). | Phase 3 stores relationships only. No `bill-to guardian`, no shared wallet. Leave a clean seam (guardian link) for a future "responsible party". |
| **Patient App** | App will need "my family" and guardian-of-minor views. | Expose linked members + `is_minor`/`guardian_required` via `PatientResource`/API from day one (read); writes deferred. |
| **DPDP Consent** | Minor consent (5.5) and nominee (5.2) are law-driven. Guardian is currently free text. | Make the **guardian link the consent anchor**: consent prefill/validate against the linked guardian; free-text stays as fallback for non-patient guardians. |
| **PRM** | PRE shows household + extended family; every patient has one Relationship. | Keep the graph patient-level; PRE keeps reading it. Do not move family truth into the Relationship spine. |
| **AI** | Copilot/AI wants structured relationships ("call the mother", "guardian for consent"). | Typed `relationship_type` + guardian flag makes the graph machine-readable. Free text does not. |
| **Timeline** | Phase 4 universal `ActivityEngine` feed. | Emit `family.link_added` / `family.link_removed` / `guardian.assigned` activities from the new service so the feed lights up when Phase 4 lands. |

## 11. Edge cases

- **Self-link** (A↔A) — must be rejected (merge already drops these).
- **Duplicate link** in reverse (A→B exists, someone adds B→A) — enforced at the **database** level by the functional unique index on `(LEAST(patient_id, linked_patient_id), GREATEST(patient_id, linked_patient_id))` (MySQL 8.0), with the service's both-directions check as defence-in-depth.
- **Reciprocal-label correctness** — A is "father of" B ⇒ B is "son/daughter of" A. Needs an inverse map + gender/age awareness (child vs son/daughter).
- **Minor with no guardian in system** (guardian isn't a patient) — must allow a free-text guardian fallback (don't force creating a patient record).
- **Guardian who is also a minor** — block or warn.
- **Multiple guardians** (divorced parents) — allow >1 guardian link; consent picks one.
- **Minor ages out to 18** — guardian gate must lift automatically (derived from `isMinor()`, never a stored "is_minor" flag).
- **DOB unknown / age-only patients** — `isMinor()` handles via `age_years`; guardian prompt keyed on `isMinor()`, not raw DOB.
- **Merge collisions** — merging two patients who each link to the same third person; merging a guardian into a ward. Merge service must de-dupe and preserve guardian flags.
- **Deleting/deactivating a linked patient** — cascade drops the link row (FK cascade); guardian of a minor being soft-deleted should warn.
- **Household vs link overlap** — a person in the same household (shared phone) *and* an explicit link: show once, don't double-count (PRE already rejects household ids from extended family).
- **Cross-branch family** — links must ignore `BranchScope` (family crosses branches; PRE already reads household branch-scope-free).

## 12. Risks

| Risk | Severity | Mitigation |
|---|---|---|
| **Merge corruption** — schema change to `patient_links` not reflected in `PatientMergeService` re-parenting | High | Update merge + `PatientMergeManifest` in the same slice; extend merge smoke tests to cover typed links & guardians |
| **Reciprocal dual-write drift** if we store two rows per link | Medium | Keep single-row model; derive inverse in service (chosen approach) |
| **Concept sprawl** — a 4th family concept instead of reconciling 3 | Medium | Explicitly declare `patient_links` the canonical *interpersonal* graph; household = phone coincidence; membership = billing. Documented in §13 |
| **Guardian free-text vs linked-guardian divergence** | Medium | Consent reads linked guardian first, free text only as fallback; never silently disagree |
| **Invariant breach** — guardian-as-new-patient bypassing `register()` | High | Guardian creation routes through `PatientService::register()`; `patients:invariant-check` stays green |
| **Scope creep into billing/wallet** | Medium | Hard-hold family financials (design §9); Phase 3 stores relationships only |
| **PHI in link notes** | Low | Link `notes` is operational, not clinical; no new PHI column |

## 13. Recommended architecture (summary — full spec in Part B)

**One sentence:** make `patient_links` the **canonical interpersonal family graph** — typed, bidirectional-by-derivation, guardian-aware — owned by a new thin `FamilyLinkService`, surfaced on the patient profile and API, wired as the DPDP guardian anchor; **household stays a phone coincidence and membership stays a billing group**, each *referenced*, never merged.

Three concepts, three owners, one reconciliation rule:

```
patient_links   → "who is related to whom"     (Patients-owned, canonical)   ← Phase 3 builds this
relationship_id → "who shares a phone/household" (PRM-owned, inferred)        ← read-only, unchanged
family membership → "who pays under one head"    (Membership-owned, billing)  ← untouched, may read the graph later
```

## 14. Recommended implementation order

Design-only now. When approved, build as small tested slices in this order:

1. **Schema + model** — `relationship_type`, `is_guardian`, `notes`; `PatientLink` model; update `PatientMergeService`/manifest; migration additive.
2. **`FamilyLinkService`** — canonical `addLink / removeLink / linksFor / attachGuardian`, reciprocity + inverse-label map, self/dup guards, activity + audit producers. Guardian-as-patient routes through `register()`.
3. **Profile "Family & Contacts" section** — read + add/remove (existing `patients/show` for now; folds into Phase 4 component). Wire the duplicate-screen "link family" entry.
4. **Minor → guardian prompt + soft gate** — on registration/profile when `isMinor()`; DPDP consent anchor (consent reads linked guardian).
5. **API/mobile read exposure** — linked members + `guardian_required` on `PatientResource`.
6. **Runtime verify → self-audit → regression (merge!) → freeze.**

---

# PART B — PHASE 3 DESIGN DOCUMENT

## A. Scope

**In scope:** typed bidirectional family links (biological/social: mother, father, spouse, child, sibling, other) with a separate `is_guardian` capacity flag (ward derived); a canonical `FamilyLinkService`; a Family & Contacts section on the patient profile; minor→guardian prompt and DPDP consent anchoring; read-only API/mobile exposure of linked members and guardian-required state; reconciliation of the three family concepts by reference.

**Out of scope (deferred, do not build):** family wallet, family billing / responsible-party billing, family membership *enrollment* changes, household-account merging, self-service family management in the patient app (write), un-family (bulk unlink). Household (concept B) and membership family (concept C) are **not modified** — only referenced.

## B. Design principles (from Dentfluence philosophy)

- **Less data entry** — link an *existing* patient by search first; only capture a new guardian when they aren't in the system; derive the inverse label instead of asking for it.
- **More intelligent processing** — reciprocity, minor detection, and consent anchoring are computed, not re-entered.
- **Progressive disclosure** — Family & Contacts is a compact section; the guardian prompt appears only for minors; the household/membership cross-references appear only when they exist.
- **No duplicate business logic** — one `FamilyLinkService`; guardian creation reuses `PatientService::register()`; consent reads the guardian link rather than storing a parallel truth.
- **Canonical services** — `patient_links` is the single family graph; `FamilyLinkService` is the single writer.
- **Long-term maintainability** — typed vocabulary + a real model + merge integration + activity producers, so the graph stays clean as data grows.

## C. Canonical model of "family"

| Concept | Owner | Source of truth for | Phase 3 action |
|---|---|---|---|
| Interpersonal relationship | **Patients** (`patient_links`) | "X is the mother/spouse/sibling of Y" (with an `is_guardian` capacity flag) | **Build** — typed, guardian-aware, service-owned |
| Household (shared phone) | PRM (`relationship_id`) | "these records share a contact number" | **Reference only** (PRE already shows it) |
| Family membership | Membership (`family_head_membership_id`) | "who is billed under one head" | **Reference only** (may later *read* the graph to suggest members) |
| Emergency contact | Patient scalar fields | "who to call in an emergency" (not necessarily a patient) | **Keep as-is** |
| Nominee | Patient scalar fields | DPDP nominee | **Keep as-is** |

**Rule:** a person may appear in more than one concept; the profile shows each once and labels its origin. We never collapse them into a single table.

## D. Data model

**Extend `patient_links` (additive migration — safe):**

| Column | Type | Notes |
|---|---|---|
| `relationship_type` | `string(30)` (app-level enum) | Biological/social relationship **only**: `mother, father, spouse, child, sibling, other`. (Guardianship is **not** a relationship type — see `is_guardian`; `ward` is derived.) Nullable during backfill, then default `other`. |
| `is_guardian` | `boolean` default `false` | The **single** representation of legal/consent guardian authority (the DPDP anchor) — a capacity flag orthogonal to `relationship_type`, so a `mother`/`father`/other link can *also* be the consent guardian. `ward` is the derived inverse of a guardian link and is never stored. |
| `notes` | `string(150)` nullable | Operational note ("primary guardian", "non-custodial"). Non-clinical. |

- **Backfill then retire the legacy label column.** Migrate existing `relationship VARCHAR(50)` values into `relationship_type` in the same additive migration; thereafter the service **stops writing** `relationship` and it is kept **read-only** until a future removal. One active label column — no dual-write.
- **Keep** the single-row directional model. Dentfluence's production database is **MySQL 8.0** (Docker `mysql:8.0`), so a **functional unique index** on `(LEAST(patient_id, linked_patient_id), GREATEST(patient_id, linked_patient_id))` enforces **one link per patient pair at the database level** — closing the reverse-direction (A→B / B→A) gap that `UNIQUE(patient_id, linked_patient_id)` alone cannot. The service-level both-directions check remains as defence-in-depth.
- **New `PatientLink` Eloquent model** (`Auditable`), so writes are audited/observable and `PatientMergeService` can migrate off raw `DB::table` over time (not required in slice 1, but the model lands in slice 1).

**No changes to** `patients`, `patient_consents`, or membership tables in Phase 3. The guardian *anchor* is a read from `patient_links`; consent keeps its free-text columns as the fallback for non-patient guardians.

**Inverse-label map (service constant, not stored):**

```
mother  → child        father  → child
child   → (parent: mother/father by the parent's gender, else "parent")
spouse  → spouse        sibling → sibling
other   → other
```

Gender/age refine "child" into son/daughter and "parent" into mother/father at render time from the counterpart's own fields — never stored on the link. **Guardian/ward** are not relationship types: "guardian" is the `is_guardian` flag on the link, and "ward" is its derived inverse (the counterpart of a guardian link) — both computed, never stored as `relationship_type`.

## E. Services

**`FamilyLinkService` (new, canonical writer):**

- `linksFor(Patient): Collection` — the **pure relationship graph**: all links (both directions), each resolved to `{counterpart, relationship_type, label_from_this_side, is_guardian, origin}` with the inverse map applied. **No household de-duplication here** — that is a presentation concern owned by the consumer (e.g. PRE's ProfileController filters household ids in its own view). This is the single canonical graph reader; ProfileController's inline extended-family computation is refactored to call it.
- `addLink(Patient $a, Patient $b, string $type, array $opts, User $actor): PatientLink` — guards: reject self-link, reject if a link already exists in *either* direction (update instead), validate `$type` against the biological/social vocabulary. Sets `is_guardian` from `$opts['as_guardian']` (guardianship is a capacity flag, never a `relationship_type` value). Writes audit + `ActivityEngine` `family.link_added`.
- `removeLink(...)` — soft/hard per audit policy; writes `family.link_removed`.
- `attachGuardian(Patient $minor, Patient|array $guardian, ...)` — runs inside a **single `DB::transaction`**: if `$guardian` is new-person input, **route creation through `PatientService::register()`** then create the link; if `$guardian` is an existing patient, create the link directly. The link carries the biological/social `relationship_type` (or `other`) with `is_guardian=true`. Wrapping creation and linking in one transaction prevents an orphaned guardian patient on partial failure.
- `guardiansFor(Patient): Collection` and `wardsFor(Patient): Collection` — used by consent + minor gate.

**`PatientService`** — unchanged as the mint point; gains no family write logic beyond optionally delegating a "link family" branch from the duplicate screen to `FamilyLinkService`. `family=has_family|no_family` filter stays.

**`ConsentService` / `ConsentController`** — when `isMinor()`, **prefill** guardian name/relationship from `guardiansFor($patient)->first()` if a guardian link exists; free text remains editable and is the fallback when no linked guardian. No schema change; consent still stores its own snapshot (lawful record of who consented at that moment).

**`PatientMergeService` + `PatientMergeManifest`** — extend the existing `patient_links` re-parenting to carry `relationship_type`/`is_guardian`, with a **fully specified conflict-resolution rule** so a merge is never silent or lossy:

- **Re-parent** all of the loser's links to the master (both directions), then reconcile against the ordered-pair uniqueness.
- **On a duplicate pair (master and loser both link to the same counterpart):** merge into one row — `is_guardian = master.is_guardian OR loser.is_guardian` (**guardian authority is never dropped**); `relationship_type` prefers the more specific value over `other`, else keeps the master's; `notes` keeps the master's, else the loser's.
- **Guardian↔ward collapse guard:** if the merge would make a guardian and their ward the same person, the resulting self-link is dropped (as today) **only after** re-homing the guardianship — i.e. the merge **must not** silently remove a minor's only guardian; if the collapse would leave a minor with no guardian, the merge is **blocked** with an explicit reason (consistent with merge's "never silent, never partial" invariant).
- **Manifest** records every link change explicitly (re-parented, merged-duplicate with the chosen values, dropped self-link, preserved guardian flags), so the action is fully auditable and reversible-ready.
- **Extend `patients:merge-smoketest`** to cover: typed-link re-parenting, duplicate-pair reconciliation (guardian OR-merge, specific-over-`other`), and the guardian↔ward guard (both the re-home and the block paths).

## F. UI

**Patient profile — new "Family & Contacts" section** (compact, information-dense, in `patients/show.blade.php` now; becomes its own component in Phase 4):

- **Linked members** — list of `linksFor()`: name · relationship label (from this patient's side) · age/minor badge · guardian badge · click-through. **Add link** (search existing patient → pick relationship type) and **Remove**.
- **Guardian block** (only if `isMinor()`) — shows linked guardian(s) or a **"Add guardian"** prompt (link existing patient or capture guardian; capture routes through `register()`).
- **Emergency contact** and **Nominee** — existing scalar fields, shown here (read/edit), consolidating "who to contact" in one place.
- **Cross-references (read-only, only when present):** "Shares household (phone) with…" (concept B) and "Family membership: <family_name>" (concept C) — labelled by origin, not editable here.

**Registration modal** — keep `emergency_contact_*`; upgrade `family_notes` free box with an optional **"Link a family member"** (search + type) that calls `FamilyLinkService`. For minors, show the **guardian prompt** inline. Progressive: nothing new is forced for a normal adult walk-in.

**Duplicate-detection screen** — finally wire the existing *"Family member sharing this number → Register + link family"* action to `register()` + `FamilyLinkService::addLink()`.

## G. API / Mobile (read-only in Phase 3)

- The **detail** `PatientResource` gains `linked_members` (`[{id, name, relationship, is_guardian, is_minor}]`), `is_minor`, and `guardian_required`. `linked_members` is exposed via `whenLoaded('links')` on the **detail endpoint only** — never on list/collection responses — to avoid N+1 on patient lists. There is **no separate `guardians` array**: guardians are simply the `linked_members` entries where `is_guardian = true` (single representation), and `guardian_required = is_minor && no linked member has is_guardian`. Writes deferred; the app can render "my family" and the guardian-needed state.

## H. Permissions (reuse Phase 2 model)

| Action | Rule |
|---|---|
| View family/links | `patients` (view) |
| Add / remove link, attach guardian | `patients` (edit) |
| (Merge of linked records) | `admin.only` (unchanged) |

Form Request `authorize()` + controller `abort_unless` backstops, consistent with Phase 2.

## I. Audit & Timeline

Every link add/remove and guardian assignment writes actor + before/after (Phase 2 audit bar) **and** an `ActivityEngine` activity (`family.link_added`, `family.link_removed`, `guardian.assigned`) keyed on the patient's `relationship_id`, so Phase 4's universal timeline lights these up with no rework.

## J. Edge-case handling (design decisions)

Self-link rejected; bidirectional duplicate detected before insert (check both directions); inverse label derived (gender/age-refined at render); non-patient guardian allowed via free text; guardian-who-is-a-minor blocked with a warning; multiple guardians allowed (consent selects one); minor→adult gate lifts automatically via `isMinor()`; DOB-unknown handled through `age_years`; merge de-dupes typed links and preserves guardian flags; FK cascade on delete, with a warning when soft-deleting a minor's only guardian; household/link overlap shown once; links read branch-scope-free.

## K. Explicitly NOT in Phase 3

Family wallet · family/responsible-party billing · membership family enrollment changes · household-account merge · patient-app family *writes* · un-family bulk tools · storing reciprocal rows · a stored `is_minor` flag · any change to household (B) or membership (C) ownership.

## L. Implementation slices (build only after approval)

1. **Schema & model** — additive migration (`relationship_type`, `is_guardian`, `notes`; backfill legacy `relationship` → `relationship_type` then stop writing it; functional unique index on the ordered `(LEAST, GREATEST)` pair), `PatientLink` model, `PatientMergeService`/manifest update, merge smoke-test extension.
2. **`FamilyLinkService`** — canonical writer, inverse map, guards, audit + activity producers; guardian creation via `register()`.
3. **Profile Family & Contacts** — read + add/remove; duplicate-screen "link family" wired.
4. **Minor → guardian** — prompt + soft gate; consent anchor (consent prefills from linked guardian).
5. **API/mobile read** — `PatientResource` linked members + `guardian_required`.
6. **Runtime verify → self-audit → regression (merge + consent) → Phase 3 Freeze doc.**

## M. Verification plan (for the build phase)

- **Automated:** `family:link-smoketest` (add/remove, self-link reject, bidirectional dup, inverse label, guardian-as-patient via `register()`), extend `patients:merge-smoketest` for typed/guardian links, `patients:invariant-check` stays green.
- **Manual (Tulip):** link two existing patients; add guardian for a minor; register a minor and get the guardian prompt; capture a consent for a minor and see guardian prefilled; merge two patients who share a linked relative; verify family shows on profile and API.

## N. Open questions for review (decide before freeze)

1. **`is_guardian` stored vs derived** — ✅ **Locked: stored** as the single guardian representation (a `mother` link can also be the consent guardian); `ward` derived, never stored.
2. **Reciprocal rows vs single-row + inverse map** — ✅ **Locked: single-row + derived inverse** (no dual-write drift), with a DB-level functional unique index on the ordered pair enforcing one row per pair.
3. **Guardian fallback** — keep consent free-text guardian for non-patient guardians? Recommend **yes**. Confirm.
4. **Registration-time linking** — build the inline "link a family member" now, or defer to profile-only for slice 3? Recommend **profile-only first**, registration link in a later slice to keep slice 3 small.

---

## Freeze checklist

- [ ] Architecture reviewed & approved by Sumit
- [ ] Open questions (§N) decided
- [ ] Then: implement slices L1–L6, each tested, then write `patients-module-phase3-freeze.md`

**Nothing is implemented until this design is approved.**
