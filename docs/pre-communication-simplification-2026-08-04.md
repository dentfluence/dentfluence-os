# WhatsApp → PRE Communication — Simplification Plan
**CEO Directive 2026-08-04 (v2): simplify, don't rebuild. Supersedes the earlier full-redesign proposal.**
**Status: DESIGN — no code changed yet**

---

## 0. What changed from the last proposal

The earlier document (`pre-communication-architecture-2026-08-04.md`) proposed a `CommunicationOrchestrator` / `CommunicationIntent` / `ChannelDriverInterface` layer. You're right to reject that at this scale — it solves a problem you don't have yet (multiple simultaneous channel implementations competing for one send pipeline) by adding abstraction you'd have to maintain alone. That document is now superseded; this one replaces it as the working plan.

The actual problem, restated simply: two things send WhatsApp messages today (`WhatsAppLinkService` for click-to-chat, `OutboundMessageService` for the Cloud API) and they each re-implement consent-checking slightly differently. There's a chat-inbox UI nobody uses. There's a message-archive database (`wa_threads`/`wa_messages`) that mostly sits empty because the Cloud API is disabled. There's a dead marketing campaign panel. And there's a fully-written webhook handler with no route pointing at it. None of that needs a new framework — it needs deleting, merging, and one route added.

---

## 1. What stays exactly as-is

These are already correctly scoped, single-purpose, and reusable. No changes.

- **`WhatsAppCloudService`** — the only class that calls Meta's Graph API. Correctly channel-specific, correctly isolated.
- **`WhatsAppLinkService`** — the click-to-chat send path (consent check → template render → `wa.me` link). This is already doing the job of "decide whether/what to send," it just does it for one channel. It's the thing every automation should be calling — no new wrapper needed around it.
- **Console commands** — `whatsapp:send-reminders`, `whatsapp:test`, `whatsapp:template`. Working automation entry points, no reason to touch them.
- **Consent infrastructure** — `CommunicationGuard`, `ConsentPurpose`, `PatientConsent`. Already channel-agnostic in design; the fix needed is making sure everything calls into it (see §3), not replacing it.
- **`VerifiesMetaSignature` trait**, webhook signature-verification logic — already correct, fail-closed, shared with Meta Lead Ads.
- **All the live call sites** — `TodayController::sendBirthdayWhatsapp`, `PrescriptionController::sendWhatsApp`, `InventoryController::purchaseOrderWhatsapp`, the `whatsapp-button.blade.php` component. These already correctly go through `WhatsAppLinkService`. No change.
- **AI drafting** — already channel-agnostic (`config/prm.php` `replies.channels` covers whatsapp/sms/email today). Nothing to build.

---

## 2. What gets removed

| Item | Why it can go |
|---|---|
| `WhatsAppInboxController` + its 4 routes (`index`/`show`/`reply`/`template`) + views | Already unlinked from nav today by the code's own admission. This is the chat window / inbox — explicitly out of scope now. |
| `WhatsAppOverviewController` (the "all conversations" list) + its route/view | This is the WhatsApp-specific timeline the directive says to remove. Patient communication history belongs on the patient's existing profile/comms view, not a WhatsApp-only page. |
| `WaThread` model + migration (`wa_threads` table) | Its only jobs — tracking the 24-hour reply window and grouping messages into a "conversation" — either aren't needed (no conversation UI anymore) or can be answered with one query against the log table in §3, instead of a dedicated table. |
| `WaMessage` model + migration (`wa_messages` table) | Superseded by extending the log table already used for this purpose (§3) instead of maintaining a second, chat-shaped table. |
| Marketing WhatsApp campaign panel (`_panel1-whatsapp.blade.php`), `IntegrationController::showWhatsappForm`/`saveWhatsapp`, the `PlatformConnection` WhatsApp credential store, and their routes | Confirmed non-functional in the audit — the backend already refuses to send through this path. Campaign messages stay a KEEP capability, but they'll go out through the same `WhatsAppLinkService`/`OutboundMessageService` everything else uses, not a separate broadcast system. Deleting this is pure debt removal, zero functionality lost. |
| Duplicate template catalog | `config/whatsapp.php` and `config/communication.php` each define a `templates` array today for the same message types. Merge into one (§4) — not a new system, just removing a second copy of the same data. |

---

## 3. What gets modified (reuse, don't rebuild)

**Consent duplication.** `OutboundMessageService::consentGate()` currently does its own lookup against `ConsentPurpose`/`PatientConsent`, separate from the check `WhatsAppLinkService` runs via `CommunicationGuard`. Fix: change `OutboundMessageService` to call `CommunicationGuard::hasWhatsAppConsent()` (the same method `WhatsAppLinkService` already uses) instead of re-implementing the lookup. One method, two callers. Nothing new created.

**The event log.** Instead of a new `communication_events` table (my earlier over-engineered proposal), extend the table that already exists and already does this job: `relationship_contact_log`. Add a handful of columns:
```
direction          (outbound | inbound)
status             (sent | delivered | read | failed | confirmed | cancelled)
wa_message_id      (nullable, indexed — Meta's id, for dedup + delivery-status matching)
template_key       (nullable — which template was used, if any)
reference_type / reference_id  (nullable, polymorphic — e.g. Appointment)
error              (nullable — failure reason)
```
This one table becomes the entire "PRE Timeline" data source for communication events (Reminder Sent, Reminder Delivered, Patient Confirmed, Patient Cancelled, Review Request Sent, Payment Reminder Sent, Patient Replied). It already gets written to by `CommunicationGuard::log()` on every send — we're widening its columns, not building a parallel system.

The 24-hour Meta reply window (needed to decide if a freeform message is still allowed, or only templates) becomes a one-line query against this same table: *does an inbound row exist for this phone/channel in the last 24 hours?* No separate thread table required.

**The webhook — the actual P0 fix.** `WhatsAppLeadController` (verify/receive, with correct fail-closed signature checking) is fully written but has no route. Add one:
```php
// routes/api.php (or a dedicated webhooks.php)
Route::get('/webhooks/prm/whatsapp',  [WhatsAppLeadController::class, 'verify']);
Route::post('/webhooks/prm/whatsapp', [WhatsAppLeadController::class, 'receive']);
```
Then trim `InboundMessageService` down to what's actually needed now that there's no chat archive to maintain:
- **Interactive button reply** (Confirm/Reschedule/Cancel) → call the existing appointment status-update method (whatever staff already use when clicking Confirm/Cancel in the UI — reuse it, don't duplicate it) → write one row to `relationship_contact_log` (`status = confirmed/cancelled`, `reference_type/id = Appointment`).
- **Delivery status update** (sent/delivered/read/failed, keyed by Meta's `wa_message_id`) → update the matching `relationship_contact_log` row's `status` column.
- **Free-text reply** (not a button click) → write one row (`direction=inbound`, no body stored) so the timeline shows "Patient replied via WhatsApp — check WhatsApp directly" and the 24-hour window updates. No message content is persisted — this satisfies "no chat archive" while still surfacing that a reply happened.
- `LeadIngestService` (new-lead creation from an unrecognized number) is untouched — that's PRM lead capture, not chat, and stays out of scope here.

**Interactive messages (new capability, small addition).** `WhatsAppCloudService::sendTemplate()` already POSTs arbitrary template payloads to Meta — it can send an interactive-button template today with no code change, just a template definition with `Confirm`/`Reschedule`/`Cancel` buttons added to the merged template config (§4). The only new code is the button-reply branch in `InboundMessageService` described above, which is a small `if` branch, not a new class.

**`Api\V1\WhatsappController::thread`** (mobile endpoint) — currently reads `WaThread`/`WaMessage`. Change its query to read recent rows from `relationship_contact_log` for that patient/channel instead. Same endpoint, same response shape as much as possible, different data source.

---

## 4. Config consolidation

Merge `config/whatsapp.php`'s `templates` array into `config/communication.php`'s `templates` array — each entry gains the Meta-specific fields (`meta_name`, `language`, `category`, `body_vars`) alongside the existing click-to-chat text, so one entry per message type covers both delivery modes:
```php
'appointment_reminder' => [
    'text' => '...',                 // click-to-chat fallback copy (existing)
    'meta_name' => 'appointment_reminder',   // Cloud API template name (moved from config/whatsapp.php)
    'language' => 'en',
    'category' => 'service',
    'body_vars' => ['patient_name', 'appointment_date', 'appointment_time'],
    'interactive' => ['confirm', 'reschedule', 'cancel'],   // new — only for reminder-type templates
],
```
`config/whatsapp.php` keeps only credentials/runtime settings (`enabled`, `dry_run`, `graph_version`, `phone_number_id`, `access_token`, `consent.*`). One template definition per message type, everywhere.

---

## 5. Folder / namespace — move, don't rewrite

Literal ask #4 was "move WhatsApp functionality under PRE > Communication." Given the philosophy of minimal change, this is a **file relocation**, not a rearchitecture:

```
app/Services/Communication/
  WhatsAppLinkService.php        (already here — stays)
  WhatsAppCloudService.php       (moved from app/Services/Whatsapp/)
  OutboundMessageService.php     (moved from app/Services/Whatsapp/)
  InboundMessageService.php      (moved, trimmed per §3)
```
`app/Services/Whatsapp/` is deleted once the move is done. Namespace changes from `App\Services\Whatsapp\*` to `App\Services\Communication\*` — a find-and-replace across the handful of call sites, not a redesign. This satisfies "WhatsApp lives inside PRE Communication" literally: it's one folder, containing the channel-specific classes, sitting next to (not competing with) the consent/guard classes that already live there.

No new interface, no new base class. If a second channel (SMS) gets built later and the duplication between it and WhatsApp becomes real and painful, that's the moment to extract a shared contract — not before.

---

## 6. Final KEEP / MODIFY / DELETE table

| Component | Action | Notes |
|---|---|---|
| `WhatsAppCloudService` | Keep, relocate | → `app/Services/Communication/` |
| `WhatsAppLinkService` | Keep, no change | Already in the right place |
| `OutboundMessageService` | Modify + relocate | Consent check delegates to `CommunicationGuard` |
| `InboundMessageService` | Modify + relocate | Strip thread/archive logic; interactive-button + delivery-status + reply-marker only |
| `WhatsAppLeadController` | Keep, **route it** | The actual P0 fix |
| `WhatsAppInboxController` + views + routes | **Delete** | Unused chat inbox |
| `WhatsAppOverviewController` + route + view | **Delete** | WhatsApp-specific timeline; fold into existing patient comms view |
| `WaThread` model + migration | **Delete** | Window/grouping logic replaced by one query on the log table |
| `WaMessage` model + migration | **Delete** | Replaced by extended `relationship_contact_log` |
| `relationship_contact_log` | Modify (add columns) | Becomes the single event log / PRE Timeline source |
| Console commands (`whatsapp:test`, `:template`, `:send-reminders`) | Keep, no change | |
| Marketing WhatsApp campaign panel + settings + `PlatformConnection` usage | **Delete** | Confirmed dead, zero functionality lost |
| `config/whatsapp.php` templates array | **Delete** (merged into `config/communication.php`) | One template catalog |
| `Api\V1\WhatsappController::thread` | Modify | Reads `relationship_contact_log` instead of `WaThread`/`WaMessage` |
| `WhatsAppConnector` / `IntegrationEngine` (Phase 7 abstraction) | Leave untouched for now | Pre-existing, feature-flagged off, not part of today's ask — revisit only if it starts causing real friction |
| Consent (`CommunicationGuard`, `ConsentPurpose`, `PatientConsent`) | Keep, no change | Already correct |
| AI draft generation | Keep, no change | Already channel-agnostic |

**No new classes are introduced.** The only new code is: a handful of migration columns, two route lines, one merged config array, and small edits inside two existing services.

---

## 7. Migration plan — minimal, ordered, independently shippable

1. **Add columns to `relationship_contact_log`** (`direction`, `status`, `wa_message_id`, `template_key`, `reference_type`, `reference_id`, `error`). Additive migration, zero risk, nothing reads them yet.
2. **Merge template config** (§4) — additive, `config/whatsapp.php` keeps working during the transition since nothing reads the old array yet on the new path.
3. **Fix `OutboundMessageService` consent check** to call `CommunicationGuard` — behavior-preserving (both paths already enforce the same rules; this removes the second implementation).
4. **Route the webhook** + trim `InboundMessageService` to write into `relationship_contact_log` instead of `WaThread`/`WaMessage`. This is the first point real inbound traffic can flow — test with Meta's sandbox before relying on it.
5. **Add interactive buttons** to the `appointment_reminder` template entry + the button-reply branch in `InboundMessageService`. Ship behind the existing `whatsapp:send-reminders` command with a `--interactive` flag or config toggle for a controlled rollout.
6. **Switch `Api\V1\WhatsappController::thread`** to read from `relationship_contact_log`.
7. **Delete** `WhatsAppInboxController`, `WhatsAppOverviewController`, their routes/views, `WaThread`, `WaMessage`, and their migrations (drop tables) — only after step 4 has been running long enough to confirm nothing still depends on the old tables.
8. **Delete** the marketing WhatsApp campaign panel, `IntegrationController::showWhatsappForm`/`saveWhatsapp`, and the old `templates` array in `config/whatsapp.php`.
9. **Relocate files** (§5) — `git mv` the three service classes into `app/Services/Communication/`, update namespaces and the handful of call sites. Do this last, once behavior is stable, so it's a pure rename with no logic riding along in the same diff.

Each step is a small, reviewable, revertible diff. Nothing in steps 1–6 removes existing capability, so there's no window where communication automation is degraded. Steps 7–9 are cleanup once the new path is proven.

---

## 8. Master Register note

Recommend updating the WhatsApp entry to: "WhatsApp is a channel inside PRE Communication (`app/Services/Communication/`), not a module. Inbound webhook routed as of this migration (was the 08-03 P0). Chat inbox and message archive removed by design — PRE Timeline (`relationship_contact_log`) is the only communication history surface." Same caveat as last time: I haven't edited `Dentfluence_Master_Register_Dashboard_V2.html` directly — this is the text to drop in wherever it's maintained.

No code was written or modified in this session — this is the plan you asked for.
