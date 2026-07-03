# Workflow Engine — Proposal for a Future Session

## Build status (read this first)

**All 5 slices CODE-COMPLETE as of 2026-07-03** (built across two sessions
the same day — see [[project_phase5_workflow_engine]] in memory for the
full breakdown). **Nothing has been migrated or tested yet** — every
session so far had no `php` binary in its sandbox, so this is code review
only. Sumit needs to run, in order:

```
php artisan migrate
php artisan test --filter=WorkflowEngineTest
php artisan test --filter=WorkflowShadowRunnerTest
```

- **Slice 1 — Engine core.** `WorkflowEngine` class
  (`app/Services/Workflow/WorkflowEngine.php`) with `start()`/`advance()`/
  `status()`, 3 additive migrations (`workflow_templates`,
  `workflow_instances`, `workflow_step_log`), models
  (`App\Models\WorkflowTemplate`/`WorkflowInstance`/`WorkflowStepLog`), one
  seeded template `rct_staging` (steps mirror the real "Root Canal
  Treatment" stages in `treatments.stages` — diagnosis → access →
  instrumentation → obturation → review → crown). Tests in
  `tests/Unit/WorkflowEngineTest.php`. Flag `workflow.engine` unchanged
  (already declared, default off). No callers — fully dormant.
- **Slice 2 — Shadow-run.** `App\Services\Workflow\WorkflowShadowRunner`,
  wired into `TreatmentVisitService::create()`/`update()` (called AFTER the
  visit's own DB transaction commits, wrapped in try/catch on both sides —
  the runner's own `run()` also self-catches everything down to `Throwable`
  so a shadow-run bug can never block or corrupt a real visit save). New
  migration + model `WorkflowShadowLog` (`workflow_shadow_log` table) records
  `started`/`noop`/`advanced`/`resynced`/`diverged`/`error` per visit save.
  Only visits linked to a `treatment_plan_id` are shadow-run (known scope
  boundary — visits without a plan are skipped, not a bug). Tests in
  `tests/Unit/WorkflowShadowRunnerTest.php`.
- **Slice 3 — Read-only surface.** A small purple "Workflow (preview)" card
  was added to `resources/views/patients/partials/treatment-visits-tab.blade.php`
  (inserted right after the summary-bar, before the visit list/form). Shows
  stage X of Y + next-step gap, sourced from the Slice 2 shadow instance.
  Renders nothing if the flag is off or no shadow instance exists yet.
  Deliberately kept OUT of the complex Alpine data-entry form — pure Blade,
  no JS, doesn't touch `current_stage` logging in any way. **No automated
  test** — the patient show page is too heavy/fragile to safely exercise in
  this session (see feedback memory on that file's Alpine scope). Sumit
  should eyeball a patient with an RCT-linked treatment plan after
  migrating with the flag on.
- **Slice 4 — Cutover decision tooling.** `php artisan workflow:parity
  {template=rct_staging}` (`app/Console/Commands/WorkflowParity.php`)
  reports agreement/divergence stats from the accumulated
  `workflow_shadow_log` — read-only, flips no flag. **The actual cutover
  decision has NOT been made** — it can't be, there's no real shadow data
  yet (nothing's migrated). Once Sumit has run the app for a while with
  `workflow.engine` on and doctors have logged real RCT stages, run this
  command and bring the divergence rows to Sumit before proposing whether
  `current_stage` becomes engine-constrained or stays an advisory sidebar.
- **Slice 5 — Second template.** `implant_staging` seeded (new migration,
  mirrors the "Single Dental Implant" stages: planning → implant_surgery →
  healing → abutment → crown → review). `WorkflowShadowRunner::TEMPLATE_MAP`
  generalized to cover both templates — no runner code is RCT-specific
  anymore.
- **Open judgment calls for whoever reviews the shadow data:** the
  `min_gap_days_from_previous` values seeded on both templates are
  placeholder clinical estimates, not measured from real visit-interval
  data (no DB access in any session so far to check). `rct_staging`:
  access→instrumentation 0, instrumentation→obturation 3, obturation→review
  7, review→crown 14. `implant_staging`: planning/surgery/healing 0,
  healing→abutment 90 (grounded in the Treatment record's own "3–6 months
  osseointegration" description text, low end), abutment→crown 14,
  crown→review 90. Treat all of these as advisory only until
  `workflow:parity` output confirms or contradicts them.

This is a plan to review, not code. Task #24 in the current session was
"decide the sequence for Phase 5" — the decision was to size and scope this
properly rather than start building it inside an already-long session. This
document is that sizing.

## Why this needs its own session

The Workflow Engine is comparable in size to all of Phase 2 (Automation),
which took 6 separate slices across its own dedicated session: a new engine
class, a schema, a shadow-run/parity harness, a cutover, and a soak period
before deleting the old code path. Rushing it into the tail end of a session
that already shipped 3 other pieces risks the same kind of thing already
found twice this session — a fix that looks done but isn't reachable/visible
from the actual UI. This needs room to research the real current behaviour
first, the same way Phase 4 and Phase 5 Pieces 1-2 did.

## What "workflow" already means informally in this codebase today

Before designing a new engine, worth being honest that a form of
"multi-step sequence" already exists, informally, in three places — the
Workflow Engine's job is to formalize these, not invent something new:

- **`TreatmentVisitService`** — each visit has a free-text `current_stage`
  and a `completed_stages` array (`app/Services/TreatmentVisitService.php`).
  A doctor manually types/selects the stage name per visit. There's no
  engine tracking "RCT has 3 stages, you're on stage 2, stage 3 needs X
  first" — it's just a label a human fills in from memory each time.
- **`TreatmentPlan` / `TreatmentPlanItem`** — a plan has `visit_count` and
  `estimated_duration` as free-text hints, not a structured sequence of
  steps with dependencies.
- **`app/Modules/PracticeProtocols/*`** — SOP-style reference content
  (what a protocol *should* involve), not an executable sequence that
  drives real visits/tasks.

The clearest, lowest-risk first real workflow template is **RCT staging**
(Root Canal Treatment: access → cleaning/shaping → obturation → crown, each
usually a separate visit, usually with a minimum gap between some steps) —
it's a genuinely multi-visit, multi-week sequence already informally tracked
via `current_stage`, has clear real value (nobody has to remember which RCT
patients are "due for stage 2"), and doesn't touch billing/communication
directly (lower blast radius than, say, an automated recall workflow).

## Design, following the target architecture doc (§B3)

Per `docs/target-architecture-engine-first.md`, the Workflow Engine
**orchestrates, never executes**. Its job is to know what step a
relationship/patient is on and what step comes next — not to create tasks,
send messages, or decide business policy. Those stay the job of the Task
Engine, Communication Engine, and Rules Engine respectively, exactly like
`AutomationEngine` (Phase 2) only answers timing questions and never sends
anything itself.

**Public interface** (from the target architecture doc, unchanged):
```php
$engine->start(string $template, Relationship $relationship, array $context = []): WorkflowInstance
$engine->advance(WorkflowInstance $instance, string $step): WorkflowInstance
$engine->status(WorkflowInstance $instance): array
```

**Guardrail, restated because it's the whole point:** never inside the
engine: creating tasks directly, sending messages, scheduling timers,
deciding policy. It only tracks "where are we in this sequence" and exposes
that so other engines (Task/Communication/Automation) can react to it.

## Sketch schema (additive, new tables only — nothing existing touched)

```
workflow_templates
  id, key (unique, e.g. "rct_staging"), name, version, steps (json), active

workflow_instances
  id, template_id, relationship_id, subject_type, subject_id (polymorphic —
    e.g. points at the TreatmentPlan or Patient this run belongs to),
  current_step, status (active|completed|abandoned), started_at,
  completed_at, context (json)

workflow_step_log
  id, workflow_instance_id, step, entered_at, exited_at, actor_id, notes
```

`steps` on the template is an ordered JSON array of step definitions —
minimum shape: `{key, label, min_gap_days_from_previous}`. Deliberately not
designing branching/conditional steps yet — RCT staging is linear, and a
linear-only v1 is much easier to reason about and test than guessing at
branching semantics nobody's asked for.

## Slice breakdown (mirrors Phase 2 Automation's shape)

1. **Engine core** — `WorkflowEngine` class with the 3 pure-ish methods
   above, `workflow_templates`/`workflow_instances`/`workflow_step_log`
   migrations, one seeded template (`rct_staging`). Flag
   `workflow.engine`, default off. No callers yet — dormant scaffolding,
   exactly like `AutomationEngine` Slice 2.
2. **Shadow-run on RCT visits** — when a doctor sets `current_stage` on an
   RCT-type `TreatmentVisit` today, ALSO (behind the flag, log-only, never
   blocking) start/advance a shadow `WorkflowInstance` and record whether it
   agrees with what the doctor typed manually. This is the parity-proof
   step, same pattern as Phase 2's recall shadow-run.
3. **Surface it read-only** — show "RCT stage 2 of 4, next visit needs 14
   days minimum gap" somewhere doctors already look (patient profile
   Treatment tab), sourced from the shadow instance, still not gating
   anything.
4. **Cutover decision** — once shadow data proves the engine agrees with
   what doctors have been doing manually, decide whether `current_stage`
   becomes engine-driven (dropdown constrained to valid next steps) or
   stays free-text with the engine as an advisory sidebar. This is a real
   product decision for Sumit, not an architecture one — don't guess.
5. **(Optional, later) A second template** — once RCT staging is proven,
   Implant staging is the next natural candidate (also already has
   informal stage fields in `TreatmentVisitService::rules()`).

## What this proposal deliberately does NOT decide

- Whether workflows should ever auto-create Tasks/reminders when a step is
  overdue (e.g., "stage 2 was due 5 days ago") — that's a Task
  Engine/Automation Engine integration decision for after the core engine
  is proven, not a day-1 feature.
- Branching/conditional step sequences — start linear, add branching only
  if a real template needs it.
- Whether this ever extends to non-clinical sequences (e.g., a
  patient-acquisition nurture sequence) — technically the same engine could
  serve that, but scoping it to one proven clinical template first keeps
  the first slice reviewable.

## Rollout pattern (same as every prior phase)

Additive migrations only, flag default OFF, shadow-logged before enforced,
one seeded template to start, full test suite green before any flag flips,
Sumit runs all `php artisan` commands himself (computer-control cannot type
into terminals — confirmed hard platform limit this session).
