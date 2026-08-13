# Communication OS v2.0 — Module Assessment

_Assessment date: 2026-06-26. Read-only audit — no files changed. Verify each "delete" item against the live file before removing (per project rule: never delete without asking)._

---

## 1. The headline finding

The "Communication OS" is actually **two subsystems sharing one name and one menu**, built at different times on **different data models**:

- **The new PRM pipeline** (what you just upgraded) — runs on the **`leads`** table → `LeadObserver` → `app/Services/Prm/*` (Ingest, Routing, FollowUp, Enrichment, Reply). Inbound via 3 webhooks (Website / Meta / WhatsApp). This is **coherent, fully wired, and self-contained.**
- **The older "Communication List / Recall / B2B" layer** — runs on the **`communication_queue`** table → `RecallEngineService` + the daily digest commands. Still active and useful for recall and B2B, but it is **not** the lead pipeline.

They were originally meant to be one "universal inbox." The upgrade moved lead intake onto `leads`, which left `communication_queue`'s lead-handling half **vestigial**. Most of the "what's unnecessary" answer flows from this one split.

---

## 2. What's NEEDED (keep — this is the working core)

**The PRM pipeline (all ACTIVE, verified):**

- `leads` table + `Lead` model + `LeadActivity` + `LeadObserver`
- `app/Services/Prm/`: `LeadIngestService`, `LeadRoutingService`, `LeadFollowUpService`, `LeadEnrichmentService`, `LeadReplyService`
- Webhooks: `WebsiteLeadController`, `MetaLeadController`, `WhatsAppLeadController` → all call `LeadIngestService::ingest()`
- `app/Http/Controllers/Communication/PrmController.php` — **fully implemented** (board, inbox, source-analytics, team-performance, channel-roi, settings, lead detail/edit, move-stage, convert, enrich, draft/log reply). All its views exist and are served.
- `routes/prm.php` (the live PRM route file), `config/prm.php`, `config/followup_rules.php` (the `prm_stage_changed` section)

**Supporting layer that's still genuinely used:**

- `RecallEngineService` + `communication_queue` for **recall** (6 daily triggers) and **B2B / lab-vendor** comms — keep, but scope it explicitly to recall+B2B.
- `follow_ups` table — shared cleanly by both patient and lead follow-ups. Good design, keep.
- Console commands (all scheduled in `routes/console.php`, all active): `recall:run`, `comm:morning-briefing`, `comm:sla-alert`, `comm:evening-summary`, `comm:auto-escalate`.
- `CommunicationController` (unified manager/inbox), `OpportunityController`, `FollowUpController`, `B2BController`, `RecallController`, `KpiController`, `DashboardController` — all active.

---

## 3. What's UNNECESSARY (remove or consolidate)

### A. Dead / orphan files (high confidence — safe to delete after a glance)

| Item | Why |
|---|---|
| `app/Http/Controllers/PrmController.php` (top-level) | Legacy **dummy-data** version. Its namespace is `App\Http\Controllers\Communication` but it sits in the wrong folder → **not autoloaded** (PSR-4 mismatch). The live one is `Communication/PrmController.php`. This stale copy is what made an earlier scan wrongly think PRM routes were broken. |
| `routes/web_fixed.php` | Orphan. **Not loaded** anywhere in `bootstrap/app.php`. Near-complete stale duplicate of `web.php`. |
| `app/Http/Controllers/Communication/.fuse_hidden0000004700000005` | Editor temp/shadow copy of PrmController. Junk. (Also `.fuse_hidden*` files under `app/Console/Commands/`.) |

### B. Redundant data layer (consolidate — needs a small migration plan, not a blind delete)

- **`communication_queue` lead-inbox role is now redundant** with `leads`. Columns like `comm_type='new_lead'`, `source_engine='inbound'`, `SLA_MINUTES['inbound']`, and likely `move_to` and `opportunity_value` are vestigial. Decision needed: formally re-scope `communication_queue` to **recall + B2B only**, and drop the dead lead columns.
- **`patient_communications` vs `communication_queue`** overlap: `PatientCommunicationController::index()` already **merges both tables** at read time for the patient profile. That's two tables storing "communications with a patient" — a consolidation candidate.
- Birthday outreach exists in **two** places: `RecallEngineService::recallBirthdayAnniversary()` and `followup_rules.php → special_occasion`. Pick one.
- `CampaignLeadService` (UTM attribution) — its docblock says it's called from `LeadController::store()`, but **no `LeadController` exists**. Likely **not wired** into the webhook intake. Verify, then either wire it into `LeadIngestService` or remove it.

### C. Genuinely unfinished / stub (decide: build or remove)

- **`TimelineController`** (`index`/`patient`/`show`) still returns **hardcoded dummy data** (`getDummyPatients`, `getDummyTimeline`). The timeline views render fake data. Either wire to real data or hide the nav entry.
- Timeline routes are **registered in two files** (`communication.php` and `timeline.php`) at overlapping names — consolidate to one.
- `TemplateController` index/edit are **view-only stubs** (no logic). Fine to leave, but it's not functional yet.

### D. Route duplication (the maintenance trap)

The PRM pipeline is referenced from **two route files**: the live `routes/prm.php` (`prm.*` names) and a leftover `prm.` sub-group inside `routes/communication.php`. Both hit the same controller/URLs; `prm.php` wins. Remove the stub group in `communication.php` so there's one source of truth. Also note the stage-move name mismatch: `move_stage` (underscore) vs `move-stage` (hyphen).

> ⚠️ Correction vs first scan: PRM **and** Task routes are **NOT broken** — `Communication/PrmController` and `Communication/TaskController` both contain every referenced method (inbox, channelRoi, teamPerformance, uploadEvidence, escalate, myTasks, overdue, etc.). The earlier "missing methods" reading came from the dead top-level controller. The only real stub is **TimelineController (dummy data)**.

---

## 4. Uniform style — the real gap, and how to fix it

You **already have a proper design system** that's barely used:

- **Baseline:** `layouts/communication.blade.php` → loads `resources/css/communication/module.css` (DM Sans, tokenized palette, `co-*` classes, Tabler Icons) and yields **`@section('communication-content')`**.
- **Canonical components:** `components/communication/*` (top-nav-tabs, filter-bar, queue-card, status-chip, empty-state…) and `components/prm/*` (lead-card, stage-badge, add-lead-modal…).

The problem is **almost no page consumes it.** The inconsistencies, in priority order:

1. **Wrong section name (breaks the shell).** The layout yields `communication-content`, but ~14 views use `@section('content')` (most of followup/, tasks/, recall/index, opportunities/board, templates/, huddle/alerts, prm/settings, timeline/patient-timeline). These bypass the module wrapper entirely. **Fixing this one thing unifies the chrome fastest.**
2. **Per-page bespoke styling.** manager (`cl-*`), b2b (`b2b-*`), recall (`re-*`), opportunities (`opp-*`), followup (`fu-*`), kpi — each ships its own `<style>` block and heavy inline `style=` (manager/show has ~67 inline styles). None use `co-page-header` or the shared components.
3. **Three different "brand purples"** + one rogue serif: layout back-button `#6a0f70`, `module.css --prm-primary #534AB7`, and `templates/index` uses `#1a0320` + Cormorant Garamond serif (totally off-palette). Pick **one** purple token.
4. It's **Bootstrap + inline styles throughout** (not a Bootstrap-vs-Tailwind split, as you might have assumed — Tailwind usage is ~zero). So the fix is consolidation onto `module.css` tokens + components, not a framework migration.

**Recommended uniform-style standard (one page contract):**
- Every page: `@extends('layouts.communication')` + `@section('communication-content')`.
- Header: `<x-communication.top-nav-tabs>` + a single `co-page-header`.
- Kill per-page `<style>` blocks; move shared rules into `module.css`; use `components/communication/*` + `components/prm/*` as the only card/badge/chip vocabulary.
- One purple token, one icon set (Tabler), DM Sans everywhere.

---

## 5. Suggested order of work (low-risk → higher)

1. **Delete the 3 dead files** in §3A (after a confirming glance). Zero behaviour change.
2. **Unify the page shell** — fix the `@section('content')` → `communication-content` mismatch across the ~14 views. Biggest visual win, low risk.
3. **De-dupe routes** — remove the `prm.` stub group in `communication.php`; consolidate timeline routes to one file.
4. **Style pass** — migrate per-page `<style>`/inline styles onto `module.css` + shared components; settle on one purple.
5. **Decide on Timeline** — wire to real data or hide it.
6. **Data consolidation (plan first)** — re-scope `communication_queue` to recall/B2B, drop dead lead columns; fold `patient_communications` in; verify/decide `CampaignLeadService`.

Items 1–5 need no migrations. Item 6 does — treat it as its own session.
