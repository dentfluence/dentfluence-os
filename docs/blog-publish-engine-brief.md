# Blog (WordPress) Publish Engine — Implementation Brief

Prepared 2026-07-17. Authoritative spec for making Dentfluence's Marketing publish
engine work for the clinic blog. Decisions below are locked. Build against this.

---

## 1. Objective

Make the Marketing publish engine reliably turn a composed post into a **well-formed
WordPress blog post** on the clinic site (`https://tulipdental.in/blog`) — with images,
formatting, and tags — created as a **Draft for human review**, and make the whole
publish flow report **honest results** (never a fake "published").

This is the only channel we're making *work* right now. Meta and Google live publishing
stay parked (blocked on business registration + approvals). WhatsApp broadcast is out of
scope.

---

## 2. What's broken today (verified in code)

Primary file: `app/Jobs/Marketing/ProcessScheduledPost.php`

1. **`publishToWordpress()` drops everything but text.** It POSTs only `title` +
   plain-text `content` with `status: publish`. No media upload, no featured image, no
   HTML formatting, no tags/category. Photos in the composer are silently discarded.
2. **Silent fake "published."** `dispatchToPlatform()` returns `success: true` when a
   platform has **no connection** (~lines 164-168). So a "Publish All" marks Instagram,
   Facebook, and Google Business as *published* even though nothing was sent. The UI lies.
3. **Publishes live, not draft.** `status: publish` pushes straight to the public blog.
4. **Content is the caption**, i.e. a social blurb with hashtags/emoji — not a blog body.
5. **No per-channel targeting.** The composer's "Publish All" fans out to all 6 platforms;
   there's no "publish to blog only."
6. **Two code paths exist** (legacy inline in `ProcessScheduledPost::publishToWordpress()`
   and the connector `app/Integration/Connectors/WebsiteConnector.php::publishWordpress()`
   behind the `integration.website` flag). The flag is currently OFF, so the legacy inline
   path runs. Both must end up consistent — prefer extracting the real logic into one
   shared service so there's a single source of truth.

Fable 5: verify these and surface anything else (this list is a starting map, not exhaustive).

---

## 3. Required working behavior (locked decisions)

**Status → Draft first.**
The resulting WordPress post is always created with `status: draft`, regardless of the
composer's Publish-Now/Schedule choice. The composer schedule still controls *when
Dentfluence pushes the post*; what lands in WordPress is a draft the user reviews and
publishes from WP. Return the WP **edit-post URL** so the user can jump straight to review.

**Images → featured + inline.**
- Upload **every** image attached to the post into the WP media library
  (`POST {site}/wp-json/wp/v2/media`, binary body + `Content-Disposition` filename,
  basic-auth app password).
- Set the **first** uploaded image as the post's **featured image** (`featured_media`).
- Embed **all** uploaded images inline in the post body (WP image block / `<img>` using the
  returned `source_url`).
- Source bytes come from Dentfluence's own storage (`Storage::disk('public')`). Read the
  file server-side and upload the **bytes** to WP — do not rely on WordPress fetching our
  URL. Images only (skip video in v1).

**Taxonomy → hashtags become tags.**
- Map each composer hashtag to a WordPress **tag** (look up by name, create via
  `POST /wp-json/wp/v2/tags` if missing, collect the tag IDs, pass as `tags: [...]`).
- Assign a default **category** "Patient Education" (look up / create via
  `/wp-json/wp/v2/categories`, pass as `categories: [...]`).

**Content → real HTML.**
- Convert the caption to HTML: line breaks → paragraphs. Strip hashtags from the body
  (they live in tags now). Append the CTA (label + link from the composer) as a trailing
  link/button paragraph.
- **Title**: use the post title if set, else the first sentence/line, else a sensible
  fallback — never dump the whole caption as the title.

**Honesty fix (all channels).**
- No connection for a platform → return `success: false` with a clear "not connected —
  nothing sent" reason. Represent it as `skipped`/`failed` with the reason on the variant,
  **not** as `published`. The calendar/UI must not show unsent posts as published.
- Token expired → fail with the existing reconnect message.

**Per-channel targeting.**
- The composer must let the user choose which channels to publish to, and the engine must
  only attempt channels that are (a) selected and (b) actually connected. Minimum bar: only
  attempt channels with a `connected` `PlatformConnection`; everything else is shown as
  "not connected — skipped," never published.

---

## 4. Expected result (acceptance criteria)

Compose a post with a title, body text, 1–3 images, a few hashtags, a CTA, targeting Blog:

1. A **Draft** post appears in `tulipdental.in/blog/wp-admin` with: correct title; HTML body
   with proper paragraphs; **featured image set**; all images uploaded to the media library
   and embedded inline; **tags = the hashtags**; **category = Patient Education**.
2. Dentfluence shows the blog variant as **"published (draft)"** with a working **WP edit
   link**; the activity log records it; **no other channel is falsely marked published.**
3. If WordPress is not connected or the credential is bad, the blog variant shows a **clear
   error**, not success.
4. Selecting **Blog only** sends nothing to Instagram / Facebook / Google / WhatsApp.
5. No destructive DB actions. If a column is needed (e.g. to store the WP edit URL /
   draft state), add an **additive** migration only.

---

## 5. Constraints & conventions

- Laravel conventions, production-quality, least churn. Prefer a thin job calling a new
  `WordpressPublishService` (media upload + tag/category resolution + HTML build) so logic
  is testable and shared between the legacy and connector paths.
- **Read before writing.** Do not assume model/field names — confirm `MarketingPost::media()`,
  `PostVariant`, `PlatformConnection.meta` (`site_url`, `username`) and `access_token`.
- **Do not run artisan or any destructive command.** List the exact commands (migrate, etc.)
  at the end for the user to run manually.
- Everything ships **untested** until the user migrates + does one real draft publish; call
  out required env/commands and what to verify.

## 6. Files likely involved

- `app/Jobs/Marketing/ProcessScheduledPost.php` — `publishToWordpress()`, the no-connection
  branch in `dispatchToPlatform()`.
- `app/Integration/Connectors/WebsiteConnector.php` — `publishWordpress()`.
- `app/Http/Controllers/Marketing/PublishController.php` — per-channel targeting + validation.
- `resources/views/marketing/publish*` (composer) — channel selection UI.
- `app/Models/Marketing/{MarketingPost,PostVariant,PlatformConnection}.php`.
- New: `app/Services/Marketing/WordpressPublishService.php`.
- Additive migration if storing the WP edit URL / result metadata.
