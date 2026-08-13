# Marketing Engine — Connecting Google Business Profile, Meta & Website

Prepared 2026-07-17. End-to-end setup path for going live with the Marketing module's
external channel connections. Companion to `docs/marketing-module-technical-dossier.md`.

---

## Read this first — the honest picture

**The code is already built.** `OAuthService` + `IntegrationEngine` handle Meta (Facebook/
Instagram) and Google (Business Profile + Analytics) OAuth end-to-end — connect, callback,
encrypted token storage, health-check, disconnect — all gated behind feature flags. WordPress
is wired via an app-password form. Nothing about "connecting" requires new code beyond the
version fix already applied (see §6).

**What actually stands between you and live connections is external, not code:**

1. **Credentials** — you need developer apps on Meta and Google, and their App ID/secret in `.env`.
2. **Approvals** — two of the three channels have *gated approval steps that take days to weeks*.
3. **Consent flow** — a one-time click-through per channel inside `/marketing/integrations`.

**This is not a same-day job.** Two hard external dependencies dominate the timeline:

| Channel | Gated approval? | Realistic lead time |
|---|---|---|
| Google Business Profile | **Yes** — Google must whitelist your Cloud project for the Business Profile API | **7–10 business days, often 2–4 weeks** |
| Meta (FB + Instagram) | **Yes** — App Review for publishing permissions | **1–3 weeks**, longer if business verification is needed |
| Website (WordPress) | No | **~10 minutes** |

My recommendation on sequencing (highest ROI, least friction first): **do WordPress today,
submit the Google and Meta applications today so their review clocks start, and treat the
actual in-app connection as a follow-up once approvals land.** Don't block the quick win on the
slow ones.

---

## 0. Prerequisites checklist

Before you start, confirm you have:

- [ ] A **live business website** (Google and Meta both check this). If your public site is the
      one you want to connect as "Website," note its URL.
- [ ] A **verified Google Business Profile** that has been **active 60+ days** (Google's rule for
      API access — a brand-new listing will be rejected).
- [ ] A **Facebook Page** for the clinic (not just a personal profile) and, if you want Instagram,
      an **Instagram Business/Creator account linked to that Page**.
- [ ] Admin access to the Dentfluence server's `.env` (VPS is Docker at `/opt/dentfluence`,
      env file `.env.production` — see `reference_vps_deploy_gotchas`).
- [ ] An Admin login to Dentfluence (to flip feature flags and run the connect flow).

---

## 1. Website (WordPress) — do this first, ~10 minutes

Only applies if your public website runs WordPress. If it's Wix/Squarespace/custom, there's no
WordPress endpoint to connect and this channel doesn't apply — tell me and we'll look at what
your site actually is.

1. Log into WordPress admin → **Users → Profile** (the account you'll post as; ideally a dedicated
   "Dentfluence" editor account, not your personal admin).
2. Scroll to **Application Passwords**, name it `Dentfluence Marketing`, click **Add New**.
3. Copy the generated password (spaces and all — it's shown once).
4. In Dentfluence: **Marketing → Integrations → WordPress → Setup**, enter:
   - Site URL (e.g. `https://yourclinic.com`)
   - Username (the WordPress user)
   - Application password (paste it)
5. Save. The app immediately test-pings `wp-json/wp/v2/users/me`; a green "connected" means it worked.

No `.env` change and no feature flag needed for WordPress — it stores per-clinic in the DB.

---

## 2. Meta (Facebook + Instagram)

### 2a. Create the Meta app

1. Go to <https://developers.facebook.com/apps> → **Create App**.
2. App type: **Business**. Attach it to your clinic's **Meta Business Portfolio** (create one if you
   don't have it — this is also where business verification happens).
3. Add products: **Facebook Login** (for OAuth) and **Instagram** (Instagram Graph API).
4. Under **App settings → Basic**, copy the **App ID** and **App Secret**.
5. Under **Facebook Login → Settings**, add the **Valid OAuth Redirect URI** — this must match
   exactly what the app generates:
   ```
   https://YOUR-DENTFLUENCE-DOMAIN/marketing/integrations/facebook/callback
   https://YOUR-DENTFLUENCE-DOMAIN/marketing/integrations/instagram/callback
   ```

### 2b. Permissions you'll request (and must justify in App Review)

The code requests these scopes:

- **Facebook Page posting:** `pages_manage_posts`, `pages_read_engagement`, `pages_show_list`
- **Instagram publishing:** `instagram_basic`, `instagram_content_publish`, `pages_read_engagement`

Everything beyond `public_profile`/`email` needs **App Review**. Two things matter for approval:

- **Business verification** of your Meta portfolio (upload business docs).
- **A per-permission written justification + a screencast** showing the exact user flow. Vague
  reasons ("improve experience") get rejected. Show: admin opens Marketing → Integrations, clicks
  Connect Facebook, authorises, composes a post, publishes to the clinic's own Page.

> **Shortcut worth knowing:** while your app is in **Development mode**, it can already post to
> Pages/IG accounts owned by users who are **Admins/Developers/Testers of the app**. For a single
> clinic connecting *its own* Page, you can test and even operate in dev mode before full App
> Review — Review is what lets you connect *other people's* accounts (i.e. when you sell this as
> SaaS to other clinics). So: **submit for review now for the SaaS future, but you can connect your
> own clinic immediately in dev mode.**

### 2c. Wire credentials + flip the flag

Add to `.env` (`.env.production` on the VPS):
```env
META_APP_ID=your_app_id
META_APP_SECRET=your_app_secret
# Optional — pin the Graph API version. Defaults to v23.0 if unset.
META_GRAPH_VERSION=v23.0
```
Then rebuild config cache (`php artisan config:clear` — on Docker VPS, inside the app container;
see deploy runbook). Flip the flag: **Settings → Cross-App Flags → `integration.meta` → on**
(or leave off to use the legacy direct path — both work; the flag routes through IntegrationEngine).

### 2d. Connect

**Marketing → Integrations → Connect** for Facebook, then Instagram. Each redirects to Meta's
consent screen, returns, and stores an encrypted token. Green = connected.

---

## 3. Google Business Profile (+ optional Analytics)

This is the slowest channel because of the API access gate. **Start it today so the clock runs.**

### 3a. Google Cloud project + OAuth client

1. <https://console.cloud.google.com> → create a project (e.g. `dentfluence-marketing`).
2. **APIs & Services → OAuth consent screen**: External, fill app name, support email, your
   business domain, and add the scopes below. This screen needs **verification** for the sensitive
   `business.manage` scope — start it early.
3. **APIs & Services → Credentials → Create OAuth client ID → Web application.** Add the redirect URIs:
   ```
   https://YOUR-DENTFLUENCE-DOMAIN/marketing/integrations/google_business/callback
   https://YOUR-DENTFLUENCE-DOMAIN/marketing/integrations/google_analytics/callback
   ```
   Copy the **Client ID** and **Client Secret**.

### 3b. Request Business Profile API access (the gate)

1. Submit the access form: <https://support.google.com/business/contact/api_default> → choose
   **"Application for Basic API Access."** You'll need your GCP **project number**, business
   details, and your live website URL.
2. Google reviews in a stated 7–10 business days (often longer). You're approved when the
   **Business Profile API quota flips from 0 QPM to 300 QPM** in the Cloud Console.
3. After approval, **enable each of the Business Profile APIs** you need in **APIs & Services →
   Library** (they're off by default even once whitelisted). For Analytics, also enable the
   **Google Analytics Data API**.

### 3c. Wire credentials + flip the flag

```env
GOOGLE_CLIENT_ID=your_client_id
GOOGLE_CLIENT_SECRET=your_client_secret
```
Flip **`integration.google`** in Settings → Cross-App Flags (same on/off legacy-vs-connector
behaviour as Meta).

### 3d. Connect

**Marketing → Integrations → Connect** for Google Business (and Google Analytics if wanted).
The `business.manage` scope requests offline access, so a refresh token is stored — connections
survive token expiry via the health-check/refresh path.

---

## 4. Redirect-URI cheat sheet

Every redirect URI registered on Meta/Google must **exactly** match `{platform}` in the callback
route. The platform keys the code uses:

| Channel | `{platform}` key | Callback URL to register |
|---|---|---|
| Facebook | `facebook` | `/marketing/integrations/facebook/callback` |
| Instagram | `instagram` | `/marketing/integrations/instagram/callback` |
| Google Business Profile | `google_business` | `/marketing/integrations/google_business/callback` |
| Google Analytics | `google_analytics` | `/marketing/integrations/google_analytics/callback` |
| WordPress | `wordpress` | (no OAuth — app-password form) |
| WhatsApp | `whatsapp` | (no OAuth — static token form) |

A mismatch here is the #1 cause of "connection failed" — the domain, scheme (https), and path
must be identical to what's registered.

---

## 5. Verifying a live connection

- **In-app:** Integrations page shows status per channel; the **Health-check** button re-pings the
  live API and updates status (connected / expired / error).
- **First real post:** compose in **Marketing → Publish**, target one channel, schedule for a
  minute out. `ProcessScheduledPost` fires and either posts or writes the API error back to the
  post — check `mkt_activity_log` and the queue worker (`docker compose ps` — the worker
  crash-loops silently if misconfigured, per the VPS deploy notes).

---

## 6. Code change applied 2026-07-17 (version bug fix)

The connectors targeted **Graph API v19.0, which Meta has expired** — any live call would have
returned an error. Fixed by centralising the version into one config value and bumping it:

- `config/services.php` → new `services.meta.graph_version` (env `META_GRAPH_VERSION`, default `v23.0`).
- `MetaConnector`, `OAuthService`, `ProcessScheduledPost`, `MetaLeadController` now all read that one value.

To migrate versions in future, change the single `.env` value — no code edit. Meta expires each
version ~2 years after release, so revisit roughly annually.

> Untested in this pass (no PHP runtime in the authoring environment). Verify with
> `php artisan config:clear` then a health-check once credentials are in.

---

## 7. What I'd do next, in order

1. **Today:** WordPress connect (10 min). Submit the Google Business Profile access form and the
   Meta App Review — both clocks start now.
2. **Today, dev mode:** flip `integration.meta` off/legacy, add Meta creds, connect your *own*
   clinic Page + IG in Meta Development mode — you don't need to wait for App Review for your own
   accounts.
3. **On Google approval (~1–4 wks):** add Google creds, enable the APIs, connect Business Profile.
4. **On Meta App Review approval:** you're cleared to connect *other* clinics' accounts — relevant
   only when you sell this module as SaaS.

Reminder from the re-engineering plan: `CLINIC_ID` is still hardcoded to `1` in a couple of
Marketing controllers. That's harmless for your single clinic today but **must be fixed before a
second clinic connects its own accounts** — otherwise tokens could cross clinics. Flag for when
SaaS onboarding begins, not now.
