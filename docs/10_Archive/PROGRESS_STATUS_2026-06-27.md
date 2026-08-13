# Dentfluence OS (Web) — Progress Status
As of: 27 June 2026 · Verified against: code in this workspace

## What this project is
A Laravel 13 / MySQL dental-clinic management web app (patients, appointments, consultations, treatment plans, prescriptions, lab, inventory, finance/wallet, HR, marketing, communication/PRM, plus a local-AI assistant) running on Laragon.

## Real completion: ~70%
Read as "works in the local dev environment for the core clinic loop," **not** "tested and safe to run on a public server." The product surface is large and mostly renders; the automated-test safety net is thin and partly red; deployment readiness is effectively 0%. Those are three different numbers and only the first is high.

The honest split:
- **Feature surface built:** ~90% (matches the MASTER brief). 110 controllers, 176 models, 60 services, 267 migrations.
- **Verified working (tested end-to-end or by passing automated test):** ~70%.
- **Production-deployment ready:** ~0–5%.

## What actually works (verified end-to-end)
- **Auth / login** — Sanctum-based, Dusk login test exists; the route crawler ran the whole app logged-in (332 OK / 178 pages returning HTTP 200).
- **Patient registration** — the most-used daily action. Dusk test (`DailyClinicAddPatientTest`) drives the real Add-Patient modal, saves, and confirms the DB write. Previously confirmed working in live use at Tulip.
- **Membership enrolment with finance chain** — `MembershipBenefitService::enrollWithFinance`; confirmed working in prior sessions.
- **Passing automated feature tests (last run):** treatment create → draft SOP, inventory PO → GRN → payable (receive), settings master create, marketing idea → campaign convert, HR auto-absent, task → assignee notification. These six pass.
- **Page rendering across the app** — June 22 route crawl: 178 pages returned 200, only 2 returned 500. So almost nothing throws on load. (Caveat: "renders" ≠ "the feature's logic is correct.")

## What's stubbed / fake / incomplete / untested
- **Six core feature tests are currently FAILING** (per `.phpunit.result.cache`, last run): **finance payment → receipt → ledger → mark-invoice-paid**, inventory item create, inventory stock-in movement, lab-case auto-close, recall-engine-queues-recall, and appointment status flow/revert. These are exactly the money- and workflow-critical paths. They may be stale or environment/factory drift rather than true regressions — but as it stands, the automated check for "record a payment" is red. This needs to be re-run and made green before launch.
- **The whole clinical workflow's end-to-end Dusk suite is unverified in this environment** — invoice, lab case, notes, prescription, treatment plan, treatment visit, wallet, documents, communication all have Dusk tests, but they require a browser + PHP runtime I can't execute here. Their last real pass/fail state is unconfirmed from code alone.
- **Automated coverage is thin for the size of the app** — ~13 feature tests + ~20 Dusk tests against 110 controllers. Most modules (HR, marketing, PRM, CMS/clinical library, communication engines, finance reports/analytics) have no automated test asserting their logic is correct — only that the page loads.
- **AI assistant "Tulip", voice notes, receipt/patient scan, vision** — depend on local Ollama + faster-whisper + GPU on the dev machine. They are real code but environment-bound; they will not function on a generic server without that stack and won't have been exercised by the route crawler.
- **Mail-dependent features** (notifications, recall, password reset) — `MAIL_MAILER=log`, so nothing actually sends. Queue is `database`, so recall/notification jobs only fire if a queue worker is running.
- **Mobile app** — out of scope here; MASTER brief notes zero `.dart` files found in the connected workspace.

## Single biggest blocker to going live
**There is no production environment and no production-safe configuration.** The app is configured purely for local dev: `APP_ENV=local`, `APP_DEBUG=true` (will leak stack traces and secrets to the public), DB as `root`, mail to `log`, AWS bucket empty, no SSL. Shipping this config to a public server with real patient data would be both broken (no email, debug exposed) and a serious data-protection risk. Closely behind it: the failing finance/payment automated test must be re-run and made green, because that's the one path you cannot get wrong.

## Deployment readiness (launch-critical)
- **Hosting:** None. Runs on Laragon at `dentfluence.test` / `localhost`. No server, Dockerfile, CI, nginx, or deploy script anywhere in the repo.
- **HTTPS/SSL:** None. `APP_URL=http://localhost`.
- **Automated backups + tested restore:** None. "backup" in the git log = git snapshots only. No `spatie/laravel-backup` or any DB-dump/restore job. No restore has been tested.
- **Rollback plan:** None in code. Migrations exist but `migrate:fresh`/rollback are flagged destructive in project rules; no blue-green or release-tagging strategy present.

## Launch-critical for July 1?
**Yes — this is the launch, and on deployment grounds it is not ready.** The product can plausibly carry the daily clinic loop (login → register patient → consult → plan → prescribe → bill) in the Tulip dev setup, but to put it on a public server with real patient data the following must be true and currently are not:
1. A real host provisioned, with `APP_ENV=production` and `APP_DEBUG=false`.
2. Valid HTTPS/TLS certificate.
3. Non-root DB credentials and rotated `APP_KEY` / secrets out of `.env` committed history.
4. A real mail transport (so reset/recall/notifications actually send) and a running queue worker.
5. Automated daily DB + file backups **with a restore you have actually tested once**.
6. A rollback path (tagged release + known-good DB snapshot).
7. The six failing feature tests re-run and green — especially payment → ledger → invoice-paid.

Realistically, items 1–6 are infra work that hasn't started; this is a deploy gap, not a product gap.

## Notes / open questions
- The `.phpunit.result.cache` is the best hard evidence I have, but it reflects the *last* run, which may predate fixes. **Action:** re-run `php artisan test` and the Dusk suite locally and trust that result over this file.
- I could not execute PHP, MySQL, or a browser in this environment, so all "tested working" claims for the Dusk clinical flows rest on the test files existing + your prior live use, not a fresh run.
- Two stray zero-byte files in the repo root (`canAccess('practice_protocols')]` and `toArray()`) look like accidental shell-redirect artifacts — harmless but worth deleting.
- AI/voice/vision features are tied to a local GPU + Ollama; decide before launch whether they ship in v1 or are gated off in production.
- The MASTER brief's "~90% complete" is fair for *features built*; this report's lower number is the *tested + deployable* view you asked for.
