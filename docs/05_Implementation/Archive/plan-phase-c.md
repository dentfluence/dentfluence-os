# Phase C — Patient-Facing + Payments + CLOUD 🚀 (Build Plan)

**Created:** 2026-06-29 · **Owner:** Sumit (solo, AI-assisted)
**Parent timeline:** `docs/plan-build-timeline.md` (Phase C = Nov–Dec 2026, ends in LAUNCH)
**Deploy companion:** `docs/deploy/vps-go-live-runbook.md` · **Docker kit:** `Dockerfile`, `docker-compose.yml`, `DEPLOY.md`
**Strategy:** `docs/competitive/eka-care-vs-dentfluence.md` (cloud-only, flat pricing, dental-depth moat)

> **What this doc is:** the *chunked build order* for the three Phase C tracks. No code here — this is the map we build from, one numbered chunk per session. **What it is NOT:** a rewrite of the timeline (that's the *what/when*); this is the *how*, sized so nothing truncates mid-build.

---

## How we build (rules baked in)

- **One chunk = one session = one vertical slice.** Each chunk lists its own *migration + model + controller + routes + view* set, sized to finish-and-test without getting cut off. Per the project pre-flight rule, before each chunk I estimate size and flag truncation risk before writing code.
- **Flags default OFF.** Every new surface ships behind a config flag (`config/features.php` or `.env`) so half-built work never reaches the live clinic.
- **Nothing destructive without sign-off.** No `migrate:fresh` / rollback. New migrations only; additive columns.
- **Reuse, don't fork.** Payments route through the existing `App\Services\Billing\InvoicePaymentService` (the same shared service web + mobile already use). Portal reads through the existing read-only `/api/v1/patients/{id}/*` endpoints where it can.
- **You run artisan/terminal yourself** at session end (migrate, config:clear, etc.) — I won't run them in-sandbox.

## Status legend
✅ done · 🟢 code-complete, needs test · 🟡 partial · ⬜ not started

---

## What already exists that we hook into (verified 2026-06-29)

| Thing | Where | We reuse it for |
|---|---|---|
| Shared payment engine | `app/Services/Billing/InvoicePaymentService.php` | Online payment must call THIS (full + EMI parity), not a new path. |
| Invoice chain | `Invoice`, `InvoiceItem`, `InvoicePayment` models | Gateway success → record an `InvoicePayment` here. |
| Wallet chain | `WalletService`, `Wallet`, `WalletTransaction` | Patient credit / online top-ups / refunds. |
| Membership finance | `MembershipBenefitService::enrollWithFinance` | Online membership purchase later. |
| API layer | `/api/v1` (Sanctum), `Api/V1/BillingController` etc. | Portal + mobile share endpoints; envelope already standard. |
| Public token-page pattern | `routes/reviews.php` → `/r/{token}` (no auth) | **The precedent for portal magic-link auth** — same pattern, scoped per patient. |
| Auth | only `web` session guard + `users` provider (patients are NOT users) | Portal needs its own auth — see C-P1 decision below. |
| Deploy | `vps-go-live-runbook.md` + Docker kit (untested, no VPS yet) | Cloud track promotes this from "test box" to "launch". |

---

# Track 1 — Online Payments (timeline 2.5, 7.2)

*Build this FIRST. Smallest surface, highest immediate value, and the portal's "Pay" button depends on it. External dependency (gateway KYC) — start that paperwork on day one.*

### Pre-req (you, not code): pick + KYC the gateway
Razorpay is the India-default (UPI + cards + netbanking, GST-friendly, good Laravel SDK). Decision needed before C-PAY-2. KYC takes days — **start it now**, it runs in parallel with C-PAY-1.

| Chunk | Scope (one session) | Deliverables | Flag |
|---|---|---|---|
| **C-PAY-1** | Payment scaffolding + config (no live calls) | migration `payment_intents` (id, invoice_id, patient_id, gateway, gateway_order_id, amount, currency, status, meta json, timestamps) + `PaymentIntent` model + `config/payments.php` (keys from `.env`, test mode) + `PaymentGatewayService` interface + a `NullGateway`/`LogGateway` stub. No real gateway yet. | `payments.enabled=false` |
| **C-PAY-2** | Razorpay adapter (server-side order create) | `RazorpayGateway implements PaymentGatewayService` (create order, verify signature) + `.env` keys + wire into `config/payments.php`. Unit-test signature verify with a fixture. Still no UI. | gateway key in `.env` |
| **C-PAY-3** | Pay flow on an existing invoice (staff-initiated, web) | `PaymentController` (`create` intent → `callback`/`verify`) + routes in `web.php` + a "Pay online" button on the existing invoice view + Razorpay checkout handoff. On verified success → call `InvoicePaymentService` to record the `InvoicePayment` (reuse, don't reinvent). Idempotent on `gateway_order_id`. | `payments.enabled` |
| **C-PAY-4** | Webhook + reconciliation (timeline 7.2) | `POST /webhooks/razorpay` (signature-verified, no CSRF, idempotent) + reconcile intent ↔ invoice ↔ payment + audit-log every transition. This is the safety net for "user paid but tab closed before callback". | — |
| **C-PAY-5** | Pay-by-link + receipt | generate a tokenised public `/pay/{token}` page (reuse the reviews `/r/{token}` pattern) so a patient pays without logging in (WhatsApp/SMS the link — ties to Phase B comms) + PDF/township receipt on success. | `payments.public_links` |
| **C-PAY-6** | Refunds + wallet bridge | gateway refund → `WalletService` credit or gateway reversal + admin refund UI on the payment record. | `payments.refunds` |

**Track exit:** a clinic can take an online payment against any invoice, it reconciles into the existing finance chain even if the browser dies, patients can pay a link without an account, refunds work. *GST e-invoicing (7.1) is tracked separately under timeline Phase C but is a billing-side chunk — slot it after C-PAY-4 if you want it pre-launch.*

---

# Track 2 — Patient Portal (timeline 2.1, 2.2, 2.3)

*Build SECOND — it leans on Track 1 for "Pay" and on existing `/api/v1` reads for "view records". Largest surface (L); the chunking matters most here.*

### C-P1 decision — how patients log in (resolve before building)
Patients are **not** `users` (verified: only `web` guard exists). Three options, recommendation first:

1. **OTP / magic-link, no password (recommended).** Patient enters mobile → we WhatsApp/SMS a one-time code or `/portal/{token}` link → short-lived portal session. Pros: no password resets, ties to Phase B comms you already built, matches the reviews-token pattern, lowest friction for "12th-pass" patients. Cons: depends on the comms channel being live.
2. **Separate `patient` auth guard + password.** Classic. More to build (registration, reset, lockout) and more support burden.
3. **ABHA/phone later.** Defer to Phase F; don't gate launch on it.

> **I recommend Option 1.** It reuses infrastructure you already have and avoids a password-support tail. Confirm before C-PORT-2.

| Chunk | Scope (one session) | Deliverables | Flag |
|---|---|---|---|
| **C-PORT-1** | Portal shell + scope plumbing | `routes/portal.php` (separate file, like reviews) + a `portal` middleware group + `PortalController@home` placeholder + a minimal patient-facing layout (NOT the staff shell). Establishes the security boundary: a portal request can only ever see ONE patient's data. | `portal.enabled=false` |
| **C-PORT-2** | Patient auth (per C-P1 decision) | OTP/magic-link request + verify + `portal_sessions` (or signed token) + rate-limit + audit. Reuses Phase B WhatsApp/SMS sender. | `portal.enabled` |
| **C-PORT-3** | Records view (read-only) | Portal pages for appointments, treatment plans, prescriptions, invoices — **reading through the existing read-only `/api/v1/patients/{id}/*` endpoints**, scoped to the logged-in patient. No new data layer. | — |
| **C-PORT-4** | Pay from portal | wire the portal invoice view to Track 1's `PaymentController` so a patient settles a balance in-portal. (Hard dependency on C-PAY-3.) | `portal.pay` |
| **C-PORT-5** | Digital intake forms (timeline 2.3) | patient-completed intake (medical history, consent, contact) → writes into the existing patient/consent tables (Wave 5 DPDP consent layer) + staff sees it on the patient record. Reuse the snap-a-bill/intake-form schema already built for paper forms. | `portal.intake` |
| **C-PORT-6** | 24/7 self-scheduling (timeline 2.1) | patient picks slot from real availability → creates a (pending-confirm) `Appointment` via `/api/v1/appointments` + staff confirm/deny + reminder hooks. Biggest single chunk — may split into 6a (read availability) / 6b (book + confirm). | `portal.booking` |

**Track exit:** a patient logs in (no password), views their records, pays a balance, completes intake online, and requests an appointment — all scoped, consented (DPDP), and audit-logged.

---

# Track 3 — Cloud (timeline 🔵 cloud migration + sync, 🔴 DPDP verify)

*Build LAST in calendar terms but **provision the box EARLY** so Tracks 1–2 get tested on real HTTPS (gateways + OTP need public URLs / webhooks anyway). This track promotes the existing untested runbook + Docker kit into an actual launch.*

| Chunk | Scope | Deliverables | Notes |
|---|---|---|---|
| **C-CLOUD-1** | Provision + dry-run the runbook | Stand up the India-region VPS (DO Bangalore / Lightsail Mumbai) per `vps-go-live-runbook.md`, import a **copy** of the DB (never `migrate:fresh`), get HTTPS green. | You run the server commands; I fix runbook gaps we hit. Gives Tracks 1–2 a public URL for gateway callbacks + webhooks. |
| **C-CLOUD-2** | Secrets + env hardening | move gateway keys, APP_KEY, mail to server `.env` only; confirm `APP_DEBUG=false`, `SESSION_SECURE_COOKIE=true`, HSTS/headers from Phase A `config/security.php` are live. | Phase A security must be ON in prod. |
| **C-CLOUD-3** | Webhooks + queue + scheduler live | Razorpay webhook URL registered against the public domain; `dentfluence-queue` worker + `schedule:run` cron running (reminders/recall/reviews from Phase B depend on these). | From runbook §13. |
| **C-CLOUD-4** | Backups + restore drill | daily encrypted DB dump (runbook §14) **+ actually test a restore** onto a scratch box. Off-site copy. | "Backup you haven't restored isn't a backup." |
| **C-CLOUD-5** | Sync-layer build (timeline 🔵) | the encrypted offline cache designed in Phase A — cloud = source of truth, device holds a scoped encrypted cache. Scope TBD from the Phase A design doc; may be deferred past launch if thin. | Don't let this block launch if Phase A design isn't final. |
| **C-CLOUD-6** | 🔴 DPDP pre-launch verification | audit the Wave 5 consent/rights/breach/purge flows end-to-end on the cloud box **before any real PHI lands**. Sign-off gate. | Hard gate. Wave 5 is built (✅) — this is verify, not build. |

**Track exit / LAUNCH GATE:** India-hosted, HTTPS, Phase-A-secure, payments + portal working on the real domain, backups restore-tested, **DPDP verified before real patient data touches the cloud.**

---

## Suggested build order (across sessions)

```
C-PAY-1 → C-PAY-2 → C-CLOUD-1 (get a public URL early) → C-PAY-3 → C-PAY-4
   → C-PORT-1 → C-PORT-2 → C-PORT-3 → C-PORT-4 → C-PAY-5
   → C-PORT-5 → C-PORT-6 → C-PAY-6
   → C-CLOUD-2..4 → C-CLOUD-6 (DPDP verify)  →  🚀 LAUNCH
   → C-CLOUD-5 sync (can trail launch)
```

Rationale: payments unblock the portal's most valuable button; a public URL early unblocks gateway callbacks + OTP testing; DPDP verify is the final gate before real PHI.

## Critical path & risks
- **Gateway KYC + GSTN are external** — start KYC the same day as C-PAY-1 or it becomes the bottleneck.
- **Portal auth (C-P1)** is the one real design decision — pick OTP/magic-link and the rest is mechanical.
- **Don't switch on cloud PHI** until C-CLOUD-6 (DPDP) + India-region hosting are both green (timeline risk #4).
- **AI features stay OFF on the VPS** (no GPU) — expected, per runbook §0.

---

## Open decisions for you (blockers to mark resolved)
1. **Gateway:** Razorpay (recommended) vs Stripe/other? → gates C-PAY-2.
2. **Portal login:** OTP/magic-link (recommended) vs password guard? → gates C-PORT-2.
3. **GST e-invoicing (7.1):** pre-launch or post-launch? → slots after C-PAY-4 if pre.
4. **Sync layer:** in-launch or trailing? → C-CLOUD-5 timing.

---

*Next session: confirm decisions 1–2, then we start **C-PAY-1** (I'll give the pre-flight size estimate before writing code).*
