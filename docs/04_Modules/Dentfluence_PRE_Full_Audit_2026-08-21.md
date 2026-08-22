# PRE — FULL MODULE AUDIT & V1 GATEWAY REGISTER
**Traced from source 21 August 2026 · Go-live 14 September 2026 (24 days)**

The Patient Relationship Engine end to end: what it is, what runs, what is built and switched off, what is switched on and does nothing, and what must change before the V1 lock.

> **Companions.** `Dentfluence_V1_Master_Gap_Register_2026-08-21.md` (frozen backlog) · `Dentfluence_PRE_Recall_Engine_Reference.md` (recall internals, findings R-1…R-13). This document does not repeat either — it covers the rest of PRE and the flag layer across the whole product.
>
> **Not verified from source:** the production `.env.production` (not readable remotely), whether the `scheduler` and `queue` containers are actually up on the VPS, and the Flutter app (not in the connected folder). Each is called out where it matters.

---

## 1. EXECUTIVE VERDICT

**PRE is the best-engineered module in Dentfluence and the least switched on.**

The architecture is genuinely good, and that is not a courtesy. `ActivityEngine` is a real event bus that fires rules `afterCommit` with a failsafe job behind it. Every one of the nine enabled automation rules now has a real event emitter — three dedicated scan commands were written specifically to close that gap. `TaskEngine` deduplicates on relationship + category + title + due date. All twelve Today's Actions categories are individually fault-tolerant and report a health entry. And uniquely in this codebase, **every major cutover ships with a parity verifier** — `today:rebuild-projection --check`, `relationship:timeline-parity`, `automation:parity recall`, `workflow:parity`, `integration:parity`, `today-actions:health`. Somebody built this to be turned on safely.

It was then never turned on. **Of 28 declared feature flags, 24 are OFF.** Phase 3 work surfaces, Phase 4 communication, Phase 5 workflow, Phase 6 insights and search — all built, all parity-harnessed, all dark.

Three findings dominate everything else:

**First, and worst: Dentfluence currently cannot send a patient anything, on any channel.** `MAIL_MAILER=log` means no email leaves the system. WhatsApp is `enabled=true` but `WHATSAPP_DRY_RUN=true` and **`WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_BUSINESS_ACCOUNT_ID` and `WHATSAPP_ACCESS_TOKEN` are all blank**. `dry_run` also *defaults to true* in config, so an unset production value fails closed. Reminders, recalls, confirmations, review requests, birthday greetings — the entire outbound half of PRE is a no-op. This is the one item on this list with an **external lead time**: a Meta WhatsApp Business Account and per-template approval take days to weeks and cannot be compressed by working harder. Against 14 September, **this is the critical path.**

**Second: seven of the twenty-eight flags are inert.** They appear in the Settings UI as toggles an administrator can flip, and **no runtime code reads them.** `journey.authoritative`, `comm.single_gateway`, `identity.reads_relationship`, `notifications.single_store`, `marketing.via_guard`, `integration.payments`, `integration.abdm`. `comm.single_gateway` is described in its own source as "a documentation flag". Flipping any of them changes nothing while presenting as though it did — the same defect class as the finance screens in the frozen register, moved into the control plane.

**Third: the automation loop does not close on the reception screen.** Nine enabled rules fire correctly and create Tasks. `TodayActionsEngine` has no Task category at all, so those tasks surface only on `/communication/tasks` — behind a module boundary reception is explicitly instructed not to link into. The engine works; nobody sees the output.

**The shape of the V1 lock, then, is not "build more".** It is: get one channel actually sending, delete or wire the seven fake switches, close the task loop, and use the parity verifiers that already exist to turn on the gateways that earn their place. Almost everything needed is written.

---

## 2. WHAT PRE IS — THE FIVE LAYERS

```
┌─ INTAKE ─────────────────────────────────────────────────────────────┐
│ Manual: Lead Pipeline · mobile quick-add · Opportunity → Lead        │
│ Automated: LeadIngestService ← WhatsApp webhook ✅                    │
│                              ← Meta Lead Ads ❌ UNROUTED              │
│                              ← Website chatbot ❌ UNROUTED            │
└──────────────────────────────┬───────────────────────────────────────┘
┌─ IDENTITY ───────────────────▼───────────────────────────────────────┐
│ RelationshipEngine · IdentityResolver · PatientRelationshipLinker    │
│ MergeService · RelationshipSplitService · RelationshipBackfillService│
│ Flag identity.link_patient = ON (the only Phase-1 flag that is)      │
└──────────────────────────────┬───────────────────────────────────────┘
┌─ ENGINES ────────────────────▼───────────────────────────────────────┐
│ ActivityEngine (THE EVENT BUS) ─afterCommit→ RulesEngine (9 rules)   │
│    └→ TaskEngine · ReminderEngine · NotificationEngine               │
│ RecallEngineService (6 triggers) · OutcomeAutomationService          │
│ JourneyService (shadow) · RelationshipScoreEngine · InsightsEngine   │
└──────────────────────────────┬───────────────────────────────────────┘
┌─ WORK SURFACES ──────────────▼───────────────────────────────────────┐
│ Today's Actions (12 categories) · Action Board / YesterdayReview     │
│ Lead / Opportunity / Recall pipelines · Reception · Unified Timeline │
│ ⚠ NO Task category — rule output does not land here                  │
└──────────────────────────────┬───────────────────────────────────────┘
┌─ COMMUNICATION ──────────────▼───────────────────────────────────────┐
│ CommunicationGuard (consent → do-not-contact → frequency → quiet)    │
│ CommunicationEngine::send()  ← ZERO CALLERS                          │
│ OutboundMessageService → Meta Graph API  ← NO CREDENTIALS, DRY RUN   │
│ Templates · WhatsApp inbox · click-to-chat                           │
└──────────────────────────────────────────────────────────────────────┘
```

**Daily clock** (`routes/console.php`, all via the `scheduler` container running `schedule:run` every 60 s):

| Time | Command | Purpose |
|---|---|---|
| 00:10 | `protocols:generate` | protocol tasks |
| 00:30 | `wallet:recalculate` | promo-credit expiry |
| 06:30 | `audit:verify` | audit chain |
| **07:00** | **`recall:run`** | **6 recall triggers** |
| 07:05 | `comm:morning-briefing` | reads the queue recall just filled |
| 07:10 | `membership:scan-expiring` | emits `membership.expiring` |
| 07:15 | `payments:scan-overdue` | emits `payment.overdue` |
| 07:20 | `birthdays:scan` | emits `birthday.approaching` |
| 08:00 | `relationship:appointment-reminders` · `tulip:huddle` | |
| 09:00 | `lab:create-overdue-tasks` | |
| 10:00 | `whatsapp:send-reminders` | ⚠ no-op — no credentials |
| 11:00 | `reviews:request` | ⚠ no-op — no credentials |
| 14:00 / 18:00 | `comm:sla-alert` · `comm:evening-summary` | |
| every 15 min | `today:rebuild-projection` · `analytics:rebuild-snapshots` | |
| every 30 min | `comm:auto-escalate` | |

**Everything above depends on one container.** `docker-compose.yml` defines `scheduler` (a `while true; schedule:run; sleep 60` loop) and `queue` (`queue:work`). `QUEUE_CONNECTION=database`. The repo's `cron/` directory is **empty** — a dead folder, not the mechanism. If either container is not running on the VPS, PRE is silently and completely dead: no recall, no reminders, no briefings, no projections, and no queued jobs. **This cannot be verified from a remote session and must be checked by hand.**

---

## 3. THE GATEWAY REGISTER — ALL 28 FLAGS

Resolution order: per-clinic `feature_flags` row → global `feature_flags` row → `config/features.php` default → false. Adding a flag to that config is the only supported way to introduce one.

### 3.1 🔴 INERT — declared, shown in Settings, read by nothing

**No runtime code calls `Feature::enabled()` for any of these.** Their only appearance outside the registry is in the Settings page's own list of toggles. An administrator can flip them and nothing happens.

| Flag | Only reference | Reality |
|---|---|---|
| `journey.authoritative` | doc-comments in `JourneyService`, `LeadPipelineController` | Journeys are shadow-only; nothing consults this |
| `identity.reads_relationship` | `Relationship/SettingsController` list | Reads never resolve through the spine |
| `comm.single_gateway` | `CommunicationEngine:21` — self-described **"a documentation flag"** | `send()` has zero callers |
| `notifications.single_store` | `Settings/SettingsController:106` | No consumer |
| `marketing.via_guard` | `Settings/SettingsController:107` | No consumer |
| `integration.payments` | `Settings/SettingsController:109` | No consumer |
| `integration.abdm` | `Settings/SettingsController:109` | No consumer |

→ **Finding P-2.** Before the V1 lock these must either be wired or removed from the Settings UI. A control that lies is worse than a control that is absent.

### 3.2 ✅ ON today

| Flag | Where | Note |
|---|---|---|
| `identity.link_patient` | `PatientRelationshipLinker:41` | Default `true` since 04-Jul. New patients get a Relationship; **historical patients still need `relationship:backfill --apply`** |
| `automation.engine` | per-branch `feature_flags` row (config default is `false`) | Owns recall trigger 1. ⚠ **Rollback unsafe** — see recall R-5 |
| `guard.consent_required` | `CommunicationGuard:87` | ON in production. Only WhatsApp has a real consent purpose defined — see P-8 |
| `case_acceptance.enabled` · `blog.hub` | `.env` `true` locally | Outside PRE; verify intent for production |

### 3.3 🟢 CLOSED BUT GOOD — built, consumed, parity-verifiable

These are the "good gateways". Each has real ON-path code **and** a verifier.

| Flag | ON path | Verifier | Verdict for 14 Sep |
|---|---|---|---|
| **`today.projection`** | `TodayActionsProjector::grouped()` replaces 12 live domain reads. Rebuilt every 15 min by `today:rebuild-projection`. Projection is derived, disposable, rebuilt inside a transaction | `today:rebuild-projection --check` · `today-actions:health --fresh` | **ACTIVATE after a clean parity run.** Best single performance win in PRE; reception's main screen stops doing 12 live reads per load. Instant rollback |
| **`activity.single_ledger_reads`** | `UnifiedTimelineService::for()` replaces the inline builder in `ProfileController:194`. UI layout identical either way | `relationship:timeline-parity --sample=25` | **ACTIVATE after a clean parity run.** Low risk, retires a duplicate implementation |
| `relationship.pipeline_journey_column` | `LeadPipelineController:148` — adds a context-only column | none needed (read-only display) | **ACTIVATE.** Display-only, cannot affect truth |
| `relationship.opportunity_journey_column` | same, opportunity pipeline | none needed | **ACTIVATE.** Same reasoning |
| `search.index` | `RelationshipSearchIndexObserver:26` writes; `SearchIndexProjector` upserts | `search:rebuild-index` for the initial backfill | **HOLD.** No scheduled rebuild — an observer-driven index drifts on any out-of-band write. Fine for V1.1, not worth the risk now |
| `insights.signals` | `RecalculateInsightSignalsListener:33` | `insights:rebuild-signals` | **HOLD until V1.1.** Memory records "built + tested, flags OFF"; **DATA READINESS RED — zero `work_outcome` rows in production**, so the signals would compute on nothing |
| `tasks.human_system_split` | `Task.php:236` | none | **HOLD.** Single call site, no verifier, no operational need before go-live |
| `guard.fail_closed` | `CommunicationGuard:146` — a guard error blocks instead of failing open | none | **ACTIVATE — but only after a channel actually sends.** Today it would harden a path that transmits nothing |
| `guard.full_8factor` | `CommunicationGuard:99`, `WhatsAppLinkService:158`, `PrescriptionController:388` | none | **HOLD.** Three call sites, no verifier; the 4-factor path is proven |
| `workflow.engine` | `WorkflowEngine:49` | `workflow:parity` | **HOLD — V1.1.** Phase 5 is out of V1 scope per CEO #004 |
| `rules.single_engine` | `LeadFollowUpService:50`, `FollowUpController:137` | none | **HOLD.** Retires `FollowUpRulesService`; a pure-cleanup cutover with no verifier, three weeks out. V1.1 |
| `integration.whatsapp` | `IntegrationEngine:74` | `integration:parity` | **HOLD until credentials exist.** Cannot be tested against a dry-run channel |
| `integration.google` · `.meta` · `.website` | `IntegrationEngine` | `integration:parity` | **HOLD — V1.5.** Marketing connect is parked |
| `marketing.integrated_providers` | 8 call sites | none | **HOLD — V3.** Marketing re-engineering |

**Recommended activation set for V1: four flags** — `today.projection`, `activity.single_ledger_reads`, and the two journey-column display flags. Each is reversible in one flip, two of the four have a parity command that must pass first, and none touches money or clinical truth.

---

## 4. LAYER FINDINGS

### 4.1 Intake

Manual creation works from three surfaces (`LeadPipelineController`, mobile `RelationshipController`, opportunity conversion), and `LeadObserver` normalises every path regardless of caller — good design.

Automated intake is one third wired. `LeadIngestService` is consumed by three webhook controllers. Only one is routed:

| Source | Controller | Route | State |
|---|---|---|---|
| WhatsApp | `WhatsAppLeadController` | `/api/v1/webhooks/prm/whatsapp` (GET verify + POST receive) | ✅ routed |
| Meta Lead Ads | `Webhooks\MetaLeadController` | **none** | ❌ `use` statement in `routes/api.php:30`, no `Route::` |
| Website chatbot | `Webhooks\ChatbotController` | **none** | ❌ `use` at `routes/api.php:32`, no `Route::` |

Both dead controllers are *imported* in the routes file and never registered — the import is the fossil of a route that was removed or never written. `config/prm.php` has `webhooks.website.enabled` defaulting to **true** with a shared-secret token, so the configuration expects a route that does not exist.

→ **Finding P-3.** Carried from the Master Register Full Repo Audit (03-Aug), which listed unrouted webhooks as P0. **Still open.** Facebook and Instagram lead ads and the website chatbot cannot reach Dentfluence; `new_enquiries` can only ever hold hand-typed leads.

### 4.2 Identity

Strong. `RelationshipEngine`, `IdentityResolver`, `MergeService`, `RelationshipSplitService`, `RelationshipBackfillService`, `PatientMergeManifest` (a 100+ table manifest driving merges), and a dedup review queue. `identity.link_patient` is ON so new patients link automatically.

The gap is historical: patients imported before 04-Jul have no `relationship_id` until `php artisan relationship:backfill --apply --force` is run. Memory records this as still outstanding, alongside `relationship:backfill` for orphaned relationship-list rows.

→ **Pre-go-live task, not a code gap.** Run it, dry-run first, before Tulip staff touch the module.

### 4.3 Engines

**`ActivityEngine` is the event bus, and it is well built.** `log()` fires `RulesEngine::evaluate()` inside an `afterCommit` callback, wrapped in its own try/catch, with `RelationshipAutomationFailedJob` behind that as an outer net. A rule that throws cannot break the write that triggered it.

**All nine enabled rules have live emitters.** This was actively repaired — `ScanUpcomingBirthdays`, `ScanOverduePayments` and `RunMembershipRenewalScan` were each written because, in their own words, "NOTHING in the app ever emitted" the event their rule listened for.

| Rule | Trigger | Emitter | Action |
|---|---|---|---|
| `implant_followup` | `treatment.completed` | `TreatmentVisitService:484` | create_task |
| `post_treatment_followup` | `treatment.completed` | same | create_task |
| `recall_6months` | `visit.completed` | **none — rule disabled** ✔ deliberate | create_reminder |
| `membership_renewal_30d` | `membership.expiring` | `membership:scan-expiring` | create_task |
| `birthday_3d` | `birthday.approaching` | `birthdays:scan` | create_task |
| `opportunity_nudge_7d` | `opportunity.created` | `TreatmentPlanOpportunitySync:96` | create_task |
| `estimate_followup_3d` | `presentation.sent` | `PresentationController:280,323` | create_task |
| `missed_appointment_followup` | `appointment.missed` | `AppointmentActivityLogger:75` | create_task |
| `lab_ready_call` | `lab.received` | `LabCaseObserver:97` | create_task |
| `payment_overdue_3d` | `payment.overdue` | `payments:scan-overdue` | create_task |

**Eight of nine rules produce a Task. Tasks are not on Today's Actions.**

`TodayActionsEngine::generate()` runs twelve categories — `new_enquiries`, `lead_followups`, `opportunities`, `recall_calls`, `follow_up_calls`, `appointment_reminders`, `pending_estimates`, `membership_renewals`, `lab_ready`, `payment_reminders`, `wellness_check_yesterday`, `logged_communications`. There is no Task reader among them; `Task::` does not appear in the file. Rule-generated tasks surface only at `/communication/tasks` and on the patient profile — and the standing rule *"PRE only, no PRM/Comm links"* means the PRE sidebar does not link there.

→ **Finding P-4.** The automation loop opens and never closes on the screen reception actually uses.

Also in `TaskEngine::autoCreate()`: `$branchId ?? Auth::user()->branch_id ?? 1` and `$createdBy = Auth::check() ? Auth::id() : 1`. Automated tasks are silently attributed to **branch 1 and user 1**. Harmless at one clinic; wrong the moment there are two.

→ **Finding P-6.**

**`JourneyService` runs in shadow** and is honest about it — journeys are not authoritative and `journey.authoritative` is inert (§3.1). `SyncRelationshipJourneys` exists to keep the shadow current. No action for V1; do not flip.

### 4.4 Work surfaces

Twelve fault-tolerant categories, each capped at `relationship_rules.today_actions.max_per_category` (default 50), each recording a health entry so a silently failing category is visible rather than merely absent. This is the right pattern and the rest of the codebase should copy it.

Two exceptions:

- `YesterdayReviewService::missedCalls()` calls `->get()` **uncapped** — the one surface that ignores `max_per_category`, and the one that renders the 1,810-row recall backlog (recall R-11).
- `wellness_check_yesterday`, `logged_communications` and `pending_estimates` were not individually data-verified in this trace; they read live domains and are structurally sound.

`call_checklists`, `response_options` and `next_actions` in `config/relationship_rules.php` give every category a scripted call flow and a fixed outcome vocabulary, and outcomes feed `OutcomeAutomationService`, which writes back `contact_invalid_at`, deceased/opted-out flags and the next follow-up date. **This closed loop is the strongest thing in PRE** and it is live.

### 4.5 Communication — the critical path

**Nothing can be sent.**

| Layer | State |
|---|---|
| Email | `MAIL_MAILER=log` — nothing leaves. Recorded 19-Aug; unchanged |
| SMS | No provider wired anywhere |
| WhatsApp | `WHATSAPP_ENABLED=true`, **`WHATSAPP_DRY_RUN=true`**, and `WHATSAPP_PHONE_NUMBER_ID` / `WHATSAPP_BUSINESS_ACCOUNT_ID` / `WHATSAPP_ACCESS_TOKEN` are **all empty** |
| `dry_run` default | `config/whatsapp.php:22` → `env('WHATSAPP_DRY_RUN', true)` — **defaults to true**, so an unset production value fails closed |

`OutboundMessageService` is complete: `sendText()`, `sendTemplate()`, a consent gate, Graph API v21.0, per-template config with Meta template names. It is fully built and pointed at nothing.

Downstream, `whatsapp:send-reminders` (10:00) and `reviews:request` (11:00) run daily and record dry-run rows.

→ **Finding P-1, and the only item on this list with an external clock.** A Meta WhatsApp Business Account, a verified sending number and per-template approval are third-party processes measured in days to weeks. Every other gap in this document is work Dentfluence controls. This one is not.

**`CommunicationEngine::send()` — the Phase 4 single gateway — has zero callers**, stated plainly in its own docblock: *"nothing reads it, because nothing calls send() yet."* It always evaluates `CommunicationGuard::decide()` and shadow-logs the decision. It is correct, dormant code.

→ **Finding P-5.** Informational, not a defect — but `comm.single_gateway` must stop presenting as a working switch.

**`CommunicationGuard` is well designed.** Consent is checked first and is *never* relaxed by urgency; do-not-contact sits in the same tier; urgency may relax only frequency and quiet hours. `guard.consent_required` is ON in production. But the guard's own source notes that **only WhatsApp has a real consent purpose defined** (`whatsapp_comms` / `marketing_promotions`); SMS and email have no consent-purpose infrastructure, so the guard deliberately invents no rule for them.

→ **Finding P-8.** Moot today because nothing sends. Live the moment a second channel is wired.

---

## 5. FINDINGS

| ID | Finding | Severity | Register? |
|---|---|---|---|
| **P-1** | **No outbound channel functions.** Email `log`; WhatsApp dry-run with all three credentials blank; `dry_run` defaults true. Reminders, recalls, confirmations, review requests all no-op. **External lead time — Meta WABA + template approval.** | **P0** | **Yes — G-24** |
| **P-2** | **Seven flags are inert switches** shown in Settings that no code reads: `journey.authoritative`, `identity.reads_relationship`, `comm.single_gateway`, `notifications.single_store`, `marketing.via_guard`, `integration.payments`, `integration.abdm` | **P1** | **Yes — G-25** |
| **P-3** | **Meta Lead Ads and website-chatbot webhooks unrouted.** Controllers imported in `routes/api.php`, never registered. `config/prm.php` expects the website route. Carried from 03-Aug P0, still open | **P1** | **Yes — G-26** |
| **P-4** | **Rule-generated Tasks never reach Today's Actions.** 8 of 9 rules create Tasks; `TodayActionsEngine` has no Task category; tasks live behind the PRM/Comm boundary reception is told not to cross | **P1** | **Yes — G-27** |
| **P-5** | `CommunicationEngine::send()` has zero callers — Phase 4 gateway dormant by design | P2 | No — fold into G-25 |
| **P-6** | `TaskEngine::autoCreate()` hardcodes `branch_id ?? 1` and `created_by = 1` for automated tasks | P2 | No — with G-21 (`clinic_id`) |
| **P-7** | **All of PRE depends on the `scheduler` and `queue` containers.** Not verifiable remotely; repo `cron/` is an empty dead folder. If either is down, PRE is silently dead with no alarm | **P0-verify** | Fold into G-07 |
| **P-8** | `guard.consent_required` is ON, but only WhatsApp has a consent purpose; SMS/email cannot be evaluated | P2 | No — V1.1, blocked by P-1 |
| **P-9** | Recall engine: 2 of 6 triggers dead, a third degraded — findings R-1…R-13 | P1 | Proposed separately |
| **P-10** | `patients.recall_status` / `next_recall_date`: zero writers, three readers (recall R-6) | P1 | Proposed separately |

### Proposed register append

The V1 Master Gap Register is frozen; its own standing rule is that new findings are **appended as new IDs, never merged into a new audit**. Proposed:

- **G-24** (P0) — Wire one outbound channel end to end. *Start the Meta WABA application today; it is the only task on the critical path with a third-party clock.*
- **G-25** (P1) — Wire or remove the seven inert flags; no Settings toggle may present a control that does nothing.
- **G-26** (P1) — Route the Meta Lead Ads and chatbot webhooks, or remove the controllers and the config that expects them.
- **G-27** (P1) — Add a Tasks category to Today's Actions so automation output reaches reception.

Plus, from the recall trace: **G-28** (R-1, dead trigger 2), **G-29** (R-2, post-op cannibalised), **G-30** (R-3, NULL-unsafe filter), **G-31** (R-6, dead recall columns).

**Eight proposed additions. None is approved until you say so.**

---

## 6. RECOMMENDED V1 LOCK SEQUENCE

**Today, before anything else — the two items with an external or invisible clock:**

1. **Start the Meta WhatsApp Business Account application** (G-24). Number verification and per-template approval run on Meta's calendar, not ours. Everything else here can be compressed; this cannot.
2. **SSH the VPS and confirm the `scheduler` and `queue` containers are running** (P-7), and read `.env.production` for `WHATSAPP_*`, `MAIL_MAILER`, `APP_DEBUG` and `QUEUE_CONNECTION`. If the scheduler is down, every finding in this document is academic — nothing in PRE has ever run in production.

**Week 1 — bugs out (alongside the register's own Week 1):**

3. G-26 route the two webhooks *(1 h)* — or delete the controllers and the dead imports.
4. G-25 wire or remove the seven inert flags *(2 h — removal is the honest default)*.
5. G-27 Tasks category on Today's Actions *(4 h)*.
6. Recall R-1, R-2, R-3 if approved *(1 d)* — two dead triggers and a NULL-unsafe filter.
7. Run `relationship:backfill --apply` for historical patients *(dry-run first)*.

**Week 2 — gateways on, one at a time, each with its verifier:**

8. `today:rebuild-projection --check` → if clean, flip **`today.projection`**. Then `today-actions:health --fresh` to confirm.
9. `relationship:timeline-parity --sample=25` → if clean, flip **`activity.single_ledger_reads`**.
10. Flip the two **journey-column display flags** — read-only, no verifier needed.
11. Re-converge the two recall paths, run `automation:parity recall`, and only then declare the `automation.engine` rollback safe (R-5).

**Week 3 — communication live:**

12. Credentials in, `WHATSAPP_DRY_RUN=false`, send to a staff number first, then one consented patient, then release the reminder scheduler.
13. Once a real message has been delivered end to end, flip **`guard.fail_closed`**.

**Week 4 — lock:**

14. No new flags, no new features. Freeze the flag table, record the final ON set in this document, and cut over.

**Explicitly NOT before 14 September:** `insights.signals` (data readiness red), `search.index` (no scheduled rebuild), `rules.single_engine`, `tasks.human_system_split`, `guard.full_8factor`, `workflow.engine`, every `integration.*`, `marketing.integrated_providers`, and `journey.authoritative` (which does nothing anyway).

---

## 7. DO NOT TOUCH

- **`ActivityEngine`** — afterCommit dispatch with a double failsafe. The event bus works; leave it.
- **The nine rules and their emitters** — all wired, all correct, three of the emitters purpose-built to fix exactly this class of gap.
- **`TaskEngine` dedup** — relationship + category + title + due date + open status. Correct.
- **`CommunicationGuard` precedence** — consent first, never relaxed by urgency, do-not-contact in the same tier. Do not "simplify" this.
- **`OutcomeAutomationService`** — the call-outcome → patient-flag → next-follow-up loop. The strongest thing in PRE.
- **The twelve Today categories' fault tolerance and health entries** — the pattern the rest of the codebase should adopt.
- **The parity toolkit** — `today:rebuild-projection --check`, `relationship:timeline-parity`, `automation:parity`, `workflow:parity`, `integration:parity`, `today-actions:health`. These are what make the gateway activations safe. Use them; do not skip them.
- **Identity: merge, split, backfill, dedup queue, `PatientMergeManifest`.** Deep and correct.

---

*Traced 21 August 2026. Findings P-1…P-10 recorded here only; the V1 Master Gap Register remains frozen and unchanged pending approval of the eight proposed additions.*
