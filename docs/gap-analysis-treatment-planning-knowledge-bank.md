# Dentfluence Product Architecture — Gap Analysis & Build Roadmap

**Date:** 2026-07-12
**Method:** Every finding below is verified against live code (file paths cited), not memory or assumption. Two modules covered so far: Treatment Planning / Knowledge Bank, and Daily Huddle. Same format for each: what's actually built → gap → non-destructive build phases → value-ranked feature list.

## Shared principles (apply to both modules below, and to future ones added here)

AI stays optional everywhere — confirmed app-wide, not just aspirational. Every phase below is additive-only: new tables, nullable columns, new logic paths with fallback to current behavior. Nothing existing gets renamed, removed, or made to depend on a new column being populated. And per the project's own build principles, weak or speculative features are called out explicitly as "skip," not quietly included in a roadmap to look thorough.

---

# Module 1: Treatment Planning / Knowledge Bank

## Headline

The philosophy of AI-as-optional (section 1 of the original vision doc) is already how the app is built — not aspirational. `PresentationNarrativeService` is deterministic and default; `PresentationSummaryService` (Ollama) is a bolt-on that throws and falls back cleanly if the model is unreachable. No architecture change needed there — just keep enforcing it for new modules.

The Microsite (section 6 of the vision) is also largely built, correctly, as part of Smart Presentation. `PublicPresentationController`, `PresentationAccessToken`, and `/present/{token}` already give every treatment plan a shareable, token-gated public page, delivered via WhatsApp.

The real gap is the Knowledge Bank (section 2) — the vision doc's "real asset" — which is genuinely thin today. That's where to focus next, not microsite or printing.

## Section-by-section findings

**1. AI-optional core** — Done. No action needed.

**2. Knowledge Bank hierarchy** — Not built as a chain. Pieces are scattered and disconnected: `Diagnosis` (thin), `Treatment` (procedure + price), `TreatmentKnowledge` (keyword-trigger rules for chairside suggestions, not per-diagnosis options), `TreatmentSop`/`TreatmentRule`/`TreatmentMedia`/`TreatmentVisitItem` (SOP text, boolean flags, a media library). No Material or Brand model exists — material choice is a free-text field (`material_variants` JSON on `TreatmentPlanItem`). "PracticeProtocols" sounds like this but isn't — it's a staff-duty scheduler, unrelated.

**3. Decision-based treatment plans** — Closer than it looks. `TreatmentPlanController::aiSuggest()` already generates best/alternative pathway pairs (RCT vs Extraction, Zirconia vs PFM) with an explicit `option_rank`, and the plan structure already supports multiple named alternatives (Option 1/2/3) per consultation. Despite the name, this logic is deterministic regex, not AI. The gap: it infers pathways from free-text consultation notes instead of walking a real knowledge tree, so it can't yet branch into material and brand.

**4. Automated document generation** — Partial. Accepting a plan auto-creates a `TreatmentOpportunity` and can trigger a 7-day follow-up via the rules engine. A print comparison view exists. Missing: a standalone cost-estimate document, any clinical consent document, and — a live bug, not a gap — **declining a presentation does nothing**. It sets status to declined and stops; no follow-up task, no opportunity update. That's a quiet revenue leak and cheap to fix.

**5. Printed report** — Partial. `treatment-plans/print.blade.php` and `consultations/print.blade.php` cover chief complaint, diagnosis, findings, and terms/validity, but have no embedded clinical images, no "next appointment" field, no QR code, and don't visually distinguish recommended vs. alternative treatment.

**6. Microsite** — Built as a mechanism. What it doesn't yet carry: material/brand comparisons (no Material/Brand model to pull from), a "Request Callback" button, FAQs, and payment-option details. Before/after cases and videos should come from the existing Clinical Digital Library rather than a new build.

**7. AI enhancement layer** — Done, correctly optional. Ollama-backed narrative summaries, voice notes, and lead enrichment all degrade gracefully.

**8. Long-term OS vision** — Not a code gap; architecture already supports it, nothing forces an AI dependency anywhere reviewed.

## Build phases — additive only

**Phase 0 — Fixes, no schema change (days).** Wire `PublicPresentationController::decline()` to update `TreatmentOpportunity` to a `declined` stage and log the activity via the existing `ActivityEngine`/rules-engine path. Add QR rendering of the existing `/present/{token}` URL to the print blade templates.

**Phase 1 — Knowledge Bank MVP (one new table).** Add `diagnosis_treatment_options` (`diagnosis_id`, `treatment_id`, `rank` [recommended/alternative], `notes`) — doesn't alter any existing table. Refactor `aiSuggest()` to check this table first, falling through to today's regex if unconfigured. Add a settings screen for dentists to populate it once per diagnosis.

**Phase 2 — Clinical consent (new table, reuses existing fields).** New `treatment_consents` table merging `TreatmentSop.consent_notes` and the existing `consent_template` media type with patient/tooth/procedure variables into a generated document. Doesn't touch the separate DPDP `PatientConsent`/`ConsentLog` module. No e-signature capture (see ranking).

**Phase 3 — Microsite content enrichment (wiring, not new modules).** Pull before/after cases from the Clinical Digital Library into the presentation view; add a Request Callback button hitting the existing opportunity path; surface existing membership/EMI payment-plan data.

**Phase 4 — Material/Brand as real models (only if productization is decided).** Add nullable `material_id`/`brand_id` foreign keys alongside the existing free-text field — old data and old flow keep working untouched.

## Feature ranking — Treatment Planning / Knowledge Bank

**Do now:** (1) Fix decline → follow-up (Phase 0) — a bug fix with direct revenue impact, essentially free. (2) QR code on the printed report (Phase 0) — low effort, ties the physical handout to the microsite already built.

**Worth building next:** (3) Ranked diagnosis→treatment options table (Phase 1) — the actual Knowledge Bank MVP; everything downstream depends on it existing first. (4) Clinical consent as a template-merge document (Phase 2) — real differentiator, reuses existing fields. (5) Microsite content enrichment (Phase 3) — conversion value, low technical risk since it's wiring, not new modules.

**Low priority, don't build speculatively:** (6) Material/Brand as relational models (Phase 4) — only earns its complexity if the Knowledge Bank gets productized across clinics.

**Skip — energy would be wasted here right now:** (7) E-signature capture for consent — large lift, most single clinics don't need it yet; a printed/merged document with wet-ink signature covers real-world workflow. (8) The full literature-perfect relational hierarchy built before validating Phase 1 — classic over-engineering; most of the chain can