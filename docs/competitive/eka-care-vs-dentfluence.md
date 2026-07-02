# Eka Care vs Dentfluence — Competitive Brief

*Prepared 2026-06-27. Sources: eka.care, ekascribe.ai, eka.care/s/for-doctors/dentists, product.eka.care pricing page, info.eka.care/stories/tags/eka-tech. All figures are Eka's own marketing claims unless noted.*

---

## TL;DR

Eka Care (legal entity **Orbi Health Pvt Ltd**) is the closest large-scale analogue to what Dentfluence is becoming: an **AI-native, ABDM-certified health OS** for India. They are broad and multi-specialty (dentistry is one of five named verticals); you are deep and dental-only. They have scale and government certification you don't; you have on-prem/local AI, dental-specific depth, and full ownership of the stack they don't expose.

The three places they directly threaten your roadmap: **(1) ABDM/ABHA certification at scale, (2) an ambient AI scribe (EkaScribe) that overlaps your Voice Notes + Tulip work, and (3) a shipped dental EMR with charting, quadrant views, and treatment estimates.** The three places you can win: **dental depth (DICOM imaging, lab, memberships, PRM), flat bundled pricing (vs their per-message/per-GB metering), and dental-native UX for an owner-operator.**

---

## ⚠️ Strategy Update — Cloud-Only Pivot (2026-06-27)

The decision to go **cloud-only after launch** changes the moat math. Recording it here because it supersedes the original framing below:

- **"Local / private AI" is no longer a differentiator.** Once Dentfluence is cloud-hosted, the "PHI never leaves the clinic" pitch evaporates — you inherit the *same* data-residency posture as Eka, except they're already NHA/ABDM-certified and you're not yet. **Do not market on privacy/residency.** Where the original doc below claims this as a win, treat it as retired.
- **Dental depth becomes the *entire* moat.** With privacy gone, what survives the cloud move is dental-native depth: DICOM imaging, lab workflow, memberships/AOCP, EMI, procurement→AP, role/SOP ops. These must now carry the whole differentiation story.
- **Offline-capability is a liability, not a feature — replace it with sync.** A full local DB on a clinic PC = theft/breach risk, no central audit, no remote wipe. Correct model: **cloud is the source of truth + an encrypted, minimal offline *cache* (sync layer)** so the app survives connectivity drops without the security exposure. "Works when the internet drops" is the feature; "the database lives on the front desk" is the risk.
- **Compete on pricing *structure*, not price.** You can't out-discount Eka's scale economics. Win with **flat, bundled pricing and no metering** (no per-message Eka-Credits, no per-GB storage overage, no per-minute tele-consult) — the exact thing their own target customer resents.
- **DPDP is now a launch prerequisite, not future work.** The moment you hold PHI on your own cloud you are a **Data Fiduciary** under DPDP (enforcement 13 May 2027, ₹250cr penalties). Wave 5 (consent, rights, breach pipeline, retention/purge) **is already built** — so this is a *verify-before-launch* checkpoint, not a build. Don't switch on the cloud until it's audited.

**Revised one-line positioning:** *not "more private than Eka" (you won't be) but "dental-native depth + flat predictable pricing + ABDM-ready when you want it"* — for the dentist who finds Eka generic and nickel-and-diming.

---

## Part 1 — Competitive Comparison

### Company & positioning

| | **Eka Care** | **Dentfluence** |
|---|---|---|
| Tagline | "The AI-Native Ambient Digital Health Platform" | Dental clinic OS (Elite app + standalone modules) |
| Entity | Orbi Health Pvt Ltd | Dentfluence (your group) |
| Scope | Multi-specialty health OS (dentists, pediatricians, cardiologists, neurologists, ophthalmologists) | Dental-only, end-to-end |
| Hosting | Cloud (AWS, India-region servers) | Local / on-prem (Laragon, local MySQL, local AI) |
| AI stack | Cloud LLMs + own models (Parrotlet ASR, BODHI knowledge graph, MedAI) | Local Ollama (qwen2.5/llama3.1), faster-whisper GPU, qwen2.5vl vision |
| Certification | NHA-approved, ABDM-compliant, FHIR-compliant, HIPAA, ISO 27001 | ABDM/FHIR **design + local-readiness built**, not yet certified/live |
| Scale (claimed) | 33K+ clinics, 90K+ doctors, 20M+ ABHA, 140M+ records, 1M+ scribe sessions | Single-builder, pre-launch |

### Product suite

**Eka Care** ships a coordinated suite:
- **EkaDoc EMR** — clinic OS: queue management, billing/payments, video + in-person visits, e-prescriptions, WhatsApp messaging, GMB/online-presence analytics.
- **EkaScribe.AI** — ambient voice-to-prescription scribe (own product + site).
- **EkaCare PHR** — patient-facing health-record app (ABHA, records analyser).
- **Medical Records Analyser** + **CDSS** — record structuring and real-time clinical decision support (drug-interaction + investigation alerts).
- **Developer Portal / ABDM Connect** — public APIs, ABHA verification, FHIR exchange.
- **Conversational Health Platform** — voice/chat AI agents for booking, symptom checks, FAQs.

**Dentfluence** already covers, dental-specifically: patients, consultations (4 workflows), treatment plans + visits, prescriptions (CDSS + overrides), memberships (AOCP), billing/finance (wallet, ledger, EMI), procurement (PO→GRN→invoice→AP), lab module, huddle/job-library, PRM, voice notes, receipt-scan, and the "Tulip" AI copilot — plus a Flutter mobile app and an in-flight ABDM/FHIR layer.

### How the AI stories differ

Eka's tech blog (info.eka.care/eka-tech) shows a serious in-house AI program aimed at *India-scale cloud inference*: **Parrotlet-a v2** (medical ASR), **Parrotlet-e / Eka-IndicMTEB** (multilingual embeddings), **Parrotlet-v-lite-4b** (vision LLM for medical records), **BODHI** (clinician-curated clinical knowledge graph in Neo4j), and an **MCP-tool + benchmark** layer ("grounding frontier models in Indian clinical reality"), partnering with **NVIDIA Nemotron 3 Nano Omni**. This is a cloud, GPU-fleet strategy.

Your strategy is the mirror image: **local, on-device/on-prem inference** (Ollama, faster-whisper) so PHI never leaves the clinic. That is a genuine differentiator on data residency and cost — but you are not going to out-research their model team, so frame yours as *"private, local AI"* not *"better models."*

### Strategic read

- **They go wide; you go deep.** Their dental module is competent but generic. Your dental depth (memberships, lab, EMI, procurement, role-based job library) has no equivalent in their offering.
- **Their moat is certification + scale.** ABDM/NHA approval and 20M ABHAs is a B2B2G trust signal you can't fake — but your ABDM-First architecture means you can be *certifiable* on the same standard when you choose to enroll.
- **Their pricing reveals the SMB target.** ₹17K–₹100K/yr base plans + per-message/per-minute/per-GB metering (see Part 3). A solo dentist who dislikes metered WhatsApp credits and storage overages is your ideal switcher.

---

## Part 2 — Feature-Gap Analysis vs Your OS Roadmap

Mapping Eka's shipped features onto your 8-wave roadmap (`docs/plan-os-feature-roadmap.md`) and ABDM blueprint (`docs/abdm/`).

### Where Eka is ahead of you (watch / close)

| Eka capability | Your status | Gap severity | Note |
|---|---|---|---|
| ABDM/ABHA **live + NHA-certified** | Architecture + local-readiness built, **not live/certified** | High (strategic) | You're deliberately local-first; gap is by choice, but certification is their headline moat. |
| **EkaScribe** ambient scribe (20+ langs, "learn my style", Chrome-extension EMR insert) | Voice Notes Phase 1 + Tulip copilot designed | Medium | Overlaps your AI roadmap. Their scribe is a *finished, paid* product; yours is in-progress. |
| **CDSS** at scale (drug interactions, investigation suggestions, ambient in-workflow) | CDSS + overrides exist in Rx module | Low–Med | You have the bones; theirs is marketed harder and graph-backed (BODHI). |
| Patient **PHR app** (records analyser, family health) | Not in scope | Low | Different audience; not your priority as a clinic OS. |
| **Conversational AI agents** (booking, symptom-check, FAQ 24/7) | PRM AI plan (`docs/plan-prm-ai.md`), unstarted | Medium | This is your PRM-AI wave — they've shipped it. |
| Built-in **payment gateway + WhatsApp Business** + GMB reviews | Finance/wallet built; comms in PRM/Marketing | Medium | They bundle online-presence + payments natively. |

### Where you are ahead of Eka (your moat — defend & market)

| Your capability | Eka status | Why it matters |
|---|---|---|
| **DICOM imaging** (OPG/CBCT/intraoral viewer) — top roadmap gap | They show "view X-rays" but no DICOM viewer/AI | Biggest *dental-specific* functional differentiator once you ship it. |
| **Lab module** (enterprise rebuild, 6-phase) | None | Dental labs are core to a dental clinic; they have nothing. |
| **Memberships / AOCP** with finance chain | None | Recurring-revenue engine for clinics; absent in Eka. |
| **Procurement → AP** (PO/GRN/vendor-invoice) | Basic billing only | Real inventory/finance ops depth. |
| **EMI / instalment billing** | "Flexible payment options" (estimates only) | You have an actual EMI engine. |
| **Role-based job library + SOPs + huddle** | None | Practice-ops layer; no Eka equivalent. |
| ~~**Local / private AI** (PHI never leaves clinic)~~ | Cloud-only | **RETIRED** — see Cloud-Only Pivot above. No longer a moat once you're cloud too. |
| **Flat bundled pricing** (no per-msg/GB/min metering) | Metered (Eka-Credits, storage/tele overages) | Predictable cost; their own SMB target resents metering. |
| **Cloud + encrypted offline sync cache** | SaaS, online-dependent | Resilience to connectivity drops *without* the local-DB security liability. |

### Roadmap implications (recommended moves)

1. **Don't chase their scribe head-on — finish yours as a *dental-native* scribe.** ~~Lead with local transcription~~ (retired with the cloud pivot). Instead lead with dental-specific output: tooth-numbered findings, procedure/CDT-coded notes, treatment-plan auto-draft — clinical structure their generic scribe doesn't produce.
2. **Accelerate DICOM imaging (Wave gap).** It's your sharpest dental wedge and they don't have it. This is where "dental-specialized" becomes visible.
3. **Keep ABDM-First moving but stay local until you incorporate/enroll.** Your blueprint already makes you *certifiable*; you don't need to certify before they force the issue. DPDP (13 May 2027) is the real clock, not Eka.
4. **Productize the membership + EMI + lab triad as the "things Eka can't do for a dental clinic" pitch.** This is your clearest narrative against a bigger, broader competitor.
5. **Watch BODHI / Parrotlet.** If they open these via the Developer Portal/MCP, you could *consume* their models locally-adjacent rather than rebuild — worth a periodic check of info.eka.care/eka-tech.

---

## Part 3 — Eka Dental EMR + Pricing Deep-Dive

### Dental EMR (eka.care/s/for-doctors/dentists)

Marketed as "Eka Dental Software," positioned around a **"Dental Specialization Ecosystem"** with five pillars:

1. **Appointment & Scheduling** — efficient appointment management, real-time updates & reminders, customizable slots/availability.
2. **Patient Records & History** — complete dental records, view X-rays + treatment history.
3. **Dental Charting** — digital charts "in seconds," mark conditions (cavities, root canals, crowns) with clicks, **quadrant view** for adult / pediatric / mixed dentition, printable charts.
4. **Treatment Planning & Estimates** — procedures + timelines + costs, comprehensive cost estimates, "flexible payment options."
5. **AI-Powered Clinical Guidance** — medication suggestions, treatment protocols, drug-interaction alerts.

Trust/compliance badges: **Google Partner, ABDM-compliant, AWS-secured, FHIR-compliant, NHA-approved.** They also push the **DHIS government-incentive scheme** (toll-free 1800-111-663, "Create your HPR") as a revenue hook for doctors.

**"Eka Promise":** 100% refund within the first 2 weeks if unsatisfied.

Their own comparison table ("Eka Dental vs Other Dental Software") claims advantages on: patient management, appointment scheduling *with AI optimization*, dental charting with quadrant views, billing & insurance, customizable reporting/analytics, advanced HIPAA-compliant security, customizable templates, support & training, treatment plans, estimates, print dental chart, add procedures.

> **Reality check for positioning:** This is solid generic dental EMR — charting, estimates, scheduling, Rx. What's *absent*: DICOM imaging/AI radiology, lab workflow, membership plans, procurement/inventory, EMI engine, role/SOP ops. That absence is exactly your dental-depth wedge.

### Pricing (product.eka.care/practice-management/plan-pricing)

All prices **exclusive of 18% GST**. Annual billing shown discounted vs list.

| Plan | List | Annual (discounted) | Seats included | Add'l Premium seat | Add'l Basic seat |
|---|---|---|---|---|---|
| **Eka Doc Plus** (solo) | ₹19,999/yr | **₹16,999/yr** | 1 doctor + 2 staff | +₹16,999 | +₹8,000 |
| **Eka Doc Pro** (solo, +Rx/analytics) | ₹24,999/yr | **₹18,749/yr** | 1 doctor + 2 staff | +₹18,749 | +₹8,000 |
| **Eka Clinic Pro** (multi-doctor) | ₹1,25,000/yr | **₹1,00,000/yr** | 5 doctors + 15 staff | +₹18,749 | +₹8,000 |

*Clinic Pro is annual-billing only.*

**Metered usage on top of base plans:**
- **Storage:** 20 GB free (100 GB on Clinic Pro), then **₹1,200 per 20 GB**.
- **Tele-consultation:** 200 min free, then **₹0.50/min**.
- **WhatsApp messages:** **1 Eka Credit per message** (₹1 = 1 Eka Credit).

**Add-ons (annual):** In-clinic Pharmacy ₹10,999 · Eka Credits from ₹500 · Success Manager ₹4,999 · Personalised Website ₹19,999 · Own Your Communication (WhatsApp/SMS from own account) ₹24,999 · Custom Form Builder ₹9,999 (non-refundable).

**Plan tiering highlights:**
- *Plus* = digitize + online presence basics; *Pro* adds specialisation-based Rx pad, Rx templates, e-lab + medicine home delivery, full practice/revenue/treatment-efficacy analytics, e-labs/e-pharmacy revenue access.
- Support is 9 AM–9 PM for all; Success Manager (virtual/in-person) is add-on for Plus/Pro, included in Clinic Pro.

### EkaScribe pricing (separate product, ekascribe.ai)

- **₹1,499/month per doctor** (billed monthly; ~₹50/day), **17% off on yearly**.
- **Free plan:** 5 consultations/day. **Pro:** unlimited.
- 20+ languages, "learn my style," Pro/Lite model picker, custom template library, **one-click Chrome-extension** insert into any web EMR.
- HIPAA + ISO 27001 + FHIR; data on India-based servers; 7-day refund window.
- Claims: 12+ hrs saved/week, 80% admin reduction, 1,400+ active users, 4.8/5 from 500+ doctors.

### Cost takeaway for your pitch

A solo dentist on **Eka Doc Pro + EkaScribe** is roughly **₹18,749 + (₹1,499×12 ≈ ₹17,988) = ~₹36,700/yr + 18% GST**, *before* WhatsApp credits, storage overages, and tele-consult minutes. The metered model (per-message, per-minute, per-GB) is where a privacy-conscious, cost-sensitive owner-operator feels nickel-and-dimed — and where a flat, locally-hosted Dentfluence with bundled dental depth has a clean counter-narrative.

---

*Note: all market-share, user-count, and accuracy figures above are Eka's own published marketing claims and are not independently verified. Pricing captured 2026-06-27 and may change.*
