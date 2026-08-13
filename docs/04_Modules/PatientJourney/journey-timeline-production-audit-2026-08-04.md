# Journey Timeline — CTO Production Readiness Audit
**Date:** 2026-08-04 · **Auditor role:** Chief Product Architect / CTO pass · **Method:** direct code read (not repeated grep sampling) of every file below; no functionality invented.

**Files read in full:** `Dentfluence_Master_Register_Dashboard_V2.html` (module registry JSON), `app/Services/Patient/PatientJourneyService.php`, `app/Services/Relationship/UnifiedTimelineService.php` (783 lines), `resources/views/patients/profile/journey-timeline.blade.php`, `resources/views/patients/profile/journey-timeline-events.blade.php`, `database/migrations/2026_07_15_150700_create_patient_journeys_table.php`, `app/Models/PatientJourney.php`, `tests/Feature/Patients/JourneyTimelineTest.php` (245 lines, 8 tests), `app/Services/Relationship/ActivityEngine.php`, `app/Observers/LeadObserver.php`, `app/Http/Controllers/PatientCommunicationController.php`, `app/Services/Relationship/JourneyService.php`, plus targeted greps across `app/` and `docs/patient-journey-v1_1-*.md`.

---

## 1. Executive Summary

"Journey Timeline" is a real, working, permission-aware **read-model** — not a stub. `PatientJourneyService` (canonical facade) sits over `UnifiedTimelineService::forPatient()` (the aggregator) and assembles a live, cursor-paginated, group-filterable feed for the Patient Profile page. It has 8 passing feature/unit tests and is wired end-to-end: route → controller → service → aggregator → Blade partial → Alpine.js "load older" UI.

It is **not** an event-sourced ledger with its own storage. It has **no owned database table** — every render is a live, on-demand fan-out query across ~13 source tables, capped and try/caught per source so one bad source can't blank the page. This is a defensible architecture for V1 scale but has real cost, staleness, and audit-trail implications at scale (Section 7).

One correction to the Master Register itself: its `dataOwnership` entry for `journey-timeline` lists `patient_journeys`. That table is **not used anywhere in this module's code path**. `patient_journeys` belongs to a completely different, unrelated system — the Case Acceptance Engine's public case-journey/token instances (confirmed by the migration's own doc-comment and `CaseJourneyController`). This is exactly the naming collision the Master Register itself flagged ("five differently-named 'journey' services... unconfirmed overlap") — now confirmed as a real, not hypothetical, documentation error.

Two source-of-truth gaps are confirmed by direct code read, not assumption:
- **Prescriptions never appear on the timeline.** The aggregator has a literal reserved no-op hook — `addPrescriptionless()` — and no Prescription controller/service calls `ActivityEngine`. Rx is link 5 of 7 in CEO Directive #004's chain and is explicitly named in the requested event list; it is currently invisible on this screen.
- **`communication_queue` (PRE/WhatsApp/Recall's live table) is not a direct timeline source.** The aggregator's comms adapter reads only the legacy `patient_communications` table. Those channels only reach the timeline indirectly, via `ActivityEngine`-logged ledger rows keyed to `patient->relationship_id` — confirmed present for WhatsApp (`Api/V1/WhatsappController` calls `ActivityEngine`) and for leads (`LeadObserver` logs `lead.created`), but this is an indirect, easy-to-miss path, not a first-class adapter, and it silently depends on identity linking (`relationship_id`) being correct.

**Bottom line:** Journey Timeline is a solid, tested, permission-correct MVP of a patient history feed. It is not freeze-ready: it is missing one CEO-chain data type (Rx) entirely, has an unverified/likely-absent audit trail for compliance purposes, has no confirmed performance ceiling, and its own Master Register dependency data is wrong.

---

## 2. Functional Overview

**Production Ready**
- Chronological aggregation of clinical, financial, comms, consent, and review events for a single patient, newest-first.
- Cursor-based "load older" pagination (`before` param, ISO8601 cursor).
- Group filter: All / Clinical / Financial / Comms / Consent / Reviews — server-enforced, not just UI cosmetic.
- Per-viewer permission filtering (`User::canAccess(module, action)`) — confirmed unit-tested with a stub viewer that only has `patients` access and correctly drops `billing.view`/`lab.view` events.
- Merged-patient guard: timeline route 404s for a patient that has been merged away (`merged_into_id`), preventing access to a dead record's history.
- Fault isolation: every source query is wrapped in a try/catch (`guard()`); one broken source (e.g., a missing table) degrades gracefully instead of 500ing the whole page.
- Real patient-decision granularity: treatment plan created / presented / accepted / partially-accepted / deferred / rejected / cancelled are each distinct, correctly-labeled entries (Slice 2.3d/2.3e), including the deliberate fix that stopped rendering administrative cancellations as patient rejections.

**Partial**
- Communications coverage: only `patient_communications` (legacy/manual log table) is a direct adapter. `communication_queue` (the live PRE/WhatsApp/Recall system of record per the Master Register) is not — those events only surface if something also calls `ActivityEngine::log()` against the same `relationship_id`. Confirmed present for WhatsApp and leads; **not confirmed** for every recall/follow-up action.
- Lead-to-patient continuity: `Lead Created` only appears if the lead's `relationship_id` matches the patient's — correct in the "single relationship per identity" model, but this module has no test asserting that specific cross-entity linkage, only the pipeline it depends on does.
- `addRelationshipWork` — an adapter explicitly planned in `docs/patient-journey-v1_1-architecture-proposal.md` ("gains `addRelationshipWork` adapter, never restructured") to surface PRE follow-up attempt history and obligation open/close events — **does not exist in the code today.** Planned, not built.

**Prototype**
- None identified — there is no half-built UI or feature-flagged-off code path for this module. What exists, works.

**Missing**
- Prescriptions/Rx — zero coverage, confirmed via the literal `addPrescriptionless()` no-op and zero `ActivityEngine` calls anywhere in prescription code.
- Any dedicated storage/audit table for the timeline itself — it is 100% read-time projection; nothing about "what the timeline showed" is durably recorded (Section 5).
- Any caching layer — every page view re-runs ~13 queries live; no cache key, no invalidation strategy found.
- clinic_id / multi-tenant scoping — consistent with the repo-wide gap the Master Register already tracks under `prog-tenant`; this module inherits it rather than introducing it independently.

---

## 3. Completion Analysis

The Master Register's own checklist for `journey-timeline` (Tier M, 8-stage ladder: Audit → Planning → Implementation → Testing → Bug Fixes → Hardening → Live Validation → Freeze) is checked at **5 of 8 (62.5%)**: Audit, Planning, Implementation, Testing, and Hardening are marked done; Bug Fixes, Live Validation, and Freeze are not.

Code evidence **supports** that self-assessment and adds detail on *why* it's stuck at 62.5% rather than higher or lower:

- **Why not higher (blocking Live Validation/Freeze):**
  1. A CEO-chain data type (Prescriptions) is completely absent — this is a functional gap, not a polish item.
  2. No confirmed production usage/adoption data exists for this screen (consistent with the wider "zero work_outcome rows in prod" adoption-gate pattern already flagged elsewhere in the codebase's own memory) — "Live Validation" cannot be marked done without real clinic usage evidence, and none was found.
  3. The Master Register's own dependency metadata for this module (`patient_journeys` ownership) is wrong, meaning whoever last updated the register was not looking at the actual aggregator code — a signal that the module hasn't had a rigorous close-out pass.
  4. No performance/load testing evidence at all for a query that fans out across up to 13 tables per page view with no cache.

- **Why not lower (justifying the 62.5% already logged):** the implementation is real and functionally deep (13 adapters, decision-ledger granularity, permission filtering, cursor pagination), and it has genuine automated test coverage (8 tests covering endpoint shape, ordering, group filtering, merged-patient 404, permission filtering, and pagination) — output that a 0–40% module would not have.

**Insufficient evidence:** production error rates, page load times, and real clinic adoption/usage of this screen. None of these are derivable from a static repo read.

---

## 4. Complete Patient Journey Flow

How each named event type actually reaches the timeline, source by source (confirmed by reading `UnifiedTimelineService::forPatient()` line-by-line):

| Event type | Status | Source & mechanism |
|---|---|---|
| Lead Created | Indirect / conditional | `Activity` ledger row (`lead.created`, logged by `LeadObserver`) surfaces only if the lead shares `relationship_id` with the patient. No direct lead adapter in `forPatient()`. |
| Appointment Booked | Direct | `addAppointments()` — queries `appointments` by `patient_id`, cap 30. |
| Consultation | Direct | `addConsultations()` — queries `consultations`, distinguishes COHA reports, links to `patients.consultations.show`. |
| Treatment Plan Presented | Direct | `addTreatmentPlanEvents()` — renders `presented_at` as its own distinct entry from "created" (Slice 2.2 clinical-truth fix). |
| Treatment Accepted | Direct | Same method — `accepted_at` → dedicated "accepted" entry; partially-accepted/deferred/rejected each rendered from the append-only `plan_decisions` ledger (Slice 2.3d), not inferred from current status. |
| Visit Completed | Direct | `addTreatmentVisits()` — queries `treatment_visits`, links to visit print view. Note: this reflects *visit records*, not the `work_outcome` clinical-progress field from Slice 2.4 — that field is not surfaced here. |
| Prescription | **Missing** | `addPrescriptionless()` is a literal empty reserved hook. No adapter, no ledger events found. |
| Invoice | Direct | `addInvoices()` — gated by `finance.view` (corrected 2026-08-03 from a dead `billing.view` string that hid these from non-admins). |
| Payment | Direct | `addPayments()` — same `finance.view` gate, links to invoice print. |
| Recall | Indirect | Only via `Activity` ledger events (`recall.queued` per `ActivityEngine`'s own documented convention) tied to `relationship_id`; no direct recall adapter. |
| Communication (general) | Partial | `addPatientCommunications()` reads only `patient_communications`, guarded by `Schema::hasTable()`. The live PRE/WhatsApp channel table (`communication_queue`) is not read directly. |
| Lab Updates | Direct | `addLabEvents()` — queries `LabCaseEvent` via `whereHas('labCase', ...)`, gated `lab.view`. |
| Review | Direct | `addReviews()` — two entries per review lifecycle (requested / received with rating), gated `patients.view`. |
| Files | Direct | `addClinicalFiles()` — gated `patients.view`, links to the Documents tab. |
| Clinical Notes | Direct | `addNotes()` (shared helper) — `PatientNote` by `patient_id`. |
| Consent | Direct | `addConsentLogs()` — `ConsentLog`, deliberately gated `patients.view` not a non-existent `consent` module slug (fixed 2026-08-03). |
| Membership | Direct (not in the user's example list, but present) | `addMemberships()` — enrollment + benefit-availed events. |

**Ordering, grouping, filtering, chronology (confirmed in code):**
- All sources are merged into one flat collection, then `sortByDesc('date')` — genuinely one merged sort, not per-source pagination stitched together.
- Cursor pagination is **whole-collection**, not per-source: every adapter is re-queried with the same `before` cursor on "load older," then re-sorted and re-capped. This is correct but means every "load older" click re-runs all 13 queries again — a cost/scalability point (Section 7).
- Grouping (`clinical|financial|comms|consent|reviews|milestone`) is set per-entry at the source (each adapter hardcodes its own group), with a `clinicalDefaults()` fallback for entries that predate the grouping scheme.
- Permission is likewise set per-entry (`module.action` string) and enforced by `PatientJourneyService::canSee()`, which calls `User::canAccess()` — this is the same authorization mechanism the rest of the app uses, not a bespoke timeline-only check.
- Every adapter caps itself (20–40 rows) *before* the merge-sort-take(limit) — so a patient with, say, 40 old appointments and 3 recent invoices could theoretically have the invoices pushed off-page by appointment volume within a single adapter's own cap, though the final merge-sort corrects for chronological ordering across sources. Not a bug, but worth knowing for very high-volume patients.

---

## 5. Event Engine Analysis

- **Timeline Events:** Not a single canonical "event" type. Each of the 13 adapters emits its own array shape mapped onto a shared display contract (`date`, `type`, `icon_type`, `title`, `description`, `actor`, `meta`, `group`, `permission`, `link`, `color`). There is no single `TimelineEvent` model or interface enforcing this shape — it's convention, held together by `clinicalDefaults()` filling gaps for legacy entries.
- **Event Sources:** A genuine dual-track design. (1) The `Activity` ledger (`ActivityEngine::log()`) — a real universal event bus keyed by `relationship_id`, used by ~30+ call sites across leads, billing, treatment plans, appointments, WhatsApp, and more. (2) Thirteen direct model adapters that read source-of-truth tables live (`Consultation`, `TreatmentPlan`, `TreatmentVisit`, `Invoice`, `InvoicePayment`, `ClinicalFile`, `LabCaseEvent`, `FinancePatientMembership`, `MembershipBenefitLog`, `Review`, `ConsentLog`, `Appointment`, `PatientNote`/`Task`/`patient_communications`). These two tracks can produce **overlapping** entries in principle (e.g., a payment could theoretically appear both as a direct `addPayments()` row and as an `Activity` `payment.received` ledger row) — no dedup logic was found between the ledger track and the direct-adapter track. Not confirmed as an active bug (would require live data to see duplicate payment entries), but it's a structural risk the code doesn't guard against.
- **Event Storage:** None, specific to this module. The `Activity` ledger table (`activities`, migration `2026_07_01_100002`) is the closest thing to durable event storage, but it's a shared repo-wide ledger, not owned by Journey Timeline, and most of the 13 direct adapters bypass it entirely (they read live source tables, not a stored "event" row).
- **Event Rendering:** Server-rendered Blade partial (`journey-timeline-events.blade.php`), fetched via `fetch()` from Alpine.js and injected as raw HTML (`insertAdjacentHTML`/`innerHTML`). This is a server-driven-UI pattern, not a client-side templating one — consistent with the stated UI Philosophy of fast, simple, information-dense screens. 17 icon types are hardcoded in a Blade `@switch`, defaulting to a generic clock icon for anything unmapped.
- **Audit Trail:** **Insufficient evidence that one exists for the timeline itself.** No `audit_log`/`AuditLog` reference was found inside `UnifiedTimelineService.php` or `PatientJourneyService.php`. The module *displays* an audit-trail-like view of what happened, but does not itself write an audit record of who *viewed* that history — relevant for DPDP compliance (Patient Rights/DSAR access-logging), which the Master Register already tracks as a separate open gap under the `consent` module, not this one. Worth flagging as a shared dependency, not a duplicate finding.

---

## 6. Integration Analysis

| Module | Direction | Confirmed mechanism |
|---|---|---|
| Patient | Depends-on | Every adapter is scoped by `patient_id`; the module has no meaning without a `Patient`. |
| Appointments | Reads-from | Direct adapter, `appointments` table, no join to Consultation/TxPlan. |
| Consultation | Reads-from | Direct adapter; links to the real consultation show route. Registered as a formal dependency in the Master Register. |
| Treatment Plans | Reads-from | Deepest integration in the module — reads `plan_date`, `presented_at`, `accepted_at`, and the append-only `plan_decisions` relation (`with('decisions')`), i.e., it reads the *decision ledger*, not just plan status. This is the one place Journey Timeline reflects the Patients-module's "clinical truth" work directly. |
| Visits | Reads-from | Direct adapter on `treatment_visits`; does **not** read the `work_outcome` field from Slice 2.4 (Clinical Progress) — a currently-unrealized integration opportunity, not a bug, since the memory record notes that field has zero adopted rows in prod today anyway. |
| Billing | Reads-from | Registered Master Register dependency; confirmed via `addInvoices()`/`addPayments()`, correctly gated on `finance.view` (a real fix from a dead permission string). |
| Finance | Reads-from | Membership/benefit adapters (`FinancePatientMembership`, `MembershipBenefitLog`) — broader than just Billing. |
| Inventory | None found | No adapter, no ledger event references inventory. Not expected to integrate — no functional reason it should. |
| Lab | Reads-from | Direct adapter (`LabCaseEvent`), gated `lab.view`. |
| Marketing | None found | No adapter. Reviews are surfaced (see below) but Marketing's own campaign/blog activity is not. |
| Communication | Partial | Only the legacy `patient_communications` table directly; the live `communication_queue` (PRE/Communication OS shared table) is not a direct source — see Section 4. |
| WhatsApp | Indirect | Not a direct adapter. Reaches the timeline only via `ActivityEngine`-logged ledger events from `Api/V1/WhatsappController`, contingent on correct `relationship_id` linkage. |
| Reports | None found | Reports is a separate read-only aggregator over the same underlying tables; the two do not share code. No evidence Reports consumes Journey Timeline or vice versa — parallel, not integrated. |
| AI | Confirmed, separate path | `PatientJourneyService::summarize()` exists specifically as an "AI/copilot convenience" — unfiltered by permission, capped at 100 events, plain-array/ISO-date shape. The doc-comment states callers must gate access themselves before invoking. The Master Register's own AI Assistant audit already flags that the `AssistantTool` interface has **no `canAccess()`/policy check at all** — meaning if any AI tool calls `summarize()`, it would receive the full unfiltered patient history (including financial/consent data) with no per-role check. This is a real, confirmed compounding risk between two independently-audited gaps, not a new one this audit invented. |

---

## 7. Production Readiness

| Dimension | Score /10 | Basis |
|---|---|---|
| Architecture | 6 | Clean facade/aggregator split, fault-isolated per source, correct separation of read-model from write-side. Loses points for zero dedup guard between the ledger and direct-adapter tracks, and for the confirmed factual error in its own Master Register entry. |
| Database | 5 | No owned schema (defensible as "pure projection," but means no independent indexing strategy for timeline-specific access patterns — it inherits whatever indexes each of 13 source tables happens to have for `patient_id`/date columns; not independently verified here). |
| Performance | 3 | Every page view and every "load older" click re-runs up to 13 separate queries with no caching layer found. No load-tested evidence for high-volume patients (long-tenure patients with hundreds of appointments/invoices). This is the single biggest technical-risk unknown for scaling to 100,000 clinics. |
| Scalability | 3 | Same root cause as Performance — a live fan-out query pattern with no pagination cost ceiling and no cache. Works fine for a single-clinic pilot; unverified at multi-tenant, high-patient-volume scale. |
| Auditability | 4 | The screen itself is a de facto audit view of clinical/financial history, which is valuable — but there is no confirmed logging of *who viewed a patient's timeline and when*, which matters for DPDP. |
| Testing | 7 | 8 real, non-trivial tests: endpoint shape, ordering, group filtering, merged-patient 404, decision-ledger event correctness, permission filtering (with a genuinely adversarial stubbed low-privilege viewer), and cursor pagination math. Above the repo's median test coverage for a Tier-M module. |
| Security | 5 | Permission filtering is real and tested. But the AI path (`summarize()`) is explicitly unfiltered by design, and the repo-wide AI Assistant authorization gap means that design assumption ("callers gate access") is not currently enforced anywhere in code — a live compounding risk, not theoretical. |
| UX | 7 | Matches the stated "fast, clean, information-dense, keyboard-friendly" philosophy: compact date column, colored accent bars, icon-coded rows, simple filter pills, no unnecessary chrome. |

**Overall: ~5/10 — functionally credible, not production-hardened.**

---

## 8. Remaining Work Checklist

1. Build a Prescription adapter (or `ActivityEngine` events from Rx creation) — closes the one confirmed CEO-chain data gap.
2. Add a direct `communication_queue` adapter (or confirm/complete `ActivityEngine` coverage across every PRE/Recall/WhatsApp action) so Communication OS activity isn't dependent on incidental ledger logging.
3. Correct the Master Register's `journey-timeline` `dataOwnership` entry — remove `patient_journeys`; it belongs to Case Acceptance.
4. Decide and document the relationship between the `Activity` ledger track and the 13 direct-adapter track — either formally dedup, or document why overlap is acceptable/impossible in practice.
5. Add a caching layer (or at minimum measure query cost) before claiming Hardening/Live Validation — no performance evidence exists today.
6. Decide whether `work_outcome` (Slice 2.4 Clinical Progress) should surface on Visit entries — currently a silent gap, blocked on the same prod-adoption gate already tracked elsewhere.
7. Build the planned `addRelationshipWork` adapter (PRE follow-up attempts + obligation open/close) referenced in the architecture proposal but not yet implemented — or explicitly re-scope it out.
8. Add access logging for timeline views if this screen is meant to satisfy any DPDP audit-trail requirement — currently not covered here or, per the Master Register, anywhere else in the DSAR/audit gap.
9. Resolve the AI Assistant authorization gap before `summarize()` is wired into any live AI tool, since its own doc-comment's safety assumption ("callers gate access") is currently unenforced.
10. Get real clinic usage data on this screen before marking "Live Validation" — none was found in this pass.

---

## 9. Freeze Readiness

**Not freeze-ready.** A freeze on this module today would lock in: a missing CEO-chain data type (Rx), an unverified performance ceiling, a factually wrong Master Register dependency record, and an unenforced safety assumption on the one AI-facing method it exposes. None of these are cosmetic — items 1, 5, and 9 in Section 8 are functional/risk gaps, not polish. Per this repo's own governing rule (CEO Directive #004: "Does this move V1 to production?"), Journey Timeline is downstream of Consultation/Treatment Plans/Billing in the CEO's execution order (rank 14 of 32) precisely because it *reads* those modules — it correctly should not freeze before its upstream dependencies do, and the Master Register's own rationale for that ordering is accurate.

---

## 10. SWOT

**Strengths**
- Genuinely tested, genuinely permission-correct, genuinely fault-isolated read model — not vaporware.
- Correctly reflects the hard-won "clinical truth" work from the Patients module (real decision ledger, not inferred status).
- Sits behind one clean public contract (`PatientJourneyService`) that every future consumer (mobile, AI, future patient microsite) is documented to reuse — good architectural discipline against duplicate aggregators.

**Weaknesses**
- No caching, no confirmed performance ceiling, no owned schema for independent optimization.
- One CEO-chain event type (Prescriptions) is completely absent.
- Communication OS's live channel table isn't a direct source — depends on incidental ledger logging.
- The Master Register's own metadata about this module is factually wrong (`patient_journeys` ownership).

**Opportunities**
- Because it's a thin, well-isolated facade, closing the Rx gap and the `communication_queue` gap are additive changes — the architecture proposal's own stated pattern ("adapters added, never restructured") means this can be done without destabilizing what already works.
- This is the one module best positioned to become the read backbone for the future Patient Microsite / Chairside App / AI Copilot, exactly as its own doc-comment states — worth protecting that contract rather than letting new consumers build parallel aggregators (the Master Register already flags five differently-named journey services; a sixth parallel one would be a real regression).

**Threats**
- If left unaddressed, the live-query-per-page-view pattern is the kind of thing that works perfectly in a single-clinic pilot and degrades unpredictably at multi-clinic/high-patient-volume scale — exactly the "commercial SaaS, 100,000 clinics" bar this project is held to.
- The unfiltered `summarize()` AI path is a live compounding risk with the already-documented AI Assistant authorization bypass — if AI feature work resumes (currently halted by CEO Directive #004), this needs to be fixed before that resumption, not during it.

---

## 11. CTO Recommendation

Do not invest in new Journey Timeline features right now — CEO Directive #004 is explicit that Journey Timeline (rank 14) trails Consultations → Treatment Plans → Visits → Appointments → Rx → Billing → Follow-up, and that ordering is structurally correct: this module is a *reader*, so hardening it before its sources stabilize would be wasted effort re-hardened later.

When its turn comes, the highest-leverage fixes are cheap and additive, not a rebuild: (a) add the missing Rx adapter, (b) decide the `communication_queue` question once, (c) fix the one-line Master Register metadata error, (d) get a real performance read on a patient with a large history before assuming the live fan-out query scales. Everything else in the remaining-work list is real but lower urgency.

One thing worth saying plainly as product judgment, not just engineering: this module is *already* doing the right commercial thing — it is the single reusable "what happened with this patient" contract that mobile, AI, and the future patient microsite are all documented to depend on. Protecting that contract (no sixth parallel journey aggregator, ever) is worth more to a sellable SaaS product than any individual event-type addition. A clinic owner will not pay extra for "the timeline module" as a line item — but a fast, trustworthy, single source of patient history is table stakes underneath everything they *will* pay for (faster front-desk decisions, fewer missed follow-ups, defensible clinical records). Treat it as foundational infrastructure, not a feature to market.

---

## 12. CEO Verdict

Journey Timeline is **62.5% functionally complete** and primarily requires **closing its two confirmed data-source gaps (Prescriptions, live Communication OS channel) and a real performance/scale validation pass** before production readiness.
