# WhatsApp Cleanup V1 — Implementation Note
**Official record for the completed work. Date: 2026-08-05.**
**Status: ACCEPTED — no further development on this module until the next phase.**

---

## Objectives achieved

1. **Inbound WhatsApp webhook routed** — `WhatsAppLeadController::verify`/`receive` (fully written, previously unreachable) is now live at `/api/v1/webhooks/prm/whatsapp`. This closes the P0 first identified in the 08-03 Repo Cleanup Audit and confirmed in the 08-04 WhatsApp module audit.
2. **Interactive appointment replies implemented** — a patient tapping Confirm, Reschedule, or Cancel on a WhatsApp reminder now produces a real effect: Cancel executes the existing appointment cancellation; Confirm and Reschedule are recorded as PRE communication events visible on the patient's Timeline.
3. **Duplicate WhatsApp consent logic removed** — there is now exactly one place WhatsApp consent is decided (`CommunicationGuard::hasWhatsAppConsent()`), not two.
4. **Interactive reminder templates enabled** — the `appointment_reminder` template can now carry Confirm/Reschedule/Cancel quick-reply buttons, wired end-to-end to the reply handler above.

## Files modified (complete list, 4 slices)

| File | Slice | Change |
|---|---|---|
| `routes/api.php` | 1 | Registered the two webhook routes |
| `app/Services/Whatsapp/InboundMessageService.php` | 2 | Added interactive-button-reply handling |
| `app/Services/Whatsapp/OutboundMessageService.php` | 3, 4 | Consent check now delegates to `CommunicationGuard`; added `buildButtonComponents()` |
| `config/whatsapp.php` | 4 | Added `buttons` array to the `appointment_reminder` template entry |
| `app/Console/Commands/WhatsAppSendReminders.php` | 4 | Passes `appointment_id` through so buttons activate |

No migrations. No new files. No files moved or renamed. No namespace changes.

## Architecture decisions taken

- **Reuse over invention, at every decision point.** Cancel reuses `AppointmentService::cancel()` exactly as it already existed — no new appointment status was created because none was needed for a real 1:1 method match. Confirm/Reschedule reuse `ActivityEngine`, the pre-existing "universal event log" service that already backs the patient Timeline and already underlies `AppointmentActivityLogger` itself — chosen after confirming no other suitable PRE mechanism was missing, per your explicit instruction to investigate before defaulting to a new table.
- **Event names are channel-agnostic by design.** `communication.confirmed` and `communication.reschedule_requested` — not `whatsapp.*` — with the channel (`whatsapp`) stored in event metadata. This was a deliberate correction mid-implementation: the first draft used WhatsApp-specific event names and was revised before being committed, so the exact same event names can be reused by SMS/email/patient-app later with zero new event types.
- **Button payloads carry the appointment id directly** (`confirm_appt_{id}` / `reschedule_appt_{id}` / `cancel_appt_{id}`), parsed by a single regex in `InboundMessageService` — the minimum mechanism needed to route a button tap back to the right appointment, no abstraction layer built around it.
- **The duplicate consent removal preserves behavior by construction**, not by assumption — both implementations were read side-by-side before the swap, and the purpose-key resolution was confirmed identical for both `service` and `marketing` categories.

## What was intentionally NOT changed

- `WaThread` / `WaMessage` tables — kept exactly as they are; no schema changes.
- `relationship_contact_log` — untouched; not used as an event store for this work (`ActivityEngine`/`Activity` was used instead, per the investigation in Slice 2).
- `WhatsAppInboxController` and its routes/views — left dormant, not deleted.
- The Marketing WhatsApp campaign UI and its `PlatformConnection` credential store — left exactly as-is.
- WhatsApp Integration Settings — no changes, no relocation.
- Folder structure and namespaces — nothing moved, nothing renamed.
- The Appointment Engine — zero lines changed in `Appointment.php`, `AppointmentService.php`, or `AppointmentActivityLogger.php`. No new appointment status was introduced (there still is no "confirmed" state, deliberately).
- PRE Architecture / Timeline module code — zero lines changed in `ActivityEngine.php` or `Activity.php`; both were called, not modified.
- No automated test coverage was added.

## Production benefits

- The inbound WhatsApp channel is no longer structurally dead — it can now receive real Meta traffic once credentials are configured (previously impossible regardless of configuration, since the route didn't exist).
- Appointment reminders can become a two-way, self-service interaction (confirm/cancel without staff involvement) rather than a one-way broadcast — the highest-leverage, lowest-effort change available for reducing no-shows and freeing front-desk time.
- One consent code path instead of two removes a category of future bug where the two implementations silently drift and produce inconsistent consent decisions for the same patient.
- The reminder template and button-reply mechanism are built to be reused by future channels (SMS/email) without new event types, even though those channels are out of scope for this phase.

## Known limitations

- **Not yet live in production.** Three external dependencies remain outside this codebase's control: Meta must re-approve the `appointment_reminder` template with the new buttons; an end-to-end test against Meta's sandbox hasn't been run; and production `.env` values (`WHATSAPP_ENABLED`, `WHATSAPP_DRY_RUN`, `PRM_WA_APP_SECRET`, `PRM_WA_VERIFY_TOKEN`) are unset.
- Confirm and Reschedule are **passive, informational events only** — nothing currently notifies staff proactively when they land (no rule is configured in `config/relationship_rules.php` yet). Wiring an active notification is a config-only addition later, not a code change, but it hasn't been done.
- Three independent Meta Graph API version configs still exist in the codebase (`config/services.php` v23.0, `config/whatsapp.php` v21.0, `config/prm.php` v19.0) and still risk drifting — this predates Cleanup V1 and was explicitly left untouched as out of scope.
- No automated tests exist for any of the four slices' new code paths.

## Remaining future work (not started, not scoped into V1)

- Meta template re-approval and Meta sandbox validation.
- Production credential configuration.
- Everything listed in the Parked / Future Improvements section below — explicitly deferred to a separate PRE Communication UX design phase.
