# Phase B — Kickoff Context (handoff)

**Created:** 2026-06-29 · Paste this (or point the new chat at this file) to start Phase B with full context.

---

## Project basics

- **App:** Dentfluence — Laravel dental clinic management system. Stack: Laravel 13, MySQL (Laragon), Blade, Bootstrap/Tailwind (check existing files first), Alpine.js.
- **Location:** `E:\Dentfluence\Dentfluence_OS\Dentfluence Web` (folder name has a space — quote paths). Mobile app at `..\dentfluence_mobile` (Flutter). DB runs in Laragon.
- **Builder:** Sumit — solo, non-developer-friendly. Explain in simple language. Do pre-flight size/truncation checks on big tasks and offer to split. Sumit runs all `php artisan` / terminal / composer commands himself at the end of a session (sandbox has no PHP).
- **Conventions:** follow Laravel MVC/Eloquent/Blade; build migration + model + controller + routes + view together; never delete files without asking; ask before destructive DB commands (migrate:fresh/rollback); keep code clean + commented.
- **Gotchas:** write to the E: path (not C:); the bash sandbox can show stale/truncated copies of just-written files — trust the Read tool / `php artisan` over bash greps. Use `Js::from` not `@json` inside Alpine. Z-index: topbar=120, sidebar=130, overlay=110, page sticky headers ≤100.

## Where Phase A landed (just finished, 2026-06-29)

Phase A (security hardening) is **CODE-COMPLETE and self-test-verified**. Built: private clinical files (SecureMediaController), PHI encryption at rest (app/Casts/Encrypted + EncryptedArray on Patient/PatientIdentifier/FinanceBankAccount/HrStaffProfile/Consultation), Sanctum token expiry + scoped abilities + logout-all, role-gated clinical API writes, tamper-evident audit logs (app/Traits/HashChained + `php artisan audit:verify`), security event logging (AuditLog::event login/logout/failed/record-view/role-change), login throttling, centralized password policy (Password::defaults), absolute session timeout, force-HTTPS + security headers (config/security.php + SecureWebHeaders), branch isolation (app/Traits/BelongsToBranch + Scopes/BranchScope, no-op under single-login admin), and MFA/TOTP (pragmarx/google2fa, TwoFactorController, /two-factor/setup + challenge). Verify anytime: `php artisan security:selftest`. Audit doc: `docs/security/phase-a-audit.md`.

**Note for Phase B:** the app is currently **single-login (admin only)**; role helpers exist but everyone is admin today (role-gating + branch scope are no-ops until per-staff logins activate in "Phase 12").

## Phase B — scope (from docs/plan-build-timeline.md)

**Goal:** close the ~5 matrix rows Eka bundles natively (comms, WhatsApp, reviews) before launch. Target window Sep–Oct 2026.

| ID | Item | Status going in |
|----|------|-----------------|
| 1.1 | Communication OS — 4 engines + unified inbox | 🟡 partial (lots already built) |
| 1.2 | WhatsApp messaging (India's primary channel) | 🟡 |
| 2.4 | Reputation / reviews management | 🟡 |
| 4.7 | Marketing Engine — mature; tie to reviews + recall | 🟡 |
| 1.5 | Voice notes — extend beyond Phase 1 (dental-native scribe) | 🟡 |
| 1.6 | Lab module v2 — remaining phases | 🟡 |

**Exit criteria:** two-way patient messaging live (WhatsApp + reminders) · reviews loop working · lab v2 usable.

## Existing work to read BEFORE building (don't rebuild)

The Communication OS is substantially built already. Relevant existing pieces:

- **Architecture:** 4 engines (Recall, Opportunity, Inbound/Leads, B2B) → 1 unified inbox. 5-phase build plan.
- **Routes/config:** `routes/communication.php`, `routes/prm.php`, `routes/tags-routes.php`, `routes/timeline.php`; `config/communication.php`, `config/prm.php`, `config/followup_rules.php`, `config/followup_settings.php`. Middleware: `communication.access` (CommunicationModuleAccess), `marketing.active` (EnsureMarketingActive).
- **Inbound webhooks already exist** (PRM Phase 4, public + secret-protected) in `routes/api.php`: `/api/webhooks/prm/website-lead`, `/meta-lead`, `/whatsapp` (WhatsApp Cloud API verify+receive), `/chatbot`. So WhatsApp INBOUND scaffolding exists — Phase B likely needs the OUTBOUND/two-way + templates side.
- **Docs:** `docs/communication-os-assessment.md`, `docs/communication-os-data-consolidation-plan.md`, `docs/communication-os-style-standard.md`, `docs/automation-map.md`, `docs/plan-prm-ai.md` (Boxly-style AI/automation roadmap, additive, 7 phases, UNSTARTED), `docs/plan-build-timeline.md` (master), `docs/plan-os-feature-roadmap.md`.
- **Voice notes:** Phase 1 done (local AI: faster-whisper GPU + Ollama llama3.1:8b → transcript → clinical notes; polymorphic). Phase B item 1.5 = extend it.
- **Lab module v2:** enterprise rebuild, Phase 1 (migrations + models) done; remaining phases for item 1.6. Note: `LabCase` now has the BelongsToBranch scope (Phase A).

## Suggested first step in the new chat

1. Read `docs/plan-build-timeline.md` (Phase B section) + `docs/communication-os-assessment.md` to get the true current state.
2. Pick ONE item to start — recommended **1.2 WhatsApp two-way messaging** (highest-leverage, India-primary; inbound webhook already exists, so the gap is outbound send + templates + threading into the unified inbox). Confirm with Sumit before building.
3. Do a pre-flight (size/truncation) and ask clarifying questions before writing code.

## Deployment context — LOCAL-FIRST until VPS

Everything is built and run **locally on Laragon for now**; a VPS move comes later. Implications for Phase B:

- **Outbound works locally:** sending WhatsApp/SMS/email via provider APIs is fine from localhost (outbound internet is allowed).
- **Inbound webhooks do NOT reach localhost:** WhatsApp Cloud API receive, Meta lead callbacks, etc. need a public HTTPS URL. While local, either (a) use a tunnel (ngrok / Cloudflare Tunnel) to test inbound, or (b) build + queue the inbound handlers and defer live webhook testing to the VPS. The inbound webhook routes already exist in `routes/api.php`.
- **Keep it `.env`-driven:** all base URLs, provider keys, webhook secrets, queue/mail drivers in `.env` so the VPS switch is config-only, no code changes.
- **HTTPS:** `config/security.php` force_https/HSTS auto-activate only in production, so local stays on http — no action needed.
- Deployment kit already exists (Docker Compose) — see [[project_deploy_docker]] / `docs/deploy`. VPS not yet purchased.

## Compliance reminders (carry forward)

- **DPDP** consent module is built (Wave 5.1+5.6, hash-chained consent_logs). Any new patient messaging must respect consent (consent-gate marketing/recall sends). DPDP enforcement deadline 13 May 2027.
- Keep new outbound messaging logged (reuse the tamper-evident AuditLog where it makes sense).
