# What Is PRE?

*Written 2026-07-04. Source: docs/plan-relationship-engine-v1.md, docs/target-architecture-engine-first.md, docs/implementation-blueprint-v1.md, docs/gap-analysis-current-to-target.md, docs/architecture/ENGINEER_HANDOVER.md, docs/HANDOVER_REPORT.md, docs/phase-0 through phase-7 READMEs, config/features.php, app/Models/Branch.php, app/Models/Scopes/BranchScope.php.*

## The one-line version

PRE (the **Relationship Engine**) is the rebuild that replaces Dentfluence's old "Lead → Patient" split with one permanent record per person. A person who calls the clinic, gets treated, drops off, and comes back five years later is the *same* record the whole time — not a lead that died and a patient that appeared from nowhere. Everything else in Dentfluence (recalls, marketing, tasks, WhatsApp, insights) is meant to eventually read and write through this one spine instead of each module keeping its own private copy of "who this person is."

The design principle, verbatim from the architecture doc: **Relationship over Lead.**

## The 13 engines

PRE isn't one feature — it's a set of independent engines, each with a narrow job, all sitting on top of the same relationship record:

1. **Relationship Engine** — the person node itself: identity, dedup, one row per human.
2. **Activity Engine** — an immutable, append-only ledger of everything that happened to that person (calls, visits, messages).
3. **Rules Engine** — decides *what should happen* based on activity (e.g. "no visit in 6 months → due for recall").
4. **Automation Engine** — actually *executes* time-based work (send the recall reminder, retry, cool down).
5. **Workflow Engine** — multi-step clinical/ops procedures (e.g. implant staging) — not built yet.
6. **Task Engine** — splits work into Human tasks (someone must do this) vs System tasks (automation is handling it).
7. **Communication Engine** — single gateway for every outbound message, gated by an 8-factor consent/quiet-hours/frequency Guard.
8. **Notification Engine** — internal staff alerts.
9. **Timeline Engine** — the per-person history view built from the Activity ledger.
10. **Insights Engine** — health/LTV/risk signals per relationship.
11. **Analytics Engine** — aggregate/cohort views across all relationships.
12. **Search Engine** — one unified index instead of per-module search.
13. **Integration Engine** — the boundary layer for WhatsApp, Google, Meta, ABDM, payments, so external APIs don't leak into business logic.

An **Organization Engine** (multi-clinic/DSO/franchise) is named in the architecture doc but explicitly deferred: *"We are not building this now."*

## What's actually live today (as of 2026-07-04)

This is the part worth being precise about, because "PRE" gets used loosely to mean both "the finished vision" and "what's running in production right now." They are not the same thing. Every cutover is gated by a flag in `config/features.php` — nothing changes behavior until a flag is flipped, and most are still off.

| Phase | Status | Notes |
|---|---|---|
| 0 — Safety & Foundations | Code-complete, flags off | No user-visible change by design. |
| 1 — Relationship Foundation | **Partially live** | `identity.link_patient` ON (2026-07-04): new patients auto-link to a Relationship. Reads still go through the old path (`identity.reads_relationship` off). Existing patients need a one-time backfill command run. |
| 2 — Automation | **Partially live** | Recall automation is running in production with verified parity (3,830 records, 0 divergence) via a per-clinic override — but the *shipped default* in features.php is still `false`. Rules consolidation still off. |
| 3 — Work surfaces | Code-complete, flags off | Today's Actions projection and Human/System task split built, not cut over. |
| 4 — Communication | **Partially live** | `guard.consent_required` is live-tested and ON: every WhatsApp send now actually checks DPDP consent. The rest of the 8-factor Guard and the single gateway are off. |
| 5 — Workflow / PRM-Marketing | Mostly not started | Workflow Engine itself needs its own dedicated build; only small PRM/Marketing bug fixes have shipped so far. |
| 6 — Read & Insights | Code-complete, unmigrated, flags off | Insights signals and search index built and tested, nothing reads them live yet. |
| 7 — Integration | Code-complete, unmigrated, flags off | WhatsApp connector is the one exception — separately live and tested at 100% parity. Google/Meta/Website connectors exist in code but there's no active plan to cut over yet. |
| 8 — PRM Retirement | Believed done, needs confirming | PRM controller/board removed and PRE is the primary nav in a later session — but `config/features.php` still ships `nav.pre_primary` defaulting to false with a comment saying "unchanged." Worth a direct sanity check before relying on this. |

**Bottom line:** the engine-first architecture exists and several pieces are proven in production on real data, but PRE as a *whole, cut-over system* is roughly a third of the way there. Most of what's built is running side-by-side with the legacy code, not instead of it.

## The part that matters most for "launch to the public"

Everything above was built and tested against **one clinic** (Tulip Dental, ~3,800 patients). That has architectural consequences that directly affect the idea of taking PRE to clinics who've never used Dentfluence:

- **There is no multi-tenant data model.** No `clinic_id` column exists on any table today. `Branch` and `BranchScope` exist, but they isolate *branches within one clinic's login*, not one clinic's data from another's. `BranchScope`'s own code comment admits it's "effectively inert today" because everyone shares one admin login.
- **The deployment model is "one stack per clinic,"** not shared multi-tenant SaaS — a separate Docker Compose + separate MySQL database per customer. This was a deliberate DPDP data-isolation choice, not an oversight, but it means onboarding clinic #2 today = standing up a second full deployment by hand, not a signup form.
- **The most rigorous internal audit says it outright:** *"NOT SaaS-ready. This is a single-tenant application... each new clinic would require a separate deployment today."* Adding real tenant isolation is flagged as the biggest architectural change still ahead, not a checkbox.
- None of the PRE docs mention "public launch," "self-serve onboarding," or "mobile parity" as a planned deliverable. The mobile Flutter work tracked separately in this project is feature-by-feature parity with the *web UI*, not a plan for onboarding a second clinic.

## What this means for "identical PRE web and mobile for public launch"

Framed as your architect/advisor, not just a summarizer: building an identical mobile UI on top of PRE is very achievable — that's the pattern already used successfully for every other module (Billing, Lab, Prescriptions, Membership all got full Flutter parity). The real risk isn't the mobile build. It's sequencing.

Two different problems are getting bundled under one goal:

1. **"Identical web/mobile UI"** — a UI/API parity problem. Solved the same way every previous module was solved: shared service classes, `/api/v1` endpoints, thin controllers, Flutter screens mirroring web. Low risk, known playbook.
2. **"Launch to clinics who've never used Dentfluence"** — a multi-tenancy, onboarding, billing, and self-serve deployment problem. This is a declared, unscheduled, "v2.0-scale" project of its own, and none of PRE's 8 phases touch it.

If you build a beautiful identical web+mobile PRE experience before deciding how a *second clinic's data* gets isolated, provisioned, and billed, you'll likely end up rebuilding the data layer under both UIs once tenancy lands — paying the UI cost twice. The higher-ROI order is probably: (a) decide and build the minimum tenant-isolation model first (even a coarse one — `clinic_id` + scoped queries, before anything fancier), (b) finish cutting the *already-built* PRE phases over from shadow-mode to live (Phases 1–4 are mostly waiting on flag flips and backfills, not new code), and only then (c) invest in mobile parity for the multi-clinic version — otherwise mobile becomes a second migration instead of a one-time build.

## Where to look in the code

- Web UI: `resources/views/relationship/**` (dashboard, reception, recalls, opportunities, pipeline).
- Controllers: `app/Http/Controllers/Relationship/**`.
- Flags: `config/features.php` — the single source of truth for what's actually on.
- Architecture docs: `docs/target-architecture-engine-first.md` (the frozen design), `docs/implementation-blueprint-v1.md` (the phase plan), `docs/architecture/ENGINEER_HANDOVER.md` (most current status).
