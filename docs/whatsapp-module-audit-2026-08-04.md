# WhatsApp Module — Complete Technical Architecture Audit
**Dentfluence OS — Laravel codebase (`E:\Dentfluence\Dentfluence_OS\Dentfluence Web`)**
**Audit type: read-only, code inspection only. Nothing was modified.**
**Date: 2026-08-04**

---

## 1. Executive summary

There are **two structurally separate WhatsApp systems** in the codebase, plus a third dead-end UI:

1. **Click-to-chat (`wa.me`) system** — LIVE, production-wired, actually used today. No Meta API or credentials involved. Staff tap a button, their own WhatsApp opens pre-filled, they hit send manually. Consent-gated server-side.
2. **WhatsApp Cloud API (Meta Graph API) system** — fully built (two-way inbox, threads, messages, templates, consent gate, audit log, Integration Engine connector) but `WHATSAPP_ENABLED` defaults to `false` and `WHATSAPP_DRY_RUN` defaults to `true`. Nothing transmits to Meta unless both env vars are explicitly flipped in production (not verifiable from the repo — `.env` isn't tracked).
3. **Inbound WhatsApp webhook → PRM Lead pipeline** (`WhatsAppLeadController`) — fully coded but **UNROUTED**. Confirmed by grep: the class is imported in `routes/api.php` but never appears in a `Route::` call anywhere. Same is true for the sibling `MetaLeadController` and `WebsiteLeadController`. This corroborates the "unrouted webhooks" P0 already logged in the 08-03 Master Register audit — and confirms it explicitly includes WhatsApp. **`InboundMessageService` (which threads inbound messages into the unified inbox) is unreachable in production as a result.**
4. **Marketing bulk WhatsApp campaign send** (compose panel + `ProcessScheduledPost` job) — UI lets a user pick WhatsApp as a broadcast platform, but the backend deliberately fails the job with an honest error (a prior bug silently "succeeded" while sending nothing — since fixed). **Confirmed non-functional by design** — dead-end UI, no real API wired for bulk/template broadcast.

Note on `META_GRAPH_VERSION`: current code default in `config/whatsapp.php` is env `WHATSAPP_GRAPH_VERSION`, defaulting to `v21.0` — not v23 as an older memory note claimed (that memory is stale on this point). The separate PRM Lead-Ads webhook config uses its own `PRM_META_GRAPH_VERSION`, default `v19.0`.

---

## 2. Complete file inventory

### Controllers
| File | Role |
|---|---|
| `app\Http\Controllers\Webhooks\WhatsAppLeadController.php` | Inbound Meta WhatsApp webhook → creates PRM lead + threads message. **UNROUTED — dead code at HTTP layer.** |
| `app\Http\Controllers\Webhooks\VerifiesMetaSignature.php` | Shared trait: GET challenge handshake + HMAC-SHA256 `X-Hub-Signature-256` verification. Used by `WhatsAppLeadController` and `MetaLeadController`. |
| `app\Http\Controllers\Communication\WhatsAppInboxController.php` | Legacy two-way inbox UI: `index`, `show`, `reply`, `sendTemplate`. Routed, gated by `communication.access` + `module:communication`. Per its own docblock, deliberately not linked from nav (superseded by PRE). |
| `app\Http\Controllers\Communication\WhatsAppLinkController.php` | Single POST endpoint — model-agnostic click-to-chat link builder used app-wide. |
| `app\Http\Controllers\Relationship\WhatsAppOverviewController.php` | PRE-native read-only "all conversations" list. Rows link to `relationship.profile`, never to the legacy inbox. |
| `app\Http\Controllers\Api\V1\WhatsappController.php` | Mobile API: `thread` (GET), `send` (POST, consent-gated Cloud API text send), `link` (POST, click-to-chat parity). |
| `app\Http\Controllers\Marketing\IntegrationController.php` (`showWhatsappForm`, `saveWhatsapp`) | Marketing settings UI storing a WhatsApp Business connection into `PlatformConnection` — a credential store separate from `config/whatsapp.php`, used only by the (non-functional) campaign feature. |
| `app\Http\Controllers\Prescription\PrescriptionController.php::sendWhatsApp` | Sends a finalized prescription via click-to-chat, DPDP consent-gated. |
| `app\Http\Controllers\Relationship\TodayController.php::sendBirthdayWhatsapp` | One-click birthday message via `WhatsAppLinkService`. |
| `app\Http\Controllers\Api\V1\InventoryController.php::purchaseOrderWhatsapp` | Builds a WhatsApp message string for a purchase order (vendor comms, click-to-chat). |

### Services
| File | Role |
|---|---|
| `app\Services\Whatsapp\WhatsAppCloudService.php` | Only class that talks HTTP to Meta Graph API: `sendText`, `sendTemplate`, payload builders, phone normalization, enabled/dry-run/credential gating. |
| `app\Services\Whatsapp\OutboundMessageService.php` | Orchestration for Cloud API sends: thread resolution, DPDP consent gate, `wa_messages` record, provider call (direct or via `IntegrationEngine`), audit log. |
| `app\Services\Whatsapp\InboundMessageService.php` | Records inbound Cloud API messages into `wa_threads`/`wa_messages`, opens the 24h reply window, dedupes on Meta's message id. **Only caller is the unrouted `WhatsAppLeadController` — unreachable in production.** |
| `app\Services\Communication\WhatsAppLinkService.php` | Single source of truth for click-to-chat: phone normalization, DPDP consent + do-not-contact gating (via `CommunicationGuard`), template rendering, `wa.me` URL building. **This is the live, actually-used send path.** |

### Integration Engine (Phase 7 abstraction layer)
| File | Role |
|---|---|
| `app\Integration\Connectors\WhatsAppConnector.php` | Implements `MessagingConnectorInterface`; thin wrapper around `WhatsAppCloudService`, plus side-effect-free preview methods for shadow-comparison logging. |
| `app\Integration\IntegrationEngine.php` (`whatsapp()`, `logWhatsAppText()`, `logWhatsAppTemplate()`) | Feature-flagged (`integration.whatsapp`, default off) routing indirection + shadow-log comparison between legacy direct call and connector call. |

### Console commands
| File | Purpose |
|---|---|
| `app\Console\Commands\WhatsAppTest.php` | `whatsapp:test {phone} --message= --patient= --category=` — manual full-pipeline text send, dry-run safe. |
| `app\Console\Commands\WhatsAppTemplateTest.php` | `whatsapp:template {phone} {template} --patient= --var=*` — manual template send test. |
| `app\Console\Commands\WhatsAppSendReminders.php` | `whatsapp:send-reminders --days= --branch= --dry-run` — production automation: sends `appointment_reminder` template to tomorrow's scheduled appointments, idempotent via `dedup_key`. Scheduled daily. |

### Scheduling (`routes/console.php`)
```php
Schedule::command('whatsapp:send-reminders')
    ->dailyAt(...)
    ->appendOutputTo(storage_path('logs/whatsapp-reminders.log'));
```
Comment confirms: "dormant unless WHATSAPP_ENABLED=true; safe in dry-run." A second scheduled block (review-request-over-WhatsApp) is gated behind `REVIEWS_ENABLED + WHATSAPP_ENABLED`, also dormant per its own comment unless both flags are on.

### Models / database
| Model | Table | Notes |
|---|---|---|
| `App\Models\WaThread` | `wa_threads` | One conversation per (channel, contact_phone). `last_preview` encrypted. Relations: `messages()`, `patient()`, `lead()`, `assignedUser()`. Helper `isWindowOpen()` = Meta 24h rule. |
| `App\Models\WaMessage` | `wa_messages` | `body` encrypted (PHI). Direction constants `INBOUND`/`OUTBOUND`; status `QUEUED/SENT/DELIVERED/READ/FAILED/RECEIVED`. |

Migrations (only two tables are WhatsApp-specific):
- `2026_06_29_220000_create_wa_threads_table.php` — `channel`, `contact_phone` (unique w/ channel), `contact_name`, `patient_id`/`lead_id` (nullOnDelete), `status`, `last_preview` (encrypted), timestamps, `window_expires_at`, `unread_count`, `assigned_to_id`.
- `2026_06_29_220001_create_wa_messages_table.php` — `wa_thread_id` (FK cascade), `channel`, `direction`, `wa_message_id` (indexed), `from_phone`/`to_phone`, `type`, `body` (encrypted), `template_name`/`template_payload` (json), `media_url`/`media_mime`, `status`, `error`, `sent_by_id`, `payload` (json).

No other migration adds WhatsApp-specific columns. `relationship_contact_log` is a generic multi-channel contact log written by `CommunicationGuard::log()` for `whatsapp` among other channels — shared infrastructure, not WhatsApp-specific.

### Config
- `config/whatsapp.php` — Cloud API: `enabled`, `dry_run` (env-driven, safe defaults), `graph_version` (v21.0 default), `phone_number_id`, `business_account_id`, `access_token`, `default_country_code` (91), `timeout`, `consent.*`, full `templates` catalog (`appointment_reminder`, `appointment_confirmation`, `recall_due`, `payment_reminder`, `lab_ready`, `review_request`, `festive_offer`).
- `config/prm.php` — `webhooks.whatsapp` block (`enabled`, `verify_token`, `app_secret`) consumed by the unrouted `WhatsAppLeadController`; `replies.channels` includes `whatsapp` for AI draft replies.
- `config/communication.php` — `whatsapp` block: `mode => 'web_open'` (comment: "future: 'api'"), `web_url => 'https://wa.me/'`, `country_code`, `api_ready => false`, `templates` array of click-to-chat copy per context. This is what `WhatsAppLinkService::render()` reads — entirely separate from `config/whatsapp.php`'s Meta template catalog.

### Views
- `resources\views\components\communication\whatsapp-button.blade.php` — reusable click-to-chat button, two modes: DIRECT (legacy, no consent gate) and CONTEXT (posts to `communication.whatsapp.link`, consent-gated). Used in recalls, quick-actions, prescriptions.
- `resources\views\marketing\integrations\whatsapp.blade.php` — WhatsApp Business connection settings form.
- `resources\views\marketing\publish\partials\_panel1-whatsapp.blade.php` — WhatsApp compose panel in the universal marketing publish UI. Backend deliberately fails these posts — **UI-only, non-functional send.**
- `communication.whatsapp.index`/`.show` — legacy inbox views.
- `relationship.whatsapp.index` — PRE conversations list view.

### No Livewire / Filament
No Livewire component classes or Filament resources touch WhatsApp. The app does not use Filament; all UI is server-rendered Blade + vanilla JS (`dfWhatsAppSend()`).

### Tests
- `tests\Feature\Marketing\ProcessScheduledPostWhatsappTest.php` — the only automated test for anything WhatsApp-related; proves the marketing bulk-send path fails honestly rather than silently.

### Docs
- `docs\mobile-whatsapp-clicktochat.md` — Flutter integration guide for the click-to-chat API endpoint; states backend was done 2026-07-18 but the mobile-side integration status is unverified from this repo.

---

## 3. Routes (verbatim)

**`routes/communication.php`** (behind `web, auth, communication.access, module:communication`):
```php
Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
    Route::post('/link',              [WhatsAppLinkController::class, 'build'])->name('link');
    Route::get('/',                   [WhatsAppInboxController::class, 'index'])->name('index');
    Route::get('/{thread}',           [WhatsAppInboxController::class, 'show'])->name('show');
    Route::post('/{thread}/reply',    [WhatsAppInboxController::class, 'reply'])->name('reply');
    Route::post('/{thread}/template', [WhatsAppInboxController::class, 'sendTemplate'])->name('template');
});
```

**`routes/relationship.php`**:
```php
Route::get('/whatsapp', [WhatsAppOverviewController::class, 'index'])->name('whatsapp');
Route::post('/today/birthday-whatsapp', [TodayController::class, 'sendBirthdayWhatsapp'])->name('today.birthday-whatsapp');
```

**`routes/api.php`**:
```php
Route::post('/patients/{patient}/whatsapp/send',  [WhatsappController::class, 'send']);
Route::post('/patients/{patient}/whatsapp/link',  [WhatsappController::class, 'link']);
Route::get('/patients/{patient}/whatsapp/thread', [WhatsappController::class, 'thread'])
    ->middleware('api.role:module:patients,view');
Route::get('/inventory/purchase-orders/{po}/whatsapp-message', [InventoryController::class, 'purchaseOrderWhatsapp']);
```
A comment confirms a `bulk-whatsapp` API route was removed 2026-07-14 as orphaned dead code.

**`routes/marketing.php`**:
```php
Route::get('/integrations/whatsapp/setup', [IntegrationController::class, 'showWhatsappForm'])->name('integrations.whatsapp-setup');
Route::post('/integrations/whatsapp/save', [IntegrationController::class, 'saveWhatsapp'])->name('integrations.whatsapp-save');
```

**`routes/prescriptions.php`**:
```php
Route::post('/{prescription}/whatsapp-send', [PrescriptionController::class, 'sendWhatsApp'])
    ->name('whatsapp-send')->middleware('module:prescriptions,edit');
```

**Confirmed UNROUTED:** `WhatsAppLeadController` (webhook verify/receive) — imported in `routes/api.php` but never used in a `Route::` call anywhere. Same for `MetaLeadController` and `WebsiteLeadController`.

---

## 4. End-to-end message flows

### (a) Outbound — click-to-chat (the LIVE path)
1. Blade renders `<x-communication.whatsapp-button context="..." :patient-id="..." :params="[...]" />`.
2. Click → JS POSTs to `communication.whatsapp.link` (`WhatsAppLinkController::build`).
3. Controller resolves the `Patient`, calls `WhatsAppLinkService::prepareParams()` then `::resolve()`.
4. `resolve()` runs `guardDecision()`: `CommunicationGuard::hasWhatsAppConsent()` (DPDP, shadow-logged unless `guard.consent_required` flag on) and do-not-contact/channel eligibility (shadow-logged unless `guard.full_8factor` flag on).
5. If allowed: renders copy from `config('communication.whatsapp.templates.{context}')`, builds `https://wa.me/<phone>?text=<encoded>`.
6. Logs the contact via `CommunicationGuard::log()` → `relationship_contact_log`.
7. Returns `{success, url, phone}`; JS opens the URL — staff's own WhatsApp sends manually. **No message content is recorded in `wa_threads`/`wa_messages` for this path.**

### (b) Outbound — Cloud API text (`OutboundMessageService::sendText`)
1. Caller (console command or mobile `WhatsappController::send`) invokes `sendText($phone, $body, $opts)`.
2. `resolveThread()` finds/creates a `WaThread` (matches known `Patient` by last-10-digit phone).
3. `consentGate()` — DPDP check keyed on `whatsapp_comms` (service) or `marketing_promotions` (marketing) purpose. Unknown number + service = allowed only if 24h window open; unknown number + marketing = always blocked.
4. If allowed: creates `WaMessage` (`status=queued`), calls `IntegrationEngine::whatsapp()` (if flag on) or `WhatsAppCloudService::sendText()` directly.
5. `dispatch()`: `!enabled` → `status='disabled'`, no-op. `dry_run` → logs payload, `status='dry_run'`, no network call. Else POSTs to `https://graph.facebook.com/{version}/{phone_number_id}/messages`.
6. Updates `WaMessage` with `wa_message_id`/status/error/raw payload; updates thread activity; writes `AuditLog::event('whatsapp_sent', ...)`.

### (c) Outbound — Cloud API template (used by `whatsapp:send-reminders`)
Same pipeline as (b), plus idempotency via `dedup_key`, ordered template variables per config, and templates always require a known consented patient (never sent to unknown numbers, even within the 24h window).

### (d) Inbound webhook — NOT REACHABLE IN PRODUCTION
Designed path: Meta → `WhatsAppLeadController::receive()` → `VerifiesMetaSignature` (fail-closed HMAC-SHA256 check) → `LeadIngestService::ingest()` (new number → PRM lead) + `InboundMessageService::record()` (threads message, opens 24h window). **Dead at the HTTP layer — no route registers this controller.**

### (e) Marketing bulk campaign — dead-end by design
Compose UI lets a user pick WhatsApp with message-type/media/template fields; `ProcessScheduledPost::dispatchToPlatform()` explicitly fails WhatsApp variants with a clear error (previously silently "succeeded" while sending nothing — fixed). `IntegrationController::saveWhatsapp` stores credentials nothing currently consumes.

---

## 5. Signature verification (key security logic)

```php
// app/Http/Controllers/Webhooks/VerifiesMetaSignature.php
protected function signatureValid(Request $request, ?string $appSecret): bool
{
    if (! $appSecret) return false; // fail closed if no secret configured
    $header = (string) $request->header('X-Hub-Signature-256', '');
    if (! str_starts_with($header, 'sha256=')) return false;
    $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);
    return hash_equals($expected, $header);
}
```
Correctly fail-closed — but moot while the route is unregistered.

---

## 6. Dependencies

### WhatsApp code depends on
`Patient`, `Lead`, `CommunicationGuard` (DPDP consent + do-not-contact + `relationship_contact_log` writer), `ConsentPurpose`/`PatientConsent`, `AuditLog`, `Feature` flags (`guard.consent_required`, `guard.full_8factor`, `integration.whatsapp`), `IntegrationEngine`/`MessagingConnectorInterface`, `Appointment` (reminder source data), `App\Casts\Encrypted`, `LeadIngestService` (webhook path, currently unreachable).

### Other modules depend on WhatsApp code
- **Relationship/PRE**: `TodayController::sendBirthdayWhatsapp`, `WhatsAppOverviewController`, recalls index view.
- **Prescriptions**: `PrescriptionController::sendWhatsApp`.
- **Patient profile / quick-actions**: shared button component.
- **Mobile app (API)**: `Api\V1\WhatsappController`.
- **Inventory**: `purchaseOrderWhatsapp` (vendor comms, message text only).
- **Reviews**: review-request links go out via click-to-chat; a scheduled review-request WhatsApp job exists, dormant unless flags on.
- **Marketing**: campaign UI references WhatsApp as a platform (currently fails honestly).

---

## 7. Usage / production-wiring assessment

| Piece | Routed? | Reachable from UI? | Functionally live? |
|---|---|---|---|
| Click-to-chat button + `WhatsAppLinkService`/`Controller` | Yes | Yes (recalls, birthday, prescriptions, quick-actions) | **YES — the one real, working WhatsApp feature today.** |
| `WhatsAppInboxController` (legacy inbox) | Yes | Not in nav (deliberate) | Reachable by direct URL only |
| `WhatsAppOverviewController` (PRE list) | Yes | Yes, in PRE subnav | Read-only list; live |
| `OutboundMessageService`/`WhatsAppCloudService` | Reachable via console + API/mobile | Code path executes | Only transmits to Meta if `WHATSAPP_ENABLED=true` and `WHATSAPP_DRY_RUN=false` in prod `.env` (unverifiable from repo) |
| `whatsapp:send-reminders` | Scheduled daily | N/A | Same enabled/dry-run gating |
| `WhatsAppLeadController` (inbound webhook) | **No — unrouted** | N/A | **DEAD — cannot receive real Meta traffic** |
| `InboundMessageService` | Only called from unrouted controller | N/A | **Unreachable in production** |
| Marketing WhatsApp campaign panel | Routed | Yes, selectable | **Fails honestly, sends nothing** (by design) |
| Marketing WhatsApp settings form | Routed | Yes | Stores credentials nothing consumes |

**Rough estimate: roughly 25-30% of the built WhatsApp module is doing real work in production** (the click-to-chat path, the PRE conversations list, the scheduled reminder job in whatever state its env flags are actually set to). The remaining ~70% (Cloud API two-way messaging, inbound webhook, marketing broadcast) is built but either flag-gated off, unrouted, or intentionally non-functional.

---

## 8. Classification

**UI-only:** `whatsapp-button.blade.php`, `_panel1-whatsapp.blade.php` (non-functional backend), `marketing/integrations/whatsapp.blade.php`.

**Backend infrastructure (WhatsApp-specific):** `WaThread`/`WaMessage` models + migrations, `WhatsAppCloudService`, `OutboundMessageService`, `InboundMessageService`, `WhatsAppConnector`, console commands, `WhatsAppLeadController` (dead — unrouted).

**Communication-engine parts (shared / channel-agnostic):** `CommunicationGuard`, `relationship_contact_log`, `VerifiesMetaSignature` trait (shared with Meta Lead Ads), `AuditLog`, `config/prm.php` `replies.channels`.

**Meta API integration parts:** `WhatsAppCloudService`, `config/whatsapp.php`, `WhatsAppConnector` preview/shadow-log methods.

**Reusable by Email/SMS by design:** `WaThread`/`WaMessage` schema explicitly carries a `channel` column "so the same structure can later cover SMS / Instagram DMs etc. without a rebuild" (migration docblock); `CommunicationGuard`, `relationship_contact_log`, `AuditLog`, the `MessagingConnectorInterface`/`IntegrationEngine` connector pattern.

**Never delete — structural dependents exist:**
- `WhatsAppLinkService` + `communication.whatsapp.link` route — depended on by Prescriptions, Relationship/Recalls/Today, Patient quick-actions, mobile API, shared Blade component. Removing this breaks the one WhatsApp feature that's actually live.
- `CommunicationGuard` — used by the whole 8-factor consent/guard system, not just WhatsApp.
- `WaThread`/`WaMessage` tables — depended on by inbox, PRE overview list, mobile thread read, and any future Cloud API re-enablement.

---

## 9. Final component table

| Component | Used | Reusable | Safe to Delete | Reason |
|---|---|---|---|---|
| `WhatsAppLinkService` + `WhatsAppLinkController` | Yes | Yes (pattern) | No | Only live, working WhatsApp send path; multiple modules call it directly |
| `whatsapp-button.blade.php` | Yes | Yes | No | Used in recalls, quick-actions, prescriptions |
| `WhatsAppCloudService` | Partial (env-gated) | Yes | No | Sole Graph API client; needed the moment `WHATSAPP_ENABLED` flips true |
| `OutboundMessageService` | Partial (console, mobile, reminders) | Yes | No | Central consent+send orchestration; deleting breaks reminders + mobile send |
| `InboundMessageService` | No (unrouted caller) | Yes | **No — fix routing instead** | Correctly designed; just needs a route registered |
| `WhatsAppLeadController` + verify/receive | **No — unrouted** | Yes | **No — this is a fix-it, not a delete-it** | Matches Master Register "unrouted webhooks" P0; nearly-complete feature, just disconnected |
| `WaThread`/`WaMessage` models + migrations | Partial | Yes (multi-channel by design) | No | Depended on by inbox, overview list, mobile thread read |
| `WhatsAppInboxController` (legacy inbox) | Routed, unlinked from nav | Yes | Product decision, not technical | Superseded by PRE overview per its own docblock; kept intentionally in background |
| `WhatsAppOverviewController` (PRE list) | Yes, in nav | No | No | Active nav item |
| `whatsapp:send-reminders` + schedule | Yes (cron) | No | No | Live automation, env-flag gated |
| `WhatsAppConnector`/`IntegrationEngine` whatsapp methods | Conditional (flag default off) | Yes | No | Deliberate abstraction seam for future provider swap |
| Marketing `_panel1-whatsapp.blade.php` + campaign send | UI-only, backend fails intentionally | No | **Candidate for removal or must-fix** | Confirmed non-functional — either wire it or remove the misleading UI option |
| Marketing `IntegrationController` whatsapp settings | Yes (form works, saves) | No | **Candidate for removal** | Stored credentials aren't consumed by any send path — orphaned vs. `config/whatsapp.php` |
| `PrescriptionController::sendWhatsApp` | Yes | No | No | Live, DPDP-gated |
| `TodayController::sendBirthdayWhatsapp` | Yes | No | No | Live PRE feature |
| `InventoryController::purchaseOrderWhatsapp` | Yes | No | No | Live vendor-comms feature |
| `Api\V1\WhatsappController` | Yes (mobile) | No | No | Mirrors web consent gate |
| `docs/mobile-whatsapp-clicktochat.md` | Reference only | N/A | N/A | Documentation |
| `WhatsAppTest.php`/`WhatsAppTemplateTest.php` | Dev/ops tooling | N/A | No | Only manual way to verify the Cloud API pipeline without UI |

---

## 10. Key finding worth flagging

The **inbound WhatsApp webhook is unrouted** — same class of bug already flagged P0 for Meta Lead Ads and website-lead webhooks in the 08-03 Master Register audit. Practical effect: (1) no inbound WhatsApp message can ever auto-create a PRM lead today, and (2) no inbound WhatsApp message can populate the two-way inbox — the only way rows land in `wa_threads`/`wa_messages` today is via **outbound** sends. Given CEO Directive #004's focus on finishing real-use-validated flows, this is a cheap, contained fix: one route pair in `routes/api.php` pointing at the already-written `WhatsAppLeadController::verify`/`receive` methods (fail-closed signature verification already implemented) — it would unlock the two-way inbox feature that already has a UI, models, and services built for it.

No files were modified as part of this audit.
