# Dentfluence OS — ABDM-First Architecture Migration
## 00 · Master Index & Guiding Principles

**Status:** DESIGN / BLUEPRINT ONLY — no code changed in this phase
**Author role:** Chief Software Architect / FHIR R4 + ABDM Integration
**Created:** 2026-06-27
**Scope of this document set:** Redesign the existing Dentfluence OS architecture so that ABDM (Ayushman Bharat Digital Mission) and FHIR R4 become a *core infrastructure layer*, not a bolt-on module — while breaking nothing that works today.

> ⚠️ **Important:** This is an architecture-migration blueprint. We are **not** building ABDM APIs, Sandbox, or Production integration yet. The goal is to redesign data models, entities, settings, services and workflows so that future ABDM integration requires *minimal* code change. Actual API integration happens in later phases (see `08-ROADMAP.md`).

---

## 1. The vision in one line

> Dentfluence OS is being re-positioned from a **Dental Practice Management Software** into **India's first AI-First, ABDM-Native Dental Operating System** — a system where every clinical event is *born* as a FHIR-ready, consent-governed, ABDM-exchangeable record.

---

## 2. Five architectural principles (the non-negotiables)

1. **ABDM is infrastructure, not a feature.** No module talks to ABDM directly. Every module talks to an internal **ABDM Layer** which owns ABHA / HPR / HFR / Consent / FHIR / Exchange. If ABDM's API contract changes tomorrow, only that layer changes.
2. **FHIR is the lingua franca, generated centrally.** Business modules (Consultation, Rx, Lab…) never hand-write FHIR JSON. They emit their normal internal models; a single **FHIR Mapping Engine** converts `Internal Model → FHIR Resource → ABDM Payload`.
3. **Consent gates everything external.** No external record is ever read, written, or shown — and the AI Secretary never sees external data — without a valid, unexpired consent artefact. Consent is enforced in code, logged immutably, and revocable.
4. **Identifiers are polymorphic, never single-column.** A patient is not "one patient_id". A patient is a *bundle of identifiers* (internal ID, ABHA number, ABHA address, government ID, insurance ID…). Same for doctors (internal + HPR) and clinics (internal + HFR). We introduce a normalized identifier model so we never run another "add ABHA column" migration.
5. **Optimise for the next 15 years.** Every decision favours additive, versioned, queue-backed, audit-logged design over quick columns. Offline-first and eventual-consistency are assumed because Indian clinic connectivity is unreliable.

---

## 3. What ABDM actually requires (plain-language primer)

Dentfluence already speaks most of these concepts — it just uses local names. The migration is largely a *mapping* exercise plus a few new infrastructure layers.

| ABDM concept | What it is | Dentfluence's current local equivalent |
|---|---|---|
| **ABHA Number** | 14-digit health account number for a citizen | *(none yet)* — `patients.patient_id` is internal only |
| **ABHA Address** | human-readable health address e.g. `sumit@abdm` | *(none yet)* |
| **HPR ID** | Healthcare Professional Registry ID for a clinician | `hr_staff_profiles.license_number` (council reg.) is the closest |
| **HFR ID** | Health Facility Registry ID for the clinic | `branches.code` is the closest |
| **Consent Artefact** | signed permission to access a patient's records, with purpose + expiry | *(none yet)* |
| **Care Context** | a linkable clinical episode (a visit) | `consultations` + `treatment_visits` |
| **FHIR Bundle** | standardized clinical document (R4) | our JSON-rich `consultations`, `prescriptions`, etc. |
| **HIP / HIU role** | Health Information Provider / User (we are both) | the clinic acts as both |
| **Milestones M1/M2/M3** | ABDM certification stages | *(future — see roadmap)* |

---

## 4. The document set (read in this order)

| # | Document | What it answers |
|---|---|---|
| 00 | **This file** — Master Index & Principles | Why we're doing this, the rules of the game |
| 01 | `01-IMPACT-AUDIT.md` | **The FIRST TASK.** Every module / model / table / API / setting that must change, what changes, and why. The master blueprint. |
| 02 | `02-TARGET-ARCHITECTURE.md` | The new layered architecture, folder structure, and diagrams (data-flow, sequence, deployment) |
| 03 | `03-DATA-MODEL-AND-SCHEMA.md` | Identifier normalization, new tables, per-table modifications, ER additions |
| 04 | `04-FHIR-MAPPING-ENGINE.md` | The `Internal → FHIR → ABDM` pipeline and per-entity mapping contracts |
| 05 | `05-CONSENT-ENGINE.md` | Consent states, lifecycle, audit, and enforcement |
| 06 | `06-SYNC-ENGINE.md` | Outgoing / incoming / retry / failed queues, conflict resolution, offline sync |
| 07 | `07-SECURITY-LAYER.md` | OAuth/JWT, encryption at rest/in transit, RBAC, token rotation, digital signatures |
| 08 | `08-ROADMAP.md` | 7-phase implementation plan, non-breaking sequencing, effort & dependencies |

---

## 5. Current system at a glance (audit baseline — verified from real schema)

Dentfluence OS today is a mature Laravel application:

- **117 Eloquent models**, **267 migrations**, **110 controllers**, **~22 service domains**.
- **Patients** — `patients` table with single `patient_id` (auto-generated `DF-00142`), rich JSON clinical fields (allergies, medical/dental conditions, habits), branch-scoped.
- **Doctors/Staff** — `users` + `hr_staff_profiles` (1:1), with `license_number` (council reg) and `employee_code`.
- **Clinic** — `branches` table (id/code/name/address) + key-value `app_settings`.
- **Clinical core** — `consultations` (diagnosis, findings, ICD-10 field already present), `clinical_findings`, `treatment_plans` + `_items`, `treatment_visits` (+ procedure-specific fields & vitals), `prescriptions` + `_items` with a real CDSS (allergy/interaction/warning rules).
- **Diagnostics** — `consultation_scans` + `clinical_media` (imaging, file-path based, no DICOM), `lab_cases` + items/vendors/events.
- **Finance** — `invoices`, `invoice_items`, `invoice_payments`, `final_bills`, `receipts`, `wallets`, memberships.
- **Platform** — Sanctum API at `/api/v1/*` with a `{success,data,message}` envelope; RBAC via `roles` + `modules` + `role_module_permissions` and `User::canAccess()`; audit via `audit_logs` + `Auditable` trait; AI Secretary "Tulip" (`ai_conversations`/`ai_messages`/`ai_action_logs`, local Ollama, confirm-card for clinical/financial); unified `communication_queue`.

**Key finding:** the data model is unusually well-suited to an ABDM migration. ICD-10 fields, immutable audit logs, append-only ledgers, a tool-gated AI with action logging, soft deletes everywhere, and a versioned prescription chain already exist. The migration is therefore **mostly additive + a mapping layer**, not a rewrite.

---

## 6. Non-breaking guarantee

Every change proposed across documents 01–08 obeys these rules:

- **Additive-first.** New tables and nullable columns; we never drop or repurpose an existing column in this phase.
- **Dual-write, never break-read.** Where we normalize identifiers, the old column (`patient_id`, `license_number`, `branch.code`) keeps working and is mirrored into the new identifier table.
- **Feature-flagged.** Everything ABDM sits behind `app_settings` feature flags (`abdm_enabled`, `fhir_enabled`, `consent_required`) defaulting to **off**, so production behaviour is unchanged until you switch it on.
- **Design-only this phase.** No migrations are run, no code is shipped. These documents are the agreed contract before any code is written.

---

## 7. How to use this blueprint

1. Read `01-IMPACT-AUDIT.md` first — it is the master checklist.
2. Approve / adjust the architecture in `02`.
3. When you're ready to build, `08-ROADMAP.md` Phase 1 is the first safe, additive coding chunk (the ABDM Layer skeleton + identifier tables), which we'll do as a separate confirmed task.

> Nothing in this set should be implemented until you've reviewed it and given the go-ahead per your project's pre-flight rule.
