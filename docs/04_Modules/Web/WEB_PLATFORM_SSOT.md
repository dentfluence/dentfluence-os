# Dentfluence Web Platform — Single Source of Truth

**Scope:** the Website Client Portal and the Website Command Centre, built as `app/Modules/Web/` inside the existing Dentfluence Laravel application.

**Status:** V1 architecture locked. No Laravel code written yet.
**Owner:** Dr. Sumit Firke (solo build)
**Last updated:** 2026-09-12

---

## 0. How to use this file

This file is the single source of truth for the Web Platform. It outranks chat history, older docs, and anything in `docs/archive/`.

Precedence when two sources disagree:

1. This file
2. The actual code in `app/Modules/Web/`
3. Everything else

If the code and this file disagree, one of them is a bug — decide which, fix it, and update this file in the same session. Never leave the two in disagreement.

Anything under **§11 Locked decisions** is closed. Do not re-litigate it without a written reason recorded in §12.

---

## 1. Vision

Every dental clinic in India runs its web presence through Dentfluence — and the clinic can see exactly what is happening with it, at any moment, without having to ask anyone.

The website stops being a file someone else controls. It becomes an asset the dentist can watch, approve, and trust.

---

## 2. Mission

Two jobs, one application.

**For the dentist:** one honest place that answers *"what is happening with my website?"* — status, what changed, what is live, what needs their approval, where their documents and invoices are.

> **Client-side core principle:** *"Here is everything happening with your website. You only need to act when we need you."*

**For Dentfluence:** one console to run every clinic website that exists — build, version, approve, deploy, verify, monitor — without opening an FTP client or remembering which folder holds which client.

> **System-wide core principle:** *One client. One portal. One source of truth.*

---

## 3. Strategy — why this gets built at all

This is not a side feature. It does five specific jobs for the business.

### 3.1 It converts Department 2 from project revenue to retained revenue

The Marketing Agency sells a website once. A website with a live portal — version history, visible changes, an approval trail, uptime reporting — is a *maintained relationship*, and a maintained relationship carries a monthly retainer. The portal is not sold separately and has no price of its own. It is what makes the retainer defensible when the client asks what they are paying for every month.

### 3.2 It is the on-ramp to Clinic OS

A dentist who logs into a Dentfluence portal every month is already inside the product. Same brand, same login shell, same server, same India-hosted database. Moving that dentist onto Clinic OS (₹12–15K/month) is a `hq_subscriptions` row and a permission grant — not a migration, not a new sale to a cold contact.

Department flywheel position: **Marketing Agency attracts → Digital Products build trust → Clinic OS retains.** This platform is the bridge between the first and the third.

### 3.3 It removes the founder as the bottleneck

Today every client change arrives on WhatsApp and lives in Sumit's head. `web_requests` turns that into a typed, assigned, statused queue with an internal/client notes split. That is the precondition for a first hire doing website work without the founder reading every message.

### 3.4 It is the delivery discipline that justifies premium pricing

Version history, insert-only approvals, deployment verification, health checks. An agency charging premium has this. A freelancer does not. This is the visible difference, and it is what the positioning — *built by a dentist* — has to be backed by.

### 3.5 It scales without headcount

Ten sites and a hundred sites use the same console. Nothing in the design requires one more person per client.

### 3.6 Sequencing logic

Schema → renderer → deployer → staff console → client portal → automation.

The data shape comes first and must be right first, because `web_approvals` and the audit rows are **insert-only**. An approval trail cannot be cleanly migrated after the fact — the moment a client has approved something, that row is evidence and it is frozen. Get the shape wrong and the only honest fix is to throw the trail away.

The UI comes last because the UI is the cheapest thing to change and the least dangerous thing to get wrong.

---

## 4. What this is NOT

- **Not the Growth Portal.** Growth is a separate future product. *Never use "Growth" as the product identity here.* The word survives only in the legacy hostname `growth.dentfluence.in`.
- **Not a patient management system.** No clinical records, no medical history, no X-rays, no prescriptions, no dental charts, no patient billing. **This wall is architectural and permanent** — see §6.3.
- Not a CRM, not accounting software, not a marketing dashboard, not a page builder.

---

## 5. The two products

| | Command Centre | Client Portal |
|---|---|---|
| Audience | Dentfluence staff | Dentist clients |
| Host | `os.dentfluence.in` | `growth.dentfluence.in` (legacy name) |
| Path | `/web` | `/portal` |
| Guard | `web` | `portal` |
| User model | `App\Models\User` | `App\Modules\Web\Models\ClientUser` |
| Gate | `module:web,<action>` | clinic ownership via `SiteScope` |
| Nature | read/write everything | read-mostly; writes are requests and approvals |

Both read and write the same tables. **There is no sync, no second store, no separate application.**

---

## 6. V1 Architecture

### 6.1 Module layout

Created 2026-09-12. New module at `app/Modules/Web/`, copying the shape of `app/Modules/Hq/` — the structural precedent in this codebase.

```
app/Modules/Web/
├── README.md                      ← structure contract; read before adding files
├── Controllers/
│   ├── CommandCentre/             ← staff, `web` guard, /web
│   └── Portal/                    ← dentists, `portal` guard, /portal
├── Enums/                         ← every enum column as a PHP 8.3 backed enum
├── Jobs/
├── Middleware/
├── Models/                        ← flat, the nine models
├── Policies/
├── Routes/                        ← web.php, portal.php
├── Scopes/                        ← SiteScope
└── Services/                      ← build, render, deploy, verify, health
```

Namespace: `App\Modules\Web\Controllers\CommandCentre\...`, `App\Modules\Web\Models\...`
Note the Hq convention: controllers sit directly under `Controllers/`, **not** `Http/Controllers/`.

**Why Controllers splits by guard:** the guard boundary becomes visible in the filesystem. A file under `Controllers/Portal/` runs as a dentist, so a reference to `User`, `Role`, a clinical model or an unscoped query is a bug visible in the diff without reading the logic. Never place a shared controller above the two folders.

**Why `Enums/` exists:** the nine tables carry fourteen enum columns. Bare string literals scattered across controllers is how this kind of schema rots. No enum value is ever written as a string outside this folder.

### 6.1a View locations

Views are **not** in the module folder — Hq's views live in `resources/views/hq`, and this follows that.

```
resources/views/web/           ← Command Centre UI (staff)
│   layouts · components · dashboard · sites · pages · deployments · requests · documents
resources/views/portal/        ← Client Portal UI (dentists)
│   layouts · components · dashboard · approvals · requests · documents
resources/views/client-sites/  ← rendered into static HTML for client websites
    layouts · blocks · pages   ← README.md in this folder states its rules
```

`client-sites/` is **not** app UI and must never share a layout, component or stylesheet with the other two. A change to the Dentfluence app's look must not be able to alter a live client site; disjoint trees are what guarantee that.

Migrations go in the normal `database/migrations/`, flat — Laravel convention, not module-local.
Tests: `tests/Feature/Web/`, `tests/Unit/Web/`.

### 6.1b Naming rule — `SiteRequest`, never `Request`

The change-request model is **`SiteRequest`**. `App\Modules\Web\Models\Request` would collide with `Illuminate\Http\Request` in every controller importing both. The table stays `web_requests`; set `$table` explicitly on the model.

### 6.2 Route registration — exact pattern

Registered in `bootstrap/app.php` inside the `then:` closure, alongside the existing Hq line:

```php
Route::middleware('web')->group(base_path('app/Modules/Web/Routes/web.php'));    // Command Centre — auth + module gate inside
Route::middleware('web')->group(base_path('app/Modules/Web/Routes/portal.php')); // Client Portal — portal guard inside
```

The `web` middleware group is applied by the registration, so the route file itself adds only auth and the gate — exactly as `app/Modules/Hq/Routes/hq.php` does:

```php
Route::prefix('web')->name('web.')->middleware(['auth', 'module:web,view'])->group(function () { ... });
Route::prefix('portal')->name('portal.')->middleware(['auth:portal'])->group(function () { ... });
```

### 6.3 Two guards, one application

```
os.dentfluence.in      → guard: web     → User        → staff    → Command Centre at /web
growth.dentfluence.in  → guard: portal  → ClientUser  → dentists → Client Portal at /portal
```

Same codebase, same database, same server, same deploy.

**The clinical wall, stated precisely:**

`ClientUser` carries **no relations to `Patient`, `Consultation`, `TreatmentPlan`, or any clinical model.** A client login has no *path* to clinical data — not merely no permission. There is nothing to forget to check, because the association does not exist.

Consequences that are part of the contract:

- Client users are **never** rows in `users`.
- Client users **never** receive a `Role` row and are never subject to `RoleModulePermission`.
- Portal authorisation is clinic ownership, resolved by `SiteScope`. It is not the module-permission system.
- `config/auth.php` gains a `portal` guard and a `portal_users` provider pointing at `ClientUser`.

### 6.4 SiteScope — must fail CLOSED

`app/Models/Scopes/BranchScope.php` **fails open** in three cases (no auth, admin role, no `branch_id` → returns without scoping). It is documented as deliberately inert. **Do not copy it.** It is the wrong pattern for this module and copying it is the most likely way client A ends up seeing client B's site.

`App\Modules\Web\Scopes\SiteScope` contract:

- Applies to models owned by a site, when the request is on the **`portal` guard**.
- Resolves the allowed site ids from the authenticated `ClientUser` → `hq_clinic_id` → that clinic's `web_sites`.
- If the resolution yields **nothing** — no authenticated portal user, no clinic, no sites, any unexpected state — the scope applies `whereRaw('1 = 0')` and writes `Log::error()` with the attempted model and resolved context.
- **There is no code path in which an unresolved site produces an unscoped query.**

Staff queries are *not* governed by `SiteScope`. Staff see across sites by design, gated by `module:web,<action>`, with `web_site_id` always explicit in the route.

**V1 granularity:** portal access is **clinic-level**. A client user sees every site belonging to their clinic (so Tulip's owner sees both `tulipdental.in` and `tulipkidsdental.com`). Per-site restriction inside one clinic is not in V1. No `client_user ↔ site` pivot table exists, deliberately.

### 6.5 Insert-only tables

`web_approvals` and the audit rows are **insert-only**.

No update path. No soft delete. No admin override. **Including for Super Admin.**

This means: no `updated_at` column on `web_approvals`, no `SoftDeletes` trait, no `update()` or `delete()` method reachable from any controller, job, or console command. Every approval row stores a `content_hash` of exactly what was approved, plus IP and user agent. A correction is a **new row**, never an edit.

The reason is evidentiary, not tidiness: the approval trail is what Dentfluence stands on if a client later says "I never approved that."

### 6.6 Table prefix

`web_`. The old `growth_` prefix is dropped entirely.

### 6.7 The nine new tables

Nothing equivalent exists in the ~180 existing models for any of these. Columns below are the V1 shape.

#### `web_sites` — the website master record
`id` · `hq_clinic_id` FK · `name` · `primary_domain` unique · `additional_domains` json · `status` enum(lead, onboarding, in_build, review, live, maintenance, suspended, archived) · `current_version_id` FK nullable · `hq_plan_id` FK nullable · `assigned_staff_id` FK users nullable · `hosting_provider` default `hostinger` · `sftp_host` · `sftp_port` · `sftp_username` · `sftp_password` encrypted · `sftp_root_path` · `ga4_measurement_id` nullable · `gsc_property` nullable · `gbp_place_id` nullable · `ssl_expires_at` nullable · `domain_expires_at` nullable · `launched_at` nullable · `internal_notes` · timestamps · softDeletes

#### `web_pages` — structured page content for the data-driven renderer
`id` · `web_site_id` FK · `parent_id` self FK nullable · `slug` · `path` · `title` · `page_type` enum(home, treatment, about, contact, team, gallery, testimonial, blog_index, policy, custom) · `template` · `content` json · `seo_title` · `seo_description` · `canonical_url` nullable · `og_image_path` nullable · `schema_json` json nullable · `is_indexable` bool default true · `status` enum(draft, in_review, approved, published) · `sort_order` · `published_version_id` FK nullable · `last_edited_by` FK users nullable · timestamps · softDeletes
**unique(`web_site_id`, `path`)**

#### `web_versions` — business-level release history
`id` · `web_site_id` FK · `version_number` · `label` · `changelog` text · `page_snapshot` json · `created_by` FK users · `released_at` nullable · `is_current` bool · timestamps
**unique(`web_site_id`, `version_number`)**

#### `web_deployments` — deployment runs and verification results
`id` · `web_site_id` FK · `web_version_id` FK · `type` enum(deploy, rollback) · `status` enum(queued, building, uploading, switching, verifying, live, failed) · `initiated_by` FK users nullable · `deploy_directory` · `rollback_directory` nullable · `started_at` · `finished_at` nullable · `http_verification` json nullable · `health_verification` json nullable · `error_message` text nullable · `log` longText nullable · timestamps

#### `web_health_checks` — monitoring results
`id` · `web_site_id` FK · `check_type` enum(uptime, ssl, response_time, broken_links, ga4_tag, sitemap, mixed_content) · `status` enum(pass, warn, fail) · `http_status` nullable · `response_time_ms` nullable · `detail` json nullable · `checked_at` · timestamps
**index(`web_site_id`, `checked_at`)**

#### `web_requests` — client change requests
`id` · `web_site_id` FK · `web_client_user_id` FK nullable *(null when staff raise it on the client's behalf)* · `reference` unique *(WR-2026-0001)* · `type` enum(content_edit, new_page, image_change, contact_update, treatment_add, seo, bug, other) · `priority` enum(low, normal, high, urgent) · `title` · `description` · `client_notes` text nullable *(visible in portal)* · `internal_notes` text nullable *(**never** exposed to the portal — enforced in the model, not the view)* · `status` enum(submitted, acknowledged, in_progress, awaiting_client, completed, declined) · `assigned_to` FK users nullable · `attachments` json nullable · `submitted_at` · `acknowledged_at` nullable · `completed_at` nullable · timestamps

#### `web_approvals` — polymorphic, INSERT-ONLY
`id` · `web_site_id` FK · `approvable_type` · `approvable_id` · `decision` enum(approved, rejected, changes_requested) · `approved_by_client_user_id` FK nullable · `approved_by_user_id` FK users nullable *(staff acting with recorded authority)* · `comment` text nullable · `content_hash` · `ip_address` · `user_agent` · **`created_at` only**
**index(`approvable_type`, `approvable_id`)** · no `updated_at` · no softDeletes

#### `web_documents` — versioned vault
`id` · `web_site_id` FK · `category` enum(contract, invoice, brand_asset, content_source, credential_handover, seo_report, other) · `title` · `description` nullable · `file_path` · `file_name` · `mime_type` · `file_size` · `version` int default 1 · `supersedes_id` self FK nullable · `visibility` enum(client, internal) · `uploaded_by` FK users nullable · `uploaded_by_client_user_id` FK nullable · timestamps · softDeletes

#### `web_client_users` — portal logins, separate guard
`id` · `hq_clinic_id` FK · `name` · `email` unique · `phone` nullable · `password` · `portal_role` enum(owner, manager, viewer) *(portal-local; **not** a `Role` row)* · `is_active` bool · `must_change_password` bool default true · `invited_at` nullable · `invitation_token` nullable · `invitation_accepted_at` nullable · `two_factor_secret` nullable · `last_login_at` nullable · `last_login_ip` nullable · `remember_token` · timestamps · softDeletes

### 6.8 Two columns on existing tables

- `hq_clinics.assigned_staff_id` — FK `users`, nullable
- `analytics_snapshots.web_site_id` — FK `web_sites`, nullable

**Additive only. Nothing existing is altered or dropped.**

### 6.9 Deployment pipeline

```
build → create version → upload to .deploy-<version>/ → switch directories keeping .rollback-<n>
      → HTTP verify → health verify → live
```

**A failed verification never marks the site live.** On failure: rename the rollback directory back, mark the deployment `failed` with the error, leave the previous version serving, alert staff.

**Rollback runs the same pipeline with an older version as input — never a separate code path.** One pipeline, one set of bugs.

Deployments run as **queued jobs**, never inside an HTTP request.

### 6.10 Hosting

| What | Where | Why |
|---|---|---|
| Laravel app (OS + Command Centre + Portal) | DigitalOcean **Bangalore** droplet + Laravel Forge | India region is mandatory — the app holds patient data (DPDP Act) |
| Client websites | their own Hostinger plans, their own domains | one outage cannot take down every client at once; handover stays clean |

The app deploys to client hosting over SFTP. **Never move client sites onto the app server.**

---

## 7. Reuse map — these exist, never rebuild them

**Before creating any model, check whether an equivalent exists. Duplicating one of these is the main technical risk in this project.**

| Need | Use |
|---|---|
| Clients, statuses, contacts | `Hq\Models\Clinic` |
| Plans and subscriptions | `Hq\Models\Plan`, `Hq\Models\Subscription` |
| Feature gating by paid plan | `Clinic::hasPass()` / `Clinic::passes()` — derived from live subscriptions, never stored |
| Vendor support tickets | `Hq\Models\Ticket` |
| Branches, timings, holidays | `Branch`, `ClinicHour`, `ClinicHoliday`, `Operatory` |
| Blog posts, SEO, versions, publishing | `Blog\BlogPost`, `BlogPostSeo`, `BlogPostVersion`, `BlogPublication`, `BlogCategory`, `BlogTag` |
| Media and assets | `Marketing\MarketingAsset`, `AssetFolder`, `AssetTag`, `BrandKit`, `MediaAsset`, `CmsMedia` |
| Invoices, payments, receipts | `Invoice`, `InvoiceItem`, `InvoicePayment`, `Receipt` |
| Audit trail | `AuditLog`, `StaffActivityLog` |
| Roles and permissions | `Role`, `RoleModulePermission`, `Module` — **seed rows only, no new code** |
| Doctors, qualifications | `User`, `PractitionerQualification`, `PractitionerIdentifier` |
| Treatments | `Treatment`, `TreatmentCategory`, `TreatmentType` |
| Analytics storage | `AnalyticsSnapshot` — extend with `web_site_id` |
| Third-party account links | `Marketing\PlatformConnection` |

---

## 8. Codebase conventions

### 8.1 Stack as it actually is

Laravel **13.7** · PHP **8.3** · MySQL 8 · Sanctum 4.3 · **Blade** · Vite · Pint · PHPUnit 12 · Dusk + Playwright for e2e

Also present and usable: `phpoffice/phpspreadsheet` (exports), `pragmarx/google2fa-qrcode` + `bacon/bacon-qr-code` (2FA — reuse for portal logins).

### 8.2 Hard nos

**No Filament. No Livewire. No Inertia. No React.** Do not introduce any of them.

### 8.3 Existing middleware aliases (from `bootstrap/app.php`)

`module` → `CheckModulePermission` · `superadmin` → `Hq\Middleware\EnsureIsSuperadmin` (`is_superadmin` flag) · `admin.only` · `api.role` · `communication.access` · `marketing.active`

The Command Centre uses `module:web,<action>`. It does **not** use `superadmin` — staff who are not superadmins must be able to run websites.

### 8.4 API response envelope

Already enforced globally in `bootstrap/app.php`: `{ success, message, errors }`. Any `api/*` route added here inherits it. Do not invent a second envelope.

### 8.5 Commands after file changes

- `composer dump-autoload` after **every** new PHP file
- `php artisan migrate` after new migrations
- `php artisan view:clear` / `config:clear` after Blade or config changes
- One file per session where practical

### 8.6 Dependencies to add when their step arrives

- `league/flysystem-sftp-v3` — SFTP deploys (step 3)
- `predis/predis` — Redis queue driver (step 3)

Nothing else. Any new package needs a reason recorded in §12.

### 8.7 Local vs server reality

The Laragon path `C:\laragon\www\dentfluence` does not mount in Cowork sessions. The repo used in these sessions is `E:\Dentfluence\Dentfluence_OS\Dentfluence Web`. Artisan and composer commands are run by Sumit locally; Claude writes files and reads errors.

---

## 9. Build order

1. **Schema.** Nine migrations, models, fail-closed `SiteScope`, `web` `Module` row, role seed rows. *Additive only.*
2. **Renderer and Builder.** Seed Vardhaman's 23 live pages into `web_pages` — the first real test of whether the schema is right.
3. **Deployer and Verifier.** Driven by an artisan command, before any UI exists.
4. **Command Centre screens.** Dashboard, Sites, Site detail first.
5. **Client Portal.** Wire the existing HTML to real controllers.
6. **Automation.** Health runner, nightly analytics job.

No hard dates. Order and ceilings only.

---

## 10. Current state

- Both UIs exist as working **single-file HTML prototypes**. They are the design reference — **port their CSS to Blade rather than redesigning.**
- No Laravel code written yet. Step 1 has not started.
- `app/Modules/` currently holds: Appointment, Hq, Huddle, Lab, Patient, PracticeProtocols, Treatment. No `Web`.

### Sites

| Site | State |
|---|---|
| **vardhamandentalcare.com** | pilot client, 23 pages live on Hostinger, v1.4 |
| tulipdental.in | live (internal) |
| tulipkidsdental.com | live (internal) |
| kidzdent.in | in build — Dr. Amar Katre |
| drnirajbhat.com | setup, blocked on client content |

### Open items, carried

1. **Vardhaman's GA4 is still the placeholder `G-XXXXXXXXXX`** on all 23 live pages. `track.js` is correct and deployed but records nothing. **Fix before building anything that reads analytics**, or Performance gets built against zeros.
2. **No external uptime monitoring.** `web_health_checks` runs inside the app, so it cannot report that the app's own droplet is down. Accepted blind spot for V1; add an external pinger in V2.

---

## 11. Locked decisions — do not re-open

| # | Decision | Reason |
|---|---|---|
| 1 | One Laravel application. No second platform, no React portal, no headless CMS, no separate Node deploy service. | The value is the ~180 existing models. A second platform means rebuilding them or building an API plus a second auth to reach them. Months of work, zero client value, one person maintaining it. |
| 2 | Blade, not a JS framework. | Portal is ~8 read-mostly screens. No build step, no node on the droplet, no hydration bugs. |
| 3 | `web_pages` in our own tables, not a hosted CMS. | Approval, version, deploy and audit must be one transaction in our own tables. A hosted CMS puts client content outside India and outside `web_approvals`. |
| 4 | New module `app/Modules/Web/`, prefix `web_`, `growth_` dropped. | |
| 5 | Two guards (`web`, `portal`), one application, one database. | |
| 6 | `ClientUser` has no relation to any clinical model. | No path, not merely no permission. |
| 7 | `SiteScope` fails closed. `BranchScope` is not the pattern. | |
| 8 | `web_approvals` and audit rows are insert-only, including for Super Admin. | Evidentiary value. |
| 9 | Failed verification never marks a site live; rollback reuses the deploy pipeline. | |
| 10 | App in DigitalOcean Bangalore; client sites stay on their own Hostinger plans. | DPDP Act; blast radius; clean handover. |
| 11 | Redis + a queue worker on the droplet. Skip Horizon until there are more than a handful of jobs. | Deployments and health runs cannot live in an HTTP request. |
| 12 | Portal access is clinic-level in V1. No client_user↔site pivot. | |

---

## 12. Decision log

| Date | Decision | Note |
|---|---|---|
| 2026-09-12 | Stay on Laravel; rejected React portal, headless CMS, separate Node deploy service. | Locked #1–3. Two genuine non-Laravel needs identified: Redis + queue worker (now), external uptime pinger (V2). |
| 2026-09-12 | This SSOT created. | Supersedes scattered chat context. |
| 2026-09-12 | Module folder tree created (§6.1). | Controllers split by guard; `Enums/` added for the 14 enum columns; `client-sites/` views kept disjoint from app UI; model named `SiteRequest` to avoid the `Illuminate\Http\Request` collision. |

*Append here whenever a locked decision changes or a package is added. Date every row.*

---

## 13. Brand

Source: `E:\Dentfluence\Dentfluence Master\06_Brand-Identity\README.md`

| Token | Value |
|---|---|
| Accent | `#A01A86` |
| Deep | `#7A1268` |
| Ink | `#1A0F1E` / `#2A1830` |
| Paper | `#F6F1F5` / `#ECE2EC` |
| Lines | `#D4C2D6` / `#E0D2E1` |
| Warn | `#B8341F` |
| Muted | `#6B5B6B` |

Headlines **Newsreader** · body/UI **Spline Sans** · labels/mono **Spline Sans Mono**

- **Light theme always.** No dark mode, anywhere, in any artifact or UI.
- The Client Portal shows the Dentfluence logo **and** the client's logo together. It should read as collaboration, not vendor branding.
- Positioning: **built by a dentist.** Direct, honest. No fear marketing, no inflated claims.

---

## 14. V1 boundaries — do not build

Patient clinical data of any kind · full CRM · full accounting · social scheduler · ads manager · AI assistant · advanced SEO editor · pixel-level or drag-and-drop page builder · payment gateway · paid rank tracking · native mobile app · in-portal chat · post scheduling · editable clinic profile *(read-only in V1; edits go through `web_requests`)*

---

## 15. Working style

- Direct recommendation, then move. No option menus unless asked.
- Short answers, one step at a time. No sugar-coating; criticism welcome.
- **No hard deadlines on build plans** — order and ceilings only.
- **No demo or mock builds for clients** — ship the real thing.
- Indian context: Maharashtra, Dombivli, ₹, GST, DPDP Act.
- Marathi replies welcome.
