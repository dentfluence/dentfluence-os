# Dentfluence PRM — AI & Automation Build Plan

**Status:** Planning · **Created:** 2026-06-26 · **Owner:** Sumit (solo builder)

Goal: bring Boxly-style AI to the Dentfluence PRM **without rewriting the board**. Every
phase below is *additive* — the existing Kanban, stages, lead drawer, and analytics stay
exactly as they are. We enrich the cards, feed the board from more sources, and automate
the follow-ups.

---

## Guiding principles

1. **Keep the board.** No rewrite. New work sits *behind* the pipeline or as new pages.
2. **Type little, capture a lot.** Front desk enters 3 fields (name, phone, what they want);
   AI fills the rest. (Matches our "data entry = dead simple" rule.)
3. **Local AI, zero per-lead cost.** Reuse the existing Ollama stack (qwen2.5 / llama3.1)
   already powering Voice Notes, Receipt Scan, and Tulip. No external AI bills.
4. **Ship phase by phase.** Each phase is independently shippable and nothing breaks the
   current PRM if a later phase is delayed.
5. **Laravel conventions.** Each feature = migration + model change + service + listener/
   controller + routes + view, built together, clean and commented.

---

## Where the PRM stands today (recap)

**Already built:** Kanban (6 stages: New Lead → Contacted → Appointment → Consultation →
Plan Given → Converted, plus Lost), list view, lead detail drawer with activity timeline,
drag-drop stage moves (auto-logged), Quick-Add (4 fields), full Add/Edit form, 10 lead
sources, activity logging, convert-to-patient (stub), overdue detection, source analytics
(conversion %, ₹ pipeline value, won value), settings page.

**The gap is the layer on top:** AI enrichment, channel auto-capture, automation, and
AI-assisted replies. That's what this plan delivers.

---

## Phase overview

| Phase | Focus | Board impact | Effort | Risk |
|---|---|---|---|---|
| 0 | Prep — columns + settings | none | XS | low |
| 1 | AI lead enrichment | new tags on cards | S | low |
| 2 | Auto-assign + follow-up reminders | none (sets existing fields) | M | low |
| 3 | AI draft replies + templates | action in drawer | M | medium |
| 4 | One-inbox channel ingestion | new leads appear + new inbox page | L | medium-high |
| 5 | Reporting additions | new report pages | M | low |
| 6 | Optional — chatbot + scoring | new surfaces | L | medium |

Recommended order: 0 → 1 → 2 → 3 → 5 → 4 → 6. (Phase 5 before 4 because reports are quick
wins and 4 is the heaviest lift.)

---

## Phase 0 — Foundation prep

**Goal:** add the storage and toggles later phases need, with zero behaviour change.

**Build:**
- Migration: add nullable columns to `leads` — `ai_summary` (string 60), `ai_treatment_label`
  (string 60), `ai_urgency` (string 10), `ai_estimated_value` (decimal), `ai_branch` (string),
  `ai_enriched_at` (timestamp).
- PRM settings: feature flags (enable AI enrichment, auto-assign, auto-reply drafts) so each
  phase can be turned on/off safely.

**Board impact:** none. **Effort:** XS. **Migration:** yes (additive, nullable).

---

## Phase 1 — AI lead enrichment (the headline feature)

**Goal:** Boxly's core trick — every new lead is auto-summarised, labelled, and prioritised
so staff understand it at a glance and type almost nothing.

**Build:**
- `app/Services/Prm/LeadEnrichmentService.php` — takes a `Lead`, calls local Ollama, returns:
  - **5-word summary** (e.g. "Wants implant, broken front tooth")
  - **treatment label** (mapped to existing treatment list)
  - **urgency** (low / medium / high)
  - **estimated value** (rough ₹ band from treatment)
- `Lead::created` event listener → dispatches a queued job → calls the service → fills the
  `ai_*` columns. Queued so the form save stays instant.
- Board card partial: show the AI summary line + treatment/urgency tags (small, opt-in via
  the Phase 0 flag).
- Lead drawer: show enrichment, with a "re-run AI" button.

**Board impact:** cards gain a summary line + 2 tags. Toggleable. **Effort:** S.
**Dependencies:** Phase 0. **Migration:** no (uses Phase 0 columns).

**Why first:** highest impact, lowest risk, reuses existing local-AI plumbing.

---

## Phase 2 — Auto-assign + follow-up reminders

**Goal:** close the two biggest dental revenue leaks — leads sitting unassigned, and
Plan-Given patients going quiet.

**Build:**
- `app/Services/Prm/LeadRoutingService.php` — rule-based assignment by treatment / source /
  location → `assigned_to`. Rules editable in settings. (AI routing can layer on later.)
- Follow-up reminder job (scheduled): finds overdue leads (`scopeOverdue` already exists) and
  leads stuck in `plan_given`, notifies the assigned staff member.
- Optional: auto-set a default `followup_date` when a lead enters certain stages.

**Board impact:** none — it sets `assigned_to` / `followup_date`, which the board already
shows. **Effort:** M. **Dependencies:** none (can run after Phase 1). **Migration:** no.

---

## Phase 3 — AI draft replies + templates

**Goal:** staff open a lead and a ready-to-send WhatsApp/email draft is waiting — they edit
and approve. (Boxly's "Contextual Reply Drafts".)

**Build:**
- Reply templates: small `lead_reply_templates` table (treatment-specific canned messages).
- `LeadReplyService` — generates a draft from lead context + treatment using local AI,
  pre-fills the chosen channel (call/WhatsApp/email already on the lead).
- Lead drawer: "Draft reply" action → editable draft → approve. **Never auto-sends** —
  human approves first (clinical/financial caution).

**Board impact:** new action button inside the existing drawer. **Effort:** M.
**Dependencies:** Phase 1 (reuses enrichment context). **Migration:** yes (templates table).

---

## Phase 4 — One-inbox channel ingestion (Engine 3)

**Goal:** the real "never miss a lead" feature — enquiries from every channel auto-create
leads and land in one inbox. This is the heaviest lift (external APIs, webhook security).

**Build:**
- Webhook controller(s) + routes for: Website forms, Meta Lead Ads, Facebook, Instagram,
  WhatsApp Cloud API. Each maps an incoming payload → `Lead::create()` with `source`
  pre-filled, then Phase 1 enrichment runs automatically.
- Signature verification + rate limiting on every webhook (security-critical).
- "Things to do" inbox page: new leads + replies + due follow-ups in one action list.
  Separate page — it sits *alongside* the board, not replacing it.

**Board impact:** new leads appear automatically; a new inbox page is added. **Effort:** L.
**Dependencies:** Phases 0–1. **Migration:** likely (channel/message metadata).
**Note:** break into sub-phases per channel (4a website form, 4b Meta, 4c WhatsApp) — each
is independently testable.

---

## Phase 5 — Reporting additions

**Goal:** give managers the two reports that actually drive dental decisions. (Complexity is
fine here — admin views, per our UI rule.)

**Build:**
- **Team performance:** reply time + conversions per staff member.
- **Channel ROI:** ₹ won per source vs. ad spend (extends existing source analytics).

**Board impact:** new report pages. **Effort:** M. **Dependencies:** richer once Phases 2–4
feed more data, but can start now on existing data. **Migration:** maybe (ad-spend input).

---

## Phase 6 — Optional / later

- **Website chatbot** — qualify visitors 24/7, push qualified leads into the inbox. Heavier;
  do after ingestion (Phase 4) works.
- **Lead scoring (1–100)** — *deliberately deferred.* A `value × urgency` sort gives ~90% of
  the benefit without the data volume Zoho-style scoring needs.

---

## What we are NOT doing

- Not rewriting the Kanban board or stages.
- Not adopting Zoho-style general-CRM complexity (clutter for a clinic).
- Not auto-sending any patient message without human approval.
- Not building lead scoring or churn ML in the near term.

---

## Suggested first step

Phase 0 + Phase 1 together: one migration (the `ai_*` columns), the `LeadEnrichmentService`,
the `Lead::created` listener, and the card/drawer display. Board stays untouched apart from
the opt-in tags. Smallest, safest, highest-impact slice.

> Per project workflow: each phase's size will be estimated and confirmed before code is
> written, and split into parts if truncation is likely.
