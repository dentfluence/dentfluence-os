# PRE Communication Architecture — Redesign Proposal
**CEO Directive: WhatsApp/SMS/Email are channels, not modules. PRE is the heart of patient communication.**
**Date: 2026-08-04 | Status: DESIGN PROPOSAL — no code changed yet**

---

## 0. A flag before the design — read this first

Before the architecture: one thing worth naming out loud, in the "challenge assumptions" spirit you asked for.

CEO Directive #004 (still your most recent standing production order) halted all non-V1 work and locked focus on Consultations → Treatment Plans → Visits → Appointments → Rx → Billing → Follow-up, with the test being "does this move V1 to production?" This new directive is a real architecture rewrite of the entire communication layer — bigger than the WhatsApp audit that triggered it. That's not a reason not to do it (the audit found the current WhatsApp code is genuinely fragmented — two template catalogs, three consent-check paths, an unrouted webhook, a dead campaign UI — so "fix it properly once" is defensible). But it is a reason to be explicit about sequencing rather than let this quietly become a second front alongside the V1 push.

My recommendation, reflected in the migration plan below: treat this document as the **design freeze** now, but gate actual implementation start behind an explicit decision — either (a) it's the next thing after V1 ships, or (b) you fold Phase 1 in now because it's small and touches the one channel flow (appointment reminders) that's already inside the V1 chain. I'd lean toward (b) only for Phase 1, and hold everything else until V1 is real-use-validated. Say the word either way and I'll adjust.

Everything else below answers your ten tasks directly.

---

## 1. Core philosophy, stated as rules

1. **PRE decides WHAT and WHY. Channels decide HOW.** No exceptions. If a piece of code contains both "this patient should be reminded about their appointment" and "here is the Meta Graph API payload shape," that code is wrong and must be split.
2. **Channels are dumb pipes.** A channel driver's entire contract is: take a rendered message, attempt delivery, report what happened. It has zero opinion about consent, timing, patient relationships, or business rules.
3. **The timeline is an event log, not a chat log.** PRE Timeline records that something meaningful happened ("Reminder sent," "Patient confirmed," "Review received") — never the raw back-and-forth of a conversation. Dentfluence is not building a WhatsApp client.
4. **Consent and audit are PRE-owned, once, for every channel.** Not re-implemented per channel (today there are effectively three consent-check code paths for WhatsApp alone — the click-to-chat guard, the Cloud API consent gate, and the mobile API's own check. That's the exact duplication this directive exists to remove).
5. **Interactivity produces domain actions, not messages.** A button tap on a reminder doesn't create a chat reply — it creates `AppointmentConfirmed`, `AppointmentCancelled`, or `RescheduleRequested`, using the same appointment-update logic a receptionist clicking a button in the UI would trigger.
6. **Meta (or Twilio, or SMTP) is an implementation detail of one driver.** PRE must be able to run — degraded, but running — if WhatsApp Cloud API is disabled, misconfigured, or Meta changes their API tomorrow. This is already half-true today (click-to-chat has no Meta dependency); the redesign makes it true everywhere.

---

## 2. New architecture

### 2.1 Component map

```
                        ┌─────────────────────────────┐
   Automation triggers  │   PRE AUTOMATION LAYER       │
   (schedule, domain    │  Reminder / Recall / Review  │
   events, staff click) │  Birthday / Payment / Campaign│
                        └──────────────┬───────────────┘
                                       │ builds
                                       ▼
                        ┌─────────────────────────────┐
                        │   CommunicationIntent        │  ← "what & why", channel-agnostic
                        └──────────────┬───────────────┘
                                       ▼
                        ┌─────────────────────────────┐
                        │  CommunicationOrchestrator   │  ← the ONE entry point, no bypasses
                        │  1. ConsentEngine check      │
                        │  2. Channel selection        │
                        │  3. Template render (AI or   │
                        │     static)                  │
                        │  4. Dispatch to driver        │
                        │  5. Record CommunicationEvent │
                        └──────────────┬───────────────┘
                                       ▼
                        ┌─────────────────────────────┐
                        │   ChannelDriverInterface      │  ← the ONLY seam PRE knows about
                        └──┬───────────┬───────────┬───┘
                           ▼           ▼           ▼
                  WhatsAppDriver   SmsDriver    EmailDriver     (future channels plug in here,
                  (Cloud API or    (future)     (future)         PRE code never changes)
                   click-to-chat
                   mode)
                           │
                           ▼
                  ┌─────────────────────┐
                  │ Provider webhook in   │  → InteractiveReplyHandler → domain Actions
                  │ (delivery status,     │     (AppointmentConfirmedAction, etc.)
                  │  button clicks, or    │  → CommunicationEvent (never raw chat storage)
                  │  free-text replies)   │
                  └─────────────────────┘
```

### 2.2 The pieces, in detail

**`CommunicationIntent`** (value object, not a model) — the universal request shape every automation or staff action produces:
```
patient_id, relationship_id, purpose (service | marketing),
event_type (reminder | recall | review_request | birthday |
            payment_reminder | campaign | rx_delivery | ...),
template_key, variables[], reference (polymorphic: Appointment,
TreatmentPlan, Invoice, Prescription, Recall...),
channel_preference (optional — patient's preferred channel, or
                     null to let the orchestrator decide),
interactive (bool — does this need Confirm/Reschedule/Cancel buttons),
scheduled_for (optional — for future-dated sends)
```

**`CommunicationOrchestrator::send(CommunicationIntent)`** — single choke point. Every send in the entire product — reminders, recalls, birthday wishes, a receptionist manually resending a prescription — goes through this one method. No controller, job, or service is allowed to call a channel driver directly. This is the actual fix for the fragmentation the audit found (today `WhatsAppLinkService`, `OutboundMessageService`, and the mobile API each independently re-implement "should this send happen").

**`ConsentEngine`** — formalizes the existing `CommunicationGuard` + `ConsentPurpose`/`PatientConsent` model as the single, channel-agnostic gate. Purpose-based (service vs. marketing), same rules regardless of channel. This already exists in spirit; the redesign just makes it structurally impossible to route around.

**`MessageTemplateResolver`** — one template registry (`communication_templates`), keyed by `(event_type)`, each entry holding per-channel variants: a Meta template name + language for WhatsApp Cloud API, a click-to-chat text template for WhatsApp manual mode, and slots reserved for future SMS/email variants. This collapses the two parallel catalogs that exist today (`config/whatsapp.php` templates vs `config/communication.php` templates) into one place a clinic owner — or future multi-tenant customer — can actually manage.

**`ChannelDriverInterface`** — the only contract PRE code is allowed to know about:
```
send(RenderedMessage $message): ChannelDeliveryResult
supportsInteractive(): bool
parseInboundWebhook(Request $request): ?InboundCommunicationEvent
```
`WhatsAppChannelDriver` implements this by wrapping the *existing* `WhatsAppCloudService` (API mode) and `WhatsAppLinkService` (manual/click-to-chat mode) as two delivery strategies of one driver — chosen automatically based on `WHATSAPP_ENABLED`/consent/patient reachability, not by the caller. This is largely a repackaging of code that already works well (the audit confirmed click-to-chat is the one genuinely live path) rather than a rewrite.

**`InteractiveReplyHandler`** — replaces `WhatsAppLeadController`'s message-threading logic. A driver parses its provider's inbound payload into a generic `InboundCommunicationEvent` (type: `button_click | free_text | delivery_status`). If it's a recognized button click on a known interactive template, the handler dispatches the matching domain action (`AppointmentConfirmedAction`, `AppointmentCancelledAction`, `RescheduleRequestedAction`) using the *existing* Appointment update services — not new logic. If it's free text, no chat thread is created; a single bounded-length `patient_replied` timeline event is logged (e.g. "Patient replied via WhatsApp — check manually," first ~100 characters as a hint) so staff know to look, without Dentfluence becoming a message archive.

**`communication_events`** (new table, the actual PRE Timeline) — replaces the chat-oriented parts of `wa_messages` and generalizes `relationship_contact_log`:
```
id, patient_id, relationship_id, channel, purpose, event_type
  (sent | delivered | read | failed | confirmed | cancelled |
   reschedule_requested | review_received | patient_replied | ...),
reference_type, reference_id (polymorphic — Appointment, Invoice, ...),
template_key, occurred_at, metadata (json, small — status code,
  button label clicked, error reason), created_by (system|user id)
```
This table is what the patient profile's communication tab reads. It answers "what happened," never "what was said."

**AI Drafts** — already channel-agnostic in spirit (`config/prm.php` `replies.channels` includes whatsapp/sms/email today). Formalized: AI drafting takes a `CommunicationIntent` + context and returns text; that text is handed to whichever driver ends up sending it. No AI code should ever import a WhatsApp class.

### 2.3 What this explicitly is NOT

No inbox. No chat thread UI. No message synchronization job. No media synchronization. No read-receipt UI. No conversation history view. If a patient and a staff member need a real back-and-forth conversation, that happens in WhatsApp itself (or a phone call) — Dentfluence records that a reply happened and nudges staff to go look, it does not try to become the place that conversation lives.

---

## 3. What moves where (mapped against the existing audit)

| Today | Becomes | Why |
|---|---|---|
| `WhatsAppLinkService` (consent + template + wa.me build) | Split: consent → `ConsentEngine`; template → `MessageTemplateResolver`; wa.me building → `WhatsAppChannelDriver` (manual mode) | Currently one class does PRE's job and the channel's job at once |
| `OutboundMessageService` (Cloud API orchestration) | Folded into `CommunicationOrchestrator` + `WhatsAppChannelDriver` (API mode) | Same orchestration concern the click-to-chat path duplicates independently today |
| `WhatsAppCloudService` | Becomes the low-level HTTP client used *inside* `WhatsAppChannelDriver` | Correctly channel-specific already — kept almost as-is |
| `InboundMessageService` + `WhatsAppLeadController` | Replaced by `InteractiveReplyHandler` + a routed webhook | Fixes the "unrouted webhook" P0 *and* removes the chat-threading behavior we no longer want |
| `wa_threads` / `wa_messages` | Replaced by `communication_events`; a much smaller internal `channel_conversation_state` table may remain *only* to track Meta's 24-hour reply window — never exposed as UI | We need the 24h-window fact for send eligibility; we don't need a message archive |
| `WhatsAppInboxController` + view | Removed | It's already unlinked from nav today — this makes that permanent and intentional |
| `WhatsAppOverviewController` (PRE conversations list) | Replaced by a generic Communication Timeline view on the patient profile, filtered/grouped as needed | Same spot in the nav, but shows events not messages |
| `relationship_contact_log` | Superseded by `communication_events` (event log absorbs its job) | One event log instead of a log-plus-a-log |
| Marketing `_panel1-whatsapp.blade.php` + `IntegrationController::saveWhatsapp`/`PlatformConnection` credentials | Removed | Confirmed non-functional today; campaigns become a `CommunicationIntent`-emitting PRE Automation like everything else, using the same driver — no separate credential store needed |
| `config/whatsapp.php` templates + `config/communication.php` templates (two catalogs) | Collapsed into `communication_templates` | Direct duplication removal |
| `whatsapp:send-reminders` | Renamed `pre:send-reminders`, now builds `CommunicationIntent`s and calls the orchestrator | The command shouldn't have "whatsapp" in its name if the channel is chosen at send time |
| `TodayController::sendBirthdayWhatsapp`, `PrescriptionController::sendWhatsApp`, `InventoryController::purchaseOrderWhatsapp` | Callers build a `CommunicationIntent` and call the orchestrator instead of `WhatsAppLinkService` directly | Even one-off manual sends should go through the same consent/template/event pipeline |
| `WhatsAppConnector` / `IntegrationEngine` (Phase 7 abstraction) | Effectively *becomes* `ChannelDriverInterface` — same idea, promoted from an optional shadow-logging feature flag to the actual production seam | You already had the right instinct here; this makes it load-bearing instead of optional |

**Never touched / stays exactly as-is:** `Patient`, `ConsentPurpose`/`PatientConsent` models, `AuditLog`, `Appointment` and its existing status-update services, `VerifiesMetaSignature` trait (still needed by whichever driver talks to Meta).

---

## 4. Interactive messages — concrete design

**Outbound:** `CommunicationIntent` for an appointment reminder sets `interactive = true`. `MessageTemplateResolver` returns a Meta interactive-button template payload (`Confirm` / `Reschedule` / `Cancel`) for the WhatsApp Cloud API driver, and for the click-to-chat driver falls back gracefully to a plain-text version with instructions ("Reply YES to confirm") since `wa.me` links can't render buttons.

**Inbound:** Meta posts the button-click webhook → `WhatsAppChannelDriver::parseInboundWebhook()` recognizes the `button_reply` payload type, extracts the button id (`confirm_appt_123`) → returns `InboundCommunicationEvent{type: button_click, action: 'confirm', reference: Appointment#123}` → `InteractiveReplyHandler` calls the existing `AppointmentService::confirm()` (or equivalent — reuse, don't rebuild) → writes a `confirmed` `communication_events` row → notifies staff via the existing notification system → done. No message body is stored beyond the structured button id.

This is genuinely new capability (today's `WhatsAppInboxController`/`InboundMessageService` never implemented interactive buttons), but it's additive to a foundation you already have 80% of: the webhook signature verification, the appointment model, and the notification system all already exist.

---

## 5. Migration plan

Design for MVP-first, per your own instructions to me — small, reversible, dual-run-validated slices, not a big-bang rewrite.

**Phase 0 — Design freeze (this document).** No code. Decision needed from you: does Phase 1 start now or after V1 ships (see §0).

**Phase 1 — Foundation, non-breaking, additive only.**
- Create `communication_events` table + `CommunicationEvent` model.
- Create `ChannelDriverInterface`, `CommunicationIntent`, `CommunicationOrchestrator`.
- Create `WhatsAppChannelDriver` as a thin adapter wrapping the *existing* `WhatsAppLinkService`/`WhatsAppCloudService` unchanged underneath — zero behavior change, just a new front door.
- Migrate exactly **one** flow through it end-to-end as proof of concept: appointment reminders (already scheduled, already isolated, already inside the V1 chain — the one candidate that overlaps with Directive #004's priority list). Dual-write: old path stays live, new path runs in parallel and logs to `communication_events`, compare for a validation window before cutover.
- No deletions yet.

**Phase 2 — Cutover the rest of the automations, one at a time.**
- Recall Engine, Birthday Wishes, Payment Reminders, Review Requests each get their trigger point changed to build a `CommunicationIntent` and call the orchestrator, in separate, individually-testable slices.
- Manual send points (`TodayController`, `PrescriptionController`, `InventoryController`) switch to the orchestrator.
- Old direct-call code paths deleted only after each slice is confirmed working in real use — same "one slice → stop → report → approval" discipline you've used on the Journey Timeline work.

**Phase 3 — Interactive + inbound.**
- Route the webhook (fixes the existing unrouted-webhook P0 as a side effect).
- Build `InteractiveReplyHandler` + the three domain actions.
- Ship interactive reminder templates.

**Phase 4 — Cleanup.**
- Remove `WhatsAppInboxController` + view, `WhatsAppOverviewController`'s message-list version (replaced by the new Timeline view), dead marketing WhatsApp campaign UI + `PlatformConnection` credential store, the duplicate template catalog, `WhatsAppLeadController`/`InboundMessageService` (superseded by the handler).
- Decide fate of `wa_threads`/`wa_messages`: either drop after confirming `communication_events` + the small `channel_conversation_state` table cover everything needed, or keep `wa_messages` write-only as a short-retention debug log not surfaced in any UI.

**Phase 5 — Prove the abstraction.**
- Add `SmsChannelDriver` and/or `EmailChannelDriver` behind the same interface with zero PRE-layer changes. This phase is the real test of whether the architecture worked — if adding SMS requires touching `CommunicationOrchestrator`, the abstraction has a leak.

Each phase is independently shippable and reversible. Nothing in Phases 1–2 requires deleting anything, so there's no point where communication capability regresses mid-migration.

---

## 6. Proposed module structure

```
app/PRE/
  Communication/
    CommunicationOrchestrator.php
    CommunicationIntent.php
    ConsentEngine.php
    MessageTemplateResolver.php
    InteractiveReplyHandler.php
    Contracts/
      ChannelDriverInterface.php
    Channels/
      WhatsAppChannelDriver.php
      SmsChannelDriver.php        (Phase 5)
      EmailChannelDriver.php      (Phase 5)
    Actions/
      AppointmentConfirmedAction.php
      AppointmentCancelledAction.php
      RescheduleRequestedAction.php
    Events/
      CommunicationEvent.php (model)
  Automations/
    ReminderAutomation.php
    RecallAutomation.php
    ReviewRequestAutomation.php
    BirthdayAutomation.php
    PaymentReminderAutomation.php
    CampaignAutomation.php
  Timeline/
    CommunicationTimelineService.php   (feeds the patient-profile timeline view)
```
`app/Services/Whatsapp/*` and `app/Services/Communication/WhatsAppLinkService.php` shrink to nothing (their logic redistributes into `Channels/WhatsAppChannelDriver.php` and the orchestrator) and get removed at the end of Phase 2.

---

## 7. Master Register update

I did not edit `Dentfluence_Master_Register_Dashboard_V2.html` directly — it's a generated dashboard I haven't inspected the build process for, and editing a file blind risks breaking its structure. Recommended entries to add, worded for whoever maintains it:

- **New governing entry, top of list:** "PRE Communication Architecture Redesign — CEO Directive 2026-08-04. Supersedes WhatsApp-module framing; communication logic (WhatsApp/SMS/Email/Reminders/Recalls/Reviews/Birthdays/Payment Reminders/Campaigns/Consent/AI Drafts) is being consolidated under PRE. See `docs/pre-communication-architecture-2026-08-04.md`. Sequencing relative to Directive #004 (V1 production focus) pending CEO decision."
- **Update WhatsApp module score/status** in the Product Audit Dashboard entry: change from "module" framing to "channel driver under PRE Communication" and note the unrouted-webhook P0 is being resolved as part of Phase 3 of this migration, not as a standalone fix.
- **Retire as a forward-looking target** (not delete — keep as history) the Marketing WhatsApp campaign entry, since that UI is being removed in Phase 4.

I've saved the full architecture doc to `docs/pre-communication-architecture-2026-08-04.md` in the repo so it's linkable from the register.

---

## 8. Open decisions for you

1. Phase 1 timing — now (small, overlaps V1's appointment-reminder flow) or after V1 ships, per §0.
2. Fate of `wa_threads`/`wa_messages` in Phase 4 — hard delete vs. keep as write-only debug log.
3. Whether Recall Engine, Review Requests, Birthday Wishes, Payment Reminders, and Campaigns get their own CEO-Order slice numbers (matching how you've been sequencing Consultations/Treatment Plans) once Phase 1 is validated.

No code was written or modified as part of this task — this is the design and migration plan you asked for.
