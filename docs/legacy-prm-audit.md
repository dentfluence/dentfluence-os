# Legacy PRM / Communication OS — Forensic Inventory

**Date:** 2026-07-25 · **Mode:** read-only. Nothing deleted, modified, or migrated.
**Companion:** completes the Patient Journey V1.1 audit set (`patients-v1_1-clinical-care-audit.md`, `patient-journey-audit.md`, `pre-relationship-engine-audit.md`).

**Spot-verified during synthesis:** `routes/prm.php` does NOT exist (12 route files globbed — the comment at `routes/communication.php:71-72` claiming PRM routes "now live in routes/prm.php" is stale/false); `patient_communications` DOES have a live writer (`PatientCommunicationController.php:82` — `$patient->communications()->create()`, which a naive `::create` grep misses). One sub-agent claim refuted accordingly.

---

## Direct answer first

**Is OLD PRM a second relationship system still participating, or undeleted dead code?**

Neither, cleanly. Three different things hide under the "old PRM" label:

1. **The PRM board/module itself is genuinely gone** — `routes/prm.php` deleted, `prm` module permission row dropped (`2026_07_06_200002` migration), main sidebar cleanly PRE-only with explanatory comments. That retirement was done properly.
2. **`app/Services/Prm/*` is NOT legacy — it is the live backend of PRE's lead pipeline**, just badly named. `LeadRoutingService`/`LeadFollowUpService`/`LeadEnrichmentService`/`LeadReplyService` fire from `LeadObserver` on every lead; `PrmRelationshipAdapter` is the spine-write primitive used by PRE web + mobile. `config/prm.php` gates all of it, defaults ON.
3. **The Communication OS shell is a half-retired module that still participates in production three ways:** (a) its scheduled commands run daily — including `comm:auto-escalate` **48×/day writing `communication_queue.outcome/sla_breached` + `comm_activity_logs` and emailing admins**, plus three daily digest emails to every active user; (b) two of its retired screens are the **only readers** of the live `comm_activity_logs` attempt-history trail that current code writes every day; (c) several of its controllers are live shared infrastructure (WhatsApp link/inbox, Tasks, FollowUps, Huddle widgets, Reviews send) that PRE and Patients actively POST into.

So: not a competing engine — but not ignorable either. It is one working system wearing two names, with a dead shell around it that still emails, escalates, and holds the only window onto a live audit trail.

---

## Inventory (classification: A=active · B=shared/PRE-dependency · C=legacy but data-bearing · D=dead, safe-delete candidate · E=uncertain)

| Legacy component | Current replacement | Still reachable? | Reads/writes data? | Safe-delete? | Class | Evidence |
|---|---|---|---|---|---|---|
| `WhatsAppLinkController` + `WhatsAppLinkService` | none — IS current (consent-gated click-to-chat) | yes; POSTed from 4+ live screens | reads consent | **NO** | **A** | `routes/communication.php:205`; `patients/show.blade.php:313` |
| `Communication\TaskController` (mounted at /tasks) | none | yes, `module:tasks` | writes tasks/follow_ups/comm_queue | NO | **A** | `routes/web.php:690-697` |
| `PatientCommunicationController` + `patient_communications` | none — current | yes (Patients + PRE profile) | **writes** (`:82`) + read by UnifiedTimelineService:135-157 | NO | **A** | verified this session |
| `app/Services/Prm/*` (5 of 6) + `config/prm.php` core keys | none — PRE's live backend | via LeadObserver + PRE routes + mobile | writes leads/follow_ups/lead_activities/spine | NO — **rename/move later, don't delete** | **B** | `LeadObserver.php:47-72`; `LeadPipelineController.php:238-550` |
| `Communication\FollowUpController` (12 routes) | none — PRE has no follow-up screen | routes live; nav only in dead partial | **writes follow_ups** | NO | **B** | `FollowUpController.php:152,203` |
| `WhatsAppInboxController` | none | yes — PRE profile POSTs `.reply`/`.template` | writes wa_threads/wa_messages | NO | **B** | `relationship/profile/index.blade.php:642,669` |
| `Communication\HuddleController` | none | own routes unlinked, but **instantiated directly by Huddle module** | reads | NO | **B** | `Modules/Huddle/.../HuddleController.php:657-660` |
| `Communication\ReviewController` | Marketing reviews page (partial) | yes — **GET-linked from Marketing Overview (rule violation)** | writes reviews | NO | **B** | `Marketing/OverviewController.php:204` |
| `CommunicationController@show` + `manager/show.blade.php` | **none** — only attempt-history viewer | yes — linked from `communication/recall/index.blade.php:308` | reads `comm_activity_logs`; attempt/close writes queue | **NO — until PRE renders attempt history** | **C** | `manager/show.blade.php:561-565` |
| `comm_activity_logs` (`CommActivityLog`) | none | written daily by current code (`CommunicationQueue::logAttempt/autoClose/ignore/dismiss`) | write-live, read only by 2 legacy blades | NO (data) | **C** | `CommunicationQueue.php:449,575-625` |
| `Communication\B2BController` + views | none (Lab overlaps partially) | routes live, no nav | writes comm_queue; 2nd `comm_activity_logs` reader | not yet | **C** | `B2BController.php:122` |
| `Communication\RecallController` (+run-now) | PRE Recall Pipeline | route live, no nav; **only screen surfacing `sla_breached`** | runNow writes queue | after PRE parity | **C** | `RecallController.php:29-44` |
| `Communication\DashboardController`, `KpiController` | PRE dashboard/analytics | routes live, no nav | read-only | after bookmark window | **C** | `DashboardController.php:24-59` |
| `comm:auto-escalate` (every 30 min) | none in PRE | scheduled, unflagged | **writes queue outcome/sla_breached + comm_activity_logs; emails admins** | decision needed: wire into PRE or disable | **C** | `AutoEscalateHighValueLeads.php:89-100`; `console.php:174` |
| `comm:morning-briefing` 07:05 / `comm:evening-summary` 18:00 / `comm:sla-alert` 14:00 | none | scheduled, unflagged | read-only; **email every active user / admins daily** | decision needed | **C** | `SendMorningBriefing.php:35-59` etc. |
| `Communication\OpportunityController` write endpoints | PRE Opportunity Pipeline | routes exist; UI unreachable (own index redirects) | would write opportunities+leads | PROBABLY — verify no external bookmarks/scripts | **D/E** | `routes/communication.php:131-141` |
| `ManagerController`, `TimelineController` (+fake-data views), `RecallSettingsController`, `TemplateController` | PRE equivalents | **no routes at all** | none | **YES** | **D** | `routes/timeline.php:28-35`; self-documented |
| `partials/communication-sidebar.blade.php` (fake badges, 7 nonexistent routes) + `layouts/partials/communication.{blade,sidebar,topbar}` | — | included by nothing | none | **YES** | **D** | grep: zero includes |
| `CommunicationServiceProvider::navBadges()` + its composer | — | composer target never renders → never executes | none | YES (code path) | **D** | `CommunicationServiceProvider.php:35-38` |
| `Webhooks\{Website,Meta,WhatsApp,Chatbot}LeadController` + `LeadIngestService` + `X-PRM-Token` | **NOTHING** | **no routes registered — all four channels 404** | would write leads | **NO — this is a capability outage, not dead code** | **D-code / P0-capability** | `routes/api.php:29-32` imports only |
| `config/prm.php` `webhooks.*`, `chatbot.*`, `ad_spend.*`, `ai.show_on_cards` | — | read by dead code or nobody | none | with the webhooks decision | **D** | sweep |
| `resources/views/crm/index.blade.php` + `/crm` redirect | PRE opportunities | route redirects before view | none | view YES; keep redirect | **D** | `routes/web.php:524-525` |
| 15 retired-route redirect stubs (manager.index, templates, recall-settings, opportunities.index) | PRE routes | reachable, redirect-only | none | keep until bookmark-decay window agreed | **E** | `routes/communication.php:48,116-172` |
| `module:communication` permission (view+edit granted to **every role**) | — | gates the whole shell | — | tighten with retirement | **E** | `2026_07_06_200002` migration |

Tests: `PrmAdapterTest` covers the live adapter (keep). Zero tests reference any Communication OS controller.

---

## Q3 — Are legacy tables still written/read?

Yes, but **no table is legacy-exclusive**. `communication_queue` is the most cross-cutting table in the app (11 writers spanning PRE, observers, engines, mobile, and legacy controllers). `comm_activity_logs` is the inverse problem: **written daily by current code, readable only on two retired pages** — deleting those pages destroys read access to a live audit trail. `patient_communications` is live (writer verified) and feeds the unified timeline. No orphaned `b2b_*`/`prm_*` tables exist.

## Q4 — Navigation exposure

Main sidebar is clean and deliberately PRE-only (with correct comments). The Communication shell is URL-only. **One live rule violation:** Marketing Overview GET-links `communication.reviews.index` (`OverviewController.php:204` → `marketing/overview/index.blade.php:88`). Two latent couplings: `PatientCommunicationController:58` emits `_detail_url` to `communication.manager.show` (never rendered, but will throw if the route is removed); PRE profile POSTs into `communication.whatsapp.*`/`reviews.send` (working as intended, but means `routes/communication.php` cannot be deleted wholesale).

## Q5 — Background execution

Five Communication-era schedules still fire with no feature flag: `comm:auto-escalate` **every 30 minutes** (writes + emails), three daily digest/alert emails (morning-briefing emails EVERY active user — the "module enabled" filter its docblock claims is not implemented), plus `whatsapp:send-reminders` and `reviews:request` (current features, comm-era naming). `LeadObserver` (attribute-registered) and `LabCaseObserver` are the only model-event automations; both are current.

## Q6/Q7 — Overlap with PRE and contribution to cognitive load

The legacy shell contributes to the PRE audit's overload findings four ways: (1) `comm:auto-escalate` + three daily emails generate notification noise pointing at screens no nav reaches — `sla_breached` is written 48×/day and surfaced only on an orphaned screen; (2) the Communication Recall screen + PRE Recall Pipeline + Missed Calls + Today's Actions makes a **fourth** surface over the same queue; (3) `CommunicationController@logForm` and `B2BController` are additional uncoordinated `communication_queue` writers; (4) stale comments actively mislead (`api.php:275` "PRM hard-deleted" — false; `communication.php:71` "routes/prm.php" — doesn't exist; `relationship.php:127` references a nonexistent `under_review/phase8_prm_retirement/` directory). Responsibility-wise there is **no duplicated engine**: leads/opportunities/recall logic each exist once; what's duplicated is **surfaces and writers**, which is exactly the PRE audit's disease.

---

## Before Patient Journey V1.1: retire / migrate / preserve / ignore

**Preserve (A/B) and formally de-stigmatize:** WhatsApp link+inbox, TaskController, FollowUpController, Huddle widgets, `PatientCommunicationController`, and all of `app/Services/Prm/*` + core `config/prm.php`. Decision for the freeze: either rename `Prm\` → `Relationship\Leads\` during V1.1's consolidation slice or explicitly document the namespace as historical. Don't delete any of it.

**Retire deliberately (C — requires care, in this order):**
1. Decide the four schedulers: wire escalation + digests into PRE surfaces or disable them. Today they are noise generators.
2. Give PRE an attempt-history panel (the drawer already needs one per the PRE audit) **before** removing `manager/show` / `b2b/show` — they are the only `comm_activity_logs` readers.
3. Then retire Communication Recall/Dashboard/KPI/B2B screens behind redirects, and tighten/remove the everyone-can-edit `module:communication` grant.

**Delete (D) in V1.1's final cleanup slice:** unrouted controllers (Manager, Timeline+fake data, RecallSettings, Template), the three dead layout partials + fake-badge sidebar, `navBadges()`, `crm/index.blade.php`, dead config keys — after the standard route/name greps.

**Explicit decision required (not silent D):** the four unrouted lead webhooks. This is a **business capability outage** (website/Meta/WhatsApp/chatbot leads all 404) misfiled as dead code. Either register the routes as part of Journey V1.1's front-of-funnel work (recommended — the journey audit's first break) or park them with a dated comment like every other deliberate retirement. Also fix the three lying comments so the next audit doesn't re-litigate this.

---

*STOP. Read-only trace complete. Nothing deleted. All classifications above are code-evidence-based; items marked E need production verification (bookmark traffic, `feature_flags` rows) before action.*
