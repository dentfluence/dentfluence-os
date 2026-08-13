# Technical Architecture Assessment — Can Dentfluence Become an AI-First Dental OS?

**Directive:** CEO Directive #001 · **Prepared by:** Principal Engineer (Claude) · **For:** CTO review
**Date:** 2026-07-19 · **Type:** Architecture review (no code written)
**Method:** Read-only crawl of the live codebase (`app/Services`, `app/Http/Controllers`, `config`, `routes`, `app/Models`), three parallel evidence passes, and direct file verification of every high-stakes claim. Every conclusion is cited to a real file. Where something does not exist, this document says **"could not find it."**

---

## Headline finding (read this first)

**The AI-first layer is not a future project — it already exists and is wired into every authenticated page.** The stale internal note "AI Copilot 'Tulip' — design locked, unstarted" is **wrong**. Verified in code:

- A working agent loop: `app/Services/Assistant/AssistantService.php` (`ask()` loops up to 5 steps, calls the model, executes tools, feeds results back).
- A local LLM client: `app/Services/Assistant/OllamaClient.php` (Ollama, `qwen2.5:7b`, tool-calling, retry on malformed JSON).
- A tool registry with **14 registered tools**: `app/Services/Assistant/ToolRegistry.php` (verified — 14 `register()` calls).
- A confirm-before-write pattern with full audit: `ConfirmableTool` interface + `AiActionLog` model (`app/Models/AiActionLog.php`) with `pending/success/failed/rejected` states.
- Voice input already plumbed: `POST /assistant/transcribe` → `app/Services/Voice/TranscriptionService.php` (local faster-whisper).
- Rendered on every page: `resources/views/layouts/app.blade.php:1137` — `@if(config('assistant.enabled')) @include('partials.tulip-assistant')`.

So the CTO's own framing — *"the engine is built, the switches are off"* — applies to the AI layer too. **The question is not "can we build AI-first?" It is "the AI-first scaffold exists; is it safe to widen, and what is stopping it?"** That reframing drives every answer below.

---

## 1. Can Dentfluence realistically become an AI-first OS without major refactoring?

**Yes — and with less new construction than expected, because the scaffold already runs.** But "without major refactoring" is true only if we accept one disciplined correction: **the AI tools must call the canonical services/engines, and the tool-runner must enforce the same permission/tenancy/consent guards the app already has.** Today they partly do not (evidence in §5).

Why it's realistic (evidence, not opinion):

- **The service layer was already designed for a non-web caller.** A codebase-wide grep found **zero `Illuminate\Http\Request` dependencies anywhere in `app/Services/`** (only `Marketing/OAuthService.php` and `DataRightsService.php` touch Request, both outside the core engines). Core services take plain models/arrays and an explicit `User $actor`. Example: `AppointmentService::create(array $in, User $actor)`, `TreatmentPlanAcceptanceService::accept(TreatmentPlan $plan, ?User $actor)`. This is unusually AI-tool-friendly for a Laravel app — a headless caller (an AI tool) can invoke them directly.
- **An event spine already decouples cause from effect.** `ActivityEngine::log()` → `RulesEngine::evaluate()` → tasks/reminders/notifications. An AI action that writes through a service automatically triggers the same downstream automation a human action would.
- **The agent loop, audit log, and confirm-card already exist** (see headline). We are extending a running system, not bootstrapping one.

Why it is *not* "zero refactoring": the transaction boundaries and much business logic still live **inside controllers**, duplicated between web and API (§5.1, §5.2). Where an AI tool needed that logic, it **reimplemented it more weakly** rather than calling a shared service — this already shipped in `BookAppointmentTool` (§5.2). That is the real work: not a rewrite, but **extracting the last controller-bound logic into services and re-pointing the AI tools at them.** Reuse over rewrite is not just possible here — it is the fix.

**Verdict: AI-first is achievable on the existing architecture. Classified effort to make it *safe* (not to invent it): Medium.**

---

## 2. Existing services/engines that can become AI Tools

Every entry below is a real class verified this pass. Classification: **CLEAN** = takes plain args / explicit `User` actor, no session coupling, safe to wrap today. **CAVEAT** = mostly clean but reaches for `Auth::` internally as a fallback — needs an explicit-actor pass first. All paths under `app/Services/`.

| Engine / Service | File | Tool it becomes | Ready? |
|---|---|---|---|
| **TodayActionsEngine** | `Relationship/TodayActionsEngine.php` | "What needs doing today" (13+ categories) | CLEAN — best single candidate |
| **UnifiedTimelineService** | `Relationship/UnifiedTimelineService.php` | "Give me this patient's full history" | CLEAN |
| **IdentityResolver** | `Relationship/IdentityResolver.php` | "Who is this person / find duplicates" | CLEAN (read-only) |
| **ActivityEngine** | `Relationship/ActivityEngine.php` | Log an event / read a timeline | CLEAN |
| **RulesEngine** | `Relationship/RulesEngine.php` | "What automation fires on event X" | CLEAN |
| **CommunicationGuard** | `Relationship/CommunicationGuard.php` | "Can we message this patient?" (consent) | CLEAN (read) |
| **CommunicationEngine** | `Relationship/CommunicationEngine.php` | Send a guarded WhatsApp message | CLEAN — built, *not yet wired* (§9) |
| **TaskEngine / ReminderEngine / NotificationEngine** | `Relationship/*.php` | Create task / reminder / notify | CLEAN (TaskEngine: pass `$branchId`) |
| **RelationshipScoreEngine** | `Relationship/RelationshipScoreEngine.php` | "How engaged is this patient?" | CLEAN |
| **JourneyService** | `Relationship/JourneyService.php` | Lead/opportunity stage mapping | CLEAN |
| **AppointmentService** | `AppointmentService.php` | Book / reschedule / cancel / check slot | CLEAN — the cleanest core service |
| **PatientService** | `PatientService.php` | Create / search / dedupe patients | CLEAN except `updateFromInput()` (CAVEAT) |
| **WalletService** | `WalletService.php` | Balance, credit/debit/refund | CLEAN (money → confirm-gate) |
| **CouponService** | `CouponService.php` | Validate / apply coupon | CLEAN |
| **TreatmentPlanAcceptanceService** | `TreatmentPlan/TreatmentPlanAcceptanceService.php` | "Accept this treatment plan" (single door) | CLEAN |
| **TreatmentVisitService** | `TreatmentVisitService.php` | Log a treatment visit | CLEAN |
| **PrescriptionAlertService** | `Prescription/PrescriptionAlertService.php` | "Check these drugs vs this patient" | CLEAN — high clinical value |
| **PatientProfileService** | `PatientProfileService.php` | Full patient-detail payload | CLEAN |
| **InsightsEngine** (+ Health/LTV/Risk calculators) | `Insights/*.php` | Risk / lifetime-value / health signals | CLEAN (read) |
| **SearchIndexProjector** | `Search/SearchIndexProjector.php` | Fuzzy patient/relationship search | CLEAN (index is flag-off — may be stale, §9) |
| **AutomationEngine** | `Automation/AutomationEngine.php` | Pure timing primitives (due/retry/cooldown) | CLEAN — pure functions, zero risk |
| **RecallEngineService / RecallAutomationRunner** | `RecallEngineService.php`, `Automation/RecallAutomationRunner.php` | Run/preview recall sweep | CAVEAT / CLEAN |
| **WorkflowEngine** | `Workflow/WorkflowEngine.php` | Start/advance a workflow | CLEAN (flag-off, §9) |
| **InvoicePaymentService** | `Billing/InvoicePaymentService.php` | Record a payment | CAVEAT (mixes `Auth::` with explicit `userId`) |

**Do NOT wrap:** `Relationship/AppointmentReminderEngine::generateReminders()` — its own docblock says it is broken (writes `created_by => null` against a NOT NULL column) and is superseded by `Automation/ReminderAutomationRunner`.

**Vision services** (`Assistant/ReceiptScanService`, `PatientScanService`, `VisionService`) are already AI tools of a different kind (image→structured data) and are wired into Finance ("Snap-a-Bill") and Patient intake.

---

## 3. How should the AI Assistant talk to the application? — A / B / C / D

**Answer: C then B — call the Engines and Services. Never call Controllers (A). And route every call through a hardened tool-runner (a light "D" wrapper) that adds the guards controllers do inline.**

Reasoning, from evidence:

- **Not Controllers (A).** Controllers here are HTTP adapters that also carry business logic *and* the transaction boundary (`DB::transaction` appears **45 times across 23 controllers**). Calling them means faking a `Request`, dealing with redirects/JSON responses, and inheriting `auth()->id()` session coupling. Worse, the same operation is implemented **twice and divergently** in web vs API controllers (§5.2), so "call the controller" has no single truth to call.
- **Engines (C) for cross-cutting operations** — `ActivityEngine`, `RulesEngine`, `TodayActionsEngine`, `CommunicationEngine`. These are stateless, dependency-injected, and already the intended integration surface.
- **Services (B) for entity operations** — `AppointmentService`, `TreatmentPlanAcceptanceService`, `WalletService`, `CouponService`. They take an explicit `User $actor`, own their own transactions, and throw catchable exceptions the tool can explain back to the user.
- **The "D" wrapper is the missing piece.** The existing `AssistantService::runTool()` already logs to `AiActionLog` and gates clinical/financial writes behind a confirm card. What it does **not** do — verified — is call `User::canAccess()` (the app's real permission gate) or enforce tenancy. The tool-runner is the correct, single place to add: (1) module/role permission check, (2) branch/tenant scoping, (3) consent routing for any send. Put the guards here once, and every current and future tool inherits them.

**In one line:** AI → hardened tool-runner → **canonical Service/Engine** → DB. The controller is bypassed; the guards controllers do inline move into the tool-runner and (ideally) into the services themselves.

---

## 4. The ideal flow — mapped onto what already exists

The requested flow is not hypothetical; most of it is already running. Real class names in brackets; ⚠️ marks a real gap.

```
Voice (mic in the Tulip widget)
   │
   ▼
Speech Recognition
   [POST /assistant/transcribe → Voice\TranscriptionService::transcribe()]
   Local faster-whisper via scripts/voice/transcribe.py. Staff may dictate in
   Hindi/Marathi; the model replies in config('assistant.reply_language').
   ⚠️ Wake word ("Hey Tulip", Porcupine) is coded but OFF by default
      (ASSISTANT_WAKE_ENABLED=false) — needs a Picovoice key.
   │
   ▼
Intent Detection + Tool Selection
   [AssistantService::ask() → OllamaClient::chat(messages, ToolRegistry::definitions())]
   The LLM (qwen2.5:7b, supports_tools=true) chooses a tool from the 14
   registered function schemas. There is NO separate hand-written intent
   classifier — intent = the model's tool choice. (A deterministic shortcut
   exists for the daily huddle: isHuddleRequest() bypasses the LLM entirely.)
   │
   ▼
Write-safety gate
   [AssistantService::needsConfirmation()]
   If the tool is a ConfirmableTool OR its category ∈ {clinical, financial}
   (config('assistant.confirm_categories')), the action is DEFERRED as a
   pending AiActionLog + a confirm card returned to the UI (deferAction()).
   Low-risk writes (e.g. create_task) auto-execute.
   ⚠️ MISSING: a permission check (User::canAccess) and tenant scoping here.
   │
   ▼
Existing Engine / Service
   [ToolRegistry::get(name)->run($args, $user)]
   ⚠️ TODAY some tools reimplement logic instead of calling the canonical
      service (BookAppointmentTool writes Appointment::create() directly with a
      weaker slot check — §5.2). TARGET: run() delegates to AppointmentService,
      CouponService, etc.
   │
   ▼
Database  (service owns the DB::transaction; ActivityEngine.log fires RulesEngine)
   │
   ▼
Response + Audit
   [runTool() writes AiActionLog (success/failed); reply streamed to the widget]
```

**What is real today:** voice→text, model tool-selection, 14 tools, confirm-cards, audit log, on every page. **What is missing for it to be trustworthy:** the permission/tenancy gate in the runner, and re-pointing the write tools at canonical services.

---

## 5. Architectural weaknesses that would block/impede AI integration (identify only)

Ordered by severity for AI integration. Not to be fixed here.

**5.1 — Business logic lives in controllers, not reusable units. (Blocker)**
Invoice creation, coupon, membership, wallet debit and manual discount are all inline inside `BillingController::store()` (transaction opens `BillingController.php:340`). Booking rules (blocked-slot, overlap, new-patient) are inline in `AppointmentController::store()` (`:117`–`:545`) with no `AppointmentController`-facing service for the *booking decision* itself. An AI tool cannot reuse a rule that only exists inside a controller closure.

**5.2 — Duplicated, drifting logic across web / API / AI-tool. (Blocker — already caused a real defect)**
The same invoice creation is written twice: web `BillingController::store()` vs API `Api/V1/BillingController::createInvoice()` (`:594`). Coupon validation: web reimplements it inline (`validateCoupon()` `:184`), API delegates to `CouponService::validate()` — so a fix to the service silently misses the web path. Most damning: **`BookAppointmentTool` is a third booking implementation** that does only a `whereDate + doctor_id + time-like + exists()` check (verified, `BookAppointmentTool.php:88-92`) and calls `Appointment::create()` directly — it has **no** doctor-on-leave / blocked-slot check and **no** duration-overlap check that `AppointmentService` enforces. An AI can already double-book across a blocked slot. This is the exact risk the directive asked us to find, already shipped.

**5.3 — Tenancy scoping is inert and incomplete. (Blocker for multi-clinic AI)**
`BranchScope` (`app/Models/Scopes/BranchScope.php`) exists but its own docblock (`:23-25`) says it is "effectively inert today" (single-login, everyone admin; admins are skipped `:37-39`). Worse, **`Invoice` has no `branch_id` / `BelongsToBranch` at all** (verified). Enforcement is inconsistent: API `createInvoice()` explicitly re-checks patient branch (`Api/V1/BillingController.php:631-635`); web `store()` does not. There is no single always-on guarantee that a tool scoped to clinic A cannot touch clinic B.

**5.4 — The AI tool layer bypasses the app's permission model. (Friction — security-relevant)**
The app has a real, web/API-consistent gate: `CheckModulePermission` (web) and `EnsureApiRole` (API) both call `User::canAccess($module, $action)` against `RoleModulePermission`. **Grep confirms `canAccess` is called nowhere under `app/Services/Assistant/`.** Any staffer who can open the chat can run any registered write tool regardless of their role. The confirm-card is a UI step, not an authorization check. (Note: `config/permissions.php` is a 0-byte dead file — the real source is the DB table.)

**5.5 — Consent guard is enforced by convention, and partly flag-off. (Minor, but a trap)**
`CommunicationGuard` is well-built and correctly wired into the two live send paths (`WhatsAppLinkService.php:150`, `Whatsapp/OutboundMessageService.php`). But it is a plain method any code can skip — no middleware/model-event enforcement. And `guard.consent_required` / `guard.full_8factor` / `guard.fail_closed` default OFF, so by default the guard largely fails open. An AI send tool must route through those two services specifically, or consent is invisible.

**5.6 — Transaction boundaries are controller-local. (Friction)**
45 `DB::transaction` in controllers vs a handful in services (`WalletService` ×7, `InvoicePaymentService` ×3, `TreatmentPlanAcceptanceService` ×2, `ManualDiscountService` ×2). The services that own their transactions are exactly the reusable ones; the controllers that own theirs are not.

**5.7 — A few services still reach for `Auth::` internally. (Friction)**
`PatientService::updateFromInput()` calls `Auth::user()` with no actor param; `TaskEngine::autoCreate()`, `InvoicePaymentService`, `RecallEngineService::runAll()` fall back to `Auth::` when not given an actor. Headless AI calls need an explicit actor threaded through, or they silently default (e.g. to branch 1 / user 1).

---

## 6. Capabilities already present that AI can expose immediately

**Already registered as live tools** (`ToolRegistry.php`, verified 14):
`find_patient`, `get_schedule` (today's schedule), `patient_summary`, `pending_treatments`, `patient_balance`, `visit_history`, `list_tasks`, `get_report` (KPIs), `membership_report`, `daily_huddle`, `create_task` (write, auto), `add_patient_note` (confirm), `book_appointment` (confirm — but see §5.2), `update_patient_contact` (confirm).

**Clean services ready to wrap with minimal work** (read-only unless noted): full patient timeline (`UnifiedTimelineService`), "who is this / duplicates" (`IdentityResolver`), today's action list across 13+ categories (`TodayActionsEngine`), engagement/risk/LTV signals (`InsightsEngine`), "can we contact this patient" consent check (`CommunicationGuard`), drug-interaction check (`PrescriptionAlertService`), wallet balance (`WalletService::summary`), coupon validation (`CouponService::validate`), slot availability (`AppointmentService::overlapConflict` / `assertSlotIsBookable`), accept treatment plan (`TreatmentPlanAcceptanceService::accept`, write/confirm).

**Vision capabilities already live:** scan a bill → expense (`ReceiptScanService`, in Finance), scan an intake form → patient (`PatientScanService`, in Patient).

**Could not find:** any AI wiring of the voice→clinical-note pipeline into the consultation screen. `Voice\ClinicalNoteService::analyze()` exists and is CLI-testable (`voicenote:test`) but is **not called by any consultation controller** — the clinical dictation capability is built but not surfaced.

---

## 7. Recommended FIRST FIVE AI capabilities

Selection rule: highest user impact × lowest engineering risk × maximum demo value. All five are **read-only or already-confirmed**, so no new write-safety surface. Four of the five already exist as tools — the "implementation" is verification + pointing at the canonical read service + adding the permission check, not greenfield.

1. **Daily Huddle / "What's on today"** — `daily_huddle` + `TodayActionsEngine`. *Impact:* it is the single most-used clinic ritual; *risk:* read-only, and a deterministic non-LLM shortcut already exists; *demo:* one sentence returns the whole day. **The flagship.**
2. **Patient 360 summary** — `patient_summary` + `UnifiedTimelineService`. *Impact:* every clinician wants "tell me about this patient" before they walk in; *risk:* read-only; *demo:* instantly obvious value.
3. **Today's schedule / find a slot** — `get_schedule` + `AppointmentService::overlapConflict` (read side only). *Impact:* reception's constant question; *risk:* read-only if we expose *availability* before *booking*; *demo:* natural-language "is Dr X free at 3?".
4. **Pending payments & patient balance** — `patient_balance` + `WalletService::summary`. *Impact:* directly ties AI to revenue collection; *risk:* read-only; *demo:* "who owes us money" is a money-flavoured wow.
5. **Clinical / patient search** — `find_patient` + `IdentityResolver`. *Impact:* the entry point to everything else; *risk:* read-only; *demo:* fuzzy "find Runali" across name/phone/ID.

**Explicit recommendation:** do **not** promote the existing *write* tools (`book_appointment`, `add_patient_note`, `update_patient_contact`) to production until §5.2/§5.4 are addressed — `book_appointment` currently reimplements a weaker booking rule and no tool checks permissions. Ship the five reads now; make the first *write* be a re-pointed `book_appointment` that calls `AppointmentService::create()`.

---

## 8. Engineering effort classification (no timelines)

| Recommendation | Effort | Why |
|---|---|---|
| Ship the 5 read capabilities (§7) | **Small** | Four exist; work is verification + permission check + wording. |
| Add permission (`User::canAccess`) + tenant scoping to the tool-runner (§5.4) | **Medium** | One well-placed wrapper in `AssistantService::runTool()`; needs per-tool module mapping. |
| Re-point `book_appointment` at `AppointmentService::create()` (§5.2) | **Small** | Service already exists and is clean; delete the ad-hoc logic. |
| Extract billing invoice-creation into a shared service (web+API+AI) (§5.1/§5.2) | **Large** | Logic is deep, duplicated, transaction-heavy, money-critical. |
| Thread explicit `User $actor` through the few `Auth::`-reaching services (§5.7) | **Small–Medium** | Mechanical, but touches billing/recall. |
| Wire voice→clinical-note into consultation screen (§6) | **Medium** | Pipeline exists; needs UI + a consultation-controller call + review. |
| Expose `CommunicationEngine` send as a guarded AI tool (§9) | **Medium** | Built and guard-aware, but sending is high-consequence; needs consent-flag decisions. |
| Make `BranchScope` load-bearing + add `branch_id` to `Invoice` (§5.3) | **Very Large** | The multi-tenant hardening already flagged as the clinic-#2 blocker; prerequisite for multi-clinic AI. |
| Add a hand-written intent/deterministic-shortcut layer for top commands | **Medium** | Optional; mirrors the existing huddle shortcut for reliability/speed. |

---

## 9. Built-but-flag-hidden capabilities AI could leverage

Verified in `config/features.php` (defaults) and the services above. These are "dark engines" — real code, switched off — that AI could surface or ride on:

- **`CommunicationEngine`** — a guard-checked WhatsApp send gateway, **built and (per its docblock) not yet wired into any production call site.** An ideal, already-consent-aware home for an AI "send this reminder" tool. (Guard flags `guard.consent_required` / `full_8factor` default OFF — decide before exposing.)
- **`TodayActionsEngine` projection** — `today.projection` OFF. The engine runs live today; the projection is a faster read the AI huddle could use once on.
- **`InsightsEngine` signals** — `insights.signals` OFF. Risk / LTV / health signals are computed but not the app's live source. AI could expose "at-risk patients" from them.
- **`SearchIndexProjector`** — `search.index` OFF. A fuzzy search index exists but nothing reads it in production yet; AI search could, but results may lag live data until cutover.
- **`WorkflowEngine`** — `workflow.engine` OFF. Linear treatment/onboarding workflows the AI could start/advance.
- **`AutomationEngine`** — `automation.engine` OFF. Recall/reminder timing owned by the new engine in shadow; AI could preview/trigger.
- **Case Acceptance Engine** — `case_acceptance.enabled` OFF. Patient-microsite journey builder; AI could draft/assemble a case journey.
- **Blog Marketing Hub** — `blog.hub` OFF. Block editor + publishing; AI content-drafting could plug in here.

**Nuance for the CTO:** reading from a flag-off projection (Insights/Search/Today) can return data that lags the live domain until the cutover flag is flipped. AI tools should read the *live* engine method (e.g. `TodayActionsEngine::generate()`) rather than the dormant projection until those flags are on.

---

## 10. If I became CTO today — what would I build next?

**One thing: a hardened AI Tool Gateway that forces every AI action through the canonical services with the app's own guards — and use building it as the lever that finishes the service-extraction the whole app needs.**

Concretely, the single next build is to make `AssistantService::runTool()` the one enforcement point that, for every tool call: (1) checks `User::canAccess(module, action)` — closing §5.4; (2) applies branch/tenant scope — starting §5.3; (3) routes writes through the canonical service/engine, never ad-hoc model writes — closing §5.2; (4) keeps the existing confirm-card + `AiActionLog` audit. Then re-point the three existing write tools at their services (`AppointmentService`, etc.).

Why this over anything flashier:

- **It makes AI-first *safe* before it makes it *bigger*.** We already shipped an AI tool that can double-book across a blocked slot with no permission check. Widening the tool catalogue before fixing the gateway multiplies that risk.
- **It pays for itself twice.** Forcing AI tools to call shared services creates the pressure (and the reference implementation) to finally extract billing/booking logic out of controllers — fixing the web/API duplication that has already caused defects, for humans *and* AI at once. Reuse over rewrite, exactly as directed.
- **It is the smallest change with the largest blast radius.** One wrapper + three re-points turns a promising-but-unsafe demo into a trustworthy platform, and every future tool inherits the guarantees for free.

What I would **not** do next: invent new engines, redesign the architecture, or chase wake-word/voice polish. The engines exist; the AI loop exists; the switches are off and a couple of the wires are crossed. The highest-leverage work is making the existing AI layer as disciplined as the rest of the app — then turning it up.

---

### Evidence & honesty notes
- Every class, method, and line reference above was read directly this session; the four highest-stakes claims (Tulip is built & page-wired, `BookAppointmentTool`'s weaker check, tools never call `canAccess`, `Invoice` lacks `branch_id`) were independently re-verified against source.
- **Corrections to prior internal notes:** "AI Copilot Tulip — unstarted" is false (it is built and wired); the "internal MCP layer" note has **no corresponding code** — could not find any MCP implementation under `app/`.
- **Could not verify from here:** whether the `ai_*` migrations are applied on the live DB (no DB access), and whether `ASSISTANT_ENABLED` is true in production. Confirm both before relying on Tulip being live.
- No code was written or changed, per directive.
