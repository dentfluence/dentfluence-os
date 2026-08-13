# WhatsApp / PRE Communication — Production Cleanup (v3, minimal)
**CEO Directive 2026-08-04 (v3): production-ready cleanup, not another architecture pass. Supersedes v2's schema changes and file relocation.**
**Status: DESIGN — no code changed yet**

---

## 0. What changed from v2

v2 proposed adding columns to `relationship_contact_log` and moving three service files into a new folder. Both are out per this directive — the table stays channel-neutral and untouched, nothing moves. What's left, once you strip those out, is genuinely small: **one routing fix, one small new code branch, one duplicate-logic removal, one config addition, one settings-page relabel.** No migration. No file moves. No namespace changes.

---

## 1. Standing decisions (unchanged from your instructions)

- `WaThread` / `WaMessage` — **kept, untouched, no deletion date set.** They already do exactly what's needed: track the 24-hour reply window and give delivery-status webhooks something to match against (`wa_message_id`). Revisit removal only after the interactive-reply flow below has run in real use for a while — not part of this pass.
- `relationship_contact_log` — **zero schema changes.** It stays a generic, channel-neutral log. Anything WhatsApp-specific keeps living in `WaMessage`, which already has the right columns (`status`, `wa_message_id`, `template_name`).
- No file moves, no namespace changes. `app/Services/Whatsapp/*` and `app/Services/Communication/WhatsAppLinkService.php` stay exactly where they are. "Belongs under PRE Communication" is a conceptual/organizational fact, not something that requires a folder to prove it.
- WhatsApp Integration Settings (`IntegrationController::showWhatsappForm`/`saveWhatsapp`, the `PlatformConnection` store) — **kept, not deleted.** Only the settings-nav entry point changes (§5), the controller/view/storage are untouched.
- `WhatsAppInboxController` and the marketing broadcast panel — **left alone for now.** They're already dormant/non-functional respectively and cause no harm sitting there. Not touched in this pass; a candidate for later cleanup, not now.

---

## 2. What actually changes (five small, contained edits)

### 2.1 Route the webhook — the one real functional gap
```php
// routes/api.php
Route::get('/webhooks/prm/whatsapp',  [WhatsAppLeadController::class, 'verify']);
Route::post('/webhooks/prm/whatsapp', [WhatsAppLeadController::class, 'receive']);
```
This is still the actual P0 from the original audit — everything else in this plan is secondary to just doing this.

### 2.2 Interactive replies — one new branch in the existing `InboundMessageService`
No new class, no restructuring of the method. Add a check at the top of message handling: if the inbound payload is a `button_reply` (Meta's interactive-message format) matching a known button id (`confirm_appt_{id}` / `reschedule_appt_{id}` / `cancel_appt_{id}`):
- Call the existing appointment status-update method (whatever the Confirm/Cancel buttons in the UI already call — reuse it directly, don't duplicate the logic).
- Update the matching `WaMessage` row's `status` (column already exists).
- Everything else about `InboundMessageService` — recording inbound `WaMessage`/`WaThread` rows, the 24h window, free-text handling — stays exactly as it already works today. No stripping down.

### 2.3 Remove the one real duplicate — `OutboundMessageService`'s consent check
`OutboundMessageService::consentGate()` re-implements a `ConsentPurpose`/`PatientConsent` lookup that `CommunicationGuard::hasWhatsAppConsent()` (used by `WhatsAppLinkService`) already does correctly. Change `consentGate()` to call `CommunicationGuard::hasWhatsAppConsent()` instead of its own lookup. One method body, one file, behavior-preserving — both paths already enforce the same rule, this just removes the second copy of it.

### 2.4 Interactive button templates — config only
Add `Confirm`/`Reschedule`/`Cancel` button definitions to the existing `appointment_reminder` entry in `config/whatsapp.php`'s template array — the same array that already holds Meta template metadata for this message. No new config file, no merge with `config/communication.php` (that catalog stays exactly as it is — click-to-chat text and Cloud API template metadata are genuinely different things and don't need to become one file).

### 2.5 WhatsApp Integration Settings — relabel/relocate the nav entry only
Move where the "WhatsApp Business Connection" settings page is *linked from* — out of the Marketing → Integrations section and into a Communication settings section — without moving the controller, the view, or the `PlatformConnection` storage. Concretely: update the settings sidebar/nav entry and (if the URL should reflect the new home) the route's `prefix`/`name` in `routes/marketing.php` moves to `routes/communication.php` — a few lines in a routes file, not a controller relocation. If even that's more than you want, leave the URL as-is and change only the nav label/grouping.

---

## 3. What this plan deliberately does not touch

No database migrations. No deletions. No file moves. No namespace changes. `wa_threads`, `wa_messages`, `relationship_contact_log`, `WhatsAppInboxController`, the marketing broadcast panel, and the two service folders all stay exactly as they are today.

---

## 4. Step-by-step order

1. Route the webhook (§2.1). Test against Meta's sandbox before relying on it in production.
2. Add the interactive-button branch to `InboundMessageService` (§2.2).
3. Add the button definitions to `config/whatsapp.php` (§2.4) — do this before or alongside step 2, since step 2 needs the button ids to match something real.
4. Fix `OutboundMessageService::consentGate()` (§2.3) — independent of the others, can happen any time.
5. Move the settings nav entry (§2.5) — purely cosmetic, no functional dependency on the other steps, safe to do first or last.

Five independent, small, revertible diffs. Nothing here removes existing capability, so there's no point mid-plan where communication automation regresses.

---

## 5. Master Register note

Suggested entry, replacing the v2 note: "WhatsApp webhook routed (P0 closed); interactive Confirm/Reschedule/Cancel replies now update appointments automatically; one duplicate consent-check path removed. WaThread/WaMessage, relationship_contact_log, and the WhatsApp Integration Settings page were deliberately left unchanged in this pass — see `docs/pre-communication-cleanup-2026-08-04-v3.md`." Same as before, I haven't edited the dashboard HTML directly — this is the text for wherever it's maintained.

No code was written or modified in this session.
