# Dentfluence — Marketing Module: Technical Dossier
Prepared 2026-07-09. Purpose: complete ground-truth reference on the existing "Marketing Hub V2" module — structure, data model, wiring, and known problems — to hand to another AI (or a developer) for a re-engineering pass. Goal of that pass: **keep the backend's real value, simplify the front end down to something a solo-clinic user can actually operate day-to-day.**

Everything below was read directly from the codebase at `E:\Dentfluence\Dentfluence_OS\Dentfluence Web` on 2026-07-09. No assumptions — file paths, class names, route names, and schema are exact.

---

## 1. What this module is supposed to solve

Stated goal (from the product owner): keep marketing *consistent* — regular posting across social media and Google — and get *analytics across all marketing channels* in one place, without adding work receptionists/dentists don't have time for.

What was actually built goes well beyond that: a full content-creation, scheduling, campaign-management, digital-asset-management, and multi-platform OAuth publishing system. It is real, working code (not a prototype), but it is scoped like a general-purpose social media management SaaS (Buffer/Hootsuite-class), not a narrow "post consistently + see results" tool for one clinic.

---

## 2. File/folder structure

```
app/Models/Marketing/            16 models (listed in §4)
app/Http/Controllers/Marketing/  14 controllers (listed in §5)
app/Services/Marketing/          4 services (listed in §6)
app/Jobs/Marketing/              ProcessScheduledPost.php (§7)
app/Integration/                 IntegrationEngine.php + Connectors/ (§8)
app/Http/Middleware/             EnsureMarketingActive.php, CheckModulePermission (aliases below)
routes/marketing.php             ~40 named routes, prefix `marketing.`, group middleware ['marketing.active','module:marketing']
resources/views/marketing/       10 top-level sections (§9): overview, publish, calendar, brainstorm, campaigns, library, brand-kit, integrations, analytics, settings
database/migrations/             18 tables, all prefixed mkt_, dated 2026_06_17_300001–300018 (§4)
config/services.php              meta.app_id/app_secret, google.client_id/client_secret
config/features.php              integration.meta / integration.google / integration.website / integration.whatsapp (all default false)
docs/archive/marketing_build.md  original build log (phases 0–7)
```

Legacy/dead code found and confirmed unreferenced by any route file (grep-confirmed, zero hits outside their own file):
- `app/Http/Controllers/Marketing/MarketingController.php` — 6 methods (`index`, `publish`, `calendar`, `ideas`, `analytics`, `accountability`), all superseded by dedicated controllers below.
- `app/Http/Controllers/Marketing/CmsMediaController.php` — 3 methods (`upload`, `tag`, `library`) touching `App\Models\CmsMedia` / `CmsMediaUploadService` — not wired to any route.

---

## 3. Routes (`routes/marketing.php`, full list)

Group: `Route::middleware(['marketing.active', 'module:marketing'])->prefix('marketing')->name('marketing.')`

| Method | URI | Name | Controller@method |
|---|---|---|---|
| GET | `/` | overview | OverviewController@index |
| GET | `/publish` | publish | PublishController@index |
| POST | `/publish` | publish.store | PublishController@store |
| POST | `/publish/draft` | publish.draft | PublishController@saveDraft |
| PUT | `/publish/{post}/variants/{platform}` | publish.variant | PublishController@updateVariant |
| GET | `/calendar` | calendar | CalendarController@index |
| GET | `/calendar/export` | calendar.export | CalendarController@export |
| PUT | `/calendar/{post}/reschedule` | calendar.reschedule | CalendarController@reschedule |
| GET | `/brainstorm` | brainstorm | BrainstormController@index |
| POST | `/ideas` | ideas.store | IdeaController@store |
| PUT | `/ideas/{idea}` | ideas.update | IdeaController@update |
| DELETE | `/ideas/{idea}` | ideas.destroy | IdeaController@destroy |
| POST | `/ideas/{idea}/convert-post` | ideas.convert-post | IdeaController@convertToPost |
| POST | `/ideas/{idea}/convert-campaign` | ideas.convert-campaign | IdeaController@convertToCampaign |
| GET | `/campaigns` | campaigns.index | CampaignController@index |
| POST | `/campaigns` | campaigns.store | CampaignController@store |
| GET | `/campaigns/{campaign}` | campaigns.show | CampaignController@show |
| PUT | `/campaigns/{campaign}` | campaigns.update | CampaignController@update |
| DELETE | `/campaigns/{campaign}` | campaigns.destroy | CampaignController@destroy |
| PUT | `/campaigns/{campaign}/goals` | campaigns.goals | CampaignController@updateGoals |
| POST | `/campaigns/{campaign}/team` | campaigns.team.add | CampaignController@addTeamMember |
| DELETE | `/campaigns/{campaign}/team` | campaigns.team.remove | CampaignController@removeTeamMember |
| GET | `/library` | library | LibraryController@index |
| POST | `/library/folders` | library.folders.store | LibraryController@createFolder |
| PUT | `/library/folders/{folder}` | library.folders.update | LibraryController@renameFolder |
| DELETE | `/library/folders/{folder}` | library.folders.destroy | LibraryController@deleteFolder |
| POST | `/assets/upload` | assets.upload | AssetController@upload |
| GET | `/assets/storage-usage` | assets.storage-usage | AssetController@storageUsage |
| PUT | `/assets/{asset}` | assets.update | AssetController@update |
| DELETE | `/assets/{asset}` | assets.destroy | AssetController@destroy |
| POST | `/assets/{asset}/tags` | assets.tags.add | AssetController@addTag |
| DELETE | `/assets/{asset}/tags` | assets.tags.remove | AssetController@removeTag |
| GET | `/brand-kit` | brand-kit | BrandKitController@index |
| PUT | `/brand-kit` | brand-kit.update | BrandKitController@update |
| POST | `/brand-kit/logo` | brand-kit.logo | BrandKitController@storeLogo |
| GET | `/integrations` | integrations | IntegrationController@index |
| GET | `/integrations/whatsapp/setup` | integrations.whatsapp-setup | IntegrationController@showWhatsappForm |
| POST | `/integrations/whatsapp/save` | integrations.whatsapp-save | IntegrationController@saveWhatsapp |
| GET | `/integrations/wordpress/setup` | integrations.wordpress-setup | IntegrationController@showWordpressForm |
| POST | `/integrations/wordpress/save` | integrations.wordpress-save | IntegrationController@saveWordpress |
| GET | `/integrations/{platform}/connect` | integrations.connect | IntegrationController@connect |
| GET | `/integrations/{platform}/callback` | integrations.callback | IntegrationController@callback |
| POST | `/integrations/{platform}/disconnect` | integrations.disconnect | IntegrationController@disconnect |
| POST | `/integrations/{platform}/health-check` | integrations.health-check | IntegrationController@healthCheck |
| GET | `/analytics` | analytics | AnalyticsController@index |
| GET | `/settings` | settings | SettingsController@index |

---

## 4. Database schema — all 18 `mkt_*` tables

**Cross-cutting facts that matter for re-engineering:**
- Zero DB-level foreign key constraints anywhere. Every relational column (`clinic_id`, `campaign_id`, `post_id`, `folder_id`, `tag_id`, user-reference columns, even pivot tables) is a plain `unsignedBigInteger`. Referential integrity is Eloquent-only.
- Soft deletes on: `mkt_campaigns`, `mkt_ideas`, `mkt_posts`, `mkt_asset_folders`, `mkt_assets`. Not on the other 13.
- `dam_asset_id` appears in 3 tables (idea_assets, post_media, assets), each commented "link to DAM asset — no FK — service layer only" but no model exposes any relationship for it — a dangling hook to a Digital Asset Manager that either doesn't exist yet or isn't connected.
- `mkt_post_variants.platform` enum omits `google_analytics` (present in `mkt_platform_connections.platform`) — a connected Google Analytics account has no matching post-variant type; worth resolving intentionally.

**mkt_settings** — id, clinic_id, key (100), value (text), type (default 'string'), created_by, updated_by, timestamps. Unique(clinic_id, key). Model `MarketingSetting`: static `get()`/`set()` helpers, type-cast by the `type` column.

**mkt_brand_kits** — id, clinic_id (unique — 1 per clinic), clinic_name, tagline, website, phone, email, address, logo_primary/light/dark/icon, colors (json), font_primary/secondary, instagram_handle, facebook_page, google_business_name, whatsapp_number, blog_url, default_ctas (json), default_hashtags (json), ai_tone (default 'professional'), ai_focus_treatments (json), ai_brand_voice_notes, created_by/updated_by, timestamps. Model `BrandKit`: `forClinic()` = firstOrCreate.

**mkt_campaigns** — id, clinic_id, name, description, status (enum draft/active/paused/completed), channels (json), start_date, end_date, budget_total (decimal 10,2), budget_utilized (decimal 10,2), campaign_color, cover_image, owner_id, created_by/updated_by, **soft deletes**, timestamps. Model `Campaign`: relations `owner()` BelongsTo User, `goals()` HasMany CampaignGoal, `posts()` HasMany MarketingPost, `assets()` HasMany MarketingAsset, `activityLogs()` HasMany MarketingActivityLog (manually filtered by subject_type — not a true polymorphic relation), `teamMembers()` BelongsToMany User via `mkt_campaign_team`.

**mkt_campaign_goals** — id, campaign_id, goal_type (enum leads/appointments/treatments/revenue/posts/custom), custom_label, target_value (decimal 12,2), actual_value (decimal 12,2), unit, created_by/updated_by, timestamps. Model `CampaignGoal`: `campaign()` BelongsTo, `progressPct()`.

**mkt_campaign_team** — pivot only, no model. id, campaign_id, user_id, role (enum manager/creator/approver/viewer), created_by/updated_by, timestamps. Unique(campaign_id, user_id). Consumed via `Campaign::teamMembers()`.

**mkt_ideas** — id, clinic_id, campaign_id (nullable), title, description, content_type (enum reel/post/carousel/story/blog/offer/general), platforms (json), tags (json), is_ai_generated (bool), status (enum idea/in_progress/converted/archived), converted_to, converted_id, cover_image, key_points (json), notes, festival_date_id (nullable), created_by/updated_by, **soft deletes**, timestamps. Model `Idea`: `campaign()`, `creator()`, `assets()` HasMany IdeaAsset, `festivalDate()`.

**mkt_idea_assets** — id, idea_id, file_path, file_name, mime_type, file_size, asset_type (enum image/video/document/other), caption, sort_order, dam_asset_id (dangling, see above), created_by/updated_by, timestamps. Model `IdeaAsset`: `idea()` BelongsTo.

**mkt_festival_dates** — global table, no clinic_id, no soft deletes. id, name, local_name, category (enum dental/national/regional/religious), month, day, festival_date (for non-recurring), is_recurring (bool), nth_week, day_of_week, description, suggested_content_type, suggested_hashtags (json), is_active (bool), created_by/updated_by, timestamps. Model `FestivalDate`: scopes `forMonth()`, `active()`, `byCategory()`.

**mkt_platform_connections** — id, clinic_id, platform (enum instagram/facebook/google_business/whatsapp/wordpress/google_analytics), access_token (text, **encrypted transparently via Crypt in accessor/mutator, hidden from serialization**), refresh_token (same), token_expires_at, scopes, external_account_id/name/avatar, meta (json), status (enum connected/expired/error/disconnected), error_message, last_checked_at, connected_by, created_by/updated_by, timestamps. Unique(clinic_id, platform). Model `PlatformConnection`: `isConnected()`, `isTokenExpired()`.

**mkt_posts** (model class `MarketingPost`) — id, clinic_id, campaign_id (nullable), title, caption, content_type (enum reel/post/carousel/story/blog/offer), platforms (json), hashtags (json), cta_type, cta_text, cta_url, ai_score (0-100), ai_score_notes (json), status (enum draft/pending/approved/scheduled/published/failed), rejection_reason, assignee_id, festival_date_id, created_by/updated_by, **soft deletes**, timestamps. Relations: `campaign()`, `assignee()`, `festivalDate()`, `variants()` HasMany PostVariant, `media()` HasMany PostMedia, `schedules()` HasMany PostSchedule.

**mkt_post_variants** — id, post_id, platform (enum instagram/facebook/google_business/whatsapp/wordpress — no google_analytics), caption, platform_specific_meta (json — IG: alt_text/location_tag; Blog: title/slug/meta_title/meta_description/excerpt; GBP: offer_type/offer_start/offer_end; WhatsApp: template_name/template_params), status (enum draft/scheduled/published/failed), external_id, external_url, publish_error, published_at, created_by/updated_by, timestamps. Unique(post_id, platform). Model `PostVariant`: `post()` BelongsTo.

**mkt_post_media** — id, post_id, file_path, file_name, mime_type, file_size, media_type (enum image/video/document), alt_text, sort_order, dam_asset_id, created_by/updated_by, timestamps. Model `PostMedia`: `post()` BelongsTo.

**mkt_post_schedules** — id, post_id, variant_id (nullable = all variants), scheduled_at, status (enum pending/processing/done/failed), job_id (Laravel queue job id), processed_at, error_message, retry_count, created_by/updated_by, timestamps. Index(status, scheduled_at) "used by queue worker". Model `PostSchedule`: `scopeDue()`.

**mkt_asset_folders** — id, clinic_id, name, description, parent_id (self-ref), color, icon, sort_order, created_by/updated_by, **soft deletes**, timestamps. Model `AssetFolder`: `children()`, `parent()`, `assets()` HasMany MarketingAsset, `isRoot()`, `hasAssets()`.

**mkt_asset_tags** — id, clinic_id, name (100), color, created_by/updated_by, timestamps. Unique(clinic_id, name). Model `AssetTag`: `assets()` BelongsToMany via `mkt_asset_tag_map`.

**mkt_assets** (model class `MarketingAsset`) — id, clinic_id, folder_id, campaign_id, name, file_path, file_name, mime_type, file_size, asset_type (enum image/video/document/template/other), alt_text, description, width, height, duration_seconds, dam_asset_id, is_favorite (bool), created_by/updated_by, **soft deletes**, timestamps. Relations: `folder()`, `campaign()`, `tags()` BelongsToMany AssetTag.

**mkt_asset_tag_map** — pivot only, no model. id, asset_id, tag_id, timestamps. Unique(asset_id, tag_id).

**mkt_activity_log** (model `MarketingActivityLog`) — id, clinic_id, user_id (nullable = system action), event (e.g. post_published, campaign_created, idea_converted, platform_connected), subject_type + subject_id (**true polymorphic** — MorphTo, unlike Campaign's manual version), description, properties (json), occurred_at, created_by/updated_by, timestamps. Static `log($clinicId, $event, $subject, $description, $properties, $userId)` helper.

---

## 5. Controllers (14, all `app/Http/Controllers/Marketing/`)

| Controller | Methods | Notes |
|---|---|---|
| MarketingController | index, publish, calendar, ideas, analytics, accountability | **Dead — unreferenced by any route.** |
| CmsMediaController | upload, tag, library | **Dead — unreferenced by any route.** Touches `CmsMedia`/`CmsMediaUploadService`. |
| SettingsController | index | Just renders `marketing.settings.index`. |
| OverviewController | index | Dashboard stats, top-3 running campaigns, upcoming schedules, activity feed. Uses `MarketingScoreService`. |
| IdeaController | store, update, destroy, convertToPost, convertToCampaign | Converts an Idea into a real Post or Campaign. |
| PublishController | index, store, saveDraft, updateVariant | Creates master `MarketingPost` + one `PostVariant` per platform, dispatches `ProcessScheduledPost`. |
| CalendarController | index, reschedule, export | CSV export of a month's schedule. |
| LibraryController | index, createFolder, renameFolder, deleteFolder | Asset folder tree + filtering. |
| AssetController | upload, update, destroy, addTag, removeTag, storageUsage | File storage on `Storage::disk('public')`. |
| IntegrationController | index, connect, callback, disconnect, healthCheck, showWhatsappForm, saveWhatsapp, showWordpressForm, saveWordpress | Delegates OAuth logic to `OAuthService`. |
| AnalyticsController | index (+5 private helpers) | KPI summary, 6-month trend, platform breakdown, campaign ROI, "intelligence insights", Marketing Score. |
| BrainstormController | index | Idea Bank + current-month festival dates. |
| BrandKitController | index, storeLogo, update | Brand kit CRUD. |
| CampaignController | index, show, store, update, destroy, updateGoals, addTeamMember, removeTeamMember | Full campaign CRUD incl. team/goals. |

---

## 6. Services (`app/Services/Marketing/`)

- **MarketingScoreService** — `score()` / `breakdown()`: composite 0-100 from posts (30pt) + campaigns (20pt) + platforms (20pt) + completion (30pt). Models only, no external calls.
- **CampaignService** — static helpers: `completionPercentage()`, `budgetUtilizationPct()`, `daysRemaining()`.
- **CampaignLeadService** — the one real cross-module bridge to PRM/Leads: `attributeLead(Lead, $utmCampaign, $utmSource)` matches `utm_campaign` to a `Campaign` name (exact then LIKE), creates a `LeadActivity`, logs `MarketingActivityLog`, increments a `leads_count` column if present. Called from `LeadController::store()`. Wrapped in try/catch — non-critical by design.
- **OAuthService** — the real OAuth2 engine for Meta + Google. Exact endpoints hit (legacy path, currently active since feature flags default off): `facebook.com/v19.0/dialog/oauth`, `graph.facebook.com/v19.0/oauth/access_token`, `graph.facebook.com/v19.0/me`; `accounts.google.com/o/oauth2/v2/auth`, `oauth2.googleapis.com/token`, `googleapis.com/oauth2/v2/userinfo`, `oauth2.googleapis.com/revoke`. Every call point branches on `Feature::enabled('integration.meta'|'integration.google')` between this legacy inline path and the newer `IntegrationEngine` connector path, and always shadow-logs both ways.

---

## 7. Async publishing job

**`app/Jobs/Marketing/ProcessScheduledPost.php`** — `ShouldQueue`, `$tries=3`, `$backoff=60s`. Triggered by `PublishController::store()` via `dispatch($schedule->id)->delay($scheduledAt)`.

Flow: load `PostSchedule` → skip if not pending → mark processing → for each `PostVariant`, call `dispatchToPlatform()` → write back status/external_id/publish_error → mark post published + schedule done → log activity. On any exception: mark failed, increment retry_count, re-throw (Laravel retry/backoff takes over).

Per-platform: looks up a connected `PlatformConnection`; if none, returns a soft "no_connection" success (UI still shows published — worth flagging as a UX honesty issue); otherwise Instagram (2-step Graph API container→publish), Facebook (page feed), Google Business (localPosts), WordPress (REST + basic auth) — each with the same legacy/connector dual-path as OAuthService.

**WhatsApp is explicitly blocked**, not just unbuilt — exact code:
```php
if ($platform === 'whatsapp') {
    return [
        'success' => false,
        'error'   => "WhatsApp marketing broadcast is not built yet — the WhatsApp Business API isn't configured. This post will NOT be sent on WhatsApp. Remove the WhatsApp platform from this post, or wait until WhatsApp broadcast is wired up.",
    ];
}
```
Reason given in the code comment: WhatsApp broadcast is a fundamentally different, consent-gated flow (needs Phase 4's `CommunicationGuard`), not a simple public post — and it used to silently report success, which was a worse bug than refusing outright.

---

## 8. Integration layer (`app/Integration/`)

**IntegrationEngine.php** — a Phase-7 "anti-corruption boundary." Holds `WhatsAppConnector`, `GoogleConnector`, `MetaConnector`, `WebsiteConnector`. `enabled($provider)` wraps `Feature::enabled("integration.$provider")`. Bulk of its surface is `log*()` methods writing `IntegrationShadowLog` rows that record whether legacy vs. connector path ran and whether results agreed — pure shadow-testing scaffolding for a planned cutover, never affects business logic.

- **MetaConnector** — authUrl/exchangeCode/fetchAccountInfo/ping/revoke(no-op) + `createInstagramContainer()`, `publishInstagramContainer()`, `publishFacebookFeed()`, `fetchLeadFields()`.
- **GoogleConnector** — authUrl/exchangeCode/fetchAccountInfo/ping/revoke(real) + `publishBusinessPost()`.
- **WebsiteConnector** — `publishWordpress()` only.
- **WhatsAppConnector** — thin wrapper over `App\Services\Whatsapp\WhatsAppCloudService` (sendText/sendTemplate/normalizePhone), plus preview-only methods explicitly documented as never used for real sends.

**Cutover state**: `config/features.php` has `integration.meta`, `integration.google`, `integration.website`, `integration.whatsapp` — **all default `false` today**, meaning every live call currently runs the legacy inline path, not the new connector path. The connector layer exists and is shadow-logging, but production behavior is still 100% legacy code.

---

## 9. Config, env, and access gating

- `config/services.php`: `meta.app_id`/`meta.app_secret` (env `META_APP_ID`/`META_APP_SECRET`), `google.client_id`/`google.client_secret` (env `GOOGLE_CLIENT_ID`/`GOOGLE_CLIENT_SECRET`).
- `.env`: **no META_*, GOOGLE_*, or WORDPRESS_* lines exist at all** — nothing is configured, not even blank placeholders. WhatsApp env exists but is dry-run: `WHATSAPP_ENABLED=true`, `WHATSAPP_DRY_RUN=true`, phone/account/token fields all blank.
- **Module gating**: `EnsureMarketingActive` middleware (alias `marketing.active`) checks `DB::table('modules')->where('slug','marketing')->exists()` — a clinic-level on/off switch. Second layer, `CheckModulePermission` (alias `module:marketing`), checks the logged-in user's `canAccess('marketing','view')` — a per-user permission layer. Both must pass.
- **Feature flags**: `App\Support\Features\Feature` facade → DB override (`feature_flags` table, per-clinic via `branch_id`, or global) → falls back to `config('features.flags.KEY.default')` → `false`. Unknown flags fail safe to `false` and log a warning rather than throwing.

---

## 10. Frontend — screen-by-screen (from direct file review)

House rule for this app: data-entry screens must be dead simple; admin/KPI screens can be dense — but never mix the two on one screen. Baseline for "simple" in this codebase: `resources/views/dashboard/index.blade.php` (158 lines, flat white cards, one border color, no gradients/shadows/icons beyond small status dots).

| Screen | File(s) | Verdict | Why |
|---|---|---|---|
| Publish (post creation) | `marketing/publish/index.blade.php` (262 lines) + `_master-content.blade.php` (609) + `_platform-previews.blade.php` (632) + `_publish-panel.blade.php` (296) ≈ 1,500 lines rendered per screen | **Cluttered/overloaded** | 40+ simultaneous interactive elements: 6-platform tab switcher, 4 live preview cards each with menus, AI score gauge, 5-item checklist, stat tiles, hashtag suggestions. Mixes data-entry with admin-density — the one thing the house rule says never to do. |
| Calendar | `marketing/calendar/index.blade.php` (1,229 lines) | **Moderately dense to cluttered** | 3-way view toggle, 6 platform filter pills, 2 checklist filters, 3 independent view renderers (month/week/list), color legend — a control panel bolted onto a calendar. |
| Overview/dashboard | `marketing/overview/index.blade.php` (96-line shell) + 8 partials ≈ 1,160 lines | **Long scroll, not a glance** | 8 stacked widgets (stat row, running campaigns, upcoming schedule, quick actions, platform status, activity feed, attention-needed, recent reviews). ~7x denser than the app's own dashboard baseline. |
| Analytics | `marketing/analytics/index.blade.php` | **Fine — matches house style** | 6 flat KPI cards, clear hierarchy. Legitimately an admin/KPI screen and built like one. |
| Integrations | `marketing/integrations/index.blade.php` (251 lines) | **Fine — genuinely simple** | 2-col grid of provider cards: icon, status badge, one button, one info line. This is the model to replicate elsewhere. |
| Settings | `marketing/settings/index.blade.php` (628 lines) | **Dense but organized** | 6 tabs; Notifications is a 6×3 toggle matrix, Permissions a 10×5 grid — a lot, but each tab is self-contained. |
| Brand Kit | `marketing/brand-kit/index.blade.php` (807 lines) | **Sprawling** | 8 form-card sections in one continuous scroll with a sticky side-nav — a setup-once task dressed as a permanent nav tab. |

General over-engineering signals: `transition`/`x-transition`/`@keyframes` appear 217 times and shadow/rounded-card divs 160 times across Marketing views (vs. near-zero in the app baseline). Heavy use of gradient backgrounds, decorative icon badges, hover-lift shadows, animated spinners/toasts. Alpine `x-data` is non-trivially nested (a full `calendarApp()` component; per-row `x-data` inside `@foreach` loops in Settings; nested `x-data` inside the Publish tab-switcher). Reads as a Buffer/Hootsuite-style SaaS dashboard, not the plain utilitarian tool the rest of Dentfluence is.

---

## 11. Coupling to the rest of Dentfluence

Deliberately thin — only two wires exist:
1. `CampaignLeadService::attributeLead()` — matches a lead's `utm_campaign` to a `Campaign` name, called from `LeadController::store()` (PRM/Leads module).
2. `resources/views/marketing/overview/partials/_recent-reviews.blade.php` — queries `App\Models\Review` directly (view-level, no controller) to surface 4+★ reviews as "turn into a post" prompts.

No references anywhere in `app/Services/Marketing`, `app/Http/Controllers/Marketing`, `app/Models/Marketing`, or `app/Jobs/Marketing` to `ActivityEngine`, `RulesEngine`, or `ActivityRecorded` — the event-bus infrastructure the rest of the app's automation runs on. Marketing does not participate in it at all today.

---

## 12. Known problems to fix regardless of front-end redesign

- `CLINIC_ID` is hardcoded to `1` in `AnalyticsController`/`OverviewController` — not multi-clinic safe.
- Two dead controllers (`MarketingController`, `CmsMediaController`) sitting unreferenced.
- Zero DB foreign keys across all 18 tables — integrity is app-layer only.
- `dam_asset_id` dangling in 3 tables with no relationship/accessor anywhere — an unfinished Digital Asset Manager hook.
- `mkt_post_variants.platform` enum missing `google_analytics`, present in `mkt_platform_connections.platform`.
- Analytics is a fully separate, duplicate KPI engine — doesn't reuse the app's existing Phase 6 "Read & Insights" layer or Huddle Reports, which already do similar aggregation elsewhere in the app.
- No live OAuth credentials configured (Meta/Google env vars don't exist); WhatsApp explicitly refuses to send.
- Integration cutover flags (`integration.meta/google/website/whatsapp`) all default off — the newer connector layer is fully built but not actually running in production; everything currently executes through the legacy inline-HTTP path.
- The "no_connection" case in `ProcessScheduledPost` returns a soft success, which can make an unpublished post look published in the UI.

---

## 13. The brief for re-engineering

Constraints to carry into any redesign, per the project's own standing rules: data-entry screens must be dead simple; admin/KPI screens can stay dense; never mix the two; avoid trendy SaaS visual flourishes (gradients, shadows, decorative icons, animations); build for the 90% workflow (a solo clinic posting consistently), not the 1% (an agency running multi-clinic campaigns with team roles and goal-tracking); prefer one strong workflow over five overlapping ones; reuse existing infrastructure (Phase 6 Insights, ActivityEngine/RulesEngine) instead of duplicating it.

Open questions worth having the re-engineering pass answer explicitly: (1) which of the 10 current sections collapse into a single "Content & Calendar" flow, and which (Campaigns, Team roles, Goals) get deferred to a later phase rather than shown to a solo-clinic user on day one; (2) whether Analytics should be rebuilt on top of the existing Insights layer instead of querying `mkt_*` tables directly; (3) whether Brand Kit becomes a one-time setup wizard instead of a permanent nav tab; (4) what the real, non-generic differentiator is if this is ever sold standalone — the two candidates already partially built are festival-date content prompts and review-to-post, both dental/reputation-specific rather than generic social scheduling.
