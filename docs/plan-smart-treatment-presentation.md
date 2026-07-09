# Smart Treatment Presentation — Product Design & V1 PRD

*Prepared 2026-07-09. New, independent module. Does not modify the Treatment Plan module or any other existing module — the only touch point on existing screens is one new button ("Create Smart Presentation") added to the Treatment Plan view.*

*Grounded in the actual codebase (models/services cited by file path below), not assumptions — see the "Integration Ownership Matrix" for exact reuse points.*

---

## 0. Position in one sentence

Smart Treatment Presentation owns everything between **Treatment Plan created** and **Treatment Accepted** — it is a communication layer, not a clinical one. It never becomes a second source of truth for diagnosis, procedures, or money; it only presents what those systems already contain, and hands "accepted" back to the systems that already own that concept.

---

## 1. Complete Workflow: Treatment Plan → Treatment Accepted

```
[Treatment Plan exists in Treatment Plan module — status: draft/active]
        │
        │  Dentist clicks "Create Smart Presentation" (new button, existing screen)
        ▼
[Presentation: Draft]
        │  Engine imports a live read of Patient + Consultation + TreatmentPlan + Invoice
        │  AI drafts patient-facing summary + auto-suggests Clinical Library media
        ▼
[Presentation: Pending Review]  ← dentist edits AI text, doctor message, toggles media
        │  Dentist confirms review (reviewed_at set — mandatory gate, see §8)
        ▼
[Presentation: Finalized]  ← an immutable point-in-time snapshot is taken here (see §8,
        │                    "never duplicate treatment data" resolved as a render-cache,
        │                    not a competing source of truth)
        │  Dentist/receptionist sends via WhatsApp or copies secure link
        ▼
[Presentation: Sent]  → event: presentation.sent
        │
        ▼
[Presentation: Viewed]  (0, 1, or many times, timestamped)  → event: presentation.viewed
        │
        ├──► [Accepted]  → event: presentation.accepted → calls the EXISTING
        │                   TreatmentPlanController::accept() action — this module
        │                   never writes treatment_plans.status itself
        │
        ├──► [Declined]  → event: presentation.declined → receptionist follow-up task
        │
        └──► [No response / Follow-up Required]  → receptionist manual follow-up
                                                     (V1: manual; V3: automated nudge)
```

Terminal state for this module is always a hand-off: acceptance is recorded by the Treatment Plan module (as it already is today for in-clinic acceptance); this module just triggers that same action from a patient-initiated event.

---

## 2. Screens

| # | Screen | Purpose | Primary user | Key actions | Info shown | Navigates to |
|---|---|---|---|---|---|---|
| 1 | **Presentations List** (module home) | See every presentation and its status at a glance | Receptionist, Dentist | Open, Resend, Duplicate, New | Patient, plan name, status chip (Draft/Sent/Viewed/Accepted/Declined/Follow-up), last activity, created-by | Builder or Detail |
| 2 | **Builder** | Assemble a presentation from an imported plan | Dentist | Edit AI summary, add doctor message, toggle media, Preview, Finalize & Send | Read-only plan/patient header, phases, live cost pull from Billing, media suggestions | Preview, Send |
| 3 | **Preview (Patient Simulator)** | See exactly what the patient will see, mobile-first | Dentist | Toggle mobile/desktop, back to edit | Full rendered patient view | Builder |
| 4 | **Send / Share** | Choose delivery channel | Dentist, Receptionist | Send via WhatsApp, Copy secure link | Channel status, consent check result | Detail |
| 5 | **Presentation Detail / Activity** | Track one presentation's lifecycle | Receptionist, Dentist | Resend, Regenerate/Revoke link, add follow-up note, Mark Accepted | Status timeline, view count + last viewed, follow-up notes | List |
| 6 | **Templates** *(V2)* | Clinic-wide defaults | Admin/Dentist | Set default doctor-message boilerplate, default media per procedure | Template list | Settings |
| 7 | **Shared Links** | Security/control surface for all active links | Admin, Receptionist | Revoke, view expiry, regenerate | Link, presentation, expiry, status | Detail |
| 8 | **Activity (module-level)** | Aggregate feed across all presentations | Receptionist, Dentist | Filter by status/date | Same underlying `Activity` log PRE already owns, filtered to `presentation.*` — **not a new data store** | Detail |
| 9 | **Analytics** *(V3 — sketch only, not built in V1)* | Acceptance rate, decision time, most-declined procedures | Admin, Dentist | — | — | — |
| 10 | **Settings** | Module configuration | Admin | Auto-suggest media on/off, default link expiry, who can create presentations | Toggles | — |

---

## 3. Sidebar Placement

```
Dashboard
Patients
Appointments
Consultations
Treatment Plans
⭐ Smart Treatment Presentation      ← new, independent top-level entry
Billing
Inventory
Lab
Marketing
Settings
```

Sits between Treatment Plans and Billing — matching the actual order of the workflow (plan → presented/explained → billed/paid), without implying it owns either neighbor's data.

## 4. Internal Navigation

```
Smart Treatment Presentation
├── Presentations   (home/list — default view)
├── Shared Links
├── Activity
├── Templates        (V2)
├── Analytics         (V3, hidden until built)
└── Settings
```

---

## 5. User Journeys

**Dentist.** Finishes a Treatment Plan → clicks "Create Smart Presentation" → Builder opens pre-filled → reviews/edits the AI summary and adds a one-line personal note → previews as patient → hits Finalize & Send → picks WhatsApp → done. Later, sees "Accepted" chip appear on their own dashboard.

**Receptionist.** Opens Presentations list each morning → filters to "Sent, not yet viewed 24h+" and "Follow-up Required" → calls those patients → logs a follow-up note on the Detail screen → if patient verbally accepts on the call, uses "Mark Accepted" (which triggers the existing Treatment Plan acceptance flow) and books the appointment in the Appointments module.

**Patient.** Gets a WhatsApp message with a secure link → opens on their phone → sees their name, photo header, diagnosis in plain language, treatment phases, cost breakdown pulled live from Billing, relevant education videos, and the doctor's personal note → can forward the link to family (no special "mode" needed — the link itself is shareable) → taps Accept or Decline, or just closes it (follow-up path).

**Administrator.** Configures who is allowed to create/send presentations, sets default link expiry, reviews the Shared Links screen periodically to revoke stale/unused links, and (once V2/V3 land) sets clinic-wide templates and reviews acceptance analytics.

---

## 6. Integration Ownership Matrix

*Grounded in the current codebase — file paths cited so this is verifiable, not aspirational.*

| Module | What's used | Access | Who owns writes | Reuse point |
|---|---|---|---|---|
| Patient | Name, age, gender, photo, medical history | Read-only | Patient module | Standard Eloquent read |
| Consultation | `chief_complaint`, `primary_diagnosis`, `findings_summary_final`, etc. (`app/Models/Consultation.php`) | Read-only | Consultation module | Several fields are `Encrypted`/`EncryptedArray` casts — must read through Eloquent, never raw SQL |
| Treatment Plan | `TreatmentPlan` + `TreatmentPlanItem` (phases, fees, tooth numbers) | Read (snapshot at Finalize) + trigger existing accept action | Treatment Plan module | **This module never sets `treatment_plans.status`.** "Accepted" from a patient always calls the existing `TreatmentPlanController::accept()` action, same one used for in-clinic acceptance |
| Billing | `Invoice` (`subtotal, discount_amount, membership_discount, wallet_applied, total_amount, balance_due`) via `TreatmentPlan->invoices` | Read-only, **live** (not snapshotted — cost must always reflect current billing truth) | Billing module | Confirmed direct FK: `Invoice.treatment_plan_id` |
| Clinical Library | `TreatmentMedia` (`belongsTo Treatment` via `treatment_id`) | Read-only, auto-suggest | Clinical Library module | **V1 should only wire `TreatmentMedia`.** Two other library subsystems (`EducationMedia` — separate taxonomy; `ClinicalMedia` — fuzzy text-match only) are not reliably linkable yet; full auto-suggestion across all three depends on the Clinical Library metadata cleanup already planned (see project memory: Clinical Digital Library Audit) |
| PRE / Activity | `presentation.created/.sent/.viewed/.accepted/.declined` | **Publish only** | PRE's `ActivityEngine`/`RulesEngine` | Copy the exact existing producer pattern: `app(ActivityEngine::class)->log(subject:, event:, actor:, metadata:, relationshipId:, description:)` — same call `InvoicePaymentService` already uses for `payment.received`. No new event bus needed |
| WhatsApp delivery | Send the presentation link | Direct call | `OutboundMessageService` (existing, already used by Recall/Review/Lab modules — not PRE-exclusive) | Consent gating (`consentGate()`) is already enforced there — do not re-implement consent logic |
| Secure Link | Generate/expire/revoke a patient-facing link | **New, owned entirely by this module** | This module | **Genuine gap:** no signed-route or token infrastructure exists anywhere in the codebase today. Must be built new — budget it as real engineering, not a checkbox (see §8) |
| Marketing | Review request / testimonial capture after acceptance | None in V1 | Marketing module (future) | Only a future event hook on `presentation.accepted` — explicitly out of V1 |

---

## 7. Standalone-SaaS Architecture (same engine, multiple front doors)

The Presentation Engine (AI summary generation, template rendering, mobile view, media attachment, link/token issuance, delivery, event emission) should operate on one canonical internal shape — call it `PresentationCase` — and never touch Dentfluence's Eloquent models directly. A thin adapter layer feeds it:

- **DentfluenceAdapter** — reads `TreatmentPlan` + `Consultation` + `Patient` + `Invoice`, maps to `PresentationCase`. (V1 build.)
- **ManualEntryAdapter** — a simple form for a dentist with no Dentfluence account to type diagnosis/procedures/cost directly. (Standalone V1.)
- **CSVAdapter** — bulk import for clinics migrating off spreadsheet-based quoting. (Standalone, later.)
- **APIAdapter** — a public endpoint accepting the same canonical schema, for future third-party PMS integrations. (Standalone, later.)

Event emission is behind an `ActivityPublisher` interface: a `DentfluenceActivityPublisher` wraps the existing `ActivityEngine::log()`; a future `StandaloneActivityPublisher` would write to its own lightweight table. Same pattern the PRE→standalone-CRM spin-off is already using — the two standalone products could eventually share an auth/billing shell.

This is the one piece of V1 that **is** worth engineering deliberately even though only one adapter ships now: if `PresentationCase` leaks Dentfluence-specific fields (e.g. raw `TreatmentPlanItem` objects) into the engine, standalone spin-off later becomes a rewrite instead of "add an adapter."

---

## 8. Challenge — Where This Proposal Is Weak

**Weak assumptions**

- *"Never duplicate treatment data"* directly conflicts with needing a stable patient-facing record once sent. Resolution: take an immutable point-in-time **snapshot** at Finalize (a render cache, like an invoice PDF freezes billing state at generation time) — Treatment Plan remains the sole clinical source of truth; the snapshot is provenance, not a competing record.
- *"No analytics in V1"* directly conflicts with the receptionist's own stated V1 job: "knows whether patient viewed it." Viewed-status **is** analytics in its simplest form. Resolution: V1 includes minimal status/event tracking (sent/viewed count/accepted/declined); true analytics — decision time, objection logging, engagement scoring — stays deferred to V3.
- Auto-surfacing Clinical Library content next to procedures assumes a clean, unified library. It isn't one yet — three parallel, inconsistently-linked media systems exist today. Only `TreatmentMedia` is safely wireable now; the rest is blocked on the Clinical Library metadata cleanup that's already planned separately.

**Workflow problems**

- Undefined behavior if a dentist edits the Treatment Plan *after* a presentation has already been sent or viewed. Silent divergence between what the patient saw and what they're later billed is a trust and liability risk. Recommend: editing a plan with an active Sent/Viewed presentation should flag that presentation "Outdated — regenerate," not fail silently.
- Acceptance must have exactly one owner. This module records the *event* that the patient accepted; the Treatment Plan module's existing accept action is what actually flips clinical status. Two independent "accepted" flags would drift out of sync.

**Commercial risks**

- If creating a presentation is one extra deliberate step, busy dentists will skip it for anything but big-ticket cases — undermining the case-acceptance ROI the whole module is sold on. The single new button on the Treatment Plan screen needs to be the obvious next action, not a detour.
- This module and the PRE standalone-CRM spin-off are both, at their core, "communication layer" products aimed at a similar buyer (a dentist with practice software but no communication tooling). Selling both separately without a clear differentiation story risks confusing the same prospect twice.

**Engineering risks**

- The secure, expiring, revocable link is **not** a minor add-on — nothing like it (no signed routes, no token model) exists anywhere in this codebase today. It's real V1 engineering, and it's the one component carrying PHI (diagnosis, cost, photos) to an unauthenticated recipient, so it needs the same rigor as the rest of the app's PHI handling, not a shortcut.
- AI-generated explanations of a diagnosis carry real accuracy/liability exposure if a dentist rubber-stamps them. The mandatory review gate should be enforced at the data layer (a required `reviewed_at` timestamp before status can move to Sent), not left as a UI convention a rushed dentist can bypass.

**Adoption risks**

- "Viewed" status will under-report reality (forwarded screenshots, family viewing on a different device) and can't be advertised as proof of viewing — set expectations with staff as a soft signal, not a guarantee, or they'll distrust the feature the first time it's wrong.

**Cut, delay, or never build**

- **Cut:** "Family discussion mode" (V4) — a shareable link is already forwardable; a dedicated mode adds UI for something already solved.
- **Delay/rescope:** "Language adaptation" (listed as V4) is arguably higher-value *sooner* than framed — Dentfluence is India-first, so this isn't BrightPlans' 27-language global problem, it's "does this patient's explanation come out in Hindi or a regional language." Worth revisiting for V2, scoped narrowly (a couple of languages actually spoken by the clinic's patients), not general i18n infrastructure.
- **Question before building:** "Objection logging" (V3) assumes a busy receptionist will reliably type in why a patient hesitated. Free-text capture at a front desk under time pressure tends to go unfilled. If built at all, use single-tap reason chips, not a text field — otherwise it becomes UI nobody uses.
- **Needs sign-off before ever shipping:** "Treatment comparison" and "Delay consequences" (V4 AI) edge from communication into clinical advice — predicting consequences of delaying treatment is a claim a dentist, not an AI, should be making. Any generated language here needs explicit dental-professional review built into the workflow, not just "AI explains it."

---

## 9. V1 Product Requirements Document

**Goal.** Help patients understand and accept treatment plans that already exist in Dentfluence, via a mobile-first, dentist-reviewed presentation delivered by WhatsApp or a secure link.

**In scope (V1).**
- Import a read-only snapshot of an existing Treatment Plan (procedures, phases, fees), its Consultation diagnosis fields, Patient basics, and live Billing figures.
- Auto-suggest `TreatmentMedia` items matching the plan's procedures; dentist toggles inclusion.
- AI-drafted, plain-language patient-facing summary — **mandatory dentist review before send** (data-layer gate, not just UI).
- Doctor's personal message (free text).
- Mobile-first patient view (no PDF in V1 — simpler than the BrightPlans "Classic" format, deliberately, per the brief's own scope).
- Delivery via existing `OutboundMessageService` (WhatsApp, already consent-gated) plus a new secure, expiring, revocable link.
- Status lifecycle: Draft → Pending Review → Finalized → Sent → Viewed (count + last-viewed) → Accepted / Declined / Follow-up Required.
- "Mark Accepted" calls the existing Treatment Plan acceptance action — no parallel acceptance state.
- Events published via the existing `ActivityEngine` (`presentation.created/.sent/.viewed/.accepted/.declined`) so PRE's timeline and future automation rules see this module without it owning any messaging logic itself.

**Explicitly out of scope for V1.** Analytics dashboards beyond basic status counts, 3D visuals, AI recommendations/objection logging, templates/branding polish (V2), Marketing hooks (future), building out the CSV/API/manual-entry adapters (design the seam in §7, don't build the extra adapters yet).

**Conceptual data model** (no code, for planning only):
- `Presentation` — id, uuid, treatment_plan_id, patient_id, status, ai_summary_text, doctor_message, reviewed_at, sent_at, created_by
- `PresentationSnapshot` — presentation_id, JSON snapshot of imported plan/cost data at Finalize (the render cache from §8)
- `PresentationMediaItem` — presentation_id, treatment_media_id, included (bool)
- `PresentationAccessToken` — presentation_id, token, expires_at, revoked_at, last_viewed_at, view_count
- No new events table — reuses the existing `Activity`/`ActivityRecorded` system.

**Permissions (confirmed).** The dentist authors and owns the clinical content — the AI summary, doctor's message, and media selection are always drafted/edited/finalized by the dentist; that judgment doesn't get delegated. Staff (receptionist/admin) get operational access to the module once a presentation is finalized: they can view status, send, resend, revoke/regenerate links, log follow-up notes, and use "Mark Accepted" — but they cannot author or edit the AI summary or doctor's message. Reuses the existing role/permission system; no new roles invented — this is a permission-scope split (author vs. operate) within the module, not a new role.

**Review gate (confirmed).** Hard block. `reviewed_at` must be set by the dentist before status can move to Sent — enforced at the data layer, not a skippable UI convention.

**Link expiry (confirmed).** Ships with a fixed clinic-wide default (recommend 30 days — long enough to cover the realistic decision window for a treatment plan, short enough that a stale link isn't sitting open indefinitely). Per-link, staff/dentist can extend the expiry or toggle it off entirely (never-expire), and can always manually revoke from Shared Links regardless of expiry setting.

**Resend behavior (recommendation).** Every resend issues a fresh access token and immediately auto-revokes the previous one — never leave two valid links for the same presentation floating around (WhatsApp messages get forwarded and cached longer than people expect, so old links shouldn't stay live once a new one exists). On top of that default, add one staleness check: if the resend happens after the presentation's original send is older than roughly the expiry window (i.e., a month-plus gap, matching your example), the system should compare the snapshot against the current Treatment Plan/Billing state before resending. If nothing changed, resend goes straight out with the new token. If the plan or cost changed in the meantime, prompt the dentist to re-review before resending rather than silently sending numbers that no longer match — this is the same "don't let the patient see stale figures" principle already flagged in §8's workflow risk, just applied to the resend path specifically.

**Minimum success signals to track from day one** (even manually, since full analytics is V3): share of accepted plans that went through a presentation vs. not; average time between Sent and Viewed; resend rate.

**Rollout constraint.** The only change to any existing screen is one new button on the Treatment Plan view ("Create Smart Presentation") that navigates into this module — consistent with "do not modify existing modules."

**Open decisions — resolved 2026-07-09.**
1. Authorship vs. access: dentist devises/owns all clinical content; staff get operational access (send/resend/follow-up/links) but not authoring rights. ✅
2. Review gate: hard block, enforced at the data layer. ✅
3. Link expiry: fixed default (recommend 30 days), extendable or toggle-off per link, always manually revocable. ✅
4. Resend: always issues a fresh token + auto-revokes the old one, plus a staleness check against current plan/billing data when the gap since last send is a month or more — flag for re-review only if something actually changed. ✅

---

*No Laravel code was written for this document, per the brief. Ready to move into an implementation slice plan once the open decisions above are confirmed.*
