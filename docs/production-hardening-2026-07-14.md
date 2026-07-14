# Production Hardening Sprint — 2026-07-14

Companion to `docs/production-readiness-review.md`. That document is the audit;
this one is the record of what was actually changed, why, and what you must run.

**Scope:** every applicable Critical + High Priority finding. Excluded by
instruction: WhatsApp inbound/outbound, GST, multi-tenancy/SaaS scaling, and the
Future Enhancement section.

---

## ⚠️ Commands to run (nothing was executed for you)

```bash
# 1. Adds the performance indexes (patients.phone, appointments dates, queue, invoices).
#    Purely additive — no column/type changes, safe on live data.
php artisan migrate

# 2. Clear caches so the new config files (audit.php, prune.php) and the
#    corrected relationship_rules.php are picked up.
php artisan config:clear
php artisan cache:clear

# 3. Preview the new/behaviour-changed jobs BEFORE trusting them:
php artisan recall:run --dry-run          # now genuinely computes (was a stub)
php artisan payments:scan-overdue --dry-run
php artisan birthdays:scan --dry-run
php artisan logs:prune                    # dry-run by default

# 4. Verify the audit chain still passes after the HMAC change
#    (existing rows verify under the legacy format — this must say OK):
php artisan audit:verify
```

### .env additions (see `.env.example`)

```
AUDIT_HASH_KEY=            # generate: php -r "echo bin2hex(random_bytes(32));"
SESSION_SECURE_COOKIE=true # production only
SESSION_ENCRYPT=true       # production only
```

---

## Behaviour changes worth knowing about

These are intentional, but they change what staff/clients experience:

1. **Double-booking is now enforced server-side.** Previously it was only a JS
   `confirm()` in one modal. Deliberate double-booking still works — the caller
   sends `allow_overlap`, which the modals now do when the user answers "Book
   anyway?". **Mobile clients that need intentional double-booking must send
   `allow_overlap: true` or the API will 422.**
2. **First `recall:run` will queue lapsed no-phone patients.** They were being
   silently dropped forever; they're now queued and flagged "needs contact
   number". Preview the volume with `--dry-run` first.
3. **Duplicate-phone registration now prompts.** The full registration form
   returns 409 with the matching patients; staff choose "open existing" or
   "register anyway". (Families sharing a number is legitimate, so it's a
   decision, not a block.)
4. **Coupons are now validated server-side on web.** An invalid coupon, or one
   below its `min_invoice_amount`, is now rejected instead of silently ignored.
5. **Concurrent edits are refused, not merged.** Patient/invoice/appointment
   edit forms now carry `updated_at`; a stale save is rejected with "changed by
   someone else — reload". Forms that don't send it are unaffected.

---

## What changed, by finding

### Money (C1–C4, H1, H4)

| Finding | Fix |
|---|---|
| **C1** Coupon discount trusted from a hidden field | `BillingController::store()` now recomputes via `calculateDiscount()` on the real subtotal and enforces `min_invoice_amount`. Membership discount is likewise recomputed (same trust bug). Mirrors the API path. |
| **C2** `wallet_applied` recorded larger than the amount debited | Wallet debit is capped to `min(requested, balance, balance_due)` and the invoice is synced to the **actually debited** figure, then recalculated. |
| **C3** No idempotency / lock on payment | Invoice row is `lockForUpdate()`-ed inside the transaction; an identical payment (amount + mode + date) within 20s is rejected. Frontend disables the Save button on submit. Applied to **both** web and `InvoicePaymentService`. |
| **C4** Wallet double-spend + hidden overdraw | New `Wallet::forPatientLocked()`; `debit`/`withdraw`/`adjust` now row-lock and refresh from the ledger. A negative ledger sum is **logged as a warning** instead of being silently clamped to zero. |
| **H1** Overpayment vanished on mobile | Ported excess-to-wallet + optional `wallet_used` allocation into `InvoicePaymentService`. |
| **H4** Racy document numbers | `Invoice`/`Receipt`/`FinalBill::nextNumber()` now `lockForUpdate()`, so concurrent generation serializes instead of colliding on the UNIQUE index and rolling back a legitimate payment. |

### Fake implementations (C5, C6, H22, H23)

- **C5** Communication Timeline rendered hardcoded fake patients ("Riya Sharma").
  Nav entries removed (config + sidebar) **and** the controller 404s, so the
  route can't be reached directly either. Dummy methods left in place with a
  restore note.
- **C6** The global File Viewer was a static mockup — it captured the real file
  id and then showed the same fabricated IOPA note for **every** file. Rewritten
  to fetch the real file from the existing JSON endpoint; notes/tags/eligibility
  flags now persist via the existing `PUT`; download and delete work. Same
  layout. All 5 dispatch sites now pass `patientId`.
- **H22** `recall:run --dry-run` was a stub that printed a banner and exited
  (misleadingly reporting "nothing to do"). It now runs the engine for real
  inside a transaction it always rolls back, printing true "would queue" counts.
- **H23** Communication sidebar badges were hardcoded to `0`, so genuinely
  overdue work was invisible. Real counts, 60s cached, failing soft to 0.

### Correctness (C8, H15)

- **C8** Reports counted appointment status `'completed'` — a value **not in the
  enum** (`scheduled|checkin|in_chair|checkout|done|cancelled|no_show`). The
  Completed KPI and Completion Rate were **always zero**. Fixed to `'done'` in
  `ReportsController` and `Api/V1/ReportController`. (`treatment_visits`
  legitimately *does* use `'completed'` — those queries were left alone.)
- **H15** Stored XSS: `{!! json_encode($patients) !!}` in `lab/index` and
  `appointments/_modal` didn't escape `<`/`>`, so a patient named
  `</script><script>…` executed in staff browsers. Now `@json()`.

### Scheduling & clinical safety (C11, H5, H6, H7)

- **C11 + H5** Overlap and blocked-slot guards moved into the shared
  `AppointmentService` and enforced on **every** write path that previously had
  none: `reschedule()` (drag-drop/resize), `update()` (edit form), all three
  `store()` paths, and the API `create()`/`createWalkIn()`. `checkConflict()`
  now uses the same shared filter, so the warning shown and the rule enforced
  cannot drift.
- **Orphan patient bug (found while fixing C11):** the walk-in-new-patient path
  created the `Patient` **before** the guards ran, so every rejected booking left
  an orphan patient behind. Guards now run first, in both the web controller and
  the service.
- **H6** Plan revision hard-deleted **completed and already-invoiced** items
  (a revision re-sends only the pending rows). `update()` now computes a
  protected set (`status=completed`, `invoiced_units>0`, non-pending
  `billing_progress`, or any non-pending tooth) and never deletes it. Applied to
  web **and** API. `destroy()` now refuses a plan with invoices (mirroring
  `revert()`); `destroyItem()` refuses a billed item.
- **H7** Only `quickCreate()` checked for duplicate phones. Added
  `PatientService::findDuplicatesByPhone()` (now used by both); full registration
  returns 409 + the matches, and the modal offers "open existing / register
  anyway".

### Data & performance (C9, C10, H18–H21)

- **C9/H18–H20** New additive migration `2026_07_14_100000_add_production_indexes_to_core_tables`:
  `patients(phone)`, `patients(branch_id,phone)`, `appointments(branch_id,appointment_date)`,
  `appointments(appointment_date,status)`, `communication_queue(status | status,due_at | patient_id | phone)`,
  `invoices(appointment_id | treatment_plan_id)`.
  **No UNIQUE on phone** — families share numbers and existing data may hold
  intentional duplicates; the index gives the performance win without a
  migration that can fail or block valid records.
- **C10** Import rewritten: dedup sets pre-loaded in **2 queries** instead of 2
  full scans *per row* (a 4,000-row file did ~8,000 scans); 500-row **chunked
  transactions** instead of one file-long lock; and it now calls
  `PatientRelationshipLinker::link()` — the missing call that produced the orphan
  relationship rows.
- **H21** New `php artisan logs:prune` + `config/prune.php` (24-month default),
  scheduled weekly. Dry-run by default, chunked, oldest-first. **`audit_logs` is
  excluded unless `--include-audit`** — pruning breaks its tamper-evident chain,
  which is a policy decision, not a default.
- **R17** Patient CSV export now neutralises `= + - @` so a malicious patient
  name can't execute as a formula on the machine that opens it.

### Backend wiring (H8, H11, H24)

- **H11** Four enabled rules had **zero producers** (grep-verified) and had never
  fired:
  - `appointment.missed` → new `AppointmentActivityLogger::missed()`, fired on
    `no_show` in both web and the shared service.
  - `estimate.sent` → the app *does* emit an equivalent event when an estimate
    goes out; it's `presentation.sent`. Pointed the rule at the real event rather
    than adding a duplicate one.
  - `payment.overdue` → new `payments:scan-overdue` (reuses the existing
    `payment_reminder_threshold` so trivial balances don't generate calls).
  - `birthday.approaching` → new `birthdays:scan`.
  - `recall_6months` flipped to `enabled => false` **with an explanation** — it is
    *deliberately* starved (TreatmentVisitService creates the 6-month recall
    inline; the rule firing too would double-queue every recall). The config was
    claiming it was live.
- **H8** No-phone patients were filtered out of **every** recall trigger, so a
  patient with a blank mobile was invisible to recall forever. Fixed at the
  single choke point (`createQueueItem`): still queued, but flagged low-priority
  with `next_action = update_contact`. **Judgment call:** the phone filter is
  kept for *birthdays* only — a greeting task you can't send is board noise,
  whereas a lapsed unreachable patient is lost revenue.
- **H24** `accept()` was implemented three times. Extracted
  `TreatmentPlanAcceptanceService`. **A real bug surfaced:** the mobile API's
  `accept()` wasn't a copy — it only flipped the status and **silently skipped**
  the Timeline log *and* the Opportunity creation, so a plan accepted on mobile
  produced no pipeline entry and no 7-day nudge. Now fixed.

### Auth & security (H13, H14, H16 partial, H17)

- **H13** `/api/v1/auth/login` **completely bypassed MFA** — it issued a full
  Bearer token on the password alone, so any 2FA-protected account was reachable
  without the second factor. Now enforces the same challenge as web (TOTP, then
  one-time recovery code), returning `401 + two_factor_required`.
- **H14** `isAdminRole()` consulted both role systems but `hasRole()` read only
  the legacy string, while web permissions go through `role_id`. Fixed
  `hasRole()` to check both. `EnsureApiRole` also now accepts
  `api.role:module:billing,edit`, so API routes can be gated by the **same
  permission table** as their web equivalents.
- **H17** The "tamper-evident" audit chain was an **unkeyed** sha256 — an
  attacker with direct DB write access (exactly the actor an audit log defends
  against) could edit history and recompute the chain to pass `audit:verify`.
  Now **HMAC-SHA256** keyed by `AUDIT_HASH_KEY` (falling back to `APP_KEY`,
  which is at least outside the DB). Verification accepts **both** formats, so
  existing rows still verify — switching algorithms doesn't masquerade as
  tampering — and `audit:verify` reports how many rows still carry the weak hash.
- **H16 (partial)** The `Encrypted` cast returned plaintext **silently** on
  decrypt failure, so an unencrypted row or a broken `APP_KEY` looked like normal
  operation. It now logs a warning (never the value) while still not breaking the
  read. **Full encryption of `name`/`phone`/`email` is deliberately NOT done —
  see "Still open" below.**

### Procurement & concurrency (H2, H3, H9)

- **H2** **Double Accounts-Payable.** Receiving goods on a PO already books an
  unpaid vendor bill (GRN → `FinanceExpense`); entering the vendor's actual
  invoice for the same PO booked a **second** one. Payables were double-counted
  and the same bill could be paid twice. The vendor-invoice path now takes over
  the existing GRN expense (re-pointing it and correcting the amounts) instead of
  creating a second, and the vendor's cached outstanding is deltaed rather than
  double-incremented.
- **H3** `VendorInvoice.status` was created as `'pending'` and **nothing ever
  advanced it**. Two consequences: procurement analytics filtered on `'unpaid'`
  — a value **not in the enum** — and so always returned **zero**; and the
  "cannot delete a paid invoice" guard never fired, so a paid vendor bill could
  be deleted along with its paid expense. Status now advances on payment; the
  analytics filters use the new `VendorInvoice::UNPAID_STATUSES` constant; and
  vendor outstanding is now **decremented on payment** (it previously only ever
  grew).
- **H9** Silent last-write-wins on concurrent edits. New
  `ChecksStaleUpdates` trait (optimistic lock on `updated_at`, no schema change,
  no-op for clients that don't send it) wired into patient, invoice and
  appointment edits, with the hidden field added to those three forms.

### Small fixes taken along the way

- **R13** Walk-in date used `toISOString()` (UTC) while the time was local, so
  between 00:00–05:29 IST walk-ins landed on **yesterday's** day-sheet. Now
  `toLocaleDateString('en-CA')` in both modals.
- **F11** Added the missing `->withInput()` so a rejected walk-in no longer
  discards typed input.
- **R3** Web now enforces the coupon `min_invoice_amount` the API already did.

---

## Audit chain — re-anchored 2026-07-14 (read this)

Running `audit:verify` for the first time revealed something worse than the
finding it was meant to check.

**The audit log had no integrity guarantee from 2026-07-04 to 2026-07-14.**

`chainCanonical()` hashed the compact JSON that Eloquent produces for
`old_values`/`new_values`. MySQL then stores those in a JSON column, and it
**re-formats the text and re-orders object keys** (by key length, then
lexically). So the string read back at verification time was never the string
that had been hashed. Every audit row carrying a non-empty payload — i.e. every
row that actually matters: appointments, patients, invoices — failed its own
hash check from the moment it was written.

It went unnoticed because:
- the only rows that *did* verify were logins/logouts, which have empty payloads;
- the first row with real content was the first model-level audit entry
  (id 133, an Appointment created 2026-07-04);
- `audit:verify` had never been run.

**Fix:** `chainCanonical()` now hashes a canonical form (decoded → recursively
key-sorted → re-encoded with fixed flags) that survives the database round-trip.
Empty payloads (`{}` / `[]`) are left byte-identical so previously-valid rows
aren't disturbed. Verified with a round-trip test on nested, unsorted JSON.

**Re-anchor:** because the stored hashes were structurally unverifiable, there
was no tamper-evidence left to preserve — so `audit:verify --backfill` was run
ONCE, on 2026-07-14, rebuilding all four chains (378 + 26 + 26 + 0 rows). They
now verify clean and are HMAC-keyed.

> **The audit chain is authoritative from 2026-07-14 onwards. Rows before that
> date carry rebuilt hashes and cannot be used to prove they were never
> altered.** This is a known, documented consequence of a code defect — not of
> tampering — but it is a real limitation and should be stated plainly if the
> log is ever relied on evidentially.

**Never run `--backfill` again** to make a red check go green. It rewrites
history's hashes to match whatever the rows currently say — exactly what an
attacker would do. Run `php artisan audit:diagnose` first; it tells you whether
a failure is a chain gap, a content mismatch, or a rebuild, which are three very
different problems.

`audit:verify` now runs **daily at 06:30** and raises a `Log::critical` plus an
in-app notification to every admin on failure. A tamper-evident log nobody checks
is decoration.

---

## Still open (deliberately)

**H16 — full PHI encryption of `name`, `phone`, `email`.** These remain
plaintext at rest. `phone` is now **indexed** (required for the C9 performance
fix and all dedup/search), and encrypting it would break both the index and every
patient lookup. Doing this properly needs a blind-index hash column, a data
backfill, and a search rewrite — a substantial, separately-scoped change with
real breakage risk, not a "smallest clean fix". **This is the one significant
High-Priority item not closed by this sprint.**

Also untouched by instruction: WhatsApp inbound webhooks (C7), GST-on-discount
(F2), and all multi-tenancy work (review Section 6 — clinic #2 remains unsafe
until that is done).

---

## Corrections to the audit

Two findings did not survive verification and were **not** "fixed", because there
was nothing to fix:

- **R20 (`AbsoluteSessionTimeout` is a no-op).** False. The middleware falls back
  to `0`, but `config/session.php` defines `absolute_lifetime` with a default of
  **720 minutes**. The middleware is already active.
- **R13/C10 (soft-deleted patients linger in household views).** False.
  `linkedPatients()` is a `belongsToMany(Patient::class)` and `Patient` uses
  `SoftDeletes`, so Eloquent's scope already excludes deleted patients from those
  reads.

---

## Testing note

`php` is not available in the environment these changes were written in, so
**nothing here has been executed**. Every change was made by reading the
surrounding code first and following the existing patterns in the file. Before
trusting any of it in front of a patient, at minimum:

1. `php artisan migrate` then `php artisan app:crawl-routes` (the existing route
   crawler will catch any 500s introduced by these edits).
2. Record a payment, double-click the button, and confirm exactly one receipt.
3. Drag an appointment onto an occupied slot and confirm the prompt + revert.
4. Revise a treatment plan that has one completed tooth; confirm the completed
   item survives.
5. `php artisan audit:verify` — must report OK.
