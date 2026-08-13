# Case Acceptance Engine — Implementation Analysis (Phases 1–3)

**Status:** Planning only — no code. Companion to the FROZEN architecture `docs/plan-case-acceptance-engine.md`.
**Date:** 2026-07-15
**Verification:** Every claim below was checked against the live codebase (four parallel code sweeps, cross-checked against real files). Discrepancies vs. the frozen doc are called out in Phase 2.

---

# Phase 1 — Implementation Analysis

## 1.1 What gets REPLACED (Smart Presentation module — retire behind flag)

The old module is the *presentation experience* only. Its service plumbing is reused (see 1.2). Nothing is deleted in V1 — it coexists behind a flag and is retired only after validation.

| Concern | File(s) | Fate |
|---|---|---|
| Builder UI | `resources/views/presentations/builder.blade.php` | Superseded by new Case Journey builder |
| Public patient page | `resources/views/presentations/public/show.blade.php` | Superseded by new patient microsite |
| Public layout | `resources/views/layouts/public-presentation.blade.php` | Reuse or clone for `/p/{token}` |
| Index / links / settings / tabs | `resources/views/presentations/{index,links,settings}.blade.php`, `partials/tabs.blade.php`, `public/expired.blade.php` | Keep during coexistence; retire with the module |
| Staff controller | `app/Http/Controllers/PresentationController.php` (methods: `index, createFromPlan, builder, update, generateSummary, finalize, destroy, send, resend, markDeclined, markFollowUp, linksIndex, linkRevoke, linkRegenerate, settingsShow, settingsUpdate`) | Parallel `CaseJourneyController` created; old kept until retirement |
| Public controller | `app/Http/Controllers/PublicPresentationController.php` (methods: `show, accept, decline, requestCallback`) | New `PublicCaseController` **mirrors** this exactly |
| Entry-point button | `resources/views/patients/partials/treatment-plan-tab.blade.php` (lines ~1223–1229, "Create Smart Presentation") | Add a **second, flag-gated** button "Open Case Journey"; do not remove the old one in V1 |

## 1.2 Services / integrations to REUSE (do NOT rebuild)

All verified present with the signatures the frozen doc assumed (minor corrections noted in Phase 2):

| Capability | Class / file | Signature we call |
|---|---|---|
| Acceptance | `app/Services/TreatmentPlan/TreatmentPlanAcceptanceService.php` | `accept(TreatmentPlan $plan, ?User $actor=null, string $via='clinic', ?int $createdBy=null): TreatmentPlan` — transactional, sets `accepted_at`+`status=ongoing`, logs, syncs opportunity. Call with `via: 'case_acceptance'`. Also has `revert(...)`. |
| Opportunity sync | `app/Services/TreatmentPlan/TreatmentPlanOpportunitySync.php` | `syncStage(TreatmentPlan $plan, string $status, array $opts=[]): TreatmentOpportunity` — idempotent, one row per plan. **Caller passes the target status verbatim** (`'quoted'` on open, `'completed'`/`'declined'` already handled by accept/decline). |
| Activity ledger | `app/Services/Relationship/ActivityEngine.php` | `log(Model $subject, string $event, ?Model $actor=null, array $metadata=[], ?int $relationshipId=null, ?string $description=null): ?Activity` — **instance** method via `app(ActivityEngine::class)`. Fires `RulesEngine`, recalcs score, publishes `ActivityRecorded`. Never throws. |
| Rules engine | `app/Services/Relationship/RulesEngine.php` | Invoked automatically by `ActivityEngine::log()`; reads `config/relationship_rules.php`. We add rule entries, no code. |
| Domain event | `app/Domain/Events/Relationship/ActivityRecorded.php` | Published automatically; no direct use needed. |
| Deterministic narrative | `app/Services/Presentations/PresentationNarrativeService.php` → `build(Presentation $p): array` | Reusable substrate; note it takes a `Presentation`, not a journey (see Phase 2 §2.4). |
| Tooth phrasing | `app/Services/Presentations/ToothLocationDescriber.php` → `describe / describeMany / phraseFor` | Reuse for `{{tooth_name}}` token resolution. |
| AI summary (optional) | `app/Services/Presentations/PresentationSummaryService.php` (Ollama) | Optional, AI-off default. |
| Feature flags | `config/features.php` + `app/Support/Features/FeatureFlagService.php` + `Feature` facade | Add flag `case_acceptance.enabled` (default false). Gate via `Feature::enabled('case_acceptance.enabled')`. |
| Permission gate | `app/Http/Middleware/CheckModulePermission.php` (`module:` alias) | Gate staff routes with `module:...` as other modules do. |

## 1.3 Models / tables that stay UNTOUCHED

`TreatmentPlan`, `TreatmentPlanItem` (has `material_variants` JSON), `TreatmentPlanItemTooth`, `Treatment` (has `default_price`, `min_price`, `max_price`, `gst_pct`, `unit_basis`, `suggested_treatment_options` JSON), `TreatmentOpportunity`, `Consultation` (`chart_data` = `EncryptedArray`, rows `{tooth, condition, custom, surfaces}`), `Presentation` + `PresentationAccessToken` + `PresentationSnapshot` + `PresentationMediaItem`, `Activity`, relationship/PRM models. **No refactor of any of these in V1** (backward-compatibility rule).

## 1.4 What must be NEWLY created

**Prerequisite (Treatment Module — build FIRST, not engine work):**
- Table `treatment_options` + `TreatmentOption` model.
- Read-only API `GET /api/treatment-pricing?treatment_id=&group=`.
- *Confirmed genuinely missing:* no `treatment_options` table, no `TreatmentOption` model, no pricing route anywhere (`diagnosis_treatment_options` is an unrelated ranking table).

**Engine — new migrations (13):** `kb_topics`, `kb_blocks`, `kb_block_media`, `kb_topic_relations`, `media_assets`, `decision_trees`, `decision_tree_nodes`, `patient_journeys`, `journey_curations`, `case_selections`, `journey_sent_snapshots`, `case_consent_snapshots` (+ the prerequisite `treatment_options`).

**Engine — new models:** one per table above, with relationships. KB models enforce a "no price/brand/`clinic_id`/PHI" guard at the model layer.

**Engine — new services:** `JourneyAssembler`, `CasePricingClient`, `CaseSelectionService`, `JourneySnapshotService`, (optional) `KnowledgeBankRetriever`. Accept delegates to the existing `TreatmentPlanAcceptanceService`.

**Engine — new controllers:** staff `CaseJourneyController` (curate/send from the treatment-plan tab); public `PublicCaseController` (mirrors `PublicPresentationController`).

**Engine — new routes:** authenticated `case-journeys/*` (inside the outer `auth` group, `module:`-gated, static-before-wildcard); public `->withoutMiddleware('auth')->prefix('p')->name('case.public.')`.

**Engine — new views:** interactive builder (Blade + Alpine) and patient microsite, both consuming the block DTO.

**Engine — new seeders:** `database/seeders/KnowledgeBank/*` and one `MissingToothTreeSeeder` (Missing Tooth → Implant/Bridge/Denture → crown material → estimate).

**Engine — config:** flag in `config/features.php`; follow-up rules appended to `config/relationship_rules.php`.

## 1.5 Dependency graph (safest implementation order)

```
                    ┌─────────────────────────────┐
                    │ M0  Feature flag scaffold    │  (no risk; gate everything)
                    └──────────────┬──────────────┘
                                   │
        ┌──────────────────────────┴───────────────────────┐
        ▼                                                   │
┌──────────────────────┐                                    │
│ M1  treatment_options │  PREREQUISITE — Treatment Module   │
│     + pricing API     │  (independent of all engine work)  │
└───────────┬──────────┘                                    │
            │                                                ▼
            │                              ┌────────────────────────────────┐
            │                              │ M2  DB foundation (13 tables +  │
            │                              │     models + relationships)     │
            │                              └───────────────┬────────────────┘
            │                    ┌────────────────┬────────┴───────┬─────────────────┐
            │                    ▼                ▼                ▼                 ▼
            │            ┌──────────────┐  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐
            │            │ M3 Knowledge │  │ M4 Media     │ │ M5 Decision  │ │ (journey     │
            │            │    Bank+seed │  │    Library   │ │    Trees+seed│ │  tables ready)│
            │            └──────┬───────┘  └──────┬───────┘ └──────┬───────┘ └──────────────┘
            │                   └─────────────┬───┴────────────────┘
            └───────────────┬────────────────┘
                            ▼
                ┌──────────────────────────┐
                │ M6  JourneyAssembler →    │  needs M1 pricing + M3/M4/M5 content
                │     block DTO (+CasePricingClient)
                └─────────────┬────────────┘
                              ▼
                ┌──────────────────────────┐
                │ M7  Renderer (staff       │  block DTO → Blade/Alpine
                │     builder + patient page)│
                └─────────────┬────────────┘
                              ▼
                ┌──────────────────────────┐
                │ M8  Patient Journey flow  │  curate→send→view→select
                │     (tokens, snapshots,   │  CaseSelectionService,
                │      selections)          │  JourneySnapshotService (sent snapshot)
                └─────────────┬────────────┘
                              ▼
                ┌──────────────────────────┐
                │ M9  Acceptance integration│  delegate to TreatmentPlanAcceptanceService
                │     + consent snapshot    │  + opportunity on open/accept/decline
                └─────────────┬────────────┘
                              ▼
                ┌──────────────────────────┐
                │ M10 Analytics + follow-up │  activities ledger events +
                │     rules (no engine code)│  config/relationship_rules.php entries
                └──────────────────────────┘
```

Critical path: **M0 → M1 → M2 → (M3‖M4‖M5) → M6 → M7 → M8 → M9 → M10.** M1 and M2 can start in parallel after M0. M3/M4/M5 are parallelizable. Media completeness gates only the *content go-live*, not the engine build (build against placeholders).

---

# Phase 2 — Frozen architecture vs. current codebase (conflicts)

Six items surfaced. None require redesign; each has a minimal adjustment. Flagged here for your sign-off before code, per the freeze rule.

### 2.1 `case_microsites` / `case_microsite_options` do not exist — they are NEW tables, not renames
**Conflict.** Frozen §5.4 calls `patient_journeys` "the former `case_microsites`" and `journey_curations` "the kept `case_microsite_options` table." Neither table, model, nor migration exists anywhere in the codebase (the word "microsite" appears only in code comments describing the public URL).
**Why:** the earlier Smart Presentation build used `presentations` + `presentation_access_tokens` + `presentation_snapshots`, never a microsite entity.
**Smallest adjustment:** treat `patient_journeys` and `journey_curations` as **brand-new tables** (create fresh migrations). No rename/alter, no data migration. Purely a documentation correction — the schema in §5.4 is unaffected.

### 2.2 Public token: reuse `PresentationAccessToken` vs. `patient_journeys.token`
**Conflict.** §3 says "reuse `PresentationAccessToken` in V1"; §5.4 gives `patient_journeys` its own `token` column. `PresentationAccessToken` has a hard `presentation_id` FK (`belongsTo Presentation`) — reusing it for a journey would require either a `Presentation` row per journey or making that FK nullable + adding `patient_journey_id` (a refactor of an untouched table — violates the backward-compat rule).
**Why:** the token model was built tightly around `Presentation`.
**Smallest adjustment:** use the **native `patient_journeys.token`** column and **mirror the *pattern*** of `PublicPresentationController` (manual `where('token', …)->first()` + an `isValid()`/`recordView()` equivalent on `PatientJourney`), rather than reusing the `PresentationAccessToken` *table*. Same shape, zero changes to existing tables. (Reuse the *pattern*, not the row.)

### 2.3 Feature-flag key + coexistence mechanism
**Not a conflict — a concretization.** A real flag system exists (`config/features.php` → `FeatureFlagService` → `Feature::enabled()`), with DB per-clinic overrides. Flags `journey.authoritative` and `relationship.opportunity_journey_column` already exist, so avoid a bare `journey.*` name.
**Adjustment:** register `case_acceptance.enabled` (default `false`) in `config/features.php`. Gate: the new treatment-plan-tab button, all `case-journeys/*` routes, and the `/p/{token}` public route. Old Smart Presentation stays fully functional; flip the flag off to restore instantly. The old module's `module:presentations` permission gate is untouched.

### 2.4 `PresentationNarrativeService::build()` expects a `Presentation`, not a journey
**Minor conflict.** §3 lists it as reusable "deterministic substrate," but its signature is `build(Presentation $p)` and it reads presentation-specific fields.
**Adjustment:** reuse `ToothLocationDescriber` directly (clean, journey-agnostic) for token resolution. Treat `PresentationNarrativeService` as *reference logic* to port into `JourneyAssembler`, not a direct call — do not couple the engine to the `Presentation` model. No change to the existing service.

### 2.5 `syncStage()` does not map statuses internally
**Clarification, not a conflict.** The `presented→quoted / accepted→completed / declined→declined` mapping in §6/§8 is a docblock convention; `syncStage()` writes the passed status verbatim.
**Adjustment:** on `case.opened`, the engine calls `syncStage($plan, 'quoted')` explicitly. Accept/decline already flow through `TreatmentPlanAcceptanceService` / the decline path, so no new mapping code.

### 2.6 `via: 'case_acceptance'` is a new value for an existing free-form arg
**No conflict.** `accept()` takes `string $via` (existing values `'clinic'|'smart_presentation'|'mobile'`, no enum constraint). Passing `'case_acceptance'` is safe. Confirm no downstream `switch($via)` rejects unknown values before M9 (spot-check during that milestone).

**Naming note (not a conflict):** the frozen doc's `treatment_plan_items.variants` is actually the `material_variants` JSON column. `treatment_options` should align its `group`/`name`/`price` shape with `material_variants` (`[{label, price, selected}]`) so the two can converge later.

---

# Phase 3 — Milestone roadmap

Each milestone is independently testable, compiles on its own, and preserves all existing functionality. Rollback is always non-destructive (no `migrate:fresh`/rollback/wipe — new tables are additive; the flag hides new UI). You run all Artisan commands manually.

### Milestone 0 — Feature-flag scaffold
- **Create:** flag `case_acceptance.enabled` in `config/features.php` (default false).
- **Modify:** none yet.
- **DB:** none.
- **Depends on:** nothing.
- **Test:** `Feature::enabled('case_acceptance.enabled')` returns false in tinker; app unaffected.
- **Rollback:** remove the flag line.

### Milestone 1 — Treatment pricing prerequisite
- **Create:** migration `create_treatment_options_table` (`treatment_id`, `group`, `name`, `price`, `is_active`, `is_default`, `sort_order`); `TreatmentOption` model + `Treatment::options()` relation; `GET /api/treatment-pricing` route + thin controller returning `{treatment_id, group, options:[{id,name,price,is_default}]}`.
- **Modify:** `Treatment` model (add `options()` relation only).
- **DB:** `php artisan make:migration create_treatment_options_table` → `php artisan migrate`.
- **Depends on:** M0 (optional gate).
- **Test:** seed a few options; hit `/api/treatment-pricing?treatment_id=X&group=implant_system`; assert JSON + no price caching. Confirm existing treatment/plan screens unchanged.
- **Rollback:** drop the new route; the additive table can stay dormant.

### Milestone 2 — Database foundation
- **Create:** the 12 engine migrations (`kb_topics`, `kb_blocks`, `kb_block_media`, `kb_topic_relations`, `media_assets`, `decision_trees`, `decision_tree_nodes`, `patient_journeys`, `journey_curations`, `case_selections`, `journey_sent_snapshots`, `case_consent_snapshots`) exactly per frozen §5; all models + relationships; KB model-layer guard rejecting price/brand/`clinic_id`/PHI fields. `patient_journeys.token` is the native public token (Phase 2 §2.2).
- **Modify:** none.
- **DB:** the 12 `make:migration` commands (frozen §14) → `php artisan migrate` → `composer dump-autoload`.
- **Depends on:** M0. (Independent of M1.)
- **Test:** `migrate` succeeds; `Model::factory`/tinker create+relate rows; assert KB guard throws on a `price` attribute.
- **Rollback:** additive tables; leave unused. Flag keeps them invisible.

### Milestone 3 — Knowledge Bank
- **Create:** `database/seeders/KnowledgeBank/*` (Missing Tooth condition + Implant/Bridge/Denture procedures + crown materials, `standard` depth, `en`, whitelisted `{{tokens}}`); KB repository/read methods used by the assembler.
- **Modify:** none.
- **DB:** `php artisan db:seed --class=KnowledgeBankSeeder`.
- **Depends on:** M2.
- **Test:** seeded topics/blocks query correctly; blocks contain only whitelisted tokens; no price/PHI fields present.
- **Rollback:** truncate KB tables + re-run without seeding.

### Milestone 4 — Media Library
- **Create:** `media_assets` handling (one row = one file); global-stock placeholders; `kb_block_media` links; `scope`/`consent_ref` rules (clinic PHI requires `consent_ref`).
- **Modify:** none.
- **DB:** seed placeholder assets.
- **Depends on:** M2 (parallel with M3/M5).
- **Test:** attach a placeholder to a KB block; assert a `clinic`-scope asset without `consent_ref` is rejected.
- **Rollback:** truncate `media_assets`/`kb_block_media`.

### Milestone 5 — Decision Trees
- **Create:** `MissingToothTreeSeeder` (entry `missing_tooth` → option nodes Implant/Bridge/Denture → material nodes → summary; nodes store pointers only); tree read methods.
- **Modify:** none.
- **DB:** `php artisan db:seed --class=MissingToothTreeSeeder`.
- **Depends on:** M2 (parallel with M3/M4).
- **Test:** walk the tree in tinker; assert no prices/prose on nodes, only `kb_topic_id` / `treatment_option_group` pointers.
- **Rollback:** truncate tree tables + re-seed.

### Milestone 6 — Journey Assembler
- **Create:** `JourneyAssembler` (walk nodes → filter by `journey_curations` → hydrate KB+media → hydrate priced options via `CasePricingClient` → inject tokens → **block DTO**); `CasePricingClient` (only path to money; calls M1 API; no caching).
- **Modify:** none.
- **DB:** none.
- **Depends on:** M1, M3, M4, M5.
- **Test:** assemble a journey for a test plan; assert the DTO is typed JSON (never HTML), tokens resolved, prices live from the API, curation filtering applied.
- **Rollback:** service is unreferenced by UI until M7; no runtime impact.

### Milestone 7 — Renderer
- **Create:** staff builder view + patient microsite view (Blade + Alpine, progress indicator, small animated sections) consuming the block DTO; clone/reuse `layouts/public-presentation.blade.php`.
- **Modify:** none (views only).
- **DB:** none.
- **Depends on:** M6.
- **Test:** render the assembled DTO; verify side-by-side option comparison, cost-visibility frame text, both delivery modes. Old presentation pages still render.
- **Rollback:** views reachable only via flag-gated routes added in M8.

### Milestone 8 — Patient Journey flow
- **Create:** `CaseJourneyController` (curate + send from the treatment-plan tab); `PublicCaseController` (mirrors `PublicPresentationController`: `show/accept/decline/requestCallback`); `CaseSelectionService` (records `case_selections`, recomputes running estimate); `JourneySnapshotService` (writes `journey_sent_snapshots` at send; supersede-on-edit); routes (`case-journeys/*` authenticated + `->withoutMiddleware('auth')->prefix('p')`); flag-gated "Open Case Journey" button in `treatment-plan-tab.blade.php`.
- **Modify:** `resources/views/patients/partials/treatment-plan-tab.blade.php` (add one gated button; do not touch the existing one).
- **DB:** none (tables from M2).
- **Depends on:** M7.
- **Test:** curate → send (sent snapshot pinned, token issued) → open `/p/{token}` → select options → estimate updates → edit-after-send creates a superseding revision (old set read-only). Old flow with flag off is untouched.
- **Rollback:** flip `case_acceptance.enabled` off — button and routes disappear; snapshots remain dormant.

### Milestone 9 — Acceptance integration
- **Create:** accept path in `PublicCaseController` delegating to `TreatmentPlanAcceptanceService::accept(..., via: 'case_acceptance')`; `case_consent_snapshots` write at accept (immutable, IP/UA); opportunity `syncStage($plan,'quoted')` on `case.opened`.
- **Modify:** none to existing services.
- **DB:** none.
- **Depends on:** M8.
- **Test:** accept a journey → plan `status=ongoing`, `accepted_at` set, opportunity `completed`, consent snapshot pinned; decline → opportunity `declined`. Verify `via='case_acceptance'` isn't rejected downstream. Confirm the old Smart Presentation accept path still works.
- **Rollback:** flag off; existing acceptance logic is shared and unchanged.

### Milestone 10 — Analytics + follow-up
- **Create:** `ActivityEngine::log()` calls for `case.*` events (opened, section_viewed, video_watched, options_compared, material_selected, estimate_viewed, callback_requested, appointment_booked, accepted, declined, more_time_requested); follow-up rule entries in `config/relationship_rules.php` (using the real keys `trigger`/`action`/`action_config`, per Phase 2).
- **Modify:** `config/relationship_rules.php` (append rules only).
- **DB:** none.
- **Depends on:** M9.
- **Test:** trigger events → assert `activities` rows + `RulesEngine` fires follow-up tasks; assert no duplicate interactions table. KPI = ethical acceptance rate, tracked off the ledger.
- **Rollback:** remove the appended rules; events are harmless no-ops if the flag is off.

---

## Manual Artisan commands (run yourself — none executed here)
```
# Milestone 1
php artisan make:migration create_treatment_options_table
php artisan migrate

# Milestone 2
php artisan make:migration create_media_assets_table
php artisan make:migration create_kb_topics_table
php artisan make:migration create_kb_blocks_table
php artisan make:migration create_kb_block_media_table
php artisan make:migration create_kb_topic_relations_table
php artisan make:migration create_decision_trees_table
php artisan make:migration create_decision_tree_nodes_table
php artisan make:migration create_patient_journeys_table
php artisan make:migration create_journey_curations_table
php artisan make:migration create_case_selections_table
php artisan make:migration create_journey_sent_snapshots_table
php artisan make:migration create_case_consent_snapshots_table
php artisan migrate
composer dump-autoload

# Milestones 3 & 5
php artisan db:seed --class=KnowledgeBankSeeder
php artisan db:seed --class=MissingToothTreeSeeder
```
No destructive commands, ever.

---

## Recommended stopping point for sign-off
Approve **Phase 2 items 2.1 and 2.2** (the `case_microsites` rename correction and the native-token decision) before any code — they are the only two places where the frozen doc and the code disagree materially. Everything else is verified-consistent. On approval, start at **Milestone 0 → Milestone 1**.
