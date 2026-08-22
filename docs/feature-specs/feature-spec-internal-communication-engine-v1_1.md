# Feature Spec — Internal Communication Engine

**Status:** 📦 DEFERRED TO V1.1 — design only, zero code written
**Classified:** 2026-08-19 under [CEO Implementation Directive — Implementation Mode (2026-08-07)]
**Phase at time of request:** Phase 1 — Fix Before Ship
**Decision:** CEO accepted the 📦 V1.1 classification. This document exists so V1.1 starts with zero discovery.

---

## 0. Implementation Check v2 (run 2026-08-19)

| # | Question | Answer |
|---|---|---|
| 1 | Current implementation phase? | Phase 1 — Fix Before Ship |
| 2 | Does this request belong to the current phase? | **NO** — new module, new tables, new settings UI, new scheduler surface |
| 3 | Does Tulip fail to operate safely without it? | **NO** — Tulip operates today without internal notifications |
| 4 | Production blocker? | **NO** |
| 5 | Violates the locked sequence? | **YES** — "new modules / new workflows / architecture redesign" are closed until V1 complete |
| 6 | **Decision** | **📦 MOVE TO V1.1** |

---

## 1. Existing architecture discovered (read-only audit, 2026-08-19)

Audit performed against `E:\Dentfluence\Dentfluence_OS\Dentfluence Web` (the real repo; `C:\laragon\www\dentfluence` is a junction).

### 1.1 Scheduling / dispatch

`routes/console.php` is the single scheduler surface. Times are **hard-coded string literals**, not configurable:

| Time | Command | Purpose |
|---|---|---|
| 07:00 | `recall:run` | Recall Engine (Phase 2) |
| 07:05 | `comm:morning-briefing` | Per-staff queue digest **email** |
| 07:10 | `membership:scan-expiring` | Membership renewal events |
| 07:15 | `payments:scan-overdue` | Payment overdue events |
| 07:20 | `birthdays:scan` | Birthday events |
| 08:00 | `tulip:huddle` | Huddle briefing → **log file only** |
| 09:00 | `lab:create-overdue-tasks` | Lab overdue tasks |
| 10:00 | `whatsapp:send-reminders` | Patient appointment reminders (idempotent, consent-gated) |
| 10:30 | `hr:mark-absent` | HR auto-absent (weekdays) |
| 11:00 | `reviews:request` | Review requests (idempotent) |
| 14:00 | `comm:sla-alert` | SLA breach **email** to manager |
| 18:00 | `comm:evening-summary` | Per-staff evening **email** |
| :00/:30 | `comm:auto-escalate` | ₹30k+ lead escalation + manager email |

**Implication:** a configurable "Send Time" per notification (spec §9) requires either a per-minute dispatcher command that reads config, or `Schedule::call()` with a runtime time check. Do not add seven more hard-coded `dailyAt()` entries.

### 1.2 Email

- **`MAIL_MAILER=log`** in `.env`. **No email currently leaves Dentfluence in any environment.** This is the single largest blocker: every email toggle in the proposed matrix would be a lie until this changes.
- Existing Mailables: `app/Mail/MorningBriefing.php`, `EveningSummary.php`, `SlaAlert.php`, `PasswordResetPinMail.php`.
- **None implement `ShouldQueue`.** Commands call `Mail::to($user->email)->send(...)` synchronously inside a `foreach` (`SendMorningBriefing.php:59`, `SendEveningSummary.php:59`, `SendSlaAlert.php:64`, `AutoEscalateHighValueLeads.php:137`). A slow SMTP host will stall the scheduled command.
- **There is no email delivery log table.** WhatsApp has `wa_messages`; email has nothing. Spec §16 (delivery logging) has no email-side substrate today.

### 1.3 WhatsApp

- `app/Services/Whatsapp/OutboundMessageService.php` is the **single sanctioned send path**. It resolves a `WaThread`, runs the DPDP consent gate via `CommunicationGuard`, records a `WaMessage`, sends via `WhatsAppCloudService` (or `IntegrationEngine` when `integration.whatsapp` is on), audits via `AuditLog::event()`.
- `WhatsAppCloudService` — Graph API client, dry-run aware.
- `InboundMessageService` — webhook handler, interactive button replies (Confirm/Reschedule/Cancel) wired 08-05.
- Consent model is **patient-centric**: `CommunicationGuard` operates on `Relationship` / `Patient` / `PatientConsent`. It has no concept of an internal staff recipient.
- `users.phone` is `string(20) nullable` (migration `2026_05_29_100001`). No country-code normalisation, no WhatsApp opt-in flag.

### 1.4 Daily Huddle — TWO implementations

| Brain | Path | Shape | Fit for notification |
|---|---|---|---|
| **A — briefing** | `app/Services/Huddle/HuddleService.php` | `build()` → `['date', 'sections' => [{title, headline, lines[]}]]`; `render()` → plain text. 10 guarded sections: schedule, safety flags, money to collect, opportunities, yesterday's flow, failures, tasks, lab, stock, new patients. Each section wrapped in `safe()` so one module failure never breaks the briefing. | **STRONG** — already a channel-neutral payload; text render already exists |
| **B — board** | `app/Modules/Huddle/**` (Controllers, DTOs, Models, Repos, Services) | `HuddleAggregationService::buildBoardForUser($branchId, $role)` → kanban board of `HuddleCard`s, persisted to `huddle_boards`/`huddle_cards`. Role-scoped via `RoleBasedHuddleService`. | Weak — stateful board, not a digest |

Plus `app/Http/Controllers/Communication/HuddleController.php` + `resources/views/communication/huddle/` (alerts, widgets, overdue-summary) — a third, communication-flavoured surface.

**V1.1 decision required:** Brain A is the correct source for the Daily Huddle notification payload. Confirm before building.

### 1.5 Overlap risk — the digest system already exists

`comm:morning-briefing` (07:05) and `comm:evening-summary` (18:00) already email every staff member a personalised digest. A "Daily Huddle → Owner/Manager" email is functionally adjacent. Spec §1 forbids parallel systems — **V1.1 must either absorb these three commands into the engine or explicitly justify keeping them separate.** Absorbing is the correct call.

### 1.6 Idempotency

Precedent exists in three places; no shared table:

- `app/Console/Commands/WhatsAppSendReminders.php` — dedup key per appointment
- `app/Console/Commands/ReviewsRequest.php` — one request per appointment
- `database/migrations/2026_07_02_400002_create_processed_domain_events_table.php` — `processed_domain_events`, used by `app/Domain/Events/DomainEventBus.php`
- `app/Services/Relationship/CommunicationEngine.php` — dedup logic

`processed_domain_events` is the closest existing substrate but is event-keyed, not `(recipient, channel, business_date)`-keyed. A dedicated table is justified (see §4).

### 1.7 Settings

- `app_settings` (migration `2026_05_29_100001`): `group` / `key` (**globally unique**) / `value`. **Not tenant-scoped** — a unique `key` cannot hold per-branch values. Unsuitable as-is for a multi-branch notification matrix.
- Module-specific settings tables already exist: `huddle_settings`, `inventory_settings`, `finance_settings`, `mkt_settings`, `branch_settings`.
- Settings controllers are scattered: `Settings/SettingsController`, `SettingsController` (root), `Marketing/SettingsController`, `Relationship/SettingsController`, `Communication/RecallSettingsController`, `Prescription/RxSettingsController`, `Modules/Huddle/Controllers/HuddleSettingsController`.
- `resources/views/settings/` contains only: activity-log, clinic-hfr, clinical-library, index, knowledge-bank, roles, tags. **There is no Communications settings section yet.**
- Permission gate exists: `role_module_permissions.can_settings` (migration `2026_08_04_060000`) + `app/Http/Controllers/Concerns/HasModuleSettingsAccess.php`.

### 1.8 Multi-tenancy — SPEC §18 CANNOT BE HONOURED

- **`users` has no `clinic_id`.** Columns added over time: `role`, `branch_id` (unsignedTinyInteger, default 1), `role_id`, `phone`, `designation`, `avatar`, `color`, 2FA columns, `is_superadmin`.
- `clinic_id` exists only on inventory/finance tables.
- This matches the standing audit finding: *"tenant #2 unsafe until clinic_id"* (Production Readiness Review 07-14) and *"no tenant isolation"* (System Settings CTO Audit 08-04).

**V1.1 must scope on `branch_id`, and the spec's clinic-isolation requirement is deferred to the parked `clinic_id` work.** Do not invent a `clinic_id` on `users` inside this module.

### 1.9 Queue

- `QUEUE_CONNECTION=database`. `config/queue.php` default `database`.
- `ShouldQueue` is used by 8 jobs (Blog, Marketing, EnrichLead, GenerateWatermark, search index, insight signals, relationship score, automation-failed).
- **Unverified:** whether a queue worker actually runs in production. Must be confirmed before the engine depends on queued delivery.

---

## 2. Blockers, ranked

| # | Blocker | Severity | Owner |
|---|---|---|---|
| B1 | `MAIL_MAILER=log` — no email leaves the system | **P0** | Infra / CEO — pick SMTP provider |
| B2 | WhatsApp to staff requires Meta-approved templates (24h session window); 7 notification types ≈ 7 approvals | **P0** | External — Meta approval lead time |
| B3 | No `clinic_id` on `users`; only `branch_id` | **P1** | Parked platform work — scope to branch |
| B4 | No email delivery log table | **P1** | This module |
| B5 | Mailables not queued; sync send inside scheduler loops | **P1** | This module |
| B6 | `users.phone` nullable, unnormalised, no WhatsApp opt-in flag | **P1** | This module |
| B7 | Existing morning/evening/SLA digests overlap the proposed engine | **P1** | Absorb, don't parallel |
| B8 | Queue worker presence in production unverified | **P1** | Infra check |
| B9 | Send times hard-coded in `routes/console.php` | **P2** | This module |

**B1 and B2 are prerequisites, not tasks.** Nothing in this module is demonstrably working until they clear.

---

## 3. Target architecture (V1.1)

### 3.1 Model

```
Trigger (event or schedule)
        ↓
NotificationDefinition  (static catalogue, code-owned)
        ↓
NotificationSetting     (per-branch, DB-owned: enabled, channels, send_time)
        ↓
RecipientResolver       (dynamic | configured)
        ↓
CommunicationPayload    (built ONCE, channel-neutral)
        ↓
 ┌──────┴───────┐
EmailRenderer  WhatsAppRenderer
 └──────┬───────┘
   InternalNotificationDispatcher (idempotency check → queue job → log)
```

**Invariant:** the payload is built once per (notification, recipient, business_date). Renderers receive it; renderers never query the database. This is spec §10 and it is the whole point of the design.

### 3.2 Proposed namespace

`app/Services/InternalComms/` — deliberately **not** `app/PRE/Communication/`. PRE owns *patient* relationship communication (CEO directive 08-04). This engine is *internal/staff operational* notification. Keeping them separate avoids dragging patient consent semantics onto staff messages.

Reuse, do not rebuild: `OutboundMessageService` (WhatsApp transport), `HuddleService` (Huddle payload), `InventoryService` (stock thresholds), `ReportMetricsService::collectionsSeries()` (collections).

### 3.3 Files (estimate)

| Kind | Count | Notes |
|---|---|---|
| Migrations | 3 | settings, recipients, delivery log |
| Models | 3 | |
| Services | ~6 | Registry, PayloadBuilder ×N, RecipientResolver, Dispatcher, IdempotencyStore |
| Jobs | 2 | `SendInternalNotificationEmail`, `SendInternalNotificationWhatsApp` |
| Console command | 1 | `internal-comms:dispatch` (runs every 5 min, reads configured times) |
| Controller | 1 | `Settings/InternalNotificationsController` |
| Views | 2 | matrix index + configure modal |
| Mailable + templates | 1 + N | |
| Tests | ~8 files | per spec §23 |

**~28 files, ~2,000–2,600 lines. This is a 4–6 slice build, not a single response.**

---

## 4. Database (proposed)

All tables branch-scoped. `clinic_id` deliberately omitted — see §1.8.

### `internal_notification_settings`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| branch_id | unsignedTinyInteger | index, default 1 |
| notification_key | string(64) | e.g. `doctor_daily_schedule` |
| enabled | boolean | default false |
| email_enabled | boolean | default false |
| whatsapp_enabled | boolean | default false |
| send_time | time nullable | null for event-driven notifications |
| timestamps | | |

Unique: `(branch_id, notification_key)`

### `internal_notification_recipients`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| branch_id | unsignedTinyInteger | index |
| notification_key | string(64) | |
| user_id | FK → users, cascadeOnDelete | **user reference only — never a raw email/phone (spec §5)** |
| timestamps | | |

Unique: `(branch_id, notification_key, user_id)`
Only populated for `recipient_mode = configured`. Dynamic notifications resolve at send time.

### `internal_notification_deliveries`
| Column | Type | Notes |
|---|---|---|
| id | bigint PK | |
| branch_id | unsignedTinyInteger | index |
| notification_key | string(64) | |
| user_id | FK → users | |
| channel | string(16) | `email` \| `whatsapp` |
| business_date | date | |
| idempotency_key | string(191) | **unique** |
| status | string(16) | queued / sent / failed / skipped |
| queued_at, sent_at | timestamp nullable | |
| failure_reason | text nullable | |
| wa_message_id | FK → wa_messages nullable | preserves delivered/read/replied states (spec §16) |
| timestamps | | |

**Idempotency key format:** `{branch_id}:{notification_key}:{user_id}:{channel}:{business_date}`
Insert-first, send-second. A `unique` violation = already handled → skip. This makes repeated scheduler execution safe by construction (spec §15).

---

## 5. Notification catalogue

Code-owned registry (`InternalNotificationRegistry`), not a DB table — definitions ship with the code; only *configuration* lives in DB.

| key | Label (dentist-facing) | Group | Mode | Recipients | Source (reuse) |
|---|---|---|---|---|---|
| `doctor_daily_schedule` | Doctor Daily Schedule | Scheduling | scheduled | **dynamic** — doctors with appointments today | `appointments` |
| `daily_huddle` | Daily Huddle | Daily Operations | scheduled | **configured** | `Services\Huddle\HuddleService::build()` |
| `online_appointment_booked` | New Online Appointment | Scheduling | event | **dynamic** — assigned doctor | online booking channel |
| `appointment_reassigned` | Appointment Reassigned | Scheduling | event | **dynamic** — both doctors | appointment update |
| `appointment_cancelled` | Appointment Cancelled | Scheduling | event | **dynamic** — assigned doctor | appointment cancel |
| `inventory_below_minimum` | Stock Below Minimum | Daily Operations | event | **configured** | `Services\Inventory\InventoryService` |
| `daily_collection_summary` | Daily Collection Summary | Daily Operations | scheduled | **configured** | `Analytics\ReportMetricsService::collectionsSeries()` |

Default send time for scheduled notifications: **06:30**, clinic timezone (`Asia/Kolkata`), configurable per notification. Never UTC.

### Doctor Daily Schedule — content rule
Include: time, patient name/ID, treatment or appointment type, status where useful.
**Exclude: diagnosis, chief complaint, clinical notes, prescription, phone number.** This is an operational schedule, not a clinical record. Empty state: *"No appointments scheduled for today."*

---

## 6. Recipient resolution

```php
interface ResolvesRecipients {
    /** @return Collection<User> — MUST be branch-scoped */
    public function resolve(NotificationContext $ctx): Collection;
}
```

Two implementations: `DynamicRecipientResolver` (derives users from the event/date) and `ConfiguredRecipientResolver` (reads `internal_notification_recipients`).

**Hard rules:**
- Every resolver query filters on `branch_id`. No exceptions, including inside queued jobs where there is no authenticated user.
- A doctor receives only their own schedule — resolve per-doctor, build a payload per-doctor, never one payload fanned out.
- A recipient with no email is skipped for the email channel (logged `skipped`), not failed. Same for phone/WhatsApp.

---

## 7. Failure isolation (spec §17)

Email and WhatsApp are **separate queued jobs with separate idempotency rows**. Neither can cancel the other. A job failure writes `status=failed` + `failure_reason` to its own delivery row and stops there.

---

## 8. Settings UI

**Location:** Settings → Communications → Internal Notifications
**Gate:** `role_module_permissions.can_settings` via `HasModuleSettingsAccess`.

Matrix, grouped by Scheduling / Daily Operations. Per row: label, WhatsApp toggle, Email toggle, ⚙️ configure. Configure panel shows only: send time (scheduled notifications), recipient checkboxes (configured mode only), two channel toggles. Nothing technical — no keys, no cron strings, no template IDs.

Follows the standing UI rules: admin/KPI surface may be information-dense; it must not leak database terminology.

---

## 9. Test plan (spec §23)

1. Doctor A receives only Doctor A's appointments; Doctor B only Doctor B's.
2. Configured Huddle recipients receive it; unselected users do not.
3. Channel matrix: Email-only, WhatsApp-only, both, neither.
4. Scheduler run twice → exactly one delivery row per (notification, user, channel, date).
5. Send time honours `Asia/Kolkata`, not UTC.
6. Branch A recipients never receive Branch B data (stand-in for the clinic-isolation test until `clinic_id` lands).
7. WhatsApp job failure leaves the email delivery `sent`.
8. Email job failure leaves the WhatsApp delivery `sent`.
9. Existing Huddle screen, `tulip:huddle`, and appointment behaviour unchanged.

---

## 10. Recommended V1.1 slice order

| Slice | Content | Depends on |
|---|---|---|
| **S0** | Infra: set a real `MAIL_MAILER`, verify queue worker, make existing Mailables `ShouldQueue` | B1, B8 |
| **S1** | Foundation: 3 migrations, registry, `CommunicationPayload`, idempotency store, dispatcher command, delivery log. No UI, no channels — prove idempotency with a null renderer. | S0 |
| **S2** | Email channel + `doctor_daily_schedule` end-to-end. First real delivery. | S1 |
| **S3** | Settings UI matrix + configure panel. | S2 |
| **S4** | `daily_huddle` via `HuddleService`, **absorbing** `comm:morning-briefing` / `comm:evening-summary` / `comm:sla-alert`. | S3, B7 |
| **S5** | Event-driven set: online booking, reassigned, cancelled, inventory, collections. | S4 |
| **S6** | WhatsApp channel — only after Meta templates are approved. Dormant behind a feature flag until then. | B2 |

Each slice ships, is tested, and stops for review — per the standing one-slice-then-report rule.

---

## 11. Explicit non-goals

No patient communication triggers. No new WhatsApp or email provider. No AI. No Huddle redesign. No appointment redesign. No inbox/thread/chat UI for any channel. No second Huddle calculation. No invented financial metrics. No duplicate consent, logging, or template systems.

---

## 12. Open questions for CEO at V1.1 kickoff

1. **Email provider** — which SMTP/API service, and does it exist on the Hostinger VPS today?
2. **Huddle brain** — confirm `Services\Huddle\HuddleService` (Brain A) as the canonical payload source.
3. **Absorb the existing digests?** — morning briefing / evening summary / SLA alert folded into the engine, or kept separate with a written reason?
4. **WhatsApp for internal staff** — worth 7 Meta template approvals, or is Email-only the honest V1.1 scope with WhatsApp as V1.2?
5. **Staff opt-out** — does a staff member get to switch off notifications addressed to them, or is it owner-controlled only?
