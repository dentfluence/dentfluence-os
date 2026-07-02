# Dentfluence — Deploy Readiness Plan

The road from "built on my PC" to "live on a VPS with real patient data."
Work top to bottom. **Buy the VPS only after Phase 4 passes** — everything
before that happens on your own machine, for free, with zero risk.

Legend: 🟢 done · 🟡 in progress · ⚪ not started
Who: **You** = runs terminal/Laravel commands · **Claude** = prepares/guides/fixes code

---

## Phase 0 — Deployment kit  🟢 DONE
*Package the app so it can run on any server.*

- 🟢 Dockerfile, docker-compose, nginx/php/entrypoint configs
- 🟢 Hardened `.env.production` (debug off, non-root DB, secure cookies, AI off)
- 🟢 `deploy.sh`, `backup.sh`, `DEPLOY.md` runbook

**Gate:** all files exist in the project. ✅ Passed.

---

## Phase 1 — Green the test suite  🟢 DONE
*The automated safety net must be green before real money/data flows.*

- 🟢 **You:** ran `php artisan test` (2026-06-28)
- 🟢 Result: **13 of 14 real tests passed**, including all money/workflow paths —
  payment→receipt→ledger→invoice-paid, inventory create/stock-in, PO→GRN→payable,
  lab auto-close, recall engine, appointment status flow. The old "6 failing
  tests" warning was STALE — they pass now.
- 🟢 **Claude:** the one failure was Laravel's boilerplate `ExampleTest` (expected
  `/` to return 200, but the app correctly redirects guests to login → 302).
  Fixed the assertion to expect the redirect.
- ⚪ **You:** re-run `php artisan test` once to confirm 100% green

**Gate:** `php artisan test` fully green. ✅ Effectively passed (one re-run to confirm).

---

## Phase 2 — Verify the security layer  🟢 DONE
*The security code is built; now prove it actually works.*

- 🟢 **You:** ran `php artisan security:selftest` — **GREEN** (2026-06-28)
- 🟢 Confirms MFA, PHI encryption, tamper-evident audit chain, security headers,
  and login throttling are working
- 🟢 **Claude:** `SecureWebHeaders` confirmed applied to the global `web` group
- ⚪ **You (recommended, on the live site):** quick manual smoke test once deployed
  — log in, trigger 2FA, confirm lockout after 5 bad logins

**Gate:** `security:selftest` green. ✅ Passed.

---

## Phase 3 — Pre-flight cleanup & config check  🟡 (almost done)
*Remove cruft and confirm production config is internally consistent.*

- 🟢 **Claude:** `route:cache` safe — no route closures found
- 🟢 **Claude:** `config:cache` safe — only `env()` outside config is in a dev-only
  command + Windows `getenv()` paths (both harmless / not used in prod)
- 🟢 **Claude:** scheduler commands verified real (`recall:run`, `lab:create-overdue-tasks`)
- 🟢 **Claude:** `.env.production` keys reviewed against code
- 🟢 **Claude:** **fixed** reverse-proxy trust (`trustProxies` in bootstrap/app.php)
  so HTTPS works correctly behind Caddy — this was missing and would have caused
  http:// links + cookie issues on the live site
- ⚪ **Claude:** delete the 2 stray zero-byte files (`canAccess('practice_protocols')]`,
  `toArray()`) — *waiting on your OK*

**Gate:** clean repo, caches build without error. (Only the file deletion remains.)

---

## Phase 4 — Local Docker dry-run  ⚪  ← *the big de-risk*
*Run the FULL Docker stack on your Windows PC before spending a rupee on a VPS.*

- ⚪ **You:** install Docker Desktop for Windows
- ⚪ **You:** `docker compose --env-file .env.production up -d --build`
  (with a local test `.env.production` — fake passwords, `APP_URL=http://localhost:8080`)
- ⚪ **Claude:** fix any build/runtime errors (extension missing, path issue, etc.)
- ⚪ **You:** open `http://localhost:8080`, log in, click through the core loop
  (register patient → consult → plan → prescribe → bill)
- ⚪ **You:** run migrations inside the container, confirm DB works

**Gate:** the whole stack builds and the core loop works locally in Docker.
**Why this matters:** if it runs here, it'll run on the VPS. This catches 90%
of "it broke on the server" problems for free.

---

## ★ DECISION POINT — Buy the VPS
*Only now.* Order **Hostinger KVM 2** (2 vCPU / 8 GB, Ubuntu 24.04, India region).
Have ready: server IP, root password, and your domain name.

---

## Phase 5 — Shift to the VPS (we do this together)  ⚪
*Follow `DEPLOY.md`, one command at a time.*

- ⚪ Point domain (DNS A record) → server IP
- ⚪ Harden server (non-root user, firewall)
- ⚪ Install Docker
- ⚪ Copy code up + create real `.env.production` (real passwords on the server)
- ⚪ `./deploy.sh` — first launch
- ⚪ Caddy → HTTPS certificate
- ⚪ Confirm core loop works on the live domain

**Gate:** `https://app.dentfluence.com` loads with a padlock and you can log in.

---

## Phase 6 — Post-launch safety  ⚪
*Things that must be true within day 1 of going live.*

- ⚪ Daily backup cron enabled
- ⚪ **Restore tested once** (prove the backup works)
- ⚪ Copy backups off-server (S3 / another machine)
- ⚪ Send a real test email (forgot-password) via Brevo
- ⚪ Decide: pilot with 1 clinic before opening to more

**Gate:** a tested restore + a real email delivered.

---

## Quick status summary

| Phase | What | Status |
|-------|------|--------|
| 0 | Deployment kit | 🟢 Done |
| 1 | Green the tests | 🟢 Done (re-run to confirm) |
| 2 | Verify security | 🟢 Done |
| 3 | Cleanup & config | 🟡 Almost (just file deletion) |
| 4 | Local Docker dry-run | ⚪ **← buy VPS after this** |
| 5 | Shift to VPS | ⚪ |
| 6 | Post-launch safety | ⚪ |

**Your immediate next step:** Phase 1 — run `php artisan test` and send me what
fails. I'll fix them. We don't touch the VPS until Phase 4 is green.
