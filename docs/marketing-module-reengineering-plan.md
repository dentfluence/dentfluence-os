# Dentfluence Marketing — Product Re-Engineering Architecture Plan
Prepared 2026-07-09, companion to `docs/marketing-module-technical-dossier.md`. No code has been written. This is the approval document.

---

## 1. Product critique

The current build is a competent clone of Buffer/Hootsuite: 10 nav sections, ~40 routes, a real OAuth engine, a real scheduling job, a real DAM. The backend engineering (§7–9 of the dossier) is genuinely good — OAuth flows, the legacy/connector dual-path cutover pattern, the activity log, the encrypted token storage. That is not the problem.

The problem is framing. Three specific mismatches against what a solo/small clinic actually needs:

**It optimizes for posting volume, not outcomes.** Analytics leads with "published/scheduled/platform breakdown" — vanity metrics. `CampaignGoal` already supports `goal_type = revenue/appointments/treatments`, and `CampaignLeadService` already attributes leads to campaigns — the ROI plumbing exists but isn't the headline. It's buried under posting-cadence metrics.

**It has no daily habit mechanic.** `MarketingScoreService` computes a 0-100 score, which is the right instinct (DBM-style consistency gamification), but it's calculated from posts+campaigns+platforms+completion — not "did you do today's marketing task." There's no streak, no "today's 5-minute list," nothing that answers "what do I do right now."

**Reviews — the single highest-leverage lever for a dental clinic's local marketing — doesn't exist inside Marketing at all.** And it turns out it doesn't need to be built: a full Reviews/Reputation system already exists elsewhere in the app (verified below, §2). Marketing's only tie to it is one read-only Blade partial. This is simultaneously the biggest gap in the current Marketing IA and the cheapest one to close.

**Architecturally, there is no standalone/integrated axis at all today.** Every controller queries Marketing's own tables directly; `CLINIC_ID` is hardcoded to `1`; there is no provider/interface layer. The two-mode strategy this document is asked to design doesn't retrofit onto anything — it has to be introduced as a new layer sitting in front of the existing controllers. That's good news: it means the retrofit is additive, not a rewrite.

---

## 2. Feature audit

Verified fact this turn, not assumption: **a complete Reviews/Reputation system already exists**, separate from `app/Http/Controllers/Marketing/`:
- Model `App\Models\Review` (table `reviews`, migration `2026_06_29_230000_create_reviews_table.php`): `patient_id`, `appointment_id`, `token`, `channel` (default `whatsapp`), `status` (`requested`/`rated`), `rating`, `comment`, `routed_to_google`, `requested_by_id`, `requested_at`, `responded_at`.
- **Collect**: `ReviewService::requestFromPatient()` — sends a WhatsApp review-request template (consent-gated via `OutboundMessageService`). Triggered manually (`ReviewController::send()`, `POST /communication/reviews/send`) or automatically by the scheduled console command `reviews:request`, which finds appointments completed N days ago and sends one request each, idempotent on `appointment_id`.
- **Reply/rate**: public unauthenticated flow `GET/POST /r/{token}` → `ReviewPublicController` → `ReviewService::recordResponse()`, flips status to `rated`, routes 4★+ to the Google review link (`config('reviews.google_review_url')`, i.e. `REVIEWS_GOOGLE_URL`), link expires after `reviews.link_ttl_days` (default 14).
- **Track**: `ReviewController::index()` (`GET /communication/reviews`) + `ReviewService::stats()` — requested/rated/avg/positive/negative, filterable. A real dashboard, not a stub.
- Only bridge to Marketing today: `_recent-reviews.blade.php` reads `Review::where('status','rated')->where('rating','>=',4)` directly.

This changes the plan materially: **the Reviews nav item is a wiring task, not a build task.**

Full audit, current location → verdict:

| Feature | Current location | Weekly need (solo clinic)? | Verdict |
|---|---|---|---|
| Compose + schedule a post | Publish, `mkt_posts`/`mkt_post_variants`/`mkt_post_schedules`, `ProcessScheduledPost` | Yes, daily-ish | **Core** — keep, simplify UI only |
| Content ideas / Idea Bank | Brainstorm, `mkt_ideas`, `IdeaController` | Yes | **Core** — folds into Content as a column, not its own nav item |
| Festival/local-date prompts | `mkt_festival_dates` | Yes (this is DBM-style local relevance) | **Core** — feeds Content>Ideas automatically, no dedicated screen needed |
| Calendar view | Calendar | Yes | **Core** — simplify to plain month grid |
| Reviews collect/reply/track | **Already exists in Communication module**, not Marketing | Yes — highest leverage per DBM principles | **Core, new to Marketing** — surface the existing system, don't rebuild |
| Campaigns (budget/status/dates) | `mkt_campaigns`, `CampaignController` | Rare for solo clinic | **Advanced** — keep backend, hide by default |
| Campaign Goals | `mkt_campaign_goals` | Occasionally (revenue/appointment targets) | **Advanced**, but its data feeds core Analytics ROI cards |
| Campaign Team/roles | `mkt_campaign_team` | Almost never (1-5 chair clinic, minimal staff) | **Advanced** |
| Brand Kit | `mkt_brand_kits`, `BrandKitController` | Once (setup), rarely after | **Core but one-time** — move to onboarding wizard, not permanent nav |
| Asset Library (folders/tags) | `mkt_assets`, `AssetFolder`, `AssetTag`, `LibraryController` | Occasionally | **Core but folded** into the Content composer's media picker; folder/tag management moves to Advanced |
| Platform connections | `mkt_platform_connections`, `OAuthService`, `IntegrationController` | Once (setup), then passive | **Core**, lives under Settings, not top-level nav |
| Analytics (posting/platform-focused) | `AnalyticsController` | Yes, but wrong metrics | **Core, re-metric** — lead with spend/leads/appointments/revenue/ROI, demote posting-volume detail |
| Marketing Score | `MarketingScoreService` | Yes | **Core** — becomes the Dashboard's headline gauge, reweighted toward consistency/streak, not raw volume |
| Settings (general) | `SettingsController`, `MarketingSetting` | Occasionally | **Core**, becomes home for Brand Kit / Channels / Notifications / Advanced |
| `MarketingController` | dead, unreferenced by any route | — | **Delete.** Confirmed dead code (rule 4 permits this). |
| `CmsMediaController` | unreferenced by any Marketing route, touches `CmsMedia`/`CmsMediaUploadService` | — | **Do not delete yet.** See flag below. |

**Flag on `CmsMediaController`**: it is dead *as a route*, but it touches `App\Models\CmsMedia` / `CmsMediaUploadService` — almost certainly scaffolding toward the "Clinical Media" module integration that this very strategy calls for (`MediaProvider`, before/after cases). Recommend: treat it as a draft starting point for the Integrated `MediaProvider`, not garbage. Confirm with Sumit before touching it either way — this is exactly the kind of call rule 4 exists to prevent getting wrong.

---

## 3. What stays (visible weekly)

Dashboard (new), Content (Publish+Brainstorm+Ideas merged), Calendar (simplified), Reviews (new — wiring, not building), Analytics (re-metriced), Settings (Brand Kit + Channels + Notifications live here).

## 4. What moves to Advanced

Campaigns, Campaign Goals, Campaign Team/roles, Library folder/tag management, per-platform live-preview panel, AI-score breakdown detail, CSV calendar export, full platform-breakdown charts, Marketing Score's detailed sub-scores (headline number stays on Dashboard; breakdown view moves to Advanced/Analytics-detail). None of this is deleted — it's a visibility toggle, one nav entry inside Settings ("Advanced"), not a new top-level menu item.

## 5. What becomes automatic (Integrated mode only)

| Manual today (Standalone) | Automatic when Integrated | Provider |
|---|---|---|
| Type in revenue for a campaign goal | Pulled from Billing/Invoice totals | `RevenueProvider` |
| Manually note patient/lead counts | Pulled from Patient + Lead/PRM records | `PatientProvider` |
| Manually tag a post's treatment type | Pulled from Consultation/Treatment Plan records | `TreatmentProvider` |
| Upload before/after photos | Pulled from Clinical Media (consented) | `MediaProvider` |
| Manually collect/log a review | Existing Reviews/Reputation system (already built — WhatsApp auto-request post-appointment, public rate link, Google routing) | `ReviewProvider` |
| Manually enter upcoming appointment counts | Pulled from Appointments module | `AppointmentProvider` |

---

## 6. Navigation redesign

Target (matches the brief, 6 items, nothing else added to the top level):

```
Dashboard  Content  Calendar  Reviews  Analytics  Settings
```

Mapping from the current 10 sections:

| Old | New location |
|---|---|
| Overview | → Dashboard (rebuilt, not renamed — different content) |
| Publish | → Content |
| Brainstorm | → Content (Ideas column) |
| Calendar | → Calendar (simplified) |
| Campaigns | → Settings › Advanced |
| Library | → folded into Content's media picker; folder/tag admin → Settings › Advanced |
| Brand Kit | → Settings › Brand Kit (one-time wizard on first use) |
| Integrations | → Settings › Channels |
| Analytics | → Analytics (re-metriced) |
| Settings | → Settings (general/notifications tabs) |
| *(new)* | → Reviews (wires to existing `/communication/reviews` system) |

No 7th nav slot for "Advanced" — it lives as a tab inside Settings, reached in one click, not a permanent visible temptation to over-manage.

---

## 7. Screen-by-screen redesign

**Dashboard** — answers "what do I do today," nothing else. Elements: Today's Score (from reweighted `MarketingScoreService`), Today's Tasks (max 3-5, generated from: a due `PostSchedule`, a pending review reply, an idea worth turning into a post from `mkt_festival_dates` this week), Current Streak (consecutive days with a `post_published` event in `mkt_activity_log`), Upcoming Posts (next 3 from `PostSchedule`), Pending Reviews (count from `Review::where('status','requested')`), Missed Activities (overdue schedules/replies), Estimated Time Required (sum of task time-estimates, capped display at "~5 min"). All sourced from data that already exists — no new tables needed for v1.

**Content** — four columns: Ideas → Drafts → Scheduled → Published, backed by `mkt_ideas` and `mkt_posts.status`. Compose becomes a 3-step guided flow (Write → Pick channels → Schedule) replacing the current all-at-once 3-panel/40-control screen; the AI score gauge, live platform previews, and hashtag-suggestion panel move behind a collapsible "Advanced preview" section, not removed — just not on-screen by default. No campaign-builder step in the main flow; tagging a post to a campaign becomes an optional field for the rare user who has one.

**Calendar** — plain month grid, one dot color per day (green = published, yellow = scheduled, red = missed — mapped from existing `mkt_posts.status`/`mkt_post_schedules.status`). Filters (platform, content type) collapse into a single "Filters" popover, off by default, rather than always-visible pills and checklists. Week/list view modes move to Advanced.

**Reviews** — four sections wired to the *existing* Communication Reviews system, not new tables: Collect (surfaces `reviews:request` activity + a manual "send request" action calling `ReviewService::requestFromPatient()`, already built), Reply (inbox of `status=rated` reviews with a low rating needing a response — new: no reply-drafting exists yet, this is the one genuinely new small piece), Convert (promotes the existing `_recent-reviews` query from a passive overview partial to a first-class "turn this 5★ review into a post" action, one click into Content>Ideas), Track (embeds `ReviewService::stats()`, already built, instead of re-deriving it).

**Analytics** — reorders KPI cards to lead with Marketing Spend, Leads, Appointments, Revenue, ROI (drawing on `CampaignGoal`, `CampaignLeadService`, and the new `RevenueProvider`/`AppointmentProvider` once built). Posting-volume/platform-breakdown detail demotes to a secondary "Content performance" collapsed section — kept, not deleted, just not the headline. Marketing Score stays as the top gauge, reweighted (see §4).

**Settings** — tabs: Brand Kit, Channels (renamed Integrations), Notifications, Advanced (Campaigns/Goals/Team/Library admin), and a Mode indicator ("Standalone" / "Connected to Dentfluence OS") with the natural-language upsell copy from the brief, e.g. "Revenue can be calculated automatically — connect Dentfluence."

---

## 8. Standalone vs Integrated architecture

Same database, same tables, same routes, same controllers, same business logic — exactly as specified. The switch point is not the schema or the UI; it's which concrete class the service container hands to a controller when it asks for, say, `RevenueProvider`.

This app already has the exact mechanism needed for this, proven in production-adjacent code: `Feature::enabled('integration.meta')` / `'integration.google'` / `'integration.website'` in `OAuthService` and `ProcessScheduledPost`, resolved through `FeatureFlagService` (per-clinic DB override → global override → config default → false). Recommend reusing this identical pattern for the new axis — a flag like `marketing.mode` (`standalone` | `integrated`), resolved the same way, per clinic. No new infrastructure to build; extend the one that already works.

Controllers never check the mode themselves. They ask the container for a provider interface; Laravel's service container, keyed off the resolved mode, hands back either the Standalone or Integrated implementation. `AnalyticsController`, for example, calls `app(RevenueProvider::class)->totalRevenue($clinicId, $range)` and has no idea whether that number was typed in by a receptionist or summed from the Billing ledger.

## 9. Provider abstraction architecture

Contracts under `app/Contracts/Marketing/Providers/`:

- `RevenueProvider` — `totalRevenue($clinicId, $range)`, `isManual(): bool`
- `PatientProvider` — `activePatientCount($clinicId)`, `recentLeads($clinicId, $range)`
- `TreatmentProvider` — `treatmentOptions($clinicId)`, `recentTreatments($clinicId, $range)`
- `MediaProvider` — `availableMedia($clinicId, ?$patientId)` (consent-filtered when Integrated)
- `ReviewProvider` — `pendingCount($clinicId)`, `stats($clinicId)`, `requestReview($clinicId, $target)` — Integrated implementation is a thin wrapper around the *existing* `ReviewService`; Standalone implementation supports a manual "log a review you collected elsewhere" action against the same `reviews` table (nullable `patient_id`/`appointment_id`, `channel='manual'` — no schema change, the columns already allow it)
- `AppointmentProvider` — `upcomingCount($clinicId)`, `completedCount($clinicId, $range)`

Each interface is deliberately narrow (3-4 methods) and read-oriented — Marketing never writes back into Patients/Billing/Appointments, it only reads, which keeps the coupling one-directional and safe. Standalone implementations live in `app/Services/Marketing/Providers/Standalone/`, backed by lightweight manual-entry storage (reuse `mkt_settings` key/value rows for simple numbers; only introduce a new small table if a manual provider genuinely needs structured history, e.g. a `mkt_manual_metrics` log — decide per-provider during implementation, not up front). Integrated implementations live in `.../Providers/Integrated/`, each wrapping an existing real module (Billing, Patient, Consultation/TreatmentPlan, CmsMedia, the existing Review system, Appointments). A single `MarketingProviderServiceProvider` binds interface → implementation based on the resolved `marketing.mode` flag.

This is additive: existing controllers keep working untouched until each is migrated, one at a time, to call a provider instead of querying directly — no big-bang cutover required, same pattern the codebase already used for the Meta/Google integration cutover (dossier §8).

---

## 10. UI simplification plan

Concrete, mapped to dossier §10 findings: Publish's 1,500-line/40-control screen becomes a 3-step wizard with an `x-show`-gated "Advanced" section for the AI score/live-previews (Alpine state already used elsewhere in these views — no new frontend stack needed). Calendar's always-visible filter pills/checklists move into a single popover, default closed. Overview's 8-widget stack becomes the Dashboard's ~6 focused elements. Brand Kit's 807-line continuous form becomes a first-run wizard, not a permanent nav destination. Strip the gradient backgrounds, box-shadows, decorative icon badges, and animated spinners/toasts flagged in the dossier (217 transition/animation occurrences, 160 shadow/card divs) and rebuild each screen against the app's own plain baseline style (`dashboard/index.blade.php`) — this alone does most of the "looks like Buffer" → "looks like Dentfluence" work, independent of the IA changes.

---

## 11. Migration strategy

No schema changes required for the navigation/IA reorganization itself — this is a routing, controller-composition, and view-layer change. All 40 existing named routes stay exactly as they are (rule 5); the new nav just changes which routes are grouped under which top-level label, and multiple old routes can still feed one merged screen (e.g. Content pulls from `publish.*`, `ideas.*`, and `brainstorm` routes without any of them being renamed or removed).

Anticipated additive-only schema changes, both optional and deferred until their feature is actually built:
1. A `mkt_manual_metrics`-style table (or reuse of `mkt_settings`) if a Standalone provider needs structured manual history rather than a single current value — decide per-provider, not speculatively now.
2. Possibly a lightweight extension to Marketing's activity log (`mkt_activity_log`) to record review-reply drafts, since "Reply" is the one genuinely new Reviews sub-feature — this does not touch the existing `reviews` table at all.

No changes anticipated to any of the 18 `mkt_*` tables, the `reviews` table, or any other existing schema. `CLINIC_ID` hardcoding in `AnalyticsController`/`OverviewController` must be fixed as part of this work regardless of provider architecture — Integrated mode cannot function correctly for a real multi-clinic account otherwise; this is a bug fix, not a schema change.

Confirmed-dead `MarketingController` can be deleted per rule 4. `CmsMediaController` — hold, per the flag in §2.

---

## 12. Risks

- **Reviews "Reply" is the one real new backend piece.** Everything else in the Reviews screen wires to code that already exists and is (per this session's verification) internally consistent — but whether the `reviews` migration has actually been run and whether `REVIEWS_GOOGLE_URL`/related env vars are populated in the live environment was not verifiable in this read-only pass. Confirm before wiring Marketing to it.
- **Provider abstraction adds an indirection layer.** Must be introduced incrementally, controller-by-controller, behind the same flag mechanism already proven for the Meta/Google cutover — not a simultaneous rewrite of every controller, which would risk the one thing that currently works (OAuth + scheduled publishing).
- **`CLINIC_ID` hardcoding** must be fixed before Integrated mode can be trusted for any second clinic — flagged here so it isn't discovered mid-rollout.
- **Analytics re-metric risk**: if the new ROI-first cards are built by querying `mkt_*` tables directly again instead of through `RevenueProvider`/`AppointmentProvider`, this recreates the exact "siloed duplicate analytics engine" problem already flagged in the dossier (§12) relative to Phase 6 Insights/Huddle Reports. The provider layer is the fix for this, not an add-on to it.
- **`CmsMediaController` judgment call** — deleting it prematurely could throw away the one existing hint at how `MediaProvider`'s Integrated implementation was meant to work. Recommend explicit confirmation before touching it either way.
- **OAuth app review (Meta/Google)** is an external, ongoing dependency unrelated to this UI/architecture work — don't let it block the reorg, but don't assume it's "done" either (dossier §12: no live credentials configured today).
- **Scope discipline**: because Reviews turned out to be mostly wiring rather than building, there's a temptation to add more "while we're in there" scope. Recommend resisting — this plan is already four phases (§13); each phase should ship and be used before the next starts, per the project's own MVP-before-perfection principle.

---

## 13. Final implementation roadmap

**V1 — Core reorg (no schema changes, UI + routing only)**
Collapse nav to 6 items; rebuild Dashboard from existing data (`MarketingScoreService`, `PostSchedule`, `mkt_activity_log`); simplify Content (3-step composer, Advanced-gated preview panel) and Calendar (plain grid, popover filters); move Campaigns/Library-admin/Brand-Kit into Settings; strip SaaS-dashboard visual styling to the app baseline; delete confirmed-dead `MarketingController`. CmsMediaController: decision deferred to Sumit.

**V2 — Reviews wiring + Analytics re-metric**
Build the Reviews screen against the existing Review system (Collect/Track are pure wiring; Reply is the one new small build); re-order Analytics to lead with spend/leads/appointments/revenue/ROI using existing `CampaignGoal`/`CampaignLeadService` data; fix hardcoded `CLINIC_ID`.

**V3 — Provider abstraction**
Define the six provider interfaces; ship Standalone implementations (mostly thin wrappers over manual fields, reusing `mkt_settings` where possible); ship Integrated implementations one at a time, starting with `ReviewProvider` (already has a real system to wrap) and `RevenueProvider` (highest ROI-story value); introduce the `marketing.mode` flag via the existing Feature-flag mechanism; migrate controllers to call providers incrementally, not all at once.

**V4 — Automation & intelligence**
Streak/habit mechanics on the Dashboard; automatic content suggestions surfaced from `ReviewProvider` (new 5★ review → draft a post) and `MediaProvider` (new consented before/after photo → draft a post); the natural-language upgrade prompts specified in the brief ("This can be calculated automatically using Dentfluence OS"), placed contextually next to manual-entry fields rather than as banners.

No coding starts until this plan is reviewed. Open decisions needing a call before V1 begins: confirm `CmsMediaController`'s fate, and confirm the `reviews` table/env are actually live before V2 wiring begins.
