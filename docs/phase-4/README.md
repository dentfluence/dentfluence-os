# Phase 4 — Communication (Engine + full Guard + Notification)

Blueprint deliverables: (1) a Communication Engine as the single patient send/receive path, (2) the full 8-factor Guard with consent never overridden, (3) Notification consolidated to one store. Built in the same additive, flag-gated, shadow-first style as every prior phase — nothing here changes live behaviour unless a flag is deliberately flipped, except where noted.

Research before building found the real starting state didn't match the docs exactly: two disconnected consent systems existed (`CommunicationGuard`, built but never called; `OutboundMessageService::consentGate()`, real and live on every governed WhatsApp send), two live sends bypassed consent entirely, and "notification consolidation" turned out to be smaller than it sounded. Each piece below documents what was actually found and why the fix looks the way it does.

## Piece 1 — Closed the Prescription WhatsApp consent bypass

**Problem found:** `PrescriptionController::sendWhatsApp()` built a raw `wa.me` deep-link with zero consent check — a real, live DPDP gap (unlike the architectural debt elsewhere in Phase 4).

**Fix:** `CommunicationGuard` gained a new public method, `hasWhatsAppConsent(?Patient $patient, string $type = 'service')`, which mirrors the exact purpose-key lookup `OutboundMessageService::consentGate()` already uses live (patient → `ConsentPurpose` by key `whatsapp_comms`/`marketing_promotions` → `PatientConsent::isGranted()`), but is patient-scoped instead of `WaThread`-scoped so it can be called before a thread exists. `PrescriptionController::sendWhatsApp()` now calls it first.

**Deliberately NOT merged into `OutboundMessageService`** — that class's live consentGate() also handles unknown numbers and the 24h reply window, which a patient-only check can't. Reconciling the *logic* (same tables, same purpose keys) was judged safer than touching an already-live send path unsupervised. `CommunicationGuard::patientHasConsent()` (used by `decide()`) now delegates to `hasWhatsAppConsent()` for the `whatsapp` channel — this closes the original Phase 0 stub ("real lookup arrives in Phase 4").

**Enforcement stays behind `guard.consent_required`** (the same flag Phase 0 already declared, default off). Every blocked attempt is logged either way — you'll see it in the log before you ever see it as a blocked send. This means the bypass isn't automatically closed the moment this ships; it's ready to close the moment you confirm `ConsentPurposeSeeder` has run and flip the flag. Left this way deliberately: flipping the flag live, unsupervised, on a daily-use feature (prescriptions) without being able to verify the purpose data is actually seeded felt like the wrong risk to take alone.

Files: `app/Services/Relationship/CommunicationGuard.php`, `app/Http/Controllers/Prescription/PrescriptionController.php`, `resources/views/prescriptions/show.blade.php` (JS now shows the block reason instead of silently reloading). Test: `tests/Feature/Relationship/PrescriptionConsentGateTest.php`.

**Verify:**
```
php artisan test --filter=PrescriptionConsentGateTest
```
Then, when ready to actually enforce: confirm `php artisan db:seed --class=ConsentPurposeSeeder` has run, then `Feature::set('guard.consent_required', true)`.

**LIVE-TESTED 2026-07-03 on the real app** (not the test DB) via Chrome MCP + a temporary route (no terminal access available to run tinker). `guard.consent_required` is now **ON in production**. Verified against a dedicated test patient (TDC-06982 "ZZTEST GuardVerification"): blocked with `422` + `"Patient has not granted 'WhatsApp messages' consent (DPDP)."` before consent, then `200` + a real wa.me link after granting consent via `/consent/patient/{id}`. This is a real, live behaviour change for every patient now, not just the test one.

## Piece 1c — Prescription send also checks Do-Not-Contact + channel eligibility

After closing the consent gap, extended the same send path with the two Guard factors from Piece 3 below that make sense for a direct, doctor-requested message: Do-Not-Contact and channel eligibility (does the relationship have a phone on file at all).

**Deliberately does NOT call `CommunicationGuard::decide()`** — that pipeline also runs frequency/quiet-hours/birthday-block checks meant for batch/automated contact (recall campaigns, scheduled reminders). Silently applying a "max 3 contacts in 7 days" rule to a doctor handing a patient their prescription would be a real, surprising behaviour change nobody asked for. Instead, added a new focused method, `CommunicationGuard::checkDoNotContactAndChannel()`, that only checks those two factors.

Only runs when the patient is linked to a Master Relationship (`patient->relationship_id`) — unlinked patients (identity linking isn't fully live yet) pass through unaffected, same conservative default as everywhere else in Phase 4. Gated behind `guard.full_8factor` (still off — not live-tested this round, only via the automated test suite).

Files: `app/Services/Relationship/CommunicationGuard.php` (`checkDoNotContactAndChannel()`), `app/Http/Controllers/Prescription/PrescriptionController.php`. Test: `tests/Feature/Relationship/PrescriptionGuardFactorsTest.php`.

**Verify:**
```
php artisan test --filter=PrescriptionGuardFactorsTest
```

## Piece 1b — Inventory PO WhatsApp: assessed, not changed

`InventoryService::purchaseOrderWhatsappMessage()` sends a raw `wa.me` link too, but to a **vendor** (B2B purchase-order fulfillment), not a patient. DPDP consent governs personal data of the individuals the clinic treats — there's no vendor-consent system in this codebase, and there shouldn't be one bolted onto the patient `PatientConsent` machinery just to "close a bypass" that isn't actually a patient-consent issue. Left as-is. Its real disposition (per `docs/gap-analysis-current-to-target.md`) is an eventual move to a future Integration Engine (Phase 7), not a Guard concern.

## Piece 2 — Notification store: documented the existing single store, not a schema cutover

**What research found:** two tables (`app_notifications`, `relationship_notifications`) are dual-written by `NotificationEngine`, but there was never a "third scattered system" to merge — and `relationship_notifications`' own JSON CRUD API has zero UI callers. It looked like a bigger job than it was.

**Real complication found while building:** `NotificationEngine::recentlySent()` — the 24h anti-spam dedup guard — reads `relationship_notifications`, not `app_notifications`, because `app_notifications` has no `relationship_id` column to dedup against. Naively "stopping the second write" behind the `notifications.single_store` flag would have silently broken duplicate-notification suppression. Rather than force that through unsupervised, this pass:

- Formalizes `app_notifications` as the documented canonical single store (matches what's already true in practice — the topbar bell and `NotificationsController` are the only thing users see; 23 write call sites elsewhere already target it directly).
- Keeps `relationship_notifications` explicitly as an internal metadata/dedup table, not a second user-facing store — its unused CRUD API is not a live alternate read path.
- Splits the two writes in `NotificationEngine::createForUser()` into independent try/catch blocks, in that order — `app_notifications` (what the user sees) writes first and isn't guarded by the second write's success; a `relationship_notifications` failure now logs clearly instead of potentially reading as "the user's notification failed to send."

**Not done this pass (flagged for later):** a real read/write cutover would need a `relationship_id` (+ ideally `type`) column added to `app_notifications` first, so dedup can move over before the second table's write is dropped. `notifications.single_store` stays declared-but-off — it's not yet wired to anything, so flipping it today does nothing (safer than wiring it to a half-finished cutover).

Files: `app/Services/Relationship/NotificationEngine.php`. Test: `tests/Feature/Relationship/NotificationEngineTest.php`.

**Verify:**
```
php artisan test --filter=NotificationEngineTest
```

## Piece 3 — Reconciled the Guard + added the 3 missing factors

Covered above under Piece 1 for the consent reconciliation (`hasWhatsAppConsent()`). The 3 factors that were missing/partial (Preference, Context, Channel eligibility) are now implemented, all gated behind `guard.full_8factor` (declared, default off):

- **Do-Not-Contact** (new `relationships.do_not_contact` boolean, migration `2026_07_03_100002_...`): hard block, all channels, never relaxed by urgency — same tier as consent. This is the only genuinely new opt-out signal added.
- **Channel eligibility**: mechanical only — blocks a send if the relationship has no contact detail for that channel (no phone → can't WhatsApp/call/SMS; no email → can't email). Does not enforce per-channel opt-outs, because none exist in the schema yet.
- **Preference** (new `relationships.preferred_channel` nullable string): informational only, logged into every `GuardDecision`'s factors while the flag is on. Deliberately never blocks — whether "sent on a non-preferred channel" should be a soft-route signal or a hard block is a product decision, not an architecture one, and this pass didn't invent an answer.
- **Context**: a declared but empty seam (`factors['context'] = 'not_evaluated'`) — the docs never specified what "relationship context" means precisely, and shadow-implementing a guessed rule on the most legally sensitive code path in the app was judged worse than leaving it honestly unimplemented.

Files: `app/Services/Relationship/CommunicationGuard.php`, `app/Models/Relationship.php`, migration `2026_07_03_100002_add_guard_preference_fields_to_relationships_table.php`. Test: `tests/Feature/Relationship/GuardEightFactorTest.php`.

**Verify:**
```
php artisan migrate
php artisan test --filter=GuardEightFactorTest
php artisan test --filter=CommunicationGuardHardeningTest
php artisan test --filter=CommunicationGuardCharacterizationTest
```
The last two are the pre-existing Phase 0 tests — they must still pass unchanged, proving this pass didn't alter default (flags-off) behaviour.

## Piece 4 — Communication Engine: built, tested, deliberately NOT wired in yet

`app/Services/Relationship/CommunicationEngine.php` is the documented single `send()` gateway the blueprint calls for. It runs `CommunicationGuard::decide()` first (shadow-logged always, only blocks if a Guard flag is on), then delegates real delivery to `OutboundMessageService` — whose own live consent gate stays untouched underneath. That layering means the engine can only ever add checks on top of what's already enforced, never remove one.

**What this pass deliberately did NOT do: migrate any existing call site to use it.** The two best-fit candidates — `whatsapp:send-reminders` (scheduled appointment reminders) and `reviews:request` (scheduled review requests) — both run unsupervised via cron. Routing a live, daily, patient-facing send through a brand-new code path for the first time, with no one available to watch the first real run, was judged the wrong risk to take while you're away. `comm.single_gateway` stays a documentation flag — nothing reads it yet, because nothing calls `send()` yet.

**Also out of scope this pass:** `send()` only wraps plain-text sends (`OutboundMessageService::sendText()`), not template sends (`sendTemplate()`, which the reminder scheduler actually uses, with its own `dedup_key`/approval semantics) — wrapping that needs its own careful pass once a call site is actually being migrated. The red-team doc's async/batching concern (8 Guard lookups per message → a throughput ceiling on bulk marketing sends) is also unaddressed — moot today since nothing sends bulk WhatsApp through this engine yet, but a real blocker before any future bulk-marketing-via-WhatsApp feature routes through it.

**Next step, when you're back and available to watch a live run:** pick one low-stakes, manually-triggered call site (not a cron job) to migrate first — e.g. the Prescription WhatsApp send from Piece 1, or a single-patient "send review request" button if one exists — behind `comm.single_gateway`, verify it in the browser, then move to the scheduled senders once that's proven.

Files: `app/Services/Relationship/CommunicationEngine.php`. Test: `tests/Feature/Relationship/CommunicationEngineTest.php`.

**Verify:**
```
php artisan test --filter=CommunicationEngineTest
```
