# Phase 3 — Tenancy Design (database per clinic)

Single design document for Signal Board Phase 3 (rows 3.1–3.6). Design only — no code, no migrations.
Signed off by the CEO at row 3.6. Until then nothing here is built.

**Read order for a reviewer:** §3.6 first (one-page summary and every decision), then §3.1–§3.5 for the evidence.
Companion for the CEO (plain language, English + मराठी): `Dentfluence_OS/Tenancy_Design_CEO_Explainer_2026-09-23.html`.

**Taken, not reopened:** one database per clinic. Dentfluence-the-company cannot read a clinic's
patient data. Support access to a clinic database is break-glass, time-boxed and logged.

| Row | Section | Status |
|---|---|---|
| 3.1 | Table classification | written 23 Sep 2026 |
| 3.2 | How a request finds its clinic | written 23 Sep 2026 |
| 3.3 | Tenant context per channel | written 23 Sep 2026 |
| 3.4 | Operations for N databases | written 23 Sep 2026 |
| 3.5 | HQ separation, operator access, entitlements | written 23 Sep 2026 |
| 3.6 | Sign-off | **awaiting CEO signature** |

---

## 3.1 — Table classification

### Measured on (23 Sep 2026)
- Repo HEAD `d81b626`, 457 migration files.
- **Production schema, read from `information_schema` on the box:** 293 tables, 417 foreign keys,
  0 `organization_id` columns, `clinic_id` on 32 tables, `branch_id` on 27.
- Diff vs `STEP1_DISCOVERY.md` (287): +6 tables since 4 Sep (`appointment_cancellations`,
  `clinic_holidays`, `clinic_hours`, `device_tokens`, `notification_rules`, `task_outcomes`);
  `patient_documents` is gone (dropped by `2026_06_14_600003`).
- Prod env: `SESSION_DRIVER=database`, `CACHE_STORE=database`, `QUEUE_CONNECTION=database`,
  `FILESYSTEM_DISK=local`, single `DB_DATABASE=dentfluence`.
- `branches` on prod: 1 row (`Main Clinic`). `users`: 14 rows, 130 FKs point at `users.id`.
- `finance_transactions.clinic_id`: one distinct value, `1`.
- The app has **no second database connection anywhere** — the only `DB::connection()` call is
  `app/Support/Monitoring/Checks/DatabaseCheck.php:19` (a health ping).

### What the three labels mean

| Label | Where it physically lives | Who writes it |
|---|---|---|
| **CLINIC** | Inside every clinic database. One clinic's rows only. | The clinic (or a platform seed at provisioning, after which the clinic owns it). |
| **GLOBAL** | A **copy inside every clinic database**, identical across clinics. | Dentfluence only, shipped as a versioned data release. Read-only in the clinic app. |
| **HQ** | The one central database. Never inside a clinic database. Holds no patient data. | Dentfluence. |

### Result — 293 tables

| Class | Count |
|---|---|
| CLINIC | 260 (of which 32 are seeded from a platform template at provisioning) |
| GLOBAL | 26 |
| HQ | 7 |

Full per-table list: **Appendix 3.1-A** at the end of this document.

### Decisions

**D-1 · The tenant is the practice, not the building.**
A tenant = one Data Fiduciary under DPDP = one legal practice that owns its patient records =
one database. A practice with several locations is one database with several `branches` rows.
Tulip Dental is tenant #1 with branch `Main Clinic`. If Tulip Kids is brought into the system under
the same owner, it is a second branch of the same tenant, not a second tenant (shared families,
shared ledger).

**D-2 · GLOBAL tables are copied into each clinic database, not shared from a central one.**
Why — measured, not preferred:
- 7 hard foreign keys run from clinic tables into global tables:
  `prescription_items.drug_id`, `prescription_overrides.drug_id`, `rx_template_items.drug_id`,
  `rx_template_items.food_instruction_id`, `patient_journeys.decision_tree_id`,
  `case_selections.decision_tree_node_id`, `journey_curations.decision_tree_node_id`.
  A foreign key cannot cross databases.
- 3 foreign keys run the other way, global → clinic:
  `decision_tree_nodes.treatment_id → treatments`, `kb_block_media.media_asset_id → media_assets`,
  `cms_edu_items.uploaded_by → users`.
- The code has zero multi-connection handling; a shared GLOBAL database would put a second
  connection into every prescription query.
Consequences that Phase 4 must honour:
- A global row is identified by a **stable key**, never by its auto-increment id. `rx_drugs.drug_code`
  already exists. `treatments.code` exists but is nullable (`2026_06_03_000001`, line 13) — every
  platform-seeded treatment must carry one, and `decision_tree_nodes` must resolve treatments by code.
- KB media (`kb_block_media`, 9 rows) cannot live in the clinic's `media_assets` table; it moves to a
  global media table with files in platform storage.
- `cms_edu_items.uploaded_by` loses its FK to `users` (0 rows, costless).
- The data release is applied by the same runner that migrates clinics (row 3.4).

**D-3 · "The drug master stays global" is only half true in the current code.**
`routes/prescriptions.php:75-83` lets a clinic create, edit, delete and restore drugs
(`RxDrugController` store/update/destroy/restore). If `rx_drugs` is pure GLOBAL, the next data
release overwrites a clinic's edits, or a clinic's edit silently changes the CDSS rules that the
other 25 rx tables depend on. Rule: platform drug rows are **read-only** in the clinic app; a clinic
may **add** its own brands, which are CLINIC rows in the same table, marked as clinic-origin. The
release never touches clinic-origin rows. The other rx_* tables have no clinic edit route and stay
pure GLOBAL.
Related trap: the screen called "Knowledge Bank" in Settings (`routes/web.php:507`,
`KnowledgeBankController`) edits `diagnosis_treatment_options` — clinic data. It is **not** the
global KB (`kb_*`), which is seeded (`KnowledgeBankSeeder`) and guarded by
`GuardsKnowledgeBankPurity`. Keep the name distinct in Phase 4 so nobody "globalises" that screen.

**D-4 · Queue, session and cache tables live inside the clinic database.**
`jobs`, `job_batches`, `failed_jobs`, `sessions`, `cache`, `cache_locks` are all CLINIC.
`failed_jobs` stores the full job payload plus the exception text, which routinely includes patient
names and phone numbers. A central queue table would put PHI in the one database Dentfluence can
read. Row 3.3 designs how workers and the scheduler reach per-clinic queues; it may not solve it by
moving these tables to HQ.

**D-5 · Login identity moves to HQ; the `users` row stays in the clinic.**
A consultant who works at three clinics needs one login, not three. But 130 foreign keys point at
`users.id`, so the `users` table cannot leave the clinic database.
Split:
- HQ identity: `email`, `phone`, `password`, `two_factor_secret`, `two_factor_recovery_codes`,
  `two_factor_confirmed_at`, `remember_token`, plus `mobile_otps`, `password_reset_pins`,
  `password_reset_tokens` (hence those three are HQ).
- Clinic `users` row keeps everything else (name, designation, colour, `role_id`, `branch_id`,
  `is_active`) and gains a reference to the HQ identity. All 130 FKs stay as they are.
- `users.is_superadmin` has no place in a clinic database — a Dentfluence operator is not a clinic
  user. Its replacement is the break-glass model (row 3.5).
- HQ identity holds staff contact details only, never patient data. How login picks the clinic is
  row 3.2.

**D-6 · The 32 "hybrid masters" are simply CLINIC, seeded at provisioning.**
STEP1 §E asked for a ruling on nullable `organization_id` for treatments, complaints, templates etc.
Under database-per-clinic that question disappears: a new clinic database is seeded from the
platform template, then the clinic owns and edits its copy. Accepted cost: an improvement to the
platform template later does not flow into existing clinics automatically. That is correct — a
clinic's price list and templates are its own.

**D-7 · `roles`, `role_*_permissions` and `feature_flags` are CLINIC. `modules` is GLOBAL.**
The clinic edits its own permission grid (`routes/web.php:1213`, `RolePermissionController`) and
toggles PRE flags (`routes/web.php:423`). `modules` is the code's own catalog and must match the
deployed version, so it ships with releases. `feature_flags` are **not** entitlements (what a clinic
has paid for); entitlements are HQ data, designed in row 3.5.

**D-8 · What database-per-clinic retires from the 4 Sep work.**
- The shared-schema retrofit is superseded: `docs/tenancy/2026_09_04_100001_create_organizations_table.php`,
  `…100002_add_organization_id_to_branches_table.php`, `STEP4_DECISIONS.md` (g) and (h), and the
  `organization_id` counts in STEP1 (b)/(c). **Never apply those two draft migrations.** They stay in
  the folder as history.
- `clinic_id` on 32 tables becomes a dead column (inside one clinic database it can only ever hold one
  value; on prod it is `1`). Do not rename it. Drop it in a later cleanup, not in Phase 4.
- `BranchScope` (`app/Models/Scopes/BranchScope.php`) stops being a tenant boundary. Its fail-open
  no longer leaks across clinics — it becomes a within-practice location filter, handled with location
  permissions in row 3.3.

### Carried forward (named, owned by a later row — not open for 3.1)
- Token format that names the clinic → 3.2.
- How a multi-clinic identity selects its clinic at login and in the mobile app → 3.2.
- How workers/scheduler reach per-clinic queue tables → 3.3.
- Data-release mechanism for GLOBAL and the migrate runner → 3.4.
- HQ tables to be created (tenant registry, identities, memberships, entitlements, break-glass log) → 3.5.

---

## 3.2 — How a request finds its clinic

### Measured on (23 Sep 2026)
- **Edge:** Caddy on the host terminates TLS and proxies two hostnames to the app
  (`/etc/caddy/Caddyfile`): `os.dentfluence.in` and `srv1791841.hstgr.cloud`. The app container's
  nginx is bound to `127.0.0.1:8080`.
- **DNS:** `os.dentfluence.in` → the VPS (187.127.152.68). `dentfluence.in` → 82.25.120.75 (a
  different host). `tulip.dentfluence.in` does not resolve — **there is no wildcard record today.**
- **Web login:** `routes/web.php:39-56` — email + password, phone OTP (`/auth/mobile/send-otp`,
  `/auth/mobile/verify`), then a 2FA challenge. `SESSION_DOMAIN=null` (cookie bound to the exact host).
- **API login:** `routes/api.php:54` `POST /api/v1/auth/login` →
  `Api/V1/AuthController.php:99` issues a Sanctum personal access token
  (`id|random`, stored hashed in `personal_access_tokens`). `config/sanctum.php`: 30-day expiry
  (43200 min), no token prefix.
- **Mobile:** one base URL, shipped default `https://os.dentfluence.in/api/v1`
  (`lib/services/app_settings.dart:30`), user-editable in Settings; logs in with email + password
  (`lib/services/api_client.dart:199`), stores one token.
- **Unauthenticated inbound HTTP that must also find a clinic:**
  patient links `/present/{token}` (`web.php:926`), `/p/{token}` (`web.php:939`), `/r/{token}`
  (`routes/reviews.php:15`), staff QR check-in `/hr/checkin/{token}` (`web.php:1285`); the WhatsApp
  webhook `/api/v1/webhooks/prm/whatsapp` (`api.php:66-67`); the OAuth callback
  `/integrations/{platform}/callback` (`marketing.php:131`); `/api/v1/ops/alert` and `/api/v1/ping`.
- Patient links are built with `route()` (`PresentationLinkService.php:48`), i.e. from `APP_URL`
  (`config/app.php:55`), which today is `https://os.dentfluence.in`.

### Decision — the host name is the clinic. Nothing else is.

**`<slug>.dentfluence.in` is the only thing that selects a clinic database**, for web, API and
public links alike. No header, no form field, no token contents, no session value may select or
override it.

Why subdomain and not "clinic code at login":
- Patient links, QR check-in and review links have **no login**. A clinic code typed at login cannot
  route them; the host can.
- The session cookie is already bound to the exact host (`SESSION_DOMAIN=null`), so two clinics'
  sessions are separate cookies with no design work.
- A receptionist types nothing extra every morning. The bookmark is the clinic.
- One source of truth. "Both" means two sources that can disagree, and the disagreement is where a
  leak happens.

**Why the token does not carry the clinic.** The token stays an opaque Sanctum token, stored only in
the clinic database of the host that issued it. A Tulip token sent to another clinic's host is looked
up in that clinic's `personal_access_tokens`, fails the hash, and returns 401. A token that *named*
its clinic would be a second tenant source the server would be tempted to trust. Format: Sanctum
`id|random` with a fixed prefix `dfc_` (so leaked tokens are findable by secret scanners). Expiry stays
30 days.

### Slug rules
- `a-z`, `0-9`, `-`; 3–30 chars; must start with a letter.
- Reserved, never issued: `os`, `www`, `api`, `hq`, `admin`, `app`, `mail`, `status`, `support`,
  `help`, `static`, `cdn`, `assets`, `demo`, `test`, `staging`.
- Issued once, never reused. A rename keeps the old slug as a 301 alias to the new one, so links
  already sent to patients keep working. A deleted clinic's slug stays reserved forever.

### The lookup, in order (every request)
1. **Edge (Caddy).** Wildcard DNS `*.dentfluence.in` → VPS. Caddy issues certificates on demand and,
   before issuing, asks the app's registry "is this slug an active clinic?" An unknown slug gets
   **no certificate** — the connection fails at TLS before any application code runs.
2. **App, first middleware — before session, cookies, auth or any database query.** Host → HQ tenant
   registry. The result is one of:
   - **active** → the clinic's database connection is opened, and the request continues.
   - **unknown, deleted, or any host that is not `<slug>.dentfluence.in` or an HQ host** → **404**.
     No session is started, no cookie is set, no clinic database is touched.
   - **suspended** (unpaid, paused) → **403** with a plain "this account is paused, contact
     Dentfluence" page. Staff of a paying clinic that lapsed should not see a 404. Patient links on a
     suspended clinic also get this page; they reveal nothing about the patient.
3. **No default clinic connection.** Today `DB_DATABASE=dentfluence` is the default connection, so
   any code that runs before resolution silently reads Tulip. In the new design the default clinic
   connection has **no database**; code that reaches the database before step 2 throws instead of
   reading someone's data. This is what makes the design fail closed, not the 404 page.

### Login and switching clinics
- **Login happens on the clinic's own host.** Credentials (email/phone, password, OTP, 2FA) are
  checked against the HQ identity (row 3.1, D-5). Then the identity must have an **active membership
  in this clinic**. No membership gives the **same** error as a wrong password — the login page never
  reveals whether an email belongs to a clinic.
- **`os.dentfluence.in` becomes the "my clinics" door.** Sign in once, see the clinics you are a
  member of, pick one. The pick hands over to `<slug>.dentfluence.in` with a single-use, 60-second
  handoff code bound to that slug; the clinic host exchanges it for its own session. The HQ host
  itself serves **no clinic data**, ever.
- **Switching** = picking another clinic from that list (or opening its bookmark). Each clinic host
  has its own cookie, so being logged into two clinics in two tabs is safe by construction.

### Mobile app
1. Login screen talks to `https://os.dentfluence.in/api/v1/auth/login` (identity + 2FA), which
   returns the member clinics (slug + display name) and one handoff code per clinic — never a clinic
   token and never clinic data.
2. One clinic → selected automatically. More than one → a picker.
3. The app exchanges the handoff at `https://<slug>.dentfluence.in/api/v1/auth/exchange` for a
   Sanctum token issued by that clinic, and from then on uses `https://<slug>.dentfluence.in/api/v1`
   as its base URL.
4. The app stores `{slug, token}` per clinic. "Switch clinic" in the menu repeats step 2 without a
   fresh password while the HQ session is valid.
5. The free-text "Server" field in Settings (`settings_screen.dart:27`) is removed from release
   builds. A user-editable base URL is a way to point a staff phone at the wrong clinic or an
   impostor server.

### Inbound traffic that has no clinic host
| Entry | Where it lands | How the clinic is found | Unknown → |
|---|---|---|---|
| WhatsApp webhook | HQ host | `phone_number_id` in the Meta payload → registry | Ack 200, drop, log. Meta disables webhooks that keep failing; a 404 here would take down every clinic's WhatsApp. Never routed to a default clinic. Detail in 3.3. |
| OAuth callback (Google/Meta etc.) | HQ host, one fixed redirect URI | Signed `state` parameter carrying slug + nonce, issued when the clinic started the connect | 404 |
| `/api/v1/ops/alert`, `/api/v1/ping` | HQ host only | Not tenant traffic | — |
| Patient links, review links, QR check-in | Clinic host | Host | 404 (TLS failure first) |

Consequence for 3.3: every link built without a request — in a queued job, the scheduler, a
notification — must be built from the clinic's slug, not from `APP_URL`.

### Cut-over for Tulip (the one existing clinic)
- Tulip becomes `tulip.dentfluence.in`.
- Everything already in the field points at `os.dentfluence.in`: installed APKs (default base URL),
  patient links already sent on WhatsApp, bookmarks. So for a **time-boxed transition**,
  `os.dentfluence.in` is an explicit alias of Tulip.
- **Hard gate:** this alias is removed **before any second clinic is activated**. With two tenants,
  a host that maps to a fixed clinic is exactly the fail-open this design exists to kill. The
  row 3.4 runbook carries this as a blocking check. Patient links still pointing at the old host
  break at that moment; they carry `expires_at`, and their live count is measured before removal.
- `srv1791841.hstgr.cloud` is removed from the Caddyfile (or answers 404). The raw IP answers 404.

### Carried forward
- WhatsApp routing detail, and link building in jobs → 3.3.
- Wildcard DNS record and Caddy on-demand TLS setup, and the step order for adding a clinic → 3.4.
- The HQ tables this row relies on: tenant registry (slug, status, DB connection details, WhatsApp
  `phone_number_id`), identities, memberships, handoff codes → 3.5.

---

## 3.3 — Tenant context for every channel

### Measured on (23 Sep 2026)
- **Queue:** `QUEUE_CONNECTION=database`; one worker container running
  `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`, using **38 MB**. 9 queued classes
  (`app/Jobs/*`: blog publication, lead enrichment, watermark, scheduled post, search index,
  insight signals, relationship score, automation-failed, push). 17 dispatch/notify call sites.
- **Scheduler:** one container looping `schedule:run` every 60 s; **24 entries** in
  `routes/console.php` (recall, reminders, huddle, today projection, analytics, wallet, push sweep,
  logs prune, audit verify…). One is named after a clinic: `tulip:huddle` (`console.php:315`).
- **Box:** 8 GB RAM, 2 cores; 6.3 GB available. MySQL 720 MB, app 75 MB.
- **Process-wide state:** 8 container singletons (`FoundationServiceProvider.php:35-43`,
  `AppServiceProvider.php:55-57`, `AbdmServiceProvider.php:45-52`) — including
  `FeatureFlagService` and `DomainEventBus`. A singleton lives for the life of the process.
- **Cache:** `CACHE_STORE=database`; 14 call sites; keys like `feature_flags.overrides`,
  `rel_analytics_*`, `system_status.probe` — **none carry a tenant**, which is fine only while the
  store itself is per clinic.
- **Files:** disk `local` → `storage/app/private` (holds `patients/`, `lab-attachments/`, `imports/`);
  clinical files use it (`ClinicalFileUploadService.php:41`). Disk `public` → `storage/app/public`,
  served straight by nginx at `/storage/…` with **no app check** (holds `marketing/`, `treatments/`,
  `inventory/` — no patient folder today); 28 call sites.
- **Logs:** `LOG_STACK=single` → one `laravel.log`, plus per-command files via `appendOutputTo`
  (`tasks-shift-reminder.log`, `recall-engine.log`, …). 164 `Log::` calls.
- **PDF:** `app/Services/Print/PdfRenderer.php` inlines images as base64 and recognises "our own
  host" from `config('app.url')` (line 105) before posting HTML to the shared Gotenberg container.
- **Push:** `FcmSender.php:107-113` sends `title` + `body` through Google FCM; the title is built as
  `$patient->name . ' — consultation done'` (`ChairsideNotifier.php:52`, `:149`). One FCM project.
- **WhatsApp:** one sender, from env — `WHATSAPP_PHONE_NUMBER_ID`, `WHATSAPP_ACCESS_TOKEN`
  (`config/whatsapp.php:26-28`).
- **Mail:** one `MAIL_FROM_ADDRESS` (`config/mail.php:113`).

### The one rule that makes this tractable
**One process serves exactly one clinic for its whole life.** A web request is one clinic. A queue
worker is started *for* one clinic and never switches. A scheduled or manual command is run as a
child process per clinic, never as an in-process loop that swaps connections.

Why: the 8 singletons above, and every static or memoised value nobody has found yet, would carry
clinic A's state into clinic B the first time a process switched. Auditing every one of them is the
work that makes shared-process tenancy fail. Process isolation makes that whole class of leak
impossible without auditing it. The cost is memory: at 38 MB a worker, 30 clinics ≈ 1.1 GB of the
6.3 GB free. The ceiling (when this stops being true) is carried into the 3.4 runbook.

A **TenantContext** is set exactly once at process start (or request start, per 3.2): slug, database
connection, storage root, cache prefix, log context, app URL. Nothing downstream reads the tenant
from anywhere else. There is no default: reading the context before it is set throws.

### Channel → how the tenant is carried → how a leak is prevented

| Channel | How the tenant is carried | How a leak is prevented |
|---|---|---|
| **Web** | Host `<slug>.dentfluence.in` → registry (3.2), first middleware. | Unknown → 404 before session/DB. Default connection has no database, so pre-resolution code throws. |
| **API (web + mobile)** | Same host rule. Sanctum token lives only in that clinic's `personal_access_tokens`. | A token from clinic A on clinic B's host fails the hash → 401. No header or token field can choose a clinic. |
| **Session** | `SESSION_DRIVER=database` → the `sessions` table in the clinic's own database. Cookie bound to the exact host. | `SESSION_DOMAIN` stays `null` — **never** `.dentfluence.in`, which would send one clinic's cookie to every clinic. Test asserts it. |
| **Queued jobs** | Job is written to the `jobs` table of the clinic that dispatched it (3.1 D-4). Worker per clinic: `queue:work` started with that clinic's context and connection. Payload also carries the slug. | The worker only *can* see its own clinic's jobs table. On `handle()`, payload slug must equal worker slug, else the job fails loudly (never runs "somewhere"). `failed_jobs` with its PHI stays in the clinic DB. |
| **Scheduler** | One scheduler in HQ context. Each tenant-level entry runs as `tenants:run <command>`, which spawns one child process per active clinic with that clinic's context. | Child processes → no shared state. One clinic's failure is logged and the next clinic still runs (unlike migrate, which stops — 3.4). `withoutOverlapping` locks live in each clinic's `cache_locks`. Platform-level entries (`logs:prune`, `audit:verify` of HQ, backups) run once in HQ context and cannot open a clinic DB. `tulip:huddle` is renamed to a neutral name at build. |
| **Manual artisan / tinker** | `--tenant=<slug>` is required on every clinic-level command. | No default connection → a command without `--tenant` touches nothing. Break-glass rules (3.5) govern who may run it on prod. |
| **Cache** | `CACHE_STORE=database` → `cache` table in the clinic DB. | Separate store per clinic by construction. If the store ever moves to Redis/files, the TenantContext sets a mandatory `slug:` prefix; the file store is never used for tenant data. |
| **File storage** | Every disk root is per clinic: `storage/tenants/<slug>/private` and `…/public`. Set by TenantContext, not by callers. | Private files only through the app (SecureMediaController + auth). The public `/storage/` alias is re-mapped per host to that clinic's public folder, so clinic B's host can't serve clinic A's public files. Rule: **no patient file on the public disk, ever** (true today; test asserts it). |
| **Logs** | One log stream; every line carries `tenant=<slug>` via shared log context. Per-command output files move to `storage/logs/tenants/<slug>/`. | Logs are Dentfluence-readable, so they must **not contain patient data**: ids only, no names/phones/OTPs. SQL exception text (which embeds bound values) is scrubbed before logging. Two live breaches found — see below. |
| **Notifications (bell, mail)** | Notification row in the clinic's `app_notifications`; queued ones ride that clinic's queue. Links built from the slug, not `APP_URL`. | Built inside the clinic worker, so recipients can only be that clinic's users. Mail "From" name is the clinic ("Tulip Dental via Dentfluence"); reply-to is the clinic's address from its own settings. |
| **Links built without a request** (reminders, review asks, presentations) | TenantContext sets the app URL to `https://<slug>.dentfluence.in` at process start. `PdfRenderer`'s own-host check (line 105) reads the same value. | A patient link can only ever point at the host of the clinic that generated it. Test: a link generated in clinic A's worker never contains another host. |
| **WhatsApp — outbound** | Credentials (`phone_number_id`, WABA id, access token) move from env to the clinic's own settings, encrypted. | The sender reads credentials only from the current clinic. There is no env fallback — no credentials = not sent. Env-level WhatsApp config is removed. |
| **WhatsApp — inbound webhook** | One platform endpoint. `phone_number_id` in the Meta payload → registry → slug. The platform process holds **no clinic credentials** (3.5): it verifies the signature and forwards the request over the internal network to that clinic's app, which queues it in its own database. Nothing is stored in HQ. | Unknown `phone_number_id` → ack 200, drop, log (3.2). Signature check first. The event is processed by the clinic's own worker, in clinic context. |
| **Push (FCM)** | `device_tokens` in the clinic DB; sent by the clinic worker. Payload `data` carries `{slug, notification_id}` so the app opens the right clinic. | **Title/body carry no patient name.** Today they do (`ChairsideNotifier.php:52`, `:149`): a patient name then passes through Google and shows on a locked phone. New text: "Consultation done — tap to open"; the app fetches detail from the clinic API. One FCM service account (an HQ secret, no tenant data). |
| **Search** | `search_index` is a clinic table; index jobs run in the clinic worker. | Separate index per clinic by construction. No global search across clinics exists, and none is built. |
| **Exports / PDF** | Generated inside the clinic request/worker; files written to the clinic's private root. | Gotenberg is shared but stateless: it receives self-contained HTML (images already inlined) and keeps nothing. Exports stay behind the role gate + activity log (4 Sep position). |
| **Outbound integrations** (Google/Meta OAuth, AI vision, SMS) | Per-clinic tokens stored in the clinic DB. OAuth callback returns via signed `state` (3.2). | Integration clients are built per call from TenantContext, never cached in a singleton with credentials inside. |

### Findings while measuring (live today, independent of tenancy)
1. 🔴 **Staff login OTP written to the log in plain text** — `app/Http/Controllers/Auth/MobileOtpController.php:72`
   logs `phone` + `otp` when the SMS gateway is not configured. Anyone who can read `laravel.log`
   can log in as that staff member. This needs a tracker row now, not in Phase 4.
2. 🟠 **Patient names in push notifications** — `ChairsideNotifier.php:52`, `:149` (above).
3. 🟠 **Exception messages logged raw** (e.g. `Api/V1/RelationshipController.php:524`) — a
   `QueryException` message contains the SQL with bound values, i.e. patient data in the log.

### Carried forward
- Worker supervision (start/stop a worker when a clinic is activated or suspended), and the memory
  ceiling for worker-per-clinic → 3.4.
- Who may run `--tenant` commands on production → 3.5.

---

## 3.4 — Operations for N databases (runbook)

### Measured on (23 Sep 2026)
- **Deploy** (`deploy.sh`): git pull → build image → **start the new app, queue and scheduler
  (line 48)** → sleep 5 → dump (line 64) → `migrate --force` (line 78) → caches → restart queue +
  scheduler. The dump refuses to continue if it is < 1 MB or not valid gzip (line 69).
- **Backup** (`/opt/dentfluence/backup.sh`, root cron `0 21 * * *` UTC = 02:30 IST): one DB dump
  (~5.2 MB gz) + one `storage/app` tarball (~30 MB), encrypted with **one** rclone crypt key, to
  Google Drive; 30 daily + 13 monthly; size read back; failure skips pruning. Hourly
  `ops/healthcheck.sh` checks site, queue, backup age.
- **Restore drill** (19 Sep, `docs/RESTORE_RUNBOOK.md`): download + decrypt 7 s, restore 24.2 s,
  database usable in < 1 minute; row counts and real records matched.
- One MySQL instance, root used by every script; the app connects as one user to one database.

### 🔴 Defect in today's deploy order
New code starts (line 48) **before** the dump and the migration (lines 64, 78). For a few seconds,
and for as long as a migration runs, new code serves requests against the old schema, and the
"pre-migration" dump is taken while that new code may already be writing. Harmless-ish with one
clinic; with N clinics and a slow migration it is guaranteed breakage. The order below fixes it.

### Standing rules (every release, every clinic)
1. **Migrations are expand-only.** A release may add tables, columns, indexes. It may not drop or
   rename anything the *previous* release's code reads. Drops ship one release later, once no code
   reads them. This is what lets old code keep running on clinics already migrated if the rollout
   stops half-way.
2. **Every clinic records its schema version** in the HQ registry (last migration applied). The app
   refuses to serve a clinic whose version is not the one the running code expects → 503 "updating,
   back in a few minutes". New code can never run on an unmigrated clinic.
3. **One MySQL user per clinic,** granted on that clinic's database only. The app opens each clinic
   with that clinic's own credentials (encrypted in the registry). An SQL-injection bug in one
   clinic's request cannot read another clinic's database — MySQL itself refuses. `root` is used by
   ops scripts only.
4. **Canary first.** An internal tenant `canary` (synthetic data only, never real patients) is
   always first in every migrate order. A migration that breaks, breaks there.
5. **Migrate stops on the first failure. Backup and scheduler do not.** Migrate: a broken clinic must
   stop the rollout. Backup: one clinic's failed dump must not leave the others unprotected.

### A. Deploy (release to N clinics)
Run in the night window (after 22:00 IST, before the 02:30 backup), never during clinic hours.
1. **Preflight** (abort if any fails)
   - Registry reachable; list of active clinics + their schema versions.
   - Every clinic database reachable with its own credentials.
   - Free disk ≥ 3 × (sum of the last nightly dump sizes).
   - **Alias gate (3.2):** if more than one real clinic is active, `os.dentfluence.in` must not be
     aliased to any clinic. Abort otherwise.
2. **Build** the new image. Do **not** start it.
3. **Stop** all queue workers and the scheduler (web stays on old code). No job runs mid-migration.
4. **HQ database:** dump → verify → migrate.
5. **Each clinic, in order** (`canary` first, then clinics oldest-first):
   dump → verify (size + gzip, as today) → migrate → apply GLOBAL data release (§E) →
   record schema version in the registry → next.
   **On the first failure: stop.** Restore *that one clinic* from the dump just taken (§C) — MySQL DDL
   is not transactional, so a half-run migration leaves a half-changed schema. Clinics already done
   stay on the new schema (safe by rule 1). Clinics not reached stay untouched. Old code keeps
   serving everyone. Workers and scheduler restart on the old code. Alert, stop, investigate.
6. **Only when every clinic is migrated:** start the new app image, then the scheduler, then workers
   (one per active clinic — §F).
7. **Smoke:** for each clinic, the login page on its host returns 200 and its version matches. For one
   random clinic, a signed-in read of today's appointments returns 200.

Rollback of a *completed* deploy = redeploy the previous image. Schema stays (rule 1 makes old code
compatible). Never run `migrate:rollback`.

### B. Backup (nightly, per clinic)
- For each active clinic, separately: dump its database + tar its `storage/tenants/<slug>/` →
  encrypt with **that clinic's own key** → off-site to `offsite:tenants/<slug>/daily/`. HQ database
  goes to `offsite:hq/daily/`. Same size/gzip/read-back checks as today; same 30 daily + 13 monthly.
- One clinic failing → alert names the clinic; the loop continues; pruning is skipped **for that
  clinic only**.
- **Why a key per clinic:** when a clinic leaves and asks for erasure (DPDP), its data still sits in
  13 months of backups. Destroying that clinic's key makes every one of those copies unreadable at
  once (crypto-shredding) without touching anyone else's backups. With one shared key, erasure from
  backups is impossible.
- Keys: per-clinic keys are stored in the HQ key file, backed up the same way today's rclone keys are
  (`E:\Dentfluence\_keys\`, and on the VPS). Lose the key file = lose every clinic's backups, so it is
  in two places, never only one.
- Healthcheck becomes per clinic: newest local and off-site backup age for every active slug.
- Capacity: today ~30 s per clinic end to end → 30 clinics ≈ 15 min, well inside the night.

### C. Restore one clinic alone
Everyone else keeps working throughout.
1. Registry: set the clinic to **suspended** → its staff see the "paused" page (3.2); stop its worker;
   its scheduler children skip it.
2. Fetch and decrypt that clinic's chosen dump + file archive (its own key).
3. Restore into a new database `<slug>_restore_<stamp>` (never over the live one first).
4. Verify: table count, row counts of patients / appointments / invoices / treatment_visits against
   the last known numbers, one real record read back — as in the 19 Sep drill.
5. If the dump's schema version is older than the running code: migrate that restored database
   forward (same expand-only migrations).
6. Point the registry at the restored database; swap the file folder the same way (old folder kept,
   renamed, not deleted).
7. Registry: **active**. Start its worker. Log the restore in the clinic's own audit log and in HQ.
8. The old live database is kept, renamed, for 7 days, then dropped by the CEO's explicit command.

The same procedure is the drill: run it for `canary` monthly and for one real clinic quarterly, into
a throwaway database, and record the timings as in `RESTORE_RUNBOOK.md`.

### D. Add a clinic (provision)
1. Choose slug (3.2 rules) → HQ registry row, status **provisioning** (unreachable: not active).
2. Create database + its own MySQL user + grants; generate its backup key; store both, encrypted.
3. Migrate to current version; load GLOBAL data release; seed the platform template (3.1 D-6):
   treatments, templates, roles and permission grid, one branch.
4. Create the owner's membership (identity + clinic `users` row).
5. Status **active** → wildcard DNS already covers it; Caddy issues the certificate on first visit;
   the worker supervisor starts its worker within a minute.
No DNS change, no deploy, no restart is needed to add a clinic.

### E. GLOBAL data release (drug master, KB, modules)
- Versioned with the code. Applied per clinic in deploy step 5, after migrations.
- Upsert by stable key (`drug_code`, treatment `code`, KB slug) — never by id.
- Never touches clinic-origin rows (3.1 D-3).
- Recorded as `global_data_version` next to schema version in the registry.

### F. Queue workers and scheduler (from 3.3)
- A supervisor in the queue container reads the registry every minute and keeps exactly one
  `queue:work` per **active** clinic: starts new ones, stops suspended/removed ones.
- **Ceiling:** 38 MB per worker today. Act when active clinics reach ~60 (~2.3 GB) or free memory
  on the box falls below 1.5 GB — whichever comes first: add a second box and split clinics
  between them (the registry says which box serves which clinic). This is a known ceiling, not a
  surprise.
- Scheduler: unchanged single loop; tenant entries fan out via `tenants:run` (3.3).

### G. A clinic leaves (offboarding)
1. Export: that clinic's dump + file archive handed to the clinic owner (DPDP: the Data Fiduciary
   owns its data).
2. Status **suspended** for the notice period, then **archived**: worker stopped, database dropped
   by explicit CEO command only, files moved to a junk folder, then removed after the notice period.
3. Destroy the clinic's backup key → every retained backup of that clinic becomes unreadable.
4. The slug stays reserved forever (3.2).

---

## 3.5 — HQ separation, operator access, entitlements

### Measured on (23 Sep 2026)
- **HQ lives inside the patient app today.** `app/Modules/Hq` (clinics, plans, subscriptions,
  tickets) is loaded by `bootstrap/app.php:25` into the same web app, same database, same session,
  gated only by `users.is_superadmin` (`Hq/Middleware/EnsureIsSuperadmin.php:13`). On prod,
  0 users have that flag.
- **Entitlements already exist as data.** `plans.unlocks` (JSON) on 8 plans with codes `pre`,
  `library`, `marketing`, `presentation` and bundles of them; `Clinic::hasPass()`
  (`Hq/Models/Clinic.php:48`). The door, `EnsureClinicHasPass` (`pass:<code>`), is written but
  registered on **no route** — it waits for "current clinic", which this design provides.
- **Operator access to production today:** two root SSH keys — `sumit-laptop` and
  `claude-cowork@dentfluence`. Password login off, root by key only. Either key can read every row
  of every table with no record beyond `last`. No MySQL port is published (confirmed in 3.2).
- **Audit:** tamper-evident hash chain on audit tables, verified by `audit:verify`
  (`app/Console/Commands/AuditVerify.php:15`).
- **Roles on prod:** Admin 2, Manager 1, Doctor 5, Associate dentist 1, **Visiting consultant 1**,
  Front Desk 2, Assistant 2 — the visiting consultant is the real case for one identity across
  clinics (3.1 D-5).

### A. Three runtimes from one codebase
Same repository, same image, **three containers with different secrets**:

| Runtime | Host | Database access | Holds |
|---|---|---|---|
| **Clinic app** (web, API, workers, scheduler) | `<slug>.dentfluence.in` | Its own clinic DB (per-clinic MySQL user); HQ: read registry row, identities, memberships; write memberships | The key that decrypts clinic DB credentials |
| **Platform door** | `os.dentfluence.in` | HQ only | Identity login, "my clinics", handoff codes, Caddy's "is this slug active?", webhook router. **No clinic credentials, no clinic data.** |
| **HQ console** | `os.dentfluence.in/hq` (same platform container, operator login) | HQ only | Sales CRM (`clinics` = prospects), plans, subscriptions, tickets, registry, entitlements, access grants |

The HQ module is unloaded from the clinic app. `users.is_superadmin` is dropped: a Dentfluence
operator is never a row in a clinic's `users` table. Operators are identities with an **operator**
record in HQ, 2FA mandatory.
`clinics` (prospects) stays the sales list; a prospect that signs becomes a **tenant** at
provisioning (`clinics.tenant_id` links them). The two are never the same table again.

### B. HQ tables (to be created in Phase 4 — design only here)
- `tenants` — the registry: slug, display name, status (provisioning / active / suspended /
  archived), DB host + name + user + password (encrypted with the clinic-runtime key),
  schema version, global-data version, serving box, WhatsApp `phone_number_id`.
- `tenant_slug_aliases` — old slugs for 301s; reserved forever.
- `identities` — email, phone, password hash, 2FA, remember token; `identity_otps`,
  `identity_password_resets` (the three HQ tables from 3.1).
- `memberships` — identity × tenant × status (invited / active / revoked). **Only "may this person
  enter this clinic."** Role and location access stay in the clinic DB (`users.role_id`,
  branch access), edited by the clinic's own admin. That keeps the three things separate:
  membership in HQ, role and location in the clinic.
- `handoff_codes` — single-use, 60 s, bound to one slug (3.2).
- `operators` — identity → operator level (below).
- `access_grants` and `access_log` — break-glass (below); `access_log` is append-only and
  hash-chained like today's audit tables.
- `plans`, `subscriptions`, `clinics`, `tickets` — moved as they are.

**Invites and revocation.** A clinic admin invites by email or phone from inside the clinic app:
a pending `users` row in the clinic + a pending membership in HQ. The invitee creates an identity
or signs in with an existing one. Revoking a membership is immediate: membership → revoked, and
that clinic's tokens and sessions for the user are deleted in the same step.

### C. Entitlements (what a clinic has paid for) ≠ permissions (what a user may do)
- **Base** — patients, appointments, consultation, prescriptions, treatment, billing, inventory,
  lab, HR, tasks, huddle — is what every active tenant has. Not sold module by module in V1.2.
- **Add-ons** are the codes already in `plans.unlocks`: `pre`, `marketing`, `library`,
  `presentation`. New codes are rows, not redesigns — so a future standalone product is a new code,
  not a new architecture.
- At tenant resolution (3.2 step 2) the registry returns the tenant's current code set, cached for
  that request / process.
- Enforcement, all server-side: `pass:<code>` on the add-on's web and API route groups
  (`EnsureClinicHasPass`, finally registered); scheduler and workers skip jobs of add-ons the tenant
  does not have; menu items are hidden too, but hiding is never the control.
- A request must pass **both** doors: entitlement (tenant) and the clinic's permission grid (user).
  Neither checks the other.
- **Lapse:** add-on expires → 7-day grace with a banner → module closed (403 "renew" page), data
  kept untouched. Base expires → same grace → tenant **suspended** (3.2 paused page). Nothing is
  deleted by a lapse.
- No product access is ever decided by an email address or a hard-coded user.

### D. Operator access — the model (the 4 Sep position, made concrete)
We do not promise that no one can ever look. We promise that **looking is separated, time-boxed,
recorded, and visible to the clinic.**

| Level | What it allows | How it is granted | Record |
|---|---|---|---|
| **L0 — Platform** | Registry, plans, subscriptions, health, backup status, counts. **No clinic rows.** | Operator login + 2FA. | HQ `access_log`. |
| **L1 — Break-glass into one clinic** | A support session inside that clinic's app, as a named "Dentfluence support — <name>" user — never by impersonating a clinic staff member. Read-only by default; write only if the grant says so. | Operator requests with **reason + ticket number + duration** (default 30 min, max 4 h). **Clinic owner approves in-app.** Auto-expires; no renewal without a new grant. | Written in HQ `access_log` **and** in the clinic's own hash-chained audit log, every page and action. The clinic owner sees it under Settings → Access log. |
| **L1-E — Emergency** | Same as L1, for a clinic that is down or being restored, when the owner cannot be reached. | Operator self-grants with reason, max 2 h. | Clinic owner notified immediately (in-app + email), and the grant is flagged for review. |
| **L2 — Infrastructure** | SSH / MySQL root on the box — required for deploy, backup, restore (3.4). Technically sees everything. | Named keys of named humans only. Every SSH login is forced through a wrapper that asks for a reason and ticket, writes it to HQ `access_log`, then opens the shell. Routine deploy/backup/restore run as fixed scripts, not by hand. | `access_log` + system auth log. |

Rules that go with it:
- **No DB GUI, no published MySQL port, no shared root password** on production (4 Sep, unchanged).
- **The Cowork key (`claude-cowork@dentfluence`) is removed before any second clinic is active.**
  Standing root access for an AI agent is the opposite of break-glass. When Claude needs the box
  again, Sumit adds a key with an OpenSSH `expiry-time` for that job only, and it goes through the
  same reason wrapper.
- **Sumit wears two hats.** For Tulip he is the clinic owner (Data Fiduciary): normal clinic login,
  no break-glass needed. For every other clinic he is an operator: L1 like anyone else. The
  design does not give the CEO a silent back door.
- **Clinic-facing promise (goes into the processor contract):** "Dentfluence staff can only open
  your data with your approval, for a stated reason and a limited time, and every access is shown
  to you." The DPDP processor contract is signed before clinic #2 goes live.

### E. Rejected, on purpose
- **Per-clinic encryption keys for PHI columns in the live database.** It would stop a stolen
  database + app key from exposing every clinic at once, but it means re-encrypting Tulip's data
  and managing N live keys, and it does not stop an L2 operator (who holds the keys anyway). The
  separation that matters here is databases, MySQL users, break-glass and per-clinic *backup* keys
  (3.4). Revisit only if a customer contract demands it.
- **Zero-knowledge / customer-held keys.** Consistent with the 18 Jul note: it makes restore and
  support impossible, which a dental clinic will not accept.

---

## 3.6 — Sign-off

### The design in one paragraph
Every clinic (practice) gets its **own database**, its **own MySQL user**, its **own file folder**,
its **own queue worker** and its **own backup key**. A clinic is chosen **only** by its web address
`<slug>.dentfluence.in`; anything unknown gets 404 before any clinic data is touched, and the app
has no default database to fall back on. People log in with **one identity** held centrally
(no patient data there) and may belong to several clinics; roles and locations stay inside each
clinic. Dentfluence reference data (drug master, KB) is **copied** read-only into every clinic.
HQ (sales, plans, registry) runs in a **separate runtime** that holds no clinic credentials.
Dentfluence staff reach a clinic's data only by **break-glass**: reason, time limit, clinic-owner
approval, logged where the owner can see it. What a clinic has **paid for** (entitlements) and what a
user **may do** (permissions) are checked separately, server-side.

### Decision register — each needs the CEO's yes

| # | Decision | § |
|---|---|---|
| S-1 | One database per clinic (taken 4 Sep; not reopened). | — |
| S-2 | A tenant = a practice (Data Fiduciary), not a building. **Tulip Kids, if brought in under the same owner, is a second branch of the Tulip tenant.** *(Rests on a fact only the CEO can confirm.)* | 3.1 D-1 |
| S-3 | GLOBAL data is copied read-only into every clinic DB and shipped as a versioned release. Drug master: platform rows read-only; a clinic may add its own brands. | 3.1 D-2, D-3 |
| S-4 | Login identity (email, phone, password, 2FA) is central; the `users` row stays in the clinic (130 FKs). `is_superadmin` is removed. | 3.1 D-5, 3.5 A |
| S-5 | Queue, session and cache tables stay in each clinic DB (no PHI in a central queue). | 3.1 D-4 |
| S-6 | The 32 editable masters are seeded per clinic from a template, then owned by the clinic. | 3.1 D-6 |
| S-7 | The web address is the only clinic selector. Tokens are opaque (`dfc_` prefix) and valid only where issued. Unknown → 404; suspended → 403 "paused". | 3.2 |
| S-8 | `os.dentfluence.in` stays a Tulip alias during cut-over and is **removed before any second clinic goes live** (blocking check in the deploy runbook). | 3.2, 3.4 |
| S-9 | Mobile: central login → pick clinic → clinic token. The free-text Server field is removed from release builds. | 3.2 |
| S-10 | One process serves one clinic for its whole life (worker per clinic, scheduler child per clinic). Ceiling ~60 clinics per box. | 3.3, 3.4 F |
| S-11 | Push notifications carry **no patient name**; logs carry **no patient data**. | 3.3 |
| S-12 | WhatsApp credentials per clinic, no env fallback. | 3.3 |
| S-13 | Migrations are expand-only; new deploy order (migrate every clinic first, then start new code); canary tenant first; migrate stops on first failure. | 3.4 A |
| S-14 | One MySQL user per clinic; one backup key per clinic (crypto-shred on exit). | 3.4 |
| S-15 | HQ leaves the patient app: three runtimes from one codebase. | 3.5 A |
| S-16 | Break-glass L0 / L1 / L1-E / L2, clinic-owner approval for L1, owner sees the access log. **The Cowork root key is removed before clinic #2. The CEO has no silent back door into other clinics.** | 3.5 D |
| S-17 | Entitlements: base product + 4 add-ons (`pre`, `marketing`, `library`, `presentation`); 7-day grace; a lapse never deletes data. | 3.5 C |

### Rejected on purpose (closed, not open)
Clinic code at login · "both" subdomain and code · a token that names its clinic · a shared GLOBAL
database · shared-schema `organization_id` (the 4 Sep drafts — never apply) · renaming `clinic_id` ·
a central queue · per-clinic PHI column keys · zero-knowledge keys · a production DB GUI ·
splitting the base product into sold modules for V1.2 · microservices.

### Open questions
**None.** S-2 is the only item that rests on a fact; the CEO confirms it by signing.

### Findings that need tracker rows now (not Phase 4 — live today)
| # | Finding | Where | Severity |
|---|---|---|---|
| F-1 | Staff login OTP + phone written to `laravel.log` in plain text | `Auth/MobileOtpController.php:72` | 🔴 P0 |
| F-2 | Deploy starts new code before backup and migration | `deploy.sh:48` vs `:64`, `:78` | 🔴 P1 |
| F-3 | Raw exception messages (with SQL values = patient data) logged | e.g. `Api/V1/RelationshipController.php:524` | 🟠 P1 |
| F-4 | Patient names in push titles (through Google, on lock screens) | `ChairsideNotifier.php:52`, `:149` | 🟠 P1 |
| F-5 | App also served on `srv1791841.hstgr.cloud` | `/etc/caddy/Caddyfile` | 🟡 P2 |

### Signal Board — Phase 4 rows that must be reworded to match this design
- **4.3** "Users live in the clinic database" → identity is central, the `users` profile row is in the
  clinic; add identity, memberships, handoff and the mobile clinic-picker to its scope.
- **4.4** "a job middleware restores it" → a worker per clinic; the payload slug is only asserted.
- **4.5** "22 commands" → 24 measured; `tenants:run` spawns a child process per clinic.
- **4.6** "every cache key carries the tenant id" → cache store is per clinic DB; prefix only if a
  shared store is ever introduced. Done-when: `SESSION_DOMAIN` is null and no store is shared.
- **4.9** "come from the central database" → **copied into each clinic DB** (3.1 D-2); done-when adds
  "platform rows cannot be edited, clinic-added drugs survive a data release".
- **Missing rows to add:** 4.12 Entitlements (`pass:<code>` registered on routes, jobs, scheduler);
  4.13 N-clinic deploy/backup/restore runner with per-clinic MySQL users and backup keys (3.4);
  4.14 Per-clinic worker supervisor.

### Acceptance tests that Phase 6.2 must contain (from 3.1–3.5 and the 23 Sep brief)
Cross-clinic: patient read by id · invoice read by id · document/file by id and by path · search
results · export · job dispatched in A writes only in A · scheduler run per clinic · push/notification
recipients · token from A on B → 401 · unknown host → 404 with no DB query · suspended → 403 ·
`SESSION_DOMAIN` null · no default connection (pre-resolution DB call throws) · membership revoked →
tokens gone · add-on not entitled → 403 on web **and** API **and** its jobs skip · location
restriction inside a clinic · break-glass session writes both access logs and expires on time ·
migrate stops on first failure and leaves later clinics untouched · restore one clinic while others
serve.

### Sign-off
Approved by: ____________________ (Dr. Sumit, CEO)    Date: ____________
On signature: Signal Board row 3.6 → green; no code in Phase 4 starts before it.

---

## Appendix 3.1-A — every table (293, production schema, 23 Sep 2026)

| # | table | class | rows (prod est.) | how / note |
|---|---|---|---|---|
| 1 | `action_option_lists` | **CLINIC** | 107 | seeded from platform template at provisioning, then clinic-owned |
| 2 | `activities` | **CLINIC** | 13177 |  |
| 3 | `ai_action_logs` | **CLINIC** | 0 |  |
| 4 | `ai_conversations` | **CLINIC** | 0 |  |
| 5 | `ai_messages` | **CLINIC** | 0 |  |
| 6 | `analytics_snapshots` | **CLINIC** | 7 |  |
| 7 | `app_notifications` | **CLINIC** | 388 |  |
| 8 | `app_settings` | **CLINIC** | 57 |  |
| 9 | `appointment_cancellations` | **CLINIC** | 18 |  |
| 10 | `appointments` | **CLINIC** | 635 |  |
| 11 | `audit_logs` | **CLINIC** | 17933 |  |
| 12 | `automation_shadow_log` | **CLINIC** | 0 |  |
| 13 | `billing_audit_logs` | **CLINIC** | 59 |  |
| 14 | `billing_prompts` | **CLINIC** | 51 |  |
| 15 | `blog_categories` | **CLINIC** | 2 | clinic_id column becomes dead (always one value) — leave, drop later |
| 16 | `blog_post_seo` | **CLINIC** | 4 |  |
| 17 | `blog_post_tag` | **CLINIC** | 0 |  |
| 18 | `blog_post_versions` | **CLINIC** | 24 |  |
| 19 | `blog_posts` | **CLINIC** | 5 | clinic_id column becomes dead (always one value) — leave, drop later |
| 20 | `blog_publications` | **CLINIC** | 4 |  |
| 21 | `blog_tags` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 22 | `branch_settings` | **CLINIC** | 0 |  |
| 23 | `branches` | **CLINIC** | 1 | 1 row on prod (Main Clinic) |
| 24 | `brands` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 25 | `cache` | **CLINIC** | 15 | infra; CACHE_STORE=database on prod |
| 26 | `cache_locks` | **CLINIC** | 0 | infra; scheduler overlap locks |
| 27 | `case_consent_snapshots` | **CLINIC** | 0 |  |
| 28 | `case_selections` | **CLINIC** | 2 |  |
| 29 | `clinic_holidays` | **CLINIC** | 0 |  |
| 30 | `clinic_hours` | **CLINIC** | 0 |  |
| 31 | `clinical_files` | **CLINIC** | 4 |  |
| 32 | `clinical_findings` | **CLINIC** | 0 |  |
| 33 | `clinical_media` | **CLINIC** | 0 |  |
| 34 | `clinics` | **HQ** | 0 | sales CRM, 0 rows |
| 35 | `cms_edu_categories` | **GLOBAL** | 8 |  |
| 36 | `cms_edu_items` | **GLOBAL** | 0 | FK uploaded_by → users — drop the FK, keep as text (0 rows) |
| 37 | `cms_media` | **CLINIC** | 0 |  |
| 38 | `cms_tags` | **CLINIC** | 0 |  |
| 39 | `cms_treatment_cases` | **CLINIC** | 0 |  |
| 40 | `comm_activity_logs` | **CLINIC** | 38912 |  |
| 41 | `communication_queue` | **CLINIC** | 10591 |  |
| 42 | `complaints` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 43 | `consent_logs` | **CLINIC** | 0 |  |
| 44 | `consent_purposes` | **CLINIC** | 10 | seeded from platform template at provisioning, then clinic-owned |
| 45 | `consultation_coha_reports` | **CLINIC** | 0 |  |
| 46 | `consultation_photographs` | **CLINIC** | 0 |  |
| 47 | `consultation_scans` | **CLINIC** | 0 |  |
| 48 | `consultation_specialty_modules` | **CLINIC** | 6 |  |
| 49 | `consultations` | **CLINIC** | 130 |  |
| 50 | `coupon_codes` | **CLINIC** | 0 |  |
| 51 | `coupon_usage` | **CLINIC** | 0 |  |
| 52 | `data_breaches` | **CLINIC** | 0 |  |
| 53 | `data_requests` | **CLINIC** | 0 |  |
| 54 | `decision_tree_nodes` | **GLOBAL** | 6 | FK treatment_id → treatments (a CLINIC table) — see D-2 |
| 55 | `decision_trees` | **GLOBAL** | 0 |  |
| 56 | `dedup_candidates` | **CLINIC** | 0 |  |
| 57 | `dental_conditions` | **GLOBAL** | 0 |  |
| 58 | `device_tokens` | **CLINIC** | 4 | infra |
| 59 | `diagnoses` | **CLINIC** | 0 |  |
| 60 | `diagnosis_masters` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 61 | `diagnosis_treatment_options` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 62 | `doctor_blocked_slots` | **CLINIC** | 12 |  |
| 63 | `documentation_protocol_steps` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 64 | `documentation_protocols` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 65 | `education_categories` | **GLOBAL** | 0 |  |
| 66 | `education_media` | **GLOBAL** | 0 |  |
| 67 | `education_treatments` | **GLOBAL** | 0 |  |
| 68 | `emi_providers` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 69 | `emi_schedules` | **CLINIC** | 0 |  |
| 70 | `emi_schemes` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 71 | `escalations` | **CLINIC** | 0 |  |
| 72 | `facility_abdm_config` | **CLINIC** | 0 |  |
| 73 | `failed_jobs` | **CLINIC** | 0 | infra; payload + exception text carry PHI — see D-4 |
| 74 | `feature_flags` | **CLINIC** | 2 | clinic-admin toggles (web.php:423) — NOT entitlements |
| 75 | `fhir_documents` | **CLINIC** | 0 |  |
| 76 | `final_bills` | **CLINIC** | 251 |  |
| 77 | `finance_audit_log` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 78 | `finance_bank_accounts` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 79 | `finance_bank_transactions` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 80 | `finance_cashbook` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 81 | `finance_expense_categories` | **CLINIC** | 11 | clinic_id column becomes dead (always one value) — leave, drop later |
| 82 | `finance_expenses` | **CLINIC** | 25 | clinic_id column becomes dead (always one value) — leave, drop later |
| 83 | `finance_gst_records` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 84 | `finance_income_entries` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 85 | `finance_membership_plans` | **CLINIC** | 2 | clinic_id column becomes dead (always one value) — leave, drop later |
| 86 | `finance_patient_memberships` | **CLINIC** | 51 | clinic_id column becomes dead (always one value) — leave, drop later |
| 87 | `finance_payroll` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 88 | `finance_settings` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 89 | `finance_staff_advances` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 90 | `finance_transactions` | **CLINIC** | 291 | clinic_id column becomes dead (always one value) — leave, drop later |
| 91 | `finance_vendor_payments` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 92 | `finance_vendors` | **CLINIC** | 25 | clinic_id column becomes dead (always one value) — leave, drop later |
| 93 | `finance_vouchers` | **CLINIC** | 15 |  |
| 94 | `follow_up_notes` | **CLINIC** | 0 |  |
| 95 | `follow_ups` | **CLINIC** | 128 |  |
| 96 | `goods_receipt_notes` | **CLINIC** | 0 |  |
| 97 | `grn_items` | **CLINIC** | 0 |  |
| 98 | `hr_attendance` | **CLINIC** | 451 |  |
| 99 | `hr_bonuses` | **CLINIC** | 0 |  |
| 100 | `hr_departments` | **CLINIC** | 0 |  |
| 101 | `hr_entry_exit_logs` | **CLINIC** | 8 |  |
| 102 | `hr_incentive_rules` | **CLINIC** | 0 |  |
| 103 | `hr_performance_memos` | **CLINIC** | 0 |  |
| 104 | `hr_periodic_training_records` | **CLINIC** | 0 |  |
| 105 | `hr_periodic_training_requirements` | **CLINIC** | 0 |  |
| 106 | `hr_salary_components` | **CLINIC** | 0 |  |
| 107 | `hr_shifts` | **CLINIC** | 0 |  |
| 108 | `hr_staff_advances` | **CLINIC** | 0 |  |
| 109 | `hr_staff_documents` | **CLINIC** | 0 |  |
| 110 | `hr_staff_profiles` | **CLINIC** | 9 |  |
| 111 | `hr_staff_shifts` | **CLINIC** | 0 |  |
| 112 | `hr_training_enrollments` | **CLINIC** | 0 |  |
| 113 | `hr_training_sessions` | **CLINIC** | 0 |  |
| 114 | `huddle_boards` | **CLINIC** | 20 |  |
| 115 | `huddle_cards` | **CLINIC** | 0 |  |
| 116 | `huddle_comments` | **CLINIC** | 0 |  |
| 117 | `huddle_notes` | **CLINIC** | 0 |  |
| 118 | `huddle_settings` | **CLINIC** | 0 |  |
| 119 | `huddle_task_logs` | **CLINIC** | 23 |  |
| 120 | `implant_catalog` | **CLINIC** | 5 |  |
| 121 | `implant_placements` | **CLINIC** | 2 |  |
| 122 | `insight_signals` | **CLINIC** | 0 |  |
| 123 | `integration_shadow_log` | **CLINIC** | 0 |  |
| 124 | `inventory_categories` | **CLINIC** | 9 | seeded from platform template at provisioning, then clinic-owned |
| 125 | `inventory_items` | **CLINIC** | 118 |  |
| 126 | `inventory_locations` | **CLINIC** | 8 |  |
| 127 | `inventory_settings` | **CLINIC** | 6 | seeded from platform template at provisioning, then clinic-owned |
| 128 | `inventory_stocks` | **CLINIC** | 56 |  |
| 129 | `inventory_sub_types` | **CLINIC** | 7 | seeded from platform template at provisioning, then clinic-owned |
| 130 | `inventory_variants` | **CLINIC** | 4 |  |
| 131 | `inventory_vendors` | **CLINIC** | 18 |  |
| 132 | `investigation_masters` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 133 | `investigations` | **CLINIC** | 0 |  |
| 134 | `invoice_items` | **CLINIC** | 367 |  |
| 135 | `invoice_payments` | **CLINIC** | 272 |  |
| 136 | `invoices` | **CLINIC** | 281 |  |
| 137 | `job_batches` | **CLINIC** | 0 | infra; see D-4 |
| 138 | `jobs` | **CLINIC** | 2 | infra; payload per clinic — see D-4 |
| 139 | `journey_curations` | **CLINIC** | 0 |  |
| 140 | `journey_custom_options` | **CLINIC** | 0 |  |
| 141 | `journey_sent_snapshots` | **CLINIC** | 0 |  |
| 142 | `kb_block_media` | **GLOBAL** | 9 | FK media_asset_id → media_assets (a CLINIC table) — see D-2 |
| 143 | `kb_blocks` | **GLOBAL** | 33 |  |
| 144 | `kb_topic_relations` | **GLOBAL** | 0 |  |
| 145 | `kb_topics` | **GLOBAL** | 7 |  |
| 146 | `lab_case_attachments` | **CLINIC** | 2 |  |
| 147 | `lab_case_events` | **CLINIC** | 19 |  |
| 148 | `lab_case_items` | **CLINIC** | 1 |  |
| 149 | `lab_case_prescriptions` | **CLINIC** | 0 |  |
| 150 | `lab_case_ratings` | **CLINIC** | 0 |  |
| 151 | `lab_cases` | **CLINIC** | 6 |  |
| 152 | `lab_monthly_reconciliations` | **CLINIC** | 0 |  |
| 153 | `lab_prescription_templates` | **CLINIC** | 0 |  |
| 154 | `lab_reconciliation_events` | **CLINIC** | 0 |  |
| 155 | `lab_reconciliation_items` | **CLINIC** | 0 |  |
| 156 | `lab_vendor_contacts` | **CLINIC** | 0 |  |
| 157 | `lab_vendor_price_lists` | **CLINIC** | 0 |  |
| 158 | `lab_vendor_services` | **CLINIC** | 0 |  |
| 159 | `lab_vendors` | **CLINIC** | 2 |  |
| 160 | `lead_activities` | **CLINIC** | 48 |  |
| 161 | `leads` | **CLINIC** | 8 |  |
| 162 | `materials` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 163 | `media_assets` | **CLINIC** | 8 |  |
| 164 | `medical_conditions` | **GLOBAL** | 0 |  |
| 165 | `medicines` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 166 | `membership_benefit_logs` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 167 | `message_templates` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 168 | `migrations` | **CLINIC** | 421 | infra; every database keeps its own |
| 169 | `mkt_activity_log` | **CLINIC** | 9 | clinic_id column becomes dead (always one value) — leave, drop later |
| 170 | `mkt_asset_folders` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 171 | `mkt_asset_tag_map` | **CLINIC** | 0 |  |
| 172 | `mkt_asset_tags` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 173 | `mkt_assets` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 174 | `mkt_brand_kits` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 175 | `mkt_campaign_goals` | **CLINIC** | 0 |  |
| 176 | `mkt_campaign_team` | **CLINIC** | 0 |  |
| 177 | `mkt_campaigns` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 178 | `mkt_festival_dates` | **CLINIC** | 20 | seeded from platform template at provisioning, then clinic-owned |
| 179 | `mkt_idea_assets` | **CLINIC** | 0 |  |
| 180 | `mkt_ideas` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 181 | `mkt_platform_connections` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 182 | `mkt_post_media` | **CLINIC** | 0 |  |
| 183 | `mkt_post_schedules` | **CLINIC** | 0 |  |
| 184 | `mkt_post_variants` | **CLINIC** | 0 |  |
| 185 | `mkt_posts` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 186 | `mkt_settings` | **CLINIC** | 0 | clinic_id column becomes dead (always one value) — leave, drop later |
| 187 | `mobile_otps` | **HQ** | 0 | staff login OTP (phone, otp) — goes with identity |
| 188 | `modules` | **GLOBAL** | 17 | code catalog; must match the deployed code version |
| 189 | `notification_rules` | **CLINIC** | 236 |  |
| 190 | `operatories` | **CLINIC** | 2 | = chairs |
| 191 | `password_reset_pins` | **HQ** | 0 | goes with identity |
| 192 | `password_reset_tokens` | **HQ** | 0 | goes with identity |
| 193 | `patient_alerts` | **CLINIC** | 0 |  |
| 194 | `patient_allergies` | **CLINIC** | 0 |  |
| 195 | `patient_communications` | **CLINIC** | 0 |  |
| 196 | `patient_consents` | **CLINIC** | 0 |  |
| 197 | `patient_identifiers` | **CLINIC** | 0 |  |
| 198 | `patient_journeys` | **CLINIC** | 1 |  |
| 199 | `patient_links` | **CLINIC** | 19 |  |
| 200 | `patient_merges` | **CLINIC** | 1 |  |
| 201 | `patient_notes` | **CLINIC** | 0 |  |
| 202 | `patient_relationship_notes` | **CLINIC** | 5 |  |
| 203 | `patient_sources` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 204 | `patient_tag` | **CLINIC** | 0 |  |
| 205 | `patients` | **CLINIC** | 3397 |  |
| 206 | `personal_access_tokens` | **CLINIC** | 14 | infra; token format that names the clinic = row 3.2 |
| 207 | `plan_decision_items` | **CLINIC** | 0 |  |
| 208 | `plan_decisions` | **CLINIC** | 19 |  |
| 209 | `plans` | **HQ** | 10 | 10 rows |
| 210 | `practice_protocol_materials` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 211 | `practice_protocols` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 212 | `practitioner_identifiers` | **CLINIC** | 0 |  |
| 213 | `practitioner_qualifications` | **CLINIC** | 0 |  |
| 214 | `prescription_audit_logs` | **CLINIC** | 175 |  |
| 215 | `prescription_items` | **CLINIC** | 222 |  |
| 216 | `prescription_overrides` | **CLINIC** | 0 |  |
| 217 | `prescriptions` | **CLINIC** | 106 |  |
| 218 | `presentation_access_tokens` | **CLINIC** | 0 |  |
| 219 | `presentation_media_items` | **CLINIC** | 0 |  |
| 220 | `presentation_snapshots` | **CLINIC** | 0 |  |
| 221 | `presentations` | **CLINIC** | 0 |  |
| 222 | `processed_domain_events` | **CLINIC** | 0 | infra |
| 223 | `product_dealers` | **CLINIC** | 52 |  |
| 224 | `purchase_order_items` | **CLINIC** | 0 |  |
| 225 | `purchase_orders` | **CLINIC** | 0 |  |
| 226 | `receipts` | **CLINIC** | 277 |  |
| 227 | `referral_rewards` | **CLINIC** | 0 |  |
| 228 | `relationship_contact_log` | **CLINIC** | 3 |  |
| 229 | `relationship_journeys` | **CLINIC** | 17 |  |
| 230 | `relationship_merges` | **CLINIC** | 0 |  |
| 231 | `relationship_notifications` | **CLINIC** | 0 |  |
| 232 | `relationship_rule_logs` | **CLINIC** | 367 |  |
| 233 | `relationships` | **CLINIC** | 3504 |  |
| 234 | `retention_policies` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 235 | `reusable_assets` | **CLINIC** | 0 |  |
| 236 | `reviews` | **CLINIC** | 3 |  |
| 237 | `role_billing_permissions` | **CLINIC** | 25 | seeded default, clinic edits |
| 238 | `role_module_permissions` | **CLINIC** | 107 | seeded default, clinic edits |
| 239 | `roles` | **CLINIC** | 10 | clinic edits its grid (web.php:1213 RolePermissionController) |
| 240 | `rx_allergy_rules` | **GLOBAL** | 48 |  |
| 241 | `rx_dose_templates` | **GLOBAL** | 8 |  |
| 242 | `rx_drug_categories` | **GLOBAL** | 25 |  |
| 243 | `rx_drug_interaction_rules` | **GLOBAL** | 93 |  |
| 244 | `rx_drugs` | **GLOBAL** | 228 | platform rows read-only; RxDrugController store/update/destroy lets the clinic add its own — see D-3 |
| 245 | `rx_duration_templates` | **GLOBAL** | 8 |  |
| 246 | `rx_food_instructions` | **GLOBAL** | 6 |  |
| 247 | `rx_generics` | **GLOBAL** | 105 |  |
| 248 | `rx_routes_of_admin` | **GLOBAL** | 8 |  |
| 249 | `rx_template_items` | **CLINIC** | 67 | seeded from platform template at provisioning, then clinic-owned |
| 250 | `rx_templates` | **CLINIC** | 26 | seeded from platform template at provisioning, then clinic-owned |
| 251 | `rx_warning_rules` | **GLOBAL** | 66 |  |
| 252 | `search_index` | **CLINIC** | 0 |  |
| 253 | `sessions` | **CLINIC** | 4 | infra; SESSION_DRIVER=database on prod |
| 254 | `staff_activity_logs` | **CLINIC** | 0 |  |
| 255 | `stock_count_lines` | **CLINIC** | 0 |  |
| 256 | `stock_count_sessions` | **CLINIC** | 0 |  |
| 257 | `stock_movements` | **CLINIC** | 143 |  |
| 258 | `subscriptions` | **HQ** | 0 | 0 rows; clinic_id column becomes dead (always one value) — leave, drop later |
| 259 | `tags` | **CLINIC** | 0 |  |
| 260 | `task_outcomes` | **CLINIC** | 18 |  |
| 261 | `tasks` | **CLINIC** | 676 |  |
| 262 | `terminology_maps` | **GLOBAL** | 0 |  |
| 263 | `tickets` | **HQ** | 0 | 0 rows; clinic_id column becomes dead (always one value) — leave, drop later |
| 264 | `today_action_dismissals` | **CLINIC** | 291 |  |
| 265 | `today_actions` | **CLINIC** | 130 |  |
| 266 | `treatment_categories` | **CLINIC** | 12 | seeded from platform template at provisioning, then clinic-owned |
| 267 | `treatment_consents` | **CLINIC** | 11 |  |
| 268 | `treatment_knowledge` | **GLOBAL** | 5 |  |
| 269 | `treatment_media` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 270 | `treatment_opportunities` | **CLINIC** | 60 |  |
| 271 | `treatment_options` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
| 272 | `treatment_plan_item_teeth` | **CLINIC** | 10 |  |
| 273 | `treatment_plan_items` | **CLINIC** | 242 |  |
| 274 | `treatment_plans` | **CLINIC** | 73 |  |
| 275 | `treatment_rules` | **CLINIC** | 165 | seeded from platform template at provisioning, then clinic-owned |
| 276 | `treatment_sops` | **CLINIC** | 54 | seeded from platform template at provisioning, then clinic-owned |
| 277 | `treatment_types` | **CLINIC** | 10 | seeded from platform template at provisioning, then clinic-owned |
| 278 | `treatment_visit_items` | **CLINIC** | 37 |  |
| 279 | `treatment_visits` | **CLINIC** | 104 |  |
| 280 | `treatments` | **CLINIC** | 54 | seeded from platform template at provisioning, then clinic-owned |
| 281 | `users` | **CLINIC** | 12 | profile stays (130 FKs point here); login columns email/phone/password/two_factor_*/remember_token move to HQ identity; is_superadmin removed (3.5) |
| 282 | `vendor_invoice_items` | **CLINIC** | 0 |  |
| 283 | `vendor_invoices` | **CLINIC** | 0 |  |
| 284 | `voice_notes` | **CLINIC** | 0 |  |
| 285 | `wa_messages` | **CLINIC** | 0 |  |
| 286 | `wa_threads` | **CLINIC** | 102 |  |
| 287 | `wallet_campaigns` | **CLINIC** | 0 |  |
| 288 | `wallet_transactions` | **CLINIC** | 23 |  |
| 289 | `wallets` | **CLINIC** | 225 |  |
| 290 | `workflow_instances` | **CLINIC** | 0 |  |
| 291 | `workflow_shadow_log` | **CLINIC** | 0 |  |
| 292 | `workflow_step_log` | **CLINIC** | 0 |  |
| 293 | `workflow_templates` | **CLINIC** | 0 | seeded from platform template at provisioning, then clinic-owned |
