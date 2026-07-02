# Dentfluence OS — Build Timeline (Phased)

**Created:** 2026-06-27 · **Horizon:** Jul 2026 → Dec 2027 (18 months)
**Companion to:** `docs/plan-os-feature-roadmap.md` (the *what*) — this doc is the *when*.
**Strategy context:** `docs/competitive/eka-care-vs-dentfluence.md` (cloud-only pivot, dental-depth moat).

---

## Assumptions (change these and the dates shift)

- **Solo builder, AI-assisted.** Pace assumed ≈ one *roadmap item* (S/M effort) shipped-and-tested per 1–2 weeks; **L** items 3–6 weeks; **XL** items span a full phase. Calendar below is built on that pace — compress if you add help, expand if testing/real-clinic feedback eats time.
- **"Shipped" = built + tested + flag-ready**, not just code-written. Most modules in memory are *code-complete but untested* — testing time is budgeted into each phase, not assumed free.
- **Cloud-only after launch.** Cloud infra + data-residency (India region) is a hard gate before launch, not an afterthought.
- **DPDP Wave 5 is DONE** ✅ — so it appears as a *pre-launch verification checkpoint*, not a build phase.
- **Build discipline unchanged:** migration + model + controller + routes + view together; flags default off; nothing destructive without sign-off.

---

## Status legend

✅ Built & tested · 🟢 Code-complete, needs test · 🟡 Partial · ⬜ Not started · 🔵 Infra/business (non-feature)

---

## The shape of it (one glance)

```
2026                                    2027
Jul  Aug | Sep  Oct | Nov  Dec || Jan  Feb Mar | Apr May Jun | Jul Aug Sep | Oct Nov Dec
─Phase A─ | ─Phase B─ | ─Phase C─ || ──Phase D── | ──Phase E── | ──Phase F── | ──Phase G──
Harden &  | Comms &   | Patient   || Clinical    | Differenti- | ABDM full   | Finance,
secure    | engage    | + pay +   || depth: img, | ators +     | stack +     | pharmacy,
base      |           | CLOUD     || DICOM,      | DPDP verify | interop     | accredit-
          |           | →LAUNCH🚀 || charting    | (deadline)  |             | ation
```

**Launch milestone: end of Phase C (~Dec 2026 / Jan 2027)** — cloud-hosted, secure, patient-facing, payment-enabled, DPDP-verified.
**Hard legal checkpoint: DPDP enforcement 13 May 2027** — falls inside Phase E; Wave 5 already built, so this is an audit/verify gate, not a scramble.

---

## Phase A — Harden & Secure the Base (Jul–Aug 2026)
*Goal: turn "code-complete but untested" into a launchable, secure foundation. Nothing flashy — this is the cloud-launch prerequisite.*

| ID | Item | Effort | Status | Why now |
|----|------|--------|--------|---------|
| 0.4 | Security & audit hardening (RBAC, encryption, MFA, tamper-evident logs) | M | 🟡 | Cloud-launch + DPDP both depend on it. |
| 8.1 | Encryption at rest + in transit | M | 🟡 | Non-negotiable before cloud PHI. |
| 8.2 | MFA + session security | S–M | ⬜ | Same. |
| 0.3 | API layer harden (`/api/v1`, Sanctum) | M | 🟡 | Mobile + portal + sync all ride on it. |
| 1.3 | Mobile app — stabilise & test built modules | L | 🟡 | Large untested surface; QA now. |
| 1.4 | Inventory / material tracking — test & ship | M | 🟡 | Already built; verify + flip on. |
| 🔵 | **Cloud infra plan** (India-region host, backups, secrets, CI) | M | 🔵 | Decide stack now; provision in Phase C. |
| 🔵 | **Sync-layer design** (cloud = source of truth, encrypted offline cache) | S–M | 🔵 | Replaces "offline-capable"; design before launch. |

**Exit criteria:** security audit passed · all Phase-A modules tested · cloud infra + sync architecture decided.

---

## Phase B — Communication & Engagement (Sep–Oct 2026)
*Goal: close the ~5 matrix rows Eka bundles natively (comms, WhatsApp, reviews). This is the Eka-overlap zone — match it before launch.*

| ID | Item | Effort | Status |
|----|------|--------|--------|
| 1.1 | Communication OS — 4 engines + unified inbox | L | 🟡 |
| 1.2 | WhatsApp messaging (India's primary channel) | M | 🟡 |
| 2.4 | Reputation / reviews management | S–M | 🟡 |
| 4.7 | Marketing Engine — mature; tie to reviews + recall | M | 🟡 |
| 1.5 | Voice notes — extend beyond Phase 1 (→ dental-native scribe) | M | 🟡 |
| 1.6 | Lab module v2 — remaining phases | L | 🟡 |

**Exit criteria:** two-way patient messaging live (WhatsApp + reminders) · reviews loop working · lab v2 usable.

---

## Phase C — Patient-Facing + Payments + CLOUD 🚀 (Nov–Dec 2026)
*Goal: everything a clinic needs to run on the cloud and let patients self-serve. Ends in LAUNCH.*

| ID | Item | Effort | Status |
|----|------|--------|--------|
| 2.1 | Online 24/7 self-scheduling | M | ⬜ |
| 2.3 | Online digital intake forms (patient-completed) | M | 🟡 |
| 2.5 | Payment gateway (Razorpay/Stripe-class, card-on-file) | M | ⬜ |
| 2.2 | Patient portal (book, forms, records, pay) | L | ⬜ |
| 7.1 | GST e-invoicing + HSN/SAC codes | M | 🟡 |
| 7.2 | UPI / gateway reconciliation | M | 🟡 |
| 🔵 | **Cloud migration** (provision infra from Phase A plan, data-residency) | L | 🔵 |
| 🔵 | **Sync layer** (build the encrypted offline cache designed in Phase A) | M | 🔵 |
| 🔴 | **DPDP pre-launch verification** (consent/rights/breach/purge audit — Wave 5 ✅ already built) | S | ✅→verify |

> ### 🚀 LAUNCH MILESTONE — target end Dec 2026 / early Jan 2027
> Cloud-hosted · secure (MFA/encryption/audit) · patient self-scheduling + portal + online payments · GST-compliant billing · **DPDP-verified before any PHI touches the cloud.** Flat bundled pricing (no metering) is the go-to-market hook.

---

## Phase D — Clinical Depth: Imaging, DICOM, Charting (Jan–Mar 2027)
*Goal: build the dental moat. This is where "dental-native" becomes visible and Eka has nothing. Highest post-launch priority.*

| ID | Item | Effort | Status | Note |
|----|------|--------|--------|------|
| 3.1 | Imaging — upload + viewer + editing + tooth tagging | L | ⬜ | Upload-first (skip sensor drivers). |
| 3.3 | Full odontogram / dental charting | L | 🟡 | Conditions + history + treatment linkage. |
| 3.2 | Perio charting (6-point) | M | ⬜ | |
| 3.7 | Treatment plan + consent forms w/ e-signature | S–M | 🟡 | Feeds DPDP consent (already built). |
| 3.5 | **DICOM viewer / PACS** (OPG, CBCT, intraoral) | L | ⬜ | **The headline dental gap vs Eka.** Integrate OHIF/dcm4che-class, don't build from scratch. |
| 3.6 | Implant / prosthetics tracking + batch/lot traceability | M | ⬜ | Medical-device rules. |
| 3.4 | Local AI x-ray *assist* (non-diagnostic) | M | ⬜ | Triage/patient-explanation only; partner diagnostic AI later if needed. |

**Exit criteria:** a dentist can upload + view + tag radiographs, run DICOM (OPG/CBCT), chart odontogram + perio, and capture e-signed consent. **This phase alone is your strongest sales story.**

---

## Phase E — Differentiators + DPDP Legal Checkpoint (Apr–Jun 2027)
*Goal: deepen where you already beat everyone. DPDP enforcement (13 May 2027) lands mid-phase — Wave 5 is built, so this is a final compliance audit, not a build.*

| ID | Item | Effort | Status |
|----|------|--------|--------|
| 🔴 | **DPDP final compliance audit** before 13 May 2027 enforcement (Wave 5 ✅) | S | ✅→audit |
| 4.0 | **Drug-data sourcing decision (CDSS spike)** — build vs consume a *neutral* Indian drug DB + interactions + clinical calculators (e.g. CDSCO/licensed source). **Do NOT depend on Eka's MedAI API** (competitor; metered; sees prescribing data). | S | ⬜ |
| 4.1 | **Tulip — role-aware personal assistant** (the "everyone's assistant" layer) | XL | 🟡 | One brain, role-scoped per persona. **Staff = a genuine PERSONAL assistant** across their whole work life (schedule, own earnings/finance, reminders, CDE/learning, what's-pending, productivity) — not just a task-bot, and not only at the chair: **dentist** → personal + clinical (chairside Jarvis is *one mode*) · **consultant** → personal + finance + visit tracking (→ ProConsult) · **front desk** → personal + tasks/what's-next · **assistant/staff** → personal + in-the-moment SOP guidance. **Patient is a DIFFERENT kind of relationship** (external) → two layers, see 4.1c + 4.1d. Scope = role + RBAC + Job Library (4.2) + that person's own data. Confirm-cards for clinical/financial. |
| 4.1a | **Tulip orchestration — indigenous, model-agnostic MCP router** | L | ⬜ | The plumbing under 4.1: routes to best frontier model (Claude/GPT/Gemini) **+ local models**, pulls + curates dental data, returns grounded answers (own version of Eka's MCP — dental-deep, role-aware). **MOAT = orchestration + dental grounding, NOT the model** (models are swappable engines). |
| 4.1b | **Tulip chairside voice secretary** — extends Voice Notes (1.5) | L | ⬜ | Hands-free, **voice-first** charting / notes / data entry + proactive **morning briefing** (ties to Huddle). The *dental* wedge: dentist is gloved/sterile mid-procedure → voice is a *need*, not a nicety (vs Eka's generic scribe). |
| 4.1c | **Patient layer 1 — 24/7 AI front desk (service)** — on Comms OS (1.1) + self-scheduling (2.1) | M | ⬜ | Always-on patient *service* bot: answers WhatsApp/calls after hours, books, reminds, reschedules, billing/queries (multilingual). Makes the clinic feel "open 24/7" without staff. External service layer — NOT a staff personal assistant. |
| 4.1d | **Patient layer 2 — personal health companion** | L | ⬜ | The patient's *own* assistant: oral-health guidance, **post-op/after-care** instructions, plain-language treatment understanding, medication/adherence + recall reminders, access to their own records. Care-side companion (distinct from the 4.1c service desk). Patient-data scoped + consent-gated. Boosts adherence + case acceptance + retention. |
| MCP | **Internal ecosystem-glue MCP** — wrap Tulip's ToolRegistry; glue (Clinical Library ↔ PRM ↔ Marketing ↔ Tulip). **Build but DO NOT launch/expose**; `ConsentManager`-gated. *Distinct from 4.1a:* this is ecosystem plumbing (not a buying driver); 4.1/4.1a IS a buying driver. | M | ⬜ |
| 4.5 | PRM + AI automation (`docs/plan-prm-ai.md`) | L | 🟡 |
| 4.2 | Role-based Job Library + SOPs (`docs/plan-job-library-sops.md`) | M | ⬜ |
| 4.3 | Procurement → AP — extend (moat vs all rivals) | M | ✅→ |
| 4.6 | Membership / AOCP — deepen | S | ✅→ |
| 4.4 | Snap-a-bill / OCR — extend to more doc types | S–M | ✅→ |

> **AI assistant design rule — model-agnostic routing must be PII/consent-gated.** Routing to external frontier models means patient data could leave to a third party → DPDP risk. Non-negotiable: strip/mask PHI before any external-model call, keep sensitive clinical/financial work on local models, gate everything through the Wave 5 consent layer, confirm-cards on clinical/financial actions. This is also a *sales point* — "your data is gated, not shipped wholesale to OpenAI."

> **Two MCPs, don't confuse them:** `4.1a` = Tulip's model-router/orchestration (the assistant's brain — a buying driver). `MCP` = ecosystem-glue MCP (internal plumbing, unexposed, not a buying driver — see [[project_mcp_decision]]).

**Exit criteria:** DPDP audit signed off (legal deadline cleared) · Tulip role-aware assistant shipping value across ≥2 roles · model-router PII-gated · ops differentiators (job library, procurement, membership) matured.

---

## Phase F — ABDM Full Stack & Interoperability (Jul–Sep 2027)
*Goal: flip ABDM-ready → ABDM-live when you incorporate/enroll. The FHIR engine is already built (Phase 2 complete in `docs/abdm/`), so this is mostly wiring + registration + the national stack.*

| ID | Item | Effort | Status | Note |
|----|------|--------|--------|------|
| 6.0 | **Schema validation vs Eka public docs** — diff our ABDM/prescription/codification tables against Eka's published API schemas (`developer.eka.care`) before building 6.x | S | ⬜ | Free design input. Reference *only* — no Eka API dependency. Cheap; can run any time before this phase. |
| 6.1 | ABHA creation + verification at registration (M1) | M | 🟢 | Local capture built; needs sandbox creds. |
| 6.2 | HPR — register dentists / hygienists | S–M | 🟢 | Capture built. |
| 6.3 | HFR — register branches / facilities | S–M | 🟢 | Capture built. |
| 6.5 | FHIR R4 record format | L | 🟢 | **Engine code-complete & tested** (Phase 2). |
| 6.7 | Clinical coding: SNOMED CT, LOINC, ICD-10/ICD-DA, ADA CDT | M | ⬜ | Terminology maps seeded; extend. |
| 6.4 | M2 link → M3 consent HIE → M4 | XL | ⬜ | Gated on ABDM sandbox→prod creds. |
| 6.6 | NHCX — health claims exchange | L | ⬜ | Lower priority; pairs with 7.4. |

> **Gate:** Phase F depends on you incorporating + getting ABDM sandbox credentials. Per current decision this is deferred until you choose to enroll — the architecture is ready whenever you flip it. If enrollment slips, Phase F slides without blocking anything else.

---

## Phase G — Finance, Pharmacy & Accreditation (Oct–Dec 2027)
*Goal: the compliance long-tail that matters as you scale to multi-clinic / sell to bigger practices.*

| ID | Item | Effort | Status |
|----|------|--------|--------|
| 7.3 | TDS handling for vendor / lab payments | S–M | ⬜ |
| 7.5 | Pharmacy: Schedule H/H1/X dispensing register | M | ⬜ |
| 7.6 | e-prescription standards aligned to ABDM | M | 🟡 |
| 7.7 | Drug license linkage (if dispensing) | S | ⬜ |
| 7.4 | Insurance / TPA claim formats (+ NHCX path) | M | ⬜ |
| 8.3 | NABH digital health / dental accreditation support | L | ⬜ |
| 8.5 | CERT-In incident reporting readiness | S–M | ⬜ |
| 8.4 | ISO 27001 posture | L | ⬜ |

**Exit criteria:** finance/tax/pharmacy compliant · accreditation-support features in place for clinics that want NABH/ISO.

---

## Phase H — Dental Vertical Ecosystem & Network Effects (2028 → · post-core growth)
*Goal: turn the single-clinic product into a dental network — the moves a general EMR (Eka) structurally won't make. Each builds on a module that already exists by launch, so this is leverage, not new foundations. Sequence them one at a time: a multi-sided network dies if you spread thin before either side is dense.*

**Design principle — data stays clinic-owned.** No cross-clinic patient-record sharing by default. Network value comes from connecting *people* (visiting consultants, labs, suppliers) into a clinic's own data with scoped, consented, audit-logged access — **not** from pooling patients. Every item here is DPDP-gated through the Wave 5 consent layer.

**Scope note — ProConsult is a SEPARATE, parallel project** (its own repo/app, built side-by-side). What lives in THIS timeline is the **OS-side network layer**: the integration sockets Dentfluence OS exposes so external network apps can plug in. Three connectors make up the layer — **consultant (→ ProConsult), lab, supplier**. Each connector = the same OS-side primitives: scoped consent-gated case access + scheduling + payment/settlement + verified-profile/ratings hooks. *We build the sockets; ProConsult builds the plug.* ProConsult itself (the Uber-style marketplace UI, consultant directory, request→accept flow) is NOT scheduled here — only the bridge it connects to.

| ID | Item | Effort | Status | Note |
|----|------|--------|--------|------|
| 9.1 | **Network layer — consultant connector (bridge to ProConsult)** | L | ⬜ | OS-side sockets only; **ProConsult itself is a separate parallel project.** Exposes: scoped, time-boxed, **consent-gated** access so a visiting endodontist / oral-surgeon / ortho / implantologist sees ONLY the cases they treat at that clinic (never the wider patient base; clinics never share patients with each other) + visiting-day scheduling + payment/settlement hooks + HPR-verified profile/ratings feed. Clinic owns all data. Confirm-card + Wave 5 consent. ProConsult plugs into these via `/api/v1`. |
| 9.2 | **Network layer — Dental Lab connector (two-sided portal)** — extends Lab module v2 | L | ⬜ | Labs get a login: digital case submission, intraoral-scan files (STL/PLY), shade, try-in scheduling, live status, QR tracking, remake/NEM analytics. Once clinics + labs are both on it, neither leaves. Easiest true two-sided network → **start here (the wedge).** |
| 9.3 | **Dental clinical-data layer** — pairs with item 4.0 | M | ⬜ | Dental-native grounding for Tulip: dental drug formulary, dental treatment protocols (RCT / extraction / implant / perio), and dental calculators — LA max-dose, fluoride, implant torque/sizing, Hounsfield bone density, CAMBRA caries-risk, AAP-2017 perio staging/grading, ortho ceph. Out-*depth* Eka in dentistry; don't match its general breadth. |
| 9.4 | **Specialty mini-modules** — build on charting (Phase D) | L | ⬜ | Ortho (aligner/bracket tracking, photo series, ceph), Implantology (guide→healing→loading timeline), Endo (working length, obturation), Pedo (eruption/sealant, behaviour notes), Perio (maintenance-recall engine). Each = a "built for your specialty" hook. |
| 9.5 | **Network layer — Supplier connector (group-buying)** — extends procurement→AP (Phase E) | M | ⬜ | Shared dental-consumables + implant-system catalog; multi-clinic group buying (better pricing + margin). Implant batch/lot traceability already schema-ready. Third connector of the network layer (consultant 9.1 / lab 9.2 / supplier 9.5). |
| 9.6 | **Dental recall intelligence** — on Comms OS (Phase B) | S–M | ⬜ | Not generic reminders: 6-month hygiene, ortho adjustment cycles, implant follow-up, perio maintenance intervals. Recall = recurring revenue; make it dental-smart. |
| 9.7 | **Smile / case-acceptance tools** | M | ⬜ | Before/after smile galleries, treatment visualisation, dental EMI financing (reuses finance chain). A revenue / case-acceptance *sales* tool, not just records. |

> **Sequencing:** the three network connectors (9.1 consultant / 9.2 lab / 9.5 supplier) share the same OS-side primitives (scoped consent access + scheduling + payment + ratings) — build that shared bridge core *once*, then expose each connector. Lab (9.2) is the wedge to ship first. Consultant connector (9.1) has the highest ceiling but is gated on **ProConsult (separate parallel project)** being ready — keep the two projects in sync on the `/api/v1` contract. Clinical-data layer (9.3) is the cheapest way to make Tulip visibly beat Eka — pair it with 4.0.

> **Why NOT "portable patient records across clinics":** rejected by design. Clinics read it as their patient data being shared / poached and won't adopt. The India-correct network is the *visiting consultant* (9.1), where data never leaves the clinic's ownership and only a trusted, consented professional sees the specific cases they treat.

> **GTM note — ProConsult (separate parallel project):** ProConsult is the Uber-style consultant marketplace — clinic searches a directory → profiles + ratings → request → consultant accepts. It is the **fast-selling wedge** ("front door"); the full Dentfluence OS is the upsell ("the house"). Its moat is **rails, not matchmaking**: case access + in-app payment + verified reputation keep both sides on-platform — pure matchmaking *leaks* (clinic + consultant swap numbers and move to WhatsApp = disintermediation). So ProConsult must carry minimum rails (booking + payment + ratings) from day one; verify credentials via HPR (item 6.2); solve cold-start by seeding the consultant side first. *Built in parallel — this timeline owns only the `/api/v1` bridge (9.1) it plugs into.*

**Exit criteria:** at least one dense two-sided network live (labs or consultants) · Tulip grounded in dental-native data · specialty depth no general EMR matches.

---

## Critical path & parallelism

- **Critical path to launch:** Phase A (security + infra) → Phase C (payments + portal + cloud + DPDP verify). Phase B can overlap A/C partially.
- **Run in parallel where you can:** Wave 4 differentiators (Phase E) are mostly built — small items (4.3/4.4/4.6) can be slotted into gaps in earlier phases as filler.
- **Single biggest post-launch bet:** Phase D (DICOM + imaging + charting). If you must cut scope anywhere, protect this — it's the whole dental-depth moat.
- **Don't let ABDM (Phase F) block launch.** It's deferred-by-design and gated on enrollment; the rest of the app ships without it.

---

## Risks to the timeline

1. **Testing debt.** Most modules are code-complete-but-untested. The schedule budgets test time, but if real-clinic QA surfaces rework, Phases A–B stretch first.
2. **Solo-builder bandwidth.** Every date assumes the pace in Assumptions. One serious illness/week-off ripples downstream — keep a 2–3 week buffer per phase.
3. **DICOM integration unknowns.** Phase D's DICOM (3.5) is the least-scoped item; integrate an existing viewer (OHIF/dcm4che) rather than build, or it eats the phase.
4. **Cloud migration data-residency.** Must land before launch (Phase C) — don't switch on cloud PHI until DPDP verify + India-region hosting are both green.
5. **Payment gateway + GST** have external dependencies (KYC, GSTN); start integrations early in Phase C.

---

## What changed vs the roadmap doc

- **DPDP Wave 5 marked ✅ DONE** — converted from a build wave to two checkpoints: pre-launch verify (Phase C) + legal-deadline audit (Phase E, before 13 May 2027).
- **Cloud migration + sync layer added** as explicit 🔵 infra items (consequence of the cloud-only pivot) — these gate launch.
- **"Local/private AI" dropped** as a feature line (retired with the pivot; see comparison doc).
- **Everything re-sequenced onto a calendar** with a defined **launch milestone** instead of an open-ended wave order.
- **Two Eka-derived research items added** (2026-06-28): `6.0` schema validation vs Eka's public API docs, and `4.0` drug-data sourcing decision. Both treat Eka as a *schema reference / competitor*, never an API dependency — Eka's site is a platform play (`developer.eka.care`); we mine its structure for free but build/consume from neutral sources.
- **Tulip reframed as role-aware personal assistant** (2026-06-28): `4.1` is now the "everyone's assistant" layer (dentist/consultant/front-desk/staff/patient personas) on an **indigenous, model-agnostic MCP router** (`4.1a` — routes Claude/GPT/Gemini + local; moat = orchestration + dental grounding, not the model), plus `4.1b` chairside voice secretary and the patient layers `4.1c` 24/7 service front desk + `4.1d` personal health companion. Refinement: **staff personas are full *personal* assistants** (whole work life — schedule/earnings/learning/reminders — chairside is just one mode), while the **patient is a different, external relationship → two layers** (service desk + health companion). Hard rule: external-model routing is **PII/consent-gated** (DPDP). The ecosystem-glue `MCP` (unexposed, not a buying driver) is kept distinct from `4.1a` (the assistant brain, IS a buying driver).
- **Phase H — Dental Vertical Ecosystem added** (2026-06-28): the network-effect moves a general EMR won't make. Core correction: dropped "portable patient records across clinics" (clinics read it as data poaching) in favour of the **visiting-consultant model** — India's consultants travel between clinics, so the *consultant* moves, not the patient data; clinic keeps ownership, access is scoped + consent-gated.
- **Phase H reframed as the OS-side NETWORK LAYER** (2026-06-28): **ProConsult is a separate, parallel project** — this timeline holds only the integration *sockets* the OS exposes, not the ProConsult marketplace app. Three connectors share one bridge core: consultant (`9.1` → ProConsult), lab (`9.2`, the wedge), supplier (`9.5`). Shared primitives: scoped consent-gated case access + scheduling + payment/settlement + HPR-verified profile/ratings, all over `/api/v1`. Marketplace moat = *rails (case access + payments + verified reputation)*, not matchmaking, which leaks via disintermediation.
