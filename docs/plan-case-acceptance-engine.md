# Case Acceptance Engine — Architecture (FROZEN)

**Status:** 🧊 FROZEN 2026-07-15. Ready for implementation planning. Replaces the Smart Treatment Presentation modal.
**Owner:** Sumit
**Supersedes / evolves:** `docs/plan-smart-treatment-presentation.md`

> Freeze rule: from here, effort goes to implementation and **validating the workflow with real patients**, not expanding the architecture. Anything new lands in §13 "Deliberately deferred," not in V1.

---

## 1. What we are building

An interactive, guided patient consultation that helps a patient understand their condition, compare options, and make an informed decision — improving **ethical** treatment acceptance. It is an **orchestration layer** that assembles independently-owned layers into one personalized journey. It owns none of their data.

Not: a PDF generator, a static presentation, a second pricing module, or a CMS.

**KPI:** ethical acceptance rate — not page views or time-on-site.

**Guiding lens (applied throughout):** future-proof the *expensive* layer (schema, inter-layer contracts, anything content is authored against); keep the *cheap* layer concrete and deletable (services, controllers, UI). Abstract code only when a second real caller exists.

---

## 2. Ownership boundaries (permanent)

| Layer | Owns | Source of truth | Never contains |
|---|---|---|---|
| **Knowledge Bank** | Education | Dentfluence (global IP) | Prices, discounts, clinic materials, implant brands, patient data, `clinic_id` |
| **Media Library** | Assets | *Split:* global stock = Dentfluence; clinic/patient captures = clinic (PHI, consent-gated) | — |
| **Treatment Module** | Commerce | The clinic (existing module) | Educational content, clinical decisions |
| **Doctor Layer** | Clinical decision + instance curation | Treating dentist (per patient) | Pricing logic, educational authoring, forking the global tree |
| **Patient Layer** | Selections + final decision | The patient (per case) | Anything authoritative — records choices only |

Template vs instance: Dentfluence owns the **decision-tree templates** and KB; the doctor owns only **instance curation** (which nodes are visible/recommended for this patient) and the diagnosis. Clinics never fork the global tree.

Media split is not cosmetic — before/after photos of real patients are PHI under DPDP and must never enter the global Knowledge Bank.

**Data flow**

```
Knowledge Bank ──┐
Media Library  ──┤
Doctor Plan    ──┼──►  Case Acceptance Engine  ──►  Patient Journey (block DTO)
Treatment Mod. ──┘         (assemble + pin)              │
                                                         ▼
                                        Accept ──► existing acceptance + Opportunity + PRM
```

Engine reads from KB + Media + Doctor + Treatment Module; writes only: patient interaction events (to the existing ledger), the selection set, immutable snapshots, and a delegation to the existing accept service.

---

## 3. Reuse — do not rebuild (verified in code 2026-07-15)

- **Public token pattern** — `routes/web.php` `Route::withoutMiddleware('auth')->prefix('present')`; `PresentationAccessToken::isValid()` / `recordView()`. `/p/{token}` follows this shape (reuse `PresentationAccessToken` in V1).
- **Acceptance** — `TreatmentPlanAcceptanceService::accept($plan, $actor, via, $createdBy)` (transactional; sets `accepted_at`, `status=ongoing`; logs; syncs opportunity). Engine calls with `via: 'case_acceptance'`. No new accept logic.
- **Opportunity** — single writer `TreatmentPlanOpportunitySync::syncStage($plan, $status)`, idempotent, one `TreatmentOpportunity` per plan. presented→`quoted`, accepted→`completed`, declined→`declined`.
- **Activity ledger** — `ActivityEngine::log(...)` → `activities`, fires `RulesEngine`, score recalc, publishes `ActivityRecorded`. `presentation.viewed` already fires. Viewed→opportunity→follow-up loop already exists.
- **Deterministic narrative** — `PresentationNarrativeService::build()` + `ToothLocationDescriber`. Reuse as deterministic substrate.
- **Clinical data** — `treatment_plans` (+ `treatment_plan_items`, `treatment_plan_item_teeth`); `Consultation.chart_data` (encrypted JSON `{tooth, condition, custom, surfaces}`); `TreatmentPlan.doctor_id`, `patient()`, `items()`, `opportunity()`.

Replaces `presentations/builder.blade.php` + `presentations/public/show.blade.php`. Retains all service plumbing. V1 rollout: coexist behind a flag until validated on real patients.

---

## 4. Prerequisites (not engine work)

### 4.1 Treatment Module must price components — BUILD FIRST
Today `treatments` is flat (`name` + `default_price`); `LabVendorService` likewise. No structured implant-system / crown-material / add-on options. The live cost builder needs granular priced options, owned by the **Treatment Module**.

- New (Treatment Module): **`treatment_options`** — `treatment_id`, `group` (`implant_system` | `crown_material` | `addon` | …), `name`, `price`, `is_active`, `is_default`, `sort_order`. Align with existing `treatment_plan_items.variants` JSON.
- Read API: `GET /api/treatment-pricing?treatment_id=&group=`. The engine consumes only this; it never reads price tables directly and never caches prices.

### 4.2 Knowledge Bank distribution — deferred
"Dentfluence updates content → all clinics benefit" implies central distribution; current reality is one Docker instance per clinic. **V1:** KB lives in-DB, seeded from version-controlled content in the repo, updated via deploy. Schema carries `version` + `content_uuid` so a future central content-sync API drops in without migration. Distribution service itself is deferred (§13).

---

## 5. Schema (database-first, journey-shaped spine)

The spine is named journey-generic so pre-op/post-op/recall/maintenance reuse it later; **only Case Acceptance is implemented** now. No journey-orchestration engine is built — one concrete journey type.

### 5.1 Knowledge Bank — global, versioned (Dentfluence)

**`kb_topics`** — `id`, `content_uuid`, `slug`, `type` enum(`condition`,`procedure`,`material`,`addon`), `title`, `version` (semver), `status` enum(`draft`,`published`,`archived`), `published_at`, timestamps.

**`kb_blocks`** — reusable content atoms. `id`, `kb_topic_id`, `block_type` enum(`intro`,`animation`,`image`,`video`,`advantage`,`disadvantage`,`risk`,`contraindication`,`healing_timeline`,`maintenance`,`faq`,`before_after`,`reference`,`comparison`), `title`, `body` (rich/structured; may contain **whitelisted `{{tokens}}`** — see §6), `depth` enum(`simple`,`standard`,`detailed`,`clinical`) default `standard` **(reserved — author only `standard` in V1)**, `locale` default `en` (`hi`,`mr` later), `sort_order`, `version`, timestamps. Media by reference (§5.2), not inline.

**`kb_block_media`** — pivot. `kb_block_id`, `media_asset_id`, `role` (`primary`,`inline`,`thumbnail`), `sort_order`.

**`kb_topic_relations`** — typed topic graph for future AI retrieval/recommendation (NOT navigation). `from_topic_id`, `to_topic_id`, `relation_type` enum(`related`,`prerequisite`,`followup`), `weight` (nullable). Isolated from the assembly path (assembly walks the decision tree, not this graph). Populate lazily as content is authored; no traversal engine in V1.

Model-layer rule: KB tables reject any price/discount/brand field, `clinic_id`, or patient data.

### 5.2 Media Library — scoped

**`media_assets`** — `id`, `scope` enum(`global`,`clinic`), `media_type` enum(`image`,`video`,`svg`,`lottie`,`model_3d`), `path`, `mime`, `locale` (nullable), `variant_of` (nullable self-FK, for future resolutions), `consent_ref` (nullable — required for `clinic` PHI captures), `uploaded_by`, timestamps. V1 keeps handling dumb: one row = one file. No transcoding/resolution ladder.

### 5.3 Decision Trees — reusable templates (Dentfluence)

**`decision_trees`** — `id`, `slug`, `title`, `entry_condition` (maps to charted condition / `kb_topic`), `version`, `status`, timestamps.

**`decision_tree_nodes`** — `id`, `decision_tree_id`, `parent_node_id` (nullable), `node_type` enum(`consequence`,`option`,`material`,`addon`,`summary`), `kb_topic_id` (nullable — education shown here), `treatment_option_group` (nullable — which Treatment Module group is chosen here), `conditions` JSON **(reserved — evaluated by a trivial equality matcher, NOT a rule engine)**, `label`, `sort_order`, `is_terminal`. Nodes store pointers only — never prices or prose.

### 5.4 Journey instance + curation (per patient)

**`patient_journeys`** *(the former `case_microsites`; journey-shaped spine)* — `id`, `journey_type` enum(`case_acceptance`, …future) default `case_acceptance`, `treatment_plan_id`, `patient_id`, `relationship_id`, `decision_tree_id`, `token`, `delivery_mode` enum(`chairside`,`take_home`,`both`), `cost_visibility` enum(`full`,`starting_from`,`hidden_until_booking`), `phase` enum(`education`,`accepted`,`pre_op`,`post_op`,`recall`,`maintenance`) default `education` **(only education/accepted used in V1)**, `status` enum(`draft`,`sent`,`viewed`,`accepted`,`declined`,`follow_up`), `pinned_kb_version`, `pinned_tree_version`, `superseded_by` (nullable self-FK), `sent_at`, `expires_at`, timestamps.

**`journey_curations`** *(the kept `case_microsite_options` table)* — per-node doctor curation, retained as a relational table for analytics/reporting. `id`, `patient_journey_id`, `decision_tree_node_id`, `visible` bool, `is_recommended` bool, `sort_order`. Rows become **immutable once the journey is sent** (part of the pinned set).

### 5.5 Patient outputs + immutable snapshots

**`case_selections`** — the mutable "cart" (until accept). `id`, `patient_journey_id`, `decision_tree_node_id`, `treatment_option_id` (Treatment Module ref, nullable), `selected_at`.

**`journey_sent_snapshots`** — **immutable, pinned at SEND.** The fully-assembled block DTO + resolved prices + versions + curation — the exact thing the patient sees. `id`, `patient_journey_id`, `snapshot` (JSON), `estimate_total`, `pinned_at`.

**`case_consent_snapshots`** — **immutable, pinned at ACCEPT.** `id`, `patient_journey_id`, `snapshot` (JSON: shown + chosen + prices at accept), `estimate_total`, `taken_at`, `ip`, `user_agent`.

Interactions get **no new table** — they ride the existing `activities` ledger (§8).

---

## 6. Version pinning + immutability (V1, hard rules)

Two immutable snapshots at two moments:
1. **Sent snapshot** (`journey_sent_snapshots`) — pinned at send; the source of truth the patient sees. Live KB/price changes never touch it.
2. **Consent snapshot** (`case_consent_snapshots`) — pinned at accept; what the patient confirmed.

**Edit-after-send = supersede, not mutate.** Any change to a sent journey (price, added tooth, re-curation) produces a *new* pinned revision (new send / new token); the prior journey is set read-only/expired and linked via `superseded_by`. No in-place edits to a sent journey, ever.

`pinned_kb_version` / `pinned_tree_version` are stored for analytics/debug; the sent snapshot is the actual guarantee.

---

## 7. Renderer — normalized block DTO (not HTML)

The assembler emits a **normalized view-model of typed blocks (structured JSON)**, never HTML strings. Web Blade renders it now; the Flutter app renders the same payload later; print reuses it. The durable artifact is the **block DTO contract** (`block_type`, resolved content with tokens injected, media refs, pricing refs). Renderers stay concrete and per-client. No generic UI-block framework.

Whitelisted variables: KB blocks may contain `{{token}}` from a fixed whitelist (`tooth_name`, `patient_first_name`, `tooth_count`, …). **No logic/conditionals/loops in content.** Tokens resolve at render, *after* translation and any AI-rewrite, so localized/rewritten text keeps its variables. This keeps KB fully generic — personalization happens only at render.

---

## 8. Services + analytics

- `JourneyAssembler` — walk `decision_tree_nodes`, filter by `journey_curations`, hydrate education from `kb_blocks`(+media), hydrate priced options via the Treatment Module API, inject tokens → block DTO. Pure read/merge; stores nothing (except the sent snapshot on send).
- `CasePricingClient` — the only path to money; calls the Treatment Module pricing API; no price caching.
- `CaseSelectionService` — records `case_selections`; recomputes running estimate on the fly.
- `JourneySnapshotService` — writes `journey_sent_snapshots` (send) and `case_consent_snapshots` (accept); enforces supersede-on-edit.
- Accept — delegates to `TreatmentPlanAcceptanceService::accept(..., via: 'case_acceptance')`.
- `KnowledgeBankRetriever` — optional AI Q&A, retrieval bounded to `kb_blocks` only.

**Analytics = the existing ledger, no duplicate table.** `ActivityEngine::log($journey, 'case.<event>', null, $metadata, $relationshipId)` with journey/step context in metadata. Events: `case.opened`, `case.section_viewed`, `case.video_watched`, `case.options_compared`, `case.material_selected`, `case.estimate_viewed`, `case.callback_requested`, `case.appointment_booked`, `case.accepted`, `case.declined`, `case.more_time_requested`. Follow-up automation = **new rules in `config/relationship_rules.php`**, no new engine code. Opportunity: `case.opened` → `syncStage($plan,'quoted')` (opportunity active on view); accept/decline already synced.

---

## 9. Cost-visibility guardrails (both delivery modes ship in V1)

`delivery_mode` + doctor-controlled `cost_visibility` (`full` | `starting_from` | `hidden_until_booking`) per journey. Any unattended/take-home view shows a mandatory frame: *"This is an estimate. Your dentist will confirm the final treatment and cost."* Chairside may default `full`; take-home defaults `starting_from` unless the doctor opts up. `journey_curations` controls which materials/premium options are even visible.

---

## 10. AI policy (removable enhancement — hard invariant)

Fully functional with AI off is the acceptance test. **Allowed:** translation (EN/HI/MR), voice narration, patient-friendly rewriting of authored KB text, end-of-journey summarization, KB-bounded Q&A. **Prohibited:** clinical recommendations, cost calculation, medical facts, treatment indication. Fallbacks: raw KB text, deterministic summary, static FAQ from the same `kb_blocks`. Follows the deterministic-default / Ollama-optional pattern.

---

## 11. Scope

**V1 (ship, then validate acceptance lift on real patients):**
Prerequisite §4.1 `treatment_options` + pricing API. One tree end-to-end — **Missing Tooth → Implant / Bridge / Denture → crown material → estimate** — with full media (scenario goes live when its media is complete; engine built/tested against placeholders in parallel). Both delivery modes + guardrails. Side-by-side comparison (no priority quiz). Live cost via Treatment Module API. Sent + consent snapshots (version pinning). Interactions on the existing ledger; opportunity on open + accept. Replace the presentation builder + public page (coexist behind a flag during rollout).

**V1.5:** Decision Assistant (priority quiz → highlight options; needs priority tags). Locales HI/MR. Voice narration.

---

## 12. Files (anticipated)

Migrations: `treatment_options` (Treatment Module), `kb_topics`, `kb_blocks`, `kb_block_media`, `kb_topic_relations`, `media_assets`, `decision_trees`, `decision_tree_nodes`, `patient_journeys`, `journey_curations`, `case_selections`, `journey_sent_snapshots`, `case_consent_snapshots`. Models + relationships. Services (§8). Controllers: staff `CaseJourneyController` (curate/send from the treatment-plan tab), public `PublicCaseController` (mirrors `PublicPresentationController`). Routes: authenticated `case-journeys/*`; unauthenticated `->withoutMiddleware('auth')->prefix('p')`. Views: interactive builder + patient microsite (Blade + Alpine, small animated sections, progress indicator) consuming the block DTO. Seeders: `database/seeders/KnowledgeBank/*` + one decision tree. Config: rules in `config/relationship_rules.php`. Retire (behind flag): `presentations/builder.blade.php`, `presentations/public/show.blade.php`.

---

## 13. Deliberately deferred (on record — NOT V1)

- Journey-orchestration engine / additional journey types (pre-op, post-op, recall, maintenance) — schema-ready via `journey_type`/`phase`; built only when a second journey is real.
- Rule engine — only a trivial equality matcher over `conditions`; doctor curation is primary.
- Presentation depths beyond `standard` — column reserved, one level authored.
- KB versioning UI / publish-rollback workflow — edit via seeders/repo; a CMS is a later concern.
- Central Dentfluence content-sync API (KB distribution).
- KB relation traversal / auto-recommendation engine — table populated lazily, no engine.
- Media transcoding / resolution ladder / CDN pipeline.
- Multi-tenant row scoping (`clinic_id` on engine tables) — travels with the content-sync decision; not needed in one-instance-per-clinic. Logged as a conscious choice.
- Pay-advance / financing options.
- Membership and "future treatment recommendation" — explicitly **not** journeys; they stay in their own bounded contexts.

---

## 14. Artisan commands (run manually — not executed here)

```
php artisan make:migration create_treatment_options_table
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
# after writing them:
php artisan migrate
php artisan db:seed --class=KnowledgeBankSeeder
php artisan db:seed --class=MissingToothTreeSeeder
composer dump-autoload
```

No destructive commands (`migrate:fresh`, rollback, wipe) — ever.

---

## 15. Frozen decision log (2026-07-15)

1. Journey-shaped schema, single concrete Case Acceptance implementation; no orchestration engine.
2. Renderer returns a normalized block DTO, not HTML.
3. Separate Media Library referenced by KB; `scope` split (global vs clinic/PHI).
4. Whitelisted `{{variables}}` in KB blocks; no logic in content; resolve after translation/AI.
5. Simple equality matcher for `conditions`; no rule engine; doctor curation is primary.
6. `depth` and `conditions` reserved in schema; only `standard` depth authored.
7. Version pinning in V1: immutable sent snapshot (send) + consent snapshot (accept); edit-after-send supersedes, never mutates.
8. `journey_curations` kept as a relational table (analytics/reporting), not JSON.
9. `kb_topic_relations` join table added now (lazy population, no traversal engine).
10. Interactions ride the existing `activities` ledger; no parallel interactions table.
11. Multi-tenant scoping deferred with KB distribution; conscious choice.
12. Prerequisite before engine code: Treatment Module `treatment_options` + pricing API.
