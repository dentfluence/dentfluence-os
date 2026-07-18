# Blog Marketing Hub — Master Architecture & Delivery Plan

Prepared 2026-07-17. Grounded in a full read-only recon of the existing code (see §2).
This is the approval blueprint for turning the blog from a content-type flag on the
social-post pipeline into a standalone **Blog Marketing Hub**. No code is written from
this document until Wave 1 slices are greenlit.

Decisions locked with Sumit: dedicated Blog module (own schema); all three website tiers
as the end-state; full feature scope as the target. Sequencing below is forced by
**dependencies**, not by trimming the vision — items that literally cannot be built until
external infrastructure/data exists are flagged 🔒.

---

## 1. Core philosophy

The Blog Hub is a **content workspace first, publisher second**. Everything — writing,
SEO planning, media, AI, organization, calendar — works with **no website connected**.
Connecting a website only unlocks *publishing and sync*. The user never logs into
WordPress for routine work.

Two architectural commitments make this real:

1. **A dedicated Blog domain** (`blog_posts` + related tables), loosely coupled — not
   bolted onto `mkt_posts` (which is caption/variant-shaped for social and is exactly why
   blog "feels half-built" today).
2. **A publishing adapter layer.** Content lives above a `WebsitePublishAdapter` interface;
   the concrete adapter (Dentfluence-static / WordPress / standalone) is resolved per clinic.
   The workspace has no idea which website type it's talking to.

---

## 2. Current-state reality (what to reuse vs replace)

**Reuse (working, keep):**
- `app/Services/Marketing/WordpressPublishService.php` — the real, working WP publish engine
  (uploads media bytes, featured + inline images, hashtags→tags, "Patient Education"
  category, draft-first, returns edit URL). Wrap it in the WordPress adapter; extend it with
  **update / delete / status / slug + meta** (today it only *creates* drafts).
- `PublishController::store/saveDraft/updateVariant` routes work but are UI-orphaned — the
  new Blog controller supersedes these for blog; social keeps them.
- **Marketing DAM** (`mkt_assets`, `AssetController`, `public` disk, real public URLs) — this
  is the Blog media library. Featured/inline images reference DAM assets. `dam_asset_id` link
  columns already exist (unused) to wire this.
- **PRE lead ingestion** — `app/Services/Prm/LeadIngestService::ingest($data,$source,$by)` is
  the canonical entry for any external signal; `WebsiteLeadController` is the exact pattern to
  copy for blog-comment→lead (source forced server-side as a new `blog_comment` key).
- **Local AI** — `app/Services/Assistant/OllamaClient.php` + `config/assistant.php`
  (default `qwen2.5:7b`, cloud-vision fallback configurable) + Brand Kit AI knobs
  (`ai_tone`, `ai_focus_treatments`, `ai_brand_voice_notes`). Blog AI extends this; no new
  provider to invent.
- **Analytics surface** — `AnalyticsController` + `mkt_activity_log` + Phase-6 Insights.
  Blog metrics feed these.
- **Gating** — module group `['marketing.active','module:marketing']` in `routes/marketing.php`;
  nav is a hardcoded `$mktTabs` array in `components/marketing/subnav.blade.php` (add "Blog");
  ship behind a new feature flag `blog.hub` (default OFF), per the `case_acceptance.enabled` pattern.
- **Clinic scoping** — `ResolvesClinicId` trait (`?? 1` fallback). All new blog tables carry
  `clinic_id` and scope through it.

**Replace / build new:**
- The composer mockup (`publish/index.blade.php`, `_panel1-blog.blade.php`) — a static Alpine
  mock with fake toolbar and unbound SEO fields. The Blog Hub gets its own real editor.
- Blog SEO — **greenfield**. No slug / meta / excerpt / sitemap anywhere. All net-new.
- Versioning, comments, per-website publish ledger, GA4 data-fetch — none exist.

---

## 3. Target data model (dedicated Blog module)

All tables `clinic_id`-scoped, `created_by`/`updated_by`, softDeletes where noted.

| Table | Purpose | Key columns |
|---|---|---|
| `blog_posts` | The blog entity | `clinic_id`, `title`, `slug` (unique per clinic), `excerpt`, `body_html`, `body_json` (editor source-of-truth), `featured_asset_id`→`mkt_assets`, `category_id`→`blog_categories`, `status` (draft/scheduled/published/archived), `author_id`, `scheduled_at`, `published_at`, `reading_time`, softDeletes |
| `blog_post_seo` (1:1) | SEO workspace | `blog_post_id`, `focus_keyword`, `secondary_keywords` (json), `meta_title`, `meta_description`, `canonical_url`, `og_title`, `og_description`, `og_image_asset_id`, `seo_score`, `readability_score`, `noindex` |
| `blog_categories` | Taxonomy | `clinic_id`, `name`, `slug`, `wp_term_id` (nullable, for sync) |
| `blog_tags` + `blog_post_tag` | Tags (m:n) | `name`, `slug`, `wp_term_id`; pivot |
| `blog_post_versions` | History + autosave + restore | `blog_post_id`, `snapshot` (json of body+seo+meta), `editor_id`, `label` (autosave/manual), `created_at` |
| `blog_publications` | **Per-website publish ledger** (drives status/update/delete/retry) | `blog_post_id`, `target_type` (dentfluence_static/wordpress/standalone), `platform_connection_id`, `external_id`, `external_url`, `status` (pending/publishing/published/failed/deleted), `last_synced_at`, `error`, `retry_count` |
| `blog_comments` | Synced website comments | `blog_post_id`, `external_comment_id`, `author_name`, `author_email`, `body`, `status` (pending/approved/spam/deleted), `is_lead_candidate`, `lead_id` (nullable→PRE), `synced_at` |
| `blog_metrics` (Wave 3) | Daily metrics per post | `blog_post_id`, `date`, `views`, `organic`, `engagement`, `conversions`; source=GA4/Search Console |

Media: **no new media table** — reuse `mkt_assets` (DAM) for featured/inline; `featured_asset_id`
and inline references point at it.

---

## 4. Publishing adapter layer

```
interface WebsitePublishAdapter {
    publish(BlogPost): PublicationResult          // create
    update(BlogPost, BlogPublication): Result      // sync edits
    delete(BlogPublication): Result                // remove from site
    status(BlogPublication): Result                // health/verify
    fetchComments(BlogPublication): array          // Wave 3
}
```

Concrete adapters, resolved per clinic from the connected website type:
- **`WordPressAdapter`** — wraps the existing `WordpressPublishService`; adds update (PUT
  `/posts/{id}`), delete, status, and slug/meta/excerpt fields. Ships Wave 1.
- **`StandaloneAdapter`** — no live site; `publish` is a no-op that records a `standalone`
  publication (content stays in Dentfluence; manual export). Ships Wave 1 (this is the
  tier-3 experience and makes the Hub valuable with no site).
- **`DentfluenceStaticAdapter`** — 🔒 talks to the future static-site publishing API. Interface
  ships Wave 1 as a stub; real implementation is Wave 4 (blocked, see §6).

Every publish/update/delete writes a `blog_publications` row so the UI can show status and
offer **retry**. Scheduling reuses the existing queue-job pattern (`ProcessScheduledPost`
analogue → `ProcessBlogPublication`).

---

## 5. Feature → integration map

- **Editor** — **block-based** (Notion/Gutenberg style), NOT a traditional WYSIWYG. The
  canonical content is a **block-JSON schema** stored in `blog_posts.body_json` (source of
  truth); `body_html` is a *generated* cache produced by a `BlogBlockRenderer` at publish time.
  Content is coupled to neither HTML nor the editor. **TipTap** is the editing surface (reads/
  writes the block schema via custom nodes). Autosave → `blog_post_versions`. Media from DAM.
  - **Block schema**: `body_json = { version, blocks: [ { id, type, data } ] }`. Types are
    open-ended — new types add a renderer + editor node, **no migration**.
  - **V1 block types (only these):** heading, paragraph, image, quote, table, cta, faq, divider.
  - **Future types** (add later, no data-model change): gallery, video, button, before/after,
    treatment card, doctor profile, testimonial, related blogs, custom HTML.
  - Rationale (Sumit): this block engine is the reusable content substrate for websites,
    landing pages, newsletters, patient education, social, and AI-assisted publishing — not
    just the blog. Keep it editor-agnostic and render-target-agnostic.
- **SEO panel**: fields on `blog_post_seo`; Google + OG previews are client-side; scores are
  Wave 2 heuristics; AI SEO suggestions Wave 2 (Ollama).
- **History / CMS list**: filter by status, search, edit/duplicate/archive/delete, restore
  version — all from `blog_posts` + `blog_post_versions`.
- **Calendar**: draft/scheduled/published from `blog_posts.status`+`scheduled_at`; drag-drop
  reschedule Wave 2.
- **Comments → PRE** (Wave 3): `fetchComments` syncs `blog_comments`; a comment flagged
  `is_lead_candidate` exposes "Create lead" → `LeadIngestService::ingest($data,'blog_comment','Blog')`
  → auto-triaged by `LeadObserver`. This is the differentiator.
- **Analytics** (Wave 3): `blog_metrics` fed by a new GA4 Data API + Search Console fetch
  (🔒 needs the parked Google connection live); surfaced in `AnalyticsController`.
- **AI** (Wave 2): ideas, rewrite, FAQ-gen, meta-gen, blog→social captions via `OllamaClient`,
  seeded by Brand Kit voice.
- **Multi-channel distribution** (Wave 4): "turn this blog into social posts" reuses the
  existing social publish pipeline.

---

## 6. Delivery waves (dependency-ordered)

**Wave 1 — The Hub that ships value now (WordPress + standalone tiers).**
Dedicated schema (`blog_posts`, `blog_post_seo`, categories/tags, versions, publications);
Blog controller + `marketing.blog.*` routes + nav tab + `blog.hub` flag; real block editor
with media/links/CTA/FAQ + autosave + versions; manual SEO panel with Google/OG previews;
History list (filter/search/duplicate/archive/delete/restore); Calendar; WordPressAdapter
(publish/update/delete/schedule/status/retry) built on the existing service; StandaloneAdapter.
→ A complete, saleable Blog Marketing Hub for every subscriber today.

**Wave 2 — Intelligence.** SEO score + readability, internal-link suggestions, AI throughout
(Ollama — already available, not blocked), drag-drop calendar, richer version diffing.

**Wave 3 — Engagement & measurement.** Comment sync + management + **Comment→PRE lead** hook;
Analytics via GA4 Data API + Search Console (🔒 depends on the Google connection, currently
parked pending Dentfluence business registration + approvals).

**Wave 4 — Premium & reach.** 🔒 Dentfluence-hosted seamless publishing — **blocked**: the
hand-coded static clinic sites (e.g. tulipdental.in) have no CMS/API; this needs a
Growth-Division static-publishing backend (headless CMS + regeneration) built first. Keyword
ranking — 🔒 needs a paid rank-tracking API (Ahrefs/SEMrush/DataForSEO) + a cost decision;
best sold as a premium add-on. Blog→multi-channel social distribution.

---

## 7. Open decisions / dependencies before their waves

1. **Editor stack** (Wave 1): ✅ RESOLVED — **TipTap** as the editing surface over a canonical
   block-JSON schema (see §5). Content stays editor-agnostic; HTML generated at publish.
2. **Google connection** (Wave 3 analytics + Search Console): still parked. Analytics can't
   pull real numbers until it's live.
3. **Dentfluence-hosted publishing infra** (Wave 4): a separate Growth-Division project. Needs
   its own decision — headless CMS vs static-site-generator-with-API. Until it exists, tier-1
   "seamless" is a stub.
4. **Keyword-ranking data source + budget** (Wave 4): pay-per-keyword API. Decide if/when.
5. **CLINIC_ID `?? 1` fallback**: fine single-clinic; must be hardened before a 2nd clinic
   uses the Hub.

---

## 8. Wave 1 slice plan (for approval — build order)

1. **Schema + models** — migrations for all Wave 1 tables + Eloquent models/relations +
   `blog.hub` flag + nav tab (behind flag). No UI yet. (additive migrations only)
2. **Blog CRUD backend** — `BlogController` + `marketing.blog.*` routes: list/create/edit/
   store/update/draft/duplicate/archive/delete + autosave + version write. Form Requests.
3. **Editor UI** — block editor page bound to the backend (title, body, featured/inline media
   from DAM, categories, tags, CTA/FAQ blocks, autosave indicator).
4. **SEO panel** — `blog_post_seo` fields + live Google/OG previews (client-side).
5. **History + Calendar** — CMS list (filters/search/actions/restore) + calendar view.
6. **Publishing** — `WebsitePublishAdapter` + `WordPressAdapter` (extend service: update/
   delete/status/slug/meta) + `StandaloneAdapter` + `blog_publications` ledger + schedule job
   + status/retry UI.

Each slice ships and is usable before the next. No destructive DB actions; artisan commands
listed for manual run at each slice. All untested until Sumit migrates + does one real draft.
