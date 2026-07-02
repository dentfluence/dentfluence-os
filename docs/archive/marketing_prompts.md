# Dentfluence Marketing Hub V2 — Build Prompts

> Paste these prompts as-is at the start of each build session.  
> Each prompt is self-contained — it tells Claude what exists, what to build, and what not to touch.  
> Check truncation risk before starting. If HIGH or VERY HIGH, use the split prompts provided.

---

## Truncation Risk Scale

| Level | Expected Output | Action |
|---|---|---|
| 🟢 LOW | < 100 lines | Build in one shot |
| 🟡 MEDIUM | 100–250 lines | Build in one shot, watch for cutoff |
| 🔴 HIGH | 250–400 lines | Use split prompts below |
| 🔴🔴 VERY HIGH | 400+ lines | Always split — multiple sessions |

---

---

# PHASE 1 — Module Shell

---

## P1 — Module Shell & Navigation

🟡 **MEDIUM** (~200 lines across multiple files)  
Split recommended: Yes — do 1A (registration + routes) then 1B (layout + skeleton pages) then 1C (components)

---

### P1-A: Module Registration + Routes

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 1-A — Marketing Hub module registration and routing.

Context:
- Existing modules (DAM, etc.) follow a ModuleServiceProvider pattern with an EnsureModuleActive middleware
- There is a `modules` table with an entry per module
- Marketing module does NOT exist yet — we are building from scratch
- Do NOT touch any existing modules, routes, or files

Build:
1. Create `app/Http/Middleware/EnsureMarketingActive.php` — checks modules table for 'marketing', aborts 403 if inactive
2. Create `routes/marketing.php` with these routes (all under auth + EnsureMarketingActive middleware):
   - GET /marketing → marketing.overview (OverviewController@index)
   - GET /marketing/publish → marketing.publish (PublishController@index)
   - GET /marketing/calendar → marketing.calendar (CalendarController@index)
   - GET /marketing/brainstorm → marketing.brainstorm (BrainstormController@index)
   - GET /marketing/campaigns → marketing.campaigns (CampaignController@index)
   - GET /marketing/campaigns/{campaign} → marketing.campaigns.show (CampaignController@show)
   - GET /marketing/library → marketing.library (LibraryController@index)
   - GET /marketing/brand-kit → marketing.brand-kit (BrandKitController@index)
   - GET /marketing/integrations → marketing.integrations (IntegrationController@index)
   - GET /marketing/analytics → marketing.analytics (AnalyticsController@index)
   - GET /marketing/settings → marketing.settings (SettingsController@index)
3. Create `app/Providers/MarketingServiceProvider.php` — registers the routes file, views namespace 'marketing'
4. Register MarketingServiceProvider in config/app.php providers array
5. Include `routes/marketing.php` in routes/web.php (one line require/include)
6. Create stub controllers (just index/show returning view()) for:
   OverviewController, PublishController, CalendarController, BrainstormController,
   IdeaController, CampaignController, LibraryController, AssetController,
   BrandKitController, IntegrationController, AnalyticsController, SettingsController
   — all in app/Http/Controllers/Marketing/

Read routes/web.php and config/app.php before editing them.
Do not run any artisan commands.
```

---

### P1-B: Marketing Layout + Skeleton Pages

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 1-B — Marketing layout shell and skeleton pages.

Context:
- Routes and controllers exist from P1-A (already built)
- Existing app layout is at resources/views/layouts/app.blade.php — read it first to match the visual language (dark sidebar, top nav, content area)
- Marketing gets its OWN layout at resources/views/marketing/layouts/app.blade.php
- The existing app uses Bootstrap or Tailwind — check resources/views/layouts/app.blade.php to confirm which and match it exactly

Build:
1. Read resources/views/layouts/app.blade.php to understand the existing structure, CSS framework, and sidebar pattern
2. Create resources/views/marketing/layouts/app.blade.php:
   - Full page shell extending the global layout OR standalone with same visual language
   - Top navigation bar with 10 tabs: Overview | Publish | Calendar | Brainstorm | Campaigns | Library | Brand Kit | Integrations | Analytics | Settings
   - Active tab highlighted based on current route
   - Search bar in header (placeholder: "Search patient, content, campaign...")
   - User avatar + name + role in top right
   - @yield('marketing-content') as the main content slot
3. Create skeleton blade views (just extend layout + empty content block) for all 11 pages:
   resources/views/marketing/overview/index.blade.php
   resources/views/marketing/publish/index.blade.php
   resources/views/marketing/calendar/index.blade.php
   resources/views/marketing/brainstorm/index.blade.php
   resources/views/marketing/campaigns/index.blade.php
   resources/views/marketing/campaigns/show.blade.php
   resources/views/marketing/library/index.blade.php
   resources/views/marketing/brand-kit/index.blade.php
   resources/views/marketing/integrations/index.blade.php
   resources/views/marketing/analytics/index.blade.php
   resources/views/marketing/settings/index.blade.php

Each skeleton page should show: page title, subtitle, and a "Coming soon" placeholder paragraph.
Do not run any artisan commands.
```

---

### P1-C: Blade UI Components

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 1-C — Marketing reusable Blade components.

Context:
- Read resources/views/marketing/layouts/app.blade.php to confirm CSS framework (Bootstrap or Tailwind)
- Build ALL components to match that framework exactly
- Components live in resources/views/components/marketing/

Build these 9 Blade components:

1. marketing-card.blade.php
   Props: title (optional), subtitle (optional), actions slot (optional)
   Slots: default (body content)
   A clean white card with border, optional header row (title + actions), body padding

2. stat-card.blade.php
   Props: label, value, trend ('+20%' or '-5%'), trend_direction ('up'/'down'), icon, color
   Shows: icon (left), large number (center), label (below), trend badge (green if up, red if down)

3. platform-badge.blade.php
   Props: platform ('instagram'/'facebook'/'google'/'whatsapp'/'blog'/'wordpress')
   Shows: correct platform icon (use inline SVG or Font Awesome) + platform name
   Each platform has its brand color as a subtle background

4. status-badge.blade.php
   Props: status ('running'/'scheduled'/'draft'/'published'/'failed'/'pending'/'paused'/'completed')
   Color-coded pill badge for each status

5. campaign-progress.blade.php
   Props: percentage (0-100)
   SVG circular ring showing percentage, number in center

6. content-type-badge.blade.php
   Props: type ('reel'/'post'/'carousel'/'story'/'blog'/'offer')
   Small colored pill badge with icon

7. filter-bar.blade.php
   Props: search_placeholder, filters (array of [label, name, options[]])
   Renders: search input + dropdown filters in a row

8. empty-state.blade.php
   Props: icon, heading, message, cta_text (optional), cta_url (optional)
   Centered empty state block

9. activity-item.blade.php
   Props: icon, icon_color, text, time_ago
   Single activity feed row with colored icon, text, right-aligned time

Do not run any artisan commands.
```

---

---

# PHASE 2 — UI Layer

---

## P2.1 — Overview Dashboard UI

🔴 **HIGH** (~350 lines across multiple partials)  
Split: P2.1-A (stat row + running campaigns + upcoming schedule) → P2.1-B (platform status + quick actions + activity + attention)

---

### P2.1-A: Overview — Stats, Campaigns, Schedule

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.1-A — Marketing Overview Dashboard UI (top half).

Context:
- Read resources/views/marketing/layouts/app.blade.php to confirm CSS framework
- Read resources/views/marketing/overview/index.blade.php (currently skeleton)
- This is UI ONLY — all data is hardcoded mock arrays passed from the controller
- No DB queries. Controller just does: return view('marketing.overview.index', [...mock data...])
- Reference UI: Marketing Overview Dashboard screenshot shared in project spec

Build:

1. Update app/Http/Controllers/Marketing/OverviewController.php to pass mock data:
   - $stats: array with keys: score(78), published(16), scheduled(12), drafts(8), missed(2), pending_approval(4) + trend percentages
   - $runningCampaigns: 3 mock campaigns with name, status, leads, appointments, revenue
   - $upcomingSchedule: 5 mock posts grouped by ['Today - 14 Jun', 'Tomorrow - 15 Jun'] with time, platform, title, content_type

2. Update resources/views/marketing/overview/index.blade.php to include the partials

3. Create resources/views/marketing/overview/partials/_stat-row.blade.php:
   - Marketing Score card (large, left): circular SVG gauge showing 78/100, "Good progress! Keep going 👍", sub-label
   - 5 stat cards in a row: Published(16, +14%), Scheduled(12, +20%), Missed(2, -50%), Drafts(8), Pending Approval(4, -20%)
   - Use the stat-card component

4. Create resources/views/marketing/overview/partials/_running-campaigns.blade.php:
   - Card with header "Running Campaigns" + "View All →" link
   - 3 campaign rows: name, description, status badge, Leads count, Appointments count, Revenue (₹)
   - Use x-marketing-card component

5. Create resources/views/marketing/overview/partials/_upcoming-schedule.blade.php:
   - Card with header "Upcoming Schedule" + "View Calendar →" link
   - Grouped by Today / Tomorrow
   - Each row: time (left), platform badge, post title, content type badge (right)

Do not run any artisan commands. UI only — no real queries.
```

---

### P2.1-B: Overview — Platform Status, Quick Actions, Activity, Attention

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.1-B — Marketing Overview Dashboard UI (bottom/right widgets).

Context:
- P2.1-A is complete. Overview layout and top widgets are done.
- Read resources/views/marketing/overview/index.blade.php to see current structure
- Read resources/views/marketing/layouts/app.blade.php for CSS framework
- UI only — mock data in controller

Build (add to OverviewController mock data + create these partials):

1. resources/views/marketing/overview/partials/_platform-status.blade.php:
   - Card "Platform Status" + "Manage →" link
   - 5 rows: Instagram (@tulipdental_clinic, Connected), Facebook (Tulip Dental Clinic, Connected), Google Business (Connected), Website/WordPress (Not connected — red), WhatsApp Business (+91 9876543210, Connected)
   - Each row: platform icon, name, handle, Connected/Not Connected badge

2. resources/views/marketing/overview/partials/_quick-actions.blade.php:
   - 6 action icon buttons in a row: Universal Publish / Create Blog / Create Reel / Add Idea / Create Campaign / Import Sheet
   - Each: rounded icon (colored), label below
   - Link to relevant marketing routes

3. resources/views/marketing/overview/partials/_activity-feed.blade.php:
   - Card "Recent Activity" + "View All →" link
   - 6 activity rows using x-activity-item component:
     "Implant Reel published on Instagram" 2h ago
     "Blog: RCT Guide scheduled" 3h ago
     "Monsoon Offer post published on GBP" 5h ago
     "Before/After IG post saved as draft" 1d ago
     "New idea added: Aligner Journey" 1d ago

4. resources/views/marketing/overview/partials/_attention-needed.blade.php:
   - Card "Attention Needed" + "View All →" link
   - Alert list: ⚠ 2 missed posts this week, ⏰ 1 blog pending approval, 💡 4 ideas waiting to be converted, 📅 1 campaign ending soon
   - Each: colored icon, text

5. Wire all partials into resources/views/marketing/overview/index.blade.php using a clean 3-column grid layout

Do not run any artisan commands.
```

---

## P2.2 — Brainstorm UI

🔴🔴 **VERY HIGH** (~450+ lines)  
Split: P2.2-A (tab shell + AI Generate tab) → P2.2-B (Quick Idea + Idea Bank + Festival Planner)

---

### P2.2-A: Brainstorm — Tab Shell + AI Generate

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.2-A — Brainstorm UI: tab navigation + AI Generate tab.

Context:
- Read resources/views/marketing/layouts/app.blade.php for CSS framework
- Read resources/views/marketing/brainstorm/index.blade.php (currently skeleton)
- UI only — hardcoded mock data in controller
- Reference: Brainstorm screenshot in project spec — card grid with side panel

Build:

1. Update BrainstormController@index to pass mock data:
   $ideas: 8 mock idea objects with: id, type(reel/post/carousel), title, description, tags[], image_placeholder, platform

2. Update resources/views/marketing/brainstorm/index.blade.php:
   - Page header: "Brainstorm" title + "Import Ideas" + "Create New Idea" buttons
   - Tab bar: AI Generate (active) | Quick Idea | Idea Bank | Festival Planner
   - Main content area (tab panels with Alpine.js x-show)

3. Create AI Generate tab content:
   - Left control bar: Treatment dropdown (Implants/Smile Makeover/Whitening/Aligners/General), Platform dropdown (Instagram/Facebook/Google/WhatsApp/Blog), Tone dropdown (Friendly/Professional/Educational/Promotional), "Generate Ideas" purple button
   - Category filter pills: All | Implants | Smile Makeover | Whitening | Aligners | General Dentistry
   - 8-card grid (4 per row) of idea cards

4. Create resources/views/marketing/brainstorm/partials/_idea-card.blade.php:
   - Content type badge (top-left overlay on image area)
   - Image placeholder area (grey bg with icon)
   - Title (bold)
   - Description (2 lines, truncated)
   - 2 tag chips
   - Action row: bookmark icon | "..." more | Save button (right)

5. Create resources/views/marketing/brainstorm/partials/_idea-detail-panel.blade.php:
   - Right slide-in panel (fixed, appears when card clicked via Alpine.js)
   - Close X button
   - Title + content type badge + edit icon
   - "Idea Description" section
   - "Reference Images" section: 3 image thumbnails + "+ Add Images" tile
   - "Key Points to Cover" section: checklist with green checkmarks
   - "Notes (Optional)" textarea with char counter (112/500)
   - Footer: "Save as Draft" + "Convert to Publish →" buttons

Use Alpine.js for tab switching and panel open/close (already in Dentfluence stack).
Do not run any artisan commands.
```

---

### P2.2-B: Brainstorm — Quick Idea + Idea Bank + Festival Planner

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.2-B — Brainstorm UI: Quick Idea, Idea Bank, Festival Planner tabs.

Context:
- P2.2-A is complete. Tab shell and AI Generate tab are done.
- Read resources/views/marketing/brainstorm/index.blade.php to see current tab structure
- UI only — mock data

Build (add tab content panels to the existing brainstorm index):

1. Quick Idea tab panel:
   - Clean centered form (max-width: 600px)
   - Fields: Title (text), Content Type (pill selector: Post/Reel/Carousel/Story/Blog/Offer), Treatment Category (dropdown), Platform (multi-select icons), Description (textarea, 500 chars), Notes (textarea, optional), Reference Image (file upload zone with drag-drop), Priority (Low/Medium/High radio)
   - Buttons: "Save Idea" (primary) + "Convert to Publish" (secondary)

2. Idea Bank tab panel:
   - Filter bar: search input + Treatment dropdown + Platform dropdown + Status dropdown (All/Draft/Saved/Converted) + Date range
   - "Showing X ideas" count
   - Same 4-per-row card grid as AI Generate (reuse _idea-card.blade.php partial)
   - Batch select mode (checkbox on hover + "Select All" + "Batch Actions" dropdown)
   - Empty state component when no results

3. Festival Planner tab panel:
   - Two-column layout: left (mini calendar) + right (festival list)
   - Left: month navigator (← June 2026 →), calendar grid with festival dots on dates, click date to filter right
   - Right: list of festivals for selected month, each entry:
     - Date pill (e.g. "20 Jun")
     - Festival/occasion name (e.g. "World Oral Health Day")
     - Category badge (Dental / National / Regional / Religious)
     - Suggested content type badge
     - "Create Idea" button → prefills Quick Idea form
   - Mock data for June: World Environment Day (5 Jun), Father's Day (15 Jun), International Yoga Day (21 Jun), plus 2 mock dental awareness dates

Do not run any artisan commands.
```

---

## P2.3 — Campaigns UI

🔴🔴 **VERY HIGH** (~500+ lines)  
Split: P2.3-A (index: kanban + list) → P2.3-B (show: header + overview tab) → P2.3-C (show: remaining tabs)

---

### P2.3-A: Campaigns Index UI

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.3-A — Campaigns Index UI (kanban + list views).

Context:
- Read resources/views/marketing/layouts/app.blade.php for CSS framework
- Read resources/views/marketing/campaigns/index.blade.php (currently skeleton)
- UI only — mock data in controller

Build:

1. Update CampaignController@index to pass mock data:
   $campaigns: 4 mock campaigns across statuses Draft/Active/Paused/Completed with: name, treatment, dates, budget, leads, appointments, revenue, completion_pct, team_avatars(3), status

2. Update campaigns/index.blade.php:
   - Page header: "Campaigns" title + "+ New Campaign" button
   - Top bar: search input + Status filter + Treatment filter + Date Range filter + Kanban/List view toggle (right)

3. Kanban view (default, Alpine.js x-show):
   - 4 columns: Draft(1) | Active(2) | Paused(1) | Completed(0)
   - Column header: status name + count badge + "+ Add" icon
   - Campaign card:
     - Top: campaign name (bold) + "..." menu
     - Treatment tag chip
     - Date range (small, grey)
     - Progress bar (full width, shows completion %)
     - 3 KPI pills in a row: Leads(42) / Appts(18) / ₹3.2L
     - Bottom: 3 stacked team avatars + days remaining label
   - "Drop campaign here" empty state per column

4. List view (Alpine.js x-show):
   - Table: Campaign / Status / Treatment / Duration / Budget / Leads / Appts / Revenue / Completion / Actions
   - Status column: x-status-badge component
   - Completion column: mini progress bar
   - Actions column: View / Edit / ... menu icons
   - Hover state on rows

Do not run any artisan commands.
```

---

### P2.3-B: Campaign Show — Header + Overview Tab

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.3-B — Campaign Show page: header + Overview tab.

Context:
- Read resources/views/marketing/layouts/app.blade.php for CSS framework
- Read resources/views/marketing/campaigns/show.blade.php (currently skeleton)
- UI only — mock data in controller

Build:

1. Update CampaignController@show to pass rich mock data for "Smile Makeover June":
   status: Running, owner: Dr. Sumit Firke, dates: 01 Jun–30 Jun 2026, budget: ₹50,000, audience: Adults 20–50 Local Area, channels: [instagram, facebook, google, wordpress] + 2 more, progress: {content_planned:18/24, content_published:12/24, budget_utilized:34000/50000, goals_achieved:4/7}, goals: {leads:150, appointments:45, treatments:20, revenue:400000}, actual: {leads:102, appointments:28, treatments:12, revenue:220000}

2. Build campaign show page header:
   - Breadcrumb: Campaigns > Smile Makeover June
   - Campaign name (h1) + status badge (Running, green)
   - Description line (small grey)
   - 5 meta pills: Campaign Owner (avatar + name) | Duration (date range + "30 days remaining") | Budget (₹50,000 Planned) | Target Audience | Channels (platform icons + "+2" overflow)
   - Right: "Share" button + "Export Report" button + "..." menu

3. Sub-tab navigation: Overview | Content Plan | Assets | Leads & Appointments | Performance | Team | Settings

4. Create resources/views/marketing/campaigns/partials/_overview-tab.blade.php:
   - Two-column main area:
     LEFT: "Campaign Progress" card — circular progress ring (68%), 4 labeled progress bars (Content Planned / Content Published / Budget Utilized / Goals Achieved)
     RIGHT: "Goals" card — table with Leads/Appts/Treatments/Revenue: target vs actual vs % with up arrow
   - Full-width "Content Plan" mini-kanban (read-only): 6 columns, 3 stubbed cards each, "+X more" labels
   - Two side-by-side cards: "Campaign Assets" (5 image thumbs + Add Asset) | "Campaign Notes" (text + last updated)

5. Right sidebar panel (fixed right, ~280px):
   - "Campaign Performance" card: vs last 30 days toggle, 6 metric rows (Reach/Impressions/Engagement/Leads/Appointments/Revenue) each with value, % change, mini sparkline
   - "Top Performing Content" card: 3 rows with thumbnail, type badge, title, platform, engagement count
   - "Team Members" card: 4 rows with avatar, name, role badge + "Manage" link

Do not run any artisan commands.
```

---

### P2.3-C: Campaign Show — Remaining Tabs

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.3-C — Campaign Show: Content Plan, Assets, Leads, Performance, Team, Settings tabs.

Context:
- P2.3-B is complete. Header and Overview tab are done.
- Read resources/views/marketing/campaigns/show.blade.php to see current tab structure
- UI only — mock data

Build tab panels (add to existing Alpine.js tab switcher):

1. _content-plan-tab.blade.php:
   - Full kanban: Idea(6) | In Progress(4) | In Review(3) | Approved(5) | Scheduled(6) | Published(12)
   - Each column: header with count + "+ Add" button
   - Cards: platform icon + content type badge + title + date + assignee avatar + "..." menu
   - "+X more ideas/published" collapsed overflow per column

2. _assets-tab.blade.php:
   - Same grid as Library UI, filtered to this campaign (5 mock assets with type badges, dates, tags)
   - "+ Upload Asset" button top right

3. _leads-tab.blade.php:
   - Two panels (50/50):
     LEFT "Leads (102)": table with Name / Phone / Source / Date / Status badge. 5 mock rows.
     RIGHT "Appointments (28)": table with Patient / Date+Time / Treatment / Status badge. 5 mock rows.
   - "Powered by Communication OS" small label at bottom

4. _performance-tab.blade.php:
   - 6 metric cards (stubbed numbers with greyed charts)
   - Placeholder area: "Connect platforms in Integrations to see real analytics"
   - Two large chart placeholder boxes (bar chart + line chart outlines with "Coming soon" label)

5. _team-tab.blade.php:
   - Team member list: avatar + name + role badge + "Joined X days ago" + Remove button
   - "+ Add Member" row at bottom: searchable user dropdown + role select + Add button

6. _settings-tab.blade.php:
   - Edit form: Campaign Name, Description, Objective (textarea), Treatment Category (dropdown), Start Date + End Date (date pickers), Budget (number), Target Audience (text), Channels (icon checkboxes for IG/FB/GBP/WA/Blog/Website)
   - "Save Changes" primary button + "Archive Campaign" danger link

Do not run any artisan commands.
```

---

## P2.4 — Universal Publish UI

🔴🔴 **VERY HIGH** (~400+ lines)  
Split: P2.4-A (master content form + platform previews) → P2.4-B (publish panel + individual platform tabs)

---

### P2.4-A: Universal Publish — Master Content + Previews

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.4-A — Universal Publish UI: master content form and platform previews.

Context:
- Read resources/views/marketing/layouts/app.blade.php for CSS framework
- Read resources/views/marketing/publish/index.blade.php (currently skeleton)
- UI only — no form submission logic yet
- Reference: Universal Publish screenshot in project spec — 3-panel layout

Build:

1. Update publish/index.blade.php with the full 3-panel layout:
   - Page header: "Universal Publish" + "Create once. Publish everywhere." subtitle + "Save as Draft" + "Schedule / Publish" buttons (top right)
   - Platform sub-tabs: Universal (active) | Instagram | Facebook | Google Business | Blog | WhatsApp

2. Panel 1 — Master Content (~40% width, left):
   Create resources/views/marketing/publish/partials/_master-content.blade.php:
   - Section ① label: "Master Content"
   - Post type pill selector: Post(active) | Reel | Carousel | Story | Blog | Offer
   - Content textarea: "A confident smile can change everything..." placeholder, 186/2200 char counter (colored dot: green when within limit)
   - Emoji / # hashtag / ✨ AI improve icon buttons below textarea
   - Media row: 3 image thumbnails + "+ Add Media or browse library" dashed tile
   - "Call to Action (Optional)" section: dropdown (Book Appointment) + URL field + copy icon
   - Hashtags section: 5 tag chips (#SmileMakeover #ConfidentSmile etc.) + type-to-add + × remove

3. Panel 2 — Platform Previews (~40% width, center):
   Create resources/views/marketing/publish/partials/_platform-previews.blade.php:
   - Section ② label + "Show all platforms" toggle
   - Instagram Feed card: profile pic + handle, image, heart/comment/save icons, caption text truncated with "more", slide indicators (1/5)
   - Facebook Feed card: profile pic + name + timestamp, image, caption, Like/Comment/Share buttons
   - Google Business card: logo + clinic name + "See more" badge, image, caption excerpt + "See more" link, "Learn more" button
   - Blog Preview card: large image, title "A Confident Smile Can Change Everything", excerpt, author + date, "Read more →"
   - WhatsApp card: chat bubble style, green background, caption text, tick marks, time (11:30 AM)
   - Each preview: "..." menu top right (Edit this version / Reset to master)

Do not run any artisan commands.
```

---

### P2.4-B: Universal Publish — Publish Panel + Platform Tabs

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.4-B — Universal Publish UI: publish panel + individual platform tabs.

Context:
- P2.4-A is complete. Master content form and previews are done.
- Read resources/views/marketing/publish/index.blade.php to see current structure
- UI only

Build:

1. Panel 3 — Publish Panel (~20% width, right):
   Create resources/views/marketing/publish/partials/_publish-panel.blade.php:
   - "AI Assistant Beta" section header
   - Content Score: donut gauge (92, "Excellent"), 5 checkmarks: Engaging headline / Strong message / Clear CTA / Optimal length / Hashtags included
   - "✨ Improve with AI" button (purple outline)
   - Divider
   - "Publishing Summary" section:
     Platforms: 6 | Total variations: 6 | Estimated reach: 12.4K | Best time to post: Today, 7:00 PM
   - "Schedule" section:
     4 radio options: Publish Now | Schedule (selected) | Add to Queue | Save as Draft
     Date picker (18 Jun 2026) + Time picker (07:00 PM) — shown when Schedule selected
     Timezone label: (GMT+05:30) Asia/Kolkata
   - "🚀 Schedule / Publish All" primary full-width button with dropdown arrow

2. Individual Platform Tabs:
   - When Instagram tab is active: same 3-panel layout but Panel 1 shows Instagram-specific fields:
     - Character limit indicator changes to 2200
     - Carousel slide manager appears (if Carousel type selected)
     - Alt text field for accessibility
   - When Blog tab is active: Panel 1 shows rich text editor placeholder + SEO fields (Title, Slug, Meta Description, Category dropdown, Tags)
   - When Google Business tab: Panel 1 shows Offer Type field + Event Start/End + CTA button type
   - When WhatsApp tab: Panel 1 shows Message Type (text/image/document), template note
   - When Facebook tab: same as Universal with FB-specific link preview section

3. Wire the platform sub-tabs with Alpine.js (x-show per tab)

Do not run any artisan commands.
```

---

## P2.5 — Content Calendar UI

🔴 **HIGH** (~300 lines)  
Split: P2.5-A (month view + sidebar) → P2.5-B (week/list views + post detail panel)

---

### P2.5-A: Calendar — Month View + Sidebar

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.5-A — Content Calendar UI: month view and left sidebar.

Context:
- Read resources/views/marketing/layouts/app.blade.php for CSS framework
- Read resources/views/marketing/calendar/index.blade.php (currently skeleton)
- UI only — mock data

Build:

1. Update CalendarController@index to pass:
   $posts: 10 mock posts across June 2026 with date, time, platform, title, content_type, status, campaign_color

2. Update calendar/index.blade.php layout:
   - Page header: "Content Calendar" + "+ New Post" button + "Import / Export" link
   - Top bar: ← June 2026 → navigator | Today button | Month/Week/List toggle | Platform filter pills (All/Instagram/Facebook/Google/Blog/WhatsApp) | Filters button

3. Left sidebar:
   - Mini month calendar with navigation (← →)
   - Day dots (colored) on days that have content
   - "Filter by Status" section: checkboxes with count — All Status(24) / Scheduled(10) / Published(8) / Draft(3) / Pending Approval(2) / Failed(1)
   - "Content Type" section: icon + label + count for Reel(8) / Post(7) / Carousel(4) / Story(3) / Blog(2)
   - "Clear Filters" link

4. Month grid (main area):
   - 7-column grid (Sun–Sat headers)
   - Each day cell: date number, post chips stacked
   - Post chip: platform color left border, platform icon, truncated title, time
   - Warning triangle (⚠) on days 7–11 (simulate missed posts week)
   - Click on empty day: "+ Create" appears on hover
   - Today's date: highlighted circle

5. Legend bar (bottom): ● Scheduled ● Published ● Draft ● Pending Approval ● Failed ○ No Content

Do not run any artisan commands.
```

---

### P2.5-B: Calendar — Week View + List View + Post Detail Panel

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.5-B — Content Calendar UI: week view, list view, post detail panel.

Context:
- P2.5-A is complete. Month view and sidebar are done.
- Read resources/views/marketing/calendar/index.blade.php to see current structure
- UI only

Build:

1. Week view (Alpine.js x-show when "Week" tab active):
   - 7 column grid, time rows from 8AM–10PM
   - Post blocks placed at correct time rows, platform color background, title truncated
   - Column headers: day name + date
   - Current time indicator line (red) on today

2. List view (Alpine.js x-show when "List" tab active):
   - Table: Date | Time | Platform badge | Content Type badge | Title | Campaign | Status badge | Actions
   - Grouped by week (expandable sections)
   - Row hover: Edit / View / Delete icon actions appear

3. Post detail slide-out panel (right side, appears on any post chip click):
   - Platform badge + content type badge + status badge
   - Post title (h3)
   - Content preview (first 100 chars)
   - Scheduled: date + time + timezone
   - Campaign: name + color dot
   - Created by: avatar + name
   - 4 action buttons: Edit | Reschedule | Publish Now | Delete
   - Panel closes with × or clicking outside (Alpine.js)

Do not run any artisan commands.
```

---

## P2.6 — Library UI

🔴 **HIGH** (~280 lines)

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.6 — Marketing Library UI.

Context:
- Read resources/views/marketing/layouts/app.blade.php for CSS framework
- Read resources/views/marketing/library/index.blade.php (currently skeleton)
- UI only — mock data

Build:

1. Update LibraryController@index to pass mock data:
   $folders: [All Assets(1248), Uncategorized(86), Campaigns → [Smile Makeover June(86), Implant Awareness(72), Teeth Whitening(64), Patient Testimonials(58), Clinic Branding(42), Festivals & Events(38), Staff & Team(26), Educational Content(18)], Recycle Bin(12)]
   $assets: 8 mock assets with filename, type(image/video/carousel), size, date, tags[], campaign
   $selectedAsset: first asset (smile-makeover-before-after.jpg)

2. Update library/index.blade.php — 3-panel layout:

   LEFT PANEL (sidebar, ~220px):
   - Tabs: "My Library" (underline active) | "DAM Assets" (Connected badge)
   - Search bar: "Search in my library..."
   - Folder tree: expandable, each folder shows count, current folder highlighted
   - Storage Usage: progress bar (34%, 68.4 GB of 200 GB used) + "Manage Storage" link

   MAIN AREA:
   - Filter bar: Asset Type dropdown + Platform + Campaign + Tags + Date Modified + "Clear all"
   - View toggle: grid icon | list icon (right)
   - "Smile Makeover June" folder header + "86 assets" + "..." menu
   - Asset grid (3 per row):
     Each card: thumbnail image, type badge (top-left), filename, size, date, 2 tag chips, "..." on hover
     Video cards show: duration badge (0:30), play button overlay
     Carousel cards show: carousel badge

   RIGHT DETAIL PANEL (~280px, shown when asset selected):
   - "Details" | "Activity" tabs
   - Large thumbnail (top)
   - File Name (with ℹ icon), Type, Size, Dimensions, Uploaded On, Uploaded By
   - Folder, Campaign, Tags (chips + "+ Add Tag")
   - Description field
   - "⬇ Download" primary button
   - "More Actions" dropdown: Use in Post / Move to Folder / Delete

3. DAM Assets tab panel: same layout with "Connected" badge, shows 4 mock DAM assets with DAM origin label

Do not run any artisan commands.
```

---

## P2.7 — Brand Kit UI

🟡 **MEDIUM** (~200 lines)

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.7 — Brand Kit UI.

Context:
- Read resources/views/marketing/layouts/app.blade.php for CSS framework
- Read resources/views/marketing/brand-kit/index.blade.php (currently skeleton)
- UI only — mock data

Build:

1. Update BrandKitController@index to pass mock brand kit data for "Tulip Dental Clinic"

2. Update brand-kit/index.blade.php — 2-panel layout:
   LEFT: vertical section nav (sticky): Logo | Brand Colors | Typography | Clinic Info | Social Links | Default CTA | Default Hashtags | AI Settings
   RIGHT: form sections (one per nav item, scroll to each)

3. Build each section as a distinct card:

   Logo section: Upload zones for Primary Logo (light) + Primary Logo (dark) + Secondary logo. Preview on white/dark bg. Accepted formats note.

   Brand Colors section: 3 color swatches — Primary (#6C3FE8 purple) / Secondary (#F5A623 orange) / Accent (#2EC4B6 teal). Each: color circle + hex input + label. "+ Add Color" link.

   Typography section: Heading font dropdown (Inter selected) + Body font dropdown + Preview text "The quick brown fox..." in selected fonts.

   Clinic Info section: 6 fields — Clinic Name / Phone / Email / Website URL / Address (textarea) / WhatsApp Number.

   Social Links section: 3 fields — Instagram Handle (@tulipdental_clinic) / Facebook Page URL / Google Business URL.

   Default CTA section: Button text input ("Book Appointment") + URL field + Preview: purple button rendering.

   Default Hashtags section: Tag chip input — 6 default tags shown (#TulipDental #DentalCare etc.) + type-to-add.

   AI Settings section: Tone selector (4 radio cards: Friendly/Professional/Educational/Promotional with descriptions) + Brand Description textarea (300 chars, "Your brand voice used when AI generates content for you").

4. Each section: "Save" button (right-aligned) + "Last saved: X ago" small text.

Do not run any artisan commands.
```

---

## P2.8 — Integrations + Analytics + Settings UI

🟡 **MEDIUM** (~220 lines total)

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 2.8 — Integrations, Analytics (placeholder), and Settings UI.

Context:
- Read resources/views/marketing/layouts/app.blade.php for CSS framework
- UI only — mock data

Build all three pages:

--- INTEGRATIONS ---
Update integrations/index.blade.php:
- Page header: "Integrations" + subtitle "Connect your platforms to publish, track, and measure."
- Grid of platform cards (2 per row):
  Each card: platform icon (large) + platform name + 1-line description + status badge + [Connect/Disconnect button] + settings gear (if connected)
  Mock states:
  - Instagram: Connected (@tulipdental_clinic, last synced 2h ago)
  - Facebook: Connected (Tulip Dental Clinic Page)
  - Google Business: Connected (Tulip Dental Clinic, Nagpur)
  - WhatsApp Business: Connected (+91 9876543210)
  - WordPress: Not Connected
  - Google Analytics: Not Connected
- Bottom: "More coming soon — YouTube, LinkedIn" greyed-out row

--- ANALYTICS ---
Update analytics/index.blade.php:
- Page header: "Analytics" + "Soon" badge
- 6 greyed-out metric card placeholders (Reach / Impressions / Engagement / Leads / Appointments / Revenue)
- 2 large greyed-out chart placeholder boxes
- Center message: "Deep analytics are coming. You'll see reach, leads, ROI per campaign, and top performing content — all in one place."
- "Get notified when Analytics launches →" CTA link

--- SETTINGS ---
Update settings/index.blade.php:
- Left tab navigation: General | Approval Workflow | Scheduling | Notifications | Permissions | AI Defaults
- Alpine.js tab switching
- General tab: Timezone select (Asia/Kolkata default), Default post status radio, Publishing confirmation toggle
- Approval Workflow tab: Enable toggle, 3 approval role steps (Content Writer → Reviewer → Publisher), Notify on approval/rejection toggles
- Scheduling tab: Default publish times per platform (time pickers, 6 rows), Queue spacing slider (15 mins), Blackout days multi-date
- Notifications tab: Toggle list for 6 event types + channel (In-app/Email/WhatsApp) per event
- Permissions tab: Read-only role matrix table with roles as columns, abilities as rows
- AI Defaults tab: Language select, Hashtag count slider (10 default), Auto-suggest best time toggle

Do not run any artisan commands.
```

---

---

# PHASE 3 — Data Layer

---

## P3-A: Migrations (Group 1 — Core entities)

🔴 **HIGH** (~300 lines)

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 3-A — Marketing Hub migrations: core entities.

Context:
- All tables prefixed mkt_
- Every table needs: id, clinic_id (FK to clinics), created_by, updated_by, timestamps, softDeletes
- Do NOT run migrations — generate files only
- Check existing migrations with: php artisan migrate:status | head -30 (do not run this, just note the pattern)
- Do NOT touch any existing migrations

Create these 9 migration files:

1. create_mkt_settings_table — key(string), value(text), clinic_id

2. create_mkt_brand_kits_table — clinic_id, logo_primary(string nullable), logo_dark(string nullable), logo_secondary(string nullable), color_primary(string), color_secondary(string), color_accent(string), heading_font(string), body_font(string), clinic_name(string), phone(string nullable), email(string nullable), website(string nullable), address(text nullable), whatsapp(string nullable), instagram_handle(string nullable), facebook_url(string nullable), google_business_url(string nullable), default_cta_text(string nullable), default_cta_url(string nullable), default_hashtags(json nullable), ai_tone(enum: friendly/professional/educational/promotional, default: friendly), brand_description(text nullable)

3. create_mkt_campaigns_table — clinic_id, name(string), description(text nullable), objective(string nullable), treatment_category(string nullable), status(enum: draft/active/paused/completed, default: draft), owner_id(FK users), start_date(date), end_date(date), budget_planned(decimal 10,2 default 0), budget_utilized(decimal 10,2 default 0), target_audience(string nullable), channels(json nullable), campaign_color(string nullable — hex for calendar), notes(text nullable), created_by, updated_by, softDeletes

4. create_mkt_campaign_goals_table — campaign_id(FK), metric(string — leads/appointments/treatments/revenue), target_value(decimal 15,2), actual_value(decimal 15,2 default 0), unit(string nullable)

5. create_mkt_campaign_team_table — campaign_id(FK), user_id(FK), role(string), joined_at(timestamp)

6. create_mkt_ideas_table — clinic_id, campaign_id(FK nullable), title(string), description(text nullable), content_type(enum: reel/post/carousel/story/blog/offer), treatment_category(string nullable), platform(json nullable), priority(enum: low/medium/high default medium), status(enum: draft/saved/converted, default: draft), notes(text nullable), ai_prompt(text nullable), created_by, updated_by, softDeletes

7. create_mkt_idea_assets_table — idea_id(FK), file_path(string), file_type(string), sort_order(int default 0)

8. create_mkt_festival_dates_table — name(string), date(date), category(enum: dental/national/regional/religious), suggested_content_type(string nullable), country(string default 'IN'), is_recurring(boolean default true), notes(text nullable)
   — NO clinic_id (shared global table), NO softDeletes

9. create_mkt_platform_connections_table — clinic_id, platform(string — instagram/facebook/google_business/whatsapp/wordpress/google_analytics), account_name(string nullable), account_id(string nullable), access_token(text nullable — encrypted), refresh_token(text nullable — encrypted), token_expires_at(timestamp nullable), is_active(boolean default true), last_synced_at(timestamp nullable), meta(json nullable)

Do not run php artisan migrate.
```

---

## P3-B: Migrations (Group 2 — Content + Library)

🔴 **HIGH** (~280 lines)

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 3-B — Marketing Hub migrations: posts, library, activity log.

Context:
- Same conventions as P3-A: mkt_ prefix, clinic_id, created_by, updated_by, timestamps, softDeletes
- Do NOT run migrations

Create these 9 migration files:

1. create_mkt_posts_table — clinic_id, campaign_id(FK nullable), content_type(enum: reel/post/carousel/story/blog/offer), master_content(text nullable), hashtags(json nullable), cta_type(string nullable), cta_url(string nullable), status(enum: draft/pending/approved/scheduled/published/failed, default: draft), assigned_to(FK users nullable), approved_by(FK users nullable), approved_at(timestamp nullable), created_by, updated_by, softDeletes

2. create_mkt_post_variants_table — post_id(FK), platform(string), content(text nullable), platform_specific_meta(json nullable — alt text, blog title/slug/meta, etc.), external_id(string nullable — ID on the platform after publish), external_url(string nullable), status(enum: draft/scheduled/published/failed, default: draft), published_at(timestamp nullable), failed_reason(text nullable)

3. create_mkt_post_media_table — post_id(FK), file_path(string), file_type(enum: image/video/document), sort_order(int default 0), alt_text(string nullable), duration_seconds(int nullable — for video), thumbnail_path(string nullable)

4. create_mkt_post_schedules_table — post_id(FK), variant_id(FK nullable), platform(string), scheduled_at(timestamp), status(enum: pending/processing/done/failed, default: pending), job_id(string nullable), processed_at(timestamp nullable), error(text nullable)

5. create_mkt_assets_table — clinic_id, folder_id(FK nullable), asset_type(enum: image/video/brochure/template/before_after/education/graphic/reel/document), filename(string), file_path(string), file_size(bigint nullable), mime_type(string nullable), dimensions(json nullable — width/height), duration(int nullable — video seconds), dam_asset_id(string nullable — reference if from DAM), campaign_id(FK nullable), description(text nullable), uploaded_by(FK users), created_by, updated_by, softDeletes

6. create_mkt_asset_folders_table — clinic_id, parent_id(FK self nullable), name(string), created_by, updated_by, softDeletes

7. create_mkt_asset_tags_table — clinic_id, name(string), color(string nullable)

8. create_mkt_asset_tag_map_table — asset_id(FK), tag_id(FK) — pivot, no timestamps, no softDeletes

9. create_mkt_activity_log_table — clinic_id, user_id(FK), event(string), subject_type(string nullable), subject_id(bigint nullable), description(text), meta(json nullable), created_at(timestamp) — no updated_at, no softDeletes

Do not run php artisan migrate.
```

---

## P3-C: Models + Relationships + Seeders

🟡 **MEDIUM** (~220 lines)

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 3-C — Marketing Hub models, relationships, and seeders.

Context:
- All migrations from P3-A and P3-B are written (not yet run)
- Models go in app/Models/Marketing/
- Read existing models (e.g. app/Models/Patient.php) to match HasFactory, SoftDeletes, fillable pattern

Build:

1. Models (with $fillable, $casts, relationships, SoftDeletes where applicable):
   - Campaign.php — hasMany(CampaignGoal), hasMany(MarketingPost), belongsToMany(User via mkt_campaign_team), hasMany(MarketingAsset), belongsTo(User 'owner'), hasMany(MarketingActivityLog)
   - CampaignGoal.php — belongsTo(Campaign)
   - Idea.php — belongsTo(Campaign nullable), hasMany(IdeaAsset), belongsTo(User 'creator')
   - IdeaAsset.php — belongsTo(Idea)
   - MarketingPost.php — belongsTo(Campaign nullable), hasMany(PostVariant), hasMany(PostMedia), hasMany(PostSchedule), belongsTo(User 'assignee')
   - PostVariant.php — belongsTo(MarketingPost)
   - PostMedia.php — belongsTo(MarketingPost)
   - PostSchedule.php — belongsTo(MarketingPost), belongsTo(PostVariant nullable)
   - MarketingAsset.php — belongsTo(AssetFolder nullable), belongsToMany(AssetTag via mkt_asset_tag_map), belongsTo(Campaign nullable)
   - AssetFolder.php — hasMany(AssetFolder 'children', foreign_key: parent_id), belongsTo(AssetFolder 'parent' nullable), hasMany(MarketingAsset)
   - AssetTag.php — belongsToMany(MarketingAsset via mkt_asset_tag_map)
   - BrandKit.php — belongsTo(Clinic), static method: forClinic($clinicId) → firstOrCreate
   - PlatformConnection.php — belongsTo(Clinic), $encrypted = ['access_token', 'refresh_token'] using Laravel encrypt/decrypt in mutators
   - MarketingSetting.php — belongsTo(Clinic), static method: get($clinicId, $key, $default)
   - FestivalDate.php — no clinic_id, no SoftDeletes, scopeForMonth($month, $year)
   - MarketingActivityLog.php — belongsTo(User), morphTo('subject')

2. Seeders:
   - MarketingModuleSeeder: inserts 'marketing' row in modules table (name, is_active: true)
   - FestivalDateSeeder: seeds 20+ Indian festivals + dental awareness dates:
     World Oral Health Day (20 Mar, dental), Children's Dental Health Month (1 Feb, dental), Dentist's Day (6 Mar, dental), Smile Day (first Friday Oct, dental), Diwali, Holi, Navratri, Dussehra, Independence Day (15 Aug), Republic Day (26 Jan), Ganesh Chaturthi, Eid, Christmas, New Year, Valentine's Day, Mother's Day, Father's Day, International Yoga Day (21 Jun), World Health Day (7 Apr), World Environment Day (5 Jun)

Do not run php artisan migrate or db:seed.
```

---

---

# PHASE 4 — Backend Wiring

---

## P4.1 — Overview + Brainstorm Backend

🟡 **MEDIUM** (~220 lines)

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 4.1 — Wire backend: Overview Dashboard + Brainstorm.

Context:
- All migrations have been run. All models exist.
- Read resources/views/marketing/overview/index.blade.php and all its partials — UI must not change visually
- Replace hardcoded mock data with real DB queries
- Read app/Http/Controllers/Marketing/OverviewController.php before editing

Build:

1. OverviewController@index — real queries:
   - Published/Scheduled/Draft/Missed/Pending counts from mkt_posts (scoped to auth clinic)
   - Running campaigns from mkt_campaigns where status=active
   - Upcoming schedule from mkt_post_schedules joined to mkt_posts
   - Platform connections from mkt_platform_connections
   - Last 10 activity log entries from mkt_activity_log
   - Marketing Score: create app/Services/Marketing/MarketingScoreService.php — calculates score 0-100 based on: posts this month (max 30pts), active campaigns (max 20pts), connected platforms (max 20pts), completion rate (max 30pts)

2. BrainstormController@index — real queries:
   - Paginated ideas from mkt_ideas (20 per page), with filters: treatment_category, platform, status, search
   - Festival dates from mkt_festival_dates for current month
   - Pass idea count by status for filter badges

3. IdeaController — full CRUD:
   - store: validate + save to mkt_ideas + attach assets + log activity
   - update: validate + update
   - destroy: soft delete
   - convertToPost: create mkt_posts from idea data → redirect to Publish
   - convertToCampaign: create mkt_campaigns with idea as first content item → redirect to Campaign show

Read all relevant view files before touching them. Do not run artisan commands.
```

---

## P4.2 — Campaigns Backend

🟡 **MEDIUM** (~230 lines)

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 4.2 — Wire backend: Campaigns CRUD + tabs.

Context:
- Migrations run. Models exist.
- Read resources/views/marketing/campaigns/index.blade.php and show.blade.php before editing
- UI must not change visually — only mock data becomes real

Build:

1. CampaignController full implementation:
   - index: paginated campaigns with search/status/treatment filters, grouped by status for kanban
   - show: load campaign with goals, posts grouped by status (for content plan kanban), team, assets
   - store: validate + create campaign + create default goals + log activity
   - update: validate + update fields
   - destroy: soft delete (only if no published posts)
   - updateGoals: PUT /campaigns/{id}/goals — update target values
   - addTeamMember: POST /campaigns/{id}/team — attach user with role
   - removeTeamMember: DELETE /campaigns/{id}/team/{user}

2. API endpoints (AJAX, return JSON):
   - GET /api/marketing/campaigns/{id}/stats — real counts for the performance sidebar
   - GET /api/marketing/campaigns/{id}/content-plan — posts grouped by status

3. CampaignService.php:
   - completionPercentage(Campaign): calculates based on goals achieved
   - budgetUtilizationPct(Campaign): budget_utilized / budget_planned * 100

Read existing controllers before editing. Do not run artisan commands.
```

---

## P4.3 — Universal Publish + Calendar Backend

🔴 **HIGH** (~320 lines) — split if needed

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 4.3 — Wire backend: Universal Publish and Calendar.

Context:
- Migrations run. Models exist.
- Read resources/views/marketing/publish/index.blade.php and all its partials before editing
- Read resources/views/marketing/calendar/index.blade.php before editing

Build:

1. PublishController:
   - index: pass brand kit defaults (hashtags, CTA) + campaigns list
   - store: validate master post + auto-create PostVariant for each selected platform + save PostMedia + if scheduled: create PostSchedule entries + log activity
   - update(post): update master + regenerate variants (keep platform-specific edits if flagged)
   - updateVariant(post, platform): update single platform variant content
   - schedule(post): create/update PostSchedule records
   - saveDraft: store with status=draft

2. CalendarController:
   - index: posts for current month grouped by date (uses scheduled_at from mkt_post_schedules)
   - reschedule: PUT /calendar/posts/{id}/reschedule — update scheduled_at
   - export: GET /calendar/export?format=csv — generate CSV of month's schedule

3. Queue Job: app/Jobs/Marketing/ProcessScheduledPost.php
   - Runs via scheduler (every minute)
   - Queries mkt_post_schedules where scheduled_at <= now() and status=pending
   - For each: calls PublishService→dispatch(variant) (stub for now — just marks as published)
   - On success: update variant status=published, schedule status=done, log activity
   - On failure: update variant status=failed, log error, fire PostFailed event

4. PublishService.php stub:
   - dispatch(PostVariant $variant): switch on platform, call relevant Adapter (all adapters stub — just return success for now)

Read all relevant files before editing. Do not run artisan commands.
```

---

## P4.4 — Library + Brand Kit Backend

🟡 **MEDIUM** (~180 lines)

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 4.4 — Wire backend: Library and Brand Kit.

Context:
- Migrations run. Models exist.
- Read library and brand-kit view files before editing

Build:

1. LibraryController + AssetController:
   - index: assets with folder tree, filters (type/campaign/tags/search), paginated 24 per page
   - store: handle file upload → store in storage/marketing/assets/{clinic_id}/ → create mkt_assets record
   - update: update description, campaign association, folder
   - destroy: soft delete
   - move: PUT /assets/{id}/move → update folder_id
   - Folder CRUD: createFolder, renameFolder, deleteFolder (block if has assets)
   - Tag CRUD: addTag(asset), removeTag(asset)
   - storageUsage: sum file_size for clinic → format as GB

2. BrandKitController:
   - index: BrandKit::forClinic(auth()->user()->clinic_id) → firstOrCreate defaults
   - update: validate + update brand kit fields
   - uploadLogo: handle logo image upload → store in storage/marketing/logos/ → update logo field
   - Color validation: ensure hex format (#RRGGBB)

3. DamAssetService stub (app/Services/Marketing/DamAssetService.php):
   - getAssets($clinicId): check if DAM module active (via modules table) → if yes return DAM assets, else return empty collection
   - This prevents hard dependency on DAM module

Read all relevant files before editing. Do not run artisan commands.
```

---

---

# PHASE 5 — Integrations & Cross-Module Wiring

---

## P5.1 — Platform OAuth Connections

🔴 **HIGH** (~300 lines) — do one platform per session

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 5.1 — Platform integration: [PLATFORM NAME].

(Replace [PLATFORM NAME] with: Instagram | Facebook | Google Business | WhatsApp | WordPress | Google Analytics)

Context:
- PlatformConnection model exists
- IntegrationController stub exists
- Read IntegrationController and the integrations view before editing

Build for [PLATFORM NAME] only:
1. OAuth/auth flow: redirect to platform → callback → store encrypted tokens in mkt_platform_connections
2. Disconnect flow: revoke token + delete/deactivate record
3. Health check: verify token still valid, refresh if expired
4. Test connection: make a simple read API call to confirm it works
5. Update integrations view: show real connected state from DB (not mock)

Adapter in app/Modules/Marketing/Services/Adapters/[Platform]Adapter.php:
- Implements PublishAdapterInterface
- publish(PostVariant): post to platform API → store external_id → return PublishResult
- schedule(): platform-native scheduling if supported, else use our queue job
- getStatus(externalId): query platform for current status
- delete(externalId): remove post from platform

Do not run artisan commands. One platform per session.
```

---

## P5.2 — Cross-Module Wiring

🟡 **MEDIUM** (~200 lines)

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 5.2 — Cross-module wiring: Comm OS, Reports, Dashboard, Daily Huddle.

Context:
- Marketing module is functionally complete
- Communication OS module exists (check its service/event structure before building)
- Read the existing Dashboard widget system and Daily Huddle aggregator before building

Build:

1. Events + Listeners:
   app/Events/Marketing/MarketingLeadCaptured.php — carries: clinic_id, campaign_id, contact data
   app/Events/Marketing/CampaignActivated.php
   app/Events/Marketing/PostPublished.php
   app/Events/Marketing/PostFailed.php
   app/Listeners/Marketing/NotifyCommOsOfLead.php — listens to MarketingLeadCaptured → calls Comm OS service to create contact + assign to inbox
   app/Listeners/Marketing/LogMarketingActivity.php — listens to all Marketing events → writes to mkt_activity_log
   Register in EventServiceProvider

2. MarketingReportDataProvider (app/Services/Marketing/MarketingReportDataProvider.php):
   - getCampaignSummary($clinicId, $dateRange): returns campaigns data for Reports
   - getPostingActivity($clinicId, $dateRange): posts published/scheduled/missed counts
   - Register with ReportsEngine (check how existing data providers register)

3. Dashboard widgets (check existing widget registration pattern):
   - MarketingScoreWidget, CampaignHealthWidget, UpcomingPostsWidget, MissedPostsWidget, PlatformStatusWidget
   - Each: implements DashboardWidgetInterface, render($clinicId) returns view

4. Daily Huddle items (check existing huddle aggregator pattern):
   - Today's scheduled posts, Posts pending approval, Failed posts, Campaign deadlines this week

Read all relevant existing files before building. Do not run artisan commands.
```

---

---

# PHASE 6 — Intelligence Layer

---

## P6.1 — AI Content Generation

🟡 **MEDIUM** (~200 lines)

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 6.1 — AI Content Generation (Brainstorm AI Generate + Universal Publish AI Assistant).

Context:
- Check if Dentfluence already has an AI/OpenAI service configured (check config/ and existing services)
- Brand Kit model is populated for the clinic
- Read brainstorm and publish views before editing

Build:

1. app/Services/Marketing/AiContentService.php:
   - generateIdeas($clinicId, $treatment, $platform, $tone, $count=8): builds prompt from Brand Kit context → calls AI API → returns structured array of idea objects [title, description, content_type, key_points[], tags[]]
   - improveContent($content, $platform, $tone): rewrites caption for platform and tone
   - scoreContent($content, $platform): returns score 0-100 + checklist [engaging_headline, strong_message, clear_cta, optimal_length, hashtags_included]
   - suggestHashtags($content, $treatment, $count=10): returns relevant hashtags
   - getFestivalIdeas($clinicId, $month, $year): given festivals for that month → suggest dental content ideas tied to each

2. Wire AI Generate in BrainstormController:
   - POST /api/marketing/brainstorm/generate → calls AiContentService::generateIdeas → returns JSON idea cards
   - Update brainstorm view to handle async response (show loading state → render cards)

3. Wire AI Assistant in PublishController:
   - POST /api/marketing/publish/score → calls scoreContent → returns score JSON
   - POST /api/marketing/publish/improve → calls improveContent → returns improved text
   - Update publish view: Content Score populates from real API call, "Improve with AI" button calls improve endpoint

Do not run artisan commands.
```

---

## P6.2 — ROI Engine

🟡 **MEDIUM** (~180 lines)

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 6.2 — ROI Engine: Campaign → Appointments → Treatments → Revenue chain.

Context:
- Campaign model has budget_planned, budget_utilized
- Communication OS module has appointments and leads — read its models/services to understand the structure
- Clinical module has treatments and revenue — read Treatment and Billing models

Build:

1. app/Services/Marketing/RoiEngineService.php:
   - calculate(Campaign $campaign): returns ROI data object:
     budget_spent: campaign->budget_utilized
     leads_generated: count from Comm OS linked to campaign_id
     cost_per_lead: budget_spent / leads_generated
     appointments: count from Comm OS appointments linked to campaign_id
     cost_per_appointment: budget_spent / appointments
     treatments_started: count from clinical module where appointment source = this campaign
     revenue_generated: sum of invoice payments where source campaign = this campaign
     roi_pct: ((revenue - budget_spent) / budget_spent) * 100
   - Handle division by zero gracefully for all calculated fields

2. Wire ROI data into Campaign Performance tab (read _performance-tab.blade.php):
   - Replace placeholder numbers with real ROI engine output
   - CampaignController@show: call RoiEngineService::calculate($campaign) and pass to view

3. Wire ROI summary into Campaign Overview tab:
   - Update the Goals card to show actual revenue from ROI engine (not just Comm OS leads)

4. Wire ROI into Analytics page (overview level):
   - AnalyticsController@index: aggregate ROI across all campaigns for the clinic
   - Update analytics view to show real numbers instead of placeholders

Read all relevant models and views before building. Do not run artisan commands.
```

---

## P6.3 — Real Analytics

🟡 **MEDIUM** (~160 lines)

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

Task: Phase 6.3 — Real Analytics: pull platform data + render charts.

Context:
- Platform connections exist in mkt_platform_connections
- Platform adapters built in Phase 5 have getStatus() methods
- Analytics view is currently placeholder

Build:

1. app/Services/Marketing/AnalyticsService.php:
   - getReachData($clinicId, $dateRange): pulls reach/impressions from connected platform APIs
   - getEngagementData($clinicId, $dateRange): likes, comments, shares
   - getTopContent($clinicId, $limit=5): top posts by engagement from platform APIs
   - getBestTimeToPost($clinicId, $platform): analyse historical engagement time distribution

2. AnalyticsController@index:
   - Query real platform data via AnalyticsService
   - Fall back gracefully if platform not connected (show "Connect [platform] to see data")
   - Pass data to view for Chart.js rendering

3. Update analytics/index.blade.php:
   - Replace placeholder cards with real metric cards (Reach, Impressions, Engagement, Leads, Appointments, Revenue)
   - Add Chart.js bar chart: Posts published per week (last 4 weeks)
   - Add Chart.js line chart: Engagement trend (last 30 days)
   - Add top performing content table (real data)
   - Platform comparison row: mini stat per connected platform
   - "Not connected" state per platform that isn't linked

Do not run artisan commands.
```

---

---

## Quick Reference: Session Start Format

At the start of every build session, tell Claude:

```
We are building Dentfluence — a Laravel dental clinic management app at C:\laragon\www\dentfluence.

[Paste the full prompt from above]
```

---

## Truncation Summary

| Phase | Task | Risk |
|---|---|---|
| P1-A | Module Registration + Routes | 🟡 MEDIUM |
| P1-B | Layout + Skeleton Pages | 🟡 MEDIUM |
| P1-C | Blade Components | 🟡 MEDIUM |
| P2.1-A | Overview Stats + Campaigns + Schedule | 🟡 MEDIUM |
| P2.1-B | Overview Platform + Actions + Activity | 🟡 MEDIUM |
| P2.2-A | Brainstorm Tab Shell + AI Generate | 🔴 HIGH |
| P2.2-B | Brainstorm Quick Idea + Bank + Festival | 🔴 HIGH |
| P2.3-A | Campaigns Index UI | 🔴 HIGH |
| P2.3-B | Campaign Show Header + Overview Tab | 🔴 HIGH |
| P2.3-C | Campaign Show Remaining Tabs | 🔴 HIGH |
| P2.4-A | Universal Publish Master + Previews | 🔴🔴 VERY HIGH |
| P2.4-B | Universal Publish Panel + Platform Tabs | 🔴 HIGH |
| P2.5-A | Calendar Month View + Sidebar | 🔴 HIGH |
| P2.5-B | Calendar Week + List + Detail Panel | 🟡 MEDIUM |
| P2.6 | Library UI | 🔴 HIGH |
| P2.7 | Brand Kit UI | 🟡 MEDIUM |
| P2.8 | Integrations + Analytics + Settings UI | 🟡 MEDIUM |
| P3-A | Migrations Group 1 (core) | 🔴 HIGH |
| P3-B | Migrations Group 2 (content + library) | 🔴 HIGH |
| P3-C | Models + Relationships + Seeders | 🟡 MEDIUM |
| P4.1 | Overview + Brainstorm Backend | 🟡 MEDIUM |
| P4.2 | Campaigns Backend | 🟡 MEDIUM |
| P4.3 | Universal Publish + Calendar Backend | 🔴 HIGH |
| P4.4 | Library + Brand Kit Backend | 🟡 MEDIUM |
| P5.1 | Platform OAuth (per platform) | 🔴 HIGH |
| P5.2 | Cross-Module Wiring | 🟡 MEDIUM |
| P6.1 | AI Content Generation | 🟡 MEDIUM |
| P6.2 | ROI Engine | 🟡 MEDIUM |
| P6.3 | Real Analytics | 🟡 MEDIUM |
