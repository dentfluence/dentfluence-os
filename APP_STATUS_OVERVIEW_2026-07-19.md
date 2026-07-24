# Dentfluence — Full App Status Overview

**Generated:** 2026-07-19
**Branch:** `main` @ `703393a` (local == origin/main; everything committed is pushed)
**Live VPS:** Hostinger KVM 2 — Docker at `/opt/dentfluence`, served at `https://srv1791841.hstgr.cloud`
**Local:** Laragon at `C:\laragon\www\dentfluence` (DB stays in Laragon MySQL)
**Scale:** ~1,200 web+API routes · 183 Eloquent models · 408 migrations · 40+ service classes · 7 first-class `app/Modules`

This is a two-layer document. **Part A** is the honest engineering truth (what is solid, what is code-complete-but-untested, what is fragile). **Part B** is the cleaner commercial summary. Read Part A to decide what to work on; use Part B when talking to anyone outside the build.

---

# PART A — Engineering Truth

## 1. The one thing to understand first: the engine is built, the switches are off

Dentfluence has a genuinely strong backbone — an `ActivityEngine` / `RulesEngine` event spine, a `CommunicationEngine` + `CommunicationGuard` (consent/8-factor), a `WorkflowEngine`, an `AutomationEngine`, `TaskEngine`, `RelationshipEngine`, `IdentityResolver`, and more. These are described in the internal production-readiness review as "fail-safe and well-designed." **But most of them are gated behind feature flags that are still OFF**, so the app largely runs on the older legacy paths while the new engines run in shadow or lie dormant.

Feature-flag reality today (`config/features.php`):

| Flag | State | Meaning |
|---|---|---|
| `identity.link_patient` | **ON** | New patients auto-link to a Master Relationship (turned on 2026-07-04). |
| `guard.consent_required` | **ON in production** (env) | Consent enforced on sends; can never be overridden by urgency. |
| `automation.engine`, `rules.single_engine` | OFF | Recall/reminders still on legacy path; new engine runs in shadow. |
| `today.projection`, `tasks.human_system_split` | OFF | Today's Actions still read live across 12 domains. |
| `comm.single_gateway`, `guard.full_8factor`, `notifications.single_store` | OFF | Sends not yet all routed through one gateway. |
| `workflow.engine`, `marketing.via_guard` | OFF | Workflow engine dormant. |
| `insights.signals`, `search.index` | OFF | Single-score + live search still in use. |
| `integration.*` (whatsapp/google/meta/website/payments/abdm) | OFF | Integration boundary dormant. |
| `marketing.integrated_providers` | OFF | Marketing reads manual/standalone data, not live records. |
| `case_acceptance.enabled` | OFF (env) | Smart Presentation is still the live experience. |
| `blog.hub` | OFF (env) | Blog Marketing Hub hidden; routes 404 until flipped. |

**Why this matters:** a huge amount of the "newest, best" code is written and migrated but not actually in the user's hands yet. The risk is not missing code — it's untested cutovers and the gap between "exists" and "wired live."

## 2. What is working perfectly (solid, in daily-usable shape)

These are the mature, battle-tested cores. The readiness review's verdict: "the finance chain is transactional and audit-trailed, the API layer is disciplined, the ActivityEngine/RulesEngine backbone is fail-safe."

- **Finance / Billing chain** — invoice → payment → receipt → ledger → invoice-paid is transactional and audit-trailed. Coupon/wallet/membership discounts are now recomputed server-side (hardening sprint). EMI schedules, wallet, patient ledger all functioning. *(Note: server-side recompute fixes were code-verified but only route-crawl tested — see §4.)*
- **Patients** — registration, unified create/edit wizard (dual-mode modal), 12-tab patient profile, tags, medical alerts. Core is old and stable.
- **Appointments** — booking with server-enforced overlap/blocked-slot guards via shared `AppointmentService`; the Carbon 500 booking bug is fixed locally.
- **Consultations** — 4 independent workflows (New / Same Issue / Minor Visit / Emergency), view+print parity.
- **Prescriptions** — full module: per-drug food advice, syrup ml dosing, optional weight/pediatric helper, print/signature handling.
- **Treatment plans** — add/edit/accept/revert/print, doctor selector, material options, configurable print footer. Acceptance now routes through shared `TreatmentPlanAcceptanceService` (Timeline + Opportunity logged).
- **Inventory / Procurement / Lab** — PO → GRN → Vendor Invoice → AP chain built; inventory reversal; Lab v2 (Phases 1–5).
- **Auth / Security foundation (Phase A)** — private file storage, token hardening, MFA/2FA, tamper-evident audit chain (canonicalisation fixed + HMAC-keyed 2026-07-14). Self-test: `php artisan security:selftest`.
- **API layer (`/api/v1`)** — Sanctum, uniform response envelope, shared services, RBAC. 237 API routes. Disciplined and consistent.
- **Reviews / reputation** — built and wired (needs `REVIEWS_GOOGLE_URL`).
- **PRE (Patient Relationship Engine) Phases 0–8** — marked complete; WhatsApp two-way was live+tested at the Phase 7 milestone.

## 3. Code-complete but UNTESTED (the biggest category — treat as "not proven")

This is where most recent work sits. It compiles, migrations exist, route-crawl passes — but it has **not** been exercised on real data / real users. Do not assume any of it works until tested.

- **Hardening sprint (2026-07-14)** — all applicable Critical + High findings fixed in code, "untested beyond route crawl." This includes the money-safety fixes, appointment guards, XSS fixes, audit-chain fix. **High-stakes and unverified — top of the test list.**
- **Mobile (Flutter) app** — large surface, ~50% web parity (audit 2026-07-14). Whole modules ported but untested on device: Inventory full parity, PRE module, Treatment Plan/Visit, Membership (this one *is* tested working), Accounts/Finance gap-fill, consultation create, patient profile (read-only), Action Board close/notes/add-call. Reskinned "Infinity OS" UI untested. 5 API money bugs + a 2FA lockout were found in the parity audit.
- **Case Acceptance Engine** — architecture FROZEN, tables/services additive and dormant (flag OFF). Replaces Smart Presentation. Not validated on real patients. Prereq: Treatment Module `treatment_options` pricing.
- **Blog Marketing Hub (Wave 1)** — schema, block editor, SEO, publishing ledger built; flag OFF, hidden. Editor still being iterated (uncommitted tweaks in progress).
- **PRE consolidations** — inline WhatsApp chat in relationship profile, comms tab consolidation, recall/templates settings — all untested.
- **Backend orchestration slices** — several event producers wired; `payment.received` wired without dedupe; `membership.expiring` scan built. **Gotcha: `RulesEngine` silently skips rules unless given an explicit `relationship_id`.**
- **HR role management fix, TP doctor/date fixes, pediatric tooth charting, visit vitals** — built, several unmigrated.

## 4. Fragile / on eggshells (known sharp edges — touch carefully)

- **Multi-tenancy is NOT done.** Onboarding clinic #2 today would leak clinic #1's PHI. `clinic_id` isolation is a multi-week hardening effort and a hard prerequisite before any second clinic. **This is the single biggest blocker to commercial scale.**
- **PHI encryption (H16) still open.** `name` / `phone` / `email` are not encrypted at rest. `phone` is indexed for dedup/search, so encrypting it needs a blind-index column + backfill + search rewrite. Encryption/access hardening is deliberately PARKED until near public launch (target Level 1: encrypt-at-rest + PHI fields + clinic_id isolation + audited access; avoid zero-knowledge since it breaks recall/automation).
- **Uncommitted WhatsApp Click-to-Chat engine** — `WhatsAppLinkService` + `WhatsAppLinkController` + upgraded `whatsapp-button` component, wired into appointments/reviews/patient-profile/today. This is the interim `wa.me` send engine before the Meta API. **It is uncommitted and undeployed.** Web slice untested. (See §6.)
- **Recall backlog** — 1,810-item backlog is live in production; `recall.effective_from` added but existing rows unresolved. Throttle needed before any bulk recall fires.
- **Relationship orphan data** — 17 orphan rows from a bulk import that bypassed the linker; fix is `relationship:backfill --apply`.
- **Fragile UI files** — `patients/show.blade.php` Alpine scope is fragile (read before editing); billing form JS has had repeated temporal-dead-zone / load-order 500s (now fixed but brittle). Z-index layering has a fixed convention (topbar 120 / sidebar 130 / overlay 110) — don't break it.
- **Sandbox gotchas** — bash sandbox can show stale/truncated copies of just-written files (trust the Read tool); PHP is not in the sandbox; git-lock issues mean **commit from Windows, not the sandbox**.

## 5. Backend wiring & connections (how the pieces actually talk)

**The event spine.** `ActivityEngine` + `RulesEngine` + the `ActivityRecorded` event already *are* the internal event bus (see `docs/event-map-blueprint.md`). The architecture is producer→event→rules→action. The real gap is **missing producer calls** — the bus exists, but not every module emits into it yet. The hardening sprint wired 4 previously-dead automation rules that had no producers.

**Communication path.** `CommunicationEngine` sends are wrapped by `CommunicationGuard` (consent + guard factors). `guard.consent_required` is ON in production. The intended end-state is `comm.single_gateway` — all patient sends through one gateway — but that flag is OFF, so multiple send paths still exist today.

**PRE is the sole front door.** Architectural rule: never GET-link into `routes/communication.php` from anywhere; PRE is the only entry point. Forms POSTing to `communication.*` are fine.

**Relationship identity.** One Relationship per patient, always (`findOrCreateForPatient()`). `identity.link_patient` ON links new patients going forward; historical patients need a one-time `relationship:backfill --apply --force`.

**Marketing.** Provider-pattern abstraction (Standalone/manual vs Integrated). `marketing.integrated_providers` OFF means marketing reads manual data, not live invoices/reviews. Meta/Google connect is PARKED (blocked on Dentfluence business registration + website + platform approvals); WordPress publish works anytime. Graph API pinned to v23 via `config META_GRAPH_VERSION`.

**Modules (`app/Modules/`):** Appointment, Hq (superadmin command center), Huddle, Lab, Patient, PracticeProtocols, Treatment. Designed loosely coupled so each can become a standalone/premium product.

**Services (`app/Services/`):** Analytics, Assistant, Automation, Billing, Blog, CaseAcceptance, ClinicalLibrary, Communication, ContentManagement, Huddle, Insights, Inventory, Marketing, Presentations, Prm, Procurement, Relationship (the engine cluster), Reviews, Search, Treatment(Plan/Visit), Voice, Whatsapp, Workflow — plus standalone services (Coupon, Wallet, Consent, DataRights, Breach, Retention, RecallEngine, MembershipBenefit, PatientProfile).

**HQ / SaaS layer.** `clinics`, `plans`, `subscriptions`, `tickets` tables + `is_superadmin` flag exist (migrations 2026-07-16). This is the beginning of the multi-tenant SaaS control plane — schema present, isolation NOT yet enforced.

## 6. VPS vs Local drift (what's live vs what's only on your PC)

**Deploy model:** the VPS runs `bash deploy.sh` inside `/opt/dentfluence`, which `git pull --ff-only` from `main`, rebuilds Docker images, runs `migrate --force`, and caches config/views. `route:cache` is intentionally skipped (closure redirect routes would break it). There is no PHP on the VPS host — everything runs in the `app` container. **Always check `docker compose ps` after every deploy** (the queue worker has a history of crash-looping).

**Git state:** local `main` and `origin/main` are identical (0 ahead / 0 behind). So every *committed* feature is pushed and deployable.

**The actual drift — two kinds:**

1. **Uncommitted local work (not on GitHub, not on VPS):** the WhatsApp Click-to-Chat engine (new `WhatsAppLinkService`, `WhatsAppLinkController`, upgraded `whatsapp-button` component) plus blog editor tweaks, marketing blog calendar/subtabs, and edits across ~16 files. This exists only on your PC right now.
2. **Pushed-but-maybe-not-deployed:** several memory notes flag work that "NEEDS `bash deploy.sh`" — e.g. the Carbon 500 booking fix and the prescription weight field. Because the VPS only changes when you run `deploy.sh`, anything committed after the last deploy is live on GitHub but **not** yet on the VPS. **This cannot be verified from here** (no VPS access) — confirm by SSHing in and checking the deployed commit hash against `703393a`.

**Pending migrations risk:** 408 migrations exist locally. Several recent features are noted as "unmigrated" (TP doctor/date, HR role fix, visit vitals, etc.). `deploy.sh` runs `migrate --force`, so deploying will apply them — but that means the *first deploy after those commits* carries real schema changes. Review before deploying.

**Production data already live:** the 1,810-item recall backlog and the 17 orphan relationship rows are in the *production* DB — real cleanup, not local test noise.

## 7. Test / verification status

- Last full run: **13 of 14 tests passed (2026-06-28)** — all money/workflow paths green (payment→receipt→ledger, inventory, PO→GRN→payable, lab auto-close, recall engine, appointment status flow). The one "failure" was Laravel's boilerplate `ExampleTest` (fixed).
- 91 test files exist across Feature/Unit.
- **But** everything from the 2026-07-14 hardening sprint forward is untested beyond route crawl. The test suite predates the riskiest changes.
- `php artisan app:crawl-routes` auto-tests every page (found + fixed 21 broken pages historically) — good smoke test, not a correctness test.

---

# PART B — Commercial Summary

**Dentfluence is a Laravel-based Dental Clinic Operating System**, well past MVP for a single clinic. The bones are strong: a transactional, audit-trailed finance chain; a disciplined API layer (Sanctum + shared services) that already powers a companion Flutter mobile app; a consent-aware communication engine (DPDP-oriented); and a modular architecture where each module (PRE, Marketing, Inventory, Lab, Membership, Case Acceptance, Blog Hub) is designed to stand alone as a premium/subscription product.

**What's shippable today:** a full clinic operating loop — patients, appointments, consultations, treatment planning, prescriptions, billing/finance, inventory, lab, and a patient-relationship + recall engine. This is enough to run a real single clinic end-to-end, and much of it is deployed on the Hostinger VPS.

**Where it's heading (built, staged behind flags):** a unified communication gateway, an automation/workflow engine, a Case Acceptance patient-microsite experience, a Blog Marketing Hub, and multi-clinic SaaS (HQ control plane with plans/subscriptions/tickets). The sophistication is already coded — the roadmap is largely about validating and switching it on, not building from scratch.

**Honest maturity line:** production-ready for **clinic #1** after the critical-fix list is tested (~1–2 weeks of focused QA). **Not yet safe for clinic #2** until multi-tenant `clinic_id` isolation and PHI encryption land — that's the gating work between "one clinic" and "SaaS."

---

# PART C — What to work on next (prioritized by ROI)

**Now (unblocks confidence / revenue safety):**

1. **Test the hardening sprint on real data** — money paths especially (coupon/wallet/membership recompute, payment idempotency, invoice lock). It's the highest-stakes untested code in the app.
2. **Commit + deploy the WhatsApp Click-to-Chat engine** — it's finished locally and stranded. Commit from Windows, run `deploy.sh`, verify `docker compose ps`.
3. **Reconcile VPS with `main`** — SSH in, confirm the deployed commit, run any pending migrations deliberately.
4. **Throttle the live recall backlog (1,810 items)** and run `relationship:backfill --apply` for the 17 orphans before anything bulk-fires.

**Next (unblocks clinic #2 / the SaaS business):**

5. **Multi-tenant `clinic_id` isolation** — the hard prerequisite for a second clinic. Multi-week, evolutionary, not a rewrite.
6. **PHI encryption Level 1** — encrypt-at-rest + PHI fields (with blind-index for phone). Currently parked; unpark near public launch.

**Then (growth / differentiation, validate before switching on):**

7. **Flip and validate the staged engines** — automation/workflow/single-gateway cutovers, one flag at a time, on real shadow data.
8. **Validate Case Acceptance Engine on real patients** (prereq: Treatment `treatment_options` pricing), then decide cutover vs Smart Presentation.
9. **Mobile parity to ~100%** — fix the 5 API money bugs + 2FA lockout first, then device-test the ported modules.
10. **Blog Marketing Hub Wave 2+** once Wave 1 is validated behind the flag.

---

*Prepared from a fresh crawl of the mounted codebase (routes, migrations, services, feature flags, git state, deploy config) cross-checked against project history. VPS-side deployment state is inferred from the deploy model and could not be confirmed from a live VPS connection — verify the deployed commit hash directly.*
