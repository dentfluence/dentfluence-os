# Dentfluence OS — Feature & Compliance Roadmap

**Status:** Planning draft · **Created:** 2026-06-27 · **Updated:** 2026-06-27
**Purpose:** Single sequenced plan for everything we should incorporate into the Dentfluence OS — product features (from the competitive matrix vs CareStack, Dentrix Ascend, Curve, Cliniify + Aeka/Overjet imaging findings) **and** the Indian regulatory stack (DPDP, ABDM, coding standards, tax, pharmacy, accreditation).

> Planning only — no code. Detailed sub-plans already exist: ABDM (`docs/abdm/`), PRM AI (`docs/plan-prm-ai.md`), Job Library (`docs/plan-job-library-sops.md`), and the competitive matrix (`docs/Dentfluence_Competitive_Matrix.xlsx`). This is the umbrella that orders them.

---

## How to read this

**Effort:** S = days · M = 1–3 weeks · L = 1–2 months · XL = multi-month
**Status today:** ✅ Built · 🟡 Partial / untested · ⬜ Not started
**Migration:** new DB tables/columns needed (you run these manually).
**Priority:** 🔴 Critical (legal deadline) · 🟠 High · 🟡 Medium · ⚪ Low

**Guiding rule:** data-entry surfaces stay *dead simple* for 12th-pass front-desk staff; admin / KPI / analytics layers can be rich. Never mix the two.
**Build discipline:** every feature = migration + model + controller + routes + view together. Flags default off. Nothing destructive without sign-off.

---

## ⚠️ Time-critical: DPDP deadline

The **DPDP Act 2023 + DPDP Rules 2025** are law (Rules notified 14 Nov 2025). **Hard enforcement: 13 May 2027** — roughly **11 months from today** — with penalties up to **₹250 crore**. The consent/rights machinery (Wave 5) is therefore *legally mandatory with a dated deadline*, not optional. It must start well before the other India work, even though it sits in a later wave thematically.

---

## Master buildup table

Every item we should incorporate, consolidated. IDs match the wave sections below.

| ID | Item | Wave | Effort | Status | Migr. | Priority |
|----|------|------|--------|--------|-------|----------|
| 0.1 | Branch / multi-location model | 0 Foundation | M | 🟡 | Y | 🟠 |
| 0.2 | ABDM identity tables + `app/Abdm` skeleton | 0 Foundation | M | 🟡 | Y | 🟠 |
| 0.3 | API integrations layer (harden `/api/v1`) | 0 Foundation | M | 🟡 | N | 🟠 |
| 0.4 | Security & audit hardening (RBAC, encryption, MFA, tamper-evident logs) | 0 Foundation | M | 🟡 | ~ | 🔴 |
| 1.1 | Communication OS — 4 engines + unified inbox | 1 Partials | L | 🟡 | Y | 🟠 |
| 1.2 | WhatsApp messaging | 1 Partials | M | 🟡 | N | 🟠 |
| 1.3 | Mobile app — stabilise & test | 1 Partials | L | 🟡 | N | 🟠 |
| 1.4 | Inventory / material tracking — test & ship | 1 Partials | M | 🟡 | ~ | 🟠 |
| 1.5 | Voice notes — beyond Phase 1 | 1 Partials | M | 🟡 | ~ | 🟡 |
| 1.6 | Lab module v2 — remaining phases | 1 Partials | L | 🟡 | Y | 🟡 |
| 2.1 | Online 24/7 self-scheduling | 2 Patient | M | ⬜ | ~ | 🟠 |
| 2.2 | Patient portal / app | 2 Patient | L | ⬜ | Y | 🟠 |
| 2.3 | Online digital intake forms | 2 Patient | M | 🟡 | Y | 🟠 |
| 2.4 | Reputation / reviews management | 2 Patient | S–M | 🟡 | ~ | 🟡 |
| 2.5 | Payment gateway integration | 2 Patient | M | ⬜ | Y | 🟠 |
| 2.6 | Teledentistry | 2 Patient | M | ⬜ | Y | ⚪ |
| 3.1 | Imaging — upload + viewer + editing + tooth tagging | 3 Clinical | L | ⬜ | Y | 🟠 |
| 3.2 | Perio charting (6-point) | 3 Clinical | M | ⬜ | Y | 🟠 |
| 3.3 | Full dental charting / odontogram | 3 Clinical | L | 🟡 | Y | 🟠 |
| 3.4 | Local AI x-ray *assist* (non-diagnostic) | 3 Clinical | M | ⬜ | N | 🟡 |
| 3.5 | DICOM viewer / PACS (OPG, CBCT, intraoral) | 3 Clinical | L | ⬜ | Y | 🟠 |
| 3.6 | Implant / prosthetics tracking + batch/lot traceability | 3 Clinical | M | ⬜ | Y | 🟡 |
| 3.7 | Treatment plan + consent forms with e-signature | 3 Clinical | S–M | 🟡 | ~ | 🟠 |
| 4.1 | Tulip AI copilot — phased | 4 Differentiators | L | 🟡 | ~ | 🟡 |
| 4.2 | Role-based Job Library + SOPs | 4 Differentiators | M | ⬜ | Y | 🟡 |
| 4.3 | Procurement → AP — extend | 4 Differentiators | M | ✅→ | ~ | 🟡 |
| 4.4 | Snap-a-bill / OCR — extend | 4 Differentiators | S–M | ✅→ | ~ | ⚪ |
| 4.5 | PRM + AI automation | 4 Differentiators | L | 🟡 | Y | 🟡 |
| 4.6 | Membership — deepen | 4 Differentiators | S | ✅→ | ~ | ⚪ |
| 4.7 | Marketing Engine — mature | 4 Differentiators | M | 🟡 | ~ | 🟡 |
| 5.1 | Granular per-purpose consent capture + withdrawal | 5 DPDP | M | ⬜ | Y | 🔴 |
| 5.2 | Patient rights workflows (access, correction, erasure, grievance, nominee) | 5 DPDP | M | ⬜ | Y | 🔴 |
| 5.3 | Breach notification pipeline (Data Protection Board + patients) | 5 DPDP | S–M | ⬜ | Y | 🔴 |
| 5.4 | Data retention / deletion + auto-purge | 5 DPDP | M | ⬜ | Y | 🔴 |
| 5.5 | Minor / parental consent flows | 5 DPDP | S–M | ⬜ | Y | 🔴 |
| 5.6 | Named DPO + tamper-evident audit trail | 5 DPDP | S | 🟡 | ~ | 🔴 |
| 6.1 | ABHA creation + verification at registration (M1) | 6 ABDM | M | 🟡 | Y | 🟠 |
| 6.2 | HPR — register dentists / hygienists | 6 ABDM | S–M | ⬜ | Y | 🟡 |
| 6.3 | HFR — register branches / facilities | 6 ABDM | S–M | ⬜ | Y | 🟡 |
| 6.4 | M2 link records → M3 consent-based HIE → M4 | 6 ABDM | XL | ⬜ | Y | 🟡 |
| 6.5 | FHIR R4 record format | 6 ABDM | L | 🟡 | Y | 🟠 |
| 6.6 | NHCX — National Health Claims Exchange | 6 ABDM | L | ⬜ | Y | ⚪ |
| 6.7 | Clinical coding: SNOMED CT, LOINC, ICD-10 / ICD-DA, ADA CDT | 6 ABDM | M | ⬜ | Y | 🟡 |
| 7.1 | GST e-invoicing + HSN/SAC codes | 7 Finance/Tax | M | 🟡 | ~ | 🟠 |
| 7.2 | UPI / e-Rupee / gateway reconciliation | 7 Finance/Tax | M | 🟡 | ~ | 🟡 |
| 7.3 | TDS handling for vendor / lab payments | 7 Finance/Tax | S–M | ⬜ | Y | 🟡 |
| 7.4 | Insurance / TPA claim formats (+ NHCX path) | 7 Finance/Tax | M | ⬜ | Y | ⚪ |
| 7.5 | Pharmacy: Schedule H / H1 / X dispensing register | 7 Pharmacy | M | ⬜ | Y | 🟡 |
| 7.6 | e-prescription standards aligned to ABDM | 7 Pharmacy | M | 🟡 | ~ | 🟡 |
| 7.7 | Drug license linkage (if dispensing) | 7 Pharmacy | S | ⬜ | Y | ⚪ |
| 8.1 | Encryption at rest + in transit | 8 Accreditation | M | 🟡 | N | 🟠 |
| 8.2 | MFA + session security | 8 Accreditation | S–M | ⬜ | ~ | 🟠 |
| 8.3 | NABH digital health / dental accreditation support | 8 Accreditation | L | ⬜ | ~ | 🟡 |
| 8.4 | ISO 27001 posture | 8 Accreditation | L | ⬜ | N | ⚪ |
| 8.5 | CERT-In incident reporting readiness | 8 Accreditation | S–M | ⬜ | N | 🟡 |

**Two highest-leverage gaps from current state:** (1) DPDP consent/rights machinery — *legally mandatory, dated deadline*; (2) DICOM imaging — the biggest dental-specific functional gap. The ABDM-first / FHIR-native architecture already positions us well for Waves 6 and the coding standards.

---

## Wave 0 — Foundation
*Aligns with the ABDM plan's "Phase 1 Wave 1". Additive, zero-destructive, flags off.*

- **0.1 Branch / multi-location model** — branch-aware scoping for every record. Unblocks DSO credibility, ABDM identity, cross-branch KPIs. *Do first.*
- **0.2 ABDM identity tables + `app/Abdm` skeleton** — polymorphic identifiers + ABHA linkage scaffold (`docs/abdm/03-DATA-MODEL-AND-SCHEMA.md`). *Dep: 0.1.*
- **0.3 API integrations layer** — harden `/api/v1` (Sanctum) into a documented surface for mobile + third parties.
- **0.4 Security & audit hardening** — RBAC review, encryption, MFA, **tamper-evident** audit trail (DPDP needs this). *Dep: 0.1. Priority lifted to 🔴 because DPDP 5.6 depends on it.*

## Wave 1 — Ship the partials (highest ROI)
- **1.1 Communication OS** — Recall, Opportunity, Inbound/Leads, B2B → unified inbox; reminders + two-way text. Closes ~5 matrix rows at once.
- **1.2 WhatsApp messaging** — India's primary channel; Cliniify already ships it. *Dep: 1.1.*
- **1.3 Mobile app** — stabilise & QA the built-but-untested Flutter modules. *Dep: 0.3.*
- **1.4 Inventory / material tracking** — finish & test the Core-6 backend (beats Cliniify's headline feature).
- **1.5 Voice notes** — extend local-AI transcription → structured notes across more touchpoints.
- **1.6 Lab module v2** — continue the enterprise rebuild past Phase 1.

## Wave 2 — Patient-facing & engagement
- **2.1 Online 24/7 self-scheduling** — public booking into the appointment engine.
- **2.2 Patient portal / app** — book, forms, records/statements, pay. Biggest patient-side gap. *Dep: 2.1, 2.3, 2.5.*
- **2.3 Online digital intake forms** — patient-completed (today we only scan paper); auto-populate the record.
- **2.4 Reputation / reviews** — review requests + monitoring; extend Marketing Engine.
- **2.5 Payment gateway** — card-on-file / online processing (Razorpay/Stripe-class); enables text-to-pay + portal pay.
- **2.6 Teledentistry** — video consult + notes. *Lower priority; defer unless demand appears.*

## Wave 3 — Clinical depth: imaging, charting, DICOM
*Biggest clinical gaps vs the US incumbents. Build plumbing; rent/scope-down the diagnostic AI.*

- **3.1 Imaging — upload + viewer + editing** — capture-by-upload first (skip native sensor drivers), zoom/contrast/enhance, side-by-side & historical compare, tooth-number tagging, claim/lab export. Reuse voice-notes' polymorphic pattern.
- **3.2 Perio charting** — 6-point probing chart.
- **3.3 Full odontogram** — conditions, history, treatment linkage.
- **3.4 Local AI x-ray *assist*** — describe / triage / patient-explanation visuals only; **not** diagnostic-grade. Data-residency edge nobody else has.
- **3.5 DICOM viewer / PACS** — OPG, CBCT, intraoral X-rays. Critical dental-specific gap and a real differentiator; integrate a DICOM-capable viewer/store.
- **3.6 Implant / prosthetics tracking** — batch/lot traceability per medical-device rules.
- **3.7 Treatment plan + consent e-signature** — formalise consent capture on the (built) treatment plan; feeds DPDP 5.1.

> **Aeka/Overjet lesson:** CareStack built imaging in-house but *rents* diagnostic AI from Overjet. Clinical-grade radiographic AI needs huge datasets + regulatory clearance — out of scope to build solo. Ship 3.1–3.3 + 3.5 for full value, 3.4 as assist-only, partner diagnostic AI as a later "if needed".

## Wave 4 — Deepen differentiators
*Where we already win. Several can run in parallel with earlier waves.*

- **4.1 Tulip AI copilot** — app-wide local assistant; confirm-cards for clinical/financial actions.
- **4.2 Role-based Job Library + SOPs** — `docs/plan-job-library-sops.md`.
- **4.3 Procurement → AP** — deepen the PO→GRN→Invoice→AP chain (a moat vs all rivals).
- **4.4 Snap-a-bill / OCR** — extend local-vision OCR to more document types.
- **4.5 PRM + AI automation** — `docs/plan-prm-ai.md`.
- **4.6 Membership** — extend the tested membership + finance chain.
- **4.7 Marketing Engine** — mature; tie into reputation (2.4) and recall (1.1).

## Wave 5 — DPDP data protection 🔴 (time-critical, deadline 13 May 2027)
*Legally mandatory. Start during Wave 0/1 even though it's numbered here.*

- **5.1 Granular per-purpose consent** — capture + withdrawal, logged, timestamped, per-purpose (not one blanket checkbox).
- **5.2 Patient rights workflows** — access, correction, erasure, grievance redressal, nominee (data after death/incapacity).
- **5.3 Breach notification pipeline** — to the Data Protection Board + affected patients.
- **5.4 Data retention / deletion** — policies + auto-purge.
- **5.5 Minor / parental consent flows** — dentistry has many minors (healthcare gets some exemption, but build the flow).
- **5.6 Named DPO + tamper-evident audit trail** — builds on 0.4 (you mostly have audit logging).

## Wave 6 — ABDM full stack & interoperability
*Core covered by `docs/abdm/`. The full national-health stack:*

- **6.1 ABHA creation + verification** at registration (M1).
- **6.2 HPR** — register every dentist/hygienist.
- **6.3 HFR** — register each branch/facility.
- **6.4 M2 → M3 → M4** — link records to ABHA, then bidirectional consent-based HIE.
- **6.5 FHIR R4** — record format (why ABDM-first/FHIR-native is the right call).
- **6.6 NHCX** — National Health Claims Exchange for cashless/insurance interop.
- **6.7 Clinical coding** — SNOMED CT (India national licence), LOINC (lab), ICD-10 + WHO ICD-DA (dental), ADA CDT procedure codes.

## Wave 7 — Financial, tax & pharmacy compliance
- **7.1 GST e-invoicing** + correct HSN/SAC codes.
- **7.2 UPI / e-Rupee / gateway reconciliation** (overlaps 2.5).
- **7.3 TDS handling** for vendor/lab payments.
- **7.4 Insurance / TPA claim formats** (+ the NHCX path, 6.6).
- **7.5 Pharmacy** — Schedule H/H1/X dispensing rules + register (ties to Rx module).
- **7.6 e-prescription standards** aligned to ABDM.
- **7.7 Drug license linkage** — if you dispense.

## Wave 8 — Security, accreditation & standards
- **8.1 Encryption** at rest + in transit.
- **8.2 MFA + session security.**
- **8.3 NABH** digital health / dental accreditation support — clinics increasingly want software that helps them pass NABH.
- **8.4 ISO 27001** posture — optional but valuable.
- **8.5 CERT-In** incident reporting readiness.

---

## Suggested sequencing (one line)

Wave 0 foundation **+ start DPDP 5.1/5.4/5.6 in parallel (deadline-driven)** → finish partials W1 (Comm OS, WhatsApp, mobile, inventory) → patient portal/scheduling/payments W2 → imaging + charting + DICOM W3 → differentiators W4 in parallel → ABDM full stack W6 + finance/pharmacy W7 → accreditation W8 as you scale.

## Open decisions for Sumit

1. **DPDP:** want a dedicated consent-schema design doc next? (Recommended — it's the dated legal item.)
2. **Imaging:** upload-only first (recommended), or invest early in native sensor bridges? And DICOM viewer — build vs integrate (OHIF/dcm4che-class)?
3. **AI x-ray:** local assist-only (recommended), or budget a partner diagnostic AI later?
4. **Hosting:** stay self-host (Laragon) or move toward SaaS/cloud for "anywhere access" + easier ABDM/DPDP infra?
5. **Patient app vs portal:** which first? (Portal cheaper; app matches Cliniify.)

---

## Sources (regulatory)

- DPDP Rules 2025 notified — [PIB](https://www.pib.gov.in/PressReleasePage.aspx?PRID=2190655)
- DPDP compliance timeline 2026–27 — [India Briefing](https://www.india-briefing.com/news/india-dpdp-compliance-timeline-enforcement-2026-27-44740.html/)
- DPDP for healthcare — [Kent Hospitals](https://kenthospitals.com/health/dpdp-act-2025-healthcare-compliance/)
- ABDM integration M1/M2/M3 — [Nirmitee](https://nirmitee.io/blog/step-by-step-guide-for-abdm-integration/)
- ABDM compliance for clinics 2026 — [EasyClinic](https://www.easyclinic.io/abdm-compliance/)

*Truncation note: complete through Wave 8 + master table. Ask to expand any single wave (esp. Wave 5 DPDP consent schema) into its own build plan with migrations/models/routes/views.*
