# 01 · Exhaustive ABDM Impact Audit
### The FIRST TASK — every module, model, table, API & setting that must change

**Status:** DESIGN ONLY. Nothing here is implemented. This is the master blueprint every other document and future code is built against.
**Method:** Grounded in the *real* schema extracted from `database/migrations` and `app/Models` (verified, not assumed).
**Legend — Impact:** 🔴 High (new infra / identity) · 🟠 Medium (additive fields + mapping) · 🟢 Low (config / cosmetic).
**Legend — Type:** `ADD` new table/field · `MAP` FHIR mapping only · `GATE` consent/security gate · `FLAG` feature flag · `SVC` service-layer change.

---

## How to read each module block

Each module lists:
- **Current state** — what exists today (real tables/columns).
- **FHIR R4 target** — the resource(s) this module maps to.
- **Required changes** — concrete, additive modifications.
- **Why** — the ABDM/clinical rationale.

A consolidated change-register table sits at the end (§ 31).

---

# PART A — IDENTITY & FACILITY CORE
*(the foundation everything else hangs off)*

## 1. Patient 🔴
**Current:** `patients` — single `patient_id` (`DF-00142`), `name`/`first/middle/last`, `date_of_birth`+`dob_unknown`+`age_years`, `gender` (male/female/other), `phone`/`alternate_phone`/`email`, address (`address`,`area`,`city`,`state`,`pincode`), `emergency_contact_*`, JSON `allergies`/`medical_conditions`/`dental_conditions`/`habits`, `source`/referral fields, denormalized financials, `branch_id`. **No ABHA fields.**

**FHIR R4 target:** `Patient` resource. Identifiers → `Patient.identifier[]`; emergency contact → `Patient.contact[]`; language → `Patient.communication[]`.

**Required changes:**
- `ADD` **`patient_identifiers`** table (polymorphic identity) — see `03-DATA-MODEL`. Holds: internal ID, **ABHA Number (14-digit)**, **ABHA Address**, government ID mapping, insurance ID, FHIR logical ID. Replaces the "one number" assumption *without removing* `patient_id`.
- `ADD` to `patients` (nullable, additive): `abha_number`, `abha_address`, `abha_verification_status` (enum: `unlinked/pending/verified/failed/revoked`), `abha_linked_at`, `preferred_language` (ISO 639), `fhir_resource_id` (UUID), `gov_id_type`+`gov_id_last4` (never store full Aadhaar — only reference/last-4, encrypted).
- `ADD` **`patient_consents`** linkage (Consent Engine, doc 05) + **`patient_external_records`** (linked records pulled from HIE).
- `GATE` reads of any external record behind consent.
- `MAP` PatientMapper in FHIR engine.

**Why:** ABHA is the citizen's portable identity; a patient may present an ABHA number, an ABHA address, or neither. We must store, verify, and link — and support *multiple* identifiers per person. `gender=other` must also map to FHIR `administrative-gender` (`male/female/other/unknown`). Preferred language drives patient-facing Rx/notes (already multi-lingual in Rx).

## 2. Doctor / Practitioner 🔴
**Current:** `users` (auth, `role`/`role_id`, `branch_id`, `designation`) + `hr_staff_profiles` (`license_number` = council reg, `license_expiry`, `qualification`, `specialization`, `employee_code`). **No HPR.**

**FHIR R4 target:** `Practitioner` (the person + qualifications) + `PractitionerRole` (their role at a facility).

**Required changes:**
- `ADD` to `hr_staff_profiles` (nullable): `hpr_id`, `hpr_verification_status`, `hpr_linked_at`, `medical_council_name`, `registration_year`, `digital_signature_ref` (pointer to secure store, not the key), `fhir_practitioner_id`.
- `ADD` **`practitioner_identifiers`** table (mirror of patient pattern) for internal ID / HPR ID / council reg.
- `ADD` `practitioner_qualifications` rows (or keep single + structured) so multiple qualifications/registrations map to `Practitioner.qualification[]`.
- `MAP` PractitionerMapper + PractitionerRoleMapper (role + facility association).

**Why:** Every ABDM clinical document must be *attributed* to a verified clinician (HPR). The digital signature underpins legally valid health records. We separate *person* (Practitioner) from *role-at-facility* (PractitionerRole) per FHIR.

## 3. Clinic / Facility 🔴
**Current:** `branches` (`code`, `name`, `address`, `city`, `state`, `phone`, `email`, `is_active`) — **no model class**, **no HFR**, key-value `app_settings` not branch-scoped.

**FHIR R4 target:** `Organization` + `Location`.

**Required changes:**
- `ADD` **`Branch` Eloquent model** (currently migration-only) — needed for relationships and FHIR mapping.
- `ADD` to `branches` (nullable): `hfr_id`, `facility_verification_status`, `facility_type` (clinic/hospital/diagnostic), `organization_mapping_id`, `geo_lat`/`geo_lng`, `fhir_organization_id`, `digital_certificate_ref`.
- `ADD` **`facility_abdm_config`** table (per-branch ABDM config: HIP id, HIU id, endpoints, key references, consent defaults) — *credentials are placeholders/references only this phase*.
- `ADD` `branch_id` to `app_settings` (or a `branch_settings` layer) so ABDM config is per-facility.
- `MAP` OrganizationMapper + LocationMapper.

**Why:** ABDM links every record to a registered facility (HFR). Geo-coordinates + facility type are HFR registration requirements. Per-branch config is mandatory because each facility has its own HFR/HIP identity.

## 4. Authentication 🟠
**Current:** Sanctum tokens; `POST /api/v1/auth/login` → Bearer; `users.password` hashed; `is_active`, `last_login_at`.

**FHIR/ABDM target:** N/A (FHIR has no auth resource) — but ABDM APIs need **OAuth2 client-credentials** + facility tokens.

**Required changes:**
- `ADD` ABDM gateway auth as a *separate* credential set in the Security Layer (doc 07) — never reuse user Sanctum tokens for ABDM.
- `ADD` token storage + rotation table `abdm_access_tokens` (short-lived gateway tokens, encrypted).
- `SVC` introduce an `AbdmAuthManager` (issues/refreshes ABDM gateway tokens) distinct from user auth.
- `FLAG` keep existing user login untouched.

**Why:** ABDM Gateway uses its own OAuth2 session tokens with short TTL; these must rotate and be isolated from clinic user sessions.

## 5. Roles & Permissions 🟠
**Current:** `roles` + `modules` + `role_module_permissions` pivot; `User::canAccess(module, action)`; legacy `role` string.

**Required changes:**
- `ADD` new modules to the `modules` table: `abdm`, `consent`, `health_exchange`, `fhir`, so RBAC can gate the new screens.
- `ADD` granular permissions: who can *initiate consent requests*, *view external records*, *link ABHA*, *configure HFR/HIP*. These are sensitive and should default to admin/doctor only.
- `SVC` add a `canAccessExternalRecords()` helper that combines RBAC **and** a valid consent (defense in depth).

**Why:** Accessing another facility's records via HIE is high-privilege; RBAC must cover the new surface area, and external-record access needs both a role *and* a consent.

---

# PART B — CLINICAL ENCOUNTER CORE
*(the records that actually become ABDM health documents)*

## 6. Consultation → Encounter 🔴
**Current:** `consultations` — `status` (draft/completed), `consultation_type` (new/followup/same_issue/recall_6m/emergency/coha), `visit_type`, `chief_complaint`, `complaint_duration`, `severity`, HOPI (`hopi_auto`/`hopi_final`), diagnoses (`provisional/differential/primary/secondary`), **`diagnosis_icd_code` already present**, `diagnosis_risk`, JSON `clinical_data`/`chart_data`/`radio_data`/`investigations`, `doctor_id`, `branch_id`, `appointment_id`. Plus `clinical_findings` child table.

**FHIR R4 target:** **`Encounter`** (the visit) — and it becomes the ABDM **Care Context** that gets linked to the patient's ABHA.

**Required changes:**
- `ADD` to `consultations` (nullable): `encounter_status` (FHIR: planned/arrived/in-progress/finished/cancelled — mapped from existing status), `encounter_class` (AMB/EMER for ambulatory/emergency), `care_context_reference` (the ABDM link id), `fhir_encounter_id`.
- `MAP` EncounterMapper: existing `consultation_type`/`visit_type` → FHIR class + type codes; `diagnosis_*` → `Encounter.diagnosis` + `Condition` resources; `doctor_id` → participant (Practitioner); `branch_id` → serviceProvider (Organization).
- `ADD` "Care Context creation" hook: when a consultation is `completed`, queue a Care Context link (Sync Engine) so the visit becomes discoverable on ABDM (consent-gated, flagged off by default).
- `MAP` chief complaint / HOPI → `Condition` + `Observation`.

**Why:** In ABDM, a *visit* is the linkable unit ("Care Context"). Consultation is already our visit object and even carries ICD-10 — it maps cleanly to `Encounter`. We add status/class normalization and the care-context reference, nothing destructive.

## 7. Clinical Notes / Findings 🔴
**Current:** `clinical_findings` (soft_tissue, caries, periodontal, BOP, plaque_index, occlusion, tmj, `chart_data` JSON) + `consultations` HOPI/findings-summary fields.

**FHIR R4 target:** `Composition` (the structured clinical document) + `DocumentReference` (the rendered/stored doc) + `Observation` (each discrete finding).

**Required changes:**
- `ADD` `fhir_composition_id` on the consultation; `ADD` an **`fhir_documents`** table (one row per generated FHIR document/Bundle: type, version, hash, storage ref, sign status) — see doc 04.
- `MAP` each finding field → an `Observation` with a LOINC/SNOMED code (mapping table in doc 04); the consultation as a whole → `Composition` of type "Dental/Clinical note"; the printable PDF → `DocumentReference`.
- `SVC` a `ClinicalDocumentBuilder` that assembles the OP Consultation Bundle (ABDM health-record type) from Encounter + Conditions + Observations + Composition.

**Why:** ABDM exchanges *documents* (FHIR Bundles), of which `Composition` is the spine. We need a place to persist generated, signed, versioned documents (`fhir_documents`) and a code-mapping for each finding.

## 8. Treatment Plan 🟠
**Current:** `treatment_plans` (`plan_uuid`, `plan_type` best/acceptable, `accepted_at`) + `treatment_plan_items` (tooth_number, treatment_name, pricing, `material_variants`).

**FHIR R4 target:** `CarePlan` (the plan) + `Condition` (what it treats) + `Goal` + planned `Procedure` (each item, status=preparation/not-done).

**Required changes:**
- `ADD` nullable `fhir_careplan_id` on `treatment_plans`; `fhir_procedure_id` on items.
- `MAP` CarePlanMapper: plan → `CarePlan` (status from `accepted_at`: draft→active), items → planned `Procedure` + `Goal`; `plan_type` → CarePlan intent (`proposal` vs `plan`).
- `SVC` treatment sequence (item order) → `CarePlan.activity` ordering.

**Why:** Treatment plans are forward-looking care — `CarePlan` with planned procedures is the exact FHIR shape. Mostly mapping; few fields.

## 9. Procedures / Treatment Visits 🟠
**Current:** `treatment_visits` (status started/ongoing/completed, procedure-specific fields RCT/implant/filling/scaling/extraction/crown, vitals, inline prescription JSON) + `treatment_visit_items` (billing bridge).

**FHIR R4 target:** `Procedure` (status=completed), with vitals → `Observation`, and the visit itself as an `Encounter` (or sub-encounter).

**Required changes:**
- `ADD` nullable `fhir_procedure_id`, `fhir_encounter_id` on `treatment_visits`.
- `MAP` ProcedureMapper: `treatment_name`/`procedure` → SNOMED CT procedure code (mapping table); `tooth_number` (FDI) → `Procedure.bodySite` with FDI tooth coding; specialty fields (RCT canals, implant brand…) → `Procedure.note`/extensions; vitals (BP/pulse/SpO2/temp/sugar/weight) → vital-sign `Observation`s (LOINC).
- `SVC` completed visit → "Discharge/Procedure" record candidate for ABDM.

**Why:** Executed treatment = `Procedure`. FDI tooth notation maps to FHIR tooth body-site codes. Vitals are standard LOINC observations and become part of the health record.

## 10. Prescription 🟠 (best-prepared module)
**Current:** `prescriptions` (number, status draft→issued→…→cancelled, version chain, `language`) + `prescription_items` (drug snapshot, morning/afternoon/night, duration, route, multilingual instructions) + **full CDSS** (`rx_allergy_rules`, `rx_drug_interaction_rules`, `rx_warning_rules`) + `rx_drugs` master with `allergy_tags`/`interaction_tags`/pregnancy/renal/hepatic data + `prescription_overrides` (CDSS override log).

**FHIR R4 target:** `MedicationRequest` (per item) + `Medication` + `AllergyIntolerance` (patient allergies) + `MedicationStatement` (history).

**Required changes:**
- `ADD` nullable `fhir_medicationrequest_id` on items; `ADD` optional drug coding columns on `rx_drugs`: `snomed_code`/`who_atc_code` (for ABDM medication coding).
- `ADD` a first-class **`patient_allergies`** table (currently allergies live as JSON on `patients`) so allergies map to `AllergyIntolerance` resources and feed both CDSS and FHIR. *(Keep the JSON column mirrored for non-breaking.)*
- `MAP` MedicationRequestMapper: item → `MedicationRequest`; dosage (M/A/N + duration + route + food) → `Dosage`; `prescriptions.status` → FHIR status; instructions → `dosageInstruction.patientInstruction`.
- `MAP` allergies → `AllergyIntolerance`; current meds / history → `MedicationStatement`.

**Why:** This module is already the most FHIR-ready (coded drugs, structured dosage, allergy engine, versioning, audit). The main gap is promoting allergies from a JSON blob to a queryable resource and adding standard drug codes. The e-Prescription is a core ABDM health-record type.

## 11. Radiology / Imaging 🟠
**Current:** `consultation_scans` (path, mime, notes) + `clinical_media` (media_type photo/xray/opg/cbct, tooth_no, stage, watermark/thumbnail, tags) — **file-path based, no DICOM**.

**FHIR R4 target:** `ImagingStudy` + `DiagnosticReport` + `Media`/`DocumentReference` for the image; future `Endpoint` for DICOM/PACS.

**Required changes:**
- `ADD` nullable `fhir_imagingstudy_id`, `fhir_diagnosticreport_id`, and `modality_code` (DICOM modality: IO/PANO/CBCT) + `dicom_uid` placeholder on `clinical_media`.
- `ADD` an abstraction seam: a `RadiologyStudy` concept (can be a thin view over `clinical_media` of imaging types) so DICOM/PACS can attach later without reshaping storage.
- `MAP` imaging metadata → `ImagingStudy`; the radiologist's read → `DiagnosticReport`.

**Why:** ABDM diagnostic exchange uses `DiagnosticReport`/`ImagingStudy`. We don't need DICOM now, but we add modality + UID placeholders + a study abstraction so future DICOM compatibility is a config, not a rebuild.

## 12. Lab 🟠
**Current:** `lab_cases` (case_number, status workflow, vendor, costs) + `lab_case_items` + `lab_vendors` + events/attachments — this is a **dental-prosthetic lab** (crowns/dentures), not a pathology lab.

**FHIR R4 target:** `ServiceRequest` (the order) + `DiagnosticReport` + `Observation` (if/when pathology results exist); the prosthetic work also maps to `DeviceRequest`/`Device`.

**Required changes:**
- `ADD` nullable `fhir_servicerequest_id`, `fhir_diagnosticreport_id` on `lab_cases`.
- `MAP` order → `ServiceRequest`; results/report → `DiagnosticReport`+`Observation`; prosthetic device → `Device`/`DeviceRequest`.
- `FLAG` Note: dental-prosthetic lab is *not* a standard ABDM diagnostic flow — map it but keep it internal-only until a pathology/diagnostic-lab use case appears.

**Why:** Diagnostic lab results are an ABDM record type. Our lab is prosthetic, so we map it for completeness but don't prioritize exchange.

---

# PART C — FINANCE, OPS & MEMBERSHIP

## 13. Billing / Finance 🟢→🟠
**Current:** `invoices`/`invoice_items`/`invoice_payments`/`final_bills`/`receipts`/`wallets`, EMI, coupons, GST.

**FHIR R4 target:** `Invoice`, `ChargeItem`, `Account`, (`Claim`/`Coverage` if insurance/PMJAY later).

**Required changes:**
- `ADD` nullable `fhir_invoice_id` on `invoices` (optional — billing exchange is *not* an ABDM M1–M3 requirement).
- `ADD` a seam for **insurance/PMJAY**: `Coverage` mapping + `patient_identifiers` already holds insurance IDs. Prepare `claims` placeholder table (future).
- `MAP` InvoiceMapper (low priority).

**Why:** ABDM core milestones don't require billing exchange, but India's PMJAY/insurance future does. We add identifiers + a `Coverage` seam now so it's painless later. Otherwise finance is largely untouched.

## 14. Inventory 🟢
**Current:** `inventory_items`/`stocks`/`stock_movements` (ledger), categories, locations, batch/expiry.

**FHIR R4 target:** Optional `Device`/`SubstanceDefinition`; generally **out of clinical exchange scope**.

**Required changes:**
- `ADD` optional `udi`/`gtin` (device identifier) columns on `inventory_items` for implants (traceability) → maps to `Device.udiCarrier`.
- Otherwise **no change**; inventory stays internal.

**Why:** Inventory isn't a health-exchange concern, except implant traceability (UDI), which is good practice and future-proofs device records.

## 15. Membership 🟢
**Current:** `finance_membership_plans` + `finance_patient_memberships` (AOCP, family head/add-on).

**FHIR R4 target:** Loosely `Coverage` (a benefit plan).

**Required changes:** `MAP` optional `Coverage` mapping only. No structural change. Keep internal.

**Why:** Membership is a commercial benefit, not a clinical record. Map to `Coverage` only if/when payer integration arrives.

## 16. Appointments 🟠
**Current:** `appointments` (date/time, status scheduled→done, operatory, queue fields, walk-in).

**FHIR R4 target:** `Appointment` + `Slot` + `Schedule`.

**Required changes:**
- `ADD` nullable `fhir_appointment_id`.
- `MAP` AppointmentMapper (status enum → FHIR `Appointment.status`).
- `FLAG` ABDM has a future "appointment booking via PHR app" flow (HIP can publish slots). Add a `published_to_abdm` flag seam; defer the actual scheduling-link API.

**Why:** ABDM's PHR ecosystem can book into HIP slots. Mapping now keeps that door open; we don't build the booking API yet.

---

# PART D — AI, COMMUNICATION & INTELLIGENCE

## 17. AI Secretary "Tulip" 🔴 (consent-critical)
**Current:** `ai_conversations`/`ai_messages`/`ai_action_logs`, local Ollama, **tool-gated** with confirm-card for clinical/financial writes, read tools (patient summary, balance, schedule…).

**FHIR/ABDM target:** N/A as a resource, but it is the **single most consent-sensitive component**.

**Required changes:**
- `GATE` **Hard rule: the AI must NEVER read external/HIE records, ABHA-linked external history, or another facility's data without a valid, unexpired consent.** Enforce in the `ToolRegistry` — any tool that touches external data calls `ConsentManager::assertValid($patientId, $purpose)` first and writes an `ai_action_logs` entry with the consent id.
- `ADD` AI awareness of new concepts: ABHA status, HPR, HFR, consent status, external records, drug allergies (from new `patient_allergies`), current medications, referrals — but *internal* data only unless consent present.
- `ADD` a `consent_id` column on `ai_action_logs` for external-data tool calls (provenance).
- `SVC` new read tools (`AbhaStatusTool`, `ConsentStatusTool`) are internal-status only; an `ExternalHistoryTool` is consent-gated and disabled by default.

**Why:** An AI that can summarise a patient is enormously useful but legally radioactive if it touches consented external data without a consent artefact. The existing confirm-card + action-log architecture is the perfect foundation; we extend it with a consent gate and provenance.

## 18. Communication 🟢
**Current:** unified `communication_queue` + `comm_activity_logs` (multi-channel inbox, SLA, B2B).

**FHIR R4 target:** Optional `Communication`/`CommunicationRequest`; mostly out of scope.

**Required changes:**
- `ADD` optional `consent_id` on outbound clinical communications (sending a patient their record via WhatsApp/email is a disclosure — log the basis).
- Otherwise no structural change.

**Why:** Sending health info to a patient is a disclosure event; tying it to a consent/communication basis is good governance. The inbox itself is unaffected.

## 19. Analytics / Reports 🟢
**Current:** `ReportsController`, KPI/huddle reports.

**Required changes:**
- `ADD` ABDM/consent dashboards (linkage rate, consent grant/revoke counts, sync queue health) as *new* reports.
- `GATE` ensure no report leaks external-record data without basis; aggregate/anonymized only.

**Why:** Operating an ABDM-native system requires observability of linkage and consent. Pure addition.

## 20. Marketing 🟢
**Current:** `mkt_settings`, campaigns.

**Required changes:** **None structurally.** One governance note: marketing must *never* use ABDM/clinical data for targeting without explicit consent (a purpose-of-use that ABDM consent does not cover). Add a policy guard.

**Why:** Compliance boundary, not a code change.

---

# PART E — CROSS-CUTTING & PLATFORM

## 21. Documents 🟠
**Current:** `clinical_files`, `patient_documents`, `ClinicalFile`/`PatientDocument` (path-based).

**FHIR R4 target:** `DocumentReference`.

**Required changes:**
- `ADD` nullable `fhir_documentreference_id`, `document_type_code` (LOINC document type), `is_abdm_shareable` flag.
- `MAP` DocumentReferenceMapper; unify with the new `fhir_documents` table (doc 04) for generated bundles.

**Why:** Any uploaded/generated document that participates in exchange must be a `DocumentReference` with a type code.

## 22. Notifications 🟢
**Current:** `app_notifications`.

**Required changes:** `ADD` new notification types: consent requested/granted/revoked/expired, ABHA link success/fail, sync failure, document signed. Pure addition.

**Why:** Consent and sync events need to reach staff/patients in-app.

## 23. Audit Logs 🟠 (already strong)
**Current:** `audit_logs` (generic, `Auditable` trait, old/new JSON, IP/device) + `billing_audit_logs` + `ai_action_logs`.

**FHIR R4 target:** `AuditEvent` + `Provenance`.

**Required changes:**
- `ADD` an **ABDM/consent audit stream** — either extend `audit_logs` with `category=abdm|consent|hie` or add `abdm_audit_logs` (recommended: dedicated, immutable, hash-chained) capturing every consent decision, every external data access, every document share, with the consent id and ABDM transaction id.
- `MAP` to `AuditEvent`/`Provenance` for any record we share.

**Why:** ABDM compliance mandates a tamper-evident audit of all health-information access and consent actions. Our audit foundation is excellent; we add a dedicated, hash-chained ABDM stream.

## 24. Master Data 🟠
**Current:** `treatments`, `diagnosis_masters` (ICD already), `medicines`/`rx_drugs`, `dental_conditions`, `medical_conditions`, `investigation_masters`, `message_templates`.

**FHIR R4 target:** `CodeSystem` / `ValueSet` / `ConceptMap`.

**Required changes:**
- `ADD` standard-terminology code columns to masters: SNOMED CT (conditions/procedures), LOINC (observations/investigations), ICD-10 (already on diagnosis), WHO-ATC/SNOMED (drugs), FDI (teeth).
- `ADD` a **`terminology_maps`** table (`ConceptMap`) — local term → standard code — so mapping is data-driven, not hard-coded, and editable as terminologies evolve.

**Why:** FHIR resources are only exchangeable if coded with national/international terminologies. A `ConceptMap` table makes Dentfluence terminology-agile for 15 years.

## 25. Clinic Settings / User Settings 🔴
**Current:** key-value `app_settings` (not branch-scoped), `SettingsController`, `mkt_settings`, HR settings.

**Required changes:** Redesign settings to add the following **new setting groups** (all feature-flagged, default off):
- **ABDM Configuration** — enable/disable, environment (`sandbox`/`production`) toggle, HIP/HIU ids, gateway base URL, facility HFR id.
- **FHIR Configuration** — enabled, default profiles, terminology server ref, document-generation defaults.
- **Consent Policies** — default purposes, default expiry, auto-expire policy, who may request consent.
- **Data Exchange Settings** — sync cadence, batch sizes, retry policy.
- **Security / Encryption** — key references (KMS/secret-store pointers), at-rest cipher, token rotation interval, signature config.
- **API Endpoints** — sandbox vs production endpoint sets.
- **Audit Configuration** — retention, hash-chaining toggle.
- **Synchronization Settings** — offline mode, conflict policy.
- **Feature Flags** — `abdm_enabled`, `fhir_enabled`, `consent_required`, `abha_linking_enabled`, per-module rollout flags.
- `ADD` `branch_id` scoping to settings so config is per-facility.

**Why:** All ABDM behaviour must be operator-configurable and environment-switchable without code changes; settings are the control panel for the whole migration and the kill-switch for safe rollout.

## 26. Dashboard 🟢
**Current:** `DashboardController`, `/api/v1/dashboard`.

**Required changes:** `ADD` ABDM widgets (ABHA linkage %, pending consents, sync queue health, unsigned documents). Pure addition.

**Why:** The operator needs at-a-glance ABDM health.

## 27. Profile Pages (patient/doctor/clinic) 🟠
**Current:** patient `show.blade.php`, doctor/staff profile, clinic settings.

**Required changes:** `ADD` UI sections (read-only this phase): ABHA card (number/address/verification/linked date) on patient; HPR card on doctor; HFR card on clinic; consent history panel on patient; linked external records list (consent-gated). Driven by the new tables; no destructive UI change.

**Why:** Staff need to see and act on ABDM identity + consent. Additive panels respecting the "data entry stays dead-simple" rule — these go in admin/detail views, not the quick-add flow.

---

# PART F — NEW INFRASTRUCTURE (no current equivalent)

## 28. ABDM Layer 🔴 NEW
A dedicated infrastructure layer (`app/Abdm/…`) containing: **ABHA Manager, HPR Manager, HFR Manager, Consent Manager, FHIR Mapping Engine, Health Information Exchange, ABDM Auth, Audit Service, Sync Service, Queue Service, Security Layer.** Every module calls *this*, never ABDM APIs. Detailed in `02-TARGET-ARCHITECTURE.md`.

## 29. FHIR Mapping Engine 🔴 NEW
`Internal Model → FHIR Resource → ABDM Payload`, one mapper per entity, a Bundle assembler per ABDM document type, plus the `fhir_documents` + `terminology_maps` tables. Modules never hand-write FHIR. Detailed in `04-FHIR-MAPPING-ENGINE.md`.

## 30. Consent / Sync / Security Engines 🔴 NEW
- **Consent Engine** — `consents` + `consent_artefacts` + `consent_audit`; states requested/granted/denied/revoked/expired; purpose, requester, provider, expiry, audit. Doc 05.
- **Sync Engine** — `sync_outbox`/`sync_inbox`/`sync_retry`/`sync_failed`; conflict resolution, versioning, offline. Doc 06.
- **Security Layer** — OAuth/JWT, encryption at rest/in transit, secret storage, token rotation, digital signatures. Doc 07.

---

## 31. Consolidated change register

| # | Module | Impact | New tables | Additive columns | FHIR resource | Consent-gated |
|---|--------|:---:|---|---|---|:---:|
| 1 | Patient | 🔴 | `patient_identifiers`, `patient_external_records` | abha_number/address/status/linked_at, preferred_language, fhir_resource_id, gov_id_* | Patient | reads of external |
| 2 | Doctor | 🔴 | `practitioner_identifiers`, `practitioner_qualifications` | hpr_id, hpr_status, council, signature_ref, fhir_practitioner_id | Practitioner / PractitionerRole | — |
| 3 | Clinic | 🔴 | `facility_abdm_config`, +Branch model | hfr_id, facility_type, geo_*, fhir_organization_id, cert_ref | Organization / Location | — |
| 4 | Auth | 🟠 | `abdm_access_tokens` | — | — | — |
| 5 | Roles/Perms | 🟠 | — | new modules+perms | — | enforces |
| 6 | Consultation | 🔴 | — | encounter_status/class, care_context_ref, fhir_encounter_id | Encounter (Care Context) | on share |
| 7 | Clinical Notes | 🔴 | `fhir_documents` | fhir_composition_id | Composition / DocumentReference / Observation | on share |
| 8 | Treatment Plan | 🟠 | — | fhir_careplan_id, fhir_procedure_id | CarePlan / Goal / Condition | — |
| 9 | Procedures/Visits | 🟠 | — | fhir_procedure_id, fhir_encounter_id | Procedure / Observation | — |
| 10 | Prescription | 🟠 | `patient_allergies` | fhir_medicationrequest_id, snomed/atc on drugs | MedicationRequest / AllergyIntolerance | — |
| 11 | Radiology | 🟠 | (`radiology_studies` view) | fhir_imagingstudy_id, modality_code, dicom_uid | ImagingStudy / DiagnosticReport | on share |
| 12 | Lab | 🟠 | — | fhir_servicerequest_id, fhir_diagnosticreport_id | ServiceRequest / DiagnosticReport | on share |
| 13 | Billing | 🟢→🟠 | `claims` (future) | fhir_invoice_id | Invoice / Coverage | — |
| 14 | Inventory | 🟢 | — | udi/gtin (implants) | Device | — |
| 15 | Membership | 🟢 | — | — | Coverage (opt) | — |
| 16 | Appointments | 🟠 | — | fhir_appointment_id, published_to_abdm | Appointment / Slot | — |
| 17 | AI Secretary | 🔴 | — | consent_id on ai_action_logs | — | **hard gate** |
| 18 | Communication | 🟢 | — | consent_id on outbound clinical | Communication (opt) | on disclosure |
| 19 | Analytics | 🟢 | — | — | — | aggregate only |
| 20 | Marketing | 🟢 | — | — | — | policy guard |
| 21 | Documents | 🟠 | (unified w/ fhir_documents) | fhir_documentreference_id, doc_type_code, is_abdm_shareable | DocumentReference | on share |
| 22 | Notifications | 🟢 | — | new types | — | — |
| 23 | Audit | 🟠 | `abdm_audit_logs` (hash-chained) | category | AuditEvent / Provenance | — |
| 24 | Master Data | 🟠 | `terminology_maps` | snomed/loinc/atc/fdi codes | CodeSystem / ConceptMap | — |
| 25 | Settings | 🔴 | `branch_settings` (or branch_id on app_settings) | 10 new setting groups + flags | — | — |
| 26 | Dashboard | 🟢 | — | — | — | — |
| 27 | Profiles | 🟠 | — | UI panels (read-only) | — | external list gated |
| 28 | ABDM Layer | 🔴 | NEW infra | — | — | owns gate |
| 29 | FHIR Engine | 🔴 | `fhir_documents`, `terminology_maps` | — | all | — |
| 30 | Consent/Sync/Security | 🔴 | `consents`, `consent_artefacts`, `consent_audit`, `sync_*` | — | Consent | core |

**Totals:** ~13 new tables, ~30 additive nullable column groups, 0 destructive changes, 3 new infrastructure layers. Every change is additive and feature-flag-gated.

---

## 32. The "nothing missed" checklist (all 30 prompt items accounted for)

Dashboard ✅ · Patients ✅ · Appointments ✅ · Doctors ✅ · Consultations ✅ · Clinical Notes ✅ · Treatment Plans ✅ · Procedures ✅ · Prescription ✅ · Radiology ✅ · Lab ✅ · Billing ✅ · Inventory ✅ · Membership ✅ · Staff ✅(=Doctors/Users) · Clinic Settings ✅ · User Settings ✅ · AI Secretary ✅ · Communication ✅ · Analytics ✅ · Marketing ✅ · Reports ✅(=Analytics) · Documents ✅ · Notifications ✅ · Audit Logs ✅ · Authentication ✅ · Roles & Permissions ✅ · Profile Pages ✅ · Master Data ✅ · (NEW) ABDM/FHIR/Consent/Sync/Security layers ✅

> Next: `02-TARGET-ARCHITECTURE.md` turns this audit into the layered design and diagrams.
