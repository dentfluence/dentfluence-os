# Dentfluence — Production Readiness Review

> **STATUS UPDATE — 2026-07-14 (hardening sprint complete).**
> All applicable **Critical** and **High Priority** findings below have been
> fixed in code, except where explicitly noted. Out-of-scope by instruction:
> WhatsApp inbound/outbound (C7), GST (F2), and everything multi-tenancy
> (Section 6). See `docs/production-hardening-2026-07-14.md` for what changed.
>
> **Still open (deliberately):** H16 full PHI encryption of `name`/`phone`/`email`.
> `phone` is now indexed for dedup + search performance; encrypting it requires a
> blind-index column, a data backfill and a search rewrite — its own scoped piece
> of work, not a "smallest fix".
>
> **Corrections found during the sprint:** `AbsoluteSessionTimeout` is NOT a
> no-op (R20 — `session.absolute_lifetime` already defaults to 720 min), and the
> `patient_links` soft-delete orphan concern (R13) is not real (Eloquent's
> soft-delete scope already excludes them from `belongsToMany` reads).

**Date:** 2026-07-14
**Scope:** Full-application review before first paying clinics. Seven parallel audit tracks: money handling, loose ends, inter-module wiring, multi-tenancy & RBAC, security, database/performance/API, and clinic workflows/UX.
**Method:** Static code review (routes → controllers → services → models → migrations → blades). No code was changed. Every finding cites file:line evidence; the highest-severity findings were independently re-verified against source.

---

## Executive Summary

Dentfluence's bones are good. The finance chain is transactional and audit-trailed, the ActivityEngine/RulesEngine backbone is fail-safe and well-designed, the API layer is disciplined (Sanctum, shared services, uniform envelope), Phase A genuinely closed the public-media exposure, and the feature-flag discipline is exactly right for staged cutovers. **Nothing here requires a rewrite.**

But the review found a consistent pattern: **the newest code is production-grade while the older core (patients, appointments, communication_queue) and the "glue" between modules never got the same treatment.** The must-fix list breaks down as:

- **11 Critical** issues that will cause revenue loss, data corruption, or visible embarrassment at clinic #1.
- **~22 High Priority** issues that will bite within the first months of real use.
- **Multi-tenancy is NOT a flag-flip.** Onboarding clinic #2 today would leak clinic #1's PHI. This is a focused multi-week hardening effort (detailed in Section 6) — evolutionary, not a rewrite.

**Verdict:** Launchable for clinic #1 after the Critical list (~1–2 weeks of focused fixes). Clinic #2 must wait for the tenancy hardening.

---

## 1. CRITICAL — Must fix before production

### C1. Web invoice creation trusts a client-submitted coupon discount
`app/Http/Controllers/BillingController.php:340-345` *(verified)*

The rupee discount is taken verbatim from a hidden form field (`$request->input('coupon_discount', 0)`) — never recomputed via `$coupon->calculateDiscount()`, never capped to the coupon's value or the subtotal. The API path already does this correctly (`Api/V1/BillingController.php:652`); web does not.

- **Impact:** Any valid coupon code + a tampered hidden field = arbitrary discount, invoice drops to zero. Direct revenue loss with a valid-looking coupon on record.
- **Fix:** After items are saved, set `$couponDiscount = $coupon->calculateDiscount((float) $invoice->subtotal)` and enforce `min_invoice_amount` — mirror the API exactly.

### C2. Invoice `store()` records a wallet discount larger than the amount actually debited
`app/Http/Controllers/BillingController.php:355, 382, 410-421`

`wallet_applied` is written from the raw request and subtracted from the total *before* the debit. `WalletService::debit()` caps the debit to real/eligible balance and returns the actual figure — which is discarded. Patient asks for ₹500, only ₹100 available → invoice reduced by ₹500, wallet loses ₹100. The payment path (`:854`) and API (`:699`) handle this correctly; only `store()` doesn't.

- **Impact:** Silent revenue leak; wallet credit remains for reuse. Trivially triggered with promo-restricted credits.
- **Fix:** After debit: `if ($debited != $walletApplied) { $invoice->update(['wallet_applied' => $debited]); $invoice->recalculate(); }`

### C3. Payment recording has no idempotency, no invoice lock, and no double-submit protection
Backend: `BillingController.php:831`, `app/Services/Billing/InvoicePaymentService.php:87` — no `lockForUpdate()`, no dedupe.
Frontend: `resources/views/billing/show.blade.php:479` — `#paymentForm` is a plain POST with **zero** disable-on-submit anywhere in the file.

- **Impact:** The single most likely money bug in daily use. Receptionist double-clicks on slow wifi → two `InvoicePayment` + two `Receipt` numbers + two `FinanceTransaction` income rows; invoice overpays and the phantom excess lands in the wallet as credit. Reconciliation nightmare at day-close.
- **Fix (both ends):** (a) disable the submit button on submit (the pattern already exists in `add-patient-modal`); (b) `lockForUpdate()` on the invoice at the top of the transaction + reject an identical (amount, mode, date) payment within the last few seconds. Consider `Idempotency-Key` on the API.

### C4. Wallet debit has no row lock; negative balances are clamped and hidden
`app/Services/WalletService.php:88-119`, `app/Models/Wallet.php:48-65`

`debit()` reads cached balance columns without a pessimistic lock — two concurrent debits both see ₹100 and both spend it. `recalculate()` then does `max(0, $perm)`, erasing the evidence of the overdraw.

- **Impact:** Wallet credit spendable more than once under concurrency; the clamp destroys the reconciliation trail.
- **Fix:** `Wallet::where('patient_id',$id)->lockForUpdate()->first()` inside the existing transaction; log/alert instead of clamping when the ledger goes negative.

### C5. Communication Timeline shows fabricated patients
`app/Http/Controllers/Communication/TimelineController.php:15, 80-93` *(verified — hardcoded "Riya Sharma", "Amit Kulkarni" etc.)*; nav entry live at `config/communication.php:183-186`.

A clickable nav destination renders ten invented patients with fake phones and treatments.

- **Impact:** A paying clinic opens Communication → Timeline and sees strangers' data. Looks like a data leak; instant credibility loss.
- **Fix:** Hide the Timeline nav item until the real union query (already sketched in the TODO at `:66-77`) is wired.

### C6. Global File Viewer is a static mockup — it never fetches the real file
`resources/views/clinical-library/partials/file-viewer.blade.php:14, 32-55` — included globally (`layouts/app.blade.php:1130`) and triggered with real file IDs from Patients Documents tab, Clinical Library, Marketing and Education tabs. `init()` captures `e.detail.id` but never fetches; notes/tags are hardcoded literals ("Pre-operative IOPA taken before root canal treatment on tooth 26…").

- **Impact:** Clinician clicks any X-ray/document and sees the same fabricated clinical notes regardless of the file — a clinical-safety and trust failure.
- **Fix:** Fetch the existing JSON endpoint (`routes/clinical-library.php:29`) in `init()` and bind the response; delete the hardcoded literals.

### C7. Inbound webhooks are imported but never routed — all inbound channels are dead
`routes/api.php:29-32` *(verified — `WhatsAppLeadController` appears only as a `use` import, no route anywhere)*. Same for `MetaLeadController`, `ChatbotController`, `WebsiteLeadController`. `InboundMessageService` is only reachable via the unrouted controller.

- **Impact:** Patient WhatsApp replies are never ingested → the 24-hour service window never opens, so staff can only send templates, never free-text replies (this undermines the PRE inline chat built on 07-09). Meta/website/chatbot leads never become leads.
- **Fix:** Register the documented routes with the existing `VerifiesMetaSignature` check. Small change, big functional unlock.

### C8. Reports count appointment status `'completed'` — a status that does not exist
`app/Http/Controllers/ReportsController.php:35, 49, 258` *(verified)* vs the enum `scheduled, checkin, in_chair, checkout, done, cancelled, no_show` *(verified in `2026_05_13_000001_create_appointments_table.php:20`)*. The terminal state is `'done'`.

- **Impact:** The "Completed" KPI, "Completion Rate", and the daily-trend completed line on the main reports page are **always zero**. The owner's first look at analytics shows garbage. (Same bug class as the historical `reviews:request` fix — see H-13 for the systemic answer.)
- **Fix:** `'completed'` → `'done'` in ReportsController now; introduce `app/Enums/AppointmentStatus.php` so this class of bug can't recur.

### C9. `patients` table: zero foreign keys, no index or uniqueness on `phone`
`database/migrations/2024_01_01_000000_create_patients_table.php:39-48` — `branch_id`/`created_by` are raw ints with an empty `// Foreign Keys` comment; `phone` is `string(20)` with no index; nothing in the other 375 migrations remediates this. (`phone` is deliberately plaintext — indexing is safe.)

- **Impact:** Every phone lookup (dedup, search, missed-call matching) is a full-table scan on the app's central table; nothing prevents duplicate patients; no referential integrity.
- **Fix (additive migration):** `index('phone')`, `index(['branch_id','phone'])`, nullable non-cascading FKs. Add `unique(['branch_id','phone'])` only after a dedup pass.

### C10. Patient import: whole file in memory, one giant transaction, per-row full scans, bypasses the relationship linker
`app/Http/Controllers/PatientImportExportController.php:155-228, 359`

`$sheet->toArray()` loads everything into RAM; one `DB::transaction` wraps the whole loop; each row fires 2 unindexed existence queries; `Patient::create()` is called directly so **no relationship shell is created** — this is the exact mechanism behind the 17 orphan rows already observed. Compounding: `patient_links` uses `onDelete('cascade')` but Patient soft-deletes, so the cascade never fires and children outlive soft-deleted parents.

- **Impact:** Every clinic migration (the first thing a new customer does) reproduces the orphan bug at scale, and a 40k-row legacy import will OOM or timeout with full rollback.
- **Fix:** Route import creates through the same `PatientService`/linker path as web Add Patient; chunked transactions (~500 rows); pre-load existing phones with one `pluck`. Add a Patient `deleting` observer that soft-cascades children. Run `relationship:backfill --apply` for existing data (see H-15).

### C11. Calendar drag-drop reschedule bypasses ALL conflict and blocked-slot checks
`AppointmentController::reschedule` (`:541-557`) — validates only date/time/duration then updates. No `blockedSlotConflict()`, no overlap query. The JS `onEventDrop`/`onEventResize` (appointments/index.blade.php:1656, 1695) never call the conflict check that the create modal uses.

- **Impact:** The most conflict-prone action in the app (dragging appointments around) silently double-books doctors or books onto their leave. Two patients arrive for the same chair.
- **Fix:** In `reschedule()` (and `update()`, see H-5) reuse the existing `checkConflict()` overlap filter + `blockedSlotConflict()`; return 422 so the existing `info.revert()` branch fires.

---

## 2. HIGH PRIORITY — Fix soon after (or alongside) the Critical list

### Money & billing

**H1. Overpayment-to-wallet exists on web but is missing from the shared service (mobile).**
`BillingController.php:951-968` deposits excess to wallet; `InvoicePaymentService.php:178-234` (the only path mobile uses) has no equivalent — a mobile overpayment is booked as income, `balance_due` clamps to 0, and the patient's surplus vanishes from their ledger. **Fix:** port the excess block into the shared service so both paths are identical.

**H2. Double Accounts-Payable: GRN auto-books a vendor bill AND VendorInvoice books another.**
`InventoryService.php:604-622` (GRN → `FinanceExpense`, `source_type => 'PurchaseOrder'`) + `VendorInvoiceController.php:189-214` (same PO → second unpaid expense, `source_type => VendorInvoice::class`). Nothing dedupes; the two `source_type` conventions don't even correlate. **Fix:** before creating vendor-invoice AP, link to or skip an existing GRN-sourced unpaid expense on the same PO; standardise `source_type` on the FQCN.

**H3. `VendorInvoice.status` never advances — analytics always zero, paid bills stay deletable.**
Created `'pending'` (`VendorInvoiceController.php:159`); `AnalyticsController.php:85,330,440,455` filters `status='unpaid'`/`'paid'` which never match; the `destroy()` paid-guard (`:250`) never fires, so a *paid* vendor invoice can be deleted along with its paid expense, corrupting vendor outstanding. **Fix:** propagate `FinanceExpense.payment_status` back to the parent; align the analytics filters to the real enum.

**H4. Document-number generation is not concurrency-safe.**
`Invoice.php:172-185`, `Receipt.php:63-78`, `FinalBill.php:63-76` — `MAX()+1` with no lock against UNIQUE columns. Concurrent creations collide; the losing **payment transaction rolls back entirely with no retry** — a legitimate payment fails with a 500 and staff assume it saved. **Fix:** generate inside the transaction under `lockForUpdate()` (or a counter table) and/or retry on unique violation.

### Scheduling & clinical workflows

**H5. No server-side appointment overlap enforcement anywhere.**
`AppointmentController::store` (`:110-280`) checks only blocked slots; real overlap detection is an *advisory* GET (`checkConflict`, `:583-618`) surfaced as a soft JS `confirm()` in one modal only. The mobile API `create()`, the full edit form (`update()`, `:506-538`), and concurrent bookings by two receptionists all write overlaps silently. **Fix:** enforce the overlap guard in `store()`/`update()`/API `create()` server-side, with an explicit `force=true` for intentional double-booking.

**H6. Treatment-plan revision hard-deletes completed/invoiced items.**
`TreatmentPlanController::update` (`:242-247`) — `whereNotIn('id', $keptIds)->delete()` with no status guard. Revising an ongoing plan deletes completed, already-billed line items and their plan↔invoice linkage. (`revert()` is guarded; `update()` is not.) **Fix:** never delete items with `status = completed` or linked invoices; prune only `pending`.

**H7. Full patient registration has no duplicate-phone detection.**
`PatientController::store` → `PatientService::createFromInput` always inserts; only `quickCreate` (`:254-264`) returns a 409 duplicate. The main registration modal creates split records for returning patients. **Fix:** run the same phone lookup in `store()`; show "Use existing / Create new" on match.

**H8. Recall engine silently drops no-phone patients; no consent check.**
`RecallEngineService.php:133, 211, 272, 324, 394, 457` — every trigger filters `whereNotNull('phone')` or `continue`s. No "couldn't-contact" bucket, so those patients are permanently invisible to recall; no opt-out check on birthday/marketing triggers. **Fix:** queue no-phone matches tagged `needs_contact` instead of skipping; add the consent flag check.

**H9. Concurrent edits are silent last-write-wins.**
`PatientService::updateFromInput` (`:286-375`), `BillingController::update` (`:497-579`), `AppointmentController::update` (`:506-538`) — no optimistic locking. Two staff editing the same record overwrite each other invisibly. **Fix:** hidden `updated_at` field + 409 "record changed since you opened it" on mismatch.

### Backend wiring

**H10. Recall queue → WhatsApp is a dead end.**
`RecallEngineService.php:552-605` — every recall writes `channel => 'call'`; no `OutboundMessageService` call exists in the pipeline, and `whatsapp:send-reminders` covers appointment reminders only. All recalls depend on reception manually calling. **Fix:** a queue-drain step that sends via the already consent-gated `OutboundMessageService::sendTemplate()` for channels the clinic enables.

**H11. Four enabled automation rules are permanently dead — no producer fires them.**
`config/relationship_rules.php:285-386` — zero producers exist for `birthday.approaching`, `estimate.sent`, `appointment.missed`, `payment.overdue` (grep-verified). 5 of 12 rules never run. **Fix:** fire `appointment.missed` where status flips to `no_show` (`AppointmentController.php:286`); add a scheduled overdue-invoice scan (mirror `membership:scan-expiring`); fire `estimate.sent` in `PresentationController::sent`; emit `birthday.approaching` from the recall run. Also flip `recall_6months` to `enabled => false` with a comment — it's intentionally starved by `TreatmentVisitService.php:384-386` but the config lies.

**H12. The whole automation backbone silently depends on cron + a queue worker.**
All producers are `Schedule::command()` entries; jobs use the `database` queue (`config/queue.php:16`). If `schedule:run` or `queue:work` isn't running, recalls, reminders, reviews, membership scans and score recomputes all stop **with no error surfaced**. **Fix:** confirm the VPS provisions both (cron + supervised worker) and add a staleness health-check/alert.

### Auth & security

**H13. The mobile API completely bypasses MFA.**
`Api/V1/AuthController.php:30-68` — never calls `hasTwoFactorEnabled()`; issues a full Bearer token on password alone, while web forces the 2FA challenge (`AuthController.php:52-57`). **Fix:** mirror the web branch (TOTP/recovery code required, or a limited 2fa-pending token).

**H14. Web and API use different authorization systems.**
Web: `CheckModulePermission` → `role_id`/`RoleModulePermission`. API: `EnsureApiRole` → legacy `role` string (`EnsureApiRole.php:41-45`). Masked today because everyone is admin; the moment non-admin logins ship, mobile and web will grant/deny differently for the same user. Related: `User.php:204` still short-circuits on the legacy string, and `HrStaffController.php:316` gates password reset with an inline `role === 'admin'`. **Fix:** route both middlewares through `canAccess()`; make `role_id` authoritative and demote `role` to a label.

**H15. Stored XSS: patient data injected unescaped into `<script>`.**
`resources/views/lab/index.blade.php:655` *(verified)* and `appointments/_modal.blade.php:431` — `{!! json_encode($patients) !!}` doesn't escape `<`/`>`; a patient name containing `</script><script>…` executes in every staff browser opening those screens. The rest of the app correctly uses `@json`. **Fix:** replace both with `@json()` / `Js::from()` (one-line changes).

**H16. PHI encryption gaps + a cast that silently hides plaintext.**
`Patient.php:137-147` encrypts secondary fields but **not** `name`, `phone`, `email`, `date_of_birth` — the strongest identifiers stay plaintext at rest and in backups. `app/Casts/Encrypted.php:30-35` returns raw value on decrypt failure, so unencrypted rows read back silently as if fine (and can fool `security:selftest`). Note: `phone` plaintext is currently *required* by C9's index fix — if you encrypt it later, use a blind-index hash column for lookups. **Fix:** extend casts to name/email (+DOB), blind-index for phone, and make the cast log/throw on fallback.

**H17. The "tamper-evident" audit chain is unkeyed.**
`app/Traits/HashChained.php:84` — plain `sha256(prev|json)`, no secret, no external anchor; the append-only guard is Eloquent-only, bypassed by any direct DB write. An attacker with DB access recomputes the chain and `audit:verify` passes. **Fix:** `hash_hmac` with a key outside the DB + periodic offsite anchoring of the latest hash.

### Database & performance

**H18. Appointments have no index on `appointment_date` or `status`.**
`2026_05_13_000001` indexes only FK columns; `ReportsController` (~12 aggregate scans per page load, `:32-119`), `AppointmentService::todayCounts` (6 status counts) and every calendar view full-scan. **Fix (additive):** `index(['branch_id','appointment_date'])`, `index(['appointment_date','status'])`; then cache the reports KPI block (`Cache::remember`, 5–15 min).

**H19. `communication_queue`: no FKs, no status/phone indexes, `assigned_to` is a name string.**
`2026_06_04_000020` — `patient_id` unconstrained, `assigned_to` stores staff *names* (breaks on rename, can't join), `status`/`due_at`/`phone` unindexed; missed-calls search does leading-wildcard LIKE on top (`MissedCallsController.php:68-79`). This is one of the busiest tables in the app. **Fix (additive):** `index('status')`, `index(['status','due_at'])`, `index('patient_id')` + FKs; add nullable `assigned_to_user_id` alongside the legacy column.

**H20. Invoice links have no FKs.**
`2026_06_05_000001:38-43` — `treatment_plan_id`, `appointment_id`, `created_by`, `updated_by` are raw nullable ints. Financial rows can dangle against deleted records; hard to defend in an audit. **Fix (additive):** nullable FKs with `nullOnDelete` + indexes.

**H21. `audit_logs` and `activities` grow unbounded — no pruning anywhere.**
Both are written on effectively every action; no retention command exists (verified across `app/Console/Commands`). Millions of rows within a year; backup and write costs climb. **Fix:** scheduled chunked prune with a retention window, respecting legal hold on financial modules.

### Loose ends

**H22. `recall:run --dry-run` is a stub that does nothing.**
`RunRecallEngine.php:33-38` — prints a banner and exits before computing anything. An operator validating recall config sees "clean" and may never run the real thing. **Fix:** implement a real preview or remove the advertised option.

**H23. Communication sidebar badge counts are hardcoded to 0.**
`CommunicationServiceProvider.php:33-38` — overdue/follow-up/pending badges permanently read zero, so real overdue work is invisible to staff. **Fix:** replace three literals with the real count queries.

**H24. Treatment-plan `accept()` is triplicated.**
`TreatmentPlanController.php:266-324`, `PublicPresentationController.php:99-163`, `Api/V1/TreatmentPlanController.php:136` — identical acceptance orchestration in three places (the public one even says "mirrors … exactly" in a comment). Any change must be made thrice; drift produces inconsistent Opportunities per channel. **Fix:** extract `TreatmentPlanAcceptanceService` and call it from all three. (Matches the flag already on record for this module.)

**H25. Pending one-time data-repair commands gate correctness.**
`config/features.php:46-52` — `identity.link_patient` is ON for *new* patients only; existing patients have no relationship row until `relationship:backfill --apply --force` runs. Also pending per past work: `action-options:sync-closes-task --apply`. **Fix:** add both to the deploy checklist with post-run count verification; consider an idempotent guarded post-deploy runner.

---

## 3. RECOMMENDED — Improves quality; schedule after High Priority

### Money edges
- **R1.** Zero-interest EMI schedule doesn't absorb rounding on the final instalment (`EmiSchedule.php:58-72`) — ₹100/3 = 99.99. Set last instalment to `principal − (monthly × (n−1))`.
- **R2.** Membership free-item matching uses substring `str_contains` (`MembershipBenefitService.php:97-107`) — "Deep scaling + full-mouth rehab" becomes 100% free because it contains "scaling". Match on treatment master / word boundary and cap at the plan's freebie value.
- **R3.** Web skips the coupon `min_invoice_amount` check the API enforces (`BillingController.php:336-345` vs `Api/V1/BillingController.php:649-651`). Add the same guard to web `store()` and `validateCoupon()`.
- **R4.** Coupon usage limit is check-then-act, non-atomic (`CouponService.php:24, 52-64`) — single-use coupons redeemable twice under concurrency. Unique index on `(coupon_code_id, patient_id)` or re-check under lock.
- **R5.** Provider-EMI marks the invoice fully paid (and issues a Final Bill) before the provider settles (`InvoicePaymentService.php:121-151`, `Invoice.php:148`). Track the unsettled portion as receivable or defer FinalBill until `provider_paid_at`.
- **R6.** FinalBill generation is check-then-act without a lock (`BillingController.php:1019`, `InvoicePaymentService.php:232`) — resolved for free by C3's invoice lock; also add unique index on `final_bills.invoice_id`.
- **R7.** `updatePaymentDate` cascades to receipt + ledger but not to EMI schedule due dates (`InvoicePaymentService.php:350-364`).
- **R8.** Overpayment silently becomes wallet credit with no cap or confirmation (`BillingController.php:747, 955-968`) — a ₹50,000 typo on a ₹5,000 invoice becomes standing credit. Require an explicit "record as advance" confirmation when `amount > balance_due`.
- **R9.** Legacy `billing.cancel` cancels with no reason, no audit, no admin gate (`BillingController.php:725-730`) while `destroy()` requires all three. Retire the button or bring `cancel()` up to par.
- **R10.** `FinanceVendor.outstanding_amount` is a cached counter with no maintainer on payment (`VendorInvoiceController.php:220-222, 261-264`) — derive from unpaid expenses on read, or update on payment too.

### Clinical & workflow edges
- **R11.** Accepting a treatment option doesn't retire sibling options (`TreatmentPlanController::accept`) — both the ₹40k implant and ₹18k bridge options can be simultaneously active and billable. Set siblings to `superseded` on accept.
- **R12.** `TreatmentPlanController::destroy` (`:393-398`) has no billing guard and no role check — `revert()` has both. Mirror them.
- **R13.** Walk-in date computed in UTC while time is local (`appointment-modal-global.blade.php:1100-1104`) — between 00:00–05:29 IST the walk-in lands on yesterday. Use `toLocaleDateString('en-CA')` (already used correctly at `index.blade.php:1651`).
- **R14.** Appointment status vocabulary drift: `RecallEngineService.php:329` filters `'confirmed'`, which isn't a valid status — that branch is inert. Same bug class as C8; the enum (F1) is the systemic fix.

### Security hardening
- **R15.** Latent IDOR: `BranchScope` documents itself as "effectively inert today"; `ClinicalFileController` checks file→patient but never user→patient (`:35, 77, 149`). Fine while everyone is admin; a hole the day non-admin logins ship. Add explicit `authorizeBranch()` like `SecureMediaController` already does.
- **R16.** MFA is capability, not policy — no middleware enforces it for privileged roles, and `security:selftest` only checks that columns/routes exist. Enforce for admins; make the self-test assert enforcement.
- **R17.** CSV formula injection in patient export (`PatientImportExportController.php:275-296`) — prefix leading `= + - @` with `'`.
- **R18.** Public share links: "never expires" allowed (`PresentationLinkService.php:37`) and no throttle on `/present/{token}`, `/r/{token}`. Enforce max expiry; add `throttle:`.
- **R19.** Backups unencrypted and co-located on the VPS (`backup.sh:34-47`) — the dump contains plaintext name/phone/DOB/email + all financials. Encrypt (`gpg`/`age`), store outside the app tree, push offsite.
- **R20.** Set `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, and a non-zero `session.absolute_lifetime` in production (`AbsoluteSessionTimeout` is registered but a no-op at 0); have `security:selftest` assert these + `APP_DEBUG=false`.
- **R21.** Login throttle is per-IP only (`throttle:5,1`) — add a per-email limiter. Sanctum tokens: 30-day expiry with no rotation — shorten + add refresh.
- **R22.** Empty catch swallows the WordPress connection-test failure then saves the (possibly bad) credentials anyway (`Marketing/IntegrationController.php:211-224`). Log the throwable; block/flag save when the test fails.

### Data quality & performance
- **R23.** Import writes `phone = ''` to satisfy NOT NULL (`PatientImportExportController.php:221`) — defeats dedup and any future unique. Make phone nullable (additive change) and store null, or route empty-phone rows to review.
- **R24.** `communication_queue.overdue_since` is a stored human string ("2 hours") that goes stale instantly; `patients.total_billed/total_received` are denormalized with no reconciler. Compute overdue at query time; nightly reconcile of patient rollups from `invoice_payments`.
- **R25.** Reports duplication: `ReportsController` and `Api/V1/ReportController` compute overlapping KPIs separately with raw joins in controllers — exactly the drift the PatientService pattern prevents (C8 lives in only one of them). Extract a `ReportingService` used by both.
- **R26.** Recall: `recall.general_days` setting is display-only (`RecallEngineService.php:108-123`) — clinics changing the interval in Settings see no change. Wire it into both hardcoded `subMonths(6)` cutoffs and re-run `automation:parity recall`.
- **R27.** Repo hygiene: `under_review/` contains a full pre-V1 git object store, retired blades, and a stale lock. Audit the old history for secrets, then move it out of the tree.
- **R28.** Stale provenance docblocks: `clinical-library/dashboard.blade.php:6` claims "static placeholder" but is live; the file-viewer claims nothing but is fake (C6). Fix both so in-file notes can be trusted.
- **R29.** "Coming soon" panels reachable in live UI (campaign performance tabs, campaigns "pagination coming soon", cloud-storage settings). Hide behind flags/roles; add real pagination to campaigns index.
- **R30.** `PatientRegistered` domain event has zero subscribers and no `patient.registered` activity — no welcome/onboarding automation can ever trigger. (Held for product decision previously — decide, then wire or remove.)

---

## 4. FUTURE ENHANCEMENT — Do not delay launch for these

- **F1.** Introduce backed PHP enums (`AppointmentStatus`, `InvoiceStatus`, `CommunicationStatus`) and cast model attributes — there is no `app/Enums` today and C8/R14 are the first two casualties of string drift across web/API/mobile.
- **F2.** GST computed on pre-discount base when header `discount_pct` is used (`Invoice.php:134-147`) — slight GST overstatement; recompute on the discounted taxable base when GST billing matters.
- **F3.** `RulesEngine::evaluate()` runs synchronously in the request's `afterCommit` — fine today; queue it when actions get heavier.
- **F4.** Recall engine logs system events against `Patient::first()` (`RunRecallEngine.php:69-83`) — polluting a random patient's timeline; use a system subject.
- **F5.** `trustProxies(at: '*')` (`bootstrap/app.php:35`) — pin to the Caddy proxy IP if the container is ever directly exposed.
- **F6.** Standardise the remaining numeric `{!! json_encode() !!}` chart embeds on `@json` (finance analytics blades) to remove the footgun class entirely.
- **F7.** Insights incremental recompute is flag-dark (`insights.signals` OFF) — intended staging, but keep the per-flag verification matrix current (the `*:parity` commands exist for exactly this) before flipping any of the ~25 OFF flags.
- **F8.** Tighter named throttles on heavy report/summary API endpoints once report caching lands.
- **F9.** Campaign performance numbers and video durations are placeholders presented as data (`CampaignController.php:77`, `EducationContentController.php:138`) — label "not yet available" until real sources are wired.
- **F10.** Recall queue: add a one-tap "called — no answer, retry tomorrow" action (`attempt_count`/`next_action` columns already exist, no UI uses them).
- **F11.** Walk-in blocked-slot error loses typed input on the non-JSON fallback (`AppointmentController.php:144` — missing `->withInput()`).

---

## 5. What's Designed Well (preserve this)

- **Finance chain:** transactional multi-writes, soft-delete voids with `FinanceTransaction` flips (real audit trail), `withTrashed()` number sequencing, `decimal:2` throughout, compensating stock movements instead of deletes, server-authoritative convenience fees, password+reason on destructive patient actions.
- **Event backbone:** `ActivityEngine::log()` never throws, auto-resolves relationships, defers side-effects to `afterCommit`; `RulesEngine` is config-driven with cooldowns and failure-routing; newer producers pass `relationship_id` explicitly (the historic silent-skip gotcha is closed for the wired events).
- **Consent:** every patient-facing WhatsApp send routes through the DPDP `consentGate()`; the one historic bypass (prescription wa.me link) was explicitly closed.
- **API layer:** uniform envelope via `ApiController` traits, FormRequests + Resources, Sanctum with expiring ability-scoped tokens, login throttling, no user-enumeration on login errors, shared services (`PatientService`, `InvoicePaymentService`) consumed by both web and API — this is the template the rest of the app should converge on.
- **Security posture:** clinical media genuinely moved off the public disk behind auth + branch check + download audit; webhook HMAC verification is constant-time and fails closed; raw SQL is parameterized; no debug leftovers; 2FA secrets encrypted at rest.
- **Feature flags:** centrally declared, fail-safe to `false`, DB-overridable, with dry-run-defaulted parity/backfill commands — real staged-cutover discipline.
- **Tenancy scaffolding:** `BelongsToBranch` + `BranchScope` + `BranchSetting` are the right *shape*; the service layer gives one clean seam to enforce tenancy through.
- **UX:** mixed-dentition per-tooth chip (correct pediatric model), broad empty-state coverage, fast topbar patient search, sane click-counts on the high-frequency actions (payment = 3 clicks, booking = 3 clicks).

---

## 6. Multi-Tenancy Readiness — the honest picture

**Verdict: onboarding clinic #2 today would expose clinic #1's patient data. This is not a flag-flip; it is a focused multi-week hardening effort. It is, however, an evolution of the existing scaffolding — not a rewrite.**

The five structural problems, in dependency order:

1. **Two disjoint tenant keys, no tenant table.** Clinical/ops tables use `branch_id` (~25 migrations); finance/marketing/inventory use `clinic_id` (~30 migrations, all `finance_*`/`mkt_*`). There is no `clinics` table and no FK tying the keyspaces together. *Fix:* create a canonical `clinics` table; treat `branch_id` as a sub-partition **within** a clinic; standardise the isolation scope on `clinic_id`.
2. **The `clinic_id` resolver reads a column that doesn't exist.** `ResolvesClinicId.php:25` → `auth()->user()->clinic_id ?? 1` — but `users` has no `clinic_id` column *(verified)*, so it is always `1`. `FinanceController.php:667, 1277` hardcode `'clinic_id' => 1` on writes, and there are zero `where('clinic_id', …)` read filters in finance. Finance/marketing tenancy is decorative today. *Fix:* add `clinic_id` to `users`, backfill, remove the `?? 1` and hardcoded writes.
3. **Admin bypasses the isolation scope.** `BranchScope.php:37-39` exempts `isAdminRole()` entirely — correct for branches of one clinic, fatal for SaaS where *every clinic owner is an admin*. *Fix:* a separate, non-bypassable tenant scope; the admin bypass may only widen visibility within a tenant. The scope must also fail **closed** (it currently returns all rows for a user with null branch, and rows default to `branch_id = 1`).
4. **Automatic isolation covers 3 of ~167 models** (`Patient`, `Appointment`, `LabCase`) — and even there the scope documents itself as "effectively inert today." Consultations, treatment plans, tasks, inventory, prescriptions, finance, marketing have no automatic guard. *Fix:* apply the tenant trait to every tenant-owned model + a test asserting each model boots the scope.
5. **Global singletons.** `AppSetting` (clinic name, GST, print config) has no tenant column — clinic #2 would overwrite clinic #1's identity. `users.branch_id` is `unsignedTinyInteger` (cap 255, no FK) vs bigint FKs elsewhere. *Fix:* tenant-scope `app_settings` (the `BranchSetting` pattern already does this correctly); normalise the key columns.

Plus the auth-parity items (H13 MFA bypass, H14 dual authz systems) which become tenant-isolation issues the moment multiple organizations share the instance.

**Suggested sequence for tenant #2 readiness:** `clinics` table + `users.clinic_id` → backfill `clinic_id` across owned tables (additive migrations) → non-bypassable `ClinicScope` on all owned models, fail-closed → tenant-scope `app_settings` → remove `?? 1` / hardcoded writes → H13/H14 auth parity → a tenant-isolation test suite that creates two clinics and asserts zero cross-reads. Design target of 100 clinics is comfortably served by this single-DB + global-scope approach; nothing more exotic is warranted yet.

---

## 7. Suggested Fix Order (pre-launch runbook)

**Week 1 — money + safety (C1–C4, C11, H5, H6):** the coupon/wallet trust bugs, payment idempotency + double-submit, wallet lock, scheduling conflict enforcement, plan-item deletion guard. These are the bugs that lose money or double-book chairs in week one of real use.

**Week 1, same pass — embarrassment removals (C5, C6, H22, H23):** hide Timeline, wire the file viewer, fix/remove dry-run, real badge counts. Hours, not days.

**Week 2 — data + wiring (C7–C10, H10–H12, H15, H18–H19, H25):** webhook routes, reports status fix, phone index + import overhaul, recall send path, dead-rule producers, cron/queue health check, the two XSS one-liners, the two index migrations, run the pending backfills.

**Weeks 3–4 — hardening (remaining High + top Recommended):** MFA/authz parity, PHI encryption extension, audit HMAC, AP dedupe, vendor-invoice status, document-number locking, backup encryption, session config.

**Before clinic #2 — Section 6 in full.**

**Deploy checklist additions:** cron `schedule:run` + supervised `queue:work` verified; `relationship:backfill --apply --force` + `action-options:sync-closes-task --apply` executed with count verification; `SESSION_SECURE_COOKIE`/`SESSION_ENCRYPT`/`session.absolute_lifetime` set; `security:selftest` green; `APP_DEBUG=false` asserted.

---

*Verification note: the 8 highest-impact claims (C1, C5, C7, C8, C9's phone column, H15, the appointments enum, and the missing `users.clinic_id`) were independently re-verified against source during this review. All confirmed. Remaining findings carry direct file:line excerpts from the audit passes.*
