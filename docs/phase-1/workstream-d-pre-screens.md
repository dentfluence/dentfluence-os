# Phase 1 · Workstream D — PRE Screens

Built incrementally in small, visually-verifiable slices. Each slice is additive — the legacy PRM board stays available and unchanged.

## Slice 1 — Relationship Dashboard (built)

A read-only, relationship-first landing page for the receptionist.

| Piece | File |
|---|---|
| Controller | `app/Http/Controllers/Relationship/DashboardController.php` |
| Route | `routes/relationship.php` → `GET /relationship/dashboard` (`relationship.dashboard`) |
| View | `resources/views/relationship/dashboard/index.blade.php` (extends `layouts.app`) |

**Shows:** headline counts (relationships, patients, active leads, open opportunities), a shadow-journey snapshot, the 12 most recent relationships (linking to their profile), and entry points to Today's Actions + Analytics.

**Safety:** additive new route; no PRM route/view touched. Counts use reliable legacy columns. Read-only.

### Verify (you)

1. Log in, visit **`/relationship/dashboard`**.
2. Confirm the page renders inside the normal app shell, cards show sensible numbers, and "View →" opens a relationship profile.
3. Confirm the legacy PRM board still works exactly as before.

Screenshot / tell me anything that looks off (spacing, missing shell, wrong numbers) and I'll adjust before the next slice.

## Slice 2 — Lead Pipeline (built)

A read-only, relationship-centric lead board (kanban) for the receptionist.

| Piece | File |
|---|---|
| Controller | `app/Http/Controllers/Relationship/LeadPipelineController.php` |
| Route | `routes/relationship.php` → `GET /relationship/pipeline` (`relationship.pipeline`) |
| View | `resources/views/relationship/pipeline/index.blade.php` (extends `layouts.app`) |
| Flag | `relationship.pipeline_journey_column` (default **off**) in `config/features.php` |
| Tests | `tests/Feature/Relationship/LeadPipelineTest.php` |

**Shows:** seven columns keyed by the **reliable legacy `leads.stage`** (New Lead → Contacted → Appointment → Consultation → Plan Given → Converted → Lost), per-column count + pipeline value, and lead cards (name → PRE profile, treatment, phone, value, follow-up/overdue + assignee chips). Headline totals up top.

**Shadow journey:** each card can also show its relationship-journey state for context, but only when `relationship.pipeline_journey_column` is on. Journeys are shadow until Phase 4, so grouping never depends on them.

**Safety:** additive new route; the legacy PRM board (`/communication/prm/board`) and all PRM routes/controllers/views are untouched. Read-only — no writes, no migration.

### Verify (you)

1. Log in, visit **`/relationship/pipeline`**.
2. Confirm the board renders in the app shell, leads sit in the right stage columns, counts/values look right, and a card name opens the relationship profile.
3. Confirm the legacy PRM board still works exactly as before.
4. (Optional) Flip the flag on to see the shadow journey line on cards.

## Slice 3 — Opportunity + Recall pipelines (built)

Two read-only, relationship-centric kanban boards for the receptionist.

| Piece | File |
|---|---|
| Controllers | `app/Http/Controllers/Relationship/OpportunityPipelineController.php`, `RecallPipelineController.php` |
| Routes | `routes/relationship.php` → `GET /relationship/opportunities` (`relationship.opportunities`), `GET /relationship/recalls` (`relationship.recalls`) |
| Views | `resources/views/relationship/opportunities/index.blade.php`, `resources/views/relationship/recalls/index.blade.php` |
| Flag | `relationship.opportunity_journey_column` (default **off**) in `config/features.php` |
| Tests | `tests/Feature/Relationship/OpportunityPipelineTest.php`, `RecallPipelineTest.php` |

**Opportunities:** columns from the reliable legacy `treatment_opportunities.status` (Identified → Nurturing → Estimate Given → Committed → Converted → Declined, from `TreatmentOpportunity::STAGES`). Person name comes from the plain **Relationship** spine (never the encrypted Patient fields); card links to the PRE profile. Per-column count + estimated value. Shadow opportunity-journey line per card behind `relationship.opportunity_journey_column` (default off), matched to the opportunity via `metadata.opportunity_id`.

**Recalls:** rows from the legacy `communication_queue` where `purpose = 'recall'` OR `source_engine = 'recall'`, grouped by the reliable legacy status (Pending → Waiting for Patient → Overdue → Closed). Recall journeys aren't synced in shadow yet, so there's no journey column — a faithful legacy read. Cards show name, phone, channel, due/overdue, attempts, assignee.

**Safety:** additive new routes; the legacy Communication List and Opportunity surfaces are untouched. Read-only — no writes, no migration.

### Verify (you)

1. Log in, visit **`/relationship/opportunities`** and **`/relationship/recalls`**.
2. Confirm each board renders in the app shell, items sit in the right status columns, counts/values look right, and (opportunities) a card name opens the relationship profile.
3. Confirm the legacy Communication List + Opportunity surfaces still work unchanged.
4. (Optional) Flip `relationship.opportunity_journey_column` on to see the shadow journey line on opportunity cards.

## Slice 4 — Household profiles (built)

Surfaces the multi-patient "household" relationships (people who share a phone and are linked to one relationship) on the profile page.

| Piece | File |
|---|---|
| Model | `app/Models/Relationship.php` → new `patients()` hasMany (the `patient()` hasOne is kept for back-compat) |
| Controller | `app/Http/Controllers/Relationship/ProfileController.php` → `show()` now passes `$householdPatients` + `$isHousehold` |
| View | `resources/views/relationship/profile/index.blade.php` → Household panel on the **Clinical** tab |
| Tests | `tests/Feature/Relationship/HouseholdProfileTest.php` |

**Shows:** on the Clinical tab, when more than one patient is linked to the relationship, a "Household" panel lists **all** linked patients (name, id, phone, a "Primary" badge on the hasOne patient), each with an "Open record" link. Patients are read branch-scope-free so the whole household is visible.

**Safety:** purely additive and gated by `@if ($isHousehold)`, so single-patient and lead-only profiles render exactly as before (no regression). The primary `$patient` still drives every existing stat and the timeline — unchanged. No writes, no migration.

### Verify (you)

1. Open a household relationship profile (one of the ~18 shared-phone records) and go to the **Clinical** tab.
2. Confirm the "Household" panel lists every linked patient and each "Open record" link works.
3. Open a normal single-patient profile and confirm nothing changed.

## Slice 5 — Relationships index (built)

A searchable / filterable / paginated browse over the whole relationship base — the piece the dashboard's 12-row snapshot was missing.

| Piece | File |
|---|---|
| Controller | `app/Http/Controllers/Relationship/RelationshipListController.php` |
| Route | `routes/relationship.php` → `GET /relationship/list` (`relationship.list`) |
| View | `resources/views/relationship/list/index.blade.php` (extends `layouts.app`) |
| Link in | Relationship dashboard header → "All relationships" |
| Tests | `tests/Feature/Relationship/RelationshipListTest.php` |

**Shows:** inline search (name/phone/email), status chips (Active/Dormant/Lost), Has-lead / Has-patient filters, sortable columns (name, score, since), and 25-per-page pagination that preserves all filters. Read-only; each row links to the relationship profile.

## Note — households

Resolved in slice 4 above: relationships with more than one patient (shared phone) now list all linked patients via the Household panel. `Relationship::patient()` (hasOne) is retained for back-compat; `Relationship::patients()` (hasMany) returns the full set.
