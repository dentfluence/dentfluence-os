# Dentfluence Marketing Hub V2 — Build Reference

> **Status:** Planning  
> **Version:** 2.0  
> **Build Strategy:** UI-First — see every page before wiring any backend  
> **Philosophy:** Growth Operating System for Dental Clinics

---

## Build Order Philosophy

```
Phase 1 — Shell         Routes + layout + nav + empty skeleton pages
Phase 2 — UI Layer      All pages built with mock/hardcoded data (no DB queries)
Phase 3 — Data Layer    Migrations + models + relationships + seeders
Phase 4 — Wire Up       Controllers + services + replace mock data with real queries
Phase 5 — Integrations  Platform OAuth + publish adapters + cross-module wiring
Phase 6 — Intelligence  AI generation + ROI engine + real analytics
```

**Why UI-first?**  
You validate layout, workflow, and UX before writing a single migration. If a page needs a redesign, you fix a Blade file — not a controller + service + view chain. Backend wiring happens once against a confirmed UI.

---

## Pre-Build Decisions

### What Gets Deleted
- All existing Marketing module routes, controllers, models, views, migrations
- Existing sidebar entries and module activation logic for Marketing
- Confirm tables: `php artisan migrate:status | grep mkt` before dropping anything

### What Gets Preserved
- Global auth, user, clinic, and permission infrastructure
- DAM module (Marketing integrates with it)
- Notification and activity log systems

### Module Activation Pattern (same as DAM)
- `modules` table entry for `marketing`
- `MarketingServiceProvider` checks activation before registering routes
- Middleware: `EnsureModuleActive::class('marketing')`
- When disabled: sidebar hidden, routes 404, APIs return 403

---

## Database Design Principles

- All tables prefixed: `mkt_`
- Every row scoped to `clinic_id`
- Soft deletes on all main entities
- `created_by`, `updated_by` on all tables
- JSON columns for platform metadata, AI outputs, settings
- No FK constraints to DAM tables — service layer only, using `dam_asset_id`

---

## Phase 1 — Module Shell

**Goal:** App navigates. All routes resolve. Pages exist but are empty. Zero errors.  
**Output:** Marketing shell with top nav, sidebar entry, and blank placeholder pages.

### 1.1 Module Registration
- [ ] Create `app/Modules/Marketing/` directory structure
- [ ] `MarketingServiceProvider` — registers routes, views, bindings
- [ ] `EnsureMarketingActive` middleware
- [ ] Add `marketing` entry to modules seeder
- [ ] Sidebar: Marketing section with all nav items, hidden when inactive

### 1.2 Routes (`routes/marketing.php`)

| Tab | Route Name | Path |
|---|---|---|
| Overview | `marketing.overview` | `/marketing` |
| Publish | `marketing.publish` | `/marketing/publish` |
| Calendar | `marketing.calendar` | `/marketing/calendar` |
| Brainstorm | `marketing.brainstorm` | `/marketing/brainstorm` |
| Campaigns | `marketing.campaigns` | `/marketing/campaigns` |
| Library | `marketing.library` | `/marketing/library` |
| Brand Kit | `marketing.brand-kit` | `/marketing/brand-kit` |
| Integrations | `marketing.integrations` | `/marketing/integrations` |
| Analytics | `marketing.analytics` | `/marketing/analytics` |
| Settings | `marketing.settings` | `/marketing/settings` |

### 1.3 Marketing Layout Shell
- [ ] `marketing/layouts/app.blade.php` — full shell with top nav tabs, search bar, user menu
- [ ] Active tab state on nav
- [ ] Consistent with Dentfluence OS visual language (dark sidebar, light content area)

### 1.4 Skeleton Pages (all return blank view with layout)
```
marketing/overview/index.blade.php
marketing/publish/index.blade.php
marketing/calendar/index.blade.php
marketing/brainstorm/index.blade.php
marketing/campaigns/index.blade.php
marketing/campaigns/show.blade.php
marketing/library/index.blade.php
marketing/brand-kit/index.blade.php
marketing/integrations/index.blade.php
marketing/analytics/index.blade.php
marketing/settings/index.blade.php
```

### 1.5 Blade UI Components (`resources/views/components/marketing/`)
```
marketing-card.blade.php       — card with header/body/footer slots
stat-card.blade.php            — KPI number + trend arrow + icon
platform-badge.blade.php       — platform icon + label (IG/FB/GBP/WA/Blog)
status-badge.blade.php         — Running/Scheduled/Draft/Published/Failed/Pending
campaign-progress.blade.php    — circular SVG progress ring
content-type-badge.blade.php   — Reel/Post/Carousel/Story/Blog/Offer
filter-bar.blade.php           — search input + dropdown filters row
empty-state.blade.php          — icon + heading + message + CTA button
activity-item.blade.php        — icon + text + timestamp row
```

### 1.6 Permissions (gates in MarketingServiceProvider)

| Role | Abilities |
|---|---|
| Owner | All |
| Marketing Manager | All except settings |
| Content Writer | Create/edit ideas, posts, drafts |
| Designer | Upload assets, edit media |
| Reviewer | Approve/reject content |
| Publisher | Schedule and publish |
| Viewer | Read only |

**Phase 1 done when:** All 10 routes load, nav highlights correct tab, no errors in log.

---

## Phase 2 — UI Layer

**Goal:** Every page looks finished. All layouts, cards, filters, panels built. Data is hardcoded mock — no DB queries, no controllers with logic.  
**Rule:** No `Invoice::` queries. No `DB::`. Hardcode arrays in controllers or pass from route closures.

### 2.1 Overview Dashboard UI

**Layout:** 3-column grid. Stats row top. Three column sections below.

**Widgets (each a Blade partial in `marketing/overview/partials/`):**

`_stat-row.blade.php`
- 6 stat cards: Marketing Score / Published / Scheduled / Drafts / Missed / Pending Approval
- Score card shows circular gauge (SVG), score/100, label ("Good progress!")
- Each stat card: icon, number, % vs last month (green up / red down)

`_running-campaigns.blade.php`
- Table/card list: Campaign name, status badge, Leads, Appointments, Revenue
- "View All →" link

`_upcoming-schedule.blade.php`
- Grouped by Today / Tomorrow
- Each row: time, platform badge, post title, content type badge

`_platform-status.blade.php`
- Instagram / Facebook / Google Business / WordPress / WhatsApp Business
- Each: icon, name, handle/URL, Connected (green) / Not connected (red) badge

`_quick-actions.blade.php`
- 6 icon buttons: Universal Publish / Create Blog / Create Reel / Add Idea / Create Campaign / Import Sheet

`_activity-feed.blade.php`
- Last 10 activity items: icon, text, time ago

`_attention-needed.blade.php`
- Alert list: missed posts / pending approvals / ideas waiting / campaign ending soon

### 2.2 Brainstorm UI

**Top tab bar:** AI Generate | Quick Idea | Idea Bank | Festival Planner

**AI Generate tab:**
- Left: prompt form — Treatment dropdown, Platform dropdown, Tone dropdown, "Generate Ideas" button
- Right: grid of idea cards (4 per row)
- Each idea card: content type badge (top-left), thumbnail image area, title, description (2 lines), 2 treatment tags, action row (bookmark / more / Save button)
- Side panel (slides in on card click): title, type badges, Idea Description, Reference Images (3 thumbnails + Add), Key Points checklist, Notes textarea (char counter), Save as Draft / Convert to Publish buttons

**Quick Idea tab:**
- Single clean form: Title, Content Type, Treatment Category, Platform, Description, Notes, Reference Image upload, Save button

**Idea Bank tab:**
- Filter bar: search + Treatment + Platform + Status + Date
- Same card grid as AI Generate, filterable
- Empty state component when no ideas

**Festival Planner tab:**
- Left: mini month calendar
- Right: list of upcoming festivals/dental awareness dates for selected month
- Each entry: date, festival name, suggested content type, "Create Idea" button
- Pre-seeded dates visible (World Oral Health Day 20 Mar, Children's Dental Health Month Feb, etc.)

### 2.3 Campaigns Index UI

**Two view toggles:** Kanban | List

**Kanban view:**
- 4 columns: Draft | Active | Paused | Completed
- Each column: header with count, campaign cards
- Campaign card: campaign name, treatment tag, date range, progress bar (content published %), 3 KPI pills (Leads / Appts / Revenue), team avatar stack, status badge

**List view:**
- Table: Campaign / Status / Treatment / Duration / Budget / Leads / Appts / Revenue / Completion / Actions
- Sortable columns
- Row click → campaign show page

**Top bar:** "New Campaign" button, search, filter (Status / Treatment / Date Range)

### 2.4 Campaign Show UI

**Header:**
- Campaign name + status badge
- Campaign Owner avatar + name
- Duration (dates + "X days remaining")
- Budget (planned)
- Target Audience
- Channel icons
- Share + Export Report buttons (right)

**Sub-tabs:** Overview | Content Plan | Assets | Leads & Appointments | Performance | Team | Settings

**Overview tab:**
- Left column: Campaign Progress (circular ring, 4 progress bars: Content Planned / Content Published / Budget Utilized / Goals Achieved)
- Right column: Goals table (Leads Generated / Appointments Booked / Treatments Started / Revenue Target) with actual vs target + % indicator
- Below: Content Plan mini-kanban (read-only, 6 columns, 3 cards each)
- Campaign Assets strip (5 thumbnails + Add Asset)
- Campaign Notes (last updated by)
- Right sidebar: Campaign Performance panel (Reach / Impressions / Engagement / Leads / Appointments / Revenue sparklines), Top Performing Content list, Team Members list

**Content Plan tab:**
- Full kanban: Idea | In Progress | In Review | Approved | Scheduled | Published
- Each card: platform icon, content type, title, date, assignee avatar, "..." menu
- "+ Add" at bottom of each column
- Collapsed "+ X more" for overflow

**Assets tab:**
- Same as Library UI but filtered to this campaign

**Leads & Appointments tab:**
- Two panels side by side
- Leads table: Name, Phone, Source, Date, Status
- Appointments table: Patient, Date/Time, Treatment, Status

**Performance tab:**
- Placeholder charts with "Analytics coming soon" label
- 6 metric cards with stubbed numbers

**Team tab:**
- Member list: avatar, name, role badge, joined date
- "+ Add Member" button → modal with role select

**Settings tab:**
- Edit form: Campaign name, Description, Objective, Treatment, Duration (date pickers), Budget, Target Audience, Channels (checkbox icons)

### 2.5 Universal Publish UI

**Three-panel layout:**

**Panel 1 — Master Content (left, ~40% width):**
- Post type selector: Post | Reel | Carousel | Story | Blog | Offer (pill tabs)
- Platform selector: icon toggles for IG / FB / GBP / Blog / WA (multi-select)
- Content textarea with live character counter (2200 / platform limit)
- Media area: uploaded thumbnails in a row + "Add Media or browse library" area
- CTA section: dropdown (Book Appointment / Learn More / Call Now / Custom) + URL field
- Hashtag area: tag chips + type-to-add input with "Save to Brand Kit" link
- Campaign association: searchable dropdown (optional)
- AI Assistant mini-bar: "Improve with AI" button + Content Score badge (92 / Excellent)

**Panel 2 — Platform Previews (center, ~40% width):**
- "Show all platforms" toggle
- Instagram Feed card (heart/comment/share icons, caption truncated)
- Facebook Feed card (like/comment/share buttons)
- Google Business card (search result style)
- Blog Preview card (title + excerpt + "Read more")
- WhatsApp card (chat bubble style with tick marks)
- Each preview has "..." menu (Edit this version / Reset to master)

**Panel 3 — Publish Panel (right, ~20% width):**
- Publishing Summary: Platforms / Total Variations / Est. Reach / Best Time
- Schedule section:
  - Radio: Publish Now | Schedule | Add to Queue | Save as Draft
  - Date picker + Time picker (shown when Schedule selected)
  - Timezone label
- "Schedule / Publish All" primary button

**Individual platform tabs** (sub-nav: Universal | Instagram | Facebook | Google Business | Blog | WhatsApp):
- Each shows same 3-panel layout but pre-filtered to that platform
- Platform-specific fields appear (e.g. Blog: Title, Slug, Meta Description, Category)

### 2.6 Content Calendar UI

**Top bar:** Month/Week/List toggle | Platform filters (All/IG/FB/GBP/Blog/WA) | + New Post button | Import/Export

**Left sidebar:**
- Mini month calendar with day dots
- Filter by Status checkboxes (with counts)
- Filter by Content Type checkboxes

**Month grid:**
- Each day cell: date number, post chips
- Post chip: platform icon + truncated title + time
- Chip color: campaign color or platform color
- Warning triangle on days with failed/missed posts
- Click chip → slide-out post detail panel

**Post detail panel (right slide-out):**
- Platform badge, content type badge, status badge
- Post title + content preview
- Scheduled date/time + timezone
- Campaign association
- Team member
- Action buttons: Edit / Reschedule / Publish Now / Delete

**Week view:** 7 columns, hourly rows, post blocks with platform color

**List view:** Table with Date / Time / Platform / Content Type / Title / Campaign / Status / Actions

### 2.7 Library UI

**Left sidebar:**
- Folder tree: All Assets / Uncategorized / Campaigns (expandable) / [campaign subfolders]
- Storage usage bar (34% of 200GB)
- "+ New Folder" button

**Main area:**
- Filter bar: search + Asset Type + Platform + Campaign + Tags + Date Modified
- View toggle: Grid | List
- Asset count label ("86 assets")
- Asset grid: thumbnail, type badge (top-left), name, size, date, tag chips
- "..." on hover: Download / Use in Post / Move / Delete

**Right detail panel (shown on asset click):**
- Large thumbnail
- File Name, Type, Size, Dimensions, Uploaded On, Uploaded By
- Folder, Campaign, Tags (editable chips + Add Tag)
- Description field
- Download button (primary)
- More Actions dropdown

**Tabs at top:** My Library | DAM Assets (Connected badge)

**DAM Assets tab:** Same layout, read-only, assets sourced from DAM module

### 2.8 Brand Kit UI

**Layout:** Left nav (section list) + Right form area

**Sections:**
- Logo — upload primary + secondary, light + dark variants. Preview on white/dark background.
- Brand Colors — 3 swatches (Primary / Secondary / Accent) with hex picker + label
- Typography — heading font dropdown + body font dropdown + preview text
- Clinic Info — name, phone, email, website, address, WhatsApp number
- Social Links — Instagram handle, Facebook URL, Google Business URL
- Default CTA — button text input + URL field + preview button
- Default Hashtags — tag chip input, save set
- AI Settings — Tone selector (Friendly / Professional / Educational / Promotional) + Brand Description textarea (used in AI prompts)

All sections: Save button per section, last saved timestamp.

### 2.9 Integrations UI

**Layout:** Grid of platform cards (2 per row)

**Each platform card:**
- Platform icon + name
- Description (1 line)
- Status badge: Connected (green) / Not Connected (grey)
- If connected: handle/account name, last synced
- Connect / Disconnect button
- Settings gear (shown when connected)

**Platforms:**
Instagram | Facebook | Google Business | WhatsApp Business | WordPress | Google Analytics

**Bottom banner:** "More integrations coming soon — YouTube, LinkedIn"

### 2.10 Analytics UI (Placeholder)

- "Analytics — Coming Soon" hero message
- 6 greyed-out metric card placeholders
- 2 greyed-out chart placeholders
- "We're building deep analytics. You'll see reach, leads, ROI per campaign, and more."

### 2.11 Settings UI

**Left tabs:** General | Approval Workflow | Scheduling | Notifications | Permissions | AI Defaults

**General tab:**
- Timezone selector
- Default post status (Draft / Pending Approval / Scheduled)
- Publishing confirmation toggle

**Approval Workflow tab:**
- Enable/disable approval toggle
- Approval chain: role selects in order
- Notify on approval/rejection toggles

**Scheduling tab:**
- Default publish times per platform (time pickers)
- Queue spacing (minutes between posts)
- Blackout days (date picker multi-select)

**Notifications tab:**
- Toggle list: Post published / Post failed / Post missed / Campaign deadline / Idea awaiting / Approval needed
- Channel: In-app / Email / WhatsApp

**Permissions tab:**
- Role → ability matrix (read-only table, links to global settings)

**AI Defaults tab:**
- Default language (English / Hindi / Marathi / etc.)
- Default tone (links to Brand Kit)
- Hashtag count default (slider 5–30)
- Auto-suggest best time toggle

**Phase 2 done when:** Every page renders correctly with mock data. Click every tab, every filter, every panel. Zero layout breaks.

---

## Phase 3 — Data Layer

**Goal:** All migrations written and run. All models with correct relationships. Seeders for dev data.  
**Rule:** No controller logic yet. Just schema + models.

### 3.1 Migrations (run order)

```
mkt_settings
mkt_brand_kits
mkt_campaigns
mkt_campaign_goals
mkt_ideas
mkt_idea_assets
mkt_posts
mkt_post_variants
mkt_post_media
mkt_post_schedules
mkt_assets
mkt_asset_folders
mkt_asset_tags
mkt_asset_tag_map
mkt_platform_connections
mkt_campaign_team
mkt_activity_log
mkt_festival_dates          — seeded list of Indian festivals + dental awareness dates
```

### 3.2 Models + Relationships

- `Campaign` — hasMany Posts, Goals, Assets, Team, ActivityLog
- `Idea` — belongsTo Campaign (nullable), hasMany IdeaAssets
- `MarketingPost` — belongsTo Campaign, hasMany Variants, Media, Schedules
- `PostVariant` — platform-specific version, belongsTo MarketingPost
- `MarketingAsset` — library item, belongsTo AssetFolder, belongsToMany AssetTag
- `BrandKit` — one per clinic (firstOrCreate by clinic_id)
- `PlatformConnection` — OAuth tokens per platform per clinic
- `MarketingSetting` — key-value per clinic
- `FestivalDate` — date, name, category, suggested_content_type, country

### 3.3 Seeders
- `MarketingModuleSeeder` — adds `marketing` to modules table
- `FestivalDateSeeder` — seeds Indian festivals + dental awareness dates (World Oral Health Day 20 Mar, Children's Dental Health Month Feb, Diwali, Holi, Navratri, Independence Day, etc.)

**Phase 3 done when:** `php artisan migrate` runs clean. All models instantiate. Tinker test relationships.

---

## Phase 4 — Backend Wiring

**Goal:** Replace all mock data with real DB queries. Controllers fully implemented.  
**Rule:** UI should not change visually — only data becomes real.

### 4.1 Overview Dashboard
- `OverviewController@index` — queries real posts, campaigns, platform connections
- API endpoints: `/api/marketing/overview/stats`, `/schedule`, `/activity`
- Marketing Score calculator (`MarketingScoreService`)

### 4.2 Brainstorm
- `IdeaController` full CRUD
- `BrainstormController@index` — paginated idea bank
- Convert to Campaign / Convert to Post flows
- Festival Planner: reads from `mkt_festival_dates`, shows current month

### 4.3 Campaigns
- `CampaignController` full CRUD + tab data methods
- Campaign goals store/update
- Content plan kanban data (posts grouped by status)
- Team add/remove

### 4.4 Universal Publish
- `PublishController` — store master post + auto-create variants per selected platform
- Per-variant update endpoint
- Schedule endpoint (writes to `mkt_post_schedules`)
- Draft save
- Queue job: `ProcessScheduledPost` (checks `mkt_post_schedules` for due posts)

### 4.5 Content Calendar
- `CalendarController` — returns posts grouped by date for selected month
- Reschedule via drag-drop (PUT endpoint)
- CSV import (basic: date, platform, title, content)
- CSV/Excel export

### 4.6 Library
- `LibraryController` + `AssetController` — full CRUD
- Folder create/rename/delete
- Tag management
- DAM bridge: reads DAM assets via `DamAssetService` when DAM active
- Storage usage query

### 4.7 Brand Kit
- `BrandKitController` — single record per clinic, create if not exists
- Logo upload (stored in `storage/marketing/logos/`)

**Phase 4 done when:** Full idea → campaign → post → calendar flow works end-to-end with real data.

---

## Phase 5 — Integrations & Cross-Module Wiring

**Goal:** Real platform connections. Posts actually publish. Cross-module data flows.

### 5.1 Platform OAuth Connections
- Instagram + Facebook (Meta Graph API, OAuth)
- Google Business (Google OAuth, GBP API)
- WhatsApp Business (via BSP — Gupshup or similar)
- WordPress (REST API, app password)
- Google Analytics (Google OAuth, read-only)

**Adapter pattern:**
```php
interface PublishAdapterInterface {
    public function publish(PostVariant $variant): PublishResult;
    public function schedule(PostVariant $variant, Carbon $time): ScheduleResult;
    public function getStatus(string $externalId): PostStatus;
    public function delete(string $externalId): bool;
}
```

### 5.2 Communication OS Integration
- `MarketingLeadCaptured` event → Comm OS creates contact + assigns to inbox
- `MarketingCampaignActivated` event → registers campaign in Comm OS
- Leads & Appointments tab → reads from Comm OS API (read-only)

### 5.3 Reports Integration
- `MarketingReportDataProvider` registered with Reports Engine
- Methods: `getCampaignSummary()`, `getPostingActivity()`, `getPlatformBreakdown()`

### 5.4 Dashboard + Daily Huddle Widgets
- 5 Dashboard widgets registered
- Daily Huddle items: today's posts, pending approvals, failed posts, campaign deadlines

**Phase 5 done when:** Post created → scheduled → published on Instagram. Lead captured → appears in Comm OS inbox.

---

## Phase 6 — Intelligence Layer

**Goal:** AI-powered features, real analytics, ROI chain.

### 6.1 AI Content Generation
- Brainstorm AI Generate: Brand Kit context + treatment + platform → idea cards via Claude/OpenAI
- Festival Planner AI: given month → suggest content ideas tied to festivals
- Universal Publish AI Assistant: Content Score + "Improve with AI" rewrite
- Caption generator from uploaded image (vision model)
- Hashtag recommender

### 6.2 ROI Engine
- `RoiEngineService` — links Campaign → Appointments (Comm OS) → Treatments (clinical) → Revenue
- Calculates: Cost per Lead, Cost per Appointment, Revenue per Campaign, ROI %
- Displayed on: Campaign Performance tab + Analytics page

### 6.3 Real Analytics
- Pull Reach / Impressions / Engagement from platform APIs
- Lead attribution per campaign
- Top performing content (by engagement, by leads)
- Best time to post analysis (based on historical engagement data)
- Platform comparison charts

**Phase 6 done when:** Campaign shows real ROI. AI generates usable content ideas. Analytics charts populate from real platform data.

---

## Dropped Features (Out of Scope — Not Phase 7)

| Feature | Reason |
|---|---|
| Competitor Ideas | Requires external scraping infra; low clinical value |
| Mood Board | No workflow benefit over Library |
| Landing Pages | Separate product concern |
| Multi-workspace tabs | UX complexity not justified |
| Google Search Console | Not a priority for clinic-level marketing |
| LinkedIn | Not a patient acquisition channel for dental |
| YouTube | Clinic video strategy is Instagram Reels first |
| Automation rules | Post-validation only (after 2 real clinics use it) |

---

## File Structure

```
app/Modules/Marketing/
  Http/Controllers/
    OverviewController.php
    PublishController.php
    CalendarController.php
    BrainstormController.php
    IdeaController.php
    CampaignController.php
    LibraryController.php
    AssetController.php
    BrandKitController.php
    IntegrationController.php
    AnalyticsController.php
    SettingsController.php
  Middleware/EnsureMarketingActive.php
  Models/
    Campaign.php / CampaignGoal.php
    Idea.php / IdeaAsset.php
    MarketingPost.php / PostVariant.php / PostMedia.php / PostSchedule.php
    MarketingAsset.php / AssetFolder.php / AssetTag.php
    BrandKit.php / PlatformConnection.php
    MarketingSetting.php / MarketingActivityLog.php / FestivalDate.php
  Services/
    CampaignService.php / IdeaService.php / PublishService.php
    CalendarService.php / LibraryService.php / BrandKitService.php
    IntegrationService.php / AnalyticsService.php
    MarketingScoreService.php / RoiEngineService.php
    Adapters/
      InstagramAdapter.php / FacebookAdapter.php
      GoogleBusinessAdapter.php / WhatsAppAdapter.php / BlogAdapter.php
    Contracts/
      PublishAdapterInterface.php
  Providers/MarketingServiceProvider.php
  Events/
    MarketingLeadCaptured.php / CampaignActivated.php
    PostPublished.php / PostFailed.php

resources/views/marketing/
  layouts/app.blade.php
  overview/index.blade.php + partials/
  publish/index.blade.php + partials/
  calendar/index.blade.php
  brainstorm/index.blade.php + partials/
  campaigns/index.blade.php + show.blade.php + partials/
  library/index.blade.php
  brand-kit/index.blade.php
  integrations/index.blade.php
  analytics/index.blade.php
  settings/index.blade.php

resources/views/components/marketing/
  marketing-card / stat-card / platform-badge / status-badge
  campaign-progress / content-type-badge / filter-bar
  empty-state / activity-item

database/migrations/  (17 mkt_ tables)
routes/marketing.php
```

---

## Session Convention

Always start a session by stating: **"Phase X.Y — [exact sub-task name]"**  
Example: `"Phase 2 — 2.4 Universal Publish UI"`

If output exceeds ~200 lines mid-task, stop at a clean point and say what remains.  
Never run `migrate:fresh` without explicit confirmation.  
Always read existing files before editing.
