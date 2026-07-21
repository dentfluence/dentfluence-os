# Patients Module — Frozen Design

> **Status: 🔒 FROZEN (2026-07-20).** Approved by Sumit. Baseline: `docs/patients-module-audit.md`.
> Implementation follows the Inventory lifecycle: Design → build phases → Runtime test → Self-audit → Fix → Close.
> This document is the benchmark reference and implementation blueprint for the Patients module.

---

## Locked decisions

1. **Patient lifecycle** — three stages: **Appointment → Arrived → Registration.** A booked appointment does **not** create a Patient. Patient record + Patient ID + clinical file are minted **only at Registration.** (Product contract frozen now; the appointment-side decoupling is implemented in the **Appointments module (#3)**. The Patients module must be *ready* for it and must never force patient creation at booking.)
2. **Medical / dental / habits** — the **patient owns the living record** (current state, visible everywhere). Capture is optional at reception and completed/confirmed chairside. No duplicate medical store elsewhere.
3. **Duplicate Merge** — admin-only, atomic, full manifest, **archive + redirect + `merged_into` (un-merge-ready).** Never silent, never partial.
4. **Allergy / medical-alert banner** — promoted to the **always-visible profile header** (the one sanctioned visual change; clinical safety).
5. **Family / Guardian** — reduced scope (guardian, mother, father, spouse, emergency contact, relationship, linked members; minor→guardian). Family wallet/billing/membership/household accounts **deferred.**
6. **Patient Journey Timeline** — **no new Timeline tab.** Evolve the existing timeline into one universal chronological activity feed backed by the existing `ActivityEngine`/`activities`. Retire the ad-hoc `combinedTimeline`. Cross-module events light up as each module completes. (§6a.)

**Build order (approved):** Design(freeze) → **Duplicate Merge** → Validation & Permissions → Family/Guardian → Profile refactor → Cleanup → Runtime test → Self-audit → Fix → Close.

---

## 1. Patient Lifecycle Contract (the module's spine)

| Stage | Owner module | Creates | Patient ID | Clinical file |
|---|---|---|:--:|:--:|
| **1. Appointment / Enquiry** | Appointments (#3) | Appointment only (name + phone + reason on the appointment; `patient_id` nullable) | No | No |
| **2. Arrived** | Appointments (#3) triggers → Patients | If phone matches existing → auto-link. Else → launch Registration | No (until reg.) | No |
| **3. Registration** | **Patients** | **Patient record + Patient ID + clinical file** | **Yes** | **Yes** |

**No-show** → stays an appointment forever; **no Patient is ever created** (ghost-patient prevention — the core reason for this model).

**Registration is the single Patient mint point,** reachable three ways:
- **Arrival-from-appointment** (pre-filled from the appointment lead) — wired in #3.
- **Direct walk-in** (no prior booking) — built in this cycle.
- Online self-registration — *deferred.*

### Interface contract the Patients module must expose (so #3 can plug in later)
- One canonical `PatientService::register(array $input, User $actor, ?Appointment $fromAppointment = null): Patient` that: runs duplicate detection, mints the Patient ID, opens the clinical file, and (if given) links back to the originating appointment.
- **Retire the patient-minting quick-create** (`quickCreate` from the appointment modal). Booking must never mint a Patient. Any residual quick path funnels through `register()`.
- Patient ID assignment lives **only** inside `register()` — nowhere else.

---

## 2. Registration workflow

**Two-speed fields** (persona-aware):
- **Required:** First name, Last name, Mobile.
- **Progressive / optional:** contact detail, source/referral, and a clearly-labelled **"Clinical — can be completed at first visit"** section (medical alert, allergies, medical/dental conditions, habits).
- **Profile-completeness indicator** on the record so incomplete (e.g. walk-in-minimal) profiles are visible for later completion.

Duplicate detection (§3) fires at registration. On success → Patient ID minted, clinical file opened, appointment linked (if any), audit `created`.

---

## 3. Duplicate detection

Fires primarily at **arrival / registration** — the moment it matters most.

- **Primary:** exact mobile match, branch-scoped (existing `findDuplicatesByPhone`).
- **Secondary (soft hint, never blocks):** name + DOB similarity — catches the same person with a second/typo'd number.
- On a phone match, present **three explicit actions** (replacing the generic "register anyway"):
  1. **Same patient → Open existing record** (default; prevents the split).
  2. **Family member sharing this number → Register + link family** (flows into §5 family linkage).
  3. **Different person → Register anyway** (explicit override).

---

## 4. Duplicate Merge (centerpiece)

**Entry points:** patient profile ("Merge this record…"), the duplicate-detection screen ("These are the same → Merge"), and an optional admin **Potential Duplicates** report (same-phone clusters). **Admin-only** everywhere.

**Wizard:**
1. **Choose master** — select the two records and the surviving one. System *suggests* the richer/older record; staff decides.
2. **Field reconciliation** — side-by-side of differing demographics (name, DOB, gender, address, email, alt phone, source, occupation, tags); pick survivor per conflict (default = master). **Allergies + medical alerts UNION by default** — never drop a safety fact.
3. **Moved-records preview** — itemised counts of every child re-parented loser→master:
   Appointments · Consultations · Treatment Plans · Treatment Visits · Invoices/Billing (+outstanding) · Wallet (balance) · Membership · Prescriptions · Clinical Files/Media · Documents · Notes & Relationship Notes · Communications/WhatsApp · Consents & consent logs · Opportunities · Alerts · Tasks/Recall · Tags · Family links (`patient_links`) · ABHA identifiers · Voice notes · Data requests.
4. **Special-entity rules:**
   - **Wallet:** sum balances into master; write an explicit transfer ledger entry (auditable). Two wallets → one.
   - **Membership:** both active → keep later-expiry/higher-tier, **log the other** for review. Never silently drop a paid membership.
   - **ABHA / identifiers:** both verified → **block merge** (or force explicit choice). One ABHA per patient.
   - **Patient ID:** master keeps its ID; loser's ID retired and stored as `merged_from` alias so old references resolve.
   - **Outstanding balances:** combined + reconciled; shown on confirmation.
5. **Confirmation** — full manifest summary; **admin password re-entry + reason** required.
6. **Execute (atomic)** — single `DB::transaction`, both rows `lockForUpdate`. Re-parent all children by FK, apply chosen fields, transfer wallet, apply membership/ABHA rules, stamp loser `merged_into` / `merged_at` / `merged_by`, **archive (soft-delete) — never hard delete.** Write one rich manifest record, fire `PatientMerged`. Loser URL thereafter **redirects to master** with a "merged into…" banner.

**Data model (new):**
- `patients.merged_into` (nullable FK → patients), `merged_at`, `merged_by`.
- `patient_merges` table: `from_patient_id`, `into_patient_id`, `performed_by`, `reason`, `manifest` (JSON of moved counts + field choices + retired IDs), `created_at`. (Enables the future admin **un-merge**.)

**Guarantees:** atomic · admin-only · never silent · never partial · allergies never lost · financial entities transferred not dropped · reversible-ready.

---

## 5. Family & Guardian (reduced scope)

Reuse the existing **`patient_links`** table (`patient_id`, `linked_patient_id`, `relationship`, `added_by`) — no new plumbing:
- Extend the `relationship` vocabulary: **guardian, ward, mother, father, spouse, child, sibling, other.** Make links **bidirectional** (A guardian-of B ⇒ B ward-of A).
- **Emergency contact** stays as the existing patient fields (`emergency_contact_name/relationship/number`) — an emergency contact need not be a registered patient.
- **Profile "Family & Contacts" section:** emergency contact + linked members (relationship + click-through). Add / remove link.
- **Minors:** DOB < 18 → prompt **"Add guardian"** (link existing patient or capture guardian). Guardian linkage becomes the **DPDP consent anchor** for the minor.
- **Deferred:** family wallet, family billing, family membership, household accounts.

---

## 6. Profile structure (refactor — keep the UX)

No visual redesign. Same 10 tabs (profile, consultation, treatment-plan, visits, prescriptions, billing, wallet, membership, documents, notes), same look. Structural only:
- Each tab → its **own Blade component with isolated Alpine scope** (retire the 3,674-line shared-scope monolith).
- **Lazy per-tab loading:** header (identity, badges) loads instantly; each tab fetches its data only when opened (replaces `loadProfile` eager-loading all 10 tabs every time).
- **Always-visible allergy / medical-alert banner** in the header (the one sanctioned safety visual).
- **No new Timeline tab.** The existing timeline surface evolves into the Patient Journey Timeline (§6a) in place.

## 6a. Patient Journey Timeline (universal activity feed)

**Decision:** do **not** add a Timeline tab. Evolve the existing Relationship/profile timeline into the single, chronological **Patient Journey Timeline** — one trusted feed of everything that happens to a patient across every module.

**Canonical source (reuse, don't rebuild):** the existing **`activities` table + `Activity` model + `ActivityEngine`** (polymorphic `subject`/`actor`, `event`, `description`, `metadata`, `occurred_at`, keyed on the patient's `relationship_id` — every patient has exactly one). The current ad-hoc `$combinedTimeline` (consultations+visits concat, `show.blade` ~L833) is **retired** and replaced by an `ActivityEngine`-backed feed.

**Events the feed must carry** (each is a producer call `ActivityEngine::log(...)` from its owning module):
appointment booked / confirmed / arrived · registration completed · consultation completed · treatment plan created / accepted / rejected · treatment visit · clinical media uploaded · lab status update · invoice generated · payment received · wallet transaction · membership event · consent captured · note added · communication (WhatsApp / SMS / Email) · review submitted · recall sent · **duplicate merge** · patient status change · future AI-generated actions.

**Rendering:** grouped chronologically (newest first), each entry showing icon/type, title, subtitle, actor, timestamp, amount where relevant, and a deep-link to the source record. Filterable by event category. Loads lazily like other tabs.

**Scope discipline (module boundaries respected):**
- **This cycle (Patients-owned producers + the feed itself):** point the profile timeline at `ActivityEngine`; render all event types; wire the producers Patients owns — **registration completed, patient status change, note added, and `PatientMerged`** (the merge event also writes an Activity).
- **Cross-module producers** (consultation, treatment plan, visits, media, lab, invoice, payment, wallet, membership, consent, review, recall) are emitted **as each of those modules goes through its own completion cycle** — this is exactly the "missing producer calls" gap closed module-by-module. The timeline is built to *receive* them; each module lights up its own events when completed. No cross-module rebuild now.

---

## 7. Permissions (per-action)

`User::canAccess` + controller `authorize()` backstop:

| Action | Rule |
|---|---|
| View / list | `patients` (view) |
| Create / edit / register | `patients` (edit) |
| Deactivate / reactivate | edit + reason |
| Delete (soft) | edit + password + reason + role gate |
| **Merge** | **Admin only** |
| Import / export | Admin / edit |

The **merge admin-only primitive lands with the Merge phase**; the full per-action sweep is the Validation & Permissions phase.

---

## 8. Audit trail

Every state change writes actor + before/after + reason where relevant: create, update (field diffs), deactivate/reactivate, delete, **merge (rich `patient_merges` manifest)**, family-link add/remove, consent capture. Access-trail on view already exists — kept. This auditability is the module's benchmark bar.

---

## 9. Explicitly out of scope (do not build now)

Patient portal / online self-registration · `clinic_id` multi-tenant isolation · family financial features · appointment-side lead-capture UI (that is Appointments #3) · un-merge (data model made ready, feature deferred).

---

## 10. Implementation phases

1. **Duplicate Merge** ⚠️ *large* — schema (`merged_into`, `patient_merges`), merge service (atomic + special rules), wizard UI, admin permission, redirect + banner, `PatientMerged` event, audit manifest.
2. **Validation & Permissions** — shared Form Requests (web+API), normalise `phone` field, per-action `canAccess` sweep, canonical `register()` (retire patient-minting quick-create; ready the lifecycle contract).
3. **Family / Guardian** — relationship vocabulary + bidirectional links, Family & Contacts section, minor→guardian prompt + DPDP anchor.
4. **Profile refactor** ⚠️ *large* — per-tab components, lazy loading, always-visible allergy banner, **Patient Journey Timeline** (point the profile timeline at `ActivityEngine`, retire `combinedTimeline`, wire Patients-owned producers incl. `PatientMerged`).
5. **Cleanup** — delete dead views (`patients/create.blade.php`, `edit.blade.php`, `edit-patient-drawer.blade.php`), unify edit surface.
6. **Runtime test → Self-audit → Fix → Close.**

⚠️ Phases 1 and 4 are each large and will likely span multiple work sessions; each will be built as tested slices.
